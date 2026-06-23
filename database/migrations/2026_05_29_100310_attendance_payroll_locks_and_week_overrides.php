<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khóa kỳ công/lương · hoán đổi T7/CN theo tuần.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_period_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at');
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unlocked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'period']);
        });

        Schema::create('work_schedule_week_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('week_start')->comment('Thứ Hai đầu tuần');
            $table->boolean('swap_enabled')->default(true);
            $table->unsignedTinyInteger('swap_rest_day')->default(6);
            $table->unsignedTinyInteger('swap_work_day')->default(7);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'week_start']);
        });

        Schema::table('payroll_cycles', function (Blueprint $table) {
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            $table->foreignId('unlocked_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('unlocked_at')->nullable()->after('unlocked_by');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_cycles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropConstrainedForeignId('unlocked_by');
            $table->dropColumn('unlocked_at');
        });

        Schema::dropIfExists('work_schedule_week_overrides');
        Schema::dropIfExists('attendance_period_locks');
    }
};
