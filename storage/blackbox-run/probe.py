import re, json, sqlite3, pathlib, urllib.request, urllib.parse, urllib.error, http.cookiejar

class Response:
    def __init__(self,r):
        self.status_code=r.code
        self.headers=r.headers
        self.text=r.read().decode('utf-8',errors='replace')
    def json(self):
        return json.loads(self.text)
    def raise_for_status(self):
        if self.status_code>=400: raise RuntimeError(f'HTTP {self.status_code}: {self.text[:300]}')

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None

class Session:
    def __init__(self):
        self.jar=http.cookiejar.CookieJar()
    def request(self,url,data=None,headers=None,allow_redirects=True,timeout=15):
        handlers=[urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(self.jar)]
        if not allow_redirects: handlers.append(NoRedirect())
        opener=urllib.request.build_opener(*handlers)
        req=urllib.request.Request(url, data=urllib.parse.urlencode(data).encode() if data is not None else None,headers=headers or {})
        try: r=opener.open(req,timeout=timeout)
        except urllib.error.HTTPError as e: r=e
        return Response(r)
    def get(self,url,**kwargs): return self.request(url,**kwargs)
    def post(self,url,**kwargs): return self.request(url,**kwargs)

BASE = 'http://127.0.0.1:8876'
out = pathlib.Path('storage/blackbox-run')
evidence = {}
counter = 0

def client():
    s = Session()
    page = s.get(BASE+'/register', timeout=15)
    page.raise_for_status()
    s.token = re.search(r'name="_token" value="([^"]+)"', page.text).group(1)
    return s, page.text

def post(s, path, data):
    return s.post(BASE+path, data={'_token':s.token, **data}, headers={'Accept':'application/json'}, allow_redirects=False, timeout=15)

def record(key, r, extra=None):
    try: body=r.json()
    except ValueError: body=None
    evidence[key]={'status':r.status_code, 'location':r.headers.get('Location'), 'body':body, **(extra or {})}
    print(key, json.dumps(evidence[key], ensure_ascii=True), flush=True)

def register(key, changes=None, omit=(), intent='buyer'):
    global counter
    counter += 1
    s, _ = client()
    data={'name':'Juan Miguel Dela Cruz','email':f'blackbox{counter}@example.com','password':'Mamimili@2026','password_confirmation':'Mamimili@2026','intent':intent,
          'phone':'09171234567','date_of_birth':'1998-05-14','terms':'1'}
    data.update(changes or {})
    for k in omit: data.pop(k,None)
    r = post(s,'/register',data)
    record(key,r,{'input':data})
    return s, data, r

s, html = client()
(out/'registration.html').write_text(html,encoding='utf-8')
record('empty',post(s,'/register',{}))
for intent in ['buyer','seller']:
    label = intent
    name='Juan Miguel Dela Cruz' if intent=='buyer' else 'Maria Clara Santos'
    email='juanmiguel.delacruz@example.com' if intent=='buyer' else 'mariaclara.santos@example.com'
    primary, data, _=register(label+'_valid',{'name':name,'email':email},intent=intent)
    record(label+'_home_unverified',primary.get(BASE+'/home',timeout=15))
    for field in ['name','email','password','password_confirmation','phone','date_of_birth','terms']:
        register(label+'_omit_'+field,omit=[field],intent=intent)
    for val in ['Juan Miguel123 Dela Cruz@#','Niño Angelo Peñaflor-O\'Hara','J','Jo','M'*50,'M'*51]:
        register(label+'_name_'+val,{'name':val},intent=intent)
    for val in ['juanmiguel.delacruzexample.com','juanmiguel.delacruz@','@example.com','juanmiguel.delacruz@example','juanmiguel delacruz@example.com',email,email.upper(),f'  {label}.test01@example.com  ']:
        register(label+'_email_'+val,{'email':val},intent=intent)
    for val in ['0917ABC4567','0917-123-456','12345678901','02-8123-4567','0917123456','09170000001','091712345678','09171234567','+639171234567']:
        register(label+'_phone_'+val,{'phone':val},intent=intent)
    for val in ['Bili@26','Bili@202','mamimili@2026','MAMIMILI@2026','Mamimili@abcd','Mamimili2026']:
        register(label+'_password_'+val,{'password':val,'password_confirmation':val},intent=intent)
    register(label+'_mismatch',{'password_confirmation':'Different@2027'},intent=intent)
    for val in ['2008-10-19','2008-10-18','2008-10-17','2026-10-19']:
        register(label+'_dob_'+val,{'date_of_birth':val},intent=intent)
    s2,_=client()
    record(label+'_login_unverified',post(s2,'/login',{'email':email,'password':'Mamimili@2026'}))
    for path in ['/admin','/admin/sellers','/seller','/rider/dashboard','/logistics','/verify-email']:
        record(label+'_access_'+path,primary.get(BASE+path,allow_redirects=False,timeout=15))
    if intent=='seller':
        page=primary.get(BASE+'/seller/apply',timeout=15)
        (out/'seller-application.html').write_text(page.text,encoding='utf-8')
        for shop in ['MC','MCH','Maria Clara Handicrafts Manila','Maria Clara Handicrafts, Manila','Maria Clara Handicrafts','Maria Clara Handicrafts']:
            record('seller_shop_'+shop,post(primary,'/seller/apply',{'shop_name':shop,'city':'Quezon City','description':'Handicrafts and home goods'}))
        record('seller_no_documents',post(primary,'/seller/apply',{'shop_name':'No Documents Corporation','city':'Manila','description':'Crafts','seller_type':'Corporation'}))
        record('seller_bad_tin_postal',post(primary,'/seller/apply',{'shop_name':'TIN Test','city':'Manila','description':'Crafts','tin':'123-456-78A-000','postal_code':'11A1'}))

register('logistics_intent',intent='logistics')
s,_=client()
for path in ['/logistics','/logistics/register','/logistics/riders/create','/admin/logistics','/verify-email','/rider/activate']:
    record('missing_'+path,s.get(BASE+path,allow_redirects=False,timeout=15))
rider,_,_=register('rider_self_signup',intent='rider')
record('buyer_can_open_rider_form',rider.get(BASE+'/rider/apply',allow_redirects=False,timeout=15))
(out/'rider-application.html').write_text(rider.get(BASE+'/rider/apply',timeout=15).text,encoding='utf-8')

db=sqlite3.connect(out/'test.sqlite')
db.row_factory=sqlite3.Row
evidence['saved_users']=[dict(r) for r in db.execute('select name,email,phone,role,email_verified_at from users')]
evidence['saved_seller_applications']=[dict(r) for r in db.execute('select shop_name,status from seller_applications')]
(out/'http-evidence.json').write_text(json.dumps(evidence,ensure_ascii=False,indent=2),encoding='utf-8')
print('Saved evidence',len(evidence),flush=True)
