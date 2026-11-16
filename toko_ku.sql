-- ============================================================================
-- MODUL PEMBELAJARAN: SQL LANJUTAN (ADVANCED DATABASE PROGRAMMING)
-- Database: toko_ku
-- Dibuat sesuai Jobsheet Praktik 1 s.d. 5 & Proyek Terintegrasi (Bab 6)
-- ============================================================================

DROP DATABASE IF EXISTS toko_ku;
CREATE DATABASE toko_ku;
USE toko_ku;

-- ============================================================================
-- BAGIAN 0: STRUKTUR TABEL UTAMA (MENDUKUNG SEMUA BAB & PROYEK TERINTEGRASI)
-- ============================================================================

-- 1. Tabel Kategori Produk
CREATE TABLE tbl_kategori (
    id_kategori INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(50) NOT NULL
);

-- 2. Tabel Produk
CREATE TABLE tbl_produk (
    id_produk INT AUTO_INCREMENT PRIMARY KEY,
    id_kategori INT,
    nama_produk VARCHAR(100) NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    stok INT NOT NULL DEFAULT 0,
    FOREIGN KEY (id_kategori) REFERENCES tbl_kategori(id_kategori)
);

-- 3. Tabel Pelanggan
CREATE TABLE tbl_pelanggan (
    id_pelanggan INT AUTO_INCREMENT PRIMARY KEY,
    nama_pelanggan VARCHAR(100) NOT NULL,
    kota VARCHAR(50) NOT NULL
);

-- 4. Tabel Cabang (Untuk kebutuhan praktik UNION di Bab 2)
CREATE TABLE tbl_cabang (
    id_cabang INT AUTO_INCREMENT PRIMARY KEY,
    nama_cabang VARCHAR(100) NOT NULL,
    kota VARCHAR(50) NOT NULL
);

-- 5. Tabel Transaksi
CREATE TABLE tbl_transaksi (
    id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    id_pelanggan INT,
    total_belanja DECIMAL(12,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_pelanggan) REFERENCES tbl_pelanggan(id_pelanggan)
);

-- 6. Tabel Detail Transaksi
CREATE TABLE tbl_detail_transaksi (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT,
    id_produk INT,
    nama_produk VARCHAR(100),
    jumlah INT NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (id_transaksi) REFERENCES tbl_transaksi(id_transaksi),
    FOREIGN KEY (id_produk) REFERENCES tbl_produk(id_produk)
);

-- 7. Tabel Log (Untuk Trigger & Audit Perubahan Data)
CREATE TABLE tbl_log (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    tabel_terkait VARCHAR(50),
    aktivitas VARCHAR(50),
    keterangan TEXT,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE log_pelanggan (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    aktivitas VARCHAR(50),
    id_pelanggan INT,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE log_perubahan_pelanggan (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    id_pelanggan INT,
    nama_lama VARCHAR(100),
    nama_baru VARCHAR(100),
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- PENGISIAN DATA AWAL (DML)
-- Minimal 10 Pelanggan & Minimal 20 Transaksi (Sesuai Jobsheet Praktik 1)
-- ============================================================================

INSERT INTO tbl_kategori (nama_kategori) VALUES
('Elektronik'),
('Sembako'),
('Alat Tulis Kantor'),
('Minuman & Makanan');

INSERT INTO tbl_produk (id_kategori, nama_produk, harga, stok) VALUES
(1, 'Mouse Wireless', 150000, 25),
(1, 'Keyboard Mechanical', 350000, 15),
(1, 'Flashdisk 64GB', 100000, 40),
(2, 'Beras Premium 5kg', 75000, 50),
(2, 'Minyak Goreng 2L', 35000, 60),
(3, 'Buku Tulis 1 Pak', 45000, 30),
(3, 'Pulpen Gel 1 Box', 25000, 45),
(4, 'Kopi Bubuk 250gr', 30000, 35);

INSERT INTO tbl_cabang (nama_cabang, kota) VALUES
('Cabang Pusat', 'Ponorogo'),
('Cabang Timur', 'Madiun'),
('Cabang Selatan', 'Pacitan'),
('Cabang Metropolitan', 'Surabaya');

-- Minimal 10 Data Pelanggan (Ditambah 2 pelanggan tanpa transaksi untuk uji Bab 2)
INSERT INTO tbl_pelanggan (nama_pelanggan, kota) VALUES
('Andi Pratama', 'Ponorogo'),
('Budi Santoso', 'Madiun'),
('Citra Lestari', 'Surabaya'),
('Dewi Anggraini', 'Ponorogo'),
('Eko Saputra', 'Magetan'),
('Fajar Nugroho', 'Ngawi'),
('Gita Permata', 'Ponorogo'),
('Hendra Wijaya', 'Madiun'),
('Indah Sari', 'Surabaya'),
('Joko Susilo', 'Trenggalek'),
('Kartika Putri', 'Ponorogo'),   -- Belum pernah bertransaksi
('Lukman Hakim', 'Solo');         -- Belum pernah bertransaksi

-- Minimal 20 Data Transaksi
INSERT INTO tbl_transaksi (tanggal, id_pelanggan, total_belanja) VALUES
('2026-10-01', 1, 250000),
('2026-10-02', 2, 350000),
('2026-10-03', 1, 150000),
('2026-10-04', 3, 500000),
('2026-10-05', 4, 120000),
('2026-10-06', 5, 300000),
('2026-10-07', 6, 90000),
('2026-10-08', 7, 450000),
('2026-10-09', 8, 200000),
('2026-10-10', 9, 750000),
('2026-10-11', 10, 180000),
('2026-10-12', 1, 600000),
('2026-10-13', 2, 110000),
('2026-10-14', 3, 850000),
('2026-10-15', 4, 225000),
('2026-10-16', 5, 140000),
('2026-10-17', 7, 320000),
('2026-10-18', 8, 95000),
('2026-10-19', 9, 410000),
('2026-10-20', 1, 1050000),
('2026-10-21', 3, 275000),
('2026-10-22', 6, 165000);

-- Data Detail Transaksi (Sampel rincian untuk kebutuhan Multiple JOIN)
INSERT INTO tbl_detail_transaksi (id_transaksi, id_produk, nama_produk, jumlah, harga) VALUES
(1, 1, 'Mouse Wireless', 1, 150000),
(1, 3, 'Flashdisk 64GB', 1, 100000),
(2, 2, 'Keyboard Mechanical', 1, 350000),
(3, 1, 'Mouse Wireless', 1, 150000),
(4, 2, 'Keyboard Mechanical', 1, 350000),
(4, 1, 'Mouse Wireless', 1, 150000),
(5, 6, 'Buku Tulis 1 Pak', 2, 45000),
(5, 8, 'Kopi Bubuk 250gr', 1, 30000),
(6, 4, 'Beras Premium 5kg', 4, 75000),
(10, 2, 'Keyboard Mechanical', 2, 350000),
(10, 7, 'Pulpen Gel 1 Box', 2, 25000),
(12, 1, 'Mouse Wireless', 4, 150000),
(14, 2, 'Keyboard Mechanical', 2, 350000),
(14, 1, 'Mouse Wireless', 1, 150000),
(20, 2, 'Keyboard Mechanical', 3, 350000);


-- ============================================================================
-- BAB 1: JOBSHEET PRAKTIK 1 - DATABASE VIEW
-- ============================================================================

-- 1. View Pelanggan Dasar
CREATE OR REPLACE VIEW view_pelanggan AS
SELECT id_pelanggan, nama_pelanggan, kota
FROM tbl_pelanggan;

-- 2. View Laporan Transaksi (JOIN)
CREATE OR REPLACE VIEW view_transaksi_pelanggan AS
SELECT 
    t.id_transaksi,
    t.tanggal,
    p.nama_pelanggan,
    p.kota,
    t.total_belanja
FROM tbl_transaksi t
JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan;

-- 3. View Total Belanja Pelanggan (Agregasi)
CREATE OR REPLACE VIEW view_total_belanja AS
SELECT 
    p.id_pelanggan,
    p.nama_pelanggan,
    p.kota,
    COUNT(t.id_transaksi) AS total_transaksi,
    COALESCE(SUM(t.total_belanja), 0) AS total_belanja
FROM tbl_pelanggan p
LEFT JOIN tbl_transaksi t ON p.id_pelanggan = t.id_pelanggan
GROUP BY p.id_pelanggan, p.nama_pelanggan, p.kota;

-- Pengujian View Bab 1:
SELECT * FROM view_transaksi_pelanggan;
SELECT * FROM view_total_belanja;


-- ============================================================================
-- BAB 2: JOBSHEET PRAKTIK 2 - ADVANCED JOIN & SUBQUERY
-- ============================================================================

-- 1. Menampilkan semua pelanggan dan transaksi mereka (LEFT JOIN)
SELECT 
    p.id_pelanggan,
    p.nama_pelanggan,
    p.kota,
    t.id_transaksi,
    t.tanggal,
    COALESCE(t.total_belanja, 0) AS total_belanja
FROM tbl_pelanggan p
LEFT JOIN tbl_transaksi t ON p.id_pelanggan = t.id_pelanggan
ORDER BY p.id_pelanggan, t.tanggal;

-- 1b. Multiple JOIN (3 Tabel: Pelanggan, Transaksi, Detail Transaksi)
SELECT 
    p.nama_pelanggan,
    t.id_transaksi,
    t.tanggal,
    d.nama_produk,
    d.jumlah,
    d.harga,
    (d.jumlah * d.harga) AS subtotal
FROM tbl_pelanggan p
INNER JOIN tbl_transaksi t ON p.id_pelanggan = t.id_pelanggan
INNER JOIN tbl_detail_transaksi d ON t.id_transaksi = d.id_transaksi;

-- 2. Pelanggan yang belum pernah bertransaksi (Subquery NOT IN / LEFT JOIN IS NULL)
SELECT * 
FROM tbl_pelanggan
WHERE id_pelanggan NOT IN (
    SELECT DISTINCT id_pelanggan 
    FROM tbl_transaksi 
    WHERE id_pelanggan IS NOT NULL
);

-- 3. Transaksi dengan nilai di atas rata-rata (Subquery AVG)
SELECT * 
FROM tbl_transaksi
WHERE total_belanja > (
    SELECT AVG(total_belanja) 
    FROM tbl_transaksi
);

-- 4. Pelanggan dengan transaksi terbanyak
SELECT 
    p.id_pelanggan,
    p.nama_pelanggan,
    COUNT(t.id_transaksi) AS jumlah_transaksi
FROM tbl_pelanggan p
JOIN tbl_transaksi t ON p.id_pelanggan = t.id_pelanggan
GROUP BY p.id_pelanggan, p.nama_pelanggan
HAVING COUNT(t.id_transaksi) = (
    SELECT MAX(jml)
    FROM (
        SELECT COUNT(id_transaksi) AS jml
        FROM tbl_transaksi
        GROUP BY id_pelanggan
    ) AS sub_hitung
);

-- 5. Total belanja setiap pelanggan
SELECT 
    p.id_pelanggan,
    p.nama_pelanggan,
    COALESCE(SUM(t.total_belanja), 0) AS total_belanja
FROM tbl_pelanggan p
LEFT JOIN tbl_transaksi t ON p.id_pelanggan = t.id_pelanggan
GROUP BY p.id_pelanggan, p.nama_pelanggan
ORDER BY total_belanja DESC;

-- 6. Lima transaksi dengan nilai terbesar
SELECT 
    t.id_transaksi,
    t.tanggal,
    p.nama_pelanggan,
    t.total_belanja
FROM tbl_transaksi t
JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
ORDER BY t.total_belanja DESC
LIMIT 5;

-- 7. Data gabungan kota pelanggan dan kota cabang menggunakan UNION & UNION ALL
SELECT kota, 'Pelanggan' AS sumber FROM tbl_pelanggan
UNION
SELECT kota, 'Cabang' AS sumber FROM tbl_cabang;

SELECT kota FROM tbl_pelanggan
UNION ALL
SELECT kota FROM tbl_cabang;


-- ============================================================================
-- BAB 3: JOBSHEET PRAKTIK 3 - STORED PROCEDURE
-- ============================================================================

DELIMITER //

-- 1. TampilPelanggan()
CREATE PROCEDURE TampilPelanggan()
BEGIN
    SELECT * FROM tbl_pelanggan;
END //

-- 2. CariPelanggan(id)
CREATE PROCEDURE CariPelanggan(IN p_id INT)
BEGIN
    SELECT * FROM tbl_pelanggan WHERE id_pelanggan = p_id;
END //

-- 3. TambahPelanggan(nama, kota)
CREATE PROCEDURE TambahPelanggan(
    IN p_nama VARCHAR(100),
    IN p_kota VARCHAR(50)
)
BEGIN
    INSERT INTO tbl_pelanggan (nama_pelanggan, kota)
    VALUES (p_nama, p_kota);
END //

-- 4. HitungPelanggan(OUT jumlah)
CREATE PROCEDURE HitungPelanggan(OUT jumlah INT)
BEGIN
    SELECT COUNT(*) INTO jumlah FROM tbl_pelanggan;
END //

-- 5. LaporanTransaksi(id_pelanggan)
CREATE PROCEDURE LaporanTransaksi(IN p_id_pelanggan INT)
BEGIN
    SELECT 
        p.id_pelanggan,
        p.nama_pelanggan,
        p.kota,
        t.id_transaksi,
        t.tanggal,
        t.total_belanja
    FROM tbl_pelanggan p
    LEFT JOIN tbl_transaksi t ON p.id_pelanggan = t.id_pelanggan
    WHERE p.id_pelanggan = p_id_pelanggan;
END //

-- Tambahan untuk Proyek Bab 6: Stored Procedure Proses Tambah Transaksi
CREATE PROCEDURE TambahTransaksi(
    IN p_tanggal DATE,
    IN p_id_pelanggan INT,
    IN p_id_produk INT,
    IN p_jumlah INT
)
BEGIN
    DECLARE v_harga DECIMAL(12,2);
    DECLARE v_nama_produk VARCHAR(100);
    DECLARE v_total DECIMAL(12,2);
    DECLARE v_id_transaksi INT;

    SELECT harga, nama_produk INTO v_harga, v_nama_produk
    FROM tbl_produk WHERE id_produk = p_id_produk;

    SET v_total = v_harga * p_jumlah;

    INSERT INTO tbl_transaksi (tanggal, id_pelanggan, total_belanja)
    VALUES (p_tanggal, p_id_pelanggan, v_total);

    SET v_id_transaksi = LAST_INSERT_ID();

    INSERT INTO tbl_detail_transaksi (id_transaksi, id_produk, nama_produk, jumlah, harga)
    VALUES (v_id_transaksi, p_id_produk, v_nama_produk, p_jumlah, v_harga);

    UPDATE tbl_produk SET stok = stok - p_jumlah WHERE id_produk = p_id_produk;
END //

DELIMITER ;

-- Pengujian Stored Procedure Bab 3:
CALL TampilPelanggan();
CALL CariPelanggan(1);
CALL TambahPelanggan('Nadia Putri', 'Ponorogo');
CALL HitungPelanggan(@total_pelanggan);
SELECT @total_pelanggan AS total_pelanggan;
CALL LaporanTransaksi(1);


-- ============================================================================
-- BAB 4: JOBSHEET PRAKTIK 4 - STORED FUNCTION
-- ============================================================================

DELIMITER //

-- 1. HitungDiskon(harga, diskon_persen)
CREATE FUNCTION HitungDiskon(
    harga DECIMAL(12,2),
    diskon DECIMAL(5,2)
)
RETURNS DECIMAL(12,2)
DETERMINISTIC
NO SQL
BEGIN
    RETURN harga * (diskon / 100);
END //

-- 2. HitungTotal(harga, jumlah)
CREATE FUNCTION HitungTotal(
    harga DECIMAL(12,2),
    jumlah INT
)
RETURNS DECIMAL(12,2)
DETERMINISTIC
NO SQL
BEGIN
    RETURN harga * jumlah;
END //

-- 3. HitungPajak(harga) -> PPN 11%
CREATE FUNCTION HitungPajak(
    harga DECIMAL(12,2)
)
RETURNS DECIMAL(12,2)
DETERMINISTIC
NO SQL
BEGIN
    RETURN harga * 0.11;
END //

-- 4. KategoriBelanja(total)
CREATE FUNCTION KategoriBelanja(
    total DECIMAL(12,2)
)
RETURNS VARCHAR(30)
DETERMINISTIC
NO SQL
BEGIN
    RETURN CASE
        WHEN total >= 1000000 THEN 'Pelanggan Gold'
        WHEN total >= 500000 THEN 'Pelanggan Silver'
        ELSE 'Pelanggan Reguler'
    END;
END //

-- 5. KonversiNilai(nilai) -> Konversi Nilai Angka ke Huruf
CREATE FUNCTION KonversiNilai(
    nilai INT
)
RETURNS VARCHAR(5)
DETERMINISTIC
NO SQL
BEGIN
    RETURN CASE
        WHEN nilai >= 85 THEN 'A'
        WHEN nilai >= 75 THEN 'B'
        WHEN nilai >= 60 THEN 'C'
        WHEN nilai >= 50 THEN 'D'
        ELSE 'E'
    END;
END //

DELIMITER ;

-- Pengujian Stored Function Bab 4:
SELECT 
    HitungDiskon(200000, 10) AS nominal_diskon,
    HitungTotal(50000, 5) AS total_harga,
    HitungPajak(1000000) AS pajak_11_persen,
    KonversiNilai(88) AS grade_nilai;

SELECT 
    nama_pelanggan,
    total_belanja,
    KategoriBelanja(total_belanja) AS kategori
FROM view_total_belanja;


-- ============================================================================
-- BAB 5: JOBSHEET PRAKTIK 5 - DATABASE TRIGGER
-- ============================================================================

DELIMITER //

-- 1. Trigger AFTER INSERT untuk mencatat pelanggan baru
CREATE TRIGGER after_insert_pelanggan
AFTER INSERT ON tbl_pelanggan
FOR EACH ROW
BEGIN
    INSERT INTO log_pelanggan (aktivitas, id_pelanggan)
    VALUES ('INSERT PELANGGAN', NEW.id_pelanggan);

    INSERT INTO tbl_log (tabel_terkait, aktivitas, keterangan)
    VALUES ('tbl_pelanggan', 'INSERT', CONCAT('Menambahkan pelanggan baru: ', NEW.nama_pelanggan));
END //

-- 2. Trigger AFTER UPDATE untuk mencatat perubahan pelanggan
CREATE TRIGGER after_update_pelanggan
AFTER UPDATE ON tbl_pelanggan
FOR EACH ROW
BEGIN
    INSERT INTO log_perubahan_pelanggan (id_pelanggan, nama_lama, nama_baru)
    VALUES (OLD.id_pelanggan, OLD.nama_pelanggan, NEW.nama_pelanggan);

    INSERT INTO tbl_log (tabel_terkait, aktivitas, keterangan)
    VALUES ('tbl_pelanggan', 'UPDATE', CONCAT('Ubah nama ID ', OLD.id_pelanggan, ' dari ', OLD.nama_pelanggan, ' menjadi ', NEW.nama_pelanggan));
END //

-- 3. Trigger BEFORE INSERT untuk validasi transaksi (menolak nilai negatif)
CREATE TRIGGER before_insert_transaksi
BEFORE INSERT ON tbl_transaksi
FOR EACH ROW
BEGIN
    IF NEW.total_belanja < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Total belanja tidak boleh negatif';
    END IF;
END //

-- Tambahan Trigger AFTER DELETE untuk audit penghapusan pelanggan (sesuai instruksi pengujian)
CREATE TRIGGER after_delete_pelanggan
AFTER DELETE ON tbl_pelanggan
FOR EACH ROW
BEGIN
    INSERT INTO tbl_log (tabel_terkait, aktivitas, keterangan)
    VALUES ('tbl_pelanggan', 'DELETE', CONCAT('Menghapus pelanggan: ', OLD.nama_pelanggan, ' (ID: ', OLD.id_pelanggan, ')'));
END //

DELIMITER ;

-- Pengujian Trigger Bab 5:
INSERT INTO tbl_pelanggan (nama_pelanggan, kota) VALUES ('Rina Marlina', 'Ponorogo');
UPDATE tbl_pelanggan SET nama_pelanggan = 'Rina Wijaya' WHERE nama_pelanggan = 'Rina Marlina';
DELETE FROM tbl_pelanggan WHERE nama_pelanggan = 'Rina Wijaya';

SELECT * FROM log_pelanggan;
SELECT * FROM log_perubahan_pelanggan;
SELECT * FROM tbl_log;

-- Catatan Pengujian Error Validasi (Jalankan manual untuk melihat pesan error):
-- INSERT INTO tbl_transaksi (tanggal, id_pelanggan, total_belanja) VALUES ('2026-11-01', 1, -50000);


-- ============================================================================
-- BAB 6: PROYEK TERINTEGRASI (TCL & INDEX / EXPLAIN)
-- ============================================================================

-- 1. Pembuatan Index untuk Optimasi Query
CREATE INDEX idx_transaksi_pelanggan ON tbl_transaksi(id_pelanggan);
CREATE INDEX idx_transaksi_tanggal ON tbl_transaksi(tanggal);
CREATE INDEX idx_pelanggan_kota ON tbl_pelanggan(kota);

-- Analisis Query menggunakan EXPLAIN
EXPLAIN SELECT * FROM tbl_transaksi WHERE id_pelanggan = 1;
EXPLAIN SELECT * FROM tbl_pelanggan WHERE kota = 'Ponorogo';

-- 2. Pengujian TCL (Transaction Control Language: COMMIT & ROLLBACK)
-- Contoh A: Transaksi Berhasil (COMMIT)
START TRANSACTION;
INSERT INTO tbl_transaksi (tanggal, id_pelanggan, total_belanja) VALUES ('2026-11-15', 2, 400000);
COMMIT;

-- Contoh B: Transaksi Dibatalkan (ROLLBACK)
START TRANSACTION;
DELETE FROM tbl_transaksi WHERE id_transaksi = 1;
ROLLBACK; -- Data id_transaksi = 1 kembali aman dan tidak terhapus
