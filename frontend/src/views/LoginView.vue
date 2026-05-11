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
  <div class="flex items-center justify-center min-h-screen bg-gray-100 dark:bg-gray-900 px-4">
    <Card class="w-full max-w-md shadow-xl border-t-4 border-blue-600">
      <template #title>
        <div class="text-center mb-4">
          <h1 class="text-3xl font-bold text-gray-800 dark:text-white">DRENT</h1>
          <p class="text-gray-500 text-sm mt-1">Car Rental Management System</p>
        </div>
      </template>
      
      <template #content>
        <form @submit.prevent="handleLogin" class="flex flex-col gap-4">
          <div v-if="errorMessage">
            <Message severity="error" :closable="false">{{ errorMessage }}</Message>
          </div>
          
          <div class="flex flex-col gap-2">
            <label for="email" class="font-medium text-gray-700 dark:text-gray-300">Email Address</label>
            <InputText id="email" v-model="email" type="email" placeholder="admin@drent.id" required />
          </div>
          
          <div class="flex flex-col gap-2">
            <label for="password" class="font-medium text-gray-700 dark:text-gray-300">Password</label>
            <Password id="password" v-model="password" :feedback="false" toggleMask placeholder="••••••••" required class="w-full" inputClass="w-full" />
          </div>
          
          <Button type="submit" label="Sign In" icon="pi pi-sign-in" :loading="loading" class="mt-4 w-full" />
        </form>
      </template>
      
      <template #footer>
        <div class="text-center text-xs text-gray-400 mt-2">
          &copy; 2026 DRENT Vibe. All rights reserved.
        </div>
      </template>
    </Card>
  </div>
</template>

<style scoped>
:deep(.p-password-input) {
    width: 100%;
}
</style>
