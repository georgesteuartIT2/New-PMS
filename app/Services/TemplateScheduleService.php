<?php

namespace App\Services;

use App\Models\{ProjectTemplate, TemplateTask};
use Carbon\Carbon;

/**
 * Enterprise Template Schedule & Planning Period Engine.
 * Template dates are reference dates, never fixed dates for new projects.
 */
class TemplateScheduleService
{
    /**
     * Map a template task's relative reference dates to an actual project's calendar schedule.
     */
    public function mapTaskDates(ProjectTemplate $template, TemplateTask $task, Carbon $projectStart): array
    {
        $reference = $template->reference_start_date?->copy()->startOfDay()
            ?? $template->tasks->pluck('start_date')->filter()->min()?->copy()->startOfDay()
            ?? $projectStart->copy()->startOfDay();

        $start = $this->shiftWorkday(
            $reference,
            $task->start_date,
            $projectStart,
            $task->start_time ?: '09:00',
            max(0, (int) $task->sort_order - 1)
        );

        if ($task->end_date) {
            $end = $this->shiftWorkday(
                $reference,
                $task->end_date,
                $projectStart,
                $task->end_time ?: '17:00'
            );
        } else {
            $end = $start->copy()->addWeekdays(max(0, (int) $task->duration_days - 1))->setTimeFromTimeString('17:00');
        }

        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->copy()->addHour();
        }

        return [$start, $end];
    }

    private function shiftWorkday(Carbon $reference, $relativeDate, Carbon $projectStart, string $time, int $fallbackDays = 0): Carbon
    {
        $reference = $reference->copy()->startOfDay();
        if (!$reference->isWeekday()) {
            $reference->next(Carbon::MONDAY);
        }

        $target = $relativeDate ? Carbon::parse($relativeDate)->startOfDay() : null;
        if ($target && !$target->isWeekday()) {
            $target->next(Carbon::MONDAY);
        }

        $days = 0;
        if ($target) {
            while ($reference->lessThan($target)) {
                $reference->addDay();
                if ($reference->isWeekday()) {
                    $days++;
                }
            }
        } else {
            $days = $fallbackDays;
        }

        $mapped = $projectStart->copy()->startOfDay();
        if (!$mapped->isWeekday()) {
            $mapped->next(Carbon::MONDAY);
        }
        if ($days) {
            $mapped->addWeekdays($days);
        }

        return $mapped->setTimeFromTimeString($time);
    }

    /**
     * Generate date-aware planning periods (Day / Working Day / Week / Month).
     *
     * @param Carbon|string $referenceStartDate
     * @param int $duration
     * @param string $unit (days, working_days, weeks, months)
     * @param array|null $customLabels Optional custom user labels keyed by period ID or number
     * @return array
     */
    public function generatePeriods(Carbon|string $referenceStartDate, int $duration, string $unit = 'working_days', ?array $customLabels = null): array
    {
        $duration = max(1, $duration);
        $start = is_string($referenceStartDate) ? Carbon::parse($referenceStartDate)->startOfDay() : $referenceStartDate->copy()->startOfDay();
        $periods = [];
        $customLabels = $customLabels ?? [];

        switch ($unit) {
            case 'days':
                // Consecutive calendar days
                for ($i = 1; $i <= $duration; $i++) {
                    $id = 'DAY-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                    $periodDate = $start->copy()->addDays($i - 1);
                    $systemLabel = "Day {$i}";
                    $userLabel = $customLabels[$id] ?? $customLabels[$i] ?? $customLabels[$systemLabel] ?? '';
                    $displayName = !empty($userLabel) ? "{$systemLabel} – {$userLabel}" : $systemLabel;

                    $periods[] = [
                        'id' => $id,
                        'number' => $i,
                        'system_number' => $i,
                        'system_label' => $systemLabel,
                        'label' => $userLabel,
                        'user_label' => !empty($userLabel) ? $userLabel : null,
                        'name' => !empty($userLabel) ? $userLabel : $systemLabel,
                        'display_name' => $displayName,
                        'starts_on' => $periodDate->format('Y-m-d'),
                        'ends_on' => $periodDate->format('Y-m-d'),
                        'start_date' => $periodDate->format('Y-m-d'),
                        'end_date' => $periodDate->format('Y-m-d'),
                        'starts_on_formatted' => $periodDate->format('d M Y'),
                        'ends_on_formatted' => $periodDate->format('d M Y'),
                        'date_range_display' => $periodDate->format('d M Y'),
                        'is_working_day' => $periodDate->isWeekday(),
                    ];
                }
                break;

            case 'working_days':
                // Consecutive working days (Monday - Friday)
                // If reference start date is a weekend, preserve display while first working day begins on Monday
                $cursor = $start->copy();
                if (!$cursor->isWeekday()) {
                    $cursor->next(Carbon::MONDAY);
                }

                for ($i = 1; $i <= $duration; $i++) {
                    $id = 'DAY-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                    $systemLabel = "Working Day {$i}";
                    $userLabel = $customLabels[$id] ?? $customLabels[$i] ?? $customLabels[$systemLabel] ?? '';
                    $displayName = !empty($userLabel) ? "{$systemLabel} – {$userLabel}" : $systemLabel;

                    $periods[] = [
                        'id' => $id,
                        'number' => $i,
                        'system_number' => $i,
                        'system_label' => $systemLabel,
                        'label' => $userLabel,
                        'user_label' => !empty($userLabel) ? $userLabel : null,
                        'name' => !empty($userLabel) ? $userLabel : $systemLabel,
                        'display_name' => $displayName,
                        'starts_on' => $cursor->format('Y-m-d'),
                        'ends_on' => $cursor->format('Y-m-d'),
                        'start_date' => $cursor->format('Y-m-d'),
                        'end_date' => $cursor->format('Y-m-d'),
                        'starts_on_formatted' => $cursor->format('d M Y'),
                        'ends_on_formatted' => $cursor->format('d M Y'),
                        'date_range_display' => $cursor->format('d M Y'),
                        'is_working_day' => true,
                    ];

                    // Advance cursor to next working day
                    if ($i < $duration) {
                        $cursor->addWeekday();
                    }
                }
                break;

            case 'weeks':
                // Consecutive 7-calendar-day intervals
                for ($i = 1; $i <= $duration; $i++) {
                    $id = 'WEEK-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                    $weekStart = $start->copy()->addWeeks($i - 1);
                    $weekEnd = $weekStart->copy()->addDays(6);
                    $systemLabel = "Week {$i}";
                    $userLabel = $customLabels[$id] ?? $customLabels[$i] ?? $customLabels[$systemLabel] ?? '';
                    $displayName = !empty($userLabel) ? "{$systemLabel} – {$userLabel}" : $systemLabel;

                    $dateRange = ($weekStart->format('Y') === $weekEnd->format('Y'))
                        ? $weekStart->format('d M') . ' to ' . $weekEnd->format('d M Y')
                        : $weekStart->format('d M Y') . ' to ' . $weekEnd->format('d M Y');

                    $periods[] = [
                        'id' => $id,
                        'number' => $i,
                        'system_number' => $i,
                        'system_label' => $systemLabel,
                        'label' => $userLabel,
                        'user_label' => !empty($userLabel) ? $userLabel : null,
                        'name' => !empty($userLabel) ? $userLabel : $systemLabel,
                        'display_name' => $displayName,
                        'starts_on' => $weekStart->format('Y-m-d'),
                        'ends_on' => $weekEnd->format('Y-m-d'),
                        'start_date' => $weekStart->format('Y-m-d'),
                        'end_date' => $weekEnd->format('Y-m-d'),
                        'starts_on_formatted' => $weekStart->format('d M Y'),
                        'ends_on_formatted' => $weekEnd->format('d M Y'),
                        'date_range_display' => $dateRange,
                        'is_working_day' => true,
                    ];
                }
                break;

            case 'months':
                // Consecutive calendar-month intervals anchored to reference start date
                for ($i = 1; $i <= $duration; $i++) {
                    $id = 'MONTH-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                    $monthStart = $start->copy()->addMonthsNoOverflow($i - 1);
                    $monthEnd = $start->copy()->addMonthsNoOverflow($i)->subDay();
                    $systemLabel = "Month {$i}";
                    $userLabel = $customLabels[$id] ?? $customLabels[$i] ?? $customLabels[$systemLabel] ?? '';
                    $displayName = !empty($userLabel) ? "{$systemLabel} – {$userLabel}" : $systemLabel;

                    $dateRange = ($monthStart->format('Y') === $monthEnd->format('Y'))
                        ? $monthStart->format('d M') . ' to ' . $monthEnd->format('d M Y')
                        : $monthStart->format('d M Y') . ' to ' . $monthEnd->format('d M Y');

                    $periods[] = [
                        'id' => $id,
                        'number' => $i,
                        'system_number' => $i,
                        'system_label' => $systemLabel,
                        'label' => $userLabel,
                        'user_label' => !empty($userLabel) ? $userLabel : null,
                        'name' => !empty($userLabel) ? $userLabel : $systemLabel,
                        'display_name' => $displayName,
                        'starts_on' => $monthStart->format('Y-m-d'),
                        'ends_on' => $monthEnd->format('Y-m-d'),
                        'start_date' => $monthStart->format('Y-m-d'),
                        'end_date' => $monthEnd->format('Y-m-d'),
                        'starts_on_formatted' => $monthStart->format('d M Y'),
                        'ends_on_formatted' => $monthEnd->format('d M Y'),
                        'date_range_display' => $dateRange,
                        'is_working_day' => true,
                    ];
                }
                break;
        }

        return $periods;
    }

    /**
     * Map a period identifier (e.g. 'DAY-001', 'Day 1', 'Week 2') to matching period dates.
     */
    public function mapPeriodToDates(string $periodIdentifier, array $periods, ?string $startTime = '09:00', ?string $endTime = '17:00'): ?array
    {
        $clean = trim($periodIdentifier);
        if ($clean === '') {
            return null;
        }

        foreach ($periods as $p) {
            if (
                strcasecmp($p['id'], $clean) === 0 ||
                strcasecmp($p['system_label'], $clean) === 0 ||
                strcasecmp($p['display_name'], $clean) === 0 ||
                strcasecmp($p['name'], $clean) === 0
            ) {
                return [
                    'period' => $p,
                    'start_date' => $p['starts_on'],
                    'end_date' => $p['ends_on'],
                    'start_time' => $startTime ?: '09:00',
                    'end_time' => $endTime ?: '17:00',
                ];
            }
        }

        return null;
    }

    public function calculate(ProjectTemplate $template, ?string $startDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay()
            : ($template->reference_start_date?->copy()->startOfDay() ?? Carbon::today());
        $est = max(1, (int) ($template->estimated_duration ?: 14));
        $unit = $template->duration_unit ?: 'working_days';

        $finish = match ($unit) {
            'days' => $start->copy()->addDays(max(0, $est - 1)),
            'weeks' => $start->copy()->addWeeks($est)->subDay(),
            'months' => $start->copy()->addMonthsNoOverflow($est)->subDay(),
            default => $this->workdayEnd($start, $est),
        };

        $calculatedFinish = $finish->copy();
        $maxTaskEnd = null;
        $tasks = $template->relationLoaded('tasks') ? $template->tasks : $template->tasks()->get();

        foreach ($tasks as $task) {
            [, $end] = $this->mapTaskDates($template, $task, $start);
            if (!$maxTaskEnd || $end->greaterThan($maxTaskEnd)) {
                $maxTaskEnd = $end;
            }
        }

        $tasksExceedPlanned = $maxTaskEnd && $maxTaskEnd->startOfDay()->greaterThan($calculatedFinish->startOfDay());
        if ($tasksExceedPlanned) {
            $finish = $maxTaskEnd;
        }

        $normalStart = $start->copy();
        if (!$normalStart->isWeekday()) {
            $normalStart->next(Carbon::MONDAY);
        }
        $workingDays = $normalStart->diffInWeekdays($finish->copy()->addDay());

        return [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'task_count' => $tasks->count(),
            'working_days' => max(1, (int) $workingDays),
            'configured_working_days' => max(1, (int) $normalStart->diffInWeekdays($calculatedFinish->copy()->addDay())),
            'tasks_exceed_planned' => (bool) $tasksExceedPlanned,
            'duration_unit' => $unit,
            'working_hours' => $template->working_hours ?: '09:00 - 17:00',
            'summary' => $tasks->count() . ' tasks · ' . $est . ' ' . str_replace('_', ' ', $unit),
            'starts_on' => $start->format('Y-m-d'),
            'deadline' => $finish->format('Y-m-d'),
            'deadline_formatted' => $finish->format('d M Y'),
        ];
    }

    public function calculateReferenceSchedule(string|Carbon $startDate, int $duration, string $unit = 'working_days'): array
    {
        $start = is_string($startDate) ? Carbon::parse($startDate)->startOfDay() : $startDate->copy()->startOfDay();
        $normalStart = $start->copy();
        if ($unit === 'working_days' && !$normalStart->isWeekday()) {
            $normalStart->next(Carbon::MONDAY);
        }

        $finish = match ($unit) {
            'days' => $normalStart->copy()->addDays(max(0, $duration - 1)),
            'weeks' => $normalStart->copy()->addWeeks($duration)->subDay(),
            'months' => $normalStart->copy()->addMonthsNoOverflow($duration)->subDay(),
            default => $this->workdayEnd($normalStart, max(1, $duration)),
        };

        return [
            'starts_on' => $normalStart->format('Y-m-d'),
            'starts_on_formatted' => $normalStart->format('d M Y'),
            'ends_on' => $finish->format('Y-m-d'),
            'ends_on_formatted' => $finish->format('d M Y'),
            'duration' => $duration,
            'duration_unit' => $unit,
            'summary' => $duration . ' ' . str_replace('_', ' ', $unit) . ' · ' . $normalStart->format('d M Y') . ' – ' . $finish->format('d M Y'),
        ];
    }

    private function workdayEnd(Carbon $start, int $days): Carbon
    {
        $cursor = $start->copy();
        if (!$cursor->isWeekday()) {
            $cursor->next(Carbon::MONDAY);
        }
        if ($days > 1) {
            $cursor->addWeekdays($days - 1);
        }
        return $cursor;
    }
}
