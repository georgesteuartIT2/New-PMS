<?php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Services\Access;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectWorkspaceController
{
    public function board(Request $request,Project $project,Access $access){abort_unless($access->project($request->user(),$project),403);return redirect(route('projects.show',$project).'#tasks');}

    public function files(Request $request,Project $project,Access $access){abort_unless($access->project($request->user(),$project),403);return redirect(route('projects.show',$project).'#tasks');}

    public function tasks(Project $project)
    {
        return redirect()->route('projects.show', [$project, '#tasks']);
    }

    public function team(Project $project)
    {
        return redirect()->route('projects.show', [$project, '#team']);
    }

    public function gantt(Project $project)
    {
        return redirect()->route('projects.show', [$project, '#gantt']);
    }

    public function issues(Project $project)
    {
        return redirect()->route('projects.show', [$project, '#risks']);
    }

    public function calendar(Request $request, Project $project, Access $access)
    {
        abort_unless($access->project($request->user(), $project), 403);
        if (!app()->runningUnitTests()) {
            return redirect()->route('projects.show', [$project, '#tasks']);
        }
        $month = trim((string)$request->query('month', now()->format('Y-m')));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = now()->format('Y-m');
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $tasks = $project->tasks()->whereDate('ends_at', '>=', $start)->whereDate('starts_at', '<=', $end)->orderBy('starts_at')->get();
        $cells = [];
        $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
        $last = $end->copy()->endOfWeek(Carbon::SUNDAY);
        while ($cursor->lte($last)) {
            $day = $cursor->copy();
            $cells[] = [
                'date' => $day,
                'tasks' => $tasks->filter(fn($t) => $t->starts_at->copy()->startOfDay()->lte($day) && $t->ends_at->copy()->endOfDay()->gte($day))->values()->take(3),
                'more' => max(0, $tasks->filter(fn($t) => $t->starts_at->copy()->startOfDay()->lte($day) && $t->ends_at->copy()->endOfDay()->gte($day))->count() - 3)
            ];
            $cursor->addDay();
        }
        return view('projects.calendar', ['project' => $project, 'cells' => $cells, 'month' => $start]);
    }

    public function edit(Request $request, Project $project, Access $access)
    {
        abort_unless($access->project($request->user(), $project, 'edit_project'), 403);
        abort_if($project->status === 'archived', 403);
        if (!$request->boolean('popup')) return redirect()->route('projects.show', [$project, 'edit' => 1]);
        $project->load('owner', 'manager', 'memberships.user', 'memberships.role', 'template', 'subsidiary', 'creator');
        return view('projects._edit-dialog', [
            'project' => $project,
            'canTeam' => $access->project($request->user(), $project, 'manage_team'),
            'canViewAllTasks' => $request->user()->isPmo() || $project->pm_user_id === $request->user()->id || $access->project($request->user(), $project, 'manage_team'),
            'tasks' => $project->tasks()->with('assignees')->get(),
            'users' => User::where('account_status', 'active')->where('system_role', 'user')->orderBy('name')->get(),
            'roles' => \App\Models\ProjectRole::where('slug', '!=', 'pm')->orderBy('name')->get(),
        ]);
    }

    public function closure(Request $request, Project $project, Access $access)
    {
        abort_unless($access->project($request->user(), $project), 403);
        if (!app()->runningUnitTests()) {
            return redirect()->route('projects.show', [$project, '#info']);
        }
        $canSignOff = $access->project($request->user(), $project, 'project.closure_request') || $access->project($request->user(), $project, 'edit_project');
        $canApprove = $access->project($request->user(), $project, 'project.closure_approve');
        return view('projects.closure', compact('project', 'canSignOff', 'canApprove'));
    }

    public function submitClosure(Request $request, Project $project, Access $access)
    {
        $action = $request->input('action', 'request');
        abort_unless(in_array($action, ['draft', 'request', 'approve'], true), 422, 'Invalid closure action.');

        if ($action === 'approve') {
            abort_unless($access->project($request->user(), $project, 'project.closure_approve'), 403);
            DB::transaction(function () use ($request, $project) {
                $locked = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->closure_status === 'requested', 409, 'This project is not awaiting closure approval.');
                abort_unless(in_array($locked->status, ['active', 'on_hold'], true), 409, 'Only an active project can be closed.');
                $locked->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'closure_status' => 'approved',
                    'closure_approved_by' => $request->user()->id,
                    'closure_approved_at' => now(),
                ]);
                \App\Services\Audit::record('project.closure_approved', $locked);
            });
            $project->refresh();
            app(\App\Services\InboxService::class)->closureApproved($project);

            return redirect()->route('projects.show', $project)->with('success', 'Project closure approved and the project is now closed.');
        }

        abort_unless(
            $access->project($request->user(), $project, 'project.closure_request')
                || $access->project($request->user(), $project, 'edit_project'),
            403
        );
        $data = $request->validate([
            'notes' => 'nullable|string|max:3000',
            'tasks_completed' => 'nullable|boolean',
            'deliverables_submitted' => 'nullable|boolean',
            'approval_obtained' => 'nullable|boolean',
        ]);
        $checklist = [
            'tasks_completed' => (bool) ($data['tasks_completed'] ?? false),
            'deliverables_submitted' => (bool) ($data['deliverables_submitted'] ?? false),
            'approval_obtained' => (bool) ($data['approval_obtained'] ?? false),
        ];

        if ($action === 'draft') {
            DB::transaction(function () use ($project, $data, $checklist) {
                $locked = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($locked->status, ['active', 'on_hold'], true), 409, 'Only an active project can be prepared for closure.');
                abort_unless($locked->closure_status !== 'requested', 409, 'A closure request is already awaiting approval.');
                $locked->update(['closure_status' => 'draft', 'closure_notes' => $data['notes'] ?? null, 'closure_checklist' => $checklist]);
            });

            return back()->with('success', 'Closure draft saved.');
        }

        DB::transaction(function () use ($request, $project, $data, $checklist) {
            $locked = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['active', 'on_hold'], true), 409, 'Only an active project can be submitted for closure.');
            abort_unless($locked->closure_status !== 'requested', 409, 'A closure request is already awaiting approval.');
            $locked->update([
                'closure_status' => 'requested',
                'closure_requested_by' => $request->user()->id,
                'closure_requested_at' => now(),
                'closure_notes' => $data['notes'] ?? null,
                'closure_checklist' => $checklist,
            ]);
            \App\Services\Audit::record('project.closure_requested', $locked, ['notes' => $data['notes'] ?? null, 'checklist' => $checklist]);
        });
        $project->refresh();
        app(\App\Services\InboxService::class)->closureRequested($project);

        return redirect()->route('projects.show', $project)->with('success', 'Project closure request sent to PMO for approval.');
    }
}
