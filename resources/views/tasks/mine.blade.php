@extends('layouts.app')
@section('title', ($view ?? '') === 'managed' && ($tab ?? '') === 'team' ? 'Team Monitor' : ((($view ?? '') === 'managed') ? 'Task Monitor' : 'My Tasks'))
@section('content')

<!-- =========================================================================
     SCREEN: USER WORKSPACE – MY TASKS, TASK MONITOR & TEAM MONITOR
     ========================================================================= -->
<div style="padding-bottom: 60px;">
    <!-- Breadcrumbs -->
    <div class="breadcrumb-trail" style="margin-bottom: 14px;">
        <a href="{{ route('dashboard') }}">{{ auth()->user()->isPmo() ? 'Dashboard' : 'My Workspace' }}</a>
        <span class="crumb-sep">›</span>
        @if(($view ?? '') === 'managed' && ($tab ?? '') === 'team')
            <span>Team Monitor</span>
        @elseif(($view ?? '') === 'managed')
            <a href="{{ route('tasks.mine') }}">My Tasks</a>
            <span class="crumb-sep">›</span>
            <span>Task Monitor</span>
        @else
            <span>My Tasks</span>
        @endif
    </div>

    <!-- Page Header & Top View Switcher -->
    <div class="page-head" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div>
            @if(($view ?? '') === 'managed' && ($tab ?? '') === 'team')
                <h1 style="font-size: 24px; font-weight: 700; color: #22272B; margin: 0 0 4px;">Team Monitor</h1>
                <p class="muted" style="margin: 0; font-size: 13.5px;">Monitor project team members, workload distribution, and task allocations.</p>
            @elseif(($view ?? '') === 'managed')
                <h1 style="font-size: 24px; font-weight: 700; color: #22272B; margin: 0 0 4px;">Task Monitor</h1>
                <p class="muted" style="margin: 0; font-size: 13.5px;">Monitor active tasks across projects you manage.</p>
            @else
                <h1 style="font-size: 24px; font-weight: 700; color: #22272B; margin: 0 0 4px;">My Tasks</h1>
                <p class="muted" style="margin: 0; font-size: 13.5px;">Manage and update tasks assigned directly to you.</p>
            @endif
        </div>

        @if($hasMonitoredProjects)
            <!-- Top Segmented Control (Assigned to Me vs Managed by Me) -->
            <div style="display: inline-flex; background: #F1F5F9; padding: 3px; border-radius: 8px; border: 1px solid #E2E8F0;">
                <a href="{{ route('tasks.mine', ['view' => 'assigned']) }}"
                   style="padding: 6px 18px; font-size: 13px; font-weight: 650; text-decoration: none; border-radius: 6px; transition: all 0.15s ease; {{ $view === 'assigned' ? 'background: #315F58; color: #FFFFFF; box-shadow: 0 1px 2px rgba(0,0,0,0.06);' : 'color: #475569;' }}">
                    Assigned to Me
                </a>
                <a href="{{ route('tasks.mine', ['view' => 'managed']) }}"
                   style="padding: 6px 18px; font-size: 13px; font-weight: 650; text-decoration: none; border-radius: 6px; transition: all 0.15s ease; {{ $view === 'managed' ? 'background: #315F58; color: #FFFFFF; box-shadow: 0 1px 2px rgba(0,0,0,0.06);' : 'color: #475569;' }}">
                    Managed by Me ({{ $monitoredProjects->count() }})
                </a>
            </div>
        @endif
    </div>

    @if($view === 'assigned')
        <!-- =========================================================================
             VIEW 1: ASSIGNED TO ME
             ========================================================================= -->
        <!-- 4 Simple Status Filter KPI Cards -->
        <div class="projects-kpi-row" style="margin-bottom: 20px;">
            <a href="{{ route('tasks.mine', ['view' => 'assigned', 'filter' => 'open']) }}" 
               class="projects-kpi-card {{ ($filters['filter'] ?? 'open') === 'open' ? 'active-filter-card' : '' }}" 
               style="text-decoration: none; color: inherit;">
                <span class="projects-kpi-label">Open Tasks</span>
                <div class="projects-kpi-val">{{ $assignedMetrics['open'] ?? 0 }}</div>
                <small class="muted" style="font-size: 11px;">Active work items</small>
            </a>
            <a href="{{ route('tasks.mine', ['view' => 'assigned', 'filter' => 'due_soon']) }}" 
               class="projects-kpi-card {{ ($filters['filter'] ?? '') === 'due_soon' ? 'active-filter-card' : '' }} {{ ($assignedMetrics['due_soon'] ?? 0) > 0 ? 'kpi-card-warning' : '' }}" 
               style="text-decoration: none; color: inherit;">
                <span class="projects-kpi-label">Due Soon</span>
                <div class="projects-kpi-val {{ ($assignedMetrics['due_soon'] ?? 0) > 0 ? 'text-warning' : '' }}">{{ $assignedMetrics['due_soon'] ?? 0 }}</div>
                <small class="muted" style="font-size: 11px;">Due in next 7 days</small>
            </a>
            <a href="{{ route('tasks.mine', ['view' => 'assigned', 'filter' => 'overdue']) }}" 
               class="projects-kpi-card {{ ($filters['filter'] ?? '') === 'overdue' ? 'active-filter-card' : '' }} {{ ($assignedMetrics['overdue'] ?? 0) > 0 ? 'kpi-card-danger' : '' }}" 
               style="text-decoration: none; color: inherit;">
                <span class="projects-kpi-label">Overdue Tasks</span>
                <div class="projects-kpi-val {{ ($assignedMetrics['overdue'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $assignedMetrics['overdue'] ?? 0 }}</div>
                <small class="muted" style="font-size: 11px;">Passed scheduled deadline</small>
            </a>
            <a href="{{ route('tasks.mine', ['view' => 'assigned', 'filter' => 'completed']) }}" 
               class="projects-kpi-card {{ ($filters['filter'] ?? '') === 'completed' ? 'active-filter-card' : '' }}" 
               style="text-decoration: none; color: inherit;">
                <span class="projects-kpi-label">Completed</span>
                <div class="projects-kpi-val">{{ $assignedMetrics['completed'] ?? 0 }}</div>
                <small class="muted" style="font-size: 11px;">Finished assignments</small>
            </a>
        </div>

        <!-- Search and Project Filter Toolbar -->
        <form method="get" action="{{ route('tasks.mine') }}" class="projects-toolbar-wrap" style="margin-bottom: 16px;">
            <input type="hidden" name="view" value="assigned">
            <input type="hidden" name="filter" value="{{ $filters['filter'] ?? 'open' }}">

            <div class="projects-search-input-wrap">
                <input type="text" name="q" class="projects-search-input" placeholder="Search tasks by name or code..." value="{{ $filters['q'] ?? '' }}" autocomplete="off">
            </div>

            @if(isset($assignedProjects) && $assignedProjects->count() > 1)
                <select name="project_id" class="projects-filter-select" onchange="this.form.submit()">
                    <option value="">All Projects ({{ $assignedProjects->count() }})</option>
                    @foreach($assignedProjects as $p)
                        <option value="{{ $p->id }}" @selected(($filters['project_id'] ?? '') == $p->id)>{{ $p->name }} [{{ $p->code }}]</option>
                    @endforeach
                </select>
            @endif

            @if(!empty($filters['q']) || !empty($filters['project_id']) || ($filters['filter'] ?? 'open') !== 'open')
                <a href="{{ route('tasks.mine', ['view' => 'assigned']) }}" class="btn btn-ghost btn-sm" style="color: var(--muted); height: 38px; line-height: 36px;">Reset</a>
            @endif
        </form>

        <!-- Task Table Card -->
        <div class="projects-table-card">
            <div class="table-wrap">
                <table class="projects-table">
                    <thead>
                        <tr>
                            <th>TASK</th>
                            <th>PROJECT</th>
                            <th>START</th>
                            <th>DEADLINE</th>
                            <th>PRIORITY</th>
                            <th>STATUS</th>
                            <th>PROGRESS</th>
                            <th class="right">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tasks as $task)
                            @php
                                $isPast = $task->ends_at->isPast() && $task->progress < 100;
                                $isSubtask = !empty($task->parent_task_id);
                            @endphp
                            <tr>
                                <!-- Task Name & Code -->
                                <td>
                                    <div class="project-title-cell {{ $isSubtask ? 'pl-16' : '' }}">
                                        <a href="{{ route('projects.show', $task->project) }}" 
                                           style="text-decoration: none; font-size: 13.5px; font-weight: 650; color: #111827;"
                                           onmouseover="this.style.color='#315F58'" 
                                           onmouseout="this.style.color='#111827'">
                                            {{ $task->title }}
                                        </a>
                                        <span class="project-code-sub">
                                            {{ $task->code }}
                                            @if($isSubtask)
                                                · <small style="color: var(--muted);">Subtask of {{ $task->parent?->title }}</small>
                                            @endif
                                        </span>
                                    </div>
                                </td>

                                <!-- Project -->
                                <td>
                                    <a href="{{ route('projects.show', $task->project) }}" style="color: #374151; font-weight: 600; text-decoration: none; font-size: 13px;">
                                        {{ $task->project->name }}
                                    </a>
                                    <small class="block muted">{{ $task->project->code }}</small>
                                </td>

                                <!-- Start Date & Time -->
                                <td>
                                    <span class="muted text-sm">{{ $task->starts_at->format('d M Y') }}</span>
                                    <small class="block muted" style="font-size: 11px;">{{ $task->starts_at->format('h:i A') }}</small>
                                </td>

                                <!-- Deadline Date & Time -->
                                <td>
                                    <span class="{{ $isPast ? 'text-danger font-semibold' : 'muted' }} text-sm">
                                        {{ $task->ends_at->format('d M Y') }}
                                    </span>
                                    <small class="block {{ $isPast ? 'text-danger' : 'muted' }}" style="font-size: 11px;">
                                        {{ $task->ends_at->format('h:i A') }}
                                        @if($isPast) · Overdue @endif
                                    </small>
                                </td>

                                <!-- Priority -->
                                <td>
                                    @php
                                        $priorityClass = match($task->priority) {
                                            'high' => 'pill-danger',
                                            'low' => 'pill-neutral',
                                            default => 'pill-warning',
                                        };
                                    @endphp
                                    <span class="status-pill {{ $priorityClass }}" style="font-size: 11px; padding: 2px 7px;">
                                        {{ ucfirst($task->priority) }}
                                    </span>
                                </td>

                                <!-- Inline Status Update Form -->
                                <td>
                                    @if(app(\App\Services\Access::class)->task(auth()->user(), $task, 'update'))
                                        <form action="{{ route('tasks.update', $task) }}" method="post" style="margin: 0;">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="progress" value="{{ $task->progress }}">
                                            <select name="status" onchange="this.form.submit()" style="height: 30px; font-size: 12px; padding: 2px 8px; border-radius: var(--radius); background: var(--surface); border: 1px solid var(--border); font-weight: 600; color: #374151;">
                                                @foreach(['todo' => 'To Do', 'in_progress' => 'In Progress', 'on_hold' => 'On Hold', 'completed' => 'Completed'] as $val => $lbl)
                                                    <option value="{{ $val }}" @selected($task->status === $val)>{{ $lbl }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        <span class="uwt-status-pill status-{{ $task->status }}">
                                            {{ ucwords(str_replace('_', ' ', $task->status)) }}
                                        </span>
                                    @endif
                                </td>

                                <!-- Progress Cell -->
                                <td>
                                    <div class="progress-col-wrap">
                                        <span class="progress-pct-val">{{ $task->progress }}%</span>
                                        <div class="progress-track-wide">
                                            <div class="progress-fill" style="width: {{ $task->progress }}%;"></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Action Button (Redirects directly to project) -->
                                <td class="right">
                                    <a href="{{ route('projects.show', $task->project) }}" class="btn btn-outline btn-xs">
                                        Details →
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="empty">
                                    <b>All caught up</b>
                                    <p class="muted">No {{ ($filters['filter'] ?? '') !== 'all' ? ($filters['filter'] ?? '') : '' }} tasks found for this view.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($tasks->hasPages())
                <div class="projects-pagination-bar">
                    <span>Showing {{ $tasks->firstItem() ?? 0 }} to {{ $tasks->lastItem() ?? 0 }} of {{ $tasks->total() }} tasks</span>
                    <div class="pagination-pages">
                        @if($tasks->onFirstPage())
                            <span class="page-nav-btn disabled">‹</span>
                        @else
                            <a href="{{ $tasks->previousPageUrl() }}" class="page-nav-btn">‹</a>
                        @endif
                        <span class="page-num-btn active">{{ $tasks->currentPage() }}</span>
                        @if($tasks->hasMorePages())
                            <a href="{{ $tasks->nextPageUrl() }}" class="page-nav-btn">›</a>
                        @else
                            <span class="page-nav-btn disabled">›</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

    @else
        <!-- =========================================================================
             VIEW 2: MANAGED BY ME (PROJECT TASK MONITOR & TEAM MONITOR)
             ========================================================================= -->
        <!-- Project Selector and Sub-Tab Navigation Bar -->
        <div class="card" style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
            <!-- Project Scope Selector -->
            <form method="get" action="{{ route('tasks.mine') }}" style="display: flex; align-items: center; gap: 10px; margin: 0;">
                <input type="hidden" name="view" value="managed">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <label for="project-scope-select" style="font-size: 13px; font-weight: 650; color: #475569; white-space: nowrap;">
                    Project Scope:
                </label>
                <select id="project-scope-select" name="project_id" onchange="this.form.submit()" style="height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 12px; font-size: 13px; font-weight: 600; color: #22272B; background: #FFFFFF; min-width: 280px;">
                    <option value="">All My Managed Projects ({{ $monitoredProjects->count() }})</option>
                    @foreach($monitoredProjects as $p)
                        <option value="{{ $p->id }}" @selected($projectId == $p->id)>{{ $p->code }} · {{ $p->name }}</option>
                    @endforeach
                </select>
            </form>

            <!-- Sub-Tabs: [ Task Monitor ] and [ Team Monitor ] -->
            <div style="display: flex; gap: 6px; background: #F1F5F9; padding: 3px; border-radius: 6px; border: 1px solid #E2E8F0;">
                <a href="{{ route('tasks.mine', ['view' => 'managed', 'tab' => 'tasks', 'project_id' => $projectId]) }}"
                   style="padding: 6px 16px; font-size: 12.5px; font-weight: 650; text-decoration: none; border-radius: 5px; {{ $tab === 'tasks' ? 'background: #FFFFFF; color: #315F58; box-shadow: 0 1px 2px rgba(0,0,0,0.06);' : 'color: #64748B;' }}">
                    Task Monitor
                </a>
                <a href="{{ route('tasks.mine', ['view' => 'managed', 'tab' => 'team', 'project_id' => $projectId]) }}"
                   style="padding: 6px 16px; font-size: 12.5px; font-weight: 650; text-decoration: none; border-radius: 5px; {{ $tab === 'team' ? 'background: #FFFFFF; color: #315F58; box-shadow: 0 1px 2px rgba(0,0,0,0.06);' : 'color: #64748B;' }}">
                    Team Monitor
                </a>
            </div>
        </div>

        @if($tab === 'tasks')
            <!-- ---------------------------------------------------------------------
                 SUB-TAB A: TASK MONITOR
                 --------------------------------------------------------------------- -->
            <!-- 5 Summary Metrics Cards -->
            <div class="kpi-row" style="grid-template-columns: repeat(5, minmax(0, 1fr)); margin-bottom: 20px;">
                <div class="kpi-card" style="background: #FFFFFF; border-color: #E5E7EB;">
                    <span class="kpi-label">Total Tasks</span>
                    <div class="kpi-value" style="color: #22272B;">{{ $taskMetrics['total_tasks'] ?? 0 }}</div>
                    <span class="kpi-sub">In monitored scope</span>
                </div>
                <div class="kpi-card" style="background: #FFFFFF; border-color: #E5E7EB;">
                    <span class="kpi-label">Open Tasks</span>
                    <div class="kpi-value" style="color: #315F58;">{{ $taskMetrics['open_tasks'] ?? 0 }}</div>
                    <span class="kpi-sub">Active executable tasks</span>
                </div>
                <div class="kpi-card {{ ($taskMetrics['overdue_tasks'] ?? 0) > 0 ? 'kpi-card-danger' : '' }}" style="border-color: {{ ($taskMetrics['overdue_tasks'] ?? 0) > 0 ? '#DC2626' : '#E5E7EB' }};">
                    <span class="kpi-label">Overdue Tasks</span>
                    <div class="kpi-value {{ ($taskMetrics['overdue_tasks'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $taskMetrics['overdue_tasks'] ?? 0 }}</div>
                    <span class="kpi-sub">Passed deadline</span>
                </div>
                <div class="kpi-card {{ ($taskMetrics['critical_tasks'] ?? 0) > 0 ? 'kpi-card-danger' : '' }}" style="border-color: {{ ($taskMetrics['critical_tasks'] ?? 0) > 0 ? '#DC2626' : '#E5E7EB' }};">
                    <span class="kpi-label">Critical Tasks</span>
                    <div class="kpi-value {{ ($taskMetrics['critical_tasks'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $taskMetrics['critical_tasks'] ?? 0 }}</div>
                    <span class="kpi-sub">Zero float critical path</span>
                </div>
                <div class="kpi-card {{ ($taskMetrics['unassigned_tasks'] ?? 0) > 0 ? 'kpi-card-warning' : '' }}" style="border-color: {{ ($taskMetrics['unassigned_tasks'] ?? 0) > 0 ? '#D97706' : '#E5E7EB' }};">
                    <span class="kpi-label">Unassigned Tasks</span>
                    <div class="kpi-value {{ ($taskMetrics['unassigned_tasks'] ?? 0) > 0 ? 'text-warning' : '' }}">{{ $taskMetrics['unassigned_tasks'] ?? 0 }}</div>
                    <span class="kpi-sub">Require resource assignment</span>
                </div>
            </div>

            <!-- Quick Filter Buttons & Toolbar -->
            <div class="card" style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px 18px; margin-bottom: 20px;">
                <!-- Quick Filter Pills -->
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
                    @php
                        $activeF = $filters['filter'] ?? 'all';
                        $pillLinks = [
                            'all' => 'All Tasks',
                            'overdue' => 'Overdue',
                            'critical' => 'Critical Path',
                            'blocked' => 'Blocked',
                            'unassigned' => 'Unassigned',
                            'due_soon' => 'Due Soon (7d)',
                            'needs_update' => 'Needs Update (>7d)',
                            'completed' => 'Completed',
                        ];
                    @endphp
                    @foreach($pillLinks as $k => $lbl)
                        <a href="{{ route('tasks.mine', array_merge(request()->query(), ['view' => 'managed', 'tab' => 'tasks', 'filter' => $k])) }}"
                           style="padding: 5px 12px; font-size: 12px; font-weight: 600; text-decoration: none; border-radius: 6px; transition: all 0.15s ease; {{ $activeF === $k ? 'background: #315F58; color: #FFFFFF;' : 'background: #F8FAFC; color: #475569; border: 1px solid #E2E8F0;' }}">
                            {{ $lbl }}
                        </a>
                    @endforeach
                </div>

                <!-- Detailed Filter Row -->
                <form method="get" action="{{ route('tasks.mine') }}" style="display: grid; grid-template-columns: 2fr 1.2fr 1.2fr auto; gap: 12px; align-items: end; margin: 0;">
                    <input type="hidden" name="view" value="managed">
                    <input type="hidden" name="tab" value="tasks">
                    <input type="hidden" name="filter" value="{{ $filters['filter'] ?? 'all' }}">
                    @if($projectId)
                        <input type="hidden" name="project_id" value="{{ $projectId }}">
                    @endif

                    <!-- Search Input -->
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 650; color: #475569; margin-bottom: 4px;">Search Tasks</label>
                        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by task title, code, assignee, project..." style="width: 100%; height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 12px; font-size: 13px;">
                    </div>

                    <!-- Assignee Filter -->
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 650; color: #475569; margin-bottom: 4px;">Assignee</label>
                        <select name="user_id" style="width: 100%; height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 12.5px;">
                            <option value="">All Assignees</option>
                            @foreach($assignees as $u)
                                <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 650; color: #475569; margin-bottom: 4px;">Status</label>
                        <select name="status" style="width: 100%; height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 12.5px;">
                            <option value="">All Statuses</option>
                            @foreach(['todo' => 'To Do', 'in_progress' => 'In Progress', 'on_hold' => 'On Hold', 'completed' => 'Completed'] as $v => $l)
                                <option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary btn-sm" style="height: 38px; padding: 0 14px; background: #315F58; color: #FFFFFF; font-weight: 600;">
                            Filter
                        </button>
                        <a href="{{ route('tasks.mine', ['view' => 'managed', 'tab' => 'tasks', 'project_id' => $projectId]) }}" class="btn btn-outline btn-sm" style="height: 38px; line-height: 36px; padding: 0 12px; color: #64748B;">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Task Monitor Table Card -->
            <div class="projects-table-card">
                <div class="table-wrap">
                    <table class="projects-table">
                        <thead>
                            <tr>
                                <th>TASK & PROJECT</th>
                                <th>ASSIGNED TO</th>
                                <th>START</th>
                                <th>DEADLINE</th>
                                <th>PROGRESS</th>
                                <th>STATUS</th>
                                <th>SCHEDULE HEALTH</th>
                                <th class="right">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tasks as $task)
                                @php
                                    $health = $task->schedule_health;
                                    $isOverdue = $health['is_overdue'] ?? false;
                                    $isCritical = $health['is_critical'] ?? false;
                                    $isBlocked = $health['is_blocked'] ?? false;
                                    $canManage = app(\App\Services\Access::class)->canManageTask(auth()->user(), $task->project) || auth()->user()->isPmo();
                                @endphp
                                <tr>
                                    <!-- Task & Project -->
                                    <td>
                                        <div class="project-title-cell">
                                            <a href="{{ route('projects.show', $task->project) }}" 
                                               style="text-decoration: none; font-size: 13.5px; font-weight: 650; color: #111827;"
                                               onmouseover="this.style.color='#315F58'" 
                                               onmouseout="this.style.color='#111827'">
                                                {{ $task->title }}
                                            </a>
                                            <span class="project-code-sub">
                                                <span style="color: #64748B; font-weight: 600;">{{ $task->code }}</span>
                                                · <a href="{{ route('projects.show', $task->project) }}" style="color: #315F58; font-weight: 600; text-decoration: none;">{{ $task->project->name }}</a>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Assigned To -->
                                    <td>
                                        @if($task->assignees->isNotEmpty())
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                @php
                                                    $firstAssignee = $task->assignees->first();
                                                    $parts = preg_split('/\s+/', trim($firstAssignee->name));
                                                    $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                                                @endphp
                                                <span class="avatar" style="width: 24px; height: 24px; font-size: 10px; background: #E2E8F0; color: #334155;">{{ $initials }}</span>
                                                <span style="font-size: 13px; font-weight: 600; color: #334155;">{{ $firstAssignee->name }}</span>
                                                @if($task->assignees->count() > 1)
                                                    <small class="muted">+{{ $task->assignees->count() - 1 }}</small>
                                                @endif
                                            </div>
                                        @else
                                            <span class="status-pill pill-warning" style="font-size: 11px; padding: 2px 7px;">Unassigned</span>
                                        @endif
                                    </td>

                                    <!-- Start -->
                                    <td>
                                        <span class="muted text-sm">{{ $task->starts_at->format('d M Y') }}</span>
                                    </td>

                                    <!-- Deadline -->
                                    <td>
                                        <span class="{{ $isOverdue ? 'text-danger font-semibold' : 'muted' }} text-sm">
                                            {{ $task->ends_at->format('d M Y') }}
                                        </span>
                                        @if($isOverdue)
                                            <small class="block text-danger" style="font-size: 11px;">Overdue</small>
                                        @endif
                                    </td>

                                    <!-- Progress -->
                                    <td>
                                        <div class="progress-col-wrap">
                                            <span class="progress-pct-val">{{ $task->progress }}%</span>
                                            <div class="progress-track-wide">
                                                <div class="progress-fill" style="width: {{ $task->progress }}%;"></div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Status (inline select if permitted) -->
                                    <td>
                                        @if($canManage && $task->project->status === 'active')
                                            <form action="{{ route('tasks.update', $task) }}" method="post" style="margin: 0;">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="progress" value="{{ $task->progress }}">
                                                <select name="status" onchange="this.form.submit()" style="height: 30px; font-size: 12px; padding: 2px 8px; border-radius: var(--radius); background: var(--surface); border: 1px solid var(--border); font-weight: 600; color: #374151;">
                                                    @foreach(['todo' => 'To Do', 'in_progress' => 'In Progress', 'on_hold' => 'On Hold', 'completed' => 'Completed'] as $val => $lbl)
                                                        <option value="{{ $val }}" @selected($task->status === $val)>{{ $lbl }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @else
                                            <span class="uwt-status-pill status-{{ $task->status }}">
                                                {{ ucwords(str_replace('_', ' ', $task->status)) }}
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Schedule Health Badge -->
                                    <td>
                                        @php
                                            $hKey = $health['key'] ?? 'on_track';
                                            $hLabel = $health['label'] ?? 'On Track';
                                            $pillClass = match($hKey) {
                                                'critical_overdue', 'critical', 'overdue' => 'pill-danger',
                                                'blocked' => 'pill-warning',
                                                'at_risk' => 'pill-warning',
                                                'completed' => 'pill-neutral',
                                                default => 'pill-success',
                                            };
                                        @endphp
                                        <span class="status-pill {{ $pillClass }}" style="font-size: 11.5px; padding: 2px 8px; white-space: nowrap;">
                                            {{ $hLabel }}
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="right" style="white-space: nowrap;">
                                        <div style="display: inline-flex; gap: 6px; align-items: center;">
                                            <a href="{{ route('projects.show', $task->project) }}" class="btn btn-outline btn-xs">
                                                Details →
                                            </a>
                                            @if($task->progress < 100 && $task->assignees->isNotEmpty())
                                                <button type="button" 
                                                        onclick="openUserRequestUpdateDialog({{ $task->id }}, '{{ addslashes($task->title) }}', {{ json_encode($task->assignees->map(fn($a) => ['id' => $a->id, 'name' => $a->name])) }})" 
                                                        class="btn btn-outline btn-xs" 
                                                        style="padding: 2px 7px; font-size: 11.5px; color: #315F58; border-color: #CBD5E1;"
                                                        title="Request progress update">
                                                    Update Req
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="empty">
                                        <b>No tasks found</b>
                                        <p class="muted">No tasks match your selected filters in this project scope.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($tasks->hasPages())
                    <div class="projects-pagination-bar">
                        <span>Showing {{ $tasks->firstItem() ?? 0 }} to {{ $tasks->lastItem() ?? 0 }} of {{ $tasks->total() }} tasks</span>
                        <div class="pagination-pages">
                            @if($tasks->onFirstPage())
                                <span class="page-nav-btn disabled">‹</span>
                            @else
                                <a href="{{ $tasks->previousPageUrl() }}" class="page-nav-btn">‹</a>
                            @endif
                            <span class="page-num-btn active">{{ $tasks->currentPage() }}</span>
                            @if($tasks->hasMorePages())
                                <a href="{{ $tasks->nextPageUrl() }}" class="page-nav-btn">›</a>
                            @else
                                <span class="page-nav-btn disabled">›</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

        @else
            <!-- ---------------------------------------------------------------------
                 SUB-TAB B: TEAM MONITOR
                 --------------------------------------------------------------------- -->
            <!-- 4 Summary Metrics Cards -->
            <div class="kpi-row" style="grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 20px;">
                <div class="kpi-card" style="background: #FFFFFF; border-color: #E5E7EB;">
                    <span class="kpi-label">Project Members</span>
                    <div class="kpi-value" style="color: #22272B;">{{ $teamMetrics['project_members'] ?? 0 }}</div>
                    <span class="kpi-sub">Distinct team members in scope</span>
                </div>
                <div class="kpi-card" style="background: #FFFFFF; border-color: #E5E7EB;">
                    <span class="kpi-label">Open Assignments</span>
                    <div class="kpi-value" style="color: #315F58;">{{ $teamMetrics['open_assignments'] ?? 0 }}</div>
                    <span class="kpi-sub">Active task allocations</span>
                </div>
                <div class="kpi-card" style="background: #FFFFFF; border-color: #E5E7EB;">
                    <span class="kpi-label">Completed Assignments</span>
                    <div class="kpi-value" style="color: #22272B;">{{ $teamMetrics['completed_assignments'] ?? 0 }}</div>
                    <span class="kpi-sub">Finished task allocations</span>
                </div>
                <div class="kpi-card {{ ($teamMetrics['overdue_assignments'] ?? 0) > 0 ? 'kpi-card-danger' : '' }}" style="border-color: {{ ($teamMetrics['overdue_assignments'] ?? 0) > 0 ? '#DC2626' : '#E5E7EB' }};">
                    <span class="kpi-label">Overdue Assignments</span>
                    <div class="kpi-value {{ ($teamMetrics['overdue_assignments'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $teamMetrics['overdue_assignments'] ?? 0 }}</div>
                    <span class="kpi-sub">Passed deadline allocations</span>
                </div>
            </div>

            <!-- Team Filter & Search Toolbar -->
            <div class="card" style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px 18px; margin-bottom: 20px;">
                <form method="get" action="{{ route('tasks.mine') }}" style="display: grid; grid-template-columns: 2fr 1.5fr auto auto; gap: 12px; align-items: end; margin: 0;">
                    <input type="hidden" name="view" value="managed">
                    <input type="hidden" name="tab" value="team">
                    @if($projectId)
                        <input type="hidden" name="project_id" value="{{ $projectId }}">
                    @endif

                    <!-- Search Input -->
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 650; color: #475569; margin-bottom: 4px;">Search Employee</label>
                        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search employee name, title, or email..." style="width: 100%; height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 12px; font-size: 13px;">
                    </div>

                    <!-- Workload Condition -->
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 650; color: #475569; margin-bottom: 4px;">Workload Condition</label>
                        <select name="workload" style="width: 100%; height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 12.5px;">
                            <option value="">All Workload</option>
                            <option value="over_capacity" @selected(($filters['workload'] ?? '') === 'over_capacity')>Over Capacity (>100%)</option>
                            <option value="near_capacity" @selected(($filters['workload'] ?? '') === 'near_capacity')>Near Capacity (80-100%)</option>
                            <option value="within_capacity" @selected(($filters['workload'] ?? '') === 'within_capacity')>Within Capacity (<80%)</option>
                            <option value="not_configured" @selected(($filters['workload'] ?? '') === 'not_configured')>Capacity Not Configured</option>
                        </select>
                    </div>

                    <!-- Quick Checkboxes -->
                    <div style="display: flex; gap: 14px; padding-bottom: 8px;">
                        <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: #334155; cursor: pointer;">
                            <input type="checkbox" name="has_overdue" value="1" @checked(!empty($filters['has_overdue']))>
                            Has Overdue
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: #334155; cursor: pointer;">
                            <input type="checkbox" name="has_critical" value="1" @checked(!empty($filters['has_critical']))>
                            Has Critical
                        </label>
                    </div>

                    <!-- Buttons -->
                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary btn-sm" style="height: 38px; padding: 0 14px; background: #315F58; color: #FFFFFF; font-weight: 600;">
                            Filter
                        </button>
                        <a href="{{ route('tasks.mine', ['view' => 'managed', 'tab' => 'team', 'project_id' => $projectId]) }}" class="btn btn-outline btn-sm" style="height: 38px; line-height: 36px; padding: 0 12px; color: #64748B;">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Team Members Table Card -->
            <div class="projects-table-card">
                <div class="table-wrap">
                    <table class="projects-table">
                        <thead>
                            <tr>
                                <th>EMPLOYEE</th>
                                <th>PROJECT(S)</th>
                                <th>OPEN TASKS</th>
                                <th>COMPLETED</th>
                                <th>OVERDUE</th>
                                <th>CRITICAL</th>
                                <th>REMAINING EFFORT</th>
                                <th>WORKLOAD</th>
                                <th class="right">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($teamMembers as $emp)
                                @php
                                    $wm = $emp->workload_metrics;
                                    $workloadPill = match($wm['workload_key']) {
                                        'over_capacity' => 'pill-danger',
                                        'near_capacity' => 'pill-warning',
                                        'within_capacity' => 'pill-success',
                                        default => 'pill-neutral',
                                    };
                                @endphp
                                <tr>
                                    <!-- Employee -->
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span class="avatar" style="width: 32px; height: 32px; font-size: 12px; background: #E2E8F0; color: #1E293B;">{{ $wm['initials'] }}</span>
                                            <div>
                                                <button type="button" 
                                                        onclick="openUserWorkloadModal({{ $emp->id }})" 
                                                        style="background: none; border: none; padding: 0; text-align: left; cursor: pointer; font-size: 13.5px; font-weight: 650; color: #111827;">
                                                    {{ $emp->name }}
                                                </button>
                                                <small class="block muted" style="font-size: 11px;">{{ $emp->job_title ?? $emp->email }}</small>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Project(s) -->
                                    <td>
                                        <span style="font-size: 12.5px; font-weight: 600; color: #475569;">
                                            {{ $wm['projects_display'] }}
                                        </span>
                                    </td>

                                    <!-- Open Tasks -->
                                    <td>
                                        <span style="font-size: 13.5px; font-weight: 700; color: #22272B;">{{ $wm['open_tasks'] }}</span>
                                    </td>

                                    <!-- Completed -->
                                    <td>
                                        <span class="muted text-sm">{{ $wm['completed_tasks'] }}</span>
                                    </td>

                                    <!-- Overdue -->
                                    <td>
                                        @if($wm['overdue_tasks'] > 0)
                                            <span class="status-pill pill-danger" style="font-size: 11px; padding: 2px 7px;">{{ $wm['overdue_tasks'] }} overdue</span>
                                        @else
                                            <span class="muted text-sm">0</span>
                                        @endif
                                    </td>

                                    <!-- Critical -->
                                    <td>
                                        @if($wm['critical_tasks'] > 0)
                                            <span class="status-pill pill-danger" style="font-size: 11px; padding: 2px 7px;">{{ $wm['critical_tasks'] }} critical</span>
                                        @else
                                            <span class="muted text-sm">0</span>
                                        @endif
                                    </td>

                                    <!-- Remaining Effort -->
                                    <td>
                                        <span style="font-size: 13px; font-weight: 600; color: #334155;">{{ $wm['remaining_effort_text'] }}</span>
                                    </td>

                                    <!-- Workload Status -->
                                    <td>
                                        <span class="status-pill {{ $workloadPill }}" style="font-size: 11.5px; padding: 2px 8px; white-space: nowrap;">
                                            {{ $wm['workload_label'] }}
                                        </span>
                                        @if($wm['capacity'])
                                            <small class="block muted" style="font-size: 11px; margin-top: 2px;">
                                                Cap: {{ $wm['capacity'] }}h / week
                                            </small>
                                        @endif
                                    </td>

                                    <!-- Action -->
                                    <td class="right" style="white-space: nowrap;">
                                        <button type="button" 
                                                onclick="openUserWorkloadModal({{ $emp->id }})" 
                                                class="btn btn-outline btn-xs" 
                                                style="padding: 4px 12px; font-size: 12px; font-weight: 600; color: #315F58; border-color: #CBD5E1;">
                                            View Workload
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="empty">
                                        <b>No team members found</b>
                                        <p class="muted">No project members match your search or filter criteria in this scope.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($teamMembers->hasPages())
                    <div class="projects-pagination-bar">
                        <span>Showing {{ $teamMembers->firstItem() ?? 0 }} to {{ $teamMembers->lastItem() ?? 0 }} of {{ $teamMembers->total() }} members</span>
                        <div class="pagination-pages">
                            @if($teamMembers->onFirstPage())
                                <span class="page-nav-btn disabled">‹</span>
                            @else
                                <a href="{{ $teamMembers->previousPageUrl() }}" class="page-nav-btn">‹</a>
                            @endif
                            <span class="page-num-btn active">{{ $teamMembers->currentPage() }}</span>
                            @if($teamMembers->hasMorePages())
                                <a href="{{ $teamMembers->nextPageUrl() }}" class="page-nav-btn">›</a>
                            @else
                                <span class="page-nav-btn disabled">›</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>

<!-- =========================================================================
     MODAL 1: EMPLOYEE WORKLOAD DETAILS POPUP
     ========================================================================= -->
<div id="user-team-workload-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px); z-index: 9998; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #FFFFFF; border-radius: 8px; width: 100%; max-width: 900px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); border: 1px solid #CBD5E1; display: flex; flex-direction: column;">
        <div style="padding: 18px 24px; border-bottom: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: space-between; background: #F8FAFC;">
            <div>
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B;">Employee Workload & Assigned Tasks</span>
                <h3 id="uwm-name" style="margin: 2px 0 0; font-size: 18px; font-weight: 700; color: #22272B;">Employee Name</h3>
                <small id="uwm-roles" style="display: block; color: #64748B; font-size: 12px; margin-top: 2px;">Roles</small>
            </div>
            <button type="button" onclick="closeUserWorkloadModal()" style="border: none; background: transparent; font-size: 20px; color: #64748B; cursor: pointer; padding: 4px 8px;">✕</button>
        </div>

        <div id="uwm-body" style="padding: 24px; font-size: 13px;">
            <div style="text-align: center; padding: 40px; color: #64748B;">Loading workload data...</div>
        </div>

        <div style="padding: 12px 24px; border-top: 1px solid #E5E7EB; background: #F8FAFC; display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeUserWorkloadModal()" class="btn btn-outline btn-sm">Close</button>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: REQUEST PROGRESS UPDATE POPUP
     ========================================================================= -->
<div id="user-request-update-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #FFFFFF; border-radius: 8px; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); border: 1px solid #CBD5E1; padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #22272B;">Request Progress Update</h3>
            <button type="button" onclick="closeUserRequestUpdateDialog()" style="border: none; background: transparent; font-size: 18px; color: #64748B; cursor: pointer;">✕</button>
        </div>

        <form id="user-request-update-form" onsubmit="event.preventDefault(); submitUserRequestUpdate();">
            <input type="hidden" id="user-req-task-id">

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 650; margin-bottom: 4px; color: #475569;">Task</label>
                <div id="user-req-task-title" style="font-size: 13.5px; font-weight: 600; color: #22272B; background: #F8FAFC; padding: 8px 12px; border-radius: 6px; border: 1px solid #E2E8F0;"></div>
            </div>

            <div style="margin-bottom: 14px;">
                <label for="user-req-target-user" style="display: block; font-size: 12.5px; font-weight: 650; margin-bottom: 4px; color: #22272B;">
                    Send Request To <span style="color:#DC2626;">*</span>
                </label>
                <select id="user-req-target-user" required style="width: 100%; height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 13px; background: #FFFFFF; color: #22272B;"></select>
            </div>

            <div style="margin-bottom: 18px;">
                <label for="user-req-message" style="display: block; font-size: 12.5px; font-weight: 650; margin-bottom: 4px; color: #22272B;">Message / Note (Optional)</label>
                <textarea id="user-req-message" rows="3" placeholder="e.g. Please provide your latest progress status before our PMO sync..." style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 8px 12px; font-size: 13px; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeUserRequestUpdateDialog()" class="btn btn-outline btn-sm">Cancel</button>
                <button type="submit" id="btn-submit-user-req" class="btn btn-primary btn-sm" style="font-weight: 650; background: #315F58; color: #FFFFFF;">Send Request</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: CONTROLLED TASK REASSIGNMENT POPUP
     ========================================================================= -->
<div id="user-reassign-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #FFFFFF; border-radius: 8px; width: 100%; max-width: 520px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); border: 1px solid #CBD5E1; padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #22272B;">Reassign Task</h3>
            <button type="button" onclick="closeUserReassignModal()" style="border: none; background: transparent; font-size: 18px; color: #64748B; cursor: pointer;">✕</button>
        </div>

        <form id="user-reassign-task-form" onsubmit="event.preventDefault(); submitUserTaskReassignment();">
            <input type="hidden" id="user-reassign-task-id">
            <input type="hidden" id="user-reassign-old-user-id">

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 650; margin-bottom: 4px; color: #475569;">Task</label>
                <div id="user-reassign-task-display" style="font-size: 13.5px; font-weight: 600; color: #22272B; background: #F8FAFC; padding: 8px 12px; border-radius: 6px; border: 1px solid #E2E8F0;"></div>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 650; margin-bottom: 4px; color: #475569;">Current Assignee</label>
                <div id="user-reassign-old-user-display" style="font-size: 13px; font-weight: 500; color: #64748B; padding: 0 4px;"></div>
            </div>

            <div style="margin-bottom: 14px;">
                <label for="user-reassign-new-user-select" style="display: block; font-size: 12.5px; font-weight: 650; margin-bottom: 4px; color: #22272B;">
                    New Assignee (Eligible Project Members) <span style="color:#DC2626;">*</span>
                </label>
                <select id="user-reassign-new-user-select" required style="width: 100%; height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 13px; background: #FFFFFF; color: #22272B;"></select>
                <small style="display:block;font-size:11.5px;color:#64748B;margin-top:3px;">Only authorized team members of this project are eligible.</small>
            </div>

            <div style="margin-bottom: 18px;">
                <label for="user-reassign-reason" style="display: block; font-size: 12.5px; font-weight: 650; margin-bottom: 4px; color: #22272B;">Reassignment Reason</label>
                <textarea id="user-reassign-reason" rows="2" placeholder="Explain why this task is being reassigned..." style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 8px 12px; font-size: 13px; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeUserReassignModal()" class="btn btn-outline btn-sm">Cancel</button>
                <button type="submit" id="btn-submit-user-reassign" class="btn btn-primary btn-sm" style="font-weight: 650; background: #315F58; color: #FFFFFF;">Confirm Reassignment</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let currentViewingUserId = null;
    const currentScopeProjectId = {{ !empty($projectId) ? (int)$projectId : 'null' }};

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    /* =========================================================================
       1. EMPLOYEE WORKLOAD MODAL
       ========================================================================= */
    function openUserWorkloadModal(userId) {
        currentViewingUserId = userId;
        const backdrop = document.getElementById('user-team-workload-modal');
        const body = document.getElementById('uwm-body');
        const nameEl = document.getElementById('uwm-name');
        const rolesEl = document.getElementById('uwm-roles');

        backdrop.style.display = 'flex';
        body.innerHTML = '<div style="text-align:center;padding:40px;color:#64748B;">Loading employee workload details...</div>';

        let url = `/tasks/team-member/${userId}/details`;
        if (currentScopeProjectId) {
            url += `?project_id=${currentScopeProjectId}`;
        }

        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                body.innerHTML = `<div style="color:#DC2626;padding:20px;text-align:center;">${res.message || 'Unable to load workload details.'}</div>`;
                return;
            }

            const data = res.data;
            nameEl.textContent = `${data.employee.name} (${data.employee.job_title})`;
            rolesEl.textContent = data.employee.roles_summary;

            let html = `
                <!-- Top Summary Cards -->
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px 14px;">
                        <span style="font-size:11px;font-weight:650;color:#64748B;text-transform:uppercase;">Open Tasks</span>
                        <div style="font-size:20px;font-weight:700;color:#22272B;margin-top:2px;">${data.summary.open_tasks}</div>
                    </div>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px 14px;">
                        <span style="font-size:11px;font-weight:650;color:#64748B;text-transform:uppercase;">Overdue Tasks</span>
                        <div style="font-size:20px;font-weight:700;color:${data.summary.overdue_tasks > 0 ? '#DC2626' : '#22272B'};margin-top:2px;">${data.summary.overdue_tasks}</div>
                    </div>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px 14px;">
                        <span style="font-size:11px;font-weight:650;color:#64748B;text-transform:uppercase;">Critical Tasks</span>
                        <div style="font-size:20px;font-weight:700;color:${data.summary.critical_tasks > 0 ? '#DC2626' : '#22272B'};margin-top:2px;">${data.summary.critical_tasks}</div>
                    </div>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px 14px;">
                        <span style="font-size:11px;font-weight:650;color:#64748B;text-transform:uppercase;">Remaining Effort</span>
                        <div style="font-size:16px;font-weight:700;color:#315F58;margin-top:4px;">${data.summary.remaining_effort}</div>
                        <small style="color:#64748B;font-size:11px;">Cap: ${data.summary.capacity}</small>
                    </div>
                </div>

                <!-- Assigned Tasks Table -->
                <div>
                    <div style="font-size:13px;font-weight:700;color:#22272B;margin-bottom:8px;">Assigned Tasks in Scope (${data.tasks.length})</div>
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px;border:1px solid #E5E7EB;border-radius:6px;overflow:hidden;">
                        <thead>
                            <tr style="background:#F8FAFC;border-bottom:1px solid #E5E7EB;text-align:left;">
                                <th style="padding:8px 12px;">Task</th>
                                <th style="padding:8px 12px;">Project</th>
                                <th style="padding:8px 12px;">Deadline</th>
                                <th style="padding:8px 12px;text-align:center;">Progress</th>
                                <th style="padding:8px 12px;">Status</th>
                                <th style="padding:8px 12px;text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.tasks.length ? data.tasks.map(t => `
                                <tr style="border-bottom:1px solid #F1F5F9;">
                                    <td style="padding:8px 12px;">
                                        <div style="font-weight:600;color:#22272B;">
                                            <a href="/tasks/${t.id}" style="color:#22272B;text-decoration:none;" onmouseover="this.style.color='#315F58'" onmouseout="this.style.color='#22272B'">
                                                <span style="color:#64748B;">${escapeHtml(t.code)}</span> ${escapeHtml(t.title)}
                                            </a>
                                            ${t.is_critical ? '<span class="status-pill pill-danger" style="font-size:10px;margin-left:4px;">Critical</span>' : ''}
                                            ${t.is_overdue ? '<span class="status-pill pill-danger" style="font-size:10px;margin-left:4px;">Overdue</span>' : ''}
                                        </div>
                                    </td>
                                    <td style="padding:8px 12px;color:#475569;">${escapeHtml(t.project_name)}</td>
                                    <td style="padding:8px 12px;color:${t.is_overdue ? '#DC2626;font-weight:600;' : '#475569;'}">${escapeHtml(t.deadline)}</td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:600;">${t.progress}%</td>
                                    <td style="padding:8px 12px;"><span class="status-pill status-${escapeHtml(t.status)}" style="font-size:11px;">${escapeHtml(t.status_label)}</span></td>
                                    <td style="padding:8px 12px;text-align:right;white-space:nowrap;">
                                        <div style="display:inline-flex;gap:4px;">
                                            ${t.progress < 100 ? `
                                                <button type="button" 
                                                        onclick="openUserRequestUpdateDialog(${t.id}, '${escapeHtml(t.title)}', [{id: ${data.employee.id}, name: '${escapeHtml(data.employee.name)}'}])" 
                                                        class="btn btn-outline btn-xs" 
                                                        style="font-size:11px;padding:2px 7px;">
                                                    Req Update
                                                </button>
                                            ` : ''}
                                            ${t.can_manage ? `
                                                <button type="button" 
                                                        onclick="openUserReassignModal(${t.id}, '${escapeHtml(t.title)}', ${data.employee.id}, '${escapeHtml(data.employee.name)}', ${JSON.stringify(t.eligible_members).replace(/"/g, '&quot;')})" 
                                                        class="btn btn-outline btn-xs" 
                                                        style="font-size:11px;padding:2px 7px;color:#315F58;">
                                                    Reassign
                                                </button>
                                            ` : ''}
                                        </div>
                                    </td>
                                </tr>
                            `).join('') : `<tr><td colspan="6" style="text-align:center;padding:16px;color:#64748B;">No assigned tasks in this project scope.</td></tr>`}
                        </tbody>
                    </table>
                </div>
            `;

            body.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            body.innerHTML = '<div style="padding:20px;color:#DC2626;text-align:center;">Failed to load employee details.</div>';
        });
    }

    function closeUserWorkloadModal() {
        document.getElementById('user-team-workload-modal').style.display = 'none';
    }

    /* =========================================================================
       2. REQUEST PROGRESS UPDATE DIALOG
       ========================================================================= */
    function openUserRequestUpdateDialog(taskId, taskTitle, assignees) {
        document.getElementById('user-req-task-id').value = taskId;
        document.getElementById('user-req-task-title').textContent = taskTitle;
        document.getElementById('user-req-message').value = '';

        const select = document.getElementById('user-req-target-user');
        select.innerHTML = '';

        if (Array.isArray(assignees) && assignees.length > 0) {
            assignees.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = a.name;
                select.appendChild(opt);
            });
        }

        document.getElementById('user-request-update-modal').style.display = 'flex';
    }

    function closeUserRequestUpdateDialog() {
        document.getElementById('user-request-update-modal').style.display = 'none';
    }

    function submitUserRequestUpdate() {
        const taskId = document.getElementById('user-req-task-id').value;
        const targetUserId = document.getElementById('user-req-target-user').value;
        const message = document.getElementById('user-req-message').value;
        const btn = document.getElementById('btn-submit-user-req');

        if (!targetUserId) {
            alert('Please select an assignee.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Sending...';

        fetch(`/tasks/${taskId}/request-update`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                target_user_id: targetUserId,
                message: message
            })
        })
        .then(res => res.json())
        .then(res => {
            btn.disabled = false;
            btn.textContent = 'Send Request';

            if (res.success) {
                alert(res.message);
                closeUserRequestUpdateDialog();
            } else {
                alert(res.message || 'Failed to send update request.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.textContent = 'Send Request';
            console.error(err);
            alert('A network or server error occurred.');
        });
    }

    /* =========================================================================
       3. REASSIGN TASK MODAL
       ========================================================================= */
    function openUserReassignModal(taskId, taskTitle, oldUserId, oldUserName, eligibleMembers) {
        document.getElementById('user-reassign-task-id').value = taskId;
        document.getElementById('user-reassign-old-user-id').value = oldUserId;
        document.getElementById('user-reassign-task-display').textContent = taskTitle;
        document.getElementById('user-reassign-old-user-display').textContent = oldUserName;
        document.getElementById('user-reassign-reason').value = '';

        const select = document.getElementById('user-reassign-new-user-select');
        select.innerHTML = '<option value="">Select eligible team member...</option>';

        if (Array.isArray(eligibleMembers)) {
            eligibleMembers.forEach(m => {
                if (m.id != oldUserId) {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.name;
                    select.appendChild(opt);
                }
            });
        }

        document.getElementById('user-reassign-modal').style.display = 'flex';
    }

    function closeUserReassignModal() {
        document.getElementById('user-reassign-modal').style.display = 'none';
    }

    function submitUserTaskReassignment() {
        const taskId = document.getElementById('user-reassign-task-id').value;
        const oldUserId = document.getElementById('user-reassign-old-user-id').value;
        const newUserId = document.getElementById('user-reassign-new-user-select').value;
        const reason = document.getElementById('user-reassign-reason').value;
        const btn = document.getElementById('btn-submit-user-reassign');

        if (!newUserId) {
            alert('Please select a new assignee.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Reassigning...';

        fetch(`/tasks/${taskId}/reassign`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                old_user_id: oldUserId,
                new_user_id: newUserId,
                reason: reason
            })
        })
        .then(res => res.json())
        .then(res => {
            btn.disabled = false;
            btn.textContent = 'Confirm Reassignment';

            if (res.success) {
                alert(res.message);
                closeUserReassignModal();
                if (currentViewingUserId) {
                    openUserWorkloadModal(currentViewingUserId);
                } else {
                    location.reload();
                }
            } else {
                alert(res.message || 'Reassignment failed.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.textContent = 'Confirm Reassignment';
            console.error(err);
            alert('A network or server error occurred.');
        });
    }
</script>
@endpush

@endsection
