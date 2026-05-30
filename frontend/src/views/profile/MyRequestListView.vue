<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { format } from 'date-fns'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dropdown from 'primevue/dropdown'
import ProgressBar from 'primevue/progressbar'
import { getMyRequests } from '../../api/myRequest'

const router = useRouter()

const requests = ref([])
const loading = ref(false)
const activeTab = ref('all')
const activeStatus = ref('all')
const isMobile = ref(window.innerWidth < 768)

const requestTabs = [
  { label: 'Semua Tipe', value: 'all' },
  { label: 'Void Pembayaran', value: 'void_payment' },
  { label: 'Void Rent to Rent', value: 'rent_to_rent_void_bill' },
  { label: 'Void Bayar R2R', value: 'rent_to_rent_void_payment' },
  { label: 'Void Bon / Expense', value: 'void_operational_expense' },
  { label: 'Kembali Rental Unit', value: 'return_rental_unit' },
  { label: 'Revert Operasional', value: 'operational_revert' },
  { label: 'Ubah Nominal R2R', value: 'rent_to_rent_amount_change' },
]

const statusTabs = [
  { label: 'Semua Status', value: 'all' },
  { label: 'Menunggu ACC', value: 'pending' },
  { label: 'Disetujui', value: 'approved' },
  { label: 'Ditolak', value: 'rejected' },
]

const filteredRequests = computed(() => {
  return requests.value.filter((request) => {
    const matchesType = activeTab.value === 'all' || request.type === activeTab.value
    const matchesStatus = activeStatus.value === 'all' || request.status === activeStatus.value
    return matchesType && matchesStatus
  })
})

const pendingCount = computed(() => requests.value.filter((r) => r.status === 'pending').length)
const approvedCount = computed(() => requests.value.filter((r) => r.status === 'approved').length)
const rejectedCount = computed(() => requests.value.filter((r) => r.status === 'rejected').length)

const resultSummary = computed(() => `${filteredRequests.value.length} request ditampilkan`)

const handleResize = () => {
  isMobile.value = window.innerWidth < 768
}

const fetchRequests = async () => {
  loading.value = true
  try {
    const response = await getMyRequests()
    requests.value = response.data.data || []
  } finally {
    loading.value = false
  }
}

const formatDateTime = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy HH:mm')
}

const statusTone = (status) => {
  if (status === 'approved') return 'success'
  if (status === 'rejected') return 'danger'
  return 'warning'
}

const requestTone = (type) =>
  ['void_payment', 'rent_to_rent_void_bill', 'rent_to_rent_void_payment', 'void_operational_expense', 'rent_to_rent_amount_change'].includes(type)
    ? 'warning'
    : 'info'

const bookingCode = (request) => request?.booking?.kode_booking || '-'
const customerName = (request) => request?.booking?.customer_name || '-'

const reviewerLabel = (request) => {
  if (!request?.reviewer?.name) return '-'
  if (request.status === 'approved') return `Disetujui oleh ${request.reviewer.name}`
  if (request.status === 'rejected') return `Ditolak oleh ${request.reviewer.name}`
  return request.reviewer.name
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
  <div class="page-container my-request-page table-page-active">
    <div class="page-header">
      <div class="header-left">
        <h1 class="text-h1">Riwayat Request Saya</h1>
        <p class="text-secondary text-xs">
          Daftar request perubahan yang Anda kirim ke supervisor beserta statusnya.
        </p>
      </div>
      <div class="header-actions">
        <div class="tab-toggle-container">
          <div class="pill-toggle status-toggle">
            <button
              v-for="tab in statusTabs"
              :key="tab.value"
              class="toggle-item"
              :class="{ active: activeStatus === tab.value }"
              @click="activeStatus = tab.value"
            >
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

    <div
      class="filter-bar surface-card flex flex-col md:flex-row md:items-center justify-between gap-4 p-3 border border-[var(--surface-border)] rounded-[10px] shadow-[var(--shadow-tile)]"
    >
      <div class="w-full md:w-auto">
        <Dropdown
          v-model="activeTab"
          :options="requestTabs"
          optionLabel="label"
          optionValue="value"
          placeholder="Tipe Request"
          class="w-full md:w-56"
        />
      </div>

      <div
        class="filter-actions summary-actions flex items-center gap-2 flex-wrap w-full md:w-auto justify-between md:justify-end"
      >
        <span class="summary-chip warning">{{ pendingCount }} menunggu</span>
        <span class="summary-chip success">{{ approvedCount }} disetujui</span>
        <span class="summary-chip danger">{{ rejectedCount }} ditolak</span>
        <span class="summary-chip neutral">{{ resultSummary }}</span>
      </div>
    </div>

    <ProgressBar v-if="loading" mode="indeterminate" style="height: 4px" class="mb-4" />

    <div v-if="!isMobile" class="table-shell my-request-table-shell">
      <DataTable
        :value="filteredRequests"
        dataKey="id"
        :loading="loading"
        scrollable
        scrollHeight="flex"
        responsiveLayout="scroll"
        class="drent-datatable"
        emptyMessage="Belum ada request."
      >
        <Column header="Status" style="min-width: 11rem">
          <template #body="{ data }">
            <span class="status-badge" :class="statusTone(data.status)">{{ data.status_label }}</span>
            <div class="text-xs text-secondary mt-2">{{ formatDateTime(data.requested_at) }}</div>
            <div v-if="data.reviewed_at" class="text-xs text-tertiary">
              Direview {{ formatDateTime(data.reviewed_at) }}
            </div>
          </template>
        </Column>
        <Column header="Tipe Request" style="min-width: 13rem">
          <template #body="{ data }">
            <span class="status-badge" :class="requestTone(data.type)">{{ data.type_label }}</span>
            <div class="text-xs text-secondary mt-2">{{ reviewerLabel(data) }}</div>
          </template>
        </Column>
        <Column header="Booking" style="min-width: 13rem">
          <template #body="{ data }">
            <button
              v-if="data.booking?.id"
              class="link-button"
              @click="router.push(`/bookings/${data.booking.id}`)"
            >
              {{ data.booking.kode_booking }}
            </button>
            <strong v-else>{{ bookingCode(data) }}</strong>
            <div class="text-xs text-secondary mt-1">{{ customerName(data) }}</div>
          </template>
        </Column>
        <Column header="Detail" style="min-width: 17rem">
          <template #body="{ data }">
            <div class="detail-stack">
              <strong>{{ data.detail_primary || '-' }}</strong>
              <span>{{ data.detail_secondary || '-' }}</span>
            </div>
          </template>
        </Column>
        <Column header="Alasan" style="min-width: 16rem">
          <template #body="{ data }">
            <span class="reason-text">{{ data.reason || '-' }}</span>
          </template>
        </Column>
        <Column header="Catatan Penolakan" style="min-width: 16rem">
          <template #body="{ data }">
            <span v-if="data.status === 'rejected'" class="reason-text rejection-text">
              {{ data.rejection_note || '-' }}
            </span>
            <span v-else class="text-tertiary">-</span>
          </template>
        </Column>
      </DataTable>
    </div>

    <div v-else class="mobile-card-list">
      <div v-if="loading" class="app-muted-panel mobile-state">
        <i class="pi pi-spin pi-spinner"></i>
        <span>Memuat riwayat request...</span>
      </div>

      <div v-else-if="!filteredRequests.length" class="app-muted-panel mobile-state">
        <i class="pi pi-info-circle"></i>
        <span>Belum ada request.</span>
      </div>

      <template v-else>
        <article v-for="request in filteredRequests" :key="request.id" class="request-card">
          <div class="card-header">
            <div class="card-title-stack">
              <button
                v-if="request.booking?.id"
                class="booking-code"
                @click="router.push(`/bookings/${request.booking.id}`)"
              >
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
              <strong class="value">{{ request.detail_primary || '-' }}</strong>
              <span class="text-secondary text-xs">{{ request.detail_secondary || '-' }}</span>
            </div>
            <div class="info-col">
              <span class="label">Alasan</span>
              <span class="value reason-text">{{ request.reason || '-' }}</span>
            </div>
            <div v-if="request.status === 'rejected'" class="info-col">
              <span class="label">Catatan Penolakan</span>
              <span class="value reason-text rejection-text">{{ request.rejection_note || '-' }}</span>
            </div>
            <div v-if="request.reviewer?.name" class="info-col">
              <span class="label">{{ request.status === 'rejected' ? 'Ditolak oleh' : 'Disetujui oleh' }}</span>
              <strong class="value">{{ request.reviewer.name }}</strong>
              <span v-if="request.reviewed_at" class="text-secondary text-xs">
                {{ formatDateTime(request.reviewed_at) }}
              </span>
            </div>
          </div>
        </article>
      </template>
    </div>
  </div>
</template>

<style scoped>
.page-container {
  padding: var(--space-2xl);
}

.my-request-page {
  background: var(--page-bg);
}

.filter-bar {
  margin-bottom: var(--space-lg);
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

.summary-chip.danger {
  border-color: color-mix(in srgb, var(--danger) 34%, var(--surface-border));
  color: var(--danger);
}

.table-shell {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
  overflow: hidden;
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

.tag-stack {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.detail-stack {
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

.reason-text {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.4;
}

.rejection-text {
  color: var(--danger);
  font-weight: 700;
}

.mobile-card-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.request-card,
.app-muted-panel {
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

.app-muted-panel {
  padding: var(--space-md);
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
}

@media (min-width: 769px) {
  .my-request-page {
    height: 100dvh;
    overflow: hidden;
  }
}
</style>
