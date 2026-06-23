<?php

/**
 * Mẫu phiếu lương — Phase 1 map breakdown hiện có; dòng chưa có dữ liệu = 0.
 */
return [
    'default' => 'bpvn-ac-pr-006',

    'templates' => [
        'bpvn-ac-pr-006' => [
            'code' => 'BPVN-AC-PR-006',
            'name' => 'Phiếu lương BPVN (tiếng Việt)',
            'view' => 'payslips.templates.bpvn-ac-pr-006',
            'doc_code' => 'BPVN-AC-PR-006 A/1',
        ],
        'simple' => [
            'code' => 'SIMPLE',
            'name' => 'Phiếu lương đơn giản',
            'view' => 'payslips.show',
        ],
    ],

    /**
     * Map breakdown / formula target_field → STT dòng trợ cấp (10–22).
     * Phase 2–3 bổ sung thêm key khi có salary_components.
     */
    'allowance_field_map' => [
        'allowance_position' => '10',
        'allowance_other' => '11',
        'allowance_phone' => '12',
        'allowance_living' => '13',
        'allowance_housing' => '14',
        'allowance_petrol' => '15',
        'allowance_meal' => '16',
        'allowance_environment' => '17',
        'allowance_housing_distance' => '18',
        'allowance_childcare' => '19',
        'allowance_firefighting' => '20',
        'allowance_safety' => '21',
        'allowance_health_check' => '22',
        'allowance_trip_housing' => '22a',
        'allowance_probation_insurance' => '22a',
    ],

    'special_field_map' => [
        'diligence_bonus_pay' => '35a',
        'performance_bonus' => '44',
        'termination_leave_payout' => '43',
        'prev_month_adjustment' => '43',
        'lunch_allowance' => '44a',
        'travel_support' => '24',
        'incentive_bonus' => '35a',
    ],
];
