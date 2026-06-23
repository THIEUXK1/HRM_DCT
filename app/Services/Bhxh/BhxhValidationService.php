<?php

namespace App\Services\Bhxh;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDependent;
use Illuminate\Support\Collection;

class BhxhValidationService
{
    public function validateCompany(Company $company, string $type): array
    {
        $errors = [];
        $fields = config("bhxh_vn.required_fields.{$type}.company", []);

        foreach ($fields as $field) {
            if (empty($company->{$field})) {
                $errors[] = "Công ty thiếu: {$field}";
            }
        }

        return $errors;
    }

    public function validateEmployee(Employee $employee, string $type): array
    {
        $errors = [];
        $fields = config("bhxh_vn.required_fields.{$type}.employee", []);

        foreach ($fields as $field) {
            $value = $employee->{$field};
            if ($value === null || $value === '') {
                $errors[] = $this->fieldLabel($field);
            }
        }

        if ($type === 'd01' && $employee->national_id && ! preg_match('/^[0-9]{9,12}$/', $employee->national_id)) {
            $errors[] = 'CCCD không hợp lệ (9–12 số)';
        }

        if (in_array($type, ['d01', 'd02'], true) && $employee->insurance_salary) {
            $min = config('bhxh_vn.salary.min_base');
            $max = config('bhxh_vn.salary.max_base');
            if ($employee->insurance_salary < $min) {
                $errors[] = "Mức lương đóng dưới mức tối thiểu (".number_format($min).' VND)';
            }
            if ($employee->insurance_salary > $max) {
                $errors[] = "Mức lương đóng vượt trần BHXH (".number_format($max).' VND)';
            }
        }

        if ($type === 'd05' && ! $employee->termination_date) {
            $errors[] = 'Thiếu ngày nghỉ việc';
        }

        return $errors;
    }

    public function validateDependent(EmployeeDependent $dependent): array
    {
        $errors = [];
        $fields = config('bhxh_vn.required_fields.tk1.dependent', []);

        foreach ($fields as $field) {
            if (empty($dependent->{$field})) {
                $errors[] = $this->fieldLabel($field);
            }
        }

        if ($dependent->employee && empty($dependent->employee->tax_code)) {
            $errors[] = 'NLĐ thiếu MST cá nhân';
        }

        return $errors;
    }

    public function buildPreview(string $type, Company $company, Collection $records): array
    {
        $companyErrors = $this->validateCompany($company, $type);
        $lines = [];
        $lineNo = 1;

        foreach ($records as $record) {
            if ($type === 'tk1') {
                $empErrors = $record->employee
                    ? $this->validateEmployee($record->employee, 'tk1')
                    : [];
                $rowErrors = array_merge($empErrors, $this->validateDependent($record));
                $employeeId = $record->employee_id;
                $payload = [
                    'dependent_id' => $record->id,
                    'employee_code' => $record->employee?->employee_code,
                    'employee_name' => $record->employee?->full_name,
                    'dependent_name' => $record->full_name,
                    'relationship' => $record->relationship,
                ];
            } else {
                /** @var Employee $record */
                $rowErrors = $this->validateEmployee($record, $type);
                $employeeId = $record->id;
                $payload = [
                    'employee_code' => $record->employee_code,
                    'full_name' => $record->full_name,
                    'social_insurance_number' => $record->social_insurance_number,
                    'national_id' => $record->national_id,
                    'insurance_salary' => $record->insurance_salary,
                    'bhxh_start_date' => $record->bhxh_start_date?->format('Y-m-d'),
                    'termination_date' => $record->termination_date?->format('Y-m-d'),
                ];
            }

            $allErrors = array_merge($companyErrors, $rowErrors);
            $lines[] = [
                'line_no' => $lineNo++,
                'employee_id' => $employeeId,
                'payload' => $payload,
                'validation_errors' => $allErrors,
                'is_valid' => count($allErrors) === 0,
            ];
        }

        $validCount = collect($lines)->where('is_valid', true)->count();

        return [
            'company_errors' => $companyErrors,
            'lines' => $lines,
            'total' => count($lines),
            'valid_count' => $validCount,
            'error_count' => count($lines) - $validCount,
            'can_export' => count($companyErrors) === 0 && $validCount > 0,
        ];
    }

    protected function fieldLabel(string $field): string
    {
        return match ($field) {
            'full_name' => 'Thiếu họ tên',
            'national_id' => 'Thiếu số CCCD',
            'social_insurance_number' => 'Thiếu mã số BHXH',
            'date_of_birth' => 'Thiếu ngày sinh',
            'gender' => 'Thiếu giới tính',
            'insurance_salary' => 'Thiếu mức lương đóng BHXH',
            'bhxh_start_date' => 'Thiếu ngày tham gia BHXH',
            'termination_date' => 'Thiếu ngày nghỉ việc',
            'relationship' => 'Thiếu quan hệ NPT',
            default => "Thiếu {$field}",
        };
    }
}
