<template>

  <div>

    <UiPageHeader title="Bảng lương" subtitle="Công thức tùy chỉnh · Thôi việc · Thưởng năng suất" breadcrumb="Payroll">

      <template #actions>

        <button v-if="mainTab === 'cycles'" type="button" class="hcm-btn-secondary" @click="openCreateCycleModal">+ Tạo kỳ lương</button>

      </template>

    </UiPageHeader>



    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">

      <button

        v-for="t in mainTabs"

        :key="t.key"

        type="button"

        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"

        :class="mainTab === t.key ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500'"

        @click="switchMainTab(t.key)"

      >

        {{ t.icon }} {{ t.label }}

      </button>

    </div>



    <template v-if="mainTab === 'cycles'">

      <div class="mb-3 flex flex-wrap gap-2 items-center">
        <button

          type="button"

          class="text-xs px-3 py-1 rounded-full border"

          :class="employeeFilter === 'all' ? 'bg-primary-50 border-primary-300 text-primary-800' : 'border-slate-200'"

          @click="employeeFilter = 'all'"

        >Tất cả NV</button>

        <button

          type="button"

          class="text-xs px-3 py-1 rounded-full border"

          :class="employeeFilter === 'terminated' ? 'bg-orange-50 border-orange-300 text-orange-800' : 'border-slate-200'"

          @click="employeeFilter = 'terminated'"

        >Thôi việc trong tháng</button>

        <UiSearchInput
          v-model="payrollSearch"
          placeholder="Tìm theo tên hoặc mã NV..."
          input-class="hcm-input text-sm max-w-xs"
        />
      </div>



      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <div class="lg:col-span-1 space-y-3">

          <div

            v-for="c in cycles"

            :key="c.id"

            class="hcm-card p-4 cursor-pointer transition-shadow hover:shadow-md"

            :class="selected?.id === c.id ? 'ring-2 ring-primary-500' : ''"

            @click="selectCycle(c)"

          >

            <div class="flex justify-between items-start">

              <div>

                <p class="font-bold text-lg">{{ cycleDisplayLabel(c) }}</p>

                <p class="text-xs text-slate-500">{{ date(c.start_date) }} — {{ date(c.end_date) }}</p>

                <p v-if="c.revision_note" class="text-xs text-amber-800 mt-0.5">{{ c.revision_note }}</p>

              </div>

              <UiBadge :variant="statusVariant(c.status)">{{ statusLabel(c.status) }}</UiBadge>

            </div>

            <div class="mt-3 flex flex-wrap gap-2" @click.stop>

              <button type="button" class="hcm-btn-secondary text-xs py-1 px-2" :disabled="isAttendanceLocked(c.period)" @click="lockAttendance(c.period)">🔒 Khóa công</button>

              <button v-if="isAdmin && isAttendanceLocked(c.period)" type="button" class="hcm-btn-secondary text-xs py-1 px-2 text-rose-700" @click="unlockAttendance(c.period)">Mở công</button>

              <button type="button" class="hcm-btn-primary text-xs py-1 px-2" :disabled="!isAttendanceLocked(c.period) || c.status === 'locked' || c.status === 'approved'" @click="calculate(c.id)">Tính lương</button>

              <button v-if="c.status === 'calculated'" type="button" class="hcm-btn-secondary text-xs py-1 px-2" @click="lockPayroll(c.id)">🔒 Khóa lương</button>

              <button v-if="c.status === 'locked' && isAdmin" type="button" class="hcm-btn-secondary text-xs py-1 px-2 text-rose-700" @click="unlockPayroll(c.id)">Mở lương</button>

              <button v-if="c.results_count > 0 && c.status !== 'locked'" type="button" class="hcm-btn-secondary text-xs py-1 px-2" @click="publishPayslips(c.id)">Phát hành payslip</button>

              <button v-if="c.results_count > 0" type="button" class="hcm-btn-secondary text-xs py-1 px-2" @click="exportXlsx(c.id)">↓ XLSX</button>

            </div>

          </div>

          <UiEmpty v-if="!cycles.length" title="Chưa có kỳ lương" />

        </div>



        <div class="lg:col-span-2 hcm-card overflow-hidden" v-if="selected">

          <div class="border-b border-slate-100 bg-slate-50 px-5 py-4">

            <h3 class="font-semibold">Chi tiết {{ selected ? cycleDisplayLabel(selected) : '' }}</h3>

            <p class="text-sm text-slate-500">{{ filteredResults.length }} dòng · Gross = lương cơ bản + công thức</p>

          </div>

          <div class="overflow-x-auto">

            <table class="hcm-table w-full text-sm" v-if="filteredResults.length">

              <thead>

                <tr>

                  <th>Nhân viên</th>

                  <th class="text-right">TV / CT</th>

                  <th class="text-right">OT</th>

                  <th class="text-right">Thưởng CC</th>

                  <th class="text-right">Thưởng NS</th>

                  <th class="text-right">Gross</th>

                  <th class="text-right">Thực lĩnh</th>

                  <th></th>

                </tr>

              </thead>

              <tbody>

                <tr v-for="r in filteredResults" :key="r.id" class="hover:bg-slate-50">

                  <td class="font-medium">

                    {{ r.employee?.full_name }}

                    <UiBadge v-if="r.breakdown?.is_terminated_in_month" variant="warning" class="ml-1 text-[10px]">Thôi việc</UiBadge>

                  </td>

                  <td class="text-right text-xs">

                    <div v-if="r.breakdown?.has_phase_split" class="text-[10px] text-slate-500 mb-0.5">
                      <span class="text-blue-700">{{ r.breakdown?.probation_work_days ?? 0 }}</span>
                      /
                      <span class="text-green-700">{{ r.breakdown?.official_work_days ?? 0 }}</span>
                      công
                    </div>

                    <span class="text-blue-700">{{ money(r.breakdown?.probation_base_pay || 0) }}</span>

                    /

                    <span class="text-green-700">{{ money(r.breakdown?.official_base_pay || 0) }}</span>

                  </td>

                  <td class="text-right text-xs">
                    <div v-if="r.breakdown?.has_phase_split" class="text-[10px] text-slate-500 mb-0.5">
                      <span class="text-blue-700">{{ Number(r.breakdown?.ot_probation_hours ?? 0).toFixed(1) }}</span>
                      /
                      <span class="text-green-700">{{ Number(r.breakdown?.ot_official_hours ?? 0).toFixed(1) }}</span>
                      h
                    </div>
                    <template v-if="r.breakdown?.has_phase_split">
                      <span class="text-blue-700">{{ money(r.breakdown?.ot_probation_pay || 0) }}</span>
                      /
                      <span class="text-green-700">{{ money(r.breakdown?.ot_official_pay || 0) }}</span>
                    </template>
                    <span v-else>{{ money(r.breakdown?.ot_pay || 0) }}</span>
                  </td>

                  <td class="text-right text-xs">
                    <template v-if="r.breakdown?.has_phase_split && (r.breakdown?.diligence_probation_pay || r.breakdown?.diligence_official_pay)">
                      <div class="text-[10px] text-slate-500 mb-0.5">TV / CT</div>
                      <span class="text-blue-700">{{ money(r.breakdown?.diligence_probation_pay || 0) }}</span>
                      /
                      <span class="text-green-700">{{ money(r.breakdown?.diligence_official_pay || 0) }}</span>
                    </template>
                    <span v-else class="text-amber-700">{{ money(r.breakdown?.diligence_bonus_pay || 0) }}</span>
                  </td>

                  <td class="text-right text-xs">
                    <template v-if="r.breakdown?.performance_bonus_split">
                      <div class="text-[10px] text-slate-500 mb-0.5">TV / CT</div>
                      <span class="text-blue-700">{{ money(r.breakdown?.performance_bonus_probation || 0) }}</span>
                      /
                      <span class="text-green-700">{{ money(r.breakdown?.performance_bonus_official || 0) }}</span>
                    </template>
                    <span v-else class="text-purple-700">{{ money(r.breakdown?.performance_bonus || 0) }}</span>
                  </td>

                  <td class="text-right font-medium">{{ money(r.gross_salary) }}</td>

                  <td class="text-right font-bold text-emerald-700">{{ money(r.net_salary) }}</td>

                  <td>

                    <button type="button" class="text-xs text-primary-600 hover:underline" @click="viewPayslip(r.id)">Phiếu lương</button>

                  </td>

                </tr>

              </tbody>

            </table>

            <UiEmpty v-else title="Chưa tính lương" description="Khóa công rồi bấm Tính lương" />

          </div>

        </div>

        <div v-else class="lg:col-span-2 hcm-card flex items-center justify-center p-12 text-slate-400">

          Chọn một kỳ lương

        </div>

      </div>

    </template>



    <template v-else-if="mainTab === 'allowances'">
      <div v-if="allowancePayrollLocked" class="mb-4 hcm-card p-4 bg-amber-50 border border-amber-200 text-amber-900 text-sm">
        Kỳ lương {{ allowancePeriod }} đã khóa — không thể sửa trợ cấp. Chỉ admin mới được mở khóa lương.
      </div>
      <div class="mb-4 flex flex-wrap items-end gap-3">
        <div>
          <label class="text-sm font-medium">Kỳ trợ cấp</label>
          <input v-model="allowancePeriod" type="month" class="hcm-input mt-1" @change="loadAllowances" />
        </div>
        <button type="button" class="hcm-btn-secondary text-sm" @click="loadAllowances">Tải lại</button>
        <button type="button" class="hcm-btn-secondary text-sm" @click="copyAllowancesFromPrevious">Sao chép tháng trước</button>
      </div>

      <div v-if="allowanceLoading" class="py-12 text-center text-slate-400">Đang tải...</div>
      <div v-else class="space-y-4">
        <p class="text-xs text-slate-500">
          Cột <b class="text-blue-700">TV</b> / <b class="text-green-700">CT</b> = số tiền tính lương sau khi áp dụng quy chế từng loại phụ cấp (ăn ca theo ngày công, CV từ ngày CT…).
        </p>
        <div class="hcm-card overflow-x-auto">
        <table class="hcm-table w-full text-sm" v-if="allowanceRows.length">
          <thead>
            <tr>
              <th>Nhân viên</th>
              <th>Phòng ban</th>
              <th class="text-right">Công TV/CT</th>
              <th class="text-right">CV (tháng)</th>
              <th class="text-right">CV TV/CT</th>
              <th class="text-right">Ăn ca (tháng)</th>
              <th class="text-right">Ăn ca TV/CT</th>
              <th class="text-right">Nhà trọ</th>
              <th class="text-right">BH TV</th>
              <th class="text-right">Tổng tính lương</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in allowanceRows" :key="row.employee_id">
              <td>
                <p class="font-medium">{{ row.full_name }}</p>
                <p class="text-xs text-slate-400 font-mono">{{ row.employee_code }}</p>
              </td>
              <td class="text-slate-500">{{ row.department || '—' }}</td>
              <td class="text-right text-xs">
                <template v-if="row.phased_preview?.has_phase_split">
                  <span class="text-blue-700">{{ row.phased_preview.probation_work_days }}</span>
                  /
                  <span class="text-green-700">{{ row.phased_preview.official_work_days }}</span>
                </template>
                <span v-else class="text-slate-400">—</span>
              </td>
              <td class="text-right">{{ money(row.allowances?.allowance_position || 0) }}</td>
              <td class="text-right text-xs">
                <template v-if="phasedItem(row, 'allowance_position')">
                  <span class="text-blue-700">{{ money(phasedItem(row, 'allowance_position').probation) }}</span>
                  /
                  <span class="text-green-700">{{ money(phasedItem(row, 'allowance_position').official) }}</span>
                </template>
                <span v-else class="text-slate-400">—</span>
              </td>
              <td class="text-right">{{ money(row.allowances?.allowance_meal || 0) }}</td>
              <td class="text-right text-xs">
                <template v-if="phasedItem(row, 'allowance_meal')">
                  <span class="text-blue-700">{{ money(phasedItem(row, 'allowance_meal').probation) }}</span>
                  /
                  <span class="text-green-700">{{ money(phasedItem(row, 'allowance_meal').official) }}</span>
                </template>
                <span v-else class="text-slate-400">—</span>
              </td>
              <td class="text-right">{{ money(row.allowances?.allowance_housing_distance || 0) }}</td>
              <td class="text-right">{{ money(row.allowances?.allowance_probation_insurance || 0) }}</td>
              <td class="text-right font-semibold">{{ money(row.phased_preview?.payroll_totals?.taxable ?? row.total_allowances) }}</td>
              <td>
                <button type="button" class="text-primary-600 text-sm" :disabled="allowancePayrollLocked" @click="openAllowanceModal(row)">Sửa</button>
              </td>
            </tr>
          </tbody>
        </table>
        <UiEmpty v-else title="Chưa có nhân viên" subtitle="Thêm nhân viên hoặc chọn kỳ khác." />
        </div>
      </div>

      <UiModal v-model="showAllowanceModal" :title="`Trợ cấp tháng ${allowancePeriod} — ${allowanceForm.full_name}`" wide>
        <form class="space-y-4" @submit.prevent="saveAllowance">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[420px] overflow-y-auto pr-1">
            <div v-for="(meta, code) in allowanceCatalog" :key="code">
              <label class="text-xs text-slate-600">{{ meta.label }}</label>
              <input
                v-model.number="allowanceForm.allowances[code]"
                type="number"
                min="0"
                step="1000"
                class="hcm-input w-full mt-1"
              />
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 border-t border-slate-100 pt-3">
            <div>
              <label class="text-xs text-slate-600">Hỗ trợ đi lại (VND)</label>
              <input v-model.number="allowanceForm.travel_support_amount" type="number" min="0" class="hcm-input w-full mt-1" />
            </div>
            <div>
              <label class="text-xs text-slate-600">Điều chỉnh tháng trước (VND)</label>
              <input v-model.number="allowanceForm.prev_month_adjustment" type="number" step="1000" class="hcm-input w-full mt-1" />
            </div>
            <label class="flex items-end gap-2 text-sm pb-2">
              <input v-model="allowanceForm.travel_eligible" type="checkbox" class="rounded" />
              Được hỗ trợ đi lại
            </label>
          </div>
          <textarea v-model="allowanceForm.notes" class="hcm-input w-full" rows="2" placeholder="Ghi chú" />
          <div
            v-if="allowanceForm.phased_preview?.has_phase_split"
            class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 space-y-2"
          >
            <p class="font-medium text-slate-700">
              Dự kiến tách TV/CT — công TV {{ allowanceForm.phased_preview.probation_work_days }}
              · công CT {{ allowanceForm.phased_preview.official_work_days }}
            </p>
            <div
              v-for="(item, code) in allowanceForm.phased_preview.items"
              :key="code"
              class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-2 first:border-t-0 first:pt-0"
            >
              <span>{{ allowanceCatalog[code]?.label || code }}</span>
              <span>
                <span class="text-blue-700">TV {{ money(item.probation) }}</span>
                <span class="mx-1">·</span>
                <span class="text-green-700">CT {{ money(item.official) }}</span>
              </span>
            </div>
          </div>
          <button type="submit" class="hcm-btn-primary w-full" :disabled="allowanceSaving">
            {{ allowanceSaving ? 'Đang lưu...' : 'Lưu trợ cấp' }}
          </button>
        </form>
      </UiModal>
    </template>

    <template v-else-if="mainTab === 'import'">
      <div class="hcm-card p-5 max-w-xl">
        <h3 class="font-semibold mb-2">Import Excel công & lương</h3>
        <p class="text-sm text-slate-500 mb-4">
          File mẫu gồm 2 sheet « công » và « lương ». Hệ thống map theo mã thẻ (cột B).
          Công đã khóa sẽ bị bỏ qua.
        </p>
        <form class="space-y-4" @submit.prevent="submitImport">
          <div>
            <label class="text-sm font-medium">Kỳ áp dụng</label>
            <input v-model="importPeriod" type="month" class="hcm-input mt-1 w-full" required />
          </div>
          <div>
            <label class="text-sm font-medium">File Excel (.xlsx)</label>
            <input
              type="file"
              accept=".xlsx"
              class="mt-1 block w-full text-sm"
              @change="onImportFileChange"
            />
          </div>
          <button type="submit" class="hcm-btn-primary" :disabled="importSaving || !importFile">
            {{ importSaving ? 'Đang import...' : 'Import dữ liệu' }}
          </button>
        </form>
        <div v-if="importResult" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
          <p><strong>Sheet công:</strong> {{ importResult.cong?.imported || 0 }} dòng, bỏ qua {{ importResult.cong?.skipped || 0 }}</p>
          <p><strong>Sheet lương:</strong> {{ importResult.luong?.imported || 0 }} dòng, bỏ qua {{ importResult.luong?.skipped || 0 }}</p>
          <ul v-if="importErrors.length" class="mt-2 list-disc pl-5 text-amber-800">
            <li v-for="(err, i) in importErrors.slice(0, 8)" :key="i">{{ err }}</li>
            <li v-if="importErrors.length > 8">… và {{ importErrors.length - 8 }} lỗi khác</li>
          </ul>
        </div>
      </div>
    </template>

    <template v-else-if="mainTab === 'formulas'">

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        <div class="hcm-card p-5">

          <h3 class="font-semibold mb-1">Tham số công thức (chỉnh được)</h3>

          <p class="text-xs text-slate-500 mb-3">Lưu vào cấu hình công ty — không cần sửa code. Dùng trong chuỗi công thức dạng <code class="text-primary-700">{tên_biến}</code>.</p>

          <form class="space-y-3 max-h-[320px] overflow-y-auto pr-1" @submit.prevent="saveFormulaParameters">

            <div v-for="param in formulaParameters" :key="param.key" class="border-b border-slate-100 pb-2 last:border-0">

              <label v-if="param.type === 'boolean'" class="flex items-start gap-2 text-sm cursor-pointer">

                <input

                  v-model="formulaParameterValues[param.key]"

                  type="checkbox"

                  true-value="1"

                  false-value="0"

                  class="rounded mt-0.5"

                />

                <span>

                  <span class="font-medium">{{ param.label }}</span>

                  <span v-if="param.description" class="block text-xs text-slate-500">{{ param.description }}</span>

                </span>

              </label>

              <template v-else>

                <label class="text-xs font-medium text-slate-700">{{ param.label }}</label>

                <p v-if="param.description" class="text-xs text-slate-500 mb-1">{{ param.description }}</p>

                <input

                  v-model="formulaParameterValues[param.key]"

                  type="number"

                  :step="param.step ?? (param.type === 'rate' ? 0.01 : 1)"

                  :min="param.min ?? undefined"

                  :max="param.max ?? undefined"

                  class="hcm-input w-full text-sm"

                />

                <p v-if="param.formula_key" class="text-[10px] text-primary-600 mt-0.5 font-mono">{<!-- -->{{ param.formula_key }}<!-- -->}</p>

              </template>

            </div>

            <button type="submit" class="hcm-btn-primary text-sm w-full" :disabled="formulaParamsSaving">

              {{ formulaParamsSaving ? 'Đang lưu…' : 'Lưu tham số' }}

            </button>

          </form>

        </div>

        <div class="hcm-card p-5">

          <div class="flex justify-between items-center mb-2">

            <h3 class="font-semibold">Biến tùy chỉnh</h3>

            <button type="button" class="hcm-btn-primary text-xs" @click="openCustomVarModal()">+ Thêm</button>

          </div>

          <p class="text-xs text-slate-500 mb-3">Hằng số theo công ty (phụ cấp cố định, hệ số điều chỉnh…). Ví dụ: <code class="text-primary-700">{phu_cap_an}</code></p>

          <div v-if="formulaCustomVars.length === 0" class="text-xs text-slate-400 py-4 text-center border border-dashed rounded-lg">Chưa có biến tùy chỉnh</div>

          <ul v-else class="space-y-2 max-h-[200px] overflow-y-auto text-sm">

            <li

              v-for="v in formulaCustomVars"

              :key="v.id"

              class="flex items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2"

            >

              <div class="min-w-0">

                <code class="text-primary-700 text-xs">{<!-- -->{{ v.code }}<!-- -->}</code>

                <span class="text-slate-600 ml-1">{{ v.label }}</span>

                <span class="block text-xs font-medium text-slate-800">{{ money(v.value) }}</span>

              </div>

              <div class="shrink-0 space-x-2">

                <button type="button" class="text-primary-600 text-xs" @click="openCustomVarModal(v)">Sửa</button>

                <button type="button" class="text-red-600 text-xs" @click="deleteCustomVar(v.id)">Xóa</button>

              </div>

            </li>

          </ul>

        </div>

      </div>

      <div class="hcm-card p-5 mb-6">

        <h3 class="font-semibold mb-2">Biến hệ thống (tự tính — chỉ tham khảo)</h3>

        <p class="text-xs text-slate-500 mb-2">Lấy từ bảng công, hợp đồng, KPI. Muốn đổi mức chuyên cần → Cài đặt / Chấm công; muốn đổi tỷ lệ KPI → Tham số bên trên.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1 max-h-36 overflow-y-auto text-xs">

          <div v-for="(hint, key) in formulaComputedVars" :key="key" class="py-0.5">

            <code class="text-slate-600">{<!-- -->{{ key }}<!-- -->}</code>

            <span class="text-slate-500 ml-1">{{ hint }}</span>

          </div>

        </div>

        <div v-if="Object.keys(formulaVariables).length" class="mt-3 pt-3 border-t border-slate-100">

          <p class="text-xs font-medium text-slate-600 mb-1">Tất cả biến dùng được trong công thức</p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 max-h-28 overflow-y-auto text-xs">

            <div v-for="(hint, key) in formulaVariables" :key="'all-'+key" class="py-0.5">

              <code class="text-primary-700">{<!-- -->{{ key }}<!-- -->}</code>

              <span class="text-slate-500 ml-1">{{ hint }}</span>

            </div>

          </div>

        </div>

      </div>



      <div class="flex justify-between mb-3">

        <h3 class="font-semibold">Công thức lương</h3>

        <button type="button" class="hcm-btn-primary text-sm" @click="openFormulaModal()">+ Thêm</button>

      </div>

      <div class="hcm-card overflow-hidden">

        <table class="hcm-table w-full text-sm">

          <thead>

            <tr>

              <th>Mã</th>

              <th>Tên</th>

              <th>Áp dụng</th>

              <th>Công thức</th>

              <th></th>

            </tr>

          </thead>

          <tbody>

            <tr v-for="f in formulaRules" :key="f.id">

              <td class="font-mono text-xs">{{ f.code }}</td>

              <td>{{ f.name }}</td>

              <td class="text-xs">{{ applyWhenLabel(f.apply_when) }}</td>

              <td class="font-mono text-xs truncate max-w-[220px]" :title="f.formula">{{ f.formula }}</td>

              <td class="space-x-2">

                <button type="button" class="text-primary-600 text-sm" @click="openFormulaModal(f)">Sửa</button>

                <button type="button" class="text-red-600 text-sm" @click="deleteFormula(f.id)">Xóa</button>

              </td>

            </tr>

          </tbody>

        </table>

      </div>



      <UiModal v-model="showCustomVarModal" :title="customVarForm.id ? 'Sửa biến tùy chỉnh' : 'Thêm biến tùy chỉnh'">

        <form class="space-y-3" @submit.prevent="saveCustomVar">

          <div>

            <label class="text-xs text-slate-500">Mã biến (chữ thường, không dấu)</label>

            <input v-model="customVarForm.code" class="hcm-input w-full mt-1 font-mono lowercase" placeholder="phu_cap_an" required :disabled="!!customVarForm.id" />

          </div>

          <input v-model="customVarForm.label" class="hcm-input w-full" placeholder="Tên hiển thị" required />

          <input v-model.number="customVarForm.value" type="number" step="any" class="hcm-input w-full" placeholder="Giá trị số" required />

          <textarea v-model="customVarForm.description" class="hcm-input w-full text-sm" rows="2" placeholder="Ghi chú (tuỳ chọn)" />

          <button type="submit" class="hcm-btn-primary w-full" :disabled="customVarSaving">{{ customVarSaving ? 'Đang lưu…' : 'Lưu' }}</button>

        </form>

      </UiModal>

      <UiModal v-model="showFormulaModal" :title="formulaForm.id ? 'Sửa công thức' : 'Thêm công thức'">

        <form class="space-y-3" @submit.prevent="saveFormula">

          <input v-model="formulaForm.code" class="hcm-input w-full uppercase" placeholder="Mã" required />

          <input v-model="formulaForm.name" class="hcm-input w-full" placeholder="Tên khoản" required />

          <input v-model="formulaForm.target_field" class="hcm-input w-full" placeholder="target_field" required />

          <select v-model="formulaForm.apply_when" class="hcm-input w-full">

            <option v-for="o in applyWhenOptions" :key="o.value" :value="o.value">{{ o.label }}</option>

          </select>

          <textarea v-model="formulaForm.formula" class="hcm-input w-full font-mono" rows="2" required />

          <select v-model="formulaForm.category" class="hcm-input w-full">

            <option value="earning">Thu nhập</option>

            <option value="deduction">Khấu trừ</option>

          </select>

          <button type="submit" class="hcm-btn-primary w-full">Lưu</button>

        </form>

      </UiModal>

    </template>

    <UiModal v-model="showCreateCycleModal" title="Tạo kỳ lương" wide>
      <form class="space-y-4" @submit.prevent="submitCreateCycle">
        <div>
          <label class="text-sm font-medium">Tháng lương</label>
          <input v-model="createCycleForm.period" type="month" class="hcm-input w-full mt-1" required @change="loadCreateCycleStatus" />
        </div>
        <div v-if="createCycleStatusLoading" class="text-sm text-slate-400">Đang kiểm tra tháng…</div>
        <div v-else-if="createCycleStatus" class="rounded-lg border p-3 text-sm space-y-2" :class="createCycleStatus.can_create_new ? 'border-slate-200 bg-slate-50' : 'border-amber-200 bg-amber-50'">
          <p v-if="createCycleStatus.has_locked_run" class="text-amber-900">
            Tháng này đã có bảng lương <strong>đã khóa</strong> — bản cũ giữ nguyên, hệ thống sẽ tạo
            <strong>lần tính {{ createCycleStatus.next_run_number }}</strong>.
          </p>
          <p v-else-if="!createCycleStatus.cycles?.length" class="text-slate-700">Chưa có bảng lương tháng này — tạo lần tính đầu tiên.</p>
          <p v-else class="text-amber-900">{{ createCycleStatus.block_reason }}</p>
          <ul v-if="createCycleStatus.cycles?.length" class="text-xs text-slate-600 space-y-1">
            <li v-for="row in createCycleStatus.cycles" :key="row.id">
              · {{ row.label }} — <UiBadge :variant="statusVariant(row.status)" class="inline">{{ statusLabel(row.status) }}</UiBadge>
            </li>
          </ul>
        </div>
        <div>
          <label class="text-sm font-medium">Ghi chú lần tính (tuỳ chọn)</label>
          <input v-model="createCycleForm.revision_note" type="text" class="hcm-input w-full mt-1" placeholder="Ví dụ: Điều chỉnh công sau bù thẻ" maxlength="500" />
        </div>
        <p class="text-xs text-slate-500">Sau khi khóa lương, bảng đó không đổi. Muốn tính lại → chọn tháng và tạo bảng mới.</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="hcm-btn-secondary" @click="showCreateCycleModal = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="createCycleSaving || (createCycleStatus && !createCycleStatus.can_create_new)">
            {{ createCycleSaving ? 'Đang tạo…' : 'Tạo bảng lương' }}
          </button>
        </div>
      </form>
    </UiModal>

  </div>

</template>



<script setup>

import { computed, onMounted, ref } from 'vue';

import api from '../../api/client';

import UiPageHeader from '../../components/ui/UiPageHeader.vue';

import UiBadge from '../../components/ui/UiBadge.vue';

import UiEmpty from '../../components/ui/UiEmpty.vue';

import UiModal from '../../components/ui/UiModal.vue';

import UiSearchInput from '../../components/ui/UiSearchInput.vue';

import { extractItems } from '../../composables/usePagination';

import { matchesEmployeeSearch } from '../../composables/useDebouncedSearch';

import { useFormat } from '../../composables/useFormat';

import { useToast } from '../../composables/useToast';

import { useAuthStore } from '../../stores/auth';

import { usePermission } from '../../composables/usePermission';



const { money, date, statusLabel } = useFormat();

const toast = useToast();

const auth = useAuthStore();

const { hasAnyRole } = usePermission();

const isAdmin = computed(() => hasAnyRole(['admin']));

const allowancePayrollLocked = computed(() => {
  const cycle = cycles.value.find((c) => c.period === allowancePeriod.value);
  return cycle?.status === 'locked';
});

function isAttendanceLocked(period) {
  return !!attendanceLocks.value[period];
}



const mainTab = ref('cycles');

const mainTabs = [

  { key: 'cycles', label: 'Kỳ lương', icon: '💰' },

  { key: 'allowances', label: 'Trợ cấp tháng', icon: '📋' },

  { key: 'import', label: 'Import Excel', icon: '📥' },

  { key: 'formulas', label: 'Công thức lương', icon: '🧮' },

];



const cycles = ref([]);

const attendanceLocks = ref({});

const selected = ref(null);

const companyId = ref(null);

const employeeFilter = ref('all');

const payrollSearch = ref('');

const showCreateCycleModal = ref(false);
const createCycleSaving = ref(false);
const createCycleStatusLoading = ref(false);
const createCycleStatus = ref(null);
const createCycleForm = ref({
  period: new Date().toISOString().slice(0, 7),
  revision_note: '',
});

const formulaRules = ref([]);

const formulaVariables = ref({});

const formulaParameters = ref([]);

const formulaParameterValues = ref({});

const formulaComputedVars = ref({});

const formulaCustomVars = ref([]);

const formulaParamsSaving = ref(false);

const applyWhenOptions = ref([]);

const showCustomVarModal = ref(false);

const customVarSaving = ref(false);

const customVarForm = ref({

  id: null,

  code: '',

  label: '',

  value: 0,

  description: '',

});

const showFormulaModal = ref(false);

const formulaForm = ref({});

const allowancePeriod = ref(new Date().toISOString().slice(0, 7));
const allowanceRows = ref([]);
const allowanceCatalog = ref({});
const allowanceLoading = ref(false);
const showAllowanceModal = ref(false);
const allowanceSaving = ref(false);
const allowanceForm = ref({
  employee_id: null,
  full_name: '',
  allowances: {},
  travel_support_amount: 0,
  travel_eligible: false,
  prev_month_adjustment: 0,
  notes: '',
  phased_preview: null,
});

const importPeriod = ref(new Date().toISOString().slice(0, 7));
const importFile = ref(null);
const importSaving = ref(false);
const importResult = ref(null);
const importErrors = ref([]);



const filteredResults = computed(() => {

  let rows = selected.value?.results || [];

  if (employeeFilter.value === 'terminated') {

    rows = rows.filter((r) => r.breakdown?.is_terminated_in_month);

  }

  const q = payrollSearch.value.trim();
  if (!q) return rows;

  return rows.filter((r) => matchesEmployeeSearch(r, q));

});



function statusVariant(s) {

  if (s === 'calculated' || s === 'approved') return 'success';

  if (s === 'locked') return 'default';

  if (s === 'draft') return 'warning';

  return 'default';

}



function applyWhenLabel(v) {

  return applyWhenOptions.value.find((o) => o.value === v)?.label || v;

}

function cycleDisplayLabel(c) {
  if (!c) return '';
  if (c.label) return c.label;
  if (Number(c.run_number) > 1) return `${c.period} · Lần ${c.run_number}`;
  return c.period;
}

async function openCreateCycleModal() {
  createCycleForm.value = {
    period: new Date().toISOString().slice(0, 7),
    revision_note: '',
  };
  showCreateCycleModal.value = true;
  await loadCreateCycleStatus();
}

async function loadCreateCycleStatus() {
  if (!createCycleForm.value.period) return;
  createCycleStatusLoading.value = true;
  try {
    const { data } = await api.get('/payroll-cycles/period-status', {
      params: { period: createCycleForm.value.period },
    });
    createCycleStatus.value = data.data || null;
  } catch (e) {
    createCycleStatus.value = null;
    toast.show(e.response?.data?.message || 'Không kiểm tra được tháng', 'error');
  } finally {
    createCycleStatusLoading.value = false;
  }
}

async function submitCreateCycle() {
  createCycleSaving.value = true;
  try {
    const { data } = await api.post('/payroll-cycles', {
      period: createCycleForm.value.period,
      revision_note: createCycleForm.value.revision_note || null,
    });
    showCreateCycleModal.value = false;
    toast.show('Đã tạo bảng lương');
    await load();
    if (data.data?.id) {
      await selectCycle(data.data);
    }
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không tạo được kỳ lương', 'error');
  } finally {
    createCycleSaving.value = false;
  }
}



function switchMainTab(key) {

  mainTab.value = key;

  if (key === 'formulas') loadFormulas();

  if (key === 'allowances') loadAllowances();

}



async function load() {

  companyId.value = auth.companyId;

  const { data } = await api.get('/payroll-cycles');

  cycles.value = extractItems(data);

  await loadAttendanceLockStatuses();

}



async function loadAttendanceLockStatuses() {

  const map = {};

  await Promise.all(cycles.value.map(async (c) => {

    try {

      const { data } = await api.get('/attendance-summaries/period-status', {

        params: { period: c.period, company_id: companyId.value },

      });

      map[c.period] = data.data?.is_locked ?? false;

    } catch {

      map[c.period] = false;

    }

  }));

  attendanceLocks.value = map;

}



async function loadFormulas() {

  const [rulesRes, varsRes] = await Promise.all([

    api.get('/payroll-formula-rules'),

    api.get('/payroll-formula-variables'),

  ]);

  const payload = rulesRes.data.data || {};

  formulaRules.value = payload.rules || [];

  formulaVariables.value = payload.variables || {};

  applyWhenOptions.value = payload.apply_when_options || [];

  applyVariableCatalog(varsRes.data.data || payload.variable_catalog || {});

}

function applyVariableCatalog(catalog) {

  formulaParameters.value = catalog.parameters || [];

  formulaComputedVars.value = catalog.computed || {};

  formulaCustomVars.value = catalog.custom_variables || [];

  formulaVariables.value = catalog.variable_hints || formulaVariables.value;

  const values = {};

  formulaParameters.value.forEach((p) => {

    values[p.key] = p.value ?? p.default ?? '';

  });

  formulaParameterValues.value = values;

}

async function saveFormulaParameters() {

  formulaParamsSaving.value = true;

  try {

    const { data } = await api.put('/payroll-formula-variables/parameters', {

      parameters: formulaParameterValues.value,

    });

    applyVariableCatalog(data.data || {});

    toast.show('Đã lưu tham số công thức');

  } catch (e) {

    toast.show(e.response?.data?.message || 'Không lưu được tham số', 'error');

  } finally {

    formulaParamsSaving.value = false;

  }

}

function openCustomVarModal(row = null) {

  customVarForm.value = row

    ? { id: row.id, code: row.code, label: row.label, value: Number(row.value), description: row.description || '' }

    : { id: null, code: '', label: '', value: 0, description: '' };

  showCustomVarModal.value = true;

}

async function saveCustomVar() {

  customVarSaving.value = true;

  try {

    const body = {

      code: customVarForm.value.code,

      label: customVarForm.value.label,

      value: customVarForm.value.value,

      description: customVarForm.value.description || null,

    };

    if (customVarForm.value.id) {

      await api.put(`/payroll-formula-custom-variables/${customVarForm.value.id}`, body);

    } else {

      await api.post('/payroll-formula-custom-variables', body);

    }

    showCustomVarModal.value = false;

    toast.show('Đã lưu biến tùy chỉnh');

    const { data } = await api.get('/payroll-formula-variables');

    applyVariableCatalog(data.data || {});

    const rulesRes = await api.get('/payroll-formula-rules');

    formulaVariables.value = rulesRes.data.data?.variables || formulaVariables.value;

  } catch (e) {

    toast.show(e.response?.data?.message || 'Không lưu được biến', 'error');

  } finally {

    customVarSaving.value = false;

  }

}

async function deleteCustomVar(id) {

  if (!confirm('Xóa biến tùy chỉnh này?')) return;

  await api.delete(`/payroll-formula-custom-variables/${id}`);

  toast.show('Đã xóa');

  const { data } = await api.get('/payroll-formula-variables');

  applyVariableCatalog(data.data || {});

}



async function exportXlsx(cycleId) {

  const response = await api.get(`/payroll-cycles/${cycleId}/export`, { responseType: 'blob' });

  const url = window.URL.createObjectURL(new Blob([response.data]));

  const a = document.createElement('a');

  a.href = url;

  a.download = `bang-luong-${cycleId}.xlsx`;

  a.click();

  window.URL.revokeObjectURL(url);

}



async function selectCycle(c) {

  const { data } = await api.get(`/payroll-cycles/${c.id}`);

  selected.value = data.data;

}






async function lockAttendance(period) {

  if (!window.confirm(`Khóa công tháng ${period}? Sau khi khóa không thể tổng hợp lại hoặc import công cho đến khi admin mở khóa.`)) return;

  await api.post('/attendance-summaries/lock', { company_id: companyId.value, period });

  toast.show('Đã khóa công tháng');

  attendanceLocks.value = { ...attendanceLocks.value, [period]: true };

}



async function unlockAttendance(period) {

  if (!window.confirm(`Mở khóa công tháng ${period}? Chỉ admin được phép thao tác này.`)) return;

  await api.post('/attendance-summaries/unlock', { company_id: companyId.value, period });

  toast.show('Đã mở khóa công tháng');

  attendanceLocks.value = { ...attendanceLocks.value, [period]: false };

}



async function lockPayroll(id) {

  if (!window.confirm('Khóa lương tháng này? Sau khi khóa không thể tính lại hoặc sửa trợ cấp cho đến khi admin mở khóa.')) return;

  await api.post(`/payroll-cycles/${id}/lock`);

  toast.show('Đã khóa lương tháng');

  await load();

  if (selected.value?.id === id) await selectCycle({ id });

}



async function unlockPayroll(id) {

  if (!window.confirm('Mở khóa lương tháng? Chỉ admin được phép thao tác này.')) return;

  await api.post(`/payroll-cycles/${id}/unlock`);

  toast.show('Đã mở khóa lương tháng');

  await load();

  if (selected.value?.id === id) await selectCycle({ id });

}



async function calculate(id) {

  await api.post(`/payroll-cycles/${id}/calculate`);

  toast.show('Đã tính lương');

  await load();

  await selectCycle({ id });

}



async function publishPayslips(cycleId) {

  const { data } = await api.post(`/payroll-cycles/${cycleId}/publish-payslips`);

  toast.show(`Phát hành ${data.data.published} phiếu lương`);

}



async function viewPayslip(resultId) {

  const { data } = await api.get(`/payroll-results/${resultId}/payslip`, { responseType: 'text', headers: { Accept: 'text/html' } });

  const w = window.open('', '_blank');

  if (w) { w.document.write(data); w.document.close(); }

}



function openFormulaModal(rule = null) {

  formulaForm.value = rule ? { ...rule } : { code: '', name: '', target_field: '', apply_when: 'all', formula: '', category: 'earning', is_active: true };

  showFormulaModal.value = true;

}



async function saveFormula() {

  const payload = { ...formulaForm.value, code: String(formulaForm.value.code).toUpperCase() };

  if (payload.id) await api.put(`/payroll-formula-rules/${payload.id}`, payload);

  else await api.post('/payroll-formula-rules', payload);

  showFormulaModal.value = false;

  toast.show('Đã lưu công thức');

  await loadFormulas();

}



async function deleteFormula(id) {

  if (!confirm('Xóa công thức?')) return;

  await api.delete(`/payroll-formula-rules/${id}`);

  await loadFormulas();

}




async function loadAllowances() {
  allowanceLoading.value = true;
  try {
    const { data } = await api.get('/payroll-allowances', {
      params: { period: allowancePeriod.value, company_id: companyId.value },
    });
    const payload = data.data || {};
    allowanceRows.value = payload.rows || [];
    allowanceCatalog.value = payload.catalog?.items || {};
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không tải được trợ cấp', 'error');
  } finally {
    allowanceLoading.value = false;
  }
}

function phasedItem(row, code) {
  return row.phased_preview?.items?.[code] ?? null;
}

function openAllowanceModal(row) {
  const base = {};
  Object.keys(allowanceCatalog.value).forEach((code) => {
    base[code] = row.allowances?.[code] ?? 0;
  });
  allowanceForm.value = {
    employee_id: row.employee_id,
    full_name: row.full_name,
    allowances: base,
    travel_support_amount: row.travel_support_amount || 0,
    travel_eligible: !!row.travel_eligible,
    prev_month_adjustment: row.prev_month_adjustment || 0,
    notes: row.notes || '',
    phased_preview: row.phased_preview ?? null,
  };
  showAllowanceModal.value = true;
}

async function saveAllowance() {
  allowanceSaving.value = true;
  try {
    await api.post('/payroll-allowances', {
      employee_id: allowanceForm.value.employee_id,
      period: allowancePeriod.value,
      allowances: allowanceForm.value.allowances,
      travel_support_amount: allowanceForm.value.travel_support_amount,
      travel_eligible: allowanceForm.value.travel_eligible,
      prev_month_adjustment: allowanceForm.value.prev_month_adjustment,
      notes: allowanceForm.value.notes,
    });
    toast.show('Đã lưu trợ cấp');
    showAllowanceModal.value = false;
    await loadAllowances();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lưu thất bại', 'error');
  } finally {
    allowanceSaving.value = false;
  }
}

function onImportFileChange(ev) {
  importFile.value = ev.target.files?.[0] || null;
  importResult.value = null;
  importErrors.value = [];
}

async function submitImport() {
  if (!importFile.value) {
    toast.show('Chọn file Excel trước', 'error');
    return;
  }
  importSaving.value = true;
  importResult.value = null;
  importErrors.value = [];
  try {
    const fd = new FormData();
    fd.append('period', importPeriod.value);
    fd.append('file', importFile.value);
    const { data } = await api.post('/payroll-import/cong-luong', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    importResult.value = data.data || {};
    importErrors.value = [
      ...(importResult.value.cong?.errors || []),
      ...(importResult.value.luong?.errors || []),
    ];
    toast.show('Import hoàn tất');
  } catch (e) {
    toast.show(e.response?.data?.message || 'Import thất bại', 'error');
  } finally {
    importSaving.value = false;
  }
}

async function copyAllowancesFromPrevious() {
  try {
    const { data } = await api.post('/payroll-allowances/copy-previous', {
      period: allowancePeriod.value,
      company_id: companyId.value,
    });
    toast.show(`Đã sao chép ${data.data?.copied || 0} NV từ tháng trước`);
    await loadAllowances();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Sao chép thất bại', 'error');
  }
}

onMounted(load);

</script>


