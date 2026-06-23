<?php

return [
    'entity_type_labels' => [
        'recruitment_request' => 'Yêu cầu tuyển dụng',
        'leave_request' => 'Nghỉ phép',
        'overtime_request' => 'Tăng ca',
    ],

    'request_statuses' => [
        'draft' => 'Nháp',
        'pending_approval' => 'Chờ duyệt headcount',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối',
        'closed' => 'Đóng',
    ],

    'job_post_statuses' => [
        'draft' => 'Nháp',
        'published' => 'Đang đăng',
        'closed' => 'Đã đóng',
    ],

    'candidate_stages' => [
        'applied' => 'Mới ứng tuyển',
        'screening' => 'Sàng lọc',
        'interview' => 'Phỏng vấn',
        'offer' => 'Offer',
        'hired' => 'Đã tuyển',
        'rejected' => 'Từ chối',
        'talent_pool' => 'Talent pool',
    ],

    'offer_statuses' => [
        'pending' => 'Chờ phản hồi',
        'accepted' => 'Ứng viên đồng ý',
        'declined' => 'Từ chối',
        'withdrawn' => 'Thu hồi',
    ],

    'interview_statuses' => [
        'scheduled' => 'Đã lên lịch',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Hủy',
    ],

    'document_types' => [
        'cv' => 'CV / Hồ sơ',
        'cover_letter' => 'Thư xin việc',
        'certificate' => 'Bằng cấp / Chứng chỉ',
        'other' => 'Khác',
    ],

    'scorecard_criteria' => [
        'Chuyên môn / Kỹ năng',
        'Giao tiếp',
        'Phù hợp văn hóa',
        'Kinh nghiệm thực tế',
        'Động lực / Thái độ',
    ],

    'contract_types' => [
        'probation' => 'Thử việc',
        'fixed_term' => 'Xác định thời hạn',
        'indefinite' => 'Không xác định thời hạn',
    ],
];
