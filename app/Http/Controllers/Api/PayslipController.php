<?php

namespace App\Http\Controllers\Api;

use App\Models\PayrollResult;
use App\Models\Payslip;
use App\Services\Payroll\PayslipRenderService;
use Illuminate\Http\Response;

class PayslipController extends ApiController
{
    public function __construct(
        private readonly PayslipRenderService $payslipRender,
    ) {}

    public function show(PayrollResult $payrollResult): Response
    {
        $payrollResult->load(['employee', 'cycle', 'payslip']);

        if (! $payrollResult->payslip) {
            Payslip::create([
                'payroll_result_id' => $payrollResult->id,
                'status' => 'published',
                'published_at' => now(),
            ]);
            $payrollResult->load('payslip');
        }

        $html = $this->payslipRender->render($payrollResult);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function publishCycle(int $payrollCycleId): \Illuminate\Http\JsonResponse
    {
        $results = PayrollResult::where('payroll_cycle_id', $payrollCycleId)->get();

        foreach ($results as $result) {
            Payslip::updateOrCreate(
                ['payroll_result_id' => $result->id],
                ['status' => 'published', 'published_at' => now()]
            );
        }

        return $this->success(['published' => $results->count()]);
    }
}
