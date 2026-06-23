<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreLeaveTypeRequest;
use App\Http\Requests\UpdateLeaveTypeRequest;
use App\Models\LeaveType;
use App\Support\CompanyContext;
use Database\Seeders\HcmPlatformSeeder;
use Illuminate\Http\JsonResponse;

class LeaveTypeController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            LeaveType::orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'payroll_categories' => config('leave_payroll_vn.payroll_categories', []),
        ]);
    }

    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = strtoupper($data['code']);
        $data['day_count_mode'] = $data['day_count_mode'] ?? 'workday';
        $data['requires_approval'] = $data['requires_approval'] ?? true;
        $data['payroll_category'] = $data['payroll_category'] ?? LeaveType::PAYROLL_COMPANY_PAID;

        $leaveType = new LeaveType($data);
        $leaveType->applyPayrollCategoryDefaults();
        $leaveType->save();

        return $this->success($leaveType, 201);
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = strtoupper($data['code']);

        $leaveType->fill($data);
        if (isset($data['payroll_category'])) {
            $leaveType->applyPayrollCategoryDefaults();
        }
        $leaveType->save();

        return $this->success($leaveType->fresh());
    }

    public function destroy(LeaveType $leaveType): JsonResponse
    {
        if ($leaveType->requests()->exists()) {
            return $this->error('Không thể xóa loại nghỉ đã có đơn nghỉ phép.', 422);
        }

        $leaveType->delete();

        return $this->success(null, 204);
    }

    public function seedStandard(): JsonResponse
    {
        $companyId = CompanyContext::id();
        (new HcmPlatformSeeder())->syncLeaveTypesForCompany($companyId);

        return $this->success(
            LeaveType::orderBy('sort_order')->orderBy('name')->get()
        );
    }
}
