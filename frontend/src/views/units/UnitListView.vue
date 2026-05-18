<script setup>
import { ref, onMounted, computed } from 'vue'
import { useUnit } from '../../composables/useUnit'
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
import UnitFormDialog from '../../components/units/UnitFormDialog.vue'

const {
  units,
  loading,
  pagination,
  fetchAll,
  store,
  update,
  remove
} = useUnit()

const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const searchQuery = ref('')
const statusFilter = ref(null)
const showDialog = ref(false)
const selectedUnit = ref(null)

const statusOptions = [
  { label: 'Semua Status', value: null },
  { label: 'Aktif', value: 'Aktif' },
  { label: 'Tidak Aktif', value: 'Tidak Aktif' },
  { label: 'Dalam Servis', value: 'Dalam Servis' }
]

const canManage = computed(() => ['superadmin', 'admin_branch'].includes(authStore.user?.role))

onMounted(() => {
  fetchData()
})

const fetchData = async () => {
  try {
    await fetchAll({
      search: searchQuery.value,
      status: statusFilter.value,
      branch_id: authStore.user?.branch_id
    })
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Gagal memuat data unit',
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
  selectedUnit.value = null
  showDialog.value = true
}

const editUnit = (unit) => {
  selectedUnit.value = { ...unit }
  showDialog.value = true
}

const saveUnit = async (data) => {
  try {
    if (data.id) {
      await update(data.id, data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Unit berhasil diperbarui', life: 3000 })
    } else {
      await store(data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Unit berhasil ditambahkan', life: 3000 })
    }
    showDialog.value = false
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.message || 'Terjadi kesalahan saat menyimpan data', life: 3000 })
  }
}

const confirmDelete = (unit) => {
  confirm.require({
    message: `Apakah Anda yakin ingin menghapus unit "${unit.merk} ${unit.tipe}" (${unit.no_polisi})?`,
    header: 'Konfirmasi Hapus',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await remove(unit.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Unit berhasil dihapus', life: 3000 })
      } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal menghapus data', life: 3000 })
      }
    }
  })
}

const getStatusSeverity = (status) => {
  switch (status) {
    case 'Aktif': return 'success'
    case 'Tidak Aktif': return 'danger'
    case 'Dalam Servis': return 'warning'
    default: return 'info'
  }
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value)
}
</script>

<template>
  <div class="page-container table-page-active unit-list-page">
    <ConfirmDialog />

    <div class="page-header">
      <div class="header-left">
        <div class="header-copy">
          <h1 class="text-h1">Unit Kendaraan</h1>
          <p>Kelola armada dan informasi unit kendaraan</p>
        </div>
      </div>
      <div class="header-actions">
        <Button
          v-if="canManage"
          label="Tambah Unit"
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
            <label>Cari Unit</label>
            <span class="filter-search">
              <i class="pi pi-search" />
              <InputText
                v-model="searchQuery"
                placeholder="Cari tipe, merk, atau plat..."
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
        </div>
      </div>

      <div class="table-shell unit-table-shell">
        <DataTable
          :value="units"
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
          class="drent-datatable unit-desktop-table"
          stripedRows
          @page="onPageChange"
        >
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-car"></i>
              <p>Belum ada data unit kendaraan.</p>
            </div>
          </template>

          <Column header="Aksi" style="width: 6.5rem; text-align: center">
            <template #body="{ data }">
              <div class="action-pill-group">
                <button type="button" class="action-btn" @click="editUnit(data)" v-tooltip.top="'Edit'">
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

          <Column field="no_polisi" header="No Polisi" style="min-width: 120px">
            <template #body="{ data }">
              <span class="plat-badge">{{ data.no_polisi }}</span>
            </template>
          </Column>

          <Column header="Kendaraan" style="min-width: 200px">
            <template #body="{ data }">
              <div class="unit-info">
                <span class="unit-name">{{ data.merk }} {{ data.tipe }}</span>
                <small class="unit-year">Tahun {{ data.tahun || '-' }}</small>
              </div>
            </template>
          </Column>

          <Column header="Pemilik" style="min-width: 150px">
            <template #body="{ data }">
              <span v-if="data.rental_owner" class="owner-text">{{ data.rental_owner.nama }}</span>
              <span v-else class="muted-text">Internal</span>
            </template>
          </Column>

          <Column header="Harga Sewa / Hari" style="min-width: 150px">
            <template #body="{ data }">
              <span class="amount-text">{{ formatCurrency(data.harga_1_hari) }}</span>
            </template>
          </Column>

          <Column field="status" header="Status" style="min-width: 130px">
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

      <div class="mobile-card-list unit-mobile-list">
        <div v-if="!loading && units.length === 0" class="empty-state app-card">
          <i class="pi pi-car"></i>
          <p>Belum ada data unit kendaraan.</p>
        </div>
        <div v-for="unit in units" :key="unit.id" class="mobile-card app-card">
          <div class="mobile-card-header">
            <div>
              <span class="plat-badge">{{ unit.no_polisi }}</span>
              <h3>{{ unit.merk }} {{ unit.tipe }}</h3>
              <p>Tahun {{ unit.tahun || '-' }}</p>
            </div>
            <Tag :severity="getStatusSeverity(unit.status)" :value="unit.status" class="status-tag" />
          </div>
          <div class="mobile-card-meta">
            <div>
              <span>Pemilik</span>
              <strong>{{ unit.rental_owner?.nama || 'Internal' }}</strong>
            </div>
            <div>
              <span>Harga / Hari</span>
              <strong class="amount-text">{{ formatCurrency(unit.harga_1_hari) }}</strong>
            </div>
          </div>
          <div class="mobile-card-actions">
            <Button label="Edit" icon="pi pi-pencil" class="btn-pill btn-secondary" @click="editUnit(unit)" />
            <Button v-if="canManage" label="Hapus" icon="pi pi-trash" class="btn-pill btn-secondary danger-action" @click="confirmDelete(unit)" />
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

    <UnitFormDialog
      v-model:visible="showDialog"
      :unit="selectedUnit"
      :loading="loading"
      @save="saveUnit"
      @refresh="fetchData"
    />
  </div>
</template>

<style scoped>
.unit-list-page {
  animation: fadeIn 0.25s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}

.status-filter {
  min-width: 200px;
}

.plat-badge {
  font-family: 'Courier New', Courier, monospace;
  font-weight: 700;
  background: var(--text-primary);
  color: var(--text-white);
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 0.85rem;
  letter-spacing: 1px;
}

.unit-info {
  display: flex;
  flex-direction: column;
}

.unit-name {
  font-weight: 700;
  color: var(--text-primary);
}

.unit-year {
  color: var(--text-tertiary);
  font-size: 0.75rem;
}

.owner-text {
  color: var(--text-primary);
}

.muted-text {
  color: var(--text-tertiary);
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

.unit-mobile-list {
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
  margin: 10px 0 0;
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
  .unit-table-shell {
    display: none;
  }

  .unit-mobile-list {
    display: flex;
  }

  .status-filter {
    width: 100%;
  }
}
</style>
