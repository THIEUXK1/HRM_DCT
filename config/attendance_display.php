<?php

/**
 * Mặc định hiển thị bảng công — admin ghi đè qua company_settings.attendance_display_config (JSON).
 */
return [
    'cell_statuses' => [
        'present' => [
            'label' => 'Có mặt',
            'bg_color' => '#f0fdf4',
            'text_color' => '#166534',
            'bold' => true,
        ],
        'late' => [
            'label' => 'Đi trễ',
            'bg_color' => '#fffbeb',
            'text_color' => '#92400e',
            'bold' => true,
        ],
        'leave' => [
            'label' => 'Nghỉ phép',
            'bg_color' => '#fefce8',
            'text_color' => '#854d0e',
            'bold' => true,
        ],
        'paid_leave' => [
            'label' => 'Nghỉ có lương',
            'bg_color' => '#fffbeb',
            'text_color' => '#78350f',
            'bold' => true,
        ],
        'unpaid_leave' => [
            'label' => 'Nghỉ không lương',
            'bg_color' => '#fff7ed',
            'text_color' => '#c2410c',
            'bold' => true,
        ],
        'absent' => [
            'label' => 'Vắng không phép',
            'bg_color' => '#fef2f2',
            'text_color' => '#b91c1c',
            'bold' => true,
        ],
        'holiday' => [
            'label' => 'Ngày lễ',
            'bg_color' => '#faf5ff',
            'text_color' => '#7e22ce',
            'bold' => false,
        ],
        'weekend' => [
            'label' => 'Cuối tuần',
            'bg_color' => '#f8fafc',
            'text_color' => '#94a3b8',
            'bold' => false,
        ],
        'terminated' => [
            'label' => 'Đã nghỉ việc',
            'bg_color' => '#f1f5f9',
            'text_color' => '#64748b',
            'bold' => false,
        ],
        'future' => [
            'label' => 'Chưa đến',
            'bg_color' => 'transparent',
            'text_color' => '#cbd5e1',
            'bold' => false,
        ],
        'off' => [
            'label' => 'Không tính công',
            'bg_color' => 'transparent',
            'text_color' => '#64748b',
            'bold' => false,
        ],
    ],

    'employment_phases' => [
        'probation' => [
            'label' => 'Thử việc',
            'short_label' => 'TV',
            'legend_color_name' => 'xanh',
            'title_prefix' => '[Thử việc]',
            'bg_color' => '#eff6ff',
            'text_color' => '#1e40af',
            'late_bg_color' => '#fffbeb',
            'late_text_color' => '#92400e',
            'late_border_color' => '#60a5fa',
            'badge_variant' => 'info',
            'footer_text' => 'Công thử việc',
        ],
        'official' => [
            'label' => 'Chính thức',
            'short_label' => 'CT',
            'legend_color_name' => 'xanh lá',
            'title_prefix' => '[Chính thức]',
            'bg_color' => '#f0fdf4',
            'text_color' => '#15803d',
            'late_bg_color' => '#fffbeb',
            'late_text_color' => '#92400e',
            'late_border_color' => '#86efac',
            'badge_variant' => 'success',
            'footer_text' => 'Công chính thức',
        ],
    ],

    'day_headers' => [
        'holiday' => [
            'bg_color' => '#faf5ff',
            'text_color' => '#7e22ce',
        ],
        'weekend' => [
            'bg_color' => '#f1f5f9',
            'text_color' => '#94a3b8',
        ],
    ],

    'totals_columns' => [
        'probation' => [
            'short_label' => 'TV',
            'bg_color' => '#eff6ff',
            'text_color' => '#1e40af',
        ],
        'official' => [
            'short_label' => 'CT',
            'bg_color' => '#f0fdf4',
            'text_color' => '#15803d',
        ],
        'paid_leave' => [
            'short_label' => 'P',
            'text_color' => '#b45309',
        ],
        'paid_leave_probation' => [
            'short_label' => 'P TV',
            'bg_color' => '#eff6ff',
            'text_color' => '#b45309',
        ],
        'paid_leave_official' => [
            'short_label' => 'P CT',
            'bg_color' => '#f0fdf4',
            'text_color' => '#b45309',
        ],
        'unpaid_leave' => [
            'short_label' => 'KL',
            'text_color' => '#c2410c',
        ],
        'unpaid_leave_probation' => [
            'short_label' => 'KL TV',
            'bg_color' => '#eff6ff',
            'text_color' => '#c2410c',
        ],
        'unpaid_leave_official' => [
            'short_label' => 'KL CT',
            'bg_color' => '#f0fdf4',
            'text_color' => '#c2410c',
        ],
        'absent' => [
            'short_label' => 'V',
            'text_color' => '#dc2626',
        ],
        'ot' => [
            'short_label' => 'OT',
            'text_color' => '#1e40af',
        ],
        'ot_probation' => [
            'short_label' => 'OT TV',
            'bg_color' => '#eff6ff',
            'text_color' => '#1e40af',
        ],
        'ot_official' => [
            'short_label' => 'OT CT',
            'bg_color' => '#f0fdf4',
            'text_color' => '#15803d',
        ],
    ],

    'legend_footer' => [
        'paid_leave' => [
            'bold_label' => 'P/HH/B',
            'text' => 'Nghỉ có hưởng lương',
            'text_color' => '#b45309',
        ],
        'unpaid_leave' => [
            'bold_label' => 'KL/Ô/TS',
            'text' => 'Nghỉ không hưởng lương NLĐ',
            'text_color' => '#c2410c',
        ],
        'absent' => [
            'bold_label' => 'V',
            'text' => 'Vắng không phép',
            'text_color' => '#dc2626',
        ],
        'terminated' => [
            'bold_label' => 'NV',
            'text' => 'Nghỉ việc',
            'text_color' => '#64748b',
        ],
    ],
];
