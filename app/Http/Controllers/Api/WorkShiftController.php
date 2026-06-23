<?php

namespace App\Http\Controllers\Api;

use App\Models\WorkShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkShiftController extends ApiController
{
    public function index(): JsonResponse
    {
        $shifts = WorkShift::orderBy('code')->get();

        return $this->success($shifts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $companyId = \App\Support\CompanyContext::id();

        if (WorkShift::where('company_id', $companyId)->where('code', $data['code'])->exists()) {
            return $this->error('Mã ca làm việc đã tồn tại trong công ty này.', 422);
        }

        $shift = WorkShift::create($data);

        return $this->success($shift, 201);
    }

    public function show(WorkShift $workShift): JsonResponse
    {
        return $this->success($workShift);
    }

    public function update(Request $request, WorkShift $workShift): JsonResponse
    {
        $data = $this->validated($request);
        $companyId = \App\Support\CompanyContext::id();

        if (WorkShift::where('company_id', $companyId)
            ->where('code', $data['code'])
            ->where('id', '!=', $workShift->id)
            ->exists()) {
            return $this->error('Mã ca làm việc đã tồn tại trong công ty này.', 422);
        }

        $workShift->update($data);

        return $this->success($workShift);
    }

    public function destroy(WorkShift $workShift): JsonResponse
    {
        $workShift->delete();

        return $this->noContent();
    }

    public function seedPresets(): JsonResponse
    {
        $companyId = \App\Support\CompanyContext::id();
        $created = 0;
        $updated = 0;

        foreach (config('hr_vn.work_shift_presets', []) as $preset) {
            $shift = WorkShift::updateOrCreate(
                ['company_id' => $companyId, 'code' => $preset['code']],
                array_merge($preset, ['is_active' => true]),
            );
            $shift->wasRecentlyCreated ? $created++ : $updated++;
        }

        return $this->success([
            'message' => 'Đã thiết lập ca hành chính và ca đêm chuẩn BLLĐ 2019.',
            'created' => $created,
            'updated' => $updated,
            'shifts' => WorkShift::where('company_id', $companyId)->orderBy('code')->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'end_time' => ['required', 'date_format:H:i,H:i:s'],
            'break_minutes' => ['required', 'integer', 'min:0'],
            'is_night_shift' => ['sometimes', 'boolean'],
            'crosses_midnight' => ['sometimes', 'boolean'],
            'standard_hours' => ['sometimes', 'numeric', 'min:1', 'max:12'],
            'legal_reference' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (strlen($data['start_time']) === 5) {
            $data['start_time'] .= ':00';
        }
        if (strlen($data['end_time']) === 5) {
            $data['end_time'] .= ':00';
        }

        $data['crosses_midnight'] = $data['crosses_midnight']
            ?? ($data['end_time'] < $data['start_time']);

        $startHour = (int) substr($data['start_time'], 0, 2);
        $endHour = (int) substr($data['end_time'], 0, 2);
        $data['is_night_shift'] = $data['is_night_shift']
            ?? ($startHour >= 22 || $startHour < 6 || $endHour >= 22 || ($data['crosses_midnight'] && $endHour <= 6));

        $minBreak = ($data['is_night_shift'] ?? false) ? 45 : 30;
        if ($data['break_minutes'] < $minBreak) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'break_minutes' => ["Nghỉ giữa ca tối thiểu {$minBreak} phút theo BLLĐ 2019 (Điều 108)."],
            ]);
        }

        return $data;
    }
}
