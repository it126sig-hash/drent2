<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { format } from 'date-fns'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import rentToRentApi from '../../api/rentToRent'

const route = useRoute()
const bill = ref(null)
const loading = ref(false)
const downloading = ref(false)
const error = ref(null)

const activePayments = computed(() => (bill.value?.payments || []).filter(payment => payment.status !== 'voided'))

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)

const formatDate = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy')
}

const statusLabel = (status) => {
  if (status === 'generated') return 'Dibuat'
  if (status === 'sent') return 'Terkirim'
  if (status === 'partial_paid') return 'Partial Paid'
  if (status === 'paid') return 'Paid'
  if (status === 'void_requested') return 'Menunggu ACC Void'
  return status || '-'
}

const statusSeverity = (status) => {
  if (status === 'paid') return 'success'
  if (status === 'partial_paid') return 'info'
  if (status === 'void_requested') return 'warn'
  return 'secondary'
}

const fetchBill = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await rentToRentApi.getPublicRentToRentBill(route.params.token)
    bill.value = response.data.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Dokumen tagihan tidak ditemukan'
  } finally {
    loading.value = false
  }
}

const downloadPdf = async () => {
  downloading.value = true
  try {
    const response = await rentToRentApi.downloadPublicRentToRentBillPdf(route.params.token)
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${bill.value?.bill_number || 'rent-to-rent-bill'}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  } finally {
    downloading.value = false
  }
}

onMounted(fetchBill)
</script>

<template>
  <main class="public-page">
    <ProgressBar v-if="loading" mode="indeterminate" style="height: 4px" />

    <section v-if="error" class="state-panel">
      <h1>Dokumen tidak tersedia</h1>
      <p>{{ error }}</p>
    </section>

    <section v-else-if="bill" class="bill-sheet">
      <header class="bill-header">
        <div>
          <span class="eyebrow">Rent to Rent Payable</span>
          <h1>{{ bill.bill_number }}</h1>
          <Tag :value="statusLabel(bill.status)" :severity="statusSeverity(bill.status)" />
        </div>
        <button class="download-button" :disabled="downloading" @click="downloadPdf">
          <i class="pi pi-download"></i>
          PDF
        </button>
      </header>

      <div class="party-grid">
        <section>
          <span class="section-label">Pemilik Rental</span>
          <h2>{{ bill.rental_owner?.nama || '-' }}</h2>
          <p>{{ bill.rental_owner?.kontak_1 || '-' }}</p>
          <p>{{ bill.rental_owner?.alamat || '-' }}</p>
        </section>
        <section>
          <span class="section-label">Rekening</span>
          <h2>{{ bill.rental_owner?.bank || '-' }}</h2>
          <p>{{ bill.rental_owner?.no_rek || '-' }}</p>
          <p>{{ bill.rental_owner?.atas_nama || '-' }}</p>
        </section>
      </div>

      <div class="summary-grid">
        <div><span>Tanggal Dokumen</span><strong>{{ formatDate(bill.generated_at) }}</strong></div>
        <div><span>Total Tagihan</span><strong>{{ formatCurrency(bill.total_amount) }}</strong></div>
        <div><span>Sudah Dibayar</span><strong>{{ formatCurrency(bill.paid_amount) }}</strong></div>
        <div><span>Sisa</span><strong>{{ formatCurrency(bill.remaining_amount) }}</strong></div>
      </div>

      <section class="table-section">
        <h2>Transaksi</h2>
        <div class="responsive-table">
          <table>
            <thead>
              <tr>
                <th>Booking</th>
                <th>Unit</th>
                <th>Pelanggan</th>
                <th>Tujuan</th>
                <th class="numeric">Nominal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in bill.items" :key="`${item.kode_booking}-${item.unit_plate}`">
                <td>{{ item.kode_booking || '-' }}</td>
                <td>{{ item.unit_name }}<br><small>{{ item.unit_plate || '-' }}</small></td>
                <td>{{ item.customer_name || '-' }}</td>
                <td>{{ item.tujuan || '-' }}</td>
                <td class="numeric">{{ formatCurrency(item.amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="table-section">
        <h2>Riwayat Pembayaran</h2>
        <div v-if="!activePayments.length" class="empty-line">Belum ada pembayaran.</div>
        <div v-else class="responsive-table">
          <table>
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Akun</th>
                <th class="numeric">Nominal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="payment in activePayments" :key="payment.id">
                <td>{{ formatDate(payment.paid_at) }}</td>
                <td>{{ payment.payment_account_name || '-' }}</td>
                <td class="numeric">{{ formatCurrency(payment.amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>
  </main>
</template>

<style scoped>
.public-page {
  min-height: 100vh;
  background: #f4f6f8;
  padding: 32px;
  color: #17202a;
}

.bill-sheet,
.state-panel {
  max-width: 1040px;
  margin: 0 auto;
  background: #fff;
  border: 1px solid #dde3ea;
  border-radius: 8px;
  box-shadow: 0 18px 50px rgba(23, 32, 42, 0.08);
  padding: 28px;
}

.bill-header,
.party-grid,
.summary-grid {
  display: grid;
  gap: 16px;
}

.bill-header {
  grid-template-columns: 1fr auto;
  align-items: start;
  border-bottom: 1px solid #e5e9ef;
  padding-bottom: 20px;
}

.eyebrow,
.section-label,
.summary-grid span {
  display: block;
  color: #687586;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

h1,
h2,
p {
  margin: 0;
}

h1 {
  margin: 6px 0 10px;
  font-size: 28px;
}

h2 {
  margin: 6px 0;
  font-size: 16px;
}

p,
small,
.empty-line {
  color: #687586;
  font-size: 13px;
}

.download-button {
  min-height: 36px;
  border: 1px solid #cfd7e2;
  border-radius: 8px;
  background: #17202a;
  color: #fff;
  padding: 0 14px;
  font-weight: 800;
  cursor: pointer;
}

.party-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin: 22px 0;
}

.party-grid section,
.summary-grid div {
  border: 1px solid #e5e9ef;
  border-radius: 8px;
  padding: 16px;
}

.summary-grid {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.summary-grid strong {
  display: block;
  margin-top: 6px;
  font-size: 18px;
  font-variant-numeric: tabular-nums;
}

.table-section {
  margin-top: 24px;
}

.table-section h2 {
  margin-bottom: 10px;
}

.responsive-table {
  overflow-x: auto;
  border: 1px solid #e5e9ef;
  border-radius: 8px;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 720px;
}

th,
td {
  padding: 12px;
  border-bottom: 1px solid #e5e9ef;
  text-align: left;
  font-size: 13px;
  vertical-align: top;
}

th {
  background: #f8fafc;
  color: #687586;
  font-size: 11px;
  text-transform: uppercase;
}

tr:last-child td {
  border-bottom: 0;
}

.numeric {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.empty-line {
  border: 1px dashed #cfd7e2;
  border-radius: 8px;
  padding: 16px;
}

@media (max-width: 760px) {
  .public-page {
    padding: 16px;
  }

  .bill-sheet,
  .state-panel {
    padding: 18px;
  }

  .bill-header,
  .party-grid,
  .summary-grid {
    grid-template-columns: 1fr;
  }
}
</style>
