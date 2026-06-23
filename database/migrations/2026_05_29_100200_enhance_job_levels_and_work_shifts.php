<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thang cấp bậc O1–O7 (band A–D) và metadata ca làm việc (ca đêm BLLĐ 2019).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_levels', function (Blueprint $table) {
            $table->string('grade', 8)->nullable()->after('code')
                ->comment('O1..O7');
            $table->string('band', 2)->nullable()->after('grade')
                ->comment('A|B|C|D');
            $table->string('category', 32)->nullable()->after('band')
                ->comment('manager|employee|worker');
            $table->text('description')->nullable()->after('basic_salary_range_max');
        });

        Schema::table('work_shifts', function (Blueprint $table) {
            $table->boolean('is_night_shift')->default(false)->after('break_minutes');
            $table->boolean('crosses_midnight')->default(false)->after('is_night_shift');
            $table->decimal('standard_hours', 4, 2)->default(8)->after('crosses_midnight')
                ->comment('Giờ làm chuẩn/ca sau nghỉ');
            $table->string('legal_reference', 255)->nullable()->after('standard_hours');
        });

        $this->backfillDefaults();
    }

    private function backfillDefaults(): void
    {
        $catalog = app(\App\Services\Hr\JobLevelCatalogService::class);

        foreach (\Illuminate\Support\Facades\DB::table('companies')->pluck('id') as $companyId) {
            $catalog->syncStandardGrades((int) $companyId, deactivateLegacy: true);

            foreach (config('hr_vn.work_shift_presets', []) as $preset) {
                \App\Models\WorkShift::updateOrCreate(
                    ['company_id' => $companyId, 'code' => $preset['code']],
                    array_merge($preset, ['is_active' => true]),
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn(['is_night_shift', 'crosses_midnight', 'standard_hours', 'legal_reference']);
        });

        Schema::table('job_levels', function (Blueprint $table) {
            $table->dropColumn(['grade', 'band', 'category', 'description']);
        });
    }
};
