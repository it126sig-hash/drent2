# Aturan Penjaminan Mutu & Remediasi Bug (DRENT QA Rule)

Dokumen ini adalah aturan ringkas **4 Gerbang Kualitas (Quality Gates)** di DRENT.
Untuk prosedur operasional lengkap, checklist, playbook bugfix, dan format sertifikat, baca `.agents/skills/drent-qa/SKILL.md`.

---

## 1. GERBANG 1: Validasi Rencana (Oleh `Master Orchestrator`)
Dilarang menugaskan `drent-engineer` atau `drent-frontend` menulis kode sebelum Plan lolos checklist `.agents/skills/drent-qa/references/plan-validation-checklist.md`:
1. **Prinsip Karpathy:** Tanpa kode spekulatif, diff minimal (*surgical*), kriteria verifikasi eksplisit (`[Step] → verify: [check]`), dan tidak ada orphan imports/variables.
2. **Standar Backend Laravel (`drent.md`):** Thin Controller `Api/V1/`, `FormRequest`, `Policy` (`$this->authorize()`), `Service`, `JsonResource`, uang **IDR integer penuh** (re-kalkulasi di backend), `DB::transaction()` untuk mutasi multi-tabel, isolasi `branch_id`, `SoftDeletes`, dan pagination untuk daftar data banyak.
3. **Standar Frontend Vue + PrimeVue (`drent-frontend`, `design.md`, `mobile.md`):** Reuse-First komponen/composable, HTTP hanya lewat `src/api/` & `src/composables/`, token `src/style.css`, Dual-View Desktop `DataTable` (`769–1365px` compact density) + Mobile `.mobile-card-list` (`<= 768px`, desktop tidak berubah), pagination data banyak, dan dialog guard (`ConfirmDialog`).
4. **Pembagian Fase & Dokumen Kerja:** Pecah pekerjaan menjadi fase berurutan (`Fase A`, `Fase B`, `Fase C`, ...) dengan *exit criteria* per fase dan tetapkan path `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md`.
5. **Strategi Verifikasi:** Cantumkan skenario PHPUnit (`php artisan test`), `npm run build`, dan Playwright E2E (desktop & mobile `390x844`) via `drent-local-testing`.

---

## 2. GERBANG 2: Inspeksi QA Independen (Oleh `drent-qa` — Fase 3)
Setelah `drent-engineer` dan/atau `drent-frontend` selesai, `drent-qa` menguji secara independen (**DILARANG mengubah file kode/dokumen apapun**):
1. **Hybrid Callers Audit (`codegraph_explore` + `grep`):** Pastikan 0 broken callers/imports di `backend/` maupun `frontend/`.
2. **Verifikasi Otomatis CLI:**
   - Backend (jika disentuh): `php artisan test` lulus, `php artisan route:list` bersih (jika rute berubah).
   - Frontend (jika disentuh): `npm run build` di `frontend/` lulus dengan **0 error**.
3. **Playwright E2E & UI Audit (`drent-local-testing`):** Buka `http://localhost:5173`, login dengan akun uji lokal, verifikasi alur bisnis + kepatuhan `design.md` & `mobile.md` pada viewport desktop (`1366x768` / `1280x800`) dan mobile (`390x844`), pastikan **0 console/network error (500/404)**.
4. **Output:** Terbitkan Sertifikat Verifikasi QA berstatus **`PASS`** atau **`FAIL`** (beserta stack trace, RCA awal, dan domain bug: Backend `drent-engineer` vs Frontend `drent-frontend`).

---

## 3. GERBANG 3: Protokol Remediasi Bug (Fase 3B — Saat QA `FAIL`)
Jika `drent-qa` menerbitkan sertifikat `FAIL`:
1. **Dilarang Menebak Kode:** Gunakan stack trace dan Hybrid Mapping (`codegraph_explore` + `grep`) oleh `drent-qa` / `drent-explorer` untuk memastikan akar masalah dan *blast radius*.
2. **Bugfix Plan Artifact:** `Master Orchestrator` membuat/memperbarui `<Artifact Directory Path>/bugfix_plan.md`.
3. **Surgical Fix Sesuai Domain:**
   - Bug Backend (`backend/`) ──► dieksekusi oleh **`drent-engineer`** (+ tambah/update test reproduksi).
   - Bug Frontend/UI (`frontend/`) ──► dieksekusi oleh **`drent-frontend`**.
4. **Re-Verification Loop:** `drent-qa` menguji ulang Gerbang 2 hingga menerbitkan sertifikat **`PASS`**.

---

## 4. GERBANG 4: Finalisasi Dokumentasi & Audit Git (Oleh `drent-docs` — Fase 4)
Dijalankan oleh `drent-docs` **HANYA SETELAH** sertifikat QA berstatus `PASS`:
1. **`docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md` (WAJIB SEMUA MODE):** Audit & finalisasi (`Status Keseluruhan: COMPLETED`, semua fase `DONE`, entri log Fase 4 ber-timestamp, serta `## 4. Ringkasan File Final` sinkron 100% dengan `git status --short`).
2. **Sinkronisasi Docs Terkait (Kondisional):** Perbarui dokumen di `docs/` (seperti `USER_MANUAL.md`, `mobile-friendly-rollout.md`, atau dokumen fitur terkait) jika perubahan memengaruhi dokumentasi produk/arsitektur.
3. **Git Status:** Pastikan hanya file target tugas dan dokumen relevan yang berubah.
