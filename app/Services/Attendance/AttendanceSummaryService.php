<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLog;
use App\Models\AttendancePeriodLock;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tính bảng công tháng theo Bộ luật Lao động 2019 (VN):
 *
 *  - Điều 105: giờ làm tối đa 8h/ngày, 48h/tuần
 *  - Điều 106: làm đêm 22:00–06:00 (+30%)
 *  - Điều 107: OT phân loại ngày thường/cuối tuần/lễ (150%/200%/300%), cap 4h/ngày, 40h/tháng
 *  - Điều 112: ngày nghỉ lễ (11 ngày/năm)
 *  - Điều 24–27: phân biệt công thử việc / công chính thức theo hợp đồng
 */
class AttendanceSummaryService
{
    /** Giờ bắt đầu làm đêm (22:00) */
    private const NIGHT_START = 22;

    /** Giờ kết thúc làm đêm (06:00) */
    private const NIGHT_END = 6;

    public function __construct(
        private readonly EmploymentPhaseResolver $phaseResolver,
        private readonly LeaveDayCalculator $leaveDayCalculator,
        private readonly AttendanceCorrectionService $correctionService,
        private readonly DiligenceBonusEvaluator $diligenceEvaluator,
        private readonly AttendanceBreakdownBuilder $breakdownBuilder,
        private readonly WorkScheduleComplianceService $scheduleCompliance,
        private readonly AttendanceWorkDaysPhaseSplitter $workDaysSplitter,
        private readonly OvertimeExcessService $otExcess,
    ) {}

    public function buildForPeriod(int $companyId, string $period): int
    {
        $this->assertPeriodNotLocked($companyId, $period);
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $holidays = VietnamHolidayService::forYear($start->year, $companyId);
        $standardDays = VietnamHolidayService::standardWorkDays($period, false, $companyId);
        $count = 0;

        // Lấy ca làm mặc định của công ty (nếu có)
        $defaultShift = WorkShift::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        $employees = Employee::where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        foreach ($employees as $employee) {
            $this->buildForEmployee($employee, $companyId, $period, $start, $end, $holidays, $standardDays, $defaultShift);
            $count++;
        }

        return $count;
    }

    /** Tổng hợp lại công một NV trong tháng (sau duyệt bù thẻ). */
    public function rebuildEmployeePeriod(int $employeeId, int $companyId, string $period): void
    {
        $employee = Employee::where('company_id', $companyId)->find($employeeId);
        if (! $employee) {
            return;
        }

        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $holidays = VietnamHolidayService::forYear($start->year, $companyId);
        $standardDays = VietnamHolidayService::standardWorkDays($period, false, $companyId);
        $defaultShift = WorkShift::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        $this->buildForEmployee($employee, $companyId, $period, $start, $end, $holidays, $standardDays, $defaultShift);
    }

    public function lockPeriod(int $companyId, string $period): int
    {
        return DB::transaction(function () use ($companyId, $period) {
            $this->buildForPeriod($companyId, $period);

            return AttendanceSummary::where('company_id', $companyId)
                ->where('period', $period)
                ->update([
                    'is_locked' => true,
                    'locked_at' => now(),
                ]);
        });
    }

    // ── Private: per-employee ─────────────────────────────────────────────────

    private function buildForEmployee(
        Employee $employee,
        int $companyId,
        string $period,
        Carbon $start,
        Carbon $end,
        array $holidays,
        int $standardDays,
        ?WorkShift $defaultShift,
    ): void {
        // Kiểm tra tuân thủ OT hàng tuần trước khi tổng hợp công
        $this->otExcess->checkWeeklyCompliance($employee->id, $period);

        // Xác định ngày kết thúc thử việc từ hợp đồng đang hiệu lực
        $probationEndDate = $this->phaseResolver->probationEndInPeriod($employee->id, $start, $end);

        // Cập nhật employment_phase trên từng log
        $logs = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy('work_date');

        foreach ($logs as $log) {
            $this->enrichLog($log, $holidays, $probationEndDate, $defaultShift);
        }

        // Đọc lại sau enrichLog để đếm phase chính xác
        $logs = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        // Lấy OT đã duyệt trong tháng
        $otRequests = OvertimeRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        // Công phân loại ngày — từ AttendanceLog sau enrichLog
        $workWeekdayDays         = 0.0;
        $workWeekendDays         = 0.0;
        $workHolidayDays         = 0.0;
        $workNightWeekdayHours   = 0.0;
        $workNightWeekendHours   = 0.0;
        $workNightHolidayHours   = 0.0;

        foreach ($logs as $log) {
            if (! $log->check_in_at) {
                continue;
            }
            $nightH = (float) ($log->night_hours ?? 0);
            if ($log->is_holiday) {
                $workHolidayDays++;
                $workNightHolidayHours += $nightH;
            } elseif ($log->is_weekend) {
                $workWeekendDays++;
                $workNightWeekendHours += $nightH;
            } else {
                $workWeekdayDays++;
                $workNightWeekdayHours += $nightH;
            }
        }

        // Tính tổng các loại OT — Điều 107 × Điều 106 BLLĐ 2019
        // N1 (200%): TC đêm không có TC ngày trước = 150% + 30% + 20%×100%
        // N2 (210%): TC đêm sau khi đã TC ngày = 150% + 30% + 20%×150%
        $otWeekday           = 0.0;
        $otWeekend           = 0.0;
        $otHoliday           = 0.0;
        $otNight             = 0.0;
        $otNightWeekday      = 0.0;
        $otNightWeekdayN2    = 0.0; // N2 riêng; N1 = otNightWeekday - otNightWeekdayN2
        $otNightWeekend      = 0.0;
        $otNightHoliday      = 0.0;

        foreach ($otRequests as $ot) {
            $otType = $ot->ot_type ?? 'weekday';
            $hours  = (float) $ot->hours;
            $nightH = (float) ($ot->night_hours ?? 0);
            $dayH   = max(0.0, $hours - $nightH);

            if ($otType === 'weekend') {
                $otWeekend      += $hours;
                $otNightWeekend += $nightH;
            } elseif ($otType === 'holiday') {
                $otHoliday      += $hours;
                $otNightHoliday += $nightH;
            } else {
                $otWeekday += $hours;
                $otNightWeekday += $nightH;
                // N2 khi cùng một OvertimeRequest có cả TC ngày lẫn TC đêm
                if ($dayH > 0.0 && $nightH > 0.0) {
                    $otNightWeekdayN2 += $nightH;
                }
            }
            $otNight += $nightH;
        }

        $otTotal = $otWeekday + $otWeekend + $otHoliday;

        // Phép đã duyệt — tách có lương / không lương
        $leaveStats = $this->leaveDayCalculator->summarizeForEmployee(
            $employee,
            $start,
            $end,
            $holidays,
            $probationEndDate,
        );
        $leaveDays = $leaveStats['total_leave_days'];
        $paidLeaveDays = $leaveStats['paid_leave_days'];
        $unpaidLeaveDays = $leaveStats['unpaid_leave_days'];
        $bhxhLeaveDays = $leaveStats['bhxh_leave_days'];

        // Tổng hợp từ logs
        [$workDays, $probationDays, $officialDays] = $this->countWorkDaysByPhase(
            $logs,
            $employee,
            $probationEndDate,
            $period,
        );
        $actualHours    = (float) $logs->sum('work_hours');
        $lateMinutes    = (float) $logs->sum('late_minutes');
        $lateCount      = $logs->filter(fn ($l) => $l->late_minutes > 0)->count();
        $earlyCount     = $logs->filter(fn ($l) => $l->early_minutes > 0)->count();
        $nightHours     = (float) $logs->sum('night_hours');

        // Ngày vắng không phép = chuẩn − đi làm − nghỉ có đơn (cả có lương & không lương)
        $absentDays = max(0, $standardDays - $workDays - $leaveDays);

        // Cờ vượt OT tháng
        $otMonthlyCapExceeded = $otTotal > OvertimeCapValidator::MAX_MONTHLY_HOURS;

        $correctionCounts = $this->correctionService->countsForEmployeePeriod($employee->id, $start, $end);
        $attendanceRate = round(($workDays / max(1, $standardDays)) * 100, 1);

        $leaveByType = $this->leaveDayCalculator->summarizeByLeaveType(
            $employee,
            $start,
            $end,
            $holidays,
            $probationEndDate,
        );
        $leaveByTypeByPhase = $this->leaveDayCalculator->summarizeByLeaveTypeByPhase(
            $employee,
            $start,
            $end,
            $holidays,
            $probationEndDate,
        );

        $attendanceBreakdown = $this->breakdownBuilder->build(
            $employee,
            $start,
            $end,
            $period,
            $holidays,
            $standardDays,
            $otRequests,
            $leaveByType,
            (float) $workDays,
            (float) $paidLeaveDays,
            (float) $absentDays,
            (float) $probationDays,
            (float) $officialDays,
            $probationEndDate,
            $leaveStats,
            $leaveByTypeByPhase,
            $workNightWeekdayHours,
            $workNightWeekendHours,
            $workNightHolidayHours,
        );

        $payload = [
            'company_id'             => $companyId,
            'work_days'              => $workDays,
            'probation_work_days'    => $probationDays,
            'official_work_days'     => $officialDays,
            'standard_work_days'     => $standardDays,
            'leave_days'             => $leaveDays,
            'paid_leave_days'        => $paidLeaveDays,
            'unpaid_leave_days'      => $unpaidLeaveDays,
            'bhxh_leave_days'        => $bhxhLeaveDays,
            'probation_paid_leave_days' => $leaveStats['probation_paid_leave_days'],
            'official_paid_leave_days'  => $leaveStats['official_paid_leave_days'],
            'probation_unpaid_leave_days' => $leaveStats['probation_unpaid_leave_days'],
            'official_unpaid_leave_days'  => $leaveStats['official_unpaid_leave_days'],
            'probation_bhxh_leave_days' => $leaveStats['probation_bhxh_leave_days'],
            'official_bhxh_leave_days'  => $leaveStats['official_bhxh_leave_days'],
            'absent_days'            => $absentDays,
            'actual_work_hours'      => $actualHours,
            'standard_work_hours'    => $standardDays * 8,
            'work_weekday_days'          => round($workWeekdayDays, 2),
            'work_weekend_days'          => round($workWeekendDays, 2),
            'work_holiday_days'          => round($workHolidayDays, 2),
            'ot_hours'                   => $otTotal,
            'ot_weekday_hours'           => $otWeekday,
            'ot_weekend_hours'           => $otWeekend,
            'ot_holiday_hours'           => $otHoliday,
            'night_hours'                => $nightHours + $otNight,
            'work_night_weekday_hours'   => round($workNightWeekdayHours, 2),
            'work_night_weekend_hours'   => round($workNightWeekendHours, 2),
            'work_night_holiday_hours'   => round($workNightHolidayHours, 2),
            'ot_night_weekday_hours'     => round($otNightWeekday, 2),
            'ot_night_weekday_n2_hours'  => round($otNightWeekdayN2, 2),
            'ot_night_weekend_hours'     => round($otNightWeekend, 2),
            'ot_night_holiday_hours'     => round($otNightHoliday, 2),
            'late_minutes'           => $lateMinutes,
            'late_count'             => $lateCount,
            'early_count'            => $earlyCount,
            'ot_monthly_cap_exceeded'=> $otMonthlyCapExceeded,
            'forgot_punch_count'     => $correctionCounts['forgot_punch_count'],
            'correction_approved_count' => $correctionCounts['correction_approved_count'],
            'is_locked'              => false,
            'attendance_breakdown'   => $attendanceBreakdown,
        ];

        $preview = new AttendanceSummary($payload + [
            'employee_id' => $employee->id,
            'period' => $period,
            'company_id' => $companyId,
        ]);
        $diligence = $this->diligenceEvaluator->evaluate($preview, $attendanceRate);
        $payload['diligence_bonus_eligible'] = $diligence['eligible'];
        $payload['diligence_bonus_amount'] = $diligence['bonus_amount'];
        $payload['attendance_breakdown']['diligence'] = [
            'eligible' => $diligence['eligible'],
            'total' => $diligence['bonus_amount'],
            'probation_pay' => $diligence['diligence_probation_pay'],
            'official_pay' => $diligence['diligence_official_pay'],
            'phase_mode' => $diligence['diligence_phase_mode'],
            'has_phase_split' => $diligence['has_phase_split'],
        ];
        $payload['compliance_alerts'] = $this->scheduleCompliance->scanEmployeePeriod($employee, $period);

        AttendanceSummary::updateOrCreate(
            ['employee_id' => $employee->id, 'period' => $period],
            $payload,
        );
    }

    /**
     * Làm giàu thông tin AttendanceLog: giờ làm, trễ, sớm, đêm, phase, lễ, cuối tuần.
     */
    private function enrichLog(
        AttendanceLog $log,
        array $holidays,
        ?Carbon $probationEndDate,
        ?WorkShift $shift,
    ): void {
        $workDate = Carbon::parse($log->work_date);

        $isHoliday   = array_key_exists($workDate->format('Y-m-d'), $holidays);
        $isWeekend   = $workDate->isWeekend(); // T7 hoặc CN
        $holidayName = $holidays[$workDate->format('Y-m-d')] ?? null;

        // Giai đoạn hợp đồng
        $phase = $this->phaseResolver->phaseOnDate($log->employee_id, $workDate, $probationEndDate) ?? 'official';

        // Tính giờ làm, trễ, sớm, đêm từ check_in / check_out
        $workHours    = 0.0;
        $lateMinutes  = 0.0;
        $earlyMinutes = 0.0;
        $nightHours   = 0.0;

        if ($log->check_in_at && $log->check_out_at) {
            $checkIn  = Carbon::parse($log->check_in_at);
            $checkOut = Carbon::parse($log->check_out_at);

            $breakMinutes = $shift?->break_minutes ?? 60;
            // abs() để an toàn với cả Carbon 2 (không dấu) và Carbon 3 (có dấu).
            $rawMinutes   = max(0, abs($checkOut->diffInMinutes($checkIn)) - $breakMinutes);
            $workHours    = round($rawMinutes / 60, 2);

            // Trễ / sớm: so sánh check_in với shift start_time
            if ($shift) {
                $shiftStart = Carbon::parse($workDate->format('Y-m-d').' '.$shift->start_time);
                $shiftEnd = Carbon::parse($workDate->format('Y-m-d').' '.$shift->end_time);

                if ($shift->crosses_midnight || $shift->end_time < $shift->start_time) {
                    $shiftEnd->addDay();
                }

                if ($checkIn->gt($shiftStart)) {
                    $lateMinutes = round(abs($checkIn->diffInMinutes($shiftStart)), 2);
                }
                if ($checkOut->lt($shiftEnd)) {
                    $earlyMinutes = round(abs($shiftEnd->diffInMinutes($checkOut)), 2);
                }
            }

            // Giờ làm đêm (22:00–06:00 hôm sau)
            $nightHours = $this->calculateNightHours($checkIn, $checkOut);
        } elseif ($log->check_in_at && ! $log->check_out_at) {
            // Chỉ có check_in, không check_out — có thể quên quẹt ra
            $workHours = 0.0;
        }

        // Lưu lại (chỉ update nếu có thay đổi để tránh dirty writes không cần thiết)
        $dirty = [
            'work_hours'       => $workHours,
            'late_minutes'     => $lateMinutes,
            'early_minutes'    => $earlyMinutes,
            'night_hours'      => $nightHours,
            'is_weekend'       => $isWeekend,
            'is_holiday'       => $isHoliday,
            'holiday_name'     => $holidayName,
            'employment_phase' => $phase,
        ];

        $log->update($dirty);
    }

    /**
     * Tính số giờ làm ban đêm (22:00–06:00) trong ca làm đó.
     * Điều 106 BLLĐ 2019.
     */
    private function calculateNightHours(Carbon $checkIn, Carbon $checkOut): float
    {
        $nightMinutes = 0;

        $current = $checkIn->copy();
        while ($current->lt($checkOut)) {
            $hour = (int) $current->format('H');
            if ($hour >= self::NIGHT_START || $hour < self::NIGHT_END) {
                $nightMinutes++;
            }
            $current->addMinute();
        }

        return round($nightMinutes / 60, 2);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AttendanceLog>  $logs
     * @return array{0: float, 1: float, 2: float} work, probation, official
     */
    private function countWorkDaysByPhase(
        $logs,
        Employee $employee,
        ?Carbon $probationEndDate,
        string $period,
    ): array {
        $workDays = 0.0;
        $probationDays = 0.0;
        $officialDays = 0.0;

        foreach ($logs as $log) {
            if (! $log->check_in_at) {
                continue;
            }

            $workDays++;
            $phase = $log->employment_phase
                ?? $this->phaseResolver->phaseOnDate(
                    $employee,
                    Carbon::parse($log->work_date),
                    $probationEndDate,
                );

            if ($phase === 'probation') {
                $probationDays++;
            } elseif ($phase === 'official') {
                $officialDays++;
            }
        }

        $phases = $this->phaseResolver->phasesInPeriod($employee, $period);
        if (count($phases) > 1 && $workDays > 0 && abs(($probationDays + $officialDays) - $workDays) > 0.01) {
            $split = $this->workDaysSplitter->splitWorkDays($employee, $period, $workDays);
            $probationDays = $split['probation_work_days'];
            $officialDays = $split['official_work_days'];
        }

        return [$workDays, $probationDays, $officialDays];
    }

    private function assertPeriodNotLocked(int $companyId, string $period): void
    {
        if (AttendancePeriodLock::where('company_id', $companyId)
            ->where('period', $period)
            ->whereNull('unlocked_at')
            ->exists()) {
            throw new \RuntimeException("Kỳ công {$period} đã khóa. Chỉ admin mới được mở khóa.");
        }
    }
}
