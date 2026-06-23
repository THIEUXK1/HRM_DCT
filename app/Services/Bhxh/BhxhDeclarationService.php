<?php

namespace App\Services\Bhxh;

use App\Models\BhxhDeclaration;
use App\Models\Company;
use App\Services\Hr\HrFileStorage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BhxhDeclarationService
{
    public function __construct(
        protected BhxhExportService $export,
        protected BhxhValidationService $validation,
        protected HrFileStorage $storage
    ) {}

    public function dashboard(Company $company): array
    {
        $active = $company->employees()->where('is_active', true);
        $insured = (clone $active)->whereNotNull('social_insurance_number')->count();
        $missingBhxh = (clone $active)->whereNull('social_insurance_number')->count();
        $missingSalary = (clone $active)->whereNull('insurance_salary')->count();
        $missingCccd = (clone $active)->whereNull('national_id')->count();

        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'social_insurance_unit_code' => $company->social_insurance_unit_code,
                'social_insurance_agency' => $company->social_insurance_agency,
                'configured' => ! empty($company->social_insurance_unit_code),
            ],
            'stats' => [
                'active_employees' => $active->count(),
                'insured_employees' => $insured,
                'missing_bhxh_number' => $missingBhxh,
                'missing_insurance_salary' => $missingSalary,
                'missing_cccd' => $missingCccd,
            ],
            'pending' => [
                'd01_count' => $this->export->employeesForIncrease($company, $from, $to)->count(),
                'd05_count' => $this->export->employeesForDecrease($company, $from, $to)->count(),
                'd02_count' => $this->export->employeesForAdjustment($company, $from, $to)->count(),
            ],
            'rates' => config('bhxh_vn.rates'),
            'salary_limits' => config('bhxh_vn.salary'),
        ];
    }

    public function preview(string $type, Company $company, ?Carbon $from, ?Carbon $to): array
    {
        $records = $this->export->resolveRecords($type, $company, $from, $to);
        $preview = $this->validation->buildPreview($type, $company, $records);

        return array_merge($preview, [
            'declaration_type' => $type,
            'declaration_label' => config("bhxh_vn.declaration_types.{$type}"),
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
        ]);
    }

    public function export(
        string $type,
        Company $company,
        ?Carbon $from,
        ?Carbon $to,
        string $format = 'csv',
        bool $onlyValid = true,
        ?int $userId = null
    ): array {
        $records = $this->export->resolveRecords($type, $company, $from, $to);
        $preview = $this->validation->buildPreview($type, $company, $records);

        if (! $preview['can_export'] && $onlyValid) {
            return [
                'success' => false,
                'message' => 'Không thể xuất: công ty hoặc nhân viên còn lỗi hồ sơ.',
                'preview' => $preview,
            ];
        }

        if ($onlyValid) {
            if ($type === 'tk1') {
                $validDepIds = collect($preview['lines'])
                    ->where('is_valid', true)
                    ->pluck('payload.dependent_id')
                    ->filter()
                    ->all();
                $records = $records->whereIn('id', $validDepIds);
            } else {
                $validIds = collect($preview['lines'])->where('is_valid', true)->pluck('employee_id')->filter()->all();
                $records = $records->whereIn('id', $validIds);
            }
        }

        if ($records->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Không có bản ghi hợp lệ để xuất.',
                'preview' => $preview,
            ];
        }

        $content = $this->export->generateContent($type, $company, $records, $format);
        $filename = $this->export->defaultFilename($type, $company, $format);
        $path = 'bhxh/declarations/'.Str::uuid().'_'.$filename;

        $disk = HrFileStorage::DISK;
        \Illuminate\Support\Facades\Storage::disk($disk)->put($path, $content);

        $declaration = DB::transaction(function () use (
            $type, $company, $from, $to, $format, $preview, $records, $path, $filename, $disk, $userId, $onlyValid
        ) {
            $declaration = BhxhDeclaration::create([
                'company_id' => $company->id,
                'declaration_type' => $type,
                'period' => $from?->format('Y-m'),
                'from_date' => $from,
                'to_date' => $to,
                'format' => $format,
                'record_count' => $records->count(),
                'error_count' => $preview['error_count'],
                'status' => 'exported',
                'file_path' => $path,
                'file_name' => $filename,
                'file_disk' => $disk,
                'summary' => [
                    'valid_count' => $preview['valid_count'],
                    'total' => $preview['total'],
                ],
                'created_by' => $userId,
            ]);

            foreach ($preview['lines'] as $line) {
                if ($onlyValid && ! $line['is_valid']) {
                    continue;
                }
                $declaration->lines()->create([
                    'employee_id' => $line['employee_id'],
                    'line_no' => $line['line_no'],
                    'payload' => $line['payload'],
                    'validation_errors' => $line['validation_errors'],
                    'is_valid' => $line['is_valid'],
                ]);
            }

            return $declaration;
        });

        return [
            'success' => true,
            'declaration' => $declaration->load('company'),
            'preview' => $preview,
            'download_url' => "/api/v1/bhxh/declarations/{$declaration->id}/download",
        ];
    }
}
