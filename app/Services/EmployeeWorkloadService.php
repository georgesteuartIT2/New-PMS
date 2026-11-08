<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeeWorkloadService
{
    public function __construct(
        private TaskMonitoringService $taskMonitoringService,
        private Access $access
    ) {}

    /**
     * Compute company-wide (or scoped) team summary metrics.
     */
    public function getTeamSummaryMetrics(User $actor, ?Carbon $asOf = null): array
    {
        $asOf ??= now();

        $activeEmployeesQuery = User::where('account_status', 'active');
        if (!$actor->isPmo()) {
            // Scope to employees who share projects with this user
            $sharedProjectIds = ProjectMembership::where('user_id', $actor->id)->pluck('project_id');
            $activeEmployeesQuery->whereHas('memberships', fn($m) => $m->whereIn('project_id', $sharedProjectIds));
        }

        $activeEmployeesCount = (clone $activeEmployeesQuery)->count();

        // Count employees with open leaf tasks
        $employeesWithOpenTasks = (clone $activeEmployeesQuery)->whereHas('assignedTasks', function ($q) {
            $q->whereDoesntHave('children')
              ->where('tasks.status', '!=', 'completed')
              ->where('tasks.progress', '<', 100)
              ->whereHas('project', fn($p) => $p->whereIn('status', ['active', 'on_hold']));
        })->count();

        // Assignment counts (note: 1 task assigned to 2 users = 2 assignments!)
        // Task assignments pivot count
        $openAssignmentsQuery = \DB::table('task_assignments')
            ->join('tasks', 'task_assignments.task_id', '=', 'tasks.id')
            ->join('projects', 'tasks.project_id', '=', 'projects.id')
            ->join('users', 'task_assignments.user_id', '=', 'users.id')
            ->where('users.account_status', 'active')
            ->whereIn('projects.status', ['active', 'on_hold'])
            ->where('tasks.status', '!=', 'completed')
            ->where('tasks.progress', '<', 100)
            ->whereNotExists(function ($sub) {
                $sub->select(\DB::raw(1))
                    ->from('tasks as children')
                    ->whereColumn('children.parent_task_id', 'tasks.id');
            });

        if (!$actor->isPmo()) {
            $sharedProjectIds = ProjectMembership::where('user_id', $actor->id)->pluck('project_id');
            $openAssignmentsQuery->whereIn('projects.id', $sharedProjectIds);
        }

        $openAssignmentsCount = (clone $openAssignmentsQuery)->count();

        $overdueAssignmentsCount = (clone $openAssignmentsQuery)
            ->where('tasks.ends_at', '<', $asOf)
            ->count();

        return [
            'active_employees' => $activeEmployeesCount,
            'employees_with_open_tasks' => $employeesWithOpenTasks,
            'open_assignments' => $openAssignmentsCount,
            'overdue_assignments' => $overdueAssignmentsCount,
        ];
    }

    /**
     * Get paginated employees with their workload metrics computed.
     */
    public function getFilteredEmployees(User $actor, array $filters, int $perPage = 25, ?Carbon $asOf = null): LengthAwarePaginator
    {
        $asOf ??= now();
        $criticalTaskIds = $this->taskMonitoringService->getCriticalTaskIds($actor);

        $query = User::with(['subsidiary:id,name,code'])
            ->where('account_status', 'active');

        if (!$actor->isPmo()) {
            $sharedProjectIds = ProjectMembership::where('user_id', $actor->id)->pluck('project_id');
            $query->whereHas('memberships', fn($m) => $m->whereIn('project_id', $sharedProjectIds));
        }

        // Search by name or email or job_title
        if (!empty($filters['q'])) {
            $search = trim($filters['q']);
            $hasJobTitle = \Illuminate\Support\Facades\Schema::hasColumn('users', 'job_title');
            $query->where(function ($q) use ($search, $hasJobTitle) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
                if ($hasJobTitle) {
                    $q->orWhere('job_title', 'like', "%{$search}%");
                }
            });
        }

        // Subsidiary filter
        if (!empty($filters['subsidiary_id'])) {
            $query->where('subsidiary_id', $filters['subsidiary_id']);
        }

        // Project filter
        if (!empty($filters['project_id'])) {
            $query->whereHas('memberships', fn($m) => $m->where('project_id', $filters['project_id']));
        }

        // Filter: has overdue tasks
        if (!empty($filters['has_overdue'])) {
            $query->whereHas('assignedTasks', function ($q) use ($asOf) {
                $q->whereDoesntHave('children')
                  ->where('tasks.status', '!=', 'completed')
                  ->where('tasks.progress', '<', 100)
                  ->where('tasks.ends_at', '<', $asOf)
                  ->whereHas('project', fn($p) => $p->whereIn('status', ['active', 'on_hold']));
            });
        }

        // Filter: has critical tasks
        if (!empty($filters['has_critical'])) {
            $query->whereHas('assignedTasks', function ($q) use ($criticalTaskIds) {
                $q->whereDoesntHave('children')
                  ->where('tasks.status', '!=', 'completed')
                  ->where('tasks.progress', '<', 100)
                  ->whereIn('tasks.id', $criticalTaskIds)
                  ->whereHas('project', fn($p) => $p->whereIn('status', ['active', 'on_hold']));
            });
        }

        $paginator = $query->orderBy('name')->paginate($perPage)->withQueryString();

        // Calculate metrics for each employee in page
        $paginator->getCollection()->transform(function ($employee) use ($criticalTaskIds, $asOf) {
            $employeeMetrics = $this->calculateEmployeeWorkload($employee, $criticalTaskIds, $asOf);
            foreach ($employeeMetrics as $k => $v) {
                $employee->{$k} = $v;
            }
            return $employee;
        });

        // Filter by workload condition in collection if requested
        if (!empty($filters['workload'])) {
            $condition = $filters['workload'];
            $filteredCollection = $paginator->getCollection()->filter(function ($emp) use ($condition) {
                return ($emp->display_workload_status ?? $emp->workload_status) === $condition
                    || $emp->workload_status === $condition;
            });
            $paginator->setCollection($filteredCollection);
        }

        return $paginator;
    }

    /**
     * Compute single employee workload metrics.
     */
    public function calculateEmployeeWorkload(User $employee, array $criticalTaskIds = [], ?Carbon $asOf = null): array
    {
        $asOf ??= now();

        // 1. Active Projects Count (distinct active projects)
        $activeProjectsCount = Project::whereIn('status', ['active', 'on_hold'])
            ->where(function ($q) use ($employee) {
                $q->whereHas('memberships', fn($m) => $m->where('user_id', $employee->id))
                  ->orWhere('pm_user_id', $employee->id);
            })->count();

        // 2. Assigned Leaf Tasks
        $assignedLeafTasks = $employee->assignedTasks()
            ->with(['project:id,code,name,status'])
            ->whereDoesntHave('children')
            ->whereHas('project', fn($p) => $p->whereIn('status', ['active', 'on_hold']))
            ->get();

        $openTasks = $assignedLeafTasks->filter(fn($t) => $t->status !== 'completed' && $t->progress < 100);
        $completedTasks = $assignedLeafTasks->filter(fn($t) => $t->status === 'completed' || $t->progress === 100);
        $overdueTasks = $openTasks->filter(fn($t) => $t->ends_at->lessThan($asOf));
        $criticalTasks = $openTasks->filter(fn($t) => in_array($t->id, $criticalTaskIds, true));

        // 3. Remaining effort calculation
        $hasEstimatedTasks = false;
        $totalRemainingHours = 0.0;

        foreach ($openTasks as $task) {
            if ($task->estimated_hours !== null && is_numeric($task->estimated_hours) && $task->estimated_hours > 0) {
                $hasEstimatedTasks = true;
                $allocation = ($task->pivot && $task->pivot->allocation_percent) ? ($task->pivot->allocation_percent / 100) : 1.0;
                $remainingPct = max(0, 100 - ($task->progress ?? 0)) / 100;
                $totalRemainingHours += ($task->estimated_hours * $remainingPct * $allocation);
            }
        }

        $remainingHours = $hasEstimatedTasks ? round($totalRemainingHours, 1) : null;
        $availableCapacity = $employee->weekly_capacity_hours ? (int) $employee->weekly_capacity_hours : null;

        // 4. Workload Status & Utilization
        if ($availableCapacity !== null && $availableCapacity > 0 && $remainingHours !== null) {
            $utilization = round(($remainingHours / $availableCapacity) * 100);
            if ($utilization > 100) {
                $status = 'over_capacity';
                $statusLabel = 'Over Capacity';
                $badgeClass = 'pill-danger';
            } elseif ($utilization >= 80) {
                $status = 'near_capacity';
                $statusLabel = 'Near Capacity';
                $badgeClass = 'pill-warning';
            } else {
                $status = 'within_capacity';
                $statusLabel = 'Within Capacity';
                $badgeClass = 'pill-success';
            }
        } else {
            $utilization = null;
            $status = 'not_configured';
            $statusLabel = 'Capacity not configured';
            $badgeClass = 'pill-default';
        }

        // Smart Display Workload (Task count based fallback when capacity is not configured)
        $openCount = $openTasks->count();
        $overdueCount = $overdueTasks->count();
        if ($openCount === 0) {
            $taskStatus = 'available';
            $taskStatusLabel = 'Available';
            $taskBadgeClass = 'pill-neutral';
        } elseif ($openCount >= 7 || ($openCount >= 4 && $overdueCount >= 2)) {
            $taskStatus = 'over_capacity';
            $taskStatusLabel = "High Load ({$openCount} tasks)";
            $taskBadgeClass = 'pill-danger';
        } elseif ($openCount >= 4) {
            $taskStatus = 'near_capacity';
            $taskStatusLabel = "Optimal ({$openCount} tasks)";
            $taskBadgeClass = 'pill-warning';
        } else {
            $taskStatus = 'within_capacity';
            $taskStatusLabel = "Light Load ({$openCount} tasks)";
            $taskBadgeClass = 'pill-success';
        }

        $displayStatus = ($status !== 'not_configured') ? $status : $taskStatus;
        $displayLabel = ($status !== 'not_configured') ? "{$statusLabel}" . ($utilization !== null ? " ({$utilization}%)" : '') : $taskStatusLabel;
        $displayBadge = ($status !== 'not_configured') ? $badgeClass : $taskBadgeClass;

        return [
            'active_projects_count' => $activeProjectsCount,
            'open_tasks_count' => $openTasks->count(),
            'completed_tasks_count' => $completedTasks->count(),
            'overdue_tasks_count' => $overdueTasks->count(),
            'critical_tasks_count' => $criticalTasks->count(),
            'remaining_hours' => $remainingHours,
            'available_capacity' => $availableCapacity,
            'utilization_percent' => $utilization,
            'workload_status' => $status,
            'workload_label' => $statusLabel,
            'badge_class' => $badgeClass,
            'display_workload_status' => $displayStatus,
            'display_workload_label' => $displayLabel,
            'display_badge_class' => $displayBadge,
        ];
    }

    /**
     * Get deep breakdown for Employee Details popup.
     */
    public function getEmployeeDetails(User $employee, User $actor, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $criticalTaskIds = $this->taskMonitoringService->getCriticalTaskIds($actor);
        $summary = $this->calculateEmployeeWorkload($employee, $criticalTaskIds, $asOf);

        // 1. Projects Breakdown
        $projectsQuery = Project::whereIn('status', ['active', 'on_hold'])
            ->where(function ($q) use ($employee) {
                $q->whereHas('memberships', fn($m) => $m->where('user_id', $employee->id))
                  ->orWhere('pm_user_id', $employee->id);
            })
            ->with(['memberships.role', 'subsidiary']);

        if (!$actor->isPmo()) {
            $sharedProjectIds = ProjectMembership::where('user_id', $actor->id)->pluck('project_id');
            $projectsQuery->whereIn('id', $sharedProjectIds);
        }

        $projects = $projectsQuery->get()->map(function ($project) use ($employee, $criticalTaskIds, $asOf) {
            $roles = $this->access->roleLabels($employee, $project);
            $tasks = $project->tasks()
                ->whereDoesntHave('children')
                ->whereHas('assignees', fn($a) => $a->where('users.id', $employee->id))
                ->get();

            $open = $tasks->filter(fn($t) => $t->status !== 'completed' && $t->progress < 100);
            $overdue = $open->filter(fn($t) => $t->ends_at->lessThan($asOf));
            $critical = $open->filter(fn($t) => in_array($t->id, $criticalTaskIds, true));

            return [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'roles' => implode(', ', $roles) ?: 'Team Member',
                'open_tasks_count' => $open->count(),
                'overdue_tasks_count' => $overdue->count(),
                'critical_tasks_count' => $critical->count(),
                'url' => route('projects.show', $project),
            ];
        });

        // 2. Assigned Tasks List
        $tasksQuery = $employee->assignedTasks()
            ->with(['project:id,code,name,status,pm_user_id', 'parent:id,code,title'])
            ->whereDoesntHave('children')
            ->whereHas('project', fn($p) => $p->whereIn('status', ['active', 'on_hold']))
            ->orderBy('ends_at');

        if (!$actor->isPmo()) {
            $sharedProjectIds = ProjectMembership::where('user_id', $actor->id)->pluck('project_id');
            $tasksQuery->whereIn('project_id', $sharedProjectIds);
        }

        $tasks = $tasksQuery->get()->map(function ($task) use ($criticalTaskIds, $asOf, $employee) {
            $isCritical = in_array($task->id, $criticalTaskIds, true);
            $isOverdue = $task->progress < 100 && $task->ends_at->lessThan($asOf);

            $allocation = ($task->pivot && $task->pivot->allocation_percent) ? ($task->pivot->allocation_percent / 100) : 1.0;
            $remainingHours = null;
            if ($task->estimated_hours !== null && is_numeric($task->estimated_hours)) {
                $remainingPct = max(0, 100 - ($task->progress ?? 0)) / 100;
                $remainingHours = round($task->estimated_hours * $remainingPct * $allocation, 1);
            }

            // Eligible members of this task's project for reassignment
            $eligibleMembers = $task->project->memberships()
                ->with('user:id,name,email,job_title')
                ->get()
                ->map(fn($m) => $m->user)
                ->filter(fn($u) => $u && $u->id !== $employee->id)
                ->unique('id')
                ->values();

            if ($task->project->pm_user_id && $task->project->pm_user_id !== $employee->id) {
                $pm = User::find($task->project->pm_user_id);
                if ($pm && !$eligibleMembers->contains('id', $pm->id)) {
                    $eligibleMembers->push($pm);
                }
            }

            return [
                'id' => $task->id,
                'code' => $task->code,
                'title' => $task->title,
                'project_id' => $task->project_id,
                'project_name' => $task->project->name,
                'project_code' => $task->project->code,
                'deadline' => $task->ends_at->format('d M Y, h:i A'),
                'deadline_raw' => $task->ends_at->toIso8601String(),
                'is_overdue' => $isOverdue,
                'is_critical' => $isCritical,
                'progress' => $task->progress,
                'status' => $task->status,
                'status_label' => ucwords(str_replace('_', ' ', $task->status)),
                'priority' => ucfirst($task->priority),
                'remaining_hours' => $remainingHours,
                'eligible_members' => $eligibleMembers->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'job_title' => $u->job_title]),
                'url' => route('tasks.panel', $task),
            ];
        });

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'job_title' => $employee->job_title ?? 'Team Member',
                'subsidiary' => $employee->subsidiary?->name,
                'weekly_capacity' => $employee->weekly_capacity_hours,
            ],
            'summary' => $summary,
            'projects' => $projects,
            'tasks' => $tasks,
        ];
    }
}

