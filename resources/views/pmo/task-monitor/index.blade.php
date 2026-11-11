@extends('layouts.app')
@section('title','Task Monitor')
@section('content')
<link rel="stylesheet" href="{{ asset('css/task-workspace.css') }}?v={{ filemtime(public_path('css/task-workspace.css')) }}">
@php
    $monitorRoute=request()->routeIs('monitoring.index')?'monitoring.index':'pmo.task-monitor';
    $currentFilter=$filters['filter']??'all';
    $taskViews=['all'=>['All Tasks','total_tasks'],'overdue'=>['Overdue','overdue_tasks'],'critical'=>['Critical Path','critical_tasks'],'due_soon'=>['Due soon','due_soon_tasks'],'unassigned'=>['Unassigned','unassigned_tasks'],'completed'=>['Completed','completed_tasks']];
    if(($summary['blocked_tasks']??0)>0)$taskViews['blocked']=['Blocked','blocked_tasks'];
    if(($summary['needs_update_tasks']??0)>0)$taskViews['needs_update']=['Needs update','needs_update_tasks'];
    $hasFilters=!empty($filters['q']) || !empty($filters['project_id']) || !empty($filters['user_id']) || !empty($filters['status']) || !empty($filters['subsidiary_id']);
@endphp
<div class="task-monitor-workspace task-workspace" style="padding-bottom: 24px;">
    <!-- Compact Executive Header -->
    <header class="tw-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 12px;">
        <div>
            <h1 style="font-size: 20px; font-weight: 700; color: #0F172A; letter-spacing: -0.4px; margin: 0; line-height: 1.2;">Task Monitor</h1>
            <p class="muted" style="margin: 2px 0 0; font-size: 12px; color: #64748B;">Monitor tasks across all company projects.</p>
        </div>
        <a href="{{ route('pmo.task-monitor.export',request()->query()) }}" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; height: 32px; padding: 0 11px; border-radius: 6px; border: 1px solid var(--border); background: #FFFFFF; color: #334155; text-decoration: none;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span>Export CSV</span>
        </a>
    </header>

    <!-- Accessible Screen-Reader Summary (No duplicate visual bar) -->
    <div class="workspace-sr-only" aria-label="Task metrics summary">
        <span>Total Tasks: {{ $summary['total_tasks'] ?? 0 }}</span>
        <span>Open Tasks: {{ $summary['open_tasks'] ?? 0 }}</span>
        <span>Overdue Tasks: {{ $summary['overdue_tasks'] ?? 0 }}</span>
        <span>Unassigned Tasks: {{ $summary['unassigned_tasks'] ?? 0 }}</span>
        <span>Completed Tasks: {{ $summary['completed_tasks'] ?? 0 }}</span>
    </div>

    <!-- Unified Filter Card -->
    <section class="card monitor-controls" aria-label="Task filters" style="padding: 0; overflow: hidden; border-radius: 8px; border: 1px solid var(--border); background: #FFFFFF; box-shadow: 0 1px 2px rgba(0,0,0,0.02); margin-bottom: 14px;">
        <!-- Clean Compact Tab Nav -->
        <nav class="tw-views" aria-label="Filter tasks" style="display: flex; gap: 18px; padding: 0 16px; border-bottom: 1px solid var(--border); background: #FAFBFC; align-items: center; overflow-x: auto; margin-bottom: 0;">
            @foreach($taskViews as $key=>$item)
                @php
                    $isActive = $currentFilter === $key;
                    $badgeVal = $summary[$item[1]] ?? 0;
                    $isDanger = ($key === 'overdue' || $key === 'blocked') && $badgeVal > 0;
                @endphp
                <a href="{{ route($monitorRoute,array_merge(request()->except('page','filter'),['filter'=>$key])) }}" class="{{ $isActive ? 'is-active' : '' }}" @if($isActive) aria-current="page" @endif style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 0; font-size: 12.5px; font-weight: {{ $isActive ? '650' : '500' }}; text-decoration: none; border-bottom: 2px solid {{ $isActive ? ($isDanger ? '#DC2626' : 'var(--primary)') : 'transparent' }}; color: {{ $isActive ? ($isDanger ? '#DC2626' : 'var(--primary)') : '#64748B' }}; white-space: nowrap;">
                    <span>{{ $item[0] }}</span>
                    <span class="pill" style="font-size: 10.5px; padding: 1px 6px; border-radius: 10px; background: {{ $isDanger ? '#FEE2E2' : ($isActive ? '#E8F0EE' : '#F1F5F9') }}; color: {{ $isDanger ? '#DC2626' : ($isActive ? 'var(--primary)' : '#64748B') }}; font-weight: 650;">
                        {{ $badgeVal }}
                    </span>
                </a>
            @endforeach
        </nav>

        <!-- Compact Toolbar Form -->
        <form method="get" action="{{ route($monitorRoute) }}" class="tw-filters" id="task-filter-form" style="padding: 10px 16px; margin-bottom: 0; background: #FFFFFF;">
            @if(request()->routeIs('monitoring.index'))<input type="hidden" name="tab" value="tasks">@endif
            <input type="hidden" name="filter" value="{{ $currentFilter }}">
            @if(!empty($filters['active_only']))<input type="hidden" name="active_only" value="1">@endif

            <div class="monitor-toolbar-row">
                <div class="monitor-toolbar-search" style="position:relative;display:flex;align-items:center;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 12px; pointer-events: none;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <label class="workspace-sr-only" for="task-search-input">Search tasks, projects or people</label>
                    <input type="search" id="task-search-input" name="q" value="{{ $filters['q']??'' }}" placeholder="Search tasks, projects or people..." autocomplete="off" style="width: 100%; height: 35px; border-radius: 7px; border: 1px solid var(--border); padding: 0 12px 0 34px; font-size: 12.5px; outline: none; transition: all 0.15s ease;">
                </div>

                <label style="margin: 0;">
                    <span class="workspace-sr-only">Project</span>
                    <select name="project_id" class="custom-select" onchange="this.form.requestSubmit()" style="height: 35px; min-width: 170px; font-size: 12px; padding: 0 32px 0 12px;">
                        <option value="">All projects</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected(($filters['project_id']??'')==$project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </label>

                    <button type="button" class="filter-panel-toggle" aria-controls="task-extra-filters" aria-expanded="{{ (!empty($filters['user_id']) || !empty($filters['status']) || !empty($filters['subsidiary_id']))?'true':'false' }}" onclick="const panel=document.getElementById('task-extra-filters');panel.open=!panel.open;this.setAttribute('aria-expanded',String(panel.open));"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="2" fill="white"/><circle cx="15" cy="17" r="2" fill="white"/></svg><span>More filters</span>@if(collect(['user_id','status','subsidiary_id'])->filter(fn($key)=>!empty($filters[$key]))->count())<span class="filter-panel-count">{{ collect(['user_id','status','subsidiary_id'])->filter(fn($key)=>!empty($filters[$key]))->count() }}</span>@endif<svg class="filter-panel-chevron" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
                <button type="submit" class="btn btn-primary btn-sm" style="height: 35px; padding: 0 15px; font-size: 12px; font-weight: 600; border-radius: 7px;">Search</button>

                @if($hasFilters)
                    <a class="tw-clear text-action" href="{{ route($monitorRoute,['filter'=>$currentFilter]) }}" style="font-size: 12px; font-weight: 600; color: var(--primary);">Clear</a>
                @endif

                <span class="tw-result-count" style="font-size: 11.5px; font-weight: 600; color: var(--muted); margin-left: auto;">
                    {{ $tasks->total() }} {{ Str::plural('task',$tasks->total()) }}
                </span>
            </div>

            <!-- More Filters Expandable Details -->
            @php
                $hasExtraFilters = !empty($filters['user_id']) || !empty($filters['status']) || !empty($filters['subsidiary_id']);
            @endphp
            <div class="monitor-extra-wrap">
                <details id="task-extra-filters" class="tw-more" @if($hasExtraFilters) open @endif style="font-size: 11.5px;">
                    <summary hidden>Additional filters</summary>
<div class="filter-panel-caption"><span>Refine your results</span><a href="{{ route('pmo.task-monitor',request()->except(['user_id','status','subsidiary_id','page'])) }}">Reset extra filters</a></div>                    <div class="tw-extra" style="display: flex; gap: 12px; flex-wrap: wrap; padding-top: 8px;">
                        <label style="font-size: 11px; font-weight: 600; color: #64748B; display: flex; flex-direction: column; gap: 3px;">
                            <span>Assignee</span>
                            <select name="user_id" class="custom-select custom-select-sm" onchange="this.form.requestSubmit()" style="height: 32px; min-width: 150px; font-size: 12px;">
                                <option value="">Anyone</option>
                                <option value="unassigned" @selected(($filters['user_id']??'')==='unassigned')>Unassigned</option>
                                @foreach($assignees as $person)
                                    <option value="{{ $person->id }}" @selected(($filters['user_id']??'')==$person->id)>{{ $person->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label style="font-size: 11px; font-weight: 600; color: #64748B; display: flex; flex-direction: column; gap: 3px;">
                            <span>Status</span>
                            <select name="status" class="custom-select custom-select-sm" onchange="this.form.requestSubmit()" style="height: 32px; min-width: 130px; font-size: 12px;">
                                <option value="">Any status</option>
                                @foreach(['todo'=>'To do','in_progress'=>'In progress','on_hold'=>'On hold','completed'=>'Completed'] as $key=>$label)
                                    <option value="{{ $key }}" @selected(($filters['status']??'')===$key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label style="font-size: 11px; font-weight: 600; color: #64748B; display: flex; flex-direction: column; gap: 3px;">
                            <span>Subsidiary</span>
                            <select name="subsidiary_id" class="custom-select custom-select-sm" onchange="this.form.requestSubmit()" style="height: 32px; min-width: 150px; font-size: 12px;">
                                <option value="">All subsidiaries</option>
                                @foreach($subsidiaries as $company)
                                    <option value="{{ $company->id }}" @selected(($filters['subsidiary_id']??'')==$company->id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </details>
            </div>
        </form>
    </section>

    <!-- Task Monitor Table -->
    <div class="card tw-table-surface" style="background: #FFFFFF; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
        <div class="table-wrap">
            <table class="clean-table monitor-task-records tw-table tw-monitor-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #FAFBFC; border-bottom: 1px solid var(--border);">
                        <th style="padding: 10px 16px; font-size: 11px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; text-align: left;">Task / Project</th>
                        <th style="padding: 10px 14px; font-size: 11px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; text-align: left;">Assigned To</th>
                        <th style="padding: 10px 14px; font-size: 11px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; text-align: left;">Due</th>
                        <th style="padding: 10px 14px; font-size: 11px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; text-align: left; width: 120px;">Progress</th>
                        <th style="padding: 10px 14px; font-size: 11px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; text-align: left;">Status</th>
                        <th style="padding: 10px 16px; font-size: 11px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                        <tr style="border-bottom: 1px solid #F1F5F9; transition: background 0.15s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='#FFFFFF'">
                            <td><a class="tw-task-name" href="{{ route('projects.show',[$task->project_id,'focus_task'=>$task->id]) }}">{{ $task->title }}</a><div class="tw-task-context"><span>{{ $task->display_number }}</span><span aria-hidden="true">&middot;</span><a href="{{ route('projects.show',$task->project_id) }}">{{ $task->project?->name ?? 'Project' }}</a></div></td>
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
                                    @if(!auth()->user()->isAdminViewer() && $task->progress < 100 && $task->assignees->isEmpty())
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

