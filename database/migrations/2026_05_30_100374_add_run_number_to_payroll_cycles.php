<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cho phép nhiều bảng lương cùng tháng (lần tính 1, 2…) — bản đã khóa giữ nguyên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_cycles', function (Blueprint $table) {
            $table->unsignedSmallInteger('run_number')->default(1)->after('period');
            $table->string('label', 120)->nullable()->after('run_number');
            $table->text('revision_note')->nullable()->after('label');
        });

        Schema::table('payroll_cycles', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'period']);
        });

        Schema::table('payroll_cycles', function (Blueprint $table) {
            $table->unique(['company_id', 'period', 'run_number'], 'payroll_cycles_company_period_run_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_cycles', function (Blueprint $table) {
            $table->dropUnique('payroll_cycles_company_period_run_unique');
        });

        Schema::table('payroll_cycles', function (Blueprint $table) {
            $table->unique(['company_id', 'period']);
            $table->dropColumn(['run_number', 'label', 'revision_note']);
        });
    }
};
