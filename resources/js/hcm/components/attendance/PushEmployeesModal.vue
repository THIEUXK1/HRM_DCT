<template>
  <UiModal :model-value="modelValue" title="Đẩy nhân viên lên máy chấm công" @update:model-value="close">

    <!-- Kết quả sau khi đẩy -->
    <div v-if="results" class="space-y-3">
      <p class="text-sm font-medium text-slate-700">Kết quả đẩy:</p>
      <div
        v-for="r in results"
        :key="r.device_id"
        class="flex items-center justify-between rounded-lg border px-4 py-2 text-sm"
        :class="r.errors > 0 && r.pushed === 0 ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50'"
      >
        <span class="font-medium">{{ r.device_name }}</span>
        <span :class="r.errors > 0 && r.pushed === 0 ? 'text-red-600' : 'text-emerald-700'">{{ r.message }}</span>
      </div>
      <div class="flex justify-end pt-2">
        <button type="button" class="hcm-btn-primary" @click="close">Đóng</button>
      </div>
    </div>

    <!-- Form -->
    <form v-else class="space-y-5" @submit.prevent="submit">

      <!-- Chọn thiết bị -->
      <div>
        <p class="hcm-label mb-2">Chọn thiết bị nhận dữ liệu <span class="text-red-500">*</span></p>
        <div class="space-y-1.5 max-h-36 overflow-y-auto rounded border p-2">
          <label
            v-for="dev in zkDevices"
            :key="dev.id"
            class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 rounded px-1 py-0.5"
          >
            <input v-model="form.device_ids" type="checkbox" :value="dev.id" class="rounded" />
            <span class="text-sm">
              <span class="font-medium">{{ dev.name }}</span>
              <span class="text-slate-400 ml-1 font-mono text-xs">{{ dev.ip_address }}:{{ dev.port }}</span>
              <span v-if="!dev.is_active" class="ml-1 text-xs text-slate-400">(Tắt)</span>
            </span>
          </label>
          <p v-if="!zkDevices.length" class="text-sm text-slate-400 px-1">Không có thiết bị ZKTeco nào có IP.</p>
        </div>
      </div>

      <!-- Chọn nhân viên -->
      <div>
        <p class="hcm-label mb-2">Nhân viên cần đẩy <span class="text-red-500">*</span></p>
        <div class="flex flex-col gap-2">
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="form.mode" type="radio" value="all" />
            <span class="text-sm">Tất cả nhân viên có mã sinh trắc học</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="form.mode" type="radio" value="manual" @change="onManualMode" />
            <span class="text-sm">Chọn từng người</span>
          </label>
        </div>

        <!-- Manual: dept filter + employee checklist -->
        <div v-if="form.mode === 'manual'" class="mt-3 space-y-2">
          <select v-model="filterDeptId" class="hcm-input text-sm" @change="loadEmployees">
            <option :value="null">-- Tất cả phòng ban --</option>
            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
          </select>

          <div v-if="loadingEmployees" class="text-sm text-slate-400 px-1 py-2">Đang tải danh sách...</div>
          <div v-else class="rounded border overflow-hidden">
            <!-- Header: select all + count -->
            <div class="flex items-center gap-2 px-3 py-2 border-b bg-slate-50">
              <input
                ref="checkAllRef"
                type="checkbox"
                :checked="allSelected"
                class="rounded"
                @change="toggleAll"
              />
              <span class="text-xs font-medium text-slate-600">
                Đã chọn {{ form.employee_ids.length }}/{{ employees.length }} người
              </span>
            </div>
            <!-- Employee list -->
            <div class="max-h-52 overflow-y-auto divide-y divide-slate-50">
              <label
                v-for="emp in employees"
                :key="emp.id"
                class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 px-3 py-1.5"
              >
                <input v-model="form.employee_ids" type="checkbox" :value="emp.id" class="rounded" />
                <span class="text-sm flex-1">{{ emp.last_name }} {{ emp.first_name }}</span>
                <span class="text-xs text-slate-400 font-mono">PIN: {{ emp.profile?.biometric_id }}</span>
              </label>
              <p v-if="!employees.length" class="text-sm text-slate-400 px-3 py-2">
                Không có nhân viên nào có mã sinh trắc học.
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-1">
        <button type="button" class="hcm-btn-secondary" @click="close">Hủy</button>
        <button type="submit" class="hcm-btn-primary" :disabled="pushing || !form.device_ids.length">
          {{ pushing ? 'Đang đẩy...' : submitLabel }}
        </button>
      </div>
    </form>
  </UiModal>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue';
import api from '../../api/client.js';
import { useToast } from '../../composables/useToast.js';
import UiModal from '../ui/UiModal.vue';

const props = defineProps({
  modelValue: Boolean,
  devices: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);
const toast = useToast();

const zkDevices       = ref([]);
const departments     = ref([]);
const employees       = ref([]);
const filterDeptId    = ref(null);
const loadingEmployees = ref(false);
const pushing         = ref(false);
const results         = ref(null);
const checkAllRef     = ref(null);

const form = reactive({ device_ids: [], mode: 'all', employee_ids: [] });

const allSelected  = computed(() => employees.value.length > 0 && form.employee_ids.length === employees.value.length);
const someSelected = computed(() => form.employee_ids.length > 0 && !allSelected.value);
const submitLabel  = computed(() => {
  const devCount = form.device_ids.length;
  if (form.mode === 'all') return `Đẩy lên ${devCount} thiết bị`;
  return `Đẩy ${form.employee_ids.length} người lên ${devCount} thiết bị`;
});

// Sync indeterminate state (DOM property, not attribute)
watch([allSelected, someSelected], async () => {
  await nextTick();
  if (checkAllRef.value) checkAllRef.value.indeterminate = someSelected.value;
});

watch(() => props.modelValue, async (open) => {
  if (!open) return;

  results.value      = null;
  form.mode          = 'all';
  form.employee_ids  = [];
  filterDeptId.value = null;
  employees.value    = [];

  zkDevices.value    = props.devices.filter(d => d.ip_address);
  form.device_ids    = zkDevices.value.filter(d => d.is_active).map(d => d.id);

  if (!departments.value.length) {
    try {
      const res = await api.get('/departments');
      departments.value = res.data.data ?? [];
    } catch { /* dept filter optional */ }
  }
});

async function onManualMode() {
  if (!employees.value.length) await loadEmployees();
}

async function loadEmployees() {
  loadingEmployees.value = true;
  try {
    const params = { has_biometric: 1, per_page: 500 };
    if (filterDeptId.value) params.department_id = filterDeptId.value;
    const res = await api.get('/employees', { params });
    employees.value = res.data.data?.data ?? res.data.data ?? [];
    // Pre-select tất cả sau khi load
    form.employee_ids = employees.value.map(e => e.id);
  } catch {
    toast.show('Lỗi tải danh sách nhân viên', 'error');
  } finally {
    loadingEmployees.value = false;
  }
}

function toggleAll() {
  form.employee_ids = allSelected.value ? [] : employees.value.map(e => e.id);
}

async function submit() {
  if (!form.device_ids.length) {
    toast.show('Chọn ít nhất 1 thiết bị', 'error');
    return;
  }
  if (form.mode === 'manual' && !form.employee_ids.length) {
    toast.show('Chọn ít nhất 1 nhân viên', 'error');
    return;
  }

  pushing.value = true;
  try {
    const res = await api.post('/attendance-devices/push-employees-bulk', {
      device_ids:   form.device_ids,
      mode:         form.mode,
      employee_ids: form.mode === 'manual' ? form.employee_ids : [],
    });
    results.value = (res.data.data ?? res.data).results ?? [];
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi đẩy nhân viên lên thiết bị', 'error');
  } finally {
    pushing.value = false;
  }
}

function close() {
  emit('update:modelValue', false);
}
</script>
