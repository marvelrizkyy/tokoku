# Sistem Informasi Penjualan & POS (`toko_ku`)
**Advanced Database Programming & SQL Lanjutan Implementation**

Aplikasi web POS & Kasir modern dibangun menggunakan **PHP Native (PDO)**, **Tailwind CSS (CDN)**, dan **JavaScript Vanilla**. Dirancang khusus untuk mengimplementasikan dan membuktikan fitur-fitur SQL Lanjutan: **Database Views**, **Advanced JOIN & Subqueries**, **Stored Procedures**, **Stored Functions**, **Database Triggers**, dan **TCL Transactions (ACID)**.

---

## 🛠️ Persyaratan Sistem
1. **XAMPP / Laragon / Web Server Lokal** dengan Apache dan MySQL/MariaDB.
2. **PHP 8.0+** dengan ekstensi `pdo_mysql` aktif.
3. Database `toko_ku` sudah di-import dari file `toko_ku.sql`.

---

## 🗄️ Langkah Setup Database
1. Buka **phpMyAdmin** (`http://localhost/phpmyadmin`) atau terminal MySQL Anda.
2. Buat database atau langsung import file `toko_ku.sql` yang ada di root folder ini:
   ```bash
   mysql -u root -p < toko_ku.sql
   ```
3. Konfigurasi koneksi database berada di file [`config/db.php`](file:///c:/xampp_baru/htdocs/tokoku/config/db.php):
   - **Host**: `localhost`
   - **Database**: `toko_ku`
   - **User**: `root`
   - **Password**: `""` (kosong default XAMPP)

---

## 📂 Struktur File & Implementasi Fitur

| Halaman / File | Fitur SQL yang Diimplementasikan | Deskripsi |
| :--- | :--- | :--- |
| [`config/db.php`](file:///c:/xampp_baru/htdocs/tokoku/config/db.php) | **PDO Connection & Error Mode** | Mengaktifkan `PDO::ERRMODE_EXCEPTION`, helper `formatRupiah()`, `formatTanggal()`, dan sanitasi. |
| [`includes/header.php`](file:///c:/xampp_baru/htdocs/tokoku/includes/header.php) | **Tailwind CSS & FontAwesome** | Template header responsif dengan CDN Tailwind CSS & FontAwesome 6. |
| [`includes/sidebar.php`](file:///c:/xampp_baru/htdocs/tokoku/includes/sidebar.php) | **Modern Responsive Sidebar** | Navigasi menu utama (Dashboard, Kasir, Laporan, Pelanggan, Audit Log) & status MySQL. |
| [`includes/footer.php`](file:///c:/xampp_baru/htdocs/tokoku/includes/footer.php) | **Vanilla JS Handlers** | Penutup layout, toggle sidebar responsif, dan alert dismisser. |
| [`index.php`](file:///c:/xampp_baru/htdocs/tokoku/index.php) | **SP `HitungPelanggan` & Subquery `NOT IN`** | - 4 Kartu Ringkasan (Pendapatan, Transaksi, Total Pelanggan via `CALL HitungPelanggan(@jumlah)`, Stok Menipis `< 20`).<br>- Tabel **5 Transaksi Terbesar** (`LIMIT 5`).<br>- Tabel **Pelanggan Belum Pernah Bertransaksi** (`Subquery NOT IN`). |
| [`kasir.php`](file:///c:/xampp_baru/htdocs/tokoku/kasir.php) | **TCL, SP `TambahTransaksi`, SF, & Trigger `SQLSTATE 45000`** | - Form Kasir & Kalkulator Real-time (Frontend JS).<br>- Menjalankan transaksi ACID (`beginTransaction()`, `commit()`, `rollBack()`).<br>- Memanggil `CALL TambahTransaksi(p_tanggal, p_id_pelanggan, p_id_produk, p_jumlah)` serta Stored Function `HitungDiskon()` dan `HitungPajak()`.<br>- Menangkap exception Trigger `before_insert_transaksi` (`SQLSTATE 45000`) saat nilai negatif diinput. |
| [`laporan.php`](file:///c:/xampp_baru/htdocs/tokoku/laporan.php) | **View & Multiple INNER JOIN (3 Tabel)** | - Tabel 1: `SELECT * FROM view_transaksi_pelanggan`.<br>- Tabel 2: Rincian item via `INNER JOIN` 3 tabel (`tbl_pelanggan`, `tbl_transaksi`, `tbl_detail_transaksi`).<br>- Filter pencarian, filter tanggal, dan statistik omset. |
| [`pelanggan.php`](file:///c:/xampp_baru/htdocs/tokoku/pelanggan.php) | **SP `TambahPelanggan`, SF `KategoriBelanja`, FK Handling** | - Tambah Pelanggan via `CALL TambahPelanggan(nama, kota)`.<br>- Tabel pelanggan dari `view_total_belanja` dengan klasifikasi status loyalitas via `KategoriBelanja(total_belanja)` (Gold, Silver, Reguler).<br>- Edit pelanggan (memicu trigger `after_update_pelanggan`).<br>- Hapus pelanggan (memicu trigger `after_delete_pelanggan` atau ditolak ramah jika foreign key aktif). |
| [`audit_log.php`](file:///c:/xampp_baru/htdocs/tokoku/audit_log.php) | **Database Triggers Real-Time Audit** | Menampilkan log real-time pembuktian 3 triggers:<br>1. `after_insert_pelanggan`<br>2. `after_update_pelanggan`<br>3. `after_delete_pelanggan`<br>pada tabel `tbl_log`, `log_perubahan_pelanggan`, dan `log_pelanggan`. |

---

## 🧪 Panduan Pengujian Fitur Khusus

### 1. Pengujian Exception Trigger `SQLSTATE 45000`
1. Buka menu **Kasir (POS)** ([`kasir.php`](file:///c:/xampp_baru/htdocs/tokoku/kasir.php)).
2. Klik tombol merah **"Set Jumlah = -1 (Uji Error)"** atau masukkan angka negatif pada input jumlah.
3. Klik **"Proses Transaksi"**.
4. Sistem akan memicu trigger database `before_insert_transaksi`, mengeksekusi `rollBack()`, dan menampilkan kotak alert merah khusus berisi pesan error:
   > **[SQLSTATE 45000] Total belanja tidak boleh negatif**

### 2. Pengujian Audit Log Trigger
1. Buka menu **Pelanggan** ([`pelanggan.php`](file:///c:/xampp_baru/htdocs/tokoku/pelanggan.php)).
2. Tambahkan pelanggan baru (misal: "Dewi Sari", "Ponorogo").
3. Edit nama pelanggan tersebut menjadi "Dewi Sartika".
4. Hapus salah satu pelanggan yang belum pernah bertransaksi (misal: "Lukman Hakim").
5. Buka menu **Audit Log** ([`audit_log.php`](file:///c:/xampp_baru/htdocs/tokoku/audit_log.php)). Anda akan melihat rekaman mutasi data otomatis pada ketiga tab: `tbl_log`, `log_perubahan_pelanggan`, dan `log_pelanggan`.

### 3. Pengujian Foreign Key Handling
1. Di menu **Pelanggan**, coba hapus pelanggan yang sudah memiliki riwayat transaksi (misal: "Andi Pratama").
2. Sistem akan menangkap kode error PDO `23000` dan menampilkan notifikasi ramah bahwa data tidak dapat dihapus demi menjaga integritas relasional transaksi.

