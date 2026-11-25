---
name: drent-frontend
description: >
  Standar engineering Frontend DRENT untuk Vue 3 (Composition API `<script setup>`), PrimeVue 4, Tailwind CSS v4, Pinia, dan Capacitor 8: kebijakan Reuse-First, Hybrid 3-Layer File Mapping (`codegraph` + `grep` + `npm run build`), Thin Views & Dekomposisi Komponen, isolasi `src/api/` & `src/composables/`, Dual-View Desktop `DataTable` (`769–1365px` compact density) + Mobile `.mobile-card-list` (`<= 768px`), Pagination data banyak, Dialog & ConfirmDialog guard, Performa, dan Motion (`transitions-dev`).
  Wajib dibaca dan dipatuhi oleh `drent-frontend`, `drent-explorer`, dan `drent-qa`.
---

# DRENT Frontend Engineering Skill (`drent-frontend`)

Pedoman teknis wajib untuk seluruh pekerjaan frontend di `c:\Users\Salivs\Data\laragon\www\drent-vibe\frontend`. Skill ini melengkapi:
- [`.agents/rules/design.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/rules/design.md) — DrentDesign tokens & komponen desktop HD-compact.
- [`.agents/rules/mobile.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/rules/mobile.md) — Aturan mobile-friendly (`<= 768px`) tanpa merusak desktop.
- [`.claude/skills/compact-table-density/SKILL.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.claude/skills/compact-table-density/SKILL.md) — Tokenisasi ukuran font & lebar kolom `DataTable` pada tier laptop `769px–1365px`.
- [`.agents/skills/transitions-dev/SKILL.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/skills/transitions-dev/SKILL.md) — Resep CSS transitions & motion tokens.

---

## 1. Kebijakan *Reuse-First* & Hierarki Pencarian

Sebelum membuat komponen atau fungsi baru, periksa berurutan:
1. **PrimeVue 4 (`primevue/*`)** — Gunakan `DataTable`, `Dialog`, `ConfirmDialog`, `Toast`, `Select`, `DatePicker`, `InputNumber`, `ContextMenu`, `Tag`, dll. Jangan membuat ulang komponen dasar yang sudah tersedia di PrimeVue.
2. **Global CSS Classes & Tokens (`frontend/src/style.css`)** — Gunakan `.page-container`, `.page-header`, `.filter-bar.surface-card`, `.drent-datatable`, `.btn-pill.btn-primary/.btn-secondary`, `.app-card`, `.app-section-header`, `.custom-dialog`, `.app-dialog-button-*`, `.mobile-card-list`, `.mobile-card`, `.status-badge`, dan variabel `--page-bg`, `--surface-default`, `--card-bg`, `--surface-border`, `--text-primary`, `--text-secondary`, `--font-size-*`, `--space-*`.
3. **Shared Components & Composables (`frontend/src/components/`, `frontend/src/composables/`, `frontend/src/api/`)** — Gunakan kembali komponen dan composable eksisting (misal `BookingStatusBadge`, `SignaturePad`, `useBooking`, `usePermission`, dll.).
4. **Rule of 2:** Jika sebuah komponen/helper dipakai oleh `>= 2` halaman/modul, letakkan di `frontend/src/components/` atau `frontend/src/composables/` (hindari copy-paste antar view).

---

## 2. *Hybrid 3-Layer File Dependency Mapping*

Saat mengubah komponen, composable, store, atau endpoint API yang sudah ada, jangan hanya mengandalkan `codegraph` sendirian (tree-sitter bisa melewatkan template `.vue` dan dynamic import):
1. **Lapis 1 (Orientasi Cepat):** MCP `codegraph_explore` (`projectPath: c:\Users\Salivs\Data\laragon\www\drent-vibe`).
2. **Lapis 2 (Completeness Backstop):** `grep` (`Select-String` / `rg`) untuk tag `<NamaKomponen`, `import ... from`, composable `use*`, dan endpoint `/v1/...`.
3. **Lapis 3 (Validasi Build Mutlak):** `npm run build` di `frontend/` (wajib **0 error**).

---

## 3. Pakem Best Practice Front-End DRENT

### 1. Thin Views, Dekomposisi Komponen, & Isolasi Layer API/Composable
- Gunakan Vue 3 Composition API (`<script setup>`).
- **Isolasi HTTP:** Seluruh panggilan HTTP wajib berada di `frontend/src/api/<modul>.js` (memakai instance `axios.js` dan prefix `/v1/...`). **DILARANG** memanggil `axios` mentah di dalam file `.vue`.
- **Error Handling di Composable:** Tangkap error & kelola state `loading`/`error` di `frontend/src/composables/use<Modul>.js`, lalu tampilkan feedback melalui PrimeVue `Toast`.
- **Dekomposisi Fokus:** Jaga `*View.vue` tetap mudah dibaca. Ekstrak dialog tambah/edit, form kompleks, atau sub-panel yang punya tanggung jawab/state sendiri menjadi sub-komponen di `frontend/src/components/<modul>/` atau `views/<modul>/components/` (hindari over-splitting markup sepele `< 30` baris).

### 2. Dual-View Responsif (Desktop `DataTable` vs Mobile `.mobile-card-list`) & Compact Density
- **Desktop (`>= 769px`):**
  - Gunakan PrimeVue `DataTable` dengan class `.drent-datatable`, `scrollable`, dan `scrollHeight="flex"` pada list page (`.table-page-active`, `.list-tab-fill`, `.table-shell`).
  - Terapkan pola `compact-table-density` untuk breakpoint `769px–1365px`: hindari kelas Tailwind fixed-size (`text-xs`, `text-sm`, `text-[10px]`) dan `style="min-width: Xrem"` mentah di dalam `<Column>` — gunakan token `var(--font-size-*)` dan CSS variable lebar kolom lokal yang diskalakan `~0.8x` pada `@media (min-width: 769px) and (max-width: 1365px)`.
- **Mobile (`<= 768px` — patuhi `.agents/rules/mobile.md`):**
  - **Desktop adalah baseline yang sudah benar.** Semua perbaikan mobile HANYA di `@media (max-width: 768px)` atau percabangan `isMobile` (`window.innerWidth < 768`). Jangan ubah style desktop demi memperbaiki mobile.
  - Tabel list **WAJIB** dirender sebagai `.mobile-card-list` + `.mobile-card` (jangan render `DataTable` di mobile).
  - Filter di mobile menggunakan **grid 2 kolom** (`.filter-groups`), tombol primary 100%, tab toggle horizontal scroll.

### 3. Wajib Pagination untuk Data Banyak
- Setiap halaman daftar/tabel/grid yang menangani data banyak atau berpotensi terus bertambah **WAJIB** menerapkan pagination di **Desktop maupun Mobile** (utamakan server-side pagination `?page=&per_page=` yang membaca `meta.total` / `meta.last_page` dari backend).
- Reset `page` ke `1` setiap kali pencarian, filter, atau `per_page` berubah.
- **DILARANG** merender ratusan/ribuan baris sekaligus ke DOM.

### 4. Format Finansial IDR & 4 UI States
- **Angka Uang IDR:** Kirim nominal ke backend sebagai **integer penuh** tanpa desimal; tampilkan di UI dengan helper format Rupiah dan font monospace (`JetBrains Mono`) untuk kolom/ringkasan nominal agar rata tabular.
- **4 UI States:** Sediakan state **Loading**, **Empty**, **Error** (dengan pesan jelas/retry), dan **Data** pada setiap halaman asinkron.

### 5. Standar Dialog, Form Validation, & Konfirmasi (`ConfirmDialog`)
- Gunakan `class="custom-dialog"` dan tombol `.app-dialog-button.app-dialog-button-primary` / `-secondary` / `-danger` (hindari warna inline ad-hoc seperti `bg-blue-600`).
- Petakan error validasi HTTP `422` dari Laravel ke pesan field/Toast yang jelas; disable tombol submit dengan loading indicator saat request berjalan.
- **Konfirmasi & Dirty-Close Guard:** Gunakan PrimeVue `ConfirmDialog` (dan pola `requestCloseDialog()` saat form berstatus *dirty*). **DILARANG** memakai `window.confirm()` / `alert()` browser mentah.

### 6. State Management, Performa, & Motion
- **Pinia (`src/stores/`)** hanya untuk state global lintas halaman (`auth.js`, dll.); state filter/tabel/modal cukup di `ref()`/`reactive()` lokal atau composable.
- **Performa:** Gunakan *dynamic import* `() => import(...)` di `src/router/`, `:key` unik berbasis ID (bukan `index`) di `v-for`, `v-show` untuk toggle sering vs `v-if` untuk kondisional berat, serta `loading="lazy" decoding="async"` pada `<img>` galeri/foto.
- **Motion:** Jika menambah transisi/animasi UI, ikuti `.agents/skills/transitions-dev/SKILL.md` (hanya animasikan properti GPU `transform`/`opacity`, durasi 150–300ms, patuhi `prefers-reduced-motion`).

---

## 4. Checklist Sebelum Melapor ke Master Orchestrator

1. [ ] Komponen PrimeVue, class global `src/style.css`, komponen bersama, dan composable eksisting sudah di-*reuse*.
2. [ ] Seluruh panggilan HTTP melalui `src/api/` dan error handling di `src/composables/` (0 `axios` mentah di `.vue`).
3. [ ] Desktop `DataTable` mematuhi `design.md` + `compact-table-density` (`769–1365px`), dan Mobile (`<= 768px`) memakai `.mobile-card-list` sesuai `mobile.md` tanpa mengubah tampilan desktop.
4. [ ] Daftar data banyak menerapkan pagination (Desktop & Mobile) dan reset halaman 1 saat filter/search berubah.
5. [ ] Dialog memakai `.custom-dialog`, `.app-dialog-button-*`, dan `ConfirmDialog` (bebas `window.confirm()`).
6. [ ] `npm run build` di `frontend/` berhasil dengan **0 error**.
