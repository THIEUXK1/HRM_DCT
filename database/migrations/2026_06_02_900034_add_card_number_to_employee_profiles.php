<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            // Số thẻ RFID vật lý — khác với biometric_id (PIN nội bộ trên máy ZKTeco)
            $table->string('card_number', 50)->nullable()->after('biometric_id');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn('card_number');
        });
    }
};
