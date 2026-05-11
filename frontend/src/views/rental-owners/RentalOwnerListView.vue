<script setup>
import { ref, onMounted } from 'vue'
import { useRentalOwner } from '../../composables/useRentalOwner'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Paginator from 'primevue/paginator'
import Badge from 'primevue/badge'
import Tag from 'primevue/tag'
import ConfirmDialog from 'primevue/confirmdialog'
import RentalOwnerFormDialog from '../../components/rental-owners/RentalOwnerFormDialog.vue'

const { 
  rentalOwners, 
  loading, 
  pagination, 
  fetchAll, 
  store, 
  update, 
  remove 
} = useRentalOwner()

const toast = useToast()
const confirm = useConfirm()

const searchQuery = ref('')
const showDialog = ref(false)
const selectedOwner = ref(null)

onMounted(() => {
  fetchData()
})

const fetchData = async () => {
  try {
    await fetchAll({ search: searchQuery.value })
  } catch (err) {
    toast.add({ 
      severity: 'error', 
      summary: 'Error', 
      detail: 'Gagal memuat data pemilik rental', 
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
  selectedOwner.value = null
  showDialog.value = true
}

const editOwner = (owner) => {
  selectedOwner.value = { ...owner }
  showDialog.value = true
}

const saveOwner = async (data) => {
  try {
    if (data.id) {
      await update(data.id, data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Data berhasil diperbarui', life: 3000 })
    } else {
      await store(data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Data berhasil ditambahkan', life: 3000 })
    }
    showDialog.value = false
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.message || 'Terjadi kesalahan saat menyimpan data', life: 3000 })
  }
}

const confirmDelete = (owner) => {
  confirm.require({
    message: `Apakah Anda yakin ingin menghapus pemilik "${owner.nama}"? Tindakan ini tidak dapat dibatalkan.`,
    header: 'Konfirmasi Hapus',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await remove(owner.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Data berhasil dihapus', life: 3000 })
      } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal menghapus data', life: 3000 })
      }
    }
  })
}
</script>

<template>
  <div class="view-container">
    <ConfirmDialog />
    
    <div class="header-section">
      <div class="header-content">
        <h1>Pemilik Rental</h1>
        <p>Kelola data pemilik armada kendaraan</p>
      </div>
      <Button label="Tambah Pemilik" icon="pi pi-plus" class="p-button-tosca" @click="openNew" />
    </div>

    <div class="content-card">
      <div class="table-toolbar">
        <span class="p-input-icon-left search-wrapper">
          <i class="pi pi-search" />
          <InputText 
            v-model="searchQuery" 
            placeholder="Cari nama pemilik..." 
            @input="onSearch"
            class="w-full"
          />
        </span>
      </div>

      <DataTable 
        :value="rentalOwners" 
        :loading="loading" 
        responsiveLayout="scroll"
        class="p-datatable-sm"
        stripedRows
      >
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-users"></i>
            <p>Belum ada data pemilik rental.</p>
          </div>
        </template>

        <Column field="nama" header="Nama Pemilik" sortable style="min-width: 200px">
          <template #body="{ data }">
            <span class="font-bold text-slate-800">{{ data.nama }}</span>
          </template>
        </Column>

        <Column field="kontak_1" header="Kontak" style="min-width: 150px"></Column>

        <Column field="kota" header="Kota" style="min-width: 120px">
          <template #body="{ data }">
            {{ data.kota || '-' }}
          </template>
        </Column>

        <Column header="Informasi Bank" style="min-width: 220px">
          <template #body="{ data }">
            <div v-if="data.bank" class="bank-info">
              <span class="bank-name">{{ data.bank }}</span>
              <span class="bank-acc">{{ data.no_rek }}</span>
              <small class="bank-owner">a.n {{ data.atas_nama }}</small>
            </div>
            <span v-else class="text-slate-400">-</span>
          </template>
        </Column>

        <Column field="is_owner" header="Status" style="min-width: 150px">
          <template #body="{ data }">
            <Tag 
              :severity="data.is_owner ? 'success' : 'info'" 
              :value="data.is_owner ? 'Pemilik Sendiri' : 'Pemilik Lain'"
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
                @click="editOwner(data)" 
                v-tooltip.top="'Edit'"
              />
              <Button 
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

    <RentalOwnerFormDialog 
      v-model:visible="showDialog" 
      :rentalOwner="selectedOwner" 
      :loading="loading"
      @save="saveOwner"
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

.search-wrapper {
  max-width: 350px;
}

.bank-info {
  display: flex;
  flex-direction: column;
  line-height: 1.4;
}

.bank-name {
  font-weight: 700;
  color: #06b6d4;
  font-size: 0.85rem;
}

.bank-acc {
  font-family: monospace;
  font-size: 0.9rem;
  color: #334155;
}

.bank-owner {
  color: #64748b;
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

:deep(.p-paginator) {
  background: transparent;
  border: none;
}
</style>
