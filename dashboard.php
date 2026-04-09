<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Always refresh user from DB
$u = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$u->bind_param('i', $user_id); $u->execute();
$user = $u->get_result()->fetch_assoc();
$_SESSION['user'] = $user;

$cat_filter = isset($_GET['cat']) ? $conn->real_escape_string($_GET['cat']) : '';
$search     = isset($_GET['q'])   ? $conn->real_escape_string($_GET['q'])   : '';
$page       = $_GET['page'] ?? 'browse';

$eq_sql = "SELECT * FROM equipment WHERE is_active=1";
if ($cat_filter) $eq_sql .= " AND category='$cat_filter'";
if ($search)     $eq_sql .= " AND (name LIKE '%$search%' OR category LIKE '%$search%')";
$eq_sql .= " ORDER BY review_count DESC";
$equipment = $conn->query($eq_sql)->fetch_all(MYSQLI_ASSOC);

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// ── POST ACTIONS ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    // ADD TO CART
    if ($act === 'add_cart') {
        // Gate: ID must be approved
        if (($user['id_status'] ?? 'none') !== 'approved') {
            $_SESSION['booking_error'] = 'id_required';
            header("Location: dashboard.php?page=browse"); exit();
        }
        // Gate: max 3 active rentals
        $active_count = $conn->query("SELECT COUNT(*) FROM rentals WHERE user_id=$user_id AND status='active'")->fetch_row()[0];
        $cart_count   = count($_SESSION['cart']);
        if (($active_count + $cart_count) >= 3) {
            $_SESSION['booking_error'] = 'max_reached';
            header("Location: dashboard.php?page=browse"); exit();
        }
        $eid = intval($_POST['eq_id']);
        $eq  = $conn->query("SELECT * FROM equipment WHERE id=$eid AND is_active=1 LIMIT 1")->fetch_assoc();
        if (!$eq) { header("Location: dashboard.php?page=browse"); exit(); }

        // Check if already in cart, skip duplicate
        $already = array_filter($_SESSION['cart'], fn($i) => $i['id'] === $eid);
        if (!$already) {
            $_SESSION['cart'][] = [
                'id'          => $eq['id'],
                'name'        => $eq['name'],
                'icon'        => $eq['icon'] ?: '🏅',
                'price'       => $eq['price_per_day'],
                'days'        => 1,
                'pickup_date' => null,
                'return_date' => null,
            ];
        }
        header("Location: dashboard.php?page=cart"); exit();
    }

    // SET DATES per cart item (done in cart page)
    if ($act === 'set_dates') {
        $eid        = intval($_POST['eq_id']);
        $today      = date('Y-m-d');
        $min_pickup = date('Y-m-d', strtotime('+3 days')); // must be at least 3 days from today
        $pickup_raw = $_POST['pickup_date'] ?? '';
        $return_raw = $_POST['return_date'] ?? '';
        // pickup must be at least 3 days from now
        $pickup = ($pickup_raw >= $min_pickup) ? $pickup_raw : $min_pickup;
        $days   = max(1, min(3, (int)((strtotime($return_raw) - strtotime($pickup)) / 86400)));
        $return = date('Y-m-d', strtotime("$pickup +$days days"));

        // Availability check
        $av = $conn->prepare("SELECT COUNT(*) as cnt FROM rentals WHERE equipment_id=? AND status='active' AND start_date <= ? AND end_date >= ?");
        $av->bind_param('iss', $eid, $return, $pickup); $av->execute();
        $booked = $av->get_result()->fetch_assoc()['cnt'];
        $eq_stock = $conn->query("SELECT stock FROM equipment WHERE id=$eid")->fetch_row()[0];
        if ($booked >= $eq_stock) {
            $_SESSION['booking_error']      = 'unavailable';
            $_SESSION['booking_error_eq']   = $_SESSION['cart'][array_search($eid, array_column($_SESSION['cart'],'id'))]['name'] ?? 'Equipment';
            $_SESSION['booking_error_date'] = date('F j, Y', strtotime($pickup));
            header("Location: dashboard.php?page=cart"); exit();
        }

        foreach ($_SESSION['cart'] as $key => $item) {
            if ((int)$item['id'] === $eid) {
                $_SESSION['cart'][$key]['days']        = $days;
                $_SESSION['cart'][$key]['pickup_date'] = $pickup;
                $_SESSION['cart'][$key]['return_date'] = $return;
                break;
            }
        }
        session_write_close();
        header("Location: dashboard.php?page=cart"); exit();
    }

    if ($act === 'remove_cart') {
        $eid = intval($_POST['eq_id']);
        $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], fn($i) => (int)$i['id'] !== $eid));
        session_write_close();
        header("Location: dashboard.php?page=cart"); exit();
    }

    if ($act === 'save_notifications') {
        $fields = ['notif_promo_email','notif_promo_sms','notif_reminder_email',
                   'notif_reminder_sms','notif_account_email','notif_account_sms'];
        $vals = [];
        foreach ($fields as $f) { $vals[$f] = isset($_POST[$f]) ? 1 : 0; }
        $conn->query("UPDATE users SET
            notif_promo_email={$vals['notif_promo_email']},
            notif_promo_sms={$vals['notif_promo_sms']},
            notif_reminder_email={$vals['notif_reminder_email']},
            notif_reminder_sms={$vals['notif_reminder_sms']},
            notif_account_email=1,
            notif_account_sms=1
            WHERE id=$user_id");
        $user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
        $_SESSION['user'] = $user;
        $_SESSION['notif_saved'] = true;
        header('Location: dashboard.php?page=profile#notifications'); exit();
    }

    if ($act === 'confirm_booking') {
        // Check all items have dates set
        foreach ($_SESSION['cart'] as $item) {
            if (empty($item['pickup_date'])) {
                $_SESSION['booking_error'] = 'no_dates';
                header("Location: dashboard.php?page=cart"); exit();
            }
        }
        foreach ($_SESSION['cart'] as $item) {
            $disc  = ($user['id_status']==='approved' && in_array($user['id_type'],['student','senior','pwd'])) ? 20 : 0;
            $total = $item['price'] * $item['days'] * (1 - $disc/100);
            $code  = 'KB-' . strtoupper(substr(uniqid(), -4));
            $start = $item['pickup_date'];
            $end   = $item['return_date'];
            $stmt  = $conn->prepare("INSERT INTO rentals (order_code,user_id,equipment_id,days,price_per_day,discount_pct,total_amount,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('siiiidiss', $code, $user_id, $item['id'], $item['days'], $item['price'], $disc, $total, $start, $end);
            $stmt->execute();
            $pts = intval($total / 10);
            $conn->query("UPDATE users SET loyalty_pts = loyalty_pts + $pts WHERE id = $user_id");
        }
        $_SESSION['cart'] = [];
        header("Location: dashboard.php?page=history&booked=1"); exit();
    }

    // ── CANCEL BOOKING ───────────────────────────────────────────
    if ($act === 'cancel_booking') {
        $rid    = intval($_POST['rental_id'] ?? 0);
        $reason = trim($conn->real_escape_string($_POST['cancel_reason'] ?? ''));

        // Verify this rental belongs to this user and is still active & not yet checked out
        $rental = $conn->query("
            SELECT * FROM rentals
            WHERE id=$rid AND user_id=$user_id AND status='active' AND checkout_by IS NULL
            LIMIT 1
        ")->fetch_assoc();

        if ($rental) {
            $conn->query("UPDATE rentals SET status='cancelled', admin_notes='Customer cancelled: $reason' WHERE id=$rid");
            // Restore loyalty points that were awarded at booking
            $pts_to_remove = intval($rental['total_amount'] / 10);
            $conn->query("UPDATE users SET loyalty_pts = GREATEST(0, loyalty_pts - $pts_to_remove) WHERE id=$user_id");
            $_SESSION['cancel_success'] = "Order {$rental['order_code']} has been cancelled.";
        } else {
            $_SESSION['cancel_error'] = "This booking cannot be cancelled. It may have already been picked up or processed.";
        }
        header("Location: dashboard.php?page=history"); exit();
    }
}

// Active rental count for limit display
$active_rental_count = $conn->query("SELECT COUNT(*) FROM rentals WHERE user_id=$user_id AND status='active'")->fetch_row()[0];

// Fetch rental history
$rentals = $conn->query("
    SELECT r.*, e.name as eq_name, e.icon as eq_icon
    FROM rentals r JOIN equipment e ON r.equipment_id = e.id
    WHERE r.user_id = $user_id ORDER BY r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$disc_pct = ($user['id_status']==='approved' && in_array($user['id_type'],['student','senior','pwd'])) ? 20 : 0;
$subtotal  = array_reduce($_SESSION['cart'], fn($s,$i) => $s + $i['price']*$i['days'], 0);
$discount  = round($subtotal * $disc_pct / 100);
$total     = $subtotal - $discount;
$id_labels = ['student'=>'Student ID','senior'=>'Senior Citizen ID','pwd'=>'PWD ID','regular'=>'Regular'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>KineticBorrow — Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,800;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --gold:#C47F2B;--gold-lt:#D9952E;--gold-bg:#FDF3E3;
      --green:#2E8B57;--green-bg:#EAF6EE;
      --red:#C0392B;--red-bg:#FDECEA;
      --blue:#2563EB;--blue-bg:#EEF3FD;
      --bg:#F7F5F2;--border:#E5E0D8;--border2:#D0C8BC;
      --muted:#8A8078;--text:#1C1916;--text2:#4A4540;
    }
    body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;}

    /* NAV */
    nav{background:#fff;border-bottom:1px solid var(--border);padding:0 40px;height:66px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 1px 8px rgba(0,0,0,.06);}
    .brand{display:flex;align-items:center;gap:8px;text-decoration:none;}
    .brand-name{font-family:'Playfair Display',serif;font-size:20px;font-weight:800;color:var(--text);}
    .brand-name span{color:var(--gold);}
    .nav-links{display:flex;gap:4px;}
    .nav-btn{background:none;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;padding:8px 16px;border-radius:6px;color:var(--text2);transition:all .2s;text-decoration:none;display:inline-block;}
    .nav-btn:hover,.nav-btn.active{background:var(--gold-bg);color:var(--gold);}
    .nav-btn.active{font-weight:600;}
    .cart-badge{background:var(--gold);color:#fff;border-radius:10px;padding:1px 7px;font-size:10px;font-weight:700;margin-left:4px;}
    .nav-user{display:flex;align-items:center;gap:12px;}
    .avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#8B5E1A);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;}
    .logout-btn{border:1px solid var(--border);color:var(--muted);padding:5px 12px;border-radius:6px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:12px;transition:all .18s;text-decoration:none;display:inline-block;background:none;}
    .logout-btn:hover{border-color:var(--red);color:var(--red);}

    .container{max-width:1400px;margin:0 auto;padding:32px 32px;}
    .page-title{font-family:'Playfair Display',serif;font-size:28px;font-weight:800;margin-bottom:6px;}
    .page-sub{font-size:13px;color:var(--muted);margin-bottom:24px;}

    /* HERO */
    .hero{background:linear-gradient(120deg,#FDF0DC 0%,#FFF9F0 60%);border:1px solid #EDD8B0;border-radius:18px;padding:28px 36px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;}
    .hero-label{font-size:11px;color:var(--gold);letter-spacing:.14em;text-transform:uppercase;margin-bottom:6px;font-weight:600;}
    .hero-title{font-family:'Playfair Display',serif;font-size:30px;font-weight:800;line-height:1.1;margin-bottom:10px;}
    .hero-title span{color:var(--gold);}
    .hero-sub{font-size:13px;color:var(--muted);max-width:320px;line-height:1.6;}

    /* ID GATE BANNER */
    .id-gate-banner{background:linear-gradient(120deg,#FEF3E2,#FFF8F0);border:1.5px solid #FADDB8;border-radius:14px;padding:18px 22px;margin-bottom:22px;display:flex;align-items:center;gap:16px;}
    .id-gate-icon{font-size:32px;flex-shrink:0;}
    .id-gate-text{flex:1;}
    .id-gate-title{font-size:14px;font-weight:700;color:#92530A;margin-bottom:3px;}
    .id-gate-sub{font-size:12px;color:#B87333;line-height:1.5;}
    .id-gate-btn{background:#E07C35;color:#fff;border:none;padding:9px 18px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;white-space:nowrap;flex-shrink:0;display:inline-block;transition:background .2s;}
    .id-gate-btn:hover{background:#C0602A;}

    /* FILTERS */
    .filter-row{display:flex;gap:12px;align-items:center;margin-bottom:22px;flex-wrap:wrap;}
    .search-wrap{position:relative;flex:1;min-width:200px;}
    .search-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:14px;color:#B0A898;pointer-events:none;}
    .search-input{background:#fff;border:1px solid var(--border);border-radius:10px;padding:10px 16px 10px 42px;color:var(--text);outline:none;width:100%;font-family:'DM Sans',sans-serif;font-size:14px;transition:all .2s;box-shadow:0 1px 4px rgba(0,0,0,.04);}
    .search-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(196,127,43,.1);}
    .search-input::placeholder{color:#C0B8AE;}
    .pills{display:flex;gap:8px;flex-wrap:wrap;}
    .pill{background:#fff;border:1px solid var(--border);border-radius:20px;padding:6px 16px;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:500;cursor:pointer;transition:all .18s;color:var(--text2);text-decoration:none;display:inline-block;}
    .pill:hover,.pill.active{background:var(--gold);border-color:var(--gold);color:#fff;}

    /* CARDS */
    .eq-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;}
    @media(max-width:600px){.eq-grid{grid-template-columns:repeat(2,1fr);}}
    .card{background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden;transition:all .25s;box-shadow:0 2px 8px rgba(0,0,0,.05);cursor:pointer;}
    .card:hover{border-color:var(--gold);transform:translateY(-4px);box-shadow:0 12px 32px rgba(196,127,43,.14);}
    .card-img{background:#FDF8F2;height:190px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;}
    .card-img img{width:100%;height:190px;object-fit:cover;display:block;transition:transform .3s;}
    .card:hover .card-img img{transform:scale(1.06);}
    .card-img-emoji{font-size:64px;}
    .card-stock{position:absolute;top:10px;right:10px;background:#fff;border:1px solid var(--border);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;color:var(--text2);}
    .card-stock.low{color:var(--red);border-color:#F5C6C2;background:var(--red-bg);}
    .card-body{padding:14px 14px 16px;}
    .card-name{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;margin-bottom:2px;line-height:1.2;}
    .card-cat{font-size:12px;color:var(--muted);margin-bottom:10px;}
    .card-foot{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
    .card-price{font-family:'Playfair Display',serif;font-size:20px;color:var(--gold);font-weight:700;}
    .card-price span{font-family:'DM Sans',sans-serif;font-size:12px;color:var(--muted);}
    .card-rating{font-size:12px;color:var(--muted);}
    .tag{font-size:10px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;padding:3px 8px;border-radius:4px;margin-bottom:8px;display:inline-block;}
    .tag-pop{background:var(--gold-bg);color:var(--gold);border:1px solid #EDD8B0;}
    .tag-std{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
    .tag-lmt{background:var(--red-bg);color:var(--red);border:1px solid #F5C6C2;}
    .tag-wkd{background:var(--blue-bg);color:var(--blue);border:1px solid #C0D4F8;}
    .gold-btn{background:var(--gold);color:#fff;border:none;padding:11px 24px;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;transition:all .2s;width:100%;}
    .gold-btn:hover{background:var(--gold-lt);transform:translateY(-1px);box-shadow:0 4px 12px rgba(196,127,43,.3);}
    .ghost-btn{background:transparent;color:var(--text2);border:1px solid var(--border);padding:10px 22px;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;transition:all .2s;width:100%;margin-top:8px;}
    .ghost-btn:hover{border-color:var(--gold);color:var(--gold);background:var(--gold-bg);}

    /* EQUIPMENT INFO MODAL */
    .eq-overlay{position:fixed;inset:0;background:rgba(0,0,0,.52);z-index:1000;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px);padding:16px;}
    .eq-overlay.show{display:flex;}
    .eq-modal{background:#fff;border-radius:22px;width:540px;max-width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 28px 80px rgba(0,0,0,.22);position:relative;animation:slideUp .25s ease;}
    .eq-modal-img{width:100%;max-height:260px;object-fit:cover;border-radius:22px 22px 0 0;display:block;}
    .eq-modal-img-placeholder{background:var(--gold-bg);height:200px;display:flex;align-items:center;justify-content:center;font-size:72px;border-radius:22px 22px 0 0;}
    .eq-modal-body{padding:24px 28px 28px;}
    .eq-modal-close{position:absolute;top:14px;right:16px;background:rgba(0,0,0,.35);border:none;width:32px;height:32px;border-radius:50%;font-size:16px;cursor:pointer;color:#fff;display:flex;align-items:center;justify-content:center;transition:background .2s;}
    .eq-modal-close:hover{background:rgba(0,0,0,.6);}

    /* BOOKING MODAL */
    .bk-overlay{position:fixed;inset:0;background:rgba(0,0,0,.48);z-index:1000;display:none;align-items:center;justify-content:center;backdrop-filter:blur(3px);}
    .bk-overlay.show{display:flex;}
    .bk-modal{background:#fff;border-radius:20px;padding:32px 30px;width:440px;max-width:95vw;box-shadow:0 24px 64px rgba(0,0,0,.2);position:relative;animation:slideUp .25s ease;}
    @keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    .bk-close{position:absolute;top:14px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:#C0B8AE;line-height:1;}
    .bk-close:hover{color:var(--text);}
    .bk-header{display:flex;align-items:center;gap:14px;margin-bottom:22px;}
    .bk-icon{font-size:36px;background:var(--gold-bg);border-radius:12px;width:58px;height:58px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .bk-eq-name{font-family:'Playfair Display',serif;font-size:18px;font-weight:800;color:var(--text);}
    .bk-eq-price{font-size:13px;color:var(--gold);font-weight:600;margin-top:2px;}
    .bk-info-bar{background:var(--gold-bg);border:1px solid #EDD8B0;border-radius:10px;padding:10px 14px;font-size:12px;color:var(--text2);margin-bottom:18px;display:flex;gap:8px;align-items:center;}
    .bk-date-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;}
    .bk-field label{font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:6px;}
    .bk-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:10px 12px;font-family:'DM Sans',sans-serif;font-size:14px;color:var(--text);outline:none;background:var(--bg);transition:all .2s;}
    .bk-input:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(196,127,43,.1);}
    .bk-summary{background:var(--bg);border-radius:10px;padding:13px 15px;margin-bottom:18px;border:1px solid var(--border);}
    .bk-sum-row{display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;}
    .bk-sum-row:last-child{font-weight:700;color:var(--gold);font-size:14px;margin-bottom:0;padding-top:8px;border-top:1px dashed var(--border);}
    .bk-btn{width:100%;background:var(--gold);color:#fff;border:none;padding:13px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:all .2s;}
    .bk-btn:hover{background:var(--gold-lt);transform:translateY(-1px);box-shadow:0 6px 16px rgba(196,127,43,.3);}

    /* POPUP TOASTS */
    .toast{position:fixed;top:24px;left:50%;transform:translateX(-50%) translateY(-16px);background:#fff;border-radius:14px;padding:16px 20px;box-shadow:0 12px 40px rgba(0,0,0,.16);z-index:2000;display:flex;gap:13px;align-items:flex-start;min-width:320px;max-width:500px;opacity:0;transition:all .3s;pointer-events:none;}
    .toast.show{opacity:1;transform:translateX(-50%) translateY(0);pointer-events:auto;}
    .toast.toast-error{border-left:4px solid var(--red);}
    .toast.toast-warn{border-left:4px solid var(--gold);}
    .toast-icon{font-size:22px;flex-shrink:0;margin-top:1px;}
    .toast-title{font-size:14px;font-weight:700;color:var(--text);margin-bottom:3px;}
    .toast-msg{font-size:12px;color:var(--muted);line-height:1.55;}
    .toast-msg a{color:var(--gold);font-weight:600;}
    .toast-x{background:none;border:none;cursor:pointer;color:#C0B8AE;font-size:16px;margin-left:auto;flex-shrink:0;align-self:flex-start;}

    /* CART */
    .cart-layout{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;}
    .cart-items{display:flex;flex-direction:column;gap:12px;}
    .cart-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 6px rgba(0,0,0,.04);}
    .cart-emoji{font-size:32px;background:var(--gold-bg);border-radius:12px;width:56px;height:56px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .cart-info{flex:1;min-width:0;}
    .cart-name{font-family:'Playfair Display',serif;font-size:15px;font-weight:700;margin-bottom:2px;}
    .cart-rate{font-size:12px;color:var(--muted);}
    .cart-dates{font-size:11px;color:var(--gold);font-weight:600;margin-top:4px;}
    .cart-days{font-size:11px;background:var(--gold-bg);border:1px solid #EDD8B0;border-radius:6px;padding:2px 8px;color:var(--gold);font-weight:700;display:inline-block;margin-top:4px;}
    .cart-sub{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;color:var(--gold);min-width:76px;text-align:right;}
    .remove-btn{background:none;border:none;color:#C0B8AE;cursor:pointer;font-size:20px;margin-left:4px;transition:color .15s;flex-shrink:0;}
    .remove-btn:hover{color:var(--red);}
    .summary-box{background:#fff;border:1px solid var(--border);border-radius:14px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,.06);}
    .summary-title{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;margin-bottom:18px;}
    .summary-rows{display:flex;flex-direction:column;gap:10px;margin-bottom:18px;}
    .summary-row{display:flex;justify-content:space-between;font-size:13px;}
    .summary-row .lbl{color:var(--muted);}
    .summary-row .val{color:var(--text);font-weight:500;}
    .summary-row.disc .lbl,.summary-row.disc .val{color:var(--green);}
    .summary-divider{border-top:1px solid var(--border);padding-top:12px;}
    .summary-total-lbl{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;}
    .summary-total-val{font-family:'Playfair Display',serif;font-size:21px;font-weight:800;color:var(--gold);}
    .summary-note{font-size:11px;color:var(--muted);text-align:center;margin-top:14px;line-height:1.5;}
    .id-box{background:#fff;border:1px solid var(--border);border-radius:14px;padding:18px 20px;box-shadow:0 1px 6px rgba(0,0,0,.04);margin-top:14px;}
    .id-title{font-family:'Playfair Display',serif;font-size:15px;font-weight:700;margin-bottom:6px;}
    .verified-pill{display:inline-flex;align-items:center;gap:6px;background:var(--green-bg);border:1px solid #A8D8B8;border-radius:20px;padding:5px 14px;font-size:12px;color:var(--green);font-weight:500;margin-top:8px;}
    .empty-state{text-align:center;padding:60px 0;color:var(--muted);}
    .empty-icon{font-size:48px;margin-bottom:12px;}

    /* HISTORY */
    .history-list{display:flex;flex-direction:column;gap:12px;}
    .history-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:18px 22px;display:flex;align-items:center;gap:20px;box-shadow:0 1px 6px rgba(0,0,0,.04);}
    .history-card.active-order{border-left:4px solid var(--green);}
    .order-id-lbl{font-size:10px;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-bottom:2px;}
    .order-id-val{font-family:'Playfair Display',serif;font-size:15px;font-weight:700;color:var(--gold);}
    .order-info{flex:1;}
    .order-name{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;margin-bottom:2px;}
    .order-date{font-size:12px;color:var(--muted);}
    .status-badge{padding:4px 12px;border-radius:20px;font-size:12px;font-weight:500;white-space:nowrap;}
    .status-active{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
    .status-returned{background:#F2F0EE;color:var(--muted);border:1px solid var(--border);}
    .order-total{font-family:'Playfair Display',serif;font-size:17px;font-weight:700;min-width:80px;text-align:right;}
    .loyalty-banner{margin-top:22px;background:linear-gradient(120deg,#FDF0DC,#FFF9F2);border:1px solid #EDD8B0;border-radius:14px;padding:22px 26px;display:flex;align-items:center;justify-content:space-between;}
    .loyalty-pts{font-family:'Playfair Display',serif;font-size:28px;font-weight:800;}
    .loyalty-pts span{font-size:14px;color:var(--muted);font-weight:400;}

    /* PROFILE */
    .profile-wrap{max-width:620px;margin:0 auto;}
    .profile-card{background:linear-gradient(135deg,#FDF0DC,#FFF9F2);border:1px solid #EDD8B0;border-radius:18px;padding:30px;margin-bottom:16px;text-align:center;}
    .profile-avatar{width:76px;height:76px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#8B5E1A);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#fff;margin:0 auto 14px;}
    .profile-name{font-family:'Playfair Display',serif;font-size:22px;font-weight:800;margin-bottom:2px;}
    .profile-email{font-size:13px;color:var(--muted);margin-bottom:14px;}
    .stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;}
    .stat-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:18px 20px;}
    .stat-val{font-family:'Playfair Display',serif;font-size:22px;font-weight:800;color:var(--gold);}
    .stat-lbl{font-size:12px;color:var(--muted);margin-top:2px;}
    .settings-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:20px 22px;margin-bottom:16px;}
    .settings-title{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;margin-bottom:16px;}
    .settings-item{display:flex;align-items:center;justify-content:space-between;padding:13px 0;border-bottom:1px solid #F0EDE8;cursor:pointer;transition:padding .15s;text-decoration:none;}
    .settings-item:last-child{border-bottom:none;}
    .settings-item:hover{padding-left:6px;}
    .settings-item-lbl{font-size:14px;color:var(--text2);}
    .settings-item-lbl.danger{color:var(--red);}
    .settings-arrow{color:#C0B8AE;font-size:16px;}

    /* NOTIFICATION CHECKBOXES */
    .notif-check{width:26px;height:26px;border-radius:7px;border:2px solid var(--border);background:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;transition:all .18s;flex-shrink:0;}
    .notif-check.on{background:var(--gold);border-color:var(--gold);color:#fff;}
    .notif-check.locked{background:#E8E4DF;border-color:#D0C8BC;color:#B0A898;cursor:default;}
    .alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;font-weight:500;}
    .alert-success{background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;}
  </style>
</head>
<body>

<!-- NAV -->
<nav>
  <div style="flex:1;display:flex;justify-content:flex-start;">
    <a class="brand" href="dashboard.php">
      <span style="font-size:22px">🏋️</span>
      <span class="brand-name">Kinetic<span>Borrow</span></span>
    </a>
  </div>
  <div class="nav-links" style="flex:1;justify-content:center;display:flex;">
    <a class="nav-btn <?= $page==='browse'?'active':'' ?>" href="dashboard.php?page=browse">Browse</a>
    <a class="nav-btn <?= $page==='history'?'active':'' ?>" href="dashboard.php?page=history">History</a>
  </div>
  <div class="nav-user" style="flex:1;justify-content:flex-end;">
    <a class="nav-btn <?= $page==='cart'?'active':'' ?>" href="dashboard.php?page=cart" style="position:relative">
      <i class="fa-solid fa-cart-shopping"></i>
      <?php if(count($_SESSION['cart'])>0): ?><span class="cart-badge"><?= count($_SESSION['cart']) ?></span><?php endif; ?>
    </a>
    <a href="dashboard.php?page=profile" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit;">
      <div class="avatar"><?= strtoupper(substr($user['first_name'],0,1)) ?></div>
      <span class="brand-name" style="font-size:14px;"><?= htmlspecialchars($user['first_name'].' '.substr($user['last_name'],0,1)) ?>.</span>
    </a>
    <a class="logout-btn" href="logout.php">Log Out</a>
  </div>
</nav>

<div class="container">
<?php if(isset($_GET['booked'])): ?>
<div class="alert alert-success">✅ Booking confirmed! Your equipment will be ready for pick-up on your selected date. Loyalty points added!</div>
<?php endif; ?>

<!-- ══ BROWSE ══ -->
<?php if($page==='browse'): ?>

  <?php
  // ID gate banner — show if not verified
  $id_st = $user['id_status'] ?? 'none';
  if ($id_st !== 'approved'):
  ?>
  <div class="id-gate-banner">
    <div class="id-gate-icon">
      <?= $id_st==='pending' ? '⏳' : ($id_st==='rejected' ? '❌' : '🪪') ?>
    </div>
    <div class="id-gate-text">
      <?php if($id_st==='pending'): ?>
        <div class="id-gate-title">ID Verification In Progress</div>
        <div class="id-gate-sub">Your ID is being reviewed. You'll be able to book equipment once it's approved — usually within 24 hours.</div>
      <?php elseif($id_st==='rejected'): ?>
        <div class="id-gate-title">ID Verification Failed — Resubmit Required</div>
        <div class="id-gate-sub">Your ID was rejected. Please upload a clearer photo to get verified and start booking equipment.</div>
      <?php else: ?>
        <div class="id-gate-title">Verify Your ID to Start Booking</div>
        <div class="id-gate-sub">ID verification is required before booking. It ensures equipment safety and accountability — and unlocks discounts for Students, Seniors, and PWD.</div>
      <?php endif; ?>
    </div>
    <a class="id-gate-btn" href="dashboard.php?page=profile">
      <?= $id_st==='pending' ? 'Check Status →' : 'Verify ID Now →' ?>
    </a>
  </div>
  <?php endif; ?>

  <div class="hero">
    <div>
      <p class="hero-label">Sports Equipment Rental</p>
      <h1 class="hero-title">Gear Up. <span>Play Hard.</span></h1>
      <p class="hero-sub">Browse, reserve, and rent premium sports equipment — pick your dates and we'll have it ready.</p>
    </div>
    <div style="font-size:72px">🏆</div>
  </div>

  <form method="GET" action="dashboard.php" style="display:contents">
    <input type="hidden" name="page" value="browse"/>
    <div class="filter-row">
      <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input class="search-input" name="q" placeholder="Search equipment..." value="<?= htmlspecialchars($search) ?>"/>
      </div>
      <div class="pills">
        <a class="pill <?= !$cat_filter?'active':'' ?>" href="dashboard.php?page=browse">All</a>
        <?php foreach(['Cycling','Racket Sports','Water Sports','Combat Sports','Team Sports'] as $c): ?>
        <a class="pill <?= $cat_filter===$c?'active':'' ?>" href="dashboard.php?page=browse&cat=<?= urlencode($c) ?>"><?= $c ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </form>

  <?php
  $cart_eq_count = count($_SESSION['cart'] ?? []);
  $total_eq_count = ($active_rental_count ?? 0) + $cart_eq_count;
  if ($total_eq_count >= 3):
  ?>
  <div style="background:#FFF2F2;border:1.5px solid #FFAAAA;border-radius:14px;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:14px">
    <span style="font-size:28px">🚫</span>
    <div>
      <div style="font-weight:700;font-size:14px;color:var(--red)">Rental Limit Reached (3/3)</div>
      <div style="font-size:13px;color:var(--red);margin-top:2px">You have <?= $total_eq_count ?> active/pending rental<?= $total_eq_count>1?'s':'' ?>. Return equipment or remove cart items to book more.</div>
    </div>
    <a href="dashboard.php?page=history" style="background:var(--red);color:#fff;padding:9px 18px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;white-space:nowrap;margin-left:auto">View Rentals →</a>
  </div>
  <?php endif; ?>

  <div class="eq-grid">
    <?php foreach($equipment as $eq): ?>
    <?php $tl=$eq['tag']??''; $tc=$tl==='Popular'?'tag-pop':($tl==='Student Deal'?'tag-std':($tl==='Limited'?'tag-lmt':($tl?'tag-wkd':''))); ?>
    <?php $eq_json = htmlspecialchars(json_encode([
        'id'    => $eq['id'],
        'name'  => $eq['name'],
        'cat'   => $eq['category'],
        'price' => $eq['price_per_day'],
        'stock' => $eq['stock'],
        'image' => $eq['image'] ?? '',
        'desc'  => $eq['description'] ?? '',
        'rating'=> $eq['rating'],
        'count' => $eq['review_count'],
        'tag'   => $tl,
    ]), ENT_QUOTES); ?>
    <div class="card">
      <div class="card-img" onclick="openEqModal(<?= $eq_json ?>)">
        <?php if(!empty($eq['image'])): ?>
          <img src="<?= htmlspecialchars($eq['image']) ?>"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"/>
          <span style="display:none;font-size:52px;align-items:center;justify-content:center;width:100%;height:100%"><?= $eq['icon'] ?: '🏅' ?></span>
        <?php else: ?>
          <span style="font-size:52px"><?= $eq['icon'] ?: '🏅' ?></span>
        <?php endif; ?>
        <?php if($eq['stock']<=2&&$eq['stock']>0): ?><span class="card-stock low">Only <?= $eq['stock'] ?> left</span><?php endif; ?>
        <?php if($eq['stock']<=0): ?><span class="card-stock low">Out of Stock</span><?php endif; ?>
      </div>
      <div class="card-body">
        <?php if($tl): ?><span class="tag <?= $tc ?>"><?= htmlspecialchars($tl) ?></span><?php endif; ?>
        <p class="card-name" style="cursor:pointer" onclick="openEqModal(<?= $eq_json ?>)"><?= htmlspecialchars($eq['name']) ?></p>
        <p class="card-cat"><?= htmlspecialchars($eq['category']) ?></p>
        <div class="card-foot">
          <span class="card-price">₱<?= number_format($eq['price_per_day'],0) ?><span>/day</span></span>
          <span class="card-rating">⭐ <?= $eq['rating'] ?> (<?= $eq['review_count'] ?>)</span>
        </div>
        <?php if($eq['stock']>0): ?>
        <form method="POST" action="dashboard.php?page=browse">
          <input type="hidden" name="act" value="add_cart"/>
          <input type="hidden" name="eq_id" value="<?= $eq['id'] ?>"/>
          <button type="submit" class="gold-btn">🛒 Add to Cart</button>
        </form>
        <?php else: ?>
        <button class="gold-btn" disabled style="opacity:.5;cursor:not-allowed">Not Available</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($equipment)): ?>
    <div class="empty-state" style="grid-column:1/-1"><div class="empty-icon">🔍</div><p>No equipment found.</p></div>
    <?php endif; ?>
  </div>

<!-- ══ EQUIPMENT INFO MODAL ══ -->
<div class="eq-overlay" id="eq-overlay" onclick="if(event.target===this)closeEqModal()">
  <div class="eq-modal" id="eq-modal">
    <button class="eq-modal-close" onclick="closeEqModal()">✕</button>
    <div id="eq-modal-img-wrap"></div>
    <div class="eq-modal-body">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:6px">
        <div>
          <p id="eq-modal-name" style="font-family:'Playfair Display',serif;font-size:22px;font-weight:800;color:var(--text);margin-bottom:2px"></p>
          <p id="eq-modal-cat" style="font-size:13px;color:var(--muted)"></p>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <p id="eq-modal-price" style="font-family:'Playfair Display',serif;font-size:24px;font-weight:800;color:var(--gold)"></p>
          <p id="eq-modal-rating" style="font-size:12px;color:var(--muted);margin-top:2px"></p>
        </div>
      </div>

      <div id="eq-modal-tag-wrap" style="margin-bottom:10px"></div>

      <div id="eq-modal-desc" style="font-size:14px;color:var(--text2);line-height:1.7;margin-bottom:18px;padding:14px;background:var(--bg);border-radius:10px;border:1px solid var(--border)"></div>

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
        <div style="display:flex;align-items:center;gap:8px">
          <span id="eq-modal-stock-dot" style="width:10px;height:10px;border-radius:50%;display:inline-block"></span>
          <span id="eq-modal-stock-txt" style="font-size:13px;font-weight:600"></span>
        </div>
        <span style="font-size:12px;color:var(--muted)">Max rental: <strong>3 days</strong></span>
      </div>

      <form method="POST" action="dashboard.php?page=browse" id="eq-modal-form">
        <input type="hidden" name="act" value="add_cart"/>
        <input type="hidden" name="eq_id" id="eq-modal-eq-id"/>
        <button type="submit" id="eq-modal-btn" class="gold-btn" style="font-size:15px;padding:14px">🛒 Add to Cart</button>
      </form>
    </div>
  </div>
</div>

<!-- ══ CART ══ -->
<?php elseif($page==='cart'): ?>
  <h2 class="page-title">Your Cart</h2>
  <p class="page-sub"><?= count($_SESSION['cart']) ?> item<?= count($_SESSION['cart'])!==1?'s':'' ?> reserved</p>

  <?php if(empty($_SESSION['cart'])): ?>
  <div class="empty-state"><div class="empty-icon">🛒</div><p>Your cart is empty</p><a class="gold-btn" href="dashboard.php?page=browse" style="display:inline-block;width:auto;padding:11px 24px;text-decoration:none;margin-top:16px">Browse Equipment</a></div>
  <?php else: ?>
  <div class="cart-layout">
    <div>
      <div class="cart-items">
        <?php
        $today_dt = date('Y-m-d');
        $min_pickup_dt = date('Y-m-d', strtotime('+3 days'));
        foreach($_SESSION['cart'] as $item):
          $has_dates = !empty($item['pickup_date']);
        ?>
        <div class="cart-card" style="flex-direction:column;align-items:stretch;gap:0">
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:<?=$has_dates?'12':'0'?>px">
            <div class="cart-emoji"><?= $item['icon'] ?></div>
            <div class="cart-info" style="flex:1">
              <p class="cart-name"><?= htmlspecialchars($item['name']) ?></p>
              <p class="cart-rate">₱<?= number_format($item['price'],0) ?>/day</p>
              <?php if($has_dates): ?>
              <p class="cart-dates" style="color:var(--green)">✅ <?= date('M j', strtotime($item['pickup_date'])) ?> → <?= date('M j, Y', strtotime($item['return_date'])) ?> · <?= $item['days'] ?> day<?= $item['days']!=1?'s':'' ?></p>
              <?php else: ?>
              <p class="cart-dates" style="color:var(--red);font-weight:600">📅 Pick a date below to continue</p>
              <?php endif; ?>
            </div>
            <div style="text-align:right">
              <?php if($has_dates): ?><span class="cart-sub">₱<?= number_format($item['price']*$item['days'],0) ?></span><?php endif; ?>
              <form method="POST" action="dashboard.php?page=cart" style="display:block;margin-top:4px">
                <input type="hidden" name="act" value="remove_cart"/>
                <input type="hidden" name="eq_id" value="<?= $item['id'] ?>"/>
                <button type="submit" class="remove-btn">×</button>
              </form>
            </div>
          </div>
          <!-- Date picker for this item -->
          <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:14px;margin-top:8px" <?= !$has_dates ? 'data-needs-dates="1"' : '' ?>>
            <form method="POST" action="dashboard.php?page=cart">
              <input type="hidden" name="act" value="set_dates"/>
              <input type="hidden" name="eq_id" value="<?= $item['id'] ?>"/>
              <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end">
                <div>
                  <label style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:5px">📅 Pick-up Date</label>
                  <input type="date" name="pickup_date" id="pu-<?= $item['id'] ?>"
                    value="<?= $item['pickup_date'] ?: date('Y-m-d', strtotime('+3 days')) ?>"
                    min="<?= date('Y-m-d', strtotime('+3 days')) ?>"
                    style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:8px 10px;font-family:'DM Sans',sans-serif;font-size:13px;background:#fff"
                    onchange="cartDateUpdate(<?= $item['id'] ?>)"/>
                </div>
                <div>
                  <label style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:5px">🔁 Return Date</label>
                  <input type="date" name="return_date" id="rt-<?= $item['id'] ?>"
                    value="<?= $item['return_date'] ?: date('Y-m-d', strtotime('+4 days')) ?>"
                    min="<?= date('Y-m-d', strtotime(($item['pickup_date'] ?? date('Y-m-d', strtotime('+3 days'))).' +1 day')) ?>"
                    max="<?= date('Y-m-d', strtotime(($item['pickup_date'] ?? date('Y-m-d', strtotime('+3 days'))).' +3 days')) ?>"
                    style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:8px 10px;font-family:'DM Sans',sans-serif;font-size:13px;background:#fff"
                    onchange="cartDateUpdate(<?= $item['id'] ?>)"/>
                </div>
                <button type="submit" style="background:var(--gold);color:#fff;border:none;border-radius:8px;padding:9px 14px;font-weight:600;font-size:13px;cursor:pointer;white-space:nowrap"><?= $has_dates?'✓ Update':'Set Dates' ?></button>
              </div>
              <div id="cart-preview-<?= $item['id'] ?>" style="font-size:12px;color:var(--muted);margin-top:7px">
                ℹ️ Earliest pick-up: <strong><?= date('M j', strtotime('+3 days')) ?></strong> (3-day lead time) · Max rental: <strong>3 days</strong>
              </div>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="id-box">
        <p class="id-title">🪪 ID Discount</p>
        <?php if($disc_pct>0): ?>
        <p style="font-size:12px;color:var(--muted)">Your <?= $id_labels[$user['id_type']] ?> gives you a <?= $disc_pct ?>% discount.</p>
        <span class="verified-pill">✅ <?= $id_labels[$user['id_type']] ?> Verified — <?= $disc_pct ?>% off</span>
        <?php else: ?>
        <p style="font-size:12px;color:var(--muted)">No eligible ID verified. <a href="dashboard.php?page=profile" style="color:var(--gold);font-weight:600">Verify your ID</a> to unlock discounts.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="summary-box">
      <p class="summary-title">Order Summary</p>
      <div class="summary-rows">
        <div class="summary-row"><span class="lbl">Subtotal</span><span class="val">₱<?= number_format($subtotal,0) ?></span></div>
        <?php if($discount>0): ?>
        <div class="summary-row disc"><span class="lbl"><?= ucfirst($user['id_type']) ?> Discount (<?= $disc_pct ?>%)</span><span class="val">−₱<?= number_format($discount,0) ?></span></div>
        <?php endif; ?>
        <div class="summary-row summary-divider">
          <span class="summary-total-lbl">Total</span>
          <span class="summary-total-val">₱<?= number_format($total,0) ?></span>
        </div>
      </div>
      <div id="dates-warning" style="display:none;background:#FDECEA;color:#C0392B;border:1px solid #F5C6C2;border-radius:10px;padding:10px 14px;font-size:13px;font-weight:500;margin-bottom:10px">
        ⚠️ Please set pick-up dates for all items first.
      </div>
      <form method="POST" action="dashboard.php?page=cart" id="booking-form">
        <input type="hidden" name="act" value="confirm_booking"/>
        <button type="submit" id="confirm-btn" class="gold-btn" onclick="return checkDates()">Confirm Booking →</button>
      </form>
      <a href="dashboard.php?page=browse" class="ghost-btn" style="display:block;text-align:center;text-decoration:none">Continue Browsing</a>
      <p class="summary-note">Equipment will be prepared for pick-up on your selected date.</p>
    </div>
  </div>
  <?php endif; ?>

<!-- ══ HISTORY ══ -->
<?php elseif($page==='history'): ?>
  <h2 class="page-title">Rental History</h2>
  <p class="page-sub">All your past and active rentals</p>

  <?php
  $cancel_success = $_SESSION['cancel_success'] ?? ''; unset($_SESSION['cancel_success']);
  $cancel_error   = $_SESSION['cancel_error']   ?? ''; unset($_SESSION['cancel_error']);
  ?>
  <?php if($cancel_success): ?>
  <div style="background:#EAF6EE;color:#2E8B57;border:1px solid #C0E0CC;border-radius:10px;padding:12px 16px;font-size:13px;font-weight:500;margin-bottom:18px;">✅ <?= htmlspecialchars($cancel_success) ?></div>
  <?php endif; ?>
  <?php if($cancel_error): ?>
  <div style="background:#FDECEA;color:#C0392B;border:1px solid #F5C6C2;border-radius:10px;padding:12px 16px;font-size:13px;font-weight:500;margin-bottom:18px;">⚠️ <?= htmlspecialchars($cancel_error) ?></div>
  <?php endif; ?>

  <?php if(empty($rentals)): ?>
  <div class="empty-state"><div class="empty-icon">📋</div><p>No rentals yet. <a href="dashboard.php?page=browse" style="color:var(--gold)">Browse equipment</a> to get started!</p></div>
  <?php else: ?>
  <div class="history-list">
    <?php foreach($rentals as $r): ?>
    <?php $can_cancel = $r['status'] === 'active' && empty($r['checkout_by']); ?>
    <div class="history-card <?= $r['status']==='active'?'active-order':'' ?>" style="flex-wrap:wrap;gap:14px;">
      <div><p class="order-id-lbl">Order ID</p><p class="order-id-val"><?= htmlspecialchars($r['order_code']) ?></p></div>
      <div class="order-info">
        <p class="order-name"><?= $r['eq_icon'] ?> <?= htmlspecialchars($r['eq_name']) ?></p>
        <p class="order-date">📅 <?= date('M j', strtotime($r['start_date'])) ?> → <?= date('M j, Y', strtotime($r['end_date'])) ?></p>
      </div>
      <?php if($r['status']==='active'): ?>
        <span class="status-badge status-active">🟢 Active</span>
      <?php elseif($r['status']==='cancelled'): ?>
        <span class="status-badge" style="background:#FDECEA;color:#C0392B;border:1px solid #F5C6C2;">✕ Cancelled</span>
      <?php else: ?>
        <span class="status-badge status-returned">✓ Returned</span>
      <?php endif; ?>
      <span class="order-total <?= $r['status']==='active'?'':'done-total' ?>">₱<?= number_format($r['total_amount'],0) ?></span>
      <?php if($can_cancel): ?>
      <button onclick="openCancelModal(<?= $r['id'] ?>,'<?= htmlspecialchars($r['order_code']) ?>','<?= htmlspecialchars($r['eq_name']) ?>')"
        style="margin-left:auto;background:none;border:1.5px solid #C0392B;color:#C0392B;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .18s;"
        onmouseover="this.style.background='#FDECEA'" onmouseout="this.style.background='none'">
        ✕ Cancel
      </button>
      <?php elseif($r['status']==='active' && !empty($r['checkout_by'])): ?>
      <span style="margin-left:auto;font-size:11px;color:var(--muted);font-style:italic;">Already picked up</span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="loyalty-banner">
    <div>
      <p style="font-size:11px;color:var(--gold);text-transform:uppercase;letter-spacing:.12em;font-weight:600;margin-bottom:4px">Loyalty Points</p>
      <p class="loyalty-pts"><?= number_format($user['loyalty_pts']) ?> <span>pts</span></p>
      <p style="font-size:12px;color:var(--muted);margin-top:2px">Earn 1 point for every ₱10 spent</p>
    </div>
    <div style="text-align:right">
      <div style="background:#fff;border:1px solid var(--border);border-radius:8px;padding:6px 14px;font-size:12px;color:var(--text2);display:inline-block;margin-bottom:8px">
        Tier: <span style="color:var(--gold);font-weight:600"><?= $user['loyalty_pts']>=5000?'Gold 🥇':($user['loyalty_pts']>=2000?'Silver 🥈':'Bronze 🥉') ?></span>
      </div>
    </div>
  </div>

<!-- ══ PROFILE ══ -->
<?php elseif($page==='profile'):
  $upload_msg   = $_SESSION['id_upload_msg']   ?? ''; unset($_SESSION['id_upload_msg']);
  $upload_error = $_SESSION['id_upload_error'] ?? ''; unset($_SESSION['id_upload_error']);
  $notif_saved  = $_SESSION['notif_saved']     ?? false; unset($_SESSION['notif_saved']);
  $user = $conn->query("SELECT * FROM users WHERE id={$user['id']}")->fetch_assoc();
  $_SESSION['user'] = $user;
  $id_status = $user['id_status'] ?? 'none';
?>
  <div class="profile-wrap">
    <div class="profile-card">
      <div class="profile-avatar"><?= strtoupper(substr($user['first_name'],0,1)) ?></div>
      <p class="profile-name"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></p>
      <p class="profile-email"><?= htmlspecialchars($user['email']) ?></p>
      <?php if($id_status==='approved'&&$user['id_type']!=='regular'): ?>
        <div class="verified-pill">✅ <?= $id_labels[$user['id_type']] ?> — 20% Discount Active</div>
      <?php elseif($id_status==='pending'): ?>
        <div class="verified-pill" style="background:#FEF3E2;color:#E07C35;border-color:#FADDB8">⏳ ID Under Review</div>
      <?php elseif($id_status==='rejected'): ?>
        <div class="verified-pill" style="background:#FDECEA;color:#C0392B;border-color:#F5C6C2">❌ ID Rejected — Please resubmit</div>
      <?php else: ?>
        <div class="verified-pill" style="background:#F2F0EE;color:#8A8078;border-color:#E5E0D8">🪪 No ID submitted yet</div>
      <?php endif; ?>
    </div>
    <div class="stats-grid">
      <div class="stat-card"><p style="font-size:22px;margin-bottom:6px">🏅</p><p class="stat-val"><?= count($rentals) ?></p><p class="stat-lbl">Total Rentals</p></div>
      <div class="stat-card"><p style="font-size:22px;margin-bottom:6px">⭐</p><p class="stat-val"><?= number_format($user['loyalty_pts']) ?></p><p class="stat-lbl">Loyalty Points</p></div>
      <div class="stat-card"><p style="font-size:22px;margin-bottom:6px">📦</p><p class="stat-val"><?= count(array_filter($rentals,fn($r)=>$r['status']==='active')) ?></p><p class="stat-lbl">Active Bookings</p></div>
      <div class="stat-card"><p style="font-size:22px;margin-bottom:6px">💰</p><p class="stat-val">₱<?= number_format(array_sum(array_map(fn($r)=>$r['total_amount']*($r['discount_pct']/100),array_filter($rentals,fn($r)=>$r['discount_pct']>0))),0) ?></p><p class="stat-lbl">Total Saved</p></div>
    </div>
    <div class="settings-card">
      <p class="settings-title">🪪 ID Verification</p>
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px;line-height:1.6">
        ID verification is <strong>required to book equipment</strong>. It protects our gear and ensures accountability.
        Students, Senior Citizens, and PWD customers also get a <strong style="color:var(--gold)">20% discount</strong> after verification.
      </p>
      <?php if($upload_msg): ?>
        <div style="background:#EAF6EE;color:#2E8B57;border:1px solid #C0E0CC;border-radius:10px;padding:12px 14px;font-size:13px;font-weight:500;margin-bottom:16px">✅ <?= htmlspecialchars($upload_msg) ?></div>
      <?php endif; ?>
      <?php if($upload_error): ?>
        <div style="background:#FDECEA;color:#C0392B;border:1px solid #F5C6C2;border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:16px">⚠️ <?= htmlspecialchars($upload_error) ?></div>
      <?php endif; ?>
      <?php if($id_status==='rejected'&&$user['id_reject_reason']): ?>
        <div style="background:#FDECEA;color:#C0392B;border:1px solid #F5C6C2;border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:16px"><strong>Rejection reason:</strong> <?= htmlspecialchars($user['id_reject_reason']) ?></div>
      <?php endif; ?>
      <?php if($id_status==='approved'): ?>
        <div style="background:#EAF6EE;border:1px solid #C0E0CC;border-radius:12px;padding:16px;text-align:center">
          <p style="font-size:22px;margin-bottom:6px">✅</p>
          <p style="font-weight:600;color:#2E8B57;font-size:14px">Your <?= ucfirst($user['id_type']) ?> ID is verified!</p>
          <p style="font-size:12px;color:#2E8B57;margin-top:4px">You can now book equipment. <?= $user['id_type']!=='regular'?'20% discount applied to all rentals.':'' ?></p>
          <?php if($user['id_image']): ?><img src="<?= htmlspecialchars($user['id_image']) ?>" style="max-width:100%;max-height:180px;border-radius:10px;border:2px solid #C0E0CC;object-fit:cover;margin-top:14px"/><?php endif; ?>
          <p style="font-size:12px;color:#2E8B57;margin-top:12px">Need to update? <a href="#" onclick="document.getElementById('reup').style.display='block';this.style.display='none'" style="color:var(--gold);font-weight:600">Resubmit</a></p>
          <div id="reup" style="display:none;margin-top:14px"><?php include_once 'includes/id_upload_form.php'; ?></div>
        </div>
      <?php elseif($id_status==='pending'): ?>
        <div style="background:#FEF3E2;border:1px solid #FADDB8;border-radius:12px;padding:16px;text-align:center">
          <p style="font-size:28px;margin-bottom:8px">⏳</p>
          <p style="font-weight:600;color:#E07C35;font-size:14px">Your ID is being reviewed</p>
          <p style="font-size:12px;color:#E07C35;margin-top:4px">Usually verified within 24 hours. Booking will be unlocked once approved.</p>
          <?php if($user['id_image']): ?><img src="<?= htmlspecialchars($user['id_image']) ?>" style="max-width:100%;max-height:180px;border-radius:10px;border:2px solid #FADDB8;object-fit:cover;margin-top:14px"/><?php endif; ?>
          <p style="font-size:12px;color:#E07C35;margin-top:12px">Wrong file? <a href="#" onclick="document.getElementById('reup2').style.display='block';this.style.display='none'" style="color:var(--gold);font-weight:600">Resubmit</a></p>
          <div id="reup2" style="display:none;margin-top:14px"><?php include_once 'includes/id_upload_form.php'; ?></div>
        </div>
      <?php else: ?>
        <?php include_once 'includes/id_upload_form.php'; ?>
      <?php endif; ?>
    </div>
    <!-- NOTIFICATION SETTINGS CARD -->
    <div class="settings-card" id="notifications">
      <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;margin-bottom:0" onclick="toggleNotifCard()">
        <div>
          <p class="settings-title" style="margin-bottom:4px">🔔 Notification Settings</p>
          <p style="font-size:12px;color:var(--muted)">What notifications do you want to see?</p>
        </div>
        <span id="notif-chevron" style="font-size:20px;color:var(--muted);transition:transform .2s;transform:rotate(180deg)">⌃</span>
      </div>

      <div id="notif-body" style="margin-top:0;overflow:hidden;max-height:1000px;transition:max-height .3s ease">
        <div style="height:1px;background:var(--border);margin:16px 0;"></div>

        <?php if($notif_saved): ?>
        <div style="background:var(--green-bg);color:var(--green);border:1px solid #C0E0CC;border-radius:10px;padding:10px 14px;font-size:13px;font-weight:500;margin-bottom:16px">✅ Notification preferences saved!</div>
        <?php endif; ?>

        <form method="POST" action="dashboard.php?page=profile">
          <input type="hidden" name="act" value="save_notifications"/>

          <?php
          $notif_groups = [
            'promo'    => ['Updates & Promotions',   'Be the first to know about campaigns, promo codes, discounts and new features.', 'notif_promo_email',    'notif_promo_sms'],
            'reminder' => ['Reminders',              'Get reminders about your cart, return dates, payments, and referring friends.',     'notif_reminder_email', 'notif_reminder_sms'],
            'account'  => ['Account Notifications',  'Important notifications on booking summaries, vouchers, and cancellations.',        'notif_account_email',  'notif_account_sms'],
          ];
          foreach ($notif_groups as $key => [$title, $desc, $email_col, $sms_col]):
            $locked = ($key === 'account'); // account notifications are always on
            $email_on = $user[$email_col] ?? 1;
            $sms_on   = $user[$sms_col]   ?? 1;
          ?>
          <div style="margin-bottom:20px;">
            <p style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:4px"><?= $title ?></p>
            <p style="font-size:12px;color:var(--muted);margin-bottom:12px;line-height:1.5"><?= $desc ?></p>
            <div style="display:flex;gap:28px;">
              <!-- Email toggle -->
              <label style="display:flex;align-items:center;gap:10px;cursor:<?= $locked?'default':'pointer' ?>">
                <span class="notif-check <?= $email_on?'on':'' ?> <?= $locked?'locked':'' ?>" data-name="<?= $email_col ?>">
                  <?php if($locked||$email_on): ?>✓<?php endif; ?>
                </span>
                <?php if(!$locked): ?><input type="checkbox" name="<?= $email_col ?>" style="display:none" <?= $email_on?'checked':'' ?>><?php endif; ?>
                <span style="font-size:14px;color:<?= $locked?'var(--muted)':'var(--text2)' ?>;font-weight:500">Email</span>
              </label>
              <!-- SMS toggle -->
              <label style="display:flex;align-items:center;gap:10px;cursor:<?= $locked?'default':'pointer' ?>">
                <span class="notif-check <?= $sms_on?'on':'' ?> <?= $locked?'locked':'' ?>" data-name="<?= $sms_col ?>">
                  <?php if($locked||$sms_on): ?>✓<?php endif; ?>
                </span>
                <?php if(!$locked): ?><input type="checkbox" name="<?= $sms_col ?>" style="display:none" <?= $sms_on?'checked':'' ?>><?php endif; ?>
                <span style="font-size:14px;color:<?= $locked?'var(--muted)':'var(--text2)' ?>;font-weight:500">SMS</span>
              </label>
            </div>
          </div>
          <?php endforeach; ?>

          <button type="submit" id="notif-save-btn" style="background:var(--muted);color:#fff;border:none;padding:11px 28px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:background .2s;opacity:.7" disabled>
            Update
          </button>
        </form>
      </div>
    </div>

    <!-- ACCOUNT SETTINGS -->
    <div class="settings-card">
      <p class="settings-title">Account Settings</p>
      <div class="settings-item"><span class="settings-item-lbl">Edit Profile</span><span class="settings-arrow">›</span></div>
      <div class="settings-item"><span class="settings-item-lbl">Change Password</span><span class="settings-arrow">›</span></div>
      <a href="logout.php" class="settings-item" style="color:inherit"><span class="settings-item-lbl danger">Log Out</span><span class="settings-arrow">›</span></a>
    </div>
  </div>
<?php endif; ?>
</div>



<!-- ══ TOAST ALERTS ══ -->
<div class="toast toast-error <?= (($_SESSION['booking_error']??'')=='max_reached')?'show':'' ?>" id="toast-maxreached">
  <span class="toast-icon">🚫</span>
  <div style="flex:1">
    <div class="toast-title">Rental Limit Reached (3/3)</div>
    <div class="toast-msg">You already have 3 active rentals. Please return equipment before booking more.</div>
  </div>
  <button class="toast-x" onclick="this.parentElement.classList.remove('show')">✕</button>
</div>
<div class="toast toast-error <?= (($_SESSION['booking_error']??'')==='unavailable')?'show':'' ?>" id="toast-unavail">
  <span class="toast-icon">🚫</span>
  <div style="flex:1">
    <div class="toast-title">Not Available on That Date</div>
    <div class="toast-msg"><strong><?= htmlspecialchars($_SESSION['booking_error_eq']??'This equipment') ?></strong> is fully booked on <?= htmlspecialchars($_SESSION['booking_error_date']??'') ?>. Please choose a different pick-up date.</div>
  </div>
  <button class="toast-x" onclick="this.parentElement.classList.remove('show')">✕</button>
</div>

<div class="toast toast-warn <?= (($_SESSION['booking_error']??'')==='id_required')?'show':'' ?>" id="toast-id">
  <span class="toast-icon">🪪</span>
  <div style="flex:1">
    <div class="toast-title">ID Verification Required</div>
    <div class="toast-msg">You need a verified ID before booking. It ensures equipment safety and accountability. <a href="dashboard.php?page=profile">Verify your ID →</a></div>
  </div>
  <button class="toast-x" onclick="this.parentElement.classList.remove('show')">✕</button>
</div>

<div class="toast toast-warn <?= (($_SESSION['booking_error']??'')==='no_dates')?'show':'' ?>" id="toast-nodates">
  <span class="toast-icon">📅</span>
  <div style="flex:1">
    <div class="toast-title">Pick-up Date Required</div>
    <div class="toast-msg">Please set pick-up and return dates for all items in your cart before confirming your booking.</div>
  </div>
  <button class="toast-x" onclick="this.parentElement.classList.remove('show')">✕</button>
</div>

<?php unset($_SESSION['booking_error'],$_SESSION['booking_error_eq'],$_SESSION['booking_error_date']); ?>

<script>
// Check all cart items have dates before confirming booking
function checkDates() {
  const missingItems = document.querySelectorAll('[data-needs-dates="1"]');
  if (missingItems.length > 0) {
    // Scroll to first missing item and highlight it
    missingItems[0].scrollIntoView({behavior:'smooth', block:'center'});
    missingItems[0].style.border = '2px solid var(--red)';
    missingItems[0].style.borderRadius = '10px';
    setTimeout(() => { missingItems[0].style.border = ''; }, 2500);
    // Show inline warning on summary
    const warn = document.getElementById('dates-warning');
    if (warn) { warn.style.display = 'block'; setTimeout(()=>warn.style.display='none', 4000); }
    return false;
  }
  return true;
}

// Equipment info modal
function openEqModal(eq) {
  // Image
  const imgWrap = document.getElementById('eq-modal-img-wrap');
  if (eq.image) {
    imgWrap.innerHTML = '<img src="'+eq.image+'" class="eq-modal-img" onerror="this.parentElement.innerHTML=\'<div class=eq-modal-img-placeholder>🏅</div>\'">';
  } else {
    imgWrap.innerHTML = '<div class="eq-modal-img-placeholder">🏅</div>';
  }
  document.getElementById('eq-modal-name').textContent  = eq.name;
  document.getElementById('eq-modal-cat').textContent   = eq.cat;
  document.getElementById('eq-modal-price').textContent = '₱' + Number(eq.price).toLocaleString() + '/day';
  document.getElementById('eq-modal-rating').textContent = '⭐ ' + eq.rating + ' (' + eq.count + ' reviews)';
  document.getElementById('eq-modal-eq-id').value = eq.id;

  // Description
  const descEl = document.getElementById('eq-modal-desc');
  descEl.textContent = eq.desc || 'No description provided.';

  // Tag
  const tagWrap = document.getElementById('eq-modal-tag-wrap');
  tagWrap.innerHTML = eq.tag ? '<span class="tag tag-pop">'+eq.tag+'</span>' : '';

  // Stock
  const dot = document.getElementById('eq-modal-stock-dot');
  const txt = document.getElementById('eq-modal-stock-txt');
  const btn = document.getElementById('eq-modal-btn');
  if (eq.stock <= 0) {
    dot.style.background = '#C0392B'; txt.textContent = 'Not Available'; txt.style.color = '#C0392B';
    btn.disabled = true; btn.style.opacity = '.5'; btn.style.cursor = 'not-allowed'; btn.textContent = 'Not Available';
  } else if (eq.stock <= 2) {
    dot.style.background = '#E07C35'; txt.textContent = 'Only ' + eq.stock + ' left'; txt.style.color = '#E07C35';
    btn.disabled = false; btn.style.opacity = '1'; btn.style.cursor = 'pointer'; btn.textContent = '🛒 Add to Cart';
  } else {
    dot.style.background = '#2E8B57'; txt.textContent = eq.stock + ' in stock'; txt.style.color = '#2E8B57';
    btn.disabled = false; btn.style.opacity = '1'; btn.style.cursor = 'pointer'; btn.textContent = '🛒 Add to Cart';
  }

  document.getElementById('eq-overlay').classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeEqModal() {
  document.getElementById('eq-overlay').classList.remove('show');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeEqModal(); });

// Cart date picker live update
function cartDateUpdate(eqId) {
  const pu = document.getElementById('pu-' + eqId);
  const rt = document.getElementById('rt-' + eqId);
  if (!pu || !rt || !pu.value) return;

  const puDate = new Date(pu.value + 'T00:00:00');
  const minRet = new Date(puDate.getTime() + 86400000).toISOString().split('T')[0];
  const maxRet = new Date(puDate.getTime() + 3*86400000).toISOString().split('T')[0];

  rt.min = minRet;
  rt.max = maxRet;
  if (!rt.value || rt.value <= pu.value) rt.value = minRet;
  if (rt.value > maxRet) rt.value = maxRet;

  const days = Math.max(1, Math.round((new Date(rt.value) - puDate) / 86400000));
  const prev = document.getElementById('cart-preview-' + eqId);
  if (prev) prev.innerHTML = '📅 <strong>' + days + ' day' + (days>1?'s':'') + '</strong> · Pick-up: ' + pu.value + ' · Return: ' + rt.value;
}

// Set min pickup date = today+3 on load for all pickers
document.addEventListener('DOMContentLoaded', function() {
  const today = new Date();
  const minPickup = new Date(today.getTime() + 3*86400000).toISOString().split('T')[0];
  document.querySelectorAll('input[id^="pu-"]').forEach(pu => {
    pu.min = minPickup;
    if (!pu.value || pu.value < minPickup) {
      pu.value = minPickup;
      const eqId = pu.id.replace('pu-', '');
      cartDateUpdate(eqId);
    }
  });
});

// Auto-dismiss toasts after 7s
document.querySelectorAll('.toast.show').forEach(t => setTimeout(()=>t.classList.remove('show'), 7000));
</script>

<!-- ══ CANCEL BOOKING MODAL ══ -->
<div id="cancel-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(3px);z-index:500;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:18px;padding:28px 30px;width:440px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.2);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
      <div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:800;color:#1C1916;">Cancel Booking</div>
      <button onclick="closeCancelModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#C0B8AE;line-height:1;">×</button>
    </div>
    <div style="background:#FDECEA;border:1px solid #F5C6C2;border-radius:10px;padding:13px 14px;margin-bottom:18px;font-size:13px;color:#C0392B;">
      <strong>⚠️ Are you sure?</strong> You are about to cancel order <strong id="cancel-order-code"></strong> for <strong id="cancel-eq-name"></strong>.
      <div style="margin-top:6px;font-size:12px;">Loyalty points earned from this booking will be deducted. This cannot be undone.</div>
    </div>
    <div style="margin-bottom:6px;font-size:11px;font-weight:700;color:#4A4540;letter-spacing:.04em;text-transform:uppercase;">Reason for cancellation</div>
    <textarea id="cancel-reason" rows="3" placeholder="e.g. Change of plans, scheduling conflict..."
      style="width:100%;background:#F7F5F2;border:1.5px solid #E5E0D8;border-radius:10px;padding:10px 14px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1C1916;outline:none;resize:none;margin-bottom:18px;"
      onfocus="this.style.borderColor='#C47F2B'" onblur="this.style.borderColor='#E5E0D8'"></textarea>
    <form method="POST" action="dashboard.php?page=history" id="cancel-form">
      <input type="hidden" name="act" value="cancel_booking"/>
      <input type="hidden" name="rental_id" id="cancel-rental-id"/>
      <input type="hidden" name="cancel_reason" id="cancel-reason-hidden"/>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" onclick="closeCancelModal()"
          style="background:none;border:1.5px solid #E5E0D8;color:#4A4540;border-radius:8px;padding:9px 18px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;">
          Keep Booking
        </button>
        <button type="submit" onclick="return submitCancel()"
          style="background:#C0392B;color:#fff;border:none;border-radius:8px;padding:9px 18px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:700;">
          ✕ Confirm Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openCancelModal(rentalId, orderCode, eqName) {
  document.getElementById('cancel-rental-id').value = rentalId;
  document.getElementById('cancel-order-code').textContent = orderCode;
  document.getElementById('cancel-eq-name').textContent = eqName;
  document.getElementById('cancel-reason').value = '';
  document.getElementById('cancel-overlay').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeCancelModal() {
  document.getElementById('cancel-overlay').style.display = 'none';
  document.body.style.overflow = '';
}
function submitCancel() {
  const reason = document.getElementById('cancel-reason').value.trim();
  document.getElementById('cancel-reason-hidden').value = reason || 'No reason provided';
  return true;
}
document.getElementById('cancel-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeCancelModal();
});
</script>

<!-- ══ FOOTER ══ -->
<footer style="background:#1C1916;color:#fff;padding:40px 40px 24px;margin-top:60px">
  <div style="max-width:1400px;margin:0 auto">
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:32px">
      <div>
        <div style="font-family:'Playfair Display',serif;font-size:20px;font-weight:800;margin-bottom:8px">Kinetic<span style="color:var(--gold,#C47F2B)">Borrow</span></div>
        <p style="font-size:13px;color:#AAA;line-height:1.7;max-width:280px">Premium sports equipment rental for students, athletes, and sports enthusiasts.</p>
        <div style="margin-top:14px;display:flex;gap:10px">
          <span style="background:#333;border-radius:6px;padding:6px 10px;font-size:18px">📘</span>
          <span style="background:#333;border-radius:6px;padding:6px 10px;font-size:18px">📸</span>
          <span style="background:#333;border-radius:6px;padding:6px 10px;font-size:18px">📧</span>
        </div>
      </div>
      <div>
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#888;margin-bottom:14px">Browse</div>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;color:#CCC">
          <a href="dashboard.php?page=browse" style="color:#CCC;text-decoration:none;transition:color .18s" onmouseover="this.style.color='#C47F2B'" onmouseout="this.style.color='#CCC'">All Equipment</a>
          <a href="dashboard.php?page=browse&cat=Cycling" style="color:#CCC;text-decoration:none" onmouseover="this.style.color='#C47F2B'" onmouseout="this.style.color='#CCC'">Cycling</a>
          <a href="dashboard.php?page=browse&cat=Team+Sports" style="color:#CCC;text-decoration:none" onmouseover="this.style.color='#C47F2B'" onmouseout="this.style.color='#CCC'">Team Sports</a>
          <a href="dashboard.php?page=browse&cat=Racket+Sports" style="color:#CCC;text-decoration:none" onmouseover="this.style.color='#C47F2B'" onmouseout="this.style.color='#CCC'">Racket Sports</a>
          <a href="dashboard.php?page=browse&cat=Outdoor" style="color:#CCC;text-decoration:none" onmouseover="this.style.color='#C47F2B'" onmouseout="this.style.color='#CCC'">Outdoor</a>
        </div>
      </div>
      <div>
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#888;margin-bottom:14px">Account</div>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:13px">
          <a href="dashboard.php?page=history" style="color:#CCC;text-decoration:none" onmouseover="this.style.color='#C47F2B'" onmouseout="this.style.color='#CCC'">My Rentals</a>
          <a href="dashboard.php?page=cart" style="color:#CCC;text-decoration:none" onmouseover="this.style.color='#C47F2B'" onmouseout="this.style.color='#CCC'">My Cart</a>
          <a href="dashboard.php?page=profile" style="color:#CCC;text-decoration:none" onmouseover="this.style.color='#C47F2B'" onmouseout="this.style.color='#CCC'">Profile & ID</a>
          <a href="logout.php" style="color:#CCC;text-decoration:none" onmouseover="this.style.color='#C47F2B'" onmouseout="this.style.color='#CCC'">Log Out</a>
        </div>
      </div>
      <div>
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#888;margin-bottom:14px">Info</div>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;color:#CCC">
          <span>📍 University of Caloocan City</span>
          <span>🏫 Computer Studies Dept.</span>
          <span>🕐 Mon–Fri · 8AM – 5PM</span>
          <span>📱 BS Information Systems</span>
        </div>
      </div>
    </div>
    <div style="border-top:1px solid #333;padding-top:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <p style="font-size:12px;color:#666">© 2025 KineticBorrow </p>
      <p style="font-size:12px;color:#555">Made with ❤️ by Agarano · Marianito · Napilot · Reyes · Tejada</p>
    </div>
  </div>
</footer>

</body>
</html>
