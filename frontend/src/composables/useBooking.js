import { ref } from 'vue'
import bookingApi from '../api/booking'
import { useToast } from 'primevue/usetoast'

export function useBooking() {
  const toast = useToast()
  const bookings = ref([])
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    total: 0,
    per_page: 15,
    current_page: 1,
    last_page: 1
  })
  const filters = ref({
    status: [],
    date_from: null,
    date_to: null,
    customer_id: null,
    search: '',
    sort_by: 'created_at',
    sort_direction: 'desc',
    rental_owner_id: null,
    kota: null
  })

  const toApiDate = (value) => {
    if (!value) return null
    if (value instanceof Date) {
      const year = value.getFullYear()
      const month = String(value.getMonth() + 1).padStart(2, '0')
      const day = String(value.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }
    return value
  }

  const buildFilterParams = () => {
    const params = {
      ...filters.value,
      date_from: toApiDate(filters.value.date_from),
      date_to: toApiDate(filters.value.date_to)
    }

    Object.keys(params).forEach((key) => {
      const value = params[key]
      if (value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
        delete params[key]
      }
    })

    return params
  }

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

  const fetchAll = async (page = 1) => {
    loading.value = true
    error.value = null
    try {
      const params = {
        ...buildFilterParams(),
        page,
        per_page: pagination.value.per_page
      }
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
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchForCalendar = async (dateFrom, dateTo) => {
    try {
      const perPage = 200
      const rows = []
      let page = 1
      let lastPage = 1

      do {
        const response = await bookingApi.getBookings({
          date_from: dateFrom,
          date_to: dateTo,
          period_overlap: 1,
          page,
          per_page: perPage
        })

        rows.push(...(response.data.data || []))
        lastPage = response.data.meta?.last_page || 1
        page += 1
      } while (page <= lastPage)

      return rows
    } catch (err) {
      console.error('Failed to fetch bookings for calendar', err)
      return []
    }
  }

  const changeStatus = async (bookingId, status, catatan = '') => {
    loading.value = true
    try {
      await bookingApi.updateBookingStatus(bookingId, { status, catatan_status: catatan })
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Status booking diperbarui', life: 3000 })
      await fetchAll(pagination.value.current_page)
    } catch (err) {
      const msg = err.response?.data?.message || 'Gagal memperbarui status'
      toast?.add({ severity: 'error', summary: 'Error', detail: msg, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchOne = async (id) => {
    loading.value = true
    try {
      const response = await bookingApi.getBooking(id)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil detail booking'
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateBooking = async (id, data) => {
    loading.value = true
    error.value = null
    try {
      const response = await bookingApi.updateBooking(id, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Data booking diperbarui', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui booking'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const handle = async (id, data) => {
    loading.value = true
    try {
      const response = await bookingApi.handleBooking(id, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Booking dipindah ke Waiting List', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memproses booking'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const checkout = async (id, data = {}) => {
    loading.value = true
    try {
      const response = await bookingApi.checkoutBooking(id, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Unit berhasil di-checkout', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal checkout'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const complete = async (id, data = {}) => {
    loading.value = true
    try {
      const response = await bookingApi.completeBooking(id, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Booking selesai', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menyelesaikan booking'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const cancel = async (id, data = {}) => {
    loading.value = true
    try {
      const response = await bookingApi.cancelBooking(id, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Booking berhasil dibatalkan', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal membatalkan booking'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const addPayment = async (bookingId, data) => {
    loading.value = true
    try {
      const response = await bookingApi.addBookingPayment(bookingId, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Pembayaran berhasil dicatat', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mencatat pembayaran'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const requestReturnToRentalUnit = async (id, reason) => {
    loading.value = true
    try {
      const response = await bookingApi.requestRentalUnitReturn(id, { reason })
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Request kembali ke rental unit dikirim ke supervisor', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengirim request kembali ke rental unit'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const approveReturnToRentalUnit = async (id) => {
    loading.value = true
    try {
      const response = await bookingApi.approveRentalUnitReturn(id)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Booking dikembalikan ke status rental unit', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menyetujui request'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const rejectReturnToRentalUnit = async (id, data = {}) => {
    loading.value = true
    try {
      const response = await bookingApi.rejectRentalUnitReturn(id, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Request kembali ke rental unit ditolak', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menolak request'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const requestVoidPayment = async (paymentId, data) => {
    loading.value = true
    try {
      const response = await bookingApi.requestVoidBookingPayment(paymentId, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Request void pembayaran dikirim ke supervisor', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengajukan void pembayaran'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const approveVoidPayment = async (paymentId) => {
    loading.value = true
    try {
      const response = await bookingApi.approveVoidBookingPayment(paymentId)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Void pembayaran disetujui', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menyetujui void pembayaran'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const rejectVoidPayment = async (paymentId, data = {}) => {
    loading.value = true
    try {
      const response = await bookingApi.rejectVoidBookingPayment(paymentId, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Request void pembayaran ditolak', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menolak void pembayaran'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const addDetail = async (bookingId, data) => {
    loading.value = true
    try {
      const response = await bookingApi.addBookingDetail(bookingId, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Kendaraan berhasil ditambahkan', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menambahkan kendaraan'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const addCost = async (detailId, data) => {
    loading.value = true
    try {
      const response = await bookingApi.addBookingCost(detailId, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Biaya berhasil ditambahkan', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menambahkan biaya'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const extend = async (bookingId, data) => {
    loading.value = true
    try {
      const response = await bookingApi.extendBooking(bookingId, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Sewa berhasil diperpanjang', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperpanjang sewa'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const rolling = async (bookingId, data) => {
    loading.value = true
    try {
      const response = await bookingApi.rollingBooking(bookingId, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Unit berhasil diganti (Rolling)', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengganti unit'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const stopEarly = async (bookingId, data) => {
    loading.value = true
    try {
      const response = await bookingApi.stopEarlyBooking(bookingId, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Sewa berhasil dihentikan awal', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menghentikan sewa'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const addAdditionalCost = async (bookingId, data) => {
    loading.value = true
    try {
      const response = await bookingApi.addAdditionalCost(bookingId, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Biaya tambahan berhasil dicatat', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menambah biaya tambahan'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateDetail = async (detailId, data) => {
    loading.value = true
    try {
      const response = await bookingApi.updateBookingDetail(detailId, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Kendaraan berhasil diupdate', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengupdate kendaraan'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateCost = async (costId, data) => {
    loading.value = true
    try {
      const response = await bookingApi.updateBookingCost(costId, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Biaya berhasil diupdate', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengupdate biaya'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
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
    filters,
    store,
    fetchAll,
    fetchForCalendar,
    changeStatus,
    fetchOne,
    updateBooking,
    handle,
    checkout,
    complete,
    cancel,
    requestReturnToRentalUnit,
    approveReturnToRentalUnit,
    rejectReturnToRentalUnit,
    addPayment,
    requestVoidPayment,
    approveVoidPayment,
    rejectVoidPayment,
    addDetail,
    addCost,
    extend,
    rolling,
    stopEarly,
    addAdditionalCost,
    updateDetail,
    updateCost
  }
}
