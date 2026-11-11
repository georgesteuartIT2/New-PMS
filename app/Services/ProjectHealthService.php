<?php

namespace App\Services;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/** Read-only project health assessment, not a statement of fault or blame. */
class ProjectHealthService
{
    public function __construct(private CriticalPathCalculator $cpm, private WorkingSchedule $calendar) {}

    public function assess(Project $project, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $tasks = $project->tasks()->with(['dependencies:id', 'assignees:id,name', 'openDelay.actionOwner', 'children:id,parent_task_id'])->orderBy('sort_order')->get();
        $leaves = $tasks->filter(fn ($task) => $task->children->isEmpty())->values();
        $allById = $tasks->keyBy('id');
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
        $nodes = [];
        $invalidLinks = 0;
        $workHours = $project->template?->working_hours;
        foreach ($leaves as $task) {
            $duration = $this->calendar->minutesBetween($task->starts_at, $task->ends_at, $workHours);
            if ($duration < 1) {
                // Off-calendar work needs custom shifts: use elapsed time rather than invent zero duration.
                $duration = max(1, (int) $task->starts_at->diffInMinutes($task->ends_at));
            }
            $predecessors = [];
            $cursor = $task;
            $seenAncestors = [];
            while ($cursor && !isset($seenAncestors[$cursor->id])) {
                $seenAncestors[$cursor->id] = true;
                // A main-task dependency applies to its descendant leaf work.
                foreach ($cursor->dependencies as $dependency) {
                    if (!isset($leafByAncestor[$dependency->id])) {
                        $invalidLinks++;
                        continue;
                    }
                    foreach ($leafByAncestor[$dependency->id] as $predecessorId) {
                        if ($predecessorId !== $task->id) $predecessors[] = $predecessorId;
                    }
                }
                $cursor = $cursor->parent_task_id ? $allById->get($cursor->parent_task_id) : null;
            }
            $nodes[$task->id] = ['duration' => $duration, 'predecessors' => array_values(array_unique($predecessors))];
        }
        try {
            $network = $this->cpm->calculate($nodes);
            $invalidNetwork = false;
        } catch (InvalidArgumentException $e) {
            $network = ['duration' => 0, 'tasks' => [], 'path' => [], 'links' => 0];
            $invalidNetwork = true;
        }
        $reliable = !$invalidNetwork && $invalidLinks === 0 && ($network['links'] > 0 || count($nodes) <= 1);
        $criticalIds = $reliable ? $network['path'] : [];

        $overdue = $leaves->filter(fn ($task) => $task->progress < 100 && $task->ends_at->lessThan($asOf));
        $unassigned = $leaves->filter(fn ($task) => $task->progress < 100 && $task->assignees->isEmpty());
        $openDelays = $leaves->filter(fn ($task) => $task->openDelay !== null);
        $criticalTasks = $leaves->filter(fn ($task) => in_array($task->id, $criticalIds, true));

        // Conservative forecast: incomplete work uses remaining duration as an estimate.
        // This is NOT an approved revised project deadline, nor an actual completion timestamp.
        $forecast = null;
        if ($leaves->isNotEmpty() && !$invalidNetwork) {
            $forecastEnd = [];
            $order = array_keys($network['tasks']);
            usort($order, fn ($a, $b) => $network['tasks'][$a]['early_start'] <=> $network['tasks'][$b]['early_start']);
            foreach ($order as $id) {
                $task = $leaves->firstWhere('id', $id);
                if (!$task) continue;
                $starts = $task->starts_at->copy();
                foreach ($nodes[$id]['predecessors'] as $depId) {
                    if (isset($forecastEnd[$depId]) && $forecastEnd[$depId]->greaterThan($starts)) {
                        $starts = $forecastEnd[$depId]->copy();
                    }
                }
                if ($task->progress >= 100) {
                    $forecastEnd[$id] = $starts->greaterThan($task->ends_at) ? $starts : $task->ends_at->copy();
                    continue;
                }
                if ($starts->lessThan($asOf)) $starts = $asOf->copy();
                $remaining = (int) ceil($nodes[$id]['duration'] * (100 - $task->progress) / 100);
                $forecastEnd[$id] = $this->calendar->addMinutes($starts, $remaining, $workHours);
            }
            foreach ($forecastEnd as $date) if (!$forecast || $date->greaterThan($forecast)) $forecast = $date->copy();
        }
        // The project deadline includes its whole day; forecasts are datetime values.
        $deadline = $project->ends_on->copy()->endOfDay();
        $forecastLate = $forecast && $forecast->greaterThan($deadline);
        $pastDeadline = $deadline->lessThan($asOf) && !in_array($project->status, ['completed','archived'], true);
        $health = match (true) {
            $project->status === 'completed' => 'completed',
            $project->status === 'archived' => 'archived',
            !in_array($project->status, ['active','on_hold'], true) => 'not_started',
            $project->approval_stage==='planning' => 'not_started',
            $pastDeadline => 'overdue',
            $forecastLate || $overdue->isNotEmpty() || $openDelays->isNotEmpty() => 'at_risk',
            default => 'on_track',
        };
        return [
            'health' => $health,
            'tasks' => $tasks,
            'leaves' => $leaves,
            'task_numbers' => TaskTree::numbers($tasks),
            'critical_ids' => $criticalIds,
            'critical_tasks' => $criticalTasks,
            'cpm_tasks' => $network['tasks'] ?? [],
            'dependency_nodes' => $nodes,
            'cpm_reliable' => $reliable,
            'cpm_warning' => $invalidNetwork ? 'Review the task dependency network: a cycle or invalid dependency exists.' :
                ($invalidLinks ? 'Some dependencies refer to missing or inaccessible tasks. Review those links.' :
                (!$reliable ? 'Add task dependencies to calculate a reliable critical path. The system will not guess the order of work.' : null)),
            'network_minutes' => $network['duration'],
            'overdue_tasks' => $overdue,
            'unassigned_tasks' => $unassigned,
            'open_delays' => $openDelays,
            'forecast' => $forecast,
            'forecast_late' => $forecastLate,
            'baseline_available' => $project->baseline_captured_at !== null,
        ];
    }
}
