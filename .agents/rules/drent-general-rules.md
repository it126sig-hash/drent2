# Aturan Umum Pengembangan DRENT (`drent-general-rules`)

Kamu WAJIB mengikuti aturan prioritas dan operasional berikut dalam setiap modifikasi kode di repository DRENT (melengkapi `.agents/rules/drent.md`, `design.md`, dan `mobile.md`):

## 1. Prioritas Utama Penyelesaian Masalah
Dalam setiap perbaikan bug atau penambahan fitur, selalu utamakan urutan berikut:
1. **Existing architecture**: Hormati arsitektur yang sudah berjalan — Backend Laravel (`Thin Api/V1 Controller -> FormRequest/Policy -> Service -> JsonResource`) dan Frontend Vue 3 (`views -> composables -> src/api` + PrimeVue + token `src/style.css`).
2. **Existing library & design system**: Gunakan package yang sudah terinstall (Laravel Sanctum, DomPDF, PrimeVue 4, Tailwind v4, Pinia, VueUse, `date-fns`, `xlsx`) dan class/token global `src/style.css` sebelum mengusulkan library baru.
3. **Existing helper/component/composable**: Gunakan kembali (`reuse`) komponen di `frontend/src/components/`, composable di `frontend/src/composables/`, dan helper/trait di `backend/app/` yang sudah tersedia.
4. **Existing service/query/endpoint**: Manfaatkan method Service, Eloquent scope, dan endpoint `/api/v1/*` yang sudah ada.
5. **Minimal perubahan (*Surgical Changes*)**: Ubah seminimal mungkin secara presisi; jangan merombak kode sekitar yang tidak rusak.
6. **Maintainability, Security & Performance**: Pastikan isolasi `branch_id`, kalkulasi uang IDR integer di backend, transaksi DB untuk mutasi multi-tabel, pagination untuk data banyak, serta tidak ada N+1 query atau regresi tampilan desktop/mobile.

## 2. Pemetaan Skills Wajib Berdasarkan Peran Agen
- **`drent-orchestrator`**: Wajib dibaca oleh agen utama (`Master Orchestrator`) untuk mengoordinasikan 5 subagent spesialis (`drent-explorer`, `drent-engineer`, `drent-frontend`, `drent-qa`, `drent-docs`).
- **`codegraph`**: Wajib digunakan oleh **`drent-explorer`** dan **`drent-qa`** bersama `grep` (Hybrid Mapping) untuk memetakan *callers*, *blast radius*, dan keterhubungan file.
- **`drent-frontend` + `design.md` + `mobile.md` + `compact-table-density` + `transitions-dev`**: Wajib digunakan oleh **`drent-frontend`** saat membuat/mengubah halaman Vue, tabel, dialog, filter, mobile card grid, atau animasi UI.
- **`drent-qa` & `drent-local-testing`**: Wajib digunakan oleh **`drent-qa`** saat memvalidasi pasca-implementasi dan menjalankan pengujian browser Playwright.

## 3. Standar Pengujian & Verifikasi Lokal
- **Backend CLI**: Jalankan `php -l` pada file PHP yang diubah dan `php artisan test` di `backend/` (serta `php artisan route:list` jika rute berubah).
- **Frontend Build**: Jalankan `npm run build` di `frontend/` (wajib 0 error).
- **Browser E2E (`drent-qa`)**: Baca `.agents/skills/drent-local-testing/SKILL.md` sebelum membuka browser Playwright ke `http://localhost:5173` (API: `http://localhost/drent-vibe/backend/public/api`), uji desktop & mobile (`390x844`), dan pastikan **0 console/network error (500/404)**.

## 4. Batas Konfirmasi Domain
Tetap patuhi Bagian 12 di `.agents/rules/drent.md`: **berhenti dan tanya user** jika tugas menyentuh strategi arsip final (soft-delete vs tabel terpisah vs `is_archived`), fitur approval perubahan invoice, atau tracking jam kerja driver non-tetap.
