<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { format } from 'date-fns'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import Paginator from 'primevue/paginator'
import { useCancellation } from '../../composables/useCancellation'
import { usePaymentAccount } from '../../composables/usePaymentAccount'

const router = useRouter()
const { cancellations, loading, pagination, fetchCancellations, payRefund } = useCancellation()
const { accounts, fetchAll: fetchPaymentAccounts } = usePaymentAccount()

const isMobile = ref(window.innerWidth < 768)
const onResize = () => { isMobile.value = window.innerWidth < 768 }
onMounted(() => window.addEventListener('resize', onResize))
onUnmounted(() => window.removeEventListener('resize', onResize))

const filters = ref({
  ada_refund: null,
  sudah_bayar_refund: null,
  per_page: 15,
  page: 1,
})

const adaRefundOptions = [
  { label: 'Semua', value: null },
  { label: 'Ada Refund', value: 1 },
  { label: 'Tidak Ada Refund', value: 0 },
]

const statusRefundOptions = [
  { label: 'Semua', value: null },
  { label: 'Belum Dibayar', value: 0 },
  { label: 'Sudah Dibayar', value: 1 },
]

const showPayRefundDialog = ref(false)
const selectedCancellation = ref(null)
const payRefundForm = ref({ payment_account_id: null })
const payRefundLoading = ref(false)
const accountOptions = ref([])

const load = async () => {
  const params = { ...filters.value }
  if (params.ada_refund === null) delete params.ada_refund
  if (params.sudah_bayar_refund === null) delete params.sudah_bayar_refund
  await fetchCancellations(params)
}

const onPageChange = (event) => {
  filters.value.page = event.page + 1
  filters.value.per_page = event.rows
  load()
}

const applyFilters = () => {
  filters.value.page = 1
  load()
}

const resetFilters = () => {
  filters.value.ada_refund = null
  filters.value.sudah_bayar_refund = null
  applyFilters()
}

const openPayRefundDialog = (cancellation) => {
  selectedCancellation.value = cancellation
  payRefundForm.value = { payment_account_id: null }
  showPayRefundDialog.value = true
}

const submitPayRefund = async () => {
  if (!payRefundForm.value.payment_account_id) return
  payRefundLoading.value = true
  try {
    await payRefund(selectedCancellation.value.id, payRefundForm.value)
    showPayRefundDialog.value = false
    load()
  } catch {
    // error handled by composable
  } finally {
    payRefundLoading.value = false
  }
}

const formatCurrency = (val) => {
  if (!val && val !== 0) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val)
}

const formatDate = (val) => {
  if (!val) return '-'
  try { return format(new Date(val), 'dd MMM yyyy HH:mm') } catch { return val }
}

onMounted(async () => {
  await Promise.all([
    load(),
    fetchPaymentAccounts(),
  ])
  accountOptions.value = accounts.value.map(a => ({ id: a.id, name: `${a.nama_bank} - ${a.nomor_rekening}` }))
})
</script>

<template>
  <div class="page-container table-page-active">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="text-h1">Pembatalan Booking</h1>
        <p class="text-secondary text-xs">Kelola pembatalan dan proses pembayaran refund.</p>
      </div>
    </div>

    <div class="tab-content list-tab-fill">
      <!-- Filter Bar -->
      <div class="filter-bar surface-card">
        <div class="filter-groups">
          <div class="filter-group">
            <label>Status Refund</label>
            <Dropdown
              v-model="filters.ada_refund"
              :options="adaRefundOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Semua"
              class="w-full"
              @change="applyFilters"
            />
          </div>
          <div class="filter-group">
            <label>Pembayaran Refund</label>
            <Dropdown
              v-model="filters.sudah_bayar_refund"
              :options="statusRefundOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Semua"
              class="w-full"
              @change="applyFilters"
            />
          </div>
        </div>
        <div class="filter-actions">
          <button class="btn-pill btn-secondary btn-pill-compact" @click="resetFilters" :disabled="loading">
            <i class="pi pi-refresh"></i>
          </button>
          <button class="btn-pill btn-primary btn-pill-compact" @click="applyFilters" :disabled="loading">
            <i class="pi pi-filter"></i> Filter
          </button>
        </div>
      </div>

      <!-- Desktop: DataTable -->
      <div v-if="!isMobile" class="table-shell">
        <DataTable
          :value="cancellations"
          :loading="loading"
          scrollable
          scrollHeight="flex"
          class="drent-datatable"
        >
          <Column header="Kode Booking" style="min-width: 150px">
            <template #body="{ data }">
              <button
                class="booking-code-link"
                @click="router.push({ name: 'BookingDetail', params: { id: data.booking_id } })"
              >
                {{ data.booking?.kode_booking || '-' }}
              </button>
            </template>
          </Column>
          <Column header="Customer" style="min-width: 160px">
            <template #body="{ data }">
              <span class="text-primary font-medium">{{ data.booking?.customer?.nama || '-' }}</span>
            </template>
          </Column>
          <Column header="Tgl Batal" style="min-width: 165px">
            <template #body="{ data }">
              <span class="font-mono text-secondary text-xs">{{ formatDate(data.created_at) }}</span>
            </template>
          </Column>
          <Column header="Ada Refund" style="min-width: 110px">
            <template #body="{ data }">
              <span
                class="status-badge"
                :class="data.ada_refund ? 'warning' : 'neutral'"
              >
                {{ data.ada_refund ? 'Ada Refund' : 'Tidak' }}
              </span>
            </template>
          </Column>
          <Column header="Nominal Refund" style="min-width: 155px">
            <template #body="{ data }">
              <span v-if="data.ada_refund" class="font-mono text-primary font-semibold">
                {{ formatCurrency(data.nominal_refund) }}
              </span>
              <span v-else class="text-tertiary">—</span>
            </template>
          </Column>
          <Column header="Bank / No. Rekening" style="min-width: 210px">
            <template #body="{ data }">
              <template v-if="data.ada_refund && data.bank_refund">
                <div class="font-medium text-primary text-sm">{{ data.bank_refund }}</div>
                <div class="font-mono text-secondary text-xs">{{ data.no_rek_refund || '-' }}</div>
                <div class="text-tertiary text-xs">{{ data.nama_rek_refund || '' }}</div>
              </template>
              <span v-else class="text-tertiary">—</span>
            </template>
          </Column>
          <Column header="Status Refund" style="min-width: 150px">
            <template #body="{ data }">
              <template v-if="!data.ada_refund">
                <span class="status-badge neutral">Tidak Ada</span>
              </template>
              <template v-else-if="data.sudah_bayar_refund">
                <div class="flex flex-col gap-1">
                  <span class="status-badge success">Sudah Dibayar</span>
                  <span class="font-mono text-tertiary text-xs">{{ formatDate(data.dibayar_at) }}</span>
                </div>
              </template>
              <template v-else>
                <span class="status-badge warning">Belum Dibayar</span>
              </template>
            </template>
          </Column>
          <Column header="Aksi" style="min-width: 140px">
            <template #body="{ data }">
              <div class="action-pill-group">
                <button
                  v-if="data.ada_refund && !data.sudah_bayar_refund"
                  class="action-btn"
                  title="Bayar Refund"
                  @click="openPayRefundDialog(data)"
                >
                  <i class="pi pi-send"></i>
                  <span>Bayar Refund</span>
                </button>
                <span v-else-if="data.sudah_bayar_refund" class="text-tertiary text-xs">
                  via {{ data.payment_account?.nama_bank || '-' }}
                </span>
                <span v-else class="text-tertiary text-xs">—</span>
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="table-empty-state">
              <i class="pi pi-inbox table-empty-icon"></i>
              <p>Tidak ada data pembatalan</p>
            </div>
          </template>
        </DataTable>

        <!-- Paginator -->
        <div class="table-paginator" v-if="pagination.total > 0">
          <Paginator
            :rows="pagination.per_page"
            :totalRecords="pagination.total"
            :rowsPerPageOptions="[10, 15, 25, 50]"
            @page="onPageChange"
          />
        </div>
      </div>

      <!-- Mobile: Card Grid -->
      <div v-else class="mobile-card-list">
        <div v-if="loading" class="table-empty-state">
          <i class="pi pi-spinner pi-spin table-empty-icon"></i>
          <p>Memuat data...</p>
        </div>
        <template v-else-if="cancellations.length">
          <div
            v-for="item in cancellations"
            :key="item.id"
            class="mobile-card"
          >
            <div class="card-header">
              <button
                class="booking-code-link"
                @click="router.push({ name: 'BookingDetail', params: { id: item.booking_id } })"
              >
                {{ item.booking?.kode_booking || '-' }}
              </button>
              <span
                class="status-badge"
                :class="!item.ada_refund ? 'neutral' : item.sudah_bayar_refund ? 'success' : 'warning'"
              >
                {{ !item.ada_refund ? 'Tidak Ada' : item.sudah_bayar_refund ? 'Lunas' : 'Belum Bayar' }}
              </span>
            </div>
            <div class="card-body">
              <div>
                <span class="field-hint">Customer</span>
                {{ item.booking?.customer?.nama || '-' }}
              </div>
              <div>
                <span class="field-hint">Tgl Batal</span>
                <span class="font-mono text-xs">{{ formatDate(item.created_at) }}</span>
              </div>
              <div v-if="item.ada_refund">
                <span class="field-hint">Nominal Refund</span>
                <span class="font-mono font-semibold">{{ formatCurrency(item.nominal_refund) }}</span>
              </div>
              <div v-if="item.ada_refund && item.bank_refund">
                <span class="field-hint">Tujuan</span>
                {{ item.bank_refund }} – {{ item.no_rek_refund || '-' }}
                <span v-if="item.nama_rek_refund" class="text-tertiary text-xs"> ({{ item.nama_rek_refund }})</span>
              </div>
              <div v-if="item.sudah_bayar_refund && item.dibayar_at">
                <span class="field-hint">Dibayar pada</span>
                <span class="font-mono text-xs">{{ formatDate(item.dibayar_at) }}</span>
              </div>
            </div>
            <div class="card-footer" v-if="item.ada_refund && !item.sudah_bayar_refund">
              <button class="btn-pill btn-primary" @click="openPayRefundDialog(item)">
                <i class="pi pi-send"></i> Bayar Refund
              </button>
            </div>
            <div class="card-footer" v-else-if="item.sudah_bayar_refund">
              <span class="text-tertiary text-xs">via {{ item.payment_account?.nama_bank || '-' }}</span>
            </div>
          </div>
        </template>
        <div v-else class="table-empty-state">
          <i class="pi pi-inbox table-empty-icon"></i>
          <p>Tidak ada data pembatalan</p>
        </div>

        <div class="table-paginator" v-if="pagination.total > 0">
          <Paginator
            :rows="pagination.per_page"
            :totalRecords="pagination.total"
            :rowsPerPageOptions="[10, 15, 25, 50]"
            @page="onPageChange"
          />
        </div>
      </div>
    </div>

    <!-- Dialog: Bayar Refund -->
    <Dialog
      v-model:visible="showPayRefundDialog"
      header="Bayar Refund"
      :style="{ width: '440px' }"
      modal
      class="custom-dialog"
    >
      <div class="flex flex-col gap-4 pt-2" v-if="selectedCancellation">
        <!-- Refund Summary -->
        <div class="app-muted-panel">
          <div class="flex flex-col gap-1">
            <span class="font-semibold text-primary text-sm">{{ selectedCancellation.booking?.kode_booking }}</span>
            <span class="text-secondary text-xs">
              Nominal refund:
              <strong class="font-mono">{{ formatCurrency(selectedCancellation.nominal_refund) }}</strong>
            </span>
            <span class="text-secondary text-xs">
              Tujuan: {{ selectedCancellation.bank_refund }} – {{ selectedCancellation.no_rek_refund }}
              <template v-if="selectedCancellation.nama_rek_refund">
                ({{ selectedCancellation.nama_rek_refund }})
              </template>
            </span>
          </div>
        </div>

        <!-- Payment Account -->
        <div class="flex flex-col gap-1.5">
          <label class="form-label">Akun Pembayaran <span class="text-negative">*</span></label>
          <Dropdown
            v-model="payRefundForm.payment_account_id"
            :options="accountOptions"
            optionLabel="name"
            optionValue="id"
            placeholder="Pilih akun yang digunakan..."
            class="w-full"
          />
          <small class="text-tertiary text-xs">Saldo akun akan berkurang sebesar nominal refund</small>
        </div>
      </div>

      <template #footer>
        <div class="flex gap-2 justify-end">
          <button
            class="app-dialog-button app-dialog-button-secondary"
            @click="showPayRefundDialog = false"
          >
            Batal
          </button>
          <button
            class="app-dialog-button app-dialog-button-primary"
            :disabled="!payRefundForm.payment_account_id || payRefundLoading"
            @click="submitPayRefund"
          >
            <i class="pi pi-spin pi-spinner" v-if="payRefundLoading"></i>
            <i class="pi pi-check" v-else></i>
            Proses Pembayaran
          </button>
        </div>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.booking-code-link {
  font-family: var(--font-mono);
  font-size: 13px;
  font-weight: 600;
  color: var(--info-cyan);
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  text-decoration: none;
  transition: color 0.15s;
}
.booking-code-link:hover {
  color: var(--text-primary);
  text-decoration: underline;
}

.font-mono {
  font-family: var(--font-mono);
}

.text-primary   { color: var(--text-primary); }
.text-secondary { color: var(--text-secondary); }
.text-tertiary  { color: var(--text-tertiary); }
.text-negative  { color: var(--negative); }

.font-semibold { font-weight: 600; }
.font-medium   { font-weight: 500; }

.table-shell {
  display: flex;
  flex-direction: column;
}

.table-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 48px 16px;
  color: var(--text-tertiary);
  font-size: 13px;
}
.table-empty-icon {
  font-size: 28px;
  opacity: 0.4;
}

.table-paginator {
  border-top: 1px solid var(--surface-border);
  padding: 8px 16px;
}

.form-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-secondary);
  letter-spacing: 0.02em;
}

/* Mobile card field hint */
.field-hint {
  display: block;
  font-size: 11px;
  color: var(--text-tertiary);
  margin-bottom: 2px;
}

@media (max-width: 768px) {
  .filter-groups {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-md);
    width: 100%;
  }
  .btn-pill.btn-primary {
    width: 100%;
    justify-content: center;
  }
}
</style>
