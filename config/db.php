<?php
/**
 * Database Connection & Self-Healing Auto-Initializer
 * Sistem Informasi Penjualan & POS (toko_ku)
 * Menggunakan PHP Data Objects (PDO) dengan MySQL
 */

// Pastikan session global aktif sebelum ada output HTML
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Konfigurasi Database
$db_host = 'localhost';
$db_name = 'toko_ku';
$db_user = 'root';
$db_pass = '';
$db_charset = 'utf8mb4';

$pdo = null;
$db_error = null;

try {
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    } catch (PDOException $e) {
        // Jika database belum ada, buat otomatis
        if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
            $rootDsn = "mysql:host={$db_host};charset={$db_charset}";
            $rootPdo = new PDO($rootDsn, $db_user, $db_pass, $options);
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        } else {
            throw $e;
        }
    }

    // Jalankan Self-Healing Schema otomatis agar tidak ada error table/view not found
    autoSelfHealDatabase($pdo);

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

/**
 * Self-Healing Auto-Initializer
 * Otomatis membuat tabel, view, procedure, function, trigger, dan sample data jika belum lengkap
 */
function autoSelfHealDatabase(PDO $pdo) {
    try {
        // 1. BUAT TABEL UTAMA JIKA BELUM ADA
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tbl_kategori (
                id_kategori INT AUTO_INCREMENT PRIMARY KEY,
                nama_kategori VARCHAR(50) NOT NULL
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS tbl_produk (
                id_produk INT AUTO_INCREMENT PRIMARY KEY,
                id_kategori INT,
                nama_produk VARCHAR(100) NOT NULL,
                barcode VARCHAR(50) NULL,
                harga DECIMAL(12,2) NOT NULL,
                stok INT NOT NULL DEFAULT 0,
                FOREIGN KEY (id_kategori) REFERENCES tbl_kategori(id_kategori) ON DELETE SET NULL
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS tbl_pelanggan (
                id_pelanggan INT AUTO_INCREMENT PRIMARY KEY,
                nama_pelanggan VARCHAR(100) NOT NULL,
                rfid_uid VARCHAR(50) NULL UNIQUE,
                kota VARCHAR(50) NOT NULL
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS tbl_cabang (
                id_cabang INT AUTO_INCREMENT PRIMARY KEY,
                nama_cabang VARCHAR(100) NOT NULL,
                kota VARCHAR(50) NOT NULL
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS tbl_transaksi (
                id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                id_pelanggan INT,
                total_belanja DECIMAL(12,2) NOT NULL DEFAULT 0,
                FOREIGN KEY (id_pelanggan) REFERENCES tbl_pelanggan(id_pelanggan)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS tbl_detail_transaksi (
                id_detail INT AUTO_INCREMENT PRIMARY KEY,
                id_transaksi INT,
                id_produk INT,
                nama_produk VARCHAR(100),
                jumlah INT NOT NULL,
                harga DECIMAL(12,2) NOT NULL,
                FOREIGN KEY (id_transaksi) REFERENCES tbl_transaksi(id_transaksi) ON DELETE CASCADE,
                FOREIGN KEY (id_produk) REFERENCES tbl_produk(id_produk) ON DELETE SET NULL
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS tbl_log (
                id_log INT AUTO_INCREMENT PRIMARY KEY,
                tabel_terkait VARCHAR(50),
                aktivitas VARCHAR(50),
                keterangan TEXT,
                waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS log_pelanggan (
                id_log INT AUTO_INCREMENT PRIMARY KEY,
                aktivitas VARCHAR(50),
                id_pelanggan INT,
                waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS log_perubahan_pelanggan (
                id_log INT AUTO_INCREMENT PRIMARY KEY,
                id_pelanggan INT,
                nama_lama VARCHAR(100),
                nama_baru VARCHAR(100),
                waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS tbl_payment_callback (
                id_callback INT AUTO_INCREMENT PRIMARY KEY,
                merchant_order_id VARCHAR(50) NULL,
                duitku_reference VARCHAR(100) NULL,
                amount DECIMAL(12,2) NULL,
                result_code VARCHAR(10) NULL,
                signature_valid TINYINT(1) NOT NULL DEFAULT 0,
                payload TEXT NULL,
                received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_callback_order (merchant_order_id)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS tbl_user (
                id_user INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                nama_lengkap VARCHAR(100) NOT NULL,
                role ENUM('Admin', 'Kasir') NOT NULL DEFAULT 'Kasir',
                status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
                terakhir_login DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;
        ");

        // 1b. TAMBAH KOLOM RINCIAN PEMBAYARAN PADA TBL_TRANSAKSI JIKA BELUM ADA
        try {
            $stmtCols = $pdo->query("SHOW COLUMNS FROM tbl_transaksi");
            $existingCols = $stmtCols ? $stmtCols->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!in_array('nominal_bayar', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN nominal_bayar DECIMAL(12,2) NOT NULL DEFAULT 0");
            }
            if (!in_array('kembalian', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN kembalian DECIMAL(12,2) NOT NULL DEFAULT 0");
            }
            if (!in_array('nominal_diskon', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN nominal_diskon DECIMAL(12,2) NOT NULL DEFAULT 0");
            }
            if (!in_array('diskon_persen', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN diskon_persen DECIMAL(5,2) NOT NULL DEFAULT 0");
            }
            if (!in_array('nominal_pajak', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN nominal_pajak DECIMAL(12,2) NOT NULL DEFAULT 0");
            }
            if (!in_array('metode_pembayaran', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN metode_pembayaran VARCHAR(30) NOT NULL DEFAULT 'Tunai'");
            }
            if (!in_array('merchant_order_id', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN merchant_order_id VARCHAR(50) NULL");
                $pdo->exec("CREATE UNIQUE INDEX idx_transaksi_merchant_order ON tbl_transaksi (merchant_order_id)");
            }
            if (!in_array('duitku_reference', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN duitku_reference VARCHAR(100) NULL");
            }
            if (!in_array('duitku_payment_url', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN duitku_payment_url TEXT NULL");
            }
            if (!in_array('payment_status', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'UNPAID'");
            }
            if (!in_array('payment_result_code', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN payment_result_code VARCHAR(10) NULL");
            }
            if (!in_array('paid_at', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN paid_at DATETIME NULL");
            }
            if (!in_array('duitku_payload', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN duitku_payload TEXT NULL");
            }
            if (!in_array('stock_processed', $existingCols)) {
                $pdo->exec("ALTER TABLE tbl_transaksi ADD COLUMN stock_processed TINYINT(1) NOT NULL DEFAULT 0");
            }
        } catch (Exception $eCols) {
            // Abaikan jika alter dibatasi
        }

        // 1c. TAMBAH KOLOM BARCODE PADA TBL_PRODUK JIKA BELUM ADA
        try {
            $stmtProdCols = $pdo->query("SHOW COLUMNS FROM tbl_produk");
            $existingProdCols = $stmtProdCols ? $stmtProdCols->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!in_array('barcode', $existingProdCols)) {
                $pdo->exec("ALTER TABLE tbl_produk ADD COLUMN barcode VARCHAR(50) NULL AFTER nama_produk");
            }
            // Auto-generate barcode default untuk produk yang belum memiliki barcode
            $pdo->exec("UPDATE tbl_produk SET barcode = CONCAT('PRD', LPAD(id_produk, 5, '0')) WHERE barcode IS NULL OR barcode = ''");
        } catch (Exception $eProdCols) {
            // Abaikan jika alter dibatasi
        }

        // 1d. TAMBAH KOLOM RFID_UID PADA TBL_PELANGGAN JIKA BELUM ADA
        try {
            $stmtPelCols = $pdo->query("SHOW COLUMNS FROM tbl_pelanggan");
            $existingPelCols = $stmtPelCols ? $stmtPelCols->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!in_array('rfid_uid', $existingPelCols)) {
                $pdo->exec("ALTER TABLE tbl_pelanggan ADD COLUMN rfid_uid VARCHAR(50) NULL UNIQUE AFTER nama_pelanggan");
            }
            // Auto-generate rfid_uid default untuk pelanggan yang belum punya (contoh: RFID-1001, RFID-1002, dst.)
            $pdo->exec("UPDATE tbl_pelanggan SET rfid_uid = CONCAT('RFID-', (1000 + id_pelanggan)) WHERE rfid_uid IS NULL OR rfid_uid = ''");
        } catch (Exception $ePelCols) {
            // Abaikan jika alter dibatasi
        }

        // 2. BUAT VIEWS OTOMATIS (Mencegah error 'Base table or view not found: view_transaksi_pelanggan')
        $pdo->exec("
            CREATE OR REPLACE VIEW view_pelanggan AS
            SELECT id_pelanggan, nama_pelanggan, rfid_uid, kota
            FROM tbl_pelanggan;

            CREATE OR REPLACE VIEW view_transaksi_pelanggan AS
            SELECT 
                t.id_transaksi,
                t.tanggal,
                p.nama_pelanggan,
                p.rfid_uid,
                p.kota,
                t.total_belanja
            FROM tbl_transaksi t
            JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan;

            CREATE OR REPLACE VIEW view_total_belanja AS
            SELECT 
                p.id_pelanggan,
                p.nama_pelanggan,
                p.rfid_uid,
                p.kota,
                COUNT(t.id_transaksi) AS total_transaksi,
                COALESCE(SUM(t.total_belanja), 0) AS total_belanja
            FROM tbl_pelanggan p
            LEFT JOIN tbl_transaksi t ON p.id_pelanggan = t.id_pelanggan
            GROUP BY p.id_pelanggan, p.nama_pelanggan, p.rfid_uid, p.kota;
        ");

        // 3. SEED DATA AWAL JIKA TABEL KOSONG
        $cekKat = $pdo->query("SELECT COUNT(*) FROM tbl_kategori")->fetchColumn();
        if ($cekKat == 0) {
            $pdo->exec("
                INSERT INTO tbl_kategori (nama_kategori) VALUES
                ('Elektronik'), ('Sembako'), ('Alat Tulis Kantor'), ('Minuman & Makanan');
            ");
        }

        $cekProd = $pdo->query("SELECT COUNT(*) FROM tbl_produk")->fetchColumn();
        if ($cekProd == 0) {
            $pdo->exec("
                INSERT INTO tbl_produk (id_kategori, nama_produk, harga, stok) VALUES
                (1, 'Mouse Wireless', 150000, 25),
                (1, 'Keyboard Mechanical', 350000, 15),
                (1, 'Flashdisk 64GB', 100000, 40),
                (2, 'Beras Premium 5kg', 75000, 50),
                (2, 'Minyak Goreng 2L', 35000, 60),
                (3, 'Buku Tulis 1 Pak', 45000, 30),
                (3, 'Pulpen Gel 1 Box', 25000, 45),
                (4, 'Kopi Bubuk 250gr', 30000, 35);
            ");
        }

        $cekCust = $pdo->query("SELECT COUNT(*) FROM tbl_pelanggan")->fetchColumn();
        if ($cekCust == 0) {
            $pdo->exec("
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
                ('Kartika Putri', 'Ponorogo'),
                ('Lukman Hakim', 'Solo');
            ");
        }

        $cekTx = $pdo->query("SELECT COUNT(*) FROM tbl_transaksi")->fetchColumn();
        if ($cekTx == 0) {
            $pdo->exec("
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
            ");
        }

        $cekUser = $pdo->query("SELECT COUNT(*) FROM tbl_user")->fetchColumn();
        if ($cekUser == 0) {
            $passAdmin = password_hash('admin123', PASSWORD_BCRYPT);
            $passKasir = password_hash('kasir123', PASSWORD_BCRYPT);
            $stmtSeedU = $pdo->prepare("
                INSERT INTO tbl_user (username, password, nama_lengkap, role, status) VALUES
                ('admin', ?, 'Administrator Toko', 'Admin', 'Aktif'),
                ('kasir', ?, 'Kasir GopellMart', 'Kasir', 'Aktif')
            ");
            $stmtSeedU->execute([$passAdmin, $passKasir]);
        }

        // 4. BUAT STORED PROCEDURES
        $pdo->exec("DROP PROCEDURE IF EXISTS TampilPelanggan");
        $pdo->exec("CREATE PROCEDURE TampilPelanggan() BEGIN SELECT * FROM tbl_pelanggan; END");

        $pdo->exec("DROP PROCEDURE IF EXISTS CariPelanggan");
        $pdo->exec("CREATE PROCEDURE CariPelanggan(IN p_id INT) BEGIN SELECT * FROM tbl_pelanggan WHERE id_pelanggan = p_id; END");

        $pdo->exec("DROP PROCEDURE IF EXISTS TambahPelanggan");
        $pdo->exec("CREATE PROCEDURE TambahPelanggan(IN p_nama VARCHAR(100), IN p_kota VARCHAR(50)) BEGIN INSERT INTO tbl_pelanggan (nama_pelanggan, kota) VALUES (p_nama, p_kota); END");

        $pdo->exec("DROP PROCEDURE IF EXISTS HitungPelanggan");
        $pdo->exec("CREATE PROCEDURE HitungPelanggan(OUT jumlah INT) BEGIN SELECT COUNT(*) INTO jumlah FROM tbl_pelanggan; END");

        $pdo->exec("DROP PROCEDURE IF EXISTS TambahTransaksi");
        $pdo->exec("
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
            END
        ");

        // 5. BUAT STORED FUNCTIONS
        $pdo->exec("DROP FUNCTION IF EXISTS HitungDiskon");
        $pdo->exec("
            CREATE FUNCTION HitungDiskon(harga DECIMAL(12,2), diskon DECIMAL(5,2))
            RETURNS DECIMAL(12,2)
            DETERMINISTIC NO SQL
            BEGIN
                RETURN harga * (diskon / 100);
            END
        ");

        $pdo->exec("DROP FUNCTION IF EXISTS HitungPajak");
        $pdo->exec("
            CREATE FUNCTION HitungPajak(harga DECIMAL(12,2))
            RETURNS DECIMAL(12,2)
            DETERMINISTIC NO SQL
            BEGIN
                RETURN harga * 0.11;
            END
        ");

        $pdo->exec("DROP FUNCTION IF EXISTS KategoriBelanja");
        $pdo->exec("
            CREATE FUNCTION KategoriBelanja(total DECIMAL(12,2))
            RETURNS VARCHAR(30)
            DETERMINISTIC NO SQL
            BEGIN
                RETURN CASE
                    WHEN total >= 1000000 THEN 'Pelanggan Gold'
                    WHEN total >= 500000 THEN 'Pelanggan Silver'
                    ELSE 'Pelanggan Reguler'
                END;
            END
        ");

        // 6. BUAT DATABASE TRIGGERS
        $pdo->exec("DROP TRIGGER IF EXISTS after_insert_pelanggan");
        $pdo->exec("
            CREATE TRIGGER after_insert_pelanggan
            AFTER INSERT ON tbl_pelanggan
            FOR EACH ROW
            BEGIN
                INSERT INTO log_pelanggan (aktivitas, id_pelanggan)
                VALUES ('INSERT PELANGGAN', NEW.id_pelanggan);

                INSERT INTO tbl_log (tabel_terkait, aktivitas, keterangan)
                VALUES ('tbl_pelanggan', 'INSERT', CONCAT('Menambahkan pelanggan baru: ', NEW.nama_pelanggan));
            END
        ");

        $pdo->exec("DROP TRIGGER IF EXISTS after_update_pelanggan");
        $pdo->exec("
            CREATE TRIGGER after_update_pelanggan
            AFTER UPDATE ON tbl_pelanggan
            FOR EACH ROW
            BEGIN
                INSERT INTO log_perubahan_pelanggan (id_pelanggan, nama_lama, nama_baru)
                VALUES (OLD.id_pelanggan, OLD.nama_pelanggan, NEW.nama_pelanggan);

                INSERT INTO tbl_log (tabel_terkait, aktivitas, keterangan)
                VALUES ('tbl_pelanggan', 'UPDATE', CONCAT('Ubah nama ID ', OLD.id_pelanggan, ' dari ', OLD.nama_pelanggan, ' menjadi ', NEW.nama_pelanggan));
            END
        ");

        $pdo->exec("DROP TRIGGER IF EXISTS before_insert_transaksi");
        $pdo->exec("
            CREATE TRIGGER before_insert_transaksi
            BEFORE INSERT ON tbl_transaksi
            FOR EACH ROW
            BEGIN
                IF NEW.total_belanja < 0 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Total belanja tidak boleh negatif';
                END IF;
            END
        ");

        $pdo->exec("DROP TRIGGER IF EXISTS after_delete_pelanggan");
        $pdo->exec("
            CREATE TRIGGER after_delete_pelanggan
            AFTER DELETE ON tbl_pelanggan
            FOR EACH ROW
            BEGIN
                INSERT INTO tbl_log (tabel_terkait, aktivitas, keterangan)
                VALUES ('tbl_pelanggan', 'DELETE', CONCAT('Menghapus pelanggan: ', OLD.nama_pelanggan, ' (ID: ', OLD.id_pelanggan, ')'));
            END
        ");

    } catch (Exception $e) {
        // Abaikan atau log internal agar tidak crash
        error_log("AutoSelfHeal Notice: " . $e->getMessage());
    }
}

/**
 * Helper: Format Angka ke Rupiah
 */
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        if ($angka === null || $angka === '') return 'Rp 0';
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}

/**
 * Helper: Format Tanggal Indonesia
 */
if (!function_exists('formatTanggal')) {
    function formatTanggal($tanggal) {
        if (!$tanggal) return '-';
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $pecahkan = explode('-', date('Y-m-d', strtotime($tanggal)));
        if (count($pecahkan) === 3) {
            return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
        }
        return $tanggal;
    }
}

/**
 * Helper: Sanitasi Input
 */
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * =========================================================================
 * HELPER ROLE-BASED ACCESS CONTROL (RBAC) SISTEM TOKOKU POS
 * =========================================================================
 */

/**
 * Cek apakah pengguna sudah login, jika belum redirect ke login.php
 */
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (empty($_SESSION['user_id'])) {
            header('Location: login.php?error=unauthorized');
            exit;
        }
    }
}

/**
 * Cek apakah role pengguna memiliki izin akses
 * Contoh: requireRole(['Admin']); atau requireRole(['Admin', 'Kasir']);
 */
if (!function_exists('requireRole')) {
    function requireRole($allowed_roles = ['Admin']) {
        requireLogin();
        $user_role = $_SESSION['role'] ?? '';
        $allowed = (array)$allowed_roles;
        
        if (!in_array($user_role, $allowed)) {
            // Kasir mencoba membuka halaman admin
            if ($user_role === 'Kasir') {
                header('Location: kasir.php?error=forbidden');
                exit;
            } else {
                header('Location: login.php?error=forbidden');
                exit;
            }
        }
    }
}

/**
 * Cek apakah role user saat ini adalah Admin
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
    }
}

/**
 * Cek apakah role user saat ini adalah Kasir
 */
if (!function_exists('isKasir')) {
    function isKasir() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'Kasir';
    }
}
