const fs=require('fs'),path=require('path');
(async()=>{
 const root=__dirname,t=await(await fetch('http://127.0.0.1:9223/json/new',{method:'PUT'})).json();const w=new WebSocket(t.webSocketDebuggerUrl);await new Promise((r,j)=>{w.onopen=r;w.onerror=j});let id=0;const requests=new Map();
 w.onmessage=e=>{const m=JSON.parse(e.data);if(m.id&&requests.has(m.id)){const p=requests.get(m.id);requests.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result)}};
 const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;requests.set(n,{resolve,reject});w.send(JSON.stringify({id:n,method,params}))});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value};
 await send('Page.enable');await send('Runtime.enable');
 const errors=[];w.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text)});
 for(const page of ['pmo-projects-create','pmo-project-create-restored'])for(const width of [1440,1280,390]){
  errors.length=0;await send('Emulation.setDeviceMetricsOverride',{width,height:width===390?844:1100,deviceScaleFactor:1,mobile:width===390});await send('Page.navigate',{url:'file:///'+path.join(root,page+'.html').replaceAll('\\','/')});
  for(let n=0;n<50;n++){await new Promise(r=>setTimeout(r,60));if(await evaluate('document.readyState==="complete"'))break;}
  const failures=await evaluate(`(async()=>{
   const failures=[],pause=ms=>new Promise(r=>setTimeout(r,ms));
   window.alert=message=>{failures.push('browser alert: '+message)};
   window.__imports=[];
   const people={newpm:{id:'99000000-0000-4000-8000-000000000001',displayName:'New Azure Manager',email:'newpm@example.invalid',systemId:910001},newteam:{id:'99000000-0000-4000-8000-000000000002',displayName:'New Azure Team',email:'newteam@example.invalid',systemId:910002},newgov:{id:'99000000-0000-4000-8000-000000000003',displayName:'New Azure Sponsor',email:'newgov@example.invalid',systemId:910003}};
   window.fetch=async(url,options={})=>{const parsed=new URL(url,'http://127.0.0.1:8000');if(options.method==='POST'){const body=JSON.parse(options.body);const remote=Object.values(people).find(p=>p.id===body.azure_id);window.__imports.push(body.azure_id);return new Response(JSON.stringify({success:true,user:{id:remote.systemId,name:remote.displayName,email:remote.email,system_role:'user',account_status:'active'}}),{status:200})}return new Response(JSON.stringify({results:people[parsed.searchParams.get('q')]?[people[parsed.searchParams.get('q')]]:[]}),{status:200});};
   const form=document.getElementById('create-project-form');
   if(document.getElementById('quick-add-user-modal')||[...form.querySelectorAll('button')].some(b=>/manual/i.test(b.textContent)))failures.push('manual user creation still present');
   if(document.querySelectorAll('.pc-section').length!==3)failures.push('three clear sections');
   if(location.href.includes('restored')){
    if(!document.getElementById('pm-selected-name').textContent.trim())failures.push('manager restoration');
    if(!document.querySelector('#assigned-team-list [name$="[user_id]"]'))failures.push('team restoration');
    const expectedGov=[...document.querySelectorAll('.gov-hidden-user-id')].filter(input=>input.value).length;
    if(!document.getElementById('assigned-roles-counter').textContent.includes(String(expectedGov)))failures.push('role count');
   }
   const template=document.getElementById('project_template_id');document.querySelector('[data-mode="template"]').click();if(!template.required)failures.push('template required');
   if(template.options.length>1){template.selectedIndex=1;template.dispatchEvent(new Event('change',{bubbles:true}));if(!template.getBoundingClientRect().height)failures.push('template change needs extra click');if(!document.getElementById('ends_on').value)failures.push('template deadline');}
   document.querySelector('[data-mode="blank"]').click();if(template.required)failures.push('blank template requirement');
   // Existing people can be selected with the keyboard, without directory import.
   document.getElementById('btn-pm-change').click();await pause(70);const pm=document.getElementById('pm-search-input');pm.focus();pm.value='';pm.dispatchEvent(new Event('input',{bubbles:true}));await pause(30);pm.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true}));pm.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));if(!document.getElementById('pm_user_id').value)failures.push('keyboard selection');
   document.getElementById('btn-pm-change').click();await pause(70);
   async function importPerson(input,query,selector){input.focus();input.value=query;input.dispatchEvent(new Event('input',{bubbles:true}));await pause(380);const option=document.querySelector(selector);if(!option){failures.push('Azure result '+query);return;}option.click();await pause(50);}
   await importPerson(pm,'newpm','[data-action="select-pm-azure"]');if(document.getElementById('pm_user_id').value!=='910001')failures.push('Azure PM assignment');
   await importPerson(document.getElementById('team-search-input'),'newteam','[data-action="add-team-azure"]');if(!document.querySelector('#assigned-team-list input[value="910002"]'))failures.push('Azure team assignment');
   document.getElementById('governance-roles-section').open=true;const gov=document.querySelector('.gov-search-input');if(gov){gov.closest('.gov-role-picker').querySelector('.btn-clear-gov').click();await importPerson(gov,'newgov','[data-action="gov-azure"]');if(gov.closest('.gov-role-picker').querySelector('.gov-hidden-user-id').value!=='910003')failures.push('Azure additional role assignment');}
   if(window.__imports.length!==(gov?3:2))failures.push('Azure import requests');
   const data=new FormData(form);if(data.get('pm_user_id')!=='910001'||![...data.values()].includes('910002'))failures.push('people included in submit');
   if(document.documentElement.scrollWidth>innerWidth+2)failures.push('page overflow');
   return failures;
  })()`);
  failures.push(...errors);console.log((failures.length?'FAIL':'PASS')+' create workflow '+page+' '+width+(failures.length?': '+failures.join('; '):''));if(failures.length)process.exitCode=1;
 }
 await send('Page.close');w.close();
})().catch(error=>{console.error(error);process.exit(1)});
