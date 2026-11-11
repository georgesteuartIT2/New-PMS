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
        if ($user->canViewPortfolio()) {
            $monitoringMetrics = $taskMonitor->getSummaryMetrics($user);
            $teamMetrics = $teamMonitor->getTeamSummaryMetrics($user);

            $allProjects = Project::with('manager', 'subsidiary')->withCount([
                'tasks'=>fn($q)=>$q->whereDoesntHave('children'),
                'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'completed')->whereDoesntHave('children')
            ])->latest()->get();

            $totalProjects = $allProjects->count();
            $activeProjects = $allProjects->where('status', 'active')->count();
            $pendingProjects = Project::where('status', 'pending')->with('manager')->latest()->take(6)->get();
            $pendingCount = Project::where('status', 'pending')->count();

            $draftProjects = Project::where('status', 'draft')->with('manager')->latest()->take(6)->get();
            $unmanaged = $draftProjects;
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

            foreach ($draftProjects as $project) {
                $hasPm = (bool)$project->pm_user_id;
                $attentionItems->push([
                    'type' => 'unmanaged',
                    'project' => $project,
                    'code' => $project->code,
                    'name' => $project->name,
                    'issue' => $hasPm ? 'Draft (Ready to create & send to PM)' : 'Draft (No PM assigned)',
                    'owner' => $project->manager?->name ?? '—',
                    'action_label' => 'Continue draft',
                    'action_url' => route('projects.create', ['draft' => $project->id]),
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

            // Project decisions stay separate from the task attention preview.
            $attentionItems = $attentionItems->filter(fn($item) => in_array($item['type'], ['declined', 'pending']))
                ->sortBy(fn($item) => $item['type'] === 'declined' ? 0 : 1)->values();
            $blockerQuery=TaskDelay::where('status','open')->whereHas('task.project',fn($q)=>$q->whereIn('status',['active','on_hold']));
            $blockerCount=(clone $blockerQuery)->count();
            $unreadBlockerCount=(clone $blockerQuery)->whereDoesntHave('readers',fn($q)=>$q->where('users.id',$user->id))->count();
            $blockerPreview=(clone $blockerQuery)->with(['task.project','actionOwner'])->withExists(['readers as is_read'=>fn($q)=>$q->where('users.id',$user->id)])->orderByRaw('CASE WHEN action_owner_id IS NULL THEN 0 ELSE 1 END')->oldest()->paginate(5,['*'],'blocker_page');
            $taskAttentionFilter = in_array($request->query('task_attention'),['critical','blocked']) ? $request->query('task_attention') : 'overdue';
            if (!$request->has('task_attention') && !$monitoringMetrics['overdue_tasks']) {
                $taskAttentionFilter = 'critical';
            }
            // Reuse Task Monitor filters, counts and ordering; always preview the first five.
            $taskAttention = $taskMonitor->getFilteredTasks($user, ['filter' => $taskAttentionFilter], 5);
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
                ->whereNotNull('ends_at')->where('ends_at', '>=', now())
                ->orderBy('ends_at')
                ->take(5)
                ->get();

            return view('dashboard', [
                'isPmo' => true,
                'taskAttention' => $taskAttention,
                'taskAttentionFilter' => $taskAttentionFilter,
                'blockerCount'=>$blockerCount,'unreadBlockerCount'=>$unreadBlockerCount,'blockerPreview'=>$blockerPreview,
                'draftProjects' => $draftProjects,
                'draftCount' => Project::where('status', 'draft')->count(),
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
                'tasks'=>fn($q)=>$q->whereDoesntHave('children'),
                'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'completed')->whereDoesntHave('children'),
            ])
            ->where('status', '!=', 'draft')
            ->where(function ($query) use ($user) {
                $query->where('pm_user_id', $user->id)
                    ->orWhereHas('memberships', fn($m) => $m->where('user_id', $user->id))
                    ->orWhereHas('tasks.assignees', fn($a) => $a->where('users.id', $user->id));
            })
            ->where(function ($q) use ($user) {
                $q->whereIn('status', ['active', 'on_hold', 'completed', 'archived'])
                    ->orWhere(fn($sub) => $sub->where(fn($lead)=>$lead->where('pm_user_id',$user->id)->orWhere('owner_user_id',$user->id))->whereIn('status',['pending','declined']));
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

        $pendingInvitations = ProjectInvitation::with('project')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('project', fn($q) => $q->where('status', '!=', 'draft'))
            ->get();

        $approvalQueue = Project::where('status','pending')->where('pm_user_id',$user->id)
            ->whereHas('invitations',fn($q)=>$q->where('user_id',$user->id)->where('status','pending'))
            ->with('subsidiary','creator')->withCount(['tasks'=>fn($q)=>$q->whereDoesntHave('children')])
            ->oldest()->paginate(5,['*'],'approvals_page')->withQueryString()->fragment('project-approvals');
        $attentionItems=app(\App\Services\PersonalAttentionService::class)->rows($user);
        $monitoredProjects=$userMonitoring->getMonitoredProjects($user);
        $hasMonitoredProjects=$monitoredProjects->isNotEmpty();

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
        $upcomingWork = $userTasks->filter(fn($task) => $task->ends_at && !$task->ends_at->isPast())->take(5);

        return view('dashboard', [
            'isPmo' => false,
            'approvalQueue' => $approvalQueue,
            'dueTodayCount' => $userTasks->filter(fn($task) => $task->ends_at?->isToday())->count(),
            'myOverdueCount' => $userTasks->filter(fn($task) => $task->ends_at && $task->ends_at->isPast())->count(),
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
