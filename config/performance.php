<?php

return [
    'cycle_statuses' => [
        'draft' => 'Nháp',
        'active' => 'Đang chạy',
        'closed' => 'Đã đóng',
    ],

    'goal_statuses' => [
        'active' => 'Đang theo dõi',
        'achieved' => 'Đạt',
        'missed' => 'Không đạt',
        'cancelled' => 'Hủy',
    ],

    'review_statuses' => [
        'pending' => 'Chờ đánh giá',
        'self_done' => 'Đã tự đánh giá',
        'manager_done' => 'QL đã chấm',
        'completed' => 'Hoàn tất',
    ],

    'ratings' => [
        'A' => 'Xuất sắc',
        'B' => 'Tốt',
        'C' => 'Đạt',
        'D' => 'Cần cải thiện',
        'E' => 'Chưa đạt',
    ],

    'weights' => [
        'kpi' => 60,
        'behavior' => 40,
    ],
];
