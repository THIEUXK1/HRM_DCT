<template>
  <div class="attendance-phased-grid">
    <div class="grid-toolbar">
      <div>
        <h3 class="grid-title">{{ report.title || 'BẢNG CÔNG TV / CT GIAI ĐOẠN' }} — {{ report.period }}</h3>
        <p class="grid-subtitle">
          Công chuẩn tháng: <b>{{ report.standard_work_days }}</b> ngày
          <span v-if="report.summary?.split_employees > 0" class="phase-note">
            · {{ report.summary.split_employees }} NV chuyển TV→CT trong tháng
          </span>
          <span v-if="report.summary?.total_lines"> · {{ report.summary.total_lines }} dòng</span>
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
              :key="`info-${col.key}`"
              rowspan="2"
              :class="headClass(col)"
              :style="stickyHeadStyle(col)"
            >
              {{ col.label }}
            </th>
            <th :colspan="metricColumns.length" class="head-metrics">CHỈ TIÊU CÔNG &amp; OT</th>
            <th rowspan="2" class="head-action">Chi tiết</th>
          </tr>
          <tr class="head-sub">
            <th
              v-for="col in metricColumns"
              :key="`metric-${col.key}`"
              class="head-metric"
              :title="col.title || col.label"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, idx) in rows"
            :key="`${row.employee_id}-${row.phase}-${idx}`"
            class="grid-row"
            :class="rowClass(row, idx)"
          >
            <td
              v-for="col in infoColumns"
              :key="`r-${row.employee_id}-${row.phase}-${col.key}`"
              :class="bodyInfoClass(col, row)"
              :style="stickyBodyStyle(col, idx, row)"
            >
              <template v-if="col.key === 'full_name'">
                <button type="button" class="name-link" @click="$emit('open-employee', row)">
                  {{ row.full_name }}
                </button>
                <span v-if="row.has_phase_split && isFirstPhaseLine(row, idx)" class="split-tag">TV→CT</span>
              </template>
              <template v-else-if="col.key === 'employee_code'">
                <span class="font-mono">{{ row.employee_code }}</span>
              </template>
              <template v-else-if="col.key === 'phase_label'">
                <span class="phase-badge" :class="row.phase === 'probation' ? 'phase-badge-tv' : 'phase-badge-ct'">
                  {{ row.phase_label }}
                </span>
              </template>
              <template v-else-if="col.key === 'date_range'">
                {{ row.date_range || `${row.from_date} → ${row.to_date}` }}
              </template>
              <template v-else-if="col.key === 'stt'">{{ row.stt }}</template>
              <template v-else>{{ row[col.key] ?? '—' }}</template>
            </td>
            <td
              v-for="col in metricColumns"
              :key="`r-${row.employee_id}-${row.phase}-m-${col.key}`"
              :class="bodyMetricClass(col, row)"
            >
              {{ formatCell(row[col.key], col) }}
            </td>
            <td class="body-action">
              <button type="button" class="action-link" @click="$emit('open-breakdown', row)">OT/Nghỉ</button>
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
const metricColumns = computed(() => props.report.layout?.metrics || []);

function formatCell(val, col) {
  if (val === null || val === undefined || val === '' || val === 0 || val === '0') {
    if (col.numeric && (val === 0 || val === '0')) return '0';
    if (col.numeric) return '—';
    return val ?? '—';
  }
  if (col.numeric) {
    const n = parseFloat(val);
    if (Number.isNaN(n)) return '—';
    if (col.key.includes('ot_') || col.key === 'ot_hours') {
      return Number.isInteger(n) ? `${n}h` : `${n.toFixed(1)}h`;
    }
    return Number.isInteger(n) ? n.toString() : n.toFixed(1).replace(/\.0$/, '');
  }
  return val;
}

function headClass(col) {
  const align = col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left';
  return `head-cell head-info ${align}`;
}

function bodyInfoClass(col, row) {
  const align = col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left';
  const mono = col.key === 'employee_code' ? 'font-mono' : '';
  const phase = row.phase === 'probation' ? 'info-tv' : 'info-ct';
  return `body-cell body-info ${phase} ${align} ${mono}`.trim();
}

function bodyMetricClass(col, row) {
  return row.phase === 'probation' ? 'body-metric body-metric-tv' : 'body-metric body-metric-ct';
}

function rowClass(row, idx) {
  const prev = props.rows[idx - 1];
  const newEmployee = !prev || prev.employee_id !== row.employee_id;
  return {
    'row-new-employee': newEmployee && idx > 0,
    'row-tv': row.phase === 'probation',
    'row-ct': row.phase === 'official',
  };
}

function isFirstPhaseLine(row, idx) {
  const prev = props.rows[idx - 1];
  return !prev || prev.employee_id !== row.employee_id;
}

function stickyHeadStyle(col) {
  if (!col.sticky) return {};
  return {
    position: 'sticky',
    left: `${stickyLeft(col.key)}px`,
    zIndex: 20,
    minWidth: col.width ? `${col.width}px` : undefined,
  };
}

function stickyBodyStyle(col, idx, row) {
  if (!col.sticky) return {};
  const bg = row.phase === 'probation' ? '#f8fbff' : '#f7fdf9';
  return {
    position: 'sticky',
    left: `${stickyLeft(col.key)}px`,
    zIndex: 10,
    background: bg,
    minWidth: col.width ? `${col.width}px` : undefined,
  };
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
.attendance-phased-grid {
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

.head-info { background: #334155 !important; }

.head-metrics {
  text-align: center;
  background: #475569 !important;
  letter-spacing: 0.06em;
}

.head-sub th {
  padding: 5px 6px;
  font-weight: 600;
  font-size: 10px;
}

.head-metric {
  background: #64748b;
  color: #f8fafc;
  text-align: center;
}

.head-action {
  background: #475569 !important;
  text-align: center;
  min-width: 72px;
}

.body-cell {
  font-variant-numeric: tabular-nums;
}

.info-tv { background: #f8fbff; }
.info-ct { background: #f7fdf9; }

.body-metric {
  text-align: center;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.body-metric-tv { background: #eef6ff; color: #1e3a8a; }
.body-metric-ct { background: #eefaf2; color: #14532d; }

.row-new-employee td { border-top: 2px solid #94a3b8; }

.phase-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
}

.phase-badge-tv {
  background: var(--tv-bg);
  color: var(--tv-text);
  border: 1px solid var(--tv-border);
}

.phase-badge-ct {
  background: var(--ct-bg);
  color: var(--ct-text);
  border: 1px solid var(--ct-border);
}

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
</style>
