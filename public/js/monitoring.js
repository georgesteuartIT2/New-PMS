document.addEventListener('DOMContentLoaded',()=>{
 const nav=document.querySelector('[data-monitoring-tabs]');if(!nav)return;
 const current=nav.dataset.currentMonitoringTab,user=nav.dataset.monitoringUser;
 // Keep each tab's last search/filter state within this signed-in user's browser session.
 try {
  const key=tab=>'projectflow.monitoring.'+user+'.'+tab;
  const allowed=['tab','q','project_id','subsidiary_id','filter','status','user_id','workload','has_overdue','has_critical'];
  const currentParams=new URLSearchParams(location.search);currentParams.set('tab',current);
  sessionStorage.setItem(key(current),currentParams.toString());
  nav.querySelectorAll('[data-monitoring-tab]').forEach(link=>{
   const tab=link.dataset.monitoringTab;if(tab===current)return;
   const saved=sessionStorage.getItem(key(tab));if(!saved)return;
   const url=new URL(link.href),params=new URLSearchParams(saved);url.search='';
   for(const [name,value]of params)if(allowed.includes(name))url.searchParams.set(name,value);
   url.searchParams.set('tab',tab);link.href=url.toString();
  });
 } catch(error) { /* Direct tab links work when session storage is unavailable. */ }
 // Arrow keys move between the two links; Enter performs the normal, addressable navigation.
 nav.addEventListener('keydown',event=>{
  if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;
  const links=[...nav.querySelectorAll('[data-monitoring-tab]')],index=links.indexOf(document.activeElement);if(index<0)return;
  event.preventDefault();const next=event.key==='Home'?0:event.key==='End'?links.length-1:(index+(event.key==='ArrowRight'?1:-1)+links.length)%links.length;links[next].focus();
 });
});
// Existing monitoring popups keep their actions and gain consistent keyboard/focus behaviour.
document.addEventListener('DOMContentLoaded',()=>{
 let activePopup=null;
 for(const id of ['pmo-team-workload-modal','pmo-reassign-modal','pmo-task-details-backdrop','pmo-request-update-modal','pmo-employee-workload-modal']){
  const popup=document.getElementById(id);if(!popup)continue;
  popup.setAttribute('role','dialog');popup.setAttribute('aria-modal','true');
  const heading=popup.querySelector('h2,h3');if(heading){heading.id ||= id+'-title';popup.setAttribute('aria-labelledby',heading.id);}
  let opener=null;
  new MutationObserver(()=>{
   const open=popup.style.display!=='none' && Boolean(popup.getClientRects().length);
   popup.setAttribute('aria-hidden',String(!open));
   if(open && activePopup!==popup){opener=document.activeElement;activePopup=popup;popup.querySelector('button,input,select,a[href]')?.focus();}
   else if(!open && activePopup===popup){activePopup=null;opener?.focus();}
  }).observe(popup,{attributes:true,attributeFilter:['style']});
 }
 document.addEventListener('keydown',event=>{
  if(event.key!=='Tab'||!activePopup)return;
  const controls=[...activePopup.querySelectorAll('button,input,select,textarea,a[href],[tabindex="0"]')].filter(control=>!control.disabled && control.getClientRects().length);
  const first=controls[0],last=controls[controls.length-1];if(!first)return;
  if(event.shiftKey && document.activeElement===first){event.preventDefault();last.focus();}
  else if(!event.shiftKey && document.activeElement===last){event.preventDefault();first.focus();}
 });
});
