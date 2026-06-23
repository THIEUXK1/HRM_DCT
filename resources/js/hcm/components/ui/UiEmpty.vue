<template>
  <div class="flex flex-col items-center justify-center py-16 text-center">
    <div class="mb-3 text-4xl opacity-40">{{ icon }}</div>
    <p class="font-medium text-slate-700">{{ title }}</p>
    <p v-if="description" class="mt-1 max-w-sm text-sm text-slate-500">{{ description }}</p>
    <div v-if="$slots.action" class="mt-4">
      <slot name="action" />
    </div>
  </div>
</template>

<script setup>
defineProps({
    icon: { type: String, default: '📋' },
    title: { type: String, default: 'Chưa có dữ liệu' },
    description: String,
});
</script>
