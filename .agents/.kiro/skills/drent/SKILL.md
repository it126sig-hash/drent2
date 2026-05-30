---
name: drent
description: >
  Sistem manajemen rental mobil internal (DRENT). Gunakan skill ini untuk semua
  task yang berkaitan dengan backend Laravel (API), frontend Vue + PrimeVue,
  skema database, atau logika bisnis DRENT. Aktifkan juga saat menyebut modul:
  booking, transaksi, keuangan, cek fisik, survey member, pemeliharaan unit,
  notifikasi, dashboard, atau laporan.
---

# DRENT — Rental Mobil Internal

## Stack Teknologi

| Layer | Teknologi |
|-------|-----------|
| API / Backend | Laravel (PHP) |
| Frontend | Vue 3 + PrimeVue |
| Database | MySQL / PostgreSQL |
| Currency | IDR only (tidak ada multi-currency) |
| Auth | Laravel Sanctum (token-based) |

---

## Arsitektur Sistem

```
Frontend (Vue 3 + PrimeVue)
    └── REST API calls → Laravel API (JSON)
            ├── Sanctum Auth
            ├── Policy / Gate (RBAC per role)
            └── MySQL / PostgreSQL
```

### Konvensi API

- Base URL: `/api/v1/`
- Response envelope:
  ```json
  { "data": {}, "message": "ok", "errors": null }
  ```
- Semua amount dalam **IDR (integer, tanpa desimal)**
- Tanggal: **ISO 8601** (`YYYY-MM-DD`, `YYYY-MM-DDTHH:mm:ssZ`)
- Pagination: `?page=1&per_page=15` → respons mengandung `meta.total`, `meta.last_page`

---

## Roles & Permission

| Role | Akses Utama |
|------|-------------|
| `superadmin` | Semua fitur, semua branch |
| `admin_branch` | Semua fitur, satu branch saja |
| `finance` | Keuangan, input bon driver non-tetap, invoice |
| `driver_tetap` | Input bon via mobile, lihat jadwal sendiri |
| `cs` | Booking, cek fisik, survey member |
| `teknisi` | Pemeliharaan unit |

### Aturan Penting

- Driver tetap punya akun sendiri; input bon dilakukan sendiri via mobile.
- Driver non-tetap tidak punya akun; bon diinput oleh Finance.
- Finance bisa langsung input/edit saldo driver tanpa approval workflow.
- Template invoice bisa berbeda per branch (logo, kontak, alamat), tetapi struktur data sama.

---

## Modul-Modul Sistem

### 1. Booking & Transaksi

**Endpoint utama:**
- `GET /api/v1/bookings` — list booking (filter: status, branch, tanggal)
- `POST /api/v1/bookings` — buat booking baru
- `PATCH /api/v1/bookings/{id}/status` — ubah status
- `GET /api/v1/bookings/{id}` — detail booking

**Status booking:** `draft → confirmed → ongoing → completed → cancelled`

**Field wajib saat create:**
```json
{
  "member_id": 1,
  "unit_id": 1,
  "driver_id": null,
  "branch_id": 1,
  "start_date": "2025-06-01",
  "end_date": "2025-06-03",
  "rental_type": "with_driver | without_driver",
  "notes": ""
}
```

---

### 2. Keuangan

- Semua transaksi dalam IDR (integer).
- Finance input saldo driver langsung via `PATCH /api/v1/drivers/{id}/balance`.
- Invoice dihasilkan per booking; template berbeda per branch (logo/kontak/alamat saja).
- `GET /api/v1/invoices/{id}/pdf` → generate PDF invoice.

---

### 3. Cek Fisik

- Dilakukan CS saat unit keluar (`checkout`) dan masuk kembali (`checkin`).
- Menyimpan foto kondisi unit, checklist part, dan catatan.
- Endpoint: `POST /api/v1/physical-checks` dengan `type: checkout | checkin`.

---

### 4. Survey Member

- Dikirim otomatis setelah booking `completed`.
- CS bisa trigger manual: `POST /api/v1/bookings/{id}/survey`.
- Skala rating 1–5; komentar opsional.

---

### 5. Pemeliharaan Unit

- Teknisi membuat work order: `POST /api/v1/maintenance-orders`.
- Status: `open → in_progress → done`.
- Unit yang sedang maintenance tidak bisa di-booking.

---

### 6. Notifikasi

- Internal (in-app) + WhatsApp (via gateway eksternal).
- Event yang memicu notifikasi: booking confirmed, booking cancelled, invoice terbit, survey dikirim, maintenance done.
- Konfigurasi per branch: `settings.notifications`.

---

### 7. Dashboard & Laporan

- Dashboard menampilkan: unit tersedia, booking aktif, revenue hari ini, outstanding invoice.
- Laporan dapat diexport CSV/Excel.
- Filter standar: `branch_id`, `date_from`, `date_to`.

---

## Retensi Data

- Data diarsipkan setelah **1 tahun**.
- ⚠️ **PENDING**: Mekanisme arsip belum final (soft-delete, tabel terpisah, atau flag `is_archived`). **Jangan implement skema DB untuk arsip sebelum keputusan ini dikonfirmasi.**
- Saat ini: implementasikan `soft-delete` Laravel (`SoftDeletes` trait) sebagai default aman, tapi tandai dengan komentar `// TODO: konfirmasi strategi arsip`.

---

## Konvensi Kode

### Laravel (Backend)

```
app/
  Http/
    Controllers/Api/V1/   ← semua controller API
    Requests/             ← FormRequest untuk validasi
    Resources/            ← API Resource transformer
  Models/
  Policies/               ← satu Policy per model
  Services/               ← business logic (bukan di Controller)
routes/
  api.php                 ← semua route prefix /api/v1
```

- Gunakan `FormRequest` untuk semua validasi; jangan validasi di dalam Controller.
- Gunakan `API Resource` (`JsonResource`) untuk semua respons; jangan return Model langsung.
- Logic bisnis masuk `Service` class, bukan Controller.
- Gunakan `Policy` untuk otorisasi; `$this->authorize()` di Controller.
- Semua migration pakai `foreignId()->constrained()->cascadeOnDelete()` kecuali ada alasan eksplisit.

### Vue 3 + PrimeVue (Frontend)

```
src/
  api/          ← axios instance + endpoint functions
  components/   ← komponen reusable
  composables/  ← useBooking, useFinance, dst.
  pages/        ← satu folder per modul
  stores/       ← Pinia stores
  router/
```

- Gunakan `<script setup>` (Composition API).
- State global via **Pinia**.
- HTTP calls via axios di `src/api/`; jangan fetch langsung di komponen.
- Gunakan komponen PrimeVue (`DataTable`, `Dialog`, `Toast`, `Dropdown`, dst.) — jangan buat custom komponen UI jika PrimeVue sudah punya.
- Toast notification untuk semua feedback aksi user (sukses/error).
- Loading state wajib ada pada setiap aksi async.

---

## Decision Trees

### Saya perlu membuat endpoint baru

1. Buat `FormRequest` untuk validasi.
2. Buat `Policy` method jika ada otorisasi.
3. Buat `Service` method untuk logic.
4. Buat `Controller` method (tipis: delegate ke Service).
5. Tambah route di `routes/api.php` dengan middleware `auth:sanctum`.
6. Buat `API Resource` untuk respons.

### Saya perlu membuat halaman baru di frontend

1. Buat file di `src/pages/{modul}/`.
2. Tambah route di `src/router/`.
3. Buat composable `use{Modul}` di `src/composables/` jika belum ada.
4. Tambah endpoint function di `src/api/{modul}.js`.
5. Gunakan komponen PrimeVue; wrap table data dalam `DataTable`.

### Saya ragu soal otorisasi suatu aksi

- Cek tabel Role di atas.
- Finance dapat akses keuangan + bon driver; driver_tetap hanya data sendiri.
- Jika tidak jelas, terapkan prinsip **least privilege** dan tambahkan komentar `// TODO: konfirmasi dengan PO`.
