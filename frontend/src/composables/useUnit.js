import { ref } from 'vue'
import { 
  getUnits, 
  createUnit, 
  updateUnit, 
  deleteUnit,
  uploadUnitPhoto,
  deleteUnitPhoto,
  batchUpdateUnitCity
} from '../api/unit'

export function useUnit() {
  const units = ref([])
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
      const response = await getUnits({
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...params
      })
      units.value = response.data.data
      pagination.value = {
        total: response.data.meta.total,
        per_page: response.data.meta.per_page,
        current_page: response.data.meta.current_page,
        last_page: response.data.meta.last_page
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil data unit'
      throw err
    } finally {
      loading.value = false
    }
  }

  const store = async (data) => {
    loading.value = true
    error.value = null
    try {
      await createUnit(data)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menyimpan data unit'
      throw err
    } finally {
      loading.value = false
    }
  }

  const update = async (id, data) => {
    loading.value = true
    error.value = null
    try {
      await updateUnit(id, data)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui data unit'
      throw err
    } finally {
      loading.value = false
    }
  }

  const remove = async (id) => {
    loading.value = true
    error.value = null
    try {
      await deleteUnit(id)
      await fetchAll()
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menghapus data unit'
      throw err
    } finally {
      loading.value = false
    }
  }

  const addPhoto = async (unitId, formData) => {
    loading.value = true
    error.value = null
    try {
      await uploadUnitPhoto(unitId, formData)
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengunggah foto'
      throw err
    } finally {
      loading.value = false
    }
  }

  const removePhoto = async (unitId, photoId) => {
    loading.value = true
    error.value = null
    try {
      await deleteUnitPhoto(unitId, photoId)
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menghapus foto'
      throw err
    } finally {
      loading.value = false
    }
  }

  const batchUpdateCity = async (data) => {
    loading.value = true
    error.value = null
    try {
      await batchUpdateUnitCity(data)
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui kota unit secara batch'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    units,
    loading,
    error,
    pagination,
    fetchAll,
    store,
    update,
    remove,
    addPhoto,
    removePhoto,
    batchUpdateCity
  }
}
