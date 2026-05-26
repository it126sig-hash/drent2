# Aturan & UI Guideline: Pemilihan Unit Kendaraan

Dokumen ini merupakan standar acuan (*guideline*) berdasarkan implementasi di `BookingCreateView.vue` (dan sinkronisasi di `BookingDetailView.vue`). Aturan ini **WAJIB** diaplikasikan pada setiap antarmuka (UI) di halaman mana pun yang memiliki fitur pemilihan, penambahan, atau pengaturan (edit) unit kendaraan.

---

## 1. Implementasi Dropdown / Select (Server-Side)
> [!IMPORTANT]
> Hindari mengambil (fetch) seluruh data unit ke *frontend* sekaligus untuk mencegah masalah performa saat data membesar.

- Seluruh input *Dropdown/Select* (Unit Kendaraan, Driver, Kota, Paket Sewa, Tipe Biaya Operasional) harus menggunakan mekanisme **Server-Side Search/Loading**.
- Manfaatkan fungsi *debouncing* (misal: 300 milidetik jeda setelah berhenti mengetik) pada *event* `@filter` dari PrimeVue Dropdown, sebelum menembak API.
- Selama menembak API, berikan indikator *loading* visual (`:loading="true"` pada komponen) agar pengguna mengetahui proses pencarian sedang berjalan.

## 2. Kemampuan Pencarian (Multi-Atribut)
- Fitur *search* unit kendaraan yang disuplai oleh *backend* harus fleksibel. Pengguna dapat mengetik satu *keyword* secara bebas dan sistem akan otomatis mencocokkannya dengan:
  - **Nama Pemilik** (Rental Owner)
  - **Tipe / Merk Mobil**
  - **Nomor Polisi (Nopol)**

## 3. Filter Kota Otomatis
- Dalam setiap modal atau tampilan "Pilih Unit", **wajib** menyertakan Dropdown **Filter Kota**.
- Filter Kota akan secara otomatis **terisi (*default value*)** menyesuaikan dengan data "Kota Sewa" pada dokumen *booking* atau form awal.
- List unit yang muncul di Dropdown Unit Kendaraan harus otomatis terfilter berdasarkan kota ini. 
- Pengguna tetap dapat menghapus (*clear*) nilai pada Filter Kota jika mereka sengaja ingin melakukan *override* dan mencari unit dari lintas kota.

## 4. Validasi Jadwal (Schedule Check) Berbasis API
> [!CAUTION]
> Jangan hanya bergantung pada pengecekan status unit (`Ready` / `Out`) di frontend karena mengabaikan jadwal booking di masa mendatang atau riwayat sebelumnya. 

- Pengecekan ketersediaan unit **wajib** divalidasi ke API `checkUnitSchedule` sebelum proses `submit/save` dilakukan (mengirimkan parameter `unit_id`, `tgl_sewa`, dan `tgl_kembali`).
- **Jika jadwal bentrok (API merespons `available: false`):** 
  - Proses *submit* ditahan.
  - Tampilkan `ConfirmDialog` (*Popup* Peringatan) dengan pesan: *"Unit sudah dijadwalkan/sedang berjalan pada tanggal tersebut. Tetap gunakan unit ini?"*
  - Jika pengguna mengklik **OK**, proses *submit* dilanjutkan (dianggap *override* paksa). Jika mengklik Batal, proses dihentikan.

## 5. UI dan Logika Kalkulasi Harga (Readonly)
> [!WARNING]
> Input Harga Mobil dan Harga All-In tidak boleh bisa diedit bebas oleh pengguna, kecuali pada skenario *override* manual yang ekstrem. Kolom harus menggunakan kelas UI visual yang menandakan kolom tersebut terkunci (misalnya warna latar `bg-slate-50`).

### A. Harga Unit Dasar (Non-All In)
- Harga mobil akan terisi secara otomatis mengikuti **Harga Harian** yang ada di tabel master `Units` begitu pengguna selesai memilih unit.
- Input diset sebagai **Readonly**.

### B. Harga All-In
- Kolom "Harga All In" juga diset **Readonly**.
- Penentuan harga All-In mengikuti kondisi hierarki berikut:
  1. **Jika Paket Sewa/Pricing Package TIDAK DIPILIH**: Sistem akan otomatis mengambil harga All-In bawaan dari master tabel `Units` tersebut.
  2. **Jika Paket Sewa/Pricing Package DIPILIH**: Sistem akan mengabaikan harga All-In bawaan unit, dan melakukan *override* menggunakan nilai harga dari **Paket** yang dipilih.

---

Dengan mematuhi paduan di atas, sistem pemilihan unit di Drent akan selalu seragam, terlindungi dari *double-booking* (berkat validasi jadwal), dan meminimalisasi kesalahan input harga (*human error*) oleh staf operasional.
