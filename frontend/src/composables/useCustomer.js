import { ref } from 'vue'
import { 
  fetchCustomers, 
  fetchCustomer,
  createCustomer, 
  updateCustomer, 
  deleteCustomer
} from '../api/customer'

export function useCustomer() {
  const customers = ref([])
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
      const response = await fetchCustomers({
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...params
      })
      customers.value = response.data.data
      pagination.value = {
        total: response.data.meta.total,
        per_page: response.data.meta.per_page,
        current_page: response.data.meta.current_page,
        last_page: response.data.meta.last_page
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil data pelanggan'
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchOne = async (id) => {
    loading.value = true
    error.value = null
    try {
      const response = await fetchCustomer(id)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil detail pelanggan'
      throw err
    } finally {
      loading.value = false
    }
  }

  const store = async (data) => {
    loading.value = true
    error.value = null
    try {
      await createCustomer(data)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menyimpan data pelanggan'
      throw err
    } finally {
      loading.value = false
    }
  }

  const update = async (id, data) => {
    loading.value = true
    error.value = null
    try {
      await updateCustomer(id, data)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui data pelanggan'
      throw err
    } finally {
      loading.value = false
    }
  }

  const remove = async (id) => {
    loading.value = true
    error.value = null
    try {
      await deleteCustomer(id)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menghapus data pelanggan'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    customers,
    loading,
    error,
    pagination,
    fetchAll,
    fetchOne,
    store,
    update,
    remove
  }
}
