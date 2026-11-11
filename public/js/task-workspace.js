(() => {
 let refresh;
 document.addEventListener('task-progress-saved',async()=>{
  const workspace=document.querySelector('.task-workspace-personal');if(!workspace)return;
  const focusedTask=document.activeElement?.dataset.taskId;
  refresh?.abort();refresh=new AbortController();const signal=refresh.signal;
  try{
   const response=await fetch(location.href,{headers:{Accept:'text/html'},signal});if(!response.ok)return;
   const page=new DOMParser().parseFromString(await response.text(),'text/html');if(signal.aborted)return;
   for(const selector of ['.tw-views','.tw-table-surface','.tw-result-count']){
    const current=workspace.querySelector(selector),updated=page.querySelector('.task-workspace-personal '+selector);
    if(current&&updated)current.replaceWith(updated);
   }
   if(focusedTask){const control=[...workspace.querySelectorAll('.quick-status-select')].find(el=>el.dataset.taskId===focusedTask);(control||workspace.querySelector('.tw-views [aria-current]'))?.focus({preventScroll:true});}
  }catch(error){if(error.name!=='AbortError')console.debug('Task summary refresh unavailable.');}
 });
})();

document.addEventListener('change',async(event)=>{
 const select=event.target.closest('[data-pm-assign-url]');if(!select||!select.value)return;
 const feedback=document.getElementById('progress-save-feedback');select.disabled=true;
 try{const response=await fetch(select.dataset.pmAssignUrl,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({user_id:select.value})});const data=await response.json();if(!response.ok)throw new Error(data.message||'Assignment could not be saved.');if(feedback){feedback.classList.remove('workspace-sr-only');feedback.textContent='Assigned successfully.';}document.dispatchEvent(new CustomEvent('task-progress-saved'));}
 catch(error){select.disabled=false;select.value='';if(feedback){feedback.classList.remove('workspace-sr-only');feedback.textContent=error.message;}}
});
