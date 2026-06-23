<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Tạo dữ liệu demo tập đoàn đa công ty:
 *   - Tenant: Tập đoàn HRM Demo
 *   - Công ty 1: HRM Global (trụ sở Hà Nội) — đã có từ InitialHrDataSeeder
 *   - Công ty 2: HRM Miền Nam (nhà máy TP.HCM)
 *   - Công ty 3: HRM Miền Trung (nhà máy Đà Nẵng)
 */
class MultiCompanyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['code' => 'TAPDOAN-HRM'],
            ['name' => 'Tập đoàn HRM Demo', 'is_active' => true]
        );

        // Gán tenant cho công ty đã có
        Company::query()->whereNull('tenant_id')->update(['tenant_id' => $tenant->id]);

        // --- Công ty 2: nhà máy TP.HCM ---
        $company2 = Company::firstOrCreate(
            ['code' => 'COMP-SGN'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'HRM Miền Nam',
                'tax_code' => 'HRM2026002',
                'social_insurance_unit_code' => 'DV-HRM-SGN',
                'social_insurance_agency' => 'BHXH TP Hồ Chí Minh',
                'legal_representative' => 'Giám đốc HRM Miền Nam',
                'address' => '456 Nguyễn Văn Linh, Quận 7, TP.HCM',
                'phone' => '+84 28 9876 5432',
                'email' => 'contact@hrmsouth.local',
                'is_active' => true,
            ]
        );

        $branch2 = Branch::firstOrCreate(
            ['company_id' => $company2->id, 'code' => 'BR-SGN-01'],
            [
                'name' => 'Nhà máy Bình Dương',
                'address' => 'KCN Sóng Thần, Bình Dương',
                'is_active' => true,
            ]
        );

        $dept2 = Department::firstOrCreate(
            ['branch_id' => $branch2->id, 'code' => 'DEP-SX-SGN'],
            ['name' => 'Phòng Sản xuất', 'is_active' => true]
        );

        $pos2 = Position::firstOrCreate(
            ['department_id' => $dept2->id, 'code' => 'POS-SX-TRG'],
            ['name' => 'Trưởng dây chuyền', 'level' => 'Middle', 'is_active' => true]
        );

        $emp2 = Employee::firstOrCreate(
            ['employee_code' => 'SGN-001'],
            [
                'company_id' => $company2->id,
                'branch_id' => $branch2->id,
                'department_id' => $dept2->id,
                'position_id' => $pos2->id,
                'first_name' => 'Trần',
                'last_name' => 'Thị Bích',
                'full_name' => 'Trần Thị Bích',
                'email' => 'tran.bich@hrmsouth.local',
                'phone' => '0908765432',
                'gender' => 'female',
                'date_of_birth' => '1992-07-20',
                'hire_date' => now()->subYear()->toDateString(),
                'employment_status' => 'active',
                'employment_type' => 'full_time',
                'is_active' => true,
            ]
        );

        // --- Công ty 3: nhà máy Đà Nẵng ---
        $company3 = Company::firstOrCreate(
            ['code' => 'COMP-DNG'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'HRM Miền Trung',
                'tax_code' => 'HRM2026003',
                'social_insurance_unit_code' => 'DV-HRM-DNG',
                'social_insurance_agency' => 'BHXH TP Đà Nẵng',
                'legal_representative' => 'Giám đốc HRM Miền Trung',
                'address' => '789 Nguyễn Văn Linh, Đà Nẵng',
                'phone' => '+84 236 5678 910',
                'email' => 'contact@hrmcentral.local',
                'is_active' => true,
            ]
        );

        $branch3 = Branch::firstOrCreate(
            ['company_id' => $company3->id, 'code' => 'BR-DNG-01'],
            [
                'name' => 'Nhà máy Đà Nẵng',
                'address' => 'KCN Hòa Khánh, Đà Nẵng',
                'is_active' => true,
            ]
        );

        $dept3 = Department::firstOrCreate(
            ['branch_id' => $branch3->id, 'code' => 'DEP-HR-DNG'],
            ['name' => 'Phòng Nhân sự', 'is_active' => true]
        );

        $pos3 = Position::firstOrCreate(
            ['department_id' => $dept3->id, 'code' => 'POS-HR-DNG'],
            ['name' => 'HR Executive', 'level' => 'Junior', 'is_active' => true]
        );

        Employee::firstOrCreate(
            ['employee_code' => 'DNG-001'],
            [
                'company_id' => $company3->id,
                'branch_id' => $branch3->id,
                'department_id' => $dept3->id,
                'position_id' => $pos3->id,
                'first_name' => 'Lê',
                'last_name' => 'Văn Cường',
                'full_name' => 'Lê Văn Cường',
                'email' => 'le.cuong@hrmcentral.local',
                'phone' => '0935987654',
                'gender' => 'male',
                'date_of_birth' => '1994-03-10',
                'hire_date' => now()->subMonths(6)->toDateString(),
                'employment_status' => 'active',
                'employment_type' => 'full_time',
                'is_active' => true,
            ]
        );

        // --- Tài khoản người dùng demo ---
        Role::firstOrCreate(['name' => 'hr_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);

        // HR Manager nhà máy Bình Dương — chỉ quản lý Công ty 2
        $hrSouth = User::firstOrCreate(
            ['email' => 'hr.south@hrmsouth.local'],
            [
                'tenant_id' => $tenant->id,
                'employee_id' => $emp2->id,
                'default_company_id' => $company2->id,
                'name' => 'HR Miền Nam',
                'password' => Hash::make('Hr@South2026'),
            ]
        );
        $hrSouth->assignRole('hr_manager');
        $hrSouth->companies()->syncWithoutDetaching([$company2->id]);
        if (! $hrSouth->api_token) {
            $hrSouth->forceFill(['api_token' => 'hr-south-token'])->save();
        }

        // Tài khoản tập đoàn — có thể xem tất cả công ty
        $admin = User::where('email', 'admin@example.com')->first();
        if ($admin) {
            $admin->update(['tenant_id' => $tenant->id]);
            $admin->companies()->syncWithoutDetaching([
                $company2->id,
                $company3->id,
            ]);
        }
    }
}
