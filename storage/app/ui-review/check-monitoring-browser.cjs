const fs=require('fs'),path=require('path');
(async()=>{
 const root=__dirname,target=await(await fetch('http://127.0.0.1:9223/json/new',{method:'PUT'})).json();const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject});let id=0;const pending=new Map(),runtimeErrors=[];
 ws.onmessage=event=>{const message=JSON.parse(event.data);if(message.method==='Runtime.exceptionThrown')runtimeErrors.push(message.params.exceptionDetails.exception?.description||message.params.exceptionDetails.text);if(message.id&&pending.has(message.id)){const item=pending.get(message.id);pending.delete(message.id);message.error?item.reject(Error(message.error.message)):item.resolve(message.result)}};
 const send=(method,params={})=>new Promise((resolve,reject)=>{const key=++id;pending.set(key,{resolve,reject});ws.send(JSON.stringify({id:key,method,params}))});
 const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(result.exceptionDetails.exception?.description||result.exceptionDetails.text);return result.result.value};
 await send('Page.enable');await send('Runtime.enable');
 const panel=fs.readFileSync(path.join(root,'pmo-tasks-24-panel.html'),'utf8'),employee=JSON.parse(fs.readFileSync(path.join(root,'monitoring-employee.json'),'utf8'));
 const user=fs.readFileSync(path.join(root,'pmo-monitoring.html'),'utf8').match(/data-monitoring-user="([^"]+)"/)[1];
 await send('Page.addScriptToEvaluateOnNewDocument',{source:`try{sessionStorage.setItem('projectflow.monitoring.${user}.tasks','tab=tasks&q=task-search&filter=overdue');sessionStorage.setItem('projectflow.monitoring.${user}.team','tab=team&q=team-search&workload=not_configured')}catch(error){}`});
 for(const tab of ['tasks','team'])for(const width of [1440,1280,390]){
  runtimeErrors.length=0;await send('Emulation.setDeviceMetricsOverride',{width,height:width===390?844:1100,deviceScaleFactor:1,mobile:width===390});const file=tab==='tasks'?'pmo-monitoring.html':'pmo-monitoring-tab-team.html';await send('Page.navigate',{url:'file:///'+path.join(root,file).replaceAll('\\','/')+'?tab='+tab});
  for(let n=0;n<50;n++){await new Promise(r=>setTimeout(r,60));if(await evaluate('document.readyState==="complete"'))break;}
  await evaluate(`window.__panel=${JSON.stringify(panel)};window.__employee=${JSON.stringify(employee)};window.fetch=async(url,options={})=>{if(options.method==='POST')throw Error('Unexpected write in UI check');return new Response(String(url).includes('/employee/')?JSON.stringify(window.__employee):window.__panel,{status:200})};`);
  const failures=await evaluate(`(async()=>{
   const failures=[],pause=ms=>new Promise(resolve=>setTimeout(resolve,ms)),tab=${JSON.stringify(tab)};
   const active=document.querySelector('nav[aria-label="Main navigation"]>a.active');if(active?.textContent.trim()!=='Monitoring')failures.push('sidebar active state');
   const nav=document.querySelector('[data-monitoring-tabs]'),links=[...nav.querySelectorAll('a')];if(links.length!==2||nav.querySelectorAll('[aria-current="page"]').length!==1)failures.push('two monitoring tabs');
   const other=links.find(link=>link.dataset.monitoringTab!==tab),query=new URL(other.href).searchParams;if(query.get('q')!==(tab==='tasks'?'team-search':'task-search'))failures.push('per-tab search memory');if(query.get(tab==='tasks'?'workload':'filter')!==(tab==='tasks'?'not_configured':'overdue'))failures.push('per-tab filter memory');
   links[0].focus();links[0].dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true}));if(document.activeElement!==links[1])failures.push('tab keyboard navigation');
   const form=document.getElementById(tab==='tasks'?'task-filter-form':'team-filter-form');let submitted;
   form.addEventListener('submit',event=>{event.preventDefault();submitted=new FormData(form)});
   const search=form.querySelector('[name="q"]');search.value='filter review';const project=form.querySelector('[name="project_id"]');if(project.options.length>1)project.selectedIndex=1;project.dispatchEvent(new Event('change',{bubbles:true}));
   if(submitted?.get('tab')!==tab||submitted?.get('q')!=='filter review')failures.push('filter autosubmit preserves tab and search');
   if(tab==='team'){
    const table=document.getElementById('monitoring-team-records'),headers=table.querySelectorAll('thead th').length;if(headers!==8)failures.push('team column count');table.querySelectorAll('tbody tr').forEach(row=>{if(!row.querySelector('[colspan]')&&row.children.length!==headers)failures.push('team table alignment')});
    const trigger=table.querySelector('a[onclick]');if(trigger){trigger.focus();trigger.click();await pause(80);const popup=document.getElementById('pmo-team-workload-modal');if(popup.style.display==='none'||!document.getElementById('twm-name').textContent.includes(window.__employee.data.employee.name))failures.push('employee workload popup');if(!popup.contains(document.activeElement))failures.push('popup focus');document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));await pause(20);if(popup.style.display!=='none')failures.push('workload Escape close');if(document.activeElement!==trigger)failures.push('workload focus restoration');}
   }else{
    const trigger=document.querySelector('[data-task-panel]');if(trigger){trigger.focus();trigger.click();await pause(80);const panel=document.getElementById('task-side-panel');if(panel.hidden||!panel.querySelector('.panel-task-top h3'))failures.push('task panel');document.getElementById('task-panel-close').click();if(!panel.hidden)failures.push('task panel close');}
   }
   if(document.documentElement.scrollWidth>innerWidth+2)failures.push('page overflow');return failures;
  })()`);failures.push(...runtimeErrors);console.log((failures.length?'FAIL':'PASS')+' monitoring interactions '+tab+' '+width+(failures.length?': '+failures.join(', '):''));if(failures.length)process.exitCode=1;
 }
 await send('Page.close');ws.close();
})().catch(error=>{console.error(error);process.exit(1)});
