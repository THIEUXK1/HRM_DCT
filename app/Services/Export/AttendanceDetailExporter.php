<?php

namespace App\Services\Export;

use App\Services\Attendance\AttendanceEmployeeDetailService;

/**
 * Xuất bảng công chi tiết một nhân viên (giờ chấm + vị trí từng ngày).
 */
class AttendanceDetailExporter
{
    public function __construct(
        private readonly AttendanceEmployeeDetailService $detailService,
    ) {}

    public function download(int $companyId, int $employeeId, string $period): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->detailService->detail($companyId, $employeeId, $period);
        $employee = $data['employee'];

        $headers = [
            'Ngày', 'Thứ', 'Ký hiệu', 'Trạng thái', 'Giai đoạn',
            'Giờ vào', 'Giờ ra', 'Giờ làm', 'Trễ (p)', 'OT (h)',
            'Vị trí vào', 'Vị trí ra', 'Nguồn', 'Xác thực vị trí',
            'Thiết bị', 'Chi tiết punch',
        ];

        $rows = [$headers];
        foreach ($data['daily_rows'] as $day) {
            $punchDetail = collect($day['punches'] ?? [])
                ->map(fn ($p) => sprintf(
                    '%s %s %s %s',
                    $p['punch_type_label'],
                    $p['punched_at'],
                    $p['zone_name'] ?? $p['source_label'] ?? '',
                    $p['is_valid'] ? '' : '(⚠ '.$p['validation_message'].')',
                ))
                ->implode(' | ');

            $rows[] = [
                $day['date'],
                $day['weekday_label'],
                $day['symbol'],
                $day['status_label'],
                $day['employment_phase_label'] ?? '',
                $day['check_in_at'] ?? '',
                $day['check_out_at'] ?? '',
                $day['work_hours'] ?? '',
                $day['late_minutes'] ?? '',
                $day['ot_hours'] ?? '',
                $day['check_in_location']['label'] ?? '',
                $day['check_out_location']['label'] ?? '',
                $day['source_label'] ?? '',
                $day['location_status_label'] ?? '',
                $day['device_name'] ?? '',
                $punchDetail,
            ];
        }

        $xlsx = new SimpleXlsxWriter();
        $sheetName = mb_substr($employee['employee_code'] ?? 'NV', 0, 20);
        $xlsx->addSheet($sheetName, $rows, [12, 6, 8, 22, 10, 8, 8, 8, 8, 8, 24, 24, 14, 18, 14, 40]);

        $filename = sprintf(
            'cong-chi-tiet-%s-%s.xlsx',
            $employee['employee_code'] ?? $employeeId,
            $period,
        );

        return $xlsx->download($filename);
    }
}
