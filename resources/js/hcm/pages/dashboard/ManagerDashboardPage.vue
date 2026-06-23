<template>

  <div>

    <UiPageHeader

      title="Dashboard Quản lý"

      subtitle="Tổng quan nhân sự, hiệu suất và cảnh báo"

      breadcrumb="Dashboard quản lý"

    />



    <div v-if="loading" class="text-center py-12 text-slate-400">Đang tải dữ liệu...</div>

    <template v-else>

      <!-- KPI cards -->

      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">

        <div v-for="card in kpiCards" :key="card.label" class="hcm-card p-4">

          <p class="text-xs text-slate-500 mb-1">{{ card.label }}</p>

          <p class="text-2xl font-bold" :class="card.color || 'text-slate-900'">{{ card.value }}</p>

          <p v-if="card.sub" class="text-xs text-slate-400 mt-1">{{ card.sub }}</p>

        </div>

      </div>



      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Biến động nhân sự -->

        <div class="hcm-card p-5">

          <h3 class="font-semibold text-slate-800 mb-4">📊 Biến động nhân sự (30 ngày)</h3>

          <div class="space-y-3">

            <div class="flex justify-between items-center">

              <span class="text-sm text-slate-600">Tuyển mới</span>

              <span class="font-semibold text-green-600">+{{ movement.hired }}</span>

            </div>

            <div class="flex justify-between items-center">

              <span class="text-sm text-slate-600">Nghỉ việc</span>

              <span class="font-semibold text-red-500">-{{ movement.terminated }}</span>

            </div>

            <div class="flex justify-between items-center">

              <span class="text-sm text-slate-600">Điều chuyển</span>

              <span class="font-semibold text-blue-600">{{ movement.transferred }}</span>

            </div>

            <div class="flex justify-between items-center border-t pt-2">

              <span class="text-sm font-medium text-slate-700">Tỷ lệ nghỉ việc</span>

              <span class="font-bold" :class="movement.turnover > 5 ? 'text-red-500' : 'text-green-600'">

                {{ movement.turnover }}%

              </span>

            </div>

          </div>

        </div>



        <!-- Cảnh báo -->
        <div class="hcm-card p-5">
          <h3 class="font-semibold text-slate-800 mb-4">⚠️ Cảnh báo cần xử lý</h3>
          <div class="grid gap-2 sm:grid-cols-2">
            <RouterLink
              v-for="item in alertLinks"
              :key="item.key"
              v-show="item.count > 0"
              :to="item.to"
              class="flex justify-between items-center p-2.5 rounded-lg transition-colors"
              :class="item.class"
            >
              <span class="text-sm">{{ item.label }}</span>
              <span class="font-bold text-sm">{{ item.count }}</span>
            </RouterLink>
          </div>
          <p v-if="!hasAnyAlert" class="text-sm text-slate-400 text-center py-4">Không có cảnh báo</p>
        </div>



        <!-- Tuyển dụng -->

        <div class="hcm-card p-5">

          <h3 class="font-semibold text-slate-800 mb-4">🎯 Tuyển dụng</h3>

          <div class="space-y-3">

            <div class="flex justify-between items-center">

              <span class="text-sm text-slate-600">Vị trí đang tuyển</span>

              <span class="font-semibold text-slate-900">{{ recruitment.openPositions }}</span>

            </div>

            <div class="flex justify-between items-center">

              <span class="text-sm text-slate-600">Ứng viên đang xử lý</span>

              <span class="font-semibold text-slate-900">{{ recruitment.activeCandidates }}</span>

            </div>

            <div class="flex justify-between items-center">

              <span class="text-sm text-slate-600">Đang phỏng vấn</span>

              <span class="font-semibold text-blue-600">{{ recruitment.interviewing }}</span>

            </div>

            <div class="flex justify-between items-center">

              <span class="text-sm text-slate-600">Đã offer</span>

              <span class="font-semibold text-green-600">{{ recruitment.offered }}</span>

            </div>

            <RouterLink :to="{ name: 'recruitment' }" class="text-xs text-primary-600 hover:underline block text-right mt-2">

              Xem chi tiết →

            </RouterLink>

          </div>

        </div>

      </div>



      <!-- Nhân sự theo phòng ban -->

      <div class="mt-6 hcm-card p-5">

        <div class="flex items-center justify-between mb-4">

          <h3 class="font-semibold text-slate-800">👥 Nhân sự theo phòng ban</h3>

          <RouterLink :to="{ name: 'employees' }" class="text-xs text-primary-600 hover:underline">

            Xem danh sách đầy đủ →

          </RouterLink>

        </div>

        <div v-if="deptStats.length === 0" class="text-sm text-slate-400 text-center py-6">Không có dữ liệu</div>

        <div v-else class="overflow-x-auto">

          <table class="w-full text-sm">

            <thead>

              <tr class="border-b text-slate-500 text-left">

                <th class="pb-2 font-medium">Phòng ban</th>

                <th class="pb-2 font-medium text-right">Tổng</th>

                <th class="pb-2 font-medium text-right">Thử việc</th>

                <th class="pb-2 font-medium text-right">Chính thức</th>

              </tr>

            </thead>

            <tbody class="divide-y divide-slate-100">

              <tr v-for="d in deptStats" :key="d.name" class="hover:bg-slate-50">

                <td class="py-2 text-slate-700">{{ d.name }}</td>

                <td class="py-2 text-right font-medium">{{ d.total }}</td>

                <td class="py-2 text-right text-amber-600">{{ d.probation }}</td>

                <td class="py-2 text-right text-green-600">{{ d.active }}</td>

              </tr>

            </tbody>

          </table>

        </div>

      </div>

    </template>

  </div>

</template>



<script setup>

import { ref, onMounted, computed } from 'vue';

import { RouterLink } from 'vue-router';

import api from '../../api/client';

import UiPageHeader from '../../components/ui/UiPageHeader.vue';



const loading = ref(true);

const summary = ref(null);



onMounted(async () => {

  try {

    const { data } = await api.get('/reports/manager-dashboard');

    summary.value = data.data;

  } finally {

    loading.value = false;

  }

});



const kpiCards = computed(() => {

  const k = summary.value?.kpi || {};

  return [

    { label: 'Tổng nhân sự', value: k.total ?? 0, color: 'text-slate-900' },

    { label: 'Đang làm việc', value: k.active ?? 0, color: 'text-green-600', sub: 'Chính thức' },

    { label: 'Thử việc', value: k.probation ?? 0, color: 'text-amber-600', sub: 'Đang thử việc' },

    { label: 'Đã nghỉ việc', value: k.inactive ?? 0, color: 'text-red-500', sub: 'Không còn active' },

  ];

});



const movement = computed(() => summary.value?.movement || { hired: 0, terminated: 0, transferred: 0, turnover: 0 });

const alerts = computed(() => summary.value?.alerts || {});

const alertLinks = computed(() => {
  const a = alerts.value;
  return [
    { key: 'expired', label: 'HĐ đã hết hạn', count: a.contractsExpired || 0, to: { name: 'contracts' }, class: 'bg-red-50 hover:bg-red-100 text-red-800' },
    { key: 'expiring', label: 'HĐ sắp hết hạn', count: a.contractsExpiring || 0, to: { name: 'contracts' }, class: 'bg-amber-50 hover:bg-amber-100 text-amber-800' },
    { key: 'missing', label: 'NV chưa có HĐ', count: a.contractsMissing || 0, to: { name: 'contracts' }, class: 'bg-red-50 hover:bg-red-100 text-red-800' },
    { key: 'noFile', label: 'Thiếu file scan HĐ', count: a.contractsNoFile || 0, to: { name: 'contracts' }, class: 'bg-amber-50 hover:bg-amber-100 text-amber-800' },
    { key: 'probation', label: 'Sắp hết thử việc', count: a.probationEnding || 0, to: { name: 'employees' }, class: 'bg-blue-50 hover:bg-blue-100 text-blue-800' },
    { key: 'ot', label: 'OT vượt / gần trần', count: a.otMonthlyWarning || 0, to: { name: 'leave', query: { tab: 'ot' } }, class: 'bg-orange-50 hover:bg-orange-100 text-orange-800' },
    { key: 'otYear', label: 'OT năm (200h+)', count: a.otYearlyWarning || 0, to: { name: 'attendance' }, class: 'bg-orange-50 hover:bg-orange-100 text-orange-800' },
    { key: 'schedule', label: 'Tuân thủ ca làm', count: a.workSchedule || 0, to: { name: 'work-schedules' }, class: 'bg-slate-50 hover:bg-slate-100 text-slate-800' },
    { key: 'approvals', label: 'Chờ duyệt', count: a.pendingApprovals || 0, to: { name: 'approvals' }, class: 'bg-red-50 hover:bg-red-100 text-red-800' },
  ];
});

const hasAnyAlert = computed(() => alertLinks.value.some((i) => i.count > 0));

const recruitment = computed(() => summary.value?.recruitment || { openPositions: 0, activeCandidates: 0, interviewing: 0, offered: 0 });

const deptStats = computed(() => summary.value?.departments || []);

</script>


