document.addEventListener('DOMContentLoaded', () => {
  // Add readable mobile labels only to simple record tables; specialist grids keep their own layout.
  document.querySelectorAll('table').forEach(table => {
    if(table.closest('dialog,.timeline-container,.gantt-container,.stp-modal,.calendar-grid') || table.matches('.project-tasks-full-table,.projects-table,.bulk-edit-table,.monitor-task-records') || table.querySelector('thead [colspan],thead [rowspan]'))return;
    const headings=[...table.querySelectorAll('thead tr:first-child th')];
    if(!headings.length || table.querySelectorAll('thead tr').length!==1)return;
    table.classList.add('system-record-table');
    headings.forEach(heading=>heading.setAttribute('scope','col'));
    table.querySelectorAll('tbody tr').forEach(row=>[...row.children].forEach((cell,index)=>{
      if(cell.tagName==='TD' && !cell.hasAttribute('colspan'))cell.dataset.label=headings[index]?.textContent.trim() || '';
    }));
    const wrap=table.closest('.table-wrap');
    if(wrap){wrap.tabIndex=0;wrap.setAttribute('role','region');wrap.setAttribute('aria-label',table.closest('section')?.querySelector('h2')?.textContent.trim() || 'Records');}
  });
  // Keep keyboard users within the same visible project sections.
  const tabs=document.querySelector('.project-tabs-nav');
  if(tabs){
    const buttons=[...tabs.querySelectorAll('[data-tab-target]')];
    tabs.setAttribute('role','tablist');
    const sync=()=>buttons.forEach(button=>{
      const active=button.classList.contains('active');
      button.setAttribute('role','tab');button.setAttribute('aria-selected',String(active));button.tabIndex=active?0:-1;
      button.id ||= 'project-tab-'+button.dataset.tabTarget;
      const panel=document.getElementById('tab-'+button.dataset.tabTarget);
      if(panel){panel.setAttribute('role','tabpanel');panel.setAttribute('aria-labelledby',button.id);}
    });
    tabs.addEventListener('click',()=>queueMicrotask(sync));
    tabs.addEventListener('keydown',event=>{
      if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;
      const current=buttons.indexOf(document.activeElement);if(current<0)return;
      event.preventDefault();
      const next=event.key==='Home'?0:event.key==='End'?buttons.length-1:(current+(event.key==='ArrowRight'?1:-1)+buttons.length)%buttons.length;
      buttons[next].click();buttons[next].focus();
    });
    sync();
  }
  document.querySelectorAll('form').forEach(form=>{
    const start=form.querySelector('input[name="starts_on"]'),end=form.querySelector('input[name="ends_on"]');
    if(start&&end){const update=()=>end.min=start.value;start.addEventListener('change',update);update();}
  });
  document.querySelectorAll('.pagination-wrap a,.pagination-pages a').forEach(link=>{
    const text=link.textContent.trim();
    if(!link.getAttribute('aria-label'))link.setAttribute('aria-label',/^[‹«←]$/.test(text)?'Previous page':/^[›»→]$/.test(text)?'Next page':'Page '+text);
  });
  document.querySelectorAll('.alert').forEach(alert=>{if(!alert.hasAttribute('role'))alert.setAttribute('role',alert.classList.contains('danger')?'alert':'status');});
  document.querySelectorAll('input[type="search"]').forEach(input=>{
    input.addEventListener('search',()=>input.dispatchEvent(new Event('input',{bubbles:true})));
  });
  document.querySelectorAll('[data-add-mode]').forEach(button=>button.addEventListener('click',()=>{
    const options=document.querySelector('#add-task-dialog .task-plan-options');
    if(options)options.open=button.dataset.addMode==='sub';
  }));
  // An invalid field inside collapsed optional settings must be visible and reachable.
  document.addEventListener('invalid',event=>{
    let parent=event.target.parentElement;
    while(parent){if(parent.tagName==='DETAILS')parent.open=true;parent=parent.parentElement;}
    event.target.setAttribute('aria-invalid','true');
  },true);
  document.addEventListener('input',event=>{if(event.target.matches('input,select,textarea') && event.target.validity.valid)event.target.removeAttribute('aria-invalid');});
});
