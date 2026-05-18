<script setup>
import { computed, ref } from 'vue'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'

// ─── Dummy Data ───────────────────────────────────────────────────────────────
const dummyTransactions = [
  {
    id: 1,
    kode_booking: 'BKG-2025-0001',
    tgl_booking: '2025-05-01 09:30:00',
    branch: 'Cabang Jakarta',
    created_by: 'Rina (CS)',
    customer_nama: 'Budi Santoso',
    customer_status: 'Member Aktif',
    unit_label: 'Toyota Avanza',
    no_polisi: 'B 1234 XYZ',
    pemilik_unit: 'Sendiri',
    tgl_sewa: '2025-05-02 07:00:00',
    tgl_kembali: '2025-05-05 23:59:00',
    lama_sewa: 3,
    paket: 'With Driver',
    driver: 'Ahmad Farid',
    pricing_mode: 'all_in',
    harga_total: 1500000,
    total_bayar: 1500000,
    sisa_piutang: 0,
    status: 'selesai',
    is_late: false,
    ada_refund: false,
  },
  {
    id: 2,
    kode_booking: 'BKG-2025-0002',
    tgl_booking: '2025-05-03 14:00:00',
    branch: 'Cabang Jakarta',
    created_by: 'Doni (CS)',
    customer_nama: 'Sari Dewi',
    customer_status: 'Non-Member',
    unit_label: 'Honda Innova',
    no_polisi: 'B 5678 ABC',
    pemilik_unit: 'Rental Mitra A',
    tgl_sewa: '2025-05-04 07:00:00',
    tgl_kembali: '2025-05-06 23:59:00',
    lama_sewa: 2,
    paket: 'Lepas Kunci',
    driver: null,
    pricing_mode: 'non_all_in',
    harga_total: 900000,
    total_bayar: 500000,
    sisa_piutang: 400000,
    status: 'selesai',
    is_late: false,
    ada_refund: false,
  },
  {
    id: 3,
    kode_booking: 'BKG-2025-0003',
    tgl_booking: '2025-05-05 10:15:00',
    branch: 'Cabang Bandung',
    created_by: 'Maya (CS)',
    customer_nama: 'Hendra Wijaya',
    customer_status: 'Member Aktif',
    unit_label: 'Mitsubishi Xpander',
    no_polisi: 'D 9012 DEF',
    pemilik_unit: 'Sendiri',
    tgl_sewa: '2025-05-06 07:00:00',
    tgl_kembali: '2025-05-09 23:59:00',
    lama_sewa: 3,
    paket: 'With Driver',
    driver: 'Budi Setiawan',
    pricing_mode: 'all_in',
    harga_total: 2100000,
    total_bayar: 1050000,
    sisa_piutang: 1050000,
    status: 'rental_unit',
    is_late: false,
    ada_refund: false,
  },
  {
    id: 4,
    kode_booking: 'BKG-2025-0004',
    tgl_booking: '2025-05-06 08:00:00',
    branch: 'Cabang Jakarta',
    created_by: 'Rina (CS)',
    customer_nama: 'Lestari Putri',
    customer_status: 'Member Aktif',
    unit_label: 'Toyota Fortuner',
    no_polisi: 'B 2222 GHI',
    pemilik_unit: 'Rental Mitra B',
    tgl_sewa: '2025-05-03 07:00:00',
    tgl_kembali: '2025-05-06 23:59:00',
    lama_sewa: 3,
    paket: 'Lepas Kunci',
    driver: null,
    pricing_mode: 'all_in',
    harga_total: 3600000,
    total_bayar: 3600000,
    sisa_piutang: 0,
    status: 'selesai',
    is_late: true,
    ada_refund: true,
  },
  {
    id: 5,
    kode_booking: 'BKG-2025-0005',
    tgl_booking: '2025-05-08 11:00:00',
    branch: 'Cabang Surabaya',
    created_by: 'Andri (CS)',
    customer_nama: 'Teguh Prasetyo',
    customer_status: 'Non-Member',
    unit_label: 'Daihatsu Terios',
    no_polisi: 'L 3333 JKL',
    pemilik_unit: 'Sendiri',
    tgl_sewa: '2025-05-09 07:00:00',
    tgl_kembali: '2025-05-11 23:59:00',
    lama_sewa: 2,
    paket: 'Lepas Kunci',
    driver: null,
    pricing_mode: 'non_all_in',
    harga_total: 700000,
    total_bayar: 350000,
    sisa_piutang: 350000,
    status: 'confirm',
    is_late: false,
    ada_refund: false,
  },
  {
    id: 6,
    kode_booking: 'BKG-2025-0006',
    tgl_booking: '2025-05-09 13:00:00',
    branch: 'Cabang Bandung',
    created_by: 'Maya (CS)',
    customer_nama: 'Andi Kusuma',
    customer_status: 'Member Aktif',
    unit_label: 'Toyota Avanza',
    no_polisi: 'D 4444 MNO',
    pemilik_unit: 'Sendiri',
    tgl_sewa: '2025-05-10 07:00:00',
    tgl_kembali: '2025-05-12 23:59:00',
    lama_sewa: 2,
    paket: 'With Driver',
    driver: 'Rudi Hermawan',
    pricing_mode: 'all_in',
    harga_total: 1200000,
    total_bayar: 600000,
    sisa_piutang: 600000,
    status: 'waiting_list',
    is_late: false,
    ada_refund: false,
  },
]

// ─── Filters ──────────────────────────────────────────────────────────────────
const filters = ref({
  search: '',
  branch: null,
  status: null,
  paket: null,
  pemilik: null,
  date_from: null,
  date_to: null,
})

const branchOptions = [
  { label: 'Semua Branch', value: null },
  { label: 'Cabang Jakarta', value: 'jakarta' },
  { label: 'Cabang Bandung', value: 'bandung' },
  { label: 'Cabang Surabaya', value: 'surabaya' },
]

const statusOptions = [
  { label: 'Semua Status', value: null },
  { label: 'Follow Up', value: 'follow_up' },
  { label: 'Confirm', value: 'confirm' },
  { label: 'Waiting List', value: 'waiting_list' },
  { label: 'Rental Unit', value: 'rental_unit' },
  { label: 'Selesai', value: 'selesai' },
  { label: 'Batal', value: 'batal' },
]

const paketOptions = [
  { label: 'Semua Paket', value: null },
  { label: 'With Driver', value: 'with_driver' },
  { label: 'Lepas Kunci', value: 'lepas_kunci' },
]

const pemilikOptions = [
  { label: 'Semua Pemilik', value: null },
  { label: 'Unit Sendiri', value: 'sendiri' },
  { label: 'Rent-to-Rent', value: 'r2r' },
]

// ─── Summary ──────────────────────────────────────────────────────────────────
const totalTransaksi = computed(() => dummyTransactions.length)
const totalRevenue = computed(() => dummyTransactions.reduce((s, t) => s + t.harga_total, 0))
const totalTerbayar = computed(() => dummyTransactions.reduce((s, t) => s + t.total_bayar, 0))
const totalPiutang = computed(() => dummyTransactions.reduce((s, t) => s + t.sisa_piutang, 0))

// ─── Helpers ─────────────────────────────────────────────────────────────────
const formatCurrency = (v) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v || 0)

const formatDateTime = (v) => {
  if (!v) return '-'
  const d = new Date(v)
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
    + ' ' + d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

const statusLabel = (s) => ({
  follow_up: 'Follow Up', confirm: 'Confirm', waiting_list: 'Waiting List',
  rental_unit: 'Rental Unit', selesai: 'Selesai', batal: 'Batal',
}[s] || s)

const statusSeverity = (s) => ({
  follow_up: 'warn', confirm: 'info', waiting_list: 'secondary',
  rental_unit: 'success', selesai: 'contrast', batal: 'danger',
}[s] || 'secondary')

const pricingLabel = (m) => m === 'all_in' ? 'All In' : 'Non All In'

const applyFilters = () => { /* dummy */ }
const resetFilters = () => {
  filters.value = { search: '', branch: null, status: null, paket: null, pemilik: null, date_from: null, date_to: null }
}
const exportCsv = () => alert('Export CSV (akan diimplementasi)')
const exportPdf = () => alert('Export PDF (akan diimplementasi)')
</script>

<template>
  <div class="page-container">

    <!-- Header -->
    <div class="detail-page-header">
      <div class="header-left">
        <h1 class="text-h1">Laporan Transaksi</h1>
        <p class="text-secondary text-xs">Rekap seluruh transaksi sewa dengan filter status, branch, periode, dan unit.</p>
      </div>
      <div class="header-actions">
        <button class="btn-pill btn-secondary btn-pill-compact" @click="exportCsv">
          <i class="pi pi-file-excel"></i>
          Export CSV
        </button>
        <button class="btn-pill btn-primary btn-pill-compact" @click="exportPdf">
          <i class="pi pi-file-pdf"></i>
          Export PDF
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="report-summary-grid">
      <div class="report-kpi-card">
        <div class="kpi-icon"><i class="pi pi-list"></i></div>
        <div class="kpi-body">
          <span class="kpi-label">Total Transaksi</span>
          <span class="kpi-value">{{ totalTransaksi }}</span>
        </div>
      </div>
      <div class="report-kpi-card">
        <div class="kpi-icon kpi-icon-revenue"><i class="pi pi-wallet"></i></div>
        <div class="kpi-body">
          <span class="kpi-label">Total Revenue</span>
          <span class="kpi-value kpi-value-mono">{{ formatCurrency(totalRevenue) }}</span>
        </div>
      </div>
      <div class="report-kpi-card">
        <div class="kpi-icon kpi-icon-paid"><i class="pi pi-check-circle"></i></div>
        <div class="kpi-body">
          <span class="kpi-label">Total Terbayar</span>
          <span class="kpi-value kpi-value-mono text-positive">{{ formatCurrency(totalTerbayar) }}</span>
        </div>
      </div>
      <div class="report-kpi-card">
        <div class="kpi-icon kpi-icon-debt"><i class="pi pi-exclamation-circle"></i></div>
        <div class="kpi-body">
          <span class="kpi-label">Total Piutang</span>
          <span class="kpi-value kpi-value-mono text-negative">{{ formatCurrency(totalPiutang) }}</span>
        </div>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar surface-card">
      <div class="filter-groups">
        <div class="filter-group filter-group-wide">
          <label>Pencarian</label>
          <span class="filter-search">
            <i class="pi pi-search"></i>
            <InputText
              v-model="filters.search"
              placeholder="Kode booking, konsumen, unit..."
              class="w-full"
              @keyup.enter="applyFilters"
            />
          </span>
        </div>
        <div class="filter-group">
          <label>Branch</label>
          <Dropdown
            v-model="filters.branch"
            :options="branchOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Semua"
            class="w-full"
          />
        </div>
        <div class="filter-group">
          <label>Status</label>
          <Dropdown
            v-model="filters.status"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Semua"
            class="w-full"
          />
        </div>
        <div class="filter-group">
          <label>Paket</label>
          <Dropdown
            v-model="filters.paket"
            :options="paketOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Semua"
            class="w-full"
          />
        </div>
        <div class="filter-group">
          <label>Pemilik Unit</label>
          <Dropdown
            v-model="filters.pemilik"
            :options="pemilikOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Semua"
            class="w-full"
          />
        </div>
        <div class="filter-group">
          <label>Mulai</label>
          <DatePicker v-model="filters.date_from" dateFormat="yy-mm-dd" placeholder="Dari" class="w-full" />
        </div>
        <div class="filter-group">
          <label>Sampai</label>
          <DatePicker v-model="filters.date_to" dateFormat="yy-mm-dd" placeholder="Sampai" class="w-full" />
        </div>
      </div>
      <div class="filter-actions">
        <button class="btn-pill btn-secondary btn-pill-compact" @click="resetFilters">
          <i class="pi pi-refresh"></i>
          Reset
        </button>
        <button class="btn-pill btn-primary btn-pill-compact" @click="applyFilters">
          <i class="pi pi-filter"></i>
          Filter
        </button>
      </div>
    </div>

    <!-- Table -->
    <DataTable
      :value="dummyTransactions"
      dataKey="id"
      responsiveLayout="scroll"
      class="drent-datatable"
    >
      <!-- Booking -->
      <Column header="Booking" style="min-width: 12rem">
        <template #body="{ data }">
          <div class="font-semibold text-xs">{{ data.kode_booking }}</div>
          <div class="text-xs text-tertiary mt-1">{{ formatDateTime(data.tgl_booking) }}</div>
          <div class="text-xs text-tertiary">{{ data.branch }}</div>
          <div class="text-xs text-secondary mt-1">oleh {{ data.created_by }}</div>
        </template>
      </Column>

      <!-- Status -->
      <Column header="Status" style="min-width: 9rem">
        <template #body="{ data }">
          <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
          <Tag v-if="data.is_late" value="Terlambat" severity="danger" class="mt-1" />
          <Tag v-if="data.ada_refund" value="Ada Refund" severity="warn" class="mt-1" />
        </template>
      </Column>

      <!-- Konsumen -->
      <Column header="Konsumen" style="min-width: 12rem">
        <template #body="{ data }">
          <div class="font-semibold text-xs">{{ data.customer_nama }}</div>
          <div class="text-xs text-secondary mt-1">{{ data.customer_status }}</div>
        </template>
      </Column>

      <!-- Unit & Periode -->
      <Column header="Unit & Periode" style="min-width: 16rem">
        <template #body="{ data }">
          <div class="font-semibold text-xs">{{ data.unit_label }}</div>
          <div class="font-mono-numeric text-xs text-secondary">{{ data.no_polisi }}</div>
          <div class="text-xs text-tertiary mt-1">
            {{ data.pemilik_unit === 'Sendiri' ? 'Unit Sendiri' : `R2R — ${data.pemilik_unit}` }}
          </div>
          <div class="text-xs text-secondary mt-1">
            {{ formatDateTime(data.tgl_sewa) }}
            <span class="text-tertiary"> → </span>
            {{ formatDateTime(data.tgl_kembali) }}
          </div>
          <div class="text-xs text-tertiary">{{ data.lama_sewa }} Hari · {{ data.paket }}</div>
          <div v-if="data.driver" class="text-xs text-secondary mt-1">
            <i class="pi pi-user" style="font-size: 10px"></i> {{ data.driver }}
          </div>
        </template>
      </Column>

      <!-- Pricing Mode -->
      <Column header="Mode" style="min-width: 7rem">
        <template #body="{ data }">
          <span class="status-badge" :class="data.pricing_mode === 'all_in' ? 'badge-info' : 'badge-neutral'">
            {{ pricingLabel(data.pricing_mode) }}
          </span>
        </template>
      </Column>

      <!-- Keuangan -->
      <Column header="Keuangan" style="min-width: 14rem">
        <template #body="{ data }">
          <div class="amount-stack">
            <div class="amount-row">
              <span class="amount-label">Total</span>
              <span class="font-mono-numeric text-xs font-semibold">{{ formatCurrency(data.harga_total) }}</span>
            </div>
            <div class="amount-row">
              <span class="amount-label">Terbayar</span>
              <span class="font-mono-numeric text-xs text-positive">{{ formatCurrency(data.total_bayar) }}</span>
            </div>
            <div class="amount-row">
              <span class="amount-label">Sisa Piutang</span>
              <span class="font-mono-numeric text-xs" :class="data.sisa_piutang > 0 ? 'text-negative' : 'text-tertiary'">
                {{ formatCurrency(data.sisa_piutang) }}
              </span>
            </div>
          </div>
        </template>
      </Column>
    </DataTable>

    <!-- Footer Summary Row -->
    <div class="report-footer-summary surface-card">
      <span class="text-xs text-secondary">Total <strong>{{ totalTransaksi }}</strong> transaksi</span>
      <div class="footer-amounts">
        <span class="text-xs text-secondary">Revenue: <strong class="font-mono-numeric">{{ formatCurrency(totalRevenue) }}</strong></span>
        <span class="text-xs text-positive">Terbayar: <strong class="font-mono-numeric">{{ formatCurrency(totalTerbayar) }}</strong></span>
        <span class="text-xs text-negative">Piutang: <strong class="font-mono-numeric">{{ formatCurrency(totalPiutang) }}</strong></span>
      </div>
    </div>

  </div>
</template>

<style scoped>
/* ── Summary KPI Grid ───────────────────────────── */
.report-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 16px;
}

.report-kpi-card {
  background: var(--surface-default, #fff);
  border: 1px solid var(--surface-border, #DDE1EE);
  border-radius: 10px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 1px 3px 0 rgba(26,29,46,.08);
}

.kpi-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--card-bg, #F0F2F8);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 16px;
  color: var(--text-secondary, #5A6070);
}
.kpi-icon-revenue { background: #E1F4F6; color: #0B7A8A; }
.kpi-icon-paid    { background: #E6F6EC; color: #27A858; }
.kpi-icon-debt    { background: #FCEAE9; color: #E5534B; }

.kpi-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.kpi-label {
  font-family: 'DM Sans', sans-serif;
  font-size: 11px;
  color: var(--text-secondary, #5A6070);
}
.kpi-value {
  font-family: 'Sora', sans-serif;
  font-size: 18px;
  font-weight: 700;
  color: var(--text-primary, #1A1D2E);
  white-space: nowrap;
}
.kpi-value-mono {
  font-family: 'JetBrains Mono', monospace;
  font-size: 14px;
}

/* ── Amount Stack in Table ──────────────────────── */
.amount-stack {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.amount-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.amount-label {
  font-family: 'DM Sans', sans-serif;
  font-size: 11px;
  color: var(--text-tertiary, #8A92A6);
  flex-shrink: 0;
}

/* ── Status Badges ──────────────────────────────── */
.status-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 6px;
  font-family: 'DM Sans', sans-serif;
  font-size: 11px;
  font-weight: 600;
}
.badge-info    { background: #E1F4F6; color: #085A66; }
.badge-neutral { background: #E4E8F3; color: #4A5060; }

/* ── Footer Summary ─────────────────────────────── */
.report-footer-summary {
  margin-top: 8px;
  padding: 12px 16px;
  border-radius: 10px;
  background: var(--surface-default, #fff);
  border: 1px solid var(--surface-border, #DDE1EE);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.footer-amounts {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}

/* ── Color helpers ──────────────────────────────── */
.text-positive { color: #27A858; }
.text-negative { color: #E5534B; }

/* ── Responsive ─────────────────────────────────── */
@media (max-width: 900px) {
  .report-summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 560px) {
  .report-summary-grid {
    grid-template-columns: 1fr;
  }
}
</style>
