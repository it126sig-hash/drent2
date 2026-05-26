<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DatePicker from 'primevue/datepicker'
import ProgressBar from 'primevue/progressbar'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import { useAuthStore } from '../stores/auth'
import { useDashboard } from '../composables/useDashboard'

const authStore = useAuthStore()
const router = useRouter()
const { dashboard, loading, fetchDashboard } = useDashboard()

const monthStart = () => {
  const date = new Date()
  return new Date(date.getFullYear(), date.getMonth(), 1)
}

const monthEnd = () => {
  const date = new Date()
  return new Date(date.getFullYear(), date.getMonth() + 1, 0)
}

const filters = ref({
  date_from: monthStart(),
  date_to: monthEnd(),
})

const kpis = computed(() => dashboard.value?.kpis || [])
const bookingToday = computed(() => dashboard.value?.booking_today || [])
const armadaStatus = computed(() => dashboard.value?.armada_status || [])
const finance = computed(() => dashboard.value?.finance_snapshot || {})
const alerts = computed(() => dashboard.value?.alerts || [])
const leaderboards = computed(() => dashboard.value?.repeat_order_leaderboards || [])
const activeLeaderboardStatus = ref('Normal')
const activeLeaderboard = computed(() => {
  return leaderboards.value.find((board) => board.status === activeLeaderboardStatus.value)
    || leaderboards.value[0]
    || { status: 'Normal', label: 'Normal', items: [] }
})

const fetchData = () => fetchDashboard(filters.value)

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(amount || 0)
}

const formatDateTime = (value) => {
  if (!value) return '-'
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

const statusLabel = (status) => {
  const map = {
    follow_up: 'Follow Up',
    confirm: 'Confirm',
    waiting_list: 'Waiting List',
    rental_unit: 'Rental Unit',
    selesai: 'Selesai',
    batal: 'Batal',
  }
  return map[status] || status || '-'
}

const statusSeverity = (status) => {
  const map = {
    follow_up: 'info',
    confirm: 'help',
    waiting_list: 'warn',
    rental_unit: 'success',
    selesai: 'secondary',
    batal: 'danger',
    Normal: 'success',
    Member: 'info',
    Corporate: 'help',
    'Rent to Rent': 'secondary',
  }
  return map[status] || 'secondary'
}

const toneClass = (tone) => `tone-${tone || 'neutral'}`

const leaderboardOrderCount = (board) => {
  return (board?.items || []).reduce((total, item) => total + (Number(item.total_bookings) || 0), 0)
}

const goTo = (path) => {
  if (path) router.push(path)
}

onMounted(fetchData)
</script>

<template>
  <div class="dashboard-page">
    <header class="dashboard-header">
      <div>
        <h1 class="text-h1">Ringkasan Operasional</h1>
        <p class="text-secondary text-sm">
          Cabang: <span class="font-semibold text-primary">{{ authStore.branch?.name || 'Pusat' }}</span>
          <span class="separator">-</span>
          {{ authStore.user?.name }} ({{ authStore.user?.role }})
        </p>
      </div>

      <div class="header-actions">
        <DatePicker v-model="filters.date_from" dateFormat="dd M yy" showIcon class="dashboard-date" placeholder="Dari" />
        <DatePicker v-model="filters.date_to" dateFormat="dd M yy" showIcon class="dashboard-date" placeholder="Sampai" />
        <button class="btn-pill btn-secondary" :disabled="loading" @click="fetchData">
          <i class="pi pi-refresh text-[10px]" :class="{ 'pi-spin': loading }"></i>
          <span>Refresh</span>
        </button>
        <button class="btn-pill btn-primary" @click="router.push({ name: 'BookingCreate' })">
          <i class="pi pi-plus text-[10px]"></i>
          <span>Buat Booking</span>
        </button>
      </div>
    </header>

    <ProgressBar v-if="loading" mode="indeterminate" class="dashboard-loader" />

    <section class="kpi-grid">
      <template v-if="loading && !dashboard">
        <div v-for="index in 5" :key="`skeleton-${index}`" class="kpi-tile">
          <Skeleton width="36px" height="36px" />
          <Skeleton width="70%" height="14px" class="mt-4" />
          <Skeleton width="45%" height="24px" class="mt-2" />
        </div>
      </template>

      <template v-else>
        <div v-for="stat in kpis" :key="stat.key" class="kpi-tile" :class="toneClass(stat.tone)" @click="goTo(stat.route)">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i :class="stat.icon"></i>
            </div>
            <span class="kpi-delta">{{ stat.delta }}</span>
          </div>
          <div class="kpi-body">
            <p>{{ stat.label }}</p>
            <strong>{{ stat.display_value }}</strong>
            <small v-if="stat.sub_value" class="kpi-sub-value">{{ stat.sub_value }}</small>
          </div>
        </div>
      </template>
    </section>

    <div class="dashboard-grid">
      <section class="app-card dashboard-panel main-panel">
        <div class="panel-header">
          <div>
            <h2>Booking Bulan Ini</h2>
            <span>{{ dashboard?.period?.label || 'Bulan berjalan' }}</span>
          </div>
          <router-link to="/bookings" class="panel-link">Lihat Semua</router-link>
        </div>

        <div v-if="bookingToday.length" class="booking-list">
          <article v-for="booking in bookingToday" :key="booking.id" class="booking-row">
            <div class="booking-icon">
              <i class="pi pi-car"></i>
            </div>
            <div class="booking-main">
              <div class="booking-title">
                <strong>{{ booking.customer_name || '-' }}</strong>
                <Tag :value="statusLabel(booking.status)" :severity="statusSeverity(booking.status)" />
                <Tag v-if="booking.is_late" value="Terlambat" severity="danger" />
              </div>
              <p>{{ booking.kode_booking }} - {{ booking.unit_label || '-' }}</p>
              <span>{{ formatDateTime(booking.tgl_sewa) }} sampai {{ formatDateTime(booking.tgl_kembali) }}</span>
            </div>
            <strong class="booking-amount">{{ formatCurrency(booking.amount) }}</strong>
          </article>
        </div>

        <div v-else class="empty-state">
          <i class="pi pi-calendar"></i>
          <span>Tidak ada booking hari ini.</span>
        </div>
      </section>

      <aside class="side-stack">
        <section class="app-card dashboard-panel">
          <div class="panel-header compact">
            <h2>Status Armada</h2>
          </div>
          <div class="armada-list">
            <div v-for="item in armadaStatus" :key="item.key" class="armada-row">
              <div>
                <span class="tone-dot" :class="toneClass(item.tone)"></span>
                <strong>{{ item.label }}</strong>
              </div>
              <span>{{ item.value }} unit</span>
              <div class="armada-track">
                <div :class="['armada-fill', toneClass(item.tone)]" :style="{ width: `${item.percentage}%` }"></div>
              </div>
            </div>
          </div>
        </section>

        <section class="app-card dashboard-panel">
          <div class="panel-header compact">
            <h2>Finance Snapshot</h2>
          </div>
          <div class="finance-grid">
            <div>
              <span>Tagihan belum lunas</span>
              <strong>{{ formatCurrency(finance.outstanding_amount) }}</strong>
              <small>{{ finance.outstanding_count || 0 }} transaksi</small>
            </div>
            <div>
              <span>Rent to Rent terbuka</span>
              <strong>{{ formatCurrency(finance.open_rent_to_rent_bill_amount) }}</strong>
              <small>{{ finance.open_rent_to_rent_bill_count || 0 }} tagihan</small>
            </div>
          </div>
          <div class="payment-list">
            <article v-for="payment in finance.latest_payments || []" :key="payment.id">
              <div>
                <strong>{{ payment.customer_name || '-' }}</strong>
                <span>{{ payment.booking_code || '-' }} - {{ payment.payment_account || '-' }}</span>
              </div>
              <b>{{ formatCurrency(payment.amount) }}</b>
            </article>
            <div v-if="!(finance.latest_payments || []).length" class="mini-empty">Belum ada pembayaran periode ini.</div>
          </div>
        </section>
      </aside>
    </div>

    <section class="alert-grid">
      <button v-for="alert in alerts" :key="alert.key" type="button" class="alert-tile" :class="toneClass(alert.tone)" @click="goTo(alert.route)">
        <span>{{ alert.label }}</span>
        <strong>{{ alert.value }}</strong>
      </button>
    </section>

    <section class="leaderboard-section">
      <div class="section-heading">
        <div>
          <h2>Top Konsumen Repeat Order</h2>
          <p>Ranking berdasarkan berapa kali order selesai pada periode terpilih.</p>
        </div>
      </div>

      <section class="app-card leaderboard-card">
        <div class="leaderboard-header">
          <div class="leaderboard-tabs">
            <button v-for="board in leaderboards" :key="board.status" type="button" class="leaderboard-tab" :class="{ active: activeLeaderboard.status === board.status }" @click="activeLeaderboardStatus = board.status">
              <span>{{ board.label }}</span>
              <b>{{ leaderboardOrderCount(board) }}x</b>
            </button>
          </div>
          <span>Top 5</span>
        </div>

        <div v-if="activeLeaderboard.items.length" class="leaderboard-list">
          <article v-for="(item, index) in activeLeaderboard.items" :key="`${activeLeaderboard.status}-${item.source}-${item.id}`" class="leaderboard-row">
            <div class="rank">{{ index + 1 }}</div>
            <div class="leaderboard-main">
              <strong>{{ item.name }}</strong>
              <span>{{ item.contact || '-' }}</span>
              <small>{{ item.latest_booking_code || '-' }} - {{ formatDateTime(item.latest_booking_at) }}</small>
            </div>
            <div class="leaderboard-metric">
              <strong>{{ item.total_bookings }}x</strong>
              <span>order selesai</span>
            </div>
          </article>
        </div>

        <div v-else class="mini-empty">Belum ada repeat order selesai.</div>
      </section>
    </section>
  </div>
</template>

<style scoped>
.dashboard-page {
  max-width: 1480px;
  margin: 0 auto;
  padding: var(--space-xl);
}

.dashboard-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.separator {
  margin: 0 8px;
  color: var(--surface-border);
}

.header-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  flex-wrap: wrap;
}

.dashboard-date {
  width: 150px;
}

.dashboard-loader {
  height: 3px;
  margin-bottom: 14px;
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}

.kpi-tile,
.app-card,
.alert-tile {
  background: var(--surface-default);
  border: 1px solid var(--surface-border);
  border-radius: 10px;
  box-shadow: var(--shadow-tile);
}

.kpi-tile {
  padding: 16px;
  cursor: pointer;
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
}

.kpi-tile:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(26, 29, 46, 0.08);
  border-color: var(--primary);
}

.kpi-sub-value {
  display: block;
  margin-top: 4px;
  font-size: 11px;
  font-weight: 500;
  color: var(--text-secondary);
  opacity: 0.85;
}

.kpi-header,
.panel-header,
.booking-title,
.armada-row>div,
.payment-list article,
.leaderboard-header,
.leaderboard-row {
  display: flex;
  align-items: center;
}

.kpi-header,
.panel-header,
.payment-list article,
.leaderboard-header,
.leaderboard-row {
  justify-content: space-between;
}

.kpi-icon {
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  border-radius: 8px;
  background: var(--card-bg);
  color: var(--text-secondary);
}

.kpi-delta {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-secondary);
  text-align: right;
}

.kpi-body {
  margin-top: 14px;
}

.kpi-body p,
.panel-header span,
.leaderboard-header span,
.finance-grid span,
.payment-list span,
.leaderboard-main span,
.leaderboard-main small,
.leaderboard-metric span,
.booking-main p,
.booking-main span {
  color: var(--text-secondary);
}

.kpi-body p {
  font-size: 12px;
  font-weight: 600;
}

.kpi-body strong {
  display: block;
  margin-top: 3px;
  font-family: var(--font-headline);
  font-size: 21px;
  color: var(--text-primary);
}

.dashboard-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(320px, 0.8fr);
  gap: 18px;
  align-items: start;
}

.side-stack {
  display: grid;
  gap: 18px;
}

.dashboard-panel {
  overflow: hidden;
}

.panel-header {
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--surface-border);
}

.panel-header.compact {
  justify-content: flex-start;
}

.panel-header h2,
.section-heading h2 {
  margin: 0;
  font-family: var(--font-headline);
  font-size: 16px;
  font-weight: 800;
  color: var(--text-primary);
}

.panel-header span,
.panel-link,
.leaderboard-header span {
  font-size: 12px;
}

.panel-link {
  color: var(--text-secondary);
  text-decoration: none;
  font-weight: 700;
}

.panel-link:hover {
  color: var(--text-primary);
}

.booking-list,
.leaderboard-list,
.payment-list,
.armada-list {
  display: grid;
}

.booking-list,
.leaderboard-list {
  gap: 10px;
  padding: 14px;
}

.booking-row {
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 12px;
  border: 1px solid var(--surface-border);
  border-radius: 8px;
  background: var(--card-bg);
}

.booking-icon {
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  border-radius: 8px;
  background: var(--surface-default);
  color: var(--text-secondary);
}

.booking-title {
  justify-content: flex-start;
  flex-wrap: wrap;
  gap: 7px;
}

.booking-title strong,
.leaderboard-main strong,
.payment-list strong,
.finance-grid strong {
  color: var(--text-primary);
}

.booking-main p,
.booking-main span {
  margin: 2px 0 0;
  font-size: 12px;
}

.booking-amount {
  font-family: var(--font-mono);
  font-size: 13px;
  color: var(--text-primary);
  white-space: nowrap;
}

.empty-state,
.mini-empty {
  display: grid;
  place-items: center;
  gap: 8px;
  padding: 28px 16px;
  color: var(--text-secondary);
  font-size: 13px;
}

.mini-empty {
  padding: 16px;
  border-top: 1px solid var(--surface-border);
}

.armada-list {
  gap: 12px;
  padding: 16px;
}

.armada-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px;
  font-size: 13px;
}

.armada-row>div {
  justify-content: flex-start;
  gap: 8px;
}

.armada-track {
  grid-column: 1 / -1;
  height: 6px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--card-bg);
}

.armada-fill {
  height: 100%;
  border-radius: inherit;
}

.finance-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  padding: 16px;
}

.finance-grid>div {
  display: grid;
  gap: 4px;
  padding: 12px;
  border: 1px solid var(--surface-border);
  border-radius: 8px;
  background: var(--card-bg);
}

.finance-grid strong {
  font-family: var(--font-mono);
  font-size: 14px;
}

.finance-grid small {
  color: var(--text-secondary);
}

.payment-list {
  padding: 0 16px 16px;
}

.payment-list article {
  gap: 10px;
  padding: 10px 0;
  border-top: 1px solid var(--surface-border);
}

.payment-list article>div {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.payment-list b {
  color: var(--positive);
  font-family: var(--font-mono);
  white-space: nowrap;
}

.alert-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-top: 18px;
}

.alert-tile {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 74px;
  padding: 14px 16px;
  color: var(--text-primary);
  cursor: pointer;
  text-align: left;
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
}

.alert-tile:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(26, 29, 46, 0.08);
  border-color: var(--primary);
}

.alert-tile span {
  font-size: 13px;
  font-weight: 700;
}

.alert-tile strong {
  font-size: 24px;
  font-family: var(--font-headline);
}

.leaderboard-section {
  margin-top: 22px;
}

.section-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 12px;
}

.section-heading p {
  margin: 4px 0 0;
  color: var(--text-secondary);
  font-size: 13px;
}

.leaderboard-card {
  overflow: hidden;
}

.leaderboard-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 13px 14px;
  border-bottom: 1px solid var(--surface-border);
}

.leaderboard-tabs {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: thin;
}

.leaderboard-tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 34px;
  padding: 7px 10px;
  border: 1px solid var(--surface-border);
  border-radius: 8px;
  background: var(--card-bg);
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 800;
  white-space: nowrap;
  cursor: pointer;
}

.leaderboard-tab b {
  color: var(--text-primary);
  font-family: var(--font-mono);
}

.leaderboard-tab.active {
  border-color: var(--primary);
  background: var(--surface-default);
  color: var(--text-primary);
  box-shadow: inset 0 0 0 1px var(--primary);
}

.leaderboard-row {
  gap: 12px;
  padding: 10px;
  border: 1px solid var(--surface-border);
  border-radius: 8px;
  background: var(--card-bg);
}

.rank {
  width: 30px;
  height: 30px;
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  border-radius: 8px;
  background: var(--surface-default);
  color: var(--text-primary);
  font-weight: 800;
}

.leaderboard-main {
  display: grid;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.leaderboard-main strong,
.leaderboard-main span,
.leaderboard-main small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leaderboard-metric {
  display: grid;
  gap: 2px;
  text-align: right;
}

.leaderboard-metric strong {
  color: var(--text-primary);
  font-size: 16px;
}

.tone-dot {
  width: 9px;
  height: 9px;
  border-radius: 999px;
}

.tone-positive {
  color: var(--positive);
}

.tone-info {
  color: var(--info-cyan);
}

.tone-warning {
  color: var(--warning);
}

.tone-negative {
  color: var(--negative);
}

.tone-neutral {
  color: var(--text-secondary);
}

.tone-dot.tone-positive,
.armada-fill.tone-positive {
  background: var(--positive);
}

.tone-dot.tone-info,
.armada-fill.tone-info {
  background: var(--info-cyan);
}

.tone-dot.tone-warning,
.armada-fill.tone-warning {
  background: var(--warning);
}

.tone-dot.tone-negative,
.armada-fill.tone-negative {
  background: var(--negative);
}

.tone-dot.tone-neutral,
.armada-fill.tone-neutral {
  background: var(--text-secondary);
}

.kpi-tile.tone-positive .kpi-delta,
.alert-tile.tone-positive strong {
  color: var(--positive);
}

.kpi-tile.tone-info .kpi-delta,
.alert-tile.tone-info strong {
  color: var(--info-cyan);
}

.kpi-tile.tone-warning .kpi-delta,
.alert-tile.tone-warning strong {
  color: var(--warning);
}

.kpi-tile.tone-negative .kpi-delta,
.alert-tile.tone-negative strong {
  color: var(--negative);
}

@media (max-width: 1180px) {
  .kpi-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .dashboard-page {
    padding: var(--space-lg);
  }

  .dashboard-header,
  .section-heading {
    align-items: stretch;
    flex-direction: column;
  }

  .header-actions,
  .header-actions .btn-pill,
  .dashboard-date {
    width: 100%;
  }

  .kpi-grid,
  .alert-grid,
  .finance-grid {
    grid-template-columns: 1fr;
  }

  .booking-row {
    grid-template-columns: 36px minmax(0, 1fr);
  }

  .booking-amount {
    grid-column: 2;
    justify-self: start;
  }

  .leaderboard-row {
    align-items: flex-start;
  }

  .leaderboard-header {
    align-items: stretch;
    flex-direction: column;
  }

  .leaderboard-metric {
    min-width: 86px;
  }
}
</style>
