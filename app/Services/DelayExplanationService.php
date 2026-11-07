<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Deterministic, rule-based service that generates concise, evidence-based
 * explanations for why a project is delayed or at risk.
 * Never fabricates real-world root causes or assigns personal blame.
 */
class DelayExplanationService
{
    /**
     * Generate a 1 to 3 sentence explanation grounded strictly in calculated project data.
     *
     * @param Project $project
     * @param array $diagnosis
     * @return string
     */
    public function generate(Project $project, array $diagnosis): string
    {
        // 1. Completed Project
        if ($project->status === 'completed') {
            return 'The project is completed. All deliverables and final sign-offs have been recorded.';
        }

        // 2. Insufficient Schedule Data / Empty WBS
        if (empty($diagnosis['has_tasks'])) {
            return 'No tasks have been added to this project yet. A work breakdown schedule is required to assess schedule performance.';
        }

        if (!empty($diagnosis['cpm_invalid_network'])) {
            return 'Critical Path and schedule forecasts cannot be calculated reliably because task dependencies contain a circular dependency or invalid links.';
        }

        $now = now();
        $approvedDeadline = $project->ends_on->copy()->endOfDay();
        $isPastDeadline = $approvedDeadline->lessThan($now);
        $forecast = $diagnosis['forecast'] ?? null;
        $varianceDays = $diagnosis['variance_working_days'] ?? 0;
        $delayedTasks = collect($diagnosis['delayed_tasks'] ?? []);
        $overdueTasks = collect($diagnosis['overdue_tasks'] ?? []);
        $cpmReliable = $diagnosis['cpm_reliable'] ?? false;

        // 3. Project is On Track
        $hasDelayCondition = $isPastDeadline || ($forecast && $forecast->greaterThan($approvedDeadline)) || $overdueTasks->isNotEmpty() || $delayedTasks->isNotEmpty();
        if (!$hasDelayCondition) {
            return "All scheduled tasks and forecast milestones remain within the approved project deadline of {$project->ends_on->format('d M Y')}.";
        }

        $sentences = [];

        // Identify primary critical delay driver
        $criticalDelayed = $delayedTasks->first(fn ($t) => !empty($t['is_critical']));
        $primaryTask = $criticalDelayed ?: $delayedTasks->first();

        if ($primaryTask) {
            $taskTitle = $primaryTask['title'];
            $isOverdue = !empty($primaryTask['is_overdue']);
            $delayDays = $primaryTask['delay_days'] ?? 1;
            $dayText = $delayDays === 1 ? 'working day' : 'working days';
            $successors = $primaryTask['blocked_successors'] ?? [];

            // Primary sentence
            if ($isOverdue) {
                $statusPart = "is overdue by {$delayDays} {$dayText} and remains incomplete";
            } else {
                $statusPart = "is forecast to finish {$delayDays} {$dayText} past its planned end date";
            }

            // Check if real root cause was reported
            if (!empty($primaryTask['reported_root_cause'])) {
                $causeSnippet = " due to reported " . strtolower($primaryTask['reported_root_cause']);
                $sentences[] = "{$taskTitle} {$statusPart}{$causeSnippet}.";
            } else {
                $sentences[] = "{$taskTitle} {$statusPart}.";
            }

            // Downstream dependency sentence
            if (!empty($successors)) {
                $firstSuccessor = $successors[0]['title'] ?? 'downstream work';
                $otherSuccessorsCount = count($successors) - 1;
                $successorText = $firstSuccessor . ($otherSuccessorsCount > 0 ? " and {$otherSuccessorsCount} dependent tasks" : '');

                if ($cpmReliable && !empty($primaryTask['is_critical'])) {
                    $sentences[] = "{$successorText} is waiting for {$taskTitle} to finish, which pushes the forecast project completion beyond the approved deadline.";
                } else {
                    $sentences[] = "{$successorText} is waiting for {$taskTitle} to finish before dependent work can proceed.";
                }
            } elseif (!empty($primaryTask['is_critical'])) {
                if ($isPastDeadline) {
                    $sentences[] = "Because {$taskTitle} is on the critical path, this delay has pushed the overall project past its approved deadline.";
                } else {
                    $sentences[] = "Because {$taskTitle} is on the critical path with zero float buffer, this schedule overrun directly threatens the approved project completion date.";
                }
            } elseif (!empty($primaryTask['critical_impact']) && $primaryTask['critical_impact'] === 'no_impact') {
                $floatDays = $primaryTask['float_days'] ?? 0;
                $floatText = $floatDays === 1 ? 'working day' : 'working days';
                $sentences[] = "Its delay is currently absorbed by {$floatDays} {$floatText} of schedule float buffer, so it does not delay overall project completion.";
            }
        } elseif ($isPastDeadline) {
            $sentences[] = "The approved project deadline of {$project->ends_on->format('d M Y')} has passed by {$varianceDays} " . ($varianceDays === 1 ? 'working day' : 'working days') . " while unfinished work remains.";
        }

        // Secondary sentence: Mention non-critical overdue tasks if separate from the primary driver
        $nonCriticalOverdue = $overdueTasks->filter(fn ($t) => empty($t['is_critical']) && ($primaryTask === null || $t['id'] !== $primaryTask['id']));
        if ($nonCriticalOverdue->isNotEmpty() && count($sentences) < 3) {
            $nonCritCount = $nonCriticalOverdue->count();
            $sampleTitle = $nonCriticalOverdue->first()['title'];
            if ($nonCritCount === 1) {
                $sentences[] = "{$sampleTitle} is also overdue, but has sufficient schedule float and does not currently impact the project deadline.";
            } else {
                $sentences[] = "{$nonCritCount} non-critical tasks (including {$sampleTitle}) are also overdue, but their schedule buffers currently absorb the delay.";
            }
        }

        // Mention missing dependency info if CPM is not reliable
        if (!$cpmReliable && count($sentences) < 3 && empty($diagnosis['cpm_invalid_network'])) {
            $sentences[] = "Task dependencies have not been configured between all tasks, so downstream critical path impact cannot be fully quantified.";
        }

        // Return 1 to 3 concise sentences
        return implode(' ', array_slice($sentences, 0, 3));
    }
}

