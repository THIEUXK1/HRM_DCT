<?php

namespace App\Services\Attendance;

use App\Models\LeaveType;
use Carbon\Carbon;

/**
 * Tính số ngày nghỉ theo loại:
 * - workday: ngày làm chuẩn (T2–T7, trừ ngày lễ) — phép năm, nghỉ có lương…
 * - calendar: ngày dương lịch (kể cả CN, lễ) — thai sản, ốm BHXH (Điều 139–141 BLLĐ 2019).
 */
class LeaveDurationCalculator
{
    public function between(Carbon $start, Carbon $end, LeaveType|string $leaveType, ?array $holidays = null): float
    {
        $mode = $this->resolveMode($leaveType);
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        if ($mode === 'calendar') {
            return (float) ($start->diffInDays($end) + 1);
        }

        $holidays ??= VietnamHolidayService::forYear($start->year);
        if ($end->year !== $start->year) {
            $holidays = array_merge(
                $holidays,
                VietnamHolidayService::forYear($end->year),
            );
        }

        $count = 0.0;
        $cursor = $start->copy();
        while ($cursor <= $end) {
            if ($this->isWorkday($cursor, $holidays)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    public function countsOnCalendarDays(LeaveType|string $leaveType): bool
    {
        return $this->resolveMode($leaveType) === 'calendar';
    }

    private function resolveMode(LeaveType|string $leaveType): string
    {
        if ($leaveType instanceof LeaveType) {
            return $leaveType->day_count_mode ?? 'workday';
        }

        return $leaveType === 'calendar' ? 'calendar' : 'workday';
    }

    public function isWorkday(Carbon $date, array $holidays): bool
    {
        if ($date->isSunday()) {
            return false;
        }

        return ! array_key_exists($date->format('Y-m-d'), $holidays);
    }
}
