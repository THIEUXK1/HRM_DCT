<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value');
            $table->timestamps();

            $table->unique(['company_id', 'key']);
        });

        Schema::create('job_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('rank')->default(1);
            $table->unsignedBigInteger('basic_salary_range_min')->nullable();
            $table->unsignedBigInteger('basic_salary_range_max')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('company_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('holiday_date');
            $table->boolean('is_paid')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'holiday_date']);
        });

        // Seed dữ liệu mặc định chuẩn luật Việt Nam cho tất cả các công ty hiện có
        $companies = DB::table('companies')->get();
        $now = now();

        foreach ($companies as $company) {
            // 1. Seed Company Settings (BHXH, OT, Phép năm)
            DB::table('company_settings')->insert([
                [
                    'company_id' => $company->id,
                    'key' => 'insurance_rate_employer',
                    'value' => '21.5',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'key' => 'insurance_rate_employee',
                    'value' => '10.5',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'key' => 'annual_leave_standard',
                    'value' => '12',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'key' => 'standard_working_days',
                    'value' => '26',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'key' => 'ot_coeff_weekday',
                    'value' => '1.5',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'key' => 'ot_coeff_weekend',
                    'value' => '2.0',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'key' => 'ot_coeff_holiday',
                    'value' => '3.0',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            ]);

            // 2. Seed Job Levels (Thang Cấp bậc & Dải lương)
            DB::table('job_levels')->insert([
                [
                    'company_id' => $company->id,
                    'code' => 'LV1',
                    'name' => 'Thực tập sinh (Intern)',
                    'rank' => 1,
                    'basic_salary_range_min' => 3000000,
                    'basic_salary_range_max' => 7000000,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'code' => 'LV2',
                    'name' => 'Nhân viên (Junior)',
                    'rank' => 2,
                    'basic_salary_range_min' => 8000000,
                    'basic_salary_range_max' => 15000000,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'code' => 'LV3',
                    'name' => 'Chuyên viên (Senior)',
                    'rank' => 3,
                    'basic_salary_range_min' => 16000000,
                    'basic_salary_range_max' => 30000000,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'code' => 'LV4',
                    'name' => 'Trưởng nhóm (Team Lead)',
                    'rank' => 4,
                    'basic_salary_range_min' => 25000000,
                    'basic_salary_range_max' => 45000000,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'code' => 'LV5',
                    'name' => 'Quản lý (Manager)',
                    'rank' => 5,
                    'basic_salary_range_min' => 40000000,
                    'basic_salary_range_max' => 80000000,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            ]);

            // 3. Seed 11 ngày nghỉ lễ Quốc gia Việt Nam năm 2026
            DB::table('company_holidays')->insert([
                [
                    'company_id' => $company->id,
                    'name' => 'Tết Dương Lịch 2026',
                    'holiday_date' => '2026-01-01',
                    'is_paid' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                // Tết Nguyên Đán 2026 (5 ngày luật định từ 16/02 đến 20/02)
                [
                    'company_id' => $company->id,
                    'name' => 'Tết Nguyên Đán (Ngày 1)',
                    'holiday_date' => '2026-02-16',
                    'is_paid' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'name' => 'Tết Nguyên Đán (Ngày 2)',
                    'holiday_date' => '2026-02-17',
                    'is_paid' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'name' => 'Tết Nguyên Đán (Ngày 3)',
                    'holiday_date' => '2026-02-18',
                    'is_paid' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'name' => 'Tết Nguyên Đán (Ngày 4)',
                    'holiday_date' => '2026-02-19',
                    'is_paid' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'name' => 'Tết Nguyên Đán (Ngày 5)',
                    'holiday_date' => '2026-02-20',
                    'is_paid' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                // Giỗ Tổ Hùng Vương (10/03 Âm lịch -> 26/04 Dương lịch năm 2026)
                [
                    'company_id' => $company->id,
                    'name' => 'Giỗ Tổ Hùng Vương',
                    'holiday_date' => '2026-04-26',
                    'is_paid' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                // Ngày Chiến thắng 30/4
                [
                    'company_id' => $company->id,
                    'name' => 'Ngày Giải phóng miền Nam 30/4',
                    'holiday_date' => '2026-04-30',
                    'is_paid' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                // Ngày Quốc tế Lao động 1/5
                [
                    'company_id' => $company->id,
                    'name' => 'Ngày Quốc tế Lao động 1/5',
                    'holiday_date' => '2026-05-01',
                    'is_paid' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                // Ngày Quốc khánh (2 ngày: 02/09 và 03/09)
                [
                    'company_id' => $company->id,
                    'name' => 'Ngày Quốc khánh 2/9 (Ngày 1)',
                    'holiday_date' => '2026-09-02',
                    'is_paid' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'name' => 'Ngày Quốc khánh 2/9 (Ngày 2)',
                    'holiday_date' => '2026-09-03',
                    'is_paid' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_holidays');
        Schema::dropIfExists('job_levels');
        Schema::dropIfExists('company_settings');
    }
};
