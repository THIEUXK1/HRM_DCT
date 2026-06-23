<template>
  <div>
    <UiPageHeader title="Chấm công & Bảng công" subtitle="Công theo ngày · Tổng hợp tháng · Báo cáo OT · Chuyên cần · Nghỉ phép · Thôi việc" breadcrumb="Attendance">
      <template #actions>
        <button type="button" class="hcm-btn-secondary text-sm" @click="exportCongLuong" :disabled="exporting">
          {{ exporting ? 'Đang xuất...' : '⬇ Excel công-lương' }}
        </button>
        <button type="button" class="hcm-btn-secondary" @click="buildSummary" :disabled="building || periodStatus?.is_locked">
          {{ building ? 'Đang tổng hợp...' : '🔄 Tổng hợp công tháng' }}
        </button>
        <button v-if="!periodStatus?.is_locked" type="button" class="hcm-btn-primary" @click="lockSummary">🔒 Khóa công {{ period }}</button>
        <button v-else-if="isAdmin" type="button" class="hcm-btn-secondary text-rose-700" @click="unlockSummary">🔓 Mở khóa công (admin)</button>
      </template>
    </UiPageHeader>

    <div v-if="periodStatus?.is_locked" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
      🔒 Kỳ công <strong>{{ period }}</strong> đã khóa
      <span v-if="periodStatus.locked_by"> bởi {{ periodStatus.locked_by }}</span>.
      Không thể tổng hợp lại hoặc sửa — chỉ admin mở khóa.
    </div>

    <!-- Bộ lọc chung -->
    <div class="hcm-card p-4 mb-4 space-y-4">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="text-sm font-medium">Kỳ tính công</label>
          <input v-model="period" type="month" class="hcm-input mt-1" @change="reloadTab" />
        </div>
        <UiOrgScopeFilters
          :show-reset="false"
          :show-company-picker="showCompanyPicker"
          :single-branch-mode="singleBranchMode"
          v-model:filter-branch-id="filterBranchId"
          v-model:filter-department-id="filterDepartmentId"
          :branches="orgBranches"
          :filtered-departments="filteredDepartments"
          @change="reloadTab"
        />
        <div class="flex-1 min-w-[220px]">
          <label class="text-sm font-medium">Tìm nhân viên</label>
          <UiSearchInput
            v-model="employeeSearch"
            class="mt-1"
            placeholder="Tên hoặc mã NV..."
            input-class="hcm-input w-full"
            @search="onEmployeeSearch"
          />
        </div>
        <button type="button" class="hcm-btn-secondary text-sm" @click="reloadTab">🔄 Tải lại</button>
      </div>
    </div>

    <!-- Tab điều hướng -->
    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="px-3 py-2 text-sm font-medium border-b-2 -mb-px transition-all whitespace-nowrap"
        :class="tab === t.key ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="switchTab(t.key)"
      >
        {{ t.icon }} {{ t.label }}
      </button>
    </div>

    <div v-if="loading" class="text-center py-16 text-slate-400">Đang tải dữ liệu...</div>
    <div v-else-if="error" class="hcm-card p-6 text-red-600">{{ error }}</div>

    <!-- TAB: Bảng công theo ngày -->
    <template v-else-if="tab === 'daily'">
      <div v-if="!filteredTimesheetEmployees.length" class="hcm-card p-8">
        <UiEmpty title="Chưa có nhân viên" subtitle="Thêm nhân viên hoặc import log chấm công trước." />
      </div>
      <div v-else class="hcm-card overflow-hidden p-0">
        <AttendanceDailyGridTable
          :timesheet="timesheet"
          :rows="filteredTimesheetEmployees"
          :legend-items="footerLegendItems"
          @open-employee="openEmployeeDetail"
          @open-day="(row, date) => openEmployeeDetail(row, date)"
        />
      </div>
    </template>

    <!-- TAB: Công & lương BestPacific (mẫu chuẩn) -->
    <template v-else-if="tab === 'congLuong'">
      <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 mb-4 text-xs text-emerald-900">
        <b>Mẫu chuẩn BestPacific</b> — bảng ngang 1 dòng / NV như file « công và lương.xlsx ».
        Sheet công A→AO; sheet lương A→AA (LCB, trợ cấp, BHXH TV cột T…). Tách TV/CT: tab « TV/CT giai đoạn ».
      </div>
      <div class="hcm-card overflow-hidden mb-6">
        <div class="px-5 py-3 border-b border-slate-100 flex justify-between items-center">
          <h3 class="font-semibold">Sheet công — {{ period }}</h3>
          <span class="text-xs text-slate-500">{{ filteredCongLuongCongRows.length }} NV · {{ congLuongReport.cong?.columns?.length || 0 }} cột</span>
        </div>
        <div class="overflow-x-auto max-h-[70vh]">
          <table class="min-w-max text-xs border-collapse" v-if="filteredCongLuongCongRows.length">
            <thead class="bg-slate-50 text-slate-600 sticky top-0 z-20">
              <tr>
                <th
                  v-for="col in congLuongReport.cong?.columns"
                  :key="col.col"
                  :class="sheetHeaderClass(col)"
                  :title="col.label"
                >
                  <span class="block text-[9px] text-slate-400 font-mono leading-none">{{ col.col }}</span>
                  <span class="block font-medium leading-tight whitespace-nowrap">{{ col.label }}</span>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="r in filteredCongLuongCongRows" :key="r.employee_id" class="hover:bg-slate-50">
                <td
                  v-for="col in congLuongReport.cong?.columns"
                  :key="`${r.employee_id}-${col.col}`"
                  :class="sheetCellClass(col)"
                >
                  {{ formatSheetCell(r, col, 'cong') }}
                </td>
              </tr>
            </tbody>
          </table>
          <UiEmpty v-else title="Chưa có bảng công BP" subtitle="Import file mẫu hoặc tổng hợp công tháng rồi import/export Excel công & lương." />
        </div>
      </div>
      <div class="hcm-card overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex justify-between items-center">
          <h3 class="font-semibold">Sheet lương / trợ cấp — {{ period }}</h3>
          <span class="text-xs text-slate-500">{{ filteredCongLuongLuongRows.length }} NV · {{ congLuongReport.luong?.columns?.length || 0 }} cột</span>
        </div>
        <div class="overflow-x-auto max-h-[60vh]">
          <table class="min-w-max text-xs border-collapse" v-if="filteredCongLuongLuongRows.length">
            <thead class="bg-slate-50 text-slate-600 sticky top-0 z-20">
              <tr>
                <th
                  v-for="col in congLuongReport.luong?.columns"
                  :key="col.col"
                  :class="sheetHeaderClass(col)"
                  :title="col.label"
                >
                  <span class="block text-[9px] text-slate-400 font-mono leading-none">{{ col.col }}</span>
                  <span class="block font-medium leading-tight whitespace-nowrap">{{ col.label }}</span>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="r in filteredCongLuongLuongRows" :key="r.employee_id" class="hover:bg-slate-50">
                <td
                  v-for="col in congLuongReport.luong?.columns"
                  :key="`${r.employee_id}-${col.col}`"
                  :class="sheetCellClass(col)"
                >
                  {{ formatSheetCell(r, col, 'luong') }}
                </td>
              </tr>
            </tbody>
          </table>
          <UiEmpty v-else title="Chưa có sheet lương" subtitle="Import tab lương từ file Excel mẫu." />
        </div>
      </div>
    </template>

    <!-- TAB: Bảng công tách giai đoạn TV/CT (chuẩn lương VN) -->
    <template v-else-if="tab === 'phased'">
      <div class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 mb-4 text-xs text-blue-900">
        <b>TV/CT giai đoạn:</b> Khi NV chuyển thử việc → chính thức trong tháng, bảng công tách 1–2 dòng theo khoảng ngày — X, P, KL, V và OT (150/200/300%) riêng từng giai đoạn.
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-slate-800">{{ phasedReport.summary?.total_lines || 0 }}</p>
          <p class="text-xs text-slate-500">Dòng bảng công</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-blue-700">{{ phasedReport.summary?.probation_lines || 0 }}</p>
          <p class="text-xs text-slate-500">Dòng thử việc</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-green-700">{{ phasedReport.summary?.official_lines || 0 }}</p>
          <p class="text-xs text-slate-500">Dòng chính thức</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-indigo-700">{{ phasedReport.summary?.split_employees || 0 }}</p>
          <p class="text-xs text-slate-500">NV chuyển TV→CT</p>
        </div>
      </div>
      <div v-if="!filteredPhasedRows.length" class="hcm-card p-8">
        <UiEmpty title="Chưa có dữ liệu giai đoạn" subtitle="Bấm «Tổng hợp công tháng» rồi mở lại tab này." />
      </div>
      <div v-else class="hcm-card overflow-hidden p-0">
        <AttendancePhasedGridTable
          :report="phasedReport"
          :rows="filteredPhasedRows"
          @open-employee="openEmployeeDetail"
          @open-breakdown="openBreakdownFromGrid"
        />
      </div>
    </template>

    <!-- TAB: Bảng công tháng -->
    <template v-else-if="tab === 'monthly'">
      <div v-if="!filteredMonthlyGridRows.length" class="hcm-card p-8">
        <UiEmpty title="Chưa có bảng công tháng" subtitle="Bấm «Tổng hợp công tháng» để tính từ log chấm công + phép + OT." />
      </div>
      <div v-else class="hcm-card overflow-hidden p-0">
        <AttendanceMonthlyGridTable
          :report="monthlyGridReport"
          :rows="filteredMonthlyGridRows"
          @open-employee="openEmployeeDetail"
          @open-breakdown="openBreakdownFromGrid"
        />
      </div>
    </template>

    <!-- TAB: Báo cáo OT -->
    <template v-else-if="tab === 'ot'">
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-blue-700">{{ otReport.summary?.total_employees || 0 }}</p>
          <p class="text-xs text-slate-500">NV có OT</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-slate-800">{{ fmt(otReport.summary?.total_ot_hours) }}h</p>
          <p class="text-xs text-slate-500">Tổng giờ OT</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-red-600">{{ otReport.summary?.cap_exceeded_count || 0 }}</p>
          <p class="text-xs text-slate-500">Vượt cap 40h/tháng</p>
        </div>
      </div>
      <div class="hcm-card overflow-hidden">
        <table class="hcm-table w-full text-sm" v-if="otReport.rows?.length">
          <thead>
            <tr>
              <th>Nhân viên</th>
              <th>Phòng ban</th>
              <th class="text-right">OT TT (150%)</th>
              <th class="text-right">OT T7 (200%)</th>
              <th class="text-right">OT Lễ (300%)</th>
              <th class="text-right">OT TV</th>
              <th class="text-right">OT CT</th>
              <th class="text-right">Tổng OT</th>
              <th class="text-right">Đêm</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in filteredOtRows" :key="r.employee_id" class="hover:bg-slate-50" :class="{ 'bg-red-50/40': r.ot_monthly_cap_exceeded }">
              <td>
                <p class="font-medium">{{ r.full_name }}</p>
                <p class="text-xs text-slate-400">{{ r.employee_code }}</p>
              </td>
              <td class="text-xs text-slate-500">{{ r.department || '—' }}</td>
              <td class="text-right">{{ fmt(r.ot_weekday_hours) }}</td>
              <td class="text-right">{{ fmt(r.ot_weekend_hours) }}</td>
              <td class="text-right">{{ fmt(r.ot_holiday_hours) }}</td>
              <td class="text-right" :style="totalsCellStyle('probation')">{{ fmt(r.ot_probation_hours) }}</td>
              <td class="text-right" :style="totalsCellStyle('official')">{{ fmt(r.ot_official_hours) }}</td>
              <td class="text-right font-semibold">{{ fmt(r.ot_total_hours) }}</td>
              <td class="text-right">{{ fmt(r.night_hours) }}</td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Không có OT trong kỳ" />
      </div>
    </template>

    <!-- TAB: Chuyên cần -->
    <template v-else-if="tab === 'diligence'">
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-4">
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-green-700">{{ diligenceReport.summary?.avg_attendance_rate || 0 }}%</p>
          <p class="text-xs text-slate-500">TB chuyên cần</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-emerald-700">{{ diligenceReport.summary?.bonus_eligible_count || 0 }}</p>
          <p class="text-xs text-slate-500">Đủ thưởng chuyên cần</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-blue-700">{{ money(diligenceReport.summary?.total_bonus_amount) }}</p>
          <p class="text-xs text-slate-500">Tổng thưởng dự kiến</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-amber-600">{{ diligenceReport.summary?.total_late_incidents || 0 }}</p>
          <p class="text-xs text-slate-500">Lần đi trễ</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-red-600">{{ fmt(diligenceReport.summary?.total_absent_days) }}</p>
          <p class="text-xs text-slate-500">Tổng ngày vắng</p>
        </div>
      </div>
      <div class="hcm-card overflow-hidden">
        <table class="hcm-table w-full text-sm" v-if="diligenceReport.rows?.length">
          <thead>
            <tr>
              <th>Nhân viên</th>
              <th>Phòng ban</th>
              <th class="text-right">Công</th>
              <th class="text-right">Vắng</th>
              <th class="text-right">Trễ (lần)</th>
              <th class="text-right">Trễ (phút)</th>
              <th class="text-right">Quên chấm</th>
              <th class="text-right">Thưởng CC</th>
              <th class="text-right">% Chuyên cần</th>
              <th>Xếp loại</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in filteredDiligenceRows" :key="r.employee_id" class="hover:bg-slate-50">
              <td class="font-medium">{{ r.full_name }}</td>
              <td class="text-xs text-slate-500">{{ r.department || '—' }}</td>
              <td class="text-right">{{ fmt(r.work_days) }}/{{ fmt(r.standard_work_days) }}</td>
              <td class="text-right text-red-600">{{ r.absent_days > 0 ? fmt(r.absent_days) : '—' }}</td>
              <td class="text-right text-amber-700">{{ r.late_count || '—' }}</td>
              <td class="text-right">{{ r.late_minutes > 0 ? Math.round(r.late_minutes) : '—' }}</td>
              <td class="text-right text-amber-700">{{ r.forgot_punch_count > 0 ? r.forgot_punch_count : '—' }}</td>
              <td class="text-right">
                <UiBadge :variant="r.diligence_bonus_eligible ? 'success' : 'default'">
                  {{ r.diligence_bonus_eligible ? money(r.diligence_bonus_amount) : 'Không' }}
                </UiBadge>
              </td>
              <td class="text-right font-semibold">{{ r.attendance_rate }}%</td>
              <td>
                <UiBadge :variant="gradeVariant(r.diligence_grade)">{{ r.diligence_grade }}</UiBadge>
                <p v-if="r.diligence_disqualify_reasons?.length" class="text-[10px] text-red-600 mt-0.5 max-w-[140px]">{{ r.diligence_disqualify_reasons[0] }}</p>
              </td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Chưa có dữ liệu chuyên cần" subtitle="Tổng hợp công tháng trước để tính báo cáo." />
      </div>
    </template>

    <!-- TAB: Nghỉ phép -->
    <template v-else-if="tab === 'leave'">
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-4">
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-slate-800">{{ leaveReport.summary?.total_requests || 0 }}</p>
          <p class="text-xs text-slate-500">Tổng đơn</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-green-700">{{ leaveReport.summary?.approved_requests || 0 }}</p>
          <p class="text-xs text-slate-500">Đã duyệt</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-amber-600">{{ fmt(leaveReport.summary?.approved_paid_days) }}</p>
          <p class="text-xs text-slate-500">Ngày có lương</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-orange-600">{{ fmt(leaveReport.summary?.approved_unpaid_days) }}</p>
          <p class="text-xs text-slate-500">Ngày không lương</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-slate-600">{{ leaveReport.summary?.pending_requests || 0 }}</p>
          <p class="text-xs text-slate-500">Chờ duyệt</p>
        </div>
      </div>
      <div class="hcm-card overflow-hidden">
        <table class="hcm-table w-full text-sm" v-if="leaveReport.rows?.length">
          <thead>
            <tr>
              <th>Nhân viên</th>
              <th>Loại nghỉ</th>
              <th>Hưởng lương</th>
              <th>Từ — Đến</th>
              <th class="text-right">Số ngày</th>
              <th>Trạng thái</th>
              <th>Lý do</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in filteredLeaveRows" :key="r.id" class="hover:bg-slate-50">
              <td class="font-medium">{{ r.full_name }}</td>
              <td>
                <span class="font-mono text-xs mr-1">{{ r.cell_symbol || '—' }}</span>
                {{ r.leave_type || '—' }}
              </td>
              <td>
                <UiBadge :variant="r.is_paid ? 'success' : 'warning'">{{ r.paid_label }}</UiBadge>
              </td>
              <td class="text-xs">{{ r.start_date }} → {{ r.end_date }}</td>
              <td class="text-right">{{ fmt(r.total_days) }}</td>
              <td><UiBadge :variant="statusVariant(r.status)">{{ statusLabel(r.status) }}</UiBadge></td>
              <td class="text-xs text-slate-500 max-w-[200px] truncate">{{ r.reason || '—' }}</td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Không có đơn nghỉ trong kỳ" />
      </div>
    </template>

    <!-- TAB: Thôi việc -->
    <template v-else-if="tab === 'termination'">
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-slate-800">{{ termReport.summary?.total_terminations || 0 }}</p>
          <p class="text-xs text-slate-500">Nghỉ việc trong kỳ</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-green-700">{{ termReport.summary?.approved || 0 }}</p>
          <p class="text-xs text-slate-500">Đã duyệt</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-blue-700">{{ termReport.summary?.completed || 0 }}</p>
          <p class="text-xs text-slate-500">Hoàn tất offboarding</p>
        </div>
      </div>
      <div class="hcm-card overflow-hidden">
        <table class="hcm-table w-full text-sm" v-if="termReport.rows?.length">
          <thead>
            <tr>
              <th>Nhân viên</th>
              <th>Phòng ban</th>
              <th>Ngày nghỉ</th>
              <th>Hình thức</th>
              <th class="text-right">Công trước nghỉ</th>
              <th>Thanh toán cuối</th>
              <th>Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in filteredTermRows" :key="r.employee_id" class="hover:bg-slate-50">
              <td class="font-medium">{{ r.full_name }}</td>
              <td class="text-xs text-slate-500">{{ r.department || '—' }}</td>
              <td>{{ r.termination_date }}</td>
              <td class="text-xs">{{ r.type || r.reason_type || '—' }}</td>
              <td class="text-right">{{ r.work_days_before_exit }}</td>
              <td>
                <UiBadge :variant="r.final_settlement_done ? 'success' : 'warning'">
                  {{ r.final_settlement_done ? 'Đã TT' : 'Chưa TT' }}
                </UiBadge>
              </td>
              <td>{{ r.status }}</td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else :title="`Không có nghỉ việc trong kỳ ${period}`" />
      </div>
    </template>

    <!-- TAB: Thiết bị & Import -->
    <template v-else-if="tab === 'devices'">
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="hcm-card p-5">
          <h3 class="font-semibold mb-3">Thiết bị chấm công</h3>
          <ul class="space-y-3">
            <li v-for="d in devices" :key="d.id" class="rounded-lg border border-slate-100 p-4">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p class="font-medium">{{ d.name }}</p>
                  <p class="text-xs text-slate-500">{{ d.code }} · {{ d.vendor || 'Generic' }} · {{ deviceTypeLabel(d.device_type) }}</p>
                  <p v-if="d.geofence_zone" class="text-xs text-emerald-700 mt-1">Geofence: {{ d.geofence_zone.name }}</p>
                </div>
                <button
                  v-if="['terminal','kiosk'].includes(d.device_type)"
                  type="button"
                  class="text-xs hcm-btn-secondary"
                  @click="issueDeviceToken(d.id)"
                >
                  🔑 Cấp token API
                </button>
              </div>
              <label v-if="d.device_type === 'import'" class="mt-3 flex cursor-pointer items-center gap-2 text-sm text-primary-600">
                <input type="file" accept=".csv" class="hidden" @change="(ev) => importFile(d.id, ev)" />
                📤 Import file CSV
              </label>
            </li>
          </ul>
          <UiEmpty v-if="!devices.length" title="Chưa có thiết bị" />
          <p v-if="lastImport" class="mt-3 text-sm text-emerald-700 font-medium">✅ {{ lastImport }}</p>
          <p v-if="lastDeviceToken" class="mt-3 text-xs break-all rounded bg-amber-50 border border-amber-200 p-3 text-amber-900">
            Token (chỉ hiện 1 lần): <code>{{ lastDeviceToken }}</code>
          </p>
        </div>
        <div class="hcm-card p-5">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold">Khu vực geofence (GPS / QR)</h3>
            <div class="flex gap-2">
              <button type="button" class="text-sm hcm-btn-primary" @click="openGeofenceModal()">+ Thêm vùng</button>
              <button type="button" class="text-sm hcm-btn-secondary" @click="reloadGeofence">🔄</button>
            </div>
          </div>
          <ul class="space-y-2 text-sm">
            <li v-for="z in geofenceZones" :key="z.id" class="rounded border border-slate-100 p-3">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p class="font-medium">{{ z.name }} <span class="text-slate-400 font-normal">({{ z.code }})</span></p>
                  <p class="text-xs text-slate-500">{{ z.latitude }}, {{ z.longitude }} · r={{ z.radius_meters }}m · {{ z.zone_type }}</p>
                  <p v-if="z.branch" class="text-xs text-blue-700 mt-1">🏢 Chi nhánh: {{ z.branch.name }}</p>
                  <p v-else class="text-xs text-slate-400 mt-1">Vùng chung (mọi chi nhánh trong công ty)</p>
                  <p v-if="z.gate_token_hash" class="text-xs text-emerald-700 mt-1">✓ Đã cấp QR cổng</p>
                </div>
                <div class="flex flex-wrap gap-1">
                  <button type="button" class="text-xs text-primary-600 hover:underline" @click="openGeofenceModal(z)">Sửa</button>
                  <button type="button" class="text-xs text-primary-600 hover:underline" @click="issueGateQr(z)">📱 QR cổng</button>
                </div>
              </div>
            </li>
          </ul>
          <UiEmpty v-if="!geofenceZones.length" title="Chưa có vùng geofence" />
          <p class="text-xs text-slate-500 mt-4">Dùng <b>Lấy vị trí hiện tại</b> khi đứng tại nhà máy để cấu hình chính xác. In QR dán tại cổng cho NV quét khi GPS yếu trong nhà xưởng.</p>
        </div>
        <div class="hcm-card p-5 space-y-3 text-xs text-blue-800 bg-blue-50 border border-blue-200 rounded-lg lg:col-span-2">
          <p class="font-semibold text-sm">📋 Quy tắc BLLĐ 2019</p>
          <p>• OT ngày thường: tối đa 4h/ngày, 40h/tháng → hệ số <b>150%</b></p>
          <p>• OT cuối tuần: <b>200%</b> · OT ngày lễ: <b>300%</b></p>
          <p>• Làm đêm 22:00–06:00: cộng thêm <b>+30%</b></p>
          <p class="text-slate-600 pt-2">CSV: <code class="bg-white px-1 rounded">employee_code,work_date,check_in,check_out</code></p>
        </div>
      </div>
    </template>

    <UiModal v-model="showGeofenceModal" :title="geofenceForm.id ? 'Sửa vùng geofence' : 'Thêm vùng geofence'" wide>
      <form class="space-y-4" @submit.prevent="saveGeofence">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium">Mã vùng</label>
            <input v-model="geofenceForm.code" class="hcm-input w-full mt-1 font-mono" required maxlength="32" :disabled="!!geofenceForm.id" />
          </div>
          <div>
            <label class="text-sm font-medium">Tên hiển thị</label>
            <input v-model="geofenceForm.name" class="hcm-input w-full mt-1" required />
          </div>
          <div>
            <label class="text-sm font-medium">Loại khu vực</label>
            <select v-model="geofenceForm.zone_type" class="hcm-input w-full mt-1">
              <option value="factory">Nhà máy</option>
              <option value="office">Văn phòng</option>
              <option value="warehouse">Kho</option>
              <option value="field_site">Công trường</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium">Chi nhánh áp dụng</label>
            <select v-model="geofenceForm.branch_id" class="hcm-input w-full mt-1">
              <option :value="null">— Vùng chung công ty —</option>
              <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }} ({{ b.code }})</option>
            </select>
            <p class="text-xs text-slate-500 mt-1">NV chỉ chấm GPS/QR trong vùng thuộc chi nhánh được phân công (hoặc vùng chung).</p>
          </div>
          <div>
            <label class="text-sm font-medium">Bán kính (mét)</label>
            <input v-model.number="geofenceForm.radius_meters" type="number" min="20" max="5000" class="hcm-input w-full mt-1" required />
          </div>
          <div>
            <label class="text-sm font-medium">Vĩ độ (latitude)</label>
            <input v-model="geofenceForm.latitude" type="number" step="0.000001" class="hcm-input w-full mt-1" required />
          </div>
          <div>
            <label class="text-sm font-medium">Kinh độ (longitude)</label>
            <input v-model="geofenceForm.longitude" type="number" step="0.000001" class="hcm-input w-full mt-1" required />
          </div>
        </div>
        <button type="button" class="hcm-btn-secondary text-sm" @click="useMyLocationForGeofence">📍 Lấy vị trí hiện tại (GPS)</button>
        <div>
          <label class="text-sm font-medium">Ghi chú địa chỉ</label>
          <input v-model="geofenceForm.address_note" class="hcm-input w-full mt-1" />
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="geofenceForm.is_active" type="checkbox" class="rounded text-primary-600" />
          Đang hoạt động
        </label>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="hcm-btn-secondary" @click="showGeofenceModal = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="savingGeofence">{{ savingGeofence ? 'Đang lưu...' : 'Lưu vùng' }}</button>
        </div>
      </form>
    </UiModal>

    <UiModal v-model="showQrModal" title="QR chấm công tại cổng">
      <div v-if="qrDisplay" class="text-center space-y-4">
        <p class="font-medium">{{ qrDisplay.zoneName }}</p>
        <img v-if="qrDisplay.dataUrl" :src="qrDisplay.dataUrl" alt="QR cổng" class="mx-auto rounded-lg border border-slate-200" />
        <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded p-3 text-left">
          In và dán tại cổng. NV mở <b>Chấm công GPS → Quét QR</b>. Mã token (chỉ hiện 1 lần):<br>
          <code class="break-all">{{ qrDisplay.gate_token }}</code>
        </p>
      </div>
    </UiModal>

    <UiModal v-model="showBreakdownModal" :title="`Chi tiết bảng công — ${breakdownEmployeeName}`" wide>
      <div v-if="breakdownDetail" class="space-y-4 text-sm">
        <div
          v-if="breakdownHasPhaseSplit(breakdownDetail)"
          class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-900"
        >
          <b>Chuyển TV→CT trong tháng</b>
          (hết TV {{ breakdownDetail.meta?.probation_end_date || '—' }}) —
          OT và nghỉ phép được tách theo giai đoạn trước / sau chuyển chính thức.
        </div>
        <div v-if="breakdownHasPhaseSplit(breakdownDetail)" class="grid md:grid-cols-2 gap-4">
          <div class="rounded-lg border border-blue-200 p-3" :style="totalsCellStyle('probation')">
            <h4 class="font-semibold mb-2">{{ phaseLabel('probation') }}</h4>
            <p class="text-xs">OT: <b>{{ fmt(breakdownDetail.ot_by_phase?.totals?.probation_hours) }}h</b></p>
            <p class="text-xs mt-1">Phép CL: <b>{{ fmt(breakdownDetail.leave_by_phase?.probation?.paid) }}</b> · Phép KL: <b>{{ fmt(breakdownDetail.leave_by_phase?.probation?.unpaid) }}</b></p>
          </div>
          <div class="rounded-lg border border-green-200 p-3" :style="totalsCellStyle('official')">
            <h4 class="font-semibold mb-2">{{ phaseLabel('official') }}</h4>
            <p class="text-xs">OT: <b>{{ fmt(breakdownDetail.ot_by_phase?.totals?.official_hours) }}h</b></p>
            <p class="text-xs mt-1">Phép CL: <b>{{ fmt(breakdownDetail.leave_by_phase?.official?.paid) }}</b> · Phép KL: <b>{{ fmt(breakdownDetail.leave_by_phase?.official?.unpaid) }}</b></p>
          </div>
        </div>
        <div>
          <h4 class="font-semibold text-slate-800 mb-2">OT theo loại (giờ) — tổng tháng</h4>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
            <div v-for="(label, key) in otGridLabels" :key="key" class="rounded border border-slate-200 px-3 py-2">
              <p class="text-[10px] text-slate-500 leading-tight">{{ label }}</p>
              <p class="font-semibold text-blue-700">{{ fmt(breakdownDetail.ot?.[key]) }}h</p>
            </div>
          </div>
        </div>
        <template v-if="breakdownHasPhaseSplit(breakdownDetail)">
          <div v-for="phaseKey in ['probation', 'official']" :key="phaseKey">
            <h4 class="font-semibold text-slate-800 mb-2">
              OT {{ phaseShortLabel(phaseKey) }} theo loại (giờ)
            </h4>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
              <div
                v-for="(label, key) in otGridLabels"
                :key="`${phaseKey}-${key}`"
                class="rounded border border-slate-200 px-3 py-2"
                :style="phaseKey === 'probation' ? totalsCellStyle('probation') : totalsCellStyle('official')"
              >
                <p class="text-[10px] text-slate-500 leading-tight">{{ label }}</p>
                <p class="font-semibold">{{ fmt(breakdownDetail.ot_by_phase?.[phaseKey]?.[key]) }}h</p>
              </div>
            </div>
          </div>
        </template>
        <div>
          <h4 class="font-semibold text-slate-800 mb-2">Nghỉ theo loại (ngày) — tổng tháng</h4>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <div v-for="(label, key) in leaveTypeLabels" :key="key" class="rounded border border-slate-200 px-3 py-2">
              <p class="text-[10px] text-slate-500">{{ label }}</p>
              <p class="font-semibold">{{ fmt(breakdownDetail.leave_by_type?.[key]) }}</p>
            </div>
          </div>
        </div>
        <template v-if="breakdownHasPhaseSplit(breakdownDetail)">
          <div v-for="phaseKey in ['probation', 'official']" :key="`leave-${phaseKey}`">
            <h4 class="font-semibold text-slate-800 mb-2">
              Nghỉ {{ phaseShortLabel(phaseKey) }} theo loại (ngày)
            </h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
              <div
                v-for="(label, key) in leaveTypeLabels"
                :key="`${phaseKey}-${key}`"
                class="rounded border border-slate-200 px-3 py-2"
                :style="phaseKey === 'probation' ? totalsCellStyle('probation') : totalsCellStyle('official')"
              >
                <p class="text-[10px] text-slate-500">{{ label }}</p>
                <p class="font-semibold">{{ fmt(breakdownDetail.leave_by_phase?.[phaseKey]?.by_type?.[key]) }}</p>
              </div>
            </div>
          </div>
        </template>
        <div v-if="breakdownDetail.work">
          <h4 class="font-semibold text-slate-800 mb-2">Công mở rộng</h4>
          <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
            <dt class="text-slate-500">Công tính lương</dt><dd class="font-medium">{{ fmt(breakdownDetail.work.payable_work_days) }}</dd>
            <dt class="text-slate-500">Ngày lễ trong tháng</dt><dd class="font-medium">{{ fmt(breakdownDetail.work.holiday_days) }}</dd>
            <dt class="text-slate-500">Ngày chưa vào</dt><dd class="font-medium">{{ fmt(breakdownDetail.work.days_not_joined) }}</dd>
            <dt class="text-slate-500">Giờ trực T7</dt><dd class="font-medium">{{ fmt(breakdownDetail.work.saturday_duty_hours) }}h</dd>
            <dt class="text-slate-500">Giờ nghỉ kinh nguyệt</dt><dd class="font-medium">{{ fmt(breakdownDetail.work.menstrual_leave_hours) }}h</dd>
            <dt class="text-slate-500">Công tác (ngày)</dt><dd class="font-medium">{{ fmt(breakdownDetail.work.business_trip_days) }}</dd>
          </dl>
        </div>
      </div>
      <UiEmpty v-else title="Chưa có breakdown" subtitle="Tổng hợp lại bảng công tháng để tính chi tiết OT/nghỉ." />
    </UiModal>

    <!-- Modal: Bảng công chi tiết NV -->
    <UiModal
      v-model="showDetailModal"
      :title="detailTitle"
      full-width
    >
      <div v-if="detailLoading" class="py-12 text-center text-slate-400">Đang tải chi tiết...</div>
      <div v-else-if="employeeDetail">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div class="flex flex-wrap gap-4 text-sm">
            <span>Công: <b>{{ fmt(employeeDetail.summary?.work_days) }}</b>/{{ fmt(employeeDetail.summary?.standard_work_days) }}</span>
            <span v-if="employeeDetail.summary?.probation_work_days > 0" :style="totalsCellStyle('probation')">{{ phaseShortLabel('probation') }}: <b>{{ fmt(employeeDetail.summary?.probation_work_days) }}</b></span>
            <span :style="totalsCellStyle('official')">{{ phaseShortLabel('official') }}: <b>{{ fmt(employeeDetail.summary?.official_work_days) }}</b></span>
            <span>OT: <b>{{ fmt(employeeDetail.summary?.ot_hours) }}h</b></span>
            <span v-if="summaryHasPhaseSplit(employeeDetail.summary)" :style="totalsCellStyle('probation')">OT TV: <b>{{ summaryPhaseOtHours(employeeDetail.summary, 'probation') }}</b></span>
            <span v-if="summaryHasPhaseSplit(employeeDetail.summary)" :style="totalsCellStyle('official')">OT CT: <b>{{ summaryPhaseOtHours(employeeDetail.summary, 'official') }}</b></span>
          </div>
          <button type="button" class="hcm-btn-secondary text-xs" @click="exportEmployeeDetail">⬇ Xuất Excel chi tiết</button>
        </div>

        <div v-if="detailFocusDate" class="mb-3 rounded-lg bg-primary-50 border border-primary-200 px-3 py-2 text-xs text-primary-900">
          Đang xem ngày <strong>{{ detailFocusDate }}</strong>
        </div>

        <div class="overflow-x-auto max-h-[60vh]">
          <table class="hcm-table w-full text-xs">
            <thead class="sticky top-0 bg-slate-50">
              <tr>
                <th class="text-left">Ngày</th>
                <th>Thứ</th>
                <th>KH</th>
                <th class="text-left">Trạng thái</th>
                <th>Vào</th>
                <th>Ra</th>
                <th>Giờ</th>
                <th>Trễ</th>
                <th class="text-left">Vị trí vào</th>
                <th class="text-left">Vị trí ra</th>
                <th class="text-left">Nguồn</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="day in employeeDetail.daily_rows"
                :key="day.date"
                :id="'detail-day-' + day.date"
                class="hover:bg-slate-50"
                :style="detailFocusDate === day.date ? undefined : detailRowStyle(day)"
                :class="{ 'bg-primary-50/60 ring-1 ring-primary-200': detailFocusDate === day.date }"
              >
                <td class="font-mono whitespace-nowrap">{{ day.date }}</td>
                <td class="text-center">{{ day.weekday_label }}</td>
                <td class="text-center font-semibold">{{ day.symbol }}</td>
                <td class="max-w-[160px]">
                  <span>{{ day.status_label }}</span>
                  <UiBadge v-if="day.employment_phase_label" :variant="phaseBadgeVariant(day.employment_phase)" class="ml-1 text-[9px]">
                    {{ day.employment_phase_label }}
                  </UiBadge>
                </td>
                <td class="text-center font-mono">{{ day.check_in_at || '—' }}</td>
                <td class="text-center font-mono">{{ day.check_out_at || '—' }}</td>
                <td class="text-center">{{ day.work_hours != null ? fmt(day.work_hours) + 'h' : '—' }}</td>
                <td class="text-center text-amber-700">{{ day.late_minutes > 0 ? Math.round(day.late_minutes) + 'p' : '—' }}</td>
                <td class="max-w-[140px] truncate" :title="day.check_in_location?.label">
                  {{ day.check_in_location?.label || '—' }}
                  <span v-if="day.location_status_label" class="block text-[9px] text-slate-500">{{ day.location_status_label }}</span>
                </td>
                <td class="max-w-[140px] truncate" :title="day.check_out_location?.label">
                  {{ day.check_out_location?.label || '—' }}
                </td>
                <td>{{ day.source_label || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="detailPunchesForFocus.length" class="mt-4 border-t border-slate-100 pt-4">
          <h4 class="text-sm font-semibold mb-2">Lịch sử chấm công ngày {{ detailFocusDate }}</h4>
          <ul class="space-y-2 text-xs">
            <li v-for="(p, i) in detailPunchesForFocus" :key="i" class="rounded-lg bg-slate-50 px-3 py-2 flex flex-wrap gap-x-4 gap-y-1">
              <span class="font-semibold">{{ p.punch_type_label }} {{ p.punched_at }}</span>
              <span>{{ p.source_label }}</span>
              <span v-if="p.zone_name">{{ p.zone_name }}<span v-if="p.zone_code"> ({{ p.zone_code }})</span></span>
              <span v-if="p.device_name">Máy: {{ p.device_name }}</span>
              <span v-if="p.latitude" class="text-slate-500">GPS {{ p.latitude }}, {{ p.longitude }}</span>
              <span v-if="!p.is_valid" class="text-red-600">{{ p.validation_message }}</span>
            </li>
          </ul>
        </div>
      </div>
    </UiModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiModal from '../../components/ui/UiModal.vue';
import UiSearchInput from '../../components/ui/UiSearchInput.vue';
import UiOrgScopeFilters from '../../components/ui/UiOrgScopeFilters.vue';
import { useOrgScopeFilters } from '../../composables/useOrgScopeFilters';
import { matchesEmployeeSearch } from '../../composables/useDebouncedSearch';
import { useToast } from '../../composables/useToast';
import { usePermission } from '../../composables/usePermission';
import { useAttendanceDisplay } from '../../composables/useAttendanceDisplay';
import { useAuthStore } from '../../stores/auth';
import AttendanceMonthlyGridTable from '../../components/attendance/AttendanceMonthlyGridTable.vue';
import AttendanceDailyGridTable from '../../components/attendance/AttendanceDailyGridTable.vue';
import AttendancePhasedGridTable from '../../components/attendance/AttendancePhasedGridTable.vue';

const toast = useToast();
const auth = useAuthStore();
const {
  filterBranchId,
  filterDepartmentId,
  branches: orgBranches,
  filteredDepartments,
  showCompanyPicker,
  singleBranchMode,
  toQueryParams,
  loadMeta: loadScopeMeta,
} = useOrgScopeFilters({ includeDepartment: true, autoLoad: false });
const { hasAnyRole } = usePermission();
const isAdmin = computed(() => hasAnyRole(['admin']));
const displayConfig = ref(null);
const {
  config: attendanceDisplay,
  footerLegendItems,
  cellStyle,
  dayHeaderStyle,
  totalsHeaderStyle,
  totalsCellStyle,
  phaseStyle,
  phaseTextStyle,
  phaseBadgeVariant,
  phaseLabel,
  phaseShortLabel,
  cellTitle,
  detailRowStyle,
} = useAttendanceDisplay(displayConfig);

const tabs = [
  { key: 'daily', label: 'Công theo ngày', icon: '📅' },
  { key: 'congLuong', label: 'Công & lương BP', icon: '📑' },
  { key: 'phased', label: 'TV / CT giai đoạn', icon: '📋' },
  { key: 'monthly', label: 'Bảng công tháng', icon: '📊' },
  { key: 'ot', label: 'Báo cáo OT', icon: '⏱️' },
  { key: 'diligence', label: 'Chuyên cần', icon: '⭐' },
  { key: 'leave', label: 'Nghỉ phép', icon: '🏖️' },
  { key: 'termination', label: 'Thôi việc', icon: '🚪' },
  { key: 'devices', label: 'Thiết bị & Import', icon: '📤' },
];

const tab = ref('daily');
const period = ref(new Date().toISOString().slice(0, 7));
const periodStatus = ref(null);
const employeeSearch = ref('');
const branches = ref([]);
const loading = ref(false);
const error = ref('');
const building = ref(false);

const devices = ref([]);
const geofenceZones = ref([]);
const lastDeviceToken = ref('');
const showGeofenceModal = ref(false);
const geofenceForm = ref({});
const savingGeofence = ref(false);
const showQrModal = ref(false);
const qrDisplay = ref(null);
const lastImport = ref('');
const summaries = ref([]);
const monthlyGridReport = ref({ rows: [], layout: {}, period: '', title: 'BẢNG CÔNG' });
const timesheet = ref({ days: [], employees: [], standard_work_days: 0, layout: {}, title: '' });
const otReport = ref({ summary: {}, rows: [] });
const diligenceReport = ref({ summary: {}, rows: [] });
const leaveReport = ref({ summary: {}, rows: [] });
const termReport = ref({ summary: {}, rows: [] });
const phasedReport = ref({ summary: {}, rows: [] });
const congLuongReport = ref({ cong: { rows: [], columns: [] }, luong: { rows: [], columns: [] } });
const showBreakdownModal = ref(false);
const breakdownDetail = ref(null);
const breakdownEmployeeName = ref('');
const showDetailModal = ref(false);
const detailLoading = ref(false);
const employeeDetail = ref(null);
const detailFocusDate = ref('');
const exporting = ref(false);

const detailTitle = computed(() => {
  if (!employeeDetail.value?.employee) return 'Bảng công chi tiết';
  const e = employeeDetail.value.employee;
  return `Bảng công chi tiết — ${e.full_name} (${e.employee_code}) · ${period.value}`;
});

const detailPunchesForFocus = computed(() => {
  if (!employeeDetail.value || !detailFocusDate.value) return [];
  const day = employeeDetail.value.daily_rows?.find((d) => d.date === detailFocusDate.value);
  return day?.punches ?? [];
});

const otGridLabels = {
  day_weekday:        'TC ngày thường ban ngày 150% (S)',
  day_weekend:        'TC ngày nghỉ ban ngày 200% (T)',
  day_holiday:        'TC ngày lễ/Tết ban ngày 300% (W)',
  day_annual_leave:   'TC phép năm ban ngày (U)',
  night_weekday_n1:   'TC đêm ngày thường 200% — không TC ngày (P1)',
  night_weekday_n2:   'TC đêm ngày thường 210% — có TC ngày (P2)',
  night_weekend:      'TC đêm ngày nghỉ 270% (Q)',
  night_paid_holiday: 'TC đêm ngày nghỉ HL 270% (R)',
  night_annual_leave: 'TC đêm phép năm (V)',
  night_holiday:      'TC đêm ngày lễ/Tết 390% (X)',
};

const leaveTypeLabels = {
  annual: 'Phép năm',
  personal: 'Việc riêng',
  wedding: 'Kết hôn',
  maternity: 'Thai sản',
  funeral: 'Tang',
  sick: 'Ốm',
  unauthorized: 'Không phép',
  company: 'Nghỉ cty',
};

function fmt(val) {
  if (val === null || val === undefined) return '0';
  const n = parseFloat(val);
  return Number.isNaN(n) ? '0' : (Number.isInteger(n) ? n.toString() : n.toFixed(2).replace(/\.?0+$/, ''));
}

function money(val) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(val || 0);
}

function sheetHeaderClass(col) {
  const align = col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left';
  const sticky = col.sticky ? 'sticky left-0 z-30 bg-slate-50 border-r border-slate-200' : '';
  return `px-2 py-1.5 align-bottom border-b border-slate-200 ${align} ${sticky}`;
}

function sheetCellClass(col) {
  const align = col.align === 'right' ? 'text-right tabular-nums' : col.align === 'center' ? 'text-center' : 'text-left';
  const sticky = col.sticky ? 'sticky left-0 z-10 bg-white border-r border-slate-100' : '';
  const mono = col.key === 'employee_code' ? 'font-mono' : '';
  return `px-2 py-1.5 whitespace-nowrap ${align} ${sticky} ${mono}`;
}

function formatSheetCell(row, col, sheet) {
  const val = row[col.key];
  if (val === null || val === undefined || val === '') {
    if (col.key === 'travel_support_flag' || col.key === 'travel_eligible') return '-';
    if (sheet === 'luong' && col.numeric && !['stt', 'job_level'].includes(col.key)) return '-';
    return col.numeric ? fmt(0) : '';
  }
  if (sheet === 'luong' && col.numeric && !['stt', 'job_level'].includes(col.key)) {
    const n = parseFloat(val);
    if (Number.isNaN(n) || n === 0) return '-';
    return money(n);
  }
  if (col.numeric) return fmt(val);
  return val;
}

function openBreakdown(summary) {
  breakdownEmployeeName.value = summary.employee?.full_name || summary.employee?.employee_code || summary.full_name || 'NV';
  breakdownDetail.value = summary.attendance_breakdown || null;
  showBreakdownModal.value = true;
}

function openBreakdownFromGrid(row) {
  openBreakdown({
    full_name: row.full_name,
    employee: { full_name: row.full_name, employee_code: row.employee_code, id: row.employee_id },
    attendance_breakdown: row.attendance_breakdown,
  });
}

async function openEmployeeDetail(row, focusDate = '') {
  detailFocusDate.value = focusDate || '';
  showDetailModal.value = true;
  detailLoading.value = true;
  employeeDetail.value = null;
  try {
    const { data } = await api.get('/attendance-reports/employee-detail', {
      params: {
        company_id: auth.companyId,
        period: period.value,
        employee_id: row.employee_id,
      },
    });
    employeeDetail.value = data.data;
    if (focusDate) {
      setTimeout(() => {
        document.getElementById('detail-day-' + focusDate)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }, 100);
    }
  } catch (e) {
    toast.error(e.response?.data?.message || 'Không tải được bảng công chi tiết');
    showDetailModal.value = false;
  } finally {
    detailLoading.value = false;
  }
}

function openEmployeeDetailFromSummary(summary) {
  openEmployeeDetail({
    employee_id: summary.employee_id ?? summary.employee?.id,
    full_name: summary.employee?.full_name,
    employee_code: summary.employee?.employee_code,
  });
}

async function exportCongLuong() {
  exporting.value = true;
  try {
    const response = await api.get('/attendance-reports/export-cong-luong', {
      params: { company_id: auth.companyId, period: period.value },
      responseType: 'blob',
    });
    const url = URL.createObjectURL(response.data);
    const a = document.createElement('a');
    a.href = url;
    a.download = `cong-luong-${period.value}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success('Đã xuất file Excel công-lương');
  } catch (e) {
    toast.error(e.response?.data?.message || 'Xuất Excel thất bại');
  } finally {
    exporting.value = false;
  }
}

async function exportEmployeeDetail() {
  const employeeId = employeeDetail.value?.employee?.id;
  if (!employeeId) return;
  try {
    const response = await api.get('/attendance-reports/export-employee-detail', {
      params: { company_id: auth.companyId, period: period.value, employee_id: employeeId },
      responseType: 'blob',
    });
    const url = URL.createObjectURL(response.data);
    const a = document.createElement('a');
    a.href = url;
    a.download = `cong-chi-tiet-${employeeDetail.value.employee.employee_code}-${period.value}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success('Đã xuất bảng công chi tiết');
  } catch (e) {
    toast.error(e.response?.data?.message || 'Xuất Excel thất bại');
  }
}

function reportParams() {
  const p = { company_id: auth.companyId, period: period.value, ...toQueryParams() };
  if (employeeSearch.value.trim()) p.search = employeeSearch.value.trim();
  return p;
}

function onEmployeeSearch(value) {
  employeeSearch.value = value;
  if (tab.value === 'monthly') {
    reloadTab();
    return;
  }
}

function filterEmployeeRows(rows) {
  const q = employeeSearch.value.trim();
  if (!q) return rows;
  return (rows || []).filter((row) => matchesEmployeeSearch(row, q));
}

const filteredTimesheetEmployees = computed(() => filterEmployeeRows(timesheet.value.employees));
const filteredSummaries = computed(() => filterEmployeeRows(summaries.value));
const filteredOtRows = computed(() => filterEmployeeRows(otReport.value.rows));
const filteredDiligenceRows = computed(() => filterEmployeeRows(diligenceReport.value.rows));
const filteredLeaveRows = computed(() => filterEmployeeRows(leaveReport.value.rows));
const filteredTermRows = computed(() => filterEmployeeRows(termReport.value.rows));
const filteredPhasedRows = computed(() => filterEmployeeRows(phasedReport.value.rows));
const filteredCongLuongCongRows = computed(() => filterEmployeeRows(congLuongReport.value.cong?.rows || []));
const filteredCongLuongLuongRows = computed(() => filterEmployeeRows(congLuongReport.value.luong?.rows || []));
const filteredMonthlyGridRows = computed(() => filterEmployeeRows(monthlyGridReport.value.rows || []));

const monthlyPhaseSplitCount = computed(() =>
  filteredSummaries.value.filter((s) => summaryHasPhaseSplit(s)).length,
);

function summaryHasPhaseSplit(summary) {
  if (!summary) return false;
  if (summary.attendance_breakdown?.meta?.has_phase_split) return true;
  return Number(summary.probation_work_days) > 0 && Number(summary.official_work_days) > 0;
}

function summaryOfficialDays(summary) {
  if (summaryHasPhaseSplit(summary)) return fmt(summary.official_work_days);
  if (Number(summary.probation_work_days) > 0) return fmt(summary.official_work_days);
  if (Number(summary.work_days) > 0) return fmt(summary.work_days);
  return '—';
}

function breakdownHasPhaseSplit(detail) {
  if (!detail) return false;
  if (detail.meta?.has_phase_split) return true;
  return Number(detail.work?.probation_work_days) > 0 && Number(detail.work?.official_work_days) > 0;
}

function phaseSplitOtCell(hours, hasPhaseSplit) {
  if (!hasPhaseSplit) return '—';
  const n = parseFloat(hours);
  if (Number.isNaN(n) || n <= 0) return '—';
  return fmt(n);
}

function summaryPhaseOtHours(summary, phase) {
  if (!summaryHasPhaseSplit(summary)) return '—';
  const totals = summary.attendance_breakdown?.ot_by_phase?.totals;
  const hours = phase === 'probation'
    ? totals?.probation_hours
    : totals?.official_hours;
  const n = parseFloat(hours);
  if (Number.isNaN(n) || n <= 0) return '—';
  return `${fmt(n)}h`;
}

function phaseSplitLeaveCell(days, hasPhaseSplit) {
  if (!hasPhaseSplit) return '—';
  const n = parseFloat(days);
  if (Number.isNaN(n) || n <= 0) return '—';
  return fmt(n);
}

function summaryPhaseLeaveDays(summary, phase, kind) {
  if (!summaryHasPhaseSplit(summary)) return '—';
  const breakdown = summary.attendance_breakdown?.leave_by_phase?.[phase] ?? {};
  const fromBreakdown = kind === 'paid' ? breakdown.paid : breakdown.unpaid;
  if (fromBreakdown != null && parseFloat(fromBreakdown) > 0) {
    return fmt(fromBreakdown);
  }
  const column = kind === 'paid'
    ? (phase === 'probation' ? summary.probation_paid_leave_days : summary.official_paid_leave_days)
    : (phase === 'probation' ? summary.probation_unpaid_leave_days : summary.official_unpaid_leave_days);
  const n = parseFloat(column);
  if (Number.isNaN(n) || n <= 0) return '—';
  return fmt(n);
}

function summaryPhasePaidLeave(summary, phase) {
  return summaryPhaseLeaveDays(summary, phase, 'paid');
}

function summaryPhaseUnpaidLeave(summary, phase) {
  return summaryPhaseLeaveDays(summary, phase, 'unpaid');
}

async function loadMonthlyGrid() {
  const [gridRes, summaryRes] = await Promise.all([
    api.get('/attendance-reports/monthly-grid', { params: reportParams() }),
    api.get('/attendance-summaries', { params: { ...reportParams(), limit: 1 } }),
  ]);
  monthlyGridReport.value = gridRes.data.data || { rows: [], layout: {}, period: period.value };
  const payload = summaryRes.data.data || {};
  periodStatus.value = payload.period_status ?? null;
}

async function loadDisplayConfig() {
  const { data } = await api.get('/attendance-display-config');
  displayConfig.value = data.data || null;
}

async function loadTimesheet() {
  const { data } = await api.get('/attendance-reports/timesheet', { params: reportParams() });
  timesheet.value = data.data || { days: [], employees: [], layout: {}, title: '' };
  if (data.data?.display_config) {
    displayConfig.value = data.data.display_config;
  }
}

async function loadOtReport() {
  const { data } = await api.get('/attendance-reports/overtime', { params: reportParams() });
  otReport.value = data.data || { summary: {}, rows: [] };
}

async function loadDiligenceReport() {
  const { data } = await api.get('/attendance-reports/diligence', { params: reportParams() });
  diligenceReport.value = data.data || { summary: {}, rows: [] };
}

async function loadLeaveReport() {
  const { data } = await api.get('/attendance-reports/leave', { params: reportParams() });
  leaveReport.value = data.data || { summary: {}, rows: [] };
}

async function loadPhasedReport() {
  const { data } = await api.get('/attendance-reports/phased-monthly', { params: reportParams() });
  phasedReport.value = data.data || { summary: {}, rows: [] };
}

async function loadCongLuongSheet() {
  const { data } = await api.get('/attendance-reports/cong-luong-sheet', { params: reportParams() });
  congLuongReport.value = data.data || { cong: { rows: [], columns: [] }, luong: { rows: [], columns: [] } };
}

async function loadTermReport() {
  const { data } = await api.get('/attendance-reports/terminations', { params: reportParams() });
  termReport.value = data.data || { summary: {}, rows: [] };
}

async function reloadTab() {
  if (tab.value === 'devices') return;
  loading.value = true;
  error.value = '';
  try {
    if (tab.value === 'daily') await loadTimesheet();
    else if (tab.value === 'congLuong') await loadCongLuongSheet();
    else if (tab.value === 'phased') await loadPhasedReport();
    else if (tab.value === 'monthly') await loadMonthlyGrid();
    else if (tab.value === 'ot') await loadOtReport();
    else if (tab.value === 'diligence') await loadDiligenceReport();
    else if (tab.value === 'leave') await loadLeaveReport();
    else if (tab.value === 'termination') await loadTermReport();
  } catch (e) {
    error.value = e.response?.data?.message || 'Không tải được dữ liệu chấm công';
  } finally {
    loading.value = false;
  }
}

function switchTab(key) {
  tab.value = key;
  if (key === 'devices') {
    reloadGeofence();
    return;
  }
  reloadTab();
}

function gradeVariant(g) {
  if (g === 'Xuất sắc' || g === 'Tốt') return 'success';
  if (g === 'Trung bình') return 'warning';
  return 'danger';
}

function statusVariant(s) {
  if (s === 'approved') return 'success';
  if (s === 'pending') return 'warning';
  return 'default';
}

function statusLabel(s) {
  return { approved: 'Đã duyệt', pending: 'Chờ duyệt', rejected: 'Từ chối' }[s] || s;
}

async function buildSummary() {
  building.value = true;
  try {
    await api.post('/attendance-summaries/build', reportParams());
    toast.show('Đã tổng hợp công tháng');
    if (tab.value === 'monthly' || tab.value === 'congLuong' || tab.value === 'phased' || tab.value === 'diligence' || tab.value === 'ot') await reloadTab();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi tổng hợp công', 'error');
  } finally {
    building.value = false;
  }
}

async function lockSummary() {
  if (!confirm(`Khóa công tháng ${period.value}? Sau khi khóa không thể sửa trừ khi admin mở khóa.`)) return;
  await api.post('/attendance-summaries/lock', reportParams());
  toast.show('Đã khóa công tháng');
  await loadMonthlyGrid();
}

async function unlockSummary() {
  const reason = prompt('Lý do mở khóa (admin):');
  if (reason === null) return;
  await api.post('/attendance-summaries/unlock', { ...reportParams(), reason });
  toast.show('Đã mở khóa công');
  await loadMonthlyGrid();
}

async function reloadGeofence() {
  const { data } = await api.get('/attendance-geofence-zones');
  geofenceZones.value = data.data || [];
}

function openGeofenceModal(zone = null) {
  geofenceForm.value = zone
    ? { ...zone, is_active: zone.is_active !== false }
    : {
        company_id: auth.companyId,
        code: '',
        name: '',
        zone_type: 'factory',
        latitude: '',
        longitude: '',
        radius_meters: 200,
        branch_id: null,
        is_active: true,
        address_note: '',
        allowed_sources: ['mobile', 'device', 'kiosk', 'qr'],
      };
  showGeofenceModal.value = true;
}

function useMyLocationForGeofence() {
  if (!navigator.geolocation) {
    toast.show('Trình duyệt không hỗ trợ GPS', 'error');
    return;
  }
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      geofenceForm.value.latitude = Number(pos.coords.latitude.toFixed(6));
      geofenceForm.value.longitude = Number(pos.coords.longitude.toFixed(6));
      toast.show('Đã lấy tọa độ hiện tại');
    },
    () => toast.show('Không lấy được GPS. Kiểm tra quyền định vị.', 'error'),
    { enableHighAccuracy: true, timeout: 15000 },
  );
}

async function saveGeofence() {
  savingGeofence.value = true;
  try {
    const payload = {
      ...geofenceForm.value,
      company_id: auth.companyId,
      code: String(geofenceForm.value.code || '').toUpperCase(),
      branch_id: geofenceForm.value.branch_id || null,
    };
    if (payload.id) {
      await api.put(`/attendance-geofence-zones/${payload.id}`, payload);
      toast.show('Đã cập nhật vùng geofence');
    } else {
      await api.post('/attendance-geofence-zones', payload);
      toast.show('Đã thêm vùng geofence');
    }
    showGeofenceModal.value = false;
    await reloadGeofence();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không lưu được vùng', 'error');
  } finally {
    savingGeofence.value = false;
  }
}

async function issueGateQr(zone) {
  try {
    const { data } = await api.post(`/attendance-geofence-zones/${zone.id}/issue-gate-token`);
    const QRCode = (await import('qrcode')).default;
    const dataUrl = await QRCode.toDataURL(data.data.qr_payload, { width: 260, margin: 2 });
    qrDisplay.value = {
      zoneName: zone.name,
      gate_token: data.data.gate_token,
      qr_payload: data.data.qr_payload,
      dataUrl,
    };
    showQrModal.value = true;
    await reloadGeofence();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không tạo được QR', 'error');
  }
}

function deviceTypeLabel(type) {
  return { import: 'Import CSV', terminal: 'Máy terminal', kiosk: 'Kiosk' }[type] || type || 'Import CSV';
}

async function issueDeviceToken(deviceId) {
  try {
    const { data } = await api.post(`/attendance-devices/${deviceId}/issue-token`);
    lastDeviceToken.value = data.data.api_token;
    toast.show(data.data.message || 'Đã cấp token');
    const devRes = await api.get('/attendance-devices');
    devices.value = devRes.data.data || [];
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không cấp được token', 'error');
  }
}

async function importFile(deviceId, ev) {
  const file = ev.target.files?.[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('file', file);
  const { data } = await api.post(`/attendance-devices/${deviceId}/import`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  lastImport.value = `Import: ${data.data.imported} dòng, bỏ qua ${data.data.skipped}`;
  toast.show('Import chấm công xong');
  ev.target.value = '';
  if (tab.value === 'daily') await reloadTab();
}

watch(() => auth.companyId, async () => {
  await loadDisplayConfig();
  reloadTab();
});

onMounted(async () => {
  const [devRes, branchRes, displayRes] = await Promise.all([
    api.get('/attendance-devices'),
    api.get('/branches'),
    api.get('/attendance-display-config'),
  ]);
  devices.value = devRes.data.data || [];
  branches.value = branchRes.data.data || [];
  displayConfig.value = displayRes.data.data || null;
  await loadScopeMeta();
  await reloadGeofence();
  await reloadTab();
});
</script>
