<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung các cột cần thiết để bảng công tuân thủ BLLĐ 2019:
 *  - Điều 105: giờ làm tiêu chuẩn, tính trễ/sớm
 *  - Điều 106: giờ làm ban đêm (22:00–06:00)
 *  - Điều 107: tăng ca phân loại ngày thường/cuối tuần/lễ, kiểm tra giới hạn
 *  - Điều 24–27: phân biệt công thử việc / công chính thức
 *  - Điều 112: ngày nghỉ lễ (holiday flag)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── attendance_logs ──────────────────────────────────────────────────
        Schema::table('attendance_logs', function (Blueprint $table) {
            // Giờ thực làm (check_out - check_in - break), đơn vị giờ
            $table->decimal('work_hours', 5, 2)->default(0)->after('check_out_at');
            // Trễ đầu giờ (phút), so với shift start_time
            $table->decimal('late_minutes', 6, 2)->default(0)->after('work_hours');
            // Về sớm (phút), so với shift end_time
            $table->decimal('early_minutes', 6, 2)->default(0)->after('late_minutes');
            // Giờ làm ban đêm (22:00–06:00) trong ngày đó
            $table->decimal('night_hours', 5, 2)->default(0)->after('early_minutes');
            // Ngày cuối tuần (T7, CN)
            $table->boolean('is_weekend')->default(false)->after('night_hours');
            // Ngày lễ (Điều 112 BLLĐ 2019)
            $table->boolean('is_holiday')->default(false)->after('is_weekend');
            // Tên ngày lễ (nếu là ngày lễ)
            $table->string('holiday_name')->nullable()->after('is_holiday');
            // Giai đoạn hợp đồng: probation | official
            $table->string('employment_phase')->default('official')->after('holiday_name');
            // Ca làm việc áp dụng
            $table->foreignId('work_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete()->after('employment_phase');

            $table->index(['employee_id', 'work_date', 'employment_phase']);
        });

        // ── overtime_requests ────────────────────────────────────────────────
        Schema::table('overtime_requests', function (Blueprint $table) {
            // Phân loại OT: weekday (150%), weekend (200%), holiday (300%)
            $table->string('ot_type')->default('weekday')->after('hours')
                ->comment('weekday=150%, weekend=200%, holiday=300%');
            // Giờ OT ban đêm trong lần OT này (cộng thêm 20%)
            $table->decimal('night_hours', 5, 2)->default(0)->after('ot_type');
            // Cờ vượt giới hạn (4h/ngày, 40h/tháng, 200h/năm)
            $table->boolean('exceeds_daily_cap')->default(false)->after('night_hours');
            $table->boolean('exceeds_monthly_cap')->default(false)->after('exceeds_daily_cap');

            $table->index(['employee_id', 'work_date']);
        });

        // ── attendance_summaries ─────────────────────────────────────────────
        Schema::table('attendance_summaries', function (Blueprint $table) {
            // Số ngày làm trong giai đoạn thử việc
            $table->decimal('probation_work_days', 5, 2)->default(0)->after('work_days');
            // Số ngày làm trong giai đoạn chính thức
            $table->decimal('official_work_days', 5, 2)->default(0)->after('probation_work_days');
            // Ngày công tiêu chuẩn trong tháng (trừ lễ, cuối tuần)
            $table->decimal('standard_work_days', 5, 2)->default(0)->after('official_work_days');
            // Ngày vắng không phép
            $table->decimal('absent_days', 5, 2)->default(0)->after('standard_work_days');
            // Tổng giờ thực làm
            $table->decimal('actual_work_hours', 8, 2)->default(0)->after('absent_days');
            // Tổng giờ tiêu chuẩn theo lịch (standard_work_days * 8)
            $table->decimal('standard_work_hours', 8, 2)->default(0)->after('actual_work_hours');
            // OT ngày thường (hệ số 150%)
            $table->decimal('ot_weekday_hours', 8, 2)->default(0)->after('ot_hours');
            // OT cuối tuần (hệ số 200%)
            $table->decimal('ot_weekend_hours', 8, 2)->default(0)->after('ot_weekday_hours');
            // OT ngày lễ (hệ số 300%)
            $table->decimal('ot_holiday_hours', 8, 2)->default(0)->after('ot_weekend_hours');
            // Giờ làm ban đêm (22:00–06:00, +30%)
            $table->decimal('night_hours', 8, 2)->default(0)->after('ot_holiday_hours');
            // Số lần đi trễ
            $table->unsignedSmallInteger('late_count')->default(0)->after('late_minutes');
            // Số lần về sớm
            $table->unsignedSmallInteger('early_count')->default(0)->after('late_count');
            // Cảnh báo vượt OT tháng
            $table->boolean('ot_monthly_cap_exceeded')->default(false)->after('early_count');

            $table->index(['employee_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn([
                'work_hours', 'late_minutes', 'early_minutes', 'night_hours',
                'is_weekend', 'is_holiday', 'holiday_name', 'employment_phase', 'work_shift_id',
            ]);
        });

        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropColumn(['ot_type', 'night_hours', 'exceeds_daily_cap', 'exceeds_monthly_cap']);
        });

        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'probation_work_days', 'official_work_days', 'standard_work_days',
                'absent_days', 'actual_work_hours', 'standard_work_hours',
                'ot_weekday_hours', 'ot_weekend_hours', 'ot_holiday_hours', 'night_hours',
                'late_count', 'early_count', 'ot_monthly_cap_exceeded',
            ]);
        });
    }
};
