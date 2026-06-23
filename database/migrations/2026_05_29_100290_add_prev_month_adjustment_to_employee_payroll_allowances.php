<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: điều chỉnh tháng trước (phiếu lương dòng 43).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_payroll_allowances', function (Blueprint $table) {
            $table->decimal('prev_month_adjustment', 15, 2)->default(0)->after('travel_eligible');
        });
    }

    public function down(): void
    {
        Schema::table('employee_payroll_allowances', function (Blueprint $table) {
            $table->dropColumn('prev_month_adjustment');
        });
    }
};
