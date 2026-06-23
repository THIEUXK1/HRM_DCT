<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use BelongsToCompany;

    public const PAYROLL_COMPANY_PAID = 'company_paid';

    public const PAYROLL_COMPANY_UNPAID = 'company_unpaid';

    public const PAYROLL_BHXH = 'bhxh_benefit';

    protected $fillable = [
        'company_id', 'name', 'code', 'is_paid', 'payroll_category', 'affects_diligence',
        'day_count_mode', 'requires_approval',
        'cell_symbol', 'legal_reference', 'description', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'affects_diligence' => 'boolean',
        ];
    }

    public function requests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /** @return array<string, string> */
    public static function payrollCategoryLabels(): array
    {
        return collect(config('leave_payroll_vn.payroll_categories', []))
            ->mapWithKeys(fn ($meta, $key) => [$key => $meta['label'] ?? $key])
            ->all();
    }

    public static function resolvePayrollCategory(?self $leaveType): string
    {
        if (! $leaveType) {
            return self::PAYROLL_COMPANY_PAID;
        }

        $category = $leaveType->payroll_category ?? null;
        if ($category && array_key_exists($category, config('leave_payroll_vn.payroll_categories', []))) {
            return $category;
        }

        return $leaveType->is_paid ? self::PAYROLL_COMPANY_PAID : self::PAYROLL_COMPANY_UNPAID;
    }

    public function applyPayrollCategoryDefaults(): void
    {
        $meta = config('leave_payroll_vn.payroll_categories.'.$this->payroll_category);
        if ($meta && array_key_exists('is_paid', $meta)) {
            $this->is_paid = (bool) $meta['is_paid'];
        }
    }

    public function affectsDiligenceByDefault(): bool
    {
        if ($this->affects_diligence !== null) {
            return (bool) $this->affects_diligence;
        }

        return match (self::resolvePayrollCategory($this)) {
            self::PAYROLL_COMPANY_UNPAID => true,
            self::PAYROLL_BHXH => false,
            default => false,
        };
    }
}
