<?php

/**
 * Cấu hình ca làm việc theo nhóm — BLLĐ 2019.
 */
return [
    /** Không làm quá N ngày liên tục (cảnh báo sản xuất). */
    'max_consecutive_work_days' => 13,

    'group_types' => [
        'production' => 'Khối sản xuất',
        'non_production' => 'Khối phi sản xuất',
    ],

    /** ISO weekday: 1=T2 … 7=CN */
    'pattern_presets' => [
        '5D8H' => [
            'name' => '5 ngày × 8 giờ (T2–T6)',
            'hours_per_day' => 8,
            'work_days' => [1, 2, 3, 4, 5],
            'rest_days' => [6, 7],
            'allow_weekend_swap' => false,
            'allow_continuous' => false,
        ],
        '6D8H' => [
            'name' => '6 ngày × 8 giờ (T2–T7)',
            'hours_per_day' => 8,
            'work_days' => [1, 2, 3, 4, 5, 6],
            'rest_days' => [7],
            'allow_weekend_swap' => true,
            'allow_continuous' => true,
        ],
    ],

    'default_groups' => [
        [
            'code' => 'SX',
            'name' => 'Khối sản xuất',
            'group_type' => 'production',
            'description' => 'Ca liên tục, có thể hoán đổi T7 nghỉ / CN đi làm',
        ],
        [
            'code' => 'HC',
            'name' => 'Khối phi sản xuất',
            'group_type' => 'non_production',
            'description' => 'Làm việc T2–T6, ca hành chính',
        ],
    ],

    'alert_types' => [
        'consecutive_days' => 'Vượt ngày làm liên tục',
        'ot_daily' => 'Vượt OT ngày',
        'ot_monthly' => 'Vượt OT tháng',
        'ot_yearly' => 'Vượt OT năm',
        'unexpected_work_day' => 'Đi làm ngoài lịch ca',
    ],
];
