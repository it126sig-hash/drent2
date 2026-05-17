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
const primaryBooking = computed(() => invoice.value?.bookings?.[0] || null)
const invoiceItems = computed(() => {
  if (invoice.value?.items?.length) return invoice.value.items

  return (invoice.value?.bookings || []).map(booking => ({
    description: booking.kode_booking,
    booking_code: booking.kode_booking,
    vehicle_name: booking.vehicle_name,
    vehicle_plate: booking.vehicle_plate,
    rental_start_date: booking.rental_start_date,
    rental_end_date: booking.rental_end_date,
    price: booking.amount,
    qty: 1,
    amount: booking.amount,
  }))
})

const customerName = computed(() => {
  return primaryBooking.value?.customer_name || 'Pelanggan'
})

const customerAddressLines = computed(() => {
  const booking = primaryBooking.value
  return [booking?.customer_address, booking?.customer_city].filter(Boolean)
})

const customerContactLines = computed(() => {
  const booking = primaryBooking.value
  return [
    booking?.customer_phone ? `Telp: ${booking.customer_phone}` : null,
    booking?.customer_phone_alt ? `Telp 2: ${booking.customer_phone_alt}` : null,
    booking?.customer_email ? `Email: ${booking.customer_email}` : null,
  ].filter(Boolean)
})

const paymentHistory = computed(() => {
  if (!invoice.value?.payments) return []
  return invoice.value.payments
})

const filteredPaymentAccounts = computed(() => {
  if (!invoice.value?.payment_accounts) return []
  return invoice.value.payment_accounts.filter(acc => acc.nama_bank && acc.nama_bank.toLowerCase() !== 'cash')
})

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

const downloadInvoice = () => {
  const previousTitle = document.title
  document.title = invoice.value?.invoice_number || 'invoice'
  window.print()
  window.setTimeout(() => {
    document.title = previousTitle
  }, 500)
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
    <div v-if="invoice && !error" class="invoice-action-bar">
      <button class="download-button" @click="downloadInvoice">
        <i class="pi pi-download"></i>
        Download Invoice
      </button>
    </div>

    <div class="invoice-container">
      <div v-if="loading" class="loading-strip">
        <ProgressBar mode="indeterminate" style="height: 4px" />
      </div>

      <div v-if="error" class="empty-state">
        <h1>Invoice tidak tersedia</h1>
        <p>{{ error }}</p>
      </div>

      <template v-else-if="invoice">
        <!-- Top header: Brand Name & INVOICE -->
        <div class="invoice-top-bar">
          <div class="brand-info">
            <i class="pi pi-box logo-icon"></i>
            <div>
              <div class="brand-name">{{ invoice.branch?.name || 'DRENT' }}</div>
              <div class="brand-tagline">CAR RENTAL SYSTEM</div>
            </div>
          </div>
          <div class="invoice-title">INVOICE</div>
        </div>
        
        <div class="red-line"></div>

        <!-- Second header: Invoice to & Invoice details -->
        <div class="invoice-meta-bar">
          <div class="invoice-to">
            <div class="invoice-to-title">Invoice to:</div>
            <div class="customer-name">{{ customerName }}</div>
            <div class="customer-address" v-if="customerAddressLines.length">
              <span v-for="line in customerAddressLines" :key="line">{{ line }}</span>
            </div>
            <div class="customer-contact" v-if="customerContactLines.length">
              <span v-for="line in customerContactLines" :key="line">{{ line }}</span>
            </div>
            <div class="customer-address" v-else-if="!customerAddressLines.length">
              Terima kasih telah menggunakan layanan penyewaan kami.
            </div>
          </div>
          <div class="invoice-details">
            <div class="detail-row">
              <span>Invoice#</span>
              <span>{{ invoice.invoice_number }}</span>
            </div>
            <div class="detail-row">
              <span>Date:</span>
              <span>{{ formatDate(invoice.generated_at || invoice.created_at) }}</span>
            </div>
            <div class="detail-row">
              <span>Due Date:</span>
              <span>{{ formatDate(invoice.due_date) }}</span>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="invoice-table">
          <div class="table-header">
            <div class="col-sl">SL.</div>
            <div class="col-desc">Item Description</div>
            <div class="col-price">Price</div>
            <div class="col-qty">Qty.</div>
            <div class="col-total">Total</div>
          </div>
          <div class="table-body">
            <div class="table-row" v-for="(item, index) in invoiceItems" :key="`${item.type || 'booking'}-${item.booking_code}-${index}`">
              <div class="col-sl">{{ index + 1 }}</div>
              <div class="col-desc">
                <div>
                  <strong>{{ item.description || item.booking_code }}: </strong>
                  <template v-if="item.vehicle_name || item.vehicle_plate">
                    <span class="font-semibold">{{ item.vehicle_name || 'Rental Service' }}</span> <span class="mono">({{ item.vehicle_plate }})</span>
                  </template>
                </div>
                <div class="item-meta" v-if="item.label">{{ item.label }} : <span  v-if="item.note">{{ item.note }}</span></div>
                <div class="item-meta">
                  {{ formatDate(item.rental_start_date) }} - {{ formatDate(item.rental_end_date) }}
                </div>
              </div>
              <div class="col-price">{{ formatCurrency(item.price ?? item.amount) }}</div>
              <div class="col-qty">{{ item.qty || 1 }}</div>
              <div class="col-total">{{ formatCurrency(item.amount) }}</div>
            </div>
          </div>
        </div>

        <!-- Bottom sections -->
        <div class="invoice-bottom">
          <div class="bottom-left">
            <div class="thank-you">Thank you for your business</div>
            <div class="payment-info">
              <div class="payment-history">
                <div class="section-label">History Payment</div>
                <template v-if="paymentHistory.length">
                  <div class="history-row" v-for="payment in paymentHistory" :key="`${payment.paid_at}-${payment.amount}`">
                    <div>
                      <strong>{{ formatDate(payment.paid_at) }}</strong>
                      <span>{{ payment.payment_account_name || '-' }}</span>
                    </div>
                    <span class="mono">{{ formatCurrency(payment.amount) }}</span>
                  </div>
                </template>
                <div v-else class="text-secondary">Belum ada pembayaran.</div>
              </div>

              <div class="payment-title">Payment Info:</div>

              <div class="section-label">Nomor Rekening</div>
              <template v-if="filteredPaymentAccounts.length">
                <div class="account-grid">
                  <div class="account-card" v-for="account in filteredPaymentAccounts" :key="account.nomor_rekening">
                    <div class="account-bank">{{ account.nama_bank }}:  <span class="account-number mono">{{ account.nomor_rekening }}</span></div>
                   
                    <div class="account-name">{{ account.atas_nama }}</div>
                  </div>
                </div>
              </template>
              <template v-else>
                <div class="text-secondary">Tidak ada informasi rekening pembayaran.</div>
              </template>
            </div>

            <div class="terms-conditions">
              <div class="terms-title">Terms & Conditions</div>
              <p>Harap lakukan pembayaran sebelum tanggal jatuh tempo. Keterlambatan dapat dikenakan denda sesuai dengan ketentuan penyewaan. Terima kasih telah mempercayai DRENT.</p>
            </div>
          </div>
          
          <div class="bottom-right">
            <div class="totals-section">
              <div class="total-row">
                <span>Sub Total:</span>
                <span>{{ formatCurrency(invoice.total_amount) }}</span>
              </div>
              <div class="total-row">
                <span>Paid:</span>
                <span>{{ formatCurrency(invoice.paid_amount) }}</span>
              </div>
              <div class="total-row" style="align-items: center;">
                <span>Status:</span>
                <span><Tag :value="invoice.status" :severity="statusSeverity(invoice.status)" class="status-badge" /></span>
              </div>
              <div class="total-row grand-total">
                <span>Remaining:</span>
                <span>{{ formatCurrency(remainingAmount) }}</span>
              </div>
            </div>

            <div class="signature-section">
              <div class="signature-line"></div>
              <div class="signature-text">Authorised Sign</div>
            </div>
          </div>
        </div>

        <div class="invoice-footer-band"></div>
      </template>
    </div>
  </main>
</template>

<style scoped>
.public-invoice-page {
  min-height: 100vh;
  background: var(--page-bg);
  padding: 28px 20px 40px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: flex-start;
  color: var(--text-primary);
  font-family: var(--font-body);
}

.invoice-action-bar {
  width: 100%;
  max-width: 210mm;
  margin: 0 auto 14px;
  display: flex;
  justify-content: flex-end;
}

.download-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 36px;
  border: none;
  border-radius: var(--radius-full);
  background: var(--text-primary);
  color: var(--text-white);
  padding: 8px 16px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: var(--shadow-tile);
}

.invoice-container {
  width: 100%;
  max-width: 210mm;
  min-height: 297mm;
  margin: 0 auto;
  background: var(--surface-default);
  box-shadow: var(--shadow-card-big);
  position: relative;
  overflow: hidden;
  border-radius: var(--radius-sm);
}

.loading-strip {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
}

.empty-state {
  padding: 40px;
  text-align: center;
}

/* TOP BAR */
.invoice-top-bar {
  background-color: var(--text-primary);
  color: var(--text-white);
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 40px;
}

.brand-info {
  display: flex;
  align-items: center;
  gap: 16px;
}

.brand-info i {
  font-size: 32px;
}

.brand-name {
  font-family: var(--font-headline);
  font-size: 24px;
  font-weight: 700;
  line-height: 1.2;
}

.brand-tagline {
  font-size: 10px;
  color: var(--neutral-4);
  letter-spacing: 1px;
}

.invoice-title {
  font-family: var(--font-headline);
  font-size: 36px;
  font-weight: 700;
  color: var(--negative);
  letter-spacing: 2px;
}

.red-line {
  height: 8px;
  background-color: var(--negative);
}

/* META BAR */
.invoice-meta-bar {
  background-color: #272C3F; /* Darker than white, lighter than primary */
  color: var(--text-white);
  padding: 30px 40px;
  display: flex;
  justify-content: space-between;
}

.invoice-to {
  font-size: 12px;
  color: var(--neutral-4);
  max-width: 360px;
}

.invoice-to-title {
  font-family: var(--font-headline);
  font-size: 16px;
  font-weight: 600;
  color: var(--text-white);
  margin-bottom: 8px;
}

.customer-name {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-white);
  margin-bottom: 4px;
}

.customer-address {
  line-height: 1.5;
  display: flex;
  flex-direction: column;
}

.customer-contact {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-top: 8px;
  color: var(--neutral-4);
  line-height: 1.4;
}

.invoice-details {
  font-size: 13px;
  color: var(--text-white);
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 220px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  gap: 16px;
}

.detail-row span:first-child {
  font-weight: 600;
  color: var(--text-white);
}

.detail-row span:last-child {
  font-family: var(--font-mono);
}

/* TABLE */
.invoice-table {
  padding: 0 40px;
  margin-top: 30px;
}

.table-header {
  display: flex;
  background-color: #EAF0EC; /* Light greenish grey */
  padding: 12px 20px;
  font-weight: 600;
  font-family: var(--font-headline);
  color: var(--text-primary);
  font-size: 14px;
}

.table-row {
  display: flex;
  padding: 16px 20px;
  border-bottom: 1px solid var(--surface-border);
  font-size: 13px;
  align-items: center;
}

.item-meta {
  color: var(--text-secondary);
  font-size: 11px;
  line-height: 1.4;
  margin-top: 2px;
}

.col-sl { width: 10%; text-align: center; }
.col-desc { width: 50%; }
.col-price { width: 15%; text-align: right; font-family: var(--font-mono); }
.col-qty { width: 10%; text-align: center; font-family: var(--font-mono); }
.col-total { width: 15%; text-align: right; font-family: var(--font-mono); }

/* BOTTOM SECTION */
.invoice-bottom {
  display: flex;
  justify-content: space-between;
  padding: 40px;
  gap: 40px;
}

.bottom-left {
  flex: 1;
}

.thank-you {
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 16px;
}

.payment-title {
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 12px;
}

.payment-info {
  margin-bottom: 30px;
  font-size: 12px;
}

.section-label {
  color: var(--text-secondary);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: .4px;
  margin: 0 0 8px;
  text-transform: uppercase;
}

.payment-history {
  margin-bottom: 18px;
}

.history-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px solid var(--surface-border);
  align-items: center;
}

.history-row div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.history-row span {
  color: var(--text-secondary);
}

.account-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.account-card {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-sm);
  background: var(--card-bg);
  padding: 10px;
  min-width: 0;
}

.account-bank {
  font-size: 11px;
  font-weight: 800;
  color: var(--text-primary);
}

.account-number {
  margin-top: 6px;
  font-size: 13px;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.account-name {
  margin-top: 4px;
  color: var(--text-secondary);
  overflow-wrap: anywhere;
}

.terms-title {
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 8px;
}

.terms-conditions p {
  font-size: 10px;
  color: var(--text-secondary);
  line-height: 1.5;
  margin: 0;
}

.bottom-right {
  width: 250px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.totals-section {
  width: 100%;
}

.total-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 13px;
  font-weight: 600;
}

.total-row span:last-child {
  font-family: var(--font-mono);
}

.grand-total {
  border-top: 1px solid var(--text-primary);
  border-bottom: 1px solid var(--text-primary);
  margin-top: 12px;
  padding: 12px 0;
  font-size: 16px;
}

.signature-section {
  margin-top: 60px;
  text-align: right;
  width: 150px;
  align-self: flex-end;
}

.signature-line {
  border-bottom: 1px solid var(--text-primary);
  width: 100%;
  margin-bottom: 8px;
}

.signature-text {
  font-size: 12px;
  font-weight: 600;
  text-align: center;
}

.invoice-footer-band {
  height: 24px;
  background-color: #272C3F;
}

.mono {
  font-family: var(--font-mono);
}

@media (max-width: 650px) {
  .public-invoice-page {
    padding: 12px;
    display: block;
  }

  .invoice-action-bar {
    position: sticky;
    top: 8px;
    z-index: 5;
    margin-bottom: 10px;
  }

  .download-button {
    width: 100%;
  }

  .invoice-container {
    min-height: 0;
  }

  .invoice-top-bar, .invoice-meta-bar {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
    padding: 24px;
  }
  
  .invoice-title {
    font-size: 28px;
  }
  
  .invoice-bottom {
    flex-direction: column;
    padding: 24px;
    gap: 30px;
  }
  
  .invoice-table {
    padding: 0 16px;
    margin-top: 20px;
    overflow-x: auto;
  }

  .table-header,
  .table-row {
    min-width: 560px;
    padding-left: 12px;
    padding-right: 12px;
  }
  
  .col-sl, .col-qty {
    display: none;
  }
  
  .col-desc { width: 50%; }
  .col-price { width: 25%; }
  .col-total { width: 25%; }
  
  .bottom-right { 
    width: 100%; 
  }

  .account-grid {
    grid-template-columns: 1fr;
  }
  
  .signature-section {
    align-self: center;
  }
}

@page {
  size: A4;
  margin: 0;
}

@media print {
  .public-invoice-page {
    background: #fff;
    padding: 0;
    min-height: 0;
    display: block;
  }

  .invoice-action-bar {
    display: none;
  }

  .invoice-container {
    width: 210mm;
    min-height: 297mm;
    max-width: none;
    border-radius: 0;
    box-shadow: none;
  }
}
</style>
