'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require(require.resolve('jsdom',{paths:[path.join(__dirname,'../../mobile')]}));
const read=p=>fs.readFileSync(path.join(__dirname,'../..',p),'utf8');
const clone=x=>JSON.parse(JSON.stringify(x));
const settle=async()=>{for(let i=0;i<8;i++)await new Promise(r=>setImmediate(r));};
const deferred=()=>{let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return{promise,resolve,reject};};
async function fixture(options={}){
 const parsed=new JSDOM(read('resources/views/web/user-screen.blade.php'));
 const markup=parsed.window.document.querySelector('[data-user-shopping-lists]').outerHTML;parsed.window.close();
 const dom=new JSDOM(markup,{url:'https://qa.invalid/web/shopping-list',runScripts:'outside-only'});
 const w=dom.window,calls=[],disposers=[],writes=[];let mount,changed=false,heldSelection=null,detailReads=0;const heldReads=[],alternativeCallbacks=[];
 const item={id:21,free_text_name:'Artículo QA',display_name:'Artículo QA',quantity:'1.0000',unit:{id:8,code:'package',symbol:'paq'},estimated_price:'1800.01',estimated_subtotal:1800.01,status:'pending'};
 if(options.zeroPrice)item.estimated_price=0;
 if(options.mapped)item.product={id:112,name:'Producto QA'};
 const lists={16:{id:16,family_group_id:8,status:'draft',source_type:'manual',estimated_total:options.existing&&!options.zeroPrice?'1800.01':'0.00',items:options.existing?[clone(item)]:[]},17:{id:17,family_group_id:8,status:'draft',source_type:'manual',estimated_total:'50.00',items:[]}};
 function apply(method,body){
  if(method==='DELETE')lists[16].items=[];
  else {const next=Object.assign(clone(item),body);next.quantity=Number(next.quantity).toFixed(4);next.estimated_price=next.estimated_price===null?null:Number(next.estimated_price).toFixed(2);lists[16].items=[next];}
  const priced=lists[16].items.filter(x=>x.estimated_price!==null);lists[16].estimated_total=priced.reduce((n,x)=>n+Number(x.quantity)*Number(x.estimated_price),0).toFixed(2);changed=true;
  return method==='DELETE'?{}:{data:clone(lists[16].items[0])};
 }
 w.HTMLElement.prototype.scrollIntoView=function(){};w.confirm=()=>true;
 w.CCPage={register:(n,f)=>{mount=f;},onDispose:f=>disposers.push(f)};
 if(options.captureAlternatives)w.ShoppingAlternatives={mount:(el,g,id,config)=>alternativeCallbacks.push(config.onSelected)};
 w.CCApi={request:(url,config={})=>{
  calls.push({url,method:config.method||'GET',body:config.body?clone(config.body):null});
  const p=new URL(url,'https://qa.invalid').pathname;
  if(config.method){
   assert.match(p,/^\/api\/v1\/family-groups\/8\/shopping-lists\/16\/items(?:\/21)?$/);
   const record={method:config.method,body:clone(config.body||{})};writes.push(record);
   if(options.writeError)return Promise.reject(options.writeError);
   if(options.holdWrite){const d=deferred();record.resolve=()=>d.resolve(apply(config.method,config.body));return d.promise;}
   return Promise.resolve(apply(config.method,config.body));
  }
  if(p==='/api/v1/family-groups')return Promise.resolve({data:[{id:8,name:'Hogar QA'},{id:9,name:'Segundo hogar QA'}]});
  if(p==='/api/v1/units')return Promise.resolve({data:[{id:8,code:'package',symbol:'paq'},{id:1,code:'g',symbol:'g'}]});
  if(p==='/api/v1/products')return Promise.resolve({data:[{id:112,name:'Producto QA'},{id:113,name:'Otro producto QA'}]});
  if(p.endsWith('/shopping-lists/16')){
   detailReads++;
   if(changed&&options.refreshError)return Promise.reject(new Error('Lectura del total no disponible'));
   if(detailReads>1&&options.holdRefresh){const d=deferred();d.snapshot=clone(lists[16]);heldReads.push(d);return d.promise;}
   return Promise.resolve({data:clone(lists[16])});
  }
  if(p.endsWith('/shopping-lists/17')){
   if(options.holdSelection){heldSelection=deferred();return heldSelection.promise;}
   return Promise.resolve({data:clone(lists[17])});
  }
  if(p.endsWith('/shopping-lists/16/items'))return Promise.resolve({data:clone(lists[16].items)});
  if(p.endsWith('/shopping-lists'))return Promise.resolve({data:p.includes('/9/')?[]:clone(Object.values(lists).filter(x=>!new URL(url,'https://qa.invalid').searchParams.get('status')||x.status===new URL(url,'https://qa.invalid').searchParams.get('status'))),meta:{total:p.includes('/9/')?0:2,current_page:1,last_page:1}});
  return Promise.resolve({data:[]});
 }};
 w.eval(read('public/js/user-shopping-lists.js'));mount();await settle();
 const clickList=id=>w.document.querySelector('[data-shopping-list-show="'+id+'"]').click();
 clickList(16);await settle();
 const form=()=>w.document.querySelector('[data-shopping-list-item-form]');
 const create=()=>{form().elements.free_text_name.value='Artículo QA';form().elements.quantity.value='1';form().elements.estimated_price.value='1800.01';form().requestSubmit();};
 const detail=()=>w.document.querySelector('[data-shopping-list-detail]');
 const total=()=>Array.from(detail().querySelectorAll('.table-line')).find(x=>x.querySelector('span')?.textContent==='Total estimado')?.querySelector('strong').textContent;
 return{dom,w,calls,writes,lists,form,create,clickList,total,detail,dispose:()=>disposers.forEach(f=>f()),close:()=>dom.window.close(),alternativeCallbacks,releaseRefresh:(index=0)=>heldReads[index].resolve({data:heldReads[index].snapshot}),hasHeldRefresh:()=>heldReads.length>0,releaseSelection:()=>heldSelection.resolve({data:clone(lists[17])})};
}

test('manual create update delete refresh authoritative total in detail and table',async t=>{
 const h=await fixture();t.after(h.close);h.create();await settle();
 assert.equal(h.total(),'1800.01');assert.match(h.w.document.querySelector('[data-shopping-list-body]').textContent,/1800.01/);
 h.w.document.querySelector('[data-shopping-list-item-edit="21"]').click();h.form().elements.quantity.value='2';h.form().requestSubmit();await settle();
 assert.equal(h.total(),'3600.02');assert.match(h.w.document.querySelector('[data-shopping-list-body]').textContent,/3600.02/);
 h.w.document.querySelector('[data-shopping-list-item-delete="21"]').click();await settle();
 assert.equal(h.total(),'0.00');assert.match(h.detail().textContent,/no tiene items/i);
 assert.deepEqual(h.writes.map(x=>x.method),['POST','PATCH','DELETE']);
});

test('saved item followed by failed detail GET reports refresh failure without another mutation',async t=>{
 const h=await fixture({refreshError:true});t.after(h.close);h.create();await settle();
 assert.equal(h.writes.length,1);assert.equal(h.lists[16].estimated_total,'1800.01');
 const message=h.w.document.querySelector('[data-shopping-lists-message]').textContent;
 assert.match(message,/guard|agreg/i);assert.match(message,/actualiz|recarg|abrir/i);
 assert.equal(h.form().elements.free_text_name.value,'');
});

test('422 mutation error preserves input, does not fetch new total, and allows correction',async t=>{
 const h=await fixture({writeError:{status:422,payload:{error:{message:'Precio inválido <script>'}}}});t.after(h.close);
 const before=h.calls.length;h.create();await settle();
 assert.equal(h.writes.length,1);assert.equal(h.calls.length,before+1);assert.equal(h.total(),'0.00');
 assert.equal(h.form().elements.free_text_name.value,'Artículo QA');assert.equal(h.form().querySelector('button[type="submit"]').disabled,false);
 assert.match(h.w.document.querySelector('[data-shopping-lists-message]').textContent,/Precio inválido <script>/);
 assert.equal(h.w.document.querySelector('[data-shopping-lists-message] script'),null);
});

for(const method of ['POST','DELETE'])test('pending '+method+' ignores double click until refreshed',async t=>{
 const h=await fixture({holdWrite:true,existing:method==='DELETE'});t.after(h.close);
 if(method==='POST'){h.create();h.form().requestSubmit();}else{h.w.document.querySelector('[data-shopping-list-item-delete="21"]').click();h.w.document.querySelector('[data-shopping-list-item-delete="21"]').click();}
 assert.equal(h.writes.length,1);h.writes[0].resolve();await settle();
 assert.equal(h.total(),method==='DELETE'?'0.00':'1800.01');
});

test('pending write cannot reset or overwrite another selected list',async t=>{
 const h=await fixture({holdWrite:true});t.after(h.close);h.create();h.clickList(17);await settle();
 assert.equal(h.total(),'50.00');h.form().elements.free_text_name.value='Edición de otra lista';
 h.writes[0].resolve();await settle();
 assert.equal(h.total(),'50.00');assert.equal(h.form().elements.free_text_name.value,'Edición de otra lista');
 assert.doesNotMatch(h.w.document.querySelector('[data-shopping-lists-message]').textContent,/agregado/);
});

test('pending authoritative refresh is discarded as soon as another list is requested',async t=>{
 const h=await fixture({holdRefresh:true,holdSelection:true});t.after(h.close);h.create();await settle();assert.equal(h.hasHeldRefresh(),true);
 h.clickList(17);h.releaseRefresh();await settle();
 assert.notEqual(h.total(),'1800.01','old refresh must not apply even before new selection GET resolves');
 h.releaseSelection();await settle();assert.equal(h.total(),'50.00');
});

test('switching household during a pending mutation leaves new household untouched',async t=>{
 const h=await fixture({holdWrite:true});t.after(h.close);h.create();
 const field=h.w.document.querySelector('[data-shopping-list-group]');field.value='9';field.dispatchEvent(new h.w.Event('change',{bubbles:true}));await settle();
 h.writes[0].resolve();await settle();assert.match(h.detail().textContent,/Selecciona una lista/);assert.equal(field.value,'9');
 assert.doesNotMatch(h.w.document.querySelector('[data-shopping-lists-message]').textContent,/agregado/);
 assert.equal(h.calls.filter(x=>x.method!=='GET').length,1);
});

test('disposed screen ignores successful mutation and performs no refresh reads',async t=>{
 const h=await fixture({holdWrite:true});t.after(h.close);h.create();h.dispose();const count=h.calls.length;h.writes[0].resolve();await settle();
 assert.equal(h.calls.length,count);assert.equal(h.total(),'0.00');
});


test('two authoritative refreshes of the same list never let the older total win',async t=>{
 const h=await fixture({captureAlternatives:true,holdRefresh:true});t.after(h.close);const refresh=h.alternativeCallbacks[0];
 h.lists[16].estimated_total='100.00';const older=refresh();await settle();
 h.lists[16].estimated_total='200.00';const newer=refresh();await settle();
 h.releaseRefresh(1);await newer;assert.equal(h.total(),'200.00');
 h.releaseRefresh(0);await older;assert.equal(h.total(),'200.00');
});

test('changing table filter during refresh keeps newer table and refreshes current detail',async t=>{
 const h=await fixture({holdRefresh:true});t.after(h.close);h.create();await settle();
 const field=h.w.document.querySelector('[data-shopping-list-status]');field.value='active';field.dispatchEvent(new h.w.Event('change',{bubbles:true}));await settle();
 assert.equal(h.w.document.querySelector('[data-shopping-list-show="16"]'),null);
 h.releaseRefresh();await settle();assert.equal(h.total(),'1800.01');assert.equal(h.w.document.querySelector('[data-shopping-list-show="16"]'),null);
});

test('numeric zero price remains visible and is sent as a real zero',async t=>{
 const h=await fixture({existing:true,zeroPrice:true});t.after(h.close);h.w.document.querySelector('[data-shopping-list-item-edit="21"]').click();
 assert.equal(h.form().elements.estimated_price.value,'0');h.form().requestSubmit();await settle();
 assert.equal(h.writes[0].body.estimated_price,0);assert.equal(h.total(),'0.00');
});

test('clearing an estimated price sends null, shows unknown item price and authoritative parent zero',async t=>{
 const h=await fixture({existing:true});t.after(h.close);h.w.document.querySelector('[data-shopping-list-item-edit="21"]').click();
 h.form().elements.estimated_price.value='';h.form().requestSubmit();await settle();
 assert.equal(h.writes[0].body.estimated_price,null);assert.equal(h.total(),'0.00');
 assert.equal(h.detail().querySelector('tbody tr td:nth-child(3)').textContent,'-');
});

for(const fieldName of ['unit_id','product_id'])test('changing '+fieldName+' invalidates the old prefilled estimate',async t=>{
 const h=await fixture({existing:true,mapped:true});t.after(h.close);h.w.document.querySelector('[data-shopping-list-item-edit="21"]').click();
 const field=h.form().elements[fieldName];field.value=fieldName==='unit_id'?'1':'113';field.dispatchEvent(new h.w.Event('change',{bubbles:true}));
 assert.equal(h.form().elements.estimated_price.value,'');h.form().requestSubmit();await settle();
 assert.equal(h.writes[0].body.estimated_price,null);assert.equal(h.total(),'0.00');
});

test('a newly entered estimate after a unit change is deliberately retained',async t=>{
 const h=await fixture({existing:true});t.after(h.close);h.w.document.querySelector('[data-shopping-list-item-edit="21"]').click();
 const unit=h.form().elements.unit_id;unit.value='1';unit.dispatchEvent(new h.w.Event('change',{bubbles:true}));
 const price=h.form().elements.estimated_price;price.value='9.25';price.dispatchEvent(new h.w.Event('input',{bubbles:true}));h.form().requestSubmit();await settle();
 assert.equal(h.writes[0].body.estimated_price,9.25);assert.equal(h.total(),'9.25');
});

test('changed unit without a change event cannot silently submit a stale estimate',async t=>{
 const h=await fixture({existing:true});t.after(h.close);h.w.document.querySelector('[data-shopping-list-item-edit="21"]').click();
 h.form().elements.unit_id.value='1';h.form().requestSubmit();await settle();assert.equal(h.writes[0].body.estimated_price,null);
});
