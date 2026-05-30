---
trigger: always_on
---

# Drent Mobile Design Rules

Aturan ini memastikan setiap halaman **mobile-friendly tanpa merusak tampilan desktop**.
Prinsip utama: **desktop adalah baseline yang sudah benar**. Semua penyesuaian mobile
HANYA boleh ditulis di dalam media query `@media (max-width: 768px)` atau dengan
percabangan `isMobile`. Jangan pernah mengubah style desktop untuk memperbaiki mobile.

Aturan ini melengkapi `design.md` (token warna, tipografi, spacing). Saat ada konflik,
`design.md` menang untuk desktop; aturan di sini menang untuk viewport `<= 768px`.

---

## 1. Breakpoint

| Nama | Lebar | Sumber kebenaran |
|---|---|---|
| **Mobile** | `<= 768px` | `@media (max-width: 768px)` **atau** `isMobile = window.innerWidth < 768` |
| **Desktop** | `>= 769px` | default + `@media (min-width: 769px)` |

- Satu breakpoint saja (768). Jangan menambah breakpoint baru per-halaman kecuali benar-benar perlu.
- Style layout-fill desktop (`.table-page-active`, `.list-tab-fill`, `.table-shell`) sudah dibungkus `@media (min-width: 769px)` — biarkan, jangan diubah.

---

## 2. Tab Toggle (`.tab-toggle-container`)

- Di mobile: lebar **100%**, dan jika isi melebihi layar → **scroll horizontal** (bukan wrap/terpotong).
- Scrollbar disembunyikan, scroll tetap jalan (touch).
- `.pill-toggle` tetap `min-width: max-content` agar bisa di-scroll, jangan dipaksa `flex-wrap`.

```css
/* base (berlaku semua ukuran) — sudah ada di style.css */
.tab-toggle-container {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.tab-toggle-container::-webkit-scrollbar { display: none; }

@media (max-width: 768px) {
  .tab-toggle-container { width: 100% !important; }
  .pill-toggle { min-width: max-content; flex-wrap: nowrap; }
}
```

---

## 3. Tombol Primary (`.btn-pill.btn-primary`)

- Di mobile, tombol aksi primary **selalu 100% lebar** dan teks rata tengah.
- Berlaku juga untuk tombol primary di header, filter actions, card footer, dan dialog footer.
- Desktop tetap `inline-flex` (tidak berubah).

```css
@media (max-width: 768px) {
  .btn-pill.btn-primary,
  .header-actions .btn-pill.btn-primary,
  .filter-actions .btn-pill,
  .card-footer .btn-pill,
  .app-dialog-button-primary {
    width: 100%;
    justify-content: center;
  }
}
```

---

## 4. Card — border & konsistensi

- **Satu kelas kanonik**: container list di mobile = `.mobile-card-list`, tiap item = `.mobile-card`.
  Hindari kelas card khusus per halaman (`operational-card`, dll.) untuk struktur dasar; pakai `.mobile-card`
  lalu tambahkan modifier bila perlu.
- Di mobile, **setiap card diberi `border: 1px solid var(--text-secondary)`** agar batas antar card jelas saat ditumpuk.
  - Catatan: `design.md` poin #7 melarang border berwarna antar tile **di desktop**. Aturan border ini **mobile-only**, dan `--text-secondary` adalah abu-abu netral, jadi tidak melanggar semangat desktop. Desktop tetap pakai `--surface-border`.
- Struktur card konsisten: `card-header` (judul + status), `card-body` (pasangan label/value), `card-footer` (aksi).

```css
.mobile-card-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.mobile-card {
  background: var(--surface-default);
  border-radius: var(--radius-default);
  padding: var(--space-md);
  box-shadow: var(--shadow-tile);
}

.mobile-card .card-header,
.mobile-card .card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-md);
}
.mobile-card .card-body {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: var(--space-md) 0;
}

@media (max-width: 768px) {
  .mobile-card { border: 1px solid var(--text-secondary); }
}
```

---

## 5. Card Filter — JANGAN tinggi

Bug saat ini: di Biaya Operasional, kartu filter menjadi sangat tinggi karena aturan mobile
memaksa `.filter-groups { flex-direction: column }` sehingga semua field (cari, status, dari, sampai)
menumpuk vertikal.

Aturan: di mobile, filter dirangkai dalam **grid 2 kolom** yang ringkas, bukan tumpukan 1 kolom.
- Field lebar (pencarian) dan grup status → `grid-column: 1 / -1` (full width).
- Field pendek (tanggal dari/sampai, dropdown) → berbagi baris 2 kolom.
- Card filter `height: auto`, tidak pernah ikut stretch flex parent.

```css
@media (max-width: 768px) {
  .filter-bar {
    height: auto !important;
    max-height: none !important;
    flex: 0 0 auto;          /* jangan stretch dari list-tab-fill */
  }

  .filter-groups,
  .advanced-filter-groups {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-md);
    width: 100%;
  }

  .filter-group { width: auto; min-width: 0; }

  /* field yang harus penuh selebar baris */
  .filter-group-wide,
  .filter-group-status,
  .filter-search,
  .filter-group:has(.status-filter-buttons) {
    grid-column: 1 / -1;
  }
}
```

> Jika sebuah halaman punya >4 field filter, pertimbangkan filter collapsible (default tertutup) — tapi grid 2 kolom sudah cukup untuk mayoritas halaman.

---

## 6. Tabel → Card Grid di Mobile (pola kanonik)

Setiap halaman list **wajib** menampilkan data sebagai card di mobile, bukan tabel yang ter-scroll horizontal. Pola yang sudah dipakai mayoritas view (jadikan standar):

**Script:**
```js
import { ref, onMounted, onUnmounted } from 'vue'
const isMobile = ref(window.innerWidth < 768)
const onResize = () => { isMobile.value = window.innerWidth < 768 }
onMounted(() => window.addEventListener('resize', onResize))
onUnmounted(() => window.removeEventListener('resize', onResize))
```

**Template:**
```vue
<!-- Desktop: DataTable -->
<div v-if="!isMobile" class="table-shell">
  <DataTable :value="rows" class="drent-datatable"> ... </DataTable>
</div>

<!-- Mobile: card grid -->
<div v-else class="mobile-card-list">
  <div v-for="row in rows" :key="row.id" class="mobile-card">
    <div class="card-header"> <strong>{{ row.title }}</strong> <StatusBadge .../> </div>
    <div class="card-body">
      <div><span class="field-hint">Label</span> {{ row.value }}</div>
    </div>
    <div class="card-footer"> <button class="btn-pill ...">Aksi</button> </div>
  </div>
</div>
```

Aturan:
- Mobile **tidak** me-render DataTable (hindari scroll horizontal & beban render).
- Card menampilkan kolom paling penting; aksi pakai `.btn-pill` (lihat aturan #3).
- Jangan duplikasi definisi `isMobile` jika nanti dibuat composable `useIsMobile()` — utamakan composable bersama.

---

## 7. Penyesuaian Umum Mobile (sudah ada di `style.css`, pertahankan)

- Spacing token diperkecil di mobile (`--space-2xl: 16px`, dst).
- Input/select/datepicker/btn `min-height: 40px` (target sentuh).
- `page-container` padding `--space-lg`.
- `header-actions` full width, subtitle `p` di header disembunyikan.

---

## 8. Checklist verifikasi per halaman

1. [ ] Tab toggle 100% & bisa di-scroll horizontal.
2. [ ] Tombol primary 100% di mobile.
3. [ ] Tiap card pakai `.mobile-card` + border `--text-secondary`.
4. [ ] Card filter ringkas (grid 2 kolom), tidak tinggi.
5. [ ] Style card konsisten dengan halaman lain.
6. [ ] Tabel berubah jadi card grid di mobile (tanpa scroll horizontal).
7. [ ] Tampilan **desktop tidak berubah** (uji ≥769px).
