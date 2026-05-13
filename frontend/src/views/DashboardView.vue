<script setup>
import { useAuthStore } from '../stores/auth'
import Button from 'primevue/button'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()
</script>

<template>
  <div class="dashboard-container">
    <div class="welcome-section flex justify-between items-center">
      <div>
        <h2>Selamat Datang, {{ authStore.user?.name }}</h2>
        <p class="subtitle">Anda masuk sebagai <span class="role-badge">{{ authStore.user?.role }}</span></p>
      </div>
      <Button label="Buat Booking" icon="pi pi-plus" @click="router.push({ name: 'BookingCreate' })" class="p-button-lg" />
    </div>
    
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">
          <i class="pi pi-check-circle"></i>
        </div>
        <div class="stat-info">
          <h3>Status Koneksi</h3>
          <p class="status-online">Terhubung ke Backend</p>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon info">
          <i class="pi pi-building"></i>
        </div>
        <div class="stat-info">
          <h3>Cabang Aktif</h3>
          <p>{{ authStore.branch?.name || 'Global' }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dashboard-container {
  display: flex;
  flex-direction: column;
  gap: 30px;
}

.welcome-section h2 {
  font-size: 1.8rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.subtitle {
  color: #64748b;
  margin-top: 5px;
}

.role-badge {
  background-color: rgba(6, 182, 212, 0.1);
  color: #06b6d4;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: capitalize;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}

.stat-card {
  background-color: #ffffff;
  padding: 24px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  gap: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.stat-icon {
  width: 48px;
  height: 48px;
  background-color: rgba(34, 197, 94, 0.1);
  color: #22c55e;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  font-size: 1.5rem;
}

.stat-icon.info {
  background-color: rgba(6, 182, 212, 0.1);
  color: #06b6d4;
}

.stat-info h3 {
  font-size: 0.9rem;
  font-weight: 600;
  color: #64748b;
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-info p {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1e293b;
  margin: 4px 0 0 0;
}

.status-online {
  color: #22c55e !important;
}
</style>
