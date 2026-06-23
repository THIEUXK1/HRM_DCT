<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2a: OT ca ngày/đêm × loại ngày + nghỉ theo leave_type (JSON breakdown).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->json('attendance_breakdown')->nullable()->after('ot_monthly_cap_exceeded')
                ->comment('OT grid P–X, leave_by_type, công mở rộng BestPacific');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->dropColumn('attendance_breakdown');
        });
    }
};
