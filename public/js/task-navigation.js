/* Task details live in their project task list, never in an overlay. */
(() => {
  let focusedId;
  const focusRow = id => {
    if (!/^\d+$/.test(String(id))) return null;
    const row = document.getElementById('task-row-' + id);
    if (!row) return null;
    document.querySelector('[data-tab-target="tasks"]')?.click();
    const visited = new Set(); let parent = row.dataset.parent;
    while (parent && !visited.has(parent)) {
      visited.add(parent); const toggle = document.querySelector('[data-parent-task="'+parent+'"]');
      if (toggle?.getAttribute('aria-expanded') === 'false') toggle.click();
      parent = document.getElementById('task-row-'+parent)?.dataset.parent;
    }
    document.querySelectorAll('.project-task-row.is-focused-task').forEach(item=>item.classList.remove('is-focused-task'));
    row.classList.add('is-focused-task'); row.tabIndex=-1; row.focus({preventScroll:true});
    row.scrollIntoView({block:'center',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
    return row;
  };
  document.addEventListener('click',event=>{
    const trigger=event.target.closest('[data-task-location]');if(!trigger)return;
    event.preventDefault();const url=new URL(trigger.dataset.taskLocation,location.href);
    if(url.origin===location.origin && url.pathname===location.pathname && document.getElementById('project-workspace')){
      history.replaceState(null,'',url);focusRow(url.searchParams.get('focus_task'));
    }else location.assign(url.href);
  });
  function clearFocus(){
    document.querySelectorAll('.project-task-row.is-focused-task').forEach(row=>row.classList.remove('is-focused-task'));
    const url=new URL(location.href);url.searchParams.delete('focus_task');if(/^#task-row-\d+$/.test(url.hash))url.hash='#tasks';
    history.replaceState(null,'',url);focusedId=null;
  }
  document.addEventListener('click',event=>{if(!event.target.closest('.project-task-row.is-focused-task,[data-task-location]') && document.querySelector('.project-task-row.is-focused-task'))clearFocus();});
  document.addEventListener('keydown',event=>{if(event.key==='Escape' && document.querySelector('.project-task-row.is-focused-task'))clearFocus();});
  const init=()=>{const id=new URLSearchParams(location.search).get('focus_task') || location.hash.match(/^#task-row-(\d+)$/)?.[1];if(!id||id===focusedId)return;focusedId=id;focusRow(id);};
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(init,80));else setTimeout(init,80);
  addEventListener('popstate',()=>{focusedId=null;init();});
})();
