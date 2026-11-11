(() => {
  function initialize(dialog) {
    if (!dialog || dialog.dataset.initialized) return;
    dialog.dataset.initialized = 'true';
    const form = dialog.querySelector('#project-details-edit-form');
    const feedback = dialog.querySelector('[data-editor-feedback]');
    const start = form.elements.namedItem('starts_on'), end = form.elements.namedItem('ends_on');
    start.addEventListener('change', () => { end.min = start.value; });
    const teamSnapshot=()=>JSON.stringify([...dialog.querySelectorAll('[data-editor-member]')].map(row=>[row.querySelector('[data-member-user]').value,row.querySelector('[data-member-role]').value]).sort());
    const originalTeam=teamSnapshot();
    const originalPm=dialog.querySelector('[name="pm_user_id"]')?.value;
    const confirmTeam=()=>new Promise(resolve=>{
      const popup=document.createElement('dialog');popup.className='team-confirm-dialog';
      popup.innerHTML='<h2>Save team changes?</h2><p>People and roles will be updated. Removed people will also lose their task assignments and project access. A new manager will receive an approval request.</p><div><button type="button" class="btn btn-outline" data-cancel>Keep editing</button><button type="button" class="btn btn-primary" data-confirm>Confirm changes</button></div>';
      document.body.append(popup);let settled=false;const finish=value=>{if(settled)return;settled=true;popup.close();popup.remove();resolve(value);};popup.querySelector('[data-cancel]').onclick=()=>finish(false);popup.querySelector('[data-confirm]').onclick=()=>finish(true);popup.addEventListener('cancel',e=>{e.preventDefault();finish(false);});popup.showModal();popup.querySelector('[data-cancel]').focus();
    });
    let nextIndex = dialog.querySelectorAll('[data-editor-member]').length;
    const members = dialog.querySelector('[data-editor-members]');
    dialog.addEventListener('click', event => {
      if(event.target.closest('[data-close-dialog]'))dialog.close();
      if(event.target.closest('[data-editor-remove-member]'))event.target.closest('[data-editor-member]').remove();
      if(event.target.closest('[data-editor-add-member]')) {
        const row=dialog.querySelector('[data-editor-member-template]').content.cloneNode(true);
        row.querySelector('[data-member-user]').name=`members[${nextIndex}][user_id]`;
        row.querySelector('[data-member-role]').name=`members[${nextIndex}][project_role_id]`;
        nextIndex++;
        members.append(row);
        members.lastElementChild.querySelector('select').focus();
      }
    });
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const submit=dialog.querySelector('[type="submit"]');
      const data=new FormData(form);
      const ids=data.getAll('edit_team').length ? [...dialog.querySelectorAll('[data-member-user]')].map(select=>select.value) : [];
      if(new Set(ids).size!==ids.length){feedback.textContent='Each person can only appear once. Choose their project role.';return;}
      if((teamSnapshot()!==originalTeam || dialog.querySelector('[name="pm_user_id"]')?.value!==originalPm) && !await confirmTeam())return;
      submit.disabled=true;feedback.textContent='Saving changes…';
      try {
        const response=await fetch(form.action,{method:'POST',body:data,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
        const result=await response.json();
        if(!response.ok)throw Error(result.errors ? Object.values(result.errors).flat().join(' ') : result.message || 'Could not save changes.');
        location.reload();
      } catch(error){feedback.textContent=error.message;submit.disabled=false;}
    });
    if(dialog.dataset.reopen==='true')dialog.showModal();
  }
  document.addEventListener('DOMContentLoaded',()=>initialize(document.getElementById('project-edit-dialog')));
  document.addEventListener('click',async event=>{
    const trigger=event.target.closest('[data-project-editor-url]');
    if(!trigger)return;
    event.preventDefault();
    if(trigger.dataset.loading)return;
    trigger.dataset.loading='true';
    try {
      const response=await fetch(trigger.dataset.projectEditorUrl,{headers:{'X-Requested-With':'XMLHttpRequest'}});
      if(!response.ok)throw Error('Unable to open the editor. Please refresh and try again.');
      const documentFragment=new DOMParser().parseFromString(await response.text(),'text/html');
      const dialog=documentFragment.getElementById('project-edit-dialog');
      if(!dialog)throw Error('Your session may have expired. Please refresh and sign in again.');
      document.getElementById('project-edit-dialog')?.remove();
      document.body.append(dialog);initialize(dialog);dialog.showModal();
    } catch(error){
      let notice=document.getElementById('project-editor-load-error');
      if(!notice){notice=document.createElement('p');notice.id='project-editor-load-error';notice.setAttribute('role','alert');notice.className='alert danger';document.querySelector('main')?.prepend(notice);}
      notice.textContent=error.message;
    } finally {delete trigger.dataset.loading;}
  });
})();
