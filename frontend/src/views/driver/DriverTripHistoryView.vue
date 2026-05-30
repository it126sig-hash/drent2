<script setup>
import { computed, onMounted, ref } from 'vue'
import { format } from 'date-fns'
import Dialog from 'primevue/dialog'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import { useOperationalFund } from '../../composables/useOperationalFund'

const {
  funds,
  schedules,
  loading,
  fetchDriverFunds,
  fetchDriverSchedules,
} = useOperationalFund()

const showDetailDialog = ref(false)
const selectedTrip = ref(null)

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

const fundStatusSeverity = (status) => {
  if (status === 'accepted') return 'success'
  if (status === 'closed') return 'info'
  if (status === 'cancelled') return 'danger'
  return 'warn'
}

const expenseStatusSeverity = (status) => {
  if (status === 'approved') return 'success'
  if (status === 'rejected') return 'danger'
  return 'warn'
}

const unitLabel = (source) => {
  const unit = source?.booking_detail?.unit || source?.unit
  if (!unit) return '-'
  const merkTipe = [unit.merk, unit.tipe].filter(Boolean).join(' ')
  return merkTipe || '-'
}

// Trips combine: past schedules (any tgl_kembali < now)
// Each trip is keyed by booking_detail id and includes the matching fund if any.
const fundByBookingId = computed(() => {
  const map = new Map()
  funds.value.forEach(fund => {
    if (fund.booking_id) {
      const list = map.get(fund.booking_id) || []
      list.push(fund)
      map.set(fund.booking_id, list)
    }
  })
  return map
})

const trips = computed(() => {
  const now = new Date()
  return schedules.value
    .filter(item => new Date(item.tgl_kembali) < now)
    .map(schedule => {
      const matchingFunds = fundByBookingId.value.get(schedule.booking_id) || []
      // Pilih fund yang berasosiasi dengan booking_detail ini jika ada,
      // jika tidak, ambil fund pertama yang matching booking.
      const fund = matchingFunds.find(f => f.booking_detail_id === schedule.id) || matchingFunds[0] || null
      return {
        ...schedule,
        fund,
      }
    })
    .sort((a, b) => new Date(b.tgl_sewa) - new Date(a.tgl_sewa))
})

// Closed/cancelled funds yang belum punya schedule (defensive: jaga agar tetap muncul).
const orphanFunds = computed(() => {
  const usedFundIds = new Set(
    trips.value.map(trip => trip.fund?.id).filter(Boolean)
  )
  return funds.value
    .filter(fund => (fund.status === 'closed' || fund.status === 'cancelled') && !usedFundIds.has(fund.id))
    .map(fund => ({
      id: `fund-${fund.id}`,
      booking_id: fund.booking_id,
      tgl_sewa: fund.booking_detail?.tgl_sewa || fund.paid_at,
      tgl_kembali: fund.booking_detail?.tgl_kembali || fund.closed_at || fund.paid_at,
      booking: fund.booking,
      unit: fund.booking_detail?.unit,
      fund,
      isOrphan: true,
    }))
})

const allTrips = computed(() => [...trips.value, ...orphanFunds.value])

const tripStatusLabel = (trip) => {
  if (trip.fund) return trip.fund.status
  return 'past'
}

const tripStatusSeverity = (trip) => {
  if (!trip.fund) return 'secondary'
  return fundStatusSeverity(trip.fund.status)
}

const reload = async () => {
  await Promise.all([
    fetchDriverFunds(1),
    fetchDriverSchedules(),
  ])
}

const openTrip = (trip) => {
  selectedTrip.value = trip
  showDetailDialog.value = true
}

onMounted(async () => {
  await reload()
})
</script>

<template>
  <div class="driver-page">
    <header class="driver-header">
      <div>
        <h1 class="text-h1">Riwayat Jalan</h1>
        <p class="text-secondary text-xs">Daftar perjalanan dan transaksi operasional yang sudah selesai.</p>
      </div>
      <button class="btn-pill btn-secondary btn-pill-compact" :disabled="loading" @click="reload">
        <i class="pi pi-refresh"></i>
        Refresh
      </button>
    </header>

    <ProgressBar v-if="loading" mode="indeterminate" style="height: 4px" />

    <section class="card-stack">
      <article
        v-for="trip in allTrips"
        :key="trip.id"
        class="app-card trip-card"
        @click="openTrip(trip)"
      >
        <div class="card-top">
          <div class="trip-headline">
            <strong>{{ trip.booking?.kode_booking || '-' }}</strong>
            <p>{{ trip.booking?.customer?.nama || '-' }}</p>
          </div>
          <Tag :value="tripStatusLabel(trip)" :severity="tripStatusSeverity(trip)" />
        </div>
        <div class="trip-info">
          <div class="trip-info-row">
            <i class="pi pi-car"></i>
            <span>{{ unitLabel(trip) }} <span v-if="(trip.unit?.no_polisi || trip.booking_detail?.unit?.no_polisi)" class="trip-nopol">({{ trip.unit?.no_polisi || trip.booking_detail?.unit?.no_polisi }})</span></span>
          </div>
          <div class="trip-info-row">
            <i class="pi pi-calendar"></i>
            <span>{{ formatDate(trip.tgl_sewa) }} &mdash; {{ formatDate(trip.tgl_kembali) }}</span>
          </div>
          <div class="trip-info-row">
            <i class="pi pi-map-marker"></i>
            <span>{{ trip.booking?.tujuan || '-' }}</span>
          </div>
          <div v-if="trip.fund" class="trip-info-row">
            <i class="pi pi-wallet"></i>
            <span>{{ formatCurrency(trip.fund.amount) }} &middot; sisa {{ formatCurrency(trip.fund.summary?.remaining_amount) }}</span>
          </div>
        </div>
      </article>
      <div v-if="!allTrips.length && !loading" class="empty-state">
        <i class="pi pi-info-circle"></i>
        <span>Belum ada riwayat jalan.</span>
      </div>
    </section>

    <Dialog
      v-model:visible="showDetailDialog"
      header="Detail Perjalanan"
      modal
      class="custom-dialog trip-detail-dialog"
      :style="{ width: '92vw', maxWidth: '720px' }"
      :breakpoints="{ '768px': '100vw' }"
    >
      <div v-if="selectedTrip" class="dialog-stack">
        <section class="detail-section">
          <h3 class="detail-section-title">Booking</h3>
          <div class="info-grid">
            <span>Kode</span><strong>{{ selectedTrip.booking?.kode_booking || '-' }}</strong>
            <span>Pelanggan</span><strong>{{ selectedTrip.booking?.customer?.nama || '-' }}</strong>
            <span>Status</span><strong>{{ selectedTrip.booking?.status || '-' }}</strong>
            <span>Kota</span><strong>{{ selectedTrip.booking?.kota || '-' }}</strong>
            <span>Tujuan</span><strong>{{ selectedTrip.booking?.tujuan || '-' }}</strong>
            <span>Penjemputan</span><strong>{{ selectedTrip.booking?.alamat_penjemputan || '-' }}</strong>
            <span>Catatan</span><strong>{{ selectedTrip.booking?.catatan || '-' }}</strong>
          </div>
        </section>

        <section class="detail-section">
          <h3 class="detail-section-title">Unit & Jadwal</h3>
          <div class="info-grid">
            <span>Unit</span><strong>{{ unitLabel(selectedTrip) }}</strong>
            <span>Nopol</span><strong>{{ selectedTrip.unit?.no_polisi || selectedTrip.booking_detail?.unit?.no_polisi || '-' }}</strong>
            <span>Tgl Sewa</span><strong>{{ formatDateTime(selectedTrip.tgl_sewa) }}</strong>
            <span>Tgl Kembali</span><strong>{{ formatDateTime(selectedTrip.tgl_kembali) }}</strong>
          </div>
        </section>

        <section v-if="selectedTrip.fund" class="detail-section">
          <h3 class="detail-section-title">Dana Operasional</h3>
          <div class="info-grid">
            <span>Status</span>
            <strong>
              <Tag :value="selectedTrip.fund.status" :severity="fundStatusSeverity(selectedTrip.fund.status)" />
            </strong>
            <span>Total Dana</span><strong>{{ formatCurrency(selectedTrip.fund.amount) }}</strong>
            <span>Sisa</span><strong>{{ formatCurrency(selectedTrip.fund.summary?.remaining_amount) }}</strong>
            <span>Tgl Bayar</span><strong>{{ formatDateTime(selectedTrip.fund.paid_at) }}</strong>
            <span v-if="selectedTrip.fund.closed_at">Ditutup</span>
            <strong v-if="selectedTrip.fund.closed_at">{{ formatDateTime(selectedTrip.fund.closed_at) }}</strong>
            <span v-if="selectedTrip.fund.close_note">Catatan Tutup</span>
            <strong v-if="selectedTrip.fund.close_note">{{ selectedTrip.fund.close_note }}</strong>
          </div>

          <div v-if="selectedTrip.fund.items?.length" class="breakdown-list">
            <div v-for="item in selectedTrip.fund.items" :key="item.id">
              <span>{{ item.label }}</span>
              <strong>{{ formatCurrency(item.planned_amount) }}</strong>
            </div>
          </div>

          <div v-if="selectedTrip.fund.expenses?.length" class="receipt-list">
            <h4 class="detail-subtitle">Bon &amp; Pengembalian</h4>
            <div v-for="expense in selectedTrip.fund.expenses" :key="expense.id" class="receipt-row">
              <div>
                <strong>{{ expense.type === 'return' ? 'Pengembalian' : (expense.cost_type?.nama || 'Bon') }}</strong>
                <p>{{ expense.description }}</p>
                <p v-if="expense.rejection_reason" class="reject-note">{{ expense.rejection_reason }}</p>
              </div>
              <div class="receipt-side">
                <strong>{{ formatCurrency(expense.amount) }}</strong>
                <Tag :value="expense.status" :severity="expenseStatusSeverity(expense.status)" />
              </div>
            </div>
          </div>
        </section>

        <section v-else class="detail-section">
          <p class="muted-line">Tidak ada dana operasional yang tercatat untuk perjalanan ini.</p>
        </section>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.driver-page {
  min-height: 100dvh;
  padding: var(--space-lg);
  padding-bottom: calc(82px + env(safe-area-inset-bottom));
  background: var(--page-bg);
}

.driver-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-md);
  margin-bottom: var(--space-lg);
}

.card-stack,
.dialog-stack,
.receipt-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.app-card {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.trip-card {
  padding: var(--space-lg);
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.trip-card:hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow-modal, var(--shadow-tile));
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--space-md);
  margin-bottom: var(--space-md);
}

.trip-headline strong {
  font-size: 14px;
  font-weight: 900;
}

.trip-headline p {
  margin: 4px 0 0;
  color: var(--text-secondary);
  font-size: 12px;
  line-height: 1.35;
}

.trip-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
  color: var(--text-primary);
}

.trip-info-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.trip-info-row i {
  color: var(--text-secondary);
  font-size: 12px;
  width: 14px;
  text-align: center;
}

.trip-nopol {
  color: var(--text-secondary);
}

.empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: var(--space-2xl);
  border: 1px dashed var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--card-bg);
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 800;
}

/* Detail dialog */
.detail-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding: var(--space-md);
  border-radius: var(--radius-default);
  background: var(--card-bg);
}

.detail-section-title {
  margin: 0;
  font-size: 12px;
  font-weight: 800;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.detail-subtitle {
  margin: var(--space-sm) 0 0;
  font-size: 12px;
  font-weight: 800;
  color: var(--text-primary);
}

.info-grid {
  display: grid;
  grid-template-columns: 110px 1fr;
  row-gap: 6px;
  column-gap: var(--space-md);
  font-size: 12px;
  line-height: 1.4;
}

.info-grid span {
  color: var(--text-secondary);
}

.info-grid strong {
  word-break: break-word;
}

.breakdown-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: var(--space-md);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  border: 1px solid var(--surface-border);
}

.breakdown-list div {
  display: flex;
  justify-content: space-between;
  gap: var(--space-md);
  font-size: 12px;
}

.receipt-row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-md);
  padding: var(--space-md);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  border: 1px solid var(--surface-border);
}

.receipt-row p {
  margin: 4px 0 0;
  color: var(--text-secondary);
  font-size: 12px;
  line-height: 1.35;
}

.receipt-side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  min-width: 112px;
}

.reject-note {
  color: var(--negative) !important;
  font-weight: 800;
}

.muted-line {
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 800;
}

:deep(.trip-detail-dialog) {
  max-height: 92vh;
}

:deep(.trip-detail-dialog .p-dialog-content) {
  padding: var(--space-lg);
}

@media (max-width: 768px) {
  :deep(.trip-detail-dialog) {
    margin: 0 !important;
    width: 100vw !important;
    max-width: 100vw !important;
    max-height: 100dvh;
    border-radius: 0 !important;
  }

  :deep(.trip-detail-dialog .p-dialog-content) {
    max-height: calc(100dvh - 64px);
  }
}

@media (min-width: 769px) {
  .driver-page {
    padding: var(--space-2xl);
  }

  .card-stack {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
  }
}
</style>
