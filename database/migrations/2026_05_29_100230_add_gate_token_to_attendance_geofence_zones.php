<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_geofence_zones', function (Blueprint $table) {
            $table->string('gate_token_hash', 64)->nullable()->after('allowed_sources')
                ->comment('Mã QR cổng — hash SHA256');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_geofence_zones', function (Blueprint $table) {
            $table->dropColumn('gate_token_hash');
        });
    }
};
