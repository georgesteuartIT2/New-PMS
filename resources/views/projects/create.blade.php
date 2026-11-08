@extends('layouts.app')
@section('title', 'Create Project')

@section('content')
<!-- GS ProjectFlow - One-Page Minimal-Click Project Creation with Smart Template & Live Team Search -->
<div class="page-head" style="margin-bottom: 20px;">
    <div>
        <div class="breadcrumb-trail">
            <a href="{{ route('projects.index') }}">Projects</a>
            <span class="crumb-sep">›</span>
            <span>Create Project</span>
        </div>
        <h1 style="font-size: 24px; font-weight: 700; color: #22272B; margin: 4px 0 2px;">Create Project</h1>
        <p class="muted" style="margin: 0; font-size: 13.5px;">Add the project details and choose a manager. The project starts after the manager approves.</p>
    </div>
    <a href="{{ route('projects.index') }}" class="btn btn-outline btn-sm">← Back to Projects</a>
</div>

<div class="form-width" style="max-width: 860px; margin: 0 auto;">
    <form method="post" action="{{ route('projects.store') }}" id="create-project-form" class="card form-card stack" style="background: #FFFFFF; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        @csrf

        @if($errors->any())
            <div class="alert danger" style="margin-bottom: 12px; background: #FEF2F2; border: 1px solid #FCA5A5; color: #991B1B; padding: 12px 16px; border-radius: 6px; font-size: 13px;">
                <b style="display: block; margin-bottom: 4px;">Please review the following issues:</b>
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- =====================================================================
             SECTION A: PROJECT IDENTITY
             ===================================================================== -->
        <div class="field-group" style="margin-bottom: 6px;">
            <div class="form-section-title" style="display: flex; align-items: center; gap: 8px;">
                <span>Project Identity</span>
            </div>

            <!-- Project Name -->
            <label style="margin-bottom: 14px;">Project Name <span class="required">*</span>
                <input 
                    name="name" 
                    id="project_name" 
                    value="{{ old('name') }}" 
                    maxlength="160" 
                    required 
                    placeholder="e.g. ERP System Migration, Factory Expansion Phase 2"
                    autofocus
                    style="height: 40px; font-size: 14px;"
                >
                @error('name')
                    <small class="text-danger block mt-4" style="color: var(--danger);">{{ $message }}</small>
                @enderror
            </label>

            <!-- Subsidiary & Auto-Generated Project Code -->
            <div class="two-fields" style="align-items: flex-start; gap: 16px;">
                <label style="margin-bottom: 0;">Subsidiary / Company Entity <span class="required">*</span>
                    <select name="subsidiary_id" id="subsidiary_select" required style="height: 40px; font-size: 13.5px; background: #FFFFFF;">
                        @foreach($subsidiaries as $sub)
                            <option 
                                value="{{ $sub->id }}" 
                                data-code="{{ $sub->code }}" 
                                data-preview="{{ $sub->previewProjectCode() }}"
                                @selected(old('subsidiary_id', $defaultSubsidiary?->id) == $sub->id)
                            >
                                {{ $sub->name }} [{{ $sub->code }}]
                            </option>
                        @endforeach
                    </select>
                    @error('subsidiary_id')
                        <small class="text-danger block mt-4" style="color: var(--danger);">{{ $message }}</small>
                    @enderror
                </label>

                <div>
                    <label style="margin-bottom: 6px;">Generated Project Code</label>
                    <div style="display: flex; align-items: center; height: 40px; padding: 0 12px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);">
                        <code id="project_code_preview" style="font-size: 13.5px; font-weight: 700; color: #315F58; letter-spacing: 0.5px;">
                            {{ $defaultSubsidiary ? $defaultSubsidiary->previewProjectCode() : 'PRJ-001' }}
                        </code>
                        <span class="muted" style="font-size: 11px; margin-left: auto;">Auto-generated</span>
                    </div>
                </div>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid var(--border); margin: 6px 0 10px;">

        <!-- =====================================================================
             SECTION B: PROJECT SETUP (Blank vs Template Flow)
             ===================================================================== -->
        <div class="field-group" style="margin-bottom: 6px;">
            <div class="form-section-title" style="display: flex; align-items: center; gap: 8px;">
                <span>Project Setup</span>
            </div>

            <div class="project-type-grid" style="margin-bottom: 12px;">
                <!-- Option 1: Blank Project -->
                <label class="project-type-card {{ old('mode', request('template_id') ? 'template' : 'blank') === 'blank' ? 'active' : '' }}" id="card-mode-blank">
                    <input type="radio" name="mode" value="blank" data-mode="blank" {{ old('mode', request('template_id') ? 'template' : 'blank') === 'blank' ? 'checked' : '' }}>
                    <div>
                        <b style="display: block; font-size: 13.5px; color: var(--text);">Blank Project</b>
                        <small class="muted" style="display: block; font-size: 12px; margin-top: 2px;">Start without predefined tasks. Tasks can be created manually or imported from Excel.</small>
                    </div>
                </label>

                <!-- Option 2: Use Template -->
                <label class="project-type-card {{ old('mode', request('template_id') ? 'template' : 'blank') === 'template' ? 'active' : '' }}" id="card-mode-template">
                    <input type="radio" name="mode" value="template" data-mode="template" {{ old('mode', request('template_id') ? 'template' : 'blank') === 'template' ? 'checked' : '' }}>
                    <div>
                        <b style="display: block; font-size: 13.5px; color: var(--text);">Use Template</b>
                        <small class="muted" style="display: block; font-size: 12px; margin-top: 2px;">Start with an existing project schedule, predefined tasks, and automatic deadlines.</small>
                    </div>
                </label>
            </div>

            <!-- Template Box Container (Conditional on "Use Template") -->
            <div id="template-selector-box" style="{{ old('mode', request('template_id') ? 'template' : 'blank') === 'template' ? '' : 'display: none;' }}; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; margin-top: 8px;">
                
                <!-- State A: Selected Template Showcase Card (Prominently displayed when template chosen) -->
                <div id="selected-template-showcase" style="display: none; background: #FFFFFF; border: 1.5px solid #315F58; border-radius: 8px; padding: 14px 18px; box-shadow: 0 1px 3px rgba(49,95,88,0.06);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 240px;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <span class="pill active" style="font-size: 11px; background: #315F58; color: #FFFFFF; font-weight: 650;">✓ Selected Template</span>
                                <span id="showcase-template-category" class="pill" style="font-size: 11px;">General</span>
                            </div>
                            <h3 id="showcase-template-name" style="margin: 0 0 6px; font-size: 15.5px; font-weight: 700; color: #22272B;"></h3>
                            <div style="font-size: 12.5px; color: #64748B; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <span id="showcase-template-summary" style="font-weight: 600; color: #334155;"></span>
                                <span style="color: #CBD5E1;">•</span>
                                <span id="showcase-template-hours" style="font-size: 11.5px;"></span>
                            </div>
                            <p id="showcase-template-desc" class="muted" style="margin: 6px 0 0; font-size: 12px; line-height: 1.4; max-width: 580px;"></p>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <button type="button" class="btn btn-outline btn-sm" id="btn-preview-template-plan" style="height: 32px; font-size: 12px; font-weight: 650;">
                                Preview Task Plan
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" id="btn-change-template-redirect" style="height: 32px; font-size: 12px; color: #315F58; border-color: #315F58; font-weight: 600;">
                                Change Template ↺
                            </button>
                        </div>
                    </div>
                </div>

                <!-- State B: Dropdown Selector (Also functional directly in-page) -->
                <div id="template-dropdown-wrap" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                        <label style="margin: 0; font-size: 13px; font-weight: 650;">Select Project Template <span class="required">*</span></label>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-browse-templates" style="background: #315F58; color: #FFFFFF; font-size: 12px; height: 30px; font-weight: 600; padding: 0 12px;">
                            Browse & Preview Templates →
                        </button>
                    </div>
                    <select name="project_template_id" id="project_template_id" style="height: 40px; font-size: 13.5px; background: #FFFFFF; width: 100%;">
                        <option value="">Choose a pre-configured template...</option>
                        @foreach($templates as $template)
                            @php $sched = $template->schedule(); @endphp
                            <option 
                                value="{{ $template->id }}" 
                                data-name="{{ $template->name }}"
                                data-category="{{ $template->category }}"
                                data-desc="{{ $template->description }}"
                                data-days="{{ $sched['working_days'] }}" 
                                data-tasks="{{ $sched['task_count'] }}" 
                                data-hours="{{ $template->working_hours ?: '09:00 - 17:00' }}"
                                data-summary="{{ $sched['summary'] }}"
                                @selected(old('project_template_id', request('template_id')) == $template->id)
                            >
                                {{ $template->name }} ({{ $sched['summary'] }})
                            </option>
                        @endforeach
                    </select>
                    <small class="muted block mt-4" style="font-size: 11.5px;">Tasks, dependencies, and working-day schedules will be automatically prepared.</small>
                </div>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid var(--border); margin: 6px 0 10px;">

        <!-- =====================================================================
             SECTION C: TIMELINE & AUTOMATIC DEADLINE
             ===================================================================== -->
        <div class="field-group" style="margin-bottom: 6px;">
            <div class="form-section-title" style="display: flex; align-items: center; gap: 8px;">
                <span>Project Timeline</span>
            </div>

            <div class="two-fields" style="align-items: flex-start; gap: 16px;">
                <!-- Start Date -->
                <label style="margin-bottom: 0;">Start Date <span class="required">*</span>
                    <input 
                        type="date" 
                        name="starts_on" 
                        id="starts_on" 
                        value="{{ old('starts_on', date('Y-m-d')) }}" 
                        required 
                        style="height: 40px; font-size: 13.5px;"
                    >
                    @error('starts_on')
                        <small class="text-danger block mt-4" style="color: var(--danger);">{{ $message }}</small>
                    @enderror
                </label>

                <!-- Project Deadline (Auto-calculated on template or manual) -->
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label style="margin: 0; font-size: 13px; font-weight: 600;">Project Deadline <span class="required">*</span></label>
                        <span id="deadline-status-tag" style="display: none;"></span>
                    </div>
                    <input 
                        type="date" 
                        name="ends_on" 
                        id="ends_on" 
                        value="{{ old('ends_on') }}" 
                        required 
                        style="height: 40px; font-size: 13.5px;"
                    >
                    <div id="deadline-feedback-box" style="margin-top: 4px;"></div>
                    @error('ends_on')
                        <small class="text-danger block mt-4" style="color: var(--danger);">{{ $message }}</small>
                    @enderror
                </div>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid var(--border); margin: 6px 0 10px;">

        <!-- =====================================================================
             SECTION D: PROJECT TEAM (LIVE SEARCH + AZURE DIRECTORY INTEGRATION)
             ===================================================================== -->
        <div class="field-group" style="margin-bottom: 6px;">
            <div class="form-section-title" style="display: flex; align-items: center; gap: 8px;">
                <span>Project Team</span>
            </div>

            <!-- 1. PROJECT MANAGER (PM) -->
            <div style="margin-bottom: 18px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <div>
                        <label style="margin: 0; font-size: 13px; font-weight: 650; color: #22272B;">
                            Project Manager (PM) <span class="label-optional" style="color: #64748B;">(Required to launch)</span>
                        </label>
                        <small class="muted block" style="font-size: 11.5px; margin-top: 2px;">Assigned leader responsible for project delivery.</small>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" data-show-dialog="quick-add-user-modal" style="font-size: 11.5px; padding: 2px 10px; height: 26px;">
                        + Manual User
                    </button>
                </div>

                <!-- Selected PM Card (Shown when PM is chosen) -->
                <div id="pm-selected-box" style="display: none; align-items: center; justify-content: space-between; padding: 10px 14px; background: #FFFFFF; border: 1.5px solid #315F58; border-radius: 6px; box-shadow: 0 1px 2px rgba(49,95,88,0.05);">
                    <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                        <span class="avatar avatar-sm" id="pm-selected-avatar" style="background: #315F58; color: #FFFFFF; font-weight: 700; flex-shrink: 0;">PM</span>
                        <div style="min-width: 0;">
                            <b id="pm-selected-name" style="font-size: 13.5px; color: #22272B; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></b>
                            <small id="pm-selected-subtext" class="muted" style="font-size: 11.5px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></small>
                        </div>
                        <span class="pill active" style="font-size: 11px; margin-left: 6px; flex-shrink: 0;">Project Manager</span>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" id="btn-pm-change" style="font-size: 12px; color: #315F58; font-weight: 600; padding: 2px 8px; flex-shrink: 0;">
                        Change PM
                    </button>
                </div>

                <!-- PM Live Search & Dropdown (Shown when no PM chosen or changing) -->
                <div id="pm-search-wrap" style="position: relative;">
                    <div style="position: relative; display: flex; align-items: center;">
                        <span style="position: absolute; left: 12px; color: #94A3B8; font-size: 14px; pointer-events: none;">🔍</span>
                        <input 
                            type="text" 
                            id="pm-search-input" 
                            placeholder="Type name or email to search local team or Microsoft Azure..." 
                            autocomplete="off"
                            style="height: 40px; padding-left: 36px; padding-right: 36px; font-size: 13.5px; background: #FFFFFF; border: 1.5px solid var(--border); border-radius: var(--radius); width: 100%;"
                        >
                        <span id="pm-search-spinner" style="position: absolute; right: 12px; font-size: 11.5px; color: #315F58; display: none;">Searching Azure...</span>
                    </div>

                    <!-- Dropdown Results -->
                    <div id="pm-search-dropdown" class="user-picker-dropdown" style="display: none;"></div>
                </div>

                <input type="hidden" name="pm_user_id" id="pm_user_id" value="{{ old('pm_user_id') }}">

                <small class="muted block mt-4" id="pm_help_text" style="font-size: 11.5px;">
                    A Project Manager is required before submitting for acceptance, or click <b>Save as Draft</b> below to assign later.
                </small>
                @error('pm_user_id')
                    <small class="text-danger block mt-4" style="color: var(--danger); font-weight: 600;">{{ $message }}</small>
                @enderror
            </div>

            <!-- 2. TEAM MEMBERS (Search Option on Top + 1-Click Azure/Local assignment) -->
            @php $teamRole = $roles->firstWhere('slug', 'team'); @endphp
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 16px; margin-bottom: 14px;">
                <div style="margin-bottom: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <label style="margin: 0; font-size: 13px; font-weight: 650; color: var(--text);">
                            Team Members <span class="label-optional">(Optional)</span>
                        </label>
                        <span id="team-members-badge" class="pill" style="font-size: 11px;">0 Members</span>
                    </div>
                    <small class="muted block mt-2" style="font-size: 11.5px;">Assignees who will execute and collaborate on project tasks.</small>
                </div>

                <!-- Live Search Bar right above team members -->
                <div style="position: relative; margin-bottom: 12px;">
                    <div style="position: relative; display: flex; align-items: center;">
                        <span style="position: absolute; left: 12px; color: #94A3B8; font-size: 14px; pointer-events: none;">🔍</span>
                        <input 
                            type="text" 
                            id="team-search-input" 
                            placeholder="Type name or email to search and add team members..." 
                            autocomplete="off"
                            style="height: 40px; padding-left: 36px; padding-right: 36px; font-size: 13.5px; background: #FFFFFF; border: 1.5px solid var(--border); border-radius: var(--radius); width: 100%;"
                        >
                        <span id="team-search-spinner" style="position: absolute; right: 12px; font-size: 11.5px; color: #315F58; display: none;">Searching Azure...</span>
                    </div>

                    <!-- Team Search Dropdown -->
                    <div id="team-search-dropdown" class="user-picker-dropdown" style="display: none;"></div>
                </div>

                <!-- Assigned Team Members List -->
                <div id="assigned-team-list" style="display: flex; flex-direction: column; gap: 6px;"></div>
                
                <div id="team-empty-state" class="muted text-sm" style="text-align: center; padding: 18px 12px; background: #FFFFFF; border: 1px dashed #CBD5E1; border-radius: 6px; font-size: 12.5px;">
                    No additional team members added yet. Type a name or email above to add members in 1 click.
                </div>
            </div>
        </div>

        <!-- =====================================================================
             SECTION E: OPTIONAL GOVERNANCE ROLES (Sponsor, Owner, Steering Committee)
             ===================================================================== -->
        <details id="governance-roles-section" class="card" style="background: var(--surface); border: 1px solid var(--border); padding: 14px 18px; margin-top: 4px;">
            <summary style="cursor: pointer; font-weight: 650; color: #315F58; display: flex; justify-content: space-between; align-items: center; user-select: none;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span>+ Additional Governance Roles (Project Sponsor, Project Owner)</span>
                    <small class="muted" style="font-weight: 400; font-size: 12px;">(Optional)</small>
                </div>
                <span class="pill" id="assigned-roles-counter" style="font-size: 11px;">0 Assigned</span>
            </summary>

            <p class="muted text-sm mt-8" style="margin-bottom: 12px;">
                Assign optional executive and steering committee stakeholders. Search local employees or Microsoft Azure.
            </p>

            <div class="role-matrix-container" style="display: flex; flex-direction: column; gap: 10px;">
                <!-- Project Sponsor -->
                @php $sponsorRole = $roles->firstWhere('slug', 'ps'); @endphp
                @if($sponsorRole)
                    <div class="role-matrix-row" data-role-id="{{ $sponsorRole->id }}" data-role-index="100">
                        <div>
                            <b style="font-size: 13px; color: var(--text);">Project Sponsor</b>
                            <small class="block muted" style="font-size: 11px;">Executive Oversight</small>
                        </div>
                        <div class="gov-role-picker" style="position: relative;">
                            <div class="gov-selected-card" style="display: none; align-items: center; justify-content: space-between; padding: 6px 12px; background: #FFFFFF; border: 1px solid #C2DDD7; border-radius: 4px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="avatar avatar-sm gov-avatar"></span>
                                    <span class="gov-name" style="font-size: 13px; font-weight: 600;"></span>
                                </div>
                                <button type="button" class="btn btn-ghost btn-sm btn-clear-gov" style="padding: 2px 6px; font-size: 12px; color: #DC2626;">✕</button>
                            </div>
                            <div class="gov-search-wrap">
                                <input type="text" class="gov-search-input" placeholder="Search sponsor by name or email..." style="height: 36px; font-size: 13px; background: #FFFFFF; width: 100%;">
                                <div class="gov-dropdown user-picker-dropdown" style="display: none;"></div>
                            </div>
                            <input type="hidden" name="members[100][user_id]" class="gov-hidden-user-id" value="">
                            <input type="hidden" name="members[100][project_role_id]" value="{{ $sponsorRole->id }}">
                        </div>
                        <span class="muted text-sm" style="font-size: 11px;">Single</span>
                    </div>
                @endif

                <!-- Project Owner -->
                @php $ownerRole = $roles->firstWhere('slug', 'po'); @endphp
                @if($ownerRole)
                    <div class="role-matrix-row" data-role-id="{{ $ownerRole->id }}" data-role-index="101">
                        <div>
                            <b style="font-size: 13px; color: var(--text);">Project Owner</b>
                            <small class="block muted" style="font-size: 11px;">Business Stakeholder</small>
                        </div>
                        <div class="gov-role-picker" style="position: relative;">
                            <div class="gov-selected-card" style="display: none; align-items: center; justify-content: space-between; padding: 6px 12px; background: #FFFFFF; border: 1px solid #C2DDD7; border-radius: 4px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="avatar avatar-sm gov-avatar"></span>
                                    <span class="gov-name" style="font-size: 13px; font-weight: 600;"></span>
                                </div>
                                <button type="button" class="btn btn-ghost btn-sm btn-clear-gov" style="padding: 2px 6px; font-size: 12px; color: #DC2626;">✕</button>
                            </div>
                            <div class="gov-search-wrap">
                                <input type="text" class="gov-search-input" placeholder="Search owner by name or email..." style="height: 36px; font-size: 13px; background: #FFFFFF; width: 100%;">
                                <div class="gov-dropdown user-picker-dropdown" style="display: none;"></div>
                            </div>
                            <input type="hidden" name="members[101][user_id]" class="gov-hidden-user-id" value="">
                            <input type="hidden" name="members[101][project_role_id]" value="{{ $ownerRole->id }}">
                        </div>
                        <span class="muted text-sm" style="font-size: 11px;">Single</span>
                    </div>
                @endif

                <!-- Steering Committee -->
                @php $steeringRole = $roles->firstWhere('slug', 'steering'); @endphp
                @if($steeringRole)
                    <div class="role-matrix-row" data-role-id="{{ $steeringRole->id }}" data-role-index="102">
                        <div>
                            <b style="font-size: 13px; color: var(--text);">Steering Committee</b>
                            <small class="block muted" style="font-size: 11px;">Governance Board</small>
                        </div>
                        <div class="gov-role-picker" style="position: relative;">
                            <div class="gov-selected-card" style="display: none; align-items: center; justify-content: space-between; padding: 6px 12px; background: #FFFFFF; border: 1px solid #C2DDD7; border-radius: 4px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="avatar avatar-sm gov-avatar"></span>
                                    <span class="gov-name" style="font-size: 13px; font-weight: 600;"></span>
                                </div>
                                <button type="button" class="btn btn-ghost btn-sm btn-clear-gov" style="padding: 2px 6px; font-size: 12px; color: #DC2626;">✕</button>
                            </div>
                            <div class="gov-search-wrap">
                                <input type="text" class="gov-search-input" placeholder="Search committee member by name or email..." style="height: 36px; font-size: 13px; background: #FFFFFF; width: 100%;">
                                <div class="gov-dropdown user-picker-dropdown" style="display: none;"></div>
                            </div>
                            <input type="hidden" name="members[102][user_id]" class="gov-hidden-user-id" value="">
                            <input type="hidden" name="members[102][project_role_id]" value="{{ $steeringRole->id }}">
                        </div>
                        <span class="muted text-sm" style="font-size: 11px;">Single</span>
                    </div>
                @endif
            </div>
        </details>

        <!-- =====================================================================
             SECTION F: DESCRIPTION (Optional)
             ===================================================================== -->
        <label style="margin-top: 4px;">Project Description <span class="label-optional">(Optional)</span>
            <textarea 
                name="description" 
                id="project_description"
                rows="3" 
                placeholder="Brief summary of objectives, deliverables, or business scope..." 
                style="font-size: 13.5px;"
            >{{ old('description') }}</textarea>
        </label>

        <!-- =====================================================================
             SECTION G: BOTTOM ACTIONS
             ===================================================================== -->
        <div class="form-actions project-create-actions" style="display: flex; justify-content: space-between; align-items: center; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border);">
            <a class="btn btn-outline" href="{{ route('projects.index') }}" style="height: 40px; line-height: 38px;">Cancel</a>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <!-- Save as Draft (PM optional) -->
                <button 
                    type="submit" 
                    name="action" 
                    value="draft" 
                    class="btn btn-outline" 
                    id="btn-save-draft" 
                    style="height: 40px; font-weight: 600;"
                >
                    Save as Draft
                </button>

                <!-- Create Project (Primary #315F58) -->
                <button 
                    type="submit" 
                    name="action" 
                    value="create" 
                    class="btn btn-primary" 
                    id="btn-create-project" 
                    style="height: 40px; font-weight: 650; min-width: 140px; background: #315F58; color: #FFFFFF;"
                >
                    Send for PM Approval
                </button>
            </div>
        </div>
    </form>
</div>

<!-- =========================================================================
     COMPACT INLINE QUICK ADD USER MODAL (Fallback Manual Create)
     ========================================================================= -->
<dialog id="quick-add-user-modal" class="modal" style="max-width: 520px;">
    <div class="modal-head">
        <h2>Add User Manually</h2>
        <button class="icon-btn" type="button" data-close-dialog>×</button>
    </div>

    <div style="padding: 12px 18px 20px;">
        <p class="muted text-sm" style="margin-bottom: 14px;">Create an active user with a temporary password and select them for your project.</p>
        <div id="quick-manual-error" class="alert danger" style="display: none; margin-bottom: 12px;"></div>

        <div class="stack">
            <label>Full Name <span class="required">*</span>
                <input id="quick_manual_name" required maxlength="120" placeholder="e.g. Nimal Perera" style="height: 38px;">
            </label>

            <label>Work Email <span class="required">*</span>
                <input id="quick_manual_email" type="email" required placeholder="nimal@company.com" style="height: 38px;">
            </label>

            @if(count($subsidiaries))
                <label>Subsidiary (Optional)
                    <select id="quick_manual_subsidiary" style="height: 38px; background: #FFFFFF;">
                        <option value="">-- None / General --</option>
                        @foreach($subsidiaries as $s)
                            <option value="{{ $s->id }}">{{ $s->name }} [{{ $s->code }}]</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <div class="form-actions mt-16" style="justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-submit-quick-manual" style="background: #315F58; color: #FFFFFF;">Add & Select</button>
            </div>
        </div>
    </div>
</dialog>

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
<script src="{{ asset('js/template-builder.js') }}"></script>
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
            canCreateProject: true,
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

    function saveProjectDraft() {
        const draft = {
            name: projectNameInput ? projectNameInput.value : '',
            subsidiary_id: subSelect ? subSelect.value : '',
            starts_on: startDateInput ? startDateInput.value : '',
            description: projectDescInput ? projectDescInput.value : '',
        };
        sessionStorage.setItem('gs_project_create_draft', JSON.stringify(draft));
    }

    function restoreProjectDraft() {
        const raw = sessionStorage.getItem('gs_project_create_draft');
        if (!raw) return;
        try {
            const draft = JSON.parse(raw);
            if (projectNameInput && !projectNameInput.value && draft.name) projectNameInput.value = draft.name;
            if (subSelect && draft.subsidiary_id) {
                subSelect.value = draft.subsidiary_id;
                subSelect.dispatchEvent(new Event('change'));
            }
            if (startDateInput && draft.starts_on) startDateInput.value = draft.starts_on;
            if (projectDescInput && !projectDescInput.value && draft.description) projectDescInput.value = draft.description;
        } catch (e) {}
    }

    // Restore draft immediately on page load
    restoreProjectDraft();

    // Subsidiary preview code updater
    if (subSelect && codePreview) {
        subSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            codePreview.textContent = (opt && opt.dataset.preview) ? opt.dataset.preview : 'PRJ-001';
        });
    }

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
    let isManuallyOverridden = false;

    function redirectToTemplateSelector() {
        saveProjectDraft();
        window.location.href = "{{ route('templates.index', ['select_for_project' => 1]) }}";
    }

    // When clicking "Use Template" radio card
    if (radioTemplate) {
        radioTemplate.addEventListener('click', function() {
            // If no template is currently selected, redirect to templates page to pick one
            if (!templateSelect.value) {
                redirectToTemplateSelector();
            } else {
                updateTemplateModeUI();
            }
        });
    }

    if (btnBrowseTemplates) btnBrowseTemplates.addEventListener('click', redirectToTemplateSelector);
    if (btnChangeTemplate) btnChangeTemplate.addEventListener('click', redirectToTemplateSelector);

    if (radioBlank) {
        radioBlank.addEventListener('change', function() {
            updateTemplateModeUI();
        });
    }

    function updateTemplateModeUI() {
        const isTemplate = radioTemplate && radioTemplate.checked;
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
            calculateDeadline();
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
    let azureSearchTimeout = null;

    /**
     * Search function querying Local Users first (0ms), then Azure if query >= 2 chars.
     */
    async function queryUsers(term, onResults, spinnerEl, excludeIds = []) {
        term = (term || '').trim().toLowerCase();
        
        // 1. Instant local filter
        let localMatches = window.LOCAL_USERS.filter(u => {
            if (excludeIds.includes(u.id)) return false;
            if (!term) return true; // if empty, show all local users
            return (u.name && u.name.toLowerCase().includes(term)) || 
                   (u.email && u.email.toLowerCase().includes(term));
        });

        // Limit initial empty view to first 25 local users
        if (!term && localMatches.length > 25) {
            localMatches = localMatches.slice(0, 25);
        }

        // Render local matches immediately
        onResults(localMatches, [], false);

        if (term.length < 2) {
            if (spinnerEl) spinnerEl.style.display = 'none';
            return;
        }

        // 2. Debounced Microsoft Azure Query
        if (spinnerEl) spinnerEl.style.display = 'inline';
        clearTimeout(azureSearchTimeout);

        azureSearchTimeout = setTimeout(async function() {
            try {
                const res = await fetch(`{{ route('users.azure') }}?q=${encodeURIComponent(term)}&ajax=1`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (spinnerEl) spinnerEl.style.display = 'none';
                if (!res.ok) return;

                const data = await res.json();
                const azureResults = data.results || [];

                // Filter out any Azure users whose email already exists in LOCAL_USERS
                const localEmails = new Set(window.LOCAL_USERS.map(u => (u.email || '').toLowerCase()));
                const filteredAzure = azureResults.filter(a => {
                    const mail = (a.email || '').toLowerCase();
                    return mail && !localEmails.has(mail);
                });

                onResults(localMatches, filteredAzure, true);
            } catch (err) {
                if (spinnerEl) spinnerEl.style.display = 'none';
            }
        }, 280);
    }

    /**
     * Add an Azure user to system database via /users/azure/add
     */
    async function addAzureUserToSystem(azureId, targetBtn) {
        if (targetBtn) {
            targetBtn.disabled = true;
            targetBtn.textContent = 'Adding...';
        }

        const res = await fetch("{{ route('users.azure.add') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ azure_id: azureId })
        });

        const data = await res.json();
        if (!res.ok) {
            if (targetBtn) {
                targetBtn.disabled = false;
                targetBtn.textContent = '+ Add from Azure';
            }
            throw new Error(data.message || 'Failed to add Azure user to system.');
        }

        const addedUser = data.user || { id: data.user_id };
        // Check if user is already in LOCAL_USERS
        if (!window.LOCAL_USERS.some(u => u.id === addedUser.id)) {
            window.LOCAL_USERS.push({
                id: addedUser.id,
                name: addedUser.name,
                email: addedUser.email,
                subsidiary_code: addedUser.subsidiary?.code || '',
                subsidiary_name: addedUser.subsidiary?.name || '',
            });
        }

        return addedUser;
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
            pmHelpText.innerHTML = `<span style="color: var(--success); font-weight: 650;">✓ Assigned ${escapeHtml(user.name)} as Project Manager!</span> ${note ? '<small class="muted">' + escapeHtml(note) + '</small>' : ''}`;
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
            pmHelpText.innerHTML = 'A Project Manager is required before submitting for acceptance, or click <b>Save as Draft</b> below to assign later.';
        }
    }

    if (btnPmChange) btnPmChange.addEventListener('click', clearProjectManager);

    function renderPmDropdown(localUsers, azureUsers) {
        if (localUsers.length === 0 && azureUsers.length === 0) {
            pmSearchDropdown.innerHTML = `<div class="muted text-sm" style="padding: 14px; text-align: center;">No matching employees found in local team or Microsoft Azure.</div>`;
            pmSearchDropdown.style.display = 'block';
            return;
        }

        let html = '';

        if (localUsers.length > 0) {
            html += `<div class="user-dropdown-section-title">Local System Users (${localUsers.length})</div>`;
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
            html += `<div class="user-dropdown-section-title" style="background: #F0F9FF; color: #0369A1;">Microsoft Azure Entra Directory (${azureUsers.length})</div>`;
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
                        <span class="pill" style="font-size: 10.5px; background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD; padding: 2px 8px; flex-shrink: 0; font-weight: 600;">+ Add from Azure & Select</span>
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
                    alert(err.message);
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

    // If PM was previously chosen (e.g. from old input), select them now
    if (pmUserIdHidden && pmUserIdHidden.value) {
        const existingPm = window.LOCAL_USERS.find(u => u.id === parseInt(pmUserIdHidden.value));
        if (existingPm) selectProjectManager(existingPm);
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
            alert(`${user.name} is already added to the team.`);
            return;
        }
        // Check if already PM
        if (pmUserIdHidden && parseInt(pmUserIdHidden.value) === user.id) {
            alert(`${user.name} is already assigned as the Project Manager.`);
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

    function renderTeamDropdown(localUsers, azureUsers) {
        const assignedIds = new Set(assignedTeamMembers.map(m => m.id));
        if (pmUserIdHidden && pmUserIdHidden.value) {
            assignedIds.add(parseInt(pmUserIdHidden.value));
        }

        const filteredLocal = localUsers.filter(u => !assignedIds.has(u.id));

        if (filteredLocal.length === 0 && azureUsers.length === 0) {
            teamSearchDropdown.innerHTML = `<div class="muted text-sm" style="padding: 14px; text-align: center;">No additional employees found in local team or Microsoft Azure.</div>`;
            teamSearchDropdown.style.display = 'block';
            return;
        }

        let html = '';

        if (filteredLocal.length > 0) {
            html += `<div class="user-dropdown-section-title">Local System Users (${filteredLocal.length})</div>`;
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
            html += `<div class="user-dropdown-section-title" style="background: #F0F9FF; color: #0369A1;">Microsoft Azure Entra Directory (${azureUsers.length})</div>`;
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
                        <span class="pill" style="font-size: 10.5px; background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD; padding: 2px 8px; flex-shrink: 0; font-weight: 600;">+ Add from Azure & Assign</span>
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
                    alert(err.message);
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

        function renderGovDropdown(localUsers, azureUsers) {
            if (localUsers.length === 0 && azureUsers.length === 0) {
                dropdown.innerHTML = `<div class="muted text-sm" style="padding: 10px; text-align: center;">No matching users found.</div>`;
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
                            <span class="pill" style="font-size: 10px; background:#E0F2FE; color:#0369A1;">+ Add & Select</span>
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
                        alert(err.message);
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
    });

    function updateGovAssignedCounter() {
        const count = document.querySelectorAll('.gov-hidden-user-id[value]:not([value=""])').length;
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
    // 7. MANUAL USER CREATE DIALOG FALLBACK
    // -------------------------------------------------------------------------
    const quickModal = document.getElementById('quick-add-user-modal');
    const btnSubmitManual = document.getElementById('btn-submit-quick-manual');

    if (btnSubmitManual) {
        btnSubmitManual.addEventListener('click', async function() {
            const nameInput = document.getElementById('quick_manual_name');
            const emailInput = document.getElementById('quick_manual_email');
            const subInput = document.getElementById('quick_manual_subsidiary');
            const errBox = document.getElementById('quick-manual-error');

            if (!nameInput.value.trim() || !emailInput.value.trim()) {
                errBox.style.display = 'block';
                errBox.textContent = 'Please provide both full name and a valid work email.';
                return;
            }

            errBox.style.display = 'none';
            btnSubmitManual.disabled = true;
            btnSubmitManual.textContent = 'Adding...';

            try {
                const res = await fetch("{{ route('users.quick') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        name: nameInput.value.trim(),
                        email: emailInput.value.trim(),
                        subsidiary_id: subInput ? subInput.value : null
                    })
                });

                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Could not create user.');

                const user = data.user;
                if (!window.LOCAL_USERS.some(u => u.id === user.id)) {
                    window.LOCAL_USERS.push(user);
                }

                selectProjectManager(user, `(Temporary Password: ${data.temp_password})`);
                if (quickModal && typeof quickModal.close === 'function') quickModal.close();
            } catch (err) {
                errBox.style.display = 'block';
                errBox.textContent = err.message;
            } finally {
                btnSubmitManual.disabled = false;
                btnSubmitManual.textContent = 'Add & Select';
            }
        });
    }

    // -------------------------------------------------------------------------
    // 8. FORM SUBMISSION SAFEGUARDS
    // -------------------------------------------------------------------------
    const form = document.getElementById('create-project-form');
    const btnCreate = document.getElementById('btn-create-project');
    const btnDraft = document.getElementById('btn-save-draft');

    if (form) {
        form.addEventListener('submit', function(e) {
            const activeAction = e.submitter?.value || 'create';
            
            // If clicking Create Project and no PM selected, prompt user
            if (activeAction === 'create' && (!pmUserIdHidden || !pmUserIdHidden.value)) {
                e.preventDefault();
                alert('A Project Manager is required to launch the project.\n\nPlease search and select a Project Manager, or click "Save as Draft" to assign later.');
                if (pmSearchInput) pmSearchInput.focus();
                return;
            }

            // Preserve the selected action when the submit buttons are disabled.
            const selectedAction = document.createElement('input');
            selectedAction.type = 'hidden';
            selectedAction.name = 'action';
            selectedAction.value = activeAction;
            form.appendChild(selectedAction);
            // Clear draft storage on submit
            sessionStorage.removeItem('gs_project_create_draft');

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
