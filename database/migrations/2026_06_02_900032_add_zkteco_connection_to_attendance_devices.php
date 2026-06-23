<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('longitude');
            $table->unsignedSmallInteger('port')->default(4370)->after('ip_address');
            $table->string('connection_password', 255)->nullable()->after('port');
            $table->timestamp('last_sync_at')->nullable()->after('connection_password');
            $table->string('sync_status', 20)->nullable()->after('last_sync_at');  // success|failed|syncing
            $table->text('sync_message')->nullable()->after('sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'port', 'connection_password', 'last_sync_at', 'sync_status', 'sync_message']);
        });
    }
};
