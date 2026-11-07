<?php
namespace App\Http\Controllers;
use App\Models\{Task,TaskAttachment,TaskComment};
use App\Services\{Access,Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
class TaskExtrasController {
 public function comment(Request $request,Task $task,Access $access){
  abort_unless($access->task($request->user(),$task,'view'),403);
  abort_unless($task->project->status==='active',403);
  abort_unless($request->user()->isPmo() || $task->project->pm_user_id===$request->user()->id || ($access->role($request->user(),$task->project)?->role?->slug==='team'),403);
  $data=$request->validate(['body'=>'required|string|min:1|max:2000']);
  $comment=$task->comments()->create(['user_id'=>$request->user()->id,'body'=>$data['body']]);
  Audit::record('task.comment_added',$task,['comment_id'=>$comment->id]);
  return back()->with('success','Comment added.');
 }
 public function removeComment(Request $request,TaskComment $comment,Access $access){
  abort_unless($access->task($request->user(),$comment->task,'view'),403);
  abort_unless($request->user()->isPmo()||$comment->user_id===$request->user()->id||$comment->task->project->pm_user_id===$request->user()->id,403);
  $comment->delete();return back()->with('success','Comment removed.');
 }
 public function removeFile(Request $request,TaskAttachment $attachment,Access $access){
  abort_unless($access->task($request->user(),$attachment->task,'upload'),403);
  abort_unless($request->user()->isPmo()||$attachment->uploaded_by===$request->user()->id||$attachment->task->project->pm_user_id===$request->user()->id,403);
  Storage::disk('local')->delete($attachment->path);
  $attachment->delete();return back()->with('success','File removed.');
 }
 public function unassign(Request $request,Task $task,Access $access){
  abort_unless($access->project($request->user(),$task->project,'assign_tasks'),403);
  abort_if($task->project->status==='archived',403);
  $data=$request->validate(['user_id'=>'required|exists:users,id']);
  $task->assignees()->detach($data['user_id']);
  return back()->with('success','Member unassigned.');
 }
 public function dependency(Request $request,Task $task,Access $access, \App\Services\ProjectService $projectService){
  abort_unless($access->canManageTask($request->user(),$task->project),403);
  abort_if($task->project->status==='archived',403);
  $data=$request->validate(['depends_on_task_id'=>'required|integer']);
  $dependency=$task->project->tasks()->whereKey($data['depends_on_task_id'])->firstOrFail();
  if($dependency->id===$task->id)throw ValidationException::withMessages(['depends_on_task_id'=>'A task cannot depend on itself.']);
  // Reject cycles in dependency links.
  $seen=[];$queue=[$dependency->id];
  while($queue){$id=array_pop($queue);if($id===$task->id)throw ValidationException::withMessages(['depends_on_task_id'=>'This would create a dependency cycle.']);if(isset($seen[$id]))continue;$seen[$id]=true;
   foreach(Task::findOrFail($id)->dependencies()->pluck('tasks.id') as $other)$queue[]=$other;
  }
  $task->dependencies()->syncWithoutDetaching([$dependency->id]);
  $projectService->recalculate($task->project);
  Audit::record('task.dependency_added', $task, ['depends_on' => $dependency->id, 'dependency_code' => $dependency->code]);
  return back()->with('success','Dependency added.');
 }
 public function removeDependency(Request $request,Task $task,Task $dependency,Access $access, \App\Services\ProjectService $projectService){
  abort_unless($access->canManageTask($request->user(),$task->project),403);
  abort_unless($task->project_id===$dependency->project_id,404);
  abort_if($task->project->status==='archived',403);
  $task->dependencies()->detach($dependency->id);
  $projectService->recalculate($task->project);
  Audit::record('task.dependency_removed', $task, ['removed_dependency' => $dependency->id, 'dependency_code' => $dependency->code]);
  return back()->with('success','Dependency removed.');
 }
}
