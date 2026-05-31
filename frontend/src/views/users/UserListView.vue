<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
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
import UserPermissionOverrideDialog from './components/UserPermissionOverrideDialog.vue'

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
const showPermissionDialog = ref(false)
const selectedUser = ref(null)

const isMobile = ref(window.innerWidth < 768)
const onResize = () => { isMobile.value = window.innerWidth < 768 }
onMounted(() => window.addEventListener('resize', onResize))
onUnmounted(() => window.removeEventListener('resize', onResize))

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

const openPermissions = (user) => {
  selectedUser.value = { ...user }
  showPermissionDialog.value = true
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

const getRoleBadgeClass = (role) => {
  return {
    superadmin: 'error',
    admin_branch: 'warning',
    finance: 'info',
    cs: 'success',
    teknisi: 'neutral',
    driver_tetap: 'neutral',
    supervisor: 'info'
  }[role] || 'neutral'
}
</script>

<template>
  <div class="page-container user-management-page table-page-active">
    <ConfirmDialog />
    
    <div class="page-header">
      <div class="header-left">
        <div>
          <h1>Manajemen User</h1>
          <p class="text-secondary text-xs">Kelola akses pengguna, role, dan branch assignment.</p>
        </div>
      </div>
      <div class="header-actions">
        <button 
          v-if="canManage"
          class="btn-pill btn-primary"
          type="button"
          @click="openNew"
        >
          <i class="pi pi-plus"></i>
          <span>Tambah User</span>
        </button>
      </div>
    </div>

    <div class="filter-bar surface-card">
      <div class="filter-groups">
        <div class="filter-group filter-group-wide">
          <label>Pencarian</label>
          <span class="filter-search">
            <i class="pi pi-search" />
            <InputText 
              v-model="searchQuery" 
              placeholder="Cari nama atau email..." 
              @input="onSearch"
              class="w-full"
            />
          </span>
        </div>
        <div class="filter-group">
          <label>Role</label>
          <Dropdown 
            v-model="roleFilter" 
            :options="roles" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Semua Role" 
            showClear
            @change="onSearch"
            class="w-full md:w-44"
          />
        </div>
        <div class="filter-group">
          <label>Status</label>
          <Dropdown 
            v-model="statusFilter" 
            :options="statusOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Semua Status" 
            @change="onSearch"
            class="w-full md:w-44"
          />
        </div>
      </div>
    </div>

    <div v-if="!isMobile" class="table-shell list-tab-fill">
      <DataTable 
        :value="users" 
        :loading="loading" 
        scrollable
        scrollHeight="flex"
        responsiveLayout="scroll"
        class="drent-datatable"
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
              <div class="user-meta">
                <span class="drent-badge" :class="getRoleBadgeClass(data.role)">{{ data.role_label }}</span>
                <span class="user-email">{{ data.email }}</span>
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
            <span class="drent-badge" :class="data.is_active ? 'success' : 'neutral'">
              {{ data.is_active ? 'Aktif' : 'Non-Aktif' }}
            </span>
          </template>
        </Column>

        <Column header="Aksi" style="min-width: 180px; text-align: center">
          <template #body="{ data }">
            <div class="action-pill-group">
              <button 
                v-if="canManage"
                class="action-btn"
                type="button"
                @click="openPermissions(data)" 
                v-tooltip.top="'Hak Akses'"
              >
                <i class="pi pi-shield" />
              </button>
              <button 
                v-if="canManage"
                class="action-btn"
                type="button"
                @click="openReset(data)" 
                v-tooltip.top="'Reset Password'"
              >
                <i class="pi pi-key" />
              </button>
              <button 
                v-if="canManage"
                class="action-btn"
                type="button"
                @click="editUser(data)" 
                v-tooltip.top="'Edit'"
              >
                <i class="pi pi-pencil" />
              </button>
              <button 
                v-if="canManage"
                class="action-btn action-btn-danger"
                type="button"
                @click="confirmDelete(data)" 
                v-tooltip.top="'Hapus'"
                :disabled="data.id === authStore.user?.id"
              >
                <i class="pi pi-trash" />
              </button>
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

    <div v-else class="mobile-card-list">
      <article v-for="user in users" :key="user.id" class="mobile-card">
        <div class="card-header">
          <div class="user-info">
            <span class="user-name">{{ user.name }}</span>
            <span class="user-email">{{ user.email }}</span>
          </div>
          <span class="drent-badge" :class="user.is_active ? 'success' : 'neutral'">{{ user.is_active ? 'Aktif' : 'Non-Aktif' }}</span>
        </div>
        <div class="card-body">
          <div><span class="field-hint">Role</span> <span class="drent-badge" :class="getRoleBadgeClass(user.role)">{{ user.role_label }}</span></div>
          <div><span class="field-hint">Branch</span> {{ user.branch_name || '-' }}</div>
        </div>
        <div v-if="canManage" class="card-footer">
          <div class="action-pill-group">
            <button class="action-btn" type="button" @click="openPermissions(user)" v-tooltip.top="'Hak Akses'"><i class="pi pi-shield" /></button>
            <button class="action-btn" type="button" @click="openReset(user)" v-tooltip.top="'Reset Password'"><i class="pi pi-key" /></button>
            <button class="action-btn" type="button" @click="editUser(user)" v-tooltip.top="'Edit'"><i class="pi pi-pencil" /></button>
            <button class="action-btn action-btn-danger" type="button" @click="confirmDelete(user)" :disabled="user.id === authStore.user?.id" v-tooltip.top="'Hapus'"><i class="pi pi-trash" /></button>
          </div>
        </div>
      </article>

      <div v-if="!loading && !users.length" class="empty-state">
        <i class="pi pi-users"></i>
        <p>Belum ada data user.</p>
      </div>

      <div class="paginator-wrapper">
        <Paginator :rows="pagination.per_page" :totalRecords="pagination.total" :first="(pagination.current_page - 1) * pagination.per_page"
          @page="onPageChange" template="PrevPageLink CurrentPageReport NextPageLink" currentPageReportTemplate="{first}-{last} dari {totalRecords}" />
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

    <UserPermissionOverrideDialog
      v-model:visible="showPermissionDialog"
      :user="selectedUser"
    />
  </div>
</template>

<style scoped>
.user-management-page {
  background: var(--page-bg);
}

.user-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.user-name {
  font-family: var(--font-headline);
  font-size: 13px;
  font-weight: 600;
  color: var(--text-primary);
}

.user-meta {
  display: flex;
  align-items: center;
  gap: 8px;
}

.user-email {
  font-size: 11px;
  color: var(--text-secondary);
}

.action-btn-danger {
  color: var(--negative) !important;
}

.field-hint { color: var(--text-tertiary); font-size: 11px; margin-right: 4px; }
.mobile-card-list .card-footer { justify-content: flex-end; }

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 44px 0;
  color: var(--text-tertiary);
}

.empty-state i {
  font-size: 2.4rem;
  margin-bottom: var(--space-md);
  opacity: 0.7;
}

.paginator-wrapper {
  padding: var(--space-sm);
  border-top: 1px solid var(--surface-border);
}

/* Premium Drent Badge styling matching design.md rules */
.drent-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 6px;
  border-radius: 6px;
  font-family: var(--font-body);
  font-size: 10px;
  font-weight: 600;
  line-height: 1.3;
  text-transform: capitalize;
  white-space: nowrap;
}

.drent-badge.success {
  background-color: #E6F6EC;
  color: #147239;
}

.drent-badge.error {
  background-color: #FCEAE9;
  color: #B02A24;
}

.drent-badge.warning {
  background-color: #FDF4D9;
  color: #8C660A;
}

.drent-badge.info {
  background-color: #E1F4F6;
  color: #085A66;
}

.drent-badge.neutral {
  background-color: #E4E8F3;
  color: #4A5060;
}
</style>
