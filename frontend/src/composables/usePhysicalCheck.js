import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import physicalCheckApi from '../api/physicalCheck'

export function usePhysicalCheck() {
  const toast = useToast()
  const loading = ref(false)
  const rows = ref([])
  const items = ref([])
  const error = ref(null)
  const pagination = ref({
    total: 0,
    per_page: 15,
    current_page: 1,
    last_page: 1
  })
  const filters = ref({
    search: ''
  })

  const fetchBookings = async (page = 1) => {
    loading.value = true
    error.value = null
    try {
      const response = await physicalCheckApi.getPhysicalCheckBookings({
        ...filters.value,
        page,
        per_page: pagination.value.per_page
      })
      rows.value = response.data.data
      if (response.data.meta) {
        pagination.value = {
          total: response.data.meta.total,
          per_page: response.data.meta.per_page,
          current_page: response.data.meta.current_page,
          last_page: response.data.meta.last_page
        }
      }
      return rows.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil daftar cek fisik'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchItems = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await physicalCheckApi.getPhysicalCheckItems()
      items.value = response.data.data
      return items.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil checklist perlengkapan'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const requestCheck = async (bookingId, type) => {
    loading.value = true
    error.value = null
    try {
      const response = await physicalCheckApi.requestPhysicalCheck(bookingId, type)
      toast?.add({
        severity: 'success',
        summary: 'Request dibuat',
        detail: type === 'departure' ? 'Cek fisik keberangkatan sudah masuk daftar.' : 'Cek fisik pengembalian sudah masuk daftar.',
        life: 4000
      })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal membuat request cek fisik'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchByBooking = async (bookingId, type) => {
    loading.value = true
    error.value = null
    try {
      const response = await physicalCheckApi.getPhysicalCheckByBooking(bookingId, type)
      return response.data.data
    } catch (err) {
      if (err.response?.status === 404) return null
      error.value = err.response?.data?.message || 'Gagal mengambil detail cek fisik'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const store = async (data) => {
    loading.value = true
    error.value = null
    try {
      const response = await physicalCheckApi.storePhysicalCheck(data)
      toast?.add({ severity: 'success', summary: 'Tersimpan', detail: 'Cek fisik berhasil disimpan', life: 4000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menyimpan cek fisik'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 6000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    rows,
    items,
    error,
    pagination,
    filters,
    fetchBookings,
    fetchItems,
    requestCheck,
    fetchByBooking,
    store
  }
}
