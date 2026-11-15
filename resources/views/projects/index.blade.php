@extends('layouts.app')
@section('title', 'Projects')
@section('content')
@include('projects._delete-dialog')

<!-- Page Header matching mockup -->
<div class="page-head">
    <div>
        <h1>{{ auth()->user()->canViewPortfolio() ? 'Projects' : 'My Projects' }}</h1>
        <p class="muted">{{ auth()->user()->isAdminViewer() ? 'Monitor delivery, ownership, schedule and progress across the company portfolio.' : (auth()->user()->canViewPortfolio() ? 'Track delivery, manage your team and keep projects moving.' : 'Projects you are involved in.') }}</p>
    </div>
    <div class="inline-actions projects-page-actions">
        @if(auth()->user()->isPmo())
            <a class="btn btn-outline" href="{{ route('gantt.portfolio') }}">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 5.5h4v3H3zM9 7h8M3 11.5h7v3H3zM13 13h4"></path></svg>
                Portfolio timeline
            </a>
            <a class="btn btn-primary" href="{{ route('projects.create') }}">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true"><path d="M10 4v12M4 10h12"></path></svg>
                New Project
            </a>
        @endif
    </div>
</div>

@php
    $projectSummary = [
        ['label'=>'All projects', 'count'=>$totalCount, 'query'=>request()->except('page','status','attention'), 'active'=>!request()->filled('status') && !request()->boolean('attention')],
        ['label'=>'Active', 'count'=>$activeCount, 'query'=>array_merge(request()->except('page','attention'), ['status'=>'active']), 'active'=>request('status')==='active' && !request()->boolean('attention')],
        ['label'=>auth()->user()->canViewPortfolio() ? 'Awaiting approval' : 'Pending action', 'count'=>$pendingCount, 'query'=>array_merge(request()->except('page','attention'), ['status'=>'pending']), 'active'=>request('status')==='pending' && !request()->boolean('attention')],
        ['label'=>'Needs attention', 'count'=>$attentionCount, 'query'=>array_merge(request()->except('page','status'), ['attention'=>1]), 'active'=>request()->boolean('attention')],
    ];
    if(auth()->user()->canViewPortfolio()) {
        $projectSummary[] = ['label'=>'Drafts', 'count'=>$draftCount ?? 0, 'query'=>array_merge(request()->except('page','attention'), ['status'=>'draft']), 'active'=>request('status')==='draft' && !request()->boolean('attention')];
    }
@endphp
<nav class="project-library-summary" aria-label="Filter projects">
    @foreach($projectSummary as $summaryItem)
        <a href="{{ route('projects.index', $summaryItem['query']) }}" class="{{ $summaryItem['active'] ? 'active' : '' }}" @if($summaryItem['active']) aria-current="true" @endif>
            <span>{{ $summaryItem['label'] }}</span><strong title="Number of projects">{{ $summaryItem['count'] }}</strong>
        </a>
    @endforeach
</nav>
<!-- Search and Filter Bar -->
<form method="get" action="{{ route('projects.index') }}" id="projects-filter-form" class="projects-toolbar-wrap">
    <div class="projects-search-input-wrap">
        <input type="text" name="q" id="projects-search-input" class="projects-search-input" placeholder="{{ auth()->user()->canViewPortfolio() ? 'Search projects or people...' : 'Search your projects by name or code...' }}" value="{{ request('q') }}" autocomplete="off">
    </div>

    @if(auth()->user()->canViewPortfolio())
        <select name="subsidiary_id" class="projects-filter-select" onchange="this.form.submit()">
            <option value="">All Subsidiaries</option>
            @foreach($subsidiaries as $sub)
                <option value="{{ $sub->id }}" @selected(request('subsidiary_id') == $sub->id)>{{ $sub->name }}</option>
            @endforeach
        </select>
    @else
        <!-- Role Scope Filter for Standard Users -->
        <select name="scope" class="projects-filter-select" onchange="this.form.submit()">
            <option value="" @selected(!request('scope'))>All Projects</option>
            <option value="managed" @selected(request('scope') === 'managed')>Managed by Me</option>
            <option value="assigned" @selected(request('scope') === 'assigned')>Assigned Tasks</option>
            <option value="view_only" @selected(request('scope') === 'view_only')>View-Only Projects</option>
        </select>
    @endif

    @if(request()->filled('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <details class="library-other-statuses"><summary>{{ ['on_hold'=>'On hold','completed'=>'Completed','declined'=>'Declined','archived'=>'Archived'][request('status')] ?? 'Other statuses' }} <span aria-hidden="true">&#8964;</span></summary><div class="library-status-options">
        @foreach(['on_hold'=>'On hold','completed'=>'Completed','declined'=>'Declined','archived'=>'Archived'] as $value=>$label)
            <a href="{{ route('projects.index',array_merge(request()->except('page','attention','status'),['status'=>$value])) }}" @if(request('status')===$value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </div></details>

    @if(request()->filled('q') || request()->filled('status') || request()->filled('subsidiary_id') || request()->filled('scope') || request()->boolean('attention'))
        <a href="{{ route('projects.index') }}" class="btn btn-ghost btn-sm">Clear filters</a>
    @endif
    @if(request()->boolean('attention'))
        <input type="hidden" name="attention" value="1">
        <span class="filter-context">Showing projects with overdue work, blockers or pending setup/approval</span>
    @endif
    <div class="view-toggle-group">
        <button type="button" class="view-toggle-btn active" id="view-btn-list" aria-label="List view" aria-pressed="true" title="List view" onclick="switchProjectsView('list')">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M2.5 3.5a1 1 0 0 1 1-1h9a1 1 0 1 1 0 2h-9a1 1 0 0 1-1-1zm0 4.5a1 1 0 0 1 1-1h9a1 1 0 1 1 0 2h-9a1 1 0 0 1-1-1zm0 4.5a1 1 0 0 1 1-1h9a1 1 0 1 1 0 2h-9a1 1 0 0 1-1-1z"/>
            </svg>
        </button>
        <button type="button" class="view-toggle-btn" id="view-btn-grid" aria-label="Grid view" aria-pressed="false" title="Grid view" onclick="switchProjectsView('grid')">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
                <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zm0 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm8-8A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm0 8A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3z"/>
            </svg>
        </button>
    </div>
</form>

<!-- List View (Default) -->
<div id="projects-list-container" class="projects-table-card">
    <div class="table-wrap">
        <table class="projects-table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>{{ auth()->user()->canViewPortfolio() ? 'Project leads' : 'My role' }}</th>

                    <th>Progress</th>
                    <th>Budget <span class="budget-currency-label">LKR</span></th>
                    <th>Due</th>
                    <th>{{ auth()->user()->canViewPortfolio() ? 'Status / Next action' : 'Status' }}</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                    @php
                        $userRoles = isset($access) ? $access->roleLabels(auth()->user(), $project) : ['Member'];
                        $isPm = $project->pm_user_id === auth()->id();
                        $isSelfManagedPmoProject = auth()->user()->isPmo() && (int) $project->created_by === auth()->id() && (int) $project->pm_user_id === auth()->id();
                        $canViewTasks = auth()->user()->isPmo() || $isPm || (isset($access) && $access->project(auth()->user(), $project, 'view_tasks'));
                        $isDelayed = ($project->approval_stage!=='planning' || $isSelfManagedPmoProject) && (bool) ($project->is_delayed ?? false);
                        $isAtRisk = ($project->approval_stage!=='planning' || $isSelfManagedPmoProject) && (bool) ($project->is_at_risk ?? false);
                        $delayDays = (int) ($project->delay_working_days ?? 0);
                        $overdueCount = (int) ($project->overdue_tasks_count ?? 0);
                    @endphp
                    <tr class="clickable-project-row {{ $isDelayed ? 'row-delayed' : ($isAtRisk ? 'row-at-risk' : '') }}" data-href="{{ route('projects.show', $project) }}" style="cursor: pointer;">
                        <td>
                            <div class="project-title-cell">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    @if($project->approval_stage!=='planning' && $isDelayed)
                                        <span class="health-dot health-delayed" title="Delayed project" aria-label="Delayed"></span>
                                    @elseif($project->approval_stage!=='planning' && $isAtRisk)
                                        <span class="health-dot health-at-risk" title="At risk project" aria-label="At Risk"></span>
                                    @endif
                                    <a class="strong-link" href="{{ route('projects.show', $project) }}">
                                        <b>{{ $project->name }}</b>
                                    </a>
                                </div>
                                <div class="project-code-sub">
                                    <span>{{ $project->code }}</span>
                                    @if($isDelayed && $delayDays > 0)
                                        <span class="sub-health-delay">· Delayed · {{ $delayDays }} {{ $delayDays === 1 ? 'working day' : 'working days' }}</span>
                                    @elseif($isAtRisk)
                                        <span class="sub-health-at-risk">· At Risk</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            @if(auth()->user()->canViewPortfolio())
                                @include('projects._leads',['project'=>$project])
                            @else
                                <span class="muted text-sm" style="font-weight: 600; color: #374151;">
                                    {{ implode(', ', $userRoles) }}
                                </span>
                            @endif
                        </td>

                        <td>
                            @if($canViewTasks)
                                <div class="progress-col-wrap">
                                    @if($project->status==='draft')<span class="library-draft-plan">Plan prepared</span>@else<span class="progress-pct-val">{{ $project->progress }}%</span>@endif
                                    <small class="project-completion">{{ $project->status==='draft' ? ($project->tasks_count ?? 0).' planned tasks' : ($project->completed_tasks_count ?? 0).' / '.($project->tasks_count ?? 0).' tasks' }}</small><div class="progress-track-wide" @if($project->status==='draft') hidden @endif>
                                        <div class="progress-fill" style="width: {{ $project->progress }}%;"></div>
                                    </div>
                                </div>
                            @else
                                <span class="muted text-sm">—</span>
                            @endif
                        </td>
                        <td>
                            @if($project->canViewBudget(auth()->user()))
                                @php
                                    $spent=(float)$project->actual_cost;
                                    $planned=$project->planned_budget;
                                    $money=fn($amount)=>number_format($amount, fmod((float)$amount,1.0)==0.0?0:2);
                                @endphp
                                @if($planned===null && $spent==0)
                                    <span class="budget-not-set">Not set</span>
                                @else
                                    <div class="budget-compact" aria-label="Budget in LKR">
                                        <div class="budget-compact-spent" title="Spent: LKR {{ number_format($spent,2) }}"><strong>{{ $money($spent) }}</strong><span>spent</span></div>
                                        <small class="budget-compact-plan" title="{{ $planned===null?'Planned budget not set':'Planned: LKR '.number_format($planned,2) }}">{{ $planned===null?'Budget not set':'of '.$money($planned).' planned' }}</small>
                                        @if($planned!==null && $spent>$planned)<small class="budget-overrun" title="Over budget by LKR {{ number_format($spent-$planned,2) }}">Over by {{ $money($spent-$planned) }}</small>@endif
                                    </div>
                                @endif
                            @else<span class="muted">&mdash;</span>@endif
                        </td>
                        <td>
                            <span class="deadline-val">{{ $project->approval_stage && !$project->plan_initialized ? 'Not scheduled' : $project->ends_on->format('d M Y') }}</span>@if($project->status==='draft')<small class="library-planned-date">Planned due date</small>@endif
                        </td>
                        <td>
                            <div class="project-health-cell">
                                @if(auth()->user()->canViewPortfolio()) @include('projects._workflow-holder') @endif
                                @if($project->approval_stage==='planning' && !$isSelfManagedPmoProject)<span class="status-pill pill-neutral">Preparing plan</span>
                                @elseif($project->status === 'declined')
                                    <span class="status-pill status-declined" style="background: #FEE2E2; color: #991B1B; font-weight: 600;">{{ $project->approval_stage==='owner_declined'?'Owner Declined':'PM Declined' }}</span>
                                @elseif($project->status === 'pending')
                                    <span class="status-pill status-pending" style="background: #FEF3C7; color: #92400E; font-weight: 600;">{{ $project->approval_stage==='owner'?'Awaiting Owner approval':'Awaiting PM approval' }}</span>
                                @elseif($project->status === 'draft')
                                    <span class="status-pill status-draft" style="background: #F1F5F9; color: #475569; font-weight: 600;">Draft</span>
                                @else
                                    <span class="status-pill status-{{ $project->status }}">{{ ucwords(str_replace('_', ' ', $project->status)) }}</span>
                                @endif
                                @if($isDelayed)
                                    <span class="status-pill pill-danger" style="font-size: 11px; padding: 1px 7px;">
                                        Delayed @if($delayDays > 0)· {{ $delayDays }}d @endif
                                    </span>
                                    @if($overdueCount > 0)
                                        <span class="overdue-count-pill">{{ $overdueCount }} Overdue {{ $overdueCount === 1 ? 'Task' : 'Tasks' }}</span>
                                    @endif
                                @elseif($isAtRisk)
                                    <span class="status-pill pill-warning" style="font-size: 11px; padding: 1px 7px;">
                                        At Risk
                                    </span>
                                    @if($overdueCount > 0)
                                        <span class="overdue-count-pill warning">{{ $overdueCount }} Overdue {{ $overdueCount === 1 ? 'Task' : 'Tasks' }}</span>
                                    @endif
                                @elseif(($project->approval_stage!=='planning' || $isSelfManagedPmoProject) && $project->status === 'active' && ($project->tasks_count ?? 0) > 0)
                                    <span class="status-pill pill-success" style="font-size: 11px; padding: 1px 7px;">On Track</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="table-actions-cell">
                                <!-- Review Delay Primary Action (Direct 1-Click Trigger) -->
                                @if($project->approval_stage!=='planning' && ($isDelayed || $isAtRisk || $overdueCount > 0))
                                    <button type="button" 
                                            class="btn btn-outline btn-xs btn-review-delay" 
                                            onclick="openDelayAnalysis({{ $project->id }})"
                                            title="Open Project Delay & Critical Path Investigation">
                                        Review
                                    </button>
                                @endif

                                @if($project->status==='draft' && (auth()->user()->isPmo() || ($project->created_by===auth()->id() && app(\App\Services\Access::class)->hasCompanyPermission(auth()->user(),'projects.create'))))
                                    <a href="{{ route('projects.create',['draft'=>$project->id]) }}" class="btn btn-outline btn-xs project-draft-create">Continue draft</a>
                                @elseif(auth()->user()->isPmo() && $project->status === 'declined')
                                    <a href="{{ route('projects.show', [$project, 'reassign' => 1]) }}" class="btn btn-primary btn-xs" style="background: #DC2626; border-color: #DC2626; font-weight: 600;">{{ $project->approval_stage ? 'Reassign' : 'Reassign PM' }}</a>
                                @elseif(auth()->user()->isPmo() && $project->status === 'draft' && !$project->pm_user_id)
                                    <a href="{{ route('projects.show', [$project, 'reassign' => 1]) }}" class="btn btn-outline btn-xs">Assign PM</a>
                                @elseif($project->status === 'pending' && $isPm)
                                    <a href="{{ route('projects.show', $project) }}" class="btn btn-primary btn-xs">Review & Respond</a>

                                @endif

                                <!-- 3-Dots Dropdown Trigger -->
                                <div style="position: relative;">
                                    <button type="button" class="btn-dots" onclick="toggleProjectMenu(event, 'proj-menu-{{ $project->id }}')" aria-label="More project actions" aria-haspopup="true" aria-expanded="false">⋮</button>
                                    <div class="actions-dropdown-panel" id="proj-menu-{{ $project->id }}">
                                        <a href="{{ route('projects.show', $project) }}" class="dropdown-item">View project</a>
                                        @if(auth()->user()->isPmo() || (isset($access) && $access->project(auth()->user(), $project, 'edit_project')))
                                            <a href="{{ route('projects.edit', $project) }}" class="dropdown-item" data-project-editor-url="{{ route('projects.edit', [$project, 'popup'=>1]) }}">Edit project</a>
                                        @endif
                                        <button type="button" class="dropdown-item" onclick="openDelayAnalysis({{ $project->id }})">Project analysis</button>
@if(auth()->user()->isPmo())<button type="button" class="dropdown-item project-delete-action" data-delete-project-url="{{ route('projects.destroy',$project) }}" data-delete-project-name="{{ $project->name }}" data-delete-project-code="{{ $project->code }}">Delete project</button>@endif
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-state">
                            No projects found matching the filter criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Grid View (Optional Toggle) -->
<div id="projects-grid-container" class="projects-grid-view" style="display: none;">
    @forelse($projects as $project)
        @php
            $isSelfManagedPmoProject = auth()->user()->isPmo() && (int) $project->created_by === auth()->id() && (int) $project->pm_user_id === auth()->id();
            $isDelayed = ($project->approval_stage!=='planning' || $isSelfManagedPmoProject) && (bool) ($project->is_delayed ?? false);
            $isAtRisk = ($project->approval_stage!=='planning' || $isSelfManagedPmoProject) && (bool) ($project->is_at_risk ?? false);
            $delayDays = (int) ($project->delay_working_days ?? 0);
            $overdueCount = (int) ($project->overdue_tasks_count ?? 0);
        @endphp
        <div class="project-grid-card clickable-project-card {{ $isDelayed ? 'grid-delayed' : '' }}" data-href="{{ route('projects.show', $project) }}" style="cursor: pointer;">
            <div class="grid-card-head">
                <div class="grid-card-title">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        @if($isDelayed)
                            <span class="health-dot health-delayed"></span>
                        @elseif($isAtRisk)
                            <span class="health-dot health-at-risk"></span>
                        @endif
                        <a href="{{ route('projects.show', $project) }}"><b>{{ $project->name }}</b></a>
                    </div>
                    <small>{{ $project->code }} @if($project->subsidiary) · {{ $project->subsidiary->name }} @endif</small>
                </div>
                <div style="text-align: right;">
                    @if($project->approval_stage==='planning' && !$isSelfManagedPmoProject)<span class="status-pill pill-neutral">Preparing plan</span>
                                @elseif($project->status === 'declined')
                        <span class="status-pill status-declined" style="background: #FEE2E2; color: #991B1B; font-weight: 600;">{{ $project->approval_stage==='owner_declined'?'Owner Declined':'PM Declined' }}</span>
                    @elseif($project->status === 'pending')
                        <span class="status-pill status-pending" style="background: #FEF3C7; color: #92400E; font-weight: 600;">{{ $project->approval_stage==='owner'?'Awaiting Owner approval':'Awaiting PM approval' }}</span>
                    @else
                        <span class="status-pill status-{{ $project->status }}">{{ ucwords(str_replace('_', ' ', $project->status)) }}</span>
                    @endif
                    @if($isDelayed)
                        <div class="mt-4"><span class="status-pill pill-danger" style="font-size: 10px;">Delayed @if($delayDays > 0)· {{ $delayDays }}d @endif</span></div>
                    @elseif($isAtRisk)
                        <div class="mt-4"><span class="status-pill pill-warning" style="font-size: 10px;">At Risk</span></div>
                    @endif
                </div>
            </div>

            <div class="grid-card-meta">
                <div class="grid-meta-row">
                    <span>Project leads:</span>
                    @include('projects._leads',['project'=>$project])
                </div>
                <div class="grid-meta-row">
                    <span>Deadline:</span>
                    <span>{{ $project->ends_on->format('d M Y') }}</span>
                </div>
                <div class="grid-meta-row">
                    <span>Tasks:</span>
                    <b>{{ $project->completed_tasks_count ?? 0 }} / {{ $project->tasks_count ?? 0 }}</b>
                </div>
                @if($overdueCount > 0)
                    <div class="grid-meta-row" style="color: #DC2626;">
                        <span>Overdue Tasks:</span>
                        <b>{{ $overdueCount }}</b>
                    </div>
                @endif
            </div>

            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12px; font-weight: 600;">
                    <span>Progress</span>
                    <span>{{ $project->progress }}%</span>
                </div>
                <small class="project-completion">{{ $project->completed_tasks_count ?? 0 }} / {{ $project->tasks_count ?? 0 }} tasks</small><div class="progress-track-wide">
                    <div class="progress-fill" style="width: {{ $project->progress }}%;"></div>
                </div>
            </div>

            <div class="grid-card-foot">
                @if($project->status==='draft' && (auth()->user()->isPmo() || ($project->created_by===auth()->id() && app(\App\Services\Access::class)->hasCompanyPermission(auth()->user(),'projects.create'))))<a href="{{ route('projects.create',['draft'=>$project->id]) }}" class="btn btn-primary btn-xs project-draft-create">Create project</a>@endif
                @if($isDelayed || $isAtRisk || $overdueCount > 0)
                    <button type="button" class="btn btn-outline btn-xs btn-review-delay" onclick="openDelayAnalysis({{ $project->id }})">Review Delay</button>
                @endif
                <a href="{{ route('projects.show', $project) }}" class="btn btn-outline btn-xs">Open Plan</a>
                <a href="{{ route('projects.show', $project) }}#gantt" class="btn btn-ghost btn-xs">Gantt</a>
            </div>
        </div>
    @empty
        <div class="empty-state" style="grid-column: 1 / -1;">No projects found matching the criteria.</div>
    @endforelse
</div>

<!-- Pagination -->
<div class="table-pagination-bar">
    <div class="pagination-info">
        @if($projects->total() <= 1)
            Showing {{ $projects->total() }} of {{ $projects->total() }} project
        @else
            Showing {{ $projects->firstItem() ?? 0 }} to {{ $projects->lastItem() ?? 0 }} of {{ $projects->total() }} projects
        @endif
    </div>
    <div class="pagination-controls">
        @if($projects->onFirstPage())
            <span class="page-btn disabled">‹</span>
        @else
            <a href="{{ $projects->previousPageUrl() }}" class="page-btn">‹</a>
        @endif

        @for($i = 1; $i <= $projects->lastPage(); $i++)
            @if($i == $projects->currentPage())
                <span class="page-btn active">{{ $i }}</span>
            @elseif($i == 1 || $i == $projects->lastPage() || abs($i - $projects->currentPage()) <= 1)
                <a href="{{ $projects->url($i) }}" class="page-btn">{{ $i }}</a>
            @elseif(abs($i - $projects->currentPage()) == 2)
                <span class="page-btn-ellipsis">…</span>
            @endif
        @endfor

        @if($projects->hasMorePages())
            <a href="{{ $projects->nextPageUrl() }}" class="page-btn">›</a>
        @else
            <span class="page-btn disabled">›</span>
        @endif
    </div>
</div>

<!-- =========================================================================
     PROJECT DELAY INVESTIGATION MODAL
     Automated, Easy-to-Understand, Evidence-Based Root Cause & Schedule Investigation
     ========================================================================= -->
<div id="delay-analysis-modal" class="delay-modal-shade" hidden aria-hidden="true" role="dialog" aria-labelledby="dam-modal-title">
    <div class="delay-modal-dialog pdi-dialog">
        <!-- Modal Top Bar -->
        <div class="dam-header pdi-header">
            <div class="dam-header-left">
                <h2 id="dam-modal-title" class="dam-title" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#315F58" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <span>Project review</span>
                </h2>
                <div class="dam-subtitle">
                    <span id="dam-project-name" style="font-weight: 600; color: #1E293B;">Loading project...</span> &nbsp;·&nbsp;
                    <span id="dam-project-code" style="color: #64748B;">PRJ-XXXX</span> &nbsp;·&nbsp;
                    <span id="pdi-manager-name" style="color: #475569;">Project Manager: —</span>
                </div>
            </div>
            <div class="dam-header-right">
                <span id="dam-health-badge" class="dam-health-pill pill-delayed" hidden>Delayed</span>
                <button type="button" class="dam-close-btn" id="dam-close-btn" aria-label="Close delay investigation popup" onclick="closeDelayAnalysis()">✕</button>
            </div>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="dam-body pdi-body" id="dam-body">
            <!-- Loading State -->
            <div id="dam-loading" style="padding: 56px 16px; text-align: center;">
                <div style="font-size: 14px; color: #475569; font-weight: 500;">
                    Loading the project schedule and unfinished tasks...
                </div>
            </div>

            <!-- Content Container (Hidden during load) -->
            <div id="dam-content" style="display: none; display: flex; flex-direction: column; gap: 18px;">
                
                <!-- DELAY STORY: verdict, where it started, who is responsible, what it holds up, result -->
                <div id="pdi-story" class="pds-story" aria-live="polite"></div>

                <!-- CRITICAL PATH CARD: explicit, visual display of Critical Tasks (0d buffer) -->
                <section class="pds-critical-card" id="pds-critical-section" aria-labelledby="pds-critical-title">
                    <div class="pds-critical-head">
                        <div>
                            <h3 id="pds-critical-title" style="display: flex; align-items: center; gap: 8px;">
                                <span>⚡ Critical Path Tasks</span>
                                <span class="pds-critical-badge">0-Day Buffer</span>
                            </h3>
                            <p class="pds-critical-subtitle">These tasks directly determine your project finish date. Any delay here pushes the project deadline.</p>
                        </div>
                        <span id="pds-critical-count-pill" class="pds-critical-pill">0 Critical</span>
                    </div>
                    <div id="pdi-cpm-chain" class="pds-flow" aria-label="Critical task sequence flow"></div>
                    <p id="pdi-cpm-note" class="pds-note" hidden></p>
                    <div id="pds-critical-list" class="pds-critical-list"></div>
                </section>

                <!-- LATE TASKS: one simple list instead of two wide tables -->
                <section class="pdl" aria-labelledby="pdl-title">
                    <div class="pdl-head">
                        <div>
                            <h3 id="pdl-title">Late tasks <span class="pdl-count" id="pdl-count">0</span></h3>
                            <p>Tap a task to see who is responsible and to record the reason.</p>
                        </div>
                        <div class="pdl-filter" id="pdl-filter" role="group" aria-label="Filter late tasks">
                            <button type="button" class="is-active" data-filter="all">All</button>
                            <button type="button" data-filter="critical">⚡ Critical tasks</button>
                            <button type="button" data-filter="spare">Has buffer</button>
                        </div>
                    </div>
                    <div id="pdl-list" class="pdl-list"></div>
                    <button type="button" id="pdl-more" class="pdl-more" hidden></button>
                </section>

                <div class="pds-extras">
                    <details class="pds-explain"><summary>Read the full explanation</summary><div id="pdi-why-delayed-box" class="pdi-explanation-box"><p id="pdi-why-delayed-text" class="pdi-explanation-text">Checking the current schedule...</p></div></details>
                </div>
            </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="dam-footer pdi-footer">
            <a id="dam-open-plan-link" href="#" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; padding: 7px 16px; color: #22272B; border-color: #D0D7DE; background: #FFFFFF; text-decoration: none;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                <span>Open Project</span>
            </a>
        </div>
    </div>
</div>

<!-- Scoped CSS for Projects List & Delay Analysis Popup -->
<style>
/* Small static health indicator dots */
.health-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.health-delayed {
    background-color: #DC2626;
    box-shadow: 0 0 0 2px #FEE2E2;
}
.health-at-risk {
    background-color: #D97706;
    box-shadow: 0 0 0 2px #FEF3C7;
}

/* Subtle row styling for delayed/at-risk projects */
.projects-table tr.row-delayed td {
    background-color: #FFFDFD;
}
.projects-table tr.row-at-risk td {
    background-color: #FFFEFA;
}

.sub-health-delay {
    color: #DC2626;
    font-weight: 500;
}
.sub-health-at-risk {
    color: #D97706;
    font-weight: 500;
}
.overdue-count-pill {
    display: inline-block;
    font-size: 10px;
    font-weight: 600;
    color: #B91C1C;
    margin-top: 3px;
}
.overdue-count-pill.warning {
    color: #B45309;
}

/* Review Delay Button */
.btn-review-delay {
    color: #991B1B !important;
    border-color: #FECACA !important;
    background-color: #FEF2F2 !important;
    font-weight: 600;
}
.btn-review-delay:hover {
    background-color: #FEE2E2 !important;
    border-color: #FCA5A5 !important;
    color: #7F1D1D !important;
}

/* Modal Overlay & Container */
.delay-modal-shade {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(3px);
    z-index: 1050;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}
.delay-modal-shade[hidden] {
    display: none !important;
}
.delay-modal-dialog {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    width: 100%;
    max-width: 980px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: damFadeIn 0.15s ease-out;
}
@keyframes damFadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}
.dam-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 18px 24px;
    border-bottom: 1px solid #E2E8F0;
    background: #FFFFFF;
}
.dam-title {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #0F172A;
    letter-spacing: -0.01em;
}
.dam-subtitle {
    margin: 4px 0 0 0;
    font-size: 13px;
    color: #475569;
}
.dam-header-right {
    display: flex;
    align-items: center;
    gap: 14px;
}
.dam-health-pill {
    display: inline-flex;
    align-items: center;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 14px;
    border-radius: 9999px;
}
.dam-health-pill.pill-delayed {
    background: #FEE2E2;
    color: #DC2626;
}
.dam-health-pill.pill-warning {
    background: #FEF3C7;
    color: #D97706;
}
.dam-health-pill.pill-success {
    background: #DCFCE7;
    color: #15803D;
}
.dam-health-pill.pill-neutral {
    background: #F1F5F9;
    color: #475569;
}
.dam-close-btn {
    background: none;
    border: none;
    font-size: 16px;
    color: #64748B;
    cursor: pointer;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.12s, color 0.12s;
}
.dam-close-btn:hover {
    background: #F1F5F9;
    color: #0F172A;
}

.dam-body {
    padding: 20px 24px;
    overflow-y: auto;
    background: #F8FAFC;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* =========================================================================
   PROJECT DELAY INVESTIGATION POPUP STYLES
   Corporate Palette: #FFFFFF, #F5F6F7, #315F58, #22272B
   ========================================================================= */
.pdi-dialog {
    max-width: 1040px;
    width: 95vw;
}
.pdi-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 16px 20px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.pdi-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 8px;
}
.pdi-section-title {
    font-size: 14px;
    font-weight: 700;
    color: #22272B;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}
.pdi-title-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #315F58;
    color: #FFFFFF;
    font-size: 11px;
    font-weight: 700;
}
.pdi-micro-pill {
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    background: #F1F5F9;
    padding: 2px 8px;
    border-radius: 4px;
}
.pdi-explanation-box {
    background: #F8FAFC;
    border-left: 4px solid #315F58;
    padding: 12px 16px;
    border-radius: 0 6px 6px 0;
}
.pdi-explanation-text {
    margin: 0;
    font-size: 13.5px;
    line-height: 1.55;
    color: #1E293B;
    font-weight: 500;
}
.pdi-section-heading {
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}
.pdi-section-desc {
    font-size: 12px;
    color: #64748B;
    margin: 2px 0 0;
}
.pdi-impact-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
@media (max-width: 768px) {
    .pdi-impact-grid {
        grid-template-columns: 1fr;
    }
}
.pdi-impact-col {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px 16px;
}
.pdi-impact-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: #64748B;
}
.pdi-impact-val {
    font-size: 16px;
    font-weight: 700;
    color: #0F172A;
    margin: 4px 0 2px;
}
.pdi-impact-sub {
    font-size: 11px;
    color: #94A3B8;
}
.pdi-toggle-details-btn {
    font-size: 12px;
    color: #315F58;
    font-weight: 600;
    cursor: pointer;
    background: none;
    border: none;
}
.pdi-impact-explanation {
    font-size: 13px;
    color: #334155;
    line-height: 1.5;
    margin-top: 10px;
    padding: 8px 12px;
    background: #F1F5F9;
    border-radius: 6px;
}
.pdi-schedule-details-panel {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #E2E8F0;
}
.pdi-details-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}
@media (max-width: 768px) {
    .pdi-details-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
.pdi-detail-item {
    background: #F8FAFC;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #E2E8F0;
}
.pdi-detail-label {
    font-size: 11px;
    color: #64748B;
}
.pdi-detail-val {
    font-size: 13px;
    font-weight: 700;
    color: #0F172A;
    margin-top: 2px;
}
.pdi-count-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 10px;
}
.pdi-count-badge.badge-delayed {
    background: #FEE2E2;
    color: #991B1B;
}
.pdi-count-badge.badge-overdue {
    background: #FEF3C7;
    color: #92400E;
}
.pdi-filter-group {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.pdi-filter-btn {
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    color: #64748B;
    font-size: 11.5px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.12s;
}
.pdi-filter-btn:hover {
    border-color: #CBD5E1;
    color: #1E293B;
}
.pdi-filter-btn.active {
    background: #315F58;
    color: #FFFFFF;
    border-color: #315F58;
}
.pdi-table-wrap {
    margin-top: 8px;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    overflow-x: auto;
}
.pdi-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
}
.pdi-table th {
    background: #F8FAFC;
    color: #475569;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 8px 10px;
    border-bottom: 1px solid #E2E8F0;
    text-align: left;
}
.pdi-table td {
    padding: 10px;
    border-bottom: 1px solid #F1F5F9;
    vertical-align: middle;
}
.pdi-empty-row {
    text-align: center;
    padding: 28px 16px !important;
    color: #64748B;
    font-size: 13px;
}
.pdi-task-row {
    cursor: pointer;
    transition: background 0.1s;
}
.pdi-task-row:hover {
    background: #F8FAFC;
}
.pdi-task-row.is-expanded {
    background: #F1F5F9;
}
.pdi-impact-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}
.pdi-impact-badge.impact-critical {
    background: #FEE2E2;
    color: #991B1B;
    border: 1px solid #FCA5A5;
}
.pdi-impact-badge.impact-warning {
    background: #FEF3C7;
    color: #92400E;
    border: 1px solid #FDE68A;
}
.pdi-impact-badge.impact-neutral {
    background: #F1F5F9;
    color: #475569;
    border: 1px solid #E2E8F0;
}
.pdi-drawer-row {
    background: #FFFFFF;
}
.pdi-drawer-cell {
    padding: 14px 18px !important;
    border-bottom: 2px solid #E2E8F0;
    background: #FFFFFF;
}
.pdi-drawer-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
@media (max-width: 860px) {
    .pdi-drawer-grid {
        grid-template-columns: 1fr;
    }
}
.pdi-drawer-card {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    padding: 10px 14px;
}
.pdi-drawer-card-label {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748B;
    margin-bottom: 4px;
}
.pdi-drawer-card-val {
    font-size: 12.5px;
    color: #0F172A;
    line-height: 1.4;
}
.dam-cpm-node.node-delayed {
    background: #FEE2E2 !important;
    color: #991B1B !important;
    border: 1px solid #FCA5A5 !important;
}
.dam-cpm-node.node-risk {
    background: #FEF3C7 !important;
    color: #92400E !important;
    border: 1px solid #FDE68A !important;
}
.dam-cpm-node.node-ok {
    background: #E8F5E9 !important;
    color: #166534 !important;
    border: 1px solid #BBF7D0 !important;
}
.dam-cpm-node.node-completed {
    background: #F1F5F9 !important;
    color: #475569 !important;
    border: 1px solid #E2E8F0 !important;
}

/* Diagnosis Banner */
.dam-diagnosis-banner {
    background: #FEF3C7;
    border: 1px solid #FDE68A;
    border-radius: 8px;
    padding: 10px 16px;
    font-size: 12px;
    color: #92400E;
    display: flex;
    align-items: center;
    gap: 10px;
    line-height: 1.4;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.dam-diagnosis-banner.banner-delayed {
    background: #FEE2E2;
    border-color: #FCA5A5;
    color: #991B1B;
}

/* Critical Path Flow Box */
.dam-cpm-box {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 16px 20px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.dam-cpm-title {
    font-size: 14px;
    font-weight: 700;
    color: #0F172A;
}
.dam-cpm-subtitle {
    font-size: 12px;
    color: #64748B;
    margin-top: 2px;
}
.dam-cpm-flow {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
    overflow-x: auto;
    padding-bottom: 4px;
}
.dam-cpm-node {
    padding: 7px 18px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
    text-align: center;
}
.dam-cpm-node.node-ok {
    background: #E8F5E9;
    color: #1B5E20;
    border: 1px solid #C8E6C9;
}
.dam-cpm-node.node-delayed {
    background: #FEE2E2;
    color: #B91C1C;
    border: 1px solid #FCA5A5;
}
.dam-cpm-arrow {
    color: #94A3B8;
    font-size: 16px;
    font-weight: 700;
    flex-shrink: 0;
}
.dam-notice-box {
    background: #FFFBEB;
    border: 1px solid #FDE68A;
    color: #92400E;
    padding: 10px 14px;
    border-radius: 6px;
    font-size: 12px;
}

/* Tabs Bar */
.dam-tabs-bar {
    display: flex;
    gap: 24px;
    border-bottom: 1px solid #E2E8F0;
    padding-left: 4px;
}
.dam-tab-btn {
    border: none;
    background: none;
    font-size: 13px;
    font-weight: 600;
    color: #64748B;
    padding: 10px 4px 12px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: color 0.12s, border-color 0.12s;
}
.dam-tab-btn:hover {
    color: #0F172A;
}
.dam-tab-btn.active {
    color: #0F172A;
    border-bottom-color: #315F58;
}

/* Table Card */
.dam-table-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.dam-table {
    font-size: 12px;
    width: 100%;
}
.dam-table thead tr {
    background: #F8FAFC;
    border-bottom: 1px solid #E2E8F0;
}
.dam-table th {
    padding: 10px 14px;
    font-size: 12px;
    font-weight: 600;
    color: #64748B;
}
.dam-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #F1F5F9;
    vertical-align: middle;
}
.dam-task-row {
    cursor: pointer;
    transition: background 0.12s;
}
.dam-task-row:hover {
    background: #F8FAFC;
}
.dam-task-row.is-expanded {
    background: #F8FAFC;
}
.dam-task-title {
    font-weight: 700;
    color: #0F172A;
    font-size: 13px;
}
.dam-assignee-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}
.dam-avatar-badge {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #D1E7DD;
    color: #0F5132;
    font-size: 10px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.dam-delay-pill {
    display: inline-flex;
    align-items: center;
    background: #FEE2E2;
    color: #DC2626;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 9999px;
}
.dam-delay-pill.pill-zero {
    background: #F1F5F9;
    color: #64748B;
}
.dam-status-pill {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 9999px;
    white-space: nowrap;
}
.dam-status-pill.status-in_progress {
    background: #E0F2FE;
    color: #0369A1;
}
.dam-status-pill.status-todo {
    background: #F1F5F9;
    color: #475569;
}
.dam-status-pill.status-completed {
    background: #DCFCE7;
    color: #15803D;
}
.dam-status-pill.status-on_hold {
    background: #FEF3C7;
    color: #B45309;
}
.dam-chevron-btn {
    border: none;
    background: none;
    color: #64748B;
    font-size: 14px;
    cursor: pointer;
    padding: 4px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.15s;
}

/* Expanded Accordion Drawer Row */
.dam-drawer-cell {
    padding: 0 !important;
    background: #F8FAFC;
    border-bottom: 1px solid #E2E8F0 !important;
}
.dam-drawer-box {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 14px 18px;
    margin: 4px 12px 14px 12px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.dam-drawer-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 14px;
}
@media (max-width: 860px) {
    .dam-drawer-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
.dam-drawer-col {
    display: flex;
    flex-direction: column;
}
.dam-drawer-col-head {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    color: #64748B;
    margin-bottom: 4px;
}
.dam-drawer-col-val {
    font-size: 12px;
    color: #0F172A;
    line-height: 1.4;
    word-break: break-word;
}
.dam-res-pill {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 9999px;
    width: fit-content;
}
.dam-res-pill.res-in-progress, .dam-res-pill.res-open {
    background: #FEF3C7;
    color: #D97706;
}
.dam-res-pill.res-resolved {
    background: #DCFCE7;
    color: #15803D;
}
.dam-res-pill.res-unreported {
    background: #FEE2E2;
    color: #DC2626;
}

.dam-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    padding: 14px 24px;
    border-top: 1px solid #E2E8F0;
    background: #FFFFFF;
}

/* Full row & card clickability */
tr.clickable-project-row {
    cursor: pointer;
    transition: background-color 0.12s ease;
}
tr.clickable-project-row:hover td {
    background-color: #F8FAFB !important;
}
tr.clickable-project-row:hover .strong-link b {
    color: var(--primary) !important;
}
.project-grid-card.clickable-project-card {
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.project-grid-card.clickable-project-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
</style>

@push('scripts')
<script>
// Navigate inside project when clicking anywhere on the project row or card
document.addEventListener('click', function(e) {
    const clickableItem = e.target.closest('.clickable-project-row, .clickable-project-card');
    if (!clickableItem) return;

    // Do not intercept if clicked on interactive elements (buttons, links, inputs, dropdown menus)
    if (e.target.closest('button, a, input, select, textarea, .actions-dropdown-panel, .btn-dots, .btn-review-delay, [data-show-dialog]')) {
        return;
    }

    // Do not navigate if user is highlighting / selecting text
    const selection = window.getSelection();
    if (selection && selection.toString().trim().length > 0) {
        return;
    }

    const href = clickableItem.dataset.href;
    if (href) {
        window.location.href = href;
    }
});
// Search debounce
(function(){
    const form = document.getElementById('projects-filter-form');
    const input = document.getElementById('projects-search-input');
    if(!form || !input) return;

    let debounceTimer;
    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            form.submit();
        }, 500);
    });
})();

// Switch between List and Grid view
function switchProjectsView(view) {
    const listContainer = document.getElementById('projects-list-container');
    const gridContainer = document.getElementById('projects-grid-container');
    const listBtn = document.getElementById('view-btn-list');
    const gridBtn = document.getElementById('view-btn-grid');

    listBtn.setAttribute('aria-pressed', String(view !== 'grid'));
    gridBtn.setAttribute('aria-pressed', String(view === 'grid'));
    if (view === 'grid') {
        listContainer.style.display = 'none';
        gridContainer.style.display = 'grid';
        listBtn.classList.remove('active');
        gridBtn.classList.add('active');
        localStorage.setItem('gs_projects_view', 'grid');
    } else {
        listContainer.style.display = 'block';
        gridContainer.style.display = 'none';
        listBtn.classList.add('active');
        gridBtn.classList.remove('active');
        localStorage.setItem('gs_projects_view', 'list');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const savedView = localStorage.getItem('gs_projects_view');
    if (savedView === 'grid') {
        switchProjectsView('grid');
    }
});

// Dropdown menu toggle
let projectLibraryMenuOpener = null;
function closeProjectLibraryMenus() {
    document.querySelectorAll('.actions-dropdown-panel').forEach(panel => panel.classList.remove('show'));
    document.querySelectorAll('.btn-dots').forEach(button => button.setAttribute('aria-expanded', 'false'));
}
function toggleProjectMenu(event, menuId) {
    event.stopPropagation();
    const panel = document.getElementById(menuId);
    if (!panel) return;
    const wasOpen = panel.classList.contains('show');
    closeProjectLibraryMenus();
    if (wasOpen) return;
    projectLibraryMenuOpener = event.currentTarget;
    panel.classList.add('show');
    projectLibraryMenuOpener.setAttribute('aria-expanded', 'true');
    const rect = projectLibraryMenuOpener.getBoundingClientRect();
    panel.style.position = 'fixed';
    panel.style.right = Math.max(12, window.innerWidth - rect.right) + 'px';
    panel.style.left = 'auto';
    panel.style.bottom = 'auto';
    panel.style.top = Math.max(12, Math.min(rect.bottom + 6, window.innerHeight - panel.offsetHeight - 12)) + 'px';
}
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && document.querySelector('.actions-dropdown-panel.show')) {
        closeProjectLibraryMenus();
        projectLibraryMenuOpener?.focus();
    }
});
window.addEventListener('resize', closeProjectLibraryMenus);
document.addEventListener('scroll', closeProjectLibraryMenus, true);

document.addEventListener('click', (e) => {
    if(!e.target.closest('.table-actions-cell')) {
        document.querySelectorAll('.actions-dropdown-panel').forEach(p => p.classList.remove('show'));
    }
});

/* =========================================================================
   PROJECT DELAY ANALYSIS MODAL JAVASCRIPT
   1-Click Async Loader & Dynamic Popup Renderer
   ========================================================================= */
let currentAnalysisProjectId = null;

function openDelayAnalysis(projectId) {
    currentAnalysisProjectId = projectId;
    const modal = document.getElementById('delay-analysis-modal');
    const loading = document.getElementById('dam-loading');
    const content = document.getElementById('dam-content');

    // Show modal & set loading state
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    loading.style.display = 'block';
    content.style.display = 'none';

    // Fetch live delay analysis from dedicated authorized endpoint
    fetch(`/projects/${projectId}/delay-analysis`, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        }
    })
    .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}: Failed to load delay analysis`);
        return res.json();
    })
    .then(data => {
        populateDelayModal(data);
        loading.style.display = 'none';
        content.style.display = 'block';
        document.getElementById('dam-close-btn').focus();
    })
    .catch(err => {
        loading.innerHTML = `
            <div style="color: #DC2626; padding: 24px 0;">
                <b>Failed to load delay analysis</b>
                <p style="margin-top: 6px; font-size: 13px;">${err.message || 'An error occurred while loading project schedule data.'}</p>
                <button type="button" class="btn btn-outline btn-xs" onclick="closeDelayAnalysis()">Close</button>
            </div>
        `;
    });
}

function closeDelayAnalysis() {
    const modal = document.getElementById('delay-analysis-modal');
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

// Close on Escape or click on backdrop
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('delay-analysis-modal');
        if (modal && !modal.hidden) {
            closeDelayAnalysis();
        }
    }
});

document.addEventListener('click', (e) => {
    const modal = document.getElementById('delay-analysis-modal');
    if (e.target === modal) {
        closeDelayAnalysis();
    }
});

let currentInvestigationData = null;


/* =========================================================================
   DELAY STORY RENDERER
   Explains in plain language: is the project late, where the delay started,
   who is responsible, what it is holding up and what it means for the deadline.
   Uses only recorded facts - it never guesses a cause.
   ========================================================================= */
function renderDelayStory(res) {
    const inv = res.investigation || {};
    const proj = res.project || {};
    const summary = res.summary || {};
    const cpm = res.critical_path || {};
    const header = inv.header || {};
    const state = header.health_state || res.health?.key || 'on_track';
    const planned = summary.approved_deadline || proj.approved_deadline || '—';
    const estimated = summary.forecast_deadline || inv.section_b?.forecast_completion || planned;
    const lateDays = Number(inv.section_b?.variance_working_days || 0);

    const days = n => n + ' working ' + (Number(n) === 1 ? 'day' : 'days');
    const el = (tag, cls, text) => {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    };

    document.getElementById('pdi-why-delayed-text').textContent =
        inv.section_a?.explanation || summary.diagnostic_summary || 'No schedule explanation is available yet.';

    const root = document.getElementById('pdi-story');
    root.replaceChildren();

    // ---- Verdict -------------------------------------------------------
    const verdicts = {
        delayed: ['bad', 'Past the ' + planned + ' deadline', days(lateDays) + ' overdue and the project is not finished yet'],
        forecast_late: ['bad', 'Will miss the ' + planned + ' deadline', 'Now expected to finish ' + estimated + ' — ' + days(lateDays) + ' late'],
        at_risk: ['warn', 'At risk of falling behind', 'Some tasks are late, but the ' + planned + ' deadline still holds for now'],
        on_track: ['ok', 'On schedule', 'Expected to finish by ' + planned],
        completed: ['ok', 'Project completed', 'All work is finished.'],
    };
    const verdict = verdicts[state] || ['neutral', header.health_badge || 'Not enough schedule data', inv.section_a?.explanation || ''];
    const tone = verdict[0];

    const verdictBox = el('div', 'pds-verdict is-' + tone);
    verdictBox.append(el('span', 'pds-dot'));
    const verdictText = el('div', 'pds-verdict-text');
    verdictText.append(el('h3', '', verdict[1]));
    if (verdict[2]) verdictText.append(el('p', '', verdict[2]));

    // Quick stats strip (Claude / Linear style)
    const statsStrip = el('div', 'pds-stat-strip');
    const lateCount = (inv.section_d?.tasks || []).length || (res.delayed_section?.count || 0) || (inv.section_e?.tasks || []).length || 0;
    const critCount = cpm.total_count || cpm.critical_tasks?.length || (cpm.sequence || []).filter(s => s.is_critical).length || 0;
    const addFilterPill = (filter, cls, label) => {
        const pill = el('button', 'pds-stat-pill ' + cls, label);
        pill.type = 'button';
        pill.dataset.reviewFilter = filter;
        pill.setAttribute('aria-pressed', 'false');
        pill.addEventListener('click', () => applyReviewSummaryFilter(filter));
        statsStrip.append(pill);
    };
    if (lateCount > 0) {
        addFilterPill('all', 'is-late', '🔴 ' + lateCount + ' late ' + (lateCount === 1 ? 'task' : 'tasks'));
    }
    if (critCount > 0) {
        addFilterPill('critical', 'is-crit', '⚡ ' + critCount + ' critical (0d buffer)');
    }
    if (planned && planned !== '—') {
        const dateText = estimated && estimated !== planned ? planned + ' → ' + estimated : planned;
        statsStrip.append(el('span', 'pds-stat-pill is-date', '📅 ' + dateText));
    }
    verdictText.append(statsStrip);
    verdictBox.append(verdictText);
    root.append(verdictBox);

    if (tone === 'ok' || tone === 'neutral') return;

    // ---- Find where the delay started -----------------------------------
    const delayed = inv.section_d?.tasks || res.delayed_section?.tasks || [];
    const overdue = inv.section_e?.tasks || res.overdue_section?.tasks || [];
    const pool = new Map();
    [...overdue, ...delayed].forEach(t => pool.set(t.id, t));

    let focus =
        delayed.find(t => t.is_critical && t.is_overdue && !t.is_completed) ||
        delayed.find(t => t.is_critical && !t.is_completed) ||
        overdue.find(t => t.is_critical) ||
        delayed.find(t => t.has_delay_report && !t.is_completed) ||
        overdue[0] ||
        delayed.find(t => !t.is_completed) ||
        null;

    // If that task is itself waiting on a late task, the real start is further up the chain.
    const seen = new Set();
    while (focus && !seen.has(focus.id)) {
        seen.add(focus.id);
        const upstream = (focus.blocking_predecessors || []).find(p => p.is_overdue && pool.has(p.id) && !seen.has(p.id));
        if (!upstream) break;
        focus = pool.get(upstream.id);
    }

    if (!focus) {
        root.append(el('p', 'pds-note', 'No single task has been identified as the cause. Open the late-task list under “Full schedule details” to review the task dates.'));
        return;
    }

    const projectUrl = new URL(proj.show_url || header.open_project_url || '#', location.origin);
    const taskUrl = new URL(projectUrl.href);
    taskUrl.searchParams.set('focus_task', focus.id);
    taskUrl.hash = 'tasks';

    const steps = el('div', 'pds-steps');
    const makeStep = (num, title) => {
        const section = el('section', 'pds-step');
        const head = el('div', 'pds-step-head');
        head.append(el('span', 'pds-step-num', num), el('h4', '', title));
        section.append(head);
        return section;
    };

    // ---- 1. Where it started + who is responsible ------------------------
    const s1 = makeStep('1', 'Where it started');
    s1.append(el('strong', 'pds-task', (focus.number ? focus.number + ' · ' : '') + focus.title));
    let when;
    if (focus.is_overdue) when = days(focus.delay_days) + ' late · was due ' + focus.planned_end;
    else if (Number(focus.delay_days) > 0 && focus.forecast_end && focus.forecast_end !== focus.planned_end) when = 'Now expected ' + focus.forecast_end + ' instead of ' + focus.planned_end + ' (' + days(focus.delay_days) + ' late)';
    else when = 'Due ' + focus.planned_end;
    s1.append(el('p', 'pds-sub', when + ' · ' + focus.progress + '% done'));

    const waiting = (focus.blocking_predecessors || []).map(p => p.title);
    if (waiting.length) s1.append(el('p', 'pds-sub', 'Waiting for: ' + waiting.slice(0, 2).join(', ') + (waiting.length > 2 ? ' +' + (waiting.length - 2) + ' more' : '')));

    const who = el('dl', 'pds-who');
    const addRow = (label, value, cls) => {
        const row = el('div', cls || '');
        row.append(el('dt', '', label), el('dd', '', value));
        who.append(row);
    };
    const unassigned = !focus.assignees_text || focus.assignees_text === 'Not assigned';
    addRow('Task owner', unassigned ? 'Nobody is assigned to this task' : focus.assignees_text, unassigned ? 'is-warn' : '');
    if (unassigned || (focus.confirmed_action_owner && focus.confirmed_action_owner !== focus.assignees_text)) {
        const manager = proj.manager_name && proj.manager_name !== 'Not assigned' ? proj.manager_name + ' (Project Manager)' : 'Project Manager';
        addRow('Follow up with', focus.confirmed_action_owner || manager);
    }
    if (focus.has_delay_report) {
        addRow('Reason given', focus.reported_root_cause || 'Reported');
        if (focus.involved_party && focus.involved_party !== 'Not recorded') addRow('Responsible party', focus.involved_party);
        if (focus.confirmed_corrective_action) addRow('Plan to fix', focus.confirmed_corrective_action);
        if (focus.resolution_date && focus.resolution_date !== '—') addRow('New date promised', focus.resolution_date);
        if (focus.reported_by_name) addRow('Reported by', focus.reported_by_name + (focus.reported_on ? ' · ' + focus.reported_on : ''));
    } else {
        addRow('Reason given', 'Nobody has explained this delay yet', 'is-warn');
    }
    s1.append(who);
    steps.append(s1);

    if (!cpm.reliable) {
        steps.append(el('p', 'pds-note', 'The task links are incomplete, so the system cannot be sure which task is moving the finish date. Check the task dates and dependencies.'));
    } else {
        // ---- 2. What it is holding up ----------------------------------
        const s2 = makeStep('2', 'What it is holding up');
        const successors = focus.blocked_successors || [];
        if (successors.length) {
            const chain = el('div', 'pds-chain');
            successors.slice(0, 4).forEach((task, index) => {
                if (index) chain.append(el('span', 'pds-arrow', '→'));
                chain.append(el('span', 'pds-chip', task.title));
            });
            if (successors.length > 4) chain.append(el('span', 'pds-chip is-more', '+' + (successors.length - 4) + ' more'));
            s2.append(chain);
            s2.append(el('p', 'pds-sub', successors.length + ' unfinished ' + (successors.length === 1 ? 'task cannot' : 'tasks cannot') + ' finish on time until this is done.'));
        } else {
            s2.append(el('p', 'pds-sub', 'No other unfinished task depends on it.'));
        }
        steps.append(s2);

        // ---- 3. What it means for the deadline -------------------------
        const s3 = makeStep('3', 'What it means for the deadline');
        let result;
        if (focus.is_critical) {
            result = 'It has no spare time, so every day it slips moves the project finish date' + (tone === 'bad' && estimated !== planned ? ' (' + planned + ' → ' + estimated + ').' : '.');
        } else if (focus.critical_impact === 'affects_deadline') {
            result = 'This delay is longer than the spare time the task had, so the project finish date moves.';
        } else {
            result = 'It still has ' + (focus.float_text || 'some') + ' of spare time, so the deadline is not affected yet. It will be if the task slips further.';
        }
        s3.append(el('p', 'pds-result', result));
        steps.append(s3);
    }
    root.append(steps);

    // ---- Other tasks also pushing the date -------------------------------
    const downstreamIds = new Set((focus.blocked_successors || []).map(t => t.id));
    const others = delayed.filter(t => !t.is_completed && t.id !== focus.id && !seen.has(t.id) && !downstreamIds.has(t.id) && t.critical_impact === 'affects_deadline').slice(0, 3);
    if (others.length) {
        const box = el('div', 'pds-others');
        box.append(el('h4', '', 'Also pushing the date'));
        others.forEach(task => {
            const link = new URL(projectUrl.href);
            link.searchParams.set('focus_task', task.id);
            link.hash = 'tasks';
            const row = el('a', 'pds-other');
            row.href = link.href;
            row.append(el('span', 'pds-other-title', task.title));
            row.append(el('span', 'pds-other-meta', (task.assignees_text && task.assignees_text !== 'Not assigned' ? task.assignees_text : 'Nobody assigned') + ' · ' + (task.delay_badge || '')));
            box.append(row);
        });
        root.append(box);
    }

    // ---- Action ----------------------------------------------------------
    const actions = el('div', 'pds-actions');
    const open = el('a', 'btn btn-primary', 'Open this task →');
    open.href = taskUrl.href;
    actions.append(open);
    root.append(actions);
}

function populateDelayModal(res) {
    currentInvestigationData = res;

    const proj = res.project || {};
    const health = res.health || {};
    const summary = res.summary || {};
    const cpm = res.critical_path || {};
    const overdue = res.overdue_section || {};
    const delayed = res.delayed_section || {};
    const inv = res.investigation || {};

    // Header info
    document.getElementById('dam-project-name').textContent = proj.name || 'Project';
    document.getElementById('dam-project-code').textContent = proj.code || 'PRJ-XXXX';
    document.getElementById('pdi-manager-name').textContent = 'Project Manager: ' + (proj.manager_name || 'Not assigned');
    document.getElementById('dam-open-plan-link').href = proj.show_url || '#';

    // Health Badge in header
    const healthBadge = document.getElementById('dam-health-badge');
    const badgeLabel = inv.header?.health_badge || health.label || 'On Track';
    healthBadge.textContent = ({'Forecast Late':'May finish late','Delayed':'Behind schedule','At Risk':'Needs attention','On Track':'On schedule'})[badgeLabel] || badgeLabel;
    healthBadge.className = 'dam-health-pill ' + (
        (health.is_delayed || inv.header?.health_state === 'delayed' || inv.header?.health_state === 'forecast_late') ? 'pill-delayed' :
        (health.is_at_risk || inv.header?.health_state === 'at_risk') ? 'pill-warning' :
        'pill-success'
    );

    // Delay story: verdict -> where it started -> who is responsible -> what it holds up -> result
    renderDelayStory(res);


    // Late tasks (one list) and the wrapped chain of linked tasks
    renderLateTasks(res);
    renderLinkedChain(res);
}

/* =========================================================================
   LATE TASKS
   One compact list that replaces the old wide "Delayed" and "Overdue"
   tables. Nothing scrolls sideways; tap a task to see who is responsible
   and to record the reason right there.
   ========================================================================= */
const LATE_PREVIEW_COUNT = 6;
let lateProjectId = null;
let lateFilter = 'all';
let lateShowAll = false;
let lateHandlersBound = false;
const openLateIds = new Set();

function applyReviewSummaryFilter(filter) {
    lateFilter = filter;
    lateShowAll = true;
    if (currentInvestigationData) renderLateTasks(currentInvestigationData);

    const lateSection = document.querySelector('#delay-analysis-modal .pdl');
    lateSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function pdEl(tag, cls, text) {
    const node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function collectLateTasks(res) {
    const inv = res.investigation || {};
    const merged = new Map();
    const sources = [
        inv.section_e?.tasks || res.overdue_section?.tasks || [],
        inv.section_d?.tasks || res.delayed_section?.tasks || [],
    ];
    sources.forEach(list => list.forEach(t => {
        if (!t.is_completed && !merged.has(t.id)) merged.set(t.id, t);
    }));
    return [...merged.values()].sort((a, b) => {
        const aw = a.critical_impact === 'affects_deadline' ? 1 : 0;
        const bw = b.critical_impact === 'affects_deadline' ? 1 : 0;
        if (aw !== bw) return bw - aw;
        return (Number(b.delay_days) || 0) - (Number(a.delay_days) || 0);
    });
}

function renderLateTasks(res) {
    const list = document.getElementById('pdl-list');
    if (!list) return;

    const projectId = res.project?.id || null;
    if (lateProjectId !== projectId) {
        lateProjectId = projectId;
        lateFilter = 'all';
        lateShowAll = false;
        openLateIds.clear();
    }

    if (!lateHandlersBound) {
        lateHandlersBound = true;
        document.getElementById('pdl-filter').addEventListener('click', event => {
            const button = event.target.closest('button[data-filter]');
            if (!button) return;
            lateFilter = button.dataset.filter;
            lateShowAll = false;
            if (currentInvestigationData) renderLateTasks(currentInvestigationData);
        });
        document.getElementById('pdl-more').addEventListener('click', () => {
            lateShowAll = !lateShowAll;
            if (currentInvestigationData) renderLateTasks(currentInvestigationData);
        });
    }

    const all = collectLateTasks(res);
    const deadlineOnes = all.filter(t => t.critical_impact === 'affects_deadline' || t.is_critical);
    const spareOnes = all.filter(t => t.critical_impact !== 'affects_deadline' && !t.is_critical);

    document.getElementById('pdl-count').textContent = all.length;
    document.querySelectorAll('#pdl-filter button').forEach(button => {
        const f = button.dataset.filter;
        const isActive = (f === lateFilter) || (lateFilter === 'deadline' && f === 'critical') || (lateFilter === 'critical' && f === 'deadline');
        button.classList.toggle('is-active', isActive);
        if (f === 'all') {
            button.textContent = 'All (' + all.length + ')';
        } else if (f === 'critical' || f === 'deadline') {
            button.textContent = '⚡ Critical tasks (' + deadlineOnes.length + ')';
            button.hidden = (deadlineOnes.length === 0);
        } else if (f === 'spare') {
            button.textContent = 'Has buffer (' + spareOnes.length + ')';
            button.hidden = (spareOnes.length === 0);
        }
    });
    document.querySelectorAll('[data-review-filter]').forEach(button => {
        const isActive = button.dataset.reviewFilter === lateFilter;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
    const filterBox = document.getElementById('pdl-filter');
    if (filterBox) {
        filterBox.hidden = (all.length === 0);
    }

    let visibleAll = all;
    if (lateFilter === 'deadline' || lateFilter === 'critical') {
        visibleAll = deadlineOnes;
    } else if (lateFilter === 'spare') {
        visibleAll = spareOnes;
    }

    const shown = lateShowAll ? visibleAll : visibleAll.slice(0, LATE_PREVIEW_COUNT);

    list.replaceChildren();
    if (!visibleAll.length) {
        list.append(pdEl('p', 'pdl-empty', '✓ No tasks in this category.'));
    } else {
        shown.forEach(task => list.append(buildLateRow(task, res)));
    }

    const more = document.getElementById('pdl-more');
    const hiddenCount = visibleAll.length - LATE_PREVIEW_COUNT;
    more.hidden = hiddenCount <= 0;
    more.textContent = lateShowAll ? 'Show fewer' : 'Show ' + hiddenCount + ' more';
}

function buildLateRow(t, res) {
    const open = openLateIds.has(t.id);
    const affects = t.critical_impact === 'affects_deadline' || t.is_critical;
    const row = pdEl('article', 'pdl-row' + (open ? ' is-open' : ''));
    row.id = 'task-row-' + t.id;

    const head = pdEl('button', 'pdl-row-head');
    head.type = 'button';
    head.setAttribute('aria-expanded', open ? 'true' : 'false');

    head.append(pdEl('span', 'pdl-num', t.number || t.code));
    const main = pdEl('span', 'pdl-main');
    main.append(pdEl('strong', '', t.title));
    const unassigned = !t.assignees_text || t.assignees_text === 'Not assigned';
    const dueInfo = (unassigned ? '⚠️ Nobody assigned' : t.assignees_text) + ' · due ' + t.planned_end + (t.forecast_end && t.forecast_end !== t.planned_end ? ' (forecast ' + t.forecast_end + ')' : '');
    main.append(pdEl('small', unassigned ? 'is-warn' : '', dueInfo));
    head.append(main);

    const side = pdEl('span', 'pdl-side');
    side.append(pdEl('span', 'pdl-pill', t.delay_badge || ('+' + t.delay_days + ' days')));
    side.append(pdEl('span', 'pdl-chip ' + (affects ? 'is-deadline' : 'is-spare'), affects ? '⚡ Critical (0d buffer)' : ('🌿 Buffer: ' + (t.float_text || 'spare'))));
    side.append(pdEl('span', 'pdl-caret'));
    head.append(side);

    const body = pdEl('div', 'pdl-body');
    body.hidden = !open;
    body.append(buildLateBody(t, res));

    head.addEventListener('click', () => {
        const nowOpen = body.hidden;
        body.hidden = !nowOpen;
        row.classList.toggle('is-open', nowOpen);
        head.setAttribute('aria-expanded', nowOpen ? 'true' : 'false');
        if (nowOpen) openLateIds.add(t.id); else openLateIds.delete(t.id);
    });

    row.append(head, body);
    return row;
}

function buildLateBody(t, res) {
    const wrap = pdEl('div', 'pdl-detail');
    const facts = pdEl('dl', 'pds-who');
    const add = (label, value, cls) => {
        const item = pdEl('div', cls || '');
        item.append(pdEl('dt', '', label), pdEl('dd', '', value));
        facts.append(item);
    };

    const unassigned = !t.assignees_text || t.assignees_text === 'Not assigned';
    add('Task owner', unassigned ? 'Nobody is assigned' : t.assignees_text, unassigned ? 'is-warn' : '');
    add('Progress', (t.status_label || t.status) + ' · ' + t.progress + '%');
    add('Finish date', t.forecast_end && t.forecast_end !== t.planned_end ? t.planned_end + ' → now ' + t.forecast_end : t.planned_end);

    const waiting = (t.blocking_predecessors || []).map(p => p.title);
    if (waiting.length) add('Waiting for', waiting.slice(0, 2).join(', ') + (waiting.length > 2 ? ' +' + (waiting.length - 2) + ' more' : ''), 'is-wide');
    const holding = (t.blocked_successors || []).map(s => s.title);
    if (holding.length) add('Holding up', holding.slice(0, 3).join(', ') + (holding.length > 3 ? ' +' + (holding.length - 3) + ' more' : ''), 'is-wide');

    if (t.has_delay_report) {
        add('Reason given', t.reported_root_cause || 'Reported', 'is-wide');
        if (t.involved_party && t.involved_party !== 'Not recorded') add('Responsible party', t.involved_party);
        if (t.confirmed_action_owner) add('Follow up with', t.confirmed_action_owner);
        if (t.confirmed_corrective_action) add('Plan to fix', t.confirmed_corrective_action, 'is-wide');
        if (t.resolution_date && t.resolution_date !== '—') add('New date promised', t.resolution_date);
        if (t.reported_by_name) add('Reported by', t.reported_by_name + (t.reported_on ? ' · ' + t.reported_on : ''));
    } else {
        add('Reason given', 'Nobody has explained this delay yet', 'is-warn is-wide');
    }
    wrap.append(facts);

    const actions = pdEl('div', 'pdl-actions');
    let formBox = null;
    if (t.can_add_reason) {
        const toggle = pdEl('button', 'btn ' + (t.has_delay_report ? 'btn-outline' : 'btn-primary') + ' btn-sm', t.has_delay_report ? 'Edit the reason' : 'Add the reason');
        toggle.type = 'button';
        toggle.addEventListener('click', () => {
            if (!formBox) {
                formBox = buildReasonForm(t, res, () => { formBox.hidden = true; });
                wrap.append(formBox);
            } else {
                formBox.hidden = !formBox.hidden;
            }
            if (!formBox.hidden) formBox.querySelector('select')?.focus();
        });
        actions.append(toggle);
    }
    if (t.has_delay_report && t.can_resolve_delay && t.resolution_status !== 'Resolved' && t.resolve_delay_url) {
        const resolve = pdEl('button', 'btn btn-outline btn-sm pdl-resolve', '✓ Mark resolved');
        resolve.type = 'button';
        resolve.addEventListener('click', event => resolveDelayReport(t.id, t.resolve_delay_url, event));
        actions.append(resolve);
    }
    const project = new URL(res.project?.show_url || '#', location.origin);
    project.searchParams.set('focus_task', t.id);
    project.hash = 'tasks';
    const link = pdEl('a', 'pdl-link', 'Open task →');
    link.href = project.href;
    actions.append(link);
    wrap.append(actions);
    return wrap;
}

function buildReasonForm(t, res, onCancel) {
    const raw = t.open_delay_raw || {};
    const box = pdEl('form', 'pdl-form');
    box.noValidate = false;

    const field = (label, control, cls) => {
        const wrapper = pdEl('label', 'pdl-field ' + (cls || ''));
        wrapper.append(pdEl('span', '', label), control);
        return wrapper;
    };

    const reason = pdEl('input');
    reason.type = 'text';
    reason.name = 'reason_text';
    reason.required = true;
    reason.maxLength = 500;
    reason.placeholder = 'Type the actual reason for the delay';
    reason.value = raw.reason_text || '';

    const partyName = pdEl('input');
    partyName.type = 'text';
    partyName.name = 'responsible_party_name';
    partyName.required = true;
    partyName.maxLength = 160;
    partyName.placeholder = 'Type the team, person, department or vendor';
    partyName.value = raw.responsible_party_name || '';

    const partyType = pdEl('input');
    partyType.type = 'hidden';
    partyType.name = 'responsible_party_type';
    partyType.value = raw.responsible_party_type || 'unknown';

    const owner = pdEl('select');
    owner.name = 'action_owner_id';
    owner.add(new Option('Choose who follows up…', ''));
    (res.project_members || []).forEach(member => owner.add(new Option(member.name, member.id, false, Number(raw.action_owner_id) === Number(member.id))));

    const plan = pdEl('textarea');
    plan.name = 'details';
    plan.rows = 2;
    plan.placeholder = 'What will be done to catch up? (optional)';
    plan.value = raw.details || '';

    const date = pdEl('input');
    date.type = 'datetime-local';
    date.name = 'revised_expected_end_at';
    date.value = raw.revised_expected_end_at || '';

    box.append(
        field('What is the main reason?', reason, 'is-wide'),
        field('Whose side is it on?', partyName, 'is-wide'),
        field('Who follows up?', owner),
        field('New finish date', date),
        field('Plan to catch up', plan, 'is-wide'),
        partyType
    );

    const footer = pdEl('div', 'pdl-form-actions');
    const cancel = pdEl('button', 'btn btn-ghost btn-sm', 'Cancel');
    cancel.type = 'button';
    cancel.addEventListener('click', onCancel);
    const save = pdEl('button', 'btn btn-primary btn-sm', 'Save');
    save.type = 'submit';
    footer.append(cancel, save);
    box.append(footer);

    box.addEventListener('submit', event => {
        event.preventDefault();
        save.disabled = true;
        save.textContent = 'Saving…';
        fetch(t.store_delay_url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            body: new FormData(box),
        })
        .then(async response => {
            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                let message = error.message || (response.status === 403
                    ? 'You do not have permission to add a delay reason for this task.'
                    : 'The reason could not be saved.');
                if (error.errors) message += '\n' + Object.values(error.errors).flat().join('\n');
                throw new Error(message);
            }
            return response.json();
        })
        .then(() => reloadDelayReview())
        .catch(error => {
            alert(error.message || 'The reason could not be saved.');
            save.disabled = false;
            save.textContent = 'Save';
        });
    });
    return box;
}

function reloadDelayReview() {
    if (currentAnalysisProjectId) openDelayAnalysis(currentAnalysisProjectId);
}

/* The chain of linked tasks, shown wrapped so it never needs sideways scrolling. */
function renderLinkedChain(res) {
    const holder = document.getElementById('pdi-cpm-chain');
    const note = document.getElementById('pdi-cpm-note');
    const box = document.getElementById('pds-critical-section') || document.getElementById('pdl-chain-box');
    const listHolder = document.getElementById('pds-critical-list');
    const countPill = document.getElementById('pds-critical-count-pill');
    if (!holder) return;
    holder.replaceChildren();
    if (listHolder) listHolder.replaceChildren();
    if (note) note.hidden = true;

    const cpm = res.critical_path || {};
    const sequence = cpm.sequence || [];
    const allCrit = cpm.all_tasks || cpm.critical_tasks || cpm.tasks || [];
    const critCount = cpm.total_count || allCrit.length || (sequence.filter(s => s.is_critical).length || sequence.length);
    const delayedCount = cpm.delayed_count || sequence.filter(s => s.is_delayed).length;

    if (!cpm.has_tasks) {
        if (box) box.hidden = true;
        return;
    }
    if (box) box.hidden = false;

    if (countPill) {
        countPill.textContent = critCount + ' Critical ' + (critCount === 1 ? 'task' : 'tasks') + (delayedCount > 0 ? ' (' + delayedCount + ' delayed)' : ' (All on track)');
        countPill.className = 'pds-critical-pill' + (delayedCount > 0 ? ' is-alert' : '');
    }

    if (!cpm.reliable && !sequence.length) {
        if (note) {
            note.textContent = cpm.warning || 'Dependency links are not configured between tasks yet, so critical path is based on linear sequence.';
            note.hidden = false;
        }
        return;
    }

    sequence.forEach((node, index) => {
        if (index) holder.append(pdEl('span', 'pds-arrow', '→'));
        const late = node.is_delayed || node.is_overrun || node.status === 'delayed';
        const done = node.status === 'completed';
        const inProgress = node.status === 'in_progress' || (node.progress > 0 && !done && !late);
        
        let statusCls = 'is-pending';
        let statusIcon = '⚪';
        let badgeText = '';
        if (late) {
            statusCls = 'is-late';
            statusIcon = '🔴';
            badgeText = node.delay_days ? '+' + node.delay_days + 'd' : 'Late';
        } else if (done) {
            statusCls = 'is-done';
            statusIcon = '✓';
            badgeText = 'Done';
        } else if (inProgress) {
            statusCls = 'is-active';
            statusIcon = '⏳';
            badgeText = (node.progress || 0) + '%';
        }

        const nodeBtn = pdEl('button', 'pds-node ' + statusCls);
        nodeBtn.type = 'button';
        nodeBtn.setAttribute('aria-label', (node.name || node.title) + '. Open task details');
        nodeBtn.append(
            pdEl('span', 'pds-node-icon', statusIcon),
            pdEl('span', 'pds-node-title', node.name || String(node))
        );
        if (badgeText) {
            nodeBtn.append(pdEl('span', 'pds-node-pill', badgeText));
        }

        nodeBtn.addEventListener('click', () => {
            const row = document.getElementById('task-row-' + node.id);
            if (row) {
                if (lateFilter !== 'all' && row.hidden) {
                    lateFilter = 'all';
                    renderLateTasks(res);
                }
                const targetRow = document.getElementById('task-row-' + node.id);
                if (targetRow) {
                    const rowBody = targetRow.querySelector('.pdl-body');
                    const rowHead = targetRow.querySelector('.pdl-row-head');
                    if (rowBody && rowBody.hidden) {
                        rowBody.hidden = false;
                        targetRow.classList.add('is-open');
                        rowHead?.setAttribute('aria-expanded', 'true');
                        openLateIds.add(node.id);
                    }
                    targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    targetRow.classList.remove('pds-row-highlight');
                    void targetRow.offsetWidth;
                    targetRow.classList.add('pds-row-highlight');
                }
            } else {
                const projectUrl = new URL(res.project?.show_url || '#', location.origin);
                projectUrl.searchParams.set('focus_task', node.id);
                projectUrl.hash = 'tasks';
                window.location.href = projectUrl.href;
            }
        });

        holder.append(nodeBtn);
    });

    if (allCrit.length > 0 && listHolder) {
        const details = pdEl('details', 'pds-critical-details');
        const summary = pdEl('summary', '', 'View all critical path task details (' + allCrit.length + ')');
        const table = pdEl('div', 'pds-critical-table');
        
        allCrit.forEach(task => {
            const r = pdEl('div', 'pds-crit-row');
            const left = pdEl('div', 'pds-crit-main');
            left.append(pdEl('span', 'pds-crit-num', task.number || ''));
            left.append(pdEl('span', 'pds-crit-title', task.title));
            r.append(left);

            const right = pdEl('div', 'pds-crit-meta');
            const unassigned = !task.assignees_text || task.assignees_text === 'Not assigned';
            right.append(pdEl('span', unassigned ? 'is-warn' : '', unassigned ? '⚠️ Unassigned' : task.assignees_text));
            right.append(pdEl('span', 'pds-crit-due', task.forecast_end && task.forecast_end !== task.planned_end ? task.planned_end + ' → ' + task.forecast_end : task.planned_end));
            const isLate = task.is_overdue || (Number(task.delay_days) > 0);
            right.append(pdEl('span', 'pds-node-pill ' + (isLate ? 'is-late' : (task.is_completed ? 'is-done' : 'is-pending')), isLate ? '+' + task.delay_days + 'd' : (task.is_completed ? 'Done' : 'On track')));
            table.append(r);
        });

        details.append(summary, table);
        listHolder.append(details);
    }
}

async function resolveDelayReport(taskId, url, event) {
    if (event) event.stopPropagation();
    if (!await AppDialog.confirm('Mark this delay report as resolved?')) return;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    fetch(url, {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        }
    })
    .then(async res => {
        if (!res.ok) {
            const errJson = await res.json().catch(() => ({}));
            throw new Error(errJson.message || 'Error resolving delay report.');
        }
        return res.json();
    })
    .then(() => {
        reloadDelayReview();
    })
    .catch(err => {
        alert(err.message || 'Failed to resolve delay report.');
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>
@endpush

<link rel="stylesheet" href="{{ asset('css/project-list-refinement.css') }}?v={{ filemtime(public_path('css/project-list-refinement.css')) }}">
@endsection

@push('scripts')
<link rel="stylesheet" href="{{ asset('css/project-workspace.css') }}?v={{ filemtime(public_path('css/project-workspace.css')) }}">
<script defer src="{{ asset('js/project-editor.js') }}?v={{ filemtime(public_path('js/project-editor.js')) }}"></script>
@endpush
