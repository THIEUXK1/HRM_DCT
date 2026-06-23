<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollFormulaParametersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parameters' => ['required', 'array'],
        ];
    }

    /** @return array<string, mixed> */
    public function parameterValues(): array
    {
        return $this->validated('parameters', []);
    }
}
