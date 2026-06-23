<template>
  <div class="relative" ref="bellRef">
    <!-- Bell button -->
    <button
      type="button"
      class="relative flex items-center justify-center w-9 h-9 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition-colors"
      @click="togglePanel"
      title="Thông báo"
    >
      <span class="text-lg">🔔</span>
      <span
        v-if="unreadCount > 0"
        class="absolute -top-0.5 -right-0.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <!-- Dropdown panel -->
    <Transition name="notif-slide">
      <div
        v-if="open"
        class="absolute right-0 top-11 w-96 bg-white rounded-xl shadow-2xl border border-slate-200 z-50 overflow-hidden"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50">
          <h3 class="font-semibold text-slate-800 text-sm">Thông báo</h3>
          <div class="flex items-center gap-2">
            <button
              v-if="unreadCount > 0"
              type="button"
              class="text-xs text-primary-600 hover:underline"
              @click="markAllRead"
            >
              Đọc tất cả
            </button>
            <button type="button" class="text-slate-400 hover:text-slate-600 text-lg leading-none" @click="open = false">×</button>
          </div>
        </div>

        <!-- List -->
        <div class="max-h-[420px] overflow-y-auto divide-y divide-slate-50">
          <div v-if="loading" class="py-8 text-center text-slate-400 text-sm">Đang tải...</div>

          <template v-else-if="notifications.length">
            <div
              v-for="n in notifications"
              :key="n.id"
              class="flex gap-3 px-4 py-3 hover:bg-slate-50 cursor-pointer transition-colors"
              :class="!n.read_at ? 'bg-blue-50/40' : ''"
              @click="handleClick(n)"
            >
              <div class="flex-shrink-0 text-xl mt-0.5">{{ typeIcon(n.type) }}</div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-800 leading-tight" :class="!n.read_at ? 'font-semibold' : ''">
                  {{ n.title }}
                </p>
                <p v-if="n.body" class="text-xs text-slate-500 mt-0.5 line-clamp-2">{{ n.body }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ timeAgo(n.created_at) }}</p>
              </div>
              <div class="flex-shrink-0 flex items-start gap-1 pt-0.5">
                <span
                  v-if="!n.read_at"
                  class="w-2 h-2 rounded-full bg-blue-500 mt-1.5 flex-shrink-0"
                />
                <button
                  type="button"
                  class="text-slate-300 hover:text-red-400 text-xs px-1"
                  title="Xóa"
                  @click.stop="remove(n)"
                >×</button>
              </div>
            </div>
          </template>

          <div v-else class="py-12 text-center text-slate-400">
            <p class="text-2xl mb-2">🔕</p>
            <p class="text-sm">Không có thông báo mới</p>
          </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-slate-100 px-4 py-2 bg-slate-50 text-center">
          <router-link
            :to="{ name: 'notifications' }"
            class="text-xs text-primary-600 hover:underline"
            @click="open = false"
          >
            Xem tất cả thông báo →
          </router-link>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api/client';

const router = useRouter();

const open          = ref(false);
const loading       = ref(false);
const notifications = ref([]);
const unreadCount   = ref(0);
const bellRef       = ref(null);

let pollInterval = null;

// ── Load ───────────────────────────────────────────────────────────────────
async function loadCount() {
  try {
    const { data } = await api.get('/notifications/count');
    unreadCount.value = data.data?.unread_count ?? 0;
  } catch { /* silent */ }
}

async function loadNotifications() {
  loading.value = true;
  try {
    const { data } = await api.get('/notifications');
    notifications.value = data.data?.items ?? [];
    unreadCount.value   = data.data?.unread_count ?? 0;
  } finally {
    loading.value = false;
  }
}

// ── Actions ────────────────────────────────────────────────────────────────
function togglePanel() {
  open.value = !open.value;
  if (open.value) {
    loadNotifications();
  }
}

async function markAllRead() {
  await api.post('/notifications/read');
  notifications.value.forEach((n) => { n.read_at = new Date().toISOString(); });
  unreadCount.value = 0;
}

async function markRead(notif) {
  if (notif.read_at) return;
  await api.post('/notifications/read', { id: notif.id });
  notif.read_at = new Date().toISOString();
  unreadCount.value = Math.max(0, unreadCount.value - 1);
}

async function remove(notif) {
  await api.delete(`/notifications/${notif.id}`);
  const idx = notifications.value.findIndex((n) => n.id === notif.id);
  if (idx !== -1) {
    if (!notifications.value[idx].read_at) {
      unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
    notifications.value.splice(idx, 1);
  }
}

async function handleClick(notif) {
  await markRead(notif);
  open.value = false;
  if (notif.action_url) {
    router.push(notif.action_url);
  }
}

// ── Helpers ────────────────────────────────────────────────────────────────
function typeIcon(type) {
  const map = {
    contract_expiring: '⚠️',
    probation_ending:  '⏳',
    birthday:          '🎂',
    leave_approved:    '✅',
    leave_rejected:    '❌',
    approval_pending:  '📥',
    payroll_finalized: '💰',
    onboarding_due:    '📋',
    transfer_approved: '🔄',
    bhxh_due:          '🏥',
    ot_approved:       '⏰',
    custom:            '📢',
  };
  return map[type] || '🔔';
}

function timeAgo(dateStr) {
  const diff = (Date.now() - new Date(dateStr)) / 1000;
  if (diff < 60)   return 'Vừa xong';
  if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`;
  return `${Math.floor(diff / 86400)} ngày trước`;
}

// ── Close on outside click ─────────────────────────────────────────────────
function onClickOutside(e) {
  if (bellRef.value && !bellRef.value.contains(e.target)) {
    open.value = false;
  }
}

// ── Lifecycle ──────────────────────────────────────────────────────────────
onMounted(() => {
  loadCount();
  document.addEventListener('click', onClickOutside);
  // Poll badge count every 90 seconds
  pollInterval = setInterval(loadCount, 90_000);
});

onUnmounted(() => {
  document.removeEventListener('click', onClickOutside);
  clearInterval(pollInterval);
});
</script>

<style scoped>
.notif-slide-enter-active,
.notif-slide-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.notif-slide-enter-from,
.notif-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
