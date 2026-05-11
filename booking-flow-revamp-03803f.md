# Revamp Alur Booking DRENT

Refaktor besar alur booking: dari input awal, handle, checkout, modifikasi (extend/rolling/batal), hingga selesai — termasuk tabel master baru, pembayaran, dan perhitungan harga All In.

---

## Konteks Perubahan

Alur booking lama (Fase 2 saat ini) sudah berjalan basic: create → handle → modifikasi. Revamp ini mengubah:

- **Booking awal** ditambah field `lama_sewa` + `paket_sewa`
- **DP** dipindah dari kolom di `bookings` ke tabel `booking_payments` (lebih fleksibel, bisa realokasi)
- **Handle booking** pakai master cost types (dinamis) + pilihan All In / Non All In dari tabel `pricing_packages`
- **Checkout** (waiting_list → rental_unit) dengan popup konfirmasi cek fisik + status unit jadi "Out"
- **Selesai** (rental_unit → selesai) dengan popup cek fisik + unit kembali "Aktif"
- **Extend/Rolling/Batal** di-revamp jadi form lengkap sesuai spesifikasi baru
- **Payment management** per booking + realokasi antar booking
- **Refund** saat pembatalan
- **Late return warning** di list booking

---

## Fase A — Migrasi Database & Seeder

### A1. Tabel `payment_accounts` + Seeder
- Fields: `id, tenant_id, branch_id, nama_bank, nomor_rekening, atas_nama, is_active, timestamps, soft_deletes`
- Seeder: 2-3 dummy akun per branch (BCA, Mandiri, Cash)

### A2. Tabel `cost_types` + Seeder
- Fields: `id, tenant_id, nama, kode (slug), require_description (bool, untuk tipe "lainnya"), is_active, sort_order, timestamps`
- Seeder: Driver, BBM, Tol, Uang Makan, Penginapan, Parkir, Antar Jemput, Lainnya

### A3. Tabel `pricing_packages` + Seeder
- Fields: `id, tenant_id, branch_id, nama_paket, harga, keterangan, is_active, timestamps, soft_deletes`
- Seeder: 2-3 paket contoh (All In Avanza Bandung, All In Innova Jakarta)

### A4. Tabel `booking_payments`
- Fields: `id, booking_id (FK), payment_account_id (FK), amount, payment_type (enum: dp/cicilan/pelunasan), catatan, paid_at, reallocated_from_id (nullable FK self-ref), created_by (FK users), timestamps`

### A5. Tabel `refunds`
- Fields: `id, booking_id (FK), payment_account_id (FK), amount, keterangan, refunded_at, created_by (FK users), timestamps`

### A6. Alter `bookings`
- Tambah: `lama_sewa (int, nullable)`, `paket_sewa (enum: harian/mingguan/bulanan, nullable)`
- Kolom `dp` dan `rekening_dp_id` tetap ada (backward compat) tapi deprecated — pembayaran aktual di `booking_payments`

### A7. Alter `booking_details`
- Tambah: `lama_sewa (int)`, `paket_sewa (enum: harian/mingguan/bulanan)`
- Tambah: `pricing_mode (enum: all_in/non_all_in, default non_all_in)`
- Tambah: `pricing_package_id (nullable FK)`
- Tambah: `harga_all_in (nullable, bigint unsigned)` — harga All In override
- Fix migration `091946` yang masih kosong

### A8. Alter `booking_costs`
- Tambah: `cost_type_id (nullable FK ke cost_types)`
- Tambah: `keterangan (nullable text)` — wajib untuk tipe "lainnya"

### A9. Alter `units` — Tambah status "Out"
- Ubah enum status: `['Aktif', 'Tidak Aktif', 'Dalam Servis', 'Out']`

---

## Fase B — Backend: Master Data CRUD Baru

### B1. Payment Account — Model + CRUD API
- Model `PaymentAccount` + Resource + FormRequest
- `GET/POST /api/v1/payment-accounts`, `PUT/DELETE /api/v1/payment-accounts/{id}`
- Policy: admin/superadmin only

### B2. Cost Type — Model + CRUD API
- Model `CostType` + Resource + FormRequest
- `GET/POST /api/v1/cost-types`, `PUT/DELETE /api/v1/cost-types/{id}`
- Policy: admin/superadmin only

### B3. Pricing Package — Model + CRUD API
- Model `PricingPackage` + Resource + FormRequest
- `GET/POST /api/v1/pricing-packages`, `PUT/DELETE /api/v1/pricing-packages/{id}`
- Policy: admin/superadmin only

---

## Fase C — Backend: Revamp Booking Flow

### C1. BookingPayment — Model + API
- Model `BookingPayment` + Resource
- Endpoints:
  - `GET /api/v1/bookings/{id}/payments` — list pembayaran per booking
  - `POST /api/v1/bookings/{id}/payments` — tambah pembayaran (DP/cicilan/pelunasan)
  - `POST /api/v1/booking-payments/{id}/reallocate` — realokasi ke booking lain
- Total bayar, sisa tagihan dihitung di BookingResource (accessor)

### C2. Refund — Model + API
- Model `Refund` + Resource
- `POST /api/v1/bookings/{id}/refund` — buat refund saat batal
- `GET /api/v1/bookings/{id}/refunds` — list refund per booking

### C3. Update `BookingService::createBooking()`
- Terima field `lama_sewa`, `paket_sewa`
- Simpan lama_sewa + paket di `bookings` dan di `booking_details` (initial)
- Jika ada DP → buat record `BookingPayment` (type: dp)
- Status tetap follow_up/confirm berdasarkan ada/tidak payment

### C4. Update Handle Booking Flow
- `POST /api/v1/bookings/{id}/handle` menerima data lengkap:
  - `unit_id`, `driver_id`, `lama_sewa`, `paket_sewa`
  - `harga_mobil`, `diskon_mobil`
  - `pricing_mode` (all_in / non_all_in)
  - `pricing_package_id` + `harga_all_in` (jika all_in)
  - Array `costs[]` (cost_type_id, label, amount, keterangan)
  - `alamat_penjemputan`, `tujuan`
- Perhitungan: `(harga_mobil - diskon_mobil) × lama_sewa` + sum(biaya lainnya)
- Jika All In: tagihan konsumen = harga_all_in, total internal hanya catatan
- Jika Non All In: tagihan konsumen = total semua biaya
- Update booking_detail (initial → diupdate dengan data handle)
- Status → `waiting_list`

### C5. Checkout Logic (waiting_list → rental_unit)
- `POST /api/v1/bookings/{id}/checkout`
- Validasi status = waiting_list
- Accept `skip_inspection (bool)` — frontend kirim berdasarkan popup
- Update booking status → `rental_unit`
- Update unit status → `Out`
- Set booking_detail status → `aktif`

### C6. Complete Logic (rental_unit → selesai)
- `POST /api/v1/bookings/{id}/complete`
- Accept `skip_inspection (bool)`
- Update booking status → `selesai`
- Update unit status → `Aktif`
- Set booking_detail status → `selesai`
- (Piutang: dicatat di fase berikutnya)

### C7. Revamp BookingModificationService
- **Extend**: form lengkap — unit, driver, tgl_sewa (H+1 dari kembali sebelumnya), tgl_kembali, lama_sewa, paket, biaya-biaya, pricing_mode (all_in/non_all_in). Buat booking_detail baru type=extend
- **Rolling**: adjust detail lama (ubah lama_sewa & harga jika perlu) + buat detail baru type=rolling dengan form lengkap
- **Batal**: buat record Refund, ubah status → batal, unit → Aktif

### C8. Update BookingResource
- Tambah computed fields:
  - `total_payments` (sum booking_payments)
  - `total_tagihan` (berdasarkan pricing_mode: all_in → harga_all_in, non_all_in → sum costs)
  - `sisa_tagihan` (total_tagihan - total_payments)
  - `is_overdue` (boolean: tgl_kembali < now && status == rental_unit)
- Include relations: payments, refunds, costs dengan cost_type

---

## Fase D — Frontend: Halaman Master Data Baru

### D1. Payment Account Management
- Halaman CRUD di admin area
- DataTable + Dialog form (nama bank, nomor rekening, atas nama)

### D2. Cost Type Management
- Halaman CRUD di admin area
- DataTable + Dialog form (nama, kode, require_description, sort_order)

### D3. Pricing Package Management
- Halaman CRUD di admin area
- DataTable + Dialog form (nama paket, harga, keterangan include/exclude)

---

## Fase E — Frontend: Revamp Booking Flow

### E1. BookingCreateView — Tambah Field Baru
- Tambah input `lama_sewa` (number) + `paket_sewa` (dropdown: Harian/Mingguan/Bulanan)
- Ganti hardcoded rekenings dengan data dari `payment-accounts` API
- Perbaiki flow DP: simpan via booking_payments

### E2. BookingDetailView — Section Pembayaran
- Tampilkan list pembayaran (DataTable)
- Tombol "Tambah Pembayaran" → dialog (nominal, akun, tipe)
- Tombol "Realokasi" per payment → dialog pilih booking tujuan
- Summary: total dibayar, sisa tagihan

### E3. BookingDetailView — Handle Booking Revamp
- Form handle lengkap:
  - Unit (dropdown nopol), Driver (dropdown)
  - Lama sewa + Paket
  - Harga mobil, diskon
  - Toggle All In / Non All In
  - Jika All In → pilih pricing package atau input manual harga_all_in
  - Biaya operasional: dynamic rows dari cost_types master
  - Alamat penjemputan, Tujuan
- Tampilkan kalkulasi real-time:
  - Harga sewa = (harga_mobil - diskon) × lama_sewa
  - Total biaya operasional
  - Grand total (internal) vs Tagihan konsumen (all_in/non_all_in)

### E4. BookingDetailView — Checkout & Complete
- Tombol "Checkout" (visible saat waiting_list):
  - Dialog konfirmasi: "Apakah kendaraan sudah di Cek Fisik?"
  - Option: "Ya, lanjutkan" / "Checkout tanpa Cek Fisik" / "Batal"
- Tombol "Selesai" (visible saat rental_unit):
  - Dialog serupa untuk cek fisik kepulangan
- Setelah aksi → refresh data

### E5. BookingDetailView — Extend/Rolling/Batal Revamp
- **Extend dialog**: form lengkap (unit, driver, tgl_sewa fixed H+1, tgl_kembali, lama_sewa, paket, biaya-biaya, pricing_mode)
- **Rolling dialog**: step 1 adjust detail lama, step 2 form lengkap detail baru
- **Batal dialog**: input nominal refund, keterangan, pilih akun pembayaran refund

### E6. BookingListView — Late Return Warning
- Kolom/badge "Terlambat" jika `tgl_kembali < now` dan status = `rental_unit`
- Highlight row atau ikon warning

---

## Urutan Pengerjaan (Task Sequencing)

| # | Task | Depends On | status |
|---|------|------------|--------|
| 1 | A1-A9: Semua migrasi + seeder | — | done |
| 2 | B1-B3: Master data CRUD backend | A1-A3 | done |
| 3 | C1-C2: BookingPayment + Refund backend | A4-A5 | done |
| 4 | C3: Update createBooking | A6-A7, C1 | done |
| 5 | C4: Update handle booking | A7-A8, B2-B3 | done |
| 6 | C5-C6: Checkout + Complete | A9 | done |
| 7 | C7: Revamp modifications | C1-C6 | done |
| 8 | C8: Update BookingResource | C1-C7 | done |
| 9 | D1-D3: Frontend master data pages | B1-B3 | done |
| 10 | E1: Revamp BookingCreateView | C3, D1 | done |
| 11 | E2: Payment section | C1 | done |
| 12 | E3: Handle booking revamp | C4, D2-D3 | done |
| 13 | E4: Checkout/Complete UI | C5-C6 | done |
| 14 | E5: Extend/Rolling/Batal revamp | C7 | done |
| 15 | E6: Late return warning | C8 | done |

---

## Catatan

- Kolom `dp` dan `rekening_dp_id` di `bookings` tetap ada (backward compat) tapi tidak dipakai untuk logic baru
- Tabel `booking_payments` menggantikan semua pencatatan pembayaran
- Piutang (receivables) belum di-implement di fase ini — hanya `sisa_tagihan` yang dihitung
- Cek fisik hanya popup konfirmasi — modul penuh belum dikembangkan
- Semua migration baru follow konvensi existing: `foreignId()->constrained()`, `unsignedBigInteger` untuk amount, `soft_deletes` di tabel utama
