<template>
  <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <p v-if="breadcrumb" class="text-xs font-medium uppercase tracking-wide text-primary-600">{{ breadcrumb }}</p>
      <h1 class="text-2xl font-bold text-slate-900">{{ title }}</h1>
      <p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: String,
    breadcrumb: String,
});
</script>
