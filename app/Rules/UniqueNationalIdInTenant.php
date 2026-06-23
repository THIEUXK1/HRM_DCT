<?php

namespace App\Rules;

use App\Models\Company;
use App\Models\Employee;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueNationalIdInTenant implements ValidationRule
{
    public function __construct(
        protected ?int $excludeEmployeeId = null,
        protected ?int $companyId = null
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $companyId = $this->companyId ?? request()->input('company_id');
        $tenantId = Company::where('id', $companyId)->value('tenant_id');

        if (! $tenantId) {
            return;
        }

        $exists = Employee::query()
            ->where('national_id', $value)
            ->when($this->excludeEmployeeId, fn ($q) => $q->where('id', '!=', $this->excludeEmployeeId))
            ->whereHas('company', fn ($q) => $q->where('tenant_id', $tenantId))
            ->exists();

        if ($exists) {
            $fail('Số CCCD/CMND đã tồn tại trong tập đoàn.');
        }
    }
}
