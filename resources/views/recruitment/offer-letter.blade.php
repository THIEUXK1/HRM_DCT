<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Thư mời nhận việc — {{ $offer->candidate->full_name }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 720px; margin: 40px auto; color: #1e293b; line-height: 1.6; }
        h1 { font-size: 1.25rem; text-align: center; text-transform: uppercase; }
        .meta { margin: 24px 0; }
        .sign { margin-top: 48px; }
        @media print { body { margin: 20px; } }
    </style>
</head>
<body>
    <h1>Thư mời nhận việc / Offer Letter</h1>
    <p>Kính gửi: <strong>{{ $offer->candidate->full_name }}</strong></p>
    <div class="meta">
        <p>Công ty: <strong>{{ $offer->candidate->company->name ?? '—' }}</strong></p>
        <p>Vị trí: <strong>{{ $offer->candidate->jobPost->title ?? 'Theo JD đã thông báo' }}</strong></p>
        <p>Mức lương đề xuất: <strong>{{ number_format($offer->salary_base, 0, ',', '.') }} VND/tháng</strong></p>
        <p>Loại hợp đồng: <strong>{{ config('recruitment.contract_types.'.$offer->contract_type, $offer->contract_type) }}</strong></p>
        <p>Ngày dự kiến bắt đầu: <strong>{{ $offer->start_date?->format('d/m/Y') }}</strong></p>
        @if($offer->letter_notes)
            <p>Ghi chú: {{ $offer->letter_notes }}</p>
        @endif
    </div>
    <p>Chúng tôi trân trọng mời bạn tham gia làm việc tại công ty với các điều khoản nêu trên. Vui lòng xác nhận đồng ý qua hệ thống HCM hoặc phản hồi bằng văn bản.</p>
    <div class="sign">
        <p>Trân trọng,</p>
        <p><strong>Phòng Nhân sự</strong></p>
        <p>Ngày lập: {{ now()->format('d/m/Y') }}</p>
    </div>
</body>
</html>
