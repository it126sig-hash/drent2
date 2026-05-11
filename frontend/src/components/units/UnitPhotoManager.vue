<script setup>
import { ref } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
  unitId: {
    type: [Number, String],
    required: true
  },
  photos: {
    type: Array,
    default: () => []
  },
  loading: Boolean
})

const emit = defineEmits(['refresh', 'upload', 'delete'])
const toast = useToast()

const selectedFile = ref(null)
const label = ref('')
const isUploading = ref(false)

const onFileSelect = (event) => {
  const file = event.target.files[0]
  if (file) {
    if (file.size > 5 * 1024 * 1024) {
      toast.add({ severity: 'error', summary: 'Gagal', detail: 'Ukuran file maksimal 5MB', life: 3000 })
      event.target.value = ''
      return
    }
    selectedFile.value = file
  }
}

const handleUpload = async () => {
  if (!selectedFile.value) return

  isUploading.value = true
  const formData = new FormData()
  formData.append('photo', selectedFile.value)
  if (label.value) formData.append('label', label.value)

  try {
    emit('upload', { unitId: props.unitId, formData })
    selectedFile.value = null
    label.value = ''
    // Clear input file
    const input = document.getElementById('photo-input')
    if (input) input.value = ''
  } catch (err) {
    // Parent handles error toast
  } finally {
    isUploading.value = false
  }
}

const handleDelete = (photoId) => {
  emit('delete', { unitId: props.unitId, photoId })
}
</script>

<template>
  <div class="photo-manager">
    <h3 class="section-title"><i class="pi pi-images mr-2"></i>Foto Unit</h3>
    
    <!-- Upload Form -->
    <div class="upload-section mb-4">
      <div class="upload-controls">
        <div class="file-input-wrapper">
          <input 
            type="file" 
            id="photo-input" 
            accept="image/*" 
            @change="onFileSelect" 
            class="hidden-input"
          />
          <label for="photo-input" class="file-label">
            <i class="pi pi-camera mr-2"></i>
            {{ selectedFile ? selectedFile.name : 'Pilih Foto...' }}
          </label>
        </div>
        <InputText v-model="label" placeholder="Label (Depan, Dalam, dll)" class="label-input" />
        <Button 
          icon="pi pi-upload" 
          label="Unggah" 
          @click="handleUpload" 
          :disabled="!selectedFile || loading" 
          :loading="isUploading"
          class="p-button-tosca"
        />
      </div>
    </div>

    <!-- Photo Grid -->
    <div v-if="photos.length > 0" class="photo-grid">
      <div v-for="photo in photos" :key="photo.id" class="photo-card">
        <img :src="photo.url" :alt="photo.label" class="unit-thumbnail" />
        <div class="photo-info">
          <span class="photo-label">{{ photo.label || 'Tanpa Label' }}</span>
          <Button 
            icon="pi pi-trash" 
            class="p-button-rounded p-button-danger p-button-text p-button-sm" 
            @click="handleDelete(photo.id)"
            v-tooltip.top="'Hapus Foto'"
          />
        </div>
      </div>
    </div>
    <div v-else class="empty-photos">
      <p>Belum ada foto yang diunggah.</p>
    </div>
  </div>
</template>

<style scoped>
.photo-manager {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
}

.section-title {
  font-size: 0.75rem;
  font-weight: 800;
  color: #0891b2;
  margin: 0 0 15px 0;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  display: flex;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 8px;
}

.upload-section {
  background: #f8fafc;
  padding: 15px;
  border-radius: 8px;
  border: 1px dashed #cbd5e1;
}

.upload-controls {
  display: flex;
  gap: 10px;
  align-items: center;
}

.file-input-wrapper {
  flex: 1;
}

.hidden-input {
  display: none;
}

.file-label {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 10px;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.85rem;
  color: #4b5563;
  transition: all 0.2s;
}

.file-label:hover {
  border-color: #06b6d4;
  color: #06b6d4;
}

.label-input {
  flex: 1;
}

.photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 15px;
}

.photo-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  transition: transform 0.2s;
}

.photo-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
}

.unit-thumbnail {
  width: 100%;
  height: 100px;
  object-fit: cover;
}

.photo-info {
  padding: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
}

.photo-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.empty-photos {
  padding: 30px;
  text-align: center;
  color: #94a3b8;
  font-style: italic;
  font-size: 0.9rem;
}

.p-button-tosca {
  background-color: #06b6d4 !important;
  border-color: #06b6d4 !important;
}

.mr-2 { margin-right: 8px; }
</style>
