<template>
  <div class="flex flex-wrap items-end gap-3">
    <div v-if="showCompany && showCompanyPicker">
      <label class="text-sm font-medium text-slate-700">Công ty</label>
      <select
        :value="filterCompanyId"
        class="hcm-input mt-1 min-w-[200px]"
        @change="onCompanySelect($event.target.value)"
      >
        <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
      </select>
    </div>

    <div v-if="showBranch && !isSingleBranchMode">
      <label class="text-sm font-medium text-slate-700">Chi nhánh</label>
      <select
        :value="filterBranchId"
        class="hcm-input mt-1 min-w-[180px]"
        @change="onBranchChange($event.target.value)"
      >
        <option value="">Tất cả chi nhánh</option>
        <option v-for="b in branchList" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
      </select>
    </div>
    <div v-else-if="showBranch && isSingleBranchMode && branchList[0]" class="text-sm text-slate-500 pb-2">
      Chi nhánh: <strong>{{ branchList[0].name }}</strong>
    </div>

    <div v-if="showDepartment">
      <label class="text-sm font-medium text-slate-700">Phòng ban</label>
      <select
        :value="filterDepartmentId"
        class="hcm-input mt-1 min-w-[180px]"
        @change="onDepartmentChange($event.target.value)"
      >
        <option value="">Tất cả phòng ban</option>
        <option v-for="d in departmentList" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
      </select>
    </div>

    <div v-if="showStatus">
      <label class="text-sm font-medium text-slate-700">Trạng thái</label>
      <select
        :value="filterStatus"
        class="hcm-input mt-1 min-w-[140px]"
        @change="onStatusChange($event.target.value)"
      >
        <option value="">Tất cả</option>
        <option value="active">Đang làm việc</option>
        <option value="probation">Thử việc</option>
        <option value="training">Đang đào tạo</option>
        <option value="terminated">Đã nghỉ</option>
      </select>
    </div>

    <button
      v-if="showReset"
      type="button"
      class="hcm-btn-secondary text-sm"
      @click="$emit('reset')"
    >
      Xóa lọc
    </button>
  </div>
</template>

<script setup>
import { computed, unref } from 'vue';
import { useAppStore } from '../../stores/app';

const props = defineProps({
  showCompany: { type: Boolean, default: false },
  showBranch: { type: Boolean, default: true },
  showDepartment: { type: Boolean, default: true },
  showStatus: { type: Boolean, default: false },
  showReset: { type: Boolean, default: true },
  showCompanyPicker: { type: Boolean, default: false },
  singleBranchMode: { type: Boolean, default: false },
  filterCompanyId: { type: String, default: '' },
  filterBranchId: { type: String, default: '' },
  filterDepartmentId: { type: String, default: '' },
  filterStatus: { type: String, default: '' },
  branches: { type: Array, default: () => [] },
  filteredDepartments: { type: Array, default: () => [] },
});

function asArray(value) {
  const resolved = unref(value);
  return Array.isArray(resolved) ? resolved.filter(Boolean) : [];
}

const branchList = computed(() => asArray(props.branches));
const departmentList = computed(() => asArray(props.filteredDepartments));
const isSingleBranchMode = computed(() => Boolean(unref(props.singleBranchMode)));

const emit = defineEmits([
  'update:filterCompanyId',
  'update:filterBranchId',
  'update:filterDepartmentId',
  'update:filterStatus',
  'company-change',
  'change',
  'reset',
]);

const appStore = useAppStore();
const companies = computed(() => appStore.companies);

function onCompanySelect(value) {
  emit('update:filterCompanyId', value);
  emit('company-change', value);
  emit('change');
}

function onBranchChange(value) {
  emit('update:filterBranchId', value);
  emit('change');
}

function onDepartmentChange(value) {
  emit('update:filterDepartmentId', value);
  emit('change');
}

function onStatusChange(value) {
  emit('update:filterStatus', value);
  emit('change');
}
</script>
