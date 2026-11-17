<?php
namespace App\Http\Controllers;
use App\Models\{Project,User,ProjectRole,ProjectMembership};
use App\Services\Audit;
use App\Notifications\ProjectWorkflowNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ProjectHandoverController {
 public function store(Request $r,Project $project){
  $d=$r->validate(['role'=>'required|in:pm,owner','user_id'=>'required|integer|exists:users,id','note'=>'required|string|max:2000']);
  DB::transaction(function()use($r,$project,$d){
   $p=Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
   abort_unless($r->user()->isPmo()||($d['role']==='pm'&&(int)$p->owner_user_id===$r->user()->id),403);
   abort_unless(in_array($p->status,['active','on_hold']),409,'Only active or on-hold projects can be handed over.');
   $person=User::whereKey($d['user_id'])->where('system_role','user')->where('account_status','active')->firstOrFail();
   $previous=$d['role']==='pm'?$p->pm_user_id:$p->owner_user_id;
   abort_if((int)$previous===$person->id,422,'Choose a different person.');
   DB::table('project_handovers')->where('project_id',$p->id)->where('role',$d['role'])->where('status','pending')->update(['status'=>'superseded','updated_at'=>now()]);
   DB::table('project_handovers')->insert(['project_id'=>$p->id,'role'=>$d['role'],'previous_user_id'=>$previous,'user_id'=>$person->id,'requested_by'=>$r->user()->id,'note'=>$d['note'],'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
   Audit::record('project.handover_requested',$p,['role'=>$d['role'],'user_id'=>$person->id]);
   $role=$d['role']==='pm'?'Project Manager':'Project Owner';
   DB::afterCommit(fn()=>$person->notify(new ProjectWorkflowNotification($p,'handover','You have been invited to take over as '.$role.'. Open the project to review and accept the handover. Note: '.$d['note'],(string)now(),'project_reassigned')));
  });
  return back()->with('success','Handover request sent. The current lead stays assigned until the new person accepts.');
 }
 public function respond(Request $r,Project $project,int $handover){
  $d=$r->validate(['decision'=>'required|in:accept,decline']);
  DB::transaction(function()use($r,$project,$handover,$d){
   $p=Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
   $h=DB::table('project_handovers')->where('id',$handover)->where('project_id',$p->id)->lockForUpdate()->first();
   abort_unless($h && (int)$h->user_id===$r->user()->id && $r->user()->isActive(),403);
   abort_unless($h->status==='pending'&&in_array($p->status,['active','on_hold']),409);
   $field=$h->role==='pm'?'pm_user_id':'owner_user_id';
   abort_unless((int)$p->$field===(int)$h->previous_user_id,409,'The project lead has changed. Request a new handover.');
   if($d['decision']==='accept'){
    $slug=$h->role==='pm'?'pm':'po';
    $role=ProjectRole::where('slug',$slug)->firstOrFail();
    $p->update([$field=>$r->user()->id]);
    ProjectMembership::where('project_id',$p->id)->where('user_id',$h->previous_user_id)->where('project_role_id',$role->id)->delete();
    ProjectMembership::firstOrCreate(['project_id'=>$p->id,'user_id'=>$r->user()->id,'project_role_id'=>$role->id]);
   }
   DB::table('project_handovers')->where('id',$h->id)->update(['status'=>$d['decision']==='accept'?'accepted':'declined','updated_at'=>now()]);
   Audit::record('project.handover_'.$d['decision'],$p,['role'=>$h->role,'user_id'=>$r->user()->id]);
  });
  return redirect(route('projects.show',$project).'#team')->with('success',$d['decision']==='accept'?'Handover accepted. Project progress, tasks and schedule are unchanged.':'Handover declined.');
 }
}
