<template>
  <div>
    <UiPageHeader title="Tuyển dụng (ATS)" subtitle="Yêu cầu · Tin tuyển · Pipeline · Talent pool" breadcrumb="Recruitment" />

    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
        :class="tab === t.key ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500'"
        @click="tab = t.key"
      >
        {{ t.label }}
      </button>
    </div>

    <div class="hcm-card mb-4 p-4">
      <UiSearchInput
        v-model="listSearch"
        :placeholder="searchPlaceholder"
        @search="onListSearch"
      />
    </div>

    <!-- Yêu cầu tuyển dụng -->
    <div v-if="tab === 'requests'" class="space-y-4">
      <div class="flex justify-end">
        <button type="button" class="hcm-btn-primary" @click="showReqForm = true">+ Yêu cầu tuyển dụng</button>
      </div>
      <div v-for="r in filteredRequests" :key="r.id" class="hcm-card p-4 flex flex-wrap justify-between gap-3">
        <div>
          <p class="font-semibold">{{ r.code }} — {{ r.title }}</p>
          <p class="text-sm text-slate-500">{{ r.department?.name }} · {{ r.headcount }} vị trí</p>
          <UiBadge class="mt-2">{{ statusLabel(r.status) }}</UiBadge>
        </div>
        <div class="flex flex-wrap gap-2">
          <button v-if="r.status === 'draft' || r.status === 'rejected'" type="button" class="hcm-btn-secondary text-xs" @click="submitRequest(r.id)">Gửi duyệt</button>
          <button v-if="r.status === 'approved'" type="button" class="hcm-btn-primary text-xs" @click="openJobForm(r)">+ Tin tuyển dụng</button>
        </div>
      </div>
      <UiEmpty v-if="!filteredRequests.length" title="Chưa có yêu cầu" />
    </div>

    <!-- Tin tuyển dụng -->
    <div v-if="tab === 'jobs'" class="space-y-3">
      <div v-for="j in filteredJobPosts" :key="j.id" class="hcm-card p-4">
        <div class="flex justify-between gap-2">
          <div>
            <p class="font-semibold">{{ j.title }}</p>
            <p class="text-xs text-slate-500">{{ j.channel || '—' }} · {{ statusLabel(j.status) }}</p>
          </div>
          <div class="flex gap-2">
            <button v-if="j.status === 'draft'" type="button" class="hcm-btn-primary text-xs" @click="publishJob(j.id)">Đăng tin</button>
            <button v-if="j.status === 'published'" type="button" class="hcm-btn-secondary text-xs" @click="copyCareerLink(j.id)">Link ứng tuyển</button>
          </div>
        </div>
      </div>
      <UiEmpty v-if="!filteredJobPosts.length" title="Chưa có tin tuyển dụng" />
    </div>

    <!-- Ứng viên -->
    <div v-if="tab === 'candidates'">
      <div class="mb-4 flex justify-between">
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 flex-1 mr-4">
          <button
            v-for="s in pipelineStages"
            :key="s.key"
            type="button"
            class="hcm-card p-2 text-center text-xs"
            :class="stageFilter === s.key ? 'ring-2 ring-primary-500' : ''"
            @click="stageFilter = stageFilter === s.key ? '' : s.key"
          >
            <p class="font-bold">{{ countStage(s.key) }}</p>
            <p class="text-slate-500">{{ s.label }}</p>
          </button>
        </div>
        <button type="button" class="hcm-btn-primary shrink-0" @click="showCandForm = true">+ Ứng viên</button>
      </div>
      <div class="hcm-card overflow-hidden">
        <table class="hcm-table w-full">
          <thead>
            <tr>
              <th>Ứng viên</th>
              <th>Giai đoạn</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in filteredCandidates" :key="c.id" class="hover:bg-slate-50">
              <td>
                <p class="font-medium">{{ c.full_name }}</p>
                <p class="text-xs text-slate-500">{{ c.email || c.phone }}</p>
              </td>
              <td><UiBadge>{{ statusLabel(c.stage) }}</UiBadge></td>
              <td class="text-right space-x-1">
                <button type="button" class="text-xs text-primary-600" @click="openDetail(c)">Chi tiết</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Talent pool -->
    <div v-if="tab === 'talent'">
      <div class="space-y-2">
        <div v-for="c in talentPool" :key="c.id" class="hcm-card p-3 flex justify-between">
          <span>{{ c.full_name }}</span>
          <button type="button" class="text-xs text-primary-600" @click="openDetail(c)">Mở</button>
        </div>
      </div>
      <UiEmpty v-if="!talentPool.length" title="Talent pool trống" />
    </div>

    <!-- Modal yêu cầu -->
    <UiModal v-model="showReqForm" title="Yêu cầu tuyển dụng">
      <form class="space-y-3" @submit.prevent="saveRequest">
        <input v-model="reqForm.title" class="hcm-input" placeholder="Tiêu đề *" required />
        <select v-model="reqForm.department_id" class="hcm-input">
          <option :value="null">— Phòng ban —</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <select v-model="reqForm.position_id" class="hcm-input">
          <option :value="null">— Chức danh —</option>
          <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <input v-model.number="reqForm.headcount" type="number" min="1" class="hcm-input" placeholder="Số lượng" />
        <textarea v-model="reqForm.description" class="hcm-input" rows="3" placeholder="Mô tả / JD nội bộ" />
        <button type="submit" class="hcm-btn-primary w-full">Lưu nháp</button>
      </form>
    </UiModal>

    <!-- Modal tin tuyển -->
    <UiModal v-model="showJobForm" title="Tạo tin tuyển dụng">
      <form class="space-y-3" @submit.prevent="saveJob">
        <input v-model="jobForm.title" class="hcm-input" required placeholder="Tiêu đề tin" />
        <textarea v-model="jobForm.job_description" class="hcm-input" rows="4" placeholder="Mô tả công việc (JD)" />
        <input v-model="jobForm.channel" class="hcm-input" placeholder="Kênh (LinkedIn, CareerViet...)" />
        <button type="submit" class="hcm-btn-primary w-full">Tạo</button>
      </form>
    </UiModal>

    <!-- Modal ứng viên mới -->
    <UiModal v-model="showCandForm" title="Thêm ứng viên">
      <form class="space-y-3" @submit.prevent="saveCandidate">
        <select v-model="candForm.job_post_id" class="hcm-input">
          <option :value="null">— Tin tuyển dụng —</option>
          <option v-for="j in publishedJobs" :key="j.id" :value="j.id">{{ j.title }}</option>
        </select>
        <input v-model="candForm.full_name" class="hcm-input" required placeholder="Họ tên" />
        <input v-model="candForm.email" type="email" class="hcm-input" placeholder="Email" />
        <input v-model="candForm.phone" class="hcm-input" placeholder="Điện thoại" />
        <input v-model="candForm.source" class="hcm-input" placeholder="Nguồn" />
        <textarea v-model="candForm.experience_summary" class="hcm-input" rows="2" placeholder="Kinh nghiệm" />
        <button type="submit" class="hcm-btn-primary w-full">Lưu</button>
      </form>
    </UiModal>

    <!-- Chi tiết ứng viên -->
    <UiModal v-model="showDetail" :title="detail?.full_name || 'Ứng viên'" wide>
      <div v-if="detail" class="space-y-4 text-sm">
        <div class="flex flex-wrap gap-2">
          <select v-model="detailStage" class="hcm-input max-w-xs" @change="saveStage">
            <option v-for="(lbl, key) in meta.candidate_stages" :key="key" :value="key">{{ lbl }}</option>
          </select>
          <button type="button" class="hcm-btn-secondary text-xs" @click="rejectCand">Từ chối</button>
          <button type="button" class="hcm-btn-secondary text-xs" @click="toTalent">Talent pool</button>
        </div>

        <section>
          <h4 class="font-semibold mb-2">CV / Tài liệu</h4>
          <input type="file" class="text-xs" @change="uploadCv" />
          <ul class="mt-2 space-y-1">
            <li v-for="d in detail.documents" :key="d.id">{{ d.file_name }} ({{ d.type }})</li>
          </ul>
        </section>

        <section>
          <h4 class="font-semibold mb-2">Phỏng vấn</h4>
          <button type="button" class="hcm-btn-secondary text-xs mb-2" @click="showInterviewForm = true">+ Lịch PV</button>
          <div v-for="iv in detail.interviews" :key="iv.id" class="border rounded p-2 mb-2">
            <p>Vòng {{ iv.round }} — {{ iv.scheduled_at }} — {{ statusLabel(iv.status) }}</p>
            <button type="button" class="text-primary-600 text-xs mt-1" @click="openFeedbackForm(iv.id)">Ghi feedback / scorecard</button>
          </div>
        </section>

        <section>
          <h4 class="font-semibold mb-2">Offer</h4>
          <button type="button" class="hcm-btn-secondary text-xs mb-2" @click="showOfferForm = true">Tạo offer</button>
          <div v-for="o in detail.offers" :key="o.id" class="border rounded p-2 mb-2 flex flex-wrap justify-between gap-2">
            <span>{{ money(o.salary_base) }} · {{ statusLabel(o.status) }} · {{ o.start_date }}</span>
            <div class="flex gap-2">
              <button type="button" class="text-xs text-slate-600" @click="printOfferLetter(o.id)">Thư mời NV</button>
              <button v-if="o.status === 'pending'" type="button" class="text-xs text-primary-600" @click="acceptOffer(o.id)">UV đồng ý</button>
            </div>
          </div>
        </section>

        <button
          v-if="!detail.employee_id && hasAcceptedOffer"
          type="button"
          class="hcm-btn-primary w-full"
          @click="showHireForm = true"
        >
          Tuyển → Nhân viên + Onboarding
        </button>
        <UiBadge v-else-if="detail.employee_id" variant="success">Đã là nhân viên</UiBadge>
        <p v-else class="text-amber-600 text-xs">Cần offer đã chấp nhận (accepted) trước khi tuyển.</p>
      </div>
    </UiModal>

    <UiModal v-model="showInterviewForm" title="Lịch phỏng vấn">
      <form class="space-y-3" @submit.prevent="saveInterview">
        <input v-model="interviewForm.scheduled_at" type="datetime-local" class="hcm-input" required />
        <input v-model.number="interviewForm.round" type="number" min="1" class="hcm-input" placeholder="Vòng" />
        <input v-model="interviewForm.location" class="hcm-input" placeholder="Địa điểm / link online" />
        <button type="submit" class="hcm-btn-primary w-full">Lưu lịch</button>
      </form>
    </UiModal>

    <UiModal v-model="showFeedbackForm" title="Feedback phỏng vấn">
      <form class="space-y-3" @submit.prevent="saveFeedback">
        <input v-model.number="feedbackForm.score" type="number" min="0" max="100" class="hcm-input" placeholder="Điểm tổng (0-100)" />
        <select v-model="feedbackForm.recommendation" class="hcm-input">
          <option value="hire">Đề xuất tuyển</option>
          <option value="maybe">Cân nhắc</option>
          <option value="no_hire">Không tuyển</option>
        </select>
        <div v-for="(row, i) in feedbackForm.scorecard" :key="i" class="grid grid-cols-2 gap-2">
          <input v-model="row.criterion" class="hcm-input text-xs" />
          <select v-model.number="row.score" class="hcm-input text-xs">
            <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
          </select>
        </div>
        <textarea v-model="feedbackForm.feedback" class="hcm-input" rows="2" placeholder="Nhận xét" />
        <button type="submit" class="hcm-btn-primary w-full">Lưu feedback</button>
      </form>
    </UiModal>

    <UiModal v-model="showOfferForm" title="Tạo offer">
      <form class="space-y-3" @submit.prevent="saveOffer">
        <input v-model.number="offerForm.salary_base" type="number" class="hcm-input" required placeholder="Lương đề xuất" />
        <input v-model="offerForm.start_date" type="date" class="hcm-input" required />
        <select v-model="offerForm.contract_type" class="hcm-input">
          <option v-for="(lbl, key) in meta.contract_types" :key="key" :value="key">{{ lbl }}</option>
        </select>
        <textarea v-model="offerForm.letter_notes" class="hcm-input" rows="2" placeholder="Ghi chú thư mời nhận việc" />
        <button type="submit" class="hcm-btn-primary w-full">Tạo offer</button>
      </form>
    </UiModal>

    <UiModal v-model="showHireForm" title="Chuyển thành nhân viên">
      <form class="space-y-3" @submit.prevent="hire">
        <select v-model="hireForm.department_id" class="hcm-input">
          <option :value="null">— Phòng ban —</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <select v-model="hireForm.position_id" class="hcm-input">
          <option :value="null">— Chức danh —</option>
          <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <select v-model="hireForm.onboarding_buddy_user_id" class="hcm-input">
          <option :value="null">— Buddy / người hướng dẫn —</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <button type="submit" class="hcm-btn-primary w-full">Xác nhận tuyển</button>
      </form>
    </UiModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiSearchInput from '../../components/ui/UiSearchInput.vue';
import UiModal from '../../components/ui/UiModal.vue';
import { useFormat } from '../../composables/useFormat';
import { extractItems } from '../../composables/usePagination';
import { useToast } from '../../composables/useToast';

const route = useRoute();
const { money, statusLabel } = useFormat();
const toast = useToast();

const tabs = [
  { key: 'requests', label: 'Yêu cầu TD' },
  { key: 'jobs', label: 'Tin tuyển' },
  { key: 'candidates', label: 'Ứng viên' },
  { key: 'talent', label: 'Talent pool' },
];
const tab = ref(route.query.tab || 'candidates');
const listSearch = ref('');
const meta = ref({ candidate_stages: {} });
const companyId = ref(null);
const tenantId = ref(null);
const requests = ref([]);
const jobPosts = ref([]);
const candidates = ref([]);
const departments = ref([]);
const positions = ref([]);
const users = ref([]);
const stageFilter = ref('');
const showReqForm = ref(false);
const showJobForm = ref(false);
const showCandForm = ref(false);
const showDetail = ref(false);
const showInterviewForm = ref(false);
const showFeedbackForm = ref(false);
const showOfferForm = ref(false);
const showHireForm = ref(false);
const detail = ref(null);
const detailStage = ref('applied');
const feedbackInterviewId = ref(null);
const reqForm = ref({ title: '', headcount: 1, description: '', department_id: null, position_id: null });
const jobForm = ref({ recruitment_request_id: null, title: '', job_description: '', channel: '' });
const candForm = ref({ job_post_id: null, full_name: '', email: '', phone: '', source: '', experience_summary: '' });
const interviewForm = ref({ scheduled_at: '', round: 1, location: '' });
const feedbackForm = ref({ score: 75, feedback: '', recommendation: 'maybe', scorecard: [] });
const offerForm = ref({ salary_base: 15000000, start_date: '', contract_type: 'probation', letter_notes: '' });
const hireForm = ref({ department_id: null, position_id: null, onboarding_buddy_user_id: null });

const pipelineStages = [
  { key: 'applied', label: 'Mới' },
  { key: 'screening', label: 'Sàng lọc' },
  { key: 'interview', label: 'PV' },
  { key: 'offer', label: 'Offer' },
  { key: 'hired', label: 'Đã tuyển' },
];

const searchPlaceholder = computed(() => {
  if (tab.value === 'requests') return 'Tìm theo mã hoặc tiêu đề yêu cầu...';
  if (tab.value === 'jobs') return 'Tìm theo tiêu đề tin hoặc kênh đăng...';
  return 'Tìm theo tên, email hoặc SĐT ứng viên...';
});

const filteredRequests = computed(() => requests.value);

const filteredJobPosts = computed(() => jobPosts.value);

const filteredCandidates = computed(() => {
  let list = candidates.value.filter((c) => !['talent_pool', 'rejected'].includes(c.stage));
  if (stageFilter.value) list = list.filter((c) => c.stage === stageFilter.value);
  return list;
});

const talentPool = computed(() => candidates.value.filter((c) => c.stage === 'talent_pool'));

function listSearchParams(extra = {}) {
  const params = { company_id: companyId.value, ...extra };
  if (listSearch.value.trim()) params.search = listSearch.value.trim();
  return params;
}

async function onListSearch(value) {
  listSearch.value = value;
  await loadAll();
}

const publishedJobs = computed(() => jobPosts.value.filter((j) => j.status === 'published'));

const hasAcceptedOffer = computed(() => detail.value?.offers?.some((o) => o.status === 'accepted'));

function initScorecard() {
  const raw = meta.value.scorecard_criteria;
  const list = Array.isArray(raw) ? raw : Object.values(raw || {});
  return list.map((criterion) => ({ criterion, score: 4 }));
}

function copyCareerLink(jobId) {
  const url = `${window.location.origin}/app/careers?job=${jobId}`;
  navigator.clipboard?.writeText(url);
  toast.show('Đã copy link cổng ứng tuyển');
}

function countStage(stage) {
  return candidates.value.filter((c) => c.stage === stage).length;
}

async function loadMeta() {
  const companies = await api.get('/companies');
  if (companies.data.data[0]) {
    companyId.value = companies.data.data[0].id;
    tenantId.value = companies.data.data[0].tenant_id;
  }
  const [metaRes, d, p, u] = await Promise.all([
    api.get('/recruitment-meta'),
    api.get('/departments'),
    api.get('/positions'),
    api.get('/users'),
  ]);
  meta.value = metaRes.data.data;
  departments.value = d.data.data;
  positions.value = p.data.data;
  users.value = u.data.data;
  feedbackForm.value.scorecard = initScorecard();
  if (!offerForm.value.start_date) {
    offerForm.value.start_date = new Date().toISOString().slice(0, 10);
  }
}

async function loadAll() {
  if (!companyId.value) return;
  const search = listSearch.value.trim();
  const [req, jobs, cand] = await Promise.all([
    api.get('/recruitment-requests', { params: listSearchParams() }),
    api.get('/job-posts', { params: search ? { search } : {} }),
    api.get('/candidates', {
      params: listSearchParams(tab.value === 'talent' ? { talent_pool: 1 } : {}),
    }),
  ]);
  requests.value = req.data.data;
  jobPosts.value = jobs.data.data;
  candidates.value = extractItems(cand.data);
}

async function saveRequest() {
  await api.post('/recruitment-requests', { ...reqForm.value, company_id: companyId.value });
  showReqForm.value = false;
  toast.show('Đã tạo yêu cầu');
  await loadAll();
}

async function submitRequest(id) {
  await api.post(`/recruitment-requests/${id}/submit`);
  toast.show('Đã gửi duyệt — xem Hộp thư duyệt');
  await loadAll();
}

function openJobForm(req) {
  jobForm.value = {
    recruitment_request_id: req.id,
    title: req.title,
    job_description: req.description || '',
    channel: '',
  };
  showJobForm.value = true;
}

async function saveJob() {
  await api.post('/job-posts', jobForm.value);
  showJobForm.value = false;
  tab.value = 'jobs';
  toast.show('Đã tạo tin');
  await loadAll();
}

async function publishJob(id) {
  await api.post(`/job-posts/${id}/publish`);
  toast.show('Đã đăng tin');
  await loadAll();
}

async function saveCandidate() {
  await api.post('/candidates', {
    ...candForm.value,
    tenant_id: tenantId.value,
    company_id: companyId.value,
    stage: 'applied',
  });
  showCandForm.value = false;
  await loadAll();
}

async function openDetail(c) {
  const { data } = await api.get(`/candidates/${c.id}`);
  detail.value = data.data;
  detailStage.value = detail.value.stage;
  showDetail.value = true;
}

async function saveStage() {
  await api.patch(`/candidates/${detail.value.id}/stage`, { stage: detailStage.value });
  await openDetail({ id: detail.value.id });
  await loadAll();
}

async function rejectCand() {
  await api.post(`/candidates/${detail.value.id}/reject`);
  toast.show('Đã từ chối');
  showDetail.value = false;
  await loadAll();
}

async function toTalent() {
  await api.post(`/candidates/${detail.value.id}/talent-pool`);
  toast.show('Đã chuyển talent pool');
  showDetail.value = false;
  await loadAll();
}

async function uploadCv(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('type', 'cv');
  fd.append('file', file);
  await api.post(`/candidates/${detail.value.id}/documents`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  toast.show('Đã upload CV');
  await openDetail({ id: detail.value.id });
}

async function saveInterview() {
  await api.post(`/candidates/${detail.value.id}/interviews`, {
    ...interviewForm.value,
    scheduled_at: interviewForm.value.scheduled_at.replace('T', ' '),
  });
  showInterviewForm.value = false;
  await openDetail({ id: detail.value.id });
}

function openFeedbackForm(interviewId) {
  feedbackInterviewId.value = interviewId;
  feedbackForm.value.scorecard = initScorecard();
  showFeedbackForm.value = true;
}

async function saveFeedback() {
  await api.post(`/interviews/${feedbackInterviewId.value}/feedback`, feedbackForm.value);
  showFeedbackForm.value = false;
  await openDetail({ id: detail.value.id });
}

async function saveOffer() {
  await api.post(`/candidates/${detail.value.id}/offers`, offerForm.value);
  showOfferForm.value = false;
  await openDetail({ id: detail.value.id });
}

async function printOfferLetter(offerId) {
  const { data } = await api.get(`/offers/${offerId}/letter`);
  const w = window.open('', '_blank');
  w.document.write(data.data.html);
  w.document.close();
  w.print();
}

async function acceptOffer(offerId) {
  await api.post(`/offers/${offerId}/accept`);
  toast.show('Offer đã accepted');
  await openDetail({ id: detail.value.id });
}

async function hire() {
  await api.post(`/candidates/${detail.value.id}/hire`, hireForm.value);
  toast.show('Đã tuyển — NV + onboarding + ghi danh hội nhập');
  showHireForm.value = false;
  showDetail.value = false;
  await loadAll();
}

watch(tab, () => loadAll());

onMounted(async () => {
  await loadMeta();
  await loadAll();
});
</script>
