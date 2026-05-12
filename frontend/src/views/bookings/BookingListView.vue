<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useBooking } from '../../composables/useBooking';
import { useUnit } from '../../composables/useUnit';
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue';
import BookingCalendar from '../../components/bookings/BookingCalendar.vue';
import { format, addMonths, subMonths, startOfMonth } from 'date-fns';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import Toolbar from 'primevue/toolbar';
import Dropdown from 'primevue/dropdown';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import ProgressBar from 'primevue/progressbar';
import ContextMenu from 'primevue/contextmenu';
import Tag from 'primevue/tag';

const router = useRouter();
const { 
  bookings, loading, pagination, filters, 
  fetchAll, fetchForCalendar, changeStatus, statusLoading 
} = useBooking();

const { units, loading: unitsLoading, fetchAll: fetchUnits } = useUnit();

const activeTab = ref(0);
const calendarStart = ref(format(startOfMonth(new Date()), 'yyyy-MM-dd'));
const calendarBookings = ref([]);

const showStatusDialog = ref(false);
const selectedBooking = ref(null);
const newStatus = ref('');
const statusNote = ref('');
const bookingContextMenu = ref(null);
const contextMenuSelection = ref(null);
const contextMenuItems = ref([]);

const statusOptions = [
  { label: 'Follow Up', value: 'follow_up' },
  { label: 'Confirm', value: 'confirm' },
  { label: 'Waiting List', value: 'waiting_list' },
  { label: 'Rental Unit', value: 'rental_unit' },
  { label: 'Selesai', value: 'selesai' },
  { label: 'Batal', value: 'batal' },
];

const loadData = async () => {
  if (activeTab.value === 0) {
    await fetchAll(pagination.value.current_page);
  } else {
    await fetchUnits({ per_page: 100 }); // Get all units for calendar
    const endDate = format(addMonths(new Date(calendarStart.value), 1), 'yyyy-MM-dd');
    calendarBookings.value = await fetchForCalendar(calendarStart.value, endDate);
  }
};

onMounted(loadData);

watch(activeTab, loadData);

const onPage = (event) => {
  pagination.value.current_page = event.page + 1;
  fetchAll(pagination.value.current_page);
};

const applyFilters = () => {
  pagination.value.current_page = 1;
  fetchAll(1);
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
  const lamaSewa = detail?.lama_sewa ?? booking?.lama_sewa;
  const paketSewa = detail?.paket_sewa ?? booking?.paket_sewa;

  if (!lamaSewa && !paketSewa) return '-';
  return `${lamaSewa || '-'} x ${formatPackage(paketSewa)}`;
};

const getPaidAmount = (booking) => {
  if (booking?.total_payments != null) return booking.total_payments;
  return (booking?.payments || []).reduce((sum, payment) => sum + (payment.amount || 0), 0);
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
</script>

<template>
  <div class="p-4">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">Manajemen Booking</h1>
      <Button label="Buat Booking" icon="pi pi-plus" @click="router.push('/bookings/create')" />
    </div>

    <TabView v-model:activeIndex="activeTab">
      <TabPanel header="Daftar Booking">
        <Toolbar class="mb-4">
          <template #start>
            <div class="flex flex-wrap gap-3 items-end">
              <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-500">Status</label>
                <Dropdown v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Semua Status" showClear class="w-48" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-500">Mulai</label>
                <DatePicker v-model="filters.date_from" dateFormat="yy-mm-dd" placeholder="Dari Tanggal" class="w-40" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-500">Sampai</label>
                <DatePicker v-model="filters.date_to" dateFormat="yy-mm-dd" placeholder="Sampai Tanggal" class="w-40" />
              </div>
              <Button icon="pi pi-filter" label="Filter" @click="applyFilters" :loading="loading" />
            </div>
          </template>
        </Toolbar>

        <ContextMenu ref="bookingContextMenu" :model="contextMenuItems" />

        <DataTable 
          :value="bookings" 
          lazy 
          paginator 
          :rows="pagination.per_page" 
          :totalRecords="pagination.total" 
          @page="onPage"
          :loading="loading"
          class="p-datatable-sm"
          responsiveLayout="scroll"
          :rowClass="rowClass"
          v-model:contextMenuSelection="contextMenuSelection"
          contextMenu
          @row-dblclick="onRowDoubleClick"
          @row-contextmenu="onRowContextMenu"
        >
          <Column header="Aksi" :exportable="false" style="min-width: 8rem">
            <template #body="{ data }">
              <div class="booking-actions">
                <Button
                  icon="pi pi-eye"
                  outlined
                  rounded
                  size="small"
                  severity="info"
                  @click.stop="goToDetail(data.id)"
                  v-tooltip="'Lihat Detail'"
                />
                <Button
                  icon="pi pi-sync"
                  outlined
                  rounded
                  size="small"
                  severity="help"
                  @click.stop="openStatusDialog(data)"
                  v-tooltip="'Ubah Status'"
                  :disabled="!canUpdateStatus(data)"
                />
              </div>
            </template>
          </Column>
          <Column header="Kode" style="min-width: 9rem">
            <template #body="{ data }">
              <div class="flex flex-col items-start gap-1">
                <span class="font-semibold whitespace-nowrap">{{ data.kode_booking }}</span>
                <div class="flex items-center gap-2">
                  <BookingStatusBadge :status="data.status" />
                  <Tag
                    v-if="getLateInfo(data)"
                    severity="danger"
                    :value="`Terlambat ${getLateInfo(data).label}`"
                    icon="pi pi-exclamation-triangle"
                    class="text-[10px]"
                  />
                </div>
              </div>
            </template>
          </Column>
          <Column header="Pelanggan">
            <template #body="{ data }">
              <div class="flex flex-col">
                <span class="font-semibold">{{ data.customer?.nama || '-' }}</span>
                <span class="text-xs text-gray-500">{{ data.customer?.status || '-' }}</span>
              </div>
            </template>
          </Column>
          <Column header="Kendaraan" style="min-width: 15rem">
            <template #body="{ data }">
              <div class="flex flex-col gap-1">
                <span class="font-semibold">{{ getVehicleInfo(data).title }}</span>
                <span class="text-xs text-gray-500">
                  {{ getVehicleInfo(data).subtitle }}
                </span>
                <div class="flex flex-wrap items-center gap-1.5">
                  <span v-if="getVehicleInfo(data).ownerName" class="text-xs text-gray-600">
                    <Tag
                    v-if="getVehicleInfo(data).ownerBadge"
                    :severity="getVehicleInfo(data).ownerSeverity"
                    :value="getVehicleInfo(data).ownerBadge"
                    class="text-[10px]"
                  > {{ getVehicleInfo(data).ownerName }} </Tag>
                  </span>
                  <Tag
                    v-if="getVehicleInfo(data).placeholder"
                    severity="danger"
                    value="Unit belum ditentukan"
                    icon="pi pi-exclamation-triangle"
                    class="!text-[10px]"
                  />
                </div>
                <span v-if="getExtraDetailCount(data)" class="text-[10px] font-semibold text-blue-600 mt-1">
                  +{{ getExtraDetailCount(data) }} detail lain
                </span>
              </div>
            </template>
          </Column>
          <Column header="Periode Sewa" style="min-width: 13rem">
            <template #body="{ data }">
              <div class="flex flex-col gap-1">
                <span class="font-medium">{{ formatDateTime(getDisplayDetail(data)?.tgl_sewa) }}</span>
                <span class="text-xs text-gray-500">s/d {{ formatDateTime(getDisplayDetail(data)?.tgl_kembali) }}</span>
                <span class="text-xs font-semibold text-gray-700 mt-1">{{ getRentalDuration(data) }}</span>
                <Tag
                  v-if="getLateInfo(data)"
                  severity="danger"
                  :value="`Lewat ${getLateInfo(data).label}`"
                  icon="pi pi-clock"
                  class="self-start text-[10px]"
                />
              </div>
            </template>
          </Column>
          <Column header="Alamat Penjemputan" style="min-width: 14rem">
            <template #body="{ data }">
              <span class="text-sm text-gray-700">{{ data.alamat_penjemputan || '-' }}</span>
            </template>
          </Column>
          <Column header="Tujuan & Catatan" style="min-width: 14rem">
            <template #body="{ data }">
              <div class="flex flex-col gap-1">
                <span class="text-sm font-medium">{{ data.tujuan || '-' }}</span>
                <span class="text-xs text-gray-500 line-clamp-2">{{ data.catatan || '-' }}</span>
              </div>
            </template>
          </Column>
          <Column header="Harga Dealing">
            <template #body="{ data }">
              {{ formatCurrency(data.harga_dealing) }}
            </template>
          </Column>
          <Column header="Sudah Bayar">
            <template #body="{ data }">
              <span class="font-semibold text-emerald-600">{{ formatCurrency(getPaidAmount(data)) }}</span>
            </template>
          </Column>
          <Column header="Dibuat" style="min-width: 10rem">
            <template #body="{ data }">
              <div class="flex flex-col">
                <span>{{ formatDateTime(data.created_at) }}</span>
                <span class="text-xs text-gray-500">{{ data.created_by_user?.name || '-' }}</span>
              </div>
            </template>
          </Column>
        </DataTable>
      </TabPanel>

      <TabPanel header="Kalender Unit">
        <div class="flex justify-between items-center mb-4">
          <div class="flex items-center gap-4">
            <Button icon="pi pi-chevron-left" @click="prevMonth" text rounded />
            <h2 class="text-xl font-semibold">{{ format(new Date(calendarStart), 'MMMM yyyy') }}</h2>
            <Button icon="pi pi-chevron-right" @click="nextMonth" text rounded />
          </div>
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 italic">* Klik pada sel kosong untuk membuat booking baru pada unit dan tanggal tersebut</span>
          </div>
        </div>

        <ProgressBar v-if="unitsLoading" mode="indeterminate" style="height: 6px" class="mb-4" />

        <div v-if="!unitsLoading && units.length === 0" class="p-8 text-center bg-gray-50 rounded-lg border border-dashed">
          <i class="pi pi-info-circle text-4xl text-gray-400 mb-3"></i>
          <p class="text-gray-500">Tidak ada unit kendaraan yang tersedia untuk ditampilkan di kalender.</p>
        </div>

        <BookingCalendar 
          v-else
          :bookings="calendarBookings" 
          :units="units" 
          :startDate="calendarStart"
          @booking-click="goToDetail"
          @cell-click="openCreateWithPreFill"
        />
      </TabPanel>
    </TabView>

    <!-- Status Dialog -->
    <Dialog v-model:visible="showStatusDialog" header="Perbarui Status Booking" :style="{ width: '450px' }" modal>
      <div class="flex flex-col gap-4">
        <div v-if="selectedBooking" class="p-3 bg-gray-50 rounded border">
          <div class="flex justify-between text-sm">
            <span>Booking:</span>
            <span class="font-bold">{{ selectedBooking.kode_booking }}</span>
          </div>
          <div class="flex justify-between text-sm mt-1">
            <span>Status Saat Ini:</span>
            <BookingStatusBadge :status="selectedBooking.status" />
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="font-semibold">Pilih Status Baru</label>
          <Dropdown 
            v-model="newStatus" 
            :options="getAllowedNextStatuses(selectedBooking?.status)" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Pilih status..." 
            class="w-full"
          />
        </div>

        <div class="flex flex-col gap-2">
          <label class="font-semibold">Catatan Status (Opsional)</label>
          <Textarea v-model="statusNote" rows="3" class="w-full" />
        </div>
      </div>

      <template #footer>
        <Button label="Batal" icon="pi pi-times" text @click="showStatusDialog = false" />
        <Button label="Simpan Perubahan" icon="pi pi-check" @click="saveStatus" :loading="statusLoading" :disabled="!newStatus" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
:deep(.booking-row) {
  cursor: pointer;
  transition: background-color 0.15s ease, box-shadow 0.15s ease;
}

:deep(.booking-row:hover) {
  background-color: #f8fafc !important;
  box-shadow: inset 3px 0 0 #3b82f6;
}

:deep(.booking-row:hover > td) {
  background-color: transparent !important;
}

:deep(.booking-row-late) {
  background-color: #fff1f2 !important;
  border-left: 3px solid #f43f5e !important;
}

:deep(.booking-row-late:hover) {
  background-color: #ffe4e6 !important;
  box-shadow: inset 3px 0 0 #f43f5e;
}

.booking-actions {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.2rem;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}

.booking-actions :deep(.p-button) {
  width: 2rem;
  height: 2rem;
}
</style>
