exec(open('storage/blackbox-run/probe.py',encoding='utf-8').read().split('s, html = client()')[0])
evidence=json.loads((out/'http-evidence.json').read_text(encoding='utf-8'))
counter=1000
s,_=client()
record('seller_followup_login',post(s,'/login',{'email':'mariaclara.santos@example.com','password':'Mamimili@2026'}))
page=s.get(BASE+'/seller/apply',timeout=15)
s.token=re.search(r'name="_token" value="([^"]+)"',page.text).group(1)
for shop in ['MC','MCH','Maria Clara Handicrafts Manila','Maria Clara Handicrafts, Manila','Maria Clara Handicrafts']:
    record('seller_shop_'+shop,post(s,'/seller/apply',{'shop_name':shop,'city':'Quezon City','description':'Handicrafts and home goods'}))
for val in ['Tind@26','Tinda@26','tindahan@2026','TINDAHAN@2026','Tindahan@abcd','Tindahan2026']:
    register('seller_exact_password_'+val,{'password':val,'password_confirmation':val},intent='seller')
for val in ['mariaclara.santosexample.com','mariaclara.santos@','mariaclara.santos@example','mariaclara santos@example.com']:
    register('seller_exact_email_'+val,{'email':val},intent='seller')
for val in ['Maria Clara99 Dela Cruz@#',"Niña Corazon Peñaranda-D'Souza"]:
    register('seller_exact_name_'+val,{'name':val},intent='seller')
for shop,extra in [('No Documents Corporation',{'seller_type':'Corporation'}),('TIN Test',{'tin':'123-456-78A-000','postal_code':'11A1'})]:
    record('seller_extra_'+shop,post(s,'/seller/apply',{'shop_name':shop,'city':'Manila','description':'Crafts',**extra}))
other,_,_=register('seller_second_applicant',intent='seller')
page=other.get(BASE+'/seller/apply',timeout=15)
other.token=re.search(r'name="_token" value="([^"]+)"',page.text).group(1)
record('seller_duplicate_shop_first',post(s,'/seller/apply',{'shop_name':'Maria Clara Handicrafts','city':'Manila','description':'Crafts'}))
record('seller_duplicate_shop_second',post(other,'/seller/apply',{'shop_name':'Maria Clara Handicrafts','city':'Manila','description':'Crafts'}))
db=sqlite3.connect(out/'test.sqlite'); db.row_factory=sqlite3.Row
evidence['saved_users']=[dict(r) for r in db.execute('select name,email,phone,role,email_verified_at from users')]
evidence['saved_seller_applications']=[dict(r) for r in db.execute('select shop_name,status from seller_applications')]
(out/'http-evidence.json').write_text(json.dumps(evidence,ensure_ascii=False,indent=2),encoding='utf-8')
