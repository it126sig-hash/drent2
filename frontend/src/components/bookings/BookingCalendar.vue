<script setup>
import { computed } from 'vue';
import { format, parseISO, addDays, isSameDay, isWithinInterval, startOfDay } from 'date-fns';

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

const getStatusColor = (status) => {
  const colors = {
    'follow_up':    '#FFF3CD',
    'confirm':      '#D1ECF1',
    'waiting_list': '#D6D8DB',
    'rental_unit':  '#D4EDDA',
    'selesai':      '#CCE5FF',
    'batal':        '#F8D7DA',
  };
  return colors[status] || '#eee';
};

const getStatusTextColor = (status) => {
  const colors = {
    'follow_up':    '#856404',
    'confirm':      '#0C5460',
    'waiting_list': '#383D41',
    'rental_unit':  '#155724',
    'selesai':      '#004085',
    'batal':        '#721C24',
  };
  return colors[status] || '#333';
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
          backgroundColor: getStatusColor(bar.status),
          color: getStatusTextColor(bar.status),
          borderLeft: `4px solid ${getStatusTextColor(bar.status)}`
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
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  color: #1e293b; /* Dark slate text */
}

.calendar-grid {
  display: grid;
  position: relative;
  min-width: max-content;
}

.grid-header {
  background: #f8fafc;
  padding: 12px 8px;
  font-weight: 600;
  font-size: 0.8rem;
  text-align: center;
  border-bottom: 2px solid #e2e8f0;
  border-right: 1px solid #e2e8f0;
  z-index: 10;
}

.sticky-col {
  position: sticky;
  left: 0;
  background: #f8fafc;
  z-index: 20;
  border-right: 2px solid #e2e8f0;
  text-align: left;
  padding-left: 16px;
}

.day-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.day-name {
  font-size: 0.65rem;
  color: #64748b;
  text-transform: uppercase;
}

.day-num {
  font-size: 1rem;
  color: #0f172a;
}

.unit-cell {
  padding: 12px 16px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.unit-name {
  font-weight: 600;
  font-size: 0.85rem;
  color: #1e293b;
}

.unit-plate {
  font-size: 0.75rem;
  color: #475569;
}

.grid-cell {
  border-bottom: 1px solid #e2e8f0;
  border-right: 1px solid #e2e8f0;
  cursor: pointer;
  transition: background 0.2s;
}

.grid-cell:hover {
  background: #f1f5f9;
}

.is-today {
  background: rgba(59, 130, 246, 0.05);
}

.is-weekend {
  background: #fcfcfc;
}

.day-header.is-today {
  color: #3b82f6;
  background: rgba(59, 130, 246, 0.1);
}

.booking-bar {
  margin: 8px 2px;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  z-index: 5;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  display: flex;
  align-items: center;
  box-shadow: 0 1px 2px rgba(0,0,0,0.1);
  transition: transform 0.1s, box-shadow 0.1s;
}

.booking-bar:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  z-index: 6;
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
