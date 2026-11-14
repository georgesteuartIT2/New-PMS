<div class="project-lead-list">
@if($project->owner)<div class="project-lead-line"><small>{{ $project->pm_user_id===$project->owner_user_id ? 'Owner / PM' : 'Owner' }}</small><strong>{{ $project->owner->name }}</strong></div>@endif
@if($project->manager && (!$project->owner || $project->pm_user_id!==$project->owner_user_id))<div class="project-lead-line"><small>PM</small><strong>{{ $project->manager->name }}</strong></div>@endif
@if(!$project->owner && !$project->manager)<span class="pm-unassigned">Not assigned</span>@endif
</div>