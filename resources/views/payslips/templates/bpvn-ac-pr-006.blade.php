<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Phiếu lương — {{ $meta['period_month'] }}/{{ $meta['period_year'] }} — {{ $result->employee->full_name ?? '' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            font-size: 8pt;
            color: #111;
            margin: 0 auto;
            padding: 8px;
            width: 148mm;
            max-width: 148mm;
            line-height: 1.25;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 6px;
            margin-bottom: 4px;
        }
        .company-vi {
            font-weight: 700;
            font-size: 9pt;
            text-transform: uppercase;
            flex: 1;
            line-height: 1.2;
        }
        .doc-code {
            font-size: 7pt;
            color: #4b5563;
            text-align: right;
            white-space: nowrap;
        }
        .title {
            text-align: center;
            font-weight: 600;
            margin: 4px 0 6px;
            font-size: 9pt;
        }
        table.payslip {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.payslip th,
        table.payslip td {
            border: 1px solid #333;
            padding: 2px 3px;
            vertical-align: top;
            font-size: 7.5pt;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        table.payslip th {
            background: #f3f4f6;
            text-align: center;
            font-size: 7pt;
        }
        .col-stt { width: 7%; text-align: center; }
        .col-label { width: 53%; }
        .col-amount {
            width: 40%;
            text-align: right;
            white-space: nowrap;
        }
        .col-value-text {
            width: 40%;
            text-align: right;
            white-space: pre-line;
            line-height: 1.2;
            font-size: 7pt;
        }
        .footer {
            margin-top: 6px;
            font-size: 7pt;
        }
        .notes {
            margin-top: 4px;
            border: 1px solid #333;
            min-height: 24px;
            padding: 3px 4px;
            font-size: 7pt;
        }
        .print-btn {
            margin-bottom: 8px;
            padding: 6px 12px;
            cursor: pointer;
        }
        @media print {
            @page {
                size: A5 portrait;
                margin: 4mm;
            }
            html, body {
                width: 140mm;
                max-width: 140mm;
                padding: 0;
                margin: 0 auto;
            }
            .no-print { display: none !important; }
            table.payslip th,
            table.payslip td {
                padding: 1px 2px;
            }
        }
    </style>
</head>
<body>
    <button type="button" class="print-btn no-print" onclick="window.print()">In phiếu lương</button>

    <div class="header">
        <div class="company-vi">{{ $meta['company_name_vi'] }}</div>
        <div class="doc-code">{{ $meta['doc_code'] }}</div>
    </div>

    <div class="title">
        Phiếu lương tháng {{ str_pad((string) $meta['period_month'], 2, '0', STR_PAD_LEFT) }} năm {{ $meta['period_year'] }}
    </div>

    <table class="payslip">
        <thead>
            <tr>
                <th class="col-stt">STT</th>
                <th class="col-label">Tiêu chí</th>
                <th class="col-amount">Số tiền / Giá trị</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
                @php($isText = ($line['type'] ?? 'money') === 'text')
                <tr>
                    <td class="col-stt">{{ $line['stt'] }}</td>
                    <td class="col-label">{{ $line['label_vi'] }}</td>
                    <td class="{{ $isText ? 'col-value-text' : 'col-amount' }}">{{ $line['display'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="notes">
        <strong>Ghi chú:</strong> {{ $notes }}
    </div>

    <div class="footer">
        <span>
            @if(!empty($meta['published_at']))
                Phát hành: {{ $meta['published_at'] }}
            @endif
        </span>
    </div>
</body>
</html>
