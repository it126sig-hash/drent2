# DRENT - Acuan Hitung Biaya Operasional Driver

Dokumen ini menjadi acuan perhitungan laporan biaya operasional driver. Fokus utamanya adalah memisahkan arus kas perusahaan dari catatan pemakaian saldo driver, supaya laporan tidak menghitung biaya operasional dua kali.

## Prinsip Utama

- Deposit finance ke driver dihitung sebagai **pengeluaran** perusahaan.
- Bon driver yang memakai deposit dihitung sebagai **catatan realisasi/pemakaian saldo**, bukan pengeluaran baru.
- Pengembalian sisa saldo driver dihitung sebagai **pemasukan** perusahaan.
- Realisasi langsung oleh finance tanpa deposit dihitung sebagai **pengeluaran langsung** perusahaan.

## Aturan Sumber Data

| Kategori laporan | Sumber data | Filter utama | Perlakuan laporan |
| --- | --- | --- | --- |
| Pengeluaran deposit | `driver_operational_funds` | `fund_type = operational`, `payment_account_id IS NOT NULL`, status valid | Masuk ke pengeluaran operasional |
| Catatan bon driver | `driver_operational_expenses` | `driver_operational_fund_id IS NOT NULL`, `type = expense` | Catatan realisasi/pemakaian saldo, tidak menambah pengeluaran |
| Pemasukan pengembalian | `driver_operational_expenses` | `type = return` | Masuk ke pemasukan operasional |
| Pengeluaran realisasi langsung | `driver_operational_expenses` | `driver_operational_fund_id IS NULL`, `source = finance`, `type = expense` | Masuk ke pengeluaran operasional |

Status valid untuk deposit mengikuti alur dana yang masih berlaku secara finansial:

```text
pending_driver_acceptance
accepted
closed
```

Status valid untuk bon, pengembalian, dan realisasi langsung adalah transaksi yang sudah efektif secara finansial:

```text
approved
void_requested
```

Catatan: `void_requested` masih dihitung sampai request void disetujui. Jika void sudah disetujui, transaksi berubah menjadi tidak valid untuk laporan berjalan.

## Formula Laporan

```text
Total Pengeluaran Operasional =
  Total Deposit OP Valid
  + Total Realisasi Langsung Finance Valid

Total Pemasukan Operasional =
  Total Pengembalian Sisa Saldo Valid

Catatan Realisasi Driver =
  Total Bon Driver dari Deposit

Net Biaya Operasional =
  Total Pengeluaran Operasional - Total Pemasukan Operasional
```

## Contoh Perhitungan

### Contoh 1: Deposit ke driver

Finance mencairkan deposit operasional Rp500.000 ke driver. Driver kemudian input bon Rp350.000, lalu mengembalikan sisa saldo Rp150.000.

Hasil laporan:

| Komponen | Nominal | Perlakuan |
| --- | ---: | --- |
| Deposit OP | Rp500.000 | Pengeluaran |
| Bon driver | Rp350.000 | Catatan realisasi, bukan pengeluaran baru |
| Pengembalian saldo | Rp150.000 | Pemasukan |

Ringkasan:

```text
Total Pengeluaran Operasional = Rp500.000
Total Pemasukan Operasional   = Rp150.000
Catatan Realisasi Driver      = Rp350.000
Net Biaya Operasional         = Rp350.000
```

### Contoh 2: Realisasi langsung finance

Finance langsung membayar biaya operasional Rp200.000 tanpa membuat deposit ke driver.

Hasil laporan:

| Komponen | Nominal | Perlakuan |
| --- | ---: | --- |
| Realisasi langsung finance | Rp200.000 | Pengeluaran langsung |

Ringkasan:

```text
Total Pengeluaran Operasional bertambah Rp200.000
```

## Batasan Implementasi

- Jangan menjumlahkan `Deposit OP + Bon Driver` sebagai pengeluaran, karena bon driver dari deposit hanya menjelaskan pemakaian dana yang sudah keluar saat deposit.
- Kolom `Realisasi OP` di UI operasional finance saat ini berisi campuran antara bon dari deposit dan realisasi langsung finance. Report harus memilahnya dengan `driver_operational_fund_id`.
- Jika `driver_operational_fund_id IS NOT NULL`, transaksi expense adalah pemakaian saldo driver.
- Jika `driver_operational_fund_id IS NULL`, `source = finance`, dan `type = expense`, transaksi expense adalah pengeluaran langsung finance.
- Jika report per rekening atau bank dibutuhkan untuk realisasi langsung finance, perlu penambahan `payment_account_id` pada `driver_operational_expenses`, karena realisasi langsung saat ini belum menyimpan rekening sumber.

## Referensi Implementasi Saat Ini

- UI review operasional: `frontend/src/views/finance/OperationalCostListView.vue`
- Logika backend dana operasional: `backend/app/Services/DriverOperationalFundService.php`
- Resource expense operasional: `backend/app/Http/Resources/DriverOperationalExpenseResource.php`
- Laporan finance bulanan: `backend/app/Services/MonthlyFinanceReportService.php`
