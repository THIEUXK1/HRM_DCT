<?php

/**
 * Phân loại nghỉ & thưởng theo thực tiễn nhân sự — tiền lương Việt Nam (BLLĐ 2019).
 * Admin cấu hình qua Settings; danh mục chuẩn seed từ hr_vn.leave_types & payroll_bonus_types.
 */
return [
    /** Ảnh hưởng tới công/lương công ty (không phải BHXH chi trả). */
    'payroll_categories' => [
        'company_paid' => [
            'label' => 'Nghỉ có lương (công ty trả)',
            'description' => 'Cộng vào công tính lương (X + P). Không trừ lương.',
            'is_paid' => true,
            'cell_group' => 'paid',
        ],
        'company_unpaid' => [
            'label' => 'Nghỉ không lương (công ty không trả)',
            'description' => 'Không cộng công tính lương — trừ theo công chuẩn.',
            'is_paid' => false,
            'cell_group' => 'unpaid',
        ],
        'bhxh_benefit' => [
            'label' => 'Nghỉ hưởng chế độ BHXH',
            'description' => 'Công ty không trả lương; chế độ do BHXH chi trả (ốm, thai sản…). Không tính vắng.',
            'is_paid' => false,
            'cell_group' => 'bhxh',
        ],
    ],

    /** Nhóm thưởng — admin thêm/sửa trong Cài đặt → Lương & thưởng. */
    'bonus_categories' => [
        'diligence' => 'Thưởng chuyên cần',
        'kpi' => 'Thưởng KPI / hiệu suất',
        'sales' => 'Thưởng doanh số / hoa hồng',
        'productivity' => 'Thưởng năng suất / sản lượng',
        'project' => 'Thưởng dự án',
        'holiday' => 'Thưởng lễ, Tết',
        'th13' => 'Thưởng tháng 13 / cuối năm',
        'seniority' => 'Thưởng thâm niên',
        'innovation' => 'Thưởng sáng kiến / cải tiến',
        'adhoc' => 'Thưởng đột xuất',
        'allowance' => 'Trợ cấp cố định tháng',
    ],

    'bonus_calculation_modes' => [
        'manual' => 'Nhập thủ công từng kỳ',
        'fixed' => 'Số tiền cố định mặc định',
        'percent_base_salary' => '% lương cơ bản',
    ],

    /** Danh mục thưởng chuẩn VN — seed khi bấm «Áp dụng danh mục chuẩn». */
    'bonus_types' => [
        ['code' => 'T_CC', 'name' => 'Thưởng chuyên cần', 'category' => 'diligence', 'breakdown_key' => 'bonus_diligence', 'sort_order' => 10],
        ['code' => 'T_KPI', 'name' => 'Thưởng KPI / hiệu suất', 'category' => 'kpi', 'breakdown_key' => 'bonus_kpi', 'sort_order' => 20],
        ['code' => 'T_DS', 'name' => 'Thưởng doanh số', 'category' => 'sales', 'breakdown_key' => 'bonus_sales', 'sort_order' => 30],
        ['code' => 'T_NS', 'name' => 'Thưởng năng suất', 'category' => 'productivity', 'breakdown_key' => 'bonus_productivity', 'sort_order' => 40],
        ['code' => 'T_DA', 'name' => 'Thưởng dự án', 'category' => 'project', 'breakdown_key' => 'bonus_project', 'sort_order' => 50],
        ['code' => 'T_LETET', 'name' => 'Thưởng lễ, Tết', 'category' => 'holiday', 'breakdown_key' => 'bonus_holiday', 'sort_order' => 60],
        ['code' => 'T_T13', 'name' => 'Thưởng tháng 13', 'category' => 'th13', 'breakdown_key' => 'bonus_th13', 'sort_order' => 70],
        ['code' => 'T_TN', 'name' => 'Thưởng thâm niên', 'category' => 'seniority', 'breakdown_key' => 'bonus_seniority', 'sort_order' => 80],
        ['code' => 'T_SK', 'name' => 'Thưởng sáng kiến', 'category' => 'innovation', 'breakdown_key' => 'bonus_innovation', 'sort_order' => 90],
        ['code' => 'T_DX', 'name' => 'Thưởng đột xuất', 'category' => 'adhoc', 'breakdown_key' => 'bonus_adhoc', 'sort_order' => 100],
    ],
];
