exec(open('storage/blackbox-run/module-probe.py',encoding='utf-8').read().split('seller_email=')[0])
evidence=json.loads((out/'module-http-evidence.json').read_text(encoding='utf-8'))
seller_email=db.execute('select u.email from shops s join users u on u.id=s.user_id limit 1').fetchone()[0]
seller=login(seller_email); buyer=login('module.changed@example.com')
cat=db.execute("select id from categories where slug='home'").fetchone()[0]
data={'name':'Abaca Woven Tote Bag','description':'Handwoven abaca tote bag with color and size choices.','category_id':cat,'price':'199','stock':0,'has_variants':1,'is_active':1}
for i,(color,size,price,stock) in enumerate([('Natural','S',199,10),('Natural','M',249,8),('Natural','L',299,5),('Black','S',199,10),('Black','M',249,0),('Black','L',299,3)]):
 for field,value in {'options_text':f'Color: {color}, Size: {size}','price_override':price,'stock':stock,'sku':f'MCH-TOTE-{color}-{size}','is_active':1}.items():data[f'variants[{i}][{field}]']=value
r=post(seller,'/seller/products',data);record('exact_tote_create',r)
slug=re.search(r'/seller/products/([^/]+)/edit',r.headers['Location']).group(1)
p=dict(db.execute('select * from products where slug=?',[slug]).fetchone())
vs=[dict(v) for v in db.execute('select * from product_variants where product_id=? order by id',[p['id']])]
record('tote_no_variant',post(buyer,'/cart',{'product_id':p['id'],'qty':1}))
for q in ['0','-1','1.5','abc','21']:
 record('tote_invalid_qty_'+q,post(buyer,'/cart',{'product_id':p['id'],'variant_id':vs[0]['id'],'qty':q}))
record('tote_zero_stock_variant',post(buyer,'/cart',{'product_id':p['id'],'variant_id':vs[4]['id'],'qty':1}))
record('tote_add_M_1',post(buyer,'/cart',{'product_id':p['id'],'variant_id':vs[1]['id'],'qty':1}))
record('tote_add_M_2',post(buyer,'/cart',{'product_id':p['id'],'variant_id':vs[1]['id'],'qty':2}))
page=buyer.get(BASE+'/cart');record('cart_merged_747',page,{'has_747':'747.00' in page.text,'tote_lines':page.text.count(p['name'])})
record('tote_add_S_1',post(buyer,'/cart',{'product_id':p['id'],'variant_id':vs[0]['id'],'qty':1}))
page=buyer.get(BASE+'/cart');record('cart_two_variants',page,{'tote_lines':page.text.count(p['name']),'has_199':'199.00' in page.text})

simple={'name':'USB-C Fast Charger 20W','description':'Compact USB-C fast charger for everyday use.','category_id':cat,'price':399,'stock':50,'is_active':1}
r=post(seller,'/seller/products',simple);record('charger_create',r)
chargerslug=re.search(r'/seller/products/([^/]+)/edit',r.headers['Location']).group(1)
charger=dict(db.execute('select * from products where slug=?',[chargerslug]).fetchone())
record('charger_cart',post(buyer,'/cart',{'product_id':charger['id'],'qty':1}))
record('tote_buy_now',post(buyer,'/cart/buy-now',{'product_id':p['id'],'variant_id':vs[1]['id'],'qty':1}))
page=buyer.get(BASE+'/checkout');record('buy_now_checkout',page,{'tote':p['name'] in page.text,'charger':simple['name'] in page.text,'has_249':'249.00' in page.text})
page=buyer.get(BASE+'/cart');record('buy_now_keeps_charger',page,{'charger':simple['name'] in page.text})

for i in range(2):record('save_tote_'+str(i),post(buyer,f'/account/saved/{slug}',{}))
record('unsave_tote',post(buyer,f'/account/saved/{slug}',{'_method':'DELETE'}))

address=dict(db.execute("select a.* from addresses a join users u on u.id=a.user_id where u.email='module.changed@example.com' order by a.id desc limit 1").fetchone())
addr={k:address[k] for k in ['line1','psgc_region_id','psgc_province_id','psgc_city_municipality_id','psgc_barangay_id','postal_code','phone']}
record('switch_default',post(buyer,f'/account/addresses/{address["id"]}',{'_method':'PATCH',**addr,'is_default':1}))
page=buyer.get(BASE+'/checkout');record('default_preselected',page,{'checked_address':bool(re.search(r'value="'+str(address['id'])+r'"[^>]*checked',page.text))})

# Clear Buy Now by adding the charger, then set its quantity to one.
post(buyer,'/cart',{'product_id':charger['id'],'qty':1})
post(buyer,f'/cart/{chargerslug}',{'_method':'PATCH','qty':1})
for v in [vs[0],vs[1]]:post(buyer,f'/cart/{slug}',{'_method':'PATCH','variant_id':v['id'],'qty':0})
page=seller.get(BASE+f'/seller/products/{chargerslug}/edit')
version=re.search(r'name="version" value="([^"]+)"',page.text).group(1)
record('charger_price_420',post(seller,f'/seller/products/{chargerslug}',{'_method':'PUT',**simple,'price':420,'version':version}))
page=buyer.get(BASE+'/cart');record('cart_price_refresh',page,{'has_420':'420.00' in page.text})
buyer.get(BASE+'/checkout')
page=seller.get(BASE+f'/seller/products/{chargerslug}/edit'); version=re.search(r'name="version" value="([^"]+)"',page.text).group(1)
post(seller,f'/seller/products/{chargerslug}',{'_method':'PUT',**simple,'price':480,'version':version})
r=post(buyer,'/checkout',{'address_id':address['id'],'payment_method':'cod','price':'0.01','total':'0.01'})
record('stale_price_tampered_checkout',r)
order=dict(db.execute('select * from orders order by id desc limit 1').fetchone())
evidence['tampered_order_totals']={k:order[k] for k in ['id','subtotal','shipping_fee','total']}
other,_=client();post(other,'/login',{'email':'juanmiguel.delacruz@example.com','password':'Mamimili@2026'})
record('other_buyer_order_access',other.get(BASE+'/orders/'+str(order['id']),allow_redirects=False))
evidence['variant_fixture']={'slug':slug,'product_id':p['id'],'variants':[{'id':v['id'],'options':v['options'],'stock':v['stock']} for v in vs]}
evidence['default_count_after_switch']=db.execute('select count(*) from addresses where user_id=? and is_default=1',[address['user_id']]).fetchone()[0]
(out/'module-http-evidence.json').write_text(json.dumps(evidence,ensure_ascii=False,indent=2),encoding='utf-8')
print('DONE')
