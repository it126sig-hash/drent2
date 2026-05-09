# DRENT — Rencana Pengembangan

> Setiap task dirancang agar scope-nya terbatas dan dapat dikerjakan dalam satu sesi AI Agent.
> Stack: Laravel (backend) + Vue 3 + PrimeVue (frontend).

---

## Ringkasan Fase

| Fase | Nama | Fokus |
|------|------|-------|
| **0** | Foundation | Setup project, DB schema, Auth |
| **1** | Master Data (MDM) | Tenant, Branch, User, Unit, Pemilik, Driver, Customer, Member |
| **2** | Booking & Transaksi | Booking flow, status transaksi, booking detail & biaya |
| **3** | Keuangan & Cek Fisik | Invoice, piutang, rent-to-rent, operasional driver, kas, cek fisik |
| **4** | Modul Pendukung | Survey member, pemeliharaan unit, notifikasi in-app, dashboard, laporan |

---

## FASE 0 — Foundation (SELESAI)

> Tujuan: Project siap dijalankan, struktur folder terbentuk, migrasi DB dasar jalan, auth bisa login.

### 0.1 — Setup Project (SELESAI)
- Laravel project dibuat di folder `backend/`
- Vue 3 + Vite dibuat di folder `frontend/`
- `.env` dikonfigurasi untuk koneksi DB
- Laravel Sanctum diinstall dan dikonfigurasi
- Tailwind CSS + PrimeVue diinstall di frontend

### 0.2 — Migrasi Database Inti (SELESAI)
- Buat migration: `tenants`, `branches`, `users`
- Tambahkan kolom `tenant_id` dan `branch_id` di semua tabel utama
- Buat seeder: 1 tenant default, 1 branch default, 1 user superadmin
- Jalankan dan verifikasi migrasi berjalan tanpa error

### 0.3 — Auth Backend (SELESAI)
- Endpoint `POST /api/v1/login` — return Sanctum token + user context (role, branch)
- Endpoint `POST /api/v1/logout` — revoke token
- Endpoint `GET /api/v1/me` — return user yang sedang login
- Middleware branch scope: pastikan setiap request membawa `branch_id`
- Buat `UserResource` untuk response

### 0.4 — Auth Frontend (SELESAI)
- Halaman Login (`/login`) dengan form email + password
- Pinia store `useAuthStore`: simpan token, user, branch aktif
- Axios interceptor: tambah token ke header setiap request
- Route guard: redirect ke login jika belum auth
- Redirect ke dashboard setelah login berhasil

---

## FASE 1 — Master Data Management (MDM)

> Tujuan: Semua data referensi tersedia sebelum modul transaksi dibuat.

### 1.1 — MDM: Pemilik Rental (Backend) (SELESAI)
- Migration tabel `rental_owners` (field: nama, kontak_1, kontak_2, alamat, kota, bank, no_rek, atas_nama, is_owner, tenant_id, soft-delete)
- CRUD endpoint: `GET/POST /api/v1/rental-owners`, `GET/PUT/DELETE /api/v1/rental-owners/{id}`
- FormRequest untuk store & update
- `RentalOwnerResource` untuk response
- Policy: hanya admin dan superadmin yang bisa akses

### 1.2 — MDM: Pemilik Rental (Frontend) (SELESAI)
- Halaman list pemilik rental dengan DataTable PrimeVue
- Dialog form tambah/edit pemilik
- Tombol hapus dengan konfirmasi
- Toast notifikasi sukses/error
- Integrasi ke API via `src/api/rentalOwner.js`

### 1.3 — MDM: Unit Kendaraan (Backend) (SELESAI)
- Migration tabel `units` (field: tipe, merk, tahun, no_polisi, pemilik_id, harga_1_hari, harga_1_minggu, harga_1_bulan, modal_1_hari, modal_1_minggu, modal_1_bulan, status, tenant_id, branch_id, soft-delete)
- CRUD endpoint: `GET/POST /api/v1/units`, `GET/PUT/DELETE /api/v1/units/{id}`
- Global scope branch per model Unit
- Upload foto unit: `POST /api/v1/units/{id}/photos`
- `UnitResource` untuk response

### 1.4 — MDM: Unit Kendaraan (Frontend) (SELESAI)
- Halaman list unit dengan DataTable + filter status
- Dialog form tambah/edit unit (dropdown pemilik dari API)
- Upload foto unit (multi-file)
- Badge status unit (Aktif / Tidak Aktif / Dalam Servis)
- Integrasi ke `src/api/unit.js`

### 1.5 — MDM: Driver (Backend)
- Migration tabel `drivers` (field: nama, alamat, kota, no_sim, kontak_1, kontak_2, saldo, status, is_tetap, user_id nullable, tenant_id, branch_id, soft-delete)
- CRUD endpoint: `GET/POST /api/v1/drivers`, `GET/PUT/DELETE /api/v1/drivers/{id}`
- Endpoint khusus: `PATCH /api/v1/drivers/{id}/balance` — update saldo (Finance only)
- `DriverResource` untuk response

### 1.6 — MDM: Driver (Frontend)
- Halaman list driver dengan DataTable + filter status & is_tetap
- Dialog form tambah/edit driver
- Panel saldo driver: tampil saldo saat ini + tombol edit saldo (Finance only)
- Integrasi ke `src/api/driver.js`

### 1.7 — MDM: Pelanggan (Backend) (SELESAI)
- Migration tabel `customers` (field: nama, kontak_1, kontak_2, alamat, kota, status, has_apply_member, tenant_id, soft-delete)
- CRUD endpoint: `GET/POST /api/v1/customers`, `GET/PUT/DELETE /api/v1/customers/{id}`
- Filter endpoint: `?status=Redflag` untuk pengecekan booking
- `CustomerResource` untuk response

### 1.8 — MDM: Pelanggan (Frontend) (SELESAI)
- Halaman list pelanggan dengan DataTable + filter status
- Dialog form tambah/edit pelanggan
- Badge status pelanggan (Normal, Corporate, Redflag, Blacklist)
- Warning banner sesuai status: Redflag (peringatan risiko), Blacklist (blokir booking)
- Integrasi ke `src/api/customer.js`

### 1.9 — MDM: Member (Backend) (SELESAI)
- Migration tabel `members` (field: customer_id, id_member, status_member, tanggal_survey, tanggal_aktif, tanggal_exp, surveyor_id, catatan, + field identitas, pekerjaan, keluarga)
- Upload dokumen: KTP, foto wajah, dokumen pendukung
- Endpoint: `GET/POST /api/v1/members`, `GET/PUT /api/v1/members/{id}`
- Endpoint: `PATCH /api/v1/members/{id}/activate` — aktivasi member (Admin)
- `MemberResource` untuk response

### 1.10 — MDM: Member (Frontend) (SELESAI)
- Halaman list member dengan DataTable + filter status_member
- Form member lengkap (multi-section: identitas, pekerjaan, keluarga)
- Upload foto dan dokumen
- Tombol aktivasi member oleh Admin
- Integrasi ke `src/api/member.js`

### 1.11 — MDM: User & Permission (Backend)
- CRUD user: `GET/POST /api/v1/users`, `GET/PUT/DELETE /api/v1/users/{id}`
- Assign role ke user
- Endpoint: `GET /api/v1/roles` — list semua role
- Migration tabel `role_permissions` dan `user_permission_overrides`
- `UserResource` untuk response

### 1.12 — MDM: User & Permission (Frontend)
- Halaman list user dengan DataTable
- Dialog form tambah/edit user (pilih role, assign branch)
- Tampilkan role badge per user
- Integrasi ke `src/api/user.js`

---

## FASE 2 — Booking & Transaksi

> Tujuan: CS bisa membuat dan mengelola booking dari awal sampai selesai.

### 2.1 — Booking: Database Schema & Migration
- Migration tabel `bookings` (field: tenant_id, branch_id, customer_id, status, harga_dealing, dp, rekening_dp_id, tujuan, alamat_penjemputan, catatan, soft-delete)
- Migration tabel `booking_details` (field: booking_id, unit_id, driver_id, tgl_sewa, tgl_kembali, harga_mobil, diskon_mobil, detail_type, status)
- Migration tabel `booking_costs` (field: booking_detail_id, type, label, amount)
- Tambahkan seeder data dummy untuk testing

### 2.2 — Booking: Buat Booking Awal (Backend)
- Endpoint `POST /api/v1/bookings` — buat booking baru (status: Follow Up atau Confirm)
- `StoreBookingRequest`: validasi customer, tanggal, harga_dealing, dp
- `BookingService::createBooking()` — logic: set status berdasarkan ada/tidak dp
- `BookingResource` untuk response
- Policy: hanya CS dan Admin yang bisa buat booking

### 2.3 — Booking: Form Buat Booking (Frontend)
- Halaman form booking baru
- Autocomplete pilih pelanggan (warning risiko jika Redflag, error/blokir jika Blacklist)
- Input: tanggal sewa & kembali, tujuan, harga dealing, dp (opsional)
- Submit → status otomatis Follow Up atau Confirm
- Integrasi ke `src/api/booking.js`

### 2.4 — Booking: List & Status (Backend)
- Endpoint `GET /api/v1/bookings` — list booking (filter: status, branch, tanggal)
- Endpoint `GET /api/v1/bookings/{id}` — detail booking
- Endpoint `PATCH /api/v1/bookings/{id}/status` — ubah status manual
- Branch scope wajib ada di semua query

### 2.5 — Booking: List & Kalender (Frontend)
- Halaman list booking dengan DataTable + filter status
- Badge status booking (warna per status)
- Kalender Timeline (Gantt-style): 1 baris per unit, 30 hari view
- Klik sel kosong → buka form booking dengan unit & tanggal pre-filled
- Integrasi ke `src/api/booking.js`

### 2.6 — Booking: Handle Booking — Detail & Biaya (Backend)
- Endpoint `POST /api/v1/bookings/{id}/details` — tambah booking_detail (pilih unit, driver, tanggal)
- Endpoint `POST /api/v1/booking-details/{id}/costs` — tambah komponen biaya
- `HandleBookingService::assignDetail()` — logic: otomatis catat hutang rent-to-rent jika unit bukan milik sendiri
- Endpoint `PATCH /api/v1/bookings/{id}/handle` — pindah status ke Waiting List

### 2.7 — Booking: Handle Booking (Frontend)
- Halaman detail booking
- Section "Handle Booking": pilih unit, driver, tanggal detail
- Section "Komponen Biaya": input biaya operasional (driver, BBM, tol, dll)
- Tombol "Handle" → pindah ke Waiting List
- Integrasi ke `src/api/booking.js`

### 2.8 — Booking: Modifikasi Transaksi Aktif (Backend)
- Endpoint `POST /api/v1/bookings/{id}/extend` — perpanjang sewa (tambah booking_detail baru, type: extend)
- Endpoint `POST /api/v1/bookings/{id}/rolling` — ganti unit (booking_detail baru, type: rolling)
- Endpoint `POST /api/v1/bookings/{id}/stop-early` — hentikan lebih awal (hitung refund)
- Endpoint `POST /api/v1/bookings/{id}/costs` — tambah biaya/diskon tambahan
- Semua modifikasi hanya bisa dilakukan saat status `Rental Unit`

### 2.9 — Booking: Modifikasi Transaksi (Frontend)
- Halaman detail booking: tampilkan semua detail & biaya
- Panel modifikasi (locked saat bukan Rental Unit): Extend, Rolling, Berhenti Mendadak
- Form tambah biaya/diskon tambahan
- Integrasi ke `src/api/booking.js`

---

## FASE 3 — Keuangan & Cek Fisik

> Tujuan: Finance bisa kelola invoice, piutang, bon driver, dan kas. Cek fisik bisa dilakukan dari mobile.

### 3.1 — Keuangan: Rekening & Setup (Backend)
- Migration tabel `rekenings` (nama bank, nomor rekening, atas nama, branch_id)
- CRUD endpoint: `GET/POST /api/v1/rekenings`, `PUT/DELETE /api/v1/rekenings/{id}`
- `RekeningResource` untuk response

### 3.2 — Keuangan: Invoice (Backend)
- Migration tabel `invoices`, `invoice_bookings`, `payments`
- Endpoint `POST /api/v1/invoices` — buat invoice dari 1 atau beberapa booking
- Endpoint `GET /api/v1/invoices/{id}` — detail invoice
- Endpoint `POST /api/v1/invoices/{id}/payments` — tambah pembayaran (partial)
- Endpoint `PATCH /api/v1/invoices/{id}/void` — void invoice
- Endpoint `GET /api/v1/invoices/{id}/pdf` — generate PDF invoice
- `InvoiceService::generate()` — logic: auto generate nomor invoice per branch

### 3.3 — Keuangan: Invoice (Frontend)
- Halaman list invoice dengan filter status & branch
- Halaman buat invoice: pilih satu/beberapa booking
- Detail invoice: tampilkan item, total, status pembayaran
- Form tambah pembayaran (partial payment)
- Tombol download PDF
- Integrasi ke `src/api/invoice.js`

### 3.4 — Keuangan: Piutang (Backend)
- Endpoint `GET /api/v1/receivables` — list booking dengan sisa tagihan
- Filter: branch, customer, periode
- `ReceivableResource` untuk response

### 3.5 — Keuangan: Piutang (Frontend)
- Halaman piutang: DataTable booking yang masih ada tagihan
- Kolom: nama pelanggan, total, sudah dibayar, sisa
- Tombol generate invoice dari halaman ini
- Integrasi ke `src/api/receivable.js`

### 3.6 — Keuangan: Rent-to-Rent / Hutang (Backend)
- Migration tabel `rent_to_rent_debts`
- Auto-create hutang saat `HandleBookingService` assign unit milik rental lain
- Endpoint `GET /api/v1/rent-to-rent-debts` — list hutang per pemilik
- Endpoint `POST /api/v1/rent-to-rent-debts/{id}/confirm` — generate tagihan konfirmasi

### 3.7 — Keuangan: Rent-to-Rent (Frontend)
- Halaman hutang rent-to-rent: grouped per pemilik rental
- Tombol generate tagihan konfirmasi (download PDF)
- Integrasi ke `src/api/rentToRent.js`

### 3.8 — Keuangan: Operasional Driver (Backend)
- Migration tabel `driver_balances` (driver_id, booking_id, amount, type, status)
- Endpoint `POST /api/v1/drivers/{id}/balance-in` — beri uang operasional ke driver
- Endpoint `GET /api/v1/driver-bons` — list bon driver (filter: status, driver)
- Endpoint `POST /api/v1/driver-bons` — input bon (Finance, untuk driver tidak tetap)
- Endpoint `PATCH /api/v1/driver-bons/{id}/validate` — validasi bon (Finance)
- Driver tetap: endpoint `POST /api/v1/driver-bons/upload` — upload bon sendiri (role Driver)

### 3.9 — Keuangan: Operasional Driver (Frontend)
- Halaman list bon driver dengan filter status & driver
- Form input bon (Finance, untuk driver tidak tetap)
- Halaman upload bon (mobile view, untuk driver tetap)
- Panel validasi bon oleh Finance
- Integrasi ke `src/api/driverBon.js`

### 3.10 — Keuangan: Kas (Backend)
- Migration tabel `kas_transactions` (type: in/out, amount, description, rekening_id, transaction_date, branch_id)
- CRUD endpoint: `GET/POST /api/v1/kas`, `PUT/DELETE /api/v1/kas/{id}`
- Endpoint `POST /api/v1/kas/transfer` — pindah kas antar rekening

### 3.11 — Keuangan: Kas (Frontend)
- Halaman kas: list transaksi kas dengan filter periode & branch
- Form tambah transaksi kas (in/out)
- Form pindah kas (pilih sumber & tujuan rekening)
- Summary saldo per rekening
- Integrasi ke `src/api/kas.js`

### 3.12 — Cek Fisik (Backend)
- Migration tabel `inspections`, `inspection_photos`, `inspection_checklists`, `inspection_signatures`
- Endpoint `POST /api/v1/inspections` — buat laporan cek fisik (type: pre-departure / post-return)
- Upload foto per sisi (kompresi di backend jika > 1MB)
- Endpoint `GET /api/v1/inspections/{id}/pdf` — export PDF cek fisik
- Endpoint `GET /api/v1/bookings/{id}/inspections` — list cek fisik per booking

### 3.13 — Cek Fisik (Frontend)
- Halaman form cek fisik (dioptimalkan untuk mobile)
- Upload foto per sisi (akses kamera HP)
- Checklist perlengkapan kendaraan
- Input KM odometer + visual fuel gauge
- Canvas tanda tangan digital (Signature Pad)
- Tombol submit → update status booking
- Integrasi ke `src/api/inspection.js`

---

## FASE 4 — Modul Pendukung

> Tujuan: Fitur pendukung operasional: survey member, pemeliharaan, notifikasi, dashboard, laporan.

### 4.1 — Survey Member (Backend)
- Endpoint `GET /api/v1/surveys` — list pengajuan survey member
- Endpoint `POST /api/v1/surveys` — surveyor mulai survey (pilih/buat pelanggan, isi form)
- Endpoint `PATCH /api/v1/surveys/{id}/submit` — surveyor submit (status: Pending atau Ditolak)
- Endpoint `PATCH /api/v1/members/{id}/activate` — Admin aktifkan member
- Endpoint `PATCH /api/v1/members/{id}/fast-track` — Admin fast-track (wajib isi keterangan)

### 4.2 — Survey Member (Frontend)
- Halaman list survey member dengan filter status
- Halaman form survey: multi-section (identitas, pekerjaan, keluarga, sosial)
- Upload KTP, foto wajah, dokumen pendukung
- Panel Admin: review submission, tombol aktifkan / fast-track
- Integrasi ke `src/api/survey.js`

### 4.3 — Pemeliharaan Unit (Backend)
- Migration tabel `maintenance_records`, `maintenance_types`, `maintenance_photos`
- CRUD maintenance_types: `GET/POST /api/v1/maintenance-types`
- CRUD maintenance records: `GET/POST /api/v1/maintenance-records`, `GET/PUT/DELETE /api/v1/maintenance-records/{id}`
- Upload foto nota
- Logic: Unit dengan maintenance aktif tidak bisa di-booking (block di `StoreBookingRequest`)

### 4.4 — Pemeliharaan Unit (Frontend)
- Halaman list pemeliharaan dengan filter unit & periode
- Form tambah catatan pemeliharaan (dropdown tipe, upload foto nota)
- Manajemen tipe pemeliharaan (Admin)
- Halaman detail unit: tab riwayat pemeliharaan
- Integrasi ke `src/api/maintenance.js`

### 4.5 — Notifikasi In-App (Backend)
- Migration tabel `notifications` (user_id, type, title, body, data JSON, is_read, created_at)
- Service `NotificationService::send()` — buat notifikasi ke satu atau banyak user
- Trigger notifikasi dari Service yang sudah ada:
  - Booking baru → CS & Admin
  - Invoice di-generate → CS terkait
  - Bon driver divalidasi → CS terkait
  - Unit jatuh tempo servis (H-7, H-1) → scheduled job
  - Member hampir habis (H-30) → scheduled job
- Endpoint `GET /api/v1/notifications` — list notifikasi user yang login
- Endpoint `PATCH /api/v1/notifications/{id}/read` — tandai dibaca
- Endpoint `PATCH /api/v1/notifications/read-all` — tandai semua dibaca

### 4.6 — Notifikasi In-App (Frontend)
- Bell icon di navbar dengan badge jumlah unread
- Dropdown list notifikasi terbaru
- Klik notifikasi → redirect ke halaman terkait
- Polling atau auto-refresh setiap 30 detik
- Integrasi ke `src/api/notification.js`

### 4.7 — Dashboard (Backend)
- Endpoint `GET /api/v1/dashboard/stats` — return KPI:
  - Jumlah transaksi aktif (Rental Unit)
  - Booking pending (Follow Up & Confirm)
  - Total piutang outstanding
  - Unit dalam pemeliharaan
  - Driver dengan saldo sisa
- Endpoint `GET /api/v1/dashboard/revenue-chart` — revenue per bulan (12 bulan)
- Endpoint `GET /api/v1/dashboard/unit-utilization` — utilisasi per unit
- Semua endpoint scope per branch

### 4.8 — Dashboard (Frontend)
- Halaman dashboard utama
- KPI Cards: 5 kartu statistik utama
- Bar chart revenue bulanan (menggunakan Chart.js atau PrimeVue Charts)
- Pie chart distribusi status booking
- Tabel top konsumen
- Integrasi ke `src/api/dashboard.js`

### 4.9 — Laporan (Backend)
- Endpoint `GET /api/v1/reports/transactions` — laporan transaksi (filter: status, branch, periode, customer, unit) + export CSV
- Endpoint `GET /api/v1/reports/receivables` — laporan piutang + export CSV
- Endpoint `GET /api/v1/reports/rent-to-rent` — laporan hutang rent-to-rent + export CSV
- Endpoint `GET /api/v1/reports/driver-operations` — laporan operasional driver + export CSV
- Endpoint `GET /api/v1/reports/kas` — laporan kas + export CSV
- Endpoint `GET /api/v1/reports/maintenance` — laporan pemeliharaan + export CSV
- Endpoint `GET /api/v1/reports/unit-utilization` — laporan utilisasi unit + export CSV
- Endpoint `GET /api/v1/reports/revenue` — laporan revenue + export CSV

### 4.10 — Laporan (Frontend)
- Halaman laporan: daftar jenis laporan
- Setiap laporan punya filter: branch, periode, dan filter spesifik
- Preview data dalam DataTable
- Tombol export CSV dan export PDF
- Integrasi ke `src/api/report.js`

---

## Catatan Penting untuk Semua Task

### Aturan yang Selalu Berlaku
1. **Validasi** → selalu gunakan `FormRequest`, jangan validasi di Controller
2. **Response** → selalu bungkus dengan `JsonResource`, jangan return Model langsung
3. **Business logic** → taruh di `Service` class, Controller hanya delegate
4. **Otorisasi** → gunakan `Policy`, bukan hardcode role di Controller
5. **Branch scope** → semua query data operasional wajib filter `branch_id`
6. **Amount** → semua angka uang sebagai integer IDR, tanpa desimal
7. **Soft delete** → semua model pakai `SoftDeletes` trait
8. **tenant_id** → semua tabel wajib punya kolom `tenant_id` (SaaS-ready)

### Hal yang Belum Boleh Diimplementasi
- Strategi arsip data (tunggu konfirmasi: soft-delete vs tabel terpisah vs is_archived)
- Approval workflow untuk perubahan invoice
- Tracking jam kerja driver non-tetap
- Integrasi WhatsApp / email (di luar scope Fase 1)
- Portal konsumen dan online booking publik
