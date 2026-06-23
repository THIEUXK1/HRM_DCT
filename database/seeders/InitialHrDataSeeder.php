<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\Position;
use Illuminate\Database\Seeder;

class InitialHrDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['code' => 'COMP-001'],
            [
                'name' => 'HRM Global',
                'tax_code' => 'HRM2026001',
                'social_insurance_unit_code' => 'DV-HRM-001',
                'social_insurance_agency' => 'BHXH TP Hà Nội',
                'legal_representative' => 'Giám đốc HRM Global',
                'address' => '123 Main Street, Hanoi',
                'phone' => '+84 24 1234 5678',
                'email' => 'contact@hrmglobal.local',
                'is_active' => true,
            ]
        );

        $branch = Branch::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'BR-001'],
            [
                'name' => 'Hanoi Headquarters',
                'address' => 'Floor 10, Tech Tower, Hanoi',
                'is_active' => true,
            ]
        );

        $department = Department::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'DEP-HR'],
            [
                'name' => 'Human Resources',
                'manager_id' => null,
                'is_active' => true,
            ]
        );

        $position = Position::firstOrCreate(
            ['department_id' => $department->id, 'code' => 'POS-HR-MGR'],
            [
                'name' => 'HR Manager',
                'level' => 'Senior',
                'job_description' => 'Manage HR operations, recruitment, and policies.',
                'is_active' => true,
            ]
        );

        $employee = Employee::firstOrCreate(
            ['employee_code' => 'EMP-001'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'position_id' => $position->id,
                'first_name' => 'Nguyễn',
                'last_name' => 'Văn An',
                'full_name' => 'Nguyễn Văn An',
                'email' => 'nguyen.an@hrmglobal.local',
                'phone' => '0912345678',
                'gender' => 'male',
                'date_of_birth' => '1990-05-15',
                'place_of_birth' => 'Hà Nội',
                'origin_place' => 'Hà Nam',
                'hire_date' => now()->toDateString(),
                'official_start_date' => now()->addMonths(2)->toDateString(),
                'employment_status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'Hà Nội - Trụ sở chính',
                'work_email' => 'nguyen.an@hrmglobal.local',
                'work_phone' => '0912345678',
                'national_id' => '001090015234',
                'id_card_type' => 'cccd',
                'id_card_issue_date' => '2021-01-15',
                'id_card_issue_place' => 'Cục Cảnh sát QLHC về TTXH',
                'tax_code' => '8012345678',
                'social_insurance_number' => '0123456789',
                'health_insurance_card' => 'DN1234567890',
                'bhxh_start_date' => now()->toDateString(),
                'insurance_salary' => 15_000_000,
                'pit_dependents_count' => 1,
                'bank_account' => '1234567890',
                'bank_account_name' => 'NGUYEN VAN AN',
                'bank_name' => 'Vietcombank',
                'bank_branch' => 'CN Hà Nội',
                'permanent_address' => 'Số 10 Nguyễn Trãi, Thanh Xuân, Hà Nội',
                'province' => 'Hà Nội',
                'district' => 'Thanh Xuân',
                'nationality' => 'VN',
                'address' => 'Số 10 Nguyễn Trãi, Thanh Xuân, Hà Nội',
                'city' => 'Hà Nội',
                'country' => 'Vietnam',
                'note' => 'HR manager — dữ liệu mẫu theo chuẩn VN.',
                'is_active' => true,
            ]
        );

        $employee->profile()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'marital_status' => 'married',
                'education_level' => 'university',
                'education_institution' => 'Đại học Kinh tế Quốc dân',
                'graduation_year' => 2012,
                'major' => 'Quản trị nhân lực',
                'emergency_contact_name' => 'Nguyễn Thị B',
                'emergency_contact_phone' => '0987654321',
                'emergency_contact_relationship' => 'Vợ',
                'military_service_status' => 'completed',
            ]
        );

        $employee->dependents()->firstOrCreate(
            ['full_name' => 'Nguyễn Minh Châu', 'relationship' => 'child'],
            [
                'date_of_birth' => '2018-03-20',
                'id_card_number' => '001218012345',
                'tax_dependent_code' => 'NPT-001',
                'is_active' => true,
            ]
        );

        EmploymentContract::firstOrCreate(
            ['contract_number' => 'CTR-001'],
            [
                'employee_id' => $employee->id,
                'contract_type' => 'indefinite',
                'job_title_on_contract' => 'HR Manager',
                'work_location' => 'Hà Nội - Trụ sở chính',
                'start_date' => now()->toDateString(),
                'signed_date' => now()->toDateString(),
                'end_date' => null,
                'probation_months' => 2,
                'probation_salary' => 12_000_000,
                'salary_base' => 15000000,
                'insurance_salary' => 15_000_000,
                'salary_currency' => 'VND',
                'working_hours' => 'full_time_48',
                'work_schedule' => 'Thứ 2 – Thứ 6, 08:30–17:30',
                'signed_by_employer' => 'Giám đốc HRM Global',
                'signed_by_employee' => 'Nguyễn Văn An',
                'status' => 'active',
            ]
        );
    }
}
