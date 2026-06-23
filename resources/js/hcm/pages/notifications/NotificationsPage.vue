<template>
  <div>
    <UiPageHeader title="Thông báo" subtitle="Tất cả thông báo của bạn" breadcrumb="Notifications">
      <template #actions>
        <button
          v-if="unreadCount > 0"
          type="button"
          class="hcm-btn-secondary"
          @click="markAllRead"
        >
          ✓ Đọc tất cả ({{ unreadCount }})
        </button>
      </template>
    </UiPageHeader>

    <!-- Filter tabs -->
    <div class="mb-4 flex gap-2">
      <button
        v-for="f in filters"
        :key="f.id"
        type="button"
        class="px-3 py-1.5 rounded-full text-sm font-medium border transition-colors"
        :class="activeFilter === f.id
          ? 'bg-primary-600 text-white border-primary-600'
          : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
        @click="activeFilter = f.id"
      >
        {{ f.label }}
        <span v-if="f.id === 'unread' && unreadCount > 0" class="ml-1 bg-red-500 text-white text-xs rounded-full px-1.5">
          {{ unreadCount }}
        </span>
      </button>
    </div>

    <!-- Notification list -->
    <div class="hcm-card overflow-hidden">
      <div v-if="loading" class="py-16 text-center text-slate-400">Đang tải...</div>

      <template v-else-if="filteredNotifications.length">
        <div
          v-for="n in filteredNotifications"
          :key="n.id"
          class="flex gap-4 p-4 border-b border-slate-100 last:border-0 hover:bg-slate-50 cursor-pointer transition-colors"
          :class="!n.read_at ? 'bg-blue-50/30' : ''"
          @click="handleClick(n)"
        >
          <!-- Icon -->
          <div
            class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-xl"
            :class="priorityBg(n.priority)"
          >
            {{ typeIcon(n.type) }}
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
              <p class="text-sm font-medium text-slate-800" :class="!n.read_at ? 'font-semibold' : ''">
                {{ n.title }}
              </p>
              <span class="flex-shrink-0 text-xs text-slate-400 whitespace-nowrap">{{ timeAgo(n.created_at) }}</span>
            </div>
            <p v-if="n.body" class="text-sm text-slate-600 mt-0.5">{{ n.body }}</p>
            <div class="flex items-center gap-3 mt-1.5">
              <UiBadge :variant="priorityVariant(n.priority)" size="sm">{{ priorityLabel(n.priority) }}</UiBadge>
              <UiBadge variant="default" size="sm">{{ typeLabel(n.type) }}</UiBadge>
              <span v-if="!n.read_at" class="w-2 h-2 rounded-full bg-blue-500 inline-block" />
            </div>
          </div>

          <!-- Actions -->
          <div class="flex-shrink-0 flex flex-col gap-1 items-end">
            <button
              v-if="!n.read_at"
              type="button"
              class="text-xs text-primary-600 hover:underline whitespace-nowrap"
              @click.stop="markOne(n)"
            >
              Đánh dấu đã đọc
            </button>
            <button
              type="button"
              class="text-xs text-slate-400 hover:text-red-500"
              @click.stop="remove(n)"
            >
              Xóa
            </button>
          </div>
        </div>
      </template>

      <UiEmpty v-else title="Không có thông báo" />
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import { useToast } from '../../composables/useToast';

const router = useRouter();
const toast  = useToast();

const loading       = ref(false);
const notifications = ref([]);
const unreadCount   = ref(0);
const activeFilter  = ref('all');

const filters = [
  { id: 'all',    label: 'Tất cả' },
  { id: 'unread', label: 'Chưa đọc' },
  { id: 'high',   label: 'Ưu tiên cao' },
];

const filteredNotifications = computed(() => {
  switch (activeFilter.value) {
    case 'unread': return notifications.value.filter((n) => !n.read_at);
    case 'high':   return notifications.value.filter((n) => ['high', 'urgent'].includes(n.priority));
    default:       return notifications.value;
  }
});

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/notifications');
    notifications.value = data.data?.items ?? [];
    unreadCount.value   = data.data?.unread_count ?? 0;
  } finally {
    loading.value = false;
  }
}

async function markAllRead() {
  await api.post('/notifications/read');
  notifications.value.forEach((n) => { n.read_at = new Date().toISOString(); });
  unreadCount.value = 0;
  toast.show('Đã đánh dấu tất cả là đã đọc');
}

async function markOne(n) {
  await api.post('/notifications/read', { id: n.id });
  n.read_at = new Date().toISOString();
  unreadCount.value = Math.max(0, unreadCount.value - 1);
}

async function remove(n) {
  await api.delete(`/notifications/${n.id}`);
  const idx = notifications.value.indexOf(n);
  if (idx !== -1) {
    if (!notifications.value[idx].read_at) unreadCount.value = Math.max(0, unreadCount.value - 1);
    notifications.value.splice(idx, 1);
  }
}

async function handleClick(n) {
  await markOne(n);
  if (n.action_url) router.push(n.action_url);
}

// ── Display helpers ────────────────────────────────────────────────────────
function typeIcon(type) {
  const map = {
    contract_expiring: '⚠️', probation_ending: '⏳', birthday: '🎂',
    leave_approved: '✅', leave_rejected: '❌', approval_pending: '📥',
    payroll_finalized: '💰', onboarding_due: '📋', transfer_approved: '🔄',
    bhxh_due: '🏥', ot_approved: '⏰', custom: '📢',
  };
  return map[type] || '🔔';
}

function typeLabel(type) {
  const map = {
    contract_expiring: 'Hợp đồng', probation_ending: 'Thử việc', birthday: 'Sinh nhật',
    leave_approved: 'Nghỉ phép', leave_rejected: 'Nghỉ phép', approval_pending: 'Phê duyệt',
    payroll_finalized: 'Lương', transfer_approved: 'Điều chuyển', bhxh_due: 'BHXH',
    ot_approved: 'Tăng ca', custom: 'Thông báo',
  };
  return map[type] || type;
}

function priorityLabel(p) {
  return { low: 'Thấp', normal: 'Bình thường', high: 'Cao', urgent: 'Khẩn' }[p] || p;
}

function priorityVariant(p) {
  return { low: 'default', normal: 'default', high: 'warning', urgent: 'danger' }[p] || 'default';
}

function priorityBg(p) {
  return {
    low:    'bg-slate-100',
    normal: 'bg-blue-50',
    high:   'bg-amber-50',
    urgent: 'bg-red-50',
  }[p] || 'bg-slate-100';
}

function timeAgo(dateStr) {
  const diff = (Date.now() - new Date(dateStr)) / 1000;
  if (diff < 60)    return 'Vừa xong';
  if (diff < 3600)  return `${Math.floor(diff / 60)} phút trước`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`;
  const days = Math.floor(diff / 86400);
  return days === 1 ? 'Hôm qua' : `${days} ngày trước`;
}

onMounted(load);
</script>
