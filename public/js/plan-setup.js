(() => {
    const dialog = document.getElementById('plan-start-dialog');
    const form = document.getElementById('plan-setup-form');
    if (!dialog || !form) return;

    let draftTimer, draftQueue=Promise.resolve(),publishing=false,draftDirty=false,draftRevision=0,savingRevision=-1;
    const draftEnabled=form.dataset.draftEnabled==='1';
    const draftStatus=document.getElementById('plan-draft-status');
    const draftSavedFeedback=document.getElementById('plan-draft-saved-feedback');
    const resumeSetupButton=document.getElementById('btn-resume-plan-setup');
    function draftPayload(){const values=new FormData(form),data={};for(const key of ['name','description','mode','project_template_id','starts_on','ends_on','planned_budget','blank_plan'])if(values.has(key))data[key]=values.get(key);data.members=[...form.querySelectorAll('[data-role-row]')].map(row=>({user_id:row.querySelector('[data-role-person]')?.value||null,project_role_id:row.querySelector('[data-role-choice]')?.value||null}));return data;}
    function formatDraftTime(value){const date=new Date(value);return Number.isNaN(date.getTime())?'':new Intl.DateTimeFormat(undefined,{hour:'numeric',minute:'2-digit'}).format(date);}
    function saveDraft(force=false){
        clearTimeout(draftTimer);
        if(!draftEnabled||publishing)return Promise.resolve(false);
        if(!force&&!draftDirty)return draftQueue;
        if(savingRevision===draftRevision)return draftQueue;
        const payload=draftPayload(),revision=draftRevision;
        savingRevision=revision;
        if(draftStatus)draftStatus.textContent='Saving draft...';
        draftQueue=draftQueue.catch(()=>false).then(async()=>{
            const response=await fetch(form.dataset.draftUrl,{method:'POST',keepalive:true,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.querySelector('[name=_token]').value},body:JSON.stringify(payload)});
            if(response.redirected||response.status===401||response.status===419)throw Error('Your session may have expired. Refresh the page and try again.');
            if(response.status===403)throw Error('You no longer have permission to save this draft.');
            if(response.status===409)throw Error('This project is no longer waiting for plan setup. Refresh the page.');
            if(response.status===422){
                const result=await response.json();
                const validationMessage=result.errors?Object.values(result.errors).flat()[0]:null;
                throw Error(validationMessage||'Some draft details are invalid. Review the form and try again.');
            }
            if(!response.ok){
                throw Error(response.status>=500?'A server issue prevented saving the draft. Please try again or contact your administrator.':'The draft could not be saved. Please try again.');
            }
            if(!(response.headers.get('content-type')||'').includes('application/json'))throw Error('The server returned an unexpected response. Refresh the page and try again.');
            const result=await response.json();
            if(!result.saved)throw Error('The server did not confirm that the draft was saved. Try again.');
            if(draftRevision===revision)draftDirty=false;
            const savedTime=formatDraftTime(result.saved_at);
            if(draftStatus){
                draftStatus.textContent=savedTime?'Draft saved at '+savedTime:'Draft saved';
                draftStatus.dataset.savedAt=result.saved_at||'';
            }
            return true;
        }).catch(error=>{
            const message=error instanceof Error&&error.name==='Error'?error.message:'A connection issue prevented saving the draft. Please try again.';
            if(draftStatus)draftStatus.textContent='Draft could not be saved: '+message;
            return false;
        }).finally(()=>{if(savingRevision===revision)savingRevision=-1;});
        return draftQueue;
    }
    function scheduleDraft(){if(publishing)return;refreshPlanIndicators();if(!draftEnabled)return;draftDirty=true;draftRevision++;if(draftStatus)draftStatus.textContent='Unsaved changes';clearTimeout(draftTimer);draftTimer=setTimeout(()=>saveDraft(),500);}
    form.addEventListener('input',scheduleDraft);form.addEventListener('change',scheduleDraft);
    form.addEventListener('click',event=>{if(event.target.closest('[data-remove-role-row]'))setTimeout(scheduleDraft,0);});
    dialog.addEventListener('close',()=>saveDraft());
    window.addEventListener('pagehide',()=>saveDraft());
    document.addEventListener('visibilitychange',()=>{if(document.hidden)saveDraft();});
    document.addEventListener('project-plan-draft-change',event=>{if(!event.detail||!window.SmartTaskPlan?.localPlanSave)return;const hidden=document.getElementById('blank-plan-data');if(hidden){hidden.value=JSON.stringify(event.detail);const mode=document.getElementById('plan-mode');const template=document.getElementById('plan-template-id');if(window.PLAN_SETUP_CONFIG?.selectedTemplateId){if(mode)mode.value='template';if(template){template.value=window.PLAN_SETUP_CONFIG.selectedTemplateId;template.disabled=false;}}else{if(mode)mode.value='blank';if(template)template.disabled=true;}scheduleDraft();}});

    const isStarted = form.dataset.planStarted === '1';

    const modeInput = document.getElementById('plan-mode');
    const templateInput = document.getElementById('plan-template-id');
    const blankPlanData = document.getElementById('blank-plan-data');
    const errorMsg = document.getElementById('plan-template-error');
    const blankBtn = form.querySelector('[data-plan-mode="blank"]');
    const chooseTemplateBtn = form.querySelector('[data-choose-template]');
    const blankSubpanel = document.getElementById('blank-plan-subpanel');
    const templateSubpanel = document.getElementById('template-plan-subpanel');
    const publishDirectBtn = document.getElementById('btn-publish-direct');
    const startsOnInput = document.getElementById('plan-starts-on');
    const endsOnInput = document.getElementById('plan-ends-on');
    const workingDaysBadge = document.getElementById('working-days-badge');
    const blankTasksStatus = document.getElementById('blank-tasks-status');
    const manualBlankBtn = document.getElementById('btn-manual-blank-tasks');
    const manualBlankLabel = document.getElementById('btn-manual-blank-label');
    const uploadExcelBtn = document.getElementById('btn-upload-plan-excel');
    const fileInput = document.getElementById('plan-excel-file-input');
    const customizeTemplateBtn = document.getElementById('btn-customize-template-tasks');
    const quickTemplateSelect = document.getElementById('quick-template-select');
    const unassignedCount = document.getElementById('plan-unassigned-count');
    const validationCount = document.getElementById('plan-validation-count');
    const saveDraftCloseBtn = document.getElementById('btn-save-draft-close');

    // Calculate working days
    function updateWorkingDays() {
        if (!startsOnInput || !endsOnInput || !workingDaysBadge) return;
        const start = new Date(startsOnInput.value);
        const end = new Date(endsOnInput.value);
        if (isNaN(start.getTime()) || isNaN(end.getTime()) || start > end) {
            workingDaysBadge.textContent = '';
            return;
        }
        let count = 0;
        let cur = new Date(start);
        while (cur <= end) {
            const day = cur.getDay();
            if (day !== 0 && day !== 6) count++;
            cur.setDate(cur.getDate() + 1);
        }
        workingDaysBadge.textContent = count + ' working ' + (count === 1 ? 'day' : 'days');
    }

    if (startsOnInput && endsOnInput) {
        startsOnInput.addEventListener('change', updateWorkingDays);
        endsOnInput.addEventListener('change', updateWorkingDays);
        updateWorkingDays();
    }

    // Refresh Publish Direct Button state
    function updatePublishBtnState() {
        if (!publishDirectBtn) return;
        const issues = getPlanValidationIssues();
        const canPublish = (isStarted || (modeInput?.value === 'template' && templateInput && !templateInput.disabled && templateInput.value) || currentPlanTasks().length > 0) && issues.length === 0;
        publishDirectBtn.disabled = !canPublish;
        publishDirectBtn.title = canPublish
            ? 'Publish this plan immediately and notify your team.'
            : issues.length ? 'Fix ' + issues.length + ' validation ' + (issues.length === 1 ? 'issue' : 'issues') + ' before publishing.'
                : 'Add tasks manually, upload an Excel file, or choose a template to publish directly.';
    }

    function currentPlanTasks() {
        const config = window.PLAN_SETUP_CONFIG || {};
        if (isStarted) return config.existingTasks || [];
        try {
            const parsed = JSON.parse(blankPlanData?.value || '{}');
            if (Array.isArray(parsed.tasks)) return parsed.tasks;
        } catch (e) {
        }
        return modeInput?.value === 'template' ? (config.selectedTemplateTasks || []) : [];
    }

    function getPlanValidationIssues() {
        const issues = [];
        if (!String(form.elements.name?.value || '').trim()) issues.push('Project name is required');
        if (!isStarted) {
            if (!modeInput?.value) issues.push('Choose Blank plan or Use a template');
            const start = startsOnInput?.value || '';
            const end = endsOnInput?.value || '';
            if (!start) issues.push('Start date is required');
            if (!end) issues.push('Target finish is required');
            if (start && end && end < start) issues.push('Target finish must be on or after the start date');
            if (modeInput?.value === 'template' && (!templateInput || templateInput.disabled || !templateInput.value)) issues.push('Choose a project template');
            if (!modeInput?.value) return issues;
        }
        const tasks = currentPlanTasks();
        if (!tasks.length) {
            issues.push('Add at least one task');
            return issues;
        }
        const codes = new Set();
        tasks.forEach(task => {
            if (!String(task.code || '').trim()) issues.push('A task is missing its code');
            if (!String(task.title || '').trim()) issues.push('A task is missing its title');
            const duration = task.duration_days ?? task.duration;
            if (duration !== undefined && (!Number.isFinite(Number(duration)) || Number(duration) < 1)) issues.push('A task has an invalid duration');
            const code = String(task.code || '').trim().toLowerCase();
            if (code && codes.has(code)) issues.push('Task codes must be unique');
            if (code) codes.add(code);
        });
        return issues;
    }

    function refreshPlanIndicators() {
        const cardSummary = document.getElementById('blank-plan-card-summary');
        if (cardSummary) {
            let tasks = [];
            try { tasks = JSON.parse(blankPlanData?.value || '{}').tasks || []; } catch {}
            const count = tasks.length;
            cardSummary.textContent = count
                ? count + ' tasks prepared · Click to edit plan'
                : 'Add tasks manually or import from Excel.';
        }
        const tasks = currentPlanTasks();
        const team = document.getElementById('plan-task-team');
        if (team) {
            const people = new Map();
            tasks.forEach(task => {
                const id = task.assignee_id || task.assigned_user_id;
                if (!id) return;
                const user = (window.PLAN_SETUP_CONFIG?.users || []).find(person => String(person.id) === String(id));
                const person = people.get(String(id)) || {name:user?.name || task.assignee_name || task.assignee_email || 'Assigned team member',count:0};
                person.count++;people.set(String(id),person);
            });
            team.replaceChildren();team.hidden = !people.size;
            if (people.size) {
                const heading = document.createElement('strong');heading.textContent='Team members assigned to tasks';team.append(heading);
                people.forEach(person => {
                    const row=document.createElement('div');row.className='plan-task-team-row';
                    const name=document.createElement('span');name.textContent=person.name;
                    const detail=document.createElement('small');detail.textContent='Team member · '+person.count+' '+(person.count===1?'task':'tasks');
                    row.append(name,detail);team.append(row);
                });
            }
        }
        const parentReferences = new Set(tasks.map(task => String(task.parent_code || '')).filter(Boolean));
        const parentIds = new Set(tasks.map(task => String(task.parent_task_id || '')).filter(Boolean));
        const leaves = tasks.filter(task => !parentReferences.has(String(task.code || '')) && !parentIds.has(String(task.id || '')));
        const unassigned = leaves.filter(task => !task.assignee_id && !task.assigned_user_id && !(Array.isArray(task.assignees) && task.assignees.length)).length;
        if (unassignedCount) unassignedCount.textContent = tasks.length ? unassigned + ' ' + (unassigned === 1 ? 'task' : 'tasks') + ' unassigned' : 'No tasks added';
        const issues = getPlanValidationIssues();
        if (errorMsg && !issues.length) errorMsg.hidden = true;
        if (validationCount) {
            validationCount.textContent = issues.length ? issues[0] + (issues.length > 1 ? ' · +' + (issues.length - 1) + ' more' : '') : 'Ready to publish';
            validationCount.title = issues.join('. ');
        }
        updatePublishBtnState();
    }

    if (draftStatus?.dataset.savedAt) {
        const savedTime = formatDraftTime(draftStatus.dataset.savedAt);
        if (savedTime) draftStatus.textContent = 'Draft saved at ' + savedTime;
    }
    saveDraftCloseBtn?.addEventListener('click', async () => {
        if(saveDraftCloseBtn.disabled)return;
        saveDraftCloseBtn.disabled=true;
        saveDraftCloseBtn.textContent='Saving draft...';
        saveDraftCloseBtn.setAttribute('aria-busy','true');
        const saved=await saveDraft(true);
        saveDraftCloseBtn.removeAttribute('aria-busy');
        if(!saved){
            saveDraftCloseBtn.disabled=false;
            saveDraftCloseBtn.textContent='Retry save & close';
            return;
        }
        if(resumeSetupButton)resumeSetupButton.textContent='Continue saved draft';
        if(draftSavedFeedback){
            draftSavedFeedback.textContent=(draftStatus?.textContent||'Draft saved')+'. Your changes are saved but have not been shared with the team.';
            draftSavedFeedback.hidden=false;
        }
        dialog.close();
    });

    // Check existing blank_plan data on load
    if (blankPlanData && blankPlanData.value) {
        try {
            const parsed = JSON.parse(blankPlanData.value);
            if (parsed.tasks && parsed.tasks.length > 0) {
                if (blankTasksStatus) {
                    blankTasksStatus.textContent = '✓ ' + parsed.tasks.length + ' tasks prepared';
                    blankTasksStatus.classList.add('is-ready');
                }
                if (manualBlankLabel) {
                    manualBlankLabel.textContent = '✏️ Edit prepared tasks (' + parsed.tasks.length + ')';
                }
            }
        } catch (e) {}
    }

    // Blank mode switcher
    blankBtn?.addEventListener('click', () => {
        modeInput.value = 'blank';
        if (templateInput) templateInput.disabled = true;
        blankBtn.classList.add('is-selected');
        blankBtn.setAttribute('aria-pressed', 'true');
        chooseTemplateBtn?.classList.remove('is-selected');
        chooseTemplateBtn?.setAttribute('aria-pressed', 'false');
        if (errorMsg) errorMsg.hidden = true;
        if (blankSubpanel) blankSubpanel.hidden = true;
        if (templateSubpanel) templateSubpanel.hidden = true;
        refreshPlanIndicators();
        scheduleDraft();
        setTimeout(() => manualBlankBtn?.click(), 0);
    });

    // Template mode switcher / opener
    chooseTemplateBtn?.addEventListener('click', (event) => {
        const hasTemplate = templateInput && templateInput.value;
        if (hasTemplate && modeInput.value === 'blank' && !event.target.closest('u')) {
            event.preventDefault();
            modeInput.value = 'template';
            templateInput.disabled = false;
            chooseTemplateBtn.classList.add('is-selected');
            chooseTemplateBtn.setAttribute('aria-pressed', 'true');
            blankBtn?.classList.remove('is-selected');
            blankBtn?.setAttribute('aria-pressed', 'false');
            if (errorMsg) errorMsg.hidden = true;
            if (blankSubpanel) blankSubpanel.hidden = true;
            if (templateSubpanel) templateSubpanel.hidden = false;
            refreshPlanIndicators();
            scheduleDraft();
            return;
        }

        // Navigate to library if no template is chosen yet or user clicked "Change"
        event.preventDefault();
        const url = new URL(form.dataset.libraryUrl, window.location.href);
        for (const name of ['starts_on', 'ends_on', 'planned_budget']) {
            const val = form.elements[name]?.value;
            if (val) url.searchParams.set(name, val);
        }
        saveDraft().then(()=>window.location.href = url.href);
    });

    // Quick template select dropdown
    quickTemplateSelect?.addEventListener('change', () => {
        const selectedOpt = quickTemplateSelect.selectedOptions[0];
        if (selectedOpt && selectedOpt.dataset.url) {
            window.location.href = selectedOpt.dataset.url;
        }
    });

    // Configure SmartTaskPlan engine
    function setupSmartTaskPlanEngine() {
        if (!window.SmartTaskPlan) return;
        const config = window.PLAN_SETUP_CONFIG || {};
        window.SmartTaskPlan.roles = config.roles || [];
        window.SmartTaskPlan.assignmentProject = config.projectId;
        window.SmartTaskPlan.assignmentEnabled = true;
        window.SmartTaskPlan.assignmentUsers = config.users || [];
        document.getElementById('smart-task-plan-modal')?.classList.add('stp-assignment-mode');
        window.SmartTaskPlan.canManage = true;
        window.SmartTaskPlan.canCreateProject = false;
        window.SmartTaskPlan.localPlanSave = function (payload) {
            payload = {...(window.PLAN_SETUP_CONFIG?.selectedTemplate || {}), ...payload};
            if (blankPlanData) {
                blankPlanData.value = JSON.stringify(payload);
            }
            if (blankTasksStatus) {
                const assigned = (payload.tasks || []).filter(t => t.assignee_id).length;
                blankTasksStatus.textContent = '✓ ' + (payload.tasks || []).length + ' tasks ready (' + assigned + ' assigned)';
                blankTasksStatus.classList.add('is-ready');
            }
            if (manualBlankLabel) {
                manualBlankLabel.textContent = '✏️ Edit prepared tasks (' + (payload.tasks || []).length + ')';
            }
            // Switch to blank mode if not already
            const templateId = window.PLAN_SETUP_CONFIG?.selectedTemplateId || templateInput?.value;
            const usesTemplate = !!templateId;
            modeInput.value = usesTemplate ? 'template' : 'blank';
            if (templateInput) { templateInput.disabled = !usesTemplate; if (usesTemplate) templateInput.value = templateId; }
            blankBtn?.classList.toggle('is-selected', !usesTemplate);
            blankBtn?.setAttribute('aria-pressed', String(!usesTemplate));
            chooseTemplateBtn?.classList.toggle('is-selected', usesTemplate);
            chooseTemplateBtn?.setAttribute('aria-pressed', String(usesTemplate));
            if (templateSubpanel) templateSubpanel.hidden = !usesTemplate;
            updatePublishBtnState();
            scheduleDraft();
        };
    }

    // Manual blank plan task builder
    manualBlankBtn?.addEventListener('click', () => {
        setupSmartTaskPlanEngine();
        if (!window.SmartTaskPlan) {
            alert('Task plan builder is loading. Please try again in a moment.');
            return;
        }
        let data = null;
        if (blankPlanData && blankPlanData.value) {
            try { data = JSON.parse(blankPlanData.value); } catch (e) {}
        }
        if (data && data.tasks && data.tasks.length > 0) {
            window.SmartTaskPlan.openEdit(data, data.tasks);
        } else {
            const config = window.PLAN_SETUP_CONFIG || {};
            window.SmartTaskPlan.openCreate({
                name: config.projectName || 'Project Plan',
                reference_start_date: startsOnInput?.value || new Date().toISOString().split('T')[0],
                working_hours: '09:00 - 17:00'
            });
        }
    });

    // Upload Excel button & file picker
    uploadExcelBtn?.addEventListener('click', () => {
        fileInput?.click();
    });

    fileInput?.addEventListener('change', async () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;

        if (blankTasksStatus) {
            blankTasksStatus.textContent = 'Parsing Excel...';
            blankTasksStatus.classList.remove('is-ready');
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('plan_project', window.PLAN_SETUP_CONFIG?.projectId || '');
        formData.append('estimated_duration', '14');
        formData.append('duration_unit', 'working_days');
        formData.append('reference_start_date', startsOnInput?.value || '');

        try {
            const res = await fetch('/templates/import-preview', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: formData
            });
            const data = await res.json();
            if (data.success && data.tasks && data.tasks.length > 0) {
                setupSmartTaskPlanEngine();
                // Open SmartTaskPlan so user can visually verify and tweak
                const templateMeta = {
                    name: file.name.replace(/\.[^/.]+$/, ''),
                    reference_start_date: startsOnInput?.value || new Date().toISOString().split('T')[0],
                    working_hours: '09:00 - 17:00'
                };
                window.SmartTaskPlan.openEdit(templateMeta, data.tasks);
            } else {
                const errMsg = (data.errors && data.errors.join('\n')) || data.message || 'Unable to parse Excel file.';
                alert('Excel Import Error:\n' + errMsg);
                if (blankTasksStatus) {
                    blankTasksStatus.textContent = 'Import failed';
                }
            }
        } catch (err) {
            console.error(err);
            alert('Failed to upload and parse Excel spreadsheet.');
            if (blankTasksStatus) {
                blankTasksStatus.textContent = 'Upload error';
            }
        } finally {
            fileInput.value = '';
        }
    });

    // Customize Template Tasks button
    customizeTemplateBtn?.addEventListener('click', () => {
        setupSmartTaskPlanEngine();
        if (!window.SmartTaskPlan) {
            alert('Task plan engine is loading. Please try again.');
            return;
        }
        const config = window.PLAN_SETUP_CONFIG || {};
        if (config.selectedTemplateId) {
            let draft;try{draft=JSON.parse(blankPlanData?.value||'null');}catch{}
            const metadata = {
                name: config.projectName || 'Project plan',
                estimated_duration: 14,
                duration_unit: 'working_days',
                working_hours: '09:00 - 17:00',
                reference_start_date: startsOnInput?.value || new Date().toISOString().split('T')[0],
                ...(config.selectedTemplate || {})
            };
            if (draft && Array.isArray(draft.tasks)) {
                const restored = {...metadata, ...draft};
                restored.name = draft.name || metadata.name;
                restored.estimated_duration = draft.estimated_duration || metadata.estimated_duration || 14;
                restored.duration_unit = draft.duration_unit || metadata.duration_unit || 'working_days';
                window.SmartTaskPlan.openEdit(restored, draft.tasks);
            } else window.SmartTaskPlan.openEdit(metadata, null);
        }
    });

    // Form submit validation & action routing
    form.addEventListener('submit', (event) => {
        const issues = getPlanValidationIssues();
        if (issues.length) {
            event.preventDefault();
            if (errorMsg) {
                errorMsg.textContent = issues.join('. ') + '.';
                errorMsg.hidden = false;
            }
            if (modeInput?.value === 'template' && (!templateInput || !templateInput.value)) chooseTemplateBtn?.focus();
            else form.querySelector(':invalid')?.focus();
            return;
        }
        if (publishing) { event.preventDefault(); return; }
        publishing = true;
        clearTimeout(draftTimer);
        if (event.submitter === publishDirectBtn) {
            const publishFlag = document.createElement('input');
            publishFlag.type = 'hidden';publishFlag.name = 'publish_now';publishFlag.value = '1';
            form.append(publishFlag);
            publishDirectBtn.disabled = true;
            publishDirectBtn.setAttribute('aria-busy', 'true');
            publishDirectBtn.textContent = 'Publishing & notifying...';
            document.getElementById('btn-save-draft-close')?.setAttribute('disabled', '');
        }
    });

    refreshPlanIndicators();
    dialog.showModal();
})();
