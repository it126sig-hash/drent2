<script setup>
import { ref, onMounted, computed } from 'vue'
import { useDriver } from '../../composables/useDriver'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useAuthStore } from '../../stores/auth'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Paginator from 'primevue/paginator'
import Tag from 'primevue/tag'
import ConfirmDialog from 'primevue/confirmdialog'
import DriverFormDialog from '../../components/drivers/DriverFormDialog.vue'
import BalanceDialog from '../../components/drivers/BalanceDialog.vue'

const {
  drivers,
  loading,
  pagination,
  fetchAll,
  store,
  update,
  remove,
  changeBalance
} = useDriver()

const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const searchQuery = ref('')
const statusFilter = ref(null)
const typeFilter = ref(null)
const showDialog = ref(false)
const showBalanceDialog = ref(false)
const selectedDriver = ref(null)

const statusOptions = [
  { label: 'Semua Status', value: null },
  { label: 'Aktif', value: 'Aktif' },
  { label: 'Tidak Aktif', value: 'Tidak Aktif' }
]

const typeOptions = [
  { label: 'Semua Tipe', value: null },
  { label: 'Tetap', value: true },
  { label: 'Non-Tetap', value: false }
]

const canManage = computed(() => ['superadmin', 'admin_branch'].includes(authStore.user?.role))
const canEditBalance = computed(() => ['superadmin', 'finance'].includes(authStore.user?.role))

onMounted(() => {
  fetchData()
})

const fetchData = async () => {
  try {
    await fetchAll({
      search: searchQuery.value,
      status: statusFilter.value,
      is_tetap: typeFilter.value,
      branch_id: authStore.user?.branch_id
    })
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Gagal memuat data driver',
      life: 3000
    })
  }
}

const onSearch = () => {
  pagination.value.current_page = 1
  fetchData()
}

const onPageChange = (event) => {
  pagination.value.current_page = event.page + 1
  fetchData()
}

const openNew = () => {
  selectedDriver.value = null
  showDialog.value = true
}

const editDriver = (driver) => {
  selectedDriver.value = { ...driver }
  showDialog.value = true
}

const openBalance = (driver) => {
  selectedDriver.value = { ...driver }
  showBalanceDialog.value = true
}

const saveDriver = async (data) => {
  try {
    if (data.id) {
      await update(data.id, data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Data driver berhasil diperbarui', life: 3000 })
    } else {
      await store(data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Driver berhasil ditambahkan', life: 3000 })
    }
    showDialog.value = false
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.message || 'Terjadi kesalahan saat menyimpan data', life: 3000 })
  }
}

const saveBalance = async (newSaldo) => {
  try {
    await changeBalance(selectedDriver.value.id, newSaldo)
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Saldo driver berhasil diperbarui', life: 3000 })
    showBalanceDialog.value = false
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal memperbarui saldo', life: 3000 })
  }
}

const confirmDelete = (driver) => {
  confirm.require({
    message: `Apakah Anda yakin ingin menghapus driver "${driver.nama}"?`,
    header: 'Konfirmasi Hapus',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await remove(driver.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Driver berhasil dihapus', life: 3000 })
      } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal menghapus data', life: 3000 })
      }
    }
  })
}

const getStatusSeverity = (status) => {
  return status === 'Aktif' ? 'success' : 'danger'
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value)
}
</script>

<template>
  <div class="page-container table-page-active driver-list-page">
    <ConfirmDialog />

    <div class="page-header">
      <div class="header-left">
        <div class="header-copy">
          <h1 class="text-h1">Manajemen Driver</h1>
          <p>Kelola data driver tetap dan non-tetap serta saldo operasional</p>
        </div>
      </div>
      <div class="header-actions">
        <Button
          v-if="canManage"
          label="Tambah Driver"
          icon="pi pi-plus"
          class="btn-pill btn-primary"
          @click="openNew"
        />
      </div>
    </div>

    <div class="list-tab-fill">
      <div class="filter-bar surface-card">
        <div class="filter-groups">
          <div class="filter-group filter-group-wide">
            <label>Cari Driver</label>
            <span class="filter-search">
              <i class="pi pi-search" />
              <InputText
                v-model="searchQuery"
                placeholder="Cari nama atau kontak..."
                @input="onSearch"
                class="w-full"
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
              placeholder="Filter Status"
              @change="onSearch"
              class="status-filter"
            />
          </div>
          <div class="filter-group">
            <label>Tipe</label>
            <Dropdown
              v-model="typeFilter"
              :options="typeOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Filter Tipe"
              @change="onSearch"
              class="status-filter"
            />
          </div>
        </div>
      </div>

      <div class="table-shell driver-table-shell">
        <DataTable
          :value="drivers"
          :loading="loading"
          lazy
          paginator
          scrollable
          scrollHeight="flex"
          :rows="pagination.per_page"
          :totalRecords="pagination.total"
          :first="(pagination.current_page - 1) * pagination.per_page"
          paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
          currentPageReportTemplate="Menampilkan {first} ke {last} dari {totalRecords} data"
          responsiveLayout="scroll"
          class="drent-datatable driver-desktop-table"
          stripedRows
          @page="onPageChange"
        >
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-id-card"></i>
              <p>Belum ada data driver.</p>
            </div>
          </template>

          <Column header="Aksi" style="width: 8rem; text-align: center">
            <template #body="{ data }">
              <div class="action-pill-group">
                <button
                  v-if="canEditBalance"
                  type="button"
                  class="action-btn"
                  @click="openBalance(data)"
                  v-tooltip.top="'Update Saldo'"
                >
                  <i class="pi pi-wallet"></i>
                </button>
                <button type="button" class="action-btn" @click="editDriver(data)" v-tooltip.top="'Edit'">
                  <i class="pi pi-pencil"></i>
                </button>
                <button
                  v-if="canManage"
                  type="button"
                  class="action-btn action-btn-danger"
                  @click="confirmDelete(data)"
                  v-tooltip.top="'Hapus'"
                >
                  <i class="pi pi-trash"></i>
                </button>
              </div>
            </template>
          </Column>

          <Column field="nama" header="Driver" style="min-width: 200px">
            <template #body="{ data }">
              <div class="driver-info">
                <span class="driver-name">{{ data.nama }}</span>
                <Tag
                  :value="data.is_tetap ? 'Tetap' : 'Non-Tetap'"
                  :severity="data.is_tetap ? 'info' : 'warning'"
                  class="type-tag"
                />
              </div>
            </template>
          </Column>

          <Column field="kontak_1" header="Kontak" style="min-width: 150px" />

          <Column field="kota" header="Kota" style="min-width: 120px">
            <template #body="{ data }">
              {{ data.kota || '-' }}
            </template>
          </Column>

          <Column field="saldo" header="Saldo Operasional" style="min-width: 180px">
            <template #body="{ data }">
              <span class="amount-text">{{ formatCurrency(data.saldo) }}</span>
            </template>
          </Column>

          <Column field="status" header="Status" style="min-width: 120px">
            <template #body="{ data }">
              <Tag
                :severity="getStatusSeverity(data.status)"
                :value="data.status"
                class="status-tag"
              />
            </template>
          </Column>
        </DataTable>
      </div>

      <div class="mobile-card-list driver-mobile-list">
        <div v-if="!loading && drivers.length === 0" class="empty-state app-card">
            <i class="pi pi-search" />
          <p>Belum ada data driver.</p>
        </div>
        <div v-for="driver in drivers" :key="driver.id" class="mobile-card app-card">
          <div class="mobile-card-header">
            <div>
              <h3>{{ driver.nama }}</h3>
              <p>{{ driver.kota || '-' }} · {{ driver.kontak_1 || '-' }}</p>
            </div>
            <Tag :severity="getStatusSeverity(driver.status)" :value="driver.status" class="status-tag" />
          </div>
          <div class="mobile-card-meta">
            <div>
              <span>Tipe</span>
              <strong>{{ driver.is_tetap ? 'Tetap' : 'Non-Tetap' }}</strong>
            </div>
            <div>
              <span>Saldo Operasional</span>
              <strong class="amount-text">{{ formatCurrency(driver.saldo) }}</strong>
            </div>
          </div>
          <div class="mobile-card-actions">
            <Button v-if="canEditBalance" label="Saldo" icon="pi pi-wallet" class="btn-pill btn-secondary" @click="openBalance(driver)" />
            <Button label="Edit" icon="pi pi-pencil" class="btn-pill btn-secondary" @click="editDriver(driver)" />
            <Button v-if="canManage" label="Hapus" icon="pi pi-trash" class="btn-pill btn-secondary danger-action" @click="confirmDelete(driver)" />
          </div>
        </div>
        <Paginator
          v-if="!loading && pagination.total > pagination.per_page"
          :rows="pagination.per_page"
          :totalRecords="pagination.total"
          :first="(pagination.current_page - 1) * pagination.per_page"
          @page="onPageChange"
          template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
          currentPageReportTemplate="{first} - {last} dari {totalRecords}"
          class="mobile-paginator"
        />
      </div>
    </div>

    <DriverFormDialog
      v-model:visible="showDialog"
      :driver="selectedDriver"
      :loading="loading"
      @save="saveDriver"
    />

    <BalanceDialog
      v-model:visible="showBalanceDialog"
      :driver="selectedDriver"
      :loading="loading"
      @saved="saveBalance"
    />
  </div>
</template>

<style scoped>
.driver-list-page {
  animation: fadeIn 0.25s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}

.status-filter {
  min-width: 180px;
}

.driver-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.driver-name {
  font-weight: 700;
  color: var(--text-primary);
}

.type-tag {
  font-size: 0.65rem;
  padding: 2px 8px;
  width: fit-content;
}

.status-tag {
  font-weight: 600;
  font-size: 0.75rem;
  padding: 4px 10px;
}

.amount-text {
  color: var(--info-cyan);
  font-family: var(--font-mono);
  font-weight: 600;
  white-space: nowrap;
}

.action-btn-danger:hover:not(:disabled) {
  color: var(--negative);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 50px 0;
  color: var(--text-tertiary);
}

.empty-state i {
  font-size: 3rem;
  margin-bottom: 15px;
  opacity: 0.5;
}

.driver-mobile-list {
  display: none;
}

.mobile-card-list {
  flex-direction: column;
  gap: var(--space-md);
}

.mobile-card {
  padding: var(--space-lg);
}

.mobile-card-header,
.mobile-card-meta,
.mobile-card-actions {
  display: flex;
  gap: var(--space-md);
}

.mobile-card-header {
  align-items: flex-start;
  justify-content: space-between;
}

.mobile-card-header h3 {
  margin: 0;
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 700;
}

.mobile-card-header p {
  margin: 4px 0 0;
  color: var(--text-secondary);
  font-size: 12px;
}

.mobile-card-meta {
  margin-top: var(--space-lg);
  flex-wrap: wrap;
}

.mobile-card-meta > div {
  flex: 1 1 130px;
  border-radius: var(--radius-default);
  background: var(--card-bg);
  padding: var(--space-md);
}

.mobile-card-meta span {
  display: block;
  color: var(--text-tertiary);
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
}

.mobile-card-meta strong {
  display: block;
  margin-top: 3px;
  color: var(--text-primary);
  font-size: 13px;
}

.mobile-card-actions {
  margin-top: var(--space-lg);
  flex-wrap: wrap;
}

.danger-action {
  color: var(--negative) !important;
}

.mobile-paginator {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
}

@media (max-width: 768px) {
  .driver-table-shell {
    display: none;
  }

  .driver-mobile-list {
    display: flex;
  }

  .status-filter {
    width: 100%;
  }
}
</style>
