<?php

/**
 * Map cột Excel « công và lương » (BestPacific) → hệ thống EHR — Phase 2d.
 */
return [
    'sheet_cong' => [
        'match_names' => ['công', 'cong', 'Công', 'CONG'],
        'header_row' => 3,
        'data_start_row' => 4,
        'employee_code_column' => 'B',
    ],

    'sheet_luong' => [
        'match_names' => ['lương', 'luong', 'Lương', 'LUONG'],
        'header_row' => 1,
        'data_start_row' => 3,
        'employee_code_column' => 'B',
    ],

    /** Cột sheet công → attendance_breakdown */
    'cong_scalar_columns' => [
        'H' => 'payable_work_days',
        'I' => 'paid_holiday_leave_days',
        'J' => 'minimum_wage_days',
        'K' => 'base_salary_paid_leave_days',
        'L' => 'holiday_days',
        'M' => 'night_hours_summary',
        'N' => 'business_trip_days',
        'O' => 'menstrual_leave_hours',
        'AH' => 'saturday_duty_hours',
        'AJ' => 'resignation_days',
        'AK' => 'days_not_joined',
        'AM' => 'standard_work_days',
    ],

    'cong_ot_columns' => [
        'P' => 'night_weekday',
        'Q' => 'night_weekend',
        'R' => 'night_paid_holiday',
        'S' => 'day_weekday',
        'T' => 'day_weekend',
        'U' => 'day_annual_leave',
        'V' => 'night_annual_leave',
        'W' => 'day_holiday',
        'X' => 'night_holiday',
    ],

    'cong_leave_columns' => [
        'Y' => 'annual',
        'Z' => 'personal',
        'AA' => 'wedding',
        'AB' => 'maternity',
        'AC' => 'funeral',
        'AD' => 'sick',
        'AE' => 'unauthorized',
        'AF' => 'company',
    ],

    /** Cột sheet lương → employee_payroll_allowances (catalog code) */
    'luong_allowance_columns' => [
        'I' => 'allowance_position',
        'J' => 'allowance_other',
        'K' => 'allowance_phone',
        'L' => 'allowance_living',
        'M' => 'allowance_housing',
        'N' => 'allowance_petrol',
        'O' => 'allowance_meal',
        'P' => 'allowance_environment',
        'Q' => 'allowance_housing_distance',
        'T' => 'allowance_probation_insurance',
        'U' => 'allowance_childcare',
        'V' => 'allowance_firefighting',
        'W' => 'allowance_safety',
        'X' => 'allowance_community',
        'Y' => 'allowance_trip_housing',
        'Z' => 'allowance_health_check',
        'AA' => 'incentive_bonus',
    ],

    'luong_travel_column' => 'R',
    'luong_travel_flag_column' => 'S',
    'luong_notes_column' => 'AB',
];
