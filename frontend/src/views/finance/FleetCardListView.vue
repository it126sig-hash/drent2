<script setup>
import { computed, onMounted, ref } from 'vue'
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
import { useCostType } from '../../composables/useCostType'
import { useFleetCard } from '../../composables/useFleetCard'
import { usePaymentAccount } from '../../composables/usePaymentAccount'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const canManage = computed(() => authStore.hasPermission('finance.fleet_card.create'))
const canReview = computed(() => authStore.hasPermission('finance.fleet_card.update'))

const {
  cards,
  bookingDetails,
  history,
  selectedFund,
  loading,
  actionLoading,
  pagination,
  filters,
  historyFilters,
  fetchCards,
  storeCard,
  updateCardStatus,
  fetchBookingDetails,
  fetchFund,
  storeFund,
  closeFund,
  fetchHistory,
  acceptFund,
  submitExpense,
  approveExpense,
  rejectExpense,
  openExpensePhoto,
  voidFund,
} = useFleetCard()

const { costTypes, fetchAll: fetchCostTypes } = useCostType()
const { accounts, fetchAll: fetchPaymentAccounts } = usePaymentAccount()

const activeTab = ref('cards')
const tabOptions = [
  { label: 'Kartu', value: 'cards' },
  { label: 'Assign ke Booking', value: 'assign' },
  { label: 'Histori', value: 'history' },
]

const switchTab = (tab) => {
  activeTab.value = tab
  if (tab === 'cards') fetchCards(1)
  if (tab === 'assign') fetchBookingDetails(1)
  if (tab === 'history') fetchHistory(1)
}

const formatMoney = (value) => `Rp ${Number(value || 0).toLocaleString('id-ID')}`
const formatDateTime = (value) => (value ? format(new Date(value), 'dd MMM yyyy HH:mm') : '-')

const detailTypeLabel = (type) => ({ initial: 'Initial', extend: 'Extend', rolling: 'Rolling' }[type] || type)
const statusLabel = (status) => ({
  pending_driver_acceptance: 'Menunggu Driver',
  accepted: 'Diterima',
  closed: 'Ditutup',
  cancelled: 'Dibatalkan',
  void_requested: 'Menunggu Void',
}[status] || status)

// --- Card management ---
const showCardDialog = ref(false)
const cardForm = ref({ code: '', label: '', notes: '' })
const openCardDialog = () => {
  cardForm.value = { code: '', label: '', notes: '' }
  showCardDialog.value = true
}
const submitCard = async () => {
  await storeCard(cardForm.value)
  showCardDialog.value = false
}

const cardStatusOptions = [
  { label: 'Aktif', value: 'active' },
  { label: 'Nonaktif', value: 'inactive' },
  { label: 'Hilang', value: 'lost' },
]
const changeCardStatus = async (card, status) => {
  if (status === card.status) return
  await updateCardStatus(card.id, status)
}

// --- Assign fleet card to booking detail ---
const showFundDialog = ref(false)
const fundTargetDetail = ref(null)
const fundForm = ref({
  fleet_card_id: null,
  payment_account_id: null,
  amount: 0,
  paid_at: new Date(),
  recipient_destination: '',
  notes: '',
  items: [{ cost_type_id: null, label: '', planned_amount: 0, notes: '' }],
})

const activeCardOptions = computed(() =>
  cards.value
    .filter((card) => card.status === 'active' && !card.active_fund)
    .map((card) => ({ label: `${card.code}${card.label ? ' - ' + card.label : ''} (Saldo: ${formatMoney(card.saldo)})`, value: card.id, saldo: card.saldo }))
)

const selectedCardSaldo = computed(() => {
  const card = cards.value.find((c) => c.id === fundForm.value.fleet_card_id)
  return card ? Number(card.saldo) : 0
})

const fundItemTotal = computed(() =>
  fundForm.value.items.reduce((sum, item) => sum + (Number(item.planned_amount) || 0), 0)
)

const fundAvailableTotal = computed(() => selectedCardSaldo.value + (Number(fundForm.value.amount) || 0))

const openFundDialog = (detail) => {
  fundTargetDetail.value = detail
  fundForm.value = {
    fleet_card_id: null,
    payment_account_id: null,
    amount: 0,
    paid_at: new Date(),
    recipient_destination: `Operasional ${detail.booking?.kode_booking || ''}`,
    notes: '',
    items: [{ cost_type_id: null, label: '', planned_amount: 0, notes: '' }],
  }
  showFundDialog.value = true
}

const addFundItem = () => fundForm.value.items.push({ cost_type_id: null, label: '', planned_amount: 0, notes: '' })
const removeFundItem = (idx) => fundForm.value.items.splice(idx, 1)
const onItemCostTypeChange = (idx) => {
  const costType = costTypes.value.find((c) => c.id === fundForm.value.items[idx].cost_type_id)
  if (costType && !fundForm.value.items[idx].label) {
    fundForm.value.items[idx].label = costType.nama
  }
}

const submitFund = async () => {
  await storeFund(fundTargetDetail.value.id, {
    ...fundForm.value,
    paid_at: fundForm.value.paid_at ? format(new Date(fundForm.value.paid_at), 'yyyy-MM-dd') : null,
  })
  showFundDialog.value = false
}

// --- Fund detail dialog (accept/close/expense) ---
const showDetailDialog = ref(false)
const detailLoadingFundId = ref(null)
const openFundDetail = async (fund) => {
  detailLoadingFundId.value = fund.id
  try {
    await fetchFund(fund.id)
    showDetailDialog.value = true
  } finally {
    detailLoadingFundId.value = null
  }
}

const handleAcceptFund = async () => {
  await acceptFund(selectedFund.value.id)
  await fetchFund(selectedFund.value.id)
  await fetchBookingDetails(pagination.value.current_page)
}

const showCloseDialog = ref(false)
const closeNote = ref('')
const handleCloseFund = async () => {
  await closeFund(selectedFund.value.id, closeNote.value)
  showCloseDialog.value = false
  showDetailDialog.value = false
  await fetchBookingDetails(pagination.value.current_page)
}

const showVoidDialog = ref(false)
const voidReason = ref('')
const handleVoidFund = async () => {
  await voidFund(selectedFund.value.id, voidReason.value)
  showVoidDialog.value = false
  showDetailDialog.value = false
  await fetchBookingDetails(pagination.value.current_page)
}

const showExpenseDialog = ref(false)
const expenseForm = ref({ type: 'expense', cost_type_id: null, amount: 0, description: '', photo: null })
const openExpenseDialog = () => {
  expenseForm.value = { type: 'expense', cost_type_id: null, amount: 0, description: '', photo: null }
  showExpenseDialog.value = true
}
const onExpensePhotoChange = (e) => {
  expenseForm.value.photo = e.target.files?.[0] || null
}
const submitFundExpense = async () => {
  await submitExpense(selectedFund.value.id, expenseForm.value)
  showExpenseDialog.value = false
  await fetchFund(selectedFund.value.id)
}

const showRejectDialog = ref(false)
const rejectTargetExpense = ref(null)
const rejectReason = ref('')
const openRejectDialog = (expense) => {
  rejectTargetExpense.value = expense
  rejectReason.value = ''
  showRejectDialog.value = true
}
const handleApproveExpense = async (expense) => {
  await approveExpense(expense.id)
  await fetchFund(selectedFund.value.id)
}
const handleRejectExpense = async () => {
  await rejectExpense(rejectTargetExpense.value.id, rejectReason.value)
  showRejectDialog.value = false
  await fetchFund(selectedFund.value.id)
}

const applyFilters = () => {
  if (activeTab.value === 'assign') fetchBookingDetails(1)
  if (activeTab.value === 'history') fetchHistory(1)
}

const onPage = (event) => {
  const page = event.page + 1
  if (activeTab.value === 'cards') fetchCards(page)
  if (activeTab.value === 'assign') fetchBookingDetails(page)
  if (activeTab.value === 'history') fetchHistory(page)
}

onMounted(async () => {
  await Promise.all([
    fetchCards(1),
    fetchCostTypes({ per_page: 100, is_active: true }),
    fetchPaymentAccounts({ per_page: 100, is_active: true }),
  ])
})
</script>

<template>
  <div class="page-container fleet-card-page table-page-active">
    <div class="page-header">
      <div class="header-left">
        <h1 class="text-h1">Kartu Fleet</h1>
        <p class="text-secondary text-xs">Kelola kartu fleet, pasang ke booking, dan review bon driver.</p>
      </div>
      <div class="header-actions">
        <div class="pill-toggle">
          <button v-for="tab in tabOptions" :key="tab.value" class="toggle-item" :class="{ active: activeTab === tab.value }" @click="switchTab(tab.value)">
            {{ tab.label }}
          </button>
        </div>
        <button v-if="canManage && activeTab === 'cards'" class="btn-pill btn-primary btn-pill-compact" @click="openCardDialog">
          <i class="pi pi-plus"></i>
          Tambah Kartu
        </button>
      </div>
    </div>

    <div v-if="activeTab !== 'cards'" class="filter-bar surface-card">
      <div class="filter-groups">
        <div class="filter-group filter-group-wide">
          <label>Pencarian</label>
          <span class="filter-search">
            <i class="pi pi-search"></i>
            <InputText v-if="activeTab === 'history'" v-model="historyFilters.search" placeholder="Kode booking, driver, kartu..." class="w-full" @keyup.enter="applyFilters" />
            <InputText v-else v-model="filters.search" placeholder="Kode booking, pelanggan..." class="w-full" @keyup.enter="applyFilters" />
          </span>
        </div>
        <div v-if="activeTab === 'history'" class="filter-group">
          <label>Mulai</label>
          <DatePicker v-model="historyFilters.date_from" dateFormat="yy-mm-dd" placeholder="Dari" class="w-full md:w-36" />
        </div>
        <div v-if="activeTab === 'history'" class="filter-group">
          <label>Sampai</label>
          <DatePicker v-model="historyFilters.date_to" dateFormat="yy-mm-dd" placeholder="Sampai" class="w-full md:w-36" />
        </div>
      </div>
      <div class="filter-actions">
        <button class="btn-pill btn-primary btn-pill-compact" :disabled="loading" @click="applyFilters">
          <i class="pi pi-filter"></i>
          Filter
        </button>
      </div>
    </div>

    <ProgressBar v-if="loading || detailLoadingFundId" mode="indeterminate" style="height: 4px" class="mb-4" />

    <!-- Tab: Kartu -->
    <div v-if="activeTab === 'cards'" class="table-shell">
      <DataTable :value="cards" lazy paginator :rows="pagination.per_page" :totalRecords="pagination.total" :loading="loading" @page="onPage" scrollable scrollHeight="flex" class="drent-datatable">
        <Column field="code" header="Kode Kartu" style="min-width: 8rem" />
        <Column field="label" header="Label" style="min-width: 10rem">
          <template #body="{ data }">{{ data.label || '-' }}</template>
        </Column>
        <Column field="saldo" header="Saldo" style="min-width: 8rem">
          <template #body="{ data }">{{ formatMoney(data.saldo) }}</template>
        </Column>
        <Column field="status" header="Status" style="min-width: 8rem">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="data.status === 'active' ? 'success' : data.status === 'lost' ? 'danger' : 'secondary'" />
          </template>
        </Column>
        <Column header="Sedang Dipinjam" style="min-width: 10rem">
          <template #body="{ data }">
            <span v-if="data.active_fund">{{ data.active_fund.booking_code }} - {{ data.active_fund.driver_name }}</span>
            <span v-else class="text-secondary">-</span>
          </template>
        </Column>
        <Column v-if="canReview" header="Aksi" style="min-width: 10rem">
          <template #body="{ data }">
            <Dropdown :modelValue="data.status" :options="cardStatusOptions" optionLabel="label" optionValue="value" class="w-full" :disabled="actionLoading || !!data.active_fund" @update:modelValue="(val) => changeCardStatus(data, val)" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Tab: Assign ke Booking -->
    <div v-if="activeTab === 'assign'" class="table-shell">
      <DataTable :value="bookingDetails" lazy paginator :rows="pagination.per_page" :totalRecords="pagination.total" :loading="loading" @page="onPage" scrollable scrollHeight="flex" class="drent-datatable">
        <Column header="Booking" style="min-width: 10rem">
          <template #body="{ data }">
            <div class="text-xs text-secondary">{{ data.booking?.kode_booking }}</div>
            <div class="text-xs">{{ data.booking?.customer?.nama || '-' }}</div>
          </template>
        </Column>
        <Column header="Periode" style="min-width: 12rem">
          <template #body="{ data }">
            <div class="detail-line">
              <Tag :value="detailTypeLabel(data.detail_type)" :severity="data.detail_type === 'initial' ? 'secondary' : 'info'" />
              <span>{{ formatDateTime(data.tgl_sewa) }} - {{ formatDateTime(data.tgl_kembali) }}</span>
            </div>
          </template>
        </Column>
        <Column header="Driver" style="min-width: 8rem">
          <template #body="{ data }">{{ data.driver?.nama || '-' }}</template>
        </Column>
        <Column header="Ringkasan Kartu" style="min-width: 12rem">
          <template #body="{ data }">
            <div class="text-xs">Top-up: {{ formatMoney(data.summary.topup_total) }}</div>
            <div class="text-xs">Bon: {{ formatMoney(data.summary.approved_expense_total) }}</div>
            <div v-if="data.summary.pending_review_count" class="text-xs text-warning">{{ data.summary.pending_review_count }} bon menunggu review</div>
          </template>
        </Column>
        <Column header="Aksi" style="min-width: 10rem">
          <template #body="{ data }">
            <button v-if="data.summary.active_fund_count > 0" class="btn-pill btn-secondary btn-pill-compact" @click="openFundDetail(data.fleet_card_funds.find((f) => ['pending_driver_acceptance', 'accepted'].includes(f.status)))">
              Lihat Kartu
            </button>
            <button v-else-if="canManage" class="btn-pill btn-primary btn-pill-compact" @click="openFundDialog(data)">
              Pasang Kartu
            </button>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Tab: Histori -->
    <div v-if="activeTab === 'history'" class="table-shell">
      <DataTable :value="history" lazy paginator :rows="pagination.per_page" :totalRecords="pagination.total" :loading="loading" @page="onPage" scrollable scrollHeight="flex" class="drent-datatable">
        <Column header="Tanggal" style="min-width: 8rem">
          <template #body="{ data }">{{ formatDateTime(data.happened_at) }}</template>
        </Column>
        <Column field="label" header="Jenis" style="min-width: 10rem" />
        <Column header="Booking" style="min-width: 8rem">
          <template #body="{ data }">{{ data.booking_code }}</template>
        </Column>
        <Column header="Kartu" style="min-width: 6rem">
          <template #body="{ data }">{{ data.fleet_card_code }}</template>
        </Column>
        <Column header="Jumlah" style="min-width: 8rem">
          <template #body="{ data }">
            <span :class="data.direction === 'out' ? 'text-danger' : 'text-success'">
              {{ data.direction === 'out' ? '-' : '+' }}{{ formatMoney(data.amount) }}
            </span>
          </template>
        </Column>
        <Column field="status" header="Status" style="min-width: 8rem">
          <template #body="{ data }">{{ statusLabel(data.status) }}</template>
        </Column>
        <Column field="description" header="Keterangan" style="min-width: 12rem" />
      </DataTable>
    </div>

    <!-- Dialog: Tambah Kartu -->
    <Dialog v-model:visible="showCardDialog" header="Tambah Kartu Fleet" modal style="width: 28rem">
      <div class="dialog-form">
        <label>Kode Kartu</label>
        <InputText v-model="cardForm.code" class="w-full" />
        <label>Label</label>
        <InputText v-model="cardForm.label" class="w-full" />
        <label>Catatan</label>
        <Textarea v-model="cardForm.notes" class="w-full" rows="2" />
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showCardDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || !cardForm.code" @click="submitCard">Simpan</button>
      </template>
    </Dialog>

    <!-- Dialog: Pasang Kartu ke Booking -->
    <Dialog v-model:visible="showFundDialog" header="Pasang Kartu Fleet" modal style="width: 36rem">
      <div class="dialog-form">
        <label>Kartu Fleet</label>
        <Dropdown v-model="fundForm.fleet_card_id" :options="activeCardOptions" optionLabel="label" optionValue="value" placeholder="Pilih kartu" class="w-full" />
        <label>Top-up Baru (boleh 0 jika hanya pakai saldo sisa kartu)</label>
        <InputNumber v-model="fundForm.amount" class="w-full" :min="0" mode="currency" currency="IDR" locale="id-ID" />
        <template v-if="fundForm.amount > 0">
          <label>Rekening Sumber</label>
          <Dropdown v-model="fundForm.payment_account_id" :options="accounts" optionLabel="nama_bank" optionValue="id" placeholder="Pilih rekening" class="w-full" />
        </template>
        <label>Tanggal</label>
        <DatePicker v-model="fundForm.paid_at" dateFormat="yy-mm-dd" class="w-full" />
        <label>Tujuan / Penerima</label>
        <InputText v-model="fundForm.recipient_destination" class="w-full" />
        <label>Catatan</label>
        <Textarea v-model="fundForm.notes" class="w-full" rows="2" />

        <div class="flex items-center justify-between mt-2">
          <strong>Breakdown Biaya</strong>
          <span class="text-xs">Saldo kartu sebelumnya: {{ formatMoney(selectedCardSaldo) }} | Total tersedia: {{ formatMoney(fundAvailableTotal) }}</span>
        </div>
        <div v-for="(item, idx) in fundForm.items" :key="idx" class="fund-item-row">
          <Dropdown v-model="item.cost_type_id" :options="costTypes" optionLabel="nama" optionValue="id" placeholder="Jenis biaya" class="w-1/3" @update:modelValue="onItemCostTypeChange(idx)" />
          <InputText v-model="item.label" placeholder="Label" class="w-1/3" />
          <InputNumber v-model="item.planned_amount" placeholder="Jumlah" class="w-1/4" mode="currency" currency="IDR" locale="id-ID" />
          <button class="btn-pill btn-secondary btn-pill-compact" @click="removeFundItem(idx)">
            <i class="pi pi-trash"></i>
          </button>
        </div>
        <button class="btn-pill btn-secondary btn-pill-compact" @click="addFundItem">
          <i class="pi pi-plus"></i>
          Tambah Item
        </button>
        <p v-if="fundItemTotal !== fundAvailableTotal" class="text-danger text-xs">
          Total breakdown ({{ formatMoney(fundItemTotal) }}) harus sama dengan total tersedia ({{ formatMoney(fundAvailableTotal) }}).
        </p>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showFundDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || !fundForm.fleet_card_id || fundItemTotal !== fundAvailableTotal || !fundForm.recipient_destination" @click="submitFund">
          Pasang Kartu
        </button>
      </template>
    </Dialog>

    <!-- Dialog: Detail Kartu / Fund -->
    <Dialog v-model:visible="showDetailDialog" header="Detail Kartu Fleet" modal style="width: 40rem">
      <div v-if="selectedFund" class="dialog-form">
        <div class="flex items-center justify-between">
          <strong>{{ selectedFund.fleet_card?.code }}</strong>
          <Tag :value="statusLabel(selectedFund.status)" />
        </div>
        <p class="text-xs text-secondary">Booking: {{ selectedFund.booking?.kode_booking }} | Driver: {{ selectedFund.driver?.nama }}</p>
        <p class="text-xs">Saldo kartu saat ini: {{ formatMoney(selectedFund.summary.card_remaining_balance) }}</p>

        <div class="flex gap-2 mt-2">
          <button v-if="selectedFund.status === 'pending_driver_acceptance'" class="btn-pill btn-primary btn-pill-compact" :disabled="actionLoading" @click="handleAcceptFund">Terima Kartu</button>
          <button v-if="selectedFund.status === 'accepted'" class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="openExpenseDialog">Input Bon</button>
          <button v-if="selectedFund.status === 'accepted'" class="btn-pill btn-primary btn-pill-compact" :disabled="actionLoading" @click="showCloseDialog = true">Tutup / Kembalikan Kartu</button>
          <button v-if="canReview && ['pending_driver_acceptance', 'accepted'].includes(selectedFund.status)" class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="showVoidDialog = true">Void</button>
        </div>

        <strong class="mt-3 block">Bon / Pengeluaran</strong>
        <table class="w-full text-xs">
          <tbody>
            <tr v-for="expense in selectedFund.expenses" :key="expense.id">
              <td>{{ expense.description }}</td>
              <td>{{ formatMoney(expense.amount) }}</td>
              <td>{{ statusLabel(expense.status) }}</td>
              <td>
                <button v-if="expense.photo_path" class="link-button" @click="openExpensePhoto(expense)">Foto</button>
              </td>
              <td v-if="canReview && expense.status === 'submitted'">
                <button class="btn-pill btn-primary btn-pill-compact" @click="handleApproveExpense(expense)">ACC</button>
                <button class="btn-pill btn-secondary btn-pill-compact" @click="openRejectDialog(expense)">Tolak</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showDetailDialog = false">Tutup</button>
      </template>
    </Dialog>

    <!-- Dialog: Input Bon -->
    <Dialog v-model:visible="showExpenseDialog" header="Input Bon Kartu Fleet" modal style="width: 28rem">
      <div class="dialog-form">
        <label>Jenis</label>
        <Dropdown v-model="expenseForm.type" :options="[{ label: 'Bon/Pengeluaran', value: 'expense' }, { label: 'Pengembalian Sisa', value: 'return' }]" optionLabel="label" optionValue="value" class="w-full" />
        <label v-if="expenseForm.type === 'expense'">Jenis Biaya</label>
        <Dropdown v-if="expenseForm.type === 'expense'" v-model="expenseForm.cost_type_id" :options="costTypes" optionLabel="nama" optionValue="id" class="w-full" />
        <label>Jumlah</label>
        <InputNumber v-model="expenseForm.amount" class="w-full" mode="currency" currency="IDR" locale="id-ID" />
        <label>Keterangan</label>
        <Textarea v-model="expenseForm.description" class="w-full" rows="2" />
        <label>Foto Bon</label>
        <input type="file" accept="image/*" @change="onExpensePhotoChange" />
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showExpenseDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || !expenseForm.amount || !expenseForm.description" @click="submitFundExpense">Kirim</button>
      </template>
    </Dialog>

    <!-- Dialog: Tutup Kartu -->
    <Dialog v-model:visible="showCloseDialog" header="Tutup Kartu Fleet" modal style="width: 24rem">
      <div class="dialog-form">
        <label>Catatan Penutupan</label>
        <Textarea v-model="closeNote" class="w-full" rows="2" />
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showCloseDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading" @click="handleCloseFund">Tutup Kartu</button>
      </template>
    </Dialog>

    <!-- Dialog: Void Kartu -->
    <Dialog v-model:visible="showVoidDialog" header="Void Kartu Fleet" modal style="width: 24rem">
      <div class="dialog-form">
        <label>Alasan Void</label>
        <Textarea v-model="voidReason" class="w-full" rows="2" />
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showVoidDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || !voidReason" @click="handleVoidFund">Ajukan Void</button>
      </template>
    </Dialog>

    <!-- Dialog: Tolak Bon -->
    <Dialog v-model:visible="showRejectDialog" header="Tolak Bon" modal style="width: 24rem">
      <div class="dialog-form">
        <label>Alasan Penolakan</label>
        <Textarea v-model="rejectReason" class="w-full" rows="2" />
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showRejectDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || !rejectReason" @click="handleRejectExpense">Tolak</button>
      </template>
    </Dialog>
  </div>
</template>
