# Hồ sơ nhân viên — Chuẩn Việt Nam (base)

Tài liệu mô tả các nhóm trường phục vụ kê khai lao động, BHXH và thuế TNCN.

## Nhóm trường

| Tab UI | Nội dung | Pháp lý / nghiệp vụ |
|--------|----------|---------------------|
| Cá nhân | Họ tên, ngày sinh, quê quán, dân tộc, liên hệ | Hồ sơ lao động, Bộ luật Lao động 2019 |
| CCCD · BHXH · Thuế | CCCD, MST, mã BHXH, BHYT, mức lương đóng BHXH, NPT | Luật BHXH, Luật thuế TNCN, Nghị định GTGC |
| Địa chỉ | HKTT, tạm trú, phường/quận/tỉnh | Kê khai BHXH, hợp đồng |
| Lao động | Loại HĐ, trạng thái, ngày vào/chính thức/nghỉ, TK NH | HĐLĐ, thanh toán lương |
| Học vấn & khẩn | Trình độ, liên hệ khẩn, GPLĐ (NN) | An toàn lao động, lao động nước ngoài |
| Phụ thuộc | Người GTGC | Tính thuế TNCN (4,4 triệu/NPT/tháng) |
| Tài liệu | CCCD, HĐLĐ, sổ BHXH, SK, … | Lưu trữ hồ sơ tối thiểu 3–30 năm |
| Hợp đồng | Liên kết HĐLĐ | Loại: không xác định / xác định / thử việc |

## API

- `GET /api/v1/hr-meta` — danh mục (loại HĐ, loại giấy tờ, …)
- `PUT /api/v1/employees/{id}` — thông tin chính
- `PUT /api/v1/employees/{id}/profile` — hồ sơ bổ sung
- `GET|POST|PUT|DELETE /api/v1/employees/{id}/dependents`
- `GET|POST|DELETE /api/v1/employees/{id}/documents`

## Payroll

- Số NPT: `pit_dependents_count` hoặc đếm bản ghi `employee_dependents` active
- BHXH: `insurance_salary` (mức đóng), tách khỏi gross có OT qua `calculateWithInsuranceBase`

## Đã triển khai (base đầy đủ)

- **Upload file** tài liệu NV & scan HĐLĐ (`storage/app/hr-private`, tải qua API có auth)
- **Kiểm tra trùng** CCCD/MST trong phạm vi `tenant` (tập đoàn)
- **Hợp đồng lao động** đủ trường BLLĐ 2019 + upload PDF
- **Kê khai BHXH** menu → D01 báo tăng, D05 báo giảm, TK1 NPT (CSV + XML)
- **Công ty**: mã đơn vị BHXH, cơ quan quản lý, người đại diện (menu Tổ chức)

## Lưu ý triển khai tiếp

- Map XML sang đúng schema cổng IVAN/VSS phiên bản mới nhất
- Ký số HĐLĐ điện tử
- S3 backup file hồ sơ
