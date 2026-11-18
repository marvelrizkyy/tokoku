<?php
/**
 * GopellMart Online Shop & Portal Pembeli
 * Halaman Khusus User/Pembeli untuk Belanja Online Jarak Jauh (Tanpa ke Toko)
 * Fitur:
 * - Login menggunakan Nomor Kartu RFID Member
 * - Memilih produk dari katalog online (Tanpa Scan Barcode)
 * - Pembayaran Khusus Non-Tunai (QRIS & Transfer Bank) - TANPA TUNAI
 * - Cek Tier Loyalitas & Riwayat E-Nota
 */
session_start();
require_once __DIR__ . '/config/db.php';

$page_title = 'GopellMart Online Shop';
$current_page = 'pembeli';

$success_message = null;
$error_message = null;

// Handle Login / Logout Kartu RFID
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. LOGIN DENGAN KARTU RFID / ID
    if ($action === 'login_rfid') {
        $rfid_input = trim($_POST['rfid_code'] ?? '');
        if (empty($rfid_input)) {
            $error_message = "Harap masukkan nomor kartu RFID Anda.";
        } else {
            try {
                $stmtCari = $pdo->prepare("
                    SELECT id_pelanggan, nama_pelanggan, rfid_uid, kota 
                    FROM tbl_pelanggan 
                    WHERE rfid_uid = ? OR id_pelanggan = ? OR nama_pelanggan LIKE ?
                    LIMIT 1
                ");
                $stmtCari->execute([$rfid_input, (int)$rfid_input, "%{$rfid_input}%"]);
                $pembeli = $stmtCari->fetch();
                $stmtCari->closeCursor();

                if ($pembeli) {
                    $_SESSION['pembeli_id'] = (int)$pembeli['id_pelanggan'];
                    $success_message = "Selamat datang, " . htmlspecialchars($pembeli['nama_pelanggan']) . "! Kartu RFID berhasil terverifikasi.";
                } else {
                    $error_message = "Nomor kartu RFID '{$rfid_input}' tidak ditemukan.";
                }
            } catch (Exception $e) {
                $error_message = "Gagal memproses kartu: " . $e->getMessage();
            }
        }
    }

    // 2. LOGOUT KARTU
    elseif ($action === 'logout_rfid') {
        unset($_SESSION['pembeli_id']);
        $success_message = "Anda telah keluar dari akun kartu member.";
    }

    // 3. CHECKOUT BELANJA ONLINE (KHUSUS NON-TUNAI)
    elseif ($action === 'checkout_online') {
        $id_pelanggan = (int)($_SESSION['pembeli_id'] ?? 0);
        $raw_metode   = trim($_POST['metode_pembayaran'] ?? 'QRIS');
        $items_raw    = $_POST['items_json'] ?? '[]';
        $cart_items   = json_decode($items_raw, true);

        // Validasi: TIDAK BOLEH TUNAI
        if (strcasecmp($raw_metode, 'Tunai') === 0) {
            $error_message = "Pembayaran tunai tidak tersedia untuk belanja online. Harap pilih QRIS atau Transfer Bank.";
        } elseif ($id_pelanggan <= 0) {
            $error_message = "Silakan login menggunakan kartu member RFID Anda terlebih dahulu.";
        } elseif (empty($cart_items) || !is_array($cart_items)) {
            $error_message = "Keranjang belanja Anda masih kosong. Silakan pilih produk terlebih dahulu.";
        } else {
            if (strcasecmp($raw_metode, 'QRIS') === 0) {
                $metode_pembayaran = 'QRIS';
            } else {
                $metode_pembayaran = 'Transfer / Debit';
            }

            try {
                $pdo->beginTransaction();

                $subtotal_kotor = 0;
                $validated_items = [];

                foreach ($cart_items as $item) {
                    $item_id  = (int)($item['id_produk'] ?? 0);
                    $item_qty = (int)($item['jumlah'] ?? 0);

                    if ($item_id <= 0 || $item_qty <= 0) continue;

                    $stmtP = $pdo->prepare("SELECT id_produk, nama_produk, harga, stok FROM tbl_produk WHERE id_produk = ? FOR UPDATE");
                    $stmtP->execute([$item_id]);
                    $prod = $stmtP->fetch();
                    $stmtP->closeCursor();

                    if (!$prod) throw new Exception("Produk ID {$item_id} tidak ditemukan.");
                    if ($prod['stok'] < $item_qty) {
                        throw new Exception("Stok untuk '{$prod['nama_produk']}' tidak mencukupi (Tersedia: {$prod['stok']}, Dipesan: {$item_qty}).");
                    }

                    $item_subtotal = (float)$prod['harga'] * $item_qty;
                    $subtotal_kotor += $item_subtotal;

                    $validated_items[] = [
                        'id_produk'   => $item_id,
                        'nama_produk' => $prod['nama_produk'],
                        'jumlah'      => $item_qty,
                        'harga'       => (float)$prod['harga'],
                        'subtotal'    => $item_subtotal
                    ];
                }

                if (empty($validated_items)) {
                    throw new Exception("Tidak ada produk valid di keranjang belanja.");
                }

                // Hitung Pajak PPN 11%
                $nominal_pajak = $subtotal_kotor * 0.11;
                $grand_total   = $subtotal_kotor + $nominal_pajak;
                $tanggal_order = date('Y-m-d');

                // Eksekusi SP TambahTransaksi untuk item pertama
                $first_item = $validated_items[0];
                $stmtSP = $pdo->prepare("CALL TambahTransaksi(?, ?, ?, ?)");
                $stmtSP->execute([$tanggal_order, $id_pelanggan, $first_item['id_produk'], $first_item['jumlah']]);
                $stmtSP->closeCursor();

                // Ambil ID transaksi terbaru
                $stmtId = $pdo->prepare("SELECT id_transaksi FROM tbl_transaksi WHERE id_pelanggan = ? ORDER BY id_transaksi DESC LIMIT 1");
                $stmtId->execute([$id_pelanggan]);
                $id_transaksi_baru = (int)$stmtId->fetchColumn();
                $stmtId->closeCursor();

                if ($id_transaksi_baru <= 0) {
                    $id_transaksi_baru = (int)$pdo->query("SELECT MAX(id_transaksi) FROM tbl_transaksi")->fetchColumn();
                }

                // Tambah item ke-2, ke-3, dst ke tbl_detail_transaksi
                $total_items = count($validated_items);
                if ($total_items > 1) {
                    $stmtInsertD = $pdo->prepare("INSERT INTO tbl_detail_transaksi (id_transaksi, id_produk, nama_produk, jumlah, harga) VALUES (?, ?, ?, ?, ?)");
                    $stmtUpdateS = $pdo->prepare("UPDATE tbl_produk SET stok = stok - ? WHERE id_produk = ?");

                    for ($i = 1; $i < $total_items; $i++) {
                        $other = $validated_items[$i];
                        $stmtInsertD->execute([$id_transaksi_baru, $other['id_produk'], $other['nama_produk'], $other['jumlah'], $other['harga']]);
                        $stmtInsertD->closeCursor();

                        $stmtUpdateS->execute([$other['jumlah'], $other['id_produk']]);
                        $stmtUpdateS->closeCursor();
                    }
                }

                // Update total belanja, metode pembayaran, pajak
                $stmtFinal = $pdo->prepare("
                    UPDATE tbl_transaksi 
                    SET total_belanja = ?, 
                        nominal_pajak = ?, 
                        nominal_bayar = ?, 
                        kembalian = 0, 
                        metode_pembayaran = ? 
                    WHERE id_transaksi = ?
                ");
                $stmtFinal->execute([$grand_total, $nominal_pajak, $grand_total, $metode_pembayaran, $id_transaksi_baru]);
                $stmtFinal->closeCursor();

                // Catat di log
                try {
                    $stmtLog = $pdo->prepare("INSERT INTO tbl_log (tabel_terkait, aktivitas, keterangan) VALUES ('tbl_transaksi', 'INSERT', ?)");
                    $stmtLog->execute(["Belanja Online Pembeli via {$metode_pembayaran} (ID Transaksi: {$id_transaksi_baru}, Total: Rp {$grand_total})"]);
                    $stmtLog->closeCursor();
                } catch (Exception $eLog) {}

                $pdo->commit();

                // Redirect langsung ke nota digital
                header("Location: nota.php?id={$id_transaksi_baru}&online=1");
                exit;

            } catch (Exception $e) {
                if ($pdo && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_message = "Gagal memproses pesanan online: " . $e->getMessage();
            }
        }
    }
}

// Auto-Login via URL GET Parameter (?rfid=RFID-1001)
if (isset($_GET['rfid']) && empty($_SESSION['pembeli_id'])) {
    $rfid_get = trim($_GET['rfid']);
    if (!empty($rfid_get)) {
        try {
            $stmtCariG = $pdo->prepare("
                SELECT id_pelanggan, nama_pelanggan, rfid_uid, kota 
                FROM tbl_pelanggan 
                WHERE rfid_uid = ? OR id_pelanggan = ?
                LIMIT 1
            ");
            $stmtCariG->execute([$rfid_get, (int)$rfid_get]);
            $pembeliG = $stmtCariG->fetch();
            $stmtCariG->closeCursor();

            if ($pembeliG) {
                $_SESSION['pembeli_id'] = (int)$pembeliG['id_pelanggan'];
                $success_message = "Selamat datang, " . htmlspecialchars($pembeliG['nama_pelanggan']) . "! Kartu RFID berhasil terverifikasi.";
            }
        } catch (Exception $eG) {}
    }
}

// Ambil data pembeli yang sedang aktif login
$current_pembeli = null;
$riwayat_belanja_pembeli = [];
$total_akumulasi_belanja = 0;
$kategori_loyalitas = 'Pelanggan Reguler';

if (!empty($_SESSION['pembeli_id']) && $pdo) {
    $cid = (int)$_SESSION['pembeli_id'];
    try {
        $stmtC = $pdo->prepare("SELECT id_pelanggan, nama_pelanggan, rfid_uid, kota FROM tbl_pelanggan WHERE id_pelanggan = ?");
        $stmtC->execute([$cid]);
        $current_pembeli = $stmtC->fetch();
        $stmtC->closeCursor();

        if ($current_pembeli) {
            // Hitung akumulasi & tier
            $stmtTot = $pdo->prepare("SELECT COALESCE(SUM(total_belanja), 0) FROM tbl_transaksi WHERE id_pelanggan = ?");
            $stmtTot->execute([$cid]);
            $total_akumulasi_belanja = (float)$stmtTot->fetchColumn();
            $stmtTot->closeCursor();

            if ($total_akumulasi_belanja >= 1000000) {
                $kategori_loyalitas = 'Pelanggan Gold';
            } elseif ($total_akumulasi_belanja >= 500000) {
                $kategori_loyalitas = 'Pelanggan Silver';
            } else {
                $kategori_loyalitas = 'Pelanggan Reguler';
            }

            // Ambil riwayat belanja pembeli
            $stmtRiw = $pdo->prepare("
                SELECT id_transaksi, tanggal, total_belanja, metode_pembayaran 
                FROM tbl_transaksi 
                WHERE id_pelanggan = ? 
                ORDER BY id_transaksi DESC 
                LIMIT 10
            ");
            $stmtRiw->execute([$cid]);
            $riwayat_belanja_pembeli = $stmtRiw->fetchAll();
            $stmtRiw->closeCursor();
        }
    } catch (Exception $e) {}
}

// Ambil Katalog Produk
$katalog_produk = [];
$daftar_kategori = [];
if ($pdo) {
    try {
        $katalog_produk = $pdo->query("
            SELECT p.id_produk, p.nama_produk, p.harga, p.stok, COALESCE(k.nama_kategori, 'Umum') AS nama_kategori 
            FROM tbl_produk p 
            LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori 
            ORDER BY p.nama_produk ASC
        ")->fetchAll();

        $daftar_kategori = $pdo->query("SELECT DISTINCT COALESCE(k.nama_kategori, 'Umum') AS nama_kategori FROM tbl_produk p LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori ORDER BY nama_kategori ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
}

// Ambil list demo akun pembeli untuk quick-testing
$demo_pembeli_list = [];
if ($pdo) {
    try {
        $demo_pembeli_list = $pdo->query("SELECT id_pelanggan, nama_pelanggan, rfid_uid FROM tbl_pelanggan ORDER BY id_pelanggan ASC LIMIT 6")->fetchAll();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Belanja Online GopellMart</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#b22b00',
                        'primary-container': '#ff5722',
                        'on-primary': '#ffffff',
                        surface: '#fcf8f8',
                        'surface-container-low': '#f7f2f2',
                        'surface-container': '#f1edec',
                        'surface-container-high': '#ebe7e7',
                        'surface-container-lowest': '#ffffff',
                        'on-surface': '#201a19',
                        secondary: '#5c5f62',
                        tertiary: '#006874',
                        error: '#ba1a1a',
                    },
                    fontFamily: {
                        headline: ['Plus Jakarta Sans', 'Inter', 'sans-serif'],
                        body: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-surface text-on-surface font-body min-h-screen flex flex-col">

    <!-- Topbar Header Toko Online -->
    <header class="bg-surface-container-lowest border-b border-outline-variant/40 sticky top-0 z-40 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
            <!-- Brand -->
            <div class="flex items-center gap-3">
                <a href="pembeli.php" class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-primary-container text-white flex items-center justify-center shadow-xs">
                        <span class="material-symbols-outlined text-[22px]">shopping_bag</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <h1 class="font-headline font-bold text-base text-on-surface leading-tight">GopellMart</h1>
                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-primary-container/10 text-primary border border-primary-container/20">ONLINE SHOP</span>
                        </div>
                        <p class="text-[11px] text-secondary">Belanja Kebutuhan Harian Tanpa Datang ke Toko</p>
                    </div>
                </a>
                <a href="welcome.php" class="hidden lg:inline-flex items-center gap-1 text-[11px] font-semibold text-secondary hover:text-on-surface transition ml-2 px-2.5 py-1 rounded-lg hover:bg-surface-container" title="Kembali ke Halaman Welcome">
                    <span class="material-symbols-outlined text-[15px]">home</span>
                    <span>Halaman Utama</span>
                </a>
            </div>

            <!-- User / Member Section -->
            <div class="flex items-center gap-2 sm:gap-3">
                <?php if ($current_pembeli): ?>
                    <!-- Member Pill -->
                    <div class="flex items-center gap-2.5 bg-surface-container-low px-3 py-1.5 rounded-xl border border-outline-variant/30">
                        <div class="w-7 h-7 rounded-lg bg-primary text-white font-bold text-xs flex items-center justify-center font-headline">
                            <?= strtoupper(substr($current_pembeli['nama_pelanggan'], 0, 1)) ?>
                        </div>
                        <div class="hidden sm:block text-left leading-tight">
                            <span class="font-bold text-xs text-on-surface block"><?= htmlspecialchars($current_pembeli['nama_pelanggan']) ?></span>
                            <span class="font-mono text-[10px] text-primary font-bold"><?= htmlspecialchars($current_pembeli['rfid_uid'] ?? '') ?></span>
                        </div>
                    </div>

                    <!-- Tombol Riwayat -->
                    <button type="button" onclick="openRiwayatModal()" class="px-3 py-1.5 rounded-xl bg-surface-container hover:bg-surface-container-high text-xs font-bold transition flex items-center gap-1.5" title="Riwayat Belanja">
                        <span class="material-symbols-outlined text-[16px]">receipt_long</span>
                        <span class="hidden md:inline">Riwayat Belanja</span>
                    </button>

                    <!-- Tombol Logout -->
                    <form method="POST" action="pembeli.php" class="inline">
                        <input type="hidden" name="action" value="logout_rfid">
                        <button type="submit" class="p-2 rounded-xl text-secondary hover:text-error hover:bg-rose-50 transition" title="Keluar dari Akun">
                            <span class="material-symbols-outlined text-[18px]">logout</span>
                        </button>
                    </form>
                <?php else: ?>
                    <button type="button" onclick="openLoginModal()" class="px-4 py-2 rounded-xl bg-primary-container hover:bg-on-primary-fixed-variant text-white font-headline text-xs font-bold shadow-xs transition flex items-center gap-1.5 active:scale-95">
                        <span class="material-symbols-outlined text-[18px]">contactless</span>
                        <span>Login Kartu RFID</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 flex-1 w-full space-y-6">

        <!-- Alerts -->
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

        <!-- Member RFID Status Banner (Jika Login) -->
        <?php if ($current_pembeli): ?>
            <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-surface-container-lowest via-surface-container-low to-primary-container/10 border border-primary-container/20 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-primary-container text-white flex items-center justify-center shadow-sm shrink-0">
                        <span class="material-symbols-outlined text-[28px]">contactless</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="font-headline font-bold text-base text-on-surface"><?= htmlspecialchars($current_pembeli['nama_pelanggan']) ?></h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= strpos($kategori_loyalitas, 'Gold') !== false ? 'bg-amber-100 text-amber-800 border border-amber-300' : (strpos($kategori_loyalitas, 'Silver') !== false ? 'bg-slate-200 text-slate-800' : 'bg-surface-container text-secondary') ?>">
                                <?= htmlspecialchars($kategori_loyalitas) ?>
                            </span>
                        </div>
                        <p class="text-xs text-secondary mt-0.5 flex items-center gap-1.5 flex-wrap">
                            <span>No. Kartu: <strong class="font-mono text-on-surface"><?= htmlspecialchars($current_pembeli['rfid_uid'] ?? '') ?></strong></span>
                            <span>&bull;</span>
                            <span>Domisili: <?= htmlspecialchars($current_pembeli['kota']) ?></span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 self-end md:self-center">
                    <div class="text-right">
                        <span class="text-[10px] text-secondary uppercase font-semibold tracking-wider block">Total Belanja Anda</span>
                        <span class="font-mono font-bold text-sm text-primary-container"><?= formatRupiah($total_akumulasi_belanja) ?></span>
                    </div>
                    <button type="button" onclick="openRiwayatModal()" class="px-3.5 py-2 rounded-xl bg-surface-container-lowest hover:bg-surface-container border border-outline-variant/30 text-xs font-bold text-on-surface transition flex items-center gap-1 shadow-2xs">
                        <span class="material-symbols-outlined text-[16px]">history</span>
                        <span>Riwayat Belanja</span>
                    </button>
                </div>
            </div>
        <?php else: ?>
            <!-- Banner Ajak Login Kartu RFID -->
            <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-primary-container/15 via-surface-container-low to-surface-container-lowest border border-primary-container/30 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-primary text-white flex items-center justify-center shrink-0 shadow-xs">
                        <span class="material-symbols-outlined text-[24px]">credit_card</span>
                    </div>
                    <div>
                        <h2 class="font-headline font-bold text-sm text-on-surface">Punya Kartu Member RFID GopellMart?</h2>
                        <p class="text-xs text-secondary mt-0.5">Login dengan kartu member Anda untuk kemudahan transaksi online dan pencatatan riwayat nota.</p>
                    </div>
                </div>
                <button type="button" onclick="openLoginModal()" class="px-4 py-2.5 rounded-xl bg-primary-container hover:bg-on-primary-fixed-variant text-white font-headline text-xs font-bold shadow-xs transition flex items-center justify-center gap-1.5 shrink-0 active:scale-95">
                    <span class="material-symbols-outlined text-[18px]">contactless</span>
                    <span>Masukkan Nomor Kartu Member</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Split Grid: Katalog Produk (Kiri) & Keranjang Belanja (Kanan) -->
        <div class="grid grid-cols-12 gap-6 items-start">

            <!-- KOLOM KIRI: Katalog Belanja Online (col-span-12 lg:col-span-8) -->
            <div class="col-span-12 lg:col-span-8 space-y-4">
                
                <div class="bg-surface-container-lowest rounded-2xl p-4 sm:p-5 border border-outline-variant/30 shadow-xs space-y-4">
                    
                    <!-- Search Bar & Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h3 class="font-headline font-bold text-base text-on-surface">Pilih Produk Kebutuhan</h3>
                            <p class="text-xs text-secondary mt-0.5">Pilih barang langsung dari katalog di bawah ini</p>
                        </div>

                        <!-- Live Search Input -->
                        <div class="relative w-full sm:w-64">
                            <span class="material-symbols-outlined absolute left-3 top-2.5 text-secondary text-[18px]">search</span>
                            <input type="text" id="katalogSearch" oninput="filterKatalog()" placeholder="Cari nama barang..."
                                   class="w-full h-9 pl-9 pr-3 text-xs bg-surface text-on-surface rounded-xl border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container">
                        </div>
                    </div>

                    <!-- Category Filter Pills -->
                    <div class="flex overflow-x-auto gap-2 pb-1 no-scrollbar text-xs">
                        <button type="button" onclick="filterKategori('ALL', this)" class="kategori-pill active px-3.5 py-1.5 rounded-xl bg-primary-container text-white font-bold whitespace-nowrap shadow-xs">
                            Semua Produk
                        </button>
                        <?php foreach ($daftar_kategori as $kat): ?>
                            <button type="button" onclick="filterKategori('<?= htmlspecialchars($kat) ?>', this)" class="kategori-pill px-3.5 py-1.5 rounded-xl bg-surface-container text-secondary hover:text-on-surface hover:bg-surface-container-high transition font-medium whitespace-nowrap">
                                <?= htmlspecialchars($kat) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Product Grid Cards (PILIH BARANG - TANPA SCAN) -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3.5" id="productGridOnline">
                        <?php foreach ($katalog_produk as $prod): 
                            $isOutOfStock = $prod['stok'] <= 0;
                            $isLowStock = $prod['stok'] > 0 && $prod['stok'] < 10;
                        ?>
                            <div class="product-item-card bg-surface-container-low hover:bg-surface-container-lowest rounded-2xl border <?= $isLowStock ? 'border-amber-300' : 'border-outline-variant/30' ?> p-3.5 flex flex-col justify-between transition-all duration-200 hover:shadow-md group"
                                 data-name="<?= strtolower(htmlspecialchars($prod['nama_produk'])) ?>"
                                 data-category="<?= htmlspecialchars($prod['nama_kategori']) ?>">
                                
                                <div>
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="text-[10px] font-semibold text-secondary uppercase tracking-wider block truncate">
                                            <?= htmlspecialchars($prod['nama_kategori']) ?>
                                        </span>
                                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full <?= $isOutOfStock ? 'bg-rose-100 text-rose-800' : ($isLowStock ? 'bg-amber-100 text-amber-800' : 'bg-surface-container text-secondary') ?>">
                                            <?= $isOutOfStock ? 'Habis' : 'Stok: ' . $prod['stok'] ?>
                                        </span>
                                    </div>

                                    <h4 class="font-headline font-bold text-xs text-on-surface mt-2 line-clamp-2 leading-snug group-hover:text-primary transition-colors">
                                        <?= htmlspecialchars($prod['nama_produk']) ?>
                                    </h4>
                                </div>

                                <div class="mt-3 pt-2.5 border-t border-outline-variant/20">
                                    <p class="font-headline font-bold text-sm text-primary-container mb-2">
                                        <?= formatRupiah($prod['harga']) ?>
                                    </p>

                                    <!-- Tombol Pilih Barang -->
                                    <button type="button" 
                                            <?= $isOutOfStock ? 'disabled' : '' ?>
                                            onclick="addToCartOnline(<?= $prod['id_produk'] ?>, '<?= htmlspecialchars(addslashes($prod['nama_produk'])) ?>', <?= (float)$prod['harga'] ?>, <?= (int)$prod['stok'] ?>)"
                                            class="w-full py-2 px-3 rounded-xl <?= $isOutOfStock ? 'bg-slate-200 text-slate-400 cursor-not-allowed' : 'bg-primary-container hover:bg-on-primary-fixed-variant text-white font-bold active:scale-95 shadow-xs' ?> text-xs transition flex items-center justify-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span>
                                        <span><?= $isOutOfStock ? 'Stok Habis' : '+ Keranjang' ?></span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>

            </div>

            <!-- KOLOM KANAN: Keranjang Belanja & Checkout Non-Tunai (col-span-12 lg:col-span-4) -->
            <div class="col-span-12 lg:col-span-4 sticky top-20 space-y-4">
                
                <div class="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/30 shadow-xs space-y-4">
                    
                    <div class="flex items-center justify-between pb-3 border-b border-outline-variant/30">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary-container text-[22px]">shopping_cart</span>
                            <h3 class="font-headline font-bold text-sm text-on-surface">Keranjang Belanja</h3>
                        </div>
                        <button type="button" onclick="clearCartOnline()" class="text-[11px] text-secondary hover:text-error transition font-semibold">
                            Kosongkan
                        </button>
                    </div>

                    <!-- List Item Keranjang -->
                    <div id="cartItemsList" class="space-y-2.5 max-h-64 overflow-y-auto pr-1">
                        <!-- Diisi via JavaScript -->
                    </div>

                    <!-- Kotak Kosong jika Keranjang Belum Ada Item -->
                    <div id="cartEmptyState" class="py-8 text-center text-secondary border border-dashed border-outline-variant/40 rounded-xl">
                        <span class="material-symbols-outlined text-[36px] text-secondary/30 block mb-1">shopping_basket</span>
                        <p class="text-xs font-semibold">Keranjang masih kosong</p>
                        <p class="text-[10px] text-secondary mt-0.5">Pilih produk di katalog sebelah kiri</p>
                    </div>

                    <!-- Rincian Total -->
                    <div class="p-3.5 rounded-xl bg-surface-container-low border border-outline-variant/20 space-y-2 text-xs">
                        <div class="flex justify-between items-center text-secondary">
                            <span>Subtotal Barang:</span>
                            <span class="font-mono font-bold text-on-surface" id="txtSubtotalOnline">Rp 0</span>
                        </div>
                        <div class="flex justify-between items-center text-secondary">
                            <span>PPN 11%:</span>
                            <span class="font-mono font-bold text-on-surface" id="txtPajakOnline">Rp 0</span>
                        </div>
                        <div class="pt-2 border-t border-outline-variant/30 flex justify-between items-center">
                            <span class="font-headline font-bold text-xs text-on-surface">Total Pembayaran:</span>
                            <span class="font-headline font-bold text-base text-primary-container" id="txtGrandTotalOnline">Rp 0</span>
                        </div>
                    </div>

                    <!-- FORM CHECKOUT KHUSUS NON-TUNAI -->
                    <form method="POST" action="pembeli.php" id="formCheckoutOnline" class="space-y-4">
                        <input type="hidden" name="action" value="checkout_online">
                        <input type="hidden" name="items_json" id="inputItemsJson" value="[]">
                        <input type="hidden" name="metode_pembayaran" id="inputMetodePembayaran" value="QRIS">

                        <!-- Pilihan Metode Pembayaran (KHUSUS NON-TUNAI) -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-on-surface">Pilih Metode Pembayaran Non-Tunai:</label>
                            
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" onclick="selectPayMethodOnline('QRIS', this)" class="pay-btn active py-2.5 px-3 rounded-xl border border-primary-container bg-primary-container/10 text-primary font-bold text-xs flex flex-col items-center justify-center gap-1 transition shadow-2xs">
                                    <span class="material-symbols-outlined text-[20px]">qr_code_scanner</span>
                                    <span>QRIS Digital</span>
                                </button>
                                <button type="button" onclick="selectPayMethodOnline('Transfer / Debit', this)" class="pay-btn py-2.5 px-3 rounded-xl border border-outline-variant/40 bg-surface-container text-secondary hover:text-on-surface font-medium text-xs flex flex-col items-center justify-center gap-1 transition">
                                    <span class="material-symbols-outlined text-[20px]">account_balance</span>
                                    <span>Transfer Bank</span>
                                </button>
                            </div>

                            <!-- Peringatan Tunai Dinonaktifkan -->
                            <div class="p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-[11px] flex items-start gap-2">
                                <span class="material-symbols-outlined text-amber-700 text-[16px] shrink-0 mt-0.5">info</span>
                                <div>
                                    <strong class="font-bold">Pembayaran Tunai Tidak Tersedia:</strong> Belanja online hanya melayani metode non-tunai (QRIS &amp; Transfer).
                                </div>
                            </div>
                        </div>

                        <!-- PANEL PREVIEW QRIS -->
                        <div id="boxQrisPreview" class="p-4 bg-white rounded-xl border border-slate-200 text-center space-y-2 shadow-2xs">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Standar QRIS Nasional</span>
                            <div class="w-28 h-28 mx-auto bg-slate-50 p-2 rounded-lg border border-slate-200 flex items-center justify-center">
                                <svg viewBox="0 0 100 100" class="w-full h-full text-slate-900 fill-current">
                                    <path d="M0,0 h30 v30 h-30 z M10,10 h10 v10 h-10 z M70,0 h30 v30 h-30 z M80,10 h10 v10 h-10 z M0,70 h30 v30 h-30 z M10,80 h10 v10 h-10 z M40,10 h20 v10 h-20 z M40,35 h10 v20 h-10 z M60,35 h30 v10 h-30 z M70,55 h20 v20 h-20 z M40,65 h15 v25 h-15 z M60,85 h30 v15 h-30 z"/>
                                </svg>
                            </div>
                            <p class="text-[11px] font-bold text-slate-700">Scan via BCA &bull; GoPay &bull; OVO &bull; Dana</p>
                        </div>

                        <!-- PANEL PREVIEW TRANSFER BANK -->
                        <div id="boxTransferPreview" class="hidden p-3.5 bg-surface-container-low rounded-xl border border-outline-variant/30 space-y-2 text-xs">
                            <div class="flex justify-between items-center p-2 rounded-lg bg-surface border border-outline-variant/20">
                                <span class="font-medium text-secondary">Bank BCA:</span>
                                <span class="font-mono font-bold text-on-surface">8820-1928-334</span>
                            </div>
                            <div class="flex justify-between items-center p-2 rounded-lg bg-surface border border-outline-variant/20">
                                <span class="font-medium text-secondary">Bank Mandiri:</span>
                                <span class="font-mono font-bold text-on-surface">144-00-192837-1</span>
                            </div>
                            <p class="text-[10px] text-secondary text-center">a.n GopellMart Retail Ponorogo</p>
                        </div>

                        <!-- Tombol Checkout -->
                        <?php if ($current_pembeli): ?>
                            <button type="submit" id="btnSubmitOnline" disabled 
                                    class="w-full py-3.5 px-4 rounded-xl bg-primary-container hover:bg-on-primary-fixed-variant disabled:opacity-50 disabled:cursor-not-allowed text-white font-headline font-bold text-xs shadow-md transition-all active:scale-[0.98] flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-[18px]">verified</span>
                                <span>Bayar Sekarang &amp; Cetak Nota</span>
                            </button>
                        <?php else: ?>
                            <button type="button" onclick="openLoginModal()"
                                    class="w-full py-3.5 px-4 rounded-xl bg-primary hover:bg-primary/90 text-white font-headline font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-[18px]">contactless</span>
                                <span>Login Kartu Member untuk Checkout</span>
                            </button>
                        <?php endif; ?>
                    </form>

                </div>

            </div>

        </div>

    </main>

    <!-- MODAL 1: Login Kartu RFID Member -->
    <div id="modalLoginRfid" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-surface-container-lowest rounded-2xl max-w-sm w-full border border-outline-variant/30 shadow-2xl overflow-hidden transition-all transform animate-in fade-in zoom-in-95">
            <!-- Modal Header -->
            <div class="px-5 py-4 border-b border-outline-variant/30 flex items-center justify-between bg-surface-container-low">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary-container text-[22px]">contactless</span>
                    <h3 class="font-headline font-bold text-sm text-on-surface">Login Kartu RFID Member</h3>
                </div>
                <button type="button" onclick="closeLoginModal()" class="text-secondary hover:text-on-surface p-1 rounded-lg">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <!-- Modal Body -->
            <form method="POST" action="pembeli.php" class="p-5 space-y-4">
                <input type="hidden" name="action" value="login_rfid">

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-on-surface">Nomor Kartu RFID Anda:</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-2.5 text-secondary text-[18px]">credit_card</span>
                        <input type="text" name="rfid_code" id="inputModalRfid" required autofocus
                               placeholder="Contoh: RFID-1001 atau tap kartu..."
                               class="w-full h-10 pl-9 pr-3 rounded-xl bg-surface text-on-surface text-xs font-mono font-bold border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container">
                    </div>
                    <p class="text-[10px] text-secondary">Ketikkan nomor kartu atau tap kartu RFID di sensor USB jika terhubung.</p>
                </div>

                <!-- Quick Pick Demo Akun -->
                <?php if (!empty($demo_pembeli_list)): ?>
                    <div class="pt-2 border-t border-outline-variant/20 space-y-1.5">
                        <span class="text-[10px] font-bold text-secondary uppercase tracking-wider block">Pilih Cepat Akun Terdaftar:</span>
                        <div class="flex flex-wrap gap-1.5">
                            <?php foreach ($demo_pembeli_list as $demo): ?>
                                <button type="button" onclick="setDemoRfid('<?= htmlspecialchars($demo['rfid_uid'] ?? '') ?>')" 
                                        class="px-2 py-1 rounded-lg bg-surface-container hover:bg-primary-container hover:text-white text-on-surface text-[10px] font-mono font-semibold transition border border-outline-variant/30">
                                    <?= htmlspecialchars($demo['nama_pelanggan']) ?> (<?= htmlspecialchars($demo['rfid_uid'] ?? '') ?>)
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeLoginModal()" class="px-4 py-2 rounded-xl text-secondary hover:bg-surface-container text-xs font-semibold transition">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-primary-container hover:bg-on-primary-fixed-variant text-white text-xs font-bold shadow-xs transition flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">login</span>
                        <span>Masuk Akun</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: Riwayat Belanja Member -->
    <div id="modalRiwayat" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-surface-container-lowest rounded-2xl max-w-lg w-full border border-outline-variant/30 shadow-2xl overflow-hidden transition-all transform animate-in fade-in zoom-in-95 flex flex-col max-h-[85vh]">
            <!-- Header -->
            <div class="px-5 py-4 border-b border-outline-variant/30 flex items-center justify-between bg-surface-container-low shrink-0">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary-container text-[22px]">receipt_long</span>
                    <div>
                        <h3 class="font-headline font-bold text-sm text-on-surface">Riwayat Belanja Anda</h3>
                        <p class="text-[10px] text-secondary">Daftar struk belanja online &amp; offline GopellMart</p>
                    </div>
                </div>
                <button type="button" onclick="closeRiwayatModal()" class="text-secondary hover:text-on-surface p-1 rounded-lg">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <!-- List Riwayat -->
            <div class="p-5 overflow-y-auto space-y-3 flex-1">
                <?php if (empty($riwayat_belanja_pembeli)): ?>
                    <div class="py-8 text-center text-secondary">
                        <span class="material-symbols-outlined text-[36px] text-secondary/30 block mb-1">receipt</span>
                        <p class="text-xs font-medium">Belum ada riwayat transaksi belanja.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($riwayat_belanja_pembeli as $riw): ?>
                        <div class="p-3.5 rounded-xl bg-surface-container-low border border-outline-variant/20 flex items-center justify-between gap-3 text-xs">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-on-surface">#TRX-<?= str_pad($riw['id_transaksi'], 4, '0', STR_PAD_LEFT) ?></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-primary-container/10 text-primary">
                                        <?= htmlspecialchars($riw['metode_pembayaran'] ?? 'QRIS') ?>
                                    </span>
                                </div>
                                <span class="text-[11px] text-secondary mt-0.5 block"><?= formatTanggal($riw['tanggal']) ?></span>
                            </div>
                            <div class="text-right">
                                <span class="font-mono font-bold text-sm text-on-surface block"><?= formatRupiah($riw['total_belanja']) ?></span>
                                <a href="nota.php?id=<?= $riw['id_transaksi'] ?>" target="_blank" class="inline-flex items-center gap-1 text-[11px] text-primary hover:underline font-bold mt-1">
                                    <span>Lihat E-Nota</span>
                                    <span class="material-symbols-outlined text-[13px]">open_in_new</span>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="px-5 py-3 bg-surface-container-low border-t border-outline-variant/20 flex items-center justify-end">
                <button type="button" onclick="closeRiwayatModal()" class="px-4 py-2 rounded-xl bg-surface-container hover:bg-surface-container-high text-xs font-bold transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript Keranjang & Filter Belanja Online -->
    <script>
        let cartOnline = {};
        let grandCalculatedTotal = 0;
        let activeKategoriFilter = 'ALL';

        function formatRupiahJs(number) {
            return 'Rp ' + Math.round(number).toLocaleString('id-ID');
        }

        // Tambahkan produk ke keranjang belanja (Memilih barang - tanpa scan)
        function addToCartOnline(id, name, price, stock) {
            if (stock <= 0) {
                alert('Maaf, stok produk ini sedang habis.');
                return;
            }

            if (cartOnline[id]) {
                if (cartOnline[id].jumlah < stock) {
                    cartOnline[id].jumlah += 1;
                } else {
                    alert('Jumlah mencapai batas stok maksimum yang tersedia (' + stock + ').');
                    return;
                }
            } else {
                cartOnline[id] = {
                    id_produk: id,
                    nama_produk: name,
                    harga: price,
                    stok: stock,
                    jumlah: 1
                };
            }
            renderCartOnline();
        }

        function updateQtyOnline(id, delta) {
            if (!cartOnline[id]) return;
            const newQty = cartOnline[id].jumlah + delta;
            if (newQty <= 0) {
                delete cartOnline[id];
            } else if (newQty > cartOnline[id].stok) {
                alert('Jumlah melebihi stok yang tersedia (' + cartOnline[id].stok + ').');
                return;
            } else {
                cartOnline[id].jumlah = newQty;
            }
            renderCartOnline();
        }

        function removeCartItemOnline(id) {
            delete cartOnline[id];
            renderCartOnline();
        }

        function clearCartOnline() {
            cartOnline = {};
            renderCartOnline();
        }

        function renderCartOnline() {
            const container = document.getElementById('cartItemsList');
            const emptyBox = document.getElementById('cartEmptyState');
            const items = Object.values(cartOnline);

            if (items.length === 0) {
                emptyBox.classList.remove('hidden');
                container.innerHTML = '';
            } else {
                emptyBox.classList.add('hidden');
                let html = '';
                items.forEach(item => {
                    const subtotal = item.harga * item.jumlah;
                    html += `
                        <div class="p-2.5 rounded-xl bg-surface-container-low border border-outline-variant/30 flex items-center justify-between gap-2.5 text-xs">
                            <div class="flex-1 min-w-0">
                                <h5 class="font-bold text-on-surface truncate font-headline">${item.nama_produk}</h5>
                                <div class="flex items-center justify-between mt-1 text-[11px] text-secondary">
                                    <span>${formatRupiahJs(item.harga)}</span>
                                    <span class="font-mono font-bold text-on-surface">${formatRupiahJs(subtotal)}</span>
                                </div>
                            </div>
                            <div class="flex items-center bg-surface-container-lowest rounded-lg border border-outline-variant/40 h-7 shrink-0">
                                <button type="button" onclick="updateQtyOnline(${item.id_produk}, -1)" class="w-6 h-full flex items-center justify-center text-secondary hover:text-primary transition font-bold text-xs">&minus;</button>
                                <span class="w-6 text-center font-bold text-xs text-on-surface">${item.jumlah}</span>
                                <button type="button" onclick="updateQtyOnline(${item.id_produk}, 1)" class="w-6 h-full flex items-center justify-center text-secondary hover:text-primary transition font-bold text-xs">+</button>
                            </div>
                            <button type="button" onclick="removeCartItemOnline(${item.id_produk})" class="text-secondary hover:text-error p-1 transition" title="Hapus">
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </button>
                        </div>
                    `;
                });
                container.innerHTML = html;
            }

            // Simpan ke input hidden JSON
            document.getElementById('inputItemsJson').value = JSON.stringify(items);

            calculateTotalOnline();
        }

        function calculateTotalOnline() {
            const items = Object.values(cartOnline);
            let subtotal = 0;
            items.forEach(item => {
                subtotal += item.harga * item.jumlah;
            });

            const pajak = subtotal * 0.11;
            const grandTotal = subtotal + pajak;
            grandCalculatedTotal = grandTotal;

            document.getElementById('txtSubtotalOnline').innerText = formatRupiahJs(subtotal);
            document.getElementById('txtPajakOnline').innerText = formatRupiahJs(pajak);
            document.getElementById('txtGrandTotalOnline').innerText = formatRupiahJs(grandTotal);

            const btn = document.getElementById('btnSubmitOnline');
            if (btn) {
                if (items.length > 0 && grandTotal > 0) {
                    btn.removeAttribute('disabled');
                } else {
                    btn.setAttribute('disabled', 'disabled');
                }
            }
        }

        // Pilihan Metode Pembayaran (Non-Tunai Murni)
        function selectPayMethodOnline(method, btn) {
            document.getElementById('inputMetodePembayaran').value = method;
            document.querySelectorAll('.pay-btn').forEach(b => {
                b.className = 'pay-btn py-2.5 px-3 rounded-xl border border-outline-variant/40 bg-surface-container text-secondary hover:text-on-surface font-medium text-xs flex flex-col items-center justify-center gap-1 transition';
            });
            btn.className = 'pay-btn active py-2.5 px-3 rounded-xl border border-primary-container bg-primary-container/10 text-primary font-bold text-xs flex flex-col items-center justify-center gap-1 transition shadow-2xs';

            const boxQris = document.getElementById('boxQrisPreview');
            const boxTf = document.getElementById('boxTransferPreview');

            if (method === 'QRIS') {
                boxQris.classList.remove('hidden');
                boxTf.classList.add('hidden');
            } else {
                boxQris.classList.add('hidden');
                boxTf.classList.remove('hidden');
            }
        }

        // Filter Live Search & Kategori
        function filterKatalog() {
            applyFiltersOnline();
        }

        function filterKategori(kat, btn) {
            activeKategoriFilter = kat;
            document.querySelectorAll('.kategori-pill').forEach(b => {
                b.className = 'kategori-pill px-3.5 py-1.5 rounded-xl bg-surface-container text-secondary hover:text-on-surface hover:bg-surface-container-high transition font-medium whitespace-nowrap';
            });
            btn.className = 'kategori-pill active px-3.5 py-1.5 rounded-xl bg-primary-container text-white font-bold whitespace-nowrap shadow-xs';
            applyFiltersOnline();
        }

        function applyFiltersOnline() {
            const query = (document.getElementById('katalogSearch').value || '').toLowerCase().trim();
            const cards = document.querySelectorAll('.product-item-card');

            cards.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                const cat = card.getAttribute('data-category') || '';

                const matchQuery = !query || name.includes(query) || cat.toLowerCase().includes(query);
                const matchCat = activeKategoriFilter === 'ALL' || cat === activeKategoriFilter;

                if (matchQuery && matchCat) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Modal Controls
        function openLoginModal() {
            document.getElementById('modalLoginRfid').classList.remove('hidden');
            document.getElementById('inputModalRfid').focus();
        }

        function closeLoginModal() {
            document.getElementById('modalLoginRfid').classList.add('hidden');
        }

        function setDemoRfid(code) {
            document.getElementById('inputModalRfid').value = code;
        }

        function openRiwayatModal() {
            document.getElementById('modalRiwayat').classList.remove('hidden');
        }

        function closeRiwayatModal() {
            document.getElementById('modalRiwayat').classList.add('hidden');
        }
    </script>
</body>
</html>
