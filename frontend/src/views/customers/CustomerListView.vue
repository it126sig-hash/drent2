<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useCustomer } from '../../composables/useCustomer'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useAuthStore } from '../../stores/auth'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Paginator from 'primevue/paginator'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import Dialog from 'primevue/dialog'
import ConfirmDialog from 'primevue/confirmdialog'
import CustomerFormDialog from '../../components/customers/CustomerFormDialog.vue'

const {
  customers,
  loading,
  pagination,
  fetchAll,
  fetchOne,
  store,
  update,
  remove
} = useCustomer()

const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const searchQuery = ref('')
const statusFilter = ref(null)
const showDialog = ref(false)
const showDetailDialog = ref(false)
const selectedCustomer = ref(null)
const selectedCustomerDetail = ref(null)
const detailLoading = ref(false)

const statusOptions = [
  { label: 'Semua Status', value: null },
  { label: 'Normal', value: 'Normal' },
  { label: 'Member', value: 'Member' },
  { label: 'Rent to Rent', value: 'Rent to Rent' },
  { label: 'Corporate', value: 'Corporate' },
  { label: 'Redflag', value: 'Redflag' },
  { label: 'Blacklist', value: 'Blacklist' }
]

const canManage = computed(() => ['superadmin', 'admin_branch', 'cs'].includes(authStore.user?.role))
const canDelete = computed(() => ['superadmin', 'admin_branch'].includes(authStore.user?.role))
const hasRiskCustomer = computed(() =>
  customers.value.some(c => ['Redflag', 'Blacklist'].includes(c.status))
)
const rentalHistory = computed(() => selectedCustomerDetail.value?.rental_history || [])
const detailTimeline = computed(() => {
  const customer = selectedCustomerDetail.value
  if (!customer) return []

  const items = [
    {
      type: 'profile',
      title: 'Data konsumen dibuat',
      date: customer.created_at,
      icon: 'pi pi-user',
      tone: 'info',
      description: customer.alamat || customer.kota || 'Profil pelanggan tersimpan di sistem.'
    }
  ]

  if (customer.catatan) {
    items.push({
      type: 'note',
      title: 'Catatan konsumen',
      date: customer.created_at,
      icon: 'pi pi-info-circle',
      tone: riskStatus(customer.status) ? 'warning' : 'neutral',
      description: customer.catatan
    })
  }

  if (customer.member) {
    items.push({
      type: 'member',
      title: `Member ${customer.member.status_member || customer.member_status || ''}`.trim(),
      date: customer.member.tanggal_aktif || customer.member.tanggal_survey,
      icon: 'pi pi-id-card',
      tone: 'success',
      description: [
        customer.member.id_member ? `ID ${customer.member.id_member}` : null,
        customer.member.tanggal_exp ? `Exp ${formatDate(customer.member.tanggal_exp)}` : null,
        customer.member.surveyor?.name ? `Surveyor ${customer.member.surveyor.name}` : null
      ].filter(Boolean).join(' · ')
    })
  }

  rentalHistory.value.forEach((booking) => {
    items.push({
      type: 'booking',
      title: booking.kode_booking || 'Booking',
      date: booking.tgl_sewa || booking.completed_at || booking.returned_at,
      icon: 'pi pi-car',
      tone: statusTone(booking.status),
      description: [
        formatRentalPeriod(booking),
        booking.unit ? vehicleLabel(booking.unit) : null,
        booking.catatan ? `Catatan: ${booking.catatan}` : null,
        booking.catatan_status ? `Status: ${booking.catatan_status}` : null
      ].filter(Boolean).join(' · '),
      booking
    })
  })

  return items.sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0))
})

onMounted(() => {
  fetchData()
})

const fetchData = async () => {
  try {
    await fetchAll({
      search: searchQuery.value,
      status: statusFilter.value
    })
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Gagal memuat data pelanggan',
      life: 3000
    })
  }
}

const onSearch = () => {
  pagination.value.current_page = 1
  fetchData()
}

const resetFilters = () => {
  searchQuery.value = ''
  statusFilter.value = null
  pagination.value.current_page = 1
  fetchData()
}

const onPageChange = (event) => {
  pagination.value.current_page = event.page + 1
  fetchData()
}

const openNew = () => {
  selectedCustomer.value = null
  showDialog.value = true
}

const openDetail = async (customer) => {
  selectedCustomerDetail.value = { ...customer, rental_history: [] }
  showDetailDialog.value = true
  detailLoading.value = true

  try {
    selectedCustomerDetail.value = await fetchOne(customer.id)
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal memuat detail konsumen', life: 3000 })
  } finally {
    detailLoading.value = false
  }
}

const editCustomer = (customer) => {
  selectedCustomer.value = { ...customer }
  showDialog.value = true
}

const saveCustomer = async (data) => {
  try {
    if (data.id) {
      await update(data.id, data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Data pelanggan berhasil diperbarui', life: 3000 })
    } else {
      await store(data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Pelanggan berhasil ditambahkan', life: 3000 })
    }
    showDialog.value = false
    await fetchData()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.message || 'Terjadi kesalahan saat menyimpan data', life: 3000 })
  }
}

const confirmDelete = (customer) => {
  confirm.require({
    message: `Apakah Anda yakin ingin menghapus pelanggan "${customer.nama}"?`,
    header: 'Konfirmasi Hapus',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await remove(customer.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Pelanggan berhasil dihapus', life: 3000 })
        await fetchData()
      } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal menghapus data', life: 3000 })
      }
    }
  })
}

const goToBooking = (bookingId) => {
  if (!bookingId) return
  router.push(`/bookings/${bookingId}`)
}

const getStatusSeverity = (status) => {
  if (status === 'Normal') return 'success'
  if (status === 'Member') return 'info'
  if (status === 'Rent to Rent') return 'secondary'
  if (status === 'Corporate') return 'help'
  if (status === 'Redflag') return 'warning'
  if (status === 'Blacklist') return 'danger'
  return 'info'
}

const statusTone = (status) => {
  if (['selesai', 'completed', 'Normal'].includes(status)) return 'success'
  if (['confirm', 'rental_unit', 'Member', 'Corporate'].includes(status)) return 'info'
  if (['waiting_list', 'Redflag'].includes(status)) return 'warning'
  if (['batal', 'cancelled', 'Blacklist'].includes(status)) return 'error'
  return 'neutral'
}

const riskStatus = (status) => ['Redflag', 'Blacklist'].includes(status)
const customerSubtitle = (customer) => [customer?.kontak_1, customer?.email, customer?.kota].filter(Boolean).join(' · ') || '-'
const vehicleLabel = (unit) => [unit?.merk, unit?.tipe, unit?.no_polisi].filter(Boolean).join(' ')
const formatCurrency = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
const formatDate = (value) => value ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value)) : '-'
const formatRentalPeriod = (booking) => {
  const start = formatDate(booking?.tgl_sewa)
  const end = formatDate(booking?.tgl_kembali)
  return start === '-' && end === '-' ? '-' : `${start} - ${end}`
}
</script>

<template>
  <div class="page-container table-page-active customer-page">
    <ConfirmDialog />

    <div class="page-header">
      <div class="header-left">
        <div>
          <h1>Manajemen Pelanggan</h1>
          <p>Kelola data konsumen, status member, risiko, dan histori sewa.</p>
        </div>
      </div>
      <div class="header-actions">
        <button v-if="canManage" class="btn-pill btn-primary" type="button" @click="openNew">
          <i class="pi pi-plus"></i>
          <span>Tambah Pelanggan</span>
        </button>
      </div>
    </div>

    <Message v-if="hasRiskCustomer" severity="warn" class="risk-message">
      Terdapat pelanggan dengan status Redflag atau Blacklist dalam daftar ini.
    </Message>

    <div class="filter-bar surface-card">
      <div class="filter-groups">
        <div class="filter-group filter-search">
          <label>Cari Konsumen</label>
          <span class="p-input-icon-left search-input">
            <i class="pi pi-search" />
            <InputText
              v-model="searchQuery"
              placeholder="Nama, kontak, email, kota, catatan..."
              @input="onSearch"
            />
          </span>
        </div>
        <div class="filter-group">
          <label>Status</label>
          <Dropdown
            v-model="statusFilter"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Semua Status"
            @change="onSearch"
          />
        </div>
      </div>
      <div class="filter-actions">
        <button class="btn-pill btn-secondary btn-pill-compact" type="button" :disabled="loading" @click="resetFilters">
          Reset
        </button>
        <button class="btn-pill btn-primary btn-pill-compact" type="button" :disabled="loading" @click="fetchData">
          Terapkan
        </button>
      </div>
    </div>

    <div class="table-shell list-tab-fill">
      <DataTable
        :value="customers"
        :loading="loading"
        scrollable
        scrollHeight="flex"
        class="drent-datatable customer-table"
        stripedRows
        @row-dblclick="openDetail($event.data)"
      >
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-users"></i>
            <p>Belum ada data pelanggan.</p>
          </div>
        </template>

        <Column header="Aksi" style="width: 128px">
          <template #body="{ data }">
            <div class="action-pill-group">
              <button class="action-btn action-btn-primary" type="button" title="Lihat detail konsumen" @click.stop="openDetail(data)">
                <i class="pi pi-eye"></i>
              </button>
              <button v-if="canManage" class="action-btn" type="button" title="Edit" @click.stop="editCustomer(data)">
                <i class="pi pi-pencil"></i>
              </button>
              <button v-if="canDelete" class="action-btn action-btn-danger" type="button" title="Hapus" @click.stop="confirmDelete(data)">
                <i class="pi pi-trash"></i>
              </button>
            </div>
          </template>
        </Column>

        <Column field="nama" header="Konsumen" style="min-width: 230px">
          <template #body="{ data }">
            <div class="customer-info">
              <div class="customer-name-row">
                <span class="customer-name">{{ data.nama }}</span>
                <span v-if="data.has_apply_member" class="status-badge success">Member</span>
              </div>
              <small>{{ customerSubtitle(data) }}</small>
            </div>
          </template>
        </Column>

        <Column field="kontak_1" header="Kontak" style="min-width: 150px">
          <template #body="{ data }">
            <div class="stacked-text">
              <span>{{ data.kontak_1 || '-' }}</span>
              <small v-if="data.kontak_2">{{ data.kontak_2 }}</small>
            </div>
          </template>
        </Column>

        <Column field="email" header="Email" style="min-width: 180px">
          <template #body="{ data }">
            <span class="truncate-text">{{ data.email || '-' }}</span>
          </template>
        </Column>

        <Column field="kota" header="Kota" style="min-width: 120px">
          <template #body="{ data }">
            {{ data.kota || '-' }}
          </template>
        </Column>

        <Column field="status" header="Status" style="min-width: 140px">
          <template #body="{ data }">
            <Tag :severity="getStatusSeverity(data.status)" :value="data.status || '-'" class="status-tag" />
          </template>
        </Column>

        <Column field="catatan" header="Catatan" style="min-width: 220px">
          <template #body="{ data }">
            <span class="note-line">{{ data.catatan || '-' }}</span>
          </template>
        </Column>
      </DataTable>

      <div class="paginator-wrapper">
        <Paginator
          :rows="pagination.per_page"
          :totalRecords="pagination.total"
          :first="(pagination.current_page - 1) * pagination.per_page"
          @page="onPageChange"
          template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
          currentPageReportTemplate="Menampilkan {first} ke {last} dari {totalRecords} data"
        />
      </div>
    </div>

    <div class="mobile-card-list">
      <article v-for="customer in customers" :key="customer.id" class="mobile-customer-card">
        <div class="mobile-card-head">
          <div>
            <h3>{{ customer.nama }}</h3>
            <p>{{ customerSubtitle(customer) }}</p>
          </div>
          <Tag :severity="getStatusSeverity(customer.status)" :value="customer.status || '-'" />
        </div>
        <p v-if="customer.catatan" class="mobile-note">{{ customer.catatan }}</p>
        <div class="card-actions">
          <button class="btn-pill btn-primary btn-pill-compact" type="button" @click="openDetail(customer)">Lihat</button>
          <button v-if="canManage" class="btn-pill btn-secondary btn-pill-compact" type="button" @click="editCustomer(customer)">Edit</button>
        </div>
      </article>
    </div>

    <CustomerFormDialog
      v-model:visible="showDialog"
      :customer="selectedCustomer"
      :loading="loading"
      @save="saveCustomer"
    />

    <Dialog
      v-model:visible="showDetailDialog"
      modal
      :style="{ width: 'min(1100px, 96vw)' }"
      class="custom-dialog customer-detail-dialog"
      :header="selectedCustomerDetail?.nama ? `Detail Konsumen - ${selectedCustomerDetail.nama}` : 'Detail Konsumen'"
    >
      <div v-if="detailLoading" class="detail-loading">
        <i class="pi pi-spin pi-spinner"></i>
        <span>Memuat detail konsumen...</span>
      </div>

      <div v-else-if="selectedCustomerDetail" class="detail-grid">
        <section class="detail-main app-card">
          <div class="app-section-header">
            <div>
              <h2>Timeline Konsumen</h2>
              <p>{{ rentalHistory.length }} histori sewa tercatat</p>
            </div>
            <span class="status-badge" :class="statusTone(selectedCustomerDetail.status)">
              {{ selectedCustomerDetail.status || '-' }}
            </span>
          </div>

          <div class="timeline-list">
            <div v-for="item in detailTimeline" :key="`${item.type}-${item.date}-${item.title}`" class="timeline-item">
              <div class="timeline-marker" :class="item.tone">
                <i :class="item.icon"></i>
              </div>
              <div class="timeline-content">
                <div class="timeline-head">
                  <strong>{{ item.title }}</strong>
                  <span>{{ formatDate(item.date) }}</span>
                </div>
                <p>{{ item.description || '-' }}</p>
                <button
                  v-if="item.booking"
                  class="btn-pill btn-secondary btn-pill-compact"
                  type="button"
                  @click="goToBooking(item.booking.id)"
                >
                  Buka Booking
                </button>
              </div>
            </div>
          </div>
        </section>

        <aside class="detail-side">
          <section class="app-card side-card">
            <div class="app-section-header">
              <div>
                <h2>Profil</h2>
                <p>{{ selectedCustomerDetail.kota || 'Kota belum diisi' }}</p>
              </div>
            </div>
            <dl class="info-list">
              <div><dt>Kontak 1</dt><dd>{{ selectedCustomerDetail.kontak_1 || '-' }}</dd></div>
              <div><dt>Kontak 2</dt><dd>{{ selectedCustomerDetail.kontak_2 || '-' }}</dd></div>
              <div><dt>Email</dt><dd>{{ selectedCustomerDetail.email || '-' }}</dd></div>
              <div><dt>Alamat</dt><dd>{{ selectedCustomerDetail.alamat || '-' }}</dd></div>
            </dl>
          </section>

          <section v-if="selectedCustomerDetail.member" class="app-card side-card">
            <div class="app-section-header">
              <div>
                <h2>Detail Member</h2>
                <p>{{ selectedCustomerDetail.member.id_member || 'ID member belum diisi' }}</p>
              </div>
              <span class="status-badge success">{{ selectedCustomerDetail.member.status_member || '-' }}</span>
            </div>
            <dl class="info-list">
              <div><dt>Aktif</dt><dd>{{ formatDate(selectedCustomerDetail.member.tanggal_aktif) }}</dd></div>
              <div><dt>Expired</dt><dd>{{ formatDate(selectedCustomerDetail.member.tanggal_exp) }}</dd></div>
              <div><dt>Survey</dt><dd>{{ formatDate(selectedCustomerDetail.member.tanggal_survey) }}</dd></div>
              <div><dt>Surveyor</dt><dd>{{ selectedCustomerDetail.member.surveyor?.name || '-' }}</dd></div>
              <div><dt>Pekerjaan</dt><dd>{{ selectedCustomerDetail.member.pekerjaan_status || selectedCustomerDetail.member.jabatan || '-' }}</dd></div>
              <div><dt>Kantor</dt><dd>{{ selectedCustomerDetail.member.nama_kantor || '-' }}</dd></div>
              <div><dt>PJ</dt><dd>{{ [selectedCustomerDetail.member.pj_nama, selectedCustomerDetail.member.pj_kontak].filter(Boolean).join(' · ') || '-' }}</dd></div>
              <div><dt>Catatan Member</dt><dd>{{ selectedCustomerDetail.member.catatan || '-' }}</dd></div>
            </dl>
          </section>

          <section class="app-card side-card">
            <div class="app-section-header">
              <div>
                <h2>Histori Sewa</h2>
                <p>Catatan rental konsumen</p>
              </div>
            </div>
            <div v-if="!rentalHistory.length" class="empty-side">Belum ada histori sewa.</div>
            <div v-else class="history-list">
              <button v-for="booking in rentalHistory" :key="booking.id" class="history-row" type="button" @click="goToBooking(booking.id)">
                <span>
                  <strong>{{ booking.kode_booking }}</strong>
                  <small>{{ formatRentalPeriod(booking) }}</small>
                  <small v-if="booking.catatan">Catatan: {{ booking.catatan }}</small>
                </span>
                <span>
                  <b>{{ formatCurrency(booking.total_tagihan) }}</b>
                  <small>Sisa {{ formatCurrency(booking.sisa_tagihan) }}</small>
                </span>
              </button>
            </div>
          </section>
        </aside>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.customer-page {
  gap: var(--space-lg);
}

.risk-message {
  margin: 0;
}

.search-input,
.search-input :deep(.p-inputtext) {
  width: 100%;
}

.customer-info,
.stacked-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.customer-name-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.customer-name {
  font-weight: 700;
  color: var(--text-primary);
}

.customer-info small,
.stacked-text small {
  color: var(--text-secondary);
  font-size: 12px;
}

.status-tag {
  font-weight: 700;
  font-size: 0.72rem;
}

.truncate-text,
.note-line {
  display: block;
  max-width: 260px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.note-line {
  color: var(--text-secondary);
  font-style: italic;
}

.paginator-wrapper {
  border-top: 1px solid var(--surface-border);
  padding: 8px;
}

.empty-state,
.detail-loading,
.empty-side {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 36px 0;
  color: var(--text-secondary);
}

.empty-state {
  flex-direction: column;
}

.empty-state i {
  font-size: 2rem;
  opacity: 0.55;
}

.mobile-card-list {
  display: none;
}

.detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.85fr);
  gap: var(--space-lg);
}

.detail-main,
.side-card {
  overflow: hidden;
}

.detail-side {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.app-section-header {
  justify-content: space-between;
}

.app-section-header h2 {
  margin: 0;
  font-size: 14px;
}

.app-section-header p {
  margin: 3px 0 0;
  color: var(--text-secondary);
  font-size: 12px;
}

.timeline-list {
  padding: var(--space-lg);
}

.timeline-item {
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr);
  gap: 12px;
  position: relative;
  padding-bottom: 16px;
}

.timeline-item:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 16px;
  top: 34px;
  bottom: 0;
  width: 1px;
  background: var(--surface-border);
}

.timeline-marker {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  color: var(--text-white);
  background: var(--text-secondary);
  z-index: 1;
}

.timeline-marker.info { background: var(--info-cyan); }
.timeline-marker.success { background: var(--positive); }
.timeline-marker.warning { background: var(--warning); }
.timeline-marker.error { background: var(--negative); }

.timeline-content {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  padding: 12px;
  background: var(--surface-default);
}

.timeline-head {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  color: var(--text-primary);
}

.timeline-head span,
.timeline-content p {
  color: var(--text-secondary);
  font-size: 12px;
}

.timeline-content p {
  margin: 6px 0 0;
  line-height: 1.5;
}

.timeline-content .btn-pill {
  margin-top: 10px;
}

.info-list {
  display: grid;
  gap: 10px;
  padding: var(--space-lg);
  margin: 0;
}

.info-list div {
  display: grid;
  grid-template-columns: 110px minmax(0, 1fr);
  gap: 12px;
}

.info-list dt {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.info-list dd {
  margin: 0;
  color: var(--text-primary);
  font-size: 13px;
  overflow-wrap: anywhere;
}

.history-list {
  display: grid;
  gap: 8px;
  padding: var(--space-lg);
}

.history-row {
  width: 100%;
  border: 1px solid var(--surface-border);
  background: var(--surface-default);
  border-radius: var(--radius-default);
  padding: 10px;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  text-align: left;
  cursor: pointer;
}

.history-row:hover {
  border-color: var(--info-cyan);
}

.history-row span {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.history-row small {
  color: var(--text-secondary);
  font-size: 11px;
}

:deep(.customer-detail-dialog .p-dialog-content) {
  background: var(--page-bg);
}

@media (max-width: 768px) {
  .customer-page {
    min-height: auto;
  }

  .table-shell {
    display: none;
  }

  .mobile-card-list {
    display: grid;
    gap: var(--space-md);
  }

  .mobile-customer-card {
    border: 1px solid var(--surface-border);
    border-radius: var(--radius-default);
    background: var(--surface-default);
    padding: var(--space-lg);
  }

  .mobile-card-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
  }

  .mobile-card-head h3,
  .mobile-card-head p,
  .mobile-note {
    margin: 0;
  }

  .mobile-card-head h3 {
    font-size: 15px;
  }

  .mobile-card-head p,
  .mobile-note {
    color: var(--text-secondary);
    font-size: 12px;
  }

  .mobile-note {
    margin-top: 10px;
  }

  .card-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
  }

  .card-actions .btn-pill {
    flex: 1;
    justify-content: center;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }

  .info-list div {
    grid-template-columns: 1fr;
    gap: 4px;
  }

  .history-row,
  .timeline-head {
    flex-direction: column;
  }
}
</style>
