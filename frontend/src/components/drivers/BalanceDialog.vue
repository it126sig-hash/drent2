<script setup>
import { ref, watch } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'

const props = defineProps({
  visible: Boolean,
  driver: {
    type: Object,
    default: null
  },
  loading: Boolean
})

const emit = defineEmits(['update:visible', 'saved'])

const newBalance = ref(0)

watch(() => props.driver, (newVal) => {
  if (newVal) {
    newBalance.value = newVal.saldo
  }
})

const handleSave = () => {
  emit('saved', newBalance.value)
}

const handleClose = () => {
  emit('update:visible', false)
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value)
}
</script>

<template>
  <Dialog 
    :visible="visible" 
    @update:visible="handleClose"
    header="Update Saldo Operasional Driver" 
    :modal="true" 
    class="custom-dialog"
    :style="{ width: '450px' }"
    :breakpoints="{ '641px': '90vw' }"
  >
    <div v-if="driver" class="balance-container">
      <div class="driver-summary mb-4 p-3 bg-slate-50 border border-slate-100 rounded-lg">
        <div class="flex items-center gap-3">
          <div class="avatar-circle">
            {{ driver.nama.charAt(0).toUpperCase() }}
          </div>
          <div>
            <div class="text-sm font-bold text-slate-700">{{ driver.nama }}</div>
            <div class="text-xs text-slate-500">{{ driver.kontak_1 }}</div>
          </div>
        </div>
      </div>

      <div class="current-balance-section mb-4">
        <label class="section-label">Saldo Saat Ini</label>
        <div class="balance-badge bg-cyan-50 text-cyan-700">
          <i class="pi pi-wallet mr-2"></i>
          {{ formatCurrency(driver.saldo) }}
        </div>
      </div>

      <div class="input-section p-fluid">
        <label for="new_balance" class="section-label label-required">Total Saldo Baru</label>
        <InputNumber 
          id="new_balance" 
          v-model="newBalance" 
          mode="currency" 
          currency="IDR" 
          locale="id-ID" 
          :min="0"
          autofocus
          class="large-input"
          placeholder="Masukkan nominal saldo..."
        />
        <div class="help-text mt-2">
          <i class="pi pi-info-circle mr-1"></i>
          Saldo akan diperbarui menjadi nominal di atas.
        </div>
      </div>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <Button label="Batal" icon="pi pi-times" class="p-button-text p-button-secondary" @click="handleClose" />
        <Button 
          label="Simpan Saldo" 
          icon="pi pi-save" 
          class="p-button-tosca" 
          @click="handleSave" 
          :loading="loading" 
        />
      </div>
    </template>
  </Dialog>
</template>

<style scoped>
.balance-container {
  padding: 10px 0;
}

.section-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 8px;
}

.label-required::after {
  content: " *";
  color: #f43f5e;
}

.balance-badge {
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 1.25rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  border: 1px solid #cffafe;
}

.avatar-circle {
  width: 40px;
  height: 40px;
  background-color: #06b6d4;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

.help-text {
  font-size: 0.75rem;
  color: #94a3b8;
  display: flex;
  align-items: center;
}

.large-input :deep(.p-inputnumber-input) {
  font-size: 1.25rem;
  font-weight: 800;
  color: #1e293b;
  padding: 12px;
  border-radius: 10px;
}

.p-button-tosca {
  background-color: #06b6d4 !important;
  border-color: #06b6d4 !important;
  font-weight: 600 !important;
  border-radius: 8px !important;
  padding: 10px 20px !important;
}

.p-button-tosca:hover {
  background-color: #0891b2 !important;
  border-color: #0891b2 !important;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 15px;
  border-top: 1px solid #f1f5f9;
}
</style>
