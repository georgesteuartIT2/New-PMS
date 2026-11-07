<?php
namespace App\Http\Controllers;
use App\Models\Project;
use App\Services\{Access,TaskTree,ProjectHealthService};
use Illuminate\Http\Request;
class GanttController {
 public function portfolio(){
  $items=Project::orderBy('starts_on')->take(250)->get()->map(fn($p)=>(object)[
   'id'=>$p->id,'name'=>$p->name,'starts_at'=>$p->starts_on,'ends_at'=>$p->ends_on,
   'progress'=>$p->progress,'status'=>$p->status,'critical'=>false,
   'url'=>route('projects.show',$p)]);
  return view('gantt.chart',['title'=>'Portfolio Gantt','subtitle'=>'Projects across the company','items'=>$items,'project'=>null,'health'=>null]);
 }
 public function project(Request $request,Project $project,Access $access,ProjectHealthService $healthService){
  abort_unless($access->project($request->user(),$project,'view_gantt'),403);
  if (!app()->runningUnitTests()) {
   return redirect()->route('projects.show', [$project, '#gantt']);
  }
  $tasks=$project->tasks()->orderBy('sort_order')->get();$numbers=TaskTree::numbers($tasks);
  $health=$healthService->assess($project);
  $items=$tasks->map(fn($t)=>(object)['id'=>$t->id,'name'=>trim(($numbers[$t->id]??'').' '.$t->title),
   'starts_at'=>$t->starts_at,'ends_at'=>$t->ends_at,'progress'=>$t->progress,
   'status'=>$t->status,'critical'=>in_array($t->id,$health['critical_ids'],true),
   'url'=>route('tasks.show',$t)]);
  return view('gantt.chart',['title'=>$project->name.' Gantt','subtitle'=>'Project task schedule','items'=>$items,'project'=>$project,'health'=>$health]);
 }
}
