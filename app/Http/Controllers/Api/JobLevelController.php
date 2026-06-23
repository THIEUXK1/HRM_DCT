<?php

namespace App\Http\Controllers\Api;

use App\Models\JobLevel;
use App\Services\Hr\JobLevelCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobLevelController extends ApiController
{
    public function index(): JsonResponse
    {
        $levels = JobLevel::orderBy('rank')->orderBy('grade')->orderBy('band')->get();

        return $this->success([
            'levels' => $levels,
            'catalog' => [
                'grades' => JobLevelCatalogService::gradeDefinitions(),
                'bands' => JobLevelCatalogService::bands(),
                'categories' => config('hr_vn.job_categories', []),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $companyId = \App\Support\CompanyContext::id();

        if (JobLevel::where('company_id', $companyId)->where('code', $data['code'])->exists()) {
            return $this->error('Mã cấp bậc này đã tồn tại trong công ty.', 422);
        }

        $level = JobLevel::create($data);

        return $this->success($level, 201);
    }

    public function show(JobLevel $jobLevel): JsonResponse
    {
        return $this->success($jobLevel);
    }

    public function update(Request $request, JobLevel $jobLevel): JsonResponse
    {
        $data = $this->validated($request);
        $companyId = \App\Support\CompanyContext::id();

        if (JobLevel::where('company_id', $companyId)
            ->where('code', $data['code'])
            ->where('id', '!=', $jobLevel->id)
            ->exists()) {
            return $this->error('Mã cấp bậc này đã tồn tại trong công ty.', 422);
        }

        $jobLevel->update($data);

        return $this->success($jobLevel);
    }

    public function destroy(JobLevel $jobLevel): JsonResponse
    {
        $jobLevel->delete();

        return $this->noContent();
    }

    public function seedStandard(JobLevelCatalogService $catalog): JsonResponse
    {
        $companyId = \App\Support\CompanyContext::id();
        $result = $catalog->syncStandardGrades($companyId);

        return $this->success([
            'message' => 'Đã áp dụng thang cấp bậc O1–O7 (band A–D).',
            ...$result,
            'levels' => JobLevel::where('company_id', $companyId)->where('is_active', true)->orderBy('rank')->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $categories = array_keys(config('hr_vn.job_categories', []));

        return $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'grade' => ['nullable', 'string', 'max:8', 'regex:/^O[1-7]$/'],
            'band' => ['nullable', 'string', 'max:2', 'in:A,B,C,D'],
            'category' => ['nullable', 'string', 'max:32', 'in:'.implode(',', $categories)],
            'name' => ['required', 'string', 'max:255'],
            'rank' => ['required', 'integer', 'min:1'],
            'basic_salary_range_min' => ['nullable', 'integer', 'min:0'],
            'basic_salary_range_max' => ['nullable', 'integer', 'min:0', 'gte:basic_salary_range_min'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
