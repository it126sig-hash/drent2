import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import fleetCardApi from '../api/fleetCard'

export function useFleetCard() {
  const toast = useToast()
  const cards = ref([])
  const bookingDetails = ref([])
  const funds = ref([])
  const history = ref([])
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
    driver_id: null,
    fleet_card_id: null,
  })
  const historyFilters = ref({
    search: '',
    fleet_card_id: null,
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

  const fetchCards = async (page = 1) => {
    loading.value = true
    error.value = null
    try {
      const response = await fleetCardApi.getFleetCards({
        ...filters.value,
        page,
        per_page: pagination.value.per_page,
      })
      cards.value = response.data.data
      syncPagination(response.data.meta)
    } catch (err) {
      showError(err, 'Gagal mengambil daftar kartu fleet')
      throw err
    } finally {
      loading.value = false
    }
  }

  const storeCard = async (payload) => {
    actionLoading.value = true
    try {
      const response = await fleetCardApi.createFleetCard(payload)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Kartu fleet berhasil dibuat', life: 3000 })
      await fetchCards(pagination.value.current_page)
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal membuat kartu fleet')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const updateCardStatus = async (cardId, status, notes = '') => {
    actionLoading.value = true
    try {
      const response = await fleetCardApi.updateFleetCardStatus(cardId, { status, notes })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Status kartu fleet diperbarui', life: 3000 })
      await fetchCards(pagination.value.current_page)
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal memperbarui status kartu fleet')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const fetchBookingDetails = async (page = 1) => {
    loading.value = true
    error.value = null
    try {
      const response = await fleetCardApi.getFleetCardBookingDetails({
        ...filters.value,
        page,
        per_page: pagination.value.per_page,
      })
      bookingDetails.value = response.data.data
      syncPagination(response.data.meta)
    } catch (err) {
      showError(err, 'Gagal mengambil periode sewa yang butuh kartu fleet')
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchFund = async (id) => {
    actionLoading.value = true
    try {
      const response = await fleetCardApi.getFleetCardFund(id)
      selectedFund.value = response.data.data
      return selectedFund.value
    } catch (err) {
      showError(err, 'Gagal mengambil detail kartu fleet')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const storeFund = async (bookingDetailId, payload) => {
    actionLoading.value = true
    try {
      const response = await fleetCardApi.createFleetCardFund(bookingDetailId, payload)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Kartu fleet berhasil dipasangkan ke booking', life: 3000 })
      await fetchBookingDetails(pagination.value.current_page)
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal memasangkan kartu fleet')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const closeFund = async (fundId, closeNote = '') => {
    actionLoading.value = true
    try {
      const response = await fleetCardApi.closeFleetCardFund(fundId, { close_note: closeNote })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Kartu fleet ditutup, saldo sisa tersimpan di kartu', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal menutup kartu fleet')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const fetchHistory = async (page = 1) => {
    loading.value = true
    try {
      const response = await fleetCardApi.getFleetCardHistory({
        ...historyFilters.value,
        page,
        per_page: pagination.value.per_page,
      })
      history.value = response.data.data
      syncPagination(response.data)
    } catch (err) {
      showError(err, 'Gagal mengambil histori kartu fleet')
      throw err
    } finally {
      loading.value = false
    }
  }

  const acceptFund = async (fundId) => {
    actionLoading.value = true
    try {
      const response = await fleetCardApi.acceptFleetCardFund(fundId)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Kartu fleet sudah diterima', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal menerima kartu fleet')
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
      const response = await fleetCardApi.createFleetCardExpense(fundId, form)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Bon kartu fleet berhasil dikirim', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal mengirim bon kartu fleet')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const approveExpense = async (expenseId) => {
    actionLoading.value = true
    try {
      const response = await fleetCardApi.approveFleetCardExpense(expenseId)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Bon disetujui dan saldo kartu dipotong', life: 3000 })
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
      const response = await fleetCardApi.rejectFleetCardExpense(expenseId, { rejection_reason: rejectionReason })
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
      const response = await fleetCardApi.getFleetCardExpensePhoto(expense.id)
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
      const response = await fleetCardApi.getDriverFleetCardFunds({
        page,
        per_page: pagination.value.per_page,
      })
      funds.value = response.data.data
      syncPagination(response.data.meta)
    } catch (err) {
      showError(err, 'Gagal mengambil kartu fleet driver')
      throw err
    } finally {
      loading.value = false
    }
  }

  const voidFund = async (fundId, voidReason) => {
    actionLoading.value = true
    try {
      const response = await fleetCardApi.voidFleetCardFund(fundId, { void_reason: voidReason })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Kartu fleet berhasil di-void', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal mem-void kartu fleet')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const approveVoidFund = async (fundId) => {
    actionLoading.value = true
    try {
      const response = await fleetCardApi.approveVoidFleetCardFund(fundId)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Request void kartu fleet disetujui', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal menyetujui void kartu fleet')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const rejectVoidFund = async (fundId, rejectionNote) => {
    actionLoading.value = true
    try {
      const response = await fleetCardApi.rejectVoidFleetCardFund(fundId, { rejection_note: rejectionNote })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Request void kartu fleet ditolak', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal menolak void kartu fleet')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const voidExpense = async (expenseId, voidReason) => {
    actionLoading.value = true
    try {
      const response = await fleetCardApi.voidFleetCardExpense(expenseId, { void_reason: voidReason })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Pengajuan void bon berhasil dikirim', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal mengajukan void bon')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const approveVoidExpense = async (expenseId) => {
    actionLoading.value = true
    try {
      const response = await fleetCardApi.approveVoidFleetCardExpense(expenseId)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Request void bon disetujui', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal menyetujui void bon')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const rejectVoidExpense = async (expenseId, rejectionNote) => {
    actionLoading.value = true
    try {
      const response = await fleetCardApi.rejectVoidFleetCardExpense(expenseId, { rejection_note: rejectionNote })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Request void bon ditolak', life: 3000 })
      return response.data.data
    } catch (err) {
      showError(err, 'Gagal menolak void bon')
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  return {
    cards,
    bookingDetails,
    funds,
    history,
    selectedFund,
    loading,
    actionLoading,
    error,
    pagination,
    filters,
    historyFilters,
    fetchCards,
    storeCard,
    updateCardStatus,
    fetchBookingDetails,
    fetchFund,
    storeFund,
    closeFund,
    fetchHistory,
    acceptFund,
    submitExpense,
    approveExpense,
    rejectExpense,
    openExpensePhoto,
    fetchDriverFunds,
    voidFund,
    approveVoidFund,
    rejectVoidFund,
    voidExpense,
    approveVoidExpense,
    rejectVoidExpense,
  }
}
