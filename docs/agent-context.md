# EHR / HCM — Agent context (nguồn sự thật)

> **Cập nhật:** 2026-05-27. Đọc file này **trước** khi quét codebase. Chi tiết module: `docs/bhxh-module.md`, `docs/employee-profile-vn.md`.

## Thư mục & chạy app

| Mục | Giá trị |
|-----|---------|
| Code app | `f:\HRM\EHR` (không nhầm `F:\HRM` gốc) |
| Stack | Laravel 12, PHP **8.2+**, SQLite mặc định, API token + Spatie Permission |
| Frontend | Vue 3 + Vite + Pinia tại `resources/js/hcm/` (SPA `/app`, **không** Inertia) |
| Serve | `php artisan serve --host=127.0.0.1 --port=8001` hoặc `F:\HRM\start-hcm.bat` |
| Web | http://127.0.0.1:8001/app |
| Swagger | http://127.0.0.1:8001/api/v1/docs |
| Demo | `admin@example.com` / `Admin@123` |
| Build UI | `npm run build` (HMR: `npm run dev`) |
| Test | `cd EHR && php artisan test` |

## Header API (bắt buộc sau login)

- `Authorization: Bearer {token}`
- `X-Company-Id: {company_id}` — phạm vi công ty trong tenant

## Cập nhật nhanh (2026-06-01)

- Bù thẻ (`attendance-correction-requests`) hỗ trợ `correction_mode`: `check_in` | `check_out` | `both`.
- Form Đơn bù thẻ ở `LeaveListPage.vue` cho phép chọn kiểu bù tương ứng (bù giờ vào / bù giờ ra / cả 2).
- Backend `AttendanceCorrectionRequestController::store()` validate theo mode: bắt buộc đúng trường thời gian và chặn case giờ ra < giờ vào.

## Tìm kiếm danh sách (2026-05-30)

- Query param chuẩn: **`?search=`** (text, LIKE) — helper `App\Support\QuerySearch`
- NV: `full_name`, `employee_code`, `email` — áp dụng trên `employees`, `whereHas('employee')`, hợp đồng, nghỉ/OT/bù thẻ, onboarding, offboarding…
- UI: `components/ui/UiSearchInput.vue` (debounce 350ms) + `composables/useDebouncedSearch.js`
- Các trang đã có ô tìm: Nhân viên, Hợp đồng, Nghỉ/OT/Bù thẻ, Offboarding, Onboarding, Tuyển dụng, Chấm công, Cơ cấu tổ chức, Lương, Phân quyền user, Hộp thư duyệt

## Lọc công ty / chi nhánh / phòng ban (2026-05-30)

- Query param: **`branch_id`**, **`department_id`**, **`employment_status`** (danh sách NV)
- Backend: `App\Support\EmployeeQueryScope` — `apply()` trực tiếp trên `employees`; `applyOnRelation()` cho leave, OT, bù thẻ, HĐ, chấm công, offboarding, onboarding
- Báo cáo chấm công: `AttendanceReportController` + `AttendanceTimesheetService` nhận thêm `branch_id`
- UI: `components/ui/UiOrgScopeFilters.vue` + `composables/useOrgScopeFilters.js`
- Trang đã gắn bộ lọc: **Nhân viên**, Hợp đồng, Nghỉ/OT/Bù thẻ, Offboarding, Onboarding, Chấm công (bảng công & báo cáo)

## Cảnh báo tuân thủ HR (2026-05-30)

- Service: `App\Services\Hr\HrComplianceAlertService` — HĐ, OT, thử việc, ca làm việc
- API: `GET /hr-alerts`, `GET /hr-alerts/summary`
- Config ngưỡng: `config/hr_vn.php` → `alert_thresholds`, `contract_max_definite_months`
- UI: `UiComplianceAlertPanel.vue` — Hợp đồng, tab OT (LeaveListPage), Dashboard quản lý
- Thông báo tự động (07:00): HĐ hết hạn, OT vượt trần — `notifications:send-scheduled`
- Tham chiếu pháp lý: BLLĐ 2019 Điều 20 (HĐ), 24–27 (thử việc), 107 (OT 40h/tháng · 200h/năm); NĐ 145/2020 (thông báo 200–300h/năm)

## Trung tâm báo cáo (`ReportsPage.vue` + `HrStandardReportsService`)

| Tab | API |
|-----|-----|
| Biến động NS | `GET /reports/workforce-movement?period=YYYY-MM` — tách **tuyển mới thực sự** (`new_hires` theo `hire_date`) vs **chuyển TV→CT** (`converted_to_official_in_period`, `probation_ended_in_period`, `conversion_rate`); không cộng trùng headcount |
| Cơ cấu NS | `GET /reports/workforce-structure` |
| Tuyển dụng | `GET /reports/recruitment?period=` |
| Nghỉ việc | `GET /reports/turnover?period=` |
| Chấm công & phép | `GET /reports/attendance-leave?period=` |
| Lương & phúc lợi | `GET /reports/payroll-benefits?period=` |
| Đào tạo | `GET /reports/training?period=` |
| Hiệu suất/KPI | `GET /reports/performance-kpi` |
| Khen thưởng/Kỷ luật | `GET /reports/awards-discipline?period=` |
| Tổng hợp lãnh đạo | `GET /reports/executive-summary?period=` |

Filter chung: `department_id`, `company_id` (header), `period` (tháng). Permission: `admin|audit_logs.view|performance.view`.

## Cây thư mục then chốt

```
EHR/
├── app/Http/Controllers/Api/     # REST v1
├── app/Services/
│   ├── Bhxh/                   # Export, validation, declaration, contribution
│   ├── Hr/HrFileStorage.php
│   ├── Payroll/                # VietnamPayrollCalculator, PayrollCycleService
│   ├── Attendance/
│   ├── Approval/
│   └── Recruitment/
├── app/Rules/                  # UniqueNationalIdInTenant, UniqueTaxCodeInTenant
├── app/Models/
├── config/
│   ├── hr_vn.php               # Danh mục NV VN
│   ├── bhxh_vn.php             # Tỷ lệ BHXH, trần, loại tờ khai
│   └── payroll_vn.php
├── database/migrations/2026_05_27_1000*.php
├── resources/js/hcm/
│   ├── router/index.js
│   ├── pages/                  # Một page / module chính
│   └── stores/auth.js
├── routes/api.php              # prefix /api/v1
├── storage/app/hr-private/     # disk hr_private — file NV, HĐ
└── tests/Feature/              # BhxhModuleTest, EmployeeComplianceTest, …
```

## Map module → code (đừng quét cả repo)

| Module | Controller / Service | UI Vue |
|--------|----------------------|--------|
| Auth | `AuthController` | `LoginPage.vue`, `stores/auth.js` |
| Tổ chức | `Company/Branch/Department/Position`, `OrgStructureScope` | `OrganizationPage.vue`, Settings tab phòng ban |
| Nhân viên VN | `EmployeeController`, `EmployeeDependentController`, `EmployeeDocumentController`, `HrMetaController` | `EmployeeListPage.vue`, `EmployeeDetailPage.vue` |
| HĐLĐ | `EmploymentContractController` | `ContractListPage.vue` |
| BHXH | `BhxhController`, `BhxhExportService`, `BhxhDeclarationService` | `BhxhPage.vue` (route `/app/bhxh`) |
| Tuyển dụng (ATS) | `RecruitmentRequestController`, `JobPostController`, `CandidateController`, `InterviewController`, `OfferController`, `HireCandidateService` | `RecruitmentPage.vue` |
| Onboarding | `OnboardingController` | `OnboardingPage.vue` |
| Chấm công | `AttendanceDeviceController`, `AttendanceSummaryController`, `AttendancePunchController`, `AttendanceGeofenceZoneController` | `AttendancePage.vue`, `AttendancePunchPage.vue` |
| Nghỉ / OT / Bù thẻ | `LeaveRequestController`, `OvertimeRequestController`, `AttendanceCorrectionRequestController` | `LeaveListPage.vue` |
| Lương | `PayrollCycleController`, `PayslipController` | `PayrollListPage.vue` |
| Duyệt | `ApprovalController` | `ApprovalsPage.vue` |
| LMS | `Course*`, `TrainingClass*` | `TrainingPage.vue` |
| Năng lực | `CompetencyController`, `CompetencyGapService` | `CompetencyPage.vue` — khung, yêu cầu vị trí, ma trận gap |
| KPI / Hiệu suất | `PerformanceController`, `PerformanceScoreService` | `PerformancePage.vue` — KPI trọng số, chốt điểm |
| Self-service (ESS) | `SelfServiceController` | `SelfServicePage.vue` (7 tab: hồ sơ, bảng công, phép, lương, HĐ, KPI, tài liệu) |
| Dashboard cá nhân | *(nhiều API)* | `DashboardPage.vue` |
| Dashboard quản lý | *(nhiều API)* | `ManagerDashboardPage.vue` |
| MSS — Cổng quản lý | *(nhiều API)* | `MssPage.vue` |
| Offboarding | `EmployeeTerminationController` | `OffboardingPage.vue` |
| Báo cáo | `ReportController` | `ReportsPage.vue` (7 tab: headcount, biến động, cơ cấu, chấm công, lương, năng lực, KPI) |

## API BHXH (lưu ý query)

Prefix: `/api/v1/bhxh/`

| Endpoint | Ghi chú |
|----------|---------|
| `GET meta` | Danh mục, tỷ lệ |
| `GET dashboard` | Tổng quan |
| `GET preview` | Query: **`declaration_type`** = `d01\|d02\|d05\|tk1\|roster` (+ `company_id`, `from`, `to`) |
| `POST export` | Body: `declaration_type`, `format` csv\|xml, `only_valid` (mặc định true) |
| `GET declarations` | Lịch sử; lọc `declaration_type` |
**Không dùng** query param `type` cho preview/export — dễ trống dữ liệu.

Permissions: `bhxh.export`, `bhxh.manage` (`RolePermissionSeeder`).

## Hồ sơ NV & file

- Meta: `GET /api/v1/hr-meta`
- Profile: `PUT /api/v1/employees/{id}/profile`
- Trùng CCCD/MST: trong **tenant** (`UniqueNationalIdInTenant`, `UniqueTaxCodeInTenant`)
- Upload: `POST .../documents`, HĐ: `POST .../employment-contracts/{id}/upload`
- Download: có auth, disk **`hr_private`**

## Payroll ↔ BHXH

- `VietnamPayrollCalculator::calculateWithInsuranceBase()` — mức `insurance_salary`, NPT
- Config: `config/payroll_vn.php`, env `PAYROLL_BHXH_*`, `PAYROLL_PIT_*`

## Migration HCM (2026-05-27)

| File | Nội dung |
|------|----------|
| `100000` | tenants |
| `100001` | user_company_access |
| `100010` | recruitment |
| `100020` | onboarding |
| `100030` | attendance |
| `100040` | payroll |
| `100050` | training, competency, performance |
| `100060` | approval |
| `100070` | employee VN compliance, dependents |
| `100080` | contracts, company BHXH fields, files |
| `100090` | bhxh_declarations, bhxh_declaration_lines |

Migration tiếp theo: `2026_05_27_100100_*` (hoặc ngày mới).

## Đã có vs chưa (tránh tìm lại)

### Đã có (v1)

- Web HCM đủ menu RBAC; hồ sơ NV 8 tab; HĐLĐ + upload; hub BHXH D01/D02/D05/TK1/DS + preview + lịch sử
- Chấm công import CSV, nghỉ, OT, khóa kỳ; payroll VN; workflow duyệt; LMS v1
- **Năng lực v2:** yêu cầu theo chức danh, ma trận gap NV, đánh giá level 1–5
- **KPI v2:** mục tiêu có trọng số, cập nhật thực tế, chốt điểm (60% KPI + 40% hành vi)
- XML export nội bộ `urn:hrm:bhxh:*` (chưa map IVAN/VSS)

### ATS / Onboarding (hoàn thiện v1)

- Yêu cầu TD + duyệt headcount + tin JD + **cổng ứng tuyển** `/app/careers`
- Pipeline UV, PV + scorecard, offer + thư mời NV (HTML) + hire bắt buộc `accepted`
- Buddy onboarding, tab Onboarding trên hồ sơ NV, xác nhận hoàn tất
- API public: `GET/POST /api/v1/public/job-posts/*`

### API Năng lực / KPI (v2)

| Endpoint | Ghi chú |
|----------|---------|
| `GET competency-meta` | Level, trạng thái gap |
| `GET employees/{id}/competency-matrix` | Ma trận required vs current |
| `PUT positions/{id}/competency-requirements` | Body: `requirements[]` |
| `GET performance-meta` | Trọng số, xếp loại |
| `PUT goals/{id}` | Cập nhật `actual_value` |
| `POST employee-reviews/{id}/finalize` | Tính `final_score` + `rating` |

Config: `config/competency.php`, `config/performance.php`.

### LMS ↔ Năng lực + Báo cáo (v2)

- Bảng `course_competencies` — khóa học cấp level NL khi hoàn thành (`min_score`)
- `POST training-enrollments/{id}/complete` → `competency_updates[]`, `source=lms`
- `PUT courses/{id}/competencies` — cấu hình liên kết
- Báo cáo: `/app/reports` — `GET reports/competency-gaps`, `GET reports/performance-kpi`
- Tab **Năng lực** / **KPI** trên hồ sơ NV

### Phase M1 — Tenant/Company hardening (DONE)

- `SetCompanyContext` middleware: **3 lớp kiểm tra** — `default_company_id`, `user_companies`, employee→company
- `CompanyController::index` scoped theo user — non-admin chỉ thấy công ty mình có quyền
- `UserController`: scope index theo tenant; `PUT /api/v1/users/{user}/access` (công ty + vai trò); `PUT /users/{user}/company-access` (legacy); `GET /users/{user}/company-access`; bảng `user_company_roles` — vai trò khác nhau từng CTTV
- `HireCandidateService`: tự động tạo / cập nhật User account với `default_company_id` + `tenant_id` khi hire
- `MultiCompanyDemoSeeder`: 3 công ty (HQ HN + nhà máy BD + nhà máy ĐN), tài khoản `hr.south@hrmsouth.local`
- Frontend `app.js` store: getters `currentCompany`, `isMultiCompany`; header chỉ hiện selector khi multi-company
- Tests: `MultiTenantIsolationTest` — 5 test cách ly tenant/company (56 tests total, 270 assertions)

### API Users (cập nhật)

| Endpoint | Ghi chú |
|----------|---------|
| `GET /api/v1/users` | Scoped theo tenant |
| `PUT /api/v1/users/{id}/company-access` | Body: `company_ids[]` — cấp/thu hồi |
| `GET /api/v1/users/{id}/company-access` | Danh sách công ty được phép truy cập |

### Chưa / backlog

- Map XML đúng schema cổng IVAN/VSS
- Import ngược BHXH; import/export Excel NV hàng loạt
- **Pagination đầy đủ** — enforce cho list lớn (Phase M2-C — tiếp theo)
- **Phase M2**: RBAC middleware chi tiết trên từng route API + policy company-scoped
- **Phase M2**: RBAC middleware chi tiết trên từng route API + policy company-scoped (Phase M2-D)
- **Phase M3**: Báo cáo tập đoàn — filter theo region/nhà máy (cross-company report)

### Attendance — BLLĐ 2019 Compliance DONE (2026-05-28)

**Migration `100160`** — thêm cột vào 3 bảng:
- `attendance_logs`: `work_hours`, `late_minutes`, `early_minutes`, `night_hours`, `is_weekend`, `is_holiday`, `holiday_name`, `employment_phase`, `work_shift_id`
- `overtime_requests`: `ot_type` (weekday/weekend/holiday), `night_hours`, `exceeds_daily_cap`, `exceeds_monthly_cap`
- `attendance_summaries`: `probation_work_days`, `official_work_days`, `standard_work_days`, `absent_days`, `actual/standard_work_hours`, `ot_weekday/weekend/holiday_hours`, `night_hours`, `late_count`, `early_count`, `ot_monthly_cap_exceeded`

**`VietnamHolidayService`** — 11 ngày lễ theo Điều 112 BLLĐ 2019 (Tết DL, Tết ÂL 4 ngày, Giỗ Tổ, 30/4, 1/5, 2/9 × 2 ngày), lookup nhanh O(1)

**`OvertimeCapValidator`** — kiểm tra cap 4h/ngày, 40h/tháng, 200h/năm (Điều 107); trả về warnings + `valid` flag

**`AttendanceSummaryService`** rewrite hoàn toàn:
- Đọc hợp đồng để xác định `probation_end_date` → phân loại từng ngày log: `probation` / `official`
- Tính `work_hours` từ check_in/check_out − break_minutes
- Tính `late_minutes`, `early_minutes` so với ca làm (WorkShift)
- Tính `night_hours` (22:00–06:00, phút từng phút)
- Detect `is_weekend`, `is_holiday` cho từng log
- Tổng hợp OT theo loại ngày (`ot_weekday/weekend/holiday_hours`)
- Cờ `ot_monthly_cap_exceeded` khi vượt 40h/tháng
- `standard_work_days` = ngày làm chuẩn trừ lễ + cuối tuần

**`OvertimeRequestController`** — tự động xác định `ot_type` từ ngày OT; validate cap trước khi tạo; route `GET /overtime-requests/cap-summary`

**`config/hr_vn.php`** — thêm `ot_rates`, `night_work_rate`, `probation_max_months`

**`AttendancePage.vue`** — rewrite bảng tổng hợp: cột TV / Chính thức / OT 3 loại (150%/200%/300%) / Đêm / Trễ; badge cảnh báo ⚠️ khi vượt OT tháng; chú thích giai đoạn TV/CT

**Cấp bậc O1–O7 & ca đêm (2026-05-29)**:
- Migration `100200`: `job_levels.grade/band/category` · `work_shifts.is_night_shift/crosses_midnight/standard_hours`
- Thang chuẩn: O1–O4 quản lý · O5–O6 nhân viên · O7 công nhân × band A–D (28 cấp)
- API: `POST /job-levels/seed-standard`, `POST /work-shifts/seed-presets`
- Ca đêm mẫu `CA-DEM`: 22:00–07:00, nghỉ 45p, +30% đêm (Điều 106 BLLĐ 2019)
- UI: **Settings → Cấp bậc nhân sự / Ca làm việc**

**Payroll formulas v2 (2026-05-29)**:
- Migration `100190`: `payroll_formula_rules` — kế toán tự định nghĩa công thức `{biến}` + toán học
- Migration `100373`: `payroll_formula_custom_variables` — biến số tùy chỉnh theo công ty (không sửa code)
- Config `config/payroll_formula_variables.php` — metadata tham số chỉnh được vs biến hệ thống (computed)
- Service `PayrollFormulaVariableService` — catalog, lưu tham số `company_settings`, gộp custom vào context khi tính lương
- Công thức mặc định: thưởng chuyên cần, thưởng KPI (`performance_bonus`), thanh toán phép thôi việc
- `PayrollContextBuilder` — biến KPI, thôi việc, bảng công
- NV thôi việc trong tháng: prorate công + lọc bảng lương; tính cả NV có bảng công đã khóa (kể cả inactive)
- UI: `PayrollListPage.vue` tab **Công thức lương** + cột Thưởng CC/NS + filter thôi việc
- API: `GET/POST/PUT/DELETE /payroll-formula-rules`, `POST /payroll-formula-settings` (legacy)
- API biến: `GET /payroll-formula-variables`, `PUT /payroll-formula-variables/parameters`, CRUD `/payroll-formula-custom-variables`
- UI tab **Công thức lương**: tham số chỉnh được, biến tùy chỉnh, biến hệ thống (read-only)
- **Nhiều lần tính / tháng** (migration `100374`): `run_number`, `label`, `revision_note` — bản **đã khóa** không đổi; tính lại → tạo lần 2, 3…
- API: `GET /payroll-cycles/period-status?period=`, `POST /payroll-cycles` (body: `period`, `revision_note`)
- UI: **+ Tạo kỳ lương** → chọn tháng + ghi chú; cảnh báo nếu tháng đã khóa / đã có bản nháp

**Leave types v2 (2026-05-29)** — phân loại nghỉ có/không lương (BLLĐ 2019):
- Migration `100170`: `paid_leave_days`, `unpaid_leave_days`, `probation_paid_leave_days`, `official_paid_leave_days`
- Migration `100360`: `probation_unpaid_leave_days`, `official_unpaid_leave_days`
- `config/hr_vn.php` → `leave_types`: PHEP, HH, CONG_TAC, BU (có lương NLĐ); OM, TS, KL (không lương NLĐ / trợ cấp BHXH)
- `LeaveDayCalculator` — đếm ngày nghỉ trên ngày làm chuẩn, tách TV/CT
- `LeaveDurationCalculator` — `workday` (phép năm…) vs `calendar` (TS, OM thai sản/ốm BHXH)
- API `GET /leave-requests/calculate-days` — tự tính số ngày khi tạo đơn
- `PayrollEarningsService` — công tính lương = đi làm + phép có lương (`payable_*_days`)
- Bảng công ngày: ký hiệu P/HH/B (có lương), KL/Ô/TS (không lương), V (vắng)
- **Admin CRUD (2026-05-29):** `POST/PUT/DELETE /leave-types`, `POST /leave-types/seed-standard` (perm `leave.manage`); UI **Settings → Chấm công & Chuyên cần → Loại nghỉ phép & ký hiệu**; test `LeaveTypeManagementTest`

**Bù thẻ & thưởng chuyên cần (2026-05-29)**:
- Migration `100180`: `attendance_correction_reasons`, `attendance_correction_requests`; summary: `forgot_punch_count`, `diligence_bonus_*`
- Lý do mặc định: Tắc đường, Lỗi máy, Quên chấm (tính hạn mức), Khác — admin CRUD tại **Settings → Chấm công & Chuyên cần**
- Quy tắc thưởng (`company_settings`): mức thưởng, % chuyên cần min, max trễ/vắng/quên chấm
- API: `attendance-correction-reasons`, `attendance-correction-requests` (+ approve/reject)
- UI: tab **Bù thẻ** trên `LeaveListPage.vue`; báo cáo chuyên cần có cột thưởng & quên chấm

**Đăng ký hàng loạt — nghỉ / OT / bù thẻ (2026-05-30):**
- `EmployeeScopeResolver` — gom NV từ **một** trong: `employee_id` | `employee_ids[]` | `department_id`
- API `POST /leave-requests`, `/overtime-requests`, `/attendance-correction-requests` — trả `created_count`, `created[]`, `errors[]` (tương thích đơn 1 NV)
- UI: `EmployeeTargetPicker.vue` trên `LeaveListPage.vue` (3 mode: 1 NV · nhiều NV · theo phòng ban)
- Test: `BulkEmployeeRegistrationTest`

**Attendance v2 (2026-05-29)** — bảng công đầy đủ:
- `GET /attendance-reports/timesheet` — ma trận công theo ngày (X/P/V/T/CN/L/TV)
- `GET /attendance-reports/overtime|diligence|leave|terminations` — báo cáo OT, chuyên cần, nghỉ phép, thôi việc
- `AttendanceTimesheetService` + `AttendanceReportController`
- `AttendancePage.vue` — 7 tab: công ngày · công tháng · OT · chuyên cần · phép · thôi việc · import
- Fix `AttendanceLog` fillable — lưu `work_hours`, trễ, đêm khi tổng hợp công

**Attendance v3 — Tách giai đoạn TV/CT (2026-05-30):**
- `EmploymentPhaseResolver` — xác định thử việc/chính thức theo HĐ (BLLĐ Điều 24–27)
- `GET /attendance-reports/phased-monthly` — bảng công tháng 1–2 dòng/NV (trước/sau thử việc), chuẩn AMIS/MISA
- Bảng ngày: màu TV/CT **cấu hình được** (mặc định xanh / xanh lá), cột tổng TV|CT, OT tách theo giai đoạn
- Tab **TV / CT giai đoạn** trên `AttendancePage.vue`

**Attendance display config (2026-05-29):**
- `config/attendance_display.php` — mặc định màu/nhãn ô công, giai đoạn TV/CT, header ngày, cột tổng, chú thích chân bảng
- `AttendanceDisplayConfigService` — merge `company_settings.attendance_display_config` (JSON)
- API: `GET /attendance-display-config` (attendance.view); `PUT` (admin|attendance.manage)
- Response timesheet kèm `display_config`; frontend `useAttendanceDisplay.js`
- UI admin: **Settings → Chấm công & Chuyên cần → Màu sắc & nhãn bảng công**
- Test: `AttendanceDisplayConfigTest`
- **Bảng công chi tiết NV (2026-05-29):** click tên NV → modal giờ vào/ra, vị trí GPS/vùng, lịch sử punch; `GET /attendance-reports/employee-detail`; xuất `export-employee-detail` + `export-cong-luong` (2 sheet Công/Lương format BestPacific)

**Chấm công đa kênh GPS (2026-05-29, migration `100220`):**
- Bảng: `attendance_geofence_zones`, `attendance_punches`; mở rộng `attendance_devices` (terminal/kiosk + API token), `attendance_logs` (tọa độ + zone)
- `GeofenceService` — Haversine, vòng tròn bán kính (mặc định demo NM-MAIN Q1 HCM r=350m)
- `AttendancePunchService` — punch in/out; nguồn `mobile|device|kiosk|field`
- Công tác: đơn leave type `CONG_TAC` (setting `attendance_field_trip_code`) đã duyệt → cho phép ngoài geofence
- Settings: `attendance_mobile_punch_enabled`, `attendance_geofence_strict`
- API NV: `GET/POST /self-service/attendance/punch*` (auth + GPS)
- API máy: `POST /attendance/device-punch` header `X-Device-Token`; cấp token `POST /attendance-devices/{id}/issue-token`
- HR: CRUD `attendance-geofence-zones`; tab thiết bị trên `AttendancePage.vue`
- UI NV: `AttendancePunchPage.vue` (menu **Chấm công GPS**); link từ ESS
- QR cổng: `gate_token_hash` trên zone; `POST .../issue-gate-token`; payload `EHR-PUNCH|company_id|zone_code|token`
- Test: `GeofenceServiceTest`, `AttendancePunchTest`

**Payroll v2 — Lương tách TV/CT (2026-05-30):**
- `PayrollEarningsService` — gross = (Lương TV × công TV + Lương CT × công CT) / công chuẩn + OT theo **ngày phát sinh** (đơn giá giờ TV/CT)
- `PhasedIncomeCalculator` — khoản theo tháng (chuyên cần, phụ cấp): `full_month` | `prorate_by_days` | `end_of_period_official` | `official_from_start_date` | `probation_only` | **`per_work_day`** (ăn ca); cấu hình CC tại Settings, phụ cấp tại `payroll_vn.phased_income.allowance_mode_overrides`
- `PayrollPreviousMonthService` — thưởng NS tách `performance_bonus_probation` / `performance_bonus_official` theo LCB TV/CT tháng T-1; fallback phân bổ theo công tháng hiện tại nếu T-1 thiếu breakdown phase
- `EmployeePayrollAllowanceService.mergeForPayroll` — trả `phased_allowances`; tab Trợ cấp có `phased_preview` (TV/CT) + modal preview
- Export Excel / phiếu lương: cột breakdown TV/CT cho LCB, OT, chuyên cần, thưởng NS, phụ cấp
- BHXH: mặc định **không đóng** cả tháng TV; tháng chuyển giai đoạn → prorate theo công CT (`PAYROLL_BHXH_ON_PROBATION`)
- OT dùng hệ số 150/200/300% từ `config/hr_vn.php`, lương giờ theo mức TV hoặc CT
- Phiếu lương + `PayrollListPage` hiển thị cột Lương TV / CT / OT
- NV demo **`EMP-TVCT`** — Nguyễn Thị Lan (TV→CT): vào làm đầu tháng, **hết TV ngày 15**, CT từ **16**; 2 HĐ (`CTR-TVCT-PB` / `CTR-TVCT-CT`); LCB TV 13.6M / CT 16M; log chấm công + OT 2 giai đoạn + trợ cấp + tổng hợp công tự động (`SampleEmployeesSeeder`)
- NV demo **`EMP-LVS`** — **Lê Văn Sơn**: hết TV **20/05/2026**, CT từ **21/05**; log chấm công **04 + 05/2026**, tổng hợp tab **Công ngày / Công tháng / TV→CT / BP**

**Payslip Phase 1 — BPVN-AC-PR-006 (2026-05-29):**
- Migration `100260`: bảng `payslip_templates`, setting `payslip_template_code` mặc định `bpvn-ac-pr-006`
- `PayslipRenderService` + `BpvnPayslipMapper` — map `breakdown` → ~46 dòng song ngữ; dòng chưa có = 0
- Template: `resources/views/payslips/templates/bpvn-ac-pr-006.blade.php`; fallback `simple` → `payslips/show.blade.php`
- API: `GET /api/v1/payroll-results/{id}/payslip` (HR + ESS modal)
- Config map trợ cấp: `config/payslip_templates.php` — Phase 2–3 bổ sung OT tách ngày/đêm, trợ cấp riêng
- Test: `PayslipRenderTest`

**Attendance Phase 2a — OT grid + nghỉ theo loại (2026-05-29):**
- Migration `100270`: cột `attendance_summaries.attendance_breakdown` (JSON)
- `AttendanceOtGridCalculator` + `AttendanceBreakdownBuilder` — lưới OT P–X, `leave_by_type`, **`ot_by_phase` / `leave_by_phase`** (tách TV/CT cùng kỳ)
- `LeaveDayCalculator` — đếm phép CL/KL; tách TV/CT theo ngày nghỉ thực tế (`summarizeForEmployee`, `summarizeByLeaveTypeByPhase`)
- `AttendanceWorkDaysPhaseSplitter::splitAllLeaveDaysByPhaseWeights` — fallback import Excel (phân bổ theo công chuẩn từng giai đoạn)
- `PayrollEarningsService` — công tính lương = công đi làm + phép CL theo từng giai đoạn; phép KL không cộng vào payable
- `LeaveDayCalculator::summarizeByLeaveType()` — map `leave_types.code` qua `config/attendance_vn.php`
- Loại nghỉ mới trong `hr_vn.php`: VIEC_RIENG, CUOI, CONG_TY, KINH_NGUYET
- Payroll merge `attendance_breakdown` → `payroll_results.breakdown`; phiếu BPVN map OT/nghỉ chi tiết
- UI: tab Công theo tháng → nút **Xem** breakdown; OT report có `ot_grid`
- Test: `AttendanceBreakdownTest`

**Payroll Phase 2b — Trợ cấp tháng (2026-05-29):**
- Migration `100280`: `employee_payroll_allowances` (NV × kỳ × JSON trợ cấp)
- `config/payroll_allowances.php` — danh mục cột I–Z sheet lương BestPacific
- `EmployeePayrollAllowanceService` — upsert, copy tháng trước, merge vào gross/breakdown
- API: `GET/POST /payroll-allowances`, `POST /payroll-allowances/copy-previous`, `GET /payroll-allowances/catalog`
- UI: `PayrollListPage` tab **Trợ cấp tháng**
- Trợ cấp chịu thuế cộng vào gross; BH cty thử việc chỉ hiển thị phiếu (không cộng gross NLĐ)
- Test: `EmployeePayrollAllowanceTest`

**Payroll Phase 2c — OT tiền theo lưới (2026-05-29):**
- `PayrollOtGridPayService` — tiền OT = lương giờ × giờ lưới × hệ số BPVN (ngày 150–300%, đêm 210–390%)
- `config/payroll_vn.php`: `ot_grid_multipliers`, `use_ot_grid_pay`
- `PayrollEarningsService` dùng lưới thay `ot_pay` tổng đơn; breakdown: `ot_pay_grid`, `ot_day_pay`, `ot_night_pay`
- Phiếu BPVN: dòng 29 (TC ngày), 34 (TC đêm), 35 (tổng TC)
- Test: `PayrollOtGridPayServiceTest`

**Payroll Phase 2d — Import Excel công/lương (2026-05-29):**
- Mẫu chuẩn: `storage/app/templates/cong-va-luong-mau.xlsx` (file BP « công và lương »)
- `CongLuongSheetService` + `GET /attendance-reports/cong-luong-sheet` — UI tab **Công & lương BP** (bảng ngang A→AO / lương AA, header tiếng Việt; LCB từ HĐLĐ)
- Import map cột AK (chưa vào), AL (ngày vào), AN (试用/正式), AO (在职) · tách TV/CT theo trạng thái sheet
- `CongLuongImportService` + `SimpleXlsxReader` đọc đa sheet theo tên
- Config map cột: `config/cong_luong_import.php`
- API: `POST /api/v1/payroll-import/cong-luong` (multipart: `period`, `file`)
- Sheet công → `attendance_summaries.attendance_breakdown` + cột tổng hợp
- Sheet lương → `employee_payroll_allowances`
- UI: `PayrollListPage` tab **Import Excel**
- Test: `CongLuongImportTest` (cần file `storage/app/temp-cong-luong.xlsx`)

**Payroll Phase 3 — Điều chỉnh T-1 + thưởng NS theo lương tháng trước (2026-05-29):**
- Migration `100290`: `employee_payroll_allowances.prev_month_adjustment`
- `PayrollPreviousMonthService`: thưởng NS = `base_pay_total` tháng T-1 × điểm KPI T-1 × tỷ lệ; **tách TV/CT** khi breakdown T-1 có `probation_base_pay` / `official_base_pay`
- `PayrollCycleService` ghi đè `performance_bonus` công thức hiện tại bằng logic T-1
- Phiếu lương **chỉ tiếng Việt** — template `bpvn-ac-pr-006.blade.php`, bỏ dòng họ tên/bộ phận Trung
- Test: `PayrollPreviousMonthServiceTest`

**Attendance — Ca làm việc theo nhóm SX / phi SX (2026-05-29):**
- Migration `100300`: `work_schedule_groups`, `work_schedule_patterns`, `employee_work_schedules`, `overtime_excess_records`, `attendance_summaries.compliance_alerts`
- Config: `config/work_schedule_vn.php` — preset 5D8H, 6D8H, max 13 ngày liên tục
- Services: `WorkScheduleResolver`, `WorkScheduleComplianceService`, `OvertimeExcessService`, `WorkScheduleSetupService`
- API prefix: `/api/v1/work-schedules/*` (groups, patterns, assignments, compliance-alerts, overtime-excess)
- UI: `WorkSchedulePage.vue` — menu **Ca làm việc** (perm `attendance.manage`)
- OT vượt mức (4h/ngày, 40h/tháng, 200h/năm) → bảng riêng, trừ khỏi tính lương
- Test: `WorkScheduleTest`

**Khóa công / khóa lương tháng (2026-05-29, migration `100310`):**
- Bảng `attendance_period_locks` — khóa kỳ công trước khi tính lương; `AttendancePeriodLockService`
- `payroll_cycles`: thêm `locked_by`, `unlocked_by`, `unlocked_at`; status `locked` sau `calculated`
- `PayrollCycleLockService` — khóa/mở lương; chặn tính lại + sửa trợ cấp khi locked
- **Chỉ role `admin`** được mở khóa công hoặc lương
- API: `POST attendance-summaries/lock|unlock`, `GET attendance-summaries/period-status`, `POST payroll-cycles/{id}/lock|unlock`
- Ca làm việc phase 2: `work_schedule_week_overrides` (hoán đổi T7/CN theo tuần), `POST work-schedules/assignments/bulk`
- UI: `PayrollListPage` (khóa công/lương), `AttendancePage` (banner kỳ khóa), `WorkSchedulePage` (tab Gán hàng loạt, Hoán đổi tuần)
- Test: `AttendancePayrollLockTest`, mở rộng `WorkScheduleTest`

**Chính sách theo công ty — Policy Pack Phase 1 (2026-05-30, migration `100320`–`100321`):**
- Gói mẫu: `textile` (dệt), `garment` (may), `trading` (KD — 5D8H, thưởng doanh số)
- Config: `config/company_policy_templates.php` · DB: `policy_templates`, `policy_template_items`
- `companies`: `industry_code`, `policy_template_code`, `policy_applied_at`
- Service: `CompanyPolicyTemplateService` — apply / sync catalog / migrate CTTV hiện có
- API: `GET /policy-templates`, `POST /companies/{id}/apply-policy-template` (admin + `policy_templates.apply`)
- Permission: `policy_templates.view|apply`, `company_policies.view|manage` (HR chỉnh khi được cấp)
- UI: `OrganizationPage.vue` — chọn gói khi tạo CTTV, admin áp lại gói
- Trợ cấp KD: `allowance_sales_commission` + công thức `SALES_COMMISSION`
- Test: `CompanyPolicyTemplateTest`

**Chính sách công ty — Phase 2–4 (2026-05-30, migration `100330`):**
- `CompanyPolicyResolver` — đọc settings + defaults + version theo kỳ
- `company_policy_versions` — lịch sử thay đổi theo miền
- Config: `company_policy_domains.php`, `company_policy_defaults.php`
- API: `GET/PUT /company-policies`, `/domains/{domain}`, `/versions`, `/export`, `/import`, `/group-comparison`
- **Áp dụng theo NV (2026-05-30):** migration `100340` → `employee_policy_settings`; `POST /company-policies/apply-to-employees`, `GET /employee-overrides`; `CompanyPolicyResolver::for($companyId, $period, $employeeId)` merge NV sau version công ty; `PayrollContextBuilder` truyền `employee_id`
- Refactor: `PayrollContextBuilder`, `PayrollPreviousMonthService`, `AttendancePunchService`, `DiligenceSettingsService`
- UI: `CompanyPolicyPage.vue` — phạm vi toàn công ty hoặc 1/nhiều NV (`EmployeeTargetPicker` + `includeCompanyMode`)
- Báo cáo tập đoàn: `group-summary` thêm `industry_code`, `policy_template_code`, `standard_working_days`
- Test: `CompanyPolicyPhaseTest`, `BulkEmployeeRegistrationTest`

**Cần chạy migration** trước khi test: `php artisan migrate`

### Phase M2-C — Pagination Enforcement DONE (2026-05-28)

**Paginate đúng nghĩa (backend + frontend):**
- `EmploymentContractController::index()` → `paginate(25)`, hỗ trợ `?all=1` bypass cho dropdown, `ContractListPage.vue` thêm pagination controls
- `CourseController::index()` → `paginate(20)`, bỏ nested `enrollments.employee` nặng, `TrainingPage.vue` adapted
- `PublicRecruitmentController::jobPosts()` → `paginate(20)`, `CareerPage.vue` adapted
- `BenefitPlanController::enrollments()` → `paginate(50)`

**Giới hạn an toàn (chỉ backend):**
- `OnboardingController::index()` → `limit(150)`
- `AttendanceSummaryController::index()` → `limit(500)` (scoped to company+period anyway)
- `RecruitmentRequestController::index()` → `limit(200)`
- `JobPostController::index()` → `limit(100)`
- `PerformanceController::cycles()` → `limit(24)`, thu gọn select relations
- `EmployeeTerminationController::all()` → `limit(300)`
- `SelfServiceController`: contracts `limit(20)`, payslips `limit(36)`, leaveRequests `limit(100)`

**Giữ `->get()` hợp lý** (reference data nhỏ < 100 rows): LeaveType, Department, Position, Branch, Role, Permission, WorkShift, ContractType, CompanyHoliday, JobLevel, AttendanceDevice, và tất cả per-employee sub-resources.

**`usePagination` composable:** đã có sẵn, xử lý cả paginated và plain array — các page khác không cần sửa.

### Phase M2-B — Token Expiration DONE (2026-05-28)

- Backend đã có từ trước: `token_expires_at` column (migration `100150`), `User.isTokenExpired()`, `AuthController` login/logout/rotate, middleware `AuthenticateApiToken` check + trả `TOKEN_EXPIRED`
- Frontend đã có từ trước: `auth.js` store (`isTokenExpired`, `tokenExpiresIn`, `rotate()`), `client.js` interceptor redirect khi 401
- **Thêm mới:** Timer proactive rotation trong `AppLayout.vue` — kiểm tra mỗi 5 phút, rotate khi còn ≤ 30 phút
- Config TTL: `config('auth.token_ttl_hours', 8)` — mặc định 8 giờ

### Phase M2-A — Audit Log DONE (2026-05-28)

- `AuditLogger` service + `AuditLog` model + migrations đã có từ trước
- Hook thêm vào: `EmploymentContractController` (create/update/delete), `ApprovalController` (approve/reject), `EmployeeTerminationController` (store/approve)
- `AuditLogController` nâng cấp: filter theo category/action/entity/actor/date/q + company scope
- `AuditLogPage.vue` tại `/app/audit-log` — bảng log, filter, diff trước/sau, phân trang
- Menu sidebar: nhóm "Quy trình & Báo cáo" → icon 🛡️, perm `audit_logs.view`
- Coverage hiện tại: Auth, User (role assign/company access), Employee, BHXH export, Payroll finalize, Leave/OT approve, Contract CRUD, Approval workflow, Termination

### Đã hoàn thành thêm (UX1 — 2026-05-27)

- Dashboard cá nhân: phép còn lại, việc cần làm, yêu cầu đang xử lý, thông báo
- ESS đầy đủ: 7 tab (hồ sơ, bảng công, nghỉ phép + quỹ phép, phiếu lương, hợp đồng, KPI, tài liệu)
- Quỹ phép toàn công ty + lịch nghỉ calendar view
- Báo cáo mở rộng: 7 tab (headcount, biến động, cơ cấu, chấm công, lương, gap NL, KPI)
- API: `GET /self-service/leave-requests`, `GET /self-service/attendance-summary`

## Quy ước khi agent sửa code

1. Chỉ mở file trong hàng **Map module** ở trên + test liên quan.
2. Quy ước code: `.claude/rules/ehr-conventions.md`.
3. Tầm nhìn / roadmap: `docs/hcm-master-design.md` — chỉ khi plan lớn.
4. Sau phase: cập nhật **file này** + 1 đoạn `README-HCM.md` nếu đổi lệnh chạy / module list.
5. Trừ khi user yêu cầu: không chạy `migrate` / `test` / `build` hàng loạt.

## Tài liệu khác (đọc khi cần sâu)

- `README-HCM.md` — cài đặt, luồng lương/nghỉ mẫu
- `docs/api-reference.md`
- `docs/bhxh-module.md`, `docs/employee-profile-vn.md`
- `docs/hcm-master-design.md` — tầm nhìn / roadmap (không chứa as-is)

## Workspace

| Path | Mô tả |
|------|--------|
| `f:\HRM\EHR` | **HCM — làm việc ở đây** |
| `f:\HRM\.claude/rules/` | Chỉ `ehr-conventions.md` (toolkit skills/commands đã gỡ) |
