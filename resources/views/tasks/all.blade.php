@extends('layouts.app')
@section('title','All tasks')
@section('content')
<link rel="stylesheet" href="{{ asset('css/task-workspace.css') }}?v={{ filemtime(public_path('css/task-workspace.css')) }}">
@php
    $currentFilter=$filters['filter']??'all';
    $taskViews=['all'=>['All tasks','total_tasks'],'open'=>['Open Tasks','open_tasks'],'overdue'=>['Overdue','overdue_tasks'],'due_soon'=>['Due soon','due_soon_tasks'],'critical'=>['Critical path','critical_tasks'],'unassigned'=>['Unassigned','unassigned_tasks'],'completed'=>['Completed','completed_tasks']];
    $hasFilters=!empty($filters['q']) || !empty($filters['project_id']) || !empty($filters['user_id']) || !empty($filters['status']) || ($scope !== 'all');
@endphp
<div class="task-workspace task-workspace-personal">
    <header class="tw-header">
        <div>
            <h1>All tasks</h1>
            <p>Your assignments and the project tasks you monitor, together.</p>
        </div>
    </header>
    @if($pmoOperationalMode ?? false)
        <nav class="tw-operational-tabs" aria-label="Task workspace">
            <a href="{{ route('tasks.mine',['scope'=>'all']) }}" class="{{ $scope==='all' ? 'is-active' : '' }}">All tasks</a>
            <a href="{{ route('tasks.mine',['scope'=>'assigned']) }}" class="{{ $scope==='assigned' ? 'is-active' : '' }}">My tasks</a>
        </nav>
    @endif
    <section class="personal-filter-surface" aria-label="Task filters">
        <div class="tw-filter-tabs-bar">
            <nav class="tw-views" aria-label="Filter tasks">
                @foreach($taskViews as $key=>$item)
                    @php
                        $count = $metrics[$item[1]] ?? 0;
                        $isActive = ($currentFilter === $key);
                    @endphp
                    <a href="{{ route('tasks.mine', array_merge(request()->except('page','view','filter'), ['scope'=>$scope, 'filter'=>$key])) }}" class="{{ $isActive ? 'is-active' : '' }}" @if($isActive) aria-current="page" @endif>
                        <span>{{ $item[0] }}</span>
                        <span class="tw-badge {{ $key === 'overdue' && $count > 0 ? 'is-danger' : '' }}">{{ $count }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="tw-result-counter">
                <span>{{ $tasks->total() }} {{ Str::plural('task', $tasks->total()) }}</span>
            </div>
        </div>

        @if(!empty($filters['attention']))
            <div class="attention-filter-context">
                <div class="attention-context-msg">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                    <span>Showing: <strong>{{ ['my_overdue'=>'Your overdue tasks','follow_up'=>'Blockers needing your follow-up','managed_overdue'=>'Overdue work in projects you manage','needs_owner'=>'Unassigned work starting within 3 working days (including overdue starts)'][$filters['attention']] ?? '' }}</strong></span>
                </div>
                <a href="{{ route('tasks.mine') }}" class="attention-clear-link">&times; Clear attention filter</a>
            </div>
        @endif

        <form method="get" action="{{ route('tasks.mine') }}" class="tw-filters-toolbar" id="all-task-filter-form">
            @if(!empty($filters['attention']))<input type="hidden" name="attention" value="{{ $filters['attention'] }}">@endif
            <input type="hidden" name="filter" value="{{ $currentFilter }}">

            <!-- Search input + Search button in one combined group -->
            <div class="tw-search-group">
                <div class="tw-search-input-wrap">
                    <svg class="tw-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="search" name="q" value="{{ $filters['q']??'' }}" placeholder="Search tasks, projects or people..." autocomplete="off" style="padding-left: 38px !important;">
                </div>
                <button class="btn btn-primary personal-search-submit" type="submit">Search</button>
            </div>

            <!-- Filter Dropdowns group -->
            <div class="tw-selects-group">
                <div class="tw-select-wrap tw-scope-select">
                    <select name="scope" onchange="this.form.requestSubmit()" aria-label="Task ownership">
                        <option value="all" @selected($scope==='all')>All tasks</option>
                        <option value="assigned" @selected($scope==='assigned')>My tasks</option>
                        @if($monitored->isNotEmpty() || $scope==='managed')
                            <option value="managed" @selected($scope==='managed')>Managed by Me</option>
                        @endif
                    </select>
                </div>
                <div class="tw-select-wrap tw-project">
                    <select name="project_id" onchange="this.form.requestSubmit()" aria-label="Project">
                        <option value="">All projects</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected(($filters['project_id']??'')==$project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="tw-select-wrap tw-assignee-filter">
                    <select name="user_id" onchange="this.form.requestSubmit()" aria-label="Assignee">
                        <option value="">All assignees</option>
                        <option value="unassigned" @selected(($filters['user_id']??'')==='unassigned')>Unassigned</option>
                        @foreach($assignees as $person)
                            <option value="{{ $person->id }}" @selected(($filters['user_id']??'')==$person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="tw-select-wrap tw-status-filter">
                    <select name="status" onchange="this.form.requestSubmit()" aria-label="Status">
                        <option value="">All statuses</option>
                        @foreach(['todo'=>'To do','in_progress'=>'In progress','on_hold'=>'On hold','completed'=>'Completed'] as $key=>$label)
                            <option value="{{ $key }}" @selected(($filters['status']??'')===$key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if($hasFilters)
                    <a class="tw-clear-btn" href="{{ route('tasks.mine',['filter'=>$currentFilter]) }}">Clear</a>
                @endif
            </div>
        </form>
    </section>
    <div class="tw-table-surface"><div class="table-wrap"><table class="tw-table tw-personal-table">
        <thead><tr><th>Task / Project</th><th>Assignee</th><th>Your involvement</th><th>Due</th><th>Progress</th><th>Status</th></tr></thead><tbody>
        @forelse($tasks as $task)
            @php
                $isPast=$task->isOverdue();
                $canUpdate=app(\App\Services\Access::class)->task(auth()->user(),$task,'update') && app(\App\Services\Access::class)->project(auth()->user(),$task->project,'tasks.update_status') && $task->project->status==='active';
            @endphp
            <tr id="task-row-{{ $task->id }}" data-task-status="{{ $task->status }}">
                <td><a class="tw-task-name" href="{{ route('projects.show',[$task->project_id,'focus_task'=>$task->id]) }}">{{ $task->title }}</a><div class="tw-task-context"><span>{{ $task->display_number }}</span><span aria-hidden="true">&middot;</span><a href="{{ route('projects.show',$task->project_id) }}">{{ $task->project->name }}</a></div>
                    @if($task->is_critical && $task->status!=='completed')<div class="tw-task-health">@include('tasks._critical-badge',['task'=>$task])</div>@endif
                </td>
                <td>@if(app(\App\Services\Access::class)->project(auth()->user(),$task->project,'tasks.assign') && $task->assignees->isEmpty() && in_array($task->project->status,['active','on_hold']) && $task->status!=='completed')
                    @php
                        $eligible=$task->project->memberships->pluck('user')->push($task->project->manager)->filter(fn($u)=>$u && $u->account_status==='active' && $u->system_role==='user')->unique('id')->sortBy('name');
                    @endphp
                    <label class="tw-inline-assignment"><span class="workspace-sr-only">Assign {{ $task->title }}</span><select data-pm-assign-url="{{ route('tasks.pm-assign',$task) }}" data-directory-picker data-members-only="1" data-directory-project="{{ $task->project_id }}"><option value="">Assign person...</option>@foreach($eligible as $person)<option value="{{ $person->id }}" data-project-member="1">{{ $person->name }} ({{ $person->email }})</option>@endforeach</select></label>
                    @else<span class="tw-assignee {{ $task->assignees->isEmpty() ? 'is-unassigned' : '' }}">{{ $task->assignees->pluck('name')->join(', ') ?: 'Unassigned' }}</span>@endif</td>
                <td><div class="tw-involvement">
                    @if($task->is_my_task)<span class="tw-my-task">My task</span>@endif
                    @if($task->is_monitored_task)<span class="tw-monitored">Monitoring</span>@endif
                </div></td>
                <td><span class="tw-due {{ $isPast ? 'is-overdue' : '' }}">{{ $task->ends_at?->format('d M Y') ?? 'Not scheduled' }}</span>@if($isPast)<small class="tw-overdue-note badge-overdue">Overdue</small>@endif</td>
                <td><div class="tw-progress"><span class="task-progress-text">{{ $task->progress }}%</span><span class="tw-progress-track"><i class="task-progress-fill" style="width:{{ $task->progress }}%"></i></span></div></td>
                <td>
                    @if($canUpdate)
                        <label class="tw-status-control"><span class="workspace-sr-only">Status for {{ $task->title }}</span><select class="quick-status-select" data-task-id="{{ $task->id }}" data-update-url="{{ route('tasks.update',$task) }}" data-current-status="{{ $task->status }}">@foreach(['todo'=>'To do','in_progress'=>'In progress','on_hold'=>'On hold','completed'=>'Completed'] as $key=>$label)<option value="{{ $key }}" @selected($task->status===$key)>{{ $label }}</option>@endforeach</select></label>
                    @else
                        <span class="status-pill status-{{ $task->status }}">{{ ucwords(str_replace('_',' ',$task->status)) }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="tw-empty"><strong>No matching tasks</strong><p>Try another search or change your filters.</p><a href="{{ route('tasks.mine',['scope'=>'all']) }}">View all tasks &rarr;</a></div></td></tr>
        @endforelse
        </tbody></table></div>
        @if($tasks->hasPages())<footer class="tw-pagination">{{ $tasks->withQueryString()->links() }}</footer>@endif
    </div>
    <p id="progress-save-feedback" class="workspace-sr-only" role="status"></p>
</div>
<script defer src="{{ asset('js/task-workspace.js') }}?v={{ filemtime(public_path('js/task-workspace.js')) }}"></script>
@endsection
