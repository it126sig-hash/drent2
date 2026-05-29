<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Message from 'primevue/message'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const errorMessage = ref('')

const handleLogin = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    await authStore.login({
      email: email.value,
      password: password.value
    })
    router.push({ name: 'dashboard' })
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Login gagal. Periksa kembali email dan password Anda.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-container">
    <aside class="login-info-panel" aria-label="Ringkasan DRENT">
      <div class="brand-lockup">
        <span class="brand-kicker">Rental Operations</span>
        <img class="brand-logo" src="/logo.svg" alt="DRENT Vibe" />
        <p>Sistem kerja internal untuk booking, armada, keuangan, dan laporan cabang.</p>
      </div>

      <div class="ops-summary">
        <div class="summary-item">
          <span class="summary-icon"><i class="pi pi-car"></i></span>
          <div>
            <strong>Armada</strong>
            <span>Status unit dan jadwal aktif dalam satu layar kerja.</span>
          </div>
        </div>
        <div class="summary-item">
          <span class="summary-icon"><i class="pi pi-wallet"></i></span>
          <div>
            <strong>Keuangan</strong>
            <span>Invoice, mutasi, dan outstanding mudah diaudit.</span>
          </div>
        </div>
        <div class="summary-item">
          <span class="summary-icon"><i class="pi pi-shield"></i></span>
          <div>
            <strong>Akses Tim</strong>
            <span>Peran pengguna mengikuti alur operasional cabang.</span>
          </div>
        </div>
      </div>
    </aside>

    <main class="login-form-panel">
      <section class="login-card surface-card" aria-label="Form masuk">
        <div class="form-header">
          <img class="form-logo" src="/logo.svg" alt="DRENT Vibe" />
          <h2>Masuk ke sistem</h2>
          <p>Gunakan akun operasional yang sudah terdaftar.</p>
        </div>

        <form @submit.prevent="handleLogin" class="login-form">
          <div v-if="errorMessage" class="error-wrapper">
            <Message severity="error" :closable="false">{{ errorMessage }}</Message>
          </div>

          <div class="field-group">
            <label for="email">Email</label>
            <InputText id="email" v-model="email" type="email" placeholder="admin@drent.id" autocomplete="email" required class="w-full" />
          </div>

          <div class="field-group">
            <label for="password">Kata Sandi</label>
            <Password id="password" v-model="password" :feedback="false" toggleMask placeholder="Kata sandi akun" autocomplete="current-password" required class="w-full" inputClass="w-full" />
          </div>

          <Button type="submit" label="Masuk" icon="pi pi-sign-in" :loading="loading" class="login-btn" />
        </form>

        <div class="form-footer">
          DRENT Vibe 2026
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
.login-container {
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(420px, 0.95fr);
  min-height: 100vh;
  width: 100%;
  background: var(--page-bg);
}

.login-info-panel {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: var(--space-3xl);
  overflow: hidden;
  padding: clamp(32px, 6vw, 72px);
  color: var(--text-white);
  background:
    radial-gradient(circle at 18% 18%, color-mix(in srgb, var(--info-cyan) 24%, transparent), transparent 30%),
    linear-gradient(135deg, var(--primary) 0%, var(--text-primary) 100%);
  border-right: 1px solid color-mix(in srgb, var(--text-white) 12%, transparent);
}

.login-info-panel::after {
  position: absolute;
  inset: 0;
  content: '';
  pointer-events: none;
  background-image:
    linear-gradient(color-mix(in srgb, var(--text-white) 12%, transparent) 1px, transparent 1px),
    linear-gradient(90deg, color-mix(in srgb, var(--text-white) 12%, transparent) 1px, transparent 1px);
  background-size: 42px 42px;
  opacity: 0.24;
}

.brand-lockup,
.ops-summary {
  position: relative;
  z-index: 1;
}

.brand-kicker {
  display: inline-flex;
  align-items: center;
  min-height: 26px;
  margin-bottom: var(--space-xl);
  padding: 4px 12px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--text-white) 12%, transparent);
  color: var(--text-white);
  font-family: var(--font-body);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0;
}

.brand-logo {
  display: block;
  width: min(280px, 76%);
  height: auto;
  max-height: 110px;
  object-fit: contain;
  object-position: left center;
}

.brand-lockup p {
  max-width: 520px;
  margin: var(--space-xl) 0 0;
  color: color-mix(in srgb, var(--text-white) 76%, transparent);
  font-size: 15px;
  line-height: 1.7;
}

.ops-summary {
  display: grid;
  gap: var(--space-md);
  max-width: 540px;
}

.summary-item {
  display: grid;
  grid-template-columns: 42px minmax(0, 1fr);
  gap: var(--space-md);
  align-items: center;
  border-radius: var(--radius-default);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}

.summary-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  border-radius: var(--radius-default);
  background: color-mix(in srgb, var(--text-white) 10%, transparent);
  color: var(--text-white);
  font-size: 15px;
  line-height: 1;
}

.summary-icon :deep(.pi) {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  line-height: 1;
}

.summary-icon :deep(.pi::before) {
  display: block;
  line-height: 1;
}

.summary-item strong,
.summary-item span {
  display: block;
}

.summary-item strong {
  color: var(--text-white);
  font-size: 13px;
  font-weight: 700;
}

.summary-item span {
  margin-top: 2px;
  color: color-mix(in srgb, var(--text-white) 70%, transparent);
  font-size: 12px;
  line-height: 1.45;
}

.login-form-panel {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: clamp(22px, 5vw, 56px);
  background: var(--page-bg);
}

.login-card {
  width: min(100%, 430px);
  padding: var(--space-3xl);
  box-shadow: var(--shadow-card-big);
}

.form-header {
  margin-bottom: var(--space-3xl);
  text-align: left;
}

.form-logo {
  display: block;
  width: 132px;
  height: 38px;
  margin-bottom: var(--space-md);
  object-fit: contain;
  object-position: left center;
}

.form-header h2 {
  margin: 0;
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 22px;
  font-weight: 700;
  line-height: 1.25;
}

.form-header p {
  margin: var(--space-sm) 0 0;
  color: var(--text-secondary);
  font-size: 13px;
  line-height: 1.55;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}

.error-wrapper {
  margin-bottom: var(--space-xs);
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.field-group label {
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 700;
}

.login-btn {
  justify-content: center;
  width: 100%;
  min-height: 42px;
  margin-top: var(--space-sm);
  border: none !important;
  border-radius: var(--radius-full) !important;
  background: var(--text-primary) !important;
  color: var(--text-white) !important;
  font-weight: 700 !important;
}

.login-btn:hover {
  background: color-mix(in srgb, var(--text-primary) 92%, var(--info-cyan)) !important;
}

.form-footer {
  margin-top: var(--space-3xl);
  text-align: center;
  color: var(--text-tertiary);
  font-size: 11px;
}

:deep(.p-inputtext),
:deep(.p-password-input) {
  width: 100%;
}

:deep(.p-inputtext) {
  min-height: 40px;
  border-color: var(--surface-border);
  border-radius: var(--radius-default);
  color: var(--text-primary);
  font-size: 13px;
}

:deep(.p-inputtext:enabled:focus) {
  border-color: var(--info-cyan);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--info-cyan) 18%, transparent);
}

:deep(.p-message) {
  margin: 0;
  border-radius: var(--radius-default);
}

@media (max-width: 992px) {
  .login-container {
    display: flex;
    min-height: 100dvh;
  }

  .login-info-panel {
    display: none;
  }

  .login-form-panel {
    width: 100%;
  }
}

@media (max-width: 560px) {
  .login-form-panel {
    align-items: stretch;
    padding: var(--space-lg);
  }

  .login-card {
    padding: var(--space-xl);
  }

  .form-header {
    margin-bottom: var(--space-2xl);
  }

  .form-header h2 {
    font-size: 20px;
  }
}
</style>
