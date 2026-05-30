# Rencana Rollout Mobile-Friendly — Per Halaman

Acuan aturan: `.agents/rules/mobile.md`. Prinsip: **jangan ubah desktop**, semua di `@media (max-width: 768px)` / `isMobile`.

Status: ✅ selesai · 🟡 ada pola, perlu disesuaikan ke aturan baru · 🔴 belum ada pola card mobile · ⚪ non-tabel (form/detail/public)

---

## Fase 0 — Fondasi global (`src/style.css`) · prasyarat semua halaman

Satu kali edit di blok `@media (max-width: 768px)` yang sudah ada. Memperbaiki banyak halaman sekaligus tanpa menyentuh desktop.

- [ ] Tab toggle: pastikan `.tab-toggle-container { width:100% }` + `.pill-toggle { min-width:max-content; flex-wrap:nowrap }` (aturan #2).
- [ ] Tombol primary 100% mobile: `.btn-pill.btn-primary`, `.filter-actions .btn-pill`, `.card-footer .btn-pill` (aturan #3).
- [ ] Kelas card kanonik `.mobile-card` + `.mobile-card-list` + border `--text-secondary` mobile-only (aturan #4).
- [ ] **Fix bug filter tinggi**: ubah `.filter-groups` mobile dari `flex-direction:column` → `grid 2 kolom`; field lebar/status `grid-column: 1 / -1`; `.filter-bar { flex:0 0 auto; height:auto }` (aturan #5).

Verifikasi: buka tiap halaman di lebar ≥769px → tidak ada perubahan.

---

## Fase 1 — Referensi (sudah sesuai, jadi acuan)

| Halaman | Status | Catatan |
|---|---|---|
| `finance/RentToRentListView.vue` | ✅ | Pola card mobile paling lengkap (acuan). |
| `finance/OperationalCostListView.vue` | 🟡 | Sudah ada card; **fix card filter tinggi** (Fase 0 menyelesaikannya). Verifikasi ulang. |

---

## Fase 2 — List sudah punya pola card (audit + samakan ke `.mobile-card`)

Tugas tiap halaman: ganti kelas card khusus → `.mobile-card`/`.mobile-card-list`, pastikan border `--text-secondary`, tombol aksi 100%, tab toggle & filter ikut Fase 0.

- [ ] 🟡 `bookings/BookingListView.vue`
- [ ] 🟡 `customers/CustomerListView.vue`
- [ ] 🟡 `drivers/DriverListView.vue`
- [ ] 🟡 `units/UnitListView.vue`
- [ ] 🟡 `finance/PaymentAccountMutationView.vue`
- [ ] 🟡 `master/PaymentAccountListView.vue`
- [ ] 🟡 `physical-checks/PhysicalCheckListView.vue`
- [ ] 🟡 `profile/MyRequestListView.vue`
- [ ] 🟡 `supervisor/SupervisorRequestListView.vue`
- [ ] 🟡 `reports/TransactionReportView.vue`

---

## Fase 3 — List BELUM punya card mobile (konversi tabel → card grid)

Tugas tiap halaman: tambah `isMobile` + `v-if="!isMobile"` (DataTable) / `v-else` (`.mobile-card-list`) sesuai aturan #6.

- [ ] 🔴 `finance/ReceivableListView.vue`
- [ ] 🔴 `finance/TransactionListView.vue`
- [ ] 🔴 `members/MemberListView.vue`
- [ ] 🔴 `users/UserListView.vue`
- [ ] 🔴 `rental-owners/RentalOwnerListView.vue`
- [ ] 🔴 `master/BranchListView.vue`
- [ ] 🔴 `master/CityListView.vue`
- [ ] 🔴 `master/CostTypeListView.vue`
- [ ] 🔴 `master/InvoiceTermsTemplateListView.vue`
- [ ] 🔴 `master/PricingPackageListView.vue`

---

## Fase 4 — Halaman non-tabel (form / detail / dashboard)

Tugas: grid → 1 kolom di mobile, tombol primary 100%, tab toggle/filter ikut Fase 0, card pakai border konsisten. Tidak ada konversi tabel (kecuali tabel tersemat).

- [ ] ⚪ `DashboardView.vue` — KPI/grid jadi 1 kolom, tab scroll.
- [ ] ⚪ `bookings/BookingCreateView.vue` — form panjang, grid → 1 kolom, sticky action.
- [ ] ⚪ `bookings/BookingDetailView.vue` — punya DataTable tersemat → konversi bagian itu ke card.
- [ ] ⚪ `members/MemberFormView.vue`
- [ ] ⚪ `members/MemberDetailView.vue`
- [ ] ⚪ `physical-checks/PhysicalCheckFormView.vue`
- [ ] ⚪ `profile/UserProfileView.vue`
- [ ] ⚪ `master/TenantSettingsView.vue`
- [ ] ⚪ `settings/RolePermissionView.vue`
- [ ] ⚪ `driver/DriverOperationalView.vue`
- [ ] ⚪ `driver/DriverTripHistoryView.vue`

---

## Fase 5 — Public / Auth (verifikasi ringan)

- [ ] ⚪ `LoginView.vue`
- [ ] ⚪ `public/PublicInvoiceView.vue`
- [ ] ⚪ `public/PublicRentToRentBillView.vue`

---

## Definition of Done (tiap halaman)

Lihat checklist 7 poin di `.agents/rules/mobile.md` §8. Inti: card grid di mobile, tombol primary 100%, card border `--text-secondary`, filter ringkas, **desktop tidak berubah**.
