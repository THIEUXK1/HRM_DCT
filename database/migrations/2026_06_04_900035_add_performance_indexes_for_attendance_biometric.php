<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes tối ưu truy vấn push/sync nhân viên lên máy chấm công.
 *
 * Các query hưởng lợi:
 *  - queryEmployeesWithBiometric(): JOIN employees+profiles, lọc company+dept+biometric_id
 *  - buildBiometricMap(): cùng pattern, dùng trong đồng bộ log
 *  - pushEmployeesToDevices(): filter active devices theo company
 */
return new class extends Migration
{
    public function up(): void
    {
        // employee_profiles.biometric_id — lọc IS NOT NULL nhanh hơn full-scan
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->index('biometric_id', 'ep_biometric_id_idx');
        });

        // employees(company_id, department_id) — covering index cho filter company + dept
        Schema::table('employees', function (Blueprint $table) {
            $table->index(['company_id', 'department_id'], 'emp_company_dept_idx');
        });

        // attendance_devices(company_id, is_active) — filter thiết bị active theo công ty
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->index(['company_id', 'is_active'], 'adev_company_active_idx');
        });

        // attendance_logs(company_id, work_date) — range query trong báo cáo / build tổng hợp
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->index(['company_id', 'work_date'], 'alog_company_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex('alog_company_date_idx');
        });

        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->dropIndex('adev_company_active_idx');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('emp_company_dept_idx');
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropIndex('ep_biometric_id_idx');
        });
    }
};
