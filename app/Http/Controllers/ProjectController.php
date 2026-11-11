<?php
namespace App\Http\Controllers;
use App\Models\{Project,User,ProjectTemplate,ProjectRole,ProjectMembership,ProjectInvitation,Subsidiary};
use App\Services\{ProjectService,Access,Audit,TaskTree,ProjectHealthService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ProjectController {
 public function index(Request $request,Access $access){
  $user = $request->user();
  $query=Project::withSum('expenses','amount')->with(['manager','subsidiary','memberships.role'])->withCount([
      'tasks'=>fn($q)=>$q->whereDoesntHave('children'),
      'tasks as completed_tasks_count'=>fn($q)=>$q->where('status','completed')->whereDoesntHave('children'),
      'tasks as overdue_tasks_count'=>fn($q)=>$q->where('progress','<',100)->where('ends_at','<',now()),
      'tasks as open_delays_count'=>fn($q)=>$q->whereHas('openDelay'),
  ]);

  // Draft projects are strictly visible ONLY to PMO admin
  if(!$user->canViewPortfolio()){
    $query->where('status', '!=', 'draft');
  }

  if(!$user->canViewPortfolio() && !$access->hasCompanyPermission($user, 'projects.view_all')){
    $query->where(function($q) use ($user) {
        $q->where('pm_user_id', $user->id)->orWhere('owner_user_id',$user->id)
          ->orWhereHas('memberships', fn($m) => $m->where('user_id', $user->id))
          ->orWhereHas('tasks.assignees', fn($a) => $a->where('users.id', $user->id));
    })->where(function($q) use ($user) {
        $q->whereIn('status', ['active', 'on_hold', 'completed', 'archived'])
          ->orWhere(fn($sub) => $sub->where(fn($lead)=>$lead->where('pm_user_id',$user->id)->orWhere('owner_user_id',$user->id))->whereIn('status',['pending','declined']));
    });

    // Role scope filtering (Managed by Me, Assigned Tasks, View-Only)
    if($request->filled('scope')){
        $scope = $request->input('scope');
        if($scope === 'managed'){
            $query->where('pm_user_id', $user->id);
        } elseif($scope === 'assigned'){
            $query->whereHas('tasks.assignees', fn($a) => $a->where('users.id', $user->id));
        } elseif($scope === 'view_only'){
            $query->where('pm_user_id', '!=', $user->id)
                  ->whereHas('memberships', fn($m) => $m->where('user_id', $user->id));
        }
    }
  }

  $baseQuery = clone $query;
  $totalCount = (clone $baseQuery)->count();
  $activeCount = (clone $baseQuery)->where('status','active')->count();
  $completedCount = (clone $baseQuery)->where('status','completed')->count();
  $pendingCount = (clone $baseQuery)->where('status','pending')->count();
  $declinedCount = (clone $baseQuery)->where('status','declined')->count();
  $draftCount = $user->canViewPortfolio() ? (clone $baseQuery)->where('status','draft')->count() : 0;
  $needsAttention = function($q) use ($user) {
      $q->whereIn('status', $user->canViewPortfolio() ? ['pending','declined','draft'] : ['pending','declined'])
        ->orWhere(fn($active) => $active->whereIn('status',['active','on_hold'])->where(function($delivery) {
            $delivery->whereDate('ends_on','<',today())
                ->orWhereHas('tasks',fn($task)=>$task->where('progress','<',100)->where('ends_at','<',now()))
                ->orWhereHas('tasks',fn($task)=>$task->whereHas('openDelay'));
        }));
  };
  $attentionCount = (clone $baseQuery)->where($needsAttention)->count();
  if($request->boolean('attention')) $query->where($needsAttention);
  if($request->filled('q')){ 
   $search=trim($request->input('q')); 
   $query->where(fn($q)=>$q->where('name','like','%'.$search.'%')->orWhere('code','like','%'.$search.'%')->orWhereHas('manager',fn($m)=>$m->where('name','like','%'.$search.'%'))); 
  }
  if($request->filled('subsidiary_id')){
   $query->where('subsidiary_id', $request->subsidiary_id);
  }
  if($request->filled('status')){
   $query->where('status',$request->status);
  }

  $subsidiaries = Subsidiary::where('status', 'active')->orderBy('name')->get();
  $paginated = $query->latest()->paginate(15)->withQueryString();
  $now = now();

  $paginated->getCollection()->transform(function($p) use ($now) {
      $deadline = $p->ends_on->copy()->endOfDay();
      $isPastDeadline = $deadline->lessThan($now) && in_array($p->status, ['active', 'on_hold'], true);
      $delayDays = $isPastDeadline ? max(1, (int) $deadline->diffInWeekdays($now)) : 0;

      $p->is_delayed = $isPastDeadline;
      $p->is_at_risk = !$isPastDeadline && in_array($p->status, ['active', 'on_hold'], true) && ($p->overdue_tasks_count > 0 || ($p->open_delays_count ?? 0) > 0);
      $p->delay_working_days = $delayDays;

      $p->health_status = match(true) {
          $p->status === 'completed' => 'completed',
          $p->status === 'archived' => 'archived',
          $p->status === 'declined' => 'declined',
          !in_array($p->status, ['active', 'on_hold'], true) => 'draft',
          $isPastDeadline => 'delayed',
          $p->is_at_risk => 'at_risk',
          $p->tasks_count === 0 => 'insufficient_data',
          default => 'on_track',
      };
      return $p;
  });

  return view('projects.index',[
    'projects'=>$paginated,
    'subsidiaries'=>$subsidiaries,
    'totalCount'=>$totalCount,
    'activeCount'=>$activeCount,
    'completedCount'=>$completedCount,
    'pendingCount'=>$pendingCount,
    'declinedCount'=>$declinedCount,
    'draftCount'=>$draftCount,
    'attentionCount'=>$attentionCount,
    'access'=>$access,
  ]);
 }
 public function create(Request $request, Access $access){
  if(!$request->filled('draft') && $request->user()->isPmo())return app(ProjectWorkflowController::class)->create($request);
  abort_unless($request->user()->isPmo() || $access->hasCompanyPermission($request->user(), 'projects.create'), 403, 'Permission denied: cannot create projects.');
  $draftProject=null;
  $projectDefaults=[];
  if($request->filled('draft')){
   $request->validate(['draft'=>'required|integer|exists:projects,id']);
   $draftProject=Project::with('memberships')->findOrFail($request->integer('draft'));
   $this->authorizeDraft($request,$draftProject,$access);
   $projectDefaults=[
    'planned_budget'=>$draftProject->planned_budget,'name'=>$draftProject->name,'description'=>$draftProject->description,'subsidiary_id'=>$draftProject->subsidiary_id,
    'starts_on'=>$draftProject->starts_on->format('Y-m-d'),'ends_on'=>$draftProject->ends_on->format('Y-m-d'),
    'pm_user_id'=>$draftProject->pm_user_id,'mode'=>$draftProject->project_template_id?'template':'blank','project_template_id'=>$draftProject->project_template_id,
    'members'=>$draftProject->memberships->where('user_id','!=',$draftProject->pm_user_id)->map(fn($member)=>['user_id'=>$member->user_id,'project_role_id'=>$member->project_role_id])->values()->all(),
   ];
  }
  $subsidiaries = Subsidiary::where('status','active')->withCount('projects')->orderBy('name')->get();
  $templates = ProjectTemplate::where(fn($q)=>$q->where('status','active')->when($draftProject?->project_template_id,fn($q)=>$q->orWhere('id',$draftProject->project_template_id)))->with(['tasks.suggestedRole'])->orderBy('name')->get();

  $templatesCache = $templates->mapWithKeys(function($t) {
      return [
          $t->id => [
              'template' => [
                  'id' => $t->id,
                  'name' => $t->name,
                  'description' => $t->description,
                  'category' => $t->category,
                  'estimated_duration' => $t->estimated_duration ?: 14,
                  'duration_unit' => $t->duration_unit ?: 'working_days',
                  'reference_start_date' => $t->reference_start_date?->format('Y-m-d') ?: date('Y-m-d'),
                  'working_hours' => $t->working_hours ?: '09:00 - 17:00',
                  'planning_periods' => $t->planning_periods ?? [],
                  'status' => $t->status,
              ],
              'tasks' => $t->tasks->map(function($task) {
                  return [
                      'id' => $task->id,
                      'code' => $task->code,
                      'parent_code' => $task->parent_code,
                      'title' => $task->title,
                      'description' => $task->description,
                      'start_date' => $task->start_date?->format('Y-m-d'),
                      'start_time' => $task->start_time ?: '09:00',
                      'end_date' => $task->end_date?->format('Y-m-d'),
                      'end_time' => $task->end_time ?: '17:00',
                      'duration_days' => $task->duration_days ?: 1,
                      'suggested_role_id' => $task->suggested_role_id,
                      'suggested_role_name' => $task->suggestedRole?->name,
                      'dependency_code' => $task->dependency_code,
                      'priority' => $task->priority ?: 'medium',
                  ];
              })->values()->toArray(),
          ]
      ];
  })->toArray();

  return view('projects.create',[
    'users'=>User::where('account_status','active')->where('system_role','user')->with('subsidiary')->orderBy('name')->get(),
    'templates'=>$templates,
    'templatesCache'=>$templatesCache,
    'draftProject'=>$draftProject,
    'projectDefaults'=>$projectDefaults,
    'roles'=>ProjectRole::where('slug','!=','pm')->orderBy('name')->get(),
    'subsidiaries'=>$subsidiaries,
    'defaultSubsidiary'=>$subsidiaries->first(),
  ]);
 }

 public function templateSchedule(Request $request, \App\Services\TemplateScheduleService $scheduleService){
  $validated=$request->validate(['template_id'=>'required|integer|exists:project_templates,id','starts_on'=>'nullable|date']);
  $template = ProjectTemplate::active()->with('tasks')->findOrFail($validated['template_id']);
  $startsOn = $validated['starts_on'] ?? date('Y-m-d');
  return response()->json($scheduleService->calculate($template, $startsOn));
 }

 public function store(Request $request,ProjectService $service, Access $access){
  abort_unless($request->user()->isPmo() || $access->hasCompanyPermission($request->user(), 'projects.create'), 403, 'Permission denied: cannot create projects.');
  $isDraft = $request->input('action') === 'draft';

  $data=$this->projectCreationInput($request);

  $data['status_override'] = $isDraft ? 'draft' : null;
  $project=$service->create($data,$request->user());

  if ($project->status === 'pending' && $project->manager) {
    return redirect()->route('projects.show',$project)->with('success',"Project created with code {$project->code}. PM invitation sent to {$project->manager->name}.");
  }

  return redirect()->route('projects.show',$project)->with('success',"Project saved as Draft with code {$project->code}.");
 }
 public function saveDraft(Request $request,Project $project,ProjectService $service,Access $access){
  $this->authorizeDraft($request,$project,$access);
  $data=$this->projectCreationInput($request);
  $data['status_override']=$request->input('action')==='draft'?'draft':null;
  $data['replace_draft_plan']=$request->boolean('replace_draft_plan');
  $saved=$service->resumeDraft($project,$data,$request->user());
  return redirect()->route('projects.show',$saved)->with('success',$saved->status==='pending'?'Project created. The manager has been invited to review and accept it.':'Draft changes saved.');
 }
 private function authorizeDraft(Request $request,Project $project,Access $access):void {
  abort_unless($request->user()->isPmo() || ($project->created_by===$request->user()->id && $access->hasCompanyPermission($request->user(),'projects.create')),403);
  abort_unless($project->status==='draft',409,'This draft has already been submitted. Open the project to see its current status.');
 }
 private function projectCreationInput(Request $request):array {
  $isDraft=$request->input('action')==='draft';
  $data=$request->validate([
    'name'=>'required|string|max:160',
    'subsidiary_id'=>'required|exists:subsidiaries,id',
    'description'=>'nullable|string|max:3000',
    'planned_budget'=>'nullable|numeric|decimal:0,2|min:0|max:9999999999999.99',
    'starts_on'=>'required|date',
    'ends_on'=>'required|date|after_or_equal:starts_on',
    'mode'=>'required|in:blank,template',
    'project_template_id'=>'required_if:mode,template|nullable|exists:project_templates,id',
    'pm_user_id'=> $isDraft ? 'nullable|exists:users,id' : 'required|exists:users,id',
    'members'=>'nullable|array|max:50',
    'members.*.user_id'=>'nullable|exists:users,id',
    'members.*.project_role_id'=>'nullable|exists:project_roles,id'
  ], [
    'pm_user_id.required' => 'A Project Manager is required to submit for acceptance, or click "Save as Draft" to save without assigning a PM.',
    'subsidiary_id.required' => 'Please select a subsidiary for project numbering.',
    'ends_on.after_or_equal' => 'The project deadline cannot be earlier than the start date.',
  ]);

  if($data['mode']==='blank')$data['project_template_id']=null;
  if(!empty($data['pm_user_id'])) {
    abort_unless(User::whereKey($data['pm_user_id'])->where('account_status','active')->where('system_role','user')->exists(), 422, 'Selected user must be active.');
  }

  return $data;
 }
 public function show(Project $project,Request $request,Access $access,ProjectHealthService $healthService){
  abort_unless($access->project($request->user(),$project),403);
  $user = $request->user();
  $isPm = $project->pm_user_id === $user->id;
  $canViewAllTasks = $user->canViewPortfolio() || ($access->project($user, $project, 'view_tasks') && in_array($project->status, ['active', 'on_hold', 'completed', 'archived'], true));

  if ($canViewAllTasks) {
      $tasks = $project->tasks()->with(['assignees', 'children', 'openDelay.actionOwner', 'attachments', 'comments', 'dependencies:id,code,title'])->orderBy('sort_order')->orderBy('id')->get();
      $completedCount = $project->tasks()->whereDoesntHave('children')->where('status', 'completed')->count();
      $totalCount = $project->tasks()->whereDoesntHave('children')->count();
  } else {
      // Scoped tasks for Team Members: strictly only tasks assigned to this user
      $tasks = $project->tasks()
          ->whereHas('assignees', fn($q) => $q->where('users.id', $user->id))
          ->with(['assignees', 'children', 'openDelay.actionOwner', 'attachments', 'comments', 'dependencies:id,code,title'])
          ->orderBy('sort_order')
          ->orderBy('id')
          ->get();
      $completedCount = $tasks->where('status', 'completed')->count();
      $totalCount = $tasks->count();
  }

  $tasks = TaskTree::treeOrdered($tasks);

  $health = $canViewAllTasks ? $healthService->assess($project) : null;
  $remainingCount = max(0, $totalCount - $completedCount);

  $files = $canViewAllTasks 
      ? \App\Models\TaskAttachment::with('task')->whereHas('task', fn($q) => $q->where('project_id', $project->id))->latest()->get() 
      : \App\Models\TaskAttachment::with('task')->whereHas('task', fn($q) => $q->where('project_id', $project->id)->whereHas('assignees', fn($a) => $a->where('users.id', $user->id)))->latest()->get();

  $delays = $canViewAllTasks 
      ? \App\Models\TaskDelay::with(['task', 'actionOwner', 'reporter'])->whereHas('task', fn($q) => $q->where('project_id', $project->id))->latest()->get() 
      : \App\Models\TaskDelay::with(['task', 'actionOwner', 'reporter'])->whereHas('task', fn($q) => $q->where('project_id', $project->id)->whereHas('assignees', fn($a) => $a->where('users.id', $user->id)))->latest()->get();

  $activities = \App\Models\ActivityLog::with('actor')
      ->where(function($q) use ($project, $tasks) {
          $q->where('subject_type', Project::class)->where('subject_id', $project->id);
          if ($tasks->isNotEmpty()) {
              $q->orWhere(function($sub) use ($tasks) {
                  $sub->where('subject_type', Task::class)->whereIn('subject_id', $tasks->pluck('id'));
              });
          }
      })->latest()->take(25)->get();

  $declinedInvitation = $project->invitations()->where('status', 'declined')->latest('id')->with('user')->first();
  $pendingInvitation = $project->invitations()->where('status', 'pending')->latest('id')->with('user')->first();
  $pmCandidates = User::where('account_status','active')->where('system_role','!=','pmo')->with('subsidiary')->orderBy('name')->get();

  return view('projects.show',[
      'project'=>$project->load('manager','subsidiary','memberships.user','memberships.role','template','creator'),
      'tasks'=>$tasks,
      'numbers'=>TaskTree::numbers($tasks),
      'health'=>$health,
      'completedCount'=>$completedCount,
      'totalCount'=>$totalCount,
      'remainingCount'=>$remainingCount,
      'files'=>$files,
      'delays'=>$delays,
      'activities'=>$activities,
      'canViewTasks'=>$canViewAllTasks || $tasks->isNotEmpty(),
      'canViewAllTasks'=>$canViewAllTasks,
      'isScopedTeamMember'=>!$canViewAllTasks,
      'canManage'=>$access->canManageTask($user,$project) && ($user->isPmo() || in_array($project->status, ['draft','pending','active','on_hold'], true)) && $project->status!=='archived',
      'canTeam'=>$access->project($user,$project,'manage_team'),
      'users'=>User::where('account_status','active')->where('system_role','user')->orderBy('name')->get(),
      'pmCandidates'=>$pmCandidates,
      'roles'=>ProjectRole::orderBy('name')->get(),
      'declinedInvitation'=>$declinedInvitation,
      'pendingInvitation'=>$pendingInvitation,
  ]);
 }
 public function update(Request $request,Project $project,Access $access){
  abort_unless($access->project($request->user(),$project,'edit_project'),403);
  abort_if($project->status==='archived',403);
  $data=$request->validate(['name'=>'required|string|max:160','description'=>'nullable|string|max:3000','starts_on'=>'required|date','ends_on'=>'required|date|after_or_equal:starts_on','status'=>'required|in:draft,pending,declined,active,on_hold,completed,archived','planned_budget'=>'sometimes|nullable|numeric|decimal:0,2|min:0|max:9999999999999.99']);
  if($project->approval_stage==='planning' && $data['status']!=='active')abort(422,'Save and publish the task plan before changing the project lifecycle status.');
  if(array_key_exists('planned_budget',$data))abort_unless($project->canManageBudget($request->user()),403);
  if(!$request->user()->isPmo() && $project->status==='pending')abort(403);
  if(in_array($project->status,['draft','pending','declined'],true) && $data['status']!==$project->status)abort(422,'Only PM acceptance can activate a project.');
  if(!in_array($project->status,['draft','pending','declined'],true)&&in_array($data['status'],['draft','pending','declined'],true))abort(422,'Cannot revert an active project to an invitation state.');
  if($request->exists('project_template_id') && (int)$request->input('project_template_id')!==(int)$project->project_template_id){
   throw \Illuminate\Validation\ValidationException::withMessages(['project_template_id'=>'The original project template cannot be changed.']);
  }
  $extra=$request->validate([
   'project_template_id'=>['sometimes','nullable',\Illuminate\Validation\Rule::exists('project_templates','id')->where(fn($q)=>$q->where('status','active')->orWhere('id',$project->project_template_id))],
   'pm_user_id'=>'sometimes|nullable|integer|exists:users,id','edit_team'=>'sometimes|in:1','members'=>'nullable|array|max:100',
   'members.*.user_id'=>'required|integer|distinct|exists:users,id',
   'members.*.project_role_id'=>['required','integer',\Illuminate\Validation\Rule::exists('project_roles','id')->where(fn($q)=>$q->where('slug','!=','pm'))],
  ]);
  if($project->approval_stage && isset($extra['pm_user_id']) && (int)$extra['pm_user_id']!==(int)$project->pm_user_id)abort(422,'Use the assignment request controls to change the Project Manager.');
  $newPm=isset($extra['pm_user_id']) && (int)$extra['pm_user_id']!==(int)$project->pm_user_id ? User::findOrFail($extra['pm_user_id']) : null;
  if($newPm){
   abort_unless($project->status==='declined' || ($project->status==='draft' && !$project->pm_user_id),409,'Reassignment is available only after rejection.');
   abort_unless($access->project($request->user(),$project,'manage_team'),403);
   abort_unless(in_array($project->status,['draft','pending','declined','on_hold'],true),422,'Manager reassignment is available before activation or while on hold.');
   abort_unless($newPm->isActive() && $newPm->system_role==='user',422,'Choose an active project user as manager.');
  }
  $editTeam=$request->boolean('edit_team');
  if($editTeam){
   abort_unless($access->project($request->user(),$project,'manage_team'),403);
   foreach($extra['members']??[] as $member){
    abort_if((int)$member['user_id']===$project->pm_user_id,422,'The project manager must be changed through the manager assignment workflow.');
    $existing=$project->memberships()->where('user_id',$member['user_id'])->exists();
    abort_unless(User::whereKey($member['user_id'])->where('system_role','user')->when(!$existing,fn($q)=>$q->where('account_status','active'))->exists(),422,'Choose an active project user.');
   }
  }
  DB::transaction(function()use($project,$data,$extra,$editTeam,$newPm){
   Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
   $project->update($data);
   if($editTeam){
    $members=collect($extra['members']??[]);
    $kept=$members->pluck('user_id')->push($project->pm_user_id)->push($project->owner_user_id)->filter()->all();
    $assigned=DB::table('task_assignments')->whereIn('task_id',$project->tasks()->select('id'))->pluck('user_id');
    $removed=$project->memberships()->pluck('user_id')->merge($assigned)->unique()->diff($kept)->all();
    $project->memberships()->whereNotIn('user_id',$kept)->delete();
    DB::table('task_assignments')->whereIn('task_id',$project->tasks()->select('id'))->whereIn('user_id',$removed)->delete();
    foreach($members->reject(fn($member)=>$project->approval_stage && (int)$member['user_id']===(int)$project->owner_user_id) as $member)ProjectMembership::updateOrCreate(['project_id'=>$project->id,'user_id'=>$member['user_id']],['project_role_id'=>$member['project_role_id']]);
   }
   if($newPm)app(ProjectService::class)->assignManager($project,$newPm);
   Audit::record('project.updated',$project);
  });
  if($request->expectsJson())return response()->json(['message'=>'Project updated.']);
  return back()->with('success','Project updated.');
 }
 public function captureBaseline(Request $request, Project $project, ProjectService $service, Access $access){
  abort_unless($access->project($request->user(),$project,'edit_project'),403);
  abort_unless(in_array($project->status,['active','on_hold'],true),422,'Activate the project before saving a baseline.');
  abort_if($project->baseline_captured_at,422,'The original baseline is already saved and cannot be silently overwritten.');
  $service->captureBaseline($project);
  Audit::record('project.baseline_saved',$project);
  return back()->with('success','Original schedule baseline saved. Changes can now be compared to it.');
 }
 public function assignPm(Request $request,Project $project,ProjectService $service){
  if($project->approval_stage){$request->merge(['assignment_type'=>'pm','assignee_id'=>$request->input('pm_user_id')]);return app(ProjectWorkflowController::class)->reassign($request,$project,$service);}
  abort_unless($request->user()->isPmo() || app(Access::class)->hasCompanyPermission($request->user(), 'projects.create') || app(Access::class)->project($request->user(), $project, 'manage_team'), 403, 'Permission denied.');
  abort_unless($project->status==='declined' || ($project->status==='draft' && !$project->pm_user_id),409,'Reassignment is available only after rejection.');
  $data=$request->validate(['pm_user_id'=>'required|exists:users,id']);
  $user=User::whereKey($data['pm_user_id'])->where('account_status','active')->firstOrFail();
  abort_if(in_array($project->status,['active','completed','archived'],true),422,'Cannot reassign an active or closed project here.');
  DB::transaction(fn()=>$service->assignManager($project,$user));
  Audit::record('project.manager_assigned',$project,['user_id'=>$user->id]);
  return redirect()->route('projects.show',$project)->with('success',"Project Manager reassigned to {$user->name}. Assignment request sent.");
 }
 public function approvalReview(Request $request,Project $project){
  abort_unless($request->user()->system_role==='user' && $project->pm_user_id===$request->user()->id,403);
  abort_unless($project->status==='pending',409,'This request is no longer awaiting a decision. Refresh your dashboard.');
  $invitation=$project->invitations()->where('user_id',$request->user()->id)->where('status','pending')->latest('id')->firstOrFail();
  $project->load('manager','creator','subsidiary','template','memberships.user','memberships.role');
  $tasks=TaskTree::treeOrdered($project->tasks()->with('assignees','dependencies')->withCount('children')->orderBy('sort_order')->orderBy('id')->get());
  $numbers=TaskTree::numbers($tasks);$leafCount=$tasks->where('children_count',0)->count();
  $files=\App\Models\TaskAttachment::with('task:id,title')->whereIn('task_id',$tasks->pluck('id'))->latest()->get();
  return response()->view('projects._approval-review',compact('project','invitation','tasks','numbers','leafCount','files'))->header('Cache-Control','private, no-store');
 }
 public function respond(Request $request,Project $project,ProjectService $service){
  if($project->approval_stage)return app(ProjectWorkflowController::class)->pmRespond($request,$project,$service);
  abort_unless($project->pm_user_id===$request->user()->id,403);
  $data=$request->validate([
      'decision'=>'required|in:accept,decline',
      'decline_reason'=>$request->input('decision')==='decline' ? 'required|string|min:5|max:1000' : 'nullable|string|max:1000'
  ], [
      'decline_reason.required' => 'Please provide a reason for declining the project assignment so PMO can reassign appropriately.',
      'decline_reason.min' => 'Please provide a detailed decline reason (at least 5 characters).',
  ]);
  DB::transaction(function()use($project,$data,$request,$service){
   $locked=Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
   abort_unless($locked->pm_user_id===$request->user()->id,403,'This assignment has changed. Refresh your dashboard.');
   abort_unless($locked->status==='pending',404,'This request has already been answered.');
   $project->setRawAttributes($locked->getAttributes(),true);
   $invitation=ProjectInvitation::where('project_id',$project->id)->where('user_id',$request->user()->id)->where('status','pending')->lockForUpdate()->latest('id')->firstOrFail();
   $status=$data['decision']==='accept'?'active':'declined';
   $project->update(['status'=>$status]);
   if($status==='active' && !$project->baseline_captured_at)$service->captureBaseline($project);
   $invitation->update(['status'=>$data['decision']==='accept'?'accepted':'declined','responded_at'=>now(),'decline_reason'=>$data['decline_reason']??null]);
   if($data['decision']==='accept')DB::afterCommit(function()use($project){
    $project->load('manager');
    $people=User::where('account_status','active')->where('system_role','user')->where('id','!=',$project->pm_user_id)->whereHas('memberships',fn($q)=>$q->where('project_id',$project->id))->get();
    foreach($people as $person)$person->notify(new \App\Notifications\ProjectActivatedNotification($project));
   });
  });
  foreach(User::where('system_role','pmo')->where('account_status','active')->get() as $pmo)app(\App\Services\InboxService::class)->put($pmo,'pm-response:'.$project->id.':'.$project->updated_at->getTimestamp(),['title'=>$data['decision']==='accept'?'Project accepted by PM':'Project declined by PM','message'=>$request->user()->name.' '.($data['decision']==='accept'?'accepted':'declined').' '.$project->name.'. '.($data['decline_reason']??''),'project_name'=>$project->name,'action_label'=>'View project','url'=>route('projects.show',$project,false)]);
  Audit::record('project.pm_'.$data['decision'],$project, ['reason' => $data['decline_reason'] ?? null]);
  $message=$data['decision']==='accept'?'Project accepted and activated.':'Project declined. PMO can reassign it.';
  if($request->expectsJson()){
   $request->session()->flash('success',$message);
   return response()->json(['message'=>$message,'decision'=>$data['decision'],'redirect'=>route('dashboard').'#project-approvals']);
  }
  return redirect()->route('dashboard')->with('success',$message);
 }
 public function addMember(Request $request,Project $project,Access $access){
  abort_unless($access->project($request->user(),$project,'manage_team'),403);
  abort_if($project->status==='archived',403);
  $data=$request->validate(['user_id'=>'required|exists:users,id','project_role_id'=>'required|exists:project_roles,id']);
  $role=ProjectRole::findOrFail($data['project_role_id']);
  abort_if($role->slug==='pm',422,'Assign a PM through the PM assignment workflow.');
  abort_if($project->pm_user_id==(int)$data['user_id'] || ($project->approval_stage && (int)$project->owner_user_id===(int)$data['user_id']),422,'Project leadership is managed through the assignment workflow.');
  abort_unless(User::whereKey($data['user_id'])->where('account_status','active')->where('system_role','user')->exists(),422);
  ProjectMembership::updateOrCreate(['project_id'=>$project->id,'user_id'=>$data['user_id']],['project_role_id'=>$role->id]);
  Audit::record('project.member_added',$project,['user_id'=>(int)$data['user_id'],'project_role_id'=>$role->id]);
  return redirect()->to(route('projects.show',$project).'#team')->with('success','Project member added.');
 }
 public function destroy(Request $request,Project $project){
  abort_unless($request->user()->isPmo(),403);
  $request->validate(['confirm_delete'=>'required|accepted']);
  DB::transaction(function()use($project){
   $project=Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
   $paths=\App\Models\TaskAttachment::whereIn('task_id',$project->tasks()->select('id'))->pluck('path')->all();
   Audit::record('project.deleted',$project,['name'=>$project->name,'code'=>$project->code]);
   $project->delete();
   DB::afterCommit(function()use($paths){if($paths)\Illuminate\Support\Facades\Storage::disk('local')->delete($paths);});
  });
  return redirect()->route('projects.index')->with('success','Project deleted.');
 }
 public function removeMember(Request $request,Project $project,User $user,Access $access){
  abort_unless($access->project($request->user(),$project,'manage_team'),403);
  abort_if($user->id===$project->pm_user_id || ($project->approval_stage && $user->id===$project->owner_user_id),422);
  ProjectMembership::where('project_id',$project->id)->where('user_id',$user->id)->delete();
  // Remove assignments to avoid orphaned task access.
  DB::table('task_assignments')->where('user_id',$user->id)->whereIn('task_id',function($q)use($project){$q->select('id')->from('tasks')->where('project_id',$project->id);})->delete();
  return back()->with('success','Member removed.');
 }
}
