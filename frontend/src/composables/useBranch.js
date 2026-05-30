import { ref } from 'vue'
import {
  fetchBranches,
  fetchBranch,
  createBranch,
  updateBranch,
  deleteBranch,
} from '../api/branch'

export function useBranch() {
  const branches = ref([])
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    current_page: 1,
    per_page: 50,
    total: 0,
    last_page: 1,
  })

  const fetchAll = async (params = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await fetchBranches({
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...params,
      })
      branches.value = response.data.data
      if (response.data.meta) {
        pagination.value = {
          current_page: response.data.meta.current_page,
          per_page: response.data.meta.per_page,
          total: response.data.meta.total,
          last_page: response.data.meta.last_page,
        }
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memuat data cabang'
      throw err
    } finally {
      loading.value = false
    }
  }

  const find = async (id) => {
    const { data } = await fetchBranch(id)
    return data.data
  }

  const store = async (formData) => {
    loading.value = true
    error.value = null
    try {
      const { data } = await createBranch(formData)
      await fetchAll()
      return data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menyimpan cabang'
      throw err
    } finally {
      loading.value = false
    }
  }

  const update = async (id, formData) => {
    loading.value = true
    error.value = null
    try {
      const { data } = await updateBranch(id, formData)
      await fetchAll()
      return data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui cabang'
      throw err
    } finally {
      loading.value = false
    }
  }

  const remove = async (id) => {
    loading.value = true
    error.value = null
    try {
      await deleteBranch(id)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menghapus cabang'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    branches,
    loading,
    error,
    pagination,
    fetchAll,
    find,
    store,
    update,
    remove,
  }
}
