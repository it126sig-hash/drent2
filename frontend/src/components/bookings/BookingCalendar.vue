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
    const dow = d.getDay();
    return {
      date: format(d, 'yyyy-MM-dd'),
      dayNum: format(d, 'd'),
      dayName: format(d, 'EEE'),
      isToday: format(d, 'yyyy-MM-dd') === todayStr,
      isWeekend: dow === 0 || dow === 6,
      isSunday: dow === 0,
    };
  });
});

const formatIDR = (val) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);

const bookingBars = computed(() => {
  const result = [];
  const start = startOfDay(parseISO(props.startDate));
  const end = addDays(start, DAYS_COUNT);

  props.bookings.forEach(booking => {
    const totalTagihan = booking.total_tagihan ?? 0;
    const totalBayar = booking.total_payments ?? (booking.payments || []).reduce((s, p) => s + (p.amount || 0), 0);
    const sisaTagihan = totalTagihan - totalBayar;
    const isLunas = sisaTagihan <= 0 && totalTagihan > 0;

    booking.booking_details.forEach(detail => {
      if (!detail.unit_id) return;

      const dStart = startOfDay(parseISO(detail.tgl_sewa));
      const dEnd = startOfDay(parseISO(detail.tgl_kembali));

      const overlapStart = dStart < start ? start : dStart;
      const overlapEnd = dEnd > end ? end : dEnd;

      if (overlapStart <= overlapEnd && overlapStart < end && overlapEnd >= start) {
        const startOffset = Math.max(0, Math.floor((overlapStart - start) / (1000 * 60 * 60 * 24)));
        const duration = Math.floor((overlapEnd - overlapStart) / (1000 * 60 * 60 * 24)) + 1;
        const span = Math.min(duration, DAYS_COUNT - startOffset);

        if (span > 0) {
          const detailType = detail.detail_type === 'extend' ? 'extend' : 'initial';
          const driver = detail.driver;

          result.push({
            bookingId: booking.id,
            unitId: detail.unit_id,
            startCol: startOffset + 2, // col 1 = unit info, so +1; +1 again for 1-based grid
            span,
            status: booking.status,
            customerName: booking.customer?.nama || 'Unknown',
            kodeBooking: booking.kode_booking,
            tujuan: booking.tujuan || null,
            driverName: driver?.nama || null,
            catatan: booking.catatan || null,
            isLunas,
            totalTagihan,
            totalBayar,
            detailType,
            unitNoPolisi: detail.unit?.no_polisi,
          });
        }
      }
    });
  });
  return result;
});

const getStatusConfig = (status) => {
  const map = {
    'follow_up':    { bg: 'rgba(168, 174, 187, 0.20)', border: '#8A92A6', color: '#1A1D2E', label: 'Follow Up' },
    'confirm':      { bg: 'rgba(11, 122, 138, 0.15)',  border: '#0B7A8A', color: '#0B7A8A', label: 'Confirm' },
    'waiting_list': { bg: 'rgba(212, 160, 23, 0.15)',  border: '#D4A017', color: '#8C660A', label: 'Waiting List' },
    'rental_unit':  { bg: 'rgba(39, 168, 88, 0.18)',   border: '#27A858', color: '#147239', label: 'Rental Unit' },
    'selesai':      { bg: 'rgba(39, 168, 88, 0.10)',   border: '#27A858', color: '#1A6A38', label: 'Selesai' },
    'batal':        { bg: 'rgba(229, 83, 75, 0.12)',   border: '#E5534B', color: '#B02A24', label: 'Batal' },
  };
  return map[status] || { bg: '#eee', border: '#ccc', color: '#333', label: status };
};

const LEGEND_STATUSES = ['follow_up', 'confirm', 'waiting_list', 'rental_unit', 'selesai', 'batal'];

const buildTooltip = (bar) => {
  const lines = [];
  lines.push(`📋 ${bar.kodeBooking}`);
  if (bar.tujuan) lines.push(`📍 ${bar.tujuan}`);
  lines.push(`🚗 ${bar.driverName ? `Supir: ${bar.driverName}` : 'Lepas kunci'}`);
  if (bar.catatan) lines.push(`📝 ${bar.catatan}`);
  lines.push(bar.isLunas ? '✅ Lunas' : `💰 Sisa: ${formatIDR(bar.totalTagihan - bar.totalBayar)}`);
  return lines.join('\n');
};

const handleCellClick = (unitId, date) => {
  emit('cell-click', { unitId, date });
};

const handleBookingClick = (bookingId) => {
  emit('booking-click', bookingId);
};
</script>

<template>
  <div class="calendar-wrapper">
    <!-- Calendar Grid -->
    <div class="calendar-container">
      <div
        class="calendar-grid"
        :style="{ gridTemplateColumns: `180px repeat(${DAYS_COUNT}, 40px)` }"
      >
        <!-- Row 1: Header -->
        <!-- Col 1: Corner -->
        <div class="grid-header sticky-col" style="grid-column: 1; grid-row: 1;">Unit / Tanggal</div>

        <!-- Col 2..N: Day Headers -->
        <div
          v-for="(day, dayIndex) in days"
          :key="`hdr-${day.date}`"
          class="grid-header day-header"
          :class="{ 'is-today': day.isToday, 'is-weekend': day.isWeekend, 'is-sunday': day.isSunday }"
          :style="{ gridColumn: dayIndex + 2, gridRow: 1 }"
        >
          <div class="day-name">{{ day.dayName }}</div>
          <div class="day-num">{{ day.dayNum }}</div>
        </div>

        <!-- Rows 2..N: Unit rows -->
        <template v-for="(unit, unitIndex) in units" :key="`unit-${unit.id}`">
          <!-- Unit Info Col -->
          <div
            class="unit-cell sticky-col"
            :style="{ gridColumn: 1, gridRow: unitIndex + 2 }"
          >
            <div class="unit-name">{{ unit.merk }} {{ unit.tipe }}</div>
            <!-- Plate + Owner inline row -->
            <div class="unit-plate-row">
              <span class="unit-plate">{{ unit.no_polisi }}</span>
              <div class="owner-marquee-wrap">
                <span
                  class="owner-badge owner-marquee"
                  :class="unit.rental_owner
                    ? (unit.rental_owner.is_owner === false ? 'owner-external' : 'owner-internal')
                    : 'owner-internal'"
                >
                  {{ unit.rental_owner
                    ? (unit.rental_owner.is_owner === false ? '🤝 ' : '🏠 ') + unit.rental_owner.nama
                    : '🏠 Internal' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Day cells -->
          <div
            v-for="(day, dayIndex) in days"
            :key="`cell-${unit.id}-${day.date}`"
            class="grid-cell"
            :class="{ 'is-today': day.isToday, 'is-weekend': day.isWeekend, 'is-sunday': day.isSunday }"
            :style="{ gridColumn: dayIndex + 2, gridRow: unitIndex + 2 }"
            @click="handleCellClick(unit.id, day.date)"
          ></div>
        </template>

        <!-- Booking Bars — Explicitly placed in grid -->
        <div
          v-for="(bar, index) in bookingBars"
          :key="`bar-${index}`"
          class="booking-bar"
          :class="`bar-status-${bar.status}`"
          :style="{
            gridColumn: `${bar.startCol} / span ${bar.span}`,
            gridRow: units.findIndex(u => u.id === bar.unitId) + 2,
            backgroundColor: getStatusConfig(bar.status).bg,
            color: getStatusConfig(bar.status).color,
            borderLeft: `3px solid ${getStatusConfig(bar.status).border}`
          }"
          :title="buildTooltip(bar)"
          @click.stop="handleBookingClick(bar.bookingId)"
        >
          <div class="bar-content">
            <div class="bar-top-row">
              <span class="bar-name">{{ bar.customerName }}</span>
              <span v-if="bar.detailType === 'extend'" class="bar-tag bar-tag-ext">EXT</span>
              <span v-if="bar.isLunas" class="bar-tag bar-tag-lunas">✓</span>
            </div>
            <div v-if="bar.tujuan && bar.span >= 3" class="bar-sub">📍 {{ bar.tujuan }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div class="calendar-legend">
      <span class="legend-title">Legenda:</span>
      <div class="legend-items">
        <div
          v-for="status in LEGEND_STATUSES"
          :key="`legend-${status}`"
          class="legend-item"
        >
          <span
            class="legend-dot"
            :style="{
              backgroundColor: getStatusConfig(status).bg,
              borderLeft: `3px solid ${getStatusConfig(status).border}`
            }"
          ></span>
          <span class="legend-label">{{ getStatusConfig(status).label }}</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot legend-dot-ext">EXT</span>
          <span class="legend-label">Extend</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot legend-dot-lunas">✓</span>
          <span class="legend-label">Lunas</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot legend-dot-weekend"></span>
          <span class="legend-label">Weekend</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.calendar-wrapper {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

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
  /* Explicit row heights: header auto, data rows fixed at 44px */
  grid-auto-rows: auto;
}

/* ── Header ── */
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
  border-right: 2px solid var(--surface-border);
  text-align: left;
  padding-left: var(--space-lg);
  box-shadow: 2px 0 6px rgba(0, 0, 0, 0.04);
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

/* Weekend headers */
.day-header.is-weekend .day-name {
  color: #B02A24;
}

.day-header.is-weekend .day-num {
  color: #E5534B;
  font-weight: 700;
}

.day-header.is-today {
  background: rgba(13, 128, 145, 0.08);
}

.day-header.is-today .day-num {
  color: #0D8091;
  font-weight: 700;
}

/* ── Unit Cell ── */
.unit-cell {
  padding: 6px var(--space-lg);
  border-bottom: 1px solid var(--surface-border);
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 2px;
  min-height: 44px;
  /* Fixed height so rows stay compact */
  height: 44px;
  overflow: hidden;
}

.unit-name {
  font-family: var(--font-headline);
  font-weight: 600;
  font-size: 12px;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 148px;
}

/* Plate + Owner on same row */
.unit-plate-row {
  display: flex;
  align-items: center;
  gap: 4px;
  overflow: hidden;
  max-width: 148px;
}

.unit-plate {
  font-family: var(--font-mono);
  font-size: 10px;
  color: var(--text-secondary);
  white-space: nowrap;
  flex-shrink: 0;
}

/* Marquee container — fixed width, clips overflow */
.owner-marquee-wrap {
  overflow: hidden;
  flex: 1;
  min-width: 0;
  /* slightly shorter than plate area */
  max-width: 90px;
}

/* The badge itself scrolls when hovered */
.owner-marquee {
  display: inline-block;
  font-size: 9px;
  font-family: var(--font-body);
  font-weight: 600;
  padding: 1px 4px;
  border-radius: 3px;
  white-space: nowrap;
  /* start static */
  transform: translateX(0);
  transition: none;
}

/* On hover over the cell, trigger the marquee scroll */
.unit-cell:hover .owner-marquee {
  animation: marquee-scroll 4s linear infinite;
}

@keyframes marquee-scroll {
  0%   { transform: translateX(0); }
  30%  { transform: translateX(0); }          /* pause at start */
  70%  { transform: translateX(-60%); }       /* scroll left */
  100% { transform: translateX(-60%); }       /* pause at end */
}

.owner-internal {
  background: #E6F6EC;
  color: #147239;
}

.owner-external {
  background: #E1F4F6;
  color: #085A66;
}

/* ── Grid Cells ── */
.grid-cell {
  border-bottom: 1px solid var(--surface-border);
  border-right: 1px solid var(--surface-border);
  cursor: pointer;
  transition: background 0.15s;
  /* Match unit-cell fixed height */
  min-height: 44px;
  height: 44px;
}

.grid-cell:hover {
  background: var(--card-bg-hover);
}

.grid-cell.is-today {
  background: rgba(13, 128, 145, 0.04);
}

.grid-cell.is-today:hover {
  background: rgba(13, 128, 145, 0.08);
}

.grid-cell.is-weekend {
  background: rgba(229, 83, 75, 0.04);
}

.grid-cell.is-sunday {
  background: rgba(229, 83, 75, 0.07);
  border-right: 1px solid rgba(229, 83, 75, 0.15);
}

.grid-cell.is-weekend:hover {
  background: rgba(229, 83, 75, 0.09);
}

/* ── Booking Bars ── */
.booking-bar {
  /*
   * Row height = 44px. We want the bar to fill ~80%.
   * 80% of 44px = 35.2px. So margin top + bottom = 9px total → 4.5px each.
   * Using 4px margin gives bar height ≈ 36px = 82%.
   */
  margin: 4px 1px;
  padding: 3px 6px;
  border-radius: var(--radius-xs);
  font-family: var(--font-body);
  font-size: 10px;
  font-weight: 600;
  cursor: pointer;
  z-index: 5;
  overflow: hidden;
  display: flex;
  align-items: center;
  box-shadow: var(--shadow-tile);
  transition: all 0.2s;
  align-self: stretch;
}

.booking-bar:hover {
  filter: brightness(0.93);
  z-index: 6;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.bar-content {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
  min-width: 0;
  width: 100%;
  overflow: hidden;
}

.bar-top-row {
  display: flex;
  align-items: center;
  gap: 3px;
  overflow: hidden;
}

.bar-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1;
  min-width: 0;
}

.bar-sub {
  font-size: 8px;
  font-weight: 400;
  opacity: 0.8;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-top: 1px;
}

.bar-tag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 8px;
  font-weight: 700;
  padding: 1px 3px;
  border-radius: 3px;
  flex-shrink: 0;
  line-height: 1;
}

.bar-tag-ext {
  background: rgba(212, 160, 23, 0.25);
  color: #8C660A;
  border: 1px solid #D4A017;
}

.bar-tag-lunas {
  background: rgba(39, 168, 88, 0.2);
  color: #147239;
  border: 1px solid #27A858;
}

/* ── Legend ── */
.calendar-legend {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  background: var(--surface-default);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  flex-wrap: wrap;
}

.legend-title {
  font-family: var(--font-body);
  font-size: 11px;
  font-weight: 600;
  color: var(--text-secondary);
  white-space: nowrap;
}

.legend-items {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  align-items: center;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 5px;
}

.legend-dot {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 16px;
  border-radius: 3px;
  font-size: 8px;
  font-weight: 700;
  flex-shrink: 0;
}

.legend-dot-ext {
  background: rgba(212, 160, 23, 0.2);
  color: #8C660A;
  border: 1px solid #D4A017;
  font-size: 8px;
}

.legend-dot-lunas {
  background: rgba(39, 168, 88, 0.18);
  color: #147239;
  border: 1px solid #27A858;
}

.legend-dot-weekend {
  background: rgba(229, 83, 75, 0.07);
  border: 1px solid rgba(229, 83, 75, 0.2);
}

.legend-label {
  font-family: var(--font-body);
  font-size: 11px;
  color: var(--text-secondary);
  white-space: nowrap;
}
</style>
