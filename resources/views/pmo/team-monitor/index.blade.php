@extends('layouts.app')
@section('title', 'Team Monitor')

@section('content')
<div class="monitoring-team-workspace">
    @if(request()->routeIs('monitoring.index'))
        @include('monitoring._header')
    @else
    <!-- Breadcrumb Trail -->
    <div class="breadcrumb-trail" style="margin-bottom: 14px;">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <span class="crumb-sep">›</span>
        <span>People</span>
        <span class="crumb-sep">›</span>
        <span>Team Monitor</span>
    </div>

    <!-- Page Header -->
    <div class="page-head" style="margin-bottom: 22px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 700; color: #22272B; margin: 0 0 4px;">Team Monitor</h1>
            <p class="muted" style="margin: 0; font-size: 13.5px;">Review employee assignments and workload across projects.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="{{ route('pmo.task-monitor') }}" class="btn btn-outline btn-sm" style="height: 36px; line-height: 34px; font-size: 13px;">
                Task Monitor →
            </a>
        </div>
    </div>

    @endif

    <div class="monitoring-team-summary" aria-label="Team summary">
        @foreach(['active_employees'=>'Active Employees','employees_with_open_tasks'=>'Employees with Open Tasks','open_assignments'=>'Open Task Assignments','overdue_assignments'=>'Overdue Assignments'] as $key=>$label)
            <div><span>{{ $label }}</span><strong class="{{ $key==='overdue_assignments' && ($summary[$key]??0)>0 ? 'text-danger' : '' }}">{{ $summary[$key]??0 }}</strong></div>
        @endforeach
    </div>
    @php $teamExtra=!empty($filters['subsidiary_id']) || !empty($filters['has_overdue']) || !empty($filters['has_critical']); @endphp
    <form method="get" action="{{ route(auth()->user()->isPmo() ? 'monitoring.index' : 'pmo.team-monitor') }}" id="team-filter-form" class="monitoring-team-filters">
        @if(request()->routeIs('monitoring.index'))<input type="hidden" name="tab" value="team">@endif
        <div class="monitoring-team-primary">
            <label class="monitoring-team-search"><span class="workspace-sr-only">Search people</span><input type="search" name="q" value="{{ $filters['q']??'' }}" placeholder="Search people by name, title or email..."></label>
            <label><span class="workspace-sr-only">Project</span><select name="project_id" onchange="this.form.requestSubmit()"><option value="">All projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(($filters['project_id']??'')==$project->id)>{{ $project->name }}</option>@endforeach</select></label>
            <label><span class="workspace-sr-only">Workload</span><select name="workload" onchange="this.form.requestSubmit()"><option value="">All workload levels</option>@foreach(['over_capacity'=>'Over capacity (>100%)','near_capacity'=>'Near capacity (80-100%)','within_capacity'=>'Within capacity (<80%)','not_configured'=>'Capacity not configured'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['workload']??'')===$key)>{{ $label }}</option>@endforeach</select></label>
            <button type="submit" class="btn btn-outline btn-sm">Search</button>
            @if(request()->hasAny(['q','project_id','subsidiary_id','workload','has_overdue','has_critical']))<a class="task-filter-clear" href="{{ route(auth()->user()->isPmo() ? 'monitoring.index' : 'pmo.team-monitor',['tab'=>'team']) }}">Clear</a>@endif
        </div>
        <div class="monitoring-team-secondary"><details @if($teamExtra) open @endif><summary>More filters</summary><div class="monitoring-team-extra"><label>Subsidiary<select name="subsidiary_id" onchange="this.form.requestSubmit()"><option value="">All subsidiaries</option>@foreach($subsidiaries as $subsidiary)<option value="{{ $subsidiary->id }}" @selected(($filters['subsidiary_id']??'')==$subsidiary->id)>{{ $subsidiary->name }}</option>@endforeach</select></label><label class="monitoring-check"><input type="checkbox" name="has_overdue" value="1" @checked($filters['has_overdue']??false) onchange="this.form.requestSubmit()">Has overdue tasks</label><label class="monitoring-check"><input type="checkbox" name="has_critical" value="1" @checked($filters['has_critical']??false) onchange="this.form.requestSubmit()">Has critical tasks</label></div></details><span>{{ $employees->total() }} people</span></div>
    </form>

    <!-- Team Monitor Table -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div class="table-wrap">
            <table id="monitoring-team-records" class="clean-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #F5F6F7; border-bottom: 1px solid #E5E7EB;">
                        <th style="padding: 12px 16px; font-size: 12px; font-weight: 700; color: #22272B; text-align: left;">Employee</th>
                        <th style="padding: 12px 14px; font-size: 12px; font-weight: 700; color: #22272B; text-align: center;">Active Projects</th>
                        <th style="padding: 12px 14px; font-size: 12px; font-weight: 700; color: #22272B; text-align: center;">Open Tasks</th>
                        <th style="padding: 12px 14px; font-size: 12px; font-weight: 700; color: #22272B; text-align: center;">Completed</th>
                        <th style="padding: 12px 14px; font-size: 12px; font-weight: 700; color: #22272B; text-align: center;">Overdue</th>
                        <th style="padding: 12px 14px; font-size: 12px; font-weight: 700; color: #22272B; text-align: center;">Critical Tasks</th>
                        <th style="padding: 12px 14px; font-size: 12px; font-weight: 700; color: #22272B; text-align: left;">Remaining Effort</th>
                        <th style="padding: 12px 14px; font-size: 12px; font-weight: 700; color: #22272B; text-align: left;">Workload Utilization</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                        <tr style="border-bottom: 1px solid #F1F5F9; transition: background 0.15s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='#FFFFFF'">
                            <!-- Employee -->
                            <td style="padding: 12px 16px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="avatar" style="width: 32px; height: 32px; font-size: 12px;">{{ strtoupper(substr($emp->name, 0, 1)) }}</span>
                                    <div>
                                        <div style="font-size: 13.5px; font-weight: 650; color: #22272B;">
                                            <a href="javascript:void(0)" onclick="openEmployeeWorkloadModal({{ $emp->id }})" style="color: #22272B; text-decoration: none;" onmouseover="this.style.color='#315F58'" onmouseout="this.style.color='#22272B'">
                                                {{ $emp->name }}
                                            </a>
                                        </div>
                                        <div style="font-size: 11.5px; color: #64748B;">
                                            {{ $emp->job_title ?? ($emp->subsidiary?->code ?? 'Employee') }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Active Projects -->
                            <td style="padding: 12px 14px; text-align: center; font-size: 13px; font-weight: 650; color: #22272B;">
                                {{ $emp->active_projects_count }}
                            </td>

                            <!-- Open Tasks -->
                            <td style="padding: 12px 14px; text-align: center; font-size: 13px; font-weight: 650; color: #22272B;">
                                {{ $emp->open_tasks_count }}
                            </td>

                            <!-- Completed Tasks -->
                            <td style="padding: 12px 14px; text-align: center; font-size: 13px; font-weight: 600; color: #107C41;">
                                {{ $emp->completed_tasks_count }}
                            </td>

                            <!-- Overdue Tasks -->
                            <td style="padding: 12px 14px; text-align: center;">
                                @if($emp->overdue_tasks_count > 0)
                                    <span style="font-size: 13px; font-weight: 700; color: #DC2626; background: #FEF2F2; padding: 2px 8px; border-radius: 12px; border: 1px solid #FECACA;">
                                        {{ $emp->overdue_tasks_count }}
                                    </span>
                                @else
                                    <span style="font-size: 13px; color: #94A3B8;">0</span>
                                @endif
                            </td>

                            <!-- Critical Tasks -->
                            <td style="padding: 12px 14px; text-align: center;">
                                @if($emp->critical_tasks_count > 0)
                                    <span style="font-size: 13px; font-weight: 700; color: #B45309; background: #FFFBEB; padding: 2px 8px; border-radius: 12px; border: 1px solid #FDE68A;">
                                        {{ $emp->critical_tasks_count }}
                                    </span>
                                @else
                                    <span style="font-size: 13px; color: #94A3B8;">0</span>
                                @endif
                            </td>

                            <!-- Remaining Effort -->
                            <td style="padding: 12px 14px; font-size: 12.5px; color: #334155;">
                                @if($emp->remaining_hours !== null)
                                    <b>{{ $emp->remaining_hours }}h</b> remaining
                                @else
                                    <span style="color: #94A3B8; font-style: italic;">Not estimated</span>
                                @endif
                            </td>

                            <!-- Workload Utilization -->
                            <td style="padding: 12px 14px;">
                                @if($emp->utilization_percent !== null)
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 13px; font-weight: 700; color: #22272B;">{{ $emp->utilization_percent }}%</span>
                                        <span class="status-pill {{ $emp->badge_class }}" style="font-size: 11px; padding: 2px 7px;">
                                            {{ $emp->workload_label }}
                                        </span>
                                    </div>
                                    @if($emp->available_capacity)
                                        <div style="font-size: 11px; color: #94A3B8; margin-top: 2px;">
                                            Cap: {{ $emp->available_capacity }}h / week
                                        </div>
                                    @endif
                                @else
                                    <span style="font-size: 12px; color: #94A3B8; font-style: italic;">Capacity not configured</span>
                                @endif
                            </td>


                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 48px 20px; color: #64748B;">
                                <div style="font-size: 15px; font-weight: 650; color: #22272B; margin-bottom: 6px;">No employees match your search criteria</div>
                                <div style="font-size: 13px;">Try adjusting filters or clear search term.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($employees->hasPages())
            <div style="padding: 14px 20px; border-top: 1px solid #E5E7EB; background: #F8FAFC;">
                {{ $employees->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

<!-- =========================================================================
     MODAL 1: EMPLOYEE WORKLOAD DETAILS POPUP (Interactive with Reassign)
     ========================================================================= -->
<div id="pmo-team-workload-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px); z-index: 9998; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #FFFFFF; border-radius: 8px; width: 100%; max-width: 920px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); border: 1px solid #CBD5E1; display: flex; flex-direction: column;">
        <div style="padding: 18px 24px; border-bottom: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: space-between; background: #F8FAFC;">
            <div>
                <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B;">Employee Workload & Assignments</span>
                <h3 id="twm-name" style="margin: 2px 0 0; font-size: 18px; font-weight: 700; color: #22272B;">Employee Name</h3>
            </div>
            <button type="button" onclick="closeTeamWorkloadModal()" style="border: none; background: transparent; font-size: 20px; color: #64748B; cursor: pointer; padding: 4px 8px;">✕</button>
        </div>

        <div id="twm-body" style="padding: 24px; font-size: 13px;">
            <div style="text-align: center; padding: 40px; color: #64748B;">Loading workload data...</div>
        </div>

        <div style="padding: 12px 24px; border-top: 1px solid #E5E7EB; background: #F8FAFC; display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeTeamWorkloadModal()" class="btn btn-outline btn-sm">Close</button>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: CONTROLLED TASK REASSIGNMENT POPUP
     ========================================================================= -->
<div id="pmo-reassign-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #FFFFFF; border-radius: 8px; width: 100%; max-width: 520px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); border: 1px solid #CBD5E1; padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #22272B;">Reassign Task</h3>
            <button type="button" onclick="closeReassignModal()" style="border: none; background: transparent; font-size: 18px; color: #64748B; cursor: pointer;">✕</button>
        </div>

        <form id="reassign-task-form" onsubmit="event.preventDefault(); submitTaskReassignment();">
            <input type="hidden" id="reassign-task-id">
            <input type="hidden" id="reassign-old-user-id">

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 650; margin-bottom: 4px; color: #475569;">Task</label>
                <div id="reassign-task-display" style="font-size: 13.5px; font-weight: 600; color: #22272B; background: #F8FAFC; padding: 8px 12px; border-radius: 6px; border: 1px solid #E2E8F0;"></div>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 650; margin-bottom: 4px; color: #475569;">Current Assignee</label>
                <div id="reassign-old-user-display" style="font-size: 13px; font-weight: 500; color: #64748B; padding: 0 4px;"></div>
            </div>

            <div style="margin-bottom: 14px;">
                <label for="reassign-new-user-select" style="display: block; font-size: 12.5px; font-weight: 650; margin-bottom: 4px; color: #22272B;">
                    New Assignee (Eligible Project Members) <span style="color:#DC2626;">*</span>
                </label>
                <select id="reassign-new-user-select" required style="width: 100%; height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 13px; background: #FFFFFF; color: #22272B;"></select>
                <small style="display:block;font-size:11.5px;color:#64748B;margin-top:3px;">Only authorized team members of this project are eligible.</small>
            </div>

            <div style="margin-bottom: 18px;">
                <label for="reassign-reason" style="display: block; font-size: 12.5px; font-weight: 650; margin-bottom: 4px; color: #22272B;">Reassignment Reason</label>
                <textarea id="reassign-reason" rows="2" placeholder="Explain why this task is being reassigned..." style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 8px 12px; font-size: 13px; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeReassignModal()" class="btn btn-outline btn-sm">Cancel</button>
                <button type="submit" id="btn-submit-reassign" class="btn btn-primary btn-sm" style="font-weight: 650; background: #315F58; color: #FFFFFF;">Confirm Reassignment</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: SHARED TASK DETAILS POPUP
     ========================================================================= -->
<div id="pmo-task-details-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #FFFFFF; border-radius: 8px; width: 100%; max-width: 780px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); border: 1px solid #CBD5E1; display: flex; flex-direction: column;">
        <div style="padding: 16px 20px; border-bottom: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: space-between; background: #F8FAFC;">
            <div>
                <span id="ptd-project-name" style="font-size: 12px; color: #64748B; font-weight: 500;">Project Name</span>
                <h3 id="ptd-task-title" style="margin: 2px 0 0; font-size: 17px; font-weight: 700; color: #22272B;">Task Details</h3>
            </div>
            <button type="button" onclick="closeTaskDetailsModal()" style="border: none; background: transparent; font-size: 20px; color: #64748B; cursor: pointer; padding: 4px 8px;">✕</button>
        </div>

        <div id="ptd-modal-body" style="padding: 20px; font-size: 13px; color: #22272B;">
            <div style="text-align: center; padding: 40px; color: #64748B;">Loading task details...</div>
        </div>

        <div style="padding: 12px 20px; border-top: 1px solid #E5E7EB; background: #F8FAFC; display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeTaskDetailsModal()" class="btn btn-outline btn-sm">Close</button>
        </div>
    </div>
</div>

<script>
    let currentViewingUserId = null;

    /* Open Employee Workload Popup */
    function openEmployeeWorkloadModal(userId) {
        currentViewingUserId = userId;
        const backdrop = document.getElementById('pmo-team-workload-modal');
        const body = document.getElementById('twm-body');
        const nameEl = document.getElementById('twm-name');
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

                <!-- Assigned Projects Table -->
                <div style="margin-bottom:20px;">
                    <div style="font-size:13px;font-weight:700;color:#22272B;margin-bottom:8px;">Active Projects & Effective Roles</div>
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px;border:1px solid #E5E7EB;border-radius:6px;overflow:hidden;">
                        <thead>
                            <tr style="background:#F8FAFC;border-bottom:1px solid #E5E7EB;text-align:left;">
                                <th style="padding:8px 12px;">Project</th>
                                <th style="padding:8px 12px;">Effective Role(s)</th>
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

                <!-- Assigned Tasks Section (with Reassign action) -->
                <div>
                    <div style="font-size:13px;font-weight:700;color:#22272B;margin-bottom:8px;">Assigned Tasks (${data.tasks.length})</div>
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
                                        <a href="javascript:void(0)" onclick="openTaskDetailsModal(${t.id});" style="font-weight:600;color:#22272B;text-decoration:none;">
                                            <span style="color:#64748B;">${t.code}</span> ${t.title}
                                        </a>
                                        ${t.is_critical ? '<span class="status-pill pill-warning" style="font-size:10px;margin-left:4px;">Critical</span>' : ''}
                                    </td>
                                    <td style="padding:8px 12px;color:#475569;">${t.project_name}</td>
                                    <td style="padding:8px 12px;color:${t.is_overdue ? '#DC2626;font-weight:600;' : '#475569;'}">${t.deadline}</td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:600;">${t.progress}%</td>
                                    <td style="padding:8px 12px;"><span class="status-pill status-${t.status}" style="font-size:11px;">${t.status_label}</span></td>
                                    <td style="padding:8px 12px;text-align:right;">
                                        <button type="button" onclick="openReassignModal(${t.id}, '${escapeHtml(t.title)}', ${data.employee.id}, '${escapeHtml(data.employee.name)}', ${JSON.stringify(t.eligible_members).replace(/"/g, '&quot;')})" class="btn btn-outline btn-xs" style="font-size:11.5px;padding:2px 8px;">
                                            Reassign
                                        </button>
                                    </td>
                                </tr>
                            `).join('') : `<tr><td colspan="6" style="text-align:center;padding:16px;color:#64748B;">No assigned tasks.</td></tr>`}
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

    function closeTeamWorkloadModal() {
        document.getElementById('pmo-team-workload-modal').style.display = 'none';
    }

    /* Open Task Details Modal (Cross-Navigation) */
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
            body.innerHTML = '<div style="padding:20px;color:#DC2626;text-align:center;">Failed to load task details.</div>';
        });
    }

    function closeTaskDetailsModal() {
        document.getElementById('pmo-task-details-backdrop').style.display = 'none';
    }

    /* Task Reassignment Workflow */
    function openReassignModal(taskId, taskTitle, oldUserId, oldUserName, eligibleMembers) {
        document.getElementById('reassign-task-id').value = taskId;
        document.getElementById('reassign-old-user-id').value = oldUserId;
        document.getElementById('reassign-task-display').textContent = taskTitle;
        document.getElementById('reassign-old-user-display').textContent = `${oldUserName} (Current Assignee)`;

        const select = document.getElementById('reassign-new-user-select');
        select.innerHTML = '<option value="">-- Select Eligible Project Member --</option>';

        if (eligibleMembers && eligibleMembers.length) {
            eligibleMembers.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = `${m.name}${m.job_title ? ' (' + m.job_title + ')' : ''}`;
                select.appendChild(opt);
            });
        } else {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'No other eligible project members available';
            select.appendChild(opt);
        }

        document.getElementById('pmo-reassign-modal').style.display = 'flex';
    }

    function closeReassignModal() {
        document.getElementById('pmo-reassign-modal').style.display = 'none';
        document.getElementById('reassign-reason').value = '';
    }

    function submitTaskReassignment() {
        const taskId = document.getElementById('reassign-task-id').value;
        const oldUserId = document.getElementById('reassign-old-user-id').value;
        const newUserId = document.getElementById('reassign-new-user-select').value;
        const reason = document.getElementById('reassign-reason').value.trim();
        const submitBtn = document.getElementById('btn-submit-reassign');

        if (!newUserId) {
            alert('Please select a new eligible project member to reassign this task to.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Reassigning...';

        fetch('/pmo/team-monitor/reassign', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                task_id: taskId,
                old_user_id: oldUserId,
                new_user_id: newUserId,
                reason: reason
            })
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Confirm Reassignment';
            if (data.success) {
                alert(data.message);
                closeReassignModal();
                // Refresh workload popup if open
                if (currentViewingUserId) {
                    openEmployeeWorkloadModal(currentViewingUserId);
                }
            } else {
                alert(data.message || 'Reassignment failed.');
            }
        })
        .catch(err => {
            console.error(err);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Confirm Reassignment';
            alert('A server or network error occurred while reassigning the task.');
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    // Keyboard ESC listener
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeTeamWorkloadModal();
            closeReassignModal();
            closeTaskDetailsModal();
        }
    });
</script>
@endsection

