<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: {
    type: String,
    required: true
  },
  text: {
    type: String,
    required: false
  }
});

const statusMap = {
  'follow_up':    { class: 'neutral', label: 'Follow Up' },
  'confirm':      { class: 'info', label: 'Confirm' },
  'waiting_list': { class: 'warning', label: 'Waiting List' },
  'rental_unit':  { class: 'success', label: 'Disewa' },
  'selesai':      { class: 'success', label: 'Selesai' },
  'batal':        { class: 'error', label: 'Batal' },
  'Rent to Rent': { class: 'info', label: 'Rent To Rent' },
  'Member':       { class: 'success', label: 'Member' },
  'rental_lain':  { class: 'info', label: props.text  },
  'pemilik_rental': { class: 'success', label: props.text },
  'generated':    { class: 'success', label: props.text },
  'not_generated':{ class: 'warning', label: props.text },
  'lunas':{ class: 'success', label: "Lunas" },
};

const config = computed(() => {
  return statusMap[props.status] || { class: 'neutral', label: props.status };
});
</script>

<template>
  <span class="status-badge" :class="config.class">
    <span class="font-normal">{{ config.label }}</span>
  </span>
</template>

<style scoped>

</style>

