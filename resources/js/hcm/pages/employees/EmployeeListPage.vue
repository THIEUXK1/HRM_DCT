<template>
  <div>
    <UiPageHeader title="Nhân viên" subtitle="Quản lý hồ sơ nhân sự" breadcrumb="Core HR">
      <template #actions>
        <button type="button" class="hcm-btn-secondary mr-2" @click="triggerExport">Xuất Excel</button>
        <button type="button" class="hcm-btn-secondary mr-2" @click="showImportModal = true">Nhập Excel</button>
        <button type="button" class="hcm-btn-secondary mr-2" @click="showSyncModal = true">🔄 Đồng bộ API</button>
        <EmployeeCardPrint v-if="selectedIds.size > 0" :employees="selectedEmployees" />
        <button type="button" class="hcm-btn-primary" @click="openCreate">+ Thêm nhân viên</button>
      </template>
    </UiPageHeader>

    <div class="hcm-card mb-4 p-4 space-y-4">
      <UiOrgScopeFilters
        show-company
        show-status
        :show-company-picker="showCompanyPicker"
        :single-branch-mode="singleBranchMode"
        v-model:filter-company-id="filterCompanyId"
        v-model:filter-branch-id="filterBranchId"
        v-model:filter-department-id="filterDepartmentId"
        v-model:filter-status="filterStatus"
        :branches="branches"
        :filtered-departments="filteredDepartments"
        @company-change="onScopeCompanyChange"
        @change="applyScopeFilters"
        @reset="resetScopeFilters"
      />
      <div class="flex items-center gap-3">
        <UiSearchInput
          v-model="search"
          placeholder="Tìm theo tên, mã NV, email..."
          :hint="`${meta.total} nhân viên`"
          class="flex-1"
          @search="onSearch"
        />
        <div class="flex items-center gap-2 shrink-0">
          <label class="text-xs text-slate-500 whitespace-nowrap">Mỗi trang</label>
          <select v-model="perPage" class="hcm-input text-sm py-1.5 w-20" @change="onPerPageChange">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>
      </div>
    </div>

    <div class="hcm-card overflow-hidden">
      <div v-if="loading" class="py-12 text-center text-slate-400">Đang tải...</div>
      <template v-else>
        <table class="hcm-table w-full" v-if="items.length">
          <thead>
            <tr>
              <th class="w-8">
                <input type="checkbox" :checked="allPageSelected" @change="toggleAllPage" class="rounded" />
              </th>
              <th>Mã NV</th>
              <th>Họ tên</th>
              <th>Chi nhánh</th>
              <th>Phòng ban</th>
              <th>Chức danh</th>
              <th>Nguồn</th>
              <th>Trạng thái</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in items" :key="e.id" class="hover:bg-slate-50" :class="{ 'bg-primary-50': selectedIds.has(e.id) }">
              <td>
                <input type="checkbox" :checked="selectedIds.has(e.id)" @change="toggleSelect(e)" class="rounded" />
              </td>
              <td class="font-mono text-xs text-primary-700">{{ e.employee_code }}</td>
              <td>
                <p class="font-medium">{{ e.full_name }}</p>
                <p class="text-xs text-slate-500">{{ e.email }}</p>
              </td>
              <td>{{ e.branch?.name || '—' }}</td>
              <td>{{ e.department?.name || '—' }}</td>
              <td>{{ e.position?.name || '—' }}</td>
              <td>
                <span v-if="e.source_company" class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">{{ e.source_company }}</span>
                <span v-else class="text-slate-300 text-xs">—</span>
              </td>
              <td>
                <UiBadge :variant="
                  e.employment_status === 'active' ? 'success' :
                  e.employment_status === 'probation' ? 'info' :
                  ['terminated','resigned'].includes(e.employment_status) ? 'danger' : 'warning'
                ">
                  {{ statusLabel(e.employment_status) }}
                </UiBadge>
              </td>
              <td>
                <RouterLink :to="{ name: 'employee-detail', params: { id: e.id } }" class="text-sm text-primary-600 hover:underline">
                  Hồ sơ
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Không có nhân viên" />
      </template>

      <UiPagination
        :current-page="meta.currentPage"
        :last-page="meta.lastPage"
        :total="meta.total"
        :from="meta.from"
        :to="meta.to"
        class="px-4 border-t"
        @change="changePage"
      />
    </div>

    <!-- Modal tạo nhân viên -->
    <UiModal v-model="showForm" :title="editing ? 'Sửa nhân viên' : 'Thêm nhân viên'" wide>
      <form class="space-y-4" @submit.prevent="save">

        <!-- Thông tin cơ bản -->
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase mb-2">Thông tin cơ bản</p>
          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
              <label class="text-sm font-medium">Họ và tên <span class="text-rose-500">*</span></label>
              <input v-model="form.full_name" class="hcm-input mt-1" required placeholder="VD: Nguyễn Văn An" />
            </div>
            <div>
              <label class="text-sm font-medium">Tên tiếng Trung</label>
              <input v-model="form.chinese_name" class="hcm-input mt-1" placeholder="VD: 阮文安" />
            </div>
            <div>
              <label class="text-sm font-medium">Họ tên gốc (song ngữ)</label>
              <input v-model="form.full_name_raw" class="hcm-input mt-1" placeholder="VD: Nguyễn Văn An 阮文安" />
            </div>
            <div>
              <label class="text-sm font-medium">Mã nhân viên</label>
              <input v-model="form.employee_code" class="hcm-input mt-1" placeholder="VD: V260001" />
            </div>
            <div>
              <label class="text-sm font-medium">Email</label>
              <input v-model="form.email" type="email" class="hcm-input mt-1" />
            </div>
          </div>
        </div>

        <!-- Tổ chức -->
        <div class="border-t pt-3">
          <p class="text-xs font-semibold text-slate-400 uppercase mb-2">Tổ chức</p>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-medium">Công ty <span class="text-rose-500">*</span></label>
              <select v-model="form.company_id" class="hcm-input mt-1" required @change="onFormCompanyChange">
                <option :value="null">-- Chọn công ty --</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="text-sm font-medium">Chi nhánh</label>
              <select v-model="form.branch_id" class="hcm-input mt-1" :disabled="!form.company_id" @change="onFormBranchChange">
                <option :value="null">-- Chọn chi nhánh --</option>
                <option v-for="b in formBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
            <div>
              <label class="text-sm font-medium">Phòng ban <span class="text-rose-500">*</span></label>
              <select v-model="form.department_id" class="hcm-input mt-1" required :disabled="!form.branch_id" @change="onFormDeptChange">
                <option :value="null">-- Chọn phòng ban --</option>
                <option v-for="d in formDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>
            <div>
              <label class="text-sm font-medium">Chức danh <span class="text-rose-500">*</span></label>
              <select v-model="form.position_id" class="hcm-input mt-1" required :disabled="!form.department_id">
                <option :value="null">-- Chọn chức danh --</option>
                <option v-for="p in formPositions" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Trạng thái & Thời gian -->
        <div class="border-t pt-3">
          <p class="text-xs font-semibold text-slate-400 uppercase mb-2">Trạng thái & Thời gian</p>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-medium">Trạng thái làm việc</label>
              <select v-model="form.employment_status" class="hcm-input mt-1">
                <option value="active">Đang làm việc</option>
                <option value="probation">Thử việc</option>
                <option value="maternity_leave">Nghỉ thai sản</option>
                <option value="unpaid_leave">Nghỉ không lương</option>
                <option value="suspended">Đình chỉ</option>
                <option value="terminated">Nghỉ việc</option>
                <option value="resigned">Tự nghỉ</option>
              </select>
            </div>
            <div>
              <label class="text-sm font-medium">Nguồn dữ liệu</label>
              <select v-model="form.source_company" class="hcm-input mt-1">
                <option value="">-- Nhập thủ công --</option>
                <option value="BPVN">BPVN</option>
                <option value="PFVN">PFVN</option>
                <option value="MEGA">MEGA</option>
              </select>
            </div>
            <div>
              <label class="text-sm font-medium">Ngày vào làm</label>
              <input v-model="form.hire_date" type="date" class="hcm-input mt-1" />
            </div>
            <div v-if="form.employment_status === 'terminated' || form.employment_status === 'resigned'">
              <label class="text-sm font-medium">Ngày nghỉ việc</label>
              <input v-model="form.termination_date" type="date" class="hcm-input mt-1" />
            </div>
          </div>
        </div>

        <!-- Ngân hàng -->
        <div class="border-t pt-3">
          <p class="text-xs font-semibold text-slate-400 uppercase mb-2">Ngân hàng</p>
          <div>
            <label class="text-sm font-medium">Tên ngân hàng</label>
            <input v-model="form.bank_name" class="hcm-input mt-1" placeholder="VD: Vietcombank" />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="hcm-btn-secondary" @click="showForm = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="saving">{{ saving ? 'Đang lưu...' : 'Lưu' }}</button>
        </div>
      </form>
    </UiModal>

    <!-- Modal đồng bộ API -->
    <UiModal v-model="showSyncModal" title="Đồng bộ nhân viên từ API">
      <div class="space-y-4">
        <!-- Bước 1: Chọn thông tin -->
        <template v-if="!syncing && !syncResults">
          <div>
            <label class="text-sm font-medium block mb-1">Công ty trong hệ thống</label>
            <select v-model="syncForm.company_id" class="hcm-input" :disabled="syncing">
              <option value="">-- Chọn công ty --</option>
              <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium block mb-1">Nguồn API</label>
            <select v-model="syncForm.api_type" class="hcm-input" :disabled="syncing">
              <option value="">-- Chọn API --</option>
              <option value="BPVN">BPVN — Best Pacific Việt Nam</option>
              <option value="PFVN">PFVN — PF Việt Nam</option>
              <option value="MEGA">MEGA</option>
            </select>
          </div>
          <div class="p-3 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800">
            Đồng bộ tháng <strong>{{ currentYM }}</strong>. Mã NV (<strong>EMPNO</strong>) là duy nhất toàn hệ thống — nếu nhân viên đã tồn tại ở công ty khác sẽ chỉ <strong>đổi công ty</strong>, không tạo thêm bản ghi mới.
          </div>
        </template>

        <!-- Bước 2: Đang đồng bộ — progress bar -->
        <template v-if="syncing">
          <div class="space-y-3">
            <div class="flex items-center justify-between text-sm">
              <span class="font-medium text-slate-700">Đang đồng bộ {{ syncForm.api_type }}...</span>
              <span class="font-bold text-primary-600">{{ syncProgress }}%</span>
            </div>
            <div class="w-full bg-slate-200 rounded-full h-3 overflow-hidden">
              <div
                class="h-3 rounded-full bg-primary-500 transition-all duration-300"
                :style="{ width: syncProgress + '%' }"
              ></div>
            </div>
            <p class="text-xs text-slate-500 text-center">
              {{ syncProcessed }} / {{ syncTotal }} nhân viên
            </p>
          </div>
        </template>

        <!-- Bước 3: Kết quả -->
        <template v-if="syncResults && !syncing">
          <div class="p-3 bg-emerald-50 border border-emerald-200 rounded space-y-2">
            <p class="font-semibold text-emerald-800">Đồng bộ hoàn tất — {{ syncResults.api_type }} tháng {{ syncResults.ym }}</p>
            <div class="grid grid-cols-3 gap-2 text-center text-xs">
              <div class="bg-white rounded p-2 border">
                <p class="text-2xl font-bold text-emerald-600">{{ syncResults.created }}</p>
                <p class="text-slate-500">Tạo mới</p>
              </div>
              <div class="bg-white rounded p-2 border">
                <p class="text-2xl font-bold text-blue-600">{{ syncResults.updated }}</p>
                <p class="text-slate-500">Cập nhật</p>
              </div>
              <div class="bg-white rounded p-2 border">
                <p class="text-2xl font-bold text-slate-600">{{ syncResults.total }}</p>
                <p class="text-slate-500">Tổng API</p>
              </div>
            </div>
            <div v-if="syncResults.errors?.length" class="text-rose-600 text-xs mt-1">
              <p class="font-semibold">Lỗi ({{ syncResults.errors.length }}):</p>
              <ul class="list-disc pl-4 max-h-24 overflow-y-auto space-y-0.5 mt-1">
                <li v-for="(err, i) in syncResults.errors" :key="i">{{ err }}</li>
              </ul>
            </div>
          </div>
        </template>

        <div class="flex justify-end gap-2 pt-2 border-t">
          <button type="button" class="hcm-btn-secondary" :disabled="syncing" @click="closeSyncModal">Đóng</button>
          <button
            v-if="!syncResults"
            type="button"
            class="hcm-btn-primary"
            :disabled="syncing || !syncForm.company_id || !syncForm.api_type"
            @click="handleSync"
          >
            {{ syncing ? 'Đang đồng bộ...' : '🔄 Bắt đầu đồng bộ' }}
          </button>
          <button v-else type="button" class="hcm-btn-primary" @click="resetSync">🔄 Đồng bộ lại</button>
        </div>
      </div>
    </UiModal>

    <!-- Modal nhập Excel -->
    <UiModal v-model="showImportModal" title="Nhập hồ sơ nhân viên từ Excel">
      <form class="space-y-4" @submit.prevent="handleImport">
        <div class="p-3 bg-blue-50 border border-blue-200 rounded text-sm">
          <div class="flex items-start justify-between gap-3">
            <div class="space-y-1 text-xs text-blue-700 flex-1">
              <p class="font-semibold text-blue-900 text-sm">Hướng dẫn:</p>
              <p>1. Tải file mẫu, điền dữ liệu theo hướng dẫn trong sheet "Hướng dẫn".</p>
              <p>2. Các cột bắt buộc: <strong>Mã NV, Họ, Tên, Email</strong>. Cột khác có thể để trống.</p>
              <p>3. Ngày tháng nhập theo định dạng <strong>dd/mm/yyyy</strong> (ví dụ: 15/03/1990).</p>
              <p>4. Phòng ban / Chức danh chưa có sẽ được tạo tự động.</p>
            </div>
            <button
              type="button"
              class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-blue-700 bg-white border border-blue-300 rounded hover:bg-blue-50 whitespace-nowrap"
              :disabled="downloadingTemplate"
              @click="handleDownloadTemplate"
            >
              <span>⬇</span> {{ downloadingTemplate ? 'Đang tải...' : 'Tải file mẫu' }}
            </button>
          </div>
        </div>
        <div>
          <label class="text-sm font-medium block mb-1">Chọn file Excel (.xlsx) hoặc CSV</label>
          <input type="file" accept=".csv,.txt,.xlsx,.xls" class="hcm-input" required @change="onFileSelected" />
        </div>
        <div v-if="importResults" class="p-3 bg-slate-50 border rounded text-xs max-h-40 overflow-y-auto space-y-1">
          <p class="font-semibold text-emerald-700">Đã nhập thành công: {{ importResults.imported }} nhân viên</p>
          <p class="font-semibold text-rose-700">Bỏ qua / Lỗi: {{ importResults.skipped }}</p>
          <div v-if="importResults.errors?.length" class="text-rose-600 mt-1">
            <p class="font-semibold text-rose-800">Chi tiết lỗi:</p>
            <ul class="list-disc pl-4 space-y-0.5">
              <li v-for="(err, idx) in importResults.errors" :key="idx">{{ err }}</li>
            </ul>
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t">
          <button type="button" class="hcm-btn-secondary" @click="showImportModal = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="importing">{{ importing ? 'Đang xử lý...' : 'Bắt đầu nhập' }}</button>
        </div>
      </form>
    </UiModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiModal from '../../components/ui/UiModal.vue';
import UiPagination from '../../components/ui/UiPagination.vue';
import UiSearchInput from '../../components/ui/UiSearchInput.vue';
import UiOrgScopeFilters from '../../components/ui/UiOrgScopeFilters.vue';
import EmployeeCardPrint from '../../components/ui/EmployeeCardPrint.vue';
import { usePagination } from '../../composables/usePagination';
import { useOrgScopeFilters } from '../../composables/useOrgScopeFilters';
import { useFormat } from '../../composables/useFormat';
import { useToast } from '../../composables/useToast';
import { useFileDownload } from '../../composables/useFileDownload';

const { statusLabel } = useFormat();
const toast = useToast();
const router = useRouter();
const currentYM = new Date().toISOString().slice(0, 7);

const { items, meta, loading, fetch, changePage, setFilter, setFilters } = usePagination(api, '/employees');
const scope = useOrgScopeFilters({ includeStatus: true });
// Destructure to top-level — Vue chỉ auto-unwrap Ref ở top-level, không unwrap qua scope.xxx
const { filterCompanyId, filterBranchId, filterDepartmentId, filterStatus, branches, filteredDepartments, showCompanyPicker, singleBranchMode } = scope;

const companies = ref([]);
const formBranches = ref([]);
const formDepartments = ref([]);
const formPositions = ref([]);
const search = ref('');
const perPage = ref(25);

// Selection for batch card printing
const selectedIds = ref(new Set());
const selectedEmployees = computed(() => items.value.filter((e) => selectedIds.value.has(e.id)));
const allPageSelected = computed(() => items.value.length > 0 && items.value.every((e) => selectedIds.value.has(e.id)));

function toggleSelect(emp) {
  const next = new Set(selectedIds.value);
  if (next.has(emp.id)) next.delete(emp.id);
  else next.add(emp.id);
  selectedIds.value = next;
}

function toggleAllPage() {
  if (allPageSelected.value) {
    const next = new Set(selectedIds.value);
    items.value.forEach((e) => next.delete(e.id));
    selectedIds.value = next;
  } else {
    const next = new Set(selectedIds.value);
    items.value.forEach((e) => next.add(e.id));
    selectedIds.value = next;
  }
}

const showForm = ref(false);
const editing = ref(false);
const saving = ref(false);
const form = ref(emptyForm());

const showImportModal = ref(false);
const importing = ref(false);
const importFile = ref(null);
const importResults = ref(null);
const downloadingTemplate = ref(false);

const showSyncModal = ref(false);
const syncing = ref(false);
const syncResults = ref(null);
const syncForm = ref({ company_id: '', api_type: '' });
const syncProgress = ref(0);
const syncTotal = ref(0);
const syncProcessed = ref(0);
const { downloadApiGet } = useFileDownload();

function emptyForm() {
  return {
    company_id: null, branch_id: null, department_id: null, position_id: null,
    employee_code: '',
    full_name: '', chinese_name: '', full_name_raw: '',
    first_name: '', last_name: '',
    email: '',
    employment_status: 'active', is_active: true,
    hire_date: null, termination_date: null,
    bank_name: '', source_company: '',
  };
}

async function loadFormMeta() {
  const { data } = await api.get('/companies');
  companies.value = data.data;
}

function applyScopeFilters() {
  const extra = { per_page: perPage.value };
  if (search.value.trim()) extra.search = search.value.trim();
  scope.applyToPagination(setFilters, extra);
}

function onPerPageChange() {
  setFilter('per_page', perPage.value);
}

function onSearch(value) {
  search.value = value;
  applyScopeFilters();
}

function resetScopeFilters() {
  scope.resetScope();
  search.value = '';
  applyScopeFilters();
}

async function onScopeCompanyChange(companyId) {
  await scope.onCompanyChange(companyId);
  applyScopeFilters();
}

function openCreate() {
  editing.value = false;
  form.value = emptyForm();
  formBranches.value = [];
  formDepartments.value = [];
  formPositions.value = [];
  showForm.value = true;
}

async function onFormCompanyChange() {
  form.value.branch_id = null;
  form.value.department_id = null;
  form.value.position_id = null;
  formBranches.value = [];
  formDepartments.value = [];
  formPositions.value = [];
  if (!form.value.company_id) return;
  const { data } = await api.get('/branches', {
    headers: { 'X-Company-Id': form.value.company_id },
  });
  formBranches.value = data.data || [];
}

async function onFormBranchChange() {
  form.value.department_id = null;
  form.value.position_id = null;
  formDepartments.value = [];
  formPositions.value = [];
  if (!form.value.branch_id) return;
  const { data } = await api.get('/departments', {
    params: { branch_id: form.value.branch_id },
  });
  formDepartments.value = data.data || [];
}

async function onFormDeptChange() {
  form.value.position_id = null;
  formPositions.value = [];
  if (!form.value.department_id) return;
  const { data } = await api.get('/positions', {
    params: { department_id: form.value.department_id },
  });
  formPositions.value = data.data || [];
}

async function save() {
  saving.value = true;
  form.value.first_name = form.value.full_name;
  form.value.last_name = '';
  form.value.is_active = !['terminated', 'resigned'].includes(form.value.employment_status);
  try {
    const { data } = await api.post('/employees', form.value);
    toast.show('Đã thêm nhân viên');
    showForm.value = false;
    fetch();
    if (data?.data?.id) {
      router.push({ name: 'employee-detail', params: { id: data.data.id } });
    }
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi lưu dữ liệu', 'error');
  } finally {
    saving.value = false;
  }
}

async function triggerExport() {
  try {
    const params = scope.toQueryParams();
    if (search.value.trim()) params.search = search.value.trim();
    await downloadApiGet('/employees/actions/export', params, 'DS-nhan-vien.csv');
    toast.show('Đã xuất file dữ liệu thành công!');
  } catch (e) {
    toast.show('Lỗi khi xuất file nhân viên', 'error');
  }
}

async function handleSync() {
  if (!syncForm.value.company_id || !syncForm.value.api_type) {
    toast.show('Vui lòng chọn công ty và nguồn API', 'error');
    return;
  }

  syncing.value = true;
  syncResults.value = null;
  syncProgress.value = 0;
  syncProcessed.value = 0;
  syncTotal.value = 0;

  try {
    // Bước 1: Fetch từ API ngoài, cache lại, lấy tổng số
    const prepRes = await api.post('/employees/actions/sync-api/prepare', {
      company_id: syncForm.value.company_id,
      api_type: syncForm.value.api_type,
    });
    const { cache_key, total, ym } = prepRes.data.data;
    syncTotal.value = total;

    // Bước 2: Xử lý từng chunk 50 NV, cập nhật progress
    let offset = 0;
    let totalCreated = 0;
    let totalUpdated = 0;
    const allErrors = [];

    while (offset < total) {
      const execRes = await api.post('/employees/actions/sync-api/execute', {
        cache_key,
        company_id: syncForm.value.company_id,
        api_type: syncForm.value.api_type,
        offset,
      });
      const chunk = execRes.data.data;
      totalCreated += chunk.created;
      totalUpdated += chunk.updated;
      allErrors.push(...(chunk.errors || []));
      offset = chunk.processed;
      syncProcessed.value = offset;
      syncProgress.value = Math.min(100, Math.round((offset / total) * 100));
      if (chunk.done) break;
    }

    syncResults.value = {
      ym,
      api_type: syncForm.value.api_type,
      total,
      created: totalCreated,
      updated: totalUpdated,
      errors: allErrors,
    };
    toast.show(`Đồng bộ xong: +${totalCreated} mới, ~${totalUpdated} cập nhật`);
    fetch();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi đồng bộ API', 'error');
  } finally {
    syncing.value = false;
  }
}

function resetSync() {
  syncResults.value = null;
  syncProgress.value = 0;
  syncProcessed.value = 0;
  syncTotal.value = 0;
}

function closeSyncModal() {
  if (!syncing.value) {
    showSyncModal.value = false;
    resetSync();
  }
}

function onFileSelected(event) { importFile.value = event.target.files[0]; }

async function handleDownloadTemplate() {
  downloadingTemplate.value = true;
  try {
    await downloadApiGet('/employees/actions/template', {}, 'mau-ho-so-nhan-vien.xlsx');
  } catch {
    toast.show('Không thể tải file mẫu', 'error');
  } finally {
    downloadingTemplate.value = false;
  }
}

async function handleImport() {
  if (!importFile.value) { toast.show('Vui lòng chọn file CSV', 'error'); return; }
  importing.value = true;
  importResults.value = null;
  const formData = new FormData();
  formData.append('file', importFile.value);
  try {
    const { data } = await api.post('/employees/actions/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    importResults.value = data.data;
    if (data.data.imported > 0) {
      toast.show(`Đã nhập thành công ${data.data.imported} nhân sự!`);
      fetch();
    } else {
      toast.show('Không có nhân sự nào được nhập', 'warning');
    }
  } catch (e) {
    toast.show(e.response?.data?.message || 'Có lỗi xảy ra khi nhập dữ liệu', 'error');
  } finally {
    importing.value = false;
  }
}

onMounted(async () => {
  await scope.loadMeta();
  await loadFormMeta();
  fetch({ per_page: perPage.value });
});
</script>
