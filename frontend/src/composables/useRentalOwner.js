import { ref } from 'vue'
import { 
  getRentalOwners, 
  createRentalOwner, 
  updateRentalOwner, 
  deleteRentalOwner 
} from '../api/rentalOwner'

export function useRentalOwner() {
  const rentalOwners = ref([])
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
      const response = await getRentalOwners({
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...params
      })
      rentalOwners.value = response.data.data
      pagination.value = {
        total: response.data.meta.total,
        per_page: response.data.meta.per_page,
        current_page: response.data.meta.current_page,
        last_page: response.data.meta.last_page
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil data pemilik rental'
      throw err
    } finally {
      loading.value = false
    }
  }

  const store = async (data) => {
    loading.value = true
    error.value = null
    try {
      await createRentalOwner(data)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menyimpan data'
      throw err
    } finally {
      loading.value = false
    }
  }

  const update = async (id, data) => {
    loading.value = true
    error.value = null
    try {
      await updateRentalOwner(id, data)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui data'
      throw err
    } finally {
      loading.value = false
    }
  }

  const remove = async (id) => {
    loading.value = true
    error.value = null
    try {
      await deleteRentalOwner(id)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menghapus data'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    rentalOwners,
    loading,
    error,
    pagination,
    fetchAll,
    store,
    update,
    remove
  }
}
