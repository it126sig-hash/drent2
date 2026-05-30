import { ref } from 'vue'
import { fetchTenant, updateTenant } from '../api/tenant'

export function useTenant() {
  const tenant = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const fetch = async () => {
    loading.value = true
    error.value = null
    try {
      const { data } = await fetchTenant()
      tenant.value = data.data
      return tenant.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memuat data tenant'
      throw err
    } finally {
      loading.value = false
    }
  }

  const update = async (formData) => {
    loading.value = true
    error.value = null
    try {
      const { data } = await updateTenant(formData)
      tenant.value = data.data
      return tenant.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui tenant'
      throw err
    } finally {
      loading.value = false
    }
  }

  return { tenant, loading, error, fetch, update }
}
