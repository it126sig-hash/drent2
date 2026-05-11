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

const openCreateWithPreFill = ({ unitId, date }) => {
  router.push({
    path: '/bookings/create',
    query: { unit_id: unitId, tgl_sewa: date }
  });
};

const formatCurrency = (val) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
};

const formatDate = (val) => {
  if (!val) return '-';
  return format(new Date(val), 'dd MMM yyyy');
};

const rowClass = (data) => {
  return data.is_late ? 'booking-row-late' : null;
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
        >
          <Column field="kode_booking" header="Kode"></Column>
          <Column header="Pelanggan">
            <template #body="{ data }">
              <div class="flex flex-col">
                <span class="font-semibold">{{ data.customer.nama }}</span>
                <span class="text-xs text-gray-500">{{ data.customer.status }}</span>
              </div>
            </template>
          </Column>
          <Column header="Tgl Buat">
            <template #body="{ data }">
              {{ formatDate(data.created_at) }}
            </template>
          </Column>
          <Column header="Status">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <BookingStatusBadge :status="data.status" />
                <span
                  v-if="data.is_late"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-600 border border-rose-200 animate-pulse"
                >
                  <i class="pi pi-exclamation-triangle text-[9px]"></i> Terlambat
                </span>
              </div>
            </template>
          </Column>
          <Column header="Harga Dealing">
            <template #body="{ data }">
              {{ formatCurrency(data.harga_dealing) }}
            </template>
          </Column>
          <Column header="Aksi" :exportable="false" style="min-width: 12rem">
            <template #body="{ data }">
              <div class="flex gap-2">
                <Button icon="pi pi-eye" text rounded @click="goToDetail(data.id)" v-tooltip="'Lihat Detail'" />
                <Button 
                  icon="pi pi-sync" 
                  text 
                  rounded 
                  severity="help" 
                  @click="openStatusDialog(data)" 
                  v-tooltip="'Ubah Status'"
                  :disabled="['selesai', 'batal'].includes(data.status)"
                />
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
:deep(.booking-row-late) {
  background-color: #fff1f2 !important;
  border-left: 3px solid #f43f5e !important;
}

:deep(.booking-row-late:hover) {
  background-color: #ffe4e6 !important;
}
</style>
