# Revamp Alur Booking DRENT

Refaktor besar alur booking: dari input awal, penentuan unit, handle status, checkout, modifikasi, pembayaran, hingga selesai. Dokumen ini sudah disesuaikan dengan perubahan implementasi terakhir pada `BookingCreateView.vue`, `BookingDetailView.vue`, API booking, dan booking detail.

---

## Konteks Perubahan

Alur booking lama sudah berjalan basic: create -> handle -> checkout/complete -> modifikasi. Revamp ini mengubah:

- **Booking awal** menyimpan `lama_sewa`, `paket_sewa`, `harga_dealing`, DP, rekening DP, dan pembuat booking (`created_by`).
- **Unit booking awal** bisa berupa unit fix atau placeholder jika unit belum ditentukan.
- **BookingCreateView** menampilkan status pelanggan pada hasil pencarian dan info unit ready: no polisi, pemilik, status.
- **Detail unit** dipindahkan ke modal Tambah Unit / Edit Unit di halaman detail booking.
- **Handle booking** hanya konfirmasi perubahan status ke `waiting_list`; handle ditolak jika unit masih placeholder.
- **Checkout** (`waiting_list` -> `rental_unit`) dengan popup konfirmasi cek fisik dan status unit menjadi `Out`.
- **Selesai** (`rental_unit` -> `selesai`) dengan popup cek fisik dan status unit kembali `Aktif`.
- **Extend/Rolling/Batal** tetap memakai detail booking sebagai sumber data biaya.
- **Payment management** per booking tetap diarahkan ke `booking_payments`.
- **Refund** saat pembatalan.
- **Late return warning** memakai computed `is_overdue`.

---

## Fase A - Migrasi Database & Seeder

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
- Tambah: `lama_sewa (int, nullable)`, `paket_sewa (enum: harian/mingguan/bulanan, nullable)`, `created_by (nullable FK users)`
- Kolom `dp` dan `rekening_dp_id` tetap ada untuk backward compatibility.
- Pembayaran aktual diarahkan ke `booking_payments`, tetapi `dp` lama masih dipakai sebagai fallback ringkasan jika detail pembayaran belum lengkap.

### A7. Alter `booking_details`
- Tambah: `lama_sewa (int)`, `paket_sewa (enum: harian/mingguan/bulanan)`
- Tambah: `pricing_mode (enum: all_in/non_all_in, default non_all_in)`
- Tambah: `pricing_package_id (nullable FK)`
- Tambah: `harga_all_in (nullable, bigint unsigned)`
- Mendukung `unit_placeholder` untuk booking yang belum menentukan unit fix.

### A8. Alter `booking_costs`
- Tambah: `cost_type_id (nullable FK ke cost_types)`
- Tambah: `keterangan (nullable text)`; wajib untuk tipe "lainnya"

### A9. Alter `units` - Tambah status "Out"
- Ubah enum status: `['Aktif', 'Tidak Aktif', 'Dalam Servis', 'Out']`

---

## Fase B - Backend: Master Data CRUD Baru

### B1. Payment Account - Model + CRUD API
- Model `PaymentAccount` + Resource + FormRequest
- `GET/POST /api/v1/payment-accounts`, `PUT/DELETE /api/v1/payment-accounts/{id}`
- Policy: admin/superadmin only

### B2. Cost Type - Model + CRUD API
- Model `CostType` + Resource + FormRequest
- `GET/POST /api/v1/cost-types`, `PUT/DELETE /api/v1/cost-types/{id}`
- Policy: admin/superadmin only

### B3. Pricing Package - Model + CRUD API
- Model `PricingPackage` + Resource + FormRequest
- `GET/POST /api/v1/pricing-packages`, `PUT/DELETE /api/v1/pricing-packages/{id}`
- Policy: admin/superadmin only

---

## Fase C - Backend: Revamp Booking Flow

### C1. BookingPayment - Model + API
- Model `BookingPayment` + Resource
- Endpoints:
  - `GET /api/v1/bookings/{id}/payments`
  - `POST /api/v1/bookings/{id}/payments`
  - `POST /api/v1/booking-payments/{id}/reallocate`
- Total bayar dan sisa tagihan dihitung di `BookingResource`.

### C2. Refund - Model + API
- Model `Refund` + Resource
- `POST /api/v1/bookings/{id}/refund`
- `GET /api/v1/bookings/{id}/refunds`

### C3. Update `BookingService::createBooking()`
- Terima field `lama_sewa`, `paket_sewa`, `harga_dealing`, `dp`, `rekening_dp_id`.
- Simpan lama sewa dan paket di `bookings` dan di `booking_details` awal.
- Detail awal boleh berupa unit fix (`unit_id`) atau placeholder (`unit_placeholder`).
- Jika ada DP, buat record `BookingPayment` type `dp`.
- Simpan `created_by` dari user login.
- Status awal tetap `follow_up`.

### C4. Booking Detail Unit/Pricing Flow
- `POST /api/v1/bookings/{id}/details` dan `PATCH /api/v1/booking-details/{id}` menerima:
  - `unit_id`, `driver_id`
  - `tgl_sewa`, `tgl_kembali`
  - `lama_sewa`, `paket_sewa`
  - `harga_mobil`, `diskon_mobil`
  - `pricing_mode` (`all_in` / `non_all_in`)
  - `pricing_package_id`, `harga_all_in`
  - `costs[]` berisi `cost_type_id`, `label`, `amount`, `keterangan`
- `detail_type` boleh tidak dikirim untuk detail awal; backend memberi default `initial`.
- Jika detail awal masih placeholder, modal Tambah Unit meng-update detail tersebut dan mengosongkan `unit_placeholder` saat `unit_id` dipilih.
- Jika detail awal sudah punya unit fix, modal yang sama berfungsi sebagai Edit Unit.
- Non All In: `(harga_mobil - diskon_mobil) x lama_sewa + sum(costs)`.
- All In: `harga_all_in x lama_sewa`; biaya operasional tetap tersimpan sebagai biaya internal/acuan.
- Tanggal operasional dikirim sebagai local datetime (`YYYY-MM-DD HH:mm:ss`), bukan `toISOString()`, agar jam tidak bergeser karena konversi UTC.

### C4b. Handle Booking Status Flow
- Handle booking tidak lagi menerima form detail kendaraan atau biaya.
- Aksi handle hanya konfirmasi status `follow_up` menjadi `waiting_list`.
- Frontend menolak handle jika detail utama masih placeholder atau belum memiliki unit fix.
- Transition `follow_up -> waiting_list` diizinkan melalui endpoint status.

### C4c. Booking Edit Flow
- `PUT/PATCH /api/v1/bookings/{id}` mengubah data booking selain konsumen.
- Field editable: `lama_sewa`, `paket_sewa`, `harga_dealing`, `dp`, `rekening_dp_id`, `tujuan`, `alamat_penjemputan`, `catatan`.
- `customer_id` tidak diedit dari detail booking agar riwayat konsumen tetap stabil.

### C5. Checkout Logic (`waiting_list` -> `rental_unit`)
- `POST /api/v1/bookings/{id}/checkout`
- Validasi status = `waiting_list`
- Accept `skip_inspection` berdasarkan popup frontend.
- Update booking status -> `rental_unit`.
- Update unit status -> `Out`.
- Set booking_detail status -> `aktif`.

### C6. Complete Logic (`rental_unit` -> `selesai`)
- `POST /api/v1/bookings/{id}/complete`
- Accept `skip_inspection`.
- Update booking status -> `selesai`.
- Update unit status -> `Aktif`.
- Set booking_detail status -> `selesai`.

### C7. Revamp BookingModificationService
- **Extend**: form lengkap dengan unit, driver, tanggal, lama sewa, paket, biaya, dan pricing mode. Buat booking_detail baru type `extend`.
- **Rolling**: adjust detail lama bila perlu, lalu buat detail baru type `rolling`.
- **Batal**: buat record refund, ubah status -> `batal`, unit -> `Aktif`.

### C8. Update BookingResource
- Tambah computed fields:
  - `total_payments`
  - `total_tagihan`
  - `sisa_tagihan`
  - `is_overdue`
- `total_tagihan` memakai detail kendaraan yang sudah memiliki harga.
- Jika detail kendaraan belum diisi, `total_tagihan` fallback ke `harga_dealing` booking lama.
- Untuk All In, `total_tagihan = harga_all_in x lama_sewa`.
- Include relations: details, payments, refunds, costs dengan cost_type.
- Include `rekening_dp_id` untuk form edit booking.

---

## Fase D - Frontend: Halaman Master Data Baru

### D1. Payment Account Management
- Halaman CRUD di admin area.
- DataTable + Dialog form: nama bank, nomor rekening, atas nama.

### D2. Cost Type Management
- Halaman CRUD di admin area.
- DataTable + Dialog form: nama, kode, require_description, sort_order.

### D3. Pricing Package Management
- Halaman CRUD di admin area.
- DataTable + Dialog form: nama paket, harga, keterangan include/exclude.

---

## Fase E - Frontend: Revamp Booking Flow

### E1. BookingCreateView
- Layout booking create didesain ulang agar input booking lebih mudah dipindai.
- Tambah input `lama_sewa` dan `paket_sewa`.
- Ganti hardcoded rekening dengan data dari `payment-accounts` API.
- List pencarian pelanggan menampilkan status pelanggan.
- Saat unit ready dipilih, tampilkan no polisi, pemilik, dan status.
- Submit booking mengirim `created_by` sesuai migration backend.
- Duplicate toast error dihilangkan dari composable agar satu error tidak muncul dua kali.

### E2. BookingDetailView - Section Pembayaran
- Tampilkan list pembayaran.
- Tombol Tambah Pembayaran membuka dialog nominal, akun, tipe.
- Tombol Realokasi per payment membuka dialog booking tujuan.
- Summary: total dibayar, sisa tagihan.

### E3. BookingDetailView - Layout, Edit Booking, Tambah/Edit Unit
- Layout detail booking didesain ulang.
- Style global surface/card ditambahkan agar background dan card punya perbedaan warna yang terlihat tanpa kontras berlebihan.
- Ringkasan utama tetap memakai navy agar kontras.
- Data booking dapat diedit kecuali data konsumen.
- Jika unit awal placeholder, tombol menampilkan "Tambah Unit".
- Jika unit awal sudah fix, tombol berubah menjadi "Edit Unit".
- Card unit/kendaraan menampilkan:
  - Placeholder unit jika belum fix.
  - No polisi, pemilik, dan status jika sudah fix.
- Modal Tambah/Edit Unit berisi:
  - Unit, driver.
  - Tanggal sewa dan tanggal kembali.
  - Lama sewa select 1-99.
  - Paket sewa.
  - Harga mobil, diskon.
  - Toggle All In / Non All In.
  - Pricing package atau manual `harga_all_in`.
  - Biaya operasional dari cost types.
- Saat pilih unit, tampilkan status, no polisi, dan pemilik.
- Saat pilih driver, tampilkan nama driver, kota, dan kontak.
- Default tanggal sewa memakai jam 07:00.
- Default tanggal kembali memakai jam 23:59.
- Jika tanggal sewa diubah, tanggal kembali otomatis sama dengan tanggal sewa jam 23:59.
- Jika lama sewa diubah, tanggal kembali otomatis maju sesuai lama sewa.
- Tanggal kembali tidak boleh kurang dari tanggal sewa.
- Semua tanggal disimpan sebagai local datetime supaya tidak mundur 7 jam di WIB.
- Kalkulasi real-time:
  - Harga sewa = `(harga_mobil - diskon) x lama_sewa`.
  - Total biaya operasional.
  - Grand total internal.
  - Tagihan konsumen berdasarkan mode All In / Non All In.
- Ringkasan keuangan memakai detail harga kendaraan jika sudah ada.
- Jika detail kendaraan belum diisi, ringkasan memakai total biaya booking sebelumnya.

### E4. BookingDetailView - Handle, Checkout & Complete
- Tombol Handle hanya membuka konfirmasi ubah status ke `waiting_list`.
- Handle disabled/ditolak jika unit masih placeholder.
- Tombol Checkout muncul saat `waiting_list`:
  - Dialog konfirmasi cek fisik.
  - Option lanjut atau checkout tanpa cek fisik.
- Tombol Selesai muncul saat `rental_unit`:
  - Dialog cek fisik kepulangan.
- Setelah aksi, data booking di-refresh.

### E5. BookingDetailView - Extend/Rolling/Batal Revamp
- **Extend dialog**: form lengkap unit, driver, tanggal, lama sewa, paket, biaya, pricing mode.
- **Rolling dialog**: step adjust detail lama, lalu form lengkap detail baru.
- **Batal dialog**: input nominal refund, keterangan, pilih akun refund.

### E6. BookingListView - Late Return Warning
- Kolom/badge Terlambat jika `is_overdue` true.
- Highlight row atau ikon warning.

---

## Urutan Pengerjaan (Task Sequencing)

| # | Task | Depends On | Status |
|---|------|------------|--------|
| 1 | A1-A9: Semua migrasi + seeder | - | done |
| 2 | B1-B3: Master data CRUD backend | A1-A3 | done |
| 3 | C1-C2: BookingPayment + Refund backend | A4-A5 | done |
| 4 | C3: Update createBooking | A6-A7, C1 | done |
| 5 | C4-C4c: Detail unit, handle status, edit booking | A7-A8, B2-B3 | done |
| 6 | C5-C6: Checkout + Complete | A9 | done |
| 7 | C7: Revamp modifications | C1-C6 | done |
| 8 | C8: Update BookingResource | C1-C7 | done |
| 9 | D1-D3: Frontend master data pages | B1-B3 | done |
| 10 | E1: Revamp BookingCreateView | C3, D1 | done |
| 11 | E2: Payment section | C1 | done |
| 12 | E3: BookingDetail layout + Tambah/Edit Unit | C4-C4c, D2-D3 | done |
| 13 | E4: Handle confirmation + Checkout/Complete UI | C4b-C6 | done |
| 14 | E5: Extend/Rolling/Batal revamp | C7 | done |
| 15 | E6: Late return warning | C8 | done |

---

## Catatan Implementasi

- Kolom `dp` dan `rekening_dp_id` di `bookings` tetap ada untuk backward compatibility.
- Tabel `booking_payments` tetap menjadi target pencatatan pembayaran aktual.
- Piutang (receivables) belum diimplement di fase ini; hanya `sisa_tagihan` yang dihitung.
- Cek fisik saat ini hanya popup konfirmasi; modul penuh belum dikembangkan.
- Semua amount dalam IDR integer.
- Semua field tanggal/jam operasional booking detail dikirim sebagai local datetime (`YYYY-MM-DD HH:mm:ss`), bukan UTC ISO string.
- All In tetap dikalikan `lama_sewa`.
- Jika detail kendaraan belum memiliki harga, ringkasan keuangan fallback ke biaya booking sebelumnya.
- Jika detail kendaraan sudah disimpan, ringkasan keuangan memakai detail harga tersebut.
