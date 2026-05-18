import { ref } from 'vue'
import dashboardApi from '../api/dashboard'
import { useToast } from 'primevue/usetoast'

export function useDashboard() {
  const toast = useToast()
  const dashboard = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const toApiDate = (value) => {
    if (!value) return null
    if (value instanceof Date) {
      const year = value.getFullYear()
      const month = String(value.getMonth() + 1).padStart(2, '0')
      const day = String(value.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }
    return value
  }

  const fetchDashboard = async (filters = {}) => {
    loading.value = true
    error.value = null

    try {
      const params = {
        ...filters,
        date_from: toApiDate(filters.date_from),
        date_to: toApiDate(filters.date_to),
      }

      Object.keys(params).forEach((key) => {
        if (params[key] === null || params[key] === '') {
          delete params[key]
        }
      })

      const response = await dashboardApi.getDashboard(params)
      dashboard.value = response.data.data
      return dashboard.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memuat dashboard'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    dashboard,
    loading,
    error,
    fetchDashboard,
  }
}
