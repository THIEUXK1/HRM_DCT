<template>
  <div>
    <UiPageHeader title="Quản lý Phúc lợi" subtitle="Gói bảo hiểm · Phụ cấp · Đăng ký nhân viên" breadcrumb="Benefits">
      <template #actions>
        <button v-if="activeTab === 'plans'" type="button" class="hcm-btn-primary" @click="openCreatePlan">
          + Thêm gói phúc lợi
        </button>
        <button v-if="activeTab === 'enrollments'" type="button" class="hcm-btn-primary" @click="openEnrollModal">
          + Đăng ký nhân viên
        </button>
      </template>
    </UiPageHeader>

    <!-- Tab navigation -->
    <div class="mb-4 flex gap-1 border-b border-slate-200">
      <button v-for="tab in tabs" :key="tab.id" type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
        :class="activeTab === tab.id ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="activeTab = tab.id">
        {{ tab.icon }} {{ tab.label }}
      </button>
    </div>

    <!-- ── TAB: Tổng quan ── -->
    <div v-if="activeTab === 'summary'">
      <div v-if="summaryLoading" class="py-16 text-center text-slate-400">Đang tải...</div>
      <template v-else-if="summary">
        <!-- KPI cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
          <div class="hcm-card p-4">
            <p class="text-xs text-slate-500">Gói phúc lợi</p>
            <p class="text-2xl font-bold text-primary-700 mt-1">{{ summary.total_plans }}</p>
            <p class="text-xs text-slate-400 mt-0.5">đang hoạt động</p>
          </div>
          <div class="hcm-card p-4">
            <p class="text-xs text-slate-500">Lượt đăng ký</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ summary.total_enrolled }}</p>
            <p class="text-xs text-slate-400 mt-0.5">/ {{ summary.total_employees }} nhân viên</p>
          </div>
          <div class="hcm-card p-4">
            <p class="text-xs text-slate-500">Chi phí ước tính</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ formatMoney(summary.monthly_cost_est) }}</p>
            <p class="text-xs text-slate-400 mt-0.5">/ tháng (gói cố định)</p>
          </div>
          <div class="hcm-card p-4">
            <p class="text-xs text-slate-500">Nhóm phúc lợi</p>
            <p class="text-2xl font-bold text-slate-700 mt-1">{{ summary.by_category?.length }}</p>
            <p class="text-xs text-slate-400 mt-0.5">loại phúc lợi</p>
          </div>
        </div>

        <!-- By category -->
        <div class="hcm-card overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100 font-medium text-sm text-slate-700">Phân bổ theo loại phúc lợi</div>
          <table class="hcm-table w-full">
            <thead>
              <tr>
                <th>Loại</th>
                <th class="text-center">Số gói</th>
                <th class="text-center">Lượt đăng ký</th>
                <th>Gói trong nhóm</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cat in summary.by_category" :key="cat.category" class="hover:bg-slate-50">
                <td>
                  <span class="flex items-center gap-2">
                    <span>{{ categoryIcon(cat.category) }}</span>
                    <span class="font-medium">{{ cat.label }}</span>
                  </span>
                </td>
                <td class="text-center">{{ cat.plan_count }}</td>
                <td class="text-center">
                  <UiBadge variant="success">{{ cat.enrolled_count }}</UiBadge>
                </td>
                <td class="text-xs text-slate-500">
                  {{ summary.plans.filter(p => p.category === cat.category).map(p => p.name).join(' · ') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

    <!-- ── TAB: Gói phúc lợi ── -->
    <div v-if="activeTab === 'plans'">
      <!-- Category filter -->
      <div class="mb-3 flex flex-wrap gap-2">
        <button type="button"
          class="px-3 py-1 rounded-full text-xs font-medium border transition-colors"
          :class="filterCat === '' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-slate-600 border-slate-200'"
          @click="filterCat = ''">Tất cả</button>
        <button v-for="(label, key) in categories" :key="key" type="button"
          class="px-3 py-1 rounded-full text-xs font-medium border transition-colors"
          :class="filterCat === key ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-slate-600 border-slate-200'"
          @click="filterCat = key">
          {{ categoryIcon(key) }} {{ label }}
        </button>
      </div>

      <div class="hcm-card overflow-hidden">
        <table class="hcm-table w-full" v-if="filteredPlans.length">
          <thead>
            <tr>
              <th>Gói phúc lợi</th>
              <th>Loại</th>
              <th>Giá trị</th>
              <th class="text-center">Sau (ngày)</th>
              <th class="text-center">Đăng ký</th>
              <th>Thuế</th>
              <th>Trạng thái</th>
              <th class="w-24"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="plan in filteredPlans" :key="plan.id" class="hover:bg-slate-50">
              <td>
                <p class="font-medium">{{ plan.name }}</p>
                <p class="text-xs text-slate-400 font-mono">{{ plan.code }}</p>
              </td>
              <td>
                <span class="flex items-center gap-1 text-sm">
                  {{ categoryIcon(plan.category) }} {{ categories[plan.category] }}
                </span>
              </td>
              <td class="font-medium text-emerald-700">{{ formatPlanValue(plan) }}</td>
              <td class="text-center text-sm">{{ plan.eligible_after_days || 0 }}</td>
              <td class="text-center">
                <UiBadge variant="success">{{ plan.active_enrollments_count }}</UiBadge>
              </td>
              <td>
                <UiBadge :variant="plan.is_taxable ? 'warning' : 'default'">
                  {{ plan.is_taxable ? 'Tính thuế' : 'Miễn thuế' }}
                </UiBadge>
              </td>
              <td>
                <UiBadge :variant="plan.is_active ? 'success' : 'default'">
                  {{ plan.is_active ? 'Hoạt động' : 'Tạm dừng' }}
                </UiBadge>
              </td>
              <td>
                <div class="flex gap-2">
                  <button type="button" class="text-xs text-primary-600 hover:underline" @click="openEditPlan(plan)">Sửa</button>
                  <button type="button" class="text-xs text-slate-500 hover:underline" @click="openEnrollModal(plan)">+ Đăng ký</button>
                  <button type="button" class="text-xs text-red-500 hover:underline" @click="deletePlan(plan)">Xóa</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Chưa có gói phúc lợi" />
      </div>
    </div>

    <!-- ── TAB: Đăng ký nhân viên ── -->
    <div v-if="activeTab === 'enrollments'">
      <!-- Filters -->
      <div class="mb-3 flex flex-wrap gap-3">
        <select v-model="enrollFilter.plan_id" class="hcm-input text-sm" @change="loadEnrollments">
          <option value="">-- Tất cả gói --</option>
          <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <select v-model="enrollFilter.status" class="hcm-input text-sm" @change="loadEnrollments">
          <option value="">-- Tất cả trạng thái --</option>
          <option value="active">Đang hưởng</option>
          <option value="suspended">Tạm dừng</option>
          <option value="expired">Hết hạn</option>
          <option value="cancelled">Đã hủy</option>
        </select>
      </div>

      <div class="hcm-card overflow-hidden">
        <div v-if="enrollLoading" class="py-12 text-center text-slate-400">Đang tải...</div>
        <table class="hcm-table w-full" v-else-if="enrollments.length">
          <thead>
            <tr>
              <th>Nhân viên</th>
              <th>Gói phúc lợi</th>
              <th>Giá trị</th>
              <th>Ngày đăng ký</th>
              <th>Hết hạn</th>
              <th>Trạng thái</th>
              <th class="w-28"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in enrollments" :key="e.id" class="hover:bg-slate-50">
              <td>
                <p class="font-medium">{{ e.employee?.last_name }} {{ e.employee?.first_name }}</p>
                <p class="text-xs text-slate-400 font-mono">{{ e.employee?.employee_code }}</p>
              </td>
              <td>
                <p class="text-sm">{{ e.plan?.name }}</p>
                <p class="text-xs text-slate-400">{{ categories[e.plan?.category] }}</p>
              </td>
              <td class="text-sm text-emerald-700 font-medium">
                {{ e.override_value ? formatMoney(e.override_value) : formatPlanValue(e.plan) }}
                <span v-if="e.override_value" class="text-xs text-amber-600 ml-1">(tùy chỉnh)</span>
              </td>
              <td class="text-sm">{{ e.enrolled_at }}</td>
              <td class="text-sm">{{ e.expires_at || '—' }}</td>
              <td>
                <UiBadge :variant="statusVariant(e.status)">{{ statusLabel(e.status) }}</UiBadge>
              </td>
              <td>
                <div class="flex gap-2">
                  <button v-if="e.status === 'active'" type="button" class="text-xs text-amber-600 hover:underline"
                    @click="updateEnrollStatus(e, 'suspended')">Tạm dừng</button>
                  <button v-if="e.status === 'suspended'" type="button" class="text-xs text-emerald-600 hover:underline"
                    @click="updateEnrollStatus(e, 'active')">Kích hoạt</button>
                  <button v-if="e.status !== 'cancelled'" type="button" class="text-xs text-red-500 hover:underline"
                    @click="updateEnrollStatus(e, 'cancelled')">Hủy</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Chưa có đăng ký" />
      </div>
    </div>

    <!-- ── Modal: Tạo / Sửa gói ── -->
    <UiModal v-model="showPlanForm" :title="editingPlan ? 'Sửa gói phúc lợi' : 'Thêm gói phúc lợi mới'">
      <form class="space-y-3" @submit.prevent="savePlan">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-medium">Mã gói <span class="text-red-500">*</span></label>
            <input v-model="planForm.code" class="hcm-input mt-1 font-mono text-sm" required :disabled="!!editingPlan" />
          </div>
          <div>
            <label class="text-sm font-medium">Tên gói <span class="text-red-500">*</span></label>
            <input v-model="planForm.name" class="hcm-input mt-1" required />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-medium">Loại phúc lợi <span class="text-red-500">*</span></label>
            <select v-model="planForm.category" class="hcm-input mt-1" required>
              <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium">Công ty <span class="text-red-500">*</span></label>
            <select v-model="planForm.company_id" class="hcm-input mt-1" required>
              <option v-for="c in companiesList" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>
        <div>
          <label class="text-sm font-medium">Mô tả</label>
          <textarea v-model="planForm.description" class="hcm-input mt-1 min-h-[60px]" rows="2" />
        </div>
        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="text-sm font-medium">Loại giá trị <span class="text-red-500">*</span></label>
            <select v-model="planForm.value_type" class="hcm-input mt-1" required>
              <option v-for="(label, key) in valueTypes" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
          <div v-if="planForm.value_type !== 'reimbursement'">
            <label class="text-sm font-medium">Giá trị {{ planForm.value_type === 'percentage' ? '(%)' : '(VNĐ)' }}</label>
            <input v-model.number="planForm.value" type="number" min="0" class="hcm-input mt-1" />
          </div>
          <div>
            <label class="text-sm font-medium">Điều kiện (ngày)</label>
            <input v-model.number="planForm.eligible_after_days" type="number" min="0" class="hcm-input mt-1" placeholder="0 = ngay từ đầu" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-medium">Ngày hiệu lực</label>
            <input v-model="planForm.effective_date" type="date" class="hcm-input mt-1" />
          </div>
          <div>
            <label class="text-sm font-medium">Ngày hết hạn</label>
            <input v-model="planForm.expiry_date" type="date" class="hcm-input mt-1" />
          </div>
        </div>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input v-model="planForm.is_taxable" type="checkbox" class="rounded" />
            <span>Tính vào thu nhập chịu thuế</span>
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input v-model="planForm.is_active" type="checkbox" class="rounded" />
            <span>Đang hoạt động</span>
          </label>
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t">
          <button type="button" class="hcm-btn-secondary" @click="showPlanForm = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="saving">{{ saving ? 'Đang lưu...' : 'Lưu' }}</button>
        </div>
      </form>
    </UiModal>

    <!-- ── Modal: Đăng ký nhân viên ── -->
    <UiModal v-model="showEnrollForm" title="Đăng ký nhân viên vào gói phúc lợi" wide>
      <form class="space-y-3" @submit.prevent="doEnroll">
        <div>
          <label class="text-sm font-medium">Gói phúc lợi <span class="text-red-500">*</span></label>
          <select v-model="enrollForm.benefit_plan_id" class="hcm-input mt-1" required>
            <option value="">-- Chọn gói --</option>
            <option v-for="p in plans" :key="p.id" :value="p.id">
              {{ categoryIcon(p.category) }} {{ p.name }} ({{ formatPlanValue(p) }})
            </option>
          </select>
        </div>
        <EmployeeTargetPicker
          :key="enrollPickerKey"
          v-model:mode="enrollTargetMode"
          v-model:employee-ids="enrollForm.employee_ids"
          v-model:department-id="enrollDepartmentId"
          :employees="activeEmployees"
          :departments="departments"
          :allowed-modes="['multi', 'department']"
        />
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-medium">Ngày bắt đầu</label>
            <input v-model="enrollForm.enrolled_at" type="date" class="hcm-input mt-1" />
          </div>
          <div>
            <label class="text-sm font-medium">Ngày hết hạn</label>
            <input v-model="enrollForm.expires_at" type="date" class="hcm-input mt-1" />
          </div>
        </div>
        <div>
          <label class="text-sm font-medium">Giá trị tùy chỉnh (để trống = dùng mức gói)</label>
          <input v-model.number="enrollForm.override_value" type="number" min="0" class="hcm-input mt-1" placeholder="VD: 600000" />
        </div>
        <div>
          <label class="text-sm font-medium">Ghi chú</label>
          <input v-model="enrollForm.notes" class="hcm-input mt-1" />
        </div>

        <div v-if="enrollResult" class="p-3 rounded bg-emerald-50 border border-emerald-200 text-sm text-emerald-800">
          {{ enrollResult.message }}
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t">
          <button type="button" class="hcm-btn-secondary" @click="showEnrollForm = false">Đóng</button>
          <button type="submit" class="hcm-btn-primary" :disabled="saving">{{ saving ? 'Đang lưu...' : 'Đăng ký' }}</button>
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
import UiModal from '../../components/ui/UiModal.vue';
import EmployeeTargetPicker from '../../components/hr/EmployeeTargetPicker.vue';
import { useToast } from '../../composables/useToast';

const toast = useToast();

// ── State ──────────────────────────────────────────────────────────────────
const activeTab = ref('summary');
const tabs = [
  { id: 'summary',     label: 'Tổng quan',       icon: '📊' },
  { id: 'plans',       label: 'Gói phúc lợi',    icon: '📋' },
  { id: 'enrollments', label: 'Đăng ký NV',       icon: '👥' },
];

const plans       = ref([]);
const categories  = ref({});
const valueTypes  = ref({});
const companiesList = ref([]);
const employees   = ref([]);
const departments = ref([]);
const enrollments = ref([]);
const summary     = ref(null);

const summaryLoading = ref(false);
const enrollLoading  = ref(false);
const saving         = ref(false);

const filterCat = ref('');
const enrollFilter = ref({ plan_id: '', status: 'active' });

// Forms
const showPlanForm = ref(false);
const editingPlan  = ref(null);
const planForm     = ref(defaultPlanForm());

const showEnrollForm = ref(false);
const enrollForm     = ref(defaultEnrollForm());
const enrollResult   = ref(null);
const enrollTargetMode = ref('multi');
const enrollDepartmentId = ref('');
const enrollPickerKey = ref(0);

// ── Computed ───────────────────────────────────────────────────────────────
const filteredPlans = computed(() =>
  filterCat.value ? plans.value.filter(p => p.category === filterCat.value) : plans.value
);

const activeEmployees = computed(() =>
  employees.value.filter(
    (e) => e.is_active !== false && e.employment_status !== 'terminated',
  ),
);

// ── Load ───────────────────────────────────────────────────────────────────
async function loadPlans() {
  const { data } = await api.get('/benefits');
  plans.value      = data.data?.plans ?? [];
  categories.value = data.data?.categories ?? {};
  valueTypes.value = data.data?.value_types ?? {};
}

async function loadSummary() {
  summaryLoading.value = true;
  try {
    const { data } = await api.get('/benefits/summary');
    summary.value = data.data;
  } finally {
    summaryLoading.value = false;
  }
}

async function loadEnrollments() {
  enrollLoading.value = true;
  try {
    const params = {};
    if (enrollFilter.value.plan_id)  params.plan_id  = enrollFilter.value.plan_id;
    if (enrollFilter.value.status)   params.status   = enrollFilter.value.status;
    const { data } = await api.get('/benefits/enrollments', { params });
    enrollments.value = data.data ?? [];
  } finally {
    enrollLoading.value = false;
  }
}

async function loadMeta() {
  const [compRes, empRes, deptRes] = await Promise.allSettled([
    api.get('/companies'),
    api.get('/employees', { params: { per_page: 500 } }),
    api.get('/departments'),
  ]);
  if (compRes.status === 'fulfilled') companiesList.value = compRes.value.data.data || [];
  if (empRes.status === 'fulfilled') {
    const payload = empRes.value.data.data;
    employees.value = Array.isArray(payload) ? payload : (payload?.data ?? []);
  }
  if (deptRes.status === 'fulfilled') {
    departments.value = deptRes.value.data.data ?? [];
  }
}

// ── Plan CRUD ──────────────────────────────────────────────────────────────
function defaultPlanForm() {
  return {
    code: '', name: '', category: 'health', description: '',
    value_type: 'fixed', value: 0, currency: 'VND',
    eligible_after_days: 0, is_taxable: false, is_active: true,
    effective_date: '', expiry_date: '', company_id: null,
  };
}

function openCreatePlan() {
  editingPlan.value = null;
  planForm.value = { ...defaultPlanForm(), company_id: companiesList.value[0]?.id };
  showPlanForm.value = true;
}

function openEditPlan(plan) {
  editingPlan.value = plan;
  planForm.value = {
    ...plan,
    effective_date: plan.effective_date ?? '',
    expiry_date: plan.expiry_date ?? '',
  };
  showPlanForm.value = true;
}

async function savePlan() {
  saving.value = true;
  try {
    if (editingPlan.value) {
      await api.put(`/benefits/${editingPlan.value.id}`, planForm.value);
      toast.show('Đã cập nhật gói phúc lợi');
    } else {
      await api.post('/benefits', planForm.value);
      toast.show('Đã tạo gói phúc lợi mới');
    }
    showPlanForm.value = false;
    await Promise.all([loadPlans(), loadSummary()]);
  } catch (e) {
    toast.show(e.response?.data?.message || Object.values(e.response?.data?.errors || {})?.[0]?.[0] || 'Lỗi lưu dữ liệu', 'error');
  } finally {
    saving.value = false;
  }
}

async function deletePlan(plan) {
  if (!confirm(`Xóa gói "${plan.name}"?`)) return;
  try {
    await api.delete(`/benefits/${plan.id}`);
    toast.show('Đã xóa gói phúc lợi');
    await loadPlans();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không thể xóa', 'error');
  }
}

// ── Enrollment ─────────────────────────────────────────────────────────────
function defaultEnrollForm() {
  return {
    benefit_plan_id: '',
    employee_ids: [],
    enrolled_at: new Date().toISOString().slice(0, 10),
    expires_at: '',
    override_value: null,
    notes: '',
  };
}

function openEnrollModal(plan = null) {
  enrollResult.value = null;
  enrollTargetMode.value = 'multi';
  enrollDepartmentId.value = '';
  enrollPickerKey.value += 1;
  enrollForm.value = { ...defaultEnrollForm(), benefit_plan_id: plan?.id ?? '' };
  showEnrollForm.value = true;
}

function resolveEnrollEmployeeIds() {
  if (enrollTargetMode.value === 'department' && enrollDepartmentId.value) {
    const deptId = Number(enrollDepartmentId.value);
    return activeEmployees.value
      .filter((e) => Number(e.department_id) === deptId)
      .map((e) => Number(e.id));
  }
  return enrollForm.value.employee_ids.map((id) => Number(id));
}

async function doEnroll() {
  const employeeIds = resolveEnrollEmployeeIds();
  if (!employeeIds.length) {
    toast.show(
      enrollTargetMode.value === 'department'
        ? 'Phòng ban không có nhân viên đang làm việc'
        : 'Vui lòng chọn ít nhất 1 nhân viên',
      'error',
    );
    return;
  }
  saving.value = true;
  enrollResult.value = null;
  try {
    const payload = { ...enrollForm.value, employee_ids: employeeIds };
    if (!payload.override_value) delete payload.override_value;
    if (!payload.expires_at) delete payload.expires_at;
    const { data } = await api.post('/benefits/enroll', payload);
    enrollResult.value = data.data;
    toast.show(data.data.message);
    await Promise.all([loadPlans(), loadEnrollments()]);
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi đăng ký', 'error');
  } finally {
    saving.value = false;
  }
}

async function updateEnrollStatus(enrollment, status) {
  try {
    await api.put(`/benefits/enrollments/${enrollment.id}`, { status });
    enrollment.status = status;
    toast.show(`Đã ${status === 'active' ? 'kích hoạt' : status === 'suspended' ? 'tạm dừng' : 'hủy'} đăng ký`);
  } catch (e) {
    toast.show('Lỗi cập nhật', 'error');
  }
}

// ── Display helpers ─────────────────────────────────────────────────────────
function categoryIcon(cat) {
  const m = { health: '🏥', accident: '🦺', phone: '📱', transport: '🚗', meal: '🍱', housing: '🏠', equipment: '💻', childcare: '👶', bonus: '🎁', other: '📦' };
  return m[cat] || '📦';
}

function formatPlanValue(plan) {
  if (!plan) return '—';
  if (plan.value_type === 'reimbursement') return 'Hoàn trả thực tế';
  if (plan.value_type === 'percentage') return `${plan.value}% lương`;
  return formatMoney(plan.value);
}

function formatMoney(v) {
  if (!v && v !== 0) return '—';
  return Number(v).toLocaleString('vi-VN') + ' đ';
}

function statusLabel(s) {
  return { active: 'Đang hưởng', suspended: 'Tạm dừng', expired: 'Hết hạn', cancelled: 'Đã hủy' }[s] || s;
}

function statusVariant(s) {
  return { active: 'success', suspended: 'warning', expired: 'default', cancelled: 'danger' }[s] || 'default';
}

// ── Watch ──────────────────────────────────────────────────────────────────
watch(activeTab, (tab) => {
  if (tab === 'enrollments' && !enrollments.value.length) loadEnrollments();
});

// ── Init ───────────────────────────────────────────────────────────────────
onMounted(async () => {
  await Promise.allSettled([loadPlans(), loadSummary(), loadMeta()]);
});
</script>
