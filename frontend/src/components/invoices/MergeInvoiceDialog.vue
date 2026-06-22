<script setup>
import { ref, computed, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import DatePicker from 'primevue/datepicker'
import { format } from 'date-fns'
import { mergeInvoices } from '../../api/receivable'
import { getInvoiceTermsTemplates } from '../../api/invoiceTermsTemplate'
import InvoiceTermsEditor from '../InvoiceTermsEditor.vue'

const props = defineProps({
  modelValue: Boolean,
  /**
   * Array of invoices to merge. Each item: { id, invoice_number, kode_booking, remaining_amount, due_date? }
   */
  invoices: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'merged'])

const toast = useToast()
const loading = ref(false)

const mergeForm = ref({ due_date: null, terms_and_conditions: '' })
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

const selectedInvoiceNumbers = computed(() => props.invoices.map(i => i.invoice_number).join(', '))
const selectedTotal = computed(() => props.invoices.reduce((sum, i) => sum + (i.remaining_amount || 0), 0))

const defaultDueDate = computed(() => {
  const dates = props.invoices
    .map(i => i.due_date ? new Date(i.due_date) : null)
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
    mergeForm.value.terms_and_conditions = defaultTpl.content
  } else {
    selectedTemplateId.value = null
    mergeForm.value.terms_and_conditions = ''
  }
}

const onTemplateSelect = (templateId) => {
  if (!templateId) { mergeForm.value.terms_and_conditions = ''; return }
  const tpl = termsTemplates.value.find(t => t.id === templateId)
  if (tpl) mergeForm.value.terms_and_conditions = tpl.content
}

watch(() => props.modelValue, async (visible) => {
  if (!visible) return
  mergeForm.value.due_date = defaultDueDate.value
  await loadTemplates()
  applyDefaultTemplate()
})

watch(defaultDueDate, (date) => {
  if (props.modelValue) mergeForm.value.due_date = date
})

const submit = async () => {
  if (props.invoices.length < 2 || loading.value) return
  loading.value = true
  try {
    const response = await mergeInvoices({
      invoice_ids: props.invoices.map(i => i.id),
      due_date: toApiDate(mergeForm.value.due_date),
      terms_and_conditions: mergeForm.value.terms_and_conditions || null,
    })
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Invoice berhasil digabungkan', life: 3000 })
    emit('update:modelValue', false)
    emit('merged', response?.data?.data || null)
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || 'Gagal menggabungkan invoice', life: 5000 })
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
    header="Gabungkan Invoice"
    modal
    :style="{ width: 'min(640px, 96vw)' }"
    class="custom-dialog"
  >
    <div class="dialog-stack">
      <div class="warning-panel">
        <i class="pi pi-exclamation-triangle"></i>
        <span>{{ invoices.length }} invoice akan digabungkan menjadi satu invoice baru. Invoice asli akan tetap aktif; pembayaran invoice gabungan ini otomatis dialokasikan ke invoice asli sesuai sisa tagihannya.</span>
      </div>
      <div class="app-muted-panel">
        <div class="summary-row">
          <span>Jumlah invoice</span>
          <strong>{{ invoices.length }}</strong>
        </div>
        <div class="summary-row">
          <span>Invoice</span>
          <strong>{{ selectedInvoiceNumbers || '-' }}</strong>
        </div>
        <div class="summary-row">
          <span>Sisa tagihan gabungan</span>
          <strong>{{ formatCurrency(selectedTotal) }}</strong>
        </div>
      </div>

      <fieldset class="form-fieldset">
        <label>Due Date Invoice</label>
        <DatePicker v-model="mergeForm.due_date" dateFormat="dd M yy" class="w-full" showIcon />
        <span class="field-hint">Default memakai due date paling akhir dari invoice yang dipilih.</span>
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
        <InvoiceTermsEditor v-model="mergeForm.terms_and_conditions" placeholder="Tulis syarat & ketentuan invoice di sini..." />
        <span class="field-hint">Opsional. Pilih template lalu edit sesuai kebutuhan, atau tulis langsung.</span>
      </fieldset>
    </div>

    <template #footer>
      <button class="app-dialog-button app-dialog-button-secondary" @click="close">
        <i class="pi pi-times"></i>
        Batal
      </button>
      <button class="app-dialog-button app-dialog-button-primary" :disabled="loading || invoices.length < 2" @click="submit">
        <i class="pi pi-check"></i>
        Gabungkan Invoice
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
  font-size: var(--font-size-12px);
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
  font-size: var(--font-size-13px);
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
</style>
