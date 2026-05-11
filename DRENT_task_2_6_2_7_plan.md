# Rencana Implementasi DRENT
## Task 2.6 — Booking: Handle Booking — Detail & Biaya (Backend)
## Task 2.7 — Booking: Handle Booking (Frontend)

Dokumen ini berisi instruksi spesifik dan terstruktur untuk diimplementasikan oleh AI Agent (model coding). Patuhi semua **Global Rules DRENT** selama pengerjaan.

---

## BAGIAN 1: BACKEND (Task 2.6)

### 1. Endpoint & Routing
Buka file `routes/api.php` dan pastikan endpoint berikut ada di bawah `Route::prefix('v1')` dan dilindungi oleh middleware auth sanctum & branch:

```php
Route::post('/bookings/{booking}/details', [BookingDetailController::class, 'store']);
Route::post('/booking-details/{bookingDetail}/costs', [BookingCostController::class, 'store']);
Route::patch('/bookings/{booking}/handle', [BookingController::class, 'handle']);
```

### 2. Form Request (Validasi)
Buat class request berikut (Gunakan `php artisan make:request`):

**`StoreBookingDetailRequest`**
- Aturan: `unit_id` (required, exists:units,id), `driver_id` (nullable, exists:drivers,id), `tgl_sewa` (required, date), `tgl_kembali` (required, date, after_or_equal:tgl_sewa), `harga_mobil` (required, integer, min:0), `diskon_mobil` (nullable, integer, min:0), `detail_type` (required, string, default 'sewa_baru').

**`StoreBookingCostRequest`**
- Aturan: `type` (required, string, in:driver,bbm,tol,parkir,lainnya), `label` (required, string), `amount` (required, integer, min:0).

> **PENTING**: Semua `amount` atau `harga` wajib divalidasi sebagai **integer** (rupiah penuh, tanpa desimal).

### 3. Business Logic (Service)
Buat/Edit file `app/Services/HandleBookingService.php` (atau `BookingService.php` jika ingin digabung). Tambahkan 3 method utama:

1. **`assignDetail(Booking $booking, array $data)`**
   - Insert ke tabel `booking_details`.
   - **Rent-to-Rent Logic**: Ambil data unit (`Unit::find($data['unit_id'])`). Jika pemilik unit bukan tenant/owner utama (misal: cek kolom penanda bahwa ini milik rental lain), buat *TODO comment* untuk catat ke tabel `rent_to_rent_debts` sesuai instruksi PRD (karena migrasinya mungkin belum ada di fase ini).

2. **`addCost(BookingDetail $detail, array $data)`**
   - Insert data ke tabel `booking_costs`.

3. **`handleBooking(Booking $booking)`**
   - Ubah `status` pada model `Booking` menjadi `Waiting List`.
   - Simpan perubahan.

### 4. Controller & Response
- **`BookingDetailController@store`**: Validasi request, panggil `assignDetail` di service, return resource `BookingDetailResource` (atau gabung ke `BookingResource`).
- **`BookingCostController@store`**: Validasi request, panggil `addCost` di service, return resource.
- **`BookingController@handle`**: Cek policy (otorisasi), pastikan status sebelumnya adalah `Follow Up` atau `Confirm`, panggil `handleBooking` di service, return resource booking terbaru.

---

## BAGIAN 2: FRONTEND (Task 2.7)

### 1. API Integration (`src/api/booking.js`)
Tambahkan fungsi-fungsi berikut untuk hit endpoint backend:
```javascript
// src/api/booking.js
export const addBookingDetail = (bookingId, data) => apiClient.post(`/v1/bookings/${bookingId}/details`, data);
export const addBookingCost = (detailId, data) => apiClient.post(`/v1/booking-details/${detailId}/costs`, data);
export const handleBookingStatus = (bookingId) => apiClient.patch(`/v1/bookings/${bookingId}/handle`);
```

### 2. Halaman Detail Booking (`BookingDetailView.vue` atau komponen setara)
Modifikasi/buat UI Detail Booking untuk memiliki layout berikut:

#### A. Header Ringkasan
- Tampilkan nomor booking, nama customer, tanggal, status saat ini.
- Tombol **"Pindah ke Waiting List (Handle)"**. Tombol ini hanya muncul/aktif jika status saat ini `Follow Up` atau `Confirm` dan minimal sudah ada 1 unit yang di-assign.

#### B. Section "Kendaraan & Driver" (Handle Booking)
- Buat tombol "Tambah Kendaraan".
- Jika diklik, buka **Dialog Form** (PrimeVue Dialog):
  - Autocomplete/Dropdown **Unit** (Ambil dari API master unit).
  - Autocomplete/Dropdown **Driver** (Opsional, ambil dari API master driver).
  - DatePicker untuk **Tanggal Sewa** dan **Tanggal Kembali** (Datetime).
  - Input Number untuk **Harga Mobil** dan **Diskon**.
  - Submit memanggil `addBookingDetail()`.

#### C. Section "Komponen Biaya"
- Tabel yang menampilkan daftar biaya yang sudah diinput untuk booking tersebut.
- Tombol "Tambah Biaya".
- Jika diklik, buka **Dialog Form**:
  - Dropdown **Kendaraan** (Pilih dari unit yang sudah di-assign ke booking ini / Booking Detail ID).
  - Dropdown **Tipe Biaya** (Driver, BBM, Tol, Parkir, dll).
  - Input Text **Keterangan/Label**.
  - Input Number **Amount (Rp)**.
  - Submit memanggil `addBookingCost()`.

### 3. State & Error Handling
- Gunakan Pinia Store (`useBookingStore`) atau Vue Composables lokal untuk menyimpan state loading.
- Tampilkan PrimeVue `Toast` saat berhasil menambahkan detail, menambahkan biaya, atau berhasil memindahkan status.
- Tangkap error dari Axios dan tampilkan pesan error yang sesuai (terutama error validasi tanggal bentrok atau unit tidak tersedia).

---

## CHECKLIST UNTUK AI AGENT
- [ ] Backend: Buat Form Requests untuk Detail & Biaya.
- [ ] Backend: Update Service dengan logic simpan detail & ubah status.
- [ ] Backend: Tambah TODO untuk Rent-to-Rent jika migrasinya belum ada.
- [ ] Backend: Buat Controller endpoints + integrasi ke file `api.php`.
- [ ] Frontend: Tambahkan 3 API functions di `src/api/booking.js`.
- [ ] Frontend: Buat Dialog Form "Tambah Kendaraan" dengan InputNumber (tanpa desimal).
- [ ] Frontend: Buat Dialog Form "Tambah Biaya" dengan InputNumber (tanpa desimal).
- [ ] Frontend: Terapkan tombol "Handle" untuk pindah ke status `Waiting List`.
