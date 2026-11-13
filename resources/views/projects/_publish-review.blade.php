@php
 $publishTasks=$project->tasks()->with('assignees')->get();
 $parentIds=$publishTasks->pluck('parent_task_id')->filter();
 $executable=$publishTasks->reject(fn($task)=>$parentIds->contains($task->id));
 $unassigned=$executable->filter(fn($task)=>$task->assignees->isEmpty());
 $assignedCount=max(0,$executable->count()-$unassigned->count());
 $assignedPct=$executable->count()?(int)round($assignedCount/$executable->count()*100):0;
 $recipientIds=$publishTasks->flatMap(fn($task)=>$task->assignees->pluck('id'))->merge($project->memberships()->pluck('user_id'))->unique()->reject(fn($id)=>(int)$id===(int)$project->pm_user_id);
 $publishRecipients=\App\Models\User::whereIn('id',$recipientIds)->where('account_status','active')->where('system_role','user')->orderBy('name')->get();
 $recipientRoles=$project->memberships()->with('role')->get()->groupBy('user_id')->map(fn($rows)=>$rows->map(fn($m)=>$m->role?->name)->filter()->unique()->join(', '));
 $workingDays=($project->starts_on&&$project->ends_on)?max(1,(int)$project->starts_on->diffInWeekdays($project->ends_on)+1):null;
 $canPublish=$executable->isNotEmpty();
 $autoOpenReview=$canPublish&&session('open_publish_review');
@endphp
<dialog class="modal flow-publish-review" id="publish-plan-review" aria-labelledby="publish-review-title" data-can-publish="{{ $canPublish?'1':'0' }}">
<header class="pr-hero">
 <span class="pr-hero-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
 <div class="pr-hero-copy"><span class="pr-eyebrow">Final review</span><h2 id="publish-review-title">Ready to publish your plan?</h2><p>{{ $project->name }}@if($project->code) <span aria-hidden="true">&middot;</span> {{ $project->code }}@endif</p></div>
 <button type="button" class="pr-close" data-close-dialog aria-label="Close publish review"><span class="workspace-sr-only">Review &amp; publish</span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
</header>
<div class="pr-body">
 <div class="publish-review-metrics">
  <div class="pr-stat"><span class="pr-stat-icon is-tasks" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6h12M9 12h12M9 18h12M3 6l1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"/></svg></span><strong>{{ $executable->count() }}</strong><span>Executable tasks</span></div>
  <div class="pr-stat"><span class="pr-stat-icon {{ $unassigned->isEmpty()?'is-ok':'is-warn' }}" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg></span><strong>{{ $unassigned->count() }}</strong><span>Unassigned tasks</span></div>
  <div class="pr-stat"><span class="pr-stat-icon is-people" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg></span><strong>{{ $publishRecipients->count() }}</strong><span>People to notify</span></div>
 </div>
 @if($executable->isNotEmpty())
 <div class="pr-coverage" role="group" aria-label="Task assignment coverage"><div class="pr-coverage-head"><span>Assignment coverage</span><b>{{ $assignedCount }} of {{ $executable->count() }} assigned</b></div><div class="pr-coverage-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $assignedPct }}"><span style="width:{{ $assignedPct }}%"></span></div></div>
 @endif
 <dl class="pr-summary">
  <div><dt>Plan</dt><dd>{{ $project->template?->name ?? 'Blank plan' }}</dd></div>
  <div><dt>Schedule</dt><dd>{{ $project->starts_on?->format('d M Y') }} &ndash; {{ $project->ends_on?->format('d M Y') }}@if($workingDays) <small>{{ $workingDays }} working days</small>@endif</dd></div>
  <div><dt>Budget</dt><dd>@if($project->planned_budget)LKR {{ number_format($project->planned_budget,2) }}@else<span class="pr-muted">Not set</span>@endif</dd></div>
 </dl>
 @if(!$canPublish)
  <div class="pr-callout is-warn"><span class="pr-callout-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span><div><b>Your plan has no tasks yet</b><p>Add or import at least one task before publishing.</p></div></div>
 @elseif($unassigned->isNotEmpty())
  <div class="publish-review-warning pr-callout is-warn"><span class="pr-callout-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span><div><b>{{ $unassigned->count() }} tasks still need an assignee</b><p>You can publish now and assign them later, or keep editing to finish assigning.</p><details><summary>Show unassigned tasks</summary><ul>@foreach($unassigned as $task)<li><code>{{ $task->code }}</code> {{ $task->title }}</li>@endforeach</ul></details></div></div>
 @else
  <div class="pr-callout is-ok"><span class="pr-callout-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span><div><b>Every task has an assignee</b><p>Your plan is complete and ready to go live.</p></div></div>
 @endif
 <details class="publish-review-people"><summary><span>People receiving notifications</span><b>{{ $publishRecipients->count() }}</b></summary><ul class="pr-people">@forelse($publishRecipients as $person)<li><span class="pr-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($person->name,0,1)) }}</span><span class="pr-person"><strong>{{ $person->name }}</strong><small>{{ $person->email }}</small></span>@if(!empty($recipientRoles[$person->id]))<em>{{ $recipientRoles[$person->id] }}</em>@endif</li>@empty<li class="pr-people-empty">No team recipients yet.</li>@endforelse</ul></details>
 <p class="pr-note"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg><span>Publishing makes the plan available to your team and captures the schedule baseline. In-app notifications appear immediately; emails are queued for sending.</span></p>
</div>
<form method="post" action="{{ route('projects.plan.publish',$project) }}">@csrf<footer class="pr-footer"><button type="button" class="btn btn-outline" data-close-dialog>Keep editing</button><button type="submit" class="btn btn-primary pr-publish" @disabled(!$canPublish)><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Publish &amp; notify team</button></footer></form>
</dialog>
@if($autoOpenReview)
<script>
(function(){
    function openPublishModal(){
        var d=document.getElementById('publish-plan-review');
        if(d && !d.open && typeof d.showModal==='function') {
            d.showModal();
        }
    }
    if(document.readyState==='loading'){
        document.addEventListener('DOMContentLoaded',openPublishModal);
    } else {
        setTimeout(openPublishModal, 50);
    }
})();
</script>
@endif