<script setup>
import { ref } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  currentStatus: {
    type: String,
    default: 'Pending'
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:visible', 'update-status'])

const status = ref('Pending')

const statusOptions = [
  { label: 'Pending', value: 'Pending' },
  { label: 'Aktif', value: 'Aktif' },
  { label: 'Tidak Aktif', value: 'Tidak Aktif' },
  { label: 'Ditolak', value: 'Ditolak' }
]

const onShow = () => {
  status.value = props.currentStatus
}

const onHide = () => {
  emit('update:visible', false)
}

const onSubmit = () => {
  emit('update-status', status.value)
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="onHide"
    @show="onShow"
    header="Ubah Status Member"
    :modal="true"
    :draggable="false"
    class="w-full max-w-sm"
  >
    <div class="flex flex-col gap-4 py-2">
      <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold text-[var(--text-secondary)] uppercase">Pilih Status Baru</label>
        <Dropdown
          v-model="status"
          :options="statusOptions"
          optionLabel="label"
          optionValue="value"
          class="w-full"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2 pt-2 border-t border-[var(--surface-border)]">
        <Button
          label="Batal"
          class="app-dialog-button app-dialog-button-secondary"
          @click="onHide"
          :disabled="loading"
        />
        <Button
          label="Simpan Perubahan"
          class="app-dialog-button app-dialog-button-primary"
          @click="onSubmit"
          :disabled="loading"
          :loading="loading"
        />
      </div>
    </template>
  </Dialog>
</template>
