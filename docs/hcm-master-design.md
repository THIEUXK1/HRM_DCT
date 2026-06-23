# HCM Suite — Thiết kế tổng thể & Lộ trình

> **Trạng thái code / map file:** [`agent-context.md`](agent-context.md) — đọc file đó cho thông tin as-is.
> File này chứa **tầm nhìn, kiến trúc, roadmap**.

---

## 1. Tầm nhìn sản phẩm

**Nguyên tắc cốt lõi:**
- Core HR = single source of truth
- Modular monolith, web-first (admin / manager / employee)
- RBAC + audit trail
- Payroll & workflow có thể cấu hình
- Multi-company / multi-tenant cấp tập đoàn

### Vòng đời nhân sự

```
Recruitment → Onboarding → Employee ↔ Organization
    → Attendance / Leave / OT
    → Payroll → Payslip
    → Training → Competency → Performance
    → Offboarding
    → Reporting & Analytics
```

### Cổng người dùng

| Portal | Đối tượng | Modules chính |
|--------|-----------|---------------|
| Admin / HR | HR Ops, C&B | Master data, payroll, tuyển dụng, BHXH, cấu hình |
| Manager | Trưởng bộ phận | MSS: team, duyệt, KPI, lịch nhóm |
| Employee | Nhân viên | ESS: hồ sơ, công, phiếu lương, phép, KPI |
| Specialist | Recruiter, Payroll, Training | Module chuyên biệt |
| Executive | Ban giám đốc | Dashboard tổng quan, báo cáo cross-company |

---

## 2. Kiến trúc

| Lớp | Hiện tại |
|-----|----------|
| Frontend | Vue 3 + Vite + Pinia, `resources/js/hcm/` (SPA `/app`) |
| Backend | Laravel 12 API, PHP 8.2+ |
| Database | SQLite (dev) / PostgreSQL (prod) |
| Auth | API Token + Spatie Permission RBAC |
| File storage | `hr_private` disk (file NV, HĐ, tài liệu) |
| Multi-tenant | `tenant_id` + `X-Company-Id` header + middleware |

Cấu trúc logic:
- `app/Services/{Module}/` — business logic
- `app/Http/Controllers/Api/` — thin REST controllers
- `app/Models/` — Eloquent models
- `resources/js/hcm/pages/` — Vue pages (1 module / 1 file)

---

## 3. Phạm vi module — Trạng thái hiện tại

### ✅ Đã có (v1 — production-ready)

| Module | Highlights |
|--------|-----------|
| **Tổ chức** | Tập đoàn → Công ty → Chi nhánh → Phòng ban → Chức danh |
| **Nhân viên VN** | Hồ sơ 8 tab, CCCD/MST unique trong tenant, hợp đồng LĐ, upload file |
| **BHXH** | D01/D02/D05/TK1/DS · preview · export CSV/XML · lịch sử kê khai |
| **Tuyển dụng (ATS)** | YC tuyển → duyệt headcount → JD → cổng apply → pipeline → PV scorecard → offer → hire |
| **Onboarding** | Checklist tự sinh khi hire, buddy, xác nhận hoàn tất |
| **Offboarding** | Checklist bàn giao, exit interview, quyết toán, analytics turnover |
| **Chấm công** | Import CSV máy chấm công, bảng công, nghỉ phép, OT, khóa kỳ |
| **Lương (Payroll)** | BHXH + PIT VN, payslip HTML, phiếu lương cá nhân |
| **Workflow duyệt** | Inbox, approve/reject đa cấp: nghỉ, OT, tuyển dụng |
| **Đào tạo (LMS)** | Khóa học, lớp học, ghi danh, hoàn thành → cập nhật năng lực tự động |
| **Năng lực** | Khung năng lực, yêu cầu vị trí, ma trận gap level 1–5 |
| **KPI / Hiệu suất** | Mục tiêu trọng số (KPI 60% + hành vi 40%), chốt điểm, xếp loại A–E |
| **Dashboard cá nhân** | Phép còn lại, việc cần làm, yêu cầu đang xử lý, thông báo |
| **Dashboard quản lý** | Headcount, biến động, cảnh báo hợp đồng/thử việc |
| **ESS** | Hồ sơ, bảng công, nghỉ phép + quỹ phép, phiếu lương, KPI, tài liệu |
| **MSS** | Team của tôi, duyệt yêu cầu, đánh giá NV, lịch nhóm |
| **Báo cáo** | Headcount, biến động, cơ cấu, chấm công, lương, KPI, gap năng lực |
| **Multi-tenant** | Cách ly tenant/company, user-company access, 3-lớp middleware |

---

## 4. Lộ trình (Roadmap)

| Phase | Nội dung | Trạng thái |
|-------|----------|-----------|
| **0** | Core HR + hồ sơ VN + BHXH | ✅ Hoàn thành |
| **1** | Chấm công + nghỉ + OT | ✅ Hoàn thành |
| **2** | Payroll VN + payslip | ✅ Hoàn thành |
| **3** | ATS + Onboarding | ✅ Hoàn thành |
| **4** | LMS + Năng lực + KPI | ✅ Hoàn thành |
| **M1** | Multi-tenant hardening | ✅ Hoàn thành |
| **UX1** | Dashboard, ESS, MSS, Offboarding, Báo cáo đầy đủ | ✅ Hoàn thành |
| **M2** | RBAC middleware chi tiết + Audit log | 🔲 Backlog |
| **5** | IVAN/VSS BHXH, Excel import/export NV | 🔲 Backlog |
| **M3** | Báo cáo cross-company tập đoàn | 🔲 Backlog |
| **6** | Mobile PWA / App | 🔲 Tương lai |

---

## 5. Backlog ưu tiên (Phase M2 + 5)

### Ưu tiên cao

1. **Audit log** — bảng `audit_logs`, ghi lại thay đổi nhạy cảm (lương, HĐ, phân quyền, BHXH export)
2. **Token expiration** — `api_token` hiện không expire; cần thêm `token_expires_at` hoặc chuyển sang Sanctum Personal Access Token
3. **Pagination đầy đủ** — các endpoint list lớn (`/employees`, `/payroll-results`) cần enforce pagination
4. **RBAC route-level** — Policy/Gate cho từng route API thay vì chỉ check ở frontend

### Ưu tiên trung bình

5. **Import/export Excel** — nhân viên hàng loạt, bảng lương, BHXH
6. **Map XML chuẩn IVAN/VSS** — kê khai BHXH gửi cổng VSS
7. **Báo cáo cross-company** — filter theo region/nhà máy, báo cáo tập đoàn
8. **Notification realtime** — push khi có yêu cầu cần duyệt

### Tương lai

9. **Mobile PWA** — ESS trên điện thoại
10. **Tích hợp máy chấm công** — API hardware hoặc SDK
11. **eSign hợp đồng** — ký điện tử HĐLĐ

---

## 6. Quyết định cần stakeholder

| # | Câu hỏi | Ảnh hưởng |
|---|---------|-----------|
| 1 | Laravel long-term vs Spring Boot Java 21? | Kiến trúc backend |
| 2 | SaaS multi-tenant vs on-premise single company? | Deploy model |
| 3 | Phiên bản luật thuế/BHXH áp dụng (năm nào)? | Config tỷ lệ |
| 4 | Tích hợp máy chấm công — brand/SDK? | Attendance module |
| 5 | Phạm vi go-live MVP — số công ty, số NV? | Performance tuning |
| 6 | eSign hợp đồng — nhà cung cấp? | Integration |

---

## 7. Tài liệu liên quan

| File | Mục đích |
|------|----------|
| `agent-context.md` | Map code, chạy app, backlog (nguồn sự thật) |
| `README-HCM.md` | Cài đặt, luồng mẫu |
| `bhxh-module.md` | Nghiệp vụ BHXH chi tiết |
| `employee-profile-vn.md` | Hồ sơ NV VN chi tiết |
| `api-reference.md` | API tóm tắt |
| `f:\HRM\.cursor\rules\` | 5 Cursor rule files cho AI agent |
