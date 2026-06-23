<?php

namespace Database\Seeders;

use App\Models\BenefitEnrollment;
use App\Models\BenefitPlan;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class BenefitSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) return;

        $plans = [
            [
                'code'                => 'HEALTH-BASIC',
                'name'                => 'Bảo hiểm sức khỏe (Gói cơ bản)',
                'category'            => 'health',
                'description'         => 'Gói bảo hiểm sức khỏe cơ bản cho toàn bộ nhân viên chính thức. Chi trả nội trú tối đa 50 triệu/năm.',
                'value_type'          => 'fixed',
                'value'               => 1_200_000,
                'eligible_after_days' => 60,
                'is_taxable'          => false,
            ],
            [
                'code'                => 'HEALTH-PREMIUM',
                'name'                => 'Bảo hiểm sức khỏe (Gói cao cấp)',
                'category'            => 'health',
                'description'         => 'Dành cho cấp quản lý trở lên. Chi trả nội trú 150 triệu/năm, ngoại trú 10 triệu/năm.',
                'value_type'          => 'fixed',
                'value'               => 3_000_000,
                'eligible_after_days' => 0,
                'is_taxable'          => false,
            ],
            [
                'code'                => 'PHONE',
                'name'                => 'Phụ cấp điện thoại',
                'category'            => 'phone',
                'description'         => 'Hỗ trợ cước phí điện thoại hàng tháng.',
                'value_type'          => 'fixed',
                'value'               => 500_000,
                'eligible_after_days' => 0,
                'is_taxable'          => false,
            ],
            [
                'code'                => 'TRANSPORT',
                'name'                => 'Phụ cấp đi lại',
                'category'            => 'transport',
                'description'         => 'Hỗ trợ chi phí đi lại hàng tháng.',
                'value_type'          => 'fixed',
                'value'               => 800_000,
                'eligible_after_days' => 0,
                'is_taxable'          => false,
            ],
            [
                'code'                => 'MEAL',
                'name'                => 'Phụ cấp ăn uống',
                'category'            => 'meal',
                'description'         => 'Hỗ trợ ăn trưa theo ngày công thực tế.',
                'value_type'          => 'fixed',
                'value'               => 730_000,
                'eligible_after_days' => 0,
                'is_taxable'          => false,
            ],
            [
                'code'                => 'ACCIDENT',
                'name'                => 'Bảo hiểm tai nạn 24/7',
                'category'            => 'accident',
                'description'         => 'Bảo hiểm tai nạn cá nhân 24/7, mức bảo vệ 200 triệu.',
                'value_type'          => 'fixed',
                'value'               => 200_000,
                'eligible_after_days' => 30,
                'is_taxable'          => false,
            ],
            [
                'code'                => 'LAPTOP',
                'name'                => 'Trang bị laptop',
                'category'            => 'equipment',
                'description'         => 'Cấp phát laptop làm việc. Giá trị khấu hao hàng tháng.',
                'value_type'          => 'reimbursement',
                'value'               => 0,
                'eligible_after_days' => 0,
                'is_taxable'          => false,
            ],
            [
                'code'                => 'PERF-BONUS',
                'name'                => 'Thưởng hiệu suất (% lương)',
                'category'            => 'bonus',
                'description'         => 'Thưởng theo kết quả đánh giá KPI hàng quý. Tỷ lệ % lương cơ bản.',
                'value_type'          => 'percentage',
                'value'               => 10,
                'eligible_after_days' => 90,
                'is_taxable'          => true,
            ],
        ];

        foreach ($plans as $plan) {
            BenefitPlan::firstOrCreate(
                ['code' => $plan['code']],
                array_merge($plan, [
                    'company_id'  => $company->id,
                    'currency'    => 'VND',
                    'is_active'   => true,
                ])
            );
        }

        // Enroll a few employees as sample data
        $employees = Employee::where('company_id', $company->id)
            ->where('employment_status', 'active')
            ->take(5)
            ->get();

        $phonePlan  = BenefitPlan::where('code', 'PHONE')->first();
        $mealPlan   = BenefitPlan::where('code', 'MEAL')->first();
        $healthPlan = BenefitPlan::where('code', 'HEALTH-BASIC')->first();

        foreach ($employees as $emp) {
            foreach ([$phonePlan, $mealPlan, $healthPlan] as $plan) {
                if (! $plan) continue;
                BenefitEnrollment::firstOrCreate(
                    ['employee_id' => $emp->id, 'benefit_plan_id' => $plan->id],
                    [
                        'status'      => 'active',
                        'enrolled_at' => $emp->hire_date ?? now()->toDateString(),
                    ]
                );
            }
        }
    }
}
