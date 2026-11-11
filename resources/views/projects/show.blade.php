@extends('layouts.app')
@section('title', $project->name)
@section('content')
@include('projects._delete-dialog')
@include('projects._workflow')
@php
    $projectImportLabel=$project->template ? 'Download project template' : 'Download blank template';
    $projectImportUrl=route('tasks.export',['project'=>$project,'starter'=>1]);
@endphp
<script defer src="{{ asset('js/project-import.js') }}?v={{ filemtime(public_path('js/project-import.js')) }}"></script>
@if(app(\App\Services\Access::class)->project(auth()->user(),$project,'assign_tasks') && $project->status!=='archived')
<template id="project-assignee-options">@foreach(($project->approval_stage==='planning' ? $users : $project->memberships->pluck('user')->push($project->manager)->filter(fn($person)=>$person && $person->account_status==='active')->unique('id')->sortBy('name')) as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</template>
@endif
<script defer src="{{ asset('js/task-assignment.js') }}?v={{ filemtime(public_path('js/task-assignment.js')) }}"></script>


<div class="project-workspace-v3" id="project-workspace">

    <nav class="workspace-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('projects.index') }}">&larr; Projects</a>
    </nav>
    <section class="workspace-overview-card" aria-label="Project overview">
    <!-- Title & Top Action Bar -->
    <div class="pw-head-row">
        <div class="pw-head-left">
            <span class="project-identity-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a1 1 0 0 1 1-1h6l2 3h8a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7z"/></svg></span>
            <div class="project-identity-copy">
                <h1>{{ $project->name }}</h1>
                <div class="pw-head-meta"><span>{{ $project->code }}</span><span class="meta-sep" aria-hidden="true">&middot;</span><span>{{ $project->subsidiary?->name ?? 'General Group' }}</span></div>
            </div>
        </div>
        <div class="pw-head-actions">
            <!-- Project Status Badge -->
            <span class="project-status-pill status-{{ $project->status }}">
                <span class="status-dot"></span>
                <span>
                    @if($project->status === 'declined')
                        {{ $project->approval_stage==='owner_declined'?'Owner Declined':'PM Declined' }}
                    @elseif($project->status === 'pending')
                        {{ $project->approval_stage==='owner'?'Pending Owner Approval':'Pending PM Approval' }}
                    @else
                        {{ ucwords(str_replace('_', ' ', $project->status)) }}
                    @endif
                </span>
            </span>

            @if(app(\App\Services\Access::class)->project(auth()->user(), $project, 'edit_project') && $project->status !== 'archived')
                <button type="button" class="btn btn-outline workspace-edit-project" data-show-dialog="project-edit-dialog">Edit project</button>
            @endif
            @if($project->status==='draft' && (auth()->user()->isPmo() || ($project->created_by===auth()->id() && app(\App\Services\Access::class)->hasCompanyPermission(auth()->user(),'projects.create'))))<a href="{{ route('projects.create',['draft'=>$project->id]) }}" class="btn btn-primary">Create project</a>@endif
        </div>
    </div>

    <!-- PM Acceptance & Comprehensive Scope Review Banner (When project is pending and current user is PM) -->
    @if(!auth()->user()->isAdminViewer() && $project->status === 'pending' && $project->pm_user_id === auth()->id())
        @php
            $workingDays = max(1, (int) $project->starts_on->diffInWeekdays($project->ends_on) + 1);
            $calendarDays = $project->starts_on->diffInDays($project->ends_on) + 1;
        @endphp
        <section class="pm-assignment-review-banner approval-review" aria-labelledby="approval-review-title">
            <header><div><span class="approval-eyebrow">Project manager invitation</span><h2 id="approval-review-title">Review your project assignment</h2><p>Check the tasks, timeline and team below. {{ $project->approval_stage ? 'Accept to choose your plan, prepare tasks and assign the team.' : 'Accepting activates the project and captures its baseline.' }}</p></div><div class="approval-review-actions"><button type="button" class="btn btn-outline" data-show-dialog="decline-project-dialog">Decline</button><form method="post" action="{{ route('projects.respond',$project) }}" onsubmit="return AppDialog.confirmAction(event,this,'Accept this assignment and activate the project?');">@csrf
            <input type="hidden" name="decision" value="accept"><button type="submit" class="btn btn-primary">Accept project</button></form></div></header>
            <dl><div><dt>Company</dt><dd>{{ $project->subsidiary?->name ?? 'General Group' }}</dd></div><div><dt>Planned schedule</dt><dd>@if($project->approval_stage && !$project->plan_initialized)Set after acceptance@else{{ $project->starts_on->format('d M Y') }} &ndash; {{ $project->ends_on->format('d M Y') }}@endif</dd></div><div><dt>Prepared plan</dt><dd>{{ $project->template?->name ?? 'Blank project' }} <small>{{ $project->tasks()->whereDoesntHave('children')->count() }} executable tasks</small></dd></div><div><dt>Requested by</dt><dd>{{ $project->creator?->name ?? 'PMO' }}<small>{{ $project->created_at->format('d M Y') }}</small></dd></div></dl>
        </section>
    @elseif($project->status === 'declined' && $project->pm_user_id === auth()->id())
        <section class="action-banner" style="margin-bottom: 20px; background: #FEF2F2; border: 1px solid #FECACA; border-left: 5px solid #DC2626; border-radius: 8px; padding: 16px 20px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: #FEE2E2; color: #DC2626; border-radius: 50%; font-size: 14px; font-weight: bold;">✕</span>
                <div>
                    <strong style="color: #991B1B; font-size: 15px;">You Declined This Project Assignment</strong>
                    <p style="color: #7F1D1D; font-size: 13px; margin: 4px 0 0 0;">
                        You declined this assignment on {{ $declinedInvitation?->responded_at ? $declinedInvitation->responded_at->format('d M Y \a\t H:i') : 'recently' }}. PMO Administration will review your decline reason and reassign the project.
                    </p>
                    @if($declinedInvitation?->decline_reason)
                        <div style="margin-top: 8px; background: #FFFFFF; border: 1px solid #FCA5A5; border-radius: 6px; padding: 8px 12px; font-size: 13px; color: #374151;">
                            <strong>Your Reason:</strong> <em>"{{ $declinedInvitation->decline_reason }}"</em>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @elseif(!$project->approval_stage && $project->status === 'declined' && auth()->user()->isPmo())
        <section class="action-banner action-banner-declined" style="margin-bottom: 20px; background: #FEF2F2; border: 1px solid #FECACA; border-left: 5px solid #DC2626; border-radius: 8px; padding: 16px 20px;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 260px;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; background: #DC2626; color: white; border-radius: 50%; font-size: 14px; font-weight: bold;">✕</span>
                        <strong style="color: #991B1B; font-size: 15px;">Project Manager Assignment Declined</strong>
                    </div>
                    <p style="color: #7F1D1D; font-size: 13px; margin: 6px 0 10px 0;">
                        <strong>{{ $declinedInvitation?->user?->name ?? $project->manager?->name ?? 'Assigned PM' }}</strong> declined this assignment on <strong>{{ $declinedInvitation?->responded_at ? $declinedInvitation->responded_at->format('d M Y \a\t H:i') : 'recently' }}</strong>.
                    </p>
                    @if($declinedInvitation?->decline_reason)
                        <div style="background: white; border: 1px solid #FCA5A5; border-radius: 6px; padding: 10px 14px; color: #1F2937; font-size: 13px;">
                            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #991B1B; letter-spacing: 0.5px; margin-bottom: 2px;">Decline Reason:</div>
                            <div style="font-style: italic; color: #374151;">"{{ $declinedInvitation->decline_reason }}"</div>
                        </div>
                    @endif
                </div>
                <div style="display: flex; align-items: center; gap: 10px; margin-top: 4px;">
                    <button type="button" class="btn btn-primary" data-show-dialog="assign-pm-dialog" style="background: #315F58; border-color: #315F58; font-weight: 600; white-space: nowrap;">
                        <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" style="display: inline-block; vertical-align: -2px; margin-right: 4px;">
                            <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"/>
                        </svg>
                        Reassign Project Manager
                    </button>
                </div>
            </div>
        </section>
    @elseif(!$project->approval_stage && $project->status === 'pending' && auth()->user()->isPmo())
        <section class="action-banner action-banner-pending" style="margin-bottom: 20px; background: #FFFBEB; border: 1px solid #FDE68A; border-left: 5px solid #F59E0B; border-radius: 8px; padding: 14px 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; background: #F59E0B; color: white; border-radius: 50%; font-size: 13px; font-weight: bold;">⏳</span>
                        <strong style="color: #92400E; font-size: 14px;">Awaiting Project Manager Acceptance</strong>
                    </div>
                    <p style="color: #B45309; font-size: 13px; margin: 4px 0 0 0;">
                        Assignment invitation sent to <strong>{{ $project->manager?->name }}</strong> on {{ $pendingInvitation?->created_at ? $pendingInvitation->created_at->format('d M Y') : $project->created_at->format('d M Y') }}. Reassignment becomes available if the Project Manager rejects the request.
                    </p>
                </div>

            </div>
        </section>
    @elseif($project->status === 'draft' && auth()->user()->isPmo())
        <section class="action-banner action-banner-draft" style="margin-bottom: 20px; background: #F8FAFC; border: 1px solid #E2E8F0; border-left: 5px solid #315F58; border-radius: 8px; padding: 16px 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background: #315F58; color: white; border-radius: 50%; font-size: 13px; font-weight: bold;">📝</span>
                        <strong style="color: #1E293B; font-size: 14px;">Project is in Draft Mode (PMO Internal Only)</strong>
                    </div>
                    <p style="color: #475569; font-size: 13px; margin: 4px 0 0 0;">
                        This draft project is strictly visible to PMO Administrators only. {{ $project->manager ? 'The assigned Project Manager (' . $project->manager->name . ') and team members' : 'Team members' }} cannot see or access this project until it is submitted / created.
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <a href="{{ route('projects.create', ['draft' => $project->id]) }}" class="btn btn-primary" style="background: #315F58; border-color: #315F58; font-weight: 600; padding: 7px 16px;">
                        {{ $project->manager ? 'Create Project & Send Request →' : 'Continue Editing & Create →' }}
                    </a>
                </div>
            </div>
        </section>
    @endif

    <dl class="workspace-summary workspace-overview-details" aria-label="Project summary">
        <div class="workspace-manager">
            <dt>Project manager</dt>
            <dd>
                @if($project->manager)
                    <span class="workspace-manager-avatar" aria-hidden="true">{{ Str::upper(Str::substr($project->manager->name, 0, 1)) }}</span>
                    <span class="workspace-manager-name">{{ $project->manager->name }}</span>
                @else
                    <span class="workspace-manager-unassigned">Not assigned</span>
                @endif
            </dd>
        </div>
        <div class="workspace-schedule">
            <dt>Schedule</dt>
            <dd>
                @if($project->approval_stage && !$project->plan_initialized)<span>Set by PM after acceptance</span>@else<time datetime="{{ $project->starts_on->format('Y-m-d') }}" aria-label="Start date">{{ $project->starts_on->format('d M Y') }}</time>
                <span class="workspace-date-separator" aria-hidden="true">&rarr;</span>
                <time datetime="{{ $project->ends_on->format('Y-m-d') }}" aria-label="Due date">{{ $project->ends_on->format('d M Y') }}</time>@endif
            </dd>
        </div>
        <div class="workspace-summary-progress">
            <dt>Progress</dt>
            <dd>
                <strong data-project-progress-text>{{ (int)$project->progress }}%</strong>
                <span class="workspace-progress-track" data-project-progress-bar role="progressbar" aria-label="Project progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int)$project->progress }}">
                    <span style="width:{{ (int)$project->progress }}%;"></span>
                </span>
                <span data-project-ready class="project-ready-note" @if((int)$project->progress!==100) hidden @endif>All work complete. Ready for closure review.</span>
                <span class="workspace-completed-tasks" data-project-completed-count title="Completed tasks without subtasks / all tasks without subtasks">{{ $completedCount }} of {{ $totalCount }} tasks complete</span>
            </dd>
        </div>
    </dl>
    </section>
@php
        $overdueCount = $tasks->filter(fn($t) => $t->isOverdue())->count();
    @endphp

    <!-- Main Tabs Navigation (Role-Scoped) -->
    <nav class="project-tabs-nav" aria-label="Project workspace sections">
        <button type="button" class="project-tab-btn active" data-tab-target="tasks">
            {{ ($isScopedTeamMember ?? false) ? 'My Assigned Tasks' : 'Tasks' }}
            @if($overdueCount > 0)
                <span style="background: #DC2626; color: #FFFFFF; font-size: 10.5px; font-weight: 700; padding: 1px 7px; border-radius: 10px; margin-left: 6px;" title="{{ $overdueCount }} tasks overdue">{{ $overdueCount }} Overdue</span>
            @endif
        </button>
        @if($canViewAllTasks ?? true)
            <button type="button" class="project-tab-btn" data-tab-target="gantt">Timeline</button>
<button type="button" class="project-tab-btn" data-tab-target="team">Team ({{ $project->memberships->count() }})</button>
        @endif
        @if($canViewAllTasks ?? true)
            @php
                $totalActiveIssues = ($delays->where('status', 'open')->count()) + ($overdueCount ?? 0);
            @endphp
            <button type="button" class="project-tab-btn" data-tab-target="risks">
                Risks &amp; Issues
                @if($totalActiveIssues > 0)
                    <span class="workspace-tab-count" style="background: {{ $delays->where('status', 'open')->count() > 0 ? '#DC2626' : '#D97706' }}; color: #FFFFFF; font-weight: 700;">
                        {{ $totalActiveIssues }}
                    </span>
                @endif
            </button>
        @endif
        <button type="button" class="project-tab-btn" data-tab-target="info">Budget &amp; Closure</button>
    </nav>

    <!-- =========================================================================
         TAB 1: TASKS (Unified WBS Tree + Integrated Timeline Gantt)
         ========================================================================= -->
    <div class="pw-tab-panel active" id="tab-tasks">

        @if($isScopedTeamMember ?? false)
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 10px 16px; margin-bottom: 14px; font-size: 13px; color: var(--muted); display: flex; align-items: center; justify-content: space-between;">
                <span>Showing tasks explicitly assigned to you in this project.</span>
                <span class="status-pill pill-neutral">{{ $tasks->count() }} Assigned</span>
            </div>
        @endif

        <div class="workspace-task-toolbar">
            <h2 class="workspace-sr-only">Project Tasks</h2>
            <p class="workspace-sr-only">Manage tasks, assignments, schedules and progress.</p>
            <div class="workspace-sr-only">+ Add Task Edit Tasks Import / Export</div>
            @if($canViewTasks && $tasks->isNotEmpty())
                <div class="task-quick-filters" role="search" aria-label="Filter project tasks">
                    <label class="quick-search-label"><span class="workspace-sr-only">Find a task</span>
                        <input type="search" data-task-search placeholder="Find a task or assignee..." autocomplete="off">
                    </label>
                    <label><span class="workspace-sr-only">Task status</span>
                        <select data-task-filter>
                            <option value="all">All tasks</option>
                            <option value="overdue">Overdue</option>
                            <option value="open">Open</option>
                            <option value="completed">Completed</option>
                        </select>
                    </label>
                    <button type="button" class="btn btn-ghost btn-sm" data-clear-task-filters hidden>Clear</button>
                    <span class="workspace-result-count" data-task-result-count role="status" aria-live="polite"></span>
                </div>
            @endif
            @if($canManage)
                <div class="workspace-task-actions">
                    <button type="button" class="btn btn-primary" data-show-dialog="add-task-dialog" data-add-mode="main">+ Add task</button>
                    @if($tasks->isNotEmpty())
                        <button type="button" class="btn btn-ghost" data-show-dialog="add-task-dialog" data-add-mode="sub">+ Subtask</button>
                    @endif
                    <button type="button" class="btn btn-outline" data-show-dialog="bulk-edit-tasks-dialog" id="open-bulk-edit-btn">Bulk edit</button>
                    <div class="pw-dropdown-wrap">
                        <button type="button" class="btn btn-outline pw-dropdown-toggle workspace-import-toggle" aria-label="Import and export tasks" aria-expanded="false" aria-controls="project-import-menu">
                            Import / export <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg>
                        </button>
                        <div class="pw-dropdown-menu align-right workspace-import-menu" id="project-import-menu">
                            <button type="button" class="pw-dropdown-item" data-show-dialog="import-task-dialog">Import from Excel</button>
                            <a href="{{ $projectImportUrl }}" class="pw-dropdown-item">{{ $projectImportLabel }}</a>
                            <a href="{{ route('tasks.export', $project) }}" class="pw-dropdown-item">Export tasks to Excel</a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        @if(!$canViewTasks)
            <div class="empty-state-inline" style="background: #FFFFFF; border: 1px solid var(--border); border-radius: 8px;">
                @if($project->status === 'pending')
                    Tasks will become available to the team as soon as the Project Manager accepts this project.
                @else
                    You have permission to view the project summary. Detailed task access is restricted.
                @endif
            </div>
        @elseif($tasks->isEmpty())
            <div class="empty-state-inline" style="background: #FFFFFF; border: 1px solid var(--border); border-radius: 8px; padding: 48px 24px;">
                <b>No tasks created yet</b>
                <p class="muted">{{ $canManage ? 'Add your first task or import your plan using an Excel template.' : 'The project plan has no tasks yet. Your project manager or PMO can prepare the plan.' }}</p>
                @if($canManage)
                    <div class="inline-actions mt-16" style="justify-content: center; gap: 10px;">
                        <button class="btn btn-primary" type="button" data-show-dialog="add-task-dialog">+ Add Task</button>
                        <button class="btn btn-outline" type="button" data-show-dialog="import-task-dialog">Import Excel</button>
                    </div>
                @endif
            </div>
        @else
            <p class="empty-state" data-task-no-results hidden>No matching tasks. Change your search or clear the filters.</p>
            <!-- Project task list -->
            <div class="card" style="padding: 0; background: #FFFFFF; border: 1px solid var(--border, #D0D7DE); border-radius: 8px; overflow: hidden; margin-bottom: 24px;">
                <div class="table-wrap" style="overflow-x: auto;">
                    <table class="clean-table project-tasks-full-table" style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; font-size: 12px; font-weight: 700; color: #475569;">
                                <th style="padding: 12px 16px; width: 33%;">Task / Subtask</th>
                                <th style="padding: 12px 14px; width: 14%;">Assignee</th>
                                <th style="padding: 12px 10px; width: 10%;">Priority</th>
                                <th style="padding: 12px 14px; width: 11%;">Starts<span class="workspace-sr-only">Start Date &amp; Time</span></th>
                                <th style="padding: 12px 14px; width: 11%;">Due<span class="workspace-sr-only">End Date &amp; Time</span></th>
                                <th style="padding: 12px 14px; width: 9%;">Progress</th>
                                <th style="padding: 12px 16px; width: 12%;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tasks as $task)
                                @php
                                    $wbsNum = $numbers[$task->id] ?? $task->code;
                                    $isSubtask = !empty($task->parent_task_id);
                                    $hasChildren = $tasks->where('parent_task_id', $task->id)->count() > 0;
                                    $assignees = $task->assignees;
                                    $primaryAssignee = $assignees->first();
                                    $extraCount = max(0, $assignees->count() - 1);
                                    $isOverdue = $task->isOverdue();
                                    $isFocusedTask = (string)request('focus_task') === (string)$task->id;
                                    $canDeleteTask = app(\App\Services\Access::class)->project(auth()->user(),$project,'tasks.delete') && $project->status !== 'archived';
                                @endphp
                                <tr id="task-row-{{ $task->id }}"
                                    class="project-task-row {{ $isSubtask ? 'is-subtask' : 'is-main-task' }} {{ $isOverdue ? 'is-overdue-task' : '' }} {{ $isFocusedTask ? 'is-focused-task' : '' }}"
                                    data-task-id="{{ $task->id }}"
                                    data-parent="{{ $task->parent_task_id ?? '' }}"
                                    data-is-overdue="{{ $isOverdue ? '1' : '0' }}" data-task-status="{{ $task->status }}" data-task-search-text="{{ $task->title . ' ' . $task->priority . ' ' . $assignees->pluck('name')->join(' ') }}"
                                    style="border-bottom: 1px solid #F1F5F9; transition: background 0.25s, box-shadow 0.25s; {{ $isSubtask ? 'background: #F8FAFC;' : '' }} {{ $isOverdue ? 'background: #FFF8F8;' : '' }}">

                                    <!-- Column 1: Task / Subtask -->
                                    <td style="padding: {{ $isSubtask ? '10px 14px 10px 38px' : '12px 16px' }}; {{ $isSubtask ? 'border-left: 3px solid #CBD5E1;' : '' }}; vertical-align: middle;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            @if($isSubtask)
                                                <span style="color: #94A3B8; font-weight: 700; font-size: 13px; line-height: 1; flex-shrink: 0;" aria-hidden="true">↳</span>
                                                <span class="muted" style="font-size: 11.5px; font-weight: 600; min-width: 20px; flex-shrink: 0;">{{ $wbsNum }}</span>
                                            @else
                                                <span class="muted" style="font-size: 12px; font-weight: 700; min-width: 20px; flex-shrink: 0;">{{ $wbsNum }}</span>
                                            @endif

                                            @if($hasChildren)
                                                <button type="button" class="uwt-toggle-btn" data-parent-task="{{ $task->id }}" aria-expanded="true" title="Collapse / Expand subtasks" style="cursor: pointer; border: 1px solid #CBD5E1; background: #FFFFFF; color: #475569; font-size: 10px; width: 18px; height: 18px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; padding: 0; line-height: 1; flex-shrink: 0;">
                                                    ⌵
                                                </button>
                                            @endif

                                            <button type="button"
                                                    class="uwt-task-title-link"
                                                    data-task-location="{{ route('projects.show', [$task->project_id, 'focus_task'=>$task->id]) }}"
                                                    title="{{ $task->title }}"
                                                    style="font-size: {{ $isSubtask ? '13px' : '13.5px' }}; {{ $isSubtask ? 'font-weight: 500; color: #334155;' : 'font-weight: 700; color: #0F172A;' }} text-decoration: none; border: none; background: none; cursor: pointer; text-align: left; padding: 0; line-height: 1.4;">
                                                {{ $task->title }}
                                            </button>

                                            @if($isSubtask)
                                                <span style="font-size: 9.5px; background: #EEF2F6; color: #475569; border: 1px solid #CBD5E1; padding: 1px 6px; border-radius: 4px; font-weight: 600; flex-shrink: 0; white-space: nowrap;">Subtask</span>
                                            @elseif($hasChildren)
                                                <span class="pill pill-neutral" style="font-size: 10px; padding: 1px 6px; font-weight: 600; flex-shrink: 0; white-space: nowrap;" title="{{ $task->children->count() }} subtasks under this task">
                                                    {{ $task->children->count() }} subtasks
                                                </span>
                                            @endif

                                            @if($isOverdue)
                                                <span class="badge-overdue-tag" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; font-size: 10px; font-weight: 700; padding: 1.5px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 3px; flex-shrink: 0; white-space: nowrap;" title="Task deadline has passed">
                                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                    Overdue
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Column 2: Assignee -->
                                    <td style="padding: 12px 14px; font-size: 13px;">
                                        @if($primaryAssignee)
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="color: #22272B; font-weight: 500;">{{ $primaryAssignee->name }}</span>
                                                @if($extraCount > 0)
                                                    <span class="pill pill-neutral" title="{{ $assignees->pluck('name')->join(', ') }}" style="font-size: 10px; padding: 1px 5px; cursor: help;">+{{ $extraCount }}</span>
                                                @endif
                                            </div>
                                        @else
                                            @if(app(\App\Services\Access::class)->project(auth()->user(),$project,'assign_tasks') && $project->status!=='archived')
                                                <div class="task-quick-assign" data-assign-url="{{ route('tasks.assign',$task) }}"><button type="button" class="btn btn-outline btn-xs" data-assign-open>+ Assign</button><div data-assign-controls hidden><select aria-label="Assign a project member" data-assign-select @if($project->approval_stage==='planning') data-directory-picker data-directory-project="{{ $project->id }}" @endif><option value="">Choose a project member</option></select><button type="button" class="task-assign-cancel" data-assign-cancel aria-label="Cancel assignment">&times;</button></div><small data-assign-feedback role="status"></small></div>
                                            @else
                                                <span class="muted" style="font-size: 12.5px;">Unassigned</span>
                                            @endif
                                        @endif
                                    </td>

                                    <!-- Column 3: Priority -->
                                    <td style="padding: 12px 10px; font-size: 12px;">
                                        @php
                                            $canChangePriority = (
                                                auth()->user()->isPmo() ||
                                                (($project->status === 'active' || auth()->user()->isPmo()) && (
                                                    $project->pm_user_id === auth()->id() ||
                                                    $canManage ||
                                                    $assignees->contains('id', auth()->id())
                                                ))
                                            ) && $project->status !== 'archived';
                                            $prioConfig = match($task->priority) {
                                                'high' => ['border' => '#FECACA', 'accent' => '#DC2626', 'text' => '#B91C1C'],
                                                'low' => ['border' => '#E2E8F0', 'accent' => '#64748B', 'text' => '#475569'],
                                                default => ['border' => '#FDE68A', 'accent' => '#D97706', 'text' => '#B45309'],
                                            };
                                        @endphp
                                        @if($canChangePriority)
                                            <select class="quick-priority-select priority-select-{{ $task->priority }}"
                                                    data-task-id="{{ $task->id }}"
                                                    data-update-url="{{ route('tasks.update', $task) }}"
                                                    data-current-priority="{{ $task->priority }}"
                                                    aria-label="Change priority for {{ $task->title }}"
                                                    style="height: 28px; font-size: 11.5px; padding: 2px 6px 2px 8px; border-radius: 5px; font-weight: 600; cursor: pointer; background: #FFFFFF; border: 1px solid {{ $prioConfig['border'] }}; border-left: 3.5px solid {{ $prioConfig['accent'] }}; color: {{ $prioConfig['text'] }}; outline: none;">
                                                <option value="low" @selected($task->priority === 'low') style="background: #FFFFFF; color: #1E293B;">Low</option>
                                                <option value="medium" @selected($task->priority === 'medium') style="background: #FFFFFF; color: #1E293B;">Medium</option>
                                                <option value="high" @selected($task->priority === 'high') style="background: #FFFFFF; color: #1E293B;">High</option>
                                            </select>
                                        @else
                                            <span style="font-size: 11px; padding: 2px 8px; border-radius: 5px; font-weight: 600; display: inline-block; background: #FFFFFF; border: 1px solid {{ $prioConfig['border'] }}; border-left: 3.5px solid {{ $prioConfig['accent'] }}; color: {{ $prioConfig['text'] }};">
                                                {{ ucfirst($task->priority ?? 'medium') }}
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Column 4: Start Date & Time -->
                                    <td style="padding: 12px 14px; font-size: 12.5px;">
                                        <div style="color: #22272B; font-weight: 500;">{{ $task->starts_at->format('d M Y') }}</div>
                                        <div class="muted" style="font-size: 11px;">{{ $task->starts_at->format('h:i A') }}</div>
                                    </td>

                                    <!-- Column 5: End Date & Time -->
                                    <td style="padding: 12px 14px; font-size: 12.5px;" class="task-end-date-cell">
                                        @if($isOverdue)
                                            <div class="task-date-val" style="color: #DC2626; font-weight: 700;">{{ $task->ends_at->format('d M Y') }}</div>
                                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 3px;">
                                                <span class="task-time-val" style="font-size: 11px; color: #DC2626; font-weight: 500;">{{ $task->ends_at->format('h:i A') }}</span>
                                                <span class="badge-overdue" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 2px;">
                                                    Overdue
                                                </span>
                                            </div>
                                        @else
                                            <div class="task-date-val" style="color: #22272B; font-weight: 500;">{{ $task->ends_at->format('d M Y') }}</div>
                                            <div class="muted task-time-val" style="font-size: 11px;">{{ $task->ends_at->format('h:i A') }}</div>
                                        @endif
                                    </td>

                                    <!-- Column 6: Progress -->
                                    <td style="padding: 12px 14px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 50px; height: 5px; background: #E5E7EB; border-radius: 3px; overflow: hidden; flex-shrink: 0;">
                                                <div class="task-progress-fill" style="width: {{ $task->progress }}%; height: 100%; background: {{ $isOverdue ? '#DC2626' : '#315F58' }}; border-radius: 3px; transition: width 0.2s;"></div>
                                            </div>
                                            <span class="task-progress-text" style="font-size: 12px; font-weight: 600; color: {{ $isOverdue ? '#DC2626' : '#374151' }};">{{ $task->progress }}%</span>
                                        </div>
                                    </td>

                                    <!-- Column 7: Status & Actions -->
                                    <td style="padding: 12px 16px;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                            <div>
                                                @if(!$hasChildren && app(\App\Services\Access::class)->task(auth()->user(),$task,'update') && app(\App\Services\Access::class)->project(auth()->user(),$project,'tasks.update_status') && $project->status==='active')
                                                    <select class="quick-status-select"
                                                            data-task-id="{{ $task->id }}"
                                                            data-update-url="{{ route('tasks.update', $task) }}"
                                                            data-current-status="{{ $task->status }}"
                                                            style="{{ $isOverdue ? 'border-color: #FCA5A5; color: #DC2626; font-weight: 600;' : '' }}">
                                                        @foreach(['todo' => 'To Do', 'in_progress' => 'In Progress', 'on_hold' => 'On Hold', 'completed' => 'Completed'] as $val => $lbl)
                                                            <option value="{{ $val }}" @selected($task->status === $val)>{{ $lbl }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <div style="display: flex; align-items: center; gap: 5px;">
                                                        <span class="uwt-status-pill status-{{ $task->status }}" style="font-size: 11px;" @if($hasChildren) title="Parent task status is automatically calculated from its subtasks" @endif>
                                                            {{ ucwords(str_replace('_', ' ', $task->status)) }}
                                                        </span>
                                                        @if($hasChildren)
                                                            <span style="font-size: 9px; color: #94A3B8; font-weight: 600; cursor: help; background: #F1F5F9; border: 1px solid #E2E8F0; padding: 1px 4px; border-radius: 3px;" title="Parent task status is automatically calculated from its subtasks">Auto</span>
                                                        @endif
                                                        @if($isOverdue)
                                                            <span class="badge-overdue" style="font-size: 9.5px; padding: 1px 5px;">Overdue</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>

                                            @if($canDeleteTask)
                                                <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return AppDialog.confirmAction(event,this,'Are you sure you want to delete \'{{ addslashes($task->title) }}\'? @if($hasChildren)All its subtasks will also be deleted. @endif This action cannot be undone.');" style="margin: 0; line-height: 1;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-delete-task-row" title="Delete task" style="border: none; background: transparent; color: #94A3B8; cursor: pointer; padding: 3px 5px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.15s ease;" onmouseover="this.style.color='#DC2626'; this.style.background='#FEE2E2';" onmouseout="this.style.color='#94A3B8'; this.style.background='transparent';">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                            <polyline points="3 6 5 6 21 6"></polyline>
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <!-- =========================================================================
         TAB 2: GANTT (Dedicated Schedule Gantt with Critical Path Analysis)
         ========================================================================= -->
    <div class="pw-tab-panel" id="tab-gantt">
        <div class="card" style="padding: 20px; background: #FFFFFF; border: 1px solid var(--border); border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px;">Full Schedule Gantt</h2>
                    <p class="muted" style="font-size: 13px; margin: 0;">Comprehensive schedule with dependencies and Critical Path Analysis.</p>
                </div>
                <div class="inline-actions" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    @if($health && $health['cpm_reliable'])
                        <label class="quiet-check" style="font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" id="highlight-critical" @checked(in_array($health['health'], ['at_risk', 'overdue'], true))>
                            Highlight Critical Path
                        </label>
                    @endif

                    <!-- Scale Switcher Segmented Control -->
                    <div class="view-scale-segmented" role="group" aria-label="Timeline scale">
                        <button type="button" class="view-scale-btn" data-scale="day">Day</button>
                        <button type="button" class="view-scale-btn active" data-scale="week">Week</button>
                        <button type="button" class="view-scale-btn" data-scale="month">Month</button>
                    </div>
                </div>
            </div>

            @if($health && $health['cpm_warning'])
                <div class="schedule-hint" style="margin-bottom: 16px;">
                    <b>Critical Path:</b> {{ $health['cpm_warning'] }}
                </div>
            @endif

            @if($tasks->isEmpty())
                <p class="muted text-sm">No tasks created yet to display on the Gantt chart.</p>
            @else
                <!-- Interactive Gantt Timeline Grid in Tab 2 -->
                <div class="unified-workplan-card" style="border: 1px solid var(--border); border-radius: 6px; margin-top: 14px;">
                    <div class="unified-workplan-scroll" id="unified-workplan-scroll">
                        <div class="unified-workplan-table" id="unified-workplan-grid">
                            <!-- Header Row -->
                            <div class="uwt-header-row">
                                <div class="uwt-head-table">
                                    <div style="text-align: center;">#</div>
                                    <div>Task</div>
                                    <div>Status</div>
                                    <div>Progress</div>
                                    <div>Start</div>
                                    <div>End</div>
                                </div>
                                <div class="uwt-head-timeline">
                                    <div class="uwt-timeline-month-band" id="uwt-month-band"></div>
                                    <div class="uwt-timeline-sub-band" id="uwt-sub-band">
                                        <div class="uwt-today-badge" style="display: none;">Today</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Body Rows for Gantt -->
                            @foreach($tasks as $task)
                                @php
                                    $wbsNum = $numbers[$task->id] ?? $task->code;
                                    $isSubtask = !empty($task->parent_task_id);
                                    $isOverdue = $task->isOverdue();
                                @endphp
                                <div class="uwt-body-row {{ $isSubtask ? 'is-subtask' : '' }} {{ $isOverdue ? 'is-overdue-row' : '' }}" data-task-id="{{ $task->id }}" style="{{ $isSubtask ? 'background: #FAFCFF;' : '' }}">
                                    <div class="uwt-cell-table">
                                        <div class="uwt-wbs-num" style="text-align: center; {{ $isSubtask ? 'font-size: 11px; color: #64748B;' : 'font-weight: 700;' }}">{{ $wbsNum }}</div>
                                        <div class="uwt-task-title-cell" style="display: flex; align-items: center; gap: 4px; overflow: hidden; {{ $isSubtask ? 'padding-left: 18px;' : '' }}">
                                            @if($isSubtask)
                                                <span style="color: #94A3B8; font-size: 11px; margin-right: 2px;" aria-hidden="true">↳</span>
                                            @endif
                                            <button type="button" class="uwt-task-title-link" data-task-location="{{ route('projects.show', [$task->project_id, 'focus_task'=>$task->id]) }}" data-uwt-task-link="{{ $task->id }}" title="{{ $task->title }}" style="text-overflow: ellipsis; overflow: hidden; white-space: nowrap; {{ $isSubtask ? 'font-weight: 500; color: #334155;' : 'font-weight: 700; color: #0F172A;' }}">
                                                {{ $task->title }}
                                            </button>
                                            @if($isSubtask)
                                                <span style="font-size: 8.5px; background: #E2E8F0; color: #475569; border: 1px solid #CBD5E1; padding: 0.5px 4px; border-radius: 3px; font-weight: 600;">Sub</span>
                                            @endif
                                            @if($isOverdue)
                                                <span style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; font-size: 9px; font-weight: 700; padding: 1px 4px; border-radius: 3px; flex-shrink: 0;">Overdue</span>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="uwt-status-pill status-{{ $task->status }}">{{ ucwords(str_replace('_', ' ', $task->status)) }}</span>
                                        </div>
                                        <div style="font-size: 12px; font-weight: 600; color: {{ $isOverdue ? '#DC2626' : '#374151' }};">{{ $task->progress }}%</div>
                                        <div style="font-size: 11.5px; color: var(--muted);">{{ $task->starts_at->format('d M') }}</div>
                                        <div style="font-size: 11.5px; {{ $isOverdue ? 'color: #DC2626; font-weight: 700;' : 'color: var(--muted);' }}">
                                            {{ $task->ends_at->format('d M') }}
                                            @if($isOverdue)
                                                <span style="color: #DC2626; font-size: 9px; font-weight: 700; display: block;">(Overdue)</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="uwt-cell-timeline" data-uwt-track="{{ $task->id }}">
                                        <div class="uwt-today-line" style="display: none;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- =========================================================================
         TAB 3: TEAM & ROLES
         ========================================================================= -->
    <div class="pw-tab-panel project-team-workspace" id="tab-team">
        <div class="card" style="padding: 20px; background: #FFFFFF;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px;">Project team</h2>
                    <p class="muted" style="font-size: 13px; margin: 0;">People and roles assigned to this project.</p>
                </div>
                @if($canTeam && $project->status !== 'archived')
                    <button class="btn btn-primary btn-sm" type="button" onclick="document.getElementById('add-member-form-box').style.display = document.getElementById('add-member-form-box').style.display === 'none' ? 'block' : 'none'">
                        + Add Member
                    </button>
                @endif
            </div>

            <!-- Inline Add Member Form (Hidden by default, 0 clicks to expand) -->
            <div id="add-member-form-box" style="display: none; background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 16px; margin-bottom: 20px;">
                <form method="post" action="{{ route('projects.members.store', $project) }}" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                    @csrf
                    <label style="flex: 1; min-width: 220px; margin: 0; font-size: 12px; font-weight: 600;">Person
                        <select data-directory-picker data-directory-project="{{ $project->id }}" name="user_id" required style="height: 38px; font-size: 13px; background: #FFFFFF; margin-top: 4px;">
                            <option value="">Search for a person...</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="min-width: 180px; margin: 0; font-size: 12px; font-weight: 600;">Role
                        <select name="project_role_id" required style="height: 38px; font-size: 13px; background: #FFFFFF; margin-top: 4px;">
                            <option value="">Select role...</option>
                            @foreach($roles->where('slug', '!=', 'pm') as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn btn-primary" style="height: 38px; font-size: 13px;">Add to Team</button>
                    <button type="button" class="btn btn-ghost" style="height: 38px;" onclick="document.getElementById('add-member-form-box').style.display='none'">Cancel</button>
                </form>
            </div>

            <!-- Team Members Table -->
            <div class="table-wrap">
                <table class="clean-table">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Work Email</th>
                            <th>Project Role</th>
                            <th>Assigned Tasks</th>
                            @if($canTeam) <th class="right">Action</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Project Manager Row -->
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="avatar avatar-sm">{{ strtoupper(substr($project->manager?->name ?? 'P', 0, 1)) }}</span>
                                    <b>{{ $project->manager?->name ?? 'Not assigned' }}</b>
                                </div>
                            </td>
                            <td><span class="muted text-sm">{{ $project->manager?->email ?? '—' }}</span></td>
                            <td><span class="pill" style="background: #E8F0EE; color: var(--primary); font-weight: 700;">Project Manager</span></td>
                            <td>{{ $tasks->filter(fn($t) => $t->assignees->contains('id', $project->pm_user_id))->count() }}</td>
                            @if($canTeam)
                                <td class="right">
                                    @if(!$project->approval_stage && auth()->user()->isPmo() && ($project->status==='declined' || ($project->status==='draft' && !$project->pm_user_id)))
                                        <button type="button" class="btn btn-outline btn-sm" data-show-dialog="assign-pm-dialog">Change PM</button>
                                    @else
                                        <span class="muted text-sm">Lead</span>
                                    @endif
                                </td>
                            @endif
                        </tr>

                        <!-- Other Members -->
                        @forelse($project->memberships->where('user_id', '!=', $project->pm_user_id) as $m)
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span class="avatar avatar-sm">{{ strtoupper(substr($m->user?->name ?? 'U', 0, 1)) }}</span>
                                        <b>{{ $m->user?->name ?? 'Unknown' }}</b>
                                    </div>
                                </td>
                                <td><span class="muted text-sm">{{ $m->user?->email ?? '—' }}</span></td>
                                <td><span class="pill todo">{{ $m->role?->name ?? 'Team Member' }}</span></td>
                                <td>{{ $tasks->filter(fn($t) => $t->assignees->contains('id', $m->user_id))->count() }}</td>
                                @if($canTeam)
                                    <td class="right">
                                        @if($project->approval_stage && (int)$project->owner_user_id===(int)$m->user_id)<span class="muted text-sm">Project Owner</span>@else<form method="post" action="{{ route('projects.members.delete', ['project' => $project, 'user' => $m->user_id]) }}" onsubmit="return AppDialog.confirmAction(event,this,'Remove this member from project?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger);">Remove</button>
                                        </form>@endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            @if(!$project->pm_user_id)
                                <tr>
                                    <td colspan="5" class="empty">No team members assigned yet.</td>
                                </tr>
                            @endif
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 4: FILES REPOSITORY
         ========================================================================= -->
    <div class="pw-tab-panel" id="tab-files">
        <div class="card" style="padding: 20px; background: #FFFFFF;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px;">Project Files & Attachments</h2>
                    <p class="muted" style="font-size: 13px; margin: 0;">Documents, deliverables, specifications, and task uploads.</p>
                </div>
            </div>

            @if($files->isEmpty())
                <div class="empty-state-inline">
                    <b>No files attached yet</b>
                    <p class="muted">Upload deliverables or documents directly from any task detail panel.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Related Task</th>
                                <th>Uploaded Date</th>
                                <th class="right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($files as $file)
                                <tr>
                                    <td><b>{{ $file->original_name }}</b></td>
                                    <td>
                                        <button type="button" class="text-action" data-task-location="{{ route('projects.show', [$file->task->project_id, 'focus_task'=>$file->task->id]) }}">
                                            {{ $file->task?->title ?? 'Task' }}
                                        </button>
                                    </td>
                                    <td><span class="muted text-sm">{{ $file->created_at->format('d M Y, H:i') }}</span></td>
                                    <td class="right">
                                        <a href="{{ route('attachments.download', $file) }}" class="btn btn-outline btn-sm">Download</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- =========================================================================
         TAB 5: MILESTONES
         ========================================================================= -->


    <!-- =========================================================================
         TAB 4: RISKS, BLOCKERS & SCHEDULE DELAYS
         ========================================================================= -->
    <div class="pw-tab-panel" id="tab-risks">
        @php
            $overdueList = in_array($project->status,['active','on_hold'],true) ? $tasks->filter(fn($t) => $t->isOverdue()) : collect();
            $openDelaysCount = $delays->where('status', 'open')->count();
            $criticalOverdueCount = $overdueList->filter(fn($t) => in_array($t->id, $health['critical_ids'] ?? [], true))->count();
        @endphp

        <!-- 1. Top Risk & Health Summary Cards -->
        <div class="project-risk-summary" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px;">
            <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; padding: 14px 18px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B;">Reported Blockers</span>
                <div style="font-size: 22px; font-weight: 700; color: {{ $openDelaysCount > 0 ? '#DC2626' : '#107C41' }}; margin: 4px 0 2px;">
                    {{ $openDelaysCount }}
                </div>
                <small style="color: #64748B; font-size: 12px;">{{ $openDelaysCount > 0 ? 'Active impediments' : 'No active blockers' }}</small>
            </div>

            <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; padding: 14px 18px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B;">Overdue Tasks</span>
                <div style="font-size: 22px; font-weight: 700; color: {{ $overdueList->count() > 0 ? '#DC2626' : '#107C41' }}; margin: 4px 0 2px;">
                    {{ $overdueList->count() }}
                </div>
                <small style="color: #64748B; font-size: 12px;">
                    @if($criticalOverdueCount > 0)
                        <span style="color: #DC2626; font-weight: 600;">{{ $criticalOverdueCount }} on Critical Path</span>
                    @else
                        {{ $overdueList->count() > 0 ? 'Exceeded scheduled deadline' : 'All tasks on schedule' }}
                    @endif
                </small>
            </div>

            <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; padding: 14px 18px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B;">Baseline Variance</span>
                <div style="margin: 6px 0 2px;">
                    @if($health && $health['baseline_available'])
                        @php $bDiff = $project->ends_on->diffInDays($project->baseline_ends_on, false); @endphp
                        @if($bDiff < 0)
                            <span class="status-pill pill-danger" style="font-size: 13px; font-weight: 700;">{{ abs($bDiff) }} Days Delayed</span>
                        @else
                            <span class="status-pill pill-success" style="font-size: 13px; font-weight: 700;">On Baseline</span>
                        @endif
                    @else
                        <span class="status-pill pill-neutral" style="font-size: 12px;">No Baseline</span>
                    @endif
                </div>
                <small style="color: #64748B; font-size: 12px;">Target: {{ $project->ends_on->format('d M Y') }}</small>
            </div>

            <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; padding: 14px 18px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B;">Schedule Health</span>
                <div style="margin: 6px 0 2px;">
                    @if(in_array($project->status,['draft','pending','declined'],true))
                        <span class="status-pill pill-neutral">Not started</span>
                    @elseif($overdueList->count() > 0 || $openDelaysCount > 0)
                        <span class="status-pill pill-warning" style="font-size: 13px; font-weight: 700;">At Risk</span>
                    @elseif($project->ends_on->lessThan(now()) && $project->status !== 'completed')
                        <span class="status-pill pill-danger" style="font-size: 13px; font-weight: 700;">Delayed</span>
                    @else
                        <span class="status-pill pill-success" style="font-size: 13px; font-weight: 700;">On Track</span>
                    @endif
                </div>
                <small style="color: #64748B; font-size: 12px;">{{ (int)$project->progress }}% completed</small>
            </div>
        </div>

        <!-- 2. CARD A: Schedule Bottlenecks & Overdue Tasks -->
        <div class="card" style="padding: 20px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px; color: #22272B;">Overdue tasks</h2>
                    <p class="muted" style="font-size: 13px; margin: 0;">Review overdue work and its impact on the schedule.</p>
                </div>
                @if($overdueList->isNotEmpty())
                    <span class="status-pill pill-danger" style="font-size: 12px; font-weight: 700;">
                        {{ $overdueList->count() }} Overdue {{ $overdueList->count() === 1 ? 'Task' : 'Tasks' }}
                    </span>
                @endif
            </div>

            @if($overdueList->isEmpty())
                <div style="background: #F0FDF4; border: 1px solid #DCFCE7; border-radius: 6px; padding: 18px; text-align: center; color: #166534;">
                    <div style="font-size: 14px; font-weight: 700; margin-bottom: 2px;">✓ All tasks are on schedule</div>
                    <p style="font-size: 12.5px; margin: 0; color: #15803D;">No overdue tasks detected in this project. Work is progressing within planned deadlines.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="clean-table" style="width: 100%; font-size: 13px;">
                        <thead>
                            <tr style="background: #F8FAFC;">
                                <th style="padding: 10px 14px;">Task</th>
                                <th style="padding: 10px 14px;">Assignee</th>
                                <th style="padding: 10px 14px;">Deadline</th>
                                <th style="padding: 10px 14px; text-align: center;">Delay</th>
                                <th style="padding: 10px 14px; text-align: center;">Progress</th>
                                <th style="padding: 10px 14px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($overdueList as $ot)
                                @php
                                    $daysLate = max(1, (int) $ot->ends_at->diffInDays(now()));
                                    $isCrit = in_array($ot->id, $health['critical_ids'] ?? [], true);
                                    $firstAssignee = $ot->assignees->first();
                                @endphp
                                <tr>
                                    <td style="padding: 10px 14px;">
                                        <button type="button" class="text-action" data-task-location="{{ route('projects.show', [$ot->project_id, 'focus_task'=>$ot->id]) }}" style="text-align: left; background: none; border: none; padding: 0; font-size: 13px; font-weight: 650; color: #22272B; cursor: pointer;">
                                            <span style="color: #64748B;">{{ $ot->code }}</span> {{ $ot->title }}
                                        </button>
                                        @if($isCrit)
                                            <span class="status-pill pill-danger" style="font-size: 10px; margin-left: 6px; padding: 1px 6px;">Critical Path</span>
                                        @endif
                                        @if($ot->openDelay)
                                            <span class="status-pill pill-warning" style="font-size: 10px; margin-left: 4px; padding: 1px 6px;">Blocker Reported</span>
                                        @endif
                                    </td>
                                    <td style="padding: 10px 14px; color: #475569;">
                                        @if($firstAssignee)
                                            <span style="font-weight: 600;">{{ $firstAssignee->name }}</span>
                                            @if($ot->assignees->count() > 1)
                                                <small class="muted">+{{ $ot->assignees->count() - 1 }}</small>
                                            @endif
                                        @else
                                            <span style="color: #94A3B8; font-style: italic;">Unassigned</span>
                                        @endif
                                    </td>
                                    <td style="padding: 10px 14px; color: #DC2626; font-weight: 600;">
                                        {{ $ot->ends_at->format('d M Y') }}
                                    </td>
                                    <td style="padding: 10px 14px; text-align: center;">
                                        <span class="status-pill pill-danger" style="font-size: 11px; padding: 2px 8px;">
                                            {{ $daysLate }}d overdue
                                        </span>
                                    </td>
                                    <td style="padding: 10px 14px; text-align: center;">
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                            <span style="font-weight: 700; font-size: 12px; width: 32px;">{{ $ot->progress }}%</span>
                                            <div style="width: 70px; height: 6px; background: #E2E8F0; border-radius: 3px; overflow: hidden;">
                                                <div style="width: {{ $ot->progress }}%; height: 100%; background: #315F58;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 10px 14px; text-align: right; white-space: nowrap;">
                                        <button type="button" class="btn btn-outline btn-xs" data-task-location="{{ route('projects.show', [$ot->project_id, 'focus_task'=>$ot->id]) }}" style="padding: 3px 10px; font-size: 12px;">
                                            Open Task →
                                        </button>
                                        @if($canManage && !$ot->openDelay)
                                            <button type="button" class="btn btn-primary btn-xs" onclick="openReportDelayModal({{ $ot->id }})" style="padding: 3px 10px; font-size: 12px; background: #DC2626; border-color: #DC2626; margin-left: 4px;">
                                                Flag Blocker
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- 3. CARD B: Reported Blockers & Impediments Registry -->
        <div class="card" style="padding: 20px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px; color: #22272B;">Blockers & issues</h2>
                    <p class="muted" style="font-size: 13px; margin: 0;">Track the issue, its owner and the next action.</p>
                </div>
                @if($canManage)
                    <button type="button" class="btn btn-primary btn-sm" onclick="openReportDelayModal()" style="background: #315F58; color: #FFFFFF; font-weight: 600; font-size: 12.5px;">
                        + Report Blocker / Delay
                    </button>
                @endif
            </div>

            @if($delays->isEmpty())
                <div style="background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 6px; padding: 28px 20px; text-align: center;">
                    <div style="font-size: 14px; font-weight: 650; color: #334155; margin-bottom: 4px;">No formal blockers reported</div>
                    <p class="muted" style="font-size: 12.5px; margin: 0 0 14px; max-width: 500px; margin-inline: auto;">
                        If any task is being delayed by vendors, external parties, pending approvals, or technical blockers, log it to track the responsible owner and impact.
                    </p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="clean-table" style="width: 100%; font-size: 13px;">
                        <thead>
                            <tr style="background: #F8FAFC;">
                                <th style="padding: 10px 14px;">Task</th>
                                <th style="padding: 10px 14px;">Cause / Reason</th>
                                <th style="padding: 10px 14px;">Involved Party</th>
                                <th style="padding: 10px 14px;">Action Owner</th>
                                <th style="padding: 10px 14px;">Reported</th>
                                <th style="padding: 10px 14px; text-align: center;">Status</th>
                                <th style="padding: 10px 14px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($delays as $delay)
                                <tr id="reported-issue-{{ $delay->id }}" @if((string)request('focus_issue')===(string)$delay->id) class="focused-reported-issue" @endif>
                                    <td style="padding: 10px 14px;">
                                        <button type="button" class="text-action" data-task-location="{{ route('projects.show', [$delay->task->project_id, 'focus_task'=>$delay->task->id]) }}" style="text-align: left; background: none; border: none; padding: 0; font-size: 13px; font-weight: 650; color: #22272B; cursor: pointer;">
                                            <span style="color: #64748B;">{{ $delay->task?->code }}</span> {{ $delay->task?->title ?? 'Task' }}
                                        </button>
                                    </td>
                                    <td style="padding: 10px 14px;">
                                        <b style="color: #22272B;">{{ $delay->reason_label }}</b>
                                        @if($delay->details)
                                            <small class="block muted" style="font-size: 12px; margin-top: 2px; max-width: 300px;">{{ $delay->details }}</small>
                                        @endif
                                    </td>
                                    <td style="padding: 10px 14px;">
                                        <span class="status-pill pill-neutral" style="font-size: 11px;">
                                            {{ $delay->responsible_party_name ?: ucwords($delay->responsible_party_type) }}
                                        </span>
                                    </td>
                                    <td style="padding: 10px 14px;">
                                        @if($delay->actionOwner)
                                            <span style="font-weight: 600; color: #334155;">{{ $delay->actionOwner->name }}</span>
                                        @else
                                            <span style="color: #94A3B8; font-style: italic;">Unassigned</span>
                                        @endif
                                    </td>
                                    <td style="padding: 10px 14px; color: #64748B; font-size: 12px;">
                                        {{ $delay->created_at->format('d M Y') }}
                                    </td>
                                    <td style="padding: 10px 14px; text-align: center;">
                                        <span class="status-pill {{ $delay->status === 'open' ? 'pill-warning' : 'pill-success' }}" style="font-size: 11.5px; padding: 2px 8px;">
                                            {{ ucfirst($delay->status) }}
                                        </span>
                                    </td>
                                    <td style="padding: 10px 14px; text-align: right; white-space: nowrap;">
                                        @if($delay->status === 'open' && $canManage)
                                            <form method="post" action="{{ route('tasks.delays.resolve', $delay) }}" style="display: inline-block;">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-outline btn-xs" style="color: #107C41; border-color: #107C41; font-weight: 600;" onclick="return AppDialog.confirmAction(event,this,'Mark this blocker as resolved?')">
                                                    ✓ Resolve
                                                </button>
                                            </form>
                                        @endif
                                        <button type="button" class="btn btn-outline btn-xs" data-task-location="{{ route('projects.show', [$delay->task->project_id, 'focus_task'=>$delay->task->id]) }}" style="margin-left: 4px;">
                                            Details
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- 4. CARD C: Baseline Variance & Audit Trail -->
        <section class="workspace-subsection" id="project-changes">
            <div class="card" style="padding: 20px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px;">
                <div style="margin-bottom: 16px;">
                    <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px; color: #22272B;">Schedule changes</h2>
                    <p class="muted" style="font-size: 13px; margin: 0;">Compares current projected schedule against approved baseline milestone dates.</p>
                </div>

                @if($health && $health['baseline_available'])
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 16px 20px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <b style="font-size: 14px; color: #22272B;">Schedule Baseline Active</b>
                                    <span class="status-pill pill-success" style="font-size: 11px;">Locked</span>
                                </div>
                                <p class="muted" style="margin: 4px 0 0; font-size: 12.5px;">
                                    Approved and captured on <b>{{ $project->baseline_captured_at?->format('d M Y') }}</b>.
                                    Original target deadline: <b>{{ $project->baseline_ends_on?->format('d M Y') }}</b> · Current target: <b>{{ $project->ends_on->format('d M Y') }}</b>
                                </p>
                            </div>
                            <div>
                                @php $diff = $project->ends_on->diffInDays($project->baseline_ends_on, false); @endphp
                                @if($diff < 0)
                                    <span class="status-pill pill-danger" style="font-size: 13px; font-weight: 700; padding: 4px 12px;">
                                        {{ abs($diff) }} Days Delayed vs Baseline
                                    </span>
                                @else
                                    <span class="status-pill pill-success" style="font-size: 13px; font-weight: 700; padding: 4px 12px;">
                                        On Approved Baseline
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 16px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <b style="font-size: 14px; color: #22272B;">No Schedule Baseline Captured</b>
                            <p class="muted" style="margin: 3px 0 0; font-size: 12.5px;">Capture an approved schedule baseline to lock milestone dates and measure future delay variance.</p>
                        </div>
                        @if($canTeam && in_array($project->status, ['active', 'on_hold'], true))
                            <form method="post" action="{{ route('projects.baseline', $project) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm" onclick="return AppDialog.confirmAction(event,this,'Capture current schedule as the approved baseline?')">
                                    Capture Schedule Baseline
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                <h3 style="font-size: 14px; font-weight: 700; margin: 20px 0 10px; color: #22272B;">Recent Schedule &amp; Task Activity</h3>
                @if($activities->isEmpty())
                    <p class="muted text-sm">No activity records logged yet.</p>
                @else
                    <div class="table-wrap">
                        <table class="clean-table" style="width: 100%; font-size: 12.5px;">
                            <thead>
                                <tr style="background: #F8FAFC;">
                                    <th style="padding: 8px 12px;">Action</th>
                                    <th style="padding: 8px 12px;">User</th>
                                    <th style="padding: 8px 12px; text-align: right;">Date &amp; Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activities as $act)
                                    <tr>
                                        <td style="padding: 8px 12px;"><b style="color: #334155;">{{ ucwords(str_replace('.', ' ', $act->action)) }}</b></td>
                                        <td style="padding: 8px 12px; color: #475569;">{{ $act->actor?->name ?? 'System' }}</td>
                                        <td style="padding: 8px 12px; text-align: right;"><span class="muted text-sm">{{ $act->created_at->format('d M Y, H:i') }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <!-- =========================================================================
         TAB 7: CHANGES & BASELINE AUDIT
         ========================================================================= -->


    <!-- =========================================================================
         TAB 8: PROJECT INFO & CLOSURE
         ========================================================================= -->
    <div class="pw-tab-panel project-overview" id="tab-info">
        <!-- Top 2-Column Responsive Overview Grid -->
        <div class="overview-grid">
            @include('projects._budget')

            <!-- Right Card: Project Closure & Governance (Moved from 3-dots) -->
            <section class="overview-card overview-closure-card" aria-labelledby="overview-closure-title">
                <div class="overview-card-head">
                    <div>
                        
                        <h2 id="overview-closure-title">Closure &amp; Sign-off</h2>
                    </div>
                    <span class="project-status-pill status-{{ $project->status }}">
                        <span class="status-dot"></span>
                        <span>{{ ucwords(str_replace('_', ' ', $project->status)) }}</span>
                    </span>
                </div>

                <div class="overview-closure-body">
                    <!-- Progress & Tally -->
                    <div class="overview-metric-block">
                        <div class="overview-progress-gauge">
                            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px;">
                                <span style="font-size: 12px; font-weight: 600; color: #475569;">Overall Completion</span>
                                <strong style="font-size: 16px; font-weight: 700; color: #22272B;">{{ (int)$project->progress }}%</strong>
                            </div>
                            <div class="overview-progress-bar-wrap">
                                <div class="overview-progress-bar-fill" style="width: {{ (int)$project->progress }}%;"></div>
                            </div>
                        </div>
                        <div class="overview-task-tally">
                            <span class="tally-count">{{ $completedCount }} / {{ $totalCount }}</span>
                            <span class="tally-label">tasks completed</span>
                        </div>
                    </div>

                    <!-- Closure Action CTA -->
                    <div class="overview-closure-action-box">
                        @if($project->status === 'completed')
                            <div class="overview-closure-status is-completed">
                                <span class="status-check-badge">✓</span>
                                <div>
                                    <b style="color: #065F46; font-size: 13.5px;">Project Closed &amp; Signed Off</b>
                                    <p class="muted" style="margin: 2px 0 0; font-size: 12px;">All project deliverables and approvals have been formally recorded.</p>
                                </div>
                            </div>
                        @elseif($canTeam && in_array($project->status, ['active', 'on_hold'], true))
                            <div class="overview-closure-status">
                                <p class="muted" style="font-size: 12.5px; margin: 0 0 12px; line-height: 1.5;">
                                    Complete deliverables, verify sign-offs, and submit for formal stakeholder closure.
                                </p>
                                <button type="button" class="btn btn-primary" data-show-dialog="project-closure-dialog" style="width: 100%; justify-content: center; height: 38px; font-weight: 600; font-size: 13px;">
                                    Review Closure &amp; Sign-off &rarr;
                                </button>
                            </div>
                        @else
                            <p class="muted" style="font-size: 12px; margin: 0;">Project sign-off is available once the project is under active delivery.</p>
                        @endif
                    </div>

                    <!-- Tools & Governance Strip -->
                    @if($canManage || auth()->user()->isPmo())
                        <div class="overview-tools-strip">
                            <span class="tools-label">Governance Tools</span>
                            <div class="tools-buttons">
                                @if($canManage)
                                    <a class="btn btn-outline btn-xs" href="{{ route('tasks.export', $project) }}">Export Tasks</a>
                                    <a class="btn btn-outline btn-xs" href="{{ $projectImportUrl }}">{{ $projectImportLabel }}</a>
                                @endif
                                @if($health && !$health['baseline_available'] && $canTeam && in_array($project->status, ['active', 'on_hold'], true))
                                    <form method="post" action="{{ route('projects.baseline', $project) }}" style="display: inline;">
                                        @csrf
                                        <button class="btn btn-outline btn-xs" type="submit" onclick="return AppDialog.confirmAction(event,this,'Capture schedule baseline?')">Capture Baseline</button>
                                    </form>
                                @endif
                                @if(auth()->user()->isPmo())
                                    <button type="button" class="btn btn-ghost btn-xs overview-delete text-danger" data-delete-project-url="{{ route('projects.destroy', $project) }}" data-delete-project-name="{{ $project->name }}" data-delete-project-code="{{ $project->code }}">Delete Project</button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <!-- Milestones & Deliverables Card Below -->
        <section class="overview-card overview-milestones-card">
            <div class="overview-card-head">
                <div>
                    
                    <h2>Key checkpoints</h2>
                </div>
                <p class="muted" style="font-size: 12.5px; margin: 0;">Major schedule checkpoints and deliverable completion.</p>
            </div>

            @php
                $milestoneTasks = $tasks->filter(fn($t) => $t->children()->count() > 0 || str_contains(strtolower($t->title), 'milestone') || str_contains(strtolower($t->title), 'approval') || str_contains(strtolower($t->title), 'sign-off') || str_contains(strtolower($t->title), 'go-live'));
            @endphp

            @if($milestoneTasks->isEmpty())
                <div style="padding: 24px 16px; text-align: center; color: #64748B;">
                    <p class="muted" style="margin: 0; font-size: 13px;">All project activities are tracked in the <b>Tasks</b> view.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="clean-table" style="width: 100%;">
                        <thead>
                            <tr style="background: #F8FAFC;">
                                <th style="padding: 10px 14px; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Phase / Milestone</th>
                                <th style="padding: 10px 14px; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Target Date</th>
                                <th style="padding: 10px 14px; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Progress</th>
                                <th style="padding: 10px 14px; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Status</th>
                                <th style="padding: 10px 14px; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($milestoneTasks as $mt)
                                @php $mtOverdue = $mt->isOverdue(); @endphp
                                <tr style="{{ $mtOverdue ? 'background: #FFF8F8;' : '' }}; border-bottom: 1px solid #F1F5F9;">
                                    <td style="padding: 12px 14px;">
                                        <b style="color: #22272B; font-size: 13px;">{{ $mt->title }}</b>
                                        @if($mtOverdue)
                                            <span class="badge-overdue" style="font-size: 10px; margin-left: 6px;">Overdue</span>
                                        @endif
                                    </td>
                                    <td style="padding: 12px 14px; {{ $mtOverdue ? 'color: #DC2626; font-weight: 700;' : 'color: #475569;' }}; font-size: 12.5px;">
                                        {{ $mt->ends_at->format('d M Y') }}
                                    </td>
                                    <td style="padding: 12px 14px; {{ $mtOverdue ? 'color: #DC2626; font-weight: 600;' : 'color: #475569;' }}; font-size: 12px;">{{ $mt->progress }}%</td>
                                    <td style="padding: 12px 14px;"><span class="uwt-status-pill status-{{ $mt->status }}" style="font-size: 11px;">{{ ucwords(str_replace('_',' ',$mt->status)) }}</span></td>
                                    <td style="padding: 12px 14px; text-align: right;">
                                        <button type="button" class="btn btn-outline btn-xs" data-task-location="{{ route('projects.show', [$mt->project_id, 'focus_task'=>$mt->id]) }}" style="font-size: 11px; height: 26px; padding: 0 8px;">View</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

</div>

<!-- =========================================================================
     SLIDE-OUT TASK DETAILS SIDE PANEL (Accessible Drawer, 0 Page Reload)
     ========================================================================= -->


<!-- =========================================================================
     COMPACT INLINE DIALOGS (Add Task, Import Tasks, Edit Project, Assign PM)
     ========================================================================= -->
@if($canManage)
    <!-- Add Task Dialog -->
    <dialog id="add-task-dialog" class="modal" style="max-width: 560px;">
        <div class="modal-head">
            <h2>Add Task</h2>
            <button type="button" class="icon-btn" data-close-dialog aria-label="Close">×</button>
        </div>
        <form method="post" action="{{ route('tasks.store', $project) }}" enctype="multipart/form-data" class="stack mt-16">
            @csrf
            <label>Task Name <span class="required">*</span>
                <input name="title" required maxlength="200" placeholder="e.g. Requirement Gathering">
            </label>

            <details class="task-plan-options"><summary>Task structure</summary>
            <label>Main Task or Subtask
                <select name="parent_task_id" id="add-task-parent-select">
                    <option value="">Main Task (Top Level)</option>
                    @foreach($tasks as $t)
                        <option value="{{ $t->id }}">Under {{ $numbers[$t->id] ?? $t->code }} {{ $t->title }}</option>
                    @endforeach
                </select>
            </label>
            </details>

            <div class="two-fields">
                <label>Start Date & Time <span class="required">*</span>
                    <input type="datetime-local" name="starts_at" value="{{ $project->starts_on->format('Y-m-d') }}T09:00" required>
                </label>
                <label>End Date & Time <span class="required">*</span>
                    <input type="datetime-local" name="ends_at" value="{{ $project->ends_on->format('Y-m-d') }}T17:00" required>
                </label>
            </div>

            <div class="two-fields">
                <label>Assign To
                    <select name="assigned_user_id" id="add-task-assignee-select" @if($project->approval_stage==='planning') data-directory-picker data-directory-project="{{ $project->id }}" @endif>
                        <option value="">Unassigned (Assign later...)</option>
                        @if($project->approval_stage==='planning')
                            @foreach($users as $person)<option value="{{ $person->id }}">{{ $person->name }} ({{ $person->email }})</option>@endforeach
                        @else
                        @foreach($project->memberships->unique('user_id') as $m)
                            <option value="{{ $m->user_id }}">{{ $m->user?->name }} ({{ $m->role?->name ?? 'Member' }})</option>
                        @endforeach
                        @endif
                    </select>
                    @if($canTeam)
                        <button type="button" class="text-action" id="add-task-open-member-btn" style="font-size: 11px; margin-top: 4px; display: inline-block; padding: 0; background: none; border: none; cursor: pointer; color: #315F58; text-decoration: underline;">
                            + Add Project Member
                        </button>
                    @endif
                </label>
                <label>Priority
                    <select name="priority">
                        <option value="medium">Normal</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                    </select>
                </label>
            </div>

            <details class="task-extra-options"><summary>Add description</summary>
            <label>Description (Optional)
                <textarea name="description" rows="2" placeholder="Task deliverables or scope..."></textarea>
            </label>
            </details>

            <div class="form-actions mt-16">
                <button class="btn btn-ghost" type="button" data-close-dialog>Cancel</button>
                <button class="btn btn-primary" type="submit">Create Task</button>
            </div>
        </form>
    </dialog>

    <!-- =========================================================================
         BULK TASK EDITING MODAL (Desktop Wide ~95vw, Max 1400px, Sticky Header/Footer)
         ========================================================================= -->
    <dialog id="bulk-edit-tasks-dialog" class="modal" style="width: 95vw; max-width: 1400px; max-height: 90vh; padding: 0; border: none; border-radius: 8px; box-shadow: 0 20px 50px rgba(0,0,0,0.25);">
        <form method="post" action="{{ route('tasks.bulk-update', $project) }}" id="bulk-edit-form" style="display: flex; flex-direction: column; height: 100%; max-height: 90vh;">
            @csrf

            <!-- Sticky Modal Header -->
            <div class="modal-head" style="padding: 16px 24px; border-bottom: 1px solid #E1E4E8; display: flex; justify-content: space-between; align-items: center; background: #FFFFFF; flex-shrink: 0;">
                <div>
                    <h2 style="font-size: 18px; font-weight: 700; color: #22272B; margin: 0 0 2px;">Edit Project Tasks</h2>
                    <p class="muted" style="font-size: 13px; margin: 0;">Update tasks directly in the table.</p>
                </div>
                <button type="button" class="icon-btn" data-close-dialog id="bulk-edit-x-btn" aria-label="Close dialog" style="font-size: 20px; background: none; border: none; cursor: pointer; color: #6B7280; padding: 4px 8px;">×</button>
            </div>

            <!-- Scrollable Bulk Edit Table -->
            <div class="bulk-edit-scroll" style="flex: 1; overflow-y: auto; overflow-x: auto; background: #FAFAFA; padding: 0;">
                <table class="clean-table bulk-edit-table" style="width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; background: #FFFFFF; font-size: 12.5px;">
                    <thead style="position: sticky; top: 0; z-index: 5; background: #F8FAFC;">
                        <tr style="border-bottom: 1px solid #CBD5E1; color: #475569; font-size: 11.5px; font-weight: 700;">
                            <th style="padding: 10px 12px; width: 45px; border-bottom: 1px solid #CBD5E1;">#</th>
                            <th style="padding: 10px 12px; min-width: 220px; border-bottom: 1px solid #CBD5E1;">Task / Subtask</th>
                            <th style="padding: 10px 12px; min-width: 180px; border-bottom: 1px solid #CBD5E1;">
                                Assignee
                                @if($canTeam)
                                    <button type="button" class="text-action" id="bulk-open-add-member" style="font-size: 10.5px; color: #315F58; margin-left: 6px; text-decoration: underline; background: none; border: none; cursor: pointer;">+ Add Member</button>
                                @endif
                            </th>
                            <th style="padding: 10px 8px; width: 130px; border-bottom: 1px solid #CBD5E1;">Start Date</th>
                            <th style="padding: 10px 8px; width: 95px; border-bottom: 1px solid #CBD5E1;">Start Time</th>
                            <th style="padding: 10px 8px; width: 130px; border-bottom: 1px solid #CBD5E1;">End Date</th>
                            <th style="padding: 10px 8px; width: 95px; border-bottom: 1px solid #CBD5E1;">End Time</th>
                            <th style="padding: 10px 8px; width: 75px; border-bottom: 1px solid #CBD5E1;">Progress</th>
                            <th style="padding: 10px 10px; width: 120px; border-bottom: 1px solid #CBD5E1;">Status</th>
                            <th style="padding: 10px 12px; min-width: 200px; border-bottom: 1px solid #CBD5E1;">Dependencies</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tasks as $task)
                            @php
                                $wbsNum = $numbers[$task->id] ?? $task->code;
                                $isSub = !empty($task->parent_task_id);
                                $assignedId = $task->assignees->first()?->id;
                                $depIds = $task->dependencies->pluck('id')->all();
                            @endphp
                            <tr class="bulk-task-row {{ $isSub ? 'is-subtask-row' : '' }}"
                                data-task-id="{{ $task->id }}"
                                style="border-bottom: 1px solid #E2E8F0; {{ $isSub ? 'background: #FAFCFF;' : '' }} transition: background 0.15s;">

                                <!-- 1. Number / Code -->
                                <td style="padding: 8px 12px; color: #64748B; font-weight: 600; font-size: 11.5px; border-bottom: 1px solid #E2E8F0; {{ $isSub ? 'padding-left: 18px;' : '' }}">
                                    @if($isSub)
                                        <span style="color: #94A3B8; margin-right: 2px;" aria-hidden="true">↳</span>
                                    @endif
                                    {{ $wbsNum }}
                                    @if($task->isOverdue())
                                        <div style="color: #DC2626; font-size: 9.5px; font-weight: 700; margin-top: 2px;">Overdue</div>
                                    @endif
                                </td>

                                <!-- 2. Task Name -->
                                <td style="padding: 8px 12px; border-bottom: 1px solid #E2E8F0; {{ $isSub ? 'padding-left: 24px;' : '' }}">
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <input type="text"
                                               name="tasks[{{ $task->id }}][title]"
                                               value="{{ $task->title }}"
                                               required
                                               maxlength="200"
                                               class="bulk-input bulk-field-track"
                                               data-field="title"
                                               style="flex: 1; font-size: 12.5px; padding: 5px 8px; border: 1px solid #CBD5E1; border-radius: 4px; {{ $isSub ? 'font-weight: 500; color: #334155;' : 'font-weight: 650; color: #0F172A;' }}">
                                        @if($isSub)
                                            <span style="font-size: 9.5px; background: #E2E8F0; color: #475569; border: 1px solid #CBD5E1; padding: 1px 5px; border-radius: 3px; font-weight: 600; flex-shrink: 0;">Subtask</span>
                                        @endif
                                    </div>
                                </td>

                                <!-- 3. Assignee -->
                                <td style="padding: 8px 12px; border-bottom: 1px solid #E2E8F0;">
                                    <select name="tasks[{{ $task->id }}][assigned_user_id]"
                                            class="bulk-select bulk-assignee-select bulk-field-track"
                                            data-field="assigned_user_id"
                                            style="width: 100%; font-size: 12px; padding: 5px 6px; border: 1px solid #CBD5E1; border-radius: 4px; background: #FFFFFF;">
                                        <option value="">Unassigned</option>
                                        @foreach($project->memberships->unique('user_id') as $m)
                                            <option value="{{ $m->user_id }}" @selected($assignedId == $m->user_id)>
                                                {{ $m->user?->name }} ({{ $m->role?->name ?? 'Member' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>

                                <!-- 4. Start Date -->
                                <td style="padding: 8px 8px; border-bottom: 1px solid #E2E8F0;">
                                    <input type="date"
                                           name="tasks[{{ $task->id }}][start_date]"
                                           value="{{ $task->starts_at->format('Y-m-d') }}"
                                           required
                                           class="bulk-input bulk-field-track"
                                           data-field="start_date"
                                           style="width: 100%; font-size: 11.5px; padding: 5px 4px; border: 1px solid #CBD5E1; border-radius: 4px;">
                                </td>

                                <!-- 5. Start Time -->
                                <td style="padding: 8px 8px; border-bottom: 1px solid #E2E8F0;">
                                    <input type="time"
                                           name="tasks[{{ $task->id }}][start_time]"
                                           value="{{ $task->starts_at->format('H:i') }}"
                                           required
                                           class="bulk-input bulk-field-track"
                                           data-field="start_time"
                                           style="width: 100%; font-size: 11.5px; padding: 5px 4px; border: 1px solid #CBD5E1; border-radius: 4px;">
                                </td>

                                <!-- 6. End Date -->
                                <td style="padding: 8px 8px; border-bottom: 1px solid #E2E8F0;">
                                    <input type="date"
                                           name="tasks[{{ $task->id }}][end_date]"
                                           value="{{ $task->ends_at->format('Y-m-d') }}"
                                           required
                                           class="bulk-input bulk-field-track"
                                           data-field="end_date"
                                           style="width: 100%; font-size: 11.5px; padding: 5px 4px; border: 1px solid #CBD5E1; border-radius: 4px;">
                                </td>

                                <!-- 7. End Time -->
                                <td style="padding: 8px 8px; border-bottom: 1px solid #E2E8F0;">
                                    <input type="time"
                                           name="tasks[{{ $task->id }}][end_time]"
                                           value="{{ $task->ends_at->format('H:i') }}"
                                           required
                                           class="bulk-input bulk-field-track"
                                           data-field="end_time"
                                           style="width: 100%; font-size: 11.5px; padding: 5px 4px; border: 1px solid #CBD5E1; border-radius: 4px;">
                                </td>

                                <!-- 8. Progress -->
                                <td style="padding: 8px 8px; border-bottom: 1px solid #E2E8F0;">
                                    <div style="display: flex; align-items: center; gap: 4px;">
                                        <input type="number"
                                               name="tasks[{{ $task->id }}][progress]"
                                               value="{{ $task->progress }}"
                                               min="0"
                                               max="100"
                                               required
                                               class="bulk-input bulk-progress-input bulk-field-track"
                                               data-task-id="{{ $task->id }}"
                                               data-field="progress"
                                               style="width: 52px; font-size: 12px; padding: 5px 4px; border: 1px solid #CBD5E1; border-radius: 4px; text-align: center;">
                                        <span class="muted" style="font-size: 11px;">%</span>
                                    </div>
                                </td>

                                <!-- 9. Status -->
                                <td style="padding: 8px 10px; border-bottom: 1px solid #E2E8F0;">
                                    <select name="tasks[{{ $task->id }}][status]"
                                            class="bulk-select bulk-status-select bulk-field-track"
                                            data-task-id="{{ $task->id }}"
                                            data-field="status"
                                            style="width: 100%; font-size: 12px; padding: 5px 6px; border: 1px solid #CBD5E1; border-radius: 4px; background: #FFFFFF; font-weight: 500;">
                                        <option value="todo" @selected($task->status === 'todo')>To Do</option>
                                        <option value="in_progress" @selected($task->status === 'in_progress')>In Progress</option>
                                        <option value="on_hold" @selected($task->status === 'on_hold')>On Hold</option>
                                        <option value="completed" @selected($task->status === 'completed')>Completed</option>
                                    </select>
                                </td>

                                <!-- 10. Dependencies (Predecessors) -->
                                <td style="padding: 8px 12px; border-bottom: 1px solid #E2E8F0;">
                                    <div class="bulk-dep-cell" data-task-id="{{ $task->id }}">
                                        <div class="bulk-dep-chips" id="bulk-chips-{{ $task->id }}" style="display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 4px;">
                                            @foreach($task->dependencies as $dep)
                                                <span class="bulk-dep-chip" data-dep-id="{{ $dep->id }}" style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 6px; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 3px; font-size: 11px; color: #334155;">
                                                    <span>{{ $numbers[$dep->id] ?? $dep->code }}</span>
                                                    <button type="button" class="bulk-remove-dep-btn" title="Remove dependency" style="border: none; background: none; cursor: pointer; color: #EF4444; font-weight: bold; font-size: 12px; line-height: 1; padding: 0 2px;">×</button>
                                                    <input type="hidden" name="tasks[{{ $task->id }}][dependencies][]" value="{{ $dep->id }}">
                                                </span>
                                            @endforeach
                                        </div>
                                        <select class="bulk-add-dep-select"
                                                data-task-id="{{ $task->id }}"
                                                style="width: 100%; font-size: 11px; padding: 3px 6px; border: 1px dashed #94A3B8; border-radius: 3px; background: #FFFFFF; color: #475569;">
                                            <option value="">+ Add Predecessor...</option>
                                            @foreach($tasks->where('id', '!=', $task->id) as $cand)
                                                @if(!in_array($cand->id, $depIds, true))
                                                    <option value="{{ $cand->id }}" data-label="{{ $numbers[$cand->id] ?? $cand->code }}">
                                                        {{ $numbers[$cand->id] ?? $cand->code }} – {{ \Illuminate\Support\Str::limit($cand->title, 24) }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Sticky Modal Footer -->
            <div class="modal-foot" style="padding: 12px 24px; border-top: 1px solid #E1E4E8; display: flex; justify-content: space-between; align-items: center; background: #FFFFFF; flex-shrink: 0;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <button type="button" class="btn btn-ghost" data-close-dialog id="bulk-edit-cancel-btn">Cancel</button>
                    <span id="bulk-modified-counter" class="muted" style="font-size: 13px; font-weight: 500;">0 tasks modified</span>
                </div>
                <button type="submit" class="btn btn-primary" id="bulk-edit-save-btn" style="min-width: 150px; font-weight: 650;">
                    Save All Changes
                </button>
            </div>
        </form>
    </dialog>

    @if($canTeam)
        <!-- Quick Add Project Member Dialog (non-disruptive inline modal) -->
        <dialog id="quick-add-member-dialog" class="modal" style="max-width: 440px;">
            <div class="modal-head">
                <h2>Add Project Member</h2>
                <button type="button" class="icon-btn" data-close-dialog aria-label="Close">×</button>
            </div>
            <form id="quick-add-member-form" method="post" action="{{ route('projects.members.store', $project) }}" class="stack mt-16">
                @csrf
                <label>Person <span class="required">*</span>
                    <select name="user_id" id="quick-member-user-id" required>
                        <option value="">Select team member...</option>
                        @foreach($users as $u)
                            @if(!$project->memberships->pluck('user_id')->contains($u->id) && $project->pm_user_id !== $u->id)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <label>Project Role <span class="required">*</span>
                    <select name="project_role_id" id="quick-member-role-id" required>
                        @foreach($roles->where('slug', '!=', 'pm') as $r)
                            <option value="{{ $r->id }}" @selected($r->slug === 'team')>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="form-actions mt-16">
                    <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                    <button type="submit" class="btn btn-primary" id="quick-add-member-submit-btn">Add to Project</button>
                </div>
            </form>
        </dialog>
    @endif

    <!-- Import Task Dialog -->
    <dialog id="import-task-dialog" class="modal project-import-dialog" aria-labelledby="project-import-title" aria-describedby="project-import-description" data-import-reopen="{{ $errors->has('file') ? 'true' : 'false' }}">
        <header class="modal-head"><div><h2 id="project-import-title">Import tasks</h2><p>{{ $project->name }} <span>&middot; {{ $project->code }}</span></p></div><button type="button" class="icon-btn" data-close-dialog aria-label="Close import">&times;</button></header>
        <p id="project-import-description" class="import-intro">Prepare your spreadsheet, then review the changes before saving.</p>
        <section class="project-import-source"><div><span>{{ $project->template ? 'Selected project template' : 'Blank project' }}</span><b>{{ $project->template?->name ?? 'Start with an empty task worksheet' }}</b><small>{{ $project->template ? 'Original plan with dates aligned to this project. Current assignments and progress are not copied.' : 'Includes the correct columns and import instructions for this project.' }}</small></div><a href="{{ $projectImportUrl }}" class="btn btn-outline btn-sm">{{ $projectImportLabel }} &darr;</a></section>
        <form method="post" action="{{ route('tasks.import.preview',$project) }}" enctype="multipart/form-data" class="project-import-form">
            @csrf
            <label for="project-import-file">Choose Excel or CSV file <span class="required">*</span></label>
            <div class="project-import-upload"><input id="project-import-file" type="file" name="file" accept=".xlsx,.csv" required aria-describedby="project-import-help project-import-feedback"><small id="project-import-help">.xlsx or .csv &middot; Maximum 2 MB</small></div>
            <p id="project-import-feedback" class="project-import-feedback" role="status">Matching task codes update existing tasks. Other tasks are kept.</p>
            <footer class="form-actions"><button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button><button type="submit" class="btn btn-primary">Preview changes &rarr;</button></footer>
        </form>
    </dialog>
@endif

@if(app(\App\Services\Access::class)->project(auth()->user(), $project, 'edit_project') && $project->status !== 'archived')
    @include('projects._edit-dialog')
@endif

@if(!auth()->user()->isAdminViewer() && $project->status === 'pending' && $project->pm_user_id === auth()->id())
    <!-- PM Decline Assignment Dialog -->
    <dialog id="decline-project-dialog" class="modal" style="max-width: 520px;">
        <div class="modal-head">
            <h2 style="display: flex; align-items: center; gap: 8px; color: #B91C1C; margin: 0; font-size: 17px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                Decline Project Assignment
            </h2>
            <button type="button" class="icon-btn" data-close-dialog aria-label="Close">×</button>
        </div>
        <form action="{{ route('projects.respond', $project) }}" method="post" class="stack mt-16">
            @csrf
            <input type="hidden" name="decision" value="decline">

            <p class="muted text-sm" style="line-height: 1.5; margin: 0 0 12px 0;">
                You are declining the Project Manager role for <strong>{{ $project->name }} ({{ $project->code }})</strong>.
                Please provide a specific reason so the PMO Admin can understand your constraints and reassign the project appropriately.
            </p>

            <label style="display: block; font-weight: 600; font-size: 13px;">
                Reason for Declining <span class="required" style="color: #DC2626;">*</span>
                <textarea name="decline_reason" id="decline-reason-input" rows="4" required minlength="5" maxlength="1000"
                    placeholder="e.g. Schedule conflicts with existing commitments, lack of technical capacity in this domain, currently at full project capacity..."
                    class="form-textarea" style="width: 100%; margin-top: 6px; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-family: inherit; font-size: 13px;"></textarea>
                <span class="text-xs muted" style="margin-top: 4px; display: block;">Minimum 5 characters. This reason will be recorded and shared directly with PMO administration.</span>
            </label>

            <div class="form-actions mt-16" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                <button type="submit" class="btn" style="background: #DC2626; color: #FFFFFF; font-weight: 600; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer;">
                    Confirm & Decline Assignment
                </button>
            </div>
        </form>
    </dialog>
@endif

@if(auth()->user()->isPmo() && in_array($project->status, ['draft', 'pending', 'declined']))
    <!-- Assign / Reassign PM Dialog -->
    <dialog id="assign-pm-dialog" class="modal" style="max-width: 520px;">
        <div class="modal-head">
            <h2 style="margin: 0; font-size: 17px;">
                @if($project->status === 'declined')
                    Reassign Project Manager
                @elseif($project->status === 'pending')
                    Reassign Project Manager
                @else
                    Assign Project Manager
                @endif
            </h2>
            <button type="button" class="icon-btn" data-close-dialog aria-label="Close">×</button>
        </div>
        <form method="post" action="{{ route('projects.manager', $project) }}" class="stack mt-16">
            @csrf

            @if($project->status === 'declined' && $declinedInvitation)
                <div style="background: #FEF2F2; border: 1px solid #FECACA; border-radius: 6px; padding: 10px 14px; font-size: 13px; color: #991B1B; margin-bottom: 12px;">
                    <div style="font-weight: 700;">Previous PM: {{ $declinedInvitation->user?->name ?? $project->manager?->name ?? 'Assigned PM' }} (Declined)</div>
                    @if($declinedInvitation->decline_reason)
                        <div style="margin-top: 4px; font-style: italic; color: #4B5563;">"{{ $declinedInvitation->decline_reason }}"</div>
                    @endif
                </div>
            @elseif($project->status === 'pending' && $project->manager)
                <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 6px; padding: 10px 14px; font-size: 13px; color: #92400E; margin-bottom: 12px;">
                    Currently assigned to <strong>{{ $project->manager->name }}</strong> (Awaiting response). Reassigning will cancel the existing pending invitation and send a request to the newly selected manager.
                </div>
            @else
                <p class="muted text-sm" style="margin-top: 0; margin-bottom: 12px;">The selected manager will receive an invitation to review, accept, and activate this project.</p>
            @endif

            <label style="display: block; font-weight: 600; font-size: 13px;">Project Manager <span class="required">*</span>
                <select name="pm_user_id" required style="width: 100%; margin-top: 6px; padding: 8px 10px; border: 1px solid #D1D5DB; border-radius: 6px;">
                    <option value="">Choose an active Project Manager...</option>
                    @foreach($pmCandidates ?? $users as $u)
                        <option value="{{ $u->id }}" @selected($u->id === $project->pm_user_id)>
                            {{ $u->name }} ({{ $u->email }}){{ $u->id === $project->pm_user_id && $project->status === 'declined' ? ' — Previously Declined' : '' }}
                        </option>
                    @endforeach
                </select>
            </label>
            <div class="form-actions mt-16" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button class="btn btn-ghost" type="button" data-close-dialog>Cancel</button>
                <button class="btn btn-primary" type="submit" style="font-weight: 650;">
                    {{ in_array($project->status, ['declined', 'pending']) ? 'Reassign & Send Request' : 'Send Invitation' }}
                </button>
            </div>
        </form>
    </dialog>
@endif

@if($canTeam)
    <!-- Project Closure & Sign-off Dialog -->
    <dialog id="project-closure-dialog" class="modal" style="max-width: 540px;">
        <div class="modal-head">
            <h2>Project Closure & Sign-off</h2>
            <button type="button" class="icon-btn" data-close-dialog>×</button>
        </div>
        <p class="muted text-sm mt-8">Review the deliverables checklist and submit this project for formal sign-off and closure.</p>
        <form method="post" action="{{ route('projects.closure.submit', $project) }}" class="stack mt-16">
            @csrf
            <div class="closure-checklist stack" style="gap: 10px; background: var(--surface); padding: 14px; border-radius: 6px; border: 1px solid var(--border);">
                <div style="font-weight: 600; font-size: 13px;">Completion checklist</div>
                <label class="custom-checkbox-row" style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                    <input type="checkbox" name="tasks_completed" value="1" {{ $project->progress >= 100 ? 'checked' : '' }}>
                    <span>All project tasks are completed.</span>
                </label>
                <label class="custom-checkbox-row" style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                    <input type="checkbox" name="deliverables_submitted" value="1" checked>
                    <span>Deliverables are submitted.</span>
                </label>
                <label class="custom-checkbox-row" style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                    <input type="checkbox" name="approval_obtained" value="1">
                    <span>Client / Sponsor approval is obtained.</span>
                </label>
            </div>

            <label class="mt-16" style="display: block; font-weight: 600; font-size: 13px;">Closure Notes
                <textarea name="notes" rows="4" placeholder="Enter closure notes..." class="form-textarea" style="width: 100%; margin-top: 6px;"></textarea>
            </label>

            <div class="form-actions mt-16" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                <button type="submit" name="action" value="draft" class="btn btn-outline">Save Draft</button>
                <button type="submit" name="action" value="request" class="btn btn-primary">Request Closure</button>
            </div>
        </form>
    </dialog>
@endif

@if($canManage)
    <!-- Report Blocker / Issue Dialog -->
    <dialog id="report-blocker-dialog" class="modal" style="max-width: 540px;">
        <div class="modal-head">
            <h2>Report Blocker / Delay</h2>
            <button type="button" class="icon-btn" data-close-dialog>×</button>
        </div>
        <p class="muted text-sm mt-8">Log a task impediment, vendor dependency, or approval delay with an assigned follow-up owner.</p>
        <form method="post" id="report-blocker-form" action="" class="stack mt-16" onsubmit="return validateBlockerForm(this);">
            @csrf
            <label style="display: block; font-weight: 600; font-size: 13px;">Affected Task <span class="required">*</span>
                <select name="task_id" id="rb-task-select" required onchange="onBlockerTaskChange(this)" style="width: 100%; margin-top: 6px; padding: 8px 10px; border: 1px solid #D1D5DB; border-radius: 6px;">
                    <option value="">-- Choose affected task --</option>
                    @foreach($tasks->filter(fn($t) => $t->status !== 'completed' && (int)$t->progress < 100) as $ot)
                        <option value="{{ $ot->id }}" data-action="{{ route('tasks.delays.store', $ot) }}">
                            {{ $ot->code }} - {{ $ot->title }} {{ $ot->isOverdue() ? '(Overdue)' : '' }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label style="display: block; font-weight: 600; font-size: 13px; margin-top: 10px;">Reason <span class="required">*</span>
                <textarea name="reason_text" required rows="2" maxlength="500" placeholder="Explain why this task is blocked or delayed..." style="width:100%;margin-top:6px;resize:vertical">{{ old('reason_text') }}</textarea>
            </label>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                <label style="display: block; font-weight: 600; font-size: 13px;">Involved Party Type <span class="required">*</span>
                    <select name="responsible_party_type" required style="width: 100%; margin-top: 6px; padding: 8px 10px; border: 1px solid #D1D5DB; border-radius: 6px;">
                        <option value="supplier">Supplier / Vendor</option>
                        <option value="internal" selected>Internal Team</option>
                        <option value="department">Other Department</option>
                        <option value="external">External Authority</option>
                        <option value="unknown">Unknown</option>
                    </select>
                </label>
                <label style="display: block; font-weight: 600; font-size: 13px;">Party / Vendor Name
                    <input type="text" name="responsible_party_name" placeholder="e.g. Legal, Vendor name" style="width: 100%; margin-top: 6px; padding: 8px 10px; border: 1px solid #D1D5DB; border-radius: 6px;">
                </label>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                <label style="display: block; font-weight: 600; font-size: 13px;">Follow-up Owner
                    <select name="action_owner_id" style="width: 100%; margin-top: 6px; padding: 8px 10px; border: 1px solid #D1D5DB; border-radius: 6px;">
                        <option value="">-- Select follow-up owner --</option>
                        @if($project->manager)
                            <option value="{{ $project->manager->id }}">{{ $project->manager->name }} (Project Manager)</option>
                        @endif
                        @foreach($project->memberships as $m)
                            @if(!$project->manager || $m->user_id !== $project->manager->id)
                                <option value="{{ $m->user_id }}">{{ $m->user?->name ?? 'User #' . $m->user_id }}</option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <label style="display: block; font-weight: 600; font-size: 13px;">Revised Target End Date
                    <input type="date" name="revised_expected_end_at" style="width: 100%; margin-top: 6px; padding: 8px 10px; border: 1px solid #D1D5DB; border-radius: 6px;">
                </label>
            </div>

            <label style="display: block; font-weight: 600; font-size: 13px; margin-top: 10px;">Blocker Details &amp; Mitigation
                <textarea name="details" rows="3" placeholder="Explain what is causing the bottleneck and what is required to unblock it..." style="width: 100%; margin-top: 6px; padding: 8px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px;"></textarea>
            </label>

            <div class="form-actions mt-16" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #315F58; color: #FFFFFF; font-weight: 650;">Submit Blocker Report</button>
            </div>
        </form>
    </dialog>
@endif

@push('scripts')
@if($canViewTasks && $tasks->isNotEmpty())
<script>
window.gsProjectTimeline = {{ \Illuminate\Support\Js::from($tasks->map(fn($t) => [
    'id' => $t->id,
    'title' => $t->title,
    'start' => $t->starts_at->toIso8601String(),
    'end' => $t->ends_at->toIso8601String(),
    'progress' => $t->progress,
    'critical' => $health && in_array($t->id, $health['critical_ids'] ?? [], true)
])->values()->all()) }};
</script>
@endif
<script defer src="{{ asset('js/project-workspace.js') }}?v={{ filemtime(public_path('js/project-workspace.js')) }}"></script>
<script>
function openReportDelayModal(taskId) {
    const dialog = document.getElementById('report-blocker-dialog');
    if (!dialog) return;
    const taskSelect = document.getElementById('rb-task-select');
    if (taskId && taskSelect) {
        taskSelect.value = taskId;
        onBlockerTaskChange(taskSelect);
    }
    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
    } else {
        dialog.style.display = 'flex';
    }
}
function onBlockerTaskChange(select) {
    const form = document.getElementById('report-blocker-form');
    if (!form || !select) return;
    const opt = select.options[select.selectedIndex];
    if (opt && opt.dataset.action) {
        form.action = opt.dataset.action;
    }
}
function validateBlockerForm(form) {
    const taskSelect = document.getElementById('rb-task-select');
    if (!taskSelect || !taskSelect.value) {
        alert('Please select an affected task.');
        return false;
    }
    onBlockerTaskChange(taskSelect);
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('reassign') === '1') {
        const dialog = document.getElementById('assign-pm-dialog');
        if (dialog && typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    }
});
</script>
@endpush

@endsection
