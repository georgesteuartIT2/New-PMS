<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TaskMonitoringService
{
    public function __construct(
        private ProjectHealthService $healthService
    ) {}

    /**
     * Build base query for tasks, enforcing scope if non-PMO user.
     * Rule: Only leaf tasks (tasks without child subtasks) represent actual executable work.
     */
    public function baseQuery(User $user, bool $leafOnly = true): Builder
    {
        $query = Task::with([
            'project:id,code,name,status,subsidiary_id',
            'project.subsidiary:id,name,code',
            'assignees:id,name,email,job_title',
            'parent:id,code,title',
            'openDelay.actionOwner:id,name',
            'openDelay.reporter:id,name',
            'children:id,parent_task_id',
        ])
        ->whereHas('project', function ($q) {
            $q->whereIn('status', ['active', 'on_hold', 'draft']);
        });

        if ($leafOnly) {
            $query->whereDoesntHave('children');
        }

        // Scope to authorized projects if standard user
        if (!$user->isPmo()) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('project.memberships', fn($m) => $m->where('user_id', $user->id))
                  ->orWhereHas('project', fn($p) => $p->where('pm_user_id', $user->id))
                  ->orWhereHas('assignees', fn($a) => $a->where('users.id', $user->id));
            });
        }

        return $query;
    }

    /**
     * Compute company-wide (or scoped) aggregate metrics.
     */
    public function getSummaryMetrics(User $user, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $base = $this->baseQuery($user, leafOnly: true);

        $totalTasks = (clone $base)->count();
        $completedTasks = (clone $base)->where(function ($q) {
            $q->where('status', 'completed')->orWhere('progress', 100);
        })->count();

        $openTasksQuery = (clone $base)->where('status', '!=', 'completed')->where('progress', '<', 100);
        $openTasks = (clone $openTasksQuery)->count();

        $overdueTasks = (clone $openTasksQuery)->where('ends_at', '<', $asOf)->count();

        $unassignedTasks = (clone $openTasksQuery)->whereDoesntHave('assignees')->count();

        $blockedTasks = (clone $openTasksQuery)->whereHas('openDelay')->count();

        $dueSoonDays = 7;
        $dueSoonTasks = (clone $openTasksQuery)
            ->where('ends_at', '>=', $asOf)
            ->where('ends_at', '<=', $asOf->copy()->addDays($dueSoonDays))
            ->count();

        $inactivityDays = 7;
        $hasCol = $this->hasLastProgressUpdatedColumn();
        $needsUpdateTasks = (clone $openTasksQuery)->where(function ($q) use ($asOf, $inactivityDays, $hasCol) {
            $cutoff = $asOf->copy()->subDays($inactivityDays);
            if ($hasCol) {
                $q->where(function ($sub) use ($cutoff) {
                    $sub->whereNotNull('last_progress_updated_at')
                        ->where('last_progress_updated_at', '<=', $cutoff);
                })->orWhere(function ($sub) use ($cutoff) {
                    $sub->whereNull('last_progress_updated_at')
                        ->where('updated_at', '<=', $cutoff);
                });
            } else {
                $q->where('updated_at', '<=', $cutoff);
            }
        })->count();

        // Calculate critical tasks via ProjectHealthService for active projects
        $criticalIds = $this->getCriticalTaskIds($user);
        $criticalTasks = (clone $openTasksQuery)->whereIn('id', $criticalIds)->count();

        return [
            'total_tasks' => $totalTasks,
            'open_tasks' => $openTasks,
            'completed_tasks' => $completedTasks,
            'overdue_tasks' => $overdueTasks,
            'unassigned_tasks' => $unassignedTasks,
            'critical_tasks' => $criticalTasks,
            'blocked_tasks' => $blockedTasks,
            'due_soon_tasks' => $dueSoonTasks,
            'needs_update_tasks' => $needsUpdateTasks,
        ];
    }

    /**
     * Get paginated and filtered tasks.
     */
    public function getFilteredTasks(User $user, array $filters, int $perPage = 25, ?Carbon $asOf = null): LengthAwarePaginator
    {
        $asOf ??= now();
        $query = $this->baseQuery($user, leafOnly: true);

        $criticalIds = $this->getCriticalTaskIds($user);

        // 1. Primary Filter Shortcut
        $quickFilter = $filters['filter'] ?? 'all';
        switch ($quickFilter) {
            case 'overdue':
                $query->where('status', '!=', 'completed')->where('progress', '<', 100)->where('ends_at', '<', $asOf);
                break;
            case 'critical':
                $query->where('status', '!=', 'completed')->where('progress', '<', 100)->whereIn('id', $criticalIds);
                break;
            case 'blocked':
                $query->where('status', '!=', 'completed')->where('progress', '<', 100)->whereHas('openDelay');
                break;
            case 'unassigned':
                $query->where('status', '!=', 'completed')->where('progress', '<', 100)->whereDoesntHave('assignees');
                break;
            case 'due_soon':
                $query->where('status', '!=', 'completed')->where('progress', '<', 100)
                    ->where('ends_at', '>=', $asOf)
                    ->where('ends_at', '<=', $asOf->copy()->addDays(7));
                break;
            case 'needs_update':
                $hasCol = $this->hasLastProgressUpdatedColumn();
                $query->where('status', '!=', 'completed')->where('progress', '<', 100)
                    ->where(function ($q) use ($asOf, $hasCol) {
                        $cutoff = $asOf->copy()->subDays(7);
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
                break;
            case 'completed':
                $query->where(function ($q) {
                    $q->where('status', 'completed')->orWhere('progress', 100);
                });
                break;
            case 'open':
                $query->where('status', '!=', 'completed')->where('progress', '<', 100);
                break;
            case 'all':
            default:
                break;
        }

        // 2. Project Filter
        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        // 3. Subsidiary Filter
        if (!empty($filters['subsidiary_id'])) {
            $query->whereHas('project', fn($p) => $p->where('subsidiary_id', $filters['subsidiary_id']));
        }

        // 4. Assigned User Filter
        if (!empty($filters['user_id'])) {
            if ($filters['user_id'] === 'unassigned') {
                $query->whereDoesntHave('assignees');
            } else {
                $query->whereHas('assignees', fn($a) => $a->where('users.id', $filters['user_id']));
            }
        }

        // 5. Status Filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // 6. Search Filter
        if (!empty($filters['q'])) {
            $search = trim($filters['q']);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhereHas('project', fn($p) => $p->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                  ->orWhereHas('assignees', fn($a) => $a->where('name', 'like', "%{$search}%"));
            });
        }

        // Ordering: Overdue first, then upcoming ends_at, then ID
        $query->orderByRaw("CASE WHEN progress < 100 AND ends_at < ? THEN 0 ELSE 1 END", [$asOf])
              ->orderBy('ends_at', 'asc')
              ->orderBy('id', 'asc');

        $paginator = $query->paginate($perPage)->withQueryString();

        // Attach dynamic attributes: critical, health label, wbs code
        $paginator->getCollection()->transform(function ($task) use ($criticalIds, $asOf) {
            $isCritical = in_array($task->id, $criticalIds, true);
            $task->is_critical = $isCritical;

            $hasBlocker = $task->openDelay !== null;
            $isOverdue = $task->progress < 100 && $task->ends_at->lessThan($asOf);

            if ($hasBlocker) {
                $task->schedule_health = 'Blocked';
                $task->health_class = 'pill-danger';
            } elseif ($isOverdue) {
                $task->schedule_health = 'Overdue';
                $task->health_class = 'pill-danger';
            } elseif ($isCritical) {
                $task->schedule_health = 'Critical Path';
                $task->health_class = 'pill-warning';
            } elseif ($task->status === 'completed' || $task->progress === 100) {
                $task->schedule_health = 'Completed';
                $task->health_class = 'pill-success';
            } else {
                $task->schedule_health = 'On Track';
                $task->health_class = 'pill-success';
            }

            return $task;
        });

        return $paginator;
    }

    /**
     * Retrieve all critical task IDs across projects visible to user.
     */
    public function getCriticalTaskIds(User $user): array
    {
        $projectsQuery = Project::whereIn('status', ['active', 'on_hold']);
        if (!$user->isPmo()) {
            $projectsQuery->where(function ($q) use ($user) {
                $q->whereHas('memberships', fn($m) => $m->where('user_id', $user->id))
                  ->orWhere('pm_user_id', $user->id);
            });
        }

        $projects = $projectsQuery->get();
        $criticalIds = [];

        foreach ($projects as $project) {
            try {
                $assessment = $this->healthService->assess($project);
                if (!empty($assessment['critical_ids'])) {
                    $criticalIds = array_merge($criticalIds, $assessment['critical_ids']);
                }
            } catch (\Throwable $e) {
                // Ignore single project CPM cycle errors gracefully
            }
        }

        return array_values(array_unique($criticalIds));
    }

    private static ?bool $hasProgressCol = null;

    private function hasLastProgressUpdatedColumn(): bool
    {
        if (self::$hasProgressCol === null) {
            try {
                self::$hasProgressCol = \Illuminate\Support\Facades\Schema::hasColumn('tasks', 'last_progress_updated_at');
            } catch (\Throwable $e) {
                self::$hasProgressCol = false;
            }
        }
        return self::$hasProgressCol;
    }
}

