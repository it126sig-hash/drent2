<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import ToggleButton from 'primevue/togglebutton'
import ConfirmDialog from 'primevue/confirmdialog'
import Tag from 'primevue/tag'
import InvoiceTermsEditor from '../../components/InvoiceTermsEditor.vue'
import {
  getInvoiceTermsTemplates,
  createInvoiceTermsTemplate,
  updateInvoiceTermsTemplate,
  deleteInvoiceTermsTemplate,
} from '../../api/invoiceTermsTemplate'

const toast = useToast()
const confirm = useConfirm()

const templates = ref([])
const loading = ref(false)
const saving = ref(false)
const showDialog = ref(false)

const emptyForm = () => ({ id: null, name: '', content: '', is_default: false, is_active: true })
const form = ref(emptyForm())
const formErrors = ref({})

const isMobile = ref(window.innerWidth < 768)
const onResize = () => { isMobile.value = window.innerWidth < 768 }
onMounted(() => window.addEventListener('resize', onResize))
onUnmounted(() => window.removeEventListener('resize', onResize))

const fetchAll = async () => {
  loading.value = true
  try {
    const res = await getInvoiceTermsTemplates()
    templates.value = res.data.data || []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat template', life: 3000 })
  } finally {
    loading.value = false
  }
}

onMounted(fetchAll)

const openNew = () => {
  form.value = emptyForm()
  formErrors.value = {}
  showDialog.value = true
}

const openEdit = (row) => {
  form.value = { id: row.id, name: row.name, content: row.content, is_default: !!row.is_default, is_active: !!row.is_active }
  formErrors.value = {}
  showDialog.value = true
}

const save = async () => {
  formErrors.value = {}
  if (!form.value.name.trim()) {
    formErrors.value.name = ['Nama template wajib diisi.']
    return
  }
  saving.value = true
  try {
    if (form.value.id) {
      await updateInvoiceTermsTemplate(form.value.id, {
        name: form.value.name,
        content: form.value.content,
        is_default: form.value.is_default,
        is_active: form.value.is_active,
      })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Template berhasil diperbarui', life: 3000 })
    } else {
      await createInvoiceTermsTemplate({
        name: form.value.name,
        content: form.value.content,
        is_default: form.value.is_default,
      })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Template berhasil ditambahkan', life: 3000 })
    }
    showDialog.value = false
    await fetchAll()
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
    message: `Hapus template "${row.name}"?`,
    header: 'Konfirmasi Hapus',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Hapus',
    rejectLabel: 'Batal',
    acceptClass: 'app-dialog-button app-dialog-button-danger',
    rejectClass: 'app-dialog-button app-dialog-button-secondary',
    accept: async () => {
      try {
        await deleteInvoiceTermsTemplate(row.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Template berhasil dihapus', life: 3000 })
        await fetchAll()
      } catch {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal menghapus template', life: 3000 })
      }
    },
  })
}
</script>

<template>
  <div class="page-container table-page-active">
    <ConfirmDialog />

    <div class="page-header">
      <div class="header-left">
        <h1 class="text-h1">Template Syarat &amp; Ketentuan Invoice</h1>
        <p class="text-secondary text-xs">Kelola template teks syarat &amp; ketentuan yang bisa dipilih saat membuat invoice.</p>
      </div>
      <div class="header-actions">
        <button class="btn-pill btn-primary" type="button" @click="openNew">
          <i class="pi pi-plus"></i>
          Tambah Template
        </button>
      </div>
    </div>

    <div class="filter-bar surface-card">
      <div class="filter-groups">
        <div class="summary-tile-compact">
          <i class="pi pi-file-edit text-info"></i>
          <span>Total Template</span>
          <strong class="font-mono-numeric">{{ templates.length }}</strong>
        </div>
      </div>
      <div class="filter-actions">
        <button class="btn-pill btn-secondary btn-pill-compact" type="button" :disabled="loading" @click="fetchAll">
          <i class="pi pi-refresh"></i>
          Refresh
        </button>
      </div>
    </div>

    <div v-if="!isMobile" class="table-shell list-tab-fill">
      <DataTable :value="templates" :loading="loading" scrollable scrollHeight="flex"
        responsiveLayout="scroll" class="drent-datatable" stripedRows>
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-file-edit"></i>
            <p>Belum ada template syarat &amp; ketentuan.</p>
          </div>
        </template>

        <Column header="Nama Template" style="min-width: 200px">
          <template #body="{ data }">
            <div class="font-semibold">{{ data.name }}</div>
            <Tag v-if="data.is_default" value="Default" severity="success" class="mt-1" style="font-size:10px" />
          </template>
        </Column>

        <Column header="Preview Konten" style="min-width: 300px">
          <template #body="{ data }">
            <div class="content-preview" v-html="data.content"></div>
          </template>
        </Column>

        <Column header="Status" style="min-width: 100px">
          <template #body="{ data }">
            <span class="drent-badge" :class="data.is_active ? 'success' : 'neutral'">
              {{ data.is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
          </template>
        </Column>

        <Column header="Aksi" style="min-width: 110px; text-align: center">
          <template #body="{ data }">
            <div class="action-pill-group">
              <button class="action-btn" type="button" title="Edit" @click="openEdit(data)">
                <i class="pi pi-pencil"></i>
              </button>
              <button class="action-btn action-btn-danger" type="button" title="Hapus" @click="confirmDelete(data)">
                <i class="pi pi-trash"></i>
              </button>
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <div v-else class="mobile-card-list">
      <article v-for="tpl in templates" :key="tpl.id" class="mobile-card">
        <div class="card-header">
          <strong>{{ tpl.name }}</strong>
          <span class="drent-badge" :class="tpl.is_active ? 'success' : 'neutral'">{{ tpl.is_active ? 'Aktif' : 'Nonaktif' }}</span>
        </div>
        <div class="card-body">
          <Tag v-if="tpl.is_default" value="Default" severity="success" style="font-size:10px; width: fit-content" />
          <div class="content-preview" v-html="tpl.content"></div>
        </div>
        <div class="card-footer">
          <button class="btn-pill btn-secondary btn-pill-compact" type="button" @click="openEdit(tpl)">
            <i class="pi pi-pencil"></i> Edit
          </button>
          <button class="btn-pill btn-secondary btn-pill-compact" type="button" @click="confirmDelete(tpl)">
            <i class="pi pi-trash"></i> Hapus
          </button>
        </div>
      </article>

      <div v-if="!loading && !templates.length" class="empty-state">
        <i class="pi pi-file-edit"></i>
        <p>Belum ada template syarat &amp; ketentuan.</p>
      </div>
    </div>

    <Dialog v-model:visible="showDialog"
      :header="form.id ? 'Edit Template' : 'Tambah Template'"
      modal class="custom-dialog" :style="{ width: 'min(680px, 96vw)' }">
      <div class="form-grid">
        <div class="field">
          <label>Nama Template <span class="req">*</span></label>
          <InputText v-model="form.name" placeholder="Contoh: Syarat Standar, Ketentuan Corporate..." class="w-full"
            :class="{ 'p-invalid': formErrors.name }" />
          <small class="p-error" v-if="formErrors.name">{{ formErrors.name[0] }}</small>
        </div>
        <div class="field">
          <label>Konten Syarat &amp; Ketentuan</label>
          <InvoiceTermsEditor v-model="form.content" placeholder="Tulis syarat & ketentuan di sini..." />
          <small class="hint">Konten ini akan ditampilkan di invoice dan bisa diedit sebelum invoice dibuat.</small>
        </div>
        <div class="field-row">
          <div class="field">
            <label>Jadikan Default</label>
            <ToggleButton v-model="form.is_default" onLabel="Ya" offLabel="Tidak"
              onIcon="pi pi-check" offIcon="pi pi-times" class="w-full" />
            <small class="hint">Template default akan otomatis dipilih saat dialog buat invoice dibuka.</small>
          </div>
          <div v-if="form.id" class="field">
            <label>Status</label>
            <ToggleButton v-model="form.is_active" onLabel="Aktif" offLabel="Nonaktif"
              onIcon="pi pi-check" offIcon="pi pi-times" class="w-full" />
          </div>
        </div>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" type="button" :disabled="saving"
          @click="showDialog = false">
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
.summary-tile-compact {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
}

.summary-tile-compact span {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-secondary);
}

.summary-tile-compact strong {
  font-size: 14px;
  font-weight: 700;
  color: var(--text-primary);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 50px 0;
  color: #94a3b8;
}
.empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: .5; }

.content-preview {
  font-size: 11px;
  color: var(--text-secondary);
  max-height: 60px;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  line-height: 1.4;
}

.content-preview :deep(p) { margin: 0 0 2px; }
.content-preview :deep(ul),
.content-preview :deep(ol) { padding-left: 16px; margin: 0; }

.form-grid { display: flex; flex-direction: column; gap: 16px; padding: 8px 0; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field-row { display: flex; gap: 16px; }
.field-row .field { flex: 1; }
.field label { font-weight: 700; font-size: 12px; color: var(--text-secondary); }
.hint { color: var(--text-tertiary); font-size: 11px; line-height: 1.4; }
.req { color: var(--negative); }
.w-full { width: 100%; }
.action-btn-danger { color: var(--negative) !important; }
.text-info { color: var(--info-cyan); }

.drent-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 6px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 600;
  text-transform: capitalize;
  white-space: nowrap;
}
.drent-badge.success { background-color: #E6F6EC; color: #147239; }
.drent-badge.neutral { background-color: #E4E8F3; color: #4A5060; }
.mobile-card-list .card-footer { justify-content: flex-end; gap: var(--space-sm); }
</style>
