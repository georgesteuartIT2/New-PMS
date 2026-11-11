@extends('layouts.app')
@section('title', 'Reports')
@section('content')

<!-- Page Header -->
<div class="page-head">
    <div>
        <h1>Reports</h1>
        <p class="muted">Comprehensive corporate project insights, timelines, and accountability records.</p>
    </div>
    <div class="inline-actions">
        <a class="btn btn-outline" href="{{ route('reports.export', request()->all()) }}">Export CSV</a>
        <a class="btn btn-outline" href="{{ route('gantt.portfolio') }}">Portfolio Gantt →</a>
    </div>
</div>

<!-- 4 KPI Metric Cards -->
<div class="projects-kpi-row">
    <div class="projects-kpi-card">
        <span class="projects-kpi-label">Total Projects</span>
        <div class="projects-kpi-val">{{ $kpi['total_projects'] }}</div>
    </div>
    <div class="projects-kpi-card">
        <span class="projects-kpi-label">Active Projects</span>
        <div class="projects-kpi-val">{{ $kpi['active_projects'] }}</div>
    </div>
    <div class="projects-kpi-card {{ $kpi['overdue_tasks'] > 0 ? 'kpi-card-danger' : '' }}">
        <span class="projects-kpi-label">Overdue Tasks</span>
        <div class="projects-kpi-val {{ $kpi['overdue_tasks'] > 0 ? 'text-danger' : '' }}">{{ $kpi['overdue_tasks'] }}</div>
    </div>
    <div class="projects-kpi-card {{ $kpi['reported_delays'] > 0 ? 'kpi-card-warning' : '' }}">
        <span class="projects-kpi-label">Reported Delays</span>
        <div class="projects-kpi-val {{ $kpi['reported_delays'] > 0 ? 'text-warning' : '' }}">{{ $kpi['reported_delays'] }}</div>
    </div>
</div>

<!-- Filter Toolbar (Auto-submits on change, 0 extra clicks) -->
<form method="get" action="{{ route('reports.index') }}" class="projects-toolbar-wrap" id="reports-filter-form">
    <!-- Report Type -->
    <select name="type" class="projects-filter-select" onchange="this.form.submit()" style="font-weight: 650; min-width: 220px;">
        <option value="status" @selected($reportType === 'status')>Project Status Report</option>
        <option value="progress" @selected($reportType === 'progress')>Project Progress Report</option>
        <option value="overdue" @selected($reportType === 'overdue')>Overdue Tasks Report</option>
        <option value="critical" @selected($reportType === 'critical')>Critical Tasks & Path Report</option>
        <option value="delays" @selected($reportType === 'delays')>Delay Reasons & Action Ownership</option>
        <option value="completed" @selected($reportType === 'completed')>Completed Projects Report</option>
        <option value="workload" @selected($reportType === 'workload')>User Workload Report</option>
    </select>

    <!-- Subsidiary -->
    <select name="subsidiary_id" class="projects-filter-select" onchange="this.form.submit()">
        <option value="">All Subsidiaries</option>
        @foreach($subsidiaries as $sub)
            <option value="{{ $sub->id }}" @selected(request('subsidiary_id') == $sub->id)>{{ $sub->name }}</option>
        @endforeach
    </select>

    <!-- Status (if applicable) -->
    @if(!in_array($reportType, ['completed', 'workload']))
        <select name="status" class="projects-filter-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            @foreach(['draft'=>'Draft', 'pending'=>'Pending PM', 'active'=>'Active', 'on_hold'=>'On Hold', 'completed'=>'Completed', 'archived'=>'Archived'] as $val => $lbl)
                <option value="{{ $val }}" @selected(request('status') === $val)>{{ $lbl }}</option>
            @endforeach
        </select>
    @endif

    <!-- Project Manager (if applicable) -->
    @if(!in_array($reportType, ['workload']))
        <select name="pm_user_id" class="projects-filter-select" onchange="this.form.submit()">
            <option value="">All Project Managers</option>
            @foreach($projectManagers as $pm)
                <option value="{{ $pm->id }}" @selected(request('pm_user_id') == $pm->id)>{{ $pm->name }}</option>
            @endforeach
        </select>
    @endif

    @if(request('subsidiary_id') || request('status') || request('pm_user_id') || (request('type') && request('type') !== 'status'))
        <a href="{{ route('reports.index') }}" class="btn btn-ghost btn-sm" style="color: var(--muted);">Reset</a>
    @endif
</form>

<!-- Report Results Card -->
<div class="dashboard-card" style="padding: 0; overflow: hidden;">
    <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap;">
        <div>
            <h2 style="font-size: 16px; font-weight: 700; color: #111827; margin: 0 0 4px;">{{ $reportData['title'] }}</h2>
            <p class="muted" style="margin: 0; font-size: 13px;">{{ $reportData['subtitle'] }}</p>
        </div>
        <span class="status-pill pill-neutral">{{ count($reportData['items']) }} Records</span>
    </div>

    <div class="table-wrap">
        @if($reportType === 'progress')
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Subsidiary</th>
                        <th>Project Manager</th>
                        <th>Tasks</th>
                        <th>Progress</th>
                        <th>Deadline</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['items'] as $item)
                        <tr>
                            <td>
                                <a class="strong-link" href="{{ route('projects.show', $item) }}">
                                    <b>{{ $item->name }}</b>
                                </a>
                                <div class="project-code-sub">{{ $item->code }}</div>
                            </td>
                            <td>{{ $item->subsidiary?->name ?? '—' }}</td>
                            <td>{{ $item->manager?->name ?? 'Not assigned' }}</td>
                            <td><span class="tasks-count-badge">{{ $item->completed_tasks_count }} / {{ $item->tasks_count }}</span></td>
                            <td>
                                <div class="progress-col-wrap">
                                    <span class="progress-pct-val">{{ $item->progress }}%</span>
                                    <div class="progress-track-wide">
                                        <div class="progress-fill" style="width: {{ $item->progress }}%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="date-text">{{ $item->ends_on->format('d M Y') }}</span></td>
                            <td><span class="status-pill status-{{ $item->status }}">{{ ucwords(str_replace('_', ' ', $item->status)) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">No records found.</td></tr>
                    @endforelse
                </tbody>
            </table>

        @elseif($reportType === 'overdue')
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Project</th>
                        <th>Assigned To</th>
                        <th>Due Date</th>
                        <th>Delay Days</th>
                        <th>Status</th>
                        <th>Priority</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['items'] as $item)
                        @php
                            $daysLate = (int) ceil(abs(now()->diffInDays($item->ends_at, false)));
                        @endphp
                        <tr>
                            <td>
                                <a class="strong-link" href="{{ route('projects.show', $item->project) }}">
                                    <b>{{ $item->title }}</b>
                                </a>
                                <div class="project-code-sub">{{ $item->code }}</div>
                            </td>
                            <td>
                                <a href="{{ route('projects.show', $item->project) }}" class="muted">{{ $item->project->name }}</a>
                            </td>
                            <td>{{ $item->assignees->pluck('name')->join(', ') ?: 'Unassigned' }}</td>
                            <td><span class="date-text" style="color: var(--danger);">{{ $item->ends_at->format('d M Y') }}</span></td>
                            <td><span class="status-pill pill-danger">{{ $daysLate }} {{ $daysLate === 1 ? 'day' : 'days' }} late</span></td>
                            <td><span class="status-pill status-{{ $item->status }}">{{ ucwords(str_replace('_', ' ', $item->status)) }}</span></td>
                            <td><span class="pill pill-neutral">{{ ucfirst($item->priority) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">No overdue tasks found. All active tasks are within schedule!</td></tr>
                    @endforelse
                </tbody>
            </table>

        @elseif($reportType === 'critical')
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Schedule Health</th>
                        <th>Critical Blocker Task</th>
                        <th>Assignees</th>
                        <th>Reported Issue</th>
                        <th>Action Owner</th>
                        <th>Deadline</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['items'] as $row)
                        @php
                            $p = $row['project'];
                            $h = $row['health'];
                            $task = $row['task'];
                        @endphp
                        <tr>
                            <td>
                                <a class="strong-link" href="{{ route('projects.show', $p) }}">
                                    <b>{{ $p->name }}</b>
                                </a>
                                <div class="project-code-sub">{{ $p->code }}</div>
                            </td>
                            <td><span class="status-pill status-{{ $h['health'] }}">{{ ucwords(str_replace('_', ' ', $h['health'])) }}</span></td>
                            <td>
                                @if($task)
                                    <b>{{ $task->title }}</b>
                                @else
                                    <span class="muted">Critical path needs review</span>
                                @endif
                            </td>
                            <td>{{ $task?->assignees->pluck('name')->join(', ') ?: '—' }}</td>
                            <td>
                                @if($task && $task->openDelay)
                                    <span class="status-pill pill-warning">{{ $task->openDelay->reason_label }}</span>
                                @else
                                    <span class="muted">Schedule variance</span>
                                @endif
                            </td>
                            <td>{{ $task?->openDelay?->actionOwner?->name ?? '—' }}</td>
                            <td><span class="date-text">{{ $p->ends_on->format('d M Y') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">No critical path risks or delayed dependencies found.</td></tr>
                    @endforelse
                </tbody>
            </table>

        @elseif($reportType === 'delays')
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Blocked Task</th>
                        <th>Reason Category</th>
                        <th>Involved / Blocking Party</th>
                        <th>Action Owner</th>
                        <th>Reported By</th>
                        <th>Revised Target</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['items'] as $item)
                        <tr>
                            <td>
                                @if($item->task?->project)
                                    <a class="strong-link" href="{{ route('projects.show', $item->task->project) }}">
                                        <b>{{ $item->task->project->name }}</b>
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if($item->task)
                                    <a href="{{ route('projects.show', $item->task->project) }}">{{ $item->task->title }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <span class="status-pill pill-warning">
                                    {{ $item->reason_label }}
                                </span>
                            </td>
                            <td>{{ $item->responsible_party_name ?: ucwords($item->responsible_party_type) }}</td>
                            <td><b>{{ $item->actionOwner?->name ?? 'Not assigned' }}</b></td>
                            <td>{{ $item->reporter?->name ?? '—' }}</td>
                            <td><span class="date-text">{{ $item->revised_expected_end_at ? $item->revised_expected_end_at->format('d M Y') : 'Not set' }}</span></td>
                            <td><span class="status-pill status-{{ $item->status }}">{{ ucfirst($item->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">No delay reports logged.</td></tr>
                    @endforelse
                </tbody>
            </table>

        @elseif($reportType === 'completed')
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Subsidiary</th>
                        <th>Project Manager</th>
                        <th>Tasks</th>
                        <th>Final Progress</th>
                        <th>Completed Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['items'] as $item)
                        <tr>
                            <td>
                                <a class="strong-link" href="{{ route('projects.show', $item) }}">
                                    <b>{{ $item->name }}</b>
                                </a>
                                <div class="project-code-sub">{{ $item->code }}</div>
                            </td>
                            <td>{{ $item->subsidiary?->name ?? '—' }}</td>
                            <td>{{ $item->manager?->name ?? '—' }}</td>
                            <td>{{ $item->tasks_count }}</td>
                            <td><span class="status-pill status-completed">100%</span></td>
                            <td><span class="date-text">{{ $item->ends_on->format('d M Y') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">No completed projects yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

        @elseif($reportType === 'workload')
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Work Email</th>
                        <th>Subsidiary</th>
                        <th>Active Tasks Assigned</th>
                        <th>Overdue Tasks</th>
                        <th>Projects Managed</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['items'] as $item)
                        <tr>
                            <td>
                                <b>{{ $item->name }}</b>
                            </td>
                            <td><span class="muted">{{ $item->email }}</span></td>
                            <td>{{ $item->subsidiary?->name ?? '—' }}</td>
                            <td>
                                <span class="tasks-count-badge">{{ $item->active_tasks_count }}</span>
                            </td>
                            <td>
                                @if($item->overdue_tasks_count > 0)
                                    <span class="status-pill pill-danger">{{ $item->overdue_tasks_count }} Overdue</span>
                                @else
                                    <span class="muted">0</span>
                                @endif
                            </td>
                            <td>{{ $item->managed_projects_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">No user assignments found.</td></tr>
                    @endforelse
                </tbody>
            </table>

        @else
            <!-- Default: Project Status Report -->
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Project Code</th>
                        <th>Project Name</th>
                        <th>Subsidiary</th>
                        <th>Project Manager</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>Deadline</th>
                        <th>Progress</th>
                        <th>Tasks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['items'] as $item)
                        <tr>
                            <td><span class="code-mono">{{ $item->code }}</span></td>
                            <td>
                                <a class="strong-link" href="{{ route('projects.show', $item) }}">
                                    <b>{{ $item->name }}</b>
                                </a>
                            </td>
                            <td>{{ $item->subsidiary?->name ?? '—' }}</td>
                            <td>{{ $item->manager?->name ?? 'Not assigned' }}</td>
                            <td><span class="status-pill status-{{ $item->status }}">{{ ucwords(str_replace('_', ' ', $item->status)) }}</span></td>
                            <td><span class="date-text">{{ $item->starts_on->format('d M Y') }}</span></td>
                            <td><span class="date-text">{{ $item->ends_on->format('d M Y') }}</span></td>
                            <td>
                                <div class="progress-bar-cell">
                                    <div class="progress-track"><div class="progress-fill" style="width: {{ $item->progress }}%;"></div></div>
                                    <span class="progress-percent">{{ $item->progress }}%</span>
                                </div>
                            </td>
                            <td><span class="tasks-count-badge">{{ $item->tasks_count }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">No projects found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</div>

@endsection
