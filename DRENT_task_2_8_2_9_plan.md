# Rencana Implementasi DRENT
## Task 2.8 — Booking: Modifikasi Transaksi Aktif (Backend)
## Task 2.9 — Booking: Modifikasi Transaksi (Frontend)

Dokumen ini berisi instruksi spesifik dan terstruktur untuk diimplementasikan oleh AI Agent (model coding). Patuhi semua **Global Rules DRENT** selama pengerjaan.

---

## BAGIAN 1: BACKEND (Task 2.8)

### 1. Endpoint & Routing
Buka file `routes/api.php` dan pastikan endpoint berikut ditambahkan di bawah `Route::prefix('v1')` dan dilindungi oleh middleware auth sanctum & branch.
Semua endpoint ini menggunakan method POST untuk mendeskripsikan *action* spesifik.

```php
Route::post('/bookings/{booking}/extend', [BookingModificationController::class, 'extend']);
Route::post('/bookings/{booking}/rolling', [BookingModificationController::class, 'rolling']);
Route::post('/bookings/{booking}/stop-early', [BookingModificationController::class, 'stopEarly']);
Route::post('/bookings/{booking}/costs', [BookingCostController::class, 'storeAdditionalCost']); 
```

### 2. Form Request (Validasi)
Buat class request berikut (Gunakan `php artisan make:request`):

**`ExtendBookingRequest`**
- Aturan: `unit_id` (required, exists:units,id), `driver_id` (nullable, exists:drivers,id), `tgl_sewa` (required, date), `tgl_kembali` (required, date, after_or_equal:tgl_sewa), `harga_mobil` (required, integer, min:0).

**`RollingBookingRequest`**
- Aturan: `booking_detail_id` (required, exists:booking_details,id), `unit_id_baru` (required, exists:units,id), `tgl_rolling` (required, date).

**`StopEarlyBookingRequest`**
- Aturan: `booking_detail_id` (required, exists:booking_details,id), `tgl_stop` (required, date), `refund_amount` (required, integer, min:0).

> **PENTING**: Sesuai Global Rules, validasi WAJIB ada di FormRequest, bukan di Controller. Semua `amount` atau harga wajib divalidasi sebagai **integer** tanpa desimal.

### 3. Business Logic (Service)
Buat file `app/Services/BookingModificationService.php`. Pastikan service *melempar exception* (misal: `UnprocessableEntityHttpException`) bila status `Booking` saat ini bukan `Rental Unit`.

1. **`extend(Booking $booking, array $data)`**
   - Tambahkan row baru di tabel `booking_details` dengan data yang di-passing dan `detail_type` = 'extend'.

2. **`rolling(Booking $booking, array $data)`**
   - Cari `booking_detail` lama (berdasarkan `$data['booking_detail_id']`). Update `tgl_kembali`-nya menjadi `$data['tgl_rolling']` dan ubah statusnya menjadi ditutup/selesai awal.
   - Tambahkan row baru di `booking_details` menggunakan unit baru (`unit_id_baru`), dengan rentang waktu dari `tgl_rolling` sampai dengan sisa waktu yang disepakati, set `detail_type` = 'rolling'.

3. **`stopEarly(Booking $booking, array $data)`**
   - Cari `booking_detail` lama, potong `tgl_kembali` menjadi `tgl_stop`.
   - Hitung refund/penyesuaian harga jika diperlukan. Anda dapat mencatat pengembalian nominal di database `booking_costs` sebagai diskon atau catatan khusus dengan nominal negatif jika memungkinkan, atau ikuti struktur DB yang ada.

### 4. Controller & Response
- Buat `BookingModificationController`.
- **Otorisasi**: Pastikan menggunakan Policy (misal `$this->authorize('modify', $booking)`).
- Panggil service masing-masing.
- Return respons dengan mengembalikan `BookingResource` yang sudah ter-update (beserta relasinya menggunakan *eager loading*).

---

## BAGIAN 2: FRONTEND (Task 2.9)

### 1. API Integration (`src/api/booking.js`)
Tambahkan fungsi-fungsi berikut untuk hit endpoint modifikasi backend:
```javascript
// src/api/booking.js
export const extendBooking = (bookingId, data) => apiClient.post(`/v1/bookings/${bookingId}/extend`, data);
export const rollingBooking = (bookingId, data) => apiClient.post(`/v1/bookings/${bookingId}/rolling`, data);
export const stopEarlyBooking = (bookingId, data) => apiClient.post(`/v1/bookings/${bookingId}/stop-early`, data);
export const addAdditionalCost = (bookingId, data) => apiClient.post(`/v1/bookings/${bookingId}/costs`, data);
```

### 2. Halaman Detail Booking (`BookingDetailView.vue`)
Di halaman ini, tampilkan keseluruhan log `booking_details` (menunjukkan kendaraan asli, perpanjangan, atau hasil rolling).

### 3. Panel Modifikasi (Dikunci jika bukan Rental Unit)
Sediakan section "Modifikasi Booking" dengan 3 tombol utama.
**Penting**: Kunci (disable) tombol-tombol ini jika `status` bukan `Rental Unit`.

#### A. Tombol Extend (Perpanjang Sewa)
- Jika diklik, buka PrimeVue Dialog.
- Form input: Autocomplete/Dropdown Unit, Dropdown Driver (Opsional), Tgl Sewa (sambungan) & Tgl Kembali, Harga (wajib integer).
- Submit memanggil `extendBooking()`.

#### B. Tombol Rolling (Ganti Kendaraan)
- Buka PrimeVue Dialog.
- Form input: Dropdown Unit Lama (mengambil list dari `booking_details` booking ini), Dropdown Unit Baru (dari list master unit), Tgl Rolling.
- Submit memanggil `rollingBooking()`.

#### C. Tombol Stop Early (Berhenti Mendadak)
- Buka PrimeVue Dialog.
- Form input: Dropdown Unit Lama, Tgl Berhenti, Input Refund Amount (jika ada, integer).
- Submit memanggil `stopEarlyBooking()`.

### 4. State & Error Handling
- Setelah submit sukses: tutup Dialog, refresh detail booking untuk memuat data terbaru, dan tampilkan PrimeVue Toast Notifikasi Sukses.
- Gunakan try-catch dan tampilkan Toast Error jika backend menolak (misalnya karena status tidak valid atau validasi gagal).

---

## CHECKLIST UNTUK AI AGENT
- [ ] Backend: Buat Controller `BookingModificationController`.
- [ ] Backend: Buat 3 Form Requests khusus (`ExtendBookingRequest`, `RollingBookingRequest`, `StopEarlyBookingRequest`).
- [ ] Backend: Buat `BookingModificationService` dan implementasi logika extend, rolling, stop early. Validasi status harus `Rental Unit`.
- [ ] Backend: Daftarkan routes baru di `api.php`.
- [ ] Frontend: Tambahkan fungsi API di `src/api/booking.js`.
- [ ] Frontend: UI Modifikasi di `BookingDetailView.vue` (Disable/Hide tab modifikasi jika bukan `Rental Unit`).
- [ ] Frontend: Buat Dialog Form & integrasi `extendBooking` (Harga wajig Integer).
- [ ] Frontend: Buat Dialog Form & integrasi `rollingBooking`.
- [ ] Frontend: Buat Dialog Form & integrasi `stopEarlyBooking`.
- [ ] Frontend: Pastikan UI merefresh data booking setelah operasi modifikasi sukses.
