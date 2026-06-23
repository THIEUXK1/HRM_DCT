<?php

namespace Tests\Unit;

use App\Models\LeaveType;
use App\Services\Attendance\LeaveDurationCalculator;
use Carbon\Carbon;
use Tests\TestCase;

class LeaveDurationCalculatorTest extends TestCase
{
    public function test_maternity_leave_uses_calendar_days_including_weekends(): void
    {
        $type = new LeaveType([
            'code' => 'TS',
            'day_count_mode' => 'calendar',
        ]);

        $calculator = new LeaveDurationCalculator();

        // 01/03–07/03/2026 = 7 ngày (có CN 01/03)
        $days = $calculator->between(
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
            $type,
        );

        $this->assertSame(7.0, $days);
    }

    public function test_six_month_maternity_span_counts_all_calendar_days(): void
    {
        $type = new LeaveType([
            'code' => 'TS',
            'day_count_mode' => 'calendar',
        ]);

        $calculator = new LeaveDurationCalculator();
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-06-30');

        $days = $calculator->between($start, $end, $type);

        $this->assertSame(181.0, $days);
    }

    public function test_annual_leave_excludes_sundays(): void
    {
        $type = new LeaveType([
            'code' => 'PHEP',
            'day_count_mode' => 'workday',
        ]);

        $calculator = new LeaveDurationCalculator();

        // 02/03–08/03/2026: T2–CN, trừ CN 08/03 → 6 ngày
        $days = $calculator->between(
            Carbon::parse('2026-03-02'),
            Carbon::parse('2026-03-08'),
            $type,
        );

        $this->assertSame(6.0, $days);
    }
}
