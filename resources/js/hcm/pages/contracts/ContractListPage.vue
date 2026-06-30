<template>
  <div>
    <UiPageHeader title="Hợp đồng lao động" subtitle="BLLĐ 2019 — đủ loại HĐ, lương, BHXH, file scan" breadcrumb="Hợp đồng">
      <template #actions>
        <button type="button" class="hcm-btn-primary" @click="openForm()">+ Ký hợp đồng mới</button>
      </template>
    </UiPageHeader>

    <div class="hcm-card mb-4 p-4 space-y-4">
      <UiOrgScopeFilters
        :show-company-picker="showCompanyPicker"
        :single-branch-mode="singleBranchMode"
        v-model:filter-branch-id="filterBranchId"
        v-model:filter-department-id="filterDepartmentId"
        :branches="branches"
        :filtered-departments="filteredDepartments"
        @change="applyScopeAndLoad"
        @reset="resetScopeFilters"
      />
      <UiSearchInput
        v-model="search"
        placeholder="Tìm theo tên, mã NV, số hợp đồng..."
        :hint="`${pagination.total} hợp đồng`"
        @search="onSearch"
      />
    </div>

    <UiComplianceAlertPanel
      v-if="complianceAlerts.length"
      :items="complianceAlerts"
      title="Cảnh báo hợp đồng & thử việc"
      subtitle="Theo BLLĐ 2019 — thời hạn HĐ, thiếu hồ sơ, NV chưa có HĐ, sắp hết thử việc."
      :categories="contractAlertCategories"
    />

    <div class="hcm-card overflow-hidden">
      <table class="hcm-table w-full" v-if="contracts.length">
        <thead>
          <tr>
            <th>Số HĐ</th>
            <th>Nhân viên</th>
            <th>Loại</th>
            <th>Lương / BHXH</th>
            <th>Hiệu lực</th>
            <th>File</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in contracts" :key="c.id" class="hover:bg-slate-50">
            <td class="font-mono text-xs">{{ c.contract_number }}</td>
            <td>
              <p class="font-medium">{{ c.employee?.full_name }}</p>
              <p class="text-xs text-slate-500">{{ c.employee?.employee_code }}</p>
            </td>
            <td>{{ label(meta.contract_types, c.contract_type) }}</td>
            <td>
              <p>{{ money(c.salary_base) }}</p>
              <p class="text-xs text-slate-500">BHXH: {{ money(c.insurance_salary || c.salary_base) }}</p>
            </td>
            <td class="text-sm">
              <p class="font-medium text-slate-800">{{ date(c.start_date) }} <span v-if="c.end_date"> → {{ date(c.end_date) }}</span></p>
              <div class="mt-1 flex flex-wrap gap-2 items-center">
                <UiBadge :variant="c.status === 'active' ? 'success' : 'default'">{{ c.status === 'active' ? 'Đang hiệu lực' : c.status }}</UiBadge>
                
                <!-- Cảnh báo thời hạn -->
                <template v-if="c.status === 'active' && c.end_date">
                  <span v-if="getDaysLeft(c.end_date) < 0" class="bg-red-100 text-red-800 text-[10px] font-bold px-2 py-0.5 rounded flex items-center gap-0.5">
                    ⚠️ Đã hết hạn
                  </span>
                  <span v-else-if="getDaysLeft(c.end_date) <= 30" class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded flex items-center gap-0.5">
                    ⏳ Còn {{ getDaysLeft(c.end_date) }} ngày
                  </span>
                </template>
              </div>
            </td>
            <td>
              <button v-if="c.file_path" type="button" class="text-xs text-primary-600" @click="downloadContract(c.id)">
                Tải PDF
              </button>
              <span v-else class="text-xs text-slate-400">—</span>
            </td>
            <td>
              <button type="button" class="text-sm text-primary-600" @click="openForm(c)">Sửa</button>
            </td>
          </tr>
        </tbody>
      </table>
      <UiEmpty v-else title="Chưa có hợp đồng" />

      <!-- Pagination -->
      <div v-if="pagination.lastPage > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
        <span>Tổng {{ pagination.total }} hợp đồng</span>
        <div class="flex gap-2">
          <button type="button" class="hcm-btn-secondary text-xs" :disabled="pagination.currentPage <= 1" @click="load(pagination.currentPage - 1)">← Trước</button>
          <span class="px-2 py-1">{{ pagination.currentPage }} / {{ pagination.lastPage }}</span>
          <button type="button" class="hcm-btn-secondary text-xs" :disabled="pagination.currentPage >= pagination.lastPage" @click="load(pagination.currentPage + 1)">Tiếp →</button>
        </div>
      </div>
    </div>

    <UiModal v-model="showForm" :title="editing ? 'Sửa hợp đồng' : 'Hợp đồng lao động mới'" wide>
      <form class="space-y-4 max-h-[70vh] overflow-y-auto pr-1" @submit.prevent="save">
        <div class="grid gap-3 sm:grid-cols-2">

          <!-- Bước 1: Chọn công ty -->
          <div v-if="!editing" class="sm:col-span-2">
            <label class="text-sm font-medium">Công ty <span class="text-rose-500">*</span></label>
            <select v-model="formCompanyId" class="hcm-input mt-1 w-full" required @change="onFormCompanyChange">
              <option :value="null">-- Chọn công ty trước --</option>
              <option v-for="c in appStore.companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <!-- Bước 2: Chọn nhân viên -->
          <div v-if="editing" class="sm:col-span-2">
            <label class="text-sm font-medium">Nhân viên</label>
            <input :value="form.employee?.full_name || form.employee_id" class="hcm-input mt-1 w-full bg-slate-50" disabled />
          </div>
          <div v-else class="sm:col-span-2">
            <label class="text-sm font-medium">
              Nhân viên <span class="text-rose-500">*</span>
              <span v-if="contractEmployeeIds.length > 1" class="ml-2 text-xs font-normal text-amber-700">
                {{ contractEmployeeIds.length }} NV — ký {{ contractEmployeeIds.length }} HĐ cùng điều khoản
              </span>
            </label>

            <!-- Placeholder khi chưa chọn công ty -->
            <div v-if="!formCompanyId" class="hcm-input mt-1 bg-slate-50 text-slate-400 text-sm cursor-not-allowed">
              Chọn công ty trước
            </div>

            <!-- Loading -->
            <div v-else-if="loadingFormEmployees" class="hcm-input mt-1 bg-slate-50 text-slate-400 text-sm">
              Đang tải danh sách nhân viên...
            </div>

            <!-- Dropdown multi-checkbox -->
            <div v-else class="relative mt-1">
              <!-- Trigger -->
              <div
                class="hcm-input min-h-[42px] cursor-pointer flex flex-wrap gap-1.5 items-center pr-8"
                @click="empDropdownOpen = !empDropdownOpen"
              >
                <span v-if="!contractEmployeeIds.length" class="text-slate-400 text-sm select-none">
                  -- Chọn nhân viên (có thể chọn nhiều) --
                </span>
                <span
                  v-for="id in contractEmployeeIds"
                  :key="id"
                  class="inline-flex items-center gap-1 bg-primary-100 text-primary-700 text-xs px-2 py-0.5 rounded-full font-medium"
                >
                  {{ formEmployees.find(e => e.id === id)?.full_name || id }}
                  <button
                    type="button"
                    class="hover:text-rose-600 leading-none font-bold"
                    @click.stop="contractEmployeeIds = contractEmployeeIds.filter(x => x !== id)"
                  >×</button>
                </span>
                <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none select-none">▾</span>
              </div>

              <!-- Backdrop -->
              <div v-if="empDropdownOpen" class="fixed inset-0 z-40" @click="empDropdownOpen = false" />

              <!-- Panel -->
              <div v-if="empDropdownOpen" class="absolute z-50 left-0 right-0 bg-white border border-slate-200 rounded-xl shadow-xl mt-1">
                <!-- Search -->
                <div class="p-2 border-b border-slate-100">
                  <input
                    v-model="empSearch"
                    class="hcm-input text-sm"
                    placeholder="Tìm tên hoặc mã NV..."
                    @click.stop
                  />
                </div>
                <!-- Actions -->
                <div class="flex items-center gap-3 px-3 py-1.5 border-b border-slate-100 text-xs">
                  <button type="button" class="text-primary-600 hover:underline" @click.stop="contractEmployeeIds = filteredFormEmployees.map(e => e.id)">Chọn tất cả</button>
                  <button type="button" class="text-slate-400 hover:underline" @click.stop="contractEmployeeIds = []">Bỏ chọn</button>
                  <span class="ml-auto text-slate-400">{{ contractEmployeeIds.length }} / {{ formEmployees.length }} đã chọn</span>
                </div>
                <!-- List -->
                <div class="max-h-56 overflow-y-auto">
                  <label
                    v-for="e in filteredFormEmployees"
                    :key="e.id"
                    class="flex items-center gap-3 px-3 py-2 hover:bg-primary-50 cursor-pointer select-none border-b border-slate-50 last:border-b-0"
                    @click.stop
                  >
                    <input
                      type="checkbox"
                      :value="e.id"
                      v-model="contractEmployeeIds"
                      class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                    />
                    <div class="min-w-0">
                      <p class="text-sm font-medium truncate">{{ e.full_name }}</p>
                      <p class="text-xs text-slate-400">{{ e.employee_code }} · {{ e.department?.name || e.position?.name || '' }}</p>
                    </div>
                  </label>
                  <p v-if="!filteredFormEmployees.length" class="text-center text-xs text-slate-400 py-5">
                    Không tìm thấy nhân viên
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div v-if="!isBulkContract">
            <label class="text-sm font-medium">Số hợp đồng *</label>
            <input v-model="form.contract_number" class="hcm-input mt-1 w-full" required />
          </div>
          <div v-else>
            <label class="text-sm font-medium">Tiền tố số HĐ (tuỳ chọn)</label>
            <input v-model="contractNumberPrefix" class="hcm-input mt-1 w-full" placeholder="VD: CTR" />
            <p class="text-xs text-slate-500 mt-1">Mỗi NV một số HĐ riêng: {tiền tố}-{mã NV}-{ngày}</p>
          </div>
          <div>
            <label class="text-sm font-medium">Loại HĐ *</label>
            <select v-model="form.contract_type" class="hcm-input mt-1 w-full" required>
              <option v-for="(lbl, k) in meta.contract_types" :key="k" :value="k">{{ lbl }}</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium">Chức danh trên HĐ</label>
            <input v-model="form.job_title_on_contract" class="hcm-input mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm font-medium">Nơi làm việc</label>
            <input v-model="form.work_location" class="hcm-input mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm font-medium">Ngày bắt đầu *</label>
            <input v-model="form.start_date" type="date" class="hcm-input mt-1 w-full" required />
          </div>
          <div v-if="form.contract_type !== 'indefinite'">
            <label class="text-sm font-medium">Ngày kết thúc</label>
            <input v-model="form.end_date" type="date" class="hcm-input mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm font-medium">Ngày ký</label>
            <input v-model="form.signed_date" type="date" class="hcm-input mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm font-medium">Thời hạn (tháng)</label>
            <input v-model.number="form.contract_duration_months" type="number" class="hcm-input mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm font-medium">Thử việc (tháng)</label>
            <input v-model.number="form.probation_months" type="number" min="0" class="hcm-input mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm font-medium">Lương thử việc</label>
            <input v-model.number="form.probation_salary" type="number" class="hcm-input mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm font-medium">Lương chính thức *</label>
            <input v-model.number="form.salary_base" type="number" class="hcm-input mt-1 w-full" required />
          </div>
          <div>
            <label class="text-sm font-medium">Mức lương đóng BHXH</label>
            <input v-model.number="form.insurance_salary" type="number" class="hcm-input mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm font-medium">Giờ làm</label>
            <select v-model="form.working_hours" class="hcm-input mt-1 w-full">
              <option value="">—</option>
              <option v-for="(lbl, k) in meta.working_hour_types" :key="k" :value="k">{{ lbl }}</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium">Trạng thái</label>
            <select v-model="form.status" class="hcm-input mt-1 w-full">
              <option v-for="(lbl, k) in meta.contract_statuses" :key="k" :value="k">{{ lbl }}</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="text-sm font-medium">Ca / lịch làm việc</label>
            <input v-model="form.work_schedule" class="hcm-input mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm font-medium">Người ký (DN)</label>
            <input v-model="form.signed_by_employer" class="hcm-input mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm font-medium">Người ký (NLĐ)</label>
            <input v-model="form.signed_by_employee" class="hcm-input mt-1 w-full" />
          </div>
          <div class="sm:col-span-2">
            <label class="text-sm font-medium">Phụ cấp / ghi chú lương</label>
            <textarea v-model="form.allowance_note" class="hcm-input mt-1 w-full" rows="2" />
          </div>
          <div class="sm:col-span-2">
            <label class="text-sm font-medium">Ghi chú HĐ</label>
            <textarea v-model="form.notes" class="hcm-input mt-1 w-full" rows="2" />
          </div>
          <div class="sm:col-span-2">
            <label class="text-sm font-medium">File hợp đồng (PDF/DOC)</label>
            <input type="file" accept=".pdf,.doc,.docx" class="mt-1 text-sm" @change="onContractFile" />
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t">
          <button type="button" class="hcm-btn-secondary" @click="showForm = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="saving">
            {{ saving ? 'Đang lưu...' : (isBulkContract ? 'Ký HĐ cho NV đã chọn' : 'Lưu hợp đồng') }}
          </button>
        </div>
      </form>
    </UiModal>
  </div>
</template>

<script setup>
import { onMounted, ref, reactive, computed, watch } from 'vue';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiSearchInput from '../../components/ui/UiSearchInput.vue';
import UiOrgScopeFilters from '../../components/ui/UiOrgScopeFilters.vue';
import UiComplianceAlertPanel from '../../components/ui/UiComplianceAlertPanel.vue';
import UiModal from '../../components/ui/UiModal.vue';
import { extractItems } from '../../composables/usePagination';
import { useOrgScopeFilters } from '../../composables/useOrgScopeFilters';
import { useFormat } from '../../composables/useFormat';
import { useFileDownload } from '../../composables/useFileDownload';
import { useToast } from '../../composables/useToast';
import { useAppStore } from '../../stores/app';

const { money, date } = useFormat();
const toast = useToast();
const { downloadApiGet } = useFileDownload();
const appStore = useAppStore();

const contracts = ref([]);
const employees = ref([]);      // dùng cho bảng danh sách (page-level)
const formEmployees = ref([]);  // dùng riêng trong form, lọc theo công ty
const formDepartments = ref([]); // phòng ban theo công ty đã chọn trong form
const formCompanyId = ref(null);
const loadingFormEmployees = ref(false);
const search = ref('');
const scope = useOrgScopeFilters({ includeDepartment: true });
// Destructure ra top-level — Vue chỉ auto-unwrap Ref ở top-level, không qua scope.xxx
const { filterBranchId, filterDepartmentId, branches, filteredDepartments, showCompanyPicker, singleBranchMode } = scope;
const pagination = reactive({ currentPage: 1, lastPage: 1, total: 0 });
const complianceAlerts = ref([]);

const contractAlertCategories = [
  'contract_expired',
  'contract_expiring',
  'contract_expiring_soon',
  'contract_missing',
  'contract_no_file',
  'contract_duration',
  'probation_ending',
];

function getDaysLeft(endDateStr) {
  if (!endDateStr) return 999;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const end = new Date(endDateStr);
  end.setHours(0, 0, 0, 0);
  const diffTime = end - today;
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}
const meta = ref({ contract_types: {}, contract_statuses: {}, working_hour_types: {} });
const showForm = ref(false);
const editing = ref(null);
const saving = ref(false);
const contractFile = ref(null);
const contractEmployeeIds = ref([]);
const contractNumberPrefix = ref('');
const empDropdownOpen = ref(false);
const empSearch = ref('');

const filteredFormEmployees = computed(() => {
  const q = empSearch.value.trim().toLowerCase();
  if (!q) return formEmployees.value;
  return formEmployees.value.filter(
    (e) => e.full_name?.toLowerCase().includes(q) || e.employee_code?.toLowerCase().includes(q),
  );
});

const emptyForm = () => ({
  employee_id: null,
  contract_number: '',
  contract_type: 'indefinite',
  job_title_on_contract: '',
  work_location: '',
  start_date: new Date().toISOString().slice(0, 10),
  end_date: '',
  signed_date: new Date().toISOString().slice(0, 10),
  probation_months: 0,
  contract_duration_months: null,
  revision_number: 1,
  salary_base: 0,
  probation_salary: null,
  insurance_salary: null,
  allowance_note: '',
  salary_currency: 'VND',
  working_hours: 'full_time_48',
  work_schedule: '',
  signed_by_employer: '',
  signed_by_employee: '',
  status: 'active',
  notes: '',
});

const form = ref(emptyForm());

const isBulkContract = computed(() => {
  if (editing.value) return false;
  return contractEmployeeIds.value.length > 1;
});

function resolveContractEmployeeIds() {
  if (editing.value) return form.value.employee_id ? [Number(form.value.employee_id)] : [];
  return contractEmployeeIds.value.map((id) => Number(id));
}

async function loadFormEmployees(companyId) {
  if (!companyId) {
    formEmployees.value = [];
    formDepartments.value = [];
    return;
  }
  loadingFormEmployees.value = true;
  try {
    const [empRes, deptRes] = await Promise.all([
      api.get('/employees', {
        params: { per_page: 500, employment_status: 'active', company_id: companyId },
      }),
      api.get('/departments', { params: { company_id: companyId } }),
    ]);
    formEmployees.value = extractItems(empRes.data);
    formDepartments.value = extractItems(deptRes.data).filter((d) => d.name?.trim());
  } catch {
    formEmployees.value = [];
    formDepartments.value = [];
  } finally {
    loadingFormEmployees.value = false;
  }
}

function label(map, key) {
  return map?.[key] || key;
}

async function loadComplianceAlerts() {
  try {
    const { data } = await api.get('/hr-alerts', { params: { limit: 100 } });
    complianceAlerts.value = data.data?.items || [];
  } catch {
    complianceAlerts.value = [];
  }
}

async function load(page = 1) {
  const params = { page, per_page: 25, ...scope.toQueryParams() };
  if (search.value.trim()) params.search = search.value.trim();

  const [c, e, m] = await Promise.all([
    api.get('/employment-contracts', { params }),
    api.get('/employees', { params: { per_page: 200, employment_status: 'active', ...scope.toQueryParams() } }),
    api.get('/hr-meta'),
  ]);

  const payload = c.data?.data;
  if (payload && typeof payload === 'object' && 'data' in payload && 'total' in payload) {
    contracts.value = payload.data;
    pagination.currentPage = payload.current_page;
    pagination.lastPage = payload.last_page;
    pagination.total = payload.total;
  } else {
    contracts.value = Array.isArray(payload) ? payload : [];
    pagination.currentPage = 1;
    pagination.lastPage = 1;
    pagination.total = contracts.value.length;
  }

  employees.value = extractItems(e.data);
  meta.value = m.data.data;
  if (employees.value[0] && !form.value.employee_id) {
    form.value.employee_id = employees.value[0].id;
  }
}

function generateContractNumber(empId, startDate) {
  const emp = formEmployees.value.find((e) => e.id === Number(empId));
  if (!emp) return '';
  const d = (startDate || new Date().toISOString().slice(0, 10)).replace(/-/g, '');
  return `HD-${emp.employee_code}-${d}`;
}

function openForm(c = null) {
  editing.value = c;
  contractFile.value = null;
  contractEmployeeIds.value = [];
  contractNumberPrefix.value = '';
  empDropdownOpen.value = false;
  empSearch.value = '';
  formEmployees.value = [];
  formDepartments.value = [];
  form.value = c ? { ...emptyForm(), ...c } : emptyForm();
  if (c?.employee?.company_id) {
    formCompanyId.value = c.employee.company_id;
    loadFormEmployees(c.employee.company_id);
  } else {
    formCompanyId.value = null;
  }
  showForm.value = true;
}

// Gọi khi user tự chọn công ty từ dropdown (không dùng watch để tránh double-load)
function onFormCompanyChange() {
  form.value.employee_id = null;
  contractEmployeeIds.value = [];
  empSearch.value = '';
  loadFormEmployees(formCompanyId.value);
}

// Khi chọn đúng 1 NV → auto-fill employee_id + số HĐ
watch(contractEmployeeIds, (ids) => {
  if (editing.value) return;
  if (ids.length === 1) {
    form.value.employee_id = ids[0];
    form.value.contract_number = generateContractNumber(ids[0], form.value.start_date);
  } else {
    form.value.employee_id = null;
    if (ids.length === 0) form.value.contract_number = '';
  }
});

// Khi đổi ngày bắt đầu → cập nhật số HĐ nếu đang chọn đúng 1 NV
watch(() => form.value.start_date, (startDate) => {
  if (editing.value || contractEmployeeIds.value.length !== 1) return;
  form.value.contract_number = generateContractNumber(contractEmployeeIds.value[0], startDate);
});

function onContractFile(e) {
  contractFile.value = e.target.files?.[0] || null;
}

async function save() {
  const employeeIds = resolveContractEmployeeIds();
  if (!employeeIds.length) {
    toast.show('Vui lòng chọn ít nhất một nhân viên', 'error');
    return;
  }

  saving.value = true;
  try {
    const payload = { ...form.value };
    if (payload.contract_type === 'indefinite') payload.end_date = null;
    if (!payload.insurance_salary) payload.insurance_salary = payload.salary_base;

    let contractId;
    let bulkMessage = null;

    if (editing.value?.id) {
      const { data } = await api.put(`/employment-contracts/${editing.value.id}`, payload);
      contractId = data.data.id;
    } else if (employeeIds.length === 1 && !isBulkContract.value) {
      const { data } = await api.post('/employment-contracts', {
        ...payload,
        employee_id: employeeIds[0],
      });
      contractId = data.data.id;
    } else {
      const bulkPayload = { ...payload };
      delete bulkPayload.employee_id;
      delete bulkPayload.contract_number;
      bulkPayload.employee_ids = employeeIds;
      if (contractNumberPrefix.value.trim()) {
        bulkPayload.contract_number_prefix = contractNumberPrefix.value.trim();
      }
      const { data } = await api.post('/employment-contracts/bulk', bulkPayload);
      bulkMessage = data.data?.message;
      contractId = null;
    }

    if (contractFile.value && contractId) {
      const fd = new FormData();
      fd.append('file', contractFile.value);
      await api.post(`/employment-contracts/${contractId}/upload`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
    }

    toast.show(bulkMessage || 'Đã lưu hợp đồng');
    showForm.value = false;
    await Promise.all([loadComplianceAlerts(), load(pagination.currentPage)]);
  } catch (e) {
    const errs = e.response?.data?.errors;
    const firstErr = errs ? Object.values(errs)?.[0]?.[0] : null;
    toast.show(firstErr || e.response?.data?.message || 'Lỗi lưu HĐ', 'error');
  } finally {
    saving.value = false;
  }
}

async function downloadContract(id) {
  await downloadApiGet(`/employment-contracts/${id}/download`, {}, 'hop-dong.pdf');
}

function onSearch(value) {
  search.value = value;
  load(1);
}

function applyScopeAndLoad() {
  load(1);
}

function resetScopeFilters() {
  scope.resetScope();
  search.value = '';
  load(1);
}

onMounted(async () => {
  await scope.loadMeta();
  await Promise.all([loadComplianceAlerts(), load()]);
});
</script>
