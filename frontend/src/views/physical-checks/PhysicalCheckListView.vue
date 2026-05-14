<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { format } from 'date-fns'
import { usePhysicalCheck } from '../../composables/usePhysicalCheck'
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'

const router = useRouter()
const { rows, loading, pagination, filters, fetchBookings, requestCheck } = usePhysicalCheck()
const isMobile = ref(window.innerWidth < 768)

const handleResize = () => {
  isMobile.value = window.innerWidth < 768
}

const loadData = async () => {
  await fetchBookings(pagination.value.current_page)
}

onMounted(() => {
  loadData()
  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
})

const onPage = (event) => {
  pagination.value.current_page = event.page + 1
  fetchBookings(pagination.value.current_page)
}

const applySearch = () => {
  pagination.value.current_page = 1
  fetchBookings(1)
}

const formatDateTime = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy HH:mm')
}

const checkLabel = (status) => {
  const map = {
    not_requested: 'Belum diminta',
    requested: 'Diminta',
    completed: 'Selesai',
    skipped: 'Dilewati'
  }
  return map[status] || status || '-'
}

const checkSeverity = (status) => {
  const map = {
    not_requested: 'secondary',
    requested: 'warning',
    completed: 'success',
    skipped: 'danger'
  }
  return map[status] || 'info'
}

const openForm = async (booking, type) => {
  const check = booking.checks?.[type]
  if (!check?.id || check.status === 'not_requested') {
    await requestCheck(booking.id, type)
    await loadData()
  }

  router.push({
    name: 'PhysicalCheckForm',
    params: { bookingId: booking.id, type }
  })
}

const actionLabel = (booking, type) => {
  const status = booking.checks?.[type]?.status
  if (status === 'completed' || status === 'skipped') return 'Lihat'
  if (status === 'requested') return 'Lanjutkan'
  return 'Mulai'
}

const isActionAllowed = (booking, type) => {
  const status = booking.checks?.[type]?.status
  return status === 'completed' || status === 'skipped' || booking.eligibility?.[type]?.allowed
}

const getActionType = (booking) => {
  if (booking.status === 'waiting_list') return 'departure'
  if (booking.status === 'rental_unit') return 'return'
  return null
}

const getActionIcon = (type) => type === 'return' ? 'pi pi-undo' : 'pi pi-car'

const getCheckSummary = (booking, type) => ({
  label: type === 'departure' ? 'Keberangkatan' : 'Kembali',
  status: booking.checks?.[type]?.status,
  allowed: booking.eligibility?.[type]?.allowed,
  reason: booking.eligibility?.[type]?.reason
})
</script>

<template>
  <div class="page-container physical-page">
    <div class="detail-page-header">
      <div class="header-left">
        <h1 class="text-h1">Cek Fisik</h1>
        <p class="text-secondary text-xs">Pantau dan lanjutkan cek fisik unit saat keberangkatan dan pengembalian.</p>
      </div>
      <div class="head-actions">
        <span class="filter-search">
          <i class="pi pi-search"></i>
          <InputText
            v-model="filters.search"
            placeholder="Cari booking, pelanggan, kendaraan..."
            @keydown.enter="applySearch"
          />
        </span>
        <button class="btn-pill btn-primary" :disabled="loading" @click="applySearch">
          <i class="pi pi-search"></i>
          Cari
        </button>
        <button class="btn-pill btn-primary" :disabled="loading" @click="loadData">
          <i class="pi pi-refresh"></i>
          Refresh
        </button>
      </div>
    </div>

    <div v-if="!isMobile" class="table-shell">
      <DataTable
        :value="rows"
        :loading="loading"
        lazy
        paginator
        :rows="pagination.per_page"
        :totalRecords="pagination.total"
        dataKey="id"
        scrollable
        scrollHeight="flex"
        responsiveLayout="scroll"
        @page="onPage"
        class="drent-datatable"
        emptyMessage="Tidak ada booking untuk cek fisik."
      >
        <Column field="kode_booking" header="Booking" style="min-width: 150px">
          <template #body="{ data }">
            <button class="booking-link" @click="router.push(`/bookings/${data.id}`)">
              {{ data.kode_booking }}
            </button>
            <div class="mt-1">
              <BookingStatusBadge :status="data.status" />
            </div>
          </template>
        </Column>

        <Column header="Kendaraan" style="min-width: 220px">
          <template #body="{ data }">
            <div class="vehicle-cell">
              <strong>{{ data.vehicle?.title || '-' }}</strong>
              <span>{{ data.vehicle?.no_polisi || 'No polisi belum ada' }}</span>
              <small v-if="data.vehicle?.owner">{{ data.vehicle.owner }}</small>
            </div>
          </template>
        </Column>

        <Column header="Tanggal Sewa & Kembali" style="min-width: 210px">
          <template #body="{ data }">
            <div class="date-stack">
              <span><i class="pi pi-calendar-plus"></i>{{ formatDateTime(data.rental?.tgl_sewa) }}</span>
              <span><i class="pi pi-calendar-minus"></i>{{ formatDateTime(data.rental?.tgl_kembali) }}</span>
            </div>
          </template>
        </Column>

        <Column header="Pelanggan" style="min-width: 180px">
          <template #body="{ data }">
            <strong class="customer-name">{{ data.customer?.nama || '-' }}</strong>
            <Tag v-if="data.customer?.status" :value="data.customer.status" severity="info" class="status-tag" />
          </template>
        </Column>

        <Column header="Cek Keberangkatan" style="min-width: 190px">
          <template #body="{ data }">
            <div class="check-cell">
              <Tag
                :value="checkLabel(data.checks?.departure?.status)"
                :severity="checkSeverity(data.checks?.departure?.status)"
              />
              <small v-if="!data.eligibility?.departure?.allowed && data.checks?.departure?.status !== 'completed'">
                {{ data.eligibility?.departure?.reason }}
              </small>
            </div>
          </template>
        </Column>

        <Column header="Cek Kembali" style="min-width: 190px">
          <template #body="{ data }">
            <div class="check-cell">
              <Tag
                :value="checkLabel(data.checks?.return?.status)"
                :severity="checkSeverity(data.checks?.return?.status)"
              />
              <small v-if="!data.eligibility?.return?.allowed && data.checks?.return?.status !== 'completed'">
                {{ data.eligibility?.return?.reason }}
              </small>
            </div>
          </template>
        </Column>

        <Column header="Aksi" frozen alignFrozen="right" style="min-width: 210px">
          <template #body="{ data }">
            <div class="action-group">
              <button
                v-if="data.status === 'waiting_list'"
                class="btn-pill btn-primary btn-pill-compact"
                :disabled="!isActionAllowed(data, 'departure')"
                @click="openForm(data, 'departure')"
              >
                <i class="pi pi-car"></i>
                {{ actionLabel(data, 'departure') }}
              </button>
              <button
                v-if="data.status === 'rental_unit'"
                class="btn-pill btn-primary btn-pill-compact"
                :disabled="!isActionAllowed(data, 'return')"
                @click="openForm(data, 'return')"
              >
                <i class="pi pi-undo"></i>
                {{ actionLabel(data, 'return') }}
              </button>
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <div v-else class="mobile-card-list">
      <div v-if="loading" class="app-muted-panel mobile-state">
        <i class="pi pi-spin pi-spinner"></i>
        <span>Memuat daftar cek fisik...</span>
      </div>

      <div v-else-if="!rows.length" class="app-muted-panel mobile-state">
        <i class="pi pi-info-circle"></i>
        <span>Tidak ada booking untuk cek fisik.</span>
      </div>

      <template v-else>
        <article
          v-for="booking in rows"
          :key="booking.id"
          class="physical-card"
        >
          <div class="card-header">
            <button class="booking-code" @click="router.push(`/bookings/${booking.id}`)">
              {{ booking.kode_booking }}
            </button>
            <BookingStatusBadge :status="booking.status" />
          </div>

          <div class="vehicle-summary">
            <strong>{{ booking.vehicle?.title || '-' }}</strong>
            <span>{{ booking.vehicle?.no_polisi || 'No polisi belum ada' }}</span>
            <small v-if="booking.vehicle?.owner">{{ booking.vehicle.owner }}</small>
          </div>

          <div class="mobile-info-grid">
            <div class="info-col">
              <span class="label">Pelanggan</span>
              <span class="value">{{ booking.customer?.nama || '-' }}</span>
              <Tag v-if="booking.customer?.status" :value="booking.customer.status" severity="info" class="status-tag" />
            </div>
            <div class="info-col">
              <span class="label">Sewa</span>
              <span class="value">{{ formatDateTime(booking.rental?.tgl_sewa) }}</span>
            </div>
            <div class="info-col">
              <span class="label">Kembali</span>
              <span class="value">{{ formatDateTime(booking.rental?.tgl_kembali) }}</span>
            </div>
          </div>

          <div class="check-summary-grid">
            <div
              v-for="summary in [getCheckSummary(booking, 'departure'), getCheckSummary(booking, 'return')]"
              :key="summary.label"
              class="check-summary"
            >
              <span class="label">{{ summary.label }}</span>
              <Tag :value="checkLabel(summary.status)" :severity="checkSeverity(summary.status)" />
              <small v-if="!summary.allowed && summary.status !== 'completed'">{{ summary.reason }}</small>
            </div>
          </div>

          <button
            v-if="getActionType(booking)"
            class="btn-pill btn-primary card-action"
            :disabled="!isActionAllowed(booking, getActionType(booking))"
            @click="openForm(booking, getActionType(booking))"
          >
            <i :class="getActionIcon(getActionType(booking))"></i>
            {{ actionLabel(booking, getActionType(booking)) }} Cek Fisik
          </button>
        </article>
      </template>

      <div v-if="rows.length" class="mobile-paginator">
        <button
          class="btn-pill btn-primary btn-pill-compact"
          :disabled="pagination.current_page === 1 || loading"
          @click="onPage({ page: pagination.current_page - 2 })"
        >
          <i class="pi pi-chevron-left"></i>
          Sebelumnya
        </button>
        <span>Hal {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
        <button
          class="btn-pill btn-primary btn-pill-compact"
          :disabled="pagination.current_page === pagination.last_page || loading"
          @click="onPage({ page: pagination.current_page })"
        >
          Berikutnya
          <i class="pi pi-chevron-right"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-container {
  min-height: 100%;
  padding: var(--space-2xl);
  background: var(--page-bg);
}

.physical-page {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.detail-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-lg);
  margin-bottom: var(--space-md);
}

.head-actions {
  display: flex;
  align-items: flex-start;
  gap: var(--space-sm);
  flex-wrap: wrap;
  justify-content: flex-end;
}

.filter-search {
  position: relative;
  width: min(360px, 70vw);
}

.filter-search :deep(.p-inputtext) {
  width: 100%;
}

.table-shell {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  overflow: hidden;
}

.drent-datatable {
  flex: 1 1 auto;
  min-height: 0;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  overflow: hidden;
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

:deep(.drent-datatable .p-datatable-table-container),
:deep(.drent-datatable .p-datatable-wrapper) {
  flex: 1 1 auto;
  min-height: 0;
  border-radius: inherit;
}

:deep(.drent-datatable .p-datatable-thead > tr > th) {
  background: var(--card-bg);
  color: var(--text-secondary);
  font-size: 11px;
  text-transform: uppercase;
}

:deep(.drent-datatable .p-paginator) {
  border-top: 1px solid var(--surface-border);
  border-radius: 0 0 var(--radius-default) var(--radius-default);
}

.booking-link {
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--text-primary);
  font-weight: 800;
  cursor: pointer;
}

.vehicle-cell,
.date-stack,
.check-cell {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.vehicle-cell span,
.date-stack span,
.check-cell small {
  color: var(--text-secondary);
}

.vehicle-cell small {
  color: var(--info-cyan);
}

.date-stack i {
  margin-right: 6px;
  color: var(--text-tertiary);
}

.customer-name {
  display: block;
  margin-bottom: 6px;
  color: var(--text-primary);
}

.status-tag {
  border-radius: 6px;
}

.action-group {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.mobile-card-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.physical-card,
.app-muted-panel {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.physical-card {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding: var(--space-lg);
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

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-md);
  padding-bottom: var(--space-sm);
  border-bottom: 1px solid var(--surface-border);
}

.booking-code {
  min-width: 0;
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 13px;
  font-weight: 700;
  text-align: left;
  overflow-wrap: anywhere;
}

.vehicle-summary {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.vehicle-summary strong {
  color: var(--text-primary);
  font-size: 14px;
  line-height: 1.3;
  overflow-wrap: anywhere;
}

.vehicle-summary span,
.vehicle-summary small {
  color: var(--text-secondary);
  font-size: 12px;
  line-height: 1.35;
}

.vehicle-summary small {
  color: var(--info-cyan);
}

.mobile-info-grid,
.check-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-sm);
  padding-top: var(--space-sm);
  border-top: 1px solid var(--surface-border);
}

.mobile-info-grid .info-col:first-child {
  grid-column: 1 / -1;
}

.info-col,
.check-summary {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.label {
  color: var(--text-tertiary);
  font-size: 10px;
  font-weight: 700;
  line-height: 1.2;
  text-transform: uppercase;
}

.value {
  color: var(--text-primary);
  font-size: 12px;
  font-weight: 700;
  line-height: 1.3;
  overflow-wrap: anywhere;
}

.check-summary small {
  color: var(--text-secondary);
  font-size: 11px;
  line-height: 1.35;
}

.card-action {
  width: 100%;
}

.mobile-paginator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  flex-wrap: wrap;
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 700;
}

@media (max-width: 768px) {
  .page-container {
    padding: var(--space-lg);
  }

  .detail-page-header {
    align-items: stretch;
    flex-direction: column;
    margin-bottom: 0;
  }

  .head-actions {
    justify-content: flex-start;
  }

  .head-actions,
  .filter-search {
    width: 100%;
  }

  .head-actions .btn-pill {
    flex: 1 1 calc(50% - var(--space-sm));
    min-width: 130px;
  }

  .mobile-info-grid,
  .check-summary-grid {
    grid-template-columns: 1fr;
  }

  .mobile-info-grid .info-col:first-child {
    grid-column: auto;
  }

  .mobile-paginator .btn-pill {
    flex: 1 1 130px;
  }
}

@media (min-width: 769px) {
  .physical-page {
    height: 100dvh;
    overflow: hidden;
  }
}
</style>
