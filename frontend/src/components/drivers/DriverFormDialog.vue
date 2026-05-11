<script setup>
import { ref, watch } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Textarea from 'primevue/textarea'
import SelectButton from 'primevue/selectbutton'
import { useAuthStore } from '../../stores/auth'

const props = defineProps({
  visible: Boolean,
  driver: {
    type: Object,
    default: null
  },
  loading: Boolean
})

const emit = defineEmits(['update:visible', 'save'])
const authStore = useAuthStore()

const formData = ref({
  nama: '',
  kontak_1: '',
  kontak_2: '',
  alamat: '',
  kota: '',
  no_sim: '',
  status: 'Aktif',
  is_tetap: false,
  catatan: '',
  user_id: null,
  tenant_id: null,
  branch_id: null
})

const statusOptions = [
  { label: 'Aktif', value: 'Aktif' },
  { label: 'Tidak Aktif', value: 'Tidak Aktif' }
]

const typeOptions = [
  { label: 'Non-Tetap', value: false },
  { label: 'Tetap', value: true }
]

watch(() => props.driver, (newVal) => {
  if (newVal) {
    formData.value = { ...newVal }
  } else {
    resetForm()
  }
}, { immediate: true })

function resetForm() {
  formData.value = {
    nama: '',
    kontak_1: '',
    kontak_2: '',
    alamat: '',
    kota: '',
    no_sim: '',
    status: 'Aktif',
    is_tetap: false,
    catatan: '',
    user_id: null,
    tenant_id: authStore.user?.tenant_id,
    branch_id: authStore.user?.branch_id
  }
}

const handleSave = () => {
  const required = ['nama', 'kontak_1', 'status']
  for (const field of required) {
    if (!formData.value[field]) return
  }

  formData.value.tenant_id = formData.value.tenant_id || authStore.user?.tenant_id
  formData.value.branch_id = formData.value.branch_id || authStore.user?.branch_id

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
    :header="driver ? 'Edit Driver' : 'Tambah Driver'" 
    :modal="true" 
    class="custom-dialog"
    :style="{ width: '650px' }"
    :breakpoints="{ '960px': '75vw', '641px': '95vw' }"
  >
    <div class="form-container p-fluid">
      <div class="form-section">
        <h3 class="section-title"><i class="pi pi-user mr-2"></i>Informasi Driver</h3>
        
        <div class="field">
          <label class="label-required">Tipe Driver</label>
          <SelectButton v-model="formData.is_tetap" :options="typeOptions" optionLabel="label" optionValue="value" aria-labelledby="basic" />
          <small v-if="formData.is_tetap" class="text-cyan-600 mt-1">
            <i class="pi pi-info-circle mr-1"></i>
            Driver tetap dapat dihubungkan ke akun user di modul User Management.
          </small>
        </div>

        <div class="field">
          <label for="nama" class="label-required">Nama Lengkap</label>
          <InputText id="nama" v-model="formData.nama" placeholder="Masukkan nama lengkap" :class="{ 'p-invalid': !formData.nama }" />
        </div>

        <div class="form-row">
          <div class="field">
            <label for="kontak_1" class="label-required">Kontak 1 (Utama)</label>
            <InputText id="kontak_1" v-model="formData.kontak_1" placeholder="0812..." :class="{ 'p-invalid': !formData.kontak_1 }" />
          </div>
          <div class="field">
            <label for="kontak_2">Kontak 2 (Cadangan)</label>
            <InputText id="kontak_2" v-model="formData.kontak_2" placeholder="0812..." />
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="no_sim">Nomor SIM</label>
            <InputText id="no_sim" v-model="formData.no_sim" placeholder="1234..." />
          </div>
          <div class="field">
            <label for="status" class="label-required">Status</label>
            <Dropdown id="status" v-model="formData.status" :options="statusOptions" optionLabel="label" optionValue="value" />
          </div>
        </div>

        <div class="form-row">
          <div class="field" style="grid-column: span 2;">
            <label for="alamat">Alamat</label>
            <Textarea id="alamat" v-model="formData.alamat" rows="2" placeholder="Masukkan alamat lengkap" />
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="kota">Kota</label>
            <InputText id="kota" v-model="formData.kota" placeholder="Contoh: Jakarta" />
          </div>
        </div>

        <div class="field">
          <label for="catatan">Catatan</label>
          <Textarea id="catatan" v-model="formData.catatan" rows="2" placeholder="Informasi tambahan" />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <Button label="Batal" icon="pi pi-times" class="p-button-text p-button-secondary" @click="handleClose" />
        <Button 
          :label="driver ? 'Simpan Perubahan' : 'Tambah Driver'" 
          icon="pi pi-save" 
          class="p-button-tosca" 
          @click="handleSave" 
          :loading="loading" 
          :disabled="!formData.nama || !formData.kontak_1" 
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

.text-cyan-600 { color: #0891b2; }

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

:deep(.p-inputtext), :deep(.p-dropdown), :deep(.p-textarea), :deep(.p-selectbutton) {
  border-radius: 8px;
}
</style>
