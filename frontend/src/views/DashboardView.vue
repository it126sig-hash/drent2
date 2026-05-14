<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

const authStore = useAuthStore()
const router = useRouter()

// === Dummy Data ===
const kpiStats = ref([
  { label: 'Unit Tersedia', value: '18 / 32', icon: 'pi pi-car', delta: '+2 hari ini', deltaColor: 'var(--positive)' },
  { label: 'Booking Aktif', value: '12', icon: 'pi pi-calendar-check', delta: 'Sesuai target', deltaColor: 'var(--info-cyan)' },
  { label: 'Pendapatan Hari Ini', value: 'Rp 8.500.000', icon: 'pi pi-wallet', delta: '+15%', deltaColor: 'var(--positive)', isMono: true },
  { label: 'Piutang Berjalan', value: 'Rp 2.450.000', icon: 'pi pi-exclamation-circle', delta: '3 booking', deltaColor: 'var(--warning)', isMono: true }
])

const recentBookings = ref([
  { id: 'BK-1024', customer: 'Budi Santoso', unit: 'Toyota Avanza (B 1234 ABC)', status: 'active', amount: 'Rp 450.000', date: '14 Mei, 09:00' },
  { id: 'BK-1025', customer: 'Siti Aminah', unit: 'Honda Brio (B 5678 DEF)', status: 'pending', amount: 'Rp 300.000', date: '14 Mei, 10:30' },
  { id: 'BK-1026', customer: 'Andi Wijaya', unit: 'Mitsubishi Xpander (B 9012 GHI)', status: 'completed', amount: 'Rp 600.000', date: '13 Mei, 15:00' },
  { id: 'BK-1027', customer: 'Rina Kartika', unit: 'Toyota Innova (B 3456 JKL)', status: 'active', amount: 'Rp 850.000', date: '14 Mei, 08:00' }
])

const recentTransactions = ref([
  { type: 'Penerimaan', merchant: 'Booking #BK-1024 - Budi Santoso', amount: '+ Rp 450.000', date: '10:15', direction: 'in' },
  { type: 'Pengeluaran', merchant: 'Biaya Cuci Mobil - Avanza B 1234 ABC', amount: '- Rp 50.000', date: '09:30', direction: 'out' },
  { type: 'Penerimaan', merchant: 'Booking #BK-1026 - Andi Wijaya', amount: '+ Rp 600.000', date: 'Kemarin', direction: 'in' },
  { type: 'Pengeluaran', merchant: 'Bensin Refill - Xpander B 9012 GHI', amount: '- Rp 200.000', date: 'Kemarin', direction: 'out' }
])

const armadaStats = ref([
  { label: 'Tersedia', value: 18, color: 'var(--positive)', percentage: 56 },
  { label: 'Disewa', value: 12, color: 'var(--warning)', percentage: 38 },
  { label: 'Maintenance', value: 2, color: 'var(--negative)', percentage: 6 }
])

const getStatusSeverity = (status) => {
  switch (status) {
    case 'active': return 'success'
    case 'pending': return 'warn'
    case 'completed': return 'info'
    default: return 'secondary'
  }
}
</script>

<template>
  <div class="dashboard-page">
    <!-- Header Section -->
    <header class="dashboard-header flex justify-between items-center mb-6">
      <div>
        <h1 class="text-h1">Ringkasan Operasional</h1>
        <p class="text-secondary text-sm">
          Cabang: <span class="font-semibold text-primary">{{ authStore.branch?.name || 'Pusat' }}</span> • 
          {{ authStore.user?.name }} ({{ authStore.user?.role }})
        </p>
      </div>
      <button class="btn-pill btn-primary" @click="router.push({ name: 'BookingCreate' })">
        <i class="pi pi-plus text-[10px]"></i>
        <span>Buat Booking</span>
      </button>
    </header>

    <!-- KPI Grid -->
    <section class="kpi-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div v-for="stat in kpiStats" :key="stat.label" class="kpi-tile">
        <div class="kpi-header flex justify-between items-start">
          <div class="kpi-icon-box">
            <i :class="stat.icon"></i>
          </div>
          <span class="kpi-delta" :style="{ color: stat.deltaColor }">{{ stat.delta }}</span>
        </div>
        <div class="kpi-body mt-3">
          <p class="kpi-label">{{ stat.label }}</p>
          <h3 class="kpi-value" :class="{ 'font-mono-numeric': stat.isMono }">{{ stat.value }}</h3>
        </div>
      </div>
    </section>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column: Main Activities -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Upcoming Bookings -->
        <section class="app-card">
          <div class="app-section-header flex justify-between items-center px-4 py-3 border-b">
            <div class="flex items-center gap-2">
              <i class="pi pi-calendar text-secondary"></i>
              <h2 class="text-h2">Booking Hari Ini</h2>
            </div>
            <router-link to="/bookings" class="text-link text-xs">Lihat Semua</router-link>
          </div>
          <div class="p-4 space-y-3">
            <div v-for="booking in recentBookings" :key="booking.id" class="booking-item-row flex items-center justify-between p-3 border rounded-[10px] hover:bg-[var(--card-bg-hover)] transition-colors">
              <div class="flex items-center gap-3">
                <div class="unit-avatar bg-[var(--card-bg)] w-10 h-10 rounded-full flex items-center justify-center">
                  <i class="pi pi-car text-primary"></i>
                </div>
                <div>
                  <p class="text-sm font-semibold text-primary">{{ booking.customer }}</p>
                  <p class="text-[11px] text-secondary">{{ booking.unit }}</p>
                </div>
              </div>
              <div class="text-right flex flex-col items-end gap-1">
                <span class="text-[12px] font-mono-numeric font-semibold">{{ booking.amount }}</span>
                <Tag :value="booking.status" :severity="getStatusSeverity(booking.status)" class="text-[9px] uppercase tracking-wider" />
              </div>
            </div>
          </div>
        </section>

        <!-- Recent Transactions -->
        <section class="app-card">
          <div class="app-section-header flex justify-between items-center px-4 py-3 border-b">
            <div class="flex items-center gap-2">
              <i class="pi pi-history text-secondary"></i>
              <h2 class="text-h2">Transaksi Terakhir</h2>
            </div>
            <router-link to="/finance" class="text-link text-xs">Riwayat Keuangan</router-link>
          </div>
          <div class="p-4 space-y-2">
            <div v-for="(tx, idx) in recentTransactions" :key="idx" class="transaction-row flex items-center justify-between py-2 border-b last:border-0 border-[var(--surface-border)]">
              <div class="flex items-center gap-3">
                <div class="tx-icon w-8 h-8 rounded-full flex items-center justify-center text-[12px]" 
                     :class="tx.direction === 'in' ? 'bg-[#E6F6EC] text-[#147239]' : 'bg-[#FCEAE9] text-[#B02A24]'">
                  <i :class="tx.direction === 'in' ? 'pi pi-arrow-down-left' : 'pi pi-arrow-up-right'"></i>
                </div>
                <div>
                  <p class="text-sm font-medium text-primary">{{ tx.type }}</p>
                  <p class="text-[11px] text-secondary">{{ tx.merchant }}</p>
                </div>
              </div>
              <div class="text-right">
                <p class="text-sm font-mono-numeric font-semibold" :class="tx.direction === 'in' ? 'text-positive' : 'text-negative'">{{ tx.amount }}</p>
                <p class="text-[10px] text-secondary">{{ tx.date }}</p>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- Right Column: Stats & Shortcuts -->
      <div class="space-y-6">
        <!-- Armada Status -->
        <section class="app-card">
          <div class="app-section-header px-4 py-3 border-b">
            <h2 class="text-h2">Status Armada</h2>
          </div>
          <div class="p-4">
            <div class="flex justify-between mb-4">
              <div v-for="item in armadaStats" :key="item.label" class="text-center">
                <p class="text-[11px] text-secondary mb-1">{{ item.label }}</p>
                <p class="text-lg font-bold" :style="{ color: item.color }">{{ item.value }}</p>
              </div>
            </div>
            <div class="armada-progress-bar w-full h-2 bg-[var(--card-bg-hover)] rounded-full flex overflow-hidden">
              <div v-for="item in armadaStats" :key="item.label" 
                   :style="{ width: item.percentage + '%', backgroundColor: item.color }" 
                   class="h-full"></div>
            </div>
            <div class="mt-4 space-y-2">
              <div class="flex items-center justify-between text-[12px]">
                <span class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-[var(--positive)]"></span> Ready
                </span>
                <span class="font-semibold">56%</span>
              </div>
              <div class="flex items-center justify-between text-[12px]">
                <span class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-[var(--warning)]"></span> Sedang Jalan
                </span>
                <span class="font-semibold">38%</span>
              </div>
              <div class="flex items-center justify-between text-[12px]">
                <span class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-[var(--negative)]"></span> Maintenance
                </span>
                <span class="font-semibold">6%</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Quick Actions Card -->
        <section class="app-card bg-[var(--primary)] text-white overflow-hidden relative">
          <div class="p-5 relative z-10">
            <h3 class="font-headline text-sm font-semibold mb-2">Butuh Bantuan?</h3>
            <p class="text-[12px] opacity-80 mb-4">Butuh bantuan teknis atau ada kendala operasional? Hubungi IT Support atau Supervisor.</p>
            <button class="btn-pill btn-secondary w-full !bg-white !text-primary !border-none">
              <span>Buka Support Ticket</span>
            </button>
          </div>
          <!-- Decorative circle -->
          <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-white/10 rounded-full"></div>
        </section>

        <!-- Quick Info / Tasks -->
        <section class="app-card p-4">
          <div class="flex items-center gap-2 mb-3">
            <i class="pi pi-info-circle text-info-cyan"></i>
            <span class="text-[12px] font-semibold text-primary">Info Penting</span>
          </div>
          <ul class="text-[11px] text-secondary space-y-2">
            <li class="flex gap-2">
              <span class="text-warning">•</span>
              <span>Unit <b>B 1234 ABC</b> jatuh tempo pajak besok.</span>
            </li>
            <li class="flex gap-2">
              <span class="text-positive">•</span>
              <span>Supervisor menyetujui refund Booking #BK-1002.</span>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dashboard-page {
  padding: var(--space-xl);
  max-width: 1400px;
  margin: 0 auto;
}

/* KPI Tile Styles */
.kpi-tile {
  background: var(--surface-default);
  border: 1px solid var(--surface-border);
  padding: 16px;
  border-radius: 10px;
  box-shadow: var(--shadow-tile);
  transition: transform 0.2s, box-shadow 0.2s;
}

.kpi-tile:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(26, 29, 46, 0.08);
}

.kpi-icon-box {
  width: 32px;
  height: 32px;
  background: var(--card-bg);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
}

.kpi-delta {
  font-size: 11px;
  font-weight: 600;
}

.kpi-label {
  font-size: 12px;
  color: var(--text-secondary);
  font-weight: 500;
}

.kpi-value {
  font-family: var(--font-headline);
  font-size: 20px;
  font-weight: 700;
  color: var(--text-primary);
  margin-top: 2px;
}

/* App Card Styles */
.app-card {
  background: var(--surface-default);
  border: 1px solid var(--surface-border);
  border-radius: 10px;
  box-shadow: var(--shadow-tile);
}

.text-link {
  color: var(--text-secondary);
  text-decoration: none;
  transition: color 0.2s;
}

.text-link:hover {
  color: var(--text-primary);
  text-decoration: underline;
}

/* Unit Avatar in Row */
.unit-avatar i {
  font-size: 18px;
}

@media (max-width: 768px) {
  .dashboard-page {
    padding: var(--space-lg);
  }
  
  .dashboard-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  
  .dashboard-header button {
    width: 100%;
  }
}
</style>

