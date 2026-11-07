<?php

namespace App\Http\Controllers;

use App\Models\{Task,TaskDelay,User};
use App\Services\{Access,Audit};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskDelayController
{
    public const REASONS = [
        'waiting_approval' => 'Waiting for approval',
        'supplier' => 'Supplier or vendor delay',
        'resources' => 'People or resources unavailable',
        'dependency' => 'Waiting for another task',
        'scope_change' => 'Scope or requirement changed',
        'technical' => 'Technical or equipment problem',
        'external' => 'Other external issue',
        'other' => 'Other reason',
    ];

    public function store(Request $request, Task $task, Access $access)
    {
        abort_unless($access->task($request->user(), $task, 'update'), 403);
        abort_unless(in_array($task->project->status, ['active', 'on_hold'], true), 403);
        $data = $request->validate([
            'reason_code' => ['required', Rule::in(array_keys(self::REASONS))],
            'details' => 'nullable|string|max:2000',
            'responsible_party_type' => ['required', Rule::in(['unknown', 'internal', 'supplier', 'department', 'external'])],
            'responsible_party_name' => 'nullable|string|max:160',
            'action_owner_id' => 'nullable|integer|exists:users,id',
            'revised_expected_end_at' => 'nullable|date',
        ]);
        if (!empty($data['action_owner_id'])) {
            $id = (int) $data['action_owner_id'];
            $project = $task->project;
            if ($project->pm_user_id !== $id && !$project->memberships()->where('user_id', $id)->exists()) {
                throw ValidationException::withMessages(['action_owner_id' => 'Select a member of this project as the follow-up owner.']);
            }
        }
        if (!empty($data['revised_expected_end_at']) && strtotime($data['revised_expected_end_at']) < time() - 86400) {
            throw ValidationException::withMessages(['revised_expected_end_at' => 'The revised target must not be in the distant past.']);
        }
        $task->delayReports()->where('status', 'open')->update(['status' => 'resolved', 'resolved_at' => now()]);
        $report = $task->delayReports()->create($data + ['reported_by' => $request->user()->id, 'status' => 'open']);
        Audit::record('task.delay_reported', $task, ['delay_id' => $report->id, 'reason_code' => $report->reason_code]);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Delay reported successfully.',
                'delay' => $report->load(['actionOwner:id,name', 'reporter:id,name']),
            ]);
        }

        return back()->with('success', 'Delay reported. PMO can now see the reason and follow-up owner.');
    }

    public function resolve(Request $request, TaskDelay $delay, Access $access)
    {
        $task = $delay->task;
        abort_unless($access->canManageTask($request->user(), $task->project), 403);
        abort_unless(in_array($task->project->status, ['active', 'on_hold'], true), 403);
        if ($delay->status !== 'resolved') {
            $delay->update(['status' => 'resolved', 'resolved_at' => now()]);
            Audit::record('task.delay_resolved', $task, ['delay_id' => $delay->id]);
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Delay marked resolved.',
            ]);
        }

        return back()->with('success', 'Delay marked resolved.');
    }
}
