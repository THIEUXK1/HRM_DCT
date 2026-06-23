<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\BulkEmploymentContractRequest;
use App\Http\Requests\EmploymentContractRequest;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Services\AuditLogger;
use App\Services\Hr\EmployeeProbationSyncService;
use App\Services\Hr\EmploymentContractBulkService;
use App\Services\Hr\HrFileStorage;
use App\Support\EmployeeQueryScope;
use App\Support\QuerySearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmploymentContractController extends ApiController
{
    public function __construct(
        protected HrFileStorage $storage,
        protected EmployeeProbationSyncService $probationSync,
        protected EmploymentContractBulkService $bulkService,
    ) {
        $this->authorizeResource(EmploymentContract::class, 'employment_contract');
    }

    public function index(Request $request): JsonResponse
    {
        $query = EmploymentContract::with(['employee:id,full_name,employee_code,department_id', 'employee.department:id,name'])
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim((string) $request->get('search'));
                $q->where(function ($sub) use ($search) {
                    $sub->where('contract_number', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn ($e) => QuerySearch::employee($e, $search));
                });
            });

        EmployeeQueryScope::applyOnRelation($query, 'employee', $request);

        $query->orderByDesc('start_date');

        if ($request->boolean('all')) {
            return $this->success($query->get());
        }

        return $this->success($query->paginate($request->integer('per_page', 25)));
    }

    public function store(EmploymentContractRequest $request): JsonResponse
    {
        $contract = EmploymentContract::create($request->validated());
        $this->syncEmployeeFromContract($contract);

        AuditLogger::log('created', $contract, null, 'contract',
            "Hợp đồng #{$contract->id} tạo mới cho NV ID {$contract->employee_id}");

        return $this->success($contract->load('employee'), 201);
    }

    /** Ký HĐ cho nhiều NV — mỗi người một bản ghi & số HĐ riêng. */
    public function storeBulk(BulkEmploymentContractRequest $request): JsonResponse
    {
        $this->authorize('create', EmploymentContract::class);

        $result = $this->bulkService->createMany($request->validated());

        return $this->success($result, 201);
    }

    public function show(EmploymentContract $employmentContract): JsonResponse
    {
        return $this->success($employmentContract->load(['employee.department', 'employee.position']));
    }

    public function update(EmploymentContractRequest $request, EmploymentContract $employmentContract): JsonResponse
    {
        $old = $employmentContract->only(['contract_type', 'start_date', 'end_date', 'basic_salary', 'status']);
        $employmentContract->update($request->validated());
        $this->syncEmployeeFromContract($employmentContract->fresh());

        AuditLogger::log('updated', $employmentContract, null, 'contract',
            "Hợp đồng #{$employmentContract->id} cập nhật",
            $old,
            $employmentContract->only(['contract_type', 'start_date', 'end_date', 'basic_salary', 'status'])
        );

        return $this->success($employmentContract);
    }

    public function destroy(EmploymentContract $employmentContract): JsonResponse
    {
        AuditLogger::log('deleted', $employmentContract, null, 'contract',
            "Hợp đồng #{$employmentContract->id} của NV ID {$employmentContract->employee_id} đã xóa");

        $this->storage->delete($employmentContract->file_path, $employmentContract->file_disk);
        $employmentContract->delete();

        return $this->noContent();
    }

    public function upload(Request $request, EmploymentContract $employmentContract): JsonResponse
    {
        $this->authorize('update', $employmentContract);

        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx'],
        ]);

        if ($employmentContract->file_path) {
            $this->storage->delete($employmentContract->file_path, $employmentContract->file_disk);
        }

        $stored = $this->storage->storeContractFile(
            $request->file('file'),
            $employmentContract->employee_id,
            $employmentContract->id
        );

        $employmentContract->update($stored);

        return $this->success($employmentContract->fresh());
    }

    public function download(EmploymentContract $employmentContract): StreamedResponse
    {
        $this->authorize('view', $employmentContract);
        abort_unless($employmentContract->file_path, 404);

        return $this->storage->downloadResponse(
            $employmentContract->file_path,
            $employmentContract->file_name ?? 'hop-dong.pdf',
            $employmentContract->file_disk
        );
    }

    protected function syncEmployeeFromContract(EmploymentContract $contract): void
    {
        $this->probationSync->syncFromContract($contract);
    }
}
