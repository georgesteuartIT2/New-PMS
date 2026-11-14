(() => {
    const config = document.getElementById('task-assignment-config');
    const dialog = document.getElementById('task-assign-dialog');
    if (!config || !dialog) return;

    const members = new Set(JSON.parse(config.dataset.members).map(Number));
    const select = dialog.querySelector('[data-bulk-assign-person]');
    const save = dialog.querySelector('[data-bulk-assign-save]');
    const feedback = dialog.querySelector('[data-bulk-assign-feedback]');
    const hint = dialog.querySelector('[data-bulk-assign-hint]');
    let taskIds = [];
    let replacement = null;
    let busy = false;
    let resumeBulk = false;

    const manual = document.getElementById('add-task-assignee-select');
    const confirm = document.getElementById('manual-task-member-confirm');
    manual?.addEventListener('change', () => {
        const adding = manual.value && !members.has(Number(manual.value));
        confirm.hidden = !adding;
        const checkbox = confirm.querySelector('input');
        checkbox.checked = false;
        checkbox.required = !!adding;
    });

    document.addEventListener('change', event => {
        const field = event.target.closest('.bulk-assignee-select');
        if (!field) return;
        const label = field.closest('tr').querySelector('.bulk-new-member-confirm');
        const adding = !!field.value && !members.has(Number(field.value));
        label.hidden = !adding;
        const checkbox = label.querySelector('input');
        checkbox.required = adding;
        checkbox.checked = false;
    });

    const checks = () => [...document.querySelectorAll('.task-assignment-check')];
    function count() {
        const selected = checks().filter(check => check.checked).length;
        const button = document.getElementById('assign-selected-tasks');
        if (button) button.disabled = !selected;
        const counter = document.querySelector('[data-selected-task-count]');
        if (counter) counter.textContent = selected ? selected + ' selected' : '';
    }

    function renderAssignees(taskId, assignees) {
        const cell = document.querySelector('[data-assignment-cell="' + taskId + '"]');
        if (!cell || !assignees.length) return;

        const row = cell.closest('.project-task-row');
        const primary = assignees[0];
        const group = document.createElement('div');
        group.className = 'task-assignee-group';

        if (row.dataset.canChangeAssignee === '1') {
            const avatar = document.createElement('button');
            avatar.type = 'button';
            avatar.className = 'task-assignee-avatar task-assignee-change';
            avatar.dataset.changeAssignee = '';
            avatar.dataset.taskId = taskId;
            avatar.dataset.assigneeId = primary.id;
            avatar.dataset.assigneeName = primary.name;
            avatar.setAttribute('aria-label', 'Change assignee from ' + primary.name);
            avatar.title = 'Change assignee';
            avatar.textContent = (primary.name || '?').slice(0, 1).toLocaleUpperCase();
            group.append(avatar);
        } else {
            const avatar = document.createElement('span');
            avatar.className = 'task-assignee-avatar';
            avatar.setAttribute('aria-hidden', 'true');
            avatar.textContent = (primary.name || '?').slice(0, 1).toLocaleUpperCase();
            group.append(avatar);
        }

        const name = document.createElement('span');
        name.className = 'task-assignee-name';
        name.title = primary.name;
        name.textContent = primary.name;
        group.append(name);

        if (assignees.length > 1) {
            const extra = document.createElement('span');
            extra.className = 'task-assignee-extra';
            extra.textContent = '+' + (assignees.length - 1);
            extra.title = assignees.map(person => person.name).join(', ');
            group.append(extra);
        }

        if (row.dataset.canChangeAssignee !== '1' && config.dataset.canAddAssignee === '1') {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'task-add-assignee';
            button.dataset.singleAssign = taskId;
            button.setAttribute('aria-label', 'Add another assignee');
            button.title = 'Add another assignee';
            button.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M19 8v6m-3-3h6"/></svg>';
            group.append(button);
        }

        cell.replaceChildren(group);
    }

    function open(ids, replace = null) {
        const bulk = document.getElementById('bulk-edit-tasks-dialog');
        resumeBulk = !!bulk?.open;
        if (resumeBulk) bulk.close();

        taskIds = ids;
        replacement = replace;
        select.value = '';
        const input = select.closest('.directory-picker')?.querySelector('input');
        if (input) input.value = '';
        save.disabled = true;
        save.textContent = replacement ? 'Change assignee' : 'Assign selected';
        feedback.textContent = '';
        dialog.querySelector('h2').textContent = replacement ? 'Change assignee' : 'Assign tasks';
        dialog.querySelector('[data-bulk-assign-count]').textContent = replacement
            ? 'Replace ' + replacement.name + ' with another project member.'
            : ids.length === 1
                ? 'Choose a person for this task.'
                : ids.length + ' selected tasks. Existing assignees will be kept.';
        hint.textContent = replacement
            ? 'Choose a current project member.'
            : 'Choose a project member. Type a name or email to find someone else in the system or Microsoft.';
        dialog.showModal();
        setTimeout(() => select.closest('.directory-picker')?.querySelector('input')?.focus(), 0);
    }

    function updateSelectionState() {
        const userId = Number(select.value);
        const adding = !!userId && !members.has(userId);
        const sameAssignee = replacement && replacement.id === userId;
        const invalidReplacement = replacement && (!members.has(userId) || sameAssignee);
        save.disabled = busy || !userId || !!invalidReplacement;
        save.textContent = replacement
            ? 'Change assignee'
            : adding
                ? 'Add to project & assign'
                : 'Assign ' + (taskIds.length > 1 ? 'selected' : 'task');
        hint.textContent = replacement
            ? sameAssignee
                ? 'Choose a different project member.'
                : !members.has(userId)
                    ? 'Only current project members can replace an assignee.'
                    : 'This replaces ' + replacement.name + ' on the selected task.'
            : adding
                ? 'This person will join this project as a Team Member and be assigned to the selected tasks.'
                : 'This person already has project access.';
    }

    document.addEventListener('change', event => {
        if (event.target.matches('#select-visible-assignments')) {
            checks().filter(check => check.closest('tr').getClientRects().length)
                .forEach(check => { check.checked = event.target.checked; });
            count();
        }
        if (event.target.matches('.task-assignment-check')) count();
    });

    document.addEventListener('click', event => {
        const changeAssignee = event.target.closest('[data-change-assignee]');
        if (changeAssignee) {
            event.preventDefault();
            open([Number(changeAssignee.dataset.taskId)], {
                id: Number(changeAssignee.dataset.assigneeId),
                name: changeAssignee.dataset.assigneeName,
                url: changeAssignee.closest('.project-task-row').dataset.reassignUrl
            });
            return;
        }

        if (event.target.closest('#assign-selected-tasks')) {
            open(checks().filter(check => check.checked).map(check => Number(check.value)));
        }
        const single = event.target.closest('[data-single-assign],[data-assign-open]');
        if (single) {
            event.preventDefault();
            open([Number(single.dataset.singleAssign || single.closest('[data-task-id]').dataset.taskId)]);
        }
    });

    dialog.addEventListener('close', () => {
        if (resumeBulk) {
            resumeBulk = false;
            document.getElementById('bulk-edit-tasks-dialog')?.showModal();
        }
    });

    select.addEventListener('change', updateSelectionState);

    save.addEventListener('click', async () => {
        if (busy || !select.value) return;
        busy = true;
        save.disabled = true;
        feedback.textContent = replacement ? 'Changing assignee...' : 'Assigning...';

        try {
            const userId = Number(select.value);
            const body = replacement
                ? { old_user_id: replacement.id, new_user_id: userId }
                : { task_ids: taskIds, user_id: userId, add_to_project: !members.has(userId) };
            const response = await fetch(replacement ? replacement.url : config.dataset.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(body)
            });
            const data = await response.json();
            if (!response.ok) throw Error(data.message || 'Could not update task assignments.');

            members.add(userId);
            config.dataset.members = JSON.stringify([...members]);
            const updatedTasks = replacement
                ? [{ id: taskIds[0], assignees: data.assignees }]
                : data.tasks;

            for (const task of updatedTasks) {
                const bulkSelect = document.querySelector('.bulk-task-row[data-task-id="' + task.id + '"] .bulk-assignee-select');
                if (bulkSelect && task.assignees.length) {
                    const primary = task.assignees[0];
                    if (![...bulkSelect.options].some(option => Number(option.value) === primary.id)) {
                        bulkSelect.add(new Option(primary.name, primary.id));
                    }
                    bulkSelect.value = primary.id;
                }
                renderAssignees(task.id, task.assignees);
            }

            checks().forEach(check => { check.checked = false; });
            const all = document.getElementById('select-visible-assignments');
            if (all) all.checked = false;
            count();
            dialog.close();

            let notice = document.getElementById('task-assignment-notice');
            if (!notice) {
                notice = document.createElement('p');
                notice.id = 'task-assignment-notice';
                notice.setAttribute('role', 'status');
                (document.getElementById('project-workspace') || document.body).prepend(notice);
            }
            notice.textContent = replacement
                ? 'Assignee changed.'
                : taskIds.length + ' ' + (taskIds.length === 1 ? 'task assigned.' : 'tasks assigned.');
        } catch (error) {
            feedback.textContent = error.message;
        } finally {
            busy = false;
            updateSelectionState();
        }
    });
})();
