<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { format } from 'date-fns'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import { getPublicBookingInvoices } from '../../api/receivable'

const route = useRoute()
const invoices = ref([])
const loading = ref(false)
const error = ref(null)

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)

const formatDate = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy')
}

const statusSeverity = (status) => {
  if (status === 'paid') return 'success'
  if (status === 'partial_paid') return 'info'
  if (status === 'void') return 'danger'
  return 'warn'
}

onMounted(async () => {
  loading.value = true
  try {
    const response = await getPublicBookingInvoices(route.params.token)
    invoices.value = response.data.data || []
  } catch (err) {
    error.value = err.response?.data?.message || 'Daftar invoice tidak ditemukan'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="public-invoice-list-page">
    <h1>Daftar Invoice</h1>

    <div v-if="loading" class="loading-strip">
      <ProgressBar mode="indeterminate" style="height: 4px" />
    </div>

    <div v-if="error" class="empty-state">
      <p>{{ error }}</p>
    </div>

    <div v-else-if="!invoices.length && !loading" class="empty-state">
      <p>Belum ada invoice untuk booking ini.</p>
    </div>

    <a v-for="invoice in invoices" :key="invoice.invoice_number" :href="invoice.public_path || '#'" class="invoice-card">
      <div class="invoice-card-head">
        <span class="invoice-number">{{ invoice.invoice_number }}</span>
        <Tag :value="invoice.status" :severity="statusSeverity(invoice.status)" />
      </div>
      <div class="invoice-amounts">
        <div><span>Total</span><strong>{{ formatCurrency(invoice.total_amount) }}</strong></div>
        <div><span>Sudah Bayar</span><strong class="positive">{{ formatCurrency(invoice.paid_amount) }}</strong></div>
        <div><span>Sisa</span><strong class="info">{{ formatCurrency(invoice.remaining_amount) }}</strong></div>
      </div>
      <div class="invoice-due">Jatuh tempo: {{ formatDate(invoice.due_date) }}</div>
    </a>
  </main>
</template>

<style scoped>
.public-invoice-list-page {
  max-width: 720px;
  margin: 0 auto;
  padding: 24px 16px 64px;
}

.public-invoice-list-page h1 {
  font-size: 18px;
  margin-bottom: 16px;
}

.loading-strip {
  margin-bottom: 16px;
}

.empty-state {
  text-align: center;
  padding: 48px 16px;
  color: #6b7280;
}

.invoice-card {
  display: block;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 14px;
  margin-bottom: 12px;
  text-decoration: none;
  color: inherit;
}

.invoice-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.invoice-number {
  font-weight: 700;
}

.invoice-amounts {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 13px;
  margin-bottom: 6px;
}

.invoice-amounts > div {
  display: flex;
  flex-direction: column;
}

.invoice-amounts span {
  color: #6b7280;
  font-size: 11px;
}

.invoice-amounts .positive {
  color: #16a34a;
}

.invoice-amounts .info {
  color: #2563eb;
}

.invoice-due {
  font-size: 12px;
  color: #6b7280;
}
</style>
