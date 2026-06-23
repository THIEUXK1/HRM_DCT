<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ca làm việc theo nhóm SX / phi SX — cảnh báo tuân thủ & OT vượt mức.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedule_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('group_type', 30)->comment('production | non_production');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('work_schedule_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_schedule_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('pattern_code', 20)->comment('5D8H, 6D8H, CUSTOM');
            $table->decimal('hours_per_day', 4, 2)->default(8);
            $table->json('work_days')->comment('ISO weekday 1=T2 … 7=CN');
            $table->json('rest_days')->nullable();
            $table->boolean('allow_weekend_swap')->default(false);
            $table->boolean('allow_continuous')->default(false);
            $table->unsignedTinyInteger('max_consecutive_work_days')->default(13);
            $table->unsignedTinyInteger('swap_rest_day')->nullable()->comment('Ngày nghỉ mặc định khi hoán đổi (6=T7)');
            $table->unsignedTinyInteger('swap_work_day')->nullable()->comment('Ngày làm thay thế (7=CN)');
            $table->foreignId('work_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('employee_work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_schedule_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_schedule_pattern_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('weekend_swap_enabled')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
            $table->index(['company_id', 'effective_from']);
        });

        Schema::create('overtime_excess_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('overtime_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 7);
            $table->date('work_date');
            $table->string('cap_type', 20)->comment('daily|monthly|yearly');
            $table->decimal('legal_hours', 6, 2)->default(0);
            $table->decimal('actual_hours', 6, 2);
            $table->decimal('excess_hours', 6, 2);
            $table->string('status', 30)->default('pending');
            $table->boolean('exclude_from_payroll')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'period']);
            $table->index(['employee_id', 'period']);
        });

        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->json('compliance_alerts')->nullable()->after('attendance_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->dropColumn('compliance_alerts');
        });

        Schema::dropIfExists('overtime_excess_records');
        Schema::dropIfExists('employee_work_schedules');
        Schema::dropIfExists('work_schedule_patterns');
        Schema::dropIfExists('work_schedule_groups');
    }
};
