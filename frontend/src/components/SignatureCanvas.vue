<script setup>
/**
 * Deprecated alias — gunakan components/physical-checks/SignaturePad.vue.
 * Dipertahankan agar import lama (UserProfileView) tetap berfungsi.
 */
import SignaturePad from './physical-checks/SignaturePad.vue'

defineOptions({ name: 'SignatureCanvas', inheritAttrs: false })
</script>

<template>
  <SignaturePad label="Tanda Tangan" v-bind="$attrs" />
</template>
