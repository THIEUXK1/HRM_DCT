<template>
  <div class="space-y-3 rounded-lg border border-slate-200 bg-slate-50/80 p-3">
    <p class="text-xs font-medium text-slate-600">Đối tượng áp dụng</p>
    <div v-if="showModeSwitcher && modes.length > 1" class="flex flex-wrap gap-2">
      <button
        v-for="opt in modes"
        :key="opt.value"
        type="button"
        class="text-xs px-3 py-1.5 rounded-full border transition-colors"
        :class="mode === opt.value ? 'bg-primary-100 border-primary-400 text-primary-800' : 'border-slate-200 text-slate-600 hover:bg-white'"
        @click="mode = opt.value"
      >
        {{ opt.label }}
      </button>
    </div>

    <p v-if="mode === 'company'" class="text-xs text-slate-600">
      Áp dụng mặc định cho toàn bộ nhân viên công ty (không ghi đè riêng từng NV).
    </p>

    <div v-else-if="mode === 'single'">
      <label class="text-sm font-medium">Nhân viên</label>
      <select :value="employeeId" class="hcm-input mt-1 w-full" required @change="onSingleChange">
        <option value="">— Chọn —</option>
        <option v-for="e in employees" :key="e.id" :value="e.id">
          {{ e.full_name }} ({{ e.employee_code }})
        </option>
      </select>
    </div>

    <div v-else-if="mode === 'multi'">
      <label class="text-sm font-medium">Nhân viên (chọn nhiều)</label>
      <div v-if="departments.length" class="mt-2 grid gap-2 sm:grid-cols-2">
        <div>
          <label class="text-xs text-slate-500">Lọc theo phòng ban</label>
          <select v-model="filterDepartmentId" class="hcm-input mt-1 w-full text-sm">
            <option value="">Tất cả phòng ban</option>
            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div>
          <label class="text-xs text-slate-500">Tìm kiếm</label>
          <input
            v-model="multiSearch"
            type="text"
            class="hcm-input mt-1 w-full text-sm"
            placeholder="Tên hoặc mã NV…"
          />
        </div>
      </div>
      <input
        v-else
        v-model="multiSearch"
        type="text"
        class="hcm-input mt-1 w-full text-sm"
        placeholder="Tìm theo tên hoặc mã NV…"
      />
      <div class="mt-2 flex flex-wrap gap-3">
        <button type="button" class="text-xs text-primary-600 hover:underline" @click="selectAllFiltered">
          Chọn tất cả{{ multiSearch.trim() ? ' (đang lọc)' : '' }}
        </button>
        <button type="button" class="text-xs text-slate-500 hover:underline" @click="clearAllSelected">
          Bỏ chọn tất cả
        </button>
      </div>
      <div class="mt-2 max-h-[220px] overflow-y-auto rounded-lg border border-slate-200 bg-white">
        <label
          v-for="e in filteredEmployees"
          :key="e.id"
          class="flex cursor-pointer items-center gap-2 border-b border-slate-100 px-3 py-2 text-sm last:border-b-0 hover:bg-slate-50"
        >
          <input
            type="checkbox"
            class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"
            :checked="isEmployeeSelected(e.id)"
            @change="toggleEmployee(e.id, $event.target.checked)"
          />
          <span>{{ e.full_name }} ({{ e.employee_code }})</span>
        </label>
        <p v-if="!filteredEmployees.length" class="px-3 py-4 text-center text-xs text-slate-400">
          Không tìm thấy nhân viên
        </p>
      </div>
      <p v-if="employeeIds.length" class="mt-1 text-xs text-primary-700">
        Đã chọn: {{ employeeIds.length }} nhân viên
      </p>
    </div>

    <div v-else-if="mode === 'department'">
      <label class="text-sm font-medium">Phòng ban (toàn bộ NV đang làm việc)</label>
      <select :value="departmentId" class="hcm-input mt-1 w-full" required @change="onDeptChange">
        <option value="">— Chọn phòng ban —</option>
        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  employees: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  includeCompanyMode: { type: Boolean, default: false },
  /** Chỉ hiện các chế độ này, VD: ['multi'] */
  allowedModes: { type: Array, default: null },
  showModeSwitcher: { type: Boolean, default: true },
});

const modes = computed(() => {
  const list = [
    { value: 'single', label: 'Một nhân viên' },
    { value: 'multi', label: 'Nhiều nhân viên' },
    { value: 'department', label: 'Cả phòng ban' },
  ];
  if (props.includeCompanyMode) {
    list.unshift({ value: 'company', label: 'Toàn công ty' });
  }
  if (props.allowedModes?.length) {
    const allowed = new Set(props.allowedModes);
    return list.filter((m) => allowed.has(m.value));
  }
  return list;
});

const mode = defineModel('mode', { type: String, default: 'single' });

watch(
  modes,
  (list) => {
    if (list.length && !list.some((m) => m.value === mode.value)) {
      mode.value = list[0].value;
    }
  },
  { immediate: true },
);
const employeeId = defineModel('employeeId', { type: [Number, String, null], default: null });
const employeeIds = defineModel('employeeIds', { type: Array, default: () => [] });
const departmentId = defineModel('departmentId', { type: [Number, String, null], default: '' });

function onSingleChange(event) {
  const v = event.target.value;
  employeeId.value = v ? Number(v) : null;
}

const multiSearch = ref('');
const filterDepartmentId = ref('');

const filteredEmployees = computed(() => {
  let list = props.employees;
  if (filterDepartmentId.value) {
    const deptId = Number(filterDepartmentId.value);
    list = list.filter((e) => Number(e.department_id) === deptId);
  }
  const q = multiSearch.value.trim().toLowerCase();
  if (!q) {
    return list;
  }
  return list.filter(
    (e) =>
      String(e.full_name ?? '').toLowerCase().includes(q)
      || String(e.employee_code ?? '').toLowerCase().includes(q),
  );
});

function isEmployeeSelected(id) {
  return employeeIds.value.includes(Number(id));
}

function toggleEmployee(id, checked) {
  const numId = Number(id);
  if (checked) {
    if (!employeeIds.value.includes(numId)) {
      employeeIds.value = [...employeeIds.value, numId];
    }
    return;
  }
  employeeIds.value = employeeIds.value.filter((x) => x !== numId);
}

function selectAllFiltered() {
  const ids = new Set(employeeIds.value.map(Number));
  filteredEmployees.value.forEach((e) => ids.add(Number(e.id)));
  employeeIds.value = Array.from(ids);
}

function clearAllSelected() {
  employeeIds.value = [];
}

function onDeptChange(event) {
  departmentId.value = event.target.value ? Number(event.target.value) : '';
}
</script>
