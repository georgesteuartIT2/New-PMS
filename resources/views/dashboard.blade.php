@extends('layouts.app')
@section('title', $isPmo ? 'Dashboard' : 'My Workspace')
@section('content')
<div class="home-workspace">
    <div class="page-head">
        <div><span class="workspace-eyebrow">{{ now()->format('l, d M Y') }}</span><h1>{{ $isPmo ? 'Dashboard' : 'My Workspace' }}</h1><p class="muted">{{ $isPmo ? 'See what needs attention, then keep work moving.' : 'Your priorities, projects and upcoming work in one place.' }}</p></div>
        <div class="inline-actions">@if($isPmo)<a href="{{ route('reports.index') }}" class="btn btn-ghost">Reports</a><a href="{{ route('projects.create') }}" class="btn btn-primary">+ New project</a>@else<a href="{{ route('tasks.mine') }}" class="btn btn-primary">My tasks &rarr;</a>@endif</div>
    </div>
    @php
        $homeMetrics = $isPmo ? [
            ['label'=>'All projects','value'=>$kpi['totalProjects']??0,'url'=>route('projects.index')],
            ['label'=>'Active','value'=>$kpi['activeProjects']??0,'url'=>route('projects.index',['status'=>'active'])],
            ['label'=>'Awaiting PM','value'=>$kpi['pendingAcceptance']??0,'url'=>route('projects.index',['status'=>'pending'])],
            ['label'=>'Need attention','value'=>$kpi['atRiskProjects']??0,'url'=>route('projects.index',['attention'=>1])],
            ['label'=>'Overdue tasks','value'=>$kpi['overdueTasks']??0,'url'=>route('monitoring.index',['filter'=>'overdue']),'warning'=>true],
        ] : [
            ['label'=>'My projects','value'=>$kpi['myProjects']??0,'url'=>route('projects.index')],
            ['label'=>'Open tasks','value'=>$kpi['activeTasks']??0,'url'=>route('tasks.mine')],
            ['label'=>'Need my attention','value'=>$kpi['attentionCount']??0,'url'=>'#my-attention'],
        ];
        $homeWork = $isPmo ? $upcomingTasks : $upcomingWork;
        $homeProjects = $isPmo ? $recentProjects : $myProjectsPreview;
    @endphp
    <nav class="home-summary" aria-label="Workspace summary">
        @foreach($homeMetrics as $metric)<a href="{{ $metric['url'] }}"><span>{{ $metric['label'] }}</span><strong class="{{ !empty($metric['warning']) && $metric['value']>0 ? 'text-danger' : '' }}">{{ $metric['value'] }}</strong><span class="home-summary-arrow" aria-hidden="true">&rarr;</span></a>@endforeach
    </nav>
    <div class="home-grid">
        <div class="home-main">
            <section class="home-panel" aria-labelledby="my-attention">
                <header class="home-panel-head"><div><h2 id="my-attention" tabindex="-1">{{ $isPmo ? 'Needs attention' : 'Your priorities' }} <span class="quiet-count">{{ $totalAttentionCount ?? $attentionItems->count() }}</span></h2><p>Review these items first.</p></div>@if($isPmo)<a class="text-action" href="{{ route('projects.index',['attention'=>1]) }}">Review projects &rarr;</a>@endif</header>
                <div class="home-action-list">
                    @forelse($attentionItems->take(6) as $item)
                        @php $taskAction = !$isPmo && !empty($item['task_id']); @endphp
                        <article class="home-action-item">
                            <span class="home-action-marker {{ in_array($item['type']??'', ['pending','invitation']) ? 'is-pending' : '' }}" aria-hidden="true"></span>
                            <div class="home-action-copy"><h3>{{ $isPmo ? $item['name'] : $item['title'] }}</h3><p>{{ $item['issue'] }}</p><small>{{ $isPmo ? ($item['owner'] ?? 'Not assigned') : ($item['project_name'] ?? '') }}</small></div>
                            @if($taskAction)<button class="btn btn-outline btn-sm" type="button" data-task-panel="{{ $item['action_url'] }}">Open task</button>@else<a class="btn btn-outline btn-sm" href="{{ $item['action_url'] }}">{{ $item['action_label'] }}</a>@endif
                        </article>
                    @empty
                        <div class="home-empty"><span class="home-empty-mark" aria-hidden="true">&#10003;</span><b>You're up to date</b><p>No items need your attention right now.</p><a class="text-action" href="{{ $isPmo ? route('projects.index') : route('tasks.mine') }}">{{ $isPmo ? 'Browse projects' : 'View my tasks' }} &rarr;</a></div>
                    @endforelse
                </div>
            </section>
            <section class="home-panel">
                <header class="home-panel-head"><div><h2>{{ $isPmo ? 'Recent projects' : 'My projects' }}</h2><p>{{ $isPmo ? 'A quick view of current delivery.' : 'Pick up where you left off.' }}</p></div><a class="text-action" href="{{ route('projects.index') }}">View all &rarr;</a></header>
                <div class="table-wrap"><table class="clean-table home-projects-table"><thead><tr><th>Project</th><th>{{ $isPmo ? 'Project manager' : 'Your role' }}</th><th>Progress</th><th>Due</th><th>Status</th></tr></thead><tbody>
                    @forelse($homeProjects as $entry)
                    <tr><td><a class="strong-link" href="{{ $entry['url'] }}">{{ $entry['name'] }}</a><small class="block muted">{{ $entry['code'] }}</small></td><td>{{ $isPmo ? $entry['manager'] : $entry['roles'] }}</td><td>@if($entry['progress']!==null)<div class="home-progress"><span class="home-progress-track"><i style="width:{{ (int)$entry['progress'] }}%"></i></span><span>{{ $entry['progress'] }}%</span></div>@else<span class="muted">&mdash;</span>@endif</td><td>{{ ($isPmo ? $entry['ends_on'] : $entry['deadline'])?->format('d M Y') ?? 'Not set' }}</td><td><span class="status-pill status-{{ $entry['status'] }}">{{ $entry['status_label'] }}</span></td></tr>
                    @empty<tr><td colspan="5"><div class="home-empty"><b>No projects yet</b><p>{{ $isPmo ? 'Create a project and assign its manager to get started.' : 'Your projects will appear here when you are assigned.' }}</p>@if($isPmo)<a class="btn btn-primary btn-sm" href="{{ route('projects.create') }}">+ New project</a>@endif</div></td></tr>@endforelse
                </tbody></table></div>
            </section>
        </div>
        <aside class="home-side" aria-label="Upcoming work and shortcuts">
            <section class="home-panel"><header class="home-panel-head"><div><h2>Upcoming work</h2><p>Next scheduled task deadlines.</p></div><a class="text-action" href="{{ route('calendar.index') }}">Calendar &rarr;</a></header><div class="home-upcoming">
                @forelse($homeWork->take(5) as $task)
                    <button type="button" class="home-upcoming-item" data-task-panel="{{ route('tasks.panel',$task) }}"><span class="home-date"><strong>{{ $task->ends_at->format('d') }}</strong><small>{{ $task->ends_at->format('M') }}</small></span><span class="home-upcoming-copy"><b>{{ $task->title }}</b><small>{{ $task->project?->name }}</small><span class="{{ $task->isOverdue() ? 'text-danger' : 'muted' }}">{{ $task->isOverdue() ? 'Overdue' : $task->ends_at->diffForHumans() }}</span></span><span aria-hidden="true">&rsaquo;</span></button>
                @empty<div class="home-empty home-empty-small"><b>No upcoming deadlines</b><p>Your scheduled work will appear here.</p></div>@endforelse
            </div></section>
            @if($isPmo)
            <section class="home-panel"><header class="home-panel-head"><div><h2>Task Attention</h2><p>Keep assignments and milestones moving.</p></div><a class="text-action" href="{{ route('pmo.task-monitor') }}">Task Monitor →</a></header><div class="home-shortcuts">
                <a href="{{ route('pmo.task-monitor',['filter'=>'overdue']) }}"><span>Overdue Tasks</span><b>{{ $monitoringMetrics['overdue_tasks']??0 }}</b><span aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('pmo.task-monitor',['filter'=>'unassigned']) }}"><span>Unassigned</span><b>{{ $monitoringMetrics['unassigned_tasks']??0 }}</b><span aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('pmo.task-monitor',['filter'=>'critical']) }}"><span>Critical Path</span><b>{{ $monitoringMetrics['critical_tasks']??0 }}</b><span aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('pmo.task-monitor',['filter'=>'needs_update']) }}"><span>Needs Update</span><b>{{ $monitoringMetrics['needs_update_tasks']??0 }}</b><span aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('pmo.team-monitor') }}"><span>Review Workload →</span><span aria-hidden="true">&rarr;</span></a>
            </div></section>
            @elseif($hasMonitoredProjects ?? false)
            <section class="home-panel"><header class="home-panel-head"><div><h2>Projects I manage</h2><p>Your team's work at a glance.</p></div></header><div class="home-shortcuts"><a href="{{ route('tasks.mine',['view'=>'managed']) }}"><span>Review team tasks</span><span aria-hidden="true">&rarr;</span></a><a href="{{ route('tasks.mine',['view'=>'managed','tab'=>'team']) }}"><span>Review team workload</span><span aria-hidden="true">&rarr;</span></a></div></section>
            @endif
        </aside>
    </div>
</div>
@include('tasks._drawer')
<script defer src="{{ asset('js/project-workspace.js') }}?v={{ filemtime(public_path('js/project-workspace.js')) }}"></script>
@endsection
