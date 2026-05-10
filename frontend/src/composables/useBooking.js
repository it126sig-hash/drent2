import { ref } from 'vue'
import bookingApi from '../api/booking'

export function useBooking() {
  const bookings = ref([])
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    total: 0,
    per_page: 15,
    current_page: 1,
    last_page: 1
  })

  const store = async (data) => {
    loading.value = true
    error.value = null
    try {
      const response = await bookingApi.createBooking(data)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal membuat booking'
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchAll = async (params = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await bookingApi.getBookings(params)
      bookings.value = response.data.data
      if (response.data.meta) {
        pagination.value = {
          total: response.data.meta.total,
          per_page: response.data.meta.per_page,
          current_page: response.data.meta.current_page,
          last_page: response.data.meta.last_page
        }
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil data booking'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    bookings,
    loading,
    error,
    pagination,
    store,
    fetchAll
  }
}
