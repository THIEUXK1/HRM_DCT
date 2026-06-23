<?php

namespace App\Http\Controllers\Api;

use App\Models\ZkTecoSyncBatch;
use App\Services\Attendance\ZKTecoSyncService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZkTecoSyncController extends ApiController
{
    public function __construct()
    {
        $this->middleware('role_or_permission:admin|attendance.manage');
    }

    /**
     * Run a dry-run check of the sync batch.
     */
    public function dryRun(Request $request, ZKTecoSyncService $syncService): JsonResponse
    {
        $companyId = CompanyContext::id();

        $data = $request->validate([
            'device_ids' => 'required|array|min:1',
            'device_ids.*' => 'integer|exists:attendance_devices,id',
            'mode' => 'required|in:all,department,manual',
            'department_id' => 'nullable|integer|exists:departments,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer|exists:employees,id',
            'filters' => 'nullable|array',
            'options' => 'nullable|array',
        ]);

        $report = $syncService->dryRunReport(
            companyId: $companyId,
            deviceIds: $data['device_ids'],
            mode: $data['mode'],
            departmentId: $data['department_id'] ?? null,
            employeeIds: $data['employee_ids'] ?? [],
            filters: $data['filters'] ?? [],
            options: $data['options'] ?? []
        );

        return $this->success($report);
    }

    /**
     * Start the actual sync batch by triggering the queue job.
     */
    public function run(Request $request, ZKTecoSyncService $syncService): JsonResponse
    {
        $companyId = CompanyContext::id();
        $requestedBy = auth()->id();

        $data = $request->validate([
            'device_ids' => 'required|array|min:1',
            'device_ids.*' => 'integer|exists:attendance_devices,id',
            'mode' => 'required|in:all,department,manual',
            'department_id' => 'nullable|integer|exists:departments,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer|exists:employees,id',
            'filters' => 'nullable|array',
            'options' => 'nullable|array',
        ]);

        $batch = $syncService->runSync(
            companyId: $companyId,
            deviceIds: $data['device_ids'],
            mode: $data['mode'],
            departmentId: $data['department_id'] ?? null,
            employeeIds: $data['employee_ids'] ?? [],
            filters: $data['filters'] ?? [],
            options: $data['options'] ?? [],
            requestedBy: $requestedBy
        );

        return $this->success($batch, 201);
    }

    /**
     * List sync batches.
     */
    public function listBatches(): JsonResponse
    {
        $batches = ZkTecoSyncBatch::with('requestedByUser:id,name')
            ->orderByDesc('id')
            ->paginate(20);

        return $this->success($batches);
    }

    /**
     * Retrieve a specific sync batch with its logs.
     */
    public function getBatch(ZkTecoSyncBatch $batch): JsonResponse
    {
        return $this->success($batch->load([
            'requestedByUser:id,name',
            'logs.employee:id,first_name,last_name,full_name,employee_code',
            'logs.device:id,name,ip_address'
        ]));
    }
}
