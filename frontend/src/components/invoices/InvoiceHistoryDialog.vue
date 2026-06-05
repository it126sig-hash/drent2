<script setup>
import { ref, watch } from 'vue'
import { format } from 'date-fns'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import { useToast } from 'primevue/usetoast'
import { getInvoiceHistories } from '../../api/receivable'

const props = defineProps({
  modelValue: Boolean,
  invoiceId: { type: [Number, String], default: null },
  invoiceNumber: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const toast = useToast()
const histories = ref([])
const loading = ref(false)

const historyEventLabel = (eventType) => {
  const map = {
    created: 'Invoice Dibuat',
    sent: 'Invoice Dikirim',
    amended: 'Nominal Diperbarui',
    payment_received: 'Pembayaran Diterima',
    voided: 'Invoice Void',
  }
  return map[eventType] || eventType
}

const historyEventSeverity = (eventType) => {
  if (eventType === 'created') return 'info'
  if (eventType === 'sent') return 'secondary'
  if (eventType === 'amended') return 'warn'
  if (eventType === 'payment_received') return 'success'
  if (eventType === 'voided') return 'danger'
  return 'secondary'
}

const formatDateTime = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy HH:mm')
}

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)

watch(() => props.modelValue, async (visible) => {
  if (!visible || !props.invoiceId) return
  loading.value = true
  histories.value = []
  try {
    const res = await getInvoiceHistories(props.invoiceId)
    histories.value = res.data.data || []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal mengambil history invoice', life: 5000 })
  } finally {
    loading.value = false
  }
})

const close = () => emit('update:modelValue', false)
</script>

<template>
  <Dialog
    :visible="modelValue"
    @update:visible="close"
    :header="`History — ${invoiceNumber || 'Invoice'}`"
    modal
    :style="{ width: 'min(560px, 96vw)' }"
    class="custom-dialog"
  >
    <div v-if="loading" class="loading-strip">
      <ProgressBar mode="indeterminate" style="height: 4px" />
    </div>
    <div v-else-if="!histories.length" class="history-empty">
      Belum ada riwayat untuk invoice ini.
    </div>
    <div v-else class="invoice-history-timeline">
      <div v-for="entry in histories" :key="entry.id" class="history-entry">
        <div class="history-dot-col">
          <span class="history-dot" :class="`history-dot-${historyEventSeverity(entry.event_type)}`"></span>
          <span class="history-line"></span>
        </div>
        <div class="history-content">
          <div class="history-header">
            <Tag
              :value="historyEventLabel(entry.event_type)"
              :severity="historyEventSeverity(entry.event_type)"
              class="history-tag"
            />
            <span class="history-time text-xs text-secondary">{{ formatDateTime(entry.created_at) }}</span>
          </div>
          <div v-if="entry.actor_name" class="text-xs text-secondary mt-1">{{ entry.actor_name }}</div>
          <div v-if="entry.event_type === 'amended'" class="history-amount-change">
            <span>{{ formatCurrency(entry.amount_before) }}</span>
            <i class="pi pi-arrow-right text-xs"></i>
            <span class="font-semibold">{{ formatCurrency(entry.amount_after) }}</span>
          </div>
          <div v-else-if="entry.event_type === 'created' && entry.amount_after" class="text-xs mt-1">
            Nominal: <strong>{{ formatCurrency(entry.amount_after) }}</strong>
          </div>
          <div v-else-if="entry.event_type === 'payment_received'" class="text-xs mt-1 text-positive font-semibold">
            +{{ formatCurrency(entry.payment_amount) }}
          </div>
        </div>
      </div>
    </div>
    <template #footer>
      <button class="app-dialog-button app-dialog-button-secondary" @click="close">
        <i class="pi pi-times"></i>
        Tutup
      </button>
    </template>
  </Dialog>
</template>

<style scoped>
.loading-strip {
  margin-bottom: 12px;
}

.history-empty {
  padding: 14px 0;
  color: var(--text-secondary);
  font-size: 12px;
  text-align: center;
}

.invoice-history-timeline {
  display: flex;
  flex-direction: column;
}

.history-entry {
  display: flex;
  gap: 12px;
  position: relative;
}

.history-entry:last-child .history-line {
  display: none;
}

.history-dot-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
  flex-shrink: 0;
  width: 16px;
  padding-top: 4px;
}

.history-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

.history-dot-info    { background: #3B82F6; }
.history-dot-secondary { background: #6B7280; }
.history-dot-warn    { background: #F59E0B; }
.history-dot-success { background: #10B981; }
.history-dot-danger  { background: #EF4444; }

.history-line {
  flex: 1;
  width: 2px;
  background: var(--surface-border);
  min-height: 20px;
  margin-top: 4px;
}

.history-content {
  padding-bottom: 20px;
  flex: 1;
  min-width: 0;
}

.history-header {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.history-tag { font-size: 11px; }

.history-time {
  margin-left: auto;
  white-space: nowrap;
}

.history-amount-change {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  margin-top: 4px;
  font-variant-numeric: tabular-nums;
}

.text-secondary { color: var(--text-secondary); }
.text-positive  { color: var(--positive, #10b981); }
</style>
