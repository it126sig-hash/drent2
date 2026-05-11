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
  <div class="view-container">
    <ConfirmDialog />
    
    <div class="header-section">
      <div class="header-content">
        <h1>Manajemen Driver</h1>
        <p>Kelola data driver tetap dan non-tetap serta saldo operasional</p>
      </div>
      <Button 
        v-if="canManage"
        label="Tambah Driver" 
        icon="pi pi-plus" 
        class="p-button-tosca" 
        @click="openNew" 
      />
    </div>

    <div class="content-card">
      <div class="table-toolbar">
        <div class="filter-wrapper">
          <span class="p-input-icon-left search-wrapper">
            <i class="pi pi-search" />
            <InputText 
              v-model="searchQuery" 
              placeholder="Cari nama atau kontak..." 
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

      <DataTable 
        :value="drivers" 
        :loading="loading" 
        responsiveLayout="scroll"
        class="p-datatable-sm"
        stripedRows
      >
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-id-card"></i>
            <p>Belum ada data driver.</p>
          </div>
        </template>

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
            <span class="font-bold text-cyan-700">{{ formatCurrency(data.saldo) }}</span>
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

        <Column header="Aksi" style="min-width: 180px; text-align: center">
          <template #body="{ data }">
            <div class="action-buttons">
              <Button 
                v-if="canEditBalance"
                icon="pi pi-wallet" 
                class="p-button-rounded p-button-text p-button-info" 
                @click="openBalance(data)" 
                v-tooltip.top="'Update Saldo'"
              />
              <Button 
                icon="pi pi-pencil" 
                class="p-button-rounded p-button-text p-button-secondary" 
                @click="editDriver(data)" 
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

.filter-wrapper {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.search-wrapper {
  max-width: 350px;
  flex: 1;
  min-width: 250px;
}

.status-filter {
  width: 180px;
}

.driver-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.driver-name {
  font-weight: 700;
  color: #1e293b;
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
