<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Subsidiary;
use App\Models\Task;
use App\Models\TaskUpdateRequest;
use App\Models\User;
use App\Notifications\TaskUpdateRequestNotification;
use App\Services\Access;
use App\Services\Audit;
use App\Services\TaskMonitoringService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskMonitorController
{
    public function __construct(
        private TaskMonitoringService $taskMonitoringService,
        private Access $access
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->isPmo() && !$this->hasMonitoringAccess($user)) {
            abort(403, 'Unauthorized. Company-wide task monitoring requires PMO Administrator authority.');
        }

        $filters = [
            'filter' => $request->input('filter', 'all'),
            'project_id' => $request->input('project_id'),
            'subsidiary_id' => $request->input('subsidiary_id'),
            'user_id' => $request->input('user_id'),
            'status' => $request->input('status'),
            'q' => $request->input('q'),
        ];

        $summary = $this->taskMonitoringService->getSummaryMetrics($user);
        $tasks = $this->taskMonitoringService->getFilteredTasks($user, $filters, 25);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'summary' => $summary,
                'tasks' => $tasks,
            ]);
        }

        $projectsQuery = Project::whereIn('status', ['active', 'on_hold', 'draft'])->orderBy('name');
        if (!$user->isPmo()) {
            $projectsQuery->where(function ($q) use ($user) {
                $q->whereHas('memberships', fn($m) => $m->where('user_id', $user->id))
                  ->orWhere('pm_user_id', $user->id);
            });
        }
        $projects = $projectsQuery->get(['id', 'code', 'name']);

        $subsidiaries = Subsidiary::orderBy('name')->get(['id', 'code', 'name']);
        $assignees = User::where('account_status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('pmo.task-monitor.index', compact(
            'summary',
            'tasks',
            'filters',
            'projects',
            'subsidiaries',
            'assignees'
        ));
    }

    public function requestUpdate(Request $request, Task $task)
    {
        $user = $request->user();
        if (!$user->isPmo() && !$this->access->canManageTask($user, $task->project)) {
            abort(403, 'Unauthorized to request updates on this task.');
        }

        $data = $request->validate([
            'target_user_id' => 'required|exists:users,id',
            'message' => 'nullable|string|max:500',
        ]);

        $targetUser = User::findOrFail($data['target_user_id']);

        // Prevent duplicate spam requests within 24 hours
        $recent = TaskUpdateRequest::where('task_id', $task->id)
            ->where('target_user_id', $targetUser->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($recent) {
            return response()->json([
                'success' => false,
                'message' => "An update request was already sent to {$targetUser->name} within the last 24 hours.",
            ], 422);
        }

        $updateRequest = TaskUpdateRequest::create([
            'task_id' => $task->id,
            'requested_by' => $user->id,
            'target_user_id' => $targetUser->id,
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);

        $targetUser->notify(new TaskUpdateRequestNotification($task, $user, $data['message'] ?? null));

        Audit::record('task.update_requested', $task, [
            'target_user' => $targetUser->name,
            'message' => $data['message'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Update request delivered to {$targetUser->name}.",
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (!$user->isPmo() && !$this->hasMonitoringAccess($user)) {
            abort(403, 'Unauthorized to export task monitoring data.');
        }

        $filters = [
            'filter' => $request->input('filter', 'all'),
            'project_id' => $request->input('project_id'),
            'subsidiary_id' => $request->input('subsidiary_id'),
            'user_id' => $request->input('user_id'),
            'status' => $request->input('status'),
            'q' => $request->input('q'),
        ];

        // Export up to 2000 filtered tasks
        $tasks = $this->taskMonitoringService->getFilteredTasks($user, $filters, 2000)->getCollection();

        $filename = 'tasks_monitor_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($tasks) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Project Code',
                'Project Name',
                'Task Code',
                'Task Title',
                'Assigned To',
                'Start Date',
                'End Date',
                'Progress %',
                'Status',
                'Priority',
                'Schedule Health',
                'Delay Status',
            ]);

            foreach ($tasks as $task) {
                fputcsv($handle, [
                    $task->project?->code,
                    $task->project?->name,
                    $task->code,
                    $task->title,
                    $task->assignees->pluck('name')->join('; ') ?: 'Unassigned',
                    $task->starts_at ? $task->starts_at->format('Y-m-d H:i') : '',
                    $task->ends_at ? $task->ends_at->format('Y-m-d H:i') : '',
                    $task->progress . '%',
                    ucwords(str_replace('_', ' ', $task->status)),
                    ucfirst($task->priority),
                    $task->schedule_health ?? 'On Track',
                    $task->openDelay ? 'Blocked (' . $task->openDelay->reason_code . ')' : 'None',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function hasMonitoringAccess(User $user): bool
    {
        // Standard user only if granted portfolio reports
        return $this->access->hasCompanyPermission($user, 'reports.portfolio');
    }
}

