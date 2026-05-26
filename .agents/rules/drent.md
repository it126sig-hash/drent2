---
trigger: manual
---

# DRENT — Global Rules

Ikuti semua aturan ini setiap saat saat bekerja di project DRENT.

---

## 1. Stack Tidak Boleh Diganti

- Backend **harus** Laravel. Jangan suggest Express, FastAPI, atau framework lain.
- Frontend **harus** Vue 3 + PrimeVue. Jangan suggest React, Nuxt, atau library UI lain.
- Jika ada pertimbangan teknis yang sangat kuat untuk deviasi, **tanya dulu** sebelum implement.

---

## 2. Semua Angka Uang dalam IDR Integer

- Simpan dan kirim amount sebagai **integer (rupiah penuh)**.
- Tidak ada desimal, tidak ada multi-currency, tidak ada konversi kurs.
- Contoh benar: `"amount": 250000`
- Contoh salah: `"amount": 250000.00`, `"amount": "Rp 250.000"`

---

## 3. Validasi Wajib di FormRequest

- **Jangan** taruh validasi `$request->validate()` di dalam method Controller.
- Selalu buat class `FormRequest` terpisah.
- Nama: `Store{Model}Request`, `Update{Model}Request`.

---

## 4. API Resource Wajib untuk Semua Respons

- **Jangan** return Eloquent Model langsung (`return $booking;`).
- Selalu bungkus dengan `JsonResource` atau `ResourceCollection`.
- Field yang tidak perlu jangan diekspos (misal: `password`, `remember_token`).

---

## 5. Business Logic di Service, Bukan Controller

- Controller hanya: validasi (via FormRequest) → otorisasi (via Policy) → panggil Service → return Resource.
- Kalkulasi harga, perubahan status, trigger notifikasi → masuk Service class.

---

## 6. Otorisasi via Policy, Bukan Hardcode Role di Controller

```php
// ❌ Salah
if (auth()->user()->role === 'finance') { ... }

// ✅ Benar
$this->authorize('updateBalance', $driver);
```

---

## 7. Soft Delete sebagai Default, dengan Catatan Arsip

- Semua model pakai `SoftDeletes` trait sampai strategi arsip dikonfirmasi.
- Tambahkan komentar ini di model yang terdampak:
  ```php
  // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
  // Keputusan ini belum final per [tanggal sesi terakhir].
  ```

---

## 8. Branch Isolation

- Semua query yang mengembalikan data operasional **harus** di-scope per `branch_id`.
- Gunakan Global Scope atau pastikan `where('branch_id', ...)` selalu ada.
- Superadmin boleh query lintas branch dengan explicit `branch_id=all`.

---

## 9. Driver Non-Tetap Tidak Punya Akun

- Jangan buat fitur login/auth untuk driver non-tetap.
- Bon driver non-tetap diinput oleh Finance via panel Finance.
- Tabel `drivers` tetap ada untuk data referensi, tapi kolom `user_id` nullable untuk non-tetap.

---

## 10. Konvensi Penamaan

| Konteks | Konvensi |
|---------|----------|
| Laravel Controller | `BookingController`, bukan `BookingsController` |
| Laravel Route | `/api/v1/bookings` (plural, kebab-case) |
| API URL (Frontend) | `/v1/users` (selalu pakai prefix `/v1`) |
| Vue component | `BookingForm.vue`, `UnitCard.vue` (PascalCase) |
| Pinia store | `useBookingStore`, `useFinanceStore` |
| Composable | `useBooking`, `useUnit` |
| API function file | `src/api/booking.js`, `src/api/finance.js` |

---

## 11. Error Handling Standar

**Laravel:**
```php
// Handler di app/Exceptions/Handler.php sudah setup
// Gunakan exception spesifik, jangan return response() langsung di Service
throw new BookingConflictException('Unit sudah dibooking pada tanggal tersebut.');
```

**Vue:**
```js
// Selalu tangkap error di composable, bukan di komponen
const { data, error, loading } = await useBooking().fetchDetail(id)
// Tampilkan Toast error jika gagal
```

---

## 12. Hal yang Perlu Dikonfirmasi Sebelum Diimplementasi

Jangan implement hal berikut sampai ada konfirmasi eksplisit:

- [ ] Strategi arsip data (soft-delete / tabel terpisah / `is_archived` flag)
- [ ] Apakah ada fitur approval untuk perubahan invoice?
- [ ] Apakah driver non-tetap perlu tracking jam kerja?

Jika task menyentuh area ini, **berhenti dan tanya** sebelum lanjut.