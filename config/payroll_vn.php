<?php

return [
    'currency' => 'VND',

    'bhxh' => [
        'employee_rate' => (float) env('PAYROLL_BHXH_EMPLOYEE_RATE', 0.08),
        'employer_rate' => (float) env('PAYROLL_BHXH_EMPLOYER_RATE', 0.175),
        'salary_cap' => (int) env('PAYROLL_BHXH_SALARY_CAP', 46_800_000),
    ],

    'bhyt' => [
        'employee_rate' => (float) env('PAYROLL_BHYT_EMPLOYEE_RATE', 0.015),
        'employer_rate' => (float) env('PAYROLL_BHYT_EMPLOYER_RATE', 0.03),
    ],

    'bhtn' => [
        'employee_rate' => (float) env('PAYROLL_BHTN_EMPLOYEE_RATE', 0.01),
        'employer_rate' => (float) env('PAYROLL_BHTN_EMPLOYER_RATE', 0.01),
    ],

    'kpcd' => [
        'employer_rate' => (float) env('PAYROLL_KPCD_EMPLOYER_RATE', 0.02),
    ],

    'union_fee' => [
        'employee_rate' => (float) env('PAYROLL_UNION_FEE_EMPLOYEE_RATE', 0.01),
        'cap_amount' => (int) env('PAYROLL_UNION_FEE_CAP', 180_000),
    ],

    'accounting_regime' => env('PAYROLL_ACCOUNTING_REGIME', 'TT99_2025'),

    'pit' => [
        'personal_deduction' => (int) env('PAYROLL_PIT_PERSONAL_DEDUCTION', 11_000_000),
        'dependent_deduction' => (int) env('PAYROLL_PIT_DEPENDENT_DEDUCTION', 4_400_000),
        'brackets' => [
            ['up_to' => 5_000_000, 'rate' => 0.05, 'quick_deduction' => 0],
            ['up_to' => 10_000_000, 'rate' => 0.10, 'quick_deduction' => 250_000],
            ['up_to' => 18_000_000, 'rate' => 0.15, 'quick_deduction' => 750_000],
            ['up_to' => 32_000_000, 'rate' => 0.20, 'quick_deduction' => 1_650_000],
            ['up_to' => 52_000_000, 'rate' => 0.25, 'quick_deduction' => 3_250_000],
            ['up_to' => 80_000_000, 'rate' => 0.30, 'quick_deduction' => 5_850_000],
            ['up_to' => PHP_INT_MAX, 'rate' => 0.35, 'quick_deduction' => 9_850_000],
        ],
    ],

    'overtime_multiplier' => (float) env('PAYROLL_OT_MULTIPLIER', 1.5),

    /*
     * Thử việc — lương theo hợp đồng thử việc.
     */
    'probation' => [
        // Mặc định false: không đóng BHXH khi cả tháng còn thử việc
        'bhxh_during_probation' => filter_var(env('PAYROLL_BHXH_ON_PROBATION', false), FILTER_VALIDATE_BOOL),
    ],

    /*
     * ── Bảng mã công chuẩn VN (BLLĐ 2019 + NĐ 145/2020/NĐ-CP) ──────────────
     *
     * Lương giờ A = LCB_tháng / ngày_chuẩn / 8
     * Chính sách thử việc 100%: A_TV = A_CT (probation_salary = salary_base)
     *
     * Mã       | Diễn giải                                  | Hệ số A
     * ---------|--------------------------------------------|--------
     * NC_D     | Ngày thường ban ngày (giờ chuẩn)           | 100% (trong lương tháng)
     * NC_N     | Ngày thường ban đêm (giờ chuẩn)            | 130% = 100% + 30% phụ trội đêm (Điều 106)
     * OT_NT_D  | Tăng ca ngày thường ban ngày               | 150%
     * OT_NT_N1 | Tăng ca ngày thường ban đêm, không TC ngày | 200% = 150%+30%+20%×100%
     * OT_NT_N2 | Tăng ca ngày thường ban đêm, có TC ngày    | 210% = 150%+30%+20%×150%
     * OT_NN_D  | Làm ngày nghỉ hằng tuần ban ngày           | 200%
     * OT_NN_N  | Làm ngày nghỉ hằng tuần ban đêm            | 270% = 200%+30%+20%×200%
     * LEAVE_HOL| Nghỉ lễ hưởng lương (không đi làm)         | 100% (trong lương tháng)
     * OT_LE_D  | Làm ngày lễ ban ngày (phần trả thêm)       | 300%
     * OT_LE_N  | Làm ngày lễ ban đêm (phần trả thêm)        | 390% = 300%+30%+20%×300%
     *
     * Tổng quyền lợi ngày lễ nếu muốn thể hiện đầy đủ:
     *   Ban ngày: 100% (lương lễ) + 300% (OT_LE_D) = 400%
     *   Ban đêm:  100% (lương lễ) + 390% (OT_LE_N) = 490%
     *
     * Khi lễ trùng ngày nghỉ hằng tuần → đi làm tính theo mức ngày lễ (300%/390%).
     * Nghỉ bù lễ-trùng-cuối-tuần → tính theo mức ngày nghỉ hằng tuần (200%/270%).
     * (NĐ 145/2020 Điều 107; mapping trong ot_grid_multipliers bên dưới)
     *
     * OT lưới BestPacific — hệ số × lương giờ A (LCB / công chuẩn / 8).
     * (BLLĐ 2019 Điều 98, 106, 107; NĐ 145/2020/NĐ-CP)
     */
    'use_ot_grid_pay' => filter_var(env('PAYROLL_USE_OT_GRID_PAY', true), FILTER_VALIDATE_BOOL),

    'ot_grid_multipliers' => [
        'day_weekday'        => 1.5,   // 150% — TC ngày thường ban ngày
        'day_weekend'        => 2.0,   // 200% — TC ngày nghỉ ban ngày
        'day_holiday'        => 3.0,   // 300% — TC ngày lễ ban ngày
        'day_annual_leave'   => 3.0,   // 300% — TC ngày phép năm ban ngày
        'night_weekday_n1'   => 2.0,   // 200% = 150%+30%+20%×100% (không TC ngày)
        'night_weekday_n2'   => 2.1,   // 210% = 150%+30%+20%×150% (có TC ngày)
        'night_weekend'      => 2.7,   // 270% = 200%+30%+20%×200%
        'night_paid_holiday' => 2.7,   // 270% — đêm ngày nghỉ có hưởng lương
        'night_holiday'      => 3.9,   // 390% = 300%+30%+20%×300%
        'night_annual_leave' => 3.9,   // 390% — đêm ngày phép năm
    ],

    /*
     * Phụ trội ca đêm thường quy (NC_N) — BLLĐ 2019 Điều 106.
     * Cộng thêm 30% × A cho mỗi giờ trong khung 22:00–06:00
     * của ca làm việc bình thường (không phải OT).
     * NC_D: 100% (đã trong lương tháng), NC_N: 100% + 30% extra.
     */
    'night_work_premium_rate' => (float) env('PAYROLL_NIGHT_WORK_PREMIUM', 0.30),

    /*
     * Khoản thu nhập theo tháng khi TV→CT cùng kỳ (chuyên cần, phụ cấp…).
     * allowance_mode_overrides: mã trợ cấp → full_month | prorate_by_days | end_of_period_official | official_from_start_date | probation_only
     */
    'phased_income' => [
        'allowance_default_mode' => env('PAYROLL_ALLOWANCE_PHASE_MODE', 'full_month'),
        'performance_bonus_split_by_phase' => filter_var(env('PAYROLL_PERFORMANCE_BONUS_SPLIT', true), FILTER_VALIDATE_BOOL),
        'allowance_mode_overrides' => [
            'allowance_position' => 'official_from_start_date',
            'allowance_meal' => 'per_work_day',
            'allowance_probation_insurance' => 'probation_only',
        ],
    ],
];
