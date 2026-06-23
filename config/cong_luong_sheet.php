<?php

/**
 * Bảng « công và lương » BestPacific — layout cột A→AO (UI/export tiếng Việt).
 * Import Excel BP vẫn đọc cột C/E/AN/AO mã Trung — lưu meta, không hiển thị.
 */
return [
    'template_path' => storage_path('app/templates/cong-va-luong-mau.xlsx'),
    'reference_period' => '2026-04',

    /** Sheet công — mã cột Excel giữ nguyên (bỏ C, E trên UI). */
    'cong_columns' => [
        'A' => ['key' => 'stt', 'label_vi' => 'STT', 'align' => 'center'],
        'B' => ['key' => 'employee_code', 'label_vi' => 'Mã thẻ', 'sticky' => true],
        'D' => ['key' => 'full_name', 'label_vi' => 'Họ tên'],
        'F' => ['key' => 'department', 'label_vi' => 'Bộ phận'],
        'G' => ['key' => 'job_level', 'label_vi' => 'Cấp bậc', 'align' => 'center'],
        'H' => ['key' => 'payable_work_days', 'label_vi' => 'Ngày công tính lương', 'align' => 'right', 'numeric' => true],
        'I' => ['key' => 'paid_holiday_leave_days', 'label_vi' => 'Ngày nghỉ có hưởng lương', 'align' => 'right', 'numeric' => true],
        'J' => ['key' => 'minimum_wage_days', 'label_vi' => 'Ngày hưởng lương tối thiểu vùng', 'align' => 'right', 'numeric' => true],
        'K' => ['key' => 'base_salary_paid_leave_days', 'label_vi' => 'Nghỉ hưởng lương cơ bản', 'align' => 'right', 'numeric' => true],
        'L' => ['key' => 'holiday_days', 'label_vi' => 'Số ngày lễ', 'align' => 'right', 'numeric' => true],
        'M' => ['key' => 'night_hours_summary', 'label_vi' => 'Số giờ làm đêm', 'align' => 'right', 'numeric' => true],
        'N' => ['key' => 'business_trip_days', 'label_vi' => 'Số ngày/đêm công tác (Đi)', 'align' => 'right', 'numeric' => true],
        'O' => ['key' => 'menstrual_leave_hours', 'label_vi' => 'Số giờ nghỉ kinh nguyệt', 'align' => 'right', 'numeric' => true],
        'P' => ['key' => 'ot_night_weekday', 'label_vi' => 'Số giờ TC đêm ngày thường', 'align' => 'right', 'numeric' => true],
        'Q' => ['key' => 'ot_night_weekend', 'label_vi' => 'Số giờ TC đêm ngày nghỉ', 'align' => 'right', 'numeric' => true],
        'R' => ['key' => 'ot_night_paid_holiday', 'label_vi' => 'Số giờ TC đêm ngày nghỉ hưởng lương', 'align' => 'right', 'numeric' => true],
        'S' => ['key' => 'ot_day_weekday', 'label_vi' => 'Số giờ TC ngày thường (ca ngày)', 'align' => 'right', 'numeric' => true],
        'T' => ['key' => 'ot_day_weekend', 'label_vi' => 'Số giờ TC ngày nghỉ (ca ngày)', 'align' => 'right', 'numeric' => true],
        'U' => ['key' => 'ot_day_annual_leave', 'label_vi' => 'Số giờ TC ngày phép năm (ca ngày)', 'align' => 'right', 'numeric' => true],
        'V' => ['key' => 'ot_night_annual_leave', 'label_vi' => 'Số giờ TC đêm ngày phép năm', 'align' => 'right', 'numeric' => true],
        'W' => ['key' => 'ot_day_holiday', 'label_vi' => 'Số giờ TC ngày lễ/tết (ca ngày)', 'align' => 'right', 'numeric' => true],
        'X' => ['key' => 'ot_night_holiday', 'label_vi' => 'Số giờ TC đêm ngày lễ/tết', 'align' => 'right', 'numeric' => true],
        'Y' => ['key' => 'leave_annual', 'label_vi' => 'Nghỉ phép năm (ngày)', 'align' => 'right', 'numeric' => true],
        'Z' => ['key' => 'leave_personal', 'label_vi' => 'Nghỉ việc riêng', 'align' => 'right', 'numeric' => true],
        'AA' => ['key' => 'leave_wedding', 'label_vi' => 'Nghỉ kết hôn', 'align' => 'right', 'numeric' => true],
        'AB' => ['key' => 'leave_maternity', 'label_vi' => 'Nghỉ thai sản', 'align' => 'right', 'numeric' => true],
        'AC' => ['key' => 'leave_funeral', 'label_vi' => 'Nghỉ việc tang', 'align' => 'right', 'numeric' => true],
        'AD' => ['key' => 'leave_sick', 'label_vi' => 'Nghỉ ốm', 'align' => 'right', 'numeric' => true],
        'AE' => ['key' => 'leave_unauthorized', 'label_vi' => 'Nghỉ không phép', 'align' => 'right', 'numeric' => true],
        'AF' => ['key' => 'leave_company', 'label_vi' => 'Nghỉ việc công ty', 'align' => 'right', 'numeric' => true],
        'AG' => ['key' => 'travel_support_flag', 'label_vi' => 'Hỗ trợ đi lại (50% lương BH)', 'align' => 'center'],
        'AH' => ['key' => 'saturday_duty_hours', 'label_vi' => 'Giờ trực T7', 'align' => 'right', 'numeric' => true],
        'AI' => ['key' => 'resignation_note', 'label_vi' => 'Ghi chú (nghỉ việc)'],
        'AJ' => ['key' => 'resignation_days', 'label_vi' => 'Số ngày nghỉ việc', 'align' => 'right', 'numeric' => true],
        'AK' => ['key' => 'days_not_joined', 'label_vi' => 'Số ngày chưa vào', 'align' => 'right', 'numeric' => true],
        'AL' => ['key' => 'join_date', 'label_vi' => 'Ngày vào', 'align' => 'center'],
        'AM' => ['key' => 'standard_work_days', 'label_vi' => 'Ngày công tiêu chuẩn', 'align' => 'right', 'numeric' => true],
        'AN' => ['key' => 'employment_status', 'label_vi' => 'Trạng thái HĐ', 'align' => 'center'],
        'AO' => ['key' => 'employment_active', 'label_vi' => 'Tình trạng làm việc', 'align' => 'center'],
    ],

    'luong_columns' => [
        'A' => ['key' => 'stt', 'label_vi' => 'STT', 'align' => 'center'],
        'B' => ['key' => 'employee_code', 'label_vi' => 'Mã thẻ', 'sticky' => true],
        'D' => ['key' => 'full_name', 'label_vi' => 'Họ tên'],
        'F' => ['key' => 'department', 'label_vi' => 'Bộ phận'],
        'G' => ['key' => 'job_level', 'label_vi' => 'Cấp bậc', 'align' => 'center', 'numeric' => true],
        'H' => ['key' => 'base_salary', 'label_vi' => 'Lương cơ bản', 'align' => 'right', 'numeric' => true],
        'I' => ['key' => 'allowance_position', 'label_vi' => 'Trợ cấp CV', 'align' => 'right', 'numeric' => true],
        'J' => ['key' => 'allowance_other', 'label_vi' => 'Trợ cấp khác', 'align' => 'right', 'numeric' => true],
        'K' => ['key' => 'allowance_phone', 'label_vi' => 'Trợ cấp Điện thoại', 'align' => 'right', 'numeric' => true],
        'L' => ['key' => 'allowance_living', 'label_vi' => 'Hỗ trợ đời sống khó khăn', 'align' => 'right', 'numeric' => true],
        'M' => ['key' => 'allowance_housing', 'label_vi' => 'Hỗ trợ Nhà ở', 'align' => 'right', 'numeric' => true],
        'N' => ['key' => 'allowance_petrol', 'label_vi' => 'Hỗ trợ xăng xe', 'align' => 'right', 'numeric' => true],
        'O' => ['key' => 'allowance_meal', 'label_vi' => 'Bồi dưỡng bữa ăn ca', 'align' => 'right', 'numeric' => true],
        'P' => ['key' => 'allowance_environment', 'label_vi' => 'Hỗ trợ tiền nước mát (Từ tháng 6 Tháng 10 hàng năm)', 'align' => 'right', 'numeric' => true],
        'Q' => ['key' => 'allowance_housing_distance', 'label_vi' => 'Hỗ trợ Nhà trợ (CNV có hộ khẩu xa 30 km trở lên)', 'align' => 'right', 'numeric' => true],
        'R' => ['key' => 'travel_support_amount', 'label_vi' => 'Số tiền Hỗ trợ đi lại', 'align' => 'right', 'numeric' => true],
        'S' => ['key' => 'travel_eligible', 'label_vi' => 'Được hỗ trợ đi lại', 'align' => 'center'],
        'T' => ['key' => 'allowance_probation_insurance', 'label_vi' => 'BHXH (17,5%), BHYT (3%), BHTN (1%), Cty chi trả cho NLĐ', 'align' => 'right', 'numeric' => true],
        'U' => ['key' => 'allowance_childcare', 'label_vi' => 'Hỗ trợ gửi trẻ con nhỏ dưới 72 tháng', 'align' => 'right', 'numeric' => true],
        'V' => ['key' => 'allowance_firefighting', 'label_vi' => 'Trợ cấp đội PCCC', 'align' => 'right', 'numeric' => true],
        'W' => ['key' => 'allowance_safety', 'label_vi' => 'Trợ cấp an ninh vệ sinh', 'align' => 'right', 'numeric' => true],
        'X' => ['key' => 'allowance_community', 'label_vi' => 'Hỗ trợ cộng viên', 'align' => 'right', 'numeric' => true],
        'Y' => ['key' => 'allowance_trip_housing', 'label_vi' => 'Hỗ trợ nhà ở đi công tác', 'align' => 'right', 'numeric' => true],
        'Z' => ['key' => 'allowance_health_check', 'label_vi' => 'Hỗ trợ phí khám sức khỏe nhân việc', 'align' => 'right', 'numeric' => true],
        'AA' => ['key' => 'incentive_bonus', 'label_vi' => 'Thưởng khuyến khích CNV ổn định công việc', 'align' => 'right', 'numeric' => true],
        'AB' => ['key' => 'notes', 'label_vi' => 'Ghi chú (Ngày nghỉ việc)'],
    ],

    /** Nhãn hiển thị UI / export (tiếng Việt). */
    'employment_status_labels' => [
        'probation' => 'Thử việc',
        'official' => 'Chính thức',
        'mixed' => 'Chính thức',
    ],

    'employment_active_labels' => [
        'active' => 'Đang làm việc',
        'inactive' => 'Nghỉ việc',
    ],

    /** Mã cột Excel gốc BP — chỉ dùng khi import file mẫu Trung/VN. */
    'import_status_codes' => [
        'probation' => '试用',
        'official' => '正式',
        'mixed' => '正式',
    ],
];
