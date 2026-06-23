<template>
  <RouterLink
    :to="item.to"
    class="flex items-center gap-2.5 rounded-lg py-2 text-sm transition-colors group relative"
    :class="[
      collapsed ? 'justify-center px-0' : indented ? 'pl-7 pr-3' : 'px-3',
      active
        ? 'bg-primary-600 text-white'
        : 'text-slate-300 hover:bg-slate-800 hover:text-white',
    ]"
    :title="collapsed ? item.label : ''"
  >
    <span class="flex-shrink-0 text-base leading-none">{{ item.icon }}</span>
    <span v-if="!collapsed" class="truncate">{{ item.label }}</span>

    <!-- Tooltip when collapsed -->
    <span
      v-if="collapsed"
      class="absolute left-full ml-2 z-50 hidden group-hover:block whitespace-nowrap rounded bg-slate-800 px-2 py-1 text-xs text-white shadow-lg"
    >
      {{ item.label }}
    </span>
  </RouterLink>
</template>

<script setup>
defineProps({
  item: { type: Object, required: true },
  active: { type: Boolean, default: false },
  collapsed: { type: Boolean, default: false },
  indented: { type: Boolean, default: false },
});
</script>
