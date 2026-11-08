const fs = require('node:fs');
const path = require('node:path');
(async () => {
  const root = __dirname;
  let target;
  for(let attempt=0;attempt<30;attempt++){
    try { target=await (await fetch('http://127.0.0.1:9223/json/new',{method:'PUT'})).json();break; }
    catch(error){if(attempt===29)throw error;await new Promise(r=>setTimeout(r,200));}
  }
  const ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((resolve,reject) => {ws.onopen=resolve;ws.onerror=reject;});
  let id=0; const pending=new Map();
  ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id&&pending.has(m.id)){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(new Error(m.error.message)):p.resolve(m.result);}};
  const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}));});
  await send('Page.enable'); await send('Runtime.enable');
  const evaluate=async(expression)=>(await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true})).result.value;
  const report=[];
  const files=fs.readdirSync(root).filter(n=>n.endsWith('.html')&&!n.includes('probe')&&!n.includes('fragment')&&!n.includes('?'));
  for(const file of files.filter(file=>!process.env.REVIEW_PAGES || process.env.REVIEW_PAGES.split(',').some(name=>file.includes(name)))) {
    if(file.includes('-panel'))continue;
    for(const width of (process.env.REVIEW_WIDTHS ? process.env.REVIEW_WIDTHS.split(',').map(Number) : [1440,390])) {
      await send('Emulation.setDeviceMetricsOverride',{width,height:width===390?844:1100,deviceScaleFactor:1,mobile:width===390});
      await send('Page.navigate',{url:'file:///'+path.join(root,file).replaceAll('\\','/')});
      for(let i=0;i<50;i++){await new Promise(r=>setTimeout(r,60));if(await evaluate('document.readyState==="complete"'))break;}
      await new Promise(r=>setTimeout(r,150));
      const result=await evaluate('('+ (() => {
        const failures=[];
        const primaryLinks=[...document.querySelectorAll('nav[aria-label="Main navigation"]>a')];
        if(primaryLinks.length){
          const isAdmin=primaryLinks[0].textContent.trim()==='Dashboard';
          if(primaryLinks.length!==(isAdmin?8:4))failures.push('primary navigation count');
          if(primaryLinks.some(link=>/^(Reports|Portfolio Timeline|Task Monitor|Team Workload)$/.test(link.textContent.trim())))failures.push('standalone navigation clutter');
          if(primaryLinks.filter(link=>link.classList.contains('active')).length>1)failures.push('multiple active destinations');
        }
        if(document.documentElement.scrollWidth>innerWidth+2)failures.push('page overflow '+document.documentElement.scrollWidth+'/'+innerWidth);
        const menu=document.querySelector('[data-open-menu]');
        if(innerWidth<600&&menu){
          menu.click();
          if(!document.body.classList.contains('menu-open'))failures.push('menu did not open');
          document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
          if(document.body.classList.contains('menu-open'))failures.push('menu did not close');
        }
        const profile=document.getElementById('user-profile-btn');
        if(profile){profile.click();if(profile.getAttribute('aria-expanded')!=='true')failures.push('profile state');document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));}
        const search=document.querySelector('[data-task-search]');
        if(search){
          search.value='__no_matching_task__';search.dispatchEvent(new Event('input',{bubbles:true}));
          if(!document.querySelector('[data-task-no-results]')||document.querySelector('[data-task-no-results]').hidden)failures.push('empty task filter');
          if([...document.querySelectorAll('.project-task-row')].some(row=>!row.hidden))failures.push('task search mismatch');
          document.querySelector('[data-clear-task-filters]').click();
          if([...document.querySelectorAll('.project-task-row')].some(row=>row.hidden))failures.push('task filter reset');
          const add=document.querySelector('button[data-add-mode="main"]');
          if(add){add.click();const dialog=document.getElementById('add-task-dialog');if(!dialog.open)failures.push('direct task action');else {
            const parent=dialog.querySelector('[name="parent_task_id"]');
            if(!parent?.closest('details'))failures.push('progressive task options');
            dialog.close();
          }}
          search.blur();
        }
        if(document.getElementById('project-workspace')){
          const tabs=[...document.querySelectorAll('button[data-tab-target]')];
          tabs.forEach(button=>{
            button.click();
            const panel=document.getElementById('tab-'+button.dataset.tabTarget);
            if(!panel?.classList.contains('active'))failures.push('workspace tab '+button.dataset.tabTarget);
          });
          document.querySelector('button[data-tab-target="tasks"]')?.click();
          const edit=document.getElementById('open-bulk-edit-btn');
          if(edit){edit.click();const dialog=document.getElementById('bulk-edit-tasks-dialog');if(!dialog?.open)failures.push('bulk edit action');else dialog.close();}
        }
        const projectTabs=[...document.querySelectorAll('.project-tabs-nav [data-tab-target]')];
        if(projectTabs.length>2){
          if(projectTabs.map(button=>button.dataset.tabTarget).join(',')!=='tasks,gantt,team,risks,info')failures.push('five clear project destinations');
          if(!document.querySelector('#tab-info #project-milestones') || !document.querySelector('#tab-risks #project-changes'))failures.push('grouped project sections');
          projectTabs[0].focus();projectTabs[0].dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true}));
          if(!projectTabs[1].classList.contains('active'))failures.push('keyboard project tabs');
          projectTabs[0].click();
        }
        const taskForm=document.getElementById('task-filter-form');
        if(taskForm){
          const moreFilters=taskForm.querySelector('details');
          const previousOpen=moreFilters.open;
          moreFilters.querySelector('summary').click();
          if(moreFilters.open===previousOpen)failures.push('more filters toggle');
          moreFilters.open=previousOpen;
          const select=taskForm.querySelector('select[name="project_id"]');
          const search=taskForm.querySelector('input[name="q"]');
          const oldSearch=search.value;
          const oldProject=select.value;
          let submitted=false,submittedValues;
          const stop=event=>{event.preventDefault();submitted=true;submittedValues=new FormData(taskForm);};
          taskForm.addEventListener('submit',stop);
          search.value='review search';
          if(select.options.length>1)select.value=select.options[1].value;
          select.dispatchEvent(new Event('change',{bubbles:true}));
          if(!submitted)failures.push('project filter autosubmit');
          if(submittedValues?.get('q')!=='review search')failures.push('search preservation');
          if(!submittedValues?.has('filter')||submittedValues.getAll('filter').length!==1)failures.push('focus preservation');
          select.value=oldProject;search.value=oldSearch;
          taskForm.removeEventListener('submit',stop);
        }
        const password=document.getElementById('login-password');
        if(password){
          const toggle=document.querySelector('[data-toggle-password="login-password"]');
          if(!password.labels.length || !document.getElementById('email')?.labels.length)failures.push('login visible labels');
          toggle.click();if(password.type!=='text')failures.push('show password');
          toggle.click();if(password.type!=='password')failures.push('hide password');
          const help=document.querySelector('.signin-help');
          help.querySelector('summary').click();if(!help.open)failures.push('login help');
          help.open=false;
        }
        for(const dialogId of ['user-dialog','add-subsidiary-dialog']){
          const trigger=document.querySelector('[data-show-dialog="'+dialogId+'"]');
          const dialog=document.getElementById(dialogId);
          if(trigger&&dialog){
            trigger.click();
            if(!dialog.open)failures.push('open '+dialogId);
            if(dialog.open){
              const rect=dialog.getBoundingClientRect();
              if(rect.width>innerWidth+2)failures.push('dialog width '+dialogId);
              dialog.close();
            }
          }
        }
        const projectList=document.getElementById('projects-list-container');
        if(projectList){
          const gridButton=document.getElementById('view-btn-grid'),listButton=document.getElementById('view-btn-list');
          gridButton.click();
          if(getComputedStyle(document.getElementById('projects-grid-container')).display==='none'||gridButton.getAttribute('aria-pressed')!=='true')failures.push('project grid view');
          listButton.click();
          if(getComputedStyle(projectList).display==='none'||listButton.getAttribute('aria-pressed')!=='true')failures.push('project list view');
          if(innerWidth>=1200){
            const wrap=projectList.querySelector('.table-wrap');
            if(wrap.scrollWidth>wrap.clientWidth+2)failures.push('desktop project table overflow');
            if(projectList.querySelector('tbody tr td')?.getBoundingClientRect().width<240)failures.push('project name column too narrow');
          }
          const dots=projectList.querySelector('.btn-dots');
          if(dots){
            dots.click();
            const panel=document.querySelector('.actions-dropdown-panel.show');
            if(!panel)failures.push('project actions menu');
            else {
              const rect=panel.getBoundingClientRect();
              if(rect.right>innerWidth+1||rect.bottom>innerHeight+1)failures.push('project menu bounds');
            }
            document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
            if(document.querySelector('.actions-dropdown-panel.show'))failures.push('project actions Escape');
          }
        }
        if(innerWidth>=1200){
          document.querySelectorAll('.project-tasks-full-table .uwt-task-title-link').forEach(button=>{
            if(button.scrollWidth>button.clientWidth+2)failures.push('task title overflow');
          });
        }
        const projectHeaderEdit=document.querySelector('.pw-head-actions [data-show-dialog="project-edit-dialog"]');
        if(projectHeaderEdit){
          projectHeaderEdit.click();
          const editDialog=document.getElementById('project-edit-dialog');
          if(!editDialog?.open)failures.push('direct project edit');
          else {
            const detailsForm=editDialog.querySelector('#project-details-edit-form');
            const saveButton=editDialog.querySelector('button[form="project-details-edit-form"]');
            if(!detailsForm || saveButton?.form!==detailsForm)failures.push('project editor save association');
            if(detailsForm && !detailsForm.checkValidity())failures.push('project editor initial fields');
            if(detailsForm){
              const data=new FormData(detailsForm);
              for(const key of ['name','description','starts_on','ends_on','status','_project_edit'])if(!data.has(key))failures.push('project editor field '+key);
              if(data.get('_project_edit')!=='1')failures.push('editor recovery marker');
              const start=detailsForm.elements.namedItem('starts_on'),end=detailsForm.elements.namedItem('ends_on');
              const initial=start.value;start.value='2026-01-01';start.dispatchEvent(new Event('change',{bubbles:true}));
              if(end.min!=='2026-01-01')failures.push('editor date constraint');
              start.value=initial;start.dispatchEvent(new Event('change',{bubbles:true}));
            }
            const templateSelect=editDialog.querySelector('[name="project_template_id"]');
            if(!templateSelect || templateSelect.form!==detailsForm)failures.push('editable template association');
            const addPerson=editDialog.querySelector('[data-editor-add-member]');
            if(addPerson){
              const before=editDialog.querySelectorAll('[data-editor-member]').length;
              addPerson.click();
              const rows=editDialog.querySelectorAll('[data-editor-member]');
              if(rows.length!==before+1)failures.push('add team person');
              const last=rows[rows.length-1];
              if(last.querySelector('[data-member-user]').form!==detailsForm || last.querySelector('[data-member-role]').form!==detailsForm)failures.push('team save association');
              last.querySelector('[data-editor-remove-member]').click();
              if(editDialog.querySelectorAll('[data-editor-member]').length!==before)failures.push('remove team person');
            }
            const setup=editDialog.querySelector('[data-project-setup]');
            if(!setup || !['template','blank'].includes(setup.dataset.projectSetup))failures.push('project setup context');
            const team=editDialog.querySelector('[data-editor-team-count]');
            if(team){
              const people=[...team.querySelectorAll('[data-editor-person]')];
              if(people.length!==Number(team.dataset.editorTeamCount))failures.push('team roster count');
              if(new Set(people.map(p=>p.dataset.editorPerson)).size!==people.length)failures.push('duplicate team person');
            }
            const rect=editDialog.getBoundingClientRect();
            if(rect.width>innerWidth+2 || rect.height>innerHeight+2)failures.push('editor viewport bounds');
            const footer=editDialog.querySelector('.project-editor-footer').getBoundingClientRect();
            if(footer.bottom>innerHeight+1)failures.push('editor footer visibility');
            const manage=editDialog.querySelector('[data-project-edit-team]');
            if(manage){
              manage.click();
              if(editDialog.open || !document.getElementById('tab-team')?.classList.contains('active'))failures.push('manage team handoff');
              document.querySelector('button[data-tab-target="tasks"]')?.click();
            } else editDialog.close();
          }
        }
        const workspaceTabs=document.querySelector('.project-tabs-nav');
        if(workspaceTabs){
          if(workspaceTabs.querySelector('[data-tab-target="files"]')||[...workspaceTabs.querySelectorAll('a')].some(a=>a.textContent.includes('Calendar')))failures.push('removed workspace tabs');
        }
        const more=document.querySelector('[data-calendar-more]');
        if(more && innerWidth>600) {
          const stack=more.closest('.calendar-event-stack');
          const items=[...stack.querySelectorAll('.cal-item')];
          if(items.length>3&&!items.slice(3).every(item=>item.hidden))failures.push('calendar collapsed state');
          more.click();
          if(items.some(item=>item.hidden))failures.push('calendar expansion');
          more.click();
        }
        if(innerWidth<=600 && document.querySelector('.calendar-grid-body')) {
          const header=document.querySelector('.calendar-grid-header');
          if(header && getComputedStyle(header).display!=='none')failures.push('mobile agenda heading');
          const dates=[...document.querySelectorAll('.calendar-agenda-date')];
          if(!dates.length)failures.push('mobile agenda dates');
        }
        return {viewport:innerWidth,scrollWidth:document.documentElement.scrollWidth,failures};
      }).toString()+')()');
      const name=file.slice(0,-5)+(width===390?'-mobile':width===1440?'':'-'+width);
      const hasEditor=await evaluate('Boolean(document.querySelector(".pw-head-actions [data-show-dialog=\\"project-edit-dialog\\"]"))');
      if(hasEditor){
        await evaluate('document.querySelector(".pw-head-actions [data-show-dialog=\\"project-edit-dialog\\"]").click()');
        await new Promise(r=>setTimeout(r,80));
        const editorShot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        fs.writeFileSync(path.join(root,name+'-edit-dialog.png'),Buffer.from(editorShot.data,'base64'));
        await evaluate('document.getElementById("project-edit-dialog").close()');
      }
      const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
      fs.writeFileSync(path.join(root,name+'.png'),Buffer.from(shot.data,'base64'));
      report.push({page:file,width,...result});
      console.log((result.failures.length?'FAIL':'PASS')+' '+width+' '+file+' '+result.failures.join(', '));
    }
  }
  fs.writeFileSync(path.join(root,'browser-review.json'),JSON.stringify(report,null,2));
  await send('Page.close'); ws.close();
})().catch(e=>{console.error(e);process.exitCode=1;});