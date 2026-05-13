<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useAuthStore } from '../../stores/auth'
import { useCity } from '../../composables/useCity'
import Button from 'primevue/button'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Paginator from 'primevue/paginator'
import Tag from 'primevue/tag'
import ToggleButton from 'primevue/togglebutton'

const { cities, loading, pagination, fetchAll, store, update, remove } = useCity()
const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const canManage = computed(() => ['superadmin', 'admin_branch', 'cs'].includes(authStore.user?.role))
const canDelete = computed(() => ['superadmin', 'admin_branch'].includes(authStore.user?.role))

const showDialog = ref(false)
const saving = ref(false)
const formErrors = ref({})
const form = ref({ id: null, nama: '', provinsi: '', is_active: true })

onMounted(() => fetchAll())

const openNew = () => {
  form.value = { id: null, nama: '', provinsi: '', is_active: true }
  formErrors.value = {}
  showDialog.value = true
}

const openEdit = (row) => {
  form.value = {
    id: row.id,
    nama: row.nama,
    provinsi: row.provinsi || '',
    is_active: !!row.is_active
  }
  formErrors.value = {}
  showDialog.value = true
}

const save = async () => {
  formErrors.value = {}
  saving.value = true
  try {
    if (form.value.id) {
      await update(form.value.id, form.value)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Kota berhasil diperbarui', life: 3000 })
    } else {
      await store(form.value)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Kota berhasil ditambahkan', life: 3000 })
    }
    showDialog.value = false
  } catch (err) {
    if (err.response?.data?.errors) {
      formErrors.value = err.response.data.errors
      return
    }
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || 'Terjadi kesalahan', life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (row) => {
  confirm.require({
    message: `Hapus kota "${row.nama}"?`,
    header: 'Konfirmasi Hapus',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await remove(row.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Kota berhasil dihapus', life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal menghapus kota', life: 3000 })
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
        <h1>List Kota</h1>
        <p>Kelola pilihan kota untuk pelanggan dan booking</p>
      </div>
      <Button v-if="canManage" label="Tambah Kota" icon="pi pi-plus" class="p-button-tosca" @click="openNew" />
    </div>

    <div class="content-card">
      <DataTable :value="cities" :loading="loading" responsiveLayout="scroll" class="p-datatable-sm" stripedRows>
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-map-marker"></i>
            <p>Belum ada data kota.</p>
          </div>
        </template>

        <Column field="nama" header="Kota" style="min-width:180px" />
        <Column field="provinsi" header="Provinsi" style="min-width:160px">
          <template #body="{ data }">{{ data.provinsi || '-' }}</template>
        </Column>
        <Column field="is_active" header="Status" style="min-width:110px">
          <template #body="{ data }">
            <Tag :severity="data.is_active ? 'success' : 'secondary'" :value="data.is_active ? 'Aktif' : 'Nonaktif'" />
          </template>
        </Column>
        <Column header="Aksi" style="min-width:110px;text-align:center">
          <template #body="{ data }">
            <div class="action-buttons">
              <Button v-if="canManage" icon="pi pi-pencil" class="p-button-rounded p-button-text p-button-secondary" @click="openEdit(data)" v-tooltip.top="'Edit'" />
              <Button v-if="canDelete" icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click="confirmDelete(data)" v-tooltip.top="'Hapus'" />
            </div>
          </template>
        </Column>
      </DataTable>

      <div class="paginator-wrapper">
        <Paginator
          :rows="pagination.per_page"
          :totalRecords="pagination.total"
          :first="(pagination.current_page - 1) * pagination.per_page"
          @page="onPageChange"
          template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
          currentPageReportTemplate="Menampilkan {first} ke {last} dari {totalRecords} data"
        />
      </div>
    </div>

    <Dialog v-model:visible="showDialog" :header="form.id ? 'Edit Kota' : 'Tambah Kota'" modal :style="{ width: '460px' }">
      <div class="form-grid">
        <div class="field">
          <label>Nama kota <span class="req">*</span></label>
          <InputText v-model="form.nama" placeholder="Jakarta, Bandung, Surabaya..." class="w-full" :class="{ 'p-invalid': formErrors.nama }" />
          <small v-if="formErrors.nama" class="p-error">{{ formErrors.nama[0] }}</small>
        </div>
        <div class="field">
          <label>Provinsi</label>
          <InputText v-model="form.provinsi" placeholder="DKI Jakarta, Jawa Barat..." class="w-full" :class="{ 'p-invalid': formErrors.provinsi }" />
          <small v-if="formErrors.provinsi" class="p-error">{{ formErrors.provinsi[0] }}</small>
        </div>
        <div class="field">
          <label>Status</label>
          <ToggleButton v-model="form.is_active" onLabel="Aktif" offLabel="Nonaktif" onIcon="pi pi-check" offIcon="pi pi-times" />
        </div>
      </div>

      <template #footer>
        <Button label="Batal" icon="pi pi-times" class="p-button-text" :disabled="saving" @click="showDialog = false" />
        <Button label="Simpan" icon="pi pi-check" class="p-button-tosca" :loading="saving" @click="save" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.view-container { display: flex; flex-direction: column; gap: var(--space-2xl); }
.header-section { display: flex; align-items: center; justify-content: space-between; gap: var(--space-lg); }
.header-content h1 { margin: 0; font-family: var(--font-headline); font-size: 20px; font-weight: 700; color: var(--text-primary); }
.header-content p { margin: 4px 0 0; color: var(--text-secondary); font-size: 13px; }
.content-card { overflow: hidden; background: var(--surface-default); border: 1px solid var(--surface-border); border-radius: var(--radius-default); box-shadow: var(--shadow-tile); }
.paginator-wrapper { padding: var(--space-sm); border-top: 1px solid var(--surface-border); }
.action-buttons { display: flex; justify-content: center; gap: var(--space-xs); }
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 44px 0; color: var(--text-tertiary); }
.empty-state i { font-size: 2.4rem; margin-bottom: var(--space-md); opacity: .7; }
.form-grid { display: flex; flex-direction: column; gap: var(--space-lg); padding: var(--space-sm) 0; }
.field { display: flex; flex-direction: column; gap: var(--space-sm); }
.field label { color: var(--text-secondary); font-size: 12px; font-weight: 600; }
.req { color: var(--negative); }
.w-full { width: 100%; }
.p-button-tosca { background-color: #0D8091 !important; border-color: #0D8091 !important; color: #fff !important; }
</style>
