<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useBooking } from '../../composables/useBooking';
import { useCustomer } from '../../composables/useCustomer';
import { useRentalOwner } from '../../composables/useRentalOwner';
import { useUnit } from '../../composables/useUnit';
import { usePaymentAccount } from '../../composables/usePaymentAccount';
import { useCity } from '../../composables/useCity';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Dropdown from 'primevue/dropdown';
import SelectButton from 'primevue/selectbutton';
import InputText from 'primevue/inputtext';
import Calendar from 'primevue/calendar';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import Message from 'primevue/message';

const router = useRouter();
const route = useRoute();
const toast = useToast();
const { store, fetchOne, updateBooking, loading: bookingLoading } = useBooking();
const { customers, fetchAll: fetchCustomers, loading: customersLoading } = useCustomer();
const { rentalOwners, fetchAll: fetchRentalOwners, loading: rentalOwnersLoading } = useRentalOwner();
const { units, fetchAll: fetchUnits, loading: unitsLoading } = useUnit();
const { cities, fetchAll: fetchCities, loading: citiesLoading } = useCity();

const { accounts: paymentAccounts, fetchAll: fetchAccounts } = usePaymentAccount();
const isEditMode = computed(() => route.name === 'BookingEdit');
const editingBooking = ref(null);
const suppressDurationSync = ref(false);

const paketOptions = [
  { label: 'Harian', value: 'harian' },
  { label: 'Mingguan', value: 'mingguan' },
  { label: 'Bulanan', value: 'bulanan' },
];

const lamaSewaOptions = Array.from({ length: 99 }, (_, index) => ({
  label: String(index + 1),
  value: index + 1,
}));

const newCustomerStatusOptions = [
  { label: 'Normal', value: 'Normal' },
  { label: 'Corporate', value: 'Corporate' },
];

const form = ref({
  customer_mode: 'existing',
  customer_id: null,
  customer_name: '',
  customer_phone: '',
  customer_email: '',
  customer_city: '',
  customer_status: 'Normal',

  unit_mode: 'existing',
  unit_id: null,
  unit_placeholder: '',

  tgl_sewa: null,
  tgl_kembali: null,
  lama_sewa: 1,
  paket_sewa: 'harian',
  tujuan: '',
  kota: '',
  alamat_penjemputan: '',
  harga_dealing: null,
  dp: null,
  rekening_dp_id: null,
  catatan: ''
});

const selectedStartDateKey = ref(null);
const selectedReturnDateKey = ref(null);

const selectedCustomer = computed(() => {
  if (form.value.customer_mode === 'existing' && form.value.customer_id) {
    return customerOptions.value.find(c => c.value === form.value.customer_id);
  }
  return null;
});

const selectedUnit = computed(() => {
  if (form.value.unit_mode === 'existing' && form.value.unit_id) {
    return unitOptions.value.find(u => u.id === form.value.unit_id);
  }
  return null;
});

const isBlacklisted = computed(() => selectedCustomer.value?.status === 'Blacklist');
const isRedflag = computed(() => selectedCustomer.value?.status === 'Redflag');

const accountOptions = computed(() =>
  paymentAccounts.value
    .filter(a => a.is_active)
    .map(a => ({ id: a.id, name: `${a.nama_bank} — ${a.nomor_rekening} (${a.atas_nama})` }))
);

onMounted(() => {
  fetchCustomers({ per_page: 200 });
  fetchRentalOwners({ per_page: 200 });
  fetchUnits({ per_page: 200 });
  fetchAccounts({ per_page: 100 });
  fetchCities({ per_page: 200, is_active: true });

  if (isEditMode.value) {
    loadBookingForEdit();
    return;
  }

  // Pre-fill from query parameters (from Calendar)
  if (route.query.unit_id) {
    form.value.unit_id = parseInt(route.query.unit_id);
    form.value.unit_mode = 'existing';
  }
  if (route.query.tgl_sewa) {
    const date = new Date(route.query.tgl_sewa);
    form.value.tgl_sewa = applyDefaultTime(date, 7, 0);
    selectedStartDateKey.value = getDateKey(date);
  }
});

const getPrimaryDetail = (booking) => {
  const details = booking?.booking_details || [];
  return details.find(detail => detail.detail_type === 'initial')
    || details.find(detail => detail.status === 'aktif')
    || details[0]
    || null;
};

const loadBookingForEdit = async () => {
  try {
    const booking = await fetchOne(route.params.id);
    const detail = getPrimaryDetail(booking);

    editingBooking.value = booking;
    suppressDurationSync.value = true;
    form.value = {
      customer_mode: 'existing',
      customer_id: booking.customer?.id ? `customer:${booking.customer.id}` : null,
      customer_name: booking.customer?.nama || '',
      customer_phone: '',
      customer_email: booking.customer?.email || '',
      customer_city: booking.customer?.kota || '',
      customer_status: booking.customer?.status || 'Normal',

      unit_mode: detail?.unit_id ? 'existing' : 'placeholder',
      unit_id: detail?.unit_id || null,
      unit_placeholder: detail?.unit_placeholder || '',

      tgl_sewa: detail?.tgl_sewa ? new Date(detail.tgl_sewa) : null,
      tgl_kembali: detail?.tgl_kembali ? new Date(detail.tgl_kembali) : null,
      lama_sewa: detail?.lama_sewa || booking.lama_sewa || 1,
      paket_sewa: detail?.paket_sewa || booking.paket_sewa || 'harian',
      tujuan: booking.tujuan || '',
      kota: booking.kota || '',
      alamat_penjemputan: booking.alamat_penjemputan || '',
      harga_dealing: booking.harga_dealing ?? null,
      dp: booking.dp ?? null,
      rekening_dp_id: booking.rekening_dp_id ?? null,
      catatan: booking.catatan || ''
    };
    selectedStartDateKey.value = getDateKey(form.value.tgl_sewa);
    selectedReturnDateKey.value = getDateKey(form.value.tgl_kembali);
    suppressDurationSync.value = false;
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: 'Gagal mengambil data booking', life: 5000 });
    router.push({ name: 'BookingList' });
  }
};

const handleSubmit = async () => {
  if (isBlacklisted.value) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Pelanggan diblacklist. Tidak bisa membuat booking.', life: 3000 });
    return;
  }

  // Validation: tgl_kembali cannot be less than tgl_sewa
  if (form.value.tgl_sewa && form.value.tgl_kembali) {
    if (new Date(form.value.tgl_kembali) < new Date(form.value.tgl_sewa)) {
      toast.add({ severity: 'warn', summary: 'Validasi', detail: 'Tanggal kembali tidak boleh kurang dari tanggal sewa', life: 3000 });
      return;
    }
  }

  try {
    const payload = { ...form.value };
    
    // Cleanup payload based on modes
    if (payload.customer_mode === 'existing') {
      const selectedOption = customerOptions.value.find(c => c.value === payload.customer_id);

      if (selectedOption?.source === 'rental_owner') {
        payload.rental_owner_id = selectedOption.rental_owner_id;
        delete payload.customer_id;
      } else if (selectedOption?.source === 'customer') {
        payload.customer_id = selectedOption.id;
        delete payload.rental_owner_id;
      }

      delete payload.customer_name;
      delete payload.customer_phone;
      delete payload.customer_email;
      delete payload.customer_city;
      delete payload.customer_status;
    } else {
      delete payload.customer_id;
      delete payload.rental_owner_id;
    }
    
    if (payload.unit_mode === 'existing') {
      delete payload.unit_placeholder;
    } else {
      delete payload.unit_id;
    }

    // Format dates to YYYY-MM-DD HH:mm:ss
    if (payload.tgl_sewa) payload.tgl_sewa = formatDateTime(payload.tgl_sewa);
    if (payload.tgl_kembali) payload.tgl_kembali = formatDateTime(payload.tgl_kembali);

    // Hapus field tidak relevan
    delete payload.customer_mode;
    delete payload.unit_mode;

    if (isEditMode.value) {
      delete payload.dp;
      delete payload.rekening_dp_id;

      const booking = await updateBooking(route.params.id, payload);
      toast.add({ severity: 'success', summary: 'Sukses', detail: `Booking ${booking.kode_booking} berhasil diperbarui`, life: 3000 });
      router.push({ name: 'BookingDetail', params: { id: route.params.id } });
      return;
    }

    if (!payload.dp || payload.dp <= 0) {
      payload.dp = null;
      payload.rekening_dp_id = null;
    }

    const booking = await store(payload);
    toast.add({ severity: 'success', summary: 'Sukses', detail: `Booking ${booking.kode_booking} berhasil dibuat`, life: 3000 });
    router.push({ name: 'BookingList' });
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || `Gagal ${isEditMode.value ? 'memperbarui' : 'membuat'} booking`, life: 5000 });
  }
};

const formatDateTime = (date) => {
  if (!date) return null;
  const d = new Date(date);
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  const hours = String(d.getHours()).padStart(2, '0');
  const minutes = String(d.getMinutes()).padStart(2, '0');
  const seconds = String(d.getSeconds()).padStart(2, '0');

  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
};

const applyDefaultTime = (date, hour, minute) => {
  if (!date) return null;
  const nextDate = new Date(date);
  nextDate.setHours(hour, minute, 0, 0);
  return nextDate;
};

const addRentalDuration = (startDate, duration, packageType) => {
  if (!startDate || !duration) return null;

  const nextDate = new Date(startDate);
  const amount = Number(duration);

  if (packageType === 'mingguan') {
    nextDate.setDate(nextDate.getDate() + (amount * 7) - 1);
  } else if (packageType === 'bulanan') {
    nextDate.setMonth(nextDate.getMonth() + amount);
    nextDate.setDate(nextDate.getDate() - 1);
  } else {
    nextDate.setDate(nextDate.getDate() + amount - 1);
  }

  return applyDefaultTime(nextDate, 23, 59);
};

const syncReturnDateFromDuration = () => {
  const returnDate = addRentalDuration(form.value.tgl_sewa, form.value.lama_sewa, form.value.paket_sewa);
  if (!returnDate) return;

  form.value.tgl_kembali = returnDate;
  selectedReturnDateKey.value = getDateKey(returnDate);
};

const getDateKey = (date) => {
  if (!date) return null;
  const nextDate = new Date(date);
  return [
    nextDate.getFullYear(),
    String(nextDate.getMonth() + 1).padStart(2, '0'),
    String(nextDate.getDate()).padStart(2, '0'),
  ].join('-');
};

const setDefaultStartTime = (date) => {
  const dateKey = getDateKey(date);
  if (dateKey && dateKey !== selectedStartDateKey.value) {
    form.value.tgl_sewa = applyDefaultTime(date, 7, 0);
    selectedStartDateKey.value = dateKey;
    syncReturnDateFromDuration();
  }
};

const setDefaultReturnTime = (date) => {
  const dateKey = getDateKey(date);
  if (dateKey && dateKey !== selectedReturnDateKey.value) {
    form.value.tgl_kembali = applyDefaultTime(date, 23, 59);
    selectedReturnDateKey.value = dateKey;
  }
};

watch(
  () => [form.value.tgl_sewa, form.value.lama_sewa, form.value.paket_sewa],
  () => {
    if (suppressDurationSync.value) return;
    syncReturnDateFromDuration();
  }
);

const resetForm = () => {
  form.value = {
    customer_mode: 'existing',
    customer_id: null,
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    customer_city: '',
    customer_status: 'Normal',
    unit_mode: 'existing',
    unit_id: null,
    unit_placeholder: '',
    tgl_sewa: null,
    tgl_kembali: null,
    lama_sewa: 1,
    paket_sewa: 'harian',
    tujuan: '',
    kota: '',
    alamat_penjemputan: '',
    harga_dealing: null,
    dp: null,
    rekening_dp_id: null,
    catatan: ''
  };
  selectedStartDateKey.value = null;
  selectedReturnDateKey.value = null;
};

const getStatusSeverity = (status) => {
  switch (status) {
    case 'Blacklist': return 'danger';
    case 'Redflag': return 'warning';
    case 'Corporate': return 'help';
    case 'Rent to Rent': return 'secondary';
    case 'Member': return 'info';
    case 'Normal': return 'success';
    default: return 'info';
  }
};

const normalizeSearch = (value) => {
  return String(value || '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();
};

const unitStatusMeta = (status) => {
  const map = {
    Aktif: { label: 'Available', severity: 'success' },
    Out: { label: 'Out', severity: 'warning' },
    'Dalam Servis': { label: 'Service', severity: 'danger' },
    'Tidak Aktif': { label: 'Inactive', severity: 'secondary' },
  };
  return map[status] || { label: status || '-', severity: 'info' };
};

const unitOptions = computed(() => {
    return units.value.map(u => ({
        ...u,
        label: `${u.merk} ${u.tipe}`,
        sublabel: `${u.no_polisi} - ${u.rental_owner?.nama || 'N/A'}`,
        disabled: u.status !== 'Aktif',
        searchableLabel: [
          u.merk,
          u.tipe,
          u.rental_owner?.nama,
          u.no_polisi,
          u.no_polisi,
          u.rental_owner?.nama,
          u.tipe,
          u.merk,
          unitStatusMeta(u.status).label,
        ].filter(Boolean).join(' '),
        normalizedSearchableLabel: normalizeSearch([
          u.merk,
          u.tipe,
          u.rental_owner?.nama,
          u.no_polisi,
          unitStatusMeta(u.status).label,
        ].filter(Boolean).join(' '))
    }));
});

const cityOptions = computed(() =>
  cities.value
    .filter(city => city.is_active)
    .map(city => ({
      label: city.provinsi ? `${city.nama} - ${city.provinsi}` : city.nama,
      value: city.nama,
      searchableLabel: [city.nama, city.provinsi].filter(Boolean).join(' ')
    }))
);

const customerOptions = computed(() => {
    const customerItems = customers.value.map(c => ({
        id: c.id,
        value: `customer:${c.id}`,
        source: 'customer',
        sourceLabel: 'Pelanggan',
        name: `${c.nama} - ${c.kota || '-'}`,
        nama: c.nama,
        kota: c.kota || '-',
        kontak_1: c.kontak_1 || '-',
        email: c.email || '-',
        status: c.status || 'Normal',
        catatan: c.catatan || '',
        member_expired_at: c.member_expired_at || null,
        searchableLabel: [
          c.nama,
          c.kota,
          c.kontak_1,
          c.email,
          c.status,
          c.catatan,
          c.member_expired_at,
          'pelanggan',
        ].filter(Boolean).join(' ')
    }));

    const rentalOwnerItems = rentalOwners.value.map(owner => ({
        id: `owner-${owner.id}`,
        value: `rental-owner:${owner.id}`,
        source: 'rental_owner',
        sourceLabel: 'Pemilik Rental',
        rental_owner_id: owner.id,
        name: `${owner.nama} - ${owner.kota || '-'}`,
        nama: owner.nama,
        kota: owner.kota || '-',
        kontak_1: owner.kontak_1 || '-',
        email: '-',
        status: 'Rent to Rent',
        catatan: owner.alamat || '',
        member_expired_at: null,
        searchableLabel: [
          owner.nama,
          owner.kota,
          owner.kontak_1,
          owner.alamat,
          'Rent to Rent',
          'pemilik rental',
        ].filter(Boolean).join(' ')
    }));

    return [...customerItems, ...rentalOwnerItems];
});

const selectedDurationLabel = computed(() => {
  const paket = paketOptions.find(option => option.value === form.value.paket_sewa)?.label || '-';
  return `${form.value.lama_sewa || 0} ${paket}`;
});

const formatCurrency = (value) => {
  if (!value) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(value);
};

const formatDate = (value) => {
  if (!value) return '-';
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(value));
};

</script>

<template>
  <div class="booking-create-container">
     <div class="detail-page-header mb-3">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" text rounded @click="router.back()" class="back-button" />
        <div>
          <div class="flex flex-wrap items-center gap-3 mb-1">
            <h1 class="booking-page-title">{{ isEditMode ? 'Edit Booking' : 'Buat Booking Baru' }}</h1>  
          </div>

        </div>
      </div>
    </div>
    <div class="booking-layout-grid">
      <div class="booking-form-column">
        <Card class="premium-card">
          <template #title>
            <div class="section-title">
              <i class="pi pi-user text-tosca"></i>
              <span>Pelanggan</span>
            </div>
          </template>
          <template #content>
            <div class="section-stack">
              <Message v-if="isEditMode" severity="info" icon="pi pi-lock" class="!m-0">
                Konsumen dikunci saat edit booking.
              </Message>

              <SelectButton
                v-if="!isEditMode"
                v-model="form.customer_mode"
                :options="[{label: 'Pelanggan Lama', value: 'existing'}, {label: 'Pelanggan Baru', value: 'new'}]"
                optionLabel="label"
                optionValue="value"
                class="w-full custom-selectbutton"
              />

              <div v-if="form.customer_mode === 'existing'" class="form-field-vertical">
                <label class="field-label">Cari pelanggan</label>
                <Dropdown
                  v-model="form.customer_id"
                  :options="customerOptions"
                  optionLabel="searchableLabel"
                  optionValue="value"
                  placeholder="Nama, kota, nomor, email, atau status..."
                  filter
                  :filterFields="['searchableLabel']"
                  :loading="customersLoading || rentalOwnersLoading"
                  :disabled="isEditMode"
                  class="w-full premium-input"
                >
                  <template #value="slotProps">
                    <div v-if="slotProps.value" class="selected-inline">
                      <span class="option-title">{{ customerOptions.find(c => c.value === slotProps.value)?.nama }}</span>
                      <Tag
                        v-if="customerOptions.find(c => c.value === slotProps.value)"
                        :value="customerOptions.find(c => c.value === slotProps.value)?.status"
                        :severity="getStatusSeverity(customerOptions.find(c => c.value === slotProps.value)?.status)"
                        class="premium-tag"
                      />
                    </div>
                    <span v-else>{{ slotProps.placeholder }}</span>
                  </template>
                  <template #option="slotProps">
                    <div class="option-row">
                      <div class="flex min-w-0 flex-col">
                        <span class="option-title truncate">{{ slotProps.option.nama }}</span>
                        <span class="option-meta truncate">{{ slotProps.option.kontak_1 }} - {{ slotProps.option.email }} - {{ slotProps.option.kota }}</span>
                        <span v-if="slotProps.option.status === 'Member' && slotProps.option.member_expired_at" class="option-positive truncate">
                          Exp {{ formatDate(slotProps.option.member_expired_at) }}
                        </span>
                        <span v-if="slotProps.option.status === 'Redflag' && slotProps.option.catatan" class="option-warning truncate">
                          {{ slotProps.option.catatan }}
                        </span>
                      </div>
                      <div class="flex shrink-0 flex-col items-end gap-1">
                        <Tag :value="slotProps.option.status" :severity="getStatusSeverity(slotProps.option.status)" class="premium-tag" />
                        <span class="option-source">{{ slotProps.option.sourceLabel }}</span>
                      </div>
                    </div>
                  </template>
                </Dropdown>

                <transition name="fade">
                  <div v-if="selectedCustomer" class="preview-panel">
                    <div class="preview-heading">
                      <span>{{ selectedCustomer.nama }}</span>
                      <Tag :value="selectedCustomer.status" :severity="getStatusSeverity(selectedCustomer.status)" class="premium-tag" />
                    </div>
                    <div class="info-grid">
                      <span>Kontak</span>
                      <strong>{{ selectedCustomer.kontak_1 || '-' }}</strong>
                      <span>Email</span>
                      <strong>{{ selectedCustomer.email || '-' }}</strong>
                      <span>Kota</span>
                      <strong>{{ selectedCustomer.kota || '-' }}</strong>
                      <span>Sumber</span>
                      <strong>{{ selectedCustomer.sourceLabel || '-' }}</strong>
                      <template v-if="selectedCustomer.status === 'Member'">
                        <span>Exp member</span>
                        <strong>{{ formatDate(selectedCustomer.member_expired_at) }}</strong>
                      </template>
                      <template v-if="isRedflag && selectedCustomer.catatan">
                        <span>Catatan</span>
                        <strong>{{ selectedCustomer.catatan }}</strong>
                      </template>
                    </div>

                    <Message v-if="isBlacklisted" severity="error" icon="pi pi-ban" class="mt-4 !m-0">
                      Blokir: Pelanggan terdaftar dalam Blacklist.
                    </Message>
                    <Message v-if="isRedflag" severity="warn" icon="pi pi-exclamation-triangle" class="mt-4 !m-0">
                      Peringatan: Pelanggan memiliki catatan Redflag<span v-if="selectedCustomer.catatan"> - {{ selectedCustomer.catatan }}</span>.
                    </Message>
                  </div>
                </transition>
              </div>

              <div v-else-if="!isEditMode" class="new-customer-form grid grid-cols-1 md:grid-cols-2 gap-3 animate-fade-in">
                <div class="form-field-vertical">
                  <label class="field-label">Nama lengkap *</label>
                  <InputText v-model="form.customer_name" placeholder="Nama pelanggan" class="w-full premium-input" />
                </div>
                <div class="form-field-vertical">
                  <label class="field-label">Nomor WhatsApp *</label>
                  <InputText v-model="form.customer_phone" placeholder="08xxxxxxxxxx" class="w-full premium-input" />
                </div>
                <div class="form-field-vertical">
                  <label class="field-label">Email</label>
                  <InputText v-model="form.customer_email" type="email" placeholder="nama@email.com" class="w-full premium-input" />
                </div>
                <div class="form-field-vertical">
                  <label class="field-label">Status pelanggan *</label>
                  <Dropdown
                    v-model="form.customer_status"
                    :options="newCustomerStatusOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Pilih status"
                    class="w-full premium-input"
                  />
                </div>
                <div class="form-field-vertical md:col-span-2">
                  <label class="field-label">Asal kota *</label>
                  <Dropdown
                    v-model="form.customer_city"
                    :options="cityOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Pilih kota pelanggan"
                    filter
                    :filterFields="['searchableLabel']"
                    :loading="citiesLoading"
                    class="w-full premium-input"
                    :empty-message="'Belum ada kota aktif'"
                  />
                </div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="premium-card">
          <template #title>
            <div class="section-title">
              <i class="pi pi-car text-tosca"></i>
              <span>Unit Kendaraan</span>
            </div>
          </template>
          <template #content>
            <div class="section-stack">
              <SelectButton
                v-model="form.unit_mode"
                :options="[{label: 'Unit Ready', value: 'existing'}, {label: 'Placeholder', value: 'placeholder'}]"
                optionLabel="label"
                optionValue="value"
                class="w-full custom-selectbutton"
              />

              <div v-if="form.unit_mode === 'existing'" class="form-field-vertical">
                <label class="field-label">Cari unit ready</label>
                <Dropdown
                  v-model="form.unit_id"
                  :options="unitOptions"
                  optionLabel="searchableLabel"
                  optionValue="id"
                  optionDisabled="disabled"
                  placeholder="Cari mobil, nopol, pemilik, atau status..."
                  filter
                  :filterFields="['searchableLabel', 'normalizedSearchableLabel']"
                  :loading="unitsLoading"
                  class="w-full premium-input"
                >
                  <template #value="slotProps">
                    <div v-if="slotProps.value" class="selected-inline">
                      <span class="option-title">{{ unitOptions.find(u => u.id === slotProps.value)?.label }}</span>
                      <Tag
                        v-if="unitOptions.find(u => u.id === slotProps.value)"
                        :value="unitStatusMeta(unitOptions.find(u => u.id === slotProps.value)?.status).label"
                        :severity="unitStatusMeta(unitOptions.find(u => u.id === slotProps.value)?.status).severity"
                        class="premium-tag"
                      />
                    </div>
                    <span v-else>{{ slotProps.placeholder }}</span>
                  </template>
                  <template #option="slotProps">
                    <div class="option-row">
                      <div class="flex min-w-0 flex-col">
                        <span class="option-title truncate">{{ slotProps.option.label }}</span>
                        <span class="option-meta truncate">{{ slotProps.option.sublabel }}</span>
                      </div>
                      <Tag
                        :value="unitStatusMeta(slotProps.option.status).label"
                        :severity="unitStatusMeta(slotProps.option.status).severity"
                        class="premium-tag shrink-0"
                      />
                    </div>
                  </template>
                </Dropdown>

                <transition name="fade">
                  <div v-if="selectedUnit" class="preview-panel">
                    <div class="preview-heading">
                      <span>{{ selectedUnit.label }}</span>
                      <Tag
                        :value="unitStatusMeta(selectedUnit.status).label"
                        :severity="unitStatusMeta(selectedUnit.status).severity"
                        class="premium-tag"
                      />
                    </div>
                    <div class="info-grid">
                      <span>No polisi</span>
                      <strong>{{ selectedUnit.no_polisi || '-' }}</strong>
                      <span>Pemilik</span>
                      <strong>{{ selectedUnit.rental_owner?.nama || 'N/A' }}</strong>
                      <span>Status</span>
                      <strong>{{ selectedUnit.status || '-' }}</strong>
                    </div>
                  </div>
                </transition>
              </div>

              <div v-else class="form-field-vertical animate-fade-in">
                <label class="field-label">Deskripsi unit sementara *</label>
                <InputText v-model="form.unit_placeholder" placeholder="Misal: Avanza Hitam Pak Budi" class="w-full premium-input" />
              </div>
            </div>
          </template>
        </Card>

        <Card class="premium-card">
          <template #title>
            <div class="section-title">
              <i class="pi pi-calendar text-tosca"></i>
              <span>Jadwal & Biaya</span>
            </div>
          </template>
          <template #content>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
              <div class="form-field-vertical">
                <label class="field-label">Mulai sewa *</label>
                <Calendar
                  v-model="form.tgl_sewa"
                  showIcon
                  showTime
                  hourFormat="24"
                  iconDisplay="input"
                  dateFormat="dd M yy"
                  :manualInput="true"
                  placeholder="Pilih tanggal & jam"
                  class="w-full premium-calendar"
                  @date-select="setDefaultStartTime"
                />
              </div>

              <div class="form-field-vertical">
                <label class="field-label">Selesai sewa *</label>
                <Calendar
                  v-model="form.tgl_kembali"
                  showIcon
                  showTime
                  hourFormat="24"
                  iconDisplay="input"
                  dateFormat="dd M yy"
                  :manualInput="true"
                  placeholder="Pilih tanggal & jam"
                  :minDate="form.tgl_sewa"
                  class="w-full premium-calendar"
                  @date-select="setDefaultReturnTime"
                />
              </div>

              <div class="form-field-vertical">
                <label class="field-label">Lama sewa *</label>
                <Dropdown
                  v-model="form.lama_sewa"
                  :options="lamaSewaOptions"
                  optionLabel="label"
                  optionValue="value"
                  placeholder="Pilih lama sewa"
                  filter
                  class="w-full premium-input"
                />
              </div>

              <div class="form-field-vertical">
                <label class="field-label">Paket sewa *</label>
                <Dropdown v-model="form.paket_sewa" :options="paketOptions" optionLabel="label" optionValue="value" placeholder="Pilih paket" class="w-full premium-input" />
              </div>

              <div class="form-field-vertical md:col-span-2">
                <label class="field-label">Tujuan</label>
                <InputText v-model="form.tujuan" placeholder="Ke luar kota / wisata / kantor..." class="w-full premium-input" />
              </div>

              <div class="form-field-vertical md:col-span-2">
                <label class="field-label">Kota booking</label>
                <Dropdown
                  v-model="form.kota"
                  :options="cityOptions"
                  optionLabel="label"
                  optionValue="value"
                  placeholder="Pilih kota tujuan/operasional"
                  filter
                  :filterFields="['searchableLabel']"
                  :loading="citiesLoading"
                  class="w-full premium-input"
                  :empty-message="'Belum ada kota aktif'"
                />
              </div>

              <div class="form-field-vertical md:col-span-2">
                <label class="field-label">Alamat jemput</label>
                <Textarea v-model="form.alamat_penjemputan" rows="2" placeholder="Input alamat lengkap penjemputan..." class="w-full premium-input" />
              </div>

              <div class="form-field-vertical">
                <label class="field-label">Harga dealing</label>
                <InputNumber v-model="form.harga_dealing" mode="currency" currency="IDR" locale="id-ID" placeholder="Rp 0" class="w-full premium-input" />
              </div>

              <div class="form-field-vertical">
                <label class="field-label">Uang muka (DP)</label>
                <InputNumber v-model="form.dp" mode="currency" currency="IDR" locale="id-ID" placeholder="Rp 0" class="w-full premium-input" :disabled="isEditMode" />
              </div>

              <transition name="slide-up">
                <div v-if="form.dp > 0" class="form-field-vertical md:col-span-2 payment-account-panel animate-slide-up">
                  <label class="field-label">Rekening DP *</label>
                  <Dropdown
                    v-model="form.rekening_dp_id"
                    :options="accountOptions"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Pilih akun pembayaran"
                    class="w-full premium-input"
                    :disabled="isEditMode"
                    :empty-message="'Belum ada akun pembayaran aktif'"
                  />
                  <Message v-if="isEditMode" severity="info" icon="pi pi-lock" class="!m-0">
                    DP dan pembayaran dikunci saat edit booking. Perubahan pembayaran dilakukan dari menu pembayaran di detail booking.
                  </Message>
                </div>
              </transition>

              <div class="form-field-vertical md:col-span-2">
                <label class="field-label">Catatan</label>
                <Textarea v-model="form.catatan" rows="3" placeholder="Informasi tambahan jika ada..." class="w-full premium-input" />
              </div>
            </div>
          </template>
        </Card>
      </div>

      <aside class="booking-summary-column">
        <div class="summary-panel">
          <div class="summary-heading">
            <i class="pi pi-clipboard text-tosca"></i>
            <span>Ringkasan</span>
          </div>

          <div class="summary-kpis">
            <div class="summary-kpi">
              <span>Durasi</span>
              <strong>{{ selectedDurationLabel }}</strong>
            </div>
            <div class="summary-kpi">
              <span>Estimasi</span>
              <strong>{{ formatCurrency(form.harga_dealing) }}</strong>
            </div>
          </div>

          <div class="summary-list">
            <div>
              <span>Pelanggan</span>
              <strong>{{ selectedCustomer?.nama || form.customer_name || '-' }}</strong>
            </div>
            <div>
              <span>Status pelanggan</span>
              <Tag v-if="selectedCustomer" :value="selectedCustomer.status" :severity="getStatusSeverity(selectedCustomer.status)" class="premium-tag" />
              <Tag v-else-if="form.customer_mode === 'new'" :value="form.customer_status" :severity="getStatusSeverity(form.customer_status)" class="premium-tag" />
              <strong v-else>-</strong>
            </div>
            <div>
              <span>Kota pelanggan</span>
              <strong>{{ selectedCustomer?.kota || form.customer_city || '-' }}</strong>
            </div>
            <div>
              <span>Unit</span>
              <strong>{{ selectedUnit?.label || form.unit_placeholder || '-' }}</strong>
            </div>
            <div>
              <span>No polisi</span>
              <strong>{{ selectedUnit?.no_polisi || '-' }}</strong>
            </div>
            <div>
              <span>Pemilik</span>
              <strong>{{ selectedUnit?.rental_owner?.nama || '-' }}</strong>
            </div>
            <div>
              <span>Status unit</span>
              <Tag
                v-if="selectedUnit"
                :value="unitStatusMeta(selectedUnit.status).label"
                :severity="unitStatusMeta(selectedUnit.status).severity"
                class="premium-tag"
              />
              <strong v-else>-</strong>
            </div>
            <div>
              <span>Durasi</span>
              <strong>{{ selectedDurationLabel }}</strong>
            </div>
            <div>
              <span>Kota booking</span>
              <strong>{{ form.kota || '-' }}</strong>
            </div>
            <div>
              <span>Harga dealing</span>
              <strong class="numeric-value">{{ formatCurrency(form.harga_dealing) }}</strong>
            </div>
            <div>
              <span>DP</span>
              <strong class="numeric-value">{{ formatCurrency(form.dp) }}</strong>
            </div>
          </div>

          <div class="summary-actions">
            <Button v-if="!isEditMode" label="Reset" icon="pi pi-refresh" class="button-secondary" @click="resetForm" />
            <Button
              :label="isEditMode ? 'Simpan Perubahan' : 'Simpan Booking'"
              icon="pi pi-check"
              :loading="bookingLoading"
              @click="handleSubmit"
              :disabled="isBlacklisted"
              class="p-button-tosca"
            />
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.booking-create-container {
  max-width: 1240px;
  margin: 0 auto;
  padding: var(--space-md) var(--space-md) var(--space-2xl);
}

.booking-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-lg);
  margin-bottom: var(--space-lg);
  padding: var(--space-lg) var(--space-xl);
  background: var(--surface-default);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  box-shadow: var(--shadow-tile);
}

.booking-page-title {
  margin: 0;
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 20px;
  font-weight: 700;
  line-height: 1.25;
}

.booking-page-subtitle {
  margin: 4px 0 0;
  color: var(--text-secondary);
  font-size: 12px;
  line-height: 1.4;
}

.booking-layout-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: var(--space-lg);
  align-items: start;
}

.booking-form-column {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: var(--space-lg);
}

.booking-summary-column {
  min-width: 0;
}

.section-stack {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.premium-card {
  border-radius: var(--radius-default);
  border: 1px solid var(--surface-border);
  box-shadow: var(--shadow-tile);
  background: var(--surface-default);
}

:deep(.premium-card .p-card-body) {
  padding: 0;
}

:deep(.premium-card .p-card-title) {
  margin: 0;
  padding: var(--space-lg) var(--space-xl) var(--space-md);
  border-bottom: 1px solid var(--surface-border);
}

:deep(.premium-card .p-card-content) {
  padding: var(--space-lg) var(--space-xl) var(--space-xl);
}

.section-title {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 600;
  line-height: 1.3;
}

.section-title i,
.summary-heading i {
  display: inline-flex;
  width: 24px;
  height: 24px;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-full);
  background: #E1F4F6;
  color: #085A66;
  font-size: 12px;
}

.form-field-vertical {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.field-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0;
}

.text-tosca { color: #0D8091; }

:deep(.premium-input .p-inputtext), 
:deep(.premium-calendar .p-inputtext),
:deep(.premium-input.p-dropdown) {
  width: 100%;
  border-radius: var(--radius-default);
  padding: 8px 12px;
  border: 1px solid var(--surface-border);
  background: var(--surface-default);
  transition: all 0.2s;
  color: var(--text-primary);
  font-size: 13px;
  min-height: 38px;
}

:deep(.premium-input .p-inputtext:hover),
:deep(.premium-calendar .p-inputtext:hover),
:deep(.premium-input.p-dropdown:not(.p-disabled):hover) {
  background: var(--card-bg-hover);
  border-color: var(--neutral-4);
}

:deep(.premium-calendar .p-datepicker-trigger) {
  width: 38px;
  border-radius: 0 var(--radius-default) var(--radius-default) 0;
}

:deep(.premium-input.p-dropdown .p-dropdown-label) {
  padding: 0;
  line-height: 1.35;
}

:deep(.premium-input.p-dropdown .p-dropdown-trigger) {
  width: 2.35rem;
}

:deep(.p-button) {
  border-radius: var(--radius-full);
  font-size: 12px;
  font-weight: 600;
}

:deep(.p-message) {
  border-radius: var(--radius-default);
  font-size: 12px;
}

:deep(.premium-input .p-inputtext:focus),
:deep(.premium-input.p-dropdown.p-focus) {
  border-color: #0D8091;
  box-shadow: 0 0 0 3px rgba(13, 128, 145, 0.12);
}

:deep(.custom-selectbutton .p-button) {
  flex: 1;
  background: var(--card-bg);
  border: 1px solid var(--surface-border);
  color: var(--text-secondary);
  border-radius: var(--radius-full) !important;
  font-weight: 600;
  font-size: 12px;
  padding: 8px;
}

:deep(.custom-selectbutton .p-button:hover) {
  background: var(--card-bg-hover);
  color: var(--text-primary);
}

:deep(.custom-selectbutton .p-button.p-highlight) {
  background: var(--text-primary);
  border-color: var(--text-primary);
  color: white;
}

.selected-inline,
.option-row,
.preview-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
}

.option-title {
  color: var(--text-primary);
  font-size: 13px;
  font-weight: 700;
}

.option-meta,
.option-source {
  color: var(--text-secondary);
  font-size: 11px;
  line-height: 1.35;
}

.option-positive {
  color: #147239;
  font-size: 11px;
  line-height: 1.35;
}

.option-warning {
  color: #8C660A;
  font-size: 11px;
  line-height: 1.35;
}

.preview-panel {
  background: var(--card-bg);
  padding: var(--space-md);
  border-radius: var(--radius-default);
  border: 1px solid var(--surface-border);
}

.preview-heading {
  margin-bottom: 10px;
  color: var(--text-primary);
  font-weight: 700;
  font-size: 13px;
}

.info-grid,
.summary-list {
  display: grid;
  gap: 8px;
}

.info-grid {
  grid-template-columns: minmax(96px, 0.38fr) 1fr;
  font-size: 12px;
}

.info-grid span,
.summary-list span {
  color: var(--text-secondary);
}

.info-grid strong,
.summary-list strong {
  color: var(--text-primary);
  font-weight: 700;
  min-width: 0;
  text-align: right;
  overflow-wrap: anywhere;
}

.info-grid strong {
  text-align: left;
}

.payment-account-panel {
  background: var(--card-bg);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  padding: var(--space-md);
}

.summary-panel {
  position: sticky;
  top: var(--space-lg);
  padding: var(--space-lg);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.summary-heading {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  margin-bottom: var(--space-md);
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 600;
}

.summary-kpis {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-sm);
  margin-bottom: var(--space-md);
}

.summary-kpi {
  min-width: 0;
  padding: var(--space-md);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--card-bg);
}

.summary-kpi span {
  display: block;
  color: var(--text-secondary);
  font-size: 11px;
  line-height: 1.3;
}

.summary-kpi strong {
  display: block;
  margin-top: 3px;
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 700;
  line-height: 1.25;
  overflow-wrap: anywhere;
}

.summary-list > div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 36px;
  padding: 8px 10px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  font-size: 12px;
}

.summary-actions {
  display: flex;
  justify-content: stretch;
  gap: 8px;
  margin-top: 16px;
  border-top: 1px solid var(--surface-border);
  padding-top: 12px;
}

.summary-actions :deep(.p-button) {
  flex: 1;
  justify-content: center;
  min-height: 38px;
}

.premium-tag {
  border-radius: var(--radius-sm);
  padding: 4px 8px;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.3;
}

:deep(.premium-tag.p-tag-success) {
  background: #E6F6EC;
  color: #147239;
}

:deep(.premium-tag.p-tag-danger) {
  background: #FCEAE9;
  color: #B02A24;
}

:deep(.premium-tag.p-tag-warning) {
  background: #FDF4D9;
  color: #8C660A;
}

:deep(.premium-tag.p-tag-info),
:deep(.premium-tag.p-tag-help) {
  background: #E1F4F6;
  color: #085A66;
}

:deep(.premium-tag.p-tag-secondary) {
  background: #E4E8F3;
  color: #4A5060;
}

.option-source {
  color: var(--text-tertiary);
  font-size: 10px;
  font-weight: 700;
  line-height: 1;
  text-transform: uppercase;
}

.animate-fade-in { animation: fadeIn 0.3s ease-out; }
.animate-slide-up { animation: slideUp 0.3s ease-out; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.p-button-tosca {
  background-color: var(--text-primary) !important;
  border-color: var(--text-primary) !important;
  color: white !important;
}

.p-button-tosca:hover {
  background-color: #2A2F46 !important;
  border-color: #2A2F46 !important;
}

.button-secondary {
  background: var(--surface-default) !important;
  border: 1px solid var(--surface-border) !important;
  color: var(--text-primary) !important;
}

.button-secondary:hover {
  background: var(--card-bg-hover) !important;
  border-color: var(--surface-border) !important;
}

.numeric-value {
  font-family: var(--font-mono);
  font-size: 13px;
  font-weight: 500;
}

:deep(.p-inputtext::placeholder) {
  color: var(--text-tertiary);
}

@media (max-width: 1024px) {
  .booking-layout-grid {
    grid-template-columns: 1fr;
  }

  .summary-panel {
    position: static;
  }
}

@media (max-width: 767px) {
  .booking-create-container {
    padding: var(--space-sm);
  }

  .booking-page-header {
    align-items: flex-start;
    flex-direction: column;
    padding: var(--space-md);
  }

  .summary-kpis {
    grid-template-columns: 1fr;
  }

  .summary-actions {
    flex-direction: column-reverse;
  }
}
</style>
