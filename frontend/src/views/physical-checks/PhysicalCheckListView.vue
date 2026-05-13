<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { format } from 'date-fns'
import { usePhysicalCheck } from '../../composables/usePhysicalCheck'
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'

const router = useRouter()
const { rows, loading, pagination, filters, fetchBookings, requestCheck } = usePhysicalCheck()

const loadData = async () => {
  await fetchBookings(pagination.value.current_page)
}

onMounted(loadData)

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
</script>

<template>
  <div class="app-page physical-page">
    <div class="page-head">
      <div>
        <p class="eyebrow">Operasional</p>
        <h1>Cek Fisik</h1>
      </div>
      <div class="head-actions">
        <span class="search-box">
          <i class="pi pi-search"></i>
          <InputText
            v-model="filters.search"
            placeholder="Cari booking, pelanggan, kendaraan..."
            @keydown.enter="applySearch"
          />
        </span>
        <Button icon="pi pi-refresh" label="Refresh" outlined @click="loadData" :loading="loading" />
      </div>
    </div>

    <div class="app-card table-shell">
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
        class="physical-table"
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
              <Button
                v-if="data.status === 'waiting_list'"
                size="small"
                icon="pi pi-car"
                :label="actionLabel(data, 'departure')"
                :disabled="!isActionAllowed(data, 'departure')"
                @click="openForm(data, 'departure')"
              />
              <Button
                v-if="data.status === 'rental_unit'"
                size="small"
                icon="pi pi-undo"
                severity="help"
                :label="actionLabel(data, 'return')"
                :disabled="!isActionAllowed(data, 'return')"
                @click="openForm(data, 'return')"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>
  </div>
</template>

<style scoped>
.physical-page {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.page-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
}

.page-head h1 {
  margin: 0;
  color: #0f172a;
  font-weight: 800;
}

.eyebrow {
  margin: 0 0 4px;
  color: #0891b2;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0;
}

.head-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.search-box {
  position: relative;
  width: min(360px, 70vw);
}

.search-box i {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  z-index: 1;
}

.search-box :deep(.p-inputtext) {
  width: 100%;
  padding-left: 34px;
}

.table-shell {
  overflow: hidden;
}

.booking-link {
  border: 0;
  padding: 0;
  background: transparent;
  color: #0369a1;
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
  color: #64748b;
}

.vehicle-cell small {
  color: #0891b2;
}

.date-stack i {
  margin-right: 6px;
  color: #64748b;
}

.customer-name {
  display: block;
  margin-bottom: 6px;
  color: #334155;
}

.status-tag {
  border-radius: 6px;
}

.action-group {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

@media (max-width: 768px) {
  .page-head {
    align-items: stretch;
    flex-direction: column;
  }

  .head-actions,
  .search-box {
    width: 100%;
  }

  .head-actions :deep(.p-button) {
    flex: 1;
  }
}
</style>
