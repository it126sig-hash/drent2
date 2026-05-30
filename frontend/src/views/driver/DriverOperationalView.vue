<script setup>
import { computed, onMounted, ref } from 'vue'
import { format } from 'date-fns'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useCostType } from '../../composables/useCostType'
import { useOperationalFund } from '../../composables/useOperationalFund'

const {
  funds,
  loading,
  actionLoading,
  fetchDriverFunds,
  acceptFund,
  submitExpense,
} = useOperationalFund()
const { costTypes, fetchAll: fetchCostTypes } = useCostType()

const activeTab = ref('funds')
const selectedFund = ref(null)
const showExpenseDialog = ref(false)
const expenseForm = ref({
  type: 'expense',
  cost_type_id: null,
  amount: null,
  description: '',
  photo: null,
})

const costTypeOptions = computed(() => costTypes.value
  .filter(item => item.kode !== 'driver')
  .map(item => ({
    id: item.id,
    label: item.nama,
  }))
)

const currentBalance = computed(() => {
  const fundWithDriver = funds.value.find(fund => fund.driver)
  return fundWithDriver?.driver?.saldo || 0
})

// Hanya tampilkan transaksi yang masih aktif (belum closed/cancelled)
const activeOnlyFunds = computed(() =>
  funds.value.filter(fund => fund.status !== 'closed' && fund.status !== 'cancelled')
)

const pendingFunds = computed(() =>
  activeOnlyFunds.value.filter(fund => fund.status === 'pending_driver_acceptance')
)

const rejectedExpenses = computed(() =>
  activeOnlyFunds.value.flatMap(fund => (fund.expenses || [])
    .filter(expense => expense.status === 'rejected')
    .map(expense => ({ ...expense, fund }))
  )
)

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)

const formatDateTime = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy HH:mm')
}

const formatDate = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy')
}

const fundStatusSeverity = (status) => {
  if (status === 'accepted') return 'success'
  if (status === 'closed') return 'info'
  if (status === 'cancelled') return 'danger'
  return 'warn'
}

const expenseStatusSeverity = (status) => {
  if (status === 'approved') return 'success'
  if (status === 'rejected') return 'danger'
  return 'warn'
}

const unitLabel = (fund) => {
  const unit = fund.booking_detail?.unit
  if (!unit) return '-'
  const merkTipe = [unit.merk, unit.tipe].filter(Boolean).join(' ')
  return merkTipe || '-'
}

const reload = async () => {
  await fetchDriverFunds(1)
}

const handleAcceptFund = async (fund) => {
  await acceptFund(fund.id)
  await reload()
}

const openExpenseDialog = (fund, type = 'expense') => {
  selectedFund.value = fund
  expenseForm.value = {
    type,
    cost_type_id: null,
    amount: null,
    description: '',
    photo: null,
  }
  showExpenseDialog.value = true
}

const retryRejectedExpense = (item) => {
  selectedFund.value = item.fund
  expenseForm.value = {
    type: item.type,
    cost_type_id: item.cost_type_id,
    amount: item.amount,
    description: item.description,
    photo: null,
  }
  showExpenseDialog.value = true
}

const onPhotoChange = (event) => {
  expenseForm.value.photo = event.target.files?.[0] || null
}

const submitDriverExpense = async () => {
  await submitExpense(selectedFund.value.id, expenseForm.value)
  showExpenseDialog.value = false
  await reload()
}

onMounted(async () => {
  await Promise.all([
    reload(),
    fetchCostTypes({ per_page: 100, is_active: true }),
  ])
})
</script>

<template>
  <div class="driver-page">
    <header class="driver-header">
      <div>
        <h1 class="text-h1">Operasional Driver</h1>
        <p class="text-secondary text-xs">Dana, bon, dan pengembalian transaksi yang sedang berjalan.</p>
      </div>
      <button class="btn-pill btn-secondary btn-pill-compact" :disabled="loading" @click="reload">
        <i class="pi pi-refresh"></i>
        Refresh
      </button>
    </header>

    <section class="balance-panel">
      <span>Saldo Operasional</span>
      <strong>{{ formatCurrency(currentBalance) }}</strong>
      <small>{{ pendingFunds.length }} dana menunggu ACC, {{ rejectedExpenses.length }} bon perlu perbaikan</small>
    </section>

    <div class="mobile-tabs">
      <button class="tab-button" :class="{ active: activeTab === 'funds' }" @click="activeTab = 'funds'">
        <i class="pi pi-wallet"></i>
        Dana
      </button>
      <button class="tab-button" :class="{ active: activeTab === 'receipts' }" @click="activeTab = 'receipts'">
        <i class="pi pi-receipt"></i>
        Bon
      </button>
    </div>

    <ProgressBar v-if="loading" mode="indeterminate" style="height: 4px" />

    <section v-if="activeTab === 'funds'" class="card-stack">
      <article v-for="fund in activeOnlyFunds" :key="fund.id" class="app-card fund-card">
        <div class="card-top">
          <div>
            <strong>{{ fund.booking?.kode_booking || '-' }}</strong>
            <p>{{ fund.booking?.customer?.nama || '-' }}</p>
          </div>
          <Tag :value="fund.status" :severity="fundStatusSeverity(fund.status)" />
        </div>
        <div class="fund-amount">{{ formatCurrency(fund.amount) }}</div>
        <div class="info-grid">
          <span>Unit</span><strong>{{ unitLabel(fund) }}</strong>
          <span>Nopol</span><strong>{{ fund.booking_detail?.unit?.no_polisi || '-' }}</strong>
          <span>Tgl Sewa</span>
          <strong>
            {{ formatDate(fund.booking_detail?.tgl_sewa) }}
            <template v-if="fund.booking_detail?.tgl_kembali">
              &mdash; {{ formatDate(fund.booking_detail?.tgl_kembali) }}
            </template>
          </strong>
          <span>Penjemputan</span><strong>{{ fund.booking?.alamat_penjemputan || '-' }}</strong>
          <span>Tujuan</span><strong>{{ fund.booking?.tujuan || '-' }}</strong>
          <span>Catatan</span><strong>{{ fund.booking?.catatan || '-' }}</strong>
          <span>Sisa Dana</span><strong>{{ formatCurrency(fund.summary.remaining_amount) }}</strong>
        </div>
        <div class="breakdown-list">
          <div v-for="item in fund.items" :key="item.id">
            <span>{{ item.label }}</span>
            <strong>{{ formatCurrency(item.planned_amount) }}</strong>
          </div>
        </div>
        <div class="card-actions">
          <button v-if="fund.status === 'pending_driver_acceptance'" class="btn-pill btn-primary" :disabled="actionLoading" @click="handleAcceptFund(fund)">
            <i class="pi pi-check"></i>
            ACC Dana
          </button>
          <template v-if="fund.status === 'accepted'">
            <button class="btn-pill btn-primary" @click="openExpenseDialog(fund, 'expense')">
              <i class="pi pi-receipt"></i>
              Input Bon
            </button>
            <button class="btn-pill btn-secondary" @click="openExpenseDialog(fund, 'return')">
              <i class="pi pi-undo"></i>
              Pengembalian
            </button>
          </template>
        </div>
      </article>
      <div v-if="!activeOnlyFunds.length && !loading" class="empty-state">
        <i class="pi pi-info-circle"></i>
        <span>Belum ada dana operasional aktif.</span>
      </div>
    </section>

    <section v-else class="card-stack">
      <article v-for="fund in activeOnlyFunds" :key="fund.id" class="app-card receipt-group">
        <div class="card-top">
          <strong>{{ fund.booking?.kode_booking || '-' }}</strong>
          <span>{{ formatCurrency(fund.summary.remaining_amount) }} tersisa</span>
        </div>
        <div v-if="fund.expenses?.length" class="receipt-list">
          <div v-for="expense in fund.expenses" :key="expense.id" class="receipt-row">
            <div>
              <strong>{{ expense.type === 'return' ? 'Pengembalian' : (expense.cost_type?.nama || 'Bon') }}</strong>
              <p>{{ expense.description }}</p>
              <p v-if="expense.rejection_reason" class="reject-note">{{ expense.rejection_reason }}</p>
            </div>
            <div class="receipt-side">
              <strong>{{ formatCurrency(expense.amount) }}</strong>
              <Tag :value="expense.status" :severity="expenseStatusSeverity(expense.status)" />
              <button v-if="expense.status === 'rejected'" class="mini-link" @click="retryRejectedExpense({ ...expense, fund })">Input ulang</button>
            </div>
          </div>
        </div>
        <div v-else class="muted-line">Belum ada bon.</div>
      </article>
      <div v-if="!activeOnlyFunds.length && !loading" class="empty-state">
        <i class="pi pi-info-circle"></i>
        <span>Belum ada bon untuk transaksi aktif.</span>
      </div>
    </section>

    <Dialog v-model:visible="showExpenseDialog" :header="expenseForm.type === 'return' ? 'Pengembalian Dana' : 'Input Bon'" modal position="bottom" class="custom-dialog mobile-bottom-sheet">
      <div class="dialog-stack">
        <div class="app-muted-panel">
          <div class="summary-row">
            <span>Booking</span>
            <strong>{{ selectedFund?.booking?.kode_booking || '-' }}</strong>
          </div>
          <div class="summary-row">
            <span>Sisa dana</span>
            <strong>{{ formatCurrency(selectedFund?.summary?.remaining_amount) }}</strong>
          </div>
        </div>
        <fieldset v-if="expenseForm.type === 'expense'" class="form-fieldset">
          <label>Jenis Biaya</label>
          <Dropdown v-model="expenseForm.cost_type_id" :options="costTypeOptions" optionLabel="label" optionValue="id" placeholder="Pilih cost type" showClear class="w-full" />
        </fieldset>
        <fieldset class="form-fieldset">
          <label>Nominal</label>
          <InputNumber v-model="expenseForm.amount" mode="currency" currency="IDR" locale="id-ID" :min="1" class="w-full" />
        </fieldset>
        <fieldset class="form-fieldset">
          <label>Keterangan</label>
          <Textarea v-model="expenseForm.description" rows="4" class="w-full" placeholder="Contoh: BBM tol parkir..." />
        </fieldset>
        <fieldset class="form-fieldset">
          <label>Foto Bon / Bukti</label>
          <input type="file" accept="image/*" capture="environment" @change="onPhotoChange" />
          <span class="field-hint">{{ expenseForm.photo?.name || 'Ambil foto bon atau bukti pengembalian.' }}</span>
        </fieldset>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showExpenseDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || !expenseForm.amount || expenseForm.description.length < 3" @click="submitDriverExpense">
          Kirim
        </button>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.driver-page {
  min-height: 100dvh;
  padding: var(--space-lg);
  padding-bottom: calc(82px + env(safe-area-inset-bottom));
  background: var(--page-bg);
}

.driver-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-md);
  margin-bottom: var(--space-lg);
}

.balance-panel {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: var(--space-lg);
  margin-bottom: var(--space-lg);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--text-primary);
  color: #fff;
  box-shadow: var(--shadow-tile);
}

.balance-panel span,
.balance-panel small {
  color: rgba(255, 255, 255, 0.74);
  font-size: 11px;
  font-weight: 700;
}

.balance-panel strong {
  font-size: 24px;
  line-height: 1.1;
  font-weight: 900;
}

.mobile-tabs {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px;
  padding: 4px;
  margin-bottom: var(--space-lg);
  border-radius: var(--radius-full);
  background: var(--card-bg);
}

.tab-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 38px;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 800;
}

.tab-button.active {
  background: var(--text-primary);
  color: #fff;
}

.card-stack,
.receipt-list,
.dialog-stack {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.app-card,
.app-muted-panel,
.form-fieldset {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.fund-card,
.receipt-group {
  padding: var(--space-lg);
}

.card-top,
.card-actions,
.summary-row,
.receipt-row,
.info-grid {
  display: flex;
  gap: var(--space-md);
}

.card-top,
.receipt-row,
.summary-row {
  justify-content: space-between;
}

.card-top {
  align-items: flex-start;
}

.card-top p,
.receipt-row p {
  margin: 4px 0 0;
  color: var(--text-secondary);
  font-size: 12px;
  line-height: 1.35;
}

.fund-amount {
  margin: var(--space-md) 0;
  font-size: 22px;
  font-weight: 900;
  color: var(--text-primary);
  font-variant-numeric: tabular-nums;
}

.info-grid {
  display: grid;
  grid-template-columns: 96px 1fr;
  row-gap: 6px;
  margin-bottom: var(--space-md);
  font-size: 12px;
  line-height: 1.35;
}

.info-grid span {
  color: var(--text-secondary);
}

.info-grid strong {
  word-break: break-word;
}

.breakdown-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: var(--space-md);
  border-radius: var(--radius-default);
  background: var(--card-bg);
}

.breakdown-list div {
  display: flex;
  justify-content: space-between;
  gap: var(--space-md);
  font-size: 12px;
}

.card-actions {
  margin-top: var(--space-md);
  flex-direction: column;
}

.card-actions .btn-pill {
  width: 100%;
  justify-content: center;
}

.receipt-row {
  padding: var(--space-md) 0;
  border-bottom: 1px dashed var(--surface-border);
}

.receipt-row:last-child {
  border-bottom: none;
}

.receipt-side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  min-width: 112px;
}

.mini-link {
  border: none;
  background: transparent;
  color: var(--text-primary);
  font-size: 11px;
  font-weight: 800;
  text-decoration: underline;
}

.reject-note {
  color: var(--negative) !important;
  font-weight: 800;
}

.muted-line,
.empty-state {
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 800;
}

.empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: var(--space-2xl);
  border: 1px dashed var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--card-bg);
}

.form-fieldset,
.app-muted-panel {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: var(--space-md);
  background: var(--card-bg);
  box-shadow: none;
}

.form-fieldset label {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 800;
}

.field-hint {
  color: var(--text-tertiary);
  font-size: 11px;
}

:deep(.mobile-bottom-sheet) {
  margin: 0 !important;
  width: 100% !important;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0 !important;
  max-height: 88vh;
}

:deep(.custom-dialog .p-dialog-footer) {
  border-top: 1px solid var(--surface-border);
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

@media (min-width: 769px) {
  .driver-page {
    padding: var(--space-2xl);
  }

  .card-stack {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
  }

  .receipt-group {
    min-height: 180px;
  }
}
</style>
