@extends('layouts.app')
@section('title','Team Monitor')
@section('content')
<link rel="stylesheet" href="{{ asset('css/task-workspace.css') }}?v={{ filemtime(public_path('css/task-workspace.css')) }}">
@php
    $teamRoute = request()->routeIs('monitoring.index') ? 'monitoring.index' : 'pmo.team-monitor';
    $moreFilters = !empty($filters['subsidiary_id']) || !empty($filters['has_critical']);
    $hasFilters = !empty($filters['q']) || !empty($filters['project_id']) || !empty($filters['subsidiary_id']) || !empty($filters['has_critical']) || !empty($filters['has_overdue']);
@endphp

<div class="task-workspace team-review-workspace">
    <header class="tw-header" style="margin-bottom: 20px;">
        <div>
            <h1 style="letter-spacing: -0.5px;">Team Monitor</h1>
            <p class="muted" style="margin-top: 4px;">Review employee assignments and workload across projects.</p>
        </div>
    </header>

    <!-- Minimal Simple KPI Metric Cards -->
    <div class="team-summary-strip" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px;">
        <div class="card" style="padding: 14px 18px; border: 1px solid var(--border); border-radius: 8px; background: #FFFFFF; box-shadow: none;">
            <div style="font-size: 12px; font-weight: 500; color: #64748B;">Active Employees</div>
            <div style="font-size: 24px; font-weight: 600; color: #0F172A; line-height: 1.2; margin-top: 4px; letter-spacing: -0.5px;">{{ $summary['active_employees'] ?? 0 }}</div>
        </div>

        <div class="card" style="padding: 14px 18px; border: 1px solid var(--border); border-radius: 8px; background: #FFFFFF; box-shadow: none;">
            <div style="font-size: 12px; font-weight: 500; color: #64748B;">Employees with Open Tasks</div>
            <div style="font-size: 24px; font-weight: 600; color: #0F172A; line-height: 1.2; margin-top: 4px; letter-spacing: -0.5px;">{{ $summary['employees_with_open_tasks'] ?? 0 }}</div>
        </div>

        <div class="card" style="padding: 14px 18px; border: 1px solid var(--border); border-radius: 8px; background: #FFFFFF; box-shadow: none;">
            <div style="font-size: 12px; font-weight: 500; color: #64748B;">Open Task Assignments</div>
            <div style="font-size: 24px; font-weight: 600; color: #0F172A; line-height: 1.2; margin-top: 4px; letter-spacing: -0.5px;">{{ $summary['open_assignments'] ?? 0 }}</div>
        </div>

        <div class="card" style="padding: 14px 18px; border: 1px solid var(--border); border-radius: 8px; background: #FFFFFF; box-shadow: none;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 12px; font-weight: 500; color: #64748B;">Overdue Assignments</span>
                @if(($summary['overdue_assignments'] ?? 0) > 0)
                    <span style="font-size: 10px; font-weight: 600; background: #FEE2E2; color: #DC2626; padding: 1px 6px; border-radius: 4px;">Action needed</span>
                @endif
            </div>
            <div style="font-size: 24px; font-weight: 600; color: {{ ($summary['overdue_assignments'] ?? 0) > 0 ? '#DC2626' : '#0F172A' }}; line-height: 1.2; margin-top: 4px; letter-spacing: -0.5px;">{{ $summary['overdue_assignments'] ?? 0 }}</div>
        </div>
    </div>

    <section class="team-filter-panel" aria-label="Team filters">
        <nav class="team-filter-tabs" aria-label="People views">
            <a class="{{ empty($filters['has_overdue'])?'is-active':'' }}" href="{{ route($teamRoute,array_merge(request()->except(['page','has_overdue']),request()->routeIs('monitoring.index')?['tab'=>'team']:[])) }}" @if(empty($filters['has_overdue'])) aria-current="page" @endif>All people</a>
            <a class="{{ !empty($filters['has_overdue'])?'is-active':'' }}" href="{{ route($teamRoute,array_merge(request()->except('page'),['has_overdue'=>1],request()->routeIs('monitoring.index')?['tab'=>'team']:[])) }}" @if(!empty($filters['has_overdue'])) aria-current="page" @endif>With overdue tasks</a>
            <span class="team-filter-result" role="status">{{ $employees->total() }} {{ Str::plural('person',$employees->total()) }}</span>
        </nav>
        <form method="get" action="{{ route($teamRoute) }}" id="team-filter-form" class="team-filter-toolbar">
            @if(request()->routeIs('monitoring.index'))<input type="hidden" name="tab" value="team">@endif
            @if(!empty($filters['has_overdue']))<input type="hidden" name="has_overdue" value="1">@endif
            <label class="team-filter-search"><span class="workspace-sr-only">Search people</span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="m15 15 5 5"/></svg><input type="search" name="q" value="{{ $filters['q']??'' }}" placeholder="Search people..." autocomplete="off"></label>
            <label><span class="workspace-sr-only">Project</span><select name="project_id" onchange="this.form.requestSubmit()"><option value="">All active projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(($filters['project_id']??'')==$project->id)>{{ $project->name }}</option>@endforeach</select></label>
            <label><span class="workspace-sr-only">Subsidiary</span><select name="subsidiary_id" onchange="this.form.requestSubmit()"><option value="">All subsidiaries</option>@foreach($subsidiaries as $company)<option value="{{ $company->id }}" @selected(($filters['subsidiary_id']??'')==$company->id)>{{ $company->name }}</option>@endforeach</select></label>
            <label class="team-critical-toggle"><input type="checkbox" name="has_critical" value="1" @checked($filters['has_critical']??false) onchange="this.form.requestSubmit()"><span>Critical tasks</span></label>
            <button type="submit" class="btn btn-primary">Search</button>
            @if($hasFilters)<a class="team-filter-clear" href="{{ route($teamRoute,request()->routeIs('monitoring.index')?['tab'=>'team']:[]) }}">Clear all</a>@endif
        </form>
    </section>

    <!-- Team Monitor Table Card -->
    <div class="card tw-table-surface" style="padding: 0; overflow: hidden; border-radius: 10px; border: 1px solid var(--border); background: #FFFFFF; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div class="table-wrap">
            <table class="tw-table tw-team-table" id="monitoring-team-records" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #FAFBFC; border-bottom: 1px solid var(--border); text-align: left;">
                        <th style="padding: 12px 18px; font-size: 11.5px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px;">Person</th>
                        <th style="padding: 12px 18px; font-size: 11.5px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px;">Projects</th>
                        <th style="padding: 12px 18px; font-size: 11.5px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; text-align: center;">Open tasks</th>
                        <th style="padding: 12px 18px; font-size: 11.5px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; text-align: center;">Overdue</th>
                        <th style="padding: 12px 18px; font-size: 11.5px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px;">Task to follow up</th>
                        <th class="right" style="padding: 12px 18px; font-size: 11.5px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $person)
                        @php
                            $taskLink = $person->priority_task_id ? route('projects.show', [$person->priority_task_project_id, 'focus_task' => $person->priority_task_id]) . '#tasks' : route('pmo.task-monitor', array_filter(['user_id' => $person->id, 'project_id' => $filters['project_id'] ?? null, 'filter' => $person->open_tasks_count > 0 ? 'open' : 'completed', 'active_only' => 1], fn($value) => $value !== null && $value !== ''));
                        $priorityProjectName = collect($person->active_project_names)->firstWhere('id', $person->priority_task_project_id)['name'] ?? null;
                            $priorityOverdue = $person->priority_task_due_at?->isPast() ?? false;
                            $overdueDays = $priorityOverdue ? max(1, (int) $person->priority_task_due_at->copy()->startOfDay()->diffInDays(today(), false)) : 0;
                        @endphp
                        <tr style="border-bottom: 1px solid var(--border); transition: background-color 0.15s ease;">
                            <td style="padding: 12px 18px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="avatar avatar-sm" style="width: 34px; height: 34px; border-radius: 8px; font-size: 13px; font-weight: 700; background: #EEF2F6; color: #334155; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        {{ strtoupper(substr($person->name, 0, 1)) }}
                                    </span>
                                    <div>
                                        <a class="team-person-name" href="{{ route('users.show', $person) }}" style="font-size: 13.5px; font-weight: 650; color: #0F172A; text-decoration: none;">
                                            {{ $person->name }}
                                        </a>
                                        <small class="team-person-meta" style="display: block; font-size: 11.5px; color: #64748B; margin-top: 1px;">
                                            {{ $person->job_title ?: ($person->subsidiary?->name ?: $person->email) }}
                                        </small>
                                    </div>
                                </div>
                            </td>

                            <td style="padding: 12px 18px;">
                                <div class="team-project-list">
                                    @foreach(collect($person->active_project_names)->take(1) as $item)
                                        <a href="{{ route('projects.show', $item['id']) }}" style="font-size: 12.5px; font-weight: 600; color: var(--primary); text-decoration: none;">
                                            {{ $item['name'] }}
                                        </a>
                                    @endforeach
                                    @if($person->active_projects_count > 1)
                                        <details class="team-more-projects"><summary>+{{ $person->active_projects_count - 1 }} more</summary><div>@foreach(collect($person->active_project_names)->skip(1) as $extraProject)<a href="{{ route('projects.show', $extraProject['id']) }}">{{ $extraProject['name'] }}</a>@endforeach</div></details>
                                    @elseif(!$person->active_projects_count)
                                        <span class="team-empty-priority" style="font-size: 11.5px; color: #94A3B8;">No active projects</span>
                                    @endif
                                </div>
                            </td>

                            <td class="team-number" style="padding: 12px 18px; text-align: center;">
                                <a class="team-open-count" href="{{ route('pmo.task-monitor', array_filter(['user_id'=>$person->id,'project_id'=>$filters['project_id']??null,'filter'=>'open','active_only'=>1])) }}" aria-label="View {{ $person->open_tasks_count }} open tasks for {{ $person->name }}">{{ $person->open_tasks_count }}</a>
                            </td>

                            <td class="team-number" style="padding: 12px 18px; text-align: center;">
                                @if($person->overdue_tasks_count > 0)
                                    <a class="team-overdue-link pill pill-danger" href="{{ route('pmo.task-monitor', array_filter(['user_id' => $person->id, 'project_id' => $filters['project_id'] ?? null, 'filter' => 'overdue', 'active_only' => 1])) }}" style="font-size: 11px; font-weight: 700; padding: 2px 8px; background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; text-decoration: none; border-radius: 12px;">
                                        {{ $person->overdue_tasks_count }} overdue
                                    </a>
                                @else
                                    <span class="team-empty-priority" style="color: #94A3B8;">&mdash;</span>
                                @endif
                            </td>

                            <td style="padding: 12px 18px;">
                                @if($person->priority_task_id)
                                    <a class="team-priority-task" href="{{ route('projects.show', [$person->priority_task_project_id, 'focus_task' => $person->priority_task_id]) }}#tasks" style="font-size: 13px; font-weight: 600; color: #0F172A; text-decoration: none; line-height: 1.4;">
                                        {{ $person->priority_task_title }}
                                    </a>
                                    @if($priorityProjectName)<span class="team-task-project">{{ $priorityProjectName }}</span>@endif
                                    <small class="team-priority-date {{ $person->priority_task_due_at->isPast() ? 'text-danger' : '' }}" style="display: block; font-size: 11.5px; font-weight: {{ $person->priority_task_due_at->isPast() ? '650' : '500' }}; color: {{ $person->priority_task_due_at->isPast() ? '#DC2626' : '#64748B' }}; margin-top: 2px;">
                                        {{ $person->priority_task_due_at->format('d M Y') }} &middot; @if($priorityOverdue){{ $overdueDays }} {{ Str::plural('day', $overdueDays) }} overdue @elseif($person->priority_task_due_at->isToday())Due today @else Upcoming @endif
                                    </small>
                                @else
                                    <span class="team-empty-priority" style="font-size: 12px; color: #94A3B8;">No open tasks</span>
                                @endif
                            </td>

                            <td class="right" style="padding: 12px 18px; text-align: right;">
                                @if($person->open_tasks_count + $person->completed_tasks_count > 0)
                                    <a class="btn btn-outline btn-xs team-view-tasks" href="{{ $taskLink }}" style="font-weight: 600; white-space: nowrap;">
                                        {{ $person->priority_task_id ? 'Open task' : 'View tasks' }} &rarr;
                                    </a>
                                @else
                                    <span class="team-empty-priority" style="font-size: 12px; color: #94A3B8;">No tasks</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 36px 18px; text-align: center;">
                                <div class="tw-empty">
                                    <strong style="font-size: 14px; color: #0F172A;">{{ !empty($filters['has_overdue'])?'No people with overdue tasks':'No matching people' }}</strong>
                                    <p class="muted text-sm" style="margin-top: 4px;">{{ !empty($filters['has_overdue'])?'No active team members have overdue assigned tasks matching these filters. Unassigned overdue tasks appear in Task Monitor.':'Try another name or change your project/subsidiary filters.' }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($employees->hasPages())
            <footer class="tw-pagination" style="padding: 12px 20px; border-top: 1px solid var(--border); background: #FAFBFC;">
                {{ $employees->withQueryString()->links() }}
            </footer>
        @endif
    </div>
</div>
@endsection
