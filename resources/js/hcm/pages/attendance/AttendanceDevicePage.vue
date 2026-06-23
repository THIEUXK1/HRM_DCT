<template>
  <div>
    <UiPageHeader
      title="Quản lý máy chấm công"
      subtitle="Cấu hình thiết bị ZKTeco · Đồng bộ log chấm công qua TCP/IP"
      breadcrumb="Máy chấm công"
    >
      <template #actions>
        <button type="button" class="hcm-btn-secondary" @click="showPushModal = true">⬆ Đẩy NV lên máy</button>
        <button type="button" class="hcm-btn-primary" @click="openForm()">+ Thêm thiết bị</button>
      </template>
    </UiPageHeader>

    <div v-if="loading" class="text-center py-16 text-slate-400">Đang tải...</div>
    <div v-else-if="error" class="hcm-card p-6 text-red-600">{{ error }}</div>
    <UiEmpty v-else-if="!devices.length" title="Chưa có thiết bị" subtitle="Thêm máy chấm công ZKTeco để tự động lấy log." />

    <div v-else class="space-y-3">
      <div v-for="dev in devices" :key="dev.id" class="hcm-card p-4">
        <div class="flex flex-wrap items-start gap-4">
          <!-- Status indicator -->
          <div class="flex flex-col items-center gap-1 min-w-[56px]">
            <div
              class="w-10 h-10 rounded-full flex items-center justify-center text-xl"
              :class="statusBg(dev)"
            >⏱️</div>
            <span class="text-[10px] font-medium" :class="statusText(dev)">
              {{ statusLabel(dev) }}
            </span>
          </div>

          <!-- Device info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-semibold text-slate-800">{{ dev.name }}</span>
              <span class="text-xs text-slate-400">[{{ dev.code }}]</span>
              <span v-if="!dev.is_active" class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded">Tắt</span>
            </div>
            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-sm text-slate-500">
              <span v-if="dev.ip_address">
                🌐 <span class="font-mono">{{ dev.ip_address }}:{{ dev.port }}</span>
              </span>
              <span v-else class="text-amber-600">⚠ Chưa cấu hình IP</span>
              <span v-if="dev.vendor">📟 {{ dev.vendor }}</span>
              <span v-if="dev.serial_number">🔢 SN: <span class="font-mono text-slate-700 font-medium">{{ dev.serial_number }}</span></span>
              <span v-if="dev.location">📍 Vị trí: {{ dev.location }}</span>
              <span v-if="dev.department_id">🏢 Bộ phận: {{ getDepartmentName(dev.department_id) }}</span>
              <span v-if="dev.last_sync_at">
                🔄 Lần cuối: {{ formatDatetime(dev.last_sync_at) }}
              </span>
              <span v-else-if="dev.ip_address" class="text-slate-400">Chưa đồng bộ lần nào</span>
            </div>
            <div v-if="dev.sync_message && dev.sync_status === 'failed'" class="mt-1 text-xs text-red-600">
              ✗ {{ dev.sync_message }}
            </div>
            <div v-else-if="dev.sync_message && dev.sync_status === 'success'" class="mt-1 text-xs text-emerald-600">
              ✓ {{ dev.sync_message }}
            </div>
          </div>

          <!-- Actions -->
          <div class="flex flex-wrap gap-2 shrink-0">
            <button
              v-if="dev.ip_address"
              type="button"
              class="hcm-btn-secondary text-sm"
              :disabled="testing[dev.id]"
              @click="testConn(dev)"
            >
              {{ testing[dev.id] ? 'Đang kiểm tra...' : '🔌 Test kết nối' }}
            </button>
            <button
              v-if="dev.ip_address"
              type="button"
              class="hcm-btn-secondary text-sm"
              :disabled="fetchingInfo[dev.id]"
              @click="fetchInfo(dev)"
            >
              {{ fetchingInfo[dev.id] ? 'Đang lấy...' : '📟 Lấy info thiết bị' }}
            </button>
            <button
              v-if="dev.ip_address"
              type="button"
              class="hcm-btn-secondary text-sm"
              :disabled="pulling[dev.id]"
              @click="pullBiometrics(dev)"
            >
              {{ pulling[dev.id] ? 'Đang lấy...' : '📥 Lấy vân tay' }}
            </button>
            <button
              v-if="dev.ip_address"
              type="button"
              class="hcm-btn-secondary text-sm"
              :disabled="pushing[dev.id]"
              @click="pushEmployees(dev)"
            >
              {{ pushing[dev.id] ? 'Đang đẩy...' : '⬆ Đẩy danh sách NV' }}
            </button>
            <button
              v-if="dev.ip_address"
              type="button"
              class="hcm-btn-primary text-sm"
              :disabled="syncing[dev.id]"
              @click="syncNow(dev)"
            >
              {{ syncing[dev.id] ? 'Đang đồng bộ...' : '⬇ Lấy log ngay' }}
            </button>
            <button type="button" class="hcm-btn-secondary text-sm" @click="openForm(dev)">✏ Sửa</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Form Modal -->
    <UiModal v-model="showForm" :title="form.id ? 'Sửa thiết bị' : 'Thêm thiết bị'">
      <form class="space-y-4" @submit.prevent="saveDevice">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="hcm-label">Tên thiết bị *</label>
            <input v-model="form.name" type="text" class="hcm-input" required />
          </div>
          <div>
            <label class="hcm-label">Mã (code) *</label>
            <input v-model="form.code" type="text" class="hcm-input" required :disabled="!!form.id" />
          </div>
          <div>
            <label class="hcm-label">Hãng sản xuất</label>
            <input v-model="form.vendor" type="text" class="hcm-input" placeholder="ZKTeco" />
          </div>
          <div>
            <label class="hcm-label">Loại thiết bị</label>
            <select v-model="form.device_type" class="hcm-input">
              <option value="zkteco">ZKTeco (TCP/IP)</option>
              <option value="import">Import CSV</option>
              <option value="terminal">Terminal</option>
              <option value="kiosk">Kiosk</option>
            </select>
          </div>
        </div>

        <div class="border-t pt-4">
          <p class="text-sm font-medium text-slate-700 mb-3">Kết nối TCP/IP (ZKTeco)</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
              <label class="hcm-label">Địa chỉ IP</label>
              <input v-model="form.ip_address" type="text" class="hcm-input font-mono" placeholder="192.168.1.200" />
            </div>
            <div>
              <label class="hcm-label">Cổng (Port)</label>
              <input v-model.number="form.port" type="number" class="hcm-input font-mono" placeholder="4370" min="1" max="65535" />
            </div>
            <div class="sm:col-span-3">
              <label class="hcm-label">Mã kết nối (Comm Key)</label>
              <input v-model="form.comm_key" type="text" class="hcm-input" placeholder="Ví dụ: 0" />
            </div>
            <div class="sm:col-span-3">
              <label class="hcm-label">
                Mật khẩu kết nối
                <span class="text-slate-400 font-normal">(để trống nếu không đặt mật khẩu)</span>
              </label>
              <input v-model="form.connection_password" type="password" class="hcm-input" autocomplete="new-password" />
            </div>
          </div>
        </div>

        <div class="border-t pt-4">
          <p class="text-sm font-medium text-slate-700 mb-3">Thông tin vị trí & bộ phận</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="hcm-label">Vị trí</label>
              <input v-model="form.location" type="text" class="hcm-input" placeholder="Nhà bảo vệ, Xưởng A..." />
            </div>
            <div>
              <label class="hcm-label">Bộ phận quản lý</label>
              <select v-model="form.department_id" class="hcm-input">
                <option :value="null">-- Không chọn --</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded" />
          <label for="is_active" class="text-sm">Thiết bị đang hoạt động</label>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="hcm-btn-secondary" @click="closeForm">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="saving">
            {{ saving ? 'Đang lưu...' : (form.id ? 'Cập nhật' : 'Thêm thiết bị') }}
          </button>
        </div>
      </form>
    </UiModal>

    <!-- Push employees modal -->
    <PushEmployeesModal v-model="showPushModal" :devices="devices" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../api/client.js';
import { useToast } from '../../composables/useToast.js';
import { useAuthStore } from '../../stores/auth.js';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiModal from '../../components/ui/UiModal.vue';
import PushEmployeesModal from '../../components/attendance/PushEmployeesModal.vue';

const toast = useToast();
const auth = useAuthStore();

const loading = ref(true);
const error = ref(null);
const devices = ref([]);
const departments = ref([]);
const showForm = ref(false);
const showPushModal = ref(false);
const saving = ref(false);
const syncing  = reactive({});
const testing  = reactive({});
const pushing  = reactive({});
const pulling  = reactive({});
const fetchingInfo = reactive({});

const emptyForm = () => ({
  id: null,
  company_id: auth.currentCompanyId,
  name: '',
  code: '',
  vendor: 'ZKTeco',
  device_type: 'zkteco',
  ip_address: '',
  port: 4370,
  comm_key: '',
  connection_password: '',
  location: '',
  department_id: null,
  is_active: true,
});

const form = reactive(emptyForm());

onMounted(load);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    const [devRes, deptRes] = await Promise.all([
      api.get('/attendance-devices'),
      api.get('/departments')
    ]);
    devices.value = devRes.data.data ?? devRes.data;
    departments.value = deptRes.data.data ?? deptRes.data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Lỗi tải dữ liệu thiết bị hoặc phòng ban';
  } finally {
    loading.value = false;
  }
}

function getDepartmentName(deptId) {
  const d = departments.value.find(x => x.id === deptId);
  return d ? d.name : 'N/A';
}

function openForm(dev = null) {
  if (dev) {
    Object.assign(form, {
      id: dev.id,
      company_id: dev.company_id,
      name: dev.name,
      code: dev.code,
      vendor: dev.vendor ?? '',
      device_type: dev.device_type ?? 'zkteco',
      ip_address: dev.ip_address ?? '',
      port: dev.port ?? 4370,
      comm_key: dev.comm_key ?? '',
      connection_password: '',
      location: dev.location ?? '',
      department_id: dev.department_id ?? null,
      is_active: dev.is_active,
    });
  } else {
    Object.assign(form, emptyForm());
  }
  showForm.value = true;
}

function closeForm() {
  showForm.value = false;
}

async function saveDevice() {
  saving.value = true;
  try {
    const payload = { ...form };
    if (!payload.connection_password) delete payload.connection_password;

    if (form.id) {
      await api.put(`/attendance-devices/${form.id}`, payload);
      toast.show('Đã cập nhật thiết bị');
    } else {
      await api.post('/attendance-devices', payload);
      toast.show('Đã thêm thiết bị');
    }
    closeForm();
    await load();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi lưu thiết bị', 'error');
  } finally {
    saving.value = false;
  }
}

async function syncNow(dev) {
  syncing[dev.id] = true;
  try {
    const res = await api.post(`/attendance-devices/${dev.id}/sync`);
    const d = res.data.data ?? res.data;
    toast.show(`✓ ${d.message || 'Đồng bộ thành công'}`);
    await load();
  } catch (e) {
    toast.show(e.response?.data?.data?.message || e.response?.data?.message || 'Lỗi đồng bộ', 'error');
    await load();
  } finally {
    syncing[dev.id] = false;
  }
}

async function pullBiometrics(dev) {
  pulling[dev.id] = true;
  try {
    const res = await api.post(`/attendance-devices/${dev.id}/pull-biometrics`);
    const d = res.data.data ?? res.data;
    toast.show(d.message || 'Đã lấy vân tay từ thiết bị');
  } catch (e) {
    toast.show(e.response?.data?.data?.message || e.response?.data?.message || 'Lỗi lấy vân tay', 'error');
  } finally {
    pulling[dev.id] = false;
  }
}

async function pushEmployees(dev) {
  pushing[dev.id] = true;
  try {
    const res = await api.post(`/attendance-devices/${dev.id}/push-employees`);
    const d = res.data.data ?? res.data;
    toast.show(d.message || 'Đã đẩy danh sách NV lên thiết bị');
  } catch (e) {
    toast.show(e.response?.data?.data?.message || e.response?.data?.message || 'Lỗi đẩy NV', 'error');
  } finally {
    pushing[dev.id] = false;
  }
}

async function testConn(dev) {
  testing[dev.id] = true;
  try {
    const res = await api.post(`/attendance-devices/${dev.id}/test-connection`);
    const d = res.data.data ?? res.data;
    toast.show(d.ok ? `✓ ${d.message}` : `✗ ${d.message}`, d.ok ? 'success' : 'error');
  } catch (e) {
    toast.show(e.response?.data?.data?.message || 'Kết nối thất bại', 'error');
  } finally {
    testing[dev.id] = false;
  }
}

async function fetchInfo(dev) {
  fetchingInfo[dev.id] = true;
  try {
    const res = await api.post(`/attendance-devices/${dev.id}/fetch-info`);
    const d = res.data.data ?? res.data;
    toast.show(d.message || 'Lấy thông tin thiết bị thành công.');
    await load();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi lấy thông tin thiết bị', 'error');
  } finally {
    fetchingInfo[dev.id] = false;
  }
}

function statusBg(dev) {
  if (!dev.ip_address) return 'bg-slate-100';
  if (dev.sync_status === 'success') return 'bg-emerald-100';
  if (dev.sync_status === 'failed') return 'bg-red-100';
  if (dev.sync_status === 'syncing') return 'bg-blue-100';
  return 'bg-amber-50';
}

function statusText(dev) {
  if (!dev.ip_address) return 'text-slate-400';
  if (dev.sync_status === 'success') return 'text-emerald-700';
  if (dev.sync_status === 'failed') return 'text-red-600';
  if (dev.sync_status === 'syncing') return 'text-blue-600';
  return 'text-amber-600';
}

function statusLabel(dev) {
  if (!dev.ip_address) return 'CSV';
  if (dev.sync_status === 'success') return 'OK';
  if (dev.sync_status === 'failed') return 'Lỗi';
  if (dev.sync_status === 'syncing') return '...';
  return 'ZKT';
}

function formatDatetime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>
