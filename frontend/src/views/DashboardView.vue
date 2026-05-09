<script setup>
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'

const authStore = useAuthStore()
const router = useRouter()

const handleLogout = async () => {
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="p-8">
    <div class="flex justify-between items-center mb-8">
      <div>
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <p class="text-gray-600">Selamat datang, {{ authStore.user?.name }} ({{ authStore.user?.role }})</p>
        <p class="text-sm text-gray-400">Cabang: {{ authStore.branch?.name || 'Global' }}</p>
      </div>
      <Button label="Logout" icon="pi pi-sign-out" severity="danger" @click="handleLogout" />
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <h3 class="text-gray-500 text-sm font-medium uppercase mb-2">Status Koneksi</h3>
        <p class="text-green-500 font-bold flex items-center gap-2">
          <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
          Terhubung ke Backend
        </p>
      </div>
    </div>
  </div>
</template>
