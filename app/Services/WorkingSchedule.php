<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/** Weekdays-only schedule, with times from the template or a default 09:00–17:00. */
class WorkingSchedule
{
    public function hours(?string $range): array
    {
        if (preg_match('/(\d{1,2}:\d{2})\s*(AM|PM)?\s*[-–]\s*(\d{1,2}:\d{2})\s*(AM|PM)?/i', (string) $range, $m)) {
            $convert = function ($value, $period) {
                if (!$period) return $value;
                $date = \DateTimeImmutable::createFromFormat('!h:i A', $value . ' ' . strtoupper($period));
                return $date ? $date->format('H:i') : $value;
            };
            $from = $convert($m[1], $m[2] ?? '');
            $to = $convert($m[3], $m[4] ?? '');
            if (strcmp($from, $to) < 0) return [$from, $to];
        }
        return ['09:00', '17:00'];
    }

    public function minutesBetween(CarbonInterface $from, CarbonInterface $to, ?string $hours = null): int
    {
        if ($to->lessThanOrEqualTo($from)) return 0;
        [$open, $close] = $this->hours($hours);
        $cursor = Carbon::instance($from)->startOfDay();
        $end = Carbon::instance($to);
        $minutes = 0;
        // Bounded by the entered dates, not by the duration of the project.
        while ($cursor->lessThanOrEqualTo($end)) {
            if ($cursor->isWeekday()) {
                $a = $cursor->copy()->setTimeFromTimeString($open);
                $b = $cursor->copy()->setTimeFromTimeString($close);
                $start = $a->greaterThan($from) ? $a : Carbon::instance($from);
                $finish = $b->lessThan($end) ? $b : $end;
                if ($finish->greaterThan($start)) $minutes += (int) $start->diffInMinutes($finish);
            }
            $cursor->addDay();
        }
        return $minutes;
    }

    public function addMinutes(CarbonInterface $origin, int $minutes, ?string $hours = null): Carbon
    {
        [$open, $close] = $this->hours($hours);
        $cursor = Carbon::instance($origin);
        $minutes = max(0, $minutes);
        for ($days = 0; $days < 20000; $days++) {
            if (!$cursor->isWeekday()) {
                $cursor = $cursor->copy()->next(Carbon::MONDAY)->setTimeFromTimeString($open);
                continue;
            }
            $start = $cursor->copy()->setTimeFromTimeString($open);
            $finish = $cursor->copy()->setTimeFromTimeString($close);
            if ($cursor->lessThan($start)) $cursor = $start;
            if ($cursor->greaterThanOrEqualTo($finish)) {
                $cursor = $cursor->addDay()->setTimeFromTimeString($open);
                continue;
            }
            $available = (int) $cursor->diffInMinutes($finish);
            if ($minutes <= $available) return $cursor->addMinutes($minutes);
            $minutes -= $available;
            $cursor = $cursor->addDay()->setTimeFromTimeString($open);
        }
        throw new \RuntimeException('Task forecast exceeds supported calendar range.');
    }
}
