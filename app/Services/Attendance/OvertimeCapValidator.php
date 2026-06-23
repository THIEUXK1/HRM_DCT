<?php

namespace App\Services\Attendance;

use App\Models\OvertimeRequest;
use Carbon\Carbon;

/**
 * Kiểm tra giới hạn tăng ca theo Điều 107 Bộ luật Lao động 2019
 * và Nghị định 145/2020/NĐ-CP.
 *
 * Giới hạn:
 *  - Tối đa 4 giờ/ngày
 *  - Tối đa 40 giờ/tháng
 *  - Tối đa 200 giờ/năm (300 giờ với ngành được phê duyệt — config)
 */
class OvertimeCapValidator
{
    /**
     * Giờ OT tối đa mỗi ngày (Điều 107 khoản 2a).
     */
    public const MAX_DAILY_HOURS = 4.0;

    /**
     * Giờ OT tối đa mỗi tháng (Điều 107 khoản 2b / NĐ 145/2020).
     */
    public const MAX_MONTHLY_HOURS = 40.0;

    /**
     * Giờ OT tối đa mỗi năm (Điều 107 khoản 2c — tiêu chuẩn).
     */
    public const MAX_YEARLY_HOURS = 200.0;

    /**
     * Giờ OT tối đa mỗi năm (ngành đặc biệt theo khoản 3 Điều 107).
     */
    public const MAX_YEARLY_HOURS_SPECIAL = 300.0;

    /**
     * Kiểm tra yêu cầu OT mới (chưa lưu vào DB) có vượt giới hạn không.
     *
     * @return array{valid: bool, daily_ok: bool, monthly_ok: bool, yearly_ok: bool,
     *               daily_used: float, monthly_used: float, yearly_used: float,
     *               warnings: string[]}
     */
    public static function validate(int $employeeId, string $workDate, float $requestedHours): array
    {
        $date     = Carbon::parse($workDate);
        $period   = $date->format('Y-m');
        $year     = $date->year;

        // Giờ OT đã duyệt trong ngày (không kể yêu cầu đang xử lý)
        $dailyUsed = (float) OvertimeRequest::where('employee_id', $employeeId)
            ->where('work_date', $workDate)
            ->whereIn('status', ['approved', 'pending'])
            ->sum('hours');

        // Giờ OT đã duyệt trong tháng
        $monthlyUsed = (float) OvertimeRequest::where('employee_id', $employeeId)
            ->whereYear('work_date', $date->year)
            ->whereMonth('work_date', $date->month)
            ->whereIn('status', ['approved', 'pending'])
            ->sum('hours');

        // Giờ OT đã duyệt trong năm
        $yearlyUsed = (float) OvertimeRequest::where('employee_id', $employeeId)
            ->whereYear('work_date', $year)
            ->whereIn('status', ['approved', 'pending'])
            ->sum('hours');

        $dailyAfter   = $dailyUsed + $requestedHours;
        $monthlyAfter = $monthlyUsed + $requestedHours;
        $yearlyAfter  = $yearlyUsed + $requestedHours;

        $yearlyMax = self::yearlyMax();

        $dailyOk   = $dailyAfter   <= self::MAX_DAILY_HOURS;
        $monthlyOk = $monthlyAfter <= self::MAX_MONTHLY_HOURS;
        $yearlyOk  = $yearlyAfter  <= $yearlyMax;

        $warnings = [];
        if (! $dailyOk) {
            $warnings[] = sprintf(
                'Vượt giới hạn OT ngày: đã có %.1fh + %.1fh = %.1fh (tối đa %.0fh/ngày — Điều 107 BLLĐ)',
                $dailyUsed, $requestedHours, $dailyAfter, self::MAX_DAILY_HOURS
            );
        }
        if (! $monthlyOk) {
            $warnings[] = sprintf(
                'Vượt giới hạn OT tháng %s: đã có %.1fh + %.1fh = %.1fh (tối đa %.0fh/tháng — NĐ 145/2020)',
                $period, $monthlyUsed, $requestedHours, $monthlyAfter, self::MAX_MONTHLY_HOURS
            );
        }
        if (! $yearlyOk) {
            $warnings[] = sprintf(
                'Vượt giới hạn OT năm %d: đã có %.1fh + %.1fh = %.1fh (tối đa %.0fh/năm — Điều 107 BLLĐ)',
                $year, $yearlyUsed, $requestedHours, $yearlyAfter, $yearlyMax
            );
        }

        return [
            'valid'        => $dailyOk && $monthlyOk && $yearlyOk,
            'daily_ok'     => $dailyOk,
            'monthly_ok'   => $monthlyOk,
            'yearly_ok'    => $yearlyOk,
            'daily_used'   => $dailyUsed,
            'monthly_used' => $monthlyUsed,
            'yearly_used'  => $yearlyUsed,
            'daily_after'  => $dailyAfter,
            'monthly_after'=> $monthlyAfter,
            'yearly_after' => $yearlyAfter,
            'warnings'     => $warnings,
        ];
    }

    /**
     * Tóm tắt tình trạng OT của nhân viên trong tháng/năm hiện tại.
     */
    public static function summary(int $employeeId, string $period): array
    {
        $date  = Carbon::createFromFormat('Y-m', $period);
        $year  = $date->year;

        $monthly = (float) OvertimeRequest::where('employee_id', $employeeId)
            ->whereYear('work_date', $date->year)
            ->whereMonth('work_date', $date->month)
            ->where('status', 'approved')
            ->sum('hours');

        $yearly = (float) OvertimeRequest::where('employee_id', $employeeId)
            ->whereYear('work_date', $year)
            ->where('status', 'approved')
            ->sum('hours');

        $yearlyMax = self::yearlyMax();

        return [
            'period'           => $period,
            'monthly_used'     => $monthly,
            'monthly_max'      => self::MAX_MONTHLY_HOURS,
            'monthly_remaining'=> max(0, self::MAX_MONTHLY_HOURS - $monthly),
            'monthly_exceeded' => $monthly > self::MAX_MONTHLY_HOURS,
            'yearly_used'      => $yearly,
            'yearly_max'       => $yearlyMax,
            'yearly_remaining' => max(0, $yearlyMax - $yearly),
            'yearly_exceeded'  => $yearly > $yearlyMax,
        ];
    }

    private static function yearlyMax(): float
    {
        return (float) config('hr_vn.ot_yearly_max_hours', self::MAX_YEARLY_HOURS);
    }
}
