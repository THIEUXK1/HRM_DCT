<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\AttendanceDevicePunchRequest;
use App\Models\Employee;
use App\Services\Attendance\AttendancePunchService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AttendanceDevicePunchController extends ApiController
{
    public function store(AttendanceDevicePunchRequest $request, AttendancePunchService $service): JsonResponse
    {
        /** @var \App\Models\AttendanceDevice $device */
        $device = $request->attributes->get('attendance_device');

        $employee = Employee::query()
            ->where('company_id', $device->company_id)
            ->where('employee_code', $request->validated('employee_code'))
            ->first();

        if (! $employee) {
            return $this->error('Không tìm thấy nhân viên với mã '.$request->validated('employee_code'), 404);
        }

        try {
            $result = $service->punch(
                $employee,
                $request->validated('punch_type'),
                'device',
                $request->filled('latitude') ? (float) $request->validated('latitude') : null,
                $request->filled('longitude') ? (float) $request->validated('longitude') : null,
                $request->validated('accuracy_meters'),
                $device,
                $request->ip(),
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'message' => $result['message'],
            'employee' => $employee->only(['id', 'employee_code', 'full_name']),
            'punch' => $result['punch'],
            'log' => $result['log'],
        ], 201);
    }
}
