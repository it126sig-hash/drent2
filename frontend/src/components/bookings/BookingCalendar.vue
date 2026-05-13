<script setup>
import { computed } from 'vue';
import { format, parseISO, addDays, startOfDay } from 'date-fns';

const props = defineProps({
  bookings: {
    type: Array,
    default: () => []
  },
  units: {
    type: Array,
    default: () => []
  },
  startDate: {
    type: String,
    default: () => new Date().toISOString().slice(0, 10)
  }
});

const emit = defineEmits(['booking-click', 'cell-click']);

const DAYS_COUNT = 30;
const todayStr = new Date().toISOString().slice(0, 10);

const days = computed(() => {
  const start = parseISO(props.startDate);
  return Array.from({ length: DAYS_COUNT }, (_, i) => {
    const d = addDays(start, i);
    return {
      date: format(d, 'yyyy-MM-dd'),
      dayNum: format(d, 'd'),
      dayName: format(d, 'EEE'),
      isToday: format(d, 'yyyy-MM-dd') === todayStr,
      isWeekend: [0, 6].includes(d.getDay())
    };
  });
});

const bookingBars = computed(() => {
  const result = [];
  const start = startOfDay(parseISO(props.startDate));
  const end = addDays(start, DAYS_COUNT);

  props.bookings.forEach(booking => {
    booking.booking_details.forEach(detail => {
      if (!detail.unit_id) return;

      const dStart = startOfDay(parseISO(detail.tgl_sewa));
      const dEnd = startOfDay(parseISO(detail.tgl_kembali));

      // Check if detail overlaps with current 30-day window
      const overlapStart = dStart < start ? start : dStart;
      const overlapEnd = dEnd > end ? end : dEnd;

      if (overlapStart <= overlapEnd && overlapStart < end && overlapEnd >= start) {
        const startOffset = Math.max(0, Math.floor((overlapStart - start) / (1000 * 60 * 60 * 24)));
        const duration = Math.floor((overlapEnd - overlapStart) / (1000 * 60 * 60 * 24)) + 1;
        
        // Ensure it doesn't overflow the grid
        const span = Math.min(duration, DAYS_COUNT - startOffset);

        if (span > 0) {
          result.push({
            bookingId: booking.id,
            unitId: detail.unit_id,
            startCol: startOffset + 2, // +1 for unit col, +1 for 1-based grid
            span: span,
            status: booking.status,
            customerName: booking.customer?.nama || 'Unknown',
            unitNoPolisi: detail.unit?.no_polisi
          });
        }
      }
    });
  });
  return result;
});

const getStatusConfig = (status) => {
  const map = {
    'follow_up':    { bg: 'rgba(168, 174, 187, 0.2)', border: 'var(--text-secondary)', color: 'var(--text-primary)' },
    'confirm':      { bg: 'rgba(11, 122, 138, 0.15)', border: 'var(--info-cyan)', color: '#0B7A8A' },
    'waiting_list': { bg: 'rgba(168, 174, 187, 0.12)', border: 'var(--neutral-6)', color: 'var(--text-secondary)' },
    'rental_unit':  { bg: 'rgba(39, 168, 88, 0.15)', border: 'var(--positive)', color: '#27A858' },
    'selesai':      { bg: 'rgba(39, 168, 88, 0.12)', border: '#27A858', color: '#1A6A38' },
    'batal':        { bg: 'rgba(229, 83, 75, 0.12)', border: 'var(--negative)', color: 'var(--negative)' },
  };
  return map[status] || { bg: '#eee', border: '#ccc', color: '#333' };
};

const handleCellClick = (unitId, date) => {
  emit('cell-click', { unitId, date });
};

const handleBookingClick = (bookingId) => {
  emit('booking-click', bookingId);
};
</script>

<template>
  <div class="calendar-container">
    <div class="calendar-grid" :style="{ gridTemplateColumns: `180px repeat(${DAYS_COUNT}, 40px)` }">
      <!-- Header: Units Label -->
      <div class="grid-header sticky-col">Unit / Tanggal</div>
      
      <!-- Header: Days -->
      <div 
        v-for="day in days" 
        :key="day.date" 
        class="grid-header day-header"
        :class="{ 'is-today': day.isToday, 'is-weekend': day.isWeekend }"
      >
        <div class="day-name">{{ day.dayName }}</div>
        <div class="day-num">{{ day.dayNum }}</div>
      </div>

      <!-- Rows -->
      <template v-for="unit in units" :key="unit.id">
        <!-- Unit Info Col -->
        <div class="unit-cell sticky-col">
          <div class="unit-name">{{ unit.merk }} {{ unit.tipe }}</div>
          <div class="unit-plate">{{ unit.no_polisi }}</div>
        </div>

        <!-- Empty Cells for Grid -->
        <div 
          v-for="day in days" 
          :key="day.date" 
          class="grid-cell"
          :class="{ 'is-today': day.isToday, 'is-weekend': day.isWeekend }"
          @click="handleCellClick(unit.id, day.date)"
        ></div>
      </template>

      <!-- Booking Bars (Absolute positioned within the grid) -->
      <div 
        v-for="(bar, index) in bookingBars" 
        :key="index"
        class="booking-bar"
        :style="{
          gridColumn: `${bar.startCol} / span ${bar.span}`,
          gridRow: units.findIndex(u => u.id === bar.unitId) + 2,
          backgroundColor: getStatusConfig(bar.status).bg,
          color: getStatusConfig(bar.status).color,
          borderLeft: `3px solid ${getStatusConfig(bar.status).border}`
        }"
        @click.stop="handleBookingClick(bar.bookingId)"
      >
        <div class="bar-content">
          <span class="bar-name">{{ bar.customerName }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.calendar-container {
  width: 100%;
  overflow-x: auto;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
}

.calendar-grid {
  display: grid;
  position: relative;
  min-width: max-content;
}

.grid-header {
  background: var(--page-bg);
  padding: 10px 4px;
  font-family: var(--font-body);
  font-weight: 600;
  font-size: 11px;
  text-align: center;
  border-bottom: 1px solid var(--surface-border);
  border-right: 1px solid var(--surface-border);
  z-index: 10;
  color: var(--text-secondary);
}

.sticky-col {
  position: sticky;
  left: 0;
  background: var(--surface-default);
  z-index: 20;
  border-right: 1px solid var(--surface-border);
  text-align: left;
  padding-left: var(--space-lg);
  box-shadow: 2px 0 4px rgba(0,0,0,0.02);
}

.day-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.day-name {
  font-size: 9px;
  color: var(--text-tertiary);
  text-transform: uppercase;
}

.day-num {
  font-family: var(--font-headline);
  font-size: 14px;
  color: var(--text-primary);
}

.unit-cell {
  padding: 10px var(--space-lg);
  border-bottom: 1px solid var(--surface-border);
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.unit-name {
  font-family: var(--font-headline);
  font-weight: 600;
  font-size: 12px;
  color: var(--text-primary);
  white-space: nowrap;
}

.unit-plate {
  font-family: var(--font-mono);
  font-size: 10px;
  color: var(--text-secondary);
}

.grid-cell {
  border-bottom: 1px solid var(--surface-border);
  border-right: 1px solid var(--surface-border);
  cursor: pointer;
  transition: background 0.15s;
}

.grid-cell:hover {
  background: var(--card-bg-hover);
}

.is-today {
  background: rgba(13, 128, 145, 0.04);
}

.is-weekend {
  background: rgba(245, 246, 250, 0.4);
}

.day-header.is-today {
  background: rgba(13, 128, 145, 0.08);
}

.day-header.is-today .day-num {
   color: #0D8091;
}

.booking-bar {
  margin: 6px 1px;
  padding: 4px 6px;
  border-radius: var(--radius-xs);
  font-family: var(--font-body);
  font-size: 10px;
  font-weight: 600;
  cursor: pointer;
  z-index: 5;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  display: flex;
  align-items: center;
  box-shadow: var(--shadow-tile);
  transition: all 0.2s;
}

.booking-bar:hover {
  filter: brightness(0.95);
  z-index: 6;
  transform: translateY(-1px);
}

.bar-content {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}

.bar-name {
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>

