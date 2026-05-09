import { ref } from 'vue'
import { 
  getUsers, 
  createUser, 
  updateUser, 
  deleteUser,
  resetUserPassword,
  getRoles
} from '../api/user'

export function useUser() {
  const users = ref([])
  const loading = ref(false)
  const error = ref(null)
  const roles = ref([])
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
      const response = await getUsers({
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...params
      })
      users.value = response.data.data
      pagination.value = {
        total: response.data.meta.total,
        per_page: response.data.meta.per_page,
        current_page: response.data.meta.current_page,
        last_page: response.data.meta.last_page
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil data user'
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchRoles = async () => {
    try {
      const response = await getRoles()
      roles.value = response.data.data
    } catch (err) {
      console.error('Gagal mengambil data role:', err)
    }
  }

  const store = async (data) => {
    loading.value = true
    error.value = null
    try {
      await createUser(data)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menyimpan data user'
      throw err
    } finally {
      loading.value = false
    }
  }

  const update = async (id, data) => {
    loading.value = true
    error.value = null
    try {
      await updateUser(id, data)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui data user'
      throw err
    } finally {
      loading.value = false
    }
  }

  const remove = async (id) => {
    loading.value = true
    error.value = null
    try {
      await deleteUser(id)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menghapus data user'
      throw err
    } finally {
      loading.value = false
    }
  }

  const resetPassword = async (id, data) => {
    loading.value = true
    error.value = null
    try {
      await resetUserPassword(id, data)
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mereset password'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    users,
    roles,
    loading,
    error,
    pagination,
    fetchAll,
    fetchRoles,
    store,
    update,
    remove,
    resetPassword
  }
}
