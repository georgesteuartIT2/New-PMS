<?php

namespace App\Http\Controllers;

use App\Models\{Project, Task, User};
use App\Services\{Access, Audit, ProjectService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskBulkController
{
    /**
     * Atomically bulk-update multiple project tasks, assignees, dates, statuses, and dependencies.
     */
    public function update(Request $request, Project $project, Access $access, ProjectService $projectService)
    {
        // 1. Authorization
        abort_unless($access->canManageTask($request->user(), $project), 403, 'You do not have permission to manage tasks in this project.');
        abort_if($project->status === 'archived', 403, 'Archived projects cannot be modified.');

        // 2. Validate request payload
        $data = $request->validate([
            'tasks' => 'required|array|min:1',
            'tasks.*.title' => 'required|string|max:200',
            'tasks.*.start_date' => 'required|date',
            'tasks.*.start_time' => 'required|string',
            'tasks.*.end_date' => 'required|date',
            'tasks.*.end_time' => 'required|string',
            'tasks.*.progress' => 'required|integer|min:0|max:100',
            'tasks.*.status' => 'required|in:todo,in_progress,on_hold,completed',
            'tasks.*.assigned_user_id' => 'nullable|integer|exists:users,id',
            'tasks.*.add_to_project' => 'sometimes|boolean',
            'tasks.*.dependencies' => 'nullable|array',
            'tasks.*.dependencies.*' => 'integer',
        ], [
            'tasks.*.title.required' => 'Task name is required.',
            'tasks.*.start_date.required' => 'Start date is required.',
            'tasks.*.end_date.required' => 'End date is required.',
        ]);

        $taskIds = array_keys($data['tasks']);
        $existingTasks = $project->tasks()->whereIn('id', $taskIds)->get()->keyBy('id');

        abort_unless($existingTasks->count() === count($taskIds), 422, 'Some tasks do not belong to this project.');

        // Eligible assignees: PM and active project members
        $eligibleAssigneeIds = $project->memberships()->pluck('user_id')
            ->push($project->pm_user_id)
            ->filter()
            ->unique()
            ->map(fn($v) => (int)$v)
            ->all();

        // 3. Row-level validations
        $errors = [];
        $newDependenciesMap = [];
        $newMembers = [];

        foreach ($data['tasks'] as $id => $row) {
            $task = $existingTasks->get($id);
            if (!$task) continue;

            $startTime = trim($row['start_time']) ?: '09:00';
            $endTime = trim($row['end_time']) ?: '17:00';

            try {
                $startsAt = Carbon::parse($row['start_date'] . ' ' . $startTime);
                $endsAt = Carbon::parse($row['end_date'] . ' ' . $endTime);
            } catch (\Exception $e) {
                $errors["tasks.{$id}.end_date"] = "Task \"{$row['title']}\": Invalid date or time format.";
                continue;
            }

            if ($endsAt->lte($startsAt)) {
                $errors["tasks.{$id}.end_date"] = "Task \"{$row['title']}\": End date and time must be after start date and time.";
            }

            if (!empty($row['assigned_user_id'])) {
                $uid = (int)$row['assigned_user_id'];
                $person=User::whereKey($uid)->where('account_status','active')->where('system_role','user')->first();
                if(!$person){$errors["tasks.{$id}.assigned_user_id"]='Choose an active project user.';}
                elseif (!in_array($uid, $eligibleAssigneeIds, true)) {
                    if(!($row['add_to_project']??false))$errors["tasks.{$id}.assigned_user_id"]='Confirm adding this person to the project.';
                    elseif(!$access->project($request->user(),$project,'assign_tasks')||!$access->project($request->user(),$project,'manage_team'))abort(403);
                    else $newMembers[$uid]=$person;
                }
            }

            // Dependency rules: cannot depend on self, must belong to same project
            $depIds = array_values(array_unique(array_filter(array_map('intval', $row['dependencies'] ?? []))));
            if (in_array((int)$id, $depIds, true)) {
                $errors["tasks.{$id}.dependencies"] = "Task \"{$row['title']}\" cannot depend on itself.";
            }

            foreach ($depIds as $depId) {
                if (!$project->tasks()->whereKey($depId)->exists()) {
                    $errors["tasks.{$id}.dependencies"] = "Task \"{$row['title']}\" references an invalid predecessor task.";
                }
            }

            $newDependenciesMap[$id] = $depIds;
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        // 4. Validate dependency graph for circular cycles across the whole project
        $allProjectTasks = $project->tasks()->with('dependencies:id')->get();
        $graph = [];
        foreach ($allProjectTasks as $pt) {
            if (array_key_exists($pt->id, $newDependenciesMap)) {
                $graph[$pt->id] = $newDependenciesMap[$pt->id];
            } else {
                $graph[$pt->id] = $pt->dependencies->pluck('id')->all();
            }
        }

        // Detect cycles using DFS
        $visited = [];
        $recStack = [];
        $hasCycle = function($nodeId) use (&$hasCycle, &$visited, &$recStack, $graph) {
            $visited[$nodeId] = true;
            $recStack[$nodeId] = true;

            foreach ($graph[$nodeId] ?? [] as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    if ($hasCycle($neighbor)) return true;
                } elseif (!empty($recStack[$neighbor])) {
                    return true;
                }
            }

            $recStack[$nodeId] = false;
            return false;
        };

        foreach (array_keys($graph) as $nodeId) {
            if (!isset($visited[$nodeId])) {
                if ($hasCycle($nodeId)) {
                    throw ValidationException::withMessages([
                        'dependencies' => 'A circular dependency cycle was detected. Tasks cannot form a closed dependency loop.'
                    ]);
                }
            }
        }

        // 5. Database transaction for atomic persistence
        $updatedCount = 0;
        DB::transaction(function() use ($data, $existingTasks, $newDependenciesMap, $project, $projectService, $request, $newMembers, &$updatedCount) {
            if($newMembers){$role=\App\Models\ProjectRole::where('slug','team')->firstOrFail();foreach($newMembers as $person){\App\Models\ProjectMembership::firstOrCreate(['project_id'=>$project->id,'user_id'=>$person->id,'project_role_id'=>$role->id]);Audit::record('project.member_added',$project,['user_id'=>$person->id,'source'=>'bulk_task_edit']);}}
            foreach ($data['tasks'] as $id => $row) {
                $task = $existingTasks->get($id);
                $startTime = trim($row['start_time']) ?: '09:00';
                $endTime = trim($row['end_time']) ?: '17:00';

                $startsAt = Carbon::parse($row['start_date'] . ' ' . $startTime);
                $endsAt = Carbon::parse($row['end_date'] . ' ' . $endTime);

                $normalized=$task->normalizeProgressInput(['status'=>$row['status'],'progress'=>(int)$row['progress']]);
                $status=$normalized['status'];$progress=$normalized['progress'];

                $task->update([
                    'title' => $row['title'],
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'progress' => $progress,
                    'status' => $status,
                ]);

                // Sync assignees
                if (!empty($row['assigned_user_id'])) {
                    $uid=(int)$row['assigned_user_id'];$already=$task->assignees()->where('users.id',$uid)->exists();
                    if(!$already){$task->assignees()->sync([$uid]);if(in_array($project->status,['active','on_hold'])&&$project->approval_stage!=='planning'){ $person=User::findOrFail($uid);DB::afterCommit(fn()=>$person->notify(new \App\Notifications\TaskReassignedNotification($task,$request->user())));}}

                } else {
                    $task->assignees()->detach();
                }

                // Sync dependencies
                $task->dependencies()->sync($newDependenciesMap[$id] ?? []);

                $updatedCount++;
            }

            // Recalculate project progress and CPM schedule
            $projectService->recalculate($project);

            // Audit record
            Audit::record('tasks.bulk_updated', $project, [
                'updated_count' => $updatedCount,
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} tasks.",
                'updated_count' => $updatedCount,
                'project_progress' => $project->fresh()->progress,
            ]);
        }

        return back()->with('success', "All {$updatedCount} project tasks have been updated successfully.");
    }
}

