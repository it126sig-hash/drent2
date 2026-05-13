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
    acceptClass: 'app-dialog-button app-dialog-button-danger',
    rejectClass: 'app-dialog-button app-dialog-button-secondary',
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
  <div class="rental-owner-page">
    <ConfirmDialog />
    
    <div class="detail-page-header">
      <div class="detail-heading">
        <h1 class="detail-title">Pemilik Rental</h1>
        <p class="detail-subtitle">
          <i class="pi pi-users" />
          Kelola data pemilik armada kendaraan
        </p>
      </div>
      <div class="detail-action-bar">
        <Button label="Tambah Pemilik" icon="pi pi-plus" class="detail-primary-action" @click="openNew" />
      </div>
    </div>

    <div class="app-card owner-table-card">
      <div class="app-section-header table-section-header">
        <div class="section-title-group">
          <span class="section-icon">
            <i class="pi pi-id-card" />
          </span>
          <div>
            <h2>Daftar Pemilik</h2>
            <p>{{ pagination.total || 0 }} data terdaftar</p>
          </div>
        </div>
      </div>

      <div class="table-toolbar">
        <span class="p-input-icon-left search-wrapper">
          <i class="pi pi-search" />
          <InputText 
            v-model="searchQuery" 
            placeholder="Cari nama pemilik..." 
            @input="onSearch"
            class="owner-search-input"
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
            <span class="owner-name">{{ data.nama }}</span>
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
            <span v-else class="muted-value">-</span>
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
.rental-owner-page {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
  width: 100%;
  padding: var(--space-2xl);
  background: var(--page-bg);
}

.detail-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-xl);
}

.detail-heading {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: var(--space-sm);
}

.detail-title {
  margin: 0;
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 20px;
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: 0;
}

.detail-subtitle {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  margin: 0;
  color: var(--text-secondary);
  font-size: 12px;
  line-height: 1.4;
}

.detail-subtitle i {
  color: var(--text-tertiary);
  font-size: 11px;
}

.detail-action-bar {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: var(--space-sm);
}

.detail-primary-action {
  min-height: 34px;
  border: none !important;
  border-radius: var(--radius-full) !important;
  background: var(--text-primary) !important;
  color: var(--text-white) !important;
  padding: 8px 16px !important;
  font-size: 12px !important;
  font-weight: 600 !important;
  box-shadow: none !important;
}

.detail-primary-action:hover {
  opacity: 0.92;
}

.app-card {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.owner-table-card {
  overflow: hidden;
}

.app-section-header {
  min-height: 54px;
  border-bottom: 1px solid var(--surface-border);
  background: var(--surface-default);
}

.table-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-lg);
  padding: var(--space-lg) var(--space-xl);
}

.section-title-group {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: var(--space-md);
}

.section-icon {
  display: inline-flex;
  width: 34px;
  height: 34px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-default);
  background: var(--card-bg);
  color: var(--text-secondary);
}

.app-section-header h2 {
  margin: 0;
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 600;
  line-height: 1.3;
}

.app-section-header p {
  margin: 2px 0 0;
  color: var(--text-secondary);
  font-size: 12px;
  line-height: 1.4;
}

.table-toolbar {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-lg) var(--space-xl);
  border-bottom: 1px solid var(--surface-border);
  background: var(--surface-default);
}

.search-wrapper {
  width: min(100%, 360px);
}

.owner-search-input {
  width: 100%;
}

.owner-name {
  color: var(--text-primary);
  font-weight: 700;
}

.bank-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  line-height: 1.35;
}

.bank-name {
  font-weight: 700;
  color: var(--info-cyan);
  font-size: 12px;
}

.bank-acc {
  color: var(--text-primary);
  font-family: var(--font-mono);
  font-size: 13px;
}

.bank-owner {
  color: var(--text-secondary);
  font-size: 11px;
}

.muted-value {
  color: var(--text-tertiary);
}

.status-tag {
  font-weight: 600;
  font-size: 11px;
  padding: 4px 8px;
}

.action-buttons {
  display: flex;
  justify-content: center;
  gap: var(--space-xs);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px var(--space-lg);
  color: var(--text-tertiary);
  text-align: center;
}

.empty-state i {
  margin-bottom: var(--space-md);
  color: var(--neutral-6);
  font-size: 34px;
  opacity: 0.5;
}

.empty-state p {
  margin: 0;
  color: var(--text-secondary);
  font-size: 13px;
}

.paginator-wrapper {
  padding: var(--space-sm) var(--space-md);
  border-top: 1px solid var(--surface-border);
  background: var(--surface-default);
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
  border-bottom: 1px solid var(--surface-border);
  background-color: var(--card-bg);
  color: var(--text-secondary);
  font-family: var(--font-headline);
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0;
  padding: 0.75rem 0.875rem;
}

:deep(.p-datatable .p-datatable-tbody > tr > td) {
  border-bottom: 1px solid var(--surface-border);
  padding: 0.75rem 0.875rem;
  color: var(--text-primary);
}

:deep(.p-datatable .p-datatable-tbody > tr:last-child > td) {
  border-bottom: none;
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
  background: var(--card-bg-hover) !important;
}

:deep(.p-button.p-button-text) {
  color: var(--text-secondary);
}

:deep(.p-button.p-button-text:hover) {
  background: var(--card-bg-hover);
  color: var(--text-primary);
}

:deep(.p-tag.p-tag-success) {
  background: #E6F6EC;
  color: #147239;
}

:deep(.p-tag.p-tag-info) {
  background: #E1F4F6;
  color: #085A66;
}

:deep(.p-paginator) {
  background: transparent;
  border: none;
  color: var(--text-secondary);
}

@media (max-width: 768px) {
  .rental-owner-page {
    padding: var(--space-lg);
    gap: var(--space-lg);
  }

  .detail-page-header {
    flex-direction: column;
    gap: var(--space-lg);
  }

  .detail-title {
    font-size: 18px;
    overflow-wrap: anywhere;
  }

  .detail-action-bar,
  .detail-action-bar :deep(.p-button) {
    width: 100%;
  }

  .table-section-header,
  .table-toolbar {
    padding: var(--space-lg);
  }

  .search-wrapper {
    width: 100%;
  }
}
</style>
