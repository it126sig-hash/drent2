<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
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
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue'
import { useCostType } from '../../composables/useCostType'
import { useOperationalFund } from '../../composables/useOperationalFund'
import { usePaymentAccount } from '../../composables/usePaymentAccount'
import { useRouter } from 'vue-router'

const router = useRouter()
const {
  bookings,
  history,
  selectedFund,
  loading,
  actionLoading,
  pagination,
  filters,
  historyFilters,
  fetchBookings,
  fetchFund,
  fetchHistory,
  storeFund,
  closeFund,
  submitExpense,
  approveExpense,
  rejectExpense,
  openExpensePhoto,
} = useOperationalFund()
const { costTypes, fetchAll: fetchCostTypes } = useCostType()
const { accounts, fetchAll: fetchPaymentAccounts } = usePaymentAccount()

const activeTab = ref('active')
const showFundDialog = ref(false)
const showDetailDialog = ref(false)
const showExpenseDialog = ref(false)
const showRejectDialog = ref(false)
const showCloseDialog = ref(false)
const showCompleteOperationalDialog = ref(false)
const selectedBooking = ref(null)
const selectedExpense = ref(null)
const isMobile = ref(window.innerWidth < 768)
const fundMode = ref('operational')
const closeNote = ref('')
const completeOperationalNote = ref('')
const detailLoadingFundId = ref(null)
const detailDialogTab = ref('selected')

const fundForm = ref({
  booking_detail_id: null,
  driver_id: null,
  payment_account_id: null,
  amount: 0,
  paid_at: new Date(),
  recipient_destination: '',
  notes: '',
  items: [],
})

const expenseForm = ref({
  fund_id: null,
  cost_type_id: null,
  type: 'expense',
  amount: null,
  description: '',
  photo: null,
})
const rejectReason = ref('')

const costTypeOptions = computed(() => costTypes.value.map(item => ({
  id: item.id,
  label: item.nama,
  value: item.id,
})))

const paymentAccountOptions = computed(() => accounts.value.map(account => ({
  id: account.id,
  label: `${account.nama_bank} - ${account.nomor_rekening} (${account.atas_nama})`,
})))

const driverCostType = computed(() => costTypes.value.find(item => item.kode === 'driver') || null)

const fundDialogTitle = computed(() =>
  fundMode.value === 'salary' ? 'Bayar Gaji Driver' : 'Cairkan Deposit Operasional'
)

const tabOptions = [
  { label: 'Operasional Aktif', value: 'active' },
  { label: 'Selesai', value: 'completed' },
  { label: 'Histori', value: 'history' },
]

const fundDetailOptions = computed(() => {
  return (selectedBooking.value?.booking_details || [])
    .filter(detail => detail.driver)
    .map(detail => ({
      label: `${detail.driver?.nama || 'Driver'} - ${formatDateTime(detail.tgl_sewa)} - ${detail.unit?.no_polisi || 'Unit'}`,
      value: detail.id,
      detail,
    }))
})

const selectedFundDetail = computed(() =>
  fundDetailOptions.value.find(option => option.value === fundForm.value.booking_detail_id)?.detail || null
)

const fundItemTotal = computed(() =>
  fundForm.value.items.reduce((sum, item) => sum + Number(item.planned_amount || 0), 0)
)

const bookingFundHistory = computed(() => {
  const sourceFunds = selectedFund.value?.booking_funds?.length
    ? selectedFund.value.booking_funds
    : selectedBooking.value?.operational_funds?.length
      ? selectedBooking.value.operational_funds
      : (selectedFund.value ? [selectedFund.value] : [])

  return [...new Map(sourceFunds.map(fund => [fund.id, fund])).values()].sort((a, b) => {
    const dateA = new Date(a.paid_at || a.created_at || 0).getTime()
    const dateB = new Date(b.paid_at || b.created_at || 0).getTime()
    return dateB - dateA
  })
})

const bookingExpenseHistory = computed(() =>
  bookingFundHistory.value
    .flatMap(fund => (fund.expenses || []).map(expense => ({ ...expense, fund })))
    .sort((a, b) => {
      const dateA = new Date(a.reviewed_at || a.created_at || 0).getTime()
      const dateB = new Date(b.reviewed_at || b.created_at || 0).getTime()
      return dateB - dateA
    })
)

const transactionHistoryRows = computed(() => {
  const deposits = bookingFundHistory.value.map(fund => ({
    id: `fund-${fund.id}`,
    row_kind: 'deposit',
    label: fund.is_salary ? 'Gaji Driver' : 'Deposit OP',
    happened_at: fund.paid_at || fund.created_at,
    amount: fund.amount,
    status: fund.status,
    description: fund.recipient_destination,
    actor: fund.creator?.name || fund.driver?.nama || '-',
    fund,
    expense: null,
  }))

  const expenses = bookingExpenseHistory.value.map(expense => ({
    id: `expense-${expense.id}`,
    row_kind: expense.type === 'return' ? 'return' : 'reimbursement',
    label: expense.type === 'return' ? 'Pengembalian Dana' : (expense.cost_type?.nama || 'Dana Reimburs'),
    happened_at: expense.reviewed_at || expense.created_at,
    amount: expense.amount,
    status: expense.status,
    description: expense.description,
    actor: expense.submitter?.name || '-',
    expense,
    fund: expense.fund,
  }))

  return [...deposits, ...expenses].sort((a, b) =>
    new Date(b.happened_at || 0).getTime() - new Date(a.happened_at || 0).getTime()
  )
})

const selectedTransactionHistoryRows = computed(() =>
  transactionHistoryRows.value.filter(row => row.fund?.id === selectedFund.value?.id)
)

const allTransactionHistoryRows = computed(() =>
  transactionHistoryRows.value
)

const visibleTransactionHistoryRows = computed(() =>
  detailDialogTab.value === 'selected' ? selectedTransactionHistoryRows.value : allTransactionHistoryRows.value
)

const visibleDetailCount = computed(() => {
  const detailIds = visibleTransactionHistoryRows.value
    .map(row => row.fund?.booking_detail?.id || row.fund?.booking_detail_id)
    .filter(Boolean)

  return new Set(detailIds).size || (visibleTransactionHistoryRows.value.length ? 1 : 0)
})

const visibleTransactionSubtotal = computed(() =>
  visibleTransactionHistoryRows.value.reduce((sum, row) => sum + Number(row.amount || 0), 0)
)

const visibleRealizationTotal = computed(() =>
  visibleTransactionHistoryRows.value
    .filter(row => row.row_kind === 'reimbursement' && row.status === 'approved')
    .reduce((sum, row) => sum + Number(row.amount || 0), 0)
)

const selectedDetailContext = computed(() => {
  const rowFund = visibleTransactionHistoryRows.value.find(row => row.fund)?.fund
  const fund = rowFund || selectedFund.value || {}
  const detail = fund.booking_detail || selectedFund.value?.booking_detail || {}

  return {
    driver: fund.driver?.nama || selectedFund.value?.driver?.nama || detail.driver?.nama || '-',
    unit: detail.unit?.no_polisi || '-',
    tanggal_sewa: formatDateTime(detail.tgl_sewa) || '-',
    tanggal_selesai: formatDateTime(detail.tgl_kembali) || '-',
  }
})

const selectedDepositTotal = computed(() =>
  bookingFundHistory.value
    .filter(fund => !fund.is_salary && fund.status !== 'cancelled')
    .reduce((sum, fund) => sum + Number(fund.amount || 0), 0)
)

const selectedReimbursedTotal = computed(() =>
  bookingExpenseHistory.value
    .filter(expense => expense.type === 'expense' && expense.status === 'approved')
    .reduce((sum, expense) => sum + Number(expense.amount || 0), 0)
)

const selectedReturnTotal = computed(() =>
  bookingExpenseHistory.value
    .filter(expense => expense.type === 'return' && expense.status === 'approved')
    .reduce((sum, expense) => sum + Number(expense.amount || 0), 0)
)

const selectedPendingReceiptCount = computed(() =>
  bookingExpenseHistory.value.filter(expense => expense.status === 'submitted').length
)

const selectedBookingDepositHistory = computed(() =>
  (selectedBooking.value?.operational_funds || [])
    .filter(fund => fund.fund_type !== 'salary')
    .sort((a, b) => new Date(b.paid_at || b.created_at || 0).getTime() - new Date(a.paid_at || a.created_at || 0).getTime())
)

const historyGroupedRows = computed(() =>
  [...history.value].sort((a, b) => {
    const codeCompare = String(a.booking_code || '').localeCompare(String(b.booking_code || ''))
    if (codeCompare !== 0) return codeCompare

    return new Date(b.happened_at || 0).getTime() - new Date(a.happened_at || 0).getTime()
  })
)

const historyGroupSummary = (bookingCode) => {
  const rows = history.value.filter(item => (item.booking_code || '-') === (bookingCode || '-'))

  return {
    count: rows.length,
    out: rows
      .filter(item => item.direction === 'out')
      .reduce((sum, item) => sum + Number(item.amount || 0), 0),
    in: rows
      .filter(item => item.direction !== 'out')
      .reduce((sum, item) => sum + Number(item.amount || 0), 0),
  }
}

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)

const formatDateTime = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy HH:mm')
}

const formatDate = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'yyyy-MM-dd')
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

const transactionKindSeverity = (kind) => {
  if (kind === 'deposit') return 'info'
  if (kind === 'return') return 'danger'
  return 'success'
}

const isReturnHistoryItem = (item) => item.expense_type === 'return' || item.label === 'Pengembalian Sisa Dana'

const historyKindSeverity = (item) => {
  if (item.type === 'transfer') return 'info'
  if (isReturnHistoryItem(item)) return 'success'
  return 'warn'
}

const historyDirectionLabel = (item) => {
  if (item.direction === 'out') return 'Keluar'
  if (isReturnHistoryItem(item)) return 'Uang Masuk'
  return 'Potong Saldo'
}

const historyDirectionClass = (item) => {
  if (item.direction === 'out') return 'text-negative'
  if (isReturnHistoryItem(item)) return 'text-positive'
  return 'text-secondary'
}

const transactionRowClass = (row) => ({
  'transaction-row-deposit': row.row_kind === 'deposit',
  'transaction-row-reimbursement': row.row_kind === 'reimbursement',
  'transaction-row-return': row.row_kind === 'return',
})

const operationalRowClass = (row) => ({
  'operational-row-summary': row.row_type === 'summary',
})

const operationalDepositBalance = (row) =>
  Number(row.disbursed_total || 0) - Number(row.realization_total || 0) - Number(row.return_total || 0)

const operationalSummaryLabel = (row) =>
  row.row_type === 'summary' ? formatCurrency(operationalDepositBalance(row)) : 'Total transaksi'

const bookingClosableFunds = (booking) =>
  (booking?.operational_funds || []).filter(fund =>
    !fund.is_salary
    && fund.fund_type !== 'salary'
    && fund.status === 'accepted'
  )

const canMarkOperationalComplete = (booking) =>
  activeTab.value === 'active'
  && bookingClosableFunds(booking).length > 0
  && Number(booking?.summary?.pending_driver_acceptance_count || 0) === 0

const detailTypeLabel = (type) => {
  if (type === 'extend') return 'Extend'
  if (type === 'rolling') return 'Rolling'
  return 'Initial'
}

const isModifiedDetail = (detail) => ['extend', 'rolling'].includes(detail?.detail_type)

const detailBudgetTotal = (detail) => {
  const operationalCosts = (detail?.costs || [])
    .filter(cost => cost.type === 'biaya' && cost.cost_type?.kode !== 'driver')
    .reduce((sum, cost) => sum + Number(cost.amount || 0), 0)

  if (operationalCosts > 0) return operationalCosts

  if (detail?.pricing_mode === 'all_in') {
    return Number(detail.harga_all_in || 0) * Number(detail.lama_sewa || 1)
  }

  return 0
}

const detailFunds = (booking, detail) =>
  (booking.operational_funds || []).filter(fund =>
    fund.booking_detail_id === detail?.id
    && !fund.is_salary
    && fund.fund_type !== 'salary'
    && fund.status !== 'cancelled'
  )

const detailSalaryFunds = (booking, detail) =>
  (booking.operational_funds || []).filter(fund =>
    fund.booking_detail_id === detail?.id
    && (fund.is_salary || fund.fund_type === 'salary')
    && fund.status !== 'cancelled'
  )

const detailDisbursedTotal = (booking, detail) =>
  detailFunds(booking, detail)
    .filter(fund => ['pending_driver_acceptance', 'accepted', 'closed'].includes(fund.status))
    .reduce((sum, fund) => sum + Number(fund.amount || 0), 0)

const detailSalaryTotal = (booking, detail) =>
  detailSalaryFunds(booking, detail)
    .filter(fund => fund.status === 'closed')
    .reduce((sum, fund) => sum + Number(fund.amount || 0), 0)

const detailRealizationTotal = (booking, detail) =>
  detailFunds(booking, detail)
    .flatMap(fund => fund.expenses || [])
    .filter(expense => expense.type === 'expense' && expense.status === 'approved')
    .reduce((sum, expense) => sum + Number(expense.amount || 0), 0)

const detailReturnTotal = (booking, detail) =>
  detailFunds(booking, detail)
    .flatMap(fund => fund.expenses || [])
    .filter(expense => expense.type === 'return' && expense.status === 'approved')
    .reduce((sum, expense) => sum + Number(expense.amount || 0), 0)

const detailHasPendingDriverAcceptance = (booking, detail) =>
  detailFunds(booking, detail).some(fund => fund.status === 'pending_driver_acceptance')

const detailHasPendingDriverReceipts = (booking, detail) =>
  detailFunds(booking, detail)
    .flatMap(fund => fund.expenses || [])
    .some(expense => expense.status === 'submitted' && expense.source === 'driver')

const selectedBudgetTotal = computed(() => {
  const detailSources = [
    selectedFund.value?.booking?.booking_details,
    selectedBooking.value?.booking_details,
    selectedFund.value?.booking_funds?.map(fund => fund.booking_detail).filter(Boolean),
    selectedBooking.value?.operational_funds?.map(fund => fund.booking_detail).filter(Boolean),
    selectedFund.value?.booking_detail ? [selectedFund.value.booking_detail] : [],
  ]

  const details = detailSources.find(source => source?.length) || []
  const uniqueDetails = [...new Map(details.map(detail => [detail.id || detail.row_key || detail, detail])).values()]

  return uniqueDetails.reduce((sum, detail) => sum + detailBudgetTotal(detail), 0)
})

const operationalRows = computed(() =>
  bookings.value.flatMap((booking) => {
    const details = booking.booking_details?.length ? booking.booking_details : [null]
    const groupId = `booking-${booking.id}`

    const detailRows = details.map((detail, index) => ({
      booking,
      detail,
      row_type: 'detail',
      row_key: `${groupId}-${detail?.id || `fallback-${index}`}`,
      booking_group_id: groupId,
      budget_total: detailBudgetTotal(detail),
      disbursed_total: detailDisbursedTotal(booking, detail),
      salary_total: detailSalaryTotal(booking, detail),
      realization_total: detailRealizationTotal(booking, detail),
      return_total: detailReturnTotal(booking, detail),
      has_pending_driver_acceptance: detailHasPendingDriverAcceptance(booking, detail),
      has_pending_driver_receipts: detailHasPendingDriverReceipts(booking, detail),
    }))

    return [
      ...detailRows,
      {
        booking,
        detail: null,
        row_type: 'summary',
        row_key: `${groupId}-summary`,
        booking_group_id: groupId,
        detail_count: detailRows.length,
        budget_total: detailRows.reduce((sum, row) => sum + row.budget_total, 0),
        disbursed_total: detailRows.reduce((sum, row) => sum + row.disbursed_total, 0),
        salary_total: detailRows.reduce((sum, row) => sum + row.salary_total, 0),
        realization_total: detailRows.reduce((sum, row) => sum + row.realization_total, 0),
        return_total: detailRows.reduce((sum, row) => sum + row.return_total, 0),
        has_pending_driver_acceptance: false,
        has_pending_driver_receipts: false,
      },
    ]
  })
)

const hasPendingDriverAcceptance = (booking) =>
  Number(booking.summary?.pending_driver_acceptance_count || 0) > 0

const hasPendingDriverReceipts = (booking) =>
  Number(booking.summary?.pending_driver_review_count || booking.summary?.pending_review_count || 0) > 0

const applyFilters = () => {
  pagination.value.current_page = 1
  if (activeTab.value === 'history') {
    fetchHistory(1)
    return
  }
  fetchBookings(1)
}

const resetFilters = () => {
  if (activeTab.value === 'history') {
    historyFilters.value = {
      search: '',
      date_from: null,
      date_to: null,
    }
    fetchHistory(1)
    return
  }

  filters.value = {
    search: '',
    status: null,
    driver_id: null,
    date_from: null,
    date_to: null,
    operational_state: activeTab.value === 'completed' ? 'completed' : 'active',
  }
  fetchBookings(1)
}

const onPage = (event) => {
  pagination.value.current_page = event.page + 1
  if (activeTab.value === 'history') {
    fetchHistory(pagination.value.current_page)
    return
  }
  fetchBookings(pagination.value.current_page)
}

const switchTab = (tab) => {
  activeTab.value = tab
  pagination.value.current_page = 1

  if (tab === 'history') {
    fetchHistory(1)
    return
  }

  filters.value.operational_state = tab === 'completed' ? 'completed' : 'active'
  fetchBookings(1)
}

const openFundDialog = (booking, mode = 'operational', detailId = null) => {
  selectedBooking.value = booking
  fundMode.value = mode
  const firstDetail = (booking.booking_details || []).find(detail => detail.id === detailId)
    || (booking.booking_details || []).find(detail => detail.driver)
  fundForm.value = {
    booking_detail_id: firstDetail?.id || null,
    driver_id: firstDetail?.driver_id || null,
    payment_account_id: paymentAccountOptions.value[0]?.id || null,
    amount: 0,
    paid_at: new Date(),
    recipient_destination: '',
    notes: '',
    items: [],
  }
  syncItemsFromDetail(firstDetail)
  showFundDialog.value = true
}

const syncItemsFromDetail = (detail) => {
  if (!detail) return
  fundForm.value.driver_id = detail.driver_id

  if (fundMode.value === 'salary') {
    const driverCost = (detail.costs || []).find(cost => cost.cost_type?.kode === 'driver')
    fundForm.value.items = [{
      cost_type_id: driverCostType.value?.id || driverCost?.cost_type_id || null,
      label: 'Gaji Driver',
      planned_amount: driverCost?.amount || 0,
      notes: driverCost?.keterangan || '',
    }]
    fundForm.value.amount = fundItemTotal.value
    return
  }

  const costs = (detail.costs || []).filter(cost => cost.type === 'biaya' && cost.cost_type?.kode !== 'driver')
  fundForm.value.items = costs.length
    ? costs.map(cost => ({
        cost_type_id: cost.cost_type_id,
        label: cost.cost_type?.nama || cost.label || 'Biaya operasional',
        planned_amount: cost.amount || 0,
        notes: cost.keterangan || '',
      }))
    : [{
        cost_type_id: null,
        label: detail.pricing_mode === 'all_in' ? 'Operasional All In' : 'Biaya operasional',
        planned_amount: 0,
        notes: '',
      }]
  fundForm.value.amount = fundItemTotal.value
}

const onFundDetailChange = () => {
  syncItemsFromDetail(selectedFundDetail.value)
}

const addFundItem = () => {
  if (fundMode.value === 'salary') return
  fundForm.value.items.push({ cost_type_id: null, label: '', planned_amount: 0, notes: '' })
}

const removeFundItem = (idx) => {
  fundForm.value.items.splice(idx, 1)
  fundForm.value.amount = fundItemTotal.value
}

const onCostTypeChange = (idx) => {
  const costType = costTypes.value.find(item => item.id === fundForm.value.items[idx].cost_type_id)
  if (costType && !fundForm.value.items[idx].label) {
    fundForm.value.items[idx].label = costType.nama
  }
}

const submitFund = async () => {
  if (!selectedBooking.value) return
  await storeFund(selectedBooking.value.id, {
    ...fundForm.value,
    fund_type: fundMode.value,
    paid_at: formatDate(fundForm.value.paid_at),
  })
  showFundDialog.value = false
}

const openCloseDialog = () => {
  closeNote.value = ''
  showCloseDialog.value = true
}

const submitCloseFund = async () => {
  if (!selectedFund.value?.id) return
  await closeFund(selectedFund.value.id, closeNote.value)
  showCloseDialog.value = false
  await fetchFund(selectedFund.value.id)
  await fetchBookings(pagination.value.current_page)
}

const openCompleteOperationalDialog = (booking) => {
  selectedBooking.value = booking
  completeOperationalNote.value = ''
  showCompleteOperationalDialog.value = true
}

const submitCompleteOperational = async () => {
  const funds = bookingClosableFunds(selectedBooking.value)
  if (!funds.length) return

  for (const fund of funds) {
    await closeFund(fund.id, completeOperationalNote.value || 'Operasional selesai')
  }

  showCompleteOperationalDialog.value = false
  await fetchBookings(pagination.value.current_page)
}

const openFundDetail = async (fundId, booking = null) => {
  if (booking) {
    selectedBooking.value = booking
  }
  detailLoadingFundId.value = fundId
  try {
    await fetchFund(fundId)
    showDetailDialog.value = true
  } finally {
    detailLoadingFundId.value = null
  }
}

const selectHistoryFund = async (fund) => {
  await fetchFund(fund.id)
}

const firstFundForDetail = (booking, detail) => {
  if (!detail?.id) return firstFund(booking)

  const funds = booking.operational_funds || []

  return funds.find(fund =>
    fund.booking_detail_id === detail.id
    && !fund.is_salary
    && fund.fund_type !== 'salary'
  ) || funds.find(fund => fund.booking_detail_id === detail.id) || firstFund(booking)
}

const openExpenseDialog = (fund, type = 'expense') => {
  selectedFund.value = fund
  expenseForm.value = {
    fund_id: fund.id,
    cost_type_id: null,
    type,
    amount: null,
    description: '',
    photo: null,
  }
  showExpenseDialog.value = true
}

const onPhotoChange = (event) => {
  expenseForm.value.photo = event.target.files?.[0] || null
}

const submitFinanceExpense = async () => {
  await submitExpense(expenseForm.value.fund_id, expenseForm.value)
  showExpenseDialog.value = false
  if (selectedFund.value?.id) {
    await fetchFund(selectedFund.value.id)
  }
  await fetchBookings(pagination.value.current_page)
}

const reviewApprove = async (expense) => {
  await approveExpense(expense.id)
  await fetchFund(selectedFund.value.id)
  await fetchBookings(pagination.value.current_page)
}

const openRejectDialog = (expense) => {
  selectedExpense.value = expense
  rejectReason.value = ''
  showRejectDialog.value = true
}

const submitReject = async () => {
  await rejectExpense(selectedExpense.value.id, rejectReason.value)
  showRejectDialog.value = false
  await fetchFund(selectedFund.value.id)
  await fetchBookings(pagination.value.current_page)
}

const firstFund = (booking) =>
  booking.operational_funds?.find(fund => !fund.is_salary) || booking.operational_funds?.[0] || null

const handleResize = () => {
  isMobile.value = window.innerWidth < 768
}

onMounted(async () => {
  window.addEventListener('resize', handleResize)
  await Promise.all([
    fetchBookings(1),
    fetchCostTypes({ per_page: 100, is_active: true }),
    fetchPaymentAccounts({ per_page: 100, is_active: true }),
  ])
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
})
</script>

<template>
  <div class="page-container operational-cost-page table-page-active">
    <div class="page-header">
      <div class="header-left">
        <h1 class="text-h1">Biaya Operasional</h1>
        <p class="text-secondary text-xs">Kelola pencairan dana driver, review bon, dan pengembalian sisa saldo.</p>
      </div>
      <div class="header-actions">
        <div class="tab-toggle-container">
          <div class="pill-toggle">
            <button
              v-for="tab in tabOptions"
              :key="tab.value"
              class="toggle-item"
              :class="{ active: activeTab === tab.value }"
              @click="switchTab(tab.value)"
            >
              {{ tab.label }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="list-tab-fill operational-list-tab">
      <div class="filter-bar surface-card">
        <div class="filter-groups">
          <div class="filter-group filter-group-wide">
            <label>Pencarian</label>
            <span class="filter-search">
              <i class="pi pi-search"></i>
              <InputText
                v-if="activeTab === 'history'"
                v-model="historyFilters.search"
                placeholder="Kode, driver, keterangan..."
                class="w-full"
                @keyup.enter="applyFilters"
              />
              <InputText
                v-else
                v-model="filters.search"
                placeholder="Kode, pelanggan, driver, tujuan..."
                class="w-full"
                @keyup.enter="applyFilters"
              />
            </span>
          </div>
          <div v-if="activeTab !== 'history'" class="filter-group">
            <label>Status Dana</label>
            <Dropdown
              v-model="filters.status"
              :options="[
                { label: 'Semua', value: null },
                { label: 'Menunggu Driver', value: 'pending_driver_acceptance' },
                { label: 'Diterima', value: 'accepted' },
                { label: 'Ditutup', value: 'closed' },
              ]"
              optionLabel="label"
              optionValue="value"
              placeholder="Semua"
              class="w-full md:w-52"
            />
          </div>
          <div class="filter-group">
            <label>Mulai</label>
            <DatePicker v-if="activeTab === 'history'" v-model="historyFilters.date_from" dateFormat="yy-mm-dd" placeholder="Dari" class="w-full md:w-36" />
            <DatePicker v-else v-model="filters.date_from" dateFormat="yy-mm-dd" placeholder="Dari" class="w-full md:w-36" />
          </div>
          <div class="filter-group">
            <label>Sampai</label>
            <DatePicker v-if="activeTab === 'history'" v-model="historyFilters.date_to" dateFormat="yy-mm-dd" placeholder="Sampai" class="w-full md:w-36" />
            <DatePicker v-else v-model="filters.date_to" dateFormat="yy-mm-dd" placeholder="Sampai" class="w-full md:w-36" />
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

      <ProgressBar v-if="loading" mode="indeterminate" style="height: 4px" class="mb-4" />
      <ProgressBar v-if="detailLoadingFundId" mode="indeterminate" style="height: 4px" class="mb-4 detail-loading-strip" />

      <div v-if="!isMobile && activeTab !== 'history'" class="table-shell operational-table-shell">
        <DataTable
          :value="operationalRows"
          dataKey="row_key"
          rowGroupMode="rowspan"
          groupRowsBy="booking_group_id"
          lazy
          paginator
          scrollable
          scrollHeight="flex"
          :rows="pagination.per_page"
          :totalRecords="pagination.total"
          :loading="loading"
          @page="onPage"
          responsiveLayout="scroll"
          class="drent-datatable"
          :rowClass="operationalRowClass"
        >
      <Column field="booking_group_id" header="Booking" style="min-width: 10rem">
        <template #body="{ data }">
          <div class="rowspan-booking-cell">
            <button class="text-xs text-secondary mt-2 link-button" @click="router.push(`/bookings/${data.booking.id}`)">{{ data.booking.kode_booking }}</button>
            <div class="text-xs text-secondary mt-2">{{ data.booking.customer?.nama || '-' }}</div>
            <BookingStatusBadge :status="data.booking.status" />
            <button
              v-if="activeTab === 'active'"
              class="btn-pill btn-secondary btn-pill-compact booking-complete-button"
              :disabled="!canMarkOperationalComplete(data.booking) || actionLoading"
              @click="openCompleteOperationalDialog(data.booking)"
            >
              <i class="pi pi-check"></i>
              Tandai selesai
            </button>
          </div>
        </template>
      </Column>
      <Column header="Driver & Jadwal" style="min-width: 15rem">
        <template #body="{ data }">
          <div v-if="data.row_type === 'summary'"  class="group-summary-label">
            Total {{ data.detail_count }} sub transaksi
          </div>
          <div v-else class="detail-line detail-line-rowspan">
            <div class="detail-title-row">
              <strong>{{ data.detail?.driver?.nama || 'Belum ada driver' }}</strong>
              <Tag :value="data.detail ? detailTypeLabel(data.detail.detail_type) : '-'" :severity="isModifiedDetail(data.detail) ? 'info' : 'secondary'" />
            </div>
            <span>{{ data.detail ? `${formatDateTime(data.detail.tgl_sewa)} - ${formatDateTime(data.detail.tgl_kembali)}` : '-' }}</span>
            <span>{{ data.detail?.unit?.tipe || '-' }} ({{ data.detail?.unit?.no_polisi || '-' }})</span>
          </div>
        </template>
      </Column>
       <Column header="Gaji Driver (Paid)" style="min-width: 9rem">
        <template #body="{ data }">
          <div class="amount-stack amount-stack-rowspan" :class="{ 'group-summary-amount': data.row_type === 'summary' }">
            <span>{{ formatCurrency(data.salary_total) }}</span>
          </div>
        </template>
      </Column>
      <Column header="Anggaran Awal" style="min-width: 9rem">
        <template #body="{ data }">
          <div class="amount-stack amount-stack-rowspan" :class="{ 'group-summary-amount': data.row_type === 'summary' }">
            <span>{{ formatCurrency(data.budget_total) }}</span>
          </div>
        </template>
      </Column>
      <Column header="Deposit" style="min-width: 9rem">
        <template #body="{ data }">
          <div class="amount-stack amount-stack-rowspan" :class="{ 'group-summary-amount': data.row_type === 'summary' }">
            <span>{{ formatCurrency(data.disbursed_total) }}</span>
            <Tag v-if="data.has_pending_driver_acceptance" value="Belum di ACC driver" severity="warn" />
          </div>
        </template>
      </Column>
     
      <Column header="Realisasi OP" style="min-width: 9rem">
        <template #body="{ data }">
          <div class="amount-stack amount-stack-rowspan" :class="{ 'group-summary-amount': data.row_type === 'summary' }">
            <span>{{ formatCurrency(data.realization_total) }}</span>
            <Tag v-if="data.has_pending_driver_receipts" value="Request ACC" severity="warn" />
          </div>
        </template>
      </Column>
      <Column header="Pengembalian" style="min-width: 9rem">
        <template #body="{ data }">
          <div class="amount-stack amount-stack-rowspan" :class="{ 'group-summary-amount': data.row_type === 'summary' }">
            <span>{{ formatCurrency(data.return_total) }}</span>
          </div>
        </template>
      </Column>
     
      <Column header="Aksi" style="min-width: 14rem">
        <template #body="{ data }">
          <div v-if="data.row_type === 'summary'" class="group-summary-label group-summary-balance text-right">
            <span>Sisa Saldo  </span>
            <strong>{{ operationalSummaryLabel(data) }}</strong>
          </div>
          <div v-else class="action-pill-group detail-action-cell">
            <button class="action-btn action-btn-primary" type="button" title="Tambah deposit" @click="openFundDialog(data.booking, 'operational', data.detail?.id)">
              <i class="pi pi-plus"></i>
            </button>
            <button class="action-btn" type="button" title="Bayar gaji driver" @click="openFundDialog(data.booking, 'salary', data.detail?.id)">
              <i class="pi pi-money-bill"></i>
            </button>
            <button
              v-if="firstFundForDetail(data.booking, data.detail)"
              class="action-btn"
              type="button"
              title="Lihat bon"
              :disabled="detailLoadingFundId === firstFundForDetail(data.booking, data.detail).id || actionLoading"
              @click="openFundDetail(firstFundForDetail(data.booking, data.detail).id, data.booking)"
            >
              <i :class="detailLoadingFundId === firstFundForDetail(data.booking, data.detail).id ? 'pi pi-spin pi-spinner' : 'pi pi-eye'"></i>
            </button>
          </div>
        </template>
      </Column>
        </DataTable>
      </div>

      <div v-else-if="!isMobile && activeTab === 'history'" class="table-shell operational-table-shell">
        <DataTable
          :value="history"
          dataKey="id"
          lazy
          paginator
          scrollable
          scrollHeight="flex"
          :rows="pagination.per_page"
          :totalRecords="pagination.total"
          :loading="loading"
          @page="onPage"
          responsiveLayout="scroll"
          class="drent-datatable"
        >
      <Column header="Tanggal" style="min-width: 11rem">
        <template #body="{ data }">
          <strong>{{ formatDateTime(data.happened_at) }}</strong>
          <div class="text-xs text-secondary">{{ data.booking_code || '-' }}</div>
        </template>
      </Column>
      <Column header="Jenis" style="min-width: 13rem">
        <template #body="{ data }">
          <Tag :value="data.label" :severity="historyKindSeverity(data)" />
          <div class="text-xs text-secondary mt-2">{{ data.status }}</div>
        </template>
      </Column>
      <Column header="Driver / Pelanggan" style="min-width: 14rem">
        <template #body="{ data }">
          <strong>{{ data.driver_name || '-' }}</strong>
          <div class="text-xs text-secondary">{{ data.customer_name || '-' }}</div>
        </template>
      </Column>
      <Column header="Nominal" style="min-width: 11rem">
        <template #body="{ data }">
          <div class="amount-stack">
            <span>{{ formatCurrency(data.amount) }}</span>
            <span :class="historyDirectionClass(data)">{{ historyDirectionLabel(data) }}</span>
          </div>
        </template>
      </Column>
      <Column header="Rekening Sumber" style="min-width: 16rem">
        <template #body="{ data }">
          <strong>{{ data.payment_account?.nama_bank || '-' }}</strong>
          <div class="text-xs text-secondary">{{ data.payment_account?.nomor_rekening || '-' }}</div>
          <div class="text-xs text-tertiary">{{ data.payment_account?.atas_nama || '-' }}</div>
        </template>
      </Column>
      <Column header="Keterangan" style="min-width: 18rem">
        <template #body="{ data }">
          <span class="table-text-clamp">{{ data.description || '-' }}</span>
          <div class="text-xs text-secondary mt-1">{{ data.created_by_name || data.reviewed_by_name || '-' }}</div>
        </template>
      </Column>
        </DataTable>
      </div>

    <div v-else-if="activeTab !== 'history'" class="mobile-card-list">
      <article v-for="booking in bookings" :key="booking.id" class="app-card operational-card">
        <div class="card-header">
          <div>
            <div class="booking-code-badge">{{ booking.kode_booking }}</div>
            <p class="text-xs text-secondary mt-2">{{ booking.customer?.nama || '-' }}</p>
          </div>
          <BookingStatusBadge :status="booking.status" />
        </div>
        <div class="card-body">
          <div class="info-row">
            <span>Rencana</span>
            <strong>{{ formatCurrency(booking.summary.booking_operational_total + booking.summary.all_in_total) }}</strong>
          </div>
          <div class="info-row">
            <span>Deposit OP</span>
            <div class="mobile-amount-note">
              <strong>{{ formatCurrency(booking.summary.finance_disbursed_total) }}</strong>
              <Tag v-if="hasPendingDriverAcceptance(booking)" value="Belum di ACC driver" severity="warn" />
            </div>
          </div>
          <div class="info-row">
            <span>Gaji driver</span>
            <strong>{{ formatCurrency(booking.summary.driver_salary_total) }}</strong>
          </div>
          <div class="info-row">
            <span>Dana reimburs</span>
            <div class="mobile-amount-note">
              <strong>{{ formatCurrency(booking.summary.approved_reimbursement_total || booking.summary.approved_expense_total) }}</strong>
              <Tag v-if="hasPendingDriverReceipts(booking)" value="Request ACC" severity="warn" />
            </div>
          </div>
          <div class="info-row">
            <span>Pengembalian</span>
            <strong>{{ formatCurrency(booking.summary.approved_return_total) }}</strong>
          </div>
        </div>
        <div class="card-footer">
          <button class="btn-pill btn-primary btn-pill-compact" @click="openFundDialog(booking)">
            <i class="pi pi-plus"></i>
            Deposit
          </button>
          <button class="btn-pill btn-secondary btn-pill-compact" @click="openFundDialog(booking, 'salary')">
            <i class="pi pi-money-bill"></i>
            Gaji
          </button>
          <button
            v-if="firstFund(booking)"
            class="btn-pill btn-secondary btn-pill-compact"
            :disabled="detailLoadingFundId === firstFund(booking).id || actionLoading"
            @click="openFundDetail(firstFund(booking).id, booking)"
          >
            <i :class="detailLoadingFundId === firstFund(booking).id ? 'pi pi-spin pi-spinner' : 'pi pi-eye'"></i>
            {{ detailLoadingFundId === firstFund(booking).id ? 'Memuat' : 'Detail' }}
          </button>
          <button
            v-if="activeTab === 'active'"
            class="btn-pill btn-secondary btn-pill-compact"
            :disabled="!canMarkOperationalComplete(booking) || actionLoading"
            @click="openCompleteOperationalDialog(booking)"
          >
            <i class="pi pi-check"></i>
            Tandai selesai
          </button>
        </div>
      </article>
    </div>

      <div v-else class="mobile-card-list">
      <article v-for="item in history" :key="item.id" class="app-card operational-card">
        <div class="card-header">
          <div>
            <Tag :value="item.label" :severity="historyKindSeverity(item)" />
            <p class="text-xs text-secondary mt-2">{{ item.booking_code || '-' }}</p>
          </div>
          <strong>{{ formatCurrency(item.amount) }}</strong>
        </div>
        <div class="card-body">
          <div class="info-row"><span>Driver</span><strong>{{ item.driver_name || '-' }}</strong></div>
          <div class="info-row"><span>Rekening</span><strong>{{ item.payment_account?.nama_bank || '-' }}</strong></div>
          <div class="info-row"><span>Arus</span><strong :class="historyDirectionClass(item)">{{ historyDirectionLabel(item) }}</strong></div>
          <div class="info-row"><span>Tanggal</span><strong>{{ formatDateTime(item.happened_at) }}</strong></div>
        </div>
      </article>
      </div>
    </div>

    <Dialog v-model:visible="showFundDialog" :header="fundDialogTitle" modal :style="{ width: 'min(1080px, 96vw)' }" :position="isMobile ? 'bottom' : 'center'" :class="[{ 'mobile-bottom-sheet': isMobile }, 'custom-dialog deposit-dialog']">
      <div class="deposit-modal">
        <section class="deposit-form-panel dialog-stack">
          <div class="app-muted-panel">
            <div class="summary-row"><span>Booking</span><strong>{{ selectedBooking?.kode_booking }}</strong></div>
            <div class="summary-row"><span>Pelanggan</span><strong>{{ selectedBooking?.customer?.nama || '-' }}</strong></div>
          </div>
          <fieldset class="form-fieldset">
            <label>Detail & Driver</label>
            <Dropdown v-model="fundForm.booking_detail_id" :options="fundDetailOptions" optionLabel="label" optionValue="value" class="w-full" @change="onFundDetailChange" />
          </fieldset>
          <fieldset class="form-fieldset">
            <label>Rekening Sumber Biaya</label>
            <Dropdown
              v-model="fundForm.payment_account_id"
              :options="paymentAccountOptions"
              optionLabel="label"
              optionValue="id"
              placeholder="Pilih rekening sumber"
              class="w-full"
            />
          </fieldset>
          <div class="form-grid">
            <fieldset class="form-fieldset">
              <label>Nominal Deposit</label>
              <InputNumber v-model="fundForm.amount" mode="currency" currency="IDR" locale="id-ID" :min="1" class="w-full" />
              <span class="field-hint">Total breakdown: {{ formatCurrency(fundItemTotal) }}</span>
            </fieldset>
            <fieldset class="form-fieldset">
              <label>Tanggal Pembayaran</label>
              <DatePicker v-model="fundForm.paid_at" dateFormat="dd M yy" showIcon class="w-full" />
            </fieldset>
          </div>
          <fieldset class="form-fieldset">
            <label>Tujuan Penerima</label>
            <InputText v-model="fundForm.recipient_destination" placeholder="No rekening, e-toll, cash, dll" class="w-full" />
          </fieldset>
          <fieldset class="form-fieldset">
            <label>Breakdown Cost Type</label>
            <div v-for="(item, idx) in fundForm.items" :key="idx" class="fund-item-row">
              <Dropdown v-model="item.cost_type_id" :options="costTypeOptions" optionLabel="label" optionValue="id" placeholder="Tipe" :disabled="fundMode === 'salary'" showClear @change="onCostTypeChange(idx)" />
              <InputText v-model="item.label" placeholder="Label" />
              <InputNumber v-model="item.planned_amount" mode="currency" currency="IDR" locale="id-ID" :min="0" @update:modelValue="fundForm.amount = fundItemTotal" />
              <button v-if="fundMode !== 'salary'" class="icon-button" type="button" @click="removeFundItem(idx)">
                <i class="pi pi-trash"></i>
              </button>
            </div>
            <span v-if="fundMode === 'salary'" class="field-hint">Cost type Driver diperlakukan sebagai gaji. Nominal ini tidak masuk saldo operasional driver dan tidak butuh bon.</span>
            <button v-else class="btn-pill btn-secondary btn-pill-compact self-start" type="button" @click="addFundItem">
              <i class="pi pi-plus"></i>
              Tambah Breakdown
            </button>
          </fieldset>
          <fieldset class="form-fieldset">
            <label>Catatan</label>
            <Textarea v-model="fundForm.notes" rows="3" class="w-full" />
          </fieldset>
        </section>
        <aside class="deposit-history-panel">
          <div class="app-section-header compact-section-header">
            <div>
              <h3>Riwayat Deposit</h3>
              <p>{{ selectedBookingDepositHistory.length }} transaksi deposit</p>
            </div>
          </div>
          <div class="deposit-history-list">
            <article v-for="fund in selectedBookingDepositHistory" :key="fund.id" class="deposit-history-card">
              <div class="deposit-history-head">
                <div>
                  <strong>{{ detailTypeLabel(fund.booking_detail?.detail_type) }} - {{ fund.driver?.nama || '-' }}</strong>
                  <span>{{ formatDate(fund.paid_at || fund.created_at) }}</span>
                </div>
                <Tag :value="fund.status" :severity="fundStatusSeverity(fund.status)" />
              </div>
              <div class="deposit-history-amount">{{ formatCurrency(fund.amount) }}</div>
              <div class="deposit-history-meta">
                <span>{{ fund.payment_account?.nama_bank || '-' }}</span>
                <span>{{ fund.recipient_destination || '-' }}</span>
              </div>
            </article>
            <div v-if="!selectedBookingDepositHistory.length" class="payment-invoice-empty">Belum ada riwayat deposit.</div>
          </div>
        </aside>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showFundDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || fundForm.amount <= 0 || fundForm.amount !== fundItemTotal || !fundForm.recipient_destination || (fundMode === 'salary' && !fundForm.items[0]?.cost_type_id)" @click="submitFund">
          <i class="pi pi-check"></i>
          {{ fundMode === 'salary' ? 'Bayar Gaji' : 'Simpan' }}
        </button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showDetailDialog" modal :show-header="false" :style="{ width: 'min(1180px, 96vw)' }" :position="isMobile ? 'bottom' : 'center'" :class="[{ 'mobile-bottom-sheet': isMobile }, 'custom-dialog detail-dialog detail-review-dialog']">
      <div v-if="selectedFund" class="detail-review-shell">
        <header class="detail-review-header">
          <div>
            <h3>Detail Dana Reimburs</h3>
            <p>Kelola rincian pengeluaran dan verifikasi bukti transaksi driver</p>
          </div>
          <button class="detail-close-button" type="button" aria-label="Tutup modal detail" @click="showDetailDialog = false">
            <i class="pi pi-times"></i>
          </button>
        </header>

        <div class="detail-review-content">
          <div class="detail-metric-grid">
            <div class="detail-metric-card">
              <span>Total Anggaran</span>
              <strong>{{ formatCurrency(selectedBudgetTotal) }}</strong>
            </div>
            <div class="detail-metric-card detail-metric-info">
              <span>Deposit OP</span>
              <strong>{{ formatCurrency(selectedDepositTotal) }}</strong>
            </div>
            <div class="detail-metric-card detail-metric-highlight">
              <span>Dana Reimburs</span>
              <strong>{{ formatCurrency(selectedReimbursedTotal) }}</strong>
            </div>
            <div class="detail-metric-card">
              <span>Pengembalian</span>
              <strong>{{ formatCurrency(selectedReturnTotal) }}</strong>
            </div>
            <div class="detail-metric-card detail-metric-danger">
              <span>Request ACC</span>
              <strong>{{ selectedPendingReceiptCount }}</strong>
            </div>
          </div>

          <div class="detail-command-row">
            <div class="detail-tab-toggle">
              <button class="toggle-item" :class="{ active: detailDialogTab === 'selected' }" @click="detailDialogTab = 'selected'">
                Transaksi Terpilih
              </button>
              <button class="toggle-item" :class="{ active: detailDialogTab === 'all' }" @click="detailDialogTab = 'all'">
                Semua Initial / Extend / Rolling
              </button>
            </div>
            <div v-if="!selectedFund.is_salary" class="detail-action-cluster">
              <button class="btn-pill btn-secondary btn-pill-compact" :disabled="selectedFund.status !== 'accepted'" @click="openExpenseDialog(selectedFund, 'expense')">
                <i class="pi pi-credit-card"></i>
                Input Bon Finance
              </button>
              <button class="btn-pill btn-secondary btn-pill-compact" :disabled="selectedFund.status !== 'accepted'" @click="openExpenseDialog(selectedFund, 'return')">
                <i class="pi pi-undo"></i>
                Input Pengembalian
              </button>
              <button class="btn-pill btn-primary btn-pill-compact" :disabled="selectedFund.status !== 'accepted' || actionLoading" @click="openCloseDialog">
                <i class="pi pi-lock"></i>
                Close Manual
              </button>
            </div>
          </div>

          <section class="transfer-history-section detail-table-panel">
            <div class="detail-table-header">
              <div class="detail-table-title">
                <i class="pi pi-wallet"></i>
                <strong>Deposit & Realisasi</strong>
              </div>
              <span>Driver: <strong>{{ selectedDetailContext.driver }}</strong> ({{ selectedDetailContext.unit }}) : <strong>{{ selectedDetailContext.tanggal_sewa }} - {{ selectedDetailContext.tanggal_selesai }}</strong></span>
              <small>{{ visibleDetailCount }} Sub Transaksi</small>
            </div>
          <DataTable
            :value="visibleTransactionHistoryRows"
            dataKey="id"
            responsiveLayout="scroll"
            class="transaction-history-table detail-review-table"
            :rowClass="transactionRowClass"
          >
            <Column header="Aksi">
              <template #body="{ data }">
                <div v-if="data.expense?.status === 'submitted'" class="table-actions">
                  <button class="btn-pill btn-primary btn-pill-compact" @click="reviewApprove(data.expense)">ACC</button>
                  <button class="btn-pill btn-secondary btn-pill-compact" @click="openRejectDialog(data.expense)">Tolak</button>
                </div>
              </template>
            </Column>
            <Column header="Bukti">
              <template #body="{ data }">
                <button v-if="data.expense?.photo_url" class="link-button" type="button" :disabled="actionLoading" @click="openExpensePhoto(data.expense)">Lihat foto</button>
                <span v-else>-</span>
              </template>
            </Column>
            <Column header="Tanggal" style="min-width: 11rem">
              <template #body="{ data }">{{ formatDateTime(data.happened_at) }}</template>
            </Column>
            <Column header="Sub Transaksi" style="min-width: 13rem">
              <template #body="{ data }">
                <strong>{{ detailTypeLabel(data.fund?.booking_detail?.detail_type) }}</strong>
                <div class="text-xs text-secondary">{{ data.fund?.booking_detail?.unit?.no_polisi || '-' }}</div>
              </template>
            </Column>
            <Column header="Jenis" style="min-width: 12rem">
              <template #body="{ data }">
                <Tag :value="data.label" :severity="transactionKindSeverity(data.row_kind)" />
              </template>
            </Column>
            <Column header="Driver" style="min-width: 12rem">
              <template #body="{ data }">{{ data.fund?.driver?.nama || selectedFund.driver?.nama || '-' }}</template>
            </Column>
            <Column header="Nominal" style="min-width: 11rem">
              <template #body="{ data }"><strong>{{ formatCurrency(data.amount) }}</strong></template>
            </Column>
            <Column header="Status" style="min-width: 10rem">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="data.row_kind === 'deposit' ? fundStatusSeverity(data.status) : expenseStatusSeverity(data.status)" />
              </template>
            </Column>
            <Column header="Keterangan" style="min-width: 18rem">
              <template #body="{ data }">
                <div>{{ data.description }}</div>
                <div v-if="data.expense?.rejection_reason" class="reject-note">{{ data.expense.rejection_reason }}</div>
              </template>
            </Column>
            
          
            <template #empty>
              Belum ada histori deposit atau realisasi.
            </template>
          </DataTable>
        </section>

          <div class="detail-total-panel">
            <div class="summary-row"><span>Subtotal Transaksi</span><strong>{{ formatCurrency(visibleTransactionSubtotal) }}</strong></div>
            <div class="summary-row"><span>Biaya Administrasi</span><strong>{{ formatCurrency(0) }}</strong></div>
            <div class="summary-row detail-total-row"><span>Total Realisasi</span><strong>{{ formatCurrency(visibleRealizationTotal) }}</strong></div>
          </div>
        </div>

        <footer class="detail-review-footer">
          <button class="app-dialog-button app-dialog-button-secondary" @click="showDetailDialog = false">Kembali</button>
        </footer>
      </div>
    </Dialog>

    <Dialog v-model:visible="showCloseDialog" header="Close Manual Transaksi" modal :style="{ width: '440px' }" class="custom-dialog">
      <div class="dialog-stack">
        <div class="app-muted-panel">
          <div class="summary-row"><span>Booking</span><strong>{{ selectedFund?.booking?.kode_booking || '-' }}</strong></div>
          <div class="summary-row"><span>Sisa dana</span><strong>{{ formatCurrency(selectedFund?.summary?.remaining_amount) }}</strong></div>
        </div>
        <fieldset class="form-fieldset">
          <label>Catatan Close</label>
          <Textarea v-model="closeNote" rows="4" class="w-full" placeholder="Contoh: semua bon sudah lengkap dan sisa dana sudah diselesaikan." />
        </fieldset>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showCloseDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading" @click="submitCloseFund">Close Transaksi</button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showCompleteOperationalDialog" header="Tandai Operasional Selesai" modal :style="{ width: '460px' }" :position="isMobile ? 'bottom' : 'center'" :class="[{ 'mobile-bottom-sheet': isMobile }, 'custom-dialog']">
      <div class="dialog-stack">
        <div class="app-muted-panel">
          <div class="summary-row"><span>Booking</span><strong>{{ selectedBooking?.kode_booking || '-' }}</strong></div>
          <div class="summary-row"><span>Transaksi aktif</span><strong>{{ bookingClosableFunds(selectedBooking).length }}</strong></div>
        </div>
        <fieldset class="form-fieldset">
          <label>Catatan selesai</label>
          <Textarea v-model="completeOperationalNote" rows="4" class="w-full" placeholder="Contoh: semua bon sudah lengkap dan operasional sudah diselesaikan." />
        </fieldset>
        <p class="field-hint">Semua dana operasional yang sudah diterima driver akan ditutup dan booking berpindah ke tab Selesai.</p>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showCompleteOperationalDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || !bookingClosableFunds(selectedBooking).length" @click="submitCompleteOperational">Tandai Selesai</button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showExpenseDialog" :header="expenseForm.type === 'return' ? 'Input Pengembalian' : 'Input Bon Finance'" modal :style="{ width: '480px' }" :position="isMobile ? 'bottom' : 'center'" :class="[{ 'mobile-bottom-sheet': isMobile }, 'custom-dialog']">
      <div class="dialog-stack">
        <fieldset v-if="expenseForm.type === 'expense'" class="form-fieldset">
          <label>Cost Type</label>
          <Dropdown v-model="expenseForm.cost_type_id" :options="costTypeOptions" optionLabel="label" optionValue="id" showClear class="w-full" />
        </fieldset>
        <fieldset class="form-fieldset">
          <label>Nominal</label>
          <InputNumber v-model="expenseForm.amount" mode="currency" currency="IDR" locale="id-ID" :min="1" class="w-full" />
        </fieldset>
        <fieldset class="form-fieldset">
          <label>Keterangan</label>
          <Textarea v-model="expenseForm.description" rows="3" class="w-full" />
        </fieldset>
        <fieldset class="form-fieldset">
          <label>Foto Bon/Bukti</label>
          <input type="file" accept="image/*" @change="onPhotoChange" />
        </fieldset>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showExpenseDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || !expenseForm.amount || expenseForm.description.length < 3" @click="submitFinanceExpense">
          Simpan
        </button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showRejectDialog" header="Tolak Bon Driver" modal :style="{ width: '440px' }" class="custom-dialog">
      <fieldset class="form-fieldset">
        <label>Alasan Penolakan</label>
        <Textarea v-model="rejectReason" rows="4" class="w-full" />
      </fieldset>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showRejectDialog = false">Batal</button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading || rejectReason.length < 5" @click="submitReject">Kirim Alasan</button>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.table-actions {
  display: flex;
  gap: var(--space-sm);
  flex-wrap: wrap;
  align-items: flex-end;
}

.action-btn-primary {
  background: var(--text-primary);
  color: #fff;
}

.form-fieldset {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-fieldset label {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-secondary);
}

:deep(.drent-datatable .p-column-header-content) {
  justify-content: center;
  text-align: center;
}

.booking-code-badge {
  display: inline-flex;
  padding: 5px 10px;
  border-radius: var(--radius-full);
  background: rgba(43, 52, 72, 0.08);
  color: var(--text-primary);
  font-size: 11px;
  font-weight: 800;
}

.detail-line,
.amount-stack,
.fund-list,
.dialog-stack {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.detail-line {
  padding-bottom: 8px;
  border-bottom: 1px dashed var(--surface-border);
  font-size: 12px;
}

.detail-line-rowspan {
  padding: 4px 0;
  border-bottom: 0;
}

.detail-line:last-child {
  border-bottom: none;
}

.amount-stack {
  align-items: flex-end;
  font-size: 12px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.amount-stack-rowspan {
  min-height: 44px;
  justify-content: center;
}

.rowspan-booking-cell,
.rowspan-action-cell {
  min-height: 68px;
  align-content: flex-start;
}

.rowspan-booking-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.booking-complete-button {
  width: fit-content;
  margin-top: 6px;
}

.rowspan-action-cell {
  align-items: flex-start;
}

:deep(.drent-datatable .p-datatable-tbody > tr > td[rowspan]) {
  vertical-align: top;
  background: var(--card-bg);
  border-right: 1px solid var(--surface-border);
}

:deep(.drent-datatable .p-datatable-tbody > tr.operational-row-summary > td) {
  background: rgba(0, 112, 234, 0.06);
  color: var(--text-primary);
  font-weight: 900;
}

:deep(.drent-datatable .p-datatable-tbody > tr.operational-row-summary > td .amount-stack),
:deep(.drent-datatable .p-datatable-tbody > tr.operational-row-summary > td .group-summary-label) {
  font-weight: 900;
}

.group-summary-balance {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.group-summary-balance span {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 800;
}

.group-summary-balance strong {
  color: #0059bb;
  font-variant-numeric: tabular-nums;
}

.detail-title-row,
.amount-breakdown-row,
.amount-breakdown-total {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.detail-title-row {
  justify-content: flex-start;
}

.amount-breakdown {
  display: flex;
  flex-direction: column;
  gap: 7px;
  min-width: 0;
  font-size: 11px;
}

.amount-breakdown-row {
  padding-bottom: 6px;
  border-bottom: 1px dashed var(--surface-border);
}

.amount-breakdown-row span {
  color: var(--text-secondary);
  font-weight: 700;
}

.amount-breakdown-row strong,
.amount-breakdown-total strong {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.amount-breakdown-total {
  padding-top: 2px;
  color: var(--text-primary);
  font-weight: 900;
}

.mobile-amount-note {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.fund-chip {
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  padding: 6px 8px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
}

.app-card,
.app-muted-panel,
.form-fieldset {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.app-muted-panel,
.form-fieldset {
  background: var(--card-bg);
  padding: var(--space-md);
  box-shadow: none;
}

.summary-row,
.info-row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-md);
}

.form-grid,
.fund-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-md);
}

.fund-summary-grid {
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
}

.fund-item-row {
  display: grid;
  grid-template-columns: 1fr 1.2fr 1fr 34px;
  gap: 8px;
  align-items: center;
}

.deposit-modal {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: var(--space-lg);
  max-height: min(74vh, 760px);
  overflow: hidden;
}

.deposit-form-panel,
.deposit-history-panel {
  min-width: 0;
  overflow: auto;
}

.deposit-history-panel,
.deposit-history-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.deposit-history-card {
  padding: var(--space-md);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.deposit-history-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-sm);
}

.deposit-history-head div,
.deposit-history-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.deposit-history-head span,
.deposit-history-meta {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

.deposit-history-amount {
  margin-top: var(--space-sm);
  color: var(--text-primary);
  font-size: 18px;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
}

.icon-button {
  width: 32px;
  height: 32px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-full);
  background: var(--surface-default);
  color: var(--negative);
  cursor: pointer;
}

.mini-table {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  overflow: hidden;
}

.transfer-history-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

:deep(.detail-review-dialog .p-dialog-content) {
  padding: 0;
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.detail-review-shell {
  display: flex;
  flex-direction: column;
  max-height: min(88vh, 921px);
  background: var(--surface-default);
}

.detail-review-header,
.detail-review-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-md);
  padding: 24px 32px;
  background: var(--surface-default);
}

.detail-review-header {
  border-bottom: 1px solid var(--surface-border);
}

.detail-review-header h3 {
  margin: 0;
  color: var(--text-primary);
  font-size: 20px;
  font-weight: 800;
}

.detail-review-header p {
  margin: 4px 0 0;
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 700;
}

.detail-close-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.detail-close-button:hover {
  background: rgba(239, 68, 68, 0.12);
  color: var(--negative);
}

.detail-review-content {
  flex: 1;
  overflow: auto;
  padding: 32px;
}

.detail-metric-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 28px;
}

.detail-metric-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  padding: 16px;
  border: 1px solid rgba(199, 197, 205, 0.55);
  border-radius: var(--radius-lg);
  background: var(--card-bg);
}

.detail-metric-card span {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.detail-metric-card strong {
  min-width: 0;
  color: var(--text-primary);
  font-size: 18px;
  font-weight: 900;
  line-height: 1.2;
  overflow-wrap: anywhere;
  font-variant-numeric: tabular-nums;
}

.detail-metric-info strong,
.detail-metric-highlight strong {
  color: #0059bb;
}

.detail-metric-highlight {
  border-color: rgba(0, 112, 234, 0.2);
  background: rgba(216, 226, 255, 0.42);
}

.detail-metric-danger {
  border-color: rgba(239, 68, 68, 0.18);
  background: rgba(239, 68, 68, 0.06);
}

.detail-metric-danger span,
.detail-metric-danger strong {
  color: var(--negative);
}

.detail-command-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-lg);
  margin-bottom: 24px;
}

.detail-tab-toggle {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 4px;
  width: fit-content;
  max-width: 100%;
  padding: 4px;
  border-radius: var(--radius-full);
  background: var(--card-bg);
}

.detail-command-row .detail-tab-toggle {
  border-radius: var(--radius-lg);
}

.detail-action-cluster {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

.detail-table-panel {
  gap: 0;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: var(--surface-default);
}

.detail-table-header {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--space-md);
  padding: 12px var(--space-md);
  border-bottom: 1px solid var(--surface-border);
  background: var(--card-bg);
}

.detail-table-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--text-primary);
  font-size: 13px;
}

.detail-table-title i {
  color: #0059bb;
}

.detail-table-header span {
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 700;
}

.detail-table-header span strong {
  color: var(--text-primary);
}

.detail-table-header small {
  color: var(--text-tertiary);
  font-size: 11px;
  font-weight: 900;
  text-transform: uppercase;
}

.detail-review-table {
  border: none;
  border-radius: 0;
}

:deep(.detail-review-table .p-datatable-thead > tr > th) {
  padding: 14px 16px;
  background: var(--card-bg);
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

:deep(.detail-review-table .p-datatable-tbody > tr > td) {
  padding: 16px;
}

.detail-total-panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: min(100%, 360px);
  margin: 28px 0 0 auto;
  padding: 20px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-lg);
  background: var(--surface-default);
}

.detail-total-panel .summary-row {
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 700;
}

.detail-total-panel .summary-row strong {
  color: var(--text-primary);
  font-variant-numeric: tabular-nums;
}

.detail-total-row {
  margin-top: 4px;
  padding-top: 12px;
  border-top: 1px solid var(--surface-border);
}

.detail-total-row span,
.detail-total-row strong {
  color: var(--text-primary) !important;
  font-size: 15px;
  font-weight: 900;
}

.detail-total-row strong {
  color: #0059bb !important;
}

.detail-review-footer {
  justify-content: flex-end;
  border-top: 1px solid var(--surface-border);
}

:deep(.transaction-history-table .transaction-row-deposit > td) {
  background: rgba(59, 130, 246, 0.07);
}

:deep(.transaction-history-table .transaction-row-reimbursement > td) {
  background: rgba(34, 197, 94, 0.08);
}

:deep(.transaction-history-table .transaction-row-return > td) {
  background: rgba(239, 68, 68, 0.06);
}

.detail-layout {
  display: grid;
  grid-template-columns: 300px minmax(0, 1fr);
  gap: var(--space-md);
  align-items: start;
}

.timeline-panel,
.fund-side-panel,
.fund-mini-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.fund-mini-item {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 4px 8px;
  width: 100%;
  padding: var(--space-md);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  text-align: left;
  cursor: pointer;
}

.fund-mini-item.active {
  border-color: var(--text-primary);
  background: var(--card-bg);
}

.fund-mini-item small {
  grid-column: 1 / -1;
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

.fund-mini-item :deep(.p-tag) {
  justify-self: start;
}

.timeline-card {
  padding: var(--space-lg);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

:deep(.fund-timeline .p-timeline-event-opposite) {
  display: none;
}

:deep(.fund-timeline .p-timeline-event-content) {
  padding: 0 0 var(--space-md) var(--space-md);
}

.timeline-card-head,
.timeline-actions,
.timeline-meta {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  flex-wrap: wrap;
}

.timeline-card-head {
  justify-content: space-between;
}

.timeline-card-head p,
.timeline-description {
  margin: 3px 0 0;
  color: var(--text-secondary);
  font-size: 12px;
}

.timeline-amount {
  margin-top: var(--space-sm);
  color: var(--text-primary);
  font-size: 18px;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
}

.timeline-meta {
  margin-top: 6px;
  color: var(--text-tertiary);
  font-size: 11px;
  font-weight: 800;
}

.timeline-actions {
  margin-top: var(--space-sm);
}

.timeline-marker {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-full);
  background: var(--surface-default);
  color: var(--text-primary);
}

.timeline-marker-receipt {
  color: var(--positive);
}

.timeline-marker-return {
  color: var(--negative);
}

.timeline-source,
.empty-timeline {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 800;
}

.empty-timeline {
  padding: var(--space-lg);
  border: 1px dashed var(--surface-border);
  border-radius: var(--radius-default);
  text-align: center;
}

.payment-invoice-empty {
  padding: 14px 0;
  color: var(--text-secondary);
  font-size: 12px;
  text-align: center;
}

.compact-section-header {
  min-height: 48px;
  padding: 0 var(--space-md);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
}

.compact-section-header h3 {
  margin: 0;
  color: var(--text-primary);
  font-size: 14px;
  font-weight: 800;
}

.compact-section-header p {
  margin: 2px 0 0;
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

.table-text-clamp {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.35;
}

.reject-note {
  margin-top: 4px;
  color: var(--negative);
  font-size: 11px;
  font-weight: 700;
}

.link-button {
  border: none;
  background: transparent;
  color: var(--text-primary);
  font-weight: 800;
  cursor: pointer;
  padding: 0;
}

.mobile-card-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding-bottom: 80px;
}

.operational-card {
  padding: var(--space-md);
}

.card-header,
.card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-md);
}

.card-body {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: var(--space-md) 0;
}

.field-hint {
  color: var(--text-tertiary);
  font-size: 11px;
}

:deep(.custom-dialog .p-dialog-footer) {
  border-top: 1px solid var(--surface-border);
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

:deep(.mobile-bottom-sheet) {
  margin: 0 !important;
  width: 100% !important;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0 !important;
  max-height: 88vh;
}

@media (max-width: 768px) {
  .form-grid,
  .fund-summary-grid,
  .detail-metric-grid,
  .fund-item-row,
  .deposit-modal,
  .detail-layout {
    grid-template-columns: 1fr;
  }

  .detail-review-shell {
    max-height: 88vh;
  }

  .detail-review-header,
  .detail-review-content,
  .detail-review-footer {
    padding: var(--space-xl);
  }

  .detail-command-row,
  .detail-action-cluster {
    align-items: stretch;
    flex-direction: column;
    width: 100%;
  }

  .detail-command-row .detail-tab-toggle,
  .detail-action-cluster .btn-pill,
  .detail-review-footer .app-dialog-button {
    width: 100%;
    justify-content: center;
  }

  .detail-table-header {
    grid-template-columns: 1fr;
    align-items: flex-start;
  }

  .deposit-modal {
    max-height: 78vh;
    overflow: auto;
  }

  .deposit-form-panel,
  .deposit-history-panel {
    overflow: visible;
  }

  :deep(.detail-dialog) {
    width: 100% !important;
  }

  .card-footer .btn-pill {
    width: 100%;
    justify-content: center;
  }
}
</style>
