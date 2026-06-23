<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance attendance_devices table
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->string('comm_key', 255)->nullable()->after('connection_password');
            $table->string('serial_number', 100)->nullable()->after('comm_key');
            $table->string('location', 255)->nullable()->after('serial_number');
            $table->foreignId('department_id')->nullable()->after('location')->constrained()->nullOnDelete();
            $table->timestamp('last_connected_at')->nullable()->after('sync_message');
        });

        // 2. Create zkteco_sync_batches table
        Schema::create('zkteco_sync_batches', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type', 50); // selected_employees|department|filter|all
            $table->json('target_device_ids');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('dry_run')->default(false);
            $table->string('status', 30)->default('pending'); // pending|processing|completed|failed
            $table->integer('total_employees')->default(0);
            $table->integer('total_devices')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // 3. Create zkteco_sync_logs table
        Schema::create('zkteco_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('zkteco_sync_batches')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('attendance_devices')->cascadeOnDelete();
            $table->string('employee_code', 50);
            $table->string('fingerprint_code', 50)->nullable();
            $table->string('action', 50); // create_user|update_user|push_card|push_fingerprint|skip
            $table->string('status', 30); // pending|success|failed|skipped
            $table->string('message', 255)->nullable();
            $table->text('error_detail')->nullable();
            $table->json('old_device_data')->nullable();
            $table->json('new_device_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkteco_sync_logs');
        Schema::dropIfExists('zkteco_sync_batches');

        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['comm_key', 'serial_number', 'location', 'department_id', 'last_connected_at']);
        });
    }
};
