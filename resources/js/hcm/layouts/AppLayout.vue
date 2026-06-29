<template>
  <div class="h-screen flex overflow-hidden">
    <!-- Sidebar -->
    <aside
      class="hidden lg:flex flex-col bg-slate-900 text-slate-200 transition-all duration-200"
      :class="sidebarCollapsed ? 'w-16' : 'w-64'"
    >
      <!-- Logo -->
      <div
        class="border-b border-slate-800 py-3 flex items-center min-h-[60px] transition-all duration-200"
        :class="sidebarCollapsed ? 'flex-col justify-center gap-1 px-1' : 'flex-row gap-3 px-4'"
      >
        <template v-if="sidebarCollapsed">
          <div class="flex-shrink-0 flex h-9 w-9 items-center justify-center rounded-lg bg-primary-600 font-bold text-white text-sm">H</div>
          <button
            class="flex h-6 w-6 items-center justify-center rounded text-slate-400 hover:bg-slate-700 hover:text-white transition-colors"
            @click="sidebarCollapsed = false"
            title="Mở rộng sidebar"
          >
            <span class="text-[10px] leading-none">▶</span>
          </button>
        </template>
        <template v-else>
          <div class="flex-shrink-0 flex h-9 w-9 items-center justify-center rounded-lg bg-primary-600 font-bold text-white text-sm">H</div>
          <div class="min-w-0 flex-1">
            <p class="font-semibold text-white text-sm leading-tight truncate">HCM Suite</p>
            <p class="text-xs text-slate-400 truncate">{{ appStore.currentCompany?.name || 'Quản trị nhân sự' }}</p>
          </div>
          <button
            class="ml-auto flex-shrink-0 flex h-6 w-6 items-center justify-center rounded text-slate-400 hover:bg-slate-700 hover:text-white transition-colors"
            @click="sidebarCollapsed = true"
            title="Thu gọn sidebar"
          >
            <span class="text-[10px] leading-none">◀</span>
          </button>
        </template>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto overflow-x-hidden py-2 px-2 space-y-0.5">
        <template v-for="group in visibleGroups" :key="group.id">
          <!-- Single items (no group label) -->
          <template v-if="!group.label">
            <NavItem
              v-for="item in group.items"
              :key="item.name"
              :item="item"
              :collapsed="sidebarCollapsed"
              :active="isActive(item)"
            />
          </template>

          <!-- Grouped items -->
          <div v-else class="pt-1">
            <button
              v-if="!sidebarCollapsed"
              class="w-full flex items-center gap-2 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-300 transition-colors rounded"
              @click="toggleGroup(group.id)"
            >
              <span>{{ group.icon }}</span>
              <span class="flex-1 text-left">{{ group.label }}</span>
              <span class="text-[10px]">{{ openGroups.has(group.id) ? '▲' : '▼' }}</span>
            </button>
            <div v-else class="flex justify-center py-1">
              <span class="text-slate-600 text-xs">{{ group.icon }}</span>
            </div>

            <div v-show="sidebarCollapsed || openGroups.has(group.id)" class="space-y-0.5 mt-0.5">
              <NavItem
                v-for="item in group.items"
                :key="item.name"
                :item="item"
                :collapsed="sidebarCollapsed"
                :active="isActive(item)"
                :indented="!sidebarCollapsed"
              />
            </div>
          </div>
        </template>
      </nav>

      <div class="border-t border-slate-800 p-3 text-xs text-slate-600 text-center">
        <span v-if="!sidebarCollapsed">v2.0 · HCM Suite</span>
        <span v-else>v2</span>
      </div>
    </aside>

    <!-- Main content -->
    <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
      <!-- Top bar -->
      <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="flex h-14 items-center justify-between gap-4 px-4 lg:px-6">
          <!-- Mobile logo -->
          <div class="flex items-center gap-3 lg:hidden">
            <span class="font-bold text-primary-700">HCM</span>
          </div>

          <!-- Company selector (width theo tên công ty dài nhất) -->
          <div class="flex items-center gap-2 min-w-0 max-w-[calc(100%-9rem)] sm:max-w-[min(100%,42rem)]">
            <span v-if="appStore.isMultiCompany" class="hidden shrink-0 text-xs font-medium text-slate-400 sm:inline whitespace-nowrap">Công ty:</span>
            <div
              v-if="appStore.isMultiCompany"
              class="inline-grid min-w-0 max-w-full"
            >
              <select
                v-model="companyId"
                class="col-start-1 row-start-1 hcm-input w-full min-w-0 max-w-full text-sm"
                @change="onCompanyChange"
              >
                <option v-for="c in appStore.companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
              </select>
              <span
                class="invisible col-start-1 row-start-1 whitespace-pre px-3 py-2 text-sm font-normal pointer-events-none select-none"
                aria-hidden="true"
              >{{ companySelectSizerText }}</span>
            </div>
            <span
              v-else-if="appStore.currentCompany"
              class="text-sm font-medium text-slate-700 whitespace-nowrap"
              :title="appStore.currentCompany.name"
            >
              {{ appStore.currentCompany.name }}
            </span>
          </div>

          <!-- Right: notifications + user + logout -->
          <div class="flex items-center gap-2">
            <!-- Notification Bell -->
            <NotificationBell />

            <div class="hidden sm:flex flex-col items-end text-xs ml-1">
              <span class="text-slate-700 font-medium">{{ auth.user?.name }}</span>
              <span class="text-slate-400">{{ userRoleLabel }}</span>
            </div>
            <button type="button" class="hcm-btn-secondary text-xs" @click="logout">Đăng xuất</button>
          </div>
        </div>
      </header>

      <!-- Mobile quick nav (sidebar ẩn trên màn nhỏ) -->
      <nav class="lg:hidden border-b border-slate-200 bg-white px-2 py-2 flex gap-1 overflow-x-auto">
        <RouterLink
          v-for="item in mobileQuickLinks"
          :key="item.name"
          :to="item.to"
          class="shrink-0 rounded-full px-3 py-1.5 text-xs font-medium border transition-colors"
          :class="isActive(item)
            ? 'bg-primary-600 text-white border-primary-600'
            : 'bg-slate-50 text-slate-600 border-slate-200 hover:border-primary-300'"
        >
          {{ item.icon }} {{ item.label }}
        </RouterLink>
      </nav>

      <main class="flex-1 overflow-y-auto p-4 lg:p-6">
        <RouterView />
      </main>
    </div>
  </div>
  <UiToast />
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useAppStore } from '../stores/app';
import { usePermission } from '../composables/usePermission';
import UiToast from '../components/ui/UiToast.vue';
import NavItem from '../components/layout/NavItem.vue';
import NotificationBell from '../components/NotificationBell.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const appStore = useAppStore();
const companyId = ref(localStorage.getItem('hcm_company_id') || '');
const sidebarCollapsed = ref(localStorage.getItem('hcm_sidebar_collapsed') === '1');

/** Khung chọn công ty co theo tên dài nhất trong danh sách */
const companySelectSizerText = computed(() => {
  const names = (appStore.companies || []).map((c) => c.name || '').filter(Boolean);
  if (!names.length) return 'Công ty';
  return names.reduce((longest, name) => (name.length > longest.length ? name : longest), names[0]);
});

const { can, canAny, hasAnyRole } = usePermission();

function loadOpenGroups() {
  try {
    const raw = localStorage.getItem('hcm_nav_open');
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : null;
  } catch {
    localStorage.removeItem('hcm_nav_open');
    return null;
  }
}

// Groups that are open
const openGroups = ref(new Set(loadOpenGroups() ?? ['top', 'portal', 'hr-core', 'tc-leave', 'payroll-ins', 'perf', 'recruit', 'workflow']));

function toggleGroup(id) {
  if (openGroups.value.has(id)) {
    openGroups.value.delete(id);
  } else {
    openGroups.value.add(id);
  }
  localStorage.setItem('hcm_nav_open', JSON.stringify([...openGroups.value]));
}

watch(sidebarCollapsed, (v) => {
  localStorage.setItem('hcm_sidebar_collapsed', v ? '1' : '0');
});

// ─── Menu definition ────────────────────────────────────────────────────────
const menuGroups = [
  {
    id: 'top',
    label: null,
    items: [
      { name: 'dashboard', to: { name: 'dashboard' }, label: 'Trang chủ', icon: '🏠' },
      { name: 'manager-dashboard', to: { name: 'manager-dashboard' }, label: 'Dashboard quản lý', icon: '📊', perm: 'employees.view' },
    ],
  },
  {
    id: 'portal',
    label: 'Cổng tự phục vụ',
    icon: '🙋',
    items: [
      { name: 'self-service', to: { name: 'self-service' }, label: 'Cổng nhân viên (ESS)', icon: '👤' },
      { name: 'mss', to: { name: 'mss' }, label: 'Cổng quản lý (MSS)', icon: '👔', permsAny: ['employees.view', 'approvals.view'] },
      { name: 'attendance-punch', to: { name: 'attendance-punch' }, label: 'Chấm công GPS', icon: '📍', permsAny: ['attendance.punch_gps', 'attendance.punch_qr'] },
    ],
  },
  {
    id: 'corp',
    label: 'Quản trị tập đoàn',
    icon: '🏢',
    perm: 'departments.view',
    items: [
      { name: 'organization', to: { name: 'organization' }, label: 'Cơ cấu tổ chức', icon: '🌳' },
      { name: 'company-policies', to: { name: 'company-policies' }, label: 'Chính sách công ty', icon: '📜', perm: 'company_policies.view' },
      { name: 'settings', to: { name: 'settings' }, label: 'Cài đặt & chính sách', icon: '⚙️', perm: 'companies.view' },
    ],
  },
  {
    id: 'hr-core',
    label: 'Hồ sơ nhân sự',
    icon: '👥',
    perm: 'employees.view',
    items: [
      { name: 'employees', to: { name: 'employees' }, label: 'Danh sách nhân viên', icon: '👤', perm: 'employees.view' },
      { name: 'contracts', to: { name: 'contracts' }, label: 'Hợp đồng lao động', icon: '📄', perm: 'employment_contracts.view' },
      { name: 'offboarding', to: { name: 'offboarding' }, label: 'Nghỉ việc', icon: '🚪', perm: 'employees.edit' },
    ],
  },
  {
    id: 'recruit',
    label: 'Tuyển dụng & Tiếp nhận',
    icon: '🎯',
    perm: 'candidates.view',
    items: [
      { name: 'recruitment', to: { name: 'recruitment' }, label: 'Tuyển dụng (ATS)', icon: '📋', perm: 'candidates.view' },
      { name: 'onboarding', to: { name: 'onboarding' }, label: 'Onboarding', icon: '🚀', perm: 'employees.view' },
    ],
  },
  {
    id: 'tc-leave',
    label: 'Chấm công & Nghỉ phép',
    icon: '⏱️',
    perm: 'attendance.view',
    items: [
      { name: 'attendance', to: { name: 'attendance' }, label: 'Bảng công', icon: '📋', perm: 'attendance.view' },
      { name: 'work-schedules', to: { name: 'work-schedules' }, label: 'Ca làm việc', icon: '🗓️', perm: 'attendance.manage' },
      { name: 'leave', to: { name: 'leave' }, label: 'Nghỉ phép / OT', icon: '🏖️', perm: 'leave.view' },
      { name: 'attendance-devices', to: { name: 'attendance-devices' }, label: 'Máy chấm công', icon: '🖲️', perm: 'attendance.manage' },
      { name: 'attendance-sources', to: { name: 'attendance-sources' }, label: 'Nguồn ZKTime', icon: '🔌', perm: 'attendance.manage' },
      { name: 'zkteco-sync', to: { name: 'zkteco-sync' }, label: 'Đồng bộ vân tay', icon: '🔄', perm: 'attendance.manage' },
    ],
  },
  {
    id: 'payroll-ins',
    label: 'Lương, thưởng & BHXH',
    icon: '💰',
    perm: 'payroll.view',
    items: [
      { name: 'payroll',  to: { name: 'payroll' },  label: 'Bảng lương',    icon: '💵', perm: 'payroll.view' },
      { name: 'benefits', to: { name: 'benefits' }, label: 'Phúc lợi',       icon: '🎁', perm: 'payroll.view' },
      { name: 'bhxh',     to: { name: 'bhxh' },     label: 'Kê khai BHXH',  icon: '🏛️', perm: 'bhxh.export' },
    ],
  },
  {
    id: 'perf',
    label: 'Hiệu suất & Đào tạo',
    icon: '📈',
    perm: 'performance.view',
    items: [
      { name: 'performance', to: { name: 'performance' }, label: 'KPI / Đánh giá', icon: '🎯', perm: 'performance.view' },
      { name: 'competency', to: { name: 'competency' }, label: 'Năng lực', icon: '🎓', perm: 'competency.view' },
      { name: 'training', to: { name: 'training' }, label: 'Đào tạo', icon: '📚', perm: 'training.view' },
    ],
  },
  {
    id: 'workflow',
    label: 'Quy trình & Báo cáo',
    icon: '✅',
    perm: 'approvals.view',
    items: [
      { name: 'approvals', to: { name: 'approvals' }, label: 'Hộp thư duyệt', icon: '📥', perm: 'approvals.view' },
      { name: 'reports', to: { name: 'reports' }, label: 'Báo cáo HR', icon: '📊', perm: 'employees.view' },
      { name: 'audit-log', to: { name: 'audit-log' }, label: 'Nhật ký kiểm toán', icon: '🛡️', perm: 'audit_logs.view' },
    ],
  },
];

function canSeeMenuItem(item) {
  if (item.permsAny && !canAny(item.permsAny)) return false;
  if (item.perm && !can(item.perm)) return false;
  if (item.role && !hasAnyRole(item.role)) return false;
  return true;
}

const mobileQuickLinks = computed(() => {
  const pick = ['dashboard', 'self-service', 'mss', 'attendance-punch', 'approvals'];
  const flat = menuGroups.flatMap((g) => g.items);
  return flat.filter((item) => pick.includes(item.name) && canSeeMenuItem(item));
});

const visibleGroups = computed(() => {
  return menuGroups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => canSeeMenuItem(item)),
    }))
    .filter((group) => {
      if (group.perm && !can(group.perm)) return false;
      return group.items.length > 0;
    });
});

// Auto-open the group containing the active route
watch(
  () => route.name,
  (name) => {
    for (const group of menuGroups) {
      if (group.label && group.items.some((i) => isActive(i))) {
        openGroups.value.add(group.id);
      }
    }
  },
  { immediate: true }
);

function isActive(item) {
  if (item.name === 'employees' && route.name === 'employee-detail') return true;
  return route.name === item.name;
}

const userRoleLabel = computed(() => {
  const r = auth.roles?.[0];
  const map = {
    admin: 'Quản trị viên',
    hr_manager: 'HR Manager',
    department_manager: 'Trưởng bộ phận',
    department_secretary: 'Thư ký bộ phận',
    employee: 'Nhân viên',
    payroll_specialist: 'C&B',
    auditor: 'Kiểm toán',
  };
  return map[r] || r || '';
});

function onCompanyChange() {
  auth.setCompanyAndRefresh(companyId.value);
  window.location.reload();
}

function logout() {
  auth.logout();
  router.push({ name: 'login' });
}

// ── Proactive token rotation ──────────────────────────────────────────────────
// Check every 5 minutes; rotate if < 30 minutes remain so user is never kicked mid-session.
let rotateTimer = null;

function scheduleRotation() {
  rotateTimer = setInterval(async () => {
    const minutesLeft = auth.tokenExpiresIn;
    if (minutesLeft !== null && minutesLeft <= 30) {
      await auth.rotate();
    }
  }, 5 * 60 * 1000);
}

onUnmounted(() => {
  if (rotateTimer) clearInterval(rotateTimer);
});

onMounted(async () => {
  // Parallel — do NOT await sequentially
  await Promise.allSettled([
    appStore.loadCompanies(),
    appStore.loadPendingApprovals(),
  ]);
  if (!companyId.value && appStore.companies[0]) {
    companyId.value = String(appStore.companies[0].id);
    auth.setCompany(companyId.value);
  }
  scheduleRotation();
});

watch(() => auth.companyId, (v) => {
  if (v) companyId.value = String(v);
});
</script>
