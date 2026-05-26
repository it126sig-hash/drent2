<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBooking } from '../../composables/useBooking';
import { getRentalOwners } from '../../api/rentalOwner';
import { useCity } from '../../composables/useCity';
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue';
import BookingCalendar from '../../components/bookings/BookingCalendar.vue';
import { format, addDays, addMonths, subMonths, startOfMonth } from 'date-fns';
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
import AutoComplete from 'primevue/autocomplete';

const router = useRouter();
const {
  bookings, loading, pagination, filters,
  fetchAll, fetchForCalendar, changeStatus, requestReturnToRentalUnit, statusLoading
} = useBooking();

const selectedOwnerFilter = ref(null);
const ownersSuggestions = ref([]);
const searchingOwners = ref(false);

const searchOwners = async (event) => {
  searchingOwners.value = true;
  try {
    const response = await getRentalOwners({ search: event.query || '', per_page: 20 });
    ownersSuggestions.value = response.data.data;
  } catch (err) {
    console.error('Gagal mencari pemilik rental', err);
  } finally {
    searchingOwners.value = false;
  }
};

watch(selectedOwnerFilter, (newVal) => {
  if (!newVal || (typeof newVal === 'string' && newVal.trim() === '')) {
    filters.value.rental_owner_id = null;
  } else {
    filters.value.rental_owner_id = newVal?.id || null;
  }
  applyFilters();
});

const { cities, fetchAll: fetchCities } = useCity();

const activeTab = ref(0);
const calendarStart = ref(format(startOfMonth(new Date()), 'yyyy-MM-dd'));
const calendarBookings = ref([]);
const calendarLoading = ref(false);
const calendarVisibleLimit = ref(50);
const showAdvancedFilters = ref(false);

// Calendar filters
const calendarOwnerFilter = ref(null);
const calendarVehicleSearch = ref('');

const calendarEnd = computed(() => format(addDays(new Date(calendarStart.value), 29), 'yyyy-MM-dd'));

const normalizeDateKey = (value) => {
  if (!value) return null;
  return format(new Date(value), 'yyyy-MM-dd');
};

const isDetailInCalendarPeriod = (detail) => {
  const detailStart = normalizeDateKey(detail?.tgl_sewa);
  const detailEnd = normalizeDateKey(detail?.tgl_kembali);

  return Boolean(detailStart && detailEnd && detailStart <= calendarEnd.value && detailEnd >= calendarStart.value);
};

const getUnitOwnerId = (unit) => unit?.rental_owner_id || unit?.rental_owner?.id || null;

const baseCalendarUnits = computed(() => {
  const unitMap = new Map();

  calendarBookings.value.forEach((booking) => {
    (booking.booking_details || []).forEach((detail) => {
      if (!isDetailInCalendarPeriod(detail)) return;

      if (!detail?.unit_id || !detail?.unit) {
        const pseudoId = `placeholder-${booking.id}-${detail.id || Math.random()}`;
        const rawPlaceholder = detail.unit_placeholder || 'Belum ditentukan';
        const isDashesOnly = !rawPlaceholder.replace(/[- ]/g, '');
        const cleanPlaceholder = isDashesOnly ? 'Belum ditentukan' : rawPlaceholder;

        unitMap.set(pseudoId, {
          id: pseudoId,
          merk: cleanPlaceholder,
          tipe: '',
          no_polisi: 'Unit belum ditentukan',
          isPlaceholder: true,
          rental_owner: null,
          rental_owner_id: null,
          transaction_count: 1,
        });
        return;
      }

      const existing = unitMap.get(detail.unit_id);
      if (existing) {
        existing.transaction_count += 1;
        return;
      }

      unitMap.set(detail.unit_id, {
        ...detail.unit,
        rental_owner_id: getUnitOwnerId(detail.unit),
        transaction_count: 1,
      });
    });
  });

  return [...unitMap.values()];
});

const calendarOwnerOptions = computed(() => {
  const ownerMap = new Map();

  baseCalendarUnits.value.forEach((unit) => {
    const owner = unit.rental_owner;
    const ownerId = getUnitOwnerId(unit);
    if (!ownerId || !owner?.nama) return;
    ownerMap.set(ownerId, { ...owner, id: ownerId });
  });

  return [...ownerMap.values()].sort((a, b) => (a?.nama || '').localeCompare(b?.nama || '', 'id', { sensitivity: 'base' }));
});

const matchesCalendarVehicleSearch = (unit) => {
  const keyword = calendarVehicleSearch.value.trim().toLowerCase();
  if (!keyword) return true;

  return [unit?.no_polisi, unit?.tipe, unit?.merk]
    .filter(Boolean)
    .some((value) => String(value).toLowerCase().includes(keyword));
};

const calendarUnits = computed(() => {
  return baseCalendarUnits.value
    .filter((unit) => !calendarOwnerFilter.value || getUnitOwnerId(unit) === calendarOwnerFilter.value)
    .filter(matchesCalendarVehicleSearch)
    .sort((a, b) => {
      if (b.transaction_count !== a.transaction_count) return b.transaction_count - a.transaction_count;

      const ownerCompare = (a.rental_owner?.nama || '').localeCompare(b.rental_owner?.nama || '', 'id', { sensitivity: 'base' });
      if (ownerCompare !== 0) return ownerCompare;

      return (a.no_polisi || '').localeCompare(b.no_polisi || '', 'id', { sensitivity: 'base' });
    });
});

const visibleCalendarUnits = computed(() => calendarUnits.value.slice(0, calendarVisibleLimit.value));
const hasMoreCalendarUnits = computed(() => visibleCalendarUnits.value.length < calendarUnits.value.length);

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
const closedStatusOptions = statusOptions.filter(option => closedTabStatusValues.includes(option.value));
const currentStatusOptions = computed(() => activeTab.value === 1 ? closedStatusOptions : activeStatusOptions);
const selectedStatusFilters = ref([]);
const selectedClosedStatusFilters = ref([]);

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

const getClosedTabStatusFilter = () => {
  return selectedClosedStatusFilters.value.length
    ? selectedClosedStatusFilters.value.filter(status => closedTabStatusValues.includes(status))
    : [...closedTabStatusValues];
};

const isStatusSelected = (status) => {
  return activeTab.value === 1
    ? selectedClosedStatusFilters.value.includes(status)
    : selectedStatusFilters.value.includes(status);
};

const toggleStatusFilter = (status) => {
  if (activeTab.value === 1) {
    selectedClosedStatusFilters.value = isStatusSelected(status)
      ? selectedClosedStatusFilters.value.filter(selectedStatus => selectedStatus !== status)
      : [...selectedClosedStatusFilters.value, status];
  } else {
    selectedStatusFilters.value = isStatusSelected(status)
      ? selectedStatusFilters.value.filter(selectedStatus => selectedStatus !== status)
      : [...selectedStatusFilters.value, status];
  }
  applyFilters();
};

let citySearchTimer = null;
const loadingCities = ref(false);

const searchCities = async (search = '') => {
  loadingCities.value = true;
  try {
    const params = { per_page: 30, is_active: true };
    if (search) params.search = search;
    await fetchCities(params);
  } catch (err) {
    console.error('Gagal memuat kota', err);
  } finally {
    loadingCities.value = false;
  }
};

const onCityFilter = (event) => {
  if (citySearchTimer) clearTimeout(citySearchTimer);
  citySearchTimer = setTimeout(() => {
    searchCities(String(event?.value || '').trim());
  }, 350);
};

const onCityDropdownShow = async () => {
  await searchCities('');
};

const loadFilterOptions = async () => {
  await Promise.allSettled([
    searchCities(''),
  ]);
};

const loadCalendarData = async () => {
  calendarLoading.value = true;
  calendarVisibleLimit.value = 50;
  try {
    calendarBookings.value = await fetchForCalendar(calendarStart.value, calendarEnd.value);
  } finally {
    calendarLoading.value = false;
  }
};

const loadData = async () => {
  if (activeTab.value === 0) {
    filters.value.status = getActiveTabStatusFilter();
    await fetchAll(pagination.value.current_page);
  } else if (activeTab.value === 1) {
    filters.value.status = getClosedTabStatusFilter();
    await fetchAll(pagination.value.current_page);
  } else {
    await loadCalendarData();
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
watch(calendarOwnerFilter, () => {
  calendarVisibleLimit.value = 50;
});
watch(calendarVehicleSearch, () => {
  calendarVisibleLimit.value = 50;
});

const loadMoreCalendarUnits = () => {
  calendarVisibleLimit.value += 50;
};

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
    filters.value.status = getClosedTabStatusFilter();
  }
  pagination.value.current_page = 1;
  fetchAll(1);
};

const resetFilters = () => {
  selectedStatusFilters.value = [];
  selectedClosedStatusFilters.value = [];
  filters.value.status = activeTab.value === 1 ? getClosedTabStatusFilter() : getActiveTabStatusFilter();
  filters.value.date_from = null;
  filters.value.date_to = null;
  filters.value.search = '';
  filters.value.rental_owner_id = null;
  selectedOwnerFilter.value = null;
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
    'follow_up': ['confirm', 'batal'],
    'confirm': ['waiting_list', 'batal'],
    'waiting_list': ['rental_unit', 'batal'],
    'rental_unit': ['selesai', 'batal'],
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
      label: 'Request Kembali Rental Unit',
      icon: 'pi pi-undo',
      disabled: !canRequestReturnToRentalUnit(event.data),
      command: () => openReturnRequestDialog(event.data),
    },
  ];
  bookingContextMenu.value.show(event.originalEvent);
};

const openCalendarContextMenu = ({ originalEvent, unitId, date, bookingId }) => {
  originalEvent?.preventDefault?.();
  contextMenuSelection.value = { unitId, date, bookingId };
  contextMenuItems.value = [
    {
      label: 'Tambah Booking',
      icon: 'pi pi-plus',
      disabled: !unitId || !date,
      command: () => openCreateWithPreFill({ unitId, date }),
    },
    ...(bookingId ? [
      {
        label: 'Lihat Detail',
        icon: 'pi pi-eye',
        command: () => goToDetail(bookingId),
      },
    ] : []),
  ];

  bookingContextMenu.value.show(originalEvent);
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

const getRentableDetails = (booking) => {
  return (booking?.booking_details || []).filter(detail => detail.status !== 'batal');
};

const getEarliestDate = (details, field) => {
  return details
    .map(detail => detail?.[field])
    .filter(Boolean)
    .sort((a, b) => new Date(a) - new Date(b))[0] || null;
};

const getLatestDate = (details, field) => {
  return details
    .map(detail => detail?.[field])
    .filter(Boolean)
    .sort((a, b) => new Date(b) - new Date(a))[0] || null;
};

const getPeriodStartDate = (booking) => {
  const details = getRentableDetails(booking);
  return getEarliestDate(details, 'tgl_sewa') || getDisplayDetail(booking)?.tgl_sewa;
};

const getPeriodEndDate = (booking) => {
  const details = getRentableDetails(booking);
  return getLatestDate(details, 'tgl_kembali') || getDisplayDetail(booking)?.tgl_kembali;
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
  const details = getRentableDetails(booking);
  const durationGroups = details.reduce((groups, detail) => {
    const paket = detail?.paket_sewa || booking?.paket_sewa;
    const lama = Number(detail?.lama_sewa || 0);
    if (!paket || !lama) return groups;

    groups[paket] = (groups[paket] || 0) + lama;
    return groups;
  }, {});

  const groupEntries = Object.entries(durationGroups);
  if (groupEntries.length) {
    const initialDetail = details.find(detail => detail.detail_type === 'initial')
      || [...details].sort((a, b) => new Date(a?.tgl_sewa || 0) - new Date(b?.tgl_sewa || 0))[0];
    const mainPackage = initialDetail?.paket_sewa && durationGroups[initialDetail.paket_sewa]
      ? initialDetail.paket_sewa
      : groupEntries[0][0];
    const mainDuration = durationGroups[mainPackage];
    const otherDuration = groupEntries
      .filter(([paket]) => paket !== mainPackage)
      .reduce((sum, [, lama]) => sum + lama, 0);
    const otherLabel = otherDuration ? ` (${otherDuration} lainnya)` : '';

    return `${mainDuration} x ${formatPackage(mainPackage)}${otherLabel}`;
  }

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

  const tglKembali = getPeriodEndDate(booking);
  if (!tglKembali) return null;

  const returnDate = new Date(tglKembali);
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
    note: `Terlambat ${parts.join(' ')}`,
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
    selesai: 'booking-card-completed',
    batal: 'booking-card-error',
  };

  return statusClassMap[booking?.status] || 'booking-card-neutral';
};
</script>

<template>
  <div class="page-container" :class="{ 'table-page-active': activeTab !== 2 }">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="text-h1">Manajemen Booking</h1>
        <p class="text-secondary text-xs">Kelola semua pesanan rental kendaraan dalam satu panel.</p>
      </div>
      <div class="header-actions">
        <!-- Tab Toggle -->
        <div class="tab-toggle-container">
          <div class="pill-toggle">
            <button class="toggle-item" :class="{ active: activeTab === 0 }" @click="activeTab = 0">
              Daftar Booking
            </button>
            <button class="toggle-item" :class="{ active: activeTab === 1 }" @click="activeTab = 1">
              Booking Selesai
            </button>
            <button class="toggle-item" :class="{ active: activeTab === 2 }" @click="activeTab = 2">
              Kalender Unit
            </button>
          </div>
        </div>

        <button class="btn-pill btn-primary create-booking-button" @click="router.push('/bookings/create')">
          <i class="pi pi-plus"></i>
          <span class="create-label-desktop">Buat Booking</span>
          <span class="create-label-mobile">Booking</span>
        </button>
      </div>
    </div>

    <ContextMenu ref="bookingContextMenu" :model="contextMenuItems" />

    <div v-if="activeTab === 0 || activeTab === 1" class="tab-content list-tab-fill booking-list-tab">
      <!-- Filter Bar -->
      <div class="filter-bar surface-card booking-filter-bar">
        <div class="filter-groups">
          <div class="filter-group filter-group-wide">
            <label>Pencarian</label>
            <span class="filter-search">
              <i class="pi pi-search"></i>
              <InputText v-model="filters.search" placeholder="Kode, pelanggan, tujuan..." class="w-full" @keyup.enter="applyFilters" />
            </span>
          </div>
          <div class="filter-group filter-group-status">
            <label>Status Rental</label>
            <div class="status-filter-buttons" role="group" aria-label="Filter status booking">
              <button v-for="option in currentStatusOptions" :key="option.value" type="button" class="status-filter-button" :class="{ active: isStatusSelected(option.value) }" :aria-pressed="isStatusSelected(option.value)" @click="toggleStatusFilter(option.value)">
                {{ option.label }}
              </button>
            </div>
          </div>
          <div class="filter-group filter-group-city">
            <label>Kota</label>
            <Dropdown v-model="filters.kota" :options="cities" optionLabel="nama" optionValue="nama" placeholder="Semua Kota" showClear filter :loading="loadingCities" @filter="onCityFilter" @show="onCityDropdownShow" @change="applyFilters" class="w-full md:w-40" />
          </div>
          <div v-if="showAdvancedFilters" class="advanced-filter-groups">
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
              <AutoComplete v-model="selectedOwnerFilter" :suggestions="ownersSuggestions" @complete="searchOwners" optionLabel="nama" placeholder="Cari Pemilik" dropdown forceSelection :loading="searchingOwners" class="w-full md:w-48" inputClass="w-full">
                <template #item="slotProps">
                  <div>
                    <div class="font-bold text-xs">{{ slotProps.item.nama }}</div>
                    <small class="text-secondary text-[10px]">{{ slotProps.item.kontak_1 }} - {{ slotProps.item.kota
                    }}</small>
                  </div>
                </template>
              </AutoComplete>
            </div>
            <div class="filter-group">
              <label>Sort</label>
              <Dropdown v-model="selectedSort" :options="sortOptions" optionLabel="label" optionValue="value" class="w-full md:w-44" />
            </div>
          </div>
        </div>
        <div class="filter-actions booking-filter-actions">
          <button class="btn-pill btn-secondary btn-pill-compact" type="button" :aria-expanded="showAdvancedFilters" @click="showAdvancedFilters = !showAdvancedFilters">
            <i class="pi" :class="showAdvancedFilters ? 'pi-chevron-up' : 'pi-sliders-h'"></i>
          </button>
          <button class="btn-pill btn-secondary btn-pill-compact" @click="resetFilters" :disabled="loading">
            <i class="pi pi-refresh"></i>
          </button>
          <button class="btn-pill btn-primary btn-pill-compact" @click="applyFilters" :disabled="loading">
            <i class="pi pi-filter"></i> Filter
          </button>
        </div>
      </div>

      <ProgressBar v-if="loading" mode="indeterminate" style="height: 4px" class="mb-4" />

      <!-- Desktop DataTable -->
      <div v-if="!isMobile" class="table-shell booking-table-shell">
        <DataTable :value="bookings" lazy paginator scrollable scrollHeight="flex" :rows="pagination.per_page" :totalRecords="pagination.total" @page="onPage" :loading="loading" class="drent-datatable" responsiveLayout="scroll" :rowClass="rowClass" v-model:contextMenuSelection="contextMenuSelection" contextMenu @row-dblclick="onRowDoubleClick" @row-contextmenu="onRowContextMenu">
          <Column header="Aksi" class="text-center">
            <template #body="{ data }">
              <div class="action-pill-group">
                <button class="action-btn" @click.stop="goToDetail(data.id)"><i class="pi pi-eye"></i></button>
                <button v-if="data.status === 'selesai'" class="action-btn" :disabled="!canRequestReturnToRentalUnit(data)" @click.stop="openReturnRequestDialog(data)" v-tooltip.top="'Request kembali ke Rental Unit'">
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
          <Column header="Unit" style="min-width: 10rem">
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
                <span class="font-medium text-xs">{{ formatDateTime(getPeriodStartDate(data)) }}</span>
                <span class="text-[10px] text-tertiary">s/d {{ formatDateTime(getPeriodEndDate(data)) }}</span>
                <span class="text-[11px] font-bold text-secondary mt-1">{{ getRentalDuration(data) }}</span>
                <span v-if="getLateInfo(data)" class="late-note">
                  <i class="pi pi-clock"></i>
                  {{ getLateInfo(data).note }}
                </span>
              </div>
            </template>
          </Column>
          <Column header="Total Biaya" style="min-width: 10rem">
            <template #body="{ data }">
              <div class="flex flex-col items-end">
                <span class="font-mono-numeric text-primary text-sm">{{ formatCurrency(getTotalSewa(data)) }}</span>
                <span class="font-mono-numeric text-positive text-sm">{{ formatCurrency(getPaidAmount(data)) }}</span>
                <div v-if="getTotalSewa(data) - getPaidAmount(data) > 0">
                  <span class="font-mono-numeric text-info text-sm italic">(sisa){{
                    formatCurrency(getTotalSewa(data) - getPaidAmount(data)) }}</span>
                </div>
                <div v-else>
                  <BookingStatusBadge status="lunas" />
                </div>
              </div>
            </template>
          </Column>
          <Column header="Tujuan" style="min-width: 11rem">
            <template #body="{ data }">
              <span class="table-text-clamp font-bold">{{ data.kota || '-' }}</span>
              <span class="table-text-clamp font-medium">{{ data.tujuan || '-' }}</span>
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
                <span class="status-badge" :class="getDriverInfo(data).hasDriver ? 'info' : 'success'">{{
                  getDriverInfo(data).hasDriver ? 'Dengan driver' : 'Tanpa driver' }}</span>
              </div>
            </template>
          </Column>

        </DataTable>
      </div>

      <!-- Mobile Card List -->
      <div v-else class="mobile-card-list">
        <div v-if="!loading && bookings.length === 0" class="p-8 text-center text-secondary">
          Tidak ada booking ditemukan.
        </div>
        <template v-else-if="!loading">
          <div v-for="booking in bookings" :key="booking.id" class="booking-card surface-card" :class="getBookingCardClass(booking)" @click="goToDetail(booking.id)">
            <div class="booking-card-topline">
              <BookingStatusBadge :status="booking.status" />
              <span class="booking-card-code">{{ booking.kode_booking }}</span>
            </div>

            <div class="booking-card-title-row">
              <span class="booking-card-icon"><i class="pi pi-car"></i></span>
              <div class="booking-card-title-block">
                <span class="booking-card-title">{{ getVehicleInfo(booking).title }}</span>
                <span class="booking-card-subtitle">{{ getVehicleInfo(booking).subtitle || 'Nopol belum ada' }}</span>
              </div>
            </div>

            <div class="booking-card-body">
              <div class="booking-info-grid">
                <div class="booking-info-item">
                  <i class="pi pi-user"></i>
                  <div>
                    <span class="booking-info-label">Pelanggan</span>
                    <span class="booking-info-value">{{ booking.customer?.nama || '-' }}</span>
                  </div>
                </div>
                <div class="booking-info-item">
                  <i class="pi pi-car"></i>
                  <div>
                    <span class="booking-info-label">Unit</span>
                    <span class="booking-info-value">{{ getVehicleInfo(booking).title }}</span>
                  </div>
                </div>
                <div class="booking-info-item">
                  <i class="pi pi-id-card"></i>
                  <div>
                    <span class="booking-info-label">Supir</span>
                    <span class="booking-info-value" :class="{ 'booking-info-value-success': !getDriverInfo(booking).hasDriver }">{{
                      getDriverInfo(booking).name }}</span>
                  </div>
                </div>
                <div class="booking-info-item">
                  <i class="pi pi-hashtag"></i>
                  <div>
                    <span class="booking-info-label">Nomor Polisi</span>
                    <span class="booking-info-value font-mono-numeric">{{ getVehicleInfo(booking).subtitle || '-'
                    }}</span>
                  </div>
                </div>
              </div>

              <div class="booking-period-row">
                <div class="booking-info-item booking-period-item">
                  <i class="pi pi-calendar"></i>
                  <div>
                    <span class="booking-info-label">Periode</span>
                    <span class="booking-info-value booking-period-text">{{ formatDateTime(getPeriodStartDate(booking))
                    }} -> {{ formatDateTime(getPeriodEndDate(booking)) }}</span>
                  </div>
                </div>
                <span class="booking-duration-pill"><i class="pi pi-clock"></i>{{ getRentalDuration(booking) }}</span>
              </div>

              <div v-if="getLateInfo(booking)" class="late-banner-mini mt-2">
                <i class="pi pi-clock"></i> {{ getLateInfo(booking).note }}
              </div>
            </div>
            <div class="booking-card-footer">
              <div class="amount-group">
                <span class="booking-info-label">Total Sewa</span>
                <span class="booking-total-amount font-mono-numeric">{{ formatCurrency(getTotalSewa(booking)) }}</span>
              </div>
              <div class="amount-group items-end">
                <span class="booking-info-label">Sudah Bayar</span>
                <span class="booking-paid-amount font-mono-numeric">{{ formatCurrency(getPaidAmount(booking)) }}</span>
                <span v-if="getTotalSewa(booking) - getPaidAmount(booking) > 0" class="booking-remaining-amount font-mono-numeric">Sisa {{ formatCurrency(getTotalSewa(booking) -
                  getPaidAmount(booking)) }}</span>
                <BookingStatusBadge v-else status="lunas" />
              </div>
            </div>
            <button v-if="booking.status === 'selesai'" class="btn-pill btn-secondary btn-pill-compact mt-3" :disabled="!canRequestReturnToRentalUnit(booking)" @click.stop="openReturnRequestDialog(booking)">
              <i class="pi pi-undo"></i>
              {{ booking.rental_unit_return_request?.status === 'pending' ? 'Menunggu Supervisor' : 'Request Rental Unit' }}
            </button>
          </div>
        </template>
        <!-- Mobile Paginator -->
        <div v-if="!loading" class="mobile-paginator mt-4">
          <Button icon="pi pi-chevron-left" :disabled="pagination.current_page === 1" @click="onPage({ page: pagination.current_page - 2 })" text />
          <span class="text-sm">Hal {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
          <Button icon="pi pi-chevron-right" :disabled="pagination.current_page === pagination.last_page" @click="onPage({ page: pagination.current_page })" text />
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
          <span class="calendar-search">
            <i class="pi pi-search"></i>
            <InputText v-model="calendarVehicleSearch" placeholder="Cari nopol / tipe" class="w-full" />
          </span>
          <Dropdown v-model="calendarOwnerFilter" :options="calendarOwnerOptions" optionLabel="nama" optionValue="id" placeholder="Semua Pemilik" showClear filter class="calendar-filter-dropdown" />
          <!-- Refresh button -->
          <button class="btn-pill btn-secondary btn-pill-compact" :disabled="calendarLoading" @click="loadData">
            <i class="pi pi-refresh" :class="{ 'pi-spin': calendarLoading }"></i>
            Refresh
          </button>
        </div>
      </div>

      <ProgressBar v-if="calendarLoading" mode="indeterminate" style="height: 4px" class="mb-4" />

      <div v-if="!calendarLoading && calendarUnits.length === 0" class="drent-empty-state">
        <i class="pi pi-info-circle text-4xl text-tertiary mb-3"></i>
        <p class="text-secondary">Tidak ada unit dengan transaksi pada periode ini.</p>
      </div>

      <BookingCalendar v-else-if="!calendarLoading" :bookings="calendarBookings" :units="visibleCalendarUnits" :startDate="calendarStart" @calendar-context="openCalendarContextMenu" />

      <div v-if="!calendarLoading && hasMoreCalendarUnits" class="calendar-load-more">
        <button class="btn-pill btn-secondary btn-pill-compact" @click="loadMoreCalendarUnits">
          <i class="pi pi-angle-down"></i>
          Muat {{ Math.min(50, calendarUnits.length - visibleCalendarUnits.length) }} unit lagi
        </button>
        <span class="text-tertiary text-xs">{{ visibleCalendarUnits.length }} dari {{ calendarUnits.length }}
          unit</span>
      </div>
    </div>

    <!-- Status Dialog -->
    <Dialog v-model:visible="showStatusDialog" header="Perbarui Status Booking" :style="{ width: '450px' }" modal :position="isMobile ? 'bottom' : 'center'" :class="{ 'mobile-bottom-sheet': isMobile }">
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
          <Dropdown v-model="newStatus" :options="getAllowedNextStatuses(selectedBooking?.status)" optionLabel="label" optionValue="value" placeholder="Pilih status..." class="w-full" />
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

    <Dialog v-model:visible="showReturnRequestDialog" header="Request Kembali ke Rental Unit" :style="{ width: '450px' }" modal :position="isMobile ? 'bottom' : 'center'" :class="{ 'mobile-bottom-sheet': isMobile }">
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
.create-label-mobile {
  display: none;
}

:deep(.booking-row-late) {
  background-color: rgba(229, 83, 75, 0.04) !important;
}

:deep(.booking-row-late td:first-child) {
  border-left: 3px solid var(--negative);
}

.late-note,
.late-banner-mini {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  max-width: 100%;
  border-radius: var(--radius-xs);
  background: rgba(229, 83, 75, 0.08);
  color: var(--negative);
  font-size: 10px;
  font-weight: 700;
  line-height: 1.2;
}

.late-note {
  gap: 5px;
  margin-top: 2px;
  padding: 4px 8px;
}

.late-note i {
  font-size: 10px;
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

.driver-cell-empty .driver-name {
  color: var(--text-secondary);
}

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

.info-row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-md);
}

.info-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-col .label {
  color: var(--text-tertiary);
  font-size: 10px;
  font-weight: 500;
}

.info-col .value {
  color: var(--text-primary);
  font-size: 12px;
  font-weight: 600;
}

.late-banner-mini {
  gap: 6px;
  padding: 4px 8px;
}

.card-footer {
  display: flex;
  justify-content: space-between;
  margin-top: var(--space-lg);
  padding-top: var(--space-md);
  border-top: 1px dashed var(--surface-border);
}

.amount-group {
  display: flex;
  flex-direction: column;
}

.booking-card-topline {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-sm);
}

.booking-card-code {
  color: var(--text-tertiary);
  font-family: var(--font-mono);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0;
  white-space: nowrap;
}

.booking-card-title-row {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}

.booking-card-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 34px;
  width: 34px;
  height: 34px;
  border-radius: var(--radius-default);
  background: var(--primary);
  color: var(--text-white);
}

.booking-card-title-block,
.booking-info-item>div {
  min-width: 0;
}

.booking-card-title-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.booking-card-title {
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 16px;
  font-weight: 800;
  line-height: 1.2;
}

.booking-card-subtitle {
  color: var(--text-tertiary);
  font-family: var(--font-mono);
  font-size: 10px;
  font-weight: 700;
}

.booking-card-body {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.booking-info-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: var(--space-md) var(--space-lg);
}

.booking-info-item {
  display: flex;
  align-items: flex-start;
  min-width: 0;
  gap: 7px;
}

.booking-info-item>i {
  flex: 0 0 auto;
  margin-top: 1px;
  color: var(--text-tertiary);
  font-size: 11px;
}

.booking-info-label {
  display: block;
  color: var(--text-tertiary);
  font-size: 10px;
  font-weight: 700;
  line-height: 1.2;
}

.booking-info-value {
  display: block;
  color: var(--text-primary);
  font-size: 12px;
  font-weight: 800;
  line-height: 1.25;
  overflow-wrap: anywhere;
}

.booking-info-value-success {
  color: var(--positive);
}

.booking-period-row {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-sm);
}

.booking-period-item {
  flex: 1 1 auto;
}

.booking-period-text {
  font-size: 11px;
}

.booking-duration-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  gap: 5px;
  min-height: 28px;
  padding: 5px 10px;
  border-radius: var(--radius-full);
  background: var(--primary);
  color: var(--text-white);
  font-size: 11px;
  font-weight: 800;
  white-space: nowrap;
}

.booking-duration-pill i {
  font-size: 10px;
}

.booking-card-footer {
  display: flex;
  justify-content: space-between;
  gap: var(--space-md);
  margin: var(--space-md) calc(var(--space-lg) * -1) calc(var(--space-lg) * -1);
  padding: var(--space-md) var(--space-lg);
  border-top: 1px solid var(--surface-border);
  background: var(--card-bg);
}

.booking-total-amount {
  color: var(--text-primary);
  font-size: 14px;
  font-weight: 900;
  line-height: 1.2;
}

.booking-paid-amount {
  color: var(--positive);
  font-size: 13px;
  font-weight: 900;
  line-height: 1.2;
}

.booking-remaining-amount {
  color: var(--warning);
  font-size: 11px;
  font-weight: 800;
  line-height: 1.2;
}

.mobile-paginator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-xl);
  color: var(--text-secondary);
}

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
    flex-wrap: nowrap;
    gap: 8px;
    padding: 10px;
    margin-bottom: var(--space-md);
    border-radius: var(--radius-default);
    height: auto !important;
    max-height: min(52dvh, 360px) !important;
    overflow-y: auto;
    overscroll-behavior: contain;
  }

  .filter-groups {
    width: 100%;
    flex-direction: column;
    flex-wrap: nowrap;
    gap: 8px;
  }

  .filter-group {
    width: 100%;
    max-width: none;
    flex: 0 0 auto;
    gap: 4px;
  }

  .filter-group-wide,
  .filter-group-status {
    min-width: 0;
    width: 100%;
    flex: 0 0 auto;
  }

  .filter-group label {
    margin-left: 4px;
    font-size: 10px;
  }

  .booking-filter-bar .filter-search :deep(.p-inputtext) {
    min-height: 36px !important;
  }

  .booking-filter-bar .advanced-filter-groups {
    gap: 8px;
    padding-top: 0;
  }

  .booking-filter-bar .advanced-filter-groups .filter-group {
    gap: 4px;
  }

  .booking-filter-bar .advanced-filter-groups :deep(.p-dropdown),
  .booking-filter-bar .advanced-filter-groups :deep(.p-datepicker),
  .booking-filter-bar .advanced-filter-groups :deep(.p-inputtext) {
    min-height: 36px !important;
  }

  .booking-filter-bar .status-filter-buttons {
    width: 100%;
    flex-wrap: nowrap;
    gap: 6px;
    overflow-x: auto;
    padding: 0 2px 2px;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
  }

  .booking-filter-bar .status-filter-buttons::-webkit-scrollbar {
    display: none;
  }

  .booking-filter-bar .status-filter-button {
    flex: 0 0 auto;
    min-height: 30px;
    padding: 6px 10px;
    white-space: nowrap;
  }

  .filter-group :deep(.p-dropdown),
  .filter-group :deep(.p-datepicker),
  .filter-group :deep(.p-inputtext) {
    width: 100%;
  }

  .filter-actions {
    width: 100%;
    justify-content: flex-end;
    flex-wrap: wrap;
  }

  .filter-bar .btn-pill {
    min-height: 30px;
    padding: 6px 12px;
  }

  .booking-filter-actions .btn-pill-compact {
    min-width: 36px;
  }

  .create-booking-button {
    position: fixed;
    right: var(--space-lg);
    bottom: calc(72px + env(safe-area-inset-bottom));
    z-index: 150;
    padding: 12px 16px;
    box-shadow: 0 12px 28px rgba(26, 29, 46, 0.22);
  }

  .create-label-desktop {
    display: none;
  }

  .create-label-mobile {
    display: inline;
  }

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
    overflow: hidden;
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

  .booking-card-completed {
    border-color: var(--text-secondary);
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

  .info-col .value {
    line-height: 1.25;
    overflow-wrap: anywhere;
  }

  .card-body .info-row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-sm);
  }

  .card-body .info-col {
    min-width: 48%;
  }

  .booking-card {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .booking-card-topline :deep(.status-badge) {
    padding: 5px 10px;
    font-size: 10px;
  }

  .booking-card-title {
    font-size: 15px;
  }

  .booking-card-footer {
    margin: var(--space-md) calc(var(--space-md) * -1) calc(var(--space-md) * -1);
    padding: var(--space-md);
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

.calendar-search {
  position: relative;
  display: inline-flex;
  align-items: center;
  min-width: 220px;
}

.calendar-search .pi {
  position: absolute;
  left: 10px;
  color: var(--text-tertiary);
  font-size: 12px;
  z-index: 1;
}

.calendar-search :deep(.p-inputtext) {
  min-height: 34px;
  padding-left: 30px;
  border-radius: var(--radius-default);
  border-color: var(--surface-border);
  font-size: 12px;
}

.calendar-filter-dropdown :deep(.p-select),
.calendar-filter-dropdown :deep(.p-dropdown) {
  min-height: 34px;
  border-radius: var(--radius-default);
  border-color: var(--surface-border);
  font-size: 12px;
}

.calendar-load-more {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  margin-top: var(--space-md);
}

/* Ensure HD desktop filter row alignment */
@media (min-width: 1024px) {
  .booking-filter-bar .filter-group-status {
    flex: 0 1 auto !important;
    min-width: 0 !important;
  }

  .booking-filter-bar .filter-group-city {
    flex: 0 0 160px !important;
  }
}
</style>
