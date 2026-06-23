<?php

use App\Models\Company;
use App\Services\Company\CompanyPolicyTemplateService;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed catalog gói chính sách + migrate một lần cho CTTV hiện có.
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(CompanyPolicyTemplateService::class);
        $service->syncTemplateCatalog();

        // CTTV hiện có: áp dụng gói may (sản xuất) nếu chưa có — admin có thể đổi sau.
        $service->migrateExistingCompanies('garment');
    }

    public function down(): void
    {
        Company::query()->update([
            'industry_code' => null,
            'policy_template_code' => null,
            'policy_applied_at' => null,
        ]);
    }
};
