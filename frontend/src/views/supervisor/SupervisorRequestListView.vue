<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { format } from 'date-fns'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { getSupervisorRequests } from '../../api/supervisorRequest'
import { useBooking } from '../../composables/useBooking'

const router = useRouter()
const {
  loading: actionLoading,
  approveVoidPayment,
  rejectVoidPayment,
  approveReturnToRentalUnit,
  rejectReturnToRentalUnit,
} = useBooking()

const requests = ref([])
const loading = ref(false)
const activeTab = ref('all')
const activeStatus = ref('pending')
const selectedRequest = ref(null)
const showRejectDialog = ref(false)
const rejectionNote = ref('')

const requestTabs = [
  { label: 'Semua', value: 'all' },
  { label: 'Void Pembayaran', value: 'void_payment' },
  { label: 'Kembali Rental Unit', value: 'return_rental_unit' },
]

const statusTabs = [
  { label: 'Menunggu ACC', value: 'pending' },
  { label: 'Sudah ACC', value: 'approved' },
  { label: 'Semua Status', value: 'all' },
]

const filteredRequests = computed(() => {
  return requests.value.filter(request => {
    const matchesType = activeTab.value === 'all' || request.type === activeTab.value
    const matchesStatus = activeStatus.value === 'all' || request.status === activeStatus.value
    return matchesType && matchesStatus
  })
})

const fetchRequests = async () => {
  loading.value = true
  try {
    const response = await getSupervisorRequests()
    requests.value = response.data.data || []
  } finally {
    loading.value = false
  }
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0)
}

const formatDateTime = (value) => {
  if (!value) return '-'
  return format(new Date(value), 'dd MMM yyyy HH:mm')
}

const tagSeverity = (type) => type === 'void_payment' ? 'warn' : 'info'

const statusSeverity = (status) => status === 'approved' ? 'success' : 'warn'

const approveRequest = async (request) => {
  if (request.type === 'void_payment') {
    await approveVoidPayment(request.payment.id)
  } else {
    await approveReturnToRentalUnit(request.booking.id)
  }

  await fetchRequests()
}

const openRejectDialog = (request) => {
  selectedRequest.value = request
  rejectionNote.value = ''
  showRejectDialog.value = true
}

const submitReject = async () => {
  if (!selectedRequest.value) return

  if (selectedRequest.value.type === 'void_payment') {
    await rejectVoidPayment(selectedRequest.value.payment.id, {
      void_rejection_note: rejectionNote.value,
    })
  } else {
    await rejectReturnToRentalUnit(selectedRequest.value.booking.id, {
      rejection_note: rejectionNote.value,
    })
  }

  showRejectDialog.value = false
  selectedRequest.value = null
  await fetchRequests()
}

onMounted(fetchRequests)
</script>

<template>
  <div class="page-container">
    <div class="detail-page-header">
      <div class="header-left">
        <h1 class="text-h1">Request Supervisor</h1>
        <p class="text-secondary text-xs">Approval untuk void pembayaran dan pengembalian booking selesai ke Rental Unit.</p>
      </div>
      <button class="btn-pill btn-secondary" :disabled="loading" @click="fetchRequests">
        <i class="pi pi-refresh"></i>
        Refresh
      </button>
    </div>

    <div class="tab-toggle-container">
      <div class="pill-toggle">
        <button
          v-for="tab in requestTabs"
          :key="tab.value"
          class="toggle-item"
          :class="{ active: activeTab === tab.value }"
          @click="activeTab = tab.value"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <div class="tab-toggle-container">
      <div class="pill-toggle">
        <button
          v-for="tab in statusTabs"
          :key="tab.value"
          class="toggle-item"
          :class="{ active: activeStatus === tab.value }"
          @click="activeStatus = tab.value"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <ProgressBar v-if="loading" mode="indeterminate" style="height: 4px" class="mb-4" />

    <DataTable
      :value="filteredRequests"
      dataKey="id"
      :loading="loading"
      responsiveLayout="scroll"
      class="drent-datatable"
      emptyMessage="Tidak ada request."
    >
      <Column header="Request" style="min-width: 13rem">
        <template #body="{ data }">
          <div class="tag-stack">
            <Tag :value="data.type_label" :severity="tagSeverity(data.type)" />
            <Tag :value="data.status_label" :severity="statusSeverity(data.status)" />
          </div>
          <div class="text-xs text-secondary mt-2">{{ formatDateTime(data.requested_at) }}</div>
          <div class="text-xs text-tertiary">oleh {{ data.requester?.name || '-' }}</div>
          <div v-if="data.status === 'approved'" class="text-xs text-positive mt-1">
            ACC {{ formatDateTime(data.approved_at) }} oleh {{ data.approver?.name || '-' }}
          </div>
        </template>
      </Column>
      <Column header="Booking" style="min-width: 12rem">
        <template #body="{ data }">
          <button class="link-button" @click="router.push(`/bookings/${data.booking.id}`)">{{ data.booking.kode_booking }}</button>
          <div class="text-xs text-secondary mt-1">{{ data.booking.customer_name || '-' }}</div>
        </template>
      </Column>
      <Column header="Detail" style="min-width: 16rem">
        <template #body="{ data }">
          <div v-if="data.type === 'void_payment'" class="detail-stack">
            <strong>{{ formatCurrency(data.payment.amount) }}</strong>
            <span>{{ data.payment.payment_type }}</span>
            <span>{{ data.payment.payment_account?.nama_bank || '-' }} {{ data.payment.payment_account?.nomor_rekening || '' }}</span>
          </div>
          <span v-else class="text-secondary">Ubah status booking dari selesai ke rental_unit.</span>
        </template>
      </Column>
      <Column header="Alasan" style="min-width: 18rem">
        <template #body="{ data }">
          <span class="reason-text">{{ data.reason || '-' }}</span>
        </template>
      </Column>
      <Column header="Aksi" style="min-width: 13rem">
        <template #body="{ data }">
          <div v-if="data.status === 'pending'" class="table-actions">
            <button class="btn-pill btn-primary btn-pill-compact" :disabled="actionLoading" @click="approveRequest(data)">
              <i class="pi pi-check"></i>
              Setujui
            </button>
            <button class="btn-pill btn-secondary btn-pill-compact" :disabled="actionLoading" @click="openRejectDialog(data)">
              <i class="pi pi-times"></i>
              Tolak
            </button>
          </div>
          <span v-else class="text-xs text-positive font-semibold">Sudah disetujui</span>
        </template>
      </Column>
    </DataTable>

    <Dialog v-model:visible="showRejectDialog" header="Tolak Request" modal :style="{ width: '450px' }">
      <div class="dialog-stack">
        <div class="app-muted-panel">
          <span class="text-xs text-tertiary">Request</span>
          <strong>{{ selectedRequest?.type_label }}</strong>
          <span>{{ selectedRequest?.booking?.kode_booking }}</span>
        </div>
        <div class="form-fieldset">
          <label>Catatan Penolakan</label>
          <Textarea v-model="rejectionNote" rows="4" class="w-full" />
        </div>
      </div>
      <template #footer>
        <button class="app-dialog-button app-dialog-button-secondary" @click="showRejectDialog = false">
          <i class="pi pi-times"></i>
          Batal
        </button>
        <button class="app-dialog-button app-dialog-button-primary" :disabled="actionLoading" @click="submitReject">
          <i class="pi pi-check"></i>
          Simpan
        </button>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page-container {
  padding: var(--space-2xl);
}

.detail-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-lg);
  margin-bottom: var(--space-2xl);
}

.tab-toggle-container {
  margin-bottom: var(--space-xl);
}

.pill-toggle {
  display: inline-flex;
  background: var(--card-bg);
  padding: 4px;
  border-radius: var(--radius-full);
  gap: 4px;
}

.toggle-item {
  padding: 6px 16px;
  border-radius: var(--radius-full);
  border: none;
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
}

.toggle-item.active {
  background: var(--text-primary);
  color: #fff;
}

.drent-datatable {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  overflow: hidden;
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.link-button {
  border: none;
  background: transparent;
  color: var(--text-primary);
  cursor: pointer;
  font-weight: 700;
  padding: 0;
}

.table-actions {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  flex-wrap: wrap;
}

.tag-stack {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.detail-stack,
.dialog-stack {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.reason-text {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.4;
}

.app-muted-panel,
.form-fieldset {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--card-bg);
  padding: var(--space-md);
}

.form-fieldset {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-fieldset label {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 700;
}

@media (max-width: 768px) {
  .page-container {
    padding: var(--space-lg);
  }

  .detail-page-header {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
