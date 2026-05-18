import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import receivableApi from '../api/receivable'
import bookingApi from '../api/booking'

export function useReceivable() {
  const toast = useToast()
  const receivables = ref([])
  const invoices = ref([])
  const paymentHistory = ref({
    latest: [],
    groups: [],
  })
  const paymentHistoryPagination = ref({
    latest: {
      total: 0,
      per_page: 15,
      current_page: 1,
      last_page: 1,
    },
    groups: {
      total: 0,
      per_page: 10,
      current_page: 1,
      last_page: 1,
    },
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
    invoice_status: null,
    search: '',
  })
  const invoiceFilters = ref({
    status: null,
    search: '',
  })

  const fetchAll = async (page = 1) => {
    loading.value = true
    error.value = null

    try {
      const response = await receivableApi.getReceivables({
        ...filters.value,
        page,
        per_page: pagination.value.per_page,
      })

      receivables.value = response.data.data
      if (response.data.meta) {
        pagination.value = {
          total: response.data.meta.total,
          per_page: response.data.meta.per_page,
          current_page: response.data.meta.current_page,
          last_page: response.data.meta.last_page,
        }
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil data piutang'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchInvoices = async (page = 1) => {
    loading.value = true
    error.value = null

    try {
      const response = await receivableApi.getInvoices({
        ...invoiceFilters.value,
        page,
        per_page: pagination.value.per_page,
      })

      invoices.value = response.data.data
      if (response.data.meta) {
        pagination.value = {
          total: response.data.meta.total,
          per_page: response.data.meta.per_page,
          current_page: response.data.meta.current_page,
          last_page: response.data.meta.last_page,
        }
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil data invoice'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchPaymentHistory = async (options = {}) => {
    historyLoading.value = true
    error.value = null

    try {
      const view = options.view || 'all'
      const latestPage = options.latestPage
        || (view === 'latest' ? options.page : paymentHistoryPagination.value.latest.current_page)
        || 1
      const groupPage = options.groupPage
        || (view === 'group' ? options.page : paymentHistoryPagination.value.groups.current_page)
        || 1
      const response = await receivableApi.getPaymentHistory({
        view,
        latest_page: latestPage,
        latest_per_page: paymentHistoryPagination.value.latest.per_page,
        group_page: groupPage,
        group_per_page: paymentHistoryPagination.value.groups.per_page,
      })
      const payload = response.data.data || {}

      paymentHistory.value = {
        latest: view === 'group' ? paymentHistory.value.latest : (payload.latest || []),
        groups: view === 'latest' ? paymentHistory.value.groups : (payload.groups || []),
      }
      paymentHistoryPagination.value = {
        latest: payload.meta?.latest || paymentHistoryPagination.value.latest,
        groups: payload.meta?.groups || paymentHistoryPagination.value.groups,
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengambil riwayat pembayaran'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      historyLoading.value = false
    }
  }

  const generate = async (payload) => {
    actionLoading.value = true
    try {
      const response = await receivableApi.generateInvoice(payload)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Invoice berhasil dibuat', life: 3000 })
      await fetchAll(pagination.value.current_page)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal membuat invoice'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const markSent = async (invoiceId) => {
    actionLoading.value = true
    try {
      const response = await receivableApi.markInvoiceSent(invoiceId)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Link publik invoice sudah siap', life: 3000 })
      // Hanya refresh invoices — receivables tidak berubah saat mark sent
      await fetchInvoices(pagination.value.current_page)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui waktu kirim invoice'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const refreshInvoiceAmount = async (invoiceId, data = {}) => {
    actionLoading.value = true
    try {
      const response = await receivableApi.refreshInvoiceAmount(invoiceId, data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Nominal invoice berhasil diperbarui', life: 3000 })
      // Refresh kedua list paralel; nominal berubah di kedua tab
      await Promise.all([
        fetchAll(pagination.value.current_page),
        fetchInvoices(pagination.value.current_page),
      ])
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memperbarui nominal invoice'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const openPdf = async (invoiceId, invoiceNumber = 'invoice') => {
    actionLoading.value = true
    try {
      const response = await receivableApi.downloadInvoicePdf(invoiceId)
      const blob = new Blob([response.data], { type: 'application/pdf' })
      const url = window.URL.createObjectURL(blob)
      window.open(url, '_blank', 'noopener,noreferrer')
      window.setTimeout(() => window.URL.revokeObjectURL(url), 60000)
      return { url, invoiceNumber }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal membuka PDF invoice'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const addPayment = async (invoiceId, data) => {
    actionLoading.value = true
    try {
      const response = await receivableApi.addInvoicePayment(invoiceId, data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Pembayaran invoice berhasil dicatat', life: 3000 })
      // Refresh invoices & receivables paralel; history di-refresh background (tidak di-await)
      await Promise.all([
        fetchInvoices(pagination.value.current_page),
        fetchAll(pagination.value.current_page),
      ])
      fetchPaymentHistory() // background; tidak block UI
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mencatat pembayaran invoice'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  const requestVoidPayment = async (paymentId, data) => {
    actionLoading.value = true
    try {
      const response = await bookingApi.requestVoidBookingPayment(paymentId, data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Request void pembayaran dikirim ke supervisor', life: 3000 })
      // Void hanya mengubah status payment; cukup refresh history
      await fetchPaymentHistory()
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal mengajukan void pembayaran'
      toast.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      actionLoading.value = false
    }
  }

  return {
    receivables,
    invoices,
    paymentHistory,
    paymentHistoryPagination,
    loading,
    historyLoading,
    actionLoading,
    error,
    pagination,
    filters,
    invoiceFilters,
    fetchAll,
    fetchInvoices,
    fetchPaymentHistory,
    generate,
    markSent,
    refreshInvoiceAmount,
    openPdf,
    addPayment,
    requestVoidPayment,
  }
}

