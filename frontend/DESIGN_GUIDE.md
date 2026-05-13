# DRENT Frontend Design Guide

Halaman acuan: `src/views/bookings/BookingDetailView.vue`.

## Prinsip Umum

- Gunakan token global dari `src/style.css`: `--page-bg`, `--surface-default`, `--card-bg`, `--surface-border`, `--text-primary`, `--text-secondary`, `--positive`, `--negative`, `--info-cyan`, `--warning`.
- UI utama bersifat operasional: padat, mudah discan, dan tidak memakai layout marketing.
- Hindari warna Tailwind langsung untuk aksi utama. Pakai class bersama seperti `btn-pill`, `detail-primary-action`, `detail-secondary-action`, atau `app-dialog-button-*`.
- Radius default untuk card dan panel adalah `var(--radius-default)`. Tombol aksi utama memakai pill radius.

## Page Layout

- Root halaman:
  - Background: `var(--page-bg)`.
  - Padding: `var(--space-2xl)`, turun ke `var(--space-lg)` di mobile.
- Header halaman:
  - Pakai pola `.detail-page-header`.
  - Kiri: tombol back + title + metadata/status.
  - Kanan: action bar dengan tombol primary/secondary.
- Konten detail:
  - Gunakan grid 1 kolom di mobile, 2 kolom di desktop.
  - Kolom kanan boleh sticky untuk ringkasan finansial/status.

## Cards dan Section

- Card standar:
  - Class: `.app-card`.
  - Border: `1px solid var(--surface-border)`.
  - Background: `var(--surface-default)`.
  - Shadow: `var(--shadow-tile)`.
- Header section:
  - Class: `.app-section-header`.
  - Tinggi minimal 54px, border bawah, icon kecil 32-36px.
  - Judul pakai 14px Sora semibold.
- Panel kecil:
  - Class: `.app-muted-panel`.
  - Dipakai untuk read-only info, summary, dan hint pendek.

## Tombol

- Tombol aksi halaman:
  - Primary: `.detail-primary-action`.
  - Secondary: `.detail-secondary-action`.
- Tombol dialog dan konfirmasi:
  - Default submit: `.app-dialog-button app-dialog-button-primary`.
  - Secondary/cancel pada ConfirmDialog: `.app-dialog-button app-dialog-button-secondary`.
  - Destructive: `.app-dialog-button app-dialog-button-danger`.
  - Info/warning flow boleh memakai `.app-dialog-button-info` atau `.app-dialog-button-warning`, tetapi warnanya tetap mengikuti primary agar konsisten.
- Hindari inline class seperti `bg-blue-600`, `bg-emerald-600`, `bg-sky-700` untuk tombol modal.

## Dialog

- Dialog form memakai `class="custom-dialog"`.
- Header/footer dialog harus punya border separator.
- Footer selalu rata kanan, gap 8px, tombol cancel dulu lalu submit.
- Jika modal ditutup saat ada potensi perubahan, gunakan `requestCloseDialog()` agar ConfirmDialog konsisten.

## Tabel dan Data Detail

- Tabel kecil di dalam card memakai `.custom-mini-table`.
- Header tabel uppercase 11px, background `var(--card-bg)`.
- Angka finansial gunakan font mono bila menjadi nilai summary.
- Status/tags memakai `BookingStatusBadge` atau PrimeVue `Tag` dengan warna status yang sudah ada.

## Form

- Label form: 11-12px semibold, warna `var(--text-secondary)`.
- Group form di dialog memakai `fieldset` dengan border `var(--surface-border)` dan background muted.
- Input wajib memiliki loading/disabled state saat action async berjalan.

## Finansial Booking

- Untuk mode `non_all_in`, tagihan = harga sewa setelah diskon mobil + biaya/diskon operasional.
- Untuk mode `all_in`, tagihan = harga all-in x lama sewa + item diskon saja.
- Item biaya operasional pada mode `all_in` tetap ditampilkan sebagai catatan internal, tetapi tidak masuk kalkulasi tagihan.

## Responsive

- Di bawah 768px:
  - Header berubah menjadi kolom.
  - Action bar full width dan tombol bisa wrap dua kolom.
  - Padding card dan section memakai `var(--space-lg)`.
- Hindari text overflow pada tombol dan kartu; gunakan wrap/truncate sesuai kebutuhan.
