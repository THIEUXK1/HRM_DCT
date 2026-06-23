<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\PayrollFormulaRule;
use App\Models\PolicyTemplate;
use App\Models\PolicyTemplateItem;
use App\Models\WorkScheduleGroup;
use App\Models\WorkSchedulePattern;
use App\Services\AuditLogger;
use App\Services\Attendance\WorkScheduleSetupService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CompanyPolicyTemplateService
{
    public function __construct(
        private readonly WorkScheduleSetupService $workScheduleSetup,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function listTemplates(): array
    {
        return PolicyTemplate::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'industry_code', 'description'])
            ->all();
    }

    public function findTemplate(string $code): ?PolicyTemplate
    {
        return PolicyTemplate::where('code', $code)->where('is_active', true)->first();
    }

    /**
     * Áp dụng gói chính sách cho công ty.
     *
     * @return array<string, mixed>
     */
    public function apply(int $companyId, string $templateCode, bool $overwrite = false): array
    {
        $definition = config("company_policy_templates.{$templateCode}");
        if (! $definition) {
            throw new RuntimeException("Gói chính sách «{$templateCode}» không tồn tại.");
        }

        $company = Company::findOrFail($companyId);

        return DB::transaction(function () use ($company, $companyId, $templateCode, $definition, $overwrite) {
            $stats = [
                'settings' => 0,
                'work_schedule_groups' => 0,
                'work_schedule_patterns' => 0,
                'formula_rules' => 0,
            ];

            $stats['settings'] = $this->applySettings($companyId, $definition['settings'] ?? [], $overwrite);
            $scheduleStats = $this->applyWorkSchedules($companyId, $definition['work_schedule'] ?? [], $overwrite);
            $stats = array_merge($stats, $scheduleStats);
            $stats['formula_rules'] = $this->applyFormulaRules($companyId, $definition['formula_rules'] ?? [], $overwrite);

            $company->update([
                'industry_code' => $definition['industry_code'] ?? $templateCode,
                'policy_template_code' => $templateCode,
                'policy_applied_at' => now(),
            ]);

            AuditLogger::log(
                'company_policy_template_applied',
                $company,
                null,
                'organization',
                "Áp dụng gói chính sách «{$definition['name']}» ({$templateCode})",
                null,
                ['template_code' => $templateCode, 'stats' => $stats],
            );

            CompanyPolicyResolver::flushCache();

            return [
                'company_id' => $companyId,
                'template_code' => $templateCode,
                'template_name' => $definition['name'],
                'stats' => $stats,
            ];
        });
    }

    /** Sync config definitions → policy_templates tables (idempotent). */
    public function syncTemplateCatalog(): void
    {
        foreach (config('company_policy_templates', []) as $code => $definition) {
            $template = PolicyTemplate::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'industry_code' => $definition['industry_code'] ?? $code,
                    'description' => $definition['description'] ?? null,
                    'is_active' => true,
                ],
            );

            PolicyTemplateItem::where('policy_template_id', $template->id)->delete();

            foreach ($definition['settings'] ?? [] as $key => $value) {
                PolicyTemplateItem::create([
                    'policy_template_id' => $template->id,
                    'domain' => 'settings',
                    'item_key' => $key,
                    'value_json' => ['value' => (string) $value],
                ]);
            }

            foreach (['work_schedule', 'formula_rules'] as $domain) {
                if (! empty($definition[$domain])) {
                    PolicyTemplateItem::create([
                        'policy_template_id' => $template->id,
                        'domain' => $domain,
                        'item_key' => '_bundle',
                        'value_json' => $definition[$domain],
                    ]);
                }
            }
        }
    }

    /** Migrate một lần: áp dụng gói mặc định cho CTTV chưa có policy. */
    public function migrateExistingCompanies(string $defaultTemplate = 'garment'): int
    {
        $count = 0;
        Company::query()
            ->whereNull('policy_template_code')
            ->orderBy('id')
            ->each(function (Company $company) use ($defaultTemplate, &$count) {
                $this->apply($company->id, $defaultTemplate, overwrite: false);
                $count++;
            });

        return $count;
    }

    /** @param  array<string, string>  $settings */
    private function applySettings(int $companyId, array $settings, bool $overwrite): int
    {
        $applied = 0;
        $now = now();

        foreach ($settings as $key => $value) {
            $exists = DB::table('company_settings')
                ->where('company_id', $companyId)
                ->where('key', $key)
                ->exists();

            if ($exists && ! $overwrite) {
                continue;
            }

            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $companyId, 'key' => $key],
                ['value' => (string) $value, 'updated_at' => $now, 'created_at' => $now],
            );
            $applied++;
        }

        return $applied;
    }

    /** @param  array<string, mixed>  $scheduleDef */
    private function applyWorkSchedules(int $companyId, array $scheduleDef, bool $overwrite): array
    {
        $hasGroups = WorkScheduleGroup::where('company_id', $companyId)->exists();
        if ($hasGroups && ! $overwrite) {
            return ['work_schedule_groups' => 0, 'work_schedule_patterns' => 0];
        }

        if ($overwrite && $hasGroups) {
            WorkSchedulePattern::where('company_id', $companyId)->delete();
            WorkScheduleGroup::where('company_id', $companyId)->delete();
        }

        $seeded = $this->workScheduleSetup->seedDefaults($companyId);

        if (! empty($scheduleDef['production_weekend_swap'])) {
            WorkSchedulePattern::where('company_id', $companyId)
                ->whereHas('group', fn ($q) => $q->where('group_type', 'production'))
                ->update(['allow_weekend_swap' => true, 'swap_rest_day' => 6, 'swap_work_day' => 7]);
        }

        $allowedPresets = array_merge(
            $scheduleDef['production_presets'] ?? ['6D8H'],
            $scheduleDef['non_production_presets'] ?? ['5D8H'],
        );

        if ($allowedPresets !== []) {
            WorkSchedulePattern::where('company_id', $companyId)
                ->whereNotIn('pattern_code', $allowedPresets)
                ->delete();
        }

        if (($scheduleDef['production_presets'] ?? null) === []) {
            WorkSchedulePattern::where('company_id', $companyId)
                ->whereHas('group', fn ($q) => $q->where('group_type', 'production'))
                ->delete();
            WorkScheduleGroup::where('company_id', $companyId)
                ->where('group_type', 'production')
                ->delete();
        }

        return [
            'work_schedule_groups' => $seeded['groups'],
            'work_schedule_patterns' => $seeded['patterns'],
        ];
    }

    /** @param  array<string, mixed>  $rulesDef */
    private function applyFormulaRules(int $companyId, array $rulesDef, bool $overwrite): int
    {
        $applied = 0;

        if (isset($rulesDef['SALES_COMMISSION']) && ($rulesDef['SALES_COMMISSION']['is_active'] ?? false)) {
            $def = $rulesDef['SALES_COMMISSION'];
            PayrollFormulaRule::updateOrCreate(
                ['company_id' => $companyId, 'code' => 'SALES_COMMISSION'],
                [
                    'name' => $def['name'] ?? 'Thưởng doanh số',
                    'target_field' => $def['target_field'] ?? 'sales_commission',
                    'apply_when' => $def['apply_when'] ?? 'all',
                    'formula' => $def['formula'] ?? '{allowance_sales_commission}',
                    'category' => $def['category'] ?? 'earning',
                    'sort_order' => $def['sort_order'] ?? 25,
                    'description' => $def['description'] ?? null,
                    'is_taxable' => true,
                    'is_active' => true,
                ],
            );
            $applied++;
        } else {
            PayrollFormulaRule::where('company_id', $companyId)
                ->where('code', 'SALES_COMMISSION')
                ->update(['is_active' => false]);
        }

        if (isset($rulesDef['PERFORMANCE_BONUS'])) {
            $def = $rulesDef['PERFORMANCE_BONUS'];
            $updates = ['is_active' => (bool) ($def['is_active'] ?? true)];
            if ($overwrite || ! empty($def['formula'])) {
                if (! empty($def['formula'])) {
                    $updates['formula'] = $def['formula'];
                }
                if (! empty($def['description'])) {
                    $updates['description'] = $def['description'];
                }
            }
            PayrollFormulaRule::where('company_id', $companyId)
                ->where('code', 'PERFORMANCE_BONUS')
                ->update($updates);
            $applied++;
        }

        return $applied;
    }
}
