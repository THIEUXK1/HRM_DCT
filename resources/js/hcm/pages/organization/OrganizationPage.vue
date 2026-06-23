<template>
  <div>
    <UiPageHeader title="Cơ cấu tổ chức" subtitle="Công ty · Chi nhánh · Phòng ban · Chức danh · Loại hợp đồng" breadcrumb="Organization">
      <template #actions>
        <button type="button" class="hcm-btn-secondary mr-2" @click="openImport" title="Nhập cơ cấu tổ chức từ Excel">
          ↑ Import Excel
        </button>
        <button type="button" class="hcm-btn-primary" @click="openCreate">
          + Thêm {{ currentTabLabel }}
        </button>
      </template>
    </UiPageHeader>

    <!-- Tab navigation -->
    <div class="mb-4 flex gap-1 border-b border-slate-200 overflow-x-auto">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap"
        :class="activeTab === tab.id
          ? 'border-primary-600 text-primary-700'
          : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="activeTab = tab.id"
      >
        {{ tab.icon }} {{ tab.label }}
        <span class="ml-1 text-xs text-slate-400">({{ currentCount(tab.id) }})</span>
      </button>
    </div>

    <div class="hcm-card mb-4 p-4">
      <UiSearchInput
        v-model="listSearch"
        :placeholder="orgSearchPlaceholder"
      />
    </div>

    <!-- Data table -->
    <div class="hcm-card overflow-hidden">
      <div v-if="loading" class="py-12 text-center text-slate-400">Đang tải...</div>
      <template v-else>
        <table class="hcm-table w-full" v-if="filteredRows.length">
          <thead>
            <tr>
              <th>Mã</th>
              <th>Tên</th>
              <th v-if="activeTab === 'companies'">Gói chính sách</th>
              <th v-if="activeTab === 'companies'">MST / BHXH</th>
              <th v-if="activeTab === 'branches'">Công ty</th>
              <th v-if="activeTab === 'branches'">Địa chỉ</th>
              <th v-if="activeTab === 'departments' && !singleBranchMode">Chi nhánh</th>
              <th v-if="activeTab === 'departments'">Phòng ban cha</th>
              <th v-if="activeTab === 'positions'">Phòng ban</th>
              <th v-if="activeTab === 'positions'">Cấp bậc</th>
              <th v-if="activeTab === 'contract_types'">BHXH</th>
              <th v-if="activeTab === 'contract_types'">Thử việc</th>
              <th v-if="activeTab === 'contract_types'">Thời hạn</th>
              <th>Trạng thái</th>
              <th class="w-28">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in filteredRows" :key="row.id" class="hover:bg-slate-50">
              <td class="font-mono text-xs text-primary-700">{{ row.code }}</td>
              <td class="font-medium">{{ row.name }}</td>

              <!-- Company extras -->
              <td v-if="activeTab === 'companies'" class="text-xs">
                <UiBadge v-if="row.policy_template_code" variant="default">{{ policyTemplateLabel(row.policy_template_code) }}</UiBadge>
                <span v-else class="text-slate-400">—</span>
              </td>
              <td v-if="activeTab === 'companies'" class="text-xs text-slate-500">
                <p v-if="row.tax_code">MST: {{ row.tax_code }}</p>
                <p v-if="row.social_insurance_unit_code">BHXH: {{ row.social_insurance_unit_code }}</p>
              </td>

              <!-- Branch extras -->
              <td v-if="activeTab === 'branches'" class="text-sm text-slate-600">{{ row.company?.name || '—' }}</td>
              <td v-if="activeTab === 'branches'" class="text-xs text-slate-500 max-w-xs truncate">{{ row.address || '—' }}</td>

              <!-- Department extras -->
              <td v-if="activeTab === 'departments' && !singleBranchMode" class="text-sm">{{ row.branch?.name || '—' }}</td>
              <td v-if="activeTab === 'departments'" class="text-sm text-slate-500">{{ row.parent?.name || '—' }}</td>

              <!-- Position extras -->
              <td v-if="activeTab === 'positions'" class="text-sm">{{ row.department?.name || '—' }}</td>
              <td v-if="activeTab === 'positions'" class="text-xs text-slate-500">{{ row.level || '—' }}</td>

              <!-- Contract type extras -->
              <td v-if="activeTab === 'contract_types'">
                <UiBadge :variant="row.is_social_insurance ? 'success' : 'default'">
                  {{ row.is_social_insurance ? 'Có' : 'Không' }}
                </UiBadge>
              </td>
              <td v-if="activeTab === 'contract_types'">
                <UiBadge :variant="row.is_probation ? 'warning' : 'default'">
                  {{ row.is_probation ? 'Có' : 'Không' }}
                </UiBadge>
              </td>
              <td v-if="activeTab === 'contract_types'" class="text-sm">
                {{ row.default_duration_months ? `${row.default_duration_months} tháng` : 'Không thời hạn' }}
              </td>

              <td>
                <UiBadge :variant="row.is_active !== false ? 'success' : 'default'">
                  {{ row.is_active !== false ? 'Hoạt động' : 'Ngưng' }}
                </UiBadge>
              </td>

              <td>
                <div class="flex gap-2">
                  <button type="button" class="text-xs text-primary-600 hover:underline" @click="openEdit(row)">Sửa</button>
                  <button type="button" class="text-xs text-red-500 hover:underline" @click="confirmDelete(row)">Xóa</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else :title="`Chưa có ${currentTabLabel}`" />
      </template>
    </div>

    <!-- ── CRUD Modal ── -->
    <UiModal v-model="showForm" :title="editingRow ? `Sửa ${currentTabLabel}` : `Thêm ${currentTabLabel}`">
      <form class="space-y-3" @submit.prevent="save">

        <!-- Companies -->
        <template v-if="activeTab === 'companies'">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-medium">Mã công ty <span class="text-red-500">*</span></label>
              <input v-model="form.code" class="hcm-input mt-1" required :disabled="!!editingRow" />
            </div>
            <div>
              <label class="text-sm font-medium">Tên công ty <span class="text-red-500">*</span></label>
              <input v-model="form.name" class="hcm-input mt-1" required />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-medium">Gói chính sách</label>
              <select v-model="form.policy_template_code" class="hcm-input mt-1">
                <option value="">— Chọn sau / tùy chỉnh —</option>
                <option v-for="t in policyTemplates" :key="t.code" :value="t.code">{{ t.name }}</option>
              </select>
              <p class="text-xs text-slate-500 mt-1">Dệt · May · Kinh doanh — áp dụng ca, công, lương mẫu</p>
            </div>
            <div>
              <label class="text-sm font-medium">Ngành (mã)</label>
              <input v-model="form.industry_code" class="hcm-input mt-1 bg-slate-50" readonly placeholder="Tự điền theo gói" />
            </div>
          </div>
          <div v-if="editingRow && isAdmin" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
            <p class="font-medium text-amber-900 mb-2">Áp lại gói chính sách (admin)</p>
            <div class="flex flex-wrap gap-2 items-end">
              <select v-model="reapplyTemplateCode" class="hcm-input text-sm">
                <option value="">— Chọn gói —</option>
                <option v-for="t in policyTemplates" :key="'r'+t.code" :value="t.code">{{ t.name }}</option>
              </select>
              <label class="flex items-center gap-1 text-xs">
                <input v-model="reapplyOverwrite" type="checkbox" class="rounded" />
                Ghi đè cấu hình hiện có
              </label>
              <button type="button" class="hcm-btn-secondary text-xs" :disabled="!reapplyTemplateCode || reapplying" @click="reapplyPolicy">
                {{ reapplying ? 'Đang áp dụng…' : 'Áp dụng gói' }}
              </button>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-medium">Mã số thuế (MST)</label>
              <input v-model="form.tax_code" class="hcm-input mt-1" />
            </div>
            <div>
              <label class="text-sm font-medium">Mã đơn vị BHXH</label>
              <input v-model="form.social_insurance_unit_code" class="hcm-input mt-1" />
            </div>
          </div>
          <div>
            <label class="text-sm font-medium">Cơ quan BHXH quản lý</label>
            <input v-model="form.social_insurance_agency" class="hcm-input mt-1" />
          </div>
          <div>
            <label class="text-sm font-medium">Người đại diện pháp luật</label>
            <input v-model="form.legal_representative" class="hcm-input mt-1" />
          </div>
          <div>
            <label class="text-sm font-medium">Địa chỉ</label>
            <input v-model="form.address" class="hcm-input mt-1" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-medium">Điện thoại</label>
              <input v-model="form.phone" class="hcm-input mt-1" />
            </div>
            <div>
              <label class="text-sm font-medium">Email</label>
              <input v-model="form.email" type="email" class="hcm-input mt-1" />
            </div>
          </div>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input v-model="form.is_active" type="checkbox" class="rounded" />
            <span>Đang hoạt động</span>
          </label>
        </template>

        <!-- Branches -->
        <template v-if="activeTab === 'branches'">
          <div>
            <label class="text-sm font-medium">Thuộc công ty <span class="text-red-500">*</span></label>
            <select v-model="form.company_id" class="hcm-input mt-1" required>
              <option :value="null">-- Chọn công ty --</option>
              <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-medium">Mã chi nhánh <span class="text-red-500">*</span></label>
              <input v-model="form.code" class="hcm-input mt-1" required :disabled="!!editingRow" />
            </div>
            <div>
              <label class="text-sm font-medium">Tên chi nhánh <span class="text-red-500">*</span></label>
              <input v-model="form.name" class="hcm-input mt-1" required />
            </div>
          </div>
          <div>
            <label class="text-sm font-medium">Địa chỉ</label>
            <input v-model="form.address" class="hcm-input mt-1" />
          </div>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input v-model="form.is_active" type="checkbox" class="rounded" />
            <span>Đang hoạt động</span>
          </label>
        </template>

        <!-- Departments -->
        <template v-if="activeTab === 'departments'">
          <div v-if="!singleBranchMode">
            <label class="text-sm font-medium">Chi nhánh / địa điểm <span class="text-red-500">*</span></label>
            <select v-model="form.branch_id" class="hcm-input mt-1" required>
              <option :value="null" disabled>-- Chọn chi nhánh --</option>
              <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
            <p class="text-xs text-slate-500 mt-1">Chỉ cần chọn khi công ty có nhiều địa điểm.</p>
          </div>
          <div v-else class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
            Thuộc công ty: <strong>{{ currentCompanyLabel }}</strong>
            <span v-if="defaultBranchLabel"> · {{ defaultBranchLabel }}</span>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-medium">Mã phòng ban / bộ phận <span class="text-red-500">*</span></label>
              <input v-model="form.code" class="hcm-input mt-1" required :disabled="!!editingRow" />
            </div>
            <div>
              <label class="text-sm font-medium">Tên phòng ban / bộ phận <span class="text-red-500">*</span></label>
              <input v-model="form.name" class="hcm-input mt-1" required />
            </div>
          </div>
          <div>
            <label class="text-sm font-medium">Thuộc phòng ban cấp trên (nếu là bộ phận)</label>
            <select v-model="form.parent_department_id" class="hcm-input mt-1">
              <option :value="null">— Phòng ban cấp cao (không có cấp trên) —</option>
              <option
                v-for="d in parentDepartmentOptions"
                :key="d.id"
                :value="d.id"
              >{{ d.name }}</option>
            </select>
          </div>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input v-model="form.is_active" type="checkbox" class="rounded" />
            <span>Đang hoạt động</span>
          </label>
        </template>

        <!-- Positions -->
        <template v-if="activeTab === 'positions'">
          <div>
            <label class="text-sm font-medium">Phòng ban <span class="text-red-500">*</span></label>
            <select v-model="form.department_id" class="hcm-input mt-1" required>
              <option :value="null">-- Chọn phòng ban --</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-medium">Mã chức danh <span class="text-red-500">*</span></label>
              <input v-model="form.code" class="hcm-input mt-1" required :disabled="!!editingRow" />
            </div>
            <div>
              <label class="text-sm font-medium">Tên chức danh <span class="text-red-500">*</span></label>
              <input v-model="form.name" class="hcm-input mt-1" required />
            </div>
          </div>
          <div>
            <label class="text-sm font-medium">Cấp bậc / Level</label>
            <input v-model="form.level" class="hcm-input mt-1" placeholder="VD: L1, L2, Manager, Senior..." />
          </div>
          <div>
            <label class="text-sm font-medium">Mô tả công việc</label>
            <textarea v-model="form.job_description" class="hcm-input mt-1 min-h-[80px]" rows="3" />
          </div>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input v-model="form.is_active" type="checkbox" class="rounded" />
            <span>Đang hoạt động</span>
          </label>
        </template>

        <!-- Contract types -->
        <template v-if="activeTab === 'contract_types'">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-medium">Mã loại HĐ <span class="text-red-500">*</span></label>
              <input v-model="form.code" class="hcm-input mt-1 font-mono text-sm" required :disabled="!!editingRow"
                placeholder="indefinite, definite..." />
            </div>
            <div>
              <label class="text-sm font-medium">Tên loại hợp đồng <span class="text-red-500">*</span></label>
              <input v-model="form.name" class="hcm-input mt-1" required />
            </div>
          </div>
          <div>
            <label class="text-sm font-medium">Thời hạn mặc định (tháng)</label>
            <input v-model.number="form.default_duration_months" type="number" min="0" class="hcm-input mt-1"
              placeholder="Để trống nếu không thời hạn" />
          </div>
          <div class="space-y-2 border rounded p-3 bg-slate-50">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input v-model="form.is_social_insurance" type="checkbox" class="rounded" />
              <span>Có đóng BHXH/BHYT/BHTN</span>
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input v-model="form.is_probation" type="checkbox" class="rounded" />
              <span>Là hợp đồng thử việc</span>
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input v-model="form.is_active" type="checkbox" class="rounded" />
              <span>Đang hoạt động</span>
            </label>
          </div>
        </template>

        <div class="flex justify-end gap-2 pt-2 border-t">
          <button type="button" class="hcm-btn-secondary" @click="showForm = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="saving">
            {{ saving ? 'Đang lưu...' : 'Lưu' }}
          </button>
        </div>
      </form>
    </UiModal>

    <!-- ── Confirm Delete Modal ── -->
    <UiModal v-model="showDeleteConfirm" title="Xác nhận xóa">
      <div class="space-y-4">
        <div class="flex items-start gap-3 p-3 bg-red-50 border border-red-200 rounded-lg">
          <span class="text-red-500 text-xl mt-0.5">⚠️</span>
          <div>
            <p class="font-medium text-red-800">Bạn chắc muốn xóa?</p>
            <p class="text-sm text-red-700 mt-1">
              <strong>{{ deletingRow?.name }}</strong> ({{ deletingRow?.code }}) sẽ bị xóa vĩnh viễn.
              Hành động này không thể hoàn tác.
            </p>
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="hcm-btn-secondary" @click="showDeleteConfirm = false">Hủy</button>
          <button type="button" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors"
            :disabled="deleting" @click="doDelete">
            {{ deleting ? 'Đang xóa...' : 'Xóa' }}
          </button>
        </div>
      </div>
    </UiModal>

    <!-- ── Import Excel Modal ── -->
    <UiModal v-model="showImport" title="Import cơ cấu tổ chức từ Excel">
      <div class="space-y-4">
        <!-- Tab chọn loại import -->
        <div class="flex gap-2 border-b border-slate-200">
          <button
            v-for="t in importTabs"
            :key="t.id"
            type="button"
            class="px-3 py-1.5 text-sm font-medium border-b-2 -mb-px transition-colors"
            :class="importType === t.id ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500'"
            @click="importType = t.id"
          >
            {{ t.label }}
          </button>
        </div>

        <!-- Hướng dẫn cột theo loại -->
        <div class="text-xs bg-slate-50 border rounded p-3 space-y-1">
          <p class="font-semibold text-slate-700">Định dạng file Excel (.xlsx / .csv):</p>
          <template v-if="importType === 'departments'">
            <p class="text-slate-600">Cột A: <strong>Mã phòng ban</strong> · B: <strong>Tên phòng ban</strong> · C: Mã chi nhánh · D: Tên phòng ban cha</p>
          </template>
          <template v-if="importType === 'positions'">
            <p class="text-slate-600">Cột A: <strong>Mã chức danh</strong> · B: <strong>Tên chức danh</strong> · C: Mã phòng ban · D: Cấp bậc</p>
          </template>
          <template v-if="importType === 'branches'">
            <p class="text-slate-600">Cột A: <strong>Mã chi nhánh</strong> · B: <strong>Tên chi nhánh</strong> · C: Mã công ty · D: Địa chỉ</p>
          </template>
          <p class="text-slate-500 mt-1">Dòng đầu là tiêu đề, dữ liệu bắt đầu từ dòng 2.</p>
        </div>

        <div>
          <label class="text-sm font-medium block mb-1">Chọn file (.xlsx, .csv)</label>
          <input type="file" accept=".xlsx,.xls,.csv,.txt" class="hcm-input" @change="onImportFile" />
        </div>

        <!-- Kết quả import -->
        <div v-if="importResult" class="p-3 bg-slate-50 border rounded text-sm space-y-1 max-h-40 overflow-y-auto">
          <p class="font-semibold text-emerald-700">✓ Đã tạo: {{ importResult.imported }}</p>
          <p v-if="importResult.skipped > 0" class="font-semibold text-amber-700">⚠ Bỏ qua: {{ importResult.skipped }}</p>
          <ul v-if="importResult.errors?.length" class="text-xs text-red-600 list-disc pl-4 space-y-0.5 mt-1">
            <li v-for="(e, i) in importResult.errors" :key="i">{{ e }}</li>
          </ul>
        </div>

        <div class="flex justify-end gap-2 border-t pt-3">
          <button type="button" class="hcm-btn-secondary" @click="showImport = false">Đóng</button>
          <button type="button" class="hcm-btn-primary" :disabled="!importFile || importing" @click="doImport">
            {{ importing ? 'Đang nhập...' : 'Nhập dữ liệu' }}
          </button>
        </div>
      </div>
    </UiModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiModal from '../../components/ui/UiModal.vue';
import UiSearchInput from '../../components/ui/UiSearchInput.vue';
import { useToast } from '../../composables/useToast';
import { usePermission } from '../../composables/usePermission';
import { useAuthStore } from '../../stores/auth';

const toast = useToast();
const auth = useAuthStore();
const { hasAnyRole } = usePermission();
const isAdmin = computed(() => hasAnyRole(['admin']));

// ── State ──────────────────────────────────────────────────────────────────
const loading  = ref(false);
const activeTab = ref('companies');
const listSearch = ref('');

const companies    = ref([]);
const branches     = ref([]);
const departments  = ref([]);
const positions    = ref([]);
const contractTypes = ref([]);
const policyTemplates = ref([]);
const reapplyTemplateCode = ref('');
const reapplyOverwrite = ref(false);
const reapplying = ref(false);

const tabs = [
  { id: 'companies',      label: 'Công ty',       icon: '🏢' },
  { id: 'branches',       label: 'Chi nhánh',     icon: '🏭' },
  { id: 'departments',    label: 'Phòng ban',      icon: '🗂️' },
  { id: 'positions',      label: 'Chức danh',      icon: '👔' },
  { id: 'contract_types', label: 'Loại hợp đồng', icon: '📋' },
];

const importTabs = [
  { id: 'branches',    label: 'Chi nhánh' },
  { id: 'departments', label: 'Phòng ban' },
  { id: 'positions',   label: 'Chức danh' },
];

// ── CRUD form ──────────────────────────────────────────────────────────────
const showForm  = ref(false);
const editingRow = ref(null);
const form      = ref({});
const saving    = ref(false);

// ── Delete ─────────────────────────────────────────────────────────────────
const showDeleteConfirm = ref(false);
const deletingRow = ref(null);
const deleting    = ref(false);

// ── Import ─────────────────────────────────────────────────────────────────
const showImport  = ref(false);
const importType  = ref('departments');
const importFile  = ref(null);
const importing   = ref(false);
const importResult = ref(null);

// ── Computed ───────────────────────────────────────────────────────────────
const dataMap = computed(() => ({
  companies:      companies.value,
  branches:       branches.value,
  departments:    departments.value,
  positions:      positions.value,
  contract_types: contractTypes.value,
}));

const currentRows = computed(() => dataMap.value[activeTab.value] || []);
const orgSearchPlaceholder = computed(() => {
  const map = {
    companies: 'Tìm theo tên hoặc mã công ty...',
    branches: 'Tìm theo tên hoặc mã chi nhánh...',
    departments: 'Tìm theo tên hoặc mã phòng ban...',
    positions: 'Tìm theo tên hoặc mã chức danh...',
    contract_types: 'Tìm theo tên hoặc mã loại HĐ...',
  };
  return map[activeTab.value] || 'Tìm theo tên hoặc mã...';
});

const filteredRows = computed(() => {
  const q = listSearch.value.trim().toLowerCase();
  const rows = currentRows.value;
  if (!q) return rows;
  return rows.filter((row) => {
    const name = String(row.name || row.title || '').toLowerCase();
    const code = String(row.code || row.contract_type || '').toLowerCase();
    return name.includes(q) || code.includes(q);
  });
});
watch(activeTab, () => { listSearch.value = ''; });
const currentTabLabel = computed(() => tabs.find(t => t.id === activeTab.value)?.label || '');
const currentCount = (tabId) => dataMap.value[tabId]?.length ?? 0;

const singleBranchMode = computed(() => branches.value.length <= 1);

const defaultBranchLabel = computed(() => branches.value[0]?.name || 'Trụ sở chính');

const currentCompanyLabel = computed(() => {
  const id = auth.companyId ? Number(auth.companyId) : null;
  const fromList = companies.value.find((c) => c.id === id);
  return fromList?.name || branches.value[0]?.company?.name || 'Công ty đang chọn';
});

const parentDepartmentOptions = computed(() => {
  const branchId = form.value.branch_id;
  return departments.value.filter((d) => {
    if (d.parent_department_id) return false;
    if (editingRow.value?.id && d.id === editingRow.value.id) return false;
    if (branchId && d.branch_id !== branchId) return false;

    return true;
  });
});

// ── Load ───────────────────────────────────────────────────────────────────
async function ensureCompanyDefaultBranch() {
  try {
    await api.post('/branches/ensure-default');
  } catch {
    // backend sẽ tự gán khi POST /departments
  }
}

async function load() {
  loading.value = true;
  try {
    await ensureCompanyDefaultBranch();
    const [c, b, d, p, ct] = await Promise.all([
      api.get('/companies'),
      api.get('/branches'),
      api.get('/departments'),
      api.get('/positions'),
      api.get('/contract-types'),
    ]);
    companies.value     = c.data.data || [];
    branches.value      = b.data.data || [];
    departments.value   = d.data.data || [];
    positions.value     = p.data.data || [];
    contractTypes.value = ct.data.data || [];
  } finally {
    loading.value = false;
  }
}

// ── CRUD helpers ───────────────────────────────────────────────────────────
const endpointMap = {
  companies:      '/companies',
  branches:       '/branches',
  departments:    '/departments',
  positions:      '/positions',
  contract_types: '/contract-types',
};

function defaultForm() {
  const base = { name: '', code: '', is_active: true };
  switch (activeTab.value) {
    case 'companies':      return { ...base, tax_code: '', social_insurance_unit_code: '', social_insurance_agency: '', legal_representative: '', address: '', phone: '', email: '', policy_template_code: '', industry_code: '' };
    case 'branches':       return { ...base, company_id: companies.value[0]?.id ?? null, address: '' };
    case 'departments':    return { ...base, branch_id: branches.value[0]?.id ?? null, parent_department_id: null };
    case 'positions':      return { ...base, department_id: null, level: '', job_description: '' };
    case 'contract_types': return { ...base, default_duration_months: null, is_social_insurance: true, is_probation: false };
    default: return base;
  }
}

function openCreate() {
  editingRow.value = null;
  form.value = defaultForm();
  showForm.value = true;
}

function openEdit(row) {
  editingRow.value = row;
  form.value = { ...row, policy_template_code: row.policy_template_code || '', industry_code: row.industry_code || '' };
  reapplyTemplateCode.value = row.policy_template_code || '';
  reapplyOverwrite.value = false;
  showForm.value = true;
}

function policyTemplateLabel(code) {
  return policyTemplates.value.find((t) => t.code === code)?.name || code;
}

async function loadPolicyTemplates() {
  try {
    const { data } = await api.get('/policy-templates');
    policyTemplates.value = data.data || [];
  } catch {
    policyTemplates.value = [];
  }
}

async function reapplyPolicy() {
  if (!editingRow.value?.id || !reapplyTemplateCode.value) return;
  if (reapplyOverwrite.value && !window.confirm('Ghi đè toàn bộ cấu hình chính sách hiện có của công ty này?')) return;
  reapplying.value = true;
  try {
    await api.post(`/companies/${editingRow.value.id}/apply-policy-template`, {
      template_code: reapplyTemplateCode.value,
      overwrite: reapplyOverwrite.value,
    });
    toast.show('Đã áp dụng gói chính sách');
    showForm.value = false;
    await load();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Áp dụng gói thất bại', 'error');
  } finally {
    reapplying.value = false;
  }
}

watch(
  () => form.value.policy_template_code,
  (code) => {
    if (!code) return;
    const t = policyTemplates.value.find((x) => x.code === code);
    if (t) form.value.industry_code = t.industry_code || code;
  },
);

watch(
  () => form.value.branch_id,
  (branchId) => {
    if (activeTab.value !== 'departments') return;
    const parentId = form.value.parent_department_id;
    if (!parentId || !branchId) return;
    const parent = departments.value.find((d) => d.id === parentId);
    if (parent && parent.branch_id !== branchId) {
      form.value.parent_department_id = null;
    }
  },
);

function buildSavePayload() {
  const f = form.value;
  switch (activeTab.value) {
    case 'companies':
      return {
        code: f.code,
        name: f.name,
        policy_template_code: f.policy_template_code || null,
        industry_code: f.industry_code || null,
        tax_code: f.tax_code,
        social_insurance_unit_code: f.social_insurance_unit_code,
        social_insurance_agency: f.social_insurance_agency,
        legal_representative: f.legal_representative,
        address: f.address,
        phone: f.phone,
        email: f.email,
        is_active: f.is_active !== false,
      };
    case 'branches':
      return {
        company_id: f.company_id,
        code: f.code,
        name: f.name,
        address: f.address,
        is_active: f.is_active !== false,
      };
    case 'departments': {
      const payload = {
        code: f.code,
        name: f.name,
        parent_department_id: f.parent_department_id || null,
        is_active: f.is_active !== false,
      };
      if (!singleBranchMode.value && f.branch_id) {
        payload.branch_id = f.branch_id;
      }
      return payload;
    }
    case 'positions':
      return {
        department_id: f.department_id,
        code: f.code,
        name: f.name,
        level: f.level,
        job_description: f.job_description,
        is_active: f.is_active !== false,
      };
    case 'contract_types':
      return {
        code: f.code,
        name: f.name,
        default_duration_months: f.default_duration_months,
        is_social_insurance: f.is_social_insurance,
        is_probation: f.is_probation,
        is_active: f.is_active !== false,
      };
    default:
      return { ...f };
  }
}

async function save() {
  saving.value = true;
  const endpoint = endpointMap[activeTab.value];
  const payload = buildSavePayload();
  if (activeTab.value === 'departments' && !singleBranchMode.value && !payload.branch_id) {
    toast.show('Vui lòng chọn chi nhánh / địa điểm', 'error');
    saving.value = false;
    return;
  }
  try {
    if (editingRow.value) {
      await api.put(`${endpoint}/${editingRow.value.id}`, payload);
      toast.show(`Đã cập nhật ${currentTabLabel.value}`);
    } else {
      await api.post(endpoint, payload);
      toast.show(`Đã thêm ${currentTabLabel.value}`);
    }
    showForm.value = false;
    await load();
  } catch (e) {
    const msg = e.response?.data?.message
      || Object.values(e.response?.data?.errors || {}).flat()[0]
      || 'Lỗi lưu dữ liệu';
    toast.show(msg, 'error');
  } finally {
    saving.value = false;
  }
}

// ── Delete ─────────────────────────────────────────────────────────────────
function confirmDelete(row) {
  deletingRow.value = row;
  showDeleteConfirm.value = true;
}

async function doDelete() {
  if (!deletingRow.value) return;
  deleting.value = true;
  const endpoint = endpointMap[activeTab.value];
  try {
    await api.delete(`${endpoint}/${deletingRow.value.id}`);
    toast.show(`Đã xóa ${currentTabLabel.value}`);
    showDeleteConfirm.value = false;
    deletingRow.value = null;
    await load();
  } catch (e) {
    const msg = e.response?.data?.message || 'Không thể xóa. Có thể còn dữ liệu liên quan.';
    toast.show(msg, 'error');
  } finally {
    deleting.value = false;
  }
}

// ── Import ─────────────────────────────────────────────────────────────────
function openImport() {
  importResult.value = null;
  importFile.value   = null;
  importType.value   = activeTab.value === 'contract_types' || activeTab.value === 'companies'
    ? 'departments'
    : activeTab.value;
  showImport.value   = true;
}

function onImportFile(e) {
  importFile.value   = e.target.files[0] ?? null;
  importResult.value = null;
}

async function doImport() {
  if (!importFile.value) return;
  importing.value    = true;
  importResult.value = null;

  const formData = new FormData();
  formData.append('file', importFile.value);
  formData.append('type', importType.value);

  try {
    const { data } = await api.post('/org-structure/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    importResult.value = data.data;
    if (data.data.imported > 0) {
      toast.show(`Đã nhập ${data.data.imported} bản ghi`);
      await load();
    }
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi import', 'error');
  } finally {
    importing.value = false;
  }
}

onMounted(async () => {
  await loadPolicyTemplates();
  await load();
});
</script>
