(() => {
    const dialog = document.getElementById('plan-start-dialog');
    const form = document.getElementById('plan-setup-form');
    if (!dialog || !form) return;

    let draftTimer, draftQueue=Promise.resolve(),publishing=false;
    const draftStatus=document.getElementById('plan-draft-status');
    function draftPayload(){const values=new FormData(form),data={};for(const key of ['name','description','mode','project_template_id','starts_on','ends_on','planned_budget','blank_plan'])if(values.has(key))data[key]=values.get(key);data.members=[...form.querySelectorAll('[data-role-row]')].map(row=>({user_id:row.querySelector('[data-role-person]')?.value||null,project_role_id:row.querySelector('[data-role-choice]')?.value||null}));return data;}
    function saveDraft(){clearTimeout(draftTimer);if(publishing)return draftQueue;const payload=draftPayload();if(draftStatus)draftStatus.textContent='Saving draft...';draftQueue=draftQueue.catch(()=>{}).then(async()=>{const response=await fetch(form.dataset.draftUrl,{method:'POST',keepalive:true,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.querySelector('[name=_token]').value},body:JSON.stringify(payload)});if(!response.ok)throw Error('Draft not saved');if(draftStatus)draftStatus.textContent='Draft saved';}).catch(()=>{if(draftStatus)draftStatus.textContent='Draft could not be saved. Keep this page open and try again.';});return draftQueue;}
    function scheduleDraft(){if(publishing)return;if(draftStatus)draftStatus.textContent='Unsaved changes';clearTimeout(draftTimer);draftTimer=setTimeout(saveDraft,500);}
    form.addEventListener('input',scheduleDraft);form.addEventListener('change',scheduleDraft);
    form.addEventListener('click',event=>{if(event.target.closest('[data-remove-role-row]'))setTimeout(scheduleDraft,0);});
    dialog.addEventListener('close',saveDraft);
    window.addEventListener('pagehide',saveDraft);
    document.addEventListener('visibilitychange',()=>{if(document.hidden)saveDraft();});
    form.addEventListener('submit',()=>{publishing=true;clearTimeout(draftTimer);});
    document.addEventListener('project-plan-draft-change',event=>{if(!event.detail||!window.SmartTaskPlan?.localPlanSave)return;const hidden=document.getElementById('blank-plan-data');if(hidden){hidden.value=JSON.stringify(event.detail);const mode=document.getElementById('plan-mode');if(mode)mode.value='blank';const template=document.getElementById('plan-template-id');if(template)template.disabled=true;scheduleDraft();}});

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
        if (isStarted) return;
        const hasTemplate = modeInput?.value === 'template' && templateInput && !templateInput.disabled && templateInput.value;
        let hasBlankTasks = false;
        if (blankPlanData && blankPlanData.value) {
            try {
                const parsed = JSON.parse(blankPlanData.value);
                if (parsed.tasks && parsed.tasks.length > 0) hasBlankTasks = true;
            } catch (e) {}
        }
        const canPublish = hasTemplate || hasBlankTasks;
        publishDirectBtn.disabled = !canPublish;
        if (canPublish) {
            publishDirectBtn.title = 'Publish this plan immediately and notify your team.';
        } else {
            publishDirectBtn.title = 'Add tasks manually, upload an Excel file, or choose a template to publish directly.';
        }
    }

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
        if (blankSubpanel) blankSubpanel.style.display = '';
        if (templateSubpanel) templateSubpanel.style.display = 'none';
        updatePublishBtnState();
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
            if (blankSubpanel) blankSubpanel.style.display = 'none';
            if (templateSubpanel) templateSubpanel.style.display = '';
            updatePublishBtnState();
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
        window.SmartTaskPlan.assignmentUsers = config.users || [];
        window.SmartTaskPlan.canManage = true;
        window.SmartTaskPlan.canCreateProject = false;
        window.SmartTaskPlan.localPlanSave = function (payload) {
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
            if (modeInput.value !== 'blank') {
                blankBtn?.click();
            }
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
            if(draft)window.SmartTaskPlan.openEdit(draft,draft.tasks);
            else window.SmartTaskPlan.openEdit({id:config.selectedTemplateId},null);
        }
    });

    // Form submit validation & action routing
    form.addEventListener('submit', (event) => {
        if (modeInput?.value === 'template' && (!templateInput || !templateInput.value)) {
            event.preventDefault();
            if (errorMsg) errorMsg.hidden = false;
            chooseTemplateBtn?.focus();
            return;
        }
    });

    updatePublishBtnState();
    dialog.showModal();
})();
