<template>
  <div>
    <UiPageHeader
      title="BHXH & Kê khai"
      subtitle="D01 báo tăng · D02 điều chỉnh · D05 báo giảm · TK1 · Lịch sử xuất"
      breadcrumb="BHXH"
    />

    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
      <button
        v-for="t in tabs"
        :key="t.id"
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
        :class="tab === t.id ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500'"
        @click="tab = t.id"
      >
        {{ t.label }}
      </button>
    </div>

    <!-- Bộ lọc chung -->
    <div class="hcm-card p-4 mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div>
        <label class="text-sm font-medium">Công ty</label>
        <select v-model="companyId" class="hcm-input mt-1 w-full" @change="onFilterChange">
          <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div v-if="tab !== 'history' && tab !== 'overview'">
        <label class="text-sm font-medium">Từ ngày</label>
        <input v-model="from" type="date" class="hcm-input mt-1 w-full" @change="loadPreview" />
      </div>
      <div v-if="tab !== 'history' && tab !== 'overview' && needsDateRange">
        <label class="text-sm font-medium">Đến ngày</label>
        <input v-model="to" type="date" class="hcm-input mt-1 w-full" @change="loadPreview" />
      </div>
      <div v-if="tab !== 'history' && tab !== 'overview'" class="flex items-end gap-2">
        <button type="button" class="hcm-btn-secondary text-sm" :disabled="previewLoading" @click="loadPreview">
          {{ previewLoading ? 'Đang kiểm tra...' : 'Kiểm tra hồ sơ' }}
        </button>
      </div>
    </div>

    <!-- Tổng quan -->
    <section v-if="tab === 'overview'" class="space-y-4">
      <div v-if="dashboard" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="hcm-card p-4">
          <p class="text-sm text-slate-500">Đang tham gia BHXH</p>
          <p class="text-2xl font-bold text-emerald-700">{{ dashboard.stats.insured_employees }}</p>
          <p class="text-xs text-slate-400">/ {{ dashboard.stats.active_employees }} NV active</p>
        </div>
        <div class="hcm-card p-4">
          <p class="text-sm text-slate-500">Thiếu mã BHXH</p>
          <p class="text-2xl font-bold text-amber-600">{{ dashboard.stats.missing_bhxh_number }}</p>
        </div>
        <div class="hcm-card p-4">
          <p class="text-sm text-slate-500">Thiếu mức lương đóng</p>
          <p class="text-2xl font-bold text-amber-600">{{ dashboard.stats.missing_insurance_salary }}</p>
        </div>
        <div class="hcm-card p-4">
          <p class="text-sm text-slate-500">Chờ báo tăng (tháng)</p>
          <p class="text-2xl font-bold text-primary-700">{{ dashboard.pending.d01_count }}</p>
        </div>
      </div>
      <div v-if="dashboard && !dashboard.company.configured" class="hcm-card p-4 border-amber-200 bg-amber-50 text-sm text-amber-900">
        Chưa cấu hình <strong>mã đơn vị BHXH</strong>. Vào <RouterLink to="/organization" class="underline">Tổ chức → Công ty → Cấu hình</RouterLink>.
      </div>
      <div v-if="dashboard" class="hcm-card p-5">
        <h3 class="font-semibold mb-3">Tỷ lệ đóng tham chiếu</h3>
        <div class="grid gap-2 sm:grid-cols-2 text-sm">
          <p>BHXH NLĐ: {{ pct(dashboard.rates.bhxh.employee) }} · DN: {{ pct(dashboard.rates.bhxh.employer) }}</p>
          <p>BHYT NLĐ: {{ pct(dashboard.rates.bhyt.employee) }} · DN: {{ pct(dashboard.rates.bhyt.employer) }}</p>
          <p>BHTN NLĐ: {{ pct(dashboard.rates.bhtn.employee) }} · DN: {{ pct(dashboard.rates.bhtn.employer) }}</p>
          <p>Trần lương đóng: {{ money(dashboard.salary_limits.max_base) }}</p>
        </div>
      </div>
    </section>

    <!-- Kê khai D01/D02/D05/TK1/Roster -->
    <section v-else-if="tab !== 'history'" class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 class="font-semibold">{{ declarationLabel }}</h3>
          <p v-if="preview" class="text-sm text-slate-500">
            {{ preview.valid_count }}/{{ preview.total }} hợp lệ
            <span v-if="preview.error_count" class="text-amber-600"> · {{ preview.error_count }} lỗi</span>
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="hcm-btn-primary text-sm"
            :disabled="!preview?.can_export || exporting"
            @click="doExport('csv')"
          >
            Xuất CSV & lưu lịch sử
          </button>
          <button
            v-if="currentType !== 'roster'"
            type="button"
            class="hcm-btn-secondary text-sm"
            :disabled="!preview?.can_export || exporting"
            @click="doExport('xml')"
          >
            Xuất XML
          </button>
        </div>
      </div>

      <div v-if="preview?.company_errors?.length" class="hcm-card p-4 bg-red-50 text-sm text-red-800">
        <p class="font-medium">Lỗi cấu hình công ty:</p>
        <ul class="list-disc ml-5 mt-1">
          <li v-for="(e, i) in preview.company_errors" :key="i">{{ e }}</li>
        </ul>
      </div>

      <div class="hcm-card overflow-hidden">
        <table class="hcm-table w-full" v-if="preview?.lines?.length">
          <thead>
            <tr>
              <th>#</th>
              <th>Mã NV / NPT</th>
              <th>Họ tên</th>
              <th>Chi tiết</th>
              <th>Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in preview.lines" :key="row.line_no" :class="!row.is_valid ? 'bg-red-50/50' : ''">
              <td>{{ row.line_no }}</td>
              <td class="font-mono text-xs">{{ row.payload.employee_code || '—' }}</td>
              <td>{{ row.payload.full_name || row.payload.dependent_name || row.payload.employee_name }}</td>
              <td class="text-xs text-slate-600">
                <span v-if="row.payload.insurance_salary">Lương đóng: {{ money(row.payload.insurance_salary) }}</span>
                <span v-if="row.payload.social_insurance_number"> · BHXH: {{ row.payload.social_insurance_number }}</span>
              </td>
              <td>
                <UiBadge :variant="row.is_valid ? 'success' : 'warning'">
                  {{ row.is_valid ? 'Hợp lệ' : row.validation_errors?.join(', ') }}
                </UiBadge>
              </td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Chưa có dữ liệu" description="Bấm Kiểm tra hồ sơ hoặc đổi kỳ" />
      </div>
    </section>

    <!-- Lịch sử -->
    <section v-else class="hcm-card overflow-hidden">
      <table class="hcm-table w-full" v-if="history.length">
        <thead>
          <tr>
            <th>Thời gian</th>
            <th>Loại</th>
            <th>Kỳ</th>
            <th>Số bản ghi</th>
            <th>Định dạng</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="h in history" :key="h.id">
            <td class="text-sm">{{ formatDt(h.created_at) }}</td>
            <td>{{ types[h.declaration_type] || h.declaration_type }}</td>
            <td>{{ h.period || '—' }}</td>
            <td>{{ h.record_count }}</td>
            <td class="uppercase text-xs">{{ h.format }}</td>
            <td>
              <button type="button" class="text-primary-600 text-sm" @click="downloadHistory(h.id)">Tải lại</button>
            </td>
          </tr>
        </tbody>
      </table>
      <UiEmpty v-else title="Chưa có lịch sử kê khai" />
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import { useFormat } from '../../composables/useFormat';
import { useFileDownload } from '../../composables/useFileDownload';
import { useToast } from '../../composables/useToast';

const { money } = useFormat();
const toast = useToast();
const { downloadApiGet } = useFileDownload();

const tabs = [
  { id: 'overview', label: 'Tổng quan' },
  { id: 'd01', label: 'D01 Báo tăng' },
  { id: 'd02', label: 'D02 Điều chỉnh' },
  { id: 'd05', label: 'D05 Báo giảm' },
  { id: 'tk1', label: 'TK1 NPT' },
  { id: 'roster', label: 'DS tham gia' },
  { id: 'history', label: 'Lịch sử' },
];

const tab = ref('overview');
const companies = ref([]);
const companyId = ref(null);
const from = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10));
const to = ref(new Date().toISOString().slice(0, 10));
const dashboard = ref(null);
const preview = ref(null);
const previewLoading = ref(false);
const exporting = ref(false);
const history = ref([]);
const types = ref({});

const currentType = computed(() => (tab.value === 'overview' || tab.value === 'history' ? null : tab.value));
const needsDateRange = computed(() => !['tk1', 'roster'].includes(tab.value));
const declarationLabel = computed(() => types.value[currentType.value] || currentType.value);

function pct(n) {
  return `${(Number(n) * 100).toFixed(1)}%`;
}

function formatDt(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('vi-VN');
}

async function loadDashboard() {
  if (!companyId.value) return;
  const { data } = await api.get('/bhxh/dashboard', { params: { company_id: companyId.value } });
  dashboard.value = data.data;
}

async function loadPreview() {
  if (!currentType.value || !companyId.value) return;
  previewLoading.value = true;
  try {
    const params = { company_id: companyId.value, declaration_type: currentType.value };
    if (needsDateRange.value) {
      params.from = from.value;
      params.to = to.value;
    }
    const { data } = await api.get('/bhxh/preview', { params });
    preview.value = data.data;
  } catch {
    preview.value = null;
    toast.show('Không tải được preview', 'error');
  } finally {
    previewLoading.value = false;
  }
}

async function loadHistory() {
  const { data } = await api.get('/bhxh/declarations', {
    params: { company_id: companyId.value, limit: 50 },
  });
  history.value = data.data;
}

async function doExport(format) {
  exporting.value = true;
  try {
    const body = {
      company_id: companyId.value,
      declaration_type: currentType.value,
      format,
      only_valid: true,
    };
    if (needsDateRange.value) {
      body.from = from.value;
      body.to = to.value;
    }
    const { data } = await api.post('/bhxh/export', body);
    if (data.data?.declaration?.id) {
      await downloadApiGet(`/bhxh/declarations/${data.data.declaration.id}/download`, {}, 'bhxh.dat');
      toast.show('Đã xuất và lưu lịch sử kê khai');
      await loadHistory();
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Xuất thất bại — kiểm tra lỗi hồ sơ';
    toast.show(msg, 'error');
    if (e.response?.data?.data) preview.value = e.response.data.data;
  } finally {
    exporting.value = false;
  }
}

async function downloadHistory(id) {
  await downloadApiGet(`/bhxh/declarations/${id}/download`, {}, 'bhxh-export.dat');
}

function onFilterChange() {
  loadDashboard();
  if (tab.value === 'history') loadHistory();
  else if (tab.value !== 'overview') loadPreview();
}

watch(tab, async (t) => {
  if (t === 'overview') await loadDashboard();
  else if (t === 'history') await loadHistory();
  else await loadPreview();
});

onMounted(async () => {
  const [c, m] = await Promise.all([api.get('/companies'), api.get('/bhxh/meta')]);
  companies.value = c.data.data;
  types.value = m.data.data.declaration_types;
  companyId.value = companies.value[0]?.id;
  await loadDashboard();
});
</script>
