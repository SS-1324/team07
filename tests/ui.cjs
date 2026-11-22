'use strict';
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
let JSDOM;try{({JSDOM}=require('jsdom'));}catch{({JSDOM}=require('../docs/frontend-preview/node_modules/jsdom'));}
const output=process.env.RENDER_DIR||path.join(__dirname,'.output');
const script=fs.readFileSync(path.join(__dirname,'../assets/js/live.js'),'utf8');
let count=0;
for(const file of fs.readdirSync(output).filter(x=>x.endsWith('.html'))){
 const dom=new JSDOM(fs.readFileSync(path.join(output,file),'utf8'),{url:'http://localhost/team07/',runScripts:'outside-only'}),w=dom.window,d=w.document;
 const errors=[];w.addEventListener('error',e=>errors.push(e.message));w.ResizeObserver=class{observe(){}};w.alert=()=>{};w.confirm=()=>false;w.eval(script);
 assert.equal(d.querySelectorAll('#diamondata-app').length,1,file);assert.equal(d.querySelectorAll('h1').length,1,file);assert(!d.querySelector('pre'),file);
 for(const form of d.querySelectorAll('form[method=post]')){assert(form.querySelector('[name=csrf]')?.value,file+' csrf');assert(form.action.startsWith('http://localhost/team07/actions/'),file+' action');}
 const menu=d.querySelector('#dd-menu');menu.click();assert.equal(menu.getAttribute('aria-expanded'),'true');
 if(file==='teams-kbo.html')assert.equal(d.querySelectorAll('tbody tr').length,10);
 if(file==='teams-mlb.html'){assert.equal(d.querySelectorAll('tbody tr').length,30);assert.equal(d.querySelectorAll('tbody tr:not([hidden])').length,20);d.querySelector('[data-pager] button:last-child').click();assert.equal(d.querySelectorAll('tbody tr:not([hidden])').length,10);}
 if(file==='empty.html'||file==='ranking-empty.html')assert.equal(d.querySelectorAll('tbody tr').length,0);
 if(file==='ranking-kbo.html'||file==='ranking-era.html')assert(d.querySelectorAll('tbody tr').length>0);
 if(file==='escape.html'){assert.equal(d.querySelector('[name=name]').value,'"><script>alert(1)</script>');assert.equal(d.querySelectorAll('script:not([src])').length,0);}
 if(file==='watchlist.html'){const form=d.querySelector('#compare-form');let event=new w.Event('submit',{cancelable:true});form.dispatchEvent(event);assert(event.defaultPrevented);d.querySelectorAll('[data-pick]').forEach(x=>x.checked=true);event=new w.Event('submit',{cancelable:true});form.dispatchEvent(event);assert(!event.defaultPrevented);assert.equal(form.querySelectorAll('[name="ids[]"]').length,2);}
 if(file==='add.html'||file==='edit.html'){const form=d.querySelector('#contract-form');assert(form);form.elements.start_year.value='2025';form.elements.end_year.value='2020';form.dispatchEvent(new w.Event('input'));assert(!form.elements.end_year.checkValidity());form.elements.end_year.value='2045';form.dispatchEvent(new w.Event('input'));assert(!form.elements.end_year.checkValidity());form.elements.end_year.value='2026';form.elements.total_value.value='100';form.elements.guaranteed_value.value='101';form.dispatchEvent(new w.Event('input'));assert(!form.elements.guaranteed_value.checkValidity());}
 if(file==='contracts.html'){const form=d.querySelector('[data-confirm]');assert(form);const e=new w.Event('submit',{cancelable:true});form.dispatchEvent(e);assert(e.defaultPrevented);}
 if(file==='aggregate.html'){const checks=[...d.querySelectorAll('[name="group[]"]')];checks.forEach(c=>c.checked=false);checks.slice(0,4).forEach(c=>{c.checked=true;c.dispatchEvent(new w.Event('change'));});assert.equal(d.querySelectorAll('[name="group[]"]:checked').length,3);}
 if(['detail.html','detail-kbo.html','window.html','window-kbo.html'].includes(file))assert(d.querySelector('[data-chart] svg'),file);
 assert.deepEqual(errors,[],file);dom.window.close();count++;
}
console.log('PASS:',count,'rendered pages; menu, pagination, CSRF forms, compare, validation, XSS escaping, chart and empty states');
