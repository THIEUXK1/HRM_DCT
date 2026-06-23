<template>
  <div class="space-y-6">
    <UiPageHeader title="Cổng nhân viên (ESS)" subtitle="Tự phục vụ — xem và cập nhật thông tin của bạn" breadcrumb="ESS" />

    <div class="hcm-card overflow-hidden">
      <div class="flex flex-wrap border-b bg-slate-50">
        <button
          v-for="t in tabs"
          :key="t.key"
          type="button"
          class="px-4 py-3 text-sm font-medium border-b-2 transition-all -mb-px whitespace-nowrap"
          :class="activeTab === t.key ? 'border-primary-600 text-primary-600 bg-white' : 'border-transparent text-slate-500 hover:text-slate-700'"
          @click="switchTab(t.key)"
        >
          <span class="mr-1.5">{{ t.icon }}</span>{{ t.label }}
        </button>
      </div>

      <div class="p-6">
        <div v-if="loading" class="py-12 text-center text-slate-400">Đang tải hồ sơ...</div>
        <div v-else-if="profileError" class="py-12">
          <UiEmpty
            title="Không mở được Cổng nhân viên"
            :subtitle="profileError"
          />
          <p class="text-center text-sm text-slate-500 mt-4 max-w-md mx-auto">
            Tài khoản cần được liên kết với hồ sơ nhân viên. HR vào <b>Nhân sự → Users</b> hoặc cập nhật <code class="text-xs bg-slate-100 px-1 rounded">employee_id</code> cho user.
          </p>
        </div>
        <div v-else-if="tabLoading" class="py-12 text-center text-slate-400">Đang tải...</div>
        <div v-else>

          <!-- ──── TAB: HỒ SƠ ──── -->
          <div v-if="activeTab === 'profile'" class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <UiSearchInput
                v-model="profileSearch"
                placeholder="Tìm theo họ tên, mã NV, CCCD, BHXH, địa chỉ..."
                input-class="hcm-input w-full sm:w-96"
                :hint="profileSearchHint"
              />
              <p v-if="profileSearch.trim()" class="text-xs text-slate-500">
                {{ filteredProfileMatchCount }} kết quả
              </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div
                class="bg-gradient-to-br from-primary-50 to-primary-100/50 p-6 rounded-2xl flex flex-col items-center border border-primary-100 transition-shadow"
                :class="{ 'ring-2 ring-primary-400 shadow-md': profileIdentityMatch }"
              >
                <div class="h-20 w-20 rounded-full bg-primary-600 text-white flex items-center justify-center text-2xl font-bold shadow-lg mb-3">
                  {{ profile.full_name?.charAt(0) }}
                </div>
                <h3 class="text-lg font-bold text-slate-800 text-center">{{ profile.full_name }}</h3>
                <p class="text-xs font-mono bg-primary-200/50 text-primary-800 px-2 py-0.5 rounded-full mt-1.5 font-semibold">{{ profile.employee_code }}</p>
                <div class="mt-4 space-y-1.5 text-xs text-slate-600 w-full border-t pt-4 border-primary-200/50">
                  <p>📂 {{ profile.department?.name || '—' }}</p>
                  <p>💼 {{ profile.position?.name || '—' }}</p>
                  <p>📅 Vào làm: {{ formatDate(profile.hire_date) }}</p>
                  <p>🏢 {{ profile.company?.name || '—' }}</p>
                </div>
              </div>

              <div class="md:col-span-2 space-y-5">
                <section v-if="showEditableContactSection" class="space-y-4">
                  <h4 class="font-semibold text-slate-800 border-b pb-2">Liên hệ có thể cập nhật</h4>
                  <form class="space-y-4" @submit.prevent="updateProfile">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div>
                        <label class="hcm-label">Số điện thoại</label>
                        <input v-model="profileForm.phone" class="hcm-input" type="text" />
                      </div>
                      <div>
                        <label class="hcm-label">Email cá nhân</label>
                        <input v-model="profileForm.personal_email" class="hcm-input" type="email" />
                      </div>
                    </div>
                    <div>
                      <label class="hcm-label">Địa chỉ</label>
                      <input v-model="profileForm.address" class="hcm-input" type="text" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                      <div>
                        <label class="hcm-label">Tỉnh/Thành phố</label>
                        <input v-model="profileForm.city" class="hcm-input" type="text" />
                      </div>
                      <div>
                        <label class="hcm-label">Liên hệ khẩn cấp</label>
                        <input v-model="profileForm.emergency_contact_phone" class="hcm-input" type="text" placeholder="SĐT" />
                      </div>
                    </div>
                    <div class="flex justify-end">
                      <button type="submit" class="hcm-btn-primary" :disabled="saving">{{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}</button>
                    </div>
                  </form>
                </section>

                <section
                  v-for="section in filteredReadonlySections"
                  :key="section.id"
                  class="space-y-3"
                >
                  <h4 class="font-semibold text-slate-800 border-b pb-2">{{ section.title }}</h4>
                  <div v-if="section.id === 'dependents'" class="overflow-x-auto">
                    <table v-if="filteredDependents.length" class="w-full text-sm">
                      <thead class="bg-slate-50">
                        <tr>
                          <th class="text-left px-3 py-2 text-xs font-medium text-slate-500">Họ tên</th>
                          <th class="text-left px-3 py-2 text-xs font-medium text-slate-500">Quan hệ</th>
                          <th class="text-left px-3 py-2 text-xs font-medium text-slate-500">Ngày sinh</th>
                          <th class="text-left px-3 py-2 text-xs font-medium text-slate-500">CCCD</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">
                        <tr v-for="d in filteredDependents" :key="d.id">
                          <td class="px-3 py-2 font-medium">{{ d.full_name }}</td>
                          <td class="px-3 py-2">{{ dependentRelationshipLabel(d.relationship) }}</td>
                          <td class="px-3 py-2">{{ formatDate(d.date_of_birth) }}</td>
                          <td class="px-3 py-2 font-mono text-xs">{{ d.id_card_number || '—' }}</td>
                        </tr>
                      </tbody>
                    </table>
                    <p v-else class="text-sm text-slate-400 py-4 text-center">Không có người phụ thuộc phù hợp</p>
                  </div>
                  <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm bg-slate-50 p-4 rounded-xl border">
                    <div v-for="f in section.fields" :key="f.key">
                      <p class="text-xs text-slate-400 uppercase font-semibold">{{ f.label }}</p>
                      <p class="font-medium mt-0.5 break-words">{{ f.display }}</p>
                    </div>
                  </div>
                </section>

                <UiEmpty
                  v-if="profileSearch.trim() && !showEditableContactSection && !filteredReadonlySections.length"
                  title="Không tìm thấy trường phù hợp"
                  subtitle="Thử: họ tên, mã NV (EMP-…), CCCD, BHXH, người phụ thuộc..."
                />
              </div>
            </div>
          </div>

          <!-- ──── TAB: BẢNG CÔNG ──── -->
          <div v-if="activeTab === 'attendance'">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <p class="text-sm text-slate-600">Tổng hợp công theo tháng</p>
              <RouterLink :to="{ name: 'attendance-punch' }" class="hcm-btn-primary text-sm">📍 Chấm công GPS hôm nay</RouterLink>
            </div>
            <div v-if="attendance.length === 0" class="text-center py-10 text-slate-400">Chưa có dữ liệu chấm công</div>
            <div v-else class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Kỳ</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-slate-500 uppercase">Ngày công</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-slate-500 uppercase">Nghỉ phép</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-slate-500 uppercase">Tăng ca (h)</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-slate-500 uppercase">Đi muộn (ph)</th>
                    <th class="px-4 py-3 text-xs font-medium text-slate-500 uppercase">Trạng thái</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="a in attendance" :key="a.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium">{{ a.period }}</td>
                    <td class="px-4 py-3 text-right">{{ a.work_days }}</td>
                    <td class="px-4 py-3 text-right text-amber-600">{{ a.leave_days }}</td>
                    <td class="px-4 py-3 text-right text-blue-600">{{ a.ot_hours }}</td>
                    <td class="px-4 py-3 text-right" :class="a.late_minutes > 0 ? 'text-red-500' : 'text-slate-400'">{{ a.late_minutes }}</td>
                    <td class="px-4 py-3">
                      <span class="text-xs px-2 py-0.5 rounded-full" :class="a.is_locked ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
                        {{ a.is_locked ? 'Đã khóa' : 'Chưa khóa' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- ──── TAB: NGHỈ PHÉP ──── -->
          <div v-if="activeTab === 'leave'">
            <!-- Leave balance -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
              <div class="p-4 rounded-xl border text-center" :class="leaveBalance.remaining > 0 ? 'bg-green-50 border-green-200' : 'bg-slate-50'">
                <p class="text-2xl font-bold text-green-600">{{ leaveBalance.remaining }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Ngày phép còn lại</p>
              </div>
              <div class="p-4 rounded-xl border bg-slate-50 text-center">
                <p class="text-2xl font-bold text-slate-700">{{ leaveBalance.total }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Tổng phép năm nay</p>
              </div>
              <div class="p-4 rounded-xl border bg-amber-50 border-amber-200 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ leaveBalance.used }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Đã sử dụng</p>
              </div>
              <div class="p-4 rounded-xl border bg-blue-50 border-blue-200 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ leavePending }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Chờ duyệt</p>
              </div>
            </div>
            <p v-if="leaveBalance.groupLabel || leaveBalance.override" class="text-xs text-slate-500 mb-3">
              <span v-if="leaveBalance.groupLabel">Nhóm: {{ leaveBalance.groupLabel }}</span>
              <span v-if="leaveBalance.override"> · Ghi đè cá nhân: {{ leaveBalance.override }} ngày/năm</span>
            </p>
            <div class="flex justify-between items-center mb-3">
              <h4 class="font-medium text-slate-700">Lịch sử đơn nghỉ</h4>
              <RouterLink :to="{ name: 'leave' }" class="hcm-btn-primary text-xs">+ Tạo đơn nghỉ</RouterLink>
            </div>
            <div v-if="leaves.length === 0" class="text-center py-8 text-slate-400">Chưa có đơn nghỉ nào</div>
            <div v-else class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="text-left px-4 py-2 text-xs font-medium text-slate-500">Loại nghỉ</th>
                    <th class="text-left px-4 py-2 text-xs font-medium text-slate-500">Từ — Đến</th>
                    <th class="text-right px-4 py-2 text-xs font-medium text-slate-500">Số ngày</th>
                    <th class="px-4 py-2 text-xs font-medium text-slate-500">Trạng thái</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="l in leaves" :key="l.id" class="hover:bg-slate-50">
                    <td class="px-4 py-2">{{ l.leave_type?.name || '—' }}</td>
                    <td class="px-4 py-2 text-slate-500">{{ l.start_date }} — {{ l.end_date }}</td>
                    <td class="px-4 py-2 text-right font-medium">{{ l.total_days }}</td>
                    <td class="px-4 py-2">
                      <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                        :class="l.status==='approved'?'bg-green-100 text-green-700':l.status==='rejected'?'bg-red-100 text-red-700':'bg-amber-100 text-amber-700'">
                        {{ l.status==='approved'?'Đã duyệt':l.status==='rejected'?'Từ chối':'Chờ duyệt' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- ──── TAB: PHIẾU LƯƠNG ──── -->
          <div v-if="activeTab === 'payslips'">
            <div v-if="payslips.length === 0" class="text-center py-10 text-slate-400">Chưa có phiếu lương</div>
            <div v-else class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Kỳ lương</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-slate-500 uppercase">Thực nhận</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-slate-500 uppercase">Thuế TNCN</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-slate-500 uppercase">BHXH NLĐ</th>
                    <th class="px-4 py-3"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="p in payslips" :key="p.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3"><p class="font-medium">{{ p.cycle?.name }}</p><p class="text-xs text-slate-400 font-mono">{{ p.cycle?.period }}</p></td>
                    <td class="px-4 py-3 text-right font-bold text-green-700">{{ formatMoney(p.net_salary) }}</td>
                    <td class="px-4 py-3 text-right text-red-500">{{ formatMoney(p.pit_amount) }}</td>
                    <td class="px-4 py-3 text-right text-red-500">{{ formatMoney(p.bhxh_employee) }}</td>
                    <td class="px-4 py-3 text-right">
                      <button class="text-xs text-primary-600 hover:underline" @click="viewPayslip(p)">Xem 📄</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- ──── TAB: HỢP ĐỒNG ──── -->
          <div v-if="activeTab === 'contracts'">
            <div v-if="contracts.length === 0" class="text-center py-10 text-slate-400">Chưa có hợp đồng lao động</div>
            <div v-else class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Số HĐ</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Loại</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Hiệu lực</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-slate-500 uppercase">Lương</th>
                    <th class="px-4 py-3 text-xs font-medium text-slate-500 uppercase">Trạng thái</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="c in contracts" :key="c.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs text-primary-700">{{ c.contract_number }}</td>
                    <td class="px-4 py-3">{{ c.contract_type }}</td>
                    <td class="px-4 py-3 text-slate-500 text-xs">{{ c.start_date }} → {{ c.end_date || '∞' }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ formatMoney(c.salary_base) }}</td>
                    <td class="px-4 py-3">
                      <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="c.status==='active'?'bg-green-100 text-green-700':'bg-slate-100 text-slate-600'">
                        {{ c.status==='active'?'Hiệu lực':c.status }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- ──── TAB: KPI CỦA TÔI ──── -->
          <div v-if="activeTab === 'kpi'">
            <div v-if="!myKpi?.cycle" class="text-center py-12 text-slate-400">
              <p class="text-2xl mb-2">📈</p>
              <p>Chưa có dữ liệu KPI hoặc chưa được giao mục tiêu</p>
              <RouterLink :to="{ name: 'performance' }" class="mt-2 inline-block text-sm text-primary-600 hover:underline">Xem trang KPI →</RouterLink>
            </div>
            <div v-else>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                <div class="hcm-card p-4 text-center">
                  <p class="text-xs text-slate-400 mb-1">Chu kỳ</p>
                  <p class="font-semibold text-slate-800 text-sm">{{ myKpi.cycle?.name }}</p>
                </div>
                <div class="hcm-card p-4 text-center">
                  <p class="text-xs text-slate-400 mb-1">Điểm KPI</p>
                  <p class="text-2xl font-bold text-primary-700">{{ myKpi.kpi_score ?? '—' }}</p>
                </div>
                <div class="hcm-card p-4 text-center">
                  <p class="text-xs text-slate-400 mb-1">Xếp loại</p>
                  <p class="text-2xl font-bold" :class="ratingColor(myKpi.review?.rating)">{{ myKpi.review?.rating || '—' }}</p>
                </div>
                <div class="hcm-card p-4 text-center">
                  <p class="text-xs text-slate-400 mb-1">Trạng thái</p>
                  <p class="text-sm font-semibold text-slate-700">{{ myKpi.review?.status || 'Chờ đánh giá' }}</p>
                </div>
              </div>
              <div class="hcm-card overflow-hidden">
                <table class="w-full text-sm">
                  <thead class="bg-slate-50">
                    <tr>
                      <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Mục tiêu</th>
                      <th class="text-right px-4 py-3 text-xs font-medium text-slate-500 uppercase">Mục tiêu</th>
                      <th class="text-right px-4 py-3 text-xs font-medium text-slate-500 uppercase">Thực tế</th>
                      <th class="text-right px-4 py-3 text-xs font-medium text-slate-500 uppercase">Trọng số</th>
                      <th class="px-4 py-3 text-xs font-medium text-slate-500 uppercase">Trạng thái</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr v-for="g in myKpi.goals" :key="g.id" class="hover:bg-slate-50">
                      <td class="px-4 py-3 font-medium text-slate-800">{{ g.title }}</td>
                      <td class="px-4 py-3 text-right text-slate-600">{{ g.target_value }}</td>
                      <td class="px-4 py-3 text-right font-semibold" :class="g.actual_value >= g.target_value ? 'text-green-600' : 'text-amber-600'">{{ g.actual_value ?? '—' }}</td>
                      <td class="px-4 py-3 text-right text-slate-500">{{ g.weight }}%</td>
                      <td class="px-4 py-3">
                        <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ g.status }}</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- ──── TAB: XIN NGHỈ VIỆC ──── -->
          <div v-if="activeTab === 'resignation'" class="space-y-6 max-w-2xl">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
              <p class="font-semibold">Lưu ý theo BLLĐ 2019</p>
              <p class="mt-1 text-amber-800">
                Thông báo trước tối thiểu khoảng <strong>{{ resignationMeta.notice_days_hint }}</strong> ngày (tùy loại HĐ).
                Sau khi HR duyệt, hệ thống cập nhật trạng thái nghỉ việc và checklist bàn giao tại phòng Nhân sự.
              </p>
            </div>

            <form
              v-if="resignationMeta.can_submit"
              class="hcm-card p-5 space-y-4"
              @submit.prevent="submitResignation"
            >
              <h4 class="font-semibold text-slate-800">Gửi đơn xin nghỉ việc</h4>
              <div>
                <label class="hcm-label">Ngày nghỉ dự kiến (ngày làm việc cuối) *</label>
                <input v-model="resignationForm.termination_date" type="date" class="hcm-input mt-1 w-full" required />
              </div>
              <div>
                <label class="hcm-label">Số ngày báo trước (tuỳ chọn)</label>
                <input v-model.number="resignationForm.notice_period_days" type="number" min="0" max="90" class="hcm-input mt-1 w-full" />
              </div>
              <div>
                <label class="hcm-label">Lý do xin nghỉ *</label>
                <textarea v-model="resignationForm.reason" class="hcm-input mt-1 w-full" rows="4" required placeholder="Mô tả ngắn gọn lý do (tối thiểu 20 ký tự)..." />
              </div>
              <div>
                <label class="hcm-label">Ghi chú bàn giao (tuỳ chọn)</label>
                <textarea v-model="resignationForm.handover_note" class="hcm-input mt-1 w-full" rows="2" placeholder="Công việc đang làm, tài liệu cần bàn giao..." />
              </div>
              <div class="flex justify-end">
                <button type="submit" class="hcm-btn-primary" :disabled="resignationSaving">
                  {{ resignationSaving ? 'Đang gửi...' : 'Gửi đơn xin nghỉ' }}
                </button>
              </div>
            </form>

            <div v-else-if="hasPendingResignation" class="hcm-card p-5 border-amber-200 bg-amber-50/50">
              <p class="font-medium text-amber-900">Bạn có đơn xin nghỉ đang chờ duyệt</p>
              <p class="text-sm text-amber-800 mt-1">HR sẽ xem xét và phản hồi qua thông báo trên hệ thống.</p>
            </div>

            <div v-else-if="profile.employment_status === 'terminated'" class="hcm-card p-5 text-slate-600 text-sm">
              Tài khoản đã ở trạng thái nghỉ việc.
            </div>

            <div>
              <h4 class="font-medium text-slate-700 mb-3">Lịch sử đơn xin nghỉ</h4>
              <div v-if="resignationRequests.length === 0" class="text-center py-8 text-slate-400 text-sm">Chưa có đơn nào</div>
              <div v-else class="space-y-3">
                <div
                  v-for="r in resignationRequests"
                  :key="r.id"
                  class="hcm-card p-4 flex flex-wrap items-start justify-between gap-3"
                >
                  <div>
                    <p class="font-mono text-xs text-slate-500">{{ r.decision_number }}</p>
                    <p class="text-sm mt-1">Ngày nghỉ dự kiến: <strong>{{ r.termination_date }}</strong></p>
                    <p class="text-sm text-slate-600 mt-1 line-clamp-2">{{ r.reason }}</p>
                    <p v-if="r.rejection_reason" class="text-xs text-red-600 mt-2">Lý do từ chối: {{ r.rejection_reason }}</p>
                  </div>
                  <div class="flex flex-col items-end gap-2">
                    <span
                      class="text-xs px-2 py-0.5 rounded-full font-medium"
                      :class="resignationStatusClass(r.status)"
                    >
                      {{ resignationStatusLabel(r.status) }}
                    </span>
                    <button
                      v-if="r.status === 'pending'"
                      type="button"
                      class="text-xs text-red-600 hover:underline"
                      @click="cancelResignation(r)"
                    >
                      Hủy đơn
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ──── TAB: TÀI LIỆU ──── -->
          <div v-if="activeTab === 'docs'">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <a v-for="doc in internalDocs" :key="doc.title"
                class="hcm-card p-4 flex items-start gap-3 hover:border-primary-300 transition-colors cursor-pointer"
                @click="doc.link ? $router.push(doc.link) : null"
              >
                <span class="text-2xl flex-shrink-0">{{ doc.icon }}</span>
                <div>
                  <p class="font-medium text-slate-800">{{ doc.title }}</p>
                  <p class="text-xs text-slate-400 mt-0.5">{{ doc.desc }}</p>
                </div>
              </a>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Payslip modal -->
    <div v-if="showPayslipModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="showPayslipModal=false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b">
          <h3 class="font-semibold text-slate-800">Phiếu lương chi tiết</h3>
          <button @click="showPayslipModal=false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <div class="flex-1 overflow-y-auto p-4" v-html="selectedPayslipHtml"></div>
        <div class="p-4 border-t flex justify-end gap-2">
          <button class="hcm-btn-secondary" @click="showPayslipModal=false">Đóng</button>
          <button class="hcm-btn-primary" @click="printPayslip">🖨️ In phiếu lương</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiSearchInput from '../../components/ui/UiSearchInput.vue';
import { useFormat } from '../../composables/useFormat';
import { useToast } from '../../composables/useToast';
import { matchesEmployeeSearch } from '../../composables/useDebouncedSearch';
import { useAuthStore } from '../../stores/auth';

const { formatDate, formatMoney } = useFormat();
const toast = useToast();
const auth = useAuthStore();

const loading = ref(true);
const profileError = ref('');
const tabLoading = ref(false);
const saving = ref(false);
const activeTab = ref('profile');
const loadedTabs = ref(new Set());

const profile = ref({});
const profileForm = ref({ phone: '', address: '', city: '', personal_email: '', emergency_contact_phone: '' });
const contracts = ref([]);
const payslips = ref([]);
const leaves = ref([]);
const leaveBalanceApi = ref(null);
const attendance = ref([]);
const myKpi = ref(null);
const resignationRequests = ref([]);
const resignationMeta = ref({ can_submit: true, notice_days_hint: 30 });
const resignationForm = ref({
  termination_date: '',
  notice_period_days: null,
  reason: '',
  handover_note: '',
});
const resignationSaving = ref(false);

const showPayslipModal = ref(false);
const selectedPayslipHtml = ref('');
const profileSearch = ref('');

const editableContactKeywords = ['điện thoại', 'phone', 'email', 'địa chỉ', 'address', 'tỉnh', 'thành phố', 'city', 'liên hệ', 'khẩn cấp', 'cập nhật'];

const genderLabels = { male: 'Nam', female: 'Nữ', other: 'Khác' };
const employmentTypeLabels = {
  full_time: 'Toàn thời gian',
  part_time: 'Bán thời gian',
  intern: 'Thực tập',
  contractor: 'Cộng tác viên',
};
const employmentStatusLabels = {
  active: 'Đang làm việc',
  probation: 'Thử việc',
  on_leave: 'Nghỉ dài hạn',
  terminated: 'Đã nghỉ',
};
const dependentRelationshipLabels = {
  child: 'Con',
  spouse: 'Vợ/Chồng',
  parent: 'Cha/Mẹ',
  sibling: 'Anh/Chị/Em',
  other: 'Khác',
};

const tabs = [
  { key: 'profile', label: 'Hồ sơ', icon: '👤' },
  { key: 'attendance', label: 'Bảng công', icon: '🕐' },
  { key: 'leave', label: 'Nghỉ phép', icon: '🏖️' },
  { key: 'payslips', label: 'Phiếu lương', icon: '💵' },
  { key: 'contracts', label: 'Hợp đồng', icon: '📄' },
  { key: 'resignation', label: 'Xin nghỉ việc', icon: '🚪' },
  { key: 'kpi', label: 'KPI của tôi', icon: '📈' },
  { key: 'docs', label: 'Tài liệu', icon: '📚' },
];

const leaveBalance = computed(() => {
  if (leaveBalanceApi.value) {
    const b = leaveBalanceApi.value;
    return {
      total: b.annual_days,
      used: b.used_days,
      remaining: b.remaining_days,
      groupLabel: b.group?.name || null,
      override: b.annual_leave_days_override,
    };
  }
  const approved = leaves.value.filter((l) => l.status === 'approved');
  const used = approved.reduce((s, l) => s + Number(l.total_days || 1), 0);
  const total = 12;
  return { total, used, remaining: Math.max(0, total - used), groupLabel: null, override: null };
});

const leavePending = computed(() => leaves.value.filter((l) => l.status === 'pending').length);

const hasPendingResignation = computed(() =>
  resignationRequests.value.some((r) => r.status === 'pending'),
);

function displayValue(val) {
  if (val === null || val === undefined || val === '') return '—';
  if (typeof val === 'boolean') return val ? 'Có' : 'Không';
  return String(val);
}

function profileField(key, label, value, extraKeywords = '') {
  const display = displayValue(value);
  return {
    key,
    label,
    display,
    searchText: `${label} ${display} ${extraKeywords}`.toLowerCase(),
  };
}

function dependentRelationshipLabel(code) {
  return dependentRelationshipLabels[code] || code || '—';
}

const readonlyProfileSections = computed(() => {
  const e = profile.value || {};
  const p = e.profile || {};

  return [
    {
      id: 'personal',
      title: 'Thông tin cá nhân',
      fields: [
        profileField('full_name', 'Họ và tên', e.full_name, 'họ tên nhân viên'),
        profileField('employee_code', 'Mã NV', e.employee_code, 'mã nhân viên msnv'),
        profileField('gender', 'Giới tính', genderLabels[e.gender] || e.gender),
        profileField('dob', 'Ngày sinh', formatDate(e.date_of_birth)),
        profileField('place_of_birth', 'Nơi sinh', e.place_of_birth),
        profileField('origin_place', 'Quê quán', e.origin_place),
        profileField('ethnicity', 'Dân tộc', e.ethnicity),
        profileField('religion', 'Tôn giáo', e.religion),
        profileField('nationality', 'Quốc tịch', e.nationality),
        profileField('work_email', 'Email công ty', e.email || e.work_email),
      ],
    },
    {
      id: 'identity',
      title: 'CCCD · BHXH · Thuế',
      fields: [
        profileField('national_id', 'Số CCCD/CMND', e.national_id, 'cccd cmnd'),
        profileField('id_card_issue_date', 'Ngày cấp CCCD', formatDate(e.id_card_issue_date)),
        profileField('id_card_issue_place', 'Nơi cấp', e.id_card_issue_place),
        profileField('tax_code', 'Mã số thuế', e.tax_code, 'mst thuế tncn'),
        profileField('social_insurance_number', 'Mã BHXH', e.social_insurance_number, 'bhxh'),
        profileField('health_insurance_card', 'Thẻ BHYT', e.health_insurance_card, 'bhyt'),
        profileField('bhxh_start_date', 'Ngày tham gia BHXH', formatDate(e.bhxh_start_date)),
        profileField('insurance_salary', 'Mức lương đóng BHXH', e.insurance_salary ? formatMoney(e.insurance_salary) : null),
        profileField('pit_dependents_count', 'Số NPT (GTGC)', e.pit_dependents_count, 'phụ thuộc giảm trừ'),
        profileField('union_member', 'Công đoàn', e.union_member),
      ],
    },
    {
      id: 'address',
      title: 'Địa chỉ',
      fields: [
        profileField('permanent_address', 'Hộ khẩu thường trú', e.permanent_address, 'hktt'),
        profileField('temporary_address', 'Tạm trú', e.temporary_address),
        profileField('address', 'Địa chỉ liên hệ', e.address),
        profileField('ward', 'Phường/Xã', e.ward),
        profileField('district', 'Quận/Huyện', e.district),
        profileField('province', 'Tỉnh/TP', e.province || e.city),
      ],
    },
    {
      id: 'work',
      title: 'Thông tin lao động',
      fields: [
        profileField('branch', 'Chi nhánh', e.branch?.name),
        profileField('employment_type', 'Loại hình', employmentTypeLabels[e.employment_type] || e.employment_type),
        profileField('employment_status', 'Trạng thái', employmentStatusLabels[e.employment_status] || e.employment_status),
        profileField('work_location', 'Nơi làm việc', e.work_location),
        profileField('probation_end_date', 'Hết thử việc', formatDate(e.probation_end_date), 'thử việc tv'),
        profileField('official_start_date', 'Ngày chính thức', formatDate(e.official_start_date), 'chính thức ct'),
        profileField('bank_account', 'Tài khoản ngân hàng', e.bank_account, 'ngân hàng'),
        profileField('bank_account_name', 'Chủ tài khoản', e.bank_account_name),
        profileField('bank_name', 'Ngân hàng', e.bank_name),
        profileField('bank_branch', 'Chi nhánh NH', e.bank_branch),
      ],
    },
    {
      id: 'education',
      title: 'Học vấn · Liên hệ khẩn',
      fields: [
        profileField('marital_status', 'Tình trạng hôn nhân', p.marital_status),
        profileField('education_level', 'Trình độ', p.education_level),
        profileField('education_institution', 'Trường', p.education_institution),
        profileField('graduation_year', 'Năm tốt nghiệp', p.graduation_year),
        profileField('major', 'Chuyên ngành', p.major),
        profileField('professional_certificate', 'Chứng chỉ', p.professional_certificate),
        profileField('emergency_contact_name', 'Liên hệ khẩn', p.emergency_contact_name),
        profileField('emergency_contact_phone', 'SĐT khẩn', p.emergency_contact_phone),
        profileField('emergency_contact_relationship', 'Quan hệ khẩn', p.emergency_contact_relationship),
        profileField('spouse_name', 'Vợ/Chồng', p.spouse_name),
        profileField('passport_number', 'Hộ chiếu', p.passport_number),
      ],
    },
    {
      id: 'dependents',
      title: 'Người phụ thuộc (GTGC)',
      fields: [],
    },
  ];
});

function matchesProfileSearch(text, query) {
  if (!query) return true;
  return text.toLowerCase().includes(query);
}

const profileIdentityMatch = computed(() => {
  const query = profileSearch.value.trim();
  if (!query) return false;
  return matchesEmployeeSearch(profile.value, query);
});

const filteredReadonlySections = computed(() => {
  const query = profileSearch.value.trim().toLowerCase();
  if (!query || profileIdentityMatch.value) return readonlyProfileSections.value;

  return readonlyProfileSections.value
    .map((section) => {
      if (section.id === 'dependents') {
        return filteredDependents.value.length || matchesProfileSearch(section.title, query) ? section : null;
      }
      const fields = section.fields.filter(
        (f) => matchesProfileSearch(f.searchText, query) || matchesProfileSearch(section.title, query),
      );
      if (!fields.length) return null;
      return { ...section, fields };
    })
    .filter(Boolean);
});

const filteredDependents = computed(() => {
  const list = profile.value?.dependents || [];
  const query = profileSearch.value.trim().toLowerCase();
  if (!query || profileIdentityMatch.value) return list;
  return list.filter((d) => matchesProfileSearch(
    `${d.full_name} ${d.relationship} ${d.id_card_number} ${dependentRelationshipLabel(d.relationship)} ${formatDate(d.date_of_birth)} người phụ thuộc gtgc npt`,
    query,
  ));
});

const showEditableContactSection = computed(() => {
  const query = profileSearch.value.trim().toLowerCase();
  if (!query) return true;
  if (profileIdentityMatch.value) return true;
  return editableContactKeywords.some((kw) => kw.includes(query) || query.includes(kw))
    || matchesProfileSearch('liên hệ có thể cập nhật', query);
});

const filteredProfileMatchCount = computed(() => {
  const query = profileSearch.value.trim().toLowerCase();
  if (!query) return 0;
  if (profileIdentityMatch.value) {
    let count = 2; // họ tên + mã NV trên thẻ NV
    readonlyProfileSections.value.forEach((section) => {
      if (section.id === 'dependents') count += (profile.value?.dependents || []).length;
      else count += section.fields.length;
    });
    return count + 1; // form liên hệ
  }
  let count = showEditableContactSection.value ? 1 : 0;
  filteredReadonlySections.value.forEach((section) => {
    if (section.id === 'dependents') count += filteredDependents.value.length;
    else count += section.fields.length;
  });
  return count;
});

const profileSearchHint = computed(() => (profileSearch.value.trim() ? '' : 'Họ tên, mã NV, tên trường hoặc giá trị'));

const internalDocs = [
  { icon: '📋', title: 'Quy chế lao động', desc: 'Nội quy, quy trình làm việc', link: null },
  { icon: '💰', title: 'Chính sách lương thưởng', desc: 'Cấu trúc lương, thưởng hiệu suất', link: null },
  { icon: '🏖️', title: 'Chính sách nghỉ phép', desc: 'Phép năm, nghỉ lễ, nghỉ bù', link: { name: 'leave' } },
  { icon: '📚', title: 'Tài liệu đào tạo', desc: 'Khóa học, chứng chỉ', link: { name: 'training' } },
  { icon: '🎯', title: 'Hướng dẫn KPI', desc: 'Cách đặt mục tiêu và đánh giá', link: { name: 'performance' } },
  { icon: '🏥', title: 'Phúc lợi & BHXH', desc: 'Bảo hiểm, khám sức khỏe', link: { name: 'bhxh' } },
];

function ratingColor(r) {
  return { A: 'text-green-600', B: 'text-blue-600', C: 'text-slate-700', D: 'text-amber-600', E: 'text-red-600' }[r] || 'text-slate-400';
}

async function loadProfile() {
  loading.value = true;
  profileError.value = '';
  try {
    const { data } = await api.get('/self-service/profile');
    profile.value = data.data;
    profileForm.value = {
      phone: profile.value.phone || '',
      address: profile.value.address || '',
      city: profile.value.city || '',
      personal_email: profile.value.personal_email || '',
      emergency_contact_phone: profile.value.profile?.emergency_contact_phone || '',
    };
    loadedTabs.value.add('profile');
  } catch (e) {
    profileError.value = e.response?.data?.message || 'Không tải được hồ sơ nhân viên.';
  } finally {
    loading.value = false;
  }
}

async function loadTabData(tab) {
  if (loadedTabs.value.has(tab) || tab === 'profile' || tab === 'docs') return;
  tabLoading.value = true;
  try {
    if (tab === 'contracts') {
      const { data } = await api.get('/self-service/contracts');
      contracts.value = data.data;
    } else if (tab === 'payslips') {
      const { data } = await api.get('/self-service/payslips');
      payslips.value = data.data;
    } else if (tab === 'leave') {
      const [reqRes, balRes] = await Promise.all([
        api.get('/self-service/leave-requests'),
        api.get('/self-service/leave-balance'),
      ]);
      leaves.value = reqRes.data.data;
      leaveBalanceApi.value = balRes.data.data;
    } else if (tab === 'attendance') {
      const { data } = await api.get('/self-service/attendance-summary');
      attendance.value = data.data;
    } else if (tab === 'kpi') {
      const { data } = await api.get('/self-service/my-kpi');
      myKpi.value = data.data;
    } else if (tab === 'resignation') {
      await loadResignationData();
    }
    loadedTabs.value.add(tab);
  } catch {
    toast.show('Không tải được dữ liệu tab', 'error');
  } finally {
    tabLoading.value = false;
  }
}

function switchTab(key) {
  activeTab.value = key;
  loadTabData(key);
}

onMounted(loadProfile);

async function updateProfile() {
  saving.value = true;
  try {
    const { data } = await api.put('/self-service/profile', profileForm.value);
    profile.value = data.data;
    profileForm.value = {
      phone: profile.value.phone || '',
      address: profile.value.address || '',
      city: profile.value.city || '',
      personal_email: profile.value.personal_email || '',
      emergency_contact_phone: profile.value.profile?.emergency_contact_phone || '',
    };
    toast.show('Đã cập nhật thành công!');
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi khi cập nhật', 'error');
  } finally {
    saving.value = false;
  }
}

async function viewPayslip(payslip) {
  try {
    const response = await api.get(`/payroll-results/${payslip.id}/payslip`);
    selectedPayslipHtml.value = response.data;
    showPayslipModal.value = true;
  } catch {
    toast.show('Không thể tải phiếu lương', 'error');
  }
}

function printPayslip() {
  const w = window.open('', '_blank');
  w.document.write(`<html><head><title>Phiếu lương</title></head><body>${selectedPayslipHtml.value}<script>window.print();<\/script></body></html>`);
  w.document.close();
}

async function loadResignationData() {
  const { data } = await api.get('/self-service/resignation-requests');
  resignationRequests.value = data.data?.requests ?? [];
  resignationMeta.value = {
    can_submit: data.data?.can_submit ?? false,
    notice_days_hint: data.data?.notice_days_hint ?? 30,
  };
  if (!resignationForm.value.termination_date) {
    const d = new Date();
    d.setDate(d.getDate() + (resignationMeta.value.notice_days_hint || 30));
    resignationForm.value.termination_date = d.toISOString().slice(0, 10);
  }
}

function resignationStatusLabel(status) {
  return {
    pending: 'Chờ duyệt',
    approved: 'Đã duyệt',
    completed: 'Hoàn tất',
    rejected: 'Từ chối',
  }[status] || status;
}

function resignationStatusClass(status) {
  return {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
  }[status] || 'bg-slate-100 text-slate-600';
}

async function submitResignation() {
  resignationSaving.value = true;
  try {
    const payload = { ...resignationForm.value };
    if (!payload.notice_period_days) delete payload.notice_period_days;
    if (!payload.handover_note) delete payload.handover_note;
    await api.post('/self-service/resignation-requests', payload);
    toast.show('Đã gửi đơn xin nghỉ việc. HR sẽ xem xét và phản hồi.');
    resignationForm.value.reason = '';
    resignationForm.value.handover_note = '';
    await loadResignationData();
    loadedTabs.value.add('resignation');
  } catch (e) {
    const errs = e.response?.data?.errors;
    const first = errs ? Object.values(errs)?.[0]?.[0] : null;
    toast.show(first || e.response?.data?.message || 'Không gửi được đơn', 'error');
  } finally {
    resignationSaving.value = false;
  }
}

async function cancelResignation(item) {
  if (!confirm('Hủy đơn xin nghỉ đang chờ duyệt?')) return;
  try {
    await api.post(`/self-service/resignation-requests/${item.id}/cancel`);
    toast.show('Đã hủy đơn xin nghỉ');
    await loadResignationData();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không hủy được đơn', 'error');
  }
}
</script>
