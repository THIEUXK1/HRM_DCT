<template>
  <div class="space-y-6">
    <UiPageHeader
      title="Nguồn chấm công ZKTime SQL"
      subtitle="Quản trị kết nối cơ sở dữ liệu ZKTime MS SQL Server động"
      breadcrumb="Nguồn chấm công"
    >
      <template #actions>
        <button type="button" class="hcm-btn-secondary text-sm" @click="showMappingModal = true">🔗 Ghép mã nhân sự</button>
        <button type="button" class="hcm-btn-primary text-sm" @click="openForm()">+ Thêm nguồn SQL</button>
      </template>
    </UiPageHeader>

    <!-- Navigation Sub-tabs -->
    <div class="border-b border-slate-200">
      <nav class="-mb-px flex space-x-8">
        <button
          v-for="t in subTabs"
          :key="t.key"
          type="button"
          class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
          :class="currentSubTab === t.key ? 'border-primary-600 text-primary-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
          @click="switchSubTab(t.key)"
        >
          {{ t.label }}
        </button>
      </nav>
    </div>

    <!-- TAB: Source list -->
    <div v-if="currentSubTab === 'sources'" class="space-y-4">
      <div v-if="loading" class="text-center py-12 text-slate-400">Đang tải cấu hình nguồn...</div>
      <div v-else-if="sources.length === 0">
        <UiEmpty title="Chưa có nguồn chấm công SQL" subtitle="Cấu hình kết nối SQL Server ZKTime để tự động hóa thu thập dữ liệu công." />
      </div>
      <div v-else class="grid grid-cols-1 gap-4">
        <div v-for="src in sources" :key="src.id" class="hcm-card p-5 bg-white border border-slate-200 shadow-sm rounded-lg hover:shadow-md transition-shadow duration-200">
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <!-- Source details -->
            <div class="space-y-2">
              <div class="flex items-center gap-3">
                <span class="font-bold text-slate-900 text-lg">{{ src.name }}</span>
                <span class="text-xs bg-primary-50 text-primary-700 font-semibold px-2 py-0.5 rounded">
                  {{ src.company?.name || 'Best Pacific' }}
                </span>
                <span
                  class="text-xs px-2.5 py-0.5 rounded-full font-medium"
                  :class="src.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                >
                  {{ src.is_active ? 'Đang hoạt động' : 'Tạm tắt' }}
                </span>
              </div>

              <div class="text-sm text-slate-500 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-1">
                <div>🖥️ Host: <span class="font-mono font-medium">{{ src.host }}:{{ src.port }}</span></div>
                <div>🗄️ Database: <span class="font-semibold">{{ src.database_name }}</span></div>
                <div>🕒 Timezone: <span>{{ src.timezone }}</span></div>
                <div>👤 User: <span class="font-mono text-xs">{{ src.username }}</span></div>
                <div>⏰ Giờ sync: <span class="font-semibold">{{ src.sync_time }}</span></div>
                <div v-if="src.last_synced_at">🔄 Lần cuối: <span class="font-semibold">{{ formatDatetime(src.last_synced_at) }}</span></div>
              </div>

              <!-- Connection Status Alert -->
              <div class="pt-1">
                <div v-if="src.connection_status === 'success'" class="inline-flex items-center gap-1.5 text-xs text-emerald-600 bg-emerald-50 px-2 py-1 rounded">
                  <span>✓ Kết nối SQL Server hoạt động tốt</span>
                  <span v-if="src.last_tested_at" class="text-slate-400">({{ formatDatetime(src.last_tested_at) }})</span>
                </div>
                <div v-else-if="src.connection_status === 'failed'" class="flex flex-col gap-1 text-xs text-red-600 bg-red-50 p-2 rounded max-w-2xl">
                  <span class="font-semibold">✗ Kết nối thất bại:</span>
                  <p class="font-mono break-all">{{ src.last_error }}</p>
                </div>
                <div v-else class="inline-flex items-center gap-1 text-xs text-slate-400 bg-slate-50 px-2 py-1 rounded">
                  <span>❔ Chưa kiểm tra kết nối</span>
                </div>
              </div>
            </div>

            <!-- Action buttons -->
            <div class="flex flex-wrap items-center gap-2 lg:justify-end shrink-0">
              <button
                type="button"
                class="hcm-btn-secondary text-sm px-3 py-1.5"
                :disabled="testing[src.id]"
                @click="testConnection(src)"
              >
                {{ testing[src.id] ? '🔌 Đang test...' : '🔌 Test kết nối' }}
              </button>
              <button
                type="button"
                class="hcm-btn-secondary text-sm px-3 py-1.5"
                @click="openSyncModal(src)"
              >
                ⬇️ Đồng bộ ngay
              </button>
              <button
                type="button"
                class="hcm-btn-secondary text-sm px-3 py-1.5"
                @click="openSyncBadgeModal(src)"
              >
                🔄 Sync mã vân tay
              </button>
              <button
                type="button"
                class="hcm-btn-secondary text-sm px-3 py-1.5"
                @click="openForm(src)"
              >
                ✏️ Sửa
              </button>
              <button
                type="button"
                class="hcm-btn-secondary text-sm text-red-600 hover:text-red-800 hover:bg-red-50 border-red-200 px-3 py-1.5"
                @click="deleteSource(src)"
              >
                🗑️ Xóa
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: Sync logs history -->
    <div v-if="currentSubTab === 'history'" class="hcm-card p-5 bg-white border border-slate-200 shadow-sm rounded-lg">
      <h3 class="text-sm font-semibold text-slate-800 mb-4">Lịch sử đồng bộ log chấm công</h3>
      <div v-if="loadingLogs" class="text-center py-8 text-slate-400">Đang tải lịch sử...</div>
      <div v-else-if="syncLogs.length === 0" class="text-slate-400 text-center py-8">
        Chưa có dữ liệu lịch sử đồng bộ.
      </div>
      <div v-else class="overflow-x-auto">
        <table class="hcm-table w-full text-xs">
          <thead>
            <tr>
              <th class="text-left font-semibold text-slate-600">Nguồn</th>
              <th class="text-left font-semibold text-slate-600">Bắt đầu</th>
              <th class="text-left font-semibold text-slate-600">Kết thúc</th>
              <th class="text-left font-semibold text-slate-600">Trạng thái</th>
              <th class="text-right font-semibold text-slate-600">Đọc được</th>
              <th class="text-right font-semibold text-slate-600">Lưu thành công</th>
              <th class="text-right font-semibold text-slate-600">Trùng lặp</th>
              <th class="text-right font-semibold text-slate-600">Chưa ánh xạ</th>
              <th class="text-left font-semibold text-slate-600">Chi tiết / Lỗi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in syncLogs" :key="log.id" class="border-t border-slate-100 hover:bg-slate-50 transition-colors">
              <td class="font-medium text-slate-800">{{ log.source?.name || '#' + log.attendance_source_id }}</td>
              <td>{{ formatDatetime(log.started_at) }}</td>
              <td>{{ log.finished_at ? formatDatetime(log.finished_at) : '—' }}</td>
              <td>
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-semibold"
                  :class="log.status === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'"
                >
                  {{ log.status === 'success' ? 'SUCCESS' : 'FAILED' }}
                </span>
              </td>
              <td class="text-right font-mono">{{ log.total_read }}</td>
              <td class="text-right font-mono text-emerald-700 font-semibold">{{ log.inserted }}</td>
              <td class="text-right font-mono text-slate-400">{{ log.skipped }}</td>
              <td class="text-right font-mono" :class="log.unmapped > 0 ? 'text-amber-600 font-semibold' : 'text-slate-400'">{{ log.unmapped }}</td>
              <td class="max-w-xs truncate font-mono text-slate-500" :title="log.error_message">
                {{ log.error_message || '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB: Unmapped raw logs -->
    <div v-if="currentSubTab === 'unmapped'" class="hcm-card p-5 bg-white border border-slate-200 shadow-sm rounded-lg">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-sm font-semibold text-slate-800">Log chấm công thô chưa ánh xạ nhân sự</h3>
          <p class="text-xs text-slate-400">Các log đọc được từ ZKTime SQL có mã SSN chưa khớp hoặc chưa cấu hình ánh xạ sang HRM.</p>
        </div>
        <button type="button" class="hcm-btn-secondary text-xs" @click="loadUnmappedLogs">🔄 Làm mới</button>
      </div>

      <div v-if="loadingUnmapped" class="text-center py-8 text-slate-400">Đang tải log chưa ánh xạ...</div>
      <div v-else-if="unmappedLogs.length === 0" class="text-slate-400 text-center py-8">
        ✓ Tuyệt vời! Hiện tại không có log nào chưa được ánh xạ nhân viên.
      </div>
      <div v-else class="overflow-x-auto">
        <table class="hcm-table w-full text-xs">
          <thead>
            <tr>
              <th class="text-left font-semibold text-slate-600">Nguồn</th>
              <th class="text-left font-semibold text-slate-600">ID máy (USERID)</th>
              <th class="text-left font-semibold text-slate-600">Mã máy (SSN / Code)</th>
              <th class="text-left font-semibold text-slate-600">Thời gian quét</th>
              <th class="text-left font-semibold text-slate-600">Kiểu quét ZK</th>
              <th class="text-left font-semibold text-slate-600">Thiết bị quét</th>
              <th class="text-center font-semibold text-slate-600">Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in unmappedLogs" :key="log.id" class="border-t border-slate-100 hover:bg-slate-50 transition-colors">
              <td class="font-medium text-slate-800">{{ log.source?.name || '#' + log.attendance_source_id }}</td>
              <td class="font-mono">{{ log.device_user_id }}</td>
              <td class="font-mono text-amber-700 font-semibold">{{ log.employee_code || '—' }}</td>
              <td>{{ formatDatetime(log.check_time) }}</td>
              <td class="font-mono">{{ log.raw_payload?.CHECKTYPE || log.raw_payload?.check_type || '—' }}</td>
              <td class="font-mono text-slate-500">{{ log.raw_payload?.SENSORID || log.raw_payload?.device_code || '—' }}</td>
              <td class="text-center">
                <button
                  type="button"
                  class="hcm-btn-primary text-[10px] px-2 py-1"
                  @click="openMapFormFromLog(log)"
                >
                  🔗 Ghép NV
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- FORM MODAL: Add/Edit ZKTime Source -->
    <UiModal v-model="showForm" :title="form.id ? 'Sửa nguồn ZKTime SQL' : 'Thêm nguồn ZKTime SQL'">
      <form class="space-y-4" @submit.prevent="saveSource">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="hcm-label">Tên nguồn *</label>
            <input v-model="form.name" type="text" class="hcm-input" required placeholder="Best Pacific ZKTime SQL" />
          </div>
          <div>
            <label class="hcm-label">Công ty *</label>
            <select v-model.number="form.company_id" class="hcm-input" required :disabled="!!form.id">
              <option value="1">Best Pacific</option>
              <option value="2">Premium Fashion</option>
              <option value="3">MEGA</option>
            </select>
          </div>
          <div>
            <label class="hcm-label">Host/IP SQL Server *</label>
            <input v-model="form.host" type="text" class="hcm-input font-mono" required placeholder="10.0.60.33" />
          </div>
          <div>
            <label class="hcm-label">Port *</label>
            <input v-model.number="form.port" type="number" class="hcm-input font-mono" required placeholder="1433" />
          </div>
          <div>
            <label class="hcm-label">Database name *</label>
            <input v-model="form.database_name" type="text" class="hcm-input" required placeholder="Zktime" />
          </div>
          <div>
            <label class="hcm-label">Timezone *</label>
            <input v-model="form.timezone" type="text" class="hcm-input font-mono" required placeholder="Asia/Ho_Chi_Minh" />
          </div>
        </div>

        <div class="border-t border-slate-100 pt-3">
          <p class="text-sm font-semibold text-slate-700 mb-2">Thông tin tài khoản (Read-only khuyên dùng)</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="hcm-label">Username *</label>
              <input v-model="form.username" type="text" class="hcm-input font-mono" required placeholder="sa" />
            </div>
            <div>
              <label class="hcm-label">
                Password {{ form.id ? '(Bỏ trống nếu không đổi)' : '*' }}
              </label>
              <input
                v-model="form.password_encrypted"
                type="password"
                class="hcm-input"
                :required="!form.id"
                autocomplete="new-password"
                placeholder="••••••••"
              />
            </div>
          </div>
        </div>

        <div class="border-t border-slate-100 pt-3">
          <p class="text-sm font-semibold text-slate-700 mb-2">Tùy biến Cấu trúc Bảng ZKTime (Tùy chọn)</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="hcm-label text-[11px]">Bảng nhân viên</label>
              <input v-model="form.user_table" type="text" class="hcm-input font-mono text-xs" />
            </div>
            <div>
              <label class="hcm-label text-[11px]">Bảng chấm công</label>
              <input v-model="form.checkinout_table" type="text" class="hcm-input font-mono text-xs" />
            </div>
            <div>
              <label class="hcm-label text-[11px]">Cột Mã NV (mapping)</label>
              <input v-model="form.employee_code_field" type="text" class="hcm-input font-mono text-xs" />
            </div>
            <div>
              <label class="hcm-label text-[11px]">Cột ID máy (badge)</label>
              <input v-model="form.badge_field" type="text" class="hcm-input font-mono text-xs" />
            </div>
            <div>
              <label class="hcm-label text-[11px]">Cột Thời gian quét</label>
              <input v-model="form.check_time_field" type="text" class="hcm-input font-mono text-xs" />
            </div>
            <div>
              <label class="hcm-label text-[11px]">Giờ Sync hằng ngày</label>
              <input v-model="form.sync_time" type="text" class="hcm-input font-mono text-xs" placeholder="09:00" />
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded" />
          <label for="is_active" class="text-sm">Nguồn chấm công đang kích hoạt</label>
        </div>

        <div class="flex justify-end gap-3 pt-3 border-t">
          <button type="button" class="hcm-btn-secondary" @click="closeForm">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="saving">
            {{ saving ? 'Đang lưu...' : (form.id ? 'Cập nhật' : 'Thêm cấu hình') }}
          </button>
        </div>
      </form>
    </UiModal>

    <!-- FORM MODAL: Sync Now date dialog -->
    <UiModal v-model="showSyncModal" :title="'Chạy đồng bộ thủ công - ' + activeSource?.name">
      <form class="space-y-4" @submit.prevent="runSync">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="hcm-label">Từ ngày *</label>
            <input v-model="syncForm.from" type="date" class="hcm-input" required />
          </div>
          <div>
            <label class="hcm-label">Đến ngày *</label>
            <input v-model="syncForm.to" type="date" class="hcm-input" required />
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input id="dry_run" v-model="syncForm.dry_run" type="checkbox" class="rounded" />
          <label for="dry_run" class="text-sm font-medium text-slate-700">
            Chạy thử nghiệm (Dry-run) - Không ghi dữ liệu
          </label>
        </div>

        <!-- Sync Dry-run/Real result dashboard -->
        <div v-if="syncResult" class="p-4 rounded-lg text-sm bg-slate-50 border border-slate-200 mt-4 space-y-2">
          <p class="font-bold text-slate-800">Kết quả chạy {{ syncResult.dry_run ? 'Thử nghiệm' : 'Thực tế' }}:</p>
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div>Tổng số log đọc từ ZK:</div>
            <div class="font-bold font-mono text-right">{{ syncResult.total_read }}</div>
            <div v-if="syncResult.dry_run">Số log mới sẽ lưu:</div>
            <div v-else>Số log đã lưu:</div>
            <div class="font-bold font-mono text-right text-emerald-600">{{ syncResult.dry_run ? syncResult.new_logs : syncResult.inserted }}</div>
            <div v-if="syncResult.dry_run">Số log trùng sẽ bỏ qua:</div>
            <div v-else>Số log trùng đã bỏ qua:</div>
            <div class="font-mono text-right text-slate-400">{{ syncResult.dry_run ? syncResult.duplicates : syncResult.skipped }}</div>
            <div>Số log chưa map nhân sự:</div>
            <div class="font-bold font-mono text-right" :class="syncResult.unmapped > 0 ? 'text-amber-600' : 'text-slate-400'">{{ syncResult.unmapped }}</div>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-3 border-t">
          <button type="button" class="hcm-btn-secondary" @click="showSyncModal = false">Đóng</button>
          <button type="submit" class="hcm-btn-primary" :disabled="syncing">
            {{ syncing ? 'Đang xử lý...' : (syncForm.dry_run ? '🔍 Chạy thử' : '⚡ Đồng bộ thật') }}
          </button>
        </div>
      </form>
    </UiModal>

    <!-- FORM MODAL: Sync Fingerprint/Badge Numbers dialog -->
    <UiModal v-model="showSyncBadgeModal" :title="'Đồng bộ Mã vân tay từ ZKTime - ' + activeSource?.name">
      <form class="space-y-4" @submit.prevent="runSyncBadge">
        <div class="rounded bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800 space-y-1">
          <p class="font-bold">Hướng dẫn nghiệp vụ:</p>
          <p>• Hệ thống sẽ đọc toàn bộ danh sách người dùng từ ZKTime SQL Server.</p>
          <p>• Trùng khớp với mã nhân viên trong ERP (employee_code = Badgenumber) để lấy Mã vân tay (biometric_id).</p>
          <p>• Mặc định chỉ cập nhật cho các hồ sơ chưa có Mã vân tay. Gõ dấu tích bên dưới để ép buộc ghi đè.</p>
        </div>

        <div class="flex items-center gap-2">
          <input id="sync_badge_dry_run" v-model="syncBadgeForm.dry_run" type="checkbox" class="rounded" />
          <label for="sync_badge_dry_run" class="text-sm font-medium text-slate-700">
            Chạy kiểm tra trước (Dry-run) - Không lưu dữ liệu thực tế
          </label>
        </div>

        <div class="flex items-center gap-2">
          <input id="sync_badge_force" v-model="syncBadgeForm.force" type="checkbox" class="rounded" />
          <label for="sync_badge_force" class="text-sm font-medium text-slate-700">
            Ghi đè mã vân tay hiện có nếu khác nhau (Force Overwrite)
          </label>
        </div>

        <!-- Sync result dashboard -->
        <div v-if="syncBadgeResult" class="p-4 rounded-lg text-sm bg-slate-50 border border-slate-200 mt-4 space-y-3 max-h-[300px] overflow-y-auto">
          <p class="font-bold text-slate-800">Kết quả đồng bộ ({{ syncBadgeResult.dry_run ? 'Kiểm tra' : 'Thực tế' }}):</p>
          <div class="grid grid-cols-2 gap-2 text-xs border-b pb-2">
            <div>Tổng số user đọc từ ZKTime:</div>
            <div class="font-bold font-mono text-right">{{ syncBadgeResult.total_read }}</div>
            <div>Nhân viên ERP khớp được:</div>
            <div class="font-bold font-mono text-right text-emerald-600">{{ syncBadgeResult.matched_count }}</div>
            <div>Nhân viên chưa khớp được:</div>
            <div class="font-bold font-mono text-right text-red-600">{{ syncBadgeResult.unmatched_count }}</div>
            <div>Bản ghi sẽ/đã cập nhật:</div>
            <div class="font-bold font-mono text-right text-blue-600">{{ syncBadgeResult.updated_count }}</div>
            <div>Bản ghi bỏ qua (đã có mã):</div>
            <div class="font-bold font-mono text-right text-slate-400">{{ syncBadgeResult.skipped_count }}</div>
          </div>

          <!-- Warnings list -->
          <div v-if="syncBadgeResult.warnings && syncBadgeResult.warnings.length" class="space-y-1 border-b pb-2">
            <p class="text-xs font-bold text-amber-700">⚠️ Cảnh báo / Chi tiết:</p>
            <ul class="list-disc list-inside text-[11px] text-amber-800 space-y-0.5">
              <li v-for="(warn, idx) in syncBadgeResult.warnings" :key="idx">{{ warn }}</li>
            </ul>
          </div>

          <!-- Updates list -->
          <div v-if="syncBadgeResult.updates && syncBadgeResult.updates.length" class="space-y-2">
            <p class="text-xs font-bold text-blue-700">📝 Danh sách cập nhật:</p>
            <table class="w-full text-[10px] border-collapse">
              <thead>
                <tr class="bg-slate-100 text-slate-600">
                  <th class="border p-1 text-left">Mã NV</th>
                  <th class="border p-1 text-left">Họ tên</th>
                  <th class="border p-1 text-right">Mã cũ</th>
                  <th class="border p-1 text-right text-blue-600">Mã mới</th>
                  <th class="border p-1 text-center">Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(upd, idx) in syncBadgeResult.updates" :key="idx" class="bg-white">
                  <td class="border p-1 font-mono">{{ upd.employee_code }}</td>
                  <td class="border p-1">{{ upd.full_name }}</td>
                  <td class="border p-1 text-right font-mono text-slate-400">{{ upd.old_value }}</td>
                  <td class="border p-1 text-right font-mono text-blue-700 font-semibold">{{ upd.new_value }}</td>
                  <td class="border p-1 text-center font-semibold text-slate-700">{{ upd.status }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-3 border-t">
          <button type="button" class="hcm-btn-secondary" @click="showSyncBadgeModal = false">Đóng</button>
          <button type="submit" class="hcm-btn-primary" :disabled="syncingBadge">
            {{ syncingBadge ? 'Đang xử lý...' : (syncBadgeForm.dry_run ? '🔍 Kiểm tra trước' : '⚡ Thực hiện đồng bộ') }}
          </button>
        </div>
      </form>
    </UiModal>

    <!-- FORM MODAL: Employee Mapping config -->
    <UiModal v-model="showMappingModal" title="Cấu hình ánh xạ Mã thiết bị & Mã nhân sự">
      <form class="space-y-4" @submit.prevent="saveMapping">
        <div>
          <label class="hcm-label">Công ty *</label>
          <select v-model.number="mapForm.company_id" class="hcm-input" required @change="onMapCompanyChange">
            <option value="1">Best Pacific</option>
            <option value="2">Premium Fashion</option>
            <option value="3">MEGA</option>
          </select>
        </div>

        <div>
          <label class="hcm-label">Tìm kiếm Nhân sự (Tên hoặc Mã NV)</label>
          <input
            v-model="employeeSearchQuery"
            type="text"
            class="hcm-input mb-2 text-sm"
            placeholder="Nhập tên hoặc mã nhân viên để lọc..."
          />
        </div>

        <div>
          <label class="hcm-label">Chọn Nhân sự HRM *</label>
          <select v-model="selectedEmployee" class="hcm-input" required @change="onEmployeeSelect">
            <option :value="null">-- Chọn nhân viên --</option>
            <option v-for="emp in filteredEmployees" :key="emp.id" :value="emp">
              [{{ emp.employee_code }}] {{ emp.full_name }}
            </option>
          </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="hcm-label">Mã nhân sự (HRM Code)</label>
            <input v-model="mapForm.employee_code" type="text" class="hcm-input font-mono" required readonly />
          </div>
          <div>
            <label class="hcm-label">Mã thiết bị ZKTime (USERID / SSN) *</label>
            <input v-model="mapForm.device_user_id" type="text" class="hcm-input font-mono" required placeholder="Nhập ID vân tay/thẻ" />
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-3 border-t">
          <button type="button" class="hcm-btn-secondary" @click="showMappingModal = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="mappingSubmit">
            {{ mappingSubmit ? 'Đang ghép...' : '🔗 Xác nhận ghép' }}
          </button>
        </div>
      </form>
    </UiModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import api from '../../api/client.js';
import { useToast } from '../../composables/useToast.js';
import { useAuthStore } from '../../stores/auth.js';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiModal from '../../components/ui/UiModal.vue';

const toast = useToast();
const auth = useAuthStore();

const subTabs = [
  { key: 'sources', label: 'Cấu hình nguồn' },
  { key: 'history', label: 'Lịch sử đồng bộ' },
  { key: 'unmapped', label: 'Log chưa ánh xạ NV' }
];

const currentSubTab = ref('sources');
const loading = ref(true);
const sources = ref([]);
const showForm = ref(false);
const saving = ref(false);
const testing = reactive({});

// Sync page states
const showSyncModal = ref(false);
const activeSource = ref(null);
const syncing = ref(false);
const syncResult = ref(null);
const syncForm = reactive({
  from: '',
  to: '',
  dry_run: true
});

// Sync Badge / Fingerprint states
const showSyncBadgeModal = ref(false);
const syncingBadge = ref(false);
const syncBadgeResult = ref(null);
const syncBadgeForm = reactive({
  dry_run: true,
  force: false
});

// Logs & Unmapped states
const loadingLogs = ref(false);
const syncLogs = ref([]);
const loadingUnmapped = ref(false);
const unmappedLogs = ref([]);

// Mapping states
const showMappingModal = ref(false);
const mappingSubmit = ref(false);
const companyEmployees = ref([]);
const selectedEmployee = ref(null);
const employeeSearchQuery = ref('');
const mapForm = reactive({
  company_id: 1,
  employee_id: null,
  employee_code: '',
  device_user_id: ''
});

const filteredEmployees = computed(() => {
  if (!employeeSearchQuery.value) return companyEmployees.value;
  const q = employeeSearchQuery.value.toLowerCase().trim();
  return companyEmployees.value.filter(emp => {
    const code = (emp.employee_code || '').toLowerCase();
    const name = (emp.full_name || '').toLowerCase();
    return code.includes(q) || name.includes(q);
  });
});

watch(showMappingModal, (newVal) => {
  if (newVal) {
    employeeSearchQuery.value = '';
  }
});

const defaultForm = () => ({
  id: null,
  company_id: auth.currentCompanyId || 1,
  name: '',
  type: 'zktime_sql_server',
  host: '',
  port: 1433,
  database_name: 'Zktime',
  username: 'sa',
  password_encrypted: '',
  timezone: 'Asia/Ho_Chi_Minh',
  user_table: 'USERINFO',
  checkinout_table: 'CHECKINOUT',
  employee_code_field: 'SSN',
  badge_field: 'Badgenumber',
  check_time_field: 'CHECKTIME',
  is_active: true,
  sync_time: '09:00'
});

const form = reactive(defaultForm());

onMounted(() => {
  loadSources();
  loadCompanyEmployees(1);
});

async function loadSources() {
  loading.value = true;
  try {
    const res = await api.get('/attendance-sources');
    sources.value = res.data.data ?? res.data;
  } catch (e) {
    toast.show(e.response?.data?.message || 'Không thể tải nguồn ZKTime', 'error');
  } finally {
    loading.value = false;
  }
}

async function loadCompanyEmployees(companyId) {
  try {
    const res = await api.get(`/employees?company_id=${companyId}&per_page=500`);
    // Extract array from standard response
    const data = res.data.data ?? res.data;
    companyEmployees.value = data.data ?? data;
  } catch (e) {
    console.error('Lỗi tải nhân viên:', e);
  }
}

function onMapCompanyChange() {
  selectedEmployee.value = null;
  mapForm.employee_id = null;
  mapForm.employee_code = '';
  loadCompanyEmployees(mapForm.company_id);
}

function onEmployeeSelect() {
  if (selectedEmployee.value) {
    mapForm.employee_id = selectedEmployee.value.id;
    mapForm.employee_code = selectedEmployee.value.employee_code;
  } else {
    mapForm.employee_id = null;
    mapForm.employee_code = '';
  }
}

function switchSubTab(key) {
  currentSubTab.value = key;
  if (key === 'sources') loadSources();
  else if (key === 'history') loadSyncLogs();
  else if (key === 'unmapped') loadUnmappedLogs();
}

async function loadSyncLogs() {
  if (sources.value.length === 0) return;
  loadingLogs.value = true;
  try {
    // Load logs for the first source or aggregate if available
    const srcId = sources.value[0].id;
    const res = await api.get(`/attendance-sources/${srcId}/sync-logs`);
    syncLogs.value = res.data.data ?? res.data;
  } catch (e) {
    toast.show('Không thể tải lịch sử đồng bộ', 'error');
  } finally {
    loadingLogs.value = false;
  }
}

async function loadUnmappedLogs() {
  if (sources.value.length === 0) return;
  loadingUnmapped.value = true;
  try {
    const srcId = sources.value[0].id;
    const res = await api.get(`/attendance-sources/${srcId}/unmapped-logs`);
    unmappedLogs.value = res.data.data ?? res.data;
  } catch (e) {
    toast.show('Không thể tải log chưa ánh xạ', 'error');
  } finally {
    loadingUnmapped.value = false;
  }
}

function openForm(src = null) {
  if (src) {
    Object.assign(form, {
      id: src.id,
      company_id: src.company_id,
      name: src.name,
      type: src.type || 'zktime_sql_server',
      host: src.host,
      port: src.port || 1433,
      database_name: src.database_name,
      username: src.username,
      password_encrypted: '', // blank password on edit
      timezone: src.timezone || 'Asia/Ho_Chi_Minh',
      user_table: src.user_table || 'USERINFO',
      checkinout_table: src.checkinout_table || 'CHECKINOUT',
      employee_code_field: src.employee_code_field || 'SSN',
      badge_field: src.badge_field || 'Badgenumber',
      check_time_field: src.check_time_field || 'CHECKTIME',
      is_active: src.is_active,
      sync_time: src.sync_time || '09:00'
    });
  } else {
    Object.assign(form, defaultForm());
  }
  showForm.value = true;
}

function closeForm() {
  showForm.value = false;
}

async function saveSource() {
  saving.value = true;
  try {
    const payload = { ...form };
    if (!payload.password_encrypted) delete payload.password_encrypted;

    if (form.id) {
      await api.put(`/attendance-sources/${form.id}`, payload);
      toast.show('Đã cập nhật nguồn kết nối ZKTime SQL.');
    } else {
      await api.post('/attendance-sources', payload);
      toast.show('Đã thêm nguồn kết nối ZKTime SQL.');
    }
    closeForm();
    await loadSources();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi lưu cấu hình nguồn', 'error');
  } finally {
    saving.value = false;
  }
}

async function deleteSource(src) {
  if (!confirm(`Bạn chắc chắn muốn xóa nguồn: ${src.name}? Tất cả lịch sử sync sẽ bị xóa.`)) return;
  try {
    await api.delete(`/attendance-sources/${src.id}`);
    toast.show('Đã xóa nguồn.');
    await loadSources();
  } catch (e) {
    toast.show('Lỗi khi xóa nguồn.', 'error');
  }
}

async function testConnection(src) {
  testing[src.id] = true;
  try {
    const res = await api.post(`/attendance-sources/${src.id}/test-connection`);
    const d = res.data.data ?? res.data;
    if (d.ok) {
      toast.show(`✓ ${d.message} (Nhân viên: ${d.user_count}, Logs: ${d.log_count})`, 'success');
    } else {
      toast.show(`✗ ${d.message}`, 'error');
    }
    await loadSources();
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi kiểm tra kết nối', 'error');
  } finally {
    testing[src.id] = false;
  }
}

function openSyncModal(src) {
  activeSource.value = src;
  syncResult.value = null;
  // Default dates: 1st of current month to today
  const today = new Date();
  const yyyy = today.getFullYear();
  const mm = String(today.getMonth() + 1).padStart(2, '0');
  const dd = String(today.getDate()).padStart(2, '0');
  
  syncForm.from = `${yyyy}-${mm}-01`;
  syncForm.to = `${yyyy}-${mm}-${dd}`;
  syncForm.dry_run = true;
  
  showSyncModal.value = true;
}

async function runSync() {
  syncing.value = true;
  syncResult.value = null;
  try {
    const res = await api.post(`/attendance-sources/${activeSource.value.id}/sync`, syncForm);
    const d = res.data.data ?? res.data;
    syncResult.value = d;
    toast.show(d.dry_run ? '✓ Hoàn thành chạy thử nghiệm (dry-run)' : '✓ Đồng bộ dữ liệu chấm công thành công!');
    if (!d.dry_run) {
      showSyncModal.value = false;
      await loadSources();
    }
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi khi chạy đồng bộ', 'error');
  } finally {
    syncing.value = false;
  }
}

function openSyncBadgeModal(src) {
  activeSource.value = src;
  syncBadgeResult.value = null;
  syncBadgeForm.dry_run = true;
  syncBadgeForm.force = false;
  showSyncBadgeModal.value = true;
}

async function runSyncBadge() {
  syncingBadge.value = true;
  syncBadgeResult.value = null;
  try {
    const res = await api.post(`/attendance-sources/${activeSource.value.id}/sync-badge-numbers`, syncBadgeForm);
    const d = res.data.data ?? res.data;
    syncBadgeResult.value = d;
    toast.show(d.dry_run ? '✓ Hoàn thành kiểm tra (dry-run)' : '✓ Đồng bộ mã vân tay thành công!');
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi khi chạy đồng bộ mã vân tay', 'error');
  } finally {
    syncingBadge.value = false;
  }
}

function openMapFormFromLog(log) {
  mapForm.company_id = log.company_id;
  mapForm.employee_id = null;
  mapForm.employee_code = '';
  mapForm.device_user_id = log.device_user_id;
  
  selectedEmployee.value = null;
  loadCompanyEmployees(log.company_id);
  showMappingModal.value = true;
}

async function saveMapping() {
  mappingSubmit.value = true;
  try {
    const res = await api.post('/attendance-sources/mappings', mapForm);
    const d = res.data.data ?? res.data;
    toast.show(`✓ Ghép nhân sự thành công. Đã tự động chuẩn hóa ${d.resolved_logs} log cũ.`);
    showMappingModal.value = false;
    if (currentSubTab.value === 'unmapped') {
      await loadUnmappedLogs();
    }
  } catch (e) {
    toast.show(e.response?.data?.message || 'Lỗi ghép mã nhân sự', 'error');
  } finally {
    mappingSubmit.value = false;
  }
}

function formatDatetime(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
}
</script>
