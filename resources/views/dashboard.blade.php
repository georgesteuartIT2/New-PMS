@extends('layouts.app')
@section('title', $isPmo ? 'Dashboard' : 'My Workspace')
@section('content')

<!-- Page Header -->
<div class="page-head">
    <div>
        <h1>{{ $isPmo ? 'Dashboard' : 'My Workspace' }}</h1>
        <p class="muted">{{ $isPmo ? 'Monitor projects, risks, and actions across the organization.' : 'Your projects, tasks, and what needs attention.' }}</p>
    </div>
    <div class="inline-actions">
        @if($isPmo)
            <a href="{{ route('gantt.portfolio') }}" class="btn btn-outline">Portfolio Gantt</a>
            <a href="{{ route('projects.create') }}" class="btn btn-primary">+ New Project</a>
        @else
            <a href="{{ route('tasks.mine') }}" class="btn btn-primary">My Tasks</a>
        @endif
    </div>
</div>

@if($isPmo)
    <!-- 5 KPI Metric Cards (Clean, Professional, No Icons) -->
    <div class="kpi-row">
        <a class="kpi-card kpi-shortcut" href="{{ route('projects.index') }}">
            <span class="kpi-label">Total Projects</span>
            <div class="kpi-value">{{ $kpi['totalProjects'] ?? 0 }}</div><span class="kpi-sub">Across all subsidiaries</span></a>
        <a class="kpi-card kpi-shortcut" href="{{ route('projects.index', ['status'=>'active']) }}">
            <span class="kpi-label">Active Projects</span>
            <div class="kpi-value">{{ $kpi['activeProjects'] ?? 0 }}</div><span class="kpi-sub">Currently in execution</span></a>
        <a class="kpi-card kpi-shortcut" href="{{ route('projects.index', ['status'=>'pending']) }}">
            <span class="kpi-label">Pending PM Approval</span>
            <div class="kpi-value">{{ $kpi['pendingAcceptance'] ?? 0 }}</div><span class="kpi-sub">Awaiting Project Manager approval</span></a>
        <a class="kpi-card {{ ($kpi['atRiskProjects'] ?? 0) > 0 ? 'kpi-card-warning' : '' }} kpi-shortcut" href="{{ route('projects.index', ['attention'=>1]) }}">
            <span class="kpi-label">At Risk Projects</span>
            <div class="kpi-value {{ ($kpi['atRiskProjects'] ?? 0) > 0 ? 'text-warning' : '' }}">{{ $kpi['atRiskProjects'] ?? 0 }}</div><span class="kpi-sub">Review projects needing attention</span></a>
        <a class="kpi-card {{ ($kpi['overdueTasks'] ?? 0) > 0 ? 'kpi-card-danger' : '' }} kpi-shortcut" href="{{ route('pmo.task-monitor', ['filter'=>'overdue']) }}">
            <span class="kpi-label">Overdue Tasks</span>
            <div class="kpi-value {{ ($kpi['overdueTasks'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $kpi['overdueTasks'] ?? 0 }}</div><span class="kpi-sub">Past due across {{ $kpi['overdueProjectsCount'] ?? 0 }} {{ ($kpi['overdueProjectsCount'] ?? 0) === 1 ? 'project' : 'projects' }}</span></a>
    </div>

    <!-- Main Two-Column Dashboard Layout -->
    <div class="dashboard-grid">
        <!-- Left Main Column (Needs Attention & Recent Projects) -->
        <div class="dashboard-col-main">
            <!-- 1. Needs Your Attention Card -->
            <section class="dashboard-card">
                <div class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <h2>Needs Your Attention</h2>
                        <p>Key items that need your review or action.</p>
                    </div>
                    <a class="card-link" href="{{ route('projects.index') }}">View all ({{ $totalAttentionCount ?? $attentionItems->count() }}) →</a>
                </div>

                <div class="table-wrap">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Issue</th>
                                <th>Owner / Responsible</th>
                                <th class="right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($attentionItems->take(5) as $item)
                                <tr>
                                    <td>
                                        <a class="project-code-link" href="{{ $item['action_url'] }}">
                                            <span class="code-mono">{{ $item['code'] }}</span>
                                            <span class="project-sep">·</span>
                                            <span class="project-title">{{ $item['name'] }}</span>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="issue-text">{{ $item['issue'] }}</span>
                                    </td>
                                    <td>
                                        <span class="owner-text">{{ $item['owner'] }}</span>
                                    </td>
                                    <td class="right">
                                        <a href="{{ $item['action_url'] }}" class="btn btn-outline btn-xs">{{ $item['action_label'] }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="empty-state">
                                        All projects are on track. No urgent items require attention right now.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 2. Recent Projects Card -->
            <section class="dashboard-card">
                <div class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <h2>Recent Projects</h2>
                        <p>Recently created or updated projects.</p>
                    </div>
                    <a class="card-link" href="{{ route('projects.index') }}">Open projects →</a>
                </div>

                <div class="table-wrap">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                
                                <th>Project</th>
                                <th>Project Manager</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentProjects as $p)
                                <tr>
                                    <td><a class="strong-link" href="{{ $p['url'] }}">{{ $p['name'] }}</a><small class="block muted">{{ $p['code'] }}</small></td>
                                    <td>
                                        <span class="manager-text">{{ $p['manager'] }}</span>
                                    </td>
                                    <td>
                                        <div class="progress-bar-cell">
                                            <div class="progress-track">
                                                <div class="progress-fill" style="width: {{ $p['progress'] }}%;"></div>
                                            </div>
                                            <span class="progress-percent">{{ $p['progress'] }}%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-pill status-{{ $p['status'] }}">{{ $p['status_label'] }}</span>
                                    </td>
                                    <td>
                                        <span class="date-text">{{ $p['ends_on'] ? $p['ends_on']->format('d M Y') : '—' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        No projects found. Click "+ New Project" above to create one.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Right Side Column (Portfolio Health & Upcoming Deadlines) -->
        <div class="dashboard-col-side">
            <!-- 1. Portfolio Health Donut Card -->
            <section class="dashboard-card">
                <div class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <h2>Portfolio Health</h2>
                        <p>Project status across the organization.</p>
                    </div>
                </div>

                <div class="portfolio-health-body">
                    <div class="donut-container">
                        @php
                            $c = 276.46; // 2 * pi * 44
                            $onTrackPct = $portfolioHealth['onTrackPercent'] ?? 0;
                            $atRiskPct = $portfolioHealth['atRiskPercent'] ?? 0;
                            $delayedPct = $portfolioHealth['delayedPercent'] ?? 0;

                            $onTrackDash = ($onTrackPct / 100) * $c;
                            $atRiskDash = ($atRiskPct / 100) * $c;
                            $delayedDash = ($delayedPct / 100) * $c;

                            $offset1 = 0;
                            $offset2 = -$onTrackDash;
                            $offset3 = -($onTrackDash + $atRiskDash);
                        @endphp
                        <svg class="donut-svg" viewBox="0 0 120 120">
                            <circle class="donut-bg" cx="60" cy="60" r="44" />
                            @if(($portfolioHealth['total'] ?? 0) > 0)
                                @if($onTrackDash > 0)
                                    <circle class="donut-segment segment-ontrack" cx="60" cy="60" r="44" stroke-dasharray="{{ $onTrackDash }} {{ $c }}" stroke-dashoffset="{{ $offset1 }}" />
                                @endif
                                @if($atRiskDash > 0)
                                    <circle class="donut-segment segment-atrisk" cx="60" cy="60" r="44" stroke-dasharray="{{ $atRiskDash }} {{ $c }}" stroke-dashoffset="{{ $offset2 }}" />
                                @endif
                                @if($delayedDash > 0)
                                    <circle class="donut-segment segment-delayed" cx="60" cy="60" r="44" stroke-dasharray="{{ $delayedDash }} {{ $c }}" stroke-dashoffset="{{ $offset3 }}" />
                                @endif
                            @endif
                        </svg>
                        <div class="donut-center-text">
                            <span class="donut-total">{{ $portfolioHealth['total'] ?? 0 }}</span>
                            <span class="donut-label">Projects</span>
                        </div>
                    </div>

                    <div class="donut-legend">
                        <div class="legend-row">
                            <span class="legend-dot dot-ontrack"></span>
                            <span class="legend-name">On Track</span>
                            <span class="legend-count">{{ $portfolioHealth['onTrack'] ?? 0 }}</span>
                            <span class="legend-pct">{{ $portfolioHealth['onTrackPercent'] ?? 0 }}%</span>
                        </div>
                        <div class="legend-row">
                            <span class="legend-dot dot-atrisk"></span>
                            <span class="legend-name">At Risk</span>
                            <span class="legend-count">{{ $portfolioHealth['atRisk'] ?? 0 }}</span>
                            <span class="legend-pct">{{ $portfolioHealth['atRiskPercent'] ?? 0 }}%</span>
                        </div>
                        <div class="legend-row">
                            <span class="legend-dot dot-delayed"></span>
                            <span class="legend-name">Delayed</span>
                            <span class="legend-count">{{ $portfolioHealth['delayed'] ?? 0 }}</span>
                            <span class="legend-pct">{{ $portfolioHealth['delayedPercent'] ?? 0 }}%</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Task Attention Section -->
            <section class="dashboard-card" style="margin-bottom: 20px;">
                <div class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <h2>Task Attention</h2>
                        <p>Work requiring operational PMO follow-up.</p>
                    </div>
                    <a class="card-link" href="{{ route('pmo.task-monitor') }}">Task Monitor →</a>
                </div>

                <div style="padding: 14px 18px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <a href="{{ route('pmo.task-monitor', ['filter' => 'overdue']) }}" style="display: block; padding: 10px 12px; background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 6px; text-decoration: none;">
                        <span style="display: block; font-size: 11px; font-weight: 650; color: #991B1B; text-transform: uppercase;">Overdue Tasks</span>
                        <span style="font-size: 18px; font-weight: 700; color: #B91C1C;">{{ $monitoringMetrics['overdue_tasks'] ?? 0 }}</span>
                    </a>

                    <a href="{{ route('pmo.task-monitor', ['filter' => 'unassigned']) }}" style="display: block; padding: 10px 12px; background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 6px; text-decoration: none;">
                        <span style="display: block; font-size: 11px; font-weight: 650; color: #475569; text-transform: uppercase;">Unassigned</span>
                        <span style="font-size: 18px; font-weight: 700; color: #1E293B;">{{ $monitoringMetrics['unassigned_tasks'] ?? 0 }}</span>
                    </a>

                    <a href="{{ route('pmo.task-monitor', ['filter' => 'critical']) }}" style="display: block; padding: 10px 12px; background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 6px; text-decoration: none;">
                        <span style="display: block; font-size: 11px; font-weight: 650; color: #92400E; text-transform: uppercase;">Critical Path</span>
                        <span style="font-size: 18px; font-weight: 700; color: #B45309;">{{ $monitoringMetrics['critical_tasks'] ?? 0 }}</span>
                    </a>

                    <a href="{{ route('pmo.task-monitor', ['filter' => 'needs_update']) }}" style="display: block; padding: 10px 12px; background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 6px; text-decoration: none;">
                        <span style="display: block; font-size: 11px; font-weight: 650; color: #475569; text-transform: uppercase;">Needs Update</span>
                        <span style="font-size: 18px; font-weight: 700; color: #1E293B;">{{ $monitoringMetrics['needs_update_tasks'] ?? 0 }}</span>
                    </a>
                </div>

                <div style="border-top: 1px solid var(--border); padding: 10px 18px; font-size: 12px; display: flex; justify-content: space-between; align-items: center; background: #F8FAFC; border-radius: 0 0 8px 8px;">
                    <span class="muted">Team capacity review:</span>
                    <a href="{{ route('pmo.team-monitor') }}" style="color: var(--primary); font-weight: 600; text-decoration: none;">Review Workload →</a>
                </div>
            </section>

            <!-- 2. Upcoming Deadlines Card -->
            <section class="dashboard-card">
                <div class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <h2>Upcoming Deadlines</h2>
                        <p>Key milestones and tasks due soon.</p>
                    </div>
                    <a class="card-link" href="{{ route('projects.index') }}">View all →</a>
                </div>

                <div class="deadlines-list">
                    @forelse($upcomingTasks as $task)
                        @php
                            $isPast = $task->ends_at->isPast();
                            $isDueSoon = !$isPast && $task->ends_at->diffInDays(now()) <= 7;
                            $badgeClass = $isPast ? 'pill-danger' : ($isDueSoon ? 'pill-warning' : 'pill-neutral');
                            $badgeText = $isPast ? 'Overdue' : ($isDueSoon ? 'Due Soon' : 'Upcoming');
                        @endphp
                        <div class="deadline-row">
                            <div class="deadline-date-box">
                                <span class="deadline-day">{{ $task->ends_at->format('d') }}</span>
                                <span class="deadline-month">{{ $task->ends_at->format('M') }}</span>
                            </div>
                            <div class="deadline-body">
                                <button type="button" data-task-panel="{{ route('tasks.panel', $task) }}" class="deadline-title">{{ $task->title }}</button>
                                <span class="deadline-project">{{ $task->project->code }} · {{ $task->project->name }}</span>
                            </div>
                            <span class="status-pill {{ $badgeClass }}">{{ $badgeText }}</span>
                        </div>
                    @empty
                        <div class="empty-state">
                            No upcoming task deadlines found.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@else
    <!-- =========================================================================
         SCREEN 01: MY WORKSPACE DASHBOARD (Dynamic, Clean, Zero-Icons)
         ========================================================================= -->
    <!-- 3 Compact Neutral Summary KPI Cards -->
    <div class="kpi-row user-summary">
        <a class="kpi-card kpi-shortcut" href="{{ route('projects.index') }}">
            <span class="kpi-label">My Projects</span>
            <div class="kpi-value">{{ $kpi['myProjects'] ?? 0 }}</div><span class="kpi-sub">Projects you are involved in</span></a>
        <a class="kpi-card kpi-shortcut" href="{{ route('tasks.mine') }}">
            <span class="kpi-label">My Active Tasks</span>
            <div class="kpi-value">{{ $kpi['activeTasks'] ?? 0 }}</div><span class="kpi-sub">Open Tasks assigned to you</span></a>
        <a class="kpi-card {{ ($kpi['attentionCount'] ?? 0) > 0 ? 'kpi-card-warning' : '' }} kpi-shortcut" href="#my-attention">
            <span class="kpi-label">Needs My Attention</span>
            <div class="kpi-value {{ ($kpi['attentionCount'] ?? 0) > 0 ? 'text-warning' : '' }}">{{ $kpi['attentionCount'] ?? 0 }}</div><span class="kpi-sub">Items requiring your action</span></a>
    </div>

    <!-- 2-Column Responsive Workspace Grid -->
    <div class="dashboard-grid">
        <!-- Main / Left Column (Needs My Attention + My Projects) -->
        <div class="dashboard-col-main">

            <!-- 1. Needs My Attention (Most Important Section) -->
            <section class="dashboard-card" style="margin-bottom: 20px;">
                <div class="dashboard-card-head" style="flex-wrap: wrap; gap: 10px;">
                    <div class="dashboard-card-title">
                        <h2 id="my-attention" tabindex="-1">Needs My Attention</h2>
                        <p>Key items requiring your action or review.</p>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <a href="{{ route('tasks.mine', ['view' => 'assigned', 'filter' => 'overdue']) }}" class="btn btn-outline btn-xs" style="font-size: 11.5px; padding: 3px 8px; color: #DC2626; border-color: #FCA5A5;">
                            My Overdue
                        </a>
                        <a href="{{ route('tasks.mine', ['view' => 'assigned', 'filter' => 'due_soon']) }}" class="btn btn-outline btn-xs" style="font-size: 11.5px; padding: 3px 8px; color: #D97706; border-color: #FCD34D;">
                            Due Soon
                        </a>
                        @if($hasMonitoredProjects ?? false)
                            <a href="{{ route('tasks.mine', ['view' => 'managed', 'tab' => 'tasks', 'filter' => 'overdue']) }}" class="btn btn-outline btn-xs" style="font-size: 11.5px; padding: 3px 8px; color: #315F58; border-color: #CBD5E1;">
                                Task Monitor
                            </a>
                            <a href="{{ route('tasks.mine', ['view' => 'managed', 'tab' => 'team']) }}" class="btn btn-outline btn-xs" style="font-size: 11.5px; padding: 3px 8px; color: #315F58; border-color: #CBD5E1;">
                                Team Monitor
                            </a>
                        @endif
                        @if(count($attentionItems) > 0)
                            <span class="status-pill pill-warning">{{ count($attentionItems) }} Actions</span>
                        @endif
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>Item / Record</th>
                                <th>Issue / Required Action</th>
                                <th>Deadline</th>
                                <th class="right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($attentionItems as $item)
                                <tr>
                                    <td>
                                        <b>{{ $item['title'] }}</b>
                                        @if(!empty($item['project_name']))
                                            <small class="block muted">{{ $item['project_name'] }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span style="font-size: 13px; color: var(--text);">{{ $item['issue'] }}</span>
                                    </td>
                                    <td>
                                        <span class="muted text-sm">{{ $item['deadline'] }}</span>
                                    </td>
                                    <td class="right">
                                        @if($item['is_invitation'] ?? false)
                                            <a href="{{ route('projects.show', $item['project_id']) }}" class="btn btn-primary btn-sm" style="font-weight: 600;">
                                                Review & Respond →
                                            </a>
                                        @elseif(!empty($item['task_id']))
                                            <button type="button" class="btn btn-outline btn-sm" data-task-panel="{{ route('tasks.panel', $item['task_id']) }}">
                                                {{ $item['action_label'] }}
                                            </button>
                                        @else
                                            <a href="{{ $item['action_url'] }}" class="btn btn-outline btn-sm">
                                                {{ $item['action_label'] }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="empty">
                                        <b>All clear</b>
                                        <p class="muted">No pending actions or urgent blockers requiring your attention.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 2. My Projects Preview -->
            <section class="dashboard-card">
                <div class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <h2>My Projects</h2>
                        <p>Projects you are currently managing, leading, or assigned to.</p>
                    </div>
                    <a class="card-link" href="{{ route('projects.index') }}">View all projects →</a>
                </div>

                <div class="table-wrap">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>My Role</th>
                                <th>Progress</th>
                                <th>Tasks</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th class="right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($myProjectsPreview as $proj)
                                <tr>
                                    <td>
                                        <a href="{{ $proj['url'] }}" style="font-weight: 650; color: #111827; text-decoration: none;">
                                            {{ $proj['name'] }}
                                        </a>
                                        <small class="block muted">{{ $proj['code'] }}</small>
                                    </td>
                                    <td>
                                        <span class="muted text-sm" style="font-weight: 600; color: #374151;">{{ $proj['roles'] }}</span>
                                    </td>
                                    <td>
                                        @if($proj['progress'] !== null)
                                            <div class="progress-cell">
                                                <div class="progress-line">
                                                    <div style="width: {{ $proj['progress'] }}%"></div>
                                                </div>
                                                <span class="progress-pct">{{ $proj['progress'] }}%</span>
                                            </div>
                                        @else
                                            <span class="muted text-sm">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($proj['total_tasks'] !== null)
                                            <span style="font-size: 13px; font-weight: 600; color: #374151;">
                                                {{ $proj['completed_tasks'] }} / {{ $proj['total_tasks'] }}
                                            </span>
                                        @else
                                            <span class="muted text-sm">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="muted text-sm">{{ $proj['deadline'] ? $proj['deadline']->format('d M Y') : '—' }}</span>
                                    </td>
                                    <td>
                                        <span class="project-status-pill status-{{ $proj['status'] }}" style="font-size: 11px; padding: 2px 8px;">
                                            {{ $proj['status_label'] }}
                                        </span>
                                    </td>
                                    <td class="right">
                                        <a href="{{ $proj['url'] }}" class="btn btn-outline btn-sm">Open Plan</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="empty">
                                        <b>No active projects</b>
                                        <p class="muted">You have not been assigned to any projects yet.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Side / Right Column (My Upcoming Work) -->
        <div class="dashboard-col-side">
            <section class="dashboard-card">
                <div class="dashboard-card-head">
                    <div class="dashboard-card-title">
                        <h2>My Upcoming Work</h2>
                        <p>Tasks assigned to you sorted by schedule.</p>
                    </div>
                    <a class="card-link" href="{{ route('tasks.mine') }}">All tasks →</a>
                </div>

                <div class="deadlines-list">
                    @forelse($upcomingWork as $t)
                        @php
                            $isPast = $t->ends_at->isPast();
                            $isDueSoon = !$isPast && $t->ends_at->diffInDays(now()) <= 3;
                            $badgeClass = $isPast ? 'pill-danger' : ($isDueSoon ? 'pill-warning' : 'pill-neutral');
                            $badgeText = $isPast ? 'Overdue' : ($isDueSoon ? 'Due Soon' : 'Upcoming');
                        @endphp
                        <div class="deadline-row" style="cursor: pointer;" data-task-panel="{{ route('tasks.panel', $t) }}">
                            <div class="deadline-date-box">
                                <span class="deadline-day">{{ $t->ends_at->format('d') }}</span>
                                <span class="deadline-month">{{ $t->ends_at->format('M') }}</span>
                            </div>
                            <div class="deadline-body">
                                <button type="button" class="deadline-title" data-task-panel="{{ route('tasks.panel', $t) }}" style="background: none; border: none; padding: 0; text-align: left; cursor: pointer; font-weight: 600;">
                                    {{ $t->title }}
                                </button>
                                <span class="deadline-project">{{ $t->project?->code }} · {{ $t->project?->name }}</span>
                            </div>
                            <span class="status-pill {{ $badgeClass }}">{{ $badgeText }}</span>
                        </div>
                    @empty
                        <div class="empty-state" style="padding: 32px 16px;">
                            <b>No upcoming tasks</b>
                            <p class="muted" style="margin-top: 4px; font-size: 13px;">You have no pending tasks on your schedule.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

@endif
    <!-- Shared task drawer for both system identities -->
    <aside class="task-side-panel" id="task-side-panel" aria-labelledby="task-panel-title" aria-hidden="true" hidden>
        <div class="task-panel-top">
            <h2 id="task-panel-title">Task Details</h2>
            <button type="button" class="panel-close" id="task-panel-close" aria-label="Close task details">×</button>
        </div>
        <div id="task-panel-content" class="task-panel-content">
            <p class="muted">Select a task to see its details.</p>
        </div>
    </aside>
    <div id="task-panel-shade" class="task-panel-shade" hidden></div>

    @push('scripts')
    <script defer src="{{ asset('js/project-workspace.js') }}"></script>
    @endpush
@endsection
