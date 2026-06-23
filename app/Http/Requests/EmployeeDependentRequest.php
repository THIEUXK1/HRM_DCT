<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeDependentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', Rule::in(array_keys(config('hr_vn.dependent_relationships')))],
            'date_of_birth' => ['nullable', 'date'],
            'id_card_number' => ['nullable', 'string', 'max:20'],
            'tax_dependent_code' => ['nullable', 'string', 'max:50'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string'],
        ];
    }
}
