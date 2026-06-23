<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'marital_status' => ['nullable', Rule::in(array_keys(config('hr_vn.marital_statuses')))],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'spouse_name' => ['nullable', 'string', 'max:255'],
            'spouse_id_number' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'education_level' => ['nullable', Rule::in(array_keys(config('hr_vn.education_levels')))],
            'education_institution' => ['nullable', 'string', 'max:255'],
            'graduation_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'major' => ['nullable', 'string', 'max:255'],
            'professional_certificate' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
            'skills' => ['nullable', 'string'],
            'certificate_summary' => ['nullable', 'string'],
            'military_service_status' => ['nullable', Rule::in(array_keys(config('hr_vn.military_service_statuses')))],
            'disability_level' => ['nullable', 'string', 'max:50'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'passport_expiry' => ['nullable', 'date'],
            'work_permit_number' => ['nullable', 'string', 'max:100'],
            'work_permit_expiry' => ['nullable', 'date'],
            'hobby'         => ['nullable', 'string'],
            'biometric_id'  => ['nullable', 'string', 'max:50'],
            'card_number'   => ['nullable', 'string', 'max:50'],
        ];
    }
}
