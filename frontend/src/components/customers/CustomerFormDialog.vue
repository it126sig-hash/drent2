<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Message from 'primevue/message'
import { useAuthStore } from '../../stores/auth'
import { useCity } from '../../composables/useCity'

const props = defineProps({
  visible: Boolean,
  customer: {
    type: Object,
    default: null
  },
  loading: Boolean
})

const emit = defineEmits(['update:visible', 'save'])
const authStore = useAuthStore()
const { cities, fetchAll: fetchCities, loading: citiesLoading } = useCity()

const formData = ref({
  nama: '',
  kontak_1: '',
  kontak_2: '',
  email: '',
  alamat: '',
  kota: '',
  status: 'Normal',
  has_apply_member: false,
  catatan: '',
  tenant_id: null
})

const statusOptions = [
  { label: 'Normal', value: 'Normal' },
  { label: 'Member', value: 'Member' },
  { label: 'Rent to Rent', value: 'Rent to Rent' },
  { label: 'Corporate', value: 'Corporate' },
  { label: 'Redflag', value: 'Redflag' },
  { label: 'Blacklist', value: 'Blacklist' }
]

const showWarning = computed(() => ['Redflag', 'Blacklist'].includes(formData.value.status))
const cityOptions = computed(() =>
  cities.value
    .filter(city => city.is_active)
    .map(city => ({
      label: city.provinsi ? `${city.nama} - ${city.provinsi}` : city.nama,
      value: city.nama
    }))
)

onMounted(() => fetchCities({ per_page: 200, is_active: true }))

watch(() => props.customer, (newVal) => {
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
    email: '',
    alamat: '',
    kota: '',
    status: 'Normal',
    has_apply_member: false,
    catatan: '',
    tenant_id: authStore.user?.tenant_id
  }
}

const handleSave = () => {
  const required = ['nama', 'kontak_1', 'status']
  for (const field of required) {
    if (!formData.value[field]) return
  }

  formData.value.tenant_id = formData.value.tenant_id || authStore.user?.tenant_id

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
    :header="customer ? 'Edit Pelanggan' : 'Tambah Pelanggan'" 
    :modal="true" 
    class="custom-dialog"
    :style="{ width: '600px' }"
    :breakpoints="{ '960px': '75vw', '641px': '95vw' }"
  >
    <div class="form-container p-fluid">
      <Message v-if="formData.status === 'Blacklist'" severity="error" :closable="false" class="mb-4">
        Pelanggan dengan status Blacklist tidak dapat melakukan booking.
      </Message>
      <Message v-if="formData.status === 'Redflag'" severity="warn" :closable="false" class="mb-4">
        Pelanggan berstatus Redflag berisiko. Proses booking tetap dapat dilakukan dengan kehati-hatian.
      </Message>

      <div class="form-section">
        <h3 class="section-title"><i class="pi pi-user mr-2"></i>Informasi Pelanggan</h3>
        
        <div class="field">
          <label for="nama" class="label-required">Nama Lengkap</label>
          <InputText id="nama" v-model="formData.nama" placeholder="Masukkan nama pelanggan" :class="{ 'p-invalid': !formData.nama }" />
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

        <div class="field">
          <label for="email">Email</label>
          <InputText id="email" v-model="formData.email" type="email" placeholder="nama@email.com" />
        </div>

        <div class="form-row">
          <div class="field">
            <label for="status" class="label-required">Status</label>
            <Dropdown id="status" v-model="formData.status" :options="statusOptions" optionLabel="label" optionValue="value" />
          </div>
          <div class="field flex align-items-center mt-4">
            <Checkbox id="has_apply_member" v-model="formData.has_apply_member" :binary="true" />
            <label for="has_apply_member" class="ml-2 mb-0 cursor-pointer">Sudah Apply Member</label>
          </div>
        </div>

        <div class="field">
          <label for="kota">Kota Domisili</label>
          <Dropdown
            id="kota"
            v-model="formData.kota"
            :options="cityOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Pilih kota"
            filter
            :loading="citiesLoading"
          />
        </div>

        <div class="field">
          <label for="alamat">Alamat Lengkap</label>
          <Textarea id="alamat" v-model="formData.alamat" rows="2" placeholder="Masukkan alamat lengkap" />
        </div>

        <div class="field">
          <label for="catatan">Catatan Internal</label>
          <Textarea id="catatan" v-model="formData.catatan" rows="2" placeholder="Informasi tambahan terkait pelanggan" />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <Button label="Batal" icon="pi pi-times" class="p-button-text p-button-secondary" @click="handleClose" />
        <Button 
          :label="customer ? 'Simpan Perubahan' : 'Tambah Pelanggan'" 
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

:deep(.p-inputtext), :deep(.p-dropdown), :deep(.p-textarea) {
  border-radius: 8px;
}
</style>
