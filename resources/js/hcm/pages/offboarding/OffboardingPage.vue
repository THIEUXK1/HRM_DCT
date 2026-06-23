<template>
  <div>
    <UiPageHeader
      title="Nghỉ việc / Offboarding"
      subtitle="Quản lý quy trình nghỉ việc, bàn giao và phân tích"
      breadcrumb="Offboarding"
    />

    <div class="flex gap-1 mb-6 border-b border-slate-200 overflow-x-auto">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        class="px-4 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition-colors"
        :class="activeTab === tab.id
          ? 'border-primary-600 text-primary-700'
          : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="activeTab = tab.id"
      >
        {{ tab.icon }} {{ tab.label }}
      </button>
    </div>

    <!-- Danh sách nghỉ việc -->
    <div v-if="activeTab === 'list'">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-col gap-3 w-full">
          <UiOrgScopeFilters
            :show-company-picker="scope.showCompanyPicker"
            :single-branch-mode="scope.singleBranchMode"
            v-model:filter-branch-id="scope.filterBranchId"
            v-model:filter-department-id="scope.filterDepartmentId"
            :branches="scope.branches"
            :filtered-departments="scope.filteredDepartments"
            @change="loadTerminations"
            @reset="resetScopeFilters"
          />
          <div class="flex flex-wrap gap-2 items-center">
            <UiSearchInput
              v-model="listSearch"
              placeholder="Tìm theo tên, mã NV, số quyết định..."
              @search="loadTerminations"
            />
            <select v-model="filterStatus" class="hcm-input text-sm w-40" @change="loadTerminations">
            <option value="">Tất cả trạng thái</option>
            <option value="pending">Chờ duyệt</option>
            <option value="approved">Đã duyệt</option>
            <option value="completed">Đã hoàn thành</option>
            <option value="rejected">Đã từ chối</option>
            </select>
          </div>
        </div>
        <p class="text-sm text-slate-500">{{ filteredTerminations.length }} hồ sơ</p>
      </div>

      <div v-if="loading" class="text-center py-10 text-slate-400">Đang tải...</div>
      <div v-else-if="filteredTerminations.length === 0" class="hcm-card p-10 text-center text-slate-400">
        <p class="text-2xl mb-2">📭</p>
        <p>Không có hồ sơ nghỉ việc</p>
      </div>
      <div v-else class="overflow-x-auto hcm-card">
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Nhân viên</th>
              <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Ngày nghỉ</th>
              <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Nguồn</th>
              <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Lý do</th>
              <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Bàn giao</th>
              <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Trạng thái</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="t in filteredTerminations" :key="t.id" class="hover:bg-slate-50">
              <td class="px-4 py-3">
                <div class="font-medium text-slate-900">{{ t.employee?.full_name || '—' }}</div>
                <div class="text-xs text-slate-400">{{ t.employee?.employee_code }}</div>
              </td>
              <td class="px-4 py-3 text-slate-600">{{ t.effective_date }}</td>
              <td class="px-4 py-3 text-slate-600 text-xs">{{ sourceLabel(t) }}</td>
              <td class="px-4 py-3 text-slate-600">{{ reasonLabel(t.reason_type) }}</td>
              <td class="px-4 py-3">
                <div class="flex gap-1">
                  <span v-if="t.handover_tasks_done" class="text-xs px-1.5 py-0.5 rounded bg-green-100 text-green-700">CV ✓</span>
                  <span v-else class="text-xs px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">CV</span>
                  <span v-if="t.assets_returned" class="text-xs px-1.5 py-0.5 rounded bg-green-100 text-green-700">TS ✓</span>
                  <span v-else class="text-xs px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">TS</span>
                  <span v-if="t.exit_interview_done" class="text-xs px-1.5 py-0.5 rounded bg-green-100 text-green-700">Exit ✓</span>
                  <span v-else class="text-xs px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">Exit</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="statusColor(t.status)">
                  {{ statusLabel(t.status) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <button class="text-xs text-primary-600 hover:underline" @click="openDetail(t)">Chi tiết</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Phân tích -->
    <div v-if="activeTab === 'analytics'">
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-slate-900">{{ analytics.total }}</p>
          <p class="text-xs text-slate-500 mt-1">Tổng nghỉ việc</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-amber-600">{{ analytics.voluntary }}</p>
          <p class="text-xs text-slate-500 mt-1">Tự nguyện</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-red-600">{{ analytics.involuntary }}</p>
          <p class="text-xs text-slate-500 mt-1">Sa thải / Kỷ luật</p>
        </div>
        <div class="hcm-card p-4 text-center">
          <p class="text-2xl font-bold text-blue-600">{{ analytics.turnoverRate }}%</p>
          <p class="text-xs text-slate-500 mt-1">Tỷ lệ nghỉ việc</p>
        </div>
      </div>

      <!-- Lý do nghỉ việc -->
      <div class="hcm-card p-5">
        <h3 class="font-semibold text-slate-800 mb-4">Phân tích lý do nghỉ việc</h3>
        <div v-if="reasonBreakdown.length === 0" class="text-sm text-slate-400 text-center py-6">Chưa có dữ liệu</div>
        <div v-else class="space-y-3">
          <div v-for="item in reasonBreakdown" :key="item.reason" class="flex items-center gap-3">
            <span class="text-sm text-slate-600 w-40 flex-shrink-0">{{ item.reason }}</span>
            <div class="flex-1 bg-slate-100 rounded-full h-2">
              <div class="bg-primary-500 h-2 rounded-full transition-all" :style="{ width: item.pct + '%' }"></div>
            </div>
            <span class="text-xs text-slate-500 w-12 text-right">{{ item.count }} ({{ item.pct }}%)</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Detail modal -->
    <div v-if="detailItem" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-slate-900">Chi tiết nghỉ việc — {{ detailItem.employee?.full_name }}</h3>
          <button @click="detailItem = null" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
        </div>
        <div class="space-y-3 text-sm">
          <div class="grid grid-cols-2 gap-3">
            <div><span class="text-slate-500">Ngày nghỉ:</span> <strong>{{ detailItem.effective_date }}</strong></div>
            <div><span class="text-slate-500">Lý do:</span> <strong>{{ reasonLabel(detailItem.reason_type) }}</strong></div>
            <div><span class="text-slate-500">Trạng thái:</span>
              <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="statusColor(detailItem.status)">
                {{ detailItem.status }}
              </span>
            </div>
          </div>
          <div v-if="detailItem.reason" class="bg-slate-50 rounded p-3">
            <p class="text-slate-500 text-xs mb-1">Lý do / nội dung đơn:</p>
            <p class="whitespace-pre-wrap">{{ detailItem.reason }}</p>
          </div>
          <div v-if="detailItem.handover_note" class="bg-slate-50 rounded p-3">
            <p class="text-slate-500 text-xs mb-1">Ghi chú bàn giao từ NV:</p>
            <p>{{ detailItem.handover_note }}</p>
          </div>
          <div v-if="detailItem.rejection_reason" class="bg-red-50 rounded p-3 text-red-800">
            <p class="text-xs mb-1 font-medium">Lý do từ chối:</p>
            <p>{{ detailItem.rejection_reason }}</p>
          </div>
          <div v-if="detailItem.notes" class="bg-slate-50 rounded p-3">
            <p class="text-slate-500 text-xs mb-1">Ghi chú HR:</p>
            <p>{{ detailItem.notes }}</p>
          </div>
          <!-- Checklist bàn giao (sau khi duyệt) -->
          <div v-if="detailItem.status === 'approved' || detailItem.status === 'completed'" class="border rounded-lg p-3 space-y-2">
            <p class="font-medium text-slate-700 text-xs uppercase tracking-wide">Checklist bàn giao</p>
            <label class="flex items-center gap-2">
              <input type="checkbox" v-model="detailItem.handover_tasks_done" @change="saveChecklist" />
              <span>Bàn giao công việc</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" v-model="detailItem.assets_returned" @change="saveChecklist" />
              <span>Hoàn trả tài sản (laptop, thẻ, v.v.)</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" v-model="detailItem.exit_interview_done" @change="saveChecklist" />
              <span>Hoàn thành exit interview</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" v-model="detailItem.accounts_disabled" @change="saveChecklist" />
              <span>Khóa tài khoản email / hệ thống</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" v-model="detailItem.final_settlement_done" @change="saveChecklist" />
              <span>Quyết toán lương và phép còn lại</span>
            </label>
          </div>
        </div>
        <div v-if="detailItem.status === 'pending'" class="flex flex-wrap gap-2 pt-2 border-t">
          <button type="button" class="hcm-btn-primary text-sm" :disabled="actionLoading" @click="approveTermination">
            Duyệt đơn
          </button>
          <button type="button" class="hcm-btn-secondary text-sm text-red-700 border-red-200" :disabled="actionLoading" @click="showRejectForm = true">
            Từ chối
          </button>
        </div>
        <div v-if="showRejectForm" class="border border-red-200 rounded-lg p-3 space-y-2">
          <label class="text-sm font-medium text-red-800">Lý do từ chối *</label>
          <textarea v-model="rejectReason" class="hcm-input w-full text-sm" rows="2" />
          <div class="flex justify-end gap-2">
            <button type="button" class="text-sm text-slate-500" @click="showRejectForm = false">Hủy</button>
            <button type="button" class="hcm-btn-primary text-sm bg-red-600 hover:bg-red-700" :disabled="actionLoading" @click="rejectTermination">
              Xác nhận từ chối
            </button>
          </div>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button class="hcm-btn-secondary" @click="closeDetail">Đóng</button>
          <button
            v-if="detailItem.status === 'approved' && allChecklistDone"
            class="hcm-btn-primary"
            :disabled="actionLoading"
            @click="markCompleted"
          >
            Đánh dấu hoàn tất
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiSearchInput from '../../components/ui/UiSearchInput.vue';
import UiOrgScopeFilters from '../../components/ui/UiOrgScopeFilters.vue';
import { useOrgScopeFilters } from '../../composables/useOrgScopeFilters';
import { useToast } from '../../composables/useToast';

const toast = useToast();

const tabs = [
  { id: 'list', label: 'Danh sách', icon: '📋' },
  { id: 'analytics', label: 'Phân tích', icon: '📊' },
];
const activeTab = ref('list');
const scope = useOrgScopeFilters({ includeDepartment: true });
const loading = ref(true);
const filterStatus = ref('');
const listSearch = ref('');
const terminations = ref([]);
const detailItem = ref(null);
const totalEmployees = ref(0);
const actionLoading = ref(false);
const showRejectForm = ref(false);
const rejectReason = ref('');

async function loadTerminations(searchValue = listSearch.value) {
  listSearch.value = searchValue;
  loading.value = true;
  try {
    const params = { ...scope.toQueryParams() };
    if (filterStatus.value) params.status = filterStatus.value;
    if (listSearch.value.trim()) params.search = listSearch.value.trim();
    const [tRes, eRes] = await Promise.all([
      api.get('/employee-terminations', { params }).catch(() => ({ data: { data: [] } })),
      api.get('/employees', { params: { per_page: 1 } }).catch(() => ({ data: { data: { total: 0 } } })),
    ]);
    terminations.value = tRes.data.data || [];
    totalEmployees.value = eRes.data.data?.total ?? extractTotal(eRes.data.data);
  } finally {
    loading.value = false;
  }
}

function extractTotal(payload) {
  if (!payload) return 0;
  if (typeof payload.total === 'number') return payload.total;
  return Array.isArray(payload) ? payload.length : 0;
}

function resetScopeFilters() {
  scope.resetScope();
  listSearch.value = '';
  loadTerminations();
}

onMounted(async () => {
  await scope.loadMeta();
  await loadTerminations();
});

const filteredTerminations = computed(() => terminations.value);

const analytics = computed(() => {
  const total = terminations.value.length;
  const voluntary = terminations.value.filter((t) =>
    ['resignation', 'retirement', 'contract_end'].includes(t.reason_type)
  ).length;
  const involuntary = total - voluntary;
  const turnoverRate = totalEmployees.value ? ((total / (totalEmployees.value + total)) * 100).toFixed(1) : 0;
  return { total, voluntary, involuntary, turnoverRate };
});

const reasonBreakdown = computed(() => {
  const map = {};
  for (const t of terminations.value) {
    const r = reasonLabel(t.reason_type);
    map[r] = (map[r] || 0) + 1;
  }
  const total = terminations.value.length || 1;
  return Object.entries(map)
    .map(([reason, count]) => ({ reason, count, pct: Math.round((count / total) * 100) }))
    .sort((a, b) => b.count - a.count);
});

const allChecklistDone = computed(() => {
  const t = detailItem.value;
  return t && t.handover_tasks_done && t.assets_returned && t.exit_interview_done;
});

function reasonLabel(type) {
  const m = {
    resignation: 'Tự nguyện nghỉ',
    retirement: 'Về hưu',
    contract_end: 'Hết hợp đồng',
    termination: 'Sa thải',
    redundancy: 'Cắt giảm nhân sự',
    death: 'Qua đời',
  };
  return m[type] || type || '—';
}

function statusColor(status) {
  return {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
  }[status] || 'bg-slate-100 text-slate-600';
}

function statusLabel(status) {
  return {
    pending: 'Chờ duyệt',
    approved: 'Đã duyệt',
    completed: 'Hoàn tất',
    rejected: 'Từ chối',
  }[status] || status;
}

function sourceLabel(t) {
  return t.submitted_by_user_id ? 'NV xin nghỉ (ESS)' : 'HR tạo';
}

function openDetail(item) {
  showRejectForm.value = false;
  rejectReason.value = '';
  detailItem.value = { ...item };
}

function closeDetail() {
  detailItem.value = null;
  showRejectForm.value = false;
}

async function approveTermination() {
  if (!detailItem.value || !confirm('Duyệt đơn xin nghỉ việc? NV sẽ chuyển trạng thái nghỉ việc.')) return;
  actionLoading.value = true;
  try {
    const { data } = await api.post(`/employee-terminations/${detailItem.value.id}/approve`);
    detailItem.value = { ...detailItem.value, ...data.data };
    const idx = terminations.value.findIndex((t) => t.id === detailItem.value.id);
    if (idx >= 0) terminations.value[idx] = { ...terminations.value[idx], ...data.data };
    toast.show('Đã duyệt đơn nghỉ việc');
    showRejectForm.value = false;
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không duyệt được', 'error');
  } finally {
    actionLoading.value = false;
  }
}

async function rejectTermination() {
  if (!rejectReason.value.trim()) {
    toast.show('Vui lòng nhập lý do từ chối', 'error');
    return;
  }
  actionLoading.value = true;
  try {
    const { data } = await api.post(`/employee-terminations/${detailItem.value.id}/reject`, {
      rejection_reason: rejectReason.value.trim(),
    });
    detailItem.value = { ...detailItem.value, ...data.data };
    const idx = terminations.value.findIndex((t) => t.id === detailItem.value.id);
    if (idx >= 0) terminations.value[idx] = { ...terminations.value[idx], ...data.data };
    toast.show('Đã từ chối đơn');
    showRejectForm.value = false;
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi từ chối', 'error');
  } finally {
    actionLoading.value = false;
  }
}

async function saveChecklist() {
  if (!detailItem.value) return;
  try {
    await api.put(`/employee-terminations/${detailItem.value.id}`, {
      handover_tasks_done: detailItem.value.handover_tasks_done,
      assets_returned: detailItem.value.assets_returned,
      exit_interview_done: detailItem.value.exit_interview_done,
      accounts_disabled: detailItem.value.accounts_disabled,
      final_settlement_done: detailItem.value.final_settlement_done,
    });
    const idx = terminations.value.findIndex((t) => t.id === detailItem.value.id);
    if (idx >= 0) terminations.value[idx] = { ...terminations.value[idx], ...detailItem.value };
  } catch {
    // silent
  }
}

async function markCompleted() {
  try {
    await api.put(`/employee-terminations/${detailItem.value.id}`, { status: 'completed' });
    detailItem.value.status = 'completed';
    const idx = terminations.value.findIndex((t) => t.id === detailItem.value.id);
    if (idx >= 0) terminations.value[idx].status = 'completed';
  } catch (e) {
    alert('Lỗi: ' + (e.response?.data?.message || e.message));
  }
}
</script>
