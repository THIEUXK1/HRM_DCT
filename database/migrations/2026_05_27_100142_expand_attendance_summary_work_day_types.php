<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mở rộng attendance_summaries với đầy đủ phân loại ngày:
 *   - Công: ngày thường / cuối tuần / ngày lễ
 *   - Ca đêm thường quy: tách theo loại ngày (Điều 106 BLLĐ 2019)
 *   - OT đêm: đổi tên night_*_hours → ot_night_*_hours cho rõ nghĩa
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            // 1. Đổi tên cột OT đêm (thêm từ migration 100141) cho rõ nghĩa
            $table->renameColumn('night_weekday_hours', 'ot_night_weekday_hours');
            $table->renameColumn('night_weekend_hours', 'ot_night_weekend_hours');
            $table->renameColumn('night_holiday_hours', 'ot_night_holiday_hours');

            // 2. Công phân loại ngày (Điều 105, 107, 112 BLLĐ 2019)
            $table->decimal('work_weekday_days', 8, 2)->default(0)->after('official_work_days');
            $table->decimal('work_weekend_days', 8, 2)->default(0)->after('work_weekday_days');
            $table->decimal('work_holiday_days', 8, 2)->default(0)->after('work_weekend_days');

            // 3. Ca đêm thường quy (không phải OT) phân loại ngày — Điều 106 BLLĐ 2019
            $table->decimal('work_night_weekday_hours', 8, 2)->default(0)->after('night_hours');
            $table->decimal('work_night_weekend_hours', 8, 2)->default(0)->after('work_night_weekday_hours');
            $table->decimal('work_night_holiday_hours', 8, 2)->default(0)->after('work_night_weekend_hours');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->renameColumn('ot_night_weekday_hours', 'night_weekday_hours');
            $table->renameColumn('ot_night_weekend_hours', 'night_weekend_hours');
            $table->renameColumn('ot_night_holiday_hours', 'night_holiday_hours');
            $table->dropColumn([
                'work_weekday_days', 'work_weekend_days', 'work_holiday_days',
                'work_night_weekday_hours', 'work_night_weekend_hours', 'work_night_holiday_hours',
            ]);
        });
    }
};
