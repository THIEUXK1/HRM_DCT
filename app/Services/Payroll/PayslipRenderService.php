<?php

namespace App\Services\Payroll;

use App\Models\CompanySetting;
use App\Models\PayrollResult;

class PayslipRenderService
{
    public function __construct(
        private readonly BpvnPayslipMapper $bpvnMapper,
    ) {}

    public function resolveTemplateCode(PayrollResult $result): string
    {
        $result->loadMissing('employee');

        $companyId = $result->employee?->company_id;
        if (! $companyId) {
            return config('payslip_templates.default', 'bpvn-ac-pr-006');
        }

        return CompanySetting::where('company_id', $companyId)
            ->where('key', 'payslip_template_code')
            ->value('value')
            ?? config('payslip_templates.default', 'bpvn-ac-pr-006');
    }

    public function render(PayrollResult $result): string
    {
        $code = strtolower($this->resolveTemplateCode($result));
        $template = config("payslip_templates.templates.{$code}");

        if ($code === 'simple' || ! $template) {
            return view('payslips.show', ['result' => $result])->render();
        }

        $viewData = match ($code) {
            'bpvn-ac-pr-006' => $this->bpvnMapper->map($result),
            default => ['result' => $result],
        };

        return view($template['view'], $viewData)->render();
    }
}
