<?php
namespace App\Services;
use App\Models\{User,Task,TaskDelay,ProjectInvitation};
class InboxService {
 public function put(User $user,string $key,array $data):void {
  $hash=md5($user->id.':'.$key);$id=substr($hash,0,8).'-'.substr($hash,8,4).'-'.substr($hash,12,4).'-'.substr($hash,16,4).'-'.substr($hash,20);
  $user->notifications()->firstOrCreate(['id'=>$id],['type'=>'projectflow.update','data'=>$data]);
 }
 public function blocker(TaskDelay $delay):void {
  $task=$delay->task;$data=['title'=>'New blocker reported','message'=>$delay->reason_label,'project_name'=>$task->project->name,'task_title'=>$task->title,'task_id'=>$task->id,'action_label'=>'View issue','url'=>route('projects.show',[$task->project_id,'focus_issue'=>$delay->id],false).'#risks'];
  foreach(User::where('system_role','pmo')->where('account_status','active')->get() as $user)$this->put($user,'blocker:'.$delay->id,$data);
  foreach(User::whereIn('id',array_filter([$task->project->pm_user_id,$delay->action_owner_id]))->where('account_status','active')->get() as $user)$this->put($user,'blocker:'.$delay->id,$data);
 }
 public function assigned(Task $task,User $user):void {
  if(!in_array($task->project->status,['active','on_hold']) || $task->project->approval_stage==='planning')return;
  if($user->notifications()->where('data->task_id',$task->id)->where('data->action_label','Open task')->exists())return;
  $this->put($user,'task:'.$task->id,['title'=>'Task assigned to you','message'=>'You are assigned to '.$task->title.'.','project_name'=>$task->project->name,'task_title'=>$task->title,'task_id'=>$task->id,'action_label'=>'Open task','url'=>route('projects.show',[$task->project_id,'focus_task'=>$task->id],false).'#tasks']);
 }
 public function sync(User $user):void {
  if($user->isPmo()){
   foreach(TaskDelay::with('task.project')->where('status','open')->whereHas('task.project',fn($q)=>$q->whereIn('status',['active','on_hold']))->get() as $delay)$this->blocker($delay);
  }else{
   foreach(ProjectInvitation::with('project')->where('user_id',$user->id)->where('status','pending')->whereHas('project',fn($q)=>$q->where('status','pending')->where('pm_user_id',$user->id))->get() as $invite){
    if(!$user->notifications()->where('data->type','project_approval')->where('data->project_id',$invite->project_id)->exists())$this->put($user,'invitation:'.$invite->id,['type'=>'project_approval','project_id'=>$invite->project_id,'title'=>'Project approval requested','message'=>'Review your Project Manager assignment for '.$invite->project->name.'.','project_name'=>$invite->project->name,'action_label'=>'Review project','url'=>route('projects.show',$invite->project_id,false)]);
   }
   foreach($user->assignedTasks()->with('project')->whereDoesntHave('children')->where('tasks.status','!=','completed')->whereHas('project',fn($q)=>$q->whereIn('status',['active','on_hold']))->get() as $task)$this->assigned($task,$user);
  }
 }
}
