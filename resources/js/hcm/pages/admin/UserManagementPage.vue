<template>
  <div class="space-y-6">
    <UiPageHeader
      title="Quản lý tài khoản người dùng"
      subtitle="Phân quyền vai trò · Cấp truy cập công ty · Đặt lại mật khẩu"
      breadcrumb="Quản trị hệ thống"
    />

    <!-- ── SECTION 1: VAI TRÒ (collapsible) ────────────────────────── -->
    <div class="hcm-card overflow-hidden">
      <!-- Header — luôn hiển thị, click để toggle -->
      <button
        type="button"
        class="flex w-full items-center justify-between px-5 py-4 text-left hover:bg-slate-50 transition-colors"
        @click="rolesExpanded = !rolesExpanded"
      >
        <div>
          <h2 class="text-base font-semibold text-slate-900">Danh sách Vai trò Hệ thống</h2>
          <p class="text-sm text-slate-500 mt-0.5">
            Quản lý vai trò RBAC và cấu hình quyền hạn tương ứng ·
            <span class="font-medium text-primary-600">{{ roles.length }} vai trò</span>
          </p>
        </div>
        <span class="ml-4 shrink-0 text-slate-400 text-sm transition-transform duration-200" :class="rolesExpanded ? 'rotate-180' : ''">
          ▼
        </span>
      </button>

      <!-- Body — ẩn mặc định -->
      <div v-show="rolesExpanded" class="border-t border-slate-100 px-5 pb-5 pt-4 space-y-4">
        <div class="flex justify-end">
          <button type="button" class="hcm-btn-primary text-sm" @click="openCreateRole">+ Thêm vai trò mới</button>
        </div>

        <div v-if="loadingRoles" class="py-8 text-center text-slate-400 text-sm">Đang tải...</div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <div
            v-for="role in roles"
            :key="role.id"
            class="flex flex-col justify-between rounded-xl border border-slate-200 bg-slate-50 p-4 hover:shadow-sm transition-all"
          >
            <div>
              <div class="flex items-start justify-between gap-2">
                <span class="font-bold text-slate-800">{{ role.name }}</span>
                <UiBadge variant="default">{{ role.guard_name }}</UiBadge>
              </div>
              <p class="mt-1.5 text-xs text-slate-500">
                <span class="font-semibold text-primary-600">{{ role.permissions?.length || 0 }}</span> quyền hạn
              </p>
            </div>
            <div class="mt-4 flex gap-2 border-t border-slate-200/60 pt-3">
              <button type="button" class="hcm-btn-primary flex-1 py-1 text-xs" @click="openRolePerms(role)">
                Cấu hình Quyền
              </button>
              <button
                type="button"
                class="hcm-btn-secondary py-1 text-xs text-rose-600 border-rose-200 hover:bg-rose-50"
                :disabled="PROTECTED_ROLES.includes(role.name)"
                :title="PROTECTED_ROLES.includes(role.name) ? 'Không thể xóa vai trò hệ thống' : ''"
                @click="deleteRole(role)"
              >
                Xóa
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── SECTION 2: NGƯỜI DÙNG ──────────────────────────────────── -->
    <div class="hcm-card p-5 space-y-4">
      <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 pb-3">
        <div class="flex-1 min-w-0">
          <h2 class="text-base font-semibold text-slate-900">Tài khoản người dùng</h2>
          <p class="text-sm text-slate-500 mt-0.5">Phân quyền truy cập công ty · Đặt lại mật khẩu</p>
        </div>
        <div class="flex items-center gap-2">
          <UiSearchInput v-model="userSearch" placeholder="Tìm theo tên, email..." @search="loadUsers" />
          <span class="text-xs text-slate-400 whitespace-nowrap">{{ users.length }} tài khoản</span>
        </div>
      </div>

      <div v-if="loadingUsers" class="flex items-center justify-center py-10 text-slate-400 text-sm gap-2">
        <span class="animate-spin inline-block w-4 h-4 border-2 border-slate-300 border-t-primary-500 rounded-full"></span>
        Đang tải...
      </div>
      <div v-else class="overflow-x-auto">
        <table class="hcm-table w-full">
          <thead>
            <tr>
              <th>Tên</th>
              <th>Email</th>
              <th>Nhân viên</th>
              <th>Vai trò (theo CTTV)</th>
              <th>Đổi MK lần cuối</th>
              <th>Trạng thái MK</th>
              <th class="text-right pr-4">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!users.length">
              <td colspan="7" class="py-10 text-center text-slate-400 text-sm">Không có tài khoản nào.</td>
            </tr>
            <tr v-for="u in users" :key="u.id" class="hover:bg-slate-50/60">
              <td class="font-medium text-slate-800">{{ u.name }}</td>
              <td class="font-mono text-xs text-slate-500">{{ u.email }}</td>
              <td class="text-sm text-slate-600">
                <span v-if="u.employee">
                  {{ u.employee.full_name }}
                  <span class="text-slate-400 text-xs">({{ u.employee.employee_code }})</span>
                </span>
                <span v-else class="text-slate-400">—</span>
              </td>
              <td>
                <div class="flex flex-wrap gap-1 max-w-xs">
                  <template v-if="Object.keys(u.company_roles || {}).length">
                    <UiBadge v-for="(roleList, cid) in u.company_roles" :key="cid" variant="success">
                      {{ companyCode(cid) }}: {{ roleList.join(', ') }}
                    </UiBadge>
                  </template>
                  <template v-else-if="u.roles?.length">
                    <UiBadge v-for="r in u.roles" :key="r.id" :variant="r.name === 'admin' ? 'danger' : 'default'">
                      {{ r.name }}
                    </UiBadge>
                  </template>
                  <span v-else class="text-xs text-slate-400">Chưa phân quyền</span>
                </div>
              </td>
              <td class="text-xs text-slate-500 whitespace-nowrap">
                {{ u.password_changed_at ? fmtDate(u.password_changed_at) : 'Chưa đổi bao giờ' }}
              </td>
              <td>
                <span
                  v-if="u.must_change_password"
                  class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-700"
                >⚠ Phải đổi lại</span>
                <span v-else class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">
                  ✓ Bình thường
                </span>
              </td>
              <td class="text-right">
                <div class="flex items-center justify-end gap-2">
                  <button type="button" class="hcm-btn-secondary text-xs py-1 px-2.5" @click="openUserAccess(u)">
                    Phân quyền
                  </button>
                  <button type="button" class="hcm-btn-secondary text-xs py-1 px-2.5" @click="openReset(u)">
                    🔑 Đặt lại MK
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── MODAL: TẠO VAI TRÒ MỚI ────────────────────────────────── -->
    <UiModal v-model="showCreateRoleModal" title="Tạo vai trò bảo mật mới">
      <form class="space-y-4" @submit.prevent="saveNewRole">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Tên vai trò <span class="text-rose-500">*</span></label>
          <input
            v-model="newRoleName"
            class="hcm-input w-full"
            required
            placeholder="VD: hr_assistant, director"
            pattern="[a-z0-9_]+"
            title="Chỉ dùng chữ thường, số và gạch dưới"
          />
          <p class="text-xs text-slate-500 mt-1">Chỉ dùng chữ thường, số và gạch dưới (_). Không có dấu cách.</p>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" class="hcm-btn-secondary" @click="showCreateRoleModal = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="savingRole">
            {{ savingRole ? 'Đang tạo...' : 'Tạo vai trò' }}
          </button>
        </div>
      </form>
    </UiModal>

    <!-- ── MODAL: CẤU HÌNH QUYỀN HẠN VAI TRÒ ─────────────────────── -->
    <UiModal v-model="showRolePermsModal" :title="`Cấu hình quyền: ${selectedRole?.name}`" size="lg">
      <form class="space-y-5" @submit.prevent="saveRolePerms">
        <div class="max-h-[60vh] overflow-y-auto pr-1 space-y-4">
          <div
            v-for="(permsList, catName) in PERMISSION_CATEGORIES"
            :key="catName"
            class="rounded-xl border border-slate-200/60 bg-slate-50 p-4"
          >
            <h4 class="mb-3 flex items-center justify-between border-b border-slate-200 pb-1.5 text-sm font-bold text-slate-900">
              <span>{{ catName }}</span>
              <button type="button" class="text-xs text-primary-600 font-semibold hover:underline" @click="toggleCategory(permsList)">
                Chọn tất cả / Bỏ chọn
              </button>
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <label
                v-for="p in permsList"
                :key="p"
                class="flex cursor-pointer select-none items-center gap-2 text-sm text-slate-700"
              >
                <input v-model="selectedPerms" type="checkbox" :value="p" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                <span class="font-mono text-xs">{{ p }}</span>
              </label>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-1">
          <button type="button" class="hcm-btn-secondary" @click="showRolePermsModal = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="savingRole">
            {{ savingRole ? 'Đang lưu...' : 'Lưu quyền hạn' }}
          </button>
        </div>
      </form>
    </UiModal>

    <!-- ── MODAL: PHÂN QUYỀN USER ─────────────────────────────────── -->
    <UiModal v-model="showAccessModal" :title="`Phân quyền: ${selectedUser?.name}`">
      <form class="space-y-4" @submit.prevent="saveUserAccess">
        <!-- User summary -->
        <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 font-bold text-sm text-primary-700">
            {{ selectedUser?.name?.charAt(0)?.toUpperCase() }}
          </div>
          <div>
            <p class="font-semibold text-slate-800 text-sm">{{ selectedUser?.name }}</p>
            <p class="text-xs text-slate-500">{{ selectedUser?.email }}</p>
          </div>
        </div>

        <!-- Công ty -->
        <div>
          <label class="block text-sm font-semibold text-slate-800 mb-1">Công ty được truy cập</label>
          <p class="text-xs text-slate-500 mb-2">Chọn một hoặc nhiều công ty trong tập đoàn.</p>
          <div class="max-h-36 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-1.5">
            <label v-for="c in companies" :key="c.id" class="flex cursor-pointer items-center gap-2 text-sm">
              <input v-model="accessCompanyIds" type="checkbox" :value="c.id" class="rounded text-primary-600" />
              <span>{{ c.name }} <span class="text-slate-400 font-mono text-xs">({{ c.code }})</span></span>
            </label>
            <p v-if="!companies.length" class="text-xs text-slate-400">Không có công ty.</p>
          </div>
        </div>

        <!-- Vai trò -->
        <div>
          <label class="block text-sm font-semibold text-slate-800 mb-1">Vai trò tại các công ty đã chọn</label>
          <p class="text-xs text-slate-500 mb-2">Áp dụng đồng nhất cho mọi công ty được tick.</p>
          <div class="grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
            <label v-for="r in assignableRoles" :key="r" class="flex cursor-pointer items-center gap-2 text-sm">
              <input v-model="accessRoles" type="checkbox" :value="r" class="rounded text-primary-600" />
              <span>{{ r }}</span>
            </label>
            <p v-if="!assignableRoles.length" class="text-xs text-slate-400">Đang tải...</p>
          </div>
        </div>

        <!-- Công ty mặc định -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Công ty mặc định khi đăng nhập</label>
          <select v-model="accessDefaultCompanyId" class="hcm-input w-full">
            <option :value="null">— Tự chọn —</option>
            <option v-for="id in accessCompanyIds" :key="id" :value="Number(id)">
              {{ companies.find((c) => c.id === Number(id))?.name }}
            </option>
          </select>
        </div>

        <div v-if="accessError" class="rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-sm text-rose-700">{{ accessError }}</div>

        <div class="flex justify-end gap-3 pt-1">
          <button type="button" class="hcm-btn-secondary" @click="showAccessModal = false">Hủy</button>
          <button
            type="submit"
            class="hcm-btn-primary"
            :disabled="savingAccess || !accessCompanyIds.length || !accessRoles.length"
          >
            {{ savingAccess ? 'Đang lưu...' : 'Lưu phân quyền' }}
          </button>
        </div>
      </form>
    </UiModal>

    <!-- ── MODAL: ĐẶT LẠI MẬT KHẨU ──────────────────────────────── -->
    <UiModal v-model="showResetModal" title="Đặt lại mật khẩu">
      <div class="mb-4 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 font-bold text-sm text-primary-700">
          {{ selectedUser?.name?.charAt(0)?.toUpperCase() }}
        </div>
        <div>
          <p class="font-semibold text-slate-800 text-sm">{{ selectedUser?.name }}</p>
          <p class="text-xs text-slate-500">{{ selectedUser?.email }}</p>
        </div>
      </div>

      <form class="space-y-4" @submit.prevent="doReset">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Mật khẩu mới <span class="text-rose-500">*</span></label>
          <input v-model="resetForm.password" type="password" class="hcm-input w-full" placeholder="Tối thiểu 8 ký tự" minlength="8" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Xác nhận mật khẩu <span class="text-rose-500">*</span></label>
          <input v-model="resetForm.password_confirmation" type="password" class="hcm-input w-full" minlength="8" required />
          <p v-if="passwordMismatch" class="mt-1 text-xs text-rose-500">Mật khẩu không khớp</p>
        </div>
        <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
          <input v-model="resetForm.must_change_password" type="checkbox" class="rounded text-primary-600" />
          <span class="text-sm text-slate-700">Bắt buộc người dùng đổi mật khẩu khi đăng nhập lần tới</span>
        </label>
        <div v-if="resetError" class="rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-sm text-rose-700">{{ resetError }}</div>
        <div class="flex justify-end gap-3 pt-1">
          <button type="button" class="hcm-btn-secondary" @click="showResetModal = false">Hủy</button>
          <button type="submit" class="hcm-btn-primary" :disabled="savingReset || passwordMismatch">
            {{ savingReset ? 'Đang lưu...' : 'Xác nhận đặt lại' }}
          </button>
        </div>
      </form>
    </UiModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../api/client';
import { useToast } from '../../composables/useToast';
import { useAppStore } from '../../stores/app';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiSearchInput from '../../components/ui/UiSearchInput.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiModal from '../../components/ui/UiModal.vue';

const toast = useToast();
const appStore = useAppStore();

// ─── Constants ──────────────────────────────────────────────────────────────
const PROTECTED_ROLES = ['admin', 'employee', 'hr_manager', 'auditor', 'department_manager'];

const PERMISSION_CATEGORIES = {
  'Hồ sơ Nhân viên': ['employees.view', 'employees.create', 'employees.edit', 'employees.delete'],
  'Hợp đồng lao động': ['employment_contracts.view', 'employment_contracts.create', 'employment_contracts.edit'],
  'Bảo hiểm xã hội': ['bhxh.export', 'bhxh.manage'],
  'Chấm công & Nghỉ phép': ['attendance.view', 'attendance.manage', 'leave.view', 'leave.manage', 'leave.approve'],
  'Tính toán Lương': ['payroll.view', 'payroll.manage', 'payroll.approve'],
  'Tuyển dụng & Onboarding': ['candidates.view', 'candidates.manage'],
  'Hộp thư phê duyệt': ['approvals.view', 'approvals.act'],
  'Đào tạo & Năng lực': ['training.view', 'training.manage', 'competency.view', 'competency.manage', 'performance.view', 'performance.manage'],
  'Cấu hình Công ty': ['companies.view', 'companies.create', 'companies.edit', 'companies.delete',
    'branches.view', 'branches.create', 'branches.edit', 'branches.delete',
    'departments.view', 'departments.create', 'departments.edit', 'departments.delete',
    'positions.view', 'positions.create', 'positions.edit', 'positions.delete',
    'audit_logs.view', 'users.manage'],
};

// ─── State: Roles ────────────────────────────────────────────────────────────
const rolesExpanded = ref(false);
const roles = ref([]);
const loadingRoles = ref(false);
const showCreateRoleModal = ref(false);
const newRoleName = ref('');
const savingRole = ref(false);

const showRolePermsModal = ref(false);
const selectedRole = ref(null);
const selectedPerms = ref([]);

// ─── State: Users ────────────────────────────────────────────────────────────
const users = ref([]);
const loadingUsers = ref(false);
const userSearch = ref('');
const assignableRoles = ref([]);

// ─── State: Access modal ─────────────────────────────────────────────────────
const showAccessModal = ref(false);
const selectedUser = ref(null);
const accessCompanyIds = ref([]);
const accessRoles = ref([]);
const accessDefaultCompanyId = ref(null);
const savingAccess = ref(false);
const accessError = ref('');

// ─── State: Reset password modal ─────────────────────────────────────────────
const showResetModal = ref(false);
const resetForm = ref({ password: '', password_confirmation: '', must_change_password: true });
const savingReset = ref(false);
const resetError = ref('');

const passwordMismatch = computed(() =>
  resetForm.value.password_confirmation.length > 0 &&
  resetForm.value.password !== resetForm.value.password_confirmation,
);

const companies = computed(() => appStore.companies || []);

// ─── Helpers ─────────────────────────────────────────────────────────────────
function fmtDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function companyCode(companyId) {
  return companies.value.find((c) => c.id === Number(companyId))?.code || `#${companyId}`;
}

// ─── Roles ───────────────────────────────────────────────────────────────────
async function loadRoles() {
  loadingRoles.value = true;
  try {
    const { data } = await api.get('/roles');
    roles.value = data.data || [];
  } finally {
    loadingRoles.value = false;
  }
}

function openCreateRole() {
  newRoleName.value = '';
  showCreateRoleModal.value = true;
}

async function saveNewRole() {
  savingRole.value = true;
  try {
    await api.post('/roles', { name: newRoleName.value });
    toast.show('Đã thêm vai trò mới');
    showCreateRoleModal.value = false;
    await loadRoles();
    await loadAssignableRoles();
  } catch (e) {
    toast.show(e?.response?.data?.message || 'Tên vai trò đã tồn tại hoặc không hợp lệ', 'error');
  } finally {
    savingRole.value = false;
  }
}

function openRolePerms(role) {
  selectedRole.value = { ...role };
  selectedPerms.value = (role.permissions || []).map((p) => p.name);
  showRolePermsModal.value = true;
}

function toggleCategory(permsList) {
  const allSelected = permsList.every((p) => selectedPerms.value.includes(p));
  if (allSelected) {
    selectedPerms.value = selectedPerms.value.filter((p) => !permsList.includes(p));
  } else {
    permsList.forEach((p) => { if (!selectedPerms.value.includes(p)) selectedPerms.value.push(p); });
  }
}

async function saveRolePerms() {
  savingRole.value = true;
  try {
    await api.put(`/roles/${selectedRole.value.id}`, {
      name: selectedRole.value.name,
      permissions: selectedPerms.value,
    });
    toast.show(`Đã cập nhật quyền cho vai trò ${selectedRole.value.name}`);
    showRolePermsModal.value = false;
    await loadRoles();
  } catch {
    toast.show('Không thể lưu quyền hạn', 'error');
  } finally {
    savingRole.value = false;
  }
}

async function deleteRole(role) {
  if (!confirm(`Xóa vai trò "${role.name}"?`)) return;
  try {
    await api.delete(`/roles/${role.id}`);
    toast.show('Đã xóa vai trò');
    await loadRoles();
  } catch (e) {
    toast.show(e?.response?.data?.message || 'Không thể xóa vai trò này', 'error');
  }
}

// ─── Users ───────────────────────────────────────────────────────────────────
async function loadUsers() {
  loadingUsers.value = true;
  try {
    const params = { per_page: 200 };
    if (userSearch.value.trim()) params.search = userSearch.value.trim();
    const { data } = await api.get('/users', { params });
    const payload = data?.data;
    users.value = Array.isArray(payload) ? payload : (payload?.data ?? []);
  } finally {
    loadingUsers.value = false;
  }
}

async function loadAssignableRoles() {
  try {
    const { data } = await api.get('/users/assignable-roles');
    assignableRoles.value = data.data || [];
  } catch {
    assignableRoles.value = [];
  }
}

// ─── User Access modal ────────────────────────────────────────────────────────
async function openUserAccess(u) {
  selectedUser.value = { ...u };
  accessCompanyIds.value = [];
  accessRoles.value = [];
  accessDefaultCompanyId.value = u.default_company_id || null;
  accessError.value = '';
  try {
    const { data } = await api.get(`/users/${u.id}/company-access`);
    accessCompanyIds.value = (data.data.companies || []).map((c) => c.id);
    const roleSet = new Set();
    Object.values(data.data.company_roles || {}).forEach((rs) => rs.forEach((r) => roleSet.add(r)));
    accessRoles.value = [...roleSet];
    accessDefaultCompanyId.value = data.data.default_company_id || accessCompanyIds.value[0] || null;
  } catch {
    accessCompanyIds.value = (u.companies || []).map((c) => c.id);
  }
  showAccessModal.value = true;
}

async function saveUserAccess() {
  savingAccess.value = true;
  accessError.value = '';
  try {
    await api.put(`/users/${selectedUser.value.id}/access`, {
      company_ids: accessCompanyIds.value.map(Number),
      roles: accessRoles.value,
      default_company_id: accessDefaultCompanyId.value ? Number(accessDefaultCompanyId.value) : null,
    });
    toast.show(`Đã cập nhật phân quyền cho ${selectedUser.value.name}`);
    showAccessModal.value = false;
    await loadUsers();
  } catch (e) {
    accessError.value = e?.response?.data?.message || 'Không thể lưu phân quyền';
  } finally {
    savingAccess.value = false;
  }
}

// ─── Reset Password modal ─────────────────────────────────────────────────────
function openReset(u) {
  selectedUser.value = u;
  resetForm.value = { password: '', password_confirmation: '', must_change_password: true };
  resetError.value = '';
  showResetModal.value = true;
}

async function doReset() {
  if (passwordMismatch.value) return;
  savingReset.value = true;
  resetError.value = '';
  try {
    await api.put(`/users/${selectedUser.value.id}/reset-password`, resetForm.value);
    toast.show(`Đã đặt lại mật khẩu cho ${selectedUser.value.name}`);
    showResetModal.value = false;
    const u = users.value.find((x) => x.id === selectedUser.value.id);
    if (u) { u.must_change_password = resetForm.value.must_change_password; u.password_changed_at = null; }
  } catch (e) {
    resetError.value = e?.response?.data?.message || 'Có lỗi xảy ra, vui lòng thử lại.';
  } finally {
    savingReset.value = false;
  }
}

// ─── Init ─────────────────────────────────────────────────────────────────────
onMounted(() => {
  Promise.all([loadRoles(), loadUsers(), loadAssignableRoles(), appStore.loadCompanies()]);
});
</script>
