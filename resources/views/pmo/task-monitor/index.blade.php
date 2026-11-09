@extends('layouts.app')
@section('title', 'Task Monitor')
@section('content')
<div class="task-monitor-workspace">
    @if(request()->routeIs('monitoring.index'))
        @include('monitoring._header')
    @else
    <header class="task-monitor-header">
        <div>
            <div class="task-heading-line">
                <h1>Task Monitor</h1>
                @include('layouts._task-scope')
            </div>
            <p class="muted">Monitor tasks across all company projects.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="{{ route('pmo.task-monitor.export', request()->query()) }}" class="btn btn-outline btn-sm">Export CSV</a>
            <a href="{{ route('pmo.team-monitor') }}" class="btn btn-outline btn-sm">Team Monitor →</a>
        </div>
    </header>
    @endif

    @php
        $monitorRoute = request()->routeIs('monitoring.index') ? 'monitoring.index' : 'pmo.task-monitor';
        $currentFilter = $filters['filter'] ?? 'all';
        $summaryLinks = [
            'all' => ['label'=>'Total Tasks', 'count'=>$summary['total_tasks'] ?? 0],
            'open' => ['label'=>'Open Tasks', 'count'=>$summary['open_tasks'] ?? 0],
            'overdue' => ['label'=>'Overdue Tasks', 'count'=>$summary['overdue_tasks'] ?? 0],
            'unassigned' => ['label'=>'Unassigned Tasks', 'count'=>$summary['unassigned_tasks'] ?? 0],
            'completed' => ['label'=>'Completed Tasks', 'count'=>$summary['completed_tasks'] ?? 0],
        ];
        $filterTabs = [
            'all' => ['label' => 'All Tasks', 'count' => $summary['total_tasks'] ?? 0],
            'overdue' => ['label' => 'Overdue', 'count' => $summary['overdue_tasks'] ?? 0, 'badge' => 'danger'],
            'critical' => ['label' => 'Critical Path', 'count' => $summary['critical_tasks'] ?? 0, 'badge' => 'warning'],
            'blocked' => ['label' => 'Blocked', 'count' => $summary['blocked_tasks'] ?? 0, 'badge' => 'danger'],
            'unassigned' => ['label' => 'Unassigned', 'count' => $summary['unassigned_tasks'] ?? 0, 'badge' => 'neutral'],
            'due_soon' => ['label' => 'Due Soon (7d)', 'count' => $summary['due_soon_tasks'] ?? 0, 'badge' => 'warning'],
            'needs_update' => ['label' => 'Needs Update', 'count' => $summary['needs_update_tasks'] ?? 0, 'badge' => 'neutral'],
        ];
        $filterTabs['open'] = ['label'=>'Open', 'count'=>$summary['open_tasks'] ?? 0];
        $filterTabs['completed'] = ['label'=>'Completed', 'count'=>$summary['completed_tasks'] ?? 0];
        $extraFiltersActive = !empty($filters['subsidiary_id']) || !empty($filters['status']);
        $hasFilters = !empty($filters['q']) || !empty($filters['project_id']) || !empty($filters['user_id']) || $extraFiltersActive || $currentFilter !== 'all';
    @endphp
    <nav class="monitor-focus-tabs" aria-label="Task views">
        @foreach($filterTabs as $fKey => $fTab)
            <a href="{{ route($monitorRoute, array_merge(request()->except('page'), ['filter' => $fKey])) }}" class="btn btn-sm {{ $currentFilter === $fKey ? 'is-active' : '' }}" @if($currentFilter === $fKey) aria-current="page" @endif style="height: 30px; font-size: 12px; border-radius: 16px; border: 1px solid {{ $currentFilter === $fKey ? '#315F58' : '#CBD5E1' }}; background: {{ $currentFilter === $fKey ? '#315F58' : '#FFFFFF' }}; color: {{ $currentFilter === $fKey ? '#FFFFFF' : '#22272B' }}; font-weight: {{ $currentFilter === $fKey ? '650' : '500' }}; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <span>{{ $fTab['label'] }}</span>
                @if(($fTab['count'] ?? 0) > 0)
                    <span style="font-size: 11px; padding: 1px 6px; border-radius: 10px; background: {{ $currentFilter === $fKey ? 'rgba(255,255,255,0.25)' : '#F1F5F9' }}; color: {{ $currentFilter === $fKey ? '#FFFFFF' : '#475569' }};">
                        {{ $fTab['count'] }}
                    </span>
                @endif
            </a>
        @endforeach
    </nav>

    <form method="get" action="{{ route($monitorRoute) }}" id="task-filter-form" class="task-monitor-filters">
        @if(request()->routeIs('monitoring.index'))<input type="hidden" name="tab" value="tasks">@endif
        <input type="hidden" name="filter" value="{{ $currentFilter }}">
        <div class="task-filter-primary">
            <label class="task-monitor-search">
                <span class="task-monitor-sr-only">Search tasks, projects or people</span>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search tasks, projects or people...">
            </label>
            <label>
                <span class="task-monitor-sr-only">Project</span>
                <select name="project_id" onchange="this.form.requestSubmit()">
                    <option value="">All projects</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" @selected(($filters['project_id'] ?? '') == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="task-monitor-sr-only">Assignee</span>
                <select name="user_id" onchange="this.form.requestSubmit()">
                    <option value="">Anyone</option>
                    <option value="unassigned" @selected(($filters['user_id'] ?? '') === 'unassigned')>Unassigned</option>
                    @foreach($assignees as $u)
                        <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </label>

            <button type="submit" class="btn btn-outline btn-sm">Search</button>
            @if($hasFilters)<a href="{{ route($monitorRoute) }}" class="task-filter-clear">Clear</a>@endif
        </div>
        <div class="task-filter-secondary">
            <details class="task-more-filters" @if($extraFiltersActive) open @endif>
                <summary>More filters @if($extraFiltersActive)<span class="task-extra-count">{{ (int)!empty($filters['subsidiary_id']) + (int)!empty($filters['status']) }}</span>@endif</summary>
                <div class="task-extra-fields">

                    <label>Subsidiary
                        <select name="subsidiary_id" onchange="this.form.requestSubmit()">
                            <option value="">All subsidiaries</option>
                            @foreach($subsidiaries as $s)
                                <option value="{{ $s->id }}" @selected(($filters['subsidiary_id'] ?? '') == $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Status
                        <select name="status" onchange="this.form.requestSubmit()">
                            <option value="">Any status</option>
                            @foreach(['todo'=>'To do','in_progress'=>'In progress','on_hold'=>'On hold','completed'=>'Completed'] as $value=>$label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </details>
            <span class="task-results-count" role="status">{{ $tasks->total() }} {{ Str::plural('task', $tasks->total()) }}</span>
        </div>
    </form>
    <!-- Task Monitor Table -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div class="table-wrap">
            <table class="clean-table monitor-task-records" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #F5F6F7; border-bottom: 1px solid #E5E7EB;">
                        <th style="padding: 12px 16px; font-size: 12px; font-weight: 700; color: #22272B; text-align: left;">Project / Task</th>
                        <th style="padding: 12px 14px; font-size: 12px; font-weight: 700; color: #22272B; text-align: left;">Assigned To</th>
                        <th style="padding: 12px 14px;">Due</th>
                        <th style="padding: 12px 14px; font-size: 12px; font-weight: 700; color: #22272B; text-align: left; width: 130px;">Progress</th>
                        <th style="padding: 12px 14px; font-size: 12px; font-weight: 700; color: #22272B; text-align: left;">Status</th>
                        <th style="padding: 12px 16px; font-size: 12px; font-weight: 700; color: #22272B; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                        <tr style="border-bottom: 1px solid #F1F5F9; transition: background 0.15s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='#FFFFFF'">
                            <!-- Project / Task -->
                            <td style="padding: 12px 16px;">
                                <div style="font-size: 11.5px; color: #64748B; font-weight: 500; margin-bottom: 2px;">
                                    <a href="{{ route('projects.show', $task->project_id) }}" style="color: #64748B; text-decoration: none;">
                                        {{ $task->project?->name ?? 'Untitled Project' }}
                                    </a>
                                </div>
                                <div style="font-size: 13.5px; font-weight: 650; color: #22272B;">
                                    <a href="{{ route('projects.show', [$task->project_id, 'focus_task' => $task->id]) }}#task-row-{{ $task->id }}" style="color: #22272B; text-decoration: none; font-weight: 650;" title="View task in project workplan">
                                        <span style="color: #64748B; font-weight: 600; margin-right: 4px;">{{ $task->code }}</span>
                                        <span>{{ $task->title }}</span>
                                    </a>
                                </div>
                            </td>

                            <!-- Assigned To (Clickable to open Employee Workload) -->
                            <td style="padding: 12px 14px;">
                                @if($task->assignees->isNotEmpty())
                                    @php
                                        $primary = $task->assignees->first();
                                        $extraCount = $task->assignees->count() - 1;
                                    @endphp
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <a href="javascript:void(0)" onclick="openEmployeeWorkloadModal({{ $primary->id }})" style="font-size: 13px; font-weight: 600; color: #315F58; text-decoration: none;" title="View Workload">
                                            {{ $primary->name }}
                                        </a>
                                        @if($extraCount > 0)
                                            <span style="font-size: 11px; background: #E2E8F0; color: #475569; padding: 1px 6px; border-radius: 10px; font-weight: 600;" title="{{ $task->assignees->pluck('name')->join(', ') }}">
                                                +{{ $extraCount }}
                                            </span>
                                        @endif
                                    </div>
                                    @if($primary->job_title)
                                        <div style="font-size: 11px; color: #94A3B8;">{{ $primary->job_title }}</div>
                                    @endif
                                @else
                                    <span style="font-size: 12.5px; color: #94A3B8; font-style: italic;">Unassigned</span>
                                @endif
                            </td>



                            <!-- End Date & Time -->
                            <td style="padding: 12px 14px; font-size: 12.5px; color: #334155;">
                                <div style="{{ $task->ends_at && $task->ends_at->isPast() && $task->progress < 100 ? 'color: #DC2626; font-weight: 650;' : '' }}">
                                    {{ $task->ends_at ? $task->ends_at->format('d M Y') : '—' }}
                                </div>
                                <div style="font-size: 11px; color: #94A3B8;">{{ $task->ends_at ? $task->ends_at->format('h:i A') : '' }}</div>
                            </td>

                            <!-- Progress -->
                            <td style="padding: 12px 14px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 12px; font-weight: 650; margin-bottom: 3px; color: #22272B;">
                                    <span>{{ $task->progress }}%</span>
                                    @if($task->estimated_hours)
                                        <span style="font-size: 10.5px; color: #64748B; font-weight: 400;">{{ $task->estimated_hours }}h</span>
                                    @endif
                                </div>
                                <div style="height: 5px; background: #E2E8F0; border-radius: 3px; overflow: hidden;">
                                    <div style="width: {{ $task->progress }}%; height: 100%; background: {{ $task->progress === 100 ? '#107C41' : '#315F58' }};"></div>
                                </div>
                            </td>

                            <!-- Status -->
                            <td style="padding: 12px 14px;">
                                <span class="status-pill status-{{ $task->status }}" style="font-size: 11.5px; padding: 2px 8px;">
                                    {{ ['todo'=>'To do','in_progress'=>'In progress','on_hold'=>'On hold','completed'=>'Completed'][$task->status] ?? ucfirst($task->status) }}
                                </span>
                                @if($task->schedule_health !== 'Completed')<div class="monitor-health">
                                @if($task->schedule_health === 'Blocked')
                                    <span class="status-pill pill-danger" style="font-size: 11.5px; padding: 2px 8px; font-weight: 650;">
                                        Blocked
                                    </span>
                                @elseif($task->schedule_health === 'Overdue')
                                    <span class="status-pill pill-danger" style="font-size: 11.5px; padding: 2px 8px; font-weight: 650;">
                                        Overdue
                                    </span>
                                @elseif($task->schedule_health === 'Critical Path')
                                    @include('tasks._critical-badge')
                                @elseif($task->schedule_health === 'Completed')
                                    <span class="status-pill pill-success" style="font-size: 11.5px; padding: 2px 8px;">
                                        Completed
                                    </span>
                                @else
                                    <span class="status-pill pill-success" style="font-size: 11.5px; padding: 2px 8px;">
                                        On Track
                                    </span>
                                @endif
                                </div>@endif
                            </td>

                            <!-- Actions -->
                            <td style="padding: 12px 16px; text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; gap: 6px; align-items: center;">
                                    @if($task->progress < 100 && $task->assignees->isEmpty())
                                        <a href="{{ route('projects.show', [$task->project_id, 'focus_task' => $task->id]) }}#task-row-{{ $task->id }}" class="btn btn-outline btn-xs" style="height: 30px; font-size: 12px; padding: 0 10px; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; font-weight: 600; color: #315F58; border-color: #315F58;" title="Open task in project workplan to assign person">Assign person</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 48px 20px; color: #64748B;">
                                <div style="font-size: 15px; font-weight: 650; color: #22272B; margin-bottom: 6px;">No tasks match your selected criteria</div>
                                <div style="font-size: 13px;">Try adjusting your search query, filter tabs, or clear active filters.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tasks->hasPages())
            <div style="padding: 14px 20px; border-top: 1px solid #E5E7EB; background: #F8FAFC;">
                {{ $tasks->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

<!-- =========================================================================
     MODAL 1: TASK DETAILS DRAWER / POPUP
     ========================================================================= -->
<div id="pmo-task-details-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px); z-index: 9998; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #FFFFFF; border-radius: 8px; width: 100%; max-width: 780px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); border: 1px solid #CBD5E1; display: flex; flex-direction: column;">
        <div style="padding: 16px 20px; border-bottom: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: space-between; background: #F8FAFC;">
            <div>
                <span id="ptd-project-name" style="font-size: 12px; color: #64748B; font-weight: 500;">Project Name</span>
                <h3 id="ptd-task-title" style="margin: 2px 0 0; font-size: 17px; font-weight: 700; color: #22272B;">Task Details</h3>
            </div>
            <button type="button" onclick="closeTaskDetailsModal()" style="border: none; background: transparent; font-size: 20px; color: #64748B; cursor: pointer; padding: 4px 8px; line-height: 1;">✕</button>
        </div>

        <div id="ptd-modal-body" style="padding: 20px; font-size: 13px; color: #22272B;">
            <div style="text-align: center; padding: 40px; color: #64748B;">Loading task details...</div>
        </div>

        <div style="padding: 12px 20px; border-top: 1px solid #E5E7EB; background: #F8FAFC; display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="closeTaskDetailsModal()" class="btn btn-outline btn-sm">Close</button>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: EMPLOYEE WORKLOAD POPUP (Cross-Navigation)
     ========================================================================= -->
<div id="pmo-employee-workload-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #FFFFFF; border-radius: 8px; width: 100%; max-width: 860px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); border: 1px solid #CBD5E1; display: flex; flex-direction: column;">
        <div style="padding: 16px 22px; border-bottom: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: space-between; background: #F8FAFC;">
            <div>
                <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B;">Employee Workload Overview</span>
                <h3 id="ewm-name" style="margin: 2px 0 0; font-size: 18px; font-weight: 700; color: #22272B;">Employee Name</h3>
            </div>
            <button type="button" onclick="closeEmployeeWorkloadModal()" style="border: none; background: transparent; font-size: 20px; color: #64748B; cursor: pointer; padding: 4px 8px;">✕</button>
        </div>

        <div id="ewm-body" style="padding: 22px; font-size: 13px;">
            <div style="text-align: center; padding: 40px; color: #64748B;">Loading employee workload details...</div>
        </div>

        <div style="padding: 12px 22px; border-top: 1px solid #E5E7EB; background: #F8FAFC; display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeEmployeeWorkloadModal()" class="btn btn-outline btn-sm">Close</button>
        </div>
    </div>
</div>

<script>
    /* Task Details Modal Integration */
    function openTaskDetailsModal(taskId) {
        const backdrop = document.getElementById('pmo-task-details-backdrop');
        const body = document.getElementById('ptd-modal-body');
        backdrop.style.display = 'flex';
        body.innerHTML = '<div style="text-align:center;padding:40px;color:#64748B;">Loading task details...</div>';

        fetch(`/tasks/${taskId}/panel`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.text())
        .then(html => {
            body.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            body.innerHTML = '<div style="padding:20px;color:#DC2626;text-align:center;">Failed to load task details. Please refresh and try again.</div>';
        });
    }

    function closeTaskDetailsModal() {
        document.getElementById('pmo-task-details-backdrop').style.display = 'none';
    }

    /* Cross-Navigation: Employee Workload Modal */
    function openEmployeeWorkloadModal(userId) {
        const backdrop = document.getElementById('pmo-employee-workload-modal');
        const body = document.getElementById('ewm-body');
        const nameEl = document.getElementById('ewm-name');
        backdrop.style.display = 'flex';
        body.innerHTML = '<div style="text-align:center;padding:40px;color:#64748B;">Loading employee workload details...</div>';

        fetch(`/pmo/team-monitor/employee/${userId}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                body.innerHTML = `<div style="color:#DC2626;padding:20px;text-align:center;">Unable to load workload details.</div>`;
                return;
            }
            const data = res.data;
            nameEl.textContent = `${data.employee.name} (${data.employee.job_title})`;

            let html = `
                <!-- Top Summary Cards -->
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px 14px;">
                        <span style="font-size:11px;font-weight:650;color:#64748B;text-transform:uppercase;">Active Projects</span>
                        <div style="font-size:20px;font-weight:700;color:#22272B;margin-top:2px;">${data.summary.active_projects_count}</div>
                    </div>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px 14px;">
                        <span style="font-size:11px;font-weight:650;color:#64748B;text-transform:uppercase;">Open Tasks</span>
                        <div style="font-size:20px;font-weight:700;color:#22272B;margin-top:2px;">${data.summary.open_tasks_count}</div>
                    </div>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px 14px;">
                        <span style="font-size:11px;font-weight:650;color:#64748B;text-transform:uppercase;">Overdue Tasks</span>
                        <div style="font-size:20px;font-weight:700;color:${data.summary.overdue_tasks_count > 0 ? '#DC2626' : '#22272B'};margin-top:2px;">${data.summary.overdue_tasks_count}</div>
                    </div>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px 14px;">
                        <span style="font-size:11px;font-weight:650;color:#64748B;text-transform:uppercase;">Workload Utilization</span>
                        <div style="font-size:14px;font-weight:700;margin-top:4px;">
                            ${data.summary.utilization_percent !== null ? `<span style="font-size:18px;">${data.summary.utilization_percent}%</span> <span class="status-pill ${data.summary.badge_class}" style="font-size:11px;margin-left:4px;">${data.summary.workload_label}</span>` : `<span style="color:#64748B;">Capacity not configured</span>`}
                        </div>
                    </div>
                </div>

                <!-- Assigned Projects Breakdown -->
                <div style="margin-bottom:20px;">
                    <div style="font-size:13px;font-weight:700;color:#22272B;margin-bottom:8px;">Active Projects & Roles</div>
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px;border:1px solid #E5E7EB;border-radius:6px;overflow:hidden;">
                        <thead>
                            <tr style="background:#F8FAFC;border-bottom:1px solid #E5E7EB;text-align:left;">
                                <th style="padding:8px 12px;">Project</th>
                                <th style="padding:8px 12px;">Role(s)</th>
                                <th style="padding:8px 12px;text-align:center;">Open Tasks</th>
                                <th style="padding:8px 12px;text-align:center;">Overdue</th>
                                <th style="padding:8px 12px;text-align:center;">Critical</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.projects.length ? data.projects.map(p => `
                                <tr style="border-bottom:1px solid #F1F5F9;">
                                    <td style="padding:8px 12px;"><a href="${p.url}" target="_blank" style="color:#315F58;font-weight:600;text-decoration:none;">${p.code} · ${p.name}</a></td>
                                    <td style="padding:8px 12px;color:#475569;">${p.roles}</td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:600;">${p.open_tasks_count}</td>
                                    <td style="padding:8px 12px;text-align:center;color:${p.overdue_tasks_count > 0 ? '#DC2626' : '#64748B'};font-weight:600;">${p.overdue_tasks_count}</td>
                                    <td style="padding:8px 12px;text-align:center;color:${p.critical_tasks_count > 0 ? '#D97706' : '#64748B'};font-weight:600;">${p.critical_tasks_count}</td>
                                </tr>
                            `).join('') : `<tr><td colspan="5" style="text-align:center;padding:16px;color:#64748B;">No active project memberships.</td></tr>`}
                        </tbody>
                    </table>
                </div>

                <!-- Assigned Tasks List -->
                <div>
                    <div style="font-size:13px;font-weight:700;color:#22272B;margin-bottom:8px;">Assigned Tasks</div>
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px;border:1px solid #E5E7EB;border-radius:6px;overflow:hidden;">
                        <thead>
                            <tr style="background:#F8FAFC;border-bottom:1px solid #E5E7EB;text-align:left;">
                                <th style="padding:8px 12px;">Task</th>
                                <th style="padding:8px 12px;">Project</th>
                                <th style="padding:8px 12px;">Deadline</th>
                                <th style="padding:8px 12px;text-align:center;">Progress</th>
                                <th style="padding:8px 12px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.tasks.length ? data.tasks.map(t => `
                                <tr style="border-bottom:1px solid #F1F5F9;">
                                    <td style="padding:8px 12px;">
                                        <a href="javascript:void(0)" onclick="closeEmployeeWorkloadModal(); openTaskDetailsModal(${t.id});" style="font-weight:600;color:#22272B;text-decoration:none;">
                                            <span style="color:#64748B;">${t.code}</span> ${t.title}
                                        </a>
                                        ${t.is_critical ? '<span class="status-pill pill-warning" style="font-size:10px;margin-left:4px;">Critical</span>' : ''}
                                    </td>
                                    <td style="padding:8px 12px;color:#475569;">${t.project_name}</td>
                                    <td style="padding:8px 12px;color:${t.is_overdue ? '#DC2626;font-weight:600;' : '#475569;'}">${t.deadline}</td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:600;">${t.progress}%</td>
                                    <td style="padding:8px 12px;"><span class="status-pill status-${t.status}" style="font-size:11px;">${t.status_label}</span></td>
                                </tr>
                            `).join('') : `<tr><td colspan="5" style="text-align:center;padding:16px;color:#64748B;">No assigned tasks found.</td></tr>`}
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

    function closeEmployeeWorkloadModal() {
        document.getElementById('pmo-employee-workload-modal').style.display = 'none';
    }

    // Close on backdrop click or ESC
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeTaskDetailsModal();
            closeRequestUpdateDialog();
            closeEmployeeWorkloadModal();
        }
    });
</script>

<script defer src="{{ asset('js/project-workspace.js') }}?v={{ filemtime(public_path('js/project-workspace.js')) }}"></script>
@endsection

