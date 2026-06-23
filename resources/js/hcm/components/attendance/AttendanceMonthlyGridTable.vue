<template>
  <div class="attendance-monthly-grid">
    <div class="grid-toolbar">
      <div>
        <h3 class="grid-title">{{ report.title || 'BẢNG CÔNG' }} — {{ report.period }}</h3>
        <p class="grid-subtitle">
          Công chuẩn tháng: <b>{{ report.standard_work_days }}</b> ngày
          <span v-if="report.summary?.phase_split_count > 0" class="phase-note">
            · {{ report.summary.phase_split_count }} NV chuyển TV→CT trong tháng
          </span>
        </p>
      </div>
      <div class="grid-legend">
        <span class="legend-item legend-official">CHÍNH THỨC</span>
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
              :style="stickyStyle(col)"
            >
              {{ col.label }}
            </th>
            <th
              v-for="col in standardColumns"
              :key="`g-std-${col.key}`"
              rowspan="2"
              :class="headClass(col, 'standard')"
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
            <th
              v-for="col in totalColumns"
              :key="`g-total-${col.key}`"
              rowspan="2"
              :class="headClass(col, 'total')"
            >
              {{ col.label }}
            </th>
            <th rowspan="2" class="head-action">Chi tiết</th>
            <th rowspan="2" class="head-action">Kỳ</th>
          </tr>
          <tr class="head-sub">
            <template v-for="(group, phaseKey) in phaseGroups" :key="`sub-wrap-${phaseKey}`">
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
            :class="{ 'row-alt': idx % 2 === 1, 'row-phase-split': row.has_phase_split }"
          >
            <td
              v-for="col in infoColumns"
              :key="`r-${row.employee_id}-${col.key}`"
              :class="bodyClass(col, 'info')"
              :style="stickyBodyStyle(col, idx)"
            >
              <template v-if="col.key === 'full_name'">
                <button type="button" class="name-link" @click="$emit('open-employee', row)">
                  {{ row.full_name }}
                </button>
                <span v-if="row.has_phase_split" class="split-tag">TV→CT</span>
              </template>
              <template v-else-if="col.key === 'employee_code'">
                <span class="font-mono text-[11px]">{{ row.employee_code }}</span>
              </template>
              <template v-else>{{ row[col.key] || '—' }}</template>
            </td>
            <td
              v-for="col in standardColumns"
              :key="`r-${row.employee_id}-std-${col.key}`"
              :class="bodyClass(col, 'standard')"
            >
              {{ formatCell(row[col.key], col) }}
            </td>
            <template v-for="(group, phaseKey) in phaseGroups" :key="`r-${row.employee_id}-${phaseKey}`">
              <td
                v-for="col in group.columns"
                :key="`${row.employee_id}-${col.key}`"
                :class="phaseBodyClass(group.theme)"
              >
                {{ formatCell(row[col.key], col) }}
              </td>
            </template>
            <td
              v-for="col in totalColumns"
              :key="`r-${row.employee_id}-total-${col.key}`"
              :class="bodyClass(col, 'total')"
            >
              {{ formatCell(row[col.key], col) }}
            </td>
            <td class="body-action">
              <button type="button" class="action-link" @click="$emit('open-breakdown', row)">OT/Nghỉ</button>
            </td>
            <td class="body-action">
              <span :class="row.is_locked ? 'badge-lock' : 'badge-draft'">
                {{ row.is_locked ? 'Khóa' : 'Nháp' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  report: { type: Object, required: true },
  rows: { type: Array, default: () => [] },
});

defineEmits(['open-employee', 'open-breakdown']);

const infoColumns = computed(() => props.report.layout?.info || []);
const standardColumns = computed(() => props.report.layout?.standard || []);
const phaseGroups = computed(() => props.report.layout?.phases || {});
const totalColumns = computed(() => props.report.layout?.totals || []);

function formatCell(val, col) {
  if (val === null || val === undefined || val === '' || val === '—') return '—';
  if (col.numeric) {
    const n = parseFloat(val);
    if (Number.isNaN(n)) return '—';
    if (col.key.includes('ot_') || col.key === 'ot_hours' || col.key === 'actual_work_hours') {
      return Number.isInteger(n) ? `${n}h` : `${n.toFixed(1)}h`;
    }
    return Number.isInteger(n) ? n.toString() : n.toFixed(1).replace(/\.0$/, '');
  }
  return val;
}

function headClass(col, zone = 'info') {
  const align = col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left';
  return `head-cell head-${zone} ${align}`;
}

function bodyClass(col, zone = 'info') {
  const align = col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left';
  const mono = col.key === 'employee_code' ? 'font-mono' : '';
  return `body-cell body-${zone} ${align} ${mono}`.trim();
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

function stickyStyle(col) {
  if (!col.sticky) return {};
  const left = stickyLeft(col.key);
  return { position: 'sticky', left: `${left}px`, zIndex: 20, minWidth: col.width ? `${col.width}px` : undefined };
}

function stickyBodyStyle(col, rowIdx) {
  if (!col.sticky) return {};
  const left = stickyLeft(col.key);
  const bg = rowIdx % 2 === 1 ? '#f8fafc' : '#ffffff';
  return { position: 'sticky', left: `${left}px`, zIndex: 10, background: bg, minWidth: col.width ? `${col.width}px` : undefined };
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
.attendance-monthly-grid {
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

.phase-note {
  color: #1d4ed8;
}

.grid-legend {
  display: flex;
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

.legend-probation {
  background: var(--tv-bg);
  color: var(--tv-text);
  border-color: var(--tv-border);
}

.legend-official {
  background: var(--ct-bg);
  color: var(--ct-text);
  border-color: var(--ct-border);
}

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
  padding: 6px 8px;
  white-space: nowrap;
}

.head-group th {
  background: #334155;
  color: #f8fafc;
  font-weight: 700;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  vertical-align: middle;
}

.head-standard,
.head-total {
  background: #475569 !important;
}

.head-phase {
  text-align: center;
  font-size: 12px;
  letter-spacing: 0.08em;
}

.head-phase-probation {
  background: #1e40af !important;
  box-shadow: inset 0 -3px 0 #93c5fd;
}

.head-phase-official {
  background: #166534 !important;
  box-shadow: inset 0 -3px 0 #86efac;
}

.head-sub th {
  font-weight: 600;
  font-size: 10px;
  padding: 5px 6px;
}

.head-sub-probation {
  background: var(--tv-bg);
  color: var(--tv-text);
}

.head-sub-official {
  background: var(--ct-bg);
  color: var(--ct-text);
}

.head-action {
  background: #475569 !important;
  text-align: center;
  min-width: 64px;
}

.body-cell {
  background: #fff;
}

.body-standard,
.body-total {
  background: #f8fafc;
  font-weight: 600;
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

.row-alt .body-cell,
.row-alt .body-standard,
.row-alt .body-total {
  background-color: #f8fafc;
}

.row-alt .body-phase-probation { background-color: #eef6ff; }
.row-alt .body-phase-official { background-color: #eefaf2; }

.name-link {
  font-weight: 600;
  color: #0f172a;
  text-align: left;
}

.name-link:hover {
  color: #2563eb;
  text-decoration: underline;
}

.split-tag {
  display: inline-block;
  margin-left: 4px;
  padding: 0 4px;
  border-radius: 4px;
  background: #dbeafe;
  color: #1d4ed8;
  font-size: 9px;
  font-weight: 700;
}

.body-action {
  text-align: center;
  background: #fff;
}

.action-link {
  color: #2563eb;
  font-size: 11px;
}

.action-link:hover { text-decoration: underline; }

.badge-lock,
.badge-draft {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
}

.badge-lock {
  background: #d1fae5;
  color: #065f46;
}

.badge-draft {
  background: #fef3c7;
  color: #92400e;
}
</style>
