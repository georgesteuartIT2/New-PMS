<?php
namespace App\Services;
use App\Models\{Task,User};
class PersonalAttentionService {
 public function query(User $user,string $category){
  $q=Task::whereDoesntHave('children')->where('status','!=','completed')->where('progress','<',100)->whereHas('project',fn($p)=>$p->whereIn('status',['active','on_hold']));
  if($category==='my_overdue')return $q->whereHas('assignees',fn($a)=>$a->where('users.id',$user->id))->where('ends_at','<',now());
  if($category==='follow_up')return $q->whereHas('openDelay',fn($d)=>$d->where('action_owner_id',$user->id))->where(function($q)use($user){$q->whereIn('project_id',app(UserTaskMonitoringService::class)->getMonitoredProjects($user)->pluck('id'))->orWhereHas('assignees',fn($a)=>$a->where('users.id',$user->id));});
  $q->whereHas('project',fn($p)=>$p->where('pm_user_id',$user->id));
  if($category==='managed_overdue')return $q->where('ends_at','<',now());
  return $q->whereDoesntHave('assignees')->where('starts_at','<=',now()->addWeekdays(3)->endOfDay());
 }
 public function rows(User $user){
  return collect(['my_overdue'=>['Your overdue tasks','View tasks'],'follow_up'=>['Blockers needing your follow-up','Review'],'managed_overdue'=>['Overdue work in projects you manage','Review tasks'],'needs_owner'=>['Upcoming work without an owner','Assign people']])->map(function($labels,$key)use($user){$q=$this->query($user,$key);return ['type'=>$key,'title'=>$labels[0],'count'=>(clone $q)->count(),'projects'=>(clone $q)->distinct()->count('project_id'),'action_label'=>$labels[1],'action_url'=>route('tasks.mine',['attention'=>$key,'scope'=>$key==='my_overdue'?'assigned':'all','filter'=>'open'])];})->filter(fn($r)=>$r['count']>0)->values();
 }
}
