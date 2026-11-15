@if(auth()->user()->system_role==='user')
@php
 $ownerRequests=\App\Models\Project::where('owner_user_id',auth()->id())->where('approval_stage','owner')->with('subsidiary')->oldest()->paginate(5,['*'],'owner_requests_page');
 $pmDeclinedRequests=\App\Models\Project::where('owner_user_id',auth()->id())->where('approval_stage','pm_declined')->with('manager')->oldest()->paginate(5,['*'],'pm_reassignments_page');
 $plansInPreparation=\App\Models\Project::where('pm_user_id',auth()->id())->where('approval_stage','planning')->latest()->take(5)->get();
 $workflowUsers=$pmDeclinedRequests->total()?\App\Models\User::where('system_role','user')->where('account_status','active')->orderBy('name')->get():collect();
@endphp
@if($pmDeclinedRequests->total())
<link rel="stylesheet" href="{{ asset('css/project-workflow.css') }}?v={{ filemtime(public_path('css/project-workflow.css')) }}">
<section class="flow-card flow-dashboard flow-decision-panel flow-reassignment-panel" aria-labelledby="owner-reassignment-title">
    <header class="flow-decision-heading"><div><span class="flow-decision-eyebrow">Action needed</span><h2 id="owner-reassignment-title">Assignments needing reassignment <span class="flow-decision-count">{{ $pmDeclinedRequests->total() }}</span></h2><p>Choose a replacement and resend the request without leaving this page.</p></div><span class="flow-decision-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2 21h20L12 3Z"/><path d="M12 9v4m0 4h.01"/></svg></span></header>
    @foreach($pmDeclinedRequests as $item)
    <article id="owner-reassignment-project-{{ $item->id }}" class="flow-reassignment-item" tabindex="-1">
        <a class="flow-reassignment-open-link" href="{{ route('projects.show',$item) }}" aria-label="Open project: {{ $item->name }}">
            Open project
            <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12 12 4M5 4h7v7"/></svg>
        </a>
        <div class="flow-reassignment-details">
            <div class="flow-reassignment-project-heading"><strong><a class="flow-reassignment-project" href="{{ route('projects.show',$item) }}">{{ $item->name }}</a></strong><span class="flow-reassignment-stage">PM rejected</span></div>
            @if($item->manager)
            <div class="flow-reassignment-reviewer" role="group" aria-label="Rejected by {{ $item->manager->name }}">
                <span class="flow-reassignment-reviewer-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($item->manager->name,0,1)) }}</span>
                <span class="flow-reassignment-reviewer-label">Rejected by</span>
                <strong class="flow-reassignment-reviewer-name">{{ $item->manager->name }}</strong>
            </div>
            @endif
            <div class="flow-reassignment-reason-box"><span class="flow-reassignment-reason-label">Reason</span><p>{{ $item->approval_reason }}</p></div>
        </div>
        <form class="flow-inline flow-reassignment-quick-form" method="post" action="{{ route('projects.workflow.reassign',$item) }}">
            @csrf
            <label><span>Send request to</span>
                <select name="assignment_type" required>
                    <option value="pm" selected>Project Manager</option>
                </select>
            </label>
            <label><span>Person</span>
                <select name="assignee_id" required data-directory-picker data-directory-project="{{ $item->id }}" aria-describedby="owner-reassignment-person-{{ $item->id }}">
                    <option value="">Search name or email</option>
                    @foreach($workflowUsers as $person)
                    <option value="{{ $person->id }}">{{ $person->name }} ({{ $person->email }})</option>
                    @endforeach
                </select>
                <small id="owner-reassignment-person-{{ $item->id }}">Search users or type 2+ characters to add from Azure.</small>
            </label>
            <button type="submit" class="btn btn-primary">Reassign &amp; send request</button>
        </form>
    </article>
    @endforeach
    {{ $pmDeclinedRequests->links() }}
</section>
@endif
@if($ownerRequests->total() || $plansInPreparation->isNotEmpty())
<link rel="stylesheet" href="{{ asset('css/project-workflow.css') }}?v={{ filemtime(public_path('css/project-workflow.css')) }}">
<section class="flow-card flow-dashboard flow-decision-panel" aria-labelledby="project-decisions-title"><header class="flow-decision-heading"><div><span class="flow-decision-eyebrow">{{ $ownerRequests->total() ? 'Action required' : 'Ready for setup' }}</span><h2 id="project-decisions-title">Project decisions &amp; setup <span class="flow-decision-count">{{ $ownerRequests->total()+$plansInPreparation->count() }}</span></h2><p>Review requests and keep your projects moving.</p></div><span class="flow-decision-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H5v16h14V5h-4M9 3h6v4H9zM8 14l3 3 5-6"/></svg></span></header>
@foreach($ownerRequests as $item)<article><div><strong>{{ $item->name }}</strong><small>{{ $item->code }} &middot; Your approval is needed</small></div><a class="btn btn-primary" href="{{ route('projects.show', $item) }}">Review &amp; choose PM &rarr;</a></article>@endforeach
{{ $ownerRequests->links() }}
@foreach($plansInPreparation as $item)<article><div><strong>{{ $item->name }}</strong><small>Accepted &middot; Plan not published yet</small></div><a class="btn btn-outline" href="{{ route('projects.plan.setup', $item) }}">Continue plan &rarr;</a></article>@endforeach</section>@endif
@endif

@if(auth()->user()->isPmo())
@php
 $declinedWorkflow=\App\Models\Project::whereIn('approval_stage',['owner_declined','pm_declined'])->with('owner','manager')->orderByDesc('created_at')->orderByDesc('id')->paginate(5,['*'],'workflow_decisions_page');
@endphp
@if($declinedWorkflow->total())
@php
 $workflowUsers=\App\Models\User::where('system_role','user')->where('account_status','active')->orderBy('name')->get();
@endphp
<link rel="stylesheet" href="{{ asset('css/project-workflow.css') }}?v={{ filemtime(public_path('css/project-workflow.css')) }}">
<section class="flow-card flow-dashboard flow-decision-panel flow-reassignment-panel" aria-labelledby="workflow-reassignment-title">
    <header class="flow-decision-heading"><div><span class="flow-decision-eyebrow">Action needed</span><h2 id="workflow-reassignment-title">Assignments needing reassignment <span class="flow-decision-count">{{ $declinedWorkflow->total() }}</span></h2><p>Choose a replacement and resend the request without leaving this page.</p></div><span class="flow-decision-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2 21h20L12 3Z"/><path d="M12 9v4m0 4h.01"/></svg></span></header>
    @foreach($declinedWorkflow as $item)
    <article id="workflow-reassignment-project-{{ $item->id }}" class="flow-reassignment-item" tabindex="-1">
        <a class="flow-reassignment-open-link" href="{{ route('projects.show',$item) }}" aria-label="Open project: {{ $item->name }}">
            Open project
            <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12 12 4M5 4h7v7"/></svg>
        </a>
        <div class="flow-reassignment-details">
            <div class="flow-reassignment-project-heading"><strong><a class="flow-reassignment-project" href="{{ route('projects.show',$item) }}">{{ $item->name }}</a></strong><span class="flow-reassignment-stage">{{ $item->approval_stage==='owner_declined'?'Owner':'PM' }} rejected</span></div>
            @if($item->approval_stage==='pm_declined' && $item->manager)
            <div class="flow-reassignment-reviewer" role="group" aria-label="Rejected by {{ $item->manager->name }}">
                <span class="flow-reassignment-reviewer-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($item->manager->name,0,1)) }}</span>
                <span class="flow-reassignment-reviewer-label">Rejected by</span>
                <strong class="flow-reassignment-reviewer-name">{{ $item->manager->name }}</strong>
            </div>
            @endif
            <div class="flow-reassignment-reason-box"><span class="flow-reassignment-reason-label">Reason</span><p>{{ $item->approval_reason }}</p></div>
        </div>
        <form class="flow-inline flow-reassignment-quick-form" method="post" action="{{ route('projects.workflow.reassign',$item) }}">
            @csrf
            <label><span>Send request to</span>
                <select name="assignment_type" required>
                    <option value="pm" @selected($item->approval_stage==='pm_declined')>Project Manager</option>
                    <option value="owner" @selected($item->approval_stage==='owner_declined')>Project Owner</option>
                </select>
            </label>
            <label><span>Person</span>
                <select name="assignee_id" required data-directory-picker data-directory-project="{{ $item->id }}" aria-describedby="workflow-reassignment-person-{{ $item->id }}">
                    <option value="">Search name or email</option>
                    @foreach($workflowUsers as $person)
                    <option value="{{ $person->id }}">{{ $person->name }} ({{ $person->email }})</option>
                    @endforeach
                </select>
                <small id="workflow-reassignment-person-{{ $item->id }}">Search users or type 2+ characters to add from Azure.</small>
            </label>
            <button type="submit" class="btn btn-primary">Reassign &amp; send request</button>
        </form>
    </article>
    @endforeach
    {{ $declinedWorkflow->links() }}
</section>
@endif
@endif
