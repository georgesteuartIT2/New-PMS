@extends('layouts.app')
@section('title', 'New project')
@section('content')
@php
    $draftProject = $draftProject ?? null;
    $projectDefaults = $projectDefaults ?? [];
    $createMode = old('mode', request('template_id') ? 'template' : ($projectDefaults['mode'] ?? 'blank'));
    $chosenTemplate = old('project_template_id', request('template_id', $projectDefaults['project_template_id'] ?? null));
    $createSubsidiary = $subsidiaries->firstWhere('id', (int) old('subsidiary_id', $projectDefaults['subsidiary_id'] ?? $defaultSubsidiary?->id)) ?? $defaultSubsidiary;
    $teamRole = $roles->firstWhere('slug', 'team');
@endphp
<div class="project-create-page">
    <a class="pc-back" href="{{ route('projects.index') }}">&larr; Projects</a>
    <header class="page-head pc-heading"><div><div class="pc-title-line"><h1>{{ $draftProject ? 'Create project' : 'New project' }}</h1>@if($draftProject)<span class="pc-draft-status">Draft</span>@endif</div><p class="muted">{{ $draftProject ? 'Review the saved details, then send this project for manager approval.' : 'Set up the essentials. Your project manager will review and accept the plan.' }}</p></div><span class="pc-required-note"><span class="required">*</span> Required</span></header>
    <form method="post" action="{{ $draftProject ? route('projects.draft.save',$draftProject) : route('projects.store') }}" data-create-user="{{ auth()->id() }}" data-draft-id="{{ $draftProject?->id }}" data-project-flow="{{ request('project_flow') }}" id="create-project-form" class="pc-form" aria-label="Create project">
        @csrf

        <section class="pc-section" aria-labelledby="pc-details-title">
            <header class="pc-section-label"><span class="pc-section-number">01</span><div><h2 id="pc-details-title">Project details</h2><p>Give the project a clear identity.</p></div></header>
            <div class="pc-section-fields">
                <div class="pc-field-grid pc-identity-fields">
                    <label for="project_name">Project name <span class="required">*</span><input id="project_name" name="name" value="{{ old('name', $projectDefaults['name'] ?? '') }}" required maxlength="160" placeholder="e.g. ERP system migration" autofocus></label>
                    <label for="subsidiary_select">Subsidiary <span class="required">*</span><select name="subsidiary_id" id="subsidiary_select" required>@forelse($subsidiaries as $sub)<option value="{{ $sub->id }}" data-code="{{ $sub->code }}" data-preview="{{ $sub->previewProjectCode() }}" data-existing-code="{{ $draftProject && (int)$draftProject->subsidiary_id===$sub->id ? $draftProject->code : '' }}" @selected((int)old('subsidiary_id',$projectDefaults['subsidiary_id'] ?? $defaultSubsidiary?->id)===$sub->id)>{{ $sub->name }}</option>@empty<option value="">No active subsidiaries</option>@endforelse</select><small class="pc-code">Project code <code id="project_code_preview">{{ $draftProject && (int)$draftProject->subsidiary_id===$createSubsidiary?->id ? $draftProject->code : ($createSubsidiary?->previewProjectCode() ?? 'Assigned on save') }}</code><span id="project-code-hint">{{ $draftProject ? 'Saved draft' : 'Auto-generated' }}</span></small></label>
                </div>
                <label for="project_description">Description <span class="pc-optional">Optional</span><textarea name="description" id="project_description" rows="2" maxlength="3000" placeholder="What should this project deliver?">{{ old('description', $projectDefaults['description'] ?? '') }}</textarea></label>
            </div>
        </section>
        <section class="pc-section" aria-labelledby="pc-plan-title">
            <header class="pc-section-label"><span class="pc-section-number">02</span><div><h2 id="pc-plan-title">Plan &amp; dates</h2><p>Start fresh or reuse a task plan.</p></div></header>
            <div class="pc-section-fields">
                <fieldset class="pc-plan-choice"><legend class="workspace-sr-only">Project setup</legend>
                    <label class="project-type-card {{ $createMode==='blank'?'active':'' }}" id="card-mode-blank"><input type="radio" name="mode" value="blank" data-mode="blank" @checked($createMode==='blank')><span><b>Blank project</b><small>Add or import tasks later.</small></span></label>
                    <label class="project-type-card {{ $createMode==='template'?'active':'' }}" id="card-mode-template"><input type="radio" name="mode" value="template" data-mode="template" @checked($createMode==='template')><span><b>Use a template</b><small>Start with a prepared task plan.</small></span></label>
                </fieldset>
                <div id="template-selector-box" class="pc-template" @if($createMode!=='template') style="display:none" @endif>
                    <div id="template-dropdown-wrap"><button type="button" class="btn btn-outline pc-template-library-button" id="btn-browse-templates">Select template <span aria-hidden="true">&rarr;</span></button><p class="pc-field-hint">Choose a saved template or create a new one in the library.</p></div><label class="pc-template-value-label" hidden>Project template<select name="project_template_id" id="project_template_id" tabindex="-1"><option value="">Choose a template</option>@foreach($templates as $template)@php $sched=$template->schedule(); @endphp<option value="{{ $template->id }}" data-name="{{ $template->name }}" data-category="{{ $template->category }}" data-desc="{{ $template->description }}" data-days="{{ $sched['working_days'] }}" data-tasks="{{ $sched['task_count'] }}" data-hours="{{ $template->working_hours ?: '09:00 - 17:00' }}" data-summary="{{ $sched['summary'] }}" @selected($chosenTemplate==$template->id)>{{ $template->name }}</option>@endforeach</select></label>
                    <div id="selected-template-showcase" class="pc-template-overview" style="display:none"><div><span id="showcase-template-summary"></span><small id="showcase-template-hours"></small><b id="showcase-template-name"></b><span id="showcase-template-category" hidden></span><span id="showcase-template-desc" hidden></span></div><div class="pc-template-actions"><button type="button" class="btn btn-ghost btn-sm" id="btn-preview-template-plan">Preview tasks</button><button type="button" class="btn btn-outline btn-sm" id="btn-change-template-redirect">Change template</button></div></div>
                    @if($templates->isEmpty())<p class="pc-field-hint">No active templates. Choose Blank project to continue.</p>@endif
                </div>
                @if($draftProject && $draftProject->tasks()->exists())
                <div id="draft-plan-replacement" class="pc-draft-plan-replacement" hidden><label><input type="checkbox" name="replace_draft_plan" value="1" @checked(old('replace_draft_plan'))><span>Replace the existing {{ $draftProject->tasks()->count() }} draft tasks with the selected plan.</span></label><small>Tasks with progress, comments, files or delay reports cannot be replaced.</small></div>
                @endif
                <div class="pc-field-grid">
                    <label for="starts_on">Start date <span class="required">*</span><input type="date" name="starts_on" id="starts_on" value="{{ old('starts_on', $projectDefaults['starts_on'] ?? now()->format('Y-m-d')) }}" required></label>
                    <label for="ends_on">Due date <span class="required">*</span><input type="date" name="ends_on" id="ends_on" value="{{ old('ends_on', $projectDefaults['ends_on'] ?? '') }}" min="{{ old('starts_on', $projectDefaults['starts_on'] ?? now()->format('Y-m-d')) }}" required><span id="deadline-status-tag" style="display:none"></span><span id="deadline-feedback-box"></span></label>
                </div>
            </div>
        </section>
        <section class="pc-section pc-people" aria-labelledby="pc-people-title">
            <header class="pc-section-label"><span class="pc-section-number">03</span><div><h2 id="pc-people-title">People</h2><p>Choose a manager and the people involved.</p></div></header>
            <div class="pc-section-fields">
                <p class="pc-directory-note"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M15 4a4 4 0 0 1 0 8M20 21v-2a4 4 0 0 0-3-3.9"/><circle cx="9" cy="7" r="4"/></svg><span>Search existing people or Microsoft Azure. <b>Add &amp; select</b> saves a new Azure person to the system.</span></p>
                <div id="create-people-feedback" class="pc-picker-feedback" role="status" hidden></div>
                <div class="pc-field-grid pc-people-grid">
                    <div class="pc-person-field">
                        <label for="pm-search-input">Project manager <span class="pc-optional">Required for approval</span></label>
                        <div id="pm-search-wrap" class="pc-picker"><input type="search" id="pm-search-input" placeholder="Search by name or email" autocomplete="off" aria-describedby="pm_help_text"><span id="pm-search-spinner" class="pc-picker-spinner" style="display:none">Searching Azure&hellip;</span><div id="pm-search-dropdown" class="user-picker-dropdown" style="display:none"></div></div>
                        <div id="pm-selected-box" class="pc-selected-person" style="display:none"><span class="avatar" id="pm-selected-avatar"></span><div><b id="pm-selected-name"></b><small id="pm-selected-subtext"></small></div><button type="button" class="btn btn-ghost btn-sm" id="btn-pm-change">Change</button></div>
                        <input type="hidden" name="pm_user_id" id="pm_user_id" value="{{ old('pm_user_id', $projectDefaults['pm_user_id'] ?? null) }}">
                        <small id="pm_help_text" class="pc-field-hint">The manager accepts the project before work starts.</small>
                    </div>
                    <div class="pc-person-field">
                        <div class="pc-label-line"><label for="team-search-input">Team members <span class="pc-optional">Optional</span></label><span id="team-members-badge" class="pc-member-count">0 members</span></div>
                        <div class="pc-picker"><input type="search" id="team-search-input" placeholder="Search to add people" autocomplete="off"><span id="team-search-spinner" class="pc-picker-spinner" style="display:none">Searching Azure&hellip;</span><div id="team-search-dropdown" class="user-picker-dropdown" style="display:none"></div></div>
                        <div id="assigned-team-list" class="pc-team-list"></div><p id="team-empty-state" class="pc-field-hint">You can assign the team now or add people later.</p>
                    </div>
                </div>
                <details id="governance-roles-section" class="pc-additional-roles" @if(collect(old('members',$projectDefaults['members'] ?? []))->contains(fn($member)=>(int)($member['project_role_id']??0)!==(int)$teamRole?->id && !empty($member['user_id']))) open @endif>
                    <summary><span>Additional project roles <small>Optional</small></span><span id="assigned-roles-counter" class="pc-member-count">0 assigned</span></summary>
                    <p class="pc-field-hint">Assign a sponsor, owner or steering committee member using the same directory search.</p>
                    <div class="pc-governance-fields">
                    @foreach(['ps'=>'Project sponsor','po'=>'Project owner','steering'=>'Steering committee'] as $slug=>$label)
                        @php $govRole=$roles->firstWhere('slug',$slug); $govIndex=100+$loop->index; @endphp
                        @if($govRole)
                        <div class="role-matrix-row" data-role-id="{{ $govRole->id }}" data-role-index="{{ $govIndex }}"><label for="gov-search-{{ $govIndex }}">{{ $label }}</label><div class="gov-role-picker pc-picker"><div class="gov-selected-card pc-selected-person" style="display:none"><span class="avatar gov-avatar"></span><span class="gov-name"></span><button type="button" class="btn btn-ghost btn-sm btn-clear-gov" aria-label="Remove {{ strtolower($label) }}">&times;</button></div><div class="gov-search-wrap"><input type="search" id="gov-search-{{ $govIndex }}" class="gov-search-input" placeholder="Search by name or email" autocomplete="off"><div class="gov-dropdown user-picker-dropdown" style="display:none"></div></div><input type="hidden" name="members[{{ $govIndex }}][user_id]" class="gov-hidden-user-id" value=""><input type="hidden" name="members[{{ $govIndex }}][project_role_id]" value="{{ $govRole->id }}"></div></div>
                        @endif
                    @endforeach
                    </div>
                </details>
            </div>
        </section>
        <footer class="project-create-actions pc-actions"><div class="pc-submit-note"><b>Ready for the manager's review?</b><span>Save a draft if you're still preparing the plan.</span></div><div class="pc-action-buttons"><a class="btn btn-ghost" href="{{ route('projects.index') }}">Cancel</a><button type="submit" name="action" value="draft" class="btn btn-outline" id="btn-save-draft">Save draft</button><button type="submit" name="action" value="create" class="btn btn-primary" id="btn-create-project">{{ $draftProject ? 'Create project' : 'Send for PM approval' }} <span aria-hidden="true">&rarr;</span></button></div></footer>
    </form>
</div>
<!-- Shared Smart Task Plan Modal for Previewing Template Plan -->
@include('templates.partials.task-plan-modal')

<style>
.user-picker-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 100;
    max-height: 280px;
    overflow-y: auto;
    background: #FFFFFF;
    border: 1.5px solid #CBD5E1;
    border-radius: 6px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}
.user-dropdown-section-title {
    padding: 6px 12px;
    font-size: 11px;
    font-weight: 700;
    color: #64748B;
    background: #F8FAFC;
    border-bottom: 1px solid #F1F5F9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.user-search-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #F8FAFC;
    transition: background 0.12s ease;
    gap: 10px;
}
.user-search-item:hover, .user-search-item.highlighted {
    background: #F1F5F9;
}
.user-search-item.azure-item {
    background: #FAFCFF;
}
.user-search-item.azure-item:hover {
    background: #F0F9FF;
}
</style>

@push('scripts')
<script src="{{ asset('js/template-builder.js') }}?v={{ filemtime(public_path('js/template-builder.js')) }}"></script>
<script>
// Local Users & Template Cache for 0ms response
window.LOCAL_USERS = {!! json_encode($users->map(fn($u) => [
    'id' => $u->id,
    'name' => $u->name,
    'email' => $u->email,
    'subsidiary_code' => $u->subsidiary?->code,
    'subsidiary_name' => $u->subsidiary?->name,
])->values()) !!};

window.TEMPLATES_CACHE = {!! json_encode($templatesCache ?? []) !!};

document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const teamRoleId = {{ $teamRole?->id ?? 'null' }};

    // Initialize Smart Task Plan Modal
    if (window.SmartTaskPlan) {
        window.SmartTaskPlan.init({
            roles: @json($roles ?? []),
            canManage: false,
            canCreateProject: false,
        });
    }

    // -------------------------------------------------------------------------
    // 1. DRAFT PERSISTENCE (Restore fields when returning from template selection)
    // -------------------------------------------------------------------------
    const projectNameInput = document.getElementById('project_name');
    const subSelect = document.getElementById('subsidiary_select');
    const codePreview = document.getElementById('project_code_preview');
    const startDateInput = document.getElementById('starts_on');
    const projectDescInput = document.getElementById('project_description');

    const createForm=document.getElementById('create-project-form');
    let restoredProjectState=null;
    let projectFlow=createForm.dataset.projectFlow;
    const flowKey=id=>'projectflow.create.'+createForm.dataset.createUser+'.'+id;
    function saveProjectDraft() {
        if(!/^[0-9a-f-]{36}$/i.test(projectFlow))projectFlow=crypto.randomUUID();
        const values=new FormData(createForm),state={};
        for(const name of ['name','description','subsidiary_id','starts_on','ends_on','pm_user_id','mode','project_template_id'])state[name]=values.get(name)||'';
        const members={};
        for(const [name,value]of values){const match=name.match(/^members\[(\d+)\]\[(user_id|project_role_id)\]$/);if(match){members[match[1]] ||= {};members[match[1]][match[2]]=value;}}
        state.members=Object.values(members).filter(member=>member.user_id && member.project_role_id);
        sessionStorage.setItem(flowKey(projectFlow),JSON.stringify(state));
        return projectFlow;
    }
    if(projectFlow && !@json(session()->hasOldInput())){
        try{
            restoredProjectState=JSON.parse(sessionStorage.getItem(flowKey(projectFlow))||'null');
            if(restoredProjectState){
                for(const name of ['name','description','subsidiary_id','starts_on','ends_on','pm_user_id']){const input=createForm.elements.namedItem(name);if(input)input.value=restoredProjectState[name]||'';}
                if(!@json((bool)request('template_id'))){
                    const mode=createForm.querySelector('input[name="mode"][value="'+(restoredProjectState.mode==='template'?'template':'blank')+'"]');if(mode)mode.checked=true;
                    const selected=document.getElementById('project_template_id');if(selected)selected.value=restoredProjectState.project_template_id||'';
                }
            }
        }catch(error){restoredProjectState=null;}
    }

    // Subsidiary preview code updater
    if (subSelect && codePreview) {
        subSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            codePreview.textContent = opt?.dataset.existingCode || opt?.dataset.preview || 'Assigned on save';
            const hint=document.getElementById('project-code-hint');if(hint)hint.textContent=opt?.dataset.existingCode?'Saved draft':'Assigned on save';
        });
    }

    subSelect?.dispatchEvent(new Event('change'));

    // -------------------------------------------------------------------------
    // 2. TEMPLATE SELECTION FLOW (Redirect to /templates & Return with Selected Template)
    // -------------------------------------------------------------------------
    const radioBlank = document.querySelector('input[data-mode="blank"]');
    const radioTemplate = document.querySelector('input[data-mode="template"]');
    const cardBlank = document.getElementById('card-mode-blank');
    const cardTemplate = document.getElementById('card-mode-template');
    const templateBox = document.getElementById('template-selector-box');
    const templateSelect = document.getElementById('project_template_id');
    const showcaseCard = document.getElementById('selected-template-showcase');
    const showcaseName = document.getElementById('showcase-template-name');
    const showcaseCategory = document.getElementById('showcase-template-category');
    const showcaseSummary = document.getElementById('showcase-template-summary');
    const showcaseHours = document.getElementById('showcase-template-hours');
    const showcaseDesc = document.getElementById('showcase-template-desc');
    const templateDropdownWrap = document.getElementById('template-dropdown-wrap');
    const btnPreviewPlan = document.getElementById('btn-preview-template-plan');
    const btnChangeTemplate = document.getElementById('btn-change-template-redirect');
    const btnBrowseTemplates = document.getElementById('btn-browse-templates');

    // Deadline calculation elements
    const endDateInput = document.getElementById('ends_on');
    const deadlineStatusTag = document.getElementById('deadline-status-tag');
    const deadlineFeedbackBox = document.getElementById('deadline-feedback-box');
    let calculatedDeadline = null;
    let currentWorkingDays = 0;
    let isManuallyOverridden = Boolean(restoredProjectState?.ends_on) || @json((bool)old('ends_on', $projectDefaults['ends_on'] ?? ''));

    function redirectToTemplateSelector() {
        try{
            const flow=saveProjectDraft();const url=new URL("{{ route('templates.index') }}");url.searchParams.set('select_for_project','1');url.searchParams.set('project_flow',flow);if(createForm.dataset.draftId)url.searchParams.set('draft',createForm.dataset.draftId);window.location.assign(url.toString());
        }catch(error){showPeopleFeedback('Save this project as a draft before leaving. Browser storage is unavailable.',true);}
    }

    // When clicking "Use Template" radio card
    if (radioTemplate) {
        radioTemplate.addEventListener('click', function() {
            updateTemplateModeUI();
            if (!templateSelect.value) redirectToTemplateSelector();
        });
    }

    if (btnBrowseTemplates) btnBrowseTemplates.addEventListener('click', redirectToTemplateSelector);
    if(btnChangeTemplate)btnChangeTemplate.addEventListener('click',redirectToTemplateSelector);

    if (radioBlank) {
        radioBlank.addEventListener('change', function() {
            updateTemplateModeUI();
        });
    }

    function updateDraftReplacement(){
        const warning=document.getElementById('draft-plan-replacement');if(!warning)return;
        const original=@json($draftProject?->project_template_id);
        const selected=radioTemplate.checked?templateSelect.value:'';
        const changed=String(original||'')!==String(selected||'');
        warning.hidden=!changed;const checkbox=warning.querySelector('input');checkbox.required=changed;if(!changed)checkbox.checked=false;
    }
    function updateTemplateModeUI() {
        const isTemplate = radioTemplate && radioTemplate.checked;
        if(templateSelect)templateSelect.required=false;
        updateDraftReplacement();
        if (cardBlank) cardBlank.classList.toggle('active', !isTemplate);
        if (cardTemplate) cardTemplate.classList.toggle('active', isTemplate);
        if (templateBox) templateBox.style.display = isTemplate ? 'block' : 'none';

        if (isTemplate) {
            renderSelectedTemplateCard();
            calculateDeadline();
        } else {
            clearAutoDeadline();
        }
    }

    function renderSelectedTemplateCard() {
        const selectedId = templateSelect ? templateSelect.value : '';
        if (!selectedId) {
            if (showcaseCard) showcaseCard.style.display = 'none';
            if (templateDropdownWrap) templateDropdownWrap.style.display = 'block';
            return;
        }

        const opt = templateSelect.options[templateSelect.selectedIndex];
        if (!opt || !opt.value) {
            if (showcaseCard) showcaseCard.style.display = 'none';
            if (templateDropdownWrap) templateDropdownWrap.style.display = 'block';
            return;
        }

        const name = opt.dataset.name || opt.text;
        const category = opt.dataset.category || 'General';
        const summary = opt.dataset.summary || '';
        const hours = opt.dataset.hours || '09:00 - 17:00';
        const desc = opt.dataset.desc || '';

        if (showcaseName) showcaseName.textContent = name;
        if (showcaseCategory) showcaseCategory.textContent = category;
        if (showcaseSummary) showcaseSummary.textContent = summary;
        if (showcaseHours) showcaseHours.textContent = `Daily hours: ${hours}`;
        if (showcaseDesc) {
            showcaseDesc.textContent = desc;
            showcaseDesc.style.display = desc ? 'block' : 'none';
        }

        if (showcaseCard) showcaseCard.style.display = 'block';
        if (templateDropdownWrap) templateDropdownWrap.style.display = 'none';
    }

    // Preview Task Plan modal when button is clicked
    if (btnPreviewPlan) {
        btnPreviewPlan.addEventListener('click', function() {
            const selectedId = templateSelect ? templateSelect.value : '';
            if (!selectedId || !window.SmartTaskPlan) return;

            const idStr = String(selectedId);
            const cached = window.TEMPLATES_CACHE && window.TEMPLATES_CACHE[idStr];
            if (cached && cached.template) {
                window.SmartTaskPlan.openView(cached.template, cached.tasks || []);
            } else {
                window.SmartTaskPlan.openView({ id: selectedId }, null);
            }
        });
    }

    if (templateSelect) {
        templateSelect.addEventListener('change', function() {
            renderSelectedTemplateCard();
            isManuallyOverridden=false;
            calculateDeadline();
            updateDraftReplacement();
        });
    }

    // Helper: Add working days (Mon-Fri) skipping weekends
    function addWorkingDays(startDateStr, workingDays) {
        if (!startDateStr || !workingDays || workingDays < 1) return null;
        const d = new Date(startDateStr + 'T00:00:00');
        let daysAdded = 0;
        
        while (daysAdded < (workingDays - 1)) {
            d.setDate(d.getDate() + 1);
            const dayOfWeek = d.getDay();
            if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                daysAdded++;
            }
        }
        
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function calculateDeadline() {
        if (!radioTemplate || !radioTemplate.checked) return;
        const opt = templateSelect ? templateSelect.options[templateSelect.selectedIndex] : null;

        if (!opt || !opt.value) {
            clearAutoDeadline();
            return;
        }

        const days = parseInt(opt.dataset.days) || 14;
        currentWorkingDays = days;

        const startVal = startDateInput ? startDateInput.value : '';
        if (!startVal) return;

        const autoDate = addWorkingDays(startVal, days);
        calculatedDeadline = autoDate;

        if (!isManuallyOverridden && autoDate && endDateInput) {
            endDateInput.value = autoDate;
            showAutoBadge();
        }
    }

    function showAutoBadge() {
        if (deadlineStatusTag) {
            deadlineStatusTag.style.display = 'inline-block';
            deadlineStatusTag.className = 'pill active';
            deadlineStatusTag.innerHTML = `⚡ Auto-calculated (${currentWorkingDays} working days)`;
        }
        if (deadlineFeedbackBox) deadlineFeedbackBox.innerHTML = '';
    }

    function clearAutoDeadline() {
        if (deadlineStatusTag) deadlineStatusTag.style.display = 'none';
        if (deadlineFeedbackBox) deadlineFeedbackBox.innerHTML = '';
        calculatedDeadline = null;
        isManuallyOverridden = false;
    }

    if (startDateInput) startDateInput.addEventListener('change', calculateDeadline);
    if (endDateInput) {
        endDateInput.addEventListener('input', function() {
            if (radioTemplate && radioTemplate.checked && calculatedDeadline) {
                isManuallyOverridden = (this.value !== calculatedDeadline);
                if (isManuallyOverridden && deadlineStatusTag) {
                    deadlineStatusTag.className = 'pill on_hold';
                    deadlineStatusTag.innerHTML = `Overridden <button type="button" id="btn-reset-deadline" style="color: inherit; text-decoration: underline; background: none; border: none; cursor: pointer; font-size: 11px; margin-left: 4px;">Reset</button>`;
                    const resetBtn = document.getElementById('btn-reset-deadline');
                    if (resetBtn) {
                        resetBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            isManuallyOverridden = false;
                            if (calculatedDeadline && endDateInput) endDateInput.value = calculatedDeadline;
                            showAutoBadge();
                        });
                    }
                } else if (!isManuallyOverridden) {
                    showAutoBadge();
                }
            }
        });
    }

    // Initialize Template UI state
    updateTemplateModeUI();

    // -------------------------------------------------------------------------
    // 3. UNIVERSAL SEARCHABLE USER PICKER (Local Users + Azure AD 1-Click Import)
    // -------------------------------------------------------------------------
    
    // Cache of Azure query debounces
    const directorySearches=new Map();
    function showPeopleFeedback(message, isError=false) {
        const feedback=document.getElementById('create-people-feedback');
        if(!feedback)return;
        feedback.hidden=!message;feedback.textContent=message;
        feedback.classList.toggle('is-error',isError);
    }
    async function queryUsers(term, onResults, spinnerEl, excludeIds=[]) {
        term=(term||'').trim().toLowerCase();
        const previous=directorySearches.get(onResults);
        if(previous){clearTimeout(previous.timer);previous.controller.abort();}
        const activeInput=document.activeElement;
        const state={controller:new AbortController(),timer:null};directorySearches.set(onResults,state);
        const localMatches=window.LOCAL_USERS.filter(person=>!excludeIds.includes(person.id) && (!term || (person.name+' '+person.email).toLowerCase().includes(term))).slice(0,25);
        onResults(localMatches,[],false);
        if(spinnerEl)spinnerEl.style.display='none';
        if(term.length<2)return;
        if(spinnerEl)spinnerEl.style.display='inline';
        state.timer=setTimeout(async()=>{
            try {
                const response=await fetch(`{{ route('users.azure') }}?q=${encodeURIComponent(term)}&ajax=1`,{headers:{Accept:'application/json'},signal:state.controller.signal});
                if(!response.ok)throw Error('Directory search is unavailable. You can still choose an existing person.');
                const data=await response.json();
                if(directorySearches.get(onResults)!==state || document.activeElement!==activeInput)return;
                const localEmails=new Set(window.LOCAL_USERS.map(person=>(person.email||'').toLowerCase()));
                const azure=(data.results||[]).filter(person=>person.email&&!localEmails.has(person.email.toLowerCase()));
                onResults(localMatches,azure,true);
            } catch(error){if(error.name!=='AbortError'){if(document.activeElement===activeInput)onResults(localMatches,[],true);showPeopleFeedback(error.message,true);}}
            finally{if(directorySearches.get(onResults)===state && spinnerEl)spinnerEl.style.display='none';}
        },300);
    }

    /**
     * Add an Azure user to system database via /users/azure/add
     */
    const pendingImports=new Map();
    async function addAzureUserToSystem(azureId,targetBtn) {
        if(pendingImports.has(azureId))return pendingImports.get(azureId);
        const work=(async()=>{
            const item=targetBtn?.closest('.user-search-item');
            const previousText=targetBtn?.textContent;
            item?.setAttribute('aria-busy','true');
            if(targetBtn)targetBtn.textContent='Adding?';
            try {
                const response=await fetch("{{ route('users.azure.add') }}",{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrfToken},body:JSON.stringify({azure_id:azureId})});
                const data=await response.json();
                if(!response.ok || !data.user?.id)throw Error(data.message || 'Could not add this person. Please try again.');
                const person=data.user;
                if(person.account_status && person.account_status!=='active')throw Error('This account is inactive. Ask PMO to activate it before assigning it.');
                if(person.system_role==='pmo')throw Error('Choose a project user for this assignment.');
                if(!window.LOCAL_USERS.some(existing=>existing.id===person.id))window.LOCAL_USERS.push({id:person.id,name:person.name,email:person.email,subsidiary_code:person.subsidiary?.code||'',subsidiary_name:person.subsidiary?.name||''});
                showPeopleFeedback(person.name+' is saved in the system and ready to assign.');
                return person;
            }finally{
                item?.removeAttribute('aria-busy');
                if(targetBtn)targetBtn.textContent=previousText;
                pendingImports.delete(azureId);
            }
        })();pendingImports.set(azureId,work);return work;
    }

    // -------------------------------------------------------------------------
    // 4. PROJECT MANAGER (PM) SEARCH COMPONENT
    // -------------------------------------------------------------------------
    const pmSearchWrap = document.getElementById('pm-search-wrap');
    const pmSearchInput = document.getElementById('pm-search-input');
    const pmSearchSpinner = document.getElementById('pm-search-spinner');
    const pmSearchDropdown = document.getElementById('pm-search-dropdown');
    const pmSelectedBox = document.getElementById('pm-selected-box');
    const pmSelectedName = document.getElementById('pm-selected-name');
    const pmSelectedSubtext = document.getElementById('pm-selected-subtext');
    const pmSelectedAvatar = document.getElementById('pm-selected-avatar');
    const pmUserIdHidden = document.getElementById('pm_user_id');
    const btnPmChange = document.getElementById('btn-pm-change');
    const pmHelpText = document.getElementById('pm_help_text');

    function selectProjectManager(user, note = '') {
        if (!user || !user.id) return;
        pmUserIdHidden.value = user.id;

        const initial = (user.name ? user.name.charAt(0) : 'P').toUpperCase();
        pmSelectedAvatar.textContent = initial;
        pmSelectedName.textContent = user.name;
        pmSelectedSubtext.textContent = `${user.email} ${user.subsidiary_code ? '· [' + user.subsidiary_code + ']' : ''}`;

        pmSelectedBox.style.display = 'flex';
        pmSearchWrap.style.display = 'none';
        pmSearchDropdown.style.display = 'none';

        if (pmHelpText) {
            pmHelpText.textContent = note ? 'Saved to the system from Microsoft Azure.' : 'The manager will receive the project for approval.';
        }

        // If this user was in the team members list, remove them (PM cannot be duplicate member)
        removeTeamMember(user.id);
    }

    function clearProjectManager() {
        pmUserIdHidden.value = '';
        pmSelectedBox.style.display = 'none';
        pmSearchWrap.style.display = 'block';
        if (pmSearchInput) {
            pmSearchInput.value = '';
            setTimeout(() => pmSearchInput.focus(), 50);
        }
        if (pmHelpText) {
            pmHelpText.textContent='Choose a manager, or save a draft to assign one later.';
        }
    }

    if (btnPmChange) btnPmChange.addEventListener('click', clearProjectManager);

    function renderPmDropdown(localUsers, azureUsers, complete=false) {
        if (localUsers.length === 0 && azureUsers.length === 0) {
            pmSearchDropdown.innerHTML = `<div class="muted text-sm" style="padding: 14px; text-align: center;">${complete ? 'No people found. Try another name or email.' : pmSearchInput.value.trim().length<2 ? 'Type at least 2 characters to search Azure.' : 'Searching Microsoft Azure?'}</div>`;
            pmSearchDropdown.style.display = 'block';
            return;
        }

        let html = '';

        if (localUsers.length > 0) {
            html += `<div class="user-dropdown-section-title">Existing people (${localUsers.length})</div>`;
            localUsers.forEach(u => {
                const initial = (u.name ? u.name.charAt(0) : 'U').toUpperCase();
                html += `
                    <div class="user-search-item" data-action="select-pm-local" data-id="${u.id}">
                        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                            <span class="avatar avatar-sm" style="background: #E2E8F0; color: #334155; font-size: 11px; font-weight: 700; flex-shrink: 0;">${initial}</span>
                            <div style="min-width: 0;">
                                <b style="font-size: 13px; color: #22272B; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(u.name)}</b>
                                <small class="muted" style="font-size: 11px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(u.email)} ${u.subsidiary_code ? '· [' + escapeHtml(u.subsidiary_code) + ']' : ''}</small>
                            </div>
                        </div>
                        <span class="pill" style="font-size: 10.5px; background: #F1F5F9; color: #475569; padding: 2px 8px; flex-shrink: 0;">Select PM</span>
                    </div>
                `;
            });
        }

        if (azureUsers.length > 0) {
            html += `<div class="user-dropdown-section-title" style="background: #F0F9FF; color: #0369A1;">Microsoft Azure (${azureUsers.length})</div>`;
            azureUsers.forEach(u => {
                const initial = (u.displayName ? u.displayName.charAt(0) : 'A').toUpperCase();
                html += `
                    <div class="user-search-item azure-item" data-action="select-pm-azure" data-azure-id="${u.id}">
                        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                            <span class="avatar avatar-sm" style="background: #0078D4; color: #FFFFFF; font-size: 11px; font-weight: 700; flex-shrink: 0;">${initial}</span>
                            <div style="min-width: 0;">
                                <b style="font-size: 13px; color: #0F172A; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(u.displayName)}</b>
                                <small class="muted" style="font-size: 11px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(u.email)} ${u.company ? '· ' + escapeHtml(u.company) : ''}</small>
                            </div>
                        </div>
                        <span class="pill" style="font-size: 10.5px; background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD; padding: 2px 8px; flex-shrink: 0; font-weight: 600;">Add & select</span>
                    </div>
                `;
            });
        }

        pmSearchDropdown.innerHTML = html;
        pmSearchDropdown.style.display = 'block';

        // Bind click events
        pmSearchDropdown.querySelectorAll('[data-action="select-pm-local"]').forEach(el => {
            el.addEventListener('click', function(e) {
                e.stopPropagation();
                const id = parseInt(this.dataset.id);
                const u = window.LOCAL_USERS.find(user => user.id === id);
                if (u) selectProjectManager(u);
            });
        });

        pmSearchDropdown.querySelectorAll('[data-action="select-pm-azure"]').forEach(el => {
            el.addEventListener('click', async function(e) {
                e.stopPropagation();
                const azureId = this.dataset.azureId;
                const pill = this.querySelector('.pill');
                try {
                    const newUser = await addAzureUserToSystem(azureId, pill);
                    selectProjectManager(newUser, '(Imported from Microsoft Azure)');
                } catch (err) {
                    showPeopleFeedback(err.message,true);
                }
            });
        });
    }

    if (pmSearchInput) {
        pmSearchInput.addEventListener('focus', function() {
            queryUsers(this.value, renderPmDropdown, pmSearchSpinner);
        });
        pmSearchInput.addEventListener('input', function() {
            queryUsers(this.value, renderPmDropdown, pmSearchSpinner);
        });
    }

    // -------------------------------------------------------------------------
    // 5. TEAM MEMBERS SEARCH & MULTI-ASSIGNMENT (1-Click Local + Azure)
    // -------------------------------------------------------------------------
    const teamSearchInput = document.getElementById('team-search-input');
    const teamSearchSpinner = document.getElementById('team-search-spinner');
    const teamSearchDropdown = document.getElementById('team-search-dropdown');
    const assignedTeamList = document.getElementById('assigned-team-list');
    const teamEmptyState = document.getElementById('team-empty-state');
    const teamBadge = document.getElementById('team-members-badge');

    // List of currently assigned team members: [{ id, name, email, subsidiary_code }]
    let assignedTeamMembers = [];

    function renderAssignedTeamList() {
        if (assignedTeamMembers.length === 0) {
            assignedTeamList.innerHTML = '';
            if (teamEmptyState) teamEmptyState.style.display = 'block';
            if (teamBadge) teamBadge.textContent = '0 Members';
            return;
        }

        if (teamEmptyState) teamEmptyState.style.display = 'none';
        if (teamBadge) teamBadge.textContent = `${assignedTeamMembers.length} ${assignedTeamMembers.length === 1 ? 'Member' : 'Members'}`;

        let html = '';
        assignedTeamMembers.forEach((m, idx) => {
            const initial = (m.name ? m.name.charAt(0) : 'T').toUpperCase();
            html += `
                <div class="team-member-input-row" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 6px;">
                    <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                        <span class="avatar avatar-sm" style="background: #315F58; color: #FFFFFF; font-size: 11px; font-weight: 700; flex-shrink: 0;">${initial}</span>
                        <div style="min-width: 0;">
                            <b style="font-size: 13px; color: #22272B; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(m.name)}</b>
                            <small class="muted" style="font-size: 11px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(m.email)} ${m.subsidiary_code ? '· [' + escapeHtml(m.subsidiary_code) + ']' : ''}</small>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
                        <span class="pill" style="font-size: 11px; background: #F1F5F9; color: #475569;">Team Member</span>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="removeTeamMember(${m.id})" style="color: #DC2626; padding: 2px 8px; font-size: 14px; line-height: 1;" title="Remove Member">✕</button>
                    </div>
                    <!-- Form Submission Hidden Inputs -->
                    <input type="hidden" name="members[${idx}][user_id]" value="${m.id}">
                    <input type="hidden" name="members[${idx}][project_role_id]" value="${teamRoleId}">
                </div>
            `;
        });

        assignedTeamList.innerHTML = html;
    }

    window.addTeamMember = function(user) {
        if (!user || !user.id) return;
        // Check if already in team
        if (assignedTeamMembers.some(m => m.id === user.id)) {
            showPeopleFeedback(`${user.name} is already on the team.`);
            return;
        }
        // Check if already PM
        if (pmUserIdHidden && parseInt(pmUserIdHidden.value) === user.id) {
            showPeopleFeedback(`${user.name} is already the project manager.`);
            return;
        }

        assignedTeamMembers.push(user);
        renderAssignedTeamList();

        // Clear search input and keep focus for rapid adding
        if (teamSearchInput) {
            teamSearchInput.value = '';
            teamSearchInput.focus();
        }
        if (teamSearchDropdown) teamSearchDropdown.style.display = 'none';
    };

    window.removeTeamMember = function(userId) {
        assignedTeamMembers = assignedTeamMembers.filter(m => m.id !== userId);
        renderAssignedTeamList();
    };

    function renderTeamDropdown(localUsers, azureUsers, complete=false) {
        const assignedIds = new Set(assignedTeamMembers.map(m => m.id));
        if (pmUserIdHidden && pmUserIdHidden.value) {
            assignedIds.add(parseInt(pmUserIdHidden.value));
        }

        const filteredLocal = localUsers.filter(u => !assignedIds.has(u.id));

        if (filteredLocal.length === 0 && azureUsers.length === 0) {
            teamSearchDropdown.innerHTML = `<div class="muted text-sm" style="padding: 14px; text-align: center;">${complete ? 'No people found. Try another name or email.' : teamSearchInput.value.trim().length<2 ? 'Type at least 2 characters to search Azure.' : 'Searching Microsoft Azure?'}</div>`;
            teamSearchDropdown.style.display = 'block';
            return;
        }

        let html = '';

        if (filteredLocal.length > 0) {
            html += `<div class="user-dropdown-section-title">Existing people (${filteredLocal.length})</div>`;
            filteredLocal.forEach(u => {
                const initial = (u.name ? u.name.charAt(0) : 'U').toUpperCase();
                html += `
                    <div class="user-search-item" data-action="add-team-local" data-id="${u.id}">
                        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                            <span class="avatar avatar-sm" style="background: #E2E8F0; color: #334155; font-size: 11px; font-weight: 700; flex-shrink: 0;">${initial}</span>
                            <div style="min-width: 0;">
                                <b style="font-size: 13px; color: #22272B; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(u.name)}</b>
                                <small class="muted" style="font-size: 11px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(u.email)} ${u.subsidiary_code ? '· [' + escapeHtml(u.subsidiary_code) + ']' : ''}</small>
                            </div>
                        </div>
                        <span class="pill" style="font-size: 10.5px; background: #EBF3F1; color: #315F58; padding: 2px 8px; flex-shrink: 0; font-weight: 600;">+ Add to Team</span>
                    </div>
                `;
            });
        }

        if (azureUsers.length > 0) {
            html += `<div class="user-dropdown-section-title" style="background: #F0F9FF; color: #0369A1;">Microsoft Azure (${azureUsers.length})</div>`;
            azureUsers.forEach(u => {
                const initial = (u.displayName ? u.displayName.charAt(0) : 'A').toUpperCase();
                html += `
                    <div class="user-search-item azure-item" data-action="add-team-azure" data-azure-id="${u.id}">
                        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                            <span class="avatar avatar-sm" style="background: #0078D4; color: #FFFFFF; font-size: 11px; font-weight: 700; flex-shrink: 0;">${initial}</span>
                            <div style="min-width: 0;">
                                <b style="font-size: 13px; color: #0F172A; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(u.displayName)}</b>
                                <small class="muted" style="font-size: 11px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(u.email)} ${u.company ? '· ' + escapeHtml(u.company) : ''}</small>
                            </div>
                        </div>
                        <span class="pill" style="font-size: 10.5px; background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD; padding: 2px 8px; flex-shrink: 0; font-weight: 600;">Add & select</span>
                    </div>
                `;
            });
        }

        teamSearchDropdown.innerHTML = html;
        teamSearchDropdown.style.display = 'block';

        // Bind click events
        teamSearchDropdown.querySelectorAll('[data-action="add-team-local"]').forEach(el => {
            el.addEventListener('click', function(e) {
                e.stopPropagation();
                const id = parseInt(this.dataset.id);
                const u = window.LOCAL_USERS.find(user => user.id === id);
                if (u) window.addTeamMember(u);
            });
        });

        teamSearchDropdown.querySelectorAll('[data-action="add-team-azure"]').forEach(el => {
            el.addEventListener('click', async function(e) {
                e.stopPropagation();
                const azureId = this.dataset.azureId;
                const pill = this.querySelector('.pill');
                try {
                    const newUser = await addAzureUserToSystem(azureId, pill);
                    window.addTeamMember(newUser);
                } catch (err) {
                    showPeopleFeedback(err.message,true);
                }
            });
        });
    }

    if (teamSearchInput) {
        teamSearchInput.addEventListener('focus', function() {
            queryUsers(this.value, renderTeamDropdown, teamSearchSpinner, assignedTeamMembers.map(m => m.id));
        });
        teamSearchInput.addEventListener('input', function() {
            queryUsers(this.value, renderTeamDropdown, teamSearchSpinner, assignedTeamMembers.map(m => m.id));
        });
    }

    // -------------------------------------------------------------------------
    // 6. GOVERNANCE ROLES PICKERS (Sponsor, Owner, Steering Committee)
    // -------------------------------------------------------------------------
    document.querySelectorAll('.gov-role-picker').forEach(picker => {
        const searchInput = picker.querySelector('.gov-search-input');
        const dropdown = picker.querySelector('.gov-dropdown');
        const hiddenUserId = picker.querySelector('.gov-hidden-user-id');
        const selectedCard = picker.querySelector('.gov-selected-card');
        const searchWrap = picker.querySelector('.gov-search-wrap');
        const avatarEl = picker.querySelector('.gov-avatar');
        const nameEl = picker.querySelector('.gov-name');
        const btnClear = picker.querySelector('.btn-clear-gov');

        function setGovUser(user) {
            if (!user) {
                hiddenUserId.value = '';
                selectedCard.style.display = 'none';
                searchWrap.style.display = 'block';
                searchInput.value = '';
            } else {
                hiddenUserId.value = user.id;
                const initial = (user.name ? user.name.charAt(0) : 'U').toUpperCase();
                avatarEl.textContent = initial;
                nameEl.textContent = `${user.name} (${user.email})`;
                selectedCard.style.display = 'flex';
                searchWrap.style.display = 'none';
                dropdown.style.display = 'none';
            }
            updateGovAssignedCounter();
        }

        if (btnClear) btnClear.addEventListener('click', () => setGovUser(null));

        function renderGovDropdown(localUsers, azureUsers, complete=false) {
            if (localUsers.length === 0 && azureUsers.length === 0) {
                dropdown.innerHTML = `<div class="muted text-sm" style="padding: 10px; text-align: center;">${complete ? 'No people found. Try another name or email.' : searchInput.value.trim().length<2 ? 'Type at least 2 characters to search Azure.' : 'Searching Microsoft Azure?'}</div>`;
                dropdown.style.display = 'block';
                return;
            }

            let html = '';
            if (localUsers.length > 0) {
                html += `<div class="user-dropdown-section-title">Local Users</div>`;
                localUsers.forEach(u => {
                    html += `
                        <div class="user-search-item" data-action="gov-local" data-id="${u.id}">
                            <div>
                                <b style="font-size: 12.5px; color: #22272B;">${escapeHtml(u.name)}</b>
                                <small class="muted" style="font-size: 11px; display: block;">${escapeHtml(u.email)}</small>
                            </div>
                            <span class="pill" style="font-size: 10px;">Select</span>
                        </div>
                    `;
                });
            }

            if (azureUsers.length > 0) {
                html += `<div class="user-dropdown-section-title" style="background:#F0F9FF;color:#0369A1;">Azure Directory</div>`;
                azureUsers.forEach(u => {
                    html += `
                        <div class="user-search-item azure-item" data-action="gov-azure" data-azure-id="${u.id}">
                            <div>
                                <b style="font-size: 12.5px; color: #0F172A;">${escapeHtml(u.displayName)}</b>
                                <small class="muted" style="font-size: 11px; display: block;">${escapeHtml(u.email)}</small>
                            </div>
                            <span class="pill" style="font-size: 10px; background:#E0F2FE; color:#0369A1;">Add & select</span>
                        </div>
                    `;
                });
            }

            dropdown.innerHTML = html;
            dropdown.style.display = 'block';

            dropdown.querySelectorAll('[data-action="gov-local"]').forEach(el => {
                el.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const id = parseInt(this.dataset.id);
                    const u = window.LOCAL_USERS.find(user => user.id === id);
                    if (u) setGovUser(u);
                });
            });

            dropdown.querySelectorAll('[data-action="gov-azure"]').forEach(el => {
                el.addEventListener('click', async function(e) {
                    e.stopPropagation();
                    const azureId = this.dataset.azureId;
                    const pill = this.querySelector('.pill');
                    try {
                        const newUser = await addAzureUserToSystem(azureId, pill);
                        setGovUser(newUser);
                    } catch (err) {
                        showPeopleFeedback(err.message,true);
                    }
                });
            });
        }

        if (searchInput) {
            searchInput.addEventListener('focus', function() {
                queryUsers(this.value, renderGovDropdown, null);
            });
            searchInput.addEventListener('input', function() {
                queryUsers(this.value, renderGovDropdown, null);
            });
        }
        const savedRole = Object.values(restoredProjectState?.members || @json(old('members', $projectDefaults['members'] ?? []))).find(member=>Number(member.project_role_id)===Number(picker.closest('[data-role-id]').dataset.roleId));
        const savedPerson=savedRole && window.LOCAL_USERS.find(person=>person.id===Number(savedRole.user_id));
        if(savedPerson)setGovUser(savedPerson);
    });

    function updateGovAssignedCounter() {
        const count = [...document.querySelectorAll('.gov-hidden-user-id')].filter(input=>input.value).length;
        const pill = document.getElementById('assigned-roles-counter');
        if (pill) {
            pill.textContent = `${count} Assigned`;
            pill.className = count > 0 ? 'pill active' : 'pill';
        }
    }

    // Global Click-Outside handler for all dropdowns
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#pm-search-wrap')) {
            if (pmSearchDropdown) pmSearchDropdown.style.display = 'none';
        }
        if (!e.target.closest('#team-search-dropdown') && !e.target.closest('#team-search-input')) {
            if (teamSearchDropdown) teamSearchDropdown.style.display = 'none';
        }
        document.querySelectorAll('.gov-role-picker').forEach(p => {
            if (!p.contains(e.target)) {
                const dd = p.querySelector('.gov-dropdown');
                if (dd) dd.style.display = 'none';
            }
        });
    });

    // -------------------------------------------------------------------------
    const savedMembers = restoredProjectState?.members || @json(old('members', $projectDefaults['members'] ?? []));
    if (pmUserIdHidden && pmUserIdHidden.value) {
        const savedManager=window.LOCAL_USERS.find(person=>person.id===Number(pmUserIdHidden.value));
        if(savedManager)selectProjectManager(savedManager);
    }
    Object.values(savedMembers).filter(member=>Number(member.project_role_id)===Number(teamRoleId)).forEach(member=>{
        const person=window.LOCAL_USERS.find(person=>person.id===Number(member.user_id));
        if(person)window.addTeamMember(person);
    });

    // 8. FORM SUBMISSION SAFEGUARDS
    // -------------------------------------------------------------------------
    const form = document.getElementById('create-project-form');
    const btnCreate = document.getElementById('btn-create-project');
    const btnDraft = document.getElementById('btn-save-draft');

    if (form) {
        form.addEventListener('submit', function(e) {
            if(form.dataset.submitting){e.preventDefault();return;}
            const activeAction = e.submitter?.value || 'create';
            
            if(radioTemplate.checked && !templateSelect.value){e.preventDefault();showPeopleFeedback('Select a project template before saving.',true);btnBrowseTemplates.focus();return;}
            // If clicking Create Project and no PM selected, prompt user
            if (activeAction === 'create' && (!pmUserIdHidden || !pmUserIdHidden.value)) {
                e.preventDefault();
                showPeopleFeedback('Select a project manager to request approval, or save a draft to assign one later.',true);
                if (pmSearchInput) pmSearchInput.focus();
                return;
            }

            // Preserve the selected action when the submit buttons are disabled.
            form.dataset.submitting='true';
            const selectedAction = document.createElement('input');
            selectedAction.type = 'hidden';
            selectedAction.name = 'action';
            selectedAction.dataset.submitAction='true';
            selectedAction.value = activeAction;
            form.appendChild(selectedAction);
            // Clear draft storage on submit
            // Keep return-state available if server validation sends the user back.

            if (btnCreate) {
                btnCreate.disabled = true;
                btnCreate.textContent = 'Saving Project...';
            }
            if (btnDraft) {
                btnDraft.disabled = true;
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }
});
</script>
@endpush
@endsection
