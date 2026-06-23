import { ref } from 'vue';

/**
 * Debounce tìm kiếm — dùng với usePagination.setFilter hoặc callback tùy chỉnh.
 */
export function useDebouncedSearch(onSearch, debounceMs = 350) {
  const search = ref('');
  let timer = null;

  function onSearchInput(value) {
    search.value = value;
    clearTimeout(timer);
    timer = setTimeout(() => onSearch(value), debounceMs);
  }

  function clearSearch() {
    search.value = '';
    onSearch('');
  }

  return { search, onSearchInput, clearSearch };
}

/**
 * Lọc client-side theo tên/mã NV (và email nếu có).
 */
export function matchesEmployeeSearch(row, query, fields = {}) {
  const q = (query || '').trim().toLowerCase();
  if (!q) return true;

  const name = String(row[fields.name || 'full_name'] || row.employee?.full_name || '').toLowerCase();
  const code = String(row[fields.code || 'employee_code'] || row.employee?.employee_code || '').toLowerCase();
  const email = String(row[fields.email || 'email'] || row.employee?.email || '').toLowerCase();

  return name.includes(q) || code.includes(q) || email.includes(q);
}
