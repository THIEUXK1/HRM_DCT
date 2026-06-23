<?php

namespace App\Http\Requests;

use App\Models\CompanyHoliday;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CompanyHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'holiday_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:holiday_date'],
            'is_paid' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = Carbon::parse($this->input('holiday_date'));
            $end = Carbon::parse($this->input('end_date') ?: $this->input('holiday_date'));
            $companyId = CompanyContext::id();
            $ignoreId = $this->route('companyHoliday')?->id ?? $this->route('company_holiday')?->id;

            $overlap = CompanyHoliday::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where(function ($q) use ($start, $end) {
                    $q->where('holiday_date', '<=', $end->toDateString())
                        ->whereRaw('COALESCE(end_date, holiday_date) >= ?', [$start->toDateString()]);
                })
                ->exists();

            if ($overlap) {
                $validator->errors()->add('holiday_date', 'Khoảng nghỉ lễ trùng với lịch đã đăng ký.');
            }
        });
    }
}
