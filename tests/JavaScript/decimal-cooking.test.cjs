'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const test=require('node:test');
const {JSDOM}=require(require.resolve('jsdom',{paths:[path.join(__dirname,'../../mobile')]}));
const read=f=>fs.readFileSync(path.join(__dirname,'../..',f),'utf8');
const flush=async()=>{for(let i=0;i<6;i++)await new Promise(r=>setImmediate(r));};
function deferred(){let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return{promise,resolve,reject};}
function environment(markup){
 const dom=new JSDOM(markup,{url:'https://qa.invalid/web/planning',runScripts:'outside-only'});
 const w=dom.window,disposers=[],mounts={};let nativeCalls=0;
 w.HTMLElement.prototype.scrollIntoView=function(){};
 w.HTMLDialogElement.prototype.showModal=function(){this.setAttribute('open','');};
 w.HTMLDialogElement.prototype.close=function(){this.removeAttribute('open');this.dispatchEvent(new w.Event('close'));};
 w.CCPage={register:(name,fn)=>{mounts[name]=fn;},onDispose:fn=>disposers.push(fn),listen:(target,type,fn)=>target.addEventListener(type,fn)};
 w.confirm=()=>{nativeCalls++;return true;};w.prompt=()=>{nativeCalls++;return'1.5';};
 return{dom,w,mounts,disposers,get nativeCalls(){return nativeCalls;},dispose(){disposers.forEach(f=>f());},close(){dom.window.close();}};
}
async function recipe(){
 const h=environment('<main><div id="recipe"></div></main>'),posts=[],pending=[];
 h.w.CCApi={request:(url,options={})=>{
  if(options.method==='POST'&&url.endsWith('/cook')){const d=deferred();posts.push({url,body:JSON.parse(JSON.stringify(options.body))});pending.push(d);return d.promise;}
  if(url==='/api/v1/family-groups?per_page=20')return Promise.resolve({data:[{id:8,name:'Hogar QA'}]});
  throw Error('Unexpected request '+url);
 }};
 h.w.eval(read('public/js/recipe-favorites-actions.js'));
 h.w.RecipeFavoritesActions.mount(h.w.document.querySelector('#recipe'),6,1.5);
 h.w.document.querySelector('[data-cook-toggle]').click();await flush();
 return Object.assign(h,{posts,pending,input:()=>h.w.document.querySelector('[data-cook-servings]'),submit:()=>h.w.document.querySelector('[data-cook-submit]').click()});
}
async function planning(options={}){
 const template=new JSDOM(read('resources/views/web/user-screen.blade.php'));
 const markup=template.window.document.querySelector('[data-user-meal-plans]').outerHTML;template.window.close();
 const h=environment(markup),posts=[],pending=[],reads=[];
 const plan={id:8,family_group_id:8,period_type:'daily',start_date:'2026-09-25',end_date:'2026-09-25',status:'draft',items:[{id:9,recipe_id:6,recipe:{id:6,name:'Arroz QA',servings:1},date:'2026-09-25',meal_type_id:1,meal_type:{id:1,name:'Almuerzo'},status:'planned',servings_total:1.5}]};
 if(options.item)Object.assign(plan.items[0],options.item);
 const clone=v=>JSON.parse(JSON.stringify(v));
 h.w.CCApi={request:(url,options={})=>{
  if(options.method==='POST'&&url==='/api/v1/family-groups/8/meal-plans/8/items/9/mark-cooked'){
   const d=deferred();posts.push({url,body:clone(options.body)});pending.push(d);
   return d.promise.then(()=>{plan.items[0].status='cooked';return{data:clone(plan.items[0])};});
  }
  if(options.method)throw Error('Unexpected write '+url);
  reads.push(url);
  if(url==='/api/v1/family-groups')return Promise.resolve({data:[{id:8,name:'Hogar QA'}]});
  if(url==='/api/v1/meal-types')return Promise.resolve({data:[{id:1,name:'Almuerzo'}]});
  if(url==='/api/v1/recipes?per_page=100')return Promise.resolve({data:[{id:6,name:'Arroz QA',servings:1}]});
  if(url.includes('/meal-plans?'))return Promise.resolve({data:[clone(plan)],meta:{current_page:1,last_page:1,total:1}});
  if(url==='/api/v1/family-groups/8/meal-plans/8')return Promise.resolve({data:clone(plan)});
  if(url.endsWith('/meal-plan-preferences'))return Promise.resolve({data:{respect_budget:false}});
  if(url.endsWith('/members')||url.endsWith('/incompatibilities'))return Promise.resolve({data:[]});
  throw Error('Unexpected read '+url);
 }};
 h.w.eval(read('public/js/panel-ui.js'));h.w.eval(read('public/js/user-meal-plans.js'));
 h.mounts['panel-ui']();h.mounts['user-meal-plans']();await flush();
 h.w.document.querySelector('[data-meal-plan-show="8"]').click();await flush();
 const open=()=>h.w.document.querySelector('[data-meal-plan-item-cooked="9"]')?.click();
 const dialog=()=>h.w.document.querySelector('[data-meal-plan-cook-dialog]');
 const input=()=>h.w.document.querySelector('[data-meal-plan-cook-servings]');
 const submit=()=>h.w.document.querySelector('[data-meal-plan-cook-form]').requestSubmit();
 return Object.assign(h,{posts,pending,reads,plan,open,dialog,input,submit});
}
test('recipe cooking sends1.5 without truncation and prevents double submission',async t=>{
 const h=await recipe();t.after(()=>h.close());
 h.input().value='1.5';h.submit();h.submit();
 assert.equal(h.posts.length,1);assert.equal(h.posts[0].body.servings,1.5);
 assert.ok(h.posts[0].body.idempotency_key);
 assert.equal(h.w.document.querySelector('[data-cook-submit]').disabled,true);
 assert.equal(h.w.document.querySelector('[data-cook-cancel]').disabled,true);
 h.w.document.querySelector('[data-cook-toggle]').click();assert.ok(h.input());
 h.pending[0].resolve({});await flush();assert.equal(h.input(),null);
});
test('recipe comma1,5 becomes JSON1.5; zero/excess precision/out-of-range rejected',async t=>{
 const h=await recipe();t.after(()=>h.close());
 for(const raw of ['0','-1','0.001','100.01','1,2,3','1x','1e2']){
  h.input().value=raw;h.submit();assert.equal(h.posts.length,0,raw);
 }
 h.input().value='1,5';h.submit();assert.equal(h.posts.length,1);assert.equal(h.posts[0].body.servings,1.5);
});
test('recipe server error preserves decimal and ambiguous retry keeps idempotency key',async t=>{
 const h=await recipe();t.after(()=>h.close());h.input().value='1,5';h.submit();
 h.pending[0].reject({status:503,payload:{error:{message:'No se pudo confirmar'}}});await flush();
 assert.equal(h.input().value.replace(',','.'),'1.5');assert.match(h.w.document.querySelector('[data-cook-msg]').textContent,/confirmar/);
 h.submit();assert.equal(h.posts.length,2);assert.equal(h.posts[1].body.idempotency_key,h.posts[0].body.idempotency_key);
});
test('recipe late response after disposal never restores removed screen',async t=>{
 const h=await recipe();t.after(()=>h.close());h.input().value='1.5';h.submit();
 const old=h.w.document.querySelector('#recipe');h.dispose();old.remove();h.pending[0].resolve({});await flush();
 assert.equal(h.w.document.querySelector('[data-cook-servings]'),null);assert.equal(h.posts.length,1);
 assert.equal(old.textContent.includes('Receta registrada'),false);
});
test('plan opens accessible modal with decimal portions and no native confirm/prompt',async t=>{
 const h=await planning();t.after(()=>h.close());h.open();
 assert.equal(h.nativeCalls,0);assert.equal(h.posts.length,0);assert.equal(h.dialog().open,true);
 assert.ok(h.w.document.getElementById(h.dialog().getAttribute('aria-labelledby')));
 assert.equal(h.input().value.replace(',','.'),'1.5');assert.equal(h.w.document.activeElement,h.input());
 h.w.document.querySelector('[data-meal-plan-cook-cancel]').click();assert.equal(h.dialog().open,false);
 assert.equal(h.w.document.activeElement.getAttribute('data-meal-plan-item-cooked'),'9');
});
test('plan accepts comma1,5, rejects invalid portions and blocks pending double click/Escape',async t=>{
 const h=await planning();t.after(()=>h.close());h.open();
 for(const raw of ['0','1000','0.001','1,2,3','1x','1e2','']){
  h.input().value=raw;h.submit();assert.equal(h.posts.length,0,raw);
 }
 h.input().value='1,5';h.submit();h.submit();
 assert.equal(h.posts.length,1);assert.equal(h.posts[0].body.servings,1.5);
 assert.equal(h.w.document.querySelector('[data-meal-plan-cook-submit]').disabled,true);
 const cancel=new h.w.Event('cancel',{cancelable:true});h.dialog().dispatchEvent(cancel);assert.equal(cancel.defaultPrevented,true);
 h.pending[0].resolve();await flush();assert.equal(h.dialog().open,false);
 assert.equal(h.w.document.querySelector('[data-meal-plan-item-cooked="9"]'),null);
 assert.match(h.w.document.querySelector('[data-meal-plan-detail]').textContent,/Cocinado/);
});
test('plan422 keeps modal/value and allows deliberate correction, not automatic POST',async t=>{
 const h=await planning();t.after(()=>h.close());h.open();h.input().value='1.5';h.submit();
 h.pending[0].reject({status:422,payload:{error:{message:'Porciones no válidas',field_errors:{servings:['Revisá las porciones']}}}});await flush();
 assert.equal(h.dialog().open,true);assert.equal(h.posts.length,1);assert.equal(h.input().value,'1.5');
 assert.match(h.w.document.querySelector('[data-meal-plan-cook-error]').textContent,/porciones/i);
 assert.equal(h.w.document.activeElement,h.input());
 h.input().value='2,25';h.submit();assert.equal(h.posts.length,2);assert.equal(h.posts[1].body.servings,2.25);
});
test('plan ambiguous network error cannot silently resend cooking',async t=>{
 const h=await planning();t.after(()=>h.close());h.open();h.input().value='1.5';h.submit();
 h.pending[0].reject(new Error('Network failed'));await flush();h.submit();
 assert.equal(h.posts.length,1);assert.equal(h.w.document.querySelector('[data-meal-plan-cook-submit]').disabled,true);
 assert.match(h.w.document.querySelector('[data-meal-plan-cook-error]').textContent,/recarg/i);
 h.w.document.querySelector('[data-meal-plan-cook-cancel]').click();h.open();assert.equal(h.posts.length,1);
});
test('plan disposing while pending closes modal and late response does not reopen or refresh next screen',async t=>{
 const h=await planning();t.after(()=>h.close());h.open();h.submit();
 const count=h.reads.length;h.dispose();h.w.document.querySelector('[data-user-meal-plans]').remove();
 h.pending[0].resolve();await flush();assert.equal(h.posts.length,1);assert.equal(h.reads.length,count);assert.equal(h.w.document.querySelector('dialog[open]'),null);
});


for (const status of [409,500,503]) {
 test('plan HTTP'+status+' blocks further cook POST until page is refreshed',async t=>{
  const h=await planning();t.after(()=>h.close());h.open();h.input().value='1,5';h.submit();
  h.pending[0].reject({status,payload:{error:{message:'Resultado no confirmado'}}});await flush();
  assert.equal(h.dialog().open,true);assert.equal(h.input().value,'1,5');
  assert.equal(h.w.document.querySelector('[data-meal-plan-cook-submit]').disabled,true);
  h.submit();assert.equal(h.posts.length,1);
  h.w.document.querySelector('[data-meal-plan-cook-cancel]').click();assert.equal(h.dialog().open,false);
  h.open();assert.equal(h.dialog().open,false);assert.equal(h.posts.length,1);
 });
}
test('plan idle Escape can close and returns focus to its trigger',async t=>{
 const h=await planning();t.after(()=>h.close());h.open();
 const escape=new h.w.Event('cancel',{cancelable:true});h.dialog().dispatchEvent(escape);
 assert.equal(escape.defaultPrevented,false);
 h.dialog().close(); // Native dialog performs this default action after an uncancelled Escape.
 assert.equal(h.w.document.activeElement.getAttribute('data-meal-plan-item-cooked'),'9');
 assert.equal(h.posts.length,0);
});
test('recipe and plan accept the minimum0.01 and their original maximums',async t=>{
 for(const value of ['0,01','100']){
  const h=await recipe();t.after(()=>h.close());h.input().value=value;h.submit();
  assert.equal(h.posts.length,1);assert.equal(h.posts[0].body.servings,Number(value.replace(',','.')));
 }
 for(const value of ['0,01','999']){
  const h=await planning();t.after(()=>h.close());h.open();h.input().value=value;h.submit();
  assert.equal(h.posts.length,1);assert.equal(h.posts[0].body.servings,Number(value.replace(',','.')));
 }
});


test('plan without total keeps assigned portions on the server instead of recipe base4',async t=>{
 const h=await planning({item:{servings_total:null,recipe:{id:6,name:'Arroz QA',servings:4}}});t.after(()=>h.close());h.open();
 const usePlan=h.w.document.querySelector('[data-meal-plan-cook-use-plan]');
 assert.ok(usePlan);assert.equal(usePlan.checked,true);assert.equal(usePlan.hidden,false);
 assert.equal(h.input().value,'');assert.equal(h.input().disabled,true);assert.equal(h.w.document.activeElement,usePlan);
 h.submit();assert.equal(h.posts.length,1);assert.deepEqual(h.posts[0].body,{});
 // Actual portions are server-owned and absent from this resource: 0.5+0.5 must stay1, never base4.
 const serverAssignedPortions=[0.5,0.5];
 const received=h.posts[0].body.servings ?? serverAssignedPortions.reduce((a,b)=>a+b,0);
 assert.equal(received,1);
});

test('plan without total allows explicit decimal override and restores the plan option',async t=>{
 const h=await planning({item:{servings_total:null,recipe:{id:6,name:'Arroz QA',servings:4}}});t.after(()=>h.close());h.open();
 const usePlan=h.w.document.querySelector('[data-meal-plan-cook-use-plan]');assert.ok(usePlan);
 usePlan.click();assert.equal(h.input().disabled,false);assert.equal(h.w.document.activeElement,h.input());
 h.input().value='0,5';h.submit();assert.equal(h.posts.length,1);assert.equal(h.posts[0].body.servings,0.5);
 h.pending[0].reject({status:422,payload:{error:{field_errors:{servings:['Corregí porciones']}}}});await flush();
 usePlan.click();assert.equal(h.input().disabled,true);h.submit();assert.deepEqual(h.posts[1].body,{});
});

test('recipe 503 then409 in progress then retry retains one idempotency key',async t=>{
 const h=await recipe();t.after(()=>h.close());h.submit();
 h.pending[0].reject({status:503,payload:{error:{message:'No se pudo confirmar'}}});await flush();h.submit();
 h.pending[1].reject({status:409,payload:{error:{code:'RECIPE_COOK_IN_PROGRESS',message:'En curso'}}});await flush();h.submit();
 assert.equal(h.posts.length,3);assert.equal(new Set(h.posts.map(x=>x.body.idempotency_key)).size,1);
 assert.deepEqual(h.posts[2].body,h.posts[0].body);
});

for(const closeSelector of ['[data-cook-cancel]','[data-cook-toggle]']){
 test('recipe ambiguous result survives close/reopen via '+closeSelector,async t=>{
  const h=await recipe();t.after(()=>h.close());h.input().value='1,5';h.submit();
  h.pending[0].reject(new Error('Network failed'));await flush();
  h.w.document.querySelector(closeSelector).click();assert.equal(h.input(),null);
  h.w.document.querySelector('[data-cook-toggle]').click();
  assert.equal(h.input().disabled,true);assert.match(h.w.document.querySelector('[data-cook-msg]').textContent,/confirmar|resultado/i);
  h.submit();assert.equal(h.posts.length,2);assert.deepEqual(h.posts[1].body,h.posts[0].body);
 });
}

test('recipe ambiguous payload cannot be changed into a new cooking operation',async t=>{
 const h=await recipe();t.after(()=>h.close());h.submit();
 h.pending[0].reject({status:503});await flush();
 assert.equal(h.input().disabled,true);assert.equal(h.w.document.querySelector('[data-cook-group]').disabled,true);
 assert.equal(h.w.document.querySelector('[data-cook-deduct]').disabled,true);
 h.input().value='2';h.submit();assert.equal(h.posts.length,1);
 assert.match(h.w.document.querySelector('[data-cook-msg]').textContent,/recarg|misma/i);
});

test('recipe initial422 permits a corrected new payload after definite validation failure',async t=>{
 const h=await recipe();t.after(()=>h.close());h.submit();
 h.pending[0].reject({status:422,payload:{error:{field_errors:{servings:['Corregí porciones']}}}});await flush();
 assert.equal(h.input().disabled,false);h.input().value='2,25';h.submit();
 assert.equal(h.posts.length,2);assert.equal(h.posts[1].body.servings,2.25);
 assert.notEqual(h.posts[1].body.idempotency_key,h.posts[0].body.idempotency_key);
});
