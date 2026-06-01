<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { Capacitor } from '@capacitor/core'
import { App } from '@capacitor/app'

const router = useRouter()
const toast = useToast()
let lastBack = 0

onMounted(() => {
  if (!Capacitor.isNativePlatform()) return
  App.addListener('backButton', ({ canGoBack }) => {
    const path = router.currentRoute.value.path
    if (path === '/' || path === '/login') {
      if (Date.now() - lastBack < 2000) {
        App.exitApp()
      } else {
        lastBack = Date.now()
        toast.add({ severity: 'contrast', summary: 'Tekan sekali lagi untuk keluar', life: 2000 })
      }
    } else if (canGoBack) {
      router.back()
    } else {
      App.exitApp()
    }
  })
})
</script>

<template>
  <router-view />
</template>

<style>
/* Global styles if any */
</style>
