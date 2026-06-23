<template>
  <div>
    <!-- Greeting -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">
          {{ greeting }}, {{ firstName }} 👋
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">{{ today }} · {{ auth.user?.roles?.[0] ? roleLabel : '' }}</p>
      </div>
      <RouterLink v-if="canViewManagerDashboard" :to="{ name: 'manager-dashboard' }" class="hidden sm:flex items-center gap-2 hcm-btn-secondary text-sm">
        📊 Dashboard quản lý
      </RouterLink>
    </div>

    <div v-if="loading" class="text-center py-16 text-slate-400">Đang tải...</div>
    <template v-else>
      <!-- Top stat row -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <!-- Phép còn lại -->
        <div class="hcm-card p-4">
          <p class="text-xs text-slate-500 mb-1">Phép còn lại</p>
          <p class="text-2xl font-bold text-green-600">{{ leaveBalance.remaining }}</p>
          <p class="text-xs text-slate-400 mt-1">/ {{ leaveBalance.total }} ngày năm nay</p>
        </div>
        <!-- Chờ duyệt -->
        <div class="hcm-card p-4">
          <p class="text-xs text-slate-500 mb-1">Yêu cầu của tôi</p>
          <p class="text-2xl font-bold" :class="myPending > 0 ? 'text-amber-600' : 'text-slate-400'">{{ myPending }}</p>
          <p class="text-xs text-slate-400 mt-1">đang chờ duyệt</p>
        </div>
        <!-- Việc cần làm -->
        <div class="hcm-card p-4">
          <p class="text-xs text-slate-500 mb-1">Cần tôi duyệt</p>
          <p class="text-2xl font-bold" :class="toApprove > 0 ? 'text-red-500' : 'text-slate-400'">{{ toApprove }}</p>
          <p class="text-xs text-slate-400 mt-1">yêu cầu chờ xử lý</p>
        </div>
        <!-- Thâm niên -->
        <div class="hcm-card p-4">
          <p class="text-xs text-slate-500 mb-1">Thâm niên</p>
          <p class="text-2xl font-bold text-slate-700">{{ tenure }}</p>
          <p class="text-xs text-slate-400 mt-1">kể từ {{ formatDate(myProfile?.hire_date) }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Left col: việc cần làm + yêu cầu đang xử lý -->
        <div class="space-y-6 lg:col-span-2">
          <!-- Việc cần làm -->
          <div class="hcm-card p-5">
            <h3 class="font-semibold text-slate-800 mb-3">✅ Việc cần làm</h3>
            <div v-if="todos.length === 0" class="text-sm text-slate-400 text-center py-4">Không có việc cần làm</div>
            <div v-else class="space-y-2">
              <RouterLink
                v-for="todo in todos"
                :key="todo.id"
                :to="todo.link"
                class="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 border border-transparent hover:border-slate-200 transition-all"
              >
                <span class="text-xl flex-shrink-0">{{ todo.icon }}</span>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-slate-800">{{ todo.title }}</p>
                  <p class="text-xs text-slate-400">{{ todo.sub }}</p>
                </div>
                <span v-if="todo.badge" class="flex-shrink-0 inline-flex items-center justify-center h-5 min-w-5 px-1.5 rounded-full text-[11px] font-bold text-white"
                  :class="todo.urgent ? 'bg-red-500' : 'bg-amber-500'">
                  {{ todo.badge }}
                </span>
              </RouterLink>
            </div>
          </div>

          <!-- Yêu cầu đang xử lý -->
          <div class="hcm-card p-5">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-semibold text-slate-800">📋 Yêu cầu của tôi</h3>
              <RouterLink :to="{ name: 'self-service' }" class="text-xs text-primary-600 hover:underline">Xem tất cả →</RouterLink>
            </div>
            <div v-if="myRequests.length === 0" class="text-sm text-slate-400 text-center py-4">Chưa có yêu cầu nào</div>
            <div v-else class="space-y-2">
              <div v-for="req in myRequests.slice(0, 5)" :key="req.id" class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
                <div>
                  <p class="text-sm text-slate-700">{{ req.label }}</p>
                  <p class="text-xs text-slate-400">{{ formatDate(req.date) }}</p>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                  :class="req.status === 'approved' ? 'bg-green-100 text-green-700'
                    : req.status === 'rejected' ? 'bg-red-100 text-red-700'
                    : 'bg-amber-100 text-amber-700'">
                  {{ req.status === 'approved' ? 'Đã duyệt' : req.status === 'rejected' ? 'Từ chối' : 'Chờ duyệt' }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right col: thông tin cá nhân + thông báo -->
        <div class="space-y-6">
          <!-- Thông tin nhanh -->
          <div class="hcm-card p-5">
            <h3 class="font-semibold text-slate-800 mb-3">👤 Thông tin của tôi</h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-slate-500">Mã NV</span>
                <span class="font-mono font-medium">{{ myProfile?.employee_code || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">Phòng ban</span>
                <span class="font-medium">{{ myProfile?.department?.name || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">Chức danh</span>
                <span class="font-medium">{{ myProfile?.position?.name || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">Trạng thái</span>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-green-100 text-green-700">
                  {{ myProfile?.employment_status === 'active' ? 'Chính thức' : myProfile?.employment_status === 'probation' ? 'Thử việc' : myProfile?.employment_status || '—' }}
                </span>
              </div>
            </div>
            <RouterLink :to="{ name: 'self-service' }" class="mt-3 block text-xs text-primary-600 hover:underline text-center">
              Xem & cập nhật hồ sơ →
            </RouterLink>
          </div>

          <!-- Thông báo / sự kiện -->
          <div class="hcm-card p-5">
            <h3 class="font-semibold text-slate-800 mb-3">🔔 Thông báo</h3>
            <div class="space-y-3">
              <div v-for="notif in notifications" :key="notif.id" class="flex gap-2.5 p-2 rounded-lg bg-slate-50">
                <span class="text-lg flex-shrink-0">{{ notif.icon }}</span>
                <div>
                  <p class="text-xs font-medium text-slate-700">{{ notif.title }}</p>
                  <p class="text-xs text-slate-400">{{ notif.sub }}</p>
                </div>
              </div>
              <div v-if="notifications.length === 0" class="text-xs text-slate-400 text-center py-2">Không có thông báo mới</div>
            </div>
          </div>

          <!-- Phép trong năm -->
          <div class="hcm-card p-5">
            <h3 class="font-semibold text-slate-800 mb-3">🏖️ Quỹ phép năm nay</h3>
            <div class="space-y-3">
              <div v-for="lt in leaveBalance.types" :key="lt.name">
                <div class="flex justify-between text-xs mb-1">
                  <span class="text-slate-600">{{ lt.name }}</span>
                  <span class="font-medium">{{ lt.used }}/{{ lt.total }} ngày</span>
                </div>
                <div class="h-1.5 rounded-full bg-slate-100">
                  <div class="h-full rounded-full bg-primary-500 transition-all"
                    :style="{ width: Math.min(100, lt.total > 0 ? (lt.used / lt.total * 100) : 0) + '%' }"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api/client';
import { useAuthStore } from '../stores/auth';
import { useAppStore } from '../stores/app';
import { useFormat } from '../composables/useFormat';
import { usePermission } from '../composables/usePermission';
import { ensureArray, extractItems } from '../composables/usePagination';

const auth = useAuthStore();
const appStore = useAppStore();
const { formatDate } = useFormat();
const { can } = usePermission();

const loading = ref(true);
const myProfile = ref(null);
const myLeaves = ref([]);
const myOt = ref([]);
const approvalInbox = ref([]);
const leaveTypes = ref([]);
const leaveBalanceApi = ref(null);

const canViewManagerDashboard = computed(() =>
  can('employees.view') && (auth.roles || []).some((r) =>
    ['admin', 'hr_manager', 'department_manager', 'department_secretary'].includes(r)
  )
);

const now = new Date();
const today = now.toLocaleDateString('vi-VN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

const greeting = computed(() => {
  const h = now.getHours();
  if (h < 12) return 'Chào buổi sáng';
  if (h < 18) return 'Chào buổi chiều';
  return 'Chào buổi tối';
});

const firstName = computed(() => {
  const name = auth.user?.name || '';
  return name.split(' ').pop();
});

const roleLabel = computed(() => {
  const m = { admin: 'Quản trị viên', hr_manager: 'HR Manager', department_manager: 'Trưởng bộ phận', department_secretary: 'Thư ký bộ phận', employee: 'Nhân viên', payroll_specialist: 'C&B' };
  return m[auth.roles?.[0]] || auth.roles?.[0] || '';
});

const tenure = computed(() => {
  if (!myProfile.value?.hire_date) return '—';
  const diff = now - new Date(myProfile.value.hire_date);
  const months = Math.floor(diff / (1000 * 60 * 60 * 24 * 30));
  if (months < 12) return `${months} tháng`;
  return `${Math.floor(months / 12)} năm ${months % 12} tháng`;
});

const myLeavesList = computed(() => ensureArray(myLeaves.value));
const myOtList = computed(() => ensureArray(myOt.value));
const approvalInboxList = computed(() => ensureArray(approvalInbox.value));

const leaveBalance = computed(() => {
  if (leaveBalanceApi.value) {
    const b = leaveBalanceApi.value;
    return {
      total: b.annual_days,
      used: b.used_days,
      remaining: b.remaining_days,
      types: [{
        name: b.group?.name || 'Phép năm',
        used: b.used_days,
        total: b.annual_days,
      }],
    };
  }
  const approved = myLeavesList.value.filter((l) => l.status === 'approved');
  const byType = {};
  for (const l of approved) {
    const name = l.leave_type?.name || 'Nghỉ phép';
    if (!byType[name]) byType[name] = { name, used: 0, total: 12 };
    byType[name].used += Number(l.total_days || 1);
  }
  const types = Object.values(byType);
  const totalUsed = types.reduce((s, t) => s + t.used, 0);
  const totalDays = 12;
  return { total: totalDays, used: totalUsed, remaining: Math.max(0, totalDays - totalUsed), types };
});

const myPending = computed(() =>
  myLeavesList.value.filter((l) => l.status === 'pending').length +
  myOtList.value.filter((o) => o.status === 'pending').length
);

const toApprove = computed(() =>
  approvalInboxList.value.filter((a) => a.instance?.status === 'pending').length
);

const myRequests = computed(() => {
  const leaves = myLeavesList.value.slice(0, 5).map((l) => ({
    id: 'l' + l.id, label: `Nghỉ phép: ${l.start_date} → ${l.end_date}`, date: l.created_at, status: l.status,
  }));
  const ots = myOtList.value.slice(0, 3).map((o) => ({
    id: 'o' + o.id, label: `Tăng ca: ${o.work_date} (${o.hours}h)`, date: o.created_at, status: o.status,
  }));
  return [...leaves, ...ots].sort((a, b) => new Date(b.date) - new Date(a.date));
});

const todos = computed(() => {
  const list = [];
  if (toApprove.value > 0) {
    list.push({ id: 'approve', icon: '📥', title: 'Yêu cầu chờ tôi duyệt', sub: 'Hộp thư phê duyệt', badge: toApprove.value, urgent: true, link: { name: 'approvals' } });
  }
  if (!myProfile.value?.phone) {
    list.push({ id: 'profile', icon: '📝', title: 'Cập nhật thông tin cá nhân', sub: 'Số điện thoại, địa chỉ', link: { name: 'self-service' } });
  }
  if (myProfile.value?.employment_status === 'probation') {
    list.push({ id: 'probation', icon: '⏳', title: 'Đang trong thời gian thử việc', sub: `Ngày chính thức: ${myProfile.value.official_start_date || '—'}`, link: { name: 'self-service' } });
  }
  return list;
});

const notifications = computed(() => {
  const list = [];
  const hireDate = myProfile.value?.hire_date;
  if (hireDate) {
    const hire = new Date(hireDate);
    const anniv = new Date(now.getFullYear(), hire.getMonth(), hire.getDate());
    const diff = Math.ceil((anniv - now) / (1000 * 60 * 60 * 24));
    if (diff >= 0 && diff <= 7) {
      list.push({ id: 'anniv', icon: '🎂', title: 'Kỷ niệm ngày vào làm', sub: `${diff === 0 ? 'Hôm nay' : `Còn ${diff} ngày`} · ${now.getFullYear() - hire.getFullYear()} năm` });
    }
  }
  if (list.length === 0) {
    list.push({ id: 'empty', icon: '✨', title: 'Chào mừng đến HCM Suite', sub: 'Hệ thống quản trị nhân sự tập đoàn' });
  }
  return list;
});

onMounted(async () => {
  try {
    // AppLayout already loads approvalInbox in parallel — reuse it, no extra API call
    const [profileRes, leavesRes, otRes, balanceRes] = await Promise.allSettled([
      api.get('/self-service/profile'),
      api.get('/self-service/leave-requests').catch(() => api.get('/leave-requests')),
      api.get('/overtime-requests', { params: { per_page: 100 } }).catch(() => null),
      api.get('/self-service/leave-balance'),
    ]);

    if (profileRes.status === 'fulfilled') myProfile.value = profileRes.value.data.data;
    if (balanceRes.status === 'fulfilled') leaveBalanceApi.value = balanceRes.value.data.data;
    if (leavesRes.status === 'fulfilled' && leavesRes.value) {
      myLeaves.value = extractItems(leavesRes.value.data);
    }
    if (otRes.status === 'fulfilled' && otRes.value) {
      let otItems = extractItems(otRes.value.data);
      const empId = myProfile.value?.id;
      if (empId) {
        otItems = otItems.filter((o) => o.employee_id === empId);
      }
      myOt.value = ensureArray(otItems);
    }

    // Use cached inbox from AppStore — set directly without another request
    approvalInbox.value = ensureArray(appStore.approvalInbox);
  } finally {
    loading.value = false;
  }
});
</script>
