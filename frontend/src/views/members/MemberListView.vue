<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useMember } from '../../composables/useMember'
import { useToast } from 'primevue/usetoast'
import { useAuthStore } from '../../stores/auth'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Paginator from 'primevue/paginator'
import Tag from 'primevue/tag'

const { 
  members, 
  loading, 
  pagination, 
  fetchAll 
} = useMember()

const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()

const searchQuery = ref('')
const statusFilter = ref(null)

const statusOptions = [
  { label: 'Semua Status', value: null },
  { label: 'Pending', value: 'Pending' },
  { label: 'Aktif', value: 'Aktif' },
  { label: 'Tidak Aktif', value: 'Tidak Aktif' },
  { label: 'Ditolak', value: 'Ditolak' }
]

const canCreate = computed(() => ['superadmin', 'admin_branch', 'cs', 'surveyor'].includes(authStore.user?.role))

onMounted(() => {
  fetchData()
})

const fetchData = async () => {
  try {
    await fetchAll({ 
      search: searchQuery.value,
      status_member: statusFilter.value
    })
  } catch (err) {
    toast.add({ 
      severity: 'error', 
      summary: 'Error', 
      detail: 'Gagal memuat data member', 
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

const viewDetail = (id) => {
  router.push(`/mdm/members/${id}`)
}

const createMember = () => {
  router.push('/mdm/members/create')
}

const getStatusSeverity = (status) => {
  if (status === 'Aktif') return 'success'
  if (status === 'Pending') return 'warning'
  if (status === 'Ditolak') return 'danger'
  return 'info'
}
</script>

<template>
  <div class="view-container">
    <div class="header-section">
      <div class="header-content">
        <h1>Manajemen Member</h1>
        <p>Kelola data pelanggan yang mengajukan sewa lepas kunci</p>
      </div>
      <Button 
        v-if="canCreate"
        label="Tambah Member Baru" 
        icon="pi pi-plus" 
        class="p-button-tosca" 
        @click="createMember" 
      />
    </div>

    <div class="content-card">
      <div class="table-toolbar">
        <div class="filter-wrapper">
          <span class="p-input-icon-left search-wrapper">
            <i class="pi pi-search" />
            <InputText 
              v-model="searchQuery" 
              placeholder="Cari nama pelanggan atau ID member..." 
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
        :value="members" 
        :loading="loading" 
        responsiveLayout="scroll"
        class="p-datatable-sm"
        stripedRows
      >
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-id-card"></i>
            <p>Belum ada data member.</p>
          </div>
        </template>

        <Column field="customer.nama" header="Nama Pelanggan" style="min-width: 200px">
          <template #body="{ data }">
            <div class="customer-info">
              <span class="customer-name">{{ data.customer?.nama }}</span>
              <small class="text-gray-500">{{ data.customer?.kontak_1 }}</small>
            </div>
          </template>
        </Column>

        <Column field="id_member" header="ID Member" style="min-width: 150px">
          <template #body="{ data }">
            <span class="font-mono">{{ data.id_member || '-' }}</span>
          </template>
        </Column>

        <Column field="status_member" header="Status" style="min-width: 120px">
          <template #body="{ data }">
            <Tag 
              :severity="getStatusSeverity(data.status_member)" 
              :value="data.status_member"
              class="status-tag"
            />
          </template>
        </Column>

        <Column field="tanggal_exp" header="Kedaluwarsa" style="min-width: 120px">
          <template #body="{ data }">
            {{ data.tanggal_exp || '-' }}
          </template>
        </Column>

        <Column header="Aksi" style="min-width: 100px; text-align: center">
          <template #body="{ data }">
            <div class="action-buttons">
              <Button 
                icon="pi pi-eye" 
                class="p-button-rounded p-button-text p-button-secondary" 
                @click="viewDetail(data.id)" 
                v-tooltip.top="'Lihat Detail'"
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
  gap: 2px;
}

.customer-name {
  font-weight: 700;
  color: #1e293b;
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
