@if($project->approval_stage && $project->approval_stage!=='ready' && !($project->approval_stage==='pm' && (int)$project->pm_user_id===auth()->id() && (int)$project->owner_user_id!==auth()->id()))
<link rel="stylesheet" href="{{ asset('css/project-workflow.css') }}">
@php
 $flowUsers=\App\Models\User::where('system_role','user')->where('account_status','active')->orderBy('name')->get();
 $isOwner=(int)$project->owner_user_id===auth()->id();
 $isLead=$isOwner || (int)$project->pm_user_id===auth()->id();
 $stageLabels=['owner'=>'Awaiting Owner approval','pm'=>'Awaiting PM acceptance','owner_declined'=>'Owner rejected the request','pm_declined'=>'PM rejected the request','planning'=>'Preparing the project plan','ready'=>'Plan published'];
@endphp
<section class="flow-banner" aria-label="Project approval and setup">
<div><small>{{ $project->owner?->name ? 'Project Owner: '.$project->owner->name : 'Direct PM assignment' }}</small><h2>{{ $stageLabels[$project->approval_stage] ?? 'Project setup' }}</h2>
@if($project->approval_reason)<p><b>Rejection reason:</b> {{ $project->approval_reason }}</p>@endif
@if($project->approval_stage==='planning')<p>Add or import tasks, assign people from Team, then save and notify them. No team invitation emails are sent until you save the plan.</p>@endif</div>
@if($project->approval_stage==='owner' && $isOwner)
<form method="post" action="{{ route('projects.owner.respond',$project) }}" class="flow-inline">@csrf<input type="hidden" name="decision" value="accept"><label>Project Manager<select name="pm_user_id" required data-directory-picker data-directory-project="{{ $project->id }}"><option value="">Search and select a manager</option>@foreach($flowUsers as $person)<option value="{{ $person->id }}">{{ $person->name }} ({{ $person->email }})</option>@endforeach</select></label><button class="btn btn-primary">Approve &amp; invite PM</button><button type="button" class="btn btn-outline" data-show-dialog="owner-reject-dialog">Reject</button></form>
<dialog class="modal" id="owner-reject-dialog" style="max-width:480px"><form class="flow-form" method="post" action="{{ route('projects.owner.respond',$project) }}">@csrf<h2>Reject project request</h2><input type="hidden" name="decision" value="decline"><label>Reason <span>*</span><textarea name="decline_reason" required minlength="5" maxlength="1000" rows="3" placeholder="Tell PMO why this request needs to change."></textarea></label><footer class="flow-footer"><button type="button" class="btn btn-outline" data-close-dialog>Cancel</button><button class="btn btn-primary">Send rejection</button></footer></form></dialog>
@endif
@if($project->approval_stage==='planning' && ($isLead || auth()->user()->isPmo()))
@if(!$project->plan_initialized)<a href="{{ route('projects.plan.setup',$project) }}" class="btn btn-primary">Choose plan &rarr;</a>@else<form method="post" action="{{ route('projects.plan.publish',$project) }}" onsubmit="return AppDialog.confirmAction(event,this,'Save this task plan and send project emails to your team?');">@csrf<button type="submit" class="btn btn-primary">Save &amp; notify team</button></form>@endif
@endif
@if(in_array($project->approval_stage,['owner_declined','pm_declined']) && (auth()->user()->isPmo() || ($isOwner && $project->approval_stage==='pm_declined')))
<details class="flow-reassign" @if(request()->boolean('reassign')) open @endif><summary>Change assignment</summary><form class="flow-inline" method="post" action="{{ route('projects.workflow.reassign',$project) }}">@csrf
@if(auth()->user()->isPmo())<label>Send request to<select name="assignment_type"><option value="pm">Project Manager</option><option value="owner">Project Owner</option></select></label>@else<input type="hidden" name="assignment_type" value="pm">@endif
<label>Person<select name="assignee_id" required data-directory-picker data-directory-project="{{ $project->id }}"><option value="">Choose a person</option>@foreach($flowUsers as $person)<option value="{{ $person->id }}">{{ $person->name }} ({{ $person->email }})</option>@endforeach</select></label><button class="btn btn-primary">Reassign &amp; send request</button></form></details>
@endif
</section>
@endif
