<script setup>
import { ref, computed, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import DatePicker from 'primevue/datepicker'
import { format } from 'date-fns'
import { generateInvoice } from '../../api/receivable'
import { getInvoiceTermsTemplates } from '../../api/invoiceTermsTemplate'
import InvoiceTermsEditor from '../InvoiceTermsEditor.vue'

const props = defineProps({
  modelValue: Boolean,
  /**
   * Array of bookings to generate invoice for.
   * Each item: { id, kode_booking, total, due_date? }
   */
  bookings: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'created'])

const toast = useToast()
const loading = ref(false)

const generateForm = ref({ due_date: null, terms_and_conditions: '' })
const termsTemplates = ref([])
const selectedTemplateId = ref(null)
const loadingTemplates = ref(false)
let templatesFetched = false

const termsTemplateOptions = computed(() => {
  const opts = [{ label: 'Tidak ada template', value: null }]
  termsTemplates.value.forEach(t => {
    opts.push({ label: t.name + (t.is_default ? ' (Default)' : ''), value: t.id })
  })
  return opts
})

const isCombinedInvoice = computed(() => props.bookings.length > 1)
const selectedBookingCodes = computed(() => props.bookings.map(b => b.kode_booking).join(', '))
const selectedTotal = computed(() => props.bookings.reduce((sum, b) => sum + (b.total || 0), 0))

const defaultDueDate = computed(() => {
  const dates = props.bookings
    .map(b => b.due_date ? new Date(b.due_date) : null)
    .filter(d => d && !Number.isNaN(d.getTime()))
    .sort((a, b) => b.getTime() - a.getTime())
  return dates[0] || null
})

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)

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
  await loadTemplates()
  applyDefaultTemplate()
})

watch(defaultDueDate, (date) => {
  if (props.modelValue) generateForm.value.due_date = date
})

const submit = async () => {
  if (!props.bookings.length || loading.value) return
  loading.value = true
  try {
    const response = await generateInvoice({
      booking_ids: props.bookings.map(b => b.id),
      due_date: toApiDate(generateForm.value.due_date),
      terms_and_conditions: generateForm.value.terms_and_conditions || null,
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
    :style="{ width: 'min(640px, 96vw)' }"
    class="custom-dialog"
  >
    <div class="dialog-stack">
      <div v-if="isCombinedInvoice" class="warning-panel">
        <i class="pi pi-exclamation-triangle"></i>
        <span>Beberapa booking terpilih akan digabungkan menjadi satu invoice.</span>
      </div>
      <div class="app-muted-panel">
        <div class="summary-row">
          <span>Jumlah booking</span>
          <strong>{{ bookings.length }}</strong>
        </div>
        <div class="summary-row">
          <span>Booking</span>
          <strong>{{ selectedBookingCodes || '-' }}</strong>
        </div>
        <div class="summary-row">
          <span>Total invoice</span>
          <strong>{{ formatCurrency(selectedTotal) }}</strong>
        </div>
      </div>

      <fieldset class="form-fieldset">
        <label>Due Date Invoice</label>
        <DatePicker v-model="generateForm.due_date" dateFormat="dd M yy" class="w-full" showIcon />
        <span class="field-hint">Otomatis dari ketentuan pelanggan. Untuk invoice gabungan, sistem memakai due date paling akhir dari booking terpilih.</span>
      </fieldset>

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

    <template #footer>
      <button class="app-dialog-button app-dialog-button-secondary" @click="close">
        <i class="pi pi-times"></i>
        Batal
      </button>
      <button class="app-dialog-button app-dialog-button-primary" :disabled="loading" @click="submit">
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

.warning-panel {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  border: 1px solid rgba(245, 158, 11, 0.35);
  border-radius: var(--radius-default, 8px);
  background: rgba(245, 158, 11, 0.1);
  color: #92400e;
  padding: 14px;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.4;
}

.app-muted-panel {
  display: flex;
  flex-direction: column;
  gap: 8px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default, 8px);
  background: var(--card-bg);
  padding: 14px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
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
  font-size: 11px;
  font-weight: 700;
}

.field-hint {
  color: var(--text-tertiary);
  font-size: 11px;
  line-height: 1.4;
}
</style>
