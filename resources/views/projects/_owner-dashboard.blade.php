@if(auth()->user()->system_role==='user')
@php
 $ownerRequests=\App\Models\Project::where('owner_user_id',auth()->id())->whereIn('approval_stage',['owner','pm_declined'])->with('subsidiary')->oldest()->paginate(5,['*'],'owner_requests_page');
 $plansInPreparation=\App\Models\Project::where('pm_user_id',auth()->id())->where('approval_stage','planning')->latest()->take(5)->get();
@endphp
@if($ownerRequests->total() || $plansInPreparation->isNotEmpty())<link rel="stylesheet" href="{{ asset('css/project-workflow.css') }}?v={{ filemtime(public_path('css/project-workflow.css')) }}"><section class="flow-card flow-dashboard flow-decision-panel" aria-labelledby="project-decisions-title"><header class="flow-decision-heading"><div><span class="flow-decision-eyebrow">{{ $ownerRequests->total() ? 'Action required' : 'Ready for setup' }}</span><h2 id="project-decisions-title">Project decisions &amp; setup <span class="flow-decision-count">{{ $ownerRequests->total()+$plansInPreparation->count() }}</span></h2><p>Review requests and keep your projects moving.</p></div><span class="flow-decision-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H5v16h14V5h-4M9 3h6v4H9zM8 14l3 3 5-6"/></svg></span></header>
@foreach($ownerRequests as $item)<article><div><strong>{{ $item->name }}</strong><small>{{ $item->code }} &middot; {{ $item->approval_stage==='owner'?'Your approval is needed':'PM rejected: '.$item->approval_reason }}</small></div><a class="btn btn-primary" href="{{ route('projects.show',$item) }}">{{ $item->approval_stage==='owner'?'Review & choose PM':'Reassign PM' }} &rarr;</a></article>@endforeach
{{ $ownerRequests->links() }}
@foreach($plansInPreparation as $item)<article><div><strong>{{ $item->name }}</strong><small>Accepted &middot; Plan not published yet</small></div><a class="btn btn-outline" href="{{ $item->plan_initialized?route('projects.show',$item).'#tasks':route('projects.plan.setup',$item) }}">Continue plan &rarr;</a></article>@endforeach</section>@endif
@endif

@if(auth()->user()->isPmo())
@php
 $declinedWorkflow=\App\Models\Project::whereIn('approval_stage',['owner_declined','pm_declined'])->with('owner','manager')->latest()->paginate(5,['*'],'workflow_decisions_page');
@endphp
@if($declinedWorkflow->total())<link rel="stylesheet" href="{{ asset('css/project-workflow.css') }}?v={{ filemtime(public_path('css/project-workflow.css')) }}"><section class="flow-card flow-dashboard"><h2>Assignments needing reassignment <span class="pill">{{ $declinedWorkflow->total() }}</span></h2><p>A project lead rejected these requests. Review the reason and choose a replacement.</p>@foreach($declinedWorkflow as $item)<article><div><strong>{{ $item->name }}</strong><small>{{ $item->approval_stage==='owner_declined'?'Project Owner':'Project Manager' }} rejected &middot; {{ $item->approval_reason }}</small></div><a class="btn btn-primary" href="{{ route('projects.show',$item) }}">Review &amp; reassign &rarr;</a></article>@endforeach{{ $declinedWorkflow->links() }}</section>@endif
@endif
