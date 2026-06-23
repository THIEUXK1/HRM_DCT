<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StorePayrollBonusTypeRequest;
use App\Http\Requests\UpdatePayrollBonusTypeRequest;
use App\Models\PayrollBonusType;
use App\Support\CompanyContext;
use Database\Seeders\HcmPlatformSeeder;
use Illuminate\Http\JsonResponse;

class PayrollBonusTypeController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            PayrollBonusType::orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'categories' => config('leave_payroll_vn.bonus_categories', []),
            'calculation_modes' => config('leave_payroll_vn.bonus_calculation_modes', []),
        ]);
    }

    public function store(StorePayrollBonusTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = strtoupper($data['code']);

        return $this->success(PayrollBonusType::create($data), 201);
    }

    public function update(UpdatePayrollBonusTypeRequest $request, PayrollBonusType $payrollBonusType): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = strtoupper($data['code']);
        $payrollBonusType->update($data);

        return $this->success($payrollBonusType->fresh());
    }

    public function destroy(PayrollBonusType $payrollBonusType): JsonResponse
    {
        $payrollBonusType->delete();

        return $this->success(null, 204);
    }

    public function seedStandard(): JsonResponse
    {
        $companyId = CompanyContext::id();
        (new HcmPlatformSeeder())->syncPayrollBonusTypesForCompany($companyId);

        return $this->success(
            PayrollBonusType::orderBy('sort_order')->orderBy('name')->get()
        );
    }
}
