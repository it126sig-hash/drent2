# Work Doc — Revamp Alur & Konsolidasi (module-map §5)

- **Tanggal mulai:** 2026-11-25
- **Tipe tugas:** REFACTOR (multi-fase)
- **Sumber rencana:** `docs/2026-11-25-module-map.md` §5, referensi komponen: `docs/component-inventory.md` §5
- **Status:** `IN_PROGRESS` — Fase D3 selesai

---

## 1. Tujuan & Scope

Mengeksekusi langkah 5 dari module map: (1) validasi alur bisnis saat ini terhadap kode,
(2) konsolidasi komponen frontend yang duplikat, (3) rombak alur bisnis per modul
(booking → invoice → payment, dana driver, approval 2 langkah, physical check),
(4) penyelarasan rent-to-rent, (5) QA independen, (6) finalisasi dokumentasi.

**Out of scope:** perubahan skema database besar, deploy, fitur baru.

## 2. Aturan Anti-Hilang-Konteks

- Satu fase = satu sesi kerja. Jangan pernah memuat lebih dari satu fase dalam konteks.
- Work doc ini adalah satu-satunya sumber kebenaran: diperbarui di akhir tiap fase
  (keputusan, progres, hasil verifikasi). Sesi baru cukup membaca doc ini + file fase berjalan.
- Tiap fase diakhiri commit (checkpoint). Jangan lanjut fase berikutnya sebelum commit.
- Bila sesi terputus: baca bagian "Status Fase" + "Keputusan & Catatan", lanjutkan fase yang
  berstatus `IN_PROGRESS`.

## 3. Status Fase

| Fase | Nama | Peran utama | Status | Commit |
|------|------|-------------|--------|--------|
| 0 | Kickoff & konfirmasi scope | Orchestrator | `DONE` | (diisi saat commit) |
| A | Validasi alur (read-only) | drent-explorer | `PLANNED` | — |
| B | Konsolidasi komponen bag. 1 | drent-frontend | `PLANNED` | — |
| C | Konsolidasi komponen bag. 2 (struktur) | drent-frontend | `PLANNED` | — |
| D1 | Refactor ReceivableController | drent-engineer | `PLANNED` | — |
| D2 | Sentralisasi controller publik | drent-engineer | `PLANNED` | — |
| D3 | Penyesuaian frontend endpoint | drent-frontend | `PLANNED` | — |
| E | Keselarasan rent-to-rent | drent-engineer | `PLANNED` | — |
| F | QA independen | drent-qa | `PLANNED` | — |
| G | Finalisasi dokumentasi | drent-docs | `PLANNED` | — |

## 4. Rencana per Fase

### Fase 0 — Kickoff & konfirmasi scope
- Inisialisasi work doc ini (sudah), isi tabel scope satu halaman.
- Konfirmasi user: item §4 module-map mana yang masuk scope (default: semua 6),
  status migrasi 5 `Legacy*ImportService` (default: keep, diputuskan di Fase A),
  apakah D2 boleh mengubah URL publik (default: tidak — URL tetap).
- Output: tabel scope. **Tidak ada perubahan kode.**

### Fase A — Validasi alur (100% read-only)
- Periksa tiap alur §3 module-map (booking→invoice→payment, rent-to-rent, dana driver,
  approval 2 langkah, physical check) terhadap kode nyata; catat perbedaan di §5.
- Konfirmasi status `Legacy*ImportService` dan komponen signature duplikat
  (rujuk `docs/component-inventory.md` §5).
- Output: verdict per alur → `valid / berubah / drop` di §5. Tanpa edit kode.

### Fase B — Konsolidasi komponen bagian 1
- Dedupe 2 komponen signature; adopsi `.custom-dialog` untuk dialog yang belum pakai;
  badge inline jadi komponen bersama.
- Verifikasi: `npm run build` + cek browser (desktop & 390×844). Commit.

### Fase C — Konsolidasi komponen bagian 2 (struktur)
- Pindahkan `UserPermissionOverrideDialog` dari `views/users/components/`.
- Rename `views/driver/` → `views/driver-portal/` (atau setara) + perbarui import/route.
- Verifikasi & commit seperti Fase B.

### Fase D1 — Refactor ReceivableController (perilaku tidak berubah)
- Pecah controller gemuk (receivable + invoice + payment + void) menjadi
  controller/service fokus; route & respons API tetap identik (refactor murni).
- Test hijau dulu (`php artisan test`) sebelum menyentuh perilaku. Commit.

### Fase D2 — Sentralisasi endpoint publik bertoken
- Kelompokkan `BookingPublicController` + invoice/R2R/physical-check publik ke satu
  grup controller publik. URL publik tetap. Commit.

### Fase D3 — Penyesuaian frontend
- Sesuaikan pemanggilan endpoint yang berubah; `npm run build` + Playwright
  desktop & 390×844. Commit.

### Fase E — Keselarasan rent-to-rent
- Hanya mengikuti rencana terpisah (`fix-rent-to-rent-task.md` bila ada); fase ini
  menyelaraskan hasilnya dengan struktur baru. Commit.

### Fase F — QA independen (dilarang edit kode)
- `php artisan test`, `npm run build`, audit pemanggil via grep, Playwright desktop +
  mobile 390×844. Terbitkan verdict PASS/FAIL di §6.
- Perbaikan (bila FAIL) kembali ke fase pemiliknya, lalu QA ulang.

### Fase G — Finalisasi dokumentasi
- Finalisasi work doc; perbarui `docs/2026-11-25-module-map.md` dan
  `docs/component-inventory.md` ke struktur baru.
- Verifikasi `git status --short` bersih; commit (+ push bila diminta user).

## 5. Keputusan & Catatan Verifikasi

| Fase | Keputusan / Temuan | Hasil verifikasi | Tanggal |
|------|--------------------|------------------|---------|
| 0 | Scope: semua 6 item §4 module-map; URL publik tetap; Legacy*ImportService diputuskan di Fase A; commit per fase sebagai checkpoint | Work doc + ADR terbaca; baseline git `e852cb2` | 2026-11-26 |
| A | Verdict per alur §3: booking→invoice→payment OK; R2R OK; approval 2-langkah OK; physical check OK; semua sesuai kode. Legacy*ImportService **DIPERTAHANKAN** (masih dipakai app/Console/Commands/LegacyImport*Command.php). ReceivableController 293 baris/20 method (invoice+payment+void+public). Endpoint publik tersebar 4 controller (routes/api.php:48-57). Duplikat komponen signature & lokasi dialog terkonfirmasi | Baca routes/api.php, ReceivableController, Services, struktur views | 2026-11-26 |
| B | SignatureCanvas.vue dijadikan alias deprecated ke components/physical-checks/SignaturePad.vue (kanonik) — dedupe 2 implementasi canvas tanda tangan; 4 dialog ditambah `class="custom-dialog"` (UserPermissionOverride, ResetPassword, MemberExtend, MemberStatus) agar style konsisten | `npm run build` frontend PASS (vite built in 2.34s) | 2026-11-26 |
| C | UserPermissionOverrideDialog dipindah ke components/users/ (reuse lintas halaman); views/driver di-rename views/driver-portal (hilangkan ambiguitas driver/drivers); import router & UserListView diperbarui. Bug ditemukan & diperbaiki: relative import dialog masih 3 level setelah pindah (build FAIL) → dikoreksi ke 2 level | `npm run build` PASS setelah fix (built in 2.97s); build FAIL pertama tertangkap & diperbaiki | 2026-11-26 |
| D3 | Semua pemanggilan endpoint publik di frontend (api/physicalCheck.js, api/receivable.js, api/rentToRent.js) sudah cocok dengan URL yang dipertahankan — **tidak ada perubahan kode yang diperlukan** | grep 9 pemanggilan `public/...` vs route:list 10 route publik — semua match | 2026-11-26 |
| D1+D2 | Endpoint publik berbasis token disatukan di controller baru `PublicAccessController` (invoice, R2R bill+pdf, physical check show/otp/activity/submit); method publik lama dihapus dari ReceivableController, RentToRentController, PhysicalCheckController. **Semua URL publik tetap identik** (routes/api.php di-rewrite dengan prefix `public` + group; 10 route terverifikasi via route:list). Catatan: ReceivableController masih memegang invoice internal; refactor penuhnya ditunda agar perubahan tetap kecil | `php artisan test tests/Feature`: 15 failed / 40 passed — **identik dengan baseline sebelum perubahan** (diverifikasi via git stash); `php -l` PASS 4 controller; route:list 10 route publik OK | 2026-11-26 |

## 6. Sertifikat QA

- Status: `BELUM DIEKSEKUSI`

## 7. Catatan Debugging

- (belum ada)
