<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\AttendancePunchRequest;
use App\Services\Attendance\AttendancePunchService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AttendancePunchController extends ApiController
{
    public function today(AttendancePunchService $service): JsonResponse
    {
        $employee = auth()->user()?->employee;
        if (! $employee) {
            return $this->error('Tài khoản chưa liên kết hồ sơ nhân viên.', 404);
        }

        return $this->success($service->todayStatus($employee));
    }

    public function punch(AttendancePunchRequest $request, AttendancePunchService $service): JsonResponse
    {
        $employee = auth()->user()->employee;

        try {
            $source = $request->input('source', 'mobile');
            if ($request->filled('zone_code') && $request->filled('gate_token')) {
                $source = 'qr';
            }

            $result = $service->punch(
                $employee,
                $request->validated('punch_type'),
                $source,
                $request->has('latitude') ? (float) $request->input('latitude') : null,
                $request->has('longitude') ? (float) $request->input('longitude') : null,
                $request->validated('accuracy_meters'),
                null,
                $request->ip(),
                $request->input('zone_code'),
                $request->input('gate_token'),
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'message' => $result['message'],
            'punch' => $result['punch'],
            'log' => $result['log'],
        ], 201);
    }
}
