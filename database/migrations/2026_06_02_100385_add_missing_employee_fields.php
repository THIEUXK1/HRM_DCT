<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // SỐ CMND — số chứng minh nhân dân cũ (trước khi đổi sang CCCD)
            $table->string('old_national_id')->nullable()->after('national_id');
            // Tháng giảm BHXH — tháng nghỉ đóng bảo hiểm xã hội
            $table->date('bhxh_stop_date')->nullable()->after('bhxh_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['old_national_id', 'bhxh_stop_date']);
        });
    }
};
