<script setup>
import { ref, onMounted, computed } from 'vue'
import { useUser } from '../../composables/useUser'
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
import UserFormDialog from '../../components/users/UserFormDialog.vue'
import ResetPasswordDialog from '../../components/users/ResetPasswordDialog.vue'

const { 
  users, 
  roles,
  loading, 
  pagination, 
  fetchAll, 
  fetchRoles,
  store, 
  update, 
  remove,
  resetPassword
} = useUser()

const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const searchQuery = ref('')
const roleFilter = ref(null)
const statusFilter = ref(null)
const showDialog = ref(false)
const showResetDialog = ref(false)
const selectedUser = ref(null)

const statusOptions = [
  { label: 'Semua Status', value: null },
  { label: 'Aktif', value: true },
  { label: 'Non-Aktif', value: false }
]

const canManage = computed(() => ['superadmin', 'admin_branch'].includes(authStore.user?.role))

onMounted(async () => {
  fetchData()
  fetchRoles()
})

const fetchData = async () => {
  try {
    await fetchAll({ 
      search: searchQuery.value,
      role: roleFilter.value,
      is_active: statusFilter.value,
      branch_id: authStore.user?.branch_id
    })
  } catch (err) {
    toast.add({ 
      severity: 'error', 
      summary: 'Error', 
      detail: 'Gagal memuat data user', 
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
  selectedUser.value = null
  showDialog.value = true
}

const editUser = (user) => {
  selectedUser.value = { ...user }
  showDialog.value = true
}

const openReset = (user) => {
  selectedUser.value = { ...user }
  showResetDialog.value = true
}

const saveUser = async (data) => {
  try {
    if (selectedUser.value) {
      await update(selectedUser.value.id, data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Data user berhasil diperbarui', life: 3000 })
    } else {
      await store(data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'User berhasil ditambahkan', life: 3000 })
    }
    showDialog.value = false
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.message || 'Terjadi kesalahan saat menyimpan data', life: 3000 })
  }
}

const saveReset = async (data) => {
  try {
    await resetPassword(selectedUser.value.id, data)
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Password berhasil direset', life: 3000 })
    showResetDialog.value = false
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal mereset password', life: 3000 })
  }
}

const confirmDelete = (user) => {
  if (user.id === authStore.user?.id) {
    toast.add({ severity: 'warn', summary: 'Peringatan', detail: 'Anda tidak dapat menghapus akun Anda sendiri', life: 3000 })
    return
  }

  confirm.require({
    message: `Apakah Anda yakin ingin menghapus user "${user.name}"?`,
    header: 'Konfirmasi Hapus',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await remove(user.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'User berhasil dihapus', life: 3000 })
      } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal menghapus data', life: 3000 })
      }
    }
  })
}

const getRoleSeverity = (role) => {
  return {
    superadmin: 'danger',
    admin_branch: 'warning',
    finance: 'info',
    cs: 'success',
    teknisi: 'contrast',
    driver_tetap: 'secondary'
  }[role] || 'secondary'
}

const getStatusSeverity = (isActive) => {
  return isActive ? 'success' : 'danger'
}
</script>

<template>
  <div class="view-container">
    <ConfirmDialog />
    
    <div class="header-section">
      <div class="header-content">
        <h1>Manajemen User</h1>
        <p>Kelola akses pengguna, role, dan branch assignment</p>
      </div>
      <Button 
        v-if="canManage"
        label="Tambah User" 
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
              placeholder="Cari nama atau email..." 
              @input="onSearch"
              class="w-full"
            />
          </span>
          <Dropdown 
            v-model="roleFilter" 
            :options="roles" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Semua Role" 
            showClear
            @change="onSearch"
            class="status-filter"
          />
          <Dropdown 
            v-model="statusFilter" 
            :options="statusOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Semua Status" 
            @change="onSearch"
            class="status-filter"
          />
        </div>
      </div>

      <DataTable 
        :value="users" 
        :loading="loading" 
        responsiveLayout="scroll"
        class="p-datatable-sm"
        stripedRows
      >
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-users"></i>
            <p>Belum ada data user.</p>
          </div>
        </template>

        <Column field="name" header="User" style="min-width: 250px">
          <template #body="{ data }">
            <div class="user-info">
              <span class="user-name">{{ data.name }}</span>
              <div class="flex gap-2 align-items-center">
                <Tag 
                  :value="data.role_label" 
                  :severity="getRoleSeverity(data.role)"
                  class="type-tag"
                />
                <span class="text-xs text-slate-500">{{ data.email }}</span>
              </div>
            </div>
          </template>
        </Column>

        <Column field="branch_name" header="Branch" style="min-width: 150px">
          <template #body="{ data }">
            {{ data.branch_name || '-' }}
          </template>
        </Column>

        <Column field="is_active" header="Status" style="min-width: 120px">
          <template #body="{ data }">
            <Tag 
              :severity="getStatusSeverity(data.is_active)" 
              :value="data.is_active ? 'Aktif' : 'Non-Aktif'"
              class="status-tag"
            />
          </template>
        </Column>

        <Column header="Aksi" style="min-width: 180px; text-align: center">
          <template #body="{ data }">
            <div class="action-buttons">
              <Button 
                v-if="canManage"
                icon="pi pi-key" 
                class="p-button-rounded p-button-text p-button-warning" 
                @click="openReset(data)" 
                v-tooltip.top="'Reset Password'"
              />
              <Button 
                v-if="canManage"
                icon="pi pi-pencil" 
                class="p-button-rounded p-button-text p-button-secondary" 
                @click="editUser(data)" 
                v-tooltip.top="'Edit'"
              />
              <Button 
                v-if="canManage"
                icon="pi pi-trash" 
                class="p-button-rounded p-button-text p-button-danger" 
                @click="confirmDelete(data)" 
                v-tooltip.top="'Hapus'"
                :disabled="data.id === authStore.user?.id"
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

    <UserFormDialog 
      v-model:visible="showDialog" 
      :user="selectedUser" 
      :roles="roles"
      :loading="loading"
      @save="saveUser"
    />

    <ResetPasswordDialog
      v-model:visible="showResetDialog"
      :user="selectedUser"
      :loading="loading"
      @saved="saveReset"
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

.user-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.user-name {
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

.flex { display: flex; }
.gap-2 { gap: 0.5rem; }
.align-items-center { align-items: center; }
.text-xs { font-size: 0.75rem; }
.text-slate-500 { color: #64748b; }

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
