<?php

namespace App\Http\Controllers\Api;

use App\Models\BhxhDeclaration;
use App\Models\Company;
use App\Services\AuditLogger;
use App\Services\Bhxh\BhxhDeclarationService;
use App\Services\Bhxh\BhxhExportService;
use App\Services\Hr\HrFileStorage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BhxhController extends ApiController
{
    public function __construct(
        protected BhxhDeclarationService $declarations,
        protected BhxhExportService $export,
        protected HrFileStorage $storage
    ) {}

    public function meta(): JsonResponse
    {
        return $this->success([
            'declaration_types' => config('bhxh_vn.declaration_types'),
            'termination_reasons' => config('bhxh_vn.termination_reasons'),
            'rates' => config('bhxh_vn.rates'),
            'salary_limits' => config('bhxh_vn.salary'),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        return $this->success($this->declarations->dashboard($company));
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'declaration_type' => ['required', 'in:d01,d02,d05,tk1,roster'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $company = Company::findOrFail($data['company_id']);
        $from = isset($data['from']) ? Carbon::parse($data['from']) : null;
        $to = isset($data['to']) ? Carbon::parse($data['to']) : null;

        return $this->success($this->declarations->preview($data['declaration_type'], $company, $from, $to));
    }

    public function export(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'declaration_type' => ['required', 'in:d01,d02,d05,tk1,roster'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'format' => ['nullable', 'in:csv,xml'],
            'only_valid' => ['sometimes', 'boolean'],
        ]);

        $company = Company::findOrFail($data['company_id']);
        $from = isset($data['from']) ? Carbon::parse($data['from']) : null;
        $to = isset($data['to']) ? Carbon::parse($data['to']) : null;
        $format = $data['format'] ?? 'csv';

        $result = $this->declarations->export(
            $data['declaration_type'],
            $company,
            $from,
            $to,
            $format,
            $data['only_valid'] ?? true,
            auth()->id()
        );

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'],
                'data' => $result['preview'] ?? null,
            ], 422);
        }

        AuditLogger::exported(
            Company::class,
            (int) $data['company_id'],
            "BHXH export: {$data['declaration_type']} ({$format}), period {$data['from']} → {$data['to']}"
        );

        return $this->success($result);
    }

    public function declarations(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'declaration_type' => ['nullable', 'in:d01,d02,d05,tk1,roster'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = BhxhDeclaration::with(['company:id,name,code', 'creator:id,name'])
            ->orderByDesc('created_at');

        if (! empty($data['company_id'])) {
            $query->where('company_id', $data['company_id']);
        }
        if (! empty($data['type'])) {
            $query->where('declaration_type', $data['type']);
        }

        return $this->success($query->limit($data['limit'] ?? 30)->get());
    }

    public function showDeclaration(BhxhDeclaration $bhxhDeclaration): JsonResponse
    {
        return $this->success($bhxhDeclaration->load(['company', 'lines.employee:id,full_name,employee_code', 'creator:id,name']));
    }

    public function downloadDeclaration(BhxhDeclaration $bhxhDeclaration): Response
    {
        abort_unless($bhxhDeclaration->file_path, 404);

        return $this->storage->downloadResponse(
            $bhxhDeclaration->file_path,
            $bhxhDeclaration->file_name,
            $bhxhDeclaration->file_disk
        );
    }

    /** Tính mức đóng BHXH/BHYT/BHTN cho một mức lương. */
    public function calculateContribution(Request $request): JsonResponse
    {
        $data = $request->validate([
            'insurance_salary' => ['required', 'numeric', 'min:0'],
        ]);

        $calc = app(\App\Services\Bhxh\BhxhContributionCalculator::class);

        return $this->success($calc->forSalary((float) $data['insurance_salary']));
    }

    protected function resolveCompany(Request $request): Company
    {
        $id = $request->validate(['company_id' => ['required', 'exists:companies,id']])['company_id'];

        return Company::findOrFail($id);
    }
}
