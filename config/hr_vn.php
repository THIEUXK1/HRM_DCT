<?php

/**
 * Danh mục nghiệp vụ nhân sự theo quy định Việt Nam (Bộ luật Lao động 2019, Luật BHXH, Luật thuế TNCN).
 * Dùng cho validation, UI labels và báo cáo.
 */
return [
    'id_card_types' => [
        'cccd' => 'Căn cước công dân',
        'cmnd' => 'Chứng minh nhân dân (cũ)',
        'passport' => 'Hộ chiếu',
    ],

    'genders' => [
        'male' => 'Nam',
        'female' => 'Nữ',
        'other' => 'Khác',
    ],

    'marital_statuses' => [
        'single' => 'Độc thân',
        'married' => 'Đã kết hôn',
        'divorced' => 'Ly hôn',
        'widowed' => 'Góa',
    ],

    'employment_types' => [
        'full_time' => 'Toàn thời gian',
        'part_time' => 'Bán thời gian',
        'internship' => 'Thực tập',
        'probation' => 'Thử việc',
        'collaborator' => 'Cộng tác viên',
    ],

    'employment_statuses' => [
        'active' => 'Đang làm việc',
        'probation' => 'Thử việc',
        'maternity_leave' => 'Nghỉ thai sản',
        'unpaid_leave' => 'Nghỉ không lương',
        'suspended' => 'Đình chỉ',
        'terminated' => 'Nghỉ việc',
        'resigned' => 'Tự nghỉ',
    ],

    'contract_types' => [
        'indefinite' => 'Không xác định thời hạn',
        'definite' => 'Xác định thời hạn',
        'seasonal' => 'Theo mùa vụ (< 12 tháng)',
        'probation' => 'Thử việc',
        'service' => 'Hợp đồng dịch vụ / cộng tác',
    ],

    'contract_statuses' => [
        'draft' => 'Nháp',
        'active' => 'Đang hiệu lực',
        'expired' => 'Hết hạn',
        'terminated' => 'Chấm dứt',
        'replaced' => 'Thay thế bởi HĐ mới',
    ],

  /** Đơn xin nghỉ việc (ESS) — tham chiếu BLLĐ 2019 Điều 35 */
    'resignation' => [
        'min_reason_length' => 20,
        'notice_days_indefinite' => 30,
        'notice_days_definite' => 3,
        'notice_days_default' => 30,
    ],

    'working_hour_types' => [
        'full_time_48' => 'Toàn thời gian (tối đa 48h/tuần)',
        'part_time' => 'Bán thời gian',
        'shift' => 'Ca kíp',
    ],

    'dependent_relationships' => [
        'child' => 'Con',
        'spouse' => 'Vợ/Chồng',
        'parent' => 'Cha/Mẹ',
        'other' => 'Khác',
    ],

    'document_types' => [
        'cccd_front' => 'CCCD (mặt trước)',
        'cccd_back' => 'CCCD (mặt sau)',
        'photo_4x6' => 'Ảnh 4x6',
        'degree' => 'Bằng cấp / chứng chỉ',
        'health_check' => 'Giấy khám sức khỏe',
        'labor_contract' => 'Hợp đồng lao động',
        'social_insurance_book' => 'Sổ BHXH / xác nhận BHXH',
        'tax_registration' => 'Đăng ký MST cá nhân',
        'resume' => 'Sơ yếu lý lịch',
        'decision_appointment' => 'Quyết định bổ nhiệm / điều chuyển',
        'termination_record' => 'Biên bản / quyết định nghỉ việc',
        'work_permit' => 'Giấy phép lao động (người nước ngoài)',
        'other' => 'Khác',
    ],

    /*
     * Giới hạn OT theo Điều 107 BLLĐ 2019 + NĐ 145/2020.
     * ot_yearly_max_hours: 200h tiêu chuẩn, 300h cho ngành đặc biệt.
     */
    'ot_daily_max_hours'   => 4,
    'ot_monthly_max_hours' => 40,
    'ot_yearly_max_hours'  => 200,

    /*
     * Ngưỡng cảnh báo HR (BLLĐ 2019, NĐ 145/2020).
     */
    'alert_thresholds' => [
        'contract_expiring_days' => [30, 60],
        'probation_ending_days' => 14,
        'ot_monthly_warning_pct' => 0.8,
        'ot_yearly_warning_pct' => 0.8,
        'ot_yearly_notify_min_hours' => 200,
    ],

    /** HĐ xác định thời hạn tối đa 36 tháng (Điều 20 BLLĐ 2019). */
    'contract_max_definite_months' => 36,

    /** HĐ mùa vụ tối đa 12 tháng. */
    'contract_max_seasonal_months' => 12,

    /*
     * Hệ số OT theo loại ngày (Điều 107 BLLĐ 2019).
     */
    'ot_rates' => [
        'weekday' => 1.5,   // 150% — ngày thường
        'weekend' => 2.0,   // 200% — cuối tuần
        'holiday' => 3.0,   // 300% — ngày lễ / nghỉ có lương
    ],

    /*
     * Hệ số làm đêm (Điều 106 BLLĐ 2019): cộng thêm 30%.
     * Nếu OT ban đêm: cộng thêm 20% nữa trên hệ số OT.
     */
    'night_work_rate'     => 0.3,
    'night_ot_extra_rate' => 0.2,

    /*
     * Thời gian thử việc tối đa (Điều 25 BLLĐ 2019).
     */
    'probation_max_months' => [
        'manager'      => 6,   // Nhà quản lý, giám đốc → tối đa 180 ngày
        'university'   => 2,   // Tốt nghiệp ĐH/CĐ → tối đa 60 ngày
        'intermediate' => 1,   // Trung cấp, CN kỹ thuật → tối đa 30 ngày
        'simple'       => 0,   // Lao động giản đơn → tối đa 6 ngày (~0.2 tháng, dùng 0)
    ],

    /*
     * Thử việc — lương theo hợp đồng thử việc.
     */

    /*
     * Thang cấp bậc O1–O7 (band A–D).
     * O1–O4: quản lý · O5–O6: nhân viên · O7: công nhân.
     */
    'job_bands' => ['A', 'B', 'C', 'D'],

    'job_categories' => [
        'manager' => 'Quản lý (O1–O4)',
        'employee' => 'Nhân viên (O5–O6)',
        'worker' => 'Công nhân (O7)',
    ],

    'job_grades' => [
        [
            'grade' => 'O1',
            'name' => 'O1 — Lãnh đạo cấp cao',
            'category' => 'manager',
            'rank_base' => 100,
            'salary_min' => 80_000_000,
            'salary_max' => 150_000_000,
            'salary_step' => 5_000_000,
            'description' => 'Ban TGĐ / Phó TGĐ / C-level',
        ],
        [
            'grade' => 'O2',
            'name' => 'O2 — Giám đốc',
            'category' => 'manager',
            'rank_base' => 200,
            'salary_min' => 50_000_000,
            'salary_max' => 90_000_000,
            'salary_step' => 4_000_000,
            'description' => 'Giám đốc đơn vị / Phó giám đốc',
        ],
        [
            'grade' => 'O3',
            'name' => 'O3 — Trưởng phòng',
            'category' => 'manager',
            'rank_base' => 300,
            'salary_min' => 30_000_000,
            'salary_max' => 55_000_000,
            'salary_step' => 3_000_000,
            'description' => 'Trưởng phòng / Quản lý cấp phòng',
        ],
        [
            'grade' => 'O4',
            'name' => 'O4 — Trưởng nhóm',
            'category' => 'manager',
            'rank_base' => 400,
            'salary_min' => 22_000_000,
            'salary_max' => 40_000_000,
            'salary_step' => 2_000_000,
            'description' => 'Trưởng nhóm / Giám sát trực tiếp',
        ],
        [
            'grade' => 'O5',
            'name' => 'O5 — Nhân viên chuyên môn',
            'category' => 'employee',
            'rank_base' => 500,
            'salary_min' => 12_000_000,
            'salary_max' => 28_000_000,
            'salary_step' => 1_500_000,
            'description' => 'Chuyên viên / Kỹ sư / Chuyên gia',
        ],
        [
            'grade' => 'O6',
            'name' => 'O6 — Nhân viên nghiệp vụ',
            'category' => 'employee',
            'rank_base' => 600,
            'salary_min' => 8_000_000,
            'salary_max' => 18_000_000,
            'salary_step' => 1_000_000,
            'description' => 'Nhân viên hành chính / nghiệp vụ',
        ],
        [
            'grade' => 'O7',
            'name' => 'O7 — Công nhân',
            'category' => 'worker',
            'rank_base' => 700,
            'salary_min' => 5_000_000,
            'salary_max' => 12_000_000,
            'salary_step' => 500_000,
            'description' => 'Công nhân sản xuất / vận hành',
        ],
    ],

    /*
     * Ca làm việc mẫu — ca đêm tuân thủ Điều 106, 108 BLLĐ 2019.
     */
    'work_shift_presets' => [
        'night' => [
            'code' => 'CA-DEM',
            'name' => 'Ca đêm (22:00 – 07:00)',
            'start_time' => '22:00:00',
            'end_time' => '07:00:00',
            'break_minutes' => 45,
            'is_night_shift' => true,
            'crosses_midnight' => true,
            'standard_hours' => 8,
            'legal_reference' => 'BLLĐ 2019 Điều 106 (+30% phụ cấp đêm), Điều 108 (nghỉ giữa ca ≥45 phút/ca đêm)',
        ],
        'day' => [
            'code' => 'CA-HC',
            'name' => 'Ca hành chính',
            'start_time' => '08:30:00',
            'end_time' => '17:30:00',
            'break_minutes' => 60,
            'is_night_shift' => false,
            'crosses_midnight' => false,
            'standard_hours' => 8,
            'legal_reference' => 'BLLĐ 2019 Điều 104–105 (8h/ngày, nghỉ giữa ca ≥30 phút)',
        ],
    ],

    /*
     * Khung giờ làm đêm (Điều 106 BLLĐ 2019).
     */
    'night_window' => [
        'start_hour' => 22,
        'end_hour' => 6,
    ],

    /*
     * Loại nghỉ theo BLLĐ 2019 — payroll_category: company_paid | company_unpaid | bhxh_benefit
     */
    'leave_types' => [
        [
            'code' => 'PHEP',
            'name' => 'Nghỉ phép năm',
            'payroll_category' => 'company_paid',
            'is_paid' => true,
            'cell_symbol' => 'PN',
            'legal_reference' => 'BLLĐ 2019 Điều 113',
            'description' => 'Nghỉ hằng năm hưởng nguyên lương — cộng công tính lương.',
            'sort_order' => 10,
        ],
        [
            'code' => 'LE',
            'name' => 'Nghỉ lễ, Tết',
            'payroll_category' => 'company_paid',
            'is_paid' => true,
            'cell_symbol' => 'LE',
            'legal_reference' => 'BLLĐ 2019 Điều 112',
            'description' => 'Ngày lễ hưởng nguyên lương (thường tự động từ lịch lễ).',
            'sort_order' => 12,
        ],
        [
            'code' => 'VR_L',
            'name' => 'Nghỉ việc riêng hưởng lương',
            'payroll_category' => 'company_paid',
            'is_paid' => true,
            'cell_symbol' => 'VR',
            'legal_reference' => 'BLLĐ 2019 Điều 115',
            'description' => 'Kết hôn, tang lễ gia đình… theo số ngày luật định.',
            'sort_order' => 15,
        ],
        [
            'code' => 'CUOI',
            'name' => 'Nghỉ kết hôn (NLĐ)',
            'payroll_category' => 'company_paid',
            'is_paid' => true,
            'cell_symbol' => 'C',
            'legal_reference' => 'BLLĐ 2019 Điều 115 — 3 ngày',
            'sort_order' => 18,
        ],
        [
            'code' => 'HH',
            'name' => 'Nghỉ hiếu hỷ',
            'payroll_category' => 'company_paid',
            'is_paid' => true,
            'cell_symbol' => 'HH',
            'legal_reference' => 'BLLĐ 2019 Điều 115 — 3 ngày',
            'sort_order' => 20,
        ],
        [
            'code' => 'BU',
            'name' => 'Nghỉ bù',
            'payroll_category' => 'company_paid',
            'is_paid' => true,
            'cell_symbol' => 'NB',
            'legal_reference' => 'Thỏa thuận / quy chế công ty',
            'description' => 'Bù cho ngày làm thêm hoặc sắp xếp ca — theo quy chế.',
            'sort_order' => 25,
        ],
        [
            'code' => 'CONG_TAC',
            'name' => 'Công tác',
            'payroll_category' => 'company_paid',
            'is_paid' => true,
            'cell_symbol' => 'CT',
            'legal_reference' => 'Theo quy chế công ty',
            'sort_order' => 30,
        ],
        [
            'code' => 'CT_CL',
            'name' => 'Nghỉ theo chế độ công ty (có lương)',
            'payroll_category' => 'company_paid',
            'is_paid' => true,
            'cell_symbol' => 'NC',
            'legal_reference' => 'Quy chế / phúc lợi công ty',
            'description' => 'Sinh nhật, du lịch công ty, khám SK, đào tạo…',
            'sort_order' => 35,
        ],
        [
            'code' => 'CONG_TY',
            'name' => 'Nghỉ việc công ty',
            'payroll_category' => 'company_paid',
            'is_paid' => true,
            'cell_symbol' => 'NC',
            'legal_reference' => 'Theo quy chế công ty',
            'sort_order' => 36,
        ],
        [
            'code' => 'TRAINING',
            'name' => 'Nghỉ đào tạo (có lương)',
            'payroll_category' => 'company_paid',
            'is_paid' => true,
            'cell_symbol' => 'DT',
            'legal_reference' => 'Theo quy chế công ty',
            'sort_order' => 38,
        ],
        [
            'code' => 'KINH_NGUYET',
            'name' => 'Nghỉ kinh nguyệt',
            'payroll_category' => 'company_paid',
            'is_paid' => true,
            'cell_symbol' => 'KN',
            'legal_reference' => 'Theo quy chế công ty / NQ 2023',
            'sort_order' => 40,
        ],
        [
            'code' => 'VR_KL',
            'name' => 'Nghỉ việc riêng không lương (luật)',
            'payroll_category' => 'company_unpaid',
            'is_paid' => false,
            'cell_symbol' => 'VRK',
            'legal_reference' => 'BLLĐ 2019 Điều 115 — 1 ngày',
            'sort_order' => 50,
        ],
        [
            'code' => 'KL',
            'name' => 'Nghỉ không lương có phép',
            'payroll_category' => 'company_unpaid',
            'is_paid' => false,
            'cell_symbol' => 'KL',
            'legal_reference' => 'BLLĐ 2019 Điều 114 — thỏa thuận',
            'description' => 'Trừ lương = LCB / công chuẩn × số ngày nghỉ.',
            'sort_order' => 60,
        ],
        [
            'code' => 'VIEC_RIENG',
            'name' => 'Nghỉ việc cá nhân không lương',
            'payroll_category' => 'company_unpaid',
            'is_paid' => false,
            'cell_symbol' => 'VR',
            'legal_reference' => 'Theo quy chế công ty',
            'sort_order' => 65,
        ],
        [
            'code' => 'QP',
            'name' => 'Nghỉ quá phép',
            'payroll_category' => 'company_unpaid',
            'is_paid' => false,
            'cell_symbol' => 'QP',
            'legal_reference' => 'Quy chế công ty',
            'description' => 'Phần vượt số ngày phép còn lại — xử lý không lương.',
            'sort_order' => 68,
        ],
        [
            'code' => 'OM',
            'name' => 'Nghỉ ốm (BHXH)',
            'payroll_category' => 'bhxh_benefit',
            'is_paid' => false,
            'day_count_mode' => 'calendar',
            'cell_symbol' => 'Ô',
            'legal_reference' => 'Luật BHXH — chế độ ốm đau',
            'description' => 'Công ty không trả lương; hưởng trợ cấp BHXH nếu đủ điều kiện.',
            'sort_order' => 70,
        ],
        [
            'code' => 'TS',
            'name' => 'Nghỉ thai sản (BHXH)',
            'payroll_category' => 'bhxh_benefit',
            'is_paid' => false,
            'day_count_mode' => 'calendar',
            'cell_symbol' => 'TS',
            'legal_reference' => 'BLLĐ 2019 Điều 139–141',
            'description' => 'Chế độ thai sản do BHXH chi trả.',
            'sort_order' => 80,
        ],
        [
            'code' => 'CON_OM',
            'name' => 'Nghỉ chăm con ốm (BHXH)',
            'payroll_category' => 'bhxh_benefit',
            'is_paid' => false,
            'day_count_mode' => 'calendar',
            'cell_symbol' => 'CO',
            'legal_reference' => 'Luật BHXH',
            'sort_order' => 85,
        ],
    ],

    'military_service_statuses' => [
        'completed' => 'Đã hoàn thành nghĩa vụ',
        'exempted' => 'Miễn',
        'not_applicable' => 'Không áp dụng',
        'not_yet' => 'Chưa thực hiện',
    ],

    'education_levels' => [
        'secondary' => 'Trung học phổ thông',
        'college' => 'Cao đẳng',
        'university' => 'Đại học',
        'master' => 'Thạc sĩ',
        'doctorate' => 'Tiến sĩ',
        'vocational' => 'Trung cấp nghề',
        'other' => 'Khác',
    ],
];
