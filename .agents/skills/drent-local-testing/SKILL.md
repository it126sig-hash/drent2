---
name: drent-local-testing
description: Mandatory before any local DRENT web, browser, E2E Playwright, manual UI, or authenticated HTTP test. Defines the fixed local URLs, seeded test account, viewport checks, and credential hygiene rules for the drent-vibe workspace.
---

# DRENT Local Testing

Gunakan pengaturan ini **hanya** untuk pengembangan lokal DRENT di `c:\Users\Salivs\Data\laragon\www\drent-vibe`.

## Activation Requirement

Baca dan aktifkan skill ini sebelum membuka aplikasi DRENT di browser, menjalankan Playwright E2E, atau mengirim request HTTP terautentikasi ke backend lokal. Pertahankan aktif selama login, eksekusi uji, verifikasi, hingga pelaporan.

*(CLI unit/feature test `php artisan test` yang tidak membuka aplikasi berjalan tidak membutuhkan langkah browser.)*

## Local URLs

- **Frontend (Vite Dev Server):** `http://localhost:5173`
- **Backend API (Laragon):** `http://localhost/drent-vibe/backend/public/api` (prefix endpoint `/v1/*`)
- Jangan diam-diam mengganti origin lokal ke URL staging/produksi atau domain publik (seperti ngrok). Jika aplikasi me-redirect ke luar origin lokal secara tak terduga, berhenti dan laporkan sebelum memasukkan kredensial.

## Local Test Accounts

Akun default yang tersedia dari `backend/database/seeders/DatabaseSeeder.php`:

| Role | Scope | Email | Password |
| --- | --- | --- | --- |
| **`superadmin`** | Semua fitur & semua branch (`Pusat Jakarta` default) | `admin@drent.id` | `admin123` |

> **Catatan Role Lain (`admin_branch`, `finance`, `driver_tetap`, `cs`, `teknisi`):** Jika skenario uji membutuhkan role selain `superadmin` dan akun uji lokal untuk role tersebut belum diketahui, tanyakan ke user atau periksa data user lokal tanpa menebak atau mereset password secara sepihak.

## Testing & Hygiene Rules

1. Perlakukan semua kredensial di atas murni sebagai kredensial uji lokal. **Dilarang** menggunakannya pada origin non-lokal.
2. Masukkan password hanya pada form login lokal. **Jangan pernah** menyalin password ke dalam source code, file test permanen, screenshot, pesan commit, dokumen `docs/`, atau laporan chat.
3. Mulai pengujian UI dari `http://localhost:5173` dan ikuti alur login aktual aplikasi.
4. Jika menguji beberapa role, logout di antara akun atau gunakan konteks browser terpisah, lalu konfirmasi role/branch aktif setelah login.
5. Untuk pengujian UI/layout, selalu verifikasi minimal:
   - **Desktop:** `1366x768` (dan `1280x800` jika menyentuh tabel/compact density).
   - **Mobile:** `390x844` (pastikan sesuai `.agents/rules/mobile.md`, tanpa horizontal scroll tabel).
6. Jika login gagal atau dev server belum aktif, laporkan gejala dan status layanan secara objektif; jangan mereset password atau memutasi data auth tanpa permintaan eksplisit.
7. Laporkan role yang diuji, URL lokal, viewport, skenario yang diuji, hasil `browser_console_messages`/`browser_network_requests`, dan hasil yang teramati (tanpa mengulang password).
