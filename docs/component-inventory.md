# Inventaris Komponen Frontend & Rencana Reuse — DRENT

> Tujuan: semua komponen di-*reuse* agar style tiap halaman konsisten (DrentDesign: light-first, HD-compact).
> Sumber kebenaran style: `frontend/src/style.css` (token CSS) + `.agents/rules/design.md`.
> Disusun dari hasil eksplorasi read-only `drent2/frontend/src` (2026-11-25).

## 1. Ringkasan Struktur

| Kategori | Jumlah | Catatan |
|---|---|---|
| `components/` (root) | 4 | AppLayout, HelloWorld (boilerplate, tak terpakai), InvoiceTermsEditor, SignatureCanvas |
| `components/` (10 subfolder domain) | 19 | bookings 2, branches 1, customers 1, drivers 2, invoices 4, members 2, physical-checks 1, rental-owners 1, units 2, users 2 |
| `views/` | 45 | termasuk `users/components/UserPermissionOverrideDialog.vue` (lokasi salah) |
| `composables/` | 24 | pola `use<Entity>.js` konsisten |
| `api/` | 31 | `axios.js` + 30 modul per resource — disiplin, semua HTTP lewat sini |
| `stores/` (Pinia) | 1 | hanya `auth.js` |

## 2. Kelas Design System (di `src/style.css`)

`.drent-datatable`, `.mobile-card-list`, `.mobile-card`, `.custom-dialog`, `.status-badge`, `.page-container`, `.page-header`, `.back-button`, `.tab-toggle-container`, `.pill-toggle`, `.btn-pill`, `.btn-primary/secondary`, `.app-dialog-button*`, `.font-headline`, `.font-mono-numeric`, override global `.p-component`/`.p-inputtext`.

Token CSS lengkap: surface (`--page-bg`, `--card-bg`, `--surface-border`), text 4 tingkat, status (`--positive/negative/warning/info-cyan`), elevation 3 shadow, tipografi Sora/DM Sans/JetBrains Mono, spacing 7 step, radius 5 step.

**Adopsi saat ini:** 24/45 views pakai `.mobile-card-list`; 28 pakai `<DataTable`; 26 pakai `.drent-datatable`. Belum patuh: `FleetCardListView`, `PublicBookingInvoiceListView`.

## 3. Inventaris Komponen (usage = jumlah file lain yang meng-import)

| Komponen | Kategori | PrimeVue yang dipakai | Usage | Status |
|---|---|---|---|---|
| `AppLayout.vue` | layout shell | Toast | 1 | Core |
| `bookings/BookingStatusBadge.vue` | badge status | — | 8 | **Core reusable** |
| `bookings/BookingCalendar.vue` | calendar | — | 1 | One-off |
| `invoices/GenerateInvoiceDialog.vue` | dialog aksi | Dialog, Dropdown, DatePicker, InputNumber | 2 | Reusable |
| `invoices/InvoiceHistoryDialog.vue` | dialog tampilan | Dialog, Tag, ProgressBar | 2 | Reusable |
| `invoices/InvoicePaymentDialog.vue` | dialog aksi | Dialog, Dropdown, DatePicker, InputNumber | 2 | Reusable |
| `invoices/MergeInvoiceDialog.vue` | dialog aksi | Dialog, Dropdown, DatePicker | 1 | Reusable |
| `branches/BranchFormDialog.vue` | form-dialog | Dialog, Dropdown, InputText, Textarea | 1 | Pola form-dialog |
| `customers/CustomerFormDialog.vue` | form-dialog | Dialog, Button, InputText, Dropdown, Textarea, Checkbox, Message | 1 | Pola form-dialog |
| `drivers/DriverFormDialog.vue` | form-dialog | Dialog, Button, InputText, Dropdown, Textarea, SelectButton | 1 | Pola form-dialog |
| `drivers/BalanceDialog.vue` | dialog aksi | Dialog, Button, InputNumber, InputText | 1 | One-off |
| `units/UnitFormDialog.vue` | form-dialog | Dialog, Button, InputText, InputNumber, Dropdown, Textarea, AutoComplete | 1 | Pola form-dialog |
| `units/UnitPhotoManager.vue` | manager | Button, InputText | 1 | One-off |
| `users/UserFormDialog.vue` | form-dialog | Dialog, Button, InputText, Dropdown, ToggleSwitch | 1 | Pola form-dialog |
| `users/ResetPasswordDialog.vue` | dialog aksi | Dialog, Button, InputText, Message | 1 | **Tanpa `.custom-dialog`** |
| `members/MemberExtendDialog.vue` | dialog aksi | Dialog, Button, Textarea, DatePicker | 1 | **Tanpa `.custom-dialog`** |
| `members/MemberStatusDialog.vue` | dialog aksi | Dialog, Button, Dropdown | 1 | **Tanpa `.custom-dialog`** |
| `rental-owners/RentalOwnerFormDialog.vue` | form-dialog | Dialog, Button, InputText, Textarea, **InputSwitch (deprecated PV4)** | 1 | Perlu migrasi ke `ToggleSwitch` |
| `physical-checks/SignaturePad.vue` | input | Button | 1 | **Duplikat SignatureCanvas** |
| `SignatureCanvas.vue` | input | — | 1 | **Duplikat SignaturePad** |
| `InvoiceTermsEditor.vue` | editor | — | 1 | Reusable |
| `HelloWorld.vue` | boilerplate | — | 0 | Dead code — hapus |
| `views/users/components/UserPermissionOverrideDialog.vue` | dialog | Dialog | 1 | **Lokasi salah** (harusnya `components/users/`), tanpa `.custom-dialog` |

## 4. Duplikasi & Inkonsistensi (target dirapikan)

1. **Dua komponen signature** — `SignatureCanvas.vue` vs `physical-checks/SignaturePad.vue`. → gabungkan jadi satu.
2. **Dialog tanpa `.custom-dialog`** — `MemberExtendDialog`, `MemberStatusDialog`, `ResetPasswordDialog`, `UserPermissionOverrideDialog`.
3. **25 view mendefinisikan dialog inline** (import `<Dialog>` langsung: BookingDetailView, BookingCreateView, semua report views) — pola `*FormDialog` tidak dipakai.
4. **Badge status inline** — 17+ file punya logika `statusBadge`/`<span class="status-badge">` sendiri padahal ada `BookingStatusBadge`.
5. **Raw axios** — hanya 1 kasus: `views/members/MemberDetailView.vue:13`.
6. **Inline style masif** — ~230+ `style="min-width:…"` di kolom DataTable, 29× `style="width: 460/480px"` untuk dialog, `style="height: 4px"`.
7. **Migrasi PrimeVue 4 belum seragam** — `InputSwitch` (deprecated) vs `ToggleSwitch`; `Dropdown` vs `Select`.
8. **Lokasi file** — `UserPermissionOverrideDialog` di `views/users/components/`; tidak ada folder `layouts/` (AppLayout di `components/`).
9. **Dead code** — `HelloWorld.vue`.
10. **Token belum terhubung ke Tailwind** — hanya `--color-primary` di-`@theme`; token `:root` lain hanya terpakai via CSS manual. Ada juga token font-size redundan (px & rem setara).

## 5. Core Reusable Set (rencana konsolidasi)

Urutan prioritas agar style semua halaman konsisten:

| # | Komponen | Menggantikan | Dampak |
|---|---|---|---|
| 1 | `DrentDialog` (wrapper `<Dialog>` + `.custom-dialog` + dirty-close guard built-in) | 25 dialog inline | Tertinggi |
| 2 | `StatusBadge` generik (map status → token warna) | semua badge inline | Tinggi |
| 3 | `PageHeader` + `FilterBar` (slot actions/filter) | pola `page-header` + `min-width` inline | Tinggi |
| 4 | `FormDialog` base (props `visible`/`initialData`, emit `save`/`cancel`) | 6× boilerplate `*FormDialog` | Sedang |
| 5 | `SignaturePad` (merge 2 versi) | 2 komponen signature | Sedang |
| 6 | `PillToggle` (komponen untuk `tab-toggle-container`/`pill-toggle`) | copy-paste CSS antar view | Sedang |
| 7 | Rapikan lokasi & dead code: `AppLayout` → `layouts/`, `UserPermissionOverrideDialog` → `components/users/`, hapus `HelloWorld.vue`, `InputSwitch` → `ToggleSwitch` | — | Perawatan |

Aturan reuse (sudah ada di `.agents/skills/drent-frontend/SKILL.md`, ditegakkan): PrimeVue → `src/components/` → `src/composables/` → `src/api/`; dilarang axios mentah di `.vue`; dual-view desktop `DataTable` (`.drent-datatable`) + mobile `.mobile-card-list`/`.mobile-card` (≤768px).

## 6. Kesenjangan Dokumentasi

`docs/design.md` belum ada di repo (aturan design hanya di `.agents/rules/design.md`). Sebaiknya dibuat mendokumentasikan token `style.css` sebagai sumber kebenaran design system untuk developer frontend.
