<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLog;
use App\Models\OvertimeExcessRecord;
use App\Models\OvertimeRequest;
use Carbon\Carbon;

/**
 * Ghi nhận OT vượt mức pháp luật — tách khỏi tính lương.
 */
class OvertimeExcessService
{
    /**
     * @param  array<string, mixed>  $capCheck
     */
    public function syncFromCapCheck(OvertimeRequest $ot, array $capCheck): void
    {
        if ($capCheck['valid'] ?? true) {
            return;
        }

        $period = Carbon::parse($ot->work_date)->format('Y-m');
        $hours = (float) $ot->hours;

        if (! ($capCheck['daily_ok'] ?? true)) {
            $legal = max(0, OvertimeCapValidator::MAX_DAILY_HOURS - (float) ($capCheck['daily_used'] ?? 0));
            $this->upsertRecord($ot, $period, 'daily', $legal, $hours);
        }

        if (! ($capCheck['monthly_ok'] ?? true)) {
            $legal = max(0, OvertimeCapValidator::MAX_MONTHLY_HOURS - (float) ($capCheck['monthly_used'] ?? 0));
            $this->upsertRecord($ot, $period, 'monthly', $legal, $hours);
        }

        if (! ($capCheck['yearly_ok'] ?? true)) {
            $yearlyMax = (float) config('hr_vn.ot_yearly_max_hours', OvertimeCapValidator::MAX_YEARLY_HOURS);
            $legal = max(0, $yearlyMax - (float) ($capCheck['yearly_used'] ?? 0));
            $this->upsertRecord($ot, $period, 'yearly', $legal, $hours);
        }
    }

    private function upsertRecord(
        OvertimeRequest $ot,
        string $period,
        string $capType,
        float $legalHours,
        float $actualHours,
    ): void {
        $excess = max(0, round($actualHours - $legalHours, 2));
        if ($excess <= 0) {
            return;
        }

        OvertimeExcessRecord::updateOrCreate(
            [
                'overtime_request_id' => $ot->id,
                'cap_type' => $capType,
            ],
            [
                'company_id' => $ot->company_id,
                'employee_id' => $ot->employee_id,
                'period' => $period,
                'work_date' => $ot->work_date,
                'legal_hours' => $legalHours,
                'actual_hours' => $actualHours,
                'excess_hours' => $excess,
                'status' => 'pending',
                'exclude_from_payroll' => true,
                'notes' => match ($capType) {
                    'daily' => 'Vượt OT ngày theo Điều 107 BLLĐ',
                    'monthly' => 'Vượt OT tháng theo NĐ 145/2020',
                    default => 'Vượt OT năm theo Điều 107 BLLĐ',
                },
            ],
        );
    }

    public function payrollExcludedHours(int $employeeId, string $period): float
    {
        return (float) OvertimeExcessRecord::where('employee_id', $employeeId)
            ->where('period', $period)
            ->where('exclude_from_payroll', true)
            ->get()
            ->groupBy('overtime_request_id')
            ->sum(fn ($group) => (float) $group->max('excess_hours'));
    }

    /**
     * @return array{excess_hours: float, payroll_excluded_hours: float, records: int}
     */
    public function excessSummaryForPeriod(int $employeeId, string $period): array
    {
        $records = OvertimeExcessRecord::where('employee_id', $employeeId)
            ->where('period', $period)
            ->where('exclude_from_payroll', true)
            ->get();

        return [
            'excess_hours' => round((float) $records->sum('excess_hours'), 2),
            'payroll_excluded_hours' => $this->payrollExcludedHours($employeeId, $period),
            'records' => $records->count(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, OvertimeExcessRecord>
     */
    public function listForCompanyPeriod(int $companyId, string $period)
    {
        return OvertimeExcessRecord::with('employee:id,employee_code,full_name')
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->orderByDesc('work_date')
            ->get();
    }

    /**
     * Kiểm tra tuân thủ làm thêm giờ hàng tuần và ngày nghỉ hàng tuần (BLLĐ 2019).
     */
    public function checkWeeklyCompliance(int $employeeId, string $period): void
    {
        // Xóa các bản ghi weekly cũ của nhân viên trong kỳ
        OvertimeExcessRecord::where('employee_id', $employeeId)
            ->where('period', $period)
            ->whereIn('cap_type', ['weekly_day_off', 'weekly_66h'])
            ->delete();

        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Lấy từ Thứ Hai đầu tiên có thể chạm đến tháng hiện tại đến Chủ Nhật cuối cùng
        $firstMonday = $start->copy()->startOfWeek(Carbon::MONDAY);
        $lastSunday = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $currentMonday = $firstMonday->copy();
        while ($currentMonday->lt($lastSunday)) {
            $weekStart = $currentMonday->copy();
            $weekEnd = $currentMonday->copy()->endOfWeek(Carbon::SUNDAY);

            // 1. Kiểm tra ngày nghỉ hàng tuần (ít nhất 1 ngày off)
            $logs = AttendanceLog::where('employee_id', $employeeId)
                ->whereBetween('work_date', [$weekStart->toDateString() . ' 00:00:00', $weekEnd->toDateString() . ' 23:59:59'])
                ->get();

            $daysWorked = $logs->whereNotNull('check_in_at')
                ->map(fn ($log) => $log->work_date instanceof Carbon ? $log->work_date->toDateString() : (string) $log->work_date)
                ->unique()
                ->count();
            
            // Một tuần có 7 ngày. Nếu đi làm đủ 7 ngày thì vi phạm
            if ($daysWorked >= 7) {
                // Saturday of this week
                $saturday = $weekStart->copy()->addDays(5);

                $satOt = OvertimeRequest::where('employee_id', $employeeId)
                    ->whereBetween('work_date', [$saturday->toDateString() . ' 00:00:00', $saturday->toDateString() . ' 23:59:59'])
                    ->where('status', 'approved')
                    ->first();

                if ($satOt) {
                    $this->createWeeklyExcessRecord(
                        $satOt,
                        $period,
                        'weekly_day_off',
                        (float) $satOt->hours,
                        'Không có ngày nghỉ trong tuần (Loại bỏ OT Thứ Bảy)'
                    );
                }
            }

            // 2. Kiểm tra giới hạn 66 giờ làm việc + OT trong tuần
            $totalWorkHours = (float) $logs->sum('work_hours');

            $otRequests = OvertimeRequest::where('employee_id', $employeeId)
                ->where('status', 'approved')
                ->whereBetween('work_date', [$weekStart->toDateString() . ' 00:00:00', $weekEnd->toDateString() . ' 23:59:59'])
                ->get();

            $totalOtHours = (float) $otRequests->sum('hours');
            $totalWeekHours = $totalWorkHours + $totalOtHours;

            if ($totalWeekHours > 66.0) {
                $excess = $totalWeekHours - 66.0;

                // Loại bỏ OT ngày thường (weekday) trước
                $weekdayOts = $otRequests->where('ot_type', 'weekday')->sortByDesc('work_date');

                foreach ($weekdayOts as $ot) {
                    if ($excess <= 0) {
                        break;
                    }
                    // Nếu OT này đã bị loại bỏ một phần hoặc toàn bộ do check weekly_day_off ở trên,
                    // cần lấy số giờ còn lại chưa bị loại bỏ
                    $alreadyExcluded = OvertimeExcessRecord::where('overtime_request_id', $ot->id)
                        ->where('cap_type', 'weekly_day_off')
                        ->sum('excess_hours');
                    
                    $availableOtHours = max(0.0, (float) $ot->hours - $alreadyExcluded);
                    if ($availableOtHours <= 0) {
                        continue;
                    }

                    $disqualified = min($excess, $availableOtHours);

                    $this->createWeeklyExcessRecord(
                        $ot,
                        $period,
                        'weekly_66h',
                        $disqualified,
                        'Tổng giờ tuần vượt 66h (Loại bỏ OT ngày thường)'
                    );
                    $excess -= $disqualified;
                }
            }

            $currentMonday->addWeek();
        }
    }

    private function createWeeklyExcessRecord(
        OvertimeRequest $ot,
        string $period,
        string $capType,
        float $excessHours,
        string $notes
    ): void {
        OvertimeExcessRecord::updateOrCreate(
            [
                'overtime_request_id' => $ot->id,
                'cap_type' => $capType,
            ],
            [
                'company_id' => $ot->company_id,
                'employee_id' => $ot->employee_id,
                'period' => $period,
                'work_date' => $ot->work_date,
                'legal_hours' => max(0, (float) $ot->hours - $excessHours),
                'actual_hours' => $ot->hours,
                'excess_hours' => $excessHours,
                'status' => 'pending',
                'exclude_from_payroll' => true,
                'notes' => $notes,
            ]
        );
    }
}
