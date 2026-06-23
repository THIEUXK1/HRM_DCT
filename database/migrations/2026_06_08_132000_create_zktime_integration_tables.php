<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32)->default('zktime_sql_server');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(1433);
            $table->string('database_name');
            $table->string('username');
            $table->text('password_encrypted');
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh');
            $table->string('user_table', 64)->default('USERINFO');
            $table->string('checkinout_table', 64)->default('CHECKINOUT');
            $table->string('employee_code_field', 64)->default('SSN');
            $table->string('badge_field', 64)->default('Badgenumber');
            $table->string('check_time_field', 64)->default('CHECKTIME');
            $table->boolean('is_active')->default(true);
            $table->string('sync_time', 5)->default('09:00');
            $table->timestamp('last_tested_at')->nullable();
            $table->string('connection_status', 20)->nullable(); // success|failed
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_attendance_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('employee_code');
            $table->string('device_user_id');
            $table->timestamps();

            $table->unique(['company_id', 'employee_id']);
            $table->unique(['company_id', 'employee_code']);
            $table->unique(['company_id', 'device_user_id']);
        });

        Schema::create('attendance_raw_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code')->nullable();
            $table->string('device_user_id');
            $table->dateTime('check_time');
            $table->json('raw_payload')->nullable();
            $table->string('unique_hash', 64)->nullable();
            $table->string('status', 20)->default('pending'); // pending|processed|unmapped|error
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['attendance_source_id', 'device_user_id', 'check_time', 'unique_hash'], 'raw_logs_unique_punch');
        });

        Schema::create('attendance_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20); // success|failed
            $table->unsignedInteger('total_read')->default(0);
            $table->unsignedInteger('inserted')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('unmapped')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sync_logs');
        Schema::dropIfExists('attendance_raw_logs');
        Schema::dropIfExists('employee_attendance_mappings');
        Schema::dropIfExists('attendance_sources');
    }
};
