<?php

namespace App\Http\Controllers;

use App\Models\{Project,Task,TaskDelay,ProjectInvitation};
use App\Services\{Access,ProjectHealthService,TaskMonitoringService,EmployeeWorkloadService,UserTaskMonitoringService};
use Illuminate\Http\Request;

class DashboardController
{
    public function index(
        Request $request,
        Access $access,
        ProjectHealthService $healthService,
        TaskMonitoringService $taskMonitor,
        EmployeeWorkloadService $teamMonitor,
        UserTaskMonitoringService $userMonitoring
    )
    {
        $user = $request->user();
        if ($user->isPmo()) {
            $monitoringMetrics = $taskMonitor->getSummaryMetrics($user);
            $teamMetrics = $teamMonitor->getTeamSummaryMetrics($user);

            $allProjects = Project::with('manager', 'subsidiary')->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'completed')
            ])->latest()->get();

            $totalProjects = $allProjects->count();
            $activeProjects = $allProjects->where('status', 'active')->count();
            $pendingProjects = Project::where('status', 'pending')->with('manager')->latest()->take(6)->get();
            $pendingCount = Project::where('status', 'pending')->count();

            $unmanaged = Project::where('status', 'draft')->whereNull('pm_user_id')->latest()->take(6)->get();
            $declinedProjects = Project::where('status', 'declined')->with('manager', 'invitations.user')->latest()->take(6)->get();

            $overdueQuery = Task::with('project', 'assignees')
                ->where('progress', '<', 100)
                ->where('ends_at', '<', now())
                ->whereHas('project', fn($q) => $q->whereIn('status', ['active', 'on_hold']));

            $overdueTasksCount = (clone $overdueQuery)->count();
            $overdueProjectsCount = (clone $overdueQuery)->distinct('project_id')->count('project_id');
            $overdueTasks = $overdueQuery->orderBy('ends_at')->take(8)->get();

            $blocked = TaskDelay::with('task.project', 'actionOwner')->where('status', 'open')
                ->whereHas('task.project', fn($q) => $q->whereIn('status', ['active', 'on_hold']))->latest()->take(8)->get();

            $reviewProjects = Project::with('manager', 'template')->whereIn('status', ['active', 'on_hold'])->where(function ($q) {
                $q->whereDate('ends_on', '<', now()->toDateString())
                    ->orWhereHas('tasks', fn($t) => $t->where('progress', '<', 100)->where('ends_at', '<', now()))
                    ->orWhereHas('tasks', fn($t) => $t->whereHas('delayReports', fn($d) => $d->where('status', 'open')));
            })->orderBy('ends_on')->take(15)->get();

            $criticalReviews = $reviewProjects->map(function ($project) use ($healthService) {
                $health = $healthService->assess($project);
                $blockingCritical = $health['critical_tasks']->first(fn($task) => $task->openDelay || ($task->ends_at->isPast() && $task->progress < 100));
                return ['project' => $project, 'health' => $health, 'blockingCritical' => $blockingCritical];
            });

            // At Risk & Delayed calculations
            $delayedProjects = $allProjects->filter(function ($p) {
                return $p->ends_on && $p->ends_on->isPast() && !in_array($p->status, ['completed', 'archived']);
            });
            $delayedCount = $delayedProjects->count();

            $atRiskCount = $criticalReviews->filter(function ($item) {
                return in_array($item['health']['health'] ?? '', ['at_risk', 'overdue']);
            })->count();

            if ($atRiskCount === 0 && $criticalReviews->count() > 0) {
                $atRiskCount = $criticalReviews->count();
            }

            $onTrackCount = max(0, $activeProjects - $atRiskCount - $delayedCount);
            if ($onTrackCount === 0 && $activeProjects > 0 && $atRiskCount === 0 && $delayedCount === 0) {
                $onTrackCount = $activeProjects;
            }

            // Portfolio Health distribution percentages
            $healthTotal = $totalProjects > 0 ? $totalProjects : ($onTrackCount + $atRiskCount + $delayedCount);
            if ($healthTotal > 0) {
                $onTrackPct = round(($onTrackCount / $healthTotal) * 100);
                $atRiskPct = round(($atRiskCount / $healthTotal) * 100);
                $delayedPct = max(0, 100 - $onTrackPct - $atRiskPct);
            } else {
                $onTrackPct = 0;
                $atRiskPct = 0;
                $delayedPct = 0;
            }

            // Attention items aggregation
            $attentionItems = collect();

            foreach ($unmanaged as $project) {
                $attentionItems->push([
                    'type' => 'unmanaged',
                    'project' => $project,
                    'code' => $project->code,
                    'name' => $project->name,
                    'issue' => 'No PM assigned',
                    'owner' => '—',
                    'action_label' => 'Assign PM',
                    'action_url' => route('projects.show', [$project, 'reassign' => 1]),
                ]);
            }

            foreach ($declinedProjects as $project) {
                $declinedInv = $project->invitations->where('status', 'declined')->sortByDesc('id')->first();
                $reasonSnippet = $declinedInv?->decline_reason ? ': "' . \Illuminate\Support\Str::limit($declinedInv->decline_reason, 45) . '"' : '';
                $attentionItems->push([
                    'type' => 'declined',
                    'project' => $project,
                    'code' => $project->code,
                    'name' => $project->name,
                    'issue' => 'PM assignment declined by ' . ($declinedInv?->user?->name ?? $project->manager?->name ?? 'PM') . $reasonSnippet,
                    'owner' => $declinedInv?->user?->name ?? $project->manager?->name ?? '—',
                    'action_label' => 'Reassign PM',
                    'action_url' => route('projects.show', [$project, 'reassign' => 1]),
                ]);
            }

            foreach ($criticalReviews as $item) {
                $project = $item['project'];
                $task = $item['blockingCritical'];
                $delayDays = null;
                if ($task && $task->ends_at && $task->ends_at->isPast()) {
                    $delayDays = (int) ceil(abs(now()->diffInDays($task->ends_at, false)));
                }
                $issue = $delayDays && $delayDays > 0
                    ? "Critical task delayed by {$delayDays} " . ($delayDays === 1 ? 'day' : 'days')
                    : ($task && $task->openDelay
                        ? (\App\Http\Controllers\TaskDelayController::REASONS[$task->openDelay->reason_code] ?? 'Critical task delayed')
                        : 'Schedule dependencies need review');

                $owner = $task?->openDelay?->actionOwner?->name ?? $project->manager?->name ?? '—';

                $attentionItems->push([
                    'type' => 'critical',
                    'project' => $project,
                    'code' => $project->code,
                    'name' => $project->name,
                    'issue' => $issue,
                    'owner' => $owner,
                    'action_label' => 'Review Delay',
                    'action_url' => route('projects.show', $project) . '#project-attention',
                ]);
            }

            foreach ($pendingProjects as $project) {
                $attentionItems->push([
                    'type' => 'pending',
                    'project' => $project,
                    'code' => $project->code,
                    'name' => $project->name,
                    'issue' => 'Pending acceptance (' . ($project->manager ? 'Awaiting ' . $project->manager->name : 'PM review') . ')',
                    'owner' => $project->manager?->name ?? '—',
                    'action_label' => 'Review Acceptance',
                    'action_url' => route('projects.show', $project),
                ]);
            }

            foreach ($overdueTasks->take(4) as $task) {
                if ($task->project) {
                    $attentionItems->push([
                        'type' => 'overdue_task',
                        'project' => $task->project,
                        'code' => $task->project->code,
                        'name' => $task->project->name,
                        'issue' => 'Milestone overdue: ' . $task->title . ' (due ' . $task->ends_at->format('d M') . ')',
                        'owner' => $task->assignees->first()?->name ?? $task->project->manager?->name ?? '—',
                        'action_label' => 'Open Project',
                        'action_url' => route('projects.show', $task->project) . '#project-attention',
                    ]);
                }
            }

            $totalAttentionCount = $attentionItems->count();

            // Recent projects formatted
            $recentProjects = $allProjects->take(6)->map(function ($p) use ($criticalReviews) {
                $status = $p->status;
                $statusLabel = ucwords(str_replace('_', ' ', $status));

                if (in_array($status, ['active', 'on_hold'])) {
                    if ($p->ends_on && $p->ends_on->isPast()) {
                        $statusLabel = 'Delayed';
                        $status = 'delayed';
                    } elseif ($criticalReviews->contains(fn($r) => $r['project']->id === $p->id)) {
                        $statusLabel = 'At Risk';
                        $status = 'at_risk';
                    } else {
                        $statusLabel = 'On Track';
                        $status = 'on_track';
                    }
                }

                return [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'manager' => $p->manager?->name ?? '—',
                    'progress' => (int) $p->progress,
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'ends_on' => $p->ends_on,
                    'url' => route('projects.show', $p),
                ];
            });

            // Upcoming deadlines (Tasks & milestones)
            $upcomingTasks = Task::with('project')
                ->where('progress', '<', 100)
                ->whereHas('project', fn($q) => $q->whereIn('status', ['active', 'on_hold']))
                ->orderBy('ends_at')
                ->take(5)
                ->get();

            return view('dashboard', [
                'isPmo' => true,
                'projects' => $allProjects->take(12),
                'recentProjects' => $recentProjects,
                'pendingInvitations' => collect(),
                'tasks' => collect(),
                'unmanaged' => $unmanaged,
                'pendingProjects' => $pendingProjects,
                'overdueTasks' => $overdueTasks,
                'reportedBlockers' => $blocked,
                'criticalReviews' => $criticalReviews,
                'attentionItems' => $attentionItems,
                'totalAttentionCount' => $totalAttentionCount,
                'upcomingTasks' => $upcomingTasks,
                'monitoringMetrics' => $monitoringMetrics,
                'teamMetrics' => $teamMetrics,
                'kpi' => [
                    'totalProjects' => $totalProjects,
                    'activeProjects' => $activeProjects,
                    'pendingAcceptance' => $pendingCount,
                    'atRiskProjects' => $atRiskCount,
                    'overdueTasks' => $overdueTasksCount,
                    'overdueProjectsCount' => $overdueProjectsCount,
                ],
                'portfolioHealth' => [
                    'total' => $totalProjects,
                    'onTrack' => $onTrackCount,
                    'onTrackPercent' => $onTrackPct,
                    'atRisk' => $atRiskCount,
                    'atRiskPercent' => $atRiskPct,
                    'delayed' => $delayedCount,
                    'delayedPercent' => $delayedPct,
                ],
                'stats' => [
                    'Projects' => $totalProjects,
                    'Active' => $activeProjects,
                    'Pending acceptance' => $pendingCount,
                    'Need attention' => $totalAttentionCount,
                ],
            ]);
        }

        // Non-PMO user (My Workspace Dashboard)
        $userProjects = Project::with(['manager', 'subsidiary', 'memberships.role'])
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'completed'),
            ])
            ->where(function ($query) use ($user) {
                $query->where('pm_user_id', $user->id)
                    ->orWhereHas('memberships', fn($m) => $m->where('user_id', $user->id))
                    ->orWhereHas('tasks.assignees', fn($a) => $a->where('users.id', $user->id));
            })
            ->where(function ($q) use ($user) {
                $q->whereIn('status', ['active', 'on_hold', 'completed', 'archived'])
                    ->orWhere('pm_user_id', $user->id);
            })
            ->latest()
            ->get()
            ->filter(fn($p) => $access->project($user, $p, 'view_project'))
            ->values();

        // Tasks assigned to user
        $userTasks = Task::with('project')
            ->whereHas('assignees', fn($q) => $q->where('users.id', $user->id))
            ->whereHas('project', fn($q) => $q->whereIn('status', ['active', 'on_hold']))
            ->where('progress', '<', 100)
            ->orderBy('ends_at')
            ->get();

        $pendingInvitations = ProjectInvitation::with('project')->where('user_id', $user->id)->where('status', 'pending')->get();

        // Needs My Attention items aggregation
        $attentionItems = collect();

        // 1. PM invitations awaiting acceptance
        foreach ($pendingInvitations as $inv) {
            $attentionItems->push([
                'type' => 'invitation',
                'title' => $inv->project?->name ?? 'Project',
                'project_name' => $inv->project?->code,
                'issue' => 'Project Manager invitation awaiting acceptance',
                'deadline' => $inv->project?->starts_on ? $inv->project->starts_on->format('d M Y') : 'Immediate',
                'is_invitation' => true,
                'project_id' => $inv->project_id,
                'action_label' => 'Accept / Decline',
                'action_url' => route('projects.show', $inv->project_id),
            ]);
        }

        // 2. Overdue assigned tasks
        foreach ($userTasks->filter(fn($t) => $t->ends_at->isPast()) as $task) {
            $attentionItems->push([
                'type' => 'overdue_task',
                'title' => $task->title,
                'project_name' => $task->project?->name,
                'issue' => 'Task overdue by ' . $task->ends_at->diffForHumans(null, true),
                'deadline' => $task->ends_at->format('d M Y, H:i'),
                'is_invitation' => false,
                'task_id' => $task->id,
                'action_label' => 'Update Task',
                'action_url' => route('tasks.panel', $task),
            ]);
        }

        // 3. Blocked tasks
        $blockedDelays = TaskDelay::with('task.project', 'actionOwner')
            ->where('status', 'open')
            ->where(function($q) use ($user) {
                $q->where('action_owner_id', $user->id)
                  ->orWhereHas('task.assignees', fn($a) => $a->where('users.id', $user->id))
                  ->orWhereHas('task.project', fn($p) => $p->where('pm_user_id', $user->id));
            })->get();

        foreach ($blockedDelays as $delay) {
            $attentionItems->push([
                'type' => 'blocker',
                'title' => $delay->task?->title ?? 'Task',
                'project_name' => $delay->task?->project?->name,
                'issue' => 'Blocker: ' . (\App\Http\Controllers\TaskDelayController::REASONS[$delay->reason_code] ?? $delay->reason_code),
                'deadline' => $delay->revised_expected_end_at ? $delay->revised_expected_end_at->format('d M Y') : 'Action required',
                'is_invitation' => false,
                'task_id' => $delay->task_id,
                'action_label' => 'View Blocker',
                'action_url' => route('tasks.panel', $delay->task_id),
            ]);
        }

        // 4. Tasks due soon (within 3 days)
        foreach ($userTasks->filter(fn($t) => !$t->ends_at->isPast() && now()->diffInDays($t->ends_at, false) <= 3) as $task) {
            $attentionItems->push([
                'type' => 'due_soon',
                'title' => $task->title,
                'project_name' => $task->project?->name,
                'issue' => 'Due in ' . $task->ends_at->diffForHumans(null, true),
                'deadline' => $task->ends_at->format('d M Y, H:i'),
                'is_invitation' => false,
                'task_id' => $task->id,
                'action_label' => 'Open Task',
                'action_url' => route('tasks.panel', $task),
            ]);
        }

        // 5. For PMs: projects completed ready for closure
        $pmProjects = $userProjects->filter(fn($p) => $p->pm_user_id === $user->id);
        foreach ($pmProjects as $pmp) {
            if ($pmp->tasks_count > 0 && $pmp->completed_tasks_count === $pmp->tasks_count && $pmp->status === 'active') {
                $attentionItems->push([
                    'type' => 'closure',
                    'title' => $pmp->name,
                    'project_name' => $pmp->code,
                    'issue' => 'All tasks completed. Ready for project closure.',
                    'deadline' => $pmp->ends_on->format('d M Y'),
                    'is_invitation' => false,
                    'project_id' => $pmp->id,
                    'action_label' => 'Request Closure',
                    'action_url' => route('projects.closure', $pmp),
                ]);
            }
        }

        // 6. Attention items for projects managed by the user
        $monitoredProjects = $userMonitoring->getMonitoredProjects($user);
        $hasMonitoredProjects = $monitoredProjects->isNotEmpty();

        if ($hasMonitoredProjects) {
            $managedMetrics = $userMonitoring->getManagedTaskMetrics($user);
            if (($managedMetrics['overdue_tasks'] ?? 0) > 0) {
                $attentionItems->push([
                    'type' => 'managed_overdue',
                    'title' => $managedMetrics['overdue_tasks'] . ' Overdue ' . ($managedMetrics['overdue_tasks'] === 1 ? 'Task' : 'Tasks'),
                    'project_name' => 'Managed Projects',
                    'issue' => 'Overdue tasks requiring review in your projects.',
                    'deadline' => 'Overdue',
                    'is_invitation' => false,
                    'action_label' => 'Task Monitor →',
                    'action_url' => route('tasks.mine', ['view' => 'managed', 'tab' => 'tasks', 'filter' => 'overdue']),
                ]);
            }

            if (($managedMetrics['unassigned_tasks'] ?? 0) > 0) {
                $attentionItems->push([
                    'type' => 'managed_unassigned',
                    'title' => $managedMetrics['unassigned_tasks'] . ' Unassigned ' . ($managedMetrics['unassigned_tasks'] === 1 ? 'Task' : 'Tasks'),
                    'project_name' => 'Managed Projects',
                    'issue' => 'Executable tasks needing assignment in your projects.',
                    'deadline' => 'Action required',
                    'is_invitation' => false,
                    'action_label' => 'Assign Tasks →',
                    'action_url' => route('tasks.mine', ['view' => 'managed', 'tab' => 'tasks', 'filter' => 'unassigned']),
                ]);
            }
        }

        // Projects preview formatted with dynamic roles and permissions
        $myProjectsPreview = $userProjects->take(6)->map(function ($p) use ($user, $access) {
            $roles = $access->roleLabels($user, $p);
            $canViewTasks = $user->isPmo() || $p->pm_user_id === $user->id || $access->project($user, $p, 'view_tasks');
            return [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'roles' => implode(', ', $roles),
                'progress' => $canViewTasks ? (int)$p->progress : null,
                'completed_tasks' => $canViewTasks ? $p->completed_tasks_count : null,
                'total_tasks' => $canViewTasks ? $p->tasks_count : null,
                'deadline' => $p->ends_on,
                'status' => $p->status,
                'status_label' => ucwords(str_replace('_', ' ', $p->status)),
                'url' => route('projects.show', $p),
            ];
        });

        // Upcoming work short list
        $upcomingWork = $userTasks->take(5);

        return view('dashboard', [
            'isPmo' => false,
            'projects' => $userProjects,
            'myProjectsPreview' => $myProjectsPreview,
            'tasks' => $userTasks,
            'upcomingWork' => $upcomingWork,
            'pendingInvitations' => $pendingInvitations,
            'attentionItems' => $attentionItems,
            'hasMonitoredProjects' => $hasMonitoredProjects,
            'monitoredProjects' => $monitoredProjects,
            'kpi' => [
                'myProjects' => $userProjects->count(),
                'activeTasks' => $userTasks->count(),
                'attentionCount' => $attentionItems->count(),
            ],
        ]);
    }
}
