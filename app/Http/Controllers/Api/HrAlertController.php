<?php

namespace App\Http\Controllers\Api;

use App\Services\Hr\HrComplianceAlertService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrAlertController extends ApiController
{
    public function __construct(
        private readonly HrComplianceAlertService $alerts,
    ) {}

    /** GET /hr-alerts — danh sách cảnh báo tuân thủ. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'category' => ['nullable', 'string', 'max:64'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $companyId = (int) CompanyContext::id();
        $period = $data['period'] ?? now()->format('Y-m');

        return $this->success([
            'period' => $period,
            'items' => $this->alerts->list(
                $companyId,
                $period,
                $data['category'] ?? null,
                (int) ($data['limit'] ?? 100),
            ),
            'summary' => $this->alerts->summary($companyId, $period),
        ]);
    }

    /** GET /hr-alerts/summary — chỉ số lượng (dashboard). */
    public function summary(Request $request): JsonResponse
    {
        $period = $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ])['period'] ?? now()->format('Y-m');

        $companyId = (int) CompanyContext::id();

        return $this->success([
            'period' => $period,
            'counts' => $this->alerts->dashboardCounts($companyId, $period),
            'detail' => $this->alerts->summary($companyId, $period),
        ]);
    }
}
