<template>
  <div v-if="lastPage > 1" class="flex items-center justify-between py-3 px-1">
    <p class="text-sm text-slate-500">
      {{ from }}–{{ to }} / {{ total }} bản ghi
    </p>
    <div class="flex items-center gap-1">
      <button
        type="button"
        class="px-2 py-1 text-sm rounded border border-slate-200 hover:bg-slate-50 disabled:opacity-40"
        :disabled="currentPage <= 1"
        @click="$emit('change', currentPage - 1)"
      >
        ‹
      </button>

      <template v-for="p in pages" :key="p">
        <span v-if="p === '...'" class="px-2 py-1 text-slate-400 text-sm">…</span>
        <button
          v-else
          type="button"
          class="px-2.5 py-1 text-sm rounded border transition-colors"
          :class="p === currentPage
            ? 'border-primary-600 bg-primary-600 text-white'
            : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
          @click="$emit('change', p)"
        >
          {{ p }}
        </button>
      </template>

      <button
        type="button"
        class="px-2 py-1 text-sm rounded border border-slate-200 hover:bg-slate-50 disabled:opacity-40"
        :disabled="currentPage >= lastPage"
        @click="$emit('change', currentPage + 1)"
      >
        ›
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentPage: { type: Number, required: true },
  lastPage:    { type: Number, required: true },
  total:       { type: Number, default: 0 },
  from:        { type: Number, default: 0 },
  to:          { type: Number, default: 0 },
})

defineEmits(['change'])

/** Build smart page list with ellipsis */
const pages = computed(() => {
  const { currentPage: cur, lastPage: last } = props
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)

  const result = []
  const addPage = (p) => result.push(p)
  const addDots = () => result.push('...')

  addPage(1)
  if (cur > 3) addDots()
  for (let p = Math.max(2, cur - 1); p <= Math.min(last - 1, cur + 1); p++) {
    addPage(p)
  }
  if (cur < last - 2) addDots()
  addPage(last)

  return result
})
</script>
