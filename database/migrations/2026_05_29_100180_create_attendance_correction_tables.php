<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đơn bù thẻ chấm công + cấu hình thưởng chuyên cần (admin tự chỉnh qua Settings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_correction_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->boolean('counts_as_forgot_punch')->default(false)
                ->comment('Đếm vào hạn mức quên chấm công/tháng (trừ thưởng chuyên cần)');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('attendance_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('correction_reason_id')->constrained('attendance_correction_reasons')->cascadeOnDelete();
            $table->date('work_date');
            $table->dateTime('requested_check_in_at')->nullable();
            $table->dateTime('requested_check_out_at')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
            $table->index(['company_id', 'status']);
        });

        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->unsignedSmallInteger('forgot_punch_count')->default(0)->after('official_paid_leave_days');
            $table->unsignedSmallInteger('correction_approved_count')->default(0)->after('forgot_punch_count');
            $table->boolean('diligence_bonus_eligible')->default(false)->after('correction_approved_count');
            $table->decimal('diligence_bonus_amount', 12, 0)->default(0)->after('diligence_bonus_eligible');
        });

        $this->seedDefaultsForExistingCompanies();
    }

    public function down(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'forgot_punch_count',
                'correction_approved_count',
                'diligence_bonus_eligible',
                'diligence_bonus_amount',
            ]);
        });

        Schema::dropIfExists('attendance_correction_requests');
        Schema::dropIfExists('attendance_correction_reasons');
    }

    private function seedDefaultsForExistingCompanies(): void
    {
        $now = now();
        $defaultReasons = [
            ['code' => 'TAC_DUONG', 'name' => 'Tắc đường', 'counts_as_forgot_punch' => false, 'sort_order' => 10],
            ['code' => 'LOI_MAY', 'name' => 'Lỗi máy chấm công', 'counts_as_forgot_punch' => false, 'sort_order' => 20],
            ['code' => 'QUEN', 'name' => 'Quên chấm công', 'counts_as_forgot_punch' => true, 'sort_order' => 30],
            ['code' => 'KHAC', 'name' => 'Nguyên nhân khác', 'counts_as_forgot_punch' => false, 'sort_order' => 40],
        ];

        $defaultSettings = [
            'diligence_bonus_enabled' => '1',
            'diligence_bonus_amount' => '500000',
            'diligence_min_attendance_rate' => '98',
            'diligence_max_late_count' => '1',
            'diligence_max_absent_days' => '0',
            'diligence_max_forgot_punch' => '2',
        ];

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach ($defaultReasons as $reason) {
                DB::table('attendance_correction_reasons')->insert(array_merge($reason, [
                    'company_id' => $companyId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }

            foreach ($defaultSettings as $key => $value) {
                DB::table('company_settings')->updateOrInsert(
                    ['company_id' => $companyId, 'key' => $key],
                    ['value' => $value, 'updated_at' => $now, 'created_at' => $now]
                );
            }
        }
    }
};
