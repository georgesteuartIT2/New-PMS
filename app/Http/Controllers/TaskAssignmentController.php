<?php
namespace App\Http\Controllers;
use App\Models\{Project,Task,User,ProjectRole,ProjectMembership};
use App\Services\{Access,Audit,InboxService};
use App\Notifications\TaskReassignedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class TaskAssignmentController {
 public function store(Request $request,Project $project,Access $access){
  abort_unless($access->project($request->user(),$project,'assign_tasks'),403);
  abort_if($project->status==='archived',403);
  $d=$request->validate(['task_ids'=>'required|array|min:1|max:500','task_ids.*'=>'required|integer|distinct','user_id'=>'required|integer','add_to_project'=>'sometimes|boolean']);
  $result=DB::transaction(function()use($request,$project,$access,$d){
   $locked=Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
   abort_if($locked->status==='archived',403);
   $tasks=$locked->tasks()->whereIn('id',$d['task_ids'])->lockForUpdate()->get();
   abort_unless($tasks->count()===count($d['task_ids']),422,'Choose tasks from this project only.');
   $person=User::whereKey($d['user_id'])->where('account_status','active')->where('system_role','user')->firstOrFail();
   $member=(int)$locked->pm_user_id===$person->id||$locked->memberships()->where('user_id',$person->id)->exists();
   if(!$member){
    abort_unless($access->project($request->user(),$locked,'manage_team'),403,'You can assign project members only.');
    abort_unless($d['add_to_project']??false,422,'Choose Add to project & assign to grant project access.');
    $role=ProjectRole::where('slug','team')->firstOrFail();
    ProjectMembership::firstOrCreate(['project_id'=>$locked->id,'user_id'=>$person->id,'project_role_id'=>$role->id]);
    Audit::record('project.member_added',$locked,['user_id'=>$person->id,'source'=>'task_assignment']);
   }
   foreach($tasks as $task){
    $new=$task->assignees()->syncWithoutDetaching([$person->id]);
    if(count($new['attached'])){Audit::record('task.assigned',$task,['user_id'=>$person->id]);
     if(in_array($locked->status,['active','on_hold'])&&$locked->approval_stage!=='planning')DB::afterCommit(fn()=>$person->notify(new TaskReassignedNotification($task,$request->user())));
    }
   }
   return $tasks->map(fn($task)=>['id'=>$task->id,'assignees'=>$task->assignees()->orderBy('name')->get(['users.id','users.name'])]);
  });
  return response()->json(['message'=>'Tasks assigned.','tasks'=>$result]);
 }
}
