<template>
  <div>
    <UiPageHeader title="Hợp đồng lao động" subtitle="BLLĐ 2019 — đủ loại HĐ, lương, BHXH, file scan" breadcrumb="Hợp đồng">
      <template #actions>
        <button type="button" class="hcm-btn-primary" @click="openForm()">+ Ký hợp đồng mới</button>
      </template>
    </UiPageHeader>

    <div class="hcm-card mb-4 p-4 space-y-4">
      <UiOrgScopeFilters
        :show-company-picker="scope.showCompanyPicker"
        :single-branch-mode="scope.singleBranchMode"
        v-model:filter-branch-id="scope.filterBranchId"
        v-model:filter-department-id="scope.filterDepartmentId"
        :branches="scope.branches"
        :filtered-departments="scope.filteredDepartments"
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
          <div v-if="editing" class="sm:col-span-2">
            <label class="text-sm font-medium">Nhân viên *</label>
            <select v-model="form.employee_id" class="hcm-input mt-1 w-full" required disabled>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }} ({{ e.employee_code }})</option>
            </select>
          </div>
          <div v-else class="sm:col-span-2">
            <EmployeeTargetPicker
              :key="contractPickerKey"
              v-model:mode="contractTargetMode"
              v-model:employee-id="form.employee_id"
              v-model:employee-ids="contractEmployeeIds"
              v-model:department-id="contractDepartmentId"
              :employees="employees"
              :departments="scope.filteredDepartments"
              :allowed-modes="['single', 'multi', 'department']"
            />
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
          <div class="sm:col-span-2" v-if="editing">
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
import { onMounted, ref, reactive, computed } from 'vue';
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
import { useFileDownload } from '../../composables/useFileDownload';
import { useToast } from '../../composables/useToast';

const { money, date } = useFormat();
const toast = useToast();
const { downloadApiGet } = useFileDownload();

const contracts = ref([]);
const employees = ref([]);
const search = ref('');
const scope = useOrgScopeFilters({ includeDepartment: true });
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
const contractTargetMode = ref('single');
const contractEmployeeIds = ref([]);
const contractDepartmentId = ref('');
const contractNumberPrefix = ref('');
const contractPickerKey = ref(0);

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
  if (editing.value) {
    return false;
  }
  if (contractTargetMode.value === 'department') {
    return true;
  }
  if (contractTargetMode.value === 'multi') {
    return contractEmployeeIds.value.length > 1;
  }
  return false;
});

function resolveContractEmployeeIds() {
  if (contractTargetMode.value === 'department' && contractDepartmentId.value) {
    const deptId = Number(contractDepartmentId.value);
    return employees.value
      .filter((e) => Number(e.department_id) === deptId)
      .map((e) => Number(e.id));
  }
  if (contractTargetMode.value === 'multi') {
    return contractEmployeeIds.value.map((id) => Number(id));
  }
  return form.value.employee_id ? [Number(form.value.employee_id)] : [];
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

function openForm(c = null) {
  editing.value = c;
  contractFile.value = null;
  contractTargetMode.value = 'single';
  contractEmployeeIds.value = [];
  contractDepartmentId.value = '';
  contractNumberPrefix.value = '';
  contractPickerKey.value += 1;
  form.value = c ? { ...emptyForm(), ...c } : emptyForm();
  if (!c && employees.value[0] && !form.value.employee_id) {
    form.value.employee_id = employees.value[0].id;
  }
  showForm.value = true;
}

function onContractFile(e) {
  contractFile.value = e.target.files?.[0] || null;
}

async function save() {
  const employeeIds = editing.value ? [Number(form.value.employee_id)] : resolveContractEmployeeIds();
  if (!employeeIds.length) {
    toast.show(
      contractTargetMode.value === 'department'
        ? 'Phòng ban không có nhân viên trong danh sách'
        : 'Vui lòng chọn ít nhất một nhân viên',
      'error',
    );
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
