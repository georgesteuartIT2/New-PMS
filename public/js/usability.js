document.addEventListener('DOMContentLoaded', () => {
  const menuButton = document.querySelector('[data-open-menu]');
  const profileButton = document.getElementById('user-profile-btn');
  const profilePanel = document.getElementById('user-profile-panel');
  const syncMenu = () => menuButton?.setAttribute('aria-expanded', String(document.body.classList.contains('menu-open')));
  menuButton?.addEventListener('click', () => {
    syncMenu();
    document.querySelector('#sidebar a')?.focus();
  });
  document.querySelector('[data-close-menu]')?.addEventListener('click', syncMenu);
  const syncProfile = () => profileButton?.setAttribute('aria-expanded', String(profilePanel?.classList.contains('open') || false));
  profileButton?.addEventListener('click', syncProfile);
  document.addEventListener('click', syncProfile);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      if (profilePanel?.classList.contains('open')) {
        profilePanel.classList.remove('open');
        profileButton?.focus();
      }
      if (menuButton?.getAttribute('aria-expanded') === 'true') menuButton.focus();
      syncMenu(); syncProfile();
    }
  });
  document.querySelectorAll('.nav > a.active').forEach(link => link.setAttribute('aria-current', 'page'));
});
document.addEventListener('DOMContentLoaded', () => {
  const nav = document.querySelector('.project-tabs-nav');
  if (!nav) return;
  const buttons = [...nav.querySelectorAll('button[data-tab-target]')];
  const panels = [...document.querySelectorAll('.pw-tab-panel')];
  const sync = () => {
    buttons.forEach(button => {
      const selected = button.classList.contains('active');
      button.setAttribute('aria-pressed', String(selected));
      button.setAttribute('aria-controls', 'tab-' + button.dataset.tabTarget);
    });
    panels.forEach(panel => {
      panel.setAttribute('aria-hidden', String(!panel.classList.contains('active')));
    });
  };
  nav.addEventListener('click', () => queueMicrotask(sync));
  sync();
});
document.addEventListener('DOMContentLoaded', () => {
  // Filter only task records already authorized and rendered by the server.
  const search = document.querySelector('[data-task-search]');
  const filter = document.querySelector('[data-task-filter]');
  const rows = [...document.querySelectorAll('.project-tasks-full-table .project-task-row')];
  const count = document.querySelector('[data-task-result-count]');
  const empty = document.querySelector('[data-task-no-results]');
  const rowById = new Map(rows.map(row => [row.dataset.taskId, row]));
  let filtering = false;
  const previousDisplay = new Map();
  const apply = () => {
    const query = search.value.trim().toLocaleLowerCase();
    const status = filter.value;
    const active = Boolean(query || status !== 'all');
    if (active && !filtering) rows.forEach(row => previousDisplay.set(row, row.style.display));
    const matches = new Set(rows.filter(row => {
      const state = row.dataset.taskStatus;
      return (row.dataset.taskSearchText || '').toLocaleLowerCase().includes(query) &&
        (status === 'all' || (status === 'overdue' && row.dataset.isOverdue === '1') ||
        (status === 'completed' && state === 'completed') ||
        (status === 'open' && state !== 'completed'));
    }));
    const visible = new Set(matches);
    // Retain ancestors so a matching subtask still has its project-plan context.
    matches.forEach(row => {
      const visited = new Set();
      while (row.dataset.parent && !visited.has(row.dataset.parent)) {
        visited.add(row.dataset.parent);
        row = rowById.get(row.dataset.parent);
        if (!row) break;
        visible.add(row);
      }
    });
    rows.forEach(row => {
      row.hidden = active && !visible.has(row);
      if (active) row.style.display = '';
      else if (filtering) row.style.display = previousDisplay.get(row) || '';
    });
    document.querySelector('.project-tasks-full-table')?.setAttribute('data-filtering', String(active));
    filtering = active;
    if (count) count.textContent = active ? matches.size + ' of ' + rows.length + ' tasks' : rows.length + ' tasks';
    const clearButton = document.querySelector('[data-clear-task-filters]');
    if (clearButton) clearButton.hidden = !active;
    if (empty) empty.hidden = matches.size > 0;
  };
  if (search && filter) {
    search.addEventListener('input', apply);
    filter.addEventListener('change', apply);
    document.querySelector('[data-clear-task-filters]')?.addEventListener('click', () => {
      search.value = ''; filter.value = 'all'; apply(); search.focus();
    });
    apply();
  }
  // Text labels for search/filter controls missing a visible associated label.
  document.querySelectorAll('input[placeholder]').forEach(input => {
    if (!input.getAttribute('aria-label') && !input.labels?.length)
      input.setAttribute('aria-label', input.placeholder);
  });
  // Clickable template rows also work without a pointing device.
  document.querySelectorAll('tr[onclick]').forEach(row => {
    row.tabIndex = 0;
    const name = row.querySelector('.template-name-link')?.textContent.trim();
    if (name) row.setAttribute('aria-label', 'Open template: ' + name);
    row.addEventListener('keydown', event => {
      if (event.target !== row || (event.key !== 'Enter' && event.key !== ' ')) return;
      event.preventDefault(); row.click();
    });
  });
});
document.addEventListener('DOMContentLoaded', () => {
  const narrow = matchMedia('(max-width:600px)');
  const stacks = [...document.querySelectorAll('.calendar-event-stack')];
  const syncCalendar = () => stacks.forEach(stack => {
    const events = [...stack.querySelectorAll('.cal-item')];
    const button = stack.querySelector('[data-calendar-more]');
    const expanded = button?.getAttribute('aria-expanded') === 'true';
    events.forEach((item,index) => item.hidden = !narrow.matches && !expanded && index >= 3);
    if(button) button.textContent = expanded ? 'Show fewer' : '+' + Math.max(0,events.length-3) + ' more';
    const cell = stack.closest('.calendar-day-cell');
    if(cell)cell.dataset.hasEvents = String(events.length>0);
  });
  stacks.forEach(stack => stack.querySelector('[data-calendar-more]')?.addEventListener('click', event => {
    event.currentTarget.setAttribute('aria-expanded',String(event.currentTarget.getAttribute('aria-expanded')!=='true'));
    syncCalendar();
  }));
  narrow.addEventListener('change',syncCalendar);
  syncCalendar();
  const body=document.querySelector('.calendar-grid-body');
  if(body && !body.querySelector('.calendar-day-cell:not(.off-month)[data-has-events="true"]')) {
    const empty=document.createElement('p');empty.className='calendar-empty-agenda';
    empty.textContent='No tasks or meetings for this month. Choose another month or change your filters.';
    body.after(empty);
  }
  document.addEventListener('keydown', event => {
    if(event.key!=='Tab')return;
    const drawer=document.getElementById('task-side-panel');
    if(!drawer || drawer.hidden)return;
    const controls=[...drawer.querySelectorAll('a[href],button,input,select,textarea,[tabindex="0"]')].filter(el=>!el.disabled&&el.getClientRects().length);
    if(!controls.length)return;
    const first=controls[0],last=controls[controls.length-1];
    if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
    else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
  });
});
document.addEventListener('DOMContentLoaded', () => {
  const names={type:'Report type',status:'Status',subsidiary_id:'Subsidiary',pm_user_id:'Project manager',project_id:'Project',user_id:'Assignee',scope:'My project role',category:'Category',filter:'Filter'};
  document.querySelectorAll('select[name]').forEach(select => {
    if(!select.labels?.length && !select.getAttribute('aria-label') && names[select.name])
      select.setAttribute('aria-label',names[select.name]);
  });
});