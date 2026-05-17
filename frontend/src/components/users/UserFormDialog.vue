<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import ToggleSwitch from 'primevue/toggleswitch'
import { useAuthStore } from '../../stores/auth'
import api from '../../api/axios'
import { fetchDrivers } from '../../api/driver'

const props = defineProps({
  visible: Boolean,
  user: {
    type: Object,
    default: null
  },
  roles: {
    type: Array,
    default: () => []
  },
  loading: Boolean
})

const emit = defineEmits(['update:visible', 'save'])
const authStore = useAuthStore()

const formData = ref({
  name: '',
  email: '',
  password: '',
  role: null,
  branch_id: null,
  driver_id: null,
  is_active: true,
  tenant_id: null
})

const branches = ref([])
const drivers = ref([])
const loadingDrivers = ref(false)
const isDriverRole = computed(() => formData.value.role === 'driver_tetap')
const driverOptions = computed(() => (
  drivers.value.filter((driver) => !driver.user_id || driver.user_id === props.user?.id)
))
const isSaveDisabled = computed(() => (
  !formData.value.name ||
  !formData.value.email ||
  !formData.value.role ||
  !formData.value.branch_id ||
  (!props.user && !formData.value.password) ||
  (isDriverRole.value && !formData.value.driver_id)
))

onMounted(async () => {
  try {
    const response = await api.get('/v1/branches')
    branches.value = response.data.data
  } catch (err) {
    console.error('Gagal mengambil data branch:', err)
  }
})

const fetchDriverOptions = async () => {
  if (!formData.value.branch_id) {
    drivers.value = []
    return
  }

  loadingDrivers.value = true
  try {
    const response = await fetchDrivers({
      branch_id: formData.value.branch_id,
      status: 'Aktif',
      is_tetap: true,
      per_page: 100
    })
    drivers.value = response.data.data
  } catch (err) {
    console.error('Gagal mengambil data driver:', err)
  } finally {
    loadingDrivers.value = false
  }
}

watch(() => props.user, (newVal) => {
  if (newVal) {
    formData.value = { 
      ...newVal,
      password: '' // Reset password field in edit mode
    }
  } else {
    resetForm()
  }
}, { immediate: true })

watch(() => props.visible, (visible) => {
  if (visible) fetchDriverOptions()
})

watch(() => formData.value.branch_id, () => {
  formData.value.driver_id = null
  if (props.visible) fetchDriverOptions()
})

watch(() => formData.value.role, (role) => {
  if (role !== 'driver_tetap') {
    formData.value.driver_id = null
  } else if (props.visible) {
    fetchDriverOptions()
  }
})

function resetForm() {
  formData.value = {
    name: '',
    email: '',
    password: '',
    role: null,
    branch_id: authStore.user?.branch_id,
    driver_id: null,
    is_active: true,
    tenant_id: authStore.user?.tenant_id
  }
}

const handleSave = () => {
  const required = ['name', 'email', 'role', 'branch_id']
  if (!props.user) required.push('password')
  if (isDriverRole.value) required.push('driver_id')
  
  for (const field of required) {
    if (!formData.value[field]) return
  }

  emit('save', { ...formData.value })
}

const handleClose = () => {
  emit('update:visible', false)
}
</script>

<template>
  <Dialog 
    :visible="visible" 
    @update:visible="handleClose"
    :header="user ? 'Edit User' : 'Tambah User'" 
    :modal="true" 
    class="custom-dialog"
    :style="{ width: '500px' }"
    :breakpoints="{ '960px': '75vw', '641px': '95vw' }"
  >
    <div class="form-container p-fluid">
      <div class="form-section">
        <h3 class="section-title"><i class="pi pi-user mr-2"></i>Informasi Akun</h3>
        
        <div class="field">
          <label for="name" class="label-required">Nama Lengkap</label>
          <InputText id="name" v-model="formData.name" placeholder="Masukkan nama lengkap" :class="{ 'p-invalid': !formData.name }" />
        </div>

        <div class="field">
          <label for="email" class="label-required">Alamat Email</label>
          <InputText id="email" v-model="formData.email" type="email" placeholder="email@example.com" :class="{ 'p-invalid': !formData.email }" />
        </div>

        <div v-if="!user" class="field">
          <label for="password" class="label-required">Password</label>
          <InputText id="password" v-model="formData.password" type="password" placeholder="Minimal 8 karakter" :class="{ 'p-invalid': !formData.password }" />
        </div>

        <div class="form-row">
          <div class="field">
            <label for="role" class="label-required">Role</label>
            <Dropdown id="role" v-model="formData.role" :options="roles" optionLabel="label" optionValue="value" placeholder="Pilih Role" :class="{ 'p-invalid': !formData.role }" />
          </div>
          <div class="field">
            <label for="branch" class="label-required">Branch</label>
            <Dropdown id="branch" v-model="formData.branch_id" :options="branches" optionLabel="name" optionValue="id" placeholder="Pilih Branch" :class="{ 'p-invalid': !formData.branch_id }" />
          </div>
        </div>

        <div v-if="isDriverRole" class="field">
          <label for="driver" class="label-required">Driver</label>
          <Dropdown
            id="driver"
            v-model="formData.driver_id"
            :options="driverOptions"
            optionLabel="nama"
            optionValue="id"
            placeholder="Pilih driver tetap"
            filter
            :loading="loadingDrivers"
            :class="{ 'p-invalid': !formData.driver_id }"
          >
            <template #option="{ option }">
              <div class="driver-option">
                <span>{{ option.nama }}</span>
                <small>{{ option.kontak_1 || '-' }}</small>
              </div>
            </template>
          </Dropdown>
        </div>

        <div class="field mt-2">
          <div class="flex align-items-center gap-3">
            <ToggleSwitch v-model="formData.is_active" />
            <span class="font-semibold" :class="formData.is_active ? 'text-green-600' : 'text-red-600'">
              Status: {{ formData.is_active ? 'Aktif' : 'Non-Aktif' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <Button label="Batal" icon="pi pi-times" class="p-button-text p-button-secondary" @click="handleClose" />
        <Button 
          :label="user ? 'Simpan Perubahan' : 'Tambah User'" 
          icon="pi pi-save" 
          class="p-button-tosca" 
          @click="handleSave" 
          :loading="loading" 
          :disabled="isSaveDisabled" 
        />
      </div>
    </template>
  </Dialog>
</template>

<style scoped>
.form-container {
  display: flex;
  flex-direction: column;
  padding: 15px 5px;
  gap: 15px;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.field label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #334155;
  display: flex;
  align-items: center;
}

.driver-option {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.driver-option small {
  color: #64748b;
}

.label-required::after {
  content: " *";
  color: #f43f5e;
  margin-left: 4px;
}

.section-title {
  font-size: 0.75rem;
  font-weight: 800;
  color: #0891b2;
  margin: 0 0 4px 0;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  display: flex;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 8px;
}

.p-button-tosca {
  background-color: #06b6d4 !important;
  border-color: #06b6d4 !important;
  padding: 10px 24px !important;
  font-weight: 600 !important;
  border-radius: 8px !important;
}

.p-button-tosca:hover {
  background-color: #0891b2 !important;
  border-color: #0891b2 !important;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 15px 0 5px 0;
  border-top: 1px solid #f1f5f9;
}

:deep(.p-dialog-content) {
  padding: 0 1.5rem 1.5rem 1.5rem !important;
}

:deep(.p-inputtext), :deep(.p-dropdown), :deep(.p-textarea), :deep(.p-toggleswitch) {
  border-radius: 8px;
}

.flex { display: flex; }
.align-items-center { align-items: center; }
.gap-3 { gap: 1rem; }
.font-semibold { font-weight: 600; }
.text-green-600 { color: #16a34a; }
.text-red-600 { color: #dc2626; }
</style>
