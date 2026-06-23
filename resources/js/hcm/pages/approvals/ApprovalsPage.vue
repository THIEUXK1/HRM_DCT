<template>
  <div>
    <UiPageHeader title="Hộp thư duyệt" subtitle="Tuyển dụng · Nghỉ phép · Tăng ca" breadcrumb="Approvals" />

    <div class="hcm-card mb-4 p-4">
      <UiSearchInput
        v-model="listSearch"
        placeholder="Tìm theo loại yêu cầu, nhãn hoặc tên nhân viên..."
      />
    </div>

    <div class="space-y-4">
      <div v-for="(item, idx) in filteredItems" :key="idx" class="hcm-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <UiBadge variant="info">{{ item.entity_type_label || item.instance.entity_type }}</UiBadge>
            <p class="mt-2 font-semibold">{{ item.entity_label || ('#' + item.instance.entity_id) }}</p>
            <p class="text-sm text-slate-500">
              Bước {{ item.instance.current_step }}: {{ item.current_step_label || 'Chờ duyệt' }}
            </p>
            <p v-if="item.entity?.start_date" class="text-sm mt-1">
              {{ item.entity.start_date }} → {{ item.entity.end_date }} ({{ item.entity.total_days }} ngày)
            </p>
          </div>
          <div class="flex gap-2">
            <button type="button" class="hcm-btn-primary" @click="act(item.instance.id, 'approve')">Duyệt</button>
            <button type="button" class="hcm-btn-secondary" @click="act(item.instance.id, 'reject')">Từ chối</button>
          </div>
        </div>
      </div>
      <UiEmpty v-if="!filteredItems.length" title="Không có yêu cầu chờ duyệt" />
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiSearchInput from '../../components/ui/UiSearchInput.vue';
import { useToast } from '../../composables/useToast';

const toast = useToast();
const items = ref([]);
const listSearch = ref('');

const filteredItems = computed(() => {
  const q = listSearch.value.trim().toLowerCase();
  if (!q) return items.value;
  return items.value.filter((item) => {
    const parts = [
      item.entity_type_label,
      item.instance?.entity_type,
      item.entity_label,
      item.entity?.employee?.full_name,
      item.entity?.employee?.employee_code,
      item.current_step_label,
    ];
    return parts.some((p) => String(p || '').toLowerCase().includes(q));
  });
});

async function load() {
  const { data } = await api.get('/approvals/inbox');
  items.value = data.data;
}

async function act(id, action) {
  await api.post(`/approvals/${id}/${action}`);
  toast.show(action === 'approve' ? 'Đã duyệt' : 'Đã từ chối');
  await load();
}

onMounted(load);
</script>
