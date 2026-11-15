<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\Task;
use App\Models\TaskDelay;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InboxService
{
    public function put(User $user, string $key, array $data): void
    {
        $hash = md5($user->id.':'.$key);
        $id = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4).'-'.substr($hash, 16, 4).'-'.substr($hash, 20);
        $user->notifications()->firstOrCreate(['id' => $id], ['type' => 'projectflow.update', 'data' => $data]);
    }

    public function blocker(TaskDelay $delay): void
    {
        $delay->loadMissing('task.project');
        $task = $delay->task;
        $data = [
            'event_key' => 'blocker_reported',
            'blocker_id' => $delay->id,
            'title' => 'New blocker reported',
            'message' => $delay->reason_label,
            'project_name' => $task->project->name,
            'project_id' => $task->project_id,
            'task_title' => $task->title,
            'task_id' => $task->id,
            'action_label' => 'View issue',
            'url' => route('projects.show', [$task->project_id, 'focus_issue' => $delay->id], false).'#risks',
        ];

        $recipientIds = User::where('system_role', 'pmo')->where('account_status', 'active')->pluck('id')
            ->merge([$task->project->pm_user_id, $task->project->owner_user_id, $delay->action_owner_id])
            ->filter()
            ->unique();

        foreach (User::whereIn('id', $recipientIds)->where('account_status', 'active')->get() as $user) {
            $this->put($user, 'blocker:'.$delay->id, $data);
        }
    }

    public function blockerResolved(TaskDelay $delay, User $resolvedBy): void
    {
        $delay->loadMissing('task.project');
        $task = $delay->task;
        $recipientIds = User::where('system_role', 'pmo')->where('account_status', 'active')->pluck('id')
            ->merge([$task->project->pm_user_id, $task->project->owner_user_id, $delay->action_owner_id, $delay->reported_by])
            ->filter()
            ->unique();
        $data = [
            'event_key' => 'blocker_resolved',
            'blocker_id' => $delay->id,
            'title' => 'Blocker resolved',
            'message' => $resolvedBy->name.' resolved the reported blocker for '.$task->title.'.',
            'project_name' => $task->project->name,
            'project_id' => $task->project_id,
            'task_title' => $task->title,
            'task_id' => $task->id,
            'action_label' => 'View issue',
            'url' => route('projects.show', [$task->project_id, 'focus_issue' => $delay->id], false).'#risks',
        ];

        foreach (User::whereIn('id', $recipientIds)->where('account_status', 'active')->get() as $user) {
            $user->notifications()->whereNull('read_at')->where('data->blocker_id', $delay->id)->update(['read_at' => now()]);
            $this->put($user, 'blocker-resolved:'.$delay->id, $data);
        }
    }

    public function assigned(Task $task, User $user): void
    {
        if (! in_array($task->project->status, ['active', 'on_hold'], true) || $task->project->approval_stage === 'planning') {
            return;
        }
        if ($user->notifications()->where('data->task_id', $task->id)->where('data->event_key', 'task_assigned')->exists()) {
            return;
        }

        $this->put($user, 'task:'.$task->id, [
            'event_key' => 'task_assigned',
            'title' => 'Task assigned to you',
            'message' => 'You are assigned to '.$task->title.'.',
            'project_name' => $task->project->name,
            'project_id' => $task->project_id,
            'task_title' => $task->title,
            'task_id' => $task->id,
            'action_label' => 'Open task',
            'url' => route('projects.show', [$task->project_id, 'focus_task' => $task->id], false).'#tasks',
        ]);
    }

    public function resolveTaskAssignment(Task $task, User $user): void
    {
        $user->notifications()
            ->whereNull('read_at')
            ->where('data->task_id', $task->id)
            ->where('data->event_key', 'task_assigned')
            ->update(['read_at' => now()]);
    }

    public function taskCompleted(Task $task): void
    {
        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->where('data->task_id', $task->id)
            ->whereIn('data->event_key', ['task_assigned', 'task_overdue'])
            ->update(['read_at' => now()]);
    }

    public function resolveProjectRequests(Project $project): void
    {
        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->where('data->project_id', $project->id)
            ->where(function ($query) {
                $query->where('data->event_key', 'project_request_received')
                    ->orWhere('data->type', 'project_approval');
            })
            ->update(['read_at' => now()]);
    }

    public function closureRequested(Project $project): void
    {
        $data = [
            'event_key' => 'project_closure_requested',
            'project_id' => $project->id,
            'project_name' => $project->name,
            'title' => 'Project closure requested',
            'message' => ($project->closureRequester?->name ?? 'The project team').' requested formal closure for '.$project->name.'.',
            'action_label' => 'Review closure',
            'url' => route('projects.show', $project->id, false),
        ];

        foreach (User::where('system_role', 'pmo')->where('account_status', 'active')->get() as $user) {
            $this->put($user, 'closure-requested:'.$project->id, $data);
        }
    }

    public function closureApproved(Project $project): void
    {
        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->where('data->project_id', $project->id)
            ->where('data->event_key', 'project_closure_requested')
            ->update(['read_at' => now()]);

        $recipientIds = $project->memberships()->pluck('user_id')
            ->merge([$project->pm_user_id, $project->owner_user_id, $project->closure_requested_by])
            ->filter()
            ->unique();
        $data = [
            'event_key' => 'project_closure_approved',
            'project_id' => $project->id,
            'project_name' => $project->name,
            'title' => 'Project closure approved',
            'message' => 'PMO approved the closure request for '.$project->name.'.',
            'action_label' => 'View project',
            'url' => route('projects.show', $project->id, false),
        ];

        foreach (User::whereIn('id', $recipientIds)->where('account_status', 'active')->get() as $user) {
            $this->put($user, 'closure-approved:'.$project->id, $data);
        }
    }

    public function sync(User $user): void
    {
        if ($user->isPmo()) {
            foreach (TaskDelay::with('task.project')
                ->where('status', 'open')
                ->whereHas('task.project', fn ($query) => $query->whereIn('status', ['active', 'on_hold']))
                ->get() as $delay) {
                $this->blocker($delay);
            }
        } else {
            foreach (ProjectInvitation::with('project')
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->whereHas('project', fn ($query) => $query->where('status', 'pending')->where('pm_user_id', $user->id))
                ->get() as $invite) {
                if (! $user->notifications()->where('data->event_key', 'project_request_received')->where('data->project_id', $invite->project_id)->exists()) {
                    $this->put($user, 'invitation:'.$invite->id, [
                        'event_key' => 'project_request_received',
                        'type' => 'project_approval',
                        'project_id' => $invite->project_id,
                        'title' => 'Project approval requested',
                        'message' => 'Review your Project Manager assignment for '.$invite->project->name.'.',
                        'project_name' => $invite->project->name,
                        'action_label' => 'Review project',
                        'url' => route('projects.show', $invite->project_id, false),
                    ]);
                }
            }

            foreach ($user->assignedTasks()->with('project')->whereDoesntHave('children')
                ->where('tasks.status', '!=', 'completed')
                ->whereHas('project', fn ($query) => $query->whereIn('status', ['active', 'on_hold']))
                ->get() as $task) {
                $this->assigned($task, $user);
            }
        }

        $this->syncOverdue($user);
    }

    private function syncOverdue(User $user): void
    {
        $query = Task::with(['project:id,name,status,pm_user_id,owner_user_id', 'assignees:id'])
            ->whereDoesntHave('children')
            ->where('status', '!=', 'completed')
            ->where('progress', '<', 100)
            ->where('ends_at', '<', now())
            ->whereHas('project', fn ($projectQuery) => $projectQuery->whereIn('status', ['active', 'on_hold']));

        if (! $user->isPmo()) {
            $query->where(function ($visibility) use ($user) {
                $visibility->whereHas('assignees', fn ($assigneeQuery) => $assigneeQuery->where('users.id', $user->id))
                    ->orWhereHas('project', fn ($projectQuery) => $projectQuery->where('pm_user_id', $user->id)->orWhere('owner_user_id', $user->id));
            });
        }

        $query->orderBy('id')->chunkById(100, function ($tasks) use ($user) {
            foreach ($tasks as $task) {
                $isRecipient = $user->isPmo()
                    || $task->assignees->contains('id', $user->id)
                    || (int) $task->project->pm_user_id === (int) $user->id
                    || (int) $task->project->owner_user_id === (int) $user->id;
                if (! $isRecipient) {
                    continue;
                }

                $daysLate = max(1, (int) $task->ends_at->diffInWeekdays(now()));
                $this->put($user, 'task-overdue:'.$task->id, [
                    'event_key' => 'task_overdue',
                    'title' => 'Task overdue',
                    'message' => $task->title.' is '.$daysLate.' working '.($daysLate === 1 ? 'day' : 'days').' overdue and remains incomplete.',
                    'project_name' => $task->project->name,
                    'project_id' => $task->project_id,
                    'task_title' => $task->title,
                    'task_id' => $task->id,
                    'action_label' => 'Open overdue task',
                    'url' => route('projects.show', [$task->project_id, 'focus_task' => $task->id], false).'#tasks',
                ]);
            }
        });
    }
}
