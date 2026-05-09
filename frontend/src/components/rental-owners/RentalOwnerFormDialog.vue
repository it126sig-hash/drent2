<script setup>
import { ref, watch } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import InputSwitch from 'primevue/inputswitch'

const props = defineProps({
  visible: Boolean,
  rentalOwner: {
    type: Object,
    default: null
  },
  loading: Boolean
})

const emit = defineEmits(['update:visible', 'save'])

const formData = ref({
  nama: '',
  kontak_1: '',
  kontak_2: '',
  alamat: '',
  kota: '',
  bank: '',
  no_rek: '',
  atas_nama: '',
  is_owner: true
})

watch(() => props.rentalOwner, (newVal) => {
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
    bank: '',
    no_rek: '',
    atas_nama: '',
    is_owner: true
  }
}

const handleSave = () => {
  if (!formData.value.nama || !formData.value.kontak_1) {
    return
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
    :header="rentalOwner ? 'Edit Pemilik Rental' : 'Tambah Pemilik Rental'" 
    :modal="true" 
    class="custom-dialog"
    :style="{ width: '550px' }"
    :breakpoints="{ '960px': '75vw', '641px': '90vw' }"
  >
    <div class="form-container p-fluid">
      <!-- Section: Identitas -->
      <div class="form-section">
        <div class="field mb-4">
          <label for="nama" class="label-required">Nama Pemilik / Perusahaan</label>
          <InputText id="nama" v-model="formData.nama" placeholder="Contoh: Budi Santoso atau CV. Rental Maju" autofocus :class="{ 'p-invalid': !formData.nama }" />
          <small class="p-error" v-if="!formData.nama">Nama wajib diisi.</small>
        </div>

        <div class="form-row mb-4">
          <div class="field">
            <label for="kontak_1" class="label-required">Kontak Utama</label>
            <InputText id="kontak_1" v-model="formData.kontak_1" placeholder="0812..." :class="{ 'p-invalid': !formData.kontak_1 }" />
          </div>
          <div class="field">
            <label for="kontak_2">Kontak Alternatif</label>
            <InputText id="kontak_2" v-model="formData.kontak_2" placeholder="Opsional" />
          </div>
        </div>

        <div class="field mb-4">
          <label for="alamat">Alamat Lengkap</label>
          <Textarea id="alamat" v-model="formData.alamat" rows="2" autoResize placeholder="Alamat domisili pemilik" />
        </div>

        <div class="field mb-4">
          <label for="kota">Kota</label>
          <InputText id="kota" v-model="formData.kota" placeholder="Contoh: Jakarta Selatan" />
        </div>
      </div>

      <!-- Section: Rekening (Aksen background) -->
      <div class="form-section highlight-section mb-4">
        <h3 class="section-title"><i class="pi pi-wallet mr-2"></i>Informasi Rekening</h3>
        
        <div class="field mb-3">
          <label for="bank">Nama Bank</label>
          <InputText id="bank" v-model="formData.bank" placeholder="BCA, Mandiri, BRI, dll" />
        </div>
        
        <div class="form-row">
          <div class="field">
            <label for="no_rek">Nomor Rekening</label>
            <InputText id="no_rek" v-model="formData.no_rek" placeholder="0000000000" />
          </div>
          <div class="field">
            <label for="atas_nama">Atas Nama</label>
            <InputText id="atas_nama" v-model="formData.atas_nama" placeholder="Nama di buku tabungan" />
          </div>
        </div>
      </div>

      <!-- Section: Status -->
      <div class="form-section status-footer">
        <div class="flex items-center justify-between">
          <div class="status-info">
            <span class="font-bold text-slate-700">Pemilik Internal?</span>
            <p class="text-xs text-slate-500">Aktifkan jika unit ini milik rental sendiri</p>
          </div>
          <InputSwitch v-model="formData.is_owner" />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <Button label="Batal" icon="pi pi-times" class="p-button-text p-button-secondary" @click="handleClose" />
        <Button 
          label="Simpan Data" 
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
  padding: 10px 5px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #475569;
}

.label-required::after {
  content: " *";
  color: #ef4444;
}

.highlight-section {
  background-color: #f8fafc;
  padding: 15px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
}

.section-title {
  font-size: 0.8rem;
  font-weight: 700;
  color: #06b6d4;
  margin: 0 0 12px 0;
  text-transform: uppercase;
  letter-spacing: 1px;
  display: flex;
  align-items: center;
}

.status-footer {
  border-top: 1px solid #f1f5f9;
  padding-top: 15px;
}

.flex {
  display: flex;
}

.items-center {
  align-items: center;
}

.justify-between {
  justify-content: space-between;
}

.mr-2 {
  margin-right: 8px;
}

.p-button-tosca {
  background-color: #06b6d4 !important;
  border-color: #06b6d4 !important;
  padding: 10px 20px !important;
}

.p-button-tosca:hover {
  background-color: #0891b2 !important;
  border-color: #0891b2 !important;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 10px;
}

:deep(.p-dialog-content) {
  padding: 0 1.5rem 1rem 1.5rem !important;
}
</style>
