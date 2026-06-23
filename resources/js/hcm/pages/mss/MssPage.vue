<template>
  <div>
    <UiPageHeader
      title="Cổng quản lý (MSS)"
      subtitle="Quản lý nhân sự thuộc quyền — duyệt yêu cầu, đánh giá, đề xuất"
      breadcrumb="MSS"
    />

    <!-- Tabs -->
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
        <span v-if="tab.badge" class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-500 text-white">
          {{ tab.badge }}
        </span>
      </button>
    </div>

    <!-- Tab: Nhân sự của tôi -->
    <div v-if="activeTab === 'team'">
      <div v-if="loadingTeam" class="text-center py-8 text-slate-400">Đang tải...</div>
      <div v-else-if="teamError" class="hcm-card p-8 text-center text-red-600">{{ teamError }}</div>
      <div v-else-if="myTeam.length === 0" class="text-center py-12">
        <p class="text-slate-500">Không có nhân sự trong phạm vi quản lý</p>
      </div>
      <div v-else>
        <div class="flex items-center justify-between mb-3">
          <p class="text-sm text-slate-500">{{ myTeam.length }} nhân viên</p>
          <input v-model="teamSearch" type="search" placeholder="Tìm kiếm..." class="hcm-input text-sm w-48" />
        </div>
        <div class="overflow-x-auto hcm-card">
          <table class="w-full text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Nhân viên</th>
                <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Chức danh</th>
                <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Trạng thái</th>
                <th class="text-left px-4 py-3 text-xs font-medium text-slate-500 uppercase">Ngày vào</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="emp in filteredTeam" :key="emp.id" class="hover:bg-slate-50">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-900">{{ emp.full_name }}</div>
                  <div class="text-xs text-slate-400">{{ emp.employee_code }}</div>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ emp.position?.name || '—' }}</td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="emp.employment_status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
                    {{ emp.employment_status === 'active' ? 'Chính thức' : 'Thử việc' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-slate-500">{{ emp.hire_date }}</td>
                <td class="px-4 py-3">
                  <RouterLink :to="{ name: 'employee-detail', params: { id: emp.id } }" class="text-xs text-primary-600 hover:underline">
                    Xem hồ sơ
                  </RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tab: Yêu cầu chờ duyệt -->
    <div v-if="activeTab === 'approvals'">
      <div v-if="loadingApprovals" class="text-center py-8 text-slate-400">Đang tải...</div>
      <div v-else-if="pendingItems.length === 0" class="text-center py-12">
        <p class="text-3xl mb-2">✅</p>
        <p class="text-slate-500">Không có yêu cầu nào chờ duyệt</p>
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="item in pendingItems"
          :key="item.instance.id"
          class="hcm-card p-4 flex items-start justify-between gap-4"
        >
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded"
                :class="typeColor(item.instance.entity_type)">
                {{ item.entity_type_label || typeLabel(item.instance.entity_type) }}
              </span>
              <span class="text-xs text-slate-400">{{ item.instance.created_at?.slice(0, 10) }}</span>
            </div>
            <p class="font-medium text-slate-800">{{ item.entity_label || item.entity?.employee?.full_name || 'N/A' }}</p>
            <p class="text-sm text-slate-500 mt-0.5 truncate">{{ item.entity?.reason || item.entity?.description || item.current_step_label || '' }}</p>
          </div>
          <div class="flex gap-2 flex-shrink-0">
            <button class="hcm-btn-primary text-xs px-3 py-1.5" @click="approve(item)">Duyệt</button>
            <button class="hcm-btn-secondary text-xs px-3 py-1.5" @click="reject(item)">Từ chối</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Đánh giá nhân viên -->
    <div v-if="activeTab === 'reviews'">
      <div v-if="myTeam.length === 0" class="text-center py-12 text-slate-400">
        Không có nhân sự để đánh giá
      </div>
      <div v-else>
        <p class="text-sm text-slate-500 mb-4">Danh sách nhân viên cần đánh giá KPI / thử việc</p>
        <div class="space-y-3">
          <div
            v-for="emp in myTeam"
            :key="emp.id"
            class="hcm-card p-4 flex items-center justify-between gap-4"
          >
            <div>
              <p class="font-medium text-slate-800">{{ emp.full_name }}</p>
              <p class="text-xs text-slate-400">{{ emp.position?.name }} · {{ emp.department?.name }}</p>
            </div>
            <div class="flex gap-2">
              <RouterLink
                :to="{ name: 'employee-detail', params: { id: emp.id } }"
                class="hcm-btn-secondary text-xs"
              >
                Xem hồ sơ & KPI
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Đề xuất nhân sự -->
    <div v-if="activeTab === 'proposals'">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
        <button class="hcm-card p-5 text-left hover:border-primary-300 hover:shadow-md transition-all group" @click="showProposalModal('recruit')">
          <div class="text-2xl mb-2">🎯</div>
          <h4 class="font-semibold text-slate-800 group-hover:text-primary-700">Đề xuất tuyển dụng</h4>
          <p class="text-xs text-slate-500 mt-1">Gửi yêu cầu tuyển nhân sự mới cho bộ phận</p>
        </button>
        <button class="hcm-card p-5 text-left hover:border-primary-300 hover:shadow-md transition-all group" @click="showProposalModal('transfer')">
          <div class="text-2xl mb-2">🔄</div>
          <h4 class="font-semibold text-slate-800 group-hover:text-primary-700">Đề xuất điều chuyển</h4>
          <p class="text-xs text-slate-500 mt-1">Đề xuất điều chuyển nhân viên sang bộ phận khác</p>
        </button>
        <button class="hcm-card p-5 text-left hover:border-primary-300 hover:shadow-md transition-all group" @click="showProposalModal('raise')">
          <div class="text-2xl mb-2">💰</div>
          <h4 class="font-semibold text-slate-800 group-hover:text-primary-700">Đề xuất tăng lương</h4>
          <p class="text-xs text-slate-500 mt-1">Đề xuất điều chỉnh lương cho nhân viên</p>
        </button>
      </div>
      <div class="hcm-card p-6 text-center text-slate-400 text-sm">
        Tính năng gửi đề xuất qua workflow phê duyệt — đang phát triển
      </div>
    </div>

    <!-- Tab: Lịch nhóm -->
    <div v-if="activeTab === 'schedule'">
      <div class="hcm-card p-6 text-center text-slate-400">
        <p class="text-2xl mb-2">📅</p>
        <p>Lịch nghỉ, lịch công tác, lịch ca của nhóm — đang phát triển</p>
        <RouterLink :to="{ name: 'leave' }" class="mt-3 inline-block text-sm text-primary-600 hover:underline">
          Xem nghỉ phép hiện tại →
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import { extractItems } from '../../composables/usePagination';
import { useAuthStore } from '../../stores/auth';
import { useAppStore } from '../../stores/app';

const auth = useAuthStore();
const appStore = useAppStore();
const activeTab = ref('team');
const loadingTeam = ref(true);
const teamError = ref('');
const loadingApprovals = ref(false);
const teamSearch = ref('');
const myTeam = ref([]);
const approvals = ref([]);

const pendingItems = computed(() =>
  approvals.value.filter((a) => a.instance?.status === 'pending')
);

const tabs = computed(() => [
  { id: 'team', label: 'Nhân sự của tôi', icon: '👥' },
  { id: 'approvals', label: 'Chờ duyệt', icon: '📥', badge: appStore.pendingApprovals || null },
  { id: 'reviews', label: 'Đánh giá nhân viên', icon: '⭐' },
  { id: 'proposals', label: 'Đề xuất nhân sự', icon: '📝' },
  { id: 'schedule', label: 'Lịch nhóm', icon: '📅' },
]);

const filteredTeam = computed(() =>
  teamSearch.value
    ? myTeam.value.filter(
        (e) =>
          e.full_name?.toLowerCase().includes(teamSearch.value.toLowerCase()) ||
          e.employee_code?.toLowerCase().includes(teamSearch.value.toLowerCase())
      )
    : myTeam.value
);

const approvalsLoaded = ref(false);

onMounted(async () => {
  teamError.value = '';
  try {
    const empRes = await api.get('/employees', { params: { per_page: 200 } });
    myTeam.value = extractItems(empRes.data);
  } catch (e) {
    teamError.value = e.response?.data?.message || 'Không tải được danh sách nhân sự. Kiểm tra quyền employees.view và công ty đang chọn.';
  } finally {
    loadingTeam.value = false;
  }
});

async function loadApprovals() {
  if (approvalsLoaded.value) {
    loadingApprovals.value = false;
    return;
  }
  loadingApprovals.value = true;
  try {
    if (appStore.inboxLoaded) {
      approvals.value = appStore.approvalInbox;
    } else {
      await appStore.loadPendingApprovals();
      approvals.value = appStore.approvalInbox;
    }
    approvalsLoaded.value = true;
  } finally {
    loadingApprovals.value = false;
  }
}

watch(activeTab, (tab) => {
  if (tab === 'approvals') loadApprovals();
});

function typeLabel(type) {
  const m = { leave_request: 'Nghỉ phép', overtime_request: 'Tăng ca', recruitment_request: 'Tuyển dụng' };
  return m[type] || type;
}

function typeColor(type) {
  const m = {
    leave_request: 'bg-blue-100 text-blue-700',
    overtime_request: 'bg-amber-100 text-amber-700',
    recruitment_request: 'bg-purple-100 text-purple-700',
  };
  return m[type] || 'bg-slate-100 text-slate-600';
}

async function approve(item) {
  try {
    await api.post(`/approvals/${item.instance.id}/approve`);
    approvals.value = approvals.value.filter((a) => a.instance?.id !== item.instance.id);
    appStore.refreshInbox();
  } catch (e) {
    alert('Lỗi: ' + (e.response?.data?.message || e.message));
  }
}

async function reject(item) {
  const comment = prompt('Lý do từ chối:');
  if (comment === null) return;
  try {
    await api.post(`/approvals/${item.instance.id}/reject`, { comment });
    approvals.value = approvals.value.filter((a) => a.instance?.id !== item.instance.id);
    appStore.refreshInbox();
  } catch (e) {
    alert('Lỗi: ' + (e.response?.data?.message || e.message));
  }
}

function showProposalModal(type) {
  alert(`Tính năng "${type}" đang phát triển`);
}
</script>
