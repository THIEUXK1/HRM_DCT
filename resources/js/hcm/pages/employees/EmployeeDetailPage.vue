<template>
  <div v-if="loading" class="py-12 text-center text-slate-500">Đang tải hồ sơ...</div>
  <div v-else-if="employee">
    <UiPageHeader :title="employee.full_name" :subtitle="employee.employee_code + ' · ' + (employee.position?.name || '')" breadcrumb="Nhân viên">
      <template #actions>
        <RouterLink :to="{ name: 'employees' }" class="hcm-btn-secondary">← Danh sách</RouterLink>
        <EmployeeCardPrint v-if="employee" :employees="[employee]" />
        <button type="button" class="hcm-btn-primary" :disabled="saving" @click="saveCurrentTab">
          {{ saving ? 'Đang lưu...' : 'Lưu tab hiện tại' }}
        </button>
      </template>
    </UiPageHeader>

    <!-- Overview card -->
    <div class="hcm-card p-4 mb-4 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3 text-sm">
      <div>
        <p class="text-xs text-slate-400 mb-0.5">Công ty</p>
        <p class="font-medium text-slate-800">{{ employee.company?.name || '—' }}</p>
      </div>
      <div>
        <p class="text-xs text-slate-400 mb-0.5">Chi nhánh</p>
        <p class="font-medium text-slate-800">{{ employee.branch?.name || '—' }}</p>
      </div>
      <div>
        <p class="text-xs text-slate-400 mb-0.5">Phòng ban</p>
        <p class="font-medium text-slate-800">{{ employee.department?.name || '—' }}</p>
      </div>
      <div>
        <p class="text-xs text-slate-400 mb-0.5">Chức danh</p>
        <p class="font-medium text-slate-800">{{ employee.position?.name || '—' }}</p>
      </div>
      <div>
        <p class="text-xs text-slate-400 mb-0.5">Trạng thái</p>
        <UiBadge :variant="employee.employment_status === 'active' ? 'success' : employee.employment_status === 'probation' ? 'info' : ['terminated','resigned'].includes(employee.employment_status) ? 'danger' : 'warning'">
          {{ statusLabel(employee.employment_status) }}
        </UiBadge>
      </div>
      <div>
        <p class="text-xs text-slate-400 mb-0.5">Ngày vào làm</p>
        <p class="font-medium text-slate-800">{{ date(employee.hire_date) || '—' }}</p>
      </div>
      <div v-if="employee.termination_date">
        <p class="text-xs text-slate-400 mb-0.5">Ngày nghỉ việc</p>
        <p class="font-medium text-red-600">{{ date(employee.termination_date) }}</p>
      </div>
      <div>
        <p class="text-xs text-slate-400 mb-0.5">Nguồn</p>
        <span v-if="employee.source_company" class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">{{ employee.source_company }}</span>
        <span v-else class="text-slate-400">Thủ công</span>
      </div>
      <div v-if="employee.chinese_name">
        <p class="text-xs text-slate-400 mb-0.5">Tên tiếng Trung</p>
        <p class="font-medium text-slate-800">{{ employee.chinese_name }}</p>
      </div>
      <div v-if="employee.email">
        <p class="text-xs text-slate-400 mb-0.5">Email</p>
        <p class="font-medium text-slate-800 truncate">{{ employee.email }}</p>
      </div>
      <div v-if="employee.phone">
        <p class="text-xs text-slate-400 mb-0.5">Điện thoại</p>
        <p class="font-medium text-slate-800">{{ employee.phone }}</p>
      </div>
      <div v-if="employee.bank_name">
        <p class="text-xs text-slate-400 mb-0.5">Ngân hàng</p>
        <p class="font-medium text-slate-800">{{ employee.bank_name }}</p>
      </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
      <button
        v-for="t in tabs"
        :key="t.id"
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
        :class="activeTab === t.id ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
        @click="activeTab = t.id"
      >
        {{ t.label }}
      </button>
    </div>

    <!-- Cá nhân -->
    <section v-show="activeTab === 'personal'" class="hcm-card p-6 space-y-4">
      <h3 class="font-semibold text-slate-900">Thông tin cá nhân</h3>

      <!-- Ảnh đại diện -->
      <div class="flex items-center gap-5 pb-4 border-b border-slate-100">
        <div class="relative flex-shrink-0">
          <img
            v-if="photoSrc"
            :src="photoSrc"
            class="w-24 h-24 rounded-full object-cover border-2 border-slate-200 bg-slate-100"
            alt="Ảnh nhân viên"
          />
          <div
            v-else
            class="w-24 h-24 rounded-full bg-slate-200 flex items-center justify-center text-3xl font-bold text-slate-400 select-none"
          >
            {{ employee.first_name?.[0]?.toUpperCase() || '?' }}
          </div>
          <label
            class="absolute bottom-0 right-0 w-7 h-7 rounded-full bg-primary-600 text-white flex items-center justify-center cursor-pointer hover:bg-primary-700 shadow"
            title="Tải ảnh lên"
          >
            <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadPhoto" :disabled="photoUploading" />
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
          </label>
        </div>
        <div class="min-w-0">
          <p class="font-semibold text-slate-900">{{ employee.full_name }}</p>
          <p class="text-sm text-slate-500 font-mono">{{ employee.employee_code }}</p>
          <p class="text-xs text-slate-400 mt-1">
            {{ photoUploading ? 'Đang tải ảnh...' : 'Ảnh chân dung · dùng in thẻ nhân viên (JPG/PNG, tối đa 5 MB)' }}
          </p>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Field label="Họ và tên" v-model="form.full_name" class="sm:col-span-2" />
        <Field label="Tên tiếng Trung" v-model="form.chinese_name" />
        <Field label="Họ tên gốc (song ngữ)" v-model="form.full_name_raw" class="sm:col-span-2 lg:col-span-3" />
        <Field label="Giới tính" v-model="form.gender" type="select" :options="meta.genders" />
        <Field label="Ngày sinh" v-model="form.date_of_birth" type="date" />
        <Field label="Nơi sinh" v-model="form.place_of_birth" />
        <Field label="Quê quán" v-model="form.origin_place" />
        <Field label="Dân tộc" v-model="form.ethnicity" />
        <Field label="Tôn giáo" v-model="form.religion" />
        <Field label="Quốc tịch" v-model="form.nationality" />
        <Field label="Điện thoại" v-model="form.phone" />
        <Field label="Email cá nhân" v-model="form.personal_email" type="email" />
        <Field label="Email công ty" v-model="form.email" type="email" required />
      </div>
    </section>

    <!-- CCCD & BHXH/Thuế -->
    <section v-show="activeTab === 'identity'" class="hcm-card p-6 space-y-4">
      <h3 class="font-semibold">Định danh · BHXH · Thuế TNCN</h3>
      <p class="text-xs text-slate-500">Theo quy định kê khai BHXH và khấu trừ thuế TNCN tại Việt Nam.</p>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Field label="Loại giấy tờ" v-model="form.id_card_type" type="select" :options="meta.id_card_types" />
        <Field label="Số CCCD/CMND" v-model="form.national_id" hint="9–12 chữ số" />
        <Field label="Ngày cấp" v-model="form.id_card_issue_date" type="date" />
        <Field label="Nơi cấp" v-model="form.id_card_issue_place" class="sm:col-span-2" />
        <Field label="Ngày hết hạn" v-model="form.id_card_expiry_date" type="date" />
        <Field label="Mã số thuế cá nhân" v-model="form.tax_code" hint="10 số hoặc 10-3" />
        <Field label="Mã số BHXH" v-model="form.social_insurance_number" />
        <Field label="Thẻ BHYT" v-model="form.health_insurance_card" />
        <Field label="Ngày tham gia BHXH" v-model="form.bhxh_start_date" type="date" />
        <Field label="Mức lương đóng BHXH (VND)" v-model="form.insurance_salary" type="number" />
        <Field label="Số người phụ thuộc (GTGC)" v-model="form.pit_dependents_count" type="number" />
        <div class="flex items-end">
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.union_member" type="checkbox" class="rounded" />
            Đoàn viên / Công đoàn
          </label>
        </div>
      </div>
    </section>

    <!-- Địa chỉ -->
    <section v-show="activeTab === 'address'" class="hcm-card p-6 space-y-4">
      <h3 class="font-semibold">Địa chỉ (HKTT · Tạm trú · Liên hệ)</h3>
      <div class="grid gap-4 sm:grid-cols-2">
        <Field label="Hộ khẩu thường trú" v-model="form.permanent_address" type="textarea" class="sm:col-span-2" />
        <Field label="Tạm trú" v-model="form.temporary_address" type="textarea" class="sm:col-span-2" />
        <Field label="Địa chỉ liên hệ hiện tại" v-model="form.address" type="textarea" class="sm:col-span-2" />
        <Field label="Phường/Xã" v-model="form.ward" />
        <Field label="Quận/Huyện" v-model="form.district" />
        <Field label="Tỉnh/TP" v-model="form.province" />
      </div>
    </section>

    <!-- Công việc -->
    <section v-show="activeTab === 'work'" class="hcm-card p-6 space-y-4">
      <h3 class="font-semibold">Thông tin lao động</h3>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Field label="Mã nhân viên" v-model="form.employee_code" required />
        <Field label="Nguồn dữ liệu" v-model="form.source_company" type="select" :options="{ BPVN: 'BPVN', PFVN: 'PFVN', MEGA: 'MEGA' }" />
        <Field label="Chi nhánh" v-model="form.branch_id" type="select" :options="branchOptions" />
        <Field label="Phòng ban" v-model="form.department_id" type="select" :options="departmentOptions" />
        <Field label="Chức danh" v-model="form.position_id" type="select" :options="positionOptions" />
        <Field label="Loại hình" v-model="form.employment_type" type="select" :options="meta.employment_types" />
        <Field label="Trạng thái" v-model="form.employment_status" type="select" :options="meta.employment_statuses" />
        <Field label="Nơi làm việc" v-model="form.work_location" />
        <Field label="Ngày vào làm" v-model="form.hire_date" type="date" />
        <Field label="Hết thử việc" v-model="form.probation_end_date" type="date" />
        <Field label="Ngày chính thức" v-model="form.official_start_date" type="date" />
        <Field label="Ngày nghỉ việc" v-model="form.termination_date" type="date" />
        <Field label="Lý do nghỉ" v-model="form.termination_reason" class="sm:col-span-2" />
        <div class="sm:col-span-2 border-t border-slate-100 pt-4 mt-2">
          <h4 class="text-sm font-semibold text-slate-800 mb-2">Quỹ phép năm</h4>
          <div class="grid gap-4 sm:grid-cols-2">
            <Field
              label="Nhóm đối tượng phép"
              v-model="form.leave_entitlement_group_id"
              type="select"
              :options="leaveGroupOptions"
              hint="Ưu tiên hơn mặc định công ty; có thể kế thừa từ phòng ban"
            />
            <Field
              label="Ghi đè số ngày/năm (cá nhân)"
              v-model="form.annual_leave_days_override"
              type="number"
              hint="Để trống = dùng nhóm hoặc chính sách công ty (vd. 12 hoặc 14)"
            />
          </div>
        </div>
        <Field label="TK ngân hàng" v-model="form.bank_account" />
        <Field label="Chủ tài khoản" v-model="form.bank_account_name" />
        <Field label="Ngân hàng" v-model="form.bank_name" />
        <Field label="Chi nhánh NH" v-model="form.bank_branch" />
      </div>
    </section>

    <!-- Học vấn & liên hệ khẩn -->
    <section v-show="activeTab === 'education'" class="hcm-card p-6 space-y-4">
      <h3 class="font-semibold">Học vấn · Liên hệ khẩn · Hồ sơ bổ sung</h3>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Field label="Tình trạng hôn nhân" v-model="profileForm.marital_status" type="select" :options="meta.marital_statuses" />
        <Field label="Trình độ" v-model="profileForm.education_level" type="select" :options="meta.education_levels" />
        <Field label="Trường" v-model="profileForm.education_institution" />
        <Field label="Năm tốt nghiệp" v-model="profileForm.graduation_year" type="number" />
        <Field label="Chuyên ngành" v-model="profileForm.major" />
        <Field label="Chứng chỉ hành nghề" v-model="profileForm.professional_certificate" class="sm:col-span-2" />
        <Field label="Nghĩa vụ quân sự" v-model="profileForm.military_service_status" type="select" :options="meta.military_service_statuses" />
        <Field label="Liên hệ khẩn" v-model="profileForm.emergency_contact_name" />
        <Field label="SĐT khẩn" v-model="profileForm.emergency_contact_phone" />
        <Field label="Quan hệ" v-model="profileForm.emergency_contact_relationship" />
        <Field label="Họ tên vợ/chồng" v-model="profileForm.spouse_name" />
        <Field label="CCCD vợ/chồng" v-model="profileForm.spouse_id_number" />
        <Field label="Hộ chiếu" v-model="profileForm.passport_number" />
        <Field label="Hết hạn HC" v-model="profileForm.passport_expiry" type="date" />
        <Field label="GPLĐ (NN)" v-model="profileForm.work_permit_number" />
        <Field label="Hết hạn GPLĐ" v-model="profileForm.work_permit_expiry" type="date" />
        <Field
          label="Mã vân tay"
          v-model="profileForm.biometric_id"
          placeholder="VD: 1001"
        />
        <Field
          label="Số thẻ RFID (Card number)"
          v-model="profileForm.card_number"
          placeholder="VD: 12345678"
        />
      </div>
    </section>

    <!-- Người phụ thuộc -->
    <section v-show="activeTab === 'dependents'" class="space-y-4">
      <div class="hcm-card p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-semibold">Người phụ thuộc (giảm trừ gia cảnh)</h3>
          <button type="button" class="hcm-btn-secondary text-sm" @click="openDependent()">+ Thêm</button>
        </div>
        <table class="hcm-table w-full" v-if="employee.dependents?.length">
          <thead>
            <tr>
              <th>Họ tên</th>
              <th>Quan hệ</th>
              <th>Ngày sinh</th>
              <th>CCCD</th>
              <th>Mã NPT</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in employee.dependents" :key="d.id">
              <td>{{ d.full_name }}</td>
              <td>{{ labelOf(meta.dependent_relationships, d.relationship) }}</td>
              <td>{{ date(d.date_of_birth) }}</td>
              <td>{{ d.id_card_number || '—' }}</td>
              <td>{{ d.tax_dependent_code || '—' }}</td>
              <td>
                <button type="button" class="text-xs text-primary-600" @click="openDependent(d)">Sửa</button>
                <button type="button" class="text-xs text-red-600 ml-2" @click="removeDependent(d.id)">Xóa</button>
              </td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Chưa khai báo người phụ thuộc" description="Cần cho tính thuế TNCN và báo cáo cơ quan thuế" />
      </div>
    </section>

    <!-- Tài liệu -->
    <section v-show="activeTab === 'documents'" class="hcm-card p-6 space-y-4">
      <div class="flex justify-between items-center">
        <h3 class="font-semibold">Hồ sơ đính kèm (pháp lý)</h3>
        <button type="button" class="hcm-btn-primary text-sm" @click="showDocForm = true">+ Tải lên tài liệu</button>
      </div>
      <table class="hcm-table w-full" v-if="employee.documents?.length">
        <thead>
          <tr>
            <th>Loại</th>
            <th>Số hiệu</th>
            <th>File</th>
            <th>Dung lượng</th>
            <th>Hết hạn</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="doc in employee.documents" :key="doc.id">
            <td>{{ labelOf(meta.document_types, doc.type) }}</td>
            <td>{{ doc.document_number || '—' }}</td>
            <td class="text-sm">{{ doc.file_name }}</td>
            <td class="text-xs text-slate-500">{{ formatSize(doc.file_size) }}</td>
            <td>{{ date(doc.expiry_date) }}</td>
            <td class="space-x-2">
              <button v-if="doc.file_path" type="button" class="text-xs text-primary-600" @click="downloadDocument(doc.id)">Tải</button>
              <button type="button" class="text-xs text-red-600" @click="removeDocument(doc.id)">Xóa</button>
            </td>
          </tr>
        </tbody>
      </table>
      <UiEmpty v-else title="Chưa có tài liệu" description="CCCD, HĐLĐ, sổ BHXH, khám sức khỏe..." />
    </section>

    <!-- Onboarding -->
    <section v-show="activeTab === 'onboarding'" class="hcm-card p-6 space-y-4">
      <div class="flex flex-wrap justify-between gap-3">
        <h3 class="font-semibold">Onboarding</h3>
        <UiBadge v-if="employee.onboarding_completed_at" variant="success">Hoàn tất {{ date(employee.onboarding_completed_at) }}</UiBadge>
        <button
          v-if="onboardingPercent === 100 && !employee.onboarding_completed_at"
          type="button"
          class="hcm-btn-primary text-sm"
          @click="completeOnboarding"
        >
          Xác nhận hoàn tất
        </button>
      </div>
      <ul class="space-y-2">
        <li
          v-for="t in onboardingTasks"
          :key="t.id"
          class="flex flex-wrap justify-between gap-2 border rounded-lg px-3 py-2 text-sm"
        >
          <span>{{ t.task?.title }}</span>
          <select
            :value="t.status"
            class="hcm-input text-xs"
            @change="updateOnboardingTask(t.id, $event.target.value)"
          >
            <option value="pending">Chờ</option>
            <option value="in_progress">Đang làm</option>
            <option value="completed">Hoàn thành</option>
          </select>
        </li>
      </ul>
      <UiEmpty v-if="!onboardingTasks.length" title="Chưa có checklist onboarding" />
      <p class="text-sm text-slate-500">Tiến độ: {{ onboardingPercent }}%</p>
    </section>

    <!-- Năng lực -->
    <section v-show="activeTab === 'competency'" class="hcm-card p-6 space-y-4">
      <div class="flex flex-wrap justify-between gap-3">
        <h3 class="font-semibold">Ma trận năng lực</h3>
        <p v-if="competencyMatrix?.summary" class="text-sm text-slate-500">
          Đạt {{ competencyMatrix.summary.coverage_percent }}% · Gap {{ competencyMatrix.summary.gaps }}
        </p>
      </div>
      <table v-if="competencyMatrix?.items?.length" class="hcm-table w-full text-sm">
        <thead>
          <tr>
            <th>Năng lực</th>
            <th>Yêu cầu</th>
            <th>Hiện tại</th>
            <th>Gap</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in competencyMatrix.items" :key="row.competency_id">
            <td>{{ row.competency?.name }}</td>
            <td>{{ row.required_level ?? '—' }}</td>
            <td>{{ row.current_level ?? '—' }}</td>
            <td>
              <UiBadge :variant="row.gap_status === 'met' ? 'success' : row.gap_status === 'gap' ? 'danger' : 'warning'">
                {{ row.gap ?? '—' }}
              </UiBadge>
            </td>
          </tr>
        </tbody>
      </table>
      <UiEmpty v-else title="Chưa có dữ liệu năng lực" />
      <RouterLink :to="{ name: 'competency' }" class="text-sm text-primary-600">Mở module Năng lực →</RouterLink>
    </section>

    <!-- KPI -->
    <section v-show="activeTab === 'kpi'" class="hcm-card p-6 space-y-4">
      <h3 class="font-semibold">KPI & đánh giá (chu kỳ gần nhất)</h3>
      <div v-if="employeeKpi?.cycle">
        <p class="text-sm text-slate-500 mb-3">{{ employeeKpi.cycle.name }} ({{ employeeKpi.cycle.period }})</p>
        <p class="text-sm mb-2">Điểm KPI: <strong>{{ employeeKpi.kpi_score ?? '—' }}%</strong></p>
        <table v-if="employeeKpi.goals?.length" class="hcm-table w-full text-sm mb-4">
          <thead>
            <tr><th>KPI</th><th>Mục tiêu</th><th>Thực tế</th><th>Trọng số</th></tr>
          </thead>
          <tbody>
            <tr v-for="g in employeeKpi.goals" :key="g.id">
              <td>{{ g.title }}</td>
              <td>{{ g.target_value }}</td>
              <td>{{ g.actual_value ?? '—' }}</td>
              <td>{{ g.weight }}%</td>
            </tr>
          </tbody>
        </table>
        <div v-if="employeeKpi.review" class="rounded-lg border p-3 text-sm">
          <p>Tự ĐG: {{ employeeKpi.review.self_score ?? '—' }} · QL: {{ employeeKpi.review.manager_score ?? '—' }}</p>
          <p class="font-semibold mt-1">Điểm cuối: {{ employeeKpi.review.final_score ?? '—' }} ({{ employeeKpi.review.rating || 'chưa chốt' }})</p>
        </div>
      </div>
      <UiEmpty v-else title="Chưa có KPI trong chu kỳ hiện tại" />
      <RouterLink :to="{ name: 'performance' }" class="text-sm text-primary-600">Mở module KPI →</RouterLink>
    </section>

    <!-- Hợp đồng -->
    <section v-show="activeTab === 'contracts'" class="hcm-card p-6">
      <h3 class="font-semibold mb-4">Hợp đồng lao động</h3>
      <table class="hcm-table w-full" v-if="employee.contracts?.length">
        <thead>
          <tr>
            <th>Số HĐ</th>
            <th>Loại</th>
            <th>Từ ngày</th>
            <th>Đến ngày</th>
            <th>Lương CB</th>
            <th>TT</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in employee.contracts" :key="c.id">
            <td>{{ c.contract_number }}</td>
            <td>{{ labelOf(meta.contract_types, c.contract_type) || c.contract_type }}</td>
            <td>{{ date(c.start_date) }}</td>
            <td>{{ date(c.end_date) }}</td>
            <td>{{ money(c.salary_base) }}</td>
            <td><UiBadge :variant="c.status === 'active' ? 'success' : 'default'">{{ c.status }}</UiBadge></td>
          </tr>
        </tbody>
      </table>
      <UiEmpty v-else title="Chưa có hợp đồng" />
      <RouterLink :to="{ name: 'contracts' }" class="mt-4 inline-block text-sm text-primary-600">Quản lý hợp đồng →</RouterLink>
    </section>

    <!-- Khen thưởng & Kỷ luật -->
    <section v-show="activeTab === 'awards_discipline'" class="space-y-4">
      <div class="hcm-card p-6">
        <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
          <h3 class="font-semibold text-slate-900">Quyết định Khen thưởng & Kỷ luật</h3>
          <button type="button" class="hcm-btn-primary text-sm" @click="openAwardModal()">+ Thêm quyết định</button>
        </div>
        <table class="hcm-table w-full" v-if="awards.length">
          <thead>
            <tr>
              <th>Loại quyết định</th>
              <th>Số quyết định</th>
              <th>Ngày quyết định</th>
              <th>Lý do</th>
              <th>Số tiền (VND)</th>
              <th>Người ký</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in awards" :key="a.id" class="hover:bg-slate-50">
              <td>
                <UiBadge :variant="a.type === 'award' ? 'success' : 'danger'">
                  {{ a.type === 'award' ? 'Khen thưởng' : 'Kỷ luật' }}
                </UiBadge>
              </td>
              <td class="font-mono text-sm">{{ a.decision_number }}</td>
              <td>{{ date(a.decision_date) }}</td>
              <td class="max-w-xs truncate" :title="a.reason">{{ a.reason }}</td>
              <td class="font-medium text-slate-800">{{ a.amount ? money(a.amount) : '—' }}</td>
              <td>{{ a.signed_by || '—' }}</td>
              <td>
                <button type="button" class="text-xs text-red-600 hover:underline font-medium" @click="removeAward(a.id)">Xóa</button>
              </td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Chưa có quyết định khen thưởng / kỷ luật" description="Các quyết định khen thưởng cuối năm, thưởng dự án hoặc xử lý kỷ luật" />
      </div>
    </section>

    <!-- Điều động & Bổ nhiệm -->
    <section v-show="activeTab === 'transfers'" class="space-y-4">
      <div class="hcm-card p-6">
        <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
          <h3 class="font-semibold text-slate-900">Quyết định Điều động · Bổ nhiệm · Luân chuyển</h3>
          <button type="button" class="hcm-btn-primary text-sm" @click="openTransferModal()">+ Thêm điều động</button>
        </div>
        <table class="hcm-table w-full text-sm" v-if="transfers.length">
          <thead>
            <tr>
              <th>Loại</th>
              <th>Số quyết định</th>
              <th>Ngày hiệu lực</th>
              <th>Chuyển từ</th>
              <th>Chuyển đến</th>
              <th>Trạng thái</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in transfers" :key="t.id" class="hover:bg-slate-50">
              <td class="font-semibold text-slate-700">
                {{ t.type === 'promotion' ? 'Thăng chức 📈' : t.type === 'demotion' ? 'Giáng chức 📉' : 'Luân chuyển 🔄' }}
              </td>
              <td class="font-mono">{{ t.decision_number }}</td>
              <td>{{ date(t.effective_date) }}</td>
              <td class="text-xs text-slate-500">
                Phòng: {{ t.from_department?.name || '—' }} <br/>
                Chức danh: {{ t.from_position?.name || '—' }}
              </td>
              <td class="text-xs text-slate-800 font-medium">
                Phòng: {{ t.to_department?.name || '—' }} <br/>
                Chức danh: {{ t.to_position?.name || '—' }}
              </td>
              <td>
                <UiBadge :variant="t.status === 'approved' ? 'success' : t.status === 'pending' ? 'warning' : 'default'">
                  {{ t.status === 'approved' ? 'Đã duyệt' : 'Chờ duyệt' }}
                </UiBadge>
              </td>
              <td>
                <button v-if="t.status === 'pending'" type="button" class="hcm-btn-secondary text-xs py-0.5 px-2" @click="approveTransfer(t.id)">
                  Duyệt
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Chưa có quyết định điều động" description="Quyết định thăng chức, đổi phòng ban, đổi chức danh" />
      </div>
    </section>

    <!-- Quyết định Thôi việc -->
    <section v-show="activeTab === 'termination'" class="space-y-4">
      <div class="hcm-card p-6">
        <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
          <h3 class="font-semibold text-slate-900">Quyết định Thôi việc / Nghỉ việc</h3>
          <button v-if="employee.employment_status !== 'terminated'" type="button" class="hcm-btn-primary text-sm" @click="openTerminationModal()">
            + Đăng ký thôi việc
          </button>
        </div>

        <div v-if="terminations.length" class="space-y-4">
          <div v-for="t in terminations" :key="t.id" class="border rounded-lg p-5 bg-slate-50/50 hover:bg-slate-50/80 transition-colors">
            <div class="flex justify-between items-start">
              <div>
                <span class="font-mono text-sm bg-slate-200 text-slate-800 px-2 py-0.5 rounded mr-2">{{ t.decision_number }}</span>
                <span class="text-sm font-semibold">Quyết định: {{ t.type === 'resignation' ? 'Tự nguyện xin nghỉ' : t.type === 'dismissal' ? 'Sa thải kỷ luật' : t.type === 'retirement' ? 'Nghỉ hưu' : 'Giảm biên chế' }}</span>
              </div>
              <UiBadge :variant="t.status === 'approved' ? 'success' : 'warning'">
                {{ t.status === 'approved' ? 'Đã phê duyệt & Khóa hồ sơ' : 'Chờ duyệt' }}
              </UiBadge>
            </div>
            <div class="grid grid-cols-2 gap-4 mt-4 text-sm text-slate-600">
              <p>Ngày thôi việc chính thức: <strong class="text-slate-800">{{ date(t.termination_date) }}</strong></p>
              <p>Người ký quyết định: <strong>{{ t.signed_by || '—' }}</strong></p>
              <p class="col-span-2">Lý do nghỉ việc: <span class="italic text-slate-700">"{{ t.reason || '—' }}"</span></p>
            </div>
            <div class="flex justify-end mt-4" v-if="t.status === 'pending'">
              <button type="button" class="hcm-btn-primary text-xs py-1 px-3" @click="approveTermination(t.id)">
                Xác nhận phê duyệt nghỉ việc
              </button>
            </div>
          </div>
        </div>
        <UiEmpty v-else title="Không có quyết định thôi việc" description="Nhân viên đang hoạt động bình thường" />
      </div>
    </section>

    <!-- Tài khoản chấm công GPS/QR -->
    <section v-show="activeTab === 'punch_account'" class="hcm-card p-6 space-y-4">
      <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3">
        <div>
          <h3 class="font-semibold text-slate-900">Tài khoản chấm công (GPS / QR)</h3>
          <p class="text-sm text-slate-500 mt-1">NV đăng nhập bằng <b>mã NV</b> trên điện thoại. Mật khẩu mặc định HR cấp — bắt buộc đổi lần đầu.</p>
        </div>
        <button v-if="canManagePunchAccount && punchAccount?.has_account" type="button" class="hcm-btn-secondary text-sm text-red-600" @click="revokePunchAccount">
          Thu hồi quyền
        </button>
      </div>

      <div v-if="punchAccountLoading" class="text-slate-400 text-sm">Đang tải...</div>
      <template v-else-if="punchAccount">
        <div class="grid sm:grid-cols-2 gap-4 text-sm">
          <div class="rounded-lg bg-slate-50 p-4">
            <p class="text-slate-500">Tên đăng nhập</p>
            <p class="font-mono font-semibold text-lg">{{ punchAccount.login || employee.employee_code }}</p>
          </div>
          <div class="rounded-lg bg-slate-50 p-4">
            <p class="text-slate-500">Trạng thái</p>
            <p class="font-medium">{{ punchAccount.has_account ? 'Đã cấp tài khoản' : 'Chưa cấp' }}</p>
            <p v-if="punchAccount.must_change_password" class="text-amber-700 text-xs mt-1">Chưa đổi mật khẩu lần đầu</p>
          </div>
        </div>

        <div v-if="punchAccount.punch_permissions?.length" class="flex flex-wrap gap-2">
          <UiBadge v-if="punchAccount.punch_permissions.includes('attendance.punch_gps')" variant="success">GPS</UiBadge>
          <UiBadge v-if="punchAccount.punch_permissions.includes('attendance.punch_qr')" variant="info">QR cổng</UiBadge>
        </div>

        <div v-if="canManagePunchAccount" class="border-t pt-4 space-y-3">
          <p class="text-sm font-medium">Cấp / cập nhật quyền</p>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="punchGrant.gps" type="checkbox" class="rounded text-primary-600" />
            Chấm công GPS (định vị chi nhánh)
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="punchGrant.qr" type="checkbox" class="rounded text-primary-600" />
            Chấm công QR tại cổng
          </label>
          <button type="button" class="hcm-btn-primary text-sm" :disabled="punchSaving || (!punchGrant.gps && !punchGrant.qr)" @click="provisionPunchAccount">
            {{ punchSaving ? 'Đang cấp...' : (punchAccount.has_account ? 'Cập nhật quyền & reset MK mặc định' : 'Cấp tài khoản chấm công') }}
          </button>
          <p class="text-xs text-slate-500">Mật khẩu mặc định: <code class="bg-slate-100 px-1 rounded">abc@123</code> — NV phải đổi ngay sau lần đăng nhập đầu.</p>
        </div>
        <p v-else class="text-xs text-slate-500">Chỉ HR có quyền <code>attendance.punch_accounts.manage</code> mới cấp tài khoản.</p>
      </template>
    </section>

    <UiModal v-model="showDepForm" :title="depEditing ? 'Sửa người phụ thuộc' : 'Thêm người phụ thuộc'">
      <form class="space-y-3" @submit.prevent="saveDependent">
        <Field label="Họ tên" v-model="depForm.full_name" required />
        <Field label="Quan hệ" v-model="depForm.relationship" type="select" :options="meta.dependent_relationships" required />
        <Field label="Ngày sinh" v-model="depForm.date_of_birth" type="date" />
        <Field label="CCCD" v-model="depForm.id_card_number" />
        <Field label="Mã NPT (thuế)" v-model="depForm.tax_dependent_code" />
        <button type="submit" class="hcm-btn-primary w-full">Lưu</button>
      </form>
    </UiModal>

    <UiModal v-model="showDocForm" title="Tải lên tài liệu">
      <form class="space-y-3" @submit.prevent="saveDocument">
        <Field label="Loại tài liệu" v-model="docForm.type" type="select" :options="meta.document_types" required />
        <Field label="Số hiệu / số văn bản" v-model="docForm.document_number" />
        <div>
          <label class="text-sm font-medium">File đính kèm *</label>
          <input type="file" class="mt-1 text-sm w-full" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required @change="onDocFile" />
          <p class="text-xs text-slate-400 mt-1">PDF, ảnh, Word, Excel — tối đa 15MB</p>
        </div>
        <Field label="Cơ quan cấp" v-model="docForm.issuing_authority" />
        <Field label="Ngày cấp" v-model="docForm.issued_date" type="date" />
        <Field label="Hết hạn" v-model="docForm.expiry_date" type="date" />
        <Field label="Ghi chú" v-model="docForm.note" type="textarea" />
        <button type="submit" class="hcm-btn-primary w-full" :disabled="!docFile">Tải lên & lưu</button>
      </form>
    </UiModal>

    <!-- Modal thêm Khen thưởng/Kỷ luật -->
    <UiModal v-model="showAwardForm" title="Thêm quyết định Khen thưởng / Kỷ luật">
      <form class="space-y-3" @submit.prevent="saveAward">
        <Field label="Loại quyết định" v-model="awardForm.type" type="select" :options="{ award: 'Khen thưởng', discipline: 'Kỷ luật' }" required />
        <Field label="Số quyết định" v-model="awardForm.decision_number" required placeholder="Ví dụ: QĐ-123/KT" />
        <Field label="Ngày quyết định" v-model="awardForm.decision_date" type="date" required />
        <Field label="Lý do / Nội dung quyết định" v-model="awardForm.reason" type="textarea" required />
        <Field label="Số tiền thưởng/phạt (VND) - Nếu có" v-model="awardForm.amount" type="number" />
        <Field label="Người ký" v-model="awardForm.signed_by" />
        <Field label="Ghi chú" v-model="awardForm.note" type="textarea" />
        <button type="submit" class="hcm-btn-primary w-full mt-4">Lưu quyết định</button>
      </form>
    </UiModal>

    <!-- Modal thêm Điều động / Luân chuyển -->
    <UiModal v-model="showTransferForm" title="Quyết định Điều động · Bổ nhiệm · Luân chuyển">
      <form class="space-y-3" @submit.prevent="saveTransfer">
        <Field label="Số quyết định" v-model="transferForm.decision_number" required placeholder="Ví dụ: QĐ-88/BN" />
        <Field label="Ngày hiệu lực" v-model="transferForm.effective_date" type="date" required />
        <Field label="Loại điều động" v-model="transferForm.type" type="select" :options="{ promotion: 'Thăng chức (Promotion)', transfer: 'Luân chuyển (Transfer)', demotion: 'Giáng chức (Demotion)' }" required />
        <Field label="Chi nhánh điều chuyển đến" v-model="transferForm.to_branch_id" type="select" :options="branchOptions" />
        <Field label="Phòng ban điều chuyển đến" v-model="transferForm.to_department_id" type="select" :options="departmentOptions" />
        <Field label="Chức danh mới" v-model="transferForm.to_position_id" type="select" :options="positionOptions" />
        <Field label="Lý do điều chuyển" v-model="transferForm.reason" type="textarea" />
        <Field label="Người ký quyết định" v-model="transferForm.signed_by" />
        <button type="submit" class="hcm-btn-primary w-full mt-4">Lưu quyết định</button>
      </form>
    </UiModal>

    <!-- Modal thêm Thôi việc -->
    <UiModal v-model="showTerminationForm" title="Quyết định Thôi việc / Nghỉ việc">
      <form class="space-y-3" @submit.prevent="saveTermination">
        <Field label="Số quyết định" v-model="terminationForm.decision_number" required placeholder="Ví dụ: QĐ-99/TV" />
        <Field label="Ngày thôi việc chính thức" v-model="terminationForm.termination_date" type="date" required />
        <Field label="Hình thức nghỉ việc" v-model="terminationForm.type" type="select" :options="{ resignation: 'Tự nguyện thôi việc', dismissal: 'Sa thải kỷ luật', retirement: 'Nghỉ hưu', redundancy: 'Giảm biên chế' }" required />
        <Field label="Lý do thôi việc" v-model="terminationForm.reason" type="textarea" />
        <Field label="Người ký quyết định" v-model="terminationForm.signed_by" />
        <button type="submit" class="hcm-btn-primary w-full mt-4">Đăng ký quyết định</button>
      </form>
    </UiModal>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiModal from '../../components/ui/UiModal.vue';
import EmployeeCardPrint from '../../components/ui/EmployeeCardPrint.vue';
import { useFormat } from '../../composables/useFormat';
import { useToast } from '../../composables/useToast';
import { usePermission } from '../../composables/usePermission';
import { useFileDownload } from '../../composables/useFileDownload';

const toast = useToast();
const { can } = usePermission();
const canManagePunchAccount = computed(() => can('attendance.punch_accounts.manage'));

const Field = defineComponent({
  name: 'Field',
  props: {
    label: String,
    modelValue: [String, Number, Boolean],
    type: { type: String, default: 'text' },
    options: { type: Object, default: () => ({}) },
    hint: String,
    required: Boolean,
    class: String,
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    return () => {
      const cls = props.class || '';
      const inputClass = 'hcm-input mt-1 w-full';
      let control;
      if (props.type === 'select') {
        control = h(
          'select',
          {
            class: inputClass,
            value: props.modelValue ?? '',
            onChange: (e) => emit('update:modelValue', e.target.value),
          },
          [
            h('option', { value: '' }, '— Chọn —'),
            ...Object.entries(props.options).map(([k, v]) =>
              h('option', { value: k }, v)
            ),
          ]
        );
      } else if (props.type === 'textarea') {
        control = h('textarea', {
          class: inputClass,
          rows: 2,
          value: props.modelValue ?? '',
          onInput: (e) => emit('update:modelValue', e.target.value),
        });
      } else {
        control = h('input', {
          class: inputClass,
          type: props.type,
          value: props.modelValue ?? '',
          required: props.required,
          onInput: (e) =>
            emit('update:modelValue', props.type === 'number' ? Number(e.target.value) : e.target.value),
        });
      }
      return h('div', { class: cls }, [
        h('label', { class: 'text-sm font-medium text-slate-700' }, props.label),
        control,
        props.hint ? h('p', { class: 'text-xs text-slate-400 mt-0.5' }, props.hint) : null,
      ]);
    };
  },
});

const route = useRoute();
const { downloadApiGet } = useFileDownload();
const { date, money, statusLabel } = useFormat();

const loading = ref(true);
const saving = ref(false);
const employee = ref(null);
const form = ref({});
const profileForm = ref({});

// Photo
const photoSrc = ref(null);
const photoUploading = ref(false);

async function loadPhoto() {
  if (!employee.value?.id) return;
  try {
    const res = await api.get(`/employees/${employee.value.id}/photo`, { responseType: 'blob' });
    if (res.status === 204 || !res.data?.size) { photoSrc.value = null; return; }
    if (photoSrc.value) URL.revokeObjectURL(photoSrc.value);
    photoSrc.value = URL.createObjectURL(res.data);
  } catch {
    photoSrc.value = null;
  }
}

async function uploadPhoto(event) {
  const file = event.target.files[0];
  if (!file) return;
  event.target.value = '';
  photoUploading.value = true;
  const fd = new FormData();
  fd.append('photo', file);
  try {
    await api.post(`/employees/${employee.value.id}/photo`, fd);
    await loadPhoto();
    toast.show('Đã cập nhật ảnh đại diện');
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi upload ảnh', 'error');
  } finally {
    photoUploading.value = false;
  }
}
const meta = ref({
  genders: {},
  id_card_types: {},
  employment_types: {},
  employment_statuses: {},
  marital_statuses: {},
  education_levels: {},
  military_service_statuses: {},
  dependent_relationships: {},
  document_types: {},
  contract_types: {},
});
const departments = ref([]);
const positions = ref([]);
const leaveEntitlementGroups = ref([]);
const activeTab = ref('personal');

const tabs = [
  { id: 'personal', label: 'Cá nhân' },
  { id: 'identity', label: 'CCCD · BHXH · Thuế' },
  { id: 'address', label: 'Địa chỉ' },
  { id: 'work', label: 'Lao động' },
  { id: 'education', label: 'Học vấn & khẩn' },
  { id: 'dependents', label: 'Phụ thuộc' },
  { id: 'documents', label: 'Tài liệu' },
  { id: 'onboarding', label: 'Onboarding' },
  { id: 'competency', label: 'Năng lực' },
  { id: 'kpi', label: 'KPI' },
  { id: 'contracts', label: 'Hợp đồng' },
  { id: 'awards_discipline', label: 'Khen thưởng & Kỷ luật' },
  { id: 'transfers', label: 'Điều động & Bổ nhiệm' },
  { id: 'termination', label: 'Thôi việc' },
  { id: 'punch_account', label: 'Chấm công GPS/QR' },
];

const onboardingTasks = ref([]);
const competencyMatrix = ref(null);
const employeeKpi = ref(null);
const punchAccount = ref(null);
const punchAccountLoading = ref(false);
const punchSaving = ref(false);
const punchGrant = ref({ gps: true, qr: true });

const onboardingPercent = computed(() => {
  const total = onboardingTasks.value.length;
  if (!total) return 0;
  const done = onboardingTasks.value.filter((t) => t.status === 'completed').length;
  return Math.round((done / total) * 100);
});

const departmentOptions = computed(() =>
  Object.fromEntries(departments.value.map((d) => [d.id, d.name]))
);
const positionOptions = computed(() =>
  Object.fromEntries(positions.value.map((p) => [p.id, p.name]))
);
const leaveGroupOptions = computed(() => ({
  '': '— Theo phòng ban / mặc định công ty —',
  ...Object.fromEntries(leaveEntitlementGroups.value.map((g) => [g.id, `${g.name} (${g.annual_days} ngày)`])),
}));

const showDepForm = ref(false);
const showDocForm = ref(false);
const depEditing = ref(null);
const depForm = ref({ full_name: '', relationship: 'child', date_of_birth: '', id_card_number: '', tax_dependent_code: '' });
const docForm = ref({ type: 'cccd_front', document_number: '', issuing_authority: '', issued_date: '', expiry_date: '', note: '' });
const docFile = ref(null);

function labelOf(map, key) {
  return map?.[key] || key;
}

function syncForms(data) {
  form.value = { ...data };
  profileForm.value = { ...(data.profile || {}) };
}

async function load() {
  const id = route.params.id;

  // Ưu tiên load dữ liệu cốt lõi để hiện trang ngay
  const [emp, metaRes, d, p, b] = await Promise.all([
    api.get(`/employees/${id}`),
    api.get('/hr-meta'),
    api.get('/departments'),
    api.get('/positions'),
    api.get('/branches'),
  ]);
  employee.value = emp.data.data;
  meta.value = { ...meta.value, ...metaRes.data.data };
  departments.value = d.data.data;
  positions.value = p.data.data;
  branches.value = b.data.data;
  syncForms(employee.value);
  loadPhoto();

  // Phần còn lại load ngầm, không block hiển thị
  loadTabData(id);
}

async function loadTabData(id) {
  const empId = Number(id);

  // Load các tab phụ song song, lỗi không block
  await Promise.allSettled([
    api.get(`/employees/${id}/awards-discipline`).then(r => { awards.value = r.data.data; }),
    api.get(`/employees/${id}/transfers`).then(r => { transfers.value = r.data.data; }),
    api.get(`/employees/${id}/terminations`).then(r => { terminations.value = r.data.data; }),
    api.get('/leave-entitlement-groups').then(r => { leaveEntitlementGroups.value = r.data.data || []; }),
    loadPunchAccount(),
    api.get(`/employees/${id}/onboarding-tasks`).then(r => { onboardingTasks.value = r.data.data; }),
    api.get(`/employees/${id}/competency-matrix`).then(r => { competencyMatrix.value = r.data.data; }),
    api.get('/performance-cycles').then(async r => {
      const cycle = r.data.data[0];
      if (!cycle) { employeeKpi.value = null; return; }
      try {
        const row = (await api.get('/reports/performance-kpi', { params: { performance_cycle_id: cycle.id } })).data.data;
        const empRow = (row.employees || []).find(e => e.employee_id === empId);
        employeeKpi.value = {
          cycle: row.cycle,
          goals: cycle.goals?.filter(g => g.employee_id === empId) || [],
          kpi_score: empRow?.kpi_score,
          review: cycle.reviews?.find(r => r.employee_id === empId) || null,
        };
      } catch { employeeKpi.value = null; }
    }),
  ]);
}

async function updateOnboardingTask(taskId, status) {
  await api.put(`/employees/${employee.value.id}/onboarding-tasks/${taskId}`, { status });
  toast.show('Đã cập nhật onboarding');
  await load();
}

async function completeOnboarding() {
  await api.post(`/employees/${employee.value.id}/onboarding/complete`);
  toast.show('Đã xác nhận hoàn tất onboarding');
  await load();
}

async function saveCurrentTab() {
  saving.value = true;
  try {
    const id = employee.value.id;
    if (activeTab.value === 'education') {
      form.value.full_name = `${form.value.first_name} ${form.value.last_name}`.trim();
      await api.put(`/employees/${id}/profile`, profileForm.value);
      toast.show('Đã lưu hồ sơ bổ sung');
    } else if (['personal', 'identity', 'address', 'work'].includes(activeTab.value)) {
      form.value.full_name = `${form.value.first_name} ${form.value.last_name}`.trim();
      const { data } = await api.put(`/employees/${id}`, form.value);
      employee.value = { ...employee.value, ...data.data };
      syncForms(employee.value);
      toast.show('Đã lưu thông tin nhân viên');
    }
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi lưu', 'error');
  } finally {
    saving.value = false;
  }
}

function openDependent(d = null) {
  depEditing.value = d;
  depForm.value = d
    ? { ...d }
    : { full_name: '', relationship: 'child', date_of_birth: '', id_card_number: '', tax_dependent_code: '' };
  showDepForm.value = true;
}

async function saveDependent() {
  const id = employee.value.id;
  if (depEditing.value?.id) {
    await api.put(`/employees/${id}/dependents/${depEditing.value.id}`, depForm.value);
  } else {
    await api.post(`/employees/${id}/dependents`, depForm.value);
  }
  showDepForm.value = false;
  toast.show('Đã lưu người phụ thuộc');
  await load();
}

async function removeDependent(depId) {
  if (!confirm('Xóa người phụ thuộc này?')) return;
  await api.delete(`/employees/${employee.value.id}/dependents/${depId}`);
  toast.show('Đã xóa');
  await load();
}

function onDocFile(e) {
  docFile.value = e.target.files?.[0] || null;
}

function formatSize(bytes) {
  if (!bytes) return '—';
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

async function saveDocument() {
  if (!docFile.value) return;
  const fd = new FormData();
  Object.entries(docForm.value).forEach(([k, v]) => {
    if (v != null && v !== '') fd.append(k, v);
  });
  fd.append('file', docFile.value);
  await api.post(`/employees/${employee.value.id}/documents`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  showDocForm.value = false;
  docFile.value = null;
  docForm.value = { type: 'cccd_front', document_number: '', issuing_authority: '', issued_date: '', expiry_date: '', note: '' };
  toast.show('Đã tải lên tài liệu');
  await load();
}

async function downloadDocument(docId) {
  await downloadApiGet(`/employees/${employee.value.id}/documents/${docId}/download`, {}, 'tai-lieu');
}

async function removeDocument(docId) {
  if (!confirm('Xóa tài liệu?')) return;
  await api.delete(`/employees/${employee.value.id}/documents/${docId}`);
  await load();
}

const branches = ref([]);
const awards = ref([]);
const transfers = ref([]);
const terminations = ref([]);

const branchOptions = computed(() =>
  Object.fromEntries(branches.value.map((b) => [b.id, b.name]))
);

const showAwardForm = ref(false);
const showTransferForm = ref(false);
const showTerminationForm = ref(false);

const awardForm = ref({ type: 'award', decision_number: '', decision_date: '', reason: '', amount: null, signed_by: '', note: '' });
const transferForm = ref({ decision_number: '', effective_date: '', type: 'transfer', to_branch_id: '', to_department_id: '', to_position_id: '', reason: '', signed_by: '' });
const terminationForm = ref({ decision_number: '', termination_date: '', type: 'resignation', reason: '', signed_by: '' });

function openAwardModal() {
  awardForm.value = { type: 'award', decision_number: '', decision_date: new Date().toISOString().slice(0, 10), reason: '', amount: null, signed_by: '', note: '' };
  showAwardForm.value = true;
}

async function saveAward() {
  const id = employee.value.id;
  await api.post(`/employees/${id}/awards-discipline`, awardForm.value);
  toast.show('Đã lưu quyết định khen thưởng/kỷ luật');
  showAwardForm.value = false;
  await load();
}

async function removeAward(awardId) {
  if (!confirm('Bạn chắc chắn muốn xóa quyết định này?')) return;
  const id = employee.value.id;
  await api.delete(`/employees/${id}/awards-discipline/${awardId}`);
  toast.show('Đã xóa quyết định');
  await load();
}

function openTransferModal() {
  transferForm.value = {
    decision_number: '',
    effective_date: new Date().toISOString().slice(0, 10),
    type: 'transfer',
    to_branch_id: employee.value.branch_id || '',
    to_department_id: employee.value.department_id || '',
    to_position_id: employee.value.position_id || '',
    reason: '',
    signed_by: '',
  };
  showTransferForm.value = true;
}

async function saveTransfer() {
  const id = employee.value.id;
  await api.post(`/employees/${id}/transfers`, transferForm.value);
  toast.show('Đã lưu quyết định điều động');
  showTransferForm.value = false;
  await load();
}

async function approveTransfer(transferId) {
  const id = employee.value.id;
  await api.post(`/employees/${id}/transfers/${transferId}/approve`);
  toast.show('Đã duyệt quyết định điều động. Hồ sơ nhân viên đã được cập nhật.');
  await load();
}

function openTerminationModal() {
  terminationForm.value = {
    decision_number: '',
    termination_date: new Date().toISOString().slice(0, 10),
    type: 'resignation',
    reason: '',
    signed_by: '',
  };
  showTerminationForm.value = true;
}

async function saveTermination() {
  const id = employee.value.id;
  await api.post(`/employees/${id}/terminations`, terminationForm.value);
  toast.show('Đã đăng ký quyết định thôi việc');
  showTerminationForm.value = false;
  await load();
}

async function approveTermination(terminationId) {
  const id = employee.value.id;
  await api.post(`/employees/${id}/terminations/${terminationId}/approve`);
  toast.show('Đã duyệt quyết định thôi việc. Nhân sự đã ngừng hoạt động.');
  await load();
}

async function loadPunchAccount() {
  punchAccountLoading.value = true;
  try {
    const { data } = await api.get(`/employees/${route.params.id}/punch-account`);
    punchAccount.value = data.data;
    punchGrant.value = {
      gps: data.data.punch_permissions?.includes('attendance.punch_gps') ?? true,
      qr: data.data.punch_permissions?.includes('attendance.punch_qr') ?? true,
    };
  } catch {
    punchAccount.value = null;
  } finally {
    punchAccountLoading.value = false;
  }
}

async function provisionPunchAccount() {
  punchSaving.value = true;
  try {
    const { data } = await api.post(`/employees/${employee.value.id}/punch-account`, {
      punch_gps: punchGrant.value.gps,
      punch_qr: punchGrant.value.qr,
    });
    toast.show(data.data.message || 'Đã cấp tài khoản chấm công');
    await loadPunchAccount();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không cấp được tài khoản', 'error');
  } finally {
    punchSaving.value = false;
  }
}

async function revokePunchAccount() {
  if (!confirm('Thu hồi quyền chấm công GPS/QR của nhân viên này?')) return;
  try {
    await api.delete(`/employees/${employee.value.id}/punch-account`);
    toast.show('Đã thu hồi quyền chấm công');
    await loadPunchAccount();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không thu hồi được', 'error');
  }
}

watch(() => route.params.id, () => load());

onMounted(async () => {
  try {
    await load();
  } finally {
    loading.value = false;
  }
});
</script>
