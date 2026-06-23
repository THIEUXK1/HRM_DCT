<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyPolicyDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->hasRole('admin') || $user->can('company_policies.manage'));
    }

    public function rules(): array
    {
        $domain = $this->route('domain');
        $keys = config("company_policy_domains.domains.{$domain}.keys", []);

        return [
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:2000'],
            'effective_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function validatedSettings(): array
    {
        $domain = $this->route('domain');
        $allowed = config("company_policy_domains.domains.{$domain}.keys", []);
        $settings = $this->validated()['settings'] ?? [];

        return array_intersect_key($settings, array_flip($allowed));
    }
}
