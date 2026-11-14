@extends('layouts.app')
@section('title', $isPmo ? 'Dashboard' : 'My Workspace')
@section('content')
<div class="home-workspace premium-dashboard">
    <div class="page-head">
        <div><span class="workspace-eyebrow">{{ now()->format('l, d M Y') }}@if($isPmo) &middot; Portfolio Health @endif</span><h1>{{ $isPmo ? 'Project Overview' : 'My workspace' }}</h1><p class="muted">{{ $isPmo ? 'See what needs attention, then keep work moving.' : 'Your priorities, projects and upcoming work in one place.' }}</p></div>
        <div class="inline-actions">
            @if($isPmo)
                <a href="{{ route('monitoring.index') }}" class="btn btn-outline">Monitor work</a>
                @if(auth()->user()->isPmo())
                    <a href="{{ route('projects.create') }}" class="btn btn-primary">+ New project</a>
                @endif
            @else
                <a href="{{ route('tasks.mine') }}" class="btn btn-primary">My Tasks &rarr;</a>
            @endif
        </div>
    </div>
    @php
        $homeMetrics = $isPmo ? [
            ['label'=>'All projects','value'=>$kpi['totalProjects']??0,'url'=>route('projects.index')],
            ['label'=>'Active','value'=>$kpi['activeProjects']??0,'url'=>route('projects.index',['status'=>'active'])],
            ['label'=>'Awaiting approval','value'=>$kpi['pendingAcceptance']??0,'url'=>route('projects.index',['status'=>'pending'])],
            ['label'=>'Need attention','value'=>$kpi['atRiskProjects']??0,'url'=>route('projects.index',['attention'=>1])],
            ['label'=>'Overdue tasks','value'=>$kpi['overdueTasks']??0,'url'=>route('monitoring.index',['filter'=>'overdue']),'warning'=>true],
        ] : [
            ['label'=>'My projects','value'=>$kpi['myProjects']??0,'url'=>route('projects.index')],
            ['label'=>'Open Tasks','value'=>$kpi['activeTasks']??0,'url'=>route('tasks.mine')],
            ['label'=>'Due today','value'=>$dueTodayCount??0,'url'=>'#upcoming-work'],
            ['label'=>'Overdue','value'=>$myOverdueCount??0,'url'=>'#my-attention','warning'=>true],
        ];
        $homeWork = $isPmo ? $upcomingTasks : $upcomingWork;
        $homeProjects = $isPmo ? $recentProjects : $myProjectsPreview;
    @endphp
    @if(!$isPmo && $approvalQueue->total()>0)
    <section class="approval-queue approval-queue-priority" id="project-approvals" aria-labelledby="approval-queue-title">
        <header><div><span class="approval-eyebrow">Action required &middot; Awaiting your decision</span><h2 id="approval-queue-title">Project approvals <span>{{ $approvalQueue->total() }}</span></h2><p>You have been nominated as project manager. Review and respond here.</p></div><span class="approval-queue-count">{{ $approvalQueue->firstItem() }}&ndash;{{ $approvalQueue->lastItem() }} of {{ $approvalQueue->total() }}</span></header>
        @foreach($approvalQueue as $approval)
        <article class="approval-queue-row"><div><a href="{{ route('projects.show',$approval) }}">{{ $approval->name }}</a><small>{{ $approval->code }} &middot; {{ $approval->subsidiary?->name ?? 'General Group' }}</small></div><div class="approval-queue-schedule"><span>{{ $approval->starts_on->format('d M') }} &ndash; {{ $approval->ends_on->format('d M Y') }}</span><small>{{ $approval->tasks_count }} planned tasks &middot; From {{ $approval->creator?->name ?? 'PMO' }}</small></div><a class="btn btn-primary btn-sm" href="{{ route('projects.show',$approval) }}" data-approval-review-url="{{ route('projects.approval-review',$approval) }}">Review request &rarr;</a></article>
        @endforeach
        @if($approvalQueue->hasPages())<footer class="approval-pagination" aria-label="Approval pages">@if($approvalQueue->onFirstPage())<span class="btn btn-outline btn-sm" aria-disabled="true">&larr; Previous</span>@else<a class="btn btn-outline btn-sm" href="{{ $approvalQueue->previousPageUrl() }}">&larr; Previous</a>@endif<span>Page {{ $approvalQueue->currentPage() }} of {{ $approvalQueue->lastPage() }}</span>@if($approvalQueue->hasMorePages())<a class="btn btn-outline btn-sm" href="{{ $approvalQueue->nextPageUrl() }}">Next &rarr;</a>@else<span class="btn btn-outline btn-sm" aria-disabled="true">Next &rarr;</span>@endif</footer>@endif
    </section>
    @endif
    @include('projects._owner-dashboard')
    <nav class="home-summary" aria-label="Workspace summary">
        @foreach($homeMetrics as $metric)<a href="{{ $metric['url'] }}"><span>{{ $metric['label'] }}</span><strong class="{{ !empty($metric['warning']) && $metric['value']>0 ? 'text-danger' : '' }}">{{ $metric['value'] }}</strong><span class="home-summary-arrow" aria-hidden="true">&rarr;</span></a>@endforeach
    </nav>

    <div class="home-grid">
        <div class="home-main">
            @if($isPmo)
            <section class="home-panel dashboard-task-attention" id="task-attention" aria-labelledby="task-attention-title">
                <header class="home-panel-head" style="padding: 18px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 16px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <h2 id="task-attention-title" style="margin: 0; font-size: 17px; font-weight: 700; color: #0F172A; letter-spacing: -0.3px;">Tasks needing attention</h2>
                            @if($unreadBlockerCount)
                                <span class="pill pill-danger" style="font-size: 11px; padding: 2px 7px; font-weight: 700;">{{ $unreadBlockerCount }} new</span>
                            @endif
                        </div>
                        <p style="margin: 4px 0 0; font-size: 12.5px; color: #64748B;">Review urgent work across your projects.</p>
                    </div>
                    <a class="btn btn-outline btn-xs" href="{{ route('pmo.task-monitor') }}" style="font-weight: 600; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;">Task Monitor &rarr;</a>
                </header>

                <nav class="dashboard-task-tabs" aria-label="Tasks needing attention" style="display: flex; gap: 20px; padding: 0 20px; border-bottom: 1px solid var(--border); background: #FAFBFC;">
                    @foreach(['overdue'=>'Overdue','blocked'=>'Reported blockers','critical'=>'Critical path'] as $key=>$label)
                        @php
                            $tabCount = $key==='blocked' ? $blockerCount : ($monitoringMetrics[$key==='critical' ? 'critical_tasks' : 'overdue_tasks'] ?? 0);
                        @endphp
                        <a href="{{ route('dashboard', ['task_attention'=>$key]) }}#task-attention" class="tab-{{ $key }} {{ $taskAttentionFilter===$key ? 'is-active' : '' }}" @if($taskAttentionFilter===$key) aria-current="page" @endif style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 0; text-decoration: none;">
                            <span>{{ $label }}</span>
                            <span class="tab-badge {{ $tabCount > 0 ? 'has-count' : '' }}" style="margin-left: 2px;">{{ $tabCount }}</span>
                        </a>
                    @endforeach
                </nav>

                @if($taskAttentionFilter==='blocked')
                    @forelse($blockerPreview as $report)
                    <article class="dashboard-blocker-row {{ !$report->is_read?'is-unread':'' }}" style="padding: 12px 20px; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: space-between; gap: 16px; transition: background-color 0.15s ease;">
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <strong style="font-size: 13.5px; color: #0F172A;">{{ $report->task?->title }}</strong>
                                <span class="pill pill-danger" style="font-size: 10px; padding: 1px 6px; font-weight: 650;">Blocker</span>
                            </div>
                            <div style="font-size: 11.5px; color: #64748B; margin-top: 3px; display: flex; align-items: center; gap: 6px;">
                                <span>{{ $report->task?->project?->name }}</span> &middot; <span>Reported {{ $report->created_at->format('d M Y') }}</span>
                            </div>
                            <p style="margin: 6px 0 4px; font-size: 12.5px; color: #334155; line-height: 1.5;">{{ \Illuminate\Support\Str::limit($report->reason_label, 140) }}</p>
                            <small style="color: #64748B; font-size: 11px;">Follow-up: <b>{{ $report->actionOwner?->name ?? 'Owner not assigned' }}</b></small>
                        </div>
                        <form method="post" action="{{ route('tasks.delays.read',$report) }}">
                            @csrf
                            <button class="btn btn-outline btn-xs" type="submit" style="white-space: nowrap; font-weight: 600;">View issue &rarr;</button>
                        </form>
                    </article>
                    @empty
                    <div class="home-empty" style="padding: 32px 20px; text-align: center;">
                        <b style="font-size: 14px; color: #0F172A;">No open blockers</b>
                        <p style="font-size: 12.5px; color: #64748B; margin-top: 4px;">New reports will appear here when impediments are submitted.</p>
                    </div>
                    @endforelse
                    @if($blockerPreview->hasPages())
                        <div class="dashboard-task-footer" style="padding: 12px 20px; border-top: 1px solid var(--border);">
                            {{ $blockerPreview->appends(['task_attention'=>'blocked'])->fragment('task-attention')->links() }}
                        </div>
                    @endif
                    <footer class="dashboard-task-footer" style="padding: 12px 20px; background: #FAFBFC; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #64748B;">
                        <span><b>{{ $blockerCount }}</b> open blocker reports</span>
                        <a class="text-action" href="{{ route('pmo.task-monitor',['filter'=>'blocked']) }}" style="font-weight: 600;">View all blockers &rarr;</a>
                    </footer>
                @else
                <div class="attention-list-heading" aria-hidden="true"><span>Task / Project</span><span>Assignee</span><span>Due</span></div>
                @forelse($taskAttention as $task)
                @php
                    $lateDays=$task->isOverdue() && $task->ends_at ? max(1,(int)$task->ends_at->copy()->startOfDay()->diffInDays(today(),false)) : 0;
                @endphp
                <article class="dashboard-task-row">
                    <div class="dashboard-task-copy">
                        <a class="dashboard-task-title-link" href="{{ route('projects.show', [$task->project_id, 'focus_task'=>$task->id]) }}">{{ $task->title }}</a>
                        <div class="dashboard-task-meta"><span class="attention-project-name" title="{{ $task->project?->name ?? 'General' }}">{{ $task->project?->name ?? 'General' }}</span></div>
                    </div>
                    <div class="attention-assignee {{ $task->assignees->isEmpty()?'is-unassigned':'' }}" title="{{ $task->assignees->isEmpty() ? 'Unassigned' : $task->assignees->pluck('name')->join(', ') }}">{{ $task->assignees->isEmpty() ? 'Unassigned' : $task->assignees->pluck('name')->join(', ') }}</div>
                    <div class="attention-due {{ $lateDays ? 'is-overdue':'' }}"><time datetime="{{ $task->ends_at?->toDateString() }}">{{ $task->ends_at?->format('d M Y') ?? 'Not scheduled' }}</time>@if($lateDays)<a class="attention-overdue-link" href="{{ route('projects.show', [$task->project_id, 'focus_task'=>$task->id]).'#tasks' }}" aria-label="Open overdue task: {{ $task->title }}">{{ $lateDays }} {{ $lateDays===1?'day':'days' }} overdue</a>@else<small>Planned due date</small>@endif</div>
                </article>
                @empty
                <div class="home-empty" style="padding: 32px 20px; text-align: center;">
                    <b style="font-size: 14px; color: #0F172A;">{{ $taskAttentionFilter==='overdue' ? 'No overdue tasks' : 'No unfinished critical-path tasks' }}</b>
                    <p style="font-size: 12.5px; color: #64748B; margin-top: 4px;">{{ $taskAttentionFilter==='overdue' ? 'Your scheduled tasks are up to date.' : 'Tasks on the critical path will appear here when action is needed.' }}</p>
                </div>
                @endforelse
                <footer class="dashboard-task-footer" style="padding: 14px 20px; background: #FAFBFC; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #64748B;">
                    <span>Showing <b>{{ $taskAttention->count() }}</b> of <b>{{ $taskAttention->total() }}</b> {{ $taskAttentionFilter==='overdue' ? 'overdue' : 'critical-path' }} tasks</span>
                    <a class="text-action" href="{{ route('pmo.task-monitor', ['filter'=>$taskAttentionFilter]) }}" style="font-weight: 600;">View all {{ $taskAttentionFilter==='overdue' ? 'overdue' : 'critical-path' }} tasks ({{ $taskAttention->total() }}) &rarr;</a>
                </footer>
                @endif
            </section>
            @endif
            @if(!$isPmo)
            <section class="home-panel personal-attention-summary" aria-labelledby="personal-attention-title">
                <header class="home-panel-head"><div><h2 id="personal-attention-title">Needs your attention</h2><p>Action required across your work and managed projects.</p></div></header>
                @forelse($attentionItems as $item)
                <article class="personal-attention-row"><div><h3>{{ $item['title'] }}</h3><p>{{ $item['count'] }} {{ $item['type']==='follow_up'?'reports':'tasks' }} &middot; {{ $item['projects'] }} {{ $item['projects']===1?'project':'projects' }}@if($item['type']==='needs_owner') &middot; Starts within 3 working days or already due @endif</p></div><a class="btn btn-outline btn-sm" href="{{ $item['action_url'] }}">{{ $item['action_label'] }} &rarr;</a></article>
                @empty<div class="home-empty"><b>You're up to date</b><p>No overdue work, follow-ups or urgent assignments need your attention.</p></div>@endforelse
            </section>
            @else
            <section class="home-panel" aria-labelledby="my-attention">
                <header class="home-panel-head"><div><h2 id="my-attention" tabindex="-1">{{ $isPmo ? 'Action centre' : 'Your next actions' }} <span class="quiet-count">{{ $totalAttentionCount ?? $attentionItems->count() }}</span></h2>@if($isPmo)<span class="sr-only">Needs Your Attention</span>@endif<p>{{ $isPmo ? 'Project decisions and PM acceptance updates.' : 'The most urgent work comes first.' }}</p></div>@if($isPmo)<a class="text-action" href="{{ route('projects.index',['attention'=>1]) }}">Review projects &rarr;</a>@endif</header>
                <div class="home-action-list">
                    @forelse($attentionItems->take(6) as $item)
                        @php $taskAction = !$isPmo && !empty($item['task_id']); @endphp
                        <article class="home-action-item">
                            <span class="home-action-marker {{ in_array($item['type']??'', ['pending','invitation']) ? 'is-pending' : '' }}" aria-hidden="true"></span>
                            <div class="home-action-copy"><h3>{{ $isPmo ? $item['name'] : $item['title'] }}</h3><p>{{ $item['issue'] }}</p><small>{{ $isPmo ? ('Owner: '.($item['owner'] ?? 'Not assigned')) : ($item['project_name'] ?? '') }}</small></div>
                            @if($taskAction)<a class="btn btn-outline btn-sm" href="{{ auth()->user()->isAdminViewer() && $isPmo ? route('projects.show',$item['project']) : $item['action_url'] }}">Open task</a>@else<a class="btn btn-outline btn-sm" href="{{ $item['action_url'] }}">{{ auth()->user()->isAdminViewer() ? 'View project' : $item['action_label'] }}</a>@endif
                        </article>
                    @empty
                        <div class="home-empty"><span class="home-empty-mark" aria-hidden="true">&#10003;</span><b>You're up to date</b><p>No items need your attention right now.</p><a class="text-action" href="{{ $isPmo ? route('projects.index') : route('tasks.mine') }}">{{ $isPmo ? 'Browse projects' : 'View my tasks' }} &rarr;</a></div>
                    @endforelse
                </div>
                @if($attentionItems->count() > 6)<details class="dashboard-more"><summary>Show {{ $attentionItems->count() - 6 }} more updates</summary>@foreach($attentionItems->skip(6) as $item)<div class="home-action-item"><div class="home-action-copy"><h3>{{ $isPmo ? $item['name'] : $item['title'] }}</h3><p>{{ $item['issue'] }}</p></div>@if(!$isPmo && !empty($item['task_id']))<a class="btn btn-outline btn-sm" href="{{ $item['action_url'] }}">Open task</a>@else<a class="btn btn-outline btn-sm" href="{{ $item['action_url'] }}">{{ $item['action_label'] }}</a>@endif</div>@endforeach</details>@endif
            </section>
            @endif
            <section class="home-panel">
                <header class="home-panel-head"><div><h2>{{ $isPmo ? 'Recent projects' : 'My projects' }}</h2><p>{{ $isPmo ? 'A quick view of current delivery.' : 'Pick up where you left off.' }}</p></div><a class="text-action" href="{{ route('projects.index') }}">View all &rarr;</a></header>
                <div class="table-wrap"><table class="clean-table home-projects-table"><thead><tr><th>Project</th><th>{{ $isPmo ? 'Project manager' : 'Your role' }}</th><th>Progress</th><th>Due</th><th>Status</th></tr></thead><tbody>
                    @forelse($homeProjects as $entry)
                    <tr><td><a class="strong-link" href="{{ $entry['url'] }}">{{ $entry['name'] }}</a><small class="block muted">{{ $entry['code'] }}</small></td><td>{{ $isPmo ? $entry['manager'] : $entry['roles'] }}</td><td>@if($entry['progress']!==null)<div class="home-progress"><span class="home-progress-track"><i style="width:{{ (int)$entry['progress'] }}%"></i></span><span>{{ $entry['progress'] }}%</span></div>@else<span class="muted">&mdash;</span>@endif</td><td>{{ ($isPmo ? $entry['ends_on'] : $entry['deadline'])?->format('d M Y') ?? 'Not set' }}</td><td><span class="status-pill status-{{ $entry['status'] }}">{{ $entry['status_label'] }}</span></td></tr>
                    @empty<tr><td colspan="5"><div class="home-empty"><b>No projects yet</b><p>{{ $isPmo ? 'Create a project and assign its manager to get started.' : 'Your projects will appear here when you are assigned.' }}</p>@if(auth()->user()->isPmo())<a class="btn btn-primary btn-sm" href="{{ route('projects.create') }}">+ New project</a>@endif</div></td></tr>@endforelse
                </tbody></table></div>
            </section>
        </div>
        <aside class="home-side" aria-label="Upcoming work and shortcuts">
            <section class="home-panel"><header class="home-panel-head"><div><h2 id="upcoming-work">{{ $isPmo ? 'Upcoming deadlines' : 'Coming up next' }}</h2><p>Next scheduled task deadlines.</p></div><a class="text-action" href="{{ route('calendar.index') }}">Calendar &rarr;</a></header><div class="home-upcoming">
                @forelse($homeWork->take(5) as $task)
                    <button type="button" class="home-upcoming-item" data-task-location="{{ route('projects.show', [$task->project_id, 'focus_task'=>$task->id]) }}"><span class="home-date"><strong>{{ $task->ends_at->format('d') }}</strong><small>{{ $task->ends_at->format('M') }}</small></span><span class="home-upcoming-copy"><b>{{ $task->title }}</b><small>{{ $task->project?->name }}</small><span class="{{ $task->isOverdue() ? 'text-danger' : 'muted' }}">{{ $task->isOverdue() ? 'Overdue' : $task->ends_at->diffForHumans() }}</span></span><span aria-hidden="true">&rsaquo;</span></button>
                @empty<div class="home-empty home-empty-small"><b>No upcoming deadlines</b><p>Your scheduled work will appear here.</p></div>@endforelse
            </div></section>
            @if($isPmo)
            <section class="home-panel"><header class="home-panel-head"><div><h2>Task Attention</h2><p>Keep assignments and milestones moving.</p></div><a class="text-action" href="{{ route('monitoring.index') }}">Task Monitor →</a></header><div class="home-shortcuts">
                <a href="{{ route('pmo.task-monitor',['filter'=>'overdue']) }}"><span>Overdue Tasks</span><b>{{ $monitoringMetrics['overdue_tasks']??0 }}</b><span aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('pmo.task-monitor',['filter'=>'unassigned']) }}"><span>Unassigned</span><b>{{ $monitoringMetrics['unassigned_tasks']??0 }}</b><span aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('pmo.task-monitor',['filter'=>'critical']) }}"><span>Critical Path</span><b>{{ $monitoringMetrics['critical_tasks']??0 }}</b><span aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('pmo.task-monitor',['filter'=>'needs_update']) }}"><span>Needs Update</span><b>{{ $monitoringMetrics['needs_update_tasks']??0 }}</b><span aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('monitoring.index',['tab'=>'team']) }}"><span>Review Workload →</span><span aria-hidden="true">&rarr;</span></a>
            </div></section>
            @if($draftCount > 0 && auth()->user()->isPmo())
            <section class="home-panel dashboard-drafts"><header class="home-panel-head"><div><h2>Draft projects <span class="quiet-count">{{ $draftCount }}</span></h2><p>Continue when you are ready.</p></div><a class="text-action" href="{{ route('projects.index',['status'=>'draft']) }}">View all &rarr;</a></header>
                @foreach($draftProjects->take(3) as $draft)<a class="dashboard-draft-link" href="{{ route('projects.create',['draft'=>$draft->id]) }}"><span><b>{{ $draft->name }}</b><small>{{ $draft->manager?->name ?? 'Manager not selected' }}</small></span><span>Continue &rarr;</span></a>@endforeach
            </section>
            @endif
            @elseif($hasMonitoredProjects ?? false)
            <section class="home-panel"><header class="home-panel-head"><div><h2>Projects I manage</h2><p>Your team's work at a glance.</p></div></header><div class="home-shortcuts"><a href="{{ route('tasks.mine',['view'=>'managed']) }}"><span>Review team tasks</span><span aria-hidden="true">&rarr;</span></a><a href="{{ route('tasks.mine',['view'=>'managed','tab'=>'team']) }}"><span>Review team workload</span><span aria-hidden="true">&rarr;</span></a></div></section>
            @endif
        </aside>
    </div>
</div>

<script defer src="{{ asset('js/project-workspace.js') }}?v={{ filemtime(public_path('js/project-workspace.js')) }}"></script>
@if(!$isPmo)
<dialog id="approval-review-dialog" class="approval-request-dialog" aria-labelledby="approval-request-title"></dialog>
<link rel="stylesheet" href="{{ asset('css/approval-inbox.css') }}?v={{ filemtime(public_path('css/approval-inbox.css')) }}">
<script defer src="{{ asset('js/approval-inbox.js') }}?v={{ filemtime(public_path('js/approval-inbox.js')) }}"></script>
@endif
@endsection
