<?php

/**
 * Layout bảng công tháng (mẫu Excel BP) — header 2 tầng, tách TV / CT.
 */
return [
    'title' => 'BẢNG CÔNG',

    'info_columns' => [
        ['key' => 'stt', 'label' => 'STT', 'width' => 44, 'sticky' => true, 'align' => 'center'],
        ['key' => 'employee_code', 'label' => 'Mã NV', 'width' => 88, 'sticky' => true, 'align' => 'left'],
        ['key' => 'full_name', 'label' => 'Họ và tên', 'width' => 160, 'sticky' => true, 'align' => 'left'],
        ['key' => 'department', 'label' => 'Bộ phận', 'width' => 120, 'align' => 'left'],
        ['key' => 'hire_date', 'label' => 'Ngày vào', 'width' => 88, 'align' => 'center'],
    ],

    'standard_columns' => [
        ['key' => 'standard_work_days', 'label' => 'Công chuẩn', 'width' => 72, 'align' => 'center', 'numeric' => true],
    ],

    'phase_groups' => [
        'probation' => [
            'label' => 'THỬ VIỆC',
            'theme' => 'probation',
            'columns' => [
                ['key' => 'probation_work_days',     'label' => 'X',   'title' => 'Công đi làm thử việc', 'numeric' => true],
                ['key' => 'probation_paid_leave',    'label' => 'P',   'title' => 'Nghỉ có lương', 'numeric' => true],
                ['key' => 'probation_unpaid_leave',  'label' => 'KL',  'title' => 'Nghỉ không lương', 'numeric' => true],
                ['key' => 'probation_absent',        'label' => 'V',   'title' => 'Vắng không phép', 'numeric' => true],
                ['key' => 'probation_ot_150',        'label' => 'OT.1.5', 'title' => 'Tăng ca 150%', 'numeric' => true],
                ['key' => 'probation_ot_200',        'label' => 'OT.2.0', 'title' => 'Tăng ca 200%', 'numeric' => true],
                ['key' => 'probation_ot_300',        'label' => 'OT.3.0', 'title' => 'Tăng ca 300%', 'numeric' => true],
            ],
        ],
        'official' => [
            'label' => 'CHÍNH THỨC',
            'theme' => 'official',
            'columns' => [
                // Công tổng + tách loại ngày
                ['key' => 'official_work_days',         'label' => 'X',      'title' => 'Tổng công đi làm', 'numeric' => true],
                ['key' => 'official_work_weekday_days', 'label' => 'X.T',    'title' => 'Công ngày thường (T2–T7)', 'numeric' => true],
                ['key' => 'official_work_weekend_days', 'label' => 'X.CN',   'title' => 'Công ngày chủ nhật / nghỉ', 'numeric' => true],
                ['key' => 'official_work_holiday_days', 'label' => 'X.Lễ',   'title' => 'Công ngày lễ / Tết', 'numeric' => true],
                // Nghỉ
                ['key' => 'official_paid_leave',        'label' => 'P',      'title' => 'Nghỉ có lương', 'numeric' => true],
                ['key' => 'official_unpaid_leave',      'label' => 'KL',     'title' => 'Nghỉ không lương', 'numeric' => true],
                ['key' => 'official_absent',            'label' => 'V',      'title' => 'Vắng không phép', 'numeric' => true],
                // Ca đêm thường quy (Điều 106)
                ['key' => 'official_work_night_weekday_hours', 'label' => 'Đêm.T',  'title' => 'Ca đêm 22h–06h ngày thường (giờ)', 'numeric' => true],
                ['key' => 'official_work_night_weekend_hours', 'label' => 'Đêm.CN', 'title' => 'Ca đêm 22h–06h cuối tuần (giờ)', 'numeric' => true],
                ['key' => 'official_work_night_holiday_hours', 'label' => 'Đêm.Lễ', 'title' => 'Ca đêm 22h–06h ngày lễ (giờ)', 'numeric' => true],
                // OT ngày/đêm (Điều 107 × Điều 106)
                ['key' => 'official_ot_weekday_day',    'label' => 'OT.T',   'title' => 'OT ngày thường — ban ngày (giờ)', 'numeric' => true],
                ['key' => 'official_ot_weekday_night',  'label' => 'OT.T.Đ', 'title' => 'OT ngày thường — ban đêm 22h–06h (giờ)', 'numeric' => true],
                ['key' => 'official_ot_weekend_day',    'label' => 'OT.CN',  'title' => 'OT cuối tuần — ban ngày (giờ)', 'numeric' => true],
                ['key' => 'official_ot_weekend_night',  'label' => 'OT.CN.Đ', 'title' => 'OT cuối tuần — ban đêm 22h–06h (giờ)', 'numeric' => true],
                ['key' => 'official_ot_holiday_day',    'label' => 'OT.Lễ',  'title' => 'OT ngày lễ — ban ngày (giờ)', 'numeric' => true],
                ['key' => 'official_ot_holiday_night',  'label' => 'OT.Lễ.Đ', 'title' => 'OT ngày lễ — ban đêm 22h–06h (giờ)', 'numeric' => true],
            ],
        ],
    ],

    'total_columns' => [
        ['key' => 'work_days', 'label' => 'Tổng công', 'width' => 72, 'align' => 'center', 'numeric' => true],
        ['key' => 'paid_leave_days', 'label' => 'P CL', 'width' => 56, 'align' => 'center', 'numeric' => true],
        ['key' => 'unpaid_leave_days', 'label' => 'P KL', 'width' => 56, 'align' => 'center', 'numeric' => true],
        ['key' => 'ot_hours', 'label' => 'Tổng OT', 'width' => 64, 'align' => 'center', 'numeric' => true],
        ['key' => 'actual_work_hours', 'label' => 'Giờ làm', 'width' => 64, 'align' => 'center', 'numeric' => true],
    ],

    /** Bảng công theo ngày — lịch tháng + tổng TV/CT (header 2 tầng). */
    'daily' => [
        'title' => 'BẢNG CÔNG THEO NGÀY',
        'info_columns' => [
            ['key' => 'stt', 'label' => 'STT', 'width' => 40, 'sticky' => true, 'align' => 'center'],
            ['key' => 'employee_code', 'label' => 'Mã NV', 'width' => 84, 'sticky' => true, 'align' => 'left'],
            ['key' => 'full_name', 'label' => 'Họ và tên', 'width' => 150, 'sticky' => true, 'align' => 'left'],
            ['key' => 'department', 'label' => 'Bộ phận', 'width' => 110, 'align' => 'left'],
        ],
        'summary_columns' => [
            ['key' => 'present', 'label' => 'Tổng X', 'numeric' => true],
        ],
    ],

    /** Bảng công TV/CT giai đoạn — 1–2 dòng / NV theo khoảng ngày (AMIS/MISA). */
    'phased' => [
        'title' => 'BẢNG CÔNG TV / CT GIAI ĐOẠN',
        'info_columns' => [
            ['key' => 'stt', 'label' => 'STT', 'width' => 40, 'sticky' => true, 'align' => 'center'],
            ['key' => 'employee_code', 'label' => 'Mã NV', 'width' => 84, 'sticky' => true, 'align' => 'left'],
            ['key' => 'full_name', 'label' => 'Họ và tên', 'width' => 140, 'sticky' => true, 'align' => 'left'],
            ['key' => 'department', 'label' => 'Bộ phận', 'width' => 100, 'align' => 'left'],
            ['key' => 'phase_label', 'label' => 'Giai đoạn', 'width' => 88, 'align' => 'center'],
            ['key' => 'date_range', 'label' => 'Từ — Đến', 'width' => 120, 'align' => 'center'],
            ['key' => 'salary_rate_label', 'label' => 'Hệ số LCB', 'width' => 88, 'align' => 'center'],
            ['key' => 'standard_work_days', 'label' => 'Công chuẩn', 'width' => 72, 'align' => 'center', 'numeric' => true],
        ],
        'metric_columns' => [
            // Công tổng + tách loại ngày
            ['key' => 'work_days',         'label' => 'X',      'title' => 'Tổng công đi làm', 'numeric' => true],
            ['key' => 'work_weekday_days',  'label' => 'X.T',    'title' => 'Công ngày thường', 'numeric' => true],
            ['key' => 'work_weekend_days',  'label' => 'X.CN',   'title' => 'Công chủ nhật / ngày nghỉ', 'numeric' => true],
            ['key' => 'work_holiday_days',  'label' => 'X.Lễ',   'title' => 'Công ngày lễ / Tết', 'numeric' => true],
            // Nghỉ
            ['key' => 'paid_leave_days',    'label' => 'P',      'title' => 'Nghỉ có lương', 'numeric' => true],
            ['key' => 'unpaid_leave_days',  'label' => 'KL',     'title' => 'Nghỉ không lương', 'numeric' => true],
            ['key' => 'absent_days',        'label' => 'V',      'title' => 'Vắng không phép', 'numeric' => true],
            // Ca đêm thường quy
            ['key' => 'work_night_weekday_hours', 'label' => 'Đêm.T',  'title' => 'Ca đêm ngày thường 22h–06h (giờ)', 'numeric' => true],
            ['key' => 'work_night_weekend_hours', 'label' => 'Đêm.CN', 'title' => 'Ca đêm cuối tuần 22h–06h (giờ)', 'numeric' => true],
            ['key' => 'work_night_holiday_hours', 'label' => 'Đêm.Lễ', 'title' => 'Ca đêm ngày lễ 22h–06h (giờ)', 'numeric' => true],
            // OT ngày/đêm
            ['key' => 'ot_weekday_day',    'label' => 'OT.T',    'title' => 'OT ngày thường — ban ngày (giờ)', 'numeric' => true],
            ['key' => 'ot_weekday_night',  'label' => 'OT.T.Đ',  'title' => 'OT ngày thường — ban đêm 22h–06h (giờ)', 'numeric' => true],
            ['key' => 'ot_weekend_day',    'label' => 'OT.CN',   'title' => 'OT cuối tuần — ban ngày (giờ)', 'numeric' => true],
            ['key' => 'ot_weekend_night',  'label' => 'OT.CN.Đ', 'title' => 'OT cuối tuần — ban đêm 22h–06h (giờ)', 'numeric' => true],
            ['key' => 'ot_holiday_day',    'label' => 'OT.Lễ',   'title' => 'OT ngày lễ — ban ngày (giờ)', 'numeric' => true],
            ['key' => 'ot_holiday_night',  'label' => 'OT.Lễ.Đ', 'title' => 'OT ngày lễ — ban đêm 22h–06h (giờ)', 'numeric' => true],
            ['key' => 'ot_hours',          'label' => 'Tổng OT', 'title' => 'Tổng giờ OT', 'numeric' => true],
            ['key' => 'payable_work_days', 'label' => 'Công TL', 'title' => 'Công tính lương (X + P)', 'numeric' => true],
        ],
    ],
];
