<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { format } from 'date-fns'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Paginator from 'primevue/paginator'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue'
import { getTransactions, getTransactionDetail } from '../../api/transaction'
import { fetchCities } from '../../api/city'

const router = useRouter()
const toast = useToast()

const activeTab = ref('semua') // semua, waiting_list, selesai, batal
const transactions = ref([])
const loading = ref(false)
const detailLoading = ref(false)
const showDetailDialog = ref(false)
const selectedTransaction = ref(null)

const pagination = ref({
  current_page: 1,
  per_page: 15,
  total: 0,
})

const filters = ref({
  search: '',
  kota: null,
  date_from: null,
  date_to: null,
})

const cities = ref([])
const citiesLoading = ref(false)

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)

const formatDateTime = (value) => {
  if (!value) return '-'
  try {
    return format(new Date(value), 'dd MMM yyyy HH:mm')
  } catch (e) {
    return value
  }
}

const formatDate = (value) => {
  if (!value) return '-'
  try {
    return format(new Date(value), 'dd MMM yyyy')
  } catch (e) {
    return value
  }
}

const toApiDate = (value) => {
  if (!value) return null
  return format(new Date(value), 'yyyy-MM-dd')
}

const fetchTransactionList = async (page = 1) => {
  loading.value = true
  try {
    const statusesMap = {
      semua: ['waiting_list', 'selesai', 'batal'],
      waiting_list: ['waiting_list'],
      selesai: ['selesai'],
      batal: ['batal'],
    }

    const params = {
      page,
      per_page: pagination.value.per_page,
      status: statusesMap[activeTab.value].join(','),
      search: filters.value.search || undefined,
      kota: filters.value.kota || undefined,
      date_from: toApiDate(filters.value.date_from) || undefined,
      date_to: toApiDate(filters.value.date_to) || undefined,
    }

    const response = await getTransactions(params)
    transactions.value = response.data.data
    pagination.value.total = response.data.meta?.total || response.data.total || 0
    pagination.value.current_page = response.data.meta?.current_page || page
  } catch (error) {
    console.error(error)
    toast.add({
      severity: 'error',
      summary: 'Gagal Memuat Data',
      detail: error.response?.data?.message || 'Terjadi kesalahan pada server.',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}

const loadCities = async () => {
  citiesLoading.value = true
  try {
    const response = await fetchCities({ per_page: 100 })
    cities.value = response.data.data.map(c => ({ label: c.nama, value: c.nama }))
  } catch (error) {
    console.error('Failed to load cities', error)
  } finally {
    citiesLoading.value = false
  }
}

const openDetail = async (id) => {
  showDetailDialog.value = true
  detailLoading.value = true
  selectedTransaction.value = null
  try {
    const response = await getTransactionDetail(id)
    selectedTransaction.value = response.data.data
  } catch (error) {
    console.error(error)
    toast.add({
      severity: 'error',
      summary: 'Gagal Memuat Detail',
      detail: error.response?.data?.message || 'Terjadi kesalahan.',
      life: 3000,
    })
    showDetailDialog.value = false
  } finally {
    detailLoading.value = false
  }
}

const applyFilters = () => {
  pagination.value.current_page = 1
  fetchTransactionList(1)
}

const resetFilters = () => {
  filters.value = {
    search: '',
    kota: null,
    date_from: null,
    date_to: null,
  }
  applyFilters()
}

const onPageChange = (event) => {
  pagination.value.current_page = event.page + 1
  fetchTransactionList(pagination.value.current_page)
}

const switchTab = (tab) => {
  activeTab.value = tab
  pagination.value.current_page = 1
  fetchTransactionList(1)
}

const computedKpis = computed(() => {
  let incoming = 0
  let outgoing = 0
  let margin = 0
  transactions.value.forEach(t => {
    incoming += Number(t.total_biaya || 0)
    outgoing += Number(t.total_pengeluaran || 0)
    margin += Number(t.margin || 0)
  })
  return { incoming, outgoing, margin }
})

onMounted(() => {
  fetchTransactionList(1)
  loadCities()
})
</script>

<template>
  <div class="page-container table-page-active">
    <!-- Header -->
    <div class="page-header flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="header-left">
        <h1 class="page-title text-h1 font-headline font-bold">List Transaksi</h1>
        <p class="page-subtitle text-secondary font-body mt-1">Kelola dan pantau seluruh transaksi keuangan rental
          secara real-time.</p>
      </div>
      <div class="header-actions">
        <div class="tab-toggle-container">
          <div class="pill-toggle">
            <button class="toggle-item" :class="{ active: activeTab === 'semua' }" @click="switchTab('semua')">
              Semua
            </button>
            <button class="toggle-item" :class="{ active: activeTab === 'waiting_list' }"
              @click="switchTab('waiting_list')">
              Waiting List
            </button>
            <button class="toggle-item" :class="{ active: activeTab === 'selesai' }" @click="switchTab('selesai')">
              Selesai
            </button>
            <button class="toggle-item" :class="{ active: activeTab === 'batal' }" @click="switchTab('batal')">
              Batal
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- KPI Summary Row -->
    <div class="kpi-container mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="kpi-tile">
        <div class="kpi-icon-wrapper text-positive">
          <i class="pi pi-arrow-down-left"></i>
        </div>
        <div class="kpi-content">
          <span class="kpi-label">Total Pemasukan (Halaman Ini)</span>
          <span class="kpi-value font-mono-numeric text-positive">{{ formatCurrency(computedKpis.incoming) }}</span>
          <span class="kpi-delta text-positive"><i class="pi pi-check-circle text-xs mr-0.5"></i>Lunas & Booking</span>
        </div>
      </div>

      <div class="kpi-tile">
        <div class="kpi-icon-wrapper text-negative">
          <i class="pi pi-arrow-up-right"></i>
        </div>
        <div class="kpi-content">
          <span class="kpi-label">Total Pengeluaran (Halaman Ini)</span>
          <span class="kpi-value font-mono-numeric text-negative">{{ formatCurrency(computedKpis.outgoing) }}</span>
          <span class="kpi-delta text-negative"><i class="pi pi-info-circle text-xs mr-0.5"></i>Operasional & R2R</span>
        </div>
      </div>

      <div class="kpi-tile">
        <div class="kpi-icon-wrapper" :class="computedKpis.margin >= 0 ? 'text-positive' : 'text-negative'">
          <i class="pi pi-chart-line"></i>
        </div>
        <div class="kpi-content">
          <span class="kpi-label">Margin Bersih (Halaman Ini)</span>
          <span class="kpi-value font-mono-numeric"
            :class="computedKpis.margin >= 0 ? 'text-positive' : 'text-negative'">
            {{ computedKpis.margin >= 0 ? '+' : '' }}{{ formatCurrency(computedKpis.margin) }}
          </span>
          <span class="kpi-delta" :class="computedKpis.margin >= 0 ? 'text-positive' : 'text-negative'">
            <i class="pi" :class="computedKpis.margin >= 0 ? 'pi-trending-up' : 'pi-trending-down'"></i> Estimasi Profit
          </span>
        </div>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar mb-4">
      <div class="filter-groups">
        <!-- Search -->
        <div class="filter-group filter-group-wide">
          <label for="search-input">Cari</label>
          <div class="filter-search">
            <i class="pi pi-search"></i>
            <InputText id="search-input" v-model="filters.search" placeholder="Cari Kode / Konsumen..."
              @keyup.enter="applyFilters" />
          </div>
        </div>

        <!-- Kota -->
        <div class="filter-group" style="min-width: 160px">
          <label for="city-filter">Kota</label>
          <Dropdown id="city-filter" v-model="filters.kota" :options="cities" optionLabel="label" optionValue="value"
            placeholder="Pilih Kota" showClear :loading="citiesLoading" class="w-full" />
        </div>

        <!-- Date Range -->
        <div class="filter-group" style="min-width: 140px">
          <label for="date-from">Dari Tanggal</label>
          <DatePicker id="date-from" v-model="filters.date_from" dateFormat="dd/mm/yy" placeholder="Pilih Tanggal"
            showIcon fluid />
        </div>
        <div class="filter-group" style="min-width: 140px">
          <label for="date-to">Sampai Tanggal</label>
          <DatePicker id="date-to" v-model="filters.date_to" dateFormat="dd/mm/yy" placeholder="Pilih Tanggal" showIcon
            fluid />
        </div>
      </div>

      <div class="filter-actions mt-3 md:mt-0">
        <button class="btn-pill btn-secondary" @click="resetFilters" :disabled="loading">
          <i class="pi pi-refresh"></i>
          Reset
        </button>
        <button class="btn-pill btn-primary" @click="applyFilters" :disabled="loading">
          <i class="pi pi-filter"></i>
          Filter
        </button>
      </div>
    </div>

    <!-- Data Table Shell -->
    <div class="table-shell app-card">
      <DataTable :value="transactions" dataKey="id" :loading="loading" scrollable scrollHeight="flex"
        class="w-full drent-datatable">
        <!-- Columns -->
        <Column header="Kode Booking" style="min-width: 11rem">
          <template #body="{ data }">
            <button class="booking-code-link font-headline font-semibold text-primary text-sm"
              @click="openDetail(data.id)">
              {{ data.kode_booking }}
            </button>
            <div class="mt-1">
              <BookingStatusBadge :status="data.status" />
            </div>
          </template>
        </Column>

        <Column header="Konsumen" style="min-width: 13rem">
          <template #body="{ data }">
            <div class="customer-info-cell">
              <span class="font-body font-semibold text-primary block text-sm">{{ data.customer?.nama }}</span>
              <div class="mt-1">
                <span v-if="data.customer?.status === 'active'" class="status-badge success">Member</span>
                <span v-else class="status-badge neutral">Umum</span>
              </div>
            </div>
          </template>
        </Column>

        <Column header="Unit" style="min-width: 11rem">
          <template #body="{ data }">
            <template v-if="data.unit">
              <span class="font-body font-semibold text-primary block text-sm">{{ data.unit.tipe }}</span>
              <span class="font-mono-numeric text-secondary text-xs block mt-0.5 font-mono">{{ data.unit.no_polisi
                }}</span>
              <div class="mt-1">
                <span v-if="data.unit.is_rent_to_rent" class="status-badge info">R2R ({{ data.unit.pemilik }})</span>
                <span v-else class="status-badge success">Internal</span>
              </div>
            </template>
            <span v-else class="text-secondary">-</span>
          </template>
        </Column>

        <Column header="Periode" style="min-width: 16rem">
          <template #body="{ data }">
            <template v-if="data.periode">
              <div class="period-cell">
                <div class="flex items-center gap-1.5 mb-0.5">
                  <i class="pi pi-map-marker text-[10px] text-secondary"></i>
                  <span class="text-primary font-headline font-semibold text-xs">{{ data.kota }}</span>
                  <span v-if="data.periode.tujuan || data.tujuan" class="text-secondary font-body font-medium text-xs">
                    ({{ data.periode.tujuan || data.tujuan }})
                  </span>
                </div>
                <div class="text-primary text-xs font-mono-numeric flex items-center gap-1">
                  <span>{{ formatDate(data.periode.tgl_sewa) }}</span>
                  <i class="pi pi-arrow-right text-secondary" style="font-size: 8px"></i>
                  <span>{{ formatDate(data.periode.tgl_kembali) }}</span>
                </div>
                <span class="text-secondary text-xs block mt-1 font-body">{{ data.periode.paket }}</span>
              </div>
            </template>
            <span v-else class="text-secondary">-</span>
          </template>
        </Column>

        <Column header="Total Biaya" style="min-width: 11rem" headerClass="justify-end" bodyClass="text-right">
          <template #body="{ data }">
            <div class="flex flex-col items-end gap-1">
              <span class="font-mono-numeric font-semibold text-primary text-sm">{{ formatCurrency(data.total_biaya)
                }}</span>
              <span v-if="data.sisa_tagihan <= 0" class="status-badge success">
                Lunas
              </span>
              <span v-else class="status-badge error">
                Belum Lunas
              </span>
            </div>
          </template>
        </Column>

        <Column header="Total Pengeluaran" style="min-width: 14rem" headerClass="justify-end" bodyClass="text-right">
          <template #body="{ data }">
            <div class="flex flex-col items-end gap-1">
              <span class="font-mono-numeric font-semibold text-primary text-sm">{{
                formatCurrency(data.total_pengeluaran) }}</span>
              <div class="text-xs text-secondary flex items-center gap-2 mt-0.5">
                <span class="font-mono-numeric bg-[var(--card-bg)] px-1.5 py-0.5 rounded text-[11px]">R2R: {{
                  formatCurrency(data.total_rent_to_rent) }}</span>
                <span class="font-mono-numeric bg-[var(--card-bg)] px-1.5 py-0.5 rounded text-[11px]">Ops: {{
                  formatCurrency(data.total_operasional) }}</span>
              </div>
            </div>
          </template>
        </Column>

        <Column header="Margin" style="min-width: 11rem" headerClass="justify-end" bodyClass="text-right">
          <template #body="{ data }">
            <span class="font-mono-numeric font-semibold text-sm"
              :class="data.margin >= 0 ? 'text-positive' : 'text-negative'">
              {{ data.margin >= 0 ? '+' : '' }}{{ formatCurrency(data.margin) }}
            </span>
          </template>
        </Column>

        <template #empty>
          <div class="py-8 text-center text-secondary font-body">
            Belum ada transaksi pada status ini.
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Paginator -->
    <div class="mt-4 flex justify-end" v-if="pagination.total > 0">
      <Paginator :rows="pagination.per_page" :totalRecords="pagination.total"
        :first="(pagination.current_page - 1) * pagination.per_page" @page="onPageChange" />
    </div>

    <!-- Detail Dialog -->
    <Dialog v-model:visible="showDetailDialog" modal header="Detail Transaksi"
      :style="{ width: '80vw', maxWidth: '1000px' }" class="transaction-detail-dialog custom-dialog">
      <div v-if="detailLoading" class="flex justify-center items-center py-12">
        <i class="pi pi-spin pi-spinner text-3xl text-secondary"></i>
      </div>

      <div v-else-if="selectedTransaction" class="flex flex-col gap-6">
        <!-- Header Info Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Rental Card -->
          <div class="detail-card p-4 flex flex-col gap-2">
            <h3 class="text-xs font-semibold text-secondary uppercase tracking-wider font-body">Rental & Konsumen</h3>
            <div>
              <span class="text-sm font-semibold text-primary block font-headline">{{ selectedTransaction.kode_booking
                }}</span>
              <span class="text-xs text-secondary mt-1 block font-body">Konsumen: {{ selectedTransaction.customer?.nama
                }}</span>
              <span class="text-xs text-secondary block font-body">Kota: {{ selectedTransaction.customer?.kota }}</span>
            </div>
            <div class="mt-1">
              <BookingStatusBadge :status="selectedTransaction.status" />
            </div>
          </div>

          <!-- Unit Card -->
          <div class="detail-card p-4 flex flex-col gap-2">
            <h3 class="text-xs font-semibold text-secondary uppercase tracking-wider font-body">Kendaraan</h3>
            <div v-if="selectedTransaction.unit">
              <span class="text-sm font-semibold text-primary block font-headline">{{ selectedTransaction.unit.tipe
                }}</span>
              <span class="text-xs text-secondary mt-0.5 block font-mono">{{ selectedTransaction.unit.no_polisi
                }}</span>
              <span class="text-xs text-secondary block font-body">Pemilik: {{ selectedTransaction.unit.pemilik
                }}</span>
            </div>
            <div class="mt-1" v-if="selectedTransaction.unit">
              <span v-if="selectedTransaction.unit.is_rent_to_rent" class="status-badge info">R2R ({{
                selectedTransaction.unit.pemilik }})</span>
              <span v-else class="status-badge success">Internal</span>
            </div>
          </div>

          <!-- Periode Card -->
          <div class="detail-card p-4 flex flex-col gap-2">
            <h3 class="text-xs font-semibold text-secondary uppercase tracking-wider font-body">Periode & Tujuan</h3>
            <div v-if="selectedTransaction.periode">
              <span class="text-xs text-primary block font-semibold font-mono-numeric">
                {{ formatDateTime(selectedTransaction.periode?.tgl_sewa) }}
              </span>
              <span class="text-xs text-primary block font-semibold mt-0.5 font-mono-numeric">
                s/d {{ formatDateTime(selectedTransaction.periode?.tgl_kembali) }}
              </span>
              <span class="text-xs text-secondary block mt-1 font-body">Paket: {{ selectedTransaction.periode?.paket
                }}</span>
              <span class="text-xs text-secondary block font-body" v-if="selectedTransaction.kota">Kota: {{
                selectedTransaction.kota }}</span>
              <span class="text-xs text-secondary block font-semibold font-body"
                v-if="selectedTransaction.periode?.tujuan || selectedTransaction.tujuan">
                Tujuan: {{ selectedTransaction.periode?.tujuan || selectedTransaction.tujuan }}
              </span>
            </div>
          </div>
        </div>

        <!-- History Table Section -->
        <div class="flex flex-col gap-3">
          <h3 class="text-sm font-semibold text-primary font-headline">Riwayat Keuangan (Pemasukan & Pengeluaran)</h3>

          <div class="table-shell overflow-hidden bg-white border border-[var(--surface-border)] rounded-[10px]">
            <DataTable :value="selectedTransaction.history" scrollable scrollHeight="300px"
              class="w-full drent-datatable">
              <Column header="Tanggal" style="min-width: 11rem">
                <template #body="{ data }">
                  <span class="text-primary text-xs font-mono-numeric">{{ formatDateTime(data.date) }}</span>
                </template>
              </Column>

              <Column header="Kategori" style="min-width: 12rem">
                <template #body="{ data }">
                  <span v-if="data.category === 'pembayaran_booking'" class="status-badge success">Pembayaran
                    Booking</span>
                  <span v-else-if="data.category === 'rent_to_rent'" class="status-badge info">Rent to Rent</span>
                  <span v-else-if="data.category === 'dana_operasional'" class="status-badge warning">Dana →
                    Driver</span>
                  <span v-else-if="data.category === 'sisa_dana'" class="status-badge success">Sisa Dana Kembali</span>
                  <span v-else-if="data.category === 'bon_operasional'"
                    class="status-badge neutral">Bon/Reimburse</span>
                  <span v-else-if="data.category === 'operasional'" class="status-badge error">Realisasi Langsung</span>
                  <span v-else class="status-badge neutral">{{ data.category }}</span>
                </template>
              </Column>

              <Column header="Keterangan" style="min-width: 16rem">
                <template #body="{ data }">
                  <span class="text-primary text-xs font-body">{{ data.description }}</span>
                </template>
              </Column>

              <Column header="Tipe" style="min-width: 8rem">
                <template #body="{ data }">
                  <span v-if="data.type === 'pemasukan'" class="status-badge success">Pemasukan</span>
                  <span v-else-if="data.type === 'pengeluaran'" class="status-badge error">Pengeluaran</span>
                  <span v-else-if="data.type === 'info'" class="status-badge neutral"
                    title="Rincian penggunaan dana driver (tidak mengurangi kas tambahan)">Info Bon</span>
                </template>
              </Column>

              <Column header="Jumlah" style="min-width: 10rem" headerClass="justify-end" bodyClass="text-right">
                <template #body="{ data }">
                  <span class="font-mono-numeric font-semibold text-xs" :class="{
                    'text-positive': data.type === 'pemasukan',
                    'text-negative': data.type === 'pengeluaran',
                    'text-secondary': data.type === 'info',
                  }">
                    <template v-if="data.type === 'pemasukan'">+{{ formatCurrency(data.amount) }}</template>
                    <template v-else-if="data.type === 'pengeluaran'">{{ formatCurrency(data.amount) }}</template>
                    <template v-else>({{ formatCurrency(data.amount) }})</template>
                  </span>
                </template>
              </Column>
            </DataTable>
            <div v-if="!selectedTransaction.history?.length" class="py-8 text-center text-secondary font-body text-xs">
              Belum ada riwayat pemasukan/pengeluaran untuk transaksi ini.
            </div>
          </div>
        </div>

        <!-- Summary Footer Card -->
        <div class="summary-footer-panel p-4 flex flex-col md:flex-row justify-between gap-4 mt-2">
          <div class="flex flex-col gap-1">
            <span class="text-[11px] text-secondary uppercase font-semibold font-body">Total Pemasukan</span>
            <span class="text-base font-bold font-mono-numeric text-positive">
              {{ formatCurrency(selectedTransaction.summary?.total_pemasukan) }}
            </span>
          </div>
          <div class="flex flex-col gap-1">
            <span class="text-[11px] text-secondary uppercase font-semibold font-body">Total Pengeluaran (Kas)</span>
            <span class="text-base font-bold font-mono-numeric text-negative">
              {{ formatCurrency(selectedTransaction.summary?.total_pengeluaran) }}
            </span>
            <span v-if="selectedTransaction.summary?.total_bon_info > 0"
              class="text-[11px] text-secondary font-mono-numeric mt-0.5"
              title="Rincian realisasi bon dari dana driver. Sudah termasuk dalam dana yang diserahkan.">
              (incl. bon: {{ formatCurrency(selectedTransaction.summary?.total_bon_info) }})
            </span>
          </div>
          <div
            class="flex flex-col gap-1 border-t md:border-t-0 md:border-l border-[var(--surface-border)] pt-3 md:pt-0 md:pl-6">
            <span class="text-[11px] text-secondary uppercase font-semibold font-body">Margin Bersih</span>
            <span class="text-base font-bold font-mono-numeric"
              :class="selectedTransaction.summary?.margin >= 0 ? 'text-positive' : 'text-negative'">
              {{ selectedTransaction.summary?.margin >= 0 ? '+' : '' }}{{
                formatCurrency(selectedTransaction.summary?.margin) }}
            </span>
          </div>
        </div>
      </div>

      <template #footer>
        <button class="btn-pill btn-secondary" @click="showDetailDialog = false">
          Tutup
        </button>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
/* === Custom Layout Utilities === */
.page-title {
  color: var(--text-primary);
  font-size: 20px;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.page-subtitle {
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 400;
}

/* === KPI Tiles === */
.kpi-container {
  display: grid;
  gap: var(--space-lg);
}

.kpi-tile {
  background: var(--surface-default);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  padding: 14px 16px;
  box-shadow: var(--shadow-tile);
  display: flex;
  align-items: center;
  gap: var(--space-lg);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kpi-tile:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(26, 29, 46, 0.05);
}

.kpi-icon-wrapper {
  width: 38px;
  height: 38px;
  border-radius: var(--radius-full);
  background: var(--card-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}

.kpi-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.kpi-label {
  font-family: var(--font-body);
  font-size: 11px;
  font-weight: 500;
  color: var(--text-secondary);
}

.kpi-value {
  font-family: var(--font-mono);
  font-size: 18px;
  font-weight: 700;
  letter-spacing: -0.01em;
}

.kpi-delta {
  font-family: var(--font-body);
  font-size: 10px;
  font-weight: 600;
  display: flex;
  align-items: center;
}

/* === Custom Filter Bar === */
.filter-bar {
  background: var(--surface-default);
  border: var(--card-border);
  border-radius: var(--radius-default);
  box-shadow: var(--shadow-tile);
  padding: 12px 16px;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-lg);
  flex-wrap: wrap;
}

.filter-group label {
  font-family: var(--font-body);
  font-size: 11px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-left: 2px;
}

/* === Pill Toggle Overrides === */
.toggle-item {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.toggle-item:hover {
  transform: translateY(-0.5px);
}

.toggle-item:active {
  transform: translateY(0.5px);
}

/* === Overriding Standard Status Badges locally === */
.status-badge {
  padding: 4px 8px !important;
  border-radius: var(--radius-sm) !important;
  font-size: 11px !important;
}

.status-badge.success {
  background-color: #E6F6EC !important;
  color: #147239 !important;
}

.status-badge.error {
  background-color: #FCEAE9 !important;
  color: #B02A24 !important;
  border: none !important;
}

.status-badge.warning {
  background-color: #FDF4D9 !important;
  color: #8C660A !important;
}

.status-badge.info {
  background-color: #E1F4F6 !important;
  color: #085A66 !important;
}

.status-badge.neutral {
  background-color: #E4E8F3 !important;
  color: #4A5060 !important;
}

/* === Links === */
.booking-code-link {
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  color: var(--text-primary);
  font-family: var(--font-headline);
  transition: color 0.2s ease;
  text-decoration: underline;
  text-underline-offset: 2px;
  text-decoration-color: var(--neutral-4);
}

.booking-code-link:hover {
  color: var(--info-cyan);
  text-decoration-color: var(--info-cyan);
}

/* === Secondary Button Override === */
.btn-secondary {
  background-color: var(--surface-default) !important;
  color: var(--text-primary) !important;
  border: 1px solid var(--surface-border) !important;
  transition: all 0.2s ease;
}

.btn-secondary:hover {
  background-color: var(--card-bg-hover) !important;
}

/* === Detail Dialog Custom Styling === */
:deep(.p-dialog.custom-dialog) {
  border-radius: var(--radius-default) !important;
  overflow: hidden;
}

:deep(.custom-dialog .p-dialog-header) {
  border-bottom: 1px solid var(--surface-border) !important;
  padding: 14px 20px !important;
  background: var(--surface-default) !important;
}

:deep(.custom-dialog .p-dialog-content) {
  padding: 20px !important;
}

:deep(.custom-dialog .p-dialog-footer) {
  border-top: 1px solid var(--surface-border) !important;
  padding: 12px 20px !important;
  background: var(--surface-default) !important;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.detail-card {
  background: var(--surface-default);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  box-shadow: var(--shadow-tile);
  transition: border-color 0.2s ease;
}

.detail-card:hover {
  border-color: var(--neutral-6);
}

.summary-footer-panel {
  background: var(--card-bg);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
}

.font-mono-numeric {
  font-family: var(--font-mono);
}

@media (min-width: 1366px) {
  .filter-bar {
    flex-wrap: nowrap !important;
  }

  .filter-groups {
    flex-wrap: nowrap !important;
    gap: var(--space-md) !important;
  }

  .filter-actions {
    margin-top: 0 !important;
    flex-shrink: 0 !important;
  }
}
</style>
