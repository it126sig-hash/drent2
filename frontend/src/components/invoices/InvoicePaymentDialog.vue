<script setup>
import { ref, computed, watch } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import Tag from 'primevue/tag'
import DatePicker from 'primevue/datepicker'
import { format } from 'date-fns'
import { addInvoicePayment } from '../../api/receivable'
import { usePaymentAccount } from '../../composables/usePaymentAccount'

const props = defineProps({
  modelValue: Boolean,
  /**
   * Full invoice object.
   * Required fields: id, invoice_number, total_amount, paid_amount,
   * remaining_amount, status, generated_at, due_date, sent_at,
   * payments[], items[], bookings[]
   */
  invoice: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'paid'])

const confirm = useConfirm()
const toast = useToast()
const { accounts, fetchAll: fetchAccounts } = usePaymentAccount()

const paymentForm = ref({ payment_account_id: null, amount: null, paid_at: new Date() })
const paymentConfirming = ref(false)
const paymentSubmitting = ref(false)
let accountsFetched = false

watch(() => props.modelValue, (visible) => {
  if (!visible) return
  if (!accountsFetched) {
    fetchAccounts({ per_page: 100 })
    accountsFetched = true
  }
  paymentForm.value = {
    payment_account_id: paymentAccountOptions.value[0]?.value || null,
    amount: props.invoice?.remaining_amount || null,
    paid_at: new Date(),
  }
})

const paymentAccountOptions = computed(() =>
  accounts.value.map(a => ({ label: `${a.nama_bank} - ${a.nomor_rekening}`, value: a.id }))
)

const selectedInvoiceRemaining = computed(() => props.invoice?.remaining_amount || 0)

const selectedInvoiceItems = computed(() => {
  if (!props.invoice) return []
  if (props.invoice.items?.length) return props.invoice.items
  return (props.invoice.bookings || []).map(b => ({
    type: 'booking',
    description: b.kode_booking,
    booking_code: b.kode_booking,
    customer_name: b.customer_name,
    price: b.amount,
    qty: 1,
    amount: b.amount,
  }))
})

const selectedInvoiceCustomerNames = computed(() => {
  const names = (props.invoice?.bookings || []).map(b => b.customer_name).filter(Boolean)
  return [...new Set(names)].join(', ') || '-'
})

const isSubmitDisabled = computed(() =>
  paymentConfirming.value || paymentSubmitting.value
  || !paymentForm.value.payment_account_id
  || !paymentForm.value.amount
)

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)

const formatDate = (value) => (value ? format(new Date(value), 'dd MMM yyyy') : '-')
const formatDateTime = (value) => (value ? format(new Date(value), 'dd MMM yyyy HH:mm') : '-')
const toApiDate = (value) => (value ? format(new Date(value), 'yyyy-MM-dd') : null)

const invoiceSeverity = (status) => {
  if (status === 'paid') return 'success'
  if (status === 'partial_paid') return 'info'
  if (status === 'void') return 'danger'
  return 'warning'
}

const submit = async () => {
  if (!props.invoice || isSubmitDisabled.value) return
  paymentConfirming.value = true
  confirm.require({
    message: `Catat pembayaran ${formatCurrency(paymentForm.value.amount)} untuk invoice ${props.invoice.invoice_number || props.invoice.number || ''}?`,
    header: 'Konfirmasi Pembayaran',
    icon: 'pi pi-credit-card',
    acceptLabel: 'Ya, Simpan',
    rejectLabel: 'Batal',
    acceptClass: 'app-dialog-button app-dialog-button-primary',
    rejectClass: 'app-dialog-button app-dialog-button-secondary',
    accept: async () => {
      if (paymentSubmitting.value) return
      paymentConfirming.value = false
      paymentSubmitting.value = true
      try {
        await addInvoicePayment(props.invoice.id, {
          payment_account_id: paymentForm.value.payment_account_id,
          amount: paymentForm.value.amount,
          paid_at: toApiDate(paymentForm.value.paid_at),
        })
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Pembayaran berhasil dicatat', life: 3000 })
        emit('update:modelValue', false)
        emit('paid')
      } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || 'Gagal mencatat pembayaran', life: 5000 })
      } finally {
        paymentSubmitting.value = false
      }
    },
    reject: () => { paymentConfirming.value = false },
    onHide: () => { paymentConfirming.value = false },
  })
}

const close = () => emit('update:modelValue', false)
</script>

<template>
  <Dialog :visible="modelValue" @update:visible="close" header="Pembayaran Invoice" modal :style="{ width: 'min(1180px, 96vw)' }" class="custom-dialog payment-invoice-dialog">
    <div class="payment-invoice-modal" v-if="invoice">
      <section class="payment-invoice-preview">
        <div class="payment-invoice-top">
          <div>
            <div class="payment-invoice-kicker">INVOICE</div>
            <h2>{{ invoice.invoice_number || invoice.number }}</h2>
            <p>{{ selectedInvoiceCustomerNames }}</p>
          </div>
          <Tag :value="invoice.status" :severity="invoiceSeverity(invoice.status)" />
        </div>

        <div class="payment-invoice-meta">
          <div>
            <span>Tanggal Invoice</span>
            <strong>{{ formatDate(invoice.generated_at) }}</strong>
          </div>
          <div>
            <span>Due Date</span>
            <strong>{{ formatDate(invoice.due_date) }}</strong>
          </div>
          <div>
            <span>Terakhir Kirim</span>
            <strong>{{ formatDateTime(invoice.sent_at) }}</strong>
          </div>
        </div>

        <div class="payment-invoice-table">
          <div class="payment-invoice-table-header">
            <span>No.</span>
            <span>Item Description</span>
            <span>Price</span>
            <span>Qty</span>
            <span>Total</span>
          </div>
          <div v-for="(item, index) in selectedInvoiceItems" :key="`${item.type || 'item'}-${item.booking_code || index}-${index}`" class="payment-invoice-table-row">
            <span>{{ index + 1 }}</span>
            <div>
              <strong>{{ item.description || item.booking_code || 'Rental Service' }}</strong>
              <small v-if="item.booking_code && item.description !== item.booking_code">{{ item.booking_code }}</small>
              <small v-if="item.label">{{ item.label }}<template v-if="item.note">: {{ item.note }}</template></small>
              <small v-if="item.vehicle_name || item.vehicle_plate">
                {{ item.vehicle_name || 'Rental Service' }}
                <span v-if="item.vehicle_plate" class="font-mono-numeric">({{ item.vehicle_plate }})</span>
              </small>
              <small v-if="item.rental_start_date || item.rental_end_date">
                {{ formatDate(item.rental_start_date) }} - {{ formatDate(item.rental_end_date) }}
              </small>
            </div>
            <span>{{ formatCurrency(item.price ?? item.amount) }}</span>
            <span>{{ item.qty || 1 }}</span>
            <strong>{{ formatCurrency(item.amount) }}</strong>
          </div>
          <div v-if="!selectedInvoiceItems.length" class="payment-invoice-empty">Belum ada detail item invoice.</div>
        </div>
      </section>

      <aside class="payment-invoice-side">
        <div class="payment-total-panel">
          <div class="payment-total-row">
            <span>Sub Total</span>
            <strong>{{ formatCurrency(invoice.total_amount) }}</strong>
          </div>
          <div class="payment-total-row">
            <span>Paid</span>
            <strong class="text-positive">{{ formatCurrency(invoice.paid_amount) }}</strong>
          </div>
          <div class="payment-total-row grand">
            <span>Remaining</span>
            <strong>{{ formatCurrency(selectedInvoiceRemaining) }}</strong>
          </div>
        </div>

        <div class="payment-history-panel">
          <div class="section-label">Riwayat Pembayaran</div>
          <template v-if="invoice.payments?.length">
            <div class="payment-history-row" v-for="payment in invoice.payments" :key="payment.id || `${payment.paid_at}-${payment.amount}`">
              <div>
                <strong>{{ formatDate(payment.paid_at) }}</strong>
                <small v-if="payment.source === 'booking'">Pembayaran transaksi</small>
              </div>
              <div class="payment-amount-col">
                <strong>{{ formatCurrency(payment.amount) }}</strong>
                <small>{{ payment.payment_account_name || '-' }}</small>
              </div>
            </div>
          </template>
          <div v-else class="payment-invoice-empty">Belum ada pembayaran.</div>
        </div>

        <div class="payment-form-panel">
          <div class="section-label">Catat Pembayaran</div>
          <fieldset class="form-fieldset">
            <label>Akun Pembayaran</label>
            <Dropdown v-model="paymentForm.payment_account_id" :options="paymentAccountOptions" optionLabel="label" optionValue="value" placeholder="Pilih akun" class="w-full" />
          </fieldset>
          <fieldset class="form-fieldset">
            <label>Nominal</label>
            <InputNumber v-model="paymentForm.amount" mode="currency" currency="IDR" locale="id-ID" :min="1" :max="selectedInvoiceRemaining" class="w-full" />
          </fieldset>
          <fieldset class="form-fieldset">
            <label>Tanggal Bayar</label>
            <DatePicker v-model="paymentForm.paid_at" dateFormat="dd M yy" class="w-full" showIcon />
          </fieldset>
        </div>
      </aside>
    </div>

    <template #footer>
      <button class="app-dialog-button app-dialog-button-secondary" @click="close">
        <i class="pi pi-times"></i>
        Batal
      </button>
      <button class="app-dialog-button app-dialog-button-primary" :disabled="isSubmitDisabled" @click="submit">
        <i class="pi pi-check"></i>
        Simpan Pembayaran
      </button>
    </template>
  </Dialog>
</template>

<style scoped>
.payment-invoice-modal {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 16px;
  max-height: min(74vh, 760px);
  overflow: hidden;
}

.payment-invoice-preview,
.payment-invoice-side {
  min-width: 0;
  overflow: auto;
}

.payment-invoice-preview {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default, 8px);
  background: var(--surface-default);
}

.payment-invoice-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 20px;
  border-bottom: 3px solid #E5534B;
}

.payment-invoice-kicker {
  color: #E5534B;
  font-size: 11px;
  font-weight: 800;
}

.payment-invoice-top h2 {
  margin: 2px 0;
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 22px;
}

.payment-invoice-top p {
  margin: 0;
  color: var(--text-secondary);
  font-size: 12px;
}

.payment-invoice-meta {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1px;
  background: var(--surface-border);
}

.payment-invoice-meta>div {
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: var(--card-bg);
  padding: 12px 16px;
}

.payment-invoice-meta span {
  color: var(--text-secondary);
  font-size: 11px;
}

.payment-invoice-meta strong {
  color: var(--text-primary);
  font-size: 12px;
}

.payment-invoice-table {
  padding: 16px;
}

.payment-invoice-table-header,
.payment-invoice-table-row {
  display: grid;
  grid-template-columns: 44px minmax(260px, 1fr) 130px 64px 130px;
  gap: 12px;
  align-items: center;
}

.payment-invoice-table-header {
  padding: 10px 12px;
  border-radius: var(--radius-xs, 4px);
  background: var(--text-primary);
  color: #fff;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

.payment-invoice-table-row {
  padding: 12px;
  border-bottom: 1px solid var(--surface-border);
  color: var(--text-primary);
  font-size: 12px;
}

.payment-invoice-table-row>span,
.payment-invoice-table-row>strong {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.payment-invoice-table-row>span:first-child {
  text-align: center;
}

.payment-invoice-table-row small {
  display: block;
  margin-top: 3px;
  color: var(--text-secondary);
  font-size: 11px;
  line-height: 1.35;
}

.payment-invoice-side {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.payment-total-panel,
.payment-history-panel,
.payment-form-panel {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default, 8px);
  background: var(--surface-default);
  padding: 14px;
}

.payment-total-row,
.payment-history-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 0;
}

.payment-total-row {
  align-items: center;
  border-bottom: 1px solid var(--surface-border);
}

.payment-total-row.grand {
  margin-top: 6px;
  border-bottom: 0;
  color: #fff;
  background: #E5534B;
  border-radius: var(--radius-xs, 4px);
  padding: 12px;
}

.payment-total-row span,
.payment-total-row strong,
.payment-history-row strong {
  font-size: 12px;
}

.payment-history-row {
  align-items: flex-start;
  border-bottom: 1px solid var(--surface-border);
}

.payment-history-row:last-child,
.payment-total-row:last-child {
  border-bottom: 0;
}

.payment-amount-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  text-align: right;
}

.payment-amount-col strong {
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}

.payment-history-row small {
  display: block;
  margin-top: 3px;
  color: var(--text-tertiary);
  font-size: 11px;
}

.section-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 8px;
}

.form-fieldset {
  display: flex;
  flex-direction: column;
  gap: 6px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default, 8px);
  background: var(--card-bg);
  padding: 12px;
  margin-bottom: 8px;
}

.form-fieldset label {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

.payment-invoice-empty {
  padding: 14px 0;
  color: var(--text-secondary);
  font-size: 12px;
  text-align: center;
}

.text-positive {
  color: var(--positive, #10b981);
}

@media (max-width: 768px) {
  .payment-invoice-modal {
    grid-template-columns: 1fr;
    max-height: 78vh;
    overflow: auto;
  }

  .payment-invoice-preview,
  .payment-invoice-side {
    overflow: visible;
  }

  .payment-invoice-meta,
  .payment-invoice-table-header,
  .payment-invoice-table-row {
    grid-template-columns: 1fr;
  }

  .payment-invoice-table-header {
    display: none;
  }

  .payment-invoice-table-row {
    gap: 6px;
  }

  .payment-invoice-table-row>span,
  .payment-invoice-table-row>strong {
    text-align: left;
  }
}
</style>
