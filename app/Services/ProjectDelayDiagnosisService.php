<?php

namespace App\Services;

use App\Models\{Project, Task, TaskDelay, User};
use App\Http\Controllers\TaskDelayController;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Enterprise Project Delay Diagnosis Service.
 * Automatically investigates project scheduling problems, critical paths,
 * delayed tasks, overdue tasks, and blocking dependencies.
 * Never invents fabricated root causes or corrective actions.
 */
class ProjectDelayDiagnosisService
{
    public function __construct(
        private CriticalPathCalculator $cpmCalculator,
        private WorkingSchedule $calendar,
        private DelayExplanationService $explanationService
    ) {}

    /**
     * Perform complete automated investigation for a project.
     *
     * @param Project $project
     * @param User|null $user
     * @param Carbon|null $asOf
     * @return array
     */
    public function diagnose(Project $project, ?User $user = null, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $workHours = $project->template?->working_hours;
        $approvedDeadline = $project->ends_on->copy()->endOfDay();
        $isPastApprovedDeadline = $approvedDeadline->lessThan($asOf) && !in_array($project->status, ['completed', 'archived'], true);

        // Load all tasks with relations
        $tasks = $project->tasks()
            ->with([
                'dependencies:id,code,title,starts_at,ends_at,status,progress',
                'assignees:id,name,email',
                'openDelay.actionOwner',
                'children:id,parent_task_id',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $leaves = $tasks->filter(fn ($t) => $t->children->isEmpty())->values();
        $taskNumbers = TaskTree::numbers($tasks);
        $allById = $tasks->keyBy('id');

        // Map ancestor to leaf tasks for CPM dependency inheritance
        $leafByAncestor = [];
        foreach ($leaves as $leaf) {
            $parent = $leaf;
            $visitedParents = [];
            while ($parent && !isset($visitedParents[$parent->id])) {
                $visitedParents[$parent->id] = true;
                $leafByAncestor[$parent->id][] = $leaf->id;
                $parent = $parent->parent_task_id ? $allById->get($parent->parent_task_id) : null;
            }
        }

        // Build CPM Network nodes
        $nodes = [];
        $invalidLinks = 0;
        foreach ($leaves as $task) {
            $duration = $this->calendar->minutesBetween($task->starts_at, $task->ends_at, $workHours);
            if ($duration < 1) {
                $duration = max(1, (int) $task->starts_at->diffInMinutes($task->ends_at));
            }

            $predecessors = [];
            $cursor = $task;
            $seenAncestors = [];
            while ($cursor && !isset($seenAncestors[$cursor->id])) {
                $seenAncestors[$cursor->id] = true;
                foreach ($cursor->dependencies as $dependency) {
                    if (!isset($leafByAncestor[$dependency->id])) {
                        $invalidLinks++;
                        continue;
                    }
                    foreach ($leafByAncestor[$dependency->id] as $predecessorId) {
                        if ($predecessorId !== $task->id) {
                            $predecessors[] = $predecessorId;
                        }
                    }
                }
                $cursor = $cursor->parent_task_id ? $allById->get($cursor->parent_task_id) : null;
            }

            $nodes[$task->id] = [
                'duration' => $duration,
                'predecessors' => array_values(array_unique($predecessors)),
            ];
        }

        // Run Critical Path Calculation
        $invalidNetwork = false;
        try {
            $cpmResult = $this->cpmCalculator->calculate($nodes);
        } catch (InvalidArgumentException $e) {
            $cpmResult = ['duration' => 0, 'tasks' => [], 'path' => [], 'links' => 0];
            $invalidNetwork = true;
        }

        $cpmReliable = !$invalidNetwork && $invalidLinks === 0 && ($cpmResult['links'] > 0 || count($nodes) <= 1);
        $criticalIds = $cpmReliable ? $cpmResult['path'] : [];
        $cpmTasks = $cpmResult['tasks'] ?? [];

        // Build reverse dependency lookup (Successors)
        $successorsMap = [];
        foreach ($nodes as $tId => $node) {
            foreach ($node['predecessors'] as $predId) {
                $successorsMap[$predId][] = $tId;
            }
        }

        // Calculate Defensible Forecast Completion
        $forecast = null;
        $taskForecasts = [];
        if ($leaves->isNotEmpty() && !$invalidNetwork) {
            $forecastEnds = [];
            $order = array_keys($cpmTasks);
            usort($order, fn ($a, $b) => ($cpmTasks[$a]['early_start'] ?? 0) <=> ($cpmTasks[$b]['early_start'] ?? 0));

            foreach ($order as $id) {
                $task = $allById->get($id);
                if (!$task) continue;

                $starts = $task->starts_at->copy();
                foreach ($nodes[$id]['predecessors'] as $predId) {
                    if (isset($forecastEnds[$predId]) && $forecastEnds[$predId]->greaterThan($starts)) {
                        $starts = $forecastEnds[$predId]->copy();
                    }
                }

                if ($task->progress >= 100 || $task->status === 'completed') {
                    $forecastEnds[$id] = $starts->greaterThan($task->ends_at) ? $starts : $task->ends_at->copy();
                    $taskForecasts[$id] = $forecastEnds[$id]->copy();
                    continue;
                }

                if ($starts->lessThan($asOf)) {
                    $starts = $asOf->copy();
                }

                $remainingMinutes = (int) ceil($nodes[$id]['duration'] * (100 - $task->progress) / 100);
                $forecastEnds[$id] = $this->calendar->addMinutes($starts, $remainingMinutes, $workHours);
                $taskForecasts[$id] = $forecastEnds[$id]->copy();
            }

            foreach ($forecastEnds as $date) {
                if (!$forecast || $date->greaterThan($forecast)) {
                    $forecast = $date->copy();
                }
            }
        }

        // Schedule Variance Calculation
        $forecastLate = $forecast && $forecast->greaterThan($approvedDeadline);
        $varianceWorkingDays = 0;
        $varianceText = 'On schedule';
        $variancePill = 'On schedule';

        if ($isPastApprovedDeadline) {
            $varianceWorkingDays = max(1, (int) $approvedDeadline->diffInWeekdays($asOf));
            $varianceText = "+{$varianceWorkingDays} " . ($varianceWorkingDays === 1 ? 'working day' : 'working days') . ' past deadline';
            $variancePill = "+{$varianceWorkingDays} " . ($varianceWorkingDays === 1 ? 'day' : 'days');
        } elseif ($forecastLate && $forecast) {
            $varianceWorkingDays = max(1, (int) $approvedDeadline->diffInWeekdays($forecast));
            $varianceText = "+{$varianceWorkingDays} " . ($varianceWorkingDays === 1 ? 'working day' : 'working days') . ' forecast delay';
            $variancePill = "+{$varianceWorkingDays} " . ($varianceWorkingDays === 1 ? 'day' : 'days');
        } elseif ($forecast && $forecast->lessThan($approvedDeadline)) {
            $aheadDays = (int) $forecast->diffInWeekdays($approvedDeadline);
            if ($aheadDays > 0) {
                $varianceText = "Ahead by {$aheadDays} " . ($aheadDays === 1 ? 'working day' : 'working days');
                $variancePill = "-{$aheadDays} " . ($aheadDays === 1 ? 'day' : 'days');
            }
        }

        // Visible tasks (scoped if standard team member without view_all permission)
        $canViewAll = $user === null || $user->isPmo() || ($project->pm_user_id === $user->id) ||
            app(Access::class)->project($user, $project, 'tasks.view_all') ||
            app(Access::class)->project($user, $project, 'view_tasks');

        $visibleLeaves = $canViewAll
            ? $leaves
            : $leaves->filter(fn ($t) => $t->assignees->contains('id', $user->id))->values();

        // SECTION E: Overdue Tasks
        // Incomplete tasks whose planned end date is in the past
        $overdueLeaves = $visibleLeaves->filter(function ($t) use ($asOf) {
            return ($t->progress < 100 && $t->status !== 'completed' && $t->ends_at->lessThan($asOf));
        })->sortBy('ends_at')->values();

        // SECTION D: Delayed Tasks
        // 1) Actual completion after planned finish (completed late)
        // 2) Forecast finish exceeds planned finish
        // 3) Incomplete task currently overdue
        $delayedLeaves = $visibleLeaves->filter(function ($t) use ($asOf, $taskForecasts) {
            $isCompleted = ($t->progress >= 100 || $t->status === 'completed');
            if ($isCompleted) {
                // Completed late
                return ($t->baseline_ends_at && $t->ends_at->greaterThan($t->baseline_ends_at));
            }

            // Incomplete overdue
            if ($t->ends_at->lessThan($asOf)) {
                return true;
            }

            // Forecast finish late
            $tForecast = $taskForecasts[$t->id] ?? null;
            if ($tForecast && $tForecast->greaterThan($t->ends_at)) {
                return true;
            }

            return false;
        })->sortBy('ends_at')->values();

        // Format task helper
        $formatTaskRow = function (Task $t, bool $isDelayedContext) use ($taskNumbers, $asOf, $criticalIds, $cpmTasks, $taskForecasts, $allById, $successorsMap, $user, $project) {
            $isCritical = in_array($t->id, $criticalIds, true);
            $isOverdue = ($t->progress < 100 && $t->status !== 'completed' && $t->ends_at->lessThan($asOf));
            $isCompleted = ($t->progress >= 100 || $t->status === 'completed');

            $taskForecast = $taskForecasts[$t->id] ?? $t->ends_at;
            $taskDelayDays = 0;

            if ($isOverdue) {
                $taskDelayDays = max(1, (int) $t->ends_at->diffInWeekdays($asOf));
            } elseif ($taskForecast && $taskForecast->greaterThan($t->ends_at)) {
                $taskDelayDays = max(1, (int) $t->ends_at->diffInWeekdays($taskForecast));
            } elseif ($isCompleted && $t->baseline_ends_at && $t->ends_at->greaterThan($t->baseline_ends_at)) {
                $taskDelayDays = max(1, (int) $t->baseline_ends_at->diffInWeekdays($t->ends_at));
            }

            // Float
            $floatMinutes = $cpmTasks[$t->id]['float_minutes'] ?? 0;
            $floatDays = $isCritical ? 0 : (int) round($floatMinutes / 480);

            // Critical Path Impact
            if ($isCritical) {
                $criticalImpact = 'affects_deadline';
                $criticalImpactLabel = 'Affects Project Deadline';
                $criticalImpactClass = 'impact-critical';
            } elseif ($taskDelayDays > $floatDays) {
                $criticalImpact = 'affects_deadline';
                $criticalImpactLabel = 'Exceeds Float Buffer (Affects Deadline)';
                $criticalImpactClass = 'impact-warning';
            } else {
                $criticalImpact = 'no_impact';
                $criticalImpactLabel = "Buffer Absorbed ({$floatDays}d float remaining)";
                $criticalImpactClass = 'impact-neutral';
            }

            // Assignees
            $assigneesList = $t->assignees->pluck('name')->all();
            $primaryAssignee = $t->assignees->first();
            $assigneeName = $primaryAssignee?->name ?? 'Not assigned';
            $assigneeInitials = '—';
            if ($primaryAssignee && !empty($primaryAssignee->name)) {
                $parts = preg_split('/\s+/', trim($primaryAssignee->name));
                $assigneeInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
            }

            // Predecessors & Successors
            $blockingPredecessors = [];
            foreach ($t->dependencies as $dep) {
                if ($dep->progress < 100 && $dep->status !== 'completed') {
                    $blockingPredecessors[] = [
                        'id' => $dep->id,
                        'code' => $dep->code,
                        'title' => $dep->title,
                        'progress' => $dep->progress,
                        'is_overdue' => $dep->ends_at->lessThan($asOf),
                    ];
                }
            }

            $blockedSuccessors = [];
            $successorIds = $successorsMap[$t->id] ?? [];
            foreach ($successorIds as $sId) {
                $suc = $allById->get($sId);
                if ($suc && $suc->progress < 100) {
                    $blockedSuccessors[] = [
                        'id' => $suc->id,
                        'code' => $suc->code,
                        'title' => $suc->title,
                    ];
                }
            }

            // Detected Schedule Problem
            if ($isOverdue && !empty($blockingPredecessors)) {
                $predTitle = $blockingPredecessors[0]['title'];
                $detectedProblem = "Overdue by {$taskDelayDays} working days and waiting for predecessor: {$predTitle}.";
            } elseif ($isOverdue) {
                $detectedProblem = "Overdue by {$taskDelayDays} working " . ($taskDelayDays === 1 ? 'day' : 'days') . " with {$t->progress}% progress.";
            } elseif (!empty($blockingPredecessors)) {
                $predTitle = $blockingPredecessors[0]['title'];
                $detectedProblem = "Blocked by incomplete predecessor: {$predTitle}.";
            } elseif ($taskDelayDays > 0) {
                $detectedProblem = "Forecast completion exceeds planned finish by {$taskDelayDays} " . ($taskDelayDays === 1 ? 'working day.' : 'working days.');
            } elseif ($isCompleted) {
                $detectedProblem = "Work completed after approved planned deadline.";
            } else {
                $detectedProblem = "Schedule progressing normally.";
            }

            // Real Reported Root Cause (TaskDelay)
            $openDelay = $t->openDelay;
            $reportedRootCause = null;
            $hasReportedRootCause = false;
            $correctiveAction = null;
            $actionOwner = null;
            $resolutionDate = null;
            $resolutionStatus = 'Unreported';
            $involvedParty = null;

            if ($openDelay) {
                $hasReportedRootCause = true;
                $reportedRootCause = $openDelay->reason_label;
                $correctiveAction = $openDelay->details ?: 'None recorded';
                $actionOwner = $openDelay->actionOwner?->name ?: 'None assigned';
                $resolutionDate = $openDelay->revised_expected_end_at ? $openDelay->revised_expected_end_at->format('d M Y') : null;
                $resolutionStatus = ucfirst($openDelay->status);
                $partyType = match($openDelay->responsible_party_type) {
                    'supplier' => 'External Supplier / Vendor',
                    'internal' => 'Internal Department',
                    'department' => 'Other Department',
                    'external' => 'External Factor',
                    default => 'Internal Team',
                };
                $involvedParty = $openDelay->responsible_party_name ? "{$openDelay->responsible_party_name} ({$partyType})" : $partyType;
            } else {
                if ($isOverdue) {
                    $actionOwner = ($assigneeName !== 'Not assigned' ? $assigneeName : ($project->manager?->name ?? 'Project Manager'));
                    $correctiveAction = "Action Required: Review task progress and establish revised delivery target with {$actionOwner}.";
                    $resolutionStatus = 'Action Required';
                    $delayReasonText = "Schedule Overdue: End date was {$t->ends_at->format('d M Y')}, but work is {$t->progress}% complete. Delayed by {$taskDelayDays} " . ($taskDelayDays === 1 ? 'working day.' : 'working days.');
                } elseif ($isCritical) {
                    $actionOwner = ($assigneeName !== 'Not assigned' ? $assigneeName : ($project->manager?->name ?? 'Project Manager'));
                    $correctiveAction = "Prioritize task execution to protect critical path milestone deadline.";
                    $resolutionStatus = 'Action Required';
                    $delayReasonText = "Critical Path Bottleneck: 0 days total float.";
                } else {
                    $actionOwner = 'None recorded';
                    $correctiveAction = 'None recorded';
                    $resolutionStatus = 'Unreported';
                    $delayReasonText = 'Underlying reason not reported';
                }
            }

            return [
                'id' => $t->id,
                'number' => $taskNumbers[$t->id] ?? $t->code,
                'code' => $t->code,
                'title' => $t->title,
                'assignees' => $assigneesList ?: ['Not assigned'],
                'assignees_text' => !empty($assigneesList) ? implode(', ', $assigneesList) : 'Not assigned',
                'assignee_name' => $assigneeName,
                'assignee_initials' => $assigneeInitials,
                'assigned_owner' => $assigneeName,
                'planned_end' => $t->ends_at->format('d M Y'),
                'planned_end_time' => $t->ends_at->format('d M Y, H:i'),
                'forecast_end' => $taskForecast ? $taskForecast->format('d M Y') : $t->ends_at->format('d M Y'),
                'status' => $t->status,
                'status_label' => ucwords(str_replace('_', ' ', $t->status)),
                'progress' => $t->progress,
                'is_critical' => $isCritical,
                'critical_label' => $isCritical ? 'Yes' : 'No',
                'is_overdue' => $isOverdue,
                'is_completed' => $isCompleted,
                'delay_days' => $taskDelayDays,
                'delay_badge' => $taskDelayDays > 0 ? "+{$taskDelayDays} " . ($taskDelayDays === 1 ? 'day' : 'days') : '0 days',
                'overdue_duration' => "{$taskDelayDays} " . ($taskDelayDays === 1 ? 'working day' : 'working days'),
                'float_minutes' => $floatMinutes,
                'float_days' => $floatDays,
                'float_text' => "{$floatDays} " . ($floatDays === 1 ? 'day' : 'days'),
                'critical_impact' => $criticalImpact,
                'critical_impact_label' => $criticalImpactLabel,
                'critical_impact_class' => $criticalImpactClass,
                'detected_problem' => $detectedProblem,
                'blocking_predecessors' => $blockingPredecessors,
                'blocked_successors' => $blockedSuccessors,
                'has_delay_report' => $hasReportedRootCause,
                'open_delay_id' => $openDelay?->id,
                'reported_root_cause' => $reportedRootCause,
                'delay_reason' => $hasReportedRootCause ? $reportedRootCause : ($delayReasonText ?? 'Underlying reason not reported'),
                'involved_party' => $involvedParty ?: 'Not recorded',
                'corrective_action' => $correctiveAction ?: 'None recorded',
                'action_owner' => $actionOwner ?: 'None recorded',
                'confirmed_action_owner' => $hasReportedRootCause && $openDelay->actionOwner ? $openDelay->actionOwner->name : null,
                'confirmed_corrective_action' => $hasReportedRootCause && $openDelay->details ? $openDelay->details : null,
                'has_confirmed_corrective_action' => $hasReportedRootCause && !empty($openDelay->details),
                'resolution_date' => $resolutionDate ?: '—',
                'resolution_status' => $resolutionStatus,
                'can_add_reason' => ($user && ($user->isPmo() || $project->pm_user_id === $user->id || $t->assignees->contains('id', $user->id))) && in_array($project->status, ['active', 'on_hold'], true),
                'store_delay_url' => route('tasks.delays.store', $t),
                'resolve_delay_url' => $openDelay ? route('tasks.delays.resolve', $openDelay) : null,
            ];
        };

        // Format Delayed Tasks and Overdue Tasks
        $delayedTasksData = $delayedLeaves->map(fn ($t) => $formatTaskRow($t, true))->values()->all();
        $overdueTasksData = $overdueLeaves->map(fn ($t) => $formatTaskRow($t, false))->values()->all();

        // Critical Path Sequence for Visual Diagram (Section C)
        $criticalTasks = $visibleLeaves->filter(fn ($t) => in_array($t->id, $criticalIds, true));
        $criticalPathSequence = [];

        // Check if root tasks form phases or tasks
        $rootTasks = $tasks->whereNull('parent_task_id')->sortBy('sort_order')->values();
        if ($rootTasks->count() >= 2) {
            foreach ($rootTasks as $rt) {
                $descendantIds = $tasks->where('parent_task_id', $rt->id)->pluck('id')->push($rt->id)->all();
                $isPhaseDelayed = $overdueLeaves->contains(fn ($t) => in_array($t->id, $descendantIds, true))
                    || ($rt->progress < 100 && $rt->ends_at && $rt->ends_at->lessThan($asOf));
                $num = $taskNumbers[$rt->id] ?? '';
                $criticalPathSequence[] = [
                    'id' => $rt->id,
                    'number' => $num,
                    'name' => trim($num . ' ' . $rt->title),
                    'is_delayed' => $isPhaseDelayed,
                    'is_overrun' => $isPhaseDelayed,
                    'status' => $isPhaseDelayed ? 'delayed' : ($rt->progress >= 100 ? 'completed' : 'on_track'),
                ];
            }
        } elseif ($criticalTasks->isNotEmpty()) {
            foreach ($criticalTasks->sortBy('starts_at') as $ct) {
                $isOverdue = ($ct->progress < 100 && $ct->ends_at->lessThan($asOf));
                $tForecast = $taskForecasts[$ct->id] ?? null;
                $isForecastLate = ($ct->progress < 100 && $tForecast && $tForecast->greaterThan($ct->ends_at));
                $num = $taskNumbers[$ct->id] ?? '';

                $criticalPathSequence[] = [
                    'id' => $ct->id,
                    'number' => $num,
                    'name' => trim($num . ' ' . $ct->title),
                    'is_delayed' => ($isOverdue || $isForecastLate),
                    'is_overrun' => ($isOverdue || $isForecastLate),
                    'status' => ($isOverdue || $isForecastLate) ? 'delayed' : ($ct->progress >= 100 ? 'completed' : 'on_track'),
                ];
            }
        }

        // Critical Path Impact Explanation (Section C)
        $criticalImpactExplanation = '';
        if (!$cpmReliable) {
            $criticalImpactExplanation = 'Critical Path unavailable because task dependency information is incomplete.';
        } elseif ($criticalTasks->isEmpty()) {
            $criticalImpactExplanation = 'No critical path tasks identified for this schedule.';
        } else {
            $delayedCritical = collect($delayedTasksData)->first(fn ($t) => !empty($t['is_critical']));
            if ($delayedCritical && !empty($delayedCritical['blocked_successors'])) {
                $firstSuc = $delayedCritical['blocked_successors'][0]['title'];
                $criticalImpactExplanation = "{$firstSuc} depends on {$delayedCritical['title']}. Because {$delayedCritical['title']} is late, downstream tasks will finish later than planned.";
            } elseif ($delayedCritical) {
                $criticalImpactExplanation = "{$delayedCritical['title']} has zero float buffer. Any schedule delay directly extends the overall project duration.";
            } else {
                $criticalImpactExplanation = 'All tasks on the critical path are progressing on schedule.';
            }
        }

        // Determine Effective Health State
        $healthState = match (true) {
            $project->status === 'completed' => 'completed',
            $project->status === 'archived' => 'archived',
            !in_array($project->status, ['active', 'on_hold'], true) => 'draft',
            $isPastApprovedDeadline => 'delayed',
            $forecastLate => 'forecast_late',
            $overdueLeaves->isNotEmpty() || $project->tasks()->whereHas('openDelay')->exists() => 'at_risk',
            $tasks->isEmpty() => 'insufficient_data',
            default => 'on_track',
        };

        $healthBadge = match ($healthState) {
            'delayed' => 'Delayed',
            'forecast_late' => 'Forecast Late',
            'at_risk' => 'At Risk',
            'on_track' => 'On Track',
            'completed' => 'Completed',
            'archived' => 'Archived',
            'insufficient_data' => 'Insufficient Schedule Data',
            default => ucfirst(str_replace('_', ' ', $project->status)),
        };

        // Raw Diagnosis Array for explanation generator
        $rawDiagnosis = [
            'has_tasks' => $tasks->isNotEmpty(),
            'cpm_reliable' => $cpmReliable,
            'cpm_invalid_network' => $invalidNetwork,
            'forecast' => $forecast,
            'variance_working_days' => $varianceWorkingDays,
            'delayed_tasks' => $delayedTasksData,
            'overdue_tasks' => $overdueTasksData,
        ];

        // SECTION A: Rule-Based Evidence-Backed Explanation
        $whyDelayedExplanation = $this->explanationService->generate($project, $rawDiagnosis);

        // Project Members for Add Reason owner selector
        $projectMembers = $project->memberships()->with('user:id,name,email')->get()->map(fn ($m) => [
            'id' => $m->user_id,
            'name' => $m->user?->name ?? 'User #' . $m->user_id,
        ])->all();

        if ($project->pm_user_id && !in_array($project->pm_user_id, array_column($projectMembers, 'id'))) {
            array_unshift($projectMembers, [
                'id' => $project->pm_user_id,
                'name' => $project->manager?->name ?? 'Project Manager',
            ]);
        }

        $cpmWarning = $tasks->isEmpty()
            ? 'No tasks have been added yet.'
            : ($invalidNetwork ? 'Dependency network contains a cycle or invalid links.' :
              (!$cpmReliable ? 'Critical Path unavailable because task dependency information is incomplete.' : null));

        return [
            'status' => 'success',
            'investigation' => [
                'header' => [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'project_code' => $project->code,
                    'status' => $project->status,
                    'status_label' => ucwords(str_replace('_', ' ', $project->status)),
                    'health_state' => $healthState,
                    'health_badge' => $healthBadge,
                    'health_badge_class' => match ($healthState) {
                        'delayed', 'forecast_late' => 'pill-delayed',
                        'at_risk' => 'pill-warning',
                        'on_track', 'completed' => 'pill-success',
                        default => 'pill-neutral',
                    },
                    'manager_name' => $project->manager?->name ?? 'Not assigned',
                    'subsidiary_name' => $project->subsidiary?->name ?? 'Company Project',
                    'open_project_url' => route('projects.show', $project),
                ],
                'section_a' => [
                    'heading' => 'Why Is This Project Delayed?',
                    'explanation' => $whyDelayedExplanation,
                    'is_delayed' => in_array($healthState, ['delayed', 'forecast_late', 'at_risk'], true),
                ],
                'section_b' => [
                    'heading' => 'Project Schedule Impact',
                    'approved_deadline' => $project->ends_on->format('d M Y'),
                    'forecast_completion' => $forecast ? $forecast->format('d M Y') : ($varianceWorkingDays > 0 ? $project->ends_on->copy()->addWeekdays($varianceWorkingDays)->format('d M Y') : 'Forecast unavailable'),
                    'schedule_variance' => $varianceText,
                    'schedule_variance_pill' => $variancePill,
                    'variance_working_days' => $varianceWorkingDays,
                    'health_state' => $healthState,
                    'health_label' => $healthBadge,
                    'is_delayed' => ($healthState === 'delayed'),
                    'is_at_risk' => in_array($healthState, ['at_risk', 'forecast_late'], true),
                ],
                'section_c' => [
                    'heading' => 'Critical Path & Affected Dependencies',
                    'is_reliable' => $cpmReliable,
                    'warning' => $cpmWarning,
                    'sequence' => $criticalPathSequence,
                    'impact_explanation' => $criticalImpactExplanation,
                    'schedule_details' => [
                        'cpm_duration_working_minutes' => $cpmResult['duration'] ?? 0,
                        'cpm_duration_working_days' => round(($cpmResult['duration'] ?? 0) / 480, 1),
                        'total_tasks' => $tasks->count(),
                        'dependency_links' => $cpmResult['links'] ?? 0,
                        'critical_tasks_count' => count($criticalIds),
                    ],
                ],
                'section_d' => [
                    'heading' => 'Delayed Tasks',
                    'count' => count($delayedTasksData),
                    'tasks' => $delayedTasksData,
                ],
                'section_e' => [
                    'heading' => 'Overdue Tasks',
                    'count' => count($overdueTasksData),
                    'tasks' => $overdueTasksData,
                ],
                'section_f' => [
                    'delay_reasons_catalog' => TaskDelayController::REASONS,
                    'project_members' => $projectMembers,
                ],
            ],

            // Backward Compatibility Keys for existing tests
            'project' => [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'status' => $project->status,
                'status_label' => ucwords(str_replace('_', ' ', $project->status)),
                'manager_name' => $project->manager?->name ?? 'Not assigned',
                'subsidiary_name' => $project->subsidiary?->name ?? 'Company Project',
                'approved_deadline' => $project->ends_on->format('d M Y'),
                'show_url' => route('projects.show', $project),
            ],
            'health' => [
                'key' => ($healthState === 'forecast_late' ? 'delayed' : $healthState),
                'label' => $healthBadge,
                'is_delayed' => in_array($healthState, ['delayed', 'forecast_late'], true),
                'is_at_risk' => ($healthState === 'at_risk'),
                'delay_working_days' => $varianceWorkingDays,
            ],
            'summary' => [
                'approved_deadline' => $project->ends_on->format('d M Y'),
                'forecast_deadline' => $forecast ? $forecast->format('d M Y') : ($varianceWorkingDays > 0 ? $project->ends_on->copy()->addWeekdays($varianceWorkingDays)->format('d M Y') : $project->ends_on->format('d M Y')),
                'schedule_variance' => $varianceText,
                'schedule_variance_pill' => $variancePill,
                'overdue_tasks_count' => count($overdueTasksData),
                'critical_overdue_tasks_count' => count(array_filter($overdueTasksData, fn ($t) => !empty($t['is_critical']))),
                'delayed_critical_tasks_count' => count(array_filter($delayedTasksData, fn ($t) => !empty($t['is_critical']))),
                'critical_delays_count' => count(array_filter($delayedTasksData, fn ($t) => !empty($t['is_critical']))),
                'total_tasks_count' => $tasks->count(),
                'has_tasks' => $tasks->isNotEmpty(),
                'diagnostic_summary' => $whyDelayedExplanation,
            ],
            'critical_path' => [
                'has_tasks' => $tasks->isNotEmpty(),
                'reliable' => $cpmReliable,
                'warning' => $cpmWarning,
                'sequence' => $criticalPathSequence,
                'sequence_text' => implode(' → ', array_column($criticalPathSequence, 'name')),
                'tasks' => array_values(array_filter($delayedTasksData, fn ($t) => !empty($t['is_critical']))),
            ],
            'overdue_section' => [
                'summary_text' => !empty($overdueTasksData)
                    ? count($overdueTasksData) . ' overdue ' . (count($overdueTasksData) === 1 ? 'task' : 'tasks')
                    : 'No overdue tasks in this project.',
                'count' => count($overdueTasksData),
                'tasks' => $overdueTasksData,
            ],
            'delayed_section' => [
                'count' => count($delayedTasksData),
                'tasks' => $delayedTasksData,
            ],
            'delay_reasons_catalog' => TaskDelayController::REASONS,
            'project_members' => $projectMembers,
        ];
    }
}
