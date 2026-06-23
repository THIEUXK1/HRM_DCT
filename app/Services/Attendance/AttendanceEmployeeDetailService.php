<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLog;
use App\Models\AttendancePunch;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Bảng công chi tiết theo nhân viên — giờ chấm, vị trí, lịch sử punch từng ngày.
 */
class AttendanceEmployeeDetailService
{
    public function __construct(
        private readonly AttendanceTimesheetService $timesheet,
        private readonly EmploymentPhaseResolver $phaseResolver,
    ) {}

    public function detail(int $companyId, int $employeeId, string $period): array
    {
        $employee = Employee::with('department:id,name')
            ->where('company_id', $companyId)
            ->where('id', $employeeId)
            ->first();

        if (! $employee) {
            throw new ModelNotFoundException('Không tìm thấy nhân viên.');
        }

        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $timesheet = $this->timesheet->dailyTimesheet($companyId, $period);
        $row = collect($timesheet['employees'] ?? [])
            ->firstWhere('employee_id', $employeeId);

        $logs = AttendanceLog::with(['checkInZone:id,code,name,address_note', 'checkOutZone:id,code,name,address_note', 'device:id,code,name'])
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceLog $log) => $log->work_date->format('Y-m-d'));

        $punches = AttendancePunch::with(['zone:id,code,name,address_note', 'device:id,code,name'])
            ->where('employee_id', $employeeId)
            ->whereBetween('punched_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('punched_at')
            ->get()
            ->groupBy(fn (AttendancePunch $p) => Carbon::parse($p->punched_at)->format('Y-m-d'));

        $summary = AttendanceSummary::where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('period', $period)
            ->first();

        $probationEnd = $this->phaseResolver->probationEndInPeriod($employee, $start, $end);
        $dailyRows = [];

        foreach ($timesheet['days'] ?? [] as $day) {
            $date = $day['date'];
            $log = $logs[$date] ?? null;
            $cell = $row['cells'][$date] ?? null;
            $dayPunches = ($punches[$date] ?? collect())->values();

            $dailyRows[] = [
                'date' => $date,
                'weekday_label' => $day['weekday_label'],
                'is_holiday' => $day['is_holiday'],
                'holiday_name' => $day['holiday_name'],
                'is_weekend' => $day['is_weekend'],
                'symbol' => $cell['symbol'] ?? '—',
                'status' => $cell['status'] ?? 'off',
                'status_label' => $cell['label'] ?? '',
                'employment_phase' => $cell['employment_phase'] ?? null,
                'employment_phase_label' => $this->phaseLabel($cell['employment_phase'] ?? null),
                'check_in_at' => $log?->check_in_at?->format('H:i'),
                'check_out_at' => $log?->check_out_at?->format('H:i'),
                'work_hours' => $log?->work_hours,
                'late_minutes' => $log?->late_minutes,
                'early_minutes' => $log?->early_minutes,
                'night_hours' => $log?->night_hours,
                'ot_hours' => $cell['ot_hours'] ?? null,
                'source' => $log?->source,
                'source_label' => $this->sourceLabel($log?->source),
                'location_status' => $log?->location_status,
                'location_status_label' => $this->locationStatusLabel($log?->location_status),
                'check_in_location' => $this->formatLocation($log, 'in'),
                'check_out_location' => $this->formatLocation($log, 'out'),
                'device_name' => $log?->device?->name,
                'punches' => $dayPunches->map(fn (AttendancePunch $p) => [
                    'punch_type' => $p->punch_type,
                    'punch_type_label' => $p->punch_type === 'in' ? 'Vào' : 'Ra',
                    'punched_at' => Carbon::parse($p->punched_at)->format('H:i:s'),
                    'source' => $p->source,
                    'source_label' => $this->sourceLabel($p->source),
                    'zone_name' => $p->zone?->name,
                    'zone_code' => $p->zone?->code,
                    'address' => $p->zone?->address_note,
                    'device_name' => $p->device?->name,
                    'latitude' => $p->latitude,
                    'longitude' => $p->longitude,
                    'is_valid' => $p->is_valid,
                    'validation_message' => $p->validation_message,
                ])->all(),
            ];
        }

        return [
            'period' => $period,
            'standard_work_days' => $timesheet['standard_work_days'] ?? 0,
            'employee' => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'department' => $employee->department?->name,
                'probation_end_date' => $probationEnd?->format('Y-m-d'),
                'has_phase_split' => count($this->phaseResolver->phasesInPeriod($employee, $period)) > 1,
            ],
            'summary' => $summary ? [
                'work_days' => (float) $summary->work_days,
                'probation_work_days' => (float) $summary->probation_work_days,
                'official_work_days' => (float) $summary->official_work_days,
                'standard_work_days' => (float) $summary->standard_work_days,
                'paid_leave_days' => (float) $summary->paid_leave_days,
                'unpaid_leave_days' => (float) $summary->unpaid_leave_days,
                'absent_days' => (float) $summary->absent_days,
                'ot_hours' => (float) $summary->ot_hours,
                'is_locked' => (bool) $summary->is_locked,
            ] : null,
            'totals' => $row['totals'] ?? [],
            'daily_rows' => $dailyRows,
        ];
    }

    /** @return array<string, mixed>|null */
    private function formatLocation(?AttendanceLog $log, string $which): ?array
    {
        if (! $log) {
            return null;
        }

        $zone = $which === 'in' ? $log->checkInZone : $log->checkOutZone;
        $lat = $which === 'in' ? $log->check_in_latitude : $log->check_out_latitude;
        $lng = $which === 'in' ? $log->check_in_longitude : $log->check_out_longitude;

        if (! $zone && ! $lat && ! $lng) {
            return null;
        }

        $label = $zone
            ? trim($zone->name.($zone->code ? " ({$zone->code})" : ''))
            : ($lat && $lng ? "GPS {$lat}, {$lng}" : null);

        return [
            'zone_name' => $zone?->name,
            'zone_code' => $zone?->code,
            'address' => $zone?->address_note,
            'latitude' => $lat,
            'longitude' => $lng,
            'label' => $label,
        ];
    }

    private function locationStatusLabel(?string $status): ?string
    {
        return match ($status) {
            'valid' => 'Đúng vùng GPS',
            'outside' => 'Ngoài vùng cho phép',
            'field_trip' => 'Công tác / ngoài văn phòng',
            'device_trusted' => 'Máy chấm công',
            'qr_gate' => 'Cổng QR',
            default => $status ? ucfirst($status) : null,
        };
    }

    private function sourceLabel(?string $source): ?string
    {
        return match ($source) {
            'mobile' => 'App di động',
            'device' => 'Máy chấm công',
            'kiosk' => 'Kiosk',
            'qr' => 'Quét QR',
            'manual' => 'Nhập tay / import',
            'import' => 'Import Excel',
            'correction' => 'Bù thẻ',
            default => $source,
        };
    }

    private function phaseLabel(?string $phase): ?string
    {
        return match ($phase) {
            'probation' => 'Thử việc',
            'official' => 'Chính thức',
            default => null,
        };
    }
}
