<template>
  <div>
    <UiPageHeader title="Nhân viên" subtitle="Quản lý hồ sơ nhân sự" breadcrumb="Core HR">
      <template #actions>
        <button type="button" class="hcm-btn-secondary mr-2" @click="triggerExport">Xuất Excel</button>
        <button type="button" class="hcm-btn-secondary mr-2" @click="showImportModal = true">Nhập Excel</button>
        <EmployeeCardPrint v-if="selectedIds.size > 0" :employees="selectedEmployees" />
        <button type="button" class="hcm-btn-primary" @click="openCreate">+ Thêm nhân viên</button>
      </template>
    </UiPageHeader>

    <div class="hcm-card mb-4 p-4 space-y-4">
      <UiOrgScopeFilters
        show-company
        show-status
        :show-company-picker="scope.showCompanyPicker"
        :single-branch-mode="scope.singleBranchMode"
        v-model:filter-company-id="filterCompanyId"
        v-model:filter-branch-id="filterBranchId"
        v-model:filter-department-id="filterDepartmentId"
        v-model:filter-status="filterStatus"
        :branches="scope.branches"
        :filtered-departments="scope.filteredDepartments"
        @company-change="onScopeCompanyChange"
        @change="applyScopeFilters"
        @reset="resetScopeFilters"
      />
      <UiSearchInput
        v-model="search"
        placeholder="Tìm theo tên, mã NV, email..."
        :hint="`${meta.total} nhân viên`"
        @search="onSearch"
      />
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
                <UiBadge :variant="e.employment_status === 'active' ? 'success' : 'warning'">
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
    <UiModal v-model="showForm" :title="editing ? 'Sửa nhân viên' : 'Thêm nhân viên'">
      <form class="space-y-3" @submit.prevent="save">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-medium">Họ</label>
            <input v-model="form.first_name" class="hcm-input mt-1" required />
          </div>
          <div>
            <label class="text-sm font-medium">Tên</label>
            <input v-model="form.last_name" class="hcm-input mt-1" required />
          </div>
        </div>
        <div>
          <label class="text-sm font-medium">Email</label>
          <input v-model="form.email" type="email" class="hcm-input mt-1" required />
        </div>
        <div>
          <label class="text-sm font-medium">Mã NV</label>
          <input v-model="form.employee_code" class="hcm-input mt-1" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-medium">Phòng ban</label>
            <select v-model="form.department_id" class="hcm-input mt-1" required>
              <option v-for="d in scope.filteredDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium">Chức danh</label>
            <select v-model="form.position_id" class="hcm-input mt-1" required>
              <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="hcm-btn-secondary" @click="showForm = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="saving">{{ saving ? 'Đang lưu...' : 'Lưu' }}</button>
        </div>
      </form>
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

const { items, meta, loading, fetch, changePage, setFilter, setFilters } = usePagination(api, '/employees');
const scope = useOrgScopeFilters({ includeStatus: true });
// Destructure filter refs to top-level so Vue v-model can correctly assign .value
const { filterCompanyId, filterBranchId, filterDepartmentId, filterStatus } = scope;

const positions = ref([]);
const companies = ref([]);
const search = ref('');

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
const { downloadApiGet } = useFileDownload();

function emptyForm() {
  return {
    company_id: null, branch_id: null, department_id: null, position_id: null,
    employee_code: '', first_name: '', last_name: '', full_name: '', email: '',
    employment_status: 'active', is_active: true,
  };
}

async function loadFormMeta() {
  const [p, c] = await Promise.all([
    api.get('/positions'),
    api.get('/companies'),
  ]);
  positions.value = p.data.data;
  companies.value = c.data.data;
}

function applyScopeFilters() {
  const extra = {};
  if (search.value.trim()) extra.search = search.value.trim();
  scope.applyToPagination(setFilters, extra);
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
  const company = companies.value[0];
  if (company) {
    form.value.company_id = company.id;
    form.value.branch_id = scope.branches.value[0]?.id;
    form.value.department_id = scope.filteredDepartments.value[0]?.id;
    form.value.position_id = positions.value[0]?.id;
  }
  showForm.value = true;
}

async function save() {
  saving.value = true;
  form.value.full_name = `${form.value.first_name} ${form.value.last_name}`.trim();
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
  fetch();
});
</script>
