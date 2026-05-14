<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { format } from 'date-fns'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import receivableApi from '../../api/receivable'

const route = useRoute()
const invoice = ref(null)
const loading = ref(false)
const error = ref(null)
let refreshTimer = null

const remainingAmount = computed(() => invoice.value?.remaining_amount || 0)

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)
}

const formatDateTime = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy HH:mm')
}

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

const fetchInvoice = async (silent = false) => {
  if (!silent) loading.value = true
  error.value = null

  try {
    const response = await receivableApi.getPublicInvoice(route.params.token)
    invoice.value = response.data.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Invoice tidak ditemukan'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await fetchInvoice()
  refreshTimer = window.setInterval(() => fetchInvoice(true), 15000)
})

onBeforeUnmount(() => {
  if (refreshTimer) window.clearInterval(refreshTimer)
})
</script>

<template>
  <main class="public-invoice-page">
    <section class="invoice-shell">
      <div v-if="loading" class="loading-strip">
        <ProgressBar mode="indeterminate" style="height: 4px" />
      </div>

      <div v-if="error" class="empty-state">
        <h1>Invoice tidak tersedia</h1>
        <p>{{ error }}</p>
      </div>

      <template v-else-if="invoice">
        <header class="invoice-header">
          <div>
            <p class="eyebrow">{{ invoice.branch?.name || 'DRENT' }}</p>
            <h1>{{ invoice.invoice_number }}</h1>
          </div>
          <Tag :value="invoice.status" :severity="statusSeverity(invoice.status)" />
        </header>

        <section class="summary-grid">
          <div class="summary-block">
            <span>Total Invoice</span>
            <strong>{{ formatCurrency(invoice.total_amount) }}</strong>
          </div>
          <div class="summary-block">
            <span>Sudah Dibayar</span>
            <strong>{{ formatCurrency(invoice.paid_amount) }}</strong>
          </div>
          <div class="summary-block" :class="{ due: remainingAmount > 0, paid: remainingAmount === 0 }">
            <span>Sisa Tagihan</span>
            <strong>{{ formatCurrency(remainingAmount) }}</strong>
          </div>
          <div class="summary-block">
            <span>Jatuh Tempo</span>
            <strong>{{ formatDate(invoice.due_date) }}</strong>
          </div>
        </section>

        <section class="content-grid">
          <div class="panel">
            <div class="panel-heading">
              <h2>Detail Invoice</h2>
              <span>{{ formatDateTime(invoice.generated_at) }}</span>
            </div>
            <div class="line-item" v-for="booking in invoice.bookings" :key="booking.kode_booking">
              <div>
                <strong>{{ booking.kode_booking }}</strong>
                <span>{{ booking.customer_name || '-' }}</span>
              </div>
              <strong>{{ formatCurrency(booking.amount) }}</strong>
            </div>
          </div>

          <div class="panel">
            <div class="panel-heading">
              <h2>Rekening Pembayaran</h2>
            </div>
            <div class="account-list">
              <div class="account-row" v-for="account in invoice.payment_accounts" :key="`${account.nama_bank}-${account.nomor_rekening}`">
                <div>
                  <strong>{{ account.nama_bank }}</strong>
                  <span>{{ account.atas_nama }}</span>
                </div>
                <strong class="mono">{{ account.nomor_rekening }}</strong>
              </div>
            </div>
          </div>

          <div class="panel panel-wide">
            <div class="panel-heading">
              <h2>Riwayat Pembayaran</h2>
              <span>Refresh otomatis</span>
            </div>
            <div v-if="invoice.payments?.length" class="payment-list">
              <div class="line-item" v-for="payment in invoice.payments" :key="`${payment.paid_at}-${payment.amount}`">
                <div>
                  <strong>{{ formatDate(payment.paid_at) }}</strong>
                  <span>{{ payment.payment_account_name || '-' }}</span>
                </div>
                <strong>{{ formatCurrency(payment.amount) }}</strong>
              </div>
            </div>
            <p v-else class="muted-text">Belum ada pembayaran tercatat.</p>
          </div>
        </section>
      </template>
    </section>
  </main>
</template>

<style scoped>
.public-invoice-page {
  min-height: 100vh;
  background: #f5f7fb;
  color: #172033;
  padding: 32px;
}

.invoice-shell {
  max-width: 1080px;
  margin: 0 auto;
}

.invoice-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 24px;
}

.invoice-header h1 {
  font-size: 32px;
  margin: 4px 0 0;
}

.eyebrow {
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
  margin: 0;
}

.summary-grid,
.content-grid {
  display: grid;
  gap: 16px;
}

.summary-grid {
  grid-template-columns: repeat(4, minmax(0, 1fr));
  margin-bottom: 16px;
}

.content-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.summary-block,
.panel,
.empty-state {
  background: #fff;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  padding: 18px;
}

.summary-block {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.summary-block span,
.panel-heading span,
.line-item span,
.account-row span,
.muted-text {
  color: #64748b;
  font-size: 13px;
}

.summary-block strong {
  font-size: 20px;
}

.summary-block.due strong {
  color: #b45309;
}

.summary-block.paid strong {
  color: #047857;
}

.panel-wide {
  grid-column: 1 / -1;
}

.panel-heading,
.line-item,
.account-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.panel-heading {
  margin-bottom: 14px;
}

.panel-heading h2 {
  font-size: 16px;
  margin: 0;
}

.line-item,
.account-row {
  border-top: 1px solid #edf2f7;
  padding: 12px 0;
}

.line-item div,
.account-row div,
.account-list,
.payment-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.mono {
  font-variant-numeric: tabular-nums;
}

.loading-strip {
  margin-bottom: 16px;
}

@media (max-width: 800px) {
  .public-invoice-page {
    padding: 20px;
  }

  .invoice-header,
  .line-item,
  .account-row {
    flex-direction: column;
  }

  .summary-grid,
  .content-grid {
    grid-template-columns: 1fr;
  }

  .invoice-header h1 {
    font-size: 24px;
  }
}
</style>
