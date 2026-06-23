<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkSchedulePatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) (\App\Support\CompanyContext::id());

        return [
            'work_schedule_group_id' => [
                'nullable',
                'integer',
                Rule::exists('work_schedule_groups', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'pattern_code' => ['required', Rule::in(['5D8H', '6D8H', 'CUSTOM'])],
            'hours_per_day' => ['required', 'numeric', 'min:1', 'max:12'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['integer', 'min:1', 'max:7'],
            'rest_days' => ['nullable', 'array'],
            'rest_days.*' => ['integer', 'min:1', 'max:7'],
            'allow_weekend_swap' => ['sometimes', 'boolean'],
            'allow_continuous' => ['sometimes', 'boolean'],
            'max_consecutive_work_days' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'swap_rest_day' => ['nullable', 'integer', 'min:1', 'max:7'],
            'swap_work_day' => ['nullable', 'integer', 'min:1', 'max:7'],
            'work_shift_id' => ['nullable', 'exists:work_shifts,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
