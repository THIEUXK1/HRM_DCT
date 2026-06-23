<template>
  <div>
    <UiPageHeader title="Đơn phép & Tăng ca" subtitle="Tạo đơn · Duyệt · Đẩy sang bảng công" breadcrumb="Attendance">
      <template #actions>
        <button v-if="tab === 'leave'" type="button" class="hcm-btn-primary" @click="showForm = true">+ Tạo đơn nghỉ</button>
        <button v-else-if="tab === 'correction'" type="button" class="hcm-btn-primary" @click="showCorrectionForm = true">+ Đơn bù thẻ</button>
        <button v-else-if="tab === 'ot'" type="button" class="hcm-btn-primary" @click="showOtForm = true">+ Đăng ký tăng ca</button>
      </template>
    </UiPageHeader>

    <!-- Tab navigation -->
    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-all"
        :class="tab === 'leave' ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="tab = 'leave'"
      >
        🏖️ Nghỉ phép
      </button>
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-all"
        :class="tab === 'ot' ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="tab = 'ot'"
      >
        ⏱️ Làm thêm giờ (OT)
      </button>
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-all"
        :class="tab === 'correction' ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="tab = 'correction'; loadCorrections()"
      >
        🪪 Bù thẻ chấm công
      </button>
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-all"
        :class="tab === 'balance' ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="tab = 'balance'; loadBalance()"
      >
        📊 Quỹ phép toàn công ty
      </button>
      <button
        v-if="canManageLeave"
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-all"
        :class="tab === 'entitlement' ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="tab = 'entitlement'; loadEntitlementGroups()"
      >
        ⚙️ Nhóm quỹ phép
      </button>
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-all"
        :class="tab === 'calendar' ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="tab = 'calendar'"
      >
        📅 Lịch nghỉ
      </button>
    </div>

    <div v-if="['leave', 'ot', 'correction'].includes(tab)" class="hcm-card mb-4 p-4 space-y-4">
      <UiOrgScopeFilters
        :show-company-picker="scope.showCompanyPicker"
        :single-branch-mode="scope.singleBranchMode"
        v-model:filter-branch-id="scope.filterBranchId"
        v-model:filter-department-id="scope.filterDepartmentId"
        :branches="scope.branches"
        :filtered-departments="scope.filteredDepartments"
        @change="onScopeChange"
        @reset="resetScopeFilters"
      />
      <UiSearchInput
        v-model="listSearch"
        placeholder="Tìm theo tên hoặc mã nhân viên..."
        @search="onListSearch"
      />
    </div>

    <!-- Tab 1: Nghỉ phép -->
    <div v-if="tab === 'leave'" class="hcm-card overflow-hidden">
      <table class="hcm-table w-full" v-if="leaves.length">
        <thead>
          <tr>
            <th>Nhân viên</th>
            <th>Loại nghỉ</th>
            <th>Hưởng lương</th>
            <th>Từ — Đến</th>
            <th>Số ngày</th>
            <th>Trạng thái</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="l in leaves" :key="l.id" class="hover:bg-slate-50">
            <td class="font-medium text-slate-900">{{ l.employee?.full_name || 'NV #' + l.employee_id }}</td>
            <td>{{ l.leave_type?.name || '—' }}</td>
            <td>
              <UiBadge v-if="l.leave_type" :variant="l.leave_type.is_paid ? 'success' : 'warning'" class="mr-1">
                {{ l.leave_type.is_paid ? 'Có lương' : 'Không lương' }}
              </UiBadge>
            </td>
            <td>{{ date(l.start_date) }} — {{ date(l.end_date) }}</td>
            <td>{{ l.total_days }}</td>
            <td>
              <UiBadge :variant="l.status === 'approved' ? 'success' : l.status === 'pending' ? 'warning' : 'default'">
                {{ statusLabel(l.status) }}
              </UiBadge>
            </td>
            <td>
              <button v-if="l.status === 'pending'" type="button" class="text-sm text-primary-600 font-medium hover:underline" @click="approve(l.id)">
                Duyệt
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <UiEmpty v-else title="Chưa có đơn nghỉ" />
    </div>

    <!-- Tab: Bù thẻ -->
    <div v-else-if="tab === 'correction'" class="hcm-card overflow-hidden">
      <table class="hcm-table w-full" v-if="corrections.length">
        <thead>
          <tr>
            <th>Nhân viên</th>
            <th>Ngày công</th>
            <th>Lý do</th>
            <th>Giờ đề xuất</th>
            <th>Trạng thái</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in corrections" :key="c.id" class="hover:bg-slate-50">
            <td class="font-medium">{{ c.employee?.full_name || 'NV #' + c.employee_id }}</td>
            <td>{{ date(c.work_date) }}</td>
            <td>
              {{ c.reason?.name || '—' }}
              <UiBadge v-if="c.reason?.counts_as_forgot_punch" variant="warning" class="ml-1">Quên chấm</UiBadge>
            </td>
            <td class="text-xs font-mono">
              {{ formatCorrectionTime(c.requested_check_in_at) }} – {{ formatCorrectionTime(c.requested_check_out_at) }}
            </td>
            <td>
              <UiBadge :variant="c.status === 'approved' ? 'success' : c.status === 'pending' ? 'warning' : 'default'">
                {{ statusLabel(c.status) }}
              </UiBadge>
            </td>
            <td class="space-x-2">
              <button v-if="c.status === 'pending'" type="button" class="text-sm text-primary-600 font-medium hover:underline" @click="approveCorrection(c.id)">Duyệt</button>
              <button v-if="c.status === 'pending'" type="button" class="text-sm text-red-600 hover:underline" @click="rejectCorrection(c.id)">Từ chối</button>
            </td>
          </tr>
        </tbody>
      </table>
      <UiEmpty v-else title="Chưa có đơn bù thẻ" />
    </div>

    <!-- Tab 2: Làm thêm giờ -->
    <template v-else-if="tab === 'ot'">
      <UiComplianceAlertPanel
        v-if="complianceAlerts.length"
        :items="complianceAlerts"
        title="Cảnh báo tăng ca (OT)"
        subtitle="Giới hạn: 4h/ngày · 40h/tháng · 200h/năm (Điều 107 BLLĐ 2019). Từ 200–300h/năm cần thông báo Sở LĐ-TB&XH."
        :categories="otAlertCategories"
      />
      <div class="hcm-card overflow-hidden">
      <table class="hcm-table w-full" v-if="overtimes.length">
        <thead>
          <tr>
            <th>Nhân viên</th>
            <th>Ngày làm việc</th>
            <th>Số giờ</th>
            <th>Lý do</th>
            <th>Trạng thái</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in overtimes" :key="o.id" class="hover:bg-slate-50">
            <td class="font-medium text-slate-900">{{ o.employee?.full_name || 'NV #' + o.employee_id }}</td>
            <td>{{ date(o.work_date) }}</td>
            <td class="font-semibold text-slate-800">{{ o.hours }}h</td>
            <td class="text-sm text-slate-600">{{ o.reason || '—' }}</td>
            <td>
              <UiBadge :variant="o.status === 'approved' ? 'success' : o.status === 'pending' ? 'warning' : 'default'">
                {{ statusLabel(o.status) }}
              </UiBadge>
            </td>
            <td>
              <button v-if="o.status === 'pending'" type="button" class="text-sm text-primary-600 font-medium hover:underline" @click="approveOt(o.id)">
                Duyệt
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <UiEmpty v-else title="Chưa có đơn làm thêm giờ" />
      </div>
    </template>

    <!-- Tab 3: Quỹ phép -->
    <div v-if="tab === 'balance'">
      <div class="mb-4 flex flex-wrap gap-3 items-end">
        <div>
          <label class="text-xs text-slate-500 block mb-1">Lọc phòng ban</label>
          <select v-model="balanceFilter" class="hcm-input max-w-xs" @change="loadBalance">
            <option value="">Tất cả phòng ban</option>
            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div>
          <label class="text-xs text-slate-500 block mb-1">Năm</label>
          <select v-model="balanceYear" class="hcm-input" @change="loadBalance">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
      </div>
      <div v-if="loadingBalance" class="text-center py-10 text-slate-400">Đang tải...</div>
      <div v-else-if="leaveBalances.length === 0" class="text-center py-10">
        <UiEmpty title="Chưa có nhân viên phù hợp" />
      </div>
      <div v-else class="hcm-card overflow-hidden">
        <table class="hcm-table w-full">
          <thead>
            <tr>
              <th>Nhân viên</th>
              <th>Phòng ban</th>
              <th class="text-center">Hưởng (ngày/năm)</th>
              <th class="text-center">Đã dùng</th>
              <th class="text-center">Còn lại</th>
              <th>Nhóm / nguồn</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in leaveBalances" :key="row.employee_id" class="hover:bg-slate-50">
              <td class="font-medium text-slate-800">{{ row.full_name }}</td>
              <td class="text-slate-500 text-xs">{{ row.department || '—' }}</td>
              <td class="text-center font-semibold">{{ row.annual_days }}</td>
              <td class="text-center text-amber-700">{{ row.used_days }}</td>
              <td class="text-center font-bold" :class="row.remaining_days > 0 ? 'text-green-700' : 'text-red-600'">
                {{ row.remaining_days }}
              </td>
              <td class="text-xs text-slate-500">
                {{ row.group?.name || 'Chính sách công ty' }}
                <span v-if="row.annual_leave_days_override" class="text-primary-600"> · Ghi đè {{ row.annual_leave_days_override }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="text-xs text-slate-400 mt-2">* Chỉ trừ phép năm (mã PHEP/PN) đã duyệt. Cấu hình nhóm tại tab «Nhóm quỹ phép» hoặc hồ sơ NV.</p>
    </div>

    <!-- Tab: Nhóm quỹ phép -->
    <div v-if="tab === 'entitlement' && canManageLeave">
      <div class="mb-4 flex justify-between items-center">
        <p class="text-sm text-slate-600">Thiết lập 12 ngày tiêu chuẩn hoặc cao hơn (vd. 14 ngày) theo nhóm lao động; gán cho NV hoặc phòng ban.</p>
        <button type="button" class="hcm-btn-primary text-sm" @click="openGroupForm()">+ Thêm nhóm</button>
      </div>
      <div v-if="loadingGroups" class="text-center py-10 text-slate-400">Đang tải...</div>
      <div v-else class="hcm-card overflow-hidden">
        <table class="hcm-table w-full">
          <thead>
            <tr>
              <th>Mã</th>
              <th>Tên nhóm</th>
              <th class="text-center">Ngày phép/năm</th>
              <th class="text-center">NV gán</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in entitlementGroups" :key="g.id" class="hover:bg-slate-50">
              <td class="font-mono text-xs">{{ g.code }}</td>
              <td>
                <span class="font-medium">{{ g.name }}</span>
                <UiBadge v-if="g.is_default" variant="info" class="ml-2">Mặc định</UiBadge>
              </td>
              <td class="text-center font-bold">{{ g.annual_days }}</td>
              <td class="text-center text-slate-500">{{ g.employees_count ?? 0 }}</td>
              <td class="text-right space-x-2">
                <button type="button" class="text-xs text-primary-600 hover:underline" @click="openGroupForm(g)">Sửa</button>
                <button
                  v-if="!g.is_default"
                  type="button"
                  class="text-xs text-red-600 hover:underline"
                  @click="deleteGroup(g)"
                >Xóa</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <UiModal v-model="showGroupForm" :title="groupForm.id ? 'Sửa nhóm quỹ phép' : 'Thêm nhóm quỹ phép'">
      <form class="space-y-3" @submit.prevent="saveGroup">
        <div>
          <label class="text-sm font-medium">Mã nhóm</label>
          <input v-model="groupForm.code" class="hcm-input mt-1 font-mono" required :disabled="!!groupForm.id" />
        </div>
        <div>
          <label class="text-sm font-medium">Tên hiển thị</label>
          <input v-model="groupForm.name" class="hcm-input mt-1" required />
        </div>
        <div>
          <label class="text-sm font-medium">Số ngày phép năm</label>
          <input v-model.number="groupForm.annual_days" type="number" min="0" max="60" step="0.5" class="hcm-input mt-1 w-32" required />
        </div>
        <div>
          <label class="text-sm font-medium">Mô tả</label>
          <textarea v-model="groupForm.description" class="hcm-input mt-1" rows="2" />
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="groupForm.is_default" type="checkbox" class="rounded" />
          Nhóm mặc định (NV chưa gán nhóm)
        </label>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="hcm-btn-secondary" @click="showGroupForm = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary">Lưu</button>
        </div>
      </form>
    </UiModal>

    <!-- Tab 4: Lịch nghỉ -->
    <div v-if="tab === 'calendar'">
      <div class="mb-4 grid grid-cols-7 gap-1 text-center text-xs text-slate-500 font-medium">
        <div v-for="d in ['T2','T3','T4','T5','T6','T7','CN']" :key="d" class="py-2">{{ d }}</div>
      </div>
      <div class="hcm-card p-5">
        <div class="flex items-center justify-between mb-4">
          <button class="hcm-btn-secondary text-xs" @click="calMonth--; if(calMonth<1){calMonth=12;calYear--}">‹ Tháng trước</button>
          <h3 class="font-semibold text-slate-800">Tháng {{ calMonth }}/{{ calYear }}</h3>
          <button class="hcm-btn-secondary text-xs" @click="calMonth++; if(calMonth>12){calMonth=1;calYear++}">Tháng sau ›</button>
        </div>
        <div class="grid grid-cols-7 gap-1">
          <div v-for="blank in calFirstDay" :key="'b'+blank" class="h-10"></div>
          <div
            v-for="day in calDays"
            :key="day"
            class="h-10 flex flex-col items-center justify-start pt-1 rounded-lg text-xs transition-all"
            :class="getLeaveDayClass(day)"
          >
            <span class="font-medium">{{ day }}</span>
            <span v-if="getLeaveDayCount(day) > 0" class="w-4 h-1.5 rounded-full bg-amber-400 mt-0.5 opacity-90"></span>
          </div>
        </div>
        <div class="mt-4 space-y-1">
          <div v-for="l in calendarLeaves" :key="l.id" class="flex items-center gap-2 text-xs p-2 bg-amber-50 rounded-lg border border-amber-200">
            <span class="font-medium text-amber-800">{{ l.employee?.full_name }}</span>
            <span class="text-slate-500">{{ l.start_date }} → {{ l.end_date }}</span>
            <span class="text-slate-400">{{ l.leave_type?.name }}</span>
          </div>
          <div v-if="calendarLeaves.length === 0" class="text-center text-slate-400 py-2">Không có nghỉ phép trong tháng này</div>
        </div>
      </div>
    </div>

    <!-- Modal Tạo đơn nghỉ phép -->
    <UiModal v-model="showForm" title="Tạo đơn nghỉ phép" wide>
      <form class="space-y-3" @submit.prevent="create">
        <EmployeeTargetPicker
          v-model:mode="leaveTargetMode"
          v-model:employee-id="form.employee_id"
          v-model:employee-ids="leaveEmployeeIds"
          v-model:department-id="leaveDepartmentId"
          :employees="employees"
          :departments="departments"
        />
        <div>
          <label class="text-sm font-medium">Loại nghỉ</label>
          <select v-model="form.leave_type_id" class="hcm-input mt-1" required>
            <option v-for="t in leaveTypes" :key="t.id" :value="t.id">
              {{ t.name }} ({{ t.is_paid ? 'có lương' : 'không lương' }})
            </option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-medium">Từ ngày</label>
            <input v-model="form.start_date" type="date" class="hcm-input mt-1" required />
          </div>
          <div>
            <label class="text-sm font-medium">Đến ngày</label>
            <input v-model="form.end_date" type="date" class="hcm-input mt-1" required />
          </div>
        </div>
        <div>
          <label class="text-sm font-medium">Số ngày</label>
          <input v-model.number="form.total_days" type="number" step="0.5" class="hcm-input mt-1 bg-slate-50" readonly required />
          <p v-if="leaveDayModeLabel" class="text-xs text-slate-500 mt-1">{{ leaveDayModeLabel }}</p>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="hcm-btn-secondary" @click="showForm = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary">Gửi đơn</button>
        </div>
      </form>
    </UiModal>

    <!-- Modal Đăng ký Làm thêm giờ -->
    <UiModal v-model="showOtForm" title="Đăng ký làm thêm giờ (OT)" wide>
      <form class="space-y-3" @submit.prevent="createOt">
        <EmployeeTargetPicker
          v-model:mode="otTargetMode"
          v-model:employee-id="otForm.employee_id"
          v-model:employee-ids="otEmployeeIds"
          v-model:department-id="otDepartmentId"
          :employees="employees"
          :departments="departments"
        />
        <div>
          <label class="text-sm font-medium">Ngày làm việc</label>
          <input v-model="otForm.work_date" type="date" class="hcm-input mt-1" required />
        </div>
        <div>
          <label class="text-sm font-medium">Số giờ tăng ca</label>
          <input v-model.number="otForm.hours" type="number" step="0.5" class="hcm-input mt-1" required />
        </div>
        <div>
          <label class="text-sm font-medium">Lý do tăng ca</label>
          <input v-model="otForm.reason" type="text" class="hcm-input mt-1" placeholder="Ví dụ: Hoàn thành báo cáo tháng" />
        </div>
        <div v-if="otCapPreview" class="rounded-lg border p-3 text-sm space-y-1" :class="otCapPreview.monthly_exceeded || otCapPreview.yearly_exceeded ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-900'">
          <p class="font-semibold">Tình trạng OT đã duyệt</p>
          <p>Tháng: {{ otCapPreview.monthly_used }}h / {{ otCapPreview.monthly_max }}h (còn {{ otCapPreview.monthly_remaining }}h)</p>
          <p>Năm: {{ otCapPreview.yearly_used }}h / {{ otCapPreview.yearly_max }}h (còn {{ otCapPreview.yearly_remaining }}h)</p>
          <p v-if="otCapPreview.monthly_exceeded || otCapPreview.yearly_exceeded" class="text-xs font-medium">⚠️ Đã vượt giới hạn pháp luật — hệ thống vẫn ghi nhận nhưng tách khỏi tính lương nếu cần.</p>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="hcm-btn-secondary" @click="showOtForm = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary">Gửi đơn</button>
        </div>
      </form>
    </UiModal>

    <!-- Modal Bù thẻ -->
    <UiModal v-model="showCorrectionForm" title="Đơn bù thẻ chấm công" wide>
      <form class="space-y-3" @submit.prevent="createCorrection">
        <EmployeeTargetPicker
          v-model:mode="correctionTargetMode"
          v-model:employee-id="correctionForm.employee_id"
          v-model:employee-ids="correctionEmployeeIds"
          v-model:department-id="correctionDepartmentId"
          :employees="employees"
          :departments="departments"
        />
        <div>
          <label class="text-sm font-medium">Ngày công cần bù</label>
          <input v-model="correctionForm.work_date" type="date" class="hcm-input mt-1" required />
        </div>
        <div>
          <label class="text-sm font-medium">Lý do</label>
          <select v-model="correctionForm.correction_reason_id" class="hcm-input mt-1" required>
            <option v-for="r in correctionReasons" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium">Kiểu bù thẻ</label>
          <select v-model="correctionForm.correction_mode" class="hcm-input mt-1" required>
            <option value="both">Bù cả giờ vào và giờ ra</option>
            <option value="check_in">Chỉ bù giờ vào</option>
            <option value="check_out">Chỉ bù giờ ra</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div v-if="correctionForm.correction_mode !== 'check_out'">
            <label class="text-sm font-medium">Giờ vào</label>
            <input v-model="correctionForm.check_in_time" type="time" class="hcm-input mt-1" :required="correctionForm.correction_mode !== 'check_out'" />
          </div>
          <div v-if="correctionForm.correction_mode !== 'check_in'">
            <label class="text-sm font-medium">Giờ ra</label>
            <input v-model="correctionForm.check_out_time" type="time" class="hcm-input mt-1" :required="correctionForm.correction_mode !== 'check_in'" />
          </div>
        </div>
        <div>
          <label class="text-sm font-medium">Ghi chú</label>
          <input v-model="correctionForm.note" type="text" class="hcm-input mt-1" placeholder="Mô tả thêm nếu cần" />
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="hcm-btn-secondary" @click="showCorrectionForm = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary">Gửi đơn</button>
        </div>
      </form>
    </UiModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiSearchInput from '../../components/ui/UiSearchInput.vue';
import UiOrgScopeFilters from '../../components/ui/UiOrgScopeFilters.vue';
import UiComplianceAlertPanel from '../../components/ui/UiComplianceAlertPanel.vue';
import UiModal from '../../components/ui/UiModal.vue';
import EmployeeTargetPicker from '../../components/hr/EmployeeTargetPicker.vue';
import { extractItems } from '../../composables/usePagination';
import { useOrgScopeFilters } from '../../composables/useOrgScopeFilters';
import { useFormat } from '../../composables/useFormat';
import { useToast } from '../../composables/useToast';
import { useAuthStore } from '../../stores/auth';
import { usePermission } from '../../composables/usePermission';

const { date, statusLabel } = useFormat();
const toast = useToast();
const auth = useAuthStore();
const { can } = usePermission();
const canManageLeave = computed(() => can('leave.manage'));
const scope = useOrgScopeFilters({ includeDepartment: true });
const complianceAlerts = ref([]);
const otCapPreview = ref(null);

const otAlertCategories = [
  'ot_monthly_warning',
  'ot_monthly_exceeded',
  'ot_yearly_warning',
  'ot_yearly_exceeded',
  'ot_yearly_notify_authority',
];

const tab = ref('leave');
const listSearch = ref('');
const leaves = ref([]);
const overtimes = ref([]);
const corrections = ref([]);
const correctionReasons = ref([]);
const employees = ref([]);
const leaveTypes = ref([]);
const departments = ref([]);

// Leave balance
const loadingBalance = ref(false);
const leaveBalances = ref([]);
const balanceFilter = ref('');
const entitlementGroups = ref([]);
const loadingGroups = ref(false);
const showGroupForm = ref(false);
const groupForm = ref({
  id: null, code: '', name: '', annual_days: 12, description: '', is_default: false,
});
const balanceYear = ref(new Date().getFullYear());
const years = [new Date().getFullYear(), new Date().getFullYear() - 1];

// Calendar
const calMonth = ref(new Date().getMonth() + 1);
const calYear = ref(new Date().getFullYear());

const calDays = computed(() => {
  return new Date(calYear.value, calMonth.value, 0).getDate();
});
const calFirstDay = computed(() => {
  const d = new Date(calYear.value, calMonth.value - 1, 1).getDay();
  return d === 0 ? 6 : d - 1;
});
const calendarLeaves = computed(() => {
  return leaves.value.filter((l) => {
    if (l.status !== 'approved') return false;
    const s = new Date(l.start_date);
    const e = new Date(l.end_date);
    const m = new Date(calYear.value, calMonth.value - 1, 1);
    const me = new Date(calYear.value, calMonth.value, 0);
    return s <= me && e >= m;
  });
});

function getLeaveDayCount(day) {
  const date = new Date(calYear.value, calMonth.value - 1, day);
  return calendarLeaves.value.filter((l) => {
    const s = new Date(l.start_date);
    const e = new Date(l.end_date);
    return date >= s && date <= e;
  }).length;
}
function getLeaveDayClass(day) {
  const today = new Date();
  const isToday = today.getDate() === day && today.getMonth() + 1 === calMonth.value && today.getFullYear() === calYear.value;
  if (isToday) return 'bg-primary-100 text-primary-700 font-bold';
  if (getLeaveDayCount(day) > 0) return 'bg-amber-50 border border-amber-200 text-amber-800';
  return '';
}

async function loadBalance() {
  loadingBalance.value = true;
  try {
    const params = { year: balanceYear.value, ...scope.toQueryParams() };
    if (balanceFilter.value) params.department_id = balanceFilter.value;
    const { data } = await api.get('/leave-balances', { params });
    leaveBalances.value = data.data?.items || [];
  } finally {
    loadingBalance.value = false;
  }
}

async function loadEntitlementGroups() {
  loadingGroups.value = true;
  try {
    const { data } = await api.get('/leave-entitlement-groups');
    entitlementGroups.value = data.data || [];
  } finally {
    loadingGroups.value = false;
  }
}

function openGroupForm(group = null) {
  if (group) {
    groupForm.value = {
      id: group.id,
      code: group.code,
      name: group.name,
      annual_days: group.annual_days,
      description: group.description || '',
      is_default: !!group.is_default,
    };
  } else {
    groupForm.value = {
      id: null, code: '', name: '', annual_days: 14, description: '', is_default: false,
    };
  }
  showGroupForm.value = true;
}

async function saveGroup() {
  const payload = {
    code: groupForm.value.code,
    name: groupForm.value.name,
    annual_days: groupForm.value.annual_days,
    description: groupForm.value.description || null,
    is_default: groupForm.value.is_default,
  };
  try {
    if (groupForm.value.id) {
      await api.put(`/leave-entitlement-groups/${groupForm.value.id}`, payload);
      toast.show('Đã cập nhật nhóm quỹ phép');
    } else {
      await api.post('/leave-entitlement-groups', payload);
      toast.show('Đã tạo nhóm quỹ phép');
    }
    showGroupForm.value = false;
    await loadEntitlementGroups();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lưu thất bại', 'error');
  }
}

async function deleteGroup(group) {
  if (!confirm(`Xóa nhóm «${group.name}»?`)) return;
  try {
    await api.delete(`/leave-entitlement-groups/${group.id}`);
    toast.show('Đã xóa nhóm');
    await loadEntitlementGroups();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không thể xóa nhóm đang được sử dụng', 'error');
  }
}

const showForm = ref(false);
const showOtForm = ref(false);
const showCorrectionForm = ref(false);

const leaveTargetMode = ref('single');
const leaveEmployeeIds = ref([]);
const leaveDepartmentId = ref('');
const otTargetMode = ref('single');
const otEmployeeIds = ref([]);
const otDepartmentId = ref('');
const correctionTargetMode = ref('single');
const correctionEmployeeIds = ref([]);
const correctionDepartmentId = ref('');

const form = ref({
  company_id: null,
  employee_id: null,
  leave_type_id: null,
  start_date: '',
  end_date: '',
  total_days: 1,
});
const leaveDayModeLabel = ref('');
const calculatingDays = ref(false);

const otForm = ref({
  company_id: null,
  employee_id: null,
  work_date: '',
  hours: 1,
  reason: '',
});

async function recalcLeaveDays() {
  if (!form.value.leave_type_id || !form.value.start_date || !form.value.end_date) return;
  calculatingDays.value = true;
  try {
    const { data } = await api.get('/leave-requests/calculate-days', {
      params: {
        leave_type_id: form.value.leave_type_id,
        start_date: form.value.start_date,
        end_date: form.value.end_date,
      },
    });
    form.value.total_days = data.data.total_days;
    leaveDayModeLabel.value = data.data.mode_label || '';
  } catch {
    leaveDayModeLabel.value = '';
  } finally {
    calculatingDays.value = false;
  }
}

async function loadComplianceAlerts() {
  try {
    const { data } = await api.get('/hr-alerts', { params: { limit: 80 } });
    complianceAlerts.value = data.data?.items || [];
  } catch {
    complianceAlerts.value = [];
  }
}

async function refreshOtCapPreview() {
  if (otTargetMode.value !== 'single' || !otForm.value.employee_id || !otForm.value.work_date) {
    otCapPreview.value = null;
    return;
  }
  try {
    const period = otForm.value.work_date.slice(0, 7);
    const { data } = await api.get('/overtime-requests/cap-summary', {
      params: { employee_id: otForm.value.employee_id, period },
    });
    otCapPreview.value = data.data;
  } catch {
    otCapPreview.value = null;
  }
}

watch(
  () => [otForm.value.employee_id, otForm.value.work_date, otTargetMode.value],
  () => refreshOtCapPreview(),
);

watch(tab, (t) => {
  if (t === 'ot') loadComplianceAlerts();
});

watch(
  () => [form.value.leave_type_id, form.value.start_date, form.value.end_date],
  () => recalcLeaveDays(),
);

const correctionForm = ref({
  company_id: null,
  employee_id: null,
  correction_reason_id: null,
  correction_mode: 'both',
  work_date: '',
  check_in_time: '08:30',
  check_out_time: '17:30',
  note: '',
});

function formatCorrectionTime(value) {
  if (!value) return '—';
  return new Date(value).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}

async function loadCorrections() {
  const params = { per_page: 200 };
  if (listSearch.value.trim()) params.search = listSearch.value.trim();
  const { data } = await api.get('/attendance-correction-requests', { params });
  corrections.value = extractItems(data);
}

function listSearchParams() {
  const params = { per_page: 200, ...scope.toQueryParams() };
  if (listSearch.value.trim()) params.search = listSearch.value.trim();
  return params;
}

async function onScopeChange() {
  if (tab.value === 'correction') {
    await loadCorrections();
    return;
  }
  if (['leave', 'ot'].includes(tab.value)) {
    await loadLists();
  }
}

function resetScopeFilters() {
  scope.resetScope();
  listSearch.value = '';
  onScopeChange();
}

async function onListSearch(value) {
  listSearch.value = value;
  if (tab.value === 'correction') {
    await loadCorrections();
    return;
  }
  await loadLists();
}

async function loadLists() {
  const params = listSearchParams();
  const [l, o] = await Promise.all([
    api.get('/leave-requests', { params }),
    api.get('/overtime-requests', { params }),
  ]);
  leaves.value = extractItems(l.data);
  overtimes.value = extractItems(o.data);
}

async function load() {
  const params = listSearchParams();
  const empParams = { per_page: 200, employment_status: 'active', ...scope.toQueryParams() };
  const [l, o, e] = await Promise.all([
    api.get('/leave-requests', { params }),
    api.get('/overtime-requests', { params }),
    api.get('/employees', { params: empParams }),
  ]);
  leaves.value = extractItems(l.data);
  overtimes.value = extractItems(o.data);
  employees.value = extractItems(e.data);

  form.value.company_id = auth.companyId;
  otForm.value.company_id = auth.companyId;

  if (employees.value[0]) {
    form.value.employee_id = employees.value[0].id;
    otForm.value.employee_id = employees.value[0].id;
  }

  const [lt, dept, reasons] = await Promise.all([
    api.get('/leave-types'),
    api.get('/departments'),
    api.get('/attendance-correction-reasons'),
  ]);
  leaveTypes.value = lt.data.data;
  departments.value = dept.data.data;
  correctionReasons.value = (reasons.data.data || []).filter((r) => r.is_active);
  if (leaveTypes.value[0]) form.value.leave_type_id = leaveTypes.value[0].id;
  if (correctionReasons.value[0]) correctionForm.value.correction_reason_id = correctionReasons.value[0].id;
  correctionForm.value.company_id = auth.companyId;
  if (employees.value[0]) correctionForm.value.employee_id = employees.value[0].id;
}

function buildEmployeeTargetPayload(base, mode, employeeId, employeeIds, departmentId) {
  const payload = { ...base };
  delete payload.employee_id;
  delete payload.employee_ids;
  delete payload.department_id;
  if (mode === 'single') {
    payload.employee_id = employeeId;
  } else if (mode === 'department') {
    payload.department_id = departmentId;
  } else {
    payload.employee_ids = employeeIds.map((id) => Number(id));
  }
  return payload;
}

async function create() {
  const payload = buildEmployeeTargetPayload(
    form.value,
    leaveTargetMode.value,
    form.value.employee_id,
    leaveEmployeeIds.value,
    leaveDepartmentId.value,
  );
  const { data } = await api.post('/leave-requests', payload);
  const count = data.data?.created_count ?? 1;
  toast.show(`Đã tạo ${count} đơn nghỉ phép`);
  if (data.data?.errors?.length) {
    toast.show(`${data.data.errors.length} NV không tạo được — xem log API`);
  }
  showForm.value = false;
  await load();
}

async function approve(id) {
  await api.post(`/leave-requests/${id}/approve`);
  toast.show('Đã duyệt đơn nghỉ phép');
  await load();
}

async function createOt() {
  const payload = buildEmployeeTargetPayload(
    otForm.value,
    otTargetMode.value,
    otForm.value.employee_id,
    otEmployeeIds.value,
    otDepartmentId.value,
  );
  const { data } = await api.post('/overtime-requests', payload);
  const count = data.data?.created_count ?? 1;
  toast.show(`Đã tạo ${count} đơn tăng ca`);
  showOtForm.value = false;
  await load();
}

async function approveOt(id) {
  await api.post(`/overtime-requests/${id}/approve`);
  toast.show('Đã duyệt đơn làm thêm giờ');
  await load();
}

async function createCorrection() {
  const f = correctionForm.value;
  const wantsCheckIn = f.correction_mode === 'both' || f.correction_mode === 'check_in';
  const wantsCheckOut = f.correction_mode === 'both' || f.correction_mode === 'check_out';
  const base = {
    company_id: f.company_id,
    correction_reason_id: f.correction_reason_id,
    correction_mode: f.correction_mode,
    work_date: f.work_date,
    requested_check_in_at: wantsCheckIn && f.check_in_time ? `${f.work_date} ${f.check_in_time}:00` : null,
    requested_check_out_at: wantsCheckOut && f.check_out_time ? `${f.work_date} ${f.check_out_time}:00` : null,
    note: f.note,
  };
  const payload = buildEmployeeTargetPayload(
    base,
    correctionTargetMode.value,
    f.employee_id,
    correctionEmployeeIds.value,
    correctionDepartmentId.value,
  );
  const { data } = await api.post('/attendance-correction-requests', payload);
  const count = data.data?.created_count ?? 1;
  toast.show(`Đã gửi ${count} đơn bù thẻ`);
  showCorrectionForm.value = false;
  await loadCorrections();
}

async function approveCorrection(id) {
  await api.post(`/attendance-correction-requests/${id}/approve`);
  toast.show('Đã duyệt bù thẻ — cập nhật log chấm công');
  await loadCorrections();
}

async function rejectCorrection(id) {
  await api.post(`/attendance-correction-requests/${id}/reject`, { rejection_reason: 'Không đủ căn cứ' });
  toast.show('Đã từ chối đơn bù thẻ');
  await loadCorrections();
}

onMounted(async () => {
  await scope.loadMeta();
  await load();
});
</script>
