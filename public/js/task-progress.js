(() => {
 let saving=false;
 function apply(data){
  for(const task of data.tasks||[]){
   const row=document.getElementById('task-row-'+task.id);if(!row)continue;
   const fill=row.querySelector('.task-progress-fill'),text=row.querySelector('.task-progress-text'),select=row.querySelector('.quick-status-select');
   if(fill){fill.style.width=task.progress+'%';fill.style.background=task.is_overdue?'#DC2626':'#315F58';}
   if(text)text.textContent=task.progress+'%';
   row.dataset.taskStatus=task.status;row.dataset.isOverdue=task.is_overdue?'1':'0';
   if(select){select.value=task.status;select.dataset.currentStatus=task.status;}
   else {const status=row.lastElementChild?.querySelector('.uwt-status-pill,.status-pill,.pill');if(status){status.className='uwt-status-pill status-'+task.status;status.textContent=({todo:'To do',in_progress:'In progress',completed:'Completed',on_hold:'On hold'})[task.status];}}
   row.classList.toggle('is-overdue-task',task.is_overdue);
   if(!task.is_overdue)row.querySelectorAll('.badge-overdue,.badge-overdue-tag').forEach(b=>b.remove());
  }
  document.querySelectorAll('[data-project-progress-text]').forEach(el=>el.textContent=data.project_progress+'%');
  document.querySelectorAll('[data-project-progress-bar]').forEach(el=>{el.setAttribute('aria-valuenow',data.project_progress);if(el.firstElementChild)el.firstElementChild.style.width=data.project_progress+'%';});
  document.querySelectorAll('[data-project-completed-count]').forEach(el=>el.textContent=data.completed_tasks+' of '+data.total_tasks+' tasks complete');
  document.querySelectorAll('[data-project-ready]').forEach(el=>el.hidden=data.project_progress!==100);
  const feedback=document.getElementById('progress-save-feedback');if(feedback)feedback.textContent='Saved. Project completion: '+data.project_progress+'%.';
  document.dispatchEvent(new CustomEvent('task-progress-saved',{detail:data}));
 }
 async function save(url,values){
  const res=await fetch(url,{method:'PATCH',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body:JSON.stringify(values)});
  const data=await res.json();if(!res.ok)throw Error(data.message||'Could not save. Try again.');apply(data);return data;
 }
 document.addEventListener('change',async event=>{
  const select=event.target.closest('.quick-status-select');if(!select)return;
  const old=select.dataset.currentStatus;select.disabled=true;
  try{await save(select.dataset.updateUrl,{status:select.value});}catch(error){select.value=old;alert(error.message);}finally{select.disabled=false;}
 });
 document.addEventListener('submit',async event=>{
  const form=event.target;if(!form.closest('.task-inline-content')||! /\/tasks\/\d+$/.test(new URL(form.getAttribute('action'),location.href).pathname)||!form.querySelector('[name="status"]'))return;
  event.preventDefault();if(saving)return;saving=true;const button=event.submitter;if(button)button.disabled=true;
  const values=Object.fromEntries(new FormData(form));delete values._token;delete values._method;
  try{const data=await save(form.getAttribute('action'),values);const feedback=document.createElement('p');feedback.setAttribute('role','status');feedback.textContent='Update saved. Project completion: '+data.project_progress+'%.';form.querySelector('[data-save-feedback]')?.remove();feedback.dataset.saveFeedback='';form.append(feedback);}catch(error){alert(error.message);}finally{saving=false;if(button)button.disabled=false;}
 });
 document.addEventListener('DOMContentLoaded',()=>{if(document.getElementById('project-workspace')){const p=document.createElement('p');p.id='progress-save-feedback';p.className='workspace-sr-only';p.setAttribute('role','status');document.getElementById('project-workspace').append(p);}});
})();
