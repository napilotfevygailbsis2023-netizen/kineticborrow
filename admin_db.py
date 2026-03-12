import sqlite3, hashlib, os, secrets

DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "atlas.db")

def get_conn():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

def init_admin():
    conn = get_conn()
    conn.execute("""CREATE TABLE IF NOT EXISTS admins (
        id       INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        email    TEXT DEFAULT 'admin@atlas.ph',
        fullname TEXT DEFAULT 'ATLAS Administrator',
        created  DATETIME DEFAULT CURRENT_TIMESTAMP
    )""")
    conn.execute("""CREATE TABLE IF NOT EXISTS admin_sessions (
        token    TEXT PRIMARY KEY,
        admin_id INTEGER NOT NULL,
        created  DATETIME DEFAULT CURRENT_TIMESTAMP
    )""")
    conn.execute("""CREATE TABLE IF NOT EXISTS custom_spots (
        id        INTEGER PRIMARY KEY AUTOINCREMENT,
        name      TEXT NOT NULL,
        city      TEXT NOT NULL,
        category  TEXT NOT NULL,
        type      TEXT NOT NULL,
        rating    REAL DEFAULT 4.0,
        entry     TEXT DEFAULT 'Free',
        hours     TEXT DEFAULT '8AM-5PM',
        desc      TEXT DEFAULT '',
        image_url TEXT DEFAULT '',
        created   DATETIME DEFAULT CURRENT_TIMESTAMP
    )""")
    conn.execute("""CREATE TABLE IF NOT EXISTS custom_restaurants (
        id        INTEGER PRIMARY KEY AUTOINCREMENT,
        name      TEXT NOT NULL,
        city      TEXT NOT NULL,
        cuisine   TEXT NOT NULL,
        price     TEXT DEFAULT 'PHP 200-400',
        rating    REAL DEFAULT 4.0,
        hours     TEXT DEFAULT '10AM-10PM',
        image_url TEXT DEFAULT '',
        created   DATETIME DEFAULT CURRENT_TIMESTAMP
    )""")
    conn.execute("""CREATE TABLE IF NOT EXISTS custom_flights (
        id       INTEGER PRIMARY KEY AUTOINCREMENT,
        airline  TEXT NOT NULL,
        origin   TEXT NOT NULL,
        dest     TEXT NOT NULL,
        dep_time TEXT NOT NULL,
        arr_time TEXT NOT NULL,
        price    TEXT DEFAULT 'PHP 2,000',
        status   TEXT DEFAULT 'Scheduled',
        created  DATETIME DEFAULT CURRENT_TIMESTAMP
    )""")
    conn.execute("""CREATE TABLE IF NOT EXISTS custom_guides (
        id        INTEGER PRIMARY KEY AUTOINCREMENT,
        name      TEXT NOT NULL,
        city      TEXT NOT NULL,
        language  TEXT NOT NULL,
        rate      TEXT DEFAULT 'PHP 1,500/day',
        rating    REAL DEFAULT 4.5,
        bio       TEXT DEFAULT '',
        image_url TEXT DEFAULT '',
        created   DATETIME DEFAULT CURRENT_TIMESTAMP
    )""")
    conn.execute("""CREATE TABLE IF NOT EXISTS custom_transport (
        id        INTEGER PRIMARY KEY AUTOINCREMENT,
        route     TEXT NOT NULL,
        type      TEXT NOT NULL,
        origin    TEXT NOT NULL,
        dest      TEXT NOT NULL,
        dep_time  TEXT NOT NULL,
        fare      TEXT DEFAULT 'PHP 100',
        created   DATETIME DEFAULT CURRENT_TIMESTAMP
    )""")
    try:
        conn.execute("ALTER TABLE users ADD COLUMN status TEXT DEFAULT 'active'")
    except: pass
    for tbl, col in [
        ("custom_spots","image_url"),
        ("custom_restaurants","image_url"),
        ("custom_guides","image_url"),
    ]:
        try: conn.execute(f"ALTER TABLE {tbl} ADD COLUMN {col} TEXT DEFAULT ''")
        except: pass
    conn.commit()
    try:
        pw = hashlib.sha256("admin123".encode()).hexdigest()
        conn.execute("INSERT INTO admins (username,password) VALUES (?,?)", ("admin", pw))
        conn.commit()
    except: pass
    conn.close()

def hash_pw(p): return hashlib.sha256(p.encode()).hexdigest()

def admin_login(username, password):
    conn = get_conn()
    row = conn.execute("SELECT * FROM admins WHERE username=? AND password=?",
                       (username.strip(), hash_pw(password))).fetchone()
    conn.close()
    if row:
        token = secrets.token_hex(32)
        conn = get_conn()
        conn.execute("INSERT INTO admin_sessions (token,admin_id) VALUES (?,?)", (token, row["id"]))
        conn.commit(); conn.close()
        return token
    return None

def get_admin_by_token(token):
    if not token: return None
    try:
        conn = get_conn()
        row = conn.execute("""SELECT a.* FROM admins a
            JOIN admin_sessions s ON s.admin_id=a.id WHERE s.token=?""", (token,)).fetchone()
        conn.close()
        return dict(row) if row else None
    except: return None

def admin_logout(token):
    try:
        conn = get_conn()
        conn.execute("DELETE FROM admin_sessions WHERE token=?", (token,))
        conn.commit(); conn.close()
    except: pass

def update_admin_profile(admin_id, fullname, email, new_password=None):
    conn = get_conn()
    if new_password:
        conn.execute("UPDATE admins SET fullname=?,email=?,password=? WHERE id=?",
                     (fullname, email, hash_pw(new_password), admin_id))
    else:
        conn.execute("UPDATE admins SET fullname=?,email=? WHERE id=?",
                     (fullname, email, admin_id))
    conn.commit(); conn.close()

# ── STATS ──
def get_stats():
    conn = get_conn()
    def count(sql):
        try: return conn.execute(sql).fetchone()[0]
        except: return 0
    s = {
        "total_tourists":  count("SELECT COUNT(*) FROM users"),
        "active_tourists": count("SELECT COUNT(*) FROM users WHERE status='active' OR status IS NULL"),
        "suspended":       count("SELECT COUNT(*) FROM users WHERE status='suspended'"),
        "total_spots":     count("SELECT COUNT(*) FROM custom_spots"),
        "total_rests":     count("SELECT COUNT(*) FROM custom_restaurants"),
        "total_flights":   count("SELECT COUNT(*) FROM custom_flights"),
        "total_guides":    count("SELECT COUNT(*) FROM custom_guides"),
        "total_transport": count("SELECT COUNT(*) FROM custom_transport"),
    }
    conn.close()
    return s

def get_recent_tourists(limit=5):
    conn = get_conn()
    rows = conn.execute("SELECT id,fname,lname,email,created,status FROM users ORDER BY created DESC LIMIT ?", (limit,)).fetchall()
    conn.close()
    return [dict(r) for r in rows]

# ── TOURISTS ──
def get_all_tourists():
    conn = get_conn()
    rows = conn.execute("SELECT id,fname,lname,email,created,status FROM users ORDER BY created DESC").fetchall()
    conn.close()
    return [dict(r) for r in rows]

def archive_tourist(uid):
    conn = get_conn()
    conn.execute("UPDATE users SET status='archived' WHERE id=?", (uid,))
    conn.commit(); conn.close()

def set_tourist_status(uid, status):
    conn = get_conn()
    conn.execute("UPDATE users SET status=? WHERE id=?", (status, uid))
    conn.commit(); conn.close()

def delete_tourist(uid):
    conn = get_conn()
    conn.execute("DELETE FROM sessions WHERE user_id=?", (uid,))
    conn.execute("DELETE FROM users WHERE id=?", (uid,))
    conn.commit(); conn.close()

# ── SPOTS CRUD ──
def get_spots():
    conn = get_conn()
    rows = conn.execute("SELECT * FROM custom_spots ORDER BY created DESC").fetchall()
    conn.close()
    return [dict(r) for r in rows]

def add_spot(name, city, category, stype, rating, entry, hours, desc, image_url=''):
    conn = get_conn()
    conn.execute("INSERT INTO custom_spots (name,city,category,type,rating,entry,hours,desc,image_url) VALUES (?,?,?,?,?,?,?,?,?)",
                 (name, city, category, stype, float(rating), entry, hours, desc, image_url))
    conn.commit(); conn.close()

def delete_spot(sid):
    conn = get_conn()
    conn.execute("DELETE FROM custom_spots WHERE id=?", (sid,))
    conn.commit(); conn.close()

# ── RESTAURANTS CRUD ──
def get_restaurants():
    conn = get_conn()
    rows = conn.execute("SELECT * FROM custom_restaurants ORDER BY created DESC").fetchall()
    conn.close()
    return [dict(r) for r in rows]

def add_restaurant(name, city, cuisine, price, rating, hours, image_url=''):
    conn = get_conn()
    conn.execute("INSERT INTO custom_restaurants (name,city,cuisine,price,rating,hours,image_url) VALUES (?,?,?,?,?,?,?)",
                 (name, city, cuisine, price, float(rating), hours, image_url))
    conn.commit(); conn.close()

def delete_restaurant(rid):
    conn = get_conn()
    conn.execute("DELETE FROM custom_restaurants WHERE id=?", (rid,))
    conn.commit(); conn.close()

# ── FLIGHTS CRUD ──
def get_flights():
    conn = get_conn()
    rows = conn.execute("SELECT * FROM custom_flights ORDER BY created DESC").fetchall()
    conn.close()
    return [dict(r) for r in rows]

def add_flight(airline, origin, dest, dep_time, arr_time, price, status):
    conn = get_conn()
    conn.execute("INSERT INTO custom_flights (airline,origin,dest,dep_time,arr_time,price,status) VALUES (?,?,?,?,?,?,?)",
                 (airline, origin, dest, dep_time, arr_time, price, status))
    conn.commit(); conn.close()

def delete_flight(fid):
    conn = get_conn()
    conn.execute("DELETE FROM custom_flights WHERE id=?", (fid,))
    conn.commit(); conn.close()

# ── GUIDES CRUD ──
def get_guides():
    conn = get_conn()
    rows = conn.execute("SELECT * FROM custom_guides ORDER BY created DESC").fetchall()
    conn.close()
    return [dict(r) for r in rows]

def add_guide(name, city, language, rate, rating, bio, image_url=''):
    conn = get_conn()
    conn.execute("INSERT INTO custom_guides (name,city,language,rate,rating,bio,image_url) VALUES (?,?,?,?,?,?,?)",
                 (name, city, language, rate, float(rating), bio, image_url))
    conn.commit(); conn.close()

def delete_guide(gid):
    conn = get_conn()
    conn.execute("DELETE FROM custom_guides WHERE id=?", (gid,))
    conn.commit(); conn.close()

# ── TRANSPORT CRUD ──
def get_transport():
    conn = get_conn()
    rows = conn.execute("SELECT * FROM custom_transport ORDER BY created DESC").fetchall()
    conn.close()
    return [dict(r) for r in rows]

def add_transport(route, ttype, origin, dest, dep_time, fare):
    conn = get_conn()
    conn.execute("INSERT INTO custom_transport (route,type,origin,dest,dep_time,fare) VALUES (?,?,?,?,?,?)",
                 (route, ttype, origin, dest, dep_time, fare))
    conn.commit(); conn.close()

def delete_transport(tid):
    conn = get_conn()
    conn.execute("DELETE FROM custom_transport WHERE id=?", (tid,))
    conn.commit(); conn.close()

init_admin()
