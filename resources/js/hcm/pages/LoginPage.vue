<template>

  <div class="flex min-h-screen">

    <div class="hidden flex-1 flex-col justify-between bg-gradient-to-br from-primary-800 to-slate-900 p-12 text-white lg:flex">

      <div>

        <p class="text-2xl font-bold">HCM Suite</p>

        <p class="mt-2 text-primary-100">Hệ thống quản trị nhân sự toàn diện</p>

      </div>

      <ul class="space-y-3 text-sm text-primary-100">

        <li>✓ Chấm công GPS / QR tại chi nhánh</li>

        <li>✓ Cổng nhân viên · Nghỉ phép</li>

        <li>✓ Tính lương · BHXH · Thuế TNCN</li>

      </ul>

      <p class="text-xs text-slate-400">© HCM Platform</p>

    </div>

    <div class="flex flex-1 items-center justify-center p-6">

      <form class="hcm-card w-full max-w-md p-8" @submit.prevent="submit">

        <h2 class="text-2xl font-bold text-slate-900">Đăng nhập</h2>

        <p v-if="isPunchLogin" class="mt-1 text-sm text-slate-500">Nhập <b>mã nhân viên</b> và mật khẩu HR đã cấp</p>
        <p v-else class="mt-1 text-sm text-slate-500">Quản trị HR: email · Nhân viên chấm công: <b>mã NV</b></p>



        <div v-if="sessionReason" class="mt-4 rounded-lg px-4 py-3 text-sm"

          :class="sessionReason === 'expired'

            ? 'bg-amber-50 border border-amber-200 text-amber-800'

            : 'bg-slate-50 border border-slate-200 text-slate-700'"

        >

          <span v-if="sessionReason === 'expired'">

            ⏱ Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.

          </span>

          <span v-else-if="sessionReason === 'unauthenticated'">

            🔒 Bạn cần đăng nhập để tiếp tuc.

          </span>

        </div>



        <div class="mt-6 space-y-4">

          <div>

            <label class="mb-1 block text-sm font-medium text-slate-700">{{ isPunchLogin ? 'Mã nhân viên' : 'Email hoặc mã NV' }}</label>

            <input v-model="login" type="text" class="hcm-input font-mono uppercase" required autocomplete="username" :placeholder="isPunchLogin ? 'EMP-001' : 'EMP-001 hoặc email@...'" />

          </div>

          <div>

            <label class="mb-1 block text-sm font-medium text-slate-700">Mật khẩu</label>

            <input v-model="password" type="password" class="hcm-input" required autocomplete="current-password" />

          </div>

        </div>

        <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>

        <button type="submit" class="hcm-btn-primary mt-6 w-full" :disabled="loading">

          {{ loading ? 'Đang đăng nhập...' : 'Đăng nhập' }}

        </button>

        <p class="mt-4 text-center text-xs text-slate-400">
          <template v-if="isPunchLogin">Tài khoản = mã NV · MK mặc định HR cấp · đổi MK lần đầu</template>
          <template v-else>HR: admin@example.com · NV: mã NV (vd. EMP-001)</template>
        </p>

      </form>

    </div>

  </div>

</template>



<script setup>

import { ref, computed } from 'vue';

import { useRouter, useRoute } from 'vue-router';

import { useAuthStore } from '../stores/auth';



const auth   = useAuthStore();

const router = useRouter();

const route  = useRoute();



const login    = ref('');
const password = ref('');

const error    = ref('');

const loading  = ref(false);



const sessionReason = computed(() => route.query.reason || null);
const isPunchLogin = computed(() => route.query.mode === 'punch' || route.query.for === 'punch');

function resolvePostLoginRoute(payload) {
  const perms = payload.user?.permissions || [];
  const roles = payload.user?.roles || [];
  const hasPunch = perms.includes('attendance.punch_gps') || perms.includes('attendance.punch_qr');
  const isHrStaff = roles.some((r) => ['admin', 'hr_manager', 'payroll_specialist', 'department_manager'].includes(r));
  if (hasPunch && !isHrStaff) {
    return { name: 'attendance-punch' };
  }
  return { name: 'dashboard' };
}



async function submit() {

  loading.value = true;

  error.value   = '';

  try {

    const payload = await auth.login(login.value.trim(), password.value);

    if (payload.must_change_password || payload.user?.must_change_password) {

      router.push({ name: 'change-password' });

      return;

    }

    const redirect = route.query.redirect;

    if (redirect && !redirect.startsWith('/app')) {

      router.push(redirect);

      return;

    }

    router.push(resolvePostLoginRoute(payload));

  } catch (e) {

    error.value = e.response?.data?.message || 'Đăng nhập thất bại. Kiểm tra server đang chạy cổng 8001.';

  } finally {

    loading.value = false;

  }

}

</script>


