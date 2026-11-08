const fs=require('fs'),path=require('path');
(async()=>{
 const root=__dirname,repo=path.resolve(root,'../../..'),qa=JSON.parse(fs.readFileSync(path.join(root,'draft-template-test-data.json'),'utf8'));
 const target=await(await fetch('http://127.0.0.1:9223/json/new',{method:'PUT'})).json(),ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject});let id=0;const pending=new Map(),errors=[],posts=[];let scenario='existing';
 const send=(method,params={})=>new Promise((resolve,reject)=>{const key=++id;pending.set(key,{resolve,reject});ws.send(JSON.stringify({id:key,method,params}))});
 const fulfill=(requestId,body,type='text/html',status=200)=>send('Fetch.fulfillRequest',{requestId,responseCode:status,responseHeaders:[{name:'Content-Type',value:type}],body:Buffer.from(body).toString('base64')});
 async function intercepted(event){const url=new URL(event.request.url),id=event.requestId;try{
  if(event.resourceType==='Document'){
   let file;
   if(url.pathname==='/projects/create')file=url.searchParams.has('template_id')?(scenario==='draft'?'pmo-draft-template-return.html':'pmo-new-template-return.html'):(scenario==='draft'?'pmo-draft-create.html':'pmo-projects-create.html');
   else if(url.pathname==='/templates/create')file=scenario==='draft'?'pmo-template-create-selection.html':'pmo-new-template-create-selection.html';
   else if(url.pathname==='/templates')file=scenario==='draft'?'pmo-template-selection.html':'pmo-new-template-selection.html';
   else throw Error('Unexpected document '+url.pathname);
   let html=fs.readFileSync(path.join(root,file),'utf8');html=html.replace(/file:\/\/\/[^"<>]*\/public\/(css|js)\//g,'http://127.0.0.1:8000/$1/');
   const flow=url.searchParams.get('project_flow')||'';html=html.replaceAll(qa.flow,flow).replace(/data-project-flow="[^"]*"/,'data-project-flow="'+flow+'"');await fulfill(id,html);return;
  }
  if(event.request.method==='POST'){
   const body=JSON.parse(event.request.postData||'{}');posts.push({path:url.pathname,body});if(url.pathname!=='/templates')throw Error('Unexpected write '+url.pathname);
   await fulfill(id,JSON.stringify({success:true,template_id:qa.template,redirect:'/projects/create?template_id='+qa.template}),'application/json');return;
  }
  if(/^\/(css|js)\//.test(url.pathname)){
   const file=path.resolve(repo,'public',url.pathname.slice(1)),publicRoot=path.join(repo,'public')+path.sep;if(!file.startsWith(publicRoot))throw Error('Asset path outside public');await fulfill(id,fs.readFileSync(file),url.pathname.endsWith('.css')?'text/css':'application/javascript');return;
  }
  if(url.pathname.includes('/users/azure/')){await fulfill(id,JSON.stringify({results:[]}),'application/json');return;}
  await fulfill(id,'','text/plain',204);
 }catch(error){errors.push(error.message);await fulfill(id,error.message,'text/plain',500)}}
 ws.onmessage=event=>{const m=JSON.parse(event.data);if(m.id&&pending.has(m.id)){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result)}else if(m.method==='Fetch.requestPaused')intercepted(m.params).catch(e=>errors.push(e.message));else if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text)};
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value};
 const until=async expression=>{for(let n=0;n<100;n++){try{if(await evaluate(expression))return;}catch(error){}await new Promise(resolve=>setTimeout(resolve,50));}throw Error('Timed out: '+expression)};
 await send('Page.enable');await send('Runtime.enable');await send('Fetch.enable',{patterns:[{urlPattern:'http://127.0.0.1:8000/*'}]});
 for(const current of ['existing','new','draft'])for(const width of [1440,390]){
  scenario=current;errors.length=0;posts.length=0;
  await send('Emulation.setDeviceMetricsOverride',{width,height:width===390?844:1100,deviceScaleFactor:1,mobile:width===390});await send('Page.navigate',{url:'http://127.0.0.1:8000/projects/create'+(current==='draft'?'?draft='+qa.draft:'')});
  await until('document.readyState==="complete" && typeof window.addTeamMember==="function"');
  const state=await evaluate(`(async()=>{const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));const form=document.getElementById('create-project-form');form.elements.namedItem('name').value='Preserved ${current} project';form.elements.namedItem('description').value='Preserved scope';form.elements.namedItem('starts_on').value='2026-11-09';form.elements.namedItem('ends_on').value='2026-12-18';if(!form.elements.namedItem('pm_user_id').value){document.getElementById('pm-search-input').focus();document.getElementById('pm-search-input').dispatchEvent(new Event('input',{bubbles:true}));await pause(50);const option=document.querySelector('[data-action="select-pm-local"]');if(!option)throw Error('PM results missing; users='+window.LOCAL_USERS?.length);option.click();}let team=form.querySelector('#assigned-team-list input[name$="[user_id]"]');if(!team){document.getElementById('team-search-input').focus();document.getElementById('team-search-input').dispatchEvent(new Event('input',{bubbles:true}));await pause(50);const option=document.querySelector('[data-action="add-team-local"]');if(!option)throw Error('Team results missing; users='+window.LOCAL_USERS?.length);option.click();team=form.querySelector('#assigned-team-list input[name$="[user_id]"]');}const data=new FormData(form);return {pm:data.get('pm_user_id'),team:team?.value};})()`);
  if(current==='draft')await evaluate("document.getElementById('btn-change-template-redirect').click()");else await evaluate("document.querySelector('[data-mode=\"template\"]').click()");
  await until('location.pathname==="/templates" && document.readyState==="complete" && Boolean(document.querySelector(".btn-select-template"))');
  const context=await evaluate('Object.fromEntries(new URLSearchParams(location.search))');if(!context.project_flow||context.select_for_project!=='1')errors.push('selection context missing');if(current==='draft'&&context.draft!==String(qa.draft))errors.push('draft context missing');
  if(current==='new'){
   await evaluate("document.querySelector('.page-head a[href*=\"/templates/create\"]').click()");await until('location.pathname==="/templates/create" && document.readyState==="complete" && Boolean(window.SmartTaskPlan)');
   await evaluate("document.getElementById('create_template_name').value='New reusable plan';document.getElementById('create-template-form').requestSubmit()");await until('window.SmartTaskPlan.mode==="create" && window.SmartTaskPlan.dom.btnSave.getClientRects().length>0');await evaluate('window.SmartTaskPlan.dom.btnSave.click()');
  }else await evaluate(`document.querySelector('.btn-select-template[href*="template_id=${qa.template}"]').click()`);
  await until('location.pathname==="/projects/create" && location.search.includes("template_id=") && document.readyState==="complete" && typeof window.addTeamMember==="function"');
  const failures=await evaluate(`(()=>{const failures=[],form=document.getElementById('create-project-form'),data=new FormData(form);if(data.get('name')!=='Preserved ${current} project'||data.get('description')!=='Preserved scope'||data.get('ends_on')!=='2026-12-18')failures.push('project details lost');if(data.get('pm_user_id')!==${JSON.stringify(state.pm)}||![...data.values()].includes(${JSON.stringify(state.team)}))failures.push('people lost');if(data.get('project_template_id')!==${JSON.stringify(String(qa.template))})failures.push('selected template lost');if(${JSON.stringify(current)}==='draft'){if(!form.getAttribute('action').endsWith('/projects/${qa.draft}/draft'))failures.push('draft identity lost');if(document.getElementById('draft-plan-replacement')?.hidden)failures.push('replacement confirmation missing');}if(document.documentElement.scrollWidth>innerWidth+2)failures.push('page overflow');return failures})()`);
  if(current==='new' && (posts.length!==1||posts[0].body.project_flow!==context.project_flow||posts[0].body.select_for_project!==1))failures.push('new template payload lost context');failures.push(...errors);console.log((failures.length?'FAIL':'PASS')+' template navigation '+current+' '+width+(failures.length?': '+failures.join('; '):''));if(failures.length)process.exitCode=1;
 }
 await send('Page.close');ws.close();
})().catch(error=>{console.error(error);process.exit(1)});
