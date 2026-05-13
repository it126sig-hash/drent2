<script setup>
import { ref, onMounted, computed } from 'vue'
import { usePaymentAccount } from '../../composables/usePaymentAccount'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useAuthStore } from '../../stores/auth'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import ToggleButton from 'primevue/togglebutton'
import Tag from 'primevue/tag'
import ConfirmDialog from 'primevue/confirmdialog'
import Paginator from 'primevue/paginator'

const { accounts, loading, pagination, fetchAll, store, update, remove } = usePaymentAccount()
const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const canManage = computed(() => ['superadmin', 'admin_branch'].includes(authStore.user?.role))

const showDialog = ref(false)
const form = ref({ id: null, nama_bank: '', nomor_rekening: '', atas_nama: '', is_active: true })
const formErrors = ref({})
const saving = ref(false)

onMounted(() => fetchAll())

const openNew = () => {
  form.value = { id: null, nama_bank: '', nomor_rekening: '', atas_nama: '', is_active: true }
  formErrors.value = {}
  showDialog.value = true
}

const openEdit = (row) => {
  form.value = { id: row.id, nama_bank: row.nama_bank, nomor_rekening: row.nomor_rekening, atas_nama: row.atas_nama, is_active: !!row.is_active }
  formErrors.value = {}
  showDialog.value = true
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
    acceptClass: 'p-button-danger',
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
</script>

<template>
  <div class="view-container">
    <ConfirmDialog />

    <div class="header-section">
      <div class="header-content">
        <h1>Akun Pembayaran</h1>
        <p>Kelola rekening bank dan akun penerimaan pembayaran</p>
      </div>
      <Button v-if="canManage" label="Tambah Akun" icon="pi pi-plus" class="p-button-tosca" @click="openNew" />
    </div>

    <div class="content-card">
      <DataTable :value="accounts" :loading="loading" responsiveLayout="scroll" class="p-datatable-sm" stripedRows>
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

        <Column field="is_active" header="Status" style="min-width:100px">
          <template #body="{ data }">
            <Tag :severity="data.is_active ? 'success' : 'secondary'" :value="data.is_active ? 'Aktif' : 'Nonaktif'" />
          </template>
        </Column>

        <Column header="Aksi" style="min-width:110px;text-align:center">
          <template #body="{ data }">
            <div class="action-buttons">
              <Button icon="pi pi-pencil" class="p-button-rounded p-button-text p-button-secondary" @click="openEdit(data)" v-tooltip.top="'Edit'" />
              <Button v-if="canManage" icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click="confirmDelete(data)" v-tooltip.top="'Hapus'" />
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

    <!-- Dialog Form -->
    <Dialog v-model:visible="showDialog" :header="form.id ? 'Edit Akun Pembayaran' : 'Tambah Akun Pembayaran'" modal :style="{ width: '480px' }">
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
          <label>Status</label>
          <ToggleButton v-model="form.is_active" onLabel="Aktif" offLabel="Nonaktif" onIcon="pi pi-check" offIcon="pi pi-times" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Batal" icon="pi pi-times" class="p-button-text" @click="showDialog = false" :disabled="saving" />
        <Button label="Simpan" icon="pi pi-check" class="p-button-tosca" @click="save" :loading="saving" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.view-container { display: flex; flex-direction: column; gap: 25px; }
.header-section { display: flex; justify-content: space-between; align-items: center; }
.header-content h1 { font-size: 1.8rem; font-weight: 700; color: #1e293b; margin: 0; }
.header-content p { color: #64748b; margin-top: 5px; }
.content-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,.05); overflow: hidden; }
.paginator-wrapper { padding: 10px; border-top: 1px solid #f1f5f9; }
.action-buttons { display: flex; justify-content: center; gap: 5px; }
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 50px 0; color: #94a3b8; }
.empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: .5; }
.mono-text { font-family: 'Courier New', monospace; font-weight: 600; }
.form-grid { display: flex; flex-direction: column; gap: 16px; padding: 8px 0; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-weight: 600; font-size: .875rem; color: #374151; }
.req { color: #ef4444; }
.w-full { width: 100%; }
.p-button-tosca { background-color: #06b6d4 !important; border-color: #06b6d4 !important; }
.p-button-tosca:hover { background-color: #0891b2 !important; border-color: #0891b2 !important; }
:deep(.p-datatable .p-datatable-thead > tr > th) { background-color: #f8fafc; color: #475569; font-weight: 700; text-transform: uppercase; font-size: .75rem; letter-spacing: .5px; padding: 15px; }
:deep(.p-datatable .p-datatable-tbody > tr > td) { padding: 15px; }
</style>
