<template>
  <div v-if="visibleItems.length" class="mb-6 space-y-3">
    <div
      class="hcm-card p-5 border-l-4"
      :class="panelBorderClass"
    >
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h3 class="font-bold flex items-center gap-2" :class="titleClass">
            🔔 {{ title }}
            <UiBadge v-if="visibleItems.length" variant="warning">{{ visibleItems.length }}</UiBadge>
          </h3>
          <p v-if="subtitle" class="text-xs mt-1" :class="subtitleClass">{{ subtitle }}</p>
        </div>
        <button v-if="collapsible" type="button" class="text-xs text-slate-500 hover:text-slate-700" @click="collapsed = !collapsed">
          {{ collapsed ? 'Mở rộng' : 'Thu gọn' }}
        </button>
      </div>

      <ul v-show="!collapsed" class="mt-3 space-y-2 overflow-y-auto pr-1" :style="maxHeight ? { maxHeight } : {}">
        <li
          v-for="(a, idx) in visibleItems"
          :key="idx"
          class="flex flex-wrap items-start justify-between gap-2 text-sm bg-white rounded-lg p-3 border shadow-sm"
          :class="rowClass(a.severity)"
        >
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2 mb-1">
              <UiBadge :variant="severityVariant(a.severity)">{{ severityLabel(a.severity) }}</UiBadge>
              <span class="font-semibold text-slate-800">{{ a.title }}</span>
            </div>
            <p class="text-slate-600">{{ a.message }}</p>
            <p v-if="a.legal_reference" class="text-[11px] text-slate-400 mt-1">📜 {{ a.legal_reference }}</p>
            <p v-if="a.full_name" class="text-xs text-slate-500 mt-1">{{ a.full_name }} · {{ a.employee_code }}</p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <RouterLink
              v-if="resolveLink(a.action_url)"
              :to="resolveLink(a.action_url)"
              class="text-xs font-medium text-primary-600 hover:underline whitespace-nowrap"
            >
              Xử lý →
            </RouterLink>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import UiBadge from './UiBadge.vue';

const props = defineProps({
  items: { type: Array, default: () => [] },
  title: { type: String, default: 'Cảnh báo tuân thủ' },
  subtitle: { type: String, default: '' },
  categories: { type: Array, default: null },
  maxHeight: { type: String, default: '220px' },
  collapsible: { type: Boolean, default: true },
  urgentOnly: { type: Boolean, default: false },
});

const collapsed = ref(false);

const visibleItems = computed(() => {
  let list = props.items || [];
  if (props.categories?.length) {
    list = list.filter((a) => props.categories.includes(a.category));
  }
  if (props.urgentOnly) {
    list = list.filter((a) => a.severity === 'urgent');
  }
  return list;
});

const hasUrgent = computed(() => visibleItems.value.some((a) => a.severity === 'urgent'));

const panelBorderClass = computed(() => (
  hasUrgent.value ? 'border-red-500 bg-red-50/40' : 'border-amber-500 bg-amber-50/50'
));

const titleClass = computed(() => (hasUrgent.value ? 'text-red-800' : 'text-amber-800'));
const subtitleClass = computed(() => (hasUrgent.value ? 'text-red-700' : 'text-amber-700'));

function severityVariant(severity) {
  return { urgent: 'danger', warning: 'warning', info: 'info' }[severity] || 'default';
}

function severityLabel(severity) {
  return { urgent: 'Khẩn', warning: 'Cảnh báo', info: 'Theo dõi' }[severity] || severity;
}

function rowClass(severity) {
  return {
    urgent: 'border-red-200',
    warning: 'border-amber-200',
    info: 'border-blue-100',
  }[severity] || 'border-slate-200';
}

const routeMap = {
  '/contracts': { name: 'contracts' },
  '/attendance': { name: 'attendance' },
  '/work-schedules': { name: 'work-schedules' },
  '/employees': { name: 'employees' },
  '/approvals': { name: 'approvals' },
  '/leave-requests': { name: 'leave' },
};

function resolveLink(url) {
  if (!url) return null;
  if (routeMap[url]) return routeMap[url];
  const empMatch = url.match(/^\/employees\/(\d+)$/);
  if (empMatch) return { name: 'employee-detail', params: { id: empMatch[1] } };
  return null;
}
</script>
