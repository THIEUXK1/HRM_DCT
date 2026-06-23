# Module BHXH — Hướng dẫn sử dụng

## Phạm vi

| Mã | Mô tả | API preview | Xuất |
|----|--------|-------------|------|
| D01 | Báo tăng lao động tham gia BHXH | `GET /bhxh/preview?declaration_type=d01` | CSV, XML |
| D02 | Điều chỉnh mức lương / hồ sơ | `declaration_type=d02` | CSV, XML |
| D05 | Báo giảm (nghỉ việc) | `declaration_type=d05` | CSV, XML |
| TK1 | Người phụ thuộc GTGC | `declaration_type=tk1` | CSV, XML |
| DS | Danh sách đang tham gia + tính mức đóng | `declaration_type=roster` | CSV |

## Luồng chuẩn

1. **Tổ chức** → cấu hình mã đơn vị BHXH (`social_insurance_unit_code`)
2. **Nhân viên** → đủ CCCD, mã BHXH, mức lương đóng, ngày tham gia
3. **BHXH → tab tương ứng** → *Kiểm tra hồ sơ* → sửa lỗi → *Xuất CSV/XML*
4. File lưu **lịch sử kê khai** — tải lại tại tab Lịch sử

## Kiểm tra trước xuất

`BhxhValidationService` kiểm tra:
- Công ty: mã đơn vị BHXH
- NV: trường bắt buộc theo loại tờ khai
- Mức lương đóng: min/max theo `config/bhxh_vn.php`

Chỉ bản ghi **Hợp lệ** mới được xuất khi `only_valid=true` (mặc định).

## Cấu hình tỷ lệ

File `config/bhxh_vn.php` và biến `.env`:
- `BHXH_RATE_EMPLOYEE`, `BHXH_RATE_EMPLOYER`
- `BHYT_*`, `BHTN_*`, `KPCD_*`
- `BHXH_MIN_SALARY_BASE`, `BHXH_MAX_SALARY_BASE`

## Map cổng VSS/IVAN

XML nội bộ (`urn:hrm:bhxh:d01` …) — cần lớp transform khi nối cổng thật.
