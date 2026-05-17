# Modul Setting: Manajemen Akses Modul per User

Fitur ini memungkinkan superadmin/admin_branch mengatur modul mana saja yang bisa diakses oleh setiap user terdaftar. Berbeda dari sistem role yang bersifat global, fitur ini memberikan kontrol **granular per-user** — misalnya, seorang user dengan role `cs` bisa direstriksi hanya ke modul Booking dan Cek Fisik, tanpa bisa membuka modul Finance.

---

## User Review Required

> [!IMPORTANT]
> **Keputusan Desain: Pendekatan Permission**
>
> Ada dua pendekatan yang bisa dipilih:
>
> **Opsi A — Whitelist Modul per User (Direkomendasikan)**
> Setiap user punya daftar "modul yang diizinkan". Jika kosong = ikuti default role. Lebih fleksibel, cocok untuk override case per case.
>
> **Opsi B — Override Role per User**
> Tambah kolom override role per user untuk kasus-kasus khusus. Lebih simpel tapi kurang granular.
>
> **Plan ini menggunakan Opsi A** (whitelist modul per user via tabel pivot). Konfirmasi sebelum eksekusi.

> [!IMPORTANT]
> **Apakah akses modul juga mengontrol navigasi sidebar?**
> Plan ini mengasumsikan: ya, sidebar akan menyembunyikan menu yang tidak diizinkan secara otomatis di frontend. Konfirmasi ini sudah sesuai ekspektasi.

> [!WARNING]
> **Superadmin tidak terpengaruh oleh setting modul.**
> Superadmin selalu mendapat akses penuh ke semua modul, tanpa bisa dibatasi. Apakah ini sudah sesuai?

---

## Open Questions

1. **Apakah driver_tetap termasuk dalam scope setting ini?** Driver punya tampilan mobile khusus — apakah modulnya juga perlu diatur?
2. **Apakah ada modul yang selalu wajib diakses semua user?** (misal: Dashboard)? Plan ini mengasumsikan Dashboard selalu tersedia.
3. **Siapa saja yang boleh mengakses halaman Setting ini?** Plan ini mengasumsikan hanya `superadmin` dan `admin_branch`.

---

## Daftar Modul yang Dikendalikan

Berikut daftar modul yang akan dijadikan unit kontrol akses:

| Module Key | Label | Default Role |
|---|---|---|
| `dashboard` | Dashboard | Semua |
| `booking` | Booking & Transaksi | Semua kecuali driver_tetap |
| `physical_check` | Cek Fisik | cs, admin_branch, superadmin |
| `finance` | Keuangan | finance, admin_branch, superadmin |
| `receivable` | Piutang & Invoice | finance, admin_branch, superadmin |
| `member` | Master Member | cs, admin_branch, superadmin |
| `customer` | Master Customer | cs, admin_branch, superadmin |
| `unit` | Master Unit | admin_branch, superadmin |
| `driver` | Master Driver | admin_branch, superadmin |
| `rental_owner` | Pemilik Kendaraan | admin_branch, superadmin |
| `user_management` | Manajemen User | superadmin, admin_branch |
| `master_data` | Data Master (Kota, Paket, dll) | admin_branch, superadmin |
| `supervisor` | Persetujuan Supervisor | supervisor, superadmin |
| `driver_operational` | Operasional Driver | driver_tetap |
| `settings` | Setting Akses | superadmin, admin_branch |

---

## Proposed Changes

### Backend — Database

#### [NEW] Migration: `create_user_module_accesses_table`
Path: `backend/database/migrations/xxxx_create_user_module_accesses_table.php`

```sql
user_module_accesses
  - id (bigint, PK)
  - user_id (FK → users.id, cascade delete)
  - module_key (varchar, e.g. 'booking', 'finance')
  - created_at / updated_at
  UNIQUE (user_id, module_key)
```

---

### Backend — Model & Relationships

#### [MODIFY] [User.php](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/backend/app/Models/User.php)
- Tambah relasi `moduleAccesses()` → hasMany `UserModuleAccess`
- Tambah helper `hasModuleAccess(string $moduleKey): bool`

#### [NEW] `UserModuleAccess.php`
Path: `backend/app/Models/UserModuleAccess.php`
- Model sederhana dengan fillable `['user_id', 'module_key']`

---

### Backend — API Layer

#### [NEW] `UserModuleAccessController.php`
Path: `backend/app/Http/Controllers/Api/V1/UserModuleAccessController.php`

Endpoint:
- `GET /api/v1/users/{user}/module-accesses` — ambil daftar modul yang diizinkan untuk user tertentu
- `PUT /api/v1/users/{user}/module-accesses` — replace seluruh daftar modul (sync operation)

#### [NEW] `UpdateUserModuleAccessRequest.php`
Path: `backend/app/Http/Requests/UpdateUserModuleAccessRequest.php`
- Validasi: `modules` array of strings, tiap string harus ada di daftar valid module keys

#### [NEW] `UserModuleAccessResource.php`
Path: `backend/app/Http/Resources/UserModuleAccessResource.php`
- Return list module keys yang diizinkan

#### [MODIFY] `AuthController.php` — endpoint `/me`
Sertakan `module_accesses` dalam respons `/me` agar frontend bisa cache-nya saat login.

#### [MODIFY] `api.php` (routes)
Tambah routes baru:
```php
Route::get('users/{user}/module-accesses', [UserModuleAccessController::class, 'index']);
Route::put('users/{user}/module-accesses', [UserModuleAccessController::class, 'update']);
```

#### [NEW] `UserModuleAccessService.php`
Path: `backend/app/Services/UserModuleAccessService.php`
- Method `sync(User $user, array $modules): void` — hapus semua lama, insert baru (atomic)
- Method `getForUser(User $user): array` — return array module keys

---

### Frontend — Auth Store

#### [MODIFY] [auth.js](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/frontend/src/stores/auth.js)
- Tambah state `moduleAccesses: []`
- Tambah getter `hasModuleAccess(key)` → cek apakah key ada di array
- Saat login (`setAuth`), simpan `module_accesses` dari respons `/me`
- Getter `canAccessModule(key)`:
  - Jika user adalah `superadmin` → always true
  - Jika array kosong → fallback ke default role-based
  - Jika array ada isi → cek whitelist

---

### Frontend — Router Guard

#### [MODIFY] [index.js](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/frontend/src/router/index.js)
- Tambah meta `module` pada setiap route, misal `meta: { module: 'booking' }`
- Perbarui `beforeEach` guard:
  ```js
  // Existing role check tetap ada
  // Tambah module access check:
  if (to.meta.module && !auth.canAccessModule(to.meta.module)) {
    return { name: 'dashboard' }
  }
  ```

---

### Frontend — API Layer

#### [NEW] `src/api/settings.js`
- `getUserModuleAccesses(userId)` → GET endpoint
- `updateUserModuleAccesses(userId, modules)` → PUT endpoint

---

### Frontend — Composable

#### [NEW] `src/composables/useModuleAccess.js`
- `fetchModuleAccesses(userId)`
- `saveModuleAccesses(userId, modules)`
- State: `accesses`, `loading`, `saving`

---

### Frontend — View (Halaman Setting)

#### [NEW] `src/views/settings/SettingModuleAccessView.vue`
Halaman utama Setting → sub-tab "Hak Akses Modul".

**Layout:**
- Tabel/list users di sisi kiri (dengan filter nama/role/branch)
- Panel kanan: checklist modul untuk user yang dipilih
- Tombol "Simpan" untuk commit perubahan

**UI Components (PrimeVue):**
- `DataTable` untuk list user
- `Checkbox` per modul (dikelompokkan: Master Data, Operasional, Keuangan, dll)
- `Button` Simpan dengan loading state
- `Toast` untuk feedback sukses/error

**Visual notes (Drent Design System):**
- Layout dua kolom desktop (left: 360px, right: flex-grow)
- Card background `#F0F2F8` untuk panel checklist
- Badge status per modul (aktif/tidak aktif)

---

### Frontend — Sidebar / Navigation

#### [MODIFY] `AppLayout.vue` (atau komponen sidebar)
- Setiap menu item di sidebar dicek dengan `auth.canAccessModule(key)` sebelum ditampilkan
- Menu yang tidak diizinkan disembunyikan (bukan hanya disabled)

---

### Frontend — Router

#### [MODIFY] [index.js](file:///c:/Users/Salivs/Data/laragon/www/drent-vibe/frontend/src/router/index.js)
- Tambahkan route baru:
  ```js
  {
    path: '/settings/module-access',
    name: 'SettingModuleAccess',
    component: () => import('../views/settings/SettingModuleAccessView.vue'),
    meta: { roles: ['superadmin', 'admin_branch'], module: 'settings' }
  }
  ```

---

## Urutan Eksekusi

```
1. Migration & Model (UserModuleAccess)
2. Service (UserModuleAccessService)
3. FormRequest + Controller + Resource
4. Update routes/api.php
5. Update /me response di AuthController
6. Update auth.js store (state + getter)
7. Update router/index.js (meta module + guard)
8. Buat src/api/settings.js
9. Buat composable useModuleAccess.js
10. Buat SettingModuleAccessView.vue
11. Update sidebar (AppLayout.vue)
```

---

## Verification Plan

### Automated / Manual API Tests
- `PUT /api/v1/users/{user}/module-accesses` dengan payload valid → 200 OK, modul tersimpan
- `GET /api/v1/users/{user}/module-accesses` → return array module keys
- Login sebagai user yang sudah dikonfigurasi → `/me` return `module_accesses` yang benar
- User `superadmin` tidak terpengaruh oleh setting → selalu dapat akses semua

### Frontend Verification
- Login sebagai user dengan modul terbatas → sidebar hanya tampilkan modul yang diizinkan
- Akses URL langsung modul yang tidak diizinkan → redirect ke dashboard
- Admin buka Setting → pilih user → centang/uncentang modul → simpan → user login ulang → akses sesuai konfigurasi

### Browser Test
- Buka halaman Setting dengan akun `superadmin` → edit akses user `cs`
- Pastikan checklist modul tampil, bisa disimpan, dan berpengaruh pada navigasi user tersebut
