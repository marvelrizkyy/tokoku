<?php
/**
 * Dashboard Utama - Stitch Design System: TokoKu POS Retail Engine
 * Sistem Informasi Penjualan & POS (toko_ku)
 */
require_once __DIR__ . '/config/db.php';

// Halaman welcome menjadi pintu utama untuk pengunjung yang belum login
if (empty($_SESSION['user_id'])) {
    header('Location: welcome.php');
    exit;
}

requireRole(['Admin']);

$page_title = 'Main Analytics Dashboard';
$current_page = 'index';

// Inisialisasi variabel metrik
$total_pendapatan = 0;
$total_transaksi = 0;
$total_pelanggan = 0;
$total_stok_menipis = 0;
$rata_rata_belanja = 0;
$produk_menipis_list = [];
$transaksi_terbesar = [];
$pelanggan_tanpa_transaksi = [];
$error_message = null;

if ($pdo) {
    try {
        // 1. Total Pendapatan
        $stmtPendapatan = $pdo->query("SELECT COALESCE(SUM(total_belanja), 0) AS total FROM tbl_transaksi");
        $total_pendapatan = (float) $stmtPendapatan->fetchColumn();

        // 2. Total Transaksi
        $stmtTransaksi = $pdo->query("SELECT COUNT(*) AS total FROM tbl_transaksi");
        $total_transaksi = (int) $stmtTransaksi->fetchColumn();

        // Hitung Rata-rata Belanja
        if ($total_transaksi > 0) {
            $rata_rata_belanja = $total_pendapatan / $total_transaksi;
        }

        // 3. Total Pelanggan menggunakan Stored Procedure: CALL HitungPelanggan(@jumlah)
        try {
            $spStmt = $pdo->query("CALL HitungPelanggan(@jumlah)");
            $spStmt->closeCursor(); // Mencegah Commands out of sync error
            $outStmt = $pdo->query("SELECT @jumlah AS total_pelanggan");
            $total_pelanggan = (int) ($outStmt->fetchColumn() ?? 0);
        } catch (Exception $e) {
            $total_pelanggan = (int) ($pdo->query("SELECT COUNT(*) FROM tbl_pelanggan")->fetchColumn() ?? 0);
        }

        // 4. Produk Stok Menipis (stok < 20)
        $stmtStok = $pdo->query("SELECT COUNT(*) AS total FROM tbl_produk WHERE stok < 20");
        $total_stok_menipis = (int) $stmtStok->fetchColumn();

        // Ambil daftar produk dengan stok menipis
        $stmtStokList = $pdo->query("SELECT p.id_produk, p.nama_produk, p.stok, p.harga, k.nama_kategori 
                                      FROM tbl_produk p 
                                      LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori 
                                      WHERE p.stok < 20 
                                      ORDER BY p.stok ASC 
                                      LIMIT 4");
        $produk_menipis_list = $stmtStokList->fetchAll();

        // 5. Lima Transaksi Terbesar (LIMIT 5)
        $sqlTop5 = "SELECT 
                        t.id_transaksi,
                        t.tanggal,
                        p.nama_pelanggan,
                        p.kota,
                        t.total_belanja
                    FROM tbl_transaksi t
                    JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
                    ORDER BY t.total_belanja DESC
                    LIMIT 5";
        $transaksi_terbesar = $pdo->query($sqlTop5)->fetchAll();

        // 6. Pelanggan Belum Pernah Bertransaksi (Subquery NOT IN)
        $sqlNotIn = "SELECT id_pelanggan, nama_pelanggan, kota 
                     FROM tbl_pelanggan
                     WHERE id_pelanggan NOT IN (
                         SELECT DISTINCT id_pelanggan 
                         FROM tbl_transaksi 
                         WHERE id_pelanggan IS NOT NULL
                     )
                     ORDER BY id_pelanggan ASC";
        $pelanggan_tanpa_transaksi = $pdo->query($sqlNotIn)->fetchAll();

    } catch (PDOException $e) {
        $error_message = $e->getMessage();
    }
} else {
    $error_message = "Koneksi database terputus. Pastikan MySQL di XAMPP telah aktif.";
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Area -->
<main class="flex-1 flex flex-col min-w-0 overflow-y-auto">

    <!-- Topbar Header -->
    <header class="bg-surface-container-lowest border-b border-outline-variant/40 px-6 sm:px-8 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 sticky top-0 z-30 shadow-[0_1px_8px_rgba(0,0,0,0.03)]">
        <div>
            <h1 class="font-headline font-bold text-lg sm:text-xl text-on-surface tracking-tight flex items-center gap-2">
                <span class="material-symbols-outlined text-primary-container text-[24px]">grid_view</span>
                Analytics Dashboard
            </h1>
            <p class="text-xs text-secondary mt-0.5"><?= formatTanggal(date('Y-m-d')) ?> &bull; Data operasional penjualan ritel terkini</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-inverse-surface text-inverse-on-surface text-xs font-semibold">
                <span class="material-symbols-outlined text-[16px] text-tertiary-fixed-dim">terminal</span>
                <span>POS #01</span>
            </div>
            <a href="kasir.php" class="flex items-center gap-1.5 h-9 px-4 rounded-lg bg-primary-container hover:bg-on-primary-fixed-variant text-on-primary text-xs font-bold shadow-sm transition-all active:scale-95">
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span>+ Transaksi Baru</span>
            </a>
        </div>
    </header>

    <!-- Page Body -->
    <div class="p-6 sm:p-8 space-y-6 flex-1 bg-surface">

        <!-- Error Notification Banner if DB Fails -->
        <?php if (!empty($error_message)): ?>
            <div role="alert" class="p-4 rounded-xl bg-error-container text-on-error-container border border-error/20 flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-3">
                    <span class="material-symbols-outlined text-error text-[22px]">error</span>
                    <p class="text-xs font-semibold"><?= htmlspecialchars($error_message) ?></p>
                </div>
                <button onclick="dismissAlert(this)" class="text-on-error-container/70 hover:text-on-error-container p-1">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Top Alert / Notification Banner (Stok Menipis) -->
        <?php if ($total_stok_menipis > 0): ?>
            <div class="w-full bg-error-container/40 border border-error/20 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-error-container text-error flex-shrink-0">
                        <span class="material-symbols-outlined text-[20px] fill-icon">warning</span>
                    </div>
                    <div>
                        <p class="text-xs sm:text-sm text-on-error-container font-semibold tracking-tight">
                            Peringatan Stok Menipis:
                            <span class="font-normal text-on-error-container/90">
                                Ditemukan <strong class="font-bold text-error"><?= $total_stok_menipis ?> produk</strong> dengan kuantitas di bawah batas aman 20 pcs.
                            </span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 self-end sm:self-center">
                    <a href="kasir.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-container-lowest text-error text-xs font-bold hover:bg-error-container/60 transition-colors shadow-xs active:scale-95">
                        <span class="material-symbols-outlined text-[16px]">inventory_2</span>
                        <span>Cek di Kasir</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- KPI Metric Cards Grid (Stitch 4 Cards) -->
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
            
            <!-- Card 1: Total Pendapatan -->
            <div class="relative overflow-hidden rounded-xl bg-primary-container text-on-primary p-6 shadow-sm flex flex-col justify-between group hover:shadow-md transition-shadow">
                <div class="absolute -right-4 -bottom-4 w-28 h-28 bg-white/10 rounded-full blur-xl pointer-events-none"></div>
                <div class="flex items-center justify-between relative z-10">
                    <span class="text-sm font-semibold text-on-primary/90">Total Pendapatan</span>
                    <span class="material-symbols-outlined text-on-primary/80 text-[22px]">payments</span>
                </div>
                <div class="my-3 relative z-10">
                    <span class="font-headline text-2xl lg:text-3xl font-extrabold tracking-tight text-on-primary block">
                        <?= formatRupiah($total_pendapatan) ?>
                    </span>
                </div>
                <div class="flex items-center gap-2 relative z-10">
                    <div class="inline-flex items-center gap-1 bg-white/20 text-on-primary px-2.5 py-0.5 rounded-full text-xs font-semibold backdrop-blur-xs">
                        <span class="material-symbols-outlined text-[14px]">trending_up</span>
                        <span>Akumulasi Omzet Ritel</span>
                    </div>
                </div>
            </div>

            <!-- Card 2: Total Transaksi -->
            <div class="relative overflow-hidden rounded-xl bg-inverse-surface text-inverse-on-surface p-6 shadow-sm flex flex-col justify-between group hover:shadow-md transition-shadow">
                <div class="absolute -right-4 -bottom-4 w-28 h-28 bg-white/5 rounded-full blur-xl pointer-events-none"></div>
                <div class="flex items-center justify-between relative z-10">
                    <span class="text-sm font-semibold text-inverse-on-surface/90">Total Transaksi</span>
                    <span class="material-symbols-outlined text-inverse-on-surface/80 text-[22px]">receipt_long</span>
                </div>
                <div class="my-3 relative z-10">
                    <span class="font-headline text-2xl lg:text-3xl font-extrabold tracking-tight text-inverse-on-surface block">
                        <?= number_format($total_transaksi, 0, ',', '.') ?> Pesanan
                    </span>
                </div>
                <div class="flex items-center gap-2 relative z-10">
                    <span class="inline-flex items-center gap-1 bg-tertiary-fixed-dim/20 text-tertiary-fixed-dim px-2.5 py-0.5 rounded-full text-xs font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-tertiary-container animate-pulse"></span>
                        Faktur Terproses
                    </span>
                </div>
            </div>

            <!-- Card 3: Total Pelanggan (Stored Procedure: CALL HitungPelanggan(@jumlah)) -->
            <div class="relative overflow-hidden rounded-xl bg-tertiary text-on-tertiary p-6 shadow-sm flex flex-col justify-between group hover:shadow-md transition-shadow">
                <div class="absolute -right-4 -bottom-4 w-28 h-28 bg-white/10 rounded-full blur-xl pointer-events-none"></div>
                <div class="flex items-center justify-between relative z-10">
                    <span class="text-sm font-semibold text-on-tertiary/90">Total Pelanggan</span>
                    <span class="material-symbols-outlined text-on-tertiary/80 text-[22px]">people</span>
                </div>
                <div class="my-3 relative z-10">
                    <span class="font-headline text-2xl lg:text-3xl font-extrabold tracking-tight text-on-tertiary block">
                        <?= number_format($total_pelanggan, 0, ',', '.') ?> Terdaftar
                    </span>
                </div>
                <div class="flex items-center gap-2 relative z-10">
                    <span class="inline-flex items-center gap-1 bg-white/20 text-on-tertiary px-2.5 py-0.5 rounded-full text-xs font-semibold backdrop-blur-xs">
                        <span class="material-symbols-outlined text-[13px]">database</span>
                        <span>SP HitungPelanggan</span>
                    </span>
                </div>
            </div>

            <!-- Card 4: Rata-rata Belanja -->
            <div class="relative overflow-hidden rounded-xl bg-secondary text-on-secondary p-6 shadow-sm flex flex-col justify-between group hover:shadow-md transition-shadow">
                <div class="absolute -right-4 -bottom-4 w-28 h-28 bg-white/10 rounded-full blur-xl pointer-events-none"></div>
                <div class="flex items-center justify-between relative z-10">
                    <span class="text-sm font-semibold text-on-secondary/90">Rata-rata Belanja</span>
                    <span class="material-symbols-outlined text-on-secondary/80 text-[22px]">query_stats</span>
                </div>
                <div class="my-3 relative z-10">
                    <span class="font-headline text-2xl lg:text-3xl font-extrabold tracking-tight text-on-secondary block">
                        <?= formatRupiah($rata_rata_belanja) ?>
                    </span>
                </div>
                <div class="flex items-center gap-2 relative z-10">
                    <span class="inline-flex items-center gap-1 bg-white/20 text-on-secondary px-2.5 py-0.5 rounded-full text-xs font-semibold backdrop-blur-xs">
                        <span class="material-symbols-outlined text-[13px]">functions</span>
                        <span>Rata-rata per Transaksi</span>
                    </span>
                </div>
            </div>

        </section>

        <!-- Main Analytics Grid Split (col-span-8 & col-span-4) -->
        <div class="grid grid-cols-12 gap-6 items-start">
            
            <!-- Left Column: 5 Transaksi Nilai Terbesar (col-span-12 xl:col-span-8) -->
            <div class="col-span-12 xl:col-span-8 bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant/30 overflow-hidden flex flex-col">
                <!-- Card Header -->
                <div class="px-6 py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-surface-container-lowest border-b border-outline-variant/30">
                    <div>
                        <h2 class="font-headline text-base font-bold text-on-surface tracking-tight flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary-container text-[22px]">leaderboard</span>
                            5 Transaksi Nilai Terbesar
                        </h2>
                        <p class="text-xs text-secondary mt-0.5">
                            Data transaksi dengan nominal transaksi tertinggi dari database TokoKu
                        </p>
                    </div>
                    <a href="laporan.php" class="inline-flex items-center gap-1 text-xs text-primary font-bold hover:text-primary-container transition-colors self-start sm:self-center">
                        <span>Lihat Semua</span>
                        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    </a>
                </div>

                <!-- Table View -->
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-surface-container-low text-secondary font-semibold uppercase tracking-wider text-[11px] h-10 border-b border-outline-variant/20">
                                <th class="py-2.5 px-6" scope="col">ID Transaksi</th>
                                <th class="py-2.5 px-4" scope="col">Tanggal</th>
                                <th class="py-2.5 px-4" scope="col">Nama Pelanggan</th>
                                <th class="py-2.5 px-4" scope="col">Kota</th>
                                <th class="py-2.5 px-4 text-right" scope="col">Total Belanja</th>
                                <th class="py-2.5 px-6 text-center" scope="col">Status & Nota</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/20">
                            <?php if (empty($transaksi_terbesar)): ?>
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-secondary">
                                        Belum ada data transaksi yang tersimpan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transaksi_terbesar as $tx): ?>
                                    <tr class="hover:bg-surface-container-low/60 transition-colors">
                                        <td class="py-3 px-6 font-mono font-bold text-on-surface whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <span class="w-1.5 h-1.5 rounded-full bg-primary-container"></span>
                                                <span>#TX-<?= str_pad($tx['id_transaksi'], 4, '0', STR_PAD_LEFT) ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-secondary whitespace-nowrap">
                                            <?= formatTanggal($tx['tanggal']) ?>
                                        </td>
                                        <td class="py-3 px-4 font-semibold text-on-surface whitespace-nowrap">
                                            <?= htmlspecialchars($tx['nama_pelanggan']) ?>
                                        </td>
                                        <td class="py-3 px-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-surface-container text-on-surface-variant">
                                                <?= htmlspecialchars($tx['kota']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right font-bold text-tertiary whitespace-nowrap text-sm">
                                            <?= formatRupiah($tx['total_belanja']) ?>
                                        </td>
                                        <td class="py-3 px-6 text-center whitespace-nowrap">
                                            <div class="inline-flex items-center gap-2">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-tertiary-fixed text-on-tertiary-fixed-variant">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span>
                                                    Selesai
                                                </span>
                                                <a href="nota.php?id=<?= $tx['id_transaksi'] ?>" target="_blank" title="Cetak Nota" 
                                                   class="p-1 rounded text-secondary hover:text-primary transition">
                                                    <span class="material-symbols-outlined text-[16px]">receipt</span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footnote Summary -->
                <div class="px-6 py-3.5 bg-surface-container-low flex flex-col sm:flex-row items-start sm:items-center justify-between text-secondary gap-2 mt-auto border-t border-outline-variant/20">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="material-symbols-outlined text-[16px] text-primary">insights</span>
                        <span>5 transaksi di atas berkontribusi signifikan terhadap volume perputaran kasir.</span>
                    </div>
                    <span class="text-xs font-semibold text-on-surface-variant font-mono">
                        Total Top 5: <?= formatRupiah(array_sum(array_column($transaksi_terbesar, 'total_belanja'))) ?>
                    </span>
                </div>
            </div>

            <!-- Right Column: Pelanggan Belum Pernah Transaksi (col-span-12 xl:col-span-4) -->
            <div class="col-span-12 xl:col-span-4 bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant/30 p-6 flex flex-col justify-between">
                <div>
                    <!-- Section Header -->
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="font-headline text-base font-bold text-on-surface tracking-tight flex items-center gap-2">
                                <span class="material-symbols-outlined text-secondary text-[22px]">person_off</span>
                                Pelanggan Belum Transaksi
                            </h3>
                            <p class="text-xs text-secondary mt-1">
                                Subquery SQL: <code class="bg-surface-container px-1 py-0.5 rounded text-[10px]">NOT IN (transaksi)</code>
                            </p>
                        </div>
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-secondary-container text-on-secondary-container text-xs font-bold">
                            <?= count($pelanggan_tanpa_transaksi) ?>
                        </span>
                    </div>

                    <!-- Customer List -->
                    <div class="space-y-3 mt-4">
                        <?php if (empty($pelanggan_tanpa_transaksi)): ?>
                            <p class="py-8 text-center text-secondary text-xs">Semua pelanggan terdaftar telah melakukan transaksi.</p>
                        <?php else: ?>
                            <?php foreach (array_slice($pelanggan_tanpa_transaksi, 0, 4) as $p): 
                                $initials = strtoupper(substr($p['nama_pelanggan'], 0, 2));
                            ?>
                                <div class="flex items-center justify-between p-3 rounded-lg bg-surface-container-low hover:bg-surface-container transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-primary-fixed text-on-primary-fixed flex items-center justify-center font-bold text-xs font-headline">
                                            <?= $initials ?>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-xs font-bold text-on-surface leading-tight"><?= htmlspecialchars($p['nama_pelanggan']) ?></span>
                                            <span class="text-[11px] text-secondary mt-0.5"><?= htmlspecialchars($p['kota']) ?> &bull; #CUST-<?= str_pad($p['id_pelanggan'], 3, '0', STR_PAD_LEFT) ?></span>
                                        </div>
                                    </div>
                                    <a href="kasir.php?pelanggan_id=<?= urlencode($p['id_pelanggan']) ?>" class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-surface-container-lowest text-primary hover:bg-primary-container hover:text-white transition shadow-xs">
                                        + Kasir
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="pt-6 mt-6 border-t border-outline-variant/20">
                    <a href="pelanggan.php" class="w-full flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-tertiary hover:bg-on-tertiary-fixed-variant text-on-tertiary text-xs font-bold shadow-sm transition-all active:scale-[0.98]">
                        <span class="material-symbols-outlined text-[18px]">group</span>
                        <span>Kelola Semua Pelanggan</span>
                    </a>
                </div>
            </div>

        </div>

    </div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>