# Playbook Remediasi Bug Terencana (DRENT Bugfix Playbook)

Gunakan playbook ini saat `drent-qa` mengeluarkan Sertifikat `FAIL` atau saat menangani laporan bug (`BUGFIX`).
Prinsip wajib: **"Think Before Coding"** dan **"Surgical Changes"** (Karpathy Guidelines).

---

## Alur 5 Langkah

```text
[1. Tangkap Error & Reproduksi]         ──► Dilaporkan oleh `drent-qa` / User
              ↓
[2. Root Cause Analysis (Hybrid)]       ──► Dianalisis oleh `drent-qa` / `drent-explorer` (Read-Only)
              ↓
[3. Susun Bugfix Plan Artifact]         ──► Disusun oleh `Master Orchestrator` (`bugfix_plan.md`)
              ↓
[4. Eksekusi Perbaikan Bedah + Test]    ──► `drent-engineer` (Backend) atau `drent-frontend` (Frontend)
              ↓
[5. Verifikasi Ulang Independen]        ──► `drent-qa` hingga Sertifikat QA: `PASS`
```

---

## Langkah 1: Tangkap Error & Reproduksi
Catat:
- Pesan error persis & HTTP status code (`422`, `403`, `404`, `500`, error console JS/Vue, atau mismatch kalkulasi IDR).
- Endpoint `/api/v1/*` atau rute UI yang bermasalah beserta viewport (Desktop vs Mobile).
- Role & branch pengguna saat error terjadi.

## Langkah 2: Root Cause Analysis (RCA) — Hybrid Mapping
1. Panggil MCP `codegraph_explore` (`projectPath: c:\Users\Salivs\Data\laragon\www\drent-vibe`, `query: "<NamaSimbol>"`).
2. Konfirmasi dengan `grep` (`Select-String` / `rg`) untuk semua pemanggil di `backend/` dan `frontend/`.
3. Tentukan lokasi pasti penyebab (`file:baris`), *blast radius*, dan domain perbaikan (**Backend `drent-engineer`** vs **Frontend `drent-frontend`**).

## Langkah 3: Format Bugfix Plan Artifact (`bugfix_plan.md`)

```markdown
### 🐛 Bug Remediation Plan: [Deskripsi Singkat Bug]

1. **Gejala & Reproduksi:**
   - Error: [Pesan error lengkap / status HTTP]
   - Endpoint / Rute UI: [URL atau path]
   - Langkah trigger: [Urutan aksi reproduksi]

2. **Akar Masalah (Root Cause):**
   - File: `[path/file:baris]`
   - Domain: `[Backend (drent-engineer) | Frontend (drent-frontend)]`
   - Penyebab: [Penjelasan akar masalah secara objektif]

3. **Blast Radius (Hybrid `codegraph` + `grep`):**
   - Callers terkait: [Daftar pemanggil yang dicek]

4. **Rencana Perbaikan Bedah (Surgical Fix):**
   - File yang diubah: [Hanya file esensial]
   - Perubahan inti: [Minimal diff tanpa mengubah kode lain yang tidak rusak]
   - Test regresi: [Feature/Unit test backend atau skenario E2E yang ditambahkan]

5. **Kriteria Verifikasi Ulang:**
   - [ ] Reproduksi error tidak terjadi lagi
   - [ ] `php artisan test` / `npm run build` lulus 0 error
   - [ ] Playwright E2E Desktop & Mobile 0 console/network error
```

## Langkah 4 & 5: Eksekusi & Loop Verifikasi
- Delegasikan perbaikan ke agen sesuai domain; untuk bug backend, wajib tulis/perbarui test reproduksi di `backend/tests/` lalu buat lulus.
- Catat gejala, RCA, dan perbaikan di `## 3. Log Progres & Debugging` pada `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md`.
- Panggil ulang `drent-qa` hingga Sertifikat QA berstatus `PASS`.
