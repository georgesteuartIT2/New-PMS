document.addEventListener('DOMContentLoaded',()=>{
  const form=document.getElementById('create-project-form');if(!form)return;
  // Search dropdowns retain input focus and support keyboard selection.
  const pickers=[['pm-search-input','pm-search-dropdown'],['team-search-input','team-search-dropdown'],...[...document.querySelectorAll('.gov-search-input')].map(input=>[input.id,input.parentElement.querySelector('.gov-dropdown')])];
  pickers.forEach(([inputId,dropdownRef])=>{
    const input=document.getElementById(inputId),dropdown=typeof dropdownRef==='string'?document.getElementById(dropdownRef):dropdownRef;
    if(!input||!dropdown)return;
    dropdown.id ||= inputId+'-results';
    input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-controls',dropdown.id);input.setAttribute('aria-expanded','false');dropdown.setAttribute('role','listbox');dropdown.setAttribute('aria-label','People search results');
    let selected=-1;
    const sync=()=>{
      const visible=dropdown.style.display!=='none' && Boolean(dropdown.children.length);
      input.setAttribute('aria-expanded',String(visible));
      [...dropdown.querySelectorAll('.user-search-item')].forEach((item,index)=>{item.id=dropdown.id+'-option-'+index;item.setAttribute('role','option');item.setAttribute('aria-selected',String(index===selected));});
      if(!visible){selected=-1;input.removeAttribute('aria-activedescendant');}
    };
    new MutationObserver(sync).observe(dropdown,{childList:true,attributes:true,attributeFilter:['style']});
    input.addEventListener('keydown',event=>{
      if(event.key==='Escape'){dropdown.style.display='none';input.removeAttribute('aria-activedescendant');return;}
      if(!['ArrowDown','ArrowUp','Enter'].includes(event.key)||dropdown.style.display==='none')return;
      const options=[...dropdown.querySelectorAll('.user-search-item')];if(!options.length)return;
      if(event.key==='Enter'){
        event.preventDefault();if(selected>=0)options[selected]?.click();return;
      }
      event.preventDefault();selected=(selected+(event.key==='ArrowDown'?1:-1)+options.length)%options.length;
      options.forEach((option,index)=>{option.classList.toggle('highlighted',index===selected);option.setAttribute('aria-selected',String(index===selected));});
      input.setAttribute('aria-activedescendant',options[selected].id);options[selected].scrollIntoView({block:'nearest'});
    });
    input.addEventListener('input',()=>{selected=-1;input.removeAttribute('aria-activedescendant');});sync();
  });
  // Back navigation restores a usable form after a native submission.
  window.addEventListener('pageshow',()=>{
    delete form.dataset.submitting;form.querySelectorAll('input[data-submit-action]').forEach(input=>input.remove());
    const create=document.getElementById('btn-create-project'),draft=document.getElementById('btn-save-draft');
    if(create){create.disabled=false;create.innerHTML=(form.dataset.draftId?'Create project':'Send for PM approval')+' <span aria-hidden="true">&rarr;</span>';}
    if(draft)draft.disabled=false;
  });
});
