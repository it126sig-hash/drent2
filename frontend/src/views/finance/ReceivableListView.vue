<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { format } from 'date-fns'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import ConfirmDialog from 'primevue/confirmdialog'
import { usePaymentAccount } from '../../composables/usePaymentAccount'
import { useReceivable } from '../../composables/useReceivable'
import { useAuthStore } from '../../stores/auth'
import { printPaymentReceipt, buildReceiptFromPaymentHistory } from '../../utils/printReceipt'
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue'
import InvoiceTermsEditor from '../../components/InvoiceTermsEditor.vue'
import { fetchCities } from '../../api/city'
import { getInvoiceTermsTemplates } from '../../api/invoiceTermsTemplate'

const router = useRouter()
const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()
const {
  receivables,
  invoices,
  paymentHistory,
  paymentHistoryPagination,
  loading,
  historyLoading,
  actionLoading,
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
  fetchInvoiceHistories,
} = useReceivable()
const { accounts, fetchAll: fetchPaymentAccounts } = usePaymentAccount()

const activeTab = ref('receivables')
const selectedRows = ref([])
const showGenerateDialog = ref(false)
const showPaymentDialog = ref(false)
const selectedReceivableRows = ref([])
const selectedInvoice = ref(null)
const paymentConfirming = ref(false)
const paymentSubmitting = ref(false)
const expandedPaymentGroups = ref({})
const paymentHistoryView = ref('latest')
const showVoidPaymentDialog = ref(false)
const selectedVoidPayment = ref(null)
const voidPaymentForm = ref({ void_reason: '' })
const voidPaymentFormErrors = ref({})

const showHistoryDialog = ref(false)
const historyDialogTitle = ref('')
const invoiceHistories = ref([])
const historyLoading2 = ref(false)

const cities = ref([])
const loadingCities = ref(false)
const cityOptions = computed(() => {
  const options = [{ label: 'Semua Kota', value: null }]
  cities.value.forEach(cityName => {
    options.push({ label: cityName, value: cityName })
  })
  return options
})
const loadCities = async () => {
  loadingCities.value = true
  try {
    const response = await fetchCities({ is_active: 1, per_page: 100 })
    cities.value = response.data.data.map(c => c.nama)
  } catch (err) {
    console.error('Failed to load cities', err)
  } finally {
    loadingCities.value = false
  }
}

const generateForm = ref({
  due_date: null,
  terms_and_conditions: '',
})

const termsTemplates = ref([])
const selectedTemplateId = ref(null)
const loadingTemplates = ref(false)

const termsTemplateOptions = computed(() => {
  const opts = [{ label: 'Tidak ada template', value: null }]
  termsTemplates.value.forEach(t => {
    opts.push({ label: t.name + (t.is_default ? ' (Default)' : ''), value: t.id })
  })
  return opts
})

const loadTermsTemplates = async () => {
  loadingTemplates.value = true
  try {
    const res = await getInvoiceTermsTemplates()
    termsTemplates.value = res.data.data || []
  } catch {
    // silently ignore
  } finally {
    loadingTemplates.value = false
  }
}

const onTemplateSelect = (templateId) => {
  if (!templateId) {
    generateForm.value.terms_and_conditions = ''
    return
  }
  const tpl = termsTemplates.value.find(t => t.id === templateId)
  if (tpl) generateForm.value.terms_and_conditions = tpl.content
}
const paymentForm = ref({
  payment_account_id: null,
  amount: null,
  paid_at: new Date(),
})

const invoiceStatusOptions = [
  { label: 'Semua', value: null },
  { label: 'Belum Buat Invoice', value: 'not_generated' },
  { label: 'Sudah Buat Invoice', value: 'generated' },
  { label: 'Perlu Update', value: 'changed' },
]
const generatedInvoiceStatusOptions = [
  { label: 'Semua', value: null },
  { label: 'Dibuat', value: 'generated' },
  { label: 'Partial Paid', value: 'partial_paid' },
  { label: 'Paid', value: 'paid' },
]
const paymentHistoryViewOptions = [
  { label: 'Pembayaran Terakhir', value: 'latest', icon: 'pi pi-clock' },
  { label: 'Group per Kode Transaksi', value: 'group', icon: 'pi pi-list' },
]

const selectedTotal = computed(() => selectedReceivableRows.value.reduce((sum, row) => sum + (row.total_biaya?.total || 0), 0))
const selectedBookingCodes = computed(() => selectedReceivableRows.value.map(row => row.kode_booking).join(', '))
const isCombinedInvoice = computed(() => selectedReceivableRows.value.length > 1)
const defaultDueDate = computed(() => {
  const dates = selectedReceivableRows.value
    .map(row => {
      const dateString = row.rent_period?.tgl_kembali || row.due_date
      return dateString ? new Date(dateString) : null
    })
    .filter(date => date && !Number.isNaN(date.getTime()))
    .sort((a, b) => b.getTime() - a.getTime())

  return dates[0] || null
})
const paymentAccountOptions = computed(() => accounts.value.map(account => ({
  label: `${account.nama_bank} - ${account.nomor_rekening}`,
  value: account.id,
})))
const selectedInvoiceRemaining = computed(() => selectedInvoice.value?.remaining_amount || 0)
const selectedInvoiceItems = computed(() => {
  if (selectedInvoice.value?.items?.length) return selectedInvoice.value.items

  return (selectedInvoice.value?.bookings || []).map(booking => ({
    type: 'booking',
    description: booking.kode_booking,
    booking_code: booking.kode_booking,
    customer_name: booking.customer_name,
    price: booking.amount,
    qty: 1,
    amount: booking.amount,
  }))
})
const selectedInvoiceCustomerNames = computed(() => {
  const names = (selectedInvoice.value?.bookings || [])
    .map(booking => booking.customer_name)
    .filter(Boolean)

  return [...new Set(names)].join(', ') || '-'
})
const isPaymentSubmitDisabled = computed(() =>
  actionLoading.value
  || paymentConfirming.value
  || paymentSubmitting.value
  || !paymentForm.value.payment_account_id
  || !paymentForm.value.amount
)

const selectedVoidPaymentId = computed(() => bookingPaymentId(selectedVoidPayment.value))

watch(defaultDueDate, (date) => {
  generateForm.value.due_date = date
})

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)
}

const formatSignedCurrency = (value) => {
  const amount = Number(value || 0)
  if (amount === 0) return formatCurrency(0)

  return `${amount > 0 ? '+' : '-'}${formatCurrency(Math.abs(amount))}`
}

const getInvoiceDueWarning = (invoice, customerStatus) => {
  if (!invoice || !invoice.due_date) return null
  if (invoice.status === 'paid' || invoice.status === 'void') return null

  const dueDate = new Date(invoice.due_date)
  if (Number.isNaN(dueDate.getTime())) return null

  const today = new Date()
  today.setHours(0, 0, 0, 0)

  const due = new Date(dueDate)
  due.setHours(0, 0, 0, 0)

  const diffTime = due.getTime() - today.getTime()
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

  const cleanStatus = (customerStatus || 'normal').toLowerCase()

  let threshold = 1 // H-1 default (normal and member)
  if (cleanStatus === 'rent to rent') {
    threshold = 7
  } else if (cleanStatus === 'corporate') {
    threshold = 30
  }

  if (diffDays <= threshold) {
    if (diffDays < 0) {
      return { severity: 'danger', label: `Overdue ${Math.abs(diffDays)} hari` }
    } else if (diffDays === 0) {
      return { severity: 'warn', label: 'Jatuh Tempo Hari Ini' }
    } else {
      return { severity: 'warn', label: `H-${diffDays} Jatuh Tempo` }
    }
  }

  return null
}

const rowClass = (data) => {
  if (activeTab.value === 'receivables') {
    if (data.invoice?.generated) {
      const warning = getInvoiceDueWarning(data.invoice, data.customer?.status)
      if (warning) {
        return warning.severity === 'danger' ? 'row-due-overdue' : 'row-due-warning'
      }
    }
  } else if (activeTab.value === 'invoices') {
    const firstBooking = data.bookings?.[0]
    const customerStatus = firstBooking?.customer_status || 'normal'
    const warning = getInvoiceDueWarning(data, customerStatus)
    if (warning) {
      return warning.severity === 'danger' ? 'row-due-overdue' : 'row-due-warning'
    }
  }
  return ''
}

const invoiceChange = (invoice) => invoice?.invoice_reconciliation || null

const hasInvoiceChange = (invoice) => Boolean(invoiceChange(invoice)?.is_changed)

const canRefreshInvoice = (invoice) =>
  hasInvoiceChange(invoice) &&
  (
    ['generated', 'partial_paid'].includes(invoice?.status) ||
    (invoice?.status === 'paid' && (invoice?.invoice_reconciliation?.difference_amount ?? 0) > 0)
  )

const buildPaidInvoiceForAmend = (row) => ({
  id: row.paid_invoice_with_delta.id,
  invoice_number: row.paid_invoice_with_delta.number,
  status: row.paid_invoice_with_delta.status,
  invoice_reconciliation: row.paid_invoice_with_delta.reconciliation,
  public_path: row.paid_invoice_with_delta.public_path,
  sent_at: row.paid_invoice_with_delta.sent_at,
})

const openHistoryDialog = async (invoiceId, invoiceNumber) => {
  historyDialogTitle.value = invoiceNumber || 'Invoice'
  invoiceHistories.value = []
  showHistoryDialog.value = true
  historyLoading2.value = true
  invoiceHistories.value = await fetchInvoiceHistories(invoiceId)
  historyLoading2.value = false
}

const historyEventLabel = (eventType) => {
  const map = {
    created: 'Invoice Dibuat',
    sent: 'Invoice Dikirim',
    amended: 'Nominal Diperbarui',
    payment_received: 'Pembayaran Diterima',
    voided: 'Invoice Void',
  }
  return map[eventType] || eventType
}

const historyEventSeverity = (eventType) => {
  if (eventType === 'created') return 'info'
  if (eventType === 'sent') return 'secondary'
  if (eventType === 'amended') return 'warn'
  if (eventType === 'payment_received') return 'success'
  if (eventType === 'voided') return 'danger'
  return 'secondary'
}

const invoiceChangeLabel = (invoice) => {
  const change = invoiceChange(invoice)
  if (!change?.is_changed) return ''

  return `Perlu Update ${formatSignedCurrency(change.difference_amount)}`
}

const formatDateTime = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy HH:mm')
}

const formatDate = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy')
}

const toApiDate = (value) => {
  if (!value) return null
  return format(new Date(value), 'yyyy-MM-dd')
}

const onPage = (event) => {
  pagination.value.current_page = event.page + 1
  if (activeTab.value === 'receivables') {
    fetchAll(pagination.value.current_page)
    return
  }

  fetchInvoices(pagination.value.current_page)
}

const onLatestPaymentHistoryPage = (event) => {
  fetchPaymentHistory({
    view: 'latest',
    page: event.page + 1,
  })
}

const onGroupPaymentHistoryPage = (event) => {
  expandedPaymentGroups.value = {}
  fetchPaymentHistory({
    view: 'group',
    page: event.page + 1,
  })
}

const refreshPaymentHistory = () => {
  fetchPaymentHistory({ view: paymentHistoryView.value })
}

const applyFilters = () => {
  selectedRows.value = []
  pagination.value.current_page = 1
  if (activeTab.value === 'receivables') {
    fetchAll(1)
    return
  }

  fetchInvoices(1)
}

const switchTab = (tab) => {
  activeTab.value = tab
  selectedRows.value = []
  pagination.value.current_page = 1
  if (tab === 'receivables') {
    fetchAll(1)
    return
  }

  if (tab === 'invoices') {
    fetchInvoices(1)
    return
  }

  refreshPaymentHistory()
}

const switchPaymentHistoryView = (view) => {
  paymentHistoryView.value = view
  expandedPaymentGroups.value = {}
  fetchPaymentHistory({ view })
}

const openGenerateDialog = (row = null) => {
  const rows = row ? [row] : selectedRows.value

  if (!rows.length) {
    return
  }

  const generatedRows = rows.filter(item => item.invoice?.generated)
  if (generatedRows.length) {
    const codes = generatedRows.map(item => item.kode_booking).join(', ')
    toast.add({
      severity: 'error',
      summary: 'Invoice aktif tersedia',
      detail: `Booking ${codes} sudah memiliki invoice aktif. Proses buat invoice dibatalkan.`,
      life: 5000,
    })
    return
  }

  selectedReceivableRows.value = rows
  generateForm.value.due_date = defaultDueDate.value

  // Auto-load default template content if templates already fetched
  const defaultTpl = termsTemplates.value.find(t => t.is_default)
  if (defaultTpl) {
    selectedTemplateId.value = defaultTpl.id
    generateForm.value.terms_and_conditions = defaultTpl.content
  } else {
    selectedTemplateId.value = null
    generateForm.value.terms_and_conditions = ''
  }

  showGenerateDialog.value = true
}

const submitGenerateInvoice = async () => {
  if (!selectedReceivableRows.value.length) return

  await generate({
    booking_ids: selectedReceivableRows.value.map(row => row.id),
    due_date: toApiDate(generateForm.value.due_date),
    terms_and_conditions: generateForm.value.terms_and_conditions || null,
  })
  selectedRows.value = []
  selectedReceivableRows.value = []
  showGenerateDialog.value = false
  if (activeTab.value === 'invoices') {
    await fetchInvoices(1)
  }
}

const sendInvoice = async (invoiceId) => {
  if (!invoiceId) return
  const invoice = await markSent(invoiceId)
  const publicUrl = invoice?.public_path
    ? new URL(invoice.public_path, window.location.origin).toString()
    : invoice?.public_url

  if (publicUrl && navigator.clipboard) {
    await navigator.clipboard.writeText(publicUrl)
    toast.add({ severity: 'info', summary: 'Link disalin', detail: publicUrl, life: 5000 })
  }
}

const submitRefreshInvoice = async (invoice, confirmSentRevision = false) => {
  if (!invoice?.id) return

  await refreshInvoiceAmount(invoice.id, {
    confirm_sent_revision: confirmSentRevision,
  })
}

const openRefreshInvoiceDialog = (invoice) => {
  if (!invoice?.id || !canRefreshInvoice(invoice)) return

  const change = invoiceChange(invoice)
  if (!change.requires_sent_confirmation) {
    submitRefreshInvoice(invoice).catch(() => { })
    return
  }

  confirm.require({
    message: `Invoice ${invoice.invoice_number || invoice.number || ''} sudah pernah dikirim. Update nominal akan mengubah link publik yang sudah dibagikan. Lanjutkan?`,
    header: 'Konfirmasi Update Invoice',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Ya, Update',
    rejectLabel: 'Batal',
    acceptClass: 'app-dialog-button app-dialog-button-primary',
    rejectClass: 'app-dialog-button app-dialog-button-secondary',
    accept: async () => {
      await submitRefreshInvoice(invoice, true)
    },
  })
}

const getInvoicePublicUrl = (invoice) => {
  if (!invoice) return null
  return invoice.public_path
    ? new URL(invoice.public_path, window.location.origin).toString()
    : invoice.public_url
}

const openInvoiceView = (invoice) => {
  const publicUrl = getInvoicePublicUrl(invoice)

  if (!publicUrl) {
    toast.add({
      severity: 'warn',
      summary: 'Link belum tersedia',
      detail: 'Invoice belum memiliki link publik.',
      life: 4000,
    })
    return
  }

  window.open(publicUrl, '_blank', 'noopener,noreferrer')
}

const openPaymentDialog = (invoice) => {
  selectedInvoice.value = invoice
  paymentForm.value = {
    payment_account_id: paymentAccountOptions.value[0]?.value || null,
    amount: invoice.remaining_amount || null,
    paid_at: new Date(),
  }
  showPaymentDialog.value = true
}

const openPaymentFromReceivable = (row) => {
  if (!row.invoice?.generated) {
    toast.add({
      severity: 'warn',
      summary: 'Invoice belum dibuat',
      detail: 'Buat invoice terlebih dahulu sebelum mencatat pembayaran.',
      life: 4000,
    })
    return
  }

  openPaymentDialog({
    id: row.invoice.id,
    invoice_number: row.invoice.number,
    total_amount: row.invoice.total_amount ?? row.total_biaya?.sisa ?? 0,
    paid_amount: row.invoice.paid_amount ?? 0,
    remaining_amount: row.invoice.remaining_amount ?? row.total_biaya?.sisa ?? 0,
    status: row.invoice.status,
    public_url: row.invoice.public_url,
    public_path: row.invoice.public_path,
    sent_at: row.invoice.sent_at,
    generated_at: row.invoice.generated_at,
    due_date: row.invoice.due_date || row.due_date,
    invoice_reconciliation: row.invoice.invoice_reconciliation,
    payments: row.invoice.payments || [],
    items: row.invoice.items || [],
    bookings: [{
      id: row.id,
      kode_booking: row.kode_booking,
      customer_name: row.customer?.nama,
      amount: row.invoice.total_amount ?? row.total_biaya?.sisa ?? 0,
    }],
    ...(row.invoice.items?.length ? {} : {
      items: [{
        type: 'booking',
        description: row.kode_booking,
        booking_code: row.kode_booking,
        customer_name: row.customer?.nama,
        vehicle_name: row.vehicle?.jenis,
        vehicle_plate: row.vehicle?.no_polisi,
        price: row.invoice.total_amount ?? row.total_biaya?.total ?? 0,
        qty: 1,
        amount: row.invoice.total_amount ?? row.total_biaya?.total ?? 0,
      }]
    }),
  })
}

const openInvoicePdf = async (invoice) => {
  if (!invoice?.id) return
  await openPdf(invoice.id, invoice.invoice_number || invoice.number)
}

const submitInvoicePayment = async () => {
  if (!selectedInvoice.value || isPaymentSubmitDisabled.value) return
  paymentConfirming.value = true

  confirm.require({
    message: `Catat pembayaran ${formatCurrency(paymentForm.value.amount)} untuk invoice ${selectedInvoice.value.invoice_number || selectedInvoice.value.number || ''}?`,
    header: 'Konfirmasi Pembayaran',
    icon: 'pi pi-credit-card',
    acceptLabel: 'Ya, Simpan',
    rejectLabel: 'Batal',
    acceptClass: 'app-dialog-button app-dialog-button-primary',
    rejectClass: 'app-dialog-button app-dialog-button-secondary',
    accept: async () => {
      if (paymentSubmitting.value) return
      paymentConfirming.value = false
      paymentSubmitting.value = true
      try {
        await addPayment(selectedInvoice.value.id, {
          payment_account_id: paymentForm.value.payment_account_id,
          amount: paymentForm.value.amount,
          paid_at: toApiDate(paymentForm.value.paid_at),
        })
        showPaymentDialog.value = false
        selectedInvoice.value = null
      } finally {
        paymentSubmitting.value = false
      }
    },
    reject: () => {
      paymentConfirming.value = false
    },
    onHide: () => {
      paymentConfirming.value = false
    },
  })
}

const bookingPaymentId = (payment) => {
  if (!payment?.id) return null
  if (payment.booking_payment_id) return Number(payment.booking_payment_id)

  const match = String(payment.id).match(/^transaction-(\d+)$/)
  return match ? Number(match[1]) : null
}

const canRequestVoidPayment = (payment) =>
  ['transaction', 'invoice_allocation'].includes(payment?.source)
  && bookingPaymentId(payment)
  && (payment.status || 'active') === 'active'

const openVoidPaymentDialog = (payment) => {
  if (!canRequestVoidPayment(payment)) return

  selectedVoidPayment.value = payment
  voidPaymentForm.value = { void_reason: '' }
  voidPaymentFormErrors.value = {}
  showVoidPaymentDialog.value = true
}

const submitVoidPaymentRequest = async () => {
  voidPaymentFormErrors.value = {}
  if (!selectedVoidPaymentId.value) return

  try {
    await requestVoidPayment(selectedVoidPaymentId.value, voidPaymentForm.value)
    showVoidPaymentDialog.value = false
    selectedVoidPayment.value = null
  } catch (err) {
    if (err.response?.data?.errors) voidPaymentFormErrors.value = err.response.data.errors
  }
}

const invoiceSeverity = (status) => {
  if (status === 'paid') return 'success'
  if (status === 'partial_paid') return 'info'
  if (status === 'void') return 'danger'
  return 'warn'
}

const paymentSourceSeverity = (source) => {
  if (source === 'invoice') return 'info'
  if (source === 'invoice_allocation') return 'warn'
  return 'success'
}

const paymentStatusSeverity = (status) => {
  if (status === 'voided') return 'danger'
  if (status === 'void_requested') return 'warn'
  return 'success'
}

const joinList = (items) => {
  return (items || []).filter(Boolean).join(', ') || '-'
}

const printHistoryPayment = (payment) => {
  const receipt = buildReceiptFromPaymentHistory(payment, authStore.branch)
  printPaymentReceipt(receipt)
}

onMounted(async () => {
  // fetchPaymentHistory dipanggil on-demand saat user klik tab Riwayat Pembayaran
  const initialTab = route.query.tab
  const initialSearch = typeof route.query.search === 'string' ? route.query.search : ''

  if (initialTab === 'invoices') {
    activeTab.value = 'invoices'
    if (initialSearch) invoiceFilters.value.search = initialSearch
  } else if (initialTab === 'receivables') {
    activeTab.value = 'receivables'
    if (initialSearch) filters.value.search = initialSearch
  } else if (initialTab === 'payments') {
    activeTab.value = 'payments'
  }

  const promises = [fetchPaymentAccounts({ per_page: 100 }), loadCities(), loadTermsTemplates()]
  if (activeTab.value === 'invoices') {
    promises.push(fetchInvoices(1))
  } else if (activeTab.value === 'payments') {
    promises.push(fetchPaymentHistory({ view: 'all' }))
  } else {
    promises.push(fetchAll())
  }

  await Promise.all(promises)
})
</script>

<template>
  <div class="page-container" :class="{ 'table-page-active': activeTab !== 'payments' }">
    <ConfirmDialog />
    <div class="page-header">
      <div class="header-left">
        <h1 class="text-h1">Piutang & Invoice</h1>
        <p class="text-secondary text-xs">Kelola piutang, invoice yang sudah dibuat, dan pembayaran invoice.</p>
      </div>
      <div class="header-actions">
        <div class="tab-toggle-container">
          <div class="pill-toggle">
            <button class="toggle-item" :class="{ active: activeTab === 'receivables' }" @click="switchTab('receivables')">
              Piutang
            </button>
            <button class="toggle-item" :class="{ active: activeTab === 'invoices' }" @click="switchTab('invoices')">
              Invoice
            </button>
            <button class="toggle-item" :class="{ active: activeTab === 'payments' }" @click="switchTab('payments')">
              Riwayat Pembayaran
            </button>
          </div>
        </div>
        <button class="btn-pill btn-primary" :disabled="selectedRows.length === 0 || actionLoading" @click="openGenerateDialog()">
          <i class="pi pi-file-plus"></i>
          {{ selectedRows.length > 1 ? 'Buat Invoice Gabungan' : 'Buat Invoice' }}
        </button>
      </div>
    </div>

    <div v-if="activeTab !== 'payments'" class="list-tab-fill receivable-list-tab">
      <div class="filter-bar surface-card">
        <div class="filter-groups">
          <div class="filter-group filter-group-search" v-if="activeTab === 'receivables'">
            <label>Pencarian</label>
            <span class="p-input-icon-left">
              <i class="pi pi-search"></i>
              <InputText v-model="filters.search" placeholder="Booking, invoice, pelanggan, kendaraan" class="w-full" @keyup.enter="applyFilters" />
            </span>
          </div>
          <div class="filter-group filter-group-search" v-else>
            <label>Pencarian</label>
            <span class="p-input-icon-left">
              <i class="pi pi-search"></i>
              <InputText v-model="invoiceFilters.search" placeholder="Invoice, booking, pelanggan" class="w-full" @keyup.enter="applyFilters" />
            </span>
          </div>
          <div class="filter-group" v-if="activeTab === 'receivables'">
            <label>Status Invoice</label>
            <Dropdown v-model="filters.invoice_status" :options="invoiceStatusOptions" optionLabel="label" optionValue="value" placeholder="Semua" class="w-full md:w-48" />
          </div>
          <div class="filter-group" v-else>
            <label>Status</label>
            <Dropdown v-model="invoiceFilters.status" :options="generatedInvoiceStatusOptions" optionLabel="label" optionValue="value" placeholder="Semua" class="w-full md:w-48" />
          </div>
          <div class="filter-group" v-if="activeTab === 'receivables'">
            <label>Kota</label>
            <Dropdown v-model="filters.kota" :options="cityOptions" optionLabel="label" optionValue="value" placeholder="Semua" class="w-full md:w-48" :loading="loadingCities" />
          </div>
          <div class="filter-group" v-else>
            <label>Kota</label>
            <Dropdown v-model="invoiceFilters.kota" :options="cityOptions" optionLabel="label" optionValue="value" placeholder="Semua" class="w-full md:w-48" :loading="loadingCities" />
          </div>
        </div>
        <div class="filter-actions">
          <button class="btn-pill btn-primary btn-pill-compact" :disabled="loading" @click="applyFilters">
            <i class="pi pi-filter"></i>
            Filter
          </button>
        </div>
      </div>

      <div v-if="loading" class="loading-strip">
        <ProgressBar mode="indeterminate" style="height: 4px" />
      </div>

      <div v-if="activeTab === 'receivables'" class="table-shell">
        <DataTable v-model:selection="selectedRows" :value="receivables" dataKey="id" lazy paginator scrollable scrollHeight="flex" :rows="pagination.per_page" :totalRecords="pagination.total" :loading="loading" @page="onPage" responsiveLayout="scroll" class="drent-datatable" :rowClass="rowClass">
          <Column selectionMode="multiple" headerStyle="width: 3rem" />
          <Column header="Aksi" style="min-width: 15rem">
            <template #body="{ data }">
              <div class="table-actions">
                <button v-if="data.invoice?.generated && canRefreshInvoice(data.invoice)" class="btn-pill btn-primary btn-pill-compact" :disabled="actionLoading" @click="openRefreshInvoiceDialog(data.invoice)">
                  <i class="pi pi-refresh"></i>
                  Update Invoice
                </button>
                <button v-else-if="data.invoice?.generated" class="btn-pill btn-primary btn-pill-compact" :disabled="(data.invoice?.remaining_amount ?? 0) <= 0" @click="openPaymentFromReceivable(data)">
                  <i class="pi pi-wallet"></i>
                  Bayar Invoice
                </button>
                <button v-else-if="data.paid_invoice_with_delta" class="btn-pill btn-primary btn-pill-compact" :disabled="actionLoading" @click="openRefreshInvoiceDialog(buildPaidInvoiceForAmend(data))">
                  <i class="pi pi-file-edit"></i>
                  Amend Invoice
                </button>
                <button v-else class="btn-pill btn-primary btn-pill-compact" :disabled="actionLoading" @click="openGenerateDialog(data)">
                  <i class="pi pi-file-plus"></i>
                  Buat Invoice
                </button>
                <span v-if="data.invoice?.generated" class="action-pill-group">
                  <button class="action-btn" :disabled="actionLoading" title="Kirim invoice" @click="sendInvoice(data.invoice.id)">
                    <i class="pi pi-send"></i>
                  </button>
                  <button class="action-btn" :disabled="!getInvoicePublicUrl(data.invoice)" title="Lihat invoice" @click="openInvoiceView(data.invoice)">
                    <i class="pi pi-eye"></i>
                  </button>
                  <button class="action-btn" title="Lihat history perubahan" @click="openHistoryDialog(data.invoice.id, data.invoice.number)">
                    <i class="pi pi-history"></i>
                  </button>
                </span>
                <span v-if="data.invoice?.sent_at" class="text-xs mt-1 text-secondary">{{
                  formatDateTime(data.invoice?.sent_at) }} <span v-if="data.invoice?.sent_by_name">({{
                    data.invoice.sent_by_name }})</span></span>
              </div>
            </template>
          </Column>
          <Column header="Booking" style="min-width: 10rem">
            <template #body="{ data }">
              <button class="link-button text-xs flex" @click="router.push(`/bookings/${data.id}`)">{{ data.kode_booking
              }}</button>
              <div class="font-semibold">{{ data.customer?.nama || '-' }}</div>
              <div class="text-xs text-secondary">{{ data.customer?.status || '-' }}</div>
            </template>
          </Column>
          <Column header="Tanggal Invoice" style="min-width: 12rem">
            <template #body="{ data }">
              <BookingStatusBadge :status="data.invoice?.number ? 'generated' : 'not_generated'" :text="data.invoice?.number || 'Belum Buat Invoice'" />
              <Tag v-if="hasInvoiceChange(data.invoice)" :value="invoiceChangeLabel(data.invoice)" severity="warn" class="mt-2" />
              <div v-if="data.invoice?.generated" class="flex flex-col gap-1 mt-1">
                <div class="font-semibold text-italic">Due: {{ formatDate(data.invoice?.due_date || data.due_date) }}</div>
                <Tag v-if="getInvoiceDueWarning(data.invoice, data.customer?.status)" :value="getInvoiceDueWarning(data.invoice, data.customer?.status).label" :severity="getInvoiceDueWarning(data.invoice, data.customer?.status).severity" class="w-max" />
              </div>
              <div class="text-xs mt-1 text-secondary" v-if="data.invoice?.generated">
                Di Buat: {{ formatDateTime(data.invoice?.generated_at) }}
              </div>
              <div class="text-xs mt-1 text-secondary font-semibold" v-if="data.invoice?.generated && data.invoice?.created_by_name">
                Oleh: {{ data.invoice.created_by_name }}
              </div>
            </template>
          </Column>
          <Column header="Periode Sewa" style="min-width: 14rem">
            <template #body="{ data }">
              <div class="font-semibold text-secondary text-xs uppercase tracking-wider">
                <div class="font-semibold">{{ data.kota || '-' }}</div>
              </div>
              <div class="font-semibold text-primary mb-1">{{ data.rent_period?.tujuan || '-' }}</div>
              <div class="text-xs font-mono-numeric">
                {{ formatDate(data.rent_period?.tgl_sewa) }} s/d {{ formatDate(data.rent_period?.tgl_kembali) }}
              </div>
              <div class="text-xs mt-1 font-semibold text-secondary">
                Paket: {{ data.rent_period?.paket_sewa || '-' }}
              </div>
            </template>
          </Column>
          <Column header="Kendaraan" style="min-width: 9rem">
            <template #body="{ data }">
              <div class="font-semibold">{{ data.vehicle?.jenis || '-' }}</div>
              <div class="text-xs text-secondary font-mono-numeric">{{ data.vehicle?.no_polisi || '-' }}</div>
              <div class="text-xs text-tertiary">{{ data.vehicle?.pemilik || '-' }}</div>
            </template>
          </Column>
          <Column header="Total Biaya" style="min-width: 8rem">
            <template #body="{ data }">
              <div class="amount-stack">
                <span>{{ formatCurrency(data.total_biaya?.total) }}</span>
                <span class="text-positive">{{ formatCurrency(data.total_biaya?.sudah_bayar) }}</span>
                <span class="text-info">{{ formatCurrency(data.total_biaya?.sisa) }}</span>
              </div>
            </template>
          </Column>
        </DataTable>
      </div>

      <div v-else class="table-shell">
        <DataTable :value="invoices" dataKey="id" lazy paginator scrollable scrollHeight="flex" :rows="pagination.per_page" :totalRecords="pagination.total" :loading="loading" @page="onPage" responsiveLayout="scroll" class="drent-datatable" :rowClass="rowClass">
          <Column header="Aksi" style="min-width: 15rem">
            <template #body="{ data }">
              <div class="table-actions">
                <button v-if="canRefreshInvoice(data)" class="btn-pill btn-primary btn-pill-compact" :disabled="actionLoading" @click="openRefreshInvoiceDialog(data)">
                  <i class="pi pi-refresh"></i>
                  Update Invoice
                </button>
                <button v-else class="btn-pill btn-primary btn-pill-compact" :disabled="(data.remaining_amount ?? 0) <= 0 || data.status === 'void'" @click="openPaymentDialog(data)">
                  <i class="pi pi-wallet"></i>
                  Bayar Invoice
                </button>
                <span class="action-pill-group">
                  <button class="action-btn" :disabled="actionLoading" title="Kirim invoice" @click="sendInvoice(data.id)">
                    <i class="pi pi-send"></i>
                  </button>
                  <button class="action-btn" :disabled="!getInvoicePublicUrl(data)" title="Lihat invoice" @click="openInvoiceView(data)">
                    <i class="pi pi-eye"></i>
                  </button>
                  <button class="action-btn" :disabled="actionLoading" title="Unduh PDF" @click="openInvoicePdf(data)">
                    <i class="pi pi-file-pdf"></i>
                  </button>
                  <button class="action-btn" title="Lihat history perubahan" @click="openHistoryDialog(data.id, data.invoice_number)">
                    <i class="pi pi-history"></i>
                  </button>
                </span>
                <span v-if="data.sent_at" class="text-xs mt-1 text-secondary">
                  {{ formatDateTime(data.sent_at) }}
                  <span v-if="data.sent_by_name">({{ data.sent_by_name }})</span>
                </span>
              </div>
            </template>
          </Column>
          <Column header="Invoice" style="min-width: 13rem">
            <template #body="{ data }">
              <div class="font-bold">{{ data.invoice_number }}</div>
              <Tag :value="data.status" :severity="invoiceSeverity(data.status)" class="mt-2" />
              <Tag v-if="hasInvoiceChange(data)" :value="invoiceChangeLabel(data)" severity="warn" class="mt-2" />
            </template>
          </Column>
          <Column header="Booking" style="min-width: 16rem">
            <template #body="{ data }">
              <div class="booking-list">
                <button v-for="booking in data.bookings" :key="booking.id" class="link-button text-xs flex" @click="router.push(`/bookings/${booking.id}`)" :title="`Buka detail booking ${booking.kode_booking}`">
                  {{ booking.kode_booking }} - {{ booking.customer_name || '-' }}
                </button>
              </div>
            </template>
          </Column>
          <Column header="Nilai Invoice" style="min-width: 13rem">
            <template #body="{ data }">
              <div class="amount-stack">
                <span>{{ formatCurrency(data.total_amount) }}</span>
                <span class="text-positive">{{ formatCurrency(data.paid_amount) }}</span>
                <span class="text-info">{{ formatCurrency(data.remaining_amount) }}</span>
              </div>
            </template>
          </Column>
          <Column header="Due Date" style="min-width: 10rem">
            <template #body="{ data }">
              <div class="font-semibold">{{ formatDate(data.due_date) }}</div>
              <Tag v-if="getInvoiceDueWarning(data, data.bookings?.[0]?.customer_status)" :value="getInvoiceDueWarning(data, data.bookings?.[0]?.customer_status).label" :severity="getInvoiceDueWarning(data, data.bookings?.[0]?.customer_status).severity" class="mt-1" />
            </template>
          </Column>
          <Column header="Tanggal Buat" style="min-width: 12rem">
            <template #body="{ data }">{{ formatDateTime(data.generated_at) }}</template>
          </Column>
          <Column header="Terakhir Kirim" style="min-width: 12rem">
            <template #body="{ data }">{{ formatDateTime(data.sent_at) }}</template>
          </Column>

        </DataTable>
      </div>
    </div>

    <div v-else class="payment-history-stack">
      <div class="filter-bar surface-card">
        <div class="filter-groups">
          <div class="filter-group">
            <label>Tampilan Riwayat</label>
            <div class="pill-toggle history-filter-toggle">
              <button v-for="option in paymentHistoryViewOptions" :key="option.value" class="toggle-item" :class="{ active: paymentHistoryView === option.value }" @click="switchPaymentHistoryView(option.value)">
                <i :class="option.icon"></i>
                {{ option.label }}
              </button>
            </div>
          </div>
        </div>
        <div class="filter-actions">
          <button class="btn-pill btn-secondary btn-pill-compact" :disabled="historyLoading" @click="refreshPaymentHistory">
            <i class="pi pi-refresh"></i>
            Refresh
          </button>
        </div>
      </div>
      <section v-if="paymentHistoryView === 'latest'" class="payment-section">
        <div class="section-heading">
          <div>
            <h2 class="text-h2">Pembayaran Terakhir</h2>
            <p class="text-secondary text-xs">Gabungan pembayaran invoice dan pembayaran transaksi langsung.</p>
          </div>
        </div>
        <DataTable :value="paymentHistory.latest" dataKey="id" :loading="historyLoading" :paginator="true" :lazy="true" :rows="paymentHistoryPagination.latest.per_page" :totalRecords="paymentHistoryPagination.latest.total" :first="(paymentHistoryPagination.latest.current_page - 1) * paymentHistoryPagination.latest.per_page" paginatorTemplate="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink" currentPageReportTemplate="{first} - {last} dari {totalRecords}" responsiveLayout="scroll" class="drent-datatable" @page="onLatestPaymentHistoryPage">
          <Column header="Sumber" style="min-width: 12rem">
            <template #body="{ data }">
              <div class="font-semibold mt-2">{{ data.reference_number || '-' }}</div>
              <span class="text-xs status-badge" :class="paymentSourceSeverity(data.source)">{{ data.source_label
              }}</span>
            </template>
          </Column>
          <Column header="Nominal" style="min-width: 9rem">
            <template #body="{ data }">
              <div class="amount-stack">{{ formatCurrency(data.amount) }}</div>
              <div class="font-xs mt-1 text-right">{{ data.payment_account_name || '-' }}</div>
            </template>
          </Column>
          <Column header="Tanggal Bayar" style="min-width: 12rem">
            <template #body="{ data }">
              <div class="font-semibold mt-1 text-right">{{ formatDate(data.paid_at) }}</div>
              <div class="font-xs mt-1 text-right">{{ data.created_by_name || '-' }}</div>
            </template>
          </Column>
          <Column header="Pelanggan" style="min-width: 12rem">
            <template #body="{ data }">{{ joinList(data.customer_names) }}</template>
          </Column>
          <Column header="Kode Transaksi" style="min-width: 13rem">
            <template #body="{ data }">{{ joinList(data.transaction_codes) }}</template>
          </Column>
          <Column header="Tanggal Input" style="min-width: 12rem">
            <template #body="{ data }">{{ formatDateTime(data.created_at) }}</template>
          </Column>
          <Column header="Status" style="min-width: 9rem">
            <template #body="{ data }">
              <Tag :value="data.status || 'active'" :severity="paymentStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column header="Aksi" style="min-width: 14rem">
            <template #body="{ data }">
              <div class="table-actions">
                <button v-if="canRequestVoidPayment(data)" class="btn-pill btn-secondary btn-pill-compact"
                  :disabled="actionLoading" @click="openVoidPaymentDialog(data)">
                  <i class="pi pi-undo"></i>
                  Request Void
                </button>
                <button class="btn-pill btn-secondary btn-pill-compact" title="Cetak kwitansi pembayaran"
                  @click="printHistoryPayment(data)">
                  <i class="pi pi-print"></i>
                  Kwitansi
                </button>
              </div>
            </template>
          </Column>

        </DataTable>
      </section>

      <section v-else class="payment-section">
        <div class="section-heading">
          <div>
            <h2 class="text-h2">Group per Kode Transaksi</h2>
            <p class="text-secondary text-xs">Pembayaran transaksi, termasuk alokasi dari invoice, diringkas per kode
              booking.</p>
          </div>
        </div>
        <DataTable v-model:expandedRows="expandedPaymentGroups" :value="paymentHistory.groups" dataKey="booking_id" :loading="historyLoading" :paginator="true" :lazy="true" :rows="paymentHistoryPagination.groups.per_page" :totalRecords="paymentHistoryPagination.groups.total" :first="(paymentHistoryPagination.groups.current_page - 1) * paymentHistoryPagination.groups.per_page" paginatorTemplate="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink" currentPageReportTemplate="{first} - {last} dari {totalRecords}" responsiveLayout="scroll" class="drent-datatable" @page="onGroupPaymentHistoryPage">
          <Column expander style="width: 3rem" />
          <Column header="Kode Transaksi" style="min-width: 12rem">
            <template #body="{ data }">
              <button class="link-button text-xs flex" @click="router.push(`/bookings/${data.booking_id}`)">
                {{ data.kode_booking || '-' }}
              </button>
              <div class="text-xs text-secondary mt-1">{{ data.payment_count }} pembayaran</div>
            </template>
          </Column>
          <Column header="Pelanggan" style="min-width: 12rem">
            <template #body="{ data }">{{ data.customer_name || '-' }}</template>
          </Column>
          <Column header="Terakhir Bayar" style="min-width: 12rem">
            <template #body="{ data }">{{ formatDateTime(data.latest_paid_at) }}</template>
          </Column>
          <Column header="Total Terbayar" style="min-width: 12rem">
            <template #body="{ data }">
              <div class="amount-stack">{{ formatCurrency(data.total_amount) }}</div>
            </template>
          </Column>
          <template #expansion="{ data }">
            <DataTable :value="data.payments" dataKey="id" responsiveLayout="scroll" class="nested-datatable">
              <Column header="Jenis" style="min-width: 12rem">
                <template #body="{ data: payment }">
                  <Tag :value="payment.source_label" :severity="paymentSourceSeverity(payment.source)" />
                  <div class="text-xs text-secondary mt-2">{{ payment.payment_type || '-' }}</div>
                </template>
              </Column>
              <Column header="Referensi" style="min-width: 12rem">
                <template #body="{ data: payment }">{{ payment.reference_number || '-' }}</template>
              </Column>
              <Column header="Akun" style="min-width: 12rem">
                <template #body="{ data: payment }">{{ payment.payment_account_name || '-' }}</template>
              </Column>
              <Column header="Diinput Oleh" style="min-width: 12rem">
                <template #body="{ data: payment }">{{ payment.created_by_name || '-' }}</template>
              </Column>
              <Column header="Status" style="min-width: 9rem">
                <template #body="{ data: payment }">
                  <Tag :value="payment.status" :severity="paymentStatusSeverity(payment.status)" />
                </template>
              </Column>
              <Column header="Tanggal" style="min-width: 12rem">
                <template #body="{ data: payment }">{{ formatDateTime(payment.paid_at) }}</template>
              </Column>
              <Column header="Tanggal Input" style="min-width: 12rem">
                <template #body="{ data: payment }">{{ formatDateTime(payment.created_at) }}</template>
              </Column>
              <Column header="Nominal" style="min-width: 11rem">
                <template #body="{ data: payment }">
                  <div class="amount-stack">{{ formatCurrency(payment.amount) }}</div>
                </template>
              </Column>
              <Column header="Catatan" style="min-width: 14rem">
                <template #body="{ data: payment }">{{ payment.note || '-' }}</template>
              </Column>
              <Column header="Aksi" style="min-width: 11rem">
                <template #body="{ data: payment }">
                  <button v-if="canRequestVoidPayment(payment)" class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="openVoidPaymentDialog(payment)">
                    <i class="pi pi-undo"></i>
                    Request Void
                  </button>
                  <span v-else class="text-xs text-secondary">-</span>
                </template>
              </Column>
            </DataTable>
          </template>
        </DataTable>
      </section>
    </div>

    <Dialog v-model:visible="showGenerateDialog" header="Buat Invoice" modal :style="{ width: 'min(640px, 96vw)' }" class="custom-dialog">
      <div class="dialog-stack">
        <div v-if="isCombinedInvoice" class="warning-panel">
          <i class="pi pi-exclamation-triangle"></i>
          <span>Beberapa booking terpilih akan digabungkan menjadi satu invoice.</span>
        </div>
        <div class="app-muted-panel">
          <div class="summary-row">
            <span>Jumlah booking</span>
            <strong>{{ selectedReceivableRows.length }}</strong>
          </div>
          <div class="summary-row">
            <span>Booking</span>
            <strong>{{ selectedBookingCodes || '-' }}</strong>
          </div>
          <div class="summary-row">
            <span>Total invoice</span>
            <strong>{{ formatCurrency(selectedTotal) }}</strong>
          </div>
        </div>
        <fieldset class="form-fieldset">
          <label>Due Date Invoice</label>
          <DatePicker v-model="generateForm.due_date" dateFormat="dd M yy" class="w-full" showIcon />
          <span class="field-hint">Otomatis dari ketentuan pelanggan. Untuk invoice gabungan, sistem memakai due date
            paling
            akhir dari booking terpilih.</span>
        </fieldset>

        <fieldset class="form-fieldset">
          <label>Syarat &amp; Ketentuan</label>
          <Dropdown v-model="selectedTemplateId" :options="termsTemplateOptions" optionLabel="label" optionValue="value" placeholder="Pilih template..." class="w-full" style="margin-bottom: 8px" :loading="loadingTemplates" @update:modelValue="onTemplateSelect" />
          <InvoiceTermsEditor v-model="generateForm.terms_and_conditions" placeholder="Tulis syarat & ketentuan invoice di sini..." />
          <span class="field-hint">Opsional. Pilih template lalu edit sesuai kebutuhan, atau tulis langsung.</span>
        </fieldset>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showGenerateDialog = false">
          <i class="pi pi-times"></i>
          Batal
        </button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading" @click="submitGenerateInvoice">
          <i class="pi pi-check"></i>
          Buat Invoice
        </button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showPaymentDialog" header="Pembayaran Invoice" modal :style="{ width: 'min(1180px, 96vw)' }" class="custom-dialog payment-invoice-dialog">
      <div class="payment-invoice-modal" v-if="selectedInvoice">
        <section class="payment-invoice-preview">
          <div class="payment-invoice-top">
            <div>
              <div class="payment-invoice-kicker">INVOICE</div>
              <h2>{{ selectedInvoice.invoice_number || selectedInvoice.number }}</h2>
              <p>{{ selectedInvoiceCustomerNames }}</p>
            </div>
            <Tag :value="selectedInvoice.status" :severity="invoiceSeverity(selectedInvoice.status)" />
          </div>

          <div class="payment-invoice-meta">
            <div>
              <span>Tanggal Invoice</span>
              <strong>{{ formatDate(selectedInvoice.generated_at) }}</strong>
            </div>
            <div>
              <span>Due Date</span>
              <strong>{{ formatDate(selectedInvoice.due_date) }}</strong>
            </div>
            <div>
              <span>Terakhir Kirim</span>
              <strong>{{ formatDateTime(selectedInvoice.sent_at) }}</strong>
            </div>
          </div>

          <div class="payment-invoice-table">
            <div class="payment-invoice-table-header">
              <span>No.</span>
              <span>Item Description</span>
              <span>Price</span>
              <span>Qty</span>
              <span>Total</span>
            </div>
            <div v-for="(item, index) in selectedInvoiceItems" :key="`${item.type || 'item'}-${item.booking_code || index}-${index}`" class="payment-invoice-table-row">
              <span>{{ index + 1 }}</span>
              <div>
                <strong>{{ item.description || item.booking_code || 'Rental Service' }}</strong>
                <small v-if="item.booking_code && item.description !== item.booking_code">{{ item.booking_code
                }}</small>
                <small v-if="item.label">{{ item.label }}<template v-if="item.note">: {{ item.note }}</template></small>
                <small v-if="item.vehicle_name || item.vehicle_plate">
                  {{ item.vehicle_name || 'Rental Service' }} <span v-if="item.vehicle_plate" class="font-mono-numeric">({{
                    item.vehicle_plate }})</span>
                </small>
                <small v-if="item.rental_start_date || item.rental_end_date">
                  {{ formatDate(item.rental_start_date) }} - {{ formatDate(item.rental_end_date) }}
                </small>
              </div>
              <span>{{ formatCurrency(item.price ?? item.amount) }}</span>
              <span>{{ item.qty || 1 }}</span>
              <strong>{{ formatCurrency(item.amount) }}</strong>
            </div>
            <div v-if="!selectedInvoiceItems.length" class="payment-invoice-empty">Belum ada detail item invoice.</div>
          </div>
        </section>

        <aside class="payment-invoice-side">
          <div class="payment-total-panel">
            <div class="payment-total-row">
              <span>Sub Total</span>
              <strong>{{ formatCurrency(selectedInvoice.total_amount) }}</strong>
            </div>
            <div class="payment-total-row">
              <span>Paid</span>
              <strong class="text-positive">{{ formatCurrency(selectedInvoice.paid_amount) }}</strong>
            </div>
            <div class="payment-total-row grand">
              <span>Remaining</span>
              <strong>{{ formatCurrency(selectedInvoiceRemaining) }}</strong>
            </div>
          </div>

          <div class="payment-history-panel">
            <div class="section-label">Riwayat Pembayaran</div>
            <template v-if="selectedInvoice.payments?.length">
              <div class="payment-history-row" v-for="payment in selectedInvoice.payments" :key="payment.id || `${payment.paid_at}-${payment.amount}`">
                <div>
                  <strong>{{ formatDate(payment.paid_at) }}</strong>
                  <span>{{ payment.payment_account_name || '-' }}</span>
                  <small v-if="payment.source === 'booking'">Pembayaran transaksi</small>
                </div>
                <strong>{{ formatCurrency(payment.amount) }}</strong>
              </div>
            </template>
            <div v-else class="payment-invoice-empty">Belum ada pembayaran.</div>
          </div>

          <div class="payment-form-panel">
            <div class="section-label">Catat Pembayaran</div>
            <fieldset class="form-fieldset">
              <label>Akun Pembayaran</label>
              <Dropdown v-model="paymentForm.payment_account_id" :options="paymentAccountOptions" optionLabel="label" optionValue="value" placeholder="Pilih akun" class="w-full" />
            </fieldset>
            <fieldset class="form-fieldset">
              <label>Nominal</label>
              <InputNumber v-model="paymentForm.amount" mode="currency" currency="IDR" locale="id-ID" :min="1" :max="selectedInvoiceRemaining" class="w-full" />
            </fieldset>
            <fieldset class="form-fieldset">
              <label>Tanggal Bayar</label>
              <DatePicker v-model="paymentForm.paid_at" dateFormat="dd M yy" class="w-full" showIcon />
            </fieldset>
          </div>
        </aside>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showPaymentDialog = false">
          <i class="pi pi-times"></i>
          Batal
        </button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="isPaymentSubmitDisabled" @click="submitInvoicePayment">
          <i class="pi pi-check"></i>
          Simpan Pembayaran
        </button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showVoidPaymentDialog" header="Request Void Pembayaran" modal :style="{ width: '460px' }" class="custom-dialog">
      <div class="dialog-stack">
        <div class="app-muted-panel">
          <div class="summary-row"><span>Referensi</span><strong>{{ selectedVoidPayment?.reference_number || '-'
              }}</strong>
          </div>
          <div class="summary-row"><span>Kode transaksi</span><strong>{{
            joinList(selectedVoidPayment?.transaction_codes)
              }}</strong></div>
          <div class="summary-row"><span>Nominal</span><strong>{{ formatCurrency(selectedVoidPayment?.amount)
              }}</strong>
          </div>
        </div>
        <fieldset class="form-fieldset">
          <label>Alasan void</label>
          <Textarea v-model="voidPaymentForm.void_reason" rows="4" class="w-full" placeholder="Tuliskan alasan untuk ACC supervisor" />
          <span v-if="voidPaymentFormErrors.void_reason?.length" class="field-error">{{
            voidPaymentFormErrors.void_reason[0]
          }}</span>
          <span v-else class="field-hint">Request ini akan masuk ke supervisor untuk approval.</span>
        </fieldset>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showVoidPaymentDialog = false">
          <i class="pi pi-times"></i>
          Batal
        </button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || !selectedVoidPaymentId || !voidPaymentForm.void_reason || voidPaymentForm.void_reason.trim().length < 5" @click="submitVoidPaymentRequest">
          <i class="pi pi-send"></i>
          Kirim Request
        </button>
      </template>
    </Dialog>
    <Dialog v-model:visible="showHistoryDialog" :header="`History — ${historyDialogTitle}`" modal :style="{ width: 'min(560px, 96vw)' }" class="custom-dialog">
      <div v-if="historyLoading2" class="loading-strip">
        <ProgressBar mode="indeterminate" style="height: 4px" />
      </div>
      <div v-else-if="!invoiceHistories.length" class="payment-invoice-empty">Belum ada riwayat untuk invoice ini.
      </div>
      <div v-else class="invoice-history-timeline">
        <div v-for="entry in invoiceHistories" :key="entry.id" class="history-entry">
          <div class="history-dot-col">
            <span class="history-dot" :class="`history-dot-${historyEventSeverity(entry.event_type)}`"></span>
            <span class="history-line"></span>
          </div>
          <div class="history-content">
            <div class="history-header">
              <Tag :value="historyEventLabel(entry.event_type)" :severity="historyEventSeverity(entry.event_type)" class="history-tag" />
              <span class="history-time text-xs text-secondary">{{ formatDateTime(entry.created_at) }}</span>
            </div>
            <div v-if="entry.actor_name" class="text-xs text-secondary mt-1">{{ entry.actor_name }}</div>
            <div v-if="entry.event_type === 'amended'" class="history-amount-change">
              <span>{{ formatCurrency(entry.amount_before) }}</span>
              <i class="pi pi-arrow-right text-xs"></i>
              <span class="font-semibold">{{ formatCurrency(entry.amount_after) }}</span>
            </div>
            <div v-else-if="entry.event_type === 'created' && entry.amount_after" class="text-xs mt-1">
              Nominal: <strong>{{ formatCurrency(entry.amount_after) }}</strong>
            </div>
            <div v-else-if="entry.event_type === 'payment_received'" class="text-xs mt-1 text-positive font-semibold">
              +{{ formatCurrency(entry.payment_amount) }}
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showHistoryDialog = false">
          <i class="pi pi-times"></i>
          Tutup
        </button>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.table-actions {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  flex-wrap: wrap;
}

.history-filter-toggle {
  flex-wrap: wrap;
}

.filter-group-search {
  min-width: min(360px, 100%);
}

.filter-group-search :deep(.p-inputtext) {
  width: 100%;
}

.app-muted-panel {
  display: flex;
  flex-direction: column;
  gap: 8px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--card-bg);
  padding: var(--space-md);
}

.warning-panel {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  border: 1px solid rgba(245, 158, 11, 0.35);
  border-radius: var(--radius-default);
  background: rgba(245, 158, 11, 0.1);
  color: #92400e;
  padding: var(--space-md);
  font-size: 12px;
  font-weight: 700;
  line-height: 1.4;
}

.field-error {
  color: var(--negative);
  font-size: 11px;
  font-weight: 700;
}

.payment-history-stack,
.payment-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.payment-history-stack {
  gap: var(--space-2xl);
}

.section-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-lg);
}

.form-fieldset {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-fieldset {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--card-bg);
  padding: var(--space-md);
}

.form-fieldset label {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

.field-hint {
  color: var(--text-tertiary);
  font-size: 11px;
  line-height: 1.4;
}

:deep(.drent-datatable .p-datatable-thead > tr > th) {
  text-align: center;
}

:deep(.drent-datatable .p-datatable-thead > tr > th .p-column-header-content) {
  justify-content: center;
}

.nested-datatable {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  overflow: hidden;
  background: var(--surface-default);
}

.link-button {
  border: none;
  background: transparent;
  color: var(--text-primary);
  cursor: pointer;
  font-weight: 700;
  padding: 0;
}

.amount-stack,
.invoice-cell,
.dialog-stack,
.booking-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.amount-stack {
  align-items: flex-end;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-md);
}

.payment-invoice-modal {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: var(--space-lg);
  max-height: min(74vh, 760px);
  overflow: hidden;
}

.payment-invoice-preview,
.payment-invoice-side {
  min-width: 0;
  overflow: auto;
}

.payment-invoice-preview {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
}

.payment-invoice-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-lg);
  padding: 18px 20px;
  border-bottom: 3px solid #E5534B;
}

.payment-invoice-kicker {
  color: #E5534B;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0;
}

.payment-invoice-top h2 {
  margin: 2px 0;
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 22px;
}

.payment-invoice-top p {
  margin: 0;
  color: var(--text-secondary);
  font-size: 12px;
}

.payment-invoice-meta {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1px;
  background: var(--surface-border);
}

.payment-invoice-meta>div {
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: var(--card-bg);
  padding: 12px 16px;
}

.payment-invoice-meta span,
.payment-invoice-table-header,
.payment-history-row span {
  color: var(--text-secondary);
  font-size: 11px;
}

.payment-invoice-meta strong {
  color: var(--text-primary);
  font-size: 12px;
}

.payment-invoice-table {
  padding: 16px;
}

.payment-invoice-table-header,
.payment-invoice-table-row {
  display: grid;
  grid-template-columns: 44px minmax(260px, 1fr) 130px 64px 130px;
  gap: 12px;
  align-items: center;
}

.payment-invoice-table-header {
  padding: 10px 12px;
  border-radius: var(--radius-xs);
  background: var(--text-primary);
  color: #fff;
  font-weight: 800;
  text-transform: uppercase;
}

.payment-invoice-table-header span {
  color: #fff;
}

.payment-invoice-table-row {
  padding: 12px;
  border-bottom: 1px solid var(--surface-border);
  color: var(--text-primary);
  font-size: 12px;
}

.payment-invoice-table-row>span,
.payment-invoice-table-row>strong {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.payment-invoice-table-row>span:first-child {
  text-align: center;
}

.payment-invoice-table-row small {
  display: block;
  margin-top: 3px;
  color: var(--text-secondary);
  font-size: 11px;
  line-height: 1.35;
}

.payment-invoice-side {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.payment-total-panel,
.payment-history-panel,
.payment-form-panel {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  padding: var(--space-md);
}

.payment-total-row,
.payment-history-row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-md);
  padding: 8px 0;
}

.payment-total-row {
  align-items: center;
  border-bottom: 1px solid var(--surface-border);
}

.payment-total-row.grand {
  margin-top: 6px;
  border-bottom: 0;
  color: #fff;
  background: #E5534B;
  border-radius: var(--radius-xs);
  padding: 12px;
}

.payment-total-row span,
.payment-total-row strong,
.payment-history-row strong {
  font-size: 12px;
}

.payment-history-row {
  align-items: flex-start;
  border-bottom: 1px solid var(--surface-border);
}

.payment-history-row:last-child,
.payment-total-row:last-child {
  border-bottom: 0;
}

.payment-history-row>strong {
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}

.payment-history-row small {
  display: block;
  margin-top: 3px;
  color: var(--text-tertiary);
  font-size: 11px;
}

.payment-invoice-empty {
  padding: 14px 0;
  color: var(--text-secondary);
  font-size: 12px;
  text-align: center;
}

.loading-strip {
  margin-bottom: var(--space-md);
}

:deep(.custom-dialog .p-dialog-footer) {
  border-top: 1px solid var(--surface-border);
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

@media (max-width: 768px) {
  .section-heading {
    flex-direction: column;
    align-items: stretch;
  }

  .section-heading .btn-pill {
    width: 100%;
    justify-content: center;
  }

  .history-filter-toggle,
  .history-filter-toggle .toggle-item {
    width: 100%;
  }

  .history-filter-toggle .toggle-item {
    justify-content: center;
  }

  .payment-invoice-modal {
    grid-template-columns: 1fr;
    max-height: 78vh;
    overflow: auto;
  }

  .payment-invoice-preview,
  .payment-invoice-side {
    overflow: visible;
  }

  .payment-invoice-meta,
  .payment-invoice-table-header,
  .payment-invoice-table-row {
    grid-template-columns: 1fr;
  }

  .payment-invoice-table-header {
    display: none;
  }

  .payment-invoice-table-row {
    gap: 6px;
  }

  .payment-invoice-table-row>span,
  .payment-invoice-table-row>strong {
    text-align: left;
  }
}

:deep(.p-datatable-tbody > tr.row-due-warning > td) {
  background-color: #FDF4D9 !important;
  color: #8C660A !important;
}

:deep(.p-datatable-tbody > tr.row-due-warning:hover > td) {
  background-color: #fcf1ce !important;
}

:deep(.p-datatable-tbody > tr.row-due-overdue > td) {
  background-color: #FCEAE9 !important;
  color: #B02A24 !important;
}

:deep(.p-datatable-tbody > tr.row-due-overdue:hover > td) {
  background-color: #fbe0de !important;
}

/* Ensure link buttons inside highlighted rows have high contrast */
:deep(.row-due-warning .link-button),
:deep(.row-due-warning .text-secondary),
:deep(.row-due-warning .font-mono-numeric) {
  color: #8C660A !important;
}

:deep(.row-due-overdue .link-button),
:deep(.row-due-overdue .text-secondary),
:deep(.row-due-overdue .font-mono-numeric) {
  color: #B02A24 !important;
}

.invoice-history-timeline {
  display: flex;
  flex-direction: column;
}

.history-entry {
  display: flex;
  gap: 12px;
  position: relative;
}

.history-entry:last-child .history-line {
  display: none;
}

.history-dot-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
  flex-shrink: 0;
  width: 16px;
  padding-top: 4px;
}

.history-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

.history-dot-info {
  background: #3B82F6;
}

.history-dot-secondary {
  background: #6B7280;
}

.history-dot-warn {
  background: #F59E0B;
}

.history-dot-success {
  background: #10B981;
}

.history-dot-danger {
  background: #EF4444;
}

.history-line {
  flex: 1;
  width: 2px;
  background: var(--surface-border);
  min-height: 20px;
  margin-top: 4px;
}

.history-content {
  padding-bottom: 20px;
  flex: 1;
  min-width: 0;
}

.history-header {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.history-tag {
  font-size: 11px;
}

.history-time {
  margin-left: auto;
  white-space: nowrap;
}

.history-amount-change {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  margin-top: 4px;
  font-variant-numeric: tabular-nums;
}
</style>
