<script setup>
import { ref, watch } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'

const props = defineProps({
  visible: Boolean,
  user: {
    type: Object,
    default: null
  },
  loading: Boolean
})

const emit = defineEmits(['update:visible', 'saved'])

const formData = ref({
  password: '',
  password_confirmation: ''
})

const error = ref('')

watch(() => props.visible, (newVal) => {
  if (newVal) {
    formData.value = {
      password: '',
      password_confirmation: ''
    }
    error.value = ''
  }
})

const handleSave = () => {
  error.value = ''
  
  if (formData.value.password.length < 8) {
    error.value = 'Password minimal 8 karakter.'
    return
  }
  
  if (formData.value.password !== formData.value.password_confirmation) {
    error.value = 'Konfirmasi password tidak cocok.'
    return
  }

  emit('saved', { ...formData.value })
}

const handleClose = () => {
  emit('update:visible', false)
}
</script>

<template>
  <Dialog 
    :visible="visible" 
    @update:visible="handleClose"
    header="Reset Password" 
    :modal="true" 
    :style="{ width: '400px' }"
  >
    <div class="form-container p-fluid">
      <div v-if="user" class="user-info-banner mb-3">
        Mereset password untuk user: <strong>{{ user.name }}</strong>
      </div>

      <Message v-if="error" severity="error" class="mb-3">{{ error }}</Message>

      <div class="field">
        <label for="new_password" class="label-required">Password Baru</label>
        <InputText id="new_password" v-model="formData.password" type="password" placeholder="Minimal 8 karakter" />
      </div>

      <div class="field">
        <label for="password_confirmation" class="label-required">Konfirmasi Password Baru</label>
        <InputText id="password_confirmation" v-model="formData.password_confirmation" type="password" placeholder="Ulangi password baru" />
      </div>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <Button label="Batal" icon="pi pi-times" class="p-button-text p-button-secondary" @click="handleClose" />
        <Button 
          label="Reset Password" 
          icon="pi pi-check" 
          class="p-button-danger" 
          @click="handleSave" 
          :loading="loading" 
          :disabled="!formData.password || !formData.password_confirmation" 
        />
      </div>
    </template>
  </Dialog>
</template>

<style scoped>
.form-container {
  padding: 10px 0;
}

.user-info-banner {
  padding: 10px;
  background-color: #f8fafc;
  border-radius: 6px;
  font-size: 0.9rem;
  color: #475569;
  border-left: 4px solid #06b6d4;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}

.field label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #334155;
}

.label-required::after {
  content: " *";
  color: #f43f5e;
  margin-left: 4px;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 10px;
}

.mb-3 { margin-bottom: 1rem; }
</style>
