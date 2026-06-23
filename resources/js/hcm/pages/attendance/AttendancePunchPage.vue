<template>
  <div>
    <UiPageHeader
      title="Chấm công GPS / QR"
      subtitle="GPS trong geofence · Quét QR tại cổng khi GPS yếu · Công tác khi có đơn duyệt"
      breadcrumb="Attendance Punch"
    />

    <div v-if="loading" class="text-center py-16 text-slate-400">Đang tải...</div>
    <div v-else-if="error" class="hcm-card p-6 text-red-600">{{ error }}</div>

    <div v-else-if="!canPunchGps && !canPunchQr" class="hcm-card p-8 text-center">
      <p class="text-slate-600">Tài khoản chưa được cấp quyền chấm công GPS hoặc QR.</p>
      <p class="text-sm text-slate-500 mt-2">Liên hệ HR để được kích hoạt tài khoản chấm công trên thiết bị.</p>
    </div>

    <template v-else>
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Panel chấm công -->
        <div class="lg:col-span-2 space-y-4">
          <div class="hcm-card p-6">
            <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
              <div>
                <p class="text-sm text-slate-500">Hôm nay · {{ todayLabel }}</p>
                <p class="text-lg font-semibold text-slate-900 mt-1">
                  <span v-if="todayLog?.check_in_at">Vào: {{ fmtTime(todayLog.check_in_at) }}</span>
                  <span v-else class="text-amber-600">Chưa chấm vào</span>
                  <span class="mx-2 text-slate-300">|</span>
                  <span v-if="todayLog?.check_out_at">Ra: {{ fmtTime(todayLog.check_out_at) }}</span>
                  <span v-else class="text-slate-400">Chưa chấm ra</span>
                </p>
                <p v-if="todayLog?.location_status" class="text-xs text-slate-500 mt-1">
                  Trạng thái vị trí: {{ locationLabel(todayLog.location_status) }}
                </p>
              </div>
              <UiBadge :variant="gpsReady ? 'success' : 'warning'">
                {{ gpsReady ? 'GPS sẵn sàng' : 'Đang lấy GPS...' }}
              </UiBadge>
            </div>

            <div v-if="position" class="rounded-lg bg-slate-50 border border-slate-100 p-4 mb-6 text-sm">
              <p><span class="text-slate-500">Tọa độ:</span> {{ position.latitude.toFixed(6) }}, {{ position.longitude.toFixed(6) }}</p>
              <p v-if="position.accuracy" class="text-slate-500 mt-1">Độ chính xác ±{{ Math.round(position.accuracy) }}m</p>
              <p v-if="matchedZone" class="text-emerald-700 mt-2 font-medium">✓ Trong phạm vi: {{ matchedZone.name }}</p>
              <p v-else class="text-amber-700 mt-2">⚠ Ngoài phạm vi chi nhánh — chấm công có thể bị từ chối</p>
            </div>
            <div v-else-if="gpsError" class="rounded-lg bg-red-50 border border-red-100 p-4 mb-4 text-sm text-red-700">
              {{ gpsError }}
              <button type="button" class="block mt-2 text-primary-600 underline" @click="refreshGps">Thử lại GPS</button>
            </div>

            <!-- QR cổng -->
            <div v-if="canPunchQr" class="rounded-lg border border-slate-200 p-4 mb-6 space-y-3">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-medium text-sm">📱 Quét QR tại cổng</p>
                <UiBadge v-if="scannedQr" variant="success">Đã quét: {{ scannedQr.zone_code }}</UiBadge>
              </div>
              <p class="text-xs text-slate-500">Dùng khi GPS trong nhà xưởng không ổn định. HR in QR tại Bảng công → Geofence.</p>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="hcm-btn-secondary text-sm" :disabled="scanningQr" @click="scanQrWithCamera">
                  {{ scanningQr ? 'Đang mở camera...' : '📷 Quét bằng camera' }}
                </button>
                <button v-if="scannedQr" type="button" class="text-sm text-red-600 hover:underline" @click="clearScannedQr">Xóa QR</button>
              </div>
              <div>
                <label class="text-xs text-slate-500">Hoặc dán nội dung QR</label>
                <div class="flex gap-2 mt-1">
                  <input v-model="qrPayloadInput" type="text" class="hcm-input flex-1 text-xs font-mono" placeholder="EHR-PUNCH|..." />
                  <button type="button" class="hcm-btn-secondary text-sm shrink-0" @click="applyQrPayload">Áp dụng</button>
                </div>
              </div>
              <video v-show="showQrVideo" ref="qrVideoRef" class="w-full max-w-xs rounded border border-slate-200" playsinline muted />
            </div>

            <div v-if="canPunchGps || (canPunchQr && scannedQr)" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <button
                type="button"
                class="hcm-btn-primary py-4 text-base"
                :disabled="punching || !canPunch"
                @click="doPunch('in')"
              >
                {{ punching === 'in' ? 'Đang chấm...' : '🟢 Chấm vào' }}
              </button>
              <button
                type="button"
                class="hcm-btn-secondary py-4 text-base"
                :disabled="punching || !canPunch"
                @click="doPunch('out')"
              >
                {{ punching === 'out' ? 'Đang chấm...' : '🔴 Chấm ra' }}
              </button>
            </div>
            <p v-else-if="canPunchQr && !canPunchGps" class="text-sm text-slate-500">Quét QR cổng để chấm công.</p>
          </div>

          <!-- Lịch sử hôm nay -->
          <div class="hcm-card p-5">
            <h3 class="font-semibold mb-3">Lịch sử chấm hôm nay</h3>
            <UiEmpty v-if="!lastPunches.length" title="Chưa có lần chấm nào" />
            <ul v-else class="divide-y divide-slate-100">
              <li v-for="p in lastPunches" :key="p.id" class="py-3 flex items-center justify-between gap-3 text-sm">
                <div>
                  <span class="font-medium">{{ p.punch_type === 'in' ? 'Vào' : 'Ra' }}</span>
                  <span class="text-slate-400 mx-2">·</span>
                  <span>{{ fmtTime(p.punched_at) }}</span>
                  <span v-if="p.zone" class="text-slate-500 ml-2">({{ p.zone.name }})</span>
                </div>
                <UiBadge :variant="p.is_valid ? 'success' : 'danger'">{{ p.is_valid ? 'Hợp lệ' : 'Không hợp lệ' }}</UiBadge>
              </li>
            </ul>
          </div>
        </div>

        <!-- Sidebar hướng dẫn -->
        <div class="space-y-4">
          <div class="hcm-card p-5 text-sm text-slate-600 space-y-2">
            <p class="font-semibold text-slate-800">📍 Cách hoạt động</p>
            <p>1. Bật GPS hoặc quét QR tại cổng nhà máy.</p>
            <p>2. GPS: kiểm tra trong vùng geofence của chi nhánh bạn.</p>
            <p>3. QR: dán tại cổng — không cần GPS chính xác.</p>
            <p>4. Công tác: cần đơn công tác đã duyệt trong ngày.</p>
          </div>

          <div class="hcm-card p-5">
            <h3 class="font-semibold text-sm mb-3">Khu vực được phép</h3>
            <p v-if="myBranch" class="text-xs text-blue-800 bg-blue-50 border border-blue-100 rounded p-2 mb-3">
              Chi nhánh làm việc: <b>{{ myBranch.name }}</b> ({{ myBranch.code }})
            </p>
            <p v-else class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded p-2 mb-3">
              Chưa gán chi nhánh — chỉ chấm tại vùng chung công ty.
            </p>
            <ul class="space-y-2 text-sm">
              <li v-for="z in zones" :key="z.id" class="rounded border border-slate-100 p-3">
                <p class="font-medium">{{ z.name }}</p>
                <p class="text-xs text-slate-500">{{ z.code }} · bán kính {{ z.radius_meters }}m</p>
              </li>
            </ul>
            <UiEmpty v-if="!zones.length" title="Chưa cấu hình geofence" subtitle="HR cấu hình tại Bảng công → Geofence" />
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import { useToast } from '../../composables/useToast';
import { usePermission } from '../../composables/usePermission';

const toast = useToast();
const { can } = usePermission();

const canPunchGps = computed(() => can('attendance.punch_gps'));
const canPunchQr = computed(() => can('attendance.punch_qr'));

const loading = ref(true);
const error = ref('');
const todayLog = ref(null);
const lastPunches = ref([]);
const zones = ref([]);
const myBranch = ref(null);
const position = ref(null);
const gpsError = ref('');
const gpsReady = ref(false);
const punching = ref('');
const scannedQr = ref(null);
const qrPayloadInput = ref('');
const scanningQr = ref(false);
const showQrVideo = ref(false);
const qrVideoRef = ref(null);
let watchId = null;
let qrScanTimer = null;
let qrStream = null;

const canPunch = computed(() => {
  if (scannedQr.value && canPunchQr.value) return true;
  if (position.value && canPunchGps.value) return true;
  return false;
});
const todayLabel = computed(() => new Date().toLocaleDateString('vi-VN', {
  weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
}));

const matchedZone = computed(() => {
  if (!position.value || !zones.value.length) return null;
  const { latitude, longitude } = position.value;
  let best = null;
  let bestDist = Infinity;
  for (const z of zones.value) {
    const d = haversine(latitude, longitude, Number(z.latitude), Number(z.longitude));
    if (d <= z.radius_meters && d < bestDist) {
      best = z;
      bestDist = d;
    }
  }
  return best;
});

function haversine(lat1, lon1, lat2, lon2) {
  const R = 6371000;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = Math.sin(dLat / 2) ** 2
    + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
  return 2 * R * Math.asin(Math.min(1, Math.sqrt(a)));
}

function fmtTime(val) {
  if (!val) return '—';
  return new Date(val).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function locationLabel(s) {
  return {
    valid: 'Trong phạm vi',
    outside: 'Ngoài phạm vi',
    field_trip: 'Công tác',
    device_trusted: 'Máy chấm công',
    qr_gate: 'QR cổng',
  }[s] || s;
}

function parseQrPayload(text) {
  const parts = String(text || '').trim().split('|');
  if (parts.length !== 4 || parts[0] !== 'EHR-PUNCH') return null;
  return { zone_code: parts[2], gate_token: parts[3], qr_payload: text.trim() };
}

function applyQrPayload() {
  const parsed = parseQrPayload(qrPayloadInput.value);
  if (!parsed) {
    toast.show('Mã QR không đúng định dạng', 'error');
    return;
  }
  scannedQr.value = parsed;
  toast.show(`Đã áp dụng QR cổng: ${parsed.zone_code}`);
}

function clearScannedQr() {
  scannedQr.value = null;
  qrPayloadInput.value = '';
}

async function stopQrCamera() {
  showQrVideo.value = false;
  scanningQr.value = false;
  if (qrScanTimer) {
    clearInterval(qrScanTimer);
    qrScanTimer = null;
  }
  if (qrStream) {
    qrStream.getTracks().forEach((t) => t.stop());
    qrStream = null;
  }
}

async function scanQrWithCamera() {
  if (!('BarcodeDetector' in window)) {
    toast.show('Trình duyệt chưa hỗ trợ quét QR — hãy dán nội dung QR thủ công', 'warning');
    return;
  }
  scanningQr.value = true;
  try {
    qrStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    showQrVideo.value = true;
    await nextTick();
    if (qrVideoRef.value) {
      qrVideoRef.value.srcObject = qrStream;
      await qrVideoRef.value.play();
    }
    const detector = new BarcodeDetector({ formats: ['qr_code'] });
    qrScanTimer = setInterval(async () => {
      if (!qrVideoRef.value) return;
      try {
        const codes = await detector.detect(qrVideoRef.value);
        if (codes.length > 0) {
          const parsed = parseQrPayload(codes[0].rawValue);
          if (parsed) {
            scannedQr.value = parsed;
            qrPayloadInput.value = parsed.qr_payload;
            toast.show(`Đã quét QR: ${parsed.zone_code}`);
            await stopQrCamera();
          }
        }
      } catch {
        /* ignore frame errors */
      }
    }, 500);
  } catch {
    toast.show('Không mở được camera. Kiểm tra quyền truy cập.', 'error');
    await stopQrCamera();
  } finally {
    scanningQr.value = false;
  }
}

async function loadToday() {
  const { data } = await api.get('/self-service/attendance/punch/today');
  todayLog.value = data.data.today;
  lastPunches.value = data.data.last_punches || [];
  zones.value = data.data.zones || [];
  myBranch.value = data.data.branch || null;
}

function refreshGps() {
  gpsError.value = '';
  gpsReady.value = false;
  if (!navigator.geolocation) {
    gpsError.value = 'Trình duyệt không hỗ trợ GPS.';
    return;
  }
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      position.value = {
        latitude: pos.coords.latitude,
        longitude: pos.coords.longitude,
        accuracy: pos.coords.accuracy,
      };
      gpsReady.value = true;
    },
    (err) => {
      gpsError.value = err.code === 1
        ? 'Bạn đã từ chối quyền định vị. Vui lòng bật GPS trong cài đặt trình duyệt.'
        : 'Không lấy được vị trí GPS. Thử ra ngoài trời hoặc bật WiFi.';
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 },
  );
}

function startGpsWatch() {
  if (!navigator.geolocation) return;
  watchId = navigator.geolocation.watchPosition(
    (pos) => {
      position.value = {
        latitude: pos.coords.latitude,
        longitude: pos.coords.longitude,
        accuracy: pos.coords.accuracy,
      };
      gpsReady.value = true;
      gpsError.value = '';
    },
    () => {},
    { enableHighAccuracy: true, maximumAge: 60000 },
  );
}

async function doPunch(type) {
  if (!canPunch.value) {
    toast.show(canPunchQr.value ? 'Bật GPS hoặc quét QR cổng trước khi chấm' : 'Bật GPS trước khi chấm', 'error');
    return;
  }
  if (scannedQr.value && !canPunchQr.value) {
    toast.show('Không có quyền chấm công QR', 'error');
    return;
  }
  if (position.value && !scannedQr.value && !canPunchGps.value) {
    toast.show('Không có quyền chấm công GPS', 'error');
    return;
  }
  punching.value = type;
  try {
    const payload = {
      punch_type: type,
      source: scannedQr.value ? 'qr' : 'mobile',
    };
    if (scannedQr.value) {
      payload.zone_code = scannedQr.value.zone_code;
      payload.gate_token = scannedQr.value.gate_token;
    }
    if (position.value) {
      payload.latitude = position.value.latitude;
      payload.longitude = position.value.longitude;
      payload.accuracy_meters = position.value.accuracy ? Math.round(position.value.accuracy) : null;
    }
    const { data } = await api.post('/self-service/attendance/punch', payload);
    toast.show(data.data.message || 'Chấm công thành công');
    await loadToday();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Chấm công thất bại', 'error');
  } finally {
    punching.value = '';
  }
}

onMounted(async () => {
  try {
    await loadToday();
    refreshGps();
    startGpsWatch();
  } catch (e) {
    error.value = e.response?.data?.message || 'Không tải được dữ liệu chấm công';
  } finally {
    loading.value = false;
  }
});

onUnmounted(() => {
  if (watchId !== null && navigator.geolocation) {
    navigator.geolocation.clearWatch(watchId);
  }
  stopQrCamera();
});
</script>
