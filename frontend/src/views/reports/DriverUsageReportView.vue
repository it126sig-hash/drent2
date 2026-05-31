<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { getDriverUsageReport } from '../../api/financeReport'
import { fetchCities } from '../../api/city'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import AutoComplete from 'primevue/autocomplete'
import DatePicker from 'primevue/datepicker'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import Dialog from 'primevue/dialog'

const toast = useToast()

const tabs = [
  { label: 'All In', value: 'all_in' },
  { label: 'Non All In', value: 'non_all_in' },
]
const activeTab = ref(0)

const now = new Date()
const filters = ref({
  search: '',
  date_from: new Date(now.getFullYear(), now.getMonth(), 1),
  date_to: new Date(now.getFullYear(), now.getMonth() + 1, 0),
})
const selectedCity = ref(null)

const cities = ref([])
const loadingCities = ref(false)

const rows = ref([])
const summary = ref({ total_driver: 0, total_transaksi: 0, total_hari: 0, total_pendapatan: 0 })
const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const loading = ref(false)

const summaryCards = computed(() => [
  { label: 'Jumlah Driver', value: summary.value.total_driver, money: false, icon: 'pi pi-id-card' },
  { label: 'Total Transaksi', value: summary.value.total_transaksi, money: false, icon: 'pi pi-receipt' },
  { label: 'Total Hari Jalan', value: summary.value.total_hari, money: false, icon: 'pi pi-calendar' },
  { label: 'Total Pendapatan', value: summary.value.total_pendapatan, money: true, icon: 'pi pi-wallet' },
])

const formatNumber = (v) => new Intl.NumberFormat('id-ID').format(Number(v || 0))
const formatCurrency = (v) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0))

const toDateParam = (d) => {
  if (!d) return null
  const date = d instanceof Date ? d : new Date(d)
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

const buildParams = (page = 1) => ({
  mode: tabs[activeTab.value].value,
  search: filters.value.search || undefined,
  date_from: toDateParam(filters.value.date_from) || undefined,
  date_to: toDateParam(filters.value.date_to) || undefined,
  kota: selectedCity.value?.nama || undefined,
  per_page: meta.value.per_page,
  page,
})

const loadReport = async (page = 1) => {
  loading.value = true
  try {
    const { data } = await getDriverUsageReport(buildParams(page))
    rows.value = data.data
    summary.value = data.summary
    meta.value = data.meta
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || 'Gagal memuat laporan', life: 4000 })
  } finally {
    loading.value = false
  }
}

const switchTab = (idx) => {
  if (activeTab.value === idx) return
  activeTab.value = idx
  loadReport(1)
}

const applyFilters = () => loadReport(1)

const resetFilters = () => {
  filters.value = {
    search: '',
    date_from: new Date(now.getFullYear(), now.getMonth(), 1),
    date_to: new Date(now.getFullYear(), now.getMonth() + 1, 0),
  }
  selectedCity.value = null
  loadReport(1)
}

const onPage = (e) => loadReport(e.page + 1)

const showTx = ref(false)
const txEntity = ref(null)
const txRows = ref([])
const txMeta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const txLoading = ref(false)

const formatDate = (v) => (v ? new Date(v).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-')

const openTransactions = async (row, page = 1) => {
  txEntity.value = row
  showTx.value = true
  txLoading.value = true
  try {
    const { data } = await getDriverUsageReport({ ...buildParams(page), driver_id: row.driver_id })
    txRows.value = data.data
    txMeta.value = data.meta
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal memuat transaksi', life: 4000 })
  } finally {
    txLoading.value = false
  }
}

const onTxPage = (e) => openTransactions(txEntity.value, e.page + 1)

const searchCities = async (e) => {
  loadingCities.value = true
  try {
    const { data } = await fetchCities({ search: e.query || '', per_page: 20, is_active: true })
    cities.value = data.data
  } catch (_) {
    cities.value = []
  } finally {
    loadingCities.value = false
  }
}

onMounted(() => loadReport(1))
</script>

<template>
  <div class="page-container table-page-active driver-usage-report">
    <div class="page-header">
      <div class="header-left">
        <h1 class="text-h1">Laporan Penggunaan Driver</h1>
        <p class="text-secondary text-xs">Jumlah transaksi, hari jalan, dan pendapatan modal per driver pada periode tertentu.</p>
      </div>
      <div class="header-actions">
        <div class="pill-toggle">
          <button v-for="(tab, idx) in tabs" :key="tab.value" class="toggle-item" :class="{ active: activeTab === idx }" @click="switchTab(idx)">
            {{ tab.label }}
          </button>
        </div>
      </div>
    </div>

    <ProgressBar v-if="loading" mode="indeterminate" style="height: 4px" />

    <div class="list-tab-fill">
      <div class="filter-bar surface-card">
        <div class="filter-groups">
          <div class="filter-group filter-group-wide">
            <label>Cari Driver</label>
            <span class="filter-search">
              <i class="pi pi-search" />
              <InputText v-model="filters.search" placeholder="Cari nama / kontak driver..." class="w-full" @keyup.enter="applyFilters" />
            </span>
          </div>
          <div class="filter-group">
            <label>Kota</label>
            <AutoComplete v-model="selectedCity" :suggestions="cities" @complete="searchCities" optionLabel="nama" placeholder="Semua Kota" dropdown forceSelection :loading="loadingCities" class="w-full md:w-48" inputClass="w-full" @item-select="applyFilters" @clear="applyFilters" />
          </div>
          <div class="filter-group">
            <label>Mulai</label>
            <DatePicker v-model="filters.date_from" dateFormat="yy-mm-dd" placeholder="Dari" showButtonBar class="w-full md:w-36" />
          </div>
          <div class="filter-group">
            <label>Sampai</label>
            <DatePicker v-model="filters.date_to" dateFormat="yy-mm-dd" placeholder="Sampai" showButtonBar class="w-full md:w-36" />
          </div>
        </div>
        <div class="filter-actions">
          <button class="btn-pill btn-primary" type="button" :disabled="loading" @click="applyFilters">
            <i class="pi pi-filter"></i> Filter
          </button>
          <button class="btn-pill btn-secondary" type="button" :disabled="loading" @click="resetFilters">
            <i class="pi pi-times"></i> Reset
          </button>
        </div>
      </div>

      <div class="summary-grid">
        <article v-for="card in summaryCards" :key="card.label" class="summary-card app-card">
          <div class="summary-icon"><i :class="card.icon"></i></div>
          <div>
            <span>{{ card.label }}</span>
            <strong>{{ card.money ? formatCurrency(card.value) : formatNumber(card.value) }}</strong>
          </div>
        </article>
      </div>

      <div class="table-shell pt-3">
        <DataTable :value="rows" :loading="loading" lazy paginator scrollable scrollHeight="flex" :rows="meta.per_page" :totalRecords="meta.total" :first="(meta.current_page - 1) * meta.per_page" paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport" currentPageReportTemplate="Menampilkan {first} ke {last} dari {totalRecords} driver" class="drent-datatable" stripedRows dataKey="driver_id" @page="onPage" @row-dblclick="(e) => openTransactions(e.data)">
          <template #empty>
            <div class="empty-state">Tidak ada data penggunaan driver pada periode ini.</div>
          </template>
          <Column header="Aksi" style="min-width: 7rem">
            <template #body="{ data }">
              <button class="btn-pill btn-secondary btn-pill-compact" type="button" @click="openTransactions(data)">
                <i class="pi pi-list"></i> Transaksi
              </button>
            </template>
          </Column>
          <Column field="nama" header="Nama Driver" style="min-width: 12rem">
            <template #body="{ data }"><strong>{{ data.nama }}</strong></template>
          </Column>
          <Column field="kontak_1" header="Kontak" style="min-width: 10rem" />
          <Column field="kota" header="Kota" style="min-width: 9rem" />
          <Column field="is_tetap" header="Tipe" style="min-width: 8rem">
            <template #body="{ data }">
              <Tag v-if="!data.driver_id" value="Lepas Kunci" severity="warn" />
              <Tag v-else :value="data.is_tetap ? 'Tetap' : 'Non-Tetap'" :severity="data.is_tetap ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column field="jumlah_transaksi" header="Transaksi" style="min-width: 8rem">
            <template #body="{ data }"><span class="font-mono-numeric">{{ formatNumber(data.jumlah_transaksi) }}x</span></template>
          </Column>
          <Column field="total_hari" header="Hari Jalan" style="min-width: 8rem">
            <template #body="{ data }"><span class="font-mono-numeric">{{ formatNumber(data.total_hari) }} hari</span></template>
          </Column>
          <Column field="total_pendapatan" header="Total Pendapatan" style="min-width: 12rem">
            <template #body="{ data }"><span class="font-mono-numeric amount positive">{{ formatCurrency(data.total_pendapatan) }}</span></template>
          </Column>
        </DataTable>
      </div>
    </div>

    <Dialog v-model:visible="showTx" modal :header="`Transaksi Driver - ${txEntity?.nama || ''}`" :style="{ width: 'min(960px, 96vw)' }" class="custom-dialog">
      <DataTable :value="txRows" :loading="txLoading" lazy paginator :rows="txMeta.per_page" :totalRecords="txMeta.total" :first="(txMeta.current_page - 1) * txMeta.per_page" class="drent-datatable" stripedRows dataKey="id" @page="onTxPage">
        <template #empty>
          <div class="empty-state">Tidak ada transaksi.</div>
        </template>
        <Column field="kode_booking" header="Kode Booking" style="min-width: 9rem" />
        <Column field="customer" header="Pelanggan" style="min-width: 10rem" />
        <Column field="no_polisi" header="No. Polisi" style="min-width: 8rem" />
        <Column field="kota" header="Kota" style="min-width: 8rem" />
        <Column header="Tgl Sewa" style="min-width: 8rem"><template #body="{ data }">{{ formatDate(data.tgl_sewa) }}</template></Column>
        <Column header="Tgl Kembali" style="min-width: 8rem"><template #body="{ data }">{{ formatDate(data.tgl_kembali) }}</template></Column>
        <Column field="hari" header="Hari" style="min-width: 5rem" />
        <Column header="Gaji" style="min-width: 9rem"><template #body="{ data }"><span class="font-mono-numeric amount positive">{{ formatCurrency(data.gaji) }}</span></template></Column>
      </DataTable>
    </Dialog>
  </div>
</template>

<style scoped>
.driver-usage-report {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}

.summary-card {
  padding: 14px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.summary-card span {
  display: block;
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.summary-card strong {
  display: block;
  margin-top: 4px;
  font-family: var(--font-mono);
  font-size: 16px;
  color: var(--text-primary);
  font-variant-numeric: tabular-nums;
}

.summary-icon {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-default);
  display: grid;
  place-items: center;
  background: var(--card-bg);
  color: var(--text-secondary);
  flex-shrink: 0;
}

.empty-state {
  padding: 38px;
  text-align: center;
  color: var(--text-tertiary);
}

.amount {
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.amount.positive {
  color: var(--positive);
}

@media (max-width: 980px) {
  .summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
