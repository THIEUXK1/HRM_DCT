<?php

/**
 * Đăng ký biến công thức lương — metadata cho UI; giá trị tham số lưu company_settings.
 * Biến computed do PayrollContextBuilder / bảng công tính — không sửa tay trên UI.
 */
return [
    'parameters' => [
        'performance_bonus_enabled' => [
            'label' => 'Bật thưởng KPI vào lương',
            'description' => 'Tắt thì không lấy điểm KPI vào công thức tháng đó.',
            'type' => 'boolean',
            'group' => 'kpi',
            'default' => '1',
        ],
        'performance_bonus_rate' => [
            'label' => 'Tỷ lệ thưởng NS tối đa (điểm 100)',
            'description' => 'Dùng trong công thức: {performance_score} × tỷ lệ × lương cơ bản.',
            'type' => 'rate',
            'group' => 'kpi',
            'formula_key' => 'performance_bonus_rate',
            'default' => '0.15',
            'min' => 0,
            'max' => 1,
            'step' => 0.01,
        ],
        'termination_unused_leave_days_default' => [
            'label' => 'Ngày phép còn lại mặc định (thôi việc)',
            'description' => 'Áp dụng {unused_leave_days} khi chưa có ghi đè theo NV.',
            'type' => 'days',
            'group' => 'termination',
            'formula_key' => 'unused_leave_days',
            'default' => '0',
            'min' => 0,
            'max' => 365,
            'step' => 0.5,
        ],
        'sales_commission_enabled' => [
            'label' => 'Bật thưởng doanh số (tham khảo)',
            'description' => 'Cờ bật/tắt — dùng kèm biến tùy chỉnh hoặc công thức riêng.',
            'type' => 'boolean',
            'group' => 'other',
            'default' => '0',
        ],
        'sales_commission_rate' => [
            'label' => 'Tỷ lệ thưởng doanh số',
            'description' => 'Có thể dùng trong biến tùy chỉnh hoặc công thức {sales_commission_rate}.',
            'type' => 'rate',
            'group' => 'other',
            'formula_key' => 'sales_commission_rate',
            'default' => '0',
            'min' => 0,
            'max' => 1,
            'step' => 0.01,
        ],
    ],

    'computed' => [
        'base_pay_total' => 'Tổng lương TV + CT (chưa OT)',
        'probation_base_pay' => 'Lương thử việc tháng',
        'official_base_pay' => 'Lương chính thức tháng',
        'ot_pay' => 'Tiền tăng ca',
        'base_salary_monthly' => 'Lương CT tháng (HĐ)',
        'standard_work_days' => 'Công chuẩn tháng',
        'payable_official_days' => 'Công CT tính lương',
        'payable_probation_days' => 'Công TV tính lương',
        'ot_probation_pay' => 'Tiền OT giai đoạn thử việc',
        'ot_official_pay' => 'Tiền OT giai đoạn chính thức',
        'diligence_bonus_amount' => 'Thưởng chuyên cần (tổng) — từ bảng công',
        'diligence_probation_pay' => 'Thưởng chuyên cần TV',
        'diligence_official_pay' => 'Thưởng chuyên cần CT',
        'performance_bonus_probation' => 'Thưởng KPI / NS trên lương TV',
        'performance_bonus_official' => 'Thưởng KPI / NS trên lương CT',
        'performance_score' => 'Điểm KPI (0–100)',
        'daily_salary' => 'Lương ngày = lương tháng / công chuẩn',
        'work_days_until_exit' => 'Công thực tế đến ngày nghỉ việc',
        'payable_days_until_exit' => 'Công tính lương đến ngày nghỉ việc',
    ],
];
