<script setup>
import { ref, computed, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import { format } from 'date-fns'
import { generateInvoice } from '../../api/receivable'
import { getInvoiceTermsTemplates } from '../../api/invoiceTermsTemplate'
import InvoiceTermsEditor from '../InvoiceTermsEditor.vue'
import BookingStatusBadge from '../bookings/BookingStatusBadge.vue'

const props = defineProps({
  modelValue: Boolean,
  /**
   * Array of bookings to generate invoice for.
   * Each item: { id, kode_booking, total, invoiceable_balance, due_date?,
   *   customer_name, vehicle_name, vehicle_plate, driver_name,
   *   tgl_sewa, tgl_kembali, total_biaya: { total, sudah_bayar, sisa } }
   */
  bookings: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'created'])

const toast = useToast()
const loading = ref(false)

const generateForm = ref({ due_date: null, terms_and_conditions: '', amounts: {}, payment_classification: null })
const termsTemplates = ref([])
const selectedTemplateId = ref(null)
const loadingTemplates = ref(false)
let templatesFetched = false

const classificationOptions = [
  { label: 'DP', value: 'dp' },
  { label: 'Cicilan', value: 'cicilan' },
  { label: 'Pelunasan', value: 'pelunasan' },
]

const termsTemplateOptions = computed(() => {
  const opts = [{ label: 'Tidak ada template', value: null }]
  termsTemplates.value.forEach(t => {
    opts.push({ label: t.name + (t.is_default ? ' (Default)' : ''), value: t.id })
  })
  return opts
})

const isCombinedInvoice = computed(() => props.bookings.length > 1)
const selectedBookingCodes = computed(() => props.bookings.map(b => b.kode_booking).join(', '))
const selectedTotal = computed(() =>
  props.bookings.reduce((sum, b) => sum + (generateForm.value.amounts[b.id] ?? b.total ?? 0), 0)
)
const bookingsWithActiveInvoice = computed(() => props.bookings.filter(b => b.has_active_invoice))
const hasInvalidAmounts = computed(() =>
  props.bookings.some(b => !(generateForm.value.amounts[b.id] > 0))
)

const defaultDueDate = computed(() => {
  const dates = props.bookings
    .map(b => b.due_date ? new Date(b.due_date) : null)
    .filter(d => d && !Number.isNaN(d.getTime()))
    .sort((a, b) => b.getTime() - a.getTime())
  return dates[0] || null
})

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)

const formatDate = (value) => (value ? format(new Date(value), 'dd MMM yyyy') : '-')

const toApiDate = (value) => (value ? format(new Date(value), 'yyyy-MM-dd') : null)

const loadTemplates = async () => {
  if (templatesFetched) return
  loadingTemplates.value = true
  try {
    const res = await getInvoiceTermsTemplates()
    termsTemplates.value = res.data.data || []
    templatesFetched = true
  } catch {
    // ignore
  } finally {
    loadingTemplates.value = false
  }
}

const applyDefaultTemplate = () => {
  const defaultTpl = termsTemplates.value.find(t => t.is_default)
  if (defaultTpl) {
    selectedTemplateId.value = defaultTpl.id
    generateForm.value.terms_and_conditions = defaultTpl.content
  } else {
    selectedTemplateId.value = null
    generateForm.value.terms_and_conditions = ''
  }
}

const onTemplateSelect = (templateId) => {
  if (!templateId) { generateForm.value.terms_and_conditions = ''; return }
  const tpl = termsTemplates.value.find(t => t.id === templateId)
  if (tpl) generateForm.value.terms_and_conditions = tpl.content
}

watch(() => props.modelValue, async (visible) => {
  if (!visible) return
  generateForm.value.due_date = defaultDueDate.value
  generateForm.value.payment_classification = null
  generateForm.value.amounts = Object.fromEntries(
    props.bookings.map(b => [b.id, 0])
  )
  await loadTemplates()
  applyDefaultTemplate()
})

watch(defaultDueDate, (date) => {
  if (props.modelValue) generateForm.value.due_date = date
})

const submit = async () => {
  if (!props.bookings.length || loading.value || hasInvalidAmounts.value) return
  loading.value = true
  try {
    const response = await generateInvoice({
      booking_ids: props.bookings.map(b => b.id),
      amounts: generateForm.value.amounts,
      due_date: toApiDate(generateForm.value.due_date),
      terms_and_conditions: generateForm.value.terms_and_conditions || null,
      payment_classification: generateForm.value.payment_classification,
    })
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Invoice berhasil dibuat', life: 3000 })
    emit('update:modelValue', false)
    emit('created', response?.data?.data || null)
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || 'Gagal membuat invoice', life: 5000 })
  } finally {
    loading.value = false
  }
}

const close = () => emit('update:modelValue', false)
</script>

<template>
  <Dialog
    :visible="modelValue"
    @update:visible="close"
    header="Buat Invoice"
    modal
    :style="{ width: 'min(960px, 96vw)' }"
    class="custom-dialog"
  >
    <div class="dialog-stack">
      <div v-if="isCombinedInvoice" class="warning-panel">
        <i class="pi pi-exclamation-triangle"></i>
        <span>Beberapa booking terpilih akan digabungkan menjadi satu invoice.</span>
      </div>
      <div v-if="bookingsWithActiveInvoice.length" class="warning-panel">
        <i class="pi pi-info-circle"></i>
        <span>
          Booking {{ bookingsWithActiveInvoice.map(b => b.kode_booking).join(', ') }} sudah memiliki invoice aktif.
          Invoice baru ini akan dibuat sebagai tambahan, bukan menggantikan invoice yang sudah ada.
        </span>
      </div>

      <div class="dialog-columns">
        <div class="dialog-column">
          <h3 class="column-title">Ringkasan Booking</h3>
          <div v-for="booking in bookings" :key="booking.id" class="booking-summary-card">
            <div class="booking-summary-header">
              <span>{{ booking.kode_booking }}</span>
              <BookingStatusBadge v-if="booking.status" :status="booking.status" />
            </div>
            <div class="booking-summary-row"><span>Unit</span><strong>{{ booking.vehicle_name || '-' }} <template v-if="booking.vehicle_plate">({{ booking.vehicle_plate }})</template></strong></div>
            <div class="booking-summary-row"><span>Konsumen</span><strong>{{ booking.customer_name || '-' }}</strong></div>
            <div class="booking-summary-row"><span>Tanggal</span><strong>{{ formatDate(booking.tgl_sewa) }} s/d {{ formatDate(booking.tgl_kembali) }}</strong></div>
            <div class="booking-summary-row"><span>Driver</span><strong>{{ booking.driver_name || '-' }}</strong></div>
            <div class="booking-summary-divider"></div>
            <div class="booking-summary-row"><span>Total Tagihan</span><strong>{{ formatCurrency(booking.total_biaya?.total ?? booking.total) }}</strong></div>
            <div class="booking-summary-row"><span>Sudah Bayar</span><strong class="text-positive">{{ formatCurrency(booking.total_biaya?.sudah_bayar) }}</strong></div>
            <div class="booking-summary-row"><span>Sisa Tagihan</span><strong class="text-info">{{ formatCurrency(booking.total_biaya?.sisa) }}</strong></div>
          </div>
        </div>

        <div class="dialog-column">
          <h3 class="column-title">Invoice Baru</h3>

          <fieldset class="form-fieldset">
            <label>Nominal Invoice <span class="required-mark">*</span></label>
            <div v-for="booking in bookings" :key="booking.id" class="amount-row">
              <span class="amount-row-label">{{ booking.kode_booking }}</span>
              <InputNumber
                v-model="generateForm.amounts[booking.id]"
                mode="currency" currency="IDR" locale="id-ID"
                :min="0"
                :max="booking.invoiceable_balance ?? booking.total ?? undefined"
                class="w-full"
              />
              <span v-if="!(generateForm.amounts[booking.id] > 0)" class="field-error">Nominal wajib diisi, tidak boleh 0.</span>
              <span v-else class="field-hint">Sisa yang bisa ditagih: {{ formatCurrency(booking.invoiceable_balance ?? booking.total ?? 0) }}</span>
            </div>
          </fieldset>

          <div class="stat-box-row">
            <div class="stat-box">
              <span>Jumlah Booking</span>
              <strong>{{ bookings.length }}</strong>
            </div>
            <div class="stat-box">
              <span>Booking ID</span>
              <strong>{{ selectedBookingCodes || '-' }}</strong>
            </div>
            <div class="stat-box stat-box-highlight">
              <span>Total Invoice</span>
              <strong>{{ formatCurrency(selectedTotal) }}</strong>
            </div>
          </div>

          <div class="dialog-columns-inner">
            <fieldset class="form-fieldset">
              <label>Klasifikasi Pembayaran</label>
              <Dropdown
                v-model="generateForm.payment_classification"
                :options="classificationOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Pilih klasifikasi..."
                class="w-full"
                showClear
              />
            </fieldset>

            <fieldset class="form-fieldset">
              <label>Due Date Invoice</label>
              <DatePicker v-model="generateForm.due_date" dateFormat="dd M yy" class="w-full" showIcon />
            </fieldset>
          </div>

          <fieldset class="form-fieldset">
            <label>Syarat &amp; Ketentuan</label>
            <Dropdown
              v-model="selectedTemplateId"
              :options="termsTemplateOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Pilih template..."
              class="w-full"
              style="margin-bottom: 8px"
              :loading="loadingTemplates"
              @update:modelValue="onTemplateSelect"
            />
            <InvoiceTermsEditor v-model="generateForm.terms_and_conditions" placeholder="Tulis syarat & ketentuan invoice di sini..." />
            <span class="field-hint">Opsional. Pilih template lalu edit sesuai kebutuhan, atau tulis langsung.</span>
          </fieldset>
        </div>
      </div>
    </div>

    <template #footer>
      <button class="app-dialog-button app-dialog-button-secondary" @click="close">
        <i class="pi pi-times"></i>
        Batal
      </button>
      <button class="app-dialog-button app-dialog-button-primary" :disabled="loading || hasInvalidAmounts" @click="submit">
        <i class="pi pi-check"></i>
        Buat Invoice
      </button>
    </template>
  </Dialog>
</template>

<style scoped>
.dialog-stack {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.dialog-columns {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 720px) {
  .dialog-columns {
    grid-template-columns: 1fr;
  }
}

.dialog-column {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
}

.column-title {
  font-size: var(--font-size-13px);
  font-weight: 800;
  margin: 0;
}

.booking-summary-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default, 8px);
  background: var(--card-bg);
  padding: 14px;
}

.booking-summary-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-weight: 800;
  font-size: var(--font-size-13px);
  margin-bottom: 4px;
}

.booking-summary-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: var(--font-size-12px);
}

.booking-summary-divider {
  border-top: 1px solid var(--surface-border);
  margin: 4px 0;
}

.stat-box-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.stat-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default, 8px);
  background: var(--card-bg);
  padding: 12px;
  min-width: 0;
}

.stat-box span {
  color: var(--text-tertiary);
  font-size: var(--font-size-11px);
  font-weight: 700;
}

.stat-box strong {
  font-size: var(--font-size-13px);
  overflow-wrap: anywhere;
}

.stat-box-highlight strong {
  color: var(--primary-color, #4f46e5);
}

.dialog-columns-inner {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

@media (max-width: 480px) {
  .stat-box-row,
  .dialog-columns-inner {
    grid-template-columns: 1fr;
  }
}

.required-mark {
  color: var(--danger-color, #dc2626);
}

.field-error {
  color: var(--danger-color, #dc2626);
  font-size: var(--font-size-11px);
  line-height: 1.4;
}

.warning-panel {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  border: 1px solid rgba(245, 158, 11, 0.35);
  border-radius: var(--radius-default, 8px);
  background: rgba(245, 158, 11, 0.1);
  color: #92400e;
  padding: 14px;
  font-size: var(--font-size-12px);
  font-weight: 700;
  line-height: 1.4;
}

.form-fieldset {
  display: flex;
  flex-direction: column;
  gap: 6px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default, 8px);
  background: var(--card-bg);
  padding: 14px;
}

.form-fieldset label {
  color: var(--text-secondary);
  font-size: var(--font-size-11px);
  font-weight: 700;
}

.field-hint {
  color: var(--text-tertiary);
  font-size: var(--font-size-11px);
  line-height: 1.4;
}

.amount-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 8px;
}

.amount-row-label {
  font-size: var(--font-size-12px);
  font-weight: 700;
}
</style>
