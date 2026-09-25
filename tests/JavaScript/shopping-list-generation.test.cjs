'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {JSDOM}=require(require.resolve('jsdom',{paths:[path.join(__dirname,'../../mobile')]}));
const read=p=>fs.readFileSync(path.join(__dirname,'../..',p),'utf8');
const clone=x=>JSON.parse(JSON.stringify(x));
const settle=async()=>{for(let i=0;i<8;i++)await new Promise(r=>setImmediate(r));};
function deferred(){let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return{promise,resolve,reject};}
async function fixture(screen,options={}){
 const parsed=new JSDOM(read('resources/views/web/user-screen.blade.php'));const selector=screen==='lists'?'[data-user-shopping-lists]':'[data-user-meal-plans]';const markup=parsed.window.document.querySelector(selector).outerHTML;parsed.window.close();
 const dom=new JSDOM(markup,{url:'https://qa.invalid/web/'+(screen==='lists'?'shopping-list':'planning'),runScripts:'outside-only'}),w=dom.window,calls=[],posts=[],disposers=[];let mount,generated=false,preview;
 const oldList={id:16,family_group_id:8,source_type:'manual',status:'draft',estimated_total:'50.00',items:[]};
 const otherList={...oldList,id:17,estimated_total:'70.00'};
 const result={id:30,family_group_id:8,source_type:'meal_plan',status:'draft',estimated_total:'2450.01',items:[]};
 const plan=id=>({id,family_group_id:8,period_type:'daily',start_date:'2026-09-25',end_date:'2026-09-25',status:'draft',items:[]});
 w.HTMLElement.prototype.scrollIntoView=function(){};w.confirm=()=>true;
 w.CCPage={register:(n,f)=>{mount=f;},onDispose:f=>disposers.push(f)};
 w.CCApi={request:(url,config={})=>{
  const p=new URL(url,'https://qa.invalid').pathname;calls.push({url,method:config.method||'GET',body:config.body?clone(config.body):null});
  if(config.method){const d=deferred();posts.push({url,body:config.body?clone(config.body):null,resolve:()=>{generated=true;d.resolve({data:clone(result)});},reject:d.reject});return d.promise;}
  if(p==='/api/v1/family-groups')return Promise.resolve({data:[{id:8,name:'Hogar QA'},{id:9,name:'Otro hogar QA'}]});
  if(p.endsWith('/shopping-list-preview')){preview=deferred();return preview.promise;}
  if(p.endsWith('/shopping-lists/30'))return options.refreshError?Promise.reject(new Error('Detalle no disponible')):Promise.resolve({data:clone(result)});
  if(/\/shopping-lists\/(16|17)$/.test(p))return Promise.resolve({data:clone(p.endsWith('/16')?oldList:otherList)});
  if(p.endsWith('/shopping-lists')){
   if(generated&&options.refreshError)return Promise.reject(new Error('Tabla no disponible'));
   return Promise.resolve({data:p.includes('/9/')?[]:clone(generated?[oldList,otherList,result]:[oldList,otherList]),meta:{total:generated?3:2,current_page:1,last_page:1}});
  }
  if(/\/meal-plans\/(8|9)$/.test(p))return Promise.resolve({data:plan(Number(p.split('/').pop()))});
  if(p.endsWith('/meal-plans'))return Promise.resolve({data:[plan(8),plan(9)],meta:{total:2,current_page:1,last_page:1}});
  if(p.endsWith('/meal-plan-preferences'))return Promise.resolve({data:{respect_budget:false}});
  return Promise.resolve({data:[]});
 }};
 w.eval(read(screen==='lists'?'public/js/user-shopping-lists.js':'public/js/user-meal-plans.js'));mount();await settle();
 const listClick=id=>w.document.querySelector('[data-shopping-list-show="'+id+'"]').click();
 const planClick=id=>w.document.querySelector('[data-meal-plan-show="'+id+'"]').click();
 if(screen==='lists')listClick(16);else planClick(8);await settle();
 function generate(kind){
  if(screen==='plans'){w.document.querySelector('[data-meal-plan-shopping-generate]').click();return;}
  const f=w.document.querySelector(kind==='menu'?'[data-shopping-list-generate-plan-form]':'[data-shopping-list-generate-history-form]');
  if(kind==='menu')f.elements.meal_plan_id.value='8';else{f.elements.date_from.value='2026-09-01';f.elements.date_to.value='2026-09-25';}
  f.requestSubmit();
 }
 function groupChange(){const el=w.document.querySelector(screen==='lists'?'[data-shopping-list-group]':'[data-meal-plan-group]');el.value='9';el.dispatchEvent(new w.Event('change',{bubbles:true}));}
 return{w,calls,posts,generate,listClick,planClick,groupChange,dispose:()=>disposers.forEach(f=>f()),close:()=>dom.window.close(),releasePreview:()=>preview.resolve({data:[{ingredient:{id:4,name:'Old preview'},missing_quantity:100,required_quantity:100,unit:{id:1,code:'g'},recipe_sources:[]}]}),detail:()=>w.document.querySelector(screen==='lists'?'[data-shopping-list-detail]':'[data-meal-plan-shopping-preview-panel]'),message:()=>w.document.querySelector(screen==='lists'?'[data-shopping-lists-message]':'[data-meal-plans-message]')};
}
for(const kind of ['menu','history']){
 test(kind+' generation submits once and shows full parent total in detail/table',async t=>{
  const h=await fixture('lists');t.after(h.close);h.generate(kind);h.generate(kind);assert.equal(h.posts.length,1);
  assert.ok(h.posts[0].url.includes('/family-groups/8/'));
  h.posts[0].resolve();await settle();assert.match(h.detail().textContent,/2450.01/);assert.match(h.w.document.querySelector('[data-shopping-list-body]').textContent,/2450.01/);
 });
 for(const change of ['household','list','dispose'])test(kind+' late generation cannot replace '+change,async t=>{
  const h=await fixture('lists');t.after(h.close);h.generate(kind);
  if(change==='household')h.groupChange();else if(change==='list')h.listClick(17);else h.dispose();await settle();
  const reads=h.calls.length;h.posts[0].resolve();await settle();
  assert.doesNotMatch(h.detail().textContent,/2450.01|#30/);assert.equal(h.calls.length,reads,'no follow-up reads on stale context');
  if(change==='list')assert.match(h.detail().textContent,/70.00/);
 });
 test(kind+' successful generation with failed refresh is distinct from mutation failure',async t=>{
  const h=await fixture('lists',{refreshError:true});t.after(h.close);h.generate(kind);h.posts[0].resolve();await settle();
  assert.equal(h.posts.length,1);assert.match(h.message().textContent,/gener(?:ad|ó)/i);assert.match(h.message().textContent,/actualiz|abrir|recarg/i);
 });
 test(kind+'422 allows correction without automatic retry',async t=>{
  const h=await fixture('lists');t.after(h.close);h.generate(kind);h.posts[0].reject({status:422,payload:{error:{message:'Datos inválidos'}}});await settle();assert.equal(h.posts.length,1);assert.match(h.message().textContent,/Datos inválidos/);
  h.generate(kind);assert.equal(h.posts.length,2);
 });
}
for(const change of ['household','plan','dispose'])test('planning late generated list cannot replace '+change,async t=>{
 const h=await fixture('plans');t.after(h.close);h.generate();
 if(change==='household')h.groupChange();else if(change==='plan')h.planClick(9);else h.dispose();await settle();
 h.posts[0].resolve();await settle();assert.doesNotMatch(h.detail().textContent,/Lista generada|#30/);
});
test('planning delayed preview cannot replace a newer generated list',async t=>{
 const h=await fixture('plans');t.after(h.close);h.w.document.querySelector('[data-meal-plan-shopping-preview]').click();h.generate();
 h.posts[0].resolve();await settle();assert.match(h.detail().textContent,/#30/);h.releasePreview();await settle();assert.match(h.detail().textContent,/#30/);assert.doesNotMatch(h.detail().textContent,/Old preview/);
});
test('planning generation pending ignores duplicate events',async t=>{
 const h=await fixture('plans');t.after(h.close);const b=h.w.document.querySelector('[data-meal-plan-shopping-generate]');
 b.dispatchEvent(new h.w.Event('click',{bubbles:true}));b.dispatchEvent(new h.w.Event('click',{bubbles:true}));assert.equal(h.posts.length,1);
 h.posts[0].resolve();await settle();assert.equal(b.disabled,false);
});


test('pending menu generation blocks history generation and manual item mutation',async t=>{
 const h=await fixture('lists');t.after(h.close);h.generate('menu');h.generate('history');
 const form=h.w.document.querySelector('[data-shopping-list-item-form]');form.elements.free_text_name.value='QA';form.elements.quantity.value='1';form.requestSubmit();
 assert.equal(h.posts.length,1);assert.deepEqual(h.posts[0].body,{meal_plan_id:8});
 h.posts[0].resolve();await settle();assert.equal(form.querySelector('button[type="submit"]').disabled,false);
});

test('pending manual item mutation prevents either generator from sending a POST',async t=>{
 const h=await fixture('lists');t.after(h.close);const form=h.w.document.querySelector('[data-shopping-list-item-form]');
 form.elements.free_text_name.value='QA';form.elements.quantity.value='1';form.requestSubmit();h.generate('menu');h.generate('history');
 assert.equal(h.posts.length,1);assert.ok(h.posts[0].url.endsWith('/shopping-lists/16/items'));
});
