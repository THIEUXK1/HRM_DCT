<?php

namespace App\Http\Controllers\Api;

use App\Models\PayrollJournalMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollJournalMappingController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-Id') ?? $request->input('company_id');

        $query = PayrollJournalMapping::with(['department:id,name', 'position:id,name']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($request->has('mapping_type')) {
            $query->where('mapping_type', $request->input('mapping_type'));
        }

        return $this->success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-Id') ?? $request->input('company_id');

        $data = $request->validate([
            'mapping_type' => ['required', 'string', 'in:salary,employee_insurance,employer_insurance,kpcd,union_fee'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'debit_account' => ['required', 'string', 'max:50'],
            'credit_account' => ['required', 'string', 'max:50'],
        ]);

        if (! $companyId) {
            return response()->json(['message' => 'X-Company-Id header or company_id input is required.'], 400);
        }

        $mapping = PayrollJournalMapping::create(array_merge($data, [
            'company_id' => $companyId,
        ]));

        return $this->success($mapping, 201);
    }

    public function show(PayrollJournalMapping $payrollJournalMapping): JsonResponse
    {
        return $this->success($payrollJournalMapping->load(['department', 'position']));
    }

    public function update(Request $request, PayrollJournalMapping $payrollJournalMapping): JsonResponse
    {
        $data = $request->validate([
            'debit_account' => ['required', 'string', 'max:50'],
            'credit_account' => ['required', 'string', 'max:50'],
        ]);

        $payrollJournalMapping->update($data);

        return $this->success($payrollJournalMapping);
    }

    public function destroy(PayrollJournalMapping $payrollJournalMapping): JsonResponse
    {
        $payrollJournalMapping->delete();

        return $this->success(null, 204);
    }
}
