import { computed, unref } from 'vue';

const FALLBACK = {
  cell_statuses: {},
  employment_phases: {},
  day_headers: {},
  totals_columns: {},
  legend_footer: {},
};

function pickStatus(cell, config) {
  return config.cell_statuses?.[cell?.status] || null;
}

function pickPhase(cell, config) {
  if (!cell?.employment_phase) return null;
  return config.employment_phases?.[cell.employment_phase] || null;
}

function styleFrom(entry, keys = ['bg_color', 'text_color']) {
  if (!entry) return {};
  const style = {};
  if (keys.includes('bg_color') && entry.bg_color && entry.bg_color !== 'transparent') {
    style.backgroundColor = entry.bg_color;
  }
  if (keys.includes('text_color') && entry.text_color) {
    style.color = entry.text_color;
  }
  if (entry.bold) {
    style.fontWeight = '600';
  }
  return style;
}

export function useAttendanceDisplay(configSource) {
  const config = computed(() => {
    const raw = unref(configSource);
    return raw && typeof raw === 'object' ? raw : FALLBACK;
  });

  function cellStyle(cell) {
    if (!cell) return {};

    const phase = pickPhase(cell, config.value);
    const status = pickStatus(cell, config.value);

    if (phase && (cell.status === 'present' || cell.status === 'late')) {
      if (cell.status === 'late') {
        return {
          ...styleFrom({
            bg_color: phase.late_bg_color,
            text_color: phase.late_text_color,
            bold: true,
          }),
          boxShadow: phase.late_border_color ? `inset 0 0 0 1px ${phase.late_border_color}` : undefined,
        };
      }

      return styleFrom(phase);
    }

    return styleFrom(status);
  }

  function dayHeaderStyle(day) {
    if (!day) return {};
    if (day.is_holiday) return styleFrom(config.value.day_headers?.holiday, ['bg_color', 'text_color']);
    if (day.is_weekend) return styleFrom(config.value.day_headers?.weekend, ['bg_color', 'text_color']);
    return {};
  }

  function totalsHeaderStyle(key) {
    const col = config.value.totals_columns?.[key];
    if (!col) return {};
    return styleFrom(col);
  }

  function totalsCellStyle(key) {
    const col = config.value.totals_columns?.[key];
    if (!col) return {};
    return styleFrom(col, ['text_color']);
  }

  function phaseStyle(phaseKey) {
    return styleFrom(config.value.employment_phases?.[phaseKey]);
  }

  function phaseTextStyle(phaseKey) {
    return styleFrom(config.value.employment_phases?.[phaseKey], ['text_color']);
  }

  function phaseBadgeVariant(phaseKey) {
    return config.value.employment_phases?.[phaseKey]?.badge_variant || 'default';
  }

  function phaseLabel(phaseKey) {
    return config.value.employment_phases?.[phaseKey]?.label || phaseKey;
  }

  function phaseShortLabel(phaseKey) {
    return config.value.employment_phases?.[phaseKey]?.short_label || phaseKey?.toUpperCase?.() || '';
  }

  function cellTitle(cell) {
    if (!cell) return '';
    let title = cell.label || '';
    const phase = pickPhase(cell, config.value);

    if (phase?.title_prefix && (cell.status === 'present' || cell.status === 'late' || cell.employment_phase)) {
      title = `${phase.title_prefix} ${title}`;
    }

    if (cell.work_hours) title += ` · ${cell.work_hours}h`;
    if (cell.late_minutes) title += ` · trễ ${Math.round(cell.late_minutes)}p`;
    if (cell.ot_hours) title += ` · OT ${cell.ot_hours}h`;

    return title;
  }

  const legendSubtitle = computed(() => {
    const probation = config.value.employment_phases?.probation;
    const official = config.value.employment_phases?.official;
    if (!probation || !official) return '';

    return [
      `Ô`,
      `<span style="color:${probation.text_color};font-weight:600">${probation.legend_color_name || probation.label}</span>`,
      `= ${probation.label.toLowerCase()}`,
      `·`,
      `<span style="color:${official.text_color};font-weight:600">${official.legend_color_name || official.label}</span>`,
      `= ${official.label.toLowerCase()}`,
    ].join(' ');
  });

  const footerLegendItems = computed(() => {
    const items = [];
    const phases = config.value.employment_phases || {};
    ['probation', 'official'].forEach((key) => {
      const phase = phases[key];
      if (!phase) return;
      items.push({
        bold: phase.short_label,
        text: phase.footer_text || phase.label,
        style: { color: phase.text_color, fontWeight: '700' },
      });
    });

    Object.entries(config.value.legend_footer || {}).forEach(([, item]) => {
      if (!item?.bold_label && !item?.text) return;
      items.push({
        bold: item.bold_label || '',
        text: item.text || '',
        style: { color: item.text_color || '#64748b', fontWeight: '700' },
      });
    });

    return items;
  });

  function detailRowStyle(day) {
    if (day?.employment_phase === 'probation' && day?.status === 'present') {
      const phase = config.value.employment_phases?.probation;
      if (!phase?.bg_color) return {};
      return { backgroundColor: `${phase.bg_color}66` };
    }
    return {};
  }

  return {
    config,
    cellStyle,
    dayHeaderStyle,
    totalsHeaderStyle,
    totalsCellStyle,
    phaseStyle,
    phaseTextStyle,
    phaseBadgeVariant,
    phaseLabel,
    phaseShortLabel,
    cellTitle,
    legendSubtitle,
    footerLegendItems,
    detailRowStyle,
  };
}

export default useAttendanceDisplay;
