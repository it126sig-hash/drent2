<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
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

const isMobile = ref(window.innerWidth < 768)
const onResize = () => { isMobile.value = window.innerWidth < 768 }
onMounted(() => window.addEventListener('resize', onResize))
onUnmounted(() => window.removeEventListener('resize', onResize))

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
  <div class="page-container cost-type-page table-page-active">
    <ConfirmDialog />

    <div class="page-header">
      <div class="header-left">
        <div>
          <h1>Tipe Biaya</h1>
          <p class="text-secondary text-xs">Kelola jenis-jenis biaya operasional sewa kendaraan.</p>
        </div>
      </div>
      <div class="header-actions">
        <button v-if="canManage" class="btn-pill btn-primary" type="button" @click="openNew">
          <i class="pi pi-plus"></i>
          <span>Tambah Tipe Biaya</span>
        </button>
      </div>
    </div>

    <div class="filter-bar surface-card">
      <div class="filter-groups">
        <div class="summary-tile-compact">
          <i class="pi pi-list text-info"></i>
          <span>Total Tipe Biaya</span>
          <strong class="font-mono-numeric">{{ pagination.total || costTypes.length }}</strong>
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
      <DataTable :value="costTypes" :loading="loading" scrollable scrollHeight="flex" responsiveLayout="scroll" class="drent-datatable" stripedRows>
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-list"></i>
            <p>Belum ada tipe biaya.</p>
          </div>
        </template>

        <Column field="sort_order" header="#" style="min-width:60px;text-align:center">
          <template #body="{ data }">
            <span class="order-badge font-mono-numeric">{{ data.sort_order }}</span>
          </template>
        </Column>

        <Column field="nama" header="Nama" style="min-width:160px" />

        <Column field="kode" header="Kode (slug)" style="min-width:130px">
          <template #body="{ data }">
            <span class="kode-badge mono-text">{{ data.kode }}</span>
          </template>
        </Column>

        <Column field="require_description" header="Butuh Keterangan" style="min-width:140px;text-align:center">
          <template #body="{ data }">
            <span class="drent-badge" :class="data.require_description ? 'warning' : 'neutral'">
              {{ data.require_description ? 'Ya' : 'Tidak' }}
            </span>
          </template>
        </Column>

        <Column field="is_active" header="Status" style="min-width:100px">
          <template #body="{ data }">
            <span class="drent-badge" :class="data.is_active ? 'success' : 'neutral'">
              {{ data.is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
          </template>
        </Column>

        <Column header="Aksi" style="min-width:110px;text-align:center">
          <template #body="{ data }">
            <div class="action-pill-group">
              <button class="action-btn" type="button" title="Edit" @click="openEdit(data)">
                <i class="pi pi-pencil"></i>
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

    <div v-else class="mobile-card-list">
      <article v-for="ct in costTypes" :key="ct.id" class="mobile-card">
        <div class="card-header">
          <strong>{{ ct.nama }}</strong>
          <span class="drent-badge" :class="ct.is_active ? 'success' : 'neutral'">{{ ct.is_active ? 'Aktif' : 'Nonaktif' }}</span>
        </div>
        <div class="card-body">
          <div><span class="field-hint">Kode</span> <span class="kode-badge">{{ ct.kode }}</span></div>
          <div><span class="field-hint">Urutan</span> {{ ct.sort_order }}</div>
          <div><span class="field-hint">Butuh Keterangan</span> {{ ct.require_description ? 'Ya' : 'Tidak' }}</div>
        </div>
        <div class="card-footer">
          <button class="btn-pill btn-secondary btn-pill-compact" type="button" @click="openEdit(ct)">
            <i class="pi pi-pencil"></i> Edit
          </button>
          <button v-if="canManage" class="btn-pill btn-secondary btn-pill-compact" type="button" @click="confirmDelete(ct)">
            <i class="pi pi-trash"></i> Hapus
          </button>
        </div>
      </article>

      <div v-if="!loading && !costTypes.length" class="empty-state">
        <i class="pi pi-list"></i>
        <p>Belum ada tipe biaya.</p>
      </div>

      <div class="paginator-wrapper">
        <Paginator :rows="pagination.per_page" :totalRecords="pagination.total" :first="(pagination.current_page - 1) * pagination.per_page"
          @page="onPageChange" template="PrevPageLink CurrentPageReport NextPageLink" currentPageReportTemplate="{first}-{last} dari {totalRecords}" />
      </div>
    </div>

    <!-- Dialog Form -->
    <Dialog v-model:visible="showDialog" :header="form.id ? 'Edit Tipe Biaya' : 'Tambah Tipe Biaya'" modal class="custom-dialog" :style="{ width: '480px' }">
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
          <label>Urutan Tampil</label>
          <InputNumber v-model="form.sort_order" :min="0" :max="999" showButtons class="w-full" />
        </div>
        <div class="field-row">
          <div class="field">
            <label>Butuh Keterangan</label>
            <ToggleButton v-model="form.require_description" onLabel="Ya" offLabel="Tidak" onIcon="pi pi-check" offIcon="pi pi-times" class="w-full" />
          </div>
          <div class="field">
            <label>Status</label>
            <ToggleButton v-model="form.is_active" onLabel="Aktif" offLabel="Nonaktif" onIcon="pi pi-check" offIcon="pi pi-times" class="w-full" />
          </div>
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
.cost-type-page { background: var(--page-bg); }

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
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 50px 0; color: #94a3b8; }
.empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: .5; }
.order-badge { background: var(--card-bg); color: var(--text-secondary); padding: 2px 8px; border-radius: var(--radius-xs); font-weight: 700; font-size: 11px; border: 1px solid var(--surface-border); }
.kode-badge { font-family: var(--font-mono); background: var(--card-bg); color: var(--text-primary); padding: 3px 8px; border-radius: var(--radius-xs); font-size: 11px; border: 1px solid var(--surface-border); }
.form-grid { display: flex; flex-direction: column; gap: 16px; padding: 8px 0; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field-row { display: flex; gap: 16px; }
.field-row .field { flex: 1; }
.field label { font-weight: 700; font-size: 12px; color: var(--text-secondary); }
.hint { color: var(--text-tertiary); font-size: 11px; }
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

.drent-badge.warning {
  background-color: #FDF4D9;
  color: #8C660A;
}

.drent-badge.neutral {
  background-color: #E4E8F3;
  color: #4A5060;
}

.text-info {
  color: var(--info-cyan);
}

.field-hint { color: var(--text-tertiary); font-size: 11px; margin-right: 4px; }
.mobile-card-list .card-footer { justify-content: flex-end; gap: var(--space-sm); }
</style>
