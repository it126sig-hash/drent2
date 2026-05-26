<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useUnit } from '../../composables/useUnit'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useAuthStore } from '../../stores/auth'
import { fetchCities } from '../../api/city'
import { getRentalOwners } from '../../api/rentalOwner'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Paginator from 'primevue/paginator'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import ConfirmDialog from 'primevue/confirmdialog'
import AutoComplete from 'primevue/autocomplete'
import UnitFormDialog from '../../components/units/UnitFormDialog.vue'

const {
  units,
  loading,
  pagination,
  fetchAll,
  store,
  update,
  remove,
  batchUpdateCity
} = useUnit()

const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const searchQuery = ref('')
const statusFilter = ref(null)
const cityFilter = ref(null)
const ownerFilter = ref(null)
const cities = ref([])
const owners = ref([])
const selectedOwnerFilter = ref(null)
const selectedBatchOwner = ref(null)
const selectedUnits = ref([])
const showDialog = ref(false)
const selectedUnit = ref(null)
const searchingOwners = ref(false)

// Batch update city state
const showBatchCityDialog = ref(false)
const batchMethod = ref('selected')
const batchOwnerId = ref(null)
const batchCityId = ref(null)
const batchLoading = ref(false)

const batchMethodOptions = [
  { label: 'Hanya Unit Terpilih di Tabel', value: 'selected' },
  { label: 'Semua Unit Milik Pemilik', value: 'owner' }
]

const statusOptions = [
  { label: 'Semua Status', value: null },
  { label: 'Aktif', value: 'Aktif' },
  { label: 'Tidak Aktif', value: 'Tidak Aktif' },
  { label: 'Dalam Servis', value: 'Dalam Servis' }
]

const cityOptions = computed(() => {
  const options = [{ label: 'Semua Kota', value: null }]
  cities.value.forEach(city => {
    options.push({
      label: city.provinsi ? `${city.nama} - ${city.provinsi}` : city.nama,
      value: city.id
    })
  })
  return options
})

const canManage = computed(() => ['superadmin', 'admin_branch'].includes(authStore.user?.role))

onMounted(() => {
  fetchData()
  fetchActiveCities()
})

const searchOwners = async (event) => {
  searchingOwners.value = true
  try {
    const response = await getRentalOwners({ search: event.query || '', per_page: 20 })
    owners.value = response.data.data
  } catch (err) {
    console.error('Gagal mencari pemilik rental', err)
  } finally {
    searchingOwners.value = false
  }
}

watch(selectedOwnerFilter, (newVal) => {
  if (!newVal || (typeof newVal === 'string' && newVal.trim() === '')) {
    ownerFilter.value = null
  } else {
    ownerFilter.value = newVal?.id || null
  }
  onSearch()
})

watch(selectedBatchOwner, (newVal) => {
  if (!newVal || (typeof newVal === 'string' && newVal.trim() === '')) {
    batchOwnerId.value = null
  } else {
    batchOwnerId.value = newVal?.id || null
  }
})

const fetchActiveCities = async () => {
  try {
    const response = await fetchCities({ per_page: 100 })
    cities.value = response.data.data.filter(c => c.is_active)
  } catch (err) {
    console.error('Gagal mengambil data kota', err)
  }
}

const fetchData = async () => {
  try {
    await fetchAll({
      search: searchQuery.value,
      status: statusFilter.value,
      city_id: cityFilter.value,
      rental_owner_id: ownerFilter.value,
      branch_id: authStore.user?.branch_id
    })
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Gagal memuat data unit',
      life: 3000
    })
  }
}

const onSearch = () => {
  pagination.value.current_page = 1
  fetchData()
}

const onPageChange = (event) => {
  pagination.value.current_page = event.page + 1
  fetchData()
}

const openNew = () => {
  selectedUnit.value = null
  showDialog.value = true
}

const editUnit = (unit) => {
  selectedUnit.value = { ...unit }
  showDialog.value = true
}

const saveUnit = async (data) => {
  try {
    if (data.id) {
      await update(data.id, data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Unit berhasil diperbarui', life: 3000 })
    } else {
      await store(data)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Unit berhasil ditambahkan', life: 3000 })
    }
    showDialog.value = false
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.message || 'Terjadi kesalahan saat menyimpan data', life: 3000 })
  }
}

const confirmDelete = (unit) => {
  confirm.require({
    message: `Apakah Anda yakin ingin menghapus unit "${unit.merk} ${unit.tipe}" (${unit.no_polisi})?`,
    header: 'Konfirmasi Hapus',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await remove(unit.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Unit berhasil dihapus', life: 3000 })
      } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal menghapus data', life: 3000 })
      }
    }
  })
}

const openBatchEditCity = () => {
  batchOwnerId.value = null
  selectedBatchOwner.value = null
  batchCityId.value = null
  batchMethod.value = selectedUnits.value.length > 0 ? 'selected' : 'owner'
  showBatchCityDialog.value = true
}

const applyBatchCityEdit = async () => {
  if (!batchCityId.value) return
  
  batchLoading.value = true
  try {
    const payload = {
      type: batchMethod.value === 'selected' ? 'by_ids' : 'by_owner',
      city_id: batchCityId.value
    }

    if (batchMethod.value === 'selected') {
      payload.ids = selectedUnits.value.map(u => u.id)
    } else if (batchMethod.value === 'owner') {
      payload.rental_owner_id = batchOwnerId.value
    }

    await batchUpdateCity(payload)
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Kota berhasil diperbarui secara batch', life: 3000 })
    showBatchCityDialog.value = false
    selectedUnits.value = [] // clear selection
    fetchData() // refresh list
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.message || 'Terjadi kesalahan saat memproses update', life: 3000 })
  } finally {
    batchLoading.value = false
  }
}

const getStatusSeverity = (status) => {
  switch (status) {
    case 'Aktif': return 'success'
    case 'Tidak Aktif': return 'danger'
    case 'Dalam Servis': return 'warning'
    default: return 'info'
  }
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value)
}
</script>

<template>
  <div class="page-container table-page-active unit-list-page">
    <ConfirmDialog />

    <div class="page-header">
      <div class="header-left">
        <div class="header-copy">
          <h1 class="text-h1">Unit Kendaraan</h1>
          <p>Kelola armada dan informasi unit kendaraan</p>
        </div>
      </div>
      <div class="header-actions flex gap-2">
        <button
          v-if="canManage"
          class="btn-pill btn-secondary create-booking-button"
          @click="openBatchEditCity"
        >
          <i class="pi pi-pencil"></i>
          <span class="create-label-desktop">Edit Kota</span>
          <span class="create-label-mobile">Kota</span>
        </button>
        <button
          v-if="canManage"
          class="btn-pill btn-primary create-booking-button"
          @click="openNew"
        >
          <i class="pi pi-plus"></i>
          <span class="create-label-desktop">Tambah Unit</span>
          <span class="create-label-mobile">Unit</span>
        </button>
      </div>
    </div>

    <div class="list-tab-fill">
      <div class="filter-bar surface-card">
        <div class="filter-groups">
          <div class="filter-group filter-group-wide">
            <label>Cari Unit</label>
            <span class="filter-search">
              <i class="pi pi-search" />
              <InputText
                v-model="searchQuery"
                placeholder="Cari tipe, merk, atau plat..."
                @input="onSearch"
                class="w-full"
              />
            </span>
          </div>
          <div class="filter-group">
            <label>Status</label>
            <Dropdown
              v-model="statusFilter"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Filter Status"
              @change="onSearch"
              class="status-filter"
            />
          </div>
          <div class="filter-group">
            <label>Kota</label>
            <Dropdown
              v-model="cityFilter"
              :options="cityOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Semua Kota"
              @change="onSearch"
              class="city-filter"
            />
          </div>
          <div class="filter-group">
            <label>Pemilik</label>
            <AutoComplete
              v-model="selectedOwnerFilter"
              :suggestions="owners"
              @complete="searchOwners"
              optionLabel="nama"
              placeholder="Cari Pemilik"
              dropdown
              forceSelection
              :loading="searchingOwners"
              class="owner-filter w-full md:w-56"
              inputClass="w-full"
            >
              <template #item="slotProps">
                <div>
                  <div class="font-bold">{{ slotProps.item.nama }}</div>
                  <small class="text-secondary text-xs">{{ slotProps.item.kontak_1 }} - {{ slotProps.item.kota }}</small>
                </div>
              </template>
            </AutoComplete>
          </div>
        </div>
      </div>

      <div class="table-shell unit-table-shell">
        <DataTable
          :value="units"
          v-model:selection="selectedUnits"
          :loading="loading"
          lazy
          paginator
          scrollable
          scrollHeight="flex"
          :rows="pagination.per_page"
          :totalRecords="pagination.total"
          :first="(pagination.current_page - 1) * pagination.per_page"
          paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
          currentPageReportTemplate="Menampilkan {first} ke {last} dari {totalRecords} data"
          responsiveLayout="scroll"
          class="drent-datatable unit-desktop-table"
          stripedRows
          @page="onPageChange"
          dataKey="id"
        >
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-car"></i>
              <p>Belum ada data unit kendaraan.</p>
            </div>
          </template>

          <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
          <Column header="Aksi" style="width: 6.5rem; text-align: center">
            <template #body="{ data }">
              <div class="action-pill-group">
                <button type="button" class="action-btn" @click="editUnit(data)" v-tooltip.top="'Edit'">
                  <i class="pi pi-pencil"></i>
                </button>
                <button
                  v-if="canManage"
                  type="button"
                  class="action-btn action-btn-danger"
                  @click="confirmDelete(data)"
                  v-tooltip.top="'Hapus'"
                >
                  <i class="pi pi-trash"></i>
                </button>
              </div>
            </template>
          </Column>

          <Column field="no_polisi" header="Kendaraan" style="min-width: 120px">
            <template #body="{ data }">
               <div class="unit-info">
                <span class="unit-name">{{ data.merk }} {{ data.tipe }}</span>
                <small class="unit-year">Tahun {{ data.tahun || '-' }}</small>
              </div>
              <span class="plat-badge">{{ data.no_polisi }}</span>
            </template>
          </Column>

          <Column header="Pemilik" style="min-width: 150px">
            <template #body="{ data }">
              <span v-if="data.rental_owner" class="owner-text">{{ data.rental_owner.nama }}</span>
              <span v-else class="muted-text">Internal</span>
            </template>
          </Column>

          <Column header="Kota" style="min-width: 130px">
            <template #body="{ data }">
              <span>{{ data.city?.nama || '-' }}</span>
            </template>
          </Column>

          <Column header="Harga Sewa / Hari" style="min-width: 150px">
            <template #body="{ data }">
              <span class="amount-text">{{ formatCurrency(data.harga_1_hari) }}</span>
            </template>
          </Column>

          <Column header="Harga All-in / Hari" style="min-width: 150px">
            <template #body="{ data }">
              <span class="amount-text">{{ formatCurrency(data.harga_all_in) }}</span>
            </template>
          </Column>

          <Column field="status" header="Status" style="min-width: 130px">
            <template #body="{ data }">
              <Tag
                :severity="getStatusSeverity(data.status)"
                :value="data.status"
                class="status-tag"
              />
            </template>
          </Column>
        </DataTable>
      </div>

      <div class="mobile-card-list unit-mobile-list">
        <div v-if="!loading && units.length === 0" class="empty-state app-card">
          <i class="pi pi-car"></i>
          <p>Belum ada data unit kendaraan.</p>
        </div>
        <div v-for="unit in units" :key="unit.id" class="mobile-card app-card">
          <div class="mobile-card-header">
            <div>
              <span class="plat-badge">{{ unit.no_polisi }}</span>
              <h3>{{ unit.merk }} {{ unit.tipe }}</h3>
              <p>Tahun {{ unit.tahun || '-' }}</p>
            </div>
            <Tag :severity="getStatusSeverity(unit.status)" :value="unit.status" class="status-tag" />
          </div>
          <div class="mobile-card-meta">
            <div>
              <span>Pemilik</span>
              <strong>{{ unit.rental_owner?.nama || 'Internal' }}</strong>
            </div>
            <div>
              <span>Kota</span>
              <strong>{{ unit.city?.nama || '-' }}</strong>
            </div>
            <div>
              <span>Harga / Hari</span>
              <strong class="amount-text">{{ formatCurrency(unit.harga_1_hari) }}</strong>
            </div>
            <div>
              <span>Harga All-in</span>
              <strong class="amount-text">{{ formatCurrency(unit.harga_all_in) }}</strong>
            </div>
          </div>
          <div class="mobile-card-actions">
            <Button label="Edit" icon="pi pi-pencil" class="btn-pill btn-secondary" @click="editUnit(unit)" />
            <Button v-if="canManage" label="Hapus" icon="pi pi-trash" class="btn-pill btn-secondary danger-action" @click="confirmDelete(unit)" />
          </div>
        </div>
        <Paginator
          v-if="!loading && pagination.total > pagination.per_page"
          :rows="pagination.per_page"
          :totalRecords="pagination.total"
          :first="(pagination.current_page - 1) * pagination.per_page"
          @page="onPageChange"
          template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
          currentPageReportTemplate="{first} - {last} dari {totalRecords}"
          class="mobile-paginator"
        />
      </div>
    </div>

    <UnitFormDialog
      v-model:visible="showDialog"
      :unit="selectedUnit"
      :loading="loading"
      @save="saveUnit"
      @refresh="fetchData"
    />

    <Dialog 
      v-model:visible="showBatchCityDialog" 
      header="Batch Edit Kota Unit" 
      :modal="true" 
      class="custom-dialog"
      :style="{ width: '450px' }"
    >
      <div class="form-container p-fluid flex flex-col gap-4" style="padding: 15px 5px; display: flex; flex-direction: column; gap: 15px;">
        <!-- Pilihan Tipe Batch Edit -->
        <div class="field" style="display: flex; flex-direction: column; gap: 8px;">
          <label class="font-semibold" style="font-size: 0.85rem; font-weight: 600; color: #475569;">Metode Update</label>
          <Dropdown
            id="batch_mode"
            v-model="batchMethod"
            :options="batchMethodOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full"
          />
        </div>

        <!-- Dropdown Pemilik (Hanya jika metode 'owner') -->
        <div v-if="batchMethod === 'owner'" class="field animate-fade-in" style="display: flex; flex-direction: column; gap: 8px;">
          <label for="batch_owner" class="label-required" style="font-size: 0.85rem; font-weight: 600; color: #475569;">Pilih Pemilik Rental</label>
          <AutoComplete 
            id="batch_owner" 
            v-model="selectedBatchOwner" 
            :suggestions="owners" 
            @complete="searchOwners" 
            optionLabel="nama" 
            placeholder="Cari & Pilih Pemilik" 
            dropdown
            forceSelection
            :loading="searchingOwners"
            class="w-full"
            inputClass="w-full"
            :class="{ 'p-invalid': !batchOwnerId }"
          >
            <template #item="slotProps">
              <div>
                <div class="font-bold">{{ slotProps.item.nama }}</div>
                <small class="text-secondary text-xs">{{ slotProps.item.kontak_1 }} - {{ slotProps.item.kota }}</small>
              </div>
            </template>
          </AutoComplete>
        </div>

        <!-- Dropdown Kota Tujuan -->
        <div class="field" style="display: flex; flex-direction: column; gap: 8px;">
          <label for="batch_city" class="label-required" style="font-size: 0.85rem; font-weight: 600; color: #475569;">Pilih Kota Baru</label>
          <Dropdown 
            id="batch_city" 
            v-model="batchCityId" 
            :options="cities" 
            optionLabel="nama" 
            optionValue="id" 
            placeholder="Pilih Kota Tujuan" 
            filter
            class="w-full"
            :class="{ 'p-invalid': !batchCityId }"
          />
        </div>
      </div>

      <template #footer>
        <div class="dialog-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 10px; border-top: 1px solid #f1f5f9;">
          <Button label="Batal" icon="pi pi-times" class="p-button-text p-button-secondary" @click="showBatchCityDialog = false" />
          <Button 
            label="Simpan Perubahan" 
            icon="pi pi-check" 
            class="p-button-tosca" 
            @click="applyBatchCityEdit" 
            :loading="batchLoading" 
            :disabled="!batchCityId || (batchMethod === 'selected' && selectedUnits.length === 0) || (batchMethod === 'owner' && !batchOwnerId)" 
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.unit-list-page {
  animation: fadeIn 0.25s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}

.status-filter,
.city-filter,
.owner-filter {
  min-width: 200px;
}

.plat-badge {
  font-family: 'Courier New', Courier, monospace;
  font-weight: 700;
  background: var(--text-primary);
  color: var(--text-white);
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 0.85rem;
  letter-spacing: 1px;
}

.unit-info {
  display: flex;
  flex-direction: column;
}

.unit-name {
  font-weight: 700;
  color: var(--text-primary);
}

.unit-year {
  color: var(--text-tertiary);
  font-size: 0.75rem;
}

.owner-text {
  color: var(--text-primary);
}

.muted-text {
  color: var(--text-tertiary);
}

.status-tag {
  font-weight: 600;
  font-size: 0.75rem;
  padding: 4px 10px;
}

.amount-text {
  color: var(--info-cyan);
  font-family: var(--font-mono);
  font-weight: 600;
  white-space: nowrap;
}

.action-btn-danger:hover:not(:disabled) {
  color: var(--negative);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 50px 0;
  color: var(--text-tertiary);
}

.empty-state i {
  font-size: 3rem;
  margin-bottom: 15px;
  opacity: 0.5;
}

.unit-mobile-list {
  display: none;
}

.mobile-card-list {
  flex-direction: column;
  gap: var(--space-md);
}

.mobile-card {
  padding: var(--space-lg);
}

.mobile-card-header,
.mobile-card-meta,
.mobile-card-actions {
  display: flex;
  gap: var(--space-md);
}

.mobile-card-header {
  align-items: flex-start;
  justify-content: space-between;
}

.mobile-card-header h3 {
  margin: 10px 0 0;
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 700;
}

.mobile-card-header p {
  margin: 4px 0 0;
  color: var(--text-secondary);
  font-size: 12px;
}

.mobile-card-meta {
  margin-top: var(--space-lg);
  flex-wrap: wrap;
}

.mobile-card-meta > div {
  flex: 1 1 130px;
  border-radius: var(--radius-default);
  background: var(--card-bg);
  padding: var(--space-md);
}

.mobile-card-meta span {
  display: block;
  color: var(--text-tertiary);
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
}

.mobile-card-meta strong {
  display: block;
  margin-top: 3px;
  color: var(--text-primary);
  font-size: 13px;
}

.mobile-card-actions {
  margin-top: var(--space-lg);
  flex-wrap: wrap;
}

.danger-action {
  color: var(--negative) !important;
}

.mobile-paginator {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
}

@media (max-width: 768px) {
  .unit-table-shell {
    display: none;
  }

  .unit-mobile-list {
    display: flex;
  }

  .status-filter,
  .city-filter,
  .owner-filter {
    width: 100%;
  }
}

.p-button-tosca {
  background-color: #06b6d4 !important;
  border-color: #06b6d4 !important;
  padding: 10px 20px !important;
  font-weight: 600 !important;
  border-radius: 8px !important;
  color: white !important;
}

.p-button-tosca:hover {
  background-color: #0891b2 !important;
  border-color: #0891b2 !important;
}

.label-required::after {
  content: " *";
  color: #f43f5e;
  margin-left: 4px;
}
</style>
