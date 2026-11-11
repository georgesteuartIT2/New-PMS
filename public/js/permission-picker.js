(() => {
 function init(root){
  const boxes=[...root.querySelectorAll('input[type=checkbox]')],items=[...root.querySelectorAll('[data-permission-item]')],search=root.querySelector('[data-permission-search]');
  const refresh=()=>{const term=search.value.trim().toLowerCase();let visible=0;items.forEach(item=>{item.hidden=!item.dataset.permissionText.includes(term);if(!item.hidden)visible++;});root.querySelectorAll('[data-permission-group]').forEach(group=>group.hidden=![...group.querySelectorAll('[data-permission-item]')].some(item=>!item.hidden));root.querySelector('[data-permission-count]').textContent=boxes.filter(box=>box.checked).length;root.querySelector('[data-permission-empty]').hidden=visible>0;};
  search.addEventListener('input',refresh);root.addEventListener('change',refresh);
  const setVisible=value=>items.filter(item=>!item.hidden).forEach(item=>{const box=item.querySelector('input');if(!box.disabled){box.checked=value;box.dispatchEvent(new Event('change',{bubbles:true}));}});
  root.querySelector('[data-permission-select]').addEventListener('click',()=>setVisible(true));root.querySelector('[data-permission-clear]').addEventListener('click',()=>setVisible(false));refresh();
 }
 document.querySelectorAll('[data-permission-picker]').forEach(init);
 const modal=document.getElementById('custom-role-modal');if(modal)modal.addEventListener('keydown',event=>{
  if(event.key==='Escape'){event.preventDefault();document.getElementById('btn-close-cr-modal')?.click();}
  if(event.key==='Tab'){const focusable=[...modal.querySelectorAll('input,button,select,a[href]')].filter(el=>!el.disabled&&el.getClientRects().length);const first=focusable[0],last=focusable.at(-1);if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}}
 });
})();

document.querySelectorAll('[data-role-policy-form]').forEach(form=>{const button=form.querySelector('button[type="submit"]');const original=new FormData(form).getAll('permissions[]').sort().join('|');const note=document.createElement('span');note.className='policy-unsaved';note.setAttribute('role','status');form.querySelector('.editor-bottom-bar>div')?.append(note);form.addEventListener('change',()=>{const changed=new FormData(form).getAll('permissions[]').sort().join('|')!==original;note.textContent=changed?'Unsaved changes':'';if(button)button.textContent=changed?'Save changes':'Save Changes';});});
