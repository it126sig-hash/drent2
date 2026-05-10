<template>
  <div class="p-4">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-slate-800">Daftar Booking</h1>
      <router-link :to="{ name: 'BookingCreate' }">
        <Button label="Buat Booking" icon="pi pi-plus" />
      </router-link>
    </div>
    <Card>
      <template #content>
        <p>Halaman daftar booking (Task 2.5) belum diimplementasikan.</p>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import Card from 'primevue/card';
const router = useRouter();
</script>
