<?php
/**
 * Laporan Penjualan & Multi-JOIN - Stitch Design System: TokoKu POS Retail Engine
 * Sistem Informasi Penjualan & POS (toko_ku)
 * Menggunakan Database View view_transaksi_pelanggan & Multiple INNER JOIN 3-4 Tabel
 */
require_once __DIR__ . '/config/db.php';
requireRole(['Admin']);

$page_title = 'Laporan Multi-JOIN & Rincian Transaksi';
$current_page = 'laporan';

$ringkasan_transaksi = [];
$rincian_item = [];
$error_message = null;

// Filter pencarian
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_tanggal_mulai = isset($_GET['tgl_mulai']) ? trim($_GET['tgl_mulai']) : '';
$filter_tanggal_selesai = isset($_GET['tgl_selesai']) ? trim($_GET['tgl_selesai']) : '';

// Statistik
$total_omset_view = 0;
$total_transaksi_view = 0;
$total_item_terjual = 0;
$akumulasi_subtotal_join = 0;

if ($pdo) {
    // 1. Multiple INNER JOIN (3-4 Tabel): Rincian Item Terjual
    $sqlJoin = "SELECT 
                    t.id_transaksi,
                    t.tanggal,
                    p.nama_pelanggan,
                    p.kota,
                    d.id_detail,
                    d.nama_produk,
                    d.jumlah,
                    d.harga,
                    (d.jumlah * d.harga) AS subtotal
                FROM tbl_pelanggan p
                INNER JOIN tbl_transaksi t ON p.id_pelanggan = t.id_pelanggan
                INNER JOIN tbl_detail_transaksi d ON t.id_transaksi = d.id_transaksi
                WHERE 1=1";
    $paramsJoin = [];

    if (!empty($search_keyword)) {
        $sqlJoin .= " AND (p.nama_pelanggan LIKE :kw OR d.nama_produk LIKE :kw OR p.kota LIKE :kw)";
        $paramsJoin[':kw'] = "%{$search_keyword}%";
    }
    if (!empty($filter_tanggal_mulai)) {
        $sqlJoin .= " AND t.tanggal >= :tgl_mulai";
        $paramsJoin[':tgl_mulai'] = $filter_tanggal_mulai;
    }
    if (!empty($filter_tanggal_selesai)) {
        $sqlJoin .= " AND t.tanggal <= :tgl_selesai";
        $paramsJoin[':tgl_selesai'] = $filter_tanggal_selesai;
    }

    $sqlJoin .= " ORDER BY t.id_transaksi DESC, d.id_detail ASC";

    try {
        $stmtJoin = $pdo->prepare($sqlJoin);
        $stmtJoin->execute($paramsJoin);
        $rincian_item = $stmtJoin->fetchAll();

        foreach ($rincian_item as $item) {
            $total_item_terjual += (int)$item['jumlah'];
            $akumulasi_subtotal_join += (float)$item['subtotal'];
        }
    } catch (PDOException $e) {
        $rincian_item = [];
    }

    // 2. Ringkasan dari View view_transaksi_pelanggan
    try {
        $stmtView = $pdo->query("SELECT * FROM view_transaksi_pelanggan ORDER BY id_transaksi DESC");
        $ringkasan_transaksi = $stmtView->fetchAll();
        $total_transaksi_view = count($ringkasan_transaksi);
        foreach ($ringkasan_transaksi as $tx) {
            $total_omset_view += (float)$tx['total_belanja'];
        }
    } catch (Exception $e) {
        $ringkasan_transaksi = [];
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Area -->
<main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-surface">

    <!-- Topbar Header -->
    <header class="bg-surface-container-lowest border-b border-outline-variant/40 px-6 sm:px-8 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 sticky top-0 z-30 shadow-[0_1px_8px_rgba(0,0,0,0.03)]">
        <div>
            <h1 class="font-headline font-bold text-lg sm:text-xl text-on-surface tracking-tight flex items-center gap-2">
                <span class="material-symbols-outlined text-primary-container text-[24px]">analytics</span>
                Laporan Multi-JOIN Transaksi
            </h1>
            <p class="text-xs text-secondary mt-0.5">Sintaks SQL Multi-Tabel (Pelanggan &bull; Transaksi &bull; Detail &bull; Produk)</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="audit_log.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface text-xs font-semibold transition">
                <span class="material-symbols-outlined text-[16px] text-tertiary">security</span>
                <span>Audit Log Trigger</span>
            </a>
            <a href="kasir.php" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-primary-container hover:bg-on-primary-fixed-variant text-on-primary text-xs font-bold shadow-sm transition active:scale-95">
                <span class="material-symbols-outlined text-[16px]">add</span>
                <span>+ Kasir Baru</span>
            </a>
        </div>
    </header>

    <!-- Page Body -->
    <div class="p-4 sm:p-6 lg:p-8 space-y-6 flex-1">

        <!-- Filter Card -->
        <div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm border border-outline-variant/30">
            <form method="GET" action="laporan.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end text-xs">
                <div>
                    <label class="block font-bold text-on-surface mb-1">Cari Pelanggan / Produk / Kota</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-2.5 text-secondary text-[18px]">search</span>
                        <input type="text" name="search" value="<?= htmlspecialchars($search_keyword) ?>" 
                               placeholder="Nama atau barang..." 
                               class="w-full h-10 pl-9 pr-3 rounded-lg bg-surface text-on-surface border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container/30">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-on-surface mb-1">Tanggal Mulai</label>
                    <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($filter_tanggal_mulai) ?>" 
                           class="w-full h-10 px-3 rounded-lg bg-surface text-on-surface border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container/30">
                </div>

                <div>
                    <label class="block font-bold text-on-surface mb-1">Tanggal Selesai</label>
                    <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($filter_tanggal_selesai) ?>" 
                           class="w-full h-10 px-3 rounded-lg bg-surface text-on-surface border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container/30">
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 h-10 rounded-lg bg-primary-container hover:bg-on-primary-fixed-variant text-on-primary font-bold shadow-xs transition flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px]">filter_alt</span>
                        <span>Filter Data</span>
                    </button>
                    <a href="laporan.php" class="h-10 px-3 rounded-lg bg-surface-container hover:bg-surface-container-high text-secondary hover:text-on-surface flex items-center justify-center transition" title="Reset">
                        <span class="material-symbols-outlined text-[18px]">restart_alt</span>
                    </a>
                </div>
            </form>
        </div>

        <!-- Main Multi-JOIN Report Table Card -->
        <div class="bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant/30 overflow-hidden flex flex-col">
            
            <div class="px-6 py-4 bg-surface-container-low border-b border-outline-variant/30 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="font-headline font-bold text-sm text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary-container text-[20px]">table_rows</span>
                        Rincian Item Penjualan (Multi-JOIN 4 Tabel)
                    </h2>
                    <p class="text-[11px] text-secondary font-mono mt-0.5">
                        tbl_pelanggan &bull; tbl_transaksi &bull; tbl_detail_transaksi &bull; tbl_produk
                    </p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-tertiary-fixed text-on-tertiary-fixed">
                    <?= count($rincian_item) ?> Baris Record
                </span>
            </div>

            <!-- Table View -->
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-surface-container-low/70 text-secondary font-semibold uppercase tracking-wider text-[11px] h-10 border-b border-outline-variant/20">
                            <th class="py-2.5 px-5">ID Transaksi</th>
                            <th class="py-2.5 px-4">Tanggal</th>
                            <th class="py-2.5 px-4">Nama Pelanggan</th>
                            <th class="py-2.5 px-4">Nama Produk</th>
                            <th class="py-2.5 px-4 text-right">Qty</th>
                            <th class="py-2.5 px-4 text-right">Harga Satuan</th>
                            <th class="py-2.5 px-5 text-right">Subtotal Item</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <?php if (empty($rincian_item)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-secondary">
                                    Tidak ada data penjualan yang sesuai dengan kriteria filter.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rincian_item as $row): 
                                $initials = strtoupper(substr($row['nama_pelanggan'], 0, 2));
                            ?>
                                <tr class="hover:bg-surface-container-low/40 transition-colors">
                                    <td class="py-3 px-5 font-mono font-bold text-on-surface whitespace-nowrap">
                                        <span class="bg-surface-container px-2 py-0.5 rounded text-[11px]">
                                            #TX-<?= str_pad($row['id_transaksi'], 4, '0', STR_PAD_LEFT) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-secondary whitespace-nowrap">
                                        <?= formatTanggal($row['tanggal']) ?>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 h-6 rounded-full bg-secondary-fixed flex items-center justify-center text-on-secondary-fixed text-[10px] font-bold font-headline">
                                                <?= $initials ?>
                                            </span>
                                            <span class="font-bold text-on-surface"><?= htmlspecialchars($row['nama_pelanggan']) ?></span>
                                            <span class="text-[10px] text-secondary">(<?= htmlspecialchars($row['kota']) ?>)</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-on-surface">
                                        <?= htmlspecialchars($row['nama_produk']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-right font-bold text-on-surface">
                                        <?= number_format($row['jumlah'], 0, ',', '.') ?> unit
                                    </td>
                                    <td class="py-3 px-4 text-right text-secondary font-mono">
                                        <?= formatRupiah($row['harga']) ?>
                                    </td>
                                    <td class="py-3 px-5 text-right font-mono font-bold text-on-surface text-sm">
                                        <?= formatRupiah($row['subtotal']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table Summary Aggregate Footer (Stitch Style) -->
            <div class="px-6 py-4 bg-surface-container-low flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-t border-outline-variant/30">
                <div class="flex items-center gap-3 text-xs text-secondary">
                    <span>Menampilkan <strong><?= count($rincian_item) ?></strong> entitas JOIN record</span>
                    <span class="w-1 h-1 rounded-full bg-secondary/40"></span>
                    <span class="text-tertiary font-semibold flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-tertiary-container"></span>
                        Status Buffer: Terindeks
                    </span>
                </div>
                <div class="flex items-center gap-6 text-xs">
                    <div class="flex items-baseline gap-1.5">
                        <span class="uppercase tracking-wider text-secondary font-semibold text-[10px]">Total Item Terjual:</span>
                        <span class="font-headline font-bold text-sm text-on-surface"><?= number_format($total_item_terjual, 0, ',', '.') ?> pcs</span>
                    </div>
                    <div class="flex items-baseline gap-1.5">
                        <span class="uppercase tracking-wider text-secondary font-semibold text-[10px]">Akumulasi Subtotal:</span>
                        <span class="font-headline font-extrabold text-base text-primary"><?= formatRupiah($akumulasi_subtotal_join) ?></span>
                    </div>
                </div>
            </div>
        </div>

    </div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>