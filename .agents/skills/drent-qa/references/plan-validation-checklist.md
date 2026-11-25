# Checklist Validasi Plan Agen (DRENT Plan Gatekeeper)

Gunakan checklist ini untuk memvalidasi `implementation_plan.md` atau `bugfix_plan.md` SEBELUM mengeksekusi perubahan kode di DRENT.

---

## 1. Kepatuhan Prinsip Karpathy & Orkestrasi — [Wajib Semua Mode]

| Kriteria | Status | Panduan Evaluasi |
|---|:---:|---|
| **No Speculative Code** | [ ] | Tidak ada fitur/abstraksi tambahan yang tidak diminta user. |
| **Minimum Necessary Diff** | [ ] | Hanya menyentuh file yang esensial; tidak merefaktor kode sekitar yang tidak rusak. |
| **Clear Success Criteria** | [ ] | Setiap langkah punya kriteria verifikasi eksplisit (`[Step] → verify: [check]`). |
| **Phased Execution Breakdown** | [ ] | Pekerjaan dipecah ke fase berurutan (`Fase A`, `B`, `C`, ...) lengkap dengan agen, subset file, dan *exit criteria*. |
| **Work Doc Path Declared** | [ ] | Path `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md` dideklarasikan (belum dibuat sebelum approval User). |
| **Pending Confirmation Check** | [ ] | Tidak melanggar Bagian 12 `.agents/rules/drent.md` (strategi arsip, approval perubahan invoice, tracking jam kerja driver non-tetap). |

---

## 2. Standar Arsitektur Backend Laravel — [Wajib Jika Sentuh `backend/`]

| Kriteria | Status | Panduan Evaluasi |
|---|:---:|---|
| **Thin Controller (`Api/V1/`)** | [ ] | Controller hanya: FormRequest $\rightarrow$ `$this->authorize()` $\rightarrow$ Service $\rightarrow$ JsonResource. |
| **FormRequest & Policy** | [ ] | Validasi di `Store*Request`/`Update*Request` (bukan `$request->validate()`), otorisasi via Policy (bukan hardcode role). |
| **API Resource** | [ ] | Semua respons dibungkus `JsonResource`/`ResourceCollection` (tidak return Model langsung). |
| **IDR Integer & Backend Calc** | [ ] | **KRITIS:** Semua nominal uang berupa **integer rupiah penuh** dan dihitung ulang di backend (tidak mempercayai kalkulasi frontend). |
| **Database Transactions** | [ ] | **KRITIS:** Mutasi multi-tabel (2+ write query) dibungkus `DB::transaction()`. |
| **Branch Isolation & SoftDeletes** | [ ] | Query operasional di-scope `branch_id`; model baru memakai `SoftDeletes` + komentar `TODO` arsip. |
| **Pagination** | [ ] | Endpoint daftar data banyak mendukung `?page=&per_page=` dan mengembalikan `meta`. |

---

## 3. Standar Frontend Vue 3 + PrimeVue 4 — [Wajib Jika Sentuh `frontend/`]

| Kriteria | Status | Panduan Evaluasi |
|---|:---:|---|
| **Reuse-First** | [ ] | Mengutamakan PrimeVue, class global `src/style.css`, dan komponen/composable/API yang sudah ada. |
| **API & Composable Isolation** | [ ] | Panggilan HTTP hanya di `src/api/*.js` (prefix `/v1`), error handling & Toast di composable (0 `axios` mentah di `.vue`). |
| **Desktop & Compact Density** | [ ] | Mematuhi `design.md` (token warna, Sora/DM Sans/JetBrains Mono) dan `compact-table-density` (`769–1365px`) untuk `DataTable`. |
| **Mobile-Friendly (`<= 768px`)** | [ ] | Mematuhi `mobile.md`: `.mobile-card-list`/`.mobile-card`, filter grid 2 kolom, tombol primary 100%, **tanpa mengubah tampilan desktop**. |
| **Pagination & Dialog Guard** | [ ] | Pagination aktif untuk daftar data banyak; dialog memakai `.custom-dialog`, `.app-dialog-button-*`, dan `ConfirmDialog` (bebas `window.confirm()`). |

---

## 4. Strategi Verifikasi (`drent-qa`) — [Wajib Semua Mode]

| Kriteria | Status | Panduan Evaluasi |
|---|:---:|---|
| **Hybrid Blast Radius Checked** | [ ] | Callers & keterhubungan file sudah dipetakan dengan `codegraph_explore` + `grep`. |
| **Automated CLI Checks** | [ ] | Mencantumkan `php artisan test` / `php -l` (backend) dan `npm run build` (frontend). |
| **Playwright E2E Scenario** | [ ] | Mencantumkan rute UI, role login (`drent-local-testing`), skenario uji Desktop (`1366x768`/`1280x800`) dan Mobile (`390x844`). |

---

## Keputusan Plan
- **`APPROVED`**: Semua kriteria relevan terpenuhi (`[x]`). Siap dipresentasikan ke User.
- **`NEEDS_REVISION`**: Ada kriteria krusial belum terpenuhi. Perbaiki plan sebelum meminta approval.
