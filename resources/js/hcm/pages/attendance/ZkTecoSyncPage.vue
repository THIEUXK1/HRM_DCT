<template>
  <div class="space-y-6">
    <UiPageHeader
      title="Đồng bộ vân tay & nhân sự"
      subtitle="Đẩy thông tin nhân viên, số thẻ và mẫu vân tay trực tiếp lên thiết bị ZKTeco"
      breadcrumb="Đồng bộ ZKTeco"
    />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left side: Form config -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Configuration Card -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="bg-gradient-to-r from-primary-600 to-indigo-600 px-6 py-4 text-white">
            <h3 class="font-semibold text-lg flex items-center gap-2">
              <span>🔄</span> Cấu hình đồng bộ
            </h3>
            <p class="text-xs text-primary-100 mt-1">Lựa chọn nhân sự và thiết bị chấm công đích</p>
          </div>

          <div class="p-6 space-y-6">
            <!-- 1. Select Sync Mode -->
            <div>
              <label class="hcm-label block text-sm font-semibold text-slate-700 mb-3">1. Phạm vi nhân sự</label>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <button
                  v-for="m in syncModes"
                  :key="m.value"
                  type="button"
                  class="flex flex-col items-center justify-center p-3 rounded-lg border text-center transition-all"
                  :class="form.mode === m.value
                    ? 'border-primary-600 bg-primary-50/50 text-primary-700 ring-2 ring-primary-500/20 font-medium'
                    : 'border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600'"
                  @click="setSyncMode(m.value)"
                >
                  <span class="text-xl mb-1">{{ m.icon }}</span>
                  <span class="text-xs">{{ m.label }}</span>
                </button>
              </div>
            </div>

            <!-- Sync Mode Details -->
            <div class="bg-slate-50 rounded-lg p-4 border border-slate-200/60 transition-all duration-200">
              <!-- Mode: department -->
              <div v-if="form.mode === 'department'" class="space-y-2">
                <label class="hcm-label text-xs">Chọn phòng ban</label>
                <select v-model="form.department_id" class="hcm-input text-sm">
                  <option :value="null">-- Vui lòng chọn phòng ban --</option>
                  <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                </select>
              </div>

              <!-- Mode: manual (Select individual employees) -->
              <div v-else-if="form.mode === 'manual'" class="space-y-3">
                <div class="flex gap-2">
                  <select v-model="filterDeptId" class="hcm-input text-xs flex-1" @change="loadEmployees">
                    <option :value="null">-- Lọc theo phòng ban --</option>
                    <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                  </select>
                  <input
                    v-model="employeeSearch"
                    type="text"
                    placeholder="Tìm theo tên/mã..."
                    class="hcm-input text-xs flex-1"
                  />
                </div>

                <div v-if="loadingEmployees" class="text-center py-4 text-xs text-slate-400">Đang tải danh sách nhân viên...</div>
                <div v-else class="border rounded bg-white max-h-60 overflow-y-auto divide-y divide-slate-100">
                  <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border-b sticky top-0">
                    <input
                      type="checkbox"
                      :checked="allEmployeesSelected"
                      class="rounded"
                      @change="toggleSelectAllEmployees"
                    />
                    <span class="text-xs font-semibold text-slate-600">
                      Chọn tất cả (Đã chọn {{ form.employee_ids.length }}/{{ filteredEmployees.length }})
                    </span>
                  </div>
                  <label
                    v-for="emp in filteredEmployees"
                    :key="emp.id"
                    class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer text-xs"
                  >
                    <input v-model="form.employee_ids" type="checkbox" :value="emp.id" class="rounded" />
                    <div class="flex-1">
                      <p class="font-medium text-slate-700">{{ emp.last_name }} {{ emp.first_name }}</p>
                      <p class="text-[10px] text-slate-400">Mã NV: {{ emp.employee_code }} | Vân tay (PIN): {{ emp.profile?.biometric_id || 'Chưa cấu hình' }}</p>
                    </div>
                    <span v-if="emp.profile?.card_number" class="text-[10px] bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full font-mono">
                      💳 {{ emp.profile?.card_number }}
                    </span>
                  </label>
                  <div v-if="!filteredEmployees.length" class="text-center py-6 text-xs text-slate-400">
                    Không tìm thấy nhân viên nào phù hợp
                  </div>
                </div>
              </div>

              <!-- Mode: filter (Custom Filters) -->
              <div v-else-if="form.mode === 'filter'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="hcm-label text-xs">Phòng ban</label>
                  <select v-model="form.filters.department_id" class="hcm-input text-xs">
                    <option :value="null">-- Tất cả phòng ban --</option>
                    <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="hcm-label text-xs">Chức vụ</label>
                  <select v-model="form.filters.position_id" class="hcm-input text-xs">
                    <option :value="null">-- Tất cả chức vụ --</option>
                    <option v-for="pos in positions" :key="pos.id" :value="pos.id">{{ pos.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="hcm-label text-xs">Trạng thái công việc</label>
                  <select v-model="form.filters.status" class="hcm-input text-xs">
                    <option value="active">Đang làm việc</option>
                    <option value="probation">Thử việc</option>
                    <option value="all">Tất cả</option>
                  </select>
                </div>
                <div>
                  <label class="hcm-label text-xs">Ngày nhận việc (Từ ngày)</label>
                  <input v-model="form.filters.join_date_from" type="date" class="hcm-input text-xs" />
                </div>
              </div>

              <!-- Mode: all -->
              <div v-else class="text-xs text-slate-500 py-1 flex items-center gap-2">
                <span class="text-emerald-500 text-lg">✓</span> Hệ thống sẽ quét toàn bộ nhân viên có trạng thái hoạt động trên HRM.
              </div>
            </div>

            <!-- 2. Select Target Devices -->
            <div>
              <label class="hcm-label block text-sm font-semibold text-slate-700 mb-3">2. Thiết bị đích nhận dữ liệu *</label>
              <div class="border rounded-lg p-3 max-h-48 overflow-y-auto space-y-2 bg-slate-50/50">
                <label
                  v-for="dev in devices"
                  :key="dev.id"
                  class="flex items-start gap-3 p-2 rounded hover:bg-white hover:shadow-sm border border-transparent hover:border-slate-200 transition-all cursor-pointer"
                >
                  <input v-model="form.device_ids" type="checkbox" :value="dev.id" class="rounded mt-0.5" />
                  <div class="flex-1 text-xs">
                    <div class="flex items-center gap-2">
                      <span class="font-medium text-slate-700">{{ dev.name }}</span>
                      <span class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-mono">{{ dev.ip_address }}:{{ dev.port }}</span>
                      <span v-if="dev.location" class="text-[10px] text-slate-400">📍 {{ dev.location }}</span>
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">
                      Model: {{ dev.vendor }} | SN: {{ dev.serial_number || 'N/A' }}
                    </div>
                  </div>
                </label>
                <div v-if="!devices.length" class="text-center py-6 text-xs text-slate-400">
                  Chưa cấu hình máy chấm công nào có kết nối IP.
                </div>
              </div>
            </div>

            <!-- 3. Overwrite behavior -->
            <div>
              <label class="hcm-label block text-sm font-semibold text-slate-700 mb-2">3. Hành vi nếu trùng mã vân tay (PIN)</label>
              <select v-model="form.options.overwrite_mode" class="hcm-input text-xs max-w-sm">
                <option value="skip">Bỏ qua (Giữ nguyên dữ liệu trên thiết bị)</option>
                <option value="update">Cập nhật (Ghi đè thông tin, mẫu vân tay & số thẻ từ HRM lên thiết bị)</option>
              </select>
            </div>
          </div>

          <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-between gap-4">
            <button
              type="button"
              class="hcm-btn-secondary flex items-center gap-1.5"
              :disabled="runningDryRun || runningSync || !form.device_ids.length"
              @click="runDryRun"
            >
              <span v-if="runningDryRun">⌛ Đang kiểm tra...</span>
              <span v-else>🔍 Kiểm tra trước (Dry-run)</span>
            </button>

            <button
              type="button"
              class="hcm-btn-primary flex items-center gap-1.5"
              :disabled="runningDryRun || runningSync || !form.device_ids.length"
              @click="runSync"
            >
              <span>🚀 Bắt đầu đồng bộ</span>
            </button>
          </div>
        </div>

        <!-- Dry Run Report panel -->
        <div v-if="dryRunReport" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden animate-fadeIn">
          <div class="bg-slate-900 px-6 py-4 text-white flex justify-between items-center">
            <div>
              <h4 class="font-semibold text-base">📊 Kết quả kiểm tra trước (Dry-run)</h4>
              <p class="text-xs text-slate-400 mt-0.5">Phân tích trước khi thay đổi dữ liệu trên phần cứng</p>
            </div>
            <button type="button" class="text-xs text-slate-400 hover:text-white" @click="dryRunReport = null">Đóng</button>
          </div>

          <div class="p-6 space-y-6">
            <!-- Summary counts -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <div class="bg-slate-50 rounded-lg p-3 border border-slate-200/60 text-center">
                <p class="text-[10px] uppercase font-semibold text-slate-400">Tổng nhân sự quét</p>
                <p class="text-2xl font-bold text-slate-700 mt-1">{{ dryRunReport.total_employees }}</p>
              </div>
              <div class="bg-slate-50 rounded-lg p-3 border border-slate-200/60 text-center">
                <p class="text-[10px] uppercase font-semibold text-slate-400">Thiết bị nhận</p>
                <p class="text-2xl font-bold text-slate-700 mt-1">{{ dryRunReport.total_devices }}</p>
              </div>
              <div class="bg-emerald-50 rounded-lg p-3 border border-emerald-100 text-center">
                <p class="text-[10px] uppercase font-semibold text-emerald-600">Đủ điều kiện</p>
                <p class="text-2xl font-bold text-emerald-700 mt-1">
                  {{ dryRunReport.total_employees - (dryRunReport.missing_biometric?.length || 0) }}
                </p>
              </div>
              <div class="bg-amber-50 rounded-lg p-3 border border-amber-100 text-center">
                <p class="text-[10px] uppercase font-semibold text-amber-600">Thiếu mã vân tay</p>
                <p class="text-2xl font-bold text-amber-700 mt-1">{{ dryRunReport.missing_biometric?.length || 0 }}</p>
              </div>
            </div>

            <!-- Device Breakdown -->
            <div>
              <p class="text-xs font-semibold text-slate-700 mb-3">Phân tích theo từng thiết bị</p>
              <div class="space-y-3">
                <div
                  v-for="db in dryRunReport.devices_breakdown"
                  :key="db.device_id"
                  class="border rounded-lg p-4 bg-slate-50/50"
                >
                  <div class="flex justify-between items-start flex-wrap gap-2">
                    <div>
                      <h5 class="font-medium text-sm text-slate-800">{{ db.device_name }}</h5>
                      <p class="text-[10px] text-slate-400 font-mono mt-0.5">{{ db.ip_address }}</p>
                    </div>
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-medium"
                      :class="db.is_online ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'"
                    >
                      {{ db.is_online ? 'ONLINE' : 'OFFLINE' }}
                    </span>
                  </div>

                  <div v-if="db.is_online" class="grid grid-cols-3 gap-2 mt-3 text-center bg-white rounded border p-2 text-xs">
                    <div>
                      <p class="text-[10px] text-slate-400 font-medium">Tạo mới</p>
                      <p class="font-bold text-emerald-600 mt-0.5">+{{ db.will_create }}</p>
                    </div>
                    <div>
                      <p class="text-[10px] text-slate-400 font-medium">Cập nhật/Ghi đè</p>
                      <p class="font-bold text-blue-600 mt-0.5">~{{ db.will_update }}</p>
                    </div>
                    <div>
                      <p class="text-[10px] text-slate-400 font-medium">Bỏ qua (đã có)</p>
                      <p class="font-bold text-slate-500 mt-0.5">{{ db.skipped_existing }}</p>
                    </div>
                  </div>
                  <div v-else class="mt-2 text-xs text-red-600 bg-red-50 p-2 rounded border border-red-100">
                    ⚠ Lỗi kết nối thiết bị: {{ db.error_message }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Warnings / Missing biometric code -->
            <div v-if="dryRunReport.missing_biometric?.length" class="space-y-2">
              <p class="text-xs font-semibold text-slate-700 text-amber-600 flex items-center gap-1">
                <span>⚠</span> Danh sách nhân sự bị bỏ qua do chưa cấu hình "Mã vân tay" trong HRM:
              </p>
              <div class="border rounded bg-amber-50/20 max-h-40 overflow-y-auto divide-y text-xs">
                <div v-for="emp in dryRunReport.missing_biometric" :key="emp.employee_code" class="px-3 py-1.5 flex justify-between">
                  <span class="font-medium text-slate-700">{{ emp.full_name }}</span>
                  <span class="font-mono text-slate-400">Mã NV: {{ emp.employee_code }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right side: Batch Status Progress & Sync History -->
      <div class="space-y-6">
        <!-- Active Batch Tracker -->
        <div v-if="activeBatch" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden animate-pulse-once">
          <div class="bg-slate-900 px-6 py-4 text-white">
            <div class="flex justify-between items-start">
              <div>
                <h4 class="font-semibold text-base flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></span>
                  Đang đồng bộ batch #{{ activeBatch.id }}
                </h4>
                <p class="text-xs text-slate-400 mt-1">Hệ thống đang đẩy dữ liệu chạy nền</p>
              </div>
              <span class="text-xs bg-slate-800 text-slate-300 px-2 py-0.5 rounded uppercase font-semibold">
                {{ activeBatch.status }}
              </span>
            </div>
          </div>

          <div class="p-6 space-y-4">
            <!-- Progress Bar -->
            <div>
              <div class="flex justify-between text-xs text-slate-500 mb-1">
                <span>Tiến trình hoàn thành</span>
                <span class="font-semibold">{{ batchPercent }}%</span>
              </div>
              <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                <div
                  class="bg-gradient-to-r from-primary-600 to-indigo-600 h-2.5 rounded-full transition-all duration-300"
                  :style="{ width: batchPercent + '%' }"
                ></div>
              </div>
            </div>

            <!-- Stats grid -->
            <div class="grid grid-cols-3 gap-2 text-center text-xs border rounded p-3 bg-slate-50">
              <div>
                <p class="text-[10px] text-slate-400 uppercase font-semibold">Thành công</p>
                <p class="text-lg font-bold text-emerald-600 mt-0.5">{{ activeBatch.success_count }}</p>
              </div>
              <div>
                <p class="text-[10px] text-slate-400 uppercase font-semibold">Thất bại</p>
                <p class="text-lg font-bold text-red-600 mt-0.5">{{ activeBatch.failed_count }}</p>
              </div>
              <div>
                <p class="text-[10px] text-slate-400 uppercase font-semibold">Bỏ qua</p>
                <p class="text-lg font-bold text-slate-500 mt-0.5">{{ activeBatch.skipped_count }}</p>
              </div>
            </div>

            <!-- Logs list for active batch -->
            <div v-if="activeBatch.logs?.length" class="space-y-2">
              <div class="flex justify-between items-center">
                <span class="text-xs font-semibold text-slate-700">Chi tiết nhật ký:</span>
                <div class="flex gap-1 text-[9px]">
                  <button
                    type="button"
                    class="px-1.5 py-0.5 rounded border"
                    :class="logFilter === 'all' ? 'bg-slate-200 border-slate-300' : 'bg-white'"
                    @click="logFilter = 'all'"
                  >Tất cả</button>
                  <button
                    type="button"
                    class="px-1.5 py-0.5 rounded border text-red-600 border-red-200"
                    :class="logFilter === 'failed' ? 'bg-red-50' : 'bg-white'"
                    @click="logFilter = 'failed'"
                  >Lỗi</button>
                  <button
                    type="button"
                    class="px-1.5 py-0.5 rounded border text-emerald-600 border-emerald-200"
                    :class="logFilter === 'success' ? 'bg-emerald-50' : 'bg-white'"
                    @click="logFilter = 'success'"
                  >Mới</button>
                </div>
              </div>

              <div class="border rounded bg-slate-50/50 max-h-48 overflow-y-auto divide-y text-[10px]">
                <div
                  v-for="log in filteredBatchLogs"
                  :key="log.id"
                  class="p-2 flex flex-col gap-0.5"
                >
                  <div class="flex justify-between items-center">
                    <span class="font-medium text-slate-700">
                      {{ log.employee?.full_name || 'N/A' }} ({{ log.employee_code }})
                    </span>
                    <span
                      class="px-1.5 py-0.2 rounded text-[8px] font-semibold uppercase"
                      :class="logStatusClass(log.status)"
                    >
                      {{ log.status }}
                    </span>
                  </div>
                  <div class="flex justify-between text-slate-400">
                    <span>📟 {{ log.device?.name || 'Device' }}</span>
                    <span>Action: {{ log.action }}</span>
                  </div>
                  <p v-if="log.message" class="text-slate-600 italic mt-0.5">{{ log.message }}</p>
                  <p v-if="log.error_detail" class="text-red-500 font-mono text-[9px] mt-0.5 break-all whitespace-pre-wrap">
                    Lỗi: {{ log.error_detail }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Past Batches History -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
            <h4 class="font-semibold text-slate-700 text-sm">🕰️ Lịch sử đồng bộ</h4>
            <button type="button" class="text-xs text-primary-600 hover:underline" @click="loadBatches">Làm mới</button>
          </div>

          <div class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
            <div
              v-for="b in pastBatches"
              :key="b.id"
              class="p-4 hover:bg-slate-50/50 cursor-pointer transition-all text-xs"
              :class="activeBatch?.id === b.id ? 'bg-primary-50/20 border-l-2 border-primary-600' : ''"
              @click="selectBatch(b.id)"
            >
              <div class="flex justify-between items-center">
                <span class="font-semibold text-slate-800">Batch #{{ b.id }}</span>
                <span
                  class="px-1.5 py-0.2 rounded text-[9px] font-semibold uppercase"
                  :class="batchStatusClass(b.status)"
                >
                  {{ b.status }}
                </span>
              </div>
              <div class="text-[10px] text-slate-400 mt-1 flex justify-between">
                <span>Phạm vi: {{ b.sync_type }}</span>
                <span>{{ formatDatetime(b.created_at) }}</span>
              </div>
              <div class="mt-2 flex gap-3 text-[10px] text-slate-500">
                <span>Tổng: {{ b.total_employees }} NV</span>
                <span class="text-emerald-600">✓ {{ b.success_count }}</span>
                <span class="text-red-600">✗ {{ b.failed_count }}</span>
              </div>
            </div>
            <div v-if="!pastBatches.length" class="text-center py-8 text-xs text-slate-400">
              Chưa có dữ liệu lịch sử đồng bộ.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import api from '../../api/client.js';
import { useToast } from '../../composables/useToast.js';
import { useAuthStore } from '../../stores/auth.js';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';

const toast = useToast();
const auth = useAuthStore();

const syncModes = [
  { value: 'all', label: 'Toàn bộ NV', icon: '👥' },
  { value: 'department', label: 'Theo phòng ban', icon: '🏢' },
  { value: 'manual', label: 'Từng người', icon: '👤' },
  { value: 'filter', label: 'Lọc nâng cao', icon: '⚙️' },
];

const devices = ref([]);
const departments = ref([]);
const positions = ref([]);
const employees = ref([]);
const pastBatches = ref([]);
const activeBatch = ref(null);

const filterDeptId = ref(null);
const employeeSearch = ref('');
const loadingEmployees = ref(false);
const runningDryRun = ref(false);
const runningSync = ref(false);
const dryRunReport = ref(null);
const logFilter = ref('all');

// Polling interval ID
let pollIntervalId = null;

const form = reactive({
  mode: 'all',
  department_id: null,
  employee_ids: [],
  device_ids: [],
  filters: {
    department_id: null,
    position_id: null,
    status: 'active',
    join_date_from: '',
  },
  options: {
    overwrite_mode: 'skip',
  },
});

onMounted(async () => {
  await Promise.all([
    loadDevices(),
    loadDepartments(),
    loadPositions(),
    loadBatches(),
  ]);
});

onUnmounted(() => {
  stopPolling();
});

async function loadDevices() {
  try {
    const res = await api.get('/attendance-devices');
    const list = res.data.data ?? res.data;
    // Lọc các thiết bị có IP
    devices.value = list.filter(d => d.ip_address);
    // Tự động check chọn tất cả thiết bị active
    form.device_ids = devices.value.filter(d => d.is_active).map(d => d.id);
  } catch {
    toast.show('Lỗi tải danh sách thiết bị', 'error');
  }
}

async function loadDepartments() {
  try {
    const res = await api.get('/departments');
    departments.value = res.data.data ?? res.data;
  } catch { /* dept filter optional */ }
}

async function loadPositions() {
  try {
    const res = await api.get('/positions');
    positions.value = res.data.data ?? res.data;
  } catch { /* position filter optional */ }
}

async function loadEmployees() {
  loadingEmployees.value = true;
  try {
    const params = { per_page: 1000 };
    if (filterDeptId.value) params.department_id = filterDeptId.value;
    const res = await api.get('/employees', { params });
    // Trả về danh sách nhân viên
    employees.value = res.data.data?.data ?? res.data.data ?? [];
  } catch {
    toast.show('Lỗi tải danh sách nhân sự', 'error');
  } finally {
    loadingEmployees.value = false;
  }
}

async function loadBatches() {
  try {
    const res = await api.get('/zkteco/sync/batches');
    pastBatches.value = res.data.data ?? res.data;
    // Check if there is a running/pending batch
    const active = pastBatches.value.find(b => b.status === 'pending' || b.status === 'processing');
    if (active) {
      startPolling(active.id);
    }
  } catch { /* optional */ }
}

function setSyncMode(mode) {
  form.mode = mode;
  form.employee_ids = [];
  form.department_id = null;
  if (mode === 'manual' && !employees.value.length) {
    loadEmployees();
  }
}

// Manual Employee filter
const filteredEmployees = computed(() => {
  let list = employees.value;
  if (employeeSearch.value.trim()) {
    const s = employeeSearch.value.toLowerCase().trim();
    list = list.filter(e =>
      e.employee_code.toLowerCase().includes(s) ||
      `${e.last_name} ${e.first_name}`.toLowerCase().includes(s)
    );
  }
  return list;
});

const allEmployeesSelected = computed(() => {
  return filteredEmployees.value.length > 0 &&
    filteredEmployees.value.every(e => form.employee_ids.includes(e.id));
});

function toggleSelectAllEmployees() {
  if (allEmployeesSelected.value) {
    // Unselect all in current filter
    const idsToRemove = filteredEmployees.value.map(e => e.id);
    form.employee_ids = form.employee_ids.filter(id => !idsToRemove.includes(id));
  } else {
    // Select all in current filter
    const newIds = [...form.employee_ids];
    filteredEmployees.value.forEach(e => {
      if (!newIds.includes(e.id)) newIds.push(e.id);
    });
    form.employee_ids = newIds;
  }
}

// Dry run API trigger
async function runDryRun() {
  if (!form.device_ids.length) {
    toast.show('Vui lòng chọn ít nhất một thiết bị nhận', 'warning');
    return;
  }
  if (form.mode === 'manual' && !form.employee_ids.length) {
    toast.show('Vui lòng chọn ít nhất một nhân viên', 'warning');
    return;
  }
  if (form.mode === 'department' && !form.department_id) {
    toast.show('Vui lòng chọn một phòng ban', 'warning');
    return;
  }

  runningDryRun.value = true;
  dryRunReport.value = null;
  try {
    const payload = {
      mode: form.mode,
      device_ids: form.device_ids,
      department_id: form.department_id,
      employee_ids: form.employee_ids,
      filters: form.mode === 'filter' ? form.filters : {},
      options: form.options,
    };
    const res = await api.post('/zkteco/sync/dry-run', payload);
    dryRunReport.value = res.data.data ?? res.data;
    toast.show('Kiểm tra trước (Dry-run) hoàn tất.');
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi chạy kiểm tra trước', 'error');
  } finally {
    runningDryRun.value = false;
  }
}

// Actual sync run
async function runSync() {
  if (!form.device_ids.length) {
    toast.show('Vui lòng chọn ít nhất một thiết bị nhận', 'warning');
    return;
  }
  if (form.mode === 'manual' && !form.employee_ids.length) {
    toast.show('Vui lòng chọn ít nhất một nhân viên', 'warning');
    return;
  }
  if (form.mode === 'department' && !form.department_id) {
    toast.show('Vui lòng chọn một phòng ban', 'warning');
    return;
  }

  runningSync.value = true;
  try {
    const payload = {
      mode: form.mode,
      device_ids: form.device_ids,
      department_id: form.department_id,
      employee_ids: form.employee_ids,
      filters: form.mode === 'filter' ? form.filters : {},
      options: form.options,
    };
    const res = await api.post('/zkteco/sync/run', payload);
    const batch = res.data.data ?? res.data;
    toast.show('✓ Đã lên lịch đồng bộ chạy nền thành công!');
    startPolling(batch.id);
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi bắt đầu đồng bộ', 'error');
  } finally {
    runningSync.value = false;
  }
}

// Detail polling for active batch progress
function startPolling(batchId) {
  stopPolling();
  pollIntervalId = setInterval(() => {
    fetchBatchDetail(batchId);
  }, 2500);
  fetchBatchDetail(batchId); // Immediate call
}

function stopPolling() {
  if (pollIntervalId) {
    clearInterval(pollIntervalId);
    pollIntervalId = null;
  }
}

async function fetchBatchDetail(batchId) {
  try {
    const res = await api.get(`/zkteco/sync/batches/${batchId}`);
    const detail = res.data.data ?? res.data;
    activeBatch.value = detail;

    // Check if finished
    if (detail.status === 'completed' || detail.status === 'failed') {
      stopPolling();
      toast.show(`✓ Batch đồng bộ #${detail.id} đã hoàn thành với trạng thái: ${detail.status}`);
      loadBatches(); // Reload past history list
    }
  } catch {
    stopPolling();
  }
}

function selectBatch(batchId) {
  startPolling(batchId);
}

const batchPercent = computed(() => {
  if (!activeBatch.value) return 0;
  const b = activeBatch.value;
  const logged = (b.success_count || 0) + (b.failed_count || 0) + (b.skipped_count || 0);
  const total = (b.total_employees || 0) * (b.target_device_ids?.length || 1);
  if (total === 0) return b.status === 'completed' ? 100 : 0;
  return Math.min(100, Math.round((logged / total) * 100));
});

const filteredBatchLogs = computed(() => {
  if (!activeBatch.value?.logs) return [];
  const list = activeBatch.value.logs;
  if (logFilter.value === 'failed') return list.filter(l => l.status === 'failed');
  if (logFilter.value === 'success') return list.filter(l => l.status === 'success');
  return list;
});

// Styling Helpers
function logStatusClass(status) {
  if (status === 'success') return 'bg-emerald-50 text-emerald-700 border border-emerald-100';
  if (status === 'failed') return 'bg-red-50 text-red-700 border border-red-100';
  return 'bg-slate-100 text-slate-600';
}

function batchStatusClass(status) {
  if (status === 'completed') return 'bg-emerald-100 text-emerald-800';
  if (status === 'failed') return 'bg-red-100 text-red-800';
  if (status === 'processing') return 'bg-blue-100 text-blue-800';
  return 'bg-amber-100 text-amber-800';
}

function formatDatetime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
  animation: fadeIn 0.3s ease-out forwards;
}

@keyframes pulseOnce {
  0% { transform: scale(1); }
  50% { transform: scale(1.01); }
  100% { transform: scale(1); }
}
.animate-pulse-once {
  animation: pulseOnce 0.4s ease-in-out 1;
}
</style>
