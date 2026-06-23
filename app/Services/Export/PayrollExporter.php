<?php



namespace App\Services\Export;



use App\Models\PayrollCycle;



class PayrollExporter

{

    public function download(PayrollCycle $cycle): \Symfony\Component\HttpFoundation\StreamedResponse

    {

        $results = $cycle->results()->with('employee.department')->get();



        $headers = [

            'Mã NV', 'Họ tên', 'Phòng ban',

            'Công TV', 'Công CT',

            'Lương TV', 'Lương CT',

            'OT TV', 'OT CT', 'OT tổng',

            'CC TV', 'CC CT',

            'NS TV', 'NS CT', 'Thưởng NS',

            'Phụ cấp TV', 'Phụ cấp CT', 'Phụ cấp tổng',

            'Thôi việc',

            'Gross', 'BHXH', 'Thuế TNCN', 'Thực lĩnh',

        ];



        $colWidths = [12, 26, 18, 8, 8, 12, 12, 10, 10, 10, 10, 10, 10, 10, 12, 12, 12, 12, 8, 14, 12, 12, 14];



        $rows = [$headers];

        foreach ($results as $r) {

            $b = is_array($r->breakdown) ? $r->breakdown : (json_decode($r->breakdown ?? '{}', true) ?? []);

            $allowanceProb = 0.0;

            $allowanceOff = 0.0;

            foreach ($b['phased_allowances'] ?? [] as $item) {

                $allowanceProb += (float) ($item['probation'] ?? 0);

                $allowanceOff += (float) ($item['official'] ?? 0);

            }



            $rows[] = [

                $r->employee?->employee_code,

                $r->employee?->full_name,

                $r->employee?->department?->name ?? '',

                $b['probation_work_days'] ?? 0,

                $b['official_work_days'] ?? 0,

                $b['probation_base_pay'] ?? 0,

                $b['official_base_pay'] ?? 0,

                $b['ot_probation_pay'] ?? 0,

                $b['ot_official_pay'] ?? 0,

                $b['ot_pay'] ?? 0,

                $b['diligence_probation_pay'] ?? 0,

                $b['diligence_official_pay'] ?? 0,

                $b['performance_bonus_probation'] ?? 0,

                $b['performance_bonus_official'] ?? 0,

                $b['performance_bonus'] ?? 0,

                $allowanceProb,

                $allowanceOff,

                $b['allowance_earnings'] ?? 0,

                ($b['is_terminated_in_month'] ?? false) ? 'Có' : '',

                $r->gross_salary,

                $r->bhxh_employee,

                $r->pit_amount,

                $r->net_salary,

            ];

        }



        $sheet = "Bảng lương {$cycle->period}";

        $xlsx = new SimpleXlsxWriter();

        $xlsx->addSheet($sheet, $rows, $colWidths);



        return $xlsx->download("bang-luong-{$cycle->period}.xlsx");

    }

}


