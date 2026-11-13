@php
 $holderLabel=null;$holderName=null;
 switch($project->approval_stage){
 case 'owner':$holderLabel='With Owner';$holderName=$project->owner?->name;break;
 case 'pm':$holderLabel='With PM';$holderName=$project->manager?->name;break;
 case 'planning':$holderLabel='PM preparing plan';$holderName=$project->manager?->name;break;
 case 'owner_declined':$holderLabel='PMO to reassign';break;
 case 'pm_declined':$holderLabel=$project->owner?'Owner / PMO to reassign':'PMO to reassign';break;
 }
 if(!$holderLabel && $project->status==='draft')$holderLabel='With PMO';
@endphp
@if($holderLabel)<div class="project-workflow-holder"><span>{{ $holderLabel }}</span>@if($holderName)<small>{{ $holderName }}</small>@endif</div>@endif