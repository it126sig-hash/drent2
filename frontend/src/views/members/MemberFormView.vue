<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useMember } from '../../composables/useMember'
import { useCustomer } from '../../composables/useCustomer'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Textarea from 'primevue/textarea'
import Calendar from 'primevue/calendar'
import FileUpload from 'primevue/fileupload'
import AutoComplete from 'primevue/autocomplete'
import Message from 'primevue/message'

const { store, update, fetchDetail, member: existingMember, loading: memberLoading } = useMember()
const { customers, fetchAll: fetchCustomers } = useCustomer()

const router = useRouter()
const route = useRoute()
const toast = useToast()

const isEdit = computed(() => !!route.params.id)
const loading = ref(false)
const activeTab = ref(0)

const tabs = [
  { label: 'Informasi Dasar', icon: 'pi pi-info-circle' },
  { label: 'Identitas & Dokumen', icon: 'pi pi-id-card' },
  { label: 'Pekerjaan', icon: 'pi pi-briefcase' },
  { label: 'Keluarga & Sosial', icon: 'pi pi-users' }
]

const form = ref({
  customer_id: null,
  selectedCustomer: null,
  status_member: 'Pending',
  tanggal_survey: null,
  catatan: '',
  
  // Identity
  identitas_type: 'KTP',
  foto_wajah: null,
  dokumen_identitas: null,
  dokumen_pendukung_files: [],

  // Job
  nama_kantor: '',
  alamat_kantor: '',
  kontak_kantor: '',
  jabatan: '',
  nama_atasan: '',
  pekerjaan_status: 'Swasta',

  // Social
  pj_nama: '',
  pj_kontak: '',
  pj_hubungan: '',
  ortu_nama: '',
  ortu_alamat: '',
  ortu_kontak: '',
  status_pernikahan: 'Lajang',
  rumah_status: 'Permanen',
  rumah_lokasi: 'Umum'
})

const filteredCustomers = ref([])
const searchingCustomers = ref(false)

onMounted(async () => {
  if (isEdit.value) {
    try {
      const data = await fetchDetail(route.params.id)
      Object.keys(form.value).forEach(key => {
        if (data[key] !== undefined) {
          form.value[key] = data[key]
        }
      })
      form.value.selectedCustomer = data.customer
      form.value.customer_id = data.customer_id
      if (data.tanggal_survey) form.value.tanggal_survey = new Date(data.tanggal_survey)
    } catch (err) {
      toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat data member', life: 3000 })
      router.push('/mdm/members')
    }
  }
})

const searchCustomers = async (event) => {
  searchingCustomers.value = true
  try {
    await fetchCustomers({ search: event.query })
    filteredCustomers.value = customers.value
  } catch (err) {
    console.error(err)
  } finally {
    searchingCustomers.value = false
  }
}

const onCustomerSelect = (event) => {
  form.value.customer_id = event.value.id
}

const onUploadFotoWajah = (event) => {
  form.value.foto_wajah = event.files[0]
}

const onUploadIdentitas = (event) => {
  form.value.dokumen_identitas = event.files[0]
}

const onUploadPendukung = (event) => {
  form.value.dokumen_pendukung_files = event.files
}

const pekerjaanStatusOptions = ['Pelajar', 'PNS', 'Swasta', 'Wiraswasta']
const identitasOptions = ['KTP', 'SIM', 'Paspor']
const statusPernikahanOptions = ['Lajang', 'Menikah', 'Cerai']
const rumahStatusOptions = ['Ruko', 'Permanen', 'Semi Permanen']
const rumahLokasiOptions = ['Umum', 'Biasa', 'Gang']

const nextTab = () => {
  if (activeTab.value < tabs.length - 1) activeTab.value++
}

const prevTab = () => {
  if (activeTab.value > 0) activeTab.value--
}

const submitForm = async () => {
  if (!form.value.customer_id) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Silakan pilih pelanggan terlebih dahulu', life: 3000 })
    activeTab.value = 0
    return
  }

  loading.value = true
  try {
    const formData = new FormData()
    
    // Append simple fields
    Object.keys(form.value).forEach(key => {
      if (['foto_wajah', 'dokumen_identitas', 'dokumen_pendukung_files', 'selectedCustomer', 'tanggal_survey'].includes(key)) return
      if (form.value[key] !== null) formData.append(key, form.value[key])
    })

    if (form.value.tanggal_survey) {
        formData.append('tanggal_survey', form.value.tanggal_survey.toISOString().split('T')[0])
    }

    // Append files
    if (form.value.foto_wajah) formData.append('foto_wajah', form.value.foto_wajah)
    if (form.value.dokumen_identitas) formData.append('dokumen_identitas', form.value.dokumen_identitas)
    
    form.value.dokumen_pendukung_files.forEach((file, index) => {
      formData.append(`dokumen_pendukung_files[${index}]`, file)
    })

    if (isEdit.value) {
      await update(route.params.id, formData)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Data member berhasil diperbarui', life: 3000 })
    } else {
      await store(formData)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Pendaftaran member berhasil', life: 3000 })
    }
    router.push('/mdm/members')
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || 'Terjadi kesalahan', life: 3000 })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="page-container">
    <div class="detail-page-header flex justify-between items-center mb-4">
      <div class="header-left flex items-center gap-3">
        <Button 
          icon="pi pi-arrow-left" 
          class="p-button-rounded p-button-text p-button-secondary" 
          @click="router.back()" 
        />
        <div>
          <h1 class="page-title m-0 text-2xl font-bold text-[var(--text-primary)]">{{ isEdit ? 'Edit Member' : 'Pendaftaran Member Baru' }}</h1>
          <p class="page-subtitle m-0 mt-1 text-[var(--text-secondary)]">Lengkapi formulir pendaftaran member lepas kunci</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-6 mt-4">
      <!-- Sidebar / Navigation -->
      <div class="md:col-span-3">
        <div class="app-card border border-[var(--surface-border)] bg-[var(--surface-default)] p-2 flex flex-col gap-1 rounded-lg">
          <div 
            v-for="(tab, index) in tabs" 
            :key="index" 
            class="tab-item p-3 rounded-lg cursor-pointer transition-colors duration-200 flex items-center gap-3 font-semibold"
            :class="{ 'active': activeTab === index }"
            @click="activeTab = index"
          >
            <i :class="tab.icon" class="text-xl"></i>
            <span>{{ tab.label }}</span>
          </div>
        </div>
      </div>

      <!-- Form Content -->
      <div class="md:col-span-9">
        <div class="app-card border border-[var(--surface-border)] bg-[var(--surface-default)] flex flex-col h-full rounded-lg">
          <!-- Section 1: Pelanggan & Survey -->
          <div v-show="activeTab === 0" class="flex flex-col h-full">
            <div class="app-section-header px-4 py-3 border-b border-[var(--surface-border)] flex items-center gap-2">
              <i class="pi pi-info-circle text-[var(--text-primary)] text-xl"></i>
              <span class="font-semibold text-lg text-[var(--text-primary)]">Informasi Dasar & Survey</span>
            </div>
            <div class="p-4 flex flex-col gap-4 flex-grow">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Pelanggan <span class="text-red-500">*</span></label>
                  <AutoComplete 
                    v-model="form.selectedCustomer" 
                    :suggestions="filteredCustomers" 
                    @complete="searchCustomers" 
                    @item-select="onCustomerSelect"
                    optionLabel="nama" 
                    placeholder="Cari nama pelanggan..." 
                    :disabled="isEdit"
                    :loading="searchingCustomers"
                    class="w-full"
                    inputClass="w-full"
                  >
                    <template #item="slotProps">
                      <div>
                        <div class="font-bold">{{ slotProps.item.nama }}</div>
                        <small class="text-[var(--text-secondary)]">{{ slotProps.item.kontak_1 }} - {{ slotProps.item.kota }}</small>
                      </div>
                    </template>
                  </AutoComplete>
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Tanggal Survey</label>
                  <Calendar v-model="form.tanggal_survey" dateFormat="yy-mm-dd" showIcon class="w-full" />
                </div>
              </div>
              <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-[var(--text-secondary)]">Catatan Surveyor</label>
                <Textarea v-model="form.catatan" rows="4" class="w-full" placeholder="Masukkan hasil survey lapangan..." />
              </div>
            </div>
          </div>

          <!-- Section 2: Identitas & Dokumen -->
          <div v-show="activeTab === 1" class="flex flex-col h-full">
            <div class="app-section-header px-4 py-3 border-b border-[var(--surface-border)] flex items-center gap-2">
              <i class="pi pi-id-card text-[var(--text-primary)] text-xl"></i>
              <span class="font-semibold text-lg text-[var(--text-primary)]">Identitas & Dokumen</span>
            </div>
            <div class="p-4 flex flex-col gap-4 flex-grow">
              <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-4 flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Tipe Identitas</label>
                  <Dropdown v-model="form.identitas_type" :options="identitasOptions" class="w-full" />
                </div>
                <div class="md:col-span-8 flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Foto Wajah</label>
                  <FileUpload mode="basic" @select="onUploadFotoWajah" :auto="false" accept="image/*" chooseLabel="Pilih Foto" class="w-full p-button-outlined p-button-secondary" />
                  <small v-if="isEdit && existingMember?.has_foto_wajah" class="text-green-600 font-semibold">✓ Foto sudah tersedia.</small>
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Dokumen Identitas (KTP/SIM)</label>
                  <FileUpload mode="basic" @select="onUploadIdentitas" :auto="false" accept="image/*,application/pdf" chooseLabel="Pilih Dokumen" class="w-full p-button-outlined p-button-secondary" />
                  <small v-if="isEdit && existingMember?.has_dokumen_identitas" class="text-green-600 font-semibold">✓ Dokumen identitas tersedia.</small>
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Dokumen Pendukung (KK, dll)</label>
                  <FileUpload mode="basic" @select="onUploadPendukung" :auto="false" :multiple="true" accept="image/*,application/pdf" chooseLabel="Pilih File" class="w-full p-button-outlined p-button-secondary" />
                </div>
              </div>
            </div>
          </div>

          <!-- Section 3: Pekerjaan -->
          <div v-show="activeTab === 2" class="flex flex-col h-full">
            <div class="app-section-header px-4 py-3 border-b border-[var(--surface-border)] flex items-center gap-2">
              <i class="pi pi-briefcase text-[var(--text-primary)] text-xl"></i>
              <span class="font-semibold text-lg text-[var(--text-primary)]">Informasi Pekerjaan</span>
            </div>
            <div class="p-4 flex flex-col gap-4 flex-grow">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Nama Kantor/Instansi</label>
                  <InputText v-model="form.nama_kantor" class="w-full" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Status Pekerjaan</label>
                  <Dropdown v-model="form.pekerjaan_status" :options="pekerjaanStatusOptions" class="w-full" />
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Jabatan</label>
                  <InputText v-model="form.jabatan" class="w-full" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Nama Atasan</label>
                  <InputText v-model="form.nama_atasan" class="w-full" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Kontak Kantor</label>
                  <InputText v-model="form.kontak_kantor" class="w-full" />
                </div>
              </div>
              <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-[var(--text-secondary)]">Alamat Kantor</label>
                <Textarea v-model="form.alamat_kantor" rows="2" class="w-full" />
              </div>
            </div>
          </div>

          <!-- Section 4: Keluarga & Sosial -->
          <div v-show="activeTab === 3" class="flex flex-col h-full">
            <div class="app-section-header px-4 py-3 border-b border-[var(--surface-border)] flex items-center gap-2">
              <i class="pi pi-users text-[var(--text-primary)] text-xl"></i>
              <span class="font-semibold text-lg text-[var(--text-primary)]">Informasi Keluarga & Sosial</span>
            </div>
            <div class="p-4 flex flex-col gap-4 flex-grow">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Status Pernikahan</label>
                  <Dropdown v-model="form.status_pernikahan" :options="statusPernikahanOptions" class="w-full" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Keadaan Rumah</label>
                  <Dropdown v-model="form.rumah_status" :options="rumahStatusOptions" class="w-full" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-[var(--text-secondary)]">Lokasi Rumah</label>
                  <Dropdown v-model="form.rumah_lokasi" :options="rumahLokasiOptions" class="w-full" />
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                <div>
                  <div class="app-muted-panel p-4 rounded-lg h-full flex flex-col gap-3">
                    <span class="font-semibold text-[var(--text-primary)] border-b border-[var(--surface-border)] pb-2">Data Penanggung Jawab (PJ)</span>
                    <div class="flex flex-col gap-2">
                      <label class="text-sm font-semibold text-[var(--text-secondary)]">Nama PJ</label>
                      <InputText v-model="form.pj_nama" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-2">
                      <label class="text-sm font-semibold text-[var(--text-secondary)]">Kontak PJ</label>
                      <InputText v-model="form.pj_kontak" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-2">
                      <label class="text-sm font-semibold text-[var(--text-secondary)]">Hubungan</label>
                      <InputText v-model="form.pj_hubungan" class="w-full" />
                    </div>
                  </div>
                </div>

                <div>
                  <div class="app-muted-panel p-4 rounded-lg h-full flex flex-col gap-3">
                    <span class="font-semibold text-[var(--text-primary)] border-b border-[var(--surface-border)] pb-2">Data Orang Tua</span>
                    <div class="flex flex-col gap-2">
                      <label class="text-sm font-semibold text-[var(--text-secondary)]">Nama Orang Tua</label>
                      <InputText v-model="form.ortu_nama" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-2">
                      <label class="text-sm font-semibold text-[var(--text-secondary)]">Kontak Orang Tua</label>
                      <InputText v-model="form.ortu_kontak" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-2">
                      <label class="text-sm font-semibold text-[var(--text-secondary)]">Alamat Orang Tua</label>
                      <Textarea v-model="form.ortu_alamat" rows="2" class="w-full" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Form Navigation Footer -->
          <div class="px-4 py-3 border-t border-[var(--surface-border)] bg-[var(--card-bg)] flex justify-between items-center mt-auto rounded-b-lg">
            <Button 
              label="Sebelumnya" 
              icon="pi pi-arrow-left" 
              class="btn-pill btn-secondary" 
              @click="prevTab" 
              :disabled="activeTab === 0"
            />
            <div class="flex gap-2">
              <Button 
                v-if="activeTab < tabs.length - 1"
                label="Selanjutnya" 
                icon="pi pi-arrow-right" 
                iconPos="right"
                class="btn-pill btn-primary" 
                @click="nextTab" 
              />
              <Button 
                v-if="activeTab === tabs.length - 1"
                label="Simpan Pendaftaran Member" 
                icon="pi pi-check" 
                class="btn-pill btn-primary px-4" 
                :loading="loading" 
                @click="submitForm"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tab-item {
  color: var(--text-secondary, #64748b);
}
.tab-item:hover {
  background-color: var(--surface-hover, #f1f5f9);
}
.tab-item.active {
  background-color: var(--text-primary, #1e293b);
  color: #ffffff;
}
.app-muted-panel {
  background-color: var(--page-bg, #f8fafc);
  border: 1px solid var(--surface-border, #e2e8f0);
}
</style>
