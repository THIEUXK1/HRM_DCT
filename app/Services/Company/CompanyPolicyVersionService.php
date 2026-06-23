<?php

namespace App\Services\Company;

use App\Models\CompanyPolicyVersion;
use App\Models\User;

class CompanyPolicyVersionService
{
    /** @return array<int, array<string, mixed>> */
    public function list(int $companyId, ?string $domain = null, int $limit = 50): array
    {
        $query = CompanyPolicyVersion::with('appliedByUser:id,name')
            ->where('company_id', $companyId)
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        if ($domain) {
            $query->where('domain', $domain);
        }

        return $query->limit($limit)->get()->map(fn ($v) => [
            'id' => $v->id,
            'domain' => $v->domain,
            'effective_from' => $v->effective_from?->toDateString(),
            'snapshot_json' => $v->snapshot_json,
            'notes' => $v->notes,
            'applied_by' => $v->appliedByUser?->name,
            'created_at' => $v->created_at?->toIso8601String(),
        ])->all();
    }

    public function record(
        int $companyId,
        string $domain,
        array $snapshot,
        string $effectiveFrom,
        ?User $user = null,
        ?string $notes = null,
    ): CompanyPolicyVersion {
        CompanyPolicyResolver::flushCache();

        return CompanyPolicyVersion::create([
            'company_id' => $companyId,
            'domain' => $domain,
            'effective_from' => $effectiveFrom,
            'snapshot_json' => $snapshot,
            'applied_by' => $user?->id,
            'notes' => $notes,
        ]);
    }
}
