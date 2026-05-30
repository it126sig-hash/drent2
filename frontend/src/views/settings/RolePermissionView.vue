<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import { getRolePermissions, updateRolePermissions } from '../../api/rolePermission'
import { useAuthStore } from '../../stores/auth'

const toast = useToast()
const authStore = useAuthStore()

const loading = ref(false)
const saving = ref(false)
const rolePermissions = ref({})
const selectedRole = ref('admin_branch')

const roles = [
  { value: 'superadmin', label: 'Super Admin' },
  { value: 'admin_branch', label: 'Admin Branch' },
  { value: 'finance', label: 'Finance' },
  { value: 'cs', label: 'Customer Service' },
  { value: 'supervisor', label: 'Supervisor' },
  { value: 'driver_tetap', label: 'Driver Tetap' },
  { value: 'teknisi', label: 'Teknisi' },
]

const permissionDefinitions = [
  {
    group: 'Dashboard',
    items: [
      { key: 'dashboard.view', label: 'Lihat Dashboard' }
    ]
  },
  {
    group: 'Booking',
    items: [
      { key: 'booking.view', label: 'Lihat Booking' },
      { key: 'booking.create', label: 'Buat Booking' },
      { key: 'booking.update', label: 'Edit Booking' },
      { key: 'booking.delete', label: 'Hapus / Cancel Booking' },
      { key: 'booking.handle', label: 'Handle Booking (Assign Unit/Driver)' },
      { key: 'booking.supervisor_request', label: 'Request Supervisor' },
      { key: 'booking.payment', label: 'Kelola Pembayaran Booking' }
    ]
  },
  {
    group: 'Cek Fisik',
    items: [
      { key: 'physical_check.view', label: 'Lihat Cek Fisik' },
      { key: 'physical_check.create', label: 'Buat Cek Fisik' },
      { key: 'physical_check.update', label: 'Edit Cek Fisik' }
    ]
  },
  {
    group: 'Keuangan',
    items: [
      { key: 'finance.receivable', label: 'Lihat Piutang' },
      { key: 'finance.receivable.create', label: 'Buat Invoice / Bayar Piutang' },
      { key: 'finance.receivable.update', label: 'Edit Piutang' },
      { key: 'finance.rent_to_rent', label: 'Lihat Rent to Rent' },
      { key: 'finance.rent_to_rent.create', label: 'Buat Tagihan R2R / Bayar' },
      { key: 'finance.rent_to_rent.update', label: 'Edit Rent to Rent' },
      { key: 'finance.operational_cost', label: 'Lihat Biaya Operasional' },
      { key: 'finance.operational_cost.create', label: 'Catat Biaya Operasional' },
      { key: 'finance.operational_cost.update', label: 'Edit Biaya Operasional' },
      { key: 'finance.account_mutation', label: 'Lihat Mutasi Rekening' },
      { key: 'finance.account_mutation.create', label: 'Buat Mutasi Rekening' },
      { key: 'finance.monthly_report', label: 'Laporan Bulanan' },
      { key: 'finance.transaction', label: 'Laporan Transaksi' },
      { key: 'finance.invoice_terms', label: 'Lihat Template Invoice' },
      { key: 'finance.invoice_terms.create', label: 'Buat Template Invoice' },
      { key: 'finance.invoice_terms.update', label: 'Edit Template Invoice' },
      { key: 'finance.invoice_terms.delete', label: 'Hapus Template Invoice' }
    ]
  },
  {
    group: 'Kendaraan',
    items: [
      { key: 'vehicle.rental_owner', label: 'Lihat Pemilik Rental' },
      { key: 'vehicle.rental_owner.create', label: 'Tambah Pemilik Rental' },
      { key: 'vehicle.rental_owner.update', label: 'Edit Pemilik Rental' },
      { key: 'vehicle.rental_owner.delete', label: 'Hapus Pemilik Rental' },
      { key: 'vehicle.unit', label: 'Lihat Unit Kendaraan' },
      { key: 'vehicle.unit.create', label: 'Tambah Unit Kendaraan' },
      { key: 'vehicle.unit.update', label: 'Edit Unit Kendaraan' },
      { key: 'vehicle.unit.delete', label: 'Hapus Unit Kendaraan' },
      { key: 'vehicle.driver', label: 'Lihat Driver' },
      { key: 'vehicle.driver.create', label: 'Tambah Driver' },
      { key: 'vehicle.driver.update', label: 'Edit Driver' },
      { key: 'vehicle.driver.delete', label: 'Hapus Driver' },
      { key: 'driver.operational', label: 'Akses Operasional Driver (Mobile)' }
    ]
  },
  {
    group: 'Pelanggan & Member',
    items: [
      { key: 'customer.view', label: 'Lihat Pelanggan' },
      { key: 'customer.create', label: 'Tambah Pelanggan' },
      { key: 'customer.update', label: 'Edit Pelanggan' },
      { key: 'customer.delete', label: 'Hapus Pelanggan' },
      { key: 'member.view', label: 'Lihat Member' },
      { key: 'member.create', label: 'Tambah Member' },
      { key: 'member.update', label: 'Edit Member' },
      { key: 'member.delete', label: 'Hapus Member' }
    ]
  },
  {
    group: 'Data Master',
    items: [
      { key: 'master.user', label: 'Lihat Manajemen User' },
      { key: 'master.user.create', label: 'Tambah User' },
      { key: 'master.user.update', label: 'Edit User' },
      { key: 'master.user.delete', label: 'Hapus User' },
      { key: 'master.payment_account', label: 'Lihat Akun Pembayaran' },
      { key: 'master.payment_account.create', label: 'Tambah Akun Pembayaran' },
      { key: 'master.payment_account.update', label: 'Edit Akun Pembayaran' },
      { key: 'master.payment_account.delete', label: 'Hapus Akun Pembayaran' },
      { key: 'master.city', label: 'Lihat Kota' },
      { key: 'master.city.create', label: 'Tambah Kota' },
      { key: 'master.city.update', label: 'Edit Kota' },
      { key: 'master.city.delete', label: 'Hapus Kota' },
      { key: 'master.cost_type', label: 'Lihat Tipe Biaya' },
      { key: 'master.cost_type.create', label: 'Tambah Tipe Biaya' },
      { key: 'master.cost_type.update', label: 'Edit Tipe Biaya' },
      { key: 'master.cost_type.delete', label: 'Hapus Tipe Biaya' },
      { key: 'master.pricing_package', label: 'Lihat Paket Harga' },
      { key: 'master.pricing_package.create', label: 'Tambah Paket Harga' },
      { key: 'master.pricing_package.update', label: 'Edit Paket Harga' },
      { key: 'master.pricing_package.delete', label: 'Hapus Paket Harga' },
      { key: 'master.finance_category', label: 'Lihat Kategori Keuangan' },
      { key: 'master.finance_category.create', label: 'Tambah Kategori Keuangan' },
      { key: 'master.finance_category.update', label: 'Edit Kategori Keuangan' },
      { key: 'master.finance_category.delete', label: 'Hapus Kategori Keuangan' },
      { key: 'master.branch', label: 'Lihat Cabang' },
      { key: 'master.branch.create', label: 'Tambah Cabang' },
      { key: 'master.branch.update', label: 'Edit Cabang' },
      { key: 'master.branch.delete', label: 'Hapus Cabang' },
      { key: 'master.tenant', label: 'Profil Tenant' },
      { key: 'master.role_management', label: 'Manajemen Role Permission' }
    ]
  }
]

// Current selected permissions
const currentPermissions = ref([])

const loadData = async () => {
  loading.value = true
  try {
    const { data } = await getRolePermissions()
    rolePermissions.value = data.data
    syncCurrentPermissions()
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat data role permission', life: 3000 })
  } finally {
    loading.value = false
  }
}

const syncCurrentPermissions = () => {
  currentPermissions.value = [...(rolePermissions.value[selectedRole.value] || [])]
}

const onRoleSelect = (roleValue) => {
  selectedRole.value = roleValue
  syncCurrentPermissions()
}

const isRoleEditable = computed(() => {
  // admin_branch cannot edit superadmin or other admin_branch roles
  if (authStore.user?.role === 'admin_branch') {
    return !['superadmin', 'admin_branch'].includes(selectedRole.value)
  }
  return true
})

const savePermissions = async () => {
  saving.value = true
  try {
    await updateRolePermissions(selectedRole.value, currentPermissions.value)
    // Update local state
    rolePermissions.value[selectedRole.value] = [...currentPermissions.value]
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Permission berhasil disimpan', life: 3000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Error', detail: error.response?.data?.message || 'Gagal menyimpan permission', life: 3000 })
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="page-container role-permission-page">
    <div class="page-header">
      <div class="header-left">
        <div>
          <h1>Manajemen Role</h1>
          <p class="text-secondary text-xs">Atur hak akses default untuk setiap role sistem.</p>
        </div>
      </div>
    </div>

    <div class="split-layout" v-if="!loading">
      <!-- Sidebar Roles -->
      <div class="sidebar-panel">
        <div class="app-card role-list-card">
          <div 
            v-for="role in roles" 
            :key="role.value"
            class="role-item"
            :class="{ 'active': selectedRole === role.value }"
            @click="onRoleSelect(role.value)"
          >
            <div class="role-icon">
              <i class="pi pi-users" v-if="role.value !== 'superadmin'"></i>
              <i class="pi pi-shield" v-else></i>
            </div>
            <div class="role-details">
              <div class="role-name">{{ role.label }}</div>
              <div class="role-count font-mono-numeric">{{ (rolePermissions[role.value] || []).length }} permissions</div>
            </div>
            <i class="pi pi-chevron-right text-xs text-secondary"></i>
          </div>
        </div>
      </div>

      <!-- Main Permission Editor -->
      <div class="editor-panel">
        <div class="app-card">
          <div class="app-section-header editor-header">
            <div>
              <h2>{{ roles.find(r => r.value === selectedRole)?.label }}</h2>
              <p class="text-secondary text-xs">Pilih hak akses untuk role ini.</p>
            </div>
            <button 
              class="btn-pill btn-primary"
              type="button"
              :disabled="saving || !isRoleEditable"
              @click="savePermissions"
            >
              <i :class="saving ? 'pi pi-spin pi-spinner' : 'pi pi-save'"></i>
              <span>Simpan Perubahan</span>
            </button>
          </div>
          
          <div v-if="!isRoleEditable" class="warning-banner">
            <i class="pi pi-exclamation-triangle"></i>
            <span>Anda tidak memiliki akses untuk mengubah permission role ini.</span>
          </div>

          <div class="permission-groups">
            <div v-for="group in permissionDefinitions" :key="group.group" class="permission-group-section">
              <h3 class="group-title">
                {{ group.group }}
              </h3>
              <div class="permission-grid">
                <div v-for="item in group.items" :key="item.key" class="permission-item">
                  <div class="checkbox-wrapper">
                    <Checkbox 
                      v-model="currentPermissions" 
                      :inputId="item.key" 
                      name="permissions"
                      :value="item.key" 
                      :disabled="!isRoleEditable || selectedRole === 'superadmin'"
                    />
                    <label :for="item.key" class="checkbox-label" :class="{ 'disabled-label': !isRoleEditable || selectedRole === 'superadmin' }">
                      {{ item.label }}
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div v-else class="app-card loading-state">
      <i class="pi pi-spin pi-spinner text-info"></i>
      <span>Memuat data permission...</span>
    </div>
  </div>
</template>

<style scoped>
.role-permission-page {
  background: var(--page-bg);
}

.split-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: var(--space-2xl);
  margin-top: var(--space-md);
}

.sidebar-panel {
  display: flex;
  flex-direction: column;
}

.editor-panel {
  display: flex;
  flex-direction: column;
}

.role-list-card {
  overflow: hidden;
  padding: 0;
}

.role-item {
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  border-bottom: 1px solid var(--surface-border);
  transition: background-color 0.2s, border-left 0.2s;
  border-left: 3px solid transparent;
}

.role-item:last-child {
  border-bottom: none;
}

.role-item:hover {
  background-color: var(--card-bg-hover);
}

.role-item.active {
  background-color: var(--card-bg-hover);
  border-left-color: var(--text-primary);
}

.role-icon {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-full);
  background-color: var(--card-bg);
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
}

.role-item.active .role-icon {
  background-color: var(--text-primary);
  color: var(--text-white);
}

.role-details {
  flex: 1;
}

.role-name {
  font-family: var(--font-headline);
  font-weight: 600;
  color: var(--text-primary);
  font-size: 13px;
}

.role-count {
  font-size: 11px;
  color: var(--text-tertiary);
  margin-top: 2px;
}

.editor-header {
  min-height: 60px;
  padding: 16px var(--space-2xl);
}

.editor-header h2 {
  margin: 0;
  font-family: var(--font-headline);
  font-size: 16px;
  font-weight: 700;
}

.warning-banner {
  background-color: #FDF4D9;
  border-bottom: 1px solid var(--surface-border);
  color: #8C660A;
  padding: 12px var(--space-2xl);
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 600;
}

.permission-groups {
  padding: var(--space-2xl);
}

.permission-group-section {
  margin-bottom: var(--space-3xl);
}

.permission-group-section:last-child {
  margin-bottom: 0;
}

.group-title {
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 600;
  color: var(--text-primary);
  padding-bottom: 8px;
  border-bottom: 1px solid var(--surface-border);
  margin-bottom: 16px;
}

.permission-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-lg);
}

.permission-item {
  display: flex;
  align-items: center;
}

.checkbox-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
}

.checkbox-label {
  font-family: var(--font-body);
  font-size: 12px;
  font-weight: 500;
  color: var(--text-secondary);
  cursor: pointer;
}

.checkbox-label:hover {
  color: var(--text-primary);
}

.disabled-label {
  opacity: 0.55;
  cursor: not-allowed;
}

.disabled-label:hover {
  color: var(--text-secondary);
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-md);
  padding: 48px;
  color: var(--text-secondary);
}

.loading-state i {
  font-size: 24px;
}

.text-info {
  color: var(--info-cyan);
}

@media (max-width: 992px) {
  .split-layout {
    grid-template-columns: 1fr;
  }
  .permission-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .permission-grid {
    grid-template-columns: 1fr;
  }
}
</style>
