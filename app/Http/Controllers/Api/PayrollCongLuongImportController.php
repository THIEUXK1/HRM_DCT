<?php

namespace App\Http\Controllers\Api;

use App\Services\Payroll\CongLuongImportService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollCongLuongImportController extends ApiController
{
    public function __construct(
        private readonly CongLuongImportService $importService,
    ) {}

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $companyId = (int) CompanyContext::id();
        $result = $this->importService->import(
            $companyId,
            $validated['period'],
            $validated['file'],
        );

        return $this->success($result, 201);
    }
}
