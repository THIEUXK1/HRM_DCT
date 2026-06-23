<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StorePayrollCycleRequest;
use App\Models\PayrollCycle;
use App\Services\AuditLogger;
use App\Services\Payroll\PayrollCycleLockService;
use App\Services\Payroll\PayrollCycleService;
use App\Services\Payroll\PayrollCycleStoreService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PayrollCycleController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            PayrollCycle::withCount('results')
                ->orderByDesc('period')
                ->orderByDesc('run_number')
                ->paginate(request()->integer('per_page', 24)),
        );
    }

    public function periodStatus(Request $request, PayrollCycleStoreService $storeService): JsonResponse
    {
        $period = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ])['period'];

        return $this->success($storeService->periodStatus(CompanyContext::id(), $period));
    }

    public function store(StorePayrollCycleRequest $request, PayrollCycleStoreService $storeService): JsonResponse
    {
        $data = $request->validated();

        try {
            $cycle = $storeService->create(
                CompanyContext::id(),
                $data['period'],
                $data['revision_note'] ?? null,
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($cycle, 201);
    }

    public function show(PayrollCycle $payrollCycle): JsonResponse
    {
        return $this->success($payrollCycle->load('results.employee'));
    }

    public function calculate(PayrollCycle $payrollCycle, PayrollCycleService $service): JsonResponse
    {
        $cycle = $service->calculate($payrollCycle);

        AuditLogger::finalized($payrollCycle, "Payroll cycle {$payrollCycle->period} calculated/finalized");

        return $this->success($cycle);
    }

    public function lock(PayrollCycle $payrollCycle, PayrollCycleLockService $lockService): JsonResponse
    {
        $cycle = $lockService->lock($payrollCycle, auth()->user());

        return $this->success($cycle);
    }

    public function unlock(Request $request, PayrollCycle $payrollCycle, PayrollCycleLockService $lockService): JsonResponse
    {
        $reason = $request->validate(['reason' => 'nullable|string|max:500'])['reason'] ?? null;
        $cycle = $lockService->unlock($payrollCycle, auth()->user(), $reason);

        return $this->success($cycle);
    }

    public function export(PayrollCycle $payrollCycle): \Symfony\Component\HttpFoundation\Response
    {
        AuditLogger::exported(
            PayrollCycle::class,
            $payrollCycle->id,
            "Exported payroll cycle {$payrollCycle->period} to XLSX"
        );

        return (new \App\Services\Export\PayrollExporter())->download($payrollCycle);
    }
}
