<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Subsidiary;
use App\Models\Task;
use App\Models\TaskAssignmentHistory;
use App\Models\User;
use App\Notifications\TaskReassignedNotification;
use App\Services\Access;
use App\Services\Audit;
use App\Services\EmployeeWorkloadService;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamMonitorController
{
    public function __construct(
        private EmployeeWorkloadService $workloadService,
        private Access $access,
        private ProjectService $projectService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->isPmo() && !$this->hasMonitoringAccess($user)) {
            abort(403, 'Unauthorized. Company-wide team monitoring requires PMO Administrator authority.');
        }

        $filters = [
            'q' => $request->input('q'),
            'subsidiary_id' => $request->input('subsidiary_id'),
            'project_id' => $request->input('project_id'),
            'workload' => $request->input('workload'),
            'has_overdue' => $request->boolean('has_overdue'),
            'has_critical' => $request->boolean('has_critical'),
        ];

        $summary = $this->workloadService->getTeamSummaryMetrics($user);
        $employees = $this->workloadService->getFilteredEmployees($user, $filters, 25);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'summary' => $summary,
                'employees' => $employees,
            ]);
        }

        $subsidiaries = Subsidiary::orderBy('name')->get(['id', 'code', 'name']);
        $projectsQuery = Project::whereIn('status', ['active', 'on_hold'])->orderBy('name');
        if (!$user->isPmo()) {
            $projectsQuery->where(function ($q) use ($user) {
                $q->whereHas('memberships', fn($m) => $m->where('user_id', $user->id))
                  ->orWhere('pm_user_id', $user->id);
            });
        }
        $projects = $projectsQuery->get(['id', 'code', 'name']);

        return view('pmo.team-monitor.index', compact(
            'summary',
            'employees',
            'filters',
            'subsidiaries',
            'projects'
        ));
    }

    public function employeeDetails(Request $request, User $user)
    {
        $actor = $request->user();
        if (!$actor->isPmo() && !$this->hasMonitoringAccess($actor)) {
            abort(403, 'Unauthorized to view employee workload details.');
        }

        $details = $this->workloadService->getEmployeeDetails($user, $actor);

        return response()->json([
            'success' => true,
            'data' => $details,
        ]);
    }

    public function reassign(Request $request)
    {
        $actor = $request->user();

        $data = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'old_user_id' => 'required|exists:users,id',
            'new_user_id' => 'required|exists:users,id|different:old_user_id',
            'reason' => 'nullable|string|max:500',
        ]);

        $task = Task::with('project.memberships')->findOrFail($data['task_id']);

        // Check permission
        if (!$actor->isPmo() && !$this->access->canManageTask($actor, $task->project)) {
            abort(403, 'Unauthorized to reassign tasks in this project.');
        }

        $oldUser = User::findOrFail($data['old_user_id']);
        $newUser = User::findOrFail($data['new_user_id']);

        // Eligible check: new user MUST be a member of the project or PM
        $isMember = $task->project->memberships()->where('user_id', $newUser->id)->exists();
        $isPm = ($task->project->pm_user_id === $newUser->id);

        if (!$isMember && !$isPm) {
            return response()->json([
                'success' => false,
                'message' => "{$newUser->name} is not an authorized member of project '{$task->project->name}'. Add them to the project team first.",
            ], 422);
        }

        DB::transaction(function () use ($task, $oldUser, $newUser, $actor, $data) {
            // Detach old assignee
            $task->assignees()->detach($oldUser->id);

            // Attach new assignee with 100% allocation
            $task->assignees()->syncWithoutDetaching([
                $newUser->id => [
                    'allocation_percent' => 100,
                    'assigned_at' => now(),
                ]
            ]);

            // Record in audit history table
            TaskAssignmentHistory::create([
                'task_id' => $task->id,
                'previous_user_id' => $oldUser->id,
                'new_user_id' => $newUser->id,
                'changed_by' => $actor->id,
                'action' => 'reassigned',
                'reason' => $data['reason'] ?? null,
                'created_at' => now(),
            ]);

            // General audit log
            Audit::record('task.reassigned', $task, [
                'from_user' => $oldUser->name,
                'to_user' => $newUser->name,
                'reason' => $data['reason'] ?? null,
            ]);
        });

        // Send notifications
        $newUser->notify(new TaskReassignedNotification($task, $actor, 'assigned', $data['reason'] ?? null));
        $oldUser->notify(new TaskReassignedNotification($task, $actor, 'unassigned', $data['reason'] ?? null));

        return response()->json([
            'success' => true,
            'message' => "Task successfully reassigned from {$oldUser->name} to {$newUser->name}.",
        ]);
    }

    private function hasMonitoringAccess(User $user): bool
    {
        return $this->access->hasCompanyPermission($user, 'reports.portfolio');
    }
}

