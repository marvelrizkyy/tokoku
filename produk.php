<?php
/**
 * Manajemen Produk - Stitch Design System: TokoKu POS Retail Engine
 * Sistem Informasi Penjualan & POS (toko_ku)
 * CRUD Master Data Produk & Monitoring Stok
 */
require_once __DIR__ . '/config/db.php';
requireRole(['Admin']);

$page_title = 'Manajemen Produk & Stok';
$current_page = 'produk';

$success_message = null;
$error_message = null;
$edit_data = null;

// Handle Form POST Actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. TAMBAH PRODUK
    if ($action === 'tambah_produk') {
        $nama_produk = sanitize($_POST['nama_produk'] ?? '');
        $barcode     = sanitize($_POST['barcode'] ?? '');
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        $harga       = (float)($_POST['harga'] ?? 0);
        $stok        = (int)($_POST['stok'] ?? 0);

        if (empty($nama_produk)) {
            $error_message = "Nama produk wajib diisi.";
        } elseif ($harga <= 0) {
            $error_message = "Harga produk harus lebih dari 0.";
        } elseif ($stok < 0) {
            $error_message = "Jumlah stok tidak boleh negatif.";
        } else {
            try {
                if (empty($barcode)) {
                    $barcode = 'PRD' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                }
                $stmt = $pdo->prepare("INSERT INTO tbl_produk (nama_produk, barcode, id_kategori, harga, stok) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nama_produk, $barcode, $id_kategori > 0 ? $id_kategori : null, $harga, $stok]);
                $stmt->closeCursor();

                // Catat ke log
                try {
                    $logStmt = $pdo->prepare("INSERT INTO tbl_log (tabel_terkait, aktivitas, keterangan) VALUES ('tbl_produk', 'INSERT', ?)");
                    $logStmt->execute(["Menambahkan produk baru: {$nama_produk} [Barcode: {$barcode}] (Harga: Rp {$harga}, Stok: {$stok})"]);
                    $logStmt->closeCursor();
                } catch (Exception $eLog) {}

                $success_message = "Produk '{$nama_produk}' (Barcode: {$barcode}) berhasil ditambahkan ke katalog TokoKu.";
            } catch (PDOException $e) {
                $error_message = "Gagal menambah produk: " . $e->getMessage();
            }
        }
    }

    // 2. UPDATE PRODUK
    elseif ($action === 'update_produk') {
        $id_produk   = (int)($_POST['id_produk'] ?? 0);
        $nama_produk = sanitize($_POST['nama_produk'] ?? '');
        $barcode     = sanitize($_POST['barcode'] ?? '');
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        $harga       = (float)($_POST['harga'] ?? 0);
        $stok        = (int)($_POST['stok'] ?? 0);

        if ($id_produk <= 0 || empty($nama_produk)) {
            $error_message = "Data perubahan produk tidak valid.";
        } elseif ($harga <= 0) {
            $error_message = "Harga produk harus lebih dari 0.";
        } elseif ($stok < 0) {
            $error_message = "Jumlah stok tidak boleh negatif.";
        } else {
            try {
                if (empty($barcode)) {
                    $barcode = 'PRD' . str_pad($id_produk, 5, '0', STR_PAD_LEFT);
                }
                $stmt = $pdo->prepare("UPDATE tbl_produk SET nama_produk = ?, barcode = ?, id_kategori = ?, harga = ?, stok = ? WHERE id_produk = ?");
                $stmt->execute([$nama_produk, $barcode, $id_kategori > 0 ? $id_kategori : null, $harga, $stok, $id_produk]);
                $stmt->closeCursor();

                // Catat ke log
                try {
                    $logStmt = $pdo->prepare("INSERT INTO tbl_log (tabel_terkait, aktivitas, keterangan) VALUES ('tbl_produk', 'UPDATE', ?)");
                    $logStmt->execute(["Memperbarui produk ID {$id_produk}: {$nama_produk} [Barcode: {$barcode}] (Harga: Rp {$harga}, Stok: {$stok})"]);
                    $logStmt->closeCursor();
                } catch (Exception $eLog) {}

                $success_message = "Produk #PROD-{$id_produk} '{$nama_produk}' (Barcode: {$barcode}) berhasil diperbarui.";
            } catch (PDOException $e) {
                $error_message = "Gagal memperbarui produk: " . $e->getMessage();
            }
        }
    }

    // 3. HAPUS PRODUK
    elseif ($action === 'delete_produk') {
        $id_produk   = (int)($_POST['id_produk'] ?? 0);
        $nama_produk = sanitize($_POST['nama_produk'] ?? 'Produk');

        if ($id_produk <= 0) {
            $error_message = "ID Produk tidak valid.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM tbl_produk WHERE id_produk = ?");
                $stmt->execute([$id_produk]);
                $stmt->closeCursor();

                // Catat ke log
                try {
                    $logStmt = $pdo->prepare("INSERT INTO tbl_log (tabel_terkait, aktivitas, keterangan) VALUES ('tbl_produk', 'DELETE', ?)");
                    $logStmt->execute(["Menghapus produk: {$nama_produk} (ID: {$id_produk})"]);
                    $logStmt->closeCursor();
                } catch (Exception $eLog) {}

                $success_message = "Produk '{$nama_produk}' berhasil dihapus.";
            } catch (PDOException $e) {
                $isForeignKeyError = ($e->getCode() === '23000' || 
                                      strpos($e->getMessage(), '1451') !== false || 
                                      strpos($e->getMessage(), 'foreign key constraint fails') !== false);

                if ($isForeignKeyError) {
                    $error_message = "Produk '{$nama_produk}' tidak dapat dihapus karena tersimpan dalam riwayat transaksi penjualan.";
                } else {
                    $error_message = "Gagal menghapus produk: " . $e->getMessage();
                }
            }
        }
    }
}

// Cek mode edit via query parameter (?edit=ID)
if (isset($_GET['edit']) && (int)$_GET['edit'] > 0 && $pdo) {
    $edit_id = (int)$_GET['edit'];
    try {
        $stmtCari = $pdo->prepare("SELECT * FROM tbl_produk WHERE id_produk = ?");
        $stmtCari->execute([$edit_id]);
        $edit_data = $stmtCari->fetch();
        $stmtCari->closeCursor();
    } catch (PDOException $e) {
        $error_message = "Gagal memuat data produk: " . $e->getMessage();
    }
}

// Ambil Master Data Kategori
$daftar_kategori = [];
if ($pdo) {
    try {
        $daftar_kategori = $pdo->query("SELECT * FROM tbl_kategori ORDER BY nama_kategori ASC")->fetchAll();
    } catch (Exception $e) {}
}

// Ambil Seluruh Data Produk beserta Kategori
$daftar_produk = [];
$total_produk = 0;
$total_nilai_aset = 0;
$total_stok_kritis = 0;

if ($pdo) {
    try {
        $queryProduk = "
            SELECT 
                p.id_produk,
                p.barcode,
                p.id_kategori,
                p.nama_produk,
                p.harga,
                p.stok,
                COALESCE(k.nama_kategori, 'Tanpa Kategori') AS nama_kategori,
                (p.harga * p.stok) AS total_aset
            FROM tbl_produk p
            LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori
            ORDER BY p.id_produk DESC
        ";
        $daftar_produk = $pdo->query($queryProduk)->fetchAll();
        $total_produk = count($daftar_produk);

        foreach ($daftar_produk as $prod) {
            $total_nilai_aset += (float)$prod['total_aset'];
            if ($prod['stok'] < 20) {
                $total_stok_kritis++;
            }
        }
    } catch (PDOException $e) {
        $error_message = "Gagal memuat katalog produk: " . $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Area (Full Width & Height Flex Layout) -->
<main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-surface">

    <!-- Topbar Sticky Header -->
    <header class="bg-surface-container-lowest border-b border-outline-variant/40 px-6 sm:px-8 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 sticky top-0 z-30 shadow-[0_1px_8px_rgba(0,0,0,0.03)]">
        <div>
            <h1 class="font-headline font-bold text-lg sm:text-xl text-on-surface tracking-tight flex items-center gap-2">
                <span class="material-symbols-outlined text-primary-container text-[24px]">inventory_2</span>
                Manajemen Produk &amp; Stok
            </h1>
            <p class="text-xs text-secondary mt-0.5">Kelola data produk, harga jual satuan, pemantauan stok real-time, dan kategori barang</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-surface-container-low border border-outline-variant/30 text-on-surface">
                Total: <?= count($daftar_produk) ?> Produk
            </span>
            <a href="kasir.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary-container hover:bg-on-primary-fixed-variant text-on-primary text-xs font-bold shadow-xs transition">
                <span class="material-symbols-outlined text-[16px]">point_of_sale</span>
                <span>Buka Terminal Kasir</span>
            </a>
        </div>
    </header>

    <!-- Page Body Container (Full Width) -->
    <div class="p-4 sm:p-6 lg:p-8 space-y-6 flex-1 w-full max-w-full">

    <!-- NOTIFIKASI SUKSES / ERROR -->
    <?php if ($success_message): ?>
        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-emerald-600 text-[20px]">check_circle</span>
                <span class="font-medium"><?= htmlspecialchars($success_message) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900">
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-rose-600 text-[20px]">error</span>
                <span class="font-medium"><?= htmlspecialchars($error_message) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-700 hover:text-rose-900">
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- 4 KPI SUMMARY CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- KPI 1: Total Macam Produk -->
        <div class="p-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 shadow-xs flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-primary-container/10 text-primary-container flex items-center justify-center">
                <span class="material-symbols-outlined text-[24px]">inventory_2</span>
            </div>
            <div>
                <p class="text-[11px] font-semibold text-secondary uppercase tracking-wider">Macam Produk</p>
                <p class="text-xl font-bold font-headline text-on-surface mt-0.5"><?= $total_produk ?> <span class="text-xs font-normal text-secondary">SKU</span></p>
            </div>
        </div>

        <!-- KPI 2: Total Nilai Aset Stok -->
        <div class="p-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 shadow-xs flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-tertiary/10 text-tertiary flex items-center justify-center">
                <span class="material-symbols-outlined text-[24px]">account_balance_wallet</span>
            </div>
            <div>
                <p class="text-[11px] font-semibold text-secondary uppercase tracking-wider">Nilai Aset Stok</p>
                <p class="text-xl font-bold font-headline text-tertiary mt-0.5"><?= formatRupiah($total_nilai_aset) ?></p>
            </div>
        </div>

        <!-- KPI 3: Stok Menipis / Kritis -->
        <div class="p-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 shadow-xs flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-xl <?= $total_stok_kritis > 0 ? 'bg-amber-100 text-amber-700' : 'bg-surface-container text-secondary' ?> flex items-center justify-center">
                <span class="material-symbols-outlined text-[24px]">warning</span>
            </div>
            <div>
                <p class="text-[11px] font-semibold text-secondary uppercase tracking-wider">Stok Kritis (&lt;20)</p>
                <p class="text-xl font-bold font-headline <?= $total_stok_kritis > 0 ? 'text-amber-700' : 'text-on-surface' ?> mt-0.5"><?= $total_stok_kritis ?> <span class="text-xs font-normal text-secondary">Produk</span></p>
            </div>
        </div>

        <!-- KPI 4: Kategori Terdaftar -->
        <div class="p-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 shadow-xs flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-[24px]">category</span>
            </div>
            <div>
                <p class="text-[11px] font-semibold text-secondary uppercase tracking-wider">Kategori Barang</p>
                <p class="text-xl font-bold font-headline text-on-surface mt-0.5"><?= count($daftar_kategori) ?> <span class="text-xs font-normal text-secondary">Kategori</span></p>
            </div>
        </div>
    </div>

    <!-- MAIN TWO COLUMN LAYOUT: Form (Left) & Product List (Right) -->
    <div class="grid grid-cols-12 gap-6">

        <!-- LEFT COLUMN: Form Tambah / Edit Produk (col-span-12 lg:col-span-4) -->
        <div class="col-span-12 lg:col-span-4">
            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 shadow-xs p-5 sticky top-20">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-outline-variant/30">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary-container text-[20px]">
                            <?= $edit_data ? 'edit_note' : 'add_circle' ?>
                        </span>
                        <h3 class="font-headline font-bold text-sm text-on-surface">
                            <?= $edit_data ? 'Edit Produk #' . $edit_data['id_produk'] : 'Tambah Produk Baru' ?>
                        </h3>
                    </div>
                    <?php if ($edit_data): ?>
                        <a href="produk.php" class="text-xs text-secondary hover:text-error transition font-semibold flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">cancel</span>
                            <span>Batal</span>
                        </a>
                    <?php endif; ?>
                </div>

                <form method="POST" action="produk.php" class="space-y-4">
                    <input type="hidden" name="action" value="<?= $edit_data ? 'update_produk' : 'tambah_produk' ?>">
                    <?php if ($edit_data): ?>
                        <input type="hidden" name="id_produk" value="<?= $edit_data['id_produk'] ?>">
                    <?php endif; ?>

                    <!-- Nama Produk -->
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1.5">Nama Produk <span class="text-error">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-secondary material-symbols-outlined text-[18px]">shopping_bag</span>
                            <input type="text" name="nama_produk" required
                                   value="<?= htmlspecialchars($edit_data['nama_produk'] ?? '') ?>"
                                   placeholder="Contoh: Mouse Wireless M185"
                                   class="w-full h-10 pl-9 pr-3 rounded-lg bg-surface text-on-surface text-xs font-medium border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container">
                        </div>
                    </div>

                    <!-- Barcode / SKU Code -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-on-surface">Barcode / Kode Produk</label>
                            <button type="button" onclick="generateAutoBarcode()" class="text-[10px] text-primary hover:text-primary/80 font-bold flex items-center gap-0.5 transition">
                                <span class="material-symbols-outlined text-[13px]">auto_fix_high</span>
                                <span>Generate Otomatis</span>
                            </button>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-secondary material-symbols-outlined text-[18px]">barcode</span>
                            <input type="text" id="inputBarcode" name="barcode"
                                   value="<?= htmlspecialchars($edit_data['barcode'] ?? '') ?>"
                                   placeholder="Contoh: PRD00001 (Otomatis jika kosong)"
                                   class="w-full h-10 pl-9 pr-3 rounded-lg bg-surface text-on-surface text-xs font-mono font-bold border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container">
                        </div>
                        <p class="text-[10px] text-secondary mt-1">Bisa scan barcode fisik atau generate otomatis jika dikosongkan</p>
                    </div>

                    <!-- Kategori Produk -->
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1.5">Kategori Barang</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-secondary material-symbols-outlined text-[18px]">category</span>
                            <select name="id_kategori" class="w-full h-10 pl-9 pr-8 rounded-lg bg-surface text-on-surface text-xs font-medium border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container appearance-none">
                                <option value="0">-- Pilih Kategori --</option>
                                <?php foreach ($daftar_kategori as $kat): ?>
                                    <option value="<?= $kat['id_kategori'] ?>" <?= (isset($edit_data['id_kategori']) && $edit_data['id_kategori'] == $kat['id_kategori']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kat['nama_kategori']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="absolute right-3 top-2.5 text-secondary pointer-events-none material-symbols-outlined text-[18px]">arrow_drop_down</span>
                        </div>
                    </div>

                    <!-- Harga Jual Satuan (Rp) -->
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1.5">Harga Jual (Rp) <span class="text-error">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-xs font-bold text-secondary">Rp</span>
                            <input type="number" name="harga" required min="1" step="any"
                                   value="<?= isset($edit_data['harga']) ? (float)$edit_data['harga'] : '' ?>"
                                   placeholder="Contoh: 6000"
                                   class="w-full h-10 pl-9 pr-3 rounded-lg bg-surface text-on-surface text-xs font-bold font-mono border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container">
                        </div>
                    </div>

                    <!-- Stok Awal / Tersedia -->
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1.5">Stok Tersedia (Unit) <span class="text-error">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-secondary material-symbols-outlined text-[18px]">inventory</span>
                            <input type="number" name="stok" required min="0" step="1"
                                   value="<?= isset($edit_data['stok']) ? (int)$edit_data['stok'] : '0' ?>"
                                   placeholder="0"
                                   class="w-full h-10 pl-9 pr-3 rounded-lg bg-surface text-on-surface text-xs font-bold font-mono border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container">
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-primary-container hover:bg-on-primary-fixed-variant text-on-primary font-headline font-bold text-xs shadow-sm transition active:scale-[0.98] flex items-center justify-center gap-2 mt-2">
                        <span class="material-symbols-outlined text-[18px]"><?= $edit_data ? 'save' : 'add' ?></span>
                        <span><?= $edit_data ? 'Simpan Perubahan Produk' : 'Simpan Produk Baru' ?></span>
                    </button>
                </form>
            </div>
        </div>

        <!-- RIGHT COLUMN: Tabel Daftar Produk (col-span-12 lg:col-span-8) -->
        <div class="col-span-12 lg:col-span-8 space-y-4">
            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 shadow-xs overflow-hidden">
                
                <!-- Table Header with Search & Filter -->
                <div class="p-4 border-b border-outline-variant/30 bg-surface-container-low flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">list_alt</span>
                        <h3 class="font-headline font-bold text-sm text-on-surface">Daftar Katalog Produk</h3>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-surface-container-high text-secondary" id="productCountLabel"><?= count($daftar_produk) ?></span>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-64">
                        <span class="absolute left-2.5 top-2 text-secondary material-symbols-outlined text-[16px]">search</span>
                        <input type="text" id="tableSearch" oninput="searchProducts()" placeholder="Cari nama atau kategori..."
                               class="w-full h-8 pl-8 pr-3 rounded-lg bg-surface text-on-surface text-xs border border-outline-variant/40 focus:outline-none focus:ring-1 focus:ring-primary-container">
                    </div>
                </div>

                <!-- Category Quick Filter Pills -->
                <div class="px-4 py-2 border-b border-outline-variant/20 flex overflow-x-auto gap-2 no-scrollbar text-[11px]">
                    <button type="button" onclick="filterTableCategory('ALL', this)" class="cat-filter-btn active px-3 py-1 rounded-lg bg-primary-container text-white font-bold whitespace-nowrap shadow-xs">
                        Semua Kategori
                    </button>
                    <?php 
                    $uniqueCats = array_unique(array_filter(array_column($daftar_produk, 'nama_kategori')));
                    foreach ($uniqueCats as $uCat): ?>
                        <button type="button" onclick="filterTableCategory('<?= htmlspecialchars($uCat) ?>', this)" class="cat-filter-btn px-3 py-1 rounded-lg bg-surface-container text-secondary hover:text-on-surface hover:bg-surface-container-high transition font-medium whitespace-nowrap">
                            <?= htmlspecialchars($uCat) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-on-surface border-collapse" id="productTable">
                        <thead class="bg-surface-container text-secondary font-headline text-[11px] uppercase tracking-wider border-b border-outline-variant/30">
                            <tr>
                                <th class="py-3 px-3.5 text-center font-bold w-12">ID</th>
                                <th class="py-3 px-3.5 font-bold">Produk &amp; Barcode</th>
                                <th class="py-3 px-3.5 text-right font-bold">Harga Satuan</th>
                                <th class="py-3 px-3.5 text-center font-bold">Stok</th>
                                <th class="py-3 px-3.5 text-right font-bold">Aset Stok</th>
                                <th class="py-3 px-3.5 text-center font-bold w-28">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/20 font-body">
                            <?php if (empty($daftar_produk)): ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-secondary">
                                        <span class="material-symbols-outlined text-[32px] text-secondary/40 block mb-1">inventory_2</span>
                                        <span>Belum ada produk yang tersimpan di database.</span>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daftar_produk as $prod): 
                                    $isHabis = $prod['stok'] <= 0;
                                    $isKritis = $prod['stok'] > 0 && $prod['stok'] < 20;
                                    $barcodeVal = !empty($prod['barcode']) ? $prod['barcode'] : ('PRD' . str_pad($prod['id_produk'], 5, '0', STR_PAD_LEFT));
                                ?>
                                    <tr class="product-row hover:bg-surface-container-low transition"
                                        data-name="<?= strtolower(htmlspecialchars($prod['nama_produk'])) ?>"
                                        data-barcode="<?= strtolower(htmlspecialchars($barcodeVal)) ?>"
                                        data-cat="<?= htmlspecialchars($prod['nama_kategori']) ?>">
                                        
                                        <!-- ID -->
                                        <td class="py-3 px-3.5 text-center font-mono text-[11px] text-secondary">
                                            #<?= str_pad($prod['id_produk'], 3, '0', STR_PAD_LEFT) ?>
                                        </td>

                                        <!-- Nama Produk & Barcode -->
                                        <td class="py-3 px-3.5">
                                            <p class="font-bold text-on-surface leading-tight font-headline"><?= htmlspecialchars($prod['nama_produk']) ?></p>
                                            <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                                <span class="inline-block text-[10px] font-semibold text-secondary">
                                                    <?= htmlspecialchars($prod['nama_kategori']) ?>
                                                </span>
                                                <span class="text-outline-variant/60 text-[10px]">&bull;</span>
                                                <span class="inline-flex items-center gap-1 text-[10px] font-mono font-bold text-primary px-1.5 py-0.5 rounded bg-primary-container/10 border border-primary-container/20">
                                                    <span class="material-symbols-outlined text-[13px]">barcode</span>
                                                    <span><?= htmlspecialchars($barcodeVal) ?></span>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Harga Satuan -->
                                        <td class="py-3 px-3.5 text-right font-mono font-bold text-primary-container">
                                            <?= formatRupiah($prod['harga']) ?>
                                        </td>

                                        <!-- Stok -->
                                        <td class="py-3 px-3.5 text-center">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $isHabis ? 'bg-rose-100 text-rose-800' : ($isKritis ? 'bg-amber-100 text-amber-800' : 'bg-emerald-50 text-emerald-800') ?>">
                                                <?= $prod['stok'] ?> unit
                                                <?php if ($isHabis): ?>
                                                    (Habis)
                                                <?php elseif ($isKritis): ?>
                                                    (Kritis)
                                                <?php endif; ?>
                                            </span>
                                        </td>

                                        <!-- Total Aset -->
                                        <td class="py-3 px-3.5 text-right font-mono text-secondary text-[11px]">
                                            <?= formatRupiah($prod['total_aset']) ?>
                                        </td>

                                        <!-- Aksi -->
                                        <td class="py-3 px-3.5 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <!-- Barcode & QR View/Print Button -->
                                                <button type="button" 
                                                        onclick="openBarcodeModal('<?= htmlspecialchars(addslashes($prod['nama_produk'])) ?>', '<?= htmlspecialchars(addslashes($barcodeVal)) ?>', '<?= formatRupiah($prod['harga']) ?>', '<?= htmlspecialchars(addslashes($prod['nama_kategori'])) ?>')"
                                                        class="p-1.5 rounded-lg text-primary bg-primary-container/10 hover:bg-primary-container hover:text-white transition" 
                                                        title="Lihat & Cetak Label Barcode / QR Code">
                                                    <span class="material-symbols-outlined text-[16px]">qr_code_2</span>
                                                </button>

                                                <!-- Edit Button -->
                                                <a href="produk.php?edit=<?= $prod['id_produk'] ?>" class="p-1.5 rounded-lg text-secondary hover:text-primary hover:bg-surface-container transition" title="Edit Produk">
                                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                                </a>

                                                <!-- Delete Button Form -->
                                                <form method="POST" action="produk.php" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk \'<?= htmlspecialchars(addslashes($prod['nama_produk'])) ?>\'?');" class="inline">
                                                    <input type="hidden" name="action" value="delete_produk">
                                                    <input type="hidden" name="id_produk" value="<?= $prod['id_produk'] ?>">
                                                    <input type="hidden" name="nama_produk" value="<?= htmlspecialchars($prod['nama_produk']) ?>">
                                                    <button type="submit" class="p-1.5 rounded-lg text-secondary hover:text-error hover:bg-rose-50 transition" title="Hapus Produk">
                                                        <span class="material-symbols-outlined text-[16px]">delete</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>

</div>

<!-- Modal Label Barcode & QR Code (Printable) -->
<div id="barcodeModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="bg-surface-container-lowest rounded-2xl max-w-sm w-full border border-outline-variant/30 shadow-2xl overflow-hidden transition-all transform animate-in fade-in zoom-in-95">
        <!-- Modal Header -->
        <div class="px-5 py-4 border-b border-outline-variant/30 flex items-center justify-between bg-surface-container-low">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary-container text-[22px]">barcode_scanner</span>
                <h3 class="font-headline font-bold text-sm text-on-surface">Label Barcode &amp; QR Code</h3>
            </div>
            <button type="button" onclick="closeBarcodeModal()" class="text-secondary hover:text-on-surface p-1 rounded-lg">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- Printable Label Area -->
        <div class="p-5 text-center space-y-4" id="printableBarcodeArea">
            <div class="border-b border-dashed border-outline-variant/40 pb-3">
                <span class="text-[10px] font-bold text-primary tracking-widest uppercase block">GOPELLMART POS</span>
                <h4 id="modalProdName" class="font-headline font-bold text-sm text-on-surface mt-1 line-clamp-2 leading-tight">Nama Produk</h4>
                <div class="flex items-center justify-center gap-2 mt-1">
                    <span id="modalProdKategori" class="text-[10px] text-secondary">Kategori</span>
                    <span class="text-secondary">&bull;</span>
                    <span id="modalProdPrice" class="font-mono font-bold text-sm text-primary-container">Rp 0</span>
                </div>
            </div>

            <!-- 1D Barcode SVG -->
            <div class="p-2.5 bg-white rounded-xl border border-outline-variant/20 flex flex-col items-center justify-center shadow-2xs">
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Barcode 1D (Code 128)</p>
                <svg id="barcodeCanvas" class="max-w-full"></svg>
            </div>

            <!-- 2D QR Code Canvas -->
            <div class="p-3 bg-white rounded-xl border border-outline-variant/20 flex flex-col items-center justify-center shadow-2xs">
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-2">QR Code 2D (Mobile Scan)</p>
                <div id="qrcodeCanvas" class="flex items-center justify-center"></div>
                <p id="modalBarcodeText" class="font-mono font-bold text-xs text-slate-800 mt-2 tracking-wider">PRD00001</p>
            </div>

            <p class="text-[11px] text-secondary">
                Dapat di-scan dengan scanner gun USB atau kamera HP di terminal kasir GopellMart.
            </p>
        </div>

        <!-- Modal Footer -->
        <div class="px-5 py-3.5 bg-surface-container-low border-t border-outline-variant/30 flex items-center justify-end gap-2.5">
            <button type="button" onclick="closeBarcodeModal()" class="px-4 py-2 rounded-xl text-secondary hover:bg-surface-container text-xs font-semibold transition">
                Tutup
            </button>
            <button type="button" onclick="printBarcodeLabel()" class="px-4 py-2 rounded-xl bg-primary-container hover:bg-on-primary-fixed-variant text-white text-xs font-bold shadow-xs transition flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">print</span>
                <span>Cetak Label Stiker</span>
            </button>
        </div>
    </div>
</div>

<!-- Print Styles for Label Printing -->
<style>
@media print {
    body * {
        visibility: hidden !important;
    }
    #printableBarcodeArea, #printableBarcodeArea * {
        visibility: visible !important;
    }
    #printableBarcodeArea {
        position: fixed !important;
        left: 0 !important;
        top: 0 !important;
        width: 80mm !important;
        padding: 10px !important;
        margin: 0 !important;
        background: white !important;
        color: black !important;
        box-shadow: none !important;
        border: 1px solid #ccc !important;
    }
}
</style>

<!-- CDN Script JsBarcode & QRCode.js -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<!-- Filter, Search & Barcode JavaScript -->
<script>
    let currentCategoryFilter = 'ALL';
    let qrcodeInstance = null;

    function generateAutoBarcode() {
        const randomNum = Math.floor(10000 + Math.random() * 90000);
        document.getElementById('inputBarcode').value = 'PRD' + randomNum;
    }

    function openBarcodeModal(name, barcode, price, category) {
        document.getElementById('modalProdName').innerText = name;
        document.getElementById('modalProdKategori').innerText = category || 'Produk';
        document.getElementById('modalProdPrice').innerText = price;
        document.getElementById('modalBarcodeText').innerText = barcode;

        // Render Barcode 1D (Code128)
        try {
            JsBarcode("#barcodeCanvas", barcode, {
                format: "CODE128",
                lineColor: "#0f172a",
                width: 2,
                height: 48,
                displayValue: true,
                fontSize: 12,
                font: "monospace",
                margin: 4
            });
        } catch (e) {
            console.error('JsBarcode error:', e);
        }

        // Render QR Code 2D
        const qrContainer = document.getElementById("qrcodeCanvas");
        qrContainer.innerHTML = '';
        try {
            qrcodeInstance = new QRCode(qrContainer, {
                text: barcode,
                width: 110,
                height: 110,
                colorDark: "#0f172a",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        } catch (e) {
            console.error('QRCode error:', e);
        }

        document.getElementById('barcodeModal').classList.remove('hidden');
    }

    function closeBarcodeModal() {
        document.getElementById('barcodeModal').classList.add('hidden');
    }

    function printBarcodeLabel() {
        window.print();
    }

    function searchProducts() {
        applyFilters();
    }

    function filterTableCategory(cat, btn) {
        currentCategoryFilter = cat;
        document.querySelectorAll('.cat-filter-btn').forEach(b => {
            b.className = 'cat-filter-btn px-3 py-1 rounded-lg bg-surface-container text-secondary hover:text-on-surface hover:bg-surface-container-high transition font-medium whitespace-nowrap';
        });
        btn.className = 'cat-filter-btn active px-3 py-1 rounded-lg bg-primary-container text-white font-bold whitespace-nowrap shadow-xs';
        applyFilters();
    }

    function applyFilters() {
        const query = (document.getElementById('tableSearch').value || '').toLowerCase().trim();
        const rows = document.querySelectorAll('.product-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const name = row.getAttribute('data-name') || '';
            const barcode = row.getAttribute('data-barcode') || '';
            const cat = row.getAttribute('data-cat') || '';

            const matchQuery = !query || name.includes(query) || barcode.includes(query) || cat.toLowerCase().includes(query);
            const matchCat = currentCategoryFilter === 'ALL' || cat === currentCategoryFilter;

            if (matchQuery && matchCat) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const countBadge = document.getElementById('productCountLabel');
        if (countBadge) {
            countBadge.innerText = visibleCount;
        }
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
