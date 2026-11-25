# Panduan Verifikasi Pasca-Eksekusi (DRENT QA Verification)

Panduan resmi untuk sub-agent independen **`drent-qa`** pada Fase 3.
`drent-qa` **DILARANG KERAS** mengedit file kode aplikasi maupun dokumentasi.

---

## 1. Verifikasi Inti (Wajib Semua Mode)

### A. Audit Callers & Kepatuhan Arsitektur (Hybrid `codegraph` + `grep`)
1. Jalankan MCP `codegraph_explore` (`projectPath: c:\Users\Salivs\Data\laragon\www\drent-vibe`) untuk simbol/method/komponen yang diubah.
2. Konfirmasi kelengkapan dengan `grep` (`Select-String` / `rg`) untuk template `.vue`, `import`, composable, dan endpoint `/v1/...` (target: **0 broken callers**).
3. Cek aturan wajib:
   - **Backend (jika disentuh):** FormRequest (bukan `$request->validate()` di Controller), Policy (`$this->authorize()`), Service, JsonResource (tidak return Model langsung), IDR integer, `DB::transaction()` untuk multi-tabel, isolasi `branch_id`.
   - **Frontend (jika disentuh):** 0 `axios` mentah di `.vue`, 0 `window.confirm()`, pagination untuk daftar data banyak, `design.md` + `compact-table-density` (`769–1365px`), dan `mobile.md` (`<= 768px`, desktop tidak rusak).

### B. Verifikasi Otomatis CLI
- **Backend (jika `backend/` disentuh):**
  ```powershell
  cd c:\Users\Salivs\Data\laragon\www\drent-vibe\backend
  php artisan test
  # Jika rute berubah:
  php artisan route:list
  ```
- **Frontend (jika `frontend/` disentuh):**
  ```powershell
  cd c:\Users\Salivs\Data\laragon\www\drent-vibe\frontend
  npm run build
  ```

### C. Pengujian Antarmuka Playwright E2E
1. **WAJIB** baca `.agents/skills/drent-local-testing/SKILL.md` sebelum membuka browser.
2. Buka `http://localhost:5173` via Playwright MCP dan login menggunakan akun uji lokal yang sesuai.
3. Uji skenario utama pada:
   - **Desktop (`1366x768`, plus `1280x800` jika menyentuh tabel/compact density):** Pastikan layout rapi, tabel tidak terpotong aneh, dialog & aksi bekerja.
   - **Mobile (`390x844`):** Pastikan tidak ada horizontal scroll tabel (memakai `.mobile-card-list` / `.mobile-card`), filter ringkas (grid 2 kolom), tombol primary 100%, tab toggle dapat di-scroll horizontal.
4. Periksa `browser_console_messages` dan `browser_network_requests`: **Wajib 0 console error dan 0 HTTP 500/404 tak terduga**.

---

## 2. Format Sertifikat Verifikasi QA

```markdown
### 🛡️ Sertifikat Verifikasi QA (`drent-qa`): [Judul Tugas]
- **Status Sertifikat:** `[PASS | FAIL]`
- **Modul:** `[Nama Modul]` | **Mode Tugas:** `[FEATURE | BUGFIX | UI_REPORT | REFACTOR | MOBILE_CAPACITOR]`
- **Tanggal/Waktu:** `[YYYY-MM-DD HH:mm]`
- **Hybrid Callers Audit (`codegraph` + `grep`):** `[Bersih (0 broken callers) / Ditemukan X broken callers]`
- **Backend CLI (`php artisan test` / `route:list`):** `[LULUS (X passed) / GAGAL / N/A]`
- **Frontend Build (`npm run build`):** `[LULUS (0 error) / GAGAL / N/A]`
- **Playwright E2E Desktop (`1366x768` / `1280x800`):** `[LULUS / GAGAL / N/A] (Role: [Role], Console/Network Errors: [0 / X])`
- **Playwright E2E Mobile (`390x844`):** `[LULUS / GAGAL / N/A] (Card Grid & Filter 2-Col OK / Bermasalah)`
- **Kepatuhan Aturan (`drent.md` / `design.md` / `mobile.md`):** `[LULUS / Bermasalah]`
- **Detail Kegagalan & RCA Awal (Jika FAIL):** `[Kosong jika PASS / Stack trace, file:baris penyebab, & penunjukan domain perbaikan: drent-engineer atau drent-frontend]`
```
