---
name: drent-qa
description: >
  Sistem penjaminan mutu independen DRENT: validasi plan (Gerbang 1), verifikasi pasca-implementasi oleh `drent-qa` (Gerbang 2: Hybrid codegraph/grep, PHPUnit, Vite build, Playwright E2E Desktop & Mobile), protokol RCA & remediasi bug (Gerbang 3), serta finalisasi dokumentasi oleh `drent-docs` (Gerbang 4).
  Gunakan skill ini saat memvalidasi plan, menjalankan audit independen pasca-implementasi, menangani bug/error terstruktur, atau memfinalisasi dokumen kerja.
---

# DRENT Quality Assurance, Plan Validation & Remediation Playbook

Skill ini mendefinisikan standar **4 Gerbang Kualitas (*Quality Gates*)** dalam arsitektur multi-agen DRENT, dengan pemisahan wewenang tegas antara **Master Orchestrator**, **Backend (`drent-engineer`)**, **Frontend (`drent-frontend`)**, **Auditor Independen (`drent-qa`)**, dan **Penjaga Dokumen (`drent-docs`)**.

---

## Ringkasan Alur Check-and-Balance

```text
[GERBANG 1: Validasi Plan] (Master Orchestrator berdasarkan laporan `drent-explorer`)
         │
         ▼
[Eksekusi Bedah per Fase] (`drent-engineer` [Backend] & `drent-frontend` [Frontend] — Fase 2)
         │
         ▼
[GERBANG 2: Inspeksi QA Independen] (`drent-qa` — Fase 3: DILARANG EDIT KODE)
         │
         ├──────────────────────────┴──────────────────────────┐
   (Sertifikat: FAIL)                                   (Sertifikat: PASS)
         │                                                     │
         ▼                                                     ▼
[GERBANG 3: Remediasi Bug Terencana (Fase 3B)]      [GERBANG 4: Finalisasi Docs (Fase 4)]
  1. RCA (`drent-qa` / `drent-explorer`)               Dikerjakan oleh `drent-docs`:
  2. Orchestrator susun `bugfix_plan.md`               - Finalisasi `docs/YYYY-MM-DD-<slug>.md`
  3. Fix (`drent-engineer` / `drent-frontend`)         - Sinkronisasi docs terkait (bila ada)
  4. Loop kembali ke GERBANG 2 (`drent-qa`)            - Verifikasi `git status --short`
```

---

## Panduan per Gerbang

### GERBANG 1 — Validasi Rencana (Pre-Execution Plan Gatekeeper)
Gunakan [references/plan-validation-checklist.md](references/plan-validation-checklist.md) sebelum menugaskan eksekutor. Plan hanya boleh berstatus `APPROVED` jika memenuhi:
- Prinsip Karpathy (tanpa kode spekulatif, surgical diff, kriteria verifikasi terukur, pembagian fase `Fase A, B, C...`, path work doc `docs/YYYY-MM-DD-<slug>.md`).
- Standar Backend Laravel (`drent.md`: FormRequest, Policy, Service, JsonResource, IDR integer, `DB::transaction()`, isolasi `branch_id`, `SoftDeletes`, pagination).
- Standar Frontend Vue + PrimeVue (`drent-frontend`, `design.md`, `mobile.md`, `compact-table-density`, Reuse-First, Dual-View Desktop + Mobile, pagination, `ConfirmDialog`).
- Strategi Verifikasi (`php artisan test`, `npm run build`, dan skenario Playwright E2E Desktop + Mobile via `drent-local-testing`).

### GERBANG 2 — Inspeksi QA Independen (`drent-qa` — Fase 3)
Ikuti [references/post-execution-verification.md](references/post-execution-verification.md). Sebagai auditor independen, `drent-qa` **DILARANG KERAS** mengedit file kode maupun dokumentasi:
1. **Hybrid Callers Audit (`codegraph_explore` + `grep`):** Target **0 broken callers/imports**.
2. **Verifikasi CLI Otomatis:** `php artisan test` + `php artisan route:list` (backend) dan `npm run build` (frontend).
3. **Playwright E2E & UI Audit (`drent-local-testing`):** Login ke `http://localhost:5173`, uji Desktop (`1366x768` / `1280x800`) dan Mobile (`390x844`), target **0 console/network error (500/404)** serta kepatuhan `design.md` & `mobile.md`.
4. **Terbitkan Sertifikat QA:** `PASS` atau `FAIL` (beserta stack trace, RCA awal, & domain bug).

### GERBANG 3 — Remediasi Bug Terencana (Fase 3B — Saat QA `FAIL`)
Ikuti [references/bugfix-playbook.md](references/bugfix-playbook.md):
1. RCA berbasis stack trace + Hybrid Mapping (`codegraph_explore` + `grep`).
2. Master Orchestrator susun `<Artifact Directory Path>/bugfix_plan.md`.
3. Surgical fix oleh `drent-engineer` (Backend + test reproduksi) atau `drent-frontend` (Frontend).
4. Uji ulang penuh oleh `drent-qa` hingga berstatus `PASS`.

### GERBANG 4 — Finalisasi Dokumentasi (`drent-docs` — Fase 4)
Dijalankan **HANYA SETELAH** `drent-qa` menerbitkan `PASS`:
1. Audit & finalisasi `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md` (`Status Keseluruhan: COMPLETED`, semua fase `DONE`, entri log Fase 4 ber-timestamp, `## 4. Ringkasan File Final` sinkron 100% dengan `git status --short`).
2. Perbarui dokumen di `docs/` yang terdampak (bila ada).
3. Verifikasi `git status --short` bersih & terisolasi.

---

## File Referensi Terkait
- [Definisi 5 Sub-Agent Spesialis](../drent-orchestrator/references/subagent-definitions.md)
- [Checklist Validasi Plan](references/plan-validation-checklist.md)
- [Panduan Verifikasi Pasca-Eksekusi (`drent-qa`)](references/post-execution-verification.md)
- [Playbook Remediasi Bug](references/bugfix-playbook.md)
