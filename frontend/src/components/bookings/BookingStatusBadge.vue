<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: {
    type: String,
    required: true
  }
});

const colorMap = {
  'follow_up':    { bg: '#FFF3CD', color: '#856404', label: 'Follow Up' },
  'confirm':      { bg: '#D1ECF1', color: '#0C5460', label: 'Confirm' },
  'waiting_list': { bg: '#D6D8DB', color: '#383D41', label: 'Waiting List' },
  'rental_unit':  { bg: '#D4EDDA', color: '#155724', label: 'Rental Unit' },
  'selesai':      { bg: '#CCE5FF', color: '#004085', label: 'Selesai' },
  'batal':        { bg: '#F8D7DA', color: '#721C24', label: 'Batal' },
};

const style = computed(() => {
  return colorMap[props.status] || { bg: '#eee', color: '#333', label: props.status };
});
</script>

<template>
  <span
    :style="{ backgroundColor: style.bg, color: style.color }"
    class="booking-badge"
  >
    {{ style.label }}
  </span>
</template>

<style scoped>
.booking-badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}
</style>
