<template>
  <div>
    <UiPageHeader
      title="Trung tâm báo cáo nhân sự"
      subtitle="Biến động · Cơ cấu · Tuyển dụng · Nghỉ việc · Chấm công · Lương · Đào tạo · KPI · Khen thưởng · Tổng hợp"
      breadcrumb="Reports"
    >
      <template #actions>
        <button type="button" class="hcm-btn-secondary text-xs" @click="exportReport">⬇️ Xuất Excel</button>
      </template>
    </UiPageHeader>

    <div class="hcm-card mb-4 p-4 flex flex-wrap items-end gap-4">
      <div>
        <label class="text-sm font-medium">Kỳ báo cáo</label>
        <input v-model="filters.period" type="month" class="hcm-input mt-1" @change="reload" />
      </div>
      <div>
        <label class="text-sm font-medium">Phòng ban</label>
        <select v-model="filters.department_id" class="hcm-input mt-1 min-w-[180px]" @change="reload">
          <option :value="null">Tất cả phòng ban</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>
      <div v-if="tab === 'performance'">
        <label class="text-sm font-medium">Chu kỳ đánh giá</label>
        <select v-model="filters.performance_cycle_id" class="hcm-input mt-1 min-w-[200px]" @change="reload">
          <option :value="null">Mới nhất</option>
          <option v-for="c in cycles" :key="c.id" :value="c.id">{{ c.name }} ({{ c.period }})</option>
        </select>
      </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="px-3 py-2 text-sm font-medium border-b-2 -mb-px transition-all whitespace-nowrap"
        :class="tab === t.key ? 'border-primary-600 text-primary-700 bg-primary-50/50' : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="switchTab(t.key)"
      >
        {{ t.icon }} {{ t.label }}
      </button>
    </div>

    <div v-if="loading" class="text-center py-16 text-slate-400">Đang tải báo cáo...</div>
    <template v-else-if="report">

      <!-- 1. Biến động nhân sự -->
      <div v-if="tab === 'movement'" class="space-y-6">
        <SummaryGrid :items="[
          { label: 'Đầu kỳ', value: report.summary.headcount_start, color: 'slate' },
          { label: 'Cuối kỳ', value: report.summary.headcount_end, color: 'primary' },
          { label: 'Tuyển mới thực sự', value: report.summary.new_hires, color: 'green' },
          { label: 'Hết thử việc', value: report.summary.probation_ended_in_period ?? 0, color: 'blue' },
          { label: 'Chuyển chính thức', value: report.summary.converted_to_official_in_period ?? 0, color: 'emerald' },
          { label: 'Không đạt TV', value: report.summary.failed_probation_in_period ?? 0, color: 'amber' },
          { label: 'Tỷ lệ đạt TV', value: (report.summary.conversion_rate ?? '—') + (report.summary.conversion_rate != null ? '%' : ''), color: 'emerald' },
          { label: 'Nghỉ việc', value: report.summary.terminations, color: 'rose' },
          { label: 'Biến động ròng', value: signed(report.summary.net_headcount_change ?? 0), color: 'slate' },
        ]" />

        <div v-if="report.summary.narrative" class="hcm-card p-4 bg-blue-50 border border-blue-100 text-sm text-blue-900 leading-relaxed">
          {{ report.summary.narrative }}
        </div>

        <div class="grid md:grid-cols-2 gap-4">
          <div class="hcm-card p-5">
            <h3 class="font-semibold mb-3">Cơ cấu đầu kỳ</h3>
            <dl class="space-y-2 text-sm">
              <div class="flex justify-between"><dt class="text-slate-500">Tổng</dt><dd class="font-semibold">{{ report.summary.headcount_start_breakdown?.total ?? report.summary.headcount_start }}</dd></div>
              <div class="flex justify-between"><dt class="text-blue-700">Thử việc</dt><dd>{{ report.summary.headcount_start_breakdown?.probation ?? '—' }}</dd></div>
              <div class="flex justify-between"><dt class="text-green-700">Chính thức</dt><dd>{{ report.summary.headcount_start_breakdown?.official ?? '—' }}</dd></div>
            </dl>
          </div>
          <div class="hcm-card p-5">
            <h3 class="font-semibold mb-3">Cơ cấu cuối kỳ</h3>
            <dl class="space-y-2 text-sm">
              <div class="flex justify-between"><dt class="text-slate-500">Tổng</dt><dd class="font-semibold">{{ report.summary.headcount_end_breakdown?.total ?? report.summary.headcount_end }}</dd></div>
              <div class="flex justify-between"><dt class="text-blue-700">Thử việc</dt><dd>{{ report.summary.headcount_end_breakdown?.probation ?? '—' }}</dd></div>
              <div class="flex justify-between"><dt class="text-green-700">Chính thức</dt><dd>{{ report.summary.headcount_end_breakdown?.official ?? '—' }}</dd></div>
            </dl>
          </div>
        </div>

        <p v-if="report.summary.new_hires_note" class="text-xs text-slate-500">{{ report.summary.new_hires_note }}</p>

        <div class="grid lg:grid-cols-2 gap-4">
          <ListCard title="Tuyển mới trong kỳ (hire_date)" :rows="report.recent_hires" empty="Không có tuyển mới">
            <template #row="{ row }">
              <td class="font-medium">{{ row.full_name }}</td>
              <td class="text-xs text-slate-500">{{ row.department }}</td>
              <td class="text-xs">{{ row.hire_date }}</td>
            </template>
          </ListCard>
          <ListCard title="Chuyển chính thức trong kỳ" :rows="report.probation_conversions" empty="Không có chuyển CT">
            <template #row="{ row }">
              <td class="font-medium">{{ row.full_name }}</td>
              <td class="text-xs text-slate-500">{{ row.department }}</td>
              <td class="text-xs">TV {{ row.probation_end_date }} → CT {{ row.official_start_date }}</td>
            </template>
          </ListCard>
          <ListCard title="Không đạt / nghỉ sau thử việc" :rows="report.failed_probations" empty="Không có">
            <template #row="{ row }">
              <td class="font-medium">{{ row.full_name }}</td>
              <td class="text-xs text-slate-500">{{ row.department }}</td>
              <td class="text-xs">{{ row.termination_date || row.probation_end_date }}</td>
            </template>
          </ListCard>
          <ListCard title="Nghỉ việc trong kỳ" :rows="report.recent_terminations" empty="Không có nghỉ việc">
            <template #row="{ row }">
              <td class="font-medium">{{ row.full_name }}</td>
              <td class="text-xs text-slate-500">{{ row.department }}</td>
              <td class="text-xs">{{ row.termination_date }}</td>
            </template>
          </ListCard>
        </div>
      </div>

      <!-- 2. Cơ cấu nhân sự -->
      <div v-else-if="tab === 'structure'" class="space-y-6">
        <SummaryGrid :items="[{ label: 'Tổng nhân sự', value: report.summary.total, color: 'primary' }]" />
        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
          <DistributionCard title="Theo phòng ban" :items="report.by_department" />
          <DistributionCard title="Theo chức vụ" :items="report.by_position" />
          <DistributionCard title="Theo giới tính" :items="report.by_gender" />
          <DistributionCard title="Theo độ tuổi" :items="report.by_age" />
          <DistributionCard title="Theo trình độ" :items="report.by_education" />
          <DistributionCard title="Theo thâm niên" :items="report.by_tenure" />
          <DistributionCard title="Theo loại HĐ" :items="report.by_contract_type" />
        </div>
      </div>

      <!-- 3. Tuyển dụng -->
      <div v-else-if="tab === 'recruitment'" class="space-y-6">
        <SummaryGrid :items="[
          { label: 'Vị trí cần tuyển', value: report.summary.open_positions, color: 'primary' },
          { label: 'Ứng viên mới', value: report.summary.candidates_applied, color: 'blue' },
          { label: 'Phỏng vấn', value: report.summary.interviews, color: 'amber' },
          { label: 'Trúng tuyển', value: report.summary.hired, color: 'green' },
          { label: 'Tỷ lệ thành công', value: (report.summary.success_rate ?? 0) + '%', color: 'green' },
          { label: 'TB ngày tuyển', value: report.summary.avg_days_to_hire ?? '—', color: 'slate' },
        ]" />
        <p v-if="report.summary.note_cost" class="text-xs text-slate-500">{{ report.summary.note_cost }}</p>
        <DistributionCard title="Pipeline ứng viên theo giai đoạn" :items="stageItems" />
      </div>

      <!-- 4. Nghỉ việc -->
      <div v-else-if="tab === 'turnover'" class="space-y-6">
        <SummaryGrid :items="[
          { label: 'Tổng nghỉ việc', value: report.summary.total_terminations, color: 'rose' },
          { label: 'Tự nguyện', value: report.summary.voluntary, color: 'amber' },
          { label: 'Không tự nguyện', value: report.summary.involuntary, color: 'rose' },
          { label: 'Tỷ lệ nghỉ việc', value: report.summary.turnover_rate + '%', color: 'rose' },
        ]" />
        <div class="grid lg:grid-cols-2 gap-4">
          <DistributionCard title="Lý do nghỉ việc" :items="reasonItems" label-key="reason" />
          <div class="hcm-card p-5">
            <h3 class="font-semibold mb-3">Phòng ban tỷ lệ nghỉ cao</h3>
            <table class="hcm-table w-full text-sm">
              <thead><tr><th>Phòng ban</th><th class="text-right">Nghỉ</th><th class="text-right">Tỷ lệ</th></tr></thead>
              <tbody>
                <tr v-for="d in report.by_department" :key="d.department">
                  <td>{{ d.department }}</td>
                  <td class="text-right">{{ d.terminations }}</td>
                  <td class="text-right font-medium text-rose-600">{{ d.rate }}%</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <RecommendBox title="Đề xuất giữ chân nhân sự" :items="report.recommendations" />
      </div>

      <!-- 5. Chấm công & nghỉ phép -->
      <div v-else-if="tab === 'attendance'" class="space-y-6">
        <SummaryGrid :items="[
          { label: 'Nhân viên', value: report.summary.employee_count, color: 'slate' },
          { label: 'TB ngày công', value: report.summary.avg_work_days, color: 'green' },
          { label: 'Giờ OT', value: report.summary.total_ot_hours, color: 'blue' },
          { label: 'Nghỉ phép', value: report.summary.total_leave_days, color: 'amber' },
          { label: 'Nghỉ không lương', value: report.summary.unpaid_leave_days, color: 'orange' },
          { label: 'Vắng không lý do', value: report.summary.total_absent_days, color: 'rose' },
          { label: 'Đi muộn (phút)', value: report.summary.total_late_minutes, color: 'rose' },
        ]" />
        <div class="hcm-card overflow-hidden">
          <table class="hcm-table w-full text-sm">
            <thead>
              <tr>
                <th>Nhân viên</th><th>Phòng ban</th>
                <th class="text-right">Công</th><th class="text-right">Phép</th>
                <th class="text-right">KL</th><th class="text-right">OT</th>
                <th class="text-right">Muộn</th><th class="text-right">Vắng</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in report.rows" :key="r.employee_id" class="hover:bg-slate-50">
                <td class="font-medium">{{ r.full_name }}</td>
                <td class="text-xs text-slate-500">{{ r.department || '—' }}</td>
                <td class="text-right">{{ r.work_days }}</td>
                <td class="text-right">{{ r.leave_days }}</td>
                <td class="text-right">{{ r.unpaid_leave_days }}</td>
                <td class="text-right">{{ r.ot_hours }}</td>
                <td class="text-right">{{ r.late_minutes }}</td>
                <td class="text-right text-rose-600">{{ r.absent_days }}</td>
              </tr>
            </tbody>
          </table>
          <UiEmpty v-if="!report.rows?.length" title="Chưa tổng hợp công kỳ này" subtitle="Vào Chấm công → Tổng hợp công tháng" />
        </div>
      </div>

      <!-- 6. Lương & phúc lợi -->
      <div v-else-if="tab === 'payroll'" class="space-y-6">
        <SummaryGrid :items="[
          { label: 'Quỹ lương Gross', value: formatMoney(report.summary.total_gross), color: 'green' },
          { label: 'Thực lĩnh Net', value: formatMoney(report.summary.total_net), color: 'primary' },
          { label: 'Thuế TNCN', value: formatMoney(report.summary.total_pit), color: 'rose' },
          { label: 'BHXH NSDLĐ', value: formatMoney(report.summary.total_bhxh_employer), color: 'amber' },
          { label: 'Phúc lợi (ước tính)', value: formatMoney(report.summary.benefit_cost_estimate), color: 'blue' },
          { label: 'Chi phí NV bình quân', value: formatMoney(report.summary.avg_cost_per_employee), color: 'slate' },
        ]" />
        <DistributionCard title="Lương theo phòng ban" :items="payrollDeptItems" label-key="department" />
        <div class="hcm-card overflow-x-auto">
          <table class="hcm-table w-full text-sm min-w-[700px]">
            <thead>
              <tr>
                <th>Nhân viên</th><th>Phòng ban</th>
                <th class="text-right">Gross</th><th class="text-right">Net</th>
                <th class="text-right">Thuế</th><th class="text-right">BHXH NLĐ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in report.rows" :key="i" class="hover:bg-slate-50">
                <td>{{ r.full_name }}</td>
                <td class="text-xs text-slate-500">{{ r.department || '—' }}</td>
                <td class="text-right">{{ formatMoney(r.gross_salary) }}</td>
                <td class="text-right font-medium text-green-700">{{ formatMoney(r.net_salary) }}</td>
                <td class="text-right">{{ formatMoney(r.pit_amount) }}</td>
                <td class="text-right">{{ formatMoney(r.bhxh_employee) }}</td>
              </tr>
            </tbody>
          </table>
          <UiEmpty v-if="!report.rows?.length" title="Chưa có bảng lương kỳ này" />
        </div>
      </div>

      <!-- 7. Đào tạo -->
      <div v-else-if="tab === 'training'" class="space-y-6">
        <SummaryGrid :items="[
          { label: 'Số khóa / lớp', value: report.summary.class_count, color: 'primary' },
          { label: 'NV tham gia', value: report.summary.participants, color: 'blue' },
          { label: 'Lượt ghi danh', value: report.summary.enrollment_count, color: 'slate' },
          { label: 'Hoàn thành', value: report.summary.completed_count, color: 'green' },
          { label: 'Điểm TB', value: report.summary.avg_score ?? '—', color: 'amber' },
        ]" />
        <p v-if="report.summary.note_cost" class="text-xs text-slate-500">{{ report.summary.note_cost }}</p>
        <div class="hcm-card overflow-hidden">
          <table class="hcm-table w-full text-sm">
            <thead><tr><th>Khóa học</th><th class="text-right">Tham gia</th><th class="text-right">Hoàn thành</th><th class="text-right">Điểm TB</th></tr></thead>
            <tbody>
              <tr v-for="c in report.by_course" :key="c.course">
                <td>{{ c.course }}</td>
                <td class="text-right">{{ c.participants }}</td>
                <td class="text-right">{{ c.completed }}</td>
                <td class="text-right">{{ c.avg_score }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <RecommendBox title="Nhu cầu đào tạo tiếp theo" :items="report.next_needs" />
      </div>

      <!-- 8. Đánh giá hiệu quả -->
      <div v-else-if="tab === 'performance'" class="space-y-6">
        <p v-if="report.cycle" class="text-sm text-slate-600">
          Chu kỳ: <strong>{{ report.cycle.name }}</strong> ({{ report.cycle.period }})
        </p>
        <SummaryGrid :items="[
          { label: 'Nhân viên', value: report.summary.employee_count, color: 'slate' },
          { label: 'TB KPI', value: fmtScore(report.summary.avg_kpi_score) + '%', color: 'primary' },
          { label: 'TB điểm cuối', value: fmtScore(report.summary.avg_final_score), color: 'blue' },
          { label: 'Xuất sắc (A/B)', value: report.summary.top_performers, color: 'green' },
          { label: 'Cần cải thiện', value: report.summary.need_improvement, color: 'rose' },
        ]" />
        <DistributionCard title="Xếp loại nhân viên" :items="ratingItems" label-key="rating" />
        <div class="hcm-card overflow-hidden">
          <table class="hcm-table w-full text-sm">
            <thead><tr><th>Nhân viên</th><th>Phòng ban</th><th>KPI</th><th>Điểm cuối</th><th>Xếp loại</th></tr></thead>
            <tbody>
              <tr v-for="r in report.employees" :key="r.employee_id">
                <td>
                  <RouterLink :to="{ name: 'employee-detail', params: { id: r.employee_id } }" class="text-primary-600 font-medium">{{ r.full_name }}</RouterLink>
                </td>
                <td>{{ r.department || '—' }}</td>
                <td>{{ r.kpi_score ?? '—' }}%</td>
                <td>{{ r.final_score ?? '—' }}</td>
                <td><span class="font-bold" :class="ratingClass(r.rating)">{{ r.rating || '—' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 9. Khen thưởng & kỷ luật -->
      <div v-else-if="tab === 'awards'" class="space-y-6">
        <SummaryGrid :items="[
          { label: 'Khen thưởng', value: report.summary.awards_count, color: 'green' },
          { label: 'Kỷ luật', value: report.summary.discipline_count, color: 'rose' },
          { label: 'Tổng tiền thưởng', value: formatMoney(report.summary.award_amount_total), color: 'green' },
        ]" />
        <div class="grid lg:grid-cols-2 gap-4">
          <DistributionCard title="Lý do khen thưởng" :items="report.award_reasons" label-key="reason" />
          <DistributionCard title="Lý do / hình thức kỷ luật" :items="report.discipline_reasons" label-key="reason" />
        </div>
        <RecommendBox title="Biện pháp khắc phục" :items="report.remedial_actions" />
      </div>

      <!-- 10. Tổng hợp lãnh đạo -->
      <div v-else-if="tab === 'executive'" class="space-y-6">
        <div class="hcm-card p-6 bg-gradient-to-br from-primary-50 to-white border border-primary-100">
          <p class="text-sm text-slate-500">Báo cáo tổng hợp · Kỳ {{ report.period }}</p>
          <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
            <div><p class="text-xs text-slate-500">Nhân sự hiện tại</p><p class="text-2xl font-bold">{{ report.headline.total_headcount }}</p></div>
            <div><p class="text-xs text-slate-500">Đầu kỳ → Cuối kỳ</p><p class="text-2xl font-bold">{{ report.headline.headcount_start }} → {{ report.headline.headcount_end }}</p></div>
            <div><p class="text-xs text-slate-500">Tuyển / Nghỉ</p><p class="text-2xl font-bold text-green-700">+{{ report.headline.new_hires }} <span class="text-rose-600">-{{ report.headline.terminations }}</span></p></div>
            <div><p class="text-xs text-slate-500">Tỷ lệ nghỉ việc</p><p class="text-2xl font-bold text-rose-600">{{ report.headline.turnover_rate }}%</p></div>
          </div>
        </div>

        <div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4">
          <SectionMini title="Tuyển dụng" :data="report.sections.recruitment" :keys="['open_positions','candidates_applied','hired','success_rate']" />
          <SectionMini title="Chấm công" :data="report.sections.attendance" :keys="['avg_work_days','total_ot_hours','total_absent_days']" />
          <SectionMini title="Lương" :data="report.sections.payroll" :keys="['total_gross','total_net','headcount']" money />
          <SectionMini title="Đào tạo" :data="report.sections.training" :keys="['class_count','participants','completed_count']" />
        </div>

        <RecommendBox title="Nhận xét & đề xuất" :items="[...report.comments, ...report.recommendations]" />
      </div>

    </template>
    <UiEmpty v-else title="Không tải được báo cáo" />
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import { useFormat } from '../../composables/useFormat';
import { useToast } from '../../composables/useToast';

const { formatMoney } = useFormat();
const toast = useToast();

const tabs = [
  { key: 'movement', label: 'Biến động NS', icon: '🔄', endpoint: '/reports/workforce-movement' },
  { key: 'structure', label: 'Cơ cấu NS', icon: '🌳', endpoint: '/reports/workforce-structure' },
  { key: 'recruitment', label: 'Tuyển dụng', icon: '📣', endpoint: '/reports/recruitment' },
  { key: 'turnover', label: 'Nghỉ việc', icon: '🚪', endpoint: '/reports/turnover' },
  { key: 'attendance', label: 'Chấm công', icon: '🕐', endpoint: '/reports/attendance-leave' },
  { key: 'payroll', label: 'Lương & PL', icon: '💰', endpoint: '/reports/payroll-benefits' },
  { key: 'training', label: 'Đào tạo', icon: '🎓', endpoint: '/reports/training' },
  { key: 'performance', label: 'Hiệu suất', icon: '🎯', endpoint: '/reports/performance-kpi' },
  { key: 'awards', label: 'Khen/Kỷ luật', icon: '🏅', endpoint: '/reports/awards-discipline' },
  { key: 'executive', label: 'Tổng hợp', icon: '📊', endpoint: '/reports/executive-summary' },
];

const tab = ref('executive');
const loading = ref(false);
const report = ref(null);
const departments = ref([]);
const cycles = ref([]);

const now = new Date();
const filters = ref({
  department_id: null,
  performance_cycle_id: null,
  period: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`,
});

const stageItems = computed(() =>
  Object.entries(report.value?.by_stage || {}).map(([label, count]) => ({ label, count, percent: 0 }))
);

const reasonItems = computed(() =>
  (report.value?.by_reason || []).map((r) => ({ label: r.reason, count: r.count, percent: 0 }))
);

const payrollDeptItems = computed(() =>
  (report.value?.by_department || []).map((d) => ({
    label: d.department,
    count: d.headcount,
    percent: report.value?.summary?.total_gross
      ? Math.round((d.gross / report.value.summary.total_gross) * 100)
      : 0,
    extra: formatMoney(d.gross),
  }))
);

const ratingItems = computed(() =>
  (report.value?.rating_distribution || []).map((r) => ({ label: r.rating, count: r.count, percent: 0 }))
);

function fmtScore(v) {
  if (v == null) return '—';
  return Number(v).toFixed(1);
}

function signed(v) {
  const n = Number(v) || 0;
  return n > 0 ? `+${n}` : String(n);
}

function ratingClass(r) {
  return { A: 'text-green-600', B: 'text-blue-600', C: 'text-amber-600', D: 'text-red-600' }[r] || '';
}

function queryParams() {
  const p = { period: filters.value.period };
  if (filters.value.department_id) p.department_id = filters.value.department_id;
  if (tab.value === 'performance' && filters.value.performance_cycle_id) {
    p.performance_cycle_id = filters.value.performance_cycle_id;
  }
  return p;
}

async function reload() {
  const current = tabs.find((t) => t.key === tab.value);
  if (!current) return;
  loading.value = true;
  report.value = null;
  try {
    const { data } = await api.get(current.endpoint, { params: queryParams() });
    report.value = data.data;
  } catch {
    toast.show('Lỗi tải báo cáo', 'error');
  } finally {
    loading.value = false;
  }
}

function switchTab(key) {
  tab.value = key;
  reload();
}

function exportReport() {
  toast.show('Xuất Excel theo tab đang chọn — sẽ bổ sung ở phase tiếp theo', 'info');
}

onMounted(async () => {
  const [d, c] = await Promise.all([
    api.get('/departments'),
    api.get('/performance-cycles').catch(() => ({ data: { data: [] } })),
  ]);
  departments.value = d.data.data;
  cycles.value = c.data.data || [];
  await reload();
});

// ── Inline presentational components ──────────────────────────────────────

const SummaryGrid = defineComponent({
  name: 'SummaryGrid',
  props: { items: { type: Array, required: true } },
  setup(props) {
    const colors = {
      primary: 'border-primary-500 text-primary-700',
      green: 'border-green-500 text-green-700',
      rose: 'border-rose-500 text-rose-700',
      blue: 'border-blue-500 text-blue-700',
      amber: 'border-amber-500 text-amber-700',
      slate: 'border-slate-400 text-slate-800',
      orange: 'border-orange-500 text-orange-700',
    };
    return () => h('div', { class: 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-3' },
      props.items.map((item) => h('div', { class: `hcm-card p-4 border-l-4 ${colors[item.color] || colors.slate}` }, [
        h('p', { class: 'text-xs text-slate-500' }, item.label),
        h('p', { class: 'text-xl font-bold mt-1' }, item.value),
      ]))
    );
  },
});

const DistributionCard = defineComponent({
  name: 'DistributionCard',
  props: {
    title: String,
    items: { type: Array, default: () => [] },
    labelKey: { type: String, default: 'label' },
  },
  setup(props) {
    return () => h('div', { class: 'hcm-card p-5' }, [
      h('h3', { class: 'font-semibold text-slate-800 mb-4' }, props.title),
      ...(props.items.length ? props.items.map((item) => {
        const label = item[props.labelKey] || item.label;
        return h('div', { class: 'mb-3' }, [
          h('div', { class: 'flex justify-between text-sm mb-1' }, [
            h('span', { class: 'text-slate-700' }, label),
            h('span', { class: 'font-bold' }, `${item.count}${item.extra ? ' · ' + item.extra : ''}${item.percent ? ' (' + item.percent + '%)' : ''}`),
          ]),
          h('div', { class: 'h-2 rounded-full bg-slate-100' }, [
            h('div', {
              class: 'h-full rounded-full bg-primary-500',
              style: { width: `${Math.min(item.percent || (item.count * 10), 100)}%` },
            }),
          ]),
        ]);
      }) : [h('p', { class: 'text-sm text-slate-400' }, 'Chưa có dữ liệu')]),
    ]);
  },
});

const ListCard = defineComponent({
  name: 'ListCard',
  props: { title: String, rows: Array, empty: String },
  setup(props, { slots }) {
    return () => h('div', { class: 'hcm-card overflow-hidden' }, [
      h('div', { class: 'border-b px-5 py-3 font-semibold' }, props.title),
      props.rows?.length
        ? h('table', { class: 'hcm-table w-full text-sm' }, [
            h('tbody', {}, props.rows.map((row, i) =>
              h('tr', { key: i, class: 'hover:bg-slate-50' }, slots.row ? slots.row({ row }) : [])
            )),
          ])
        : h('p', { class: 'p-6 text-center text-slate-400 text-sm' }, props.empty),
    ]);
  },
});

const RecommendBox = defineComponent({
  name: 'RecommendBox',
  props: { title: String, items: { type: Array, default: () => [] } },
  setup(props) {
    return () => h('div', { class: 'hcm-card p-5 bg-slate-50 border border-slate-200' }, [
      h('h3', { class: 'font-semibold text-slate-800 mb-3' }, props.title),
      h('ul', { class: 'list-disc pl-5 space-y-1 text-sm text-slate-600' },
        (props.items || []).map((t, i) => h('li', { key: i }, t))
      ),
    ]);
  },
});

const SectionMini = defineComponent({
  name: 'SectionMini',
  props: { title: String, data: Object, keys: Array, money: Boolean },
  setup(props) {
    const labels = {
      open_positions: 'Vị trí mở',
      candidates_applied: 'Ứng viên',
      hired: 'Đã tuyển',
      success_rate: 'Tỷ lệ %',
      avg_work_days: 'TB ngày công',
      total_ot_hours: 'Giờ OT',
      total_absent_days: 'Vắng',
      total_gross: 'Gross',
      total_net: 'Net',
      headcount: 'Số NV',
      class_count: 'Lớp học',
      participants: 'Tham gia',
      completed_count: 'Hoàn thành',
    };
    return () => h('div', { class: 'hcm-card p-4' }, [
      h('h4', { class: 'font-semibold text-sm text-slate-700 mb-3' }, props.title),
      h('dl', { class: 'space-y-2 text-sm' },
        props.keys.map((k) => {
          let val = props.data?.[k];
          if (val == null) val = '—';
          else if (props.money && typeof val === 'number') val = formatMoney(val);
          else if (k === 'success_rate') val = val + '%';
          return h('div', { class: 'flex justify-between' }, [
            h('dt', { class: 'text-slate-500' }, labels[k] || k),
            h('dd', { class: 'font-medium' }, val),
          ]);
        })
      ),
    ]);
  },
});
</script>
