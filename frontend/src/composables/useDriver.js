import { ref } from 'vue'
import { 
  fetchDrivers, 
  createDriver, 
  updateDriver, 
  deleteDriver,
  updateDriverBalance
} from '../api/driver'

export function useDriver() {
  const drivers = ref([])
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    total: 0,
    per_page: 15,
    current_page: 1,
    last_page: 1
  })

  const fetchAll = async (params = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await fetchDrivers({
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...params
      })
      drivers.value = response.data.data
      pagination.value = {
        total: response.data.meta.total,
        per_page: response.data.meta.per_page,
        current_page: response.data.meta.current_page,
        last_page: response.data.meta.last_page
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil data driver'
      throw err
    } finally {
      loading.value = false
    }
  }

  const store = async (data) => {
    loading.value = true
    error.value = null
    try {
      await createDriver(data)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menyimpan data driver'
      throw err
    } finally {
      loading.value = false
    }
  }

  const update = async (id, data) => {
    loading.value = true
    error.value = null
    try {
      await updateDriver(id, data)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui data driver'
      throw err
    } finally {
      loading.value = false
    }
  }

  const remove = async (id) => {
    loading.value = true
    error.value = null
    try {
      await deleteDriver(id)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menghapus data driver'
      throw err
    } finally {
      loading.value = false
    }
  }

  const changeBalance = async (id, saldo) => {
    loading.value = true
    error.value = null
    try {
      await updateDriverBalance(id, saldo)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui saldo driver'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    drivers,
    loading,
    error,
    pagination,
    fetchAll,
    store,
    update,
    remove,
    changeBalance
  }
}
