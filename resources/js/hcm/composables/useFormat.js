export function useFormat() {
    const money = (value) =>
        new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value || 0);

    const date = (value) => {
        if (!value) return '—';
        return new Date(value).toLocaleDateString('vi-VN');
    };

    const datetime = (value) => {
        if (!value) return '—';
        return new Date(value).toLocaleString('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const statusLabel = (status) => {
        const map = {
            active: 'Đang làm việc',
            probation: 'Thử việc',
            training: 'Đang đào tạo',
            maternity_leave: 'Nghỉ thai sản',
            unpaid_leave: 'Nghỉ không lương',
            suspended: 'Đình chỉ',
            terminated: 'Nghỉ việc',
            resigned: 'Tự nghỉ',
            full_time: 'Toàn thời gian',
            part_time: 'Bán thời gian',
            internship: 'Thực tập',
            collaborator: 'Cộng tác viên',
            pending: 'Chờ duyệt',
            approved: 'Đã duyệt',
            rejected: 'Từ chối',
            draft: 'Nháp',
            calculated: 'Đã tính',
            locked: 'Đã khóa',
            applied: 'Mới ứng tuyển',
            screening: 'Sàng lọc',
            interview: 'Phỏng vấn',
            offer: 'Offer',
            hired: 'Đã tuyển',
            rejected: 'Từ chối',
            talent_pool: 'Talent pool',
            pending_approval: 'Chờ duyệt TD',
            published: 'Đang đăng',
            closed: 'Đã đóng',
            accepted: 'UV đồng ý',
            declined: 'Từ chối offer',
        };
        return map[status] || status;
    };

    return {
        money,
        date,
        datetime,
        statusLabel,
        // Alias dùng ở nhiều page (SelfService, Dashboard, Reports…)
        formatDate: date,
        formatDateTime: datetime,
        formatMoney: money,
    };
}
