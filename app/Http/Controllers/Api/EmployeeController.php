<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\EmployeeProfileRequest;
use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use App\Services\Hr\EmployeeProbationSyncService;
use App\Services\Hr\HrFileStorage;
use App\Support\EmployeeQueryScope;
use App\Support\QuerySearch;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends ApiController
{
    public function __construct(
        private readonly EmployeeProbationSyncService $probationSync,
    ) {
        $this->authorizeResource(Employee::class, 'employee');
    }

    public function index(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Employee::query()
            ->with(['branch:id,name,code', 'department:id,name', 'position:id,name']);

        EmployeeQueryScope::apply($query, $request);

        // Lọc chỉ NV có mã sinh trắc học — dùng cho màn hình đẩy lên máy chấm công
        if ($request->boolean('has_biometric')) {
            $query->whereHas('profile', fn ($q) => $q->whereNotNull('biometric_id')->where('biometric_id', '!=', ''));
            $query->with(['profile:employee_id,biometric_id']);
        }

        return $this->success($query->orderBy('last_name')->paginate($request->integer('per_page', 50)));
    }

    public function store(EmployeeRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        $employee = Employee::create($data);
        $this->probationSync->refreshEmploymentStatus($employee);

        return $this->success($employee, 201);
    }

    public function show(Employee $employee): \Illuminate\Http\JsonResponse
    {
        return $this->success($employee->load([
            'company', 'branch', 'department', 'position', 'manager',
            'leaveEntitlementGroup:id,code,name,annual_days',
            'profile', 'documents', 'contracts', 'dependents',
        ]));
    }

    public function updateProfile(EmployeeProfileRequest $request, Employee $employee): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $employee);

        $profile = $employee->profile()->updateOrCreate(
            ['employee_id' => $employee->id],
            $request->validated()
        );

        return $this->success($profile);
    }

    public function update(EmployeeRequest $request, Employee $employee): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        $employee->update($data);
        $this->probationSync->refreshEmploymentStatus($employee->fresh());

        return $this->success($employee);
    }

    public function destroy(Employee $employee): \Illuminate\Http\JsonResponse
    {
        $employee->delete();

        return $this->noContent();
    }

    public function photo(Employee $employee): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $employee);
        $path = $employee->profile?->profile_picture_path;
        if (! $path || ! Storage::disk(HrFileStorage::DISK)->exists($path)) {
            abort(404, 'Không có ảnh đại diện.');
        }
        return Storage::disk(HrFileStorage::DISK)->response($path);
    }

    public function uploadPhoto(\Illuminate\Http\Request $request, Employee $employee): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $employee);

        $request->validate([
            'photo' => ['required', 'file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $storage = app(HrFileStorage::class);

        // Delete old photo if exists
        $oldPath = $employee->profile?->profile_picture_path;
        if ($oldPath) {
            $storage->delete($oldPath);
        }

        $path = $storage->storeEmployeePhoto($request->file('photo'), $employee->id);

        $employee->profile()->updateOrCreate(
            ['employee_id' => $employee->id],
            ['profile_picture_path' => $path]
        );

        return $this->success(['profile_picture_path' => $path]);
    }

    public function export(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        if (!$user->hasRole('admin') && !$user->hasPermissionTo('employees.view')) {
            return $this->error('Bạn không có quyền xuất dữ liệu nhân viên.', 403);
        }

        $companyId = \App\Support\CompanyContext::id() ?? $user->default_company_id;
        if (!$companyId) {
            return $this->error('Không tìm thấy thông tin công ty.', 400);
        }

        $query = Employee::query();
        EmployeeQueryScope::apply($query, $request);

        \App\Services\AuditLogger::exported(Employee::class, $companyId, "Exported employee list (xlsx)");

        return (new \App\Services\Export\EmployeeExporter())->download($query);
    }

    public function downloadTemplate(\App\Services\Hr\EmployeeImportExportService $service): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $service->downloadTemplate();
    }

    public function import(\Illuminate\Http\Request $request, \App\Services\Hr\EmployeeImportExportService $service): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('admin') && !$user->hasPermissionTo('employees.create')) {
            return $this->error('Bạn không có quyền nhập dữ liệu nhân viên.', 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        $companyId = \App\Support\CompanyContext::id() ?? $user->default_company_id;
        if (!$companyId) {
            return $this->error('Không tìm thấy thông tin công ty.', 400);
        }

        $file      = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $result = match ($extension) {
            'xlsx', 'xls' => $service->importXlsx($companyId, $file),
            default       => $service->importCsv($companyId, $file),
        };

        return $this->success($result);
    }
}

