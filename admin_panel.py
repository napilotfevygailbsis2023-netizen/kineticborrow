import sys, os, base64, uuid
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import admin_db, guide_db
from datetime import datetime, date, timedelta

CITIES = ['Albay','Baguio','Bataan','Batangas','Ilocos Norte','Manila','Pangasinan','Tagaytay','Vigan']
BASE   = os.path.dirname(os.path.abspath(__file__))
UPLOAD_DIR = os.path.join(BASE, "uploads")
os.makedirs(UPLOAD_DIR, exist_ok=True)

# ── SAVE UPLOADED IMAGE ──
def save_image(file_data, filename_hint="img"):
    if not file_data: return ""
    ext = os.path.splitext(filename_hint)[-1] or ".jpg"
    fname = f"{uuid.uuid4().hex}{ext}"
    fpath = os.path.join(UPLOAD_DIR, fname)
    with open(fpath, "wb") as f: f.write(file_data)
    return f"/uploads/{fname}"

# ── SHELL ──
def shell(title, body, active, admin):
    aname = admin.get("fullname","Admin") if admin else "Admin"
    ainit = (aname[0] if aname else "A").upper()
    today = datetime.now().strftime("%A, %B %d, %Y")
    nav = [
        ("dashboard",   "&#9732;",   "Dashboard"),
        ("tourists",    "&#128100;", "Tourists"),
        ("spots",       "&#127963;", "Attractions"),
        ("restaurants", "&#127869;", "Restaurants"),
        ("guides",      "&#129517;", "Tour Guides"),
        ("transport",   "&#128652;", "Transportation"),
        ("flights",     "&#9992;",   "Flights"),
    ]
    nav_html = ""
    for key, icon, label in nav:
        a = active == key
        bg   = "background:#EEF2FF;color:#4338CA;font-weight:700;" if a else "color:#6B7280;"
        left = "border-left:3px solid #4338CA;" if a else "border-left:3px solid transparent;"
        nav_html += f'<a href="/admin/{key}" style="display:flex;align-items:center;gap:10px;padding:10px 20px;text-decoration:none;font-size:13.5px;{bg}{left}">{icon} {label}</a>'
    return f"""<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>{title} - ATLAS Admin</title>
<style>
*{{box-sizing:border-box;margin:0;padding:0}}
body{{font-family:'Segoe UI',sans-serif;background:#F8FAFC;color:#1E293B;min-height:100vh;display:flex}}
.sidebar{{width:230px;flex-shrink:0;background:#fff;border-right:1px solid #E2E8F0;display:flex;flex-direction:column;height:100vh;position:sticky;top:0;overflow-y:auto}}
.s-brand{{padding:20px;border-bottom:1px solid #E2E8F0;flex-shrink:0}}
.s-logo-row{{display:flex;align-items:center;gap:10px}}
.s-logo{{width:36px;height:36px;background:linear-gradient(135deg,#0038A8,#CE1126);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:16px}}
.s-title{{font-weight:900;font-size:17px}}
.s-badge{{font-size:9px;color:#6366F1;font-weight:700;background:#EEF2FF;padding:2px 8px;border-radius:10px;margin-top:2px;display:inline-block}}
.s-section{{font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.8px;padding:16px 20px 6px}}
.s-bottom{{margin-top:auto;border-top:1px solid #E2E8F0;padding:12px 0;flex-shrink:0}}
.main{{flex:1;display:flex;flex-direction:column;overflow-x:hidden;min-width:0}}
.topbar{{background:#fff;border-bottom:1px solid #E2E8F0;padding:12px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}}
.topbar-title{{font-size:20px;font-weight:800}}
.admin-pill{{display:flex;align-items:center;gap:10px;background:#F1F5F9;border-radius:30px;padding:6px 14px 6px 6px;text-decoration:none}}
.av{{width:32px;height:32px;background:linear-gradient(135deg,#0038A8,#CE1126);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:14px;flex-shrink:0}}
.content{{padding:28px;flex:1}}
.stat-grid{{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-bottom:24px}}
.stat-card{{background:#fff;border:1px solid #E2E8F0;border-radius:14px;padding:20px}}
.card{{background:#fff;border:1px solid #E2E8F0;border-radius:14px;overflow:hidden;margin-bottom:20px}}
.card-hdr{{padding:16px 20px;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;justify-content:space-between}}
.card-hdr h3{{font-size:14px;font-weight:700}}
.card-body{{padding:20px}}
table{{width:100%;border-collapse:collapse;font-size:13px}}
th{{text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #F1F5F9;background:#FAFAFA}}
td{{padding:11px 16px;border-bottom:1px solid #F8FAFC;color:#475569;vertical-align:middle}}
tr:last-child td{{border-bottom:none}}
tr:hover td{{background:#FAFAFA}}
.ba{{background:#DCFCE7;color:#16A34A;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}}
.bs{{background:#FEE2E2;color:#DC2626;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}}
.bar{{background:#F3F4F6;color:#6B7280;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}}
.bb{{background:#DBEAFE;color:#1D4ED8;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}}
.bg{{background:#DCFCE7;color:#16A34A;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}}
.btn{{padding:5px 12px;border-radius:6px;font-size:11px;font-weight:700;border:none;cursor:pointer;font-family:inherit}}
.bdanger{{background:#FEE2E2;color:#DC2626}}
.bwarn{{background:#FEF3C7;color:#D97706}}
.bsuccess{{background:#DCFCE7;color:#16A34A}}
.bprimary{{background:#4338CA;color:#fff}}
.bgray{{background:#F3F4F6;color:#6B7280}}
.bblue{{background:#DBEAFE;color:#1D4ED8}}
label{{display:block;font-size:11px;font-weight:700;color:#64748B;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px}}
input:not([type=file]),select,textarea{{width:100%;background:#F8FAFC;border:1.5px solid #E2E8F0;border-radius:8px;padding:9px 12px;color:#1E293B;font-size:13px;outline:none;font-family:inherit;margin-bottom:12px}}
input:not([type=file]):focus,select:focus,textarea:focus{{border-color:#6366F1;background:#fff}}
input[type=file]{{width:100%;padding:8px;border:1.5px dashed #CBD5E1;border-radius:8px;font-size:13px;margin-bottom:12px;cursor:pointer;background:#F8FAFC}}
.fg2{{display:grid;grid-template-columns:1fr 1fr;gap:12px}}
.fg3{{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}}
.ph{{margin-bottom:22px}}
.ph h1{{font-size:22px;font-weight:800}}
.ph p{{font-size:13px;color:#94A3B8;margin-top:3px}}
.va{{font-size:12px;font-weight:600;color:#6366F1;text-decoration:none;background:#EEF2FF;padding:5px 12px;border-radius:20px}}
.er td{{text-align:center;color:#94A3B8;padding:28px!important;font-size:13px}}
a.nl{{display:flex;align-items:center;gap:10px;padding:10px 20px;text-decoration:none;font-size:13.5px;color:#EF4444;border-left:3px solid transparent}}
a.nv{{display:flex;align-items:center;gap:10px;padding:10px 20px;text-decoration:none;font-size:13.5px;color:#94A3B8;border-left:3px solid transparent}}
.img-thumb{{width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid #E2E8F0}}
/* TABS */
.tabs{{display:flex;gap:0;margin-bottom:0;border-bottom:2px solid #E2E8F0}}
.tab-btn{{padding:10px 22px;border:none;background:none;font-size:13px;font-weight:600;color:#6B7280;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;font-family:inherit}}
.tab-btn.active{{color:#4338CA;border-bottom-color:#4338CA;background:#F8FAFC}}
.tab-pane{{display:none;padding-top:0}}
.tab-pane.active{{display:block}}
/* PAGINATION */
.pager{{display:flex;align-items:center;gap:6px;padding:14px 20px;border-top:1px solid #F1F5F9;justify-content:flex-end}}
.pager a,.pager span{{padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none}}
.pager a{{background:#F1F5F9;color:#374151}}
.pager a:hover{{background:#E2E8F0}}
.pager span.cur{{background:#4338CA;color:#fff}}
.pager span.dots{{color:#94A3B8}}
</style>
</head><body>
<div class="sidebar">
  <div class="s-brand"><div class="s-logo-row"><div class="s-logo">A</div><div><div class="s-title">ATLAS</div><div class="s-badge">ADMIN PANEL</div></div></div></div>
  <div class="s-section">Navigation</div>
  {nav_html}
  <div class="s-bottom">
    <a class="nl" href="/admin/logout">&#128682; Log Out</a>
    <a class="nv" href="/" target="_blank">&#127968; View Site</a>
  </div>
</div>
<div class="main">
  <div class="topbar">
    <div class="topbar-title">{title}</div>
    <div style="display:flex;align-items:center;gap:16px">
      <span style="font-size:12px;color:#94A3B8">{today}</span>
      <a href="/admin/profile" class="admin-pill">
        <div class="av">{ainit}</div>
        <div><div style="font-size:13px;font-weight:700;color:#1E293B;line-height:1.2">{aname}</div><div style="font-size:10px;color:#6B7280">Super Admin</div></div>
      </a>
    </div>
  </div>
  <div class="content">{body}</div>
</div>
<script>
function switchTab(group, tab) {{
  document.querySelectorAll('[data-group="'+group+'"] .tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('[data-group="'+group+'"] .tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelector('[data-group="'+group+'"] [data-tab="'+tab+'"]').classList.add('active');
  document.getElementById(group+'-'+tab).classList.add('active');
}}
</script>
</body></html>"""

def _alert(msg="", err=""):
    out=""
    if msg: out+=f'<div style="background:#DCFCE7;border:1px solid #BBF7D0;border-radius:8px;padding:10px 14px;color:#15803D;font-size:13px;margin-bottom:16px">&#10003; {msg}</div>'
    if err: out+=f'<div style="background:#FEE2E2;border:1px solid #FECACA;border-radius:8px;padding:10px 14px;color:#DC2626;font-size:13px;margin-bottom:16px">&#9888; {err}</div>'
    return out

def _stars(n):
    try: n=int(float(n))
    except: n=0
    return "&#9733;"*min(n,5)+"&#9734;"*(5-min(n,5))

def _img_cell(url, icon):
    if url: return f'<img src="{url}" class="img-thumb" onerror="this.style.display=\'none\'"/>'
    return f'<div style="width:44px;height:44px;background:#F1F5F9;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#CBD5E1;font-size:20px">{icon}</div>'

def _stat_mini(val, label, color):
    return f'<div style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:12px 20px;text-align:center;min-width:100px"><div style="font-size:22px;font-weight:900;color:{color}">{val}</div><div style="font-size:11px;color:#94A3B8;font-weight:600">{label}</div></div>'

def _paginate(rows_html_list, page, per_page, base_url, extra_params=""):
    total = len(rows_html_list)
    pages = max(1, (total + per_page - 1) // per_page)
    page  = max(1, min(page, pages))
    start = (page-1)*per_page
    shown = rows_html_list[start:start+per_page]
    sep = "&" if "?" in base_url else "?"
    pager = ""
    if pages > 1:
        pager = '<div class="pager">'
        if page > 1: pager += f'<a href="{base_url}{sep}page={page-1}{extra_params}">&#8592; Prev</a>'
        for p in range(1, pages+1):
            if p == page: pager += f'<span class="cur">{p}</span>'
            elif abs(p-page) <= 2 or p == 1 or p == pages: pager += f'<a href="{base_url}{sep}page={p}{extra_params}">{p}</a>'
            elif abs(p-page) == 3: pager += '<span class="dots">…</span>'
        if page < pages: pager += f'<a href="{base_url}{sep}page={page+1}{extra_params}">Next &#8594;</a>'
        pager += "</div>"
    return "".join(shown), pager, total, pages

# ── DASHBOARD ──
def dashboard(admin):
    s = admin_db.get_stats()
    db_spots  = admin_db.get_spots()
    db_rests  = admin_db.get_restaurants()
    db_guides = admin_db.get_guides()
    db_trans  = admin_db.get_transport()
    reg_guides = guide_db.get_public_guides()

    cards = [
        ("&#128100;","Tourists","#1D4ED8",   s["total_tourists"],    f'{s["active_tourists"]} active · {s["suspended"]} suspended'),
        ("&#127963;","Attractions","#D97706", len(db_spots),          f'{len(db_spots)} admin-added'),
        ("&#127869;","Restaurants","#BE185D", len(db_rests),          f'{len(db_rests)} admin-added'),
        ("&#129517;","Tour Guides","#7C3AED", len(db_guides)+len(reg_guides), f'{len(reg_guides)} registered · {len(db_guides)} admin-added'),
        ("&#128652;","Transport","#0369A1",   len(db_trans),          f'{len(db_trans)} admin-added'),
    ]
    sc = "".join(f'<div class="stat-card"><div style="font-size:26px;color:{c};margin-bottom:8px">{ico}</div><div style="font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:4px">{lbl}</div><div style="font-size:30px;font-weight:900;color:{c}">{val}</div><div style="font-size:11px;color:#94A3B8;margin-top:3px">{sub}</div></div>' for ico,lbl,c,val,sub in cards)
    recent = admin_db.get_recent_tourists(6)
    def _badge(st): 
        if st=="archived": return '<span class=bar>Archived</span>'
        if st=="suspended": return '<span class=bs>Suspended</span>'
        return '<span class=ba>Active</span>'
    rows = "".join(f'<tr><td style="font-weight:600;color:#1E293B">{u["fname"]} {u["lname"]}</td><td>{u["email"]}</td><td>{(u.get("created") or "")[:10]}</td><td>{_badge(u.get("status") or "active")}</td></tr>' for u in recent) or '<tr class="er"><td colspan="4">No tourists yet</td></tr>'
    body = f'<div class="ph"><h1>&#9732; Dashboard</h1><p>Welcome back, {admin.get("fullname","Admin")}!</p></div><div class="stat-grid">{sc}</div><div class="card"><div class="card-hdr"><h3>&#128100; Recent Tourists</h3><a class="va" href="/admin/tourists">View All</a></div><table><thead><tr><th>Name</th><th>Email</th><th>Joined</th><th>Status</th></tr></thead><tbody>{rows}</tbody></table></div>'
    return shell("Dashboard", body, "dashboard", admin)

# ── TOURISTS (TABS) ──
def tourists_page(admin, msg="", err="", tab="active"):
    users = admin_db.get_all_tourists()
    def _badge(st):
        if st=="archived": return '<span class=bar>Archived</span>'
        if st=="suspended": return '<span class=bs>Suspended</span>'
        return '<span class=ba>Active</span>'

    groups = {
        "active":   [u for u in users if (u.get("status") or "active")=="active"],
        "suspended":[u for u in users if u.get("status")=="suspended"],
        "archived": [u for u in users if u.get("status")=="archived"],
    }
    counts = {k:len(v) for k,v in groups.items()}
    counts["all"] = len(users)

    def build_rows(lst, show_actions):
        if not lst: return '<tr class="er"><td colspan="5">No users in this group</td></tr>'
        rows=""
        for u in lst:
            st = u.get("status") or "active"
            acts=""
            if st=="archived":
                acts = f'<a href="/admin/tourists/activate/{u["id"]}"><button class="btn bsuccess" style="margin-right:4px">Restore</button></a><a href="/admin/tourists/delete/{u["id"]}" onclick="return confirm(\'Delete permanently?\')"><button class="btn bdanger">Delete</button></a>'
            elif st=="suspended":
                acts = f'<a href="/admin/tourists/activate/{u["id"]}"><button class="btn bsuccess" style="margin-right:4px">Activate</button></a><a href="/admin/tourists/archive/{u["id"]}"><button class="btn bgray" style="margin-right:4px">Archive</button></a><a href="/admin/tourists/delete/{u["id"]}" onclick="return confirm(\'Delete permanently?\')"><button class="btn bdanger">Delete</button></a>'
            else:
                acts = f'<a href="/admin/tourists/suspend/{u["id"]}"><button class="btn bwarn" style="margin-right:4px">Suspend</button></a><a href="/admin/tourists/archive/{u["id"]}"><button class="btn bgray" style="margin-right:4px">Archive</button></a><a href="/admin/tourists/delete/{u["id"]}" onclick="return confirm(\'Delete permanently?\')"><button class="btn bdanger">Delete</button></a>'
            rows += f'<tr><td style="font-weight:600;color:#1E293B">{u["fname"]} {u["lname"]}</td><td>{u["email"]}</td><td>{(u.get("created") or "")[:10]}</td><td>{_badge(st)}</td><td>{acts}</td></tr>'
        return rows

    tab_labels = [("all","All",counts["all"],"#1D4ED8"),("active","Active",counts["active"],"#16A34A"),("suspended","Suspended",counts["suspended"],"#D97706"),("archived","Archived",counts["archived"],"#6B7280")]
    tab_btns = "".join(f'<button class="tab-btn {"active" if tab==k else ""}" data-tab="{k}" onclick="switchTab(\'tourists\',\'{k}\')">{lbl} <span style="background:{"#EEF2FF" if tab==k else "#F3F4F6"};color:{c};padding:1px 7px;border-radius:10px;font-size:11px">{cnt}</span></button>' for k,lbl,cnt,c in tab_labels)

    all_rows = build_rows(users, True)
    act_rows = build_rows(groups["active"], True)
    sus_rows = build_rows(groups["suspended"], True)
    arc_rows = build_rows(groups["archived"], True)

    def pane(pid, rows, active_tab):
        a = "active" if pid==active_tab else ""
        return f'<div id="tourists-{pid}" class="tab-pane {a}"><table><thead><tr><th>Name</th><th>Email</th><th>Joined</th><th>Status</th><th>Actions</th></tr></thead><tbody>{rows}</tbody></table></div>'

    body = f'''<div class="ph"><h1>&#128100; Tourists</h1><p>Manage tourist accounts</p></div>
    {_alert(msg,err)}
    <div class="card">
      <div style="padding:0 20px" data-group="tourists">
        <div class="tabs">{tab_btns}</div>
        {pane("all",all_rows,tab)}
        {pane("active",act_rows,tab)}
        {pane("suspended",sus_rows,tab)}
        {pane("archived",arc_rows,tab)}
      </div>
    </div>'''
    return shell("Tourists", body, "tourists", admin)

# ── ATTRACTIONS ──
def spots_page(admin, msg="", err="", page=1, tab="list"):
    items = admin_db.get_spots()
    PER = 8

    row_list = []
    for s in items:
        img = _img_cell(s.get("image_url",""), "&#127963;")
        acts = f'<a href="/admin/spots/delete/{s["id"]}" onclick="return confirm(\'Delete?\')"><button class="btn bdanger">Delete</button></a>'
        row_list.append(f'<tr><td>{img}</td><td style="font-weight:600;color:#1E293B">{s["name"]}</td><td>{s["city"]}</td><td><span class=bb>{s["category"]}</span></td><td>{_stars(s["rating"])}</td><td>{s["entry"]}</td><td>{acts}</td></tr>')

    rows_html, pager, total, _ = _paginate(row_list, page, PER, "/admin/spots")

    tab_btns  = f'<button class="tab-btn {"active" if tab=="add" else ""}" data-tab="add"  onclick="switchTab(\'spots\',\'add\')">&#43; Add Attraction</button><button class="tab-btn {"active" if tab=="list" else ""}" data-tab="list" onclick="switchTab(\'spots\',\'list\')">All Attractions ({total})</button>'

    add_form = f'''<div id="spots-add" class="tab-pane {"active" if tab=="add" else ""}"><div style="padding:20px">
    <form method="post" action="/admin/spots/add" enctype="multipart/form-data"><div class="fg2">
    <div><label>Name *</label><input name="name" placeholder="Intramuros" required/></div>
    <div><label>City / Location *</label><input name="city" placeholder="Manila, Batangas, Cavite..." required/></div>
    <div><label>Category</label><select name="category"><option>Historical</option><option>Nature</option><option>Heritage</option><option>Landmark</option><option>Park</option><option>Museum</option><option>Beach</option><option>Adventure</option><option>Religious</option></select></div>
    <div><label>Type</label><input name="type" placeholder="Walled City, National Park..."/></div>
    <div><label>Rating (1-5)</label><input name="rating" type="number" min="1" max="5" step="0.1" value="4.5"/></div>
    <div><label>Entry Fee</label><input name="entry" placeholder="Free / PHP 75"/></div>
    <div><label>Hours</label><input name="hours" placeholder="8AM-5PM Daily"/></div>
    <div><label>Upload Image</label><input type="file" name="image_file" accept="image/*"/></div>
    </div><div><label>Description</label><textarea name="desc" rows="2" style="resize:none" placeholder="Short description..."></textarea></div>
    <button class="btn bprimary" type="submit" style="padding:9px 22px;font-size:13px">&#43; Add Attraction</button>
    </form></div></div>'''

    list_pane = f'''<div id="spots-list" class="tab-pane {"active" if tab=="list" else ""}">
    <table><thead><tr><th>Img</th><th>Name</th><th>City</th><th>Category</th><th>Rating</th><th>Entry</th><th>Action</th></tr></thead>
    <tbody>{"" if rows_html else "<tr class=er><td colspan=7>No attractions added yet</td></tr>"}{rows_html}</tbody></table>
    {pager}</div>'''

    body = f'''<div class="ph"><h1>&#127963; Attractions</h1><p>{total} admin-added attractions</p></div>
    {_alert(msg,err)}
    <div class="card"><div style="padding:0 20px" data-group="spots"><div class="tabs">{tab_btns}</div>
    {add_form}{list_pane}</div></div>'''
    return shell("Attractions", body, "spots", admin)

# ── RESTAURANTS ──
def restaurants_page(admin, msg="", err="", page=1, tab="list"):
    items = admin_db.get_restaurants()
    PER = 8

    row_list = []
    for r in items:
        img  = _img_cell(r.get("image_url",""), "&#127869;")
        acts = f'<a href="/admin/restaurants/delete/{r["id"]}" onclick="return confirm(\'Delete?\')"><button class="btn bdanger">Delete</button></a>'
        row_list.append(f'<tr><td>{img}</td><td style="font-weight:600;color:#1E293B">{r["name"]}</td><td>{r["city"]}</td><td>{r["cuisine"]}</td><td style="color:#16A34A;font-weight:600">{r["price"]}</td><td>{_stars(r["rating"])}</td><td>{acts}</td></tr>')

    rows_html, pager, total, _ = _paginate(row_list, page, PER, "/admin/restaurants")
    tab_btns = f'<button class="tab-btn {"active" if tab=="add" else ""}" data-tab="add"  onclick="switchTab(\'rests\',\'add\')">&#43; Add Restaurant</button><button class="tab-btn {"active" if tab=="list" else ""}" data-tab="list" onclick="switchTab(\'rests\',\'list\')">All Restaurants ({total})</button>'

    add_form = f'''<div id="rests-add" class="tab-pane {"active" if tab=="add" else ""}"><div style="padding:20px">
    <form method="post" action="/admin/restaurants/add" enctype="multipart/form-data"><div class="fg2">
    <div><label>Name *</label><input name="name" placeholder="Cafe Juanita" required/></div>
    <div><label>City / Location *</label><input name="city" placeholder="Manila, Tagaytay, Vigan..." required/></div>
    <div><label>Cuisine</label><input name="cuisine" placeholder="Filipino / Italian / Asian"/></div>
    <div><label>Price Range</label><input name="price" placeholder="PHP 200-500"/></div>
    <div><label>Rating (1-5)</label><input name="rating" type="number" min="1" max="5" step="0.1" value="4.0"/></div>
    <div><label>Hours</label><input name="hours" placeholder="10AM-10PM"/></div>
    <div><label>Upload Image</label><input type="file" name="image_file" accept="image/*"/></div>
    </div><button class="btn bprimary" type="submit" style="padding:9px 22px;font-size:13px">&#43; Add Restaurant</button>
    </form></div></div>'''

    list_pane = f'''<div id="rests-list" class="tab-pane {"active" if tab=="list" else ""}">
    <table><thead><tr><th>Img</th><th>Name</th><th>City</th><th>Cuisine</th><th>Price</th><th>Rating</th><th>Action</th></tr></thead>
    <tbody>{"" if rows_html else "<tr class=er><td colspan=7>No restaurants added yet</td></tr>"}{rows_html}</tbody></table>
    {pager}</div>'''

    body = f'''<div class="ph"><h1>&#127869; Restaurants</h1><p>{total} admin-added restaurants</p></div>
    {_alert(msg,err)}
    <div class="card"><div style="padding:0 20px" data-group="rests"><div class="tabs">{tab_btns}</div>
    {add_form}{list_pane}</div></div>'''
    return shell("Restaurants", body, "restaurants", admin)

# ── TOUR GUIDES ──
def guides_page(admin, msg="", err="", page=1, tab="registered"):
    db_guides  = admin_db.get_guides()
    reg_guides = guide_db.get_public_guides()
    PER = 8

    # Registered (via guide portal)
    reg_list = []
    for g in reg_guides:
        avg, cnt = guide_db.get_avg_rating(g["id"])
        img  = _img_cell(g.get("photo",""), "&#129517;")
        avail = g.get("availability","N/A")
        acts = f'<span class=ba>Registered</span>'
        reg_list.append(f'<tr><td>{img}</td><td style="font-weight:600;color:#1E293B">{g["fname"]} {g["lname"]}</td><td>{g["city"]}</td><td>{g.get("languages","EN, FIL")}</td><td style="color:#7C3AED;font-weight:600">{g.get("rate","N/A")}</td><td>{avg}&#9733; ({cnt})</td><td>{acts}</td></tr>')

    reg_rows, reg_pager, reg_total, _ = _paginate(reg_list, page, PER, "/admin/guides", "&tab=registered")

    # Admin-added
    adm_list = []
    for g in db_guides:
        img  = _img_cell(g.get("image_url",""), "&#129517;")
        acts = f'<a href="/admin/guides/delete/{g["id"]}" onclick="return confirm(\'Delete?\')"><button class="btn bdanger">Delete</button></a>'
        adm_list.append(f'<tr><td>{img}</td><td style="font-weight:600;color:#1E293B">{g["name"]}</td><td>{g["city"]}</td><td>{g["language"]}</td><td style="color:#7C3AED;font-weight:600">{g["rate"]}</td><td>{_stars(g["rating"])}</td><td>{acts}</td></tr>')

    adm_rows, adm_pager, adm_total, _ = _paginate(adm_list, page, PER, "/admin/guides", "&tab=added")

    tab_btns = (
        f'<button class="tab-btn {"active" if tab=="registered" else ""}" data-tab="registered" onclick="switchTab(\'guides\',\'registered\')">&#129517; Registered Guides ({reg_total})</button>'
        f'<button class="tab-btn {"active" if tab=="added" else ""}" data-tab="added" onclick="switchTab(\'guides\',\'added\')">&#128394; Admin-Added ({adm_total})</button>'
    )

    reg_pane = f'''<div id="guides-registered" class="tab-pane {"active" if tab=="registered" else ""}">
    <div style="padding:12px 20px;background:#F8FAFC;border-bottom:1px solid #F1F5F9;font-size:13px;color:#64748B">&#128274; These guides registered themselves through the Guide Portal. Manage their accounts from the Guide Portal.</div>
    <table><thead><tr><th>Photo</th><th>Name</th><th>City</th><th>Languages</th><th>Rate</th><th>Rating</th><th>Status</th></tr></thead>
    <tbody>{"" if reg_rows else "<tr class=er><td colspan=7>No registered guides yet</td></tr>"}{reg_rows}</tbody></table>
    {reg_pager}</div>'''

    adm_pane = f'''<div id="guides-added" class="tab-pane {"active" if tab=="added" else ""}">
    <table><thead><tr><th>Photo</th><th>Name</th><th>City</th><th>Languages</th><th>Rate</th><th>Rating</th><th>Action</th></tr></thead>
    <tbody>{"" if adm_rows else "<tr class=er><td colspan=7>No admin-added guides yet</td></tr>"}{adm_rows}</tbody></table>
    {adm_pager}</div>'''

    body = f'''<div class="ph"><h1>&#129517; Tour Guides</h1><p>{reg_total} registered · {adm_total} admin-added</p></div>
    {_alert(msg,err)}
    <div class="card"><div style="padding:0 20px" data-group="guides"><div class="tabs">{tab_btns}</div>
    {reg_pane}{adm_pane}</div></div>'''
    return shell("Tour Guides", body, "guides", admin)

# ── TRANSPORTATION ──
def transport_page(admin, msg="", err="", page=1, tab="list"):
    items = admin_db.get_transport()
    PER = 8

    row_list = []
    for t in items:
        acts = f'<a href="/admin/transport/delete/{t["id"]}" onclick="return confirm(\'Delete?\')"><button class="btn bdanger">Delete</button></a>'
        row_list.append(f'<tr><td style="font-weight:600;color:#1E293B">{t["route"]}</td><td><span class=bb>{t["type"]}</span></td><td>{t["origin"]} &#8594; {t["dest"]}</td><td>{t["dep_time"]}</td><td style="color:#0369A1;font-weight:600">{t["fare"]}</td><td>{acts}</td></tr>')

    rows_html, pager, total, _ = _paginate(row_list, page, PER, "/admin/transport")
    tab_btns = f'<button class="tab-btn {"active" if tab=="add" else ""}" data-tab="add"  onclick="switchTab(\'transport\',\'add\')">&#43; Add Route</button><button class="tab-btn {"active" if tab=="list" else ""}" data-tab="list" onclick="switchTab(\'transport\',\'list\')">All Routes ({total})</button>'

    add_form = f'''<div id="transport-add" class="tab-pane {"active" if tab=="add" else ""}"><div style="padding:20px">
    <form method="post" action="/admin/transport/add"><div class="fg2">
    <div><label>Route Name *</label><input name="route" placeholder="Manila to Baguio Express" required/></div>
    <div><label>Type</label><select name="type"><option>Bus</option><option>Van</option><option>Train</option><option>Ferry</option><option>Jeepney</option></select></div>
    <div><label>Origin *</label><input name="origin" placeholder="Manila, Tarlac, Pampanga..." required/></div>
    <div><label>Destination *</label><input name="dest" placeholder="Baguio, Ilocos Norte..." required/></div>
    <div><label>Departure Time *</label><input name="dep_time" placeholder="6:00 AM" required/></div>
    <div><label>Fare</label><input name="fare" placeholder="PHP 450"/></div>
    </div><button class="btn bprimary" type="submit" style="padding:9px 22px;font-size:13px">&#43; Add Route</button>
    </form></div></div>'''

    list_pane = f'''<div id="transport-list" class="tab-pane {"active" if tab=="list" else ""}">
    <table><thead><tr><th>Route Name</th><th>Type</th><th>From &#8594; To</th><th>Departure</th><th>Fare</th><th>Action</th></tr></thead>
    <tbody>{"" if rows_html else "<tr class=er><td colspan=6>No routes added yet</td></tr>"}{rows_html}</tbody></table>
    {pager}</div>'''

    body = f'''<div class="ph"><h1>&#128652; Transportation</h1><p>{total} admin-added routes</p></div>
    {_alert(msg,err)}
    <div class="card"><div style="padding:0 20px" data-group="transport"><div class="tabs">{tab_btns}</div>
    {add_form}{list_pane}</div></div>'''
    return shell("Transportation", body, "transport", admin)

# ── FLIGHTS ──
def flights_page(admin, msg="", err=""):
    all_flights = admin_db.get_flights()
    today  = date.today()
    week   = today + timedelta(days=7)

    available = [f for f in all_flights if f.get("status","") not in ("Cancelled","Full") and f.get("dep_time","")]
    taken     = [f for f in all_flights if f.get("status","") in ("Full","Booked")]

    def flight_row(f, show_del=True):
        sc_map = {"Scheduled":"#2563EB","On Time":"#16A34A","Delayed":"#D97706","Cancelled":"#DC2626","Full":"#6B7280"}
        sc = sc_map.get(f.get("status","Scheduled"), "#2563EB")
        badge = f'<span style="background:{sc}22;color:{sc};padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700">{f.get("status","Scheduled")}</span>'
        del_btn = f'<a href="/admin/flights/delete/{f["id"]}" onclick="return confirm(\'Delete?\')"><button class="btn bdanger">Delete</button></a>' if show_del else ""
        return f'<tr><td style="font-weight:600;color:#1E293B">{f["airline"]}</td><td>{f["origin"]} &#8594; {f["dest"]}</td><td>{f["dep_time"]}</td><td>{f["arr_time"]}</td><td style="color:#16A34A;font-weight:600">{f["price"]}</td><td>{badge}</td><td>{del_btn}</td></tr>'

    avail_rows = "".join(flight_row(f) for f in available) or '<tr class="er"><td colspan="7">No available flights</td></tr>'
    taken_rows = "".join(flight_row(f) for f in taken)     or '<tr class="er"><td colspan="7">No fully booked flights</td></tr>'

    body = f'''<div class="ph"><h1>&#9992; Flights</h1><p>Available flights this week · {len(available)} available · {len(taken)} fully booked</p></div>
    {_alert(msg,err)}
    <div class="card" style="margin-bottom:20px">
      <div class="card-hdr"><h3>&#43; Add Flight</h3></div>
      <div class="card-body">
        <form method="post" action="/admin/flights/add"><div class="fg2">
        <div><label>Airline *</label><input name="airline" placeholder="Philippine Airlines" required/></div>
        <div><label>Status</label><select name="status"><option>Scheduled</option><option>On Time</option><option>Delayed</option><option>Cancelled</option><option>Full</option></select></div>
        <div><label>Origin *</label><input name="origin" placeholder="Manila, Cebu..." required/></div>
        <div><label>Destination *</label><input name="dest" placeholder="Baguio, Ilocos..." required/></div>
        <div><label>Departure *</label><input name="dep_time" placeholder="06:00 AM" required/></div>
        <div><label>Arrival *</label><input name="arr_time" placeholder="07:30 AM" required/></div>
        <div><label>Price</label><input name="price" placeholder="PHP 2,500"/></div>
        </div><button class="btn bprimary" type="submit" style="padding:9px 22px;font-size:13px">&#43; Add Flight</button>
        </form>
      </div>
    </div>
    <div class="card" style="margin-bottom:20px">
      <div class="card-hdr"><h3>&#9989; Available Flights ({len(available)})</h3></div>
      <table><thead><tr><th>Airline</th><th>Route</th><th>Departs</th><th>Arrives</th><th>Price</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>{avail_rows}</tbody></table>
    </div>
    <div class="card">
      <div class="card-hdr"><h3>&#128683; Fully Booked / Cancelled ({len(taken)})</h3></div>
      <table><thead><tr><th>Airline</th><th>Route</th><th>Departs</th><th>Arrives</th><th>Price</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>{taken_rows}</tbody></table>
    </div>'''
    return shell("Flights", body, "flights", admin)

# ── ADMIN PROFILE ──
def profile_page(admin, msg="", err=""):
    ainit  = (admin.get("fullname","A") or "A")[0].upper()
    aname  = admin.get("fullname","ATLAS Administrator")
    aemail = admin.get("email","admin@atlas.ph")
    created= (admin.get("created","") or "")[:10]
    body = f'''<div class="ph"><h1>&#128100; Admin Profile</h1><p>Your account information</p></div>
    {_alert(msg,err)}
    <div style="display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start">
      <div class="card"><div class="card-body" style="text-align:center;padding:32px 20px">
        <div style="width:90px;height:90px;background:linear-gradient(135deg,#0038A8,#CE1126);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:36px;margin:0 auto 18px">{ainit}</div>
        <div style="font-size:20px;font-weight:800">{aname}</div>
        <div style="font-size:13px;color:#94A3B8;margin-top:4px">{aemail}</div>
        <div style="margin-top:10px"><span class=bb>Super Admin</span></div>
        <div style="height:1px;background:#F1F5F9;margin:20px 0"></div>
        <div style="text-align:left;display:flex;flex-direction:column;gap:10px">
          <div style="display:flex;justify-content:space-between;font-size:13px"><span style="color:#94A3B8;font-weight:600">Role</span><span style="font-weight:700">Super Admin</span></div>
          <div style="display:flex;justify-content:space-between;font-size:13px"><span style="color:#94A3B8;font-weight:600">Member since</span><span style="font-weight:700">{created}</span></div>
          <div style="display:flex;justify-content:space-between;font-size:13px"><span style="color:#94A3B8;font-weight:600">System</span><span style="font-weight:700">ATLAS v1.0</span></div>
          <div style="display:flex;justify-content:space-between;font-size:13px"><span style="color:#94A3B8;font-weight:600">Region</span><span style="font-weight:700">Luzon, PH</span></div>
        </div>
      </div></div>
      <div>
        <div class="card" style="margin-bottom:16px"><div class="card-hdr"><h3>&#128274; Account Information</h3></div><div class="card-body">
          <div style="background:#FEF9C3;border:1px solid #FDE047;border-radius:8px;padding:10px 14px;font-size:13px;color:#854D0E;margin-bottom:16px">&#9432; Admin account details are locked for security. Only password changes are allowed.</div>
          <div class="fg2" style="margin-bottom:0">
            <div><label>Full Name</label><input value="{aname}" disabled style="background:#F1F5F9;color:#6B7280;cursor:not-allowed;margin-bottom:0"/></div>
            <div><label>Email Address</label><input value="{aemail}" disabled style="background:#F1F5F9;color:#6B7280;cursor:not-allowed;margin-bottom:0"/></div>
            <div><label>Username</label><input value="admin" disabled style="background:#F1F5F9;color:#6B7280;cursor:not-allowed;margin-bottom:0"/></div>
            <div><label>Role</label><input value="Super Admin" disabled style="background:#F1F5F9;color:#6B7280;cursor:not-allowed;margin-bottom:0"/></div>
          </div>
        </div></div>
        <div class="card"><div class="card-hdr"><h3>&#128272; Change Password</h3></div><div class="card-body">
          <form method="post" action="/admin/profile/update">
            <div class="fg2">
              <div><label>New Password</label><input name="new_password" type="password" placeholder="Min. 8 characters"/></div>
              <div><label>Confirm Password</label><input name="confirm_password" type="password" placeholder="Repeat password"/></div>
            </div>
            <button class="btn bprimary" type="submit" style="padding:10px 24px;font-size:13px">&#128272; Change Password</button>
          </form>
        </div></div>
      </div>
    </div>'''
    return shell("Admin Profile", body, "profile", admin)
