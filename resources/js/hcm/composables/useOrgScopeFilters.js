import { computed, ref, watch } from 'vue';
import api from '../api/client';
import { useAppStore } from '../stores/app';
import { useAuthStore } from '../stores/auth';

/**
 * Bộ lọc công ty / chi nhánh / phòng ban — dùng chung cho danh sách NV và module liên quan.
 */
export function useOrgScopeFilters(options = {}) {
  const {
    includeDepartment = true,
    includeStatus = false,
    autoLoad = true,
  } = options;

  const appStore = useAppStore();
  const auth = useAuthStore();

  const filterCompanyId = ref(auth.companyId ? String(auth.companyId) : '');
  const filterBranchId = ref('');
  const filterDepartmentId = ref('');
  const filterStatus = ref('');

  const branches = ref([]);
  const departments = ref([]);
  const loadingMeta = ref(false);

  const showCompanyPicker = computed(() => appStore.isMultiCompany);

  const filteredDepartments = computed(() => {
    if (!filterBranchId.value) {
      return departments.value;
    }
    const branchId = Number(filterBranchId.value);
    return departments.value.filter((d) => d.branch_id === branchId);
  });

  const singleBranchMode = computed(() => branches.value.length <= 1);

  function toQueryParams() {
    const params = {};
    if (filterBranchId.value) params.branch_id = filterBranchId.value;
    if (filterDepartmentId.value) params.department_id = filterDepartmentId.value;
    if (includeStatus && filterStatus.value) params.employment_status = filterStatus.value;
    return params;
  }

  async function loadBranches() {
    const { data } = await api.get('/branches');
    branches.value = data.data || [];
    if (branches.value.length === 1 && !filterBranchId.value) {
      filterBranchId.value = String(branches.value[0].id);
    }
  }

  async function loadDepartments() {
    if (!includeDepartment) return;
    const params = {};
    if (filterBranchId.value) params.branch_id = filterBranchId.value;
    const { data } = await api.get('/departments', { params });
    departments.value = data.data || [];
  }

  async function loadMeta() {
    loadingMeta.value = true;
    try {
      if (showCompanyPicker.value && !appStore.companies.length) {
        await appStore.loadCompanies();
      }
      await Promise.all([loadBranches(), loadDepartments()]);
    } finally {
      loadingMeta.value = false;
    }
  }

  async function onCompanyChange(companyId) {
    filterCompanyId.value = companyId;
    filterBranchId.value = '';
    filterDepartmentId.value = '';
    await auth.setCompanyAndRefresh(companyId);
    await loadMeta();
  }

  function resetScope() {
    filterBranchId.value = '';
    filterDepartmentId.value = '';
    filterStatus.value = '';
  }

  function applyToPagination(setFilters, extra = {}) {
    const batch = {
      branch_id: '',
      department_id: '',
      employment_status: '',
      search: '',
      ...toQueryParams(),
      ...extra,
    };
    setFilters(batch);
  }

  if (autoLoad) {
    watch(filterBranchId, async () => {
      filterDepartmentId.value = '';
      await loadDepartments();
    });
  }

  return {
    filterCompanyId,
    filterBranchId,
    filterDepartmentId,
    filterStatus,
    branches,
    departments,
    filteredDepartments,
    showCompanyPicker,
    singleBranchMode,
    loadingMeta,
    toQueryParams,
    loadMeta,
    loadBranches,
    loadDepartments,
    onCompanyChange,
    resetScope,
    applyToPagination,
  };
}
