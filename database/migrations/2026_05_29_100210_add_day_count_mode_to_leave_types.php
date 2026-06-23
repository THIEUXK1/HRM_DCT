<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->string('day_count_mode', 16)->default('workday')->after('is_paid')
                ->comment('workday = ngày làm chuẩn (trừ CN+lễ); calendar = ngày dương lịch (thai sản, ốm BHXH)');
        });

        foreach (['TS', 'OM'] as $code) {
            DB::table('leave_types')->where('code', $code)->update(['day_count_mode' => 'calendar']);
        }
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('day_count_mode');
        });
    }
};
