(() => {
 const root=document.querySelector('[data-optional-roles]');if(!root)return;
 const rows=root.querySelector('[data-role-rows]'),source=root.querySelector('[data-role-row-template]'),panel=root.querySelector('[data-new-role-panel]'),feedback=root.querySelector('[data-roles-feedback]');let index=1000;
 const count=()=>{root.querySelector('[data-role-count]').textContent=rows.children.length?rows.children.length+' '+(rows.children.length===1?'assignment':'assignments'):'';};
 const save=()=>{count();try{sessionStorage.setItem(root.dataset.draftKey,JSON.stringify([...rows.children].map(row=>({user_id:row.querySelector('[data-role-person]').value,project_role_id:row.querySelector('[data-role-choice]').value}))));}catch{}};
 function add(value={}){const row=source.content.firstElementChild.cloneNode(true);const i=index++;const person=row.querySelector('[data-role-person]'),role=row.querySelector('[data-role-choice]');person.name='members['+i+'][user_id]';role.name='members['+i+'][project_role_id]';person.value=value.user_id||'';role.value=value.project_role_id||'';rows.append(row);count();return row;}
 if(!rows.children.length){try{const cached=JSON.parse(sessionStorage.getItem(root.dataset.draftKey)||'[]');if(Array.isArray(cached))cached.slice(0,100).forEach(add);}catch{}}count();if(rows.children.length)root.open=true;
 root.addEventListener('change',save);
 root.addEventListener('click',async event=>{
  if(event.target.closest('[data-add-role-row]')){if(rows.children.length>=100)return;add();save();}
  if(event.target.closest('[data-remove-role-row]')){event.target.closest('[data-role-row]').remove();save();}
  if(event.target.closest('[data-show-new-role]')){panel.hidden=false;root.querySelector('[data-new-role-name]').focus();}
  if(event.target.closest('[data-cancel-new-role]'))panel.hidden=true;
  const button=event.target.closest('[data-save-new-role]');if(!button)return;
  const name=root.querySelector('[data-new-role-name]').value.trim();if(!name){feedback.textContent='Enter a name for the new role.';root.querySelector('[data-new-role-name]').focus();return;}
  button.disabled=true;feedback.textContent='Creating role...';
  try{const response=await fetch(root.dataset.roleUrl,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({name,project_id:root.dataset.projectId||null,permissions:[...panel.querySelectorAll('input[type=checkbox]:checked')].map(input=>input.value)})});const data=await response.json();if(!response.ok||!data.role)throw Error(data.errors?Object.values(data.errors).flat().join(' '):data.message||'Could not create this role.');
   root.querySelectorAll('select[data-role-choice]').forEach(select=>select.add(new Option(data.role.name,data.role.id)));source.content.querySelector('[data-role-choice]').add(new Option(data.role.name,data.role.id));
   const empty=[...rows.children].find(row=>!row.querySelector('[data-role-choice]').value)||add();empty.querySelector('[data-role-choice]').value=data.role.id;panel.hidden=true;root.querySelector('[data-new-role-name]').value='';feedback.textContent='Role created. Choose a person to assign it to this project.';save();
  }catch(error){feedback.textContent=error.message;}finally{button.disabled=false;}
 });
 root.closest('form')?.addEventListener('submit',()=>{try{sessionStorage.removeItem(root.dataset.draftKey);}catch{}});
})();
