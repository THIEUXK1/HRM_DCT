<?php

/**
 * Cấu hình bảng công mở rộng (BestPacific / BPVN) — Phase 2a.
 * Map leave_types.code → key trong attendance_breakdown.leave_by_type.
 */
return [
    'leave_type_breakdown_map' => [
        'PHEP' => 'annual',
        'PN' => 'annual',
        'LE' => 'holiday',
        'VR_L' => 'personal_paid',
        'VIEC_RIENG' => 'personal',
        'VR_KL' => 'personal_unpaid',
        'CUOI' => 'wedding',
        'TS' => 'maternity',
        'HH' => 'funeral',
        'OM' => 'sick',
        'CON_OM' => 'child_sick',
        'KL' => 'unpaid',
        'QP' => 'excess_leave',
        'CONG_TY' => 'company',
        'CT_CL' => 'company',
        'TRAINING' => 'training',
        'CONG_TAC' => 'business_trip',
        'KINH_NGUYET' => 'menstrual',
        'BU' => 'compensatory',
    ],

    /**
     * Cột OT sheet công Excel — giá trị = giờ.
     * N1: TC đêm ngày thường không TC ngày trước = 150%+30%+20%×100% = 200%
     * N2: TC đêm ngày thường sau TC ngày        = 150%+30%+20%×150% = 210%
     * (Nghị định 145/2020/NĐ-CP)
     */
    'ot_grid_keys' => [
        'night_weekday_n1'   => 'P1 — TC đêm ngày thường (200%, không TC ngày)',
        'night_weekday_n2'   => 'P2 — TC đêm ngày thường (210%, có TC ngày)',
        'night_weekend'      => 'Q — TC đêm ngày nghỉ (270%)',
        'night_paid_holiday' => 'R — TC đêm ngày nghỉ có hưởng lương',
        'day_weekday'        => 'S — TC ngày thường ban ngày (150%)',
        'day_weekend'        => 'T — TC ngày nghỉ ban ngày (200%)',
        'day_annual_leave'   => 'U — TC ngày phép năm ban ngày',
        'night_annual_leave' => 'V — TC đêm ngày phép năm',
        'day_holiday'        => 'W — TC ngày lễ/Tết ban ngày (300%)',
        'night_holiday'      => 'X — TC đêm ngày lễ/Tết (390%)',
    ],

    /** Map ot_grid → STT phiếu lương BPVN (giờ). */
    'payslip_ot_hour_map' => [
        'day_weekday'        => '25',
        'day_weekend'        => '26',
        'day_holiday'        => '27',
        'day_annual_leave'   => '28',
        'night_weekday_n1'   => '30',
        'night_weekday_n2'   => '30b',
        'night_weekend'      => '31',
        'night_paid_holiday' => '32',
        'night_holiday'      => '32',
        'night_annual_leave' => '33',
    ],

    /** Map leave_by_type → STT phiếu lương (ngày). */
    'payslip_leave_day_map' => [
        'annual' => 'Y',
        'personal' => 'Z',
        'wedding' => 'AA',
        'maternity' => 'AB',
        'funeral' => 'AC',
        'sick' => 'AD',
        'unauthorized' => 'AE',
        'company' => 'AF',
    ],
];
