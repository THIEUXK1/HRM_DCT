<template>
  <div>
    <UiPageHeader title="Thiết lập hệ thống" subtitle="Cấu hình chế độ lao động Việt Nam · Ca làm việc · Cấp bậc · Lịch công ty · Phân quyền RBAC" breadcrumb="System Settings" />

    <!-- Tabs Navigation -->
    <div class="mb-4 flex gap-2 border-b border-slate-200 overflow-x-auto">
      <button
        v-for="tab in visibleTabs"
        :key="tab.id"
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap"
        :class="activeTab === tab.id ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="activeTab = tab.id"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Tab Content Area -->
    <div class="hcm-card p-6">
      
      <!-- TAB 1: THÔNG TIN CÔNG TY -->
      <div v-if="activeTab === 'company'" class="space-y-6">
        <div class="border-b border-slate-100 pb-3">
          <h3 class="text-base font-semibold text-slate-900">Hồ sơ pháp lý công ty</h3>
          <p class="text-sm text-slate-500">Thiết lập thông tin đăng ký doanh nghiệp và thông tin đại diện pháp luật.</p>
        </div>
        <form class="space-y-4 max-w-3xl" @submit.prevent="saveCompanyProfile">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-slate-700">Tên doanh nghiệp</label>
              <input v-model="companyForm.name" class="hcm-input mt-1 w-full" required />
            </div>
            <div>
              <label class="text-sm font-medium text-slate-700">Mã doanh nghiệp / MST</label>
              <input v-model="companyForm.tax_code" class="hcm-input mt-1 w-full" placeholder="VD: 0101234567" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-slate-700">Người đại diện pháp luật</label>
              <input v-model="companyForm.legal_representative" class="hcm-input mt-1 w-full" placeholder="Ông/Bà..." />
            </div>
            <div>
              <label class="text-sm font-medium text-slate-700">Số điện thoại liên hệ</label>
              <input v-model="companyForm.phone" class="hcm-input mt-1 w-full" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-slate-700">Mã đơn vị BHXH (Mã VSS)</label>
              <input v-model="companyForm.social_insurance_unit_code" class="hcm-input mt-1 w-full" placeholder="VD: DV0012A" />
            </div>
            <div>
              <label class="text-sm font-medium text-slate-700">Cơ quan BHXH quản lý</label>
              <input v-model="companyForm.social_insurance_agency" class="hcm-input mt-1 w-full" placeholder="VD: BHXH Quận Cầu Giấy" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-slate-700">Prefix mã nhân viên (đồng bộ EHR)</label>
              <input v-model="companyForm.employee_code_prefix" class="hcm-input mt-1 w-full font-mono uppercase" maxlength="10" placeholder="VD: V, Y, I, M" />
              <p class="text-xs text-slate-400 mt-1">Ký tự đầu EMPNO để định tuyến NV về đúng công ty khi sync EHR cũ.</p>
            </div>
            <div>
              <label class="text-sm font-medium text-slate-700">Địa chỉ trụ sở chính</label>
              <input v-model="companyForm.address" class="hcm-input mt-1 w-full" />
            </div>
          </div>
          <div>
            <button type="submit" class="hcm-btn-primary px-6" :disabled="savingCompany">
              {{ savingCompany ? 'Đang lưu...' : 'Lưu thông tin hồ sơ' }}
            </button>
          </div>
        </form>
      </div>

      <!-- TAB 2: CƠ CẤU PHÒNG BAN & BỘ PHẬN -->
      <div v-if="activeTab === 'departments'" class="space-y-6">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-semibold text-slate-900">Danh sách Phòng ban & Bộ phận</h3>
            <p class="text-sm text-slate-500">
              Thiết lập cơ cấu tổ chức của <strong>{{ companyForm.name || 'công ty đang chọn' }}</strong>.
              <span v-if="singleBranchMode">Công ty một địa điểm — phòng ban được gán tự động vào trụ sở chính.</span>
              <span v-else>Chọn chi nhánh khi công ty có nhiều địa điểm.</span>
            </p>
          </div>
          <button type="button" class="hcm-btn-primary text-sm" @click="openDepartmentModal()">
            + Thêm phòng ban/bộ phận
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="hcm-table w-full">
            <thead>
              <tr>
                <th>Mã phòng/bộ phận</th>
                <th>Tên gọi</th>
                <th v-if="!singleBranchMode">Chi nhánh</th>
                <th>Thuộc phòng ban</th>
                <th>Trưởng bộ phận</th>
                <th>Trạng thái</th>
                <th>Hành động</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="dept in departments" :key="dept.id" class="hover:bg-slate-50">
                <td class="font-mono text-xs">{{ dept.code }}</td>
                <td class="font-medium">
                  <span v-if="dept.parent_department_id" class="text-slate-400 mr-1">└──</span>
                  {{ dept.name }}
                </td>
                <td v-if="!singleBranchMode" class="text-sm">{{ dept.branch?.name }}</td>
                <td class="text-sm text-slate-500">{{ dept.parent?.name || '—' }}</td>
                <td class="text-sm font-medium text-slate-600">{{ dept.manager?.full_name || '—' }}</td>
                <td>
                  <UiBadge :variant="dept.is_active ? 'success' : 'default'">
                    {{ dept.is_active ? 'Hoạt động' : 'Ngưng' }}
                  </UiBadge>
                </td>
                <td class="space-x-3">
                  <button type="button" class="text-sm text-primary-600 hover:underline" @click="openDepartmentModal(dept)">Sửa</button>
                  <button type="button" class="text-sm text-red-600 hover:underline" @click="deleteDepartment(dept.id)">Xóa</button>
                </td>
              </tr>
              <tr v-if="!departments.length">
                <td colspan="7" class="text-center py-6 text-slate-400">Chưa cấu hình phòng ban nào.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 3: CA LÀM VIỆC -->
      <div v-if="activeTab === 'shifts'" class="space-y-6">
        <div class="flex flex-wrap justify-between items-start gap-3 border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-semibold text-slate-900">Ca làm việc của công ty</h3>
            <p class="text-sm text-slate-500">Tự định nghĩa ca ngày/đêm. Ca đêm: 22h–6h (+30% phụ cấp, Điều 106 BLLĐ 2019).</p>
          </div>
          <div class="flex gap-2">
            <button type="button" class="hcm-btn-secondary text-sm" @click="seedShiftPresets">
              ↻ Ca HC + Ca đêm mẫu
            </button>
            <button type="button" class="hcm-btn-primary text-sm" @click="openShiftModal()">
              + Thêm ca làm việc
            </button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="hcm-table w-full">
            <thead>
              <tr>
                <th>Mã ca</th>
                <th>Tên ca</th>
                <th>Giờ bắt đầu</th>
                <th>Giờ kết thúc</th>
                <th>Số phút nghỉ giữa ca</th>
                <th>Giờ chuẩn</th>
                <th>Loại ca</th>
                <th>Tuân thủ BLLĐ</th>
                <th>Hành động</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="shift in shifts" :key="shift.id" class="hover:bg-slate-50">
                <td class="font-mono text-xs">{{ shift.code }}</td>
                <td class="font-medium">{{ shift.name }}</td>
                <td class="font-mono text-sm">{{ formatTime(shift.start_time) }}</td>
                <td class="font-mono text-sm">{{ formatTime(shift.end_time) }}</td>
                <td class="text-sm">{{ shift.break_minutes }} phút</td>
                <td class="text-sm">{{ shift.standard_hours || 8 }}h</td>
                <td>
                  <UiBadge :variant="isNightShift(shift) ? 'warning' : 'info'">
                    {{ shift.is_night_shift || isNightShift(shift) ? 'Ca đêm' : 'Ca ngày' }}
                  </UiBadge>
                </td>
                <td>
                  <UiBadge :variant="checkShiftBreakCompliance(shift).valid ? 'success' : 'default'">
                    {{ checkShiftBreakCompliance(shift).message }}
                  </UiBadge>
                </td>
                <td class="space-x-3">
                  <button type="button" class="text-sm text-primary-600 hover:underline" @click="openShiftModal(shift)">Sửa</button>
                  <button type="button" class="text-sm text-red-600 hover:underline" @click="deleteShift(shift.id)">Xóa</button>
                </td>
              </tr>
              <tr v-if="!shifts.length">
                <td colspan="8" class="text-center py-6 text-slate-400">Chưa thiết lập ca làm việc.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 4: CHẾ ĐỘ & LUẬT LAO ĐỘNG -->
      <div v-if="activeTab === 'policies'" class="space-y-6">
        <div class="border-b border-slate-100 pb-3">
          <h3 class="text-base font-semibold text-slate-900">Cấu hình chế độ & Tỷ lệ đóng BHXH</h3>
          <p class="text-sm text-slate-500">Cấu hình các tham số tính lương, đóng bảo hiểm bắt buộc và làm thêm giờ (OT) chuẩn Việt Nam.</p>
        </div>
        <form class="space-y-6 max-w-4xl" @submit.prevent="savePolicies">
          <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/60">
            <h4 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
              🏛️ Tỷ lệ bảo hiểm xã hội bắt buộc (Quyết định 595/QĐ-BHXH)
            </h4>
            <div class="grid grid-cols-2 gap-6">
              <div>
                <label class="text-sm font-medium text-slate-700">Tỷ lệ Doanh nghiệp đóng (%)</label>
                <div class="flex items-center gap-2 mt-1">
                  <input v-model="policyForm.insurance_rate_employer" type="number" step="0.1" class="hcm-input w-full" required />
                  <span class="text-xs text-slate-500">(Chuẩn luật: 21.5%)</span>
                </div>
              </div>
              <div>
                <label class="text-sm font-medium text-slate-700">Tỷ lệ Người lao động đóng (%)</label>
                <div class="flex items-center gap-2 mt-1">
                  <input v-model="policyForm.insurance_rate_employee" type="number" step="0.1" class="hcm-input w-full" required />
                  <span class="text-xs text-slate-500">(Chuẩn luật: 10.5%)</span>
                </div>
              </div>
            </div>
            <p class="text-xs text-slate-500 mt-2">Bao gồm các chế độ: Hưu trí, Ốm đau thai sản, TNLĐ-BNN, BHYT và BHTN.</p>
          </div>

          <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/60">
            <h4 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
              ⏱️ Hệ số làm thêm giờ (Điều 98 BLLĐ 2019)
            </h4>
            <div class="grid grid-cols-3 gap-6">
              <div>
                <label class="text-sm font-medium text-slate-700">Ngày làm việc thường</label>
                <div class="flex items-center gap-2 mt-1">
                  <input v-model="policyForm.ot_coeff_weekday" type="number" step="0.1" class="hcm-input w-full" required />
                  <span class="text-xs text-slate-500">(Min: 1.5)</span>
                </div>
              </div>
              <div>
                <label class="text-sm font-medium text-slate-700">Ngày nghỉ hàng tuần</label>
                <div class="flex items-center gap-2 mt-1">
                  <input v-model="policyForm.ot_coeff_weekend" type="number" step="0.1" class="hcm-input w-full" required />
                  <span class="text-xs text-slate-500">(Min: 2.0)</span>
                </div>
              </div>
              <div>
                <label class="text-sm font-medium text-slate-700">Ngày lễ, tết nghỉ</label>
                <div class="flex items-center gap-2 mt-1">
                  <input v-model="policyForm.ot_coeff_holiday" type="number" step="0.1" class="hcm-input w-full" required />
                  <span class="text-xs text-slate-500">(Min: 3.0)</span>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/60">
            <h4 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
              🏖️ Ngày nghỉ phép năm & công công chuẩn (Điều 113 BLLĐ 2019)
            </h4>
            <div class="grid grid-cols-2 gap-6">
              <div>
                <label class="text-sm font-medium text-slate-700">Số ngày phép năm tiêu chuẩn</label>
                <div class="flex items-center gap-2 mt-1">
                  <input v-model="policyForm.annual_leave_standard" type="number" class="hcm-input w-full" required />
                  <span class="text-xs text-slate-500">(Mặc định: 12 ngày)</span>
                </div>
              </div>
              <div>
                <label class="text-sm font-medium text-slate-700">Số ngày công tính lương chuẩn tháng</label>
                <div class="flex items-center gap-2 mt-1">
                  <input v-model="policyForm.standard_working_days" type="number" class="hcm-input w-full" required />
                  <span class="text-xs text-slate-500">(Mặc định: 26 công)</span>
                </div>
              </div>
            </div>
          </div>

          <div>
            <button type="submit" class="hcm-btn-primary px-6" :disabled="savingPolicies">
              {{ savingPolicies ? 'Đang lưu...' : 'Lưu cấu hình chế độ' }}
            </button>
          </div>
        </form>
      </div>

      <!-- TAB: Chấm công & Thưởng chuyên cần -->
      <div v-if="activeTab === 'attendance'" class="space-y-8 max-w-4xl">
        <form class="space-y-6" @submit.prevent="saveDiligenceSettings">
          <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/60">
            <h4 class="text-sm font-semibold text-slate-900 mb-3">📍 Chấm công GPS / QR</h4>
            <label class="flex items-center gap-2 text-sm mb-3">
              <input v-model="diligenceForm.attendance_mobile_punch_enabled" type="checkbox" true-value="1" false-value="0" class="rounded text-primary-600" />
              Cho phép NV chấm công qua điện thoại (GPS + QR cổng)
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="diligenceForm.attendance_geofence_strict" type="checkbox" true-value="1" false-value="0" class="rounded text-primary-600" />
              Bắt buộc trong geofence (tắt để test / môi trường linh hoạt)
            </label>
            <p class="text-xs text-slate-500 mt-2">Cấu hình vùng geofence & in QR tại <b>Bảng công → Thiết bị & Import</b>.</p>
          </div>
          <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/60">
            <h4 class="text-sm font-semibold text-slate-900 mb-3">⭐ Thưởng chuyên cần</h4>
            <label class="flex items-center gap-2 text-sm mb-4">
              <input v-model="diligenceForm.diligence_bonus_enabled" type="checkbox" true-value="1" false-value="0" class="rounded text-primary-600" />
              Bật thưởng chuyên cần hàng tháng
            </label>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-slate-700">Mức thưởng chung (VND/tháng)</label>
                <input v-model="diligenceForm.diligence_bonus_amount" type="number" min="0" class="hcm-input w-full mt-1" />
              </div>
              <div>
                <label class="text-sm font-medium text-slate-700">Cách tính khi TV→CT cùng kỳ</label>
                <select v-model="diligenceForm.diligence_phase_mode" class="hcm-input w-full mt-1">
                  <option value="full_month">Trả nguyên tháng nếu đủ điều kiện</option>
                  <option value="prorate_by_days">Chia theo công TV / CT (mức khác nhau)</option>
                  <option value="end_of_period_official">Xét trạng thái cuối kỳ</option>
                </select>
              </div>
              <div>
                <label class="text-sm font-medium text-slate-700">Mức CC thử việc/tháng</label>
                <input v-model="diligenceForm.diligence_bonus_amount_probation" type="number" min="0" class="hcm-input w-full mt-1" placeholder="Để trống = mức chung" />
              </div>
              <div>
                <label class="text-sm font-medium text-slate-700">Mức CC chính thức/tháng</label>
                <input v-model="diligenceForm.diligence_bonus_amount_official" type="number" min="0" class="hcm-input w-full mt-1" placeholder="Để trống = mức chung" />
              </div>
              <div>
                <label class="text-sm font-medium text-slate-700">Tỷ lệ chuyên cần tối thiểu (%)</label>
                <input v-model="diligenceForm.diligence_min_attendance_rate" type="number" min="0" max="100" class="hcm-input w-full mt-1" />
              </div>
              <div>
                <label class="text-sm font-medium text-slate-700">Số lần đi trễ tối đa</label>
                <input v-model="diligenceForm.diligence_max_late_count" type="number" min="0" class="hcm-input w-full mt-1" />
              </div>
              <div>
                <label class="text-sm font-medium text-slate-700">Ngày vắng không phép tối đa</label>
                <input v-model="diligenceForm.diligence_max_absent_days" type="number" min="0" step="0.5" class="hcm-input w-full mt-1" />
              </div>
              <div class="sm:col-span-2">
                <label class="text-sm font-medium text-slate-700">Quên chấm công tối đa / tháng (vượt → mất thưởng)</label>
                <input v-model="diligenceForm.diligence_max_forgot_punch" type="number" min="0" class="hcm-input w-full mt-1" />
                <p class="text-xs text-slate-500 mt-1">Chỉ tính đơn bù thẻ duyệt với lý do được đánh dấu «Quên chấm công».</p>
              </div>
            </div>
          </div>
          <button type="submit" class="hcm-btn-primary px-6" :disabled="savingDiligence">
            {{ savingDiligence ? 'Đang lưu...' : 'Lưu quy tắc chuyên cần' }}
          </button>
        </form>

        <div v-if="can('leave.manage')" class="border-t border-slate-200 pt-8">
          <div class="flex flex-wrap justify-between items-start gap-3 border-b border-slate-100 pb-3 mb-4">
            <div>
              <h3 class="text-base font-semibold text-slate-900">Loại nghỉ phép & ký hiệu bảng công</h3>
              <p class="text-sm text-slate-500">Phân loại theo BLLĐ 2019: có lương công ty · không lương · hưởng BHXH. Admin tự thêm/sửa mã công (PN, KL, Ô…).</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="hcm-btn-secondary text-sm" @click="seedLeaveTypes">↻ Áp dụng danh mục chuẩn VN</button>
              <button type="button" class="hcm-btn-primary text-sm" @click="openLeaveTypeModal()">+ Thêm loại nghỉ</button>
            </div>
          </div>
          <table class="hcm-table w-full">
            <thead>
              <tr>
                <th>Mã</th>
                <th>Tên loại nghỉ</th>
                <th>Ký hiệu</th>
                <th>Nhóm lương</th>
                <th>Cách tính ngày</th>
                <th>Thứ tự</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="lt in leaveTypes" :key="lt.id" class="hover:bg-slate-50">
                <td class="font-mono text-xs">{{ lt.code }}</td>
                <td>
                  <p class="font-medium">{{ lt.name }}</p>
                  <p v-if="lt.legal_reference" class="text-[11px] text-slate-400">{{ lt.legal_reference }}</p>
                </td>
                <td>
                  <span
                    class="inline-flex min-w-[2rem] items-center justify-center rounded px-1.5 py-0.5 text-xs font-bold border border-slate-200"
                    :style="leaveSymbolStyle(lt)"
                  >
                    {{ lt.cell_symbol || '—' }}
                  </span>
                </td>
                <td>
                  <UiBadge :variant="payrollCategoryVariant(lt.payroll_category)">
                    {{ payrollCategoryLabel(lt.payroll_category, lt.is_paid) }}
                  </UiBadge>
                </td>
                <td class="text-sm text-slate-600">{{ dayCountModeLabel(lt.day_count_mode) }}</td>
                <td>{{ lt.sort_order }}</td>
                <td class="space-x-2 whitespace-nowrap">
                  <button type="button" class="text-sm text-primary-600 hover:underline" @click="openLeaveTypeModal(lt)">Sửa</button>
                  <button type="button" class="text-sm text-red-600 hover:underline" @click="deleteLeaveType(lt.id)">Xóa</button>
                </td>
              </tr>
              <tr v-if="!leaveTypes.length">
                <td colspan="7" class="text-center py-6 text-slate-400">Chưa có loại nghỉ — bấm «Áp dụng danh mục chuẩn VN».</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="can('payroll.manage')" class="border-t border-slate-200 pt-8 mt-8">
          <div class="flex flex-wrap justify-between items-start gap-3 border-b border-slate-100 pb-3 mb-4">
            <div>
              <h3 class="text-base font-semibold text-slate-900">Danh mục loại thưởng</h3>
              <p class="text-sm text-slate-500">Thưởng chuyên cần, KPI, doanh số, lễ Tết, tháng 13… — cấu hình theo quy chế thưởng công ty.</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="hcm-btn-secondary text-sm" @click="seedBonusTypes">↻ Áp dụng danh mục chuẩn VN</button>
              <button type="button" class="hcm-btn-primary text-sm" @click="openBonusTypeModal()">+ Thêm loại thưởng</button>
            </div>
          </div>
          <table class="hcm-table w-full">
            <thead>
              <tr>
                <th>Mã</th>
                <th>Tên khoản thưởng</th>
                <th>Nhóm</th>
                <th>Cách tính</th>
                <th>Tính gross</th>
                <th>Trạng thái</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="bt in bonusTypes" :key="bt.id" class="hover:bg-slate-50">
                <td class="font-mono text-xs">{{ bt.code }}</td>
                <td>
                  <p class="font-medium">{{ bt.name }}</p>
                  <p v-if="bt.legal_reference" class="text-[11px] text-slate-400">{{ bt.legal_reference }}</p>
                </td>
                <td class="text-sm">{{ bonusCategoryLabel(bt.category) }}</td>
                <td class="text-sm text-slate-600">{{ bonusCalcModeLabel(bt.calculation_mode) }}</td>
                <td>
                  <UiBadge :variant="bt.counts_in_gross ? 'success' : 'default'">{{ bt.counts_in_gross ? 'Có' : 'Không' }}</UiBadge>
                </td>
                <td>
                  <UiBadge :variant="bt.is_active ? 'success' : 'default'">{{ bt.is_active ? 'Dùng' : 'Tắt' }}</UiBadge>
                </td>
                <td class="space-x-2 whitespace-nowrap">
                  <button type="button" class="text-sm text-primary-600 hover:underline" @click="openBonusTypeModal(bt)">Sửa</button>
                  <button type="button" class="text-sm text-red-600 hover:underline" @click="deleteBonusType(bt.id)">Xóa</button>
                </td>
              </tr>
              <tr v-if="!bonusTypes.length">
                <td colspan="7" class="text-center py-6 text-slate-400">Chưa có loại thưởng — bấm «Áp dụng danh mục chuẩn VN».</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div>
          <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
            <div>
              <h3 class="text-base font-semibold text-slate-900">Lý do bù thẻ chấm công</h3>
              <p class="text-sm text-slate-500">Admin tự thêm/sửa — không cần sửa code.</p>
            </div>
            <button type="button" class="hcm-btn-primary text-sm" @click="openReasonModal()">+ Thêm lý do</button>
          </div>
          <table class="hcm-table w-full">
            <thead>
              <tr>
                <th>Mã</th>
                <th>Tên lý do</th>
                <th>Tính quên chấm</th>
                <th>Thứ tự</th>
                <th>Trạng thái</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in correctionReasons" :key="r.id" class="hover:bg-slate-50">
                <td class="font-mono text-xs">{{ r.code }}</td>
                <td class="font-medium">{{ r.name }}</td>
                <td>
                  <UiBadge :variant="r.counts_as_forgot_punch ? 'warning' : 'default'">
                    {{ r.counts_as_forgot_punch ? 'Có (trừ thưởng)' : 'Không' }}
                  </UiBadge>
                </td>
                <td>{{ r.sort_order }}</td>
                <td>
                  <UiBadge :variant="r.is_active ? 'success' : 'default'">{{ r.is_active ? 'Dùng' : 'Tắt' }}</UiBadge>
                </td>
                <td class="space-x-2">
                  <button type="button" class="text-sm text-primary-600 hover:underline" @click="openReasonModal(r)">Sửa</button>
                  <button type="button" class="text-sm text-red-600 hover:underline" @click="deleteReason(r.id)">Xóa</button>
                </td>
              </tr>
              <tr v-if="!correctionReasons.length">
                <td colspan="6" class="text-center py-6 text-slate-400">Chưa có lý do bù thẻ.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="can('attendance.manage')" class="border-t border-slate-200 pt-8">
          <div class="flex flex-wrap justify-between items-start gap-3 mb-4">
            <div>
              <h3 class="text-base font-semibold text-slate-900">Màu sắc & nhãn bảng công</h3>
              <p class="text-sm text-slate-500 mt-1">Admin tự định nghĩa màu ô công, giai đoạn TV/CT, chú thích — không cần sửa code.</p>
            </div>
            <button type="button" class="hcm-btn-secondary text-sm" @click="resetDisplayConfig">↻ Khôi phục mặc định</button>
          </div>

          <div v-for="section in displaySections" :key="section.key" class="mb-6 bg-slate-50 rounded-xl border border-slate-200/60 p-4">
            <h4 class="text-sm font-semibold text-slate-900 mb-3">{{ section.title }}</h4>
            <div class="space-y-3">
              <div
                v-for="row in section.rows"
                :key="row.code"
                class="grid grid-cols-1 md:grid-cols-[140px_1fr_auto] gap-3 items-center bg-white rounded-lg border border-slate-100 p-3"
              >
                <div>
                  <p class="text-sm font-medium text-slate-800">{{ row.label }}</p>
                  <p class="text-[11px] text-slate-400 font-mono">{{ row.code }}</p>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                  <div v-for="field in row.fields" :key="field.key">
                    <label v-if="field.type !== 'boolean'" class="text-[11px] text-slate-500 block mb-1">{{ field.label }}</label>
                    <input
                      v-if="field.type === 'color'"
                      v-model="displayForm[section.key][row.code][field.key]"
                      type="color"
                      class="h-9 w-full rounded border border-slate-200 cursor-pointer"
                      :disabled="displayForm[section.key][row.code][field.key] === 'transparent'"
                    />
                    <label v-else-if="field.type === 'boolean'" class="flex items-center gap-2 text-sm mt-1">
                      <input v-model="displayForm[section.key][row.code][field.key]" type="checkbox" class="rounded text-primary-600" />
                      {{ field.label }}
                    </label>
                    <input
                      v-else
                      v-model="displayForm[section.key][row.code][field.key]"
                      type="text"
                      class="hcm-input w-full text-sm"
                    />
                  </div>
                </div>
                <div
                  class="hidden md:flex h-10 min-w-[72px] items-center justify-center rounded border border-slate-200 text-xs font-semibold"
                  :style="previewStyle(section.key, row.code)"
                >
                  {{ previewText(section.key, row.code) }}
                </div>
              </div>
            </div>
          </div>

          <button type="button" class="hcm-btn-primary px-6" :disabled="savingDisplay" @click="saveDisplayConfig">
            {{ savingDisplay ? 'Đang lưu...' : 'Lưu màu bảng công' }}
          </button>
        </div>
      </div>

      <!-- TAB 5: CẤP BẬC NHÂN SỰ -->
      <div v-if="activeTab === 'job_levels'" class="space-y-6">
        <div class="flex flex-wrap justify-between items-start gap-3 border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-semibold text-slate-900">Thang cấp bậc O1 – O7</h3>
            <p class="text-sm text-slate-500 mt-1">
              O1–O4: Quản lý · O5–O6: Nhân viên · O7: Công nhân — mỗi cấp có band A, B, C, D.
            </p>
          </div>
          <div class="flex gap-2">
            <button type="button" class="hcm-btn-secondary text-sm" @click="seedJobLevels">
              ↻ Áp dụng thang O1–O7
            </button>
            <button type="button" class="hcm-btn-primary text-sm" @click="openJobLevelModal()">
              + Thêm cấp bậc
            </button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="hcm-table w-full">
            <thead>
              <tr>
                <th>Cấp</th>
                <th>Band</th>
                <th>Loại</th>
                <th>Mã</th>
                <th>Tên</th>
                <th>Dải lương</th>
                <th>TT</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="level in jobLevels" :key="level.id" class="hover:bg-slate-50">
                <td class="font-mono font-semibold text-primary-700">{{ level.grade || '—' }}</td>
                <td class="font-mono">{{ level.band || '—' }}</td>
                <td>
                  <UiBadge :variant="categoryVariant(level.category)">{{ categoryLabel(level.category) }}</UiBadge>
                </td>
                <td class="font-mono text-xs">{{ level.code }}</td>
                <td class="font-medium text-slate-700">{{ level.name }}</td>
                <td class="text-xs font-mono">
                  {{ formatMoney(level.basic_salary_range_min) }} – {{ formatMoney(level.basic_salary_range_max) }}
                </td>
                <td>
                  <UiBadge :variant="level.is_active ? 'success' : 'default'">
                    {{ level.is_active ? 'ON' : 'OFF' }}
                  </UiBadge>
                </td>
                <td class="space-x-3">
                  <button type="button" class="text-sm text-primary-600 hover:underline" @click="openJobLevelModal(level)">Sửa</button>
                  <button type="button" class="text-sm text-red-600 hover:underline" @click="deleteJobLevel(level.id)">Xóa</button>
                </td>
              </tr>
              <tr v-if="!jobLevels.length">
                <td colspan="8" class="text-center py-6 text-slate-400">Chưa thiết lập — bấm «Áp dụng thang O1–O7».</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 6: LỊCH NGHỈ LỄ CÔNG TY -->
      <div v-if="activeTab === 'holidays'" class="space-y-6">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-semibold text-slate-900">Lịch nghỉ lễ công ty</h3>
            <p class="text-sm text-slate-500">Khai báo khoảng nghỉ lễ (1 hoặc nhiều ngày liên tiếp). NLĐ nghỉ và hưởng 100% lương nếu bật «Có hưởng lương».</p>
          </div>
          <button type="button" class="hcm-btn-primary text-sm" @click="openHolidayModal()">
            + Đăng ký nghỉ lễ mới
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="hcm-table w-full">
            <thead>
              <tr>
                <th>Tên ngày lễ</th>
                <th>Khoảng nghỉ</th>
                <th>Số ngày</th>
                <th>Hình thức nghỉ</th>
                <th>Hành động</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="holiday in holidays" :key="holiday.id" class="hover:bg-slate-50">
                <td class="font-medium text-slate-700">{{ holiday.name }}</td>
                <td class="font-mono text-sm whitespace-nowrap">{{ holiday.date_range_label || formatHolidayRange(holiday) }}</td>
                <td class="text-center text-sm font-semibold text-slate-700">{{ holiday.day_count || 1 }}</td>
                <td>
                  <UiBadge :variant="holiday.is_paid ? 'success' : 'default'">
                    {{ holiday.is_paid ? 'Có hưởng lương' : 'Nghỉ không lương' }}
                  </UiBadge>
                </td>
                <td class="space-x-3">
                  <button type="button" class="text-sm text-primary-600 hover:underline" @click="openHolidayModal(holiday)">Sửa</button>
                  <button type="button" class="text-sm text-red-600 hover:underline" @click="deleteHoliday(holiday.id)">Xóa</button>
                </td>
              </tr>
              <tr v-if="!holidays.length">
                <td colspan="5" class="text-center py-6 text-slate-400">Chưa thêm ngày nghỉ lễ nào.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 7: PHÂN QUYỀN & VAI TRÒ -->
      <div v-if="activeTab === 'roles'" class="space-y-8">
        
        <!-- SECTION 1: Cấu hình vai trò & Quyền -->
        <div class="space-y-4">
          <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <div>
              <h3 class="text-base font-semibold text-slate-900">Danh sách Vai trò Hệ thống</h3>
              <p class="text-sm text-slate-500">Quản lý danh sách vai trò bảo mật của Spatie RBAC và cấu hình phân quyền hạn tương ứng.</p>
            </div>
            <button type="button" class="hcm-btn-primary text-sm" @click="openCreateRoleModal()">
              + Thêm vai trò mới
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="role in roles" :key="role.id" class="bg-slate-50 p-4 rounded-xl border border-slate-200 flex flex-col justify-between hover:shadow-sm transition-all">
              <div>
                <div class="flex justify-between items-start">
                  <span class="font-bold text-slate-800 text-base">{{ role.name }}</span>
                  <UiBadge variant="default">{{ role.guard_name }}</UiBadge>
                </div>
                <div class="mt-2 text-xs text-slate-500">
                  Tổng số quyền sở hữu: <span class="font-semibold text-primary-600">{{ role.permissions?.length || 0 }}</span> quyền
                </div>
              </div>
              <div class="mt-4 flex gap-3 border-t border-slate-200/60 pt-3">
                <button type="button" class="hcm-btn-primary text-xs flex-1 py-1" @click="openRolePermissions(role)">
                  Cấu hình Quyền
                </button>
                <button 
                  type="button" 
                  class="hcm-btn-secondary text-xs text-red-600 border-red-200 hover:bg-red-50 py-1" 
                  :disabled="['admin', 'employee', 'hr_manager', 'auditor', 'department_manager'].includes(role.name)"
                  @click="deleteRole(role.id)"
                >
                  Xóa
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION 2: Gán vai trò & công ty cho người dùng -->
        <div class="space-y-4">
          <div class="border-b border-slate-100 pb-3">
            <h3 class="text-base font-semibold text-slate-900">Phân quyền người dùng theo công ty</h3>
            <p class="text-sm text-slate-500">
              Chọn <strong>công ty được truy cập</strong> và <strong>vai trò</strong> áp dụng tại các công ty đó.
              Một người có thể quản lý nhiều công ty con với cùng hoặc khác vai trò.
            </p>
          </div>

          <div class="overflow-x-auto">
            <div class="mb-3">
              <UiSearchInput
                v-model="userSearch"
                placeholder="Tìm theo tên, email hoặc mã NV..."
                @search="loadUsers"
              />
            </div>
            <table class="hcm-table w-full">
              <thead>
                <tr>
                  <th>Họ và tên</th>
                  <th>Email</th>
                  <th>Công ty truy cập</th>
                  <th>Vai trò (theo CTTV)</th>
                  <th>Hành động</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="user in usersList" :key="user.id" class="hover:bg-slate-50">
                  <td class="font-medium text-slate-800">{{ user.name }}</td>
                  <td class="text-sm font-mono text-slate-500">{{ user.email }}</td>
                  <td>
                    <div class="flex flex-wrap gap-1 max-w-xs">
                      <UiBadge v-for="c in user.companies || []" :key="c.id" variant="default">{{ c.code }}</UiBadge>
                      <span v-if="!user.companies?.length" class="text-xs text-slate-400">Chưa cấp</span>
                    </div>
                  </td>
                  <td>
                    <div class="flex flex-wrap gap-1 max-w-sm">
                      <UiBadge v-for="(roles, cid) in (user.company_roles || {})" :key="cid" variant="success">
                        {{ companyCode(cid) }}: {{ roles.join(', ') }}
                      </UiBadge>
                      <span v-if="!Object.keys(user.company_roles || {}).length" class="text-xs text-slate-400">—</span>
                    </div>
                  </td>
                  <td>
                    <button type="button" class="text-sm text-primary-600 font-semibold hover:underline" @click="openUserAccess(user)">
                      Phân quyền
                    </button>
                  </td>
                </tr>
                <tr v-if="!usersList.length">
                  <td colspan="5" class="text-center py-6 text-slate-400">Chưa có tài khoản trong tenant.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- TAB: ĐỒNG BỘ EHR CŨ -->
      <div v-if="activeTab === 'sync_ehr'" class="space-y-6 max-w-3xl">
        <div class="border-b border-slate-100 pb-3">
          <h3 class="text-base font-semibold text-slate-900">Đồng bộ từ hệ thống EHR cũ</h3>
          <p class="text-sm text-slate-500 mt-1">
            Lấy dữ liệu nhân viên từ <code class="bg-slate-100 px-1 rounded text-xs">bptehr.bestpacific.com</code> và cập nhật vào hệ thống.
            Mỗi nhân viên được định tuyến về đúng công ty theo <strong>ký tự đầu của mã NV (EMPNO prefix)</strong>.
          </p>
        </div>

        <!-- Prefix mapping table -->
        <div class="space-y-2">
          <p class="text-sm font-medium text-slate-700">Cấu hình định tuyến theo prefix mã NV:</p>
          <table class="hcm-table w-full text-sm">
            <thead>
              <tr>
                <th class="w-24">Prefix</th>
                <th>Công ty nhận dữ liệu</th>
                <th>Mã CT</th>
                <th>Trạng thái</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in tenantCompanies" :key="c.id" class="hover:bg-slate-50">
                <td>
                  <span v-if="c.employee_code_prefix" class="inline-block font-mono font-bold text-primary-700 bg-primary-50 border border-primary-200 px-2 py-0.5 rounded">
                    {{ c.employee_code_prefix }}
                  </span>
                  <span v-else class="text-slate-400 text-xs italic">Chưa cấu hình</span>
                </td>
                <td class="font-medium">{{ c.name }}</td>
                <td class="font-mono text-xs text-slate-500">{{ c.code }}</td>
                <td>
                  <UiBadge :variant="c.employee_code_prefix ? 'success' : 'warning'">
                    {{ c.employee_code_prefix ? 'Sẵn sàng sync' : 'Cần cấu hình prefix' }}
                  </UiBadge>
                </td>
              </tr>
              <tr v-if="!tenantCompanies.length">
                <td colspan="4" class="text-center py-4 text-slate-400">Chưa có công ty nào.</td>
              </tr>
            </tbody>
          </table>
          <p class="text-xs text-slate-400">Để cấu hình prefix, vào <button type="button" class="text-primary-600 hover:underline font-medium" @click="activeTab = 'company'">Tab Thông tin công ty</button> → trường «Prefix mã nhân viên».</p>
        </div>

        <div class="rounded-lg border border-slate-200 p-4 bg-slate-50 space-y-2 text-sm">
          <p class="font-medium text-slate-700">Quy tắc đồng bộ:</p>
          <ul class="list-disc pl-4 space-y-1 text-slate-600">
            <li>Nhân viên khớp theo <strong>Mã NV (EMPNO)</strong> — tự động vào đúng công ty theo prefix</li>
            <li>Nếu đã tồn tại: chỉ điền các trường <strong>còn trống</strong>, không ghi đè dữ liệu đã nhập</li>
            <li>Nếu chưa có: tạo mới nhân viên vào đúng công ty</li>
            <li>Phòng ban / Chức danh chưa có sẽ được <strong>tạo tự động</strong> theo mã từ EHR</li>
            <li>STATUS=2 (nghỉ việc) → cập nhật trạng thái <strong>terminated</strong></li>
            <li>Tên song ngữ (VN + Hán tự) → chỉ giữ phần tiếng Việt</li>
            <li>Lịch tự động: <strong>2:00 sáng mỗi ngày</strong></li>
          </ul>
        </div>

        <div v-if="syncResult" class="rounded-lg border p-4 space-y-2 text-sm"
          :class="syncResult.errors?.length ? 'border-amber-300 bg-amber-50' : 'border-emerald-300 bg-emerald-50'">
          <p class="font-semibold text-slate-800">Kết quả đồng bộ lần cuối:</p>
          <div class="flex gap-6">
            <span class="text-emerald-700">✓ Tạo mới: <strong>{{ syncResult.created }}</strong></span>
            <span class="text-blue-700">↻ Cập nhật: <strong>{{ syncResult.updated }}</strong></span>
            <span class="text-slate-500">— Bỏ qua: <strong>{{ syncResult.skipped }}</strong></span>
          </div>
          <div v-if="syncResult.errors?.length" class="text-amber-800 pt-1">
            <p class="font-medium">Cảnh báo ({{ syncResult.errors.length }}):</p>
            <ul class="list-disc pl-4 max-h-32 overflow-y-auto space-y-0.5 text-xs">
              <li v-for="(e, i) in syncResult.errors" :key="i">{{ e }}</li>
            </ul>
          </div>
        </div>

        <button type="button" class="hcm-btn-primary" :disabled="syncing" @click="runSyncEhr">
          {{ syncing ? 'Đang đồng bộ...' : 'Đồng bộ ngay' }}
        </button>
      </div>

    </div>

    <!-- MODAL 1: THÊM/SỬA PHÒNG BAN -->
    <UiModal v-model="showDeptModal" :title="deptForm.id ? 'Cấu hình phòng ban/bộ phận' : 'Thêm phòng ban/bộ phận mới'">
      <form class="space-y-4" @submit.prevent="saveDepartment">
        <div v-if="!singleBranchMode">
          <label class="text-sm font-medium">Chi nhánh / địa điểm</label>
          <select v-model="deptForm.branch_id" class="hcm-input mt-1 w-full" required>
            <option :value="null" disabled>-- Chọn chi nhánh --</option>
            <option v-for="b in companyBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
          <p class="text-xs text-slate-500 mt-1">Chỉ hiển thị khi công ty có nhiều chi nhánh.</p>
        </div>
        <div v-else class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
          Thuộc công ty: <strong>{{ companyForm.name }}</strong>
          <span v-if="defaultBranchLabel"> · {{ defaultBranchLabel }}</span>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium">Mã phòng ban / bộ phận</label>
            <input v-model="deptForm.code" class="hcm-input mt-1 w-full font-mono text-sm" required placeholder="VD: HR, ACC" />
          </div>
          <div>
            <label class="text-sm font-medium">Tên phòng ban / bộ phận</label>
            <input v-model="deptForm.name" class="hcm-input mt-1 w-full" required placeholder="VD: Phòng Kế Toán" />
          </div>
        </div>
        <div>
          <label class="text-sm font-medium">Thuộc phòng ban cấp trên (Nếu là bộ phận trực thuộc)</label>
          <select v-model="deptForm.parent_department_id" class="hcm-input mt-1 w-full">
            <option :value="null">Không trực thuộc (Cấp cao nhất)</option>
            <option v-for="d in parentDepartments" :key="d.id" :value="d.id">
              {{ d.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium">Trưởng phòng / Quản lý bộ phận</label>
          <select v-model="deptForm.manager_id" class="hcm-input mt-1 w-full">
            <option :value="null">Chưa bổ nhiệm</option>
            <option v-for="emp in companyEmployees" :key="emp.id" :value="emp.id">{{ emp.full_name }} ({{ emp.employee_code }})</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <input v-model="deptForm.is_active" type="checkbox" id="dept_active" class="rounded text-primary-600" />
          <label for="dept_active" class="text-sm font-medium select-none cursor-pointer">Hoạt động</label>
        </div>
        <button type="submit" class="hcm-btn-primary w-full">Lưu thay đổi</button>
      </form>
    </UiModal>

    <!-- MODAL 2: THÊM/SỬA CA LÀM VIỆC -->
    <UiModal v-model="showShiftModal" :title="shiftForm.id ? 'Cấu hình ca làm việc' : 'Tạo ca làm việc mới'">
      <form class="space-y-4" @submit.prevent="saveShift">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium">Mã ca</label>
            <input v-model="shiftForm.code" class="hcm-input mt-1 w-full font-mono text-sm" required placeholder="VD: CA-DEM" :disabled="!!shiftForm.id" />
          </div>
          <div>
            <label class="text-sm font-medium">Tên ca làm việc</label>
            <input v-model="shiftForm.name" class="hcm-input mt-1 w-full" required placeholder="VD: Ca đêm sản xuất" />
          </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div>
            <label class="text-sm font-medium">Giờ bắt đầu</label>
            <input v-model="shiftForm.start_time" type="time" class="hcm-input mt-1 w-full font-mono" required />
          </div>
          <div>
            <label class="text-sm font-medium">Giờ kết thúc</label>
            <input v-model="shiftForm.end_time" type="time" class="hcm-input mt-1 w-full font-mono" required />
          </div>
          <div>
            <label class="text-sm font-medium">Nghỉ giữa ca (phút)</label>
            <input v-model.number="shiftForm.break_minutes" type="number" min="0" class="hcm-input mt-1 w-full" required />
          </div>
          <div>
            <label class="text-sm font-medium">Giờ làm chuẩn</label>
            <input v-model.number="shiftForm.standard_hours" type="number" min="1" max="12" step="0.5" class="hcm-input mt-1 w-full" />
          </div>
        </div>
        <div class="flex flex-wrap gap-4 text-sm">
          <label class="flex items-center gap-2">
            <input v-model="shiftForm.is_night_shift" type="checkbox" class="rounded text-primary-600" />
            Ca đêm (22h–6h)
          </label>
          <label class="flex items-center gap-2">
            <input v-model="shiftForm.crosses_midnight" type="checkbox" class="rounded text-primary-600" />
            Ca qua ngày (kết thúc sáng hôm sau)
          </label>
          <label class="flex items-center gap-2">
            <input v-model="shiftForm.is_active" type="checkbox" class="rounded text-primary-600" />
            Hoạt động
          </label>
        </div>
        <p v-if="shiftForm.is_night_shift" class="text-xs text-amber-700 bg-amber-50 p-2 rounded">
          Ca đêm: nghỉ giữa ca ≥45 phút · phụ cấp +30% giờ làm đêm (Điều 106) · OT đêm +20% (cấu hình payroll).
        </p>
        <button type="button" class="text-xs text-primary-600 hover:underline" @click="applyNightShiftPreset">Dùng mẫu ca đêm CA-DEM</button>
        <button type="submit" class="hcm-btn-primary w-full">Lưu thay đổi</button>
      </form>
    </UiModal>

    <!-- MODAL 3: THÊM/SỬA CẤP BẬC -->
    <UiModal v-model="showLevelModal" :title="levelForm.id ? 'Cấu hình cấp bậc' : 'Thêm cấp bậc mới'">
      <form class="space-y-4" @submit.prevent="saveJobLevel">
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="text-sm font-medium">Cấp (O1–O7)</label>
            <select v-model="levelForm.grade" class="hcm-input mt-1 w-full" @change="onGradeChange">
              <option value="">— Chọn —</option>
              <option v-for="g in gradeOptions" :key="g.grade" :value="g.grade">{{ g.grade }} — {{ g.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium">Band</label>
            <select v-model="levelForm.band" class="hcm-input mt-1 w-full" @change="syncLevelCode">
              <option v-for="b in bandOptions" :key="b" :value="b">{{ b }}</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium">Loại</label>
            <input :value="categoryLabel(levelForm.category)" class="hcm-input mt-1 w-full bg-slate-50" readonly />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium">Mã cấp bậc</label>
            <input v-model="levelForm.code" class="hcm-input mt-1 w-full font-mono text-sm" required placeholder="O5-B" />
          </div>
          <div>
            <label class="text-sm font-medium">Tên hiển thị</label>
            <input v-model="levelForm.name" class="hcm-input mt-1 w-full" required />
          </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="text-sm font-medium">Thứ tự (rank)</label>
            <input v-model.number="levelForm.rank" type="number" min="1" class="hcm-input mt-1 w-full" required />
          </div>
          <div>
            <label class="text-sm font-medium">Lương tối thiểu</label>
            <input v-model.number="levelForm.basic_salary_range_min" type="number" min="0" class="hcm-input mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm font-medium">Lương tối đa</label>
            <input v-model.number="levelForm.basic_salary_range_max" type="number" min="0" class="hcm-input mt-1 w-full" />
          </div>
        </div>
        <div class="flex items-center gap-2">
          <input v-model="levelForm.is_active" type="checkbox" id="level_active" class="rounded text-primary-600" />
          <label for="level_active" class="text-sm font-medium select-none cursor-pointer">Hoạt động</label>
        </div>
        <button type="submit" class="hcm-btn-primary w-full">Lưu thay đổi</button>
      </form>
    </UiModal>

    <!-- MODAL 4: THÊM/SỬA LỊCH LỄ -->
    <UiModal v-model="showHolidayModal" :title="holidayForm.id ? 'Sửa khoảng nghỉ lễ' : 'Đăng ký nghỉ lễ mới'">
      <form class="space-y-4" @submit.prevent="saveHoliday">
        <div>
          <label class="text-sm font-medium">Tên ngày lễ / đợt nghỉ</label>
          <input v-model="holidayForm.name" class="hcm-input mt-1 w-full" required placeholder="VD: Tết Nguyên Đán 2026" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium">Từ ngày</label>
            <input v-model="holidayForm.holiday_date" type="date" class="hcm-input mt-1 w-full" required @change="syncHolidayEndDate" />
          </div>
          <div>
            <label class="text-sm font-medium">Đến ngày</label>
            <input v-model="holidayForm.end_date" type="date" class="hcm-input mt-1 w-full" required :min="holidayForm.holiday_date" />
          </div>
        </div>
        <p v-if="holidayDayCount > 1" class="text-xs text-blue-700 bg-blue-50 rounded-lg px-3 py-2">
          Khoảng nghỉ <b>{{ holidayDayCount }}</b> ngày liên tiếp (tính vào công chuẩn tháng).
        </p>
        <div class="flex items-center gap-2">
          <input v-model="holidayForm.is_paid" type="checkbox" id="holiday_paid" class="rounded text-primary-600" />
          <label for="holiday_paid" class="text-sm font-medium select-none cursor-pointer">Nghỉ lễ có hưởng lương</label>
        </div>
        <button type="submit" class="hcm-btn-primary w-full">Lưu lịch nghỉ lễ</button>
      </form>
    </UiModal>

    <!-- MODAL 5: THÊM VAI TRÒ MỚI -->
    <UiModal v-model="showCreateRoleModal" title="Tạo vai trò bảo mật mới">
      <form class="space-y-4" @submit.prevent="saveNewRole">
        <div>
          <label class="text-sm font-medium">Tên vai trò mới</label>
          <input v-model="newRoleName" class="hcm-input mt-1 w-full" required placeholder="VD: hr_assistant, director" />
          <p class="text-xs text-slate-500 mt-1">Chỉ sử dụng chữ cái thường, gạch dưới và không có dấu cách.</p>
        </div>
        <button type="submit" class="hcm-btn-primary w-full">Tạo vai trò</button>
      </form>
    </UiModal>

    <!-- MODAL 6: CẤU HÌNH QUYỀN HẠN CHO VAI TRÒ -->
    <UiModal v-model="showRolePermissionsModal" :title="`Cấu hình Quyền hạn vai trò: ${selectedRole?.name}`" size="lg">
      <form class="space-y-6" @submit.prevent="saveRolePermissions">
        <div class="max-h-[60vh] overflow-y-auto pr-2 space-y-5">
          <div v-for="(permsList, catName) in permissionCategories" :key="catName" class="bg-slate-50 p-4 rounded-xl border border-slate-200/60">
            <h4 class="text-sm font-bold text-slate-900 border-b border-slate-200 pb-1.5 mb-3 flex items-center justify-between">
              <span>{{ catName }}</span>
              <button type="button" class="text-xs text-primary-600 font-semibold hover:underline" @click="toggleCategoryPermissions(permsList)">
                Chọn tất cả / Bỏ chọn
              </button>
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
              <label v-for="pName in permsList" :key="pName" class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer select-none">
                <input 
                  type="checkbox" 
                  :value="pName" 
                  v-model="selectedPermissions" 
                  class="rounded text-primary-600 focus:ring-primary-500 border-slate-300"
                />
                <span class="font-mono text-xs">{{ pName }}</span>
              </label>
            </div>
          </div>
        </div>
        <button type="submit" class="hcm-btn-primary w-full">Đồng bộ quyền hạn cho vai trò</button>
      </form>
    </UiModal>

    <!-- MODAL: Lý do bù thẻ -->
    <UiModal v-model="showLeaveTypeModal" :title="leaveTypeForm.id ? 'Sửa loại nghỉ' : 'Thêm loại nghỉ'">
      <form class="space-y-4" @submit.prevent="saveLeaveType">
        <div>
          <label class="text-sm font-medium">Mã loại nghỉ</label>
          <input v-model="leaveTypeForm.code" class="hcm-input w-full mt-1 uppercase" required maxlength="32" />
        </div>
        <div>
          <label class="text-sm font-medium">Tên hiển thị</label>
          <input v-model="leaveTypeForm.name" class="hcm-input w-full mt-1" required />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium">Ký hiệu bảng công</label>
            <input v-model="leaveTypeForm.cell_symbol" class="hcm-input w-full mt-1 uppercase" maxlength="8" placeholder="P, KL, Ô…" />
          </div>
          <div>
            <label class="text-sm font-medium">Thứ tự</label>
            <input v-model.number="leaveTypeForm.sort_order" type="number" min="0" class="hcm-input w-full mt-1" />
          </div>
        </div>
        <div>
          <label class="text-sm font-medium">Căn cứ pháp lý / ghi chú</label>
          <input v-model="leaveTypeForm.legal_reference" class="hcm-input w-full mt-1" maxlength="255" />
        </div>
        <div>
          <label class="text-sm font-medium">Nhóm ảnh hưởng lương</label>
          <select v-model="leaveTypeForm.payroll_category" class="hcm-input w-full mt-1" @change="onLeaveCategoryChange">
            <option v-for="(meta, key) in leavePayrollCategories" :key="key" :value="key">{{ meta.label }}</option>
          </select>
          <p v-if="leavePayrollCategories[leaveTypeForm.payroll_category]?.description" class="text-xs text-slate-500 mt-1">
            {{ leavePayrollCategories[leaveTypeForm.payroll_category].description }}
          </p>
        </div>
        <div>
          <label class="text-sm font-medium">Mô tả / quy chế nội bộ</label>
          <textarea v-model="leaveTypeForm.description" class="hcm-input w-full mt-1" rows="2" maxlength="1000" />
        </div>
        <div>
          <label class="text-sm font-medium">Cách tính ngày nghỉ</label>
          <select v-model="leaveTypeForm.day_count_mode" class="hcm-input w-full mt-1">
            <option value="workday">Theo ngày làm việc (trừ CN, lễ)</option>
            <option value="calendar">Theo ngày dương lịch (ốm, thai sản…)</option>
          </select>
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="leaveTypeForm.is_paid" type="checkbox" class="rounded text-primary-600" disabled />
          Hưởng lương từ công ty (tự động theo nhóm lương)
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input :checked="leaveTypeForm.affects_diligence === true" type="checkbox" class="rounded text-primary-600" @change="leaveTypeForm.affects_diligence = $event.target.checked ? true : null" />
          Ảnh hưởng thưởng chuyên cần (bỏ trống = theo mặc định nhóm)
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="leaveTypeForm.requires_approval" type="checkbox" class="rounded text-primary-600" />
          Bắt buộc duyệt trước khi nghỉ
        </label>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="hcm-btn-secondary" @click="showLeaveTypeModal = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary">Lưu</button>
        </div>
      </form>
    </UiModal>

    <UiModal v-model="showBonusTypeModal" :title="bonusTypeForm.id ? 'Sửa loại thưởng' : 'Thêm loại thưởng'">
      <form class="space-y-4" @submit.prevent="saveBonusType">
        <div>
          <label class="text-sm font-medium">Mã thưởng</label>
          <input v-model="bonusTypeForm.code" class="hcm-input w-full mt-1 uppercase" required maxlength="32" placeholder="T_KPI" />
        </div>
        <div>
          <label class="text-sm font-medium">Tên hiển thị</label>
          <input v-model="bonusTypeForm.name" class="hcm-input w-full mt-1" required />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium">Nhóm thưởng</label>
            <select v-model="bonusTypeForm.category" class="hcm-input w-full mt-1">
              <option v-for="(label, key) in bonusCategories" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium">Cách tính mặc định</label>
            <select v-model="bonusTypeForm.calculation_mode" class="hcm-input w-full mt-1">
              <option v-for="(label, key) in bonusCalcModes" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium">Số tiền mặc định (VND)</label>
            <input v-model.number="bonusTypeForm.default_amount" type="number" min="0" class="hcm-input w-full mt-1" />
          </div>
          <div>
            <label class="text-sm font-medium">Tỷ lệ % LCB (nếu có)</label>
            <input v-model.number="bonusTypeForm.default_rate" type="number" min="0" step="0.01" class="hcm-input w-full mt-1" />
          </div>
        </div>
        <div>
          <label class="text-sm font-medium">Key breakdown lương</label>
          <input v-model="bonusTypeForm.breakdown_key" class="hcm-input w-full mt-1" maxlength="64" placeholder="bonus_kpi" />
        </div>
        <div>
          <label class="text-sm font-medium">Căn cứ pháp lý / quy chế</label>
          <input v-model="bonusTypeForm.legal_reference" class="hcm-input w-full mt-1" maxlength="255" />
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="bonusTypeForm.taxable" type="checkbox" class="rounded text-primary-600" />
          Chịu thuế TNCN
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="bonusTypeForm.counts_in_gross" type="checkbox" class="rounded text-primary-600" />
          Cộng vào gross lương
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="bonusTypeForm.is_active" type="checkbox" class="rounded text-primary-600" />
          Đang sử dụng
        </label>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="hcm-btn-secondary" @click="showBonusTypeModal = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary">Lưu</button>
        </div>
      </form>
    </UiModal>

    <UiModal v-model="showReasonModal" :title="reasonForm.id ? 'Sửa lý do bù thẻ' : 'Thêm lý do bù thẻ'">
      <form class="space-y-4" @submit.prevent="saveReason">
        <div>
          <label class="text-sm font-medium">Mã</label>
          <input v-model="reasonForm.code" class="hcm-input w-full mt-1 uppercase" required maxlength="32" />
        </div>
        <div>
          <label class="text-sm font-medium">Tên hiển thị</label>
          <input v-model="reasonForm.name" class="hcm-input w-full mt-1" required />
        </div>
        <div>
          <label class="text-sm font-medium">Thứ tự</label>
          <input v-model.number="reasonForm.sort_order" type="number" min="0" class="hcm-input w-full mt-1" />
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="reasonForm.counts_as_forgot_punch" type="checkbox" class="rounded text-primary-600" />
          Tính vào «quên chấm công» (vượt hạn mức → mất thưởng chuyên cần)
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="reasonForm.is_active" type="checkbox" class="rounded text-primary-600" />
          Đang sử dụng
        </label>
        <button type="submit" class="hcm-btn-primary w-full">Lưu</button>
      </form>
    </UiModal>

    <!-- MODAL 7: PHÂN QUYỀN USER THEO CÔNG TY -->
    <UiModal v-model="showUserAccessModal" :title="`Phân quyền: ${selectedUser?.name}`">
      <form class="space-y-4" @submit.prevent="saveUserAccess">
        <div>
          <label class="text-sm font-semibold text-slate-800">Công ty được truy cập</label>
          <p class="text-xs text-slate-500 mb-2">Chọn một hoặc nhiều công ty con trong tập đoàn.</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 bg-slate-50 p-3 rounded-xl border border-slate-200 max-h-40 overflow-y-auto">
            <label v-for="c in tenantCompanies" :key="c.id" class="flex items-center gap-2 text-sm cursor-pointer">
              <input v-model="selectedCompanyIds" type="checkbox" :value="c.id" class="rounded text-primary-600" />
              <span>{{ c.name }} <span class="text-slate-400 font-mono text-xs">({{ c.code }})</span></span>
            </label>
          </div>
        </div>
        <div>
          <label class="text-sm font-semibold text-slate-800">Vai trò tại các công ty đã chọn</label>
          <p class="text-xs text-slate-500 mb-2">Vai trò áp dụng đồng nhất cho mọi công ty được tick ở trên (vd. hr_manager).</p>
          <div class="grid grid-cols-2 gap-2 bg-slate-50 p-3 rounded-xl border border-slate-200">
            <label v-for="role in assignableRoles" :key="role" class="flex items-center gap-2 text-sm cursor-pointer">
              <input v-model="selectedUserRoles" type="checkbox" :value="role" class="rounded text-primary-600" />
              <span>{{ role }}</span>
            </label>
          </div>
        </div>
        <div>
          <label class="text-sm font-medium">Công ty mặc định khi đăng nhập</label>
          <select v-model="defaultCompanyId" class="hcm-input mt-1 w-full">
            <option :value="null">— Tự chọn —</option>
            <option v-for="id in selectedCompanyIds" :key="id" :value="Number(id)">
              {{ tenantCompanies.find(c => c.id === Number(id))?.name }}
            </option>
          </select>
        </div>
        <button type="submit" class="hcm-btn-primary w-full" :disabled="!selectedCompanyIds.length || !selectedUserRoles.length">
          Lưu phân quyền
        </button>
      </form>
    </UiModal>

  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiModal from '../../components/ui/UiModal.vue';
import UiSearchInput from '../../components/ui/UiSearchInput.vue';
import { extractItems } from '../../composables/usePagination';
import { useToast } from '../../composables/useToast';
import { useAuthStore } from '../../stores/auth';
import { usePermission } from '../../composables/usePermission';

const toast = useToast();
const auth = useAuthStore();
const { can } = usePermission();
const activeTab = ref('company');

// Form States
const companyForm = ref({});
const savingCompany = ref(false);

const policyForm = ref({
  insurance_rate_employer: '21.5',
  insurance_rate_employee: '10.5',
  annual_leave_standard: '12',
  standard_working_days: '26',
  ot_coeff_weekday: '1.5',
  ot_coeff_weekend: '2.0',
  ot_coeff_holiday: '3.0'
});
const savingPolicies = ref(false);

const diligenceForm = ref({
  attendance_mobile_punch_enabled: '1',
  attendance_geofence_strict: '1',
  diligence_bonus_enabled: '1',
  diligence_bonus_amount: '500000',
  diligence_bonus_amount_probation: '',
  diligence_bonus_amount_official: '',
  diligence_phase_mode: 'full_month',
  diligence_min_attendance_rate: '98',
  diligence_max_late_count: '1',
  diligence_max_absent_days: '0',
  diligence_max_forgot_punch: '2',
});
const savingDiligence = ref(false);
const correctionReasons = ref([]);
const leaveTypes = ref([]);
const leavePayrollCategories = ref({});
const showLeaveTypeModal = ref(false);
const leaveTypeForm = ref({});
const bonusTypes = ref([]);
const bonusCategories = ref({});
const bonusCalcModes = ref({});
const showBonusTypeModal = ref(false);
const bonusTypeForm = ref({});
const showReasonModal = ref(false);
const reasonForm = ref({});
const displayForm = ref({});
const savingDisplay = ref(false);

const DISPLAY_SECTION_TITLES = {
  cell_statuses: 'Trạng thái ô công',
  employment_phases: 'Giai đoạn làm việc (TV / CT)',
  day_headers: 'Header cột ngày',
  totals_columns: 'Cột tổng hợp',
  legend_footer: 'Chú thích chân bảng',
};

const DISPLAY_FIELD_LABELS = {
  label: 'Nhãn',
  bg_color: 'Màu nền',
  text_color: 'Màu chữ',
  bold: 'In đậm',
  short_label: 'Viết tắt',
  legend_color_name: 'Tên màu (legend)',
  title_prefix: 'Tiền tố tooltip',
  footer_text: 'Chú thích chân bảng',
  late_bg_color: 'Nền đi trễ',
  late_text_color: 'Chữ đi trễ',
  late_border_color: 'Viền đi trễ',
  badge_variant: 'Kiểu badge',
  bold_label: 'Nhãn đậm',
  text: 'Mô tả',
};

const displaySections = computed(() => {
  const cfg = displayForm.value || {};
  return Object.entries(cfg).map(([key, items]) => ({
    key,
    title: DISPLAY_SECTION_TITLES[key] || key,
    rows: Object.entries(items || {}).map(([code, fields]) => ({
      code,
      label: fields.label || fields.bold_label || fields.short_label || code,
      fields: Object.entries(fields || {}).map(([fieldKey]) => ({
        key: fieldKey,
        label: DISPLAY_FIELD_LABELS[fieldKey] || fieldKey,
        type: fieldKey === 'bold'
          ? 'boolean'
          : ((fieldKey.endsWith('_color') || fieldKey === 'bg_color' || fieldKey === 'text_color')
            && displayForm.value?.[key]?.[code]?.[fieldKey] !== 'transparent'
            ? 'color'
            : 'text'),
      })),
    })),
  }));
});

function cloneDisplayConfig(config) {
  return JSON.parse(JSON.stringify(config || {}));
}

function previewStyle(sectionKey, code) {
  const item = displayForm.value?.[sectionKey]?.[code];
  if (!item) return {};
  return {
    backgroundColor: item.bg_color && item.bg_color !== 'transparent' ? item.bg_color : undefined,
    color: item.text_color || undefined,
    fontWeight: item.bold ? '600' : undefined,
  };
}

function previewText(sectionKey, code) {
  const item = displayForm.value?.[sectionKey]?.[code];
  return item?.short_label || item?.bold_label || item?.label?.slice(0, 3) || 'Aa';
}

async function saveDisplayConfig() {
  savingDisplay.value = true;
  try {
    const { data } = await api.put('/attendance-display-config', { config: displayForm.value });
    displayForm.value = cloneDisplayConfig(data.data);
    toast.show('Đã lưu màu bảng công');
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không lưu được cấu hình màu', 'error');
  } finally {
    savingDisplay.value = false;
  }
}

async function resetDisplayConfig() {
  savingDisplay.value = true;
  try {
    await api.put('/attendance-display-config', { config: {} });
    const { data } = await api.get('/attendance-display-config');
    displayForm.value = cloneDisplayConfig(data.data);
    toast.show('Đã khôi phục màu mặc định');
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không khôi phục được', 'error');
  } finally {
    savingDisplay.value = false;
  }
}

function dayCountModeLabel(mode) {
  return mode === 'calendar' ? 'Dương lịch' : 'Ngày công';
}

function leaveSymbolStyle(lt) {
  const paid = displayForm.value?.cell_statuses?.paid_leave;
  const unpaid = displayForm.value?.cell_statuses?.unpaid_leave;
  const category = lt.payroll_category || (lt.is_paid ? 'company_paid' : 'company_unpaid');
  const palette = category === 'company_paid' ? paid : unpaid;
  if (!palette) return {};
  return {
    backgroundColor: palette.bg_color && palette.bg_color !== 'transparent' ? palette.bg_color : undefined,
    color: palette.text_color || undefined,
    fontWeight: palette.bold ? '700' : '600',
  };
}

function payrollCategoryLabel(category, isPaid) {
  const meta = leavePayrollCategories.value[category];
  if (meta?.label) return meta.label;
  return isPaid ? 'Có lương công ty' : 'Không lương';
}

function payrollCategoryVariant(category) {
  if (category === 'company_paid') return 'success';
  if (category === 'bhxh_benefit') return 'default';
  return 'warning';
}

function onLeaveCategoryChange() {
  const meta = leavePayrollCategories.value[leaveTypeForm.value.payroll_category];
  if (meta && typeof meta.is_paid === 'boolean') {
    leaveTypeForm.value.is_paid = meta.is_paid;
  }
}

function bonusCategoryLabel(key) {
  return bonusCategories.value[key] || key;
}

function bonusCalcModeLabel(key) {
  return bonusCalcModes.value[key] || key;
}

function openBonusTypeModal(row = null) {
  bonusTypeForm.value = row
    ? { ...row }
    : {
        code: '',
        name: '',
        category: 'adhoc',
        calculation_mode: 'manual',
        breakdown_key: '',
        taxable: true,
        counts_in_gross: true,
        is_active: true,
        sort_order: bonusTypes.value.length + 1,
        default_amount: null,
        default_rate: null,
        legal_reference: '',
      };
  showBonusTypeModal.value = true;
}

async function saveBonusType() {
  try {
    const payload = {
      ...bonusTypeForm.value,
      code: String(bonusTypeForm.value.code || '').toUpperCase(),
    };
    if (payload.id) {
      await api.put(`/payroll-bonus-types/${payload.id}`, payload);
      toast.show('Đã cập nhật loại thưởng');
    } else {
      await api.post('/payroll-bonus-types', payload);
      toast.show('Đã thêm loại thưởng');
    }
    showBonusTypeModal.value = false;
    const { data } = await api.get('/payroll-bonus-types');
    bonusTypes.value = data.data || [];
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không lưu được loại thưởng', 'error');
  }
}

async function deleteBonusType(id) {
  if (!confirm('Xóa loại thưởng này?')) return;
  try {
    await api.delete(`/payroll-bonus-types/${id}`);
    toast.show('Đã xóa');
    const { data } = await api.get('/payroll-bonus-types');
    bonusTypes.value = data.data || [];
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không xóa được', 'error');
  }
}

async function seedBonusTypes() {
  try {
    const { data } = await api.post('/payroll-bonus-types/seed-standard');
    bonusTypes.value = data.data || [];
    toast.show('Đã áp dụng danh mục thưởng chuẩn VN');
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không áp dụng được danh mục', 'error');
  }
}

function openLeaveTypeModal(leaveType = null) {
  leaveTypeForm.value = leaveType
    ? { ...leaveType }
    : {
        code: '',
        name: '',
        cell_symbol: '',
        payroll_category: 'company_paid',
        is_paid: true,
        affects_diligence: null,
        description: '',
        day_count_mode: 'workday',
        requires_approval: true,
        legal_reference: '',
        sort_order: leaveTypes.value.length + 1,
      };
  showLeaveTypeModal.value = true;
}

async function saveLeaveType() {
  try {
    const payload = {
      ...leaveTypeForm.value,
      code: String(leaveTypeForm.value.code || '').toUpperCase(),
      cell_symbol: String(leaveTypeForm.value.cell_symbol || '').toUpperCase(),
    };
    if (payload.id) {
      await api.put(`/leave-types/${payload.id}`, payload);
      toast.show('Đã cập nhật loại nghỉ');
    } else {
      await api.post('/leave-types', payload);
      toast.show('Đã thêm loại nghỉ');
    }
    showLeaveTypeModal.value = false;
    const { data } = await api.get('/leave-types');
    leaveTypes.value = data.data || [];
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không lưu được loại nghỉ', 'error');
  }
}

async function deleteLeaveType(id) {
  if (!confirm('Xóa loại nghỉ này?')) return;
  try {
    await api.delete(`/leave-types/${id}`);
    toast.show('Đã xóa');
    const { data } = await api.get('/leave-types');
    leaveTypes.value = data.data || [];
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không xóa được', 'error');
  }
}

async function seedLeaveTypes() {
  if (!confirm('Áp dụng lại danh mục nghỉ chuẩn VN? Các loại trùng mã sẽ được cập nhật.')) return;
  try {
    const { data } = await api.post('/leave-types/seed-standard');
    leaveTypes.value = data.data || [];
    toast.show('Đã đồng bộ danh mục nghỉ chuẩn');
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không đồng bộ được', 'error');
  }
}

// Masters Refs
const departments = ref([]);
const branches = ref([]);
const employees = ref([]);
const shifts = ref([]);
const jobLevels = ref([]);
const jobLevelCatalog = ref({ grades: [], bands: ['A', 'B', 'C', 'D'], categories: {} });
const holidays = ref([]);
const showHolidayModal = ref(false);
const holidayForm = ref({});

// RBAC State Refs
const roles = ref([]);
const permissions = ref([]);
const usersList = ref([]);
const userSearch = ref('');
const tenantCompanies = ref([]);
const assignableRoles = ref([]);

// External EHR sync
const syncing = ref(false);
const syncResult = ref(null);

async function runSyncEhr() {
  syncing.value = true;
  syncResult.value = null;
  try {
    const { data } = await api.post('/admin/sync-external-hr');
    syncResult.value = data.data;
    toast.show(`Đồng bộ xong: +${data.data.created} mới, ${data.data.updated} cập nhật`);
  } catch (e) {
    toast.show(e.response?.data?.message || 'Đồng bộ thất bại', 'error');
  } finally {
    syncing.value = false;
  }
}

// Modals Trigger
const showDeptModal = ref(false);
const deptForm = ref({});

const showShiftModal = ref(false);
const shiftForm = ref({});

const showLevelModal = ref(false);
const levelForm = ref({});

// Spatie Modals Trigger
const showRolePermissionsModal = ref(false);
const selectedRole = ref(null);
const selectedPermissions = ref([]);

const showUserAccessModal = ref(false);
const selectedUser = ref(null);
const selectedCompanyIds = ref([]);
const selectedUserRoles = ref([]);
const defaultCompanyId = ref(null);

const showCreateRoleModal = ref(false);
const newRoleName = ref('');

const visibleTabs = computed(() => {
  const tabs = [
    { id: 'company', label: 'Thông tin công ty & BHXH' },
    { id: 'departments', label: 'Cơ cấu phòng ban & Bộ phận' },
    { id: 'shifts', label: 'Ca làm việc' },
    { id: 'policies', label: 'Chế độ & Luật lao động' },
    { id: 'attendance', label: 'Chấm công & Chuyên cần' },
    { id: 'job_levels', label: 'Cấp bậc nhân sự' },
    { id: 'holidays', label: 'Lịch nghỉ lễ công ty' },
  ];
  if (can('users.manage')) {
    tabs.push({ id: 'roles', label: 'Phân quyền & Vai trò' });
  }
  if (can('users.manage')) {
    tabs.push({ id: 'sync_ehr', label: 'Đồng bộ EHR cũ' });
  }
  return tabs;
});

const gradeOptions = computed(() => jobLevelCatalog.value.grades || []);
const bandOptions = computed(() => jobLevelCatalog.value.bands || ['A', 'B', 'C', 'D']);

function categoryLabel(cat) {
  return jobLevelCatalog.value.categories?.[cat] || cat || '—';
}

function categoryVariant(cat) {
  if (cat === 'manager') return 'info';
  if (cat === 'worker') return 'warning';
  return 'default';
}

// Spatie Permission Categories
const permissionCategories = {
  'Hồ sơ Nhân viên': ['employees.view', 'employees.create', 'employees.edit', 'employees.delete'],
  'Hợp đồng lao động': ['employment_contracts.view', 'employment_contracts.create', 'employment_contracts.edit'],
  'Bảo hiểm xã hội': ['bhxh.export', 'bhxh.manage'],
  'Chấm công & Nghỉ phép': ['attendance.view', 'attendance.manage', 'leave.view', 'leave.manage', 'leave.approve'],
  'Tính toán Lương': ['payroll.view', 'payroll.manage', 'payroll.approve'],
  'Tuyển dụng & Onboarding': ['candidates.view', 'candidates.manage'],
  'Hộp thư phê duyệt': ['approvals.view', 'approvals.act'],
  'Đào tạo & Năng lực': ['training.view', 'training.manage', 'competency.view', 'competency.manage', 'performance.view', 'performance.manage'],
  'Cấu hình Công ty': ['companies.view', 'companies.create', 'companies.edit', 'companies.delete', 'branches.view', 'branches.create', 'branches.edit', 'branches.delete', 'departments.view', 'departments.create', 'departments.edit', 'departments.delete', 'positions.view', 'positions.create', 'positions.edit', 'positions.delete', 'audit_logs.view']
};

const companyBranches = computed(() => branches.value);

const singleBranchMode = computed(() => companyBranches.value.length <= 1);

const defaultBranchLabel = computed(() => companyBranches.value[0]?.name || 'Trụ sở chính');

const companyEmployees = computed(() => employees.value);

const parentDepartments = computed(() => {
  const branchId = deptForm.value.branch_id;
  return departments.value.filter((d) => {
    if (d.parent_department_id) return false;
    if (deptForm.value.id && d.id === deptForm.value.id) return false;
    if (branchId && d.branch_id !== branchId) return false;

    return true;
  });
});

watch(
  () => deptForm.value.branch_id,
  (branchId) => {
    const parentId = deptForm.value.parent_department_id;
    if (!parentId || !branchId) return;
    const parent = departments.value.find((d) => d.id === parentId);
    if (parent && parent.branch_id !== branchId) {
      deptForm.value.parent_department_id = null;
    }
  },
);

async function ensureCompanyDefaultBranch() {
  try {
    await api.post('/branches/ensure-default');
    const { data } = await api.get('/branches');
    branches.value = data.data || [];
  } catch {
    // ignore — sẽ báo lỗi khi lưu phòng ban nếu thật sự không có chi nhánh
  }
}

// Load System Settings
async function loadUsers(searchValue = userSearch.value) {
  if (!can('users.manage')) return;
  userSearch.value = searchValue;
  const params = { per_page: 200 };
  if (userSearch.value.trim()) params.search = userSearch.value.trim();
  const { data } = await api.get('/users', { params });
  usersList.value = extractItems(data);
}

async function loadAllData() {
  const currentCompanyId = auth.companyId;
  if (!currentCompanyId) return;

  await ensureCompanyDefaultBranch();

  const [comp, depts, brs, emps, ws, jl, hols, policies, reasons, leaveTypeList, leaveTypeMeta, bonusTypeList, bonusTypeMeta, displayCfg, rList, pList, uList, compList, roleList] = await Promise.all([
    api.get(`/companies/${currentCompanyId}`),
    api.get('/departments'),
    api.get('/branches'),
    api.get('/employees'),
    api.get('/work-shifts'),
    api.get('/job-levels'),
    api.get('/company-holidays'),
    api.get('/company-settings'),
    api.get('/attendance-correction-reasons'),
    can('leave.manage') ? api.get('/leave-types') : Promise.resolve({ data: { data: [] } }),
    can('leave.manage') ? api.get('/leave-types/meta') : Promise.resolve({ data: { data: {} } }),
    can('payroll.manage') ? api.get('/payroll-bonus-types') : Promise.resolve({ data: { data: [] } }),
    can('payroll.manage') ? api.get('/payroll-bonus-types/meta') : Promise.resolve({ data: { data: {} } }),
    (can('attendance.manage') || can('leave.manage')) ? api.get('/attendance-display-config') : Promise.resolve({ data: { data: {} } }),
    api.get('/roles'),
    api.get('/permissions'),
    can('users.manage') ? api.get('/users', { params: userSearch.value.trim() ? { search: userSearch.value.trim(), per_page: 200 } : { per_page: 200 } }) : Promise.resolve({ data: { data: [] } }),
    api.get('/companies'),
    can('users.manage') ? api.get('/users/assignable-roles') : Promise.resolve({ data: { data: [] } }),
  ]);

  companyForm.value = comp.data.data;
  departments.value = depts.data.data;
  branches.value = brs.data.data;
  employees.value = extractItems(emps.data);
  shifts.value = ws.data.data;
  const jlPayload = jl.data.data;
  jobLevels.value = Array.isArray(jlPayload) ? jlPayload : (jlPayload?.levels || []);
  jobLevelCatalog.value = jlPayload?.catalog || jobLevelCatalog.value;
  holidays.value = hols.data.data;
  
  roles.value = rList.data.data;
  permissions.value = pList.data.data;
  usersList.value = extractItems(uList.data);
  tenantCompanies.value = compList.data.data || [];
  assignableRoles.value = roleList.data.data || [];

  const pol = policies.data.data;
  if (pol && Object.keys(pol).length > 0) {
    policyForm.value = { ...policyForm.value, ...pol };
    diligenceForm.value = {
      ...diligenceForm.value,
      attendance_mobile_punch_enabled: pol.attendance_mobile_punch_enabled ?? '1',
      attendance_geofence_strict: pol.attendance_geofence_strict ?? '1',
      diligence_bonus_enabled: pol.diligence_bonus_enabled ?? '1',
      diligence_bonus_amount: pol.diligence_bonus_amount ?? '500000',
      diligence_bonus_amount_probation: pol.diligence_bonus_amount_probation ?? '',
      diligence_bonus_amount_official: pol.diligence_bonus_amount_official ?? '',
      diligence_phase_mode: pol.diligence_phase_mode ?? 'full_month',
      diligence_min_attendance_rate: pol.diligence_min_attendance_rate ?? '98',
      diligence_max_late_count: pol.diligence_max_late_count ?? '1',
      diligence_max_absent_days: pol.diligence_max_absent_days ?? '0',
      diligence_max_forgot_punch: pol.diligence_max_forgot_punch ?? '2',
    };
  }
  correctionReasons.value = reasons.data.data || [];
  leaveTypes.value = leaveTypeList.data.data || [];
  leavePayrollCategories.value = leaveTypeMeta.data.data?.payroll_categories || {};
  bonusTypes.value = bonusTypeList.data.data || [];
  bonusCategories.value = bonusTypeMeta.data.data?.categories || {};
  bonusCalcModes.value = bonusTypeMeta.data.data?.calculation_modes || {};
  displayForm.value = cloneDisplayConfig(displayCfg.data.data);
}

// 1. Save Company Profile
async function saveCompanyProfile() {
  savingCompany.value = true;
  try {
    await api.put(`/companies/${auth.companyId}`, companyForm.value);
    toast.show('Đã cập nhật thông tin công ty thành công');
  } catch (err) {
    toast.show('Có lỗi xảy ra khi lưu thông tin công ty', 'error');
  } finally {
    savingCompany.value = false;
  }
}

// 2. Department Management
function buildDepartmentPayload() {
  const payload = {
    code: deptForm.value.code,
    name: deptForm.value.name,
    parent_department_id: deptForm.value.parent_department_id || null,
    manager_id: deptForm.value.manager_id || null,
    is_active: deptForm.value.is_active !== false,
  };
  if (!singleBranchMode.value && deptForm.value.branch_id) {
    payload.branch_id = deptForm.value.branch_id;
  }

  return payload;
}

function openDepartmentModal(dept = null) {
  if (dept) {
    deptForm.value = {
      id: dept.id,
      branch_id: dept.branch_id,
      code: dept.code,
      name: dept.name,
      parent_department_id: dept.parent_department_id,
      manager_id: dept.manager_id,
      is_active: dept.is_active !== false,
    };
  } else {
    deptForm.value = {
      branch_id: companyBranches.value[0]?.id || null,
      code: '',
      name: '',
      parent_department_id: null,
      manager_id: null,
      is_active: true,
    };
  }
  showDeptModal.value = true;
}

async function saveDepartment() {
  if (!singleBranchMode.value && !deptForm.value.branch_id) {
    toast.show('Vui lòng chọn chi nhánh', 'error');
    return;
  }
  const payload = buildDepartmentPayload();
  try {
    if (deptForm.value.id) {
      await api.put(`/departments/${deptForm.value.id}`, payload);
      toast.show('Đã cập nhật phòng ban');
    } else {
      await api.post('/departments', payload);
      toast.show('Đã tạo phòng ban mới');
    }
    showDeptModal.value = false;
    const { data } = await api.get('/departments');
    departments.value = data.data;
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không thể lưu phòng ban', 'error');
  }
}

async function deleteDepartment(id) {
  if (!confirm('Bạn có chắc chắn muốn xóa phòng ban/bộ phận này không?')) return;
  try {
    await api.delete(`/departments/${id}`);
    toast.show('Đã xóa phòng ban');
    const { data } = await api.get('/departments');
    departments.value = data.data;
  } catch (err) {
    toast.show('Không thể xóa phòng ban có liên kết dữ liệu nhân viên.', 'error');
  }
}

// 3. Work Shift Management
function openShiftModal(shift = null) {
  if (shift) {
    shiftForm.value = { ...shift };
    shiftForm.value.start_time = formatTime(shift.start_time);
    shiftForm.value.end_time = formatTime(shift.end_time);
  } else {
    shiftForm.value = {
      code: '',
      name: '',
      start_time: '08:30',
      end_time: '17:30',
      break_minutes: 60,
      standard_hours: 8,
      is_night_shift: false,
      crosses_midnight: false,
      is_active: true,
    };
  }
  showShiftModal.value = true;
}

function applyNightShiftPreset() {
  shiftForm.value = {
    ...shiftForm.value,
    code: shiftForm.value.code || 'CA-DEM',
    name: 'Ca đêm (22:00 – 07:00)',
    start_time: '22:00',
    end_time: '07:00',
    break_minutes: 45,
    standard_hours: 8,
    is_night_shift: true,
    crosses_midnight: true,
    is_active: true,
  };
}

async function seedShiftPresets() {
  try {
    const { data } = await api.post('/work-shifts/seed-presets');
    shifts.value = data.data?.shifts || data.data || [];
    toast.show(data.data?.message || 'Đã thiết lập ca mẫu');
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không thiết lập được ca mẫu', 'error');
  }
}

async function saveShift() {
  try {
    if (shiftForm.value.id) {
      await api.put(`/work-shifts/${shiftForm.value.id}`, shiftForm.value);
      toast.show('Đã cập nhật ca làm việc');
    } else {
      await api.post('/work-shifts', shiftForm.value);
      toast.show('Đã tạo ca làm việc mới');
    }
    showShiftModal.value = false;
    const { data } = await api.get('/work-shifts');
    shifts.value = data.data;
  } catch (err) {
    toast.show(err.response?.data?.message || 'Lỗi khi lưu ca làm việc', 'error');
  }
}

async function deleteShift(id) {
  if (!confirm('Bạn có chắc chắn muốn xóa ca làm việc này không?')) return;
  try {
    await api.delete(`/work-shifts/${id}`);
    toast.show('Đã xóa ca làm việc');
    const { data } = await api.get('/work-shifts');
    shifts.value = data.data;
  } catch (err) {
    toast.show('Không thể xóa ca làm việc.', 'error');
  }
}

function isNightShift(shift) {
  if (shift.is_night_shift) return true;
  const start = shift.start_time ? parseInt(shift.start_time.split(':')[0]) : 0;
  const end = shift.end_time ? parseInt(shift.end_time.split(':')[0]) : 0;
  return (start >= 22 || start < 6 || end >= 22 || end <= 6 || shift.crosses_midnight);
}

function checkShiftBreakCompliance(shift) {
  const night = isNightShift(shift);
  const minBreak = night ? 45 : 30;
  if (shift.break_minutes >= minBreak) {
    return { valid: true, message: 'Đạt chuẩn (BLLĐ)' };
  }
  return { valid: false, message: `Vi phạm (Y/c ca ${night ? 'đêm >=45p' : 'ngày >=30p'})` };
}

// 4. Save Policies & regimes
async function savePolicies() {
  savingPolicies.value = true;
  try {
    await api.post('/company-settings', { settings: policyForm.value });
    toast.show('Đã lưu cấu hình chế độ lao động thành công');
  } catch (err) {
    toast.show('Có lỗi xảy ra khi cấu hình chế độ chính sách', 'error');
  } finally {
    savingPolicies.value = false;
  }
}

async function saveDiligenceSettings() {
  savingDiligence.value = true;
  try {
    await api.post('/company-settings', { settings: diligenceForm.value });
    toast.show('Đã lưu cấu hình chấm công & chuyên cần');
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không lưu được cấu hình', 'error');
  } finally {
    savingDiligence.value = false;
  }
}

function openReasonModal(reason = null) {
  reasonForm.value = reason
    ? { ...reason }
    : { code: '', name: '', sort_order: correctionReasons.value.length + 1, counts_as_forgot_punch: false, is_active: true };
  showReasonModal.value = true;
}

async function saveReason() {
  try {
    const payload = { ...reasonForm.value, code: String(reasonForm.value.code || '').toUpperCase() };
    if (payload.id) {
      await api.put(`/attendance-correction-reasons/${payload.id}`, payload);
      toast.show('Đã cập nhật lý do');
    } else {
      await api.post('/attendance-correction-reasons', payload);
      toast.show('Đã thêm lý do bù thẻ');
    }
    showReasonModal.value = false;
    const { data } = await api.get('/attendance-correction-reasons');
    correctionReasons.value = data.data || [];
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không lưu được lý do', 'error');
  }
}

async function deleteReason(id) {
  if (!confirm('Xóa lý do này?')) return;
  try {
    await api.delete(`/attendance-correction-reasons/${id}`);
    toast.show('Đã xóa');
    const { data } = await api.get('/attendance-correction-reasons');
    correctionReasons.value = data.data || [];
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không xóa được', 'error');
  }
}

// 5. Job Level Management
function onGradeChange() {
  const g = gradeOptions.value.find((x) => x.grade === levelForm.value.grade);
  if (g) {
    levelForm.value.category = g.category;
    levelForm.value.name = `${g.name} — Band ${levelForm.value.band || 'A'}`;
    const bandIdx = bandOptions.value.indexOf(levelForm.value.band || 'A');
    levelForm.value.rank = (g.rank_base || 100) + bandIdx + 1;
  }
  syncLevelCode();
}

function syncLevelCode() {
  if (levelForm.value.grade && levelForm.value.band) {
    levelForm.value.code = `${levelForm.value.grade}-${levelForm.value.band}`;
    const g = gradeOptions.value.find((x) => x.grade === levelForm.value.grade);
    if (g && !levelForm.value.id) {
      levelForm.value.name = `${g.name} — Band ${levelForm.value.band}`;
    }
  }
}

async function seedJobLevels() {
  if (!confirm('Áp dụng thang O1–O7 (28 cấp bậc)? Cấp LV cũ sẽ được ngưng.')) return;
  try {
    const { data } = await api.post('/job-levels/seed-standard');
    jobLevels.value = data.data?.levels || [];
    toast.show(data.data?.message || 'Đã áp dụng thang cấp bậc');
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không áp dụng được thang cấp bậc', 'error');
  }
}

function openJobLevelModal(level = null) {
  if (level) {
    levelForm.value = { ...level };
  } else {
    levelForm.value = {
      code: 'O6-A',
      grade: 'O6',
      band: 'A',
      category: 'employee',
      name: 'O6 — Nhân viên nghiệp vụ — Band A',
      rank: 601,
      basic_salary_range_min: 8000000,
      basic_salary_range_max: 18000000,
      is_active: true,
    };
  }
  showLevelModal.value = true;
}

async function saveJobLevel() {
  try {
    if (levelForm.value.id) {
      await api.put(`/job-levels/${levelForm.value.id}`, levelForm.value);
      toast.show('Đã cập nhật cấp bậc nhân sự');
    } else {
      await api.post('/job-levels', levelForm.value);
      toast.show('Đã thêm cấp bậc mới');
    }
    showLevelModal.value = false;
    const { data } = await api.get('/job-levels');
    const payload = data.data;
    jobLevels.value = Array.isArray(payload) ? payload : (payload?.levels || []);
    if (payload?.catalog) jobLevelCatalog.value = payload.catalog;
  } catch (err) {
    toast.show(err.response?.data?.message || 'Lỗi khi lưu cấp bậc', 'error');
  }
}

async function deleteJobLevel(id) {
  if (!confirm('Bạn có chắc chắn muốn xóa cấp bậc nhân sự này không?')) return;
  try {
    await api.delete(`/job-levels/${id}`);
    toast.show('Đã xóa cấp bậc');
    const { data } = await api.get('/job-levels');
    const payload = data.data;
    jobLevels.value = Array.isArray(payload) ? payload : (payload?.levels || []);
  } catch (err) {
    toast.show('Không thể xóa cấp bậc nhân sự.', 'error');
  }
}

// 6. Company Holiday Calendar
const holidayDayCount = computed(() => {
  const start = holidayForm.value.holiday_date;
  const end = holidayForm.value.end_date || start;
  if (!start || !end) return 1;
  const s = new Date(`${start}T00:00:00`);
  const e = new Date(`${end}T00:00:00`);
  if (Number.isNaN(s.getTime()) || Number.isNaN(e.getTime()) || e < s) return 1;
  return Math.floor((e - s) / 86400000) + 1;
});

function formatHolidayRange(holiday) {
  const start = formatDate(holiday.holiday_date);
  const end = formatDate(holiday.end_date || holiday.holiday_date);
  return start === end ? start : `${start} → ${end}`;
}

function syncHolidayEndDate() {
  if (!holidayForm.value.end_date || holidayForm.value.end_date < holidayForm.value.holiday_date) {
    holidayForm.value.end_date = holidayForm.value.holiday_date;
  }
}

function openHolidayModal(holiday = null) {
  if (holiday) {
    holidayForm.value = {
      ...holiday,
      holiday_date: String(holiday.holiday_date || '').slice(0, 10),
      end_date: String(holiday.end_date || holiday.holiday_date || '').slice(0, 10),
    };
  } else {
    const today = new Date().toISOString().split('T')[0];
    holidayForm.value = {
      name: '',
      holiday_date: today,
      end_date: today,
      is_paid: true,
    };
  }
  showHolidayModal.value = true;
}

async function saveHoliday() {
  try {
    if (!holidayForm.value.end_date) {
      holidayForm.value.end_date = holidayForm.value.holiday_date;
    }
    const payload = { ...holidayForm.value };
    if (payload.id) {
      await api.put(`/company-holidays/${payload.id}`, payload);
      toast.show('Đã cập nhật lịch nghỉ lễ');
    } else {
      await api.post('/company-holidays', payload);
      toast.show('Đã lưu khoảng nghỉ lễ');
    }
    showHolidayModal.value = false;
    const { data } = await api.get('/company-holidays');
    holidays.value = data.data;
  } catch (err) {
    toast.show(err.response?.data?.message || err.response?.data?.errors?.holiday_date?.[0] || 'Lỗi khi cấu hình ngày lễ', 'error');
  }
}

async function deleteHoliday(id) {
  if (!confirm('Bạn có chắc chắn muốn xóa ngày lễ này không?')) return;
  try {
    await api.delete(`/company-holidays/${id}`);
    toast.show('Đã xóa ngày nghỉ lễ');
    const { data } = await api.get('/company-holidays');
    holidays.value = data.data;
  } catch (err) {
    toast.show('Không thể xóa ngày nghỉ lễ này.', 'error');
  }
}

// 7. Spatie RBAC Phân quyền & Vai trò
function openCreateRoleModal() {
  newRoleName.value = '';
  showCreateRoleModal.value = true;
}

async function saveNewRole() {
  try {
    await api.post('/roles', { name: newRoleName.value });
    toast.show('Đã thêm vai trò bảo mật mới');
    showCreateRoleModal.value = false;
    const { data } = await api.get('/roles');
    roles.value = data.data;
  } catch (err) {
    toast.show(err.response?.data?.message || 'Lỗi khi thêm vai trò mới. Đảm bảo tên vai trò không trùng lặp.', 'error');
  }
}

function openRolePermissions(role) {
  selectedRole.value = { ...role };
  selectedPermissions.value = role.permissions.map(p => p.name);
  showRolePermissionsModal.value = true;
}

async function saveRolePermissions() {
  try {
    await api.put(`/roles/${selectedRole.value.id}`, {
      name: selectedRole.value.name,
      permissions: selectedPermissions.value
    });
    toast.show(`Đồng bộ quyền hạn cho vai trò ${selectedRole.value.name} thành công`);
    showRolePermissionsModal.value = false;
    const { data } = await api.get('/roles');
    roles.value = data.data;
  } catch (err) {
    toast.show('Không thể đồng bộ quyền hạn', 'error');
  }
}

function toggleCategoryPermissions(permsList) {
  const allSelected = permsList.every(p => selectedPermissions.value.includes(p));
  if (allSelected) {
    // Bỏ chọn tất cả thuộc Category này
    selectedPermissions.value = selectedPermissions.value.filter(p => !permsList.includes(p));
  } else {
    // Chọn tất cả thuộc Category này
    permsList.forEach(p => {
      if (!selectedPermissions.value.includes(p)) {
        selectedPermissions.value.push(p);
      }
    });
  }
}

async function deleteRole(id) {
  if (!confirm('Bạn có chắc chắn muốn xóa vai trò bảo mật này không?')) return;
  try {
    await api.delete(`/roles/${id}`);
    toast.show('Đã xóa vai trò thành công');
    const { data } = await api.get('/roles');
    roles.value = data.data;
  } catch (err) {
    toast.show(err.response?.data?.message || 'Có lỗi xảy ra', 'error');
  }
}

function companyCode(companyId) {
  return tenantCompanies.value.find((c) => c.id === Number(companyId))?.code || `#${companyId}`;
}

async function openUserAccess(user) {
  selectedUser.value = { ...user };
  selectedCompanyIds.value = [];
  selectedUserRoles.value = [];
  defaultCompanyId.value = user.default_company_id || null;
  try {
    const { data } = await api.get(`/users/${user.id}/company-access`);
    selectedCompanyIds.value = (data.data.companies || []).map((c) => c.id);
    const roleSet = new Set();
    Object.values(data.data.company_roles || {}).forEach((rs) => rs.forEach((r) => roleSet.add(r)));
    selectedUserRoles.value = [...roleSet];
    defaultCompanyId.value = data.data.default_company_id || selectedCompanyIds.value[0] || null;
  } catch {
    selectedCompanyIds.value = (user.companies || []).map((c) => c.id);
  }
  showUserAccessModal.value = true;
}

async function saveUserAccess() {
  if (!selectedUser.value?.id) return;
  try {
    await api.put(`/users/${selectedUser.value.id}/access`, {
      company_ids: selectedCompanyIds.value.map(Number),
      roles: selectedUserRoles.value,
      default_company_id: defaultCompanyId.value ? Number(defaultCompanyId.value) : null,
    });
    toast.show(`Đã cập nhật phân quyền cho ${selectedUser.value.name}`);
    showUserAccessModal.value = false;
    await loadUsers();
  } catch (err) {
    toast.show(err.response?.data?.message || 'Không thể lưu phân quyền', 'error');
  }
}

// Helpers
function formatTime(val) {
  if (!val) return '—';
  return val.substring(0, 5);
}

function formatDate(val) {
  if (!val) return '—';
  return new Date(val).toLocaleDateString('vi-VN', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function formatMoney(val) {
  if (val === null || val === undefined) return '0';
  return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

onMounted(async () => {
  await loadAllData();
});
</script>
