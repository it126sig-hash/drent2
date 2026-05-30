<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useMonthlyFinanceReport } from '../../composables/useMonthlyFinanceReport'
import { usePaymentAccount } from '../../composables/usePaymentAccount'
import { useBranch } from '../../composables/useBranch'
import { useRentToRent } from '../../composables/useRentToRent'
import * as XLSX from 'xlsx'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import InputText from 'primevue/inputtext'
import Paginator from 'primevue/paginator'

const toast = useToast()
const router = useRouter()
const { report, loading, fetchReport } = useMonthlyFinanceReport()
const { accounts, fetchAll: fetchAccounts } = usePaymentAccount()
const { branches, fetchAll: fetchBranches } = useBranch()
const { summary: r2rSummary, fetchDebts } = useRentToRent()

const now = new Date()
const filters = ref({
  month: now.getMonth() + 1,
  year: now.getFullYear(),
  payment_account_id: null,
  branch_id: null,
})
const isMobile = ref(window.innerWidth < 768)

const searchQuery = ref('')
const sourceTypeFilter = ref(null)
const currentPage = ref(1)
const rowsPerPage = ref(10)

const handleResize = () => {
  isMobile.value = window.innerWidth < 768
}

const monthOptions = [
  { label: 'Januari', value: 1 },
  { label: 'Februari', value: 2 },
  { label: 'Maret', value: 3 },
  { label: 'April', value: 4 },
  { label: 'Mei', value: 5 },
  { label: 'Juni', value: 6 },
  { label: 'Juli', value: 7 },
  { label: 'Agustus', value: 8 },
  { label: 'September', value: 9 },
  { label: 'Oktober', value: 10 },
  { label: 'November', value: 11 },
  { label: 'Desember', value: 12 },
]

const accountOptions = computed(() => [
  { label: 'Semua Rekening', value: null },
  ...accounts.value.map((account) => ({
    label: cleanAccountName(`${account.nama_bank} - ${account.nomor_rekening}`),
    value: account.id,
  })),
])

const branchOptions = computed(() => [
  { label: 'Semua Cabang', value: null },
  ...branches.value.map((b) => ({ label: b.name, value: b.id })),
])

const sourceTypeOptions = [
  { label: 'Semua Tipe', value: null },
  { label: 'Penerimaan Booking', value: 'booking_payment_in' },
  { label: 'Penerimaan Invoice', value: 'invoice_payment_in' },
  { label: 'Pengembalian Dana (Refund)', value: 'refund_out' },
  { label: 'Dana Operasional Keluar', value: 'driver_fund_out' },
  { label: 'Pengembalian Dana Driver', value: 'driver_return_in' },
  { label: 'Pembayaran Rent-to-Rent', value: 'rent_to_rent_payment_out' },
  { label: 'Transfer Masuk', value: 'transfer_in' },
  { label: 'Transfer Keluar', value: 'transfer_out' },
  { label: 'Pemasukan Lain-lain', value: 'other_income' },
  { label: 'Pengeluaran Lain-lain', value: 'other_expense' },
  { label: 'Penyesuaian Saldo', value: 'balance_adjustment' },
  { label: 'Transaksi Void', value: '__void__' },
]

const summary = computed(() => report.value?.summary || {})
const accountRows = computed(() => report.value?.accounts || [])

// Mapping dictionary for raw backend leaks
const sourceLabelMap = {
  driver_return_in: 'Pengembalian Dana Operasional',
  driver_fund_out: 'Pencairan Dana Driver',
  invoice_payment_in: 'Penerimaan Invoice',
  booking_payment_in: 'Penerimaan Booking',
  refund_out: 'Pengembalian Dana (Refund)',
  operational_fund_out: 'Dana Operasional Keluar',
  operational_fund_in: 'Dana Operasional Masuk',
  rent_to_rent_payment_out: 'Pembayaran Rent to Rent',
  account_transaction_in: 'Transfer Masuk',
  account_transaction_out: 'Transfer Keluar',
  booking_payment: 'Penerimaan Booking',
  invoice_payment: 'Penerimaan Invoice',
  refund: 'Pengembalian Dana (Refund)',
  operational_fund: 'Dana Operasional',
  rent_to_rent_payment: 'Pembayaran Rent to Rent',
  account_transaction: 'Transfer Rekening',
  booking_payment_void: 'Void Penerimaan Booking',
  invoice_payment_void: 'Void Penerimaan Invoice',
  driver_fund_void: 'Void Dana Operasional',
  driver_direct_expense_out: 'Pengeluaran Langsung Driver',
  driver_direct_expense_void: 'Void Pengeluaran Driver',
  rent_to_rent_payment_void: 'Void Bayar Rent-to-Rent',
  balance_adjustment: 'Penyesuaian Saldo',
  other_income: 'Pemasukan Lain-lain',
  other_expense: 'Pengeluaran Lain-lain',
  transfer_in: 'Transfer Masuk',
  transfer_out: 'Transfer Keluar',
}

const formatSourceLabel = (label) => {
  if (!label) return '-'
  if (sourceLabelMap[label]) return sourceLabelMap[label]
  return label
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

const cleanAccountName = (name) => {
  if (!name) return '-'
  let cleaned = name.replace(/-\s*-/g, '-').replace(/\s+/g, ' ').trim()
  if (cleaned.startsWith('-')) cleaned = cleaned.replace(/^-\s*/, '')
  if (cleaned.endsWith('-')) cleaned = cleaned.replace(/\s*-$/, '')
  return cleaned
}

const isVoidType = (type) => type && type.endsWith('_void')

const groupAndDeduplicateEntries = (rawEntries) => {
  if (!rawEntries || !rawEntries.length) return []

  const merged = []
  const seenIndices = new Set()

  for (let i = 0; i < rawEntries.length; i++) {
    if (seenIndices.has(i)) continue

    const current = { ...rawEntries[i] }

    let partnerIndex = -1
    for (let j = i + 1; j < rawEntries.length; j++) {
      if (seenIndices.has(j)) continue

      const other = rawEntries[j]
      const sameRef = current.reference && other.reference && current.reference === other.reference
      const sameDate = current.happened_at === other.happened_at
      const sameAbsAmount = Math.abs(Number(current.signed_amount)) === Math.abs(Number(other.signed_amount))

      if (sameRef && sameDate && sameAbsAmount) {
        partnerIndex = j
        break
      }
    }

    if (partnerIndex !== -1) {
      const partner = rawEntries[partnerIndex]
      seenIndices.add(partnerIndex)

      if (current.signed_amount < 0 && partner.signed_amount > 0) {
        current.payment_account_name = `${partner.payment_account_name} ➔ ${current.payment_account_name}`
        current.signed_amount = partner.signed_amount
        current.label = partner.label || current.label
        current.source_type = partner.source_type || current.source_type
      } else if (current.signed_amount > 0 && partner.signed_amount < 0) {
        current.payment_account_name = `${current.payment_account_name} ➔ ${partner.payment_account_name}`
      } else {
        current.payment_account_name = `${current.payment_account_name}, ${partner.payment_account_name}`
      }

      if (current.description !== partner.description && partner.description) {
        current.description = `${current.description} | ${partner.description}`
      }
    }

    merged.push(current)
    seenIndices.add(i)
  }

  return merged
}

const processedEntries = computed(() => {
  let list = report.value?.entries || []

  list = groupAndDeduplicateEntries(list)

  list = list.map(entry => ({
    ...entry,
    label: formatSourceLabel(entry.label || entry.source_type),
    payment_account_name: cleanAccountName(entry.payment_account_name),
    is_void: isVoidType(entry.type),
  }))

  // Source type filter
  if (sourceTypeFilter.value) {
    if (sourceTypeFilter.value === '__void__') {
      list = list.filter(entry => entry.is_void)
    } else {
      list = list.filter(entry => entry.type === sourceTypeFilter.value)
    }
  }

  // Search query filter
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(entry =>
      (entry.reference && entry.reference.toLowerCase().includes(q)) ||
      (entry.description && entry.description.toLowerCase().includes(q)) ||
      (entry.payment_account_name && entry.payment_account_name.toLowerCase().includes(q)) ||
      (entry.label && entry.label.toLowerCase().includes(q))
    )
  }

  return list
})

const paginatedEntries = computed(() => {
  const startIndex = (currentPage.value - 1) * rowsPerPage.value
  const endIndex = startIndex + rowsPerPage.value
  return processedEntries.value.slice(startIndex, endIndex)
})

const kpis = computed(() => {
  const bookingRevenue = Number(summary.value.booking_revenue || 0)
  const rentalIncome = Number(summary.value.rental_income || 0)
  const piutang = bookingRevenue - rentalIncome

  return [
    { label: 'Omzet/Tagihan', value: bookingRevenue, tone: 'neutral', icon: 'pi pi-chart-line' },
    { label: 'Kas Masuk Rental', value: rentalIncome, tone: 'positive', icon: 'pi pi-wallet' },
    { label: 'Piutang (Estimasi)', value: piutang, tone: piutang > 0 ? 'warning' : 'neutral', icon: 'pi pi-exclamation-circle', route: 'ReceivableList' },
    { label: 'Pemasukan Lain-lain', value: summary.value.other_income, tone: 'info', icon: 'pi pi-plus-circle' },
    { label: 'Pengeluaran', value: summary.value.business_expense, tone: 'negative', icon: 'pi pi-minus-circle' },
    { label: 'Net Kas', value: summary.value.net_cash, tone: Number(summary.value.net_cash || 0) >= 0 ? 'positive' : 'negative', icon: 'pi pi-calculator' },
    { label: 'Total Saldo Saat Ini', value: summary.value.total_current_balance, tone: 'neutral', icon: 'pi pi-credit-card' },
  ]
})

// Reconciliation: estimated_opening + net_movement should equal current_balance
const reconciliationOk = (row) => {
  const estimated = Number(row.estimated_opening_balance || 0)
  const netMovement = Number(row.net_movement || 0)
  const currentBalance = Number(row.payment_account?.current_balance || 0)
  return Math.abs((estimated + netMovement) - currentBalance) < 1
}

onMounted(async () => {
  await Promise.all([
    fetchAccounts({ per_page: 100 }),
    fetchBranches({ per_page: 100 }),
    fetchDebts(),
    loadReport(),
  ])
  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
})

const loadReport = async () => {
  try {
    await fetchReport(filters.value)
    currentPage.value = 1
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || 'Gagal memuat laporan', life: 4000 })
  }
}

const resetFilters = () => {
  filters.value = { month: now.getMonth() + 1, year: now.getFullYear(), payment_account_id: null, branch_id: null }
  sourceTypeFilter.value = null
  loadReport()
}

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))

const formatDate = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
}

const sourceSeverity = (sourceType) => {
  const map = {
    booking_payment: 'success',
    booking_payment_in: 'success',
    invoice_payment: 'success',
    invoice_payment_in: 'success',
    refund: 'danger',
    refund_out: 'danger',
    operational_fund: 'danger',
    operational_fund_out: 'danger',
    driver_fund_out: 'danger',
    driver_return_in: 'success',
    rent_to_rent_payment: 'danger',
    rent_to_rent_payment_out: 'danger',
    account_transaction: 'info',
    account_transaction_in: 'info',
    account_transaction_out: 'info',
  }
  return map[sourceType] || 'secondary'
}

const exportExcel = () => {
  const wb = XLSX.utils.book_new()
  const monthLabel = selectedMonthLabel.value
  const year = filters.value.year

  // Sheet 1: Ringkasan KPI
  const kpiRows = [
    ['Laporan Bulanan', `${monthLabel} ${year}`],
    [],
    ['Indikator', 'Nilai (IDR)'],
    ...kpis.value.map(k => [k.label, Number(k.value || 0)]),
  ]
  const wsKpi = XLSX.utils.aoa_to_sheet(kpiRows)
  wsKpi['!cols'] = [{ wch: 28 }, { wch: 20 }]
  XLSX.utils.book_append_sheet(wb, wsKpi, 'Ringkasan')

  // Sheet 2: Saldo per Rekening
  const accHeader = [
    'Rekening', 'No. Rekening', 'Atas Nama', 'Saldo Saat Ini',
    'Est. Saldo Awal', 'Masuk Rental', 'Pemasukan Lain',
    'Refund', 'Dana Operasional', 'Rent-to-Rent', 'Pengeluaran Lain',
    'Transfer Masuk', 'Transfer Keluar', 'Penyesuaian', 'Net Movement', 'Status Rekonsiliasi',
  ]
  const accData = accountRows.value.map(row => [
    cleanAccountName(row.payment_account.nama_bank),
    row.payment_account.nomor_rekening,
    row.payment_account.atas_nama,
    Number(row.payment_account.current_balance || 0),
    Number(row.estimated_opening_balance || 0),
    Number(row.rental_income || 0),
    Number(row.other_income || 0),
    Number(row.refunds || 0),
    Number(row.operational_funds || 0),
    Number(row.rent_to_rent_payments || 0),
    Number(row.other_expense || 0),
    Number(row.transfer_in || 0),
    Number(row.transfer_out || 0),
    Number(row.balance_adjustment || 0),
    Number(row.net_movement || 0),
    reconciliationOk(row) ? 'Balance' : 'Selisih',
  ])
  const wsAcc = XLSX.utils.aoa_to_sheet([accHeader, ...accData])
  wsAcc['!cols'] = accHeader.map((_, i) => ({ wch: i === 0 ? 24 : i <= 2 ? 18 : 16 }))
  XLSX.utils.book_append_sheet(wb, wsAcc, 'Saldo per Rekening')

  // Sheet 3: Detail Mutasi
  const txHeader = ['Tanggal', 'Sumber', 'Rekening', 'Referensi', 'Nominal (IDR)', 'Catatan', 'Void']
  const txData = processedEntries.value.map(entry => [
    entry.happened_at ? new Date(entry.happened_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-',
    entry.label || '-',
    entry.payment_account_name || '-',
    entry.reference || '-',
    Number(entry.signed_amount || 0),
    entry.description || '-',
    entry.is_void ? 'Ya' : 'Tidak',
  ])
  const wsTx = XLSX.utils.aoa_to_sheet([txHeader, ...txData])
  wsTx['!cols'] = [{ wch: 20 }, { wch: 26 }, { wch: 24 }, { wch: 18 }, { wch: 16 }, { wch: 36 }, { wch: 8 }]
  XLSX.utils.book_append_sheet(wb, wsTx, 'Detail Mutasi')

  XLSX.writeFile(wb, `Laporan_Bulanan_${monthLabel}_${year}.xlsx`)

  toast.add({ severity: 'success', summary: 'Berhasil', detail: 'File Excel berhasil diunduh.', life: 3000 })
}

const exportPdfPlaceholder = () => {
  toast.add({
    severity: 'info',
    summary: 'Download PDF',
    detail: 'Fitur Download PDF untuk Laporan Bulanan sedang disiapkan.',
    life: 3000
  })
}

const selectedMonthLabel = computed(() => monthOptions.find((month) => month.value === filters.value.month)?.label || '-')
</script>

<template>
  <div class="page-container transaction-report-page">
    <div class="page-header">
      <div class="header-left">
        <div>
          <h1>Laporan Bulanan</h1>
          <p>Rekap omzet, kas masuk, pengeluaran, transfer, dan posisi saldo rekening.</p>
        </div>
      </div>
      <div class="header-actions"></div>
    </div>

    <ProgressBar v-if="loading" mode="indeterminate" class="report-loader" />

    <section class="filter-bar surface-card report-filter-bar">
      <div class="filter-groups">
        <div class="filter-group" v-if="branchOptions.length > 1">
          <label>Cabang</label>
          <Dropdown v-model="filters.branch_id" :options="branchOptions" optionLabel="label" optionValue="value" placeholder="Semua Cabang" class="branch-filter" />
        </div>
        <div class="filter-group">
          <label>Rekening</label>
          <Dropdown v-model="filters.payment_account_id" :options="accountOptions" optionLabel="label" optionValue="value" placeholder="Semua Rekening" filter class="account-filter" />
        </div>
        <div class="filter-group">
          <label>Bulan</label>
          <Dropdown v-model="filters.month" :options="monthOptions" optionLabel="label" optionValue="value" class="month-picker" />
        </div>
        <div class="filter-group">
          <label>Tahun</label>
          <InputNumber v-model="filters.year" :useGrouping="false" :min="2000" :max="2100" class="year-input" />
        </div>
      </div>
      <div class="filter-actions flex flex-wrap items-center gap-2">
        <button class="btn-pill btn-primary btn-pill-compact" type="button" :disabled="loading" @click="loadReport">
          <i :class="loading ? 'pi pi-spin pi-spinner' : 'pi pi-filter'"></i>
          Filter
        </button>
        <button class="btn-pill btn-secondary btn-pill-compact" type="button" :disabled="loading" @click="resetFilters">
          <i class="pi pi-times"></i>
          Reset
        </button>
        <div class="flex items-center gap-1.5 border-l border-[var(--surface-border)] pl-3 ml-1">
          <button class="btn-pill btn-secondary btn-pill-compact flex items-center gap-1" type="button" @click="exportExcel">
            <i class="pi pi-file-excel text-emerald-600"></i>
            <span>Excel</span>
          </button>
          <button class="btn-pill btn-secondary btn-pill-compact flex items-center gap-1" type="button" @click="exportPdfPlaceholder">
            <i class="pi pi-file-pdf text-rose-600"></i>
            <span>PDF</span>
          </button>
        </div>
      </div>
    </section>

    <section class="kpi-grid">
      <article
        v-for="item in kpis"
        :key="item.label"
        class="kpi-card"
        :class="[item.tone, { 'kpi-card-link': item.route }]"
        @click="item.route && router.push({ name: item.route })"
      >
        <div class="kpi-icon"><i :class="item.icon"></i></div>
        <div>
          <span>{{ item.label }}</span>
          <strong>{{ formatCurrency(item.value) }}</strong>
        </div>
        <i v-if="item.route" class="pi pi-arrow-right kpi-link-arrow"></i>
      </article>
    </section>

    <!-- Rekap Hutang Rent-to-Rent -->
    <section class="app-card report-section r2r-summary-section" v-if="r2rSummary.debt_count > 0 || r2rSummary.remaining_amount > 0">
      <div class="app-section-header report-section-header">
        <div>
          <h2>Rekap Hutang Rent-to-Rent</h2>
          <p>Total kewajiban kepada pemilik rental (rent-to-rent). Hutang ini tidak termasuk dalam kas/pengeluaran bulan ini kecuali sudah dibayarkan.</p>
        </div>
        <button class="btn-pill btn-secondary btn-pill-compact" type="button" @click="router.push({ name: 'RentToRentList' })">
          <i class="pi pi-external-link"></i>
          Lihat Detail
        </button>
      </div>
      <div class="r2r-kpi-row">
        <div class="r2r-kpi-item">
          <span>Total Hutang</span>
          <strong class="font-mono-numeric">{{ formatCurrency(r2rSummary.total_amount) }}</strong>
        </div>
        <div class="r2r-kpi-item positive">
          <span>Sudah Dibayar</span>
          <strong class="font-mono-numeric text-positive">{{ formatCurrency(r2rSummary.paid_amount) }}</strong>
        </div>
        <div class="r2r-kpi-item" :class="{ negative: r2rSummary.remaining_amount > 0 }">
          <span>Sisa Hutang</span>
          <strong class="font-mono-numeric" :class="{ 'text-negative': r2rSummary.remaining_amount > 0 }">{{ formatCurrency(r2rSummary.remaining_amount) }}</strong>
        </div>
        <div class="r2r-kpi-item">
          <span>Jumlah Hutang</span>
          <strong>{{ r2rSummary.debt_count }} booking</strong>
        </div>
        <div class="r2r-kpi-item">
          <span>Pemilik Rental</span>
          <strong>{{ r2rSummary.owner_count }} owner</strong>
        </div>
      </div>
    </section>

    <!-- Saldo per Rekening -->
    <section class="app-card report-section">
      <div class="app-section-header report-section-header">
        <div>
          <h2>Saldo per Rekening</h2>
          <p>{{ report?.period?.label || '-' }}</p>
        </div>
      </div>
      <div v-if="!isMobile" class="table-shell report-table-shell">
        <DataTable :value="accountRows" :loading="loading" responsiveLayout="scroll" class="drent-datatable" stripedRows>
          <template #empty>
            <div class="empty-state">Tidak ada rekening pada periode ini.</div>
          </template>
          <Column header="Rekening" style="min-width: 15rem; position: sticky; left: 0; background: inherit; z-index: 1;">
            <template #body="{ data }">
              <strong>{{ cleanAccountName(data.payment_account.nama_bank) }}</strong>
              <div class="text-secondary text-xs">{{ data.payment_account.nomor_rekening }} - {{ data.payment_account.atas_nama }}</div>
            </template>
          </Column>
          <Column header="Saldo Saat Ini" style="min-width: 11rem">
            <template #body="{ data }">
              <span class="font-mono-numeric flex items-center gap-1" :class="{ 'text-red-600 font-semibold': data.payment_account.current_balance < 0 }">
                {{ formatCurrency(data.payment_account.current_balance) }}
                <i v-if="data.payment_account.current_balance < 0" class="pi pi-exclamation-circle text-xs text-red-500 cursor-help" title="Saldo negatif mengindikasikan akun kewajiban / hutang (liability/payable account) atau penarikan berlebih (overdraft)."></i>
              </span>
            </template>
          </Column>
          <Column field="estimated_opening_balance" header="Estimasi Saldo Awal" style="min-width: 12rem">
            <template #body="{ data }">{{ formatCurrency(data.estimated_opening_balance) }}</template>
          </Column>
          <Column field="rental_income" header="Masuk Rental" style="min-width: 10rem">
            <template #body="{ data }"><span class="amount positive">{{ formatCurrency(data.rental_income) }}</span></template>
          </Column>
          <Column field="other_income" header="Pemasukan Lain" style="min-width: 10rem">
            <template #body="{ data }"><span class="amount positive">{{ formatCurrency(data.other_income) }}</span></template>
          </Column>
          <Column header="Refund" style="min-width: 9rem">
            <template #body="{ data }">
              <span v-if="data.refunds > 0" class="amount negative">-{{ formatCurrency(data.refunds) }}</span>
              <span v-else class="text-secondary">-</span>
            </template>
          </Column>
          <Column header="Dana Operasional" style="min-width: 10rem">
            <template #body="{ data }">
              <span v-if="data.operational_funds > 0" class="amount negative">-{{ formatCurrency(data.operational_funds) }}</span>
              <span v-else class="text-secondary">-</span>
            </template>
          </Column>
          <Column header="Rent-to-Rent" style="min-width: 10rem">
            <template #body="{ data }">
              <span v-if="data.rent_to_rent_payments > 0" class="amount negative">-{{ formatCurrency(data.rent_to_rent_payments) }}</span>
              <span v-else class="text-secondary">-</span>
            </template>
          </Column>
          <Column header="Pengeluaran Lain" style="min-width: 10rem">
            <template #body="{ data }">
              <span v-if="data.other_expense > 0" class="amount negative">-{{ formatCurrency(data.other_expense) }}</span>
              <span v-else class="text-secondary">-</span>
            </template>
          </Column>
          <Column field="transfer_in" header="Transfer Masuk" style="min-width: 10rem">
            <template #body="{ data }">{{ formatCurrency(data.transfer_in) }}</template>
          </Column>
          <Column field="transfer_out" header="Transfer Keluar" style="min-width: 10rem">
            <template #body="{ data }">{{ formatCurrency(data.transfer_out) }}</template>
          </Column>
          <Column header="Penyesuaian" style="min-width: 10rem">
            <template #body="{ data }">
              <span v-if="data.balance_adjustment !== 0" :class="{ 'amount positive': data.balance_adjustment > 0, 'amount negative': data.balance_adjustment < 0 }">
                {{ formatCurrency(data.balance_adjustment) }}
              </span>
              <span v-else class="text-secondary">-</span>
            </template>
          </Column>
          <Column field="net_movement" header="Net Movement" style="min-width: 10rem">
            <template #body="{ data }">
              <span class="amount" :class="{ negative: data.net_movement < 0, positive: data.net_movement > 0 }">{{ formatCurrency(data.net_movement) }}</span>
            </template>
          </Column>
          <Column header="Rekonsiliasi" style="min-width: 8rem">
            <template #body="{ data }">
              <span v-if="reconciliationOk(data)" class="recon-badge ok" title="Saldo Awal + Net Movement = Saldo Saat Ini">
                <i class="pi pi-check-circle"></i> Balance
              </span>
              <span v-else class="recon-badge fail" :title="`Estimasi: ${formatCurrency(data.estimated_opening_balance)} + ${formatCurrency(data.net_movement)} ≠ ${formatCurrency(data.payment_account.current_balance)}`">
                <i class="pi pi-times-circle"></i> Selisih
              </span>
            </template>
          </Column>
        </DataTable>
      </div>
      <div v-else class="mobile-card-list report-mobile-list">
        <div v-if="!accountRows.length" class="app-muted-panel mobile-state">Tidak ada rekening pada periode ini.</div>
        <article v-else v-for="row in accountRows" :key="row.payment_account?.id" class="report-mobile-card app-card">
          <div class="mobile-card-head">
            <div>
              <h3>{{ cleanAccountName(row.payment_account.nama_bank) }}</h3>
              <p>{{ row.payment_account.nomor_rekening }} - {{ row.payment_account.atas_nama }}</p>
            </div>
            <div class="flex flex-col items-end gap-1">
              <strong class="amount" :class="{ negative: row.net_movement < 0, positive: row.net_movement > 0 }">{{ formatCurrency(row.net_movement) }}</strong>
              <span v-if="reconciliationOk(row)" class="recon-badge ok"><i class="pi pi-check-circle"></i> Balance</span>
              <span v-else class="recon-badge fail"><i class="pi pi-times-circle"></i> Selisih</span>
            </div>
          </div>
          <div class="mobile-info-grid">
            <div>
              <span>Saldo Saat Ini</span>
              <strong class="font-mono-numeric flex items-center gap-1" :class="{ 'text-red-600 font-semibold': row.payment_account.current_balance < 0 }">
                {{ formatCurrency(row.payment_account.current_balance) }}
                <i v-if="row.payment_account.current_balance < 0" class="pi pi-exclamation-circle text-[10px] text-red-500 cursor-help" title="Saldo negatif mengindikasikan akun kewajiban / hutang."></i>
              </strong>
            </div>
            <div><span>Estimasi Awal</span><strong>{{ formatCurrency(row.estimated_opening_balance) }}</strong></div>
            <div><span>Masuk Rental</span><strong class="amount positive">{{ formatCurrency(row.rental_income) }}</strong></div>
            <div><span>Pemasukan Lain</span><strong class="amount positive">{{ formatCurrency(row.other_income) }}</strong></div>
            <div><span>Refund</span><strong :class="row.refunds > 0 ? 'amount negative' : ''">{{ row.refunds > 0 ? '-' + formatCurrency(row.refunds) : '-' }}</strong></div>
            <div><span>Dana Operasional</span><strong :class="row.operational_funds > 0 ? 'amount negative' : ''">{{ row.operational_funds > 0 ? '-' + formatCurrency(row.operational_funds) : '-' }}</strong></div>
            <div><span>Rent-to-Rent</span><strong :class="row.rent_to_rent_payments > 0 ? 'amount negative' : ''">{{ row.rent_to_rent_payments > 0 ? '-' + formatCurrency(row.rent_to_rent_payments) : '-' }}</strong></div>
            <div><span>Pengeluaran Lain</span><strong :class="row.other_expense > 0 ? 'amount negative' : ''">{{ row.other_expense > 0 ? '-' + formatCurrency(row.other_expense) : '-' }}</strong></div>
          </div>
        </article>
      </div>
    </section>

    <!-- Detail Mutasi -->
    <section class="app-card report-section">
      <div class="app-section-header report-section-header flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2>Detail Mutasi</h2>
          <p>Pembayaran rental, pengeluaran operasional, transfer rekening, dan transaksi lain-lain.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <!-- Filter Tipe Transaksi -->
          <Dropdown
            v-model="sourceTypeFilter"
            :options="sourceTypeOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Semua Tipe"
            class="source-type-filter"
            @change="currentPage = 1"
          />
          <!-- Search -->
          <span class="p-input-icon-left w-full md:w-64" style="position: relative; display: inline-block;">
            <i class="pi pi-search text-secondary" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-secondary);"></i>
            <InputText
              v-model="searchQuery"
              placeholder="Cari Referensi / Catatan..."
              class="w-full font-body text-xs py-1.5 pl-8 pr-3 border border-[var(--surface-border)] rounded-[10px] bg-[var(--surface-default)]"
              @input="currentPage = 1"
            />
          </span>
        </div>
      </div>

      <div v-if="!isMobile" class="table-shell report-table-shell">
        <DataTable :value="paginatedEntries" :loading="loading" responsiveLayout="scroll" class="drent-datatable" stripedRows>
          <template #empty>
            <div class="empty-state">Belum ada mutasi pada periode ini.</div>
          </template>
          <Column field="happened_at" header="Tanggal" style="min-width: 11rem">
            <template #body="{ data }">{{ formatDate(data.happened_at) }}</template>
          </Column>
          <Column field="label" header="Sumber" style="min-width: 14rem">
            <template #body="{ data }">
              <div class="flex items-center gap-1.5 flex-wrap">
                <Tag :value="data.label" :severity="sourceSeverity(data.type)" />
                <span v-if="data.is_void" class="void-badge">VOID</span>
              </div>
            </template>
          </Column>
          <Column field="payment_account_name" header="Rekening" style="min-width: 13rem" />
          <Column field="reference" header="Referensi" style="min-width: 12rem">
            <template #body="{ data }">{{ data.reference || '-' }}</template>
          </Column>
          <Column field="signed_amount" header="Nominal" style="min-width: 10rem">
            <template #body="{ data }">
              <span class="amount" :class="{ negative: data.signed_amount < 0, positive: data.signed_amount > 0, 'opacity-50': data.is_void }">{{ formatCurrency(data.signed_amount) }}</span>
            </template>
          </Column>
          <Column field="description" header="Catatan" style="min-width: 15rem">
            <template #body="{ data }">{{ data.description || '-' }}</template>
          </Column>
        </DataTable>

        <div class="mt-4 flex justify-between items-center" v-if="processedEntries.length > 0">
          <span class="text-xs text-secondary font-body">
            Menampilkan {{ (currentPage - 1) * rowsPerPage + 1 }} - {{ Math.min(currentPage * rowsPerPage, processedEntries.length) }} dari {{ processedEntries.length }} baris
          </span>
          <Paginator
            :rows="rowsPerPage"
            :totalRecords="processedEntries.length"
            :first="(currentPage - 1) * rowsPerPage"
            @page="(e) => currentPage = e.page + 1"
            class="drent-paginator"
          />
        </div>
      </div>
      <div v-else class="mobile-card-list report-mobile-list">
        <div v-if="!processedEntries.length" class="app-muted-panel mobile-state">Belum ada mutasi pada periode ini.</div>
        <template v-else>
          <article v-for="entry in paginatedEntries" :key="`${entry.source_type}-${entry.reference}-${entry.happened_at}-${entry.signed_amount}`" class="report-mobile-card app-card" :class="{ 'void-entry': entry.is_void }">
            <div class="mobile-card-head">
              <div>
                <h3>{{ entry.payment_account_name || '-' }}</h3>
                <p>{{ formatDate(entry.happened_at) }}</p>
              </div>
              <div class="flex flex-col items-end gap-1">
                <Tag :value="entry.label" :severity="sourceSeverity(entry.type)" />
                <span v-if="entry.is_void" class="void-badge">VOID</span>
              </div>
            </div>
            <div class="mobile-info-grid">
              <div><span>Nominal</span><strong class="amount" :class="{ negative: entry.signed_amount < 0, positive: entry.signed_amount > 0, 'opacity-50': entry.is_void }">{{ formatCurrency(entry.signed_amount) }}</strong></div>
              <div><span>Referensi</span><strong>{{ entry.reference || '-' }}</strong></div>
              <div class="span-2"><span>Catatan</span><strong>{{ entry.description || '-' }}</strong></div>
            </div>
          </article>

          <div class="mt-4 flex flex-col items-center gap-2" v-if="processedEntries.length > 0">
            <span class="text-xs text-secondary font-body">
              Menampilkan {{ (currentPage - 1) * rowsPerPage + 1 }} - {{ Math.min(currentPage * rowsPerPage, processedEntries.length) }} dari {{ processedEntries.length }} baris
            </span>
            <Paginator
              :rows="rowsPerPage"
              :totalRecords="processedEntries.length"
              :first="(currentPage - 1) * rowsPerPage"
              @page="(e) => currentPage = e.page + 1"
              class="drent-paginator w-full"
            />
          </div>
        </template>
      </div>
    </section>
  </div>
</template>

<style scoped>
.transaction-report-page { display: flex; flex-direction: column; gap: var(--space-lg); background: var(--page-bg); }
.transaction-report-page .page-header { margin-bottom: var(--space-md); }
.month-picker { min-width: 10rem; }
.year-input { width: 7rem; }
.report-loader { height: 4px; }
.report-filter-bar { margin-bottom: 0; }
.account-filter { min-width: 20rem; }
.branch-filter { min-width: 13rem; }
.source-type-filter { min-width: 14rem; font-size: 0.8rem; }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
.kpi-card { padding: 14px; display: flex; align-items: center; gap: 12px; position: relative; }
.kpi-card-link { cursor: pointer; transition: box-shadow 0.15s; }
.kpi-card-link:hover { box-shadow: 0 0 0 2px var(--primary-color, #6366f1); }
.kpi-link-arrow { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 11px; color: var(--text-tertiary); }
.kpi-card span { display: block; color: var(--text-secondary); font-size: 11px; font-weight: 700; text-transform: uppercase; }
.kpi-card strong { display: block; margin-top: 4px; font-family: var(--font-mono); font-size: 16px; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.kpi-icon { width: 36px; height: 36px; border-radius: var(--radius-default); display: grid; place-items: center; background: var(--card-bg); color: var(--text-secondary); flex-shrink: 0; }
.kpi-card.positive .kpi-icon { background: rgba(39, 168, 88, .12); color: var(--positive); }
.kpi-card.negative .kpi-icon { background: rgba(229, 83, 75, .12); color: var(--negative); }
.kpi-card.info .kpi-icon { background: rgba(11, 122, 138, .12); color: var(--info-cyan); }
.kpi-card.warning .kpi-icon { background: #FDF4D9; color: #8C660A; }

/* R2R Summary section */
.r2r-summary-section .app-section-header { display: flex; justify-content: space-between; align-items: flex-start; gap: var(--space-md); }
.r2r-kpi-row { display: flex; flex-wrap: wrap; gap: var(--space-lg); padding: var(--space-md) var(--space-md) var(--space-md); border-top: 1px solid var(--surface-border); margin-top: var(--space-md); }
.r2r-kpi-item { display: flex; flex-direction: column; gap: 4px; min-width: 120px; }
.r2r-kpi-item span { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); }
.r2r-kpi-item strong { font-family: var(--font-mono); font-size: 15px; font-variant-numeric: tabular-nums; color: var(--text-primary); }
.text-positive { color: var(--positive); }
.text-negative { color: var(--negative); }

/* Reconciliation badges */
.recon-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 6px; white-space: nowrap; }
.recon-badge.ok { background: rgba(39, 168, 88, .12); color: var(--positive, #27a858); }
.recon-badge.fail { background: rgba(229, 83, 75, .12); color: var(--negative, #e5534b); }

/* Void badge */
.void-badge { display: inline-block; font-size: 10px; font-weight: 800; padding: 1px 6px; border-radius: 4px; background: rgba(0,0,0,.08); color: var(--text-tertiary); letter-spacing: 0.05em; text-transform: uppercase; }
.void-entry { opacity: 0.7; }

.report-section { overflow: hidden; }
.report-section-header h2 { margin: 0; }
.report-section-header p { margin: 3px 0 0; color: var(--text-secondary); font-size: 12px; }
.report-table-shell { display: block; padding: var(--space-md); }
.empty-state { padding: 38px; text-align: center; color: var(--text-tertiary); }
.text-xs { font-size: .78rem; }
.text-secondary { color: var(--text-secondary); }
.amount { font-weight: 800; font-variant-numeric: tabular-nums; }
.amount.negative { color: var(--negative); }
.amount.positive { color: var(--positive); }
.mobile-card-list { display: flex; flex-direction: column; gap: var(--space-md); padding: var(--space-md); }
.mobile-state { display: flex; justify-content: center; padding: var(--space-xl); color: var(--text-secondary); }
.report-mobile-card { padding: var(--space-lg); }
.mobile-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: var(--space-md); }
.mobile-card-head h3 { margin: 0; font-family: var(--font-headline); font-size: 15px; }
.mobile-card-head p { margin: 4px 0 0; color: var(--text-secondary); font-size: 12px; }
.mobile-card-head strong { font-family: var(--font-mono); white-space: nowrap; }
.mobile-info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-md); margin-top: var(--space-md); }
.mobile-info-grid div { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.mobile-info-grid span { color: var(--text-tertiary); font-size: 11px; font-weight: 700; }
.mobile-info-grid strong { overflow-wrap: anywhere; }
.span-2 { grid-column: span 2; }
@media (max-width: 980px) {
  .header-actions { justify-content: flex-start; width: 100%; }
  .kpi-grid { grid-template-columns: 1fr; }
  .account-filter { min-width: 100%; }
  .branch-filter { min-width: 100%; }
  .mobile-info-grid { grid-template-columns: 1fr; }
  .span-2 { grid-column: auto; }
  .r2r-summary-section .app-section-header { flex-direction: column; }
  .r2r-kpi-row { gap: var(--space-md); }
}
</style>
