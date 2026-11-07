<?php
namespace App\Services;
use App\Models\{Project,ProjectRole,ProjectMembership,ProjectInvitation,ProjectTemplate,Task,User,Subsidiary};
use App\Notifications\ProjectAssignmentNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
class ProjectService {
 public function create(array $data,User $actor):Project {
  return DB::transaction(function()use($data,$actor){
   $template=!empty($data['project_template_id'])?ProjectTemplate::with('tasks')->where('status','active')->findOrFail($data['project_template_id']):null;
   $pm=!empty($data['pm_user_id'])?User::find($data['pm_user_id']):null;
   $subsidiary=!empty($data['subsidiary_id'])?Subsidiary::whereKey($data['subsidiary_id'])->lockForUpdate()->firstOrFail():null;

   $code = $subsidiary
     ? $subsidiary->nextProjectCode()
     : 'GS-PRJ-' . str_pad(Project::whereNull('subsidiary_id')->count() + 1, 3, '0', STR_PAD_LEFT);

   $isDraft = ($data['status_override'] ?? null) === 'draft' || empty($pm);

   $p=Project::create([
    'code'=>$code,
    'subsidiary_id'=>$subsidiary?->id,
    'name'=>$data['name'],
    'description'=>$data['description']??null,
    'starts_on'=>$data['starts_on'],
    'ends_on'=>$data['ends_on'],
    'project_template_id'=>$template?->id,
    'pm_user_id'=>$pm?->id,
    'status'=>$isDraft ? 'draft' : 'pending',
    'created_by'=>$actor->id,
   ]);
   if($template){
    $byCode=[];
    $scheduleService=app(TemplateScheduleService::class);
    foreach($template->tasks->sortBy('sort_order') as $entry){
     [$start,$end]=$scheduleService->mapTaskDates($template,$entry,Carbon::parse($p->starts_on));
     $task=Task::create([
      'project_id'=>$p->id,'code'=>$entry->code,'title'=>$entry->title,
      'description'=>$entry->description,
      'starts_at'=>$start,'ends_at'=>$end,'sort_order'=>$entry->sort_order,
      'priority'=>$entry->priority,'created_by'=>$actor->id,
     ]);
     $byCode[$entry->code]=[$task,$entry->parent_code,$entry->dependency_code];
    }
    foreach($byCode as [$task,$parent,$dependency]){
     if($parent && isset($byCode[$parent]))$task->update(['parent_task_id'=>$byCode[$parent][0]->id]);
     if($dependency && isset($byCode[$dependency]))$task->dependencies()->syncWithoutDetaching([$byCode[$dependency][0]->id]);
    }
   }
   foreach($data['members']??[] as $member){
    $uid=$member['user_id']??null;$rid=$member['project_role_id']??null;
    if(!$uid||!$rid)continue;
    $chosenUser=User::whereKey($uid)->where('account_status','active')->first();
    $role=ProjectRole::whereKey($rid)->where('slug','!=','pm')->first();
    if(!$chosenUser || !$role) continue;
    if($pm && (int)$uid===$pm->id)continue;
    ProjectMembership::updateOrCreate(['project_id'=>$p->id,'user_id'=>$chosenUser->id],['project_role_id'=>$role->id]);
   }
   if($pm){
    $role=ProjectRole::where('slug','pm')->firstOrFail();
    ProjectMembership::updateOrCreate(['project_id'=>$p->id,'user_id'=>$pm->id],['project_role_id'=>$role->id]);
    if(!$isDraft){
     ProjectInvitation::where('project_id',$p->id)->where('status','pending')->update(['status'=>'superseded']);
     ProjectInvitation::create(['project_id'=>$p->id,'user_id'=>$pm->id,'status'=>'pending']);
     DB::afterCommit(fn()=>$pm->notify(new ProjectAssignmentNotification($p)));
    }
   }
   Audit::record('project.created',$p);
   return $p;
  });
 }
 public function assignManager(Project $project,User $user):void {
  abort_if($user->isPmo()||!$user->isActive(),422,'Only an active project user can be a Project Manager.');
  $role=ProjectRole::where('slug','pm')->firstOrFail();
  if($project->pm_user_id && $project->pm_user_id!==$user->id){
   ProjectMembership::where('project_id',$project->id)->where('user_id',$project->pm_user_id)->delete();
  }
  $project->update(['pm_user_id'=>$user->id,'status'=>'pending']);
  ProjectMembership::updateOrCreate(['project_id'=>$project->id,'user_id'=>$user->id],['project_role_id'=>$role->id]);
  ProjectInvitation::where('project_id',$project->id)->where('status','pending')->update(['status'=>'superseded']);
  ProjectInvitation::create(['project_id'=>$project->id,'user_id'=>$user->id,'status'=>'pending']);
  DB::afterCommit(fn()=>$user->notify(new ProjectAssignmentNotification($project)));
 }
 /** Approved schedule snapshot: existing historical projects require an explicit action. */
 public function captureBaseline(Project $project): void {
  DB::transaction(function() use($project) {
   $project->update([
    'baseline_starts_on'=>$project->starts_on,
    'baseline_ends_on'=>$project->ends_on,
    'baseline_captured_at'=>now(),
   ]);
   foreach($project->tasks as $task) {
    $task->update(['baseline_starts_at'=>$task->starts_at,'baseline_ends_at'=>$task->ends_at]);
   }
  });
 }
 public function recalculate(Project $project):void {
  $tasks=$project->tasks()->get();$children=$tasks->groupBy(fn($t)=>(string)($t->parent_task_id??0));
  $leaves=[];$visited=[];
  $compute=function($task)use(&$compute,&$visited,&$leaves,$children){
   if(isset($visited[$task->id]))return (int)$task->progress;
   $visited[$task->id]=true;
   $kids=$children->get((string)$task->id,collect());
   if(!$kids->count()){$leaves[]=(int)$task->progress;return (int)$task->progress;}
   $avg=(int)round($kids->map(fn($k)=>$compute($k))->avg());
   $task->update(['progress'=>$avg,'status'=>$avg===100?'completed':($avg>0?'in_progress':'todo')]);
   return $avg;
  };
  foreach($tasks as $task)if(!$task->parent_task_id)$compute($task);
  foreach($tasks as $task)if(!isset($visited[$task->id]))$compute($task);
  $progress=count($leaves)?(int)round(array_sum($leaves)/count($leaves)):0;
  $project->update(['progress'=>$progress]);
 }
}
