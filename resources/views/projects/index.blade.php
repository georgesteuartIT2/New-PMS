@extends('layouts.app')
@section('title', 'Projects')
@section('content')
@include('projects._delete-dialog')

<!-- Page Header matching mockup -->
<div class="page-head">
    <div>
        <h1>{{ auth()->user()->canViewPortfolio() ? 'Projects' : 'My Projects' }}</h1>
        <p class="muted">{{ auth()->user()->canViewPortfolio() ? 'Track delivery, manage your team and keep projects moving.' : 'Projects you are involved in.' }}</p>
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
                        $canViewTasks = auth()->user()->isPmo() || $isPm || (isset($access) && $access->project(auth()->user(), $project, 'view_tasks'));
                        $isDelayed = $project->approval_stage!=='planning' && (bool) ($project->is_delayed ?? false);
                        $isAtRisk = $project->approval_stage!=='planning' && (bool) ($project->is_at_risk ?? false);
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
                                @if($project->approval_stage==='planning')<span class="status-pill pill-neutral">Preparing plan</span>
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
                                @elseif($project->approval_stage!=='planning' && $project->status === 'active' && ($project->tasks_count ?? 0) > 0)
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
            $isDelayed = $project->approval_stage!=='planning' && (bool) ($project->is_delayed ?? false);
            $isAtRisk = $project->approval_stage!=='planning' && (bool) ($project->is_at_risk ?? false);
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
                    @if($project->approval_stage==='planning')<span class="status-pill pill-neutral">Preparing plan</span>
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
                <span id="dam-health-badge" class="dam-health-pill pill-delayed">Delayed</span>
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
                
                <!-- SECTION A: Why Is This Project Delayed? -->
                <div class="pdi-card pdi-section-a">
                    <div class="pdi-card-head">
                        <div class="pdi-section-title">
                            <span class="pdi-title-icon">?</span>
                            <span>What to check first</span>
                        </div>
                        <span id="pdi-explanation-status-pill" class="pdi-micro-pill">Schedule review</span>
                    </div>
                    <div class="pdi-plain-summary"><div><span>Task to check first</span><strong id="pdi-first-task">Loading...</strong></div><div><span>Reported reason</span><p id="pdi-plain-reason">No reason has been reported yet.</p></div><a id="pdi-next-task-link" class="btn btn-primary" href="#">Review task &rarr;</a></div>
                    <details class="pdi-explanation-details"><summary>Read the schedule explanation</summary><div id="pdi-why-delayed-box" class="pdi-explanation-box">
                        <p id="pdi-why-delayed-text" class="pdi-explanation-text">
                            Checking the current schedule...
                        </p>
                    </div></details>
                </div>

                <!-- SECTION B: Project Schedule Impact -->
                <div class="pdi-card pdi-section-b">
                    <div class="pdi-section-heading">Finish date</div>
                    <p id="pdi-finish-summary" class="pdi-finish-summary"></p>
                    <div class="pdi-impact-grid">
                        <div class="pdi-impact-col">
                            <div class="pdi-impact-label">Planned finish</div>
                            <div class="pdi-impact-val" id="dam-approved-deadline">—</div>
                            <div class="pdi-impact-sub">The agreed project end date</div>
                        </div>
                        <div class="pdi-impact-col">
                            <div class="pdi-impact-label">Estimated finish</div>
                            <div class="pdi-impact-val" id="dam-forecast-completion">—</div>
                            <div class="pdi-impact-sub">Estimate based on the current task plan</div>
                        </div>
                        <div class="pdi-impact-col">
                            <div class="pdi-impact-label">Expected delay</div>
                            <div class="pdi-impact-val text-danger" id="dam-schedule-variance">—</div>
                            <div class="pdi-impact-sub">Working days later than planned</div>
                        </div>
                    </div>
                </div>

                <section class="pdi-card pdi-delay-drivers" aria-labelledby="pdi-drivers-heading">
                    <h3 id="pdi-drivers-heading">Which tasks are affecting this estimate?</h3>
                    <div id="pdi-delay-drivers"></div>
                    <p class="pdi-driver-note">Task delays are not added together: some tasks run at the same time. The finish date follows the full task plan. Saturdays and Sundays are excluded from working-day counts.</p>
                </section>

                <details class="project-review-more"><summary>More schedule details <span>Dependencies and full task lists</span></summary><div class="project-review-more-body">
                <!-- SECTION C: Critical Path & Affected Dependencies -->
                <div class="pdi-card pdi-section-c">
                    <div class="pdi-card-head">
                        <div>
                            <div class="pdi-section-heading" style="margin-bottom: 2px;">Tasks that affect the finish date</div>
                            <div class="pdi-section-desc">These tasks depend on each other. A delay here can move the project finish date.</div>
                        </div>
                        <button type="button" class="btn btn-ghost btn-xs pdi-toggle-details-btn" id="pdi-toggle-schedule-details-btn" onclick="toggleScheduleDetails()">
                            Show Schedule Details ▼
                        </button>
                    </div>

                    <!-- Horizontal Sequence Flow -->
                    <div id="dam-cpm-sequence-flow" class="dam-cpm-flow">
                        <!-- Populated dynamically by JS -->
                    </div>

                    <!-- Warning box for missing dependencies or cycle -->
                    <div id="dam-cpm-warning-box" class="dam-notice-box" style="display: none; margin-top: 10px;">
                        <span id="dam-cpm-warning-text"></span>
                    </div>

                    <!-- Impact Explanation -->
                    <div id="pdi-critical-impact-explanation" class="pdi-impact-explanation">
                        <!-- Populated by JS -->
                    </div>

                    <!-- Collapsible Schedule Details Panel -->
                    <div id="pdi-schedule-details-panel" class="pdi-schedule-details-panel" style="display: none;">
                        <div class="pdi-details-grid">
                            <div class="pdi-detail-item">
                                <div class="pdi-detail-label">Critical Path Duration</div>
                                <div class="pdi-detail-val" id="pdi-cpm-duration">—</div>
                            </div>
                            <div class="pdi-detail-item">
                                <div class="pdi-detail-label">Critical Tasks Count</div>
                                <div class="pdi-detail-val" id="pdi-cpm-tasks-count">—</div>
                            </div>
                            <div class="pdi-detail-item">
                                <div class="pdi-detail-label">Task Dependencies</div>
                                <div class="pdi-detail-val" id="pdi-cpm-links-count">—</div>
                            </div>
                            <div class="pdi-detail-item">
                                <div class="pdi-detail-label">CPM Reliability</div>
                                <div class="pdi-detail-val" id="pdi-cpm-reliability">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION D: Delayed Tasks (Mandatory SEPARATE Section) -->
                <div class="pdi-card pdi-section-d">
                    <div class="pdi-card-head" style="align-items: flex-end;">
                        <div>
                            <div class="pdi-section-heading" style="display: flex; align-items: center; gap: 8px;">
                                <span>Delayed Tasks</span>
                                <span class="pdi-count-badge badge-delayed" id="pdi-delayed-count-badge">0</span>
                            </div>
                            <div class="pdi-section-desc">
                                Tasks that finished late or are now expected to finish later than planned.
                            </div>
                        </div>

                        <!-- Section D Filters -->
                        <div class="pdi-filter-group">
                            <button type="button" class="pdi-filter-btn active" id="pdi-filter-all" onclick="filterDelayedTasks('all')">All Delayed (<span id="pdi-filter-count-all">0</span>)</button>
                            <button type="button" class="pdi-filter-btn" id="pdi-filter-critical" onclick="filterDelayedTasks('critical')">Affects Project Deadline (<span id="pdi-filter-count-critical">0</span>)</button>
                            <button type="button" class="pdi-filter-btn" id="pdi-filter-noncritical" onclick="filterDelayedTasks('noncritical')">No Current Impact (<span id="pdi-filter-count-noncritical">0</span>)</button>
                        </div>
                    </div>

                    <!-- Delayed Tasks Table -->
                    <div class="table-wrap pdi-table-wrap">
                        <table class="clean-table pdi-table">
                            <thead>
                                <tr>
                                    <th style="width: 44px;">#</th>
                                    <th style="width: 220px;">Task / Subtask</th>
                                    <th style="width: 150px;">Assigned User</th>
                                    <th style="width: 105px;">Planned Finish</th>
                                    <th style="width: 105px;">Forecast Finish</th>
                                    <th style="width: 80px;">Delay</th>
                                    <th style="width: 175px;">Critical Path Impact</th>
                                    <th>Detected Problem</th>
                                    <th style="width: 60px; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="pdi-delayed-tasks-tbody">
                                <tr><td colspan="9" class="pdi-empty-row">Loading delayed tasks...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECTION E: Overdue Tasks (Mandatory SEPARATE Section) -->
                <div class="pdi-card pdi-section-e">
                    <div class="pdi-card-head">
                        <div>
                            <div class="pdi-section-heading" style="display: flex; align-items: center; gap: 8px;">
                                <span>Overdue Tasks</span>
                                <span class="pdi-count-badge badge-overdue" id="pdi-overdue-count-badge">0</span>
                            </div>
                            <div class="pdi-section-desc">
                                All unfinished tasks whose scheduled finish date/time is already in the past.
                            </div>
                        </div>
                    </div>

                    <!-- Overdue Tasks Table -->
                    <div class="table-wrap pdi-table-wrap">
                        <table class="clean-table pdi-table">
                            <thead>
                                <tr>
                                    <th style="width: 44px;">#</th>
                                    <th style="width: 240px;">Task / Subtask</th>
                                    <th style="width: 160px;">Assigned To</th>
                                    <th style="width: 140px;">Due Date &amp; Time</th>
                                    <th style="width: 110px;">Overdue Duration</th>
                                    <th style="width: 110px;">Status</th>
                                    <th style="width: 80px;">Critical</th>
                                    <th style="width: 60px; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="pdi-overdue-tasks-tbody">
                                <tr><td colspan="8" class="pdi-empty-row">Loading overdue tasks...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                </div></details>
            </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="dam-footer pdi-footer">
            <a id="dam-open-plan-link" href="#" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; padding: 7px 16px; color: #22272B; border-color: #D0D7DE; background: #FFFFFF; text-decoration: none;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                <span>Open Project</span>
            </a>
            <button type="button" class="btn btn-primary" style="background: #315F58; border-color: #315F58; font-weight: 600; padding: 7px 22px; color: #FFFFFF;" onclick="closeDelayAnalysis()">Close</button>
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
let currentDelayedFilter = 'all';
let expandedDelayedTaskIds = new Set();
let expandedOverdueTaskIds = new Set();
let isScheduleDetailsVisible = false;

function toggleScheduleDetails() {
    isScheduleDetailsVisible = !isScheduleDetailsVisible;
    const panel = document.getElementById('pdi-schedule-details-panel');
    const btn = document.getElementById('pdi-toggle-schedule-details-btn');
    if (panel) panel.style.display = isScheduleDetailsVisible ? 'block' : 'none';
    if (btn) btn.textContent = isScheduleDetailsVisible ? 'Hide Schedule Details ▲' : 'Show Schedule Details ▼';
}

function filterDelayedTasks(filter) {
    currentDelayedFilter = filter;
    ['all', 'critical', 'noncritical'].forEach(f => {
        const b = document.getElementById(`pdi-filter-${f}`);
        if (b) b.classList.toggle('active', f === filter);
    });
    renderDelayedTasksTable();
}

function toggleDelayedDrawer(taskId, event) {
    if (event) event.stopPropagation();
    if (expandedDelayedTaskIds.has(taskId)) {
        expandedDelayedTaskIds.delete(taskId);
    } else {
        expandedDelayedTaskIds.add(taskId);
    }
    renderDelayedTasksTable();
}

function toggleOverdueDrawer(taskId, event) {
    if (event) event.stopPropagation();
    if (expandedOverdueTaskIds.has(taskId)) {
        expandedOverdueTaskIds.delete(taskId);
    } else {
        expandedOverdueTaskIds.add(taskId);
    }
    renderOverdueTasksTable();
}

function populateDelayModal(res) {
    currentInvestigationData = res;
    expandedDelayedTaskIds.clear();
    expandedOverdueTaskIds.clear();
    isScheduleDetailsVisible = false;

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

    // SECTION A: Why Is This Project Delayed?
    const whyDelayedText = inv.section_a?.explanation || summary.diagnostic_summary || 'Analyzing schedule data...';
    document.getElementById('pdi-why-delayed-text').textContent = whyDelayedText;
    const candidates=inv.section_d?.tasks || res.delayed_section?.tasks || [];
    const overdueCandidates=inv.section_e?.tasks || res.overdue_section?.tasks || [];
    const focus=candidates.find(t=>t.is_critical && t.is_overdue) || candidates.find(t=>t.has_delay_report) || overdueCandidates[0] || candidates[0];
    document.getElementById('pdi-first-task').textContent=focus ? (focus.title || focus.name || 'Review the unfinished tasks below') : 'No delayed tasks identified';
    document.getElementById('pdi-plain-reason').textContent=(focus?.has_delay_report && focus?.reported_root_cause) || 'No delay reason reported. Ask the task owner for an update.';
    const nextLink=document.getElementById('pdi-next-task-link');
    nextLink.href=focus?.id ? new URL((proj.show_url || inv.header?.open_project_url || '#'),location.origin).pathname+'?focus_task='+encodeURIComponent(focus.id)+'#tasks' : (proj.show_url || '#');
    nextLink.textContent=focus?.id?'Review task \u2192':'Open project \u2192';


    const explanationPill = document.getElementById('pdi-explanation-status-pill');
    if (explanationPill) {
        if (health.is_delayed || inv.header?.health_state === 'delayed' || inv.header?.health_state === 'forecast_late') {
            explanationPill.textContent = 'Finish date may move';
            explanationPill.style.background = '#FEE2E2';
            explanationPill.style.color = '#991B1B';
        } else if (health.is_at_risk || inv.header?.health_state === 'at_risk') {
            explanationPill.textContent = 'Needs attention';
            explanationPill.style.background = '#FEF3C7';
            explanationPill.style.color = '#92400E';
        } else {
            explanationPill.textContent = 'On schedule';
            explanationPill.style.background = '#E8F5E9';
            explanationPill.style.color = '#166534';
        }
    }

    // SECTION B: Project Schedule Impact
    document.getElementById('dam-approved-deadline').textContent = summary.approved_deadline || proj.approved_deadline || '—';
    document.getElementById('dam-forecast-completion').textContent = summary.forecast_deadline || inv.section_b?.forecast_completion || '—';
    
    const schedVar = document.getElementById('dam-schedule-variance');
    schedVar.textContent = summary.schedule_variance_pill || summary.schedule_variance || 'On schedule';
    if (health.is_delayed || (health.delay_working_days > 0)) {
        schedVar.className = 'pdi-impact-val text-danger';
    } else {
        schedVar.className = 'pdi-impact-val';
    }

    const plannedFinish=summary.approved_deadline || proj.approved_deadline;
    const estimatedFinish=summary.forecast_deadline || inv.section_b?.forecast_completion;
    const workingDelay=Number(inv.section_b?.variance_working_days || 0);
    document.getElementById('pdi-finish-summary').textContent=workingDelay>0
        ? 'The current plan estimates completion on '+estimatedFinish+' instead of '+plannedFinish+' ('+workingDelay+' working '+(workingDelay===1?'day':'days')+' later). This is an estimate, not a changed approved deadline.'
        : 'Compare the agreed deadline with the current estimate below. The approved deadline stays unchanged.';
    const drivers=candidates.filter(t=>!t.is_completed && t.is_critical && (t.is_overdue || Number(t.delay_days)>0));
    const driverBox=document.getElementById('pdi-delay-drivers');
    driverBox.replaceChildren();
    if(!cpm.reliable || !drivers.length){
        const note=document.createElement('p');
        note.textContent=!cpm.reliable ? 'The task links are incomplete, so the system cannot reliably identify which task is moving the finish date. Review the task dates and dependencies.' : 'No individual delayed task has been identified as the cause of this finish estimate. Review the task schedule before assigning a cause.';
        driverBox.append(note);
    } else drivers.forEach(t=>{
        const row=document.createElement('article');row.className='pdi-driver-row';
        const body=document.createElement('div');
        const title=document.createElement('strong');title.textContent=t.title || t.name;body.append(title);
        const detail=document.createElement('p');
        detail.textContent=t.is_overdue ? 'Unfinished; planned to finish '+t.planned_end+'. Overdue by '+t.delay_days+' working '+(Number(t.delay_days)===1?'day.':'days.') : 'Planned finish: '+t.planned_end+'. Current task estimate: '+t.forecast_end+'.';body.append(detail);
        const successors=t.blocked_successors || [];
        const impact=document.createElement('p');impact.textContent=successors.length ? 'Next dependent work: '+successors.map(x=>x.title).filter(Boolean).join(', ')+'. A later finish here can move that work back.' : 'This task has no spare time in the schedule; a later finish can move the project finish date.';body.append(impact);
        const reason=document.createElement('p');reason.className='pdi-driver-reason';reason.textContent=t.has_delay_report && t.reported_root_cause ? 'Reported reason: '+t.reported_root_cause : 'Reason for the delay: not reported yet.';body.append(reason);
        const link=document.createElement('a');link.className='btn btn-outline btn-xs';link.textContent='View task';
        const target=new URL(proj.show_url || inv.header?.open_project_url,location.origin);target.searchParams.set('focus_task',t.id);target.hash='tasks';link.href=target.href;
        row.append(body,link);driverBox.append(row);
    });

    // SECTION C: Critical Path Sequence Flow
    const sequenceContainer = document.getElementById('dam-cpm-sequence-flow');
    const warningBox = document.getElementById('dam-cpm-warning-box');
    const warningText = document.getElementById('dam-cpm-warning-text');
    const impactExplanationEl = document.getElementById('pdi-critical-impact-explanation');

    const seq = cpm.sequence || [];
    if (!cpm.has_tasks) {
        warningBox.style.display = 'block';
        warningText.textContent = 'No tasks have been added to this project yet.';
        sequenceContainer.innerHTML = `<span style="font-size: 13px; color: #64748B;">No schedule sequence available.</span>`;
        if (impactExplanationEl) impactExplanationEl.textContent = 'Add tasks to calculate the Critical Path.';
    } else if (!cpm.reliable && seq.length === 0) {
        warningBox.style.display = 'block';
        warningText.textContent = cpm.warning || 'Critical Path unavailable because task dependency information is incomplete.';
        sequenceContainer.innerHTML = `<span style="font-size: 13px; color: #64748B;">Critical Path unavailable because task dependency information is incomplete.</span>`;
        if (impactExplanationEl) impactExplanationEl.textContent = 'Configure dependencies between tasks to enable automatic critical path scheduling.';
    } else {
        warningBox.style.display = 'none';
        if (seq.length === 0) {
            sequenceContainer.innerHTML = `<span style="font-size: 13px; color: #64748B;">No critical sequence identified.</span>`;
        } else {
            let flowHtml = '';
            seq.forEach((node, index) => {
                const isObj = (typeof node === 'object' && node !== null);
                const name = isObj ? node.name : node;
                const isDelayed = isObj ? (node.is_delayed || node.is_overrun || node.status === 'delayed') : false;
                const isAtRisk = isObj ? (node.status === 'at_risk') : false;
                const isCompleted = isObj ? (node.status === 'completed') : false;

                const nodeClass = isDelayed ? 'node-delayed' : (isAtRisk ? 'node-risk' : (isCompleted ? 'node-completed' : 'node-ok'));

                flowHtml += `<div class="dam-cpm-node ${nodeClass}">${escapeHtml(name)}</div>`;
                if (index < seq.length - 1) {
                    flowHtml += `<div class="dam-cpm-arrow">→</div>`;
                }
            });
            sequenceContainer.innerHTML = flowHtml;
        }

        if (impactExplanationEl) {
            impactExplanationEl.textContent = 'This is the linked task sequence, not a list of overdue tasks. A task with no spare time can affect the finish date if it finishes late. See the affected tasks above for the current delays.';
        }
    }

    // Schedule details
    const schedDetails = inv.section_c?.schedule_details || {};
    document.getElementById('pdi-cpm-duration').textContent = (schedDetails.cpm_duration_working_days || 0) + ' working days';
    document.getElementById('pdi-cpm-tasks-count').textContent = (schedDetails.critical_tasks_count || 0) + ' critical tasks';
    document.getElementById('pdi-cpm-links-count').textContent = (schedDetails.dependency_links || 0) + ' dependencies';
    document.getElementById('pdi-cpm-reliability').textContent = cpm.reliable ? 'Reliable (DAG Verified)' : 'Incomplete Links';

    // SECTION D: Delayed Tasks Counts & Table
    const allDelayed = (inv.section_d?.tasks || delayed.tasks || []);
    const critDelayed = allDelayed.filter(t => t.is_critical || t.critical_impact === 'affects_deadline');
    const nonCritDelayed = allDelayed.filter(t => t.critical_impact === 'no_impact');

    document.getElementById('pdi-delayed-count-badge').textContent = allDelayed.length;
    document.getElementById('pdi-filter-count-all').textContent = allDelayed.length;
    document.getElementById('pdi-filter-count-critical').textContent = critDelayed.length;
    document.getElementById('pdi-filter-count-noncritical').textContent = nonCritDelayed.length;

    // Auto-expand first task with delay report or overdue
    if (allDelayed.length > 0) {
        const firstToExpand = allDelayed.find(t => t.has_delay_report || t.is_overdue) || allDelayed[0];
        // Details open only when the user chooses a task.
    }
    renderDelayedTasksTable();

    // SECTION E: Overdue Tasks Count & Table
    const allOverdue = (inv.section_e?.tasks || overdue.tasks || []);
    document.getElementById('pdi-overdue-count-badge').textContent = allOverdue.length;
    renderOverdueTasksTable();
}

function renderDelayedTasksTable() {
    if (!currentInvestigationData) return;
    const tbody = document.getElementById('pdi-delayed-tasks-tbody');
    const allDelayed = (currentInvestigationData.investigation?.section_d?.tasks || currentInvestigationData.delayed_section?.tasks || []);

    let filtered = allDelayed;
    if (currentDelayedFilter === 'critical') {
        filtered = allDelayed.filter(t => t.is_critical || t.critical_impact === 'affects_deadline');
    } else if (currentDelayedFilter === 'noncritical') {
        filtered = allDelayed.filter(t => t.critical_impact === 'no_impact');
    }

    if (filtered.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="pdi-empty-row">
                    ${allDelayed.length === 0 ? 'No delayed tasks found in this project.' : 'No tasks match the selected filter.'}
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    filtered.forEach(t => {
        const isExpanded = expandedDelayedTaskIds.has(t.id);
        const initials = t.assignee_initials || '—';
        const avatarBg = (initials === 'NP' || initials.charCodeAt(0) % 2 === 0) ? '#D1E7DD' : '#E0F2FE';
        const avatarColor = (initials === 'NP' || initials.charCodeAt(0) % 2 === 0) ? '#0F5132' : '#0369A1';
        const delayBadgeClass = t.delay_days > 0 ? 'dam-delay-pill' : 'dam-delay-pill pill-zero';

        const impactClass = t.critical_impact_class || (t.is_critical ? 'impact-critical' : 'impact-neutral');
        const impactLabel = t.critical_impact_label || (t.is_critical ? 'Affects Project Deadline' : 'Buffer Absorbed');

        html += `
            <tr class="pdi-task-row ${isExpanded ? 'is-expanded' : ''}" onclick="toggleDelayedDrawer(${t.id}, event)">
                <td style="font-weight: 600; color: #64748B;">${escapeHtml(t.number || t.code)}</td>
                <td>
                    <div style="font-weight: 700; color: #0F172A;">${escapeHtml(t.title)}</div>
                    <div style="font-size: 11px; color: #64748B;">${escapeHtml(t.code)}</div>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 7px;">
                        <span class="dam-avatar-badge" style="background: ${avatarBg}; color: ${avatarColor};">${escapeHtml(initials)}</span>
                        <span style="font-weight: 500; color: #1E293B;">${escapeHtml(t.assignee_name || t.assignees_text)}</span>
                    </div>
                </td>
                <td style="color: #475569;">${escapeHtml(t.planned_end)}</td>
                <td style="color: #0F172A; font-weight: 600;">${escapeHtml(t.forecast_end || t.planned_end)}</td>
                <td>
                    <span class="${delayBadgeClass}">${escapeHtml(t.delay_badge || ('+' + t.delay_days + ' days'))}</span>
                </td>
                <td>
                    <span class="pdi-impact-badge ${impactClass}">${escapeHtml(impactLabel)}</span>
                </td>
                <td>
                    <div style="color: #334155; font-size: 12px; line-height: 1.35;">${escapeHtml(t.detected_problem)}</div>
                </td>
                <td style="text-align: center;">
                    <button type="button" class="dam-chevron-btn" onclick="toggleDelayedDrawer(${t.id}, event)" aria-label="Toggle details">
                        ${isExpanded ? '▲' : '▼'}
                    </button>
                </td>
            </tr>
        `;

        if (isExpanded) {
            html += `
                <tr class="pdi-drawer-row">
                    <td colspan="9" class="pdi-drawer-cell">
                        <div class="pdi-drawer-grid">
                            <div class="pdi-drawer-card">
                                <div class="pdi-drawer-card-label">Assigned Task Owner</div>
                                <div class="pdi-drawer-card-val" style="font-weight: 600;">
                                    ${escapeHtml(t.assignee_name || t.assignees_text)}
                                </div>
                                <div style="font-size: 11px; color: #64748B; margin-top: 4px;">
                                    Planned: ${escapeHtml(t.planned_end_time || t.planned_end)}
                                </div>
                            </div>
                            <div class="pdi-drawer-card">
                                <div class="pdi-drawer-card-label">Detected Schedule Problem</div>
                                <div class="pdi-drawer-card-val" style="color: #B91C1C;">
                                    ${escapeHtml(t.detected_problem)}
                                </div>
                                <div style="font-size: 11px; color: #64748B; margin-top: 4px;">
                                    Status: <b>${escapeHtml(t.status_label)}</b> (${t.progress}%)
                                </div>
                            </div>
                            <div class="pdi-drawer-card">
                                <div class="pdi-drawer-card-label">Dependencies</div>
                                <div class="pdi-drawer-card-val" style="font-size: 11.5px;">
                                    ${t.blocking_predecessors && t.blocking_predecessors.length > 0 
                                        ? `<span style="color: #B45309;">Waiting on: ${escapeHtml(t.blocking_predecessors.map(p => p.title).join(', '))}</span>` 
                                        : '<span style="color: #64748B;">No blocking predecessor.</span>'}
                                    <br>
                                    ${t.blocked_successors && t.blocked_successors.length > 0
                                        ? `<span style="color: #0F766E;">Blocks: ${escapeHtml(t.blocked_successors.map(s => s.title).join(', '))}</span>`
                                        : '<span style="color: #64748B;">No downstream task waiting.</span>'}
                                </div>
                            </div>

                            <div class="pdi-drawer-card">
                                <div class="pdi-drawer-card-label">Reported Root Cause</div>
                                <div class="pdi-drawer-card-val">
                                    ${t.has_delay_report 
                                        ? `<b style="color: #0F172A;">${escapeHtml(t.reported_root_cause)}</b>` 
                                        : '<span style="color: #64748B; font-style: italic;">Underlying reason not reported.</span>'}
                                </div>
                                <div style="font-size: 11px; color: #64748B; margin-top: 4px;">
                                    Involved Party: ${escapeHtml(t.involved_party || 'Not recorded')}
                                </div>
                            </div>

                            <div class="pdi-drawer-card">
                                <div class="pdi-drawer-card-label">Confirmed Corrective Action</div>
                                <div class="pdi-drawer-card-val">
                                    ${t.has_delay_report && t.corrective_action !== 'None recorded'
                                        ? escapeHtml(t.corrective_action)
                                        : '<span style="color: #64748B; font-style: italic;">None recorded.</span>'}
                                </div>
                                <div style="font-size: 11px; color: #64748B; margin-top: 4px;">
                                    Resolution Target: ${escapeHtml(t.resolution_date || '—')}
                                </div>
                            </div>

                            <div class="pdi-drawer-card">
                                <div class="pdi-drawer-card-label">Action Owner &amp; Status</div>
                                <div class="pdi-drawer-card-val" style="display: flex; align-items: center; justify-content: space-between;">
                                    <span>${escapeHtml(t.action_owner || 'None recorded')}</span>
                                    <span class="pdi-micro-pill" style="font-weight: 700; background: ${t.resolution_status === 'Open' ? '#FEF3C7' : (t.resolution_status === 'Resolved' ? '#DCFCE7' : '#F1F5F9')}; color: ${t.resolution_status === 'Open' ? '#B45309' : (t.resolution_status === 'Resolved' ? '#15803D' : '#475569')};">
                                        ${escapeHtml(t.resolution_status)}
                                    </span>
                                </div>
                                <div style="margin-top: 8px;">
                                    ${t.can_add_reason ? `
                                        <button type="button" class="btn btn-xs ${t.has_delay_report ? 'btn-outline' : 'btn-primary'}" onclick="toggleInlineDelayForm(${t.id}, event)" style="font-size: 11px; padding: 3px 8px; font-weight: 600;">
                                            ${t.has_delay_report ? '✏️ Edit Delay Reason' : '+ Add Reason & Corrective Action'}
                                        </button>
                                    ` : ''}
                                    ${(t.has_delay_report && t.resolution_status !== 'Resolved' && t.resolve_delay_url) ? `
                                        <button type="button" class="btn btn-xs btn-outline" onclick="resolveDelayReport(${t.id}, '${t.resolve_delay_url}', event)" style="font-size: 11px; padding: 3px 8px; color: #15803D; border-color: #86EFAC;">
                                            ✓ Mark Resolved
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                        </div>

                        <!-- Inline Form for Recording Delay Reason -->
                        <div id="dam-delay-form-${t.id}" style="display: none; margin-top: 14px; padding: 16px; background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 8px;">
                            <div style="font-size: 13px; font-weight: 700; color: #0F172A; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                                <span>Record Official Delay Reason &amp; Corrective Action</span>
                            </div>
                            <form onsubmit="submitDelayReport(event, ${t.id}, '${t.store_delay_url}')" style="display: flex; flex-direction: column; gap: 12px;">
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                    <div>
                                        <label style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Reason Category *</label>
                                        <select name="reason_code" required class="input" style="width: 100%; font-size: 12px; padding: 6px 8px; border: 1px solid #CBD5E1; border-radius: 4px; background: #FFFFFF;">
                                            <option value="">Select root cause category...</option>
                                            ${Object.entries(currentInvestigationData.delay_reasons_catalog || {}).map(([k, v]) => `
                                                <option value="${k}" ${t.open_delay_raw && t.open_delay_raw.reason_code === k ? 'selected' : ''}>${escapeHtml(v)}</option>
                                            `).join('')}
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Involved Party Type *</label>
                                        <select name="responsible_party_type" required class="input" style="width: 100%; font-size: 12px; padding: 6px 8px; border: 1px solid #CBD5E1; border-radius: 4px; background: #FFFFFF;">
                                            <option value="internal">Internal Department</option>
                                            <option value="supplier">External Supplier / Vendor</option>
                                            <option value="department">Other Department</option>
                                            <option value="external">External Factor</option>
                                            <option value="unknown">Unknown Party</option>
                                        </select>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                    <div>
                                        <label style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Party Name (Supplier or Department Name)</label>
                                        <input type="text" name="responsible_party_name" value="${t.open_delay_raw ? escapeHtml(t.open_delay_raw.responsible_party_name || '') : ''}" placeholder="e.g. ABC Traders or Logistics Unit" class="input" style="width: 100%; font-size: 12px; padding: 6px 8px; border: 1px solid #CBD5E1; border-radius: 4px; background: #FFFFFF;">
                                    </div>
                                    <div>
                                        <label style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Action Owner</label>
                                        <select name="action_owner_id" class="input" style="width: 100%; font-size: 12px; padding: 6px 8px; border: 1px solid #CBD5E1; border-radius: 4px; background: #FFFFFF;">
                                            <option value="">Select owner...</option>
                                            ${(currentInvestigationData.project_members || []).map(m => `
                                                <option value="${m.id}" ${t.open_delay_raw && t.open_delay_raw.action_owner_id == m.id ? 'selected' : ''}>${escapeHtml(m.name)}</option>
                                            `).join('')}
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Corrective Action Plan</label>
                                    <textarea name="details" rows="2" placeholder="Describe the specific action being taken to recover schedule..." class="input" style="width: 100%; font-size: 12px; padding: 6px 8px; border: 1px solid #CBD5E1; border-radius: 4px; background: #FFFFFF;">${t.open_delay_raw ? escapeHtml(t.open_delay_raw.details || '') : ''}</textarea>
                                </div>

                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; align-items: flex-end;">
                                    <div>
                                        <label style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Revised Expected Resolution Date</label>
                                        <input type="datetime-local" name="revised_expected_end_at" value="${t.open_delay_raw && t.open_delay_raw.revised_expected_end_at ? t.open_delay_raw.revised_expected_end_at : ''}" class="input" style="width: 100%; font-size: 12px; padding: 6px 8px; border: 1px solid #CBD5E1; border-radius: 4px; background: #FFFFFF;">
                                    </div>
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <button type="button" class="btn btn-ghost btn-xs" onclick="toggleInlineDelayForm(${t.id}, event)" style="font-size: 11px;">Cancel</button>
                                        <button type="submit" class="btn btn-primary btn-xs" id="dam-submit-btn-${t.id}" style="font-size: 11px; background: #315F58; border-color: #315F58; color: #FFFFFF; font-weight: 600; padding: 6px 16px;">Save Reason &amp; Action</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
            `;
        }
    });

    tbody.innerHTML = html;
}

function renderOverdueTasksTable() {
    if (!currentInvestigationData) return;
    const tbody = document.getElementById('pdi-overdue-tasks-tbody');
    const allOverdue = (currentInvestigationData.investigation?.section_e?.tasks || currentInvestigationData.overdue_section?.tasks || []);

    if (allOverdue.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="pdi-empty-row" style="color: #15803D; font-weight: 500;">
                    ✓ No overdue tasks in this project.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    allOverdue.forEach(t => {
        const isExpanded = expandedOverdueTaskIds.has(t.id);
        const initials = t.assignee_initials || '—';
        const avatarBg = (initials === 'NP' || initials.charCodeAt(0) % 2 === 0) ? '#D1E7DD' : '#E0F2FE';
        const avatarColor = (initials === 'NP' || initials.charCodeAt(0) % 2 === 0) ? '#0F5132' : '#0369A1';
        const statusClass = 'dam-status-pill status-' + (t.status || 'todo');

        html += `
            <tr class="pdi-task-row ${isExpanded ? 'is-expanded' : ''}" onclick="toggleOverdueDrawer(${t.id}, event)">
                <td style="font-weight: 600; color: #64748B;">${escapeHtml(t.number || t.code)}</td>
                <td>
                    <div style="font-weight: 700; color: #0F172A;">${escapeHtml(t.title)}</div>
                    <div style="font-size: 11px; color: #64748B;">${escapeHtml(t.code)}</div>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 7px;">
                        <span class="dam-avatar-badge" style="background: ${avatarBg}; color: ${avatarColor};">${escapeHtml(initials)}</span>
                        <span style="font-weight: 500; color: #1E293B;">${escapeHtml(t.assignee_name || t.assignees_text)}</span>
                    </div>
                </td>
                <td style="color: #DC2626; font-weight: 600;">${escapeHtml(t.planned_end_time || t.planned_end)}</td>
                <td>
                    <span class="dam-delay-pill">${escapeHtml(t.overdue_duration || (t.delay_days + ' days'))}</span>
                </td>
                <td>
                    <span class="${statusClass}">${escapeHtml(t.status_label || t.status)}</span>
                </td>
                <td>
                    <span style="font-weight: 700; color: ${t.is_critical ? '#DC2626' : '#64748B'};">
                        ${t.is_critical ? 'Yes (CPM)' : 'No'}
                    </span>
                </td>
                <td style="text-align: center;">
                    <button type="button" class="dam-chevron-btn" onclick="toggleOverdueDrawer(${t.id}, event)" aria-label="Toggle details">
                        ${isExpanded ? '▲' : '▼'}
                    </button>
                </td>
            </tr>
        `;

        if (isExpanded) {
            html += `
                <tr class="pdi-drawer-row">
                    <td colspan="8" class="pdi-drawer-cell">
                        <div style="display: flex; justify-content: space-between; align-items: center; background: #F8FAFC; padding: 10px 14px; border: 1px solid #E2E8F0; border-radius: 6px;">
                            <div>
                                <span style="font-weight: 600; color: #0F172A;">${escapeHtml(t.title)}</span>
                                <span style="color: #64748B; font-size: 12px; margin-left: 8px;">Progress: <b>${t.progress}%</b> · Float Buffer: <b>${escapeHtml(t.float_text || '0 days')}</b></span>
                            </div>
                            <div style="font-size: 12px; color: #334155;">
                                ${escapeHtml(t.detected_problem || '')}
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }
    });

    tbody.innerHTML = html;
}

function toggleInlineDelayForm(taskId, event) {
    if (event) event.stopPropagation();
    const formBox = document.getElementById(`dam-delay-form-${taskId}`);
    if (formBox) {
        formBox.style.display = (formBox.style.display === 'none') ? 'block' : 'none';
    }
}

function submitDelayReport(event, taskId, url) {
    event.preventDefault();
    const form = event.target;
    const submitBtn = document.getElementById(`dam-submit-btn-${taskId}`);
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
    }

    const formData = new FormData(form);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: formData
    })
    .then(async res => {
        if (!res.ok) {
            const errJson = await res.json().catch(() => ({}));
            let msg = errJson.message || 'Error saving delay report.';
            if (errJson.errors) {
                msg += '\n' + Object.values(errJson.errors).flat().join('\n');
            }
            throw new Error(msg);
        }
        return res.json();
    })
    .then(() => {
        // Refresh project delay analysis modal instantly
        if (currentDamData && currentDamData.project) {
            openDelayAnalysis(currentDamData.project.id);
        }
    })
    .catch(err => {
        alert(err.message || 'Error saving delay report.');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Delay Report';
        }
    });
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
        if (currentDamData && currentDamData.project) {
            openDelayAnalysis(currentDamData.project.id);
        }
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
