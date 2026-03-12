#!/usr/bin/env python3
import http.server, socketserver, urllib.parse, os, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import index, flights, weather, attractions, restaurants, guides, transport, itinerary
import login, register, db
import admin_login, admin_panel, admin_db
import guide_portal, guide_db

PORT = int(os.environ.get("PORT", 5000))
BASE = os.path.dirname(os.path.abspath(__file__))
CSS  = os.path.join(BASE, "css", "styles.css")

ROUTES = {
    "/":               lambda p, u: index.render(u),
    "/index.py":       lambda p, u: index.render(u),
    "/flights.py":     lambda p, u: flights.render(p, u),
    "/weather.py":     lambda p, u: weather.render(p.get("location","Manila"), u),
    "/attractions.py": lambda p, u: attractions.render(p.get("city","All"), p.get("cat","All"), p.get("kw",""), u),
    "/restaurants.py": lambda p, u: restaurants.render(p.get("city","All"), p.get("kw",""), u),
    "/guides.py":      lambda p, u: guides.render(p.get("city","All"), p.get("lang","All"), u),
    "/transport.py":   lambda p, u: transport.render(p.get("type","All"), p.get("from","All"), u),
    "/itinerary.py":   lambda p, u: itinerary.render(p.get("dest","Manila"), p.get("days",None), u),
    "/login.py":       lambda p, u: login.render(),
    "/register.py":    lambda p, u: register.render(),
}

def get_token(cookie_header, name="atlas_token"):
    if not cookie_header: return None
    for part in cookie_header.split(";"):
        part = part.strip()
        if part.startswith(f"{name}="):
            return part[len(f"{name}="):]
    return None

def redirect(handler, location, cookie=None):
    handler.send_response(302)
    handler.send_header("Location", location)
    if cookie: handler.send_header("Set-Cookie", cookie)
    handler.end_headers()

def send_html(handler, html, cookie=None):
    b = html.encode("utf-8")
    handler.send_response(200)
    handler.send_header("Content-Type","text/html; charset=utf-8")
    handler.send_header("Content-Length", str(len(b)))
    if cookie: handler.send_header("Set-Cookie", cookie)
    handler.end_headers()
    handler.wfile.write(b)

class ATLASHandler(http.server.SimpleHTTPRequestHandler):

    def get_admin(self):
        a_tok = get_token(self.headers.get("Cookie",""), "atlas_admin")
        return admin_db.get_admin_by_token(a_tok), a_tok

    def require_admin(self):
        admin, tok = self.get_admin()
        if not admin:
            redirect(self, "/admin/login")
            return None, None
        return admin, tok

    def do_GET(self):
        parsed = urllib.parse.urlparse(self.path)
        params = dict(urllib.parse.parse_qsl(parsed.query))
        path   = parsed.path
        cookie = self.headers.get("Cookie","")
        token  = get_token(cookie, "atlas_token")
        user   = db.get_user_by_token(token)

        # CSS
        if path == "/css/styles.css":
            with open(CSS,"rb") as f: css = f.read()
            self.send_response(200)
            self.send_header("Content-Type","text/css; charset=utf-8")
            self.send_header("Content-Length", str(len(css)))
            self.end_headers()
            self.wfile.write(css)
            return


        # ── GUIDE PORTAL GET ──
        if path in ("/guide", "/guide/"):
            send_html(self, guide_portal.render_login()); return
        if path == "/guide/register":
            send_html(self, guide_portal.render_register()); return
        if path == "/guide/logout":
            g_tok = get_token(cookie, "atlas_guide")
            if g_tok: guide_db.logout_guide(g_tok)
            redirect(self, "/guide", "atlas_guide=; Path=/; Max-Age=0"); return
        if path == "/guide/dashboard":
            g_tok = get_token(cookie, "atlas_guide")
            guide = guide_db.get_guide_by_token(g_tok)
            if not guide: redirect(self, "/guide"); return
            send_html(self, guide_portal.render_dashboard(guide)); return
        if path == "/guide/packages":
            g_tok = get_token(cookie, "atlas_guide")
            guide = guide_db.get_guide_by_token(g_tok)
            if not guide: redirect(self, "/guide"); return
            send_html(self, guide_portal.render_packages(guide)); return
        if path == "/guide/bookings":
            g_tok = get_token(cookie, "atlas_guide")
            guide = guide_db.get_guide_by_token(g_tok)
            if not guide: redirect(self, "/guide"); return
            params = dict(urllib.parse.parse_qsl(urllib.parse.urlparse(self.path).query))
            send_html(self, guide_portal.render_bookings(guide, params.get("filter","all"))); return
        if path == "/guide/availability":
            g_tok = get_token(cookie, "atlas_guide")
            guide = guide_db.get_guide_by_token(g_tok)
            if not guide: redirect(self, "/guide"); return
            send_html(self, guide_portal.render_availability(guide)); return
        if path == "/guide/ratings":
            g_tok = get_token(cookie, "atlas_guide")
            guide = guide_db.get_guide_by_token(g_tok)
            if not guide: redirect(self, "/guide"); return
            send_html(self, guide_portal.render_ratings(guide)); return
        if path == "/guide/profile":
            g_tok = get_token(cookie, "atlas_guide")
            guide = guide_db.get_guide_by_token(g_tok)
            if not guide: redirect(self, "/guide"); return
            send_html(self, guide_portal.render_profile(guide)); return

        # User logout
        if path == "/logout.py":
            if token: db.logout(token)
            redirect(self, "/", "atlas_token=; Path=/; Max-Age=0")
            return

        # Admin entry
        if path in ("/admin", "/admin/"):
            redirect(self, "/admin/login"); return
        if path == "/admin/login":
            send_html(self, admin_login.render()); return
        if path == "/admin/logout":
            _, a_tok = self.get_admin()
            if a_tok: admin_db.admin_logout(a_tok)
            redirect(self, "/admin/login", "atlas_admin=; Path=/; Max-Age=0"); return

        # Protected admin routes
        if path.startswith("/admin/"):
            admin, _ = self.get_admin()
            if not admin:
                redirect(self, "/admin/login"); return

            if path == "/admin/dashboard":
                send_html(self, admin_panel.dashboard(admin)); return
            if path == "/admin/tourists":
                tab = params.get("tab","active")
                send_html(self, admin_panel.tourists_page(admin, tab=tab)); return
            if path.startswith("/admin/tourists/archive/"):
                uid = path.split("/")[-1]
                admin_db.set_tourist_status(uid, "archived")
                redirect(self, "/admin/tourists"); return
            if path.startswith("/admin/tourists/suspend/"):
                admin_db.set_tourist_status(path.split("/")[-1], "suspended")
                redirect(self, "/admin/tourists"); return
            if path.startswith("/admin/tourists/activate/"):
                admin_db.set_tourist_status(path.split("/")[-1], "active")
                redirect(self, "/admin/tourists"); return
            if path.startswith("/admin/tourists/delete/"):
                admin_db.delete_tourist(path.split("/")[-1])
                redirect(self, "/admin/tourists"); return
                send_html(self, admin_panel.flights_page(admin)); return
                admin_db.delete_flight(path.split("/")[-1])
                send_html(self, admin_panel.spots_page(admin)); return
                admin_db.delete_spot(path.split("/")[-1])
                send_html(self, admin_panel.restaurants_page(admin)); return
                admin_db.delete_restaurant(path.split("/")[-1])

            if path == "/admin/spots":
                pg = int(params.get("page","1") or "1")
                send_html(self, admin_panel.spots_page(admin, page=pg)); return
            if path.startswith("/admin/spots/delete/"):
                admin_db.delete_spot(path.split("/")[-1])
                redirect(self, "/admin/spots"); return
            if path == "/admin/restaurants":
                pg = int(params.get("page","1") or "1")
                send_html(self, admin_panel.restaurants_page(admin, page=pg)); return
            if path.startswith("/admin/restaurants/delete/"):
                admin_db.delete_restaurant(path.split("/")[-1])
                redirect(self, "/admin/restaurants"); return
            if path == "/admin/guides":
                pg = int(params.get("page","1") or "1")
                tab = params.get("tab","registered")
                send_html(self, admin_panel.guides_page(admin, page=pg, tab=tab)); return
            if path.startswith("/admin/guides/delete/"):
                admin_db.delete_guide(path.split("/")[-1])
                redirect(self, "/admin/guides"); return
            if path == "/admin/transport":
                pg = int(params.get("page","1") or "1")
                send_html(self, admin_panel.transport_page(admin, page=pg)); return
            if path.startswith("/admin/transport/delete/"):
                admin_db.delete_transport(path.split("/")[-1])
                redirect(self, "/admin/transport"); return
            if path == "/admin/flights":
                send_html(self, admin_panel.flights_page(admin)); return
            if path.startswith("/admin/flights/delete/"):
                admin_db.delete_flight(path.split("/")[-1])
                redirect(self, "/admin/flights"); return
            if path == "/admin/profile":
                send_html(self, admin_panel.profile_page(admin)); return
            redirect(self, "/admin/dashboard"); return

        # Public routes
        handler = ROUTES.get(path)
        if handler is None:
            self.send_error(404, "Page not found"); return
        send_html(self, handler(params, user))

    def do_POST(self):
        path   = urllib.parse.urlparse(self.path).path
        cookie = self.headers.get("Cookie","")
        content_type = self.headers.get("Content-Type","")
        length = int(self.headers.get("Content-Length",0))

        # Handle multipart/form-data (file uploads)
        if "multipart/form-data" in content_type:
            environ = {"REQUEST_METHOD":"POST","CONTENT_TYPE":content_type,"CONTENT_LENGTH":length}
            fp = self.rfile
            fs = cgi.FieldStorage(fp=fp, headers=self.headers, environ=environ)
            form = {}
            files = {}
            for key in fs.keys():
                field = fs[key]
                if hasattr(field, "filename") and field.filename:
                    files[key] = (field.filename, field.file.read())
                else:
                    form[key] = field.value if hasattr(field,"value") else ""
            body = ""
        else:
            body = self.rfile.read(length).decode("utf-8")
            form = dict(urllib.parse.parse_qsl(body))
            files = {}

        if path == "/login.py":
            token, err = login.handle_post(form)
            if token: redirect(self, "/", f"atlas_token={token}; Path=/; Max-Age=86400")
            else: send_html(self, err)

        elif path == "/register.py":
            _, html = register.handle_post(form)
            send_html(self, html)

        elif path == "/admin/login":
            token, err = admin_login.handle_post(form)
            if token: redirect(self, "/admin/dashboard", f"atlas_admin={token}; Path=/; Max-Age=86400")
            else: send_html(self, err)

        elif path.startswith("/admin/"):
            a_tok = get_token(cookie, "atlas_admin")
            admin = admin_db.get_admin_by_token(a_tok)
            if not admin:
                redirect(self, "/admin/login"); return

                try:
                    admin_db.add_flight(form.get("airline",""),form.get("origin",""),form.get("dest",""),
                        form.get("dep_time",""),form.get("arr_time",""),form.get("price",""),form.get("status","Scheduled"))
                    send_html(self, admin_panel.flights_page(admin, msg="Flight added!"))
                except Exception as e: send_html(self, admin_panel.flights_page(admin))

                try:
                    admin_db.add_spot(form.get("name",""),form.get("city",""),form.get("category",""),
                        form.get("type",""),form.get("rating","4.0"),form.get("entry","Free"),
                        form.get("hours","8AM-5PM"),form.get("desc",""))
                    send_html(self, admin_panel.spots_page(admin, msg="Attraction added!"))
                except Exception as e: send_html(self, admin_panel.spots_page(admin))

                try:
                    admin_db.add_restaurant(form.get("name",""),form.get("city",""),form.get("cuisine",""),
                        form.get("price",""),form.get("rating","4.0"),form.get("hours",""))
                    send_html(self, admin_panel.restaurants_page(admin, msg="Restaurant added!"))
                except Exception as e: send_html(self, admin_panel.restaurants_page(admin))


            elif path == "/admin/spots/add":
                try:
                    img_url = ""
                    if "image_file" in files:
                        fname, fdata = files["image_file"]
                        if fdata: img_url = admin_panel.save_image(fdata, fname)
                    admin_db.add_spot(form.get("name",""),form.get("city",""),form.get("category",""),
                        form.get("type",""),form.get("rating","4.0"),form.get("entry","Free"),
                        form.get("hours","8AM-5PM"),form.get("desc",""),img_url)
                    send_html(self, admin_panel.spots_page(admin, msg="Attraction added!", tab="list"))
                except Exception as e: send_html(self, admin_panel.spots_page(admin, err=str(e)))

            elif path == "/admin/restaurants/add":
                try:
                    img_url = ""
                    if "image_file" in files:
                        fname, fdata = files["image_file"]
                        if fdata: img_url = admin_panel.save_image(fdata, fname)
                    admin_db.add_restaurant(form.get("name",""),form.get("city",""),form.get("cuisine",""),
                        form.get("price",""),form.get("rating","4.0"),form.get("hours",""),img_url)
                    send_html(self, admin_panel.restaurants_page(admin, msg="Restaurant added!", tab="list"))
                except Exception as e: send_html(self, admin_panel.restaurants_page(admin, err=str(e)))
            elif path == "/admin/guides/add":
                try:
                    img_url = ""
                    if "image_file" in files:
                        fname, fdata = files["image_file"]
                        if fdata: img_url = admin_panel.save_image(fdata, fname)
                    admin_db.add_guide(form.get("name",""),form.get("city",""),form.get("language",""),
                        form.get("rate",""),form.get("rating","4.5"),form.get("bio",""),img_url)
                    send_html(self, admin_panel.guides_page(admin, msg="Tour guide added!", tab="added"))
                except Exception as e: send_html(self, admin_panel.guides_page(admin, err=str(e)))

            elif path == "/admin/flights/add":
                try:
                    admin_db.add_flight(form.get("airline",""),form.get("origin",""),form.get("dest",""),
                        form.get("dep_time",""),form.get("arr_time",""),form.get("price",""),form.get("status","Scheduled"))
                    send_html(self, admin_panel.flights_page(admin, msg="Flight added!"))
                except Exception as e: send_html(self, admin_panel.flights_page(admin, err=str(e)))

            elif path == "/admin/transport/add":
                try:
                    admin_db.add_transport(form.get("route",""),form.get("type",""),form.get("origin",""),
                        form.get("dest",""),form.get("dep_time",""),form.get("fare",""))
                    send_html(self, admin_panel.transport_page(admin, msg="Route added!", tab="list"))
                except Exception as e: send_html(self, admin_panel.transport_page(admin, err=str(e)))

            elif path == "/admin/profile/update":
                new_pw  = form.get("new_password","").strip()
                confirm = form.get("confirm_password","").strip()
                if not new_pw:
                    send_html(self, admin_panel.profile_page(admin, err="Please enter a new password."))
                elif new_pw != confirm:
                    send_html(self, admin_panel.profile_page(admin, err="Passwords do not match."))
                elif len(new_pw) < 8:
                    send_html(self, admin_panel.profile_page(admin, err="Password must be at least 8 characters."))
                else:
                    admin_db.update_admin_profile(admin["id"], admin.get("fullname",""), admin.get("email",""), new_pw)
                    updated = admin_db.get_admin_by_token(a_tok)
                    send_html(self, admin_panel.profile_page(updated, msg="Password changed successfully!"))
            else:
                self.send_error(404)


        elif path == "/guide/login":
            email = form.get("email",""); pw = form.get("password","")
            ok, token, guide = guide_db.login_guide(email, pw)
            if ok:
                redirect(self, "/guide/dashboard", f"atlas_guide={token}; Path=/; Max-Age=86400")
            else:
                send_html(self, guide_portal.render_login(error="Invalid email or password."))

        elif path == "/guide/register":
            fname=form.get("fname","").strip(); lname=form.get("lname","").strip()
            email=form.get("email","").strip(); pw=form.get("password","").strip()
            pw2=form.get("password2","").strip(); phone=form.get("phone","").strip()
            city=form.get("city","Manila")
            if not all([fname,lname,email,pw,phone]):
                send_html(self, guide_portal.render_register(error="Please fill in all required fields."))
            elif pw != pw2:
                send_html(self, guide_portal.render_register(error="Passwords do not match."))
            elif len(pw) < 6:
                send_html(self, guide_portal.render_register(error="Password must be at least 6 characters."))
            else:
                ok, msg = guide_db.register_guide(fname, lname, email, pw, phone, city)
                if ok:
                    send_html(self, guide_portal.render_login(success="Account created! Please log in."))
                else:
                    send_html(self, guide_portal.render_register(error=msg))

        elif path.startswith("/guide/"):
            g_tok = get_token(cookie, "atlas_guide")
            guide = guide_db.get_guide_by_token(g_tok)
            if not guide:
                redirect(self, "/guide"); return
            action = form.get("action","")
            msg = ""; err = ""
            if action == "add_package":
                guide_db.add_package(guide["id"], form); msg = "Package added!"
            elif action == "delete_package":
                guide_db.delete_package(int(form.get("pkg_id",0)), guide["id"]); msg = "Package deleted."
            elif action == "accept_booking":
                guide_db.update_booking_status(int(form.get("booking_id",0)), guide["id"], "accepted"); msg = "Booking accepted!"
            elif action == "reject_booking":
                guide_db.update_booking_status(int(form.get("booking_id",0)), guide["id"], "rejected"); msg = "Booking rejected."
            elif action == "cancel_booking":
                guide_db.update_booking_status(int(form.get("booking_id",0)), guide["id"], "cancelled"); msg = "Booking cancelled."
            elif action == "reschedule_booking":
                new_date = form.get("new_date","")
                guide_db.update_booking_status(int(form.get("booking_id",0)), guide["id"], "rescheduled", f"Rescheduled to {new_date}"); msg = f"Rescheduled to {new_date}!"
            elif action == "update_availability":
                avail = ",".join([v for k,v in urllib.parse.parse_qsl(body) if k=="days"])
                if avail:
                    conn = guide_db.get_conn()
                    conn.execute("UPDATE tour_guides SET availability=? WHERE id=?", (avail, guide["id"]))
                    conn.commit(); conn.close()
                    guide = guide_db.get_guide_by_id(guide["id"])
                msg = "Availability updated!"
            elif action == "update_profile":
                guide_db.update_guide_profile(guide["id"], form)
                guide = guide_db.get_guide_by_id(guide["id"]); msg = "Profile updated!"
            elif action == "change_password":
                pw1=form.get("new_pw",""); pw2=form.get("new_pw2","")
                if pw1 and pw1==pw2 and len(pw1)>=6:
                    guide_db.change_guide_password(guide["id"], pw1); msg = "Password changed!"
                else: err = "Passwords do not match or too short."
            # Redirect to correct page
            if path == "/guide/packages":
                send_html(self, guide_portal.render_packages(guide, msg, err))
            elif path == "/guide/bookings":
                params = dict(urllib.parse.parse_qsl(urllib.parse.urlparse(self.path).query))
                send_html(self, guide_portal.render_bookings(guide, params.get("filter","all"), msg, err))
            elif path == "/guide/availability":
                send_html(self, guide_portal.render_availability(guide, msg, err))
            elif path == "/guide/profile":
                send_html(self, guide_portal.render_profile(guide, msg, err))
            else:
                send_html(self, guide_portal.render_dashboard(guide, msg, err))



        else:
            self.send_error(404)

    def log_message(self, *a): pass

if __name__ == "__main__":
    print("="*50)
    print("  ATLAS - Luzon Travel Companion")
    print("="*50)
    print(f"\n  Site:  http://localhost:{PORT}")
    print(f"  Admin: http://localhost:{PORT}/admin")
    print(f"  Guide: http://localhost:{PORT}/guide")
    print(f"\n  Admin login: admin / admin123")
    print("  Ctrl+C to stop\n")
    socketserver.TCPServer.allow_reuse_address = True
    with socketserver.TCPServer(("0.0.0.0", PORT), ATLASHandler) as s:
        try: s.serve_forever()
        except KeyboardInterrupt: print("\n  Goodbye!")
