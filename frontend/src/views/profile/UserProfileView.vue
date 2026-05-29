<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Textarea from 'primevue/textarea'

import { getProfile, updateProfile, updateProfilePassword } from '../../api/profile'
import { useAuthStore } from '../../stores/auth'

const toast = useToast()
const authStore = useAuthStore()

const loading = ref(false)
const savingProfile = ref(false)
const savingPassword = ref(false)
const photoInput = ref(null)
const selectedPhoto = ref(null)
const photoPreview = ref(null)
const shouldRemovePhoto = ref(false)

const profileForm = ref({
  name: '',
  nik: '',
  alamat: '',
  no_rekening: '',
  bank: '',
  atas_nama: '',
  kontak: '',
  foto_profile_url: null,
})

const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const bankOptions = [
  'BCA',
  'Mandiri',
  'BRI',
  'BNI',
  'CIMB Niaga',
  'Permata',
  'Danamon',
  'BTN',
  'BSI',
]

const currentPhotoUrl = computed(() => {
  if (photoPreview.value) return photoPreview.value
  if (shouldRemovePhoto.value) return null
  return profileForm.value.foto_profile_url
})

const initials = computed(() => {
  const name = profileForm.value.name || authStore.user?.name || 'US'
  return name.substring(0, 2).toUpperCase()
})

const fillProfile = (user) => {
  profileForm.value = {
    name: user?.name || '',
    nik: user?.nik || '',
    alamat: user?.alamat || '',
    no_rekening: user?.no_rekening || '',
    bank: user?.bank || '',
    atas_nama: user?.atas_nama || '',
    kontak: user?.kontak || '',
    foto_profile_url: user?.foto_profile_url || null,
  }
}

const errorMessage = (error, fallback) => {
  const errors = error?.response?.data?.errors
  if (errors) {
    const first = Object.values(errors).flat()[0]
    if (first) return first
  }

  return error?.response?.data?.message || fallback
}

const fetchProfile = async () => {
  loading.value = true
  try {
    const { data } = await getProfile()
    fillProfile(data.data)
    authStore.setUser(data.data)
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal memuat profil user', life: 3000 })
  } finally {
    loading.value = false
  }
}

const openPhotoPicker = () => {
  photoInput.value?.click()
}

const selectPhoto = (event) => {
  const file = event.target.files?.[0]
  if (!file) return

  if (photoPreview.value) URL.revokeObjectURL(photoPreview.value)

  selectedPhoto.value = file
  shouldRemovePhoto.value = false
  photoPreview.value = URL.createObjectURL(file)
}

const removePhoto = () => {
  if (photoPreview.value) {
    URL.revokeObjectURL(photoPreview.value)
  }

  selectedPhoto.value = null
  photoPreview.value = null
  shouldRemovePhoto.value = true

  if (photoInput.value) {
    photoInput.value.value = ''
  }
}

const submitProfile = async () => {
  savingProfile.value = true

  const payload = new FormData()
  payload.append('name', profileForm.value.name)
  payload.append('nik', profileForm.value.nik || '')
  payload.append('alamat', profileForm.value.alamat || '')
  payload.append('no_rekening', profileForm.value.no_rekening || '')
  payload.append('bank', profileForm.value.bank || '')
  payload.append('atas_nama', profileForm.value.atas_nama || '')
  payload.append('kontak', profileForm.value.kontak || '')
  payload.append('remove_foto_profile', shouldRemovePhoto.value ? '1' : '0')

  if (selectedPhoto.value) {
    payload.append('foto_profile', selectedPhoto.value)
  }

  try {
    const { data } = await updateProfile(payload)
    fillProfile(data.data)
    authStore.setUser(data.data)
    selectedPhoto.value = null
    shouldRemovePhoto.value = false
    if (photoPreview.value) {
      URL.revokeObjectURL(photoPreview.value)
      photoPreview.value = null
    }
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Profil berhasil diperbarui', life: 3000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: errorMessage(error, 'Gagal menyimpan profil'), life: 3500 })
  } finally {
    savingProfile.value = false
  }
}

const submitPassword = async () => {
  savingPassword.value = true

  try {
    await updateProfilePassword(passwordForm.value)
    passwordForm.value = {
      current_password: '',
      password: '',
      password_confirmation: '',
    }
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Password berhasil diperbarui', life: 3000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: errorMessage(error, 'Gagal memperbarui password'), life: 3500 })
  } finally {
    savingPassword.value = false
  }
}

onMounted(fetchProfile)

onBeforeUnmount(() => {
  if (photoPreview.value) {
    URL.revokeObjectURL(photoPreview.value)
  }
})
</script>

<template>
  <div class="page-container profile-page">
    <div class="page-header">
      <div class="header-left">
        <div>
          <h1>Profil User</h1>
          <p class="text-secondary text-xs">Kelola informasi personal, rekening bank, dan keamanan akun Anda dalam satu tempat.</p>
        </div>
      </div>
    </div>

    <div class="profile-layout">
      <div class="profile-main">
        <section class="app-card profile-summary-card">
          <div class="profile-photo">
            <img v-if="currentPhotoUrl" :src="currentPhotoUrl" alt="Foto profil" />
            <span v-else>{{ initials }}</span>
            <span class="photo-status"><i class="pi pi-check"></i></span>
          </div>

          <div class="profile-summary-copy">
            <h2>{{ profileForm.name || 'Nama User' }}</h2>
            <p>{{ authStore.user?.role_label || authStore.user?.role || '-' }} <span v-if="profileForm.nik">• {{ profileForm.nik }}</span></p>
            <div class="photo-actions">
              <input
                ref="photoInput"
                type="file"
                accept="image/*"
                class="hidden-input"
                @change="selectPhoto"
              />
              <button
                class="btn-pill btn-profile-photo"
                type="button"
                :disabled="savingProfile || loading"
                @click="openPhotoPicker"
              >
                <i class="pi pi-camera"></i>
                <span>Pilih Foto</span>
              </button>
              <button
                class="btn-pill btn-profile-danger"
                type="button"
                :disabled="(!currentPhotoUrl && !selectedPhoto) || savingProfile || loading"
                @click="removePhoto"
              >
                <i class="pi pi-trash"></i>
                <span>Hapus</span>
              </button>
            </div>
          </div>
        </section>

        <section class="app-card profile-info-card">
          <div class="app-section-header profile-section-header">
            <div class="section-title">
              <i class="pi pi-user"></i>
              <span>Informasi Personal</span>
            </div>
          </div>

          <form class="profile-form" @submit.prevent="submitProfile">
            <div class="form-grid two-columns">
              <div class="form-field">
                <label>Nama Lengkap</label>
                <InputText v-model="profileForm.name" :disabled="loading" required />
              </div>
              <div class="form-field">
                <label>NIK (Nomor Induk Karyawan)</label>
                <InputText v-model="profileForm.nik" :disabled="loading" placeholder="Contoh: EMP-001-2024" />
              </div>
              <div class="form-field">
                <label>Kontak (WhatsApp/Telp)</label>
                <InputText v-model="profileForm.kontak" :disabled="loading" placeholder="+62 812 3456 7890" />
              </div>
              <div class="form-field">
                <label>Bank</label>
                <Dropdown
                  v-model="profileForm.bank"
                  :options="bankOptions"
                  editable
                  :disabled="loading"
                  placeholder="Pilih atau ketik bank"
                  class="w-full"
                />
              </div>
              <div class="form-field">
                <label>Nomor Rekening</label>
                <InputText v-model="profileForm.no_rekening" :disabled="loading" placeholder="0000 0000 0000" />
              </div>
              <div class="form-field">
                <label>Atas Nama Rekening</label>
                <InputText v-model="profileForm.atas_nama" :disabled="loading" placeholder="Contoh: Femi Hartanti" />
              </div>
              <div class="form-field full-span">
                <label>Alamat Lengkap</label>
                <Textarea v-model="profileForm.alamat" rows="4" autoResize :disabled="loading" placeholder="Masukkan alamat domisili saat ini..." />
              </div>
            </div>

            <div class="form-actions profile-save-actions">
              <button
                class="btn-pill btn-save-profile"
                type="submit"
                :disabled="loading || savingProfile"
              >
                <i :class="savingProfile ? 'pi pi-spin pi-spinner' : 'pi pi-save'"></i>
                <span>Simpan Profil</span>
              </button>
            </div>
          </form>
        </section>
      </div>

      <aside class="profile-sidebar">
        <section class="app-card password-panel">
          <div class="app-section-header password-header">
            <div class="section-title">
              <i class="pi pi-lock"></i>
              <span>Keamanan Akun</span>
            </div>
          </div>

          <form class="password-form" @submit.prevent="submitPassword">
            <p class="password-note">Pastikan password Anda minimal 8 karakter dengan kombinasi huruf besar, kecil, dan angka untuk keamanan maksimal.</p>

            <div class="form-field">
              <label>Password Saat Ini</label>
              <Password
                v-model="passwordForm.current_password"
                toggleMask
                :feedback="false"
                inputClass="w-full"
                class="w-full"
                required
              />
            </div>
            <div class="form-field">
              <label>Password Baru</label>
              <Password
                v-model="passwordForm.password"
                toggleMask
                inputClass="w-full"
                class="w-full"
                required
              />
            </div>
            <div class="form-field">
              <label>Konfirmasi Password</label>
              <Password
                v-model="passwordForm.password_confirmation"
                toggleMask
                :feedback="false"
                inputClass="w-full"
                class="w-full"
                required
              />
            </div>

            <button
              class="btn-pill btn-password-submit"
              type="submit"
              :disabled="savingPassword"
            >
              <i :class="savingPassword ? 'pi pi-spin pi-spinner' : 'pi pi-key'"></i>
              <span>Perbarui Password</span>
            </button>
          </form>
        </section>

        <section class="app-muted-panel security-tips">
          <div class="tips-icon">
            <i class="pi pi-shield"></i>
          </div>
          <h3>Tips Keamanan</h3>
          <ul>
            <li>Jangan pernah bagikan password Anda kepada siapapun.</li>
            <li>Aktifkan Two-Factor Authentication (2FA) jika tersedia.</li>
            <li>Logout dari perangkat yang bukan milik pribadi.</li>
          </ul>
        </section>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.profile-page {
  background: var(--page-bg);
}

.profile-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: var(--space-lg);
  align-items: start;
}

.profile-main,
.profile-sidebar {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.profile-summary-card {
  min-height: 136px;
  padding: var(--space-lg) var(--space-xl);
  display: flex;
  align-items: center;
  gap: var(--space-lg);
}

.profile-photo {
  position: relative;
  width: 112px;
  height: 112px;
  flex: 0 0 112px;
  border-radius: var(--radius-default);
  border: 1px solid var(--surface-border);
  background: #E7F0FF;
  box-shadow: inset 0 0 0 4px rgba(255, 255, 255, 0.72);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  color: #007A44;
  font-family: var(--font-headline);
  font-size: 34px;
  font-weight: 700;
}

.profile-photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.photo-status {
  position: absolute;
  right: 6px;
  bottom: 6px;
  width: 22px;
  height: 22px;
  border: 2px solid #FFFFFF;
  border-radius: var(--radius-default);
  background: #008F5A;
  color: #FFFFFF;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
}

.profile-summary-copy {
  min-width: 0;
}

.profile-summary-copy h2 {
  margin: 0 0 4px;
  font-family: var(--font-headline);
  font-size: 18px;
  font-weight: 700;
  color: var(--text-primary);
}

.profile-summary-copy p {
  margin: 0 0 var(--space-md);
  font-size: 12px;
  color: var(--text-secondary);
}

.photo-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-sm);
}

.hidden-input {
  display: none;
}

.btn-profile-photo {
  min-height: 34px;
  padding: 7px 14px;
  border-radius: var(--radius-sm);
  background: #008F5A;
  color: #FFFFFF;
}

.btn-profile-danger {
  min-height: 34px;
  padding: 7px 14px;
  border-radius: var(--radius-sm);
  border: 1px solid rgba(229, 83, 75, 0.32);
  background: #FFFFFF;
  color: var(--negative);
}

.profile-info-card {
  overflow: hidden;
}

.profile-section-header {
  background: #EEF5FF;
}

.password-header {
  background: #FFFDFC;
}

.section-title {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 700;
}

.section-title i {
  color: #007A44;
  font-size: 14px;
}

.password-header .section-title i {
  color: var(--negative);
}

.profile-form {
  padding: var(--space-xl);
}

.form-grid {
  display: grid;
  gap: var(--space-lg) var(--space-xl);
}

.two-columns {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.full-span {
  grid-column: 1 / -1;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  min-width: 0;
}

.form-field label {
  font-size: 10px;
  font-weight: 600;
  line-height: 1.35;
  color: var(--text-secondary);
}

.form-field :deep(.p-inputtext),
.form-field :deep(.p-dropdown),
.form-field :deep(.p-password-input) {
  width: 100%;
  min-height: 40px;
  border-radius: var(--radius-sm);
}

.form-field :deep(textarea.p-inputtext) {
  min-height: 88px;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: var(--space-xl);
}

.profile-save-actions {
  padding-top: var(--space-md);
  border-top: 1px solid rgba(218, 224, 235, 0.72);
}

.btn-save-profile {
  min-width: 168px;
  border-radius: var(--radius-sm);
  background: #00A66A;
  color: #FFFFFF;
  box-shadow: 0 8px 18px rgba(0, 166, 106, 0.16);
}

.password-panel {
  overflow: hidden;
}

.password-form {
  padding: var(--space-lg) var(--space-xl) var(--space-xl);
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.password-note {
  margin: 0 0 var(--space-sm);
  font-size: 11px;
  line-height: 1.65;
  color: var(--text-secondary);
}

.btn-password-submit {
  width: 100%;
  margin-top: var(--space-sm);
  border-radius: var(--radius-sm);
  background: var(--text-primary);
  color: #FFFFFF;
}

.security-tips {
  padding: var(--space-xl);
  background: #E5F0FF;
  border-color: #D4E2F6;
}

.tips-icon {
  width: 36px;
  height: 36px;
  margin-bottom: var(--space-sm);
  border-radius: var(--radius-sm);
  background: #FFFFFF;
  color: #008F5A;
  display: flex;
  align-items: center;
  justify-content: center;
}

.security-tips h3 {
  margin: 0 0 var(--space-sm);
  font-family: var(--font-headline);
  font-size: 13px;
  color: var(--text-primary);
}

.security-tips ul {
  margin: 0;
  padding-left: 16px;
  color: var(--text-primary);
  font-size: 11px;
  line-height: 1.55;
}

.security-tips li::marker {
  color: #008F5A;
}

@media (max-width: 1180px) {
  .profile-layout {
    grid-template-columns: 1fr;
  }

  .profile-sidebar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 0.75fr);
  }
}

@media (max-width: 760px) {
  .profile-summary-card {
    align-items: flex-start;
    padding: var(--space-lg);
  }

  .profile-sidebar {
    display: flex;
  }

  .two-columns {
    grid-template-columns: 1fr;
  }

  .profile-photo {
    width: 88px;
    height: 88px;
    flex-basis: 88px;
    font-size: 26px;
  }

  .profile-form,
  .password-form,
  .security-tips {
    padding: var(--space-lg);
  }

  .form-actions {
    justify-content: stretch;
  }

  .form-actions .btn-pill,
  .btn-password-submit {
    width: 100%;
  }
}

@media (max-width: 520px) {
  .profile-summary-card {
    flex-direction: column;
  }
}
</style>
