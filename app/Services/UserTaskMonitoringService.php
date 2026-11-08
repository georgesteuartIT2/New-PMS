<?php

namespace App\Services;

use App\Models\{Project, Task, User, TaskUpdateRequest, TaskAssignmentHistory, ProjectMembership};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserTaskMonitoringService
{
    public function __construct(
        private Access $access,
        private TaskMonitoringService $taskMonitoringService,
        private EmployeeWorkloadService $workloadService
    ) {}

    /**
     * Get all projects where the user has management or monitoring authority.
     */
    public function getMonitoredProjects(User $user): Collection
    {
        if ($user->isPmo()) {
            return Project::whereIn('status', ['active', 'on_hold', 'draft'])->orderBy('name')->get();
        }

        // 1. Projects where user is the assigned PM
        $pmProjects = Project::where('pm_user_id', $user->id)
            ->whereIn('status', ['active', 'on_hold'])
            ->get();

        // 2. Projects where user has project membership with monitoring permissions
        $memberProjects = Project::whereHas('memberships', fn($m) => $m->where('user_id', $user->id))
            ->whereIn('status', ['active', 'on_hold'])
            ->get()
            ->filter(function ($project) use ($user) {
                return $this->access->project($user, $project, 'tasks.view_all')
                    || $this->access->project($user, $project, 'tasks.edit_all')
                    || $this->access->project($user, $project, 'view_tasks')
                    || $this->access->project($user, $project, 'manage_tasks')
                    || $this->access->project($user, $project, 'team.view_all')
                    || $this->access->project($user, $project, 'project.team_view');
            });

        return $pmProjects->merge($memberProjects)->unique('id')->sortBy('name')->values();
    }

    /**
     * Get summary metrics for tasks assigned to the user.
     */
    public function getAssignedMetrics(User $user, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $base = Task::whereHas('assignees', fn($q) => $q->where('users.id', $user->id))
            ->whereHas('project', fn($q) => $q->whereIn('status', ['active', 'on_hold', 'completed']));

        $open = (clone $base)->where('status', '!=', 'completed')->where('progress', '<', 100)->count();
        $dueSoon = (clone $base)->where('status', '!=', 'completed')->where('progress', '<', 100)
            ->where('ends_at', '>=', $asOf)->where('ends_at', '<=', $asOf->copy()->addDays(7))->count();
        $overdue = (clone $base)->where('status', '!=', 'completed')->where('progress', '<', 100)
            ->where('ends_at', '<', $asOf)->count();
        $completed = (clone $base)->where(fn($q) => $q->where('status', 'completed')->orWhere('progress', 100))->count();

        return [
            'open' => $open,
            'due_soon' => $dueSoon,
            'overdue' => $overdue,
            'completed' => $completed,
        ];
    }

    /**
     * Get paginated tasks directly assigned to the user across projects.
     */
    public function getAssignedTasks(User $user, array $filters, int $perPage = 20, ?Carbon $asOf = null): LengthAwarePaginator
    {
        $asOf ??= now();
        $query = Task::with([
            'project:id,code,name,status',
            'parent:id,code,title',
            'assignees:id,name',
        ])
        ->whereHas('assignees', fn($q) => $q->where('users.id', $user->id))
        ->whereHas('project', fn($q) => $q->whereIn('status', ['active', 'on_hold', 'completed']));

        // Filter
        $filter = $filters['filter'] ?? 'open';
        if ($filter === 'open') {
            $query->where('status', '!=', 'completed')->where('progress', '<', 100);
        } elseif ($filter === 'due_soon') {
            $query->where('status', '!=', 'completed')->where('progress', '<', 100)
                  ->where('ends_at', '>=', $asOf)->where('ends_at', '<=', $asOf->copy()->addDays(7));
        } elseif ($filter === 'overdue') {
            $query->where('status', '!=', 'completed')->where('progress', '<', 100)
                  ->where('ends_at', '<', $asOf);
        } elseif ($filter === 'completed') {
            $query->where(fn($q) => $q->where('status', 'completed')->orWhere('progress', 100));
        } elseif ($filter === 'todo') {
            $query->where('status', 'todo')->where('progress', '<', 100);
        } elseif ($filter === 'in_progress') {
            $query->where('status', 'in_progress')->where('progress', '<', 100);
        }

        // Project filter
        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        // Search
        if (!empty($filters['q'])) {
            $search = trim($filters['q']);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhereHas('project', fn($p) => $p->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy('ends_at')->paginate($perPage)->withQueryString();
    }

    /**
     * Resolve authorized scoped project IDs.
     */
    public function resolveScopedProjectIds(User $user, ?int $selectedProjectId = null): array
    {
        $monitored = $this->getMonitoredProjects($user);
        if ($monitored->isEmpty()) {
            return [];
        }

        if ($selectedProjectId) {
            $found = $monitored->firstWhere('id', $selectedProjectId);
            return $found ? [$found->id] : [];
        }

        return $monitored->pluck('id')->all();
    }

    /**
     * Get summary metrics for Managed Tasks in scope.
     */
    public function getManagedTaskMetrics(User $user, ?int $projectId = null, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $scopedProjectIds = $this->resolveScopedProjectIds($user, $projectId);
        if (empty($scopedProjectIds)) {
            return [
                'total_tasks' => 0,
                'open_tasks' => 0,
                'overdue_tasks' => 0,
                'critical_tasks' => 0,
                'unassigned_tasks' => 0,
            ];
        }

        $base = Task::whereIn('project_id', $scopedProjectIds)
            ->whereDoesntHave('children')
            ->whereHas('project', fn($p) => $p->whereIn('status', ['active', 'on_hold', 'draft']));

        $total = (clone $base)->count();
        $open = (clone $base)->where('status', '!=', 'completed')->where('progress', '<', 100)->count();
        $overdue = (clone $base)->where('status', '!=', 'completed')->where('progress', '<', 100)->where('ends_at', '<', $asOf)->count();
        
        $criticalIds = $this->taskMonitoringService->getCriticalTaskIds($user);
        $critical = (clone $base)->whereIn('id', $criticalIds)->where('status', '!=', 'completed')->where('progress', '<', 100)->count();

        $unassigned = (clone $base)->whereDoesntHave('assignees')->where('status', '!=', 'completed')->where('progress', '<', 100)->count();

        return [
            'total_tasks' => $total,
            'open_tasks' => $open,
            'overdue_tasks' => $overdue,
            'critical_tasks' => $critical,
            'unassigned_tasks' => $unassigned,
        ];
    }

    /**
     * Get paginated tasks for Managed by Me -> Task Monitor.
     */
    public function getManagedTasks(User $user, array $filters, ?int $projectId = null, int $perPage = 20, ?Carbon $asOf = null): LengthAwarePaginator
    {
        $asOf ??= now();
        $scopedProjectIds = $this->resolveScopedProjectIds($user, $projectId);

        if (empty($scopedProjectIds)) {
            return new LengthAwarePaginator([], 0, $perPage, 1, ['path' => request()->url(), 'query' => request()->query()]);
        }

        $criticalIds = $this->taskMonitoringService->getCriticalTaskIds($user);

        $query = Task::with([
            'project:id,code,name,status,subsidiary_id',
            'parent:id,code,title',
            'assignees:id,name,email,job_title',
            'openDelay.actionOwner:id,name',
        ])
        ->whereIn('project_id', $scopedProjectIds)
        ->whereDoesntHave('children')
        ->whereHas('project', fn($p) => $p->whereIn('status', ['active', 'on_hold', 'draft']));

        // Filter
        $filter = $filters['filter'] ?? 'all';
        if ($filter === 'overdue') {
            $query->where('status', '!=', 'completed')->where('progress', '<', 100)->where('ends_at', '<', $asOf);
        } elseif ($filter === 'critical') {
            $query->whereIn('id', $criticalIds)->where('status', '!=', 'completed')->where('progress', '<', 100);
        } elseif ($filter === 'blocked') {
            $query->where('status', '!=', 'completed')->where('progress', '<', 100)->whereHas('openDelay');
        } elseif ($filter === 'unassigned') {
            $query->whereDoesntHave('assignees')->where('status', '!=', 'completed')->where('progress', '<', 100);
        } elseif ($filter === 'due_soon') {
            $query->where('status', '!=', 'completed')->where('progress', '<', 100)
                  ->where('ends_at', '>=', $asOf)->where('ends_at', '<=', $asOf->copy()->addDays(7));
        } elseif ($filter === 'needs_update') {
            $inactivityDays = 7;
            $cutoff = $asOf->copy()->subDays($inactivityDays);
            $hasCol = Schema::hasColumn('tasks', 'last_progress_updated_at');
            $query->where('status', '!=', 'completed')->where('progress', '<', 100)
                  ->where(function ($q) use ($cutoff, $hasCol) {
                      if ($hasCol) {
                          $q->where(function ($sub) use ($cutoff) {
                              $sub->whereNotNull('last_progress_updated_at')->where('last_progress_updated_at', '<=', $cutoff);
                          })->orWhere(function ($sub) use ($cutoff) {
                              $sub->whereNull('last_progress_updated_at')->where('updated_at', '<=', $cutoff);
                          });
                      } else {
                          $q->where('updated_at', '<=', $cutoff);
                      }
                  });
        } elseif ($filter === 'completed') {
            $query->where(fn($q) => $q->where('status', 'completed')->orWhere('progress', 100));
        }

        // Assigned user filter
        if (!empty($filters['user_id'])) {
            $query->whereHas('assignees', fn($q) => $q->where('users.id', $filters['user_id']));
        }

        // Task status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Search
        if (!empty($filters['q'])) {
            $search = trim($filters['q']);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhereHas('project', fn($p) => $p->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                  ->orWhereHas('assignees', fn($a) => $a->where('name', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->orderBy('ends_at')->paginate($perPage)->withQueryString();

        // Transform collection to attach accurate schedule health
        $paginator->getCollection()->transform(function ($task) use ($criticalIds, $asOf) {
            $isOverdue = ($task->progress < 100 && $task->ends_at->lessThan($asOf));
            $isCritical = in_array($task->id, $criticalIds, true);
            $hasBlocker = ($task->openDelay !== null);

            $healthKey = match(true) {
                $task->status === 'completed' || $task->progress >= 100 => 'completed',
                $hasBlocker => 'blocked',
                $isCritical && $isOverdue => 'critical_overdue',
                $isOverdue => 'overdue',
                $isCritical => 'critical',
                $task->progress < 50 && $task->ends_at->diffInDays($asOf) <= 3 => 'at_risk',
                default => 'on_track',
            };

            $healthLabel = match($healthKey) {
                'completed' => 'Completed',
                'blocked' => 'Blocked',
                'critical_overdue' => 'Critical & Overdue',
                'overdue' => 'Overdue',
                'critical' => 'Critical Path',
                'at_risk' => 'At Risk',
                default => 'On Track',
            };

            $task->schedule_health = [
                'key' => $healthKey,
                'label' => $healthLabel,
                'is_critical' => $isCritical,
                'is_overdue' => $isOverdue,
                'is_blocked' => $hasBlocker,
            ];

            return $task;
        });

        return $paginator;
    }

    /**
     * Get summary metrics for Managed Team in scope.
     */
    public function getManagedTeamMetrics(User $user, ?int $projectId = null, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $scopedProjectIds = $this->resolveScopedProjectIds($user, $projectId);

        if (empty($scopedProjectIds)) {
            return [
                'project_members' => 0,
                'open_assignments' => 0,
                'completed_assignments' => 0,
                'overdue_assignments' => 0,
            ];
        }

        // Distinct employees in scope
        $memberUserIds = ProjectMembership::whereIn('project_id', $scopedProjectIds)->pluck('user_id');
        $pmUserIds = Project::whereIn('id', $scopedProjectIds)->whereNotNull('pm_user_id')->pluck('pm_user_id');
        $assignedUserIds = DB::table('task_assignments')
            ->join('tasks', 'task_assignments.task_id', '=', 'tasks.id')
            ->whereIn('tasks.project_id', $scopedProjectIds)
            ->pluck('task_assignments.user_id');
        $distinctEmployeeIds = $memberUserIds->merge($pmUserIds)->merge($assignedUserIds)->unique()->values();

        $activeEmployeesCount = User::whereIn('id', $distinctEmployeeIds)->where('account_status', 'active')->count();

        // Assignment counts in scope
        $assignmentsQuery = DB::table('task_assignments')
            ->join('tasks', 'task_assignments.task_id', '=', 'tasks.id')
            ->join('projects', 'tasks.project_id', '=', 'projects.id')
            ->join('users', 'task_assignments.user_id', '=', 'users.id')
            ->where('users.account_status', 'active')
            ->whereIn('projects.id', $scopedProjectIds)
            ->whereIn('projects.status', ['active', 'on_hold', 'draft'])
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('tasks as children')
                    ->whereColumn('children.parent_task_id', 'tasks.id');
            });

        $openAssignments = (clone $assignmentsQuery)
            ->where('tasks.status', '!=', 'completed')
            ->where('tasks.progress', '<', 100)
            ->count();

        $completedAssignments = (clone $assignmentsQuery)
            ->where(fn($q) => $q->where('tasks.status', 'completed')->orWhere('tasks.progress', 100))
            ->count();

        $overdueAssignments = (clone $assignmentsQuery)
            ->where('tasks.status', '!=', 'completed')
            ->where('tasks.progress', '<', 100)
            ->where('tasks.ends_at', '<', $asOf)
            ->count();

        return [
            'project_members' => $activeEmployeesCount,
            'open_assignments' => $openAssignments,
            'completed_assignments' => $completedAssignments,
            'overdue_assignments' => $overdueAssignments,
        ];
    }

    /**
     * Get paginated employees and their workload in Managed Team scope.
     */
    public function getManagedTeamMembers(User $user, array $filters, ?int $projectId = null, int $perPage = 20, ?Carbon $asOf = null): LengthAwarePaginator
    {
        $asOf ??= now();
        $scopedProjectIds = $this->resolveScopedProjectIds($user, $projectId);

        if (empty($scopedProjectIds)) {
            return new LengthAwarePaginator([], 0, $perPage, 1, ['path' => request()->url(), 'query' => request()->query()]);
        }

        $criticalIds = $this->taskMonitoringService->getCriticalTaskIds($user);

        // Distinct employees in scope
        $memberUserIds = ProjectMembership::whereIn('project_id', $scopedProjectIds)->pluck('user_id');
        $pmUserIds = Project::whereIn('id', $scopedProjectIds)->whereNotNull('pm_user_id')->pluck('pm_user_id');
        $assignedUserIds = DB::table('task_assignments')
            ->join('tasks', 'task_assignments.task_id', '=', 'tasks.id')
            ->whereIn('tasks.project_id', $scopedProjectIds)
            ->pluck('task_assignments.user_id');
        $distinctEmployeeIds = $memberUserIds->merge($pmUserIds)->merge($assignedUserIds)->unique()->values();

        $employeesQuery = User::whereIn('id', $distinctEmployeeIds)->where('account_status', 'active');

        // Search
        if (!empty($filters['q'])) {
            $search = trim($filters['q']);
            $hasJobTitle = Schema::hasColumn('users', 'job_title');
            $employeesQuery->where(function ($q) use ($search, $hasJobTitle) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
                if ($hasJobTitle) {
                    $q->orWhere('job_title', 'like', "%{$search}%");
                }
            });
        }

        $allEmployees = $employeesQuery->orderBy('name')->get();

        // Single project context?
        $singleProject = (count($scopedProjectIds) === 1) ? Project::find($scopedProjectIds[0]) : null;

        // Compute metrics for each employee within the scope
        $hasEstimatedCol = Schema::hasColumn('tasks', 'estimated_hours');

        $computedList = $allEmployees->map(function ($emp) use ($scopedProjectIds, $singleProject, $criticalIds, $asOf, $hasEstimatedCol) {
            // Tasks assigned to this employee within scope
            $tasksQuery = Task::whereIn('project_id', $scopedProjectIds)
                ->whereDoesntHave('children')
                ->whereHas('assignees', fn($q) => $q->where('users.id', $emp->id))
                ->whereHas('project', fn($p) => $p->whereIn('status', ['active', 'on_hold', 'draft']));

            $openTasksCount = (clone $tasksQuery)->where('status', '!=', 'completed')->where('progress', '<', 100)->count();
            $completedTasksCount = (clone $tasksQuery)->where(fn($q) => $q->where('status', 'completed')->orWhere('progress', 100))->count();
            $overdueTasksCount = (clone $tasksQuery)->where('status', '!=', 'completed')->where('progress', '<', 100)->where('ends_at', '<', $asOf)->count();
            $criticalTasksCount = (clone $tasksQuery)->whereIn('id', $criticalIds)->where('status', '!=', 'completed')->where('progress', '<', 100)->count();

            // Remaining effort calculation
            $remainingHours = null;
            if ($hasEstimatedCol) {
                $openTasks = (clone $tasksQuery)->where('status', '!=', 'completed')->where('progress', '<', 100)->get(['id', 'estimated_hours', 'progress']);
                $hasAnyEstimate = $openTasks->contains(fn($t) => $t->estimated_hours !== null && $t->estimated_hours > 0);
                if ($hasAnyEstimate) {
                    $remainingHours = round($openTasks->sum(function ($t) {
                        $est = (float) ($t->estimated_hours ?? 0);
                        $prog = (float) ($t->progress ?? 0);
                        return $est * (1 - ($prog / 100));
                    }), 1);
                }
            }

            // Workload status
            $capacity = $emp->weekly_capacity_hours ?? null;
            if ($capacity !== null && $capacity > 0 && $remainingHours !== null) {
                $utilizationPct = (int) round(($remainingHours / $capacity) * 100);
                if ($utilizationPct > 100) {
                    $workloadKey = 'over_capacity';
                    $workloadLabel = 'Over Capacity';
                } elseif ($utilizationPct >= 80) {
                    $workloadKey = 'near_capacity';
                    $workloadLabel = 'Near Capacity';
                } else {
                    $workloadKey = 'within_capacity';
                    $workloadLabel = 'Within Capacity';
                }
            } else {
                $utilizationPct = null;
                $workloadKey = 'not_configured';
                $workloadLabel = 'Capacity not configured';
            }

            // Role / Project display
            if ($singleProject) {
                $roleLabels = $this->access->roleLabels($emp, $singleProject);
                $projectsDisplay = !empty($roleLabels) ? implode(', ', $roleLabels) : 'Team Member';
            } else {
                $count = ProjectMembership::whereIn('project_id', $scopedProjectIds)->where('user_id', $emp->id)->count();
                if ($count === 0 && Project::whereIn('id', $scopedProjectIds)->where('pm_user_id', $emp->id)->exists()) {
                    $count = 1;
                }
                $projectsDisplay = "{$count} " . ($count === 1 ? 'project' : 'projects');
            }

            // Avatar initials
            $parts = preg_split('/\s+/', trim($emp->name));
            $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

            $emp->workload_metrics = [
                'initials' => $initials ?: '—',
                'projects_display' => $projectsDisplay,
                'open_tasks' => $openTasksCount,
                'completed_tasks' => $completedTasksCount,
                'overdue_tasks' => $overdueTasksCount,
                'critical_tasks' => $criticalTasksCount,
                'remaining_hours' => $remainingHours,
                'remaining_effort_text' => $remainingHours !== null ? "{$remainingHours}h" : 'Not estimated',
                'capacity' => $capacity,
                'utilization_pct' => $utilizationPct,
                'workload_key' => $workloadKey,
                'workload_label' => $workloadLabel,
            ];

            return $emp;
        });

        // Apply post-filters
        if (!empty($filters['workload'])) {
            $computedList = $computedList->filter(fn($e) => $e->workload_metrics['workload_key'] === $filters['workload'])->values();
        }

        if (!empty($filters['has_overdue'])) {
            $computedList = $computedList->filter(fn($e) => $e->workload_metrics['overdue_tasks'] > 0)->values();
        }

        if (!empty($filters['has_critical'])) {
            $computedList = $computedList->filter(fn($e) => $e->workload_metrics['critical_tasks'] > 0)->values();
        }

        // Paginate in-memory collection
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $computedList->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $currentItems,
            $computedList->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Get detailed workload information for the Employee Workload Popup modal.
     */
    public function getEmployeeWorkloadDetails(User $employee, User $actor, ?int $projectId = null, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $scopedProjectIds = $this->resolveScopedProjectIds($actor, $projectId);

        if (empty($scopedProjectIds)) {
            abort(403, 'Unauthorized to view this employee in this project scope.');
        }

        $criticalIds = $this->taskMonitoringService->getCriticalTaskIds($actor);
        $hasEstimatedCol = Schema::hasColumn('tasks', 'estimated_hours');

        // Tasks assigned to this employee within actor's scope
        $tasks = Task::with([
            'project:id,code,name,status,pm_user_id',
            'project.memberships.user:id,name',
        ])
        ->whereIn('project_id', $scopedProjectIds)
        ->whereDoesntHave('children')
        ->whereHas('assignees', fn($q) => $q->where('users.id', $employee->id))
        ->whereHas('project', fn($p) => $p->whereIn('status', ['active', 'on_hold', 'draft']))
        ->orderBy('ends_at')
        ->get();

        $openTasks = $tasks->filter(fn($t) => $t->status !== 'completed' && $t->progress < 100);
        $completedTasks = $tasks->filter(fn($t) => $t->status === 'completed' || $t->progress >= 100);
        $overdueTasks = $openTasks->filter(fn($t) => $t->ends_at->lessThan($asOf));
        $criticalTasks = $openTasks->filter(fn($t) => in_array($t->id, $criticalIds, true));

        // Remaining effort
        $remainingHours = null;
        if ($hasEstimatedCol) {
            $hasAny = $openTasks->contains(fn($t) => $t->estimated_hours !== null && $t->estimated_hours > 0);
            if ($hasAny) {
                $remainingHours = round($openTasks->sum(function ($t) {
                    $est = (float) ($t->estimated_hours ?? 0);
                    $prog = (float) ($t->progress ?? 0);
                    return $est * (1 - ($prog / 100));
                }), 1);
            }
        }

        // Roles breakdown across scoped projects
        $scopedProjects = Project::whereIn('id', $scopedProjectIds)->get();
        $rolesList = [];
        foreach ($scopedProjects as $proj) {
            $labels = $this->access->roleLabels($employee, $proj);
            if (!empty($labels)) {
                $rolesList[] = "{$proj->name}: " . implode(', ', $labels);
            }
        }

        // Format assigned tasks
        $formattedTasks = $tasks->map(function ($t) use ($criticalIds, $asOf, $actor) {
            $isOverdue = ($t->progress < 100 && $t->ends_at->lessThan($asOf));
            $isCritical = in_array($t->id, $criticalIds, true);
            $canManage = $this->access->canManageTask($actor, $t->project) || $actor->isPmo();

            // Eligible project members for reassignment
            $members = $t->project->memberships->map(fn($m) => [
                'id' => $m->user_id,
                'name' => $m->user?->name ?? "User #{$m->user_id}",
            ])->all();

            if ($t->project->pm_user_id && !in_array($t->project->pm_user_id, array_column($members, 'id'))) {
                array_unshift($members, [
                    'id' => $t->project->pm_user_id,
                    'name' => $t->project->manager?->name ?? 'Project Manager',
                ]);
            }

            return [
                'id' => $t->id,
                'code' => $t->code,
                'title' => $t->title,
                'project_id' => $t->project_id,
                'project_name' => $t->project->name,
                'project_code' => $t->project->code,
                'deadline' => $t->ends_at->format('d M Y'),
                'deadline_full' => $t->ends_at->format('d M Y, H:i'),
                'progress' => $t->progress,
                'status' => $t->status,
                'status_label' => ucwords(str_replace('_', ' ', $t->status)),
                'is_overdue' => $isOverdue,
                'is_critical' => $isCritical,
                'remaining_effort' => $t->estimated_hours ? round($t->estimated_hours * (1 - $t->progress / 100), 1) . 'h' : 'Not estimated',
                'can_manage' => $canManage,
                'eligible_members' => $members,
                'panel_url' => route('tasks.panel', $t),
            ];
        })->values()->all();

        $parts = preg_split('/\s+/', trim($employee->name));
        $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'job_title' => $employee->job_title ?? 'Team Member',
                'initials' => $initials,
                'capacity_hours' => $employee->weekly_capacity_hours,
                'roles_summary' => !empty($rolesList) ? implode(' · ', $rolesList) : 'Team Member',
            ],
            'summary' => [
                'open_tasks' => $openTasks->count(),
                'completed_tasks' => $completedTasks->count(),
                'overdue_tasks' => $overdueTasks->count(),
                'critical_tasks' => $criticalTasks->count(),
                'remaining_effort' => $remainingHours !== null ? "{$remainingHours}h" : 'Not estimated',
                'capacity' => $employee->weekly_capacity_hours ? "{$employee->weekly_capacity_hours}h / week" : 'Not configured',
            ],
            'tasks' => $formattedTasks,
        ];
    }
}
