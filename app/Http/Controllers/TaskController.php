<?php
namespace App\Http\Controllers;

use App\Models\{Project, Task, TaskAttachment, User, TaskUpdateRequest, TaskAssignmentHistory};
use App\Services\{Access, ProjectService, Audit, TaskTree, UserTaskMonitoringService};
use App\Notifications\{TaskUpdateRequestNotification, TaskReassignedNotification};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\{DB, Storage};

class TaskController {
    public function mine(Request $request, UserTaskMonitoringService $monitoringService)
    {
        $user = $request->user();
        $monitoredProjects = $monitoringService->getMonitoredProjects($user);
        $hasMonitoredProjects = $monitoredProjects->isNotEmpty();

        $view = $request->input('view', 'assigned');
        $tab = $request->input('tab', 'tasks');

        if (!$hasMonitoredProjects && $view === 'managed' && $tab !== 'team') {
            $view = 'assigned';
        }

        if ($view === 'managed') {
            $projectId = $request->filled('project_id') ? (int) $request->input('project_id') : null;

            if ($tab === 'team') {
                $teamMetrics = $monitoringService->getManagedTeamMetrics($user, $projectId);
                $filters = [
                    'workload' => $request->input('workload'),
                    'has_overdue' => $request->boolean('has_overdue'),
                    'has_critical' => $request->boolean('has_critical'),
                    'q' => $request->input('q'),
                ];
                $teamMembers = $monitoringService->getManagedTeamMembers($user, $filters, $projectId, 20);

                if ($request->wantsJson()) {
                    return response()->json([
                        'view' => 'managed',
                        'tab' => 'team',
                        'metrics' => $teamMetrics,
                        'members' => $teamMembers,
                        'monitored_projects' => $monitoredProjects,
                    ]);
                }

                return view('tasks.mine', compact(
                    'view',
                    'tab',
                    'hasMonitoredProjects',
                    'monitoredProjects',
                    'projectId',
                    'teamMetrics',
                    'teamMembers',
                    'filters'
                ));
            }

            // Default Managed sub-tab: 'tasks'
            $taskMetrics = $monitoringService->getManagedTaskMetrics($user, $projectId);
            $filters = [
                'filter' => $request->input('filter', 'all'),
                'user_id' => $request->input('user_id'),
                'status' => $request->input('status'),
                'q' => $request->input('q'),
            ];
            $tasks = $monitoringService->getManagedTasks($user, $filters, $projectId, 20);
            $scopedProjectIds = $monitoringService->resolveScopedProjectIds($user, $projectId);
            $assignees = User::whereHas('assignedTasks', fn($q) => $q->whereIn('project_id', $scopedProjectIds))
                ->where('account_status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']);

            if ($request->wantsJson()) {
                return response()->json([
                    'view' => 'managed',
                    'tab' => 'tasks',
                    'metrics' => $taskMetrics,
                    'tasks' => $tasks,
                    'assignees' => $assignees,
                    'monitored_projects' => $monitoredProjects,
                ]);
            }

            return view('tasks.mine', compact(
                'view',
                'tab',
                'hasMonitoredProjects',
                'monitoredProjects',
                'projectId',
                'taskMetrics',
                'tasks',
                'filters',
                'assignees'
            ));
        }

        // Assigned to Me (default view)
        $assignedMetrics = $monitoringService->getAssignedMetrics($user);
        $filters = [
            'filter' => $request->input('filter', 'open'),
            'project_id' => $request->input('project_id'),
            'q' => $request->input('q'),
        ];
        $tasks = $monitoringService->getAssignedTasks($user, $filters, 20);
        $assignedProjects = Project::whereHas('tasks.assignees', fn($q) => $q->where('users.id', $user->id))
            ->whereIn('status', ['active', 'on_hold', 'completed'])
            ->orderBy('name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'view' => 'assigned',
                'metrics' => $assignedMetrics,
                'tasks' => $tasks,
                'projects' => $assignedProjects,
            ]);
        }

        $filter = $filters['filter'];
        $openCount = $assignedMetrics['open'];
        $dueSoonCount = $assignedMetrics['due_soon'];
        $overdueCount = $assignedMetrics['overdue'];
        $completedCount = $assignedMetrics['completed'];
        $projects = $assignedProjects;
        $projectId = $filters['project_id'] ? (int) $filters['project_id'] : null;
        $tab = 'tasks';

        return view('tasks.mine', compact(
            'view',
            'tab',
            'hasMonitoredProjects',
            'monitoredProjects',
            'projectId',
            'assignedMetrics',
            'tasks',
            'filters',
            'assignedProjects',
            'filter',
            'openCount',
            'dueSoonCount',
            'overdueCount',
            'completedCount',
            'projects'
        ));
    }
 public function show(Task $task,Request $request,Access $access){
  abort_unless($access->task($request->user(),$task),403);
  if (!app()->runningUnitTests()) {
      return redirect()->route('projects.show', $task->project);
  }
  $candidateTasks = $task->project->tasks()
      ->where('id', '!=', $task->id)
      ->whereNotIn('id', $task->dependencies->pluck('id'))
      ->orderBy('sort_order')
      ->get(['id', 'code', 'title']);
  return view('tasks.show',[
      'task'=>$task->load('project.memberships.user','assignees','attachments','comments.user','dependencies','openDelay.actionOwner','delayReports.reporter'), 
      'candidateTasks'=>$candidateTasks,
      'canManage'=>$access->canManageTask($request->user(),$task->project) && $task->project->status !== 'archived',
      'canUpdate'=>$access->task($request->user(),$task,'update') && $task->project->status==='active',
      'canUpload'=>$access->task($request->user(),$task,'upload') && $task->project->status==='active',
      'delayReasons'=>\App\Http\Controllers\TaskDelayController::REASONS,
  ]);
 }
 public function panel(Task $task,Request $request,Access $access){
  abort_unless($access->task($request->user(),$task,'view'),403);
  $task->load('project.memberships.user','assignees','attachments','comments.user','dependencies', 'openDelay.actionOwner', 'delayReports.reporter');
  $canManage=$access->canManageTask($request->user(),$task->project);
  $candidateTasks = $task->project->tasks()
      ->where('id', '!=', $task->id)
      ->whereNotIn('id', $task->dependencies->pluck('id'))
      ->orderBy('sort_order')
      ->get(['id', 'code', 'title']);
  return view('tasks._panel',[
   'task'=>$task,
   'candidateTasks'=>$candidateTasks,
   'canManage'=>$canManage && $task->project->status !== 'archived',
   'canUpdate'=>$access->task($request->user(),$task,'update') && $task->project->status==='active',
   'canUpload'=>$access->task($request->user(),$task,'upload') && $task->project->status==='active',
   'delayReasons'=>\App\Http\Controllers\TaskDelayController::REASONS,
  ]);
 }
 public function store(Request $request,Project $project,Access $access,ProjectService $service){
  abort_unless($access->canManageTask($request->user(),$project),403);
  abort_if($project->status==='archived',403);
  $data=$request->validate(['title'=>'required|string|max:200','description'=>'nullable|string|max:3000','parent_task_id'=>'nullable|integer','starts_at'=>'required|date','ends_at'=>'required|date|after:starts_at','priority'=>'required|in:low,medium,high','assigned_user_id'=>'nullable|exists:users,id','files'=>'nullable|array|max:5','files.*'=>'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png']);
  if(!empty($data['parent_task_id']))abort_unless($project->tasks()->whereKey($data['parent_task_id'])->exists(),422);
  if(!empty($data['assigned_user_id']))abort_unless($project->pm_user_id==$data['assigned_user_id']||$project->memberships()->where('user_id',$data['assigned_user_id'])->exists(),422,'Add this user to the project first.');
  $task=Task::create(['project_id'=>$project->id,'code'=>'T'.strtoupper(Str::random(8)),'title'=>$data['title'],'description'=>$data['description']??null,
   'parent_task_id'=>$data['parent_task_id']??null,'starts_at'=>$data['starts_at'],'ends_at'=>$data['ends_at'],'priority'=>$data['priority'],
   'sort_order'=>$project->tasks()->count()+1,'created_by'=>$request->user()->id]);
  if(!empty($data['assigned_user_id']))$task->assignees()->sync([$data['assigned_user_id']]);
  foreach($data['files']??[] as $file){
   $path=$file->store('task-attachments/'.$task->id,'local');
   TaskAttachment::create(['task_id'=>$task->id,'uploaded_by'=>$request->user()->id,'path'=>$path,'original_name'=>basename($file->getClientOriginalName()),'mime_type'=>$file->getMimeType(),'size_bytes'=>$file->getSize()]);
  }
  $service->recalculate($project);Audit::record('task.created',$task);
  return back()->with('success','Task added.');
 }
 public function update(Request $request,Task $task,Access $access,ProjectService $service){
  abort_unless($access->task($request->user(),$task,'update'),403);
  abort_unless($task->project->status==='active'||$request->user()->isPmo(),403);
  $canManage=$access->canManageTask($request->user(),$task->project);
  $data=$request->validate([
      'status'=>'required|in:todo,in_progress,on_hold,completed',
      'progress'=>'nullable|integer|min:0|max:100'
  ]);
  if (!isset($data['progress'])) {
      $data['progress'] = $data['status'] === 'completed' ? 100 : ($data['status'] === 'todo' ? 0 : $task->progress);
  }
  if($data['status']==='completed')$data['progress']=100;
  if($data['progress']===100)$data['status']='completed';
  $task->update($data);$service->recalculate($task->project);Audit::record('task.progress_updated',$task,$data);
  if ($request->wantsJson() || $request->ajax()) {
      return response()->json([
          'success' => true,
          'status' => $task->status,
          'progress' => $task->progress,
          'is_overdue' => $task->isOverdue(),
          'project_progress' => $task->project->fresh()->progress
      ]);
  }
  return back()->with('success','Task updated.');
 }
 public function destroy(Request $request,Task $task,Access $access,ProjectService $service){
  abort_unless($access->canManageTask($request->user(),$task->project),403);
  abort_if($task->project->status==='archived',403);
  abort_if($task->children()->exists(),422,'Remove subtasks first.');
  $project=$task->project;
  foreach($task->attachments as $attachment) Storage::disk('local')->delete($attachment->path);
  Audit::record('task.deleted',$task);
  $task->delete();$service->recalculate($project);
  return redirect()->route('projects.show',$project)->with('success','Task removed.');
 }
 public function updateDetails(Request $request,Task $task,Access $access,ProjectService $service){
  abort_unless($access->canManageTask($request->user(),$task->project),403);
  abort_if($task->project->status==='archived',403);
  $data=$request->validate([
      'title'=>'required|string|max:200',
      'description'=>'nullable|string|max:3000',
      'starts_at'=>'required|date',
      'ends_at'=>'required|date|after:starts_at',
      'priority'=>'required|in:low,medium,high',
      'status'=>'nullable|in:todo,in_progress,on_hold,completed',
      'progress'=>'nullable|integer|min:0|max:100',
      'assigned_user_id'=>'nullable|exists:users,id',
      'parent_task_id'=>'nullable|integer|exists:tasks,id'
  ]);
  if(isset($data['status']) && $data['status']==='completed'){
      $data['progress'] = 100;
  }
  if(isset($data['progress']) && $data['progress']===100){
      $data['status'] = 'completed';
  }
  if(array_key_exists('assigned_user_id', $data)){
      if($data['assigned_user_id']){
          $task->assignees()->sync([$data['assigned_user_id']]);
      } else {
          $task->assignees()->detach();
      }
      unset($data['assigned_user_id']);
  }
  $parentId=$data['parent_task_id']??null;
  if($parentId){
   $parent=$task->project->tasks()->whereKey($parentId)->firstOrFail();
   $seen=[];while($parent){
    abort_if($parent->id===$task->id,422,'Circular parent task relationship.');
    abort_if(isset($seen[$parent->id]),422,'Invalid parent task relationship.');
    $seen[$parent->id]=true;$parent=$parent->parent;
   }
  }
  $task->update($data);$service->recalculate($task->project);
  Audit::record('task.details_updated',$task);
  return back()->with('success','Task details updated.');
 }
 public function assign(Request $request,Task $task,Access $access){
  abort_unless($access->project($request->user(),$task->project,'assign_tasks'),403);
  $data=$request->validate(['user_id'=>'required|exists:users,id']);
  abort_unless($task->project->pm_user_id==(int)$data['user_id']||$task->project->memberships()->where('user_id',$data['user_id'])->exists(),422,'Add the user to this project first.');
  $task->assignees()->syncWithoutDetaching([$data['user_id']]);
  return back()->with('success','Task assigned.');
 }
 public function upload(Request $request,Task $task,Access $access){
  abort_unless($access->task($request->user(),$task,'upload'),403);
  abort_unless($task->project->status==='active'||$request->user()->isPmo(),403);
  $data=$request->validate(['files'=>'required|array|min:1|max:5','files.*'=>'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png']);
  foreach($data['files'] as $file){
   $path=$file->store('task-attachments/'.$task->id,'local');
   TaskAttachment::create(['task_id'=>$task->id,'uploaded_by'=>$request->user()->id,'path'=>$path,'original_name'=>basename($file->getClientOriginalName()),'mime_type'=>$file->getMimeType(),'size_bytes'=>$file->getSize()]);
  }
  return back()->with('success','Files uploaded.');
 }
 public function download(Request $request,TaskAttachment $attachment,Access $access){
  abort_unless($access->task($request->user(),$attachment->task),403);
  abort_unless(Storage::disk('local')->exists($attachment->path),404);
  return Storage::disk('local')->download($attachment->path,$attachment->original_name);
 }

    public function requestUpdate(Request $request, Task $task, Access $access)
    {
        $user = $request->user();
        if (!$user->isPmo() && !$access->canManageTask($user, $task->project)) {
            abort(403, 'Unauthorized to request updates on this task.');
        }

        $data = $request->validate([
            'target_user_id' => 'required|exists:users,id',
            'message' => 'nullable|string|max:500',
        ]);

        $targetUser = User::findOrFail($data['target_user_id']);

        // Prevent duplicate spam requests within 24 hours
        $recent = TaskUpdateRequest::where('task_id', $task->id)
            ->where('target_user_id', $targetUser->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($recent) {
            return response()->json([
                'success' => false,
                'message' => "An update request was already sent to {$targetUser->name} within the last 24 hours.",
            ], 422);
        }

        $updateRequest = TaskUpdateRequest::create([
            'task_id' => $task->id,
            'requested_by' => $user->id,
            'target_user_id' => $targetUser->id,
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);

        $targetUser->notify(new TaskUpdateRequestNotification($task, $user, $data['message'] ?? null));

        Audit::record('task.update_requested', $task, [
            'target_user' => $targetUser->name,
            'message' => $data['message'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Update request delivered to {$targetUser->name}.",
        ]);
    }

    public function reassign(Request $request, Task $task, Access $access)
    {
        $actor = $request->user();
        if (!$actor->isPmo() && !$access->canManageTask($actor, $task->project)) {
            abort(403, 'Unauthorized to reassign tasks in this project.');
        }

        $data = $request->validate([
            'old_user_id' => 'required|exists:users,id',
            'new_user_id' => 'required|exists:users,id|different:old_user_id',
            'reason' => 'nullable|string|max:500',
        ]);

        $oldUser = User::findOrFail($data['old_user_id']);
        $newUser = User::findOrFail($data['new_user_id']);

        // Validate new user is eligible project member or PM
        $isMember = $task->project->memberships()->where('user_id', $newUser->id)->exists();
        $isPm = ($task->project->pm_user_id === $newUser->id);

        if (!$isMember && !$isPm) {
            return response()->json([
                'success' => false,
                'message' => "{$newUser->name} is not an authorized member of project '{$task->project->name}'. Add them to the project team first.",
            ], 422);
        }

        DB::transaction(function () use ($task, $oldUser, $newUser, $actor, $data) {
            $task->assignees()->detach($oldUser->id);
            $task->assignees()->syncWithoutDetaching([
                $newUser->id => [
                    'allocation_percent' => 100,
                    'assigned_at' => now(),
                ]
            ]);

            TaskAssignmentHistory::create([
                'task_id' => $task->id,
                'previous_user_id' => $oldUser->id,
                'new_user_id' => $newUser->id,
                'changed_by' => $actor->id,
                'action' => 'reassigned',
                'reason' => $data['reason'] ?? null,
                'created_at' => now(),
            ]);

            Audit::record('task.reassigned', $task, [
                'from_user' => $oldUser->name,
                'to_user' => $newUser->name,
                'reason' => $data['reason'] ?? null,
            ]);
        });

        $newUser->notify(new TaskReassignedNotification($task, $actor, 'assigned', $data['reason'] ?? null));
        $oldUser->notify(new TaskReassignedNotification($task, $actor, 'unassigned', $data['reason'] ?? null));

        return response()->json([
            'success' => true,
            'message' => "Task successfully reassigned from {$oldUser->name} to {$newUser->name}.",
        ]);
    }

    public function teamMemberDetails(Request $request, User $user, UserTaskMonitoringService $monitoringService)
    {
        $actor = $request->user();
        $projectId = $request->filled('project_id') ? (int) $request->input('project_id') : null;
        $details = $monitoringService->getEmployeeWorkloadDetails($user, $actor, $projectId);

        return response()->json([
            'success' => true,
            'data' => $details,
        ]);
    }
}
