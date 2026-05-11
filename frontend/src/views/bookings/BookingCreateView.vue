<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useBooking } from '../../composables/useBooking';
import { useCustomer } from '../../composables/useCustomer';
import { useUnit } from '../../composables/useUnit';
import { usePaymentAccount } from '../../composables/usePaymentAccount';
import Button from 'primevue/button';
import Card from 'primevue/card';
import RadioButton from 'primevue/radiobutton';
import Dropdown from 'primevue/dropdown';
import SelectButton from 'primevue/selectbutton';
import InputText from 'primevue/inputtext';
import Calendar from 'primevue/calendar';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import Message from 'primevue/message';

const router = useRouter();
const toast = useToast();
const { store, loading: bookingLoading } = useBooking();
const { customers, fetchAll: fetchCustomers, loading: customersLoading } = useCustomer();
const { units, fetchAll: fetchUnits, loading: unitsLoading } = useUnit();

const { accounts: paymentAccounts, fetchAll: fetchAccounts } = usePaymentAccount();

const paketOptions = [
  { label: 'Harian', value: 'harian' },
  { label: 'Mingguan', value: 'mingguan' },
  { label: 'Bulanan', value: 'bulanan' },
];

const form = ref({
  customer_mode: 'existing',
  customer_id: null,
  customer_name: '',
  customer_phone: '',
  customer_city: '',

  unit_mode: 'existing',
  unit_id: null,
  unit_placeholder: '',

  tgl_sewa: null,
  tgl_kembali: null,
  lama_sewa: null,
  paket_sewa: 'harian',
  tujuan: '',
  alamat_penjemputan: '',
  harga_dealing: null,
  dp: null,
  rekening_dp_id: null,
  catatan: ''
});

const selectedCustomer = computed(() => {
  if (form.value.customer_mode === 'existing' && form.value.customer_id) {
    return customers.value.find(c => c.id === form.value.customer_id);
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
  fetchCustomers();
  fetchUnits({ status: 'Aktif' });
  fetchAccounts({ per_page: 100 });

  // Pre-fill from query parameters (from Calendar)
  if (router.currentRoute.value.query.unit_id) {
    form.value.unit_id = parseInt(router.currentRoute.value.query.unit_id);
    form.value.unit_mode = 'existing';
  }
  if (router.currentRoute.value.query.tgl_sewa) {
    const date = new Date(router.currentRoute.value.query.tgl_sewa);
    // Set to 07:00 as per project rule for start time
    date.setHours(7, 0, 0);
    form.value.tgl_sewa = date;
  }
});

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
      delete payload.customer_name;
      delete payload.customer_phone;
      delete payload.customer_city;
    } else {
      delete payload.customer_id;
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

    const booking = await store(payload);
    toast.add({ severity: 'success', summary: 'Sukses', detail: `Booking ${booking.kode_booking} berhasil dibuat`, life: 3000 });
    
    // Redirect to list for now as detail view might not be ready
    router.push({ name: 'BookingList' });
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || 'Gagal membuat booking', life: 5000 });
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

// Default time logic
watch(() => form.value.tgl_sewa, (newVal) => {
  if (newVal && newVal instanceof Date) {
    // If time is 00:00:00 (newly selected from calendar), set to 07:00
    if (newVal.getHours() === 0 && newVal.getMinutes() === 0 && newVal.getSeconds() === 0) {
      newVal.setHours(7, 0, 0);
    }
  }
});

watch(() => form.value.tgl_kembali, (newVal) => {
  if (newVal && newVal instanceof Date) {
    // If time is 00:00:00 (newly selected from calendar), set to 23:59
    if (newVal.getHours() === 0 && newVal.getMinutes() === 0 && newVal.getSeconds() === 0) {
      newVal.setHours(23, 59, 0);
    }
  }
});

const resetForm = () => {
  form.value = {
    customer_mode: 'existing',
    customer_id: null,
    customer_name: '',
    customer_phone: '',
    customer_city: '',
    unit_mode: 'existing',
    unit_id: null,
    unit_placeholder: '',
    tgl_sewa: null,
    tgl_kembali: null,
    lama_sewa: null,
    paket_sewa: 'harian',
    tujuan: '',
    alamat_penjemputan: '',
    harga_dealing: null,
    dp: null,
    rekening_dp_id: null,
    catatan: ''
  };
};

const getStatusSeverity = (status) => {
  switch (status) {
    case 'Blacklist': return 'danger';
    case 'Redflag': return 'warning';
    case 'Corporate': return 'help';
    case 'Normal': return 'success';
    default: return 'info';
  }
};

const unitOptions = computed(() => {
    return units.value.map(u => ({
        ...u,
        label: `${u.merk} ${u.tipe}`,
        sublabel: `${u.no_polisi} - ${u.rental_owner?.nama || 'N/A'}`,
        searchableLabel: `${u.merk} ${u.tipe} ${u.no_polisi} ${u.rental_owner?.nama || ''}`
    }));
});

const customerOptions = computed(() => {
    return customers.value.map(c => ({
        id: c.id,
        name: `${c.nama} - ${c.kota || '-'}`
    }));
});

</script>

<template>
  <div class="booking-create-container">
    <div class="page-header mb-8 flex justify-between items-center">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Buat Booking Baru</h1>
        <p class="text-slate-500 mt-1">Input data pelanggan, unit, dan detail penyewaan</p>
      </div>
      <Button label="Batal" icon="pi pi-times" class="p-button-text p-button-secondary" @click="router.back()" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
      <!-- Left Column: Customer & Unit -->
      <div class="xl:col-span-4 flex flex-col gap-8">
        <!-- Section 1: Pelanggan -->
        <Card class="premium-card overflow-hidden">
          <template #title>
            <div class="card-header-accent bg-slate-900 px-6 py-4 -mx-6 -mt-6 mb-6">
              <div class="flex items-center gap-3">
                <div class="icon-circle bg-tosca">
                  <i class="pi pi-user text-white"></i>
                </div>
                <h2 class="text-white font-bold m-0 text-lg">Informasi Pelanggan</h2>
              </div>
            </div>
          </template>
          <template #content>
            <div class="flex flex-col gap-6">
              <SelectButton 
                v-model="form.customer_mode" 
                :options="[{label: 'Pelanggan Lama', value: 'existing'}, {label: 'Pelanggan Baru', value: 'new'}]" 
                optionLabel="label" 
                optionValue="value" 
                class="w-full custom-selectbutton"
              />

              <div v-if="form.customer_mode === 'existing'" class="flex flex-col gap-3">
                <div class="form-field-vertical">
                  <label class="field-label">Cari Nama Pelanggan</label>
                  <Dropdown 
                    v-model="form.customer_id" 
                    :options="customerOptions" 
                    optionLabel="name" 
                    optionValue="id" 
                    placeholder="Pilih dari database..." 
                    filter 
                    :loading="customersLoading"
                    class="w-full premium-input"
                  />
                </div>
                
                <transition name="fade">
                  <div v-if="selectedCustomer" class="customer-preview mt-2">
                    <div class="flex justify-between items-start mb-3">
                      <span class="font-bold text-slate-900">{{ selectedCustomer.nama }}</span>
                      <Tag :value="selectedCustomer.status" :severity="getStatusSeverity(selectedCustomer.status)" class="premium-tag" />
                    </div>
                    <div class="contact-rows space-y-2 text-sm">
                      <div class="flex items-center text-slate-600">
                        <i class="pi pi-phone mr-3 text-tosca text-xs"></i>
                        <span>{{ selectedCustomer.kontak_1 }}</span>
                      </div>
                      <div class="flex items-center text-slate-600">
                        <i class="pi pi-map-marker mr-3 text-tosca text-xs"></i>
                        <span>{{ selectedCustomer.kota || '-' }}</span>
                      </div>
                    </div>

                    <Message v-if="isBlacklisted" severity="error" icon="pi pi-ban" class="mt-4 !m-0">
                      Blokir: Pelanggan terdaftar dalam Blacklist.
                    </Message>
                    <Message v-if="isRedflag" severity="warn" icon="pi pi-exclamation-triangle" class="mt-4 !m-0">
                      Peringatan: Pelanggan memiliki catatan Redflag.
                    </Message>
                  </div>
                </transition>
              </div>

              <div v-else class="new-customer-form grid gap-4 animate-fade-in">
                <div class="form-field-vertical">
                  <label class="field-label">Nama Lengkap *</label>
                  <InputText v-model="form.customer_name" placeholder="Input nama" class="w-full premium-input" />
                </div>
                <div class="form-field-vertical">
                  <label class="field-label">Nomor WhatsApp *</label>
                  <InputText v-model="form.customer_phone" placeholder="08xxxxxxxxxx" class="w-full premium-input" />
                </div>
                <div class="form-field-vertical">
                  <label class="field-label">Asal Kota *</label>
                  <InputText v-model="form.customer_city" placeholder="Input kota" class="w-full premium-input" />
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Section 2: Unit -->
        <Card class="premium-card overflow-hidden">
          <template #title>
            <div class="card-header-accent bg-slate-900 px-6 py-4 -mx-6 -mt-6 mb-6">
              <div class="flex items-center gap-3">
                <div class="icon-circle bg-tosca">
                  <i class="pi pi-car text-white"></i>
                </div>
                <h2 class="text-white font-bold m-0 text-lg">Unit Kendaraan</h2>
              </div>
            </div>
          </template>
          <template #content>
            <div class="flex flex-col gap-6">
              <SelectButton 
                v-model="form.unit_mode" 
                :options="[{label: 'Unit Ready', value: 'existing'}, {label: 'Placeholder', value: 'placeholder'}]" 
                optionLabel="label" 
                optionValue="value" 
                class="w-full custom-selectbutton"
              />

              <div v-if="form.unit_mode === 'existing'" class="form-field-vertical">
                <label class="field-label">Cari Unit / Nopol</label>
                <Dropdown 
                  v-model="form.unit_id" 
                  :options="unitOptions" 
                  optionLabel="searchableLabel" 
                  optionValue="id" 
                  placeholder="Cari Mobil/Nopol/Pemilik..." 
                  filter 
                  :loading="unitsLoading"
                  class="w-full premium-input"
                >
                    <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center">
                            <span class="font-bold">{{ unitOptions.find(u => u.id === slotProps.value)?.label }}</span>
                        </div>
                        <span v-else>{{ slotProps.placeholder }}</span>
                    </template>
                    <template #option="slotProps">
                        <div class="flex flex-col py-1">
                            <span class="font-bold text-slate-800">{{ slotProps.option.label }}</span>
                            <span class="text-xs text-slate-500">{{ slotProps.option.sublabel }}</span>
                        </div>
                    </template>
                </Dropdown>
              </div>

              <div v-else class="form-field-vertical animate-fade-in">
                <label class="field-label">Deskripsi Unit Sementara *</label>
                <InputText v-model="form.unit_placeholder" placeholder="Misal: Avanza Hitam Pak Budi" class="w-full premium-input" />
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Right Column: Details -->
      <div class="xl:col-span-8">
        <Card class="premium-card h-full flex flex-col">
          <template #title>
            <div class="card-header-accent bg-slate-900 px-6 py-4 -mx-6 -mt-6 mb-8">
              <div class="flex items-center gap-3">
                <div class="icon-circle bg-tosca">
                  <i class="pi pi-calendar text-white"></i>
                </div>
                <h2 class="text-white font-bold m-0 text-lg">Detail Jadwal & Biaya</h2>
              </div>
            </div>
          </template>
          <template #content>
            <div class="flex flex-col gap-8">
              <!-- Grid Layout for tidier Label-Input pairs -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <!-- Date Rows -->
                <div class="form-field-horizontal">
                  <label class="horizontal-label">Mulai Sewa *</label>
                  <div class="horizontal-input">
                    <Calendar v-model="form.tgl_sewa" showIcon showTime hourFormat="24" iconDisplay="input" placeholder="Pilih Tanggal & Jam" class="w-full premium-calendar" />
                  </div>
                </div>
                
                <div class="form-field-horizontal">
                  <label class="horizontal-label">Selesai Sewa *</label>
                  <div class="horizontal-input">
                    <Calendar v-model="form.tgl_kembali" showIcon showTime hourFormat="24" iconDisplay="input" placeholder="Pilih Tanggal & Jam" :minDate="form.tgl_sewa" class="w-full premium-calendar" />
                  </div>
                </div>

                <!-- Durasi Sewa -->
                <div class="form-field-horizontal">
                  <label class="horizontal-label">Lama Sewa *</label>
                  <div class="horizontal-input">
                    <InputNumber v-model="form.lama_sewa" :min="1" placeholder="Jumlah hari/minggu/bulan" class="w-full premium-input" />
                  </div>
                </div>

                <div class="form-field-horizontal">
                  <label class="horizontal-label">Paket Sewa *</label>
                  <div class="horizontal-input">
                    <Dropdown v-model="form.paket_sewa" :options="paketOptions" optionLabel="label" optionValue="value" placeholder="Pilih paket" class="w-full premium-input" />
                  </div>
                </div>

                <!-- Usage Info -->
                <div class="form-field-horizontal md:col-span-2">
                  <label class="horizontal-label">Tujuan</label>
                  <div class="horizontal-input">
                    <InputText v-model="form.tujuan" placeholder="Ke luar kota / wisata / kantor..." class="w-full premium-input" />
                  </div>
                </div>

                <div class="form-field-horizontal md:col-span-2">
                  <label class="horizontal-label">Alamat Jemput</label>
                  <div class="horizontal-input">
                    <Textarea v-model="form.alamat_penjemputan" rows="2" placeholder="Input alamat lengkap penjemputan..." class="w-full premium-input" />
                  </div>
                </div>

                <!-- Financials -->
                <div class="form-field-horizontal">
                  <label class="horizontal-label">Harga Dealing</label>
                  <div class="horizontal-input">
                    <InputNumber v-model="form.harga_dealing" mode="currency" currency="IDR" locale="id-ID" placeholder="Rp 0" class="w-full premium-input" />
                  </div>
                </div>

                <div class="form-field-horizontal">
                  <label class="horizontal-label">Uang Muka (DP)</label>
                  <div class="horizontal-input">
                    <InputNumber v-model="form.dp" mode="currency" currency="IDR" locale="id-ID" placeholder="Rp 0" class="w-full premium-input" />
                  </div>
                </div>

                <!-- Account Section -->
                <transition name="slide-up">
                  <div v-if="form.dp > 0" class="md:col-span-2 bg-slate-50 p-6 rounded-xl border border-slate-200 animate-slide-up">
                    <div class="form-field-horizontal">
                      <label class="horizontal-label text-tosca-dark font-bold">Rekening DP *</label>
                      <div class="horizontal-input">
                        <Dropdown 
                          v-model="form.rekening_dp_id" 
                          :options="accountOptions" 
                          optionLabel="name" 
                          optionValue="id" 
                          placeholder="Pilih Akun Pembayaran" 
                          class="w-full premium-input !border-tosca"
                          :empty-message="'Belum ada akun pembayaran aktif'"
                        />
                      </div>
                    </div>
                  </div>
                </transition>

                <div class="form-field-horizontal md:col-span-2">
                  <label class="horizontal-label">Catatan</label>
                  <div class="horizontal-input">
                    <Textarea v-model="form.catatan" rows="3" placeholder="Informasi tambahan jika ada..." class="w-full premium-input" />
                  </div>
                </div>
              </div>

              <div class="mt-8 flex justify-end gap-4 border-t border-slate-100 pt-8">
                <Button label="Reset" class="p-button-text p-button-secondary px-6" @click="resetForm" />
                <Button 
                  label="Simpan Booking" 
                  icon="pi pi-check" 
                  :loading="bookingLoading" 
                  @click="handleSubmit" 
                  :disabled="isBlacklisted"
                  class="p-button-tosca px-10 py-4 font-bold rounded-xl shadow-lg shadow-tosca/20"
                />
              </div>
            </div>
          </template>
        </Card>
      </div>
    </div>
  </div>
</template>

<style scoped>
.booking-create-container {
  max-width: 1400px;
  margin: 0 auto;
}

/* Premium Card & Header */
.premium-card {
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
  background: white;
}

.icon-circle {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  box-shadow: 0 4px 8px rgba(6, 182, 212, 0.2);
}

/* Field Layouts for Tidiness */
.form-field-vertical {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-field-horizontal {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

@media (min-width: 768px) {
  .form-field-horizontal {
    flex-direction: row;
    align-items: center;
    gap: 20px;
  }
}

.field-label {
  font-size: 0.75rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.horizontal-label {
  min-width: 140px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #334155;
  white-space: nowrap;
}

.horizontal-input {
  flex: 1;
}

/* Colors & Accents */
.bg-tosca { background-color: #06b6d4; }
.text-tosca { color: #06b6d4; }
.text-tosca-dark { color: #0891b2; }

/* Inputs Refinement */
:deep(.premium-input .p-inputtext), 
:deep(.premium-calendar .p-inputtext),
:deep(.premium-input.p-dropdown) {
  border-radius: 10px;
  padding: 10px 14px;
  border: 1.5px solid #e2e8f0;
  background: #fff;
  transition: all 0.2s;
  font-size: 0.95rem;
}

:deep(.premium-input .p-inputtext:focus),
:deep(.premium-input.p-dropdown.p-focus) {
  border-color: #06b6d4;
  box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
}

/* SelectButton Customization */
:deep(.custom-selectbutton .p-button) {
  flex: 1;
  background: #f1f5f9;
  border: none;
  color: #475569;
  border-radius: 10px !important;
  font-weight: 700;
  font-size: 0.85rem;
  padding: 10px;
}

:deep(.custom-selectbutton .p-button.p-highlight) {
  background: #0f172a;
  color: white;
}

/* Customer Preview */
.customer-preview {
  background: #f8fafc;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.premium-tag {
  border-radius: 4px;
  padding: 2px 8px;
  font-size: 0.65rem;
}

/* Animations */
.animate-fade-in { animation: fadeIn 0.3s ease-out; }
.animate-slide-up { animation: slideUp 0.3s ease-out; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* Button Action */
.p-button-tosca {
  background-color: #06b6d4 !important;
  border-color: #06b6d4 !important;
  color: white !important;
}
</style>


