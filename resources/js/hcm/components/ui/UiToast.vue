<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="visible"
        class="fixed bottom-6 right-6 z-[100] max-w-sm rounded-lg px-4 py-3 text-sm text-white shadow-lg"
        :class="type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
      >
        {{ message }}
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { useToast } from '../../composables/useToast';

const { message, type, visible } = useToast();
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
