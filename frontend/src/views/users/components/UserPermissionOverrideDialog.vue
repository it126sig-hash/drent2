<script setup>
import { ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import { getUserPermissions, updateUserPermissions, getRolePermissions } from '../../../api/rolePermission'

const props = defineProps({
  visible: Boolean,
  user: Object
})

const emit = defineEmits(['update:visible', 'saved'])

const toast = useToast()

const loading = ref(false)
const saving = ref(false)
const userEffectivePermissions = ref([])
const roleBasePermissions = ref([])
const overrides = ref({}) // key: value ('grant', 'revoke', null)

const permissionDefinitions = [
  {
    group: 'Dashboard',
    items: [
      { key: 'dashboard.view', label: 'Lihat Dashboard' }
    ]
  },
  {
    group: 'Booking & Transaksi',
    items: [
      { key: 'booking.view', label: 'Lihat Booking' },
      { key: 'booking.create', label: 'Buat Booking' },
      { key: 'booking.handle', label: 'Handle Booking (Assign Unit/Driver)' },
      { key: 'booking.supervisor_request', label: 'Request Supervisor' },
      { key: 'physical_check.view', label: 'Lihat & Input Cek Fisik' }
    ]
  },
  {
    group: 'Keuangan',
    items: [
      { key: 'finance.receivable', label: 'Kelola Piutang' },
      { key: 'finance.rent_to_rent', label: 'Kelola Rent to Rent' },
      { key: 'finance.operational_cost', label: 'Kelola Biaya Operasional' },
      { key: 'finance.account_mutation', label: 'Mutasi Rekening' },
      { key: 'finance.monthly_report', label: 'Laporan Bulanan' },
      { key: 'finance.transaction', label: 'Laporan Transaksi' }
    ]
  },
  {
    group: 'Kendaraan & Pelanggan',
    items: [
      { key: 'vehicle.rental_owner', label: 'Kelola Pemilik Rental' },
      { key: 'vehicle.unit', label: 'Kelola Unit Kendaraan' },
      { key: 'vehicle.driver', label: 'Kelola Driver' },
      { key: 'driver.operational', label: 'Akses Operasional Driver (Mobile)' },
      { key: 'customer.view', label: 'Kelola Pelanggan' },
      { key: 'member.view', label: 'Kelola Member' }
    ]
  },
  {
    group: 'Data Master',
    items: [
      { key: 'master.user', label: 'Manajemen User' },
      { key: 'master.payment_account', label: 'Akun Pembayaran' },
      { key: 'master.city', label: 'List Kota' },
      { key: 'master.cost_type', label: 'Tipe Biaya' },
      { key: 'master.pricing_package', label: 'Paket Harga' },
      { key: 'master.role_management', label: 'Manajemen Role Permission' }
    ]
  }
]

const loadData = async () => {
  if (!props.user) return
  loading.value = true
  try {
    const [userRes, roleRes] = await Promise.all([
      getUserPermissions(props.user.id),
      getRolePermissions()
    ])
    
    roleBasePermissions.value = roleRes.data.data[props.user.role] || []
    const userOverrides = userRes.data.data.overrides || []
    
    // reset overrides
    const newOverrides = {}
    userOverrides.forEach(o => {
      newOverrides[o.key] = o.value
    })
    overrides.value = newOverrides
    
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat permission', life: 3000 })
  } finally {
    loading.value = false
  }
}

watch(() => props.visible, (val) => {
  if (val) {
    loadData()
  } else {
    overrides.value = {}
  }
})

const getStatus = (key) => {
  const override = overrides.value[key]
  if (override === 'grant') return 'granted'
  if (override === 'revoke') return 'revoked'
  if (roleBasePermissions.value.includes(key)) return 'default-granted'
  return 'default-revoked'
}

const togglePermission = (key) => {
  const currentStatus = getStatus(key)
  const isDefaultGranted = roleBasePermissions.value.includes(key)
  
  if (isDefaultGranted) {
    // If default is granted: Default -> Revoke -> Default
    if (currentStatus === 'default-granted') {
      overrides.value[key] = 'revoke'
    } else {
      overrides.value[key] = null
    }
  } else {
    // If default is revoked: Default -> Grant -> Default
    if (currentStatus === 'default-revoked') {
      overrides.value[key] = 'grant'
    } else {
      overrides.value[key] = null
    }
  }
}

const save = async () => {
  saving.value = true
  try {
    const payload = Object.keys(overrides.value).map(key => ({
      key,
      value: overrides.value[key]
    }))
    
    await updateUserPermissions(props.user.id, payload)
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Permission pengguna diperbarui', life: 3000 })
    emit('saved')
    close()
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Error', detail: error.response?.data?.message || 'Gagal menyimpan', life: 3000 })
  } finally {
    saving.value = false
  }
}

const close = () => {
  emit('update:visible', false)
}
</script>

<template>
  <Dialog 
    :visible="visible" 
    @update:visible="val => emit('update:visible', val)" 
    modal 
    header="Override Permission Pengguna" 
    :style="{ width: '50vw', minWidth: '600px' }"
  >
    <div v-if="loading" class="flex justify-content-center p-4">
      <i class="pi pi-spin pi-spinner text-3xl text-primary"></i>
    </div>
    
    <div v-else-if="user">
      <div class="mb-4 bg-blue-50 p-3 border-round border-1 border-blue-200">
        <div class="font-semibold text-blue-900">{{ user.name }}</div>
        <div class="text-sm text-blue-700">Role: {{ user.role }}</div>
        <div class="text-xs text-blue-600 mt-2">
          Klik pada item untuk memberikan (override) atau mencabut hak akses khusus untuk pengguna ini.
        </div>
      </div>
      
      <div v-if="user.role === 'superadmin'" class="p-3 bg-yellow-50 text-yellow-800 border-round">
        Superadmin memiliki semua hak akses secara sistem. Override tidak berlaku.
      </div>
      
      <div v-else>
        <div class="flex gap-4 mb-4 text-xs">
          <div class="flex align-items-center gap-2"><div class="w-1rem h-1rem border-circle bg-green-100 border-1 border-green-500"></div> Default: Ya</div>
          <div class="flex align-items-center gap-2"><div class="w-1rem h-1rem border-circle surface-100 border-1 surface-border"></div> Default: Tidak</div>
          <div class="flex align-items-center gap-2"><div class="w-1rem h-1rem border-circle bg-green-500"></div> Override: Ya</div>
          <div class="flex align-items-center gap-2"><div class="w-1rem h-1rem border-circle bg-red-500"></div> Override: Tidak</div>
        </div>

        <div v-for="group in permissionDefinitions" :key="group.group" class="mb-4">
          <div class="font-semibold text-sm mb-2 text-slate-700 border-b border-slate-200 pb-1">{{ group.group }}</div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div v-for="item in group.items" :key="item.key" class="col-span-1">
              <div 
                class="perm-item" 
                :class="getStatus(item.key)"
                @click="togglePermission(item.key)"
              >
                <div class="indicator">
                  <i v-if="getStatus(item.key).includes('granted')" class="pi pi-check text-xs"></i>
                  <i v-if="getStatus(item.key) === 'revoked'" class="pi pi-times text-xs"></i>
                </div>
                <span class="text-sm">{{ item.label }}</span>
                <span v-if="overrides[item.key] === 'grant'" class="ml-auto text-xs font-bold text-green-700 bg-green-100 px-1 border-round">Grant</span>
                <span v-if="overrides[item.key] === 'revoke'" class="ml-auto text-xs font-bold text-red-700 bg-red-100 px-1 border-round">Revoke</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <template #footer>
      <Button label="Batal" icon="pi pi-times" text @click="close" />
      <Button label="Simpan" icon="pi pi-check" class="btn-primary" @click="save" :loading="saving" :disabled="user?.role === 'superadmin'" />
    </template>
  </Dialog>
</template>

<style scoped>
.perm-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  border-radius: var(--radius-sm);
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
  user-select: none;
}

.perm-item:hover {
  background-color: var(--surface-hover);
}

.indicator {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--surface-border);
}

/* Default Granted */
.perm-item.default-granted .indicator {
  background-color: var(--green-50);
  border-color: var(--green-500);
  color: var(--green-600);
}

/* Default Revoked */
.perm-item.default-revoked .indicator {
  background-color: var(--surface-ground);
  color: transparent;
}

/* Override Grant */
.perm-item.granted {
  background-color: var(--green-50);
  border-color: var(--green-200);
}
.perm-item.granted .indicator {
  background-color: var(--green-500);
  border-color: var(--green-600);
  color: white;
}

/* Override Revoke */
.perm-item.revoked {
  background-color: var(--red-50);
  border-color: var(--red-200);
  color: var(--red-900);
}
.perm-item.revoked .indicator {
  background-color: var(--red-500);
  border-color: var(--red-600);
  color: white;
}
.perm-item.revoked span {
  text-decoration: line-through;
  opacity: 0.7;
}
</style>
