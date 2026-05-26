<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { usePaymentAccount } from '../../composables/usePaymentAccount'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useAuthStore } from '../../stores/auth'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import ToggleButton from 'primevue/togglebutton'
import Tag from 'primevue/tag'
import ConfirmDialog from 'primevue/confirmdialog'
import Paginator from 'primevue/paginator'
import Textarea from 'primevue/textarea'
import { createAdjustment } from '../../api/paymentAccountTransaction'

const { accounts, loading, pagination, fetchAll, store, update, remove } = usePaymentAccount()
const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const canManage = computed(() => ['superadmin', 'admin_branch'].includes(authStore.user?.role))
const canAdjust = computed(() => ['superadmin', 'admin_branch', 'finance'].includes(authStore.user?.role))

const showDialog = ref(false)
const showAdjustDialog = ref(false)
const form = ref({ id: null, nama_bank: '', nomor_rekening: '', atas_nama: '', current_balance: 0, is_active: true })
const adjustForm = ref({ payment_account_id: null, current_balance: 0, transaction_at: new Date(), description: '' })
const selectedAccount = ref(null)
const formErrors = ref({})
const saving = ref(false)
const savingAdjust = ref(false)
const isMobile = ref(window.innerWidth < 768)

const handleResize = () => {
  isMobile.value = window.innerWidth < 768
}

onMounted(() => {
  fetchAll()
  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
})

const openNew = () => {
  form.value = { id: null, nama_bank: '', nomor_rekening: '', atas_nama: '', current_balance: 0, is_active: true }
  formErrors.value = {}
  showDialog.value = true
}

const openEdit = (row) => {
  form.value = { id: row.id, nama_bank: row.nama_bank, nomor_rekening: row.nomor_rekening, atas_nama: row.atas_nama, current_balance: row.current_balance || 0, is_active: !!row.is_active }
  formErrors.value = {}
  showDialog.value = true
}

const openAdjust = (row) => {
  selectedAccount.value = row
  adjustForm.value = {
    payment_account_id: row.id,
    current_balance: row.current_balance || 0,
    transaction_at: new Date(),
    description: '',
  }
  formErrors.value = {}
  showAdjustDialog.value = true
}

const save = async () => {
  formErrors.value = {}
  saving.value = true
  try {
    if (form.value.id) {
      await update(form.value.id, form.value)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Akun pembayaran berhasil diperbarui', life: 3000 })
    } else {
      await store(form.value)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Akun pembayaran berhasil ditambahkan', life: 3000 })
    }
    showDialog.value = false
  } catch (err) {
    if (err.response?.data?.errors) {
      formErrors.value = err.response.data.errors
    } else {
      toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || 'Terjadi kesalahan', life: 3000 })
    }
  } finally {
    saving.value = false
  }
}

const confirmDelete = (row) => {
  confirm.require({
    message: `Hapus akun "${row.nama_bank} - ${row.nomor_rekening}"?`,
    header: 'Konfirmasi Hapus',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'app-dialog-button app-dialog-button-danger',
    rejectClass: 'app-dialog-button app-dialog-button-secondary',
    acceptLabel: 'Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await remove(row.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Akun berhasil dihapus', life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal menghapus akun', life: 3000 })
      }
    }
  })
}

const onPageChange = (event) => {
  pagination.value.current_page = event.page + 1
  fetchAll()
}

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))

const toApiDate = (value) => value instanceof Date ? value.toISOString() : value

const submitAdjust = () => {
  formErrors.value = {}
  if (!adjustForm.value.description || adjustForm.value.description.length < 3) {
    formErrors.value = { description: ['Catatan wajib diisi.'] }
    return
  }

  confirm.require({
    message: `Adjust saldo ${selectedAccount.value?.nama_bank} menjadi ${formatCurrency(adjustForm.value.current_balance)}?`,
    header: 'Konfirmasi Adjust Saldo',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Adjust',
    rejectLabel: 'Batal',
    acceptClass: 'app-dialog-button app-dialog-button-primary',
    rejectClass: 'app-dialog-button app-dialog-button-secondary',
    accept: async () => {
      savingAdjust.value = true
      try {
        await createAdjustment({
          ...adjustForm.value,
          transaction_at: toApiDate(adjustForm.value.transaction_at),
        })
        showAdjustDialog.value = false
        await fetchAll()
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Saldo rekening berhasil disesuaikan', life: 3000 })
      } catch (err) {
        if (err.response?.data?.errors) {
          formErrors.value = err.response.data.errors
        } else {
          toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || 'Gagal adjust saldo', life: 3000 })
        }
      } finally {
        savingAdjust.value = false
      }
    },
  })
}

const activeAccountCount = computed(() => accounts.value.filter((account) => account.is_active).length)
const totalBalance = computed(() => accounts.value.reduce((sum, account) => sum + Number(account.current_balance || 0), 0))
</script>

<template>
  <div class="page-container payment-account-page table-page-active">
    <ConfirmDialog />

    <div class="page-header">
      <div class="header-left">
        <div>
          <h1>Akun Pembayaran</h1>
          <p class="text-secondary text-xs">Kelola rekening bank dan akun penerimaan pembayaran.</p>
        </div>
      </div>
      <div class="header-actions">
        <button v-if="canManage" class="btn-pill btn-primary" type="button" @click="openNew">
          <i class="pi pi-plus"></i>
          <span>Tambah Akun</span>
        </button>
      </div>
    </div>

    <div class="filter-bar surface-card account-summary-bar">
      <div class="filter-groups">
        <div class="summary-tile">
          <div class="summary-tile-header">
            <i class="pi pi-credit-card"></i>
            <span>Total Rekening</span>
          </div>
          <strong class="font-mono-numeric">{{ pagination.total || accounts.length }}</strong>
        </div>
        <div class="summary-tile">
          <div class="summary-tile-header">
            <i class="pi pi-check-circle text-positive"></i>
            <span>Aktif</span>
          </div>
          <strong class="font-mono-numeric">{{ activeAccountCount }}</strong>
        </div>
        <div class="summary-tile summary-tile-wide">
          <div class="summary-tile-header">
            <i class="pi pi-wallet text-info"></i>
            <span>Total Saldo Terbaca</span>
          </div>
          <strong class="font-mono-numeric">{{ formatCurrency(totalBalance) }}</strong>
        </div>
      </div>
      <div class="filter-actions">
        <button class="btn-pill btn-secondary btn-pill-compact" type="button" :disabled="loading" @click="fetchAll">
          <i class="pi pi-refresh"></i>
          <span>Refresh</span>
        </button>
      </div>
    </div>

    <div v-if="!isMobile" class="table-shell list-tab-fill">
      <DataTable :value="accounts" :loading="loading" scrollable scrollHeight="flex" responsiveLayout="scroll" class="drent-datatable payment-account-table" stripedRows>
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-credit-card"></i>
            <p>Belum ada akun pembayaran.</p>
          </div>
        </template>

        <Column field="nama_bank" header="Bank" style="min-width:140px" />

        <Column field="nomor_rekening" header="No. Rekening" style="min-width:180px">
          <template #body="{ data }">
            <span class="mono-text">{{ data.nomor_rekening }}</span>
          </template>
        </Column>

        <Column field="atas_nama" header="Atas Nama" style="min-width:160px" />

        <Column field="current_balance" header="Saldo Saat Ini" style="min-width:160px">
          <template #body="{ data }">
            <span class="amount-text">{{ formatCurrency(data.current_balance) }}</span>
          </template>
        </Column>

        <Column field="is_active" header="Status" style="min-width:100px">
          <template #body="{ data }">
            <span class="drent-badge" :class="data.is_active ? 'success' : 'neutral'">{{ data.is_active ? 'Aktif' : 'Nonaktif' }}</span>
          </template>
        </Column>

        <Column header="Aksi" frozen style="min-width: 8rem">
          <template #body="{ data }">
            <div class="action-pill-group">
              <button v-if="canManage" class="action-btn" type="button" title="Edit" @click="openEdit(data)">
                <i class="pi pi-pencil"></i>
              </button>
              <button v-if="canAdjust" class="action-btn action-btn-primary" type="button" title="Adjust Saldo" @click="openAdjust(data)">
                <i class="pi pi-calculator"></i>
              </button>
              <button v-if="canManage" class="action-btn action-btn-danger" type="button" title="Hapus" @click="confirmDelete(data)">
                <i class="pi pi-trash"></i>
              </button>
            </div>
          </template>
        </Column>
      </DataTable>

      <div class="paginator-wrapper">
        <Paginator :rows="pagination.per_page" :totalRecords="pagination.total" :first="(pagination.current_page - 1) * pagination.per_page"
          @page="onPageChange" template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
          currentPageReportTemplate="Menampilkan {first} ke {last} dari {totalRecords} data" />
      </div>
    </div>

    <div v-else class="mobile-card-list account-mobile-list">
      <div v-if="loading" class="app-muted-panel mobile-state">
        <i class="pi pi-spin pi-spinner"></i>
        <span>Memuat akun pembayaran...</span>
      </div>
      <div v-else-if="!accounts.length" class="app-muted-panel mobile-state">
        <i class="pi pi-credit-card"></i>
        <span>Belum ada akun pembayaran.</span>
      </div>
      <article v-else v-for="account in accounts" :key="account.id" class="account-card app-card">
        <div class="mobile-card-head">
          <div>
            <h3>{{ account.nama_bank }}</h3>
            <p>{{ account.atas_nama }}</p>
          </div>
          <span class="drent-badge" :class="account.is_active ? 'success' : 'neutral'">{{ account.is_active ? 'Aktif' : 'Nonaktif' }}</span>
        </div>
        <div class="mobile-info-grid">
          <div>
            <span>No. Rekening</span>
            <strong class="mono-text">{{ account.nomor_rekening }}</strong>
          </div>
          <div>
            <span>Saldo Saat Ini</span>
            <strong class="amount-text">{{ formatCurrency(account.current_balance) }}</strong>
          </div>
        </div>
        <div class="card-actions">
          <button v-if="canAdjust" class="btn-pill btn-primary btn-pill-compact" type="button" @click="openAdjust(account)">
            <i class="pi pi-calculator"></i>
            Adjust
          </button>
          <button v-if="canManage" class="btn-pill btn-secondary btn-pill-compact" type="button" @click="openEdit(account)">
            <i class="pi pi-pencil"></i>
            Edit
          </button>
        </div>
      </article>
      <div v-if="accounts.length" class="mobile-paginator">
        <Paginator :rows="pagination.per_page" :totalRecords="pagination.total" :first="(pagination.current_page - 1) * pagination.per_page"
          @page="onPageChange" template="PrevPageLink CurrentPageReport NextPageLink"
          currentPageReportTemplate="{first} - {last} dari {totalRecords}" />
      </div>
    </div>

    <!-- Dialog Form -->
    <Dialog v-model:visible="showDialog" :header="form.id ? 'Edit Akun Pembayaran' : 'Tambah Akun Pembayaran'" modal class="custom-dialog" :style="{ width: '480px' }">
      <div class="form-grid">
        <div class="field">
          <label>Nama Bank <span class="req">*</span></label>
          <InputText v-model="form.nama_bank" placeholder="BCA, Mandiri, Cash..." class="w-full" :class="{ 'p-invalid': formErrors.nama_bank }" />
          <small class="p-error" v-if="formErrors.nama_bank">{{ formErrors.nama_bank[0] }}</small>
        </div>
        <div class="field">
          <label>Nomor Rekening <span class="req">*</span></label>
          <InputText v-model="form.nomor_rekening" placeholder="1234567890" class="w-full" :class="{ 'p-invalid': formErrors.nomor_rekening }" />
          <small class="p-error" v-if="formErrors.nomor_rekening">{{ formErrors.nomor_rekening[0] }}</small>
        </div>
        <div class="field">
          <label>Atas Nama <span class="req">*</span></label>
          <InputText v-model="form.atas_nama" placeholder="Nama pemilik rekening" class="w-full" :class="{ 'p-invalid': formErrors.atas_nama }" />
          <small class="p-error" v-if="formErrors.atas_nama">{{ formErrors.atas_nama[0] }}</small>
        </div>
        <div class="field">
          <label>Saldo Saat Ini</label>
          <InputNumber v-model="form.current_balance" mode="currency" currency="IDR" locale="id-ID" class="w-full" :class="{ 'p-invalid': formErrors.current_balance }" />
          <small class="p-error" v-if="formErrors.current_balance">{{ formErrors.current_balance[0] }}</small>
        </div>
        <div class="field">
          <label>Status</label>
          <ToggleButton v-model="form.is_active" onLabel="Aktif" offLabel="Nonaktif" onIcon="pi pi-check" offIcon="pi pi-times" class="w-full" />
        </div>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" type="button" :disabled="saving" @click="showDialog = false">
          <i class="pi pi-times"></i>
          Batal
        </button>
        <button class="app-dialog-button app-dialog-button-primary" type="button" :disabled="saving" @click="save">
          <i :class="saving ? 'pi pi-spin pi-spinner' : 'pi pi-check'"></i>
          Simpan
        </button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showAdjustDialog" header="Adjust Saldo Rekening" modal class="custom-dialog" :style="{ width: '460px' }">
      <div class="form-grid">
        <div class="adjust-summary" v-if="selectedAccount">
          <span>{{ selectedAccount.nama_bank }} - {{ selectedAccount.nomor_rekening }}</span>
          <strong>{{ formatCurrency(selectedAccount.current_balance) }}</strong>
        </div>
        <div class="field">
          <label>Saldo Baru <span class="req">*</span></label>
          <InputNumber v-model="adjustForm.current_balance" mode="currency" currency="IDR" locale="id-ID" class="w-full" :class="{ 'p-invalid': formErrors.current_balance }" />
          <small class="p-error" v-if="formErrors.current_balance">{{ formErrors.current_balance[0] }}</small>
        </div>
        <div class="field">
          <label>Tanggal</label>
          <DatePicker v-model="adjustForm.transaction_at" showTime hourFormat="24" class="w-full" />
        </div>
        <div class="field">
          <label>Catatan <span class="req">*</span></label>
          <Textarea v-model="adjustForm.description" rows="3" class="w-full" :class="{ 'p-invalid': formErrors.description }" />
          <small class="p-error" v-if="formErrors.description">{{ formErrors.description[0] }}</small>
        </div>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" type="button" :disabled="savingAdjust" @click="showAdjustDialog = false">
          <i class="pi pi-times"></i>
          Batal
        </button>
        <button class="app-dialog-button app-dialog-button-primary" type="button" :disabled="savingAdjust" @click="submitAdjust">
          <i :class="savingAdjust ? 'pi pi-spin pi-spinner' : 'pi pi-check'"></i>
          Adjust Saldo
        </button>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.payment-account-page { background: var(--page-bg); }
.account-summary-bar { align-items: stretch; }
.summary-tile { 
  min-width: 140px; 
  display: flex; 
  flex-direction: column; 
  gap: 6px; 
  padding: 12px 16px; 
  border: 1px solid var(--surface-border); 
  border-radius: var(--radius-default); 
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}
.summary-tile-wide { min-width: 220px; }

.summary-tile-header {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-secondary);
}

.summary-tile-header i {
  font-size: 14px;
}

.summary-tile-header span { 
  font-family: var(--font-body);
  font-size: 11px; 
  font-weight: 600; 
  color: var(--text-secondary);
}
.summary-tile strong { 
  color: var(--text-primary); 
  font-family: var(--font-headline); 
  font-size: 18px; 
  font-weight: 700;
}
.payment-account-table { min-width: 760px; }
.paginator-wrapper { padding: var(--space-sm); border-top: 1px solid var(--surface-border); }
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 50px 0; color: #94a3b8; }
.empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: .5; }
.mono-text { font-family: var(--font-mono); font-weight: 600; }
.amount-text { font-family: var(--font-mono); font-weight: 700; font-variant-numeric: tabular-nums; }
.form-grid { display: flex; flex-direction: column; gap: 16px; padding: 8px 0; }
.adjust-summary { display: flex; justify-content: space-between; gap: 12px; border: 1px solid var(--surface-border); background: var(--card-bg); border-radius: var(--radius-default); padding: 12px; color: var(--text-secondary); }
.adjust-summary strong { color: var(--text-primary); font-variant-numeric: tabular-nums; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-weight: 700; font-size: 12px; color: var(--text-secondary); }
.req { color: var(--negative); }
.w-full { width: 100%; }
.action-btn-primary { color: var(--info-cyan); }
.action-btn-danger { color: var(--negative); }
.mobile-card-list { display: flex; flex-direction: column; gap: var(--space-md); }
.mobile-state { display: flex; align-items: center; justify-content: center; gap: var(--space-sm); padding: var(--space-xl); color: var(--text-secondary); }
.account-card { padding: var(--space-lg); }
.mobile-card-head { display: flex; justify-content: space-between; gap: var(--space-md); align-items: flex-start; }
.mobile-card-head h3 { margin: 0; font-family: var(--font-headline); font-size: 15px; }
.mobile-card-head p { margin: 4px 0 0; color: var(--text-secondary); font-size: 12px; }
.mobile-info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-md); margin-top: var(--space-md); }
.mobile-info-grid div { display: flex; flex-direction: column; gap: 3px; }
.mobile-info-grid span { color: var(--text-tertiary); font-size: 11px; font-weight: 700; }
.card-actions { display: flex; flex-wrap: wrap; gap: var(--space-sm); justify-content: flex-end; margin-top: var(--space-md); }
.mobile-paginator { overflow: hidden; border: 1px solid var(--surface-border); border-radius: var(--radius-default); background: var(--surface-default); }

/* Premium Drent Badge styling matching design.md rules */
.drent-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 6px;
  border-radius: 6px;
  font-family: var(--font-body);
  font-size: 10px;
  font-weight: 600;
  line-height: 1.3;
  text-transform: capitalize;
  white-space: nowrap;
}

.drent-badge.success {
  background-color: #E6F6EC;
  color: #147239;
}

.drent-badge.neutral {
  background-color: #E4E8F3;
  color: #4A5060;
}

.text-positive {
  color: var(--positive);
}

.text-info {
  color: var(--info-cyan);
}

@media (max-width: 768px) {
  .mobile-info-grid { grid-template-columns: 1fr; }
}
</style>
