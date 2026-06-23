<?php

namespace Database\Seeders;

use App\Models\AttendanceDevice;
use App\Models\Company;
use App\Models\LeaveType;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\SalaryComponent;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Services\Hr\JobLevelCatalogService;
use Illuminate\Database\Seeder;

class HcmPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['code' => 'TAPDOAN-HRM'],
            ['name' => 'Tập đoàn HRM Demo', 'is_active' => true]
        );

        Company::query()->whereNull('tenant_id')->update(['tenant_id' => $tenant->id]);

        $company = Company::first();
        if (! $company) {
            return;
        }

        foreach (config('hr_vn.work_shift_presets', []) as $preset) {
            WorkShift::updateOrCreate(
                ['company_id' => $company->id, 'code' => $preset['code']],
                array_merge($preset, ['is_active' => true]),
            );
        }

        app(JobLevelCatalogService::class)->syncStandardGrades($company->id, deactivateLegacy: true);

        $this->syncLeaveTypesForCompany($company->id);

        foreach ([
            ['code' => 'BASE', 'name' => 'Lương cơ bản', 'type' => 'earning'],
            ['code' => 'OT', 'name' => 'Tăng ca', 'type' => 'earning'],
            ['code' => 'BHXH', 'name' => 'BHXH NLĐ', 'type' => 'deduction'],
            ['code' => 'PIT', 'name' => 'Thuế TNCN', 'type' => 'deduction'],
        ] as $component) {
            SalaryComponent::firstOrCreate(
                ['company_id' => $company->id, 'code' => $component['code']],
                [
                    'name' => $component['name'],
                    'type' => $component['type'],
                    'is_taxable' => $component['type'] === 'earning',
                ]
            );
        }

        AttendanceDevice::firstOrCreate(
            ['code' => 'DEVICE-001'],
            [
                'company_id' => $company->id,
                'name' => 'Máy chấm công cổng chính',
                'vendor' => 'Generic',
                'import_format' => 'csv_generic',
            ]
        );

        $template = OnboardingTemplate::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Onboarding mặc định'],
            ['is_active' => true]
        );

        $tasks = [
            'Thu thập CCCD và tài khoản ngân hàng',
            'Ký hợp đồng lao động',
            'Tạo email và tài khoản HRM',
            'Cấu hình chấm công',
            'Gán khóa đào tạo hội nhập',
            'Phân công buddy / người hướng dẫn',
        ];

        foreach ($tasks as $i => $title) {
            OnboardingTask::firstOrCreate(
                [
                    'onboarding_template_id' => $template->id,
                    'title' => $title,
                ],
                [
                    'category' => 'onboarding',
                    'sort_order' => $i + 1,
                ]
            );
        }
    }

    /** Đồng bộ danh mục loại nghỉ chuẩn VN cho một công ty. */
    public function syncLeaveTypesForCompany(int $companyId): void
    {
        foreach (config('hr_vn.leave_types', []) as $def) {
            LeaveType::updateOrCreate(
                ['company_id' => $companyId, 'code' => $def['code']],
                [
                    'name' => $def['name'],
                    'is_paid' => (bool) ($def['is_paid'] ?? true),
                    'payroll_category' => $def['payroll_category'] ?? ((($def['is_paid'] ?? true) ? 'company_paid' : 'company_unpaid')),
                    'day_count_mode' => $def['day_count_mode'] ?? 'workday',
                    'cell_symbol' => $def['cell_symbol'] ?? null,
                    'legal_reference' => $def['legal_reference'] ?? null,
                    'description' => $def['description'] ?? null,
                    'sort_order' => (int) ($def['sort_order'] ?? 0),
                    'requires_approval' => true,
                ]
            );
        }
    }

    public function syncPayrollBonusTypesForCompany(int $companyId): void
    {
        foreach (config('leave_payroll_vn.bonus_types', []) as $def) {
            \App\Models\PayrollBonusType::updateOrCreate(
                ['company_id' => $companyId, 'code' => $def['code']],
                [
                    'name' => $def['name'],
                    'category' => $def['category'] ?? 'adhoc',
                    'breakdown_key' => $def['breakdown_key'] ?? null,
                    'taxable' => $def['taxable'] ?? true,
                    'counts_in_gross' => $def['counts_in_gross'] ?? true,
                    'calculation_mode' => $def['calculation_mode'] ?? 'manual',
                    'default_rate' => $def['default_rate'] ?? null,
                    'default_amount' => $def['default_amount'] ?? null,
                    'legal_reference' => $def['legal_reference'] ?? null,
                    'sort_order' => (int) ($def['sort_order'] ?? 0),
                    'is_active' => true,
                ]
            );
        }
    }
}
