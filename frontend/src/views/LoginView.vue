<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Card from 'primevue/card'
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
    <!-- Left Panel (Decorative) -->
    <div class="login-decor">
      <div class="decor-content">
        <h1 class="decor-logo">DRENT</h1>
        <p class="decor-tagline">Professional Car Rental Management System</p>
        
        <ul class="decor-features">
          <li>
            <i class="pi pi-check-circle"></i>
            <span>Real-time Fleet Management</span>
          </li>
          <li>
            <i class="pi pi-check-circle"></i>
            <span>Integrated Financial Reporting</span>
          </li>
          <li>
            <i class="pi pi-check-circle"></i>
            <span>Secure Booking & Operations</span>
          </li>
        </ul>
      </div>
      <div class="decor-pattern"></div>
    </div>

    <!-- Right Panel (Login Form) -->
    <div class="login-form-panel">
      <div class="form-card-wrapper">
        <div class="form-header">
          <span class="app-name-small">DRENT</span>
          <h2>Selamat Datang</h2>
          <p>Silakan masuk ke akun Anda</p>
        </div>

        <form @submit.prevent="handleLogin" class="login-form">
          <div v-if="errorMessage" class="error-wrapper">
            <Message severity="error" :closable="false">{{ errorMessage }}</Message>
          </div>
          
          <div class="field-group">
            <label for="email">Email Address</label>
            <InputText id="email" v-model="email" type="email" placeholder="admin@drent.id" required class="w-full" />
          </div>
          
          <div class="field-group">
            <label for="password">Password</label>
            <Password id="password" v-model="password" :feedback="false" toggleMask placeholder="••••••••" required class="w-full" inputClass="w-full" />
          </div>
          
          <Button type="submit" label="Sign In" icon="pi pi-sign-in" :loading="loading" class="login-btn" />
        </form>

        <div class="form-footer">
          &copy; 2026 DRENT Vibe. All rights reserved.
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
:root {
  --navy: #0f172a;
  --tosca: #06b6d4;
  --tosca-dark: #0891b2;
}

.login-container {
  display: flex;
  min-height: 100vh;
  width: 100%;
  background-color: #ffffff;
}

/* Left Decorative Panel */
.login-decor {
  flex: 1.5;
  background: linear-gradient(135deg, #0f172a 0%, #06b6d4 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  color: white;
  padding: 40px;
}

.decor-content {
  position: relative;
  z-index: 10;
  max-width: 480px;
}

.decor-logo {
  font-size: 5rem;
  font-weight: 800;
  margin: 0;
  letter-spacing: -2px;
  line-height: 1;
}

.decor-tagline {
  font-size: 1.25rem;
  opacity: 0.9;
  margin-top: 10px;
  font-weight: 300;
}

.decor-features {
  list-style: none;
  padding: 0;
  margin-top: 50px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.decor-features li {
  display: flex;
  align-items: center;
  gap: 15px;
  font-size: 1.1rem;
}

.decor-features i {
  color: #22d3ee;
  font-size: 1.2rem;
}

.decor-pattern {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,0.05) 1px, transparent 0);
  background-size: 40px 40px;
}

/* Right Form Panel */
.login-form-panel {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
  background-color: #f8fafc;
}

.form-card-wrapper {
  width: 100%;
  max-width: 400px;
}

.form-header {
  margin-bottom: 40px;
  text-align: center;
}

.app-name-small {
  color: #06b6d4;
  font-weight: 700;
  font-size: 1.2rem;
  letter-spacing: 2px;
  display: block;
  margin-bottom: 10px;
}

.form-header h2 {
  font-size: 1.8rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.form-header p {
  color: #64748b;
  margin-top: 8px;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.error-wrapper {
  margin-bottom: 8px;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.field-group label {
  font-weight: 600;
  color: #334155;
  font-size: 0.9rem;
}

.login-btn {
  background-color: #06b6d4 !important;
  border-color: #06b6d4 !important;
  padding: 12px !important;
  font-weight: 600 !important;
  margin-top: 10px;
}

.login-btn:hover {
  background-color: #0891b2 !important;
  border-color: #0891b2 !important;
}

.form-footer {
  margin-top: 40px;
  text-align: center;
  font-size: 0.8rem;
  color: #94a3b8;
}

/* Mobile Responsiveness */
@media (max-width: 992px) {
  .login-decor {
    display: none;
  }
  
  .login-form-panel {
    background-color: #ffffff;
  }
}

:deep(.p-password-input) {
  width: 100%;
}
</style>
