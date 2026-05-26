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
import InputText from 'primevue/inputtext'
import Paginator from 'primevue/paginator'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { usePaymentAccount } from '../../composables/usePaymentAccount'
import { useRentToRent } from '../../composables/useRentToRent'

const router = useRouter()
const toast = useToast()
const {
  debts,
  bills,
  paymentHistory,
  paymentHistoryPagination,
  selectedDebt,
  availableOwners,
  summary,
  loading,
  historyLoading,
  actionLoading,
  pagination,
  filters,
  billFilters,
  fetchDebts,
  fetchDebt,
  updateDebtAmount,
  requestAmountChange,
  cancelAmountChange,
  fetchBills,
  fetchBill,
  generateBill,
  markSent,
  markDebtPaid,
  markBillPaid,
  addPayment,
  addDebtPayment,
  voidPayment,
  requestVoid,
  openPdf,
  fetchPaymentHistory,
} = useRentToRent()
const { accounts, fetchAll: fetchPaymentAccounts } = usePaymentAccount()

const activeTab = ref('debts')
const selectedRows = ref([])
const selectedBill = ref(null)
const selectedPaymentDebt = ref(null)
const showGenerateDialog = ref(false)
const showDebtDialog = ref(false)
const showPaymentDialog = ref(false)
const showVoidDialog = ref(false)
const paymentAccountsLoaded = ref(false)
const detailAmountForm = ref({ amount_override: null, reason: '' })
const showCancelConfirm = ref(false)
const voidForm = ref({ bill: null, void_reason: '' })
const paymentForm = ref({
  payment_account_id: null,
  amount: null,
  paid_at: new Date(),
})

const debtStatusOptions = [
  { label: 'Semua', value: null },
  { label: 'Belum', value: 'open' },
  { label: 'Sudah Dibuat Dokumen', value: 'billed' },
  { label: 'Partial Paid', value: 'partial_paid' },
  { label: 'Paid', value: 'paid' },
]
const billStatusOptions = [
  { label: 'Semua', value: null },
  { label: 'Dibuat', value: 'generated' },
  { label: 'Terkirim', value: 'sent' },
  { label: 'Partial Paid', value: 'partial_paid' },
  { label: 'Paid', value: 'paid' },
  { label: 'Menunggu ACC Void', value: 'void_requested' },
  { label: 'Void', value: 'void' },
]

const eligibleRows = computed(() => selectedRows.value.filter(row => row.status === 'open' && !row.bill))
const selectedOwnerIds = computed(() => [...new Set(eligibleRows.value.map(row => row.rental_owner?.id).filter(Boolean))])
const canGenerateBill = computed(() => eligibleRows.value.length > 0 && selectedOwnerIds.value.length === 1 && selectedRows.value.length === eligibleRows.value.length)
const selectedTotal = computed(() => eligibleRows.value.reduce((sum, row) => sum + Number(row.total_amount || 0), 0))
const selectedBookingCodes = computed(() => eligibleRows.value.map(row => row.kode_booking).join(', '))
const selectedOwner = computed(() => eligibleRows.value[0]?.rental_owner || null)
const paymentTarget = computed(() => selectedBill.value || selectedPaymentDebt.value)
const paymentPreviewItems = computed(() => {
  if (selectedBill.value) return selectedBill.value.items || []
  if (!selectedPaymentDebt.value) return []

  const debt = selectedPaymentDebt.value
  return [{
    id: debt.id,
    kode_booking: debt.kode_booking,
    unit_name: debt.unit?.name || '-',
    unit_plate: debt.unit?.no_polisi,
    customer_name: debt.booking?.customer_name || '-',
    amount: debt.total_amount,
  }]
})
const paymentAccountOptions = computed(() => accounts.value.map(account => ({
  label: `${account.nama_bank} - ${account.nomor_rekening} (${account.atas_nama})`,
  value: account.id,
})))
const ownerOptions = computed(() => [
  { id: null, nama: 'Semua Pemilik' },
  ...availableOwners.value,
])
const debtGroups = computed(() => {
  const groups = new Map()
  debts.value.forEach((debt) => {
    if (Number(debt.remaining_amount || 0) <= 0 && filters.value.status !== 'paid') {
      return
    }
    const ownerId = debt.rental_owner?.id || 'unknown'
    if (!groups.has(ownerId)) {
      groups.set(ownerId, {
        owner: debt.rental_owner,
        rows: [],
        total_amount: 0,
        paid_amount: 0,
        remaining_amount: 0,
      })
    }
    const group = groups.get(ownerId)
    group.rows.push(debt)
    group.total_amount += Number(debt.total_amount || 0)
    group.paid_amount += Number(debt.paid_amount || 0)
    group.remaining_amount += Number(debt.remaining_amount || 0)
  })

  return [...groups.values()]
})

const computedSummary = computed(() => {
  let totalAmount = 0
  let paidAmount = 0
  let remainingAmount = 0
  const activeOwners = new Set()

  debts.value.forEach((debt) => {
    if (Number(debt.remaining_amount || 0) <= 0 && filters.value.status !== 'paid') {
      return
    }
    totalAmount += Number(debt.total_amount || 0)
    paidAmount += Number(debt.paid_amount || 0)
    remainingAmount += Number(debt.remaining_amount || 0)
    if (debt.rental_owner?.id) {
      activeOwners.add(debt.rental_owner.id)
    }
  })

  return {
    total_amount: totalAmount,
    paid_amount: paidAmount,
    remaining_amount: remainingAmount,
    owner_count: activeOwners.size,
  }
})

watch(selectedRows, (rows) => {
  if (!rows.length) return

  const firstOwnerId = rows[0]?.rental_owner?.id
  const sanitized = rows.filter(row => row.status === 'open' && !row.bill && row.rental_owner?.id === firstOwnerId)
  if (sanitized.length !== rows.length) {
    selectedRows.value = sanitized
    toast.add({
      severity: 'warn',
      summary: 'Pilihan disesuaikan',
      detail: 'Dokumen hanya bisa dibuat dari transaksi open milik satu pemilik rental.',
      life: 3500,
    })
  }
})

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)

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

const statusSeverity = (status) => {
  if (status === 'paid') return 'success'
  if (status === 'partial_paid') return 'info'
  if (status === 'billed' || status === 'sent' || status === 'void_requested') return 'warn'
  if (status === 'cancelled' || status === 'void') return 'danger'
  return 'secondary'
}

const statusLabel = (status) => {
  if (status === 'open') return 'Belum'
  if (status === 'billed') return 'Sudah Dokumen'
  if (status === 'generated') return 'Dibuat'
  if (status === 'sent') return 'Terkirim'
  if (status === 'partial_paid') return 'Partial Paid'
  if (status === 'paid') return 'Paid'
  if (status === 'void_requested') return 'Menunggu ACC Void'
  if (status === 'void') return 'Void'
  return status || '-'
}

const paymentStatusSeverity = (status) => {
  if (status === 'voided') return 'danger'
  if (status === 'void_requested') return 'warn'
  return 'success'
}

const paymentStatusLabel = (status) => {
  if (status === 'voided') return 'Void'
  if (status === 'void_requested') return 'Menunggu ACC Void'
  return 'Aktif'
}

const applyFilters = () => {
  selectedRows.value = []
  pagination.value.current_page = 1
  if (activeTab.value === 'debts') {
    fetchDebts(1)
    return
  }

  if (activeTab.value === 'bills') {
    fetchBills(1)
    return
  }

  fetchPaymentHistory()
}

const resetFilters = () => {
  filters.value = { search: '', rental_owner_id: null, status: null }
  billFilters.value = { rental_owner_id: null, status: null }
  applyFilters()
}

const onPage = (event) => {
  pagination.value.current_page = event.page + 1
  if (activeTab.value === 'debts') {
    fetchDebts(pagination.value.current_page)
    return
  }
  fetchBills(pagination.value.current_page)
}

const onLatestPaymentHistoryPage = (event) => {
  fetchPaymentHistory({ view: 'latest', page: event.page + 1 })
}

const onGroupPaymentHistoryPage = (event) => {
  fetchPaymentHistory({ view: 'group', page: event.page + 1 })
}

const switchTab = (tab) => {
  activeTab.value = tab
  selectedRows.value = []
  pagination.value.current_page = 1
  if (tab === 'debts') {
    fetchDebts(1)
    return
  }
  if (tab === 'bills') {
    fetchBills(1)
    return
  }
  fetchPaymentHistory()
}

const openDebtDetail = async (debt) => {
  await fetchDebt(debt.id)
  if (selectedDebt.value.pending_amount_request) {
    detailAmountForm.value.amount_override = selectedDebt.value.pending_amount_request.requested_amount_override
    detailAmountForm.value.reason = selectedDebt.value.pending_amount_request.reason
  } else {
    detailAmountForm.value.amount_override = selectedDebt.value.amount_override ?? selectedDebt.value.default_amount ?? 0
    detailAmountForm.value.reason = ''
  }
  showDebtDialog.value = true
}

const submitDebtAmount = async () => {
  if (!selectedDebt.value?.id) return
  if (!detailAmountForm.value.reason || detailAmountForm.value.reason.trim().length < 5) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Alasan wajib diisi (minimal 5 karakter)', life: 3000 })
    return
  }
  await requestAmountChange(selectedDebt.value.id, {
    amount_override: detailAmountForm.value.amount_override,
    reason: detailAmountForm.value.reason,
  })
  showDebtDialog.value = false
}

const resetDebtAmount = async () => {
  if (!selectedDebt.value?.id) return
  if (!detailAmountForm.value.reason || detailAmountForm.value.reason.trim().length < 5) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Alasan wajib diisi untuk melakukan reset (minimal 5 karakter)', life: 3000 })
    return
  }
  await requestAmountChange(selectedDebt.value.id, {
    amount_override: null,
    reason: detailAmountForm.value.reason,
  })
  showDebtDialog.value = false
}

const handleCancelAmountChange = async () => {
  if (!selectedDebt.value?.pending_amount_request?.id) return
  await cancelAmountChange(selectedDebt.value.pending_amount_request.id)
  showDebtDialog.value = false
}

const openGenerateDialog = () => {
  if (!canGenerateBill.value) return
  showGenerateDialog.value = true
}

const submitGenerateBill = async () => {
  if (!canGenerateBill.value) return
  await generateBill({ debt_ids: eligibleRows.value.map(row => row.id) })
  selectedRows.value = []
  showGenerateDialog.value = false
}

const ensurePaymentAccounts = async () => {
  if (paymentAccountsLoaded.value) return
  await fetchPaymentAccounts({ per_page: 100, is_active: true })
  paymentAccountsLoaded.value = true
}

const openPaymentDialog = async (bill) => {
  if (['void', 'void_requested'].includes(bill.status)) return
  await ensurePaymentAccounts()
  selectedBill.value = bill
  selectedPaymentDebt.value = null
  paymentForm.value = {
    payment_account_id: paymentAccountOptions.value[0]?.value || null,
    amount: bill.remaining_amount || null,
    paid_at: new Date(),
  }
  showPaymentDialog.value = true
}

const openDebtPaymentDialog = async (debt) => {
  await ensurePaymentAccounts()
  selectedBill.value = null
  selectedPaymentDebt.value = debt
  paymentForm.value = {
    payment_account_id: paymentAccountOptions.value[0]?.value || null,
    amount: debt.remaining_amount || null,
    paid_at: new Date(),
  }
  showPaymentDialog.value = true
}

const openDirectPayment = async (debt) => {
  if (debt.remaining_amount <= 0 || ['cancelled', 'paid'].includes(debt.status)) return

  if (debt.bill?.id) {
    const bill = await fetchBill(debt.bill.id)
    await openPaymentDialog(bill)
    return
  }

  await openDebtPaymentDialog(debt)
}

const submitMarkDebtPaid = async (debt) => {
  if (!debt?.id || ['cancelled', 'paid'].includes(debt.status)) return
  const ok = window.confirm(`Tandai ${debt.kode_booking || 'rent-to-rent'} sebagai sudah dibayar meski nominal belum sesuai?`)
  if (!ok) return
  await markDebtPaid(debt.id)
}

const submitMarkBillPaid = async (bill) => {
  if (!bill?.id || ['void', 'void_requested', 'paid'].includes(bill.status)) return
  const ok = window.confirm(`Tandai dokumen ${bill.bill_number || ''} sebagai sudah dibayar meski nominal belum sesuai?`)
  if (!ok) return
  await markBillPaid(bill.id)
}

const submitVoidPayment = async (payment) => {
  const paymentId = payment?.payment_id || payment?.id
  if (!paymentId || ['voided', 'void_requested'].includes(payment.status)) return
  const reason = window.prompt('Alasan request void pembayaran rent-to-rent:')
  if (!reason?.trim()) return
  await voidPayment(paymentId, { void_reason: reason.trim() })
  if (selectedDebt.value?.id) {
    await fetchDebt(selectedDebt.value.id)
  }
}

const publicBillUrl = (bill) => {
  if (bill?.public_path) {
    return new URL(bill.public_path, window.location.origin).toString()
  }

  return bill?.public_url || ''
}

const copyToClipboard = async (text) => {
  if (!text) return false

  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(text)
    return true
  }

  const textarea = document.createElement('textarea')
  textarea.value = text
  textarea.setAttribute('readonly', '')
  textarea.style.position = 'fixed'
  textarea.style.opacity = '0'
  document.body.appendChild(textarea)
  textarea.select()
  const copied = document.execCommand('copy')
  document.body.removeChild(textarea)
  return copied
}

const sendBill = async (billId) => {
  if (!billId) return

  const bill = await markSent(billId)
  const copiedUrl = publicBillUrl(bill)

  if (await copyToClipboard(copiedUrl)) {
    toast.add({ severity: 'info', summary: 'Link public disalin', detail: copiedUrl, life: 5000 })
  }
}

const openPublicBill = (bill) => {
  const url = publicBillUrl(bill)
  if (!url) return
  window.open(url, '_blank', 'noopener,noreferrer')
}

const openVoidDialog = (bill) => {
  voidForm.value = {
    bill,
    void_reason: '',
  }
  showVoidDialog.value = true
}

const submitVoidRequest = async () => {
  if (!voidForm.value.bill?.id || !voidForm.value.void_reason?.trim()) return
  await requestVoid(voidForm.value.bill.id, { void_reason: voidForm.value.void_reason })
  showVoidDialog.value = false
  voidForm.value = { bill: null, void_reason: '' }
}

const submitPayment = async () => {
  if ((!selectedBill.value?.id && !selectedPaymentDebt.value?.id) || !paymentForm.value.amount || !paymentForm.value.payment_account_id) return

  const payload = {
    payment_account_id: paymentForm.value.payment_account_id,
    amount: paymentForm.value.amount,
    paid_at: toApiDate(paymentForm.value.paid_at),
  }

  if (selectedBill.value?.id) {
    await addPayment(selectedBill.value.id, payload)
  } else {
    await addDebtPayment(selectedPaymentDebt.value.id, payload)
  }

  showPaymentDialog.value = false
  selectedBill.value = null
  selectedPaymentDebt.value = null
}

onMounted(async () => {
  await fetchDebts(1)
})
</script>

<template>
  <div class="page-container" :class="{ 'table-page-active': activeTab === 'bills' }">
    <div class="page-header">
      <div class="header-left">
        <h1 class="text-h1">Rent to Rent</h1>
        <p class="text-secondary text-xs">Kelola hutang penggunaan unit milik rental lain dan pembayaran ke pemilik rental.</p>
      </div>
      <div class="header-actions">
        <div class="tab-toggle-container">
          <div class="pill-toggle">
            <button class="toggle-item" :class="{ active: activeTab === 'debts' }" @click="switchTab('debts')">Transaksi</button>
            <button class="toggle-item" :class="{ active: activeTab === 'bills' }" @click="switchTab('bills')">Dokumen Tagihan</button>
            <button class="toggle-item" :class="{ active: activeTab === 'payments' }" @click="switchTab('payments')">Riwayat Pembayaran</button>
          </div>
        </div>
        <button v-if="activeTab === 'debts'" class="btn-pill btn-primary" :disabled="!canGenerateBill || actionLoading" @click="openGenerateDialog">
          <i class="pi pi-file-plus"></i>
          Buat Dokumen Tagihan
        </button>
      </div>
    </div>

    <div v-if="activeTab === 'payments'" class="filter-bar surface-card">
      <div class="filter-group">
        <label>Riwayat Pembayaran</label>
        <span class="text-secondary text-xs">Pembayaran terbaru dan group per dokumen tagihan.</span>
      </div>
      <div class="filter-actions">
        <button class="btn-pill btn-secondary btn-pill-compact" :disabled="historyLoading" @click="fetchPaymentHistory">
          <i class="pi pi-refresh"></i>
          Refresh
        </button>
      </div>
    </div>

    <ProgressBar v-if="loading || historyLoading" mode="indeterminate" style="height: 4px" class="mb-4" />

    <div v-if="activeTab !== 'payments'" class="list-tab-fill rent-list-tab">
      <div class="filter-bar surface-card">
        <div class="filter-groups">
          <div v-if="activeTab === 'debts'" class="filter-group filter-group-wide">
            <label>Pencarian</label>
            <span class="filter-search">
              <i class="pi pi-search"></i>
              <InputText v-model="filters.search" placeholder="Kode, pelanggan, unit, tujuan..." class="w-full" @keyup.enter="applyFilters" />
            </span>
          </div>
          <div class="filter-group">
            <label>Pemilik Rental</label>
            <Dropdown
              v-if="activeTab === 'debts'"
              v-model="filters.rental_owner_id"
              :options="ownerOptions"
              optionLabel="nama"
              optionValue="id"
              placeholder="Semua Pemilik"
              filter
              class="w-full md:w-56"
            />
            <Dropdown
              v-else
              v-model="billFilters.rental_owner_id"
              :options="ownerOptions"
              optionLabel="nama"
              optionValue="id"
              placeholder="Semua Pemilik"
              filter
              class="w-full md:w-56"
            />
          </div>
          <div class="filter-group">
            <label>Status</label>
            <Dropdown
              v-if="activeTab === 'debts'"
              v-model="filters.status"
              :options="debtStatusOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Semua"
              class="w-full md:w-52"
            />
            <Dropdown
              v-else
              v-model="billFilters.status"
              :options="billStatusOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Semua"
              class="w-full md:w-52"
            />
          </div>
        </div>
        <div class="filter-actions">
          <button class="btn-pill btn-secondary btn-pill-compact" :disabled="loading" @click="resetFilters">
            <i class="pi pi-refresh"></i>
            Reset
          </button>
          <button class="btn-pill btn-primary btn-pill-compact" :disabled="loading" @click="applyFilters">
            <i class="pi pi-filter"></i>
            Filter
          </button>
        </div>
      </div>

      <div v-if="activeTab === 'debts'" class="summary-grid">
        <div class="summary-tile">
          <span>Total Rent to Rent</span>
          <strong>{{ formatCurrency(computedSummary.total_amount) }}</strong>
        </div>
        <div class="summary-tile">
          <span>Sudah Bayar</span>
          <strong>{{ formatCurrency(computedSummary.paid_amount) }}</strong>
        </div>
        <div class="summary-tile">
          <span>Sisa Hutang</span>
          <strong>{{ formatCurrency(computedSummary.remaining_amount) }}</strong>
        </div>
        <div class="summary-tile">
          <span>Pemilik Rental</span>
          <strong>{{ computedSummary.owner_count }}</strong>
        </div>
      </div>

      <section v-if="activeTab === 'debts'" class="debt-groups">
      <div v-for="group in debtGroups" :key="group.owner?.id || 'unknown'" class="owner-section">
        <div class="owner-section-head">
          <div>
            <h2>{{ group.owner?.nama || 'Tanpa Pemilik' }}</h2>
            <p>{{ group.owner?.bank || '-' }} <span v-if="group.owner?.no_rek">- {{ group.owner.no_rek }}</span></p>
          </div>
          <div class="owner-totals">
            <span>{{ formatCurrency(group.total_amount) }}</span>
            <small>Sisa {{ formatCurrency(group.remaining_amount) }}</small>
          </div>
        </div>

        <DataTable
          v-model:selection="selectedRows"
          :value="group.rows"
          dataKey="id"
          responsiveLayout="scroll"
          class="drent-datatable desktop-table"
        >
          <Column selectionMode="multiple" headerStyle="width: 3rem" />
          <Column header="Aksi" style="min-width: 9rem">
            <template #body="{ data }">
              <div class="table-actions">
                <button class="btn-pill btn-primary btn-pill-compact" :disabled="actionLoading || data.remaining_amount <= 0 || ['cancelled', 'paid'].includes(data.status)" @click="openDirectPayment(data)">
                  <i class="pi pi-wallet"></i>
                  Bayar
                </button>
                <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading || ['cancelled', 'paid'].includes(data.status)" @click="submitMarkDebtPaid(data)">
                  <i class="pi pi-check"></i>
                  Selesai
                </button>
                <button class="btn-pill btn-secondary btn-pill-compact" :class="{ 'warning-btn': data.pending_amount_request }" :disabled="actionLoading" @click="openDebtDetail(data)">
                  <i class="pi" :class="data.pending_amount_request ? 'pi-clock' : 'pi-pencil'"></i>
                  {{ data.pending_amount_request ? 'Pending ACC' : 'Nominal' }}
                </button>
              </div>
            </template>
          </Column>
          <Column header="Kode Booking" style="min-width: 10rem">
            <template #body="{ data }">
              <button class="link-button" @click="router.push(`/bookings/${data.booking_id}`)">{{ data.kode_booking }}</button>
              <div class="mt-2"><Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" /></div>
            </template>
          </Column>
          <Column header="Rent to Rent" style="min-width: 9rem">
            <template #body="{ data }">
              <div class="amount-stack">
                <strong>{{ formatCurrency(data.total_amount) }}</strong>
                <span v-if="data.pending_amount_request" style="color: #8C660A; background-color: #FDF4D9; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; width: fit-content; margin-top: 4px; display: inline-block;">Pending ACC: {{ data.pending_amount_request.requested_amount_override !== null ? formatCurrency(data.pending_amount_request.requested_amount_override) : 'Reset Live' }}</span>
                <span v-else-if="data.amount_override !== null">Manual override</span>
                <span v-else>Live dari master unit</span>
              </div>
            </template>
          </Column>
          <Column header="Sudah Bayar" style="min-width: 9em">
            <template #body="{ data }">
              <div class="amount-stack">
                <strong>{{ formatCurrency(data.paid_amount) }}</strong>
                <span>Sisa {{ formatCurrency(data.remaining_amount) }}</span>
              </div>
            </template>
          </Column>
          <Column header="Unit" style="min-width: 10rem">
            <template #body="{ data }">
              <strong>{{ data.unit?.name || '-' }}</strong>
              <div class="text-secondary text-xs">{{ data.unit?.no_polisi || '-' }}</div>
              <div class="text-secondary text-xs">{{ data.unit?.lama_sewa || 0 }} {{ data.unit?.paket_sewa || 'harian' }}</div>
            </template>
          </Column>
          <Column header="Nama Pelanggan" style="min-width: 10rem">
            <template #body="{ data }">{{ data.booking?.customer_name || '-' }}</template>
          </Column>
          <Column header="Tujuan" style="min-width: 14rem">
            <template #body="{ data }">{{ data.booking?.tujuan || '-' }}</template>
          </Column>
          
        </DataTable>
        <div class="mobile-card-list rent-mobile-list">
          <article v-for="debt in group.rows" :key="debt.id" class="mobile-list-card">
            <div class="mobile-card-head">
              <div>
                <button class="link-button" @click="router.push(`/bookings/${debt.booking_id}`)">{{ debt.kode_booking }}</button>
                <p>{{ debt.booking?.customer_name || '-' }}</p>
              </div>
              <Tag :value="statusLabel(debt.status)" :severity="statusSeverity(debt.status)" />
            </div>
            <div class="mobile-card-meta">
              <div><span>Unit</span><strong>{{ debt.unit?.name || '-' }}</strong><small>{{ debt.unit?.no_polisi || '-' }}</small></div>
              <div><span>Tujuan</span><strong>{{ debt.booking?.tujuan || '-' }}</strong><small>{{ debt.unit?.lama_sewa || 0 }} {{ debt.unit?.paket_sewa || 'harian' }}</small></div>
            </div>
            <div class="mobile-card-amount">
              <div><span>Total</span><strong>{{ formatCurrency(debt.total_amount) }}</strong></div>
              <div v-if="debt.pending_amount_request" style="grid-column: span 2; margin-top: 4px;">
                <span style="color: #8C660A; background-color: #FDF4D9; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; display: inline-block;">Pending ACC: {{ debt.pending_amount_request.requested_amount_override !== null ? formatCurrency(debt.pending_amount_request.requested_amount_override) : 'Reset Live' }}</span>
              </div>
              <div><span>Sisa</span><strong>{{ formatCurrency(debt.remaining_amount) }}</strong></div>
            </div>
            <div class="mobile-card-actions">
              <button class="btn-pill btn-primary btn-pill-compact" :disabled="actionLoading || debt.remaining_amount <= 0 || ['cancelled', 'paid'].includes(debt.status)" @click="openDirectPayment(debt)">
                <i class="pi pi-wallet"></i>
                Bayar
              </button>
              <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading || ['cancelled', 'paid'].includes(debt.status)" @click="submitMarkDebtPaid(debt)">
                <i class="pi pi-check"></i>
                Tag Paid
              </button>
              <button class="btn-pill btn-secondary btn-pill-compact" :class="{ 'warning-btn': debt.pending_amount_request }" :disabled="actionLoading" @click="openDebtDetail(debt)">
                <i class="pi" :class="debt.pending_amount_request ? 'pi-clock' : 'pi-pencil'"></i>
                {{ debt.pending_amount_request ? 'Pending ACC' : 'Ubah Nominal' }}
              </button>
            </div>
          </article>
        </div>
      </div>
      <div v-if="!debtGroups.length && !loading" class="empty-state">Belum ada hutang rent-to-rent sesuai filter.</div>

      <Paginator
        v-if="debtGroups.length > 0"
        :rows="pagination.per_page"
        :totalRecords="pagination.total"
        :first="(pagination.current_page - 1) * pagination.per_page"
        @page="onPage"
        class="mt-4"
      ></Paginator>
      </section>

      <div v-if="activeTab === 'bills'" class="table-shell">
        <DataTable
          :value="bills"
          dataKey="id"
          lazy
          paginator
          scrollable
          scrollHeight="flex"
          :rows="pagination.per_page"
          :totalRecords="pagination.total"
          :loading="loading"
          @page="onPage"
          responsiveLayout="scroll"
          class="drent-datatable desktop-table"
        >
        <Column header="Aksi" style="min-width: 15rem">
        <template #body="{ data }">
          <div class="table-actions">
            <button class="btn-pill btn-primary btn-pill-compact" :disabled="data.remaining_amount <= 0 || ['void', 'void_requested'].includes(data.status)" @click="openPaymentDialog(data)">
              <i class="pi pi-wallet"></i>
              Bayar
            </button>
              <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="sendBill(data.id)">
              <i class="pi pi-send"></i>
              Kirim
            </button>
            <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading || data.remaining_amount <= 0 || ['void', 'void_requested', 'paid'].includes(data.status)" @click="submitMarkBillPaid(data)">
              <i class="pi pi-check"></i>
              Selesai
            </button>
            <!-- <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="openPublicBill(data)">
              <i class="pi pi-external-link"></i>
              Public
            </button>
            <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="openPdf(data.id, `${data.bill_number}.pdf`)">
              <i class="pi pi-download"></i>
              PDF
            </button> -->
            <button class="btn-pill btn-danger btn-pill-compact" :disabled="actionLoading || ['void', 'void_requested'].includes(data.status)" @click="openVoidDialog(data)">
              <i class="pi pi-ban"></i>
              Void
            </button>
          </div>
        </template>
      </Column>
      <Column header="Kode" style="min-width: 13rem">
        <template #body="{ data }">
          <strong>{{ data.bill_number }}</strong>
          <div class="mt-2"><Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" /></div>
        </template>
      </Column>
      <Column header="Pemilik Rental" style="min-width: 9rem">
        <template #body="{ data }">
          <strong>{{ data.rental_owner?.nama || '-' }}</strong>
          <div class="text-secondary text-xs">{{ data.rental_owner?.bank || '-' }} {{ data.rental_owner?.no_rek || '' }}</div>
        </template>
      </Column>
      <Column header="Transaksi" style="min-width: 14rem">
        <template #body="{ data }">
          <div class="booking-list">
            <span v-for="item in data.items" :key="item.id">{{ item.kode_booking }} - {{ item.unit_plate || '-' }}</span>
          </div>
        </template>
      </Column>
      <Column header="Total" style="min-width: 9rem">
        <template #body="{ data }"><div class="amount-stack">{{ formatCurrency(data.total_amount) }}</div></template>
      </Column>
      <Column header="Sudah Bayar" style="min-width: 9rem">
        <template #body="{ data }"><div class="amount-stack">{{ formatCurrency(data.paid_amount) }}</div></template>
      </Column>
      <Column header="Tanggal" style="min-width: 12rem">
        <template #body="{ data }">
          <div>{{ formatDate(data.generated_at) }}</div>
          <div class="text-secondary text-xs">Kirim {{ formatDateTime(data.sent_at) }}</div>
        </template>
      </Column>
      
        </DataTable>
        <div class="mobile-card-list rent-mobile-list">
          <article v-for="bill in bills" :key="bill.id" class="mobile-list-card">
            <div class="mobile-card-head">
              <div>
                <strong>{{ bill.bill_number }}</strong>
                <p>{{ bill.rental_owner?.nama || '-' }}</p>
              </div>
              <Tag :value="statusLabel(bill.status)" :severity="statusSeverity(bill.status)" />
            </div>
            <div class="booking-list">
              <span v-for="item in bill.items" :key="item.id">{{ item.kode_booking }} - {{ item.unit_plate || '-' }}</span>
            </div>
            <div class="mobile-card-amount">
              <div><span>Total</span><strong>{{ formatCurrency(bill.total_amount) }}</strong></div>
              <div><span>Sudah Bayar</span><strong>{{ formatCurrency(bill.paid_amount) }}</strong></div>
            </div>
            <div class="mobile-card-meta">
              <div><span>Tanggal</span><strong>{{ formatDate(bill.generated_at) }}</strong><small>Kirim {{ formatDateTime(bill.sent_at) }}</small></div>
              <div><span>Bank</span><strong>{{ bill.rental_owner?.bank || '-' }}</strong><small>{{ bill.rental_owner?.no_rek || '' }}</small></div>
            </div>
            <div class="mobile-card-actions">
              <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="sendBill(bill.id)">
                <i class="pi pi-send"></i>
                Kirim
              </button>
              <button class="btn-pill btn-primary btn-pill-compact" :disabled="bill.remaining_amount <= 0 || ['void', 'void_requested'].includes(bill.status)" @click="openPaymentDialog(bill)">
                <i class="pi pi-wallet"></i>
                Bayar
              </button>
              <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading || bill.remaining_amount <= 0 || ['void', 'void_requested', 'paid'].includes(bill.status)" @click="submitMarkBillPaid(bill)">
                <i class="pi pi-check"></i>
                Tag Paid
              </button>
              <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="openPublicBill(bill)">
                <i class="pi pi-external-link"></i>
                Public
              </button>
              <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="openPdf(bill.id, `${bill.bill_number}.pdf`)">
                <i class="pi pi-download"></i>
                PDF
              </button>
              <button class="btn-pill btn-danger btn-pill-compact" :disabled="actionLoading || ['void', 'void_requested'].includes(bill.status)" @click="openVoidDialog(bill)">
                <i class="pi pi-ban"></i>
                Void
              </button>
            </div>
          </article>
        </div>
      </div>
    </div>

    <div v-if="activeTab === 'payments'" class="payment-history-stack">
      <section class="payment-section">
        <div class="section-heading">
          <h2>Pembayaran Terbaru</h2>
        </div>
        <DataTable :value="paymentHistory.latest" dataKey="id" responsiveLayout="scroll" class="drent-datatable desktop-table">
          <Column header="Dokumen" style="min-width: 12rem">
            <template #body="{ data }">{{ data.bill_number || '-' }}</template>
          </Column>
          <Column header="Pemilik Rental" style="min-width: 14rem">
            <template #body="{ data }">{{ data.owner_name || '-' }}</template>
          </Column>
          <Column header="Booking" style="min-width: 16rem">
            <template #body="{ data }">{{ (data.booking_codes || []).join(', ') || '-' }}</template>
          </Column>
          <Column header="Akun" style="min-width: 13rem">
            <template #body="{ data }">{{ data.payment_account_name || '-' }}</template>
          </Column>
          <Column header="Tanggal" style="min-width: 12rem">
            <template #body="{ data }">{{ formatDateTime(data.paid_at) }}</template>
          </Column>
          <Column header="Nominal" style="min-width: 11rem">
            <template #body="{ data }"><div class="amount-stack">{{ formatCurrency(data.amount) }}</div></template>
          </Column>
          <Column header="Status" style="min-width: 9rem">
            <template #body="{ data }">
              <Tag :value="paymentStatusLabel(data.status)" :severity="paymentStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column header="Aksi" style="min-width: 9rem">
            <template #body="{ data }">
              <button class="btn-pill btn-danger btn-pill-compact" :disabled="actionLoading || ['voided', 'void_requested'].includes(data.status)" @click="submitVoidPayment(data)">
                <i class="pi pi-ban"></i>
                Void
              </button>
            </template>
          </Column>
        </DataTable>
        <Paginator
          :rows="paymentHistoryPagination.latest.per_page"
          :totalRecords="paymentHistoryPagination.latest.total"
          :first="(paymentHistoryPagination.latest.current_page - 1) * paymentHistoryPagination.latest.per_page"
          template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
          currentPageReportTemplate="{first} - {last} dari {totalRecords}"
          class="history-paginator"
          @page="onLatestPaymentHistoryPage"
        />
        <div class="mobile-card-list rent-mobile-list">
          <article v-for="payment in paymentHistory.latest" :key="payment.id" class="mobile-list-card">
            <div class="mobile-card-head">
              <div>
                <strong>{{ payment.bill_number || '-' }}</strong>
                <p>{{ payment.owner_name || '-' }}</p>
              </div>
              <Tag :value="paymentStatusLabel(payment.status)" :severity="paymentStatusSeverity(payment.status)" />
            </div>
            <div class="mobile-card-meta">
              <div><span>Booking</span><strong>{{ (payment.booking_codes || []).join(', ') || '-' }}</strong></div>
              <div><span>Akun</span><strong>{{ payment.payment_account_name || '-' }}</strong></div>
            </div>
            <div class="mobile-card-amount">
              <div><span>Tanggal</span><strong>{{ formatDateTime(payment.paid_at) }}</strong></div>
              <div><span>Nominal</span><strong>{{ formatCurrency(payment.amount) }}</strong></div>
            </div>
            <div class="mobile-card-actions">
              <button class="btn-pill btn-danger btn-pill-compact" :disabled="actionLoading || ['voided', 'void_requested'].includes(payment.status)" @click="submitVoidPayment(payment)">
                <i class="pi pi-ban"></i>
                Void
              </button>
            </div>
          </article>
        </div>
      </section>

      <section class="payment-section">
        <div class="section-heading">
          <h2>Group per Dokumen</h2>
        </div>
        <DataTable :value="paymentHistory.groups" dataKey="bill_id" responsiveLayout="scroll" class="drent-datatable desktop-table">
          <Column header="Dokumen" style="min-width: 12rem">
            <template #body="{ data }">{{ data.bill_number || '-' }}</template>
          </Column>
          <Column header="Pemilik Rental" style="min-width: 14rem">
            <template #body="{ data }">{{ data.owner_name || '-' }}</template>
          </Column>
          <Column header="Booking" style="min-width: 16rem">
            <template #body="{ data }">{{ (data.booking_codes || []).join(', ') || '-' }}</template>
          </Column>
          <Column header="Pembayaran" style="min-width: 10rem">
            <template #body="{ data }">{{ data.payment_count }} pembayaran</template>
          </Column>
          <Column header="Terakhir Bayar" style="min-width: 12rem">
            <template #body="{ data }">{{ formatDateTime(data.latest_paid_at) }}</template>
          </Column>
          <Column header="Total Terbayar" style="min-width: 12rem">
            <template #body="{ data }"><div class="amount-stack">{{ formatCurrency(data.total_amount) }}</div></template>
          </Column>
        </DataTable>
        <Paginator
          :rows="paymentHistoryPagination.groups.per_page"
          :totalRecords="paymentHistoryPagination.groups.total"
          :first="(paymentHistoryPagination.groups.current_page - 1) * paymentHistoryPagination.groups.per_page"
          template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
          currentPageReportTemplate="{first} - {last} dari {totalRecords}"
          class="history-paginator"
          @page="onGroupPaymentHistoryPage"
        />
        <div class="mobile-card-list rent-mobile-list">
          <article v-for="group in paymentHistory.groups" :key="group.bill_id" class="mobile-list-card">
            <div class="mobile-card-head">
              <div>
                <strong>{{ group.bill_number || '-' }}</strong>
                <p>{{ group.owner_name || '-' }}</p>
              </div>
              <span class="mobile-card-count">{{ group.payment_count }} bayar</span>
            </div>
            <div class="mobile-card-meta">
              <div><span>Booking</span><strong>{{ (group.booking_codes || []).join(', ') || '-' }}</strong></div>
              <div><span>Terakhir</span><strong>{{ formatDateTime(group.latest_paid_at) }}</strong></div>
            </div>
            <div class="mobile-card-amount">
              <div><span>Total Terbayar</span><strong>{{ formatCurrency(group.total_amount) }}</strong></div>
            </div>
          </article>
        </div>
      </section>
    </div>

    <Dialog v-model:visible="showGenerateDialog" header="Buat Dokumen Tagihan" modal :style="{ width: '480px' }" class="custom-dialog">
      <div class="dialog-stack">
        <div class="app-muted-panel">
          <div class="summary-row"><span>Pemilik rental</span><strong>{{ selectedOwner?.nama || '-' }}</strong></div>
          <div class="summary-row"><span>Jumlah transaksi</span><strong>{{ eligibleRows.length }}</strong></div>
          <div class="summary-row"><span>Booking</span><strong>{{ selectedBookingCodes || '-' }}</strong></div>
          <div class="summary-row"><span>Total tagihan</span><strong>{{ formatCurrency(selectedTotal) }}</strong></div>
        </div>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showGenerateDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading" @click="submitGenerateBill">Buat Dokumen</button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showDebtDialog" header="Detail Rent to Rent" modal :style="{ width: 'min(980px, 96vw)' }" class="custom-dialog">
      <div v-if="selectedDebt" class="detail-modal">
        <section class="detail-main">
          <div class="detail-top">
            <div>
              <span class="section-label">PEMILIK RENTAL</span>
              <h2>{{ selectedDebt.rental_owner?.nama || '-' }}</h2>
              <p>{{ selectedDebt.rental_owner?.kontak_1 || '-' }}</p>
            </div>
            <Tag :value="statusLabel(selectedDebt.status)" :severity="statusSeverity(selectedDebt.status)" />
          </div>
          <div class="info-grid">
            <div><span>Bank</span><strong>{{ selectedDebt.rental_owner?.bank || '-' }}</strong></div>
            <div><span>No. Rekening</span><strong>{{ selectedDebt.rental_owner?.no_rek || '-' }}</strong></div>
            <div><span>Atas Nama</span><strong>{{ selectedDebt.rental_owner?.atas_nama || '-' }}</strong></div>
          </div>
          <div class="info-grid">
            <div><span>Booking</span><strong>{{ selectedDebt.kode_booking }}</strong></div>
            <div><span>Pelanggan</span><strong>{{ selectedDebt.booking?.customer_name || '-' }}</strong></div>
            <div><span>Tujuan</span><strong>{{ selectedDebt.booking?.tujuan || '-' }}</strong></div>
          </div>
          <div class="info-grid">
            <div><span>Unit</span><strong>{{ selectedDebt.unit?.name || '-' }}</strong></div>
            <div><span>Nopol</span><strong>{{ selectedDebt.unit?.no_polisi || '-' }}</strong></div>
            <div><span>Periode</span><strong>{{ formatDate(selectedDebt.unit?.tgl_sewa) }} - {{ formatDate(selectedDebt.unit?.tgl_kembali) }}</strong></div>
          </div>
          <section class="history-panel">
            <div class="section-heading"><h2>Riwayat Bayar</h2></div>
            <DataTable :value="selectedDebt.payments" dataKey="id" responsiveLayout="scroll" class="mini-table">
              <Column header="Dokumen">
                <template #body="{ data }">{{ data.bill_number || '-' }}</template>
              </Column>
              <Column header="Akun">
                <template #body="{ data }">{{ data.payment_account_name || '-' }}</template>
              </Column>
              <Column header="Tanggal">
                <template #body="{ data }">{{ formatDateTime(data.paid_at) }}</template>
              </Column>
              <Column header="Nominal">
                <template #body="{ data }">{{ formatCurrency(data.amount) }}</template>
              </Column>
              <Column header="Status">
                <template #body="{ data }">
                  <Tag :value="paymentStatusLabel(data.status)" :severity="paymentStatusSeverity(data.status)" />
                </template>
              </Column>
              <Column header="Aksi">
                <template #body="{ data }">
                  <button class="btn-pill btn-danger btn-pill-compact" :disabled="actionLoading || ['voided', 'void_requested'].includes(data.status)" @click="submitVoidPayment(data)">
                    <i class="pi pi-ban"></i>
                    Void
                  </button>
                </template>
              </Column>
            </DataTable>
          </section>
        </section>
        <aside class="detail-side">
          <div class="payment-total-panel">
            <div class="payment-total-row"><span>Default Live</span><strong>{{ formatCurrency(selectedDebt.default_amount) }}</strong></div>
            <div v-if="selectedDebt.pricing_mode === 'all_in'" class="payment-total-row"><span>Harga Jual</span><strong>{{ formatCurrency(selectedDebt.selling_price) }}</strong></div>
            <div class="payment-total-row"><span>Total Tagihan</span><strong>{{ formatCurrency(selectedDebt.total_amount) }}</strong></div>
            <div class="payment-total-row"><span>Sudah Bayar</span><strong>{{ formatCurrency(selectedDebt.paid_amount) }}</strong></div>
            <div class="payment-total-row grand"><span>Sisa</span><strong>{{ formatCurrency(selectedDebt.remaining_amount) }}</strong></div>
          </div>
          <div class="payment-form-panel" style="display: flex; flex-direction: column; gap: 12px; padding: 12px;">
            <div class="section-label" style="margin-bottom: 4px;">Edit Harga Modal</div>
            
            <div v-if="selectedDebt.pending_amount_request" class="pending-request-banner" style="background-color: #FDF4D9; color: #8C660A; border: 1px solid #D4A017; border-radius: 6px; padding: 10px; font-size: 12px; display: flex; flex-direction: column; gap: 4px;">
              <div style="font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                <i class="pi pi-clock"></i> Menunggu ACC Supervisor
              </div>
              <div>
                <strong>Nominal Baru:</strong> {{ selectedDebt.pending_amount_request.requested_amount_override !== null ? formatCurrency(selectedDebt.pending_amount_request.requested_amount_override) : 'Reset ke Live (Default)' }}
              </div>
              <div style="word-break: break-all;">
                <strong>Alasan:</strong> {{ selectedDebt.pending_amount_request.reason }}
              </div>
              <div style="margin-top: 6px;">
                <button class="btn-pill btn-secondary btn-pill-compact" style="background-color: #ffffff; border: 1px solid #CDD2DF; color: #1A1D2E; font-size: 11px; padding: 4px 8px;" :disabled="actionLoading" @click="handleCancelAmountChange">
                  Batalkan Pengajuan
                </button>
              </div>
            </div>

            <fieldset class="form-fieldset" style="display: flex; flex-direction: column; gap: 4px; border: none; padding: 0; margin: 0; box-shadow: none;">
              <label>Nominal Manual</label>
              <InputNumber v-model="detailAmountForm.amount_override" mode="currency" currency="IDR" locale="id-ID" :min="0" class="w-full" :disabled="!selectedDebt.can_request_amount_change" />
            </fieldset>

            <fieldset class="form-fieldset" style="display: flex; flex-direction: column; gap: 4px; border: none; padding: 0; margin: 0; box-shadow: none;">
              <label>Alasan Perubahan (Min. 5 karakter)</label>
              <Textarea v-model="detailAmountForm.reason" rows="3" class="w-full" placeholder="Tuliskan alasan pengajuan..." :disabled="!selectedDebt.can_request_amount_change" style="resize: vertical; font-family: inherit; font-size: 12px;" />
              <span class="field-hint" style="font-size: 10px; color: var(--text-secondary); margin-top: 2px;">Pengajuan ini memerlukan persetujuan dari supervisor sebelum nominal baru diterapkan.</span>
            </fieldset>

            <div class="table-actions" style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 4px;">
              <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading || !selectedDebt.can_request_amount_change" @click="resetDebtAmount">Reset Live</button>
              <button class="btn-pill btn-primary btn-pill-compact" :disabled="actionLoading || !selectedDebt.can_request_amount_change" @click="submitDebtAmount">Simpan</button>
            </div>
          </div>
        </aside>
      </div>
    </Dialog>

    <Dialog v-model:visible="showPaymentDialog" header="Pembayaran Rent to Rent" modal :style="{ width: 'min(980px, 96vw)' }" class="custom-dialog">
      <div v-if="paymentTarget" class="payment-modal">
        <section class="detail-main">
          <div class="detail-top">
            <div>
              <span class="section-label">{{ selectedBill ? 'DOKUMEN TAGIHAN' : 'PEMBAYARAN LANGSUNG' }}</span>
              <h2>{{ selectedBill?.bill_number || selectedPaymentDebt?.kode_booking }}</h2>
              <p>{{ paymentTarget.rental_owner?.nama || '-' }}</p>
            </div>
            <Tag :value="statusLabel(paymentTarget.status)" :severity="statusSeverity(paymentTarget.status)" />
          </div>
          <DataTable :value="paymentPreviewItems" dataKey="id" responsiveLayout="scroll" class="mini-table">
            <Column header="Booking">
              <template #body="{ data }">{{ data.kode_booking }}</template>
            </Column>
            <Column header="Unit">
              <template #body="{ data }">{{ data.unit_name }} ({{ data.unit_plate || '-' }})</template>
            </Column>
            <Column header="Pelanggan">
              <template #body="{ data }">{{ data.customer_name || '-' }}</template>
            </Column>
            <Column header="Nominal">
              <template #body="{ data }">{{ formatCurrency(data.amount) }}</template>
            </Column>
          </DataTable>
        </section>
        <aside class="detail-side">
          <div class="payment-total-panel">
            <div v-if="paymentTarget.pricing_mode === 'all_in'" class="payment-total-row"><span>Harga Jual</span><strong>{{ formatCurrency(paymentTarget.selling_price) }}</strong></div>
            <div class="payment-total-row"><span>Total</span><strong>{{ formatCurrency(paymentTarget.total_amount) }}</strong></div>
            <div class="payment-total-row"><span>Sudah Bayar</span><strong>{{ formatCurrency(paymentTarget.paid_amount) }}</strong></div>
            <div class="payment-total-row grand"><span>Sisa</span><strong>{{ formatCurrency(paymentTarget.remaining_amount) }}</strong></div>
          </div>
          <div class="payment-form-panel">
            <div class="section-label">Catat Pembayaran</div>
            <fieldset class="form-fieldset">
              <label>Akun Pembayaran</label>
              <Dropdown v-model="paymentForm.payment_account_id" :options="paymentAccountOptions" optionLabel="label" optionValue="value" placeholder="Pilih akun" class="w-full" />
            </fieldset>
            <fieldset class="form-fieldset">
              <label>Nominal</label>
              <InputNumber v-model="paymentForm.amount" mode="currency" currency="IDR" locale="id-ID" :min="1" :max="paymentTarget.remaining_amount" class="w-full" />
            </fieldset>
            <fieldset class="form-fieldset">
              <label>Tanggal Bayar</label>
              <DatePicker v-model="paymentForm.paid_at" dateFormat="dd M yy" class="w-full" showIcon />
            </fieldset>
          </div>
        </aside>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showPaymentDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || !paymentForm.amount || !paymentForm.payment_account_id" @click="submitPayment">Simpan Pembayaran</button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showVoidDialog" header="Ajukan Void Tagihan" modal :style="{ width: '480px' }" class="custom-dialog">
      <div class="dialog-stack">
        <div class="app-muted-panel">
          <div class="summary-row"><span>Dokumen</span><strong>{{ voidForm.bill?.bill_number || '-' }}</strong></div>
          <div class="summary-row"><span>Pemilik rental</span><strong>{{ voidForm.bill?.rental_owner?.nama || '-' }}</strong></div>
          <div class="summary-row"><span>Total tagihan</span><strong>{{ formatCurrency(voidForm.bill?.total_amount) }}</strong></div>
          <div class="summary-row"><span>Sudah bayar</span><strong>{{ formatCurrency(voidForm.bill?.paid_amount) }}</strong></div>
        </div>
        <fieldset class="form-fieldset">
          <label>Alasan Void</label>
          <Textarea v-model="voidForm.void_reason" rows="4" class="w-full" placeholder="Tuliskan alasan untuk ACC supervisor" />
          <span class="field-hint">Jika disetujui, semua payment di dokumen ini ikut void dan transaksi kembali terbuka.</span>
        </fieldset>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showVoidDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || !voidForm.void_reason?.trim()" @click="submitVoidRequest">Ajukan Void</button>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.owner-section-head,
.section-heading,
.table-actions {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-md);
}

.table-actions {
  align-items: center;
  flex-wrap: wrap;
}

.summary-tile,
.owner-section,
.app-muted-panel,
.payment-total-panel,
.payment-form-panel,
.history-panel,
.form-fieldset {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.filter-group,
.form-fieldset,
.dialog-stack,
.booking-list,
.debt-groups,
.payment-history-stack,
.payment-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-fieldset label {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

.history-paginator {
  justify-content: flex-end;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
}

.filter-search {
  position: relative;
  display: block;
}

.filter-search > i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-tertiary);
}

.filter-search :deep(.p-inputtext) {
  padding-left: 34px;
}

.rent-list-tab {
  overflow: visible;
}

@media (min-width: 769px) {
  .table-page-active .rent-list-tab {
    overflow: hidden;
  }
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-md);
  margin-bottom: var(--space-lg);
}

.summary-tile {
  padding: var(--space-md);
}

.summary-tile span,
.owner-section-head p,
.owner-totals small,
.amount-stack span,
.field-hint,
.section-label {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

.summary-tile strong {
  display: block;
  margin-top: 6px;
  color: var(--text-primary);
  font-size: 18px;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
}

.owner-section {
  overflow: hidden;
}

.owner-section-head {
  align-items: center;
  padding: var(--space-md);
  border-bottom: 1px solid var(--surface-border);
}

.owner-section-head h2,
.section-heading h2,
.detail-top h2 {
  margin: 0;
  color: var(--text-primary);
  font-size: 16px;
  font-weight: 900;
}

.owner-section-head p,
.detail-top p {
  margin: 3px 0 0;
}

.owner-totals {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
  font-variant-numeric: tabular-nums;
}

.owner-totals span {
  color: var(--text-primary);
  font-size: 15px;
  font-weight: 900;
}

.mini-table {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  overflow: hidden;
  background: var(--surface-default);
}

.owner-section .drent-datatable {
  border: 0;
  border-radius: 0;
  box-shadow: none;
}

:deep(.drent-datatable .p-datatable-thead > tr > th),
:deep(.mini-table .p-datatable-thead > tr > th) {
  text-align: center;
}

:deep(.drent-datatable .p-datatable-thead > tr > th .p-column-header-content),
:deep(.mini-table .p-datatable-thead > tr > th .p-column-header-content) {
  justify-content: center;
}

.link-button {
  border: none;
  background: transparent;
  color: var(--text-primary);
  cursor: pointer;
  font-weight: 800;
  padding: 0;
}

.amount-stack {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  font-variant-numeric: tabular-nums;
}

.mobile-card-list {
  display: none;
}

.mobile-list-card {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
  padding: var(--space-md);
}

.mobile-card-head,
.mobile-card-meta,
.mobile-card-amount,
.mobile-card-actions {
  display: flex;
  gap: var(--space-md);
}

.mobile-card-head {
  align-items: flex-start;
  justify-content: space-between;
}

.mobile-card-head p {
  margin: 4px 0 0;
  color: var(--text-secondary);
  font-size: 12px;
}

.mobile-card-meta,
.mobile-card-amount {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin-top: var(--space-md);
}

.mobile-card-meta div,
.mobile-card-amount div {
  min-width: 0;
}

.mobile-card-meta span,
.mobile-card-amount span,
.mobile-card-meta small {
  display: block;
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

.mobile-card-meta strong,
.mobile-card-amount strong {
  display: block;
  margin-top: 4px;
  color: var(--text-primary);
  font-size: 12px;
  font-weight: 900;
  word-break: break-word;
}

.mobile-card-amount strong {
  font-variant-numeric: tabular-nums;
}

.mobile-card-actions {
  flex-wrap: wrap;
  margin-top: var(--space-md);
}

.mobile-card-count {
  flex: 0 0 auto;
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 800;
}

.btn-danger {
  border-color: #dc2626;
  background: #dc2626;
  color: #fff;
}

.empty-state {
  padding: var(--space-xl);
  border: 1px dashed var(--surface-border);
  border-radius: var(--radius-default);
  color: var(--text-secondary);
  text-align: center;
  font-size: 12px;
  font-weight: 700;
}

.app-muted-panel,
.payment-total-panel,
.payment-form-panel,
.history-panel,
.form-fieldset {
  background: var(--card-bg);
  padding: var(--space-md);
  box-shadow: none;
}

.summary-row,
.payment-total-row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-md);
}

.detail-modal,
.payment-modal {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: var(--space-lg);
  max-height: min(74vh, 760px);
  overflow: hidden;
}

.detail-main,
.detail-side {
  min-width: 0;
  overflow: auto;
}

.detail-main,
.detail-side {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.detail-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-lg);
  padding: var(--space-md);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1px;
  overflow: hidden;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-border);
}

.info-grid > div {
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: var(--surface-default);
  padding: var(--space-md);
}

.info-grid span {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

.info-grid strong {
  color: var(--text-primary);
  font-size: 12px;
}

.payment-total-row {
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid var(--surface-border);
  font-size: 12px;
}

.payment-total-row.grand {
  margin-top: 6px;
  border-bottom: 0;
  color: #fff;
  background: #E5534B;
  border-radius: var(--radius-xs);
  padding: 12px;
}

:deep(.custom-dialog .p-dialog-footer) {
  border-top: 1px solid var(--surface-border);
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

@media (max-width: 768px) {
  .owner-section-head {
    flex-direction: column;
    align-items: stretch;
  }

  .summary-grid,
  .detail-modal,
  .payment-modal,
  .info-grid {
    grid-template-columns: 1fr;
  }

  .detail-modal,
  .payment-modal {
    max-height: 78vh;
    overflow: auto;
  }

  .detail-main,
  .detail-side {
    overflow: visible;
  }

  .desktop-table {
    display: none;
  }

  .mobile-card-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
  }

  .owner-section .mobile-card-list {
    padding: var(--space-md);
  }

  .mobile-card-meta,
  .mobile-card-amount {
    grid-template-columns: 1fr;
  }

  .mobile-card-actions .btn-pill {
    flex: 1 1 calc(50% - var(--space-sm));
    justify-content: center;
  }

  .header-actions .btn-pill,
  .filter-actions .btn-pill,
  .table-actions .btn-pill {
    width: 100%;
    justify-content: center;
  }
}

.warning-btn {
  background-color: #FDF4D9 !important;
  color: #8C660A !important;
  border-color: #D4A017 !important;
}
.warning-btn:hover {
  background-color: #fdf0c3 !important;
}
</style>
