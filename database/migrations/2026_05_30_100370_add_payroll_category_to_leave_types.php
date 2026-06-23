<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phân loại nghỉ: có lương công ty / không lương / hưởng BHXH (BLLĐ 2019).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->string('payroll_category', 32)->default('company_paid')->after('is_paid')
                ->comment('company_paid | company_unpaid | bhxh_benefit');
            $table->boolean('affects_diligence')->nullable()->after('payroll_category')
                ->comment('null = theo quy tắc mặc định của nhóm');
            $table->text('description')->nullable()->after('legal_reference');
        });

        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->decimal('bhxh_leave_days', 5, 2)->default(0)->after('unpaid_leave_days');
            $table->decimal('probation_bhxh_leave_days', 5, 2)->default(0)->after('bhxh_leave_days');
            $table->decimal('official_bhxh_leave_days', 5, 2)->default(0)->after('probation_bhxh_leave_days');
        });

        // Backfill: loại BHXH đã có
        DB::table('leave_types')->whereIn('code', ['OM', 'TS', 'CON_OM'])->update([
            'payroll_category' => 'bhxh_benefit',
        ]);
        DB::table('leave_types')->where('is_paid', false)
            ->whereNotIn('code', ['OM', 'TS', 'CON_OM'])
            ->update(['payroll_category' => 'company_unpaid']);
        DB::table('leave_types')->where('is_paid', true)->update([
            'payroll_category' => 'company_paid',
        ]);
    }

    public function down(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->dropColumn(['bhxh_leave_days', 'probation_bhxh_leave_days', 'official_bhxh_leave_days']);
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['payroll_category', 'affects_diligence', 'description']);
        });
    }
};
