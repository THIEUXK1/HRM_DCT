<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class AttendanceDeviceImportService
{
    public function importCsv(AttendanceDevice $device, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            throw new RuntimeException('Cannot read import file.');
        }

        $header = fgetcsv($handle);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $mapped = $this->mapRow($device->import_format, $header, $row);
                $employee = Employee::where('company_id', $device->company_id)
                    ->where(function ($q) use ($mapped) {
                        $q->where('employee_code', $mapped['employee_code'])
                            ->orWhere('national_id', $mapped['employee_code']);
                    })
                    ->first();

                if (! $employee) {
                    $skipped++;
                    $errors[] = 'Employee not found: '.$mapped['employee_code'];

                    continue;
                }

                $workDate = Carbon::parse($mapped['work_date'])->toDateString();

                AttendanceLog::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'work_date' => Carbon::parse($workDate),
                    ],
                    [
                        'company_id' => $device->company_id,
                        'attendance_device_id' => $device->id,
                        'check_in_at' => $mapped['check_in'] ?? null,
                        'check_out_at' => $mapped['check_out'] ?? null,
                        'source' => 'device',
                        'external_ref' => $mapped['external_ref'] ?? null,
                    ]
                );

                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = $e->getMessage();
            }
        }

        fclose($handle);

        return compact('imported', 'skipped', 'errors');
    }

    protected function mapRow(string $format, ?array $header, array $row): array
    {
        if ($format === 'csv_generic' && $header) {
            $data = array_combine($header, $row);

            return [
                'employee_code' => trim($data['employee_code'] ?? $data['ma_nv'] ?? ''),
                'work_date' => $data['work_date'] ?? $data['ngay'] ?? '',
                'check_in' => $data['check_in'] ?? $data['gio_vao'] ?? null,
                'check_out' => $data['check_out'] ?? $data['gio_ra'] ?? null,
                'external_ref' => $data['ref'] ?? null,
            ];
        }

        return [
            'employee_code' => trim($row[0] ?? ''),
            'work_date' => $row[1] ?? '',
            'check_in' => $row[2] ?? null,
            'check_out' => $row[3] ?? null,
            'external_ref' => $row[4] ?? null,
        ];
    }
}
