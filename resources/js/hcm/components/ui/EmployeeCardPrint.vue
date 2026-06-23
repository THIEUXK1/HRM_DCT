<template>
  <div>
    <button type="button" class="hcm-btn-secondary" :disabled="loading" @click="openPrint">
      {{ loading ? 'Đang chuẩn bị...' : 'In thẻ' }}
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import JsBarcode from 'jsbarcode';
import api from '../../api/client';
import { useAppStore } from '../../stores/app';

const props = defineProps({
  employees: { type: Array, default: () => [] },
});

const appStore = useAppStore();

const loading = ref(false);

async function fetchPhotoDataUrl(employeeId) {
  try {
    const res = await api.get(`/employees/${employeeId}/photo`, { responseType: 'blob' });
    return await blobToDataUrl(res.data);
  } catch {
    return null;
  }
}

function blobToDataUrl(blob) {
  return new Promise((resolve) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => resolve(null);
    reader.readAsDataURL(blob);
  });
}

function generateBarcodeSvg(value) {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  try {
    JsBarcode(svg, value, {
      format: 'CODE128',
      width: 1.2,
      height: 28,
      displayValue: false,
      margin: 0,
    });
    return svg.outerHTML;
  } catch {
    return '';
  }
}

function formatDate(value) {
  if (!value) return '—';
  return new Date(value).toISOString().slice(0, 10);
}

function buildCardHtml(emp, photoDataUrl, companyName) {
  const barcodeSvg = generateBarcodeSvg(emp.employee_code || 'N/A');
  const photoHtml = photoDataUrl
    ? `<img src="${photoDataUrl}" style="width:28mm;height:35mm;object-fit:cover;border:1px solid #ccc;" />`
    : `<div style="width:28mm;height:35mm;background:#e5e7eb;border:1px solid #ccc;display:flex;align-items:center;justify-content:center;font-size:8px;color:#6b7280;">No photo</div>`;

  return `
    <div class="card">
      <div class="card-top">${companyName}</div>
      <div class="card-photo">${photoHtml}</div>
      <div class="card-info">
        <div class="info-row"><span class="info-label">姓名：</span><span class="info-value">${emp.full_name || ''}</span></div>
        <div class="info-row"><span class="info-label">部门：</span><span class="info-value">${emp.department?.name || '—'}</span></div>
        <div class="info-row"><span class="info-label">工号：</span><span class="info-value">${emp.employee_code || '—'}</span></div>
        <div class="info-row"><span class="info-label">日期：</span><span class="info-value">${formatDate(emp.hire_date)}</span></div>
      </div>
      <div class="card-barcode">${barcodeSvg}</div>
    </div>
  `;
}

function buildPrintDocument(cardsHtml) {
  return `<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8"/>
  <title>Thẻ nhân viên</title>
  <style>
    @page { size: 54mm 86mm portrait; margin: 0; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; background: #f3f4f6; }
    .cards-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 6mm;
      padding: 6mm;
    }
    .card {
      width: 54mm;
      height: 86mm;
      border: 1px solid #b0b0b0;
      border-radius: 3px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      align-items: center;
      page-break-inside: avoid;
      background: #fff;
    }
    .card-top {
      width: 100%;
      background: #1e3a5f;
      text-align: center;
      font-size: 9pt;
      font-weight: bold;
      letter-spacing: 1.5px;
      color: #fff;
      padding: 3.5mm 2mm;
      flex-shrink: 0;
    }
    .card-photo {
      margin-top: 3mm;
      flex-shrink: 0;
    }
    .card-info {
      width: 100%;
      padding: 3mm 4mm 0;
      flex: 1;
    }
    .info-row {
      font-size: 7pt;
      margin-bottom: 1.8mm;
      display: flex;
      align-items: baseline;
      line-height: 1.3;
    }
    .info-label {
      font-weight: bold;
      color: #1e3a5f;
      min-width: 10mm;
      flex-shrink: 0;
    }
    .info-value {
      color: #111;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .card-barcode {
      width: 100%;
      padding: 1mm 3mm 2.5mm;
      display: flex;
      justify-content: center;
      flex-shrink: 0;
    }
    .card-barcode svg { width: 100%; height: auto; }
    @media print {
      body { background: #fff; }
      .cards-grid { gap: 0; padding: 0; }
      .card { border: none; }
    }
  </style>
</head>
<body>
  <div class="cards-grid">${cardsHtml}</div>
  <script>window.onload = function(){ window.print(); window.onafterprint = function(){ window.close(); }; }<\/script>
</body>
</html>`;
}

async function openPrint() {
  if (!props.employees.length) return;
  loading.value = true;
  try {
    const companyName = appStore.currentCompany?.name || 'BEST PACIFIC';
    const cards = await Promise.all(
      props.employees.map(async (emp) => {
        const photo = await fetchPhotoDataUrl(emp.id);
        return buildCardHtml(emp, photo, companyName);
      })
    );
    const html = buildPrintDocument(cards.join(''));
    const win = window.open('', '_blank', 'width=900,height=700');
    if (win) {
      win.document.write(html);
      win.document.close();
    }
  } finally {
    loading.value = false;
  }
}
</script>
