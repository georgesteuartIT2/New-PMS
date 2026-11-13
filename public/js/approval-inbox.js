(() => {
 const dialog=document.getElementById('approval-review-dialog');if(!dialog)return;
 let loading,saving=false,trigger,previousOverflow='';
 const close=()=>{if(!saving)dialog.close();};
 dialog.addEventListener('close',()=>{loading?.abort();document.body.style.overflow=previousOverflow;trigger?.focus();});
 dialog.addEventListener('cancel',event=>{if(saving)event.preventDefault();});
 document.addEventListener('click',async event=>{
  const link=event.target.closest('[data-approval-review-url]');if(!link)return;event.preventDefault();trigger=link;
  loading?.abort();loading=new AbortController();const signal=loading.signal;
  dialog.innerHTML='<div class="ar-loading"><span class="ar-spinner" aria-hidden="true"></span><h2 id="approval-request-title">Opening request</h2><p role="status">Loading project details...</p><button type="button" class="btn btn-outline" data-close-approval>Close</button></div>';
  previousOverflow=document.body.style.overflow;document.body.style.overflow='hidden';if(!dialog.open)dialog.showModal();
  try{
   const response=await fetch(link.dataset.approvalReviewUrl,{headers:{Accept:'text/html'},signal});
   if(!response.ok)throw Error(response.status===409||response.status===404?'This request is no longer available. Refresh your dashboard.':'Unable to load this request. Refresh your dashboard and try again.');
   const page=new DOMParser().parseFromString(await response.text(),'text/html'),content=page.querySelector('[data-approval-content]');if(!content)throw Error('Your session may have expired. Refresh and sign in again.');if(signal.aborted)return;
   dialog.replaceChildren(content);dialog.querySelector('h2')?.focus({preventScroll:true});
  }catch(error){if(error.name==='AbortError')return;dialog.querySelector('.ar-spinner')?.remove();dialog.querySelector('[role=status]').textContent=error.message;}
 });
 dialog.addEventListener('click',event=>{
  // Clicking the dimmed backdrop closes the popup.
  if(event.target===dialog){close();return;}
  if(event.target.closest('[data-close-approval]')){close();return;}
  // One tap on a quick reason fills the rejection box.
  const chip=event.target.closest('[data-quick-reason]');
  if(chip){
   const box=dialog.querySelector('#approval-reject-reason');
   if(box){box.value=chip.dataset.quickReason;box.focus();box.setSelectionRange(box.value.length,box.value.length);}
   dialog.querySelectorAll('[data-quick-reason]').forEach(c=>c.classList.toggle('is-selected',c===chip));
   return;
  }
  const reject=event.target.closest('[data-show-rejection]'),back=event.target.closest('[data-reject-back]');if(!reject&&!back)return;
  const fields=dialog.querySelector('[data-reject-fields]'),reason=fields.querySelector('textarea'),show=!!reject;
  fields.hidden=!show;reason.disabled=!show;reason.required=show;
  for(const key of ['reject-back','reject-submit'])dialog.querySelector('[data-'+key+']').hidden=!show;
  dialog.querySelector('[data-show-rejection]').hidden=show;dialog.querySelector('[data-accept-project]').hidden=show;
  dialog.querySelector('[data-approval-note]').hidden=show;
  dialog.querySelector('[data-approval-error]').hidden=true;
  if(show)reason.focus();else dialog.querySelector('[data-show-rejection]').focus();
 });
 dialog.addEventListener('submit',async event=>{
  const form=event.target.closest('[data-approval-response]');if(!form)return;event.preventDefault();if(saving)return;
  const decision=event.submitter?.dataset.decision;if(!decision)return;
  if(!form.reportValidity())return;
  const data=new FormData(form);data.set('decision',decision);const errorBox=form.querySelector('[data-approval-error]');errorBox.hidden=true;
  saving=true;form.setAttribute('aria-busy','true');const buttons=[...dialog.querySelectorAll('button')];buttons.forEach(button=>button.disabled=true);
  const note=form.querySelector('[data-approval-note]'),original=note.textContent,noteHidden=note.hidden;note.hidden=false;note.textContent=decision==='accept'?'Accepting... opening plan setup.':'Sending your rejection...';
  try{
   const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:data});const result=await response.json();
   if(!response.ok)throw Error(result.errors?Object.values(result.errors).flat().join(' '):result.message||'This request could not be processed. Refresh your dashboard.');
   // Close immediately after the server confirms the decision.
   // Navigating to the current dashboard hash alone does not reload its content.
   saving=false;form.removeAttribute('aria-busy');dialog.close();
   const destination=new URL(result.redirect||location.href,location.href);
   if(destination.origin===location.origin && destination.pathname===location.pathname && destination.search===location.search){
    if(destination.hash!==location.hash)history.replaceState(null,'',destination.href);
    window.location.reload();
   }else window.location.assign(destination.href);
  }catch(error){errorBox.textContent=error instanceof TypeError?'Unable to confirm the response. Refresh your dashboard before trying again.':error.message;errorBox.hidden=false;saving=false;form.removeAttribute('aria-busy');buttons.forEach(button=>button.disabled=false);note.textContent=original;note.hidden=noteHidden;}
 });
})();
