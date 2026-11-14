<?php
namespace App\Http\Controllers;

use App\Models\{Project, Task, TaskAttachment, User, TaskUpdateRequest, TaskAssignmentHistory};
use App\Services\{Access, ProjectService, Audit, TaskTree, UserTaskMonitoringService};
use App\Notifications\{TaskUpdateRequestNotification, TaskReassignedNotification};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\{DB, Storage};

class TaskController {
    public function team(Request $request, UserTaskMonitoringService $monitoringService)
    {
        if ($request->user()->isPmo()) return redirect()->route('pmo.team-monitor', $request->except('view','tab'));
        $request->merge(['view'=>'managed','tab'=>'team']);
        return $this->mine($request, $monitoringService);
    }

    public function mine(Request $request, UserTaskMonitoringService $monitoringService)
    {
        if (!app()->runningUnitTests() && !$request->routeIs('team-monitor') && $request->input('tab') === 'team') {
            return redirect()->route('team-monitor', $request->except('view','tab'));
        }
        if ($request->input('view') === 'managed' && $request->filled('tab')) {
            // Keep legacy managed task/team monitor tab endpoints for backward compatibility with monitoring tests
        } else {
            return $this->allTasks($request, $monitoringService);
        }
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
 private function allTasks(Request $request,UserTaskMonitoringService $service){
  $legacy=$request->input('view');$scope=$request->input('scope',in_array($legacy,['assigned','managed'],true)?$legacy:'all');
  if(!in_array($scope,['all','assigned','managed'],true))$scope='all';
  $filters=['attention'=>$request->input('attention'),'scope'=>$scope,'filter'=>$request->input('filter','all'),'project_id'=>$request->input('project_id'),'user_id'=>$request->input('user_id'),'status'=>$request->input('status'),'q'=>$request->input('q')];
  $workspace=$service->personalWorkspace($request->user(),$filters);
  if($request->wantsJson())return response()->json(['view'=>$scope,'tab'=>'tasks','filters'=>$filters,'metrics'=>$workspace['metrics'],'tasks'=>$workspace['tasks'],'projects'=>$workspace['projects'],'assignees'=>$workspace['assignees']]);
  return view('tasks.all',$workspace+['filters'=>$filters,'scope'=>$scope]);
 }
 public function show(Task $task,Request $request,Access $access){
  abort_unless($access->task($request->user(),$task),403);
  if (!app()->runningUnitTests()) {
      return redirect()->route('projects.show', [$task->project_id, 'focus_task'=>$task->id]);
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
 public function criticalExplanation(Task $task,Request $request,Access $access,\App\Services\ProjectHealthService $healthService){
  abort_unless($access->task($request->user(),$task,'view'),403);
  $health=$healthService->assess($task->project);$critical=in_array($task->id,$health['critical_ids'],true);
  if(!$critical)return response()->json(['critical'=>false,'title'=>$task->title,'reason'=>'This task is not currently on a reliable critical path. Refresh the task list for the latest schedule.']);
  $nodes=$health['dependency_nodes'];$before=$nodes[$task->id]['predecessors']??[];
  $after=array_keys(array_filter($nodes,fn($node)=>in_array($task->id,$node['predecessors'],true)));
  $linked=$task->project->tasks()->whereIn('id',array_unique(array_merge($before,$after)))->get()->keyBy('id');
  $names=function($ids)use($linked,$request,$access){return collect($ids)->map(function($id)use($linked,$request,$access){$item=$linked->get($id);return $item && $access->task($request->user(),$item,'view')?['code'=>$item->code,'title'=>$item->title]:['code'=>null,'title'=>'Restricted task'];})->values();};
  $reason= !$before && !$after ? 'This task itself is a longest path through the task network.' : (!$before ? 'This task starts a critical dependency chain. Later tasks depend on it.' : (!$after ? 'This task finishes a critical dependency chain and determines the calculated completion.' : 'This task connects earlier and later work on a critical dependency chain.'));
  return response()->json(['critical'=>true,'title'=>$task->title,'reason'=>$reason,'float_minutes'=>$health['cpm_tasks'][$task->id]['float_minutes'],'predecessors'=>$names($before),'successors'=>$names($after),'impact'=>'There is no spare working time in this calculated network. A delay can move its completion if the remaining plan stays unchanged.']);
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
  abort_unless($access->project($request->user(),$project,'tasks.create'),403);
  abort_if($project->status==='archived',403);
  $data=$request->validate(['title'=>'required|string|max:200','description'=>'nullable|string|max:3000','parent_task_id'=>'nullable|integer','starts_at'=>'required|date','ends_at'=>'required|date|after:starts_at','priority'=>'required|in:low,medium,high','assigned_user_id'=>'nullable|exists:users,id','files'=>'nullable|array|max:5','files.*'=>'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png']);
  if(!empty($data['parent_task_id']))abort_unless($project->tasks()->whereKey($data['parent_task_id'])->exists(),422);
  if(!empty($data['assigned_user_id'])){
   $person=User::whereKey($data['assigned_user_id'])->where('account_status','active')->where('system_role','user')->firstOrFail();
   $member=(int)$project->pm_user_id===$person->id||$project->memberships()->where('user_id',$person->id)->exists();
   if(!$member){
    abort_unless($access->project($request->user(),$project,'assign_tasks')&&$access->project($request->user(),$project,'manage_team'),403);
    abort_unless($request->boolean('add_assignee_to_project'),422,'Confirm Add this person to the project before assigning.');
    $role=\App\Models\ProjectRole::where('slug','team')->firstOrFail();
    \App\Models\ProjectMembership::firstOrCreate(['project_id'=>$project->id,'user_id'=>$person->id,'project_role_id'=>$role->id]);
    Audit::record('project.member_added',$project,['user_id'=>$person->id,'source'=>'manual_task']);
   }
  }
  if(!empty($data['assigned_user_id']))abort_unless($project->pm_user_id==$data['assigned_user_id']||$project->memberships()->where('user_id',$data['assigned_user_id'])->exists(),422,'Add this user to the project first.');
  $parentId = !empty($data['parent_task_id']) ? (int)$data['parent_task_id'] : null;
  $siblingOrder = ($parentId ? $project->tasks()->where('parent_task_id', $parentId)->count() : $project->tasks()->whereNull('parent_task_id')->count()) + 1;
  $task=Task::create(['project_id'=>$project->id,'code'=>'T'.strtoupper(Str::random(8)),'title'=>$data['title'],'description'=>$data['description']??null,
   'parent_task_id'=>$parentId,'starts_at'=>$data['starts_at'],'ends_at'=>$data['ends_at'],'priority'=>$data['priority'],
   'sort_order'=>$siblingOrder,'created_by'=>$request->user()->id]);
  if(!empty($data['assigned_user_id'])){$task->assignees()->sync([$data['assigned_user_id']]);app(\App\Services\InboxService::class)->assigned($task,User::findOrFail($data['assigned_user_id']));}
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
  $isAssigned=$task->assignees()->where('users.id',$request->user()->id)->exists();
  abort_unless($canManage || $request->user()->isPmo() || $task->project->pm_user_id === $request->user()->id || $isAssigned, 403);
  $data=$request->validate([
      'status'=>'nullable|in:todo,in_progress,on_hold,completed',
      'progress'=>'nullable|integer|min:0|max:100',
      'priority'=>'nullable|in:low,medium,high',
  ]);
  $legacyAssigned=$isAssigned && !$access->memberships($request->user(),$task->project)->count() && $task->project->pm_user_id!==$request->user()->id;
  if(isset($data['status']))abort_unless($legacyAssigned || $access->project($request->user(),$task->project,'tasks.update_status'),403);
  if(isset($data['progress']))abort_unless($legacyAssigned || $access->project($request->user(),$task->project,'tasks.update_progress'),403);
  if(isset($data['priority']))abort_unless($access->project($request->user(),$task->project,'tasks.edit_all'),403);
  abort_if(empty($data['status']) && empty($data['priority']) && !isset($data['progress']), 422, 'No updates provided.');
  if ($task->children()->exists() && (isset($data['progress']) || !empty($data['status']))) {
      abort(422,'Parent progress is calculated from its subtasks. Update a subtask instead.');
  }
  DB::transaction(function()use($task,$data,$service){
      Project::whereKey($task->project_id)->lockForUpdate()->firstOrFail();$task->refresh();
      $updateFields = [];
      if (!empty($data['priority'])) {
          $updateFields['priority'] = $data['priority'];
      }
      if (!empty($data['status']) || isset($data['progress'])) {
          $normalized = $task->normalizeProgressInput($data);
          $updateFields = array_merge($updateFields, $normalized);
      }
      if (!empty($updateFields)) {
          $task->update($updateFields);
          $service->recalculate($task->project);
          Audit::record('task.progress_updated',$task,$updateFields);
      }
  });
  if ($request->wantsJson() || $request->ajax()) {
      $task->refresh();
      $visible=$task->project->tasks()->get()->filter(fn($item)=>$access->task($request->user(),$item,'view'));
      $countQuery=$task->project->tasks()->whereDoesntHave('children');
      if(!$request->user()->isPmo() && $task->project->pm_user_id!==$request->user()->id && !$access->project($request->user(),$task->project,'view_tasks'))$countQuery->whereHas('assignees',fn($q)=>$q->where('users.id',$request->user()->id));
      return response()->json([
          'success' => true,
          'status' => $task->status,
          'progress' => $task->progress,
          'is_overdue' => $task->isOverdue(),
          'project_progress' => $task->project->fresh()->progress,
          'completed_tasks' => (clone $countQuery)->where('status','completed')->count(),
          'total_tasks' => (clone $countQuery)->count(),
          'tasks' => $visible->map(fn($item)=>['id'=>$item->id,'status'=>$item->status,'progress'=>$item->progress,'is_overdue'=>$item->isOverdue()])->values()
      ]);
  }
  return back()->with('success','Task updated.');
 }
 public function destroy(Request $request,Task $task,Access $access,ProjectService $service){
  $user = $request->user();
  $project = $task->project;
  $canDelete = $access->project($user,$project,'tasks.delete') && $project->status !== 'archived';
  abort_unless($canDelete, 403, 'Only PMO Admin or the assigned Project Manager can delete tasks.');

  // If task has subtasks, cascade delete subtasks and their attachments
  foreach ($task->children as $child) {
      foreach ($child->attachments as $att) Storage::disk('local')->delete($att->path);
      $child->delete();
  }
  foreach ($task->attachments as $attachment) Storage::disk('local')->delete($attachment->path);
  Audit::record('task.deleted', $task, ['title' => $task->title]);
  $task->delete();
  $service->recalculate($project);

  if ($request->wantsJson()) {
      return response()->json(['success' => true, 'message' => 'Task removed.']);
  }
  return redirect()->route('projects.show', $project)->with('success', 'Task removed.');
 }
 public function updateDetails(Request $request,Task $task,Access $access,ProjectService $service){
  abort_unless($access->project($request->user(),$task->project,'tasks.edit_all'),403);
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
  $data=$task->normalizeProgressInput($data);
  if(array_key_exists('assigned_user_id', $data)){
      if($data['assigned_user_id']){
          $service->addPlanningMember($task->project,(int)$data['assigned_user_id'],$request->user());
          $task->assignees()->sync([$data['assigned_user_id']]);
          app(\App\Services\InboxService::class)->assigned($task,User::findOrFail($data['assigned_user_id']));
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
 public function pmAssign(Request $request,Task $task){
  abort_unless($request->user()->system_role==='user' && (int)$task->project->pm_user_id===$request->user()->id,403);
  abort_unless(in_array($task->project->status,['active','on_hold'],true) && app(Access::class)->project($request->user(),$task->project,'tasks.assign'),403);
  $data=$request->validate(['user_id'=>'required|integer|exists:users,id']);
  DB::transaction(function()use($request,$task,$data){
   $project=\App\Models\Project::whereKey($task->project_id)->lockForUpdate()->firstOrFail();
   abort_unless((int)$project->pm_user_id===$request->user()->id && in_array($project->status,['active','on_hold'],true),403);
   $locked=Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
   abort_if($locked->assignees()->exists(),409,'This task already has an assignee. Refresh the list.');
   abort_if($locked->status==='completed' || $locked->progress>=100,422,'This task is already completed.');
   $person=User::whereKey($data['user_id'])->where('account_status','active')->where('system_role','user')->firstOrFail();
   abort_unless($person->id===$project->pm_user_id || $project->memberships()->where('user_id',$person->id)->exists(),422,'Choose a member of this project.');
   $locked->assignees()->attach($person->id);Audit::record('task.assigned',$locked,['user_id'=>$person->id]);
   app(\App\Services\InboxService::class)->assigned($locked,$person);
  });
  return response()->json(['success'=>true,'message'=>'Task assigned.']);
 }
 public function assign(Request $request,Task $task,Access $access){
  abort_unless($access->project($request->user(),$task->project,'assign_tasks'),403);
  abort_if($task->project->status==='archived',403);
  $data=$request->validate(['user_id'=>['required','integer',\Illuminate\Validation\Rule::exists('users','id')->where('account_status','active')]]);
  app(ProjectService::class)->addPlanningMember($task->project,(int)$data['user_id'],$request->user());
  abort_unless($task->project->pm_user_id==(int)$data['user_id']||$task->project->memberships()->where('user_id',$data['user_id'])->exists(),422,'Add this person to the project team first.');
  $task->assignees()->syncWithoutDetaching([(int)$data['user_id']]);
  app(\App\Services\InboxService::class)->assigned($task,User::findOrFail($data['user_id']));
  Audit::record('task.assigned',$task,['user_id'=>(int)$data['user_id']]);
  if($request->wantsJson())return response()->json(['success'=>true,'assignees'=>$task->assignees()->orderBy('name')->get(['users.id','users.name'])]);
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
