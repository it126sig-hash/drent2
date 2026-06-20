<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { format } from 'date-fns'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Paginator from 'primevue/paginator'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import { useCancellation } from '../../composables/useCancellation'
import { usePaymentAccount } from '../../composables/usePaymentAccount'

const router = useRouter()
const { cancellations, loading, pagination, fetchCancellations, updateCancellation, payRefund } = useCancellation()
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

// --- Edit Refund Dialog ---
const showEditDialog = ref(false)
const selectedEditCancellation = ref(null)
const editForm = ref({
  ada_refund: false,
  nominal_refund: null,
  bank_refund: '',
  no_rek_refund: '',
  nama_rek_refund: '',
  catatan_refund: '',
})
const editLoading = ref(false)

const openEditDialog = (cancellation) => {
  selectedEditCancellation.value = cancellation
  editForm.value = {
    ada_refund: cancellation.ada_refund ?? false,
    nominal_refund: cancellation.nominal_refund ?? null,
    bank_refund: cancellation.bank_refund ?? '',
    no_rek_refund: cancellation.no_rek_refund ?? '',
    nama_rek_refund: cancellation.nama_rek_refund ?? '',
    catatan_refund: cancellation.catatan_refund ?? '',
  }
  showEditDialog.value = true
}

const submitEdit = async () => {
  editLoading.value = true
  try {
    await updateCancellation(selectedEditCancellation.value.id, {
      ada_refund: editForm.value.ada_refund,
      nominal_refund: editForm.value.ada_refund ? editForm.value.nominal_refund : null,
      bank_refund: editForm.value.ada_refund ? editForm.value.bank_refund : null,
      no_rek_refund: editForm.value.ada_refund ? editForm.value.no_rek_refund : null,
      nama_rek_refund: editForm.value.ada_refund ? editForm.value.nama_rek_refund : null,
      catatan_refund: editForm.value.catatan_refund || null,
    })
    showEditDialog.value = false
    load()
  } catch {
    // error handled by composable
  } finally {
    editLoading.value = false
  }
}

// --- Pay Refund Dialog ---
const showPayRefundDialog = ref(false)
const selectedCancellation = ref(null)
const payRefundForm = ref({ payment_account_id: null, nominal_dibayar: null, catatan: '' })
const payRefundLoading = ref(false)
const accountOptions = ref([])

const openPayRefundDialog = (cancellation) => {
  selectedCancellation.value = cancellation
  payRefundForm.value = {
    payment_account_id: null,
    nominal_dibayar: cancellation.nominal_refund ?? null,
    catatan: '',
  }
  showPayRefundDialog.value = true
}

const submitPayRefund = async () => {
  if (!payRefundForm.value.payment_account_id) return
  payRefundLoading.value = true
  try {
    await payRefund(selectedCancellation.value.id, {
      payment_account_id: payRefundForm.value.payment_account_id,
      nominal_dibayar: payRefundForm.value.nominal_dibayar,
      catatan: payRefundForm.value.catatan || null,
    })
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
          <Column header="Aksi" style="min-width: 220px">
            <template #body="{ data }">
              <div class="table-actions">
                <!-- Pay refund / status -->
                <button
                  v-if="data.ada_refund && !data.sudah_bayar_refund"
                  class="btn-pill btn-primary btn-pill-compact"
                  title="Bayar Refund"
                  @click="openPayRefundDialog(data)"
                >
                  <i class="pi pi-send"></i>
                  Bayar Refund
                </button>
                <span v-else-if="data.sudah_bayar_refund" class="text-tertiary text-xs">
                  via {{ data.payment_account?.nama_bank || '-' }}
                </span>
                <span v-else class="text-tertiary text-xs">—</span>

                <!-- Edit refund (only before paid) -->
                <span v-if="!data.sudah_bayar_refund" class="action-pill-group">
                  <button
                    class="action-btn"
                    title="Edit Refund"
                    @click="openEditDialog(data)"
                  >
                    <i class="pi pi-pencil"></i>
                  </button>
                </span>
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
            <div class="card-footer mobile-card-actions">
              <template v-if="!item.sudah_bayar_refund">
                <button class="btn-pill btn-secondary btn-sm" @click="openEditDialog(item)">
                  <i class="pi pi-pencil"></i> Edit Refund
                </button>
                <button
                  v-if="item.ada_refund"
                  class="btn-pill btn-primary btn-sm"
                  @click="openPayRefundDialog(item)"
                >
                  <i class="pi pi-send"></i> Bayar Refund
                </button>
              </template>
              <span v-else class="text-tertiary text-xs">via {{ item.payment_account?.nama_bank || '-' }}</span>
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

    <!-- Dialog: Edit Refund -->
    <Dialog
      v-model:visible="showEditDialog"
      header="Edit Data Refund"
      :style="{ width: '480px' }"
      modal
      class="custom-dialog"
    >
      <div class="flex flex-col gap-4 pt-2" v-if="selectedEditCancellation">
        <div class="app-muted-panel">
          <span class="font-semibold text-primary text-sm">{{ selectedEditCancellation.booking?.kode_booking }}</span>
        </div>

        <!-- Ada Refund toggle -->
        <div class="flex items-center gap-3">
          <Checkbox v-model="editForm.ada_refund" :binary="true" inputId="edit-ada-refund" />
          <label for="edit-ada-refund" class="form-label cursor-pointer">Ada Refund</label>
        </div>

        <template v-if="editForm.ada_refund">
          <div class="flex flex-col gap-1.5">
            <label class="form-label">Nominal Refund <span class="text-negative">*</span></label>
            <InputNumber
              v-model="editForm.nominal_refund"
              :min="0"
              mode="currency"
              currency="IDR"
              locale="id-ID"
              :minFractionDigits="0"
              :maxFractionDigits="0"
              class="w-full"
              placeholder="0"
            />
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="form-label">Bank</label>
            <InputText v-model="editForm.bank_refund" class="w-full" placeholder="Contoh: BCA, Mandiri, BRI..." />
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="form-label">Nomor Rekening</label>
            <InputText v-model="editForm.no_rek_refund" class="w-full" placeholder="Nomor rekening tujuan" />
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="form-label">Nama Pemilik Rekening</label>
            <InputText v-model="editForm.nama_rek_refund" class="w-full" placeholder="Nama pemilik rekening" />
          </div>
        </template>

        <div class="flex flex-col gap-1.5">
          <label class="form-label">Catatan Pembatalan</label>
          <Textarea v-model="editForm.catatan_refund" rows="3" class="w-full" placeholder="Alasan atau catatan pembatalan..." />
        </div>
      </div>

      <template #footer>
        <div class="flex gap-2 justify-end">
          <button
            class="app-dialog-button app-dialog-button-secondary"
            @click="showEditDialog = false"
          >
            Batal
          </button>
          <button
            class="app-dialog-button app-dialog-button-primary"
            :disabled="(editForm.ada_refund && !editForm.nominal_refund) || editLoading"
            @click="submitEdit"
          >
            <i class="pi pi-spin pi-spinner" v-if="editLoading"></i>
            <i class="pi pi-check" v-else></i>
            Simpan Perubahan
          </button>
        </div>
      </template>
    </Dialog>

    <!-- Dialog: Bayar Refund -->
    <Dialog
      v-model:visible="showPayRefundDialog"
      header="Bayar Refund"
      :style="{ width: '480px' }"
      modal
      class="custom-dialog"
    >
      <div class="flex flex-col gap-4 pt-2" v-if="selectedCancellation">
        <!-- Refund Summary -->
        <div class="app-muted-panel">
          <div class="flex flex-col gap-1">
            <span class="font-semibold text-primary text-sm">{{ selectedCancellation.booking?.kode_booking }}</span>
            <span class="text-secondary text-xs">
              Nominal refund tercatat:
              <strong class="font-mono">{{ formatCurrency(selectedCancellation.nominal_refund) }}</strong>
            </span>
            <span v-if="selectedCancellation.bank_refund" class="text-secondary text-xs">
              Tujuan: {{ selectedCancellation.bank_refund }} – {{ selectedCancellation.no_rek_refund }}
              <template v-if="selectedCancellation.nama_rek_refund">
                ({{ selectedCancellation.nama_rek_refund }})
              </template>
            </span>
          </div>
        </div>

        <!-- Nominal yang dibayarkan -->
        <div class="flex flex-col gap-1.5">
          <label class="form-label">Nominal yang Dibayarkan <span class="text-negative">*</span></label>
          <InputNumber
            v-model="payRefundForm.nominal_dibayar"
            :min="1"
            mode="currency"
            currency="IDR"
            locale="id-ID"
            :minFractionDigits="0"
            :maxFractionDigits="0"
            class="w-full"
            placeholder="Isi nominal aktual yang dibayarkan"
          />
          <small class="text-tertiary text-xs">Saldo akun pembayaran akan berkurang sebesar nominal ini</small>
        </div>

        <!-- Catatan -->
        <div class="flex flex-col gap-1.5">
          <label class="form-label">Catatan Pembayaran Refund <span class="text-tertiary">(opsional)</span></label>
          <Textarea v-model="payRefundForm.catatan" rows="3" class="w-full" placeholder="Contoh: ditransfer via mobile banking..." />
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
          <small class="text-tertiary text-xs">Saldo akun akan berkurang dan tercatat sebagai transaksi refund_out</small>
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
            :disabled="!payRefundForm.payment_account_id || !payRefundForm.nominal_dibayar || payRefundLoading"
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
  font-size: var(--font-size-13px);
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
  font-size: var(--font-size-13px);
}
.table-empty-icon {
  font-size: var(--font-size-28px);
  opacity: 0.4;
}

.table-paginator {
  border-top: 1px solid var(--surface-border);
  padding: 8px 16px;
}

.table-actions {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  flex-wrap: wrap;
}

.form-label {
  font-size: var(--font-size-11px);
  font-weight: 600;
  color: var(--text-secondary);
  letter-spacing: 0.02em;
}

/* Mobile card field hint */
.field-hint {
  display: block;
  font-size: var(--font-size-11px);
  color: var(--text-tertiary);
  margin-bottom: 2px;
}

.mobile-card-actions {
  display: flex;
  gap: var(--space-sm);
  flex-wrap: wrap;
}

.btn-sm {
  font-size: var(--font-size-12px);
  padding: 6px 12px;
  min-height: 30px;
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
