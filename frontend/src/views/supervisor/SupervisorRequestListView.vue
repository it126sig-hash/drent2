<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { format } from 'date-fns'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import ProgressBar from 'primevue/progressbar'
import Textarea from 'primevue/textarea'
import rentToRentApi from '../../api/rentToRent'
import { getSupervisorRequests } from '../../api/supervisorRequest'
import { useBooking } from '../../composables/useBooking'
import { useOperationalFund } from '../../composables/useOperationalFund'

const router = useRouter()
const {
  loading: actionLoading,
  approveVoidPayment,
  rejectVoidPayment,
  approveReturnToRentalUnit,
  rejectReturnToRentalUnit,
  approveRevertOperational,
  rejectRevertOperational,
} = useBooking()

const {
  approveVoidExpense,
  rejectVoidExpense,
} = useOperationalFund()

const requests = ref([])
const loading = ref(false)
const activeTab = ref('all')
const activeStatus = ref('pending')
const selectedRequest = ref(null)
const showRejectDialog = ref(false)
const rejectionNote = ref('')
const isMobile = ref(window.innerWidth < 768)

const requestTabs = [
  { label: 'Semua', value: 'all' },
  { label: 'Void Pembayaran', value: 'void_payment' },
  { label: 'Void Rent to Rent', value: 'rent_to_rent_void_bill' },
  { label: 'Void Bayar R2R', value: 'rent_to_rent_void_payment' },
  { label: 'Void Bon / Expense', value: 'void_operational_expense' },
  { label: 'Kembali Rental Unit', value: 'return_rental_unit' },
  { label: 'Revert Operasional', value: 'operational_revert' },
  { label: 'Ubah Nominal R2R', value: 'rent_to_rent_amount_change' },
]

const statusTabs = [
  { label: 'Menunggu ACC', value: 'pending' },
  { label: 'Sudah ACC', value: 'approved' },
  { label: 'Semua Status', value: 'all' },
]

const filteredRequests = computed(() => {
  return requests.value.filter(request => {
    const matchesType = activeTab.value === 'all' || request.type === activeTab.value
    const matchesStatus = activeStatus.value === 'all' || request.status === activeStatus.value
    return matchesType && matchesStatus
  })
})

const pendingCount = computed(() => requests.value.filter(request => request.status === 'pending').length)

const approvedCount = computed(() => requests.value.filter(request => request.status === 'approved').length)

const resultSummary = computed(() => `${filteredRequests.value.length} request ditampilkan`)

const handleResize = () => {
  isMobile.value = window.innerWidth < 768
}

const fetchRequests = async () => {
  loading.value = true
  try {
    const response = await getSupervisorRequests()
    requests.value = response.data.data || []
  } finally {
    loading.value = false
  }
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)
}

const formatDateTime = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy HH:mm')
}

const statusTone = (status) => status === 'approved' ? 'success' : 'warning'

const requestTone = (type) => ['void_payment', 'rent_to_rent_void_bill', 'rent_to_rent_void_payment', 'void_operational_expense', 'rent_to_rent_amount_change'].includes(type) ? 'warning' : 'info'

const bookingCode = (request) => request.bill?.bill_number || request.booking?.kode_booking || '-'

const customerName = (request) => request.booking?.customer_name || '-'

const requestDetailLabel = (request) => {
  if (request.type === 'void_payment') {
    return `${formatCurrency(request.payment?.amount)} - ${request.payment?.payment_type || '-'}`
  }

  if (request.type === 'rent_to_rent_void_bill') {
    return `${formatCurrency(request.bill?.total_amount)} - sudah bayar ${formatCurrency(request.bill?.paid_amount)}`
  }

  if (request.type === 'rent_to_rent_void_payment') {
    return `${formatCurrency(request.payment?.amount)} - pembayaran rent-to-rent`
  }

  if (request.type === 'void_operational_expense') {
    return `Void Bon: ${formatCurrency(request.payment?.amount)} - ${request.payment?.payment_type || '-'}`
  }

  if (request.type === 'operational_revert') {
    return 'Aktifkan kembali operasional (kembali ke operasional aktif)'
  }

  if (request.type === 'rent_to_rent_amount_change') {
    return `Ubah Nominal: ${formatCurrency(request.debt?.current_amount)} -> ${request.debt?.requested_amount !== null ? formatCurrency(request.debt?.requested_amount) : 'Reset Live (Default)'}`
  }

  return 'Ubah status booking dari selesai ke rental_unit'
}

const requestSecondaryDetail = (request) => {
  if (request.type === 'void_payment') {
    const account = request.payment?.payment_account
    return `${account?.nama_bank || '-'} ${account?.nomor_rekening || ''}`.trim()
  }

  if (request.type === 'rent_to_rent_void_bill') {
    return (request.bill?.booking_codes || []).join(', ') || '-'
  }

  if (request.type === 'rent_to_rent_void_payment') {
    const account = request.payment?.payment_account
    return `${account?.nama_bank || '-'} ${account?.nomor_rekening || ''}`.trim()
  }

  if (request.type === 'void_operational_expense') {
    return request.booking?.kode_booking || '-'
  }

  if (request.type === 'rent_to_rent_amount_change') {
    return `Hutang R2R: ${request.debt?.kode_booking || '-'}`
  }

  return request.booking?.kode_booking || '-'
}

const approveRequest = async (request) => {
  if (request.type === 'void_payment') {
    await approveVoidPayment(request.payment.id)
  } else if (request.type === 'rent_to_rent_void_bill') {
    await rentToRentApi.approveVoidRentToRentBill(request.bill.id)
  } else if (request.type === 'rent_to_rent_void_payment') {
    await rentToRentApi.approveVoidRentToRentPayment(request.payment.id)
  } else if (request.type === 'void_operational_expense') {
    await approveVoidExpense(request.payment.id)
  } else if (request.type === 'operational_revert') {
    await approveRevertOperational(request.booking.id)
  } else if (request.type === 'rent_to_rent_amount_change') {
    await rentToRentApi.approveRentToRentAmountChange(request.amount_change.id)
  } else {
    await approveReturnToRentalUnit(request.booking.id)
  }

  await fetchRequests()
}

const openRejectDialog = (request) => {
  selectedRequest.value = request
  rejectionNote.value = ''
  showRejectDialog.value = true
}

const submitReject = async () => {
  if (!selectedRequest.value) return

  if (selectedRequest.value.type === 'void_payment') {
    await rejectVoidPayment(selectedRequest.value.payment.id, {
      void_rejection_note: rejectionNote.value,
    })
  } else if (selectedRequest.value.type === 'rent_to_rent_void_bill') {
    await rentToRentApi.rejectVoidRentToRentBill(selectedRequest.value.bill.id, {
      void_rejection_note: rejectionNote.value,
    })
  } else if (selectedRequest.value.type === 'rent_to_rent_void_payment') {
    await rentToRentApi.rejectVoidRentToRentPayment(selectedRequest.value.payment.id, {
      void_rejection_note: rejectionNote.value,
    })
  } else if (selectedRequest.value.type === 'void_operational_expense') {
    await rejectVoidExpense(selectedRequest.value.payment.id, rejectionNote.value)
  } else if (selectedRequest.value.type === 'operational_revert') {
    await rejectRevertOperational(selectedRequest.value.booking.id, {
      rejection_note: rejectionNote.value,
    })
  } else if (selectedRequest.value.type === 'rent_to_rent_amount_change') {
    await rentToRentApi.rejectRentToRentAmountChange(selectedRequest.value.amount_change.id, rejectionNote.value)
  } else {
    await rejectReturnToRentalUnit(selectedRequest.value.booking.id, {
      rejection_note: rejectionNote.value,
    })
  }

  showRejectDialog.value = false
  selectedRequest.value = null
  await fetchRequests()
}

onMounted(() => {
  fetchRequests()
  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
})
</script>

<template>
  <div class="page-container supervisor-page table-page-active">
    <div class="page-header">
      <div class="header-left">
        <h1 class="text-h1">Request Supervisor</h1>
        <p class="text-secondary text-xs">Approval untuk void pembayaran dan pengembalian booking selesai ke Rental
          Unit.</p>
      </div>
      <div class="header-actions">


        <div class="tab-toggle-container">
          <div class="pill-toggle status-toggle">
            <button v-for="tab in statusTabs" :key="tab.value" class="toggle-item"
              :class="{ active: activeStatus === tab.value }" @click="activeStatus = tab.value">
              {{ tab.label }}
            </button>
          </div>
        </div>

        <button class="btn-pill btn-secondary" :disabled="loading" @click="fetchRequests">
          <i class="pi pi-refresh"></i>
          Refresh
        </button>
      </div>
    </div>

    <div class="filter-bar surface-card flex flex-col md:flex-row md:items-center justify-between gap-4 p-3 border border-[var(--surface-border)] rounded-[10px] shadow-[var(--shadow-tile)]">
      <div class="w-full md:w-auto">
        <Dropdown v-model="activeTab" :options="requestTabs" optionLabel="label" optionValue="value"
          placeholder="Tipe Request" class="w-full md:w-56" />
      </div>

      <div class="filter-actions summary-actions flex items-center gap-2 flex-wrap w-full md:w-auto justify-between md:justify-end">
        <span class="summary-chip warning">{{ pendingCount }} menunggu</span>
        <span class="summary-chip success">{{ approvedCount }} sudah ACC</span>
        <span class="summary-chip neutral">{{ resultSummary }}</span>
      </div>
    </div>

    <ProgressBar v-if="loading" mode="indeterminate" style="height: 4px" class="mb-4" />

    <div v-if="!isMobile" class="table-shell supervisor-table-shell">
      <DataTable :value="filteredRequests" dataKey="id" :loading="loading" scrollable scrollHeight="flex"
        responsiveLayout="scroll" class="drent-datatable" emptyMessage="Tidak ada request.">
        <Column header="Aksi" frozen style="min-width: 13rem">
          <template #body="{ data }">
            <div v-if="data.status === 'pending'" class="action-pill-group">
              <button class="action-btn action-btn-primary" :disabled="actionLoading" title="Setujui request"
                @click="approveRequest(data)">
                <i class="pi pi-check"></i>
              </button>
              <button class="action-btn" :disabled="actionLoading" title="Tolak request"
                @click="openRejectDialog(data)">
                <i class="pi pi-times"></i>
              </button>
            </div>
            <span v-else class="status-badge success">Sudah disetujui</span>
          </template>
        </Column>
        <Column header="Request" style="min-width: 15rem">
          <template #body="{ data }">
            <div class="tag-stack">
              <span class="status-badge" :class="requestTone(data.type)">{{ data.type_label }}</span>
              <span class="status-badge" :class="statusTone(data.status)">{{ data.status_label }}</span>
            </div>
            <div class="text-xs text-secondary mt-2">{{ formatDateTime(data.requested_at) }}</div>
            <div class="text-xs text-tertiary">oleh {{ data.requester?.name || '-' }}</div>
            <div v-if="data.status === 'approved'" class="approval-note">
              ACC {{ formatDateTime(data.approved_at) }} oleh {{ data.approver?.name || '-' }}
            </div>
          </template>
        </Column>
        <Column header="Booking" style="min-width: 13rem">
          <template #body="{ data }">
            <button v-if="data.booking?.id" class="link-button" @click="router.push(`/bookings/${data.booking.id}`)">{{
              data.booking.kode_booking }}</button>
            <strong v-else>{{ bookingCode(data) }}</strong>
            <div class="text-xs text-secondary mt-1">{{ customerName(data) }}</div>
          </template>
        </Column>
        <Column header="Detail" style="min-width: 17rem">
          <template #body="{ data }">
            <div class="detail-stack">
              <strong>{{ requestDetailLabel(data) }}</strong>
              <span>{{ requestSecondaryDetail(data) }}</span>
            </div>
          </template>
        </Column>
        <Column header="Alasan" style="min-width: 20rem">
          <template #body="{ data }">
            <span class="reason-text">{{ data.reason || '-' }}</span>
          </template>
        </Column>
      </DataTable>
    </div>

    <div v-else class="mobile-card-list">
      <div v-if="loading" class="app-muted-panel mobile-state">
        <i class="pi pi-spin pi-spinner"></i>
        <span>Memuat request supervisor...</span>
      </div>

      <div v-else-if="!filteredRequests.length" class="app-muted-panel mobile-state">
        <i class="pi pi-info-circle"></i>
        <span>Tidak ada request.</span>
      </div>

      <template v-else>
        <article v-for="request in filteredRequests" :key="request.id" class="request-card">
          <div class="card-header">
            <div class="card-title-stack">
              <button v-if="request.booking?.id" class="booking-code"
                @click="router.push(`/bookings/${request.booking.id}`)">
                {{ request.booking.kode_booking }}
              </button>
              <strong v-else class="booking-code static-code">{{ bookingCode(request) }}</strong>
              <span>{{ customerName(request) }}</span>
            </div>
            <span class="status-badge" :class="statusTone(request.status)">{{ request.status_label }}</span>
          </div>

          <div class="tag-stack">
            <span class="status-badge" :class="requestTone(request.type)">{{ request.type_label }}</span>
            <span class="request-date">{{ formatDateTime(request.requested_at) }}</span>
          </div>

          <div class="mobile-info-grid">
            <div class="info-col">
              <span class="label">Detail</span>
              <strong class="value">{{ requestDetailLabel(request) }}</strong>
              <span class="text-secondary text-xs">{{ requestSecondaryDetail(request) }}</span>
            </div>
            <div class="info-col">
              <span class="label">Requester</span>
              <strong class="value">{{ request.requester?.name || '-' }}</strong>
            </div>
            <div class="info-col">
              <span class="label">Alasan</span>
              <span class="value reason-text">{{ request.reason || '-' }}</span>
            </div>
          </div>

          <div v-if="request.status === 'approved'" class="app-muted-panel approval-panel">
            ACC {{ formatDateTime(request.approved_at) }} oleh {{ request.approver?.name || '-' }}
          </div>

          <div v-if="request.status === 'pending'" class="card-actions">
            <button class="btn-pill btn-primary" :disabled="actionLoading" @click="approveRequest(request)">
              <i class="pi pi-check"></i>
              Setujui
            </button>
            <button class="btn-pill btn-secondary" :disabled="actionLoading" @click="openRejectDialog(request)">
              <i class="pi pi-times"></i>
              Tolak
            </button>
          </div>
        </article>
      </template>
    </div>

    <Dialog v-model:visible="showRejectDialog" header="Tolak Request" modal class="custom-dialog"
      :style="{ width: '450px' }">
      <div class="dialog-stack">
        <div class="app-muted-panel">
          <span class="text-xs text-tertiary">Request</span>
          <strong>{{ selectedRequest?.type_label }}</strong>
          <span>{{ bookingCode(selectedRequest || {}) }}</span>
        </div>
        <div class="form-fieldset">
          <label>Catatan Penolakan</label>
          <Textarea v-model="rejectionNote" rows="4" class="w-full" />
        </div>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showRejectDialog = false">
          <i class="pi pi-times"></i>
          Batal
        </button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading" @click="submitReject">
          <i class="pi pi-check"></i>
          Simpan
        </button>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page-container {
  padding: var(--space-2xl);
}

.supervisor-page {
  background: var(--page-bg);
}

.filter-bar {
  margin-bottom: var(--space-lg);
}

.filter-label {
  color: var(--text-tertiary);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}

.status-toggle {
  max-width: 100%;
  overflow-x: auto;
}

.summary-actions {
  align-items: center;
}

.summary-chip {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  padding: 5px 10px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-full);
  background: var(--surface-default);
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 800;
  white-space: nowrap;
}

.summary-chip.warning {
  border-color: color-mix(in srgb, var(--warning) 34%, var(--surface-border));
  color: var(--warning);
}

.summary-chip.success {
  border-color: color-mix(in srgb, var(--positive) 34%, var(--surface-border));
  color: var(--positive);
}

.table-shell {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
  overflow: hidden;
}

.supervisor-table-shell {
  min-height: 0;
}

:deep(.drent-datatable .p-datatable-thead > tr > th) {
  background: var(--card-bg);
  color: var(--text-secondary);
  font-size: 11px;
  text-transform: uppercase;
}

.link-button {
  border: none;
  background: transparent;
  color: var(--text-primary);
  cursor: pointer;
  font-weight: 700;
  padding: 0;
  text-align: left;
  overflow-wrap: anywhere;
}

.action-pill-group {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.action-btn {
  width: 32px;
  height: 32px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-full);
  background: var(--surface-default);
  color: var(--text-secondary);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.action-btn-primary {
  background: var(--text-primary);
  border-color: var(--text-primary);
  color: #fff;
}

.action-btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.tag-stack {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.detail-stack,
.dialog-stack {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.detail-stack strong {
  color: var(--text-primary);
  font-size: 12px;
  line-height: 1.35;
}

.detail-stack span {
  color: var(--text-secondary);
  font-size: 11px;
  line-height: 1.4;
}

.approval-note {
  color: var(--positive);
  font-size: 11px;
  font-weight: 700;
  margin-top: 4px;
}

.reason-text {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.4;
}

.mobile-card-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.request-card,
.app-muted-panel,
.form-fieldset {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.request-card {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding: var(--space-lg);
}

.app-muted-panel,
.form-fieldset {
  padding: var(--space-md);
}

.form-fieldset {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-fieldset label {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-md);
  padding-bottom: var(--space-sm);
  border-bottom: 1px solid var(--surface-border);
}

.card-title-stack {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.card-title-stack span,
.request-date {
  color: var(--text-secondary);
  font-size: 12px;
}

.booking-code {
  min-width: 0;
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--text-primary);
  cursor: pointer;
  font-family: var(--font-headline);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.3;
  text-align: left;
  overflow-wrap: anywhere;
}

.static-code {
  cursor: default;
}

.mobile-info-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-sm);
  padding-top: var(--space-sm);
  border-top: 1px solid var(--surface-border);
}

.info-col {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.label {
  color: var(--text-tertiary);
  font-size: 10px;
  font-weight: 800;
  line-height: 1.2;
  text-transform: uppercase;
}

.value {
  color: var(--text-primary);
  font-size: 12px;
  font-weight: 700;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.approval-panel {
  color: var(--positive);
  font-size: 11px;
  font-weight: 800;
}

.card-actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-sm);
}

.mobile-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  padding: var(--space-xl);
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 700;
}

@media (max-width: 768px) {
  .page-container {
    padding: var(--space-lg);
  }

  .header-actions {
    align-items: stretch;
  }

  .header-actions .btn-pill {
    width: 100%;
  }

  .pill-toggle {
    width: 100%;
    overflow-x: auto;
  }

  .toggle-item {
    flex: 1 0 auto;
    white-space: nowrap;
  }

  .summary-actions {
    justify-content: flex-start;
    width: 100%;
  }

  .card-actions .btn-pill {
    width: 100%;
  }
}

@media (min-width: 769px) {
  .supervisor-page {
    height: 100dvh;
    overflow: hidden;
  }
}
</style>
