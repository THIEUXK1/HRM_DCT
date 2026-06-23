<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/50" @click="$emit('update:modelValue', false)" />
      <div
        class="relative z-10 w-full hcm-card p-6 max-h-[90vh] overflow-y-auto"
        :class="fullWidth ? 'max-w-5xl' : wide ? 'max-w-3xl' : 'max-w-lg'"
      >
        <div class="mb-4 flex items-start justify-between gap-4">
          <h3 class="text-lg font-semibold text-slate-900">{{ title }}</h3>
          <button type="button" class="text-slate-400 hover:text-slate-600" @click="$emit('update:modelValue', false)">✕</button>
        </div>
        <slot />
      </div>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({
    modelValue: Boolean,
    title: { type: String, default: '' },
    wide: { type: Boolean, default: false },
    fullWidth: { type: Boolean, default: false },
});
defineEmits(['update:modelValue']);
</script>
