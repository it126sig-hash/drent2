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
  <div class="page-container city-management-page table-page-active">
    <ConfirmDialog />

    <div class="page-header">
      <div class="header-left">
        <div>
          <h1>List Kota</h1>
          <p class="text-secondary text-xs">Kelola pilihan kota untuk pelanggan dan booking.</p>
        </div>
      </div>
      <div class="header-actions">
        <button v-if="canManage" class="btn-pill btn-primary" type="button" @click="openNew">
          <i class="pi pi-plus"></i>
          <span>Tambah Kota</span>
        </button>
      </div>
    </div>

    <div class="filter-bar surface-card">
      <div class="filter-groups">
        <div class="summary-tile-compact">
          <i class="pi pi-map-marker text-info"></i>
          <span>Total Kota</span>
          <strong class="font-mono-numeric">{{ pagination.total || cities.length }}</strong>
        </div>
      </div>
      <div class="filter-actions">
        <button class="btn-pill btn-secondary btn-pill-compact" type="button" :disabled="loading" @click="fetchAll">
          <i class="pi pi-refresh"></i>
          <span>Refresh</span>
        </button>
      </div>
    </div>

    <div class="table-shell list-tab-fill">
      <DataTable :value="cities" :loading="loading" scrollable scrollHeight="flex" responsiveLayout="scroll" class="drent-datatable" stripedRows>
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
            <span class="drent-badge" :class="data.is_active ? 'success' : 'neutral'">{{ data.is_active ? 'Aktif' : 'Nonaktif' }}</span>
          </template>
        </Column>
        <Column header="Aksi" style="min-width:110px;text-align:center">
          <template #body="{ data }">
            <div class="action-pill-group">
              <button v-if="canManage" class="action-btn" type="button" title="Edit" @click="openEdit(data)">
                <i class="pi pi-pencil"></i>
              </button>
              <button v-if="canDelete" class="action-btn action-btn-danger" type="button" title="Hapus" @click="confirmDelete(data)">
                <i class="pi pi-trash"></i>
              </button>
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

    <Dialog v-model:visible="showDialog" :header="form.id ? 'Edit Kota' : 'Tambah Kota'" modal class="custom-dialog" :style="{ width: '460px' }">
      <div class="form-grid">
        <div class="field">
          <label>Nama Kota <span class="req">*</span></label>
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
  </div>
</template>

<style scoped>
.city-management-page { background: var(--page-bg); }

.summary-tile-compact {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.summary-tile-compact span {
  font-family: var(--font-body);
  font-size: 11px;
  font-weight: 600;
  color: var(--text-secondary);
}

.summary-tile-compact strong {
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 700;
  color: var(--text-primary);
}

.paginator-wrapper { padding: var(--space-sm); border-top: 1px solid var(--surface-border); }
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 44px 0; color: var(--text-tertiary); }
.empty-state i { font-size: 2.4rem; margin-bottom: var(--space-md); opacity: .7; }
.form-grid { display: flex; flex-direction: column; gap: var(--space-lg); padding: var(--space-sm) 0; }
.field { display: flex; flex-direction: column; gap: var(--space-sm); }
.field label { color: var(--text-secondary); font-size: 12px; font-weight: 700; }
.req { color: var(--negative); }
.w-full { width: 100%; }
.action-btn-danger { color: var(--negative) !important; }

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

.text-info {
  color: var(--info-cyan);
}
</style>
