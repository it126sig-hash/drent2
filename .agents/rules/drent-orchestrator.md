# Aturan Master Orchestrator & Pembagian Tugas Agen (DRENT Orchestrator Rule)

Aturan ini **WAJIB** dipatuhi di setiap sesi pengembangan di DRENT (`FEATURE`, `BUGFIX`, `REFACTOR`, `UI_REPORT`, `MOBILE_CAPACITOR`).
Sebelum memulai orkestrasi, **baca penuh `.agents/skills/drent-orchestrator/SKILL.md` dan `.agents/skills/drent-orchestrator/references/subagent-definitions.md`**.

> **Pengecualian Tugas Trivial:** Untuk pertanyaan murni (tanpa edit kode) atau perbaikan 1–2 baris tipografi/copy sepele tanpa risiko regresi, agen boleh merespons ringkas tanpa membuka siklus penuh; namun untuk seluruh pengembangan fitur, bugfix logika/UI, refaktor, perubahan DB/API, atau perubahan layout, siklus orkestrasi ini **WAJIB**.

---

## 1. Kontrak Pembagian Tanggung Jawab (1 Agen = 1 Tanggung Jawab)

Agen utama **DILARANG** mengerjakan seluruh fase sendirian atau berhenti setelah kode ditulis tanpa QA & finalisasi dokumen. Setiap agen bertanggung jawab penuh atas perannya:

1. **Master Orchestrator (Agen Utama — Manajer & Gatekeeper):**
   - Mengklasifikasikan mode tugas (`FEATURE`, `BUGFIX`, `REFACTOR`, `UI_REPORT`, `MOBILE_CAPACITOR`).
   - **WAJIB** memecah setiap pekerjaan di dalam Plan menjadi **beberapa fase eksekusi berurutan** (`Fase A`, `Fase B`, `Fase C`, `Fase D`, ...) lengkap dengan lingkup, agen, subset file, dan *exit criteria*, lalu mengeksekusinya **satu fase per tahap** (dilarang menggabung semua fase sekaligus).
   - **WAJIB** menyusun & memvalidasi **Plan dalam bentuk File Artefak Markdown nyata** (`<Artifact Directory Path>/implementation_plan.md` di Fase 1 atau `bugfix_plan.md` untuk mode `BUGFIX` / Fase 3B) via `write_file` dengan `ArtifactMetadata: { UserFacing: true, RequestFeedback: true, Summary: "..." }` (untuk Fase 3B internal dalam scope yang sudah disetujui boleh `RequestFeedback: false`, tetap `UserFacing: true`), serta menetapkan path target dokumen kerja `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md`.
   - **Protokol Plan Mode Read-Only (*Artifact-Only Handoff*):** Jika `write_file`/`edit_file` ditolak karena sesi berada pada Plan Mode read-only, **DILARANG** fallback membuang seluruh plan ke teks chat. Wajib panggil `exit_plan_mode` dengan instruksi tunggal khusus *Execution Turn* untuk **HANYA** menulis/memperbarui file artefak plan (`UserFacing: true`, `RequestFeedback: true`), mengirim tautan + 2–4 poin review singkat, lalu **BERHENTI** menunggu persetujuan User (**DILARANG** menyentuh kode workspace, `docs/`, atau memanggil subagent eksekutor pada turn tersebut).
   - Mengarahkan User mereview/mengomentari artefak plan (sorot 2–4 poin kunci, tanpa menyalin ulang seluruh isi plan di chat); jika ada komentar, revisi file artefak yang sama sebelum melanjut ke Fase 2.
   - **WAJIB** menginisialisasi `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md` segera setelah Plan disetujui User dan memperbarui tabel fase + log ber-timestamp setiap kali satu fase eksekusi (`Fase A`, `Fase B`, ...) atau putaran QA/debugging selesai.
   - Mendefinisikan (`define_subagent`) dan mendelegasikan tugas (`invoke_subagent`) ke **5 Sub-Agent Spesialis**.
   - Menyerahkan Laporan Akhir Orkestrasi ke User hanya setelah seluruh fase tuntas, Sertifikat QA berstatus `PASS`, dan `drent-docs` selesai.
   - *Larangan:* Dilarang mengeksekusi seluruh pekerjaan sekaligus tanpa pembagian fase & log kerja; dilarang menulis plan panjang hanya di teks chat tanpa file Artefak; dilarang menduplikasi isi artefak plan di chat; dilarang melakukan eksplorasi ribuan baris sendiri; dilarang menulis kode produksi (`backend/`, `frontend/`) sendiri (pengecualian penulisan oleh Master Orchestrator hanyalah file Artefak Plan di `<Artifact Directory Path>` dan dokumen kerja `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md` pasca-approval); dilarang menjalankan tes browser sendiri.

2. **`drent-explorer` (Sub-Agent Fase 1 & RCA — 100% Read-Only):**
   - Mengeksplorasi `backend/` & `frontend/`, memetakan *callers* & *blast radius* menggunakan **Hybrid Mapping (`codegraph_explore` + `grep`)**, mengaudit komponen/composable/service yang wajib di-*reuse*, memisahkan *File Inventory* Backend vs Frontend, melakukan RCA saat terjadi bug, dan mengusulkan urutan fase eksekusi.
   - *Larangan:* **100% Read-Only (`EnableWriteTools: false`).** Dilarang mengubah file apapun.

3. **`drent-engineer` (Sub-Agent Fase 2 & Fase 3B Backend — Backend Laravel Executor):**
   - Mengeksekusi kode **Backend Laravel** (`backend/app/`, `backend/database/`, `backend/routes/api.php`, `backend/tests/`) secara *surgical* sesuai Plan.
   - **WAJIB mematuhi `.agents/rules/drent.md`:** Thin Controller di `App\Http\Controllers\Api\V1\`, validasi wajib di `FormRequest`, respons wajib via `JsonResource`/`ResourceCollection` (envelope JSON murni, tidak return Eloquent Model langsung), business logic di `Service`, otorisasi via `Policy` (`$this->authorize()`), seluruh nominal uang dalam **IDR integer penuh** & dihitung ulang di backend, mutasi multi-tabel dibungkus `DB::transaction()`, isolasi `branch_id` pada semua query operasional, `SoftDeletes` + komentar `TODO` strategi arsip, dan pagination (`page`/`per_page` + `meta`) untuk endpoint daftar data banyak.
   - Menjalankan `php -l` pada file PHP yang disentuh dan `php artisan test` yang relevan (serta menambah/memperbarui Feature/Unit test untuk logika kritis/bugfix).
   - *Larangan:* Dilarang mengubah file di `frontend/src/`, dilarang menyentuh file di luar *File Inventory* Backend, dilarang mengklaim tes E2E browser lulus sendiri.

4. **`drent-frontend` (Sub-Agent Fase 2 & Fase 3B Frontend — Vue 3 + PrimeVue + Capacitor Specialist):**
   - Mengeksekusi perubahan **Frontend** (`frontend/src/`) secara *surgical* sesuai Plan.
   - **WAJIB mematuhi `.agents/skills/drent-frontend/SKILL.md`, `design.md`, `mobile.md`, `compact-table-density`, & `transitions-dev`:** Kebijakan *Reuse-First* (PrimeVue $\rightarrow$ `src/components/` $\rightarrow$ `src/composables/` $\rightarrow$ `src/api/`), Thin Views & dekomposisi komponen fokus, seluruh panggilan HTTP hanya di `src/api/` + error handling di `src/composables/` (dilarang `axios` mentah di `.vue`), Dual-View Desktop PrimeVue `DataTable` (`.drent-datatable`, compact density `769–1365px`) + Mobile `.mobile-card-list` / `.mobile-card` (`<= 768px`, tanpa merusak desktop), wajib pagination untuk data banyak, dialog `.custom-dialog` + `ConfirmDialog` & dirty-close guard (bebas `window.confirm()`), serta menjalankan `npm run build` hingga **0 error**.
   - *Larangan:* Dilarang mengubah file di `backend/`, dilarang mengubah gaya desktop saat memperbaiki mobile (`mobile.md`), dilarang mengklaim tes E2E browser lulus sendiri.

5. **`drent-qa` (Sub-Agent Fase 3 — Independent QA & E2E Auditor):**
   - Membaca `.agents/skills/drent-local-testing/SKILL.md`, `.agents/skills/drent-qa/SKILL.md`, dan `.agents/skills/drent-frontend/SKILL.md`.
   - Melakukan **Hybrid Callers Audit (`codegraph_explore` + `grep`)**, verifikasi CLI (`php artisan test`, `php artisan route:list`, `npm run build`), serta pengujian E2E nyata via Playwright MCP di `http://localhost:5173` (Desktop & Mobile `390x844`, 0 console/500/404 error, cek kepatuhan `design.md` & `mobile.md` + pagination + modal guard).
   - Mengeluarkan **Sertifikat Verifikasi QA (`PASS` atau `FAIL` + RCA & klasifikasi domain bug: Backend `drent-engineer` vs Frontend `drent-frontend`)**.
   - *Larangan:* **Auditor Independen.** Dilarang keras memanggil `write_file`/`edit_file` untuk mengubah kode aplikasi maupun dokumentasi!

6. **`drent-docs` (Sub-Agent Fase 4 — Docs & Ledger Custodian — Wajib Semua Mode Pasca-PASS):**
   - Hanya dipanggil **SETELAH** `drent-qa` menerbitkan sertifikat `PASS`.
   - **WAJIB SELALU** mengaudit & memfinalisasi `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md` (`Status Keseluruhan: COMPLETED`, semua fase `DONE`, entri log Fase 4 ber-timestamp, serta `## 4. Ringkasan File Final` sinkron 100% dengan `git status --short`; atau membuat dari template standar jika terlewat).
   - Menyinkronkan dokumen terkait di `docs/` (misalnya `docs/USER_MANUAL.md`, `docs/mobile-friendly-rollout.md`, atau dokumen fitur terkait) jika ada perubahan alur/kontrak publik yang terdampak, serta memverifikasi `git status --short` bersih & terisolasi.
   - *Larangan:* Dilarang mengubah kode di `backend/` atau `frontend/`.

---

## 2. Siklus Wajib 4 Fase

```text
[FASE 1: Eksplorasi & Hybrid Mapping (`drent-explorer`) + Plan Artefak (`Fase A, B, C, ...` + Path Work Doc) oleh `Orchestrator`] ──► (Review/Komen & Approval User)
                                      ↓
[FASE 2: Inisialisasi `docs/YYYY-MM-DD-<slug>.md` ──► Eksekusi Bertahap `Fase A` → `Fase B` → ... (Backend `drent-engineer` / Frontend `drent-frontend`) + Log per Fase]
                                      ↓
[FASE 3: Inspeksi QA Independen (`drent-qa` — Dilarang Edit Kode) + Catat Hasil ke Work Doc]
                                      ↓
                     (Status Sertifikat Verifikasi QA?)
                     ├── FAIL ──► [FASE 3B: Bugfix Plan Artefak + Surgical Fix (`drent-engineer` / `drent-frontend`) + Log Debugging] ──► (Ulangi FASE 3)
                     └── PASS ──► [FASE 4: Finalisasi `docs/YYYY-MM-DD-<slug>.md` & Sinkronisasi Docs (`drent-docs` — Wajib Semua Mode)] ──► [Laporan Akhir ke User]
```

---

## 3. Aturan Wajib Pembagian Fase & Dokumen Kerja (`docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md`)

1. **Pembagian Fase Wajib di Plan:** Pecah setiap pekerjaan menjadi fase eksekusi berurutan (`Fase A`, `Fase B`, `Fase C`, `Fase D`, ...) di Plan Artefak (minimal 3 fase untuk tugas terfokus/single-layer; `Fase A`–`Fase D` untuk full-stack/kompleks), lengkap dengan lingkup, agen, subset file, dan *exit criteria*.
2. **Eksekusi Satu Fase per Tahap:** Jangan memulai fase berikutnya sebelum *exit criteria* fase aktif lulus dan progresnya direkam ke dokumen kerja.
3. **Konvensi Nama Dokumen Kerja:** Wajib memakai format `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md` (tanggal lokal `YYYY-MM-DD` + slug *kebab-case*; pekerjaan lanjutan lintas hari pada tugas yang sama melanjutkan file tersebut).
4. **Waktu Penulisan & Kepemilikan:** Path dideklarasikan di Plan (dilarang membuat file di `docs/` sebelum approval User), diinisialisasi oleh `Master Orchestrator` tepat di awal Fase 2, diperbarui setiap selesai fase/QA/debugging, dan difinalisasi oleh `drent-docs` pada Fase 4.
