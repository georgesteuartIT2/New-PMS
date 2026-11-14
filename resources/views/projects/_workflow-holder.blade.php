@php
 $holderLabel=null;$holderName=null;
 switch($project->approval_stage){
 case 'owner':$holderLabel='With Owner';break;
 case 'pm':$holderLabel='With PM';break;
 case 'planning':$holderLabel='With PM';break;
 case 'owner_declined':$holderLabel='PMO to reassign';break;
 case 'pm_declined':$holderLabel=$project->owner?'Owner / PMO to reassign':'PMO to reassign';break;
 }
 if(!$holderLabel && $project->status==='draft')$holderLabel='With PMO';
@endphp
@if($holderLabel)<div class="project-workflow-holder"><span>{{ $holderLabel }}</span>@if($holderName)<small>{{ $holderName }}</small>@endif</div>@endif