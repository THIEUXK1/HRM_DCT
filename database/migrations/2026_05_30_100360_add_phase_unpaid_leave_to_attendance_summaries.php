<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tách nghỉ không lương theo giai đoạn TV/CT (cùng kỳ chuyển giai đoạn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->decimal('probation_unpaid_leave_days', 5, 2)->default(0)->after('official_paid_leave_days');
            $table->decimal('official_unpaid_leave_days', 5, 2)->default(0)->after('probation_unpaid_leave_days');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'probation_unpaid_leave_days',
                'official_unpaid_leave_days',
            ]);
        });
    }
};
