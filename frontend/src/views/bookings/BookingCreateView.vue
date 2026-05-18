<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useBooking } from '../../composables/useBooking';
import { useCustomer } from '../../composables/useCustomer';
import { useRentalOwner } from '../../composables/useRentalOwner';
import { useUnit } from '../../composables/useUnit';
import { useDriver } from '../../composables/useDriver';
import { usePaymentAccount } from '../../composables/usePaymentAccount';
import { useCity } from '../../composables/useCity';
import { useCostType } from '../../composables/useCostType';
import { usePricingPackage } from '../../composables/usePricingPackage';
import { getUnits } from '../../api/unit';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Dialog from 'primevue/dialog';
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
const { store, fetchOne, updateBooking, handle, loading: bookingLoading } = useBooking();
const { customers, fetchAll: fetchCustomers, loading: customersLoading } = useCustomer();
const { rentalOwners, fetchAll: fetchRentalOwners, loading: rentalOwnersLoading } = useRentalOwner();
const { units, loading: unitsLoading } = useUnit();
const { drivers, fetchAll: fetchDrivers } = useDriver();
const { cities, fetchAll: fetchCities, loading: citiesLoading } = useCity();
const { costTypes: costTypesMaster, fetchAll: fetchCostTypes } = useCostType();
const { packages: pricingPackages, fetchAll: fetchPricingPackages, loading: pricingPackagesLoading } = usePricingPackage();

const { accounts: paymentAccounts, fetchAll: fetchAccounts } = usePaymentAccount();
const isEditMode = computed(() => route.name === 'BookingEdit');
const editingBooking = ref(null);
const selectedCustomerCache = ref(null);
const selectedUnitCache = ref(null);
const selectedPricingPackageCache = ref(null);
const suppressDurationSync = ref(false);
const showCreateModeDialog = ref(false);
const submitMode = ref('booking');
const formErrors = ref({});
const waitingListErrors = ref({});
const isSubmitting = ref(false);
const unitSearchLoading = ref(false);
const unitServerSearchTerm = ref('');
let customerSearchTimer = null;
let unitSearchTimer = null;
let pricingPackageSearchTimer = null;
let unitSearchRequestId = 0;

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

const createModeOptions = [
  {
    value: 'booking',
    title: 'Simpan sebagai Booking',
    icon: 'pi pi-bookmark',
    description: 'Booking masuk follow up atau confirm sesuai DP. Unit dan biaya operasional bisa dilengkapi nanti.',
  },
  {
    value: 'waiting_list',
    title: 'Langsung Waiting List',
    icon: 'pi pi-send',
    description: 'Booking langsung di-handle dengan unit ready, driver, harga, dan biaya operasional.',
  },
];

const pricingModeOptions = [
  { label: 'Non All In', value: 'non_all_in' },
  { label: 'All In', value: 'all_in' },
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
  dp_paid_at: new Date(),
  catatan: ''
});

const waitingListForm = ref({
  driver_id: null,
  harga_mobil: 0,
  diskon_mobil: 0,
  pricing_mode: 'non_all_in',
  pricing_package_id: null,
  harga_all_in: null,
  costs: [],
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
const isDirectWaitingList = computed(() => !isEditMode.value && submitMode.value === 'waiting_list');

const accountOptions = computed(() =>
  paymentAccounts.value
    .filter(a => a.is_active)
    .map(a => ({ id: a.id, name: `${a.nama_bank} — ${a.nomor_rekening} (${a.atas_nama})` }))
);

const costTypeOptions = computed(() =>
  costTypesMaster.value
    .filter(c => c.is_active)
    .map(c => ({ id: c.id, label: c.nama, kode: c.kode, require_description: c.require_description }))
);

const packageOptions = computed(() =>
  mergeSelectedOption(
    pricingPackages.value
      .filter(p => p.is_active)
      .map(mapPricingPackageOption),
    selectedPricingPackageCache.value,
    'id'
  )
);

const findPricingPackage = (packageId) =>
  pricingPackages.value.find(pkg => pkg.id === packageId)
  || (selectedPricingPackageCache.value?.id === packageId ? selectedPricingPackageCache.value : null);

const packageCostItems = (pkg) =>
  (pkg?.items || []).map(item => ({
    cost_type_id: item.cost_type_id ?? null,
    type: item.type || 'biaya',
    label: item.label || item.cost_type?.nama || '',
    amount: item.amount || 0,
    keterangan: item.keterangan || '',
  }));

const getPackageCostKey = (cost) => [
  cost.cost_type_id ?? '',
  cost.type || 'biaya',
  cost.label || '',
  cost.keterangan || '',
].join('|');

const getSignedCostAmount = (cost) => {
  const amount = cost?.amount || 0;
  return cost?.type === 'diskon' ? -amount : amount;
};

const sumCosts = (costs = [], { discountsOnly = false } = {}) => {
  return (costs || []).reduce((sum, cost) => {
    if (discountsOnly && cost?.type !== 'diskon') return sum;
    return sum + getSignedCostAmount(cost);
  }, 0);
};

const getBillableCostTotal = (pricingMode, costs = []) => {
  return sumCosts(costs, { discountsOnly: pricingMode === 'all_in' });
};

const waitingHargaSewa = computed(() => {
  const { harga_mobil, diskon_mobil } = waitingListForm.value;
  return Math.max(0, ((harga_mobil || 0) - (diskon_mobil || 0)) * (form.value.lama_sewa || 0));
});

const waitingTotalBiayaOps = computed(() =>
  getBillableCostTotal(waitingListForm.value.pricing_mode, waitingListForm.value.costs)
);

const waitingGrandTotalInternal = computed(() => waitingHargaSewa.value + waitingTotalBiayaOps.value);

const waitingTagihanKonsumen = computed(() => {
  if (waitingListForm.value.pricing_mode === 'all_in') {
    const lama = form.value.lama_sewa || 1;
    const selectedPackage = findPricingPackage(waitingListForm.value.pricing_package_id);
    return ((selectedPackage?.harga || waitingListForm.value.harga_all_in || 0) * lama) + waitingTotalBiayaOps.value;
  }

  return waitingGrandTotalInternal.value;
});

onMounted(() => {
  searchCustomers();
  searchUnits();
  fetchAccounts({ per_page: 100 });
  fetchCities({ per_page: 200, is_active: true });
  fetchDrivers({ per_page: 200 });
  fetchCostTypes({ per_page: 200 });
  searchPricingPackages();

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

  showCreateModeDialog.value = true;
});

const selectCreateMode = (mode) => {
  submitMode.value = mode;
  showCreateModeDialog.value = false;

  if (mode === 'waiting_list') {
    form.value.unit_mode = 'existing';
    if (selectedUnit.value) {
      waitingListForm.value.harga_mobil = selectedUnit.value.harga_1_hari || 0;
    }
  }
};

const getPrimaryDetail = (booking) => {
  const details = booking?.booking_details || [];
  return details.find(detail => detail.detail_type === 'initial')
    || details.find(detail => detail.status === 'aktif')
    || details[0]
    || null;
};

const getRentableDetails = (booking) => {
  return (booking?.booking_details || []).filter(detail => detail.status !== 'batal');
};

const getLatestDate = (details, field) => {
  return details
    .map(detail => detail?.[field])
    .filter(Boolean)
    .sort((a, b) => new Date(b) - new Date(a))[0] || null;
};

const getPeriodEndDate = (booking) => {
  const details = getRentableDetails(booking);
  return getLatestDate(details, 'tgl_kembali') || getPrimaryDetail(booking)?.tgl_kembali;
};

const loadBookingForEdit = async () => {
  try {
    const booking = await fetchOne(route.params.id);
    const detail = getPrimaryDetail(booking);
    const latestReturnDate = getPeriodEndDate(booking);

    editingBooking.value = booking;
    if (booking.customer?.id) {
      selectedCustomerCache.value = mapCustomerOption(booking.customer);
    }
    if (detail?.unit) {
      selectedUnitCache.value = mapUnitOption(detail.unit);
    }
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
      tgl_kembali: latestReturnDate ? new Date(latestReturnDate) : null,
      lama_sewa: detail?.lama_sewa || booking.lama_sewa || 1,
      paket_sewa: detail?.paket_sewa || booking.paket_sewa || 'harian',
      tujuan: booking.tujuan || '',
      kota: booking.kota || '',
      alamat_penjemputan: booking.alamat_penjemputan || '',
      harga_dealing: booking.harga_dealing ?? null,
      dp: booking.dp ?? null,
      rekening_dp_id: booking.rekening_dp_id ?? null,
      dp_paid_at: booking.payments?.find(payment => payment.payment_type === 'dp')?.paid_at
        ? new Date(booking.payments.find(payment => payment.payment_type === 'dp').paid_at)
        : new Date(),
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

const debounceSearch = (timerName, callback, delay = 350) => {
  if (timerName === 'customer' && customerSearchTimer) clearTimeout(customerSearchTimer);
  if (timerName === 'unit' && unitSearchTimer) clearTimeout(unitSearchTimer);
  if (timerName === 'pricingPackage' && pricingPackageSearchTimer) clearTimeout(pricingPackageSearchTimer);

  const timer = setTimeout(callback, delay);
  if (timerName === 'customer') customerSearchTimer = timer;
  if (timerName === 'unit') unitSearchTimer = timer;
  if (timerName === 'pricingPackage') pricingPackageSearchTimer = timer;
};

const searchCustomers = async (search = '') => {
  const params = { per_page: 25 };
  if (search) params.search = search;
  await Promise.all([
    fetchCustomers(params),
    fetchRentalOwners(params),
  ]);
};

const onCustomerFilter = (event) => {
  debounceSearch('customer', () => searchCustomers(String(event?.value || '').trim()));
};

const searchUnits = async (search = '') => {
  const requestId = ++unitSearchRequestId;
  const params = { per_page: 25 };
  if (search) params.search = search;
  unitSearchLoading.value = true;

  try {
    const response = await getUnits(params);
    if (requestId !== unitSearchRequestId) return;

    unitServerSearchTerm.value = search;
    units.value = response.data.data;
  } catch (err) {
    // Biarkan opsi terakhir tetap tampil saat request pencarian unit gagal sesaat.
  } finally {
    if (requestId === unitSearchRequestId) {
      unitSearchLoading.value = false;
    }
  }
};

const onUnitFilter = (event) => {
  debounceSearch('unit', () => searchUnits(String(event?.value || '').trim()));
};

const searchPricingPackages = async (search = '') => {
  const params = { per_page: 25, is_active: true };
  if (search) params.search = search;
  await fetchPricingPackages(params);
};

const onPricingPackageFilter = (event) => {
  debounceSearch('pricingPackage', () => searchPricingPackages(String(event?.value || '').trim()));
};

const handleSubmit = async () => {
  if (isSubmitting.value) return;

  if (isBlacklisted.value) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Pelanggan diblacklist. Tidak bisa membuat booking.', life: 3000 });
    return;
  }

  if (!validateBookingForm()) {
    return;
  }

  // Validation: tgl_kembali cannot be less than tgl_sewa
  if (form.value.tgl_sewa && form.value.tgl_kembali) {
    if (new Date(form.value.tgl_kembali) < new Date(form.value.tgl_sewa)) {
      toast.add({ severity: 'warn', summary: 'Validasi', detail: 'Tanggal kembali tidak boleh kurang dari tanggal sewa', life: 3000 });
      return;
    }
  }

  if (isDirectWaitingList.value && !validateWaitingListForm()) {
    return;
  }

  isSubmitting.value = true;

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

    if (isDirectWaitingList.value) {
      applyDirectWaitingListBookingDefaults(payload);
    }

    // Format dates to YYYY-MM-DD HH:mm:ss
    if (payload.tgl_sewa) payload.tgl_sewa = formatDateTime(payload.tgl_sewa);
    if (payload.tgl_kembali) payload.tgl_kembali = formatDateTime(payload.tgl_kembali);
    if (payload.dp_paid_at) payload.dp_paid_at = formatDateTime(payload.dp_paid_at);

    // Hapus field tidak relevan
    delete payload.customer_mode;
    delete payload.unit_mode;

    if (isEditMode.value) {
      delete payload.dp;
      delete payload.rekening_dp_id;
      delete payload.dp_paid_at;

      const booking = await updateBooking(route.params.id, payload);
      toast.add({ severity: 'success', summary: 'Sukses', detail: `Booking ${booking.kode_booking} berhasil diperbarui`, life: 3000 });
      router.push({ name: 'BookingDetail', params: { id: route.params.id } });
      return;
    }

    if (!payload.dp || payload.dp <= 0) {
      payload.dp = null;
      payload.rekening_dp_id = null;
      payload.dp_paid_at = null;
    }

    const booking = await store(payload);

    if (isDirectWaitingList.value) {
      await handle(booking.id, buildWaitingListPayload());
      router.push({ name: 'BookingDetail', params: { id: booking.id } });
      return;
    }

    toast.add({ severity: 'success', summary: 'Sukses', detail: `Booking ${booking.kode_booking} berhasil dibuat`, life: 3000 });
    router.push({ name: 'BookingList' });
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || `Gagal ${isEditMode.value ? 'memperbarui' : 'membuat'} booking`, life: 5000 });
  } finally {
    isSubmitting.value = false;
  }
};

const validateBookingForm = () => {
  formErrors.value = {};

  if (!String(form.value.kota || '').trim()) {
    formErrors.value.kota = 'Kota booking wajib diisi.';
  }

  if (!String(form.value.alamat_penjemputan || '').trim()) {
    formErrors.value.alamat_penjemputan = 'Alamat penjemputan wajib diisi.';
  }

  if (!isDirectWaitingList.value && (!form.value.harga_dealing || form.value.harga_dealing <= 0)) {
    formErrors.value.harga_dealing = 'Harga dealing wajib diisi.';
  }

  if (!isEditMode.value && form.value.dp > 0 && !form.value.dp_paid_at) {
    formErrors.value.dp_paid_at = 'Tanggal pembayaran DP wajib diisi.';
  }

  if (Object.keys(formErrors.value).length) {
    toast.add({ severity: 'warn', summary: 'Validasi', detail: Object.values(formErrors.value)[0], life: 3500 });
    return false;
  }

  return true;
};

const validateWaitingListForm = () => {
  waitingListErrors.value = {};

  if (form.value.unit_mode !== 'existing' || !form.value.unit_id) {
    waitingListErrors.value.unit_id = 'Pilih unit ready untuk langsung masuk Waiting List.';
  }

  if (!form.value.tgl_sewa) {
    waitingListErrors.value.tgl_sewa = 'Tanggal sewa wajib diisi.';
  }

  if (!form.value.tgl_kembali) {
    waitingListErrors.value.tgl_kembali = 'Tanggal kembali wajib diisi.';
  }

  if (!form.value.lama_sewa || form.value.lama_sewa < 1) {
    waitingListErrors.value.lama_sewa = 'Lama sewa wajib diisi.';
  }

  if (!waitingListForm.value.harga_mobil && waitingListForm.value.pricing_mode === 'non_all_in') {
    waitingListErrors.value.harga_mobil = 'Harga mobil wajib diisi.';
  }

  if (
    waitingListForm.value.pricing_mode === 'all_in'
    && !waitingListForm.value.pricing_package_id
    && !waitingListForm.value.harga_all_in
  ) {
    waitingListErrors.value.harga_all_in = 'Pilih pricing package atau isi harga All In.';
  }

  const invalidCost = waitingListForm.value.costs.some(cost => !cost.label || cost.amount == null);
  if (invalidCost) {
    waitingListErrors.value.costs = 'Setiap biaya harus punya keterangan dan nominal.';
  }

  if (Object.keys(waitingListErrors.value).length) {
    toast.add({ severity: 'warn', summary: 'Validasi Waiting List', detail: Object.values(waitingListErrors.value)[0], life: 3500 });
    return false;
  }

  return true;
};

const applyDirectWaitingListBookingDefaults = (payload) => {
  payload.tgl_sewa = payload.tgl_sewa || applyDefaultTime(new Date(), 7, 0);
  payload.tgl_kembali = payload.tgl_kembali || addRentalDuration(payload.tgl_sewa, payload.lama_sewa, payload.paket_sewa);
  payload.harga_dealing = null;
  payload.dp = null;
  payload.rekening_dp_id = null;
  payload.dp_paid_at = null;
};

const buildWaitingListPayload = () => {
  const selectedPackage = findPricingPackage(waitingListForm.value.pricing_package_id);

  return {
    unit_id: form.value.unit_id,
    driver_id: waitingListForm.value.driver_id,
    lama_sewa: form.value.lama_sewa,
    paket_sewa: form.value.paket_sewa,
    harga_mobil: waitingListForm.value.harga_mobil || 0,
    diskon_mobil: waitingListForm.value.diskon_mobil || 0,
    pricing_mode: waitingListForm.value.pricing_mode,
    pricing_package_id: waitingListForm.value.pricing_package_id,
    harga_all_in: waitingListForm.value.pricing_mode === 'all_in'
      ? (waitingListForm.value.harga_all_in || selectedPackage?.harga || null)
      : null,
    costs: waitingListForm.value.costs.map(({
      _auto_all_in_ops,
      _all_in_base_amount,
      _all_in_package_cost,
      _manual_all_in_ops,
      ...cost
    }) => cost),
    alamat_penjemputan: form.value.alamat_penjemputan,
    tujuan: form.value.tujuan,
    kota: form.value.kota,
  };
};

const getWaitingRentalDuration = () => form.value.lama_sewa || 1;

const createAllInPackageCost = (cost) => {
  const baseAmount = cost.amount || 0;
  return {
    ...cost,
    amount: baseAmount * getWaitingRentalDuration(),
    _auto_all_in_ops: true,
    _all_in_base_amount: baseAmount,
    _all_in_package_cost: true,
  };
};

const syncWaitingAllInOperationalCosts = () => {
  if (waitingListForm.value.pricing_mode !== 'all_in') {
    waitingListForm.value.costs = waitingListForm.value.costs.filter(cost => !cost._all_in_package_cost);
    return;
  }

  const pkg = findPricingPackage(waitingListForm.value.pricing_package_id);
  if (!pkg) return;

  const duration = getWaitingRentalDuration();
  const packageItems = packageCostItems(pkg);
  const packageKeys = new Set(packageItems.map(getPackageCostKey));

  waitingListForm.value.costs = waitingListForm.value.costs.filter(cost => {
    if (cost._auto_all_in_ops) return packageKeys.has(getPackageCostKey(cost));
    return true;
  });

  packageItems.forEach(item => {
    const key = getPackageCostKey(item);
    const existing = waitingListForm.value.costs.find(cost =>
      cost._all_in_package_cost && getPackageCostKey(cost) === key
    );

    if (existing) {
      if (existing._auto_all_in_ops) {
        existing._all_in_base_amount = item.amount || 0;
        existing.amount = (item.amount || 0) * duration;
      }
      return;
    }

    waitingListForm.value.costs.push(createAllInPackageCost(item));
  });
};

const onWaitingCostAmountUpdate = (cost, value) => {
  if (!cost._auto_all_in_ops) return;
  if (value !== (cost._all_in_base_amount || 0) * getWaitingRentalDuration()) {
    delete cost._auto_all_in_ops;
    cost._manual_all_in_ops = true;
  }
};

const applyWaitingPackage = () => {
  const pkg = findPricingPackage(waitingListForm.value.pricing_package_id);
  if (!pkg) {
    waitingListForm.value.harga_all_in = null;
    waitingListForm.value.costs = waitingListForm.value.costs.filter(cost => !cost._all_in_package_cost);
    return;
  }

  waitingListForm.value.harga_all_in = pkg.harga || null;
  waitingListForm.value.costs = packageCostItems(pkg).map(createAllInPackageCost);
};

const addWaitingCostRow = () => {
  waitingListForm.value.costs.push({ cost_type_id: null, type: 'biaya', label: '', amount: 0, keterangan: '' });
};

const removeWaitingCostRow = (idx) => {
  waitingListForm.value.costs.splice(idx, 1);
};

const onWaitingCostTypeChange = (idx, typeId) => {
  const ct = costTypesMaster.value.find(c => c.id === typeId);
  if (ct) waitingListForm.value.costs[idx].label = ct.nama;
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

watch(
  selectedUnit,
  (unit) => {
    if (!isDirectWaitingList.value || !unit) return;
    waitingListForm.value.harga_mobil = unit.harga_1_hari || 0;
  }
);

watch(selectedCustomer, (customer) => {
  if (customer && selectedCustomerCache.value?.value !== customer.value) {
    selectedCustomerCache.value = customer;
  }
});

watch(selectedUnit, (unit) => {
  if (unit && selectedUnitCache.value?.id !== unit.id) {
    selectedUnitCache.value = unit;
  }
});

watch(
  () => waitingListForm.value.pricing_package_id,
  (packageId) => {
    const selectedPackage = findPricingPackage(packageId);
    if (selectedPackage && selectedPricingPackageCache.value?.id !== selectedPackage.id) {
      selectedPricingPackageCache.value = mapPricingPackageOption(selectedPackage);
    }
  }
);

watch(
  () => [
    waitingListForm.value.pricing_mode,
    waitingListForm.value.pricing_package_id,
    waitingListForm.value.harga_all_in,
    form.value.lama_sewa,
  ],
  () => {
    if (!isDirectWaitingList.value) return;
    syncWaitingAllInOperationalCosts();
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
    dp_paid_at: new Date(),
    catatan: ''
  };
  waitingListForm.value = {
    driver_id: null,
    harga_mobil: 0,
    diskon_mobil: 0,
    pricing_mode: 'non_all_in',
    pricing_package_id: null,
    harga_all_in: null,
    costs: [],
  };
  waitingListErrors.value = {};
  formErrors.value = {};
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

const mergeSelectedOption = (options, selected, key = 'id') => {
  if (!selected) return options;
  return options.some(option => option[key] === selected[key])
    ? options
    : [selected, ...options];
};

const mapUnitOption = (u) => ({
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
        ].filter(Boolean).join(' ')),
        serverSearchLabel: unitServerSearchTerm.value,
});

const unitOptions = computed(() =>
  mergeSelectedOption(units.value.map(mapUnitOption), selectedUnitCache.value, 'id')
);

const cityOptions = computed(() =>
  cities.value
    .filter(city => city.is_active)
    .map(city => ({
      label: city.provinsi ? `${city.nama} - ${city.provinsi}` : city.nama,
      value: city.nama,
      searchableLabel: [city.nama, city.provinsi].filter(Boolean).join(' ')
    }))
);

const mapCustomerOption = (c) => ({
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
});

const mapRentalOwnerOption = (owner) => ({
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
});

const customerOptions = computed(() => {
    const customerItems = customers.value.map(mapCustomerOption);
    const rentalOwnerItems = rentalOwners.value.map(mapRentalOwnerOption);

    return mergeSelectedOption([...customerItems, ...rentalOwnerItems], selectedCustomerCache.value, 'value');
});

const mapPricingPackageOption = (pkg) => ({
  ...pkg,
  id: pkg.id,
  label: `${pkg.nama_paket} - ${formatCurrency(pkg.harga)}`,
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
    <Dialog
      v-model:visible="showCreateModeDialog"
      modal
      :closable="false"
      :style="{ width: '560px' }"
      :breakpoints="{ '640px': '94vw' }"
      header="Pilih Jenis Input Booking"
      class="create-mode-dialog"
    >
      <div class="create-mode-grid">
        <button
          v-for="option in createModeOptions"
          :key="option.value"
          type="button"
          class="create-mode-card"
          @click="selectCreateMode(option.value)"
        >
          <span class="create-mode-icon"><i :class="option.icon"></i></span>
          <span class="create-mode-title">{{ option.title }}</span>
          <span class="create-mode-description">{{ option.description }}</span>
        </button>
      </div>
    </Dialog>

     <div class="detail-page-header mb-3">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" text rounded @click="router.back()" class="back-button" />
        <div>
          <div class="flex flex-wrap items-center gap-3 mb-1">
            <h1 class="booking-page-title">{{ isEditMode ? 'Edit Booking' : 'Buat Booking Baru' }}</h1>  
            <Tag
              v-if="!isEditMode"
              :value="isDirectWaitingList ? 'Langsung Waiting List' : 'Booking Biasa'"
              :severity="isDirectWaitingList ? 'info' : 'secondary'"
              class="premium-tag"
            />
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
                  @filter="onCustomerFilter"
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
                v-if="!isDirectWaitingList"
                v-model="form.unit_mode"
                :options="[{label: 'Unit Ready', value: 'existing'}, {label: 'Placeholder', value: 'placeholder'}]"
                optionLabel="label"
                optionValue="value"
                class="w-full custom-selectbutton"
              />

              <Message v-else severity="info" icon="pi pi-info-circle" class="!m-0">
                Mode Waiting List membutuhkan unit ready karena booking langsung di-handle.
              </Message>

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
                  :filterFields="['serverSearchLabel', 'searchableLabel', 'normalizedSearchableLabel']"
                  :loading="unitsLoading || unitSearchLoading"
                  class="w-full premium-input"
                  @filter="onUnitFilter"
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
                <small class="p-error" v-if="waitingListErrors.unit_id">{{ waitingListErrors.unit_id }}</small>

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

        <Card v-if="!isDirectWaitingList" class="premium-card">
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
                <label class="field-label">Kota booking *</label>
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
                  :class="{ 'p-invalid': formErrors.kota }"
                  :empty-message="'Belum ada kota aktif'"
                />
                <small class="p-error" v-if="formErrors.kota">{{ formErrors.kota }}</small>
              </div>

              <div class="form-field-vertical md:col-span-2">
                <label class="field-label">Alamat jemput *</label>
                <Textarea
                  v-model="form.alamat_penjemputan"
                  rows="2"
                  placeholder="Input alamat lengkap penjemputan..."
                  class="w-full premium-input"
                  :class="{ 'p-invalid': formErrors.alamat_penjemputan }"
                />
                <small class="p-error" v-if="formErrors.alamat_penjemputan">{{ formErrors.alamat_penjemputan }}</small>
              </div>

              <div class="form-field-vertical">
                <label class="field-label">Harga dealing *</label>
                <InputNumber
                  v-model="form.harga_dealing"
                  mode="currency"
                  currency="IDR"
                  locale="id-ID"
                  placeholder="Rp 0"
                  class="w-full premium-input"
                  :class="{ 'p-invalid': formErrors.harga_dealing }"
                />
                <small class="p-error" v-if="formErrors.harga_dealing">{{ formErrors.harga_dealing }}</small>
              </div>

              <div class="form-field-vertical">
                <label class="field-label">Uang muka (DP)</label>
                <InputNumber v-model="form.dp" mode="currency" currency="IDR" locale="id-ID" placeholder="Rp 0" class="w-full premium-input" :disabled="isEditMode" />
              </div>

              <transition name="slide-up">
                <div v-if="form.dp > 0" class="form-field-vertical md:col-span-2 payment-account-panel animate-slide-up">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="form-field-vertical">
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
                    </div>
                    <div class="form-field-vertical">
                      <label class="field-label">Tanggal Pembayaran *</label>
                      <Calendar
                        v-model="form.dp_paid_at"
                        dateFormat="dd M yy"
                        showIcon
                        showTime
                        hourFormat="24"
                        class="w-full premium-calendar"
                        :class="{ 'p-invalid': formErrors.dp_paid_at }"
                        :disabled="isEditMode"
                      />
                      <small class="p-error" v-if="formErrors.dp_paid_at">{{ formErrors.dp_paid_at }}</small>
                    </div>
                  </div>
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

        <Card v-if="isDirectWaitingList" class="premium-card">
          <template #title>
            <div class="section-title">
              <i class="pi pi-send text-tosca"></i>
              <span>Data Waiting List</span>
            </div>
          </template>
          <template #content>
            <div class="section-stack">
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
                  <small class="p-error" v-if="waitingListErrors.tgl_sewa">{{ waitingListErrors.tgl_sewa }}</small>
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
                  <small class="p-error" v-if="waitingListErrors.tgl_kembali">{{ waitingListErrors.tgl_kembali }}</small>
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
                  <small class="p-error" v-if="waitingListErrors.lama_sewa">{{ waitingListErrors.lama_sewa }}</small>
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
                  <label class="field-label">Kota booking *</label>
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
                    :class="{ 'p-invalid': formErrors.kota }"
                    :empty-message="'Belum ada kota aktif'"
                  />
                  <small class="p-error" v-if="formErrors.kota">{{ formErrors.kota }}</small>
                </div>

                <div class="form-field-vertical md:col-span-2">
                  <label class="field-label">Alamat jemput *</label>
                  <Textarea
                    v-model="form.alamat_penjemputan"
                    rows="2"
                    placeholder="Input alamat lengkap penjemputan..."
                    class="w-full premium-input"
                    :class="{ 'p-invalid': formErrors.alamat_penjemputan }"
                  />
                  <small class="p-error" v-if="formErrors.alamat_penjemputan">{{ formErrors.alamat_penjemputan }}</small>
                </div>

                <div class="form-field-vertical">
                  <label class="field-label">Driver</label>
                  <Dropdown
                    v-model="waitingListForm.driver_id"
                    :options="drivers"
                    optionLabel="nama"
                    optionValue="id"
                    placeholder="Lepas kunci / pilih driver"
                    filter
                    showClear
                    class="w-full premium-input"
                  />
                </div>

                <div class="form-field-vertical">
                  <label class="field-label">Mode pricing *</label>
                  <SelectButton
                    v-model="waitingListForm.pricing_mode"
                    :options="pricingModeOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full custom-selectbutton"
                  />
                </div>

                <div class="form-field-vertical">
                  <label class="field-label">Harga mobil / periode *</label>
                  <InputNumber
                    v-model="waitingListForm.harga_mobil"
                    mode="currency"
                    currency="IDR"
                    locale="id-ID"
                    class="w-full premium-input"
                  />
                  <small class="p-error" v-if="waitingListErrors.harga_mobil">{{ waitingListErrors.harga_mobil }}</small>
                </div>

                <div class="form-field-vertical">
                  <label class="field-label">Diskon mobil</label>
                  <InputNumber
                    v-model="waitingListForm.diskon_mobil"
                    mode="currency"
                    currency="IDR"
                    locale="id-ID"
                    class="w-full premium-input"
                  />
                </div>

                <div v-if="waitingListForm.pricing_mode === 'all_in'" class="form-field-vertical md:col-span-2 all-in-panel">
                  <label class="field-label">Pricing package</label>
                  <Dropdown
                    v-model="waitingListForm.pricing_package_id"
                    :options="packageOptions"
                    optionLabel="label"
                    optionValue="id"
                    placeholder="Pilih paket atau isi manual"
                    showClear
                    filter
                    :filterFields="['label', 'nama_paket', 'keterangan']"
                    :loading="pricingPackagesLoading"
                    class="w-full premium-input"
                    @filter="onPricingPackageFilter"
                    @change="applyWaitingPackage"
                  />
                  <div v-if="!waitingListForm.pricing_package_id" class="form-field-vertical">
                    <label class="field-label">Harga All In *</label>
                    <InputNumber
                      v-model="waitingListForm.harga_all_in"
                      mode="currency"
                      currency="IDR"
                      locale="id-ID"
                      class="w-full premium-input"
                    />
                    <small class="p-error" v-if="waitingListErrors.harga_all_in">{{ waitingListErrors.harga_all_in }}</small>
                  </div>
                </div>
              </div>

              <div class="waiting-cost-panel">
                <div class="waiting-cost-header">
                  <div class="section-title">
                    <i class="pi pi-wallet text-tosca"></i>
                    <span>Biaya Operasional</span>
                  </div>
                  <Button label="Tambah Biaya" icon="pi pi-plus" text size="small" @click="addWaitingCostRow" />
                </div>

                <div v-if="!waitingListForm.costs.length" class="empty-cost-state">
                  Belum ada biaya operasional.
                </div>

                <div v-for="(cost, idx) in waitingListForm.costs" :key="idx" class="cost-row">
                  <Dropdown
                    v-model="cost.cost_type_id"
                    :options="costTypeOptions"
                    optionLabel="label"
                    optionValue="id"
                    placeholder="Tipe"
                    showClear
                    class="premium-input cost-type-input"
                    @change="onWaitingCostTypeChange(idx, cost.cost_type_id)"
                  />
                  <Dropdown
                    v-model="cost.type"
                    :options="[{ label: 'Biaya', value: 'biaya' }, { label: 'Diskon', value: 'diskon' }]"
                    optionLabel="label"
                    optionValue="value"
                    class="premium-input cost-kind-input"
                  />
                  <InputText v-model="cost.label" placeholder="Keterangan" class="premium-input cost-label-input" />
                  <InputNumber
                    v-model="cost.amount"
                    mode="currency"
                    currency="IDR"
                    locale="id-ID"
                    class="premium-input cost-amount-input"
                    @update:modelValue="onWaitingCostAmountUpdate(cost, $event)"
                  />
                  <Button icon="pi pi-times" text rounded severity="danger" class="remove-cost-button" @click="removeWaitingCostRow(idx)" />
                </div>
                <small class="p-error" v-if="waitingListErrors.costs">{{ waitingListErrors.costs }}</small>
              </div>

              <div class="waiting-total-panel">
                <div>
                  <span>Harga sewa</span>
                  <strong>{{ formatCurrency(waitingHargaSewa) }}</strong>
                </div>
                <div>
                  <span>{{ waitingListForm.pricing_mode === 'all_in' ? 'Diskon ops dihitung' : 'Biaya ops' }}</span>
                  <strong>{{ formatCurrency(waitingTotalBiayaOps) }}</strong>
                </div>
                <div>
                  <span>Grand total internal</span>
                  <strong>{{ formatCurrency(waitingGrandTotalInternal) }}</strong>
                </div>
                <div class="waiting-total-highlight">
                  <span>Tagihan konsumen</span>
                  <strong>{{ formatCurrency(waitingTagihanKonsumen) }}</strong>
                </div>
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
              <strong>{{ formatCurrency(isDirectWaitingList ? waitingTagihanKonsumen : form.harga_dealing) }}</strong>
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
            <div v-if="!isDirectWaitingList">
              <span>Harga dealing</span>
              <strong class="numeric-value">{{ formatCurrency(form.harga_dealing) }}</strong>
            </div>
            <div v-if="!isDirectWaitingList">
              <span>DP</span>
              <strong class="numeric-value">{{ formatCurrency(form.dp) }}</strong>
            </div>
            <div v-if="isDirectWaitingList">
              <span>Driver</span>
              <strong>{{ drivers.find(driver => driver.id === waitingListForm.driver_id)?.nama || 'Lepas kunci' }}</strong>
            </div>
            <div v-if="isDirectWaitingList">
              <span>Tagihan WL</span>
              <strong class="numeric-value">{{ formatCurrency(waitingTagihanKonsumen) }}</strong>
            </div>
          </div>

          <div class="summary-actions">
            <Button v-if="!isEditMode" label="Reset" icon="pi pi-refresh" class="button-secondary" @click="resetForm" />
            <Button
              :label="isEditMode ? 'Simpan Perubahan' : (isDirectWaitingList ? 'Simpan ke Waiting List' : 'Simpan Booking')"
              icon="pi pi-check"
              :loading="bookingLoading || isSubmitting"
              @click="handleSubmit"
              :disabled="isBlacklisted || isSubmitting"
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
  align-self: start;
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

.create-mode-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.create-mode-card {
  display: flex;
  min-height: 170px;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  padding: 16px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  color: var(--text-primary);
  text-align: left;
  cursor: pointer;
  transition: border-color 0.2s ease, background-color 0.2s ease;
}

.create-mode-card:hover {
  border-color: #0D8091;
  background: var(--card-bg-hover);
}

.create-mode-icon {
  display: inline-flex;
  width: 34px;
  height: 34px;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-default);
  background: #E1F4F6;
  color: #085A66;
}

.create-mode-title {
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 700;
  line-height: 1.25;
}

.create-mode-description {
  color: var(--text-secondary);
  font-size: 12px;
  line-height: 1.45;
}

.all-in-panel,
.waiting-cost-panel,
.waiting-total-panel {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--card-bg);
  padding: var(--space-md);
}

.waiting-cost-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.empty-cost-state {
  border: 1px dashed var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  padding: 14px;
  color: var(--text-secondary);
  text-align: center;
  font-size: 12px;
}

.cost-row {
  display: grid;
  grid-template-columns: minmax(120px, 1.2fr) minmax(96px, 0.8fr) minmax(140px, 1.5fr) minmax(130px, 1.1fr) 34px;
  gap: 8px;
  align-items: start;
  margin-bottom: 8px;
}

.remove-cost-button {
  width: 34px;
  height: 34px;
  padding: 0 !important;
}

.waiting-total-panel {
  display: grid;
  gap: 8px;
}

.waiting-total-panel > div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  color: var(--text-secondary);
  font-size: 12px;
}

.waiting-total-panel strong {
  color: var(--text-primary);
  font-family: var(--font-mono);
  font-size: 13px;
  font-weight: 600;
}

.waiting-total-highlight {
  margin-top: 4px;
  border-top: 1px solid var(--surface-border);
  padding-top: 10px;
}

.waiting-total-highlight span,
.waiting-total-highlight strong {
  color: #085A66;
  font-weight: 700;
}

.summary-panel {
  position: sticky;
  top: var(--space-lg);
  max-height: calc(100vh - (var(--space-lg) * 2));
  overflow-y: auto;
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
    max-height: none;
    overflow-y: visible;
  }
}

@media (max-width: 767px) {
  .booking-create-container {
    padding: var(--space-md);
  }

  .create-mode-grid,
  .cost-row {
    grid-template-columns: 1fr;
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

  :deep(.premium-card .p-card-title) {
    padding: var(--space-md) var(--space-lg);
  }

  :deep(.premium-card .p-card-content) {
    padding: var(--space-md) var(--space-lg) var(--space-lg);
  }

  .cost-row {
     display: flex;
     flex-direction: column;
     gap: 8px;
     border: 1px solid var(--surface-border);
     border-radius: var(--radius-sm);
     padding: var(--space-md);
     margin-bottom: var(--space-md);
     position: relative;
  }

  .remove-cost-button {
     position: absolute;
     top: 4px;
     right: 4px;
  }
}
</style>
