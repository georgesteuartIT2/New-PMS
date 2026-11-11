const fs=require('fs'),path=require('path');
(async()=>{const target=await(await fetch('http://127.0.0.1:9223/json/new',{method:'PUT'})).json();const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise((r,j)=>{ws.onopen=r;ws.onerror=j;});let id=0;const pending=new Map();ws.onmessage=e=>{const m=JSON.parse(e.data);if(pending.has(m.id)){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}};const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}));});const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};await send('Page.enable');
for(const width of [1600,1280,390]){
 await send('Emulation.setDeviceMetricsOverride',{width,height:950,deviceScaleFactor:1,mobile:width===390});await send('Page.navigate',{url:'file:///'+path.resolve('storage/app/ui-review/pmo-templates-create.html').replaceAll('\\','/')});await new Promise(r=>setTimeout(r,500));
 await evaluate(`SmartTaskPlan.openCreate({name:'Website rollout plan',estimated_duration:10,duration_unit:'working_days',reference_start_date:'2026-11-11',working_hours:'09:00 - 17:00'});`);
 const before=await evaluate(`({count:SmartTaskPlan.tasks.length,overflow:document.documentElement.scrollWidth>innerWidth+2,footer:document.getElementById('stp-unsaved-text').textContent,body:document.querySelector('.stp-body').getBoundingClientRect().height,controlHeight:document.querySelector('#stp-table-body input').getBoundingClientRect().height})`);
 if(before.count!==10||before.overflow||before.body<150||before.controlHeight>38)throw Error(JSON.stringify(before));
 await evaluate(`const input=document.querySelector('#stp-table-body input[type=text]');input.value='Review project scope';input.dispatchEvent(new Event('input',{bubbles:true}));SmartTaskPlan.addSubtask(0);SmartTaskPlan.addTaskBelow(0);`);
 const after=await evaluate(`({count:SmartTaskPlan.tasks.length,title:SmartTaskPlan.tasks[0].title,sub:SmartTaskPlan.tasks.some(t=>t.parent_code),buttons:[...document.querySelectorAll('#stp-table-body button')].every(b=>b.getAttribute('aria-label')),summary:document.getElementById('stp-summary-counts').textContent})`);
 if(after.count!==12||after.title!=='Review project scope'||!after.sub||!after.buttons||after.summary.includes('\\u00b7'))throw Error(JSON.stringify(after));
 await new Promise(r=>setTimeout(r,350));
 const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync('storage/app/ui-review/task-plan-clean-'+width+'.png',Buffer.from(shot.data,'base64'));console.log('PASS '+width+' popup layout, inline edit, subtask, row insertion and action labels');
}
ws.close();await fetch('http://127.0.0.1:9223/json/close/'+target.id);
})().catch(e=>{console.error(e);process.exit(1)});
