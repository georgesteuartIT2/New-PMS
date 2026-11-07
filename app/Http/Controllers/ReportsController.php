<?php

namespace App\Http\Controllers;

use App\Models\{Project, Task, TaskDelay, User, Subsidiary};
use App\Services\{ProjectHealthService, Access};
use Illuminate\Http\Request;

class ReportsController
{
    public function index(Request $request, ProjectHealthService $healthService, Access $access)
    {
        abort_unless($request->user()->isPmo() || $access->hasCompanyPermission($request->user(), 'reports.portfolio'), 403, 'Permission denied: portfolio reports access required.');
        $reportType = $request->input('type', 'status');
        $subsidiaryId = $request->input('subsidiary_id');
        $status = $request->input('status');
        $pmId = $request->input('pm_user_id');

        $subsidiaries = Subsidiary::where('status', 'active')->orderBy('name')->get();
        $projectManagers = User::where('account_status', 'active')
            ->whereHas('pmProjects')
            ->orderBy('name')
            ->get();

        // 4 KPI Summary Stats
        $kpi = [
            'total_projects' => Project::count(),
            'active_projects' => Project::where('status', 'active')->count(),
            'overdue_tasks' => Task::where('progress', '<', 100)->where('ends_at', '<', now())->whereHas('project', fn($q) => $q->whereIn('status', ['active', 'on_hold']))->count(),
            'reported_delays' => TaskDelay::where('status', 'open')->count(),
        ];

        $reportData = $this->getReportData($reportType, $subsidiaryId, $status, $pmId, $healthService);

        return view('reports.index', [
            'reportType' => $reportType,
            'subsidiaries' => $subsidiaries,
            'projectManagers' => $projectManagers,
            'kpi' => $kpi,
            'reportData' => $reportData,
            'stats' => [
                'projects' => $kpi['total_projects'],
                'active' => $kpi['active_projects'],
                'completed' => Task::where('status', 'completed')->count(),
                'overdue' => $kpi['overdue_tasks'],
            ],
            'projects' => Project::latest()->take(8)->get(),
        ]);
    }

    public function export(Request $request, ProjectHealthService $healthService)
    {
        $reportType = $request->input('type', 'status');
        $subsidiaryId = $request->input('subsidiary_id');
        $status = $request->input('status');
        $pmId = $request->input('pm_user_id');

        $data = $this->getReportData($reportType, $subsidiaryId, $status, $pmId, $healthService);

        $filename = "gs-projectflow-{$reportType}-report-" . date('Y-m-d') . ".csv";
        $file = fopen('php://temp', 'w+');

        $safe = fn($s) => preg_match('/^[=+\-@\t\r]/u', (string)$s) ? "'" . (string)$s : (string)$s;

        switch ($reportType) {
            case 'progress':
                fputcsv($file, ['Project Code', 'Project Name', 'Subsidiary', 'Manager', 'Total Tasks', 'Completed Tasks', 'Progress (%)', 'Status', 'Deadline']);
                foreach ($data['items'] as $item) {
                    fputcsv($file, [
                        $safe($item->code),
                        $safe($item->name),
                        $safe($item->subsidiary?->name ?? '—'),
                        $safe($item->manager?->name ?? 'Not assigned'),
                        $item->tasks_count,
                        $item->completed_tasks_count,
                        $item->progress . '%',
                        $safe(ucwords(str_replace('_', ' ', $item->status))),
                        $item->ends_on->format('Y-m-d')
                    ]);
                }
                break;

            case 'overdue':
                fputcsv($file, ['Task Code', 'Task Title', 'Project Code', 'Project Name', 'Assigned To', 'Due Date', 'Days Overdue', 'Status', 'Priority']);
                foreach ($data['items'] as $item) {
                    fputcsv($file, [
                        $safe($item->code),
                        $safe($item->title),
                        $safe($item->project?->code),
                        $safe($item->project?->name),
                        $safe($item->assignees->pluck('name')->join(', ') ?: 'Unassigned'),
                        $item->ends_at->format('Y-m-d H:i'),
                        (int) ceil(abs(now()->diffInDays($item->ends_at, false))),
                        $safe(ucwords(str_replace('_', ' ', $item->status))),
                        $safe(ucfirst($item->priority))
                    ]);
                }
                break;

            case 'critical':
                fputcsv($file, ['Project Code', 'Project Name', 'Critical Task', 'Assignees', 'Delay Code', 'Action Owner', 'Deadline']);
                foreach ($data['items'] as $item) {
                    fputcsv($file, [
                        $safe($item['project']->code),
                        $safe($item['project']->name),
                        $safe($item['task']?->title ?? 'Dependencies need review'),
                        $safe($item['task']?->assignees->pluck('name')->join(', ') ?: '—'),
                        $safe($item['task']?->openDelay?->reason_code ?? 'None reported'),
                        $safe($item['task']?->openDelay?->actionOwner?->name ?? '—'),
                        $item['project']->ends_on->format('Y-m-d')
                    ]);
                }
                break;

            case 'delays':
                fputcsv($file, ['Project', 'Task Title', 'Delay Reason', 'Involved Party', 'Action Owner', 'Reported By', 'Revised Target', 'Status']);
                foreach ($data['items'] as $item) {
                    fputcsv($file, [
                        $safe($item->task?->project?->name ?? '—'),
                        $safe($item->task?->title ?? '—'),
                        $safe(\App\Http\Controllers\TaskDelayController::REASONS[$item->reason_code] ?? $item->reason_code),
                        $safe($item->responsible_party_name ?: ucwords($item->responsible_party_type)),
                        $safe($item->actionOwner?->name ?? 'Not assigned'),
                        $safe($item->reporter?->name ?? '—'),
                        $item->revised_expected_end_at ? $item->revised_expected_end_at->format('Y-m-d') : 'Not set',
                        $safe(ucfirst($item->status))
                    ]);
                }
                break;

            case 'workload':
                fputcsv($file, ['User Name', 'Email', 'Subsidiary', 'Active Tasks', 'Overdue Tasks', 'Managed Projects']);
                foreach ($data['items'] as $item) {
                    fputcsv($file, [
                        $safe($item->name),
                        $safe($item->email),
                        $safe($item->subsidiary?->code ?? '—'),
                        $item->active_tasks_count,
                        $item->overdue_tasks_count,
                        $item->managed_projects_count
                    ]);
                }
                break;

            case 'status':
            default:
                fputcsv($file, ['Project Code', 'Project Name', 'Subsidiary', 'Manager', 'Status', 'Start Date', 'Deadline', 'Progress (%)', 'Tasks']);
                foreach ($data['items'] as $item) {
                    fputcsv($file, [
                        $safe($item->code),
                        $safe($item->name),
                        $safe($item->subsidiary?->name ?? '—'),
                        $safe($item->manager?->name ?? 'Not assigned'),
                        $safe(ucwords(str_replace('_', ' ', $item->status))),
                        $item->starts_on->format('Y-m-d'),
                        $item->ends_on->format('Y-m-d'),
                        $item->progress . '%',
                        $item->tasks_count
                    ]);
                }
                break;
        }

        rewind($file);
        $csv = stream_get_contents($file);
        fclose($file);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function getReportData(string $reportType, ?string $subsidiaryId, ?string $status, ?string $pmId, ProjectHealthService $healthService): array
    {
        switch ($reportType) {
            case 'progress':
                $query = Project::with('manager', 'subsidiary')
                    ->withCount([
                        'tasks',
                        'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'completed')
                    ]);
                if ($subsidiaryId) $query->where('subsidiary_id', $subsidiaryId);
                if ($status) $query->where('status', $status);
                if ($pmId) $query->where('pm_user_id', $pmId);
                return [
                    'title' => 'Project Progress Report',
                    'subtitle' => 'Detailed task completion and overall progress breakdown by project.',
                    'items' => $query->latest()->get()
                ];

            case 'overdue':
                $query = Task::with('project.manager', 'project.subsidiary', 'assignees')
                    ->where('progress', '<', 100)
                    ->where('ends_at', '<', now())
                    ->whereHas('project', function ($q) use ($subsidiaryId, $status, $pmId) {
                        if ($subsidiaryId) $q->where('subsidiary_id', $subsidiaryId);
                        if ($status) $q->where('status', $status);
                        if ($pmId) $q->where('pm_user_id', $pmId);
                        $q->whereIn('status', ['active', 'on_hold']);
                    });
                return [
                    'title' => 'Overdue Tasks Report',
                    'subtitle' => 'All uncompleted tasks past their scheduled deadline across projects.',
                    'items' => $query->orderBy('ends_at')->get()
                ];

            case 'critical':
                $query = Project::with('manager', 'subsidiary', 'template')
                    ->whereIn('status', ['active', 'on_hold']);
                if ($subsidiaryId) $query->where('subsidiary_id', $subsidiaryId);
                if ($pmId) $query->where('pm_user_id', $pmId);

                $projects = $query->orderBy('ends_on')->get();
                $items = collect();
                foreach ($projects as $p) {
                    $health = $healthService->assess($p);
                    if ($health['critical_tasks']->isNotEmpty() || in_array($health['health'], ['at_risk', 'overdue'])) {
                        $blocking = $health['critical_tasks']->first(fn($t) => $t->openDelay || ($t->ends_at->isPast() && $t->progress < 100));
                        $items->push([
                            'project' => $p,
                            'health' => $health,
                            'task' => $blocking,
                        ]);
                    }
                }
                return [
                    'title' => 'Critical Tasks & Path Report',
                    'subtitle' => 'Projects with critical path blockers, zero float dependencies, or schedule risks.',
                    'items' => $items
                ];

            case 'delays':
                $query = TaskDelay::with('task.project.manager', 'task.project.subsidiary', 'reporter', 'actionOwner')
                    ->latest();
                if ($subsidiaryId) {
                    $query->whereHas('task.project', fn($q) => $q->where('subsidiary_id', $subsidiaryId));
                }
                if ($pmId) {
                    $query->whereHas('task.project', fn($q) => $q->where('pm_user_id', $pmId));
                }
                return [
                    'title' => 'Delay Reasons & Action Ownership Report',
                    'subtitle' => 'Reported issues, responsible involved parties, and designated follow-up action owners.',
                    'items' => $query->get()
                ];

            case 'completed':
                $query = Project::with('manager', 'subsidiary')->withCount('tasks')
                    ->where('status', 'completed');
                if ($subsidiaryId) $query->where('subsidiary_id', $subsidiaryId);
                if ($pmId) $query->where('pm_user_id', $pmId);
                return [
                    'title' => 'Completed Projects Report',
                    'subtitle' => 'Archival and performance summary of successfully finished projects.',
                    'items' => $query->latest('ends_on')->get()
                ];

            case 'workload':
                $query = User::where('account_status', 'active')
                    ->with('subsidiary')
                    ->withCount([
                        'assignedTasks as active_tasks_count' => fn($q) => $q->where('progress', '<', 100)->whereHas('project', fn($p) => $p->whereIn('status', ['active', 'on_hold'])),
                        'assignedTasks as overdue_tasks_count' => fn($q) => $q->where('progress', '<', 100)->where('ends_at', '<', now())->whereHas('project', fn($p) => $p->whereIn('status', ['active', 'on_hold'])),
                        'pmProjects as managed_projects_count' => fn($q) => $q->whereIn('status', ['active', 'on_hold']),
                    ]);
                if ($subsidiaryId) $query->where('subsidiary_id', $subsidiaryId);
                $items = $query->orderBy('name')->get()
                    ->filter(fn($u) => $u->active_tasks_count > 0 || $u->managed_projects_count > 0)
                    ->sortByDesc('active_tasks_count')
                    ->values();
                return [
                    'title' => 'User Assignment & Workload Report',
                    'subtitle' => 'Active task load and project leadership per employee across subsidiaries.',
                    'items' => $items
                ];

            case 'status':
            default:
                $query = Project::with('manager', 'subsidiary')->withCount('tasks');
                if ($subsidiaryId) $query->where('subsidiary_id', $subsidiaryId);
                if ($status) $query->where('status', $status);
                if ($pmId) $query->where('pm_user_id', $pmId);
                return [
                    'title' => 'Project Status Report',
                    'subtitle' => 'High-level status, timeline, and ownership summary across all company projects.',
                    'items' => $query->latest()->get()
                ];
        }
    }
}
