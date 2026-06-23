<?php

/**
 * Miền chính sách theo công ty — map key → domain cho UI & versioning.
 */
return [
    'domains' => [
        'attendance' => [
            'label' => 'Chấm công & ca',
            'keys' => [
                'standard_working_days',
                'attendance_mobile_punch_enabled',
                'attendance_geofence_strict',
                'attendance_field_trip_code',
                'compliance_alerts_enabled',
                'diligence_bonus_enabled',
                'diligence_bonus_amount',
                'diligence_min_attendance_rate',
                'diligence_max_late_count',
                'diligence_max_absent_days',
                'diligence_max_forgot_punch',
                'diligence_phase_mode',
                'diligence_bonus_amount_probation',
                'diligence_bonus_amount_official',
                'diligence_prorate_on_phase_split',
            ],
        ],
        'leave' => [
            'label' => 'Nghỉ phép',
            'keys' => [
                'annual_leave_standard',
            ],
        ],
        'payroll' => [
            'label' => 'Lương & OT',
            'keys' => [
                'insurance_rate_employer',
                'insurance_rate_employee',
                'ot_coeff_weekday',
                'ot_coeff_weekend',
                'ot_coeff_holiday',
                'performance_bonus_enabled',
                'performance_bonus_rate',
                'performance_bonus_use_prev_month',
                'sales_commission_enabled',
                'sales_commission_rate',
                'termination_unused_leave_days_default',
            ],
        ],
    ],

    'labels' => [
        'standard_working_days' => 'Ngày công chuẩn / tháng',
        'annual_leave_standard' => 'Phép năm tiêu chuẩn (ngày)',
        'insurance_rate_employer' => 'Tỷ lệ BHXH NLĐ (%)',
        'insurance_rate_employee' => 'Tỷ lệ BHXH NV (%)',
        'ot_coeff_weekday' => 'Hệ số OT ngày thường',
        'ot_coeff_weekend' => 'Hệ số OT cuối tuần',
        'ot_coeff_holiday' => 'Hệ số OT ngày lễ',
        'attendance_mobile_punch_enabled' => 'Chấm công mobile/GPS',
        'attendance_geofence_strict' => 'Bắt buộc trong geofence',
        'compliance_alerts_enabled' => 'Cảnh báo tuân thủ ca',
        'performance_bonus_enabled' => 'Bật thưởng năng suất/KPI',
        'performance_bonus_rate' => 'Tỷ lệ thưởng NS',
        'performance_bonus_use_prev_month' => 'Thưởng NS theo lương T-1',
        'sales_commission_enabled' => 'Bật thưởng doanh số',
        'sales_commission_rate' => 'Tỷ lệ thưởng DS (tham khảo)',
        'diligence_bonus_enabled' => 'Bật thưởng chuyên cần',
        'diligence_bonus_amount' => 'Mức thưởng chuyên cần (VND)',
        'diligence_min_attendance_rate' => 'Tỷ lệ chuyên cần tối thiểu (%)',
        'diligence_max_late_count' => 'Số lần đi trễ tối đa',
        'diligence_max_absent_days' => 'Số ngày vắng tối đa',
        'diligence_max_forgot_punch' => 'Số lần quên chấm tối đa',
        'diligence_phase_mode' => 'Cách tính CC khi TV→CT cùng kỳ (full_month|prorate_by_days|end_of_period_official)',
        'diligence_bonus_amount_probation' => 'Mức thưởng CC thử việc/tháng (để trống = dùng mức chung)',
        'diligence_bonus_amount_official' => 'Mức thưởng CC chính thức/tháng (để trống = dùng mức chung)',
        'diligence_prorate_on_phase_split' => '(Legacy) Prorate CC theo công CT',
        'termination_unused_leave_days_default' => 'Ngày phép còn lại mặc định (thôi việc)',
    ],
];
