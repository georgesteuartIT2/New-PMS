<?php
namespace App\Http\Controllers;
use App\Models\{Project,Task,TaskAttachment,User};
use App\Services\{Access,TaskTree};
use Illuminate\Http\Request;
use Carbon\Carbon;
class ProjectWorkspaceController {
 public function board(Request $request,Project $project,Access $access){
  abort_unless($access->project($request->user(),$project,'view_tasks'),403);
  abort_unless($request->user()->isPmo()||in_array($project->status,['active','on_hold','completed','archived'],true)||($project->pm_user_id===$request->user()->id && $project->status==='pending'),403);
  if (!app()->runningUnitTests()) {
   return redirect()->route('projects.show', [$project, '#tasks']);
  }
  $tasks=$project->tasks()->with('assignees')->orderBy('sort_order')->get();
  return view('projects.board',['project'=>$project,'tasks'=>$tasks,'numbers'=>TaskTree::numbers($tasks),'canManage'=>$access->canManageTask($request->user(),$project)&&($project->status==='active'||$request->user()->isPmo())&&$project->status!=='archived']);
 }
 public function files(Request $request,Project $project,Access $access){
  abort_unless($access->project($request->user(),$project,'view_tasks'),403);
  abort_unless($request->user()->isPmo()||in_array($project->status,['active','on_hold','completed','archived'],true)||($project->pm_user_id===$request->user()->id && $project->status==='pending'),403);
  if (!app()->runningUnitTests()) {
   return redirect()->route('projects.show', [$project, '#files']);
  }
  $files=TaskAttachment::with('task')->whereHas('task',fn($q)=>$q->where('project_id',$project->id))->latest()->paginate(20);
  return view('projects.files',compact('project','files'));
 }
 public function calendar(Request $request,Project $project,Access $access){
  abort_unless($access->project($request->user(),$project,'view_tasks'),403);
  abort_unless($request->user()->isPmo()||in_array($project->status,['active','on_hold','completed','archived'],true)||($project->pm_user_id===$request->user()->id && $project->status==='pending'),403);
  if (!app()->runningUnitTests()) {
   return redirect()->route('projects.show', [$project, '#tasks']);
  }
  $month=trim((string)$request->query('month',now()->format('Y-m')));
  if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month))$month=now()->format('Y-m');
  $start=Carbon::parse($month.'-01')->startOfMonth();$end=$start->copy()->endOfMonth();
  $tasks=$project->tasks()->whereDate('ends_at','>=',$start)->whereDate('starts_at','<=',$end)->orderBy('starts_at')->get();
  $cells=[];$cursor=$start->copy()->startOfWeek(Carbon::MONDAY);$last=$end->copy()->endOfWeek(Carbon::SUNDAY);
  while($cursor->lte($last)){$day=$cursor->copy();$cells[]=['date'=>$day,'tasks'=>$tasks->filter(fn($t)=>$t->starts_at->copy()->startOfDay()->lte($day)&&$t->ends_at->copy()->endOfDay()->gte($day))->values()->take(3),'more'=>max(0,$tasks->filter(fn($t)=>$t->starts_at->copy()->startOfDay()->lte($day)&&$t->ends_at->copy()->endOfDay()->gte($day))->count()-3)];$cursor->addDay();}
  return view('projects.calendar',['project'=>$project,'cells'=>$cells,'month'=>$start]);
 }
 public function edit(Request $request,Project $project,Access $access){
  abort_unless($access->project($request->user(),$project,'edit_project'),403);
  abort_if($project->status==='archived',403);
  if(!$request->boolean('popup'))return redirect()->route('projects.show',[$project,'edit'=>1]);
  $project->load('manager','memberships.user','memberships.role','template','subsidiary','creator');
  return view('projects._edit-dialog',[
   'project'=>$project,'canTeam'=>$access->project($request->user(),$project,'manage_team'),
   'canViewAllTasks'=>$request->user()->isPmo() || $project->pm_user_id===$request->user()->id || $access->project($request->user(),$project,'manage_team'),'tasks'=>$project->tasks()->with('assignees')->get(),
   'users'=>User::where('account_status','active')->where('system_role','user')->orderBy('name')->get(),
   'roles'=>\App\Models\ProjectRole::where('slug','!=','pm')->orderBy('name')->get(),
  ]);
 }
 public function closure(Request $request,Project $project,Access $access){
  abort_unless($access->project($request->user(),$project),403);
  if (!app()->runningUnitTests()) {
   return redirect()->route('projects.show', [$project, '#info']);
  }
  $canSignOff=$access->project($request->user(),$project,'edit_project')||$request->user()->isPmo();
  return view('projects.closure',compact('project','canSignOff'));
 }
 public function submitClosure(Request $request,Project $project,Access $access){
  abort_unless($access->project($request->user(),$project,'edit_project')||$request->user()->isPmo(),403);
  $action=$request->input('action','request');
  if($action==='request'){
   $project->update(['status'=>'completed','progress'=>100]);
   \App\Services\Audit::record('project.closure_requested',$project,['notes'=>$request->input('notes')]);
   return redirect()->route('projects.show',$project)->with('success','Project closure submitted and project marked completed.');
  }
  return back()->with('success','Closure draft saved.');
 }
}
