import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import operationalFundApi from '../api/operationalFund'

export function useOperationalFund() {
  const toast = useToast()
  const bookings = ref([])
  const funds = ref([])
  const history = ref([])
  const schedules = ref([])
  const selectedFund = ref(null)
  const loading = ref(false)
  const actionLoading = ref(false)
  const error = ref(null)
  const pagination = ref({
    total: 0,
    per_page: 15,
    current_page: 1,
    last_page: 1,
  })
  const filters = ref({
    search: '',
    status: null,
    driver_id: null,
    date_from: null,
    date_to: null,
    operational_state: 'active',
  })
  const historyFilters = ref({
    search: '',
    date_from: null,
    date_to: null,
  })

  const syncPagination = (meta) => {
    if (!meta) return
    pagination.value = {
      total: meta.total,
      per_page: meta.per_page,
      current_page: meta.current_page,
      last_page: meta.last_page,
    }
  }

  const showError = (err, fallback) => {
    error.value = err.response?.data?.message || fallback
    toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
  }

  const toApiDate = (value) => {
    if (!value) return null
    if (typeof value === 'string') return value
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return null
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${date.getFullYear()}-${month}-${day}`
  }

  const normalizedFilters = () => ({
    ...filters.value,
    date_from: toApiDate(filters.value.date_from),
    date_to: toApiDate(filters.value.date_to),
  })

  const normalizedHistoryFilters = () => ({
    ...historyFilters.value,
    date_from: toApiDate(historyFilters.value.date_from),
    date_to: toApiDate(historyFilters.value.date_to),
  })

  const fetchBookings = async (page = 1) => {
    loading.value = true
    error.value = null
    try {
      const response = await operationalFundApi.getOperationalBookings({
        ...normalizedFilters(),
        page,
        per_page: pagination.value.per_page,
      })
      bookings.value = response.data.data
      syncPagination(response.data.meta)
    } catch (err) {
      showError(err, 'Gagal mengambil transaksi operasional')
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchFund = async (id) => {
    actionLoading.value = true
    try {
      const response = await operationalFundApi.getOperationalFund(id)
      selectedFund.value = response.data.data
      return selectedFund.value
    } catch (err) {
      showError(err, 'Gagal mengambil detail dana operasional')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const storeFund = async (bookingId, payload) => {
    actionLoading.value = true
    try {
      const response = await operationalFundApi.createOperationalFund(bookingId, payload)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Dana operasional berhasil dibuat', life: 3000 })
      await fetchBookings(pagination.value.current_page)
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal membuat dana operasional')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const closeFund = async (fundId, closeNote = '') => {
    actionLoading.value = true
    try {
      const response = await operationalFundApi.closeOperationalFund(fundId, {
        close_note: closeNote,
      })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Transaksi operasional ditutup', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal menutup transaksi operasional')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const fetchHistory = async (page = 1) => {
    loading.value = true
    try {
      const response = await operationalFundApi.getOperationalHistory({
        ...normalizedHistoryFilters(),
        page,
        per_page: pagination.value.per_page,
      })
      history.value = response.data.data
      syncPagination(response.data)
    } catch (err) {
      showError(err, 'Gagal mengambil histori operasional')
      throw err
    } finally {
      loading.value = false
    }
  }

  const acceptFund = async (fundId) => {
    actionLoading.value = true
    try {
      const response = await operationalFundApi.acceptOperationalFund(fundId)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Dana operasional sudah diterima', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal menerima dana operasional')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const submitExpense = async (fundId, payload) => {
    actionLoading.value = true
    try {
      const form = new FormData()
      Object.entries(payload).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
          form.append(key, value)
        }
      })
      const response = await operationalFundApi.createOperationalExpense(fundId, form)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Bon operasional berhasil dikirim', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal mengirim bon operasional')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const submitBookingExpense = async (bookingId, payload) => {
    actionLoading.value = true
    try {
      const form = new FormData()
      Object.entries(payload).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
          form.append(key, value)
        }
      })
      const response = await operationalFundApi.createBookingExpense(bookingId, form)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Realisasi operasional berhasil disimpan', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal menyimpan realisasi operasional')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const approveExpense = async (expenseId) => {
    actionLoading.value = true
    try {
      const response = await operationalFundApi.approveOperationalExpense(expenseId)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Bon disetujui dan saldo driver dipotong', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal menyetujui bon')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const rejectExpense = async (expenseId, rejectionReason) => {
    actionLoading.value = true
    try {
      const response = await operationalFundApi.rejectOperationalExpense(expenseId, {
        rejection_reason: rejectionReason,
      })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Bon ditolak dengan alasan perbaikan', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal menolak bon')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const openExpensePhoto = async (expense) => {
    if (!expense?.id) return

    actionLoading.value = true
    const previewWindow = window.open('', '_blank', 'noopener,noreferrer')
    try {
      const response = await operationalFundApi.getOperationalExpensePhoto(expense.id)
      const blobUrl = URL.createObjectURL(response.data)
      if (previewWindow) {
        previewWindow.location.href = blobUrl
      } else {
        window.open(blobUrl, '_blank', 'noopener,noreferrer')
      }
      window.setTimeout(() => URL.revokeObjectURL(blobUrl), 60000)
    } catch (err) {
      previewWindow?.close()
      showError(err, 'Gagal membuka foto bon')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const fetchDriverFunds = async (page = 1) => {
    loading.value = true
    try {
      const response = await operationalFundApi.getDriverOperationalFunds({
        page,
        per_page: pagination.value.per_page,
      })
      funds.value = response.data.data
      syncPagination(response.data.meta)
    } catch (err) {
      showError(err, 'Gagal mengambil dana operasional driver')
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchDriverSchedules = async (params = {}) => {
    loading.value = true
    try {
      const response = await operationalFundApi.getDriverSchedules({ per_page: 50, ...params })
      schedules.value = response.data.data
      return schedules.value
    } catch (err) {
      showError(err, 'Gagal mengambil jadwal driver')
      throw err
    } finally {
      loading.value = false
    }
  }

  const markOperationalComplete = async (bookingId) => {
    actionLoading.value = true
    try {
      const response = await operationalFundApi.markOperationalComplete(bookingId)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Operasional ditandai selesai', life: 3000 })
      return response.data
    } catch (err) {
      showError(err, 'Gagal menandai operasional selesai')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const revertOperational = async (bookingId, reason) => {
    actionLoading.value = true
    try {
      const response = await operationalFundApi.revertOperationalActive(bookingId, { reason })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Request aktifkan kembali operasional berhasil dikirim', life: 3000 })
      return response.data
    } catch (err) {
      showError(err, 'Gagal mengajukan aktifkan kembali operasional')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const voidFund = async (fundId, voidReason) => {
    actionLoading.value = true
    try {
      const response = await operationalFundApi.voidOperationalFund(fundId, { void_reason: voidReason })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Dana operasional berhasil di-void', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal mem-void dana operasional')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const voidExpense = async (expenseId, voidReason) => {
    actionLoading.value = true
    try {
      const response = await operationalFundApi.voidOperationalExpense(expenseId, { void_reason: voidReason })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Pengajuan void realisasi/bon berhasil dikirim', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal mengajukan void realisasi/bon')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const approveVoidExpense = async (expenseId) => {
    actionLoading.value = true
    try {
      const response = await operationalFundApi.approveVoidOperationalExpense(expenseId)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Request void realisasi/bon disetujui', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal menyetujui void realisasi/bon')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const rejectVoidExpense = async (expenseId, rejectionNote) => {
    actionLoading.value = true
    try {
      const response = await operationalFundApi.rejectVoidOperationalExpense(expenseId, { rejection_note: rejectionNote })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Request void realisasi/bon ditolak', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal menolak void realisasi/bon')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  return {
    bookings,
    funds,
    history,
    schedules,
    selectedFund,
    loading,
    actionLoading,
    error,
    pagination,
    filters,
    historyFilters,
    fetchBookings,
    fetchFund,
    storeFund,
    closeFund,
    fetchHistory,
    acceptFund,
    submitExpense,
    submitBookingExpense,
    approveExpense,
    rejectExpense,
    openExpensePhoto,
    fetchDriverFunds,
    fetchDriverSchedules,
    markOperationalComplete,
    revertOperational,
    voidFund,
    voidExpense,
    approveVoidExpense,
    rejectVoidExpense,
  }
}
