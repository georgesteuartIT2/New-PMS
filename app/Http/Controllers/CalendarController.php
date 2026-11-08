<?php

namespace App\Http\Controllers;

use App\Models\{Meeting, Project, Task, User};
use App\Services\{Access, TaskTree};
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController
{
    /**
     * Unified Personal / Organization Calendar View.
     */
    public function index(Request $request, Access $access)
    {
        $user = $request->user();

        // 1. Month calculation
        $monthStr = trim((string) $request->query('month', now()->format('Y-m')));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthStr)) {
            $monthStr = now()->format('Y-m');
        }

        $currentMonth = Carbon::parse($monthStr . '-01')->startOfMonth();
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        // 2. Filters
        $filterType = $request->query('filter', 'all'); // all, tasks, meetings
        $selectedProjectId = $request->query('project_id');

        // 3. User Accessible Projects
        if ($user->isPmo()) {
            $schedulableProjects = Project::where('status', '!=', 'archived')->orderBy('name')->get();
            $accessibleProjectIds = $schedulableProjects->pluck('id');
        } else {
            $pmProjectIds = $user->pmProjects()->where('status', '!=', 'archived')->pluck('id');
            $memberProjectIds = $user->memberships()->pluck('project_id');
            $accessibleProjectIds = $pmProjectIds->merge($memberProjectIds)->unique();
            $schedulableProjects = Project::whereIn('id', $pmProjectIds)->where('status', '!=', 'archived')->orderBy('name')->get();
        }

        if ($selectedProjectId && in_array((int)$selectedProjectId, $accessibleProjectIds->all(), true)) {
            $filterProjectIds = [(int)$selectedProjectId];
        } else {
            $filterProjectIds = $accessibleProjectIds->all();
        }

        // 4. Fetch Tasks (Strictly internal to ProjectFlow - not synced to Outlook)
        $tasks = collect();
        if ($filterType !== 'meetings') {
            $taskQuery = Task::with(['project', 'assignees'])
                ->whereIn('project_id', $filterProjectIds)
                ->whereDate('ends_at', '>=', $startOfMonth->copy()->subDays(7))
                ->whereDate('starts_at', '<=', $endOfMonth->copy()->addDays(7));

            // Scoped for non-PMO / non-PM team members to tasks assigned to them
            if (!$user->isPmo()) {
                $pmIds = $user->pmProjects()->pluck('id')->all();
                $taskQuery->where(function ($q) use ($user, $pmIds) {
                    $q->whereIn('project_id', $pmIds)
                      ->orWhereHas('assignees', fn ($a) => $a->where('users.id', $user->id));
                });
            }

            $tasks = $taskQuery->orderBy('starts_at')->get();
        }

        // 5. Fetch Meetings (Synced to Outlook)
        $meetings = collect();
        if ($filterType !== 'tasks') {
            $meetingQuery = Meeting::with(['project', 'attendees', 'creator'])
                ->whereIn('project_id', $filterProjectIds)
                ->where('status', '!=', 'cancelled')
                ->whereDate('ends_at', '>=', $startOfMonth->copy()->subDays(7))
                ->whereDate('starts_at', '<=', $endOfMonth->copy()->addDays(7));

            if (!$user->isPmo()) {
                $meetingQuery->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                      ->orWhereHas('attendees', fn ($a) => $a->where('users.id', $user->id));
                });
            }

            $meetings = $meetingQuery->orderBy('starts_at')->get();
        }

        // 6. Build Calendar Grid Cells (Mon - Sun)
        $cells = [];
        $cursor = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $last = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);

        while ($cursor->lte($last)) {
            $day = $cursor->copy();
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();

            // Find tasks active on this day
            $dayTasks = $tasks->filter(function ($t) use ($dayStart, $dayEnd) {
                return $t->starts_at->lte($dayEnd) && $t->ends_at->gte($dayStart);
            })->values();

            // Find meetings active on this day
            $dayMeetings = $meetings->filter(function ($m) use ($dayStart, $dayEnd) {
                return $m->starts_at->lte($dayEnd) && $m->ends_at->gte($dayStart);
            })->values();

            $cells[] = [
                'date' => $day,
                'is_current_month' => $day->month === $currentMonth->month,
                'is_today' => $day->isToday(),
                'tasks' => $dayTasks,
                'meetings' => $dayMeetings,
                'total_events' => $dayTasks->count() + $dayMeetings->count(),
            ];

            $cursor->addDay();
        }

        // Preload project members for quick meeting scheduler modal
        $projectMembersMap = [];
        foreach ($schedulableProjects as $sp) {
            $members = User::whereHas('memberships', fn ($m) => $m->where('project_id', $sp->id))
                ->orWhere('id', $sp->pm_user_id)
                ->orWhere('id', $sp->created_by)
                ->distinct()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'job_title']);
            $projectMembersMap[$sp->id] = $members;
        }

        return view('calendar.index', [
            'month' => $currentMonth,
            'cells' => $cells,
            'filterType' => $filterType,
            'selectedProjectId' => $selectedProjectId,
            'schedulableProjects' => $schedulableProjects,
            'projectMembersMap' => $projectMembersMap,
            'canSchedule' => $user->isPmo() || $schedulableProjects->isNotEmpty(),
            'totalTasksCount' => $tasks->count(),
            'totalMeetingsCount' => $meetings->count(),
        ]);
    }

    /**
     * Dedicated Project Workspace Calendar View.
     */
    public function projectCalendar(Request $request, Project $project, Access $access)
    {
        abort_unless($access->project($request->user(), $project, 'view_tasks'), 403);
        abort_unless(
            $request->user()->isPmo() ||
            in_array($project->status, ['active', 'on_hold', 'completed', 'archived'], true) ||
            ($project->pm_user_id === $request->user()->id && $project->status === 'pending'),
            403
        );

        $user = $request->user();
        $canManageMeetings = $user->isPmo() || $project->pm_user_id === $user->id || $access->canManageTask($user, $project);

        $monthStr = trim((string) $request->query('month', now()->format('Y-m')));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthStr)) {
            $monthStr = now()->format('Y-m');
        }

        $currentMonth = Carbon::parse($monthStr . '-01')->startOfMonth();
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        // Tasks in this project
        $tasks = $project->tasks()
            ->with(['assignees', 'children'])
            ->whereDate('ends_at', '>=', $startOfMonth->copy()->subDays(7))
            ->whereDate('starts_at', '<=', $endOfMonth->copy()->addDays(7))
            ->orderBy('starts_at')
            ->get();

        // Meetings in this project
        $meetings = $project->meetings()
            ->with(['attendeeRecords', 'creator'])
            ->where('status', '!=', 'cancelled')
            ->whereDate('ends_at', '>=', $startOfMonth->copy()->subDays(7))
            ->whereDate('starts_at', '<=', $endOfMonth->copy()->addDays(7))
            ->orderBy('starts_at')
            ->get();

        // Project members list for meeting scheduling
        $projectMembers = User::whereHas('memberships', fn ($m) => $m->where('project_id', $project->id))
            ->orWhere('id', $project->pm_user_id)
            ->orWhere('id', $project->created_by)
            ->distinct()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'job_title']);

        // Build Calendar Grid Cells (Mon - Sun)
        $cells = [];
        $cursor = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $last = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);

        while ($cursor->lte($last)) {
            $day = $cursor->copy();
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();

            $dayTasks = $tasks->filter(fn ($t) => $t->starts_at->lte($dayEnd) && $t->ends_at->gte($dayStart))->values();
            $dayMeetings = $meetings->filter(fn ($m) => $m->starts_at->lte($dayEnd) && $m->ends_at->gte($dayStart))->values();

            $cells[] = [
                'date' => $day,
                'is_current_month' => $day->month === $currentMonth->month,
                'is_today' => $day->isToday(),
                'tasks' => $dayTasks,
                'meetings' => $dayMeetings,
                'total_events' => $dayTasks->count() + $dayMeetings->count(),
            ];

            $cursor->addDay();
        }

        return view('projects.calendar', [
            'project' => $project,
            'month' => $currentMonth,
            'cells' => $cells,
            'tasks' => $tasks,
            'meetings' => $meetings,
            'projectMembers' => $projectMembers,
            'canManageMeetings' => $canManageMeetings,
        ]);
    }
}

