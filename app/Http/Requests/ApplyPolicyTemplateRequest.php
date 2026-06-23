<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyPolicyTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->hasRole('admin') || $user->can('policy_templates.apply'));
    }

    public function rules(): array
    {
        $codes = array_keys(config('company_policy_templates', []));

        return [
            'template_code' => ['required', 'string', Rule::in($codes)],
            'overwrite' => ['sometimes', 'boolean'],
        ];
    }
}
