<template>
  <div>
    <UiPageHeader
      title="Nhật ký kiểm toán"
      subtitle="Ghi lại toàn bộ thao tác nhạy cảm trong hệ thống"
      breadcrumb="Audit Log"
    />

    <!-- Filters -->
    <div class="hcm-card p-4 mb-4">
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <input
          v-model="filters.q"
          class="hcm-input col-span-2 sm:col-span-1 lg:col-span-2"
          placeholder="Tìm theo mô tả, người thực hiện..."
          @keyup.enter="load"
        />
        <select v-model="filters.category" class="hcm-input">
          <option value="">Tất cả danh mục</option>
          <option value="security">🔐 Bảo mật</option>
          <option value="contract">📄 Hợp đồng</option>
          <option value="payroll">💵 Lương</option>
          <option value="export">📤 Xuất dữ liệu</option>
          <option value="workflow">✅ Phê duyệt</option>
          <option value="offboarding">🚪 Nghỉ việc</option>
          <option value="general">🔧 Chung</option>
        </select>
        <input v-model="filters.from" class="hcm-input" type="date" title="Từ ngày" />
        <input v-model="filters.to"   class="hcm-input" type="date" title="Đến ngày" />
        <button type="button" class="hcm-btn-primary" @click="load">Lọc</button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <span class="text-slate-400 text-sm">Đang tải...</span>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="hcm-card p-6 text-center text-red-500">
      {{ error }}
    </div>

    <!-- Table -->
    <div v-else-if="rows.length" class="hcm-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider">
            <tr>
              <th class="px-4 py-3 text-left">Thời gian</th>
              <th class="px-4 py-3 text-left">Người thực hiện</th>
              <th class="px-4 py-3 text-left">Danh mục</th>
              <th class="px-4 py-3 text-left">Hành động</th>
              <th class="px-4 py-3 text-left">Đối tượng</th>
              <th class="px-4 py-3 text-left">Mô tả</th>
              <th class="px-4 py-3 text-left">IP</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <template v-for="row in rows" :key="row.id">
              <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-4 py-3 whitespace-nowrap text-slate-500 text-xs">
                  {{ fmt.datetime(row.created_at) }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap font-medium">
                  {{ row.actor_name || '—' }}
                </td>
                <td class="px-4 py-3">
                  <UiBadge :variant="categoryVariant(row.action_category)">
                    {{ categoryLabel(row.action_category) }}
                  </UiBadge>
                </td>
                <td class="px-4 py-3">
                  <span :class="actionClass(row.action)" class="font-mono text-xs px-1.5 py-0.5 rounded">
                    {{ row.action }}
                  </span>
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs">
                  {{ entityShortName(row.entity_type) }}
                  <span v-if="row.entity_id" class="text-slate-400">#{{ row.entity_id }}</span>
                </td>
                <td class="px-4 py-3 text-slate-600 max-w-xs truncate" :title="row.description">
                  {{ row.description || '—' }}
                </td>
                <td class="px-4 py-3 text-slate-400 text-xs font-mono">{{ row.ip_address }}</td>
                <td class="px-4 py-3">
                  <button
                    v-if="row.old_value || row.new_value"
                    type="button"
                    class="text-xs text-primary-600 hover:underline"
                    @click="expanded === row.id ? expanded = null : expanded = row.id"
                  >
                    {{ expanded === row.id ? 'Ẩn' : 'Chi tiết' }}
                  </button>
                </td>
              </tr>
              <!-- Expanded diff -->
              <tr v-if="expanded === row.id" class="bg-slate-50">
                <td colspan="8" class="px-4 py-3">
                  <div class="grid gap-3 sm:grid-cols-2 text-xs font-mono">
                    <div v-if="row.old_value">
                      <p class="text-slate-500 mb-1 font-sans font-semibold">Trước</p>
                      <pre class="bg-red-50 text-red-800 rounded p-2 overflow-x-auto whitespace-pre-wrap">{{ formatJson(row.old_value) }}</pre>
                    </div>
                    <div v-if="row.new_value">
                      <p class="text-slate-500 mb-1 font-sans font-semibold">Sau</p>
                      <pre class="bg-green-50 text-green-800 rounded p-2 overflow-x-auto whitespace-pre-wrap">{{ formatJson(row.new_value) }}</pre>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
        <span>Hiển thị {{ rows.length }} / {{ pagination.total }} bản ghi</span>
        <div class="flex gap-2">
          <button
            type="button"
            class="hcm-btn-secondary text-xs"
            :disabled="pagination.current_page <= 1"
            @click="changePage(pagination.current_page - 1)"
          >← Trước</button>
          <span class="px-2 py-1">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
          <button
            type="button"
            class="hcm-btn-secondary text-xs"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
          >Tiếp →</button>
        </div>
      </div>
    </div>

    <UiEmpty v-else title="Chưa có nhật ký nào" subtitle="Các thao tác nhạy cảm sẽ được ghi lại tại đây" />
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import { useFormat } from '../../composables/useFormat';

const fmt = useFormat();
const loading = ref(false);
const error = ref('');
const rows = ref([]);
const expanded = ref(null);
const pagination = reactive({ current_page: 1, last_page: 1, total: 0 });

const filters = reactive({
  q: '',
  category: '',
  from: '',
  to: '',
  page: 1,
});

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const params = Object.fromEntries(
      Object.entries(filters).filter(([, v]) => v !== '' && v !== null)
    );
    const res = await api.get('/audit-logs', { params });
    const data = res.data?.data ?? res.data;
    rows.value = data.data ?? data;
    if (data.meta ?? data.last_page) {
      const meta = data.meta ?? data;
      pagination.current_page = meta.current_page;
      pagination.last_page = meta.last_page;
      pagination.total = meta.total;
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Không thể tải nhật ký';
  } finally {
    loading.value = false;
  }
}

function changePage(page) {
  filters.page = page;
  load();
}

function categoryLabel(cat) {
  return {
    security: '🔐 Bảo mật',
    contract: '📄 Hợp đồng',
    payroll: '💵 Lương',
    export: '📤 Xuất',
    workflow: '✅ Duyệt',
    offboarding: '🚪 Nghỉ việc',
    general: '🔧 Chung',
  }[cat] ?? cat ?? '—';
}

function categoryVariant(cat) {
  return {
    security: 'danger',
    contract: 'warning',
    payroll: 'warning',
    export: 'info',
    workflow: 'success',
    offboarding: 'danger',
  }[cat] ?? 'default';
}

function actionClass(action) {
  if (['deleted', 'rejected', 'login_failed'].includes(action)) return 'bg-red-100 text-red-700';
  if (['created', 'approved', 'login'].includes(action)) return 'bg-green-100 text-green-700';
  if (['updated', 'finalized'].includes(action)) return 'bg-yellow-100 text-yellow-700';
  if (['exported'].includes(action)) return 'bg-blue-100 text-blue-700';
  return 'bg-slate-100 text-slate-600';
}

function entityShortName(type) {
  if (!type) return '—';
  return type.split('\\').pop();
}

function formatJson(raw) {
  try {
    return JSON.stringify(JSON.parse(raw), null, 2);
  } catch {
    return raw;
  }
}

onMounted(load);
</script>
