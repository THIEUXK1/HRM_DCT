<template>
  <div class="min-h-screen bg-slate-50 py-10 px-4">
    <div class="mx-auto max-w-3xl">
      <header class="mb-8 text-center">
        <h1 class="text-2xl font-bold text-slate-900">Cơ hội nghề nghiệp</h1>
        <p class="text-slate-600 mt-1">Nộp hồ sơ trực tuyến — không cần đăng nhập</p>
      </header>

      <div v-if="!selectedJob" class="space-y-4">
        <div
          v-for="job in jobs"
          :key="job.id"
          class="hcm-card p-5 cursor-pointer hover:ring-2 hover:ring-primary-200"
          @click="selectJob(job)"
        >
          <h2 class="font-semibold text-lg">{{ job.title }}</h2>
          <p class="text-sm text-slate-500 mt-1">
            {{ job.recruitment_request?.company?.name }} · {{ job.recruitment_request?.department?.name || '—' }}
          </p>
          <p class="text-xs text-slate-400 mt-2 line-clamp-2">{{ stripHtml(job.job_description) }}</p>
        </div>
        <UiEmpty v-if="!jobs.length" title="Chưa có tin tuyển dụng công khai" />
      </div>

      <div v-else class="hcm-card p-6">
        <button type="button" class="text-sm text-primary-600 mb-4" @click="selectedJob = null">← Danh sách tin</button>
        <h2 class="text-xl font-bold">{{ selectedJob.title }}</h2>
        <div class="prose prose-sm mt-4 text-slate-700 whitespace-pre-wrap">{{ selectedJob.job_description || '—' }}</div>

        <form class="mt-8 space-y-3 border-t pt-6" @submit.prevent="submit">
          <h3 class="font-semibold">Nộp hồ sơ ứng tuyển</h3>
          <input v-model="form.full_name" class="hcm-input" required placeholder="Họ tên *" />
          <input v-model="form.email" type="email" class="hcm-input" placeholder="Email" />
          <input v-model="form.phone" class="hcm-input" placeholder="Điện thoại" />
          <textarea v-model="form.experience_summary" class="hcm-input" rows="3" placeholder="Kinh nghiệm / giới thiệu bản thân" />
          <input type="file" accept=".pdf,.doc,.docx" class="text-sm" @change="onFile" />
          <button type="submit" class="hcm-btn-primary w-full" :disabled="submitting">
            {{ submitting ? 'Đang gửi...' : 'Gửi hồ sơ' }}
          </button>
        </form>
        <p v-if="success" class="mt-4 text-center text-emerald-600 font-medium">{{ success }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import UiEmpty from '../../components/ui/UiEmpty.vue';

const route = useRoute();
const jobs = ref([]);
const selectedJob = ref(null);
const submitting = ref(false);
const success = ref('');
const cvFile = ref(null);
const form = ref({ full_name: '', email: '', phone: '', experience_summary: '' });

const publicApi = axios.create({ baseURL: '/api/v1/public', headers: { Accept: 'application/json' } });

function stripHtml(text) {
  if (!text) return '';
  return text.replace(/<[^>]+>/g, '').slice(0, 160);
}

function selectJob(job) {
  selectedJob.value = job;
  success.value = '';
}

function onFile(e) {
  cvFile.value = e.target.files?.[0] || null;
}

async function load() {
  const { data } = await publicApi.get('/job-posts', { params: { per_page: 50 } });
  const payload = data?.data;
  jobs.value = (payload?.data && Array.isArray(payload.data)) ? payload.data : (Array.isArray(payload) ? payload : []);
  const jobId = route.query.job;
  if (jobId) {
    const found = jobs.value.find((j) => String(j.id) === String(jobId));
    if (found) selectedJob.value = found;
  }
}

async function submit() {
  submitting.value = true;
  success.value = '';
  try {
    const fd = new FormData();
    Object.entries(form.value).forEach(([k, v]) => {
      if (v) fd.append(k, v);
    });
    if (cvFile.value) fd.append('file', cvFile.value);
    const { data } = await publicApi.post(`/job-posts/${selectedJob.value.id}/apply`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    success.value = data.data.message;
    form.value = { full_name: '', email: '', phone: '', experience_summary: '' };
    cvFile.value = null;
  } finally {
    submitting.value = false;
  }
}

onMounted(load);
</script>
