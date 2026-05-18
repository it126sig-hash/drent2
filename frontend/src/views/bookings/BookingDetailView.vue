<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBooking } from '../../composables/useBooking';
import { useUnit } from '../../composables/useUnit';
import { useDriver } from '../../composables/useDriver';
import { usePaymentAccount } from '../../composables/usePaymentAccount';
import { useCostType } from '../../composables/useCostType';
import { usePricingPackage } from '../../composables/usePricingPackage';
import { usePhysicalCheck } from '../../composables/usePhysicalCheck';
import { useAuthStore } from '../../stores/auth';
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
const authStore = useAuthStore();
const { fetchOne, updateBooking, handle, checkout, complete, cancel, addDetail, addCost, updateDetail, updateCost, extend, rolling, stopEarly, addAdditionalCost, addPayment, requestVoidPayment, approveVoidPayment, rejectVoidPayment, loading } = useBooking();
const { units, fetchAll: fetchUnits } = useUnit();
const { drivers, fetchAll: fetchDrivers } = useDriver();
const { accounts: paymentAccounts, fetchAll: fetchAccounts } = usePaymentAccount();
const { costTypes: costTypesMaster, fetchAll: fetchCostTypes } = useCostType();
const { packages: pricingPackages, fetchAll: fetchPricingPackages } = usePricingPackage();
const { requestCheck: requestPhysicalCheck, loading: physicalCheckLoading } = usePhysicalCheck();

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
const paymentConfirming = ref(false);
const paymentSubmitting = ref(false);
const showVoidPaymentDialog = ref(false);
const showRejectVoidDialog = ref(false);
const selectedVoidPayment = ref(null);
const voidPaymentForm = ref({ void_reason: '' });
const rejectVoidForm = ref({ void_rejection_note: '' });
const voidPaymentFormErrors = ref({});
const paymentTypeOptions = [
  { label: 'DP / Uang Muka', value: 'dp' },
  { label: 'Cicilan', value: 'cicilan' },
  { label: 'Pelunasan', value: 'pelunasan' },
];

const isPaymentSubmitDisabled = computed(() =>
  loading.value
  || paymentConfirming.value
  || paymentSubmitting.value
  || !paymentForm.value.payment_type
  || !paymentForm.value.payment_account_id
  || !paymentForm.value.amount
);

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

const findPricingPackage = (packageId) =>
  pricingPackages.value.find(pkg => pkg.id === packageId);

const packageCostItems = (pkg) =>
  (pkg?.items || []).map(item => ({
    cost_type_id: item.cost_type_id ?? null,
    type: item.type || 'biaya',
    label: item.label || item.cost_type?.nama || '',
    amount: item.amount || 0,
    keterangan: item.keterangan || '',
  }));

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

const applyPricingPackage = (target, packageId, costKey = 'costs', priceKey = 'harga_all_in') => {
  const pkg = findPricingPackage(packageId);
  if (!pkg) {
    target[priceKey] = null;
    return;
  }

  target[priceKey] = pkg.harga || null;
  target[costKey] = packageCostItems(pkg);
};

const onHandlePackageChange = () => {
  applyPricingPackage(handleForm.value, handleForm.value.pricing_package_id);
};

const onDetailPackageChange = () => {
  applyPricingPackage(detailForm.value, detailForm.value.pricing_package_id);
};

const onExtendPackageChange = () => {
  applyPricingPackage(extendForm.value, extendForm.value.pricing_package_id);
};

const onRollingOldPackageChange = () => {
  applyPricingPackage(rollingForm.value, rollingForm.value.pricing_package_id_lama, 'costs_lama', 'harga_all_in_lama');
};

const onRollingNewPackageChange = () => {
  applyPricingPackage(rollingForm.value, rollingForm.value.pricing_package_id);
};

const hargaSewa = computed(() => {
  const { harga_mobil, diskon_mobil, lama_sewa } = handleForm.value;
  return Math.max(0, ((harga_mobil || 0) - (diskon_mobil || 0)) * (lama_sewa || 0));
});

const totalBiayaOps = computed(() =>
  getBillableCostTotal(handleForm.value.pricing_mode, handleForm.value.costs)
);

const grandTotalInternal = computed(() => hargaSewa.value + totalBiayaOps.value);

const tagihanKonsumen = computed(() => {
  if (handleForm.value.pricing_mode === 'all_in') {
    const lama = handleForm.value.lama_sewa || 1;
    if (handleForm.value.pricing_package_id) {
      const pkg = pricingPackages.value.find(p => p.id === handleForm.value.pricing_package_id);
      return ((pkg?.harga || handleForm.value.harga_all_in || 0) * lama) + totalBiayaOps.value;
    }
    return ((handleForm.value.harga_all_in || 0) * lama) + totalBiayaOps.value;
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

const prepareHandleForm = () => {
  handleFormErrors.value = {};
  const detail = primaryUnitDetail.value || booking.value?.booking_details?.[0];
  handleForm.value = {
    unit_id: detail?.unit_id || null,
    driver_id: detail?.driver_id || null,
    lama_sewa: detail?.lama_sewa || booking.value?.lama_sewa || 1,
    paket_sewa: detail?.paket_sewa || booking.value?.paket_sewa || 'harian',
    harga_mobil: detail?.harga_mobil || 0,
    diskon_mobil: detail?.diskon_mobil || 0,
    pricing_mode: detail?.pricing_mode || 'non_all_in',
    pricing_package_id: detail?.pricing_package_id || null,
    harga_all_in: detail?.harga_all_in || null,
    costs: detail?.costs?.map(c => ({ cost_type_id: c.cost_type_id, type: c.type || 'biaya', label: c.label, amount: c.amount, keterangan: c.keterangan || '' })) || [],
    alamat_penjemputan: booking.value?.alamat_penjemputan || '',
    tujuan: booking.value?.tujuan || '',
  };
};

const openHandleDialog = () => {
  prepareHandleForm();
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

    const selectedPackage = findPricingPackage(handleForm.value.pricing_package_id);
    const payload = {
      ...handleForm.value,
      harga_all_in: handleForm.value.pricing_mode === 'all_in'
        ? (handleForm.value.harga_all_in || selectedPackage?.harga || null)
        : null,
    };

    await handle(booking.value.id, payload);
    showHandleConfirmDialog.value = false;
    showHandleDialog.value = false;
    router.push({ name: 'BookingList' });
  } catch (err) {
    if (err.response?.data?.errors) {
      handleFormErrors.value = err.response.data.errors;
      showHandleConfirmDialog.value = false;
      showHandleDialog.value = true;
    }
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
  unit_id_lama: null,
  driver_id_lama: null,
  tgl_sewa_lama: null,
  tgl_kembali_lama: null,
  lama_sewa_lama: null,
  paket_sewa_lama: 'harian',
  harga_mobil_lama: 0,
  diskon_mobil_lama: 0,
  pricing_mode_lama: 'non_all_in',
  pricing_package_id_lama: null,
  harga_all_in_lama: null,
  costs_lama: [],
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
const extendTotalBiaya = computed(() => getBillableCostTotal(extendForm.value.pricing_mode, extendForm.value.costs));
const extendGrandTotal = computed(() => extendHargaSewa.value + extendTotalBiaya.value);
const extendTagihan = computed(() => {
  if (extendForm.value.pricing_mode === 'all_in') {
    const lama = extendForm.value.lama_sewa || 1;
    if (extendForm.value.pricing_package_id) {
      const pkg = pricingPackages.value.find(p => p.id === extendForm.value.pricing_package_id);
      return ((pkg?.harga || extendForm.value.harga_all_in || 0) * lama) + extendTotalBiaya.value;
    }
    return ((extendForm.value.harga_all_in || 0) * lama) + extendTotalBiaya.value;
  }
  return extendGrandTotal.value;
});

// Rolling computed
const rollingHargaSewa = computed(() => Math.max(0, ((rollingForm.value.harga_mobil||0) - (rollingForm.value.diskon_mobil||0)) * (rollingForm.value.lama_sewa||0)));
const rollingTotalBiaya = computed(() => getBillableCostTotal(rollingForm.value.pricing_mode, rollingForm.value.costs));
const rollingGrandTotal = computed(() => rollingHargaSewa.value + rollingTotalBiaya.value);
const rollingTagihan = computed(() => {
  if (rollingForm.value.pricing_mode === 'all_in') {
    const lama = rollingForm.value.lama_sewa || 1;
    if (rollingForm.value.pricing_package_id) {
      const pkg = pricingPackages.value.find(p => p.id === rollingForm.value.pricing_package_id);
      return ((pkg?.harga || rollingForm.value.harga_all_in || 0) * lama) + rollingTotalBiaya.value;
    }
    return ((rollingForm.value.harga_all_in || 0) * lama) + rollingTotalBiaya.value;
  }
  return rollingGrandTotal.value;
});

const rollingOldHargaSewa = computed(() => Math.max(0, ((rollingForm.value.harga_mobil_lama || 0) - (rollingForm.value.diskon_mobil_lama || 0)) * (rollingForm.value.lama_sewa_lama || 0)));
const rollingOldTotalBiaya = computed(() => getBillableCostTotal(rollingForm.value.pricing_mode_lama, rollingForm.value.costs_lama));
const rollingOldGrandTotal = computed(() => rollingOldHargaSewa.value + rollingOldTotalBiaya.value);
const rollingOldTagihan = computed(() => {
  if (rollingForm.value.pricing_mode_lama === 'all_in') {
    const lama = rollingForm.value.lama_sewa_lama || 1;
    if (rollingForm.value.pricing_package_id_lama) {
      const pkg = pricingPackages.value.find(p => p.id === rollingForm.value.pricing_package_id_lama);
      return ((pkg?.harga || rollingForm.value.harga_all_in_lama || 0) * lama) + rollingOldTotalBiaya.value;
    }
    return ((rollingForm.value.harga_all_in_lama || 0) * lama) + rollingOldTotalBiaya.value;
  }
  return rollingOldGrandTotal.value;
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
const unitActionLabel = computed(() => hasFixedUnit.value ? 'Edit Unit' : 'Atur Unit');
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
    .filter(payment => payment.payment_type === 'dp' && payment.status !== 'voided')
    .reduce((sum, payment) => sum + (payment.amount || 0), 0);
});

const activePayments = computed(() => {
  return (booking.value?.payments || []).filter(payment => payment.status !== 'voided');
});

const totalRecordedPayments = computed(() => {
  const paymentTotal = activePayments.value
    .reduce((sum, payment) => sum + (payment.amount || 0), 0);

  return (booking.value?.payments || []).length ? paymentTotal : booking.value?.dp || 0;
});

const hasPricedDetails = computed(() => billableDetails.value.some(detail => detail.unit_id && ((detail.harga_mobil || 0) > 0 || (detail.harga_all_in || 0) > 0)));
const bookingCalculatedTagihan = computed(() => billableDetails.value.reduce((sum, detail) => sum + detailConsumerBill(detail), 0));
const bookingTotalTagihan = computed(() => {
  if (!hasPricedDetails.value) return booking.value?.harga_dealing ?? 0;
  return bookingCalculatedTagihan.value || booking.value?.total_tagihan || 0;
});
const bookingTotalPayments = computed(() => {
  const backendTotalPayments = booking.value?.total_payments;
  if (backendTotalPayments != null) return backendTotalPayments;
  return totalRecordedPayments.value;
});
const bookingSisaTagihan = computed(() => {
  return Math.max(0, bookingTotalTagihan.value - bookingTotalPayments.value);
});
const isPaidOff = computed(() => bookingTotalTagihan.value > 0 && bookingSisaTagihan.value <= 0);
const canApprovePaymentVoid = computed(() => ['superadmin', 'supervisor'].includes(authStore.user?.role));

const selectedDetailUnit = computed(() => units.value.find(unit => unit.id === detailForm.value.unit_id));
const selectedDetailDriver = computed(() => drivers.value.find(driver => driver.id === detailForm.value.driver_id));
const detailHargaSewa = computed(() => Math.max(0, ((detailForm.value.harga_mobil || 0) - (detailForm.value.diskon_mobil || 0)) * (detailForm.value.lama_sewa || 0)));
const detailTotalBiayaOps = computed(() => getBillableCostTotal(detailForm.value.pricing_mode, detailForm.value.costs));
const detailGrandTotalInternal = computed(() => detailHargaSewa.value + detailTotalBiayaOps.value);
const detailTagihanKonsumen = computed(() => {
  if (detailForm.value.pricing_mode === 'all_in') {
    const lama = detailForm.value.lama_sewa || 1;
    if (detailForm.value.pricing_package_id) {
      const pkg = pricingPackages.value.find(p => p.id === detailForm.value.pricing_package_id);
      return ((pkg?.harga || detailForm.value.harga_all_in || 0) * lama) + detailTotalBiayaOps.value;
    }
    return ((detailForm.value.harga_all_in || 0) * lama) + detailTotalBiayaOps.value;
  }
  return detailGrandTotalInternal.value;
});

const detailDialogHeader = computed(() => {
  if (detailDialogMode.value === 'extend') return 'Perpanjang Sewa (Extend)';
  if (detailDialogMode.value === 'edit_extend') return 'Edit Transaksi Extend';
  if (detailDialogMode.value === 'edit_rolling') return 'Edit Transaksi Rolling';
  return editingDetailId.value && hasFixedUnit.value ? 'Edit Unit & Driver' : 'Tambah Unit & Driver';
});

const detailSubmitLabel = computed(() => {
  if (detailDialogMode.value === 'extend') return 'Proses Extend';
  if (detailDialogMode.value === 'edit_extend') return 'Simpan Extend';
  if (detailDialogMode.value === 'edit_rolling') return 'Simpan Rolling';
  return editingDetailId.value && hasFixedUnit.value ? 'Simpan Perubahan' : 'Simpan Unit';
});

const detailSubmitButtonClass = computed(() => {
  if (detailDialogMode.value === 'edit_rolling') return 'app-dialog-button app-dialog-button-info';
  if (['extend', 'edit_extend'].includes(detailDialogMode.value)) return 'app-dialog-button app-dialog-button-warning';
  return 'app-dialog-button app-dialog-button-primary';
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
  return sumCosts(detail.costs || []);
};

const detailBillableCostTotal = (detail) => {
  return getBillableCostTotal(detail.pricing_mode, detail.costs || []);
};

const detailConsumerBill = (detail) => {
  if (detail.pricing_mode === 'all_in') {
    return ((detail.harga_all_in || 0) * (detail.lama_sewa || booking.value?.lama_sewa || 1)) + detailBillableCostTotal(detail);
  }
  return detailRentalSubtotal(detail) + detailCostTotal(detail);
};

const detailPricingModeLabel = (detail) => detail.pricing_mode === 'all_in' ? 'All In' : 'Non All In';

const detailUnitPriceTotal = (detail) => {
  const lama = detail.lama_sewa || booking.value?.lama_sewa || 1;
  if (detail.pricing_mode === 'all_in') {
    return (detail.harga_all_in || 0) * lama;
  }
  return detailRentalSubtotal(detail);
};

const detailUnitTotalWithCosts = (detail) => {
  return detailUnitPriceTotal(detail) + detailBillableCostTotal(detail);
};

const detailUnitPriceDescription = (detail) => {
  const lama = detail.lama_sewa || booking.value?.lama_sewa || 1;
  const price = detail.pricing_mode === 'all_in'
    ? (detail.harga_all_in || 0)
    : Math.max(0, (detail.harga_mobil || 0) - (detail.diskon_mobil || 0));

  return `${detailPricingModeLabel(detail)} - ${formatCurrency(price)} x ${lama}`;
};

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
  const finalDetailStatuses = ['selesai', 'batal', 'cancelled', 'completed'];
  const finalBookingStatuses = ['selesai', 'batal', 'cancelled', 'completed'];
  return ['extend', 'rolling'].includes(detail.detail_type)
    && !finalDetailStatuses.includes(detail.status)
    && !finalBookingStatuses.includes(booking.value?.status);
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

const getNextRentalStartDate = (returnDate) => {
  if (!returnDate) return null;
  const startDate = new Date(returnDate);
  startDate.setDate(startDate.getDate() + 1);
  return applyDefaultTime(startDate, 7, 0);
};

const cloneDetailCosts = (detail) => {
  return detail?.costs?.map(c => ({
    cost_type_id: c.cost_type_id,
    type: c.type || 'biaya',
    label: c.label,
    amount: c.amount,
    keterangan: c.keterangan || '',
  })) || [];
};

const syncRollingOldReturnDate = () => {
  const returnDate = addRentalDuration(
    rollingForm.value.tgl_sewa_lama,
    rollingForm.value.lama_sewa_lama,
    rollingForm.value.paket_sewa_lama
  );
  if (!returnDate) return;

  rollingForm.value.tgl_kembali_lama = returnDate;
  rollingForm.value.tgl_rolling = returnDate;
};

const syncRollingNewSchedule = () => {
  const originalDuration = Number(rollingForm.value.lama_sewa_original || 0);
  const adjustedDuration = Number(rollingForm.value.lama_sewa_lama || 0);
  const remainingDuration = Math.max(0, originalDuration - adjustedDuration);
  rollingForm.value.lama_sewa = remainingDuration;

  const startDate = getNextRentalStartDate(rollingForm.value.tgl_kembali_lama);
  rollingForm.value.tgl_sewa = startDate;
  rollingForm.value.tgl_kembali = remainingDuration > 0
    ? addRentalDuration(startDate, remainingDuration, rollingForm.value.paket_sewa)
    : null;
};

const applyRollingOldDetail = (detail) => {
  const oldStartDate = detail?.tgl_sewa ? new Date(detail.tgl_sewa) : null;
  const oldReturnDate = detail?.tgl_kembali ? new Date(detail.tgl_kembali) : null;
  const oldDuration = detail?.lama_sewa || booking.value?.lama_sewa || 1;

  rollingForm.value.booking_detail_id = detail?.id || null;
  rollingForm.value.tgl_rolling = oldReturnDate;
  rollingForm.value.unit_id_lama = detail?.unit_id || null;
  rollingForm.value.driver_id_lama = detail?.driver_id || null;
  rollingForm.value.tgl_sewa_lama = oldStartDate;
  rollingForm.value.tgl_kembali_lama = oldReturnDate;
  rollingForm.value.lama_sewa_lama = oldDuration;
  rollingForm.value.lama_sewa_original = oldDuration;
  rollingForm.value.paket_sewa_lama = detail?.paket_sewa || booking.value?.paket_sewa || 'harian';
  rollingForm.value.harga_mobil_lama = detail?.harga_mobil || 0;
  rollingForm.value.diskon_mobil_lama = detail?.diskon_mobil || 0;
  rollingForm.value.pricing_mode_lama = detail?.pricing_mode || 'non_all_in';
  rollingForm.value.pricing_package_id_lama = detail?.pricing_package_id || null;
  rollingForm.value.harga_all_in_lama = detail?.harga_all_in || null;
  rollingForm.value.costs_lama = cloneDetailCosts(detail);
  rollingForm.value.paket_sewa = detail?.paket_sewa || booking.value?.paket_sewa || 'harian';
  syncRollingNewSchedule();
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

watch(
  () => [rollingForm.value.lama_sewa_lama, rollingForm.value.paket_sewa_lama],
  () => {
    if (!showRollingDialog.value || rollingStep.value !== 1) return;
    syncRollingOldReturnDate();
    syncRollingNewSchedule();
  }
);

watch(
  () => rollingForm.value.paket_sewa,
  () => {
    if (!showRollingDialog.value) return;
    syncRollingNewSchedule();
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
  detailDialogMode.value = detail?.detail_type === 'extend'
    ? 'edit_extend'
    : detail?.detail_type === 'rolling'
      ? 'edit_rolling'
      : 'detail';
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
    booking_detail_id: null,
    tgl_rolling: null,
    unit_id_lama: null,
    driver_id_lama: null,
    tgl_sewa_lama: null,
    tgl_kembali_lama: null,
    lama_sewa_lama: null,
    lama_sewa_original: null,
    paket_sewa_lama: booking.value?.paket_sewa || 'harian',
    harga_mobil_lama: 0,
    diskon_mobil_lama: 0,
    pricing_mode_lama: 'non_all_in',
    pricing_package_id_lama: null,
    harga_all_in_lama: null,
    costs_lama: [],
    unit_id: null,
    driver_id: null,
    tgl_sewa: null,
    tgl_kembali: null,
    lama_sewa: null,
    paket_sewa: active?.paket_sewa || booking.value?.paket_sewa || 'harian',
    harga_mobil: 0,
    diskon_mobil: 0,
    pricing_mode: 'non_all_in',
    pricing_package_id: null,
    harga_all_in: null,
    costs: [],
  };
  applyRollingOldDetail(active);
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

const onRollingDetailChange = (detailId) => {
  const detail = activeDetails.value.find(item => item.id === detailId);
  if (detail) applyRollingOldDetail(detail);
};

const onRollingOldUnitChange = (e) => {
  const unit = units.value.find(u => u.id === e.value);
  if (unit) rollingForm.value.harga_mobil_lama = unit.harga_1_hari || 0;
};

const onRollingOldCostTypeChange = (idx, typeId) => {
  const ct = costTypesMaster.value.find(c => c.id === typeId);
  if (ct) rollingForm.value.costs_lama[idx].label = ct.nama;
};

const onRollingNewCostTypeChange = (idx, typeId) => {
  const ct = costTypesMaster.value.find(c => c.id === typeId);
  if (ct) rollingForm.value.costs[idx].label = ct.nama;
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

    const selectedPackage = findPricingPackage(detailForm.value.pricing_package_id);
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
    const selectedPackage = findPricingPackage(extendForm.value.pricing_package_id);
    const payload = {
      ...extendForm.value,
      harga_all_in: extendForm.value.pricing_mode === 'all_in'
        ? (extendForm.value.harga_all_in || selectedPackage?.harga || null)
        : null,
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
    syncRollingOldReturnDate();
    syncRollingNewSchedule();

    if (!rollingForm.value.lama_sewa || rollingForm.value.lama_sewa < 1) {
      toast.add({
        severity: 'warn',
        summary: 'Sisa durasi habis',
        detail: 'Lama sewa koreksi harus lebih kecil dari lama sewa sebelum rolling.',
        life: 4000,
      });
      return;
    }

    const oldSelectedPackage = findPricingPackage(rollingForm.value.pricing_package_id_lama);
    const newSelectedPackage = findPricingPackage(rollingForm.value.pricing_package_id);
    const payload = {
      ...rollingForm.value,
      harga_all_in_lama: rollingForm.value.pricing_mode_lama === 'all_in'
        ? (rollingForm.value.harga_all_in_lama || oldSelectedPackage?.harga || null)
        : null,
      harga_all_in: rollingForm.value.pricing_mode === 'all_in'
        ? (rollingForm.value.harga_all_in || newSelectedPackage?.harga || null)
        : null,
      tgl_rolling: formatLocalDateTime(rollingForm.value.tgl_rolling),
      tgl_sewa: formatLocalDateTime(rollingForm.value.tgl_sewa),
      tgl_kembali: formatLocalDateTime(rollingForm.value.tgl_kembali),
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
  prepareHandleForm();
  showHandleConfirmDialog.value = true;
};

const openPaymentDialog = () => {
  paymentForm.value = { payment_account_id: null, amount: null, payment_type: 'cicilan', catatan: '' };
  paymentFormErrors.value = {};
  showPaymentDialog.value = true;
};

const openVoidPaymentDialog = (payment) => {
  selectedVoidPayment.value = payment;
  voidPaymentForm.value = { void_reason: '' };
  voidPaymentFormErrors.value = {};
  showVoidPaymentDialog.value = true;
};

const submitVoidPaymentRequest = async () => {
  voidPaymentFormErrors.value = {};
  try {
    await requestVoidPayment(selectedVoidPayment.value.id, voidPaymentForm.value);
    showVoidPaymentDialog.value = false;
    await loadBooking();
  } catch (err) {
    if (err.response?.data?.errors) voidPaymentFormErrors.value = err.response.data.errors;
    console.error(err);
  }
};

const approveVoidRequest = (payment) => {
  confirm.require({
    message: `Setujui void pembayaran ${formatCurrency(payment.amount)}? Nominal ini tidak lagi dihitung sebagai pembayaran.`,
    header: 'ACC Void Pembayaran',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'ACC Void',
    rejectLabel: 'Batal',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await approveVoidPayment(payment.id);
      await loadBooking();
    },
  });
};

const openRejectVoidDialog = (payment) => {
  selectedVoidPayment.value = payment;
  rejectVoidForm.value = { void_rejection_note: '' };
  showRejectVoidDialog.value = true;
};

const submitRejectVoid = async () => {
  await rejectVoidPayment(selectedVoidPayment.value.id, rejectVoidForm.value);
  showRejectVoidDialog.value = false;
  await loadBooking();
};

const paymentStatusLabel = (status) => {
  const map = {
    active: 'Aktif',
    void_requested: 'Menunggu ACC void',
    voided: 'Void',
  };
  return map[status || 'active'] || status;
};

// E4: Checkout & Complete dialogs
const showCheckoutDialog = ref(false);
const showCompleteDialog = ref(false);
const checkoutSkip = ref(false);
const completeSkip = ref(false);
const completeReturnedAt = ref(null);

const departurePhysicalCheck = computed(() => booking.value?.physical_check_summary?.departure || { status: 'not_requested' });
const returnPhysicalCheck = computed(() => booking.value?.physical_check_summary?.return || { status: 'not_requested' });
const departureCheckDone = computed(() => departurePhysicalCheck.value.status === 'completed');
const returnCheckDone = computed(() => returnPhysicalCheck.value.status === 'completed');

const physicalCheckStatusLabel = (status) => {
  const map = {
    not_requested: 'Belum diminta',
    requested: 'Diminta',
    completed: 'Sudah dilakukan',
    skipped: 'Dilewati',
  };
  return map[status] || status || '-';
};

const physicalCheckSeverity = (status) => {
  const map = {
    not_requested: 'secondary',
    requested: 'warning',
    completed: 'success',
    skipped: 'danger',
  };
  return map[status] || 'info';
};

const openCheckoutDialog = () => {
  checkoutSkip.value = false;
  showCheckoutDialog.value = true;
};

const openCompleteDialog = () => {
  completeSkip.value = false;
  completeReturnedAt.value = new Date();
  showCompleteDialog.value = true;
};

const openPhysicalCheckForm = (type) => {
  router.push({
    name: 'PhysicalCheckForm',
    params: { bookingId: booking.value.id, type },
  });
};

const requestPhysicalCheckFromBooking = async (type) => {
  await requestPhysicalCheck(booking.value.id, type);
  await loadBooking();
  toast.add({
    severity: 'warn',
    summary: 'Checkout ditahan',
    detail: type === 'departure'
      ? 'Cek fisik keberangkatan belum selesai. Request sudah masuk ke tabel cek fisik.'
      : 'Cek fisik pengembalian belum selesai. Request sudah masuk ke tabel cek fisik.',
    life: 6000,
  });
};

const submitCheckout = async () => {
  try {
    if (!checkoutSkip.value && !departureCheckDone.value) {
      await requestPhysicalCheckFromBooking('departure');
      showCheckoutDialog.value = false;
      return;
    }

    await checkout(booking.value.id, { skip_inspection: checkoutSkip.value });
    showCheckoutDialog.value = false;
    router.push({ name: 'BookingList' });
  } catch (err) {
    console.error(err);
  }
};

const submitComplete = async () => {
  try {
    if (!completeSkip.value && !returnCheckDone.value) {
      await requestPhysicalCheckFromBooking('return');
      showCompleteDialog.value = false;
      return;
    }

    await complete(booking.value.id, {
      skip_inspection: completeSkip.value,
      returned_at: completeReturnedAt.value?.toISOString(),
    });
    showCompleteDialog.value = false;
    loadBooking();
  } catch (err) {
    console.error(err);
  }
};

const submitPayment = async () => {
  if (isPaymentSubmitDisabled.value) return;
  paymentFormErrors.value = {};
  paymentConfirming.value = true;

  confirm.require({
    message: `Catat pembayaran ${formatCurrency(paymentForm.value.amount)} untuk booking ${booking.value?.kode_booking || ''}?`,
    header: 'Konfirmasi Pembayaran',
    icon: 'pi pi-credit-card',
    acceptLabel: 'Ya, Simpan',
    rejectLabel: 'Batal',
    acceptClass: 'app-dialog-button app-dialog-button-primary',
    rejectClass: 'app-dialog-button app-dialog-button-secondary',
    accept: async () => {
      if (paymentSubmitting.value) return;
      paymentConfirming.value = false;
      paymentSubmitting.value = true;
      try {
        await addPayment(booking.value.id, { ...paymentForm.value });
        showPaymentDialog.value = false;
        await loadBooking();
      } catch (err) {
        if (err.response?.data?.errors) paymentFormErrors.value = err.response.data.errors;
        console.error(err);
      } finally {
        paymentSubmitting.value = false;
      }
    },
    reject: () => {
      paymentConfirming.value = false;
    },
    onHide: () => {
      paymentConfirming.value = false;
    },
  });
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
  showVoidPaymentDialog,
  showRejectVoidDialog,
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
  showVoidPaymentDialog: 'Request Void Pembayaran',
  showRejectVoidDialog: 'Tolak Void Pembayaran',
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
    acceptClass: 'app-dialog-button app-dialog-button-danger',
    rejectClass: 'app-dialog-button app-dialog-button-secondary',
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

const auditUserName = (user) => user?.name || '-';
</script>

<template>
  <div class="booking-detail-container">
    <ConfirmDialog />
    <!-- Header & Action Bar -->
    <div class="detail-page-header">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" text rounded @click="router.push({ name: 'BookingList' })" class="back-button" />
        <div>
          <div class="flex flex-wrap items-center gap-3 mb-1">
            <h1 class="detail-title">Booking #{{ booking?.kode_booking || '...' }}</h1>
            <BookingStatusBadge v-if="booking" :status="booking.status" />
            <span
              v-if="booking?.is_overdue"
              class="overdue-chip"
            >
              <i class="pi pi-exclamation-triangle text-[10px]"></i> Terlambat Kembali
            </span>
          </div>
          <p class="detail-subtitle">
            <i class="pi pi-clock"></i>
            {{ booking?.created_at ? new Date(booking.created_at).toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' }) : '-' }}
          </p>
        </div>
      </div>

      <div class="detail-action-bar">
        <Button
          v-if="booking && ['follow_up', 'confirm'].includes(booking.status)"
          label="Handle Booking"
          icon="pi pi-check-circle"
          class="detail-primary-action"
          @click="onHandle"
          :loading="loading"
          :disabled="!canHandleBooking"
          :title="canHandleBooking ? 'Handle booking' : 'Isi unit kendaraan terlebih dahulu'"
        />
        <Button
          v-if="booking && booking.status === 'waiting_list'"
          label="Checkout"
          icon="pi pi-sign-out"
          class="detail-primary-action"
          @click="openCheckoutDialog"
          :loading="loading"
        />
        <Button
          v-if="booking && booking.status === 'rental_unit'"
          label="Selesai"
          icon="pi pi-flag-fill"
          class="detail-primary-action"
          @click="openCompleteDialog"
          :loading="loading"
        />
        <Button
          v-if="booking && !['selesai','batal','cancelled'].includes(booking.status)"
          label="Batalkan"
          icon="pi pi-ban"
          severity="danger"
          outlined
          class="detail-secondary-action"
          @click="openBatalDialog"
          :loading="loading"
        />
        <Button label="Print" icon="pi pi-print" severity="secondary" outlined class="detail-secondary-action" disabled />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="!booking && loading" class="loading-grid">
      <div class="lg:col-span-2 flex flex-col gap-4">
        <Skeleton width="100%" height="160px" borderRadius="10px" />
        <Skeleton width="100%" height="340px" borderRadius="10px" />
      </div>
      <Skeleton width="100%" height="420px" borderRadius="10px" />
    </div>

    <!-- Main Content Grid -->
    <div v-else-if="booking" class="detail-grid">
      <!-- LEFT COLUMN -->
      <div class="detail-main-column">

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
          <div class="p-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-5">
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
            <div class="flex flex-col gap-1">
              <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Booking Oleh</span>
              <span class="text-sm font-medium text-slate-700">{{ auditUserName(booking.created_by_user) }}</span>
            </div>
            <div class="flex flex-col gap-1">
              <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Confirm / DP Oleh</span>
              <span class="text-sm font-medium text-slate-700">{{ auditUserName(booking.confirmed_by_user) }}</span>
              <span v-if="booking.confirmed_at" class="text-xs text-slate-400">{{ formatDateTime(booking.confirmed_at) }}</span>
            </div>
            <div class="flex flex-col gap-1">
              <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Handle Oleh</span>
              <span class="text-sm font-medium text-slate-700">{{ auditUserName(booking.handled_by_user) }}</span>
              <span v-if="booking.handled_at" class="text-xs text-slate-400">{{ formatDateTime(booking.handled_at) }}</span>
            </div>
            <div class="flex flex-col gap-1">
              <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Checkout Oleh</span>
              <span class="text-sm font-medium text-slate-700">{{ auditUserName(booking.checked_out_by_user) }}</span>
              <span v-if="booking.checked_out_at" class="text-xs text-slate-400">{{ formatDateTime(booking.checked_out_at) }}</span>
            </div>
            <div class="flex flex-col gap-1">
              <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Dikembalikan</span>
              <span class="text-sm font-medium text-slate-700">{{ formatDateTime(booking.returned_at) }}</span>
            </div>
            <div class="flex flex-col gap-1">
              <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Selesai Oleh</span>
              <span class="text-sm font-medium text-slate-700">{{ auditUserName(booking.completed_by_user) }}</span>
              <span v-if="booking.completed_at" class="text-xs text-slate-400">{{ formatDateTime(booking.completed_at) }}</span>
            </div>
          </div>
        </div>

        <!-- Section: Financial Reference -->
        <div class="app-card overflow-hidden" v-if="!hasFixedUnit">
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
              v-if="['follow_up', 'confirm','waiting_list'].includes(booking.status)"
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
                  : detail.detail_type === 'rolling'
                    ? 'border-sky-200 hover:border-sky-300 bg-sky-50/20'
                  : 'border-slate-100 hover:border-slate-200'"
              >
                <div
                  v-if="detail.detail_type === 'extend'"
                  class="px-5 py-2 bg-amber-50 border-b border-amber-100 flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-amber-700"
                >
                  <i class="pi pi-calendar-plus text-[11px]"></i>
                  Ada Transaksi Extend
                </div>
                <div
                  v-else-if="detail.detail_type === 'rolling'"
                  class="px-5 py-2 bg-sky-50 border-b border-sky-100 flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-sky-700"
                >
                  <i class="pi pi-sync text-[11px]"></i>
                  Ada Transaksi Rolling
                </div>
                <!-- Unit Header -->
                <div class="p-5 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-5 bg-slate-50/40">
                  <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
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
                      <div class="flex flex-wrap items-center gap-2 mt-2 text-[11px] font-semibold">
                        <span class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 px-2.5 py-1 rounded-md border border-blue-100">
                          <i class="pi pi-wallet text-[9px]"></i>
                          Harga Mobil: {{ formatCurrency(detail.harga_mobil || 0) }}
                        </span>
                        <span
                          v-if="detail.diskon_mobil > 0"
                          class="inline-flex items-center gap-1.5 bg-rose-50 text-rose-700 px-2.5 py-1 rounded-md border border-rose-100"
                        >
                          <i class="pi pi-tag text-[9px]"></i>
                          Diskon: {{ formatCurrency(detail.diskon_mobil) }}
                        </span>
                        <span
                          v-if="detail.pricing_mode === 'all_in'"
                          class="inline-flex items-center gap-1.5 bg-cyan-50 text-cyan-700 px-2.5 py-1 rounded-md border border-cyan-100"
                        >
                          <i class="pi pi-check-circle text-[9px]"></i>
                          All In: {{ formatCurrency(detail.harga_all_in || 0) }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <div class="w-full lg:w-auto bg-slate-900 p-4 rounded-xl border border-slate-700 text-right lg:min-w-[220px] shadow-lg shadow-slate-200/70 mt-4 lg:mt-0">
                    <span class="text-[10px] font-bold text-slate-300 uppercase tracking-widest block mb-0.5">
                      {{ detail.pricing_mode === 'all_in' ? 'Total Tagihan Unit' : 'Total Unit + Ops' }}
                    </span>
                    <span class="text-xl font-bold text-white">{{ formatCurrency(detailUnitTotalWithCosts(detail)) }}</span>
                    <div class="text-[11px] font-semibold text-sky-200 mt-1">
                      {{ detailUnitPriceDescription(detail) }}
                    </div>
                    <div class="text-[11px] font-semibold text-slate-300 mt-0.5">
                      {{ detail.pricing_mode === 'all_in' ? 'Diskon Ops Dihitung' : 'Biaya Ops' }}: {{ formatCurrency(detailBillableCostTotal(detail)) }}
                    </div>
                    <Button
                      v-if="canEditDetailTransaction(detail)"
                      :label="detail.detail_type === 'rolling' ? 'Edit Rolling' : 'Edit Extend'"
                      icon="pi pi-pencil"
                      size="small"
                      outlined
                      :severity="detail.detail_type === 'rolling' ? 'info' : 'warning'"
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
      <div class="detail-side-column">

        <!-- Financial Summary Card -->
        <div class="financial-summary-card">
          <div class="flex items-center gap-2.5 mb-5">
            <div class="summary-icon">
              <i class="pi pi-receipt"></i>
            </div>
            <h2>Ringkasan Keuangan</h2>
          </div>
          <div class="flex flex-col gap-3 relative z-10">
            <div class="summary-row">
              <span>Total Tagihan</span>
              <strong>{{ formatCurrency(bookingTotalTagihan) }}</strong>
            </div>
            <div class="summary-row">
              <span>Total Dibayar</span>
              <strong class="summary-positive">{{ formatCurrency(bookingTotalPayments) }}</strong>
            </div>
            <div class="summary-divider"></div>
            <div class="summary-balance">
              <span
                :class="bookingSisaTagihan > 0 ? 'is-due' : 'is-paid'"
              >Sisa Tagihan</span>
              <strong
                :class="bookingSisaTagihan > 0 ? 'is-due' : 'is-paid'"
              >{{ formatCurrency(bookingSisaTagihan) }}</strong>
            </div>
            <div class="summary-status">
              <span>Status Pembayaran</span>
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
            <div
              v-for="p in booking.payments"
              :key="p.id"
              class="px-5 py-3.5 flex items-start justify-between gap-3"
              :class="{ 'bg-rose-50/60': p.status === 'voided', 'bg-amber-50/60': p.status === 'void_requested' }"
            >
              <div class="flex flex-col gap-0.5 min-w-0">
                <div class="flex flex-wrap gap-1.5">
                  <span class="text-[11px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md w-fit"
                    :class="{
                      'bg-blue-100 text-blue-700': p.payment_type === 'dp',
                      'bg-amber-100 text-amber-700': p.payment_type === 'cicilan',
                      'bg-emerald-100 text-emerald-700': p.payment_type === 'pelunasan',
                    }"
                  >{{ p.payment_type }}</span>
                  <span
                    v-if="p.status && p.status !== 'active'"
                    class="text-[11px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md w-fit"
                    :class="{
                      'bg-amber-100 text-amber-700': p.status === 'void_requested',
                      'bg-rose-100 text-rose-700': p.status === 'voided',
                    }"
                  >{{ paymentStatusLabel(p.status) }}</span>
                </div>
                <span class="text-xs text-slate-500 truncate">
                  {{ p.paid_at ? new Date(p.paid_at).toLocaleDateString('id-ID', { dateStyle: 'medium' }) : '-' }}
                </span>
                <span v-if="p.catatan" class="text-xs text-slate-400 italic truncate">{{ p.catatan }}</span>
                <span v-if="p.void_reason" class="text-xs text-rose-500 truncate">Alasan void: {{ p.void_reason }}</span>
                <span v-if="p.void_requester" class="text-xs text-amber-600 truncate">
                  Request void oleh {{ p.void_requester.name }}
                  <span v-if="p.void_requested_at">
                    pada {{ new Date(p.void_requested_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) }}
                  </span>
                </span>
                <span v-if="p.status === 'voided' && p.void_approver" class="text-xs text-rose-500 truncate">Di-ACC oleh {{ p.void_approver.name }}</span>
                <span v-if="p.void_rejection_note" class="text-xs text-slate-400 truncate">Catatan penolakan: {{ p.void_rejection_note }}</span>
              </div>
              <div class="flex flex-col items-end gap-2">
                <span
                  class="text-sm font-bold whitespace-nowrap"
                  :class="p.status === 'voided' ? 'text-rose-500 line-through' : 'text-slate-800'"
                >{{ formatCurrency(p.amount) }}</span>
                <div class="flex flex-wrap justify-end gap-1.5">
                  <Button
                    v-if="(!p.status || p.status === 'active') && !['cancelled', 'batal', 'selesai'].includes(booking.status)"
                    label="Void"
                    icon="pi pi-ban"
                    size="small"
                    severity="danger"
                    text
                    class="text-xs px-2 py-1"
                    @click="openVoidPaymentDialog(p)"
                  />
                  <Button
                    v-if="p.status === 'void_requested' && canApprovePaymentVoid"
                    label="ACC"
                    icon="pi pi-check"
                    size="small"
                    severity="success"
                    text
                    class="text-xs px-2 py-1"
                    @click="approveVoidRequest(p)"
                  />
                  <Button
                    v-if="p.status === 'void_requested' && canApprovePaymentVoid"
                    label="Tolak"
                    icon="pi pi-times"
                    size="small"
                    severity="danger"
                    text
                    class="text-xs px-2 py-1"
                    @click="openRejectVoidDialog(p)"
                  />
                </div>
              </div>
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
          <Button label="Simpan" icon="pi pi-save" class="app-dialog-button app-dialog-button-primary" @click="submitBookingEdit" :loading="loading" />
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
        <div class="p-3 bg-slate-50 border border-slate-100 rounded-xl flex flex-col gap-2">
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs font-bold text-slate-500 uppercase">Status cek fisik keberangkatan</span>
            <Tag
              :value="physicalCheckStatusLabel(departurePhysicalCheck.status)"
              :severity="physicalCheckSeverity(departurePhysicalCheck.status)"
              class="rounded-md"
            />
          </div>
          <p v-if="departureCheckDone" class="text-sm text-emerald-700 font-semibold">
            Cek fisik sudah dilakukan. Checkout bisa dilanjutkan.
          </p>
          <p v-else class="text-sm text-slate-500">
            Jika memilih lakukan cek fisik, sistem akan membuat request dan checkout belum diproses sampai hasil cek fisik disimpan.
          </p>
          <Button
            v-if="!departureCheckDone"
            label="Buka Cek Fisik"
            icon="pi pi-camera"
            size="small"
            outlined
            @click="openPhysicalCheckForm('departure')"
          />
        </div>
        <div class="p-4 bg-amber-50 border border-amber-100 rounded-xl">
          <p class="text-sm font-semibold text-amber-800 mb-3 flex items-center gap-2">
            <i class="pi pi-camera text-amber-600"></i>
            Pilihan proses cek fisik keberangkatan
          </p>
          <div class="flex flex-col gap-2">
            <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-amber-100 transition-colors"
              :class="!checkoutSkip ? 'bg-amber-100 ring-1 ring-amber-300' : ''">
              <input type="radio" :value="false" v-model="checkoutSkip" class="accent-emerald-600 w-4 h-4" />
              <span class="text-sm font-semibold text-slate-700">Lakukan cek fisik sebelum checkout</span>
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
          <Button
            :label="!checkoutSkip && !departureCheckDone ? 'Buat Request Cek Fisik' : 'Proses Checkout'"
            icon="pi pi-sign-out"
            class="app-dialog-button app-dialog-button-primary"
            @click="submitCheckout"
            :loading="loading || physicalCheckLoading"
          />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Selesai / Complete (E4) ======= -->
    <Dialog :visible="showCompleteDialog" @update:visible="onDialogVisibleChange('showCompleteDialog', $event)" header="Konfirmasi Selesai Sewa" :style="{ width: '460px' }" modal class="custom-dialog">
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
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Tanggal Unit Dikembalikan *</label>
          <Calendar v-model="completeReturnedAt" showTime hourFormat="24" dateFormat="dd M yy" class="w-full" />
        </div>
        <div class="p-3 bg-slate-50 border border-slate-100 rounded-xl flex flex-col gap-2">
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs font-bold text-slate-500 uppercase">Status cek fisik kembali</span>
            <Tag
              :value="physicalCheckStatusLabel(returnPhysicalCheck.status)"
              :severity="physicalCheckSeverity(returnPhysicalCheck.status)"
              class="rounded-md"
            />
          </div>
          <p v-if="returnCheckDone" class="text-sm text-emerald-700 font-semibold">
            Cek fisik pengembalian sudah dilakukan. Sewa bisa diselesaikan.
          </p>
          <p v-else class="text-sm text-slate-500">
            Jika memilih lakukan cek fisik, sistem akan membuat request dan status belum menjadi selesai sampai hasil cek fisik disimpan.
          </p>
          <Button
            v-if="!returnCheckDone"
            label="Buka Cek Fisik"
            icon="pi pi-camera"
            size="small"
            outlined
            @click="openPhysicalCheckForm('return')"
          />
        </div>
        <div class="p-4 bg-amber-50 border border-amber-100 rounded-xl">
          <p class="text-sm font-semibold text-amber-800 mb-3 flex items-center gap-2">
            <i class="pi pi-camera text-amber-600"></i>
            Pilihan proses cek fisik kepulangan
          </p>
          <div class="flex flex-col gap-2">
            <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-amber-100 transition-colors"
              :class="!completeSkip ? 'bg-amber-100 ring-1 ring-amber-300' : ''">
              <input type="radio" :value="false" v-model="completeSkip" class="accent-violet-600 w-4 h-4" />
              <span class="text-sm font-semibold text-slate-700">Lakukan cek fisik sebelum selesai</span>
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
          <Button
            :label="!completeSkip && !returnCheckDone ? 'Buat Request Cek Fisik' : 'Selesaikan Sewa'"
            icon="pi pi-flag-fill"
            class="app-dialog-button app-dialog-button-primary"
            @click="submitComplete"
            :loading="loading || physicalCheckLoading"
            :disabled="!completeReturnedAt"
          />
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
          <Button label="Ya, Waiting List" icon="pi pi-check-circle" class="app-dialog-button app-dialog-button-primary" @click="submitHandle" :loading="loading" />
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
                <Dropdown v-model="handleForm.pricing_package_id" :options="packageOptions" optionLabel="label" optionValue="id" placeholder="Pilih paket..." showClear class="w-full" @change="onHandlePackageChange" />
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
              <span class="text-white/60">{{ handleForm.pricing_mode === 'all_in' ? 'Diskon Ops Dihitung' : 'Total Biaya Ops' }}</span>
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
            <p v-if="handleForm.pricing_mode === 'all_in'" class="text-[10px] text-slate-500 mt-1">* Tagihan konsumen = harga All In dikurangi item diskon. Item biaya operasional lain hanya menjadi catatan internal.</p>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="requestCloseDialog('showHandleDialog')" />
          <Button label="Proses Handle" icon="pi pi-check-circle" class="app-dialog-button app-dialog-button-primary" @click="submitHandle" :loading="loading" />
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
              <Dropdown v-model="detailForm.pricing_package_id" :options="packageOptions" optionLabel="label" optionValue="id" placeholder="Pilih paket All In..." showClear class="w-full" @change="onDetailPackageChange" />
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
              <span class="text-white/60">{{ detailForm.pricing_mode === 'all_in' ? 'Diskon Ops Dihitung' : 'Biaya/Diskon Ops' }}</span>
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
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4 btn-secondary" @click="requestCloseDialog('showDetailDialog')" />
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
          <Button :label="editingCostId ? 'Simpan Perubahan' : 'Tambahkan'" icon="pi pi-check" class="app-dialog-button app-dialog-button-primary" @click="submitCost" :loading="loading" />
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
              <Dropdown v-model="extendForm.pricing_package_id" :options="packageOptions" optionLabel="label" optionValue="id" placeholder="Pilih paket..." showClear class="w-full" @change="onExtendPackageChange" />
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
            <div class="flex justify-between"><span class="text-white/60">{{ extendForm.pricing_mode === 'all_in' ? 'Diskon Ops Dihitung' : 'Biaya Ops' }}</span><span>{{ formatCurrency(extendTotalBiaya) }}</span></div>
            <div class="h-px bg-white/10 my-1"></div>
            <div class="flex justify-between"><span class="font-bold text-cyan-300">Tagihan Konsumen</span><span class="text-lg font-bold text-cyan-300">{{ formatCurrency(extendTagihan) }}</span></div>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text @click="requestCloseDialog('showExtendDialog')" />
          <Button label="Proses Extend" icon="pi pi-check" class="app-dialog-button app-dialog-button-warning" @click="submitExtend" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Ganti Unit (Rolling) — E5 Revamp ======= -->
    <Dialog :visible="showRollingDialog" @update:visible="onDialogVisibleChange('showRollingDialog', $event)" :header="rollingStep === 1 ? 'Ganti Unit (Rolling) - Step 1: Koreksi Unit Lama' : 'Ganti Unit (Rolling) - Step 2: Pilih Unit Baru'" :style="{ width: '760px' }" modal :breakpoints="{ '820px': '95vw' }" class="custom-dialog">
      <!-- Step indicator -->
      <div class="flex items-center gap-2 mb-4">
        <div class="flex items-center gap-1.5">
          <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold" :class="rollingStep >= 1 ? 'bg-sky-600 text-white' : 'bg-slate-200 text-slate-400'">1</div>
          <span class="text-xs font-semibold" :class="rollingStep === 1 ? 'text-sky-700' : 'text-slate-400'">Koreksi Unit Lama</span>
        </div>
        <div class="flex-1 h-px bg-slate-200"></div>
        <div class="flex items-center gap-1.5">
          <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold" :class="rollingStep >= 2 ? 'bg-sky-600 text-white' : 'bg-slate-200 text-slate-400'">2</div>
          <span class="text-xs font-semibold" :class="rollingStep === 2 ? 'text-sky-700' : 'text-slate-400'">Unit Baru</span>
        </div>
      </div>

      <!-- Step 1 -->
      <div v-if="rollingStep === 1" class="flex flex-col gap-4 pt-1">
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Unit Lama & Driver</legend>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Transaksi Aktif *</label>
              <Dropdown v-model="rollingForm.booking_detail_id" :options="activeDetails" optionLabel="unit.no_polisi" optionValue="id" placeholder="Pilih unit aktif" class="w-full" @change="onRollingDetailChange(rollingForm.booking_detail_id)" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Unit Lama *</label>
              <Dropdown v-model="rollingForm.unit_id_lama" :options="units" optionLabel="no_polisi" optionValue="id" placeholder="Pilih unit" filter class="w-full" @change="onRollingOldUnitChange" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Driver</label>
              <Dropdown v-model="rollingForm.driver_id_lama" :options="drivers" optionLabel="nama" optionValue="id" placeholder="Lepas kunci / pilih" filter showClear class="w-full" />
            </div>
          </div>
        </fieldset>

        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Periode Koreksi</legend>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-2">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Mulai</label>
              <Calendar v-model="rollingForm.tgl_sewa_lama" showTime hourFormat="24" dateFormat="dd M yy" class="w-full" disabled />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Kembali</label>
              <Calendar v-model="rollingForm.tgl_kembali_lama" showTime hourFormat="24" dateFormat="dd M yy" class="w-full" disabled />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Lama Sewa *</label>
              <InputNumber v-model="rollingForm.lama_sewa_lama" :min="1" :max="Math.max(1, (rollingForm.lama_sewa_original || 1) - 1)" placeholder="Jml" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Paket *</label>
              <Dropdown v-model="rollingForm.paket_sewa_lama" :options="paketOptions" optionLabel="label" optionValue="value" class="w-full" />
            </div>
          </div>
        </fieldset>

        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Harga & Mode Tagihan Lama</legend>
          <div class="flex flex-col gap-3 mt-2">
            <div class="grid grid-cols-2 gap-3">
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-600">Harga Mobil *</label>
                <InputNumber v-model="rollingForm.harga_mobil_lama" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-600">Diskon</label>
                <InputNumber v-model="rollingForm.diskon_mobil_lama" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
              </div>
            </div>
            <SelectButton v-model="rollingForm.pricing_mode_lama" :options="pricingModeOptions" optionLabel="label" optionValue="value" class="w-full" />
            <div v-if="rollingForm.pricing_mode_lama === 'all_in'" class="flex flex-col gap-3 p-3 bg-cyan-50 border border-cyan-100 rounded-xl">
              <Dropdown v-model="rollingForm.pricing_package_id_lama" :options="packageOptions" optionLabel="label" optionValue="id" placeholder="Pilih paket..." showClear class="w-full" @change="onRollingOldPackageChange" />
              <InputNumber v-if="!rollingForm.pricing_package_id_lama" v-model="rollingForm.harga_all_in_lama" mode="currency" currency="IDR" locale="id-ID" placeholder="Harga All In manual" class="w-full" />
            </div>
          </div>
        </fieldset>

        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Biaya Operasional Lama</legend>
          <div class="flex flex-col gap-2 mt-2">
            <div v-if="!rollingForm.costs_lama.length" class="text-center text-sm text-slate-400 py-2">Belum ada biaya.</div>
            <div v-for="(cost, idx) in rollingForm.costs_lama" :key="idx" class="grid grid-cols-12 gap-2 items-center">
              <div class="col-span-4"><Dropdown v-model="cost.cost_type_id" :options="costTypeOptions" optionLabel="label" optionValue="id" placeholder="Tipe" class="w-full" showClear @change="onRollingOldCostTypeChange(idx, cost.cost_type_id)" /></div>
              <div class="col-span-4"><InputText v-model="cost.label" placeholder="Keterangan" class="w-full" /></div>
              <div class="col-span-3"><InputNumber v-model="cost.amount" mode="currency" currency="IDR" locale="id-ID" class="w-full" /></div>
              <div class="col-span-1"><Button icon="pi pi-times" text rounded severity="danger" size="small" class="w-7 h-7 p-0" @click="rollingForm.costs_lama.splice(idx,1)" /></div>
            </div>
            <Button label="+ Tambah Biaya" text size="small" class="text-blue-600 font-semibold self-start" @click="rollingForm.costs_lama.push({cost_type_id:null,type:'biaya',label:'',amount:0,keterangan:''})" />
          </div>
        </fieldset>

        <div class="bg-slate-900 rounded-xl p-4 text-white">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2">Kalkulasi Koreksi</p>
          <div class="flex flex-col gap-1.5 text-sm">
            <div class="flex justify-between"><span class="text-white/60">Harga Sewa Lama</span><span>{{ formatCurrency(rollingOldHargaSewa) }}</span></div>
            <div class="flex justify-between"><span class="text-white/60">{{ rollingForm.pricing_mode_lama === 'all_in' ? 'Diskon Ops Lama Dihitung' : 'Biaya Ops Lama' }}</span><span>{{ formatCurrency(rollingOldTotalBiaya) }}</span></div>
            <div class="flex justify-between"><span class="text-white/60">Sisa Lama Sewa Rolling</span><span>{{ rollingForm.lama_sewa || 0 }} {{ rollingForm.paket_sewa }}</span></div>
            <div class="h-px bg-white/10 my-1"></div>
            <div class="flex justify-between"><span class="font-bold text-sky-300">Tagihan Unit Lama</span><span class="text-lg font-bold text-sky-300">{{ formatCurrency(rollingOldTagihan) }}</span></div>
          </div>
        </div>
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
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Mulai</label>
              <Calendar v-model="rollingForm.tgl_sewa" showTime hourFormat="24" dateFormat="dd M yy" class="w-full" disabled />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Kembali</label>
              <Calendar v-model="rollingForm.tgl_kembali" showTime hourFormat="24" dateFormat="dd M yy" class="w-full" disabled />
            </div>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Lama Sewa</label>
              <Dropdown v-model="rollingForm.lama_sewa" :options="lamaSewaOptions" optionLabel="label" optionValue="value" placeholder="Lama" class="w-full" disabled />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Paket</label>
              <Dropdown v-model="rollingForm.paket_sewa" :options="paketOptions" optionLabel="label" optionValue="value" class="w-full" disabled />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Harga Mobil *</label>
              <InputNumber v-model="rollingForm.harga_mobil" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-600">Diskon</label>
              <InputNumber v-model="rollingForm.diskon_mobil" mode="currency" currency="IDR" locale="id-ID" class="w-full" />
            </div>
          </div>
          <div class="mt-3">
            <SelectButton v-model="rollingForm.pricing_mode" :options="pricingModeOptions" optionLabel="label" optionValue="value" class="w-full" />
            <div v-if="rollingForm.pricing_mode === 'all_in'" class="flex flex-col gap-2 mt-3 p-3 bg-cyan-50 border border-cyan-100 rounded-xl">
              <Dropdown v-model="rollingForm.pricing_package_id" :options="packageOptions" optionLabel="label" optionValue="id" placeholder="Pilih paket..." showClear class="w-full" @change="onRollingNewPackageChange" />
              <InputNumber v-if="!rollingForm.pricing_package_id" v-model="rollingForm.harga_all_in" mode="currency" currency="IDR" locale="id-ID" placeholder="Harga All In manual" class="w-full" />
            </div>
          </div>
        </fieldset>
        <fieldset class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
          <legend class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2">Biaya Operasional</legend>
          <div class="flex flex-col gap-2 mt-2">
            <div v-if="!rollingForm.costs.length" class="text-center text-sm text-slate-400 py-2">Belum ada biaya.</div>
            <div v-for="(cost, idx) in rollingForm.costs" :key="idx" class="grid grid-cols-12 gap-2 items-center">
              <div class="col-span-4"><Dropdown v-model="cost.cost_type_id" :options="costTypeOptions" optionLabel="label" optionValue="id" placeholder="Tipe" class="w-full" showClear @change="onRollingNewCostTypeChange(idx, cost.cost_type_id)" /></div>
              <div class="col-span-4"><InputText v-model="cost.label" placeholder="Keterangan" class="w-full" /></div>
              <div class="col-span-3"><InputNumber v-model="cost.amount" mode="currency" currency="IDR" locale="id-ID" class="w-full" /></div>
              <div class="col-span-1"><Button icon="pi pi-times" text rounded severity="danger" size="small" class="w-7 h-7 p-0" @click="rollingForm.costs.splice(idx,1)" /></div>
            </div>
            <Button label="+ Tambah Biaya" text size="small" class="text-blue-600 font-semibold self-start" @click="rollingForm.costs.push({cost_type_id:null,type:'biaya',label:'',amount:0,keterangan:''})" />
          </div>
        </fieldset>
        <div class="bg-slate-900 rounded-xl p-4 text-white">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2">Kalkulasi Unit Baru</p>
          <div class="flex flex-col gap-1.5 text-sm">
            <div class="flex justify-between"><span class="text-white/60">Harga Sewa</span><span>{{ formatCurrency(rollingHargaSewa) }}</span></div>
            <div class="flex justify-between"><span class="text-white/60">{{ rollingForm.pricing_mode === 'all_in' ? 'Diskon Ops Dihitung' : 'Biaya Ops' }}</span><span>{{ formatCurrency(rollingTotalBiaya) }}</span></div>
            <div class="h-px bg-white/10 my-1"></div>
            <div class="flex justify-between"><span class="font-bold text-cyan-300">Tagihan Konsumen</span><span class="text-lg font-bold text-cyan-300">{{ formatCurrency(rollingTagihan) }}</span></div>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text @click="requestCloseDialog('showRollingDialog')" />
          <Button v-if="rollingStep === 1" label="Lanjut" icon="pi pi-arrow-right" iconPos="right" class="app-dialog-button app-dialog-button-info" @click="() => { syncRollingOldReturnDate(); syncRollingNewSchedule(); rollingStep = 2; }" />
          <Button v-if="rollingStep === 2" label="Kembali" icon="pi pi-arrow-left" text class="text-slate-500" @click="rollingStep = 1" />
          <Button v-if="rollingStep === 2" label="Proses Rolling" icon="pi pi-check" class="app-dialog-button app-dialog-button-info" @click="submitRolling" :loading="loading" />
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
          <Button label="Proses Stop" icon="pi pi-check" class="app-dialog-button app-dialog-button-danger" @click="submitStopEarly" :loading="loading" />
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
          <Button label="Ya, Batalkan Booking" icon="pi pi-ban" class="app-dialog-button app-dialog-button-danger" @click="submitBatal" :loading="loading" />
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
        <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 text-sm">
          <div class="flex justify-between text-slate-500 mb-1">
            <span>Sisa tagihan saat ini</span>
            <span class="font-bold text-rose-500">{{ formatCurrency(bookingSisaTagihan) }}</span>
          </div>
          <div v-if="paymentForm.amount" class="flex justify-between text-slate-500">
            <span>Sisa setelah pembayaran ini</span>
            <span class="font-bold" :class="(bookingSisaTagihan - paymentForm.amount) <= 0 ? 'text-emerald-600' : 'text-amber-600'">
              {{ formatCurrency(Math.max(0, bookingSisaTagihan - paymentForm.amount)) }}
            </span>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="requestCloseDialog('showPaymentDialog')" />
          <Button
            label="Simpan Pembayaran"
            icon="pi pi-check"
            class="app-dialog-button app-dialog-button-primary"
            @click="submitPayment"
            :loading="loading || paymentConfirming || paymentSubmitting"
            :disabled="isPaymentSubmitDisabled"
          />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Request Void Pembayaran ======= -->
    <Dialog :visible="showVoidPaymentDialog" @update:visible="onDialogVisibleChange('showVoidPaymentDialog', $event)" header="Request Void Pembayaran" :style="{ width: '460px' }" modal class="custom-dialog">
      <div class="flex flex-col gap-5 pt-2">
        <div class="p-3 bg-rose-50 rounded-xl border border-rose-100 text-sm">
          <div class="flex justify-between text-slate-500 mb-1">
            <span>Pembayaran</span>
            <span class="font-bold text-rose-600">{{ selectedVoidPayment ? formatCurrency(selectedVoidPayment.amount) : '-' }}</span>
          </div>
          <div class="flex justify-between text-slate-500">
            <span>Tipe</span>
            <span class="font-bold uppercase">{{ selectedVoidPayment?.payment_type || '-' }}</span>
          </div>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Alasan Void *</label>
          <Textarea
            v-model="voidPaymentForm.void_reason"
            rows="3"
            placeholder="Jelaskan kenapa pembayaran perlu di-void..."
            class="w-full"
            :class="{ 'p-invalid': voidPaymentFormErrors.void_reason }"
          />
          <small class="p-error" v-if="voidPaymentFormErrors.void_reason">{{ voidPaymentFormErrors.void_reason[0] }}</small>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="requestCloseDialog('showVoidPaymentDialog')" />
          <Button label="Kirim Request" icon="pi pi-send" class="app-dialog-button app-dialog-button-danger" @click="submitVoidPaymentRequest" :loading="loading" />
        </div>
      </template>
    </Dialog>

    <!-- ======= DIALOG: Tolak Void Pembayaran ======= -->
    <Dialog :visible="showRejectVoidDialog" @update:visible="onDialogVisibleChange('showRejectVoidDialog', $event)" header="Tolak Void Pembayaran" :style="{ width: '440px' }" modal class="custom-dialog">
      <div class="flex flex-col gap-5 pt-2">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-600">Catatan Penolakan</label>
          <Textarea v-model="rejectVoidForm.void_rejection_note" rows="3" placeholder="Opsional, misalnya pembayaran sudah cocok dengan mutasi bank..." class="w-full" />
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-3">
          <Button label="Batal" icon="pi pi-times" text class="text-slate-500 font-semibold px-4" @click="requestCloseDialog('showRejectVoidDialog')" />
          <Button label="Tolak Request" icon="pi pi-times" class="app-dialog-button app-dialog-button-danger" @click="submitRejectVoid" :loading="loading" />
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
            class="app-dialog-button app-dialog-button-primary"
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
  width: 100%;
  padding: var(--space-2xl);
  background: var(--page-bg);
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

.detail-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-xl);
  margin-bottom: var(--space-2xl);
}

.detail-title {
  margin: 0;
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 20px;
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: 0;
}

.detail-subtitle {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  margin: 0;
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 400;
  line-height: 1.4;
}

.detail-subtitle i {
  color: var(--text-tertiary);
  font-size: 11px;
}



.overdue-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border-radius: var(--radius-sm);
  background: #FCEAE9;
  color: #B02A24;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.3;
}

.detail-action-bar {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: var(--space-sm);
}

.detail-primary-action,
.detail-secondary-action {
  min-height: 34px;
  border-radius: var(--radius-full) !important;
  padding: 8px 16px !important;
  font-size: 12px !important;
  font-weight: 600 !important;
  box-shadow: none !important;
}

.detail-primary-action {
  border: none !important;
  background: var(--text-primary) !important;
  color: #fff !important;
}

.detail-primary-action:hover {
  opacity: 0.92;
}

.detail-secondary-action {
  border: 1px solid var(--surface-border) !important;
  background: var(--surface-default) !important;
  color: var(--text-primary) !important;
}

.detail-secondary-action:hover {
  background: var(--card-bg-hover) !important;
}

.loading-grid,
.detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--space-xl);
  align-items: start;
}

.detail-main-column,
.detail-side-column {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: var(--space-xl);
}

.app-card {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  box-shadow: var(--shadow-tile);
}

.app-section-header {
  min-height: 54px;
  border-bottom: 1px solid var(--surface-border);
  background: var(--surface-default);
}

.app-section-header h2,
.app-section-header h3 {
  margin: 0;
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 600;
  line-height: 1.3;
}

.app-section-header p {
  color: var(--text-secondary);
  font-size: 12px;
  line-height: 1.4;
}

.app-section-header :is(.w-9, .w-8) {
  border-radius: var(--radius-default);
  background: var(--card-bg) !important;
  color: var(--text-secondary) !important;
}

.app-muted-panel {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--card-bg);
}

.app-card :deep(.p-button.p-button-text) {
  color: var(--text-secondary);
}

.app-card :deep(.p-button.p-button-text:hover) {
  background: var(--card-bg-hover);
  color: var(--text-primary);
}

.app-section-header :deep(.p-button:not(.p-button-text)) {
  border: none !important;
  border-radius: var(--radius-full) !important;
  background: var(--text-primary) !important;
  color: #fff !important;
  box-shadow: none !important;
}

.app-section-header :deep(.p-button:not(.p-button-text):hover) {
  opacity: 0.92;
}

.app-card :deep(.status-badge) {
  border-radius: var(--radius-sm);
  padding: 4px 8px;
  font-size: 11px;
}

.app-card :deep(.status-badge.neutral) {
  background: #E4E8F3;
  color: #4A5060;
}

.app-card :deep(.status-badge.info) {
  background: #E1F4F6;
  color: #085A66;
}

.app-card :deep(.status-badge.success) {
  background: #E6F6EC;
  color: #147239;
}

.app-card :deep(.status-badge.error) {
  background: #FCEAE9;
  color: #B02A24;
}

.app-card :deep(.status-badge.warning) {
  background: #FDF4D9;
  color: #8C660A;
}

.financial-summary-card {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  padding: var(--space-xl);
  color: var(--text-primary);
  box-shadow: var(--shadow-tile);
}

.financial-summary-card h2 {
  margin: 0;
  color: var(--text-primary);
  font-family: var(--font-headline);
  font-size: 14px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: 0;
  text-transform: none;
}

.summary-icon {
  display: flex;
  width: 32px;
  height: 32px;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-default);
  background: var(--card-bg);
  color: var(--text-secondary);
}

.summary-row,
.summary-status {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-md);
}

.summary-row span,
.summary-status span {
  color: var(--text-secondary);
  font-size: 12px;
  font-weight: 500;
}

.summary-row strong {
  color: var(--text-primary);
  font-family: var(--font-mono);
  font-size: 13px;
  font-weight: 500;
  line-height: 1.4;
  text-align: right;
}

.summary-row .summary-positive {
  color: var(--positive);
}

.summary-divider {
  height: 1px;
  margin: 2px 0;
  background: var(--surface-border);
}

.summary-balance {
  border-radius: var(--radius-default);
  background: var(--card-bg);
  padding: var(--space-lg);
}

.summary-balance span {
  display: block;
  margin-bottom: 4px;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.3;
}

.summary-balance strong {
  display: block;
  font-family: var(--font-headline);
  font-size: 20px;
  font-weight: 700;
  line-height: 1.25;
}

.summary-balance .is-due {
  color: #B02A24;
}

.summary-balance .is-paid {
  color: #147239;
}

/* ---- DataTable inside vehicle cards ---- */
.custom-mini-table :deep(.p-datatable-thead > tr > th) {
  background-color: var(--card-bg);
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0;
  text-transform: uppercase;
  padding: 0.625rem 0.75rem;
  border-bottom: 1px solid var(--surface-border);
}

.custom-mini-table :deep(.p-datatable-tbody > tr > td) {
  padding: 0.625rem 0.75rem;
  border-bottom: 1px solid var(--surface-border);
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
  color: var(--text-primary);
  border-bottom: 1px solid var(--surface-border);
}

:deep(.custom-dialog .p-dialog-content) {
  padding: 1rem 1.5rem 0.5rem 1.5rem;
}

:deep(.custom-dialog .p-dialog-footer) {
  padding: 0.75rem 1.5rem 1.25rem 1.5rem;
  border-top: 1px solid var(--surface-border);
}

:deep(.app-dialog-button) {
  min-height: 36px;
  border-radius: var(--radius-full) !important;
  padding: 8px 18px !important;
  font-size: 12px !important;
  font-weight: 600 !important;
  box-shadow: none !important;
  transition: opacity 0.2s ease, background-color 0.2s ease;
}

:deep(.app-dialog-button-primary),
:deep(.app-dialog-button-info),
:deep(.app-dialog-button-warning) {
  border: none !important;
  background: var(--text-primary) !important;
  color: #fff !important;
}

:deep(.app-dialog-button-primary:hover),
:deep(.app-dialog-button-info:hover),
:deep(.app-dialog-button-warning:hover) {
  opacity: 0.92;
}

:deep(.app-dialog-button-secondary) {
  border: 1px solid var(--surface-border) !important;
  background: var(--surface-default) !important;
  color: var(--text-primary) !important;
}

:deep(.app-dialog-button-secondary:hover) {
  background: var(--card-bg-hover) !important;
}

:deep(.app-dialog-button-danger) {
  border: none !important;
  background: var(--negative) !important;
  color: #fff !important;
}

:deep(.app-dialog-button-danger:hover) {
  opacity: 0.9;
}

/* ---- Fieldset inside dialogs ---- */
fieldset {
  margin: 0;
}

fieldset legend {
  margin-left: 0.25rem;
}

.financial-summary-card {
  border-radius: var(--radius-default);
}

.cost-reference-card {
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--surface-default);
  padding: 16px;
}

.cost-mini-box {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 4px;
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-default);
  background: var(--card-bg);
  padding: 12px;
}

.cost-mini-box span {
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0;
  text-transform: uppercase;
}

.cost-mini-box strong {
  color: var(--text-primary);
  font-size: 13px;
}

@media (min-width: 1024px) {
  .loading-grid,
  .detail-grid {
    grid-template-columns: minmax(0, 2fr) minmax(320px, 0.95fr);
  }

  .detail-main-column {
    grid-column: 1;
  }

  .detail-side-column {
    position: sticky;
    top: 20px;
    grid-column: 2;
  }
}

@media (max-width: 768px) {
  .booking-detail-container {
    padding: var(--space-lg);
  }

  .detail-page-header {
    flex-direction: column;
    gap: var(--space-lg);
    margin-bottom: var(--space-xl);
  }

  .detail-title {
    font-size: 18px;
    overflow-wrap: anywhere;
  }

  .detail-action-bar {
    width: 100%;
    justify-content: stretch;
  }

  .detail-action-bar :deep(.p-button) {
    flex: 1 1 calc(50% - var(--space-sm));
  }

  .detail-grid,
  .detail-main-column,
  .detail-side-column {
    gap: var(--space-lg);
  }

  .app-section-header {
    padding: var(--space-lg) !important;
  }

  .app-card > .p-6,
  .app-card > div.p-6 {
    padding: var(--space-lg) !important;
  }
}
</style>

