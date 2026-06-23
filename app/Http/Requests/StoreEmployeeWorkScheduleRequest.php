<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) (\App\Support\CompanyContext::id());

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'work_schedule_group_id' => [
                'required',
                'integer',
                Rule::exists('work_schedule_groups', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'work_schedule_pattern_id' => [
                'required',
                'integer',
                Rule::exists('work_schedule_patterns', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'weekend_swap_enabled' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
