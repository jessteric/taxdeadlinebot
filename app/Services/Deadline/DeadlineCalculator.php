<?php

namespace App\Services\Deadline;

use App\Services\Deadline\HolidayService;
use DateTimeImmutable;
use DateTimeZone;

final readonly class DeadlineCalculator
{
    public function __construct(
        private HolidayService $holidays,
        private DateTimeZone   $tz
    ) {}

    public function dueForPeriod(
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        array $rule
    ): DateTimeImmutable {
        $date = match ($rule['rrule']['anchor'] ?? 'period_end_plus') {
            'period_end_plus' => $periodEnd->setTimezone($this->tz)
                ->modify('+' . (int)($rule['rrule']['anchorDays'] ?? 0) . ' days'),
            'month_end_plus' => (new DateTimeImmutable('last day of ' . $periodEnd->format('Y-m'), $this->tz))
                ->modify('+' . (int)($rule['rrule']['anchorDays'] ?? 0) . ' days'),
            default => $periodEnd->setTimezone($this->tz),
        };

        // фиксируем день месяца, если указан
        if (!empty($rule['due_day'])) {
            $date = $date->setDate(
                (int)$date->format('Y'),
                (int)$date->format('m'),
                (int)$rule['due_day']
            );
        }

        // сдвиг на рабочий день
        $date = $this->applyBusinessShift($date, $rule['due_shift'] ?? 'next_business', $rule['holiday_calendar_code'] ?? null);

        // grace period
        if (!empty($rule['grace_days'])) {
            $date = $date->modify('+' . (int)$rule['grace_days'] . ' days');
            $date = $this->applyBusinessShift($date, 'next_business', $rule['holiday_calendar_code'] ?? null);
        }

        return $date->setTime(9, 0);
    }

    private function applyBusinessShift(DateTimeImmutable $d, string $mode, ?string $calendar): DateTimeImmutable
    {
        $cur = $d;
        if ($mode === 'none') return $cur;

        while ($this->holidays->isHolidayOrWeekend($cur, $calendar)) {
            $cur = $mode === 'prev_business'
                ? $cur->modify('-1 day')
                : $cur->modify('+1 day');
        }

        return $cur;
    }
}
