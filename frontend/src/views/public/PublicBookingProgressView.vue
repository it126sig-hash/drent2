<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { format } from 'date-fns'
import ProgressBar from 'primevue/progressbar'
import { getPublicBookingProgress } from '../../api/receivable'
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue'

const route = useRoute()
const booking = ref(null)
const loading = ref(false)
const error = ref(null)

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)

const formatDate = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy')
}

onMounted(async () => {
  loading.value = true
  try {
    const response = await getPublicBookingProgress(route.params.token)
    booking.value = response.data.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Booking tidak ditemukan'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="public-booking-page">
    <div v-if="loading" class="loading-strip">
      <ProgressBar mode="indeterminate" style="height: 4px" />
    </div>

    <div v-if="error" class="empty-state">
      <h1>Booking tidak tersedia</h1>
      <p>{{ error }}</p>
    </div>

    <template v-else-if="booking">
      <header class="booking-header">
        <div class="booking-code">{{ booking.kode_booking }}</div>
        <BookingStatusBadge :status="booking.status" />
      </header>

      <section class="customer-card">
        <div class="customer-name">{{ booking.customer_name || 'Pelanggan' }}</div>
        <div class="customer-line" v-if="booking.customer_phone">Telp: {{ booking.customer_phone }}</div>
        <div class="customer-line" v-if="booking.customer_address">Alamat: {{ booking.customer_address }}</div>
      </section>

      <section class="timeline-section">
        <h2>Riwayat Transaksi</h2>
        <div v-for="(detail, index) in booking.timeline" :key="index" class="timeline-entry">
          <div class="timeline-dot"></div>
          <div class="timeline-content">
            <div class="timeline-head">
              <BookingStatusBadge :status="detail.detail_type" />
              <span class="vehicle-name">{{ detail.vehicle_name || '-' }}</span>
            </div>
            <div class="timeline-dates">{{ formatDate(detail.rental_start_date) }} s/d {{ formatDate(detail.rental_end_date) }}</div>
            <ul v-if="detail.costs?.length" class="cost-list">
              <li v-for="(cost, costIndex) in detail.costs" :key="costIndex" :class="{ discount: cost.type === 'diskon' }">
                <span>{{ cost.label }}<span v-if="cost.is_additional"> (Tambahan)</span></span>
                <span>{{ cost.type === 'diskon' ? '-' : '' }}{{ formatCurrency(cost.amount) }}</span>
              </li>
            </ul>
          </div>
        </div>
      </section>

      <footer class="page-footer">
        <RouterLink :to="`/booking/${route.params.token}/invoices`" class="invoice-link">
          Lihat Daftar Invoice <i class="pi pi-arrow-right"></i>
        </RouterLink>
      </footer>
    </template>
  </main>
</template>

<style scoped>
.public-booking-page {
  max-width: 720px;
  margin: 0 auto;
  padding: 24px 16px 64px;
  font-family: inherit;
}

.loading-strip {
  margin-bottom: 16px;
}

.empty-state {
  text-align: center;
  padding: 64px 16px;
}

.booking-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.booking-code {
  font-size: 20px;
  font-weight: 800;
}

.customer-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 14px;
  margin-bottom: 24px;
}

.customer-name {
  font-weight: 700;
  margin-bottom: 4px;
}

.customer-line {
  font-size: 13px;
  color: #6b7280;
}

.timeline-section h2 {
  font-size: 16px;
  margin-bottom: 12px;
}

.timeline-entry {
  display: flex;
  gap: 12px;
  margin-bottom: 18px;
}

.timeline-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #3b82f6;
  margin-top: 6px;
  flex-shrink: 0;
}

.timeline-content {
  flex: 1;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
}

.timeline-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.vehicle-name {
  font-weight: 700;
}

.timeline-dates {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 8px;
}

.cost-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
}

.cost-list li {
  display: flex;
  justify-content: space-between;
}

.cost-list li.discount {
  color: #16a34a;
}

.page-footer {
  margin-top: 24px;
  text-align: center;
}

.invoice-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 700;
  color: #2563eb;
  text-decoration: none;
}
</style>
