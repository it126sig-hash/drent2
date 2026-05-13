import { ref } from 'vue'
import { fetchCities, createCity, updateCity, deleteCity } from '../api/city'

export function useCity() {
  const cities = ref([])
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({ current_page: 1, per_page: 15, total: 0, last_page: 1 })

  const fetchAll = async (params = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await fetchCities({
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...params
      })
      cities.value = response.data.data
      if (response.data.meta) {
        pagination.value = {
          current_page: response.data.meta.current_page,
          per_page: response.data.meta.per_page,
          total: response.data.meta.total,
          last_page: response.data.meta.last_page
        }
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil data kota'
      throw err
    } finally {
      loading.value = false
    }
  }

  const store = async (data) => {
    loading.value = true
    error.value = null
    try {
      await createCity(data)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menyimpan kota'
      throw err
    } finally {
      loading.value = false
    }
  }

  const update = async (id, data) => {
    loading.value = true
    error.value = null
    try {
      await updateCity(id, data)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui kota'
      throw err
    } finally {
      loading.value = false
    }
  }

  const remove = async (id) => {
    loading.value = true
    error.value = null
    try {
      await deleteCity(id)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menghapus kota'
      throw err
    } finally {
      loading.value = false
    }
  }

  return { cities, loading, error, pagination, fetchAll, store, update, remove }
}
