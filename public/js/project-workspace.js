/* GS ProjectFlow V3: Integrated Project Workspace, Timeline & Drawer */
(function () {
  'use strict';

  function escapeHtml(str) { return String(str || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }
  document.addEventListener('keydown', event => {if(event.key === 'Escape') closeAllDropdowns();});
  // 2. Dropdown Menus (Add Task, Import/Export, Project Actions)
  function closeAllDropdowns() {
    document.querySelectorAll('.pw-dropdown-menu').forEach(menu => menu.style.display = 'none');
    document.querySelectorAll('.pw-dropdown-toggle').forEach(button=>button.setAttribute('aria-expanded','false'));
  }

  document.addEventListener('click', event => {
    const toggle = event.target.closest('.pw-dropdown-toggle');
    if (toggle) {
      event.preventDefault();
      event.stopPropagation();
      const wrap = toggle.closest('.pw-dropdown-wrap');
      const menu = wrap?.querySelector('.pw-dropdown-menu');
      if (menu) {
        const isShown = menu.style.display === 'block';
        closeAllDropdowns();
        menu.style.display = isShown ? 'none' : 'block';
        toggle.setAttribute('aria-expanded',String(!isShown));
      }
      return;
    }

    if (event.target.closest('.pw-dropdown-item') || !event.target.closest('.pw-dropdown-menu')) {
      closeAllDropdowns();
    }
  });

  // 3. In-page Tab Switching
  const tabButtons = document.querySelectorAll('.project-tab-btn');
  const tabPanels = document.querySelectorAll('.pw-tab-panel');

  function switchTab(targetId) {
    if (!targetId) return;
    const requestedId = targetId.replace('#', '');
    const cleanId = ({milestones:'info',changes:'risks'})[requestedId] || requestedId;
    if (!document.getElementById('tab-' + cleanId)) return;
    tabButtons.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tabTarget === cleanId);
    });
    tabPanels.forEach(p => {
      p.classList.toggle('active', p.id === 'tab-' + cleanId);
    });
    if (cleanId === 'gantt' && typeof renderTimeline === 'function') {
      setTimeout(renderTimeline, 60);
    }
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, null, '#' + cleanId);
    }
  }

  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tabTarget));
  });

  if (window.location.hash) {
    const initialTab = window.location.hash.replace('#', '');
    if (document.getElementById('tab-' + initialTab) || ['milestones','changes'].includes(initialTab)) {
      switchTab(initialTab);
    }
  }

  // 4. Collapsible Parent-Child Task Hierarchy
  document.addEventListener('click', event => {
    const toggle = event.target.closest('.uwt-toggle-btn');
    if (!toggle) return;
    event.preventDefault();
    event.stopPropagation();
    const taskId = toggle.dataset.parentTask;
    const isExpanded = toggle.getAttribute('aria-expanded') !== 'false';
    const newState = !isExpanded;

    toggle.setAttribute('aria-expanded', String(newState));
    toggle.textContent = newState ? '⌵' : '›';

    // Find direct and indirect children
    const childRows = document.querySelectorAll(`[data-parent="${taskId}"]`);
    childRows.forEach(row => {
      row.classList.toggle('collapsed-child', !newState);
    });
  });

  // 5. Unified Interactive Timeline Grid & Gantt
  const tasks = window.gsProjectTimeline;
  const grid = document.getElementById('unified-workplan-grid');
  const monthBand = document.getElementById('uwt-month-band');
  const subBand = document.getElementById('uwt-sub-band');
  const scaleButtons = document.querySelectorAll('.view-scale-btn');

  if (!grid || !Array.isArray(tasks) || !tasks.length) return;

  const DAY = 86400000;
  const dayStamp = value => {
    const d = new Date(value);
    return Date.UTC(d.getFullYear(), d.getMonth(), d.getDate());
  };

  const validTasks = tasks.filter(t => t.start && t.end);
  if (!validTasks.length) return;

  const start = Math.min(...validTasks.map(t => dayStamp(t.start)));
  const end = Math.max(...validTasks.map(t => dayStamp(t.end)));
  const anchor = new Date(start);
  const finish = new Date(end);
  const monthStart = new Date(Date.UTC(anchor.getUTCFullYear(), anchor.getUTCMonth(), 1));
  const weekStart = new Date(start);
  weekStart.setUTCDate(weekStart.getUTCDate() - (weekStart.getUTCDay() + 6) % 7);

  let currentScale = 'week';

  const units = {
    day: {
      size: 44,
      anchor: start,
      step: DAY,
      label: time => new Date(time).toLocaleDateString('en-GB', { timeZone: 'UTC', day: '2-digit', month: 'short' })
    },
    week: {
      size: 110,
      anchor: weekStart.getTime(),
      step: DAY * 7,
      label: (time, index) => {
        const d1 = new Date(time);
        const d2 = new Date(time + 6 * DAY);
        const startStr = d1.toLocaleDateString('en-GB', { timeZone: 'UTC', day: '2-digit' });
        const endStr = d2.toLocaleDateString('en-GB', { timeZone: 'UTC', day: '2-digit', month: 'short' });
        return `W${index + 1} ${startStr} - ${endStr}`;
      }
    },
    month: {
      size: 140,
      anchor: monthStart.getTime(),
      step: null,
      label: time => new Date(time).toLocaleDateString('en-GB', { timeZone: 'UTC', month: 'short', year: 'numeric' })
    }
  };

  function renderTimeline() {
    const unit = units[currentScale];
    const base = unit.anchor;
    const count = currentScale === 'month'
      ? (finish.getUTCFullYear() - monthStart.getUTCFullYear()) * 12 + finish.getUTCMonth() - monthStart.getUTCMonth() + 1
      : Math.ceil((end - base + DAY) / unit.step);

    const slots = Math.max(3, Math.min(1000, count));
    const width = slots * unit.size;
    grid.style.setProperty('--timeline-px', width + 'px');

    // Render Month Banner
    if (monthBand) {
      const monthNames = [];
      const dCursor = new Date(start);
      const dEnd = new Date(end);
      while (dCursor <= dEnd || monthNames.length === 0) {
        const mStr = dCursor.toLocaleDateString('en-GB', { timeZone: 'UTC', month: 'short', year: 'numeric' });
        if (!monthNames.includes(mStr)) monthNames.push(mStr);
        dCursor.setUTCMonth(dCursor.getUTCMonth() + 1);
      }
      monthBand.textContent = monthNames.join('  ·  ');
    }

    // Render Sub-band slots
    if (subBand) {
      subBand.replaceChildren();
      for (let i = 0; i < slots; i++) {
        const time = currentScale === 'month'
          ? Date.UTC(monthStart.getUTCFullYear(), monthStart.getUTCMonth() + i, 1)
          : base + i * unit.step;
        const slotEl = document.createElement('div');
        slotEl.className = 'uwt-timeline-sub-slot';
        slotEl.style.width = unit.size + 'px';
        slotEl.textContent = unit.label(time, i);
        subBand.appendChild(slotEl);
      }
    }

    const position = t => {
      if (currentScale !== 'month') {
        return (t - base) / unit.step * unit.size;
      }
      const d = new Date(t);
      const mIdx = (d.getUTCFullYear() - monthStart.getUTCFullYear()) * 12 + d.getUTCMonth() - monthStart.getUTCMonth();
      const totalDays = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 0)).getUTCDate();
      return (mIdx + (d.getUTCDate() - 1) / totalDays) * unit.size;
    };

    // Position Today vertical line
    const todayStamp = dayStamp(new Date());
    const todayPos = position(todayStamp);
    const todayLines = grid.querySelectorAll('.uwt-today-line');
    const todayBadges = grid.querySelectorAll('.uwt-today-badge');

    if (todayPos >= 0 && todayPos <= width) {
      todayLines.forEach(l => {
        l.style.display = 'block';
        l.style.left = todayPos + 'px';
      });
      todayBadges.forEach(b => {
        b.style.display = 'block';
        b.style.left = todayPos + 'px';
      });
    } else {
      todayLines.forEach(l => l.style.display = 'none');
      todayBadges.forEach(b => b.style.display = 'none');
    }

    // Render Gantt Bars on each task track
    for (const task of validTasks) {
      const cell = grid.querySelector(`[data-uwt-track="${task.id}"]`);
      if (!cell) continue;

      // Keep today line
      const existingToday = cell.querySelector('.uwt-today-line');
      cell.replaceChildren();
      if (existingToday) cell.appendChild(existingToday);

      const taskStart = dayStamp(task.start);
      const taskEnd = dayStamp(task.end);
      const left = Math.max(0, position(taskStart));
      const right = position(taskEnd + DAY);
      const barWidth = Math.max(8, Math.min(width - left, right - left));

      const bar = document.createElement('button');
      bar.type = 'button';
      bar.className = 'uwt-gantt-bar';
      if (task.progress === 100) {
        bar.classList.add('is-completed');
      } else if (task.progress === 0) {
        bar.classList.add('is-todo');
      }
      if (task.critical) {
        bar.classList.add('is-critical');
      }

      bar.dataset.taskLocation = grid.querySelector(`[data-uwt-task-link="${task.id}"]`)?.dataset.taskLocation || '';
      bar.title = `${task.title || 'Task'} · ${task.progress}% complete`;
      bar.setAttribute('aria-label', `View ${task.title || 'Task'} details (${task.progress}% complete)`);
      bar.style.left = left + 'px';
      bar.style.width = barWidth + 'px';

      if (task.progress > 0 && task.progress < 100) {
        const fill = document.createElement('span');
        fill.className = 'uwt-gantt-bar-fill';
        fill.style.width = task.progress + '%';
        bar.appendChild(fill);
      }

      cell.appendChild(bar);
    }
  }

  // Scale buttons
  scaleButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      scaleButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentScale = btn.dataset.scale || 'week';
      document.addEventListener('task-progress-saved',event=>{
    for(const updated of event.detail.tasks||[]){const task=tasks.find(item=>Number(item.id)===Number(updated.id));if(task)task.progress=updated.progress;}
    renderTimeline();
  });
  renderTimeline();
    });
  });

  renderTimeline();

  // Highlight critical path toggle
  document.getElementById('highlight-critical')?.addEventListener('change', event => {
    grid.classList.toggle('show-critical', event.target.checked);
  });

  // 7. Bulk Task Editing Modal & Row Modification Tracking
  const bulkDialog = document.getElementById('bulk-edit-tasks-dialog');
  const bulkForm = document.getElementById('bulk-edit-form');
  const bulkCounter = document.getElementById('bulk-modified-counter');
  const initialRowValues = new Map();

  function snapshotBulkRow(row) {
    const inputs = row.querySelectorAll('.bulk-field-track');
    const data = {};
    inputs.forEach(input => {
      data[input.dataset.field || input.name] = input.value;
    });
    // Record current dependencies
    const depChips = row.querySelectorAll('.bulk-dep-chip');
    data.deps = Array.from(depChips).map(c => c.dataset.depId).sort().join(',');
    return data;
  }

  function initBulkSnapshot() {
    if (!bulkDialog) return;
    initialRowValues.clear();
    const rows = bulkDialog.querySelectorAll('.bulk-task-row');
    rows.forEach(row => {
      const taskId = row.dataset.taskId;
      initialRowValues.set(taskId, snapshotBulkRow(row));
      row.classList.remove('row-modified');
    });
    updateBulkModifiedCounter();
  }

  function updateBulkModifiedCounter() {
    if (!bulkCounter || !bulkDialog) return;
    const modifiedCount = bulkDialog.querySelectorAll('.bulk-task-row.row-modified').length;
    bulkCounter.textContent = modifiedCount === 1 ? '1 task modified' : `${modifiedCount} tasks modified`;
    bulkCounter.style.color = modifiedCount > 0 ? '#1E293B' : 'var(--muted)';
    bulkCounter.style.fontWeight = modifiedCount > 0 ? '600' : '500';
  }

  function checkRowModified(row) {
    const taskId = row.dataset.taskId;
    const initial = initialRowValues.get(taskId);
    if (!initial) return;
    const current = snapshotBulkRow(row);
    let isDifferent = false;
    for (const key of Object.keys(initial)) {
      if (initial[key] !== current[key]) {
        isDifferent = true;
        break;
      }
    }
    row.classList.toggle('row-modified', isDifferent);
    updateBulkModifiedCounter();
  }

  // Hook bulk modal open
  document.getElementById('open-bulk-edit-btn')?.addEventListener('click', () => {
    initBulkSnapshot();
  });

  // Track field changes in bulk edit
  bulkForm?.addEventListener('input', event => {
    const row = event.target.closest('.bulk-task-row');
    if (!row) return;

    // Status <-> Progress sync
    if (event.target.classList.contains('bulk-progress-input')) {
      const pVal = parseInt(event.target.value, 10);
      const statusSelect = row.querySelector('.bulk-status-select');
      if (statusSelect) {
        if (pVal === 100) {
          statusSelect.value = 'completed';
        } else if (pVal < 100 && statusSelect.value === 'completed') {
          statusSelect.value = 'in_progress';
        }
      }
    }

    checkRowModified(row);
  });

  bulkForm?.addEventListener('change', event => {
    const row = event.target.closest('.bulk-task-row');
    if (!row) return;

    // Status <-> Progress sync
    if (event.target.classList.contains('bulk-status-select')) {
      const sVal = event.target.value;
      const progressInput = row.querySelector('.bulk-progress-input');
      if (progressInput) {
        if (sVal === 'completed') {
          progressInput.value = '100';
        } else if (sVal === 'todo' && progressInput.value === '100') {
          progressInput.value = '0';
        } else if (progressInput.value === '100') {
          progressInput.value = '50';
        }
      }
    }

    // Predecessor dependency add
    if (event.target.classList.contains('bulk-add-dep-select')) {
      const sel = event.target;
      const depId = sel.value;
      if (!depId) return;

      const taskId = row.dataset.taskId;
      const selectedOption = sel.options[sel.selectedIndex];
      const depLabel = selectedOption.dataset.label || selectedOption.text.split('–')[0].trim();
      const chipsContainer = document.getElementById(`bulk-chips-${taskId}`);

      if (chipsContainer && !chipsContainer.querySelector(`[data-dep-id="${depId}"]`)) {
        const chip = document.createElement('span');
        chip.className = 'bulk-dep-chip';
        chip.dataset.depId = depId;
        chip.innerHTML = `
          <span>${escapeHtml(depLabel)}</span>
          <button type="button" class="bulk-remove-dep-btn" title="Remove dependency">×</button>
          <input type="hidden" name="tasks[${taskId}][dependencies][]" value="${depId}">
        `;
        chipsContainer.appendChild(chip);
      }

      sel.value = '';
    }

    checkRowModified(row);
  });

  // Predecessor dependency remove click
  bulkForm?.addEventListener('click', event => {
    const btn = event.target.closest('.bulk-remove-dep-btn');
    if (!btn) return;
    event.preventDefault();
    const chip = btn.closest('.bulk-dep-chip');
    const row = btn.closest('.bulk-task-row');
    if (chip) chip.remove();
    if (row) checkRowModified(row);
  });

  // Cancel with unsaved changes confirmation
  function handleBulkClose(event) {
    const modifiedCount = bulkDialog?.querySelectorAll('.bulk-task-row.row-modified').length || 0;
    if (modifiedCount > 0) {
      if (!confirm(`You have unsaved changes across ${modifiedCount} task(s). Discard all changes?`)) {
        event.preventDefault();
        event.stopPropagation();
        return false;
      }
    }
    return true;
  }

  document.getElementById('bulk-edit-cancel-btn')?.addEventListener('click', event => {
    if (!handleBulkClose(event)) return;
  });

  document.getElementById('bulk-edit-x-btn')?.addEventListener('click', event => {
    if (!handleBulkClose(event)) return;
  });

  // 8. Quick Add Project Member Inline AJAX
  const quickMemberDialog = document.getElementById('quick-add-member-dialog');
  const quickMemberForm = document.getElementById('quick-add-member-form');

  document.getElementById('bulk-open-add-member')?.addEventListener('click', () => {
    if (quickMemberDialog && typeof quickMemberDialog.showModal === 'function') {
      quickMemberDialog.showModal();
    }
  });

  quickMemberForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const submitBtn = document.getElementById('quick-add-member-submit-btn');
    if (submitBtn) submitBtn.disabled = true;

    try {
      const formData = new FormData(quickMemberForm);
      const res = await fetch(quickMemberForm.action, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      });

      if (!res.ok) {
        const errData = await res.json().catch(() => ({}));
        throw new Error(errData.message || 'Failed to add project member.');
      }

      const userSelect = document.getElementById('quick-member-user-id');
      const roleSelect = document.getElementById('quick-member-role-id');
      const chosenUserId = userSelect.value;
      const chosenUserName = userSelect.options[userSelect.selectedIndex].text.split('(')[0].trim();
      const chosenRoleName = roleSelect.options[roleSelect.selectedIndex].text;

      // Append new option to all assignee selects in bulk edit and add task
      const newOptionText = `${chosenUserName} (${chosenRoleName})`;
      document.querySelectorAll('.bulk-assignee-select, #add-task-assignee-select').forEach(sel => {
        const opt = document.createElement('option');
        opt.value = chosenUserId;
        opt.textContent = newOptionText;
        sel.appendChild(opt);
      });

      quickMemberDialog.close();
      alert(`Added ${chosenUserName} to project members.`);
    } catch (err) {
      alert('Could not add member: ' + err.message);
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  });

})();
