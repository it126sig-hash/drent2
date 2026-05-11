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
  <div class="view-container">
    <ConfirmDialog />
    
    <div class="header-section">
      <div class="header-content">
        <h1>Unit Kendaraan</h1>
        <p>Kelola armada dan informasi unit kendaraan</p>
      </div>
      <Button 
        v-if="canManage"
        label="Tambah Unit" 
        icon="pi pi-plus" 
        class="p-button-tosca" 
        @click="openNew" 
      />
    </div>

    <div class="content-card">
      <div class="table-toolbar">
        <div class="flex gap-3">
          <span class="p-input-icon-left search-wrapper">
            <i class="pi pi-search" />
            <InputText 
              v-model="searchQuery" 
              placeholder="Cari tipe, merk, atau plat..." 
              @input="onSearch"
              class="w-full"
            />
          </span>
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

      <DataTable 
        :value="units" 
        :loading="loading" 
        responsiveLayout="scroll"
        class="p-datatable-sm"
        stripedRows
      >
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-car"></i>
            <p>Belum ada data unit kendaraan.</p>
          </div>
        </template>

        <Column field="no_polisi" header="No Polisi" style="min-width: 120px">
          <template #body="{ data }">
            <span class="plat-badge">{{ data.no_polisi }}</span>
          </template>
        </Column>

        <Column header="Kendaraan" style="min-width: 200px">
          <template #body="{ data }">
            <div class="unit-info">
              <span class="unit-name">{{ data.merk }} {{ data.tipe }}</span>
              <small class="unit-year">Tahun {{ data.tahun }}</small>
            </div>
          </template>
        </Column>

        <Column header="Pemilik" style="min-width: 150px">
          <template #body="{ data }">
            <span v-if="data.rental_owner" class="text-slate-700">{{ data.rental_owner.nama }}</span>
            <span v-else class="text-slate-400">Internal</span>
          </template>
        </Column>

        <Column header="Harga Sewa / Hari" style="min-width: 150px">
          <template #body="{ data }">
            <span class="font-semibold text-cyan-700">{{ formatCurrency(data.harga_1_hari) }}</span>
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

        <Column header="Aksi" style="min-width: 120px; text-align: center">
          <template #body="{ data }">
            <div class="action-buttons">
              <Button 
                icon="pi pi-pencil" 
                class="p-button-rounded p-button-text p-button-secondary" 
                @click="editUnit(data)" 
                v-tooltip.top="'Edit'"
              />
              <Button 
                v-if="canManage"
                icon="pi pi-trash" 
                class="p-button-rounded p-button-text p-button-danger" 
                @click="confirmDelete(data)" 
                v-tooltip.top="'Hapus'"
              />
            </div>
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
.view-container {
  display: flex;
  flex-direction: column;
  gap: 25px;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-content h1 {
  font-size: 1.8rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.header-content p {
  color: #64748b;
  margin-top: 5px;
}

.content-card {
  background-color: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.table-toolbar {
  padding: 20px;
  border-bottom: 1px solid #f1f5f9;
}

.flex { display: flex; }
.gap-3 { gap: 12px; }

.search-wrapper {
  max-width: 350px;
}

.status-filter {
  width: 200px;
}

.plat-badge {
  font-family: 'Courier New', Courier, monospace;
  font-weight: 700;
  background: #334155;
  color: #f8fafc;
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
  color: #1e293b;
}

.unit-year {
  color: #94a3b8;
  font-size: 0.75rem;
}

.status-tag {
  font-weight: 600;
  font-size: 0.75rem;
  padding: 4px 10px;
}

.action-buttons {
  display: flex;
  justify-content: center;
  gap: 5px;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 50px 0;
  color: #94a3b8;
}

.empty-state i {
  font-size: 3rem;
  margin-bottom: 15px;
  opacity: 0.5;
}

.paginator-wrapper {
  padding: 10px;
  border-top: 1px solid #f1f5f9;
}

.p-button-tosca {
  background-color: #06b6d4 !important;
  border-color: #06b6d4 !important;
}

.p-button-tosca:hover {
  background-color: #0891b2 !important;
  border-color: #0891b2 !important;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
  background-color: #f8fafc;
  color: #475569;
  font-weight: 700;
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.5px;
  padding: 15px;
}

:deep(.p-datatable .p-datatable-tbody > tr > td) {
  padding: 15px;
}
</style>
