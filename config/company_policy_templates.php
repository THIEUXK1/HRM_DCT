<?php

/**
 * Gói chính sách nhân sự theo ngành — seed vào policy_templates khi migrate.
 *
 * textile  : Dệt — sản xuất, ca 6D8H, tuân thủ ca chặt
 * garment  : May — sản xuất + hoán đổi T7/CN
 * trading  : Kinh doanh — văn phòng 5D8H, thưởng doanh số
 */
return [
    'textile' => [
        'name' => 'Dệt — Sản xuất',
        'industry_code' => 'textile',
        'description' => 'Khối sản xuất dệt: ca 6D8H, GPS chặt, cảnh báo tuân thủ, thưởng NS theo KPI.',
        'settings' => [
            'standard_working_days' => '26',
            'annual_leave_standard' => '12',
            'insurance_rate_employer' => '21.5',
            'insurance_rate_employee' => '10.5',
            'ot_coeff_weekday' => '1.5',
            'ot_coeff_weekend' => '2.0',
            'ot_coeff_holiday' => '3.0',
            'attendance_mobile_punch_enabled' => '1',
            'attendance_geofence_strict' => '1',
            'compliance_alerts_enabled' => '1',
            'performance_bonus_enabled' => '1',
            'performance_bonus_rate' => '0.15',
            'performance_bonus_use_prev_month' => '1',
            'sales_commission_enabled' => '0',
        ],
        'work_schedule' => [
            'seed_both_groups' => true,
            'production_presets' => ['6D8H'],
            'non_production_presets' => ['5D8H'],
            'production_weekend_swap' => false,
        ],
        'formula_rules' => [
            'PERFORMANCE_BONUS' => [
                'is_active' => true,
                'formula' => '{base_pay_total} * {performance_score} / 100 * {performance_bonus_rate}',
                'description' => 'Thưởng năng suất theo KPI tháng',
            ],
            'SALES_COMMISSION' => ['is_active' => false],
        ],
    ],

    'garment' => [
        'name' => 'May — Sản xuất',
        'industry_code' => 'garment',
        'description' => 'Khối may: ca 6D8H, hoán đổi T7/CN, cảnh báo tuân thủ, thưởng NS.',
        'settings' => [
            'standard_working_days' => '26',
            'annual_leave_standard' => '12',
            'insurance_rate_employer' => '21.5',
            'insurance_rate_employee' => '10.5',
            'ot_coeff_weekday' => '1.5',
            'ot_coeff_weekend' => '2.0',
            'ot_coeff_holiday' => '3.0',
            'attendance_mobile_punch_enabled' => '1',
            'attendance_geofence_strict' => '1',
            'compliance_alerts_enabled' => '1',
            'performance_bonus_enabled' => '1',
            'performance_bonus_rate' => '0.15',
            'performance_bonus_use_prev_month' => '1',
            'sales_commission_enabled' => '0',
        ],
        'work_schedule' => [
            'seed_both_groups' => true,
            'production_presets' => ['6D8H'],
            'non_production_presets' => ['5D8H'],
            'production_weekend_swap' => true,
        ],
        'formula_rules' => [
            'PERFORMANCE_BONUS' => [
                'is_active' => true,
                'formula' => '{base_pay_total} * {performance_score} / 100 * {performance_bonus_rate}',
                'description' => 'Thưởng năng suất theo KPI tháng',
            ],
            'SALES_COMMISSION' => ['is_active' => false],
        ],
    ],

    'trading' => [
        'name' => 'Kinh doanh — Văn phòng',
        'industry_code' => 'trading',
        'description' => 'Khối kinh doanh: ca 5D8H, chấm công linh hoạt, thưởng doanh số.',
        'settings' => [
            'standard_working_days' => '22',
            'annual_leave_standard' => '12',
            'insurance_rate_employer' => '21.5',
            'insurance_rate_employee' => '10.5',
            'ot_coeff_weekday' => '1.5',
            'ot_coeff_weekend' => '2.0',
            'ot_coeff_holiday' => '3.0',
            'attendance_mobile_punch_enabled' => '1',
            'attendance_geofence_strict' => '0',
            'compliance_alerts_enabled' => '0',
            'performance_bonus_enabled' => '0',
            'performance_bonus_rate' => '0',
            'performance_bonus_use_prev_month' => '0',
            'sales_commission_enabled' => '1',
            'sales_commission_rate' => '0.02',
        ],
        'work_schedule' => [
            'seed_both_groups' => true,
            'production_presets' => [],
            'non_production_presets' => ['5D8H'],
            'production_weekend_swap' => false,
        ],
        'formula_rules' => [
            'PERFORMANCE_BONUS' => ['is_active' => false],
            'SALES_COMMISSION' => [
                'is_active' => true,
                'name' => 'Thưởng doanh số',
                'target_field' => 'sales_commission',
                'apply_when' => 'all',
                'formula' => '{allowance_sales_commission}',
                'category' => 'earning',
                'sort_order' => 25,
                'description' => 'Nhập số tiền thưởng doanh số tại tab Trợ cấp tháng (cột Thưởng DS)',
            ],
        ],
    ],
];
