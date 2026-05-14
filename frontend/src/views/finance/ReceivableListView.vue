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
  loading,
  actionLoading,
  pagination,
  filters,
  invoiceFilters,
  fetchAll,
  fetchInvoices,
  generate,
  markSent,
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
]
const generatedInvoiceStatusOptions = [
  { label: 'Semua', value: null },
  { label: 'Dibuat', value: 'generated' },
  { label: 'Partial Paid', value: 'partial_paid' },
  { label: 'Paid', value: 'paid' },
]

const selectedTotal = computed(() => selectedReceivableRows.value.reduce((sum, row) => sum + (row.total_biaya?.sisa || 0), 0))
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

  fetchInvoices(1)
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
    bookings: [{
      id: row.id,
      kode_booking: row.kode_booking,
      customer_name: row.customer?.nama,
      amount: row.invoice.total_amount ?? row.total_biaya?.sisa ?? 0,
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

onMounted(async () => {
  await Promise.all([
    fetchAll(),
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
      </div>
    </div>

    <div class="app-card filter-bar">
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
              v-if="data.invoice?.generated"
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
              Kirim
            </button>
            <button
              v-if="data.invoice?.generated"
              class="btn-pill btn-secondary btn-pill-compact"
              :disabled="!data.invoice?.generated || actionLoading"
              @click="openInvoicePdf(data.invoice)"
            >
              <i class="pi pi-file-pdf"></i>
              PDF
            </button>
          </div>
        </template>
      </Column>
      <Column header="Booking" style="min-width: 10rem">
        <template #body="{ data }">
          <button class="link-button text-xs flex" @click="router.push(`/bookings/${data.id}`)">{{ data.kode_booking }}</button>
          <BookingStatusBadge :status="data.invoice?.number ? 'generated' : 'not_generated'" :text="data.invoice?.number || 'Belum Buat Invoice'" /> 
        </template>
      </Column>
      <Column header="Tanggal Buat & Jatuh Tempo" style="min-width: 12rem">
        <template #body="{ data }">
          <div>{{formatDateTime(data.invoice?.generated_at)}}</div>
          <div class="text-xs text-secondary mt-1">{{data.invoice?.generated ? 'Due ' + formatDate(data.invoice?.due_date || data.due_date) : ''}}</div>
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
      v-else
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
            <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="openInvoicePdf(data)">
              <i class="pi pi-file-pdf"></i>
              PDF
            </button>
          </div>
        </template>
      </Column>
    </DataTable>

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

    <Dialog v-model:visible="showPaymentDialog" header="Pembayaran Invoice" modal :style="{ width: '480px' }" class="custom-dialog">
      <div class="dialog-stack">
        <div class="app-muted-panel">
          <div class="summary-row">
            <span>Invoice</span>
            <strong>{{ selectedInvoice?.invoice_number }}</strong>
          </div>
          <div class="summary-row">
            <span>Total</span>
            <strong>{{ formatCurrency(selectedInvoice?.total_amount) }}</strong>
          </div>
          <div class="summary-row">
            <span>Sudah dibayar</span>
            <strong>{{ formatCurrency(selectedInvoice?.paid_amount) }}</strong>
          </div>
          <div class="summary-row">
            <span>Sisa</span>
            <strong>{{ formatCurrency(selectedInvoiceRemaining) }}</strong>
          </div>
          <div class="summary-row">
            <span>Status</span>
            <strong>{{ selectedInvoice?.status || '-' }}</strong>
          </div>
        </div>
        <div class="app-muted-panel" v-if="selectedInvoice?.bookings?.length">
          <div class="summary-row" v-for="booking in selectedInvoice.bookings" :key="booking.id || booking.kode_booking">
            <span>{{ booking.kode_booking }} - {{ booking.customer_name || '-' }}</span>
            <strong>{{ formatCurrency(booking.amount) }}</strong>
          </div>
        </div>
        <div class="app-muted-panel" v-if="selectedInvoice?.payments?.length">
          <div class="summary-row" v-for="payment in selectedInvoice.payments" :key="payment.id">
            <span>{{ formatDate(payment.paid_at) }} - {{ payment.payment_account_name || '-' }}</span>
            <strong>{{ formatCurrency(payment.amount) }}</strong>
          </div>
        </div>
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

.toggle-item {
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
  .filter-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions .btn-pill,
  .filter-bar .btn-pill {
    width: 100%;
    justify-content: center;
  }
}
</style>
