<script setup>
import { ref, onMounted, computed } from 'vue'
import { useCostType } from '../../composables/useCostType'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useAuthStore } from '../../stores/auth'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import ToggleButton from 'primevue/togglebutton'
import Tag from 'primevue/tag'
import ConfirmDialog from 'primevue/confirmdialog'
import Paginator from 'primevue/paginator'

const { costTypes, loading, pagination, fetchAll, store, update, remove } = useCostType()
const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const canManage = computed(() => ['superadmin', 'admin_branch'].includes(authStore.user?.role))

const showDialog = ref(false)
const form = ref({ id: null, nama: '', kode: '', require_description: false, sort_order: 0, is_active: true })
const formErrors = ref({})
const saving = ref(false)

onMounted(() => fetchAll())

const openNew = () => {
  form.value = { id: null, nama: '', kode: '', require_description: false, sort_order: 0, is_active: true }
  formErrors.value = {}
  showDialog.value = true
}

const openEdit = (row) => {
  form.value = { id: row.id, nama: row.nama, kode: row.kode, require_description: !!row.require_description, sort_order: row.sort_order ?? 0, is_active: !!row.is_active }
  formErrors.value = {}
  showDialog.value = true
}

const save = async () => {
  formErrors.value = {}
  saving.value = true
  try {
    if (form.value.id) {
      await update(form.value.id, form.value)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Tipe biaya berhasil diperbarui', life: 3000 })
    } else {
      await store(form.value)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Tipe biaya berhasil ditambahkan', life: 3000 })
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
    message: `Hapus tipe biaya "${row.nama}"?`,
    header: 'Konfirmasi Hapus',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await remove(row.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Tipe biaya berhasil dihapus', life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal menghapus data', life: 3000 })
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
        <h1>Tipe Biaya</h1>
        <p>Kelola jenis-jenis biaya operasional sewa kendaraan</p>
      </div>
      <Button v-if="canManage" label="Tambah Tipe Biaya" icon="pi pi-plus" class="p-button-tosca" @click="openNew" />
    </div>

    <div class="content-card">
      <DataTable :value="costTypes" :loading="loading" responsiveLayout="scroll" class="p-datatable-sm" stripedRows>
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-list"></i>
            <p>Belum ada tipe biaya.</p>
          </div>
        </template>

        <Column field="sort_order" header="#" style="min-width:60px;text-align:center">
          <template #body="{ data }">
            <span class="order-badge">{{ data.sort_order }}</span>
          </template>
        </Column>

        <Column field="nama" header="Nama" style="min-width:160px" />

        <Column field="kode" header="Kode (slug)" style="min-width:130px">
          <template #body="{ data }">
            <span class="kode-badge">{{ data.kode }}</span>
          </template>
        </Column>

        <Column field="require_description" header="Butuh Keterangan" style="min-width:140px;text-align:center">
          <template #body="{ data }">
            <Tag :severity="data.require_description ? 'warn' : 'secondary'" :value="data.require_description ? 'Ya' : 'Tidak'" />
          </template>
        </Column>

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
    <Dialog v-model:visible="showDialog" :header="form.id ? 'Edit Tipe Biaya' : 'Tambah Tipe Biaya'" modal :style="{ width: '480px' }">
      <div class="form-grid">
        <div class="field">
          <label>Nama <span class="req">*</span></label>
          <InputText v-model="form.nama" placeholder="Driver, BBM, Tol..." class="w-full" :class="{ 'p-invalid': formErrors.nama }" />
          <small class="p-error" v-if="formErrors.nama">{{ formErrors.nama[0] }}</small>
        </div>
        <div class="field">
          <label>Kode (slug) <span class="req">*</span></label>
          <InputText v-model="form.kode" placeholder="driver, bbm, tol..." class="w-full" :class="{ 'p-invalid': formErrors.kode }" />
          <small class="p-error" v-if="formErrors.kode">{{ formErrors.kode[0] }}</small>
          <small class="hint">Huruf kecil, tanpa spasi. Digunakan sebagai identifier.</small>
        </div>
        <div class="field">
          <label>Urutan tampil</label>
          <InputNumber v-model="form.sort_order" :min="0" :max="999" showButtons class="w-full" />
        </div>
        <div class="field-row">
          <div class="field">
            <label>Butuh Keterangan</label>
            <ToggleButton v-model="form.require_description" onLabel="Ya" offLabel="Tidak" onIcon="pi pi-check" offIcon="pi pi-times" />
          </div>
          <div class="field">
            <label>Status</label>
            <ToggleButton v-model="form.is_active" onLabel="Aktif" offLabel="Nonaktif" onIcon="pi pi-check" offIcon="pi pi-times" />
          </div>
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
.order-badge { background: #e2e8f0; color: #475569; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: .8rem; }
.kode-badge { font-family: 'Courier New', monospace; background: #f1f5f9; color: #0f172a; padding: 3px 8px; border-radius: 4px; font-size: .8rem; }
.form-grid { display: flex; flex-direction: column; gap: 16px; padding: 8px 0; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field-row { display: flex; gap: 16px; }
.field-row .field { flex: 1; }
.field label { font-weight: 600; font-size: .875rem; color: #374151; }
.hint { color: #94a3b8; font-size: .78rem; }
.req { color: #ef4444; }
.w-full { width: 100%; }
.p-button-tosca { background-color: #06b6d4 !important; border-color: #06b6d4 !important; }
.p-button-tosca:hover { background-color: #0891b2 !important; border-color: #0891b2 !important; }
:deep(.p-datatable .p-datatable-thead > tr > th) { background-color: #f8fafc; color: #475569; font-weight: 700; text-transform: uppercase; font-size: .75rem; letter-spacing: .5px; padding: 15px; }
:deep(.p-datatable .p-datatable-tbody > tr > td) { padding: 15px; }
</style>
