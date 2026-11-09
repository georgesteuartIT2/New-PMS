<?php

namespace App\Services;

use App\Models\{ProjectTemplate, ProjectRole, TemplateTask};
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class TemplateSpreadsheetService
{
    public const COLUMNS = [
        'task_code' => '#',
        'parent_task_code' => 'Parent # (Optional)',
        'task_name' => 'Task Name',
        'start_date' => 'Start Date',
        'start_time' => 'Start Time',
        'end_date' => 'End Date',
        'end_time' => 'End Time',
        'duration_days' => 'Duration (Days)',
        'dependency_code' => 'Predecessor #',
        'suggested_role' => 'Suggested Role',
        'description' => 'Description'
    ];

    public function __construct(
        protected TemplateScheduleService $scheduleService
    ) {}

    /**
     * Build standard 3-sheet Excel workbook based on template details, planning periods, and tasks.
     *
     * @param array $templateDetails
     * @param array $periods Generated planning periods
     * @param array $tasks Real tasks to export (empty for new blank template)
     * @return Spreadsheet
     */
    public function buildWorkbook(array $templateDetails, array $periods, array $tasks = []): Spreadsheet
    {
        $book = new Spreadsheet();

        // -------------------------------------------------------------
        // SHEET 1: HOW TO FILL
        // -------------------------------------------------------------
        $sheet1 = $book->getActiveSheet();
        $sheet1->setTitle('HOW TO FILL');

        $templateName = $templateDetails['name'] ?? 'Project Template';
        $duration = $templateDetails['estimated_duration'] ?? count($periods);
        $unitLabel = str_replace('_', ' ', $templateDetails['duration_unit'] ?? 'working_days');

        $instructions = [
            ['GS ProjectFlow – Smart Template Task Plan Guide', ''],
            ['Template Name:', $templateName],
            ['Planned Duration:', "{$duration} {$unitLabel} (" . count($periods) . " Planning Periods)"],
            ['', ''],
            ['Step', 'Instructions & Best Practices'],
            ['1. Planning Periods', 'Review the "PLANNING PERIODS" worksheet. It contains your automatically generated Day / Week / Month planning periods and dates.'],
            ['2. Tasks Worksheet', 'Enter your project tasks in the "TASKS" worksheet. Keep the header row intact.'],
            ['3. Task #', 'Required. Enter a task number (e.g. 1, 2, 3). For subtasks, use decimal format (e.g. 1.1) or specify Parent # in column B.'],
            ['4. Subtasks', 'Optional. Enter the parent task number in "Parent # (Optional)" to nest subtasks (e.g. subtask 1.1 has Parent 1).'],
            ['5. Start / End Dates', 'Enter reference start and end dates (format YYYY-MM-DD, e.g. 2026-11-09). Dates shift automatically to real project start dates upon project creation.'],
            ['6. Working Hours', 'Optional. Specify 24-hour start and end times (format HH:MM, e.g. 09:00 and 17:00).'],
            ['7. Predecessors', 'Optional. Enter the Task # that must finish before this task starts (e.g. 1). Multiple predecessors can be comma-separated.'],
            ['8. Suggested Role', 'Optional. Assign a suggested corporate project role (e.g. Project Manager, Team Member, Project Owner, Sponsor).'],
            ['9. Upload Workbook', 'Save this file and click "Upload Excel" in GS ProjectFlow. Your task plan will be generated automatically!'],
        ];

        foreach ($instructions as $rowIdx => $row) {
            $sheet1->setCellValueExplicit([1, $rowIdx + 1], $row[0], DataType::TYPE_STRING);
            $sheet1->setCellValueExplicit([2, $rowIdx + 1], $row[1], DataType::TYPE_STRING);
        }

        // Style Sheet 1
        $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet1->getStyle('A5:B5')->getFont()->setBold(true);
        $sheet1->getStyle('A5:B5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF5F6F7');
        $sheet1->getColumnDimension('A')->setWidth(26);
        $sheet1->getColumnDimension('B')->setWidth(95);

        // -------------------------------------------------------------
        // SHEET 2: PLANNING PERIODS
        // -------------------------------------------------------------
        $sheet2 = $book->createSheet();
        $sheet2->setTitle('PLANNING PERIODS');

        $periodHeaders = ['Period ID', 'Period Name', 'Start Date', 'End Date'];
        foreach ($periodHeaders as $colIdx => $header) {
            $sheet2->setCellValueExplicit([$colIdx + 1, 1], $header, DataType::TYPE_STRING);
        }

        $periodRow = 2;
        foreach ($periods as $p) {
            $sheet2->setCellValueExplicit([1, $periodRow], (string) $p['id'], DataType::TYPE_STRING);
            $sheet2->setCellValueExplicit([2, $periodRow], (string) ($p['display_name'] ?? $p['system_label'] ?? $p['name']), DataType::TYPE_STRING);
            $sheet2->setCellValueExplicit([3, $periodRow], (string) ($p['starts_on'] ?? ''), DataType::TYPE_STRING);
            $sheet2->setCellValueExplicit([4, $periodRow], (string) ($p['ends_on'] ?? ''), DataType::TYPE_STRING);
            $periodRow++;
        }

        $sheet2->freezePane('A2');
        $sheet2->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet2->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF5F6F7');
        foreach (range('A', 'D') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        // -------------------------------------------------------------
        // SHEET 3: TASKS
        // -------------------------------------------------------------
        $sheet3 = $book->createSheet();
        $sheet3->setTitle('TASKS');

        $colKeys = array_keys(self::COLUMNS);
        $colLabels = array_values(self::COLUMNS);

        foreach ($colLabels as $colIdx => $label) {
            $sheet3->setCellValueExplicit([$colIdx + 1, 1], $label, DataType::TYPE_STRING);
        }

        // Period lookup map to convert task start/end date to Period ID
        $periodMap = [];
        foreach ($periods as $p) {
            $periodMap[$p['starts_on']] = $p['id'];
        }

        $taskRow = 2;
        foreach ($tasks as $t) {
            $startDate = !empty($t['start_date']) ? Carbon::parse($t['start_date'])->format('Y-m-d') : '';
            $endDate = !empty($t['end_date']) ? Carbon::parse($t['end_date'])->format('Y-m-d') : '';

            $startPeriod = $t['start_period'] ?? ($periodMap[$startDate] ?? '');
            $endPeriod = $t['end_period'] ?? ($periodMap[$endDate] ?? $startPeriod);

            $roleName = $t['suggested_role_name'] ?? ($t['suggestedRole']?->name ?? '');

            $rowVals = [
                $t['code'] ?? '',
                $t['parent_code'] ?? '',
                $t['title'] ?? '',
                $startDate,
                $t['start_time'] ?? '09:00',
                $endDate,
                $t['end_time'] ?? '17:00',
                $t['duration_days'] ?? 1,
                $t['dependency_code'] ?? '',
                $roleName,
                $t['description'] ?? '',
            ];

            foreach ($rowVals as $cIdx => $val) {
                $sheet3->setCellValueExplicit([$cIdx + 1, $taskRow], (string) ($val ?? ''), DataType::TYPE_STRING);
            }
            $taskRow++;
        }

        // Styling Sheet 3
        $sheet3->freezePane('A2');
        $sheet3->getStyle('A1:K1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet3->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF315F58');

        // Column widths
        $sheet3->getColumnDimension('A')->setWidth(10); // #
        $sheet3->getColumnDimension('B')->setWidth(20); // Parent #
        $sheet3->getColumnDimension('C')->setWidth(38); // Task Name
        $sheet3->getColumnDimension('D')->setWidth(16); // Start Date
        $sheet3->getColumnDimension('E')->setWidth(13); // Start Time
        $sheet3->getColumnDimension('F')->setWidth(16); // End Date
        $sheet3->getColumnDimension('G')->setWidth(13); // End Time
        $sheet3->getColumnDimension('H')->setWidth(15); // Duration
        $sheet3->getColumnDimension('I')->setWidth(18); // Predecessor #
        $sheet3->getColumnDimension('J')->setWidth(24); // Suggested Role
        $sheet3->getColumnDimension('K')->setWidth(40); // Description

        // Add Role Dropdown Validation on Column J
        $rolesList = ProjectRole::orderBy('name')->pluck('name')->all();
        if (!empty($rolesList)) {
            $roleValidation = new DataValidation();
            $roleValidation->setType(DataValidation::TYPE_LIST);
            $roleValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $roleValidation->setAllowBlank(true);
            $roleValidation->setShowDropDown(true);
            $roleFormula = '"' . implode(',', array_slice($rolesList, 0, 15)) . '"';
            $roleValidation->setFormula1($roleFormula);

            for ($r = 2; $r <= max(100, $taskRow + 50); $r++) {
                $sheet3->getCell("J{$r}")->setDataValidation(clone $roleValidation);
            }
        }

        // Set active sheet to TASKS for immediate user focus
        $book->setActiveSheetIndex(2);

        return $book;
    }

    /**
     * Export workbook for an existing ProjectTemplate.
     */
    public function exportWorkbook(ProjectTemplate $template): Spreadsheet
    {
        return $this->generateExcel($template);
    }

    /**
     * Generate complete 3-sheet Excel workbook for a ProjectTemplate.
     */
    public function generateExcel(ProjectTemplate $template): Spreadsheet
    {
        $template->load(['tasks.suggestedRole']);
        $duration = max(1, (int) ($template->estimated_duration ?: 14));
        $unit = $template->duration_unit ?: 'working_days';
        $refDate = $template->reference_start_date ? $template->reference_start_date->format('Y-m-d') : date('Y-m-d');

        $periods = $this->scheduleService->generatePeriods($refDate, $duration, $unit, $template->planning_periods ?? []);

        $tasks = $template->tasks->map(function (TemplateTask $t) {
            return [
                'code' => $t->code,
                'parent_code' => $t->parent_code,
                'title' => $t->title,
                'start_date' => $t->start_date?->format('Y-m-d'),
                'start_time' => $t->start_time ?: '09:00',
                'end_date' => $t->end_date?->format('Y-m-d'),
                'end_time' => $t->end_time ?: '17:00',
                'duration_days' => $t->duration_days ?: 1,
                'suggested_role_name' => $t->suggestedRole?->name ?? '',
                'dependency_code' => $t->dependency_code ?? '',
                'priority' => $t->priority ?: 'medium',
                'description' => $t->description ?? '',
            ];
        })->all();

        return $this->buildWorkbook([
            'id' => $template->id,
            'name' => $template->name,
            'estimated_duration' => $duration,
            'duration_unit' => $unit,
            'reference_start_date' => $refDate,
        ], $periods, $tasks);
    }

    /**
     * Build blank workbook based on template setup parameters.
     */
    public function blankWorkbook(?array $templateDetails = null, array $tasks = []): Spreadsheet
    {
        $templateDetails = $templateDetails ?? [];
        $duration = max(1, (int) ($templateDetails['estimated_duration'] ?? 14));
        $unit = $templateDetails['duration_unit'] ?? 'working_days';
        $refDate = $templateDetails['reference_start_date'] ?? date('Y-m-d');

        $periods = $this->scheduleService->generatePeriods($refDate, $duration, $unit);

        // Export tasks if provided, otherwise empty
        return $this->buildWorkbook($templateDetails, $periods, $tasks);
    }

    /**
     * Download blank standard Excel import template.
     */
    public function downloadBlank(?array $templateDetails = null, array $tasks = []): string
    {
        $book = $this->blankWorkbook($templateDetails, $tasks);
        $path = tempnam(sys_get_temp_dir(), 'gs_tpl_blank_');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
        return $path;
    }

    /**
     * Parse uploaded .xlsx file into normalized task items and period structure.
     *
     * @param UploadedFile $file
     * @param array|null $templateContext Context parameters (duration, unit, refDate, existing_tasks)
     * @return array
     */
    public function parse(UploadedFile $file, ?array $templateContext = null): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            throw ValidationException::withMessages(['file' => 'Only .xlsx or .csv files are supported.']);
        }

        try {
            $book = IOFactory::load($file->getRealPath());
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['file' => 'Unable to read the spreadsheet: ' . $e->getMessage()]);
        }

        // 1. Extract Planning Periods from 'PLANNING PERIODS' sheet if present
        $periodsSheet = $book->getSheetByName('PLANNING PERIODS')
            ?? $book->getSheetByName('Planning Periods')
            ?? $book->getSheetByName('PERIODS');

        $periods = [];
        $periodsById = [];
        $periodsByName = [];

        if ($periodsSheet) {
            $pRows = $periodsSheet->toArray(null, false, true, false);
            if (count($pRows) >= 2) {
                foreach (array_slice($pRows, 1) as $pRow) {
                    $pId = trim((string) ($pRow[0] ?? ''));
                    $pName = trim((string) ($pRow[1] ?? ''));
                    $pStart = trim((string) ($pRow[2] ?? ''));
                    $pEnd = trim((string) ($pRow[3] ?? ''));

                    if ($pId !== '') {
                        $periodObj = [
                            'id' => $pId,
                            'name' => $pName ?: $pId,
                            'starts_on' => $pStart,
                            'ends_on' => $pEnd ?: $pStart,
                        ];
                        $periods[] = $periodObj;
                        $periodsById[strtoupper($pId)] = $periodObj;
                        if ($pName !== '') {
                            $periodsByName[strtolower($pName)] = $periodObj;
                        }
                    }
                }
            }
        }

        // Fallback: If no periods sheet was in workbook, use template context
        if (empty($periods) && !empty($templateContext)) {
            $cDuration = (int) ($templateContext['estimated_duration'] ?? 14);
            $cUnit = $templateContext['duration_unit'] ?? 'working_days';
            $cStart = $templateContext['reference_start_date'] ?? date('Y-m-d');
            $periods = $this->scheduleService->generatePeriods($cStart, $cDuration, $cUnit);

            foreach ($periods as $p) {
                $periodsById[strtoupper($p['id'])] = $p;
                $periodsByName[strtolower($p['system_label'])] = $p;
                if (!empty($p['name'])) {
                    $periodsByName[strtolower($p['name'])] = $p;
                }
            }
        }

        // 2. Extract Tasks from 'TASKS' sheet (or fallback to active sheet)
        $tasksSheet = $book->getSheetByName('TASKS')
            ?? $book->getSheetByName('Tasks')
            ?? $book->getActiveSheet();

        $rows = $tasksSheet->toArray(null, false, true, false);
        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => 'The uploaded spreadsheet has no task rows.']);
        }

        // Header mapping
        $headers = array_map(fn ($v) => strtolower(trim((string) $v)), $rows[0]);
        $headerMap = [];

        foreach ($headers as $colIdx => $rawHeader) {
            $rawLower = strtolower(trim((string) $rawHeader));
            if ($rawLower === '#' || $rawLower === 'no' || $rawLower === 'no.' || $rawLower === 'task #' || $rawLower === 'task no') {
                $headerMap['task_code'] = $colIdx;
                continue;
            }
            $header = strtolower(str_replace([' ', '_', '(', ')'], '', $rawLower));
            if (str_contains($header, 'parent')) {
                $headerMap['parent_task_code'] = $colIdx;
            } elseif (str_contains($header, 'taskcode') || $header === 'code' || str_contains($header, 'task#') || str_contains($header, 'taskno')) {
                $headerMap['task_code'] = $colIdx;
            } elseif (str_contains($header, 'taskname') || $header === 'title' || $header === 'name') {
                $headerMap['task_name'] = $colIdx;
            } elseif (str_contains($header, 'startperiod') || $header === 'fromperiod') {
                $headerMap['start_period'] = $colIdx;
            } elseif (str_contains($header, 'endperiod') || $header === 'toperiod') {
                $headerMap['end_period'] = $colIdx;
            } elseif (str_contains($header, 'startdate') || $header === 'starts') {
                $headerMap['start_date'] = $colIdx;
            } elseif (str_contains($header, 'enddate') || $header === 'ends' || $header === 'finish') {
                $headerMap['end_date'] = $colIdx;
            } elseif (str_contains($header, 'starttime')) {
                $headerMap['start_time'] = $colIdx;
            } elseif (str_contains($header, 'endtime')) {
                $headerMap['end_time'] = $colIdx;
            } elseif (str_contains($header, 'duration')) {
                $headerMap['duration_days'] = $colIdx;
            } elseif (str_contains($header, 'depend') || str_contains($header, 'predecessor')) {
                $headerMap['dependency_code'] = $colIdx;
            } elseif (str_contains($header, 'role')) {
                $headerMap['suggested_role'] = $colIdx;
            } elseif (str_contains($header, 'description') || str_contains($header, 'desc') || str_contains($header, 'notes')) {
                $headerMap['description'] = $colIdx;
            }
        }

        // Read and parse raw task rows
        $parsedRows = [];
        foreach (array_slice($rows, 1) as $idx => $row) {
            // Check if entire row is empty
            $nonEmpty = array_filter($row, fn ($c) => trim((string) $c) !== '');
            if (empty($nonEmpty)) {
                continue;
            }

            $item = [
                'excel_row' => $idx + 2,
                'task_code' => isset($headerMap['task_code']) ? trim((string) ($row[$headerMap['task_code']] ?? '')) : trim((string) ($row[0] ?? '')),
                'parent_task_code' => isset($headerMap['parent_task_code']) ? trim((string) ($row[$headerMap['parent_task_code']] ?? '')) : trim((string) ($row[1] ?? '')),
                'task_name' => isset($headerMap['task_name']) ? trim((string) ($row[$headerMap['task_name']] ?? '')) : trim((string) ($row[2] ?? '')),
                'start_period' => isset($headerMap['start_period']) ? trim((string) ($row[$headerMap['start_period']] ?? '')) : (empty($headerMap) ? trim((string) ($row[3] ?? '')) : ''),
                'end_period' => isset($headerMap['end_period']) ? trim((string) ($row[$headerMap['end_period']] ?? '')) : (empty($headerMap) ? trim((string) ($row[4] ?? '')) : ''),
                'start_date' => isset($headerMap['start_date']) ? trim((string) ($row[$headerMap['start_date']] ?? '')) : '',
                'end_date' => isset($headerMap['end_date']) ? trim((string) ($row[$headerMap['end_date']] ?? '')) : '',
                'start_time' => isset($headerMap['start_time']) ? trim((string) ($row[$headerMap['start_time']] ?? '')) : trim((string) ($row[5] ?? '09:00')),
                'end_time' => isset($headerMap['end_time']) ? trim((string) ($row[$headerMap['end_time']] ?? '')) : trim((string) ($row[6] ?? '17:00')),
                'duration_days' => isset($headerMap['duration_days']) ? trim((string) ($row[$headerMap['duration_days']] ?? '')) : trim((string) ($row[7] ?? '1')),
                'dependency_code' => isset($headerMap['dependency_code']) ? trim((string) ($row[$headerMap['dependency_code']] ?? '')) : trim((string) ($row[8] ?? '')),
                'suggested_role' => isset($headerMap['suggested_role']) ? trim((string) ($row[$headerMap['suggested_role']] ?? '')) : trim((string) ($row[9] ?? '')),
                'description' => isset($headerMap['description']) ? trim((string) ($row[$headerMap['description']] ?? '')) : trim((string) ($row[10] ?? '')),
            ];

            $parsedRows[] = $item;
        }

        return $this->validateAndNormalize($parsedRows, $periodsById, $periodsByName, $periods, $templateContext);
    }

    /**
     * Parse raw tab-delimited or comma-delimited text pasted from Excel.
     */
    public function parseRawText(string $rawText, ?array $templateContext = null): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($rawText));
        $parsedRows = [];
        $headerMap = [];
        $dataRows = [];

        foreach ($lines as $lineIndex => $line) {
            $line = trim($line);
            if ($line === '') continue;

            $cells = str_contains($line, "\t") ? explode("\t", $line) : str_getcsv($line);
            $cells = array_map(fn ($c) => trim($c, " \t\n\r\0\x0B\"'"), $cells);

            // Detect header row
            if (empty($headerMap) && (
                strcasecmp($cells[0] ?? '', '#') === 0 ||
                strcasecmp($cells[0] ?? '', 'No') === 0 ||
                strcasecmp($cells[0] ?? '', 'Task #') === 0 ||
                strcasecmp($cells[0] ?? '', 'Task No') === 0 ||
                strcasecmp($cells[0] ?? '', 'task_code') === 0 || 
                strcasecmp($cells[0] ?? '', 'Task Code') === 0 || 
                strcasecmp($cells[0] ?? '', 'code') === 0 ||
                strcasecmp($cells[2] ?? '', 'task_name') === 0 ||
                strcasecmp($cells[2] ?? '', 'Task Name') === 0
            )) {
                foreach ($cells as $colIdx => $header) {
                    $raw = strtolower(trim((string) $header));
                    if ($raw === '#' || $raw === 'no' || $raw === 'task #' || $raw === 'task no') {
                        $headerMap['task_code'] = $colIdx;
                        continue;
                    }
                    $norm = strtolower(str_replace([' ', '_', '(', ')'], '', $header));
                    if (str_contains($norm, 'parent')) {
                        $headerMap['parent_task_code'] = $colIdx;
                    } elseif (str_contains($norm, 'taskcode') || $norm === 'code' || str_contains($norm, 'task#') || str_contains($norm, 'taskno')) {
                        $headerMap['task_code'] = $colIdx;
                    } elseif (str_contains($norm, 'taskname') || $norm === 'title' || $norm === 'name') {
                        $headerMap['task_name'] = $colIdx;
                    } elseif (str_contains($norm, 'startperiod') || $norm === 'fromperiod') {
                        $headerMap['start_period'] = $colIdx;
                    } elseif (str_contains($norm, 'endperiod') || $norm === 'toperiod') {
                        $headerMap['end_period'] = $colIdx;
                    } elseif (str_contains($norm, 'startdate') || $norm === 'starts') {
                        $headerMap['start_date'] = $colIdx;
                    } elseif (str_contains($norm, 'enddate') || $norm === 'ends' || $norm === 'finish') {
                        $headerMap['end_date'] = $colIdx;
                    } elseif (str_contains($norm, 'starttime')) {
                        $headerMap['start_time'] = $colIdx;
                    } elseif (str_contains($norm, 'endtime')) {
                        $headerMap['end_time'] = $colIdx;
                    } elseif (str_contains($norm, 'duration')) {
                        $headerMap['duration_days'] = $colIdx;
                    } elseif (str_contains($norm, 'depend') || str_contains($norm, 'predecessor')) {
                        $headerMap['dependency_code'] = $colIdx;
                    } elseif (str_contains($norm, 'role')) {
                        $headerMap['suggested_role'] = $colIdx;
                    } elseif (str_contains($norm, 'desc') || str_contains($norm, 'notes')) {
                        $headerMap['description'] = $colIdx;
                    }
                }
                continue;
            }

            $dataRows[] = [
                'excel_row' => $lineIndex + 1,
                'cells' => $cells,
            ];
        }

        foreach ($dataRows as $d) {
            $cells = $d['cells'];
            $line = $d['excel_row'];

            if (!empty($headerMap)) {
                $item = [
                    'excel_row' => $line,
                    'task_code' => isset($headerMap['task_code']) ? ($cells[$headerMap['task_code']] ?? '') : ($cells[0] ?? ''),
                    'parent_task_code' => isset($headerMap['parent_task_code']) ? ($cells[$headerMap['parent_task_code']] ?? '') : '',
                    'task_name' => isset($headerMap['task_name']) ? ($cells[$headerMap['task_name']] ?? '') : ($cells[2] ?? ''),
                    'start_period' => isset($headerMap['start_period']) ? ($cells[$headerMap['start_period']] ?? '') : '',
                    'end_period' => isset($headerMap['end_period']) ? ($cells[$headerMap['end_period']] ?? '') : '',
                    'start_date' => isset($headerMap['start_date']) ? ($cells[$headerMap['start_date']] ?? '') : '',
                    'end_date' => isset($headerMap['end_date']) ? ($cells[$headerMap['end_date']] ?? '') : '',
                    'start_time' => isset($headerMap['start_time']) ? ($cells[$headerMap['start_time']] ?? '09:00') : '09:00',
                    'end_time' => isset($headerMap['end_time']) ? ($cells[$headerMap['end_time']] ?? '17:00') : '17:00',
                    'duration_days' => isset($headerMap['duration_days']) ? ($cells[$headerMap['duration_days']] ?? '1') : '1',
                    'dependency_code' => isset($headerMap['dependency_code']) ? ($cells[$headerMap['dependency_code']] ?? '') : '',
                    'suggested_role' => isset($headerMap['suggested_role']) ? ($cells[$headerMap['suggested_role']] ?? '') : '',
                    'description' => isset($headerMap['description']) ? ($cells[$headerMap['description']] ?? '') : '',
                ];
            } else {
                $item = [
                    'excel_row' => $line,
                    'task_code' => $cells[0] ?? '',
                    'parent_task_code' => $cells[1] ?? '',
                    'task_name' => $cells[2] ?? '',
                    'start_period' => $cells[3] ?? '',
                    'end_period' => $cells[4] ?? '',
                    'start_date' => '',
                    'end_date' => '',
                    'start_time' => $cells[5] ?? '09:00',
                    'end_time' => $cells[6] ?? '17:00',
                    'duration_days' => $cells[7] ?? '1',
                    'dependency_code' => $cells[8] ?? '',
                    'suggested_role' => $cells[9] ?? '',
                    'description' => $cells[10] ?? '',
                ];

                if (empty($item['task_name']) && !empty($item['task_code']) && count($cells) === 1) {
                    $item['task_name'] = $item['task_code'];
                    $item['task_code'] = '';
                }
            }

            $parsedRows[] = $item;
        }

        // Generate default periods from context if needed
        $cDuration = (int) ($templateContext['estimated_duration'] ?? 14);
        $cUnit = $templateContext['duration_unit'] ?? 'working_days';
        $cStart = $templateContext['reference_start_date'] ?? date('Y-m-d');
        $periods = $this->scheduleService->generatePeriods($cStart, $cDuration, $cUnit);
        $pById = [];
        $pByName = [];
        foreach ($periods as $p) {
            $pById[strtoupper($p['id'])] = $p;
            $pByName[strtolower($p['system_label'])] = $p;
        }

        return $this->validateAndNormalize($parsedRows, $pById, $pByName, $periods, $templateContext);
    }

    /**
     * Validate and normalize parsed Excel rows into structured tasks and periods.
     */
    protected function validateAndNormalize(
        array $rows,
        array $periodsById,
        array $periodsByName,
        array $periods,
        ?array $templateContext = null
    ): array {
        $errors = [];
        $normalized = [];
        $roles = ProjectRole::all();
        $codeCounts = [];
        $autoCodeIndex = 1;

        $firstPeriod = !empty($periods) ? $periods[0] : null;

        foreach ($rows as $index => $row) {
            $line = $row['excel_row'] ?? ($index + 2);
            $code = trim($row['task_code'] ?? '');
            $parentCode = trim($row['parent_task_code'] ?? '');
            $title = trim($row['task_name'] ?? '');

            // 1. Task Name Validation
            if ($title === '') {
                $errors[] = "Row {$line}: Task Name is required.";
                continue;
            }

            // 2. Auto-generate Task Code if omitted
            if ($code === '') {
                $code = $parentCode !== '' ? "{$parentCode}.{$autoCodeIndex}" : (string) $autoCodeIndex;
                $autoCodeIndex++;
            }

            // 3. Duplicate Task Code Detection
            if (isset($codeCounts[$code])) {
                $errors[] = "Row {$line}: Duplicate task code '{$code}'.";
            }
            $codeCounts[$code] = true;

            // 4. Resolve Planning Periods to Actual Dates
            $startPeriodInput = trim($row['start_period'] ?? '');
            $endPeriodInput = trim($row['end_period'] ?? '') ?: $startPeriodInput;
            $startDate = !empty($row['start_date']) ? trim($row['start_date']) : null;
            $endDate = !empty($row['end_date']) ? trim($row['end_date']) : null;

            // If start_period or end_period is formatted as a date YYYY-MM-DD
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startPeriodInput)) {
                $startDate = $startPeriodInput;
                $startPeriodInput = '';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endPeriodInput)) {
                $endDate = $endPeriodInput;
                $endPeriodInput = '';
            }

            $resolvedStartPeriod = null;
            $resolvedEndPeriod = null;

            if ($startPeriodInput !== '') {
                $key = strtoupper($startPeriodInput);
                $keyName = strtolower($startPeriodInput);
                if (isset($periodsById[$key])) {
                    $resolvedStartPeriod = $periodsById[$key];
                } elseif (isset($periodsByName[$keyName])) {
                    $resolvedStartPeriod = $periodsByName[$keyName];
                } else {
                    $errors[] = "Row {$line}: Start Period '{$startPeriodInput}' does not exist.";
                }
            }

            if ($endPeriodInput !== '') {
                $key = strtoupper($endPeriodInput);
                $keyName = strtolower($endPeriodInput);
                if (isset($periodsById[$key])) {
                    $resolvedEndPeriod = $periodsById[$key];
                } elseif (isset($periodsByName[$keyName])) {
                    $resolvedEndPeriod = $periodsByName[$keyName];
                } else {
                    $errors[] = "Row {$line}: End Period '{$endPeriodInput}' does not exist.";
                }
            }

            // Derive dates from resolved periods if not directly provided
            if ($resolvedStartPeriod) {
                $startDate = $resolvedStartPeriod['starts_on'];
            }
            if ($resolvedEndPeriod) {
                $endDate = $resolvedEndPeriod['ends_on'];
            }

            // If direct dates were provided, find matching period
            if ($startDate && !$resolvedStartPeriod && !empty($periods)) {
                foreach ($periods as $p) {
                    if ($startDate >= $p['starts_on'] && $startDate <= $p['ends_on']) {
                        $resolvedStartPeriod = $p;
                        break;
                    }
                }
                if (!$resolvedStartPeriod) {
                    $resolvedStartPeriod = $firstPeriod;
                }
            }

            if ($endDate && !$resolvedEndPeriod && !empty($periods)) {
                foreach ($periods as $p) {
                    if ($endDate >= $p['starts_on'] && $endDate <= $p['ends_on']) {
                        $resolvedEndPeriod = $p;
                        break;
                    }
                }
                if (!$resolvedEndPeriod) {
                    $resolvedEndPeriod = $resolvedStartPeriod;
                }
            }

            // If still empty, fall back to first period
            if (!$startDate && $firstPeriod) {
                $startDate = $firstPeriod['starts_on'];
                $resolvedStartPeriod = $firstPeriod;
            }
            if (!$endDate) {
                $endDate = $startDate;
                $resolvedEndPeriod = $resolvedStartPeriod;
            }

            if ($startDate && $endDate && $startDate > $endDate) {
                $errors[] = "Row {$line} ({$code}): End date ({$endDate}) cannot be earlier than start date ({$startDate}).";
            }

            // 5. Working Times
            $startTime = trim($row['start_time'] ?? '09:00');
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $startTime)) {
                $startTime = '09:00';
            }

            $endTime = trim($row['end_time'] ?? '17:00');
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $endTime)) {
                $endTime = '17:00';
            }

            // 6. Duration
            $duration = (int) ($row['duration_days'] ?? 1);
            if ($startDate && $endDate) {
                $calculatedDur = max(1, (int) Carbon::parse($startDate)->diffInWeekdays(Carbon::parse($endDate)->addDay()));
                $duration = $calculatedDur;
            }
            if ($duration < 1) $duration = 1;
            if ($duration > 365) $duration = 365;

            // 7. Suggested Role
            $roleInput = trim($row['suggested_role'] ?? '');
            $roleId = null;
            $roleName = null;
            if ($roleInput !== '') {
                $match = $roles->first(function ($r) use ($roleInput) {
                    return strcasecmp($r->name, $roleInput) === 0 || strcasecmp($r->slug, $roleInput) === 0;
                });
                if ($match) {
                    $roleId = $match->id;
                    $roleName = $match->name;
                }
            }

            // 8. Parent Code
            if ($parentCode === $code && $code !== '') {
                $errors[] = "Row {$line} ({$code}): A task cannot be its own parent.";
            }

            $normalized[] = [
                'excel_row' => $line,
                'code' => $code,
                'parent_code' => $parentCode ?: null,
                'title' => $title,
                'description' => trim($row['description'] ?? '') ?: null,
                'start_period' => $resolvedStartPeriod ? $resolvedStartPeriod['id'] : null,
                'start_period_name' => $resolvedStartPeriod ? ($resolvedStartPeriod['display_name'] ?? $resolvedStartPeriod['name']) : null,
                'end_period' => $resolvedEndPeriod ? $resolvedEndPeriod['id'] : null,
                'end_period_name' => $resolvedEndPeriod ? ($resolvedEndPeriod['display_name'] ?? $resolvedEndPeriod['name']) : null,
                'start_date' => $startDate,
                'start_time' => $startTime,
                'end_date' => $endDate,
                'end_time' => $endTime,
                'duration_days' => $duration,
                'suggested_role_id' => $roleId,
                'suggested_role_name' => $roleName,
                'dependency_code' => trim($row['dependency_code'] ?? '') ?: null,
                'priority' => 'medium',
                'sort_order' => $index,
                'is_subtask' => !empty($parentCode),
            ];
        }

        // 9. Parent Code Integrity
        $allCodes = array_column($normalized, 'code');
        foreach ($normalized as $item) {
            if ($item['parent_code'] && !in_array($item['parent_code'], $allCodes, true)) {
                $errors[] = "Row {$item['excel_row']}: Parent task code '{$item['parent_code']}' not found in the task list.";
            }
        }

        // 10. Dependency Validation and Cycle Detection
        $depGraph = [];
        foreach ($normalized as $item) {
            $c = $item['code'];
            $dep = $item['dependency_code'];
            if ($dep) {
                $deps = array_values(array_filter(array_map('trim', explode(',', $dep))));
                foreach ($deps as $d) {
                    if ($d === $c) {
                        $errors[] = "Row {$item['excel_row']} ({$c}): Task cannot depend on itself.";
                    } elseif (!in_array($d, $allCodes, true)) {
                        $errors[] = "Row {$item['excel_row']} ({$c}): Dependency '{$d}' was not found in the task list.";
                    }
                }
                $depGraph[$c] = array_filter($deps, fn ($d) => $d !== $c);
            } else {
                $depGraph[$c] = [];
            }
        }

        // DFS for Cycle Detection
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
                    $errors[] = "Circular dependency detected between tasks: " . implode(' -> ', $cyclePath);
                    break;
                }
            }
        }

        // Count main tasks vs subtasks
        $mainTasksCount = count(array_filter($normalized, fn ($t) => empty($t['parent_code'])));
        $subtasksCount = count(array_filter($normalized, fn ($t) => !empty($t['parent_code'])));

        // 11. Comparison with Existing Tasks (if updating existing template or draft)
        $comparison = [
            'to_add' => [],
            'to_update' => [],
            'unchanged' => [],
            'missing' => [],
        ];

        $existingTasks = $templateContext['existing_tasks'] ?? [];
        if (!empty($existingTasks)) {
            $existingByCode = collect($existingTasks)->keyBy('code');

            foreach ($normalized as $t) {
                if ($existingByCode->has($t['code'])) {
                    $ext = $existingByCode->get($t['code']);
                    $isChanged = ($ext['title'] ?? '') !== $t['title']
                        || ($ext['start_date'] ?? '') !== $t['start_date']
                        || ($ext['end_date'] ?? '') !== $t['end_date']
                        || ($ext['dependency_code'] ?? '') !== ($t['dependency_code'] ?? '');

                    if ($isChanged) {
                        $comparison['to_update'][] = $t['code'];
                    } else {
                        $comparison['unchanged'][] = $t['code'];
                    }
                } else {
                    $comparison['to_add'][] = $t['code'];
                }
            }

            $uploadedCodes = array_column($normalized, 'code');
            foreach ($existingByCode as $code => $ext) {
                if (!in_array($code, $uploadedCodes, true)) {
                    $comparison['missing'][] = [
                        'code' => $code,
                        'title' => $ext['title'] ?? $code,
                    ];
                }
            }
        }

        return [
            'tasks' => $normalized,
            'errors' => array_values(array_unique($errors)),
            'count' => count($normalized),
            'tasks_found' => count($normalized),
            'main_tasks_count' => $mainTasksCount,
            'subtasks_count' => $subtasksCount,
            'errors_count' => count($errors),
            'comparison' => $comparison,
            'planning_periods' => $periods,
        ];
    }
}
