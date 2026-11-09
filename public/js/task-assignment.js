document.addEventListener('click',event=>{
 const wrap=event.target.closest('.task-quick-assign');if(!wrap)return;
 const controls=wrap.querySelector('[data-assign-controls]'),button=wrap.querySelector('[data-assign-open]'),select=wrap.querySelector('select'),feedback=wrap.querySelector('[data-assign-feedback]');
 if(event.target.closest('[data-assign-open]')){if(select.options.length===1)select.append(document.getElementById('project-assignee-options')?.content.cloneNode(true)||document.createDocumentFragment());if(select.options.length===1){feedback.textContent='No active project members available. Add someone in Team first.';return;}controls.hidden=false;button.hidden=true;select.focus();}
 if(event.target.closest('[data-assign-cancel]')){controls.hidden=true;button.hidden=false;feedback.textContent='';select.value='';button.focus();}
});
document.addEventListener('change',async event=>{
 const select=event.target.closest('[data-assign-select]');if(!select||!select.value)return;
 const wrap=select.closest('.task-quick-assign'),feedback=wrap.querySelector('[data-assign-feedback]'),cancel=wrap.querySelector('[data-assign-cancel]');select.disabled=true;cancel.disabled=true;feedback.textContent='Assigning...';
 try{const response=await fetch(wrap.dataset.assignUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body:JSON.stringify({user_id:Number(select.value)})});const data=await response.json();if(!response.ok)throw Error(data.message||'Assignment failed. Try again.');const name=document.createElement('span');name.textContent=data.assignees.map(person=>person.name).join(', ');name.className='task-assigned-name';const cell=wrap.parentElement;wrap.replaceWith(name);const status=document.createElement('span');status.className='workspace-sr-only';status.setAttribute('role','status');status.textContent='Task assigned to '+name.textContent;cell.append(status);}
 catch(error){feedback.textContent=error.message;select.value='';select.disabled=false;cancel.disabled=false;}
});
