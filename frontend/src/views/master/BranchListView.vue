<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import InputText from 'primevue/inputtext'
import Paginator from 'primevue/paginator'

import { useAuthStore } from '../../stores/auth'
import { useBranch } from '../../composables/useBranch'
import { useCity } from '../../composables/useCity'
import BranchFormDialog from '../../components/branches/BranchFormDialog.vue'

const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const { branches, loading, pagination, fetchAll, store, update, remove } = useBranch()
const { cities, fetchAll: fetchCities } = useCity()

const search = ref('')
const showDialog = ref(false)
const saving = ref(false)
const selectedBranch = ref(null)

const isMobile = ref(window.innerWidth < 768)
const onResize = () => { isMobile.value = window.innerWidth < 768 }
onMounted(() => window.addEventListener('resize', onResize))
onUnmounted(() => window.removeEventListener('resize', onResize))

const isSuperadmin = computed(() => authStore.user?.role === 'superadmin')
const userBranchId = computed(() => authStore.user?.branch_id)

const canCreate = computed(() => isSuperadmin.value)
const canDelete = (row) => isSuperadmin.value
const canEdit = (row) =>
  isSuperadmin.value || (authStore.user?.role === 'admin_branch' && row?.id === userBranchId.value)

const errorMessage = (err, fallback) => {
  const errors = err?.response?.data?.errors
  if (errors) {
    const first = Object.values(errors).flat()[0]
    if (first) return first
  }
  return err?.response?.data?.message || fallback
}

const refresh = () => {
  fetchAll(search.value ? { search: search.value } : {})
}

onMounted(async () => {
  await fetchAll()
  await fetchCities({ per_page: 200, is_active: true }).catch(() => {})
})

const openCreate = () => {
  selectedBranch.value = null
  showDialog.value = true
}

const openEdit = (row) => {
  selectedBranch.value = row
  showDialog.value = true
}

const onDialogSubmit = async ({ id, formData, onError }) => {
  saving.value = true
  try {
    if (id) {
      await update(id, formData)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Cabang berhasil diperbarui', life: 3000 })
    } else {
      await store(formData)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Cabang berhasil ditambahkan', life: 3000 })
    }
    showDialog.value = false
  } catch (err) {
    if (err.response?.data?.errors) {
      onError?.(err.response.data.errors)
    }
    toast.add({ severity: 'error', summary: 'Gagal', detail: errorMessage(err, 'Gagal menyimpan cabang'), life: 3500 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (row) => {
  confirm.require({
    message: `Hapus cabang "${row.name}"?`,
    header: 'Konfirmasi Hapus',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await remove(row.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Cabang berhasil dihapus', life: 3000 })
      } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: errorMessage(err, 'Gagal menghapus cabang'), life: 3000 })
      }
    },
  })
}

const onPageChange = (event) => {
  pagination.value.current_page = event.page + 1
  refresh()
}

const onSearch = () => {
  pagination.value.current_page = 1
  refresh()
}
</script>

<template>
  <div class="page-container branch-list-page table-page-active">
    <ConfirmDialog />

    <div class="page-header">
      <div class="header-left">
        <div>
          <h1>Cabang</h1>
          <p class="text-secondary text-xs">Kelola data cabang/kantor operasional dalam tenant ini.</p>
        </div>
      </div>
      <div class="header-actions">
        <button v-if="canCreate" class="btn-pill btn-primary" type="button" @click="openCreate">
          <i class="pi pi-plus"></i>
          <span>Tambah Cabang</span>
        </button>
      </div>
    </div>

    <div class="filter-bar surface-card">
      <div class="filter-groups">
        <div class="search-input">
          <span class="p-input-icon-left">
            <i class="pi pi-search"></i>
            <InputText
              v-model="search"
              placeholder="Cari nama, email, telp..."
              @keyup.enter="onSearch"
            />
          </span>
        </div>
        <div class="summary-tile-compact">
          <i class="pi pi-sitemap text-info"></i>
          <span>Total Cabang</span>
          <strong class="font-mono-numeric">{{ pagination.total || branches.length }}</strong>
        </div>
      </div>
      <div class="filter-actions">
        <button class="btn-pill btn-secondary btn-pill-compact" type="button" :disabled="loading" @click="onSearch">
          <i class="pi pi-refresh"></i>
          <span>Refresh</span>
        </button>
      </div>
    </div>

    <div v-if="!isMobile" class="table-shell list-tab-fill">
      <DataTable :value="branches" :loading="loading" scrollable scrollHeight="flex" responsiveLayout="scroll"
        class="drent-datatable" stripedRows>
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-sitemap"></i>
            <p>Belum ada data cabang.</p>
          </div>
        </template>

        <Column header="Logo" style="width:80px">
          <template #body="{ data }">
            <div class="row-logo">
              <img v-if="data.logo_url" :src="data.logo_url" :alt="data.name" />
              <span v-else><i class="pi pi-image"></i></span>
            </div>
          </template>
        </Column>
        <Column field="name" header="Nama Cabang" style="min-width:180px" />
        <Column header="Kota" style="min-width:140px">
          <template #body="{ data }">{{ data.city?.nama || '-' }}</template>
        </Column>
        <Column field="phone" header="Telepon" style="min-width:140px">
          <template #body="{ data }">{{ data.phone || '-' }}</template>
        </Column>
        <Column field="email" header="Email" style="min-width:180px">
          <template #body="{ data }">{{ data.email || '-' }}</template>
        </Column>
        <Column header="Aksi" style="min-width:110px;text-align:center">
          <template #body="{ data }">
            <div class="action-pill-group">
              <button v-if="canEdit(data)" class="action-btn" type="button" title="Edit" @click="openEdit(data)">
                <i class="pi pi-pencil"></i>
              </button>
              <button v-if="canDelete(data)" class="action-btn action-btn-danger" type="button" title="Hapus"
                @click="confirmDelete(data)">
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

    <div v-else class="mobile-card-list">
      <article v-for="branch in branches" :key="branch.id" class="mobile-card">
        <div class="card-header">
          <strong>{{ branch.name }}</strong>
          <span class="text-secondary text-xs">{{ branch.city?.nama || '-' }}</span>
        </div>
        <div class="card-body">
          <div><span class="field-hint">Telepon</span> {{ branch.phone || '-' }}</div>
          <div><span class="field-hint">Email</span> {{ branch.email || '-' }}</div>
        </div>
        <div v-if="canEdit(branch) || canDelete(branch)" class="card-footer">
          <button v-if="canEdit(branch)" class="btn-pill btn-secondary btn-pill-compact" type="button" @click="openEdit(branch)">
            <i class="pi pi-pencil"></i> Edit
          </button>
          <button v-if="canDelete(branch)" class="btn-pill btn-secondary btn-pill-compact" type="button" @click="confirmDelete(branch)">
            <i class="pi pi-trash"></i> Hapus
          </button>
        </div>
      </article>

      <div v-if="!loading && !branches.length" class="empty-state">
        <i class="pi pi-sitemap"></i>
        <p>Belum ada data cabang.</p>
      </div>

      <div class="paginator-wrapper">
        <Paginator :rows="pagination.per_page" :totalRecords="pagination.total" :first="(pagination.current_page - 1) * pagination.per_page"
          @page="onPageChange" template="PrevPageLink CurrentPageReport NextPageLink" currentPageReportTemplate="{first}-{last} dari {totalRecords}" />
      </div>
    </div>

    <BranchFormDialog
      v-model:visible="showDialog"
      :branch="selectedBranch"
      :cities="cities"
      :saving="saving"
      @submit="onDialogSubmit"
    />
  </div>
</template>

<style scoped>
.branch-list-page { background: var(--page-bg); }

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

.search-input :deep(.p-inputtext) { width: 280px; }

.row-logo {
  width: 44px;
  height: 44px;
  border-radius: 6px;
  overflow: hidden;
  background: var(--surface-default);
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--surface-border);
}

.row-logo img { width: 100%; height: 100%; object-fit: contain; }
.row-logo i { color: var(--text-tertiary); }

.paginator-wrapper { padding: var(--space-sm); border-top: 1px solid var(--surface-border); }

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 44px 0;
  color: var(--text-tertiary);
}

.empty-state i { font-size: 2.4rem; margin-bottom: var(--space-md); opacity: .7; }

.action-btn-danger { color: var(--negative) !important; }

.text-info { color: var(--info-cyan); }

.field-hint { color: var(--text-tertiary); font-size: 11px; margin-right: 4px; }
.mobile-card-list .card-footer { justify-content: flex-end; gap: var(--space-sm); }
</style>
