# DRENT — Repository Instructions (`AGENTS.md`)

Repository ini (`c:\Users\Salivs\Data\laragon\www\drent-vibe`) adalah monorepo sistem manajemen rental mobil internal **DRENT** (**Backend:** Laravel 13 + Sanctum + PHP 8.3 | **Frontend:** Vue 3 + PrimeVue 4 + Tailwind CSS v4 + Pinia + Capacitor 8).

---

## 1. Aturan Core & Acuan Wajib

Sebelum merancang atau mengubah kode, patuhi seluruh aturan yang berlaku di `.agents/rules/`:
- [`.agents/rules/drent.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/rules/drent.md) — Aturan global stack (Laravel + Vue 3/PrimeVue), angka uang IDR integer, `FormRequest`, `JsonResource`, `Service`, `Policy`, `SoftDeletes`, isolasi `branch_id`, konvensi penamaan, dan daftar item yang wajib dikonfirmasi sebelum diimplementasi.
- [`.agents/rules/design.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/rules/design.md) — DrentDesign light-first HD-compact design system (token warna, tipografi Sora/DM Sans/JetBrains Mono, spacing, komponen desktop).
- [`.agents/rules/mobile.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/rules/mobile.md) — Aturan mobile-friendly (`<= 768px`) tanpa merusak desktop: tab toggle horizontal scroll, tombol primary 100%, `.mobile-card-list` / `.mobile-card`, filter grid 2 kolom, dan tabel menjadi card grid.
- [`.agents/rules/drent-general-rules.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/rules/drent-general-rules.md) — Prioritas penyelesaian masalah, pemetaan skill per peran agen, dan standar pengujian lokal.
- [`.agents/rules/drent-orchestrator.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/rules/drent-orchestrator.md) — Kontrak Master Orchestrator & 5 Sub-Agent Spesialis, Plan Artefak, pembagian fase bertahap (`Fase A`, `B`, `C`, ...), dan dokumen kerja `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md`.
- [`.agents/rules/drent-qa.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/rules/drent-qa.md) — 4 Gerbang Kualitas (Plan Validation, QA Independen, Bug Remediation, dan Finalisasi Dokumentasi).

---

## 2. Local Application Testing

- Sebelum melakukan pengujian web, browser, E2E Playwright, manual UI, atau HTTP terautentikasi ke aplikasi lokal DRENT (`http://localhost:5173` / `http://localhost/drent-vibe/backend/public/api`), **selalu baca dan aktifkan `.agents/skills/drent-local-testing/SKILL.md`**.
- Pertahankan skill tersebut aktif selama proses login, eksekusi uji, verifikasi, hingga pelaporan.
- Pengujian unit/feature CLI (`php artisan test`) yang tidak membuka aplikasi di browser tidak membutuhkan aktivasi browser.

---

## 3. Master Orchestration & Multi-Agent Development Workflow

- Untuk setiap tugas pengembangan (`FEATURE`, `BUGFIX`, `REFACTOR`, `UI_REPORT`, `MOBILE_CAPACITOR`), agen utama bertindak sebagai **Master Orchestrator** mengikuti `.agents/rules/drent-orchestrator.md` dan `.agents/skills/drent-orchestrator/SKILL.md`.
- Pecah setiap pekerjaan menjadi fase eksekusi berurutan (`Fase A`, `Fase B`, `Fase C`, `Fase D`, ...) dalam Plan Artefak dan rekam progres, verifikasi, serta debugging di `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md` (diinisialisasi pasca-approval User, diperbarui setiap selesai fase/QA/debugging, dan difinalisasi pada Fase 4).
- Tegakkan pemisahan tanggung jawab tegas ke **5 Sub-Agent Spesialis** ([`.agents/skills/drent-orchestrator/references/subagent-definitions.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/skills/drent-orchestrator/references/subagent-definitions.md)):
  1. **`drent-explorer`** *(Fase 1 & RCA — 100% Read-Only)*: Eksplorasi codebase (`backend/` & `frontend/`), Hybrid Mapping (`codegraph_explore` + `grep`), audit reuse komponen/composable/service, dan Root Cause Analysis (RCA).
  2. **`drent-engineer`** *(Fase 2 & Fase 3B Backend)*: Eksekusi bedah Backend Laravel (`Controllers/Api/V1/`, `Requests/`, `Resources/`, `Policies/`, `Services/`, `Models/`, `routes/api.php`, `tests/`) + `php -l` & `php artisan test`.
  3. **`drent-frontend`** *(Fase 2 & Fase 3B Frontend/UI/Capacitor)*: Eksekusi Frontend Vue 3 + PrimeVue 4 + Tailwind v4 + Pinia + Capacitor mengikuti `.agents/skills/drent-frontend/SKILL.md`, `design.md`, `mobile.md`, dan `compact-table-density` + `npm run build`.
  4. **`drent-qa`** *(Fase 3 — Independent QA & E2E Auditor)*: Auditor independen (**DILARANG mengedit kode**); menjalankan Hybrid callers audit (`codegraph` + `grep`), `php artisan test`, `npm run build`, Playwright E2E Desktop & Mobile (`390x844`), dan menerbitkan Sertifikat QA (`PASS`/`FAIL`).
  5. **`drent-docs`** *(Fase 4 — Mandatory All Modes Pasca-PASS)*: Mengaudit & memfinalisasi `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md`, menyinkronkan dokumen terkait di `docs/` bila kontrak/fitur berubah, dan memverifikasi `git status --short`.
