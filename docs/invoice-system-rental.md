# Invoice System — Sistem Rental Mobil

## Konteks

Sistem rental mobil internal yang sedang dikembangkan. Invoice bukan hanya dokumen internal — akan berfungsi sebagai **halaman live customer-facing** sekaligus **halaman pembayaran resmi** dengan payment gateway terintegrasi.

---

## Keputusan Arsitektur Utama

**Invoice bersifat immutable setelah diterbitkan.**

Invoice yang sudah di-*issue* tidak boleh diubah. Penambahan biaya apapun setelah invoice diterbitkan harus dibuat sebagai dokumen terpisah yang mereferensikan invoice asal. Ini bukan preferensi — ini keharusan karena:

- Ada payment gateway yang mencatat invoice number per transaksi
- Ada kebutuhan laporan keuangan
- Mutable invoice menciptakan mismatch rekonsiliasi dan tidak dapat diaudit

---

## Jenis Dokumen

### Invoice Original
Dibuat saat awal rental dikonfirmasi. Berisi biaya sewa pokok.

### Additional Invoice
Dibuat untuk setiap penambahan biaya di luar invoice original. Selalu mereferensikan invoice asal (`parent_invoice_id`).

Jenis tambahan biaya yang diantisipasi:

| Tipe | Contoh | Keterangan |
|------|--------|------------|
| `extend` | Perpanjangan sewa X hari | Bisa terjadi sebelum/saat jatuh tempo |
| `penalty` | Denda keterlambatan, BBM, parkir | Umumnya muncul setelah rental selesai |
| `other` | Biaya tak terduga lainnya | Exceptional, perlu catatan |

---

## Alur Status Invoice

```
draft → issued → partial_paid → paid
                      ↑
         Tidak bisa diedit setelah "issued"
```

Invoice yang sudah berstatus `paid` tidak dapat diubah dalam kondisi apapun.

---

## Live Page (Customer-Facing)

Live page **bukan** menampilkan satu invoice yang berubah-ubah. Live page menampilkan **summary per rental** — semua invoice yang terkait dengan satu transaksi rental, beserta status pembayarannya.

```
Rental #R-001 — Toyota Avanza

Invoice Utama
─────────────────────────────────────────
INV-001   Sewa 3 hari          Rp 900.000   ✓ Lunas

Tagihan Tambahan
─────────────────────────────────────────
INV-001-A  Extend 1 hari       Rp 300.000   ✓ Lunas
INV-001-B  Denda BBM + Parkir  Rp  85.000   ● Belum Dibayar

                              [Bayar Sekarang →]
```

Tombol bayar hanya muncul untuk invoice yang outstanding. Payment gateway menerima amount dari invoice spesifik — bukan total gabungan yang ambigu.

---

## Skenario Penambahan Biaya

| Kondisi | Tindakan |
|---------|----------|
| Invoice masih `draft`, belum ada pembayaran | Boleh edit langsung |
| Invoice sudah `issued`, belum ada pembayaran | Buat additional invoice baru |
| Sudah ada pembayaran sebagian | Buat additional invoice baru |
| Invoice sudah `paid` (lunas) | Buat additional invoice baru — status rental kembali outstanding |

---

## Struktur Data (High-Level)

```
rentals
  └── invoices[]          (original + additional)
        └── invoice_items[]
  └── payments[]          (terikat ke invoice spesifik)
```

Field penting yang perlu ada:

- `invoices.parent_invoice_id` — menghubungkan additional invoice ke invoice asal
- `invoices.type` — `original | extend | penalty | other`
- `invoices.status` — `draft | issued | partial_paid | paid | void`
- `invoices.payment_gateway_order_id` — ID dari payment gateway untuk rekonsiliasi
- `rentals.rental_page_token` — unique token untuk URL live page customer
- `payments.gateway_transaction_id` — ID transaksi dari payment gateway

---

## Kebutuhan Laporan Keuangan

Struktur immutable invoice memungkinkan laporan yang konsisten:

- Pendapatan per periode (sum dari payments yang settled)
- Outstanding per rental
- Breakdown per jenis biaya (sewa vs extend vs denda)
- Rekonsiliasi: payments di sistem vs settlement payment gateway

---

## Hal yang Belum Diputuskan

- **Approval flow untuk additional invoice**: apakah additional invoice perlu persetujuan customer sebelum muncul di live page dan bisa dibayar, atau langsung visible begitu staff input? Ini berpengaruh ke UX, notifikasi, dan potensi dispute di payment gateway — terutama untuk tagihan denda.

---

## Catatan Implementasi

> Detail implementasi (model, controller, view, integrasi payment gateway) ditentukan menyesuaikan kondisi project yang sudah ada. Dokumen ini adalah panduan arsitektur dan keputusan desain, bukan spesifikasi teknis final.
