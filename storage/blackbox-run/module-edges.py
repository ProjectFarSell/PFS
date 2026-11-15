exec(open('storage/blackbox-run/module-probe.py',encoding='utf-8').read().split('seller_email=')[0])
evidence=json.loads((out/'module-http-evidence.json').read_text(encoding='utf-8'))
seller=login(db.execute('select u.email from shops s join users u on u.id=s.user_id limit 1').fetchone()[0]); buyer=login('module.changed@example.com')
cat=db.execute("select id from categories where slug='home'").fetchone()[0]
base={'name':'Boundary Product','description':'Valid product description for module edge checks.','category_id':cat,'price':199,'stock':20,'is_active':1}
for n in [20,21]:
 data={**base,'name':f'Options {n}','has_variants':1,'stock':0}
 for i in range(n):
  for field,value in {'options_text':f'Color: Option{i}','stock':1,'price_override':199,'sku':f'BB-OPT-{n}-{i}'}.items():data[f'variants[{i}][{field}]']=value
 record('options_'+str(n),post(seller,'/seller/products',data))
data={**base,'has_variants':1,'stock':0,'variants[0][options_text]':'Color: Red','variants[0][stock]':1,'variants[1][options_text]':'Color: red','variants[1][stock]':1}
record('duplicate_variant_options',post(seller,'/seller/products',data))
record('empty_variant_options',post(seller,'/seller/products',{**base,'has_variants':1,'variants[0][options_text]':'','variants[0][stock]':1}))
record('duplicate_sku_existing',post(seller,'/seller/products',{**base,'has_variants':1,'variants[0][options_text]':'Color: Red','variants[0][stock]':1,'variants[0][sku]':'MCH-TOTE-Natural-S'}))
before=db.execute("select count(*) from products where name='Double Publish Probe'").fetchone()[0]
for i in range(2):record('duplicate_publish_'+str(i),post(seller,'/seller/products',{**base,'name':'Double Publish Probe'}))
evidence['duplicate_publish_records']=db.execute("select count(*) from products where name='Double Publish Probe'").fetchone()[0]-before
slug=evidence['variant_fixture']['slug']; pid=evidence['variant_fixture']['product_id']; vs=evidence['variant_fixture']['variants']; mid=vs[1]['id']
record('tote_one_variant_order_cart',post(buyer,'/cart',{'product_id':pid,'variant_id':mid,'qty':1}))
address=db.execute('select id from addresses where user_id=(select id from users where email=?) order by is_default desc,id desc limit 1',['module.changed@example.com']).fetchone()[0]
record('tote_one_variant_order',post(buyer,'/checkout',{'address_id':address,'payment_method':'cod'}))
evidence['variant_stock_after_order']=[dict(r) for r in db.execute('select id,stock from product_variants where product_id=? order by id',[pid])]
orderid=db.execute('select id from orders order by id desc limit 1').fetchone()[0]
edit=seller.get(BASE+f'/seller/products/{slug}/edit')
version=re.search(r'name="version" value="([^"]+)"',edit.text).group(1)
data={**base,'name':'Abaca Woven Tote Bag','has_variants':1,'stock':0,'_method':'PUT','version':version}
for i,v in enumerate(db.execute('select * from product_variants where product_id=? order by id',[pid]).fetchall()):
 v=dict(v); opt=json.loads(v['options']);opt['Color']='Beige' if opt['Color']=='Natural' else 'Brown'
 for k,value in {'id':v['id'],'options_text':', '.join(f'{k}: {val}' for k,val in opt.items()),'price_override':249,'stock':v['stock'],'is_active':1}.items():data[f'variants[{i}][{k}]']=value
record('rename_variant_after_order',post(seller,f'/seller/products/{slug}',data))
page=buyer.get(BASE+f'/orders/{orderid}');record('order_variant_snapshot',page,{'natural':'Natural' in page.text,'price_249':'249.00' in page.text})
page=buyer.get(BASE+f'/p/{slug}');record('renamed_variant_page',page,{'beige':'Beige' in page.text,'brown':'Brown' in page.text})
other,_=client();post(other,'/login',{'email':'juanmiguel.delacruz@example.com','password':'Mamimili@2026'}); page=other.get(BASE+'/account/profile');other.token=re.search(r'name="_token" value="([^"]+)"',page.text).group(1)
other_before=other.get(BASE+'/cart').text
record('cart_user_id_tamper',post(buyer,'/cart',{'product_id':pid,'variant_id':mid,'qty':1,'user_id':1,'buyer_id':1}))
other_after=other.get(BASE+'/cart').text
evidence['other_cart_unchanged']=('Your cart is empty' in other_before and 'Your cart is empty' in other_after)
(out/'module-http-evidence.json').write_text(json.dumps(evidence,ensure_ascii=False,indent=2),encoding='utf-8')
print('DONE')
