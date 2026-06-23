<template>
  <div>
    <UiPageHeader title="Onboarding" subtitle="Checklist nhận việc · Buddy · Xác nhận hoàn tất" breadcrumb="Onboarding" />

    <div class="hcm-card mb-4 p-4 space-y-4">
      <UiOrgScopeFilters
        :show-company-picker="scope.showCompanyPicker"
        :single-branch-mode="scope.singleBranchMode"
        v-model:filter-branch-id="scope.filterBranchId"
        v-model:filter-department-id="scope.filterDepartmentId"
        :branches="scope.branches"
        :filtered-departments="scope.filteredDepartments"
        @change="load"
        @reset="resetScopeFilters"
      />
      <UiSearchInput
        v-model="listSearch"
        placeholder="Tìm theo tên hoặc mã nhân viên..."
        @search="load"
      />
    </div>

    <div class="space-y-4">
      <div v-for="emp in employees" :key="emp.id" class="hcm-card p-5">
        <div class="flex flex-wrap justify-between gap-3">
          <div>
            <RouterLink :to="{ name: 'employee-detail', params: { id: emp.id } }" class="font-semibold text-primary-700 hover:underline">
              {{ emp.full_name }}
            </RouterLink>
            <p class="text-sm text-slate-500">{{ emp.employee_code }} · {{ emp.department?.name || '—' }}</p>
            <UiBadge v-if="emp.onboarding_completed_at" variant="success" class="mt-2">Đã hoàn tất onboarding</UiBadge>
          </div>
          <div class="text-right">
            <p class="text-2xl font-bold text-primary-600">{{ progress(emp) }}%</p>
            <p class="text-xs text-slate-500">{{ completedCount(emp) }}/{{ emp.onboarding_tasks?.length || 0 }} mục</p>
            <button
              v-if="progress(emp) === 100 && !emp.onboarding_completed_at"
              type="button"
              class="hcm-btn-primary text-xs mt-2"
              @click="markComplete(emp.id)"
            >
              Xác nhận hoàn tất
            </button>
          </div>
        </div>
        <ul class="mt-4 space-y-2">
          <li
            v-for="t in emp.onboarding_tasks"
            :key="t.id"
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm"
          >
            <span>{{ t.task?.title }}</span>
            <div class="flex flex-wrap items-center gap-2">
              <select
                :value="t.assigned_to || ''"
                class="hcm-input text-xs py-1 max-w-[140px]"
                @change="updateTask(emp.id, t.id, { assigned_to: $event.target.value || null })"
              >
                <option value="">— Buddy —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <select
                :value="t.status"
                class="hcm-input text-xs py-1"
                @change="updateTask(emp.id, t.id, { status: $event.target.value })"
              >
                <option value="pending">Chờ</option>
                <option value="in_progress">Đang làm</option>
                <option value="completed">Hoàn thành</option>
              </select>
            </div>
          </li>
        </ul>
      </div>
      <UiEmpty v-if="!employees.length" title="Không có onboarding đang mở" />
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiSearchInput from '../../components/ui/UiSearchInput.vue';
import UiOrgScopeFilters from '../../components/ui/UiOrgScopeFilters.vue';
import { extractItems } from '../../composables/usePagination';
import { useOrgScopeFilters } from '../../composables/useOrgScopeFilters';
import { useToast } from '../../composables/useToast';

const toast = useToast();
const scope = useOrgScopeFilters({ includeDepartment: true });
const employees = ref([]);
const users = ref([]);
const listSearch = ref('');

function completedCount(emp) {
  return (emp.onboarding_tasks || []).filter((t) => t.status === 'completed').length;
}

function progress(emp) {
  const total = emp.onboarding_tasks?.length || 0;
  if (!total) return 0;
  return Math.round((completedCount(emp) / total) * 100);
}

async function load(searchValue = listSearch.value) {
  listSearch.value = searchValue;
  const params = { ...scope.toQueryParams() };
  if (listSearch.value.trim()) params.search = listSearch.value.trim();
  const [o, u] = await Promise.all([
    api.get('/onboarding', { params }),
    api.get('/users', { params: { per_page: 200 } }),
  ]);
  employees.value = o.data.data;
  users.value = extractItems(u.data);
}

function resetScopeFilters() {
  scope.resetScope();
  listSearch.value = '';
  load();
}

async function updateTask(employeeId, taskId, payload) {
  await api.put(`/employees/${employeeId}/onboarding-tasks/${taskId}`, payload);
  toast.show('Đã cập nhật');
  await load();
}

async function markComplete(employeeId) {
  await api.post(`/employees/${employeeId}/onboarding/complete`);
  toast.show('Đã xác nhận hoàn tất onboarding');
  await load();
}

onMounted(async () => {
  await scope.loadMeta();
  await load();
});
</script>
