<template>
  <div>
    <UiPageHeader title="Năng lực" subtitle="Khung năng lực · Yêu cầu vị trí · Ma trận gap" breadcrumb="Competency">
      <template #actions>
        <button type="button" class="hcm-btn-secondary" @click="showGroupForm = true">+ Nhóm</button>
        <button type="button" class="hcm-btn-primary" @click="showCompForm = true">+ Năng lực</button>
      </template>
    </UiPageHeader>

    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
        :class="tab === t.key ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500'"
        @click="tab = t.key"
      >
        {{ t.label }}
      </button>
    </div>

    <!-- Khung năng lực -->
    <div v-if="tab === 'framework'" class="grid gap-6 lg:grid-cols-2">
      <div v-for="group in groups" :key="group.id" class="hcm-card p-5">
        <h3 class="font-semibold text-slate-900">{{ group.name }}</h3>
        <ul class="mt-4 space-y-2">
          <li v-for="c in group.competencies" :key="c.id" class="flex justify-between text-sm border-b border-slate-50 pb-2">
            <span>{{ c.name }} <span class="text-slate-400">({{ c.code }})</span></span>
            <span class="text-xs text-slate-500">max L{{ c.max_level }}</span>
          </li>
        </ul>
      </div>
      <UiEmpty v-if="!groups.length" title="Chưa có khung năng lực" />
    </div>

    <!-- Yêu cầu theo vị trí -->
    <div v-if="tab === 'position'" class="hcm-card p-5 space-y-4">
      <select v-model="selectedPositionId" class="hcm-input max-w-md" @change="loadPositionReqs">
        <option :value="null">— Chọn chức danh —</option>
        <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <div v-if="selectedPositionId" class="space-y-2">
        <div v-for="row in positionReqForm" :key="row.competency_id" class="grid grid-cols-2 gap-3 items-center">
          <span class="text-sm">{{ competencyName(row.competency_id) }}</span>
          <select v-model.number="row.required_level" class="hcm-input text-sm">
            <option v-for="n in 5" :key="n" :value="n">Yêu cầu L{{ n }} — {{ meta.levels[n] }}</option>
          </select>
        </div>
        <button type="button" class="hcm-btn-primary" @click="savePositionReqs">Lưu yêu cầu vị trí</button>
      </div>
    </div>

    <!-- Ma trận nhân viên -->
    <div v-if="tab === 'matrix'" class="space-y-4">
      <div class="flex flex-wrap gap-3">
        <select v-model="selectedEmployeeId" class="hcm-input max-w-sm" @change="loadMatrix">
          <option :value="null">— Chọn nhân viên —</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
        </select>
      </div>

      <div v-if="matrix?.summary" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="hcm-card p-3 text-center">
          <p class="text-2xl font-bold">{{ matrix.summary.coverage_percent }}%</p>
          <p class="text-xs text-slate-500">Đạt yêu cầu</p>
        </div>
        <div class="hcm-card p-3 text-center">
          <p class="text-2xl font-bold text-emerald-600">{{ matrix.summary.met }}</p>
          <p class="text-xs text-slate-500">Đủ mức</p>
        </div>
        <div class="hcm-card p-3 text-center">
          <p class="text-2xl font-bold text-amber-600">{{ matrix.summary.gaps }}</p>
          <p class="text-xs text-slate-500">Còn gap</p>
        </div>
        <div class="hcm-card p-3 text-center">
          <p class="text-2xl font-bold text-slate-400">{{ matrix.summary.not_assessed }}</p>
          <p class="text-xs text-slate-500">Chưa đánh giá</p>
        </div>
      </div>

      <div v-if="matrix?.items?.length" class="hcm-card overflow-hidden">
        <table class="hcm-table w-full">
          <thead>
            <tr>
              <th>Năng lực</th>
              <th>Yêu cầu</th>
              <th>Hiện tại</th>
              <th>Gap</th>
              <th>Trạng thái</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in matrix.items" :key="row.competency_id">
              <td>{{ row.competency?.name }}</td>
              <td>{{ row.required_level ?? '—' }}</td>
              <td>{{ row.current_level ?? '—' }}</td>
              <td>{{ row.gap ?? '—' }}</td>
              <td>
                <UiBadge :variant="gapVariant(row.gap_status)">{{ meta.gap_statuses[row.gap_status] }}</UiBadge>
              </td>
              <td>
                <button type="button" class="text-xs text-primary-600" @click="quickAssess(row)">Đánh giá</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <UiEmpty v-else-if="selectedEmployeeId" title="Chưa có dữ liệu ma trận" />
    </div>

    <UiModal v-model="showGroupForm" title="Thêm nhóm năng lực">
      <form class="space-y-3" @submit.prevent="saveGroup">
        <input v-model="groupName" class="hcm-input" placeholder="Tên nhóm" required />
        <button type="submit" class="hcm-btn-primary w-full">Lưu</button>
      </form>
    </UiModal>

    <UiModal v-model="showCompForm" title="Thêm năng lực">
      <form class="space-y-3" @submit.prevent="saveCompetency">
        <select v-model="compForm.competency_group_id" class="hcm-input" required>
          <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
        </select>
        <input v-model="compForm.code" class="hcm-input" placeholder="Mã" required />
        <input v-model="compForm.name" class="hcm-input" placeholder="Tên năng lực" required />
        <button type="submit" class="hcm-btn-primary w-full">Lưu</button>
      </form>
    </UiModal>

    <UiModal v-model="showAssessForm" title="Đánh giá năng lực">
      <form class="space-y-3" @submit.prevent="saveAssess">
        <p class="text-sm text-slate-600">{{ assessForm.competency_name }}</p>
        <select v-model.number="assessForm.current_level" class="hcm-input" required>
          <option v-for="n in 5" :key="n" :value="n">Level {{ n }} — {{ meta.levels[n] }}</option>
        </select>
        <button type="submit" class="hcm-btn-primary w-full">Lưu đánh giá</button>
      </form>
    </UiModal>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiModal from '../../components/ui/UiModal.vue';
import { extractItems } from '../../composables/usePagination';
import { useToast } from '../../composables/useToast';

const toast = useToast();
const tabs = [
  { key: 'framework', label: 'Khung năng lực' },
  { key: 'position', label: 'Yêu cầu vị trí' },
  { key: 'matrix', label: 'Ma trận NV' },
];
const tab = ref('matrix');
const meta = ref({ levels: {}, gap_statuses: {} });
const groups = ref([]);
const employees = ref([]);
const positions = ref([]);
const matrix = ref(null);
const selectedEmployeeId = ref(null);
const selectedPositionId = ref(null);
const positionReqForm = ref([]);
const showGroupForm = ref(false);
const showCompForm = ref(false);
const showAssessForm = ref(false);
const groupName = ref('');
const compForm = ref({ competency_group_id: null, code: '', name: '' });
const assessForm = ref({ competency_id: null, competency_name: '', current_level: 3 });

function gapVariant(status) {
  if (status === 'met') return 'success';
  if (status === 'partial') return 'warning';
  if (status === 'gap') return 'danger';
  return 'default';
}

function competencyName(id) {
  return groups.value.flatMap((g) => g.competencies || []).find((c) => c.id === id)?.name || id;
}

async function loadFramework() {
  const { data } = await api.get('/competencies');
  groups.value = data.data;
  if (groups.value[0] && !compForm.value.competency_group_id) {
    compForm.value.competency_group_id = groups.value[0].id;
  }
}

async function loadMatrix() {
  if (!selectedEmployeeId.value) {
    matrix.value = null;
    return;
  }
  const { data } = await api.get(`/employees/${selectedEmployeeId.value}/competency-matrix`);
  matrix.value = data.data;
}

async function loadPositionReqs() {
  if (!selectedPositionId.value) return;
  const { data } = await api.get(`/positions/${selectedPositionId.value}/competency-requirements`);
  const existing = data.data || [];
  const all = groups.value.flatMap((g) => g.competencies || []);
  positionReqForm.value = all.map((c) => {
    const row = existing.find((r) => r.competency_id === c.id);
    return { competency_id: c.id, required_level: row?.required_level ?? 3 };
  });
}

async function savePositionReqs() {
  await api.put(`/positions/${selectedPositionId.value}/competency-requirements`, {
    requirements: positionReqForm.value,
  });
  toast.show('Đã lưu yêu cầu theo vị trí');
}

function quickAssess(row) {
  assessForm.value = {
    competency_id: row.competency_id,
    competency_name: row.competency?.name,
    current_level: row.current_level || 3,
  };
  showAssessForm.value = true;
}

async function saveAssess() {
  await api.post('/competency-assessments', {
    employee_id: selectedEmployeeId.value,
    competency_id: assessForm.value.competency_id,
    current_level: assessForm.value.current_level,
  });
  showAssessForm.value = false;
  toast.show('Đã lưu đánh giá');
  await loadMatrix();
}

async function saveGroup() {
  await api.post('/competency-groups', { name: groupName.value });
  showGroupForm.value = false;
  groupName.value = '';
  await loadFramework();
  toast.show('Đã thêm nhóm');
}

async function saveCompetency() {
  await api.post('/competencies', compForm.value);
  showCompForm.value = false;
  compForm.value = { competency_group_id: groups.value[0]?.id, code: '', name: '' };
  await loadFramework();
  toast.show('Đã thêm năng lực');
}

onMounted(async () => {
  const [m, e, p] = await Promise.all([
    api.get('/competency-meta'),
    api.get('/employees'),
    api.get('/positions'),
  ]);
  meta.value = m.data.data;
  employees.value = extractItems(e.data);
  positions.value = p.data.data;
  if (employees.value[0]) selectedEmployeeId.value = employees.value[0].id;
  if (positions.value[0]) selectedPositionId.value = positions.value[0].id;
  await loadFramework();
  await loadMatrix();
  await loadPositionReqs();
});
</script>
