<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBooking } from '../../composables/useBooking';
import { useUnit } from '../../composables/useUnit';
import { useRentalOwner } from '../../composables/useRentalOwner';
import { useCity } from '../../composables/useCity';
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue';
import BookingCalendar from '../../components/bookings/BookingCalendar.vue';
import { format, addMonths, subMonths, startOfMonth } from 'date-fns';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import ProgressBar from 'primevue/progressbar';
import ContextMenu from 'primevue/contextmenu';

const router = useRouter();
const { 
  bookings, loading, pagination, filters, 
  fetchAll, fetchForCalendar, changeStatus, requestReturnToRentalUnit, statusLoading 
} = useBooking();

const { units, loading: unitsLoading, fetchAll: fetchUnits } = useUnit();
const { rentalOwners, fetchAll: fetchRentalOwners } = useRentalOwner();
const { cities, fetchAll: fetchCities } = useCity();

const activeTab = ref(0);
const calendarStart = ref(format(startOfMonth(new Date()), 'yyyy-MM-dd'));
const calendarBookings = ref([]);

// Calendar filters
const calendarOwnerFilter = ref(null);
const calendarTransactionFilter = ref('all'); // 'all' | 'has_booking'

const calendarTransactionOptions = [
  { label: 'Semua Unit', value: 'all' },
  { label: 'Hanya Ada Transaksi', value: 'has_booking' },
];

const filteredCalendarUnits = computed(() => {
  let list = units.value;

  // Filter by rental owner
  if (calendarOwnerFilter.value) {
    list = list.filter(u => u.rental_owner_id === calendarOwnerFilter.value);
  }

  // Filter by transaction presence in this date window
  if (calendarTransactionFilter.value === 'has_booking') {
    const unitIdsWithBookings = new Set(
      calendarBookings.value.flatMap(b =>
        b.booking_details.map(d => d.unit_id)
      ).filter(Boolean)
    );
    list = list.filter(u => unitIdsWithBookings.has(u.id));
  }

  return list;
});

const showStatusDialog = ref(false);
const showReturnRequestDialog = ref(false);
const selectedBooking = ref(null);
const newStatus = ref('');
const statusNote = ref('');
const returnRequestReason = ref('');
const bookingContextMenu = ref(null);
const contextMenuSelection = ref(null);
const contextMenuItems = ref([]);

const isMobile = ref(window.innerWidth < 768);
const handleResize = () => {
  isMobile.value = window.innerWidth < 768;
};

const statusOptions = [
  { label: 'Follow Up', value: 'follow_up' },
  { label: 'Confirm', value: 'confirm' },
  { label: 'Waiting List', value: 'waiting_list' },
  { label: 'Rental Unit', value: 'rental_unit' },
  { label: 'Selesai', value: 'selesai' },
  { label: 'Batal', value: 'batal' },
];

const mainTabStatusValues = ['follow_up', 'confirm', 'waiting_list', 'rental_unit'];
const closedTabStatusValues = ['selesai', 'batal'];
const activeStatusOptions = statusOptions.filter(option => mainTabStatusValues.includes(option.value));
const selectedStatusFilters = ref([]);

const sortOptions = [
  { label: 'Terbaru dibuat', value: 'created_at:desc' },
  { label: 'Kode A-Z', value: 'kode_booking:asc' },
  { label: 'Kode Z-A', value: 'kode_booking:desc' },
  { label: 'Sewa terdekat', value: 'tgl_sewa:asc' },
  { label: 'Sewa terbaru', value: 'tgl_sewa:desc' },
];

const selectedSort = ref('created_at:desc');

const getActiveTabStatusFilter = () => {
  return selectedStatusFilters.value.length
    ? selectedStatusFilters.value.filter(status => mainTabStatusValues.includes(status))
    : [...mainTabStatusValues];
};

const isStatusSelected = (status) => selectedStatusFilters.value.includes(status);

const toggleStatusFilter = (status) => {
  selectedStatusFilters.value = isStatusSelected(status)
    ? selectedStatusFilters.value.filter(selectedStatus => selectedStatus !== status)
    : [...selectedStatusFilters.value, status];
};

const loadFilterOptions = async () => {
  await Promise.allSettled([
    fetchRentalOwners({ per_page: 100 }),
    fetchCities({ per_page: 100, is_active: true }),
  ]);
};

const loadData = async () => {
  if (activeTab.value === 0) {
    filters.value.status = getActiveTabStatusFilter();
    await fetchAll(pagination.value.current_page);
  } else if (activeTab.value === 1) {
    filters.value.status = [...closedTabStatusValues];
    await fetchAll(pagination.value.current_page);
  } else {
    await fetchUnits({ per_page: 100 }); // Get all units for calendar
    const endDate = format(addMonths(new Date(calendarStart.value), 1), 'yyyy-MM-dd');
    calendarBookings.value = await fetchForCalendar(calendarStart.value, endDate);
  }
};

onMounted(() => {
  loadFilterOptions();
  loadData();
  window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
});

watch(activeTab, loadData);

const onPage = (event) => {
  pagination.value.current_page = event.page + 1;
  fetchAll(pagination.value.current_page);
};

const applyFilters = () => {
  const [sortBy, sortDirection] = selectedSort.value.split(':');
  filters.value.sort_by = sortBy;
  filters.value.sort_direction = sortDirection;
  if (activeTab.value === 0) {
    filters.value.status = getActiveTabStatusFilter();
  } else if (activeTab.value === 1) {
    filters.value.status = [...closedTabStatusValues];
  }
  pagination.value.current_page = 1;
  fetchAll(1);
};

const resetFilters = () => {
  selectedStatusFilters.value = [];
  filters.value.status = activeTab.value === 1 ? [...closedTabStatusValues] : [...mainTabStatusValues];
  filters.value.date_from = null;
  filters.value.date_to = null;
  filters.value.search = '';
  filters.value.rental_owner_id = null;
  filters.value.kota = null;
  selectedSort.value = 'created_at:desc';
  applyFilters();
};

const openStatusDialog = (booking) => {
  selectedBooking.value = booking;
  newStatus.value = '';
  statusNote.value = booking.catatan_status || '';
  showStatusDialog.value = true;
};

const canUpdateStatus = (booking) => {
  return booking && !['selesai', 'batal'].includes(booking.status);
};

const canRequestReturnToRentalUnit = (booking) => {
  return booking?.status === 'selesai'
    && booking?.rental_unit_return_request?.status !== 'pending';
};

const getAllowedNextStatuses = (currentStatus) => {
  const map = {
    'follow_up':    ['confirm', 'batal'],
    'confirm':      ['waiting_list', 'batal'],
    'waiting_list': ['rental_unit', 'batal'],
    'rental_unit':  ['selesai', 'batal'],
  };
  const allowed = map[currentStatus] || [];
  return statusOptions.filter(opt => allowed.includes(opt.value));
};

const saveStatus = async () => {
  if (!newStatus.value) return;
  try {
    await changeStatus(selectedBooking.value.id, newStatus.value, statusNote.value);
    showStatusDialog.value = false;
  } catch (err) {
    console.error(err);
  }
};

const openReturnRequestDialog = (booking) => {
  selectedBooking.value = booking;
  returnRequestReason.value = '';
  showReturnRequestDialog.value = true;
};

const submitReturnRequest = async () => {
  if (!selectedBooking.value || !returnRequestReason.value.trim()) return;

  await requestReturnToRentalUnit(selectedBooking.value.id, returnRequestReason.value.trim());
  showReturnRequestDialog.value = false;
  await fetchAll(pagination.value.current_page);
};

const prevMonth = () => {
  calendarStart.value = format(subMonths(new Date(calendarStart.value), 1), 'yyyy-MM-dd');
  loadData();
};

const nextMonth = () => {
  calendarStart.value = format(addMonths(new Date(calendarStart.value), 1), 'yyyy-MM-dd');
  loadData();
};

const goToDetail = (id) => {
  router.push(`/bookings/${id}`);
};

const onRowDoubleClick = (event) => {
  goToDetail(event.data.id);
};

const onRowContextMenu = (event) => {
  contextMenuSelection.value = event.data;
  contextMenuItems.value = [
    {
      label: 'Lihat Detail',
      icon: 'pi pi-eye',
      command: () => goToDetail(event.data.id),
    },
    {
      label: 'Ubah Status',
      icon: 'pi pi-sync',
      disabled: !canUpdateStatus(event.data),
      command: () => openStatusDialog(event.data),
    },
    {
      label: 'Request Kembali Rental Unit',
      icon: 'pi pi-undo',
      disabled: !canRequestReturnToRentalUnit(event.data),
      command: () => openReturnRequestDialog(event.data),
    },
  ];
  bookingContextMenu.value.show(event.originalEvent);
};

const openCreateWithPreFill = ({ unitId, date }) => {
  router.push({
    path: '/bookings/create',
    query: { unit_id: unitId, tgl_sewa: date }
  });
};

const formatCurrency = (val) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
};

const formatDateTime = (val) => {
  if (!val) return '-';
  return format(new Date(val), 'dd MMM yyyy HH:mm');
};

const formatPackage = (val) => {
  const map = {
    harian: 'Harian',
    mingguan: 'Mingguan',
    bulanan: 'Bulanan',
  };
  return map[val] || val || '-';
};

const getDisplayDetail = (booking) => {
  const details = booking?.booking_details || [];
  if (!details.length) return null;

  return details.find(detail => detail.status === 'aktif')
    || details.find(detail => detail.detail_type === 'initial')
    || details.find(detail => detail.status === 'draft')
    || details[details.length - 1];
};

const getExtraDetailCount = (booking) => {
  const count = booking?.booking_details?.length || 0;
  return Math.max(count - 1, 0);
};

const getVehicleInfo = (booking) => {
  const detail = getDisplayDetail(booking);
  const unit = detail?.unit;
  const owner = unit?.rental_owner;

  if (unit) {
    return {
      title: [unit.merk, unit.tipe].filter(Boolean).join(' ') || 'Unit tanpa nama',
      subtitle: unit.no_polisi || '-',
      ownerName: owner?.nama || 'Internal',
      ownerType: owner?.is_owner === false ? 'rental_lain' : 'pemilik_rental',
      ownerBadge: owner?.is_owner === false ? 'Rental Lain' : 'Pemilik Rental',
      ownerSeverity: owner?.is_owner === false ? 'info' : 'success',
      assigned: true,
      placeholder: false,
    };
  }

  return {
    title: detail?.unit_placeholder || 'Belum ditentukan',
    subtitle: '',
    ownerName: null,
    ownerType: null,
    ownerBadge: null,
    ownerSeverity: null,
    assigned: false,
    placeholder: true,
  };
};

const getRentalDuration = (booking) => {
  const detail = getDisplayDetail(booking);
  const lamaSewa = detail?.lama_sewa || booking?.lama_sewa;
  const paketSewa = detail?.paket_sewa || booking?.paket_sewa;

  if (!lamaSewa && !paketSewa) return '-';
  return `${lamaSewa || '-'} x ${formatPackage(paketSewa)}`;
};

const getDriverInfo = (booking) => {
  const driver = getDisplayDetail(booking)?.driver;

  return {
    name: driver?.nama || 'Lepas kunci',
    hasDriver: Boolean(driver),
  };
};

const hasPickupNotes = (booking) => {
  return Boolean(booking?.alamat_penjemputan || booking?.catatan);
};

const getPaidAmount = (booking) => {
  if (booking?.total_payments != null) return booking.total_payments;
  return (booking?.payments || []).reduce((sum, payment) => sum + (payment.amount || 0), 0);
};

const getSignedCostAmount = (cost) => {
  const amount = Number(cost?.amount || 0);
  return cost?.type === 'diskon' ? -amount : amount;
};

const getDetailCostTotal = (detail, options = {}) => {
  const costs = detail?.costs || [];
  const filteredCosts = options.additionalOnly
    ? costs.filter(cost => cost.is_additional)
    : costs;

  return filteredCosts.reduce((sum, cost) => sum + getSignedCostAmount(cost), 0);
};

const getDetailRentalSubtotal = (detail, booking) => {
  const duration = detail?.lama_sewa || booking?.lama_sewa || 1;
  return Math.max(0, ((detail?.harga_mobil || 0) - (detail?.diskon_mobil || 0)) * duration);
};

const getDetailTotalSewa = (detail, booking) => {
  const duration = detail?.lama_sewa || booking?.lama_sewa || 1;

  if (detail?.pricing_mode === 'all_in') {
    return ((detail?.harga_all_in || 0) * duration) + getDetailCostTotal(detail, { additionalOnly: true });
  }

  return getDetailRentalSubtotal(detail, booking) + getDetailCostTotal(detail);
};

const hasPricedDetails = (booking) => {
  const details = booking?.booking_details || [];
  return details
    .filter(detail => detail.status !== 'batal')
    .some(detail => detail.unit_id && ((detail.harga_mobil || 0) > 0 || (detail.harga_all_in || 0) > 0));
};

const getTotalSewa = (booking) => {
  if (!hasPricedDetails(booking)) return booking?.harga_dealing || 0;

  if (booking?.total_tagihan != null) return booking.total_tagihan;

  return (booking?.booking_details || [])
    .filter(detail => detail.status !== 'batal')
    .reduce((sum, detail) => sum + getDetailTotalSewa(detail, booking), 0);
};

const getLateInfo = (booking) => {
  if (['selesai', 'batal'].includes(booking?.status)) return null;

  const detail = getDisplayDetail(booking);
  if (!detail?.tgl_kembali) return null;

  const returnDate = new Date(detail.tgl_kembali);
  const now = new Date();
  const diffMs = now.getTime() - returnDate.getTime();
  if (Number.isNaN(diffMs) || diffMs <= 0) return null;

  const totalHours = Math.floor(diffMs / (1000 * 60 * 60));
  const days = Math.floor(totalHours / 24);
  const hours = totalHours % 24;
  const parts = [];

  if (days) parts.push(`${days} hari`);
  if (hours) parts.push(`${hours} jam`);
  if (!parts.length) parts.push('kurang dari 1 jam');

  return {
    days,
    hours,
    label: parts.join(' '),
  };
};

const rowClass = (data) => {
  return getLateInfo(data) ? 'booking-row booking-row-late' : 'booking-row';
};

const getBookingCardClass = (booking) => {
  const statusClassMap = {
    follow_up: 'booking-card-neutral',
    confirm: 'booking-card-info',
    waiting_list: 'booking-card-neutral',
    rental_unit: 'booking-card-success',
    selesai: 'booking-card-success',
    batal: 'booking-card-error',
  };

  return statusClassMap[booking?.status] || 'booking-card-neutral';
};
</script>

<template>
  <div class="page-container" :class="{ 'booking-list-active': activeTab !== 2 }">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="text-h1">Manajemen Booking</h1>
        <p class="text-secondary text-xs">Kelola semua pesanan rental kendaraan dalam satu panel.</p>
      </div>
      <button class="btn-pill btn-primary create-booking-button" @click="router.push('/bookings/create')">
        <i class="pi pi-plus"></i>
        <span class="create-label-desktop">Buat Booking</span>
        <span class="create-label-mobile">Booking</span>
      </button>
    </div>

    <!-- Tab Toggle -->
    <div class="tab-toggle-container">
      <div class="pill-toggle">
        <button 
          class="toggle-item" 
          :class="{ active: activeTab === 0 }" 
          @click="activeTab = 0"
        >
          Daftar Booking
        </button>
        <button 
          class="toggle-item" 
          :class="{ active: activeTab === 1 }" 
          @click="activeTab = 1"
        >
          Booking Selesai
        </button>
        <button 
          class="toggle-item" 
          :class="{ active: activeTab === 2 }" 
          @click="activeTab = 2"
        >
          Kalender Unit
        </button>
      </div>
    </div>

    <div v-if="activeTab === 0 || activeTab === 1" class="tab-content booking-list-tab">
      <!-- Filter Bar -->
      <div class="filter-bar surface-card">
        <div class="filter-groups">
          <div class="filter-group filter-group-wide">
            <label>Cari</label>
            <span class="filter-search">
              <i class="pi pi-search"></i>
              <InputText v-model="filters.search" placeholder="Kode, pelanggan, tujuan..." class="w-full" @keyup.enter="applyFilters" />
            </span>
          </div>
          <div v-if="activeTab === 0" class="filter-group filter-group-status">
            <label>Status</label>
            <div class="status-filter-buttons" role="group" aria-label="Filter status booking">
              <button
                v-for="option in activeStatusOptions"
                :key="option.value"
                type="button"
                class="status-filter-button"
                :class="{ active: isStatusSelected(option.value) }"
                :aria-pressed="isStatusSelected(option.value)"
                @click="toggleStatusFilter(option.value)"
              >
                {{ option.label }}
              </button>
            </div>
          </div>
          <div class="filter-group">
            <label>Mulai</label>
            <DatePicker v-model="filters.date_from" dateFormat="yy-mm-dd" placeholder="Dari Tanggal" class="w-full md:w-36" />
          </div>
          <div class="filter-group">
            <label>Sampai</label>
            <DatePicker v-model="filters.date_to" dateFormat="yy-mm-dd" placeholder="Sampai Tanggal" class="w-full md:w-36" />
          </div>
          <div class="filter-group">
            <label>Pemilik</label>
            <Dropdown v-model="filters.rental_owner_id" :options="rentalOwners" optionLabel="nama" optionValue="id" placeholder="Semua Pemilik" showClear filter class="w-full md:w-48" />
          </div>
          <div class="filter-group">
            <label>Kota</label>
            <Dropdown v-model="filters.kota" :options="cities" optionLabel="nama" optionValue="nama" placeholder="Semua Kota" showClear filter class="w-full md:w-40" />
          </div>
          <div class="filter-group">
            <label>Sort</label>
            <Dropdown v-model="selectedSort" :options="sortOptions" optionLabel="label" optionValue="value" class="w-full md:w-44" />
          </div>
        </div>
        <div class="filter-actions">
          <button class="btn-pill btn-secondary btn-pill-compact" @click="resetFilters" :disabled="loading">
            <i class="pi pi-refresh"></i> Reset
          </button>
          <button class="btn-pill btn-primary btn-pill-compact" @click="applyFilters" :disabled="loading">
            <i class="pi pi-filter"></i> Filter
          </button>
        </div>
      </div>

      <ContextMenu ref="bookingContextMenu" :model="contextMenuItems" />

      <!-- Desktop DataTable -->
      <div v-if="!isMobile" class="booking-table-shell">
        <DataTable
          :value="bookings"
          lazy
          paginator
          scrollable
          scrollHeight="flex"
          :rows="pagination.per_page"
          :totalRecords="pagination.total"
          @page="onPage"
          :loading="loading"
          class="drent-datatable"
          responsiveLayout="scroll"
          :rowClass="rowClass"
          v-model:contextMenuSelection="contextMenuSelection"
          contextMenu
          @row-dblclick="onRowDoubleClick"
          @row-contextmenu="onRowContextMenu"
        >
         <Column header="Aksi" class="text-center">
            <template #body="{ data }">
               <div class="action-pill-group">
                  <button class="action-btn" @click.stop="goToDetail(data.id)"><i class="pi pi-eye"></i></button>
                  <button
                    v-if="data.status === 'selesai'"
                    class="action-btn"
                    :disabled="!canRequestReturnToRentalUnit(data)"
                    @click.stop="openReturnRequestDialog(data)"
                    v-tooltip.top="'Request kembali ke Rental Unit'"
                  >
                    <i class="pi pi-undo"></i>
                  </button>
                  <!-- <button class="action-btn" @click.stop="openStatusDialog(data)" :disabled="!canUpdateStatus(data)"><i class="pi pi-sync"></i></button> -->
               </div>
            </template>
          </Column>
          <Column header="Kode" style="min-width: 9rem">
            <template #body="{ data }">
              <div class="flex flex-col items-start gap-1">
                <span class="font-semibold text-xs">{{ data.kode_booking }}</span>
                <BookingStatusBadge :status="data.status" />
              </div>
            </template>
          </Column>
          <Column header="Pelanggan" style="min-width: 10rem">
            <template #body="{ data }">
              <div class="flex flex-col items-start gap-1">
                <span class="font-semibold">{{ data.customer?.nama || '-' }}</span>
                <BookingStatusBadge v-if="data.customer?.status" :status="data.customer.status" />
                <span v-else class="text-xs text-tertiary">Status belum ada</span>
              </div>
            </template>
          </Column>
            <Column header="Unit" style="min-width: 12rem">
            <template #body="{ data }">
              <div class="flex flex-col gap-0.5">
                <span class="font-semibold text-xs">{{ getVehicleInfo(data).title }}</span>
                <span class="text-xs text-secondary font-mono-numeric">{{ getVehicleInfo(data).subtitle }}</span>
                <div class="mt-1" v-if="getVehicleInfo(data).ownerType">
                  <BookingStatusBadge :status="getVehicleInfo(data).ownerType" :text="getVehicleInfo(data).ownerName" />
                </div>
              </div>
            </template>
          </Column>
           <Column header="Periode" style="min-width: 13rem">
            <template #body="{ data }">
              <div class="flex flex-col gap-1">
                <span class="font-medium text-xs">{{ formatDateTime(getDisplayDetail(data)?.tgl_sewa) }}</span>
                <span class="text-[10px] text-tertiary">s/d {{ formatDateTime(getDisplayDetail(data)?.tgl_kembali) }}</span>
                <span class="text-[11px] font-bold text-secondary mt-1">{{ getRentalDuration(data) }}</span>
              </div>
            </template>
          </Column>
          <Column header="Total Biaya" style="min-width: 10rem">
            <template #body="{ data }">
              <div class="flex flex-col items-end">
                <span class="font-mono-numeric text-primary text-sm">{{ formatCurrency(getTotalSewa(data)) }}</span>
                <span class="font-mono-numeric text-positive text-sm">{{ formatCurrency(getPaidAmount(data)) }}</span>
                <div v-if="getTotalSewa(data)-getPaidAmount(data) > 0">
                  <span class="font-mono-numeric text-info text-sm italic">(sisa){{ formatCurrency(getTotalSewa(data)-getPaidAmount(data)) }}</span>
                </div>
                <div v-else>
                 <BookingStatusBadge status="lunas"/>
                </div>
              </div>
            </template>
          </Column>
          <Column header="Tujuan" style="min-width: 11rem">
            <template #body="{ data }">
              <span class="table-text-clamp font-medium">{{ data.tujuan || '-' }}</span>
            </template>
          </Column>
          <Column header="Kota" style="min-width: 9rem">
            <template #body="{ data }">
              <span class="table-text-clamp font-medium">{{ data.kota || '-' }}</span>
            </template>
          </Column>
          <Column header="Alamat & Catatan" style="min-width: 17rem">
            <template #body="{ data }">
              <div v-if="hasPickupNotes(data)" class="table-note-stack">
                <div v-if="data.alamat_penjemputan" class="table-note-line">
                  <span class="font-bold">{{ data.alamat_penjemputan }}</span>
                </div>
                <div v-if="data.catatan" class="table-note-line">
                  <span class="text-xs text-secondary italic">*{{ data.catatan }}</span>
                </div>
              </div>
              <span v-else class="text-secondary">-</span>
            </template>
          </Column>
        
          <Column header="Driver" style="min-width: 10rem">
            <template #body="{ data }">
              <div class="driver-cell" :class="{ 'driver-cell-empty': !getDriverInfo(data).hasDriver }">
                <span class="driver-name">{{ getDriverInfo(data).name }}</span>
                <span class="status-badge" :class="getDriverInfo(data).hasDriver ? 'info' : 'success'">{{ getDriverInfo(data).hasDriver ? 'Dengan driver' : 'Tanpa driver' }}</span>
              </div>
            </template>
          </Column>
         
        </DataTable>
      </div>

      <!-- Mobile Card List -->
      <div v-else class="mobile-card-list">
         <div v-if="loading" class="p-4 text-center">
            <ProgressBar mode="indeterminate" style="height: 4px" />
         </div>
         <div v-else-if="bookings.length === 0" class="p-8 text-center text-secondary">
            Tidak ada booking ditemukan.
         </div>
         <div
           v-else
           v-for="booking in bookings"
           :key="booking.id"
           class="booking-card surface-card"
           :class="getBookingCardClass(booking)"
           @click="goToDetail(booking.id)"
         >
            <div class="card-header">
               <BookingStatusBadge :status="booking.status" />
               <span class="font-bold text-sm">{{ getVehicleInfo(booking).title }}</span>
               <span class="text-xs text-semibold text-tertiary">{{ booking.kode_booking }}</span>
            </div>
            <div class="card-body">
               <div class="info-row">
                  <div class="info-col">
                     <span class="label">Pelanggan</span>
                     <span class="value">{{ booking.customer?.nama || '-' }}</span>
                  </div>
                  <div class="info-col items-end">
                     <span class="label">Unit</span>
                     <span class="value">{{ getVehicleInfo(booking).title }}</span>
                  </div>
               </div>
               <div class="info-row mt-3">
                  <div class="info-col">
                     <span class="label">Periode</span>
                     <span class="value text-xs">{{ formatDateTime(getDisplayDetail(booking)?.tgl_sewa) }}</span>
                  </div>
                  <div class="info-col items-end">
                     <span class="label">Durasi</span>
                     <span class="value">{{ getRentalDuration(booking) }}</span>
                  </div>
               </div>
               <div v-if="getLateInfo(booking)" class="late-banner-mini mt-2">
                  <i class="pi pi-clock"></i> Terlambat {{ getLateInfo(booking).label }}
               </div>
            </div>
            <div class="card-footer">
               <div class="amount-group">
                  <span class="text-tertiary text-[10px]">Total Sewa</span>
                  <span class="font-mono-numeric font-bold text-sm">{{ formatCurrency(getTotalSewa(booking)) }}</span>
               </div>
               <div class="amount-group items-end">
                  <span class="text-tertiary text-[10px]">Sudah Bayar</span>
                  <span class="font-mono-numeric font-bold text-sm text-positive">{{ formatCurrency(getPaidAmount(booking)) }}</span>
               </div>
            </div>
            <button
              v-if="booking.status === 'selesai'"
              class="btn-pill btn-secondary btn-pill-compact mt-3"
              :disabled="!canRequestReturnToRentalUnit(booking)"
              @click.stop="openReturnRequestDialog(booking)"
            >
              <i class="pi pi-undo"></i>
              {{ booking.rental_unit_return_request?.status === 'pending' ? 'Menunggu Supervisor' : 'Request Rental Unit' }}
            </button>
         </div>
         <!-- Mobile Paginator -->
         <div class="mobile-paginator mt-4">
            <Button icon="pi pi-chevron-left" :disabled="pagination.current_page === 1" @click="onPage({page: pagination.current_page - 2})" text />
            <span class="text-sm">Hal {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
            <Button icon="pi pi-chevron-right" :disabled="pagination.current_page === pagination.last_page" @click="onPage({page: pagination.current_page})" text />
         </div>
      </div>
    </div>

    <div v-if="activeTab === 2" class="tab-content">
      <!-- Calendar Controls Bar -->
      <div class="calendar-controls-bar">
        <!-- Month Navigation -->
        <div class="calendar-nav-group">
          <Button icon="pi pi-chevron-left" @click="prevMonth" text rounded size="small" />
          <h2 class="calendar-title">{{ format(new Date(calendarStart), 'MMMM yyyy') }}</h2>
          <Button icon="pi pi-chevron-right" @click="nextMonth" text rounded size="small" />
        </div>

        <!-- Filters -->
        <div class="calendar-filters-group">
          <Dropdown
            v-model="calendarOwnerFilter"
            :options="rentalOwners"
            optionLabel="nama"
            optionValue="id"
            placeholder="Semua Pemilik"
            showClear
            filter
            class="calendar-filter-dropdown"
          />
          <Dropdown
            v-model="calendarTransactionFilter"
            :options="calendarTransactionOptions"
            optionLabel="label"
            optionValue="value"
            class="calendar-filter-dropdown"
          />
          <!-- Refresh button -->
          <button
            class="btn-pill btn-secondary btn-pill-compact"
            :disabled="unitsLoading"
            @click="loadData"
          >
            <i class="pi pi-refresh" :class="{ 'pi-spin': unitsLoading }"></i>
            Refresh
          </button>
        </div>
      </div>

      <ProgressBar v-if="unitsLoading" mode="indeterminate" style="height: 4px" class="mb-4" />

      <div v-if="!unitsLoading && units.length === 0" class="drent-empty-state">
        <i class="pi pi-info-circle text-4xl text-tertiary mb-3"></i>
        <p class="text-secondary">Tidak ada unit kendaraan yang tersedia.</p>
      </div>

      <BookingCalendar
        v-else
        :bookings="calendarBookings"
        :units="filteredCalendarUnits"
        :startDate="calendarStart"
        @booking-click="goToDetail"
        @cell-click="openCreateWithPreFill"
      />
    </div>

    <!-- Status Dialog -->
    <Dialog v-model:visible="showStatusDialog" header="Perbarui Status Booking" :style="{ width: '450px' }" modal :position="isMobile ? 'bottom' : 'center'" :class="{'mobile-bottom-sheet': isMobile}">
      <div class="flex flex-col gap-4">
        <div v-if="selectedBooking" class="status-summary-card">
          <div class="flex justify-between">
            <span class="text-tertiary">Booking</span>
            <span class="font-bold">{{ selectedBooking.kode_booking }}</span>
          </div>
          <div class="flex justify-between mt-2">
            <span class="text-tertiary">Status Saat Ini</span>
            <BookingStatusBadge :status="selectedBooking.status" />
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-xs text-secondary">Pilih Status Baru</label>
          <Dropdown 
            v-model="newStatus" 
            :options="getAllowedNextStatuses(selectedBooking?.status)" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Pilih status..." 
            class="w-full"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-xs text-secondary">Catatan Status (Opsional)</label>
          <Textarea v-model="statusNote" rows="3" class="w-full" />
        </div>
      </div>

      <template #footer>
        <div class="flex gap-2 w-full">
           <Button label="Batal" icon="pi pi-times" text class="flex-1" @click="showStatusDialog = false" />
           <Button label="Simpan" icon="pi pi-check" class="flex-1" @click="saveStatus" :loading="statusLoading" :disabled="!newStatus" />
        </div>
      </template>
    </Dialog>

    <Dialog v-model:visible="showReturnRequestDialog" header="Request Kembali ke Rental Unit" :style="{ width: '450px' }" modal :position="isMobile ? 'bottom' : 'center'" :class="{'mobile-bottom-sheet': isMobile}">
      <div class="flex flex-col gap-4">
        <div v-if="selectedBooking" class="status-summary-card">
          <div class="flex justify-between">
            <span class="text-tertiary">Booking</span>
            <span class="font-bold">{{ selectedBooking.kode_booking }}</span>
          </div>
          <div class="flex justify-between mt-2">
            <span class="text-tertiary">Status Saat Ini</span>
            <BookingStatusBadge :status="selectedBooking.status" />
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-xs text-secondary">Alasan Request</label>
          <Textarea v-model="returnRequestReason" rows="4" class="w-full" placeholder="Contoh: transaksi perlu dikoreksi karena unit belum benar-benar selesai..." />
        </div>
      </div>

      <template #footer>
        <div class="flex gap-2 w-full">
           <Button label="Batal" icon="pi pi-times" text class="flex-1" @click="showReturnRequestDialog = false" />
           <Button label="Kirim Request" icon="pi pi-send" class="flex-1" @click="submitReturnRequest" :loading="loading" :disabled="returnRequestReason.trim().length < 5" />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page-container {
  padding: var(--space-2xl);
  width: 100%;
  max-width: none;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: var(--space-3xl);
}

.create-label-mobile {
   display: none;
}

/* === Tab Toggle === */
.tab-toggle-container {
   margin-bottom: var(--space-2xl);
}

.pill-toggle {
   display: inline-flex;
   background: var(--card-bg);
   padding: 4px;
   border-radius: var(--radius-full);
   gap: 4px;
}

.toggle-item {
   padding: 6px 16px;
   border-radius: var(--radius-full);
   border: none;
   background: transparent;
   font-family: var(--font-body);
   font-size: 12px;
   font-weight: 600;
   color: var(--text-secondary);
   cursor: pointer;
   transition: all 0.2s;
}

.toggle-item.active {
   background: var(--text-primary);
   color: #FFFFFF;
   box-shadow: var(--shadow-tile);
}

/* === Filter Bar === */
.filter-bar {
   padding: var(--space-md);
   display: flex;
   justify-content: space-between;
   align-items: flex-end;
   margin-bottom: var(--space-lg);
   gap: var(--space-lg);
   flex-wrap: wrap;
}

.filter-groups {
   display: flex;
   gap: var(--space-xl);
   flex-wrap: wrap;
   align-items: flex-end;
   flex: 1 1 auto;
}

.filter-group {
   display: flex;
   flex-direction: column;
   gap: 4px;
}

.filter-group-wide {
   min-width: min(260px, 100%);
}

.filter-group-status {
   flex: 1 1 520px;
   min-width: min(520px, 100%);
}

.filter-search {
   display: block;
   position: relative;
   width: 100%;
}

.filter-search > i {
   position: absolute;
   top: 50%;
   left: 12px;
   z-index: 1;
   color: var(--text-tertiary);
   font-size: 12px;
   transform: translateY(-50%);
   pointer-events: none;
}

.filter-search :deep(.p-inputtext) {
   padding-left: 34px;
}

.filter-bar :deep(.p-inputtext),
.filter-bar :deep(.p-datepicker input),
.filter-bar :deep(.p-select),
.filter-bar :deep(.p-dropdown) {
   min-height: 36px;
   border-radius: var(--radius-default);
   border-color: var(--surface-border);
   font-size: 13px;
}

.filter-bar :deep(.p-select),
.filter-bar :deep(.p-dropdown) {
   display: inline-flex;
   align-items: center;
}

.filter-bar :deep(.p-select-label),
.filter-bar :deep(.p-dropdown-label) {
   display: flex;
   align-items: center;
   padding-top: 0;
   padding-bottom: 0;
}

.filter-bar :deep(.p-select-dropdown),
.filter-bar :deep(.p-dropdown-trigger) {
   width: 36px;
}

.filter-actions {
   display: flex;
   align-items: center;
   gap: var(--space-sm);
}

.filter-group label {
   font-size: 11px;
   font-weight: 600;
   color: var(--text-tertiary);
   margin-left: 4px;
}

.status-filter-buttons {
   display: flex;
   flex-wrap: wrap;
   gap: 4px;
   max-width: 100%;
}

.status-filter-button {
   min-height: 32px;
   padding: 7px 14px;
   border-radius: var(--radius-full);
   border: 1px solid var(--surface-border);
   background: var(--surface-default);
   color: var(--text-secondary);
   font-family: var(--font-body);
   font-size: 11px;
   font-weight: 700;
   line-height: 1.2;
   white-space: nowrap;
   cursor: pointer;
   box-shadow: inset 0 0 0 1px rgba(26, 29, 46, 0.02);
   transition: background 0.2s, border-color 0.2s, color 0.2s, box-shadow 0.2s;
}

.status-filter-button:hover {
   border-color: var(--neutral-6);
   background: var(--card-bg-hover);
   color: var(--text-primary);
}

.status-filter-button.active {
   border-color: var(--primary);
   background: var(--primary);
   color: var(--text-white);
   box-shadow: 0 4px 10px rgba(26, 29, 46, 0.18);
}

.status-filter-button.active:hover {
   background: var(--primary);
   color: var(--text-white);
}

/* === DataTable Styling === */
.booking-table-shell {
   width: 100%;
}

:deep(.booking-row-late) {
   background-color: rgba(229, 83, 75, 0.04) !important;
}

:deep(.booking-row-late td:first-child) {
   border-left: 3px solid var(--negative);
}

.drent-datatable {
   width: 100%;
   border: 1px solid var(--surface-border);
   border-radius: var(--radius-default);
   overflow: hidden;
   background: var(--surface-default);
   box-shadow: var(--shadow-tile);
}

:deep(.drent-datatable .p-datatable-wrapper),
:deep(.drent-datatable .p-datatable-table-container) {
   border-radius: inherit;
}

:deep(.drent-datatable .p-datatable-thead > tr > th),
:deep(.drent-datatable .p-datatable-tbody > tr > td) {
   border-right: none;
}

:deep(.drent-datatable .p-datatable-tbody > tr:last-child > td) {
   border-bottom: none;
}

:deep(.drent-datatable .p-paginator) {
   border-top: 1px solid var(--surface-border);
   border-radius: 0 0 var(--radius-default) var(--radius-default);
}

.booking-code-badge {
   display: inline-flex;
   align-items: center;
   width: fit-content;
   max-width: 100%;
   padding: 5px 10px;
   border-radius: var(--radius-full);
   background: rgba(43, 52, 72, 0.08);
   color: var(--text-primary);
   font-size: 11px;
   font-weight: 700;
   line-height: 1.2;
   white-space: nowrap;
}

.table-text-clamp {
   display: -webkit-box;
   -webkit-line-clamp: 2;
   -webkit-box-orient: vertical;
   overflow: hidden;
   line-height: 1.35;
}

.table-note-stack {
   display: flex;
   flex-direction: column;
   gap: 6px;
}

.table-note-line {
   display: flex;
   flex-direction: column;
   gap: 2px;
}

.table-note-label {
   width: fit-content;
   color: var(--text-tertiary);
   font-size: 10px;
   font-weight: 700;
   text-transform: uppercase;
}

.driver-cell {
   display: inline-flex;
   flex-direction: column;
   align-items: flex-start;
   gap: 2px;
   max-width: 100%;
}

.driver-name {
   color: var(--text-primary);
   font-size: 12px;
   font-weight: 700;
   line-height: 1.25;
}

.driver-mode {
   color: var(--text-tertiary);
   font-size: 10px;
   font-weight: 600;
}

.driver-cell-empty .driver-name {
   color: var(--text-secondary);
}

.action-pill-group {
   display: inline-flex;
   background: var(--surface-default);
   border: 1px solid var(--surface-border);
   padding: 2px;
   border-radius: var(--radius-full);
   gap: 2px;
}

.action-btn {
   width: 28px;
   height: 28px;
   border-radius: var(--radius-full);
   border: none;
   background: transparent;
   display: flex;
   align-items: center;
   justify-content: center;
   color: var(--text-secondary);
   cursor: pointer;
   transition: all 0.2s;
}

.action-btn:hover:not(:disabled) {
   background: var(--card-bg-hover);
   color: var(--text-primary);
}

.action-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* === Mobile Card List === */
.mobile-card-list {
   display: flex;
   flex-direction: column;
   gap: var(--space-sm);
}

.booking-card {
   padding: var(--space-lg);
   cursor: pointer;
}

.card-header {
   display: flex;
   justify-content: space-between;
   align-items: center;
   margin-bottom: var(--space-lg);
   padding-bottom: var(--space-sm);
   border-bottom: 1px solid var(--surface-border);
}

.info-row { display: flex; justify-content: space-between; gap: var(--space-md); }
.info-col { display: flex; flex-direction: column; gap: 2px; }
.info-col .label { font-size: 10px; color: var(--text-tertiary); font-weight: 500; }
.info-col .value { font-size: 12px; font-weight: 600; color: var(--text-primary); }

.late-banner-mini {
   display: inline-flex;
   align-items: center;
   gap: 6px;
   padding: 4px 8px;
   background: rgba(229, 83, 75, 0.08);
   color: var(--negative);
   border-radius: var(--radius-xs);
   font-size: 10px;
   font-weight: 700;
}

.card-footer {
   margin-top: var(--space-lg);
   padding-top: var(--space-md);
   border-top: 1px dashed var(--surface-border);
   display: flex;
   justify-content: space-between;
}

.amount-group { display: flex; flex-direction: column; }

.mobile-paginator {
   display: flex;
   align-items: center;
   justify-content: center;
   gap: var(--space-xl);
   color: var(--text-secondary);
}

/* === Calendar Polish === */
.calendar-header {
   margin-bottom: var(--space-xl);
}

.calendar-title {
   font-family: var(--font-headline);
   font-size: 16px;
   font-weight: 700;
   color: var(--text-primary);
   text-transform: capitalize;
}

.drent-empty-state {
   padding: var(--space-3xl);
   text-align: center;
   background: var(--card-bg);
   border-radius: var(--radius-default);
   border: 1px dashed var(--surface-border);
}

/* === Dialog/Bottom Sheet === */
.status-summary-card {
   background: var(--card-bg);
   padding: var(--space-lg);
   border-radius: var(--radius-default);
   font-size: 12px;
}

:deep(.mobile-bottom-sheet) {
   margin: 0 !important;
   width: 100% !important;
   border-radius: var(--radius-lg) var(--radius-lg) 0 0 !important;
   max-height: 80vh;
}

@media (max-width: 768px) {
  .page-container {
     padding: var(--space-lg);
  }

  .page-header {
     margin-bottom: var(--space-xl);
  }

  .page-header .header-left p {
     display: none;
  }

  .page-header .text-h1 {
     font-size: 20px;
     line-height: 1.2;
  }

  .tab-toggle-container {
     margin-bottom: var(--space-xl);
  }

  .pill-toggle {
     width: fit-content;
     max-width: 100%;
     padding: 4px;
  }

  .toggle-item {
     min-height: 32px;
     padding: 7px 16px;
     font-size: 12px;
  }

  .filter-bar {
     align-items: flex-start;
     flex-direction: column;
     gap: var(--space-md);
     padding: var(--space-lg);
     margin-bottom: var(--space-md);
     border-radius: var(--radius-default);
  }

  .filter-groups {
     width: 100%;
     flex-direction: column;
     gap: var(--space-md);
  }

  .filter-group {
     width: 100%;
     max-width: none;
     gap: 6px;
  }

  .filter-group-wide,
  .filter-group-status {
     min-width: 0;
     width: 100%;
  }

  .filter-group label {
     margin-left: 4px;
     font-size: 10px;
  }

  .filter-group :deep(.p-dropdown),
  .filter-group :deep(.p-datepicker),
  .filter-group :deep(.p-inputtext) {
     width: 100%;
  }

  .filter-actions {
     width: 100%;
     justify-content: flex-start;
     flex-wrap: wrap;
   }

  .filter-bar .btn-pill {
     min-height: 30px;
     padding: 6px 12px;
  }

  .create-booking-button {
     position: fixed;
     right: var(--space-lg);
     bottom: calc(72px + env(safe-area-inset-bottom));
     z-index: 150;
     padding: 12px 16px;
     box-shadow: 0 12px 28px rgba(26, 29, 46, 0.22);
  }
  .create-label-desktop { display: none; }
  .create-label-mobile { display: inline; }

  .mobile-card-list {
     gap: var(--space-md);
     padding-bottom: 80px;
  }

  .booking-card {
     padding: var(--space-md);
     border-width: 1px;
     border-style: solid;
     border-radius: var(--radius-default);
     box-shadow: var(--shadow-tile);
  }

  .booking-card-neutral {
     border-color: var(--neutral-4);
  }

  .booking-card-info {
     border-color: var(--info-cyan);
  }

  .booking-card-success {
     border-color: var(--positive);
  }

  .booking-card-error {
     border-color: var(--negative);
  }

  .card-header {
     margin-bottom: var(--space-md);
     padding-bottom: var(--space-sm);
     align-items: center;
  }

  .card-header :deep(.status-badge) {
     padding: 5px 10px;
     font-size: 10px;
  }

  .card-body {
     display: flex;
     flex-direction: column;
     gap: var(--space-md);
  }

  .info-row {
     align-items: flex-start;
     gap: var(--space-md);
  }

  .info-col {
     flex: 1 1 0;
     min-width: 0;
  }

  .info-col.items-end {
     text-align: right;
  }

  .info-col .label {
     font-size: 10px;
  }

  .info-col .value {
     font-size: 12px;
     line-height: 1.25;
     overflow-wrap: anywhere;
  }

  .card-footer {
     margin-top: var(--space-md);
     padding-top: var(--space-sm);
     align-items: flex-end;
  }

  .amount-group {
     min-width: 0;
  }

  .amount-group.items-end {
     text-align: right;
  }
}

@media (min-width: 769px) {
  .page-container.booking-list-active {
     height: 100dvh;
     min-height: 0;
     display: flex;
     flex-direction: column;
     overflow: hidden;
  }

  .page-container.booking-list-active .page-header,
  .page-container.booking-list-active .tab-toggle-container {
     flex: 0 0 auto;
  }

  .booking-list-tab {
     flex: 1 1 auto;
     min-height: 0;
     display: flex;
     flex-direction: column;
     overflow: hidden;
  }

  .filter-bar {
     flex: 0 0 auto;
  }

  .booking-table-shell {
     flex: 1 1 auto;
     min-height: 0;
     display: flex;
     overflow: hidden;
  }

  .drent-datatable {
     flex: 1 1 auto;
     min-height: 0;
     display: flex;
     flex-direction: column;
  }

  :deep(.drent-datatable .p-datatable-table-container),
  :deep(.drent-datatable .p-datatable-wrapper) {
     flex: 1 1 auto;
     min-height: 0;
     overflow: auto;
  }
}

/* === Calendar Controls Bar === */
.calendar-controls-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-md);
  margin-bottom: var(--space-lg);
  padding: var(--space-md) var(--space-lg);
  background: var(--surface-default);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  box-shadow: var(--shadow-tile);
}

.calendar-nav-group {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}

.calendar-title {
  font-family: var(--font-headline);
  font-size: 16px;
  font-weight: 700;
  color: var(--text-primary);
  min-width: 160px;
  text-align: center;
}

.calendar-filters-group {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  flex-wrap: wrap;
}

.calendar-filter-dropdown {
  min-width: 160px;
}

.calendar-filter-dropdown :deep(.p-select),
.calendar-filter-dropdown :deep(.p-dropdown) {
  min-height: 34px;
  border-radius: var(--radius-default);
  border-color: var(--surface-border);
  font-size: 12px;
}
</style>
