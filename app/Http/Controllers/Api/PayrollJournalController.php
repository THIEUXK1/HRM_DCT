<?php

namespace App\Http\Controllers\Api;

use App\Models\PayrollJournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollJournalController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-Id') ?? $request->input('company_id');

        $query = PayrollJournalEntry::with(['cycle:id,period,run_number']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('payroll_cycle_id')) {
            $query->where('payroll_cycle_id', $request->input('payroll_cycle_id'));
        }

        return $this->success($query->orderByDesc('entry_date')->get());
    }

    public function show(PayrollJournalEntry $payrollJournalEntry): JsonResponse
    {
        $payrollJournalEntry->load([
            'cycle:id,period,run_number',
            'lines.employee:id,employee_code,full_name',
            'lines.department:id,name',
            'postedBy:id,name',
        ]);

        return $this->success($payrollJournalEntry);
    }

    public function post(Request $request, PayrollJournalEntry $payrollJournalEntry): JsonResponse
    {
        if ($payrollJournalEntry->status === 'posted') {
            return response()->json(['message' => 'Bút toán này đã được hạch toán (posted) trước đó.'], 422);
        }

        $payrollJournalEntry->update([
            'status' => 'posted',
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);

        return $this->success($payrollJournalEntry);
    }
}
