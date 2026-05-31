<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { getCustomerUsageReport } from '../../api/financeReport'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import Dialog from 'primevue/dialog'

const toast = useToast()

const statusOptions = [
  { label: 'Semua Status', value: null },
  { label: 'Normal', value: 'Normal' },
  { label: 'Member', value: 'Member' },
  { label: 'Rent to Rent', value: 'Rent to Rent' },
  { label: 'Corporate', value: 'Corporate' },
  { label: 'Redflag', value: 'Redflag' },
  { label: 'Blacklist', value: 'Blacklist' },
]

const filters = ref({ search: '', status: null })

const rows = ref([])
const summary = ref({ total_pelanggan: 0, total_sewa: 0 })
const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const loading = ref(false)

const summaryCards = computed(() => [
  { label: 'Jumlah Pelanggan', value: summary.value.total_pelanggan, icon: 'pi pi-users' },
  { label: 'Total Sewa', value: summary.value.total_sewa, icon: 'pi pi-receipt' },
])

const formatNumber = (v) => new Intl.NumberFormat('id-ID').format(Number(v || 0))

const statusSeverity = (status) => {
  const map = {
    Normal: 'success',
    Member: 'info',
    'Rent to Rent': 'secondary',
    Corporate: 'help',
    Redflag: 'warning',
    Blacklist: 'danger',
  }
  return map[status] || 'secondary'
}

const buildParams = (page = 1) => ({
  search: filters.value.search || undefined,
  status: filters.value.status || undefined,
  per_page: meta.value.per_page,
  page,
})

const loadReport = async (page = 1) => {
  loading.value = true
  try {
    const { data } = await getCustomerUsageReport(buildParams(page))
    rows.value = data.data
    summary.value = data.summary
    meta.value = data.meta
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || 'Gagal memuat laporan', life: 4000 })
  } finally {
    loading.value = false
  }
}

const applyFilters = () => loadReport(1)

const resetFilters = () => {
  filters.value = { search: '', status: null }
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
    const { data } = await getCustomerUsageReport({ ...buildParams(page), customer_id: row.customer_id })
    txRows.value = data.data
    txMeta.value = data.meta
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal memuat transaksi', life: 4000 })
  } finally {
    txLoading.value = false
  }
}

const onTxPage = (e) => openTransactions(txEntity.value, e.page + 1)

onMounted(() => loadReport(1))
</script>

<template>
  <div class="page-container table-page-active customer-usage-report">
    <div class="page-header">
      <div class="header-left">
        <h1 class="text-h1">Laporan Pelanggan</h1>
        <p class="text-secondary text-xs">Jumlah sewa per pelanggan beserta kota booking.</p>
      </div>
    </div>

    <ProgressBar v-if="loading" mode="indeterminate" style="height: 4px" />

    <div class="list-tab-fill">
      <div class="filter-bar surface-card">
        <div class="filter-groups">
          <div class="filter-group filter-group-wide">
            <label>Cari Pelanggan</label>
            <span class="filter-search">
              <i class="pi pi-search" />
              <InputText v-model="filters.search" placeholder="Cari nama / kontak..." class="w-full" @keyup.enter="applyFilters" />
            </span>
          </div>
          <div class="filter-group">
            <label>Status Member</label>
            <Dropdown v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Semua Status" class="w-full md:w-48" @change="applyFilters" />
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
            <strong>{{ formatNumber(card.value) }}</strong>
          </div>
        </article>
      </div>

      <div class=" pt-3">
        <DataTable :value="rows" :loading="loading" lazy paginator scrollable scrollHeight="flex" :rows="meta.per_page" :totalRecords="meta.total" :first="(meta.current_page - 1) * meta.per_page" paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport" currentPageReportTemplate="Menampilkan {first} ke {last} dari {totalRecords} pelanggan" class="drent-datatable" stripedRows dataKey="customer_id" @page="onPage" @row-dblclick="(e) => openTransactions(e.data)">
          <template #empty>
            <div class="empty-state">Tidak ada data pelanggan.</div>
          </template>
          <Column header="Aksi" style="min-width: 7rem">
            <template #body="{ data }">
              <button class="btn-pill btn-secondary btn-pill-compact" type="button" @click="openTransactions(data)">
                <i class="pi pi-list"></i> Transaksi
              </button>
            </template>
          </Column>
          <Column field="nama" header="Nama Pelanggan" style="min-width: 12rem">
            <template #body="{ data }"><strong>{{ data.nama }}</strong></template>
          </Column>
          <Column field="status" header="Status Member" style="min-width: 9rem">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="statusSeverity(data.status)" />
            </template>
          </Column>
          <Column field="kontak_1" header="Kontak" style="min-width: 10rem" />
          <Column field="kota" header="Kota Booking" style="min-width: 11rem" />
          <Column field="jumlah_sewa" header="Jumlah Sewa" style="min-width: 8rem">
            <template #body="{ data }"><span class="font-mono-numeric">{{ formatNumber(data.jumlah_sewa) }}x</span></template>
          </Column>
        </DataTable>
      </div>
    </div>

    <Dialog v-model:visible="showTx" modal :header="`Transaksi Pelanggan - ${txEntity?.nama || ''}`" :style="{ width: 'min(900px, 96vw)' }" class="custom-dialog">
      <DataTable :value="txRows" :loading="txLoading" lazy paginator :rows="txMeta.per_page" :totalRecords="txMeta.total" :first="(txMeta.current_page - 1) * txMeta.per_page" class="drent-datatable" stripedRows dataKey="id" @page="onTxPage">
        <template #empty>
          <div class="empty-state">Tidak ada transaksi.</div>
        </template>
        <Column field="kode_booking" header="Kode Booking" style="min-width: 9rem" />
        <Column field="status" header="Status" style="min-width: 8rem" />
        <Column field="kota" header="Kota" style="min-width: 8rem" />
        <Column header="Tgl Sewa" style="min-width: 8rem"><template #body="{ data }">{{ formatDate(data.tgl_sewa) }}</template></Column>
        <Column header="Tgl Kembali" style="min-width: 8rem"><template #body="{ data }">{{ formatDate(data.tgl_kembali) }}</template></Column>
        <Column field="units" header="Unit" style="min-width: 10rem" />
      </DataTable>
    </Dialog>
  </div>
</template>

<style scoped>
.customer-usage-report {
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

@media (max-width: 980px) {
  .summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
