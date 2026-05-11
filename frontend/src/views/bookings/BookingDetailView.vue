<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBooking } from '../../composables/useBooking';
import { useUnit } from '../../composables/useUnit';
import { useDriver } from '../../composables/useDriver';
import { usePaymentAccount } from '../../composables/usePaymentAccount';
import { useCostType } from '../../composables/useCostType';
import { usePricingPackage } from '../../composables/usePricingPackage';
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue';
import { useToast } from 'primevue/usetoast';

import Button from 'primevue/button';
import Card from 'primevue/card';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import Calendar from 'primevue/calendar';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import SelectButton from 'primevue/selectbutton';
import ToggleButton from 'primevue/togglebutton';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { fetchOne, handle, addDetail, addCost, updateDetail, updateCost, extend, rolling, stopEarly, addAdditionalCost, addPayment, loading } = useBooking();
const { units, fetchAll: fetchUnits } = useUnit();
const { drivers, fetchAll: fetchDrivers } = useDriver();
const { accounts: paymentAccounts, fetchAll: fetchAccounts } = usePaymentAccount();
const { costTypes: costTypesMaster, fetchAll: fetchCostTypes } = useCostType();
const { packages: pricingPackages, fetchAll: fetchPricingPackages } = usePricingPackage();

const booking = ref(null);
const showDetailDialog = ref(false);
const showCostDialog = ref(false);
const editingDetailId = ref(null);
const editingCostId = ref(null);

// Payment dialog
const showPaymentDialog = ref(false);
const paymentForm = ref({ payment_account_id: null, amount: null, payment_type: 'cicilan', catatan: '' });
const paymentFormErrors = ref({});
const paymentTypeOptions = [
  { label: 'DP / Uang Muka', value: 'dp' },
  { label: 'Cicilan', value: 'cicilan' },
  { label: 'Pelunasan', value: 'pelunasan' },
];

const accountOptions = computed(() =>
  paymentAccounts.value
    .filter(a => a.is_active)
    .map(a => ({ id: a.id, name: `${a.nama_bank} — ${a.nomor_rekening} (${a.atas_nama})` }))
);

// Handle booking dialog (E3)
const showHandleDialog = ref(false);
const handleFormErrors = ref({});
const handleForm = ref({
  unit_id: null,
  driver_id: null,
  lama_sewa: null,
  paket_sewa: 'harian',
  harga_mobil: 0,
  diskon_mobil: 0,
  pricing_mode: 'non_all_in',
  pricing_package_id: null,
  harga_all_in: null,
  costs: [],
  alamat_penjemputan: '',
  tujuan: '',
});

const paketOptions = [
  { label: 'Harian', value: 'harian' },
  { label: 'Mingguan', value: 'mingguan' },
  { label: 'Bulanan', value: 'bulanan' },
];

const pricingModeOptions = [
  { label: 'Non All In', value: 'non_all_in' },
  { label: 'All In', value: 'all_in' },
];

const costTypeOptions = computed(() =>
  costTypesMaster.value.filter(c => c.is_active).map(c => ({ id: c.id, label: c.nama, kode: c.kode, require_description: c.require_description }))
);

const packageOptions = computed(() =>
  pricingPackages.value.filter(p => p.is_active).map(p => ({ id: p.id, label: `${p.nama_paket} — ${formatCurrency(p.harga)}` }))
);

const hargaSewa = computed(() => {
  const { harga_mobil, diskon_mobil, lama_sewa } = handleForm.value;
  return Math.max(0, ((harga_mobil || 0) - (diskon_mobil || 0)) * (lama_sewa || 0));
});

const totalBiayaOps = computed(() =>
  handleForm.value.costs.reduce((sum, c) => sum + (c.amount || 0), 0)
);

const grandTotalInternal = computed(() => hargaSewa.value + totalBiayaOps.value);

const tagihanKonsumen = computed(() => {
  if (handleForm.value.pricing_mode === 'all_in') {
    if (handleForm.value.pricing_package_id) {
      const pkg = pricingPackages.value.find(p => p.id === handleForm.value.pricing_package_id);
      return pkg?.harga || handleForm.value.harga_all_in || 0;
    }
    return handleForm.value.harga_all_in || 0;
  }
  return grandTotalInternal.value;
});

const addCostRow = () => {
  handleForm.value.costs.push({ cost_type_id: null, label: '', amount: 0, keterangan: '' });
};

const removeCostRow = (idx) => {
  handleForm.value.costs.splice(idx, 1);
};

const onHandleUnitChange = (e) => {
  const unit = units.value.find(u => u.id === e.value);
  if (unit) handleForm.value.harga_mobil = unit.harga_1_hari || 0;
};

const onCostTypeChange = (idx, typeId) => {
  const ct = costTypesMaster.value.find(c => c.id === typeId);
  if (ct) handleForm.value.costs[idx].label = ct.nama;
};

const openHandleDialog = () => {
  handleFormErrors.value = {};
  const detail = booking.value?.booking_details?.[0];
  handleForm.value = {
    unit_id: detail?.unit_id || null,
    driver_id: detail?.driver_id || null,
    lama_sewa: booking.value?.lama_sewa || null,
    paket_sewa: booking.value?.paket_sewa || 'harian',
    harga_mobil: detail?.harga_mobil || 0,
    diskon_mobil: detail?.diskon_mobil || 0,
    pricing_mode: detail?.pricing_mode || 'non_all_in',
    pricing_package_id: detail?.pricing_package_id || null,
    harga_all_in: detail?.harga_all_in || null,
    costs: detail?.costs?.map(c => ({ cost_type_id: c.cost_type_id, label: c.label, amount: c.amount, keterangan: c.keterangan || '' })) || [],
    alamat_penjemputan: booking.value?.alamat_penjemputan || '',
    tujuan: booking.value?.tujuan || '',
  };
  showHandleDialog.value = true;
};

const submitHandle = async () => {
  handleFormErrors.value = {};
  try {
    await handle(booking.value.id, handleForm.value);
    showHandleDialog.value = false;
    loadBooking();
  } catch (err) {
    if (err.response?.data?.errors) handleFormErrors.value = err.response.data.errors;
  }
};

// Modification Dialogs
const showExtendDialog = ref(false);
const showRollingDialog = ref(false);
const rollingStep = ref(1);
const showStopEarlyDialog = ref(false);
const showAdditionalCostDialog = ref(false);
const showBatalDialog = ref(false);

const detailForm = ref({
  unit_id: null,
  driver_id: null,
  tgl_sewa: null,
  tgl_kembali: null,
  harga_mobil: 0,
  diskon_mobil: 0,
  detail_type: 'initial'
});

const costForm = ref({
  booking_detail_id: null,
  type: 'driver',
  label: '',
  amount: 0
});

// Extend form (revamp)
const extendForm = ref({
  unit_id: null,
  driver_id: null,
  tgl_sewa: null,
  tgl_kembali: null,
  lama_sewa: null,
  paket_sewa: 'harian',
  harga_mobil: 0,
  diskon_mobil: 0,
  pricing_mode: 'non_all_in',
  pricing_package_id: null,
  harga_all_in: null,
  costs: [],
});

// Rolling form (step 1: adjust lama, step 2: detail baru)
const rollingForm = ref({
  booking_detail_id: null,
  tgl_rolling: null,
  lama_sewa_lama: null,
  harga_mobil_lama: 0,
  diskon_mobil_lama: 0,
  unit_id: null,
  driver_id: null,
  tgl_kembali: null,
  lama_sewa: null,
  paket_sewa: 'harian',
  harga_mobil: 0,
  diskon_mobil: 0,
  pricing_mode: 'non_all_in',
  pricing_package_id: null,
  harga_all_in: null,
  costs: [],
});

// Batal form
const batalForm = ref({
  refund_amount: null,
  refund_keterangan: '',
  payment_account_id: null,
});

const stopEarlyForm = ref({
  booking_detail_id: null,
  tgl_stop: null,
  refund_amount: 0
});

const additionalCostForm = ref({
  type: 'lainnya',
  label: '',
  amount: 0,
  is_discount: false
});

const costTypes = [
  { label: 'Driver', value: 'driver' },
  { label: 'BBM', value: 'bbm' },
  { label: 'Tol', value: 'tol' },
  { label: 'Parkir', value: 'parkir' },
  { label: 'Lainnya', value: 'lainnya' },
  { label: 'Diskon', value: 'diskon' }
];

// Extend computed
const extendHargaSewa = computed(() => Math.max(0, ((extendForm.value.harga_mobil||0) - (extendForm.value.diskon_mobil||0)) * (extendForm.value.lama_sewa||0)));
const extendTotalBiaya = computed(() => extendForm.value.costs.reduce((s, c) => s + (c.amount||0), 0));
const extendGrandTotal = computed(() => extendHargaSewa.value + extendTotalBiaya.value);
const extendTagihan = computed(() => {
  if (extendForm.value.pricing_mode === 'all_in') {
    if (extendForm.value.pricing_package_id) {
      const pkg = pricingPackages.value.find(p => p.id === extendForm.value.pricing_package_id);
      return pkg?.harga || extendForm.value.harga_all_in || 0;
    }
    return extendForm.value.harga_all_in || 0;
  }
  return extendGrandTotal.value;
});

// Rolling computed
const rollingHargaSewa = computed(() => Math.max(0, ((rollingForm.value.harga_mobil||0) - (rollingForm.value.diskon_mobil||0)) * (rollingForm.value.lama_sewa||0)));
const rollingTotalBiaya = computed(() => rollingForm.value.costs.reduce((s, c) => s + (c.amount||0), 0));
const rollingGrandTotal = computed(() => rollingHargaSewa.value + rollingTotalBiaya.value);
const rollingTagihan = computed(() => {
  if (rollingForm.value.pricing_mode === 'all_in') {
    if (rollingForm.value.pricing_package_id) {
      const pkg = pricingPackages.value.find(p => p.id === rollingForm.value.pricing_package_id);
      return pkg?.harga || rollingForm.value.harga_all_in || 0;
    }
    return rollingForm.value.harga_all_in || 0;
  }
  return rollingGrandTotal.value;
});

const validDetails = computed(() => {
  if (!booking.value?.booking_details) return [];
  return booking.value.booking_details.filter(detail => detail.unit_id !== null);
});

const activeDetails = computed(() => {
  return validDetails.value.filter(d => d.status === 'active');
});

const canHandle = computed(() => {
  return booking.value && 
         ['follow_up', 'confirm'].includes(booking.value.status) && 
         validDetails.value.length > 0;
});

const isRentalUnit = computed(() => {
  return booking.value && booking.value.status === 'rental_unit';
});

const loadBooking = async () => {
  try {
    booking.value = await fetchOne(route.params.id);
  } catch (err) {
    console.error(err);
  }
};

onMounted(async () => {
  loadBooking();
  fetchUnits({ per_page: 100 });
  fetchDrivers({ per_page: 100 });
  fetchAccounts({ per_page: 100 });
  fetchCostTypes({ per_page: 100 });
  fetchPricingPackages({ per_page: 100 });
});

const openDetailDialog = (detail = null) => {
  if (detail) {
    editingDetailId.value = detail.id;
    detailForm.value = {
      unit_id: detail.unit_id,
      driver_id: detail.driver_id,
      tgl_sewa: detail.tgl_sewa ? new Date(detail.tgl_sewa) : null,
      tgl_kembali: detail.tgl_kembali ? new Date(detail.tgl_kembali) : null,
      harga_mobil: detail.harga_mobil,
      diskon_mobil: detail.diskon_mobil,
      detail_type: detail.detail_type
    };
  } else {
    editingDetailId.value = null;
    detailForm.value = {
      unit_id: null,
      driver_id: null,
      tgl_sewa: booking.value.booking_details?.[0]?.tgl_sewa ? new Date(booking.value.booking_details[0].tgl_sewa) : null,
      tgl_kembali: booking.value.booking_details?.[0]?.tgl_kembali ? new Date(booking.value.booking_details[0].tgl_kembali) : null,
      harga_mobil: 0,
      diskon_mobil: 0,
      detail_type: 'initial'
    };
  }
  showDetailDialog.value = true;
};

const openCostDialog = (detailId, cost = null) => {
  if (cost) {
    editingCostId.value = cost.id;
    costForm.value = {
      booking_detail_id: detailId,
      type: cost.type,
      label: cost.label,
      amount: cost.amount
    };
  } else {
    editingCostId.value = null;
    costForm.value = {
      booking_detail_id: detailId,
      type: 'driver',
      label: '',
      amount: 0
    };
  }
  showCostDialog.value = true;
};

// Modification Dialog Openers
const openExtendDialog = () => {
  const last = validDetails.value[validDetails.value.length - 1];
  const tglSewa = last?.tgl_kembali ? new Date(last.tgl_kembali) : new Date();
  tglSewa.setHours(7, 0, 0);
  extendForm.value = {
    unit_id: last?.unit_id || null,
    driver_id: last?.driver_id || null,
    tgl_sewa: tglSewa,
    tgl_kembali: null,
    lama_sewa: null,
    paket_sewa: last?.paket_sewa || 'harian',
    harga_mobil: last?.harga_mobil || 0,
    diskon_mobil: last?.diskon_mobil || 0,
    pricing_mode: last?.pricing_mode || 'non_all_in',
    pricing_package_id: last?.pricing_package_id || null,
    harga_all_in: null,
    costs: [],
  };
  showExtendDialog.value = true;
};

const openRollingDialog = () => {
  const active = activeDetails.value[0];
  rollingStep.value = 1;
  rollingForm.value = {
    booking_detail_id: active?.id || null,
    tgl_rolling: new Date(),
    lama_sewa_lama: active?.lama_sewa || null,
    harga_mobil_lama: active?.harga_mobil || 0,
    diskon_mobil_lama: active?.diskon_mobil || 0,
    unit_id: null,
    driver_id: null,
    tgl_kembali: null,
    lama_sewa: null,
    paket_sewa: active?.paket_sewa || 'harian',
    harga_mobil: 0,
    diskon_mobil: 0,
    pricing_mode: 'non_all_in',
    pricing_package_id: null,
    harga_all_in: null,
    costs: [],
  };
  showRollingDialog.value = true;
};

const openBatalDialog = () => {
  batalForm.value = { refund_amount: null, refund_keterangan: '', payment_account_id: null };
  showBatalDialog.value = true;
};

const openStopEarlyDialog = () => {
  stopEarlyForm.value = {
    booking_detail_id: activeDetails.value[0]?.id || null,
    tgl_stop: new Date(),
    refund_amount: 0
  };
  showStopEarlyDialog.value = true;
};

const openAdditionalCostDialog = () => {
  additionalCostForm.value = {
    type: 'lainnya',
    label: '',
    amount: 0,
    is_discount: false
  };
  showAdditionalCostDialog.value = true;
};

const onUnitChange = (e) => {
  const unit = units.value.find(u => u.id === e.value);
  if (unit) {
    detailForm.value.harga_mobil = unit.harga_1_hari || 0;
  }
};

const onExtendUnitChange = (e) => {
  const unit = units.value.find(u => u.id === e.value);
  if (unit) {
    extendForm.value.harga_mobil = unit.harga_1_hari || 0;
  }
};

const submitDetail = async () => {
  try {
    const payload = {
      ...detailForm.value,
      tgl_sewa: detailForm.value.tgl_sewa?.toISOString(),
      tgl_kembali: detailForm.value.tgl_kembali?.toISOString()
    };
    if (editingDetailId.value) {
      await updateDetail(editingDetailId.value, payload);
    } else {
      await addDetail(booking.value.id, payload);
    }
    showDetailDialog.value = false;
    loadBooking();
  } catch (err) {}
};

const submitCost = async () => {
  try {
    if (editingCostId.value) {
      await updateCost(editingCostId.value, costForm.value);
    } else {
      await addCost(costForm.value.booking_detail_id, costForm.value);
    }
    showCostDialog.value = false;
    loadBooking();
  } catch (err) {}
};

// Modification Submits
const submitExtend = async () => {
  try {
    const payload = {
      ...extendForm.value,
      tgl_sewa: extendForm.value.tgl_sewa instanceof Date ? extendForm.value.tgl_sewa.toISOString() : extendForm.value.tgl_sewa,
      tgl_kembali: extendForm.value.tgl_kembali instanceof Date ? extendForm.value.tgl_kembali.toISOString() : extendForm.value.tgl_kembali,
    };
    await extend(booking.value.id, payload);
    showExtendDialog.value = false;
    loadBooking();
  } catch (err) {}
};

const submitRolling = async () => {
  try {
    const payload = {
      ...rollingForm.value,
      tgl_rolling: rollingForm.value.tgl_rolling instanceof Date ? rollingForm.value.tgl_rolling.toISOString() : rollingForm.value.tgl_rolling,
      tgl_kembali: rollingForm.value.tgl_kembali instanceof Date ? rollingForm.value.tgl_kembali.toISOString() : rollingForm.value.tgl_kembali,
    };
    await rolling(booking.value.id, payload);
    showRollingDialog.value = false;
    loadBooking();
  } catch (err) {}
};

const submitBatal = async () => {
  try {
    await cancel(booking.value.id, batalForm.value);
    showBatalDialog.value = false;
    loadBooking();
  } catch (err) {}
};

const submitStopEarly = async () => {
  try {
    const payload = {
      ...stopEarlyForm.value,
      tgl_stop: stopEarlyForm.value.tgl_stop?.toISOString()
    };
    await stopEarly(booking.value.id, payload);
    showStopEarlyDialog.value = false;
    loadBooking();
  } catch (err) {}
};

const submitAdditionalCost = async () => {
  try {
    await addAdditionalCost(booking.value.id, additionalCostForm.value);
    showAdditionalCostDialog.value = false;
    loadBooking();
  } catch (err) {}
};

const onHandle = () => openHandleDialog();

const openPaymentDialog = () => {
  paymentForm.value = { payment_account_id: null, amount: null, payment_type: 'cicilan', catatan: '' };
  paymentFormErrors.value = {};
  showPaymentDialog.value = true;
};

// E4: Checkout & Complete dialogs
const showCheckoutDialog = ref(false);
const showCompleteDialog = ref(false);
const checkoutSkip = ref(false);
const completeSkip = ref(false);

const submitCheckout = async () => {
  try {
    await checkout(booking.value.id, { skip_inspection: checkoutSkip.value });
    showCheckoutDialog.value = false;
    loadBooking();
  } catch (err) {}
};

const submitComplete = async () => {
  try {
    await complete(booking.value.id, { skip_inspection: completeSkip.value });
    showCompleteDialog.value = false;
    loadBooking();
  } catch (err) {}
};

const submitPayment = async () => {
  paymentFormErrors.value = {};
  try {
    await addPayment(booking.value.id, paymentForm.value);
    showPaymentDialog.value = false;
    loadBooking();
  } catch (err) {
    if (err.response?.data?.errors) paymentFormErrors.value = err.response.data.errors;
  }
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
};
</script>

<template>
  <div class="booking-detail-container">
    <!-- Header & Action Bar -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" text rounded @click="router.back()" class="bg-white shadow-sm hover:shadow-md transition-all" />
        <div>
          <div class="flex flex-wrap items-center gap-3 mb-1">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Booking #{{ booking?.kode_booking || '...' }}</h1>
            <BookingStatusBadge v-if="booking" :status="booking.status" />
            <span
              v-if="booking?.is_late"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-600 border border-rose-200 animate-pulse"
            >
              <i class="pi pi-exclamation-triangle text-[10px]"></i> Terlambat Kembali
            </span>
          </div>
          <p class="text-sm text-slate-500 flex items-center gap-1.5">
            <i class="pi pi-clock text-xs text-slate-400"></i>
            {{ booking?.created_at ? new Date(booking.created_at).toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' }) : '-' }}
          </p>
        </div>
      </div>

      <div class="flex flex-wrap gap-2 w-full lg:w-auto">
        <Button
          v-if="booking && ['follow_up', 'confirm'].includes(booking.status)"
          label="Handle Booking"
          icon="pi pi-check-circle"
          class="bg-blue-600 hover:bg-blue-700 border-none px-5 py-2.5 rounded-xl font-semibold flex-1 lg:flex-none shadow-md shadow-blue-200 transition-all text-white"
          @click="onHandle"
          :loading="loading"
        />
        <Button
          v-if="booking && booking.status === 'waiting_list'"
          label="Checkout"
          icon="pi pi-sign-out"
          class="bg-emerald-600 hover:bg-emerald-700 border-none px-5 py-2.5 rounded-xl font-semibold flex-1 lg:flex-none shadow-md shadow-emerald-200 transition-all text-white"
          @click="checkoutSkip = false; showCheckoutDialog = true"
          :loading="loading"
        />
        <Button
          v-if="booking && booking.status === 'rental_unit'"
          label="Selesai"
          icon="pi pi-flag-fill"
          class="bg-violet-600 hover:bg-violet-700 border-none px-5 py-2.5 rounded-xl font-semibold flex-1 lg:flex-none shadow-md shadow-violet-200 transition-all text-white"
          @click="completeSkip = false; showCompleteDialog = true"
          :loading="loading"
        />
        <Button
          v-if="booking && !['selesai','batal','cancelled'].includes(booking.status)"
          label="Batalkan"
          icon="pi pi-ban"
          severity="danger"
          outlined
          class="px-4 py-2.5 rounded-xl font-semibold flex-1 lg:flex-none"
          @click="openBatalDialog"
          :loading="loading"
        />
        <Button label="Print" icon="pi pi-print" severity="secondary" outlined class="px-4 py-2.5 rounded-xl font-semibold border flex-1 lg:flex-none" disabled />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="!booking && loading" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 flex flex-col gap-6">
        <Skeleton width="100%" height="180px" borderRadius="16px" />
        <Skeleton width="100%" height="360px" borderRadius="16px" />
      </div>
      <Skeleton width="100%" height="460px" borderRadius="16px" />
    </div>

    <!-- Main Content Grid -->
    <div v-else-if="booking" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
      <!-- LEFT COLUMN -->
      <div class="lg:col-span-8 flex flex-col gap-6">

        <!-- Section: Customer & Booking Info -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/60 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600">
              <i class="pi pi-user"></i>
            </div>
            <h2 class="text-base font-bold text-slate-800">Informasi Booking</h2>
          </div>
          <div class="p-6 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-5">
            <div class="flex flex-col gap-1">
              <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Pelanggan</span>
              <span class="text-sm font-bold text-slate-800">{{ booking.customer?.nama }}</span>
            </div>
            <div class="flex flex-col gap-1">
              <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status Member</span>
              <div>
                <Tag :value="booking.customer?.status" :severity="booking.customer?.status === 'Blacklist' ? 'danger' : 'info'" class="px-2.5 py-0.5 text-xs font-semibold rounded-md" />
              </div>
            </div>
            <div class="flex flex-col gap-1">
              <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Tujuan</span>
              <span class="text-sm font-medium text-slate-700">{{ booking.tujuan || 'Dalam Kota' }}</span>
            </div>
            <div class="flex flex-col gap-1">
              <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Penjemputan</span>
              <span class="text-sm font-medium text-slate-700">{{ booking.alamat_penjemputan || 'Office' }}</span>
            </div>
          </div>
        </div>

        <!-- Section: Vehicles & Drivers -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/60 flex justify-between items-center">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600">
                <i class="pi pi-car"></i>
              </div>
              <h2 class="text-base font-bold text-slate-800">Unit Kendaraan & Driver</h2>
            </div>
            <Button
              label="Tambah Unit"
              icon="pi pi-plus"
              size="small"
              class="bg-emerald-600 hover:bg-emerald-700 border-none font-semibold rounded-lg px-3.5 py-2 text-white text-xs shadow-sm transition-all"
              @click="openDetailDialog"
              v-if="['follow_up', 'confirm'].includes(booking.status)"
            />
          </div>

          <div class="p-6">
            <div v-if="!validDetails.length" class="text-center py-14 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200">
              <i class="pi pi-inbox text-5xl text-slate-200 mb-3"></i>
              <p class="text-slate-400 font-semibold">Belum ada unit kendaraan yang di-assign.</p>
              <p class="text-slate-300 text-sm mt-1">Klik tombol Tambah Unit untuk memulai.</p>
            </div>

            <div v-else class="flex flex-col gap-5">
              <div v-for="detail in validDetails" :key="detail.id" class="rounded-xl border border-slate-100 overflow-hidden hover:border-slate-200 transition-colors">
                <!-- Unit Header -->
                <div class="p-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-5 bg-slate-50/40">
                  <div class="flex gap-4 items-center">
                    <div class="w-14 h-14 bg-white rounded-xl border border-slate-100 flex items-center justify-center shadow-sm flex-shrink-0">
                      <i class="pi pi-car text-2xl text-slate-300"></i>
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <h3 class="text-lg font-bold text-slate-800">{{ detail.unit?.merk }} {{ detail.unit?.tipe }}</h3>
                        <Button
                          icon="pi pi-pencil"
                          text
                          rounded
                          class="text-blue-500 hover:bg-blue-50 w-7 h-7 p-0 ml-1"
                          @click="openDetailDialog(detail)"
                          v-if="!isRentalUnit"
                          title="Edit Kendaraan"
                        />
                      </div>
                      <div class="flex items-center gap-2 mt-1">
                        <span class="inline-block bg-slate-800 text-white font-mono text-xs font-semibold px-2 py-0.5 rounded tracking-wider">{{ detail.unit?.no_polisi }}</span>
                        <span class="text-[11px] font-semibold text-slate-500 flex items-center gap-1 bg-slate-100 px-2 py-0.5 rounded-md">
                          <i class="pi pi-user text-[9px] text-slate-400"></i> {{ detail.unit?.rental_owner?.nama || 'Internal' }}
                        </span>
                      </div>
                      <div class="flex flex-wrap items-center gap-2 mt-3 text-xs font-semibold text-slate-500">
                        <span class="inline-flex items-center gap-1.5 bg-white px-2.5 py-1 rounded-md border border-slate-100 shadow-sm">
                          <i class="pi pi-calendar-plus text-blue-500 text-[10px]"></i>
                          {{ new Date(detail.tgl_sewa).toLocaleDateString('id-ID', { dateStyle: 'medium' }) }}
                          <span class="text-slate-400 font-normal ml-0.5">{{ new Date(detail.tgl_sewa).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}</span>
                        </span>
                        <i class="pi pi-arrow-right text-slate-300 text-[10px]"></i>
                        <span class="inline-flex items-center gap-1.5 bg-white px-2.5 py-1 rounded-md border border-slate-100 shadow-sm">
                          <i class="pi pi-calendar-minus text-rose-500 text-[10px]"></i>
                          {{ new Date(detail.tgl_kembali).toLocaleDateString('id-ID', { dateStyle: 'medium' }) }}
                          <span class="text-slate-400 font-normal ml-0.5">{{ new Date(detail.tgl_kembali).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}</span>
                        </span>
                      </div>
                    </div>
                  </div>

                  <div class="bg-white p-4 rounded-xl border border-slate-100 text-right min-w-[160px]">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">Biaya Unit</span>
                    <span class="text-xl font-bold text-slate-900">{{ formatCurrency(detail.harga_mobil) }}</span>
                    <div v-if="detail.diskon_mobil > 0" class="text-[11px] font-semibold text-rose-500 mt-0.5 flex items-center justify-end gap-1">
                      <i class="pi pi-tag text-[9px]"></i>
                      -{{ formatCurrency(detail.diskon_mobil) }}
                    </div>
                  </div>
                </div>

                <!-- Driver & Costs -->
                <div class="px-5 pb-5">
                  <div class="border-t border-slate-100 pt-4 flex flex-wrap justify-between items-center gap-3">
                    <div class="flex items-center gap-2.5">
                      <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                        <i class="pi pi-id-card text-sm"></i>
                      </div>
                      <div>
                        <span class="text-[10px] font-semibold text-slate-400 uppercase block leading-none mb-0.5">Driver</span>
                        <span class="text-sm font-bold text-slate-700">{{ detail.driver?.nama || 'Lepas Kunci' }}</span>
                      </div>
                    </div>

                    <Button
                      label="Tambah Biaya"
                      icon="pi pi-plus-circle"
                      size="small"
                      text
                      class="text-blue-600 font-semibold hover:bg-blue-50 px-3 py-1.5 rounded-lg text-xs transition-all"
                      @click="openCostDialog(detail.id)"
                      v-if="['follow_up', 'confirm'].includes(booking.status)"
                    />
                  </div>

                  <div v-if="detail.costs?.length" class="mt-4 bg-white rounded-lg border border-slate-100 overflow-hidden">
                    <DataTable :value="detail.costs" class="p-datatable-sm custom-mini-table">
                      <Column field="type" header="TIPE">
                        <template #body="{ data }">
                          <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-bold uppercase">{{ data.type }}</span>
                        </template>
                      </Column>
                      <Column field="label" header="KETERANGAN">
                        <template #body="{ data }">
                          <span class="text-sm text-slate-600">{{ data.label }}</span>
                        </template>
                      </Column>
                      <Column field="amount" header="JUMLAH" class="text-right">
                        <template #body="{ data }">
                          <span class="text-sm font-bold text-slate-800">{{ formatCurrency(data.amount) }}</span>
                        </template>
                      </Column>
                      <Column v-if="!isRentalUnit" bodyStyle="text-align: right; width: 40px" header="">
                        <template #body="{ data }">
                          <Button icon="pi pi-pencil" text rounded size="small" class="w-7 h-7 p-0 text-slate-400 hover:text-blue-500 hover:bg-blue-50" @click="openCostDialog(detail.id, data)" />
                        </template>
                      </Column>
                    </DataTable>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Section: Modification (Only for Rental Unit) -->
        <div v-if="isRentalUnit" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/60 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600">
              <i class="pi pi-cog"></i>
            </div>
            <h2 class="text-base font-bold text-slate-800">Modifikasi Transaksi</h2>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <Button 
                label="Perpanjang (Extend)" 
                icon="pi pi-calendar-plus" 
                severity="warning" 
                outlined 
                class="rounded-xl font-semibold text-sm py-3"
                @click="openExtendDialog"
              />
              <Button 
                label="Ganti Unit (Rolling)" 
                icon="pi pi-sync" 
                severity="warning" 
                outlined 
                class="rounded-xl font-semibold text-sm py-3"
                @click="openRollingDialog"
              />
              <Button 
                label="Stop Early" 
                icon="pi pi-stop-circle" 
                severity="danger" 
                outlined 
                class="rounded-xl font-semibold text-sm py-3"
                @click="openStopEarlyDialog"
              />
              <Button 
                label="Biaya/Diskon +" 
                icon="pi pi-plus-circle" 
                severity="info" 
                outlined 
                class="rounded-xl font-semibold text-sm py-3"
                @click="openAdditionalCostDialog"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN: Payment Summary -->
      <div class="lg:col-span-4 flex flex-col gap-6 lg:sticky lg:top-6">

        <!-- Financial Summary Card -->
        <div class="bg-slate-900 rounded-2xl shadow-xl p-6 text-white relative overflow-hidden">
          <div class="absolute -top-10 -right-10 w-32 h-32 bg-cyan-500/10 rounded-full blur-3xl"></div>
          <div class="flex items-center gap-2.5 mb-5">
            <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
              <i class="pi pi-receipt text-sm text-cyan-300"></i>
            </div>
            <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-200">Ringkasan Keuangan</h2>
          </div>
          <div class="flex flex-col gap-3 relative z-10">
            <div class="flex justify-between items-baseline">
              <span class="text-sm text-white/50">Total Tagihan</span>
              <span class="text-base font-bold text-white">{{ formatCurrency(booking.total_tagihan ?? booking.harga_dealing ?? 0) }}</span>
            </div>
            <div class="flex justify-between items-baseline">
              <span class="text-sm text-white/50">Total Dibayar</span>
              <span class="text-base font-bold text-emerald-400">{{ formatCurrency(booking.total_payments ?? 0) }}</span>
            </div>
            <div class="h-px bg-white/10 my-1"></div>
            <div class="p-4 bg-white/5 rounded-xl border border-white/5">
              <span class="text-[10px] font-bold uppercase tracking-widest block mb-1"
                :class="(booking.sisa_tagihan ?? 0) > 0 ? 'text-rose-300' : 'text-emerald-300'"
              >Sisa Tagihan</span>
              <span class="text-2xl font-bold tracking-tight"
                :class="(booking.sisa_tagihan ?? 0) > 0 ? 'text-rose-300' : 'text-emerald-300'"
              >{{ formatCurrency(booking.sisa_tagihan ?? (booking.harga_dealing - booking.dp) ?? 0) }}</span>
            </div>
          </div>
        </div>

        <!-- Payment List Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600">
                <i class="pi pi-money-bill text-sm"></i>
              </div>
              <h3 class="text-sm font-bold text-slate-800">Pembayaran</h3>
            </div>
            <Button
              label="Tambah"
              icon="pi pi-plus"
              size="small"
              class="bg-emerald-600 hover:bg-emerald-700 border-none text-white text-xs font-semibold px-3 py-1.5 rounded-lg"
              @click="openPaymentDialog"
              v-if="booking.status !== 'cancelled' && booking.status !== 'selesai'"
            />
          </div>

          <div v-if="!booking.payments?.length" class="p-6 text-center text-slate-400">
            <i class="pi pi-inbox text-3xl mb-2 opacity-30 block"></i>
            <p class="text-sm">Belum ada pembayaran.</p>
          </div>

          <div v-else class="divide-y divide-slate-50">
            <div v-for="p in booking.payments" :key="p.id" class="px-5 py-3.5 flex items-center justify-between gap-3">
              <div class="flex flex-col gap-0.5 min-w-0">
                <span class="text-[11px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md w-fit"
                  :class="{
                    'bg-blue-100 text-blue-700': p.payment_type === 'dp',
                    'bg-amber-100 text-amber-700': p.payment_type === 'cicilan',
                    'bg-emerald-100 text-emerald-700': p.payment_type === 'pelunasan',
                  }"
                >{{ p.payment_type }}</span>
                <span class="text-xs text-slate-500 truncate">
                  {{ p.paid_at ? new Date(p.paid_at).toLocaleDateString('id-ID', { dateStyle: 'medium' }) : '-' }}
                </span>
                <span v-if="p.catatan" class="text-xs text-slate-400 italic truncate">{{ p.catatan }}</span>
              </div>
              <span class="text-sm font-bold text-slate-800 whitespace-nowrap">{{ formatCurrency(p.amount) }}</span>
            </div>
          </div>
        </div>

        <!-- Notes Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
          <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
            <i class="pi pi-comments text-blue-500 text-sm"></i>
            Catatan Internal
          </h3>
          <div class="bg-slate-50 p-3.5 rounded-lg border border-slate-100 text-sm text-slate-600 leading-relaxed">
            {{ booking.catatan || 'Tidak ada catatan tambahan.' }}
          </div>
        </div>
      </div>
    </div>

    <!-- ======= DIALOG: Checkout (E4) ======= -->
    <Dialog v-model:visible="showCheckoutDialog" header="Konfirmasi Checkout" :style="{ width: '420px' }" modal class="custom-dialog">
      <div class="flex flex-col gap-5 pt-2">
        <div class="flex items-start gap-4 p-4 bg-emerald-50 border border-emerald-100 rounded-xl">
          <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600 flex-shrink-0">
            <i class="pi pi-sign-out text-lg"></i>
          </div>
          <div>
            <p class="font-bold text-slate-800 mb-1">Unit akan di-checkout sekarang</p>
            <p class="text-sm text-slate-500">Status booking akan berubah ke <strong>Rental Unit</strong> dan status unit menjadi <strong>Out</strong>.</p>
          </div>
        </div>
        <div class="p-4 bg-amber-50 border border-amber-100 rounded-xl">
          <p class="text-sm font-semibold text-amber-800 mb-3 flex items-center gap-2">
            <i class="pi pi-camera text-amber-600"></i>
            Apakah kendaraan sudah dilakukan Cek Fisik keberangkatan?
          </p>
          <div class="flex flex-col gap-2">
            <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-amber-100 transition-colors"
              :class="!checkoutSkip ? 'bg-amber-100 ring-1 ring-amber-300' : ''">
              <input type="radio" :value="false" v-model="checkoutSkip" class="accent-emerald-600 w-4 h-4" />
              <span class="text-sm font-semibold text-slate-700">Ya, cek fisik sudah dilakukan</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-amber-100 transition-colors"
              :class="checkoutSkip ? 'bg-amber-100 ring-1 ring-amber-300' : ''">
              <input type="radio" :value="true" v-model="checkoutSkip" class="accent-amber-600 w-4 h-4" />
              <span class="text-sm font-semibold text-slate-700">Lewati cek fisik, lanjutkan checkout</span>
            </label>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="showCheckoutDialog = false" />
          <Button label="Proses Checkout" icon="pi pi-sign-out" class="bg-emerald-600 hover:bg-emerald-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all" @click="submitCheckout" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Selesai / Complete (E4) ======= -->
    <Dialog v-model:visible="showCompleteDialog" header="Konfirmasi Selesai Sewa" :style="{ width: '420px' }" modal class="custom-dialog">
      <div class="flex flex-col gap-5 pt-2">
        <div class="flex items-start gap-4 p-4 bg-violet-50 border border-violet-100 rounded-xl">
          <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center text-violet-600 flex-shrink-0">
            <i class="pi pi-flag-fill text-lg"></i>
          </div>
          <div>
            <p class="font-bold text-slate-800 mb-1">Sewa akan diselesaikan</p>
            <p class="text-sm text-slate-500">Status booking berubah ke <strong>Selesai</strong> dan status unit kembali menjadi <strong>Aktif</strong>.</p>
          </div>
        </div>
        <div class="p-4 bg-amber-50 border border-amber-100 rounded-xl">
          <p class="text-sm font-semibold text-amber-800 mb-3 flex items-center gap-2">
            <i class="pi pi-camera text-amber-600"></i>
            Apakah kendaraan sudah dilakukan Cek Fisik kepulangan?
          </p>
          <div class="flex flex-col gap-2">
            <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-amber-100 transition-colors"
              :class="!completeSkip ? 'bg-amber-100 ring-1 ring-amber-300' : ''">
              <input type="radio" :value="false" v-model="completeSkip" class="accent-violet-600 w-4 h-4" />
              <span class="text-sm font-semibold text-slate-700">Ya, cek fisik sudah dilakukan</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-amber-100 transition-colors"
              :class="completeSkip ? 'bg-amber-100 ring-1 ring-amber-300' : ''">
              <input type="radio" :value="true" v-model="completeSkip" class="accent-amber-600 w-4 h-4" />
              <span class="text-sm font-semibold text-slate-700">Lewati cek fisik, selesaikan sewa</span>
            </label>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="showCompleteDialog = false" />
          <Button label="Selesaikan Sewa" icon="pi pi-flag-fill" class="bg-violet-600 hover:bg-violet-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all" @click="submitComplete" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Handle Booking (E3) ======= -->
    <Dialog v-model:visible="showHandleDialog" header="Handle Booking" :style="{ width: '760px' }" modal :breakpoints="{ '800px': '95vw' }" class="custom-dialog">
      <div class="flex flex-col gap-6 pt-2">

        <!-- Unit & Driver -->
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Unit & Driver</legend>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Unit Kendaraan *</label>
              <Dropdown v-model="handleForm.unit_id" :options="units" optionLabel="no_polisi" optionValue="id" placeholder="Pilih unit..." filter @change="onHandleUnitChange" class="w-full" :class="{ 'p-invalid': handleFormErrors.unit_id }" />
              <small class="p-error" v-if="handleFormErrors.unit_id">{{ handleFormErrors.unit_id[0] }}</small>
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Driver <span class="text-slate-400 font-normal">(opsional)</span></label>
              <Dropdown v-model="handleForm.driver_id" :options="drivers" optionLabel="nama" optionValue="id" placeholder="Lepas kunci / Pilih driver" filter showClear class="w-full" />
            </div>
          </div>
        </fieldset>

        <!-- Durasi -->
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Durasi Sewa</legend>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Lama Sewa *</label>
              <InputNumber v-model="handleForm.lama_sewa" :min="1" placeholder="Jumlah" class="w-full" :class="{ 'p-invalid': handleFormErrors.lama_sewa }" />
              <small class="p-error" v-if="handleFormErrors.lama_sewa">{{ handleFormErrors.lama_sewa[0] }}</small>
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Paket Sewa *</label>
              <Dropdown v-model="handleForm.paket_sewa" :options="paketOptions" optionLabel="label" optionValue="value" class="w-full" />
            </div>
          </div>
        </fieldset>

        <!-- Harga & Pricing Mode -->
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Harga & Mode Tagihan</legend>
          <div class="flex flex-col gap-4 mt-2">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-600">Harga Mobil / Periode *</label>
                <InputNumber v-model="handleForm.harga_mobil" mode="currency" currency="IDR" locale="id-ID" class="w-full" :class="{ 'p-invalid': handleFormErrors.harga_mobil }" />
                <small class="p-error" v-if="handleFormErrors.harga_mobil">{{ handleFormErrors.harga_mobil[0] }}</small>
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-600">Diskon <span class="text-slate-400 font-normal">(opsional)</span></label>
                <InputNumber v-model="handleForm.diskon_mobil" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
              </div>
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Mode Pricing *</label>
              <SelectButton v-model="handleForm.pricing_mode" :options="pricingModeOptions" optionLabel="label" optionValue="value" class="w-full" />
            </div>

            <!-- All In section -->
            <div v-if="handleForm.pricing_mode === 'all_in'" class="flex flex-col gap-3 p-3 bg-cyan-50 border border-cyan-100 rounded-xl">
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-cyan-700">Pricing Package <span class="text-slate-400 font-normal">(opsional, atau isi manual)</span></label>
                <Dropdown v-model="handleForm.pricing_package_id" :options="packageOptions" optionLabel="label" optionValue="id" placeholder="Pilih paket..." showClear class="w-full" />
              </div>
              <div v-if="!handleForm.pricing_package_id" class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-cyan-700">Harga All In (Override) *</label>
                <InputNumber v-model="handleForm.harga_all_in" mode="currency" currency="IDR" locale="id-ID" class="w-full" :class="{ 'p-invalid': handleFormErrors.harga_all_in }" />
                <small class="p-error" v-if="handleFormErrors.harga_all_in">{{ handleFormErrors.harga_all_in[0] }}</small>
              </div>
            </div>
          </div>
        </fieldset>

        <!-- Biaya Operasional -->
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Biaya Operasional</legend>
          <div class="flex flex-col gap-3 mt-2">
            <div v-if="!handleForm.costs.length" class="text-center text-sm text-slate-400 py-3">
              Belum ada biaya. Klik + untuk menambah.
            </div>
            <div v-for="(cost, idx) in handleForm.costs" :key="idx" class="grid grid-cols-12 gap-2 items-start">
              <div class="col-span-4">
                <Dropdown
                  v-model="cost.cost_type_id"
                  :options="costTypeOptions"
                  optionLabel="label"
                  optionValue="id"
                  placeholder="Tipe..."
                  class="w-full"
                  showClear
                  @change="onCostTypeChange(idx, cost.cost_type_id)"
                />
              </div>
              <div class="col-span-3">
                <InputText v-model="cost.label" placeholder="Keterangan" class="w-full" />
              </div>
              <div class="col-span-3">
                <InputNumber v-model="cost.amount" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
              </div>
              <div class="col-span-1">
                <InputText
                  v-if="costTypesMaster.find(c => c.id === cost.cost_type_id)?.require_description"
                  v-model="cost.keterangan"
                  placeholder="Detail..."
                  class="w-full text-xs"
                  title="Keterangan wajib untuk tipe ini"
                />
              </div>
              <div class="col-span-1 flex items-start pt-1">
                <Button icon="pi pi-times" text rounded severity="danger" size="small" class="w-7 h-7 p-0" @click="removeCostRow(idx)" />
              </div>
            </div>
            <Button label="+ Tambah Biaya" icon="pi pi-plus" text size="small" class="text-blue-600 font-semibold self-start" @click="addCostRow" />
          </div>
        </fieldset>

        <!-- Lokasi -->
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Lokasi</legend>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Alamat Penjemputan</label>
              <Textarea v-model="handleForm.alamat_penjemputan" rows="2" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Tujuan</label>
              <Textarea v-model="handleForm.tujuan" rows="2" class="w-full" />
            </div>
          </div>
        </fieldset>

        <!-- Kalkulasi Real-time -->
        <div class="bg-slate-900 rounded-xl p-4 text-white">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Kalkulasi Real-time</p>
          <div class="flex flex-col gap-2 text-sm">
            <div class="flex justify-between">
              <span class="text-white/60">Harga Sewa <span class="text-white/40 text-xs">({{ handleForm.lama_sewa || 0 }} × {{ formatCurrency((handleForm.harga_mobil||0) - (handleForm.diskon_mobil||0)) }})</span></span>
              <span class="font-semibold">{{ formatCurrency(hargaSewa) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/60">Total Biaya Ops</span>
              <span class="font-semibold">{{ formatCurrency(totalBiayaOps) }}</span>
            </div>
            <div class="h-px bg-white/10 my-1"></div>
            <div class="flex justify-between text-white/60">
              <span>Grand Total Internal</span>
              <span class="font-semibold text-white">{{ formatCurrency(grandTotalInternal) }}</span>
            </div>
            <div class="flex justify-between items-center mt-1">
              <span class="font-bold text-cyan-300">Tagihan Konsumen</span>
              <span class="text-xl font-bold text-cyan-300">{{ formatCurrency(tagihanKonsumen) }}</span>
            </div>
            <p v-if="handleForm.pricing_mode === 'all_in'" class="text-[10px] text-slate-500 mt-1">* Tagihan konsumen = harga All In. Biaya ops hanya untuk catatan internal.</p>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="showHandleDialog = false" />
          <Button label="Proses Handle" icon="pi pi-check-circle" class="bg-blue-600 hover:bg-blue-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all" @click="submitHandle" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Tambah/Edit Unit & Driver ======= -->
    <Dialog v-model:visible="showDetailDialog" :header="editingDetailId ? 'Edit Unit & Driver' : 'Tambah Unit & Driver'" :style="{ width: '600px' }" modal :breakpoints="{ '640px': '95vw' }" class="custom-dialog">
      <div class="flex flex-col gap-6 pt-2">
        <!-- Section: Kendaraan & Driver -->
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Kendaraan & Driver</legend>
          <div class="flex flex-col gap-4 mt-1">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                <i class="pi pi-car text-slate-400 text-[11px]"></i> Unit Kendaraan
              </label>
              <Dropdown v-model="detailForm.unit_id" :options="units" optionLabel="no_polisi" optionValue="id" placeholder="Pilih unit kendaraan" filter @change="onUnitChange" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                <i class="pi pi-id-card text-slate-400 text-[11px]"></i> Driver
                <span class="text-slate-300 font-normal">(opsional)</span>
              </label>
              <Dropdown v-model="detailForm.driver_id" :options="drivers" optionLabel="nama" optionValue="id" placeholder="Lepas kunci / Pilih driver" filter showClear class="w-full" />
            </div>
          </div>
        </fieldset>

        <!-- Section: Periode Sewa -->
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Periode Sewa</legend>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-1">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                <i class="pi pi-calendar-plus text-blue-500 text-[11px]"></i> Mulai Sewa
              </label>
              <Calendar v-model="detailForm.tgl_sewa" showTime hourFormat="24" dateFormat="dd M yy" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                <i class="pi pi-calendar-minus text-rose-500 text-[11px]"></i> Selesai Sewa
              </label>
              <Calendar v-model="detailForm.tgl_kembali" showTime hourFormat="24" dateFormat="dd M yy" class="w-full" />
            </div>
          </div>
        </fieldset>

        <!-- Section: Harga -->
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Harga</legend>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-1">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                <i class="pi pi-wallet text-emerald-500 text-[11px]"></i> Harga Mobil
              </label>
              <InputNumber v-model="detailForm.harga_mobil" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                <i class="pi pi-percentage text-orange-500 text-[11px]"></i> Diskon
              </label>
              <InputNumber v-model="detailForm.diskon_mobil" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
            </div>
          </div>
        </fieldset>
      </div>

      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="showDetailDialog = false" />
          <Button :label="editingDetailId ? 'Simpan Perubahan' : 'Simpan Unit'" icon="pi pi-check" class="bg-emerald-600 hover:bg-emerald-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all" @click="submitDetail" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Biaya Operasional ======= -->
    <Dialog v-model:visible="showCostDialog" :header="editingCostId ? 'Edit Biaya Operasional' : 'Tambah Biaya Operasional'" :style="{ width: '460px' }" modal :breakpoints="{ '640px': '95vw' }" class="custom-dialog">
      <div class="flex flex-col gap-5 pt-2">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
            <i class="pi pi-list text-slate-400 text-[11px]"></i> Tipe Biaya
          </label>
          <Dropdown v-model="costForm.type" :options="costTypes" optionLabel="label" optionValue="value" class="w-full" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
            <i class="pi pi-pencil text-slate-400 text-[11px]"></i> Keterangan
          </label>
          <InputText v-model="costForm.label" placeholder="Misal: Fee Driver Luar Kota" class="w-full" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
            <i class="pi pi-wallet text-slate-400 text-[11px]"></i> Nominal
          </label>
          <InputNumber v-model="costForm.amount" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
        </div>
      </div>

      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="showCostDialog = false" />
          <Button :label="editingCostId ? 'Simpan Perubahan' : 'Tambahkan'" icon="pi pi-check" class="bg-blue-600 hover:bg-blue-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all" @click="submitCost" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Perpanjang (Extend) — E5 Revamp ======= -->
    <Dialog v-model:visible="showExtendDialog" header="Perpanjang Sewa (Extend)" :style="{ width: '700px' }" modal :breakpoints="{ '750px': '95vw' }" class="custom-dialog">
      <div class="flex flex-col gap-5 pt-2">
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Unit & Driver</legend>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Unit Kendaraan *</label>
              <Dropdown v-model="extendForm.unit_id" :options="units" optionLabel="no_polisi" optionValue="id" placeholder="Pilih unit" filter @change="e => { const u = units.find(u=>u.id===e.value); if(u) extendForm.harga_mobil=u.harga_1_hari||0; }" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Driver <span class="text-slate-400 font-normal">(opsional)</span></label>
              <Dropdown v-model="extendForm.driver_id" :options="drivers" optionLabel="nama" optionValue="id" placeholder="Lepas kunci / Pilih driver" filter showClear class="w-full" />
            </div>
          </div>
        </fieldset>
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Periode & Durasi</legend>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-2">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Mulai (fixed H+1)</label>
              <Calendar v-model="extendForm.tgl_sewa" showTime hourFormat="24" dateFormat="dd M yy" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Selesai *</label>
              <Calendar v-model="extendForm.tgl_kembali" showTime hourFormat="24" dateFormat="dd M yy" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Lama *</label>
              <InputNumber v-model="extendForm.lama_sewa" :min="1" placeholder="Jml" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Paket *</label>
              <Dropdown v-model="extendForm.paket_sewa" :options="paketOptions" optionLabel="label" optionValue="value" class="w-full" />
            </div>
          </div>
        </fieldset>
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Harga & Mode Tagihan</legend>
          <div class="flex flex-col gap-3 mt-2">
            <div class="grid grid-cols-2 gap-3">
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-600">Harga Mobil *</label>
                <InputNumber v-model="extendForm.harga_mobil" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-600">Diskon</label>
                <InputNumber v-model="extendForm.diskon_mobil" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
              </div>
            </div>
            <SelectButton v-model="extendForm.pricing_mode" :options="pricingModeOptions" optionLabel="label" optionValue="value" class="w-full" />
            <div v-if="extendForm.pricing_mode === 'all_in'" class="flex flex-col gap-3 p-3 bg-cyan-50 border border-cyan-100 rounded-xl">
              <Dropdown v-model="extendForm.pricing_package_id" :options="packageOptions" optionLabel="label" optionValue="id" placeholder="Pilih paket..." showClear class="w-full" />
              <InputNumber v-if="!extendForm.pricing_package_id" v-model="extendForm.harga_all_in" mode="currency" currency="IDR" locale="id-ID" placeholder="Harga All In manual" class="w-full" />
            </div>
          </div>
        </fieldset>
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Biaya Operasional</legend>
          <div class="flex flex-col gap-2 mt-2">
            <div v-if="!extendForm.costs.length" class="text-center text-sm text-slate-400 py-2">Belum ada biaya.</div>
            <div v-for="(cost, idx) in extendForm.costs" :key="idx" class="grid grid-cols-12 gap-2 items-center">
              <div class="col-span-4"><Dropdown v-model="cost.cost_type_id" :options="costTypeOptions" optionLabel="label" optionValue="id" placeholder="Tipe" class="w-full" showClear @change="onCostTypeChange(idx, cost.cost_type_id)" /></div>
              <div class="col-span-4"><InputText v-model="cost.label" placeholder="Keterangan" class="w-full" /></div>
              <div class="col-span-3"><InputNumber v-model="cost.amount" mode="currency" currency="IDR" locale="id-ID" class="w-full" /></div>
              <div class="col-span-1"><Button icon="pi pi-times" text rounded severity="danger" size="small" class="w-7 h-7 p-0" @click="extendForm.costs.splice(idx,1)" /></div>
            </div>
            <Button label="+ Tambah Biaya" text size="small" class="text-blue-600 font-semibold self-start" @click="extendForm.costs.push({cost_type_id:null,label:'',amount:0,keterangan:''})" />
          </div>
        </fieldset>
        <div class="bg-slate-900 rounded-xl p-4 text-white">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2">Kalkulasi</p>
          <div class="flex flex-col gap-1.5 text-sm">
            <div class="flex justify-between"><span class="text-white/60">Harga Sewa</span><span>{{ formatCurrency(extendHargaSewa) }}</span></div>
            <div class="flex justify-between"><span class="text-white/60">Biaya Ops</span><span>{{ formatCurrency(extendTotalBiaya) }}</span></div>
            <div class="h-px bg-white/10 my-1"></div>
            <div class="flex justify-between"><span class="font-bold text-cyan-300">Tagihan Konsumen</span><span class="text-lg font-bold text-cyan-300">{{ formatCurrency(extendTagihan) }}</span></div>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text @click="showExtendDialog = false" />
          <Button label="Proses Extend" icon="pi pi-check" class="bg-amber-600 border-none text-white px-6 rounded-lg font-semibold" @click="submitExtend" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Ganti Unit (Rolling) — E5 Revamp ======= -->
    <Dialog v-model:visible="showRollingDialog" :header="rollingStep === 1 ? 'Ganti Unit (Rolling) — Step 1: Adjust Unit Lama' : 'Ganti Unit (Rolling) — Step 2: Detail Unit Baru'" :style="{ width: '700px' }" modal :breakpoints="{ '750px': '95vw' }" class="custom-dialog">
      <!-- Step indicator -->
      <div class="flex items-center gap-2 mb-4">
        <div class="flex items-center gap-1.5">
          <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold" :class="rollingStep >= 1 ? 'bg-amber-500 text-white' : 'bg-slate-200 text-slate-400'">1</div>
          <span class="text-xs font-semibold" :class="rollingStep === 1 ? 'text-amber-600' : 'text-slate-400'">Adjust Unit Lama</span>
        </div>
        <div class="flex-1 h-px bg-slate-200"></div>
        <div class="flex items-center gap-1.5">
          <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold" :class="rollingStep >= 2 ? 'bg-amber-500 text-white' : 'bg-slate-200 text-slate-400'">2</div>
          <span class="text-xs font-semibold" :class="rollingStep === 2 ? 'text-amber-600' : 'text-slate-400'">Detail Unit Baru</span>
        </div>
      </div>

      <!-- Step 1 -->
      <div v-if="rollingStep === 1" class="flex flex-col gap-4 pt-1">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Unit Yang Akan Diganti *</label>
          <Dropdown v-model="rollingForm.booking_detail_id" :options="activeDetails" optionLabel="unit.no_polisi" optionValue="id" placeholder="Pilih unit aktif" class="w-full" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Tanggal & Jam Rolling (unit lama selesai di sini) *</label>
          <Calendar v-model="rollingForm.tgl_rolling" showTime hourFormat="24" dateFormat="dd M yy" class="w-full" />
        </div>
        <div class="grid grid-cols-3 gap-3">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-600">Lama Sewa Lama (revisi)</label>
            <InputNumber v-model="rollingForm.lama_sewa_lama" :min="1" class="w-full" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-600">Harga Lama (revisi)</label>
            <InputNumber v-model="rollingForm.harga_mobil_lama" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-600">Diskon Lama (revisi)</label>
            <InputNumber v-model="rollingForm.diskon_mobil_lama" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
          </div>
        </div>
        <p class="text-xs text-slate-400">* Field revisi opsional — hanya isi jika harga unit lama perlu dikoreksi.</p>
      </div>

      <!-- Step 2 -->
      <div v-if="rollingStep === 2" class="flex flex-col gap-4 pt-1">
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Unit Baru & Driver</legend>
          <div class="grid grid-cols-2 gap-3 mt-2">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Unit Baru *</label>
              <Dropdown v-model="rollingForm.unit_id" :options="units" optionLabel="no_polisi" optionValue="id" placeholder="Pilih unit baru" filter @change="e => { const u = units.find(u=>u.id===e.value); if(u) rollingForm.harga_mobil=u.harga_1_hari||0; }" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Driver</label>
              <Dropdown v-model="rollingForm.driver_id" :options="drivers" optionLabel="nama" optionValue="id" placeholder="Lepas kunci / pilih" filter showClear class="w-full" />
            </div>
          </div>
        </fieldset>
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Durasi & Harga Baru</legend>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-2">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Selesai *</label>
              <Calendar v-model="rollingForm.tgl_kembali" showTime hourFormat="24" dateFormat="dd M yy" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Lama *</label>
              <InputNumber v-model="rollingForm.lama_sewa" :min="1" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Paket *</label>
              <Dropdown v-model="rollingForm.paket_sewa" :options="paketOptions" optionLabel="label" optionValue="value" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Harga Mobil *</label>
              <InputNumber v-model="rollingForm.harga_mobil" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
            </div>
          </div>
          <div class="mt-3">
            <SelectButton v-model="rollingForm.pricing_mode" :options="pricingModeOptions" optionLabel="label" optionValue="value" class="w-full" />
            <div v-if="rollingForm.pricing_mode === 'all_in'" class="flex flex-col gap-2 mt-3 p-3 bg-cyan-50 border border-cyan-100 rounded-xl">
              <Dropdown v-model="rollingForm.pricing_package_id" :options="packageOptions" optionLabel="label" optionValue="id" placeholder="Pilih paket..." showClear class="w-full" />
              <InputNumber v-if="!rollingForm.pricing_package_id" v-model="rollingForm.harga_all_in" mode="currency" currency="IDR" locale="id-ID" placeholder="Harga All In manual" class="w-full" />
            </div>
          </div>
        </fieldset>
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Biaya Operasional</legend>
          <div class="flex flex-col gap-2 mt-2">
            <div v-if="!rollingForm.costs.length" class="text-center text-sm text-slate-400 py-2">Belum ada biaya.</div>
            <div v-for="(cost, idx) in rollingForm.costs" :key="idx" class="grid grid-cols-12 gap-2 items-center">
              <div class="col-span-4"><Dropdown v-model="cost.cost_type_id" :options="costTypeOptions" optionLabel="label" optionValue="id" placeholder="Tipe" class="w-full" showClear @change="onCostTypeChange(idx, cost.cost_type_id)" /></div>
              <div class="col-span-4"><InputText v-model="cost.label" placeholder="Keterangan" class="w-full" /></div>
              <div class="col-span-3"><InputNumber v-model="cost.amount" mode="currency" currency="IDR" locale="id-ID" class="w-full" /></div>
              <div class="col-span-1"><Button icon="pi pi-times" text rounded severity="danger" size="small" class="w-7 h-7 p-0" @click="rollingForm.costs.splice(idx,1)" /></div>
            </div>
            <Button label="+ Tambah Biaya" text size="small" class="text-blue-600 font-semibold self-start" @click="rollingForm.costs.push({cost_type_id:null,label:'',amount:0,keterangan:''})" />
          </div>
        </fieldset>
        <div class="bg-slate-900 rounded-xl p-4 text-white">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2">Kalkulasi Unit Baru</p>
          <div class="flex flex-col gap-1.5 text-sm">
            <div class="flex justify-between"><span class="text-white/60">Harga Sewa</span><span>{{ formatCurrency(rollingHargaSewa) }}</span></div>
            <div class="flex justify-between"><span class="text-white/60">Biaya Ops</span><span>{{ formatCurrency(rollingTotalBiaya) }}</span></div>
            <div class="h-px bg-white/10 my-1"></div>
            <div class="flex justify-between"><span class="font-bold text-cyan-300">Tagihan Konsumen</span><span class="text-lg font-bold text-cyan-300">{{ formatCurrency(rollingTagihan) }}</span></div>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text @click="showRollingDialog = false" />
          <Button v-if="rollingStep === 1" label="Lanjut →" icon="pi pi-arrow-right" iconPos="right" class="bg-amber-500 border-none text-white px-6 rounded-lg font-semibold" @click="rollingStep = 2" />
          <Button v-if="rollingStep === 2" label="← Kembali" icon="pi pi-arrow-left" text class="text-slate-500" @click="rollingStep = 1" />
          <Button v-if="rollingStep === 2" label="Proses Rolling" icon="pi pi-check" class="bg-amber-600 border-none text-white px-6 rounded-lg font-semibold" @click="submitRolling" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Stop Early ======= -->
    <Dialog v-model:visible="showStopEarlyDialog" header="Hentikan Sewa Awal (Stop Early)" :style="{ width: '460px' }" modal class="custom-dialog">
      <div class="flex flex-col gap-5 pt-2">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Pilih Unit</label>
          <Dropdown v-model="stopEarlyForm.booking_detail_id" :options="activeDetails" optionLabel="unit.no_polisi" optionValue="id" placeholder="Pilih unit" class="w-full" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Tanggal Stop</label>
          <Calendar v-model="stopEarlyForm.tgl_stop" showTime hourFormat="24" dateFormat="dd M yy" class="w-full" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600 text-rose-500">Nominal Refund / Diskon (Rp)</label>
          <InputNumber v-model="stopEarlyForm.refund_amount" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text @click="showStopEarlyDialog = false" />
          <Button label="Proses Stop" icon="pi pi-check" severity="danger" class="px-6" @click="submitStopEarly" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Batalkan Booking (E5) ======= -->
    <Dialog v-model:visible="showBatalDialog" header="Batalkan Booking" :style="{ width: '440px' }" modal class="custom-dialog">
      <div class="flex flex-col gap-5 pt-2">
        <div class="flex items-start gap-3 p-4 bg-rose-50 border border-rose-100 rounded-xl">
          <div class="w-9 h-9 rounded-xl bg-rose-100 flex items-center justify-center text-rose-600 flex-shrink-0">
            <i class="pi pi-ban"></i>
          </div>
          <div>
            <p class="font-bold text-slate-800 mb-0.5">Booking akan dibatalkan</p>
            <p class="text-sm text-slate-500">Status berubah ke <strong>Batal</strong>. Jika ada pembayaran yang sudah masuk, isi nominal refund di bawah.</p>
          </div>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Nominal Refund <span class="text-slate-400 font-normal">(opsional)</span></label>
          <InputNumber v-model="batalForm.refund_amount" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
        </div>
        <div v-if="batalForm.refund_amount > 0" class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Akun Pembayaran Refund *</label>
          <Dropdown v-model="batalForm.payment_account_id" :options="accountOptions" optionLabel="name" optionValue="id" placeholder="Pilih akun..." class="w-full" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Keterangan Pembatalan <span class="text-slate-400 font-normal">(opsional)</span></label>
          <Textarea v-model="batalForm.refund_keterangan" rows="2" placeholder="Alasan pembatalan..." class="w-full" />
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Kembali" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="showBatalDialog = false" />
          <Button label="Ya, Batalkan Booking" icon="pi pi-ban" severity="danger" class="px-6 py-2.5 rounded-lg font-semibold" @click="submitBatal" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Tambah Pembayaran ======= -->
    <Dialog v-model:visible="showPaymentDialog" header="Tambah Pembayaran" :style="{ width: '460px' }" modal class="custom-dialog">
      <div class="flex flex-col gap-5 pt-2">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Tipe Pembayaran *</label>
          <Dropdown v-model="paymentForm.payment_type" :options="paymentTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
          <small class="p-error" v-if="paymentFormErrors.payment_type">{{ paymentFormErrors.payment_type[0] }}</small>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Akun Tujuan Pembayaran *</label>
          <Dropdown v-model="paymentForm.payment_account_id" :options="accountOptions" optionLabel="name" optionValue="id" placeholder="Pilih akun..." class="w-full" :class="{ 'p-invalid': paymentFormErrors.payment_account_id }" />
          <small class="p-error" v-if="paymentFormErrors.payment_account_id">{{ paymentFormErrors.payment_account_id[0] }}</small>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Nominal *</label>
          <InputNumber v-model="paymentForm.amount" mode="currency" currency="IDR" locale="id-ID" class="w-full" :class="{ 'p-invalid': paymentFormErrors.amount }" />
          <small class="p-error" v-if="paymentFormErrors.amount">{{ paymentFormErrors.amount[0] }}</small>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Catatan (opsional)</label>
          <Textarea v-model="paymentForm.catatan" rows="2" placeholder="Transfer via BCA, bukti sudah dikirim WA..." class="w-full" />
        </div>
        <div v-if="booking?.sisa_tagihan != null" class="p-3 bg-slate-50 rounded-xl border border-slate-100 text-sm">
          <div class="flex justify-between text-slate-500 mb-1">
            <span>Sisa tagihan saat ini</span>
            <span class="font-bold text-rose-500">{{ formatCurrency(booking.sisa_tagihan) }}</span>
          </div>
          <div v-if="paymentForm.amount" class="flex justify-between text-slate-500">
            <span>Sisa setelah pembayaran ini</span>
            <span class="font-bold" :class="(booking.sisa_tagihan - paymentForm.amount) <= 0 ? 'text-emerald-600' : 'text-amber-600'">
              {{ formatCurrency(Math.max(0, booking.sisa_tagihan - paymentForm.amount)) }}
            </span>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="showPaymentDialog = false" />
          <Button label="Simpan Pembayaran" icon="pi pi-check" class="bg-emerald-600 hover:bg-emerald-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all" @click="submitPayment" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Additional Cost/Discount ======= -->
    <Dialog v-model:visible="showAdditionalCostDialog" header="Tambah Biaya/Diskon Tambahan" :style="{ width: '460px' }" modal class="custom-dialog">
      <div class="flex flex-col gap-5 pt-2">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Tipe</label>
          <Dropdown v-model="additionalCostForm.type" :options="costTypes" optionLabel="label" optionValue="value" class="w-full" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Keterangan</label>
          <InputText v-model="additionalCostForm.label" placeholder="Misal: Denda Keterlambatan" class="w-full" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Nominal</label>
          <InputNumber v-model="additionalCostForm.amount" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
        </div>
        <div class="flex items-center gap-2">
          <input type="checkbox" v-model="additionalCostForm.is_discount" id="is_discount" class="w-4 h-4" />
          <label for="is_discount" class="text-sm font-semibold text-slate-600">Ini adalah Diskon (Mengurangi Tagihan)</label>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text @click="showAdditionalCostDialog = false" />
          <Button label="Simpan" icon="pi pi-check" class="bg-blue-600 border-none text-white px-6" @click="submitAdditionalCost" :loading="loading" />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.booking-detail-container {
  animation: fadeIn 0.35s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ---- DataTable inside vehicle cards ---- */
.custom-mini-table :deep(.p-datatable-thead > tr > th) {
  background-color: #f8fafc;
  color: #94a3b8;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  padding: 0.625rem 0.75rem;
  border-bottom: 1px solid #f1f5f9;
}

.custom-mini-table :deep(.p-datatable-tbody > tr > td) {
  padding: 0.625rem 0.75rem;
  border-bottom: 1px solid #f8fafc;
}

.custom-mini-table :deep(.p-datatable-tbody > tr:last-child > td) {
  border-bottom: none;
}

/* ---- Skeleton ---- */
:deep(.p-skeleton) {
  background-color: rgba(0, 0, 0, 0.04);
}

/* ---- Dialog global overrides ---- */
:deep(.custom-dialog .p-dialog-header) {
  padding: 1.25rem 1.5rem;
  font-weight: 700;
  font-size: 1.05rem;
  color: #1e293b;
  border-bottom: 1px solid #f1f5f9;
}

:deep(.custom-dialog .p-dialog-content) {
  padding: 1rem 1.5rem 0.5rem 1.5rem;
}

:deep(.custom-dialog .p-dialog-footer) {
  padding: 0.75rem 1.5rem 1.25rem 1.5rem;
  border-top: 1px solid #f1f5f9;
}

/* ---- Fieldset inside dialogs ---- */
fieldset {
  margin: 0;
}

fieldset legend {
  margin-left: 0.25rem;
}
</style>

