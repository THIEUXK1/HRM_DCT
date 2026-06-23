<?php

/**
 * Cấu hình kê khai BHXH Việt Nam (tỷ lệ tham khảo NĐ 595/NĐ-QĐ từ 2018, có thể override .env).
 */
return [
    'currency' => 'VND',

    'salary' => [
        'min_base' => (int) env('BHXH_MIN_SALARY_BASE', 4_960_000),
        'max_base' => (int) env('BHXH_MAX_SALARY_BASE', 46_800_000),
    ],

    'rates' => [
        'bhxh' => [
            'employee' => (float) env('BHXH_RATE_EMPLOYEE', 0.08),
            'employer' => (float) env('BHXH_RATE_EMPLOYER', 0.17),
        ],
        'bhyt' => [
            'employee' => (float) env('BHYT_RATE_EMPLOYEE', 0.015),
            'employer' => (float) env('BHYT_RATE_EMPLOYER', 0.03),
        ],
        'bhtn' => [
            'employee' => (float) env('BHTN_RATE_EMPLOYEE', 0.01),
            'employer' => (float) env('BHTN_RATE_EMPLOYER', 0.01),
        ],
        'kpcd' => [
            'employer' => (float) env('KPCD_RATE_EMPLOYER', 0.02),
        ],
    ],

    'declaration_types' => [
        'd01' => 'D01 — Báo tăng lao động tham gia BHXH',
        'd02' => 'D02 — Điều chỉnh thông tin / mức lương đóng',
        'd05' => 'D05 — Báo giảm lao động',
        'tk1' => 'TK1 — Phụ lục người phụ thuộc (thuế TNCN)',
        'roster' => 'Danh sách đang tham gia BHXH',
    ],

    'termination_reasons' => [
        'resignation' => 'Tự nghỉ / thỏa thuận',
        'contract_end' => 'Hết hạn hợp đồng',
        'discipline' => 'Sa thải / kỷ luật',
        'company_closure' => 'Đóng cửa / thay đổi cơ cấu',
        'retirement' => 'Nghỉ hưu',
        'other' => 'Lý do khác',
    ],

    'required_fields' => [
        'd01' => [
            'company' => ['social_insurance_unit_code', 'name', 'tax_code'],
            'employee' => ['full_name', 'national_id', 'date_of_birth', 'gender', 'insurance_salary', 'bhxh_start_date'],
        ],
        'd05' => [
            'company' => ['social_insurance_unit_code'],
            'employee' => ['full_name', 'social_insurance_number', 'termination_date'],
        ],
        'd02' => [
            'company' => ['social_insurance_unit_code'],
            'employee' => ['full_name', 'social_insurance_number', 'insurance_salary'],
        ],
        'tk1' => [
            'company' => ['social_insurance_unit_code'],
            'dependent' => ['full_name', 'relationship', 'date_of_birth'],
        ],
    ],
];
