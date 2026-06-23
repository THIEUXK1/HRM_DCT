<?php

use App\Models\Employee;
use App\Models\AttendanceLog;

$e = Employee::where('employee_code', 'EMP-101')->first();
$logs = AttendanceLog::withoutGlobalScopes()
    ->where('employee_id', $e->id)
    ->orderBy('work_date')->limit(3)->get();

echo 'So log thang nay: '.AttendanceLog::withoutGlobalScopes()->where('employee_id', $e->id)->count().PHP_EOL;
foreach ($logs as $l) {
    echo json_encode([
        'work_date' => (string) $l->work_date,
        'check_in_at' => (string) $l->check_in_at,
        'check_out_at' => (string) $l->check_out_at,
        'work_hours' => $l->work_hours,
        'employment_phase' => $l->employment_phase,
    ], JSON_UNESCAPED_UNICODE).PHP_EOL;
}
