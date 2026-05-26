<script setup>
import { ref } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker' // PrimeVue v4 uses DatePicker

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  currentExpDate: {
    type: String,
    default: null
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:visible', 'extend'])

const expDate = ref(null)
const catatan = ref('')

const onShow = () => {
  // Set default extension to 1 year from current expiry if available, or today
  const baseDate = props.currentExpDate ? new Date(props.currentExpDate) : new Date()
  const defaultDate = new Date(baseDate)
  defaultDate.setFullYear(defaultDate.getFullYear() + 1)
  expDate.value = defaultDate
  catatan.value = ''
}

const onHide = () => {
  emit('update:visible', false)
}

const onSubmit = () => {
  if (!expDate.value || !catatan.value.trim()) return

  // Format date to YYYY-MM-DD local time format
  const year = expDate.value.getFullYear()
  const month = String(expDate.value.getMonth() + 1).padStart(2, '0')
  const day = String(expDate.value.getDate()).padStart(2, '0')
  const formattedDate = `${year}-${month}-${day}`

  emit('extend', {
    new_exp_date: formattedDate,
    catatan: catatan.value
  })
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="onHide"
    @show="onShow"
    header="Perpanjang Masa Aktif Member"
    :modal="true"
    :draggable="false"
    class="w-full max-w-md"
  >
    <div class="flex flex-col gap-4 py-2">
      <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold text-[var(--text-secondary)] uppercase">Tanggal Kedaluwarsa Baru</label>
        <DatePicker
          v-model="expDate"
          dateFormat="yy-mm-dd"
          :minDate="new Date()"
          showIcon
          fluid
          class="w-full"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold text-[var(--text-secondary)] uppercase">Catatan Perpanjangan</label>
        <Textarea
          v-model="catatan"
          rows="3"
          placeholder="Tulis alasan atau detail perpanjangan..."
          fluid
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
          label="Simpan Perpanjangan"
          class="app-dialog-button app-dialog-button-primary"
          @click="onSubmit"
          :disabled="!expDate || !catatan.trim() || loading"
          :loading="loading"
        />
      </div>
    </template>
  </Dialog>
</template>
