exec(open('storage/blackbox-run/probe.py',encoding='utf-8').read().split('s, html = client()')[0])
evidence={}
db=sqlite3.connect(out/'test.sqlite'); db.row_factory=sqlite3.Row

def login(email):
    s,_=client()
    r=post(s,'/login',{'email':email,'password':'password'})
    if r.status_code!=302: raise RuntimeError(r.text[:300])
    p=s.get(BASE+'/account/profile/edit',timeout=15)
    s.token=re.search(r'name="_token" value="([^"]+)"',p.text).group(1)
    return s

seller_email=db.execute('select u.email from shops s join users u on u.id=s.user_id limit 1').fetchone()[0]
buyer_email=db.execute('select u.email from addresses a join users u on u.id=a.user_id limit 1').fetchone()[0]
seller=login(seller_email)
buyer=login(buyer_email)
cat=db.execute("select id from categories where slug='home'").fetchone()[0]
product={'name':'Rattan Fruit Basket','description':'Handwoven rattan fruit basket for the home.','category_id':cat,'price':'450.00','stock':20,'is_active':1}
r=post(seller,'/seller/products',product);record('create_basket',r)
pslug=re.search(r'/seller/products/([^/]+)/edit',r.headers['Location']).group(1)
pid=db.execute('select id from products where slug=?',[pslug]).fetchone()[0]
for field,values in [('price',['0.99','1.00','1.01','999999.99','1000000.00','1000000.01','199.999','-1','abc']),('stock',['0','99999','100000','-1','1.5']),('name',['A'*4,'A'*121]),('description',['A'*19,'A'*3001])]:
    for value in values:
        record('product_'+field+'_'+value[:15],post(seller,'/seller/products',{**product,field:value}))
record('minimal_draft',post(seller,'/seller/products',{'name':'Name Only Draft','is_active':0}))
record('zero_image_publish',post(seller,'/seller/products',{**product,'name':'Zero Image Product'}))
record('variant_three_types',post(seller,'/seller/products',{**product,'name':'Three Dimension Variant','has_variants':1,
    'variants[0][options_text]':'Color: Red, Size: S, Material: Cotton','variants[0][stock]':'2','variants[0][price_override]':'99','variants[0][sku]':'BB-THREE','variants[0][is_active]':1}))

for term in ['Rattan Fruit Basket','rattan','RATTAN','  rattan  ','ratan baskt','zzqxw','<script>alert(1)</script>',"' OR '1'='1",'','   ']:
    r=buyer.get(BASE+'/search?'+urllib.parse.urlencode({'q':term,'clear_city':1}),timeout=15)
    record('search_'+term,r,{'basket_present':'Rattan Fruit Basket' in r.text,'no_results':'No products' in r.text or 'No results' in r.text})

record('cart_add_20',post(buyer,'/cart',{'product_id':pid,'qty':20}))
for value in ['0','1','21','1.5','-1']:
    record('cart_qty_'+value,post(buyer,f'/cart/{pslug}',{'_method':'PATCH','qty':value}))
record('cart_add_again',post(buyer,'/cart',{'product_id':pid,'qty':2}))
cart=buyer.get(BASE+'/cart',timeout=15);(out/'module-cart.html').write_text(cart.text,encoding='utf-8')
record('checkout_no_payment',post(buyer,'/checkout',{'address_id':db.execute('select a.id from addresses a join users u on u.id=a.user_id where u.email=?',[buyer_email]).fetchone()[0]}))
record('chat_start',post(buyer,f'/chat/products/{pslug}',{}))
conversation=db.execute('select id from conversations order by id desc limit 1').fetchone()[0]
for value in ['','   ','A'*1000,'A'*1001]:
    record('chat_length_'+str(len(value))+'_'+str(bool(value.strip())),post(buyer,f'/chat/{conversation}/messages',{'body':value}))
record('seller_chat_1001',post(seller,f'/chat/{conversation}/messages',{'body':'B'*1001}))

for name in ['J','Juan123','J'*51]:
    record('profile_name_'+name[:8],post(buyer,'/account/profile',{'_method':'PATCH','name':name,'email':buyer_email,'phone':'09171234567','current_password':'password'}))
record('email_change_immediate',post(buyer,'/account/profile',{'_method':'PATCH','name':'Guest Buyer','email':'module.changed@example.com','phone':'09171234567','current_password':'password'}))
buyerid=db.execute("select id from users where email='module.changed@example.com'").fetchone()[0]
address=dict(db.execute('select * from addresses where user_id=? order by id limit 1',[buyerid]).fetchone())
addr={k:address[k] for k in ['line1','psgc_region_id','psgc_province_id','psgc_city_municipality_id','psgc_barangay_id','postal_code','phone']}
for value in ['0917ABC4567','0917-123-456','12345678901','02-8123-4567']:
    record('address_phone_'+value,post(buyer,'/account/addresses',{**addr,'phone':value}))
for i in range(7):
    r=post(buyer,'/account/addresses',{**addr,'label':f'Limit probe {i+1}'})
    record('address_number_'+str(6+i),r)
record('delete_default_address',post(buyer,f'/account/addresses/{address["id"]}',{'_method':'DELETE'}))

evidence['database']={'profile':dict(db.execute('select name,email,phone from users where id=?',[buyerid]).fetchone()),'address_count':db.execute('select count(*) from addresses where user_id=?',[buyerid]).fetchone()[0], 'default_count':db.execute('select count(*) from addresses where user_id=? and is_default=1',[buyerid]).fetchone()[0]}
(out/'module-http-evidence.json').write_text(json.dumps(evidence,ensure_ascii=False,indent=2),encoding='utf-8')
print('DONE',len(evidence))
