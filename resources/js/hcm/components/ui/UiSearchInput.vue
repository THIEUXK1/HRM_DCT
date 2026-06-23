<template>
  <div class="flex flex-wrap items-center gap-2">
    <input
      :value="modelValue"
      type="search"
      :placeholder="placeholder"
      :class="inputClass"
      @input="onInput"
    />
    <span v-if="hint" class="text-sm text-slate-500">{{ hint }}</span>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Tìm kiếm...' },
  inputClass: { type: String, default: 'hcm-input max-w-sm' },
  debounceMs: { type: Number, default: 350 },
  hint: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'search']);

let timer = null;

function onInput(event) {
  const value = event.target.value;
  emit('update:modelValue', value);
  clearTimeout(timer);
  timer = setTimeout(() => emit('search', value), props.debounceMs);
}
</script>
