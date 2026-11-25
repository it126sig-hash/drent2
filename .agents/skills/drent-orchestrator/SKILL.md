---
name: drent-orchestrator
description: >
  Master Orchestrator untuk mengoordinasikan seluruh alur kerja pengembangan di DRENT (monorepo Laravel 13 + Vue 3/PrimeVue 4/Tailwind v4/Capacitor 8) dengan pembagian tugas tegas ke 5 Sub-Agent Spesialis (`drent-explorer`, `drent-engineer`, `drent-frontend`, `drent-qa`, `drent-docs`).
  Mendukung 5 mode tugas: Fitur Baru (FEATURE), Perbaikan Bug (BUGFIX), Refaktor (REFACTOR), UI/Laporan/Mobile (UI_REPORT), serta Integrasi Mobile Native (MOBILE_CAPACITOR).
  Selalu membuat Plan Artefak Markdown nyata, membagi pekerjaan menjadi fase eksekusi berurutan (`Fase A`, `Fase B`, ...), dan merekam progres serta debugging di `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md`.
---

# DRENT Master Orchestrator Playbook (Multi-Agent Architecture)

Playbook ini memandu agen utama bertindak sebagai **Master Orchestrator** yang mengoordinasikan **5 Sub-Agent Spesialis** di repository `c:\Users\Salivs\Data\laragon\www\drent-vibe`. Setiap agen memiliki **satu tanggung jawab spesifik (*Single Responsibility*)** dan batasan ketat (*guardrails*) agar Backend Laravel, Frontend Vue/PrimeVue, QA Independen, dan Dokumentasi berjalan rapi tanpa tumpang tindih.

---

## 1. Pembagian Tugas & Tanggung Jawab per Agen (Strict Separation of Duties)

| Agen | Peran & Fase | Tanggung Jawab Utama (KPI) | Larangan Keras (*Guardrails*) |
|---|---|---|---|
| **0. Master Orchestrator** *(Agen Utama)* | Manajer Siklus Hidup & Gatekeeper *(Fase 1–4)* | - Klasifikasi mode tugas (`FEATURE`, `BUGFIX`, `REFACTOR`, `UI_REPORT`, `MOBILE_CAPACITOR`)<br>- Pecah pekerjaan menjadi **fase eksekusi berurutan** (`Fase A`, `Fase B`, `Fase C`, `Fase D`, ...) di Plan, eksekusi satu fase per tahap sesuai *exit criteria*, & tetapkan path `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md`<br>- Susun & validasi **Plan Artefak Markdown nyata** (`implementation_plan.md` / `bugfix_plan.md` via `write_file` dengan `ArtifactMetadata: { UserFacing: true, RequestFeedback: true }`; gunakan protokol *Artifact-Only Handoff* bila Plan Mode read-only)<br>- Pasca-approval User: inisialisasi & perbarui `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md` di setiap akhir fase/QA/debugging<br>- Definisikan (`define_subagent`) & delegasikan (`invoke_subagent`) ke 5 Sub-Agent, awasi loop QA/bugfix, dan terbitkan Laporan Akhir | **DILARANG** menggabung seluruh pekerjaan dalam satu langkah besar tanpa fase & work doc; **DILARANG** membuang seluruh plan ke teks chat tanpa file Artefak; **DILARANG** menyalin ulang seluruh isi artefak di chat; **DILARANG** melanjut ke Fase 2 sebelum approval User; **DILARANG** menulis kode produksi (`backend/`, `frontend/`) sendiri; **DILARANG** menjalankan tes browser sendiri; **DILARANG** menutup tugas sebelum Sertifikat QA `PASS` dan finalisasi `drent-docs`. |
| **1. `drent-explorer`** | Codebase Explorer, Hybrid Mapper & Reuse Auditor *(Fase 1 & RCA)* | - Petakan arsitektur, *callers*, *callees*, dan *blast radius* di `backend/` & `frontend/` menggunakan **Hybrid Mapping (`codegraph_explore` + `grep`)**<br>- Audit komponen, composable, API module, Service, dan Policy yang sudah ada (*Reuse-First Audit*)<br>- Pisahkan *File Inventory* Backend vs Frontend, usulkan urutan fase eksekusi, dan lakukan RCA mendalam saat terjadi bug | **100% READ-ONLY** (`EnableWriteTools: false`). Dilarang mengubah file apapun. |
| **2. `drent-engineer`** | Backend Laravel 13 Engineer *(Fase 2 & 3B Backend)* | - Eksekusi bedah kode di `backend/` (`Controllers/Api/V1/`, `Requests/`, `Resources/`, `Policies/`, `Services/`, `Models/`, `routes/api.php`, `database/migrations/`, `tests/`) sesuai Plan & `.agents/rules/drent.md`<br>- Terapkan: IDR integer penuh (re-kalkulasi di backend), `FormRequest`, `Policy` (`$this->authorize()`), `Service` + `DB::transaction()`, `JsonResource`, isolasi `branch_id`, `SoftDeletes` + TODO arsip, pagination (`page`/`per_page` + `meta`) untuk data banyak<br>- Jalankan `php -l` & `php artisan test` (+ tulis/perbarui test untuk logika kritis/bugfix) | **DILARANG** mengubah file di `frontend/src/`; **DILARANG** menyentuh file di luar *File Inventory* Backend; **DILARANG** mengklaim tes E2E browser lulus sendiri. |
| **3. `drent-frontend`** | Frontend Vue 3 + PrimeVue 4 + Capacitor Specialist *(Fase 2 & 3B Frontend)* | - Eksekusi bedah kode di `frontend/src/` sesuai `.agents/skills/drent-frontend/SKILL.md`, `design.md`, `mobile.md`, `compact-table-density`, & `transitions-dev`<br>- Terapkan: *Reuse-First* (PrimeVue $\rightarrow$ `src/components/` $\rightarrow$ `src/composables/` $\rightarrow$ `src/api/`), Thin Views & dekomposisi komponen fokus, 0 `axios` mentah di `.vue`, Desktop `DataTable` (`769–1365px` compact density) + Mobile `.mobile-card-list` (`<= 768px`, desktop tidak rusak), pagination data banyak, `.custom-dialog` + `ConfirmDialog`/dirty-close guard<br>- Jalankan `npm run build` di `frontend/` (wajib **0 error**) | **DILARANG** mengubah file di `backend/`; **DILARANG** mengubah style desktop untuk memperbaiki mobile (`mobile.md`); **DILARANG** mengklaim tes E2E browser lulus sendiri. |
| **4. `drent-qa`** | Independent QA & E2E Inspector *(Fase 3)* | - Muat `.agents/skills/drent-local-testing/SKILL.md`, `.agents/skills/drent-qa/SKILL.md`, & `.agents/skills/drent-frontend/SKILL.md`<br>- Audit callers & integritas simbol via **Hybrid Mapping (`codegraph_explore` + `grep`)**<br>- Verifikasi CLI: `php artisan test`, `php artisan route:list` (kondisional), `npm run build`<br>- Uji UI nyata via Playwright MCP di `http://localhost:5173` (Desktop & Mobile `390x844`, 0 console/network error 500/404, cek `design.md` & `mobile.md`)<br>- Keluarkan **Sertifikat QA (`PASS` / `FAIL` + RCA & domain bug Backend/Frontend)** | **AUDITOR INDEPENDEN.** **DILARANG KERAS** memanggil `write_file`/`edit_file` untuk mengubah kode aplikasi maupun dokumen! |
| **5. `drent-docs`** | Docs & Ledger Custodian *(Fase 4 — Wajib Semua Mode)* | - Berjalan **HANYA SETELAH** Sertifikat QA `PASS`<br>- **WAJIB SELALU** mengaudit & memfinalisasi `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md` (`Status Keseluruhan: COMPLETED`, semua fase `DONE`, entri log Fase 4 ber-timestamp, `## 4. Ringkasan File Final` sinkron 100% dengan `git status --short`)<br>- Menyinkronkan dokumen terkait di `docs/` (misal `USER_MANUAL.md`, `mobile-friendly-rollout.md`, atau dokumen fitur) jika terdampak<br>- Memverifikasi `git status --short` bersih & terisolasi | **DILARANG** menyentuh file kode di `backend/` atau `frontend/`. |

> **Spesifikasi Lengkap Sub-Agent:** Gunakan definisi JSON `define_subagent` dan template `invoke_subagent` di [references/subagent-definitions.md](references/subagent-definitions.md).

---

## 2. Klasifikasi 5 Mode Tugas

1. **`FEATURE`** — Pembuatan fitur baru, endpoint `/api/v1/*`, halaman baru, form/dialog, atau peningkatan kapabilitas modul eksisting.
2. **`BUGFIX`** — Penanganan error runtime/API, salah kalkulasi IDR/finansial, bug RBAC/branch isolation, atau broken UI/mobile/dialog.
3. **`UI_REPORT`** — Penyesuaian tampilan desktop (`design.md`, `compact-table-density`), rollout mobile (`mobile.md`), animasi (`transitions-dev`), atau pembuatan/perbaikan laporan & export PDF/Excel.
4. **`REFACTOR`** — Penataan ulang struktur kode (misal memecah controller gemuk ke `Service`/`FormRequest`/`JsonResource` atau memecah `.vue` monolitik) dengan jaminan zero-regression & test hijau sebelum/sesudah.
5. **`MOBILE_CAPACITOR`** — Penyesuaian perilaku aplikasi mobile Capacitor (`@capacitor/core`, Android/iOS, safe area, fallback web, deep link).

---

## 3. Alur Orkestrasi End-to-End

```text
[User Request] ──► Master Orchestrator klasifikasi Mode (FEATURE / BUGFIX / UI_REPORT / REFACTOR / MOBILE_CAPACITOR)
       │
       ▼
[FASE 1: Eksplorasi & Perencanaan]
  ├─ Orchestrator ──(invoke)──► [drent-explorer] (100% Read-Only)
  │    └─ Hybrid Mapping (`codegraph_explore` + `grep`) + Reuse Audit + File Inventory Backend/Frontend + RCA (jika bug)
  ├─ Orchestrator buat File Artefak Plan (`<Artifact Directory Path>/implementation_plan.md` atau `bugfix_plan.md`)
  │    ├─ Validasi dengan `.agents/skills/drent-qa/references/plan-validation-checklist.md`
  │    ├─ Tetapkan path work doc `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md` (belum ditulis ke `docs/` sebelum approval)
  │    └─ Pecah fase eksekusi berurutan (`Fase A`, `Fase B`, `Fase C`, `Fase D`, ...) + exit criteria
  └─ Kirim tautan artefak + 2–4 poin review singkat ke User ──► (Revisi bila ada komentar, tunggu Approval User)
       │
       ▼
[FASE 2: Inisialisasi Work Doc & Eksekusi Bertahap (Fase A → Fase B → Fase C → ...)]
  ├─ 0) Awal Fase 2 (Pasca-Approval): Master Orchestrator buat `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md`
  ├─ Loop satu fase per tahap sesuai urutan dependensi:
  │    ├─ Backend Laravel ──► invoke `drent-engineer` (`php -l` + `php artisan test`)
  │    ├─ Frontend Vue/PrimeVue ──► invoke `drent-frontend` (`npm run build`)
  │    └─ Checkpoint Akhir Fase: verifikasi exit criteria lulus ──► update tabel fase (`DONE`) + log ber-timestamp di work doc
       │
       ▼
[FASE 3: Inspeksi QA Independen (`drent-qa` — Dilarang Edit Kode)]
  ├─ Orchestrator ──(invoke)──► [drent-qa]
  │    └─ Hybrid Callers Audit + `php artisan test` + `npm run build` + Playwright E2E Desktop & Mobile (`drent-local-testing`)
  ├─ Orchestrator catat hasil QA ke work doc
  │    ├── Jika `FAIL` ──► [FASE 3B: Bugfix Plan Artefak + Surgical Fix (`drent-engineer`/`drent-frontend`) + Log Debugging] ──► Ulangi FASE 3
  │    └── Jika `PASS` ──► [FASE 4: Finalisasi Work Doc & Sinkronisasi Docs (`drent-docs`)] ──► [Laporan Akhir ke User]
```

### 3.1 Standar Wajib Plan Artefak (*Comment-Friendly Plan*)

- **File Artefak Wajib:** `<Artifact Directory Path>/implementation_plan.md` (atau `bugfix_plan.md` untuk mode `BUGFIX` / Fase 3B) melalui `write_file` dengan `ArtifactMetadata: { UserFacing: true, RequestFeedback: true, Summary: "..." }`.
  > *Catatan Fase 3B:* Untuk loop remediasi internal yang masih dalam scope plan yang sudah disetujui, boleh `RequestFeedback: false` (tetap `UserFacing: true`).
- **Protokol Plan Mode Read-Only (*Artifact-Only Handoff*):** Jika `write_file`/`edit_file` ditolak karena sesi berada pada Plan Mode read-only, **DILARANG** menulis plan panjang ke chat. Panggil `exit_plan_mode` dengan satu instruksi tunggal untuk *Execution Turn*: **HANYA** membuat/memperbarui file artefak plan (`UserFacing: true`, `RequestFeedback: true`), mengirim tautan clickable + 2–4 poin review singkat, lalu **BERHENTI** menunggu persetujuan User (**DILARANG** menyentuh kode workspace atau `docs/`).
- **Struktur Plan Artefak (9 Bagian Bernomor):**
  1. **Header Metadata:** Modul, Mode Tugas, Status Checklist Validasi (`APPROVED` / `NEEDS_REVISION`), Role Pengujian, & Path Work Doc (`docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md`).
  2. **Tujuan & Ringkasan Permasalahan** *(atau Gejala, Reproduksi, & RCA `file:baris` untuk `BUGFIX`)*.
  3. **Asumsi & Keputusan untuk Review User** (blok `> [!IMPORTANT]` + daftar bernomor; termasuk konfirmasi item Bagian 12 `drent.md` jika tersentuh).
  4. **Hasil Eksplorasi & *Blast Radius*** (temuan `drent-explorer`: Hybrid `codegraph` + `grep`, tabel DB, endpoint `/api/v1/*`, dan Audit Reuse komponen/composable/service).
  5. **File Inventory Terpisah (Backend vs Frontend)** dengan tautan clickable `file:///...`.
  6. **Rencana Perubahan Detail & Kontrak Publik** (Layer Laravel + IDR integer + `DB::transaction()` + `branch_id`, serta komponen Vue/PrimeVue + `design.md`/`mobile.md` + pagination).
  7. **Pembagian Fase Eksekusi (`Fase A`, `B`, `C`, `D`, ...) & Exit Criteria** (`[Step] → verify: [check]`).
  8. **Strategi Verifikasi QA (`drent-qa`) & Finalisasi Docs (`drent-docs`)**.
  9. **Ringkasan Checklist Validasi Plan** (`plan-validation-checklist.md`).
- **Presentasi ke User:** Jangan salin ulang isi artefak di chat. Berikan tautan clickable + sorotan 2–4 poin kunci, lalu tunggu komentar/persetujuan.

### 3.2 Pembagian Pekerjaan Bertahap (*Phased Execution Breakdown*)

- **Tugas Full-Stack / Kompleks:**
  - `Fase A`: Inisialisasi work doc + migrasi DB / schema / konfigurasi prasyarat.
  - `Fase B`: Backend Core (`Request`, `Policy`, `Service`, `Resource`, `Controller`, `routes/api.php`, `tests/` — `drent-engineer`).
  - `Fase C`: Frontend & UI (`api/`, `composables/`, `components/`, `views/`, `router/` — `drent-frontend`, pecah `C1`/`C2` bila banyak halaman/dialog).
  - `Fase D`: `Fase D1` QA Independen (`drent-qa`) dan `Fase D2` Finalisasi Work Doc & Sinkronisasi Docs (`drent-docs`).
- **Tugas Terfokus / Single-Layer (`BUGFIX` / `UI_REPORT` / kecil):** Minimal **3 fase eksplisit** (`Fase A` Inisialisasi work doc & fondasi/RCA $\rightarrow$ `Fase B` Eksekusi bedah $\rightarrow$ `Fase C` `C1` QA Independen + `C2` Finalisasi Docs).

### 3.3 Template Dokumen Kerja (`docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md`)

Diinisialisasi oleh Master Orchestrator tepat saat awal Fase 2 (pasca-approval User), diperbarui setiap selesai fase/QA/debugging, dan difinalisasi oleh `drent-docs` di Fase 4:

```markdown
# YYYY-MM-DD — Log Progres & Debugging: [Nama Pekerjaan]

- **Tanggal Mulai:** YYYY-MM-DD
- **Modul Terkait:** `[NamaModul]`
- **Mode Tugas:** `[FEATURE | BUGFIX | REFACTOR | UI_REPORT | MOBILE_CAPACITOR]`
- **Status Keseluruhan:** `IN_PROGRESS` *(ubah menjadi `COMPLETED` di Fase 4)*

---

## 1. Tujuan & Keputusan yang Disetujui User
1. [Keputusan bisnis / batasan teknis / asumsi yang disetujui dari Plan Artefak]

---

## 2. Pelacak Progres per Fase

| Fase | Lingkup | Agen | Status | Catatan Ringkas |
|---|---|---|---|---|
| **Fase A** | [Lingkup Fase A] | [Agen] | `TODO / IN_PROGRESS / DONE` | [Hasil & verifikasi] |
| **Fase B** | [Lingkup Fase B] | [Agen] | `TODO` | - |
| **Fase C** | [Lingkup Fase C] | [Agen] | `TODO` | - |
| **Fase D** | QA Independen (`D1`) & Finalisasi Docs (`D2`) | `drent-qa` + `drent-docs` | `TODO` | - |

---

## 3. Log Progres & Debugging

### [YYYY-MM-DD HH:mm] Fase 1 — Eksplorasi & Plan Disetujui (`drent-explorer`)
- **Temuan & Blast Radius:** [Ringkasan callers, tabel, komponen reuse, atau RCA awal]

### [YYYY-MM-DD HH:mm] Fase A — [Judul Fase A] (`[Agen]`)
- **Perubahan:** [Daftar file & perubahan inti]
- **Debugging / Temuan:** [Kendala yang ditemukan & solusinya, atau Tidak ada]
- **Verifikasi:** [Output `php -l`, `php artisan test`, `npm run build`, Playwright, dll.]

---

## 4. Ringkasan File Final
- *(Diisi & diverifikasi sinkron 100% dengan `git status --short` oleh `drent-docs` pada Fase 4)*
```

---

## 4. Matriks Eksekusi Adaptif per Mode Tugas

| Tahap / Pemeriksaan | Agen | `FEATURE` | `BUGFIX` | `UI_REPORT` | `REFACTOR` | `MOBILE_CAPACITOR` |
|---|---|:---:|:---:|:---:|:---:|:---:|
| **Fase 1: Hybrid Mapping (`codegraph` + `grep`) & Reuse Audit** | `drent-explorer` | ✅ Wajib | ✅ Wajib (RCA) | ✅ Wajib | ✅ Wajib | ✅ Wajib |
| **Fase 1: Plan Artefak + Pembagian Fase + Path Work Doc** | `Master Orchestrator` | ✅ Wajib | ✅ Wajib (`bugfix_plan.md`) | ✅ Wajib | ✅ Wajib | ✅ Wajib |
| **Fase 2–4: Work Doc (`docs/YYYY-MM-DD-<slug>.md`)** | `Orchestrator` + `drent-docs` | ✅ Wajib | ✅ Wajib | ✅ Wajib | ✅ Wajib | ✅ Wajib |
| **Fase 2: Backend Laravel + PHPUnit** | `drent-engineer` | ⚙️ Jika sentuh BE | ⚙️ Jika bug BE | ⚙️ Jika butuh data BE | ⚙️ Jika refaktor BE | ⚙️ Jika butuh API |
| **Fase 2: Frontend Vue/PrimeVue + `npm run build`** | `drent-frontend` | ⚙️ Jika sentuh FE | ⚙️ Jika bug FE | ✅ Wajib | ⚙️ Jika refaktor FE | ✅ Wajib |
| **Fase 3: Hybrid Callers + CLI (`artisan test` / `build`) + Playwright E2E** | `drent-qa` | ✅ Wajib | ✅ Wajib | ✅ Wajib | ✅ Wajib | ✅ Wajib |
| **Fase 3B: Surgical Bug Remediation** | `drent-engineer` / `drent-frontend` | ⚙️ Sesuai domain | ⚙️ Sesuai domain | ⚙️ Sesuai domain | ⚙️ Sesuai domain | ⚙️ Sesuai domain |
| **Fase 4: Finalisasi Work Doc & Sync Docs + `git status`** | `drent-docs` | ✅ Wajib | ✅ Wajib | ✅ Wajib | ✅ Wajib | ✅ Wajib |

---

## 5. Format Laporan Akhir Orkestrator ke Pengguna

```markdown
# 🎯 Laporan Orkestrasi Tugas (DRENT): [Judul Tugas]

## 1. Ringkasan Eksekusi & Pembagian Peran
- **Modul:** `[NamaModul]` | **Mode Tugas:** `[FEATURE | BUGFIX | UI_REPORT | REFACTOR | MOBILE_CAPACITOR]`
- **Status Akhir:** `COMPLETED`
- **Eksekusi Bertahap:** `Fase A` (`DONE`), `Fase B` (`DONE`), `Fase C` (`DONE`), `Fase D` (`DONE`)
- **Rantai Agen:**
  - 🔍 **Explorer (`drent-explorer`):** Hybrid Mapping (`codegraph` + `grep`) & Reuse Audit selesai
  - ⚙️ **Backend Engineer (`drent-engineer`):** [Selesai (`php -l` & `php artisan test` lulus) / N/A]
  - 🎨 **Frontend Specialist (`drent-frontend`):** [Selesai (`npm run build` 0 error) / N/A]
  - 🛡️ **QA Inspector (`drent-qa`):** Sertifikat QA `PASS` (Callers, CLI, & Playwright Desktop + Mobile bersih)
  - 📚 **Docs Custodian (`drent-docs`):** Finalisasi `docs/YYYY-MM-DD-<slug>.md` & verifikasi `git status` selesai

## 2. Hasil Inspeksi QA Independen (`drent-qa`)
- [x] Hybrid Callers Audit (`codegraph` + `grep`): Lulus (0 broken callers)
- [x/N/A] Backend CLI (`php artisan test` & `route:list`): [Lulus / N/A]
- [x/N/A] Frontend Build (`npm run build`): [Lulus 0 error / N/A]
- [x] Playwright E2E (`http://localhost:5173`): Lulus (Role: [Role], Desktop & Mobile `390x844`, 0 console/network errors)
- [x/N/A] Kepatuhan `design.md` & `mobile.md`: [Lulus — desktop tidak berubah, mobile card grid rapi / N/A]

## 3. Penanganan Bug & Stabilitas (Fase 3B)
- [Tidak ada bug ditemukan / X bug telah diperbaiki secara surgical oleh `drent-engineer` / `drent-frontend`]

## 4. Dokumentasi (`drent-docs`)
- [x] `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md`: Difinalisasi (`COMPLETED`)
- [x/N/A] Dokumen terkait di `docs/`: [Diperbarui / N/A]
```
