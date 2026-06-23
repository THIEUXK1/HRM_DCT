<?php

namespace App\Http\Controllers\Api;

use App\Models\AttendanceCorrectionReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceCorrectionReasonController extends ApiController
{
    public function index(): JsonResponse
    {
        $reasons = AttendanceCorrectionReason::orderBy('sort_order')->orderBy('name')->get();

        return $this->success($reasons);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = \App\Support\CompanyContext::id();

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('attendance_correction_reasons')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => 'required|string|max:255',
            'counts_as_forgot_punch' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $reason = AttendanceCorrectionReason::create($data);

        return $this->success($reason, 201);
    }

    public function update(Request $request, AttendanceCorrectionReason $attendanceCorrectionReason): JsonResponse
    {
        $companyId = \App\Support\CompanyContext::id();

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('attendance_correction_reasons')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($attendanceCorrectionReason->id),
            ],
            'name' => 'required|string|max:255',
            'counts_as_forgot_punch' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $attendanceCorrectionReason->update($data);

        return $this->success($attendanceCorrectionReason->fresh());
    }

    public function destroy(AttendanceCorrectionReason $attendanceCorrectionReason): JsonResponse
    {
        if ($attendanceCorrectionReason->requests()->exists()) {
            return $this->error('Không thể xóa lý do đã có đơn bù thẻ.', 422);
        }

        $attendanceCorrectionReason->delete();

        return $this->success(null, 204);
    }
}
