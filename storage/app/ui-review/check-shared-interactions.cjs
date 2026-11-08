const fs=require('fs'),path=require('path');
(async()=>{
 const root=__dirname,target=await(await fetch('http://127.0.0.1:9223/json/new',{method:'PUT'})).json();
 const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise((r,j)=>{ws.onopen=r;ws.onerror=j});let id=0;const pending=new Map();
 ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id&&pending.has(m.id)){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result)}};
 const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}))});
 const evaluate=async expression=>{const response=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(response.exceptionDetails)throw Error(response.exceptionDetails.text);return response.result.value;};
 await send('Page.enable');await send('Runtime.enable');
 const userPanel=fs.readFileSync(path.join(root,'user-tasks-12-panel.html'),'utf8');
 const panel=fs.readFileSync(path.join(root,'pmo-tasks-17-panel.html'),'utf8');
 const fragment=fs.readFileSync(path.join(root,'shared-editor-fragment.html'),'utf8');
 for(const page of ['pmo-dashboard','user-dashboard','pmo-pmo-task-monitor','user-tasks-my','pmo-projects'])for(const width of [1440,390]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:width===390?844:1100,deviceScaleFactor:1,mobile:width===390});
  await send('Page.navigate',{url:'file:///'+path.join(root,page+'.html').replaceAll('\\','/')});
  for(let n=0;n<60;n++){await new Promise(r=>setTimeout(r,50));if(await evaluate('document.readyState==="complete"'))break;}
  await evaluate(`window.__panelHtml=${JSON.stringify(page.startsWith('user-')?userPanel:panel)};window.__editorHtml=${JSON.stringify(fragment)};window.__originalFetch=window.fetch;window.fetch=async(url,options)=>String(url).includes('/panel')?new Response(window.__panelHtml,{status:200}):String(url).includes('popup=1')?new Response(window.__editorHtml,{status:200}):window.__originalFetch(url,options);`);
  const result=await evaluate(`(async()=>{const failures=[];const link=document.querySelector('[data-task-panel]');if(link){link.click();await new Promise(r=>setTimeout(r,50));const drawer=document.getElementById('task-side-panel');if(!drawer || drawer.hidden || !drawer.querySelector('.panel-task-top h3'))failures.push('shared task drawer');document.getElementById('task-panel-close').click();if(!drawer.hidden)failures.push('drawer close');if(document.activeElement!==link)failures.push('drawer focus restoration');}else if(!document.querySelector('[data-project-editor-url]'))failures.push('no direct task/editor action');const shortcut=document.querySelector('[data-task-panel-section]');if(shortcut){shortcut.click();await new Promise(r=>setTimeout(r,50));const section=document.querySelector('#task-panel-content [data-panel-section="'+shortcut.dataset.taskPanelSection+'"]');if(section?.tagName==='DETAILS'&&!section.open)failures.push('direct assignment section');document.getElementById('task-panel-close').click();}const edit=document.querySelector('[data-project-editor-url]');if(edit){edit.click();await new Promise(r=>setTimeout(r,80));const dialog=document.getElementById('project-edit-dialog');if(!dialog?.open || !dialog.querySelector('[name="project_template_id"]'))failures.push('table shared editor');const rect=dialog?.getBoundingClientRect();if(rect && (rect.width>innerWidth || rect.bottom>innerHeight+1))failures.push('table editor bounds');dialog?.close();}return failures;})()`);
  console.log((result.length?'FAIL':'PASS')+' shared interactions '+width+' '+page+(result.length?': '+result.join(', '):''));if(result.length)process.exitCode=1;
 }
 await send('Page.close');ws.close();
})().catch(error=>{console.error(error);process.exit(1)});
