<!DOCTYPE html>

<html lang="vi">

<head>

    <meta charset="utf-8">

    <title>Phiếu lương — {{ $result->employee->full_name ?? '' }}</title>

    <style>

        body { font-family: 'Segoe UI', sans-serif; max-width: 148mm; margin: 8px auto; padding: 0 8px; color: #1e293b; font-size: 9pt; }

        h1 { font-size: 11pt; margin-bottom: 4px; }

        .meta { color: #64748b; font-size: 8pt; margin-bottom: 12px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; table-layout: fixed; }

        th, td { padding: 4px 5px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 8pt; overflow-wrap: anywhere; word-break: break-word; }

        th { background: #f8fafc; font-size: 7pt; text-transform: uppercase; color: #64748b; }

        .net { font-size: 11pt; font-weight: 700; color: #059669; }

        .section { font-size: 8pt; font-weight: 600; color: #475569; margin: 12px 0 6px; }

        .muted { color: #64748b; font-size: 7.5pt; }

        @media print {
            @page { size: A5 portrait; margin: 5mm; }
            body { margin: 0 auto; max-width: 140mm; }
            .no-print { display: none; }
        }

    </style>

</head>

<body>

    @php
        $b = is_array($result->breakdown) ? $result->breakdown : [];
        $pa = is_array($b['payslip_attendance'] ?? null) ? $b['payslip_attendance'] : [];
        $stdDays = (float) ($pa['standard_work_days'] ?? $b['standard_work_days'] ?? 0);
        $workDays = (float) ($pa['work_days'] ?? $b['work_days'] ?? 0);
        $payProb = (float) ($pa['payable_probation_days'] ?? $b['payable_probation_days'] ?? 0);
        $payOff = (float) ($pa['payable_official_days'] ?? $b['payable_official_days'] ?? 0);
        $payTotal = (float) ($pa['payable_total_days'] ?? ($payProb + $payOff));
        $hasPhase = !empty($pa['has_phase_split']) || !empty($b['has_phase_split']);
        $showAttendance = $workDays > 0 || $payTotal > 0 || ($b['base_pay_total'] ?? 0) > 0 || $hasPhase;
    @endphp

    <button class="no-print" onclick="window.print()" style="margin-bottom:16px;padding:8px 16px;">In phiếu lương</button>

    <h1>PHIẾU LƯƠNG</h1>

    <p class="meta">

        Kỳ: {{ $result->cycle->period ?? '—' }} ·

        Nhân viên: {{ $result->employee->full_name ?? '—' }} ({{ $result->employee->employee_code ?? '' }})

    </p>

    @if($showAttendance)

        <p class="section">Bảng công &amp; lương (khớp dữ liệu tính lương)</p>

        <table>

            <tr><th>Khoản</th><th>Công</th><th>Thành tiền (VND)</th></tr>

            <tr class="muted">
                <td colspan="3">
                    Đi làm {{ number_format($workDays, 1) }} · Công chuẩn {{ number_format($stdDays, 0) }}
                    · Công tính lương {{ number_format($payTotal, 1) }}
                    @if($hasPhase && ($payProb > 0 || $payOff > 0))
                        (TV {{ number_format($payProb, 1) }} + CT {{ number_format($payOff, 1) }})
                    @endif
                    @if(($pa['paid_leave_days'] ?? $b['paid_leave_days'] ?? 0) > 0)
                        · Phép CL {{ number_format($pa['paid_leave_days'] ?? $b['paid_leave_days'], 1) }}
                    @endif
                    @if(($pa['unpaid_leave_days'] ?? $b['unpaid_leave_days'] ?? 0) > 0)
                        · KL {{ number_format($pa['unpaid_leave_days'] ?? $b['unpaid_leave_days'], 1) }}
                    @endif
                </td>
            </tr>

            @if(($b['probation_work_days'] ?? $pa['probation_work_days'] ?? 0) > 0)

            <tr>

                <td>
                    @if(isset($b['probation_salary_rate']))
                        Lương thử việc ({{ number_format($b['probation_salary_rate'] * 100, 0) }}%)
                    @else
                        Lương thử việc
                    @endif
                    @if(!empty($b['has_phase_split']) && (($b['probation_paid_leave_days'] ?? 0) > 0 || ($b['probation_unpaid_leave_days'] ?? 0) > 0))
                        <span class="muted"> · phép CL {{ number_format($b['probation_paid_leave_days'] ?? 0, 1) }} · KL {{ number_format($b['probation_unpaid_leave_days'] ?? 0, 1) }}</span>
                    @endif
                </td>

                <td>{{ number_format($b['payable_probation_days'] ?? $b['probation_work_days'] ?? 0, 1) }}/{{ number_format($b['standard_work_days'] ?? 0, 0) }}</td>

                <td>{{ number_format($b['probation_base_pay'] ?? 0, 0, ',', '.') }}</td>

            </tr>

            @endif

            @if(($b['official_work_days'] ?? $pa['official_work_days'] ?? 0) > 0 || (!$hasPhase && $payOff > 0))

            <tr>

                <td>Lương chính thức
                    @if(!empty($b['has_phase_split']) && (($b['official_paid_leave_days'] ?? 0) > 0 || ($b['official_unpaid_leave_days'] ?? 0) > 0))
                        <span class="muted"> · phép CL {{ number_format($b['official_paid_leave_days'] ?? 0, 1) }} · KL {{ number_format($b['official_unpaid_leave_days'] ?? 0, 1) }}</span>
                    @elseif(($b['paid_leave_days'] ?? 0) > 0)
                        <span class="muted"> (gồm {{ number_format($b['paid_leave_days'], 1) }} ngày phép có lương)</span>
                    @endif
                </td>

                <td>{{ number_format($b['payable_official_days'] ?? $b['official_work_days'] ?? 0, 1) }}/{{ number_format($b['standard_work_days'] ?? 0, 0) }}</td>

                <td>{{ number_format($b['official_base_pay'] ?? 0, 0, ',', '.') }}</td>

            </tr>

            @endif

            @if(!$hasPhase && ($payProb + $payOff) <= 0 && ($b['base_pay_total'] ?? 0) > 0 && $workDays > 0)

            <tr>
                <td>Lương theo công tháng</td>
                <td>{{ number_format($workDays, 1) }}/{{ number_format($stdDays, 0) }}</td>
                <td>{{ number_format($b['base_pay_total'] ?? 0, 0, ',', '.') }}</td>
            </tr>

            @endif

            @if(($b['unpaid_leave_days'] ?? $pa['unpaid_leave_days'] ?? 0) > 0)

            <tr>

                <td colspan="3" class="muted">Nghỉ không hưởng lương: {{ number_format($b['unpaid_leave_days'], 1) }} ngày (không tính vào lương NLĐ)</td>

            </tr>

            @endif

            @if(($b['ot_pay'] ?? 0) > 0)

            <tr>

                <td>Tăng ca (TV: {{ number_format($b['ot_probation_hours'] ?? 0, 1) }}h · CT: {{ number_format($b['ot_official_hours'] ?? 0, 1) }}h)</td>

                <td>{{ number_format($b['ot_hours'] ?? 0, 1) }}h</td>

                <td>
                    @if(!empty($b['has_phase_split']) && (($b['ot_probation_pay'] ?? 0) > 0 || ($b['ot_official_pay'] ?? 0) > 0))
                        TV {{ number_format($b['ot_probation_pay'] ?? 0, 0, ',', '.') }}
                        · CT {{ number_format($b['ot_official_pay'] ?? 0, 0, ',', '.') }}
                        = {{ number_format($b['ot_pay'] ?? 0, 0, ',', '.') }}
                    @else
                        {{ number_format($b['ot_pay'] ?? 0, 0, ',', '.') }}
                    @endif
                </td>

            </tr>

            @endif

            @if(($b['performance_bonus'] ?? 0) > 0)
            <tr>
                <td>Thưởng năng suất / KPI (T-1)
                    @if(!empty($b['performance_bonus_split']))
                        (TV {{ number_format($b['performance_bonus_probation'] ?? 0, 0, ',', '.') }} · CT {{ number_format($b['performance_bonus_official'] ?? 0, 0, ',', '.') }})
                    @endif
                </td>
                <td>{{ number_format($b['performance_score_prev_month'] ?? 0, 0) }} điểm</td>
                <td>{{ number_format($b['performance_bonus'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            @endif

            @if(!empty($b['phased_allowances']))
            <tr><td colspan="3" class="muted">Phụ cấp tách theo giai đoạn</td></tr>
            @foreach($b['phased_allowances'] as $code => $item)
            <tr>
                <td>{{ str_replace('_', ' ', $code) }} ({{ $item['mode'] ?? '—' }})</td>
                <td>TV {{ number_format($item['probation'] ?? 0, 0, ',', '.') }} · CT {{ number_format($item['official'] ?? 0, 0, ',', '.') }}</td>
                <td>{{ number_format($item['total'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            @endif

            @if(!empty($b['formula_lines']))
            @foreach($b['formula_lines'] as $line)
            @if(($line['amount'] ?? 0) > 0 && ($line['target_field'] ?? '') === 'diligence_bonus_pay')
            <tr>
                <td>Thưởng chuyên cần
                    @if(!empty($b['has_phase_split']) && (($b['diligence_probation_pay'] ?? 0) > 0 || ($b['diligence_official_pay'] ?? 0) > 0))
                        (TV {{ number_format($b['diligence_probation_pay'] ?? 0, 0, ',', '.') }} · CT {{ number_format($b['diligence_official_pay'] ?? 0, 0, ',', '.') }})
                    @endif
                </td>
                <td>—</td>
                <td>{{ number_format($line['amount'], 0, ',', '.') }}</td>
            </tr>
            @elseif(($line['amount'] ?? 0) > 0)
            <tr>
                <td>{{ $line['name'] ?? $line['code'] }}</td>
                <td>—</td>
                <td>{{ number_format($line['amount'], 0, ',', '.') }}</td>
            </tr>
            @endif
            @endforeach
            @endif

            @if(!empty($b['is_terminated_in_month']))
            <tr>
                <td colspan="3" class="muted">Nghỉ việc ngày {{ $b['termination_date'] ?? '—' }} · Công đến ngày nghỉ: {{ number_format($b['work_days_until_exit'] ?? 0, 1) }}</td>
            </tr>
            @endif

        </table>

    @endif



    <p class="section">Tổng hợp khấu trừ</p>

    <table>

        <tr><th>Khoản</th><th>Số tiền (VND)</th></tr>

        <tr><td>Lương gross</td><td>{{ number_format($result->gross_salary, 0, ',', '.') }}</td></tr>

        <tr><td>BHXH (NLĐ) @if(($b['insurance_salary_applied'] ?? null) !== null)<span class="muted"> — mức đóng {{ number_format($b['insurance_salary_applied'] ?? 0, 0, ',', '.') }}</span>@endif</td><td>-{{ number_format($result->bhxh_employee, 0, ',', '.') }}</td></tr>

        <tr><td>Thuế TNCN</td><td>-{{ number_format($result->pit_amount, 0, ',', '.') }}</td></tr>

        <tr><td>Khấu trừ khác</td><td>-{{ number_format($result->other_deductions, 0, ',', '.') }}</td></tr>

        <tr><td><strong>Thực lĩnh</strong></td><td class="net">{{ number_format($result->net_salary, 0, ',', '.') }}</td></tr>

    </table>

    <p class="meta" style="margin-top:24px;">Phát hành: {{ optional($result->payslip?->published_at)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</p>

</body>

</html>

