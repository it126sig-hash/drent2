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
  <div class="page-container table-page-active">
    <div class="page-header flex justify-between items-center mb-4">
      <div class="header-left">
        <h1 class="page-title m-0 text-2xl font-bold text-[var(--text-primary)]">Manajemen Member</h1>
        <p class="page-subtitle m-0 mt-1 text-[var(--text-secondary)]">Kelola data pelanggan yang mengajukan sewa lepas kunci</p>
      </div>
      <div class="header-actions">
        <Button 
          v-if="canCreate"
          label="Tambah Member Baru" 
          icon="pi pi-plus" 
          class="btn-pill btn-primary" 
          @click="createMember" 
        />
      </div>
    </div>

    <div class="filter-bar bg-[var(--surface-default)] p-3 rounded-lg border border-[var(--surface-border)] mb-3">
      <div class="filter-groups flex flex-wrap gap-3">
        <div class="filter-group filter-search flex-1" style="min-width: 300px;">
          <span class="p-input-icon-left w-full">
            <i class="pi pi-search" />
            <InputText 
              v-model="searchQuery" 
              placeholder="Cari nama atau ID..." 
              @input="onSearch"
              class="w-full"
            />
          </span>
        </div>
        <div class="filter-group">
          <Dropdown 
            v-model="statusFilter" 
            :options="statusOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Filter Status" 
            @change="onSearch"
            style="min-width: 180px;"
          />
        </div>
      </div>
    </div>

    <div class="table-shell app-card rounded-lg border border-[var(--surface-border)] overflow-hidden bg-[var(--surface-default)] flex-col">
      <DataTable 
        :value="members" 
        :loading="loading" 
        scrollable 
        scrollHeight="flex"
        class="drent-datatable"
      >
        <template #empty>
          <div class="flex flex-col items-center justify-center p-5 text-[var(--text-secondary)]">
            <i class="pi pi-id-card text-4xl mb-3 opacity-50"></i>
            <p>Belum ada data member.</p>
          </div>
        </template>

        <Column field="customer.nama" header="Nama Pelanggan" style="min-width: 200px">
          <template #body="{ data }">
            <div class="flex flex-col gap-1">
              <span class="font-semibold text-[var(--text-primary)]">{{ data.customer?.nama }}</span>
              <small class="text-[var(--text-secondary)]">{{ data.customer?.kontak_1 }}</small>
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
              class="status-badge"
            />
          </template>
        </Column>

        <Column field="tanggal_exp" header="Kedaluwarsa" style="min-width: 120px">
          <template #body="{ data }">
            <span class="text-sm">{{ data.tanggal_exp || '-' }}</span>
          </template>
        </Column>

        <Column header="Aksi" style="min-width: 80px; text-align: center" frozen alignFrozen="right">
          <template #body="{ data }">
            <div class="action-pill-group flex justify-center">
              <Button 
                icon="pi pi-eye" 
                class="action-btn p-button-rounded p-button-text p-button-secondary" 
                @click="viewDetail(data.id)" 
                v-tooltip.top="'Lihat Detail'"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <div class="border-t border-[var(--surface-border)] p-2">
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
/* Mewarisi token global DRENT dari src/style.css */
</style>
