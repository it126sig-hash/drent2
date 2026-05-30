<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import ToggleButton from 'primevue/togglebutton'

import { useTenant } from '../../composables/useTenant'
import { useCity } from '../../composables/useCity'

const toast = useToast()
const { tenant, loading: tenantLoading, fetch: fetchTenant, update: updateTenant } = useTenant()
const { cities, fetchAll: fetchCities } = useCity()

const saving = ref(false)
const formErrors = ref({})

const logoInput = ref(null)
const selectedLogo = ref(null)
const logoPreview = ref(null)
const shouldRemoveLogo = ref(false)

const form = ref({
  name: '',
  slug: '',
  is_active: true,
  phone: '',
  phone_alt: '',
  email: '',
  website: '',
  instagram: '',
  tiktok: '',
  facebook: '',
  city_id: null,
  logo_url: null,
})

const currentLogoUrl = computed(() => {
  if (logoPreview.value) return logoPreview.value
  if (shouldRemoveLogo.value) return null
  return form.value.logo_url
})

const slugLocked = computed(() => Boolean(tenant.value?.slug))

const fillForm = (data) => {
  if (!data) return
  form.value = {
    name: data.name || '',
    slug: data.slug || '',
    is_active: data.is_active ?? true,
    phone: data.phone || '',
    phone_alt: data.phone_alt || '',
    email: data.email || '',
    website: data.website || '',
    instagram: data.instagram || '',
    tiktok: data.tiktok || '',
    facebook: data.facebook || '',
    city_id: data.city_id || null,
    logo_url: data.logo_url || null,
  }
}

const errorMessage = (err, fallback) => {
  const errors = err?.response?.data?.errors
  if (errors) {
    const first = Object.values(errors).flat()[0]
    if (first) return first
  }
  return err?.response?.data?.message || fallback
}

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

const submitForm = async () => {
  saving.value = true
  formErrors.value = {}

  const payload = new FormData()
  payload.append('name', form.value.name || '')
  if (!slugLocked.value && form.value.slug) {
    payload.append('slug', form.value.slug)
  }
  payload.append('is_active', form.value.is_active ? '1' : '0')
  payload.append('phone', form.value.phone || '')
  payload.append('phone_alt', form.value.phone_alt || '')
  payload.append('email', form.value.email || '')
  payload.append('website', form.value.website || '')
  payload.append('instagram', form.value.instagram || '')
  payload.append('tiktok', form.value.tiktok || '')
  payload.append('facebook', form.value.facebook || '')
  if (form.value.city_id) payload.append('city_id', form.value.city_id)
  payload.append('remove_logo', shouldRemoveLogo.value ? '1' : '0')
  if (selectedLogo.value) payload.append('logo', selectedLogo.value)

  try {
    const updated = await updateTenant(payload)
    fillForm(updated)
    selectedLogo.value = null
    shouldRemoveLogo.value = false
    if (logoPreview.value) {
      URL.revokeObjectURL(logoPreview.value)
      logoPreview.value = null
    }
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Profil tenant berhasil diperbarui', life: 3000 })
  } catch (err) {
    if (err.response?.data?.errors) {
      formErrors.value = err.response.data.errors
    }
    toast.add({ severity: 'error', summary: 'Gagal', detail: errorMessage(err, 'Gagal menyimpan profil tenant'), life: 3500 })
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const data = await fetchTenant()
    fillForm(data)
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: errorMessage(err, 'Gagal memuat profil tenant'), life: 3000 })
  }
  await fetchCities({ per_page: 200, is_active: true }).catch(() => {})
})

onBeforeUnmount(() => {
  if (logoPreview.value) URL.revokeObjectURL(logoPreview.value)
})
</script>

<template>
  <div class="page-container tenant-settings-page">
    <div class="page-header">
      <div class="header-left">
        <div>
          <h1>Profil Tenant</h1>
          <p class="text-secondary text-xs">Kelola identitas, kontak, dan branding tenant ini.</p>
        </div>
      </div>
      <div class="header-actions">
        <button
          class="btn-pill btn-primary"
          type="button"
          :disabled="saving || tenantLoading"
          @click="submitForm"
        >
          <i :class="saving ? 'pi pi-spin pi-spinner' : 'pi pi-check'"></i>
          <span>Simpan Perubahan</span>
        </button>
      </div>
    </div>

    <div class="tenant-settings-grid">
      <!-- Branding Section -->
      <section class="app-card tenant-section">
        <div class="section-title">
          <i class="pi pi-image"></i>
          <span>Branding</span>
        </div>

        <div class="logo-block">
          <div class="logo-preview">
            <img v-if="currentLogoUrl" :src="currentLogoUrl" alt="Logo tenant" />
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
      </section>

      <!-- Identitas Section -->
      <section class="app-card tenant-section">
        <div class="section-title">
          <i class="pi pi-id-card"></i>
          <span>Identitas</span>
        </div>

        <div class="field">
          <label>Nama Tenant <span class="req">*</span></label>
          <InputText
            v-model="form.name"
            class="w-full"
            placeholder="DRENT Global"
            :class="{ 'p-invalid': formErrors.name }"
          />
          <small v-if="formErrors.name" class="p-error">{{ formErrors.name[0] }}</small>
        </div>

        <div class="field">
          <label>Slug</label>
          <InputText
            v-model="form.slug"
            class="w-full"
            placeholder="auto-generate dari nama kalau kosong"
            :disabled="slugLocked"
            :class="{ 'p-invalid': formErrors.slug }"
          />
          <small v-if="slugLocked" class="text-secondary text-xs">Slug terkunci karena sudah pernah disimpan.</small>
          <small v-if="formErrors.slug" class="p-error">{{ formErrors.slug[0] }}</small>
        </div>

        <div class="field">
          <label>Status</label>
          <ToggleButton
            v-model="form.is_active"
            onLabel="Aktif"
            offLabel="Nonaktif"
            onIcon="pi pi-check"
            offIcon="pi pi-times"
          />
        </div>
      </section>

      <!-- Kontak Section -->
      <section class="app-card tenant-section">
        <div class="section-title">
          <i class="pi pi-phone"></i>
          <span>Kontak</span>
        </div>

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
          <InputText v-model="form.email" class="w-full" placeholder="halo@drent.id"
            :class="{ 'p-invalid': formErrors.email }" />
          <small v-if="formErrors.email" class="p-error">{{ formErrors.email[0] }}</small>
        </div>
      </section>

      <!-- Sosial Media Section -->
      <section class="app-card tenant-section">
        <div class="section-title">
          <i class="pi pi-globe"></i>
          <span>Website & Sosial Media</span>
        </div>

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
      </section>
    </div>
  </div>
</template>

<style scoped>
.tenant-settings-page { background: var(--page-bg); }

.tenant-settings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
  gap: var(--space-lg);
}

.tenant-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding: var(--space-lg);
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: var(--font-headline);
  font-size: 14px;
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
  width: 160px;
  height: 160px;
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

.logo-placeholder i { font-size: 2.4rem; }

.logo-actions {
  display: flex;
  gap: var(--space-sm);
  flex-wrap: wrap;
}

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
  .field-row { grid-template-columns: 1fr; }
}
</style>
