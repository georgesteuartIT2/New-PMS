(() => {
 const config=document.getElementById('directory-picker-config');
 function init(select){
  if(select.dataset.directoryReady)return;select.dataset.directoryReady='1';
  const wasFocused=document.activeElement===select;const required=select.required;
  const wrap=document.createElement('div');wrap.className='directory-picker';select.after(wrap);wrap.append(select);
  const input=document.createElement('input');input.type='search';input.autocomplete='off';input.placeholder='Search name or email';input.setAttribute('aria-label','Search system or Microsoft people');input.setAttribute('aria-expanded','false');
  if(select.hasAttribute('aria-describedby'))input.setAttribute('aria-describedby',select.getAttribute('aria-describedby'));
  input.required=required;if(select.hasAttribute('form'))input.setAttribute('form',select.getAttribute('form'));
  const list=document.createElement('div');list.className='directory-results';list.hidden=true;
  const status=document.createElement('small');status.className='directory-status';status.setAttribute('role','status');
  wrap.append(input,list,status);if(select.dataset.directoryFloating){(select.closest('dialog')||document.body).append(list);list.classList.add('directory-results-floating');}
  function positionList(){if(!select.dataset.directoryFloating)return;const rect=input.getBoundingClientRect();list.style.position='fixed';list.style.width=Math.max(260,Math.min(340,innerWidth-24))+'px';list.style.left=Math.max(8,Math.min(innerWidth-parseFloat(list.style.width)-8,rect.left))+'px';list.style.top=(rect.bottom+4)+'px';list.style.maxHeight=Math.max(120,Math.min(250,innerHeight-rect.bottom-20))+'px';}
  select.closest('.bulk-edit-scroll')?.addEventListener('scroll',()=>close(),{passive:true});
  select.hidden=true;select.style.display='none';select.required=false;
  if(select.value)input.value=select.selectedOptions[0]?.textContent.trim()||'';
  let timer,controller,version=0,busy=false,suppressFocusSearch=false;
  const close=()=>{list.hidden=true;input.setAttribute('aria-expanded','false');status.textContent='';};
  function choose(person){
   let option=[...select.options].find(o=>o.value===String(person.id));
   if(!option){option=new Option(person.email ? person.name+' ('+person.email+')' : person.name,person.id);select.add(option);}
   select.value=String(person.id);input.value=option.textContent.trim();input.setCustomValidity('');status.textContent='';close();select.dispatchEvent(new Event('change',{bubbles:true}));
  }
  function focusWithoutSearch(){const alreadyFocused=document.activeElement===input;suppressFocusSearch=true;input.focus();if(alreadyFocused)suppressFocusSearch=false;}
  function item(name,email,run,remote=false){const b=document.createElement('button');b.type='button';b.className='directory-person';const strong=document.createElement('strong');const match=name.match(/^(.+?)\s*\(([^)]+@[^)]+)\)$/);strong.textContent=match?match[1].trim():name;if(!email&&match)email=match[2];const detail=document.createElement('small');detail.textContent=(remote?'Add from Microsoft - ':'')+(email||'System user');const avatar=document.createElement('span');avatar.className='directory-person-avatar';avatar.setAttribute('aria-hidden','true');const parts=strong.textContent.trim().split(/\s+/).filter(Boolean);const initials=parts.length>1?parts.slice(0,2).map(part=>part.charAt(0)).join(''):((parts[0]||'').slice(0,2));avatar.textContent=initials.toUpperCase();const copy=document.createElement('span');copy.className='directory-person-copy';copy.append(strong,detail);b.append(avatar,copy);b.addEventListener('click',run);list.append(b);}
  async function search(){
   if(busy)return;const selectedLabel=select.selectedOptions[0]?.textContent.trim()||'';const term=select.value&&input.value.trim()===selectedLabel?'':input.value.trim(),current=++version;controller?.abort();controller=new AbortController();list.replaceChildren();
   if(term.length<2&&!select.dataset.membersOnly){close();status.textContent=term?'Type at least 2 characters to search.':'';return;}
   list.hidden=false;input.setAttribute('aria-expanded','true');positionList();
   const memberConfig=document.getElementById('task-assignment-config');let memberIds=[];try{memberIds=JSON.parse(memberConfig?.dataset.members||'[]').map(Number);}catch{}
   const local=[...select.options].filter(o=>o.value&&o.textContent.toLowerCase().includes(term.toLowerCase())&&(!select.dataset.membersOnly||term.length>=2||memberIds.includes(Number(o.value)))).sort((a,b)=>Number(memberIds.includes(Number(b.value)))-Number(memberIds.includes(Number(a.value))));
   local.slice(0,12).forEach(o=>item(o.textContent,'',()=>choose({id:o.value,name:o.textContent})));
   status.textContent=term.length<2?'':'Searching Microsoft...';
   if(term.length<2)return;
   try{
    const url=new URL(config.dataset.searchUrl);url.searchParams.set('q',term);url.searchParams.set('ajax','1');url.searchParams.set('directory_project_id',select.dataset.directoryProject);
    const response=await fetch(url,{headers:{Accept:'application/json'},signal:controller.signal});const data=await response.json();if(current!==version)return;
    if(!response.ok||data.error)throw Error(data.error?'Microsoft search is unavailable. Existing people are still available.':data.message||'Directory search unavailable.');
    let added=0;
    for(const person of data.results||[]){
     if(!person.email||local.some(o=>o.textContent.toLowerCase().includes(person.email.toLowerCase())))continue;
     item(person.displayName,person.email,async()=>{
      if(busy)return;busy=true;input.disabled=true;list.querySelectorAll('button').forEach(b=>b.disabled=true);status.textContent='Saving person to the system...';let added=false;
      try{const r=await fetch(config.dataset.addUrl,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({azure_id:person.id,directory_project_id:select.dataset.directoryProject})});const result=await r.json();if(!r.ok||!result.user)throw Error(result.message||'Could not add this person.');if(result.user.account_status!=='active'||result.user.system_role!=='user')throw Error('Choose an active project user.');choose(result.user);added=true;}
      catch(error){status.textContent=error.message;list.querySelectorAll('button').forEach(b=>b.disabled=false);}
      finally{busy=false;input.disabled=false;focusWithoutSearch();if(added)status.textContent='Added to the system and selected. Complete the form to finish assigning.';}
     },true);added++;
    }
    status.textContent=local.length||added?'Choose a person. Microsoft selections are saved to the system.':'No people found. Try another name or email.';
   }catch(error){if(error.name!=='AbortError'&&current===version)status.textContent=error.message;}
  }
  input.addEventListener('focus',()=>{if(suppressFocusSearch){suppressFocusSearch=false;return;}search();});
  input.addEventListener('click',()=>{if(list.hidden)search();});
  input.addEventListener('input',()=>{select.value='';const term=input.value.trim();input.setCustomValidity(!required && !term?'':'Select a person from the results.');clearTimeout(timer);controller?.abort();version++;if(term.length<2&&!select.dataset.membersOnly){list.replaceChildren();close();status.textContent=term?'Type at least 2 characters to search.':'';return;}timer=setTimeout(search,300);});
  input.addEventListener('keydown',e=>{if(e.key==='Escape'){close();e.stopPropagation();}if(e.key==='ArrowDown'){e.preventDefault();list.querySelector('button')?.focus();}});
  list.addEventListener('keydown',e=>{const buttons=[...list.querySelectorAll('button')],i=buttons.indexOf(document.activeElement);if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();buttons[(i+(e.key==='ArrowDown'?1:-1)+buttons.length)%buttons.length]?.focus();}if(e.key==='Escape'){close();input.focus();}});
  document.addEventListener('pointerdown',e=>{if(!wrap.contains(e.target)&&!list.contains(e.target)){close();controller?.abort();version++;}});
  if(wasFocused)input.focus();
 }
 function scan(root){root.querySelectorAll('select[data-directory-picker]').forEach(init);}
 scan(document);new MutationObserver(records=>{for(const record of records)for(const node of record.addedNodes)if(node.nodeType===1){if(node.matches('select[data-directory-picker]'))init(node);scan(node);}}).observe(document.body,{childList:true,subtree:true});
})();
