<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { format } from 'date-fns'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { usePaymentAccount } from '../../composables/usePaymentAccount'
import { useRentalOwner } from '../../composables/useRentalOwner'
import { useRentToRent } from '../../composables/useRentToRent'

const router = useRouter()
const toast = useToast()
const {
  debts,
  bills,
  paymentHistory,
  selectedDebt,
  summary,
  loading,
  historyLoading,
  actionLoading,
  pagination,
  filters,
  billFilters,
  fetchDebts,
  fetchDebt,
  updateDebtAmount,
  fetchBills,
  fetchBill,
  generateBill,
  markSent,
  addPayment,
  requestVoid,
  openPdf,
  fetchPaymentHistory,
} = useRentToRent()
const { rentalOwners, fetchAll: fetchRentalOwners } = useRentalOwner()
const { accounts, fetchAll: fetchPaymentAccounts } = usePaymentAccount()

const activeTab = ref('debts')
const selectedRows = ref([])
const selectedBill = ref(null)
const showGenerateDialog = ref(false)
const showDebtDialog = ref(false)
const showPaymentDialog = ref(false)
const showVoidDialog = ref(false)
const detailAmountForm = ref({ amount_override: null })
const voidForm = ref({ bill: null, void_reason: '' })
const paymentForm = ref({
  payment_account_id: null,
  amount: null,
  paid_at: new Date(),
})

const debtStatusOptions = [
  { label: 'Semua', value: null },
  { label: 'Belum Dibuat Dokumen', value: 'open' },
  { label: 'Sudah Dibuat Dokumen', value: 'billed' },
  { label: 'Partial Paid', value: 'partial_paid' },
  { label: 'Paid', value: 'paid' },
]
const billStatusOptions = [
  { label: 'Semua', value: null },
  { label: 'Dibuat', value: 'generated' },
  { label: 'Terkirim', value: 'sent' },
  { label: 'Partial Paid', value: 'partial_paid' },
  { label: 'Paid', value: 'paid' },
  { label: 'Menunggu ACC Void', value: 'void_requested' },
  { label: 'Void', value: 'void' },
]

const eligibleRows = computed(() => selectedRows.value.filter(row => row.status === 'open' && !row.bill))
const selectedOwnerIds = computed(() => [...new Set(eligibleRows.value.map(row => row.rental_owner?.id).filter(Boolean))])
const canGenerateBill = computed(() => eligibleRows.value.length > 0 && selectedOwnerIds.value.length === 1 && selectedRows.value.length === eligibleRows.value.length)
const selectedTotal = computed(() => eligibleRows.value.reduce((sum, row) => sum + Number(row.total_amount || 0), 0))
const selectedBookingCodes = computed(() => eligibleRows.value.map(row => row.kode_booking).join(', '))
const selectedOwner = computed(() => eligibleRows.value[0]?.rental_owner || null)
const paymentAccountOptions = computed(() => accounts.value.map(account => ({
  label: `${account.nama_bank} - ${account.nomor_rekening} (${account.atas_nama})`,
  value: account.id,
})))
const ownerOptions = computed(() => [
  { id: null, nama: 'Semua Pemilik' },
  ...rentalOwners.value.filter(owner => owner.is_owner === false),
])
const debtGroups = computed(() => {
  const groups = new Map()
  debts.value.forEach((debt) => {
    const ownerId = debt.rental_owner?.id || 'unknown'
    if (!groups.has(ownerId)) {
      groups.set(ownerId, {
        owner: debt.rental_owner,
        rows: [],
        total_amount: 0,
        paid_amount: 0,
        remaining_amount: 0,
      })
    }
    const group = groups.get(ownerId)
    group.rows.push(debt)
    group.total_amount += Number(debt.total_amount || 0)
    group.paid_amount += Number(debt.paid_amount || 0)
    group.remaining_amount += Number(debt.remaining_amount || 0)
  })

  return [...groups.values()]
})

watch(selectedRows, (rows) => {
  if (!rows.length) return

  const firstOwnerId = rows[0]?.rental_owner?.id
  const sanitized = rows.filter(row => row.status === 'open' && !row.bill && row.rental_owner?.id === firstOwnerId)
  if (sanitized.length !== rows.length) {
    selectedRows.value = sanitized
    toast.add({
      severity: 'warn',
      summary: 'Pilihan disesuaikan',
      detail: 'Dokumen hanya bisa dibuat dari transaksi open milik satu pemilik rental.',
      life: 3500,
    })
  }
})

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

const toApiDate = (value) => {
  if (!value) return null
  return format(new Date(value), 'yyyy-MM-dd')
}

const statusSeverity = (status) => {
  if (status === 'paid') return 'success'
  if (status === 'partial_paid') return 'info'
  if (status === 'billed' || status === 'sent' || status === 'void_requested') return 'warn'
  if (status === 'cancelled' || status === 'void') return 'danger'
  return 'secondary'
}

const statusLabel = (status) => {
  if (status === 'open') return 'Belum Dokumen'
  if (status === 'billed') return 'Sudah Dokumen'
  if (status === 'generated') return 'Dibuat'
  if (status === 'sent') return 'Terkirim'
  if (status === 'partial_paid') return 'Partial Paid'
  if (status === 'paid') return 'Paid'
  if (status === 'void_requested') return 'Menunggu ACC Void'
  if (status === 'void') return 'Void'
  return status || '-'
}

const applyFilters = () => {
  selectedRows.value = []
  pagination.value.current_page = 1
  if (activeTab.value === 'debts') {
    fetchDebts(1)
    return
  }

  if (activeTab.value === 'bills') {
    fetchBills(1)
    return
  }

  fetchPaymentHistory()
}

const resetFilters = () => {
  filters.value = { search: '', rental_owner_id: null, status: null }
  billFilters.value = { rental_owner_id: null, status: null }
  applyFilters()
}

const onPage = (event) => {
  pagination.value.current_page = event.page + 1
  if (activeTab.value === 'debts') {
    fetchDebts(pagination.value.current_page)
    return
  }
  fetchBills(pagination.value.current_page)
}

const switchTab = (tab) => {
  activeTab.value = tab
  selectedRows.value = []
  pagination.value.current_page = 1
  if (tab === 'debts') {
    fetchDebts(1)
    return
  }
  if (tab === 'bills') {
    fetchBills(1)
    return
  }
  fetchPaymentHistory()
}

const openDebtDetail = async (debt) => {
  await fetchDebt(debt.id)
  detailAmountForm.value.amount_override = selectedDebt.value.amount_override ?? selectedDebt.value.default_amount ?? 0
  showDebtDialog.value = true
}

const submitDebtAmount = async () => {
  if (!selectedDebt.value?.id) return
  await updateDebtAmount(selectedDebt.value.id, detailAmountForm.value.amount_override)
  showDebtDialog.value = false
}

const resetDebtAmount = async () => {
  if (!selectedDebt.value?.id) return
  await updateDebtAmount(selectedDebt.value.id, null)
  showDebtDialog.value = false
}

const openGenerateDialog = () => {
  if (!canGenerateBill.value) return
  showGenerateDialog.value = true
}

const submitGenerateBill = async () => {
  if (!canGenerateBill.value) return
  await generateBill({ debt_ids: eligibleRows.value.map(row => row.id) })
  selectedRows.value = []
  showGenerateDialog.value = false
}

const openPaymentDialog = (bill) => {
  if (['void', 'void_requested'].includes(bill.status)) return
  selectedBill.value = bill
  paymentForm.value = {
    payment_account_id: paymentAccountOptions.value[0]?.value || null,
    amount: bill.remaining_amount || null,
    paid_at: new Date(),
  }
  showPaymentDialog.value = true
}

const openDirectPayment = async (debt) => {
  if (debt.remaining_amount <= 0 || ['cancelled', 'paid'].includes(debt.status)) return

  let bill = null
  if (debt.bill?.id) {
    bill = await fetchBill(debt.bill.id)
  } else {
    bill = await generateBill({ debt_ids: [debt.id] })
  }

  openPaymentDialog(bill)
}

const openPublicBill = (bill) => {
  if (!bill.public_path) return
  window.open(bill.public_path, '_blank', 'noopener,noreferrer')
}

const openVoidDialog = (bill) => {
  voidForm.value = {
    bill,
    void_reason: '',
  }
  showVoidDialog.value = true
}

const submitVoidRequest = async () => {
  if (!voidForm.value.bill?.id || !voidForm.value.void_reason?.trim()) return
  await requestVoid(voidForm.value.bill.id, { void_reason: voidForm.value.void_reason })
  showVoidDialog.value = false
  voidForm.value = { bill: null, void_reason: '' }
}

const submitPayment = async () => {
  if (!selectedBill.value?.id || !paymentForm.value.amount || !paymentForm.value.payment_account_id) return
  await addPayment(selectedBill.value.id, {
    payment_account_id: paymentForm.value.payment_account_id,
    amount: paymentForm.value.amount,
    paid_at: toApiDate(paymentForm.value.paid_at),
  })
  showPaymentDialog.value = false
  selectedBill.value = null
}

onMounted(async () => {
  await Promise.all([
    fetchDebts(1),
    fetchPaymentHistory(),
    fetchRentalOwners({ per_page: 200 }),
    fetchPaymentAccounts({ per_page: 100, is_active: true }),
  ])
})
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h1 class="text-h1">Rent to Rent</h1>
        <p class="text-secondary text-xs">Kelola hutang penggunaan unit milik rental lain dan pembayaran ke pemilik rental.</p>
      </div>
      <div class="header-actions" v-if="activeTab === 'debts'">
        <button class="btn-pill btn-primary" :disabled="!canGenerateBill || actionLoading" @click="openGenerateDialog">
          <i class="pi pi-file-plus"></i>
          Buat Dokumen Tagihan
        </button>
      </div>
    </div>

    <div class="tab-toggle-container">
      <div class="pill-toggle">
        <button class="toggle-item" :class="{ active: activeTab === 'debts' }" @click="switchTab('debts')">Transaksi</button>
        <button class="toggle-item" :class="{ active: activeTab === 'bills' }" @click="switchTab('bills')">Dokumen Tagihan</button>
        <button class="toggle-item" :class="{ active: activeTab === 'payments' }" @click="switchTab('payments')">Riwayat Pembayaran</button>
      </div>
    </div>

    <div v-if="activeTab !== 'payments'" class="filter-bar">
      <div class="filter-groups">
        <div v-if="activeTab === 'debts'" class="filter-group filter-group-wide">
          <label>Pencarian</label>
          <span class="filter-search">
            <i class="pi pi-search"></i>
            <InputText v-model="filters.search" placeholder="Kode, pelanggan, unit, tujuan..." class="w-full" @keyup.enter="applyFilters" />
          </span>
        </div>
        <div class="filter-group">
          <label>Pemilik Rental</label>
          <Dropdown
            v-if="activeTab === 'debts'"
            v-model="filters.rental_owner_id"
            :options="ownerOptions"
            optionLabel="nama"
            optionValue="id"
            placeholder="Semua Pemilik"
            filter
            class="w-full md:w-56"
          />
          <Dropdown
            v-else
            v-model="billFilters.rental_owner_id"
            :options="ownerOptions"
            optionLabel="nama"
            optionValue="id"
            placeholder="Semua Pemilik"
            filter
            class="w-full md:w-56"
          />
        </div>
        <div class="filter-group">
          <label>Status</label>
          <Dropdown
            v-if="activeTab === 'debts'"
            v-model="filters.status"
            :options="debtStatusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Semua"
            class="w-full md:w-52"
          />
          <Dropdown
            v-else
            v-model="billFilters.status"
            :options="billStatusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Semua"
            class="w-full md:w-52"
          />
        </div>
      </div>
      <div class="filter-actions">
        <button class="btn-pill btn-secondary btn-pill-compact" :disabled="loading" @click="resetFilters">
          <i class="pi pi-refresh"></i>
          Reset
        </button>
        <button class="btn-pill btn-primary btn-pill-compact" :disabled="loading" @click="applyFilters">
          <i class="pi pi-filter"></i>
          Filter
        </button>
      </div>
    </div>

    <div v-else class="filter-bar">
      <div class="filter-group">
        <label>Riwayat Pembayaran</label>
        <span class="text-secondary text-xs">Pembayaran terbaru dan group per dokumen tagihan.</span>
      </div>
      <button class="btn-pill btn-secondary btn-pill-compact" :disabled="historyLoading" @click="fetchPaymentHistory">
        <i class="pi pi-refresh"></i>
        Refresh
      </button>
    </div>

    <ProgressBar v-if="loading || historyLoading" mode="indeterminate" style="height: 4px" class="mb-4" />

    <div v-if="activeTab === 'debts'" class="summary-grid">
      <div class="summary-tile">
        <span>Total Rent to Rent</span>
        <strong>{{ formatCurrency(summary.total_amount) }}</strong>
      </div>
      <div class="summary-tile">
        <span>Sudah Bayar</span>
        <strong>{{ formatCurrency(summary.paid_amount) }}</strong>
      </div>
      <div class="summary-tile">
        <span>Sisa Hutang</span>
        <strong>{{ formatCurrency(summary.remaining_amount) }}</strong>
      </div>
      <div class="summary-tile">
        <span>Pemilik Rental</span>
        <strong>{{ summary.owner_count || 0 }}</strong>
      </div>
    </div>

    <section v-if="activeTab === 'debts'" class="debt-groups">
      <div v-for="group in debtGroups" :key="group.owner?.id || 'unknown'" class="owner-section">
        <div class="owner-section-head">
          <div>
            <h2>{{ group.owner?.nama || 'Tanpa Pemilik' }}</h2>
            <p>{{ group.owner?.bank || '-' }} <span v-if="group.owner?.no_rek">- {{ group.owner.no_rek }}</span></p>
          </div>
          <div class="owner-totals">
            <span>{{ formatCurrency(group.total_amount) }}</span>
            <small>Sisa {{ formatCurrency(group.remaining_amount) }}</small>
          </div>
        </div>

        <DataTable
          v-model:selection="selectedRows"
          :value="group.rows"
          dataKey="id"
          responsiveLayout="scroll"
          class="drent-datatable"
        >
          <Column selectionMode="multiple" headerStyle="width: 3rem" />
          <Column header="Kode Booking" style="min-width: 10rem">
            <template #body="{ data }">
              <button class="link-button" @click="router.push(`/bookings/${data.booking_id}`)">{{ data.kode_booking }}</button>
              <div class="mt-2"><Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" /></div>
            </template>
          </Column>
          <Column header="Total Rent to Rent" style="min-width: 13rem">
            <template #body="{ data }">
              <div class="amount-stack">
                <strong>{{ formatCurrency(data.total_amount) }}</strong>
                <span v-if="data.amount_override !== null">Manual override</span>
                <span v-else>Live dari master unit</span>
              </div>
            </template>
          </Column>
          <Column header="Sudah Bayar" style="min-width: 12rem">
            <template #body="{ data }">
              <div class="amount-stack">
                <strong>{{ formatCurrency(data.paid_amount) }}</strong>
                <span>Sisa {{ formatCurrency(data.remaining_amount) }}</span>
              </div>
            </template>
          </Column>
          <Column header="Unit" style="min-width: 14rem">
            <template #body="{ data }">
              <strong>{{ data.unit?.name || '-' }}</strong>
              <div class="text-secondary text-xs">{{ data.unit?.no_polisi || '-' }}</div>
              <div class="text-secondary text-xs">{{ data.unit?.lama_sewa || 0 }} {{ data.unit?.paket_sewa || 'harian' }}</div>
            </template>
          </Column>
          <Column header="Nama Pelanggan" style="min-width: 13rem">
            <template #body="{ data }">{{ data.booking?.customer_name || '-' }}</template>
          </Column>
          <Column header="Tujuan" style="min-width: 14rem">
            <template #body="{ data }">{{ data.booking?.tujuan || '-' }}</template>
          </Column>
          <Column header="Aksi" style="min-width: 11rem">
            <template #body="{ data }">
              <div class="table-actions">
                <button class="btn-pill btn-primary btn-pill-compact" :disabled="actionLoading || data.remaining_amount <= 0 || ['cancelled', 'paid'].includes(data.status)" @click="openDirectPayment(data)">
                  <i class="pi pi-wallet"></i>
                  Bayar
                </button>
                <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="openDebtDetail(data)">
                  <i class="pi pi-eye"></i>
                  Detail
                </button>
              </div>
            </template>
          </Column>
        </DataTable>
      </div>
      <div v-if="!debtGroups.length && !loading" class="empty-state">Belum ada hutang rent-to-rent sesuai filter.</div>
    </section>

    <DataTable
      v-if="activeTab === 'bills'"
      :value="bills"
      dataKey="id"
      lazy
      paginator
      :rows="pagination.per_page"
      :totalRecords="pagination.total"
      :loading="loading"
      @page="onPage"
      responsiveLayout="scroll"
      class="drent-datatable"
    >
      <Column header="Dokumen" style="min-width: 13rem">
        <template #body="{ data }">
          <strong>{{ data.bill_number }}</strong>
          <div class="mt-2"><Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" /></div>
        </template>
      </Column>
      <Column header="Pemilik Rental" style="min-width: 14rem">
        <template #body="{ data }">
          <strong>{{ data.rental_owner?.nama || '-' }}</strong>
          <div class="text-secondary text-xs">{{ data.rental_owner?.bank || '-' }} {{ data.rental_owner?.no_rek || '' }}</div>
        </template>
      </Column>
      <Column header="Transaksi" style="min-width: 16rem">
        <template #body="{ data }">
          <div class="booking-list">
            <span v-for="item in data.items" :key="item.id">{{ item.kode_booking }} - {{ item.unit_plate || '-' }}</span>
          </div>
        </template>
      </Column>
      <Column header="Total" style="min-width: 11rem">
        <template #body="{ data }"><div class="amount-stack">{{ formatCurrency(data.total_amount) }}</div></template>
      </Column>
      <Column header="Sudah Bayar" style="min-width: 11rem">
        <template #body="{ data }"><div class="amount-stack">{{ formatCurrency(data.paid_amount) }}</div></template>
      </Column>
      <Column header="Tanggal" style="min-width: 12rem">
        <template #body="{ data }">
          <div>{{ formatDate(data.generated_at) }}</div>
          <div class="text-secondary text-xs">Kirim {{ formatDateTime(data.sent_at) }}</div>
        </template>
      </Column>
      <Column header="Aksi" style="min-width: 15rem">
        <template #body="{ data }">
          <div class="table-actions">
            <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="markSent(data.id)">
              <i class="pi pi-send"></i>
              Kirim
            </button>
            <button class="btn-pill btn-primary btn-pill-compact" :disabled="data.remaining_amount <= 0 || ['void', 'void_requested'].includes(data.status)" @click="openPaymentDialog(data)">
              <i class="pi pi-wallet"></i>
              Bayar
            </button>
            <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="openPublicBill(data)">
              <i class="pi pi-external-link"></i>
              Public
            </button>
            <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="openPdf(data.id, `${data.bill_number}.pdf`)">
              <i class="pi pi-download"></i>
              PDF
            </button>
            <button class="btn-pill btn-danger btn-pill-compact" :disabled="actionLoading || ['void', 'void_requested'].includes(data.status)" @click="openVoidDialog(data)">
              <i class="pi pi-ban"></i>
              Void
            </button>
          </div>
        </template>
      </Column>
    </DataTable>

    <div v-if="activeTab === 'payments'" class="payment-history-stack">
      <section class="payment-section">
        <div class="section-heading">
          <h2>Pembayaran Terbaru</h2>
        </div>
        <DataTable :value="paymentHistory.latest" dataKey="id" responsiveLayout="scroll" class="drent-datatable">
          <Column header="Dokumen" style="min-width: 12rem">
            <template #body="{ data }">{{ data.bill_number || '-' }}</template>
          </Column>
          <Column header="Pemilik Rental" style="min-width: 14rem">
            <template #body="{ data }">{{ data.owner_name || '-' }}</template>
          </Column>
          <Column header="Booking" style="min-width: 16rem">
            <template #body="{ data }">{{ (data.booking_codes || []).join(', ') || '-' }}</template>
          </Column>
          <Column header="Akun" style="min-width: 13rem">
            <template #body="{ data }">{{ data.payment_account_name || '-' }}</template>
          </Column>
          <Column header="Tanggal" style="min-width: 12rem">
            <template #body="{ data }">{{ formatDateTime(data.paid_at) }}</template>
          </Column>
          <Column header="Nominal" style="min-width: 11rem">
            <template #body="{ data }"><div class="amount-stack">{{ formatCurrency(data.amount) }}</div></template>
          </Column>
        </DataTable>
      </section>

      <section class="payment-section">
        <div class="section-heading">
          <h2>Group per Dokumen</h2>
        </div>
        <DataTable :value="paymentHistory.groups" dataKey="bill_id" responsiveLayout="scroll" class="drent-datatable">
          <Column header="Dokumen" style="min-width: 12rem">
            <template #body="{ data }">{{ data.bill_number || '-' }}</template>
          </Column>
          <Column header="Pemilik Rental" style="min-width: 14rem">
            <template #body="{ data }">{{ data.owner_name || '-' }}</template>
          </Column>
          <Column header="Booking" style="min-width: 16rem">
            <template #body="{ data }">{{ (data.booking_codes || []).join(', ') || '-' }}</template>
          </Column>
          <Column header="Pembayaran" style="min-width: 10rem">
            <template #body="{ data }">{{ data.payment_count }} pembayaran</template>
          </Column>
          <Column header="Terakhir Bayar" style="min-width: 12rem">
            <template #body="{ data }">{{ formatDateTime(data.latest_paid_at) }}</template>
          </Column>
          <Column header="Total Terbayar" style="min-width: 12rem">
            <template #body="{ data }"><div class="amount-stack">{{ formatCurrency(data.total_amount) }}</div></template>
          </Column>
        </DataTable>
      </section>
    </div>

    <Dialog v-model:visible="showGenerateDialog" header="Buat Dokumen Tagihan" modal :style="{ width: '480px' }" class="custom-dialog">
      <div class="dialog-stack">
        <div class="app-muted-panel">
          <div class="summary-row"><span>Pemilik rental</span><strong>{{ selectedOwner?.nama || '-' }}</strong></div>
          <div class="summary-row"><span>Jumlah transaksi</span><strong>{{ eligibleRows.length }}</strong></div>
          <div class="summary-row"><span>Booking</span><strong>{{ selectedBookingCodes || '-' }}</strong></div>
          <div class="summary-row"><span>Total tagihan</span><strong>{{ formatCurrency(selectedTotal) }}</strong></div>
        </div>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showGenerateDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading" @click="submitGenerateBill">Buat Dokumen</button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showDebtDialog" header="Detail Rent to Rent" modal :style="{ width: 'min(980px, 96vw)' }" class="custom-dialog">
      <div v-if="selectedDebt" class="detail-modal">
        <section class="detail-main">
          <div class="detail-top">
            <div>
              <span class="section-label">PEMILIK RENTAL</span>
              <h2>{{ selectedDebt.rental_owner?.nama || '-' }}</h2>
              <p>{{ selectedDebt.rental_owner?.kontak_1 || '-' }}</p>
            </div>
            <Tag :value="statusLabel(selectedDebt.status)" :severity="statusSeverity(selectedDebt.status)" />
          </div>
          <div class="info-grid">
            <div><span>Bank</span><strong>{{ selectedDebt.rental_owner?.bank || '-' }}</strong></div>
            <div><span>No. Rekening</span><strong>{{ selectedDebt.rental_owner?.no_rek || '-' }}</strong></div>
            <div><span>Atas Nama</span><strong>{{ selectedDebt.rental_owner?.atas_nama || '-' }}</strong></div>
          </div>
          <div class="info-grid">
            <div><span>Booking</span><strong>{{ selectedDebt.kode_booking }}</strong></div>
            <div><span>Pelanggan</span><strong>{{ selectedDebt.booking?.customer_name || '-' }}</strong></div>
            <div><span>Tujuan</span><strong>{{ selectedDebt.booking?.tujuan || '-' }}</strong></div>
          </div>
          <div class="info-grid">
            <div><span>Unit</span><strong>{{ selectedDebt.unit?.name || '-' }}</strong></div>
            <div><span>Nopol</span><strong>{{ selectedDebt.unit?.no_polisi || '-' }}</strong></div>
            <div><span>Periode</span><strong>{{ formatDate(selectedDebt.unit?.tgl_sewa) }} - {{ formatDate(selectedDebt.unit?.tgl_kembali) }}</strong></div>
          </div>
          <section class="history-panel">
            <div class="section-heading"><h2>Riwayat Bayar</h2></div>
            <DataTable :value="selectedDebt.payments" dataKey="id" responsiveLayout="scroll" class="mini-table">
              <Column header="Dokumen">
                <template #body="{ data }">{{ data.bill_number || '-' }}</template>
              </Column>
              <Column header="Akun">
                <template #body="{ data }">{{ data.payment_account_name || '-' }}</template>
              </Column>
              <Column header="Tanggal">
                <template #body="{ data }">{{ formatDateTime(data.paid_at) }}</template>
              </Column>
              <Column header="Nominal">
                <template #body="{ data }">{{ formatCurrency(data.amount) }}</template>
              </Column>
            </DataTable>
          </section>
        </section>
        <aside class="detail-side">
          <div class="payment-total-panel">
            <div class="payment-total-row"><span>Default Live</span><strong>{{ formatCurrency(selectedDebt.default_amount) }}</strong></div>
            <div class="payment-total-row"><span>Total Tagihan</span><strong>{{ formatCurrency(selectedDebt.total_amount) }}</strong></div>
            <div class="payment-total-row"><span>Sudah Bayar</span><strong>{{ formatCurrency(selectedDebt.paid_amount) }}</strong></div>
            <div class="payment-total-row grand"><span>Sisa</span><strong>{{ formatCurrency(selectedDebt.remaining_amount) }}</strong></div>
          </div>
          <div class="payment-form-panel">
            <div class="section-label">Edit Harga Modal</div>
            <fieldset class="form-fieldset">
              <label>Nominal Manual</label>
              <InputNumber v-model="detailAmountForm.amount_override" mode="currency" currency="IDR" locale="id-ID" :min="0" class="w-full" :disabled="!selectedDebt.can_edit_amount" />
              <span class="field-hint">Kosongkan override dengan tombol reset untuk kembali mengikuti master unit.</span>
            </fieldset>
            <div class="table-actions">
              <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading || !selectedDebt.can_edit_amount" @click="resetDebtAmount">Reset Live</button>
              <button class="btn-pill btn-primary btn-pill-compact" :disabled="actionLoading || !selectedDebt.can_edit_amount" @click="submitDebtAmount">Simpan</button>
            </div>
          </div>
        </aside>
      </div>
    </Dialog>

    <Dialog v-model:visible="showPaymentDialog" header="Pembayaran Rent to Rent" modal :style="{ width: 'min(980px, 96vw)' }" class="custom-dialog">
      <div v-if="selectedBill" class="payment-modal">
        <section class="detail-main">
          <div class="detail-top">
            <div>
              <span class="section-label">DOKUMEN TAGIHAN</span>
              <h2>{{ selectedBill.bill_number }}</h2>
              <p>{{ selectedBill.rental_owner?.nama || '-' }}</p>
            </div>
            <Tag :value="statusLabel(selectedBill.status)" :severity="statusSeverity(selectedBill.status)" />
          </div>
          <DataTable :value="selectedBill.items" dataKey="id" responsiveLayout="scroll" class="mini-table">
            <Column header="Booking">
              <template #body="{ data }">{{ data.kode_booking }}</template>
            </Column>
            <Column header="Unit">
              <template #body="{ data }">{{ data.unit_name }} ({{ data.unit_plate || '-' }})</template>
            </Column>
            <Column header="Pelanggan">
              <template #body="{ data }">{{ data.customer_name || '-' }}</template>
            </Column>
            <Column header="Nominal">
              <template #body="{ data }">{{ formatCurrency(data.amount) }}</template>
            </Column>
          </DataTable>
        </section>
        <aside class="detail-side">
          <div class="payment-total-panel">
            <div class="payment-total-row"><span>Total</span><strong>{{ formatCurrency(selectedBill.total_amount) }}</strong></div>
            <div class="payment-total-row"><span>Sudah Bayar</span><strong>{{ formatCurrency(selectedBill.paid_amount) }}</strong></div>
            <div class="payment-total-row grand"><span>Sisa</span><strong>{{ formatCurrency(selectedBill.remaining_amount) }}</strong></div>
          </div>
          <div class="payment-form-panel">
            <div class="section-label">Catat Pembayaran</div>
            <fieldset class="form-fieldset">
              <label>Akun Pembayaran</label>
              <Dropdown v-model="paymentForm.payment_account_id" :options="paymentAccountOptions" optionLabel="label" optionValue="value" placeholder="Pilih akun" class="w-full" />
            </fieldset>
            <fieldset class="form-fieldset">
              <label>Nominal</label>
              <InputNumber v-model="paymentForm.amount" mode="currency" currency="IDR" locale="id-ID" :min="1" :max="selectedBill.remaining_amount" class="w-full" />
            </fieldset>
            <fieldset class="form-fieldset">
              <label>Tanggal Bayar</label>
              <DatePicker v-model="paymentForm.paid_at" dateFormat="dd M yy" class="w-full" showIcon />
            </fieldset>
          </div>
        </aside>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showPaymentDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || !paymentForm.amount || !paymentForm.payment_account_id" @click="submitPayment">Simpan Pembayaran</button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showVoidDialog" header="Ajukan Void Tagihan" modal :style="{ width: '480px' }" class="custom-dialog">
      <div class="dialog-stack">
        <div class="app-muted-panel">
          <div class="summary-row"><span>Dokumen</span><strong>{{ voidForm.bill?.bill_number || '-' }}</strong></div>
          <div class="summary-row"><span>Pemilik rental</span><strong>{{ voidForm.bill?.rental_owner?.nama || '-' }}</strong></div>
          <div class="summary-row"><span>Total tagihan</span><strong>{{ formatCurrency(voidForm.bill?.total_amount) }}</strong></div>
          <div class="summary-row"><span>Sudah bayar</span><strong>{{ formatCurrency(voidForm.bill?.paid_amount) }}</strong></div>
        </div>
        <fieldset class="form-fieldset">
          <label>Alasan Void</label>
          <Textarea v-model="voidForm.void_reason" rows="4" class="w-full" placeholder="Tuliskan alasan untuk ACC supervisor" />
          <span class="field-hint">Jika disetujui, semua payment di dokumen ini ikut void dan transaksi kembali terbuka.</span>
        </fieldset>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showVoidDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || !voidForm.void_reason?.trim()" @click="submitVoidRequest">Ajukan Void</button>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page-container {
  padding: var(--space-2xl);
}

.page-header,
.owner-section-head,
.section-heading,
.header-actions,
.filter-actions,
.table-actions {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-md);
}

.page-header {
  margin-bottom: var(--space-2xl);
}

.header-actions,
.filter-actions,
.table-actions {
  align-items: center;
  flex-wrap: wrap;
}

.tab-toggle-container {
  margin-bottom: var(--space-xl);
}

.pill-toggle {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 4px;
  padding: 4px;
  border-radius: var(--radius-full);
  background: var(--card-bg);
}

.toggle-item {
  min-height: 32px;
  padding: 7px 16px;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
}

.toggle-item.active {
  background: var(--text-primary);
  color: #fff;
}

.filter-bar,
.summary-tile,
.owner-section,
.app-muted-panel,
.payment-total-panel,
.payment-form-panel,
.history-panel,
.form-fieldset {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.filter-bar {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-lg);
  padding: var(--space-md);
  margin-bottom: var(--space-lg);
  flex-wrap: wrap;
}

.filter-groups {
  display: flex;
  gap: var(--space-lg);
  align-items: flex-end;
  flex-wrap: wrap;
  flex: 1 1 auto;
}

.filter-group,
.form-fieldset,
.dialog-stack,
.booking-list,
.debt-groups,
.payment-history-stack,
.payment-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.filter-group label,
.form-fieldset label {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

.filter-group-wide {
  min-width: min(320px, 100%);
}

.filter-search {
  position: relative;
  display: block;
}

.filter-search > i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-tertiary);
}

.filter-search :deep(.p-inputtext) {
  padding-left: 34px;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-md);
  margin-bottom: var(--space-lg);
}

.summary-tile {
  padding: var(--space-md);
}

.summary-tile span,
.owner-section-head p,
.owner-totals small,
.amount-stack span,
.field-hint,
.section-label {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

.summary-tile strong {
  display: block;
  margin-top: 6px;
  color: var(--text-primary);
  font-size: 18px;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
}

.owner-section {
  overflow: hidden;
}

.owner-section-head {
  align-items: center;
  padding: var(--space-md);
  border-bottom: 1px solid var(--surface-border);
}

.owner-section-head h2,
.section-heading h2,
.detail-top h2 {
  margin: 0;
  color: var(--text-primary);
  font-size: 16px;
  font-weight: 900;
}

.owner-section-head p,
.detail-top p {
  margin: 3px 0 0;
}

.owner-totals {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
  font-variant-numeric: tabular-nums;
}

.owner-totals span {
  color: var(--text-primary);
  font-size: 15px;
  font-weight: 900;
}

.drent-datatable,
.mini-table {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  overflow: hidden;
  background: var(--surface-default);
}

.owner-section .drent-datatable {
  border: 0;
  border-radius: 0;
}

:deep(.drent-datatable .p-datatable-thead > tr > th),
:deep(.mini-table .p-datatable-thead > tr > th) {
  text-align: center;
}

:deep(.drent-datatable .p-datatable-thead > tr > th .p-column-header-content),
:deep(.mini-table .p-datatable-thead > tr > th .p-column-header-content) {
  justify-content: center;
}

.link-button {
  border: none;
  background: transparent;
  color: var(--text-primary);
  cursor: pointer;
  font-weight: 800;
  padding: 0;
}

.amount-stack {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  font-variant-numeric: tabular-nums;
}

.btn-danger {
  border-color: #dc2626;
  background: #dc2626;
  color: #fff;
}

.empty-state {
  padding: var(--space-xl);
  border: 1px dashed var(--surface-border);
  border-radius: var(--radius-default);
  color: var(--text-secondary);
  text-align: center;
  font-size: 12px;
  font-weight: 700;
}

.app-muted-panel,
.payment-total-panel,
.payment-form-panel,
.history-panel,
.form-fieldset {
  background: var(--card-bg);
  padding: var(--space-md);
  box-shadow: none;
}

.summary-row,
.payment-total-row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-md);
}

.detail-modal,
.payment-modal {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: var(--space-lg);
  max-height: min(74vh, 760px);
  overflow: hidden;
}

.detail-main,
.detail-side {
  min-width: 0;
  overflow: auto;
}

.detail-main,
.detail-side {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.detail-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-lg);
  padding: var(--space-md);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1px;
  overflow: hidden;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-border);
}

.info-grid > div {
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: var(--surface-default);
  padding: var(--space-md);
}

.info-grid span {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

.info-grid strong {
  color: var(--text-primary);
  font-size: 12px;
}

.payment-total-row {
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid var(--surface-border);
  font-size: 12px;
}

.payment-total-row.grand {
  margin-top: 6px;
  border-bottom: 0;
  color: #fff;
  background: #E5534B;
  border-radius: var(--radius-xs);
  padding: 12px;
}

:deep(.custom-dialog .p-dialog-footer) {
  border-top: 1px solid var(--surface-border);
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

@media (max-width: 768px) {
  .page-container {
    padding: var(--space-lg);
  }

  .page-header,
  .filter-bar,
  .filter-groups,
  .filter-actions,
  .owner-section-head {
    flex-direction: column;
    align-items: stretch;
  }

  .summary-grid,
  .detail-modal,
  .payment-modal,
  .info-grid {
    grid-template-columns: 1fr;
  }

  .detail-modal,
  .payment-modal {
    max-height: 78vh;
    overflow: auto;
  }

  .detail-main,
  .detail-side {
    overflow: visible;
  }

  .header-actions .btn-pill,
  .filter-actions .btn-pill,
  .table-actions .btn-pill {
    width: 100%;
    justify-content: center;
  }
}
</style>
