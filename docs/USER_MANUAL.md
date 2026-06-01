# DRENT — Panduan Pengguna (User Manual)

Selamat datang di Panduan Pengguna Sistem Manajemen Operasional Rental Mobil **DRENT**. Dokumen ini dirancang untuk memandu seluruh staf internal (Super Admin, Admin Branch, Customer Service, Finance, Cek Fisik, dll) dalam menggunakan fitur-fitur yang ada di dalam sistem.

Sistem ini bersifat tertutup (hanya untuk internal perusahaan) dan digunakan untuk mendigitalisasi seluruh proses operasional mulai dari data referensi, reservasi (booking), keuangan, inspeksi kendaraan, hingga laporan manajerial.

---

## Daftar Isi

1. [Pendahuluan & Akses Sistem](#1-pendahuluan--akses-sistem)
2. [Dashboard Utama](#2-dashboard-utama)
3. [Modul Data Master](#3-modul-data-master)
4. [Modul Booking & Transaksi Sewa](#4-modul-booking--transaksi-sewa)
5. [Modul Keuangan](#5-modul-keuangan)
6. [Modul Cek Fisik](#6-modul-cek-fisik)
7. [Modul Pendukung](#7-modul-pendukung)

---

## 1. Pendahuluan & Akses Sistem

Sistem DRENT dilindungi oleh sistem autentikasi dan otorisasi. Setiap tindakan yang Anda lakukan akan disesuaikan dengan **Role** (peran) dan **Cabang** (branch) Anda.

### 1.1 Cara Login ke dalam Sistem
1. Buka browser (disarankan Google Chrome atau Mozilla Firefox) dan akses tautan (URL) DRENT yang telah diberikan oleh tim IT.
2. Pada halaman **Login**, masukkan **Email** dan **Kata Sandi (Password)** Anda.
3. Klik tombol **Masuk / Login**.
4. Jika kredensial valid, Anda akan diarahkan ke halaman **Dashboard**.

### 1.2 Keluar dari Sistem (Logout)
1. Klik pada inisial nama profil atau foto Anda di pojok kanan atas layar.
2. Pilih opsi **Logout** dari menu dropdown.
3. Anda akan dikembalikan ke halaman Login.

### 1.3 Hak Akses (Role)
Akses Anda terhadap menu-menu di bawah ini ditentukan oleh Role Anda:
*   **Super Admin / Admin Branch:** Akses penuh untuk pengaturan data utama dan konfigurasi cabang.
*   **Customer Service (CS):** Mengelola reservasi (booking) pelanggan dan status transaksi.
*   **Finance:** Mengelola faktur (invoice), kas, pembayaran, piutang, dan pencatatan bon operasional supir.
*   **Tim Cek Fisik:** Mengelola laporan kondisi kendaraan saat keberangkatan dan kepulangan (diakses via mobile).
*   **Driver (Tetap):** Mengunggah (upload) bon operasional.
*   **Teknisi / Surveyor:** Memiliki akses spesifik untuk pendataan servis kendaraan dan survey anggota (member).

---

## 2. Dashboard Utama

Setelah Anda berhasil login, halaman pertama yang akan Anda lihat adalah **Dashboard**. Halaman ini memberikan ringkasan (summary) metrik operasional secara waktu nyata (real-time).

### 2.1 Kartu Statistik (KPI Cards)
Di bagian atas, terdapat kartu metrik yang menampilkan:
*   **Transaksi Aktif:** Jumlah kendaraan yang saat ini sedang disewa (berstatus *Rental Unit*).
*   **Booking Menunggu:** Jumlah pesanan baru (berstatus *Follow Up* atau *Confirm*) yang butuh tindakan CS segera.
*   **Total Piutang:** Total nilai uang (outstanding) yang belum dibayar oleh konsumen.
*   **Unit dalam Pemeliharaan:** Jumlah mobil yang sedang diperbaiki.
*   **Driver dengan Saldo Sisa:** Jumlah supir yang masih memiliki saldo operasional belum dikembalikan/dilaporkan.

### 2.2 Visualisasi Data
*   **Grafik Pendapatan (Revenue):** Menampilkan tren pemasukan kotor selama 12 bulan terakhir.
*   **Utilisasi Unit:** Grafik batang yang menunjukkan seberapa sering suatu unit disewa (hari terpakai dibandingkan hari tersedia).
*   **Distribusi Status Transaksi:** Grafik pie yang menunjukkan rasio status transaksi berjalan.

> **Catatan:** Data yang muncul di Dashboard otomatis ter-filter sesuai dengan Cabang (Branch) dari user yang sedang login.

---

## 3. Modul Data Master

Modul ini adalah pusat data referensi. Semua data di sini harus diisi sebelum transaksi dapat berjalan lancar. Modul ini umumnya hanya bisa diakses oleh **Admin**. Menu ini dapat ditemukan pada *sidebar* di bawah kategori "Master Data".

### 3.1 Data Pelanggan & Member
Mengelola data penyewa kendaraan.

*   **Menambah Pelanggan:**
    1. Buka menu **Pelanggan**, lalu klik tombol **Tambah Pelanggan**.
    2. Isi nama, kontak, alamat, dan kota.
    3. Pilih status pelanggan (Umum, Corporate, Redflag, atau Blacklist).
    4. Klik **Simpan**.
    *Catatan: Pelanggan dengan status 'Redflag' akan memunculkan peringatan, dan 'Blacklist' tidak dapat melakukan pemesanan.*

*   **Pendaftaran Member (Lepas Kunci):**
    1. Pengajuan member diisi oleh peran **Surveyor** pada menu **Survey Member**.
    2. Isi formulir Identitas, Pekerjaan, dan Keluarga, lalu unggah dokumen KTP/KK/Foto.
    3. Status akan menjadi *Pending*.
    4. Admin dapat melakukan tinjauan (review) dan klik tombol **Aktifkan**. Admin juga dapat menggunakan fitur **Fast-Track** untuk aktivasi cepat dengan menyertakan catatan alasan.

### 3.2 Data Pemilik Rental (Rent-to-Rent)
Digunakan untuk mendata pemasok kendaraan (vendor mitra) atau entitas milik sendiri.
1. Buka menu **Pemilik Rental**, lalu klik **Tambah**.
2. Isi identitas pemilik dan detail rekening bank untuk keperluan pembayaran.
3. Centang (checklist) kotak **Milik Sendiri (Is Owner)** jika entitas ini adalah perusahaan Anda sendiri. Jika tidak dicentang, mobil dari pemilik ini akan otomatis memicu hutang *Rent-to-Rent*.

### 3.3 Data Unit Kendaraan (Mobil)
1. Buka menu **Unit Kendaraan**, klik **Tambah Unit**.
2. Masukkan nomor polisi, tipe, merk, dan tahun.
3. Pilih **Pemilik Rental** dari pilihan *dropdown*.
4. Masukkan struktur **Harga Jual** (harian, mingguan, bulanan) dan **Harga Modal** (untuk perhitungan profit).
5. Unggah beberapa foto kendaraan jika diperlukan, lalu klik **Simpan**.

### 3.4 Data Supir (Driver)
1. Buka menu **Driver**, klik **Tambah Driver**.
2. Masukkan informasi personal (Nama, SIM, Kontak).
3. Tentukan apakah dia **Driver Tetap** (memiliki akun login di sistem) atau Lepas/Freelance (tanpa akun).
4. Klik **Simpan**. *Finance mengelola saldo supir melalui modul yang berbeda.*

### 3.5 Data Pendukung Lainnya
*   **Akun Pembayaran (Rekening):** Daftar bank atau akun kas (tunai) yang digunakan untuk menerima DP, cicilan, dan mencatat pengeluaran.
*   **Paket Harga All-In:** Mengatur daftar harga tetap (contoh: "All-in Innova Jakarta Rp1.500.000" sudah termasuk supir dan bbm).
*   **Tipe Biaya Operasional:** Mengelola jenis-jenis biaya pada transaksi (BBM, Tol, Uang Makan, dll).
*   **User & Akses:** Admin cabang dapat menambah pengguna (staf) baru dan menentukan perannya.

---

## 4. Modul Booking & Transaksi Sewa

Modul ini adalah urat nadi operasional DRENT. Modul ini utamanya digunakan oleh tim **Customer Service (CS)**.

### 4.1 Melihat Jadwal (Kalender Timeline)
Buka menu **Kalender Booking**. Di sini, Anda dapat melihat visual berupa baris per unit kendaraan dan rentang 30 hari kalender. Batang berwarna menandakan kendaraan tersebut sudah dibooking/disewa pada tanggal tersebut.
*   **Tips:** Klik pada kotak kosong (tanggal dan mobil yang kosong) untuk langsung membuka form pembuatan booking baru yang sudah terisi otomatis mobil dan tanggalnya.

### 4.2 Alur Transaksi Sewa (Siklus Booking)
Sebuah reservasi akan melewati beberapa tahapan status berurutan:
`Follow Up` ➔ `Confirm` ➔ `Waiting List` ➔ `Rental Unit` ➔ `Selesai`

*(Status **Batal** dapat dilakukan kapan saja sebelum selesai).*

#### Langkah 1: Pembuatan Booking Awal (Status: Follow Up / Confirm)
1. Buka menu **Booking**, klik **Tambah Booking Baru**.
2. Cari dan pilih nama Pelanggan.
3. Masukkan Tanggal & Jam keberangkatan serta kepulangan.
4. Masukkan **Lama Sewa** dan **Paket Sewa** (Harian/Mingguan/Bulanan).
5. Masukkan **Harga Dealing** (harga negosiasi akhir dengan konsumen).
6. (Opsional) Pilih tujuan dan lokasi penjemputan.
7. Jika konsumen membayar **DP (Uang Muka)**, masukkan nominalnya dan pilih rekening penerima.
8. Klik **Simpan**.
    *   Jika ada DP, status otomatis menjadi **Confirm**.
    *   Jika tanpa DP, status otomatis menjadi **Follow Up**. Anda dapat mengubahnya secara manual jika diperlukan.

#### Langkah 2: Proses "Handle Booking" (Status -> Waiting List)
Setelah status menjadi *Confirm*, CS wajib menentukan detail operasional sesungguhnya.
1. Pada daftar booking, klik detail transaksi yang berstatus Confirm.
2. Di halaman detail, klik tombol **Handle Booking**.
3. **Pilih Unit:** Tentukan plat nomor kendaraan aktual yang akan dipakai.
4. **Pilih Driver:** (Opsional, tinggalkan kosong jika lepas kunci).
5. **Tentukan Mode Harga:**
    *   **All-In:** Pilih paket All-In atau ketik manual. Total tagihan ke konsumen adalah nominal All-In ini.
    *   **Non All-In:** Tagihan ke konsumen akan diakumulasi dari harga sewa mobil + total biaya operasional.
6. **Biaya Operasional:** Tambahkan rincian estimasi biaya (seperti Uang BBM, Uang Makan Driver, Tol, dsb).
7. Klik **Proses/Simpan**. Status transaksi akan berubah menjadi **Waiting List**.

#### Langkah 3: Keberangkatan (Checkout -> Rental Unit)
1. Pada hari H, buka detail transaksi (status Waiting List).
2. Klik tombol **Checkout**.
3. Akan muncul *popup* untuk memastikan apakah tim Cek Fisik sudah melakukan inspeksi keberangkatan.
4. Konfirmasi *Checkout*. Status akan berubah menjadi **Rental Unit** dan unit kendaraan berstatus "Out" (keluar). Pada tahap ini data booking inti akan **terkunci**.

#### Langkah 4: Penyelesaian (Rental Unit -> Selesai)
1. Setelah mobil kembali dengan selamat, buka detail transaksi.
2. Klik tombol **Selesai**.
3. Akan muncul *popup* untuk memastikan inspeksi kepulangan telah dilakukan.
4. Status berubah menjadi **Selesai**. Kendaraan kembali berstatus "Aktif".

### 4.3 Mengubah Transaksi (Saat Status "Rental Unit")
Karena data terkunci, jika terjadi perubahan di lapangan, Anda dapat menggunakan tombol aksi berikut di halaman detail:
*   **Extend (Perpanjang):** Untuk menambah hari penyewaan. Sistem akan meminta Anda memasukkan tanggal, paket tambahan, dan tambahan biaya.
*   **Rolling (Ganti Unit):** Digunakan jika kendaraan bermasalah di tengah jalan. Sistem akan memotong (adjust) pemakaian kendaraan pertama, dan meminta Anda menginput data "Handle Booking" baru untuk kendaraan pengganti.
*   **Tambah Biaya:** Memasukkan biaya-biaya operasional tambahan yang tak terduga (misal biaya cuci atau parkir tambahan).
*   **Berhenti Mendadak (Batal):** Membatalkan dan menghitung potensi pengembalian dana (refund) untuk hari yang tidak terpakai.

### 4.4 Manajemen Pembayaran
Pada halaman Detail Transaksi, CS atau Finance dapat mencatat histori penerimaan pembayaran konsumen.
1. Cari bagian "Pembayaran".
2. Klik **Tambah Pembayaran**.
3. Masukkan Nominal, pilih Tipe Pembayaran (DP / Cicilan / Pelunasan), pilih Rekening penerima, dan catatan (opsional).
4. Sisa tagihan pelanggan akan langsung disesuaikan secara otomatis.

---

## 5. Modul Keuangan

Modul ini diakses oleh peran **Finance** untuk mengelola arus kas dan tagihan.

### 5.1 Tagihan Piutang & Invoice Konsumen
*   **Melihat Piutang:** Akses menu **Piutang**. Anda akan melihat daftar semua transaksi yang telah berstatus Selesai namun masih memiliki sisa tagihan yang belum dilunasi.
*   **Membuat Invoice PDF:**
    1. Dari menu Invoice atau Piutang, pilih satu (atau centang beberapa) transaksi milik pelanggan yang sama.
    2. Klik **Generate Invoice**.
    3. Invoice resmi dengan nomor unik akan terbuat.
    4. Di halaman daftar Invoice, klik icon detail untuk **Download PDF** atau memasukkan catatan pembayaran parsial (bayar sebagian).

### 5.2 Hutang Rent-to-Rent (R2R)
Jika transaksi menggunakan unit kendaraan dari vendor/pemilik lain, sistem **secara otomatis** mencatat akumulasi hutangnya.
1. Buka menu **Hutang Rent-to-Rent**.
2. Anda akan melihat daftar hutang yang dikelompokkan berdasarkan nama Mitra/Pemilik.
3. Klik tombol aksi untuk membuat **Tagihan Konfirmasi** berformat PDF yang bisa Anda berikan ke pemilik sebagai bukti pemakaian.

### 5.3 Operasional Driver (Bon / Uang Jalan)
Prosedur pencatatan dana jalan supir:
*   **Memberikan Saldo Awal:** Finance mencari nama supir di daftar, lalu klik tombol "Tambah Saldo". Dana operasional berpindah ke supir.
*   **Pelaporan Bon (Driver Tetap):** Supir login melalui HP masing-masing dan mengunggah foto struk (BBM, Tol, dll).
*   **Pelaporan Bon (Driver Lepas):** Supir menyerahkan struk fisik, lalu pihak Finance yang menginputnya secara manual ke sistem di menu **Bon Driver**.
*   **Validasi Bon:** Finance mereview bon yang masuk. Jika klik "Validasi", nominal pada bon akan memotong sisa saldo supir tersebut.
*   **Pengembalian:** Sisa saldo yang belum divalidasi akan terus menempel di supir dan harus dikembalikan (disetor kembali ke Finance).

### 5.4 Buku Kas
Berfungsi sebagai pembukuan sederhana untuk mencatat arus masuk (In) dan keluar (Out).
*   **Tambah Transaksi Kas:** Buka menu **Kas**, klik tambah, pilih Tipe, Nominal, Rekening yang terpengaruh, dan Keterangan.
*   **Transfer Antar Rekening:** Klik tombol "Pindah Kas" untuk memindahkan uang dari satu rekening perusahaan (misal: Kas Tunai) ke rekening lainnya (misal: BCA Perusahaan).

---

## 6. Modul Cek Fisik

Modul ini dirancang khusus untuk layar Handphone (Mobile), digunakan oleh tim petugas lapangan di garasi.

### 6.1 Melakukan Inspeksi (Pre-departure & Post-return)
Inspeksi dilakukan dua kali: sebelum kunci diserahkan ke konsumen, dan saat mobil kembali.

1. Petugas login via browser HP, masuk ke menu **Cek Fisik**.
2. Pilih transaksi yang sedang berada dalam status *Waiting List* (untuk keberangkatan) atau *Rental Unit* (untuk kepulangan).
3. **Pemeriksaan Eksterior & Interior:** Akan muncul diagram visual mobil. Petugas wajib memfoto sisi Depan, Kiri, Kanan, Belakang, dan Atap langsung menggunakan kamera HP.
4. **Ceklis Perlengkapan:** Centang perlengkapan yang ada di dalam mobil (Ban Serep, Dongkrak, STNK, dll).
5. **Indikator Fisik:** Ketikkan angka Odometer (KM terakhir) dan atur tampilan visual indikator bensin (Kosong sampai Penuh).
6. **Tanda Tangan Digital:** Petugas dan penyewa/supir melakukan coretan tanda tangan langsung menggunakan jari di atas layar HP (pada kotak kanvas yang disediakan).
7. Klik **Submit**. Hasil inspeksi akan di-generate menjadi format PDF dan tersimpan di dalam detail transaksi.

---

## 7. Modul Pendukung

### 7.1 Pemeliharaan Unit (Service)
Menu ini (biasanya diakses Teknisi/Admin) digunakan untuk mencatat pengeluaran bengkel dan memantau jadwal servis mobil.
1. Buka menu **Pemeliharaan Unit**.
2. Klik **Tambah Catatan**.
3. Pilih mobil, pilih jenis servis (misal: Ganti Oli), masukkan biaya, catatan, Odometer saat servis, dan target Odometer servis berikutnya.
4. Unggah foto nota/struk bengkel, lalu simpan.
    *Catatan: Mobil yang berstatus sedang dalam pemeliharaan aktif tidak akan bisa dipilih saat CS membuat booking.*

### 7.2 Laporan (Reports)
Modul ini digunakan oleh manajemen untuk melakukan analisa.
1. Buka menu **Laporan**.
2. Pilih jenis laporan yang ingin dilihat (Laporan Transaksi, Piutang, Kas, Pemeliharaan, Utilisasi, Revenue, dll).
3. Atur filter pencarian (Cabang, Tanggal Dari-Sampai, Konsumen).
4. Hasil laporan akan tampil dalam bentuk tabel. Anda dapat mengunduhnya dengan menekan tombol **Export CSV** atau **Export PDF**.

### 7.3 Notifikasi (Lonceng)
Sistem memiliki fitur pemberitahuan (in-app notification). Anda dapat melihatnya melalui ikon **Lonceng** di kanan atas *header* navigasi.
*   CS akan mendapat notif jika ada booking baru atau bon yang telah divalidasi finance.
*   Finance akan mendapat notif jika ada tagihan baru atau permohonan saldo driver.
*   Admin akan mendapat notif otomatis dari sistem jika ada mobil yang mendekati jatuh tempo servis (H-7).

---
**Hak Cipta © DRENT System.** Panduan pengguna ini dibuat khusus untuk keperluan internal. Tidak untuk didistribusikan kepada pihak luar perusahaan.
