# Peta Module & Alur — DRENT

> Tujuan: inventarisasi module backend + frontend beserta alur bisnisnya sebagai dasar perapian dan perombakan alur.
> Disusun dari eksplorasi read-only `backend/` (routes/api.php, Controllers/Api/V1, Models, Services) dan `frontend/src` (2026-11-25).
> Dokumen pendamping: `docs/component-inventory.md` (sisi komponen UI).

## 1. Konvensi Arsitektur

- Backend: Laravel + Sanctum, semua endpoint di `routes/api.php` prefix `v1`, middleware `auth:sanctum` + `branch.scope` (isolasi cabang).
- Pola per module: `Controller (Api/V1) → FormRequest → Service → Model → JsonResource`.
- Frontend: `views/` (halaman) → `composables/use<Entity>.js` → `api/<entity>.js` (axios terpusat) → `backend`.
- Endpoint publik (tanpa login): invoice SOA, tagihan rent-to-rent (+PDF), physical check (OTP + submit), progres booking publik.

## 2. Peta Module

| # | Module | Backend (Controller / Service / Model utama) | Frontend (views) | Catatan |
|---|---|---|---|---|
| 1 | **Auth & Profil** | `AuthController`, `ProfileController`, `AuthService`, `UserProfileService`; User, RolePermission, UserPermissionOverride | `LoginView`, `views/profile`, `views/users/`, `views/settings/RolePermissionView` | Login/logout/me, ganti password, permission override per user |
| 2 | **Master Data** | `BranchController`, `TenantController`, `CityController`, `ProvinceController`, `CostTypeController`, `FinanceCategoryController`, `PaymentAccountController`, `PricingPackageController` (+import), `InvoiceTermsTemplateController`, `RentalOwnerController` | `views/master/` (Branch, City, CostType, PaymentAccount, PricingPackage, InvoiceTermsTemplate, TenantSettings), `views/rental-owners/` | Data dasar; `pricing-packages/import` ada endpoint khusus |
| 3 | **Armada (Unit)** | `UnitController`, `UnitService`; Unit, UnitPhoto | `views/units/UnitListView` (+`UnitFormDialog`, `UnitPhotoManager`) | Batch update city, jadwal cek (`schedule-check`) |
| 4 | **Driver & Dana Operasional** | `DriverController`, `DriverOperationalFundController`, `DriverOperationalFundService`, `DriverUsageReportService`; Driver, DriverBalanceLedger, DriverOperationalFund(+Item), DriverOperationalExpense | `views/drivers/DriverListView`, `views/driver/` (DriverOperationalView, DriverTripHistoryView), `views/reports/DriverUsageReportView` | Perhatikan dua folder mirip: `drivers/` (admin) vs `driver/` (portal driver) |
| 5 | **Customer & Member** | `CustomerController`, `MemberController`, `MemberService`, `Legacy*ImportService`; Customer, Member, MemberExtension | `views/customers/`, `views/members/` | Member punya aktivasi, status, perpanjangan, dokumen |
| 6 | **Booking (inti)** | `BookingController`, `BookingDetailController`, `BookingCostController`, `BookingModificationController`, `BookingService`, `BookingModalService`; Booking, BookingDetail, BookingCost | `views/bookings/` (List, Create, Detail), `views/reports/` | Aksi status: handle, checkout, complete; modifikasi: extend, rolling, stop-early, cancel; extend unit sewa via booking-details |
| 7 | **Pembayaran Booking** | `BookingPaymentController`, `BookingPaymentService`; BookingPayment, Payment | dialog di `views/bookings/` | Realokasi alokasi DP, void dengan alur request/approve/reject (2 langkah) |
| 8 | **Refund & Pembatalan** | `BookingCancellationController`, `RefundController`, `BookingCancellationService`, `RefundService`; BookingCancellation, Refund | `views/bookings/BookingCancellationListView` | Refund DP saat cancel; bayar refund (`pay-refund`) |
| 9 | **Invoice / Piutang** | `ReceivableController`, `ReceivableService`, `InvoicePdfService`, `BookingBillingService`; Invoice, InvoiceBooking, InvoiceHistory, InvoiceInvoice, InvoiceTermsTemplate, Payment | `views/finance/ReceivableListView`, `views/public/` (PublicInvoiceView, PublicMergedInvoiceView, PublicBookingInvoiceListView) | Generate invoice dari kumpulan booking, merge, kirim, bayar, void 2 langkah; publik via token SOA |
| 10 | **Rent-to-Rent** | `RentToRentController`, `RentToRentService`, `RentToRentPdfService`; RentToRentBill(+Item), Payment, PaymentAllocation, Debt, AmountChangeRequest | `views/finance/RentToRentListView`, `views/public/PublicRentToRentBillView` | Tagihan ke pemilik unit, mark-paid, void 2 langkah, hutang, PDF via token |
| 11 | **Physical Check** | `PhysicalCheckController`(+Item), `PhysicalCheckService`; PhysicalCheck + Section/Checklist/Photo/Signature/Activity/Otp | `views/physical-checks/`, `views/public/` | Berbasis booking + token publik + OTP; signature (lihat inventaris: 2 komponen duplikat) |
| 12 | **Keuangan & Mutasi** | `PaymentAccountTransactionController`, `TransactionController`, `FinanceCategoryService`, `MonthlyFinanceReportService`; PaymentAccountTransaction, FleetCard* | `views/finance/` (PaymentAccountMutationView, TransactionListView, OperationalCostListView), `views/reports/MonthlyFinance*` | Transfer antar rekening, adjust, void 2 langkah |
| 13 | **Fleet Card** | `FleetCardController`, `FleetCardService`; FleetCard, FleetCardFund(+Item), Expense, BalanceLedger | `views/finance/FleetCardListView` | Dana untuk booking detail; approve/reject/void expense 2 langkah; juga dipakai driver (`driver/fleet-card-funds`) |
| 14 | **Approval / Permintaan** | `MyRequestController`, `SupervisorRequestController`; berbagai model request | `views/supervisor/SupervisorRequestListView`, API `myRequest.js` | Sentral untuk void/refund/extend/return yang butuh supervisor |
| 15 | **Dashboard & Laporan** | `DashboardController`, `MonthlyFinanceReportController`, `UnitUsageReportController`, `DriverUsageReportController`, `CustomerUsageReportController` | `DashboardView`, `views/reports/` | 4 laporan usage + laporan keuangan bulanan |
| 16 | **Public (tanpa login)** | `BookingPublicController`, `ReceivableController@publicInvoice`, `RentToRentController@publicBill*`, `PhysicalCheckController@public*` | `views/public/` (5 view) | Semua via token di URL |

## 3. Alur Bisnis Utama (state sekarang)

### 3.1 Booking → Invoice → Pembayaran
1. Buat booking (`bookings` + `booking-details` per unit sewa) → status awal; aksi `handle` → `checkout` → `complete`.
2. DP/angsuran dicatat di `booking_payments` dengan alokasi ke booking; realokasi & void 2 langkah (request → approve/reject).
3. Biaya tambahan via `booking-costs` / `bookings/{booking}/costs`.
4. Modifikasi: extend (perpanjang detail), rolling (ganti unit), stop-early (berhenti lebih awal), cancel → `booking_cancellations` + refund.
5. Piutang dirangkum jadi `invoices` (bisa merge beberapa invoice via `InvoiceInvoice`), dikirim (`mark-sent`), dibayar (`invoice-payments`), riwayat di `invoice_histories`; semua void 2 langkah.
6. Pelanggan melihat tagihan via link token publik (SOA / merged invoice / invoice per booking).

### 3.2 Rent-to-Rent (sewa unit dari pemilik)
1. `rental-owners` = pemilik unit; unit milik owner masuk skema rent-to-rent.
2. Tagihan periodik dibuat (`generateBill` → `rent_to_rent_bills` + items), bisa PDF, `mark-paid`.
3. Pembayaran owner dicatat, bisa void 2 langkah; sisa jadi `rent_to_rent_debts`; perubahan nominal via `AmountChangeRequest`.
4. Owner melihat tagihan via link token publik.
> Catatan: ada rencana perbaikan terpisah di `fix-rent-to-rent-task.md` / implementation plan — alur di sini adalah kondisi kode saat ini.

### 3.3 Pengembalian unit rental (rent-to-rent return)
`request-rental-unit-return` → `approve/reject-rental-unit-return` pada booking: pengembalian unit ke pemilik butuh persetujuan supervisor.

### 3.4 Dana operasional driver & fleet card
1. Fund dibuka per booking detail (`booking-details/{id}/fleet-card-funds` atau dana operasional driver) → diisi kas → dipakai expense (foto bukti) → approve/reject expense → fund di-close.
2. Void fund/expense selalu 2 langkah lewat supervisor request.
3. Driver mengakses fund miliknya via `driver/fleet-card-funds` (portal `views/driver/`).

### 3.5 Pola approval 2 langkah
Hampir semua aksi uang (void payment/invoice/bill/fund/expense, refund, extend, operational revert, return unit) mengikuti pola sama: **request → approve/reject** oleh supervisor, terlihat di `MyRequestController`/`SupervisorRequestController`.

### 3.6 Physical check
Permintaan check per booking → OTP → form publik (foto, tanda tangan, aktivitas) → submit; hasil dilihat di `views/physical-checks/`.

## 4. Masalah / Ketidakkonsistenan yang Ditemukan

1. **Duplikasi konsep invoice** — model `Invoice`, `InvoiceBooking`, `InvoiceInvoice` (invoice gabungan) dan controller tunggal `ReceivableController` menangani receivable + invoice + payment + void: satu controller sangat gemuk, pemisahan logika perlu ditinjau saat atur ulang alur.
2. **Dua "driver" di frontend** — `views/drivers/` (master driver) vs `views/driver/` (portal driver); mudah tertukar, perlu penamaan jelas (mis. `driver-portal/`).
3. **`UserPermissionOverrideDialog` salah lokasi** (`views/users/components/` bukan `src/components/users/`).
4. **Endpoint publik tersebar** di banyak controller (`BookingPublicController`, `ReceivableController`, `RentToRentController`, `PhysicalCheckController`) tanpa controller publik terpusat.
5. **Komponen UI belum seragam** — lihat `docs/component-inventory.md` §4 (duplikat signature, dialog tanpa `.custom-dialog`, badge inline, dll).
6. **Legacy import services** (`Legacy*ImportService` 5 buah) masih hidup di Services — konfirmasi apakah migrasi data sudah selesai sebelum dihapus.

## 5. Langkah Berikutnya (usulan untuk atur ulang alur)

1. Konfirmasikan daftar masalah §4 dan alur §3 mana yang mau diubah (user menyatakan lupa alur lama — validasi dulu alur ini sebelum refactor).
2. Prioritaskan konsolidasi komponen (§5 `component-inventory.md`) supaya halaman baru mengikuti design system.
3. Baru rombak alur bisnis per module (booking/payment/invoice paling berdampak; rent-to-rent punya rencana terpisah).
