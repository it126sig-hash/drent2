<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { format } from 'date-fns'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import ConfirmDialog from 'primevue/confirmdialog'
import { usePaymentAccount } from '../../composables/usePaymentAccount'
import { useReceivable } from '../../composables/useReceivable'
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue'

const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const {
  receivables,
  invoices,
  paymentHistory,
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

const generateForm = ref({
  due_date: null,
})
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
    .map(row => row.due_date ? new Date(row.due_date) : null)
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

const invoiceChange = (invoice) => invoice?.invoice_reconciliation || null

const hasInvoiceChange = (invoice) => Boolean(invoiceChange(invoice)?.is_changed)

const canRefreshInvoice = (invoice) => hasInvoiceChange(invoice) && ['generated', 'partial_paid'].includes(invoice?.status)

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

  fetchPaymentHistory()
}

const switchPaymentHistoryView = (view) => {
  paymentHistoryView.value = view
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
  showGenerateDialog.value = true
}

const submitGenerateInvoice = async () => {
  if (!selectedReceivableRows.value.length) return

  await generate({
    booking_ids: selectedReceivableRows.value.map(row => row.id),
    due_date: toApiDate(generateForm.value.due_date),
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
    submitRefreshInvoice(invoice).catch(() => {})
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
    bookings: [{
      id: row.id,
      kode_booking: row.kode_booking,
      customer_name: row.customer?.nama,
      amount: row.invoice.total_amount ?? row.total_biaya?.sisa ?? 0,
    }],
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
    }],
    payments: [],
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

onMounted(async () => {
  await Promise.all([
    fetchAll(),
    fetchPaymentHistory(),
    fetchPaymentAccounts({ per_page: 100 }),
  ])
})
</script>

<template>
  <div class="page-container">
    <ConfirmDialog />
    <div class="detail-page-header">
      <div class="header-left">
        <h1 class="text-h1">Piutang & Invoice</h1>
        <p class="text-secondary text-xs">Kelola piutang, invoice yang sudah dibuat, dan pembayaran invoice.</p>
      </div>
      <div class="header-actions" v-if="activeTab === 'receivables'">
        <button
          class="btn-pill btn-primary"
          :disabled="selectedRows.length === 0 || actionLoading"
          @click="openGenerateDialog()"
        >
          <i class="pi pi-file-plus"></i>
          {{ selectedRows.length > 1 ? 'Buat Invoice Gabungan' : 'Buat Invoice' }}
        </button>
      </div>
    </div>

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

    <div v-if="activeTab !== 'payments'" class="app-card filter-bar">
      <div class="filter-group" v-if="activeTab === 'receivables'">
        <label>Status Invoice</label>
        <Dropdown
          v-model="filters.invoice_status"
          :options="invoiceStatusOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Semua"
          class="w-full md:w-48"
        />
      </div>
      <div class="filter-group" v-else>
        <label>Status</label>
        <Dropdown
          v-model="invoiceFilters.status"
          :options="generatedInvoiceStatusOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Semua"
          class="w-full md:w-48"
        />
      </div>
      <button class="btn-pill btn-secondary btn-pill-compact" :disabled="loading" @click="applyFilters">
        <i class="pi pi-filter"></i>
        Filter
      </button>
    </div>

    <div v-else class="app-card filter-bar">
      <div class="filter-group">
        <label>Tampilan Riwayat</label>
        <div class="pill-toggle history-filter-toggle">
          <button
            v-for="option in paymentHistoryViewOptions"
            :key="option.value"
            class="toggle-item"
            :class="{ active: paymentHistoryView === option.value }"
            @click="switchPaymentHistoryView(option.value)"
          >
            <i :class="option.icon"></i>
            {{ option.label }}
          </button>
        </div>
      </div>
      <button class="btn-pill btn-secondary btn-pill-compact" :disabled="historyLoading" @click="fetchPaymentHistory">
        <i class="pi pi-refresh"></i>
        Refresh
      </button>
    </div>

    <div v-if="loading" class="loading-strip">
      <ProgressBar mode="indeterminate" style="height: 4px" />
    </div>

    <DataTable
      v-if="activeTab === 'receivables'"
      v-model:selection="selectedRows"
      :value="receivables"
      dataKey="id"
      lazy
      paginator
      :rows="pagination.per_page"
      :totalRecords="pagination.total"
      :loading="loading"
      @page="onPage"
      responsiveLayout="scroll"
      class="drent-datatable"
    >
      <Column selectionMode="multiple" headerStyle="width: 3rem" />
       <Column header="Aksi" style="min-width: 18rem">
        <template #body="{ data }">
          <div class="table-actions">
            <button
              v-if="data.invoice?.generated && canRefreshInvoice(data.invoice)"
              class="btn-pill btn-primary btn-pill-compact"
              :disabled="actionLoading"
              @click="openRefreshInvoiceDialog(data.invoice)"
            >
              <i class="pi pi-refresh"></i>
              Update Invoice
            </button>
            <button
              v-else-if="data.invoice?.generated"
              class="btn-pill btn-primary btn-pill-compact"
              :disabled="(data.invoice?.remaining_amount ?? 0) <= 0"
              @click="openPaymentFromReceivable(data)"
            >
              <i class="pi pi-wallet"></i>
              Bayar Invoice
            </button>
            <button
              v-else
              class="btn-pill btn-primary btn-pill-compact"
              :disabled="actionLoading"
              @click="openGenerateDialog(data)"
            >
              <i class="pi pi-file-plus"></i>
              Buat Invoice
            </button>
            <button
              v-if="data.invoice?.generated"
              class="btn-pill btn-secondary btn-pill-compact"
              :disabled="actionLoading"
              @click="sendInvoice(data.invoice.id)"
            >
              <i class="pi pi-send"></i>
            </button>
            <button
              v-if="data.invoice?.generated"
              class="btn-pill btn-secondary btn-pill-compact"
              :disabled="!getInvoicePublicUrl(data.invoice)"
              @click="openInvoiceView(data.invoice)"
            >
              <i class="pi pi-eye"></i>
            </button>
            <!-- <button
              v-if="data.invoice?.generated"
              class="btn-pill btn-secondary btn-pill-compact"
              :disabled="!data.invoice?.generated || actionLoading"
              @click="openInvoicePdf(data.invoice)"
            >
              <i class="pi pi-file-pdf"></i>
              PDF
            </button> -->
          </div>
        </template>
      </Column>
      <Column header="Booking" style="min-width: 10rem">
        <template #body="{ data }">
          <button class="link-button text-xs flex" @click="router.push(`/bookings/${data.id}`)">{{ data.kode_booking }}</button>
          <BookingStatusBadge :status="data.invoice?.number ? 'generated' : 'not_generated'" :text="data.invoice?.number || 'Belum Buat Invoice'" /> 
          <Tag v-if="hasInvoiceChange(data.invoice)" :value="invoiceChangeLabel(data.invoice)" severity="warn" class="mt-2" />
        </template>
      </Column>
      <Column header="Tanggal Invoice" style="min-width: 12rem">
        <template #body="{ data }">
          <div class="font-semibold">{{formatDateTime(data.invoice?.generated_at)}}</div>
          <div class="text-xs text-tertiary mt-1 text-italic">{{data.invoice?.generated ? 'Due ' + formatDate(data.invoice?.due_date || data.due_date) : ''}}</div>
        </template>
      </Column>
      <Column header="Terakhir Kirim" style="min-width: 12rem">
        <template #body="{ data }">
          <span>{{ formatDateTime(data.invoice?.sent_at) }}</span>
        </template>
      </Column>
      <Column header="Pelanggan" style="min-width: 12rem">
        <template #body="{ data }">
          <div class="font-semibold">{{ data.customer?.nama || '-' }}</div>
          <div class="text-xs text-secondary">{{ data.customer?.status || '-' }}</div>
        </template>
      </Column>
      <Column header="Kendaraan" style="min-width: 14rem">
        <template #body="{ data }">
          <div class="font-semibold">{{ data.vehicle?.jenis || '-' }}</div>
          <div class="text-xs text-secondary font-mono-numeric">{{ data.vehicle?.no_polisi || '-' }}</div>
          <div class="text-xs text-tertiary">{{ data.vehicle?.pemilik || '-' }}</div>
        </template>
      </Column>
      <Column header="Total Biaya" style="min-width: 13rem">
        <template #body="{ data }">
          <div class="amount-stack">
            <span>{{ formatCurrency(data.total_biaya?.total) }}</span>
            <span class="text-positive">{{ formatCurrency(data.total_biaya?.sudah_bayar) }}</span>
            <span class="text-info">{{ formatCurrency(data.total_biaya?.sisa) }}</span>
          </div>
        </template>
      </Column>
      
      
     
    </DataTable>

    <DataTable
      v-else-if="activeTab === 'invoices'"
      :value="invoices"
      dataKey="id"
      lazy
      paginator
      :rows="pagination.per_page"
      :totalRecords="pagination.total"
      :loading="loading"
      @page="onPage"
      responsiveLayout="scroll"
      class="drent-datatable"
    >
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
            <span v-for="booking in data.bookings" :key="booking.id">
              {{ booking.kode_booking }} - {{ booking.customer_name || '-' }}
            </span>
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
        <template #body="{ data }">{{ formatDate(data.due_date) }}</template>
      </Column>
      <Column header="Tanggal Buat" style="min-width: 12rem">
        <template #body="{ data }">{{ formatDateTime(data.generated_at) }}</template>
      </Column>
      <Column header="Terakhir Kirim" style="min-width: 12rem">
        <template #body="{ data }">{{ formatDateTime(data.sent_at) }}</template>
      </Column>
      <Column header="Aksi" style="min-width: 15rem">
        <template #body="{ data }">
          <div class="table-actions">
            <button
              v-if="canRefreshInvoice(data)"
              class="btn-pill btn-primary btn-pill-compact"
              :disabled="actionLoading"
              @click="openRefreshInvoiceDialog(data)"
            >
              <i class="pi pi-refresh"></i>
              Update
            </button>
            <button
              v-else
              class="btn-pill btn-primary btn-pill-compact"
              :disabled="data.remaining_amount <= 0 || data.status === 'void'"
              @click="openPaymentDialog(data)"
            >
              <i class="pi pi-wallet"></i>
              Bayar
            </button>
            <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="sendInvoice(data.id)">
              <i class="pi pi-send"></i>
              Kirim
            </button>
            <button
              class="btn-pill btn-secondary btn-pill-compact"
              :disabled="!getInvoicePublicUrl(data)"
              @click="openInvoiceView(data)"
            >
              <i class="pi pi-eye"></i>
              View
            </button>
            <!-- <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="openInvoicePdf(data)">
              <i class="pi pi-file-pdf"></i>
              PDF
            </button> -->
          </div>
        </template>
      </Column>
    </DataTable>

    <div v-else class="payment-history-stack">
      <section v-if="paymentHistoryView === 'latest'" class="payment-section">
        <div class="section-heading">
          <div>
            <h2 class="text-h2">Pembayaran Terakhir</h2>
            <p class="text-secondary text-xs">Gabungan pembayaran invoice dan pembayaran transaksi langsung.</p>
          </div>
        </div>
        <DataTable
          :value="paymentHistory.latest"
          dataKey="id"
          :loading="historyLoading"
          responsiveLayout="scroll"
          class="drent-datatable"
        >
          <Column header="Sumber" style="min-width: 12rem">
            <template #body="{ data }">
              <div class="font-semibold mt-2">{{ data.reference_number || '-' }}</div>
              <span class="text-xs status-badge" :class="paymentSourceSeverity(data.source)">{{ data.source_label }}</span>
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
                <div class="font-semibold mt-1 text-right">{{ formatDateTime(data.paid_at) }}</div>
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

        </DataTable>
      </section>

      <section v-else class="payment-section">
        <div class="section-heading">
          <div>
            <h2 class="text-h2">Group per Kode Transaksi</h2>
            <p class="text-secondary text-xs">Pembayaran transaksi, termasuk alokasi dari invoice, diringkas per kode booking.</p>
          </div>
        </div>
        <DataTable
          v-model:expandedRows="expandedPaymentGroups"
          :value="paymentHistory.groups"
          dataKey="booking_id"
          :loading="historyLoading"
          responsiveLayout="scroll"
          class="drent-datatable"
        >
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
            <DataTable
              :value="data.payments"
              dataKey="id"
              responsiveLayout="scroll"
              class="nested-datatable"
            >
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
            </DataTable>
          </template>
        </DataTable>
      </section>
    </div>

    <Dialog v-model:visible="showGenerateDialog" header="Buat Invoice" modal :style="{ width: '460px' }" class="custom-dialog">
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
          <span class="field-hint">Otomatis dari ketentuan pelanggan. Untuk invoice gabungan, sistem memakai due date paling akhir dari booking terpilih.</span>
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
            <div
              v-for="(item, index) in selectedInvoiceItems"
              :key="`${item.type || 'item'}-${item.booking_code || index}-${index}`"
              class="payment-invoice-table-row"
            >
              <span>{{ index + 1 }}</span>
              <div>
                <strong>{{ item.description || item.booking_code || 'Rental Service' }}</strong>
                <small v-if="item.booking_code && item.description !== item.booking_code">{{ item.booking_code }}</small>
                <small v-if="item.label">{{ item.label }}<template v-if="item.note">: {{ item.note }}</template></small>
                <small v-if="item.vehicle_name || item.vehicle_plate">
                  {{ item.vehicle_name || 'Rental Service' }} <span v-if="item.vehicle_plate" class="font-mono-numeric">({{ item.vehicle_plate }})</span>
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

          <!-- <div class="payment-history-panel">
            <div class="section-label">History Payment</div>
            <template v-if="selectedInvoice.payments?.length">
              <div class="payment-history-row" v-for="payment in selectedInvoice.payments" :key="payment.id || `${payment.paid_at}-${payment.amount}`">
                <div>
                  <strong>{{ formatDate(payment.paid_at) }}</strong>
                  <span>{{ payment.payment_account_name || '-' }}</span>
                </div>
                <strong>{{ formatCurrency(payment.amount) }}</strong>
              </div>
            </template>
            <div v-else class="payment-invoice-empty">Belum ada pembayaran.</div>
          </div> -->

          <div class="payment-form-panel">
            <div class="section-label">Catat Pembayaran</div>
            <fieldset class="form-fieldset">
              <label>Akun Pembayaran</label>
              <Dropdown
                v-model="paymentForm.payment_account_id"
                :options="paymentAccountOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Pilih akun"
                class="w-full"
              />
            </fieldset>
            <fieldset class="form-fieldset">
              <label>Nominal</label>
              <InputNumber
                v-model="paymentForm.amount"
                mode="currency"
                currency="IDR"
                locale="id-ID"
                :min="1"
                :max="selectedInvoiceRemaining"
                class="w-full"
              />
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
        <button
          class="app-dialog-button app-dialog-button-primary"
          :disabled="isPaymentSubmitDisabled"
          @click="submitInvoicePayment"
        >
          <i class="pi pi-check"></i>
          Simpan Pembayaran
        </button>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page-container {
  padding: var(--space-2xl);
}

.detail-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-lg);
  margin-bottom: var(--space-2xl);
}

.header-actions,
.table-actions {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  flex-wrap: wrap;
}

.tab-toggle-container {
  margin-bottom: var(--space-xl);
}

.pill-toggle {
  display: inline-flex;
  background: var(--card-bg);
  padding: 4px;
  border-radius: var(--radius-full);
  gap: 4px;
}

.history-filter-toggle {
  flex-wrap: wrap;
}

.toggle-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 16px;
  border-radius: var(--radius-full);
  border: none;
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
}

.toggle-item.active {
  background: var(--text-primary);
  color: #fff;
}

.app-card {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
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

.filter-bar {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-lg);
  padding: var(--space-md);
  margin-bottom: var(--space-lg);
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

.filter-group,
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

.filter-group label,
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

.drent-datatable {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  overflow: hidden;
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
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

.payment-invoice-meta > div {
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

.payment-invoice-table-row > span,
.payment-invoice-table-row > strong {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.payment-invoice-table-row > span:first-child {
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

.payment-history-row > strong {
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
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
  .page-container {
    padding: var(--space-lg);
  }

  .detail-page-header,
  .filter-bar,
  .section-heading {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions .btn-pill,
  .filter-bar .btn-pill,
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

  .payment-invoice-table-row > span,
  .payment-invoice-table-row > strong {
    text-align: left;
  }
}
</style>
