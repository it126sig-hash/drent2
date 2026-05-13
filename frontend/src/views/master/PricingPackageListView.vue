<script setup>
import { ref, onMounted, computed } from 'vue'
import { usePricingPackage } from '../../composables/usePricingPackage'
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
import Dropdown from 'primevue/dropdown'
import Textarea from 'primevue/textarea'
import ToggleButton from 'primevue/togglebutton'
import Tag from 'primevue/tag'
import ConfirmDialog from 'primevue/confirmdialog'
import Paginator from 'primevue/paginator'

const { packages, loading, pagination, fetchAll, store, update, remove } = usePricingPackage()
const { costTypes, fetchAll: fetchCostTypes } = useCostType()
const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const canManage = computed(() => ['superadmin', 'admin_branch'].includes(authStore.user?.role))
const costTypeOptions = computed(() =>
  costTypes.value
    .filter((type) => type.is_active)
    .map((type) => ({ id: type.id, label: type.nama, kode: type.kode, require_description: type.require_description }))
)
const itemTypeOptions = [
  { label: 'Biaya', value: 'biaya' },
  { label: 'Diskon', value: 'diskon' },
]

const showDialog = ref(false)
const emptyItem = () => ({ cost_type_id: null, type: 'biaya', label: '', amount: 0, keterangan: '' })
const emptyForm = () => ({ id: null, nama_paket: '', harga: null, keterangan: '', is_active: true, items: [] })
const form = ref(emptyForm())
const formErrors = ref({})
const saving = ref(false)

onMounted(() => {
  fetchAll()
  fetchCostTypes({ per_page: 100 })
})

const openNew = () => {
  form.value = emptyForm()
  formErrors.value = {}
  showDialog.value = true
}

const openEdit = (row) => {
  form.value = {
    id: row.id,
    nama_paket: row.nama_paket,
    harga: row.harga,
    keterangan: row.keterangan || '',
    is_active: !!row.is_active,
    items: row.items?.map((item) => ({
      cost_type_id: item.cost_type_id ?? null,
      type: item.type || 'biaya',
      label: item.label || item.cost_type?.nama || '',
      amount: item.amount || 0,
      keterangan: item.keterangan || '',
    })) || []
  }
  formErrors.value = {}
  showDialog.value = true
}

const addItem = () => {
  form.value.items.push(emptyItem())
}

const removeItem = (idx) => {
  form.value.items.splice(idx, 1)
}

const onItemCostTypeChange = (idx, typeId) => {
  const costType = costTypes.value.find((type) => type.id === typeId)
  if (costType) form.value.items[idx].label = costType.nama
}

const fieldError = (field) => formErrors.value[field]?.[0]

const formatItemsSummary = (items = []) => {
  if (!items.length) return '-'
  return items.map((item) => item.label || item.cost_type?.nama).filter(Boolean).join(', ')
}

const packageItemsTotal = (items = []) =>
  items.reduce((sum, item) => sum + (item.type === 'diskon' ? -1 : 1) * (item.amount || 0), 0)

const save = async () => {
  formErrors.value = {}
  saving.value = true
  try {
    if (form.value.id) {
      await update(form.value.id, form.value)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Paket harga berhasil diperbarui', life: 3000 })
    } else {
      await store(form.value)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Paket harga berhasil ditambahkan', life: 3000 })
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
    message: `Hapus paket "${row.nama_paket}"?`,
    header: 'Konfirmasi Hapus',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await remove(row.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Paket berhasil dihapus', life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal menghapus paket', life: 3000 })
      }
    }
  })
}

const onPageChange = (event) => {
  pagination.value.current_page = event.page + 1
  fetchAll()
}

const formatCurrency = (v) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v)
</script>

<template>
  <div class="view-container">
    <ConfirmDialog />

    <div class="header-section">
      <div class="header-content">
        <h1>Paket Harga All In</h1>
        <p>Kelola paket harga all-in untuk ditawarkan ke konsumen</p>
      </div>
      <Button v-if="canManage" label="Tambah Paket" icon="pi pi-plus" class="p-button-tosca" @click="openNew" />
    </div>

    <div class="content-card">
      <DataTable :value="packages" :loading="loading" responsiveLayout="scroll" class="p-datatable-sm" stripedRows>
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-tag"></i>
            <p>Belum ada paket harga.</p>
          </div>
        </template>

        <Column field="nama_paket" header="Nama Paket" style="min-width:200px" />

        <Column header="Item Biaya" style="min-width:260px">
          <template #body="{ data }">
            <div class="item-summary">
              <span class="text-slate-600 text-sm">{{ formatItemsSummary(data.items) }}</span>
              <small v-if="data.items?.length">{{ data.items.length }} item | {{ formatCurrency(packageItemsTotal(data.items)) }}</small>
            </div>
          </template>
        </Column>

        <Column field="harga" header="Harga All In" style="min-width:160px">
          <template #body="{ data }">
            <span class="price-text">{{ formatCurrency(data.harga) }}</span>
          </template>
        </Column>

        <Column field="keterangan" header="Keterangan" style="min-width:250px">
          <template #body="{ data }">
            <span class="text-slate-600 text-sm">{{ data.keterangan || '-' }}</span>
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
    <Dialog v-model:visible="showDialog" :header="form.id ? 'Edit Paket Harga' : 'Tambah Paket Harga'" modal :style="{ width: '760px' }" :breakpoints="{ '820px': '95vw' }">
      <div class="form-grid">
        <div class="field">
          <label>Nama Paket <span class="req">*</span></label>
          <InputText v-model="form.nama_paket" placeholder="All In Avanza Bandung..." class="w-full" :class="{ 'p-invalid': formErrors.nama_paket }" />
          <small class="p-error" v-if="formErrors.nama_paket">{{ formErrors.nama_paket[0] }}</small>
        </div>
        <div class="field">
          <label>Harga All In (IDR) <span class="req">*</span></label>
          <InputNumber v-model="form.harga" :min="0" mode="currency" currency="IDR" locale="id-ID" class="w-full" :class="{ 'p-invalid': formErrors.harga }" />
          <small class="p-error" v-if="formErrors.harga">{{ formErrors.harga[0] }}</small>
        </div>
        <div class="field">
          <div class="section-label">
            <label>Detail Biaya Operasional</label>
            <span>{{ form.items.length }} item</span>
          </div>
          <div v-if="!form.items.length" class="empty-items">
            Belum ada item biaya.
          </div>
          <div v-for="(item, idx) in form.items" :key="idx" class="item-row">
            <div class="item-header">
              <span>Item {{ idx + 1 }}</span>
              <Button icon="pi pi-times" text rounded severity="danger" size="small" class="item-remove" @click="removeItem(idx)" />
            </div>
            <div class="item-grid">
              <div class="field">
                <label>Tipe Biaya</label>
                <Dropdown v-model="item.cost_type_id" :options="costTypeOptions" optionLabel="label" optionValue="id" placeholder="Pilih tipe" showClear class="w-full" @change="onItemCostTypeChange(idx, item.cost_type_id)" />
                <small class="p-error" v-if="fieldError(`items.${idx}.cost_type_id`)">{{ fieldError(`items.${idx}.cost_type_id`) }}</small>
              </div>
              <div class="field">
                <label>Biaya / Diskon</label>
                <Dropdown v-model="item.type" :options="itemTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
                <small class="p-error" v-if="fieldError(`items.${idx}.type`)">{{ fieldError(`items.${idx}.type`) }}</small>
              </div>
              <div class="field">
                <label>Nominal</label>
                <InputNumber v-model="item.amount" :min="0" mode="currency" currency="IDR" locale="id-ID" class="w-full" :class="{ 'p-invalid': fieldError(`items.${idx}.amount`) }" />
                <small class="p-error" v-if="fieldError(`items.${idx}.amount`)">{{ fieldError(`items.${idx}.amount`) }}</small>
              </div>
              <div class="field item-label-field">
                <label>Keterangan Item <span class="req">*</span></label>
                <InputText v-model="item.label" placeholder="Driver, BBM, Tol..." class="w-full" :class="{ 'p-invalid': fieldError(`items.${idx}.label`) }" />
                <small class="p-error" v-if="fieldError(`items.${idx}.label`)">{{ fieldError(`items.${idx}.label`) }}</small>
              </div>
              <div v-if="costTypes.find((type) => type.id === item.cost_type_id)?.require_description" class="field item-note-field">
                <label>Detail Tambahan</label>
                <InputText v-model="item.keterangan" placeholder="Detail sesuai tipe biaya" class="w-full" />
              </div>
            </div>
          </div>
          <div class="items-footer">
            <Button label="Tambah Item" icon="pi pi-plus" text size="small" class="text-blue-600 font-semibold" @click="addItem" />
            <span>Total biaya item: {{ formatCurrency(packageItemsTotal(form.items)) }}</span>
          </div>
        </div>
        <div class="field">
          <label>Keterangan (Include / Exclude)</label>
          <Textarea v-model="form.keterangan" rows="4" placeholder="Include: Driver, BBM, Tol&#10;Exclude: Parkir, Makan driver" class="w-full" autoResize />
        </div>
        <div class="field">
          <label>Status</label>
          <ToggleButton v-model="form.is_active" onLabel="Aktif" offLabel="Nonaktif" onIcon="pi pi-check" offIcon="pi pi-times" />
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
.price-text { font-weight: 700; color: #0891b2; }
.item-summary { display: flex; flex-direction: column; gap: 2px; }
.item-summary small { color: #94a3b8; font-size: .75rem; }
.form-grid { display: flex; flex-direction: column; gap: 16px; padding: 8px 0; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-weight: 600; font-size: .875rem; color: #374151; }
.section-label { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.section-label span { color: #64748b; font-size: .8rem; font-weight: 600; }
.empty-items { border: 1px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; color: #94a3b8; padding: 18px; text-align: center; font-size: .875rem; }
.item-row { border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; padding: 12px; }
.item-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; color: #64748b; font-size: .8rem; font-weight: 700; }
.item-remove { width: 28px; height: 28px; padding: 0; }
.item-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(120px, .8fr) minmax(140px, 1fr); gap: 12px; }
.item-label-field, .item-note-field { grid-column: span 3; }
.items-footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; color: #64748b; font-size: .85rem; font-weight: 600; }
.req { color: #ef4444; }
.w-full { width: 100%; }
.p-button-tosca { background-color: #06b6d4 !important; border-color: #06b6d4 !important; }
.p-button-tosca:hover { background-color: #0891b2 !important; border-color: #0891b2 !important; }
:deep(.p-datatable .p-datatable-thead > tr > th) { background-color: #f8fafc; color: #475569; font-weight: 700; text-transform: uppercase; font-size: .75rem; letter-spacing: .5px; padding: 15px; }
:deep(.p-datatable .p-datatable-tbody > tr > td) { padding: 15px; }
@media (max-width: 640px) {
  .item-grid { grid-template-columns: 1fr; }
  .item-label-field, .item-note-field { grid-column: auto; }
  .items-footer { align-items: flex-start; flex-direction: column; }
}
</style>
