<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phân loại nghỉ có lương / không lương theo BLLĐ 2019 (Điều 113–115, 139–141).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->string('cell_symbol', 8)->nullable()->after('is_paid')
                ->comment('Ký hiệu bảng công: P, KL, Ô, TS…');
            $table->string('legal_reference')->nullable()->after('cell_symbol');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('legal_reference');
        });

        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->decimal('paid_leave_days', 5, 2)->default(0)->after('leave_days');
            $table->decimal('unpaid_leave_days', 5, 2)->default(0)->after('paid_leave_days');
            $table->decimal('probation_paid_leave_days', 5, 2)->default(0)->after('unpaid_leave_days');
            $table->decimal('official_paid_leave_days', 5, 2)->default(0)->after('probation_paid_leave_days');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['cell_symbol', 'legal_reference', 'sort_order']);
        });

        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'paid_leave_days',
                'unpaid_leave_days',
                'probation_paid_leave_days',
                'official_paid_leave_days',
            ]);
        });
    }
};
