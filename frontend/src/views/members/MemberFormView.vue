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
  try {
    await fetchCustomers({ search: event.query })
    filteredCustomers.value = customers.value
  } catch (err) {
    console.error(err)
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
  <div class="view-container">
    <div class="header-section">
      <div class="header-content">
        <h1>{{ isEdit ? 'Edit Member' : 'Pendaftaran Member Baru' }}</h1>
        <p>Lengkapi formulir pendaftaran member lepas kunci</p>
      </div>
      <Button 
        label="Batal" 
        icon="pi pi-times" 
        class="p-button-outlined p-button-secondary" 
        @click="router.back()" 
      />
    </div>

    <div class="form-layout">
      <!-- Sidebar Tabs -->
      <div class="form-sidebar">
        <div 
          v-for="(tab, index) in tabs" 
          :key="index" 
          class="tab-item" 
          :class="{ 'active': activeTab === index }"
          @click="activeTab = index"
        >
          <i :class="tab.icon"></i>
          <span>{{ tab.label }}</span>
        </div>
      </div>

      <!-- Form Content -->
      <div class="form-main">
        <div class="form-card">
          <!-- Section 1: Pelanggan & Survey -->
          <div v-show="activeTab === 0" class="tab-content">
            <h2 class="section-title">Informasi Dasar & Survey</h2>
            <div class="form-body">
              <div class="form-row">
                <div class="form-group half">
                  <label>Pelanggan <span class="text-red-500">*</span></label>
                  <AutoComplete 
                    v-model="form.selectedCustomer" 
                    :suggestions="filteredCustomers" 
                    @complete="searchCustomers" 
                    @item-select="onCustomerSelect"
                    optionLabel="nama" 
                    placeholder="Cari nama pelanggan..." 
                    :disabled="isEdit"
                    class="w-full"
                    inputClass="w-full"
                  >
                    <template #item="slotProps">
                      <div class="customer-item">
                        <div class="font-bold">{{ slotProps.item.nama }}</div>
                        <small>{{ slotProps.item.kontak_1 }} - {{ slotProps.item.kota }}</small>
                      </div>
                    </template>
                  </AutoComplete>
                </div>
                <div class="form-group half">
                  <label>Tanggal Survey</label>
                  <Calendar v-model="form.tanggal_survey" dateFormat="yy-mm-dd" showIcon class="w-full" />
                </div>
              </div>
              <div class="form-group">
                <label>Catatan Surveyor</label>
                <Textarea v-model="form.catatan" rows="4" class="w-full" placeholder="Masukkan hasil survey lapangan..." />
              </div>
            </div>
          </div>

          <!-- Section 2: Identitas & Dokumen -->
          <div v-show="activeTab === 1" class="tab-content">
            <h2 class="section-title">Identitas & Dokumen</h2>
            <div class="form-body">
              <div class="form-row">
                <div class="form-group third">
                  <label>Tipe Identitas</label>
                  <Dropdown v-model="form.identitas_type" :options="identitasOptions" class="w-full" />
                </div>
                <div class="form-group twothirds">
                  <label>Foto Wajah</label>
                  <FileUpload mode="basic" @select="onUploadFotoWajah" :auto="false" accept="image/*" chooseLabel="Pilih Foto" class="w-full" />
                  <small v-if="isEdit && existingMember?.has_foto_wajah" class="text-green-600 block mt-1">✓ Foto sudah tersedia.</small>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group half">
                  <label>Dokumen Identitas (KTP/SIM)</label>
                  <FileUpload mode="basic" @select="onUploadIdentitas" :auto="false" accept="image/*,application/pdf" chooseLabel="Pilih Dokumen" class="w-full" />
                  <small v-if="isEdit && existingMember?.has_dokumen_identitas" class="text-green-600 block mt-1">✓ Dokumen identitas sudah tersedia.</small>
                </div>
                <div class="form-group half">
                  <label>Dokumen Pendukung (KK, dll)</label>
                  <FileUpload mode="basic" @select="onUploadPendukung" :auto="false" :multiple="true" accept="image/*,application/pdf" chooseLabel="Pilih File" class="w-full" />
                </div>
              </div>
            </div>
          </div>

          <!-- Section 3: Pekerjaan -->
          <div v-show="activeTab === 2" class="tab-content">
            <h2 class="section-title">Informasi Pekerjaan</h2>
            <div class="form-body">
              <div class="form-row">
                <div class="form-group half">
                  <label>Nama Kantor/Instansi</label>
                  <InputText v-model="form.nama_kantor" class="w-full" />
                </div>
                <div class="form-group half">
                  <label>Status Pekerjaan</label>
                  <Dropdown v-model="form.pekerjaan_status" :options="pekerjaanStatusOptions" class="w-full" />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group third">
                  <label>Jabatan</label>
                  <InputText v-model="form.jabatan" class="w-full" />
                </div>
                <div class="form-group third">
                  <label>Nama Atasan</label>
                  <InputText v-model="form.nama_atasan" class="w-full" />
                </div>
                <div class="form-group third">
                  <label>Kontak Kantor</label>
                  <InputText v-model="form.kontak_kantor" class="w-full" />
                </div>
              </div>
              <div class="form-group">
                <label>Alamat Kantor</label>
                <Textarea v-model="form.alamat_kantor" rows="2" class="w-full" />
              </div>
            </div>
          </div>

          <!-- Section 4: Keluarga & Sosial -->
          <div v-show="activeTab === 3" class="tab-content">
            <h2 class="section-title">Informasi Keluarga & Sosial</h2>
            <div class="form-body">
              <div class="form-row">
                <div class="form-group third">
                  <label>Status Pernikahan</label>
                  <Dropdown v-model="form.status_pernikahan" :options="statusPernikahanOptions" class="w-full" />
                </div>
                <div class="form-group third">
                  <label>Keadaan Rumah</label>
                  <Dropdown v-model="form.rumah_status" :options="rumahStatusOptions" class="w-full" />
                </div>
                <div class="form-group third">
                  <label>Lokasi Rumah</label>
                  <Dropdown v-model="form.rumah_lokasi" :options="rumahLokasiOptions" class="w-full" />
                </div>
              </div>

              <div class="form-row mt-4">
                <div class="form-group half sub-section">
                  <h3 class="subsection-title">Data Penanggung Jawab (PJ)</h3>
                  <div class="form-group">
                    <label>Nama PJ</label>
                    <InputText v-model="form.pj_nama" class="w-full" />
                  </div>
                  <div class="form-group">
                    <label>Kontak PJ</label>
                    <InputText v-model="form.pj_kontak" class="w-full" />
                  </div>
                  <div class="form-group">
                    <label>Hubungan</label>
                    <InputText v-model="form.pj_hubungan" class="w-full" />
                  </div>
                </div>

                <div class="form-group half sub-section">
                  <h3 class="subsection-title">Data Orang Tua</h3>
                  <div class="form-group">
                    <label>Nama Orang Tua</label>
                    <InputText v-model="form.ortu_nama" class="w-full" />
                  </div>
                  <div class="form-group">
                    <label>Kontak Orang Tua</label>
                    <InputText v-model="form.ortu_kontak" class="w-full" />
                  </div>
                  <div class="form-group">
                    <label>Alamat Orang Tua</label>
                    <Textarea v-model="form.ortu_alamat" rows="2" class="w-full" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Form Navigation Footer -->
          <div class="form-footer">
            <Button 
              label="Sebelumnya" 
              icon="pi pi-arrow-left" 
              class="p-button-text p-button-secondary" 
              @click="prevTab" 
              :disabled="activeTab === 0"
            />
            <div class="flex gap-2">
              <Button 
                v-if="activeTab < tabs.length - 1"
                label="Selanjutnya" 
                icon="pi pi-arrow-right" 
                iconPos="right"
                class="p-button-tosca" 
                @click="nextTab" 
              />
              <Button 
                v-if="activeTab === tabs.length - 1"
                label="Simpan Pendaftaran Member" 
                icon="pi pi-check" 
                class="p-button-tosca font-bold px-4" 
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
.view-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 25px;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.header-content h1 {
  font-size: 2rem;
  font-weight: 800;
  color: #1e293b;
  margin: 0;
}

.header-content p {
  color: #64748b;
  margin-top: 5px;
  font-size: 1.1rem;
}

/* Layout */
.form-layout {
  display: flex;
  gap: 30px;
  align-items: flex-start;
}

/* Sidebar Tabs */
.form-sidebar {
  width: 280px;
  background-color: #ffffff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  padding: 15px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.tab-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 16px 20px;
  border-radius: 12px;
  color: #64748b;
  cursor: pointer;
  transition: all 0.25s ease;
  font-weight: 600;
}

.tab-item i {
  font-size: 1.2rem;
}

.tab-item:hover {
  background-color: #f8fafc;
  color: #1e293b;
  transform: translateX(5px);
}

.tab-item.active {
  background-color: #06b6d4;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
}

/* Main Content */
.form-main {
  flex: 1;
}

.form-card {
  background-color: #ffffff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.section-title {
  padding: 24px 30px;
  background-color: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  font-size: 1.4rem;
  font-weight: 800;
  color: #1e293b;
  margin: 0;
}

.form-body {
  padding: 30px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-row {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.form-group.half {
  flex: 1 1 calc(50% - 10px);
}

.form-group.third {
  flex: 1 1 calc(33.333% - 14px);
}

.form-group.twothirds {
  flex: 1 1 calc(66.666% - 6px);
}

.form-group.sub-section {
  background-color: #f8fafc;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #f1f5f9;
}

.form-group label {
  font-weight: 700;
  color: #475569;
  font-size: 0.95rem;
}

.subsection-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: #0e7490;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 2px solid #e0f2fe;
}

.form-footer {
  padding: 24px 30px;
  background-color: #f8fafc;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
}

.p-button-tosca {
  background-color: #06b6d4 !important;
  border-color: #06b6d4 !important;
}

.p-button-tosca:hover {
  background-color: #0891b2 !important;
  border-color: #0891b2 !important;
  transform: translateY(-1px);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.customer-item {
  display: flex;
  flex-direction: column;
}

@media (max-width: 992px) {
  .form-layout {
    flex-direction: column;
  }
  
  .form-sidebar {
    width: 100%;
    flex-direction: row;
    overflow-x: auto;
    padding: 10px;
  }
  
  .tab-item {
    white-space: nowrap;
    padding: 12px 18px;
  }

  .form-group.half, .form-group.third, .form-group.twothirds {
    flex: 1 1 100%;
  }
}
</style>
