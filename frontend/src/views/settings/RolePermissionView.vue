<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import { getRolePermissions, updateRolePermissions } from '../../api/rolePermission'
import { useAuthStore } from '../../stores/auth'
import { permissionDefinitions } from '../../constants/permissionDefinitions'

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

              <div v-if="group.items" class="permission-grid">
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

              <div v-for="subgroup in group.subgroups" :key="subgroup.name" class="permission-subgroup">
                <h4 class="subgroup-title">{{ subgroup.name }}</h4>
                <div class="permission-grid">
                  <div v-for="item in subgroup.items" :key="item.key" class="permission-item">
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
  font-size: var(--font-size-14px);
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
  font-size: var(--font-size-13px);
}

.role-count {
  font-size: var(--font-size-11px);
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
  font-size: var(--font-size-16px);
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
  font-size: var(--font-size-12px);
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
  font-size: var(--font-size-14px);
  font-weight: 600;
  color: var(--text-primary);
  padding-bottom: 8px;
  border-bottom: 1px solid var(--surface-border);
  margin-bottom: 16px;
}

.permission-subgroup {
  margin-bottom: 20px;
}

.permission-subgroup:last-child {
  margin-bottom: 0;
}

.subgroup-title {
  font-family: var(--font-body);
  font-size: var(--font-size-12px);
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  margin: 0 0 10px;
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
  font-size: var(--font-size-12px);
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
  font-size: var(--font-size-24px);
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
