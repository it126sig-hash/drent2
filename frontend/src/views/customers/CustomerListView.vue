<script setup>
import { ref, onMounted, computed } from 'vue'
import { useCustomer } from '../../composables/useCustomer'
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
import Message from 'primevue/message'
import ConfirmDialog from 'primevue/confirmdialog'
import CustomerFormDialog from '../../components/customers/CustomerFormDialog.vue'

const { 
  customers, 
  loading, 
  pagination, 
  fetchAll, 
  store, 
  update, 
  remove 
} = useCustomer()

const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const searchQuery = ref('')
const statusFilter = ref(null)
const showDialog = ref(false)
const selectedCustomer = ref(null)

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
const hasRiskCustomer = computed(() => 
  customers.value.some(c => ['Redflag', 'Blacklist'].includes(c.status))
)

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

const onPageChange = (event) => {
  pagination.value.current_page = event.page + 1
  fetchData()
}

const openNew = () => {
  selectedCustomer.value = null
  showDialog.value = true
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
      } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal menghapus data', life: 3000 })
      }
    }
  })
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
</script>

<template>
  <div class="view-container">
    <ConfirmDialog />
    
    <div class="header-section">
      <div class="header-content">
        <h1>Manajemen Pelanggan</h1>
        <p>Kelola data pelanggan dan status keanggotaan</p>
      </div>
      <Button 
        v-if="canManage"
        label="Tambah Pelanggan" 
        icon="pi pi-plus" 
        class="p-button-tosca" 
        @click="openNew" 
      />
    </div>

    <Message v-if="hasRiskCustomer" severity="warn" class="mb-2">
      Terdapat pelanggan dengan status Redflag (berisiko) atau Blacklist (diblokir) dalam daftar ini.
    </Message>

    <div class="content-card">
      <div class="table-toolbar">
        <div class="filter-wrapper">
          <span class="p-input-icon-left search-wrapper">
            <i class="pi pi-search" />
            <InputText 
              v-model="searchQuery" 
              placeholder="Cari nama, kontak, email, atau kota..." 
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
        :value="customers" 
        :loading="loading" 
        responsiveLayout="scroll"
        class="p-datatable-sm"
        stripedRows
      >
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-users"></i>
            <p>Belum ada data pelanggan.</p>
          </div>
        </template>

        <Column field="nama" header="Pelanggan" style="min-width: 200px">
          <template #body="{ data }">
            <div class="customer-info">
              <span class="customer-name">{{ data.nama }}</span>
              <Tag 
                v-if="data.has_apply_member"
                value="Member" 
                severity="success"
                class="member-tag"
              />
            </div>
          </template>
        </Column>

        <Column field="kontak_1" header="Kontak" style="min-width: 150px">
          <template #body="{ data }">
            <div class="contact-info">
              <span>{{ data.kontak_1 }}</span>
              <small v-if="data.kontak_2" class="text-gray-500">{{ data.kontak_2 }}</small>
            </div>
          </template>
        </Column>

        <Column field="email" header="Email" style="min-width: 180px">
          <template #body="{ data }">
            {{ data.email || '-' }}
          </template>
        </Column>

        <Column field="kota" header="Kota" style="min-width: 120px">
          <template #body="{ data }">
            {{ data.kota || '-' }}
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

        <Column header="Aksi" style="min-width: 150px; text-align: center">
          <template #body="{ data }">
            <div class="action-buttons">
              <Button 
                icon="pi pi-pencil" 
                class="p-button-rounded p-button-text p-button-secondary" 
                @click="editCustomer(data)" 
                v-tooltip.top="'Edit'"
              />
              <Button 
                v-if="['superadmin', 'admin_branch'].includes(authStore.user?.role)"
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

    <CustomerFormDialog 
      v-model:visible="showDialog" 
      :customer="selectedCustomer" 
      :loading="loading"
      @save="saveCustomer"
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

.customer-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.customer-name {
  font-weight: 700;
  color: #1e293b;
}

.member-tag {
  font-size: 0.65rem;
  padding: 2px 8px;
  width: fit-content;
}

.contact-info {
  display: flex;
  flex-direction: column;
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
