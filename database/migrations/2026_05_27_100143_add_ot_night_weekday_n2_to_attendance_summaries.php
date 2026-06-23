<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tách OT đêm ngày thường thành N1 (200%) và N2 (210%).
 *
 * N1: làm đêm OT không có TC ngày trước đó — 150% + 30% + 20%×100% = 200%
 * N2: làm đêm OT sau khi đã có TC ngày trong cùng ngày — 150% + 30% + 20%×150% = 210%
 * (Nghị định 145/2020/NĐ-CP Điều 107)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            // N2 riêng; N1 = ot_night_weekday_hours - ot_night_weekday_n2_hours
            $table->decimal('ot_night_weekday_n2_hours', 8, 2)->default(0)->after('ot_night_weekday_hours');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->dropColumn('ot_night_weekday_n2_hours');
        });
    }
};
