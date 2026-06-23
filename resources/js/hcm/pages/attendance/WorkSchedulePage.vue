<template>
  <div>
    <UiPageHeader
      title="Ca làm việc theo nhóm"
      subtitle="Khối sản xuất · Phi sản xuất · 5D8H / 6D8H · Cảnh báo tuân thủ"
      breadcrumb="Chấm công"
    >
      <template #actions>
        <button type="button" class="hcm-btn-secondary text-sm" @click="seedDefaults">Khởi tạo mẫu mặc định</button>
      </template>
    </UiPageHeader>

    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
        :class="tab === t.key ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500'"
        @click="switchTab(t.key)"
      >
        {{ t.icon }} {{ t.label }}
      </button>
    </div>

    <!-- Nhóm -->
    <template v-if="tab === 'groups'">
      <div v-if="loading" class="py-12 text-center text-slate-400">Đang tải...</div>
      <div v-else class="grid gap-4 lg:grid-cols-2">
        <div class="hcm-card p-5">
          <h3 class="font-semibold mb-3">Thêm / sửa nhóm</h3>
          <form class="space-y-3" @submit.prevent="saveGroup">
            <input v-model="groupForm.code" class="hcm-input w-full" placeholder="Mã nhóm (SX, HC…)" required />
            <input v-model="groupForm.name" class="hcm-input w-full" placeholder="Tên nhóm" required />
            <select v-model="groupForm.group_type" class="hcm-input w-full" required>
              <option v-for="(label, key) in config.group_types" :key="key" :value="key">{{ label }}</option>
            </select>
            <textarea v-model="groupForm.description" class="hcm-input w-full" rows="2" placeholder="Mô tả" />
            <button type="submit" class="hcm-btn-primary w-full" :disabled="saving">{{ saving ? 'Đang lưu…' : 'Lưu nhóm' }}</button>
          </form>
        </div>
        <div class="hcm-card overflow-hidden">
          <table v-if="groups.length" class="hcm-table w-full text-sm">
            <thead>
              <tr>
                <th>Mã</th>
                <th>Tên</th>
                <th>Loại</th>
                <th>Mẫu ca</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="g in groups" :key="g.id">
                <td class="font-mono">{{ g.code }}</td>
                <td>{{ g.name }}</td>
                <td><UiBadge :variant="g.group_type === 'production' ? 'warning' : 'default'">{{ groupTypeLabel(g.group_type) }}</UiBadge></td>
                <td>{{ g.patterns_count ?? 0 }}</td>
                <td><button type="button" class="text-primary-600 text-sm" @click="editGroup(g)">Sửa</button></td>
              </tr>
            </tbody>
          </table>
          <UiEmpty v-else title="Chưa có nhóm" subtitle="Bấm « Khởi tạo mẫu mặc định » hoặc tạo nhóm mới." />
        </div>
      </div>
    </template>

    <!-- Mẫu ca -->
    <template v-else-if="tab === 'patterns'">
      <div v-if="loading" class="py-12 text-center text-slate-400">Đang tải...</div>
      <div v-else class="space-y-4">
        <div class="hcm-card p-5">
          <h3 class="font-semibold mb-3">Tạo mẫu ca</h3>
          <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="savePattern">
            <select v-model="patternForm.work_schedule_group_id" class="hcm-input" required>
              <option value="">— Chọn nhóm —</option>
              <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
            <select v-model="patternForm.pattern_code" class="hcm-input" @change="applyPreset">
              <option value="5D8H">5D8H — T2–T6 × 8h</option>
              <option value="6D8H">6D8H — T2–T7 × 8h</option>
              <option value="CUSTOM">Tùy chỉnh</option>
            </select>
            <input v-model="patternForm.code" class="hcm-input" placeholder="Mã mẫu" required />
            <input v-model="patternForm.name" class="hcm-input" placeholder="Tên hiển thị" required />
            <input v-model.number="patternForm.hours_per_day" type="number" min="1" max="12" class="hcm-input" placeholder="Giờ/ngày" />
            <input v-model.number="patternForm.max_consecutive_work_days" type="number" min="1" max="30" class="hcm-input" placeholder="Tối đa ngày liên tục" />
            <label class="flex items-center gap-2 text-sm sm:col-span-2">
              <input v-model="patternForm.allow_weekend_swap" type="checkbox" class="rounded" />
              Cho phép hoán đổi T7 nghỉ / CN đi làm (khối SX)
            </label>
            <label class="flex items-center gap-2 text-sm sm:col-span-2">
              <input v-model="patternForm.allow_continuous" type="checkbox" class="rounded" />
              Ca liên tục (sản xuất)
            </label>
            <div class="sm:col-span-2">
              <p class="text-xs text-slate-500 mb-1">Ngày làm việc (1=T2 … 7=CN)</p>
              <div class="flex flex-wrap gap-2">
                <label v-for="d in weekdayOptions" :key="d.value" class="flex items-center gap-1 text-sm">
                  <input type="checkbox" :checked="patternForm.work_days.includes(d.value)" @change="toggleWorkDay(d.value)" />
                  {{ d.label }}
                </label>
              </div>
            </div>
            <button type="submit" class="hcm-btn-primary sm:col-span-2" :disabled="saving">{{ saving ? 'Đang lưu…' : 'Lưu mẫu ca' }}</button>
          </form>
        </div>
        <div class="hcm-card overflow-x-auto">
          <table v-if="patterns.length" class="hcm-table w-full text-sm">
            <thead>
              <tr>
                <th>Mã</th>
                <th>Tên</th>
                <th>Nhóm</th>
                <th>Loại</th>
                <th>Giờ/ngày</th>
                <th>Liên tục tối đa</th>
                <th>Hoán đổi T7/CN</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in patterns" :key="p.id">
                <td class="font-mono">{{ p.code }}</td>
                <td>{{ p.name }}</td>
                <td>{{ p.group?.name || '—' }}</td>
                <td>{{ p.pattern_code }}</td>
                <td>{{ p.hours_per_day }}h</td>
                <td>{{ p.max_consecutive_work_days }} ngày</td>
                <td>{{ p.allow_weekend_swap ? 'Có' : 'Không' }}</td>
              </tr>
            </tbody>
          </table>
          <UiEmpty v-else title="Chưa có mẫu ca" />
        </div>
      </div>
    </template>

    <!-- Gán NV -->
    <template v-else-if="tab === 'assignments'">
      <div class="hcm-card p-5 mb-4">
        <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="saveAssignment">
          <select v-model="assignForm.employee_id" class="hcm-input" required>
            <option value="">— Chọn nhân viên —</option>
            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.employee_code }} — {{ e.full_name }}</option>
          </select>
          <select v-model="assignForm.work_schedule_group_id" class="hcm-input" required @change="onAssignGroupChange">
            <option value="">— Nhóm —</option>
            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
          <select v-model="assignForm.work_schedule_pattern_id" class="hcm-input" required>
            <option value="">— Mẫu ca —</option>
            <option v-for="p in patternsForAssign" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <input v-model="assignForm.effective_from" type="date" class="hcm-input" required />
          <input v-model="assignForm.effective_to" type="date" class="hcm-input" />
          <label class="flex items-center gap-2 text-sm">
            <input v-model="assignForm.weekend_swap_enabled" type="checkbox" class="rounded" />
            Bật hoán đổi T7/CN
          </label>
          <button type="submit" class="hcm-btn-primary lg:col-span-3" :disabled="saving">{{ saving ? 'Đang lưu…' : 'Gán ca cho NV' }}</button>
        </form>
      </div>
      <div v-if="loading" class="py-12 text-center text-slate-400">Đang tải...</div>
      <div v-else class="hcm-card overflow-x-auto">
        <table v-if="assignments.length" class="hcm-table w-full text-sm">
          <thead>
            <tr>
              <th>Nhân viên</th>
              <th>Nhóm</th>
              <th>Mẫu ca</th>
              <th>Từ ngày</th>
              <th>Đến ngày</th>
              <th>T7/CN</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in assignments" :key="a.id">
              <td>
                <p class="font-medium">{{ a.employee?.full_name }}</p>
                <p class="text-xs text-slate-400 font-mono">{{ a.employee?.employee_code }}</p>
              </td>
              <td>{{ a.group?.name }}</td>
              <td>{{ a.pattern?.name }} ({{ a.pattern?.pattern_code }})</td>
              <td>{{ a.effective_from }}</td>
              <td>{{ a.effective_to || '—' }}</td>
              <td>{{ a.weekend_swap_enabled ? 'Có' : 'Không' }}</td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Chưa gán ca" subtitle="Chọn nhân viên và mẫu ca phía trên." />
      </div>
    </template>

    <!-- Gán hàng loạt theo phòng ban & nhân viên -->
    <template v-else-if="tab === 'bulk'">
      <div class="hcm-card p-5 mb-4">
        <h3 class="font-semibold mb-3">Gán ca hàng loạt cho phòng ban & nhân viên</h3>
        <form class="space-y-4" @submit.prevent="saveBulkAssign">
          <div class="grid gap-4 sm:grid-cols-2">
            <!-- Chọn phòng ban (Multi-select) -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Chọn phòng ban</label>
              <div class="border border-slate-200 rounded-lg p-3 bg-white">
                <div class="flex items-center gap-2 mb-2">
                  <input
                    type="text"
                    v-model="deptSearch"
                    placeholder="Tìm phòng ban..."
                    class="hcm-input w-full text-xs"
                  />
                  <button
                    type="button"
                    @click="toggleAllDepts"
                    class="text-xs text-primary-600 font-semibold hover:underline whitespace-nowrap"
                  >
                    {{ isAllDeptsSelected ? 'Bỏ chọn hết' : 'Chọn hết' }}
                  </button>
                </div>
                <div class="max-h-40 overflow-y-auto space-y-1.5 pr-1 text-sm">
                  <label
                    v-for="d in filteredDepartments"
                    :key="d.id"
                    class="flex items-center gap-2 px-1 py-0.5 rounded hover:bg-slate-50 cursor-pointer"
                  >
                    <input
                      type="checkbox"
                      :value="d.id"
                      v-model="bulkForm.department_ids"
                      class="rounded text-primary-600"
                    />
                    <span class="truncate" :title="d.name">{{ d.name }}</span>
                  </label>
                  <div v-if="filteredDepartments.length === 0" class="text-xs text-slate-400 text-center py-2">
                    Không tìm thấy phòng ban
                  </div>
                </div>
                <div class="mt-2 pt-2 border-t border-slate-100 text-xs text-slate-500 font-medium">
                  Đã chọn: <span class="text-slate-800 font-bold">{{ bulkForm.department_ids.length }}</span> phòng ban
                </div>
              </div>
            </div>

            <!-- Chọn nhân viên lẻ (Multi-select) -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Chọn nhân viên lẻ (tùy chọn)</label>
              <div class="border border-slate-200 rounded-lg p-3 bg-white">
                <div class="flex items-center gap-2 mb-2">
                  <input
                    type="text"
                    v-model="empSearch"
                    placeholder="Tìm nhân viên..."
                    class="hcm-input w-full text-xs"
                  />
                  <button
                    type="button"
                    @click="toggleAllEmps"
                    class="text-xs text-primary-600 font-semibold hover:underline whitespace-nowrap"
                  >
                    {{ isAllEmpsSelected ? 'Bỏ chọn hết' : 'Chọn hết' }}
                  </button>
                </div>
                <div class="max-h-40 overflow-y-auto space-y-1.5 pr-1 text-sm">
                  <label
                    v-for="e in filteredEmployees"
                    :key="e.id"
                    class="flex items-center gap-2 px-1 py-0.5 rounded hover:bg-slate-50 cursor-pointer"
                  >
                    <input
                      type="checkbox"
                      :value="e.id"
                      v-model="bulkForm.employee_ids"
                      class="rounded text-primary-600"
                    />
                    <span class="font-mono text-xs text-slate-400">{{ e.employee_code }}</span>
                    <span class="truncate">{{ e.full_name }}</span>
                  </label>
                  <div v-if="filteredEmployees.length === 0" class="text-xs text-slate-400 text-center py-2">
                    Không tìm thấy nhân viên
                  </div>
                </div>
                <div class="mt-2 pt-2 border-t border-slate-100 text-xs text-slate-500 font-medium">
                  Đã chọn: <span class="text-slate-800 font-bold">{{ bulkForm.employee_ids.length }}</span> nhân viên
                </div>
              </div>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <select v-model="bulkForm.work_schedule_group_id" class="hcm-input" required @change="bulkForm.work_schedule_pattern_id = ''">
              <option value="">— Nhóm ca làm việc —</option>
              <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
            <select v-model="bulkForm.work_schedule_pattern_id" class="hcm-input" required>
              <option value="">— Mẫu ca làm việc —</option>
              <option v-for="p in patternsForBulk" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[10px] text-slate-400 uppercase font-bold">Từ ngày</label>
                <input v-model="bulkForm.effective_from" type="date" class="hcm-input w-full mt-0.5" required />
              </div>
              <div>
                <label class="block text-[10px] text-slate-400 uppercase font-bold">Đến ngày (tùy chọn)</label>
                <input v-model="bulkForm.effective_to" type="date" class="hcm-input w-full mt-0.5" />
              </div>
            </div>
            <div class="flex items-center sm:col-span-2 lg:col-span-3 gap-4">
              <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                <input v-model="bulkForm.weekend_swap_enabled" type="checkbox" class="rounded" />
                Bật hoán đổi T7/CN
              </label>
            </div>
          </div>

          <button type="submit" class="hcm-btn-primary w-full" :disabled="saving">
            {{ saving ? 'Đang gán…' : 'Gán ca cho danh sách đã chọn' }}
          </button>
        </form>
        <div v-if="bulkResult" class="mt-4 text-sm text-slate-700 p-3 bg-slate-50 border border-slate-200 rounded-lg">
          <p>Kết quả gán ca: Đã gán: <b class="text-green-600">{{ bulkResult.assigned }}</b> · Bỏ qua: <b class="text-amber-600">{{ bulkResult.skipped }}</b></p>
          <div v-if="bulkResult.errors?.length" class="mt-2">
            <p class="font-medium text-xs text-rose-700">Chi tiết lý do bỏ qua:</p>
            <ul class="list-disc pl-5 text-xs text-slate-600 mt-1 space-y-0.5">
              <li v-for="(err, i) in bulkResult.errors.slice(0, 15)" :key="i">{{ err }}</li>
            </ul>
          </div>
        </div>
      </div>
    </template>

    <!-- Hoán đổi T7/CN theo tuần -->
    <template v-else-if="tab === 'week_swap'">
      <div class="hcm-card p-5 mb-4">
        <h3 class="font-semibold mb-3">Hoán đổi T7/CN theo tuần (khối SX)</h3>
        <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="saveWeekOverride">
          <select v-model="weekForm.employee_id" class="hcm-input" required>
            <option value="">— Chọn nhân viên —</option>
            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.employee_code }} — {{ e.full_name }}</option>
          </select>
          <input v-model="weekForm.week_start" type="date" class="hcm-input" required />
          <label class="flex items-center gap-2 text-sm">
            <input v-model="weekForm.swap_enabled" type="checkbox" class="rounded" />
            Bật hoán đổi tuần này
          </label>
          <select v-model.number="weekForm.swap_rest_day" class="hcm-input">
            <option v-for="d in weekdayOptions" :key="'r'+d.value" :value="d.value">Nghỉ: {{ d.label }}</option>
          </select>
          <select v-model.number="weekForm.swap_work_day" class="hcm-input">
            <option v-for="d in weekdayOptions" :key="'w'+d.value" :value="d.value">Đi làm: {{ d.label }}</option>
          </select>
          <input v-model="weekForm.notes" class="hcm-input lg:col-span-2" placeholder="Ghi chú (tuỳ chọn)" />
          <button type="submit" class="hcm-btn-primary" :disabled="saving">{{ saving ? 'Đang lưu…' : 'Lưu hoán đổi tuần' }}</button>
        </form>
      </div>
      <div v-if="weekLoading" class="py-12 text-center text-slate-400">Đang tải...</div>
      <div v-else class="hcm-card overflow-x-auto">
        <table v-if="weekOverrides.length" class="hcm-table w-full text-sm">
          <thead>
            <tr>
              <th>Nhân viên</th>
              <th>Tuần (T2)</th>
              <th>Trạng thái</th>
              <th>Nghỉ</th>
              <th>Đi làm</th>
              <th>Ghi chú</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="w in weekOverrides" :key="w.id">
              <td>{{ w.employee?.full_name }} ({{ w.employee?.employee_code }})</td>
              <td>{{ w.week_start }}</td>
              <td>{{ w.swap_enabled ? 'Bật' : 'Tắt' }}</td>
              <td>T{{ w.swap_rest_day === 7 ? 'CN' : w.swap_rest_day + 1 }}</td>
              <td>T{{ w.swap_work_day === 7 ? 'CN' : w.swap_work_day + 1 }}</td>
              <td class="text-xs text-slate-500">{{ w.notes || '—' }}</td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Chưa có hoán đổi tuần" subtitle="Thêm hoán đổi T7/CN cho nhân viên sản xuất." />
      </div>
    </template>

    <!-- Cảnh báo -->
    <template v-else-if="tab === 'alerts'">
      <div class="mb-4 flex flex-wrap items-end gap-3">
        <div>
          <label class="text-sm font-medium">Kỳ kiểm tra</label>
          <input v-model="alertPeriod" type="month" class="hcm-input mt-1" @change="loadAlerts" />
        </div>
        <button type="button" class="hcm-btn-secondary text-sm" @click="loadAlerts">Tải lại</button>
      </div>
      <div v-if="alertLoading" class="py-12 text-center text-slate-400">Đang quét cảnh báo...</div>
      <div v-else class="space-y-4">
        <div class="hcm-card overflow-x-auto">
          <h3 class="font-semibold px-5 pt-4">Cảnh báo tuân thủ ({{ alerts.length }})</h3>
          <table v-if="alerts.length" class="hcm-table w-full text-sm mt-2">
            <thead>
              <tr>
                <th>NV</th>
                <th>Loại</th>
                <th>Nội dung</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(a, i) in alerts" :key="i">
                <td class="whitespace-nowrap">
                  <span class="font-mono text-xs">{{ a.employee_code }}</span>
                  <span class="block text-slate-600">{{ a.full_name }}</span>
                </td>
                <td><UiBadge :variant="a.severity === 'warning' ? 'warning' : 'default'">{{ alertTypeLabel(a.type) }}</UiBadge></td>
                <td class="text-slate-700">{{ a.message }}</td>
              </tr>
            </tbody>
          </table>
          <UiEmpty v-else title="Không có cảnh báo" subtitle="Tổng hợp công trước hoặc chọn kỳ khác." class="py-8" />
        </div>
        <div class="hcm-card overflow-x-auto">
          <h3 class="font-semibold px-5 pt-4">OT vượt mức — tách khỏi lương ({{ excessRecords.length }})</h3>
          <table v-if="excessRecords.length" class="hcm-table w-full text-sm mt-2">
            <thead>
              <tr>
                <th>NV</th>
                <th>Ngày</th>
                <th>Loại vượt</th>
                <th class="text-right">Giờ vượt</th>
                <th>Ghi chú</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in excessRecords" :key="r.id">
                <td>{{ r.employee?.full_name }} ({{ r.employee?.employee_code }})</td>
                <td>{{ r.work_date }}</td>
                <td>{{ r.cap_type }}</td>
                <td class="text-right font-semibold text-rose-700">{{ r.excess_hours }}h</td>
                <td class="text-xs text-slate-500">{{ r.notes }}</td>
              </tr>
            </tbody>
          </table>
          <UiEmpty v-else title="Không có OT vượt mức" class="py-8" />
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import { extractItems } from '../../composables/usePagination';
import { useToast } from '../../composables/useToast';

const toast = useToast();

const tabs = [
  { key: 'groups', label: 'Nhóm làm việc', icon: '🏭' },
  { key: 'patterns', label: 'Mẫu ca', icon: '📅' },
  { key: 'assignments', label: 'Gán nhân viên', icon: '👤' },
  { key: 'bulk', label: 'Gán hàng loạt', icon: '👥' },
  { key: 'week_swap', label: 'Hoán đổi tuần', icon: '🔄' },
  { key: 'alerts', label: 'Cảnh báo', icon: '⚠️' },
];

const tab = ref('groups');
const loading = ref(false);
const saving = ref(false);
const config = ref({ group_types: {}, pattern_presets: {}, alert_types: {} });
const groups = ref([]);
const patterns = ref([]);
const assignments = ref([]);
const departments = ref([]);
const employees = ref([]);
const weekOverrides = ref([]);
const bulkResult = ref(null);
const weekLoading = ref(false);
const alerts = ref([]);
const excessRecords = ref([]);
const alertPeriod = ref(new Date().toISOString().slice(0, 7));
const alertLoading = ref(false);

const weekdayOptions = [
  { value: 1, label: 'T2' }, { value: 2, label: 'T3' }, { value: 3, label: 'T4' },
  { value: 4, label: 'T5' }, { value: 5, label: 'T6' }, { value: 6, label: 'T7' }, { value: 7, label: 'CN' },
];

const groupForm = ref({ id: null, code: '', name: '', group_type: 'non_production', description: '' });
const patternForm = ref({
  id: null,
  work_schedule_group_id: '',
  code: '',
  name: '',
  pattern_code: '5D8H',
  hours_per_day: 8,
  work_days: [1, 2, 3, 4, 5],
  rest_days: [6, 7],
  allow_weekend_swap: false,
  allow_continuous: false,
  max_consecutive_work_days: 13,
});
const assignForm = ref({
  employee_id: '',
  work_schedule_group_id: '',
  work_schedule_pattern_id: '',
  effective_from: new Date().toISOString().slice(0, 10),
  effective_to: '',
  weekend_swap_enabled: false,
});
const bulkForm = ref({
  department_ids: [],
  employee_ids: [],
  work_schedule_group_id: '',
  work_schedule_pattern_id: '',
  effective_from: new Date().toISOString().slice(0, 10),
  effective_to: '',
  weekend_swap_enabled: false,
});

const deptSearch = ref('');
const empSearch = ref('');

const filteredDepartments = computed(() => {
  const q = deptSearch.value.trim().toLowerCase();
  if (!q) return departments.value;
  return departments.value.filter(d => d.name.toLowerCase().includes(q));
});

const filteredEmployees = computed(() => {
  const q = empSearch.value.trim().toLowerCase();
  if (!q) return employees.value;
  return employees.value.filter(e => 
    e.full_name.toLowerCase().includes(q) || 
    e.employee_code.toLowerCase().includes(q)
  );
});

const isAllDeptsSelected = computed(() => {
  const filteredIds = filteredDepartments.value.map(d => d.id);
  if (filteredIds.length === 0) return false;
  return filteredIds.every(id => bulkForm.value.department_ids.includes(id));
});

const isAllEmpsSelected = computed(() => {
  const filteredIds = filteredEmployees.value.map(e => e.id);
  if (filteredIds.length === 0) return false;
  return filteredIds.every(id => bulkForm.value.employee_ids.includes(id));
});

function toggleAllDepts() {
  const filteredIds = filteredDepartments.value.map(d => d.id);
  if (isAllDeptsSelected.value) {
    bulkForm.value.department_ids = bulkForm.value.department_ids.filter(id => !filteredIds.includes(id));
  } else {
    const newIds = [...bulkForm.value.department_ids];
    filteredIds.forEach(id => {
      if (!newIds.includes(id)) newIds.push(id);
    });
    bulkForm.value.department_ids = newIds;
  }
}

function toggleAllEmps() {
  const filteredIds = filteredEmployees.value.map(e => e.id);
  if (isAllEmpsSelected.value) {
    bulkForm.value.employee_ids = bulkForm.value.employee_ids.filter(id => !filteredIds.includes(id));
  } else {
    const newIds = [...bulkForm.value.employee_ids];
    filteredIds.forEach(id => {
      if (!newIds.includes(id)) newIds.push(id);
    });
    bulkForm.value.employee_ids = newIds;
  }
}
const weekForm = ref({
  employee_id: '',
  week_start: new Date().toISOString().slice(0, 10),
  swap_enabled: true,
  swap_rest_day: 6,
  swap_work_day: 7,
  notes: '',
});

const patternsForAssign = computed(() => {
  if (!assignForm.value.work_schedule_group_id) return patterns.value;
  return patterns.value.filter((p) => p.work_schedule_group_id === Number(assignForm.value.work_schedule_group_id));
});

const patternsForBulk = computed(() => {
  if (!bulkForm.value.work_schedule_group_id) return patterns.value;
  return patterns.value.filter((p) => p.work_schedule_group_id === Number(bulkForm.value.work_schedule_group_id));
});

function groupTypeLabel(t) {
  return config.value.group_types?.[t] || t;
}

function alertTypeLabel(t) {
  return config.value.alert_types?.[t] || t;
}

function switchTab(key) {
  tab.value = key;
  if (key === 'groups') loadGroups();
  if (key === 'patterns') { loadGroups(); loadPatterns(); }
  if (key === 'assignments') { loadGroups(); loadPatterns(); loadAssignments(); loadEmployees(); }
  if (key === 'bulk') { loadGroups(); loadPatterns(); loadDepartments(); loadEmployees(); }
  if (key === 'week_swap') { loadEmployees(); loadWeekOverrides(); }
  if (key === 'alerts') loadAlerts();
}

async function loadConfig() {
  const { data } = await api.get('/work-schedules/config');
  config.value = data.data || {};
}

async function loadGroups() {
  loading.value = true;
  try {
    const { data } = await api.get('/work-schedules/groups');
    groups.value = data.data || [];
  } finally {
    loading.value = false;
  }
}

async function loadPatterns() {
  const { data } = await api.get('/work-schedules/patterns');
  patterns.value = data.data || [];
}

async function loadAssignments() {
  loading.value = true;
  try {
    const { data } = await api.get('/work-schedules/assignments');
    assignments.value = extractItems(data);
  } finally {
    loading.value = false;
  }
}

async function loadEmployees() {
  const { data } = await api.get('/employees', { params: { per_page: 3000 } });
  employees.value = extractItems(data);
}

async function loadDepartments() {
  const { data } = await api.get('/departments');
  departments.value = extractItems(data);
}

async function loadWeekOverrides() {
  weekLoading.value = true;
  try {
    const { data } = await api.get('/work-schedules/week-overrides');
    weekOverrides.value = data.data || [];
  } finally {
    weekLoading.value = false;
  }
}

async function loadAlerts() {
  alertLoading.value = true;
  try {
    const [aRes, eRes] = await Promise.all([
      api.get('/work-schedules/compliance-alerts', { params: { period: alertPeriod.value } }),
      api.get('/work-schedules/overtime-excess', { params: { period: alertPeriod.value } }),
    ]);
    alerts.value = aRes.data.data?.alerts || [];
    excessRecords.value = eRes.data.data?.records || [];
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không tải được cảnh báo', 'error');
  } finally {
    alertLoading.value = false;
  }
}

async function seedDefaults() {
  try {
    const { data } = await api.post('/work-schedules/seed-defaults');
    toast.show('Đã khởi tạo nhóm & mẫu ca mặc định');
    groups.value = data.data?.groups || [];
    await loadPatterns();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Khởi tạo thất bại', 'error');
  }
}

function editGroup(g) {
  groupForm.value = { id: g.id, code: g.code, name: g.name, group_type: g.group_type, description: g.description || '' };
}

async function saveGroup() {
  saving.value = true;
  try {
    const payload = { ...groupForm.value };
    delete payload.id;
    if (groupForm.value.id) {
      await api.put(`/work-schedules/groups/${groupForm.value.id}`, payload);
    } else {
      await api.post('/work-schedules/groups', payload);
    }
    toast.show('Đã lưu nhóm');
    groupForm.value = { id: null, code: '', name: '', group_type: 'non_production', description: '' };
    await loadGroups();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lưu thất bại', 'error');
  } finally {
    saving.value = false;
  }
}

function applyPreset() {
  const preset = config.value.pattern_presets?.[patternForm.value.pattern_code];
  if (!preset) return;
  patternForm.value.name = preset.name;
  patternForm.value.hours_per_day = preset.hours_per_day;
  patternForm.value.work_days = [...preset.work_days];
  patternForm.value.rest_days = [...(preset.rest_days || [])];
  patternForm.value.allow_weekend_swap = preset.allow_weekend_swap;
  patternForm.value.allow_continuous = preset.allow_continuous;
}

function toggleWorkDay(day) {
  const days = patternForm.value.work_days;
  const idx = days.indexOf(day);
  if (idx >= 0) days.splice(idx, 1);
  else days.push(day);
  days.sort((a, b) => a - b);
}

async function savePattern() {
  saving.value = true;
  try {
    await api.post('/work-schedules/patterns', { ...patternForm.value });
    toast.show('Đã lưu mẫu ca');
    await loadPatterns();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lưu thất bại', 'error');
  } finally {
    saving.value = false;
  }
}

function onAssignGroupChange() {
  assignForm.value.work_schedule_pattern_id = '';
  const g = groups.value.find((x) => x.id === Number(assignForm.value.work_schedule_group_id));
  assignForm.value.weekend_swap_enabled = g?.group_type === 'production';
}

async function saveAssignment() {
  saving.value = true;
  try {
    const payload = { ...assignForm.value };
    if (!payload.effective_to) delete payload.effective_to;
    await api.post('/work-schedules/assignments', payload);
    toast.show('Đã gán ca cho nhân viên');
    await loadAssignments();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Gán ca thất bại', 'error');
  } finally {
    saving.value = false;
  }
}

async function saveBulkAssign() {
  saving.value = true;
  bulkResult.value = null;
  try {
    const payload = {
      department_ids: bulkForm.value.department_ids,
      employee_ids: bulkForm.value.employee_ids,
      shift_id: bulkForm.value.work_schedule_pattern_id ? Number(bulkForm.value.work_schedule_pattern_id) : null,
      work_schedule_group_id: bulkForm.value.work_schedule_group_id ? Number(bulkForm.value.work_schedule_group_id) : null,
      work_schedule_pattern_id: bulkForm.value.work_schedule_pattern_id ? Number(bulkForm.value.work_schedule_pattern_id) : null,
      start_date: bulkForm.value.effective_from,
      effective_from: bulkForm.value.effective_from,
      end_date: bulkForm.value.effective_to || null,
      effective_to: bulkForm.value.effective_to || null,
      swap_weekend: bulkForm.value.weekend_swap_enabled,
      weekend_swap_enabled: bulkForm.value.weekend_swap_enabled,
    };
    if (!payload.end_date) {
      delete payload.end_date;
      delete payload.effective_to;
    }
    const { data } = await api.post('/work-schedules/assignments/bulk', payload);
    bulkResult.value = data.data || {};
    toast.show(`Đã gán ca cho ${bulkResult.value.assigned || 0} nhân viên`);
  } catch (e) {
    toast.show(e.response?.data?.message || 'Gán hàng loạt thất bại', 'error');
  } finally {
    saving.value = false;
  }
}

async function saveWeekOverride() {
  saving.value = true;
  try {
    const payload = { ...weekForm.value };
    if (!payload.notes) delete payload.notes;
    await api.post('/work-schedules/week-overrides', payload);
    toast.show('Đã lưu hoán đổi tuần');
    weekForm.value.notes = '';
    await loadWeekOverrides();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lưu thất bại', 'error');
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  await loadConfig();
  await loadGroups();
});
</script>
