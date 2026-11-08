<?php
namespace App\Http\Controllers;

use App\Models\{ProjectTemplate, TemplateTask, Project, ProjectRole};
use App\Services\{TemplateSpreadsheetService, TemplateScheduleService, Access, Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TemplateController {
    /**
     * Standard corporate categories.
     */
    public const DEFAULT_CATEGORIES = [
        'IT & Software',
        'Infrastructure',
        'Construction',
        'Finance',
        'Procurement',
        'Operations',
        'Marketing',
        'HR',
        'General'
    ];

    /**
     * Check if current user is authorized to manage templates (PMO or templates.manage permission).
     */
    protected function authorizeManage(Request $request): void {
        $user = $request->user();
        $canManage = $user && ($user->isPmo() || app(Access::class)->hasCompanyPermission($user, 'templates.manage'));
        abort_unless($canManage, 403, 'Permission denied: cannot manage project templates.');
    }

    /**
     * Templates Library (Screen 01)
     */
    public function index(Request $request) {
        $search = trim($request->query('search', ''));
        $category = trim($request->query('category', ''));
        $status = trim($request->query('status', 'active')); // active, archived, all

        $query = ProjectTemplate::withCount('tasks')
            ->with(['creator', 'tasks.suggestedRole']);

        if ($search !== '') {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category !== '' && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($status === 'active') {
            $query->where('status', 'active');
        } elseif ($status === 'archived') {
            $query->where('status', 'archived');
        } // if 'all', don't filter by status

        $templates = $query->latest()->paginate(15)->withQueryString();

        // Distinct categories in database combined with standard categories
        $dbCategories = ProjectTemplate::whereNotNull('category')->distinct()->pluck('category')->toArray();
        $categories = array_values(array_unique(array_merge(self::DEFAULT_CATEGORIES, $dbCategories)));
        sort($categories);

        $counts = [
            'all' => ProjectTemplate::count(),
            'active' => ProjectTemplate::where('status', 'active')->count(),
            'archived' => ProjectTemplate::where('status', 'archived')->count(),
        ];

        $user = $request->user();
        $canManage = $user && ($user->isPmo() || app(Access::class)->hasCompanyPermission($user, 'templates.manage'));
        $canCreateProject = $user && ($user->isPmo() || app(Access::class)->hasCompanyPermission($user, 'projects.create'));
        abort_unless($canManage || $canCreateProject, 403, 'Permission denied.');
        $roles = ProjectRole::orderBy('name')->get();

        $templatesCache = $templates->getCollection()->mapWithKeys(function($t) {
            return [
                $t->id => [
                    'template' => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'description' => $t->description,
                        'category' => $t->category,
                        'estimated_duration' => $t->estimated_duration ?: 14,
                        'duration_unit' => $t->duration_unit ?: 'working_days',
                        'reference_start_date' => $t->reference_start_date?->format('Y-m-d') ?: date('Y-m-d'),
                        'working_hours' => $t->working_hours ?: '09:00 - 17:00',
                        'planning_periods' => $t->planning_periods ?? [],
                        'status' => $t->status,
                    ],
                    'tasks' => $t->tasks->map(function($task) {
                        return [
                            'id' => $task->id,
                            'code' => $task->code,
                            'parent_code' => $task->parent_code,
                            'title' => $task->title,
                            'description' => $task->description,
                            'start_date' => $task->start_date?->format('Y-m-d'),
                            'start_time' => $task->start_time ?: '09:00',
                            'end_date' => $task->end_date?->format('Y-m-d'),
                            'end_time' => $task->end_time ?: '17:00',
                            'duration_days' => $task->duration_days ?: 1,
                            'suggested_role_id' => $task->suggested_role_id,
                            'suggested_role_name' => $task->suggestedRole?->name,
                            'dependency_code' => $task->dependency_code,
                            'priority' => $task->priority ?: 'medium',
                        ];
                    })->values()->toArray(),
                ]
            ];
        })->toArray();

        $selectForProject = $request->boolean('select_for_project') || $request->has('select');

        return view('templates.index', compact(
            'templates', 
            'categories', 
            'search', 
            'category', 
            'status', 
            'counts', 
            'canManage', 
            'canCreateProject', 
            'roles',
            'templatesCache',
            'selectForProject'
        ));
    }

    /**
     * Screen 02: Create Template form
     */
    public function create(Request $request) {
        $this->authorizeManage($request);

        $roles = ProjectRole::orderBy('name')->get();
        $categories = self::DEFAULT_CATEGORIES;
        return view('templates.create', compact('roles', 'categories'));
    }

    /**
     * Store newly created template
     */
    public function store(Request $request) {
        $this->authorizeManage($request);

        $data = $request->validate([
            'name' => 'required|string|max:160',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|string|max:60',
            'estimated_duration' => 'required|integer|min:1|max:3650',
            'duration_unit' => 'required|in:working_days,days,weeks,months',
            'reference_start_date' => 'nullable|date',
            'working_hours' => 'nullable|string|max:40',
            'planning_periods' => 'nullable|array',
            'tasks' => 'nullable|array',
            'tasks.*.title' => 'required|string|max:200',
            'tasks.*.description' => 'nullable|string|max:2000',
            'tasks.*.code' => 'nullable|string|max:60',
            'tasks.*.parent_code' => 'nullable|string|max:60',
            'tasks.*.duration_days' => 'nullable|integer|min:1|max:365',
            'tasks.*.start_date' => 'nullable|date',
            'tasks.*.start_time' => 'nullable|string|max:10',
            'tasks.*.end_date' => 'nullable|date',
            'tasks.*.end_time' => 'nullable|string|max:10',
            'tasks.*.suggested_role_id' => 'nullable|exists:project_roles,id',
            'tasks.*.dependency_code' => 'nullable|string|max:60',
            'tasks.*.priority' => 'nullable|in:low,medium,high',
        ]);

        $tasks = $data['tasks'] ?? [];
        $this->validateTaskGraph($tasks);

        $template = DB::transaction(function() use ($request, $data, $tasks) {
            $tpl = ProjectTemplate::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'],
                'estimated_duration' => $data['estimated_duration'],
                'duration_unit' => $data['duration_unit'],
                'reference_start_date' => $data['reference_start_date'] ?? null,
                'working_hours' => ($data['working_hours'] ?? null) ?: '09:00 - 17:00',
                'planning_periods' => $data['planning_periods'] ?? null,
                'status' => 'active',
                'created_by' => $request->user()->id,
            ]);

            $this->syncTasks($tpl, $tasks);
            Audit::record('template.created', $tpl, ['name' => $tpl->name, 'task_count' => count($tasks)]);

            return $tpl;
        });

        $returnUrl=$request->boolean('select_for_project')
            ? route('projects.create',array_merge($request->only('project_flow','draft'),['template_id'=>$template->id,'mode'=>'template']))
            : route('templates.index');
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Template '{$template->name}' created successfully.",
                'template_id' => $template->id,
                'redirect' => $returnUrl
            ]);
        }

        return redirect($returnUrl)->with('success', "Template '{$template->name}' created successfully.");
    }

    /**
     * Show / Open template (returns JSON if requested via AJAX or view)
     */
    public function show(Request $request, ProjectTemplate $template) {
        if ($request->expectsJson() || $request->ajax()) {
            return $this->tasksJson($template);
        }

        $template->load(['tasks.suggestedRole', 'creator']);
        $roles = ProjectRole::orderBy('name')->get();
        $categories = self::DEFAULT_CATEGORIES;
        $schedule = $template->schedule();
        $user = $request->user();
        $canManage = $user && ($user->isPmo() || app(Access::class)->hasCompanyPermission($user, 'templates.manage'));
        $canCreateProject = $user && ($user->isPmo() || app(Access::class)->hasCompanyPermission($user, 'projects.create'));

        return view('templates.edit', compact('template', 'roles', 'categories', 'schedule', 'canManage', 'canCreateProject'));
    }

    /**
     * Fast dedicated JSON endpoint returning template details and tasks for popup preview.
     */
    public function tasksJson(ProjectTemplate $template, TemplateScheduleService $scheduleService) {
        $user = request()->user();
        $canManage = $user && ($user->isPmo() || app(Access::class)->hasCompanyPermission($user, 'templates.manage'));
        $canCreateProject = $user && ($user->isPmo() || app(Access::class)->hasCompanyPermission($user, 'projects.create'));
        abort_unless($canManage || $canCreateProject, 403, 'Permission denied.');

        $template->load(['tasks.suggestedRole', 'creator']);
        $roles = ProjectRole::orderBy('name')->get(['id', 'name', 'slug']);
        $schedule = $template->schedule();

        $duration = max(1, (int) ($template->estimated_duration ?: 14));
        $unit = $template->duration_unit ?: 'working_days';
        $refDate = $template->reference_start_date ? $template->reference_start_date->format('Y-m-d') : date('Y-m-d');
        $periods = $scheduleService->generatePeriods($refDate, $duration, $unit, $template->planning_periods ?? []);

        // Period lookup map for tasks
        $periodMap = [];
        foreach ($periods as $p) {
            $periodMap[$p['starts_on']] = $p;
        }

        return response()->json([
            'success' => true,
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'category' => $template->category,
                'estimated_duration' => $template->estimated_duration,
                'duration_unit' => $template->duration_unit,
                'formatted_duration' => $template->formattedDuration(),
                'reference_start_date' => $template->reference_start_date?->format('Y-m-d'),
                'reference_start_date_formatted' => $template->reference_start_date?->format('d M Y'),
                'working_hours' => $template->working_hours ?: '09:00 - 17:00',
                'status' => $template->status,
                'planning_periods' => $template->planning_periods,
                'created_by' => $template->creator?->name,
                'updated_at' => $template->updated_at?->format('d M Y'),
            ],
            'planning_periods' => $periods,
            'tasks' => $template->tasks->map(function(TemplateTask $task) use ($periodMap) {
                $startDate = $task->start_date?->format('Y-m-d');
                $endDate = $task->end_date?->format('Y-m-d');
                $startP = $startDate && isset($periodMap[$startDate]) ? $periodMap[$startDate] : null;
                $endP = $endDate && isset($periodMap[$endDate]) ? $periodMap[$endDate] : null;

                return [
                    'id' => $task->id,
                    'code' => $task->code,
                    'parent_code' => $task->parent_code,
                    'title' => $task->title,
                    'description' => $task->description,
                    'duration_days' => $task->duration_days ?: 1,
                    'start_period' => $startP ? $startP['id'] : null,
                    'start_period_name' => $startP ? ($startP['display_name'] ?? $startP['name']) : null,
                    'end_period' => $endP ? $endP['id'] : ($startP ? $startP['id'] : null),
                    'end_period_name' => $endP ? ($endP['display_name'] ?? $endP['name']) : null,
                    'start_date' => $startDate,
                    'start_date_formatted' => $task->start_date?->format('d M Y'),
                    'start_time' => $task->start_time ?: '09:00',
                    'end_date' => $endDate,
                    'end_date_formatted' => $task->end_date?->format('d M Y'),
                    'end_time' => $task->end_time ?: '17:00',
                    'suggested_role_id' => $task->suggested_role_id,
                    'suggested_role_name' => $task->suggestedRole?->name,
                    'dependency_code' => $task->dependency_code,
                    'priority' => $task->priority ?: 'medium',
                    'is_subtask' => !empty($task->parent_code),
                    'sort_order' => $task->sort_order,
                ];
            }),
            'schedule' => $schedule,
            'roles' => $roles,
        ]);
    }

    /**
     * Edit template
     */
    public function edit(Request $request, ProjectTemplate $template) {
        $this->authorizeManage($request);

        $template->load(['tasks.suggestedRole', 'creator']);
        $roles = ProjectRole::orderBy('name')->get();
        $categories = self::DEFAULT_CATEGORIES;
        $schedule = $template->schedule();
        $user = $request->user();
        $canManage = true;
        $canCreateProject = $user && ($user->isPmo() || app(Access::class)->hasCompanyPermission($user, 'projects.create'));

        return view('templates.edit', compact('template', 'roles', 'categories', 'schedule', 'canManage', 'canCreateProject'));
    }

    /**
     * Update existing template
     */
    public function update(Request $request, ProjectTemplate $template) {
        $this->authorizeManage($request);

        $data = $request->validate([
            'name' => 'required|string|max:160',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|string|max:60',
            'estimated_duration' => 'required|integer|min:1|max:3650',
            'duration_unit' => 'required|in:working_days,days,weeks,months',
            'reference_start_date' => 'nullable|date',
            'working_hours' => 'nullable|string|max:40',
            'planning_periods' => 'nullable|array',
            'tasks' => 'nullable|array',
            'tasks.*.title' => 'required|string|max:200',
            'tasks.*.description' => 'nullable|string|max:2000',
            'tasks.*.code' => 'nullable|string|max:60',
            'tasks.*.parent_code' => 'nullable|string|max:60',
            'tasks.*.duration_days' => 'nullable|integer|min:1|max:365',
            'tasks.*.start_date' => 'nullable|date',
            'tasks.*.start_time' => 'nullable|string|max:10',
            'tasks.*.end_date' => 'nullable|date',
            'tasks.*.end_time' => 'nullable|string|max:10',
            'tasks.*.suggested_role_id' => 'nullable|exists:project_roles,id',
            'tasks.*.dependency_code' => 'nullable|string|max:60',
            'tasks.*.priority' => 'nullable|in:low,medium,high',
        ]);

        $tasks = $data['tasks'] ?? [];
        $this->validateTaskGraph($tasks);

        DB::transaction(function() use ($template, $data, $tasks) {
            $template->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'],
                'estimated_duration' => $data['estimated_duration'],
                'duration_unit' => $data['duration_unit'],
                'reference_start_date' => $data['reference_start_date'] ?? null,
                'working_hours' => ($data['working_hours'] ?? null) ?: '09:00 - 17:00',
                'planning_periods' => $data['planning_periods'] ?? $template->planning_periods,
            ]);

            $this->syncTasks($template, $tasks);
            Audit::record('template.updated', $template, ['name' => $template->name, 'task_count' => count($tasks)]);
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Template '{$template->name}' updated successfully.",
                'template_id' => $template->id,
                'redirect' => route('templates.index')
            ]);
        }

        return redirect()->route('templates.index')->with('success', "Template '{$template->name}' updated successfully.");
    }

    /**
     * Duplicate template
     */
    public function duplicate(Request $request, ProjectTemplate $template) {
        $this->authorizeManage($request);

        $clone = $template->duplicate($request->user());
        Audit::record('template.duplicated', $clone, ['original_id' => $template->id, 'name' => $clone->name]);

        return redirect()->route('templates.index')->with('success', "Template duplicated as '{$clone->name}'.");
    }

    /**
     * Archive or Unarchive template
     */
    public function archive(Request $request, ProjectTemplate $template) {
        $this->authorizeManage($request);

        $newStatus = $template->status === 'active' ? 'archived' : 'active';
        $template->update(['status' => $newStatus]);
        $action = $newStatus === 'archived' ? 'archived' : 'restored to active';
        Audit::record('template.' . ($newStatus === 'archived' ? 'archived' : 'restored'), $template);

        return back()->with('success', "Template '{$template->name}' has been {$action}.");
    }

    /**
     * Export template tasks to Excel (.xlsx)
     */
    public function export(ProjectTemplate $template, TemplateSpreadsheetService $service) {
        $filename = Str::slug($template->name) . '-tasks.xlsx';
        $book = $service->exportWorkbook($template);
        return response()->streamDownload(function() use ($book) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Download blank standard Excel import template tailored to template details and periods
     */
    public function downloadBlank(Request $request, TemplateSpreadsheetService $service) {
        $details = $request->only([
            'name',
            'estimated_duration',
            'duration_unit',
            'reference_start_date',
            'working_hours',
            'category',
            'description'
        ]);

        $name = !empty($details['name']) ? $details['name'] : 'Project-Template';
        $filename = 'GS-ProjectFlow-' . Str::slug($name) . '-Task-Plan.xlsx';

        $rawTasks = $request->input('tasks');
        $tasks = [];
        if (!empty($rawTasks)) {
            $tasks = is_string($rawTasks) ? json_decode($rawTasks, true) : $rawTasks;
            if (!is_array($tasks)) {
                $tasks = [];
            }
        }

        $book = $service->blankWorkbook($details, $tasks);
        return response()->streamDownload(function() use ($book) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Parse Excel file or pasted raw text and return JSON preview of tasks
     */
    public function importPreview(Request $request, TemplateSpreadsheetService $service) {
        try {
            $context = $request->only([
                'estimated_duration',
                'duration_unit',
                'reference_start_date',
                'working_hours',
                'template_id'
            ]);

            if ($request->filled('existing_tasks')) {
                $ext = $request->input('existing_tasks');
                $context['existing_tasks'] = is_array($ext) ? $ext : json_decode($ext, true);
            }

            if ($request->hasFile('file')) {
                $request->validate(['file' => 'required|file|max:5120|mimes:xlsx,csv,txt']);
                $result = $service->parse($request->file('file'), $context);
            } elseif ($request->filled('raw_text')) {
                $result = $service->parseRawText($request->input('raw_text'), $context);
            } else {
                return response()->json(['success' => false, 'message' => 'Please provide an Excel file or pasted table content.'], 422);
            }

            return response()->json([
                'success' => count($result['errors']) === 0,
                'tasks' => $result['tasks'],
                'errors' => $result['errors'],
                'count' => $result['count'],
                'tasks_found' => $result['tasks_found'] ?? $result['count'],
                'main_tasks_count' => $result['main_tasks_count'] ?? 0,
                'subtasks_count' => $result['subtasks_count'] ?? 0,
                'errors_count' => count($result['errors']),
                'comparison' => $result['comparison'] ?? null,
                'planning_periods' => $result['planning_periods'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Delete unused template
     */
    public function destroy(Request $request, ProjectTemplate $template) {
        $this->authorizeManage($request);

        $usedInProjects = Project::where('project_template_id', $template->id)->exists();
        if ($usedInProjects) {
            return back()->withErrors(['error' => "Template '{$template->name}' is associated with existing projects and cannot be deleted. You can Archive it instead."]);
        }

        $template->tasks()->delete();
        $template->delete();
        Audit::record('template.deleted', $template, ['name' => $template->name]);

        return redirect()->route('templates.index')->with('success', "Template '{$template->name}' deleted successfully.");
    }

    /**
     * Cycle detection and self-dependency validation for template tasks.
     */
    protected function validateTaskGraph(array $tasks): void {
        $depGraph = [];

        foreach ($tasks as $idx => $t) {
            $code = trim($t['code'] ?? '') ?: (string)($idx + 1);
            $dep = trim($t['dependency_code'] ?? '');

            if ($dep !== '') {
                $deps = array_values(array_filter(array_map('trim', explode(',', $dep))));
                foreach ($deps as $d) {
                    if ($d === $code) {
                        throw ValidationException::withMessages([
                            "tasks.{$idx}.dependency_code" => "Task '{$code}' cannot depend on itself."
                        ]);
                    }
                }
                $depGraph[$code] = $deps;
            } else {
                $depGraph[$code] = [];
            }
        }

        $visited = [];
        $recStack = [];
        $cyclePath = [];

        $dfs = function ($node) use (&$dfs, &$visited, &$recStack, &$depGraph, &$cyclePath) {
            $visited[$node] = true;
            $recStack[$node] = true;

            foreach ($depGraph[$node] ?? [] as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    if ($dfs($neighbor)) {
                        $cyclePath[] = $neighbor;
                        return true;
                    }
                } elseif (!empty($recStack[$neighbor])) {
                    $cyclePath = [$neighbor, $node];
                    return true;
                }
            }

            $recStack[$node] = false;
            return false;
        };

        foreach (array_keys($depGraph) as $code) {
            if (empty($visited[$code])) {
                if ($dfs($code)) {
                    $cyclePath = array_reverse($cyclePath);
                    throw ValidationException::withMessages([
                        'tasks' => "Circular dependency detected between tasks: " . implode(' -> ', $cyclePath)
                    ]);
                }
            }
        }
    }

    /**
     * Helper to synchronize template tasks in an ordered hierarchy.
     */
    protected function syncTasks(ProjectTemplate $template, array $tasks): void {
        $template->tasks()->delete();

        $autoIndex = 1;
        $codeCounts = [];

        foreach ($tasks as $idx => $t) {
            $title = trim($t['title'] ?? '');
            if ($title === '') continue;

            $code = trim($t['code'] ?? '');
            $parentCode = trim($t['parent_code'] ?? '') ?: null;

            if ($code === '') {
                $code = $parentCode ? "{$parentCode}.{$autoIndex}" : (string)$autoIndex;
                $autoIndex++;
            }

            // Ensure unique task code within this template
            if (isset($codeCounts[$code])) {
                $code = "{$code}-" . (++$codeCounts[$code]);
            } else {
                $codeCounts[$code] = 1;
            }

            $template->tasks()->create([
                'code' => $code,
                'parent_code' => $parentCode,
                'title' => $title,
                'description' => !empty($t['description']) ? trim($t['description']) : null,
                'duration_days' => max(1, (int)($t['duration_days'] ?? 1)),
                'start_date' => !empty($t['start_date']) ? $t['start_date'] : null,
                'start_time' => !empty($t['start_time']) ? $t['start_time'] : '09:00',
                'end_date' => !empty($t['end_date']) ? $t['end_date'] : null,
                'end_time' => !empty($t['end_time']) ? $t['end_time'] : '17:00',
                'suggested_role_id' => !empty($t['suggested_role_id']) ? $t['suggested_role_id'] : null,
                'dependency_code' => !empty($t['dependency_code']) ? trim($t['dependency_code']) : null,
                'priority' => !empty($t['priority']) ? $t['priority'] : 'medium',
                'sort_order' => $idx,
            ]);
        }
    }
}
