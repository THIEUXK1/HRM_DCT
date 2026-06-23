import { ref, reactive } from 'vue'

/**
 * Composable for handling Laravel paginated API responses.
 *
 * Laravel paginate() returns:
 *   { current_page, data: [...], total, per_page, last_page, ... }
 *
 * Wrapped by ApiController::success():
 *   { data: { current_page, data: [...], total, ... } }
 *
 * Usage:
 *   const { items, meta, loading, fetch, changePage } = usePagination(api, '/employees')
 */
export function usePagination(apiClient, endpoint, defaultParams = {}) {
  const items = ref([])
  const loading = ref(false)
  const error = ref(null)

  const meta = reactive({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 50,
    from: 0,
    to: 0,
  })

  const params = reactive({ ...defaultParams, page: 1 })

  async function fetch(extraParams = {}) {
    loading.value = true
    error.value = null
    try {
      const query = { ...params, ...extraParams }
      const res = await apiClient.get(endpoint, { params: query })
      const payload = res.data?.data

      if (payload && typeof payload === 'object' && 'data' in payload && 'total' in payload) {
        // Paginated response
        items.value = payload.data
        meta.currentPage = payload.current_page
        meta.lastPage = payload.last_page
        meta.total = payload.total
        meta.perPage = payload.per_page
        meta.from = payload.from ?? 0
        meta.to = payload.to ?? 0
      } else {
        // Non-paginated (plain array) fallback
        items.value = Array.isArray(payload) ? payload : []
        meta.total = items.value.length
        meta.currentPage = 1
        meta.lastPage = 1
      }
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Lỗi tải dữ liệu'
    } finally {
      loading.value = false
    }
  }

  function changePage(page) {
    params.page = page
    fetch()
  }

  function setFilter(key, value) {
    params[key] = value
    params.page = 1
    fetch()
  }

  function setFilters(updates) {
    Object.assign(params, updates)
    params.page = 1
    fetch()
  }

  function reset() {
    Object.assign(params, { ...defaultParams, page: 1 })
    fetch()
  }

  return { items, meta, loading, error, params, fetch, changePage, setFilter, setFilters, reset }
}

/**
 * Normalize any API/list value to a plain array (handles paginator objects).
 */
export function ensureArray(value) {
  if (Array.isArray(value)) return value
  if (value != null && typeof value === 'object' && Array.isArray(value.data)) {
    return value.data
  }
  return []
}

/**
 * Extract items array from either a paginated or plain API response.
 * Use for one-shot fetches where you don't need pagination controls.
 */
export function extractItems(responseData) {
  if (!responseData) return []
  const payload = responseData.data !== undefined ? responseData.data : responseData
  return ensureArray(payload)
}
