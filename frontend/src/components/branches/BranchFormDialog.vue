<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'

const props = defineProps({
  visible: { type: Boolean, default: false },
  branch: { type: Object, default: null },
  cities: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['update:visible', 'submit'])

const isEdit = computed(() => Boolean(props.branch?.id))

const form = ref(emptyForm())
const formErrors = ref({})

const logoInput = ref(null)
const selectedLogo = ref(null)
const logoPreview = ref(null)
const shouldRemoveLogo = ref(false)

function emptyForm() {
  return {
    name: '',
    address: '',
    phone: '',
    phone_alt: '',
    email: '',
    website: '',
    instagram: '',
    tiktok: '',
    facebook: '',
    city_id: null,
    logo_url: null,
  }
}

const fillForm = (data) => {
  form.value = {
    name: data?.name || '',
    address: data?.address || '',
    phone: data?.phone || '',
    phone_alt: data?.phone_alt || '',
    email: data?.email || '',
    website: data?.website || '',
    instagram: data?.instagram || '',
    tiktok: data?.tiktok || '',
    facebook: data?.facebook || '',
    city_id: data?.city_id || null,
    logo_url: data?.logo_url || null,
  }
  formErrors.value = {}
  selectedLogo.value = null
  shouldRemoveLogo.value = false
  if (logoPreview.value) {
    URL.revokeObjectURL(logoPreview.value)
    logoPreview.value = null
  }
}

watch(
  () => props.visible,
  (visible) => {
    if (visible) {
      fillForm(props.branch || {})
    }
  },
  { immediate: true }
)

const currentLogoUrl = computed(() => {
  if (logoPreview.value) return logoPreview.value
  if (shouldRemoveLogo.value) return null
  return form.value.logo_url
})

const closeDialog = () => emit('update:visible', false)

const openLogoPicker = () => logoInput.value?.click()

const selectLogo = (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  if (logoPreview.value) URL.revokeObjectURL(logoPreview.value)
  selectedLogo.value = file
  shouldRemoveLogo.value = false
  logoPreview.value = URL.createObjectURL(file)
}

const removeLogo = () => {
  if (logoPreview.value) URL.revokeObjectURL(logoPreview.value)
  selectedLogo.value = null
  logoPreview.value = null
  shouldRemoveLogo.value = true
  if (logoInput.value) logoInput.value.value = ''
}

const submit = () => {
  formErrors.value = {}

  const payload = new FormData()
  payload.append('name', form.value.name || '')
  payload.append('address', form.value.address || '')
  payload.append('phone', form.value.phone || '')
  payload.append('phone_alt', form.value.phone_alt || '')
  payload.append('email', form.value.email || '')
  payload.append('website', form.value.website || '')
  payload.append('instagram', form.value.instagram || '')
  payload.append('tiktok', form.value.tiktok || '')
  payload.append('facebook', form.value.facebook || '')
  if (form.value.city_id) payload.append('city_id', form.value.city_id)
  if (isEdit.value) payload.append('remove_logo', shouldRemoveLogo.value ? '1' : '0')
  if (selectedLogo.value) payload.append('logo', selectedLogo.value)

  emit('submit', { id: props.branch?.id || null, formData: payload, onError: handleError })
}

const handleError = (errors) => {
  if (errors && typeof errors === 'object') {
    formErrors.value = errors
  }
}

onBeforeUnmount(() => {
  if (logoPreview.value) URL.revokeObjectURL(logoPreview.value)
})
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="(v) => emit('update:visible', v)"
    :header="isEdit ? `Edit Cabang: ${branch?.name || ''}` : 'Tambah Cabang'"
    modal
    class="custom-dialog branch-form-dialog"
    :style="{ width: '760px' }"
  >
    <div class="form-grid">
      <!-- Branding -->
      <div class="form-section">
        <div class="section-title"><i class="pi pi-image"></i><span>Branding</span></div>
        <div class="logo-block">
          <div class="logo-preview">
            <img v-if="currentLogoUrl" :src="currentLogoUrl" alt="Logo cabang" />
            <div v-else class="logo-placeholder">
              <i class="pi pi-image"></i>
              <span>Belum ada logo</span>
            </div>
          </div>
          <div class="logo-actions">
            <input
              ref="logoInput"
              type="file"
              accept="image/png,image/jpeg,image/webp"
              class="hidden-input"
              @change="selectLogo"
            />
            <button class="btn-pill btn-secondary" type="button" :disabled="saving" @click="openLogoPicker">
              <i class="pi pi-upload"></i>
              <span>Pilih Logo</span>
            </button>
            <button
              class="btn-pill btn-pill-danger"
              type="button"
              :disabled="saving || (!currentLogoUrl && !selectedLogo)"
              @click="removeLogo"
            >
              <i class="pi pi-trash"></i>
              <span>Hapus</span>
            </button>
          </div>
          <small class="text-secondary text-xs">Format: jpg, png, webp. Maks 2MB.</small>
        </div>
      </div>

      <!-- Identitas -->
      <div class="form-section">
        <div class="section-title"><i class="pi pi-id-card"></i><span>Identitas</span></div>

        <div class="field">
          <label>Nama Cabang <span class="req">*</span></label>
          <InputText v-model="form.name" class="w-full" placeholder="Pusat Jakarta"
            :class="{ 'p-invalid': formErrors.name }" />
          <small v-if="formErrors.name" class="p-error">{{ formErrors.name[0] }}</small>
        </div>

        <div class="field">
          <label>Kota</label>
          <Dropdown
            v-model="form.city_id"
            :options="cities"
            option-label="nama"
            option-value="id"
            placeholder="Pilih kota..."
            :showClear="true"
            class="w-full"
            :class="{ 'p-invalid': formErrors.city_id }"
            filter
          />
          <small v-if="formErrors.city_id" class="p-error">{{ formErrors.city_id[0] }}</small>
        </div>

        <div class="field">
          <label>Alamat</label>
          <Textarea v-model="form.address" rows="3" autoResize class="w-full"
            placeholder="Jl. Kebagusan Raya No. 1, Jakarta Selatan"
            :class="{ 'p-invalid': formErrors.address }" />
          <small v-if="formErrors.address" class="p-error">{{ formErrors.address[0] }}</small>
        </div>
      </div>

      <!-- Kontak -->
      <div class="form-section">
        <div class="section-title"><i class="pi pi-phone"></i><span>Kontak</span></div>

        <div class="field-row">
          <div class="field">
            <label>Telepon Utama</label>
            <InputText v-model="form.phone" class="w-full" placeholder="021-1234567"
              :class="{ 'p-invalid': formErrors.phone }" />
            <small v-if="formErrors.phone" class="p-error">{{ formErrors.phone[0] }}</small>
          </div>
          <div class="field">
            <label>Telepon Alternatif</label>
            <InputText v-model="form.phone_alt" class="w-full" placeholder="0812-3456-7890"
              :class="{ 'p-invalid': formErrors.phone_alt }" />
            <small v-if="formErrors.phone_alt" class="p-error">{{ formErrors.phone_alt[0] }}</small>
          </div>
        </div>

        <div class="field">
          <label>Email</label>
          <InputText v-model="form.email" class="w-full" placeholder="cabang@drent.id"
            :class="{ 'p-invalid': formErrors.email }" />
          <small v-if="formErrors.email" class="p-error">{{ formErrors.email[0] }}</small>
        </div>
      </div>

      <!-- Web & Sosmed -->
      <div class="form-section">
        <div class="section-title"><i class="pi pi-globe"></i><span>Website & Sosial Media</span></div>

        <div class="field">
          <label>Website</label>
          <InputText v-model="form.website" class="w-full" placeholder="https://drent.id"
            :class="{ 'p-invalid': formErrors.website }" />
          <small v-if="formErrors.website" class="p-error">{{ formErrors.website[0] }}</small>
        </div>
        <div class="field">
          <label>Instagram</label>
          <InputText v-model="form.instagram" class="w-full" placeholder="https://instagram.com/drent"
            :class="{ 'p-invalid': formErrors.instagram }" />
          <small v-if="formErrors.instagram" class="p-error">{{ formErrors.instagram[0] }}</small>
        </div>
        <div class="field">
          <label>TikTok</label>
          <InputText v-model="form.tiktok" class="w-full" placeholder="https://tiktok.com/@drent"
            :class="{ 'p-invalid': formErrors.tiktok }" />
          <small v-if="formErrors.tiktok" class="p-error">{{ formErrors.tiktok[0] }}</small>
        </div>
        <div class="field">
          <label>Facebook</label>
          <InputText v-model="form.facebook" class="w-full" placeholder="https://facebook.com/drent"
            :class="{ 'p-invalid': formErrors.facebook }" />
          <small v-if="formErrors.facebook" class="p-error">{{ formErrors.facebook[0] }}</small>
        </div>
      </div>
    </div>

    <template #footer>
      <button class="app-dialog-button app-dialog-button-secondary" type="button" :disabled="saving" @click="closeDialog">
        <i class="pi pi-times"></i>
        Batal
      </button>
      <button class="app-dialog-button app-dialog-button-primary" type="button" :disabled="saving" @click="submit">
        <i :class="saving ? 'pi pi-spin pi-spinner' : 'pi pi-check'"></i>
        Simpan
      </button>
    </template>
  </Dialog>
</template>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-lg);
  padding: var(--space-sm) 0;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: var(--font-headline);
  font-size: 13px;
  font-weight: 700;
  color: var(--text-primary);
  padding-bottom: var(--space-sm);
  border-bottom: 1px solid var(--surface-border);
}

.section-title i { color: var(--primary); }

.field { display: flex; flex-direction: column; gap: var(--space-sm); }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md); }
.field label { color: var(--text-secondary); font-size: 12px; font-weight: 700; }
.req { color: var(--negative); }
.w-full { width: 100%; }

.logo-block {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  align-items: flex-start;
}

.logo-preview {
  width: 140px;
  height: 140px;
  border-radius: var(--radius-default);
  border: 1px dashed var(--surface-border);
  background: var(--surface-default);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.logo-preview img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.logo-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  color: var(--text-tertiary);
}

.logo-placeholder i { font-size: 2rem; }

.logo-actions { display: flex; gap: var(--space-sm); flex-wrap: wrap; }

.hidden-input {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  border: 0;
  clip: rect(0, 0, 0, 0);
  overflow: hidden;
}

.btn-pill-danger {
  background: #fdecec;
  color: #b91c1c;
  border-color: transparent;
}

.btn-pill-danger:hover:not(:disabled) {
  background: #fbd9d9;
}

@media (max-width: 720px) {
  .form-grid { grid-template-columns: 1fr; }
  .field-row { grid-template-columns: 1fr; }
}
</style>
