<template>
  <div>
    <UiPageHeader title="Đánh giá hiệu suất (KPI)" subtitle="Mục tiêu KPI · Tự đánh giá · Điểm tổng hợp" breadcrumb="Performance">
      <template #actions>
        <button type="button" class="hcm-btn-primary" @click="showCycle = true">+ Chu kỳ</button>
      </template>
    </UiPageHeader>

    <div class="space-y-6">
      <div v-for="cycle in cycles" :key="cycle.id" class="hcm-card p-5 space-y-4">
        <div class="flex flex-wrap justify-between gap-3">
          <div>
            <h3 class="font-bold text-lg">{{ cycle.name }}</h3>
            <p class="text-sm text-slate-500">
              {{ cycle.period }} · {{ cycle.kpi_summary?.goal_count || 0 }} KPI ·
              TB KPI {{ cycle.kpi_summary?.avg_kpi_score ?? '—' }}%
            </p>
          </div>
          <UiBadge>{{ meta.cycle_statuses[cycle.status] || cycle.status }}</UiBadge>
        </div>

        <section>
          <div class="flex justify-between items-center mb-2">
            <h4 class="font-semibold text-sm">Mục tiêu KPI</h4>
            <button type="button" class="text-xs text-primary-600" @click="openGoalForm(cycle)">+ KPI</button>
          </div>
          <table v-if="cycle.goals?.length" class="hcm-table w-full text-sm">
            <thead>
              <tr>
                <th>Nhân viên</th>
                <th>KPI</th>
                <th>Mục tiêu</th>
                <th>Thực tế</th>
                <th>Trọng số</th>
                <th>Tiến độ</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="g in cycle.goals" :key="g.id">
                <td>{{ g.employee?.full_name }}</td>
                <td>{{ g.title }}</td>
                <td>{{ g.target_value ?? '—' }}</td>
                <td>
                  <input
                    :value="g.actual_value"
                    type="number"
                    class="hcm-input w-24 text-xs"
                    @change="updateGoalActual(g, $event.target.value)"
                  />
                </td>
                <td>{{ g.weight }}%</td>
                <td>
                  <span class="font-medium">{{ goalProgress(g) }}%</span>
                </td>
                <td><UiBadge :variant="g.status === 'achieved' ? 'success' : 'default'">{{ meta.goal_statuses[g.status] }}</UiBadge></td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-xs text-slate-500">Chưa có KPI trong chu kỳ.</p>
        </section>

        <section>
          <div class="flex justify-between items-center mb-2">
            <h4 class="font-semibold text-sm">Đánh giá tổng hợp</h4>
            <button type="button" class="text-xs text-primary-600" @click="addReview(cycle)">+ Đánh giá NV</button>
          </div>
          <table v-if="cycle.reviews?.length" class="hcm-table w-full text-sm">
            <thead>
              <tr>
                <th>Nhân viên</th>
                <th>KPI (%)</th>
                <th>Tự ĐG</th>
                <th>QL ĐG</th>
                <th>Điểm cuối</th>
                <th>Xếp loại</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in cycle.reviews" :key="r.id">
                <td>{{ r.employee?.full_name }}</td>
                <td>{{ reviewKpi(r) }}</td>
                <td>{{ r.self_score ?? '—' }}</td>
                <td>{{ r.manager_score ?? '—' }}</td>
                <td class="font-semibold">{{ r.final_score ?? '—' }}</td>
                <td>{{ r.rating ? meta.ratings[r.rating] : '—' }}</td>
                <td class="space-x-2">
                  <button type="button" class="text-xs text-primary-600" @click="openReview(r)">Chấm</button>
                  <button type="button" class="text-xs text-emerald-600" @click="finalizeReview(r.id)">Chốt điểm</button>
                </td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>
      <UiEmpty v-if="!cycles.length" title="Chưa có chu kỳ đánh giá" />
    </div>

    <UiModal v-model="showCycle" title="Chu kỳ đánh giá">
      <form class="space-y-3" @submit.prevent="saveCycle">
        <input v-model="cycleForm.name" class="hcm-input" placeholder="Tên chu kỳ" required />
        <input v-model="cycleForm.period" class="hcm-input" placeholder="2026 hoặc 2026-01" required />
        <div class="grid grid-cols-2 gap-3">
          <input v-model="cycleForm.start_date" type="date" class="hcm-input" required />
          <input v-model="cycleForm.end_date" type="date" class="hcm-input" required />
        </div>
        <button type="submit" class="hcm-btn-primary w-full">Tạo</button>
      </form>
    </UiModal>

    <UiModal v-model="showGoal" title="Thêm KPI">
      <form class="space-y-3" @submit.prevent="saveGoal">
        <select v-model="goalForm.employee_id" class="hcm-input" required>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
        </select>
        <input v-model="goalForm.title" class="hcm-input" placeholder="Tên KPI" required />
        <input v-model.number="goalForm.target_value" type="number" class="hcm-input" placeholder="Mục tiêu (số)" />
        <input v-model.number="goalForm.weight" type="number" class="hcm-input" placeholder="Trọng số %" />
        <button type="submit" class="hcm-btn-primary w-full">Lưu KPI</button>
      </form>
    </UiModal>

    <UiModal v-model="showReview" title="Cập nhật đánh giá">
      <form class="space-y-3" @submit.prevent="saveReview">
        <p class="text-xs text-slate-500">Trọng số: KPI {{ meta.weights?.kpi }}% · Hành vi {{ meta.weights?.behavior }}%</p>
        <input v-model.number="reviewForm.self_score" type="number" step="0.1" min="0" max="100" class="hcm-input" placeholder="Điểm tự đánh giá (0-100)" />
        <input v-model.number="reviewForm.manager_score" type="number" step="0.1" min="0" max="100" class="hcm-input" placeholder="Điểm quản lý (0-100)" />
        <textarea v-model="reviewForm.manager_comment" class="hcm-input" rows="2" placeholder="Nhận xét quản lý" />
        <button type="submit" class="hcm-btn-primary w-full">Lưu</button>
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
const meta = ref({ cycle_statuses: {}, goal_statuses: {}, ratings: {}, weights: { kpi: 60, behavior: 40 } });
const cycles = ref([]);
const employees = ref([]);
const showCycle = ref(false);
const showGoal = ref(false);
const showReview = ref(false);
const activeCycleId = ref(null);
const activeReviewId = ref(null);
const cycleForm = ref({
  name: '',
  period: new Date().getFullYear().toString(),
  start_date: `${new Date().getFullYear()}-01-01`,
  end_date: `${new Date().getFullYear()}-12-31`,
});
const goalForm = ref({ performance_cycle_id: null, employee_id: null, title: '', target_value: 100, weight: 100 });
const reviewForm = ref({ self_score: null, manager_score: null, manager_comment: '' });

function goalProgress(g) {
  if (!g.target_value || g.target_value <= 0) return '—';
  const p = Math.min(100, Math.round(((g.actual_value || 0) / g.target_value) * 100));
  return p;
}

function reviewKpi(r) {
  const goals = cycles.value.flatMap((c) => c.goals || []).filter((g) => g.employee_id === r.employee_id);
  if (!goals.length) return '—';
  const totalW = goals.reduce((s, g) => s + (Number(g.weight) || 0), 0) || goals.length;
  let sum = 0;
  goals.forEach((g) => {
    const p = goalProgress(g);
    if (p === '—') return;
    sum += p * ((Number(g.weight) || 1) / totalW);
  });
  return Math.round(sum);
}

async function load() {
  const [m, c, e] = await Promise.all([
    api.get('/performance-meta'),
    api.get('/performance-cycles'),
    api.get('/employees'),
  ]);
  meta.value = m.data.data;
  cycles.value = c.data.data;
  employees.value = extractItems(e.data);
  if (employees.value[0]) goalForm.value.employee_id = employees.value[0].id;
}

async function saveCycle() {
  await api.post('/performance-cycles', cycleForm.value);
  showCycle.value = false;
  toast.show('Đã tạo chu kỳ');
  await load();
}

function openGoalForm(cycle) {
  activeCycleId.value = cycle.id;
  goalForm.value.performance_cycle_id = cycle.id;
  showGoal.value = true;
}

async function saveGoal() {
  await api.post('/goals', goalForm.value);
  showGoal.value = false;
  toast.show('Đã thêm KPI');
  await load();
}

async function updateGoalActual(goal, value) {
  await api.put(`/goals/${goal.id}`, { actual_value: value === '' ? null : Number(value) });
  await load();
}

async function addReview(cycle) {
  if (!employees.value[0]) return;
  await api.post('/employee-reviews', {
    performance_cycle_id: cycle.id,
    employee_id: employees.value[0].id,
  });
  toast.show('Đã thêm bản đánh giá');
  await load();
}

function openReview(r) {
  activeReviewId.value = r.id;
  reviewForm.value = {
    self_score: r.self_score,
    manager_score: r.manager_score,
    manager_comment: r.manager_comment,
  };
  showReview.value = true;
}

async function saveReview() {
  await api.put(`/employee-reviews/${activeReviewId.value}`, reviewForm.value);
  showReview.value = false;
  toast.show('Đã cập nhật đánh giá');
  await load();
}

async function finalizeReview(id) {
  await api.post(`/employee-reviews/${id}/finalize`);
  toast.show('Đã chốt điểm tổng hợp');
  await load();
}

onMounted(load);
</script>
