<template>
  <div class="attendance-daily-grid">
    <div class="grid-toolbar">
      <div>
        <h3 class="grid-title">{{ timesheet.title || 'BẢNG CÔNG THEO NGÀY' }} — {{ timesheet.period }}</h3>
        <p class="grid-subtitle">
          Công chuẩn: <b>{{ timesheet.standard_work_days }}</b> ngày · {{ timesheet.summary?.employee_count || rows.length }} NV
          <span v-if="timesheet.summary?.phase_split_count > 0" class="phase-note">
            · {{ timesheet.summary.phase_split_count }} NV chuyển TV→CT
          </span>
        </p>
      </div>
      <div class="grid-legend">
        <span class="legend-item legend-official">CHÍNH THỨC</span>
        <span class="legend-item legend-calendar">Lịch tháng</span>
      </div>
    </div>

    <div class="grid-scroll">
      <table class="grid-table">
        <thead>
          <tr class="head-group">
            <th
              v-for="col in infoColumns"
              :key="`g-info-${col.key}`"
              rowspan="2"
              :class="headClass(col)"
              :style="stickyHeadStyle(col)"
            >
              {{ col.label }}
            </th>
            <th :colspan="days.length" class="head-calendar">LỊCH THÁNG {{ calendarLabel }}</th>
            <th
              v-for="col in summaryColumns"
              :key="`g-sum-${col.key}`"
              rowspan="2"
              class="head-summary"
            >
              {{ col.label }}
            </th>
            <th
              v-for="(group, phaseKey) in phaseGroups"
              :key="`g-phase-${phaseKey}`"
              :colspan="group.columns.length"
              :class="phaseGroupClass(group.theme)"
            >
              {{ group.label }}
            </th>
          </tr>
          <tr class="head-sub">
            <th
              v-for="d in days"
              :key="`day-${d.date}`"
              class="head-day"
              :class="dayHeadClass(d)"
              :style="dayHeaderStyle(d)"
              :title="d.holiday_name || d.weekday_label"
            >
              <span class="day-num">{{ d.day }}</span>
              <span class="day-wd">{{ d.weekday_label }}</span>
            </th>
            <template v-for="(group, phaseKey) in phaseGroups" :key="`sub-${phaseKey}`">
              <th
                v-for="col in group.columns"
                :key="`${phaseKey}-${col.key}`"
                :class="phaseSubClass(group.theme)"
                :title="col.title || col.label"
              >
                {{ col.label }}
              </th>
            </template>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, idx) in rows"
            :key="row.employee_id"
            class="grid-row"
            :class="{ 'row-alt': idx % 2 === 1 }"
          >
            <td
              v-for="col in infoColumns"
              :key="`r-${row.employee_id}-${col.key}`"
              :class="bodyClass(col)"
              :style="stickyBodyStyle(col, idx)"
            >
              <template v-if="col.key === 'full_name'">
                <button type="button" class="name-link" @click="$emit('open-employee', row)">
                  {{ row.full_name }}
                </button>
                <span v-if="row.has_phase_split" class="split-tag">TV→CT</span>
              </template>
              <template v-else-if="col.key === 'employee_code'">
                <span class="font-mono">{{ row.employee_code }}</span>
              </template>
              <template v-else-if="col.key === 'stt'">{{ row.stt }}</template>
              <template v-else>{{ row[col.key] || '—' }}</template>
            </td>
            <td
              v-for="d in days"
              :key="`${row.employee_id}-${d.date}`"
              class="body-day"
              :class="dayCellClass(row.cells[d.date])"
              :style="dayCellStyle(row.cells[d.date])"
              :title="cellTitle(row.cells[d.date])"
              @click="$emit('open-day', row, d.date)"
            >
              {{ row.cells[d.date]?.symbol || '—' }}
            </td>
            <td
              v-for="col in summaryColumns"
              :key="`r-${row.employee_id}-sum-${col.key}`"
              class="body-summary"
            >
              {{ formatTotal(row, col) }}
            </td>
            <template v-for="(group, phaseKey) in phaseGroups" :key="`r-${row.employee_id}-${phaseKey}`">
              <td
                v-for="col in group.columns"
                :key="`${row.employee_id}-${col.key}`"
                :class="phaseBodyClass(group.theme)"
              >
                {{ formatTotal(row, col) }}
              </td>
            </template>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="legendItems.length" class="grid-footer">
      <span v-for="(item, i) in legendItems" :key="i">
        <b :style="item.style">{{ item.bold }}</b> {{ item.text }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useAttendanceDisplay } from '../../composables/useAttendanceDisplay';

const props = defineProps({
  timesheet: { type: Object, required: true },
  rows: { type: Array, default: () => [] },
  legendItems: { type: Array, default: () => [] },
});

defineEmits(['open-employee', 'open-day']);

const displayConfig = computed(() => props.timesheet.display_config || null);
const { cellStyle, dayHeaderStyle, cellTitle } = useAttendanceDisplay(displayConfig);

const infoColumns = computed(() => props.timesheet.layout?.info || []);
const summaryColumns = computed(() => props.timesheet.layout?.summary || []);
const phaseGroups = computed(() => props.timesheet.layout?.phases || {});
const days = computed(() => props.timesheet.days || []);

const calendarLabel = computed(() => {
  const p = props.timesheet.period || '';
  const [y, m] = p.split('-');
  return m && y ? `T${m}/${y}` : '';
});

/** Map cột layout → key trong row.totals (chỉ cần khi config key ≠ totals key) */
const TOTAL_KEY_MAP = {
  // Phase work days (config: official_work_days → totals: official_days)
  probation_work_days: 'probation_days',
  official_work_days: 'official_days',
  // Leave
  probation_paid_leave: 'probation_paid_leave',
  official_paid_leave: 'official_paid_leave',
  probation_unpaid_leave: 'probation_unpaid_leave',
  official_unpaid_leave: 'official_unpaid_leave',
  probation_absent: 'probation_absent',
  official_absent: 'official_absent',
  present: 'present',
  // Work day types, ca đêm, OT: config key = totals key → fallback handles them
};

function resolveTotal(row, col) {
  const key = TOTAL_KEY_MAP[col.key] || col.key;
  return row.totals?.[key];
}

function formatTotal(row, col) {
  if (!col.numeric && col.key === 'present') {
    const v = row.totals?.present;
    return v > 0 ? v : '—';
  }
  const val = resolveTotal(row, col);
  if (val === null || val === undefined || val === '' || val === 0 || val === '0') {
    if (col.key.startsWith('probation_') && !row.has_phase_split) return '—';
    return val === 0 || val === '0' ? '—' : (val ?? '—');
  }
  // Cột giờ (OT, ca đêm): hiển thị Nh
  const isHours = col.key.includes('ot_') || col.key.endsWith('_hours');
  if (isHours) {
    const n = parseFloat(val);
    return Number.isNaN(n) ? '—' : (Number.isInteger(n) ? `${n}h` : `${n.toFixed(1)}h`);
  }
  const n = parseFloat(val);
  if (Number.isNaN(n)) return '—';
  return Number.isInteger(n) ? n.toString() : n.toFixed(1).replace(/\.0$/, '');
}

function headClass(col) {
  const align = col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left';
  return `head-cell ${align}`;
}

function bodyClass(col) {
  const align = col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left';
  return `body-cell body-info ${align}`;
}

function phaseGroupClass(theme) {
  return theme === 'probation' ? 'head-phase head-phase-probation' : 'head-phase head-phase-official';
}

function phaseSubClass(theme) {
  return theme === 'probation' ? 'head-sub-probation' : 'head-sub-official';
}

function phaseBodyClass(theme) {
  return theme === 'probation' ? 'body-phase body-phase-probation' : 'body-phase body-phase-official';
}

function dayHeadClass(d) {
  if (d.is_holiday) return 'head-day-holiday';
  if (d.is_weekend) return 'head-day-weekend';
  return '';
}

function dayCellStyle(cell) {
  return cellStyle(cell);
}

function dayCellClass(cell) {
  if (!cell) return '';
  if (cell.status === 'late') return 'cell-late';
  if (cell.employment_phase === 'probation') return 'cell-probation';
  if (cell.employment_phase === 'official') return 'cell-official';
  return '';
}

function stickyHeadStyle(col) {
  if (!col.sticky) return {};
  return { position: 'sticky', left: `${stickyLeft(col.key)}px`, zIndex: 25, minWidth: col.width ? `${col.width}px` : undefined };
}

function stickyBodyStyle(col, rowIdx) {
  if (!col.sticky) return {};
  const bg = rowIdx % 2 === 1 ? '#f8fafc' : '#ffffff';
  return { position: 'sticky', left: `${stickyLeft(col.key)}px`, zIndex: 15, background: bg, minWidth: col.width ? `${col.width}px` : undefined };
}

function stickyLeft(key) {
  let left = 0;
  for (const col of infoColumns.value) {
    if (col.key === key) break;
    if (col.sticky) left += col.width || 80;
  }
  return left;
}
</script>

<style scoped>
.attendance-daily-grid {
  --tv-bg: #eff6ff;
  --tv-border: #93c5fd;
  --tv-text: #1d4ed8;
  --ct-bg: #f0fdf4;
  --ct-border: #86efac;
  --ct-text: #15803d;
}

.grid-toolbar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.grid-title {
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: #0f172a;
}

.grid-subtitle {
  margin-top: 4px;
  font-size: 12px;
  color: #64748b;
}

.phase-note { color: #1d4ed8; }

.grid-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.legend-item {
  font-size: 11px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid transparent;
}

.legend-probation { background: var(--tv-bg); color: var(--tv-text); border-color: var(--tv-border); }
.legend-official { background: var(--ct-bg); color: var(--ct-text); border-color: var(--ct-border); }
.legend-calendar { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }

.grid-scroll {
  overflow: auto;
  max-height: 72vh;
}

.grid-table {
  width: max-content;
  min-width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 11px;
}

.grid-table th,
.grid-table td {
  border: 1px solid #dbe3ee;
  padding: 4px 5px;
  white-space: nowrap;
}

.head-group th {
  background: #334155;
  color: #f8fafc;
  font-weight: 700;
  font-size: 10px;
  text-transform: uppercase;
  vertical-align: middle;
}

.head-calendar {
  text-align: center;
  background: #475569 !important;
  letter-spacing: 0.06em;
  font-size: 11px;
}

.head-summary {
  background: #64748b !important;
  text-align: center;
  min-width: 48px;
}

.head-phase-probation {
  background: #1e40af !important;
  text-align: center;
  letter-spacing: 0.08em;
  box-shadow: inset 0 -3px 0 #93c5fd;
}

.head-phase-official {
  background: #166534 !important;
  text-align: center;
  letter-spacing: 0.08em;
  box-shadow: inset 0 -3px 0 #86efac;
}

.head-day {
  min-width: 26px;
  text-align: center;
  background: #f8fafc;
  color: #64748b;
  padding: 3px 2px;
  vertical-align: bottom;
}

.head-day-holiday { background: #faf5ff; color: #7e22ce; }
.head-day-weekend { background: #f1f5f9; color: #94a3b8; }

.day-num { display: block; font-weight: 700; font-size: 10px; line-height: 1.1; }
.day-wd { display: block; font-size: 8px; opacity: 0.75; line-height: 1; }

.head-sub-probation { background: var(--tv-bg); color: var(--tv-text); font-size: 9px; font-weight: 600; }
.head-sub-official { background: var(--ct-bg); color: var(--ct-text); font-size: 9px; font-weight: 600; }

.body-cell { background: #fff; }
.body-day {
  text-align: center;
  cursor: pointer;
  font-weight: 600;
  font-size: 10px;
  min-width: 26px;
  padding: 3px 2px;
}

.body-day:hover {
  outline: 2px solid #2563eb;
  outline-offset: -2px;
  z-index: 1;
}

.body-summary {
  text-align: center;
  font-weight: 700;
  background: #f1f5f9;
  font-variant-numeric: tabular-nums;
}

.body-phase-probation {
  background: #f8fbff;
  text-align: center;
  font-variant-numeric: tabular-nums;
}

.body-phase-official {
  background: #f7fdf9;
  text-align: center;
  font-variant-numeric: tabular-nums;
}

.row-alt .body-cell { background: #f8fafc; }
.row-alt .body-day { background: #f8fafc; }
.row-alt .body-summary { background: #e2e8f0; }
.row-alt .body-phase-probation { background: #eef6ff; }
.row-alt .body-phase-official { background: #eefaf2; }

.name-link {
  font-weight: 600;
  color: #0f172a;
  text-align: left;
}

.name-link:hover { color: #2563eb; text-decoration: underline; }

.split-tag {
  display: inline-block;
  margin-left: 4px;
  padding: 0 4px;
  border-radius: 4px;
  background: #dbeafe;
  color: #1d4ed8;
  font-size: 8px;
  font-weight: 700;
}

.grid-footer {
  border-top: 1px solid #e2e8f0;
  padding: 12px 20px;
  display: flex;
  flex-wrap: wrap;
  gap: 12px 20px;
  font-size: 11px;
  color: #64748b;
  background: #fafbfc;
}
</style>
