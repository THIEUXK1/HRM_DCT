<template>
  <div class="flex min-h-screen items-center justify-center p-6 bg-slate-50">
    <form class="hcm-card w-full max-w-md p-8" @submit.prevent="submit">
      <h2 class="text-2xl font-bold text-slate-900">Đổi mật khẩu</h2>
      <p class="mt-1 text-sm text-slate-500">
        Lần đăng nhập đầu tiên — bạn phải đổi mật khẩu trước khi sử dụng hệ thống.
      </p>

      <div class="mt-6 space-y-4">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Mật khẩu hiện tại</label>
          <input v-model="currentPassword" type="password" class="hcm-input" required autocomplete="current-password" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Mật khẩu mới</label>
          <input v-model="password" type="password" class="hcm-input" required minlength="8" autocomplete="new-password" />
          <p class="text-xs text-slate-500 mt-1">Tối thiểu 8 ký tự, có chữ hoa, chữ thường và số.</p>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Xác nhận mật khẩu mới</label>
          <input v-model="passwordConfirmation" type="password" class="hcm-input" required autocomplete="new-password" />
        </div>
      </div>

      <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>
      <button type="submit" class="hcm-btn-primary mt-6 w-full" :disabled="loading">
        {{ loading ? 'Đang lưu...' : 'Lưu mật khẩu mới' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api/client';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const currentPassword = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const error = ref('');
const loading = ref(false);

function resolvePostLoginRoute(user) {
  const perms = user?.permissions || [];
  const roles = user?.roles || [];
  const hasPunch = perms.includes('attendance.punch_gps') || perms.includes('attendance.punch_qr');
  const isHrStaff = roles.some((r) => ['admin', 'hr_manager', 'payroll_specialist', 'department_manager'].includes(r));
  if (hasPunch && !isHrStaff) {
    return { name: 'attendance-punch' };
  }
  return { name: 'dashboard' };
}

async function submit() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.post('/auth/change-password', {
      current_password: currentPassword.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    });
    auth.setUser(data.data.user);
    router.push(resolvePostLoginRoute({ user: data.data.user }));
  } catch (e) {
    error.value = e.response?.data?.message || 'Không đổi được mật khẩu.';
  } finally {
    loading.value = false;
  }
}
</script>
