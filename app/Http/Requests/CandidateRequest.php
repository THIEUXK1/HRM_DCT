<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|exists:tenants,id',
            'company_id' => 'required|exists:companies,id',
            'job_post_id' => 'nullable|exists:job_posts,id',
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'source' => 'nullable|string|max:100',
            'stage' => 'nullable|string|max:50',
            'expected_salary' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
