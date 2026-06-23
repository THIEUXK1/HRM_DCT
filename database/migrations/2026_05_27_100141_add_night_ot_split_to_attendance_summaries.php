<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            // OT đêm tách theo loại ngày — Điều 106 + 107 BLLĐ 2019
            $table->decimal('night_weekday_hours', 8, 2)->default(0)->after('night_hours');
            $table->decimal('night_weekend_hours', 8, 2)->default(0)->after('night_weekday_hours');
            $table->decimal('night_holiday_hours', 8, 2)->default(0)->after('night_weekend_hours');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->dropColumn(['night_weekday_hours', 'night_weekend_hours', 'night_holiday_hours']);
        });
    }
};
