<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Pure Critical Path Method (CPM) for a directed acyclic task network.
 * Durations are expressed in working minutes by the caller.
 * Planned calendar gaps are not automatically dependencies.
 */
class CriticalPathCalculator
{
    /** @param array<int|string,array{duration:int|float,predecessors:array}> $nodes */
    public function calculate(array $nodes): array
    {
        if (!$nodes) return ['duration' => 0, 'tasks' => [], 'path' => [], 'links' => 0];
        $successors = []; $indegree = []; $links = 0;
        foreach ($nodes as $id => $node) {
            if (!is_numeric($node['duration'] ?? null) || $node['duration'] <= 0) {
                throw new InvalidArgumentException("Task {$id} must have a positive duration.");
            }
            $indegree[$id] = 0;
            $successors[$id] = [];
        }
        foreach ($nodes as $id => $node) {
            foreach (array_unique($node['predecessors'] ?? []) as $parent) {
                if (!array_key_exists($parent, $nodes)) {
                    throw new InvalidArgumentException("Task {$id} depends on an unknown task {$parent}.");
                }
                if ((string) $id === (string) $parent) {
                    throw new InvalidArgumentException('Tasks cannot depend on themselves.');
                }
                $successors[$parent][] = $id;
                $indegree[$id]++;
                $links++;
            }
        }
        $queue = [];
        foreach ($indegree as $id => $count) if ($count === 0) $queue[] = $id;
        $order = [];
        while ($queue) {
            $id = array_shift($queue); $order[] = $id;
            foreach ($successors[$id] as $next) {
                if (--$indegree[$next] === 0) $queue[] = $next;
            }
        }
        if (count($order) !== count($nodes)) throw new InvalidArgumentException('A cycle exists in task dependencies.');

        $result = [];
        foreach ($order as $id) {
            $earliest = 0;
            foreach (array_unique($nodes[$id]['predecessors'] ?? []) as $parent) {
                $earliest = max($earliest, $result[$parent]['early_finish']);
            }
            $result[$id] = [
                'early_start' => $earliest,
                'early_finish' => $earliest + (int) ceil($nodes[$id]['duration']),
            ];
        }
        $duration = max(array_column($result, 'early_finish'));
        foreach (array_reverse($order) as $id) {
            $latestFinish = $successors[$id]
                ? min(array_map(fn ($child) => $result[$child]['late_start'], $successors[$id]))
                : $duration;
            $result[$id]['late_finish'] = $latestFinish;
            $result[$id]['late_start'] = $latestFinish - (int) ceil($nodes[$id]['duration']);
            $result[$id]['float_minutes'] = max(0, $result[$id]['late_start'] - $result[$id]['early_start']);
            $result[$id]['critical'] = $result[$id]['float_minutes'] === 0;
        }
        return [
            'duration' => $duration,
            'tasks' => $result,
            'path' => array_keys(array_filter($result, fn ($task) => $task['critical'])),
            'links' => $links,
        ];
    }
}
