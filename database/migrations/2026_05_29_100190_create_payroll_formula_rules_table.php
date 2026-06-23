<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Công thức lương do kế toán tự định nghĩa (thưởng NS, thôi việc, phụ cấp…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_formula_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('target_field', 64)
                ->comment('Tên khoản trong breakdown: performance_bonus, diligence_bonus_pay…');
            $table->string('apply_when')->default('all')
                ->comment('all|active|terminated_in_month|has_performance_score');
            $table->text('formula');
            $table->string('category')->default('earning');
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_formula_rules');
    }

    private function seedDefaults(): void
    {
        $now = now();
        $rules = [
            [
                'code' => 'DILIGENCE_BONUS',
                'name' => 'Thưởng chuyên cần',
                'target_field' => 'diligence_bonus_pay',
                'apply_when' => 'all',
                'formula' => '{diligence_bonus_amount}',
                'category' => 'earning',
                'sort_order' => 10,
                'description' => 'Lấy từ bảng công tháng (cấu hình Settings → Chấm công)',
            ],
            [
                'code' => 'PERFORMANCE_BONUS',
                'name' => 'Thưởng năng suất (KPI)',
                'target_field' => 'performance_bonus',
                'apply_when' => 'has_performance_score',
                'formula' => '{base_pay_total} * {performance_score} / 100 * {performance_bonus_rate}',
                'category' => 'earning',
                'sort_order' => 20,
                'description' => 'Điểm KPI × tỷ lệ thưởng × lương cơ bản tháng',
            ],
            [
                'code' => 'TERMINATED_LEAVE_PAYOUT',
                'name' => 'Thanh toán phép còn lại (thôi việc)',
                'target_field' => 'termination_leave_payout',
                'apply_when' => 'terminated_in_month',
                'formula' => '{unused_leave_days} * {daily_salary}',
                'category' => 'earning',
                'sort_order' => 30,
                'description' => 'NV nghỉ việc trong tháng — kế toán chỉnh số ngày phép còn lại',
            ],
        ];

        $settings = [
            'performance_bonus_enabled' => '1',
            'performance_bonus_rate' => '0.15',
            'termination_unused_leave_days_default' => '0',
        ];

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach ($rules as $rule) {
                DB::table('payroll_formula_rules')->insert(array_merge($rule, [
                    'company_id' => $companyId,
                    'is_taxable' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
            foreach ($settings as $key => $value) {
                DB::table('company_settings')->updateOrInsert(
                    ['company_id' => $companyId, 'key' => $key],
                    ['value' => $value, 'updated_at' => $now, 'created_at' => $now]
                );
            }
        }
    }
};
