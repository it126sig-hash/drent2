<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useMember } from '../../composables/useMember'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useAuthStore } from '../../stores/auth'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import ConfirmDialog from 'primevue/confirmdialog'
import Divider from 'primevue/divider'
import axios from '../../api/axios'

const { fetchDetail, activate, updateStatus, extendMember, fetchExtensions, member, extensions, loading } = useMember()
const router = useRouter()
const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

import MemberStatusDialog from '../../components/members/MemberStatusDialog.vue'
import MemberExtendDialog from '../../components/members/MemberExtendDialog.vue'

const showStatusDialog = ref(false)
const showExtendDialog = ref(false)
const saving = ref(false)

const documentUrls = ref({
  foto_wajah: null,
  dokumen_identitas: null,
  pendukung: []
})

onMounted(async () => {
  await loadMember()
  await loadExtensions()
})

const loadMember = async () => {
  try {
    const data = await fetchDetail(route.params.id)
    if (data) {
      loadDocuments()
    }
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat detail member', life: 3000 })
    router.push('/mdm/members')
  }
}

const loadExtensions = async () => {
  try {
    await fetchExtensions(route.params.id)
  } catch (err) {
    console.error('Failed to load extension logs', err)
  }
}

const loadDocuments = async () => {
  if (member.value.has_foto_wajah) {
    documentUrls.value.foto_wajah = await fetchSecureDoc('foto_wajah')
  }
  if (member.value.has_dokumen_identitas) {
    documentUrls.value.dokumen_identitas = await fetchSecureDoc('dokumen_identitas')
  }
}

const fetchSecureDoc = async (type) => {
  try {
    const response = await axios.get(`/v1/members/${route.params.id}/documents/${type}`, {
      responseType: 'blob'
    })
    return URL.createObjectURL(response.data)
  } catch (err) {
    console.error(`Failed to load document ${type}`, err)
    return null
  }
}

const canActivate = computed(() => {
  return ['superadmin', 'admin_branch'].includes(authStore.user?.role) && member.value?.status_member === 'Pending'
})

const canManage = computed(() => {
  return ['superadmin', 'admin_branch', 'cs'].includes(authStore.user?.role)
})

const onActivate = () => {
  confirm.require({
    message: 'Apakah Anda yakin ingin mengaktifkan member ini? ID Member akan digenerate otomatis dan berlaku selama 1 tahun.',
    header: 'Konfirmasi Aktivasi Member',
    icon: 'pi pi-check-circle',
    acceptClass: 'p-button-success',
    accept: async () => {
      try {
        await activate(route.params.id)
        toast.add({ severity: 'success', summary: 'Sukses', detail: 'Member berhasil diaktifkan', life: 3000 })
        await loadMember()
      } catch (err) {
        toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal mengaktifkan member', life: 3000 })
      }
    }
  })
}

const handleStatusUpdate = async (newStatus) => {
  saving.value = true
  try {
    await updateStatus(route.params.id, newStatus)
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Status member berhasil diubah', life: 3000 })
    showStatusDialog.value = false
    await loadMember()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal mengubah status member', life: 3000 })
  } finally {
    saving.value = false
  }
}

const handleExtend = async (data) => {
  saving.value = true
  try {
    await extendMember(route.params.id, data)
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Member berhasil diperpanjang', life: 3000 })
    showExtendDialog.value = false
    await loadMember()
    await loadExtensions()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal memperpanjang member', life: 3000 })
  } finally {
    saving.value = false
  }
}

const getStatusSeverity = (status) => {
  if (status === 'Aktif') return 'success'
  if (status === 'Pending') return 'warning'
  if (status === 'Ditolak') return 'danger'
  return 'info'
}
</script>

<template>
  <div class="view-container" v-if="member">
    <ConfirmDialog />
    
    <div class="header-section">
      <div class="header-content">
        <div class="flex-align-center gap-3">
          <Button icon="pi pi-arrow-left" class="p-button-text p-button-secondary" @click="router.push('/mdm/members')" />
          <h1>Profil Member</h1>
        </div>
      </div>
      <div class="header-actions flex gap-2">
        <Button 
          v-if="canManage"
          label="Ubah Status" 
          icon="pi pi-shield" 
          class="p-button-outlined p-button-warning btn-pill" 
          @click="showStatusDialog = true" 
        />
        <Button 
          v-if="canManage && member.status_member === 'Aktif'"
          label="Perpanjang Member" 
          icon="pi pi-calendar-plus" 
          class="p-button-outlined p-button-info btn-pill" 
          @click="showExtendDialog = true" 
        />
        <Button 
          label="Edit Data" 
          icon="pi pi-pencil" 
          class="p-button-outlined p-button-secondary btn-pill" 
          @click="router.push(`/mdm/members/${member.id}/edit`)" 
        />
      </div>
    </div>

    <!-- Approval Banner for Pending Members -->
    <div v-if="member.status_member === 'Pending'" class="approval-card">
      <div class="approval-info">
        <div class="info-icon">
          <i class="pi pi-exclamation-circle"></i>
        </div>
        <div class="info-text">
          <h3>Menunggu Persetujuan</h3>
          <p>Member ini telah menyelesaikan survey lapangan. Tinjau data di bawah ini sebelum melakukan aktivasi.</p>
        </div>
      </div>
      <div class="approval-actions" v-if="canActivate">
        <Button 
          label="Tolak" 
          icon="pi pi-times" 
          class="p-button-text p-button-danger mr-2" 
          @click="handleStatusUpdate('Ditolak')"
        />
        <Button 
          label="Setujui & Aktifkan Member" 
          icon="pi pi-check" 
          class="p-button-success p-shadow" 
          @click="onActivate" 
        />
      </div>
      <div v-else class="approval-actions">
        <Tag severity="info" value="Hanya Admin yang dapat menyetujui" />
      </div>
    </div>

    <div class="detail-layout">
      <!-- Sidebar Info -->
      <div class="detail-sidebar">
        <div class="profile-card">
          <div class="profile-photo-wrapper">
            <img v-if="documentUrls.foto_wajah" :src="documentUrls.foto_wajah" class="profile-photo" />
            <div v-else class="profile-photo-placeholder">
              <i class="pi pi-user text-6xl"></i>
            </div>
          </div>
          <h2 class="profile-name">{{ member.customer?.nama }}</h2>
          <p class="member-id">{{ member.id_member || 'ID BELUM TERBIT' }}</p>
          <Tag :severity="getStatusSeverity(member.status_member)" :value="member.status_member" class="status-badge" />
          
          <Divider class="my-4" />
          
          <div class="quick-info">
            <div class="info-item">
              <span class="label">Tanggal Daftar</span>
              <span class="value">{{ member.created_at }}</span>
            </div>
            <div class="info-item">
              <span class="label">Mulai Aktif</span>
              <span class="value">{{ member.tanggal_aktif || '-' }}</span>
            </div>
            <div class="info-item">
              <span class="label">Masa Berlaku</span>
              <span class="value">{{ member.tanggal_exp || '-' }}</span>
            </div>
            <div class="info-item">
              <span class="label">Surveyor</span>
              <span class="value">{{ member.surveyor?.name || 'Manual' }}</span>
            </div>
          </div>
        </div>

        <div class="document-card">
          <div class="doc-header">
            <h3 class="section-title-small">Identitas</h3>
            <Tag :value="member.identitas_type" severity="info" />
          </div>
          <div class="document-preview">
            <img v-if="documentUrls.dokumen_identitas" :src="documentUrls.dokumen_identitas" class="preview-img" @click="window.open(documentUrls.dokumen_identitas)" />
            <div v-else class="preview-empty">
              <i class="pi pi-file"></i>
              <p>Belum diunggah</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="detail-main">
        <div class="main-card flex flex-col gap-6">
          <div class="section">
            <div class="section-header">
              <i class="pi pi-briefcase text-cyan-600"></i>
              <h3>Informasi Pekerjaan</h3>
            </div>
            <div class="info-grid">
              <div class="info-group">
                <label>Nama Kantor/Instansi</label>
                <p>{{ member.nama_kantor || '-' }}</p>
              </div>
              <div class="info-group">
                <label>Status Kepegawaian</label>
                <p>{{ member.pekerjaan_status || '-' }}</p>
              </div>
              <div class="info-group">
                <label>Jabatan</label>
                <p>{{ member.jabatan || '-' }}</p>
              </div>
              <div class="info-group">
                <label>Nama Atasan</label>
                <p>{{ member.nama_atasan || '-' }}</p>
              </div>
              <div class="info-group">
                <label>Kontak Kantor</label>
                <p>{{ member.kontak_kantor || '-' }}</p>
              </div>
              <div class="info-group full">
                <label>Alamat Kantor</label>
                <p>{{ member.alamat_kantor || '-' }}</p>
              </div>
            </div>
          </div>

          <Divider />

          <div class="section">
            <div class="section-header">
              <i class="pi pi-users text-cyan-600"></i>
              <h3>Keluarga & Sosial</h3>
            </div>
            <div class="info-grid">
              <div class="info-group">
                <label>Status Pernikahan</label>
                <p>{{ member.status_pernikahan || '-' }}</p>
              </div>
              <div class="info-group">
                <label>Kepemilikan Rumah</label>
                <p>{{ member.rumah_status || '-' }}</p>
              </div>
              <div class="info-group">
                <label>Lokasi Rumah</label>
                <p>{{ member.rumah_lokasi || '-' }}</p>
              </div>
            </div>
            
            <div class="sub-grid mt-4">
              <div class="sub-card">
                <div class="sub-card-header">Penanggung Jawab</div>
                <div class="info-group mt-3">
                  <label>Nama Lengkap</label>
                  <p class="font-bold">{{ member.pj_nama || '-' }}</p>
                </div>
                <div class="info-group">
                  <label>Nomor Telepon</label>
                  <p>{{ member.pj_kontak || '-' }}</p>
                </div>
                <div class="info-group">
                  <label>Hubungan</label>
                  <p>{{ member.pj_hubungan || '-' }}</p>
                </div>
              </div>

              <div class="sub-card">
                <div class="sub-card-header">Data Orang Tua</div>
                <div class="info-group mt-3">
                  <label>Nama Lengkap</label>
                  <p class="font-bold">{{ member.ortu_nama || '-' }}</p>
                </div>
                <div class="info-group">
                  <label>Nomor Telepon</label>
                  <p>{{ member.ortu_kontak || '-' }}</p>
                </div>
                <div class="info-group">
                  <label>Alamat Tinggal</label>
                  <p>{{ member.ortu_alamat || '-' }}</p>
                </div>
              </div>
            </div>
          </div>

          <Divider />

          <div class="section">
            <div class="section-header">
              <i class="pi pi-comment text-cyan-600"></i>
              <h3>Catatan Survey</h3>
            </div>
            <div class="survey-box">
              {{ member.catatan || 'Tidak ada catatan survey.' }}
            </div>
          </div>

          <Divider v-if="extensions.length > 0" />

          <!-- History Perpanjangan Member -->
          <div v-if="extensions.length > 0" class="section">
            <div class="section-header">
              <i class="pi pi-history text-cyan-600"></i>
              <h3>History Perpanjangan Member</h3>
            </div>
            <div class="border border-[var(--surface-border)] rounded-lg overflow-hidden">
              <table class="w-full text-left border-collapse text-sm">
                <thead>
                  <tr class="bg-[var(--card-bg)] border-b border-[var(--surface-border)]">
                    <th class="p-3 font-semibold text-[var(--text-secondary)]">Tanggal Perpanjang</th>
                    <th class="p-3 font-semibold text-[var(--text-secondary)]">Exp Lama</th>
                    <th class="p-3 font-semibold text-[var(--text-secondary)]">Exp Baru</th>
                    <th class="p-3 font-semibold text-[var(--text-secondary)]">Catatan</th>
                    <th class="p-3 font-semibold text-[var(--text-secondary)]">Oleh</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="ext in extensions" :key="ext.id" class="border-b border-[var(--surface-border)] hover:bg-[var(--card-bg-hover)]">
                    <td class="p-3 font-mono text-xs">{{ ext.created_at }}</td>
                    <td class="p-3 font-mono text-xs">{{ ext.old_exp_date || '-' }}</td>
                    <td class="p-3 font-mono text-xs font-semibold text-[var(--positive)]">{{ ext.new_exp_date }}</td>
                    <td class="p-3 text-[var(--text-secondary)]">{{ ext.catatan }}</td>
                    <td class="p-3 text-xs">{{ ext.creator?.name || 'Staff' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Dialogs -->
    <MemberStatusDialog
      v-model:visible="showStatusDialog"
      :currentStatus="member.status_member"
      :loading="saving"
      @update-status="handleStatusUpdate"
    />

    <MemberExtendDialog
      v-model:visible="showExtendDialog"
      :currentExpDate="member.tanggal_exp"
      :loading="saving"
      @extend="handleExtend"
    />
  </div>
</template>

<style scoped>
.view-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 25px;
  display: flex;
  flex-direction: column;
  gap: 25px;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.flex-align-center {
  display: flex;
  align-items: center;
}

.gap-3 {
  gap: 15px;
}

.header-content h1 {
  font-size: 2rem;
  font-weight: 800;
  color: #1e293b;
  margin: 0;
}

/* Approval Card */
.approval-card {
  background: linear-gradient(to right, #fffbeb, #ffffff);
  border: 1px solid #fde68a;
  border-radius: 16px;
  padding: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  margin-bottom: 5px;
}

.approval-info {
  display: flex;
  align-items: center;
  gap: 20px;
}

.info-icon {
  width: 54px;
  height: 54px;
  background-color: #fef3c7;
  color: #b45309;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
}

.info-text h3 {
  margin: 0;
  color: #92400e;
  font-weight: 800;
  font-size: 1.2rem;
}

.info-text p {
  margin: 5px 0 0;
  color: #b45309;
  font-size: 1rem;
}

/* Detail Layout */
.detail-layout {
  display: flex;
  gap: 30px;
  align-items: flex-start;
}

.detail-sidebar {
  width: 350px;
  display: flex;
  flex-direction: column;
  gap: 25px;
  position: sticky;
  top: 100px;
}

.profile-card, .document-card, .main-card {
  background-color: #ffffff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  padding: 30px;
}

.profile-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.profile-photo-wrapper {
  width: 150px;
  height: 150px;
  border-radius: 50%;
  overflow: hidden;
  border: 5px solid #f1f5f9;
  margin-bottom: 20px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.profile-photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.profile-photo-placeholder {
  width: 100%;
  height: 100%;
  background-color: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #cbd5e1;
}

.profile-name {
  font-size: 1.5rem;
  font-weight: 800;
  margin: 0;
  color: #1e293b;
}

.member-id {
  font-family: monospace;
  color: #64748b;
  font-size: 1.1rem;
  margin: 5px 0 15px;
  letter-spacing: 1px;
}

.status-badge {
  padding: 8px 16px;
  font-weight: 800;
  font-size: 0.9rem;
  text-transform: uppercase;
}

.quick-info {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  font-size: 0.95rem;
  padding-bottom: 8px;
  border-bottom: 1px dashed #e2e8f0;
}

.info-item .label {
  color: #64748b;
}

.info-item .value {
  font-weight: 700;
  color: #1e293b;
}

.doc-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.document-preview {
  width: 100%;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #f1f5f9;
}

.preview-img {
  width: 100%;
  cursor: pointer;
  transition: transform 0.2s;
}

.preview-img:hover {
  transform: scale(1.02);
}

.preview-empty {
  padding: 40px;
  background-color: #f8fafc;
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #94a3b8;
}

.preview-empty i {
  font-size: 3rem;
  margin-bottom: 10px;
}

/* Main Content */
.detail-main {
  flex: 1;
}

.section {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.section-header i {
  font-size: 1.4rem;
}

.section-header h3 {
  margin: 0;
  font-size: 1.2rem;
  font-weight: 800;
  color: #1e293b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
}

.info-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.info-group.full {
  grid-column: span 2;
}

.info-group label {
  font-size: 0.8rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
}

.info-group p {
  margin: 0;
  font-size: 1.05rem;
  color: #1e293b;
  font-weight: 500;
}

.sub-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.sub-card {
  background-color: #f8fafc;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #f1f5f9;
}

.sub-card-header {
  font-weight: 800;
  color: #0891b2;
  font-size: 0.9rem;
  text-transform: uppercase;
  border-bottom: 2px solid #e0f2fe;
  padding-bottom: 8px;
}

.survey-box {
  background-color: #ecfeff;
  border-left: 6px solid #06b6d4;
  padding: 25px;
  border-radius: 12px;
  font-style: italic;
  color: #164e63;
  line-height: 1.7;
  font-size: 1.1rem;
}

.p-shadow {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

@media (max-width: 1200px) {
  .detail-layout {
    flex-direction: column;
  }
  
  .detail-sidebar {
    width: 100%;
    position: static;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .info-group.full {
    grid-column: span 1;
  }

  .sub-grid {
    grid-template-columns: 1fr;
  }
}
</style>
