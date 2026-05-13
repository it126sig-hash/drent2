<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBooking } from '../../composables/useBooking';
import { useUnit } from '../../composables/useUnit';
import { useDriver } from '../../composables/useDriver';
import { usePaymentAccount } from '../../composables/usePaymentAccount';
import { useCostType } from '../../composables/useCostType';
import { usePricingPackage } from '../../composables/usePricingPackage';
import BookingStatusBadge from '../../components/bookings/BookingStatusBadge.vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import ConfirmDialog from 'primevue/confirmdialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import Calendar from 'primevue/calendar';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import SelectButton from 'primevue/selectbutton';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirm = useConfirm();
const { fetchOne, updateBooking, changeStatus, checkout, complete, cancel, addDetail, addCost, updateDetail, updateCost, extend, rolling, stopEarly, addAdditionalCost, addPayment, loading } = useBooking();
const { units, fetchAll: fetchUnits } = useUnit();
const { drivers, fetchAll: fetchDrivers } = useDriver();
const { accounts: paymentAccounts, fetchAll: fetchAccounts } = usePaymentAccount();
const { costTypes: costTypesMaster, fetchAll: fetchCostTypes } = useCostType();
const { packages: pricingPackages, fetchAll: fetchPricingPackages } = usePricingPackage();

const booking = ref(null);
const showDetailDialog = ref(false);
const showCostDialog = ref(false);
const showEditBookingDialog = ref(false);
const editingDetailId = ref(null);
const editingCostId = ref(null);
const detailDialogMode = ref('detail');

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
const showHandleConfirmDialog = ref(false);
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

const bookingForm = ref({
  lama_sewa: 1,
  paket_sewa: 'harian',
  harga_dealing: null,
  dp: null,
  rekening_dp_id: null,
  tujuan: '',
  alamat_penjemputan: '',
  catatan: '',
});

const paketOptions = [
  { label: 'Harian', value: 'harian' },
  { label: 'Mingguan', value: 'mingguan' },
  { label: 'Bulanan', value: 'bulanan' },
];

const lamaSewaOptions = Array.from({ length: 99 }, (_, index) => ({
  label: String(index + 1),
  value: index + 1,
}));

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
    const lama = handleForm.value.lama_sewa || 1;
    if (handleForm.value.pricing_package_id) {
      const pkg = pricingPackages.value.find(p => p.id === handleForm.value.pricing_package_id);
      return (pkg?.harga || handleForm.value.harga_all_in || 0) * lama;
    }
    return (handleForm.value.harga_all_in || 0) * lama;
  }
  return grandTotalInternal.value;
});

const addCostRow = () => {
  handleForm.value.costs.push({ cost_type_id: null, type: 'biaya', label: '', amount: 0, keterangan: '' });
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
    costs: detail?.costs?.map(c => ({ cost_type_id: c.cost_type_id, type: c.type || 'biaya', label: c.label, amount: c.amount, keterangan: c.keterangan || '' })) || [],
    alamat_penjemputan: booking.value?.alamat_penjemputan || '',
    tujuan: booking.value?.tujuan || '',
  };
  showHandleDialog.value = true;
};

const submitHandle = async () => {
  try {
    if (hasZeroReadyUnitPrice.value) {
      toast.add({
        severity: 'warn',
        summary: 'Harga unit belum diisi',
        detail: 'Unit sudah ready, tetapi harga masih Rp0. Edit unit dan isi harga sebelum handle.',
        life: 5000,
      });
      showHandleConfirmDialog.value = false;
      return;
    }

    await changeStatus(booking.value.id, 'waiting_list');
    showHandleConfirmDialog.value = false;
    showHandleDialog.value = false;
    router.push({ name: 'BookingList' });
  } catch (err) {
    console.error(err);
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
  lama_sewa: 1,
  paket_sewa: 'harian',
  pricing_mode: 'non_all_in',
  pricing_package_id: null,
  harga_all_in: null,
  costs: [],
  detail_type: 'initial'
});

const costForm = ref({
  booking_detail_id: null,
  type: 'driver',
  label: '',
  amount: 0
});

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
  cost_type_id: null,
  type: 'biaya',
  label: '',
  amount: 0,
  keterangan: '',
});

const additionalTypeOptions = [
  { label: 'Biaya', value: 'biaya' },
  { label: 'Diskon', value: 'diskon' },
];

const costTypes = [
  { label: 'Driver', value: 'driver' },
  { label: 'BBM', value: 'bbm' },
  { label: 'Tol', value: 'tol' },
  { label: 'Parkir', value: 'parkir' },
  { label: 'Lainnya', value: 'lainnya' },
];

const extendHargaSewa = computed(() => Math.max(0, ((extendForm.value.harga_mobil || 0) - (extendForm.value.diskon_mobil || 0)) * (extendForm.value.lama_sewa || 0)));
const extendTotalBiaya = computed(() => extendForm.value.costs.reduce((s, c) => s + (c.amount || 0), 0));
const extendGrandTotal = computed(() => extendHargaSewa.value + extendTotalBiaya.value);
const extendTagihan = computed(() => {
  if (extendForm.value.pricing_mode === 'all_in') {
    const lama = extendForm.value.lama_sewa || 1;
    if (extendForm.value.pricing_package_id) {
      const pkg = pricingPackages.value.find(p => p.id === extendForm.value.pricing_package_id);
      return (pkg?.harga || extendForm.value.harga_all_in || 0) * lama;
    }
    return (extendForm.value.harga_all_in || 0) * lama;
  }
  return extendGrandTotal.value;
});

// Rolling computed
const rollingHargaSewa = computed(() => Math.max(0, ((rollingForm.value.harga_mobil||0) - (rollingForm.value.diskon_mobil||0)) * (rollingForm.value.lama_sewa||0)));
const rollingTotalBiaya = computed(() => rollingForm.value.costs.reduce((s, c) => s + (c.amount||0), 0));
const rollingGrandTotal = computed(() => rollingHargaSewa.value + rollingTotalBiaya.value);
const rollingTagihan = computed(() => {
  if (rollingForm.value.pricing_mode === 'all_in') {
    const lama = rollingForm.value.lama_sewa || 1;
    if (rollingForm.value.pricing_package_id) {
      const pkg = pricingPackages.value.find(p => p.id === rollingForm.value.pricing_package_id);
      return (pkg?.harga || rollingForm.value.harga_all_in || 0) * lama;
    }
    return (rollingForm.value.harga_all_in || 0) * lama;
  }
  return rollingGrandTotal.value;
});

const validDetails = computed(() => {
  if (!booking.value?.booking_details) return [];
  return booking.value.booking_details.filter(detail => detail.unit_id !== null || detail.unit_placeholder);
});

const primaryUnitDetail = computed(() => {
  return validDetails.value.find(detail => detail.detail_type === 'initial') || validDetails.value[0] || null;
});

const hasFixedUnit = computed(() => Boolean(primaryUnitDetail.value?.unit_id));
const canHandleBooking = computed(() => hasFixedUnit.value);
const unitActionDetail = computed(() => primaryUnitDetail.value || null);
const unitActionLabel = computed(() => hasFixedUnit.value ? 'Edit Unit' : 'Tambah Unit');
const unitActionIcon = computed(() => hasFixedUnit.value ? 'pi pi-pencil' : 'pi pi-plus');
const hasZeroReadyUnitPrice = computed(() => {
  const detail = primaryUnitDetail.value;
  if (!detail?.unit_id) return false;
  if (detail.pricing_mode === 'all_in') {
    return !detail.pricing_package_id && (detail.harga_all_in || 0) <= 0;
  }
  return (detail.harga_mobil || 0) <= 0;
});

const activeDetails = computed(() => {
  return validDetails.value.filter(d => d.status === 'aktif');
});

const isRentalUnit = computed(() => {
  return booking.value && booking.value.status === 'rental_unit';
});

const billableDetails = computed(() => {
  return validDetails.value.filter(detail => detail.status !== 'batal');
});

const totalDpPayments = computed(() => {
  return (booking.value?.payments || [])
    .filter(payment => payment.payment_type === 'dp')
    .reduce((sum, payment) => sum + (payment.amount || 0), 0);
});

const totalRecordedPayments = computed(() => {
  const paymentTotal = (booking.value?.payments || [])
    .reduce((sum, payment) => sum + (payment.amount || 0), 0);

  return paymentTotal || booking.value?.dp || 0;
});

const hasPricedDetails = computed(() => billableDetails.value.some(detail => detail.unit_id && ((detail.harga_mobil || 0) > 0 || (detail.harga_all_in || 0) > 0)));
const bookingTotalTagihan = computed(() => hasPricedDetails.value ? (booking.value?.total_tagihan ?? 0) : (booking.value?.harga_dealing ?? 0));
const bookingTotalPayments = computed(() => {
  const backendTotalPayments = booking.value?.total_payments;
  if (backendTotalPayments != null && backendTotalPayments > 0) return backendTotalPayments;
  return totalRecordedPayments.value;
});
const bookingSisaTagihan = computed(() => {
  if (!hasPricedDetails.value) {
    return Math.max(0, bookingTotalTagihan.value - bookingTotalPayments.value);
  }

  return Math.max(0, booking.value?.sisa_tagihan ?? (bookingTotalTagihan.value - bookingTotalPayments.value));
});
const isPaidOff = computed(() => bookingTotalTagihan.value > 0 && bookingSisaTagihan.value <= 0);

const selectedDetailUnit = computed(() => units.value.find(unit => unit.id === detailForm.value.unit_id));
const selectedDetailDriver = computed(() => drivers.value.find(driver => driver.id === detailForm.value.driver_id));
const detailHargaSewa = computed(() => Math.max(0, ((detailForm.value.harga_mobil || 0) - (detailForm.value.diskon_mobil || 0)) * (detailForm.value.lama_sewa || 0)));
const detailTotalBiayaOps = computed(() => detailForm.value.costs.reduce((sum, cost) => sum + getSignedCostAmount(cost), 0));
const detailGrandTotalInternal = computed(() => detailHargaSewa.value + detailTotalBiayaOps.value);
const detailTagihanKonsumen = computed(() => {
  if (detailForm.value.pricing_mode === 'all_in') {
    const lama = detailForm.value.lama_sewa || 1;
    if (detailForm.value.pricing_package_id) {
      const pkg = pricingPackages.value.find(p => p.id === detailForm.value.pricing_package_id);
      return (pkg?.harga || detailForm.value.harga_all_in || 0) * lama;
    }
    return (detailForm.value.harga_all_in || 0) * lama;
  }
  return detailGrandTotalInternal.value;
});

const detailDialogHeader = computed(() => {
  if (detailDialogMode.value === 'extend') return 'Perpanjang Sewa (Extend)';
  if (detailDialogMode.value === 'edit_extend') return 'Edit Transaksi Extend';
  return editingDetailId.value && hasFixedUnit.value ? 'Edit Unit & Driver' : 'Tambah Unit & Driver';
});

const detailSubmitLabel = computed(() => {
  if (detailDialogMode.value === 'extend') return 'Proses Extend';
  if (detailDialogMode.value === 'edit_extend') return 'Simpan Extend';
  return editingDetailId.value && hasFixedUnit.value ? 'Simpan Perubahan' : 'Simpan Unit';
});

const detailSubmitButtonClass = computed(() => {
  if (['extend', 'edit_extend'].includes(detailDialogMode.value)) return 'bg-amber-600 hover:bg-amber-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all';
  return 'bg-emerald-600 hover:bg-emerald-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all';
});

const extendMinStartDate = computed(() => {
  if (!['extend', 'edit_extend'].includes(detailDialogMode.value)) return null;
  if (detailDialogMode.value === 'edit_extend' && editingDetailId.value) {
    const currentIndex = validDetails.value.findIndex(detail => detail.id === editingDetailId.value);
    return getExtendStartDate(validDetails.value[currentIndex - 1]);
  }
  return getExtendStartDate(validDetails.value[validDetails.value.length - 1]);
});

const detailRentalSubtotal = (detail) => {
  return Math.max(0, ((detail.harga_mobil || 0) - (detail.diskon_mobil || 0)) * (detail.lama_sewa || booking.value?.lama_sewa || 1));
};

const detailCostTotal = (detail) => {
  return (detail.costs || []).reduce((sum, cost) => sum + getSignedCostAmount(cost), 0);
};

const detailConsumerBill = (detail) => {
  if (detail.pricing_mode === 'all_in') {
    return (detail.harga_all_in || 0) * (detail.lama_sewa || booking.value?.lama_sewa || 1);
  }
  return detailRentalSubtotal(detail) + detailCostTotal(detail);
};

const detailPricingModeLabel = (detail) => detail.pricing_mode === 'all_in' ? 'All In' : 'Non All In';

const detailTransactionLabel = (detail) => {
  if (detail.detail_type === 'extend') return 'Transaksi Extend';
  if (detail.detail_type === 'rolling') return 'Transaksi Rolling';
  return 'Transaksi Awal';
};

const detailTransactionSeverity = (detail) => {
  if (detail.detail_type === 'extend') return 'warning';
  if (detail.detail_type === 'rolling') return 'info';
  return 'success';
};

const canEditDetailTransaction = (detail) => {
  return detail.detail_type === 'extend' && !['selesai', 'batal'].includes(detail.status);
};

const getSignedCostAmount = (cost) => {
  const amount = cost?.amount || 0;
  return cost?.type === 'diskon' ? -amount : amount;
};

const formatSignedCostAmount = (cost) => {
  const amount = getSignedCostAmount(cost);
  return amount < 0 ? `-${formatCurrency(Math.abs(amount))}` : formatCurrency(amount);
};

const formatDateTime = (value) => {
  if (!value) return '-';
  return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};

const formatLocalDateTime = (value) => {
  if (!value) return null;
  const date = new Date(value);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  const seconds = String(date.getSeconds()).padStart(2, '0');

  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
};

const applyDefaultTime = (value, hour, minute) => {
  if (!value) return null;
  const date = new Date(value);
  date.setHours(hour, minute, 0, 0);
  return date;
};

const getExtendStartDate = (detail) => {
  if (!detail?.tgl_kembali) return applyDefaultTime(new Date(), 7, 0);
  const startDate = new Date(detail.tgl_kembali);
  startDate.setDate(startDate.getDate() + 1);
  startDate.setHours(7, 0, 0, 0);
  return startDate;
};

const addRentalDuration = (startDate, duration, packageType) => {
  if (!startDate || !duration) return null;

  const returnDate = new Date(startDate);
  const amount = Number(duration);

  if (packageType === 'mingguan') {
    returnDate.setDate(returnDate.getDate() + (amount * 7) - 1);
  } else if (packageType === 'bulanan') {
    returnDate.setMonth(returnDate.getMonth() + amount);
    returnDate.setDate(returnDate.getDate() - 1);
  } else {
    returnDate.setDate(returnDate.getDate() + amount - 1);
  }

  return applyDefaultTime(returnDate, 23, 59);
};

const syncDetailReturnDate = () => {
  const returnDate = addRentalDuration(
    detailForm.value.tgl_sewa,
    detailForm.value.lama_sewa,
    detailForm.value.paket_sewa
  );
  if (returnDate) detailForm.value.tgl_kembali = returnDate;
};

const setDetailStartDate = (date) => {
  const startDate = detailDialogMode.value === 'extend'
    ? applyDefaultTime(date, 7, 0)
    : applyDefaultTime(date, 7, 0);

  if (extendMinStartDate.value && startDate < extendMinStartDate.value) {
    detailForm.value.tgl_sewa = new Date(extendMinStartDate.value);
    toast.add({
      severity: 'warn',
      summary: 'Tanggal tidak valid',
      detail: 'Tanggal sewa extend minimal H+1 dari tanggal kembali terakhir.',
      life: 3500,
    });
    syncDetailReturnDate();
    return;
  }

  detailForm.value.tgl_sewa = startDate;
  syncDetailReturnDate();
};

const setDetailReturnDate = (date) => {
  const returnDate = applyDefaultTime(date, 23, 59);
  if (detailForm.value.tgl_sewa && returnDate < detailForm.value.tgl_sewa) {
    syncDetailReturnDate();
    toast.add({
      severity: 'warn',
      summary: 'Tanggal tidak valid',
      detail: 'Tanggal kembali tidak boleh kurang dari tanggal sewa.',
      life: 3500,
    });
    return;
  }
  detailForm.value.tgl_kembali = returnDate;
};

const getInitialBookingDetail = () => {
  return booking.value?.booking_details?.find(detail => detail.detail_type === 'initial') || booking.value?.booking_details?.[0] || null;
};

watch(
  () => [detailForm.value.tgl_sewa, detailForm.value.lama_sewa, detailForm.value.paket_sewa],
  () => {
    syncDetailReturnDate();
  }
);

const showActionError = (err, fallback) => {
  const detail = err.response?.data?.message || fallback;
  toast.add({ severity: 'error', summary: 'Gagal', detail, life: 5000 });
};

const loadBooking = async () => {
  try {
    booking.value = await fetchOne(route.params.id);
  } catch (err) {
    showActionError(err, 'Gagal mengambil detail booking');
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
  detailDialogMode.value = detail?.detail_type === 'extend' ? 'edit_extend' : 'detail';
  const initial = getInitialBookingDetail();
  if (detail) {
    editingDetailId.value = detail.id;
    detailForm.value = {
      unit_id: detail.unit_id,
      driver_id: detail.driver_id,
      tgl_sewa: detail.tgl_sewa ? new Date(detail.tgl_sewa) : null,
      tgl_kembali: detail.tgl_kembali ? new Date(detail.tgl_kembali) : null,
      harga_mobil: detail.harga_mobil,
      diskon_mobil: detail.diskon_mobil,
      lama_sewa: detail.lama_sewa || booking.value?.lama_sewa || 1,
      paket_sewa: detail.paket_sewa || booking.value?.paket_sewa || 'harian',
      pricing_mode: detail.pricing_mode || 'non_all_in',
      pricing_package_id: detail.pricing_package_id || null,
      harga_all_in: detail.harga_all_in || null,
      costs: detail.costs?.map(c => ({ cost_type_id: c.cost_type_id, type: c.type || 'biaya', label: c.label, amount: c.amount, keterangan: c.keterangan || '' })) || [],
      detail_type: detail.detail_type
    };
  } else {
    editingDetailId.value = null;
    detailForm.value = {
      unit_id: null,
      driver_id: null,
      tgl_sewa: initial?.tgl_sewa ? applyDefaultTime(initial.tgl_sewa, 7, 0) : null,
      tgl_kembali: initial?.tgl_kembali ? applyDefaultTime(initial.tgl_kembali, 23, 59) : null,
      harga_mobil: 0,
      diskon_mobil: 0,
      lama_sewa: booking.value?.lama_sewa || initial?.lama_sewa || 1,
      paket_sewa: booking.value?.paket_sewa || initial?.paket_sewa || 'harian',
      pricing_mode: 'non_all_in',
      pricing_package_id: null,
      harga_all_in: null,
      costs: [],
      detail_type: 'initial'
    };
  }
  showDetailDialog.value = true;
};

const openPrimaryUnitDialog = () => {
  openDetailDialog(unitActionDetail.value);
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
  const tglSewa = getExtendStartDate(last);
  detailDialogMode.value = 'extend';
  editingDetailId.value = null;
  detailForm.value = {
    unit_id: last?.unit_id || null,
    driver_id: last?.driver_id || null,
    tgl_sewa: tglSewa,
    tgl_kembali: null,
    lama_sewa: last?.lama_sewa || booking.value?.lama_sewa || 1,
    paket_sewa: last?.paket_sewa || 'harian',
    harga_mobil: last?.harga_mobil || 0,
    diskon_mobil: last?.diskon_mobil || 0,
    pricing_mode: last?.pricing_mode || 'non_all_in',
    pricing_package_id: last?.pricing_package_id || null,
    harga_all_in: null,
    costs: [],
    detail_type: 'extend',
  };
  showDetailDialog.value = true;
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

const openEditBookingDialog = () => {
  if (!booking.value?.id) return;
  router.push({ name: 'BookingEdit', params: { id: booking.value.id } });
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
    cost_type_id: null,
    type: 'biaya',
    label: '',
    amount: 0,
    keterangan: '',
  };
  showAdditionalCostDialog.value = true;
};

const onAdditionalCostTypeChange = (costTypeId) => {
  const ct = costTypesMaster.value.find(c => c.id === costTypeId);
  additionalCostForm.value.label = ct?.nama || '';
};

const onUnitChange = (e) => {
  const unit = units.value.find(u => u.id === e.value);
  if (unit) {
    detailForm.value.harga_mobil = unit.harga_1_hari || 0;
  }
};

const onDetailCostTypeChange = (idx, typeId) => {
  const ct = costTypesMaster.value.find(c => c.id === typeId);
  if (ct) detailForm.value.costs[idx].label = ct.nama;
};

const addDetailCostRow = () => {
  detailForm.value.costs.push({ cost_type_id: null, type: 'biaya', label: '', amount: 0, keterangan: '' });
};

const removeDetailCostRow = (idx) => {
  detailForm.value.costs.splice(idx, 1);
};

const submitDetail = async () => {
  try {
    if (detailForm.value.tgl_sewa && detailForm.value.tgl_kembali && detailForm.value.tgl_kembali < detailForm.value.tgl_sewa) {
      toast.add({
        severity: 'warn',
        summary: 'Tanggal tidak valid',
        detail: 'Tanggal kembali tidak boleh kurang dari tanggal sewa.',
        life: 3500,
      });
      return;
    }

    if (['extend', 'edit_extend'].includes(detailDialogMode.value) && extendMinStartDate.value && detailForm.value.tgl_sewa < extendMinStartDate.value) {
      toast.add({
        severity: 'warn',
        summary: 'Tanggal tidak valid',
        detail: 'Tanggal sewa extend minimal H+1 dari tanggal kembali terakhir.',
        life: 3500,
      });
      return;
    }

    const selectedPackage = pricingPackages.value.find(p => p.id === detailForm.value.pricing_package_id);
    const payload = {
      ...detailForm.value,
      detail_type: detailForm.value.detail_type || 'initial',
      harga_all_in: detailForm.value.pricing_mode === 'all_in'
        ? (detailForm.value.harga_all_in || selectedPackage?.harga || null)
        : null,
      tgl_sewa: formatLocalDateTime(detailForm.value.tgl_sewa),
      tgl_kembali: formatLocalDateTime(detailForm.value.tgl_kembali)
    };
    if (detailDialogMode.value === 'extend') {
      await extend(booking.value.id, payload);
    } else if (editingDetailId.value) {
      await updateDetail(editingDetailId.value, payload);
    } else {
      await addDetail(booking.value.id, payload);
    }
    showDetailDialog.value = false;
    loadBooking();
  } catch (err) {
    console.error(err);
  }
};

const submitBookingEdit = async () => {
  try {
    const payload = { ...bookingForm.value };
    if (!payload.dp || payload.dp <= 0) {
      payload.dp = null;
      payload.rekening_dp_id = null;
    }
    await updateBooking(booking.value.id, payload);
    showEditBookingDialog.value = false;
    loadBooking();
  } catch (err) {
    console.error(err);
  }
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
  } catch (err) {
    console.error(err);
  }
};

// Modification Submits
const submitExtend = async () => {
  try {
    const payload = {
      ...extendForm.value,
      tgl_sewa: formatLocalDateTime(extendForm.value.tgl_sewa),
      tgl_kembali: formatLocalDateTime(extendForm.value.tgl_kembali),
    };
    await extend(booking.value.id, payload);
    showExtendDialog.value = false;
    loadBooking();
  } catch (err) {
    console.error(err);
  }
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
  } catch (err) {
    console.error(err);
  }
};

const submitBatal = async () => {
  try {
    await cancel(booking.value.id, batalForm.value);
    showBatalDialog.value = false;
    loadBooking();
  } catch (err) {
    console.error(err);
  }
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
  } catch (err) {
    console.error(err);
  }
};

const submitAdditionalCost = async () => {
  try {
    const selectedCostType = costTypesMaster.value.find(c => c.id === additionalCostForm.value.cost_type_id);
    await addAdditionalCost(booking.value.id, {
      ...additionalCostForm.value,
      label: additionalCostForm.value.label || selectedCostType?.nama || '',
      is_discount: additionalCostForm.value.type === 'diskon',
    });
    showAdditionalCostDialog.value = false;
    loadBooking();
  } catch (err) {
    console.error(err);
  }
};

const onHandle = () => {
  if (!canHandleBooking.value) {
    toast.add({
      severity: 'warn',
      summary: 'Unit belum fix',
      detail: 'Booking masih memakai placeholder. Isi unit kendaraan dulu sebelum handle.',
      life: 4000,
    });
    return;
  }
  if (hasZeroReadyUnitPrice.value) {
    toast.add({
      severity: 'warn',
      summary: 'Harga unit belum diisi',
      detail: 'Unit sudah ready, tetapi harga masih Rp0. Edit unit dan isi harga sebelum handle.',
      life: 5000,
    });
    return;
  }
  showHandleConfirmDialog.value = true;
};

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
    router.push({ name: 'BookingList' });
  } catch (err) {
    console.error(err);
  }
};

const submitComplete = async () => {
  try {
    await complete(booking.value.id, { skip_inspection: completeSkip.value });
    showCompleteDialog.value = false;
    loadBooking();
  } catch (err) {
    console.error(err);
  }
};

const submitPayment = async () => {
  paymentFormErrors.value = {};
  try {
    await addPayment(booking.value.id, paymentForm.value);
    showPaymentDialog.value = false;
    loadBooking();
  } catch (err) {
    if (err.response?.data?.errors) paymentFormErrors.value = err.response.data.errors;
    console.error(err);
  }
};

const getDialogStateMap = () => ({
  showEditBookingDialog,
  showCheckoutDialog,
  showCompleteDialog,
  showHandleConfirmDialog,
  showHandleDialog,
  showDetailDialog,
  showCostDialog,
  showExtendDialog,
  showRollingDialog,
  showStopEarlyDialog,
  showBatalDialog,
  showPaymentDialog,
  showAdditionalCostDialog,
});

const dialogTitles = {
  showEditBookingDialog: 'Edit Data Booking',
  showCheckoutDialog: 'Konfirmasi Checkout',
  showCompleteDialog: 'Konfirmasi Selesai Sewa',
  showHandleConfirmDialog: 'Konfirmasi Handle Booking',
  showHandleDialog: 'Handle Booking',
  showDetailDialog: 'Unit & Driver',
  showCostDialog: 'Biaya Operasional',
  showExtendDialog: 'Perpanjang Sewa',
  showRollingDialog: 'Ganti Unit',
  showStopEarlyDialog: 'Stop Early',
  showBatalDialog: 'Batalkan Booking',
  showPaymentDialog: 'Tambah Pembayaran',
  showAdditionalCostDialog: 'Tambah Biaya/Diskon Tambahan',
};

const closeDialogSilently = (dialogKey) => {
  const dialog = getDialogStateMap()[dialogKey];
  if (dialog) dialog.value = false;
};

const requestCloseDialog = (dialogKey) => {
  confirm.require({
    message: `Tutup modal ${dialogTitles[dialogKey] || 'ini'}? Perubahan yang belum disimpan akan hilang.`,
    header: 'Konfirmasi Tutup',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Tutup',
    rejectLabel: 'Tetap Edit',
    accept: () => closeDialogSilently(dialogKey),
  });
};

const onDialogVisibleChange = (dialogKey, visible) => {
  if (!visible && getDialogStateMap()[dialogKey]?.value) {
    requestCloseDialog(dialogKey);
  }
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
};
</script>

<template>
  <div class="booking-detail-container app-page">
    <ConfirmDialog />

    <!-- Header & Action Bar -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" text rounded @click="router.push({ name: 'BookingList' })" class="bg-white shadow-sm hover:shadow-md transition-all" />
        <div>
          <div class="flex flex-wrap items-center gap-3 mb-1">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Booking #{{ booking?.kode_booking || '...' }}</h1>
            <BookingStatusBadge v-if="booking" :status="booking.status" />
            <span
              v-if="booking?.is_overdue"
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
          :disabled="!canHandleBooking"
          :title="canHandleBooking ? 'Handle booking' : 'Isi unit kendaraan terlebih dahulu'"
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
        <div class="app-card overflow-hidden">
          <div class="app-section-header px-6 py-4 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600">
                <i class="pi pi-user"></i>
              </div>
              <h2 class="text-base font-bold text-slate-800">Informasi Booking</h2>
            </div>
            <Button label="Edit Booking" icon="pi pi-pencil" size="small" text class="text-blue-600 font-semibold" @click="openEditBookingDialog" v-if="!['selesai','batal','cancelled', 'rental_unit', 'waiting_list'].includes(booking.status)" />
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

        <!-- Section: Financial Reference -->
        <div class="app-card overflow-hidden">
          <div class="app-section-header px-6 py-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-cyan-100 flex items-center justify-center text-cyan-700">
              <i class="pi pi-calculator"></i>
            </div>
            <div>
              <h2 class="text-base font-bold text-slate-800">Acuan Biaya Booking</h2>
              <p class="text-xs text-slate-500 mt-0.5">Breakdown ini dipakai sebagai rujukan saat handle, pembayaran, extend, dan rolling.</p>
            </div>
          </div>

          <div class="p-6 flex flex-col gap-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div class="app-muted-panel p-4">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Harga Dealing Awal</span>
                <span class="text-lg font-bold text-slate-900">{{ formatCurrency(booking.harga_dealing || 0) }}</span>
              </div>
              <div class="app-muted-panel p-4">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">DP Tercatat</span>
                <span class="text-lg font-bold text-emerald-700">{{ formatCurrency(totalDpPayments || booking.dp || 0) }}</span>
              </div>
              <div class="app-muted-panel p-4">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Total Final Backend</span>
                <span class="text-lg font-bold text-slate-900">{{ formatCurrency(bookingTotalTagihan) }}</span>
              </div>
            </div>

            <div v-if="!billableDetails.length" class="app-muted-panel p-5 text-sm text-slate-500">
              Belum ada detail unit yang bisa dihitung. Gunakan harga dealing awal sebagai acuan sementara.
            </div>

            <div v-else class="flex flex-col gap-3">

            </div>
          </div>
        </div>

        <!-- Section: Vehicles & Drivers -->
        <div class="app-card overflow-hidden">
          <div class="app-section-header px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600">
                <i class="pi pi-car"></i>
              </div>
              <h2 class="text-base font-bold text-slate-800">Unit Kendaraan & Driver</h2>
            </div>
            <Button
              :label="unitActionLabel"
              :icon="unitActionIcon"
              size="small"
              class="bg-emerald-600 hover:bg-emerald-700 border-none font-semibold rounded-lg px-3.5 py-2 text-white text-xs shadow-sm transition-all"
              @click="openPrimaryUnitDialog"
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
              <div
                v-if="hasZeroReadyUnitPrice"
                class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 flex items-start gap-3"
              >
                <i class="pi pi-exclamation-triangle mt-0.5 text-amber-600"></i>
                <div>
                  <p class="font-bold">Harga unit ready masih Rp0</p>
                  <p class="mt-0.5">Isi harga unit terlebih dahulu sebelum booking di-handle.</p>
                </div>
              </div>
              <div
                v-for="detail in validDetails"
                :key="detail.id"
                class="rounded-xl border overflow-hidden transition-colors"
                :class="detail.detail_type === 'extend'
                  ? 'border-amber-200 hover:border-amber-300 bg-amber-50/20'
                  : 'border-slate-100 hover:border-slate-200'"
              >
                <div
                  v-if="detail.detail_type === 'extend'"
                  class="px-5 py-2 bg-amber-50 border-b border-amber-100 flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-amber-700"
                >
                  <i class="pi pi-calendar-plus text-[11px]"></i>
                  Ada Transaksi Extend
                </div>
                <!-- Unit Header -->
                <div class="p-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-5 bg-slate-50/40">
                  <div class="flex gap-4 items-center">
                    <div class="w-14 h-14 bg-white rounded-xl border border-slate-100 flex items-center justify-center shadow-sm flex-shrink-0">
                      <i class="pi pi-car text-2xl text-slate-300"></i>
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <h3 class="text-lg font-bold text-slate-800">
                          {{ detail.unit ? `${detail.unit.merk} ${detail.unit.tipe}` : detail.unit_placeholder || 'Placeholder Unit' }}
                        </h3>
                        <Tag
                          :value="detailTransactionLabel(detail)"
                          :severity="detailTransactionSeverity(detail)"
                          class="rounded-md text-[10px] font-bold"
                        />
                      </div>
                      <div class="flex items-center gap-2 mt-1">
                        <span class="inline-block bg-slate-800 text-white font-mono text-xs font-semibold px-2 py-0.5 rounded tracking-wider">{{ detail.unit?.no_polisi || 'PLACEHOLDER' }}</span>
                        <span class="text-[11px] font-semibold text-slate-500 flex items-center gap-1 bg-slate-100 px-2 py-0.5 rounded-md">
                          <i class="pi pi-user text-[9px] text-slate-400"></i> {{ detail.unit?.rental_owner?.nama || detail.unit_placeholder || 'Internal' }}
                        </span>
                      </div>
                      <div class="flex flex-wrap items-center gap-2 mt-3 text-xs font-semibold text-slate-500">
                        <span class="inline-flex items-center gap-1.5 bg-white px-2.5 py-1 rounded-md border border-slate-100 shadow-sm">
                          <i class="pi pi-calendar-plus text-blue-500 text-[10px]"></i>
                          {{ formatDateTime(detail.tgl_sewa) }}
                        </span>
                        <i class="pi pi-arrow-right text-slate-300 text-[10px]"></i>
                        <span class="inline-flex items-center gap-1.5 bg-white px-2.5 py-1 rounded-md border border-slate-100 shadow-sm">
                          <i class="pi pi-calendar-minus text-rose-500 text-[10px]"></i>
                          {{ formatDateTime(detail.tgl_kembali) }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <div class="bg-white p-4 rounded-xl border border-slate-100 text-right min-w-[180px]">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">Subtotal Sewa</span>
                    <span class="text-xl font-bold text-slate-900">{{ formatCurrency(detailRentalSubtotal(detail)) }}</span>
                    <div class="text-[11px] font-semibold text-slate-500 mt-0.5">
                      {{ formatCurrency(detail.harga_mobil || 0) }} x {{ detail.lama_sewa || booking.lama_sewa || 1 }}
                    </div>
                    <div v-if="detail.diskon_mobil > 0" class="text-[11px] font-semibold text-rose-500 mt-0.5 flex items-center justify-end gap-1">
                      <i class="pi pi-tag text-[9px]"></i>
                      -{{ formatCurrency(detail.diskon_mobil) }}
                    </div>
                    <Button
                      v-if="canEditDetailTransaction(detail)"
                      label="Edit Extend"
                      icon="pi pi-pencil"
                      size="small"
                      outlined
                      severity="warning"
                      class="mt-3 rounded-lg font-semibold text-xs px-3 py-1.5"
                      @click="openDetailDialog(detail)"
                    />
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

                  
                  </div>

                  <div v-if="detail.costs?.length" class="mt-4 bg-white rounded-lg border border-slate-100 overflow-hidden">
                    <DataTable :value="detail.costs" class="p-datatable-sm custom-mini-table">
                      <Column field="type" header="TIPE">
                        <template #body="{ data }">
                          <div class="flex items-center gap-1.5">
                            <span
                              class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                              :class="data.type === 'diskon' ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-600'"
                            >
                              {{ data.type || 'biaya' }}
                            </span>
                            <Tag v-if="data.is_additional" value="Di luar deal" severity="warning" class="text-[10px]" />
                          </div>
                        </template>
                      </Column>
                      <Column field="label" header="KETERANGAN">
                        <template #body="{ data }">
                          <div class="flex flex-col">
                            <span class="text-sm text-slate-600">{{ data.cost_type?.nama || data.label }}</span>
                            <span v-if="data.keterangan" class="text-xs text-slate-400">{{ data.keterangan }}</span>
                          </div>
                        </template>
                      </Column>
                      <Column field="amount" header="JUMLAH" class="text-right">
                        <template #body="{ data }">
                          <span
                            class="text-sm font-bold"
                            :class="data.type === 'diskon' ? 'text-rose-600' : 'text-slate-800'"
                          >
                            {{ formatSignedCostAmount(data) }}
                          </span>
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
        <div v-if="isRentalUnit" class="app-card overflow-hidden">
          <div class="app-section-header px-6 py-4 flex items-center gap-3">
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
        <div class="financial-summary-card bg-slate-900 rounded-2xl shadow-xl p-6 text-white relative overflow-hidden">
          <div class="flex items-center gap-2.5 mb-5">
            <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
              <i class="pi pi-receipt text-sm text-cyan-300"></i>
            </div>
            <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-200">Ringkasan Keuangan</h2>
          </div>
          <div class="flex flex-col gap-3 relative z-10">
            <div class="flex justify-between items-baseline">
              <span class="text-sm text-white/50">Total Tagihan</span>
              <span class="text-base font-bold text-white">{{ formatCurrency(bookingTotalTagihan) }}</span>
            </div>
            <div class="flex justify-between items-baseline">
              <span class="text-sm text-white/50">Total Dibayar</span>
              <span class="text-base font-bold text-emerald-400">{{ formatCurrency(bookingTotalPayments) }}</span>
            </div>
            <div class="h-px bg-white/10 my-1"></div>
            <div class="p-4 bg-white/5 rounded-xl border border-white/5">
              <span class="text-[10px] font-bold uppercase tracking-widest block mb-1"
                :class="bookingSisaTagihan > 0 ? 'text-rose-300' : 'text-emerald-300'"
              >Sisa Tagihan</span>
              <span class="text-2xl font-bold tracking-tight"
                :class="bookingSisaTagihan > 0 ? 'text-rose-300' : 'text-emerald-300'"
              >{{ formatCurrency(bookingSisaTagihan) }}</span>
            </div>
            <div class="flex justify-between items-center text-xs">
              <span class="text-white/50">Status Pembayaran</span>
              <Tag :value="isPaidOff ? 'Lunas' : 'Belum Lunas'" :severity="isPaidOff ? 'success' : 'warning'" class="rounded-md" />
            </div>
          </div>
        </div>

        <!-- Payment List Card -->
        <div class="app-card overflow-hidden">
          <div class="app-section-header px-5 py-4 flex justify-between items-center">
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
              v-if="!['cancelled', 'batal', 'selesai'].includes(booking.status)"
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
        <div class="app-card p-5">
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

    <Dialog :visible="showEditBookingDialog" @update:visible="onDialogVisibleChange('showEditBookingDialog', $event)" header="Edit Data Booking" :style="{ width: '620px' }" modal :breakpoints="{ '680px': '95vw' }" class="custom-dialog">
      <div class="flex flex-col gap-5 pt-2">
        <div class="p-3 bg-slate-50 border border-slate-100 rounded-xl text-sm text-slate-600">
          Konsumen tidak dapat diubah dari halaman ini.
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-600">Lama Sewa</label>
            <InputNumber v-model="bookingForm.lama_sewa" :min="1" class="w-full" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-600">Paket Sewa</label>
            <Dropdown v-model="bookingForm.paket_sewa" :options="paketOptions" optionLabel="label" optionValue="value" class="w-full" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-600">Harga Dealing</label>
            <InputNumber v-model="bookingForm.harga_dealing" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-600">DP</label>
            <InputNumber v-model="bookingForm.dp" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
          </div>
          <div v-if="bookingForm.dp > 0" class="flex flex-col gap-1.5 sm:col-span-2">
            <label class="text-xs font-semibold text-slate-600">Rekening DP</label>
            <Dropdown v-model="bookingForm.rekening_dp_id" :options="accountOptions" optionLabel="name" optionValue="id" placeholder="Pilih akun pembayaran" class="w-full" />
          </div>
          <div class="flex flex-col gap-1.5 sm:col-span-2">
            <label class="text-xs font-semibold text-slate-600">Tujuan</label>
            <InputText v-model="bookingForm.tujuan" class="w-full" />
          </div>
          <div class="flex flex-col gap-1.5 sm:col-span-2">
            <label class="text-xs font-semibold text-slate-600">Alamat Penjemputan</label>
            <Textarea v-model="bookingForm.alamat_penjemputan" rows="2" class="w-full" />
          </div>
          <div class="flex flex-col gap-1.5 sm:col-span-2">
            <label class="text-xs font-semibold text-slate-600">Catatan</label>
            <Textarea v-model="bookingForm.catatan" rows="3" class="w-full" />
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="requestCloseDialog('showEditBookingDialog')" />
          <Button label="Simpan" icon="pi pi-save" class="bg-blue-600 hover:bg-blue-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all" @click="submitBookingEdit" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Checkout (E4) ======= -->
    <Dialog :visible="showCheckoutDialog" @update:visible="onDialogVisibleChange('showCheckoutDialog', $event)" header="Konfirmasi Checkout" :style="{ width: '420px' }" modal class="custom-dialog">
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
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="requestCloseDialog('showCheckoutDialog')" />
          <Button label="Proses Checkout" icon="pi pi-sign-out" class="bg-emerald-600 hover:bg-emerald-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all" @click="submitCheckout" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Selesai / Complete (E4) ======= -->
    <Dialog :visible="showCompleteDialog" @update:visible="onDialogVisibleChange('showCompleteDialog', $event)" header="Konfirmasi Selesai Sewa" :style="{ width: '420px' }" modal class="custom-dialog">
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
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="requestCloseDialog('showCompleteDialog')" />
          <Button label="Selesaikan Sewa" icon="pi pi-flag-fill" class="bg-violet-600 hover:bg-violet-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all" @click="submitComplete" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <Dialog :visible="showHandleConfirmDialog" @update:visible="onDialogVisibleChange('showHandleConfirmDialog', $event)" header="Konfirmasi Handle Booking" :style="{ width: '460px' }" modal class="custom-dialog">
      <div class="flex flex-col gap-4 pt-2">
        <div class="flex items-start gap-3 p-4 bg-blue-50 border border-blue-100 rounded-xl">
          <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-blue-700 flex-shrink-0">
            <i class="pi pi-check-circle"></i>
          </div>
          <div>
            <p class="font-bold text-slate-800 mb-1">Ubah status ke Waiting List?</p>
            <p class="text-sm text-slate-500">Detail unit, driver, jadwal, dan biaya diisi lewat modal Tambah Unit. Pastikan data kendaraan sudah disimpan.</p>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="requestCloseDialog('showHandleConfirmDialog')" />
          <Button label="Ya, Waiting List" icon="pi pi-check-circle" class="bg-blue-600 hover:bg-blue-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all" @click="submitHandle" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Handle Booking (E3) ======= -->
    <Dialog :visible="showHandleDialog" @update:visible="onDialogVisibleChange('showHandleDialog', $event)" header="Handle Booking" :style="{ width: '760px' }" modal :breakpoints="{ '800px': '95vw' }" class="custom-dialog">
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
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="requestCloseDialog('showHandleDialog')" />
          <Button label="Proses Handle" icon="pi pi-check-circle" class="bg-blue-600 hover:bg-blue-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all" @click="submitHandle" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Tambah/Edit Unit & Driver ======= -->
    <Dialog :visible="showDetailDialog" @update:visible="onDialogVisibleChange('showDetailDialog', $event)" :header="detailDialogHeader" :style="{ width: '760px' }" modal :breakpoints="{ '820px': '95vw' }" class="custom-dialog">
      <div class="flex flex-col gap-6 pt-2">
        <!-- Section: Kendaraan & Driver -->
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Kendaraan & Driver</legend>
          <div class="flex flex-col gap-4 mt-1">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                <i class="pi pi-car text-slate-400 text-[11px]"></i> Unit Kendaraan
              </label>
              <Dropdown v-model="detailForm.unit_id" :options="units" optionLabel="no_polisi" optionValue="id" placeholder="Pilih unit kendaraan" filter @change="onUnitChange" class="w-full">
                <template #option="{ option }">
                  <div class="flex items-center justify-between gap-3">
                    <div class="flex flex-col">
                      <span class="font-semibold text-slate-800">{{ option.merk }} {{ option.tipe }}</span>
                      <span class="text-xs text-slate-500">{{ option.no_polisi }} - {{ option.rental_owner?.nama || 'Internal' }}</span>
                    </div>
                    <Tag :value="option.status" :severity="option.status === 'Aktif' ? 'success' : 'warning'" class="rounded-md" />
                  </div>
                </template>
              </Dropdown>
              <div v-if="selectedDetailUnit" class="app-muted-panel p-3 text-sm">
                <div class="grid grid-cols-3 gap-2">
                  <div><span class="text-slate-400 block text-xs">No Polisi</span><strong>{{ selectedDetailUnit.no_polisi }}</strong></div>
                  <div><span class="text-slate-400 block text-xs">Pemilik</span><strong>{{ selectedDetailUnit.rental_owner?.nama || 'Internal' }}</strong></div>
                  <div><span class="text-slate-400 block text-xs">Status</span><Tag :value="selectedDetailUnit.status" :severity="selectedDetailUnit.status === 'Aktif' ? 'success' : 'warning'" class="rounded-md" /></div>
                </div>
              </div>
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                <i class="pi pi-id-card text-slate-400 text-[11px]"></i> Driver
                <span class="text-slate-300 font-normal">(opsional)</span>
              </label>
              <Dropdown v-model="detailForm.driver_id" :options="drivers" optionLabel="nama" optionValue="id" placeholder="Lepas kunci / Pilih driver" filter showClear class="w-full">
                <template #option="{ option }">
                  <div class="flex flex-col">
                    <span class="font-semibold text-slate-800">{{ option.nama }}</span>
                    <span class="text-xs text-slate-500">{{ option.kota || '-' }} - {{ option.kontak_1 || '-' }}</span>
                  </div>
                </template>
              </Dropdown>
              <div v-if="selectedDetailDriver" class="app-muted-panel p-3 text-sm">
                <div class="grid grid-cols-3 gap-2">
                  <div><span class="text-slate-400 block text-xs">Driver</span><strong>{{ selectedDetailDriver.nama }}</strong></div>
                  <div><span class="text-slate-400 block text-xs">Kota</span><strong>{{ selectedDetailDriver.kota || '-' }}</strong></div>
                  <div><span class="text-slate-400 block text-xs">Kontak / SIM</span><strong>{{ selectedDetailDriver.kontak_1 || '-' }} / {{ selectedDetailDriver.no_sim || '-' }}</strong></div>
                </div>
              </div>
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
              <Calendar
                v-model="detailForm.tgl_sewa"
                showTime
                hourFormat="24"
                dateFormat="dd M yy"
                :minDate="extendMinStartDate"
                class="w-full"
                @date-select="setDetailStartDate"
              />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                <i class="pi pi-calendar-minus text-rose-500 text-[11px]"></i> Selesai Sewa
              </label>
              <Calendar
                v-model="detailForm.tgl_kembali"
                showTime
                hourFormat="24"
                dateFormat="dd M yy"
                :minDate="detailForm.tgl_sewa"
                class="w-full"
                @date-select="setDetailReturnDate"
              />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Lama Sewa</label>
              <Dropdown
                v-model="detailForm.lama_sewa"
                :options="lamaSewaOptions"
                optionLabel="label"
                optionValue="value"
                filter
                class="w-full"
              />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Paket Sewa</label>
              <Dropdown v-model="detailForm.paket_sewa" :options="paketOptions" optionLabel="label" optionValue="value" class="w-full" />
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
          <div class="mt-4 flex flex-col gap-3">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Mode Pricing</label>
              <SelectButton v-model="detailForm.pricing_mode" :options="pricingModeOptions" optionLabel="label" optionValue="value" class="w-full" />
            </div>
            <div v-if="detailForm.pricing_mode === 'all_in'" class="flex flex-col gap-3 p-3 bg-cyan-50 border border-cyan-100 rounded-xl">
              <Dropdown v-model="detailForm.pricing_package_id" :options="packageOptions" optionLabel="label" optionValue="id" placeholder="Pilih paket All In..." showClear class="w-full" />
              <InputNumber v-if="!detailForm.pricing_package_id" v-model="detailForm.harga_all_in" mode="currency" currency="IDR" locale="id-ID" placeholder="Harga All In per paket sewa" class="w-full" />
              <p class="text-xs text-cyan-700">Harga All In dikalikan lama sewa pada tagihan konsumen.</p>
            </div>
          </div>
        </fieldset>

        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Biaya Operasional</legend>
          <div class="flex flex-col gap-3 mt-3">
            <div v-if="!detailForm.costs.length" class="text-center text-sm text-slate-400 py-5 border border-dashed border-slate-200 rounded-lg bg-white">
              Belum ada biaya operasional.
            </div>
            <div
              v-for="(cost, idx) in detailForm.costs"
              :key="idx"
              class="rounded-lg border border-slate-200 bg-white p-3"
            >
              <div class="flex items-center justify-between gap-3 mb-3">
                <span class="text-xs font-bold text-slate-500">Item {{ idx + 1 }}</span>
                <Button icon="pi pi-times" text rounded severity="danger" size="small" class="w-7 h-7 p-0" @click="removeDetailCostRow(idx)" />
              </div>
              <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                <div class="md:col-span-4 flex flex-col gap-1.5">
                  <label class="text-[11px] font-semibold text-slate-500">Tipe Master</label>
                  <Dropdown v-model="cost.cost_type_id" :options="costTypeOptions" optionLabel="label" optionValue="id" placeholder="Pilih tipe" showClear class="w-full" @change="onDetailCostTypeChange(idx, cost.cost_type_id)" />
                </div>
                <div class="md:col-span-3 flex flex-col gap-1.5">
                  <label class="text-[11px] font-semibold text-slate-500">Biaya / Diskon</label>
                  <Dropdown v-model="cost.type" :options="additionalTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
                </div>
                <div class="md:col-span-5 flex flex-col gap-1.5">
                  <label class="text-[11px] font-semibold text-slate-500">Keterangan</label>
                  <InputText v-model="cost.label" placeholder="Misal: Fee driver luar kota" class="w-full" />
                </div>
                <div class="md:col-span-4 flex flex-col gap-1.5">
                  <label class="text-[11px] font-semibold text-slate-500">Nominal</label>
                  <InputNumber v-model="cost.amount" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
                </div>
                <div v-if="costTypesMaster.find(c => c.id === cost.cost_type_id)?.require_description" class="md:col-span-8 flex flex-col gap-1.5">
                  <label class="text-[11px] font-semibold text-slate-500">Detail Tambahan</label>
                  <InputText v-model="cost.keterangan" placeholder="Detail sesuai tipe biaya" class="w-full" />
                </div>
              </div>
            </div>
            <Button label="+ Tambah Biaya" icon="pi pi-plus" text size="small" class="text-blue-600 font-semibold self-start" @click="addDetailCostRow" />
          </div>
        </fieldset>

        <div class="bg-slate-900 rounded-xl p-4 text-white">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Kalkulasi Unit</p>
          <div class="flex flex-col gap-2 text-sm">
            <div class="flex justify-between"><span class="text-white/60">Harga Sewa</span><span>{{ formatCurrency(detailHargaSewa) }}</span></div>
            <div class="flex justify-between">
              <span class="text-white/60">Biaya/Diskon Ops</span>
              <span :class="detailTotalBiayaOps < 0 ? 'text-rose-300' : ''">
                {{ detailTotalBiayaOps < 0 ? '-' : '' }}{{ formatCurrency(Math.abs(detailTotalBiayaOps)) }}
              </span>
            </div>
            <div class="h-px bg-white/10 my-1"></div>
            <div class="flex justify-between"><span class="font-bold text-cyan-300">Tagihan Konsumen</span><span class="text-lg font-bold text-cyan-300">{{ formatCurrency(detailTagihanKonsumen) }}</span></div>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="requestCloseDialog('showDetailDialog')" />
          <Button :label="detailSubmitLabel" icon="pi pi-check" :class="detailSubmitButtonClass" @click="submitDetail" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Biaya Operasional ======= -->
    <Dialog :visible="showCostDialog" @update:visible="onDialogVisibleChange('showCostDialog', $event)" :header="editingCostId ? 'Edit Biaya Operasional' : 'Tambah Biaya Operasional'" :style="{ width: '460px' }" modal :breakpoints="{ '640px': '95vw' }" class="custom-dialog">
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
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="requestCloseDialog('showCostDialog')" />
          <Button :label="editingCostId ? 'Simpan Perubahan' : 'Tambahkan'" icon="pi pi-check" class="bg-blue-600 hover:bg-blue-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all" @click="submitCost" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Perpanjang (Extend) — E5 Revamp ======= -->
    <Dialog v-if="false" :visible="showExtendDialog" @update:visible="onDialogVisibleChange('showExtendDialog', $event)" header="Perpanjang Sewa (Extend)" :style="{ width: '700px' }" modal :breakpoints="{ '750px': '95vw' }" class="custom-dialog">
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
          <Button label="Batal" icon="pi pi-times" text @click="requestCloseDialog('showExtendDialog')" />
          <Button label="Proses Extend" icon="pi pi-check" class="bg-amber-600 border-none text-white px-6 rounded-lg font-semibold" @click="submitExtend" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Ganti Unit (Rolling) — E5 Revamp ======= -->
    <Dialog :visible="showRollingDialog" @update:visible="onDialogVisibleChange('showRollingDialog', $event)" :header="rollingStep === 1 ? 'Ganti Unit (Rolling) — Step 1: Adjust Unit Lama' : 'Ganti Unit (Rolling) — Step 2: Detail Unit Baru'" :style="{ width: '700px' }" modal :breakpoints="{ '750px': '95vw' }" class="custom-dialog">
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
          <Button label="Batal" icon="pi pi-times" text @click="requestCloseDialog('showRollingDialog')" />
          <Button v-if="rollingStep === 1" label="Lanjut →" icon="pi pi-arrow-right" iconPos="right" class="bg-amber-500 border-none text-white px-6 rounded-lg font-semibold" @click="rollingStep = 2" />
          <Button v-if="rollingStep === 2" label="← Kembali" icon="pi pi-arrow-left" text class="text-slate-500" @click="rollingStep = 1" />
          <Button v-if="rollingStep === 2" label="Proses Rolling" icon="pi pi-check" class="bg-amber-600 border-none text-white px-6 rounded-lg font-semibold" @click="submitRolling" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Stop Early ======= -->
    <Dialog :visible="showStopEarlyDialog" @update:visible="onDialogVisibleChange('showStopEarlyDialog', $event)" header="Hentikan Sewa Awal (Stop Early)" :style="{ width: '460px' }" modal class="custom-dialog">
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
          <Button label="Batal" icon="pi pi-times" text @click="requestCloseDialog('showStopEarlyDialog')" />
          <Button label="Proses Stop" icon="pi pi-check" severity="danger" class="px-6" @click="submitStopEarly" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Batalkan Booking (E5) ======= -->
    <Dialog :visible="showBatalDialog" @update:visible="onDialogVisibleChange('showBatalDialog', $event)" header="Batalkan Booking" :style="{ width: '440px' }" modal class="custom-dialog">
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
          <Button label="Kembali" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="requestCloseDialog('showBatalDialog')" />
          <Button label="Ya, Batalkan Booking" icon="pi pi-ban" severity="danger" class="px-6 py-2.5 rounded-lg font-semibold" @click="submitBatal" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Tambah Pembayaran ======= -->
    <Dialog :visible="showPaymentDialog" @update:visible="onDialogVisibleChange('showPaymentDialog', $event)" header="Tambah Pembayaran" :style="{ width: '460px' }" modal class="custom-dialog">
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
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="requestCloseDialog('showPaymentDialog')" />
          <Button label="Simpan Pembayaran" icon="pi pi-check" class="bg-emerald-600 hover:bg-emerald-700 border-none px-6 py-2.5 rounded-lg font-semibold text-white transition-all" @click="submitPayment" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Additional Cost/Discount ======= -->
    <Dialog :visible="showAdditionalCostDialog" @update:visible="onDialogVisibleChange('showAdditionalCostDialog', $event)" header="Tambah Biaya/Diskon Tambahan" :style="{ width: '460px' }" modal class="custom-dialog">
      <div class="flex flex-col gap-5 pt-2">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Tipe Biaya/Diskon *</label>
          <Dropdown
            v-model="additionalCostForm.cost_type_id"
            :options="costTypeOptions"
            optionLabel="label"
            optionValue="id"
            placeholder="Pilih dari master cost type"
            filter
            class="w-full"
            @change="onAdditionalCostTypeChange(additionalCostForm.cost_type_id)"
          />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Jenis Transaksi *</label>
          <Dropdown v-model="additionalCostForm.type" :options="additionalTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Catatan</label>
          <InputText v-model="additionalCostForm.keterangan" placeholder="Misal: Denda keterlambatan di luar kesepakatan awal" class="w-full" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Nominal</label>
          <InputNumber v-model="additionalCostForm.amount" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
        </div>
        <div class="p-3 rounded-lg bg-amber-50 border border-amber-100 text-sm text-amber-800">
          <span class="font-semibold">Di luar kesepakatan awal.</span>
          Item ini akan disimpan dengan flag tambahan dan ditandai di rincian biaya.
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text @click="requestCloseDialog('showAdditionalCostDialog')" />
          <Button
            label="Simpan"
            icon="pi pi-check"
            class="bg-blue-600 border-none text-white px-6"
            @click="submitAdditionalCost"
            :loading="loading"
            :disabled="!additionalCostForm.cost_type_id || !additionalCostForm.amount"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.booking-detail-container {
  animation: fadeIn 0.35s ease-out;
  padding: 2px;
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

.financial-summary-card {
  border-radius: 8px;
  background: #0f172a;
  box-shadow: 0 18px 34px -22px rgba(15, 23, 42, 0.85);
}

.cost-reference-card {
  border: 1px solid var(--surface-border);
  border-radius: 8px;
  background: #ffffff;
  padding: 16px;
}

.cost-mini-box {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 4px;
  border: 1px solid var(--surface-border-soft);
  border-radius: 8px;
  background: #f8fbfe;
  padding: 12px;
}

.cost-mini-box span {
  color: #64748b;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.cost-mini-box strong {
  color: #0f172a;
  font-size: 0.92rem;
}
</style>

