import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import rentToRentApi from '../api/rentToRent'

export function useRentToRent() {
  const toast = useToast()
  const debts = ref([])
  const bills = ref([])
  const paymentHistory = ref({ latest: [], groups: [] })
  const selectedDebt = ref(null)
  const summary = ref({
    total_amount: 0,
    paid_amount: 0,
    remaining_amount: 0,
    debt_count: 0,
    owner_count: 0,
  })
  const loading = ref(false)
  const historyLoading = ref(false)
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
    rental_owner_id: null,
    status: null,
  })
  const billFilters = ref({
    rental_owner_id: null,
    status: null,
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

  const fetchDebts = async (page = 1) => {
    loading.value = true
    error.value = null
    try {
      const response = await rentToRentApi.getRentToRentDebts({
        ...filters.value,
        page,
        per_page: pagination.value.per_page,
      })
      debts.value = response.data.data
      summary.value = response.data.summary || summary.value
      syncPagination(response.data.meta)
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil data rent-to-rent'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchDebt = async (debtId) => {
    actionLoading.value = true
    try {
      const response = await rentToRentApi.getRentToRentDebt(debtId)
      selectedDebt.value = response.data.data
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil detail rent-to-rent'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const updateDebtAmount = async (debtId, amountOverride) => {
    actionLoading.value = true
    try {
      const response = await rentToRentApi.updateRentToRentDebtAmount(debtId, {
        amount_override: amountOverride,
      })
      selectedDebt.value = response.data.data
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Nominal rent-to-rent diperbarui', life: 3000 })
      await fetchDebts(pagination.value.current_page)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui nominal rent-to-rent'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const fetchBills = async (page = 1) => {
    loading.value = true
    error.value = null
    try {
      const response = await rentToRentApi.getRentToRentBills({
        ...billFilters.value,
        page,
        per_page: pagination.value.per_page,
      })
      bills.value = response.data.data
      syncPagination(response.data.meta)
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil dokumen tagihan rent-to-rent'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchBill = async (billId) => {
    actionLoading.value = true
    try {
      const response = await rentToRentApi.getRentToRentBill(billId)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil dokumen tagihan rent-to-rent'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const generateBill = async (payload) => {
    actionLoading.value = true
    try {
      const response = await rentToRentApi.generateRentToRentBill(payload)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Dokumen tagihan rent-to-rent berhasil dibuat', life: 3000 })
      await fetchDebts(1)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal membuat dokumen tagihan rent-to-rent'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const markSent = async (billId) => {
    actionLoading.value = true
    try {
      const response = await rentToRentApi.markRentToRentBillSent(billId)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Dokumen ditandai sudah dikirim', life: 3000 })
      await fetchBills(pagination.value.current_page)
      await fetchDebts(1)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal menandai dokumen terkirim'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const addPayment = async (billId, payload) => {
    actionLoading.value = true
    try {
      const response = await rentToRentApi.addRentToRentPayment(billId, payload)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Pembayaran rent-to-rent berhasil dicatat', life: 3000 })
      await fetchBills(pagination.value.current_page)
      await fetchDebts(1)
      await fetchPaymentHistory()
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mencatat pembayaran rent-to-rent'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const requestVoid = async (billId, payload) => {
    actionLoading.value = true
    try {
      const response = await rentToRentApi.requestVoidRentToRentBill(billId, payload)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Request void tagihan dikirim untuk ACC supervisor', life: 3500 })
      await fetchBills(pagination.value.current_page)
      await fetchDebts(1)
      await fetchPaymentHistory()
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengajukan void tagihan'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const approveVoid = async (billId) => {
    const response = await rentToRentApi.approveVoidRentToRentBill(billId)
    return response.data.data
  }

  const rejectVoid = async (billId, payload) => {
    const response = await rentToRentApi.rejectVoidRentToRentBill(billId, payload)
    return response.data.data
  }

  const openPdf = async (billId, filename = 'rent-to-rent.pdf') => {
    actionLoading.value = true
    try {
      const response = await rentToRentApi.downloadRentToRentBillPdf(billId)
      const blob = new Blob([response.data], { type: 'application/pdf' })
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = filename
      link.click()
      URL.revokeObjectURL(url)
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengunduh PDF tagihan'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const fetchPaymentHistory = async () => {
    historyLoading.value = true
    try {
      const response = await rentToRentApi.getRentToRentPaymentHistory({
        latest_limit: 20,
        group_limit: 30,
      })
      paymentHistory.value = {
        latest: response.data.data?.latest || [],
        groups: response.data.data?.groups || [],
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil riwayat pembayaran rent-to-rent'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      historyLoading.value = false
    }
  }

  return {
    debts,
    bills,
    paymentHistory,
    selectedDebt,
    summary,
    loading,
    historyLoading,
    actionLoading,
    error,
    pagination,
    filters,
    billFilters,
    fetchDebts,
    fetchDebt,
    updateDebtAmount,
    fetchBills,
    fetchBill,
    generateBill,
    markSent,
    addPayment,
    requestVoid,
    approveVoid,
    rejectVoid,
    openPdf,
    fetchPaymentHistory,
  }
}
