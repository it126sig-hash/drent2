<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { usePaymentAccount } from '../../composables/usePaymentAccount'
import { usePaymentAccountTransaction } from '../../composables/usePaymentAccountTransaction'
import { useFinanceCategory } from '../../composables/useFinanceCategory'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Paginator from 'primevue/paginator'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import ToggleButton from 'primevue/togglebutton'

const toast = useToast()
const confirm = useConfirm()
const { accounts, fetchAll: fetchAccounts } = usePaymentAccount()
const { transactions, loading, actionLoading, pagination, fetchAll, transfer, other } = usePaymentAccountTransaction()
const {
  categories,
  loading: categoryLoading,
  fetchAll: fetchCategories,
  store: storeCategory,
  update: updateCategory,
  remove: removeCategory,
} = useFinanceCategory()

const activeTab = ref('transfer')
const showMutationDialog = ref(false)
const showCategoryListDialog = ref(false)
const filters = ref({
  payment_account_id: null,
  type: null,
  finance_category_id: null,
  date_from: null,
  date_to: null,
})
const transferForm = ref({
  from_payment_account_id: null,
  to_payment_account_id: null,
  amount: null,
  transaction_at: new Date(),
  description: '',
})
const otherForm = ref({
  type: 'expense',
  payment_account_id: null,
  finance_category_id: null,
  amount: null,
  transaction_at: new Date(),
  description: '',
})
const showCategoryDialog = ref(false)
const categoryForm = ref({ id: null, name: '', type: 'expense', is_active: true, sort_order: 0 })
const formErrors = ref({})
const isMobile = ref(window.innerWidth < 768)

const handleResize = () => {
  isMobile.value = window.innerWidth < 768
}

const accountOptions = computed(() => accounts.value.map((account) => ({
  label: `${account.nama_bank} - ${account.nomor_rekening} (${formatCurrency(account.current_balance)})`,
  value: account.id,
})))
const categoryOptions = computed(() => categories.value
  .filter((category) => category.type === otherForm.value.type && category.is_active)
  .map((category) => ({ label: category.name, value: category.id })))
const typeOptions = [
  { label: 'Pemasukan', value: 'income' },
  { label: 'Pengeluaran', value: 'expense' },
]
const transactionTypeOptions = [
  { label: 'Semua Tipe', value: null },
  { label: 'Transfer Keluar', value: 'transfer_out' },
  { label: 'Transfer Masuk', value: 'transfer_in' },
  { label: 'Pemasukan Lain-lain', value: 'other_income' },
  { label: 'Pengeluaran Lain-lain', value: 'other_expense' },
  { label: 'Adjust Saldo', value: 'balance_adjustment' },
]
const mutationTabs = [
  { key: 'transfer', label: 'Transfer Rekening', icon: 'pi pi-arrow-right-arrow-left' },
  { key: 'other', label: 'Lain-lain', icon: 'pi pi-file-edit' },
]

const visibleTransactionCount = computed(() => transactions.value.length)
const netMutationTotal = computed(() => transactions.value.reduce((sum, transaction) => sum + Number(transaction.signed_amount || 0), 0))
const activeCategoryCount = computed(() => categories.value.filter((category) => category.is_active).length)

onMounted(async () => {
  await Promise.all([
    fetchAccounts({ per_page: 100, is_active: true }),
    fetchCategories({ per_page: 100 }),
    fetchAll(normalizedFilters()),
  ])
  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
})

const normalizedFilters = () => ({
  ...filters.value,
  date_from: toApiDate(filters.value.date_from),
  date_to: toApiDate(filters.value.date_to),
})

const toApiDate = (value) => {
  if (!value) return null
  return value instanceof Date ? value.toISOString() : value
}

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))

const formatDate = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
}

const transactionLabel = (type) => ({
  transfer_out: 'Transfer Keluar',
  transfer_in: 'Transfer Masuk',
  other_income: 'Pemasukan Lain-lain',
  other_expense: 'Pengeluaran Lain-lain',
  balance_adjustment: 'Adjust Saldo',
}[type] || type)

const transactionSeverity = (type) => ({
  transfer_out: 'warn',
  transfer_in: 'info',
  other_income: 'success',
  other_expense: 'danger',
  balance_adjustment: 'secondary',
}[type] || 'secondary')

const resetTransferForm = () => {
  transferForm.value = {
    from_payment_account_id: null,
    to_payment_account_id: null,
    amount: null,
    transaction_at: new Date(),
    description: '',
  }
}

const resetOtherForm = () => {
  otherForm.value = {
    type: 'expense',
    payment_account_id: null,
    finance_category_id: null,
    amount: null,
    transaction_at: new Date(),
    description: '',
  }
}

const applyFilters = async () => {
  pagination.value.current_page = 1
  await fetchAll(normalizedFilters())
}

const resetFilters = async () => {
  filters.value = { payment_account_id: null, type: null, finance_category_id: null, date_from: null, date_to: null }
  pagination.value.current_page = 1
  await fetchAll()
}

const onPageChange = (event) => {
  pagination.value.current_page = event.page + 1
  fetchAll(normalizedFilters())
}

const submitTransfer = () => {
  if (!transferForm.value.from_payment_account_id || !transferForm.value.to_payment_account_id || !transferForm.value.amount) return
  confirm.require({
    header: 'Konfirmasi Transfer',
    message: `Transfer ${formatCurrency(transferForm.value.amount)} antar rekening?`,
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Transfer',
    rejectLabel: 'Batal',
    acceptClass: 'app-dialog-button app-dialog-button-primary',
    rejectClass: 'app-dialog-button app-dialog-button-secondary',
    accept: async () => {
      try {
        await transfer({ ...transferForm.value, transaction_at: toApiDate(transferForm.value.transaction_at) })
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Transfer rekening berhasil dicatat', life: 3000 })
        resetTransferForm()
        showMutationDialog.value = false
        await refreshData()
      } catch (err) {
        showError(err)
      }
    },
  })
}

const submitOther = () => {
  if (!otherForm.value.payment_account_id || !otherForm.value.finance_category_id || !otherForm.value.amount) return
  const label = otherForm.value.type === 'income' ? 'pemasukan' : 'pengeluaran'
  confirm.require({
    header: 'Konfirmasi Transaksi',
    message: `Catat ${label} lain-lain sebesar ${formatCurrency(otherForm.value.amount)}?`,
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Simpan',
    rejectLabel: 'Batal',
    acceptClass: 'app-dialog-button app-dialog-button-primary',
    rejectClass: 'app-dialog-button app-dialog-button-secondary',
    accept: async () => {
      try {
        await other({ ...otherForm.value, transaction_at: toApiDate(otherForm.value.transaction_at) })
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Transaksi lain-lain berhasil dicatat', life: 3000 })
        resetOtherForm()
        showMutationDialog.value = false
        await refreshData()
      } catch (err) {
        showError(err)
      }
    },
  })
}

const refreshData = async () => {
  await Promise.all([
    fetchAccounts({ per_page: 100, is_active: true }),
    fetchAll(normalizedFilters()),
  ])
}

const openCategory = (category = null) => {
  formErrors.value = {}
  categoryForm.value = category
    ? { id: category.id, name: category.name, type: category.type, is_active: !!category.is_active, sort_order: category.sort_order || 0 }
    : { id: null, name: '', type: otherForm.value.type || 'expense', is_active: true, sort_order: 0 }
  showCategoryDialog.value = true
}

const saveCategory = async () => {
  formErrors.value = {}
  try {
    if (categoryForm.value.id) {
      await updateCategory(categoryForm.value.id, categoryForm.value)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Kategori diperbarui', life: 3000 })
    } else {
      await storeCategory(categoryForm.value)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Kategori ditambahkan', life: 3000 })
    }
    showCategoryDialog.value = false
  } catch (err) {
    if (err.response?.data?.errors) {
      formErrors.value = err.response.data.errors
    } else {
      showError(err)
    }
  }
}

const confirmDeleteCategory = (category) => {
  confirm.require({
    header: 'Hapus Kategori',
    message: `Hapus kategori "${category.name}"?`,
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'app-dialog-button app-dialog-button-danger',
    rejectClass: 'app-dialog-button app-dialog-button-secondary',
    acceptLabel: 'Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await removeCategory(category.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Kategori dihapus', life: 3000 })
      } catch (err) {
        showError(err)
      }
    },
  })
}

const showError = (err) => {
  const errors = err.response?.data?.errors
  const firstError = errors ? Object.values(errors).flat()[0] : null
  toast.add({
    severity: 'error',
    summary: 'Gagal',
    detail: firstError || err.response?.data?.message || 'Terjadi kesalahan',
    life: 4000,
  })
}
</script>

<template>
  <div class="page-container payment-mutation-page">
    <ConfirmDialog />

    <div class="page-header">
      <div class="header-left">
        <div>
          <h1>Mutasi Rekening</h1>
          <p>Catat transfer antar rekening, pemasukan lain-lain, dan pengeluaran di luar transaksi rental.</p>
        </div>
      </div>
      <div class="header-actions">
        <button class="btn-pill btn-secondary" type="button" @click="showCategoryListDialog = true">
          <i class="pi pi-tags"></i>
          <span>Kelola Kategori</span>
        </button>
        <button class="btn-pill btn-primary" type="button" @click="showMutationDialog = true">
          <i class="pi pi-plus"></i>
          <span>Catat Mutasi</span>
        </button>
      </div>
    </div>

    <div class="filter-bar surface-card mutation-filter-bar">
      <div class="filter-groups">
        <div class="filter-group filter-group-wide">
          <label>Rekening</label>
          <Dropdown v-model="filters.payment_account_id" :options="accountOptions" optionLabel="label" optionValue="value" showClear placeholder="Semua rekening" filter />
        </div>
        <div class="filter-group">
          <label>Tipe</label>
          <Dropdown v-model="filters.type" :options="transactionTypeOptions" optionLabel="label" optionValue="value" placeholder="Semua tipe" />
        </div>
        <div class="filter-group">
          <label>Kategori</label>
          <Dropdown v-model="filters.finance_category_id" :options="categories" optionLabel="name" optionValue="id" showClear placeholder="Semua kategori" filter />
        </div>
        <div class="filter-group">
          <label>Dari</label>
          <DatePicker v-model="filters.date_from" placeholder="Dari tanggal" />
        </div>
        <div class="filter-group">
          <label>Sampai</label>
          <DatePicker v-model="filters.date_to" placeholder="Sampai tanggal" />
        </div>
      </div>
      <div class="filter-actions">
        <button class="btn-pill btn-primary btn-pill-compact" type="button" @click="applyFilters">
          <i class="pi pi-search"></i>
          Filter
        </button>
        <button class="btn-pill btn-secondary btn-pill-compact" type="button" @click="resetFilters">
          <i class="pi pi-times"></i>
          Reset
        </button>
      </div>
    </div>

    <div class="filter-bar surface-card mutation-summary-bar">
      <div class="filter-groups">
        <span class="summary-chip neutral">{{ visibleTransactionCount }} mutasi tampil</span>
        <span class="summary-chip" :class="netMutationTotal >= 0 ? 'success' : 'warning'">Net {{ formatCurrency(netMutationTotal) }}</span>
        <span class="summary-chip info">{{ activeCategoryCount }} kategori aktif</span>
      </div>
      <div class="filter-actions">
        <button class="btn-pill btn-secondary btn-pill-compact" type="button" :disabled="loading" @click="refreshData">
          <i class="pi pi-refresh"></i>
          Refresh
        </button>
      </div>
    </div>

    <div v-if="!isMobile" class="table-shell mutation-table-shell">
      <DataTable :value="transactions" :loading="loading" scrollable scrollHeight="480px" responsiveLayout="scroll" class="drent-datatable" stripedRows>
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-wallet"></i>
            <p>Belum ada mutasi rekening.</p>
          </div>
        </template>
        <Column field="transaction_at" header="Tanggal" style="min-width: 11rem">
          <template #body="{ data }">{{ formatDate(data.transaction_at) }}</template>
        </Column>
        <Column field="type" header="Tipe" style="min-width: 12rem">
          <template #body="{ data }"><Tag :value="transactionLabel(data.type)" :severity="transactionSeverity(data.type)" /></template>
        </Column>
        <Column header="Rekening" style="min-width: 14rem">
          <template #body="{ data }">
            <strong>{{ data.payment_account?.nama_bank || '-' }}</strong>
            <div class="text-xs text-secondary">{{ data.payment_account?.nomor_rekening || '-' }}</div>
          </template>
        </Column>
        <Column header="Relasi/Kategori" style="min-width: 14rem">
          <template #body="{ data }">
            <span>{{ data.finance_category?.name || data.related_payment_account?.nama_bank || '-' }}</span>
            <div v-if="data.related_payment_account?.nomor_rekening" class="text-xs text-secondary">{{ data.related_payment_account.nomor_rekening }}</div>
          </template>
        </Column>
        <Column field="signed_amount" header="Nominal" style="min-width: 10rem">
          <template #body="{ data }">
            <span class="amount" :class="{ negative: data.signed_amount < 0, positive: data.signed_amount > 0 }">{{ formatCurrency(data.signed_amount) }}</span>
          </template>
        </Column>
        <Column field="balance_after" header="Saldo Akhir" style="min-width: 10rem">
          <template #body="{ data }">{{ formatCurrency(data.balance_after) }}</template>
        </Column>
        <Column field="description" header="Catatan" style="min-width: 16rem">
          <template #body="{ data }">{{ data.description || '-' }}</template>
        </Column>
      </DataTable>
      <div class="paginator-wrapper">
        <Paginator :rows="pagination.per_page" :totalRecords="pagination.total" :first="(pagination.current_page - 1) * pagination.per_page"
          template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
          currentPageReportTemplate="{first} - {last} dari {totalRecords}" @page="onPageChange" />
      </div>
    </div>

    <div v-else class="mobile-card-list mutation-mobile-list">
      <div v-if="loading" class="app-muted-panel mobile-state">
        <i class="pi pi-spin pi-spinner"></i>
        <span>Memuat mutasi rekening...</span>
      </div>
      <div v-else-if="!transactions.length" class="app-muted-panel mobile-state">
        <i class="pi pi-wallet"></i>
        <span>Belum ada mutasi rekening.</span>
      </div>
      <article v-else v-for="transaction in transactions" :key="transaction.id" class="mutation-card app-card">
        <div class="mobile-card-head">
          <div>
            <h3>{{ transaction.payment_account?.nama_bank || '-' }}</h3>
            <p>{{ formatDate(transaction.transaction_at) }}</p>
          </div>
          <Tag :value="transactionLabel(transaction.type)" :severity="transactionSeverity(transaction.type)" />
        </div>
        <div class="mobile-info-grid">
          <div>
            <span>Nominal</span>
            <strong class="amount" :class="{ negative: transaction.signed_amount < 0, positive: transaction.signed_amount > 0 }">{{ formatCurrency(transaction.signed_amount) }}</strong>
          </div>
          <div>
            <span>Saldo Akhir</span>
            <strong>{{ formatCurrency(transaction.balance_after) }}</strong>
          </div>
          <div>
            <span>Relasi/Kategori</span>
            <strong>{{ transaction.finance_category?.name || transaction.related_payment_account?.nama_bank || '-' }}</strong>
          </div>
          <div>
            <span>Catatan</span>
            <strong>{{ transaction.description || '-' }}</strong>
          </div>
        </div>
      </article>
      <div v-if="transactions.length" class="mobile-paginator">
        <Paginator :rows="pagination.per_page" :totalRecords="pagination.total" :first="(pagination.current_page - 1) * pagination.per_page"
          template="PrevPageLink CurrentPageReport NextPageLink"
          currentPageReportTemplate="{first} - {last} dari {totalRecords}" @page="onPageChange" />
      </div>
    </div>

    <!-- Dialog Catat Mutasi -->
    <Dialog v-model:visible="showMutationDialog" header="Catat Mutasi Rekening" modal class="custom-dialog" :style="{ width: '560px' }">
      <div class="tab-toggle-container mb-4">
        <div class="pill-toggle w-full">
          <button v-for="tab in mutationTabs" :key="tab.key" class="toggle-item flex-1 text-center" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key">
            <i :class="tab.icon" class="mr-1"></i>
            <span>{{ tab.label }}</span>
          </button>
        </div>
      </div>

      <div v-if="activeTab === 'transfer'" class="form-grid">
        <div class="field">
          <label>Rekening Asal</label>
          <Dropdown v-model="transferForm.from_payment_account_id" :options="accountOptions" optionLabel="label" optionValue="value" placeholder="Pilih rekening asal" filter class="w-full" />
        </div>
        <div class="field">
          <label>Rekening Tujuan</label>
          <Dropdown v-model="transferForm.to_payment_account_id" :options="accountOptions" optionLabel="label" optionValue="value" placeholder="Pilih rekening tujuan" filter class="w-full" />
        </div>
        <div class="field">
          <label>Nominal</label>
          <InputNumber v-model="transferForm.amount" mode="currency" currency="IDR" locale="id-ID" :min="1" class="w-full" />
        </div>
        <div class="field">
          <label>Tanggal</label>
          <DatePicker v-model="transferForm.transaction_at" showTime hourFormat="24" class="w-full" />
        </div>
        <div class="field">
          <label>Catatan</label>
          <Textarea v-model="transferForm.description" rows="2" class="w-full" />
        </div>
      </div>

      <div v-else-if="activeTab === 'other'" class="form-grid">
        <div class="field">
          <label>Tipe</label>
          <Dropdown v-model="otherForm.type" :options="typeOptions" optionLabel="label" optionValue="value" class="w-full" @change="otherForm.finance_category_id = null" />
        </div>
        <div class="field">
          <label>Rekening</label>
          <Dropdown v-model="otherForm.payment_account_id" :options="accountOptions" optionLabel="label" optionValue="value" placeholder="Pilih rekening" filter class="w-full" />
        </div>
        <div class="field">
          <label>Kategori</label>
          <Dropdown v-model="otherForm.finance_category_id" :options="categoryOptions" optionLabel="label" optionValue="value" placeholder="Pilih kategori" filter class="w-full" />
        </div>
        <div class="field">
          <label>Nominal</label>
          <InputNumber v-model="otherForm.amount" mode="currency" currency="IDR" locale="id-ID" :min="1" class="w-full" />
        </div>
        <div class="field">
          <label>Tanggal</label>
          <DatePicker v-model="otherForm.transaction_at" showTime hourFormat="24" class="w-full" />
        </div>
        <div class="field">
          <label>Catatan</label>
          <Textarea v-model="otherForm.description" rows="2" class="w-full" />
        </div>
      </div>

      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" type="button" :disabled="actionLoading" @click="showMutationDialog = false">
          <i class="pi pi-times"></i>
          Batal
        </button>
        <button v-if="activeTab === 'transfer'" class="app-dialog-button app-dialog-button-primary" type="button" :disabled="!transferForm.from_payment_account_id || !transferForm.to_payment_account_id || !transferForm.amount || actionLoading" @click="submitTransfer">
          <i :class="actionLoading ? 'pi pi-spin pi-spinner' : 'pi pi-check'"></i>
          Simpan Transfer
        </button>
        <button v-else class="app-dialog-button app-dialog-button-primary" type="button" :disabled="!otherForm.payment_account_id || !otherForm.finance_category_id || !otherForm.amount || actionLoading" @click="submitOther">
          <i :class="actionLoading ? 'pi pi-spin pi-spinner' : 'pi pi-check'"></i>
          Simpan Transaksi
        </button>
      </template>
    </Dialog>

    <!-- Dialog Kelola Kategori -->
    <Dialog v-model:visible="showCategoryListDialog" header="Kelola Kategori Keuangan" modal class="custom-dialog" :style="{ width: '800px' }">
      <div class="dialog-header-action mb-3">
        <p class="text-secondary text-xs m-0">Daftar kategori untuk klasifikasi pemasukan dan pengeluaran lain-lain.</p>
        <button class="btn-pill btn-primary btn-pill-compact" type="button" @click="openCategory()">
          <i class="pi pi-plus"></i>
          <span>Tambah Kategori</span>
        </button>
      </div>

      <div class="category-list">
        <DataTable :value="categories" :loading="categoryLoading" responsiveLayout="scroll" class="drent-datatable compact-table" stripedRows :paginator="true" :rows="5">
          <Column field="name" header="Kategori" />
          <Column field="type" header="Tipe">
            <template #body="{ data }">
              <Tag :value="data.type === 'income' ? 'Pemasukan' : 'Pengeluaran'" :severity="data.type === 'income' ? 'success' : 'danger'" />
            </template>
          </Column>
          <Column field="is_active" header="Status">
            <template #body="{ data }">
              <Tag :value="data.is_active ? 'Aktif' : 'Nonaktif'" :severity="data.is_active ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column header="Aksi" style="width: 9rem">
            <template #body="{ data }">
              <div class="action-pill-group">
                <button class="action-btn" type="button" title="Edit" @click="openCategory(data)">
                  <i class="pi pi-pencil"></i>
                </button>
                <button class="action-btn action-btn-danger" type="button" title="Hapus" @click="confirmDeleteCategory(data)">
                  <i class="pi pi-trash"></i>
                </button>
              </div>
            </template>
          </Column>
        </DataTable>
      </div>

      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" type="button" @click="showCategoryListDialog = false">
          <i class="pi pi-times"></i>
          Tutup
        </button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showCategoryDialog" :header="categoryForm.id ? 'Edit Kategori' : 'Tambah Kategori'" modal class="custom-dialog" :style="{ width: '440px' }">
      <div class="form-grid">
        <div class="field">
          <label>Nama Kategori</label>
          <InputText v-model="categoryForm.name" class="w-full" :class="{ 'p-invalid': formErrors.name }" />
          <small v-if="formErrors.name" class="p-error">{{ formErrors.name[0] }}</small>
        </div>
        <div class="field">
          <label>Tipe</label>
          <Dropdown v-model="categoryForm.type" :options="typeOptions" optionLabel="label" optionValue="value" class="w-full" :class="{ 'p-invalid': formErrors.type }" />
          <small v-if="formErrors.type" class="p-error">{{ formErrors.type[0] }}</small>
        </div>
        <div class="field">
          <label>Urutan</label>
          <InputNumber v-model="categoryForm.sort_order" :min="0" class="w-full" />
        </div>
        <div class="field">
          <label>Status</label>
          <ToggleButton v-model="categoryForm.is_active" onLabel="Aktif" offLabel="Nonaktif" onIcon="pi pi-check" offIcon="pi pi-times" class="w-full" />
        </div>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" type="button" @click="showCategoryDialog = false">
          <i class="pi pi-times"></i>
          Batal
        </button>
        <button class="app-dialog-button app-dialog-button-primary" type="button" :disabled="categoryLoading" @click="saveCategory">
          <i :class="categoryLoading ? 'pi pi-spin pi-spinner' : 'pi pi-check'"></i>
          Simpan
        </button>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.dialog-header-action { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 12px; }
.flex-1 { flex: 1; }
.text-center { text-align: center; }
.mr-1 { margin-right: 4px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.align-center { align-items: center; }
.m-0 { margin: 0; }
.w-full { width: 100%; }

.payment-mutation-page { display: flex; flex-direction: column; gap: var(--space-lg); background: var(--page-bg); }
.payment-mutation-page .page-header { margin-bottom: var(--space-md); }
.entry-shell { padding: var(--space-lg); }
.entry-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-size: 12px; font-weight: 700; color: var(--text-secondary); }
.span-2 { grid-column: span 2; }
.form-actions { grid-column: span 2; display: flex; justify-content: flex-end; gap: 8px; }
.category-list { overflow: hidden; }
.compact-table { min-height: 240px; }
.mutation-filter-bar,
.mutation-summary-bar { margin-bottom: 0; }
.mutation-table-shell { flex-direction: column; overflow: visible; }
.paginator-wrapper { padding: 10px; border: 1px solid var(--surface-border); border-top: none; border-radius: 0 0 var(--radius-default) var(--radius-default); background: var(--surface-default); }
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 40px; text-align: center; color: var(--text-tertiary); }
.empty-state i { font-size: 28px; opacity: .65; }
.summary-chip { display: inline-flex; align-items: center; min-height: 30px; padding: 5px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 700; color: var(--text-primary); background: var(--card-bg); border: 1px solid var(--surface-border); }
.summary-chip.success { background: rgba(39, 168, 88, .12); color: var(--positive); border-color: rgba(39, 168, 88, .24); }
.summary-chip.warning { background: rgba(247, 144, 9, .14); color: #b45309; border-color: rgba(247, 144, 9, .28); }
.summary-chip.info { background: rgba(11, 122, 138, .12); color: var(--info-cyan); border-color: rgba(11, 122, 138, .22); }
.amount { font-weight: 800; font-variant-numeric: tabular-nums; }
.amount.negative { color: #dc2626; }
.amount.positive { color: #059669; }
.form-grid { display: flex; flex-direction: column; gap: 14px; padding: 8px 0; }
.text-xs { font-size: .78rem; }
.text-secondary { color: var(--text-secondary); }
.w-full { width: 100%; }
.action-btn-danger { color: var(--negative); }
.mobile-card-list { display: flex; flex-direction: column; gap: var(--space-md); }
.mobile-state { display: flex; align-items: center; justify-content: center; gap: var(--space-sm); padding: var(--space-xl); color: var(--text-secondary); }
.mutation-card { padding: var(--space-lg); }
.mobile-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: var(--space-md); }
.mobile-card-head h3 { margin: 0; font-family: var(--font-headline); font-size: 15px; }
.mobile-card-head p { margin: 4px 0 0; color: var(--text-secondary); font-size: 12px; }
.mobile-info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-md); margin-top: var(--space-md); }
.mobile-info-grid div { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.mobile-info-grid span { color: var(--text-tertiary); font-size: 11px; font-weight: 700; }
.mobile-info-grid strong { min-width: 0; overflow-wrap: anywhere; }
.mobile-paginator { overflow: hidden; border: 1px solid var(--surface-border); border-radius: var(--radius-default); background: var(--surface-default); }
@media (max-width: 980px) {
  .entry-grid,
  .mobile-info-grid { grid-template-columns: 1fr; }
  .span-2,
  .form-actions { grid-column: auto; }
  .form-actions { justify-content: stretch; flex-wrap: wrap; }
  .form-actions .btn-pill { flex: 1 1 160px; }
}
</style>
