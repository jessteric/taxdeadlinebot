<?php

namespace App\Services\Deadline;

use App\Models\HolidayCalendar;
use DateTimeInterface;

class HolidayService
{
    public function isHolidayOrWeekend(DateTimeInterface $date, ?string $calendarCode): bool
    {
        $dow = (int)$date->format('N'); // 6,7 = weekend
        if ($dow >= 6) {
            return true;
        }

        if (!$calendarCode) {
            return false;
        }

        $calendar = HolidayCalendar::find($calendarCode);
        if (!$calendar) {
            return false;
        }

        return in_array($date->format('Y-m-d'), $calendar->data ?? [], true);
    }
}
