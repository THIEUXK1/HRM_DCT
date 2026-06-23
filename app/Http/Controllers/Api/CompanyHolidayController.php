<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CompanyHolidayRequest;
use App\Models\CompanyHoliday;
use App\Services\Attendance\VietnamHolidayService;
use Illuminate\Http\JsonResponse;

class CompanyHolidayController extends ApiController
{
    public function index(): JsonResponse
    {
        $holidays = CompanyHoliday::orderBy('holiday_date')->get();

        return $this->success($holidays);
    }

    public function store(CompanyHolidayRequest $request): JsonResponse
    {
        $data = $this->normalizePayload($request->validated());
        $holiday = CompanyHoliday::create($data);
        VietnamHolidayService::clearCache($holiday->company_id);

        return $this->success([
            'holiday' => $holiday,
            'notice' => 'Cần tổng hợp lại bảng công (Chấm công → Tổng hợp công) để ngày lễ có hiệu lực.',
        ], 201);
    }

    public function show(CompanyHoliday $companyHoliday): JsonResponse
    {
        return $this->success($companyHoliday);
    }

    public function update(CompanyHolidayRequest $request, CompanyHoliday $companyHoliday): JsonResponse
    {
        $companyHoliday->update($this->normalizePayload($request->validated()));
        VietnamHolidayService::clearCache($companyHoliday->company_id);

        return $this->success([
            'holiday' => $companyHoliday->fresh(),
            'notice' => 'Cần tổng hợp lại bảng công để thay đổi có hiệu lực.',
        ]);
    }

    public function destroy(CompanyHoliday $companyHoliday): JsonResponse
    {
        $companyId = $companyHoliday->company_id;
        $companyHoliday->delete();
        VietnamHolidayService::clearCache($companyId);

        return $this->noContent();
    }

    /** @param  array<string, mixed>  $data */
    private function normalizePayload(array $data): array
    {
        $data['end_date'] = $data['end_date'] ?? $data['holiday_date'];
        $data['is_paid'] = $data['is_paid'] ?? true;

        return $data;
    }
}
