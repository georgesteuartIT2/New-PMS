/**
 * GS ProjectFlow - Smart Template Builder & Interactive Task Plan Modal
 * Enterprise vanilla JavaScript engine.
 * Automatic Day/Week/Month Planning Periods + Excel Round-Trip Editing.
 */
(function(window, document) {
    'use strict';

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    const SmartTaskPlan = {
        mode: 'view', // 'view' | 'edit' | 'create'
        viewType: 'periods', // 'periods' | 'table'
        template: {
            id: null,
            name: '',
            category: 'General',
            estimated_duration: 14,
            duration_unit: 'working_days',
            reference_start_date: '',
            working_hours: '09:00 - 17:00',
            description: '',
            planning_periods: {}
        },
        periods: [],
        tasks: [],
        roles: [],
        canManage: false,
        canCreateProject: false,
        unsavedChanges: false,
        pendingImportTasks: null,
        collapsedPeriods: {},
        dom: {},

        init(config = {}) {
            this.roles = config.roles || [];
            this.localPlanSave = config.localPlanSave || null;
            this.canManage = !!config.canManage;
            this.canCreateProject = !!config.canCreateProject;

            this.dom.backdrop = document.getElementById('smart-task-plan-backdrop');
            this.dom.modal = document.getElementById('smart-task-plan-modal');
            this.dom.title = document.getElementById('stp-template-title');
            this.dom.scheduleBadge = document.getElementById('stp-schedule-badge');
            this.dom.modeBadge = document.getElementById('stp-mode-badge');
            this.dom.detailsDrawer = document.getElementById('stp-details-drawer');
            this.dom.detailsToggleBtn = document.getElementById('stp-btn-toggle-details');
            this.dom.toolbar = document.getElementById('stp-toolbar');
            this.dom.periodsContainer = document.getElementById('stp-periods-container');
            this.dom.tableWrap = document.getElementById('stp-table-wrap');
            this.dom.tableBody = document.getElementById('stp-table-body');
            this.dom.summaryCounts = document.getElementById('stp-summary-counts');
            this.dom.periodsCountBadge = document.getElementById('stp-periods-count-badge');
            this.dom.warningBanner = document.getElementById('stp-warning-banner');
            this.dom.importBanner = document.getElementById('stp-import-banner');
            this.dom.unsavedText = document.getElementById('stp-unsaved-text');
            this.dom.fileInput = document.getElementById('stp-excel-file-input');
            this.dom.btnSave = document.getElementById('stp-btn-save');
            this.dom.btnCancel = document.getElementById('stp-btn-cancel');
            this.dom.btnClose = document.getElementById('stp-btn-close');
            this.dom.btnEditMode = document.getElementById('stp-btn-edit-mode');
            this.dom.btnUse = document.getElementById('stp-btn-use');
            this.dom.btnExport = document.getElementById('stp-btn-export');

            this.bindEvents();
        },

        bindEvents() {
            if (this.dom.btnClose) {
                this.dom.btnClose.addEventListener('click', () => this.attemptClose());
            }
            if (this.dom.btnCancel) {
                this.dom.btnCancel.addEventListener('click', () => this.attemptClose());
            }
            if (this.dom.backdrop) {
                this.dom.backdrop.addEventListener('click', (e) => {
                    if (e.target === this.dom.backdrop) this.attemptClose();
                });
            }
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen()) {
                    if (document.getElementById('stp-task-editor-backdrop').style.display === 'flex') {
                        this.closeTaskEditor();
                    } else if (document.getElementById('stp-import-confirm-backdrop').style.display === 'flex') {
                        this.cancelImport();
                    } else {
                        this.attemptClose();
                    }
                }
            });

            if (this.dom.detailsToggleBtn) {
                this.dom.detailsToggleBtn.addEventListener('click', () => {
                    const isHidden = this.dom.detailsDrawer.style.display === 'none';
                    this.dom.detailsDrawer.style.display = isHidden ? 'grid' : 'none';
                    this.dom.detailsToggleBtn.textContent = isHidden ? 'Hide Details ▴' : 'Edit Details ▾';
                });
            }

            if (this.dom.btnEditMode) {
                this.dom.btnEditMode.addEventListener('click', () => this.switchToEditMode());
            }

            if (this.dom.fileInput) {
                this.dom.fileInput.addEventListener('change', (e) => {
                    if (e.target.files && e.target.files[0]) {
                        this.uploadExcel(e.target.files[0]);
                        e.target.value = '';
                    }
                });
            }

            if (this.dom.btnSave) {
                this.dom.btnSave.addEventListener('click', () => {
                    const isProjectSelection = this.mode === 'select' || window.location.search.includes('select_for_project') || window.location.pathname.includes('/projects/create');
                    if (isProjectSelection && this.template && this.template.id) {
                        this.saveAndContinueToProject();
                    } else {
                        this.saveTemplate();
                    }
                });
            }

            if (this.dom.btnUse) {
                this.dom.btnUse.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.saveAndContinueToProject();
                });
            }
        },

        isOpen() {
            return this.dom.backdrop && this.dom.backdrop.classList.contains('active');
        },

        async attemptClose() {
            if (this.unsavedChanges) {
                if (!await AppDialog.confirm('You have unsaved changes in this template task plan. Discard changes?')) {
                    return;
                }
            }
            this.close();
        },

        close() {
            if (this.dom.backdrop) {
                this.dom.backdrop.classList.remove('active');
                this.dom.backdrop.style.display = 'none';
                document.body.style.overflow = '';
            }
            this.unsavedChanges = false;
        },

        showBackdrop() {
            if (this.dom.backdrop) {
                this.dom.backdrop.style.display = 'flex';
                void this.dom.backdrop.offsetWidth;
                this.dom.backdrop.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        },

        /* Date arithmetic helper methods */
        isWeekday(d) {
            const day = d.getDay();
            return day !== 0 && day !== 6;
        },

        addWorkingDays(startDateStr, days) {
            let d = startDateStr ? new Date(startDateStr + 'T00:00:00') : new Date();
            if (isNaN(d.getTime())) d = new Date();
            while (!this.isWeekday(d)) {
                d.setDate(d.getDate() + 1);
            }
            let added = 0;
            const target = Math.max(1, parseInt(days, 10) || 1) - 1;
            while (added < target) {
                d.setDate(d.getDate() + 1);
                if (this.isWeekday(d)) {
                    added++;
                }
            }
            return this.formatDateISO(d);
        },

        countWorkingDays(startStr, endStr) {
            if (!startStr || !endStr) return 1;
            let d1 = new Date(startStr + 'T00:00:00');
            let d2 = new Date(endStr + 'T00:00:00');
            if (isNaN(d1.getTime()) || isNaN(d2.getTime())) return 1;
            if (d1 > d2) return 1;

            let count = 0;
            let cur = new Date(d1.getTime());
            while (cur <= d2) {
                if (this.isWeekday(cur)) {
                    count++;
                }
                cur.setDate(cur.getDate() + 1);
            }
            return Math.max(1, count);
        },

        calculateTemplateEndDate(startStr, duration, unit) {
            let d = startStr ? new Date(startStr + 'T00:00:00') : new Date();
            if (isNaN(d.getTime())) d = new Date();
            const dur = Math.max(1, parseInt(duration, 10) || 1);

            if (unit === 'working_days') {
                return this.addWorkingDays(startStr, dur);
            } else if (unit === 'days') {
                d.setDate(d.getDate() + (dur - 1));
                return this.formatDateISO(d);
            } else if (unit === 'weeks') {
                d.setDate(d.getDate() + (dur * 7 - 1));
                return this.formatDateISO(d);
            } else if (unit === 'months') {
                d.setMonth(d.getMonth() + dur);
                d.setDate(d.getDate() - 1);
                return this.formatDateISO(d);
            }
            return this.addWorkingDays(startStr, dur);
        },

        formatDateISO(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        },

        formatDateDisplay(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr + 'T00:00:00');
            if (isNaN(d.getTime())) return dateStr;
            const day = String(d.getDate()).padStart(2, '0');
            const month = MONTHS[d.getMonth()];
            const year = d.getFullYear();
            return `${day} ${month} ${year}`;
        },

        /**
         * Generate date-aware planning periods (Day / Working Day / Week / Month).
         */
        generatePeriods(startDateStr, duration, unit, customLabels = {}) {
            const dur = Math.max(1, parseInt(duration, 10) || 1);
            let start = startDateStr ? new Date(startDateStr + 'T00:00:00') : new Date();
            if (isNaN(start.getTime())) start = new Date();

            const periods = [];
            const labels = customLabels || {};

            if (unit === 'days') {
                for (let i = 1; i <= dur; i++) {
                    const id = 'DAY-' + String(i).padStart(3, '0');
                    const cur = new Date(start.getTime());
                    cur.setDate(cur.getDate() + (i - 1));
                    const sysLabel = `Day ${i}`;
                    const userLabel = labels[id] || labels[i] || labels[sysLabel] || '';
                    const disp = userLabel ? `${sysLabel} – ${userLabel}` : sysLabel;
                    const iso = this.formatDateISO(cur);

                    periods.push({
                        id: id,
                        number: i,
                        system_number: i,
                        system_label: sysLabel,
                        label: userLabel,
                        name: userLabel || sysLabel,
                        display_name: disp,
                        starts_on: iso,
                        ends_on: iso,
                        date_range_display: this.formatDateDisplay(iso),
                        is_working_day: this.isWeekday(cur)
                    });
                }
            } else if (unit === 'working_days') {
                let cur = new Date(start.getTime());
                while (!this.isWeekday(cur)) {
                    cur.setDate(cur.getDate() + 1);
                }

                for (let i = 1; i <= dur; i++) {
                    const id = 'DAY-' + String(i).padStart(3, '0');
                    const sysLabel = `Working Day ${i}`;
                    const userLabel = labels[id] || labels[i] || labels[sysLabel] || '';
                    const disp = userLabel ? `${sysLabel} – ${userLabel}` : sysLabel;
                    const iso = this.formatDateISO(cur);

                    periods.push({
                        id: id,
                        number: i,
                        system_number: i,
                        system_label: sysLabel,
                        label: userLabel,
                        name: userLabel || sysLabel,
                        display_name: disp,
                        starts_on: iso,
                        ends_on: iso,
                        date_range_display: this.formatDateDisplay(iso),
                        is_working_day: true
                    });

                    if (i < dur) {
                        cur.setDate(cur.getDate() + 1);
                        while (!this.isWeekday(cur)) {
                            cur.setDate(cur.getDate() + 1);
                        }
                    }
                }
            } else if (unit === 'weeks') {
                for (let i = 1; i <= dur; i++) {
                    const id = 'WEEK-' + String(i).padStart(3, '0');
                    const wStart = new Date(start.getTime());
                    wStart.setDate(wStart.getDate() + (i - 1) * 7);
                    const wEnd = new Date(wStart.getTime());
                    wEnd.setDate(wEnd.getDate() + 6);
                    const sysLabel = `Week ${i}`;
                    const userLabel = labels[id] || labels[i] || labels[sysLabel] || '';
                    const disp = userLabel ? `${sysLabel} – ${userLabel}` : sysLabel;
                    const startIso = this.formatDateISO(wStart);
                    const endIso = this.formatDateISO(wEnd);
                    const rangeDisp = `${this.formatDateDisplay(startIso).replace(/\s\d{4}$/, '')} to ${this.formatDateDisplay(endIso)}`;

                    periods.push({
                        id: id,
                        number: i,
                        system_number: i,
                        system_label: sysLabel,
                        label: userLabel,
                        name: userLabel || sysLabel,
                        display_name: disp,
                        starts_on: startIso,
                        ends_on: endIso,
                        date_range_display: rangeDisp,
                        is_working_day: true
                    });
                }
            } else if (unit === 'months') {
                for (let i = 1; i <= dur; i++) {
                    const id = 'MONTH-' + String(i).padStart(3, '0');
                    const mStart = new Date(start.getTime());
                    mStart.setMonth(mStart.getMonth() + (i - 1));
                    const mEnd = new Date(start.getTime());
                    mEnd.setMonth(mEnd.getMonth() + i);
                    mEnd.setDate(mEnd.getDate() - 1);
                    const sysLabel = `Month ${i}`;
                    const userLabel = labels[id] || labels[i] || labels[sysLabel] || '';
                    const disp = userLabel ? `${sysLabel} – ${userLabel}` : sysLabel;
                    const startIso = this.formatDateISO(mStart);
                    const endIso = this.formatDateISO(mEnd);
                    const rangeDisp = `${this.formatDateDisplay(startIso).replace(/\s\d{4}$/, '')} to ${this.formatDateDisplay(endIso)}`;

                    periods.push({
                        id: id,
                        number: i,
                        system_number: i,
                        system_label: sysLabel,
                        label: userLabel,
                        name: userLabel || sysLabel,
                        display_name: disp,
                        starts_on: startIso,
                        ends_on: endIso,
                        date_range_display: rangeDisp,
                        is_working_day: true
                    });
                }
            }

            return periods;
        },

        /* Open Modal in Create Mode */
        openCreate(details) {
            this.mode = 'create';
            this.viewType = 'table';
            this.template = Object.assign({
                id: null,
                name: 'New Project Template',
                category: 'General',
                estimated_duration: 14,
                duration_unit: 'working_days',
                reference_start_date: this.formatDateISO(new Date()),
                working_hours: '09:00 - 17:00',
                description: '',
                planning_periods: {}
            }, details || {});

            if (!this.template.reference_start_date) {
                this.template.reference_start_date = this.formatDateISO(new Date());
            }

            this.periods = this.generatePeriods(
                this.template.reference_start_date,
                this.template.estimated_duration,
                this.template.duration_unit,
                this.template.planning_periods
            );

            // Automatically generate matching tasks for each day/period based on duration
            const times = (this.template.working_hours || '09:00 - 17:00').split('-');
            const startTime = times[0]?.trim() || '09:00';
            const endTime = times[1]?.trim() || '17:00';

            this.tasks = this.periods.map((p, idx) => {
                const num = String(idx + 1);
                return {
                    code: num,
                    parent_code: null,
                    title: `Task ${num}`,
                    description: '',
                    start_period: p.id,
                    end_period: p.id,
                    start_date: p.starts_on,
                    end_date: p.ends_on,
                    start_time: startTime,
                    end_time: endTime,
                    duration_days: 1,
                    suggested_role_id: null,
                    suggested_role_name: '',
                    dependency_code: idx > 0 ? String(idx) : '',
                    priority: 'medium',
                    sort_order: idx
                };
            });

            this.collapsedPeriods = {};
            this.unsavedChanges = false;
            this.render();
            this.showBackdrop();
        },

        /* Open Modal in View Mode */
        openView(templateData, tasksData = null) {
            this.mode = 'view';
            this.viewType = 'table';
            this.template = Object.assign({}, templateData);
            this.tasks = tasksData ? JSON.parse(JSON.stringify(tasksData)) : [];
            this.periods = this.generatePeriods(
                this.template.reference_start_date,
                this.template.estimated_duration,
                this.template.duration_unit,
                this.template.planning_periods
            );
            this.collapsedPeriods = {};
            this.unsavedChanges = false;
            this.render();
            this.showBackdrop();

            if (!tasksData && templateData.id) {
                this.fetchTemplateJson(templateData.id);
            }
        },

        /* Open Modal in Edit Mode */
        openEdit(templateData, tasksData = null) {
            this.mode = 'edit';
            this.viewType = 'table';
            this.template = Object.assign({}, templateData);
            this.tasks = tasksData ? JSON.parse(JSON.stringify(tasksData)) : [];
            this.periods = this.generatePeriods(
                this.template.reference_start_date,
                this.template.estimated_duration,
                this.template.duration_unit,
                this.template.planning_periods
            );
            this.collapsedPeriods = {};
            this.unsavedChanges = false;
            this.render();
            this.showBackdrop();

            if (!tasksData && templateData.id) {
                this.fetchTemplateJson(templateData.id, true);
            }
        },

        /* Open Modal in Select Mode (For Project Creation Flow) */
        openSelect(templateData, tasksData = null) {
            this.mode = this.canManage ? 'select' : 'view';
            this.viewType = 'table';
            this.template = Object.assign({}, templateData);
            this.tasks = tasksData ? JSON.parse(JSON.stringify(tasksData)) : [];
            this.periods = this.generatePeriods(
                this.template.reference_start_date,
                this.template.estimated_duration,
                this.template.duration_unit,
                this.template.planning_periods
            );
            this.collapsedPeriods = {};
            this.unsavedChanges = false;
            this.render();
            this.showBackdrop();

            if (!tasksData && templateData.id) {
                this.fetchTemplateJson(templateData.id, true);
            }
        },

        /* Switch View Mode to Edit Mode in-place */
        switchToEditMode() {
            if (!this.canManage) {
                alert('Only PMO Administrators are authorized to edit templates.');
                return;
            }
            this.mode = 'edit';
            this.render();
        },

        /* Fetch template JSON for popups */
        fetchTemplateJson(templateId, isEditMode = false) {
            fetch(`/templates/${templateId}/tasks-json`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.template = Object.assign({}, data.template);
                    this.tasks = data.tasks || [];
                    if (data.roles) this.roles = data.roles;
                    if (data.planning_periods) {
                        this.periods = data.planning_periods;
                    } else {
                        this.periods = this.generatePeriods(
                            this.template.reference_start_date,
                            this.template.estimated_duration,
                            this.template.duration_unit,
                            this.template.planning_periods
                        );
                    }
                    if (this.mode !== 'select') {
                        this.mode = isEditMode ? 'edit' : 'view';
                    }
                    this.reindexCodes();
                    this.render();
                } else {
                    alert('Unable to load template details: ' + (data.message || 'Error'));
                }
            })
            .catch(err => {
                console.error(err);
            });
        },

        /* Main Render Method */
        render() {
            this.renderHeader();
            this.renderToolbar();
            this.renderDetailsDrawer();
            this.renderSummary();

            if (this.dom.periodsContainer) this.dom.periodsContainer.style.display = 'none';
            if (this.dom.tableWrap) this.dom.tableWrap.style.display = 'block';
            this.renderTable();

            this.renderFooter();
        },

        renderHeader() {
            if(this.dom.modal)this.dom.modal.dataset.mode=this.mode;
            if (this.dom.title) {
                this.dom.title.textContent = this.template.name || 'Untitled Template';
            }

            const endDate = this.calculateTemplateEndDate(
                this.template.reference_start_date,
                this.template.estimated_duration,
                this.template.duration_unit
            );
            const unitLabel = (this.template.duration_unit || 'working_days').replace('_', ' ');
            const startDisplay = this.formatDateDisplay(this.template.reference_start_date);
            const endDisplay = this.formatDateDisplay(endDate);

            if (this.dom.scheduleBadge) {
                this.dom.scheduleBadge.textContent = `${this.template.estimated_duration} ${unitLabel} · ${startDisplay} – ${endDisplay}`;
            }

            if (this.dom.modeBadge) {
                if (this.mode === 'select') {
                    this.dom.modeBadge.className = 'stp-badge';
                    this.dom.modeBadge.style.background = '#E6F4EA';
                    this.dom.modeBadge.style.color = '#137333';
                    this.dom.modeBadge.style.borderColor = '#CEEAD6';
                    this.dom.modeBadge.style.fontWeight = '650';
                    this.dom.modeBadge.textContent = 'Preview & Customize';
                } else {
                    this.dom.modeBadge.style.background = '';
                    this.dom.modeBadge.style.color = '';
                    this.dom.modeBadge.style.borderColor = '';
                    this.dom.modeBadge.className = 'stp-badge ' + (
                        this.mode === 'create' ? 'stp-badge-draft' :
                        (this.mode === 'edit' ? 'stp-badge-edit' : 'stp-badge-view')
                    );
                    this.dom.modeBadge.textContent = this.mode === 'create' ? 'Draft' :
                        (this.mode === 'edit' ? 'Edit Mode' : 'View Mode');
                }
            }

            // Header Actions
            const isView = this.mode === 'view';
            const isSelect = this.mode === 'select';
            const isEditable = this.mode === 'create' || this.mode === 'edit' || isSelect;
            const isProjectSelection = isSelect || window.location.search.includes('select_for_project') || window.location.pathname.includes('/projects/create');

            if (this.dom.btnUse) {
                if (isSelect) {
                    this.dom.btnUse.style.display = 'none';
                    this.dom.btnUse.textContent = 'Save and continue';
                    this.dom.btnUse.href = '#';
                } else if (isView && isProjectSelection && this.canCreateProject && this.template.id && this.template.status!=='archived') {
                    this.dom.btnUse.style.display = 'inline-block';
                    this.dom.btnUse.textContent = isProjectSelection ? 'Continue with Template →' : 'Use Template';
                    this.dom.btnUse.href = this.projectCreateUrl(this.template.id);
                } else {
                    this.dom.btnUse.style.display = 'none';
                }
            }
            if (this.dom.btnEditMode) {
                this.dom.btnEditMode.style.display = (isView && this.canManage) ? 'inline-block' : 'none';
            }
            if (this.dom.btnExport) {
                this.dom.btnExport.style.display = ((isView || isSelect) && this.template.id) ? 'inline-block' : 'none';
                if (this.template.id) {
                    this.dom.btnExport.href = `/templates/${this.template.id}/export-excel`;
                }
            }
            if (this.dom.detailsToggleBtn) {
                this.dom.detailsToggleBtn.style.display = isEditable ? 'inline-block' : 'none';
            }
        },

        renderToolbar() {
            if (!this.dom.toolbar) return;
            const isSelect = this.mode === 'select';
            const isEditable = this.mode === 'create' || this.mode === 'edit' || isSelect;

            if (!isEditable) {
                this.dom.toolbar.style.display = 'none';
                const summary=this.dom.modal?.querySelector('.stp-summary-bar>div');
                if(summary && this.dom.summaryCounts)summary.prepend(this.dom.summaryCounts);
                return;
            }

            this.dom.toolbar.style.display = 'flex';
            this.dom.toolbar.innerHTML = `
                <div class="stp-toolbar-left">
                    <button type="button" class="btn btn-primary btn-sm" onclick="SmartTaskPlan.addTaskAtEnd()">+ Add task</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="SmartTaskPlan.triggerExcelUpload()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 16V3m-5 5 5-5 5 5M4 15v5h16v-5"/></svg>Upload Excel</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="SmartTaskPlan.downloadExcel()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3v13m-5-5 5 5 5-5M4 16v4h16v-4"/></svg>Download Excel</button>
                </div>
                <div class="stp-toolbar-meta" aria-label="Task count"></div>
            `;
            if(this.dom.summaryCounts)this.dom.toolbar.querySelector('.stp-toolbar-meta').append(this.dom.summaryCounts);
        },

        switchView(type) {
            this.viewType = type;
            this.render();
        },

        renderDetailsDrawer() {
            if (!this.dom.detailsDrawer) return;
            const isEditable = this.mode === 'create' || this.mode === 'edit' || this.mode === 'select';
            if (!isEditable) {
                this.dom.detailsDrawer.style.display = 'none';
                return;
            }

            if (this.mode === 'create') {
                this.dom.detailsDrawer.innerHTML = `<div><label for="stp_drawer_name">Template name</label><input id="stp_drawer_name" value="${this.escapeHtml(this.template.name || '')}" onchange="SmartTaskPlan.onDetailsChange('name', this.value)"></div><div><label for="stp_drawer_duration">Estimated duration (Working days)</label><input type="number" id="stp_drawer_duration" min="1" max="3650" step="1" value="${this.template.estimated_duration}" onchange="if(this.checkValidity()) SmartTaskPlan.onDetailsChange('duration', this.value)"><small>Days / excludes Saturdays and Sundays</small></div><div><label for="stp_drawer_start_date">Reference start date</label><input type="date" id="stp_drawer_start_date" value="${this.template.reference_start_date}" onchange="SmartTaskPlan.onDetailsChange('start_date', this.value)"></div>`;
                return;
            }
            this.dom.detailsDrawer.innerHTML = `
                <div>
                    <label>Template Name</label>
                    <input type="text" id="stp_drawer_name" value="${this.escapeHtml(this.template.name || '')}" onchange="SmartTaskPlan.onDetailsChange('name', this.value)">
                </div>
                <div>
                    <label>Category</label>
                    <select id="stp_drawer_category" onchange="SmartTaskPlan.onDetailsChange('category', this.value)">
                        ${['General', 'IT & Software', 'Infrastructure', 'Construction', 'Finance', 'Procurement', 'Operations', 'Marketing', 'HR'].map(c => `
                            <option value="${c}" ${this.template.category === c ? 'selected' : ''}>${c}</option>
                        `).join('')}
                    </select>
                </div>
                <div>
                    <label>Estimated Duration</label>
                    <div style="display: flex; gap: 6px;">
                        <input type="number" id="stp_drawer_duration" value="${this.template.estimated_duration || 14}" min="1" max="3650" style="width: 75px; text-align: center;" onchange="SmartTaskPlan.onDetailsChange('duration', this.value)">
                        <select id="stp_drawer_unit" style="flex: 1;" onchange="SmartTaskPlan.onDetailsChange('unit', this.value)">
                            <option value="working_days" ${this.template.duration_unit === 'working_days' ? 'selected' : ''}>Working Days</option>
                            <option value="days" ${this.template.duration_unit === 'days' ? 'selected' : ''}>Calendar Days</option>
                            <option value="weeks" ${this.template.duration_unit === 'weeks' ? 'selected' : ''}>Weeks</option>
                            <option value="months" ${this.template.duration_unit === 'months' ? 'selected' : ''}>Months</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label>Reference Start Date</label>
                    <input type="date" id="stp_drawer_start_date" value="${this.template.reference_start_date || this.formatDateISO(new Date())}" onchange="SmartTaskPlan.onDetailsChange('start_date', this.value)">
                </div>
                <div>
                    <label>Working Hours</label>
                    <select id="stp_drawer_hours" onchange="SmartTaskPlan.onDetailsChange('working_hours', this.value)">
                        <option value="09:00 - 17:00" ${this.template.working_hours === '09:00 - 17:00' ? 'selected' : ''}>09:00 AM – 05:00 PM (8h)</option>
                        <option value="08:30 - 17:00" ${this.template.working_hours === '08:30 - 17:00' ? 'selected' : ''}>08:30 AM – 05:00 PM</option>
                        <option value="08:00 - 16:30" ${this.template.working_hours === '08:00 - 16:30' ? 'selected' : ''}>08:00 AM – 04:30 PM</option>
                        <option value="09:00 - 18:00" ${this.template.working_hours === '09:00 - 18:00' ? 'selected' : ''}>09:00 AM – 06:00 PM</option>
                    </select>
                </div>
            `;
        },

        onDetailsChange(field, value) {
            this.unsavedChanges = true;
            if (field === 'name') {
                this.template.name = value.trim();
            } else if (field === 'category') {
                this.template.category = value;
            } else if (field === 'duration') {
                const oldDur = this.template.estimated_duration;
                const newDur = Math.max(1, parseInt(value, 10) || 1);
                this.template.estimated_duration = newDur;

                // Regenerate periods
                this.periods = this.generatePeriods(
                    this.template.reference_start_date,
                    newDur,
                    this.template.duration_unit,
                    this.template.planning_periods
                );

                // Warning if shortened and tasks fall outside
                if (newDur < oldDur) {
                    const maxPeriodId = this.periods[this.periods.length - 1]?.id;
                    const outOfRange = this.tasks.filter(t => t.start_period && t.start_period > maxPeriodId);
                    if (outOfRange.length > 0) {
                        alert(`Warning: ${outOfRange.length} task(s) are scheduled past the new duration. Please adjust them before final save.`);
                    }
                }
            } else if (field === 'unit') {
                this.template.duration_unit = value;
                this.periods = this.generatePeriods(
                    this.template.reference_start_date,
                    this.template.estimated_duration,
                    value,
                    this.template.planning_periods
                );
            } else if (field === 'start_date') {
                this.template.reference_start_date = value;
                this.periods = this.generatePeriods(
                    value,
                    this.template.estimated_duration,
                    this.template.duration_unit,
                    this.template.planning_periods
                );
            } else if (field === 'working_hours') {
                this.template.working_hours = value;
            }

            this.render();
        },

        renderSummary() {
            const taskCount = this.tasks.length;
            const mainTasks = this.tasks.filter(t => !t.parent_code).length;
            const subtasks = taskCount - mainTasks;

            if (this.dom.summaryCounts) {
                this.dom.summaryCounts.textContent = `${taskCount} tasks \u00b7 ${mainTasks} main${subtasks ? ` \u00b7 ${subtasks} subtasks` : ''}`;
            }

            if (this.dom.periodsCountBadge) {
                this.dom.periodsCountBadge.textContent = `${this.periods.length} Planning Periods`;
            }

            // Check if any tasks exceed planned template finish
            const endDate = this.calculateTemplateEndDate(
                this.template.reference_start_date,
                this.template.estimated_duration,
                this.template.duration_unit
            );

            let hasOverrun = false;
            for (const t of this.tasks) {
                if (t.end_date && t.end_date > endDate) {
                    hasOverrun = true;
                    break;
                }
            }

            if (this.dom.warningBanner) {
                this.dom.warningBanner.style.display = hasOverrun ? 'inline-flex' : 'none';
            }
        },

        /**
         * Render Main Content as Expandable Planning Periods
         */
        renderPeriods() {
            if (!this.dom.periodsContainer) return;
            const container = this.dom.periodsContainer;
            container.innerHTML = '';

            const isEditable = this.mode === 'create' || this.mode === 'edit' || this.mode === 'select';

            // Lookup map for fast task retrieval
            const tasksByStartPeriod = {};
            const spanningTasksByPeriod = {};

            this.periods.forEach(p => {
                tasksByStartPeriod[p.id] = [];
                spanningTasksByPeriod[p.id] = [];
            });

            this.tasks.forEach((t, idx) => {
                t._index = idx;
                // Identify start period
                let startPid = t.start_period;
                if (!startPid && t.start_date) {
                    const match = this.periods.find(p => t.start_date >= p.starts_on && t.start_date <= p.ends_on);
                    if (match) startPid = match.id;
                }
                if (!startPid && this.periods[0]) {
                    startPid = this.periods[0].id;
                }

                // Identify end period
                let endPid = t.end_period || startPid;
                if (!endPid && t.end_date) {
                    const match = this.periods.find(p => t.end_date >= p.starts_on && t.end_date <= p.ends_on);
                    if (match) endPid = match.id;
                }

                t.start_period = startPid;
                t.end_period = endPid;

                if (tasksByStartPeriod[startPid]) {
                    tasksByStartPeriod[startPid].push(t);
                }

                // If spanning multiple periods, track in subsequent periods
                if (startPid && endPid && startPid !== endPid) {
                    let inSpan = false;
                    this.periods.forEach(p => {
                        if (p.id === startPid) {
                            inSpan = true;
                        } else if (inSpan) {
                            spanningTasksByPeriod[p.id]?.push(t);
                            if (p.id === endPid) {
                                inSpan = false;
                            }
                        }
                    });
                }
            });

            // Render each period card
            this.periods.forEach(p => {
                const isCollapsed = !!this.collapsedPeriods[p.id];
                const startTasks = tasksByStartPeriod[p.id] || [];
                const contTasks = spanningTasksByPeriod[p.id] || [];
                const totalInPeriod = startTasks.length + contTasks.length;

                const card = document.createElement('div');
                card.className = 'stp-period-card ' + (isCollapsed ? 'stp-period-collapsed' : 'stp-period-expanded');
                card.setAttribute('data-period-id', p.id);

                // Header
                const header = document.createElement('div');
                header.className = 'stp-period-header';
                header.innerHTML = `
                    <div class="stp-period-title-wrap" onclick="SmartTaskPlan.togglePeriod('${p.id}')">
                        <span class="stp-period-chevron">${isCollapsed ? '▶' : '▼'}</span>
                        <span class="stp-period-fixed-id">${this.escapeHtml(p.system_label)}</span>
                        ${p.label ? `<span class="stp-period-label-text"> – ${this.escapeHtml(p.label)}</span>` : ''}
                        ${isEditable ? `<button type="button" class="stp-period-edit-label-btn" onclick="event.stopPropagation(); SmartTaskPlan.promptRenamePeriod('${p.id}')" title="Rename period label">✏</button>` : ''}
                        <span class="stp-period-date-range">${p.date_range_display}</span>
                    </div>
                    <div class="stp-period-actions">
                        <span class="stp-period-task-count">${totalInPeriod === 1 ? '1 Task' : totalInPeriod + ' Tasks'}</span>
                        ${isEditable ? `<button type="button" class="btn btn-outline btn-xs" onclick="event.stopPropagation(); SmartTaskPlan.openTaskEditor(null, '${p.id}')" style="height: 26px; font-size: 11.5px; padding: 0 8px;">+ Add Task</button>` : ''}
                    </div>
                `;

                // Content
                const content = document.createElement('div');
                content.className = 'stp-period-content';

                if (totalInPeriod === 0) {
                    content.innerHTML = `
                        <div class="stp-period-empty">
                            <span>No tasks added in this period.</span>
                            ${isEditable ? `<button type="button" class="btn btn-outline btn-xs" onclick="SmartTaskPlan.openTaskEditor(null, '${p.id}')" style="height: 24px; font-size: 11px;">+ Add Task</button>` : ''}
                        </div>
                    `;
                } else {
                    const table = document.createElement('table');
                    table.className = 'stp-table';
                    table.style.width = '100%';
                    table.innerHTML = `
                        <thead>
                            <tr style="background: transparent;">
                                <th style="width: 70px;">Code</th>
                                <th style="min-width: 220px;">Task Name</th>
                                <th style="width: 140px;">Period Range</th>
                                <th style="width: 110px;">Working Hours</th>
                                <th style="width: 120px;">Dependencies</th>
                                <th style="width: 140px;">Suggested Role</th>
                                ${isEditable ? '<th style="width: 80px; text-align: right;">Actions</th>' : ''}
                            </tr>
                        </thead>
                        <tbody></tbody>
                    `;

                    const tbody = table.querySelector('tbody');

                    // Render starting tasks
                    startTasks.forEach(t => {
                        const tr = document.createElement('tr');
                        const isSubtask = !!t.parent_code;
                        const spans = t.start_period !== t.end_period;
                        const role = this.roles.find(r => r.id == t.suggested_role_id) || { name: t.suggested_role_name || '' };

                        tr.innerHTML = `
                            <td><b style="color: #315F58; font-size: 12px;">${this.escapeHtml(t.code || '')}</b></td>
                            <td>
                                <div style="${isSubtask ? 'padding-left: 18px; color: #475569;' : 'font-weight: 600; color: #22272B;'} display: flex; align-items: center; flex-wrap: wrap;">
                                    ${isSubtask ? '<span style="color: #94A3B8; margin-right: 4px;">↳</span>' : ''}
                                    <span>${this.escapeHtml(t.title || '')}</span>
                                    ${spans ? `<span class="stp-span-badge">Spans ${this.getPeriodLabel(t.start_period)} – ${this.getPeriodLabel(t.end_period)} (${t.duration_days || 1}d)</span>` : ''}
                                </div>
                                ${t.description ? `<div style="font-size: 11px; color: #64748B; margin-top: 2px; ${isSubtask ? 'padding-left: 18px;' : ''}">${this.escapeHtml(t.description)}</div>` : ''}
                            </td>
                            <td style="font-size: 11.5px; color: #475569;">
                                ${this.getPeriodLabel(t.start_period)} ${spans ? '→ ' + this.getPeriodLabel(t.end_period) : ''}
                            </td>
                            <td style="font-size: 11.5px; color: #64748B;">
                                ${t.start_time || '09:00'} – ${t.end_time || '17:00'}
                            </td>
                            <td style="font-size: 11.5px; color: #475569;">
                                ${t.dependency_code ? `<span style="background: #F1F5F9; padding: 2px 6px; border-radius: 4px; font-weight: 550;">${this.escapeHtml(t.dependency_code)}</span>` : '<span style="color: #94A3B8;">—</span>'}
                            </td>
                            <td>
                                ${role && role.name ? `<span style="font-size: 11px; font-weight: 550; background: #EBF3F1; color: #315F58; padding: 2px 7px; border-radius: 4px;">${this.escapeHtml(role.name)}</span>` : '<span style="color: #94A3B8; font-size: 11px;">—</span>'}
                            </td>
                            ${isEditable ? `
                                <td style="text-align: right; white-space: nowrap;">
                                    ${!isSubtask ? `<button type="button" class="btn btn-outline btn-xs" onclick="SmartTaskPlan.openTaskEditor(null, '${t.start_period}', '${t.code}')" title="Add Subtask" style="padding: 2px 5px; font-size: 10.5px; margin-right: 3px;">+ Sub</button>` : ''}
                                    <button type="button" class="btn btn-outline btn-xs" onclick="SmartTaskPlan.openTaskEditor(${t._index})" title="Edit Task" style="padding: 2px 6px; font-size: 11px;">✏</button>
                                    <button type="button" class="btn btn-outline btn-xs" onclick="SmartTaskPlan.deleteTask(${t._index})" title="Delete Task" style="padding: 2px 6px; font-size: 11px; color: #DC2626;">🗑</button>
                                </td>
                            ` : ''}
                        `;
                        tbody.appendChild(tr);
                    });

                    // Render spanning continuing tasks
                    contTasks.forEach(t => {
                        const tr = document.createElement('tr');
                        tr.style.background = '#FAFCFB';
                        tr.innerHTML = `
                            <td><span style="color: #94A3B8; font-size: 11.5px;">${this.escapeHtml(t.code || '')}</span></td>
                            <td>
                                <div style="color: #64748B; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                                    <span style="font-style: italic;">${this.escapeHtml(t.title || '')}</span>
                                    <span style="font-size: 10.5px; background: #F1F5F9; color: #475569; padding: 1px 6px; border-radius: 4px;">(Continued from ${this.getPeriodLabel(t.start_period)})</span>
                                </div>
                            </td>
                            <td style="font-size: 11px; color: #64748B;">Through ${this.getPeriodLabel(t.end_period)}</td>
                            <td style="font-size: 11px; color: #94A3B8;">${t.start_time || '09:00'} – ${t.end_time || '17:00'}</td>
                            <td style="font-size: 11px; color: #94A3B8;">${t.dependency_code || '—'}</td>
                            <td><span style="color: #94A3B8; font-size: 11px;">—</span></td>
                            ${isEditable ? `
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-outline btn-xs" onclick="SmartTaskPlan.openTaskEditor(${t._index})" title="Edit Original Task" style="padding: 2px 6px; font-size: 11px;">✏</button>
                                </td>
                            ` : ''}
                        `;
                        tbody.appendChild(tr);
                    });

                    content.appendChild(table);
                }

                card.appendChild(header);
                card.appendChild(content);
                container.appendChild(card);
            });
        },

        getPeriodLabel(periodId) {
            if (!periodId) return '—';
            const p = this.periods.find(item => item.id === periodId);
            return p ? (p.label ? `${p.system_label} (${p.label})` : p.system_label) : periodId;
        },

        togglePeriod(periodId) {
            this.collapsedPeriods[periodId] = !this.collapsedPeriods[periodId];
            const card = document.querySelector(`.stp-period-card[data-period-id="${periodId}"]`);
            if (card) {
                if (this.collapsedPeriods[periodId]) {
                    card.classList.add('stp-period-collapsed');
                    card.classList.remove('stp-period-expanded');
                    const chev = card.querySelector('.stp-period-chevron');
                    if (chev) chev.textContent = '▶';
                } else {
                    card.classList.remove('stp-period-collapsed');
                    card.classList.add('stp-period-expanded');
                    const chev = card.querySelector('.stp-period-chevron');
                    if (chev) chev.textContent = '▼';
                }
            }
        },

        areAllPeriodsCollapsed() {
            return this.periods.every(p => !!this.collapsedPeriods[p.id]);
        },

        toggleAllPeriods() {
            const allCollapsed = this.areAllPeriodsCollapsed();
            this.periods.forEach(p => {
                this.collapsedPeriods[p.id] = !allCollapsed;
            });
            this.renderPeriods();
            this.renderToolbar();
        },

        async promptRenamePeriod(periodId) {
            const p = this.periods.find(item => item.id === periodId);
            if (!p) return;

            const current = p.label || '';
            const newLabel = await AppDialog.prompt(`Enter descriptive label for ${p.system_label} (e.g. Requirements Gathering):`, current);
            if (newLabel !== null) {
                const trimmed = newLabel.trim();
                this.template.planning_periods = this.template.planning_periods || {};
                this.template.planning_periods[periodId] = trimmed;
                p.label = trimmed;
                p.display_name = trimmed ? `${p.system_label} – ${trimmed}` : p.system_label;
                this.unsavedChanges = true;
                this.render();
            }
        },

        /* Table View Render */
        renderTable() {
            if (!this.dom.tableBody) return;
            const tbody = this.dom.tableBody;
            tbody.innerHTML = '';

            const isEditable = this.mode === 'create' || this.mode === 'edit' || this.mode === 'select';

            if (this.tasks.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 36px 20px; color: #94A3B8;">No tasks added yet. Click "+ Add Task" or "Upload Excel" to populate the task plan.</td></tr>`;
                return;
            }

            this.tasks.forEach((t, idx) => {
                const tr = document.createElement('tr');
                const isSubtask = !!t.parent_code;
                const role = this.roles.find(r => r.id == t.suggested_role_id) || { name: t.suggested_role_name || '' };

                tr.innerHTML = `
                    <td style="text-align: center; vertical-align: middle; padding: 6px;">
                        <span style="font-weight: ${isSubtask ? '600' : '750'}; color: ${isSubtask ? '#64748B' : '#315F58'}; font-size: ${isSubtask ? '11.5px' : '12.5px'};">
                            ${this.escapeHtml(t.code || String(idx + 1))}
                        </span>
                    </td>
                    <td style="padding: 6px 8px; vertical-align: middle;">
                        <div style="display: flex; align-items: center; gap: 6px; ${isSubtask ? 'padding-left: 20px;' : ''}">
                            ${isSubtask ? '<span style="color: #64748B; font-weight: 700; font-size: 13px;">↳</span> <span style="background: #E2E8F0; color: #475569; font-size: 10px; padding: 1px 6px; border-radius: 3px; font-weight: 600;">Subtask</span>' : ''}
                            ${isEditable ? `
                                <input type="text" value="${this.escapeHtml(t.title || '')}" placeholder="Task name..." oninput="SmartTaskPlan.onInlineTitleChange(${idx}, this.value)" style="flex: 1; height: 30px; border: 1px solid #CBD5E1; border-radius: 4px; padding: 0 8px; font-size: 12.5px; ${!isSubtask ? 'font-weight: 650; color: #22272B;' : 'color: #334155;'}">
                            ` : `
                                <span style="${!isSubtask ? 'font-weight: 650; color: #22272B;' : 'color: #334155; font-size: 12px;'}">
                                    ${this.escapeHtml(t.title || '')}
                                </span>
                            `}
                        </div>
                    </td>
                    <td style="padding: 6px 6px; vertical-align: middle;">
                        ${isEditable ? `
                            <div style="display: flex; gap: 4px; align-items: center;">
                                <input type="date" value="${t.start_date || ''}" onchange="SmartTaskPlan.onInlineStartDateChange(${idx}, this.value)" style="flex: 1; min-width: 108px; height: 30px; font-size: 11.5px; border: 1px solid #CBD5E1; border-radius: 4px; padding: 0 4px; background: #FFFFFF; color: #22272B;">
                                <input type="time" value="${t.start_time || '09:00'}" onchange="SmartTaskPlan.onInlineStartTimeChange(${idx}, this.value)" style="width: 76px; height: 30px; font-size: 11.5px; border: 1px solid #CBD5E1; border-radius: 4px; padding: 0 4px; background: #FFFFFF; color: #22272B;">
                            </div>
                        ` : `
                            <div style="font-size: 12px; color: #334155; white-space: nowrap;">
                                ${this.formatDateDisplay(t.start_date)} <span style="color: #64748B; font-size: 11px;">${t.start_time || '09:00'}</span>
                            </div>
                        `}
                    </td>
                    <td style="padding: 6px 6px; vertical-align: middle;">
                        ${isEditable ? `
                            <div style="display: flex; gap: 4px; align-items: center;">
                                <input type="date" value="${t.end_date || ''}" onchange="SmartTaskPlan.onInlineEndDateChange(${idx}, this.value)" style="flex: 1; min-width: 108px; height: 30px; font-size: 11.5px; border: 1px solid #CBD5E1; border-radius: 4px; padding: 0 4px; background: #FFFFFF; color: #22272B;">
                                <input type="time" value="${t.end_time || '17:00'}" onchange="SmartTaskPlan.onInlineEndTimeChange(${idx}, this.value)" style="width: 76px; height: 30px; font-size: 11.5px; border: 1px solid #CBD5E1; border-radius: 4px; padding: 0 4px; background: #FFFFFF; color: #22272B;">
                            </div>
                        ` : `
                            <div style="font-size: 12px; color: #334155; white-space: nowrap;">
                                ${this.formatDateDisplay(t.end_date)} <span style="color: #64748B; font-size: 11px;">${t.end_time || '17:00'}</span>
                            </div>
                        `}
                    </td>
                    <td style="padding: 6px 4px; vertical-align: middle; text-align: center;">
                        ${isEditable ? `
                            <input type="number" min="1" max="365" value="${t.duration_days || 1}" oninput="SmartTaskPlan.onInlineDurationChange(${idx}, this.value)" style="width: 52px; height: 30px; text-align: center; border: 1px solid #CBD5E1; border-radius: 4px; font-size: 12px; margin: 0 auto; display: block;">
                        ` : `
                            <span style="font-size: 12px;">${t.duration_days || 1}d</span>
                        `}
                    </td>
                    <td style="padding: 6px 6px; vertical-align: middle;">
                        ${isEditable ? `
                            <select class="stp-inline-select" onchange="SmartTaskPlan.onInlineDependencyChange(${idx}, this.value)" style="height: 30px; font-size: 11px; border: 1px solid #CBD5E1; border-radius: 4px; padding: 0 4px; background: #FFFFFF; width: 100%; color: #334155;">
                                <option value="">— None —</option>
                                ${this.tasks.slice(0, idx).map(pt => `
                                    <option value="${pt.code}" ${t.dependency_code === pt.code ? 'selected' : ''}>
                                        #${pt.code} ${this.escapeHtml(pt.title || '')}
                                    </option>
                                `).join('')}
                            </select>
                        ` : `
                            <span style="font-size: 12px; color: #475569;">${t.dependency_code ? '#' + t.dependency_code : '—'}</span>
                        `}
                    </td>
                    <td style="padding: 6px 6px; vertical-align: middle;">
                        ${isEditable ? `
                            <select class="stp-inline-select" onchange="SmartTaskPlan.onInlineRoleChange(${idx}, this.value)" style="height: 30px; font-size: 11px; border: 1px solid #CBD5E1; border-radius: 4px; padding: 0 4px; background: #FFFFFF; width: 100%; color: #334155;">
                                <option value="">— Unassigned —</option>
                                ${this.roles.map(r => `
                                    <option value="${r.id}" ${t.suggested_role_id == r.id ? 'selected' : ''}>
                                        ${this.escapeHtml(r.name)}
                                    </option>
                                `).join('')}
                            </select>
                        ` : `
                            <span style="font-size: 12px; color: #475569;">${role?.name || '—'}</span>
                        `}
                    </td>
                    <td style="padding: 6px 8px; vertical-align: middle; text-align: right; white-space: nowrap;">
                        ${isEditable ? `
                            <div style="display: flex; gap: 4px; justify-content: flex-end; align-items: center;">
                                <button type="button" class="btn btn-outline btn-xs" onclick="SmartTaskPlan.addSubtask(${idx})" title="Add Subtask under this task" style="font-size: 10.5px; padding: 2px 6px; color: #315F58; border-color: #315F58; font-weight: 600; height: 26px;">+ Subtask</button>
                                <button type="button" class="btn btn-outline btn-xs" onclick="SmartTaskPlan.addTaskBelow(${idx})" title="Add Task Below" style="font-size: 10.5px; padding: 2px 6px; height: 26px;">+ Row</button>
                                <button type="button" class="btn btn-outline btn-xs" onclick="SmartTaskPlan.deleteTask(${idx})" title="Delete" style="color: #DC2626; border-color: #FECACA; padding: 2px 6px; height: 26px;">🗑</button>
                            </div>
                        ` : ''}
                    </td>
                `;
                tr.classList.toggle('stp-subtask-row',isSubtask);
                const labels=['Task number','Task name','Start date','Finish date','Duration in working days','Depends on','Suggested role','Task actions'];
                [...tr.cells].forEach((cell,column)=>{
                    cell.dataset.label=labels[column];
                    cell.querySelectorAll('input,select').forEach(control=>{
                        const label=control.type==='time'?(column===2?'Start time':'Finish time'):labels[column];
                        control.setAttribute('aria-label',label+' for task '+(t.code || idx+1));
                        if(control.tagName==='SELECT')control.title=control.selectedOptions[0]?.textContent.trim() || label;
                    });
                });
                tr.cells[1].querySelector('input')?.classList.add('stp-task-name-input');
                for(const column of [2,3])tr.cells[column].querySelector('div')?.classList.add('stp-datetime');
                const actions=tr.cells[7].querySelectorAll('button');
                const titles=['Add subtask','Add task below','Delete task'];
                actions.forEach((button,index)=>{button.classList.add('stp-row-action');button.setAttribute('aria-label',titles[index]+' '+(t.code || idx+1));});
                if(actions[0])actions[0].textContent='+ Subtask';
                if(actions[1]){actions[1].textContent='+';actions[1].classList.add('stp-row-insert');}
                if(actions[2]){actions[2].classList.add('stp-row-delete');actions[2].innerHTML='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg>';}
                tbody.appendChild(tr);
            });
        },

        onInlineTitleChange(index, value) {
            if (this.tasks[index]) {
                this.tasks[index].title = value;
                this.unsavedChanges = true;
                this.renderFooter();
            }
        },

        onInlineStartDateChange(index, newDate) {
            if (this.tasks[index]) {
                this.tasks[index].start_date = newDate;
                if (newDate) {
                    const dur = this.tasks[index].duration_days || 1;
                    const isWorking = (this.template.duration_unit || 'working_days') === 'working_days';
                    if (isWorking) {
                        this.tasks[index].end_date = this.addWorkingDays(newDate, dur);
                    } else {
                        const d = new Date(newDate + 'T00:00:00');
                        d.setDate(d.getDate() + (dur - 1));
                        this.tasks[index].end_date = this.formatDateISO(d);
                    }
                }
                this.unsavedChanges = true;
                this.render();
            }
        },

        onInlineStartTimeChange(index, newTime) {
            if (this.tasks[index]) {
                this.tasks[index].start_time = newTime;
                this.unsavedChanges = true;
                this.renderFooter();
            }
        },

        onInlineEndDateChange(index, newEndDate) {
            if (this.tasks[index]) {
                this.tasks[index].end_date = newEndDate;
                if (this.tasks[index].start_date && newEndDate) {
                    const isWorking = (this.template.duration_unit || 'working_days') === 'working_days';
                    if (isWorking) {
                        this.tasks[index].duration_days = this.countWorkingDays(this.tasks[index].start_date, newEndDate);
                    } else {
                        const d1 = new Date(this.tasks[index].start_date + 'T00:00:00');
                        const d2 = new Date(newEndDate + 'T00:00:00');
                        const diff = Math.max(1, Math.round((d2 - d1) / (1000 * 60 * 60 * 24)) + 1);
                        this.tasks[index].duration_days = diff;
                    }
                }
                this.unsavedChanges = true;
                this.render();
            }
        },

        onInlineEndTimeChange(index, newTime) {
            if (this.tasks[index]) {
                this.tasks[index].end_time = newTime;
                this.unsavedChanges = true;
                this.renderFooter();
            }
        },

        onInlineDurationChange(index, value) {
            if (this.tasks[index]) {
                const days = Math.max(1, parseInt(value, 10) || 1);
                this.tasks[index].duration_days = days;
                if (this.tasks[index].start_date) {
                    const isWorking = (this.template.duration_unit || 'working_days') === 'working_days';
                    if (isWorking) {
                        this.tasks[index].end_date = this.addWorkingDays(this.tasks[index].start_date, days);
                    } else {
                        const d = new Date(this.tasks[index].start_date + 'T00:00:00');
                        d.setDate(d.getDate() + (days - 1));
                        this.tasks[index].end_date = this.formatDateISO(d);
                    }
                }
                this.unsavedChanges = true;
                this.renderSummary();
                this.render();
            }
        },

        onInlineDependencyChange(index, depCode) {
            if (this.tasks[index]) {
                this.tasks[index].dependency_code = depCode;
                this.unsavedChanges = true;
                this.renderFooter();
            }
        },

        onInlineRoleChange(index, roleId) {
            if (this.tasks[index]) {
                this.tasks[index].suggested_role_id = roleId ? parseInt(roleId, 10) : null;
                const r = this.roles.find(item => item.id == roleId);
                this.tasks[index].suggested_role_name = r ? r.name : '';
                this.unsavedChanges = true;
                this.renderFooter();
            }
        },

        addSubtask(parentIndex) {
            const parent = this.tasks[parentIndex];
            if (!parent) return;

            const targetParentCode = parent.parent_code || parent.code;
            const existingSubs = this.tasks.filter(t => t.parent_code === targetParentCode);
            const subNum = `${targetParentCode}.${existingSubs.length + 1}`;

            const newSubtask = {
                code: subNum,
                parent_code: targetParentCode,
                title: `Subtask ${subNum}`,
                description: '',
                start_period: parent.start_period || null,
                end_period: parent.end_period || null,
                start_date: parent.start_date || this.formatDateISO(new Date()),
                end_date: parent.end_date || this.formatDateISO(new Date()),
                start_time: parent.start_time || '09:00',
                end_time: parent.end_time || '17:00',
                duration_days: parent.duration_days || 1,
                suggested_role_id: null,
                suggested_role_name: '',
                dependency_code: '',
                priority: 'medium',
                sort_order: parentIndex + existingSubs.length + 1
            };

            let insertPos = parentIndex;
            for (let i = parentIndex + 1; i < this.tasks.length; i++) {
                if (this.tasks[i].parent_code === targetParentCode) {
                    insertPos = i;
                } else {
                    break;
                }
            }

            this.tasks.splice(insertPos + 1, 0, newSubtask);
            this.reindexCodes();
            this.unsavedChanges = true;
            this.render();
        },

        addTaskBelow(index) {
            const current = this.tasks[index];
            const isWorking = (this.template.duration_unit || 'working_days') === 'working_days';
            const startDate = current?.end_date 
                ? (isWorking ? this.addWorkingDays(current.end_date, 2) : this.formatDateISO(new Date(new Date(current.end_date + 'T00:00:00').getTime() + 86400000)))
                : (this.template.reference_start_date || this.formatDateISO(new Date()));
            const endDate = isWorking ? this.addWorkingDays(startDate, 1) : startDate;
            const times = (this.template.working_hours || '09:00 - 17:00').split('-');

            const newTask = {
                code: String(this.tasks.length + 1),
                parent_code: null,
                title: `Task`,
                description: '',
                start_period: null,
                end_period: null,
                start_date: startDate,
                end_date: endDate,
                start_time: times[0]?.trim() || '09:00',
                end_time: times[1]?.trim() || '17:00',
                duration_days: 1,
                suggested_role_id: null,
                suggested_role_name: '',
                dependency_code: current ? current.code : '',
                priority: 'medium',
                sort_order: index + 1
            };

            this.tasks.splice(index + 1, 0, newTask);
            this.reindexCodes();
            newTask.title = `Task ${newTask.code}`;
            this.unsavedChanges = true;
            this.render();
        },

        addTaskAtEnd() {
            const lastTask = this.tasks[this.tasks.length - 1];
            const isWorking = (this.template.duration_unit || 'working_days') === 'working_days';
            const startDate = lastTask?.end_date
                ? (isWorking ? this.addWorkingDays(lastTask.end_date, 2) : this.formatDateISO(new Date(new Date(lastTask.end_date + 'T00:00:00').getTime() + 86400000)))
                : (this.template.reference_start_date || this.formatDateISO(new Date()));
            const endDate = isWorking ? this.addWorkingDays(startDate, 1) : startDate;
            const mainTasksCount = this.tasks.filter(t => !t.parent_code).length;
            const nextNum = String(mainTasksCount + 1);
            const times = (this.template.working_hours || '09:00 - 17:00').split('-');

            const newTask = {
                code: nextNum,
                parent_code: null,
                title: `Task ${nextNum}`,
                description: '',
                start_period: null,
                end_period: null,
                start_date: startDate,
                end_date: endDate,
                start_time: times[0]?.trim() || '09:00',
                end_time: times[1]?.trim() || '17:00',
                duration_days: 1,
                suggested_role_id: null,
                suggested_role_name: '',
                dependency_code: lastTask ? lastTask.code : '',
                priority: 'medium',
                sort_order: this.tasks.length
            };

            this.tasks.push(newTask);
            this.reindexCodes();
            this.unsavedChanges = true;
            this.render();
        },

        renderFooter() {
            if (this.dom.unsavedText) {
                this.dom.unsavedText.textContent = this.unsavedChanges ? 'Unsaved changes' : (this.mode==='create' ? 'Draft - not saved yet' : 'No unsaved changes');
                this.dom.unsavedText.style.color = this.unsavedChanges ? '#D97706' : '#64748B';
                this.dom.unsavedText.style.fontWeight = this.unsavedChanges ? '650' : '400';
            }

            const isSelect = this.mode === 'select';
            const isEditable = this.mode === 'create' || this.mode === 'edit' || isSelect;
            const isProjectSelection = isSelect || window.location.search.includes('select_for_project') || window.location.pathname.includes('/projects/create');

            if (this.dom.btnSave) {
                if (this.localPlanSave || isSelect || (isEditable && isProjectSelection)) {
                    this.dom.btnSave.style.display = 'inline-block';
                    this.dom.btnSave.textContent = 'Save and continue';
                    this.dom.btnSave.style.background = '#315F58';
                    this.dom.btnSave.style.color = '#FFFFFF';
                    this.dom.btnSave.style.fontWeight = '650';
                } else {
                    this.dom.btnSave.style.display = isEditable ? 'inline-block' : 'none';
                    this.dom.btnSave.textContent = 'Save Template';
                    this.dom.btnSave.style.background = '#315F58';
                    this.dom.btnSave.style.color = '#FFFFFF';
                    this.dom.btnSave.style.fontWeight = '650';
                }
            }

            if (this.dom.btnUse && (isSelect || isProjectSelection)) {
                this.dom.btnUse.textContent = this.unsavedChanges ? 'Save & Continue with Template →' : 'Continue with Template →';
            }
        },

        /* Task Editor Logic */
        openTaskEditor(taskIndex = null, defaultPeriodId = null, defaultParentCode = null) {
            const editorBackdrop = document.getElementById('stp-task-editor-backdrop');
            const editorTitle = document.getElementById('stp-task-editor-title');
            const form = document.getElementById('stp-task-form');

            if (!editorBackdrop || !form) return;

            document.getElementById('task_edit_index').value = taskIndex !== null ? taskIndex : '';
            editorTitle.textContent = taskIndex !== null ? 'Edit Task' : (defaultParentCode ? `Add Subtask under Task ${defaultParentCode}` : 'Add Task');

            // Populate parent task dropdown
            const parentSelect = document.getElementById('task_edit_parent');
            parentSelect.innerHTML = '<option value="">None (Top-Level Main Task)</option>';
            this.tasks.forEach((t, i) => {
                if (!t.parent_code && (taskIndex === null || taskIndex !== i)) {
                    parentSelect.innerHTML += `<option value="${t.code}">${t.code}. ${this.escapeHtml(t.title)}</option>`;
                }
            });

            // Populate period dropdowns
            const startPSelect = document.getElementById('task_edit_start_period');
            const endPSelect = document.getElementById('task_edit_end_period');
            startPSelect.innerHTML = '';
            endPSelect.innerHTML = '';

            this.periods.forEach(p => {
                const optText = `${p.system_label}${p.label ? ' – ' + p.label : ''} (${p.date_range_display})`;
                startPSelect.innerHTML += `<option value="${p.id}">${optText}</option>`;
                endPSelect.innerHTML += `<option value="${p.id}">${optText}</option>`;
            });

            // Populate role dropdown
            const roleSelect = document.getElementById('task_edit_role');
            roleSelect.innerHTML = '<option value="">None</option>';
            this.roles.forEach(r => {
                roleSelect.innerHTML += `<option value="${r.id}">${this.escapeHtml(r.name)}</option>`;
            });

            if (taskIndex !== null) {
                const t = this.tasks[taskIndex];
                document.getElementById('task_edit_title').value = t.title || '';
                parentSelect.value = t.parent_code || '';
                startPSelect.value = t.start_period || (this.periods[0]?.id || '');
                endPSelect.value = t.end_period || startPSelect.value;
                document.getElementById('task_edit_start_time').value = t.start_time || '09:00';
                document.getElementById('task_edit_end_time').value = t.end_time || '17:00';
                document.getElementById('task_edit_duration').value = t.duration_days || 1;
                document.getElementById('task_edit_dependency').value = t.dependency_code || '';
                roleSelect.value = t.suggested_role_id || '';
                document.getElementById('task_edit_description').value = t.description || '';
            } else {
                form.reset();
                if (defaultParentCode) {
                    parentSelect.value = defaultParentCode;
                }
                if (defaultPeriodId) {
                    startPSelect.value = defaultPeriodId;
                    endPSelect.value = defaultPeriodId;
                } else if (this.periods[0]) {
                    startPSelect.value = this.periods[0].id;
                    endPSelect.value = this.periods[0].id;
                }
                document.getElementById('task_edit_start_time').value = '09:00';
                document.getElementById('task_edit_end_time').value = '17:00';
                document.getElementById('task_edit_duration').value = 1;
            }

            editorBackdrop.style.display = 'flex';
            document.getElementById('task_edit_title').focus();
        },

        closeTaskEditor() {
            const editorBackdrop = document.getElementById('stp-task-editor-backdrop');
            if (editorBackdrop) editorBackdrop.style.display = 'none';
        },

        onEditorPeriodChange() {
            const startPId = document.getElementById('task_edit_start_period').value;
            const endPSelect = document.getElementById('task_edit_end_period');
            const endPId = endPSelect.value;

            const p1 = this.periods.find(p => p.id === startPId);
            const p2 = this.periods.find(p => p.id === endPId);

            if (p1 && p2) {
                if (p2.number < p1.number) {
                    endPSelect.value = startPId;
                }
                const dur = this.countWorkingDays(p1.starts_on, (p2.number < p1.number ? p1.ends_on : p2.ends_on));
                document.getElementById('task_edit_duration').value = Math.max(1, dur);
            }
        },

        saveTaskFromEditor() {
            const indexStr = document.getElementById('task_edit_index').value;
            const title = document.getElementById('task_edit_title').value.trim();
            const parentCode = document.getElementById('task_edit_parent').value.trim() || null;
            const startPId = document.getElementById('task_edit_start_period').value;
            const endPId = document.getElementById('task_edit_end_period').value || startPId;
            const startTime = document.getElementById('task_edit_start_time').value || '09:00';
            const endTime = document.getElementById('task_edit_end_time').value || '17:00';
            const duration = parseInt(document.getElementById('task_edit_duration').value, 10) || 1;
            const dependency = document.getElementById('task_edit_dependency').value.trim();
            const roleId = document.getElementById('task_edit_role').value || null;
            const description = document.getElementById('task_edit_description').value.trim();

            if (!title) {
                alert('Please enter a Task Name.');
                return;
            }

            const p1 = this.periods.find(p => p.id === startPId);
            const p2 = this.periods.find(p => p.id === endPId);

            const startDate = p1 ? p1.starts_on : this.template.reference_start_date;
            const endDate = p2 ? p2.ends_on : startDate;

            const roleObj = this.roles.find(r => r.id == roleId);

            const taskItem = {
                title: title,
                parent_code: parentCode,
                start_period: startPId,
                end_period: endPId,
                start_date: startDate,
                start_time: startTime,
                end_date: endDate,
                end_time: endTime,
                duration_days: duration,
                dependency_code: dependency,
                suggested_role_id: roleId,
                suggested_role_name: roleObj?.name || '',
                description: description,
                priority: 'medium'
            };

            if (indexStr !== '') {
                const idx = parseInt(indexStr, 10);
                taskItem.code = this.tasks[idx].code;
                this.tasks[idx] = Object.assign(this.tasks[idx], taskItem);
            } else {
                taskItem.code = '';
                this.tasks.push(taskItem);
            }

            this.reindexCodes();
            this.unsavedChanges = true;
            this.closeTaskEditor();
            this.render();
        },

        async deleteTask(index) {
            const task = this.tasks[index];
            if (!task) return;

            const isParent = !task.parent_code;
            const subtasks = this.tasks.filter(sub => sub.parent_code === task.code);
            const msg = isParent && subtasks.length > 0
                ? `Delete task "${task.title}" and its ${subtasks.length} subtask(s)?`
                : `Delete task "${task.title}"?`;

            if (await AppDialog.confirm(msg)) {
                if (isParent) {
                    this.tasks = this.tasks.filter(t => t.code !== task.code && t.parent_code !== task.code);
                } else {
                    this.tasks.splice(index, 1);
                }
                this.reindexCodes();
                this.unsavedChanges = true;
                this.render();
            }
        },

        /* Hierarchical Task Numbering */
        reindexCodes() {
            let mainIndex = 1;
            const subtaskCounts = {};
            const codeMap = {};

            // First pass: reassign main task codes
            this.tasks.forEach(t => {
                if (!t.parent_code) {
                    const oldCode = t.code;
                    const newCode = String(mainIndex++);
                    t.code = newCode;
                    codeMap[oldCode] = newCode;
                    subtaskCounts[newCode] = 1;
                }
            });

            // Second pass: reassign subtasks
            this.tasks.forEach(t => {
                if (t.parent_code) {
                    if (codeMap[t.parent_code]) {
                        t.parent_code = codeMap[t.parent_code];
                    }
                    const count = subtaskCounts[t.parent_code] || 1;
                    const oldCode = t.code;
                    const newCode = `${t.parent_code}.${count}`;
                    t.code = newCode;
                    codeMap[oldCode] = newCode;
                    subtaskCounts[t.parent_code] = count + 1;
                }
            });

            // Third pass: update dependencies if code changed
            this.tasks.forEach(t => {
                if (t.dependency_code && codeMap[t.dependency_code]) {
                    t.dependency_code = codeMap[t.dependency_code];
                }
            });
        },

        /* Excel Download Workflow */
        downloadExcel() {
            if (this.template.id && this.tasks.length === 0) {
                window.location.href = `/templates/${this.template.id}/export-excel`;
                return;
            }

            // Post current tasks so the exported Excel contains the generated tasks & periods
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/templates/download-blank-excel';
            form.style.display = 'none';

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            form.appendChild(csrfInput);

            const fields = {
                name: this.template.name || 'New Template',
                estimated_duration: this.template.estimated_duration || 14,
                duration_unit: this.template.duration_unit || 'working_days',
                reference_start_date: this.template.reference_start_date || this.formatDateISO(new Date()),
                working_hours: this.template.working_hours || '09:00 - 17:00',
                category: this.template.category || 'General',
                tasks: JSON.stringify(this.tasks)
            };

            for (const [k, v] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = k;
                input.value = v;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
            setTimeout(() => {
                if (document.body.contains(form)) {
                    document.body.removeChild(form);
                }
            }, 1000);
        },

        /* Excel Upload Workflow */
        triggerExcelUpload() {
            if (this.dom.fileInput) {
                this.dom.fileInput.click();
            }
        },

        uploadExcel(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('estimated_duration', this.template.estimated_duration || 14);
            formData.append('duration_unit', this.template.duration_unit || 'working_days');
            formData.append('reference_start_date', this.template.reference_start_date || this.formatDateISO(new Date()));
            if (this.tasks.length > 0) {
                formData.append('existing_tasks', JSON.stringify(this.tasks));
            }

            // Show uploading spinner
            if (this.dom.importBanner) {
                this.dom.importBanner.style.display = 'block';
                this.dom.importBanner.innerHTML = `
                    <div class="stp-import-preview-box" style="background: #F8FAFC; border-color: #CBD5E1;">
                        <span>⏳ Reading and validating Excel workbook...</span>
                    </div>
                `;
            }

            fetch('/templates/import-preview', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success && data.errors && data.errors.length > 0) {
                    this.showImportErrors(data.errors);
                    return;
                }

                if (data.tasks && data.tasks.length > 0) {
                    if (this.tasks.length > 0) {
                        this.pendingImportTasks = data.tasks;
                        this.showImportConfirmModal(data);
                    } else {
                        // Draft was empty, apply immediately
                        this.tasks = data.tasks;
                        this.reindexCodes();
                        this.unsavedChanges = true;
                        this.render();
                        this.showImportSuccessBanner(data);
                    }
                } else {
                    alert('No task rows found in the uploaded Excel workbook.');
                }
            })
            .catch(err => {
                console.error(err);
                this.showImportErrors(['Failed to read Excel workbook. Please verify the file format and try again.']);
            });
        },

        showImportErrors(errors) {
            if (!this.dom.importBanner) return;
            this.dom.importBanner.style.display = 'block';
            this.dom.importBanner.innerHTML = `
                <div class="stp-import-error-box">
                    <div style="font-weight: 700; margin-bottom: 4px;">Spreadsheet Validation Failed (${errors.length} error${errors.length > 1 ? 's' : ''}):</div>
                    <ul style="margin: 0; padding-left: 20px;">
                        ${errors.map(e => `<li>${this.escapeHtml(e)}</li>`).join('')}
                    </ul>
                </div>
            `;
        },

        showImportSuccessBanner(data) {
            if (!this.dom.importBanner) return;
            this.dom.importBanner.style.display = 'block';
            this.dom.importBanner.innerHTML = `
                <div class="stp-import-preview-box">
                    <div>
                        <b style="color: #166534;">✓ Task Plan Imported Successfully</b>
                        <div style="font-size: 12px; color: #15803D; margin-top: 2px;">
                            ${data.tasks_found || data.tasks.length} tasks loaded (${data.main_tasks_count || 0} Main Tasks, ${data.subtasks_count || 0} Subtasks, 0 Errors).
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline btn-xs" onclick="document.getElementById('stp-import-banner').style.display='none'">Dismiss</button>
                </div>
            `;
        },

        showImportConfirmModal(data) {
            const modal = document.getElementById('stp-import-confirm-backdrop');
            const msg = document.getElementById('stp-import-confirm-msg');
            if (!modal || !msg) return;

            msg.textContent = `Uploaded Excel contains ${data.tasks_found || data.tasks.length} tasks. Your current draft already contains ${this.tasks.length} tasks.`;
            modal.style.display = 'flex';
        },

        cancelImport() {
            this.pendingImportTasks = null;
            const modal = document.getElementById('stp-import-confirm-backdrop');
            if (modal) modal.style.display = 'none';
        },

        confirmImport() {
            if (!this.pendingImportTasks) return;

            const mode = document.querySelector('input[name="stp_import_mode"]:checked')?.value || 'merge';

            if (mode === 'replace') {
                this.tasks = this.pendingImportTasks;
            } else {
                // Merge / Update matching tasks by code
                const existingByCode = {};
                this.tasks.forEach(t => { existingByCode[t.code] = t; });

                this.pendingImportTasks.forEach(uploaded => {
                    if (existingByCode[uploaded.code]) {
                        Object.assign(existingByCode[uploaded.code], uploaded);
                    } else {
                        this.tasks.push(uploaded);
                    }
                });
            }

            this.pendingImportTasks = null;
            this.reindexCodes();
            this.unsavedChanges = true;
            this.cancelImport();
            this.render();

            if (this.dom.importBanner) {
                this.dom.importBanner.style.display = 'block';
                this.dom.importBanner.innerHTML = `
                    <div class="stp-import-preview-box">
                        <div>
                            <b style="color: #166534;">✓ Excel Import Applied (${mode === 'merge' ? 'Merged with existing tasks' : 'Draft replaced'})</b>
                            <div style="font-size: 12px; color: #15803D;">Current Task Plan now has ${this.tasks.length} total tasks.</div>
                        </div>
                        <button type="button" class="btn btn-outline btn-xs" onclick="document.getElementById('stp-import-banner').style.display='none'">Dismiss</button>
                    </div>
                `;
            }
        },

        projectCreateUrl(templateId=null) {
            const context=window.PROJECT_TEMPLATE_CONTEXT||{};
            if (context.plan_project) {
                const url = new URL('/projects/' + encodeURIComponent(context.plan_project) + '/plan-setup', window.location.href);
                for (const key of ['starts_on', 'ends_on', 'planned_budget']) {
                    if (context[key]) url.searchParams.set(key, context[key]);
                }
                if (templateId) url.searchParams.set('template_id', templateId);
                return url.pathname + url.search;
            }
            const url=new URL('/projects/create',window.location.href);
            for(const key of ['project_flow','draft'])if(context[key])url.searchParams.set(key,context[key]);
            if(templateId)url.searchParams.set('template_id',templateId);
            url.searchParams.set('mode','template');return url.pathname+url.search;
        },
        /* Save and Continue to Create Project flow */
        saveAndContinueToProject() {
            if (!this.template || !this.template.id) {
                this.saveTemplate();
                return;
            }
            const targetUrl = this.projectCreateUrl(this.template.id);
            if (this.unsavedChanges) {
                this.saveTemplate(targetUrl);
            } else {
                window.location.href = targetUrl;
            }
        },

        /* Save Template to Database */
        saveTemplate(customRedirect = null) {
            if (!this.template.name || !this.template.name.trim()) {
                alert('Please enter a Template Name before saving.');
                if (this.dom.detailsDrawer) this.dom.detailsDrawer.style.display = 'grid';
                document.getElementById('stp_drawer_name')?.focus();
                return;
            }

            const payload = {
                ...(window.PROJECT_TEMPLATE_CONTEXT || {}),
                name: this.template.name.trim(),
                description: this.template.description || null,
                category: this.template.category || 'General',
                estimated_duration: this.template.estimated_duration || 14,
                duration_unit: this.template.duration_unit || 'working_days',
                reference_start_date: this.template.reference_start_date || this.formatDateISO(new Date()),
                working_hours: this.template.working_hours || '09:00 - 17:00',
                planning_periods: this.template.planning_periods || {},
                tasks: this.tasks.map((t, idx) => ({
                    code: t.code,
                    parent_code: t.parent_code || null,
                    title: t.title,
                    description: t.description || null,
                    duration_days: t.duration_days || 1,
                    start_date: t.start_date || null,
                    start_time: t.start_time || '09:00',
                    end_date: t.end_date || null,
                    end_time: t.end_time || '17:00',
                    suggested_role_id: t.suggested_role_id || null,
                    dependency_code: t.dependency_code || null,
                    priority: t.priority || 'medium',
                    sort_order: idx
                }))
            };

            if (this.localPlanSave) { this.localPlanSave(payload); this.close(); return; }
            const url = this.template.id ? `/templates/${this.template.id}` : '/templates';
            const method = this.template.id ? 'PUT' : 'POST';

            if (this.dom.btnSave) {
                this.dom.btnSave.disabled = true;
                this.dom.btnSave.textContent = customRedirect ? 'Saving & Continuing...' : 'Saving...';
            }
            if (this.dom.btnUse) {
                this.dom.btnUse.disabled = true;
                this.dom.btnUse.textContent = 'Saving...';
            }

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.unsavedChanges = false;
                    const continuation=(window.PROJECT_TEMPLATE_CONTEXT?.select_for_project && data.template_id) ? this.projectCreateUrl(data.template_id) : null;
                    window.location.href = continuation || customRedirect || data.redirect || '/templates';
                } else {
                    if (this.dom.btnSave) {
                        this.dom.btnSave.disabled = false;
                        this.dom.btnSave.textContent = (this.mode === 'select' || customRedirect) ? 'Save & Continue with Template →' : 'Save Template';
                    }
                    if (this.dom.btnUse) {
                        this.dom.btnUse.disabled = false;
                        this.dom.btnUse.textContent = 'Save & Continue with Template →';
                    }
                    alert('Error saving template: ' + (data.message || 'Please check task inputs.'));
                }
            })
            .catch(err => {
                console.error(err);
                if (this.dom.btnSave) {
                    this.dom.btnSave.disabled = false;
                    this.dom.btnSave.textContent = (this.mode === 'select' || customRedirect) ? 'Save & Continue with Template →' : 'Save Template';
                }
                if (this.dom.btnUse) {
                    this.dom.btnUse.disabled = false;
                    this.dom.btnUse.textContent = 'Save & Continue with Template →';
                }
                alert('An error occurred while saving the template.');
            });
        },

        escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    window.SmartTaskPlan = SmartTaskPlan;

})(window, document);
