# GEMINI.md — DRENT Project Context

## Project Overview

**DRENT** adalah aplikasi manajemen operasional dan keuangan rental mobil internal berbasis monorepo (`backend/` + `frontend/`), melayani web desktop HD-compact (1366x768+), mobile browser (`<= 768px`), dan aplikasi mobile native via Capacitor.

### Key Technologies
- **Backend (`backend/`):** PHP `^8.3`, Laravel `^13.7`, Laravel Sanctum `^4.0`, MySQL, `barryvdh/laravel-dompdf` (PDF), PHPUnit `^12.5`
- **Frontend (`frontend/`):** Vue `^3.5` (Composition API `<script setup>`), Vite `^8.0`, PrimeVue `^4.5` (`@primevue/themes`), Tailwind CSS `^4.3`, Pinia `^3.0`, Vue Router `^5.0`, Axios, `@vueuse/core`, `date-fns`, `xlsx`, `jspdf`/`html2canvas`, `@tiptap/vue-3`
- **Mobile Runtime:** Capacitor `^8.3` (`@capacitor/core`, `@capacitor/android`, `@capacitor/ios`, `@capacitor/app`)
- **Currency & Locale:** IDR integer penuh (tanpa desimal, tanpa multi-currency), tanggal ISO 8601 (`YYYY-MM-DD`)

---

## Architecture & Directory Structure

```text
drent-vibe/
├── backend/
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/   # Thin API Controllers (delegasi ke Service)
│   │   │   ├── Middleware/           # branch.scope, dll.
│   │   │   ├── Requests/             # FormRequest wajib (Store*, Update*)
│   │   │   └── Resources/            # JsonResource / ResourceCollection wajib
│   │   ├── Models/                   # Eloquent Models (SoftDeletes default)
│   │   ├── Policies/                 # Otorisasi RBAC per model
│   │   ├── Services/                 # Business logic, kalkulasi, transaksi DB
│   │   ├── Exceptions/               # Custom domain exceptions
│   │   └── Traits/ & Helpers/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/                  # DatabaseSeeder, RolePermissionSeeder, dll.
│   ├── routes/api.php                # Semua endpoint di bawah /api/v1/*
│   └── tests/                        # Feature & Unit tests (PHPUnit)
├── frontend/
│   ├── src/
│   │   ├── api/                      # Axios instance (axios.js) & modul API (/v1/*)
│   │   ├── components/               # Komponen reusable per domain & layout (AppLayout.vue)
│   │   ├── composables/              # useBooking, useUnit, usePermission, dll.
│   │   ├── router/                   # Vue Router routes
│   │   ├── stores/                   # Pinia store global (auth.js)
│   │   ├── utils/ & constants/
│   │   ├── views/                    # Halaman per modul (bookings, finance, master, dll.)
│   │   └── style.css                 # DrentDesign tokens, desktop compact & mobile CSS
│   └── capacitor.config.json
├── docs/                             # Manual, arsitektur fitur, dan log kerja YYYY-MM-DD-*.md
└── .agents/                          # Rules & Skills orkestrasi multi-agen DRENT
```

---

## Core Modules

- **Booking & Transaksi:** Booking (`draft → confirmed → ongoing → completed → cancelled`), detail/modifikasi, pembatalan, link publik progres & invoice.
- **Keuangan:** Pembayaran booking, invoice (single & merged), piutang (`receivables`), rent-to-rent, biaya operasional & bon driver (`driver operational funds`), fleet card, mutasi rekening pembayaran (`payment account mutations`), transaksi, refund.
- **Armada & Operasional:** Unit, cek fisik (`checkout` / `checkin` + tanda tangan & link publik OTP), driver tetap & non-tetap (non-tetap tanpa akun), rental owner.
- **Pelanggan & Member:** Customer, member & perpanjangan, request approval supervisor & my requests.
- **Master, RBAC & Laporan:** Tenant, branch (isolasi `branch_id`), city/province, cost type, pricing package, payment account, invoice terms template, role & permission management, dashboard & laporan penggunaan (unit, driver, customer, transaksi, keuangan bulanan).

---

## Building, Running & Testing

1. **Backend (`backend/`):**
   - Local API Base URL (Laragon): `http://localhost/drent-vibe/backend/public/api`
   - Syntax check: `php -l <file.php>`
   - Route check: `php artisan route:list`
   - Test suite: `php artisan test` (atau `php artisan test tests/Feature/<TestFile>.php`)
2. **Frontend (`frontend/`):**
   - Dev server: `npm run dev` (`http://localhost:5173`)
   - Production build verification: `npm run build`

---

## Development Conventions & Guardrails

- **Backend Strict Layering:** `Thin Controller` $\rightarrow$ `FormRequest` (validasi) $\rightarrow$ `Policy` (`$this->authorize()`) $\rightarrow$ `Service` (business logic + `DB::transaction()`) $\rightarrow$ `JsonResource` (respons). Semua uang dalam **IDR integer** dan query operasional terisolasi `branch_id`.
- **Frontend Discipline:** Ikuti [`.agents/rules/design.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/rules/design.md), [`.agents/rules/mobile.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/rules/mobile.md), dan [`.agents/skills/drent-frontend/SKILL.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/skills/drent-frontend/SKILL.md) (Reuse-First PrimeVue & komponen/composable eksisting, HTTP hanya lewat `src/api/` & `src/composables/`, tabel desktop `DataTable` + mobile `.mobile-card-list`, pagination data banyak, dan compact density `769–1365px`).
- **Multi-Agent Orchestration:** Setiap tugas pengembangan dijalankan melalui Master Orchestrator ([`.agents/rules/drent-orchestrator.md`](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/.agents/rules/drent-orchestrator.md)) dengan Plan Artefak, fase eksekusi berurutan (`Fase A`, `B`, `C`, ...), work doc `docs/YYYY-MM-DD-<slug-nama-pekerjaan>.md`, QA independen (`drent-qa`), dan finalisasi dokumentasi (`drent-docs`).
