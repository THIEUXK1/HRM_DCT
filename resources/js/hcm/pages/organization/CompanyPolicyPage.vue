<template>
  <div>
    <UiPageHeader
      title="Chính sách công ty"
      subtitle="Cấu hình chấm công · phép · lương theo từng công ty thành viên"
      breadcrumb="Tổ chức"
    >
      <template #actions>
        <button type="button" class="hcm-btn-secondary text-sm" :disabled="loading" @click="load">Tải lại</button>
        <button v-if="canManage" type="button" class="hcm-btn-secondary text-sm" @click="exportPolicy">Xuất JSON</button>
      </template>
    </UiPageHeader>

    <div v-if="loading" class="py-16 text-center text-slate-400">Đang tải chính sách...</div>
    <div v-else-if="error" class="hcm-card p-6 text-center text-rose-600">{{ error }}</div>
    <template v-else-if="overview">
      <div class="mb-4 hcm-card p-4 bg-slate-50 border border-slate-200 text-sm">
        <p class="font-semibold text-slate-800">{{ overview.company.name }} ({{ overview.company.code }})</p>
        <p class="text-slate-600 mt-1">
          Gói:
          <UiBadge variant="default">{{ overview.template?.name || overview.company.policy_template_code || 'Chưa gán' }}</UiBadge>
          <span v-if="overview.company.policy_applied_at" class="text-xs text-slate-500 ml-2">
            Áp dụng {{ formatDate(overview.company.policy_applied_at) }}
          </span>
        </p>
      </div>

      <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
        <button
          v-for="t in tabs"
          :key="t.key"
          type="button"
          class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
          :class="tab === t.key ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500'"
          @click="switchTab(t.key)"
        >
          {{ t.icon }} {{ t.label }}
        </button>
      </div>

      <!-- Tổng quan -->
      <div v-if="tab === 'overview'" class="grid gap-4 lg:grid-cols-3">
        <div class="hcm-card p-5">
          <p class="text-xs text-slate-500">Nhóm ca làm việc</p>
          <p class="text-2xl font-bold">{{ overview.work_schedule_summary.groups }}</p>
          <router-link :to="{ name: 'work-schedules' }" class="text-xs text-primary-600 mt-2 inline-block">Quản lý ca →</router-link>
        </div>
        <div class="hcm-card p-5">
          <p class="text-xs text-slate-500">Công thức lương active</p>
          <p class="text-2xl font-bold">{{ overview.formula_rules_count }}</p>
          <router-link :to="{ name: 'payroll' }" class="text-xs text-primary-600 mt-2 inline-block">Bảng lương →</router-link>
        </div>
        <div class="hcm-card p-5">
          <p class="text-xs text-slate-500">Ngày công chuẩn</p>
          <p class="text-2xl font-bold">{{ settingValue('attendance', 'standard_working_days') || '—' }}</p>
        </div>
      </div>

      <!-- Miền chính sách -->
      <div v-else-if="domainData && ['attendance', 'leave', 'payroll'].includes(tab)" class="hcm-card p-5">
        <form class="space-y-4 max-w-2xl" @submit.prevent="saveDomain">
          <div v-for="item in domainForm" :key="item.key" class="grid grid-cols-1 sm:grid-cols-2 gap-2 items-center border-b border-slate-100 pb-3">
            <label class="text-sm text-slate-700">{{ item.label }}</label>
            <input
              v-model="formSettings[item.key]"
              class="hcm-input text-sm"
              :disabled="!canManage"
              :type="isToggleKey(item.key) ? 'text' : 'text'"
              :placeholder="isToggleKey(item.key) ? '1 hoặc 0' : ''"
            />
          </div>
          <div v-if="canManage" class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="text-xs font-medium">Áp dụng từ ngày</label>
              <input v-model="effectiveFrom" type="date" class="hcm-input mt-1 w-full text-sm" />
            </div>
            <div>
              <label class="text-xs font-medium">Ghi chú</label>
              <input v-model="saveNotes" class="hcm-input mt-1 w-full text-sm" placeholder="Tuỳ chọn" />
            </div>
          </div>

          <EmployeeTargetPicker
            v-if="canManage"
            v-model:mode="policyTargetMode"
            v-model:employee-id="policyEmployeeId"
            v-model:employee-ids="policyEmployeeIds"
            v-model:department-id="policyDepartmentId"
            :employees="policyEmployees"
            :departments="policyDepartments"
            include-company-mode
          />

          <div v-if="canManage" class="flex flex-wrap gap-2">
            <button type="submit" class="hcm-btn-primary" :disabled="saving">
              {{ saving ? 'Đang lưu…' : 'Lưu chính sách' }}
            </button>
          </div>
          <p v-if="!canManage" class="text-xs text-amber-700">Bạn chỉ xem — cần quyền «company_policies.manage» để sửa.</p>
        </form>

        <div v-if="employeeOverrides.length" class="mt-6 border-t border-slate-100 pt-4">
          <h4 class="text-sm font-semibold mb-2">NV có chính sách riêng ({{ tab }})</h4>
          <div class="overflow-x-auto max-h-48">
            <table class="hcm-table w-full text-xs">
              <thead>
                <tr>
                  <th>NV</th>
                  <th>Phòng ban</th>
                  <th>Thiết lập</th>
                  <th>Từ ngày</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in employeeOverrides" :key="row.id">
                  <td>{{ row.full_name }} ({{ row.employee_code }})</td>
                  <td>{{ row.department || '—' }}</td>
                  <td class="font-mono">{{ row.key }} = {{ row.value }}</td>
                  <td>{{ row.effective_from }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Lịch sử -->
      <div v-else-if="tab === 'history'" class="hcm-card overflow-x-auto">
        <table v-if="versions.length" class="hcm-table w-full text-sm">
          <thead>
            <tr>
              <th>Miền</th>
              <th>Từ ngày</th>
              <th>Người sửa</th>
              <th>Ghi chú</th>
              <th>Thời điểm</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="v in versions" :key="v.id">
              <td>{{ domainLabel(v.domain) }}</td>
              <td>{{ v.effective_from }}</td>
              <td>{{ v.applied_by || '—' }}</td>
              <td class="text-xs text-slate-500">{{ v.notes || '—' }}</td>
              <td class="text-xs">{{ formatDate(v.created_at) }}</td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Chưa có lịch sử thay đổi" />
      </div>

      <!-- So sánh tập đoàn -->
      <div v-else-if="tab === 'group'" class="space-y-4">
        <div v-if="groupLoading" class="py-12 text-center text-slate-400">Đang tải...</div>
        <div v-else-if="groupComparison" class="hcm-card overflow-x-auto">
          <table class="hcm-table w-full text-sm">
            <thead>
              <tr>
                <th>Công ty</th>
                <th>Gói</th>
                <th>Công chuẩn</th>
                <th>Phép năm</th>
                <th>GPS strict</th>
                <th>Thưởng DS</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in groupComparison.companies" :key="row.company_id">
                <td>
                  <p class="font-medium">{{ row.company_name }}</p>
                  <p class="text-xs text-slate-400">{{ row.company_code }}</p>
                </td>
                <td>{{ row.policy_template_name || row.policy_template_code || '—' }}</td>
                <td>{{ row.policy.standard_working_days }}</td>
                <td>{{ row.policy.annual_leave_standard }}</td>
                <td>{{ row.policy.attendance_geofence_strict === '1' ? 'Có' : 'Không' }}</td>
                <td>{{ row.policy.sales_commission_enabled === '1' ? 'Có' : 'Không' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <UiEmpty v-else title="Không có dữ liệu so sánh" />
      </div>
    </template>
    <UiEmpty v-else title="Không tải được chính sách" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import EmployeeTargetPicker from '../../components/hr/EmployeeTargetPicker.vue';
import { extractItems } from '../../composables/usePagination';
import { useToast } from '../../composables/useToast';
import { usePermission } from '../../composables/usePermission';

const toast = useToast();
const { can, hasAnyRole } = usePermission();
const canManage = computed(() => hasAnyRole(['admin']) || can('company_policies.manage'));

const tabs = [
  { key: 'overview', label: 'Tổng quan', icon: '📋' },
  { key: 'attendance', label: 'Chấm công', icon: '⏱️' },
  { key: 'leave', label: 'Nghỉ phép', icon: '🏖️' },
  { key: 'payroll', label: 'Lương & OT', icon: '💰' },
  { key: 'history', label: 'Lịch sử', icon: '📜' },
  { key: 'group', label: 'So sánh CTTV', icon: '🏢' },
];

const tab = ref('overview');
const loading = ref(false);
const error = ref('');
const saving = ref(false);
const overview = ref(null);
const domainData = ref(null);
const formSettings = ref({});
const effectiveFrom = ref(new Date().toISOString().slice(0, 10));
const saveNotes = ref('');
const versions = ref([]);
const groupComparison = ref(null);
const groupLoading = ref(false);
const policyEmployees = ref([]);
const policyDepartments = ref([]);
const policyTargetMode = ref('company');
const policyEmployeeId = ref(null);
const policyEmployeeIds = ref([]);
const policyDepartmentId = ref('');
const employeeOverrides = ref([]);

const domainLabels = {
  attendance: 'Chấm công',
  leave: 'Nghỉ phép',
  payroll: 'Lương & OT',
};

const domainForm = computed(() => domainData.value?.settings || []);

function domainLabel(d) {
  return domainLabels[d] || d;
}

function formatDate(iso) {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString('vi-VN');
  } catch {
    return iso;
  }
}

function settingValue(domain, key) {
  const items = overview.value?.domains?.[domain]?.settings || [];
  return items.find((i) => i.key === key)?.value;
}

function isToggleKey(key) {
  return key.includes('_enabled') || key.includes('_strict');
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/company-policies');
    overview.value = data.data;
    versions.value = overview.value.recent_versions || [];
  } catch (e) {
    error.value = e.response?.data?.message || 'Không tải được chính sách công ty';
    overview.value = null;
  } finally {
    loading.value = false;
  }
}

async function loadPolicyEmployees() {
  const [empRes, deptRes] = await Promise.all([
    api.get('/employees', { params: { per_page: 500 } }),
    api.get('/departments'),
  ]);
  policyEmployees.value = extractItems(empRes.data);
  policyDepartments.value = deptRes.data.data || [];
}

async function loadEmployeeOverrides(domain) {
  try {
    const { data } = await api.get('/company-policies/employee-overrides', { params: { domain } });
    employeeOverrides.value = (data.data?.rows || []).filter((r) => r.domain === domain);
  } catch {
    employeeOverrides.value = [];
  }
}

async function loadDomain(domain) {
  try {
    const { data } = await api.get(`/company-policies/domains/${domain}`);
    domainData.value = data.data;
    const map = {};
    (data.data.settings || []).forEach((s) => {
      map[s.key] = s.value;
    });
    formSettings.value = map;
    await loadEmployeeOverrides(domain);
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không tải miền chính sách', 'error');
  }
}

async function loadGroupComparison() {
  groupLoading.value = true;
  try {
    const { data } = await api.get('/company-policies/group-comparison');
    groupComparison.value = data.data;
  } catch {
    groupComparison.value = null;
  } finally {
    groupLoading.value = false;
  }
}

function switchTab(key) {
  tab.value = key;
  if (['attendance', 'leave', 'payroll'].includes(key)) {
    if (!policyEmployees.value.length) loadPolicyEmployees();
    loadDomain(key);
  }
  if (key === 'history') {
    api.get('/company-policies/versions').then(({ data }) => {
      versions.value = data.data?.versions || [];
    });
  }
  if (key === 'group') {
    loadGroupComparison();
  }
}

function buildPolicyTargetPayload() {
  const base = {
    domain: tab.value,
    settings: formSettings.value,
    effective_from: effectiveFrom.value,
    notes: saveNotes.value || null,
  };
  if (policyTargetMode.value === 'company') {
    return null;
  }
  if (policyTargetMode.value === 'single' && policyEmployeeId.value) {
    return { ...base, employee_id: policyEmployeeId.value };
  }
  if (policyTargetMode.value === 'multi' && policyEmployeeIds.value.length) {
    return { ...base, employee_ids: policyEmployeeIds.value.map((id) => Number(id)) };
  }
  if (policyTargetMode.value === 'department' && policyDepartmentId.value) {
    return { ...base, department_id: Number(policyDepartmentId.value) };
  }
  throw new Error('Chọn đối tượng áp dụng chính sách (NV hoặc phòng ban).');
}

async function saveDomain() {
  if (!canManage.value) return;
  saving.value = true;
  try {
    if (policyTargetMode.value === 'company') {
      await api.put(`/company-policies/domains/${tab.value}`, {
        settings: formSettings.value,
        effective_from: effectiveFrom.value,
        notes: saveNotes.value || null,
      });
      toast.show('Đã lưu chính sách toàn công ty');
    } else {
      const employeePayload = buildPolicyTargetPayload();
      const { data } = await api.post('/company-policies/apply-to-employees', employeePayload);
      toast.show(`Đã áp dụng chính sách cho ${data.data?.applied_count || 0} nhân viên`);
    }
    await load();
    await loadDomain(tab.value);
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lưu thất bại', 'error');
  } finally {
    saving.value = false;
  }
}

async function exportPolicy() {
  try {
    const { data } = await api.get('/company-policies/export');
    const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `policy-${data.data.company_code || 'company'}.json`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (e) {
    toast.show(e.response?.data?.message || 'Xuất thất bại', 'error');
  }
}

onMounted(load);
</script>
