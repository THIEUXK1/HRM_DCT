<?php

namespace App\Http\Controllers\Api;

use App\Services\Hr\ExternalHrSyncService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalSyncController extends ApiController
{
    public function __construct(private readonly ExternalHrSyncService $service) {}

    /**
     * POST /api/v1/admin/sync-external-hr
     * Syncs all companies (prefix-routed) or a single company when ?company_id= is given.
     */
    public function syncHr(Request $request): JsonResponse
    {
        // Allow scoping to a single company via query param (optional)
        $companyId = $request->integer('company_id') ?: null;

        try {
            $stats = $this->service->sync($companyId);
            return $this->success($stats, 200);
        } catch (\Throwable $e) {
            return $this->error('Đồng bộ thất bại: ' . $e->getMessage(), 500);
        }
    }
}
