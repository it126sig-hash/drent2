<script setup>
import { ref, watch, onMounted } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Dropdown from 'primevue/dropdown'
import Textarea from 'primevue/textarea'
import { getRentalOwners } from '../../api/rentalOwner'
import { useAuthStore } from '../../stores/auth'
import { useUnit } from '../../composables/useUnit'
import UnitPhotoManager from './UnitPhotoManager.vue'

const props = defineProps({
  visible: Boolean,
  unit: {
    type: Object,
    default: null
  },
  loading: Boolean
})

const emit = defineEmits(['update:visible', 'save', 'refresh'])
const authStore = useAuthStore()
const { addPhoto, removePhoto } = useUnit()

const formData = ref({
  tipe: '',
  merk: '',
  tahun: new Date().getFullYear(),
  no_polisi: '',
  rental_owner_id: null,
  harga_1_hari: 0,
  harga_1_minggu: 0,
  harga_1_bulan: 0,
  modal_1_hari: 0,
  modal_1_minggu: 0,
  modal_1_bulan: 0,
  status: 'Aktif',
  catatan: '',
  tenant_id: null,
  branch_id: null
})

const owners = ref([])
const statusOptions = [
  { label: 'Aktif', value: 'Aktif' },
  { label: 'Tidak Aktif', value: 'Tidak Aktif' },
  { label: 'Dalam Servis', value: 'Dalam Servis' }
]

onMounted(() => {
  fetchOwners()
})

const fetchOwners = async () => {
  try {
    const response = await getRentalOwners({ per_page: 100 })
    owners.value = response.data.data
  } catch (err) {
    console.error('Gagal mengambil data pemilik rental', err)
  }
}

watch(() => props.unit, (newVal) => {
  if (newVal) {
    formData.value = { 
      ...newVal,
      rental_owner_id: newVal.rental_owner_id || null
    }
  } else {
    resetForm()
  }
}, { immediate: true })

function resetForm() {
  formData.value = {
    tipe: '',
    merk: '',
    tahun: new Date().getFullYear(),
    no_polisi: '',
    rental_owner_id: null,
    harga_1_hari: 0,
    harga_1_minggu: 0,
    harga_1_bulan: 0,
    modal_1_hari: 0,
    modal_1_minggu: 0,
    modal_1_bulan: 0,
    status: 'Aktif',
    catatan: '',
    tenant_id: authStore.user?.tenant_id,
    branch_id: authStore.user?.branch_id
  }
}

const handleSave = () => {
  const required = ['tipe', 'merk', 'tahun', 'no_polisi', 'harga_1_hari', 'modal_1_hari']
  for (const field of required) {
    if (!formData.value[field] && formData.value[field] !== 0) return
  }

  formData.value.tenant_id = formData.value.tenant_id || authStore.user?.tenant_id
  formData.value.branch_id = formData.value.branch_id || authStore.user?.branch_id

  emit('save', { ...formData.value })
}

const handleClose = () => {
  emit('update:visible', false)
}

const onUploadPhoto = async ({ unitId, formData: photoData }) => {
  try {
    await addPhoto(unitId, photoData)
    emit('refresh') // Trigger refresh data unit di parent
  } catch (err) {
    console.error(err)
  }
}

const onDeletePhoto = async ({ unitId, photoId }) => {
  try {
    await removePhoto(unitId, photoId)
    emit('refresh')
  } catch (err) {
    console.error(err)
  }
}
</script>

<template>
  <Dialog 
    :visible="visible" 
    @update:visible="handleClose"
    :header="unit ? 'Edit Unit Kendaraan' : 'Tambah Unit Kendaraan'" 
    :modal="true" 
    class="custom-dialog"
    :style="{ width: '700px' }"
    :breakpoints="{ '960px': '75vw', '641px': '95vw' }"
  >
    <div class="form-container p-fluid">
      <!-- Section: Informasi Dasar -->
      <div class="form-section">
        <h3 class="section-title"><i class="pi pi-info-circle mr-2"></i>Informasi Kendaraan</h3>
        <div class="form-row">
          <div class="field">
            <label for="merk" class="label-required">Merk</label>
            <InputText id="merk" v-model="formData.merk" placeholder="Contoh: Toyota" :class="{ 'p-invalid': !formData.merk }" />
          </div>
          <div class="field">
            <label for="tipe" class="label-required">Tipe / Model</label>
            <InputText id="tipe" v-model="formData.tipe" placeholder="Contoh: Avanza Veloz" :class="{ 'p-invalid': !formData.tipe }" />
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="tahun" class="label-required">Tahun</label>
            <InputNumber id="tahun" v-model="formData.tahun" :useGrouping="false" placeholder="2023" :class="{ 'p-invalid': !formData.tahun }" />
          </div>
          <div class="field">
            <label for="no_polisi" class="label-required">Nomor Polisi (Plat)</label>
            <InputText id="no_polisi" v-model="formData.no_polisi" placeholder="B 1234 ABC" @input="formData.no_polisi = $event.target.value.toUpperCase()" :class="{ 'p-invalid': !formData.no_polisi }" />
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="rental_owner">Pemilik Rental</label>
            <Dropdown 
              id="rental_owner" 
              v-model="formData.rental_owner_id" 
              :options="owners" 
              optionLabel="nama" 
              optionValue="id" 
              placeholder="Pilih Pemilik" 
              showClear
              filter
            />
          </div>
          <div class="field">
            <label for="status" class="label-required">Status Unit</label>
            <Dropdown id="status" v-model="formData.status" :options="statusOptions" optionLabel="label" optionValue="value" />
          </div>
        </div>
      </div>

      <!-- Section: Keuangan (Harga & Modal) -->
      <div class="finance-grid">
        <!-- Section: Harga Sewa -->
        <div class="form-section highlight-section-blue">
          <h3 class="section-title text-cyan-600"><i class="pi pi-tag mr-2"></i>Harga Sewa</h3>
          <div class="field">
            <label for="harga_1_hari" class="label-required">1 Hari</label>
            <InputNumber id="harga_1_hari" v-model="formData.harga_1_hari" mode="currency" currency="IDR" locale="id-ID" :min="0" />
          </div>
          <div class="field">
            <label for="harga_1_minggu" class="label-required">1 Minggu</label>
            <InputNumber id="harga_1_minggu" v-model="formData.harga_1_minggu" mode="currency" currency="IDR" locale="id-ID" :min="0" />
          </div>
          <div class="field">
            <label for="harga_1_bulan" class="label-required">1 Bulan</label>
            <InputNumber id="harga_1_bulan" v-model="formData.harga_1_bulan" mode="currency" currency="IDR" locale="id-ID" :min="0" />
          </div>
        </div>

        <!-- Section: Modal / Biaya Pokok -->
        <div class="form-section highlight-section-gray">
          <h3 class="section-title text-slate-600"><i class="pi pi-money-bill mr-2"></i>Modal / Pokok</h3>
          <div class="field">
            <label for="modal_1_hari" class="label-required">1 Hari</label>
            <InputNumber id="modal_1_hari" v-model="formData.modal_1_hari" mode="currency" currency="IDR" locale="id-ID" :min="0" />
          </div>
          <div class="field">
            <label for="modal_1_minggu" class="label-required">1 Minggu</label>
            <InputNumber id="modal_1_minggu" v-model="formData.modal_1_minggu" mode="currency" currency="IDR" locale="id-ID" :min="0" />
          </div>
          <div class="field">
            <label for="modal_1_bulan" class="label-required">1 Bulan</label>
            <InputNumber id="modal_1_bulan" v-model="formData.modal_1_bulan" mode="currency" currency="IDR" locale="id-ID" :min="0" />
          </div>
        </div>
      </div>

      <div class="field">
        <label for="catatan">Catatan / Keterangan</label>
        <Textarea id="catatan" v-model="formData.catatan" rows="2" placeholder="Informasi tambahan mengenai unit" />
      </div>

      <!-- Photo Section (Only in Edit Mode) -->
      <UnitPhotoManager 
        v-if="unit && unit.id" 
        :unitId="unit.id" 
        :photos="unit.photos" 
        :loading="loading"
        @upload="onUploadPhoto"
        @delete="onDeletePhoto"
      />
    </div>

    <template #footer>
      <div class="dialog-footer">
        <Button label="Batal" icon="pi pi-times" class="p-button-text p-button-secondary" @click="handleClose" />
        <Button 
          label="Simpan Unit" 
          icon="pi pi-save" 
          class="p-button-tosca" 
          @click="handleSave" 
          :loading="loading" 
          :disabled="!formData.merk || !formData.tipe || !formData.no_polisi" 
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
  gap: 12px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-row-three {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 16px;
}

.finance-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 640px) {
  .finance-grid {
    grid-template-columns: 1fr;
  }
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

.highlight-section-blue {
  background-color: #f0f9ff;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #bae6fd;
  box-shadow: inset 0 1px 2px rgba(186, 230, 253, 0.5);
}

.highlight-section-gray {
  background-color: #f8fafc;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: inset 0 1px 2px rgba(226, 232, 240, 0.5);
}

.text-cyan-600 { color: #0891b2; }
.text-slate-600 { color: #475569; }

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

:deep(.p-inputnumber-input) {
  font-weight: 600;
  color: #1e293b;
}

:deep(.p-inputtext), :deep(.p-dropdown), :deep(.p-textarea) {
  border-radius: 8px;
}
</style>
