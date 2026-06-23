<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BenefitPlan extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'category',
        'description',
        'value_type',
        'value',
        'currency',
        'eligible_after_days',
        'is_taxable',
        'effective_date',
        'expiry_date',
        'is_active',
    ];

    protected $casts = [
        'value'          => 'decimal:2',
        'is_taxable'     => 'boolean',
        'is_active'      => 'boolean',
        'effective_date' => 'date',
        'expiry_date'    => 'date',
    ];

    // ── Categories ────────────────────────────────────────────────────────
    const CATEGORIES = [
        'health'     => 'Bảo hiểm sức khỏe',
        'accident'   => 'Bảo hiểm tai nạn',
        'phone'      => 'Phụ cấp điện thoại',
        'transport'  => 'Phụ cấp xăng xe / đi lại',
        'meal'       => 'Phụ cấp ăn uống',
        'housing'    => 'Phụ cấp nhà ở',
        'equipment'  => 'Thiết bị / laptop',
        'childcare'  => 'Hỗ trợ giữ trẻ',
        'bonus'      => 'Thưởng / trợ cấp đặc biệt',
        'other'      => 'Khác',
    ];

    const VALUE_TYPES = [
        'fixed'         => 'Số tiền cố định',
        'percentage'    => '% lương cơ bản',
        'reimbursement' => 'Hoàn trả thực tế',
    ];

    // ── Relations ─────────────────────────────────────────────────────────
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function enrollments()
    {
        return $this->hasMany(BenefitEnrollment::class);
    }

    public function activeEnrollments()
    {
        return $this->hasMany(BenefitEnrollment::class)->where('status', 'active');
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function formattedValue(): string
    {
        if ($this->value_type === 'percentage') {
            return number_format($this->value, 0) . '%';
        }
        if ($this->value_type === 'reimbursement') {
            return 'Hoàn trả thực tế';
        }
        return number_format($this->value, 0) . ' ' . $this->currency;
    }
}
