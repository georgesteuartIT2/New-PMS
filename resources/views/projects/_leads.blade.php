<div class="project-lead-list">
@if($project->owner)
<div class="project-lead-person"><span class="project-lead-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($project->owner->name,0,1)) }}</span><span><strong>{{ $project->owner->name }}</strong><small>Owner{{ $project->pm_user_id===$project->owner_user_id ? ' & PM' : '' }}</small></span></div>
@endif
@if($project->manager && (!$project->owner || $project->pm_user_id!==$project->owner_user_id))
<div class="project-lead-person"><span class="project-lead-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($project->manager->name,0,1)) }}</span><span><strong>{{ $project->manager->name }}</strong><small>Project Manager</small></span></div>
@endif
@if(!$project->owner && !$project->manager)<span class="pm-unassigned">Not assigned</span>@endif
</div>