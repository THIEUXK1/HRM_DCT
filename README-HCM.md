# HCM Suite — Hướng dẫn cài đặt & vận hành

> **AI Agent:** đọc [`docs/agent-context.md`](docs/agent-context.md) trước · quy ước: [`.claude/rules/ehr-conventions.md`](../.claude/rules/ehr-conventions.md) · roadmap: [`docs/hcm-master-design.md`](docs/hcm-master-design.md)

---

## Yêu cầu hệ thống

- PHP 8.2+, Composer 2.x
- Node.js 20+, npm
- SQLite (mặc định) hoặc PostgreSQL

---

## Cài đặt (một lần)

```powershell
cd f:\HRM\EHR
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm install
npm run build
```

---

## Chạy server

**Cách 1 — Double-click (dễ nhất):**
```
F:\HRM\start-hcm.bat
```

**Cách 2 — PowerShell:**
```powershell
cd f:\HRM\EHR
php artisan serve --host=127.0.0.1 --port=8001
```

> Giữ terminal **mở** — đóng terminal = tắt web.
> `npm run dev` chỉ cần khi đang sửa giao diện Vue (HMR).

---

## Truy cập

| URL | Mô tả |
|-----|-------|
| http://127.0.0.1:8001/app | Web app (SPA) |
| http://127.0.0.1:8001/app/careers | Cổng tuyển dụng (public) |
| http://127.0.0.1:8001/api/v1/docs | Swagger API |

---

## Tài khoản demo

| Email | Mật khẩu | Vai trò | Phạm vi |
|-------|----------|---------|---------|
| `admin@example.com` | `Admin@123` | Admin | Toàn bộ hệ thống |
| `hr.south@hrmsouth.local` | `Hr@South2026` | HR Manager | Công ty HRM Miền Nam |

---

## Header API (bắt buộc)

```http
Authorization: Bearer {api_token}
X-Company-Id: {company_id}
```

---

## Module đã triển khai

| Module | API | Web UI | Ghi chú |
|--------|-----|--------|---------|
| Core HR — Tổ chức | ✅ | ✅ | Tập đoàn, công ty, chi nhánh, phòng ban, chức danh |
| Core HR — Nhân viên | ✅ | ✅ | Hồ sơ 8 tab, upload file, CCCD/MST duy nhất trong tenant |
| Hợp đồng lao động | ✅ | ✅ | Upload file, theo dõi trạng thái |
| BHXH | ✅ | ✅ | D01/D02/D05/TK1/DS · preview · lịch sử · export CSV/XML |
| Tuyển dụng (ATS) | ✅ | ✅ | YC tuyển → headcount → JD → pipeline → PV → offer → hire |
| Cổng ứng tuyển | ✅ | ✅ | Public: `/app/careers`, apply CV |
| Onboarding | ✅ | ✅ | Checklist, buddy, tab NV |
| Offboarding | ✅ | ✅ | Bàn giao, exit interview, quyết toán, analytics |
| Chấm công | ✅ | ✅ | Import CSV máy CC, bảng công, khóa kỳ |
| Nghỉ phép / OT | ✅ | ✅ | Đơn nghỉ, OT, quỹ phép, lịch nghỉ toàn công ty |
| Payroll | ✅ | ✅ | BHXH + PIT VN, payslip HTML, phiếu lương cá nhân |
| Workflow duyệt | ✅ | ✅ | Inbox, approve/reject, đa cấp |
| Đào tạo (LMS) | ✅ | ✅ | Khóa học, lớp, ghi danh, hoàn thành → cập nhật năng lực |
| Năng lực | ✅ | ✅ | Khung, yêu cầu vị trí, ma trận gap level 1–5 |
| KPI / Hiệu suất | ✅ | ✅ | Mục tiêu trọng số, thực tế, chốt điểm A–E |
| Dashboard cá nhân | ✅ | ✅ | Phép còn lại, việc cần làm, yêu cầu, thông báo |
| Dashboard quản lý | ✅ | ✅ | Headcount, biến động, cảnh báo hợp đồng |
| ESS — Cổng nhân viên | ✅ | ✅ | Hồ sơ, bảng công, nghỉ phép, phiếu lương, KPI, tài liệu |
| MSS — Cổng quản lý | ✅ | ✅ | Team, duyệt, đánh giá NV, lịch nhóm |
| Báo cáo | ✅ | ✅ | Headcount, biến động, cơ cấu, chấm công, lương, KPI, năng lực |
| Multi-tenant | ✅ | ✅ | Cách ly tenant/company, user-company access management |

---

## Luồng lương mẫu

```
1. Import chấm công   POST /api/v1/attendance-devices/{id}/import   (CSV)
2. Khóa kỳ công       POST /api/v1/attendance-summaries/lock
3. Tạo kỳ lương       POST /api/v1/payroll-cycles
4. Tính lương         POST /api/v1/payroll-cycles/{id}/calculate
5. Phát hành payslip  POST /api/v1/payroll-cycles/{id}/publish-payslips
6. Xem phiếu lương    GET  /api/v1/payroll-results/{id}/payslip      (HTML)
```

## Luồng nghỉ phép mẫu

```
1. NV tạo đơn   POST /api/v1/leave-requests         → tạo ApprovalInstance
2. QL duyệt     GET  /api/v1/approvals/inbox
                POST /api/v1/approvals/{id}/approve  hoặc /reject
```

## Luồng hire từ ATS

```
1. Ứng viên apply → pipeline PV → offer accepted
2. POST /api/v1/candidates/{id}/hire  → tạo Employee + User account tự động
3. Onboarding checklist tự sinh
```

---

## CSV chấm công

```csv
employee_code,work_date,check_in,check_out
EMP-00001,2026-05-01,08:30:00,17:30:00
EMP-00002,2026-05-01,08:45:00,17:15:00
```

---

## Cấu hình thuế / BHXH

| File | Nội dung |
|------|----------|
| `config/payroll_vn.php` | Bậc thuế PIT, giảm trừ gia cảnh, tỷ lệ BHXH |
| `config/bhxh_vn.php` | Trần đóng BHXH, loại tờ khai |
| `config/hr_vn.php` | Danh mục NV VN (loại HĐ, dân tộc, trình độ…) |
| `.env` | `PAYROLL_BHXH_*`, `PAYROLL_PIT_*` override |

---

## RBAC — Vai trò mặc định

| Role | Quyền |
|------|-------|
| `admin` | Toàn quyền, quản lý users, companies |
| `hr_manager` | HR operations, payroll, tuyển dụng |
| `department_manager` | Duyệt team, xem KPI, báo cáo phòng |
| `department_secretary` | Chấm công phòng, duyệt nghỉ phòng |
| `payroll_specialist` | Payroll, BHXH |
| `recruiter` | ATS pipeline |
| `employee` | ESS, tự phục vụ |

---

## Chạy test

```powershell
cd f:\HRM\EHR
php artisan test
# Hiện tại: 56 tests, 270 assertions — tất cả PASS
```
