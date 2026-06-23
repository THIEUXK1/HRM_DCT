<?php

namespace App\Services\Export;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

class EmployeeExporter
{
    private array $headers = [
        'Mã NV', 'Họ tên', 'Email', 'Điện thoại',
        'Phòng ban', 'Chi nhánh', 'Chức danh',
        'Giới tính', 'Ngày sinh', 'Ngày vào làm',
        'Loại HĐ', 'Trạng thái',
        'CCCD/CMND', 'Mã số thuế', 'BHXH',
    ];

    private array $colWidths = [
        14, 28, 30, 16,
        24, 20, 24,
        10, 14, 14,
        18, 14,
        18, 16, 18,
    ];

    public function download(Builder $query): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $employees = $query
            ->with(['department', 'branch', 'position'])
            ->orderBy('last_name')
            ->get();

        $rows = [$this->headers];

        foreach ($employees as $e) {
            $rows[] = [
                $e->employee_code,
                $e->full_name,
                $e->email,
                $e->phone,
                $e->department?->name,
                $e->branch?->name,
                $e->position?->name,
                $e->gender === 'male' ? 'Nam' : ($e->gender === 'female' ? 'Nữ' : ''),
                $e->date_of_birth,
                $e->hire_date,
                $e->employment_type,
                $this->statusLabel($e->employment_status),
                $e->cccd,
                $e->tax_code,
                $e->bhxh_code,
            ];
        }

        $xlsx = new SimpleXlsxWriter();
        $xlsx->addSheet('Danh sách nhân viên', $rows, $this->colWidths);

        $filename = 'DS-nhan-vien-' . now()->format('Ymd') . '.xlsx';

        return $xlsx->download($filename);
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'active'      => 'Đang làm việc',
            'probation'   => 'Thử việc',
            'resigned'    => 'Đã nghỉ',
            'terminated'  => 'Thôi việc',
            'on_leave'    => 'Tạm nghỉ',
            default       => $status ?? '',
        };
    }
}
