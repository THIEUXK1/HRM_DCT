<template>
  <div>
    <UiPageHeader title="Đào tạo (LMS)" subtitle="Khóa học · Lớp học · Ghi danh · Đồng bộ năng lực" breadcrumb="Training">
      <template #actions>
        <button type="button" class="hcm-btn-primary" @click="showCourse = true">+ Khóa học</button>
      </template>
    </UiPageHeader>

    <div class="grid gap-4 lg:grid-cols-2">
      <div v-for="course in courses" :key="course.id" class="hcm-card p-5">
        <div class="flex justify-between gap-2">
          <div>
            <UiBadge :variant="course.type === 'mandatory' ? 'warning' : 'default'">{{ course.type }}</UiBadge>
            <h3 class="mt-2 font-semibold">{{ course.name }}</h3>
            <p class="text-xs text-slate-500">{{ course.code }} · {{ course.duration_hours }}h</p>
          </div>
          <button type="button" class="text-xs text-primary-600 shrink-0" @click="openCompMap(course)">Liên kết năng lực</button>
        </div>
        <div class="mt-4 space-y-2">
          <div v-for="cls in course.classes" :key="cls.id" class="rounded-lg border border-slate-100 p-3 text-sm">
            <p class="font-medium">{{ cls.name }}</p>
            <ul v-if="cls.enrollments?.length" class="mt-2 space-y-1">
              <li v-for="en in cls.enrollments" :key="en.id" class="flex flex-wrap justify-between gap-2 text-xs">
                <span>{{ en.employee?.full_name }} · {{ en.status }}</span>
                <button
                  v-if="en.status !== 'completed'"
                  type="button"
                  class="text-emerald-600"
                  @click="completeEnrollment(en)"
                >
                  Hoàn thành → NL
                </button>
              </li>
            </ul>
            <p v-else class="text-xs text-slate-400 mt-1">Chưa có học viên</p>
            <button type="button" class="mt-2 text-primary-600 text-xs" @click="openEnroll(cls)">+ Ghi danh NV</button>
          </div>
          <button type="button" class="text-sm text-primary-600" @click="openClass(course)">+ Tạo lớp</button>
        </div>
      </div>
    </div>
    <UiEmpty v-if="!courses.length" title="Chưa có khóa học" />

    <UiModal v-model="showCourse" title="Thêm khóa học">
      <form class="space-y-3" @submit.prevent="saveCourse">
        <input v-model="courseForm.name" class="hcm-input" placeholder="Tên khóa học" required />
        <input v-model="courseForm.code" class="hcm-input" placeholder="Mã khóa" required />
        <select v-model="courseForm.type" class="hcm-input">
          <option value="mandatory">Bắt buộc</option>
          <option value="optional">Tự chọn</option>
        </select>
        <button type="submit" class="hcm-btn-primary w-full">Lưu</button>
      </form>
    </UiModal>

    <UiModal v-model="showClassModal" title="Tạo lớp học">
      <form class="space-y-3" @submit.prevent="saveClass">
        <input v-model="classForm.name" class="hcm-input" placeholder="Tên lớp" required />
        <input v-model="classForm.start_date" type="date" class="hcm-input" />
        <button type="submit" class="hcm-btn-primary w-full">Tạo lớp</button>
      </form>
    </UiModal>

    <UiModal v-model="showEnrollModal" title="Ghi danh nhân viên">
      <form @submit.prevent="saveEnroll">
        <select v-model="enrollEmployeeId" class="hcm-input" required>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
        </select>
        <button type="submit" class="hcm-btn-primary mt-3 w-full">Ghi danh</button>
      </form>
    </UiModal>

    <UiModal v-model="showCompMap" :title="`Năng lực sau khóa: ${selectedCourse?.name || ''}`">
      <form class="space-y-3" @submit.prevent="saveCompMap">
        <p class="text-xs text-slate-500">Khi hoàn thành khóa, hệ thống cập nhật level năng lực (LMS).</p>
        <div v-for="row in compMapForm" :key="row.competency_id" class="grid grid-cols-3 gap-2 items-center">
          <span class="text-sm col-span-1">{{ competencyName(row.competency_id) }}</span>
          <select v-model.number="row.granted_level" class="hcm-input text-xs">
            <option v-for="n in 5" :key="n" :value="n">Cấp L{{ n }}</option>
          </select>
          <input v-model.number="row.min_score" type="number" min="0" max="100" class="hcm-input text-xs" placeholder="Điểm tối thiểu" />
        </div>
        <button type="submit" class="hcm-btn-primary w-full">Lưu liên kết</button>
      </form>
    </UiModal>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../api/client';
import UiPageHeader from '../../components/ui/UiPageHeader.vue';
import UiBadge from '../../components/ui/UiBadge.vue';
import UiEmpty from '../../components/ui/UiEmpty.vue';
import UiModal from '../../components/ui/UiModal.vue';
import { extractItems } from '../../composables/usePagination';
import { useToast } from '../../composables/useToast';

const toast = useToast();
const courses = ref([]);
const employees = ref([]);
const competencies = ref([]);
const showCourse = ref(false);
const showClassModal = ref(false);
const showEnrollModal = ref(false);
const showCompMap = ref(false);
const selectedCourse = ref(null);
const selectedCourseId = ref(null);
const selectedClassId = ref(null);
const enrollEmployeeId = ref(null);
const courseForm = ref({ name: '', code: '', type: 'optional' });
const classForm = ref({ name: '', start_date: '' });
const compMapForm = ref([]);

function competencyName(id) {
  return competencies.value.find((c) => c.id === id)?.name || id;
}

async function load() {
  const [c, e, comp] = await Promise.all([
    api.get('/courses'),
    api.get('/employees'),
    api.get('/competencies'),
  ]);
  const cp = c.data?.data;
  courses.value = (cp?.data && Array.isArray(cp.data)) ? cp.data : (Array.isArray(cp) ? cp : []);
  employees.value = extractItems(e.data);
  competencies.value = comp.data.data.flatMap((g) => g.competencies || []);
}

async function saveCourse() {
  await api.post('/courses', courseForm.value);
  showCourse.value = false;
  toast.show('Đã tạo khóa học');
  await load();
}

function openClass(course) {
  selectedCourseId.value = course.id;
  showClassModal.value = true;
}

async function saveClass() {
  await api.post('/training-classes', { ...classForm.value, course_id: selectedCourseId.value });
  showClassModal.value = false;
  toast.show('Đã tạo lớp');
  await load();
}

function openEnroll(cls) {
  selectedClassId.value = cls.id;
  enrollEmployeeId.value = employees.value[0]?.id;
  showEnrollModal.value = true;
}

async function saveEnroll() {
  await api.post(`/training-classes/${selectedClassId.value}/enroll`, { employee_id: enrollEmployeeId.value });
  showEnrollModal.value = false;
  toast.show('Đã ghi danh');
  await load();
}

async function completeEnrollment(enrollment) {
  const { data } = await api.post(`/training-enrollments/${enrollment.id}/complete`, { score: 100 });
  const updates = data.data.competency_updates || [];
  if (updates.length) {
    toast.show(`Hoàn thành — cập nhật ${updates.length} năng lực từ LMS`);
  } else {
    toast.show('Đã hoàn thành (chưa cấu hình liên kết năng lực cho khóa)');
  }
  await load();
}

async function openCompMap(course) {
  selectedCourse.value = course;
  const { data } = await api.get(`/courses/${course.id}/competencies`);
  const existing = data.data || [];
  const all = competencies.value;
  compMapForm.value = all.map((c) => {
    const row = existing.find((r) => r.competency_id === c.id || r.competency?.id === c.id);
    return {
      competency_id: c.id,
      granted_level: row?.granted_level ?? 3,
      min_score: row?.min_score ?? 70,
    };
  });
  showCompMap.value = true;
}

async function saveCompMap() {
  await api.put(`/courses/${selectedCourse.value.id}/competencies`, { mappings: compMapForm.value });
  showCompMap.value = false;
  toast.show('Đã lưu liên kết khóa học → năng lực');
}

onMounted(load);
</script>
