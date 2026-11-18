<?php
/**
 * Riwayat Pembelian & Transaksi Kasir
 * Khusus Kasir & Admin TokoKu POS Retail Engine
 */
require_once __DIR__ . '/config/db.php';
requireRole(['Kasir', 'Admin']);

$current_page = 'riwayat';
$page_title = 'Riwayat Transaksi Kasir';

// Tangkap filter
$filter_search = trim($_GET['search'] ?? '');
$filter_tanggal = trim($_GET['tanggal'] ?? date('Y-m-d'));
$filter_metode = trim($_GET['metode'] ?? '');

// Statistik Hari Ini
$stat_today_count = 0;
$stat_today_total = 0;
$stat_tunai_total = 0;
$stat_nontunai_total = 0;

try {
    $today = date('Y-m-d');
    $stmtS = $pdo->prepare("
        SELECT 
            COUNT(id_transaksi) AS jml,
            COALESCE(SUM(total_belanja), 0) AS total,
            COALESCE(SUM(CASE WHEN LOWER(metode_pembayaran) = 'tunai' THEN total_belanja ELSE 0 END), 0) AS total_tunai,
            COALESCE(SUM(CASE WHEN LOWER(metode_pembayaran) != 'tunai' THEN total_belanja ELSE 0 END), 0) AS total_nontunai
        FROM tbl_transaksi
        WHERE DATE(tanggal) = ?
    ");
    $stmtS->execute([$today]);
    $resS = $stmtS->fetch();
    if ($resS) {
        $stat_today_count     = (int)$resS['jml'];
        $stat_today_total     = (float)$resS['total'];
        $stat_tunai_total     = (float)$resS['total_tunai'];
        $stat_nontunai_total  = (float)$resS['total_nontunai'];
    }
} catch (Exception $e) {}

// Query Daftar Transaksi
$transaksi_list = [];
try {
    $sql = "
        SELECT 
            t.id_transaksi,
            t.tanggal,
            t.total_belanja,
            COALESCE(t.metode_pembayaran, 'Tunai') AS metode_pembayaran,
            COALESCE(t.bayar_nominal, t.total_belanja) AS bayar_nominal,
            COALESCE(t.kembalian_nominal, 0) AS kembalian_nominal,
            p.id_pelanggan,
            p.nama_pelanggan,
            p.rfid_uid,
            p.kota
        FROM tbl_transaksi t
        LEFT JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
        WHERE 1=1
    ";
    $params = [];

    if (!empty($filter_tanggal)) {
        $sql .= " AND DATE(t.tanggal) = ? ";
        $params[] = $filter_tanggal;
    }

    if (!empty($filter_metode)) {
        $sql .= " AND LOWER(t.metode_pembayaran) = ? ";
        $params[] = strtolower($filter_metode);
    }

    if (!empty($filter_search)) {
        $sql .= " AND (p.nama_pelanggan LIKE ? OR p.rfid_uid LIKE ? OR CAST(t.id_transaksi AS CHAR) = ?) ";
        $params[] = "%{$filter_search}%";
        $params[] = "%{$filter_search}%";
        $params[] = $filter_search;
    }

    $sql .= " ORDER BY t.id_transaksi DESC LIMIT 100 ";

    $stmtT = $pdo->prepare($sql);
    $stmtT->execute($params);
    $transaksi_list = $stmtT->fetchAll();
    $stmtT->closeCursor();

} catch (Exception $e) {
    $error_message = "Gagal memuat data transaksi: " . $e->getMessage();
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Canvas -->
<main class="flex-1 flex flex-col min-w-0 overflow-y-auto">

    <!-- Topbar Header -->
    <header class="bg-surface-container-lowest border-b border-outline-variant/40 px-6 sm:px-8 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 sticky top-0 z-30 shadow-2xs">
        <div>
            <h1 class="font-headline font-bold text-lg sm:text-xl text-on-surface tracking-tight flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[24px]">receipt_long</span>
                Riwayat Pembelian &amp; Transaksi Kasir
            </h1>
            <p class="text-xs text-secondary mt-0.5">Pantau daftar nota penjualan dan cetak ulang struk kasir.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="kasir.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-primary-container hover:bg-on-primary-fixed-variant text-white text-xs font-bold shadow-2xs transition active:scale-95">
                <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span>
                <span>Buka Kasir Baru</span>
            </a>
        </div>
    </header>

    <!-- Page Body -->
    <div class="p-6 sm:p-8 space-y-6 flex-1 bg-surface">

        <!-- 3 KPI Cards Ringkasan Kasir Hari Ini -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            
            <div class="p-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 flex items-center gap-4 shadow-2xs">
                <div class="w-12 h-12 rounded-xl bg-primary-container/10 text-primary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[26px]">receipt</span>
                </div>
                <div>
                    <span class="text-xs text-secondary font-medium">Transaksi Hari Ini</span>
                    <h3 class="font-headline font-extrabold text-xl text-on-surface mt-0.5"><?= $stat_today_count ?> Transaksi</h3>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 flex items-center gap-4 shadow-2xs">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[26px]">payments</span>
                </div>
                <div>
                    <span class="text-xs text-secondary font-medium">Total Omset Hari Ini</span>
                    <h3 class="font-headline font-extrabold text-xl text-emerald-700 mt-0.5"><?= formatRupiah($stat_today_total) ?></h3>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 flex items-center gap-4 shadow-2xs">
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[26px]">qr_code_2</span>
                </div>
                <div>
                    <span class="text-xs text-secondary font-medium">Non-Tunai / QRIS</span>
                    <h3 class="font-headline font-extrabold text-xl text-blue-700 mt-0.5"><?= formatRupiah($stat_nontunai_total) ?></h3>
                </div>
            </div>

        </div>

        <!-- Filter Bar -->
        <div class="p-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 shadow-2xs">
            <form method="GET" action="riwayat.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                
                <!-- Search Input -->
                <div class="sm:col-span-5 relative">
                    <input type="text" name="search" value="<?= htmlspecialchars($filter_search) ?>"
                           placeholder="Cari ID transaksi, nama member, atau nomor RFID..."
                           class="w-full h-10 pl-9 pr-4 text-xs font-medium rounded-lg border border-outline-variant/40 bg-surface-container-lowest text-on-surface focus:outline-none focus:ring-2 focus:ring-primary-container">
                    <span class="material-symbols-outlined absolute left-2.5 top-2.5 text-secondary text-[18px]">search</span>
                </div>

                <!-- Tanggal -->
                <div class="sm:col-span-3">
                    <input type="date" name="tanggal" value="<?= htmlspecialchars($filter_tanggal) ?>"
                           class="w-full h-10 px-3 text-xs font-medium rounded-lg border border-outline-variant/40 bg-surface-container-lowest text-on-surface focus:outline-none focus:ring-2 focus:ring-primary-container">
                </div>

                <!-- Metode Pembayaran -->
                <div class="sm:col-span-2">
                    <select name="metode" class="w-full h-10 px-3 text-xs font-medium rounded-lg border border-outline-variant/40 bg-surface-container-lowest text-on-surface focus:outline-none focus:ring-2 focus:ring-primary-container">
                        <option value="">Semua Metode</option>
                        <option value="Tunai" <?= $filter_metode === 'Tunai' ? 'selected' : '' ?>>Tunai</option>
                        <option value="QRIS" <?= $filter_metode === 'QRIS' ? 'selected' : '' ?>>QRIS</option>
                        <option value="Transfer" <?= $filter_metode === 'Transfer' ? 'selected' : '' ?>>Transfer Bank</option>
                    </select>
                </div>

                <!-- Tombol Filter & Reset -->
                <div class="sm:col-span-2 flex items-center gap-2">
                    <button type="submit" class="flex-1 h-10 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-headline text-xs font-bold transition flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">filter_list</span>
                        <span>Filter</span>
                    </button>
                    <?php if (!empty($filter_search) || !empty($filter_metode) || $filter_tanggal !== date('Y-m-d')): ?>
                        <a href="riwayat.php" class="h-10 px-3 rounded-lg bg-surface-container hover:bg-surface-container-high text-secondary flex items-center justify-center transition" title="Reset Filter">
                            <span class="material-symbols-outlined text-[18px]">restart_alt</span>
                        </a>
                    <?php endif; ?>
                </div>

            </form>
        </div>

        <!-- Tabel Transaksi Kasir -->
        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 shadow-2xs overflow-hidden">
            <div class="px-5 py-4 border-b border-outline-variant/20 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[20px]">receipt</span>
                    <h3 class="font-headline font-bold text-sm text-on-surface">Daftar Transaksi Kasir</h3>
                </div>
                <span class="text-xs text-secondary font-medium">Ditemukan <?= count($transaksi_list) ?> transaksi</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-surface-container-low text-secondary border-b border-outline-variant/30">
                            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[10px]">ID Transaksi</th>
                            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[10px]">Waktu</th>
                            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[10px]">Pelanggan / Member</th>
                            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[10px]">Metode Bayar</th>
                            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[10px] text-right">Total Belanja</th>
                            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[10px] text-center">Aksi Kasir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20 text-on-surface">
                        <?php if (empty($transaksi_list)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-secondary">
                                    <span class="material-symbols-outlined text-[36px] text-secondary/40 block mb-2">search_off</span>
                                    <p class="font-semibold text-xs">Tidak ada riwayat transaksi yang cocok dengan filter.</p>
                                    <p class="text-[11px] text-secondary/70 mt-0.5">Ubah tanggal atau kata kunci pencarian di atas.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transaksi_list as $t): ?>
                                <tr class="hover:bg-surface-container-low/50 transition">
                                    
                                    <!-- ID TRX -->
                                    <td class="py-3 px-4 font-mono font-bold text-primary">
                                        #TRX-<?= str_pad($t['id_transaksi'], 4, '0', STR_PAD_LEFT) ?>
                                    </td>

                                    <!-- Tanggal -->
                                    <td class="py-3 px-4">
                                        <span class="font-medium block text-on-surface"><?= date('d M Y', strtotime($t['tanggal'])) ?></span>
                                        <span class="text-[10px] text-secondary font-mono"><?= date('H:i:s', strtotime($t['tanggal'])) ?> WIB</span>
                                    </td>

                                    <!-- Pelanggan / Member RFID -->
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-surface-container flex items-center justify-center font-bold text-xs text-primary font-headline shrink-0">
                                                <?= strtoupper(substr($t['nama_pelanggan'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <div>
                                                <span class="font-bold text-on-surface block"><?= htmlspecialchars($t['nama_pelanggan'] ?? 'Umum') ?></span>
                                                <?php if (!empty($t['rfid_uid'])): ?>
                                                    <span class="font-mono text-[10px] font-bold text-teal-700 bg-teal-50 px-1.5 py-0.2 rounded border border-teal-200">
                                                        <?= htmlspecialchars($t['rfid_uid']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-[10px] text-secondary">Non-Member</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Metode Pembayaran -->
                                    <td class="py-3 px-4">
                                        <?php 
                                            $m = strtolower($t['metode_pembayaran']);
                                            if ($m === 'qris') {
                                                echo '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200"><span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>QRIS</span>';
                                            } elseif ($m === 'transfer' || strpos($m, 'transfer') !== false) {
                                                echo '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200"><span class="w-1.5 h-1.5 rounded-full bg-purple-600"></span>Transfer</span>';
                                            } else {
                                                echo '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>Tunai</span>';
                                            }
                                        ?>
                                    </td>

                                    <!-- Total Belanja -->
                                    <td class="py-3 px-4 text-right font-headline font-bold text-on-surface text-sm">
                                        <?= formatRupiah($t['total_belanja']) ?>
                                    </td>

                                    <!-- Aksi Cetak Ulang Nota -->
                                    <td class="py-3 px-4 text-center">
                                        <a href="nota.php?id=<?= $t['id_transaksi'] ?>" target="_blank"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface font-semibold text-xs border border-outline-variant/30 transition shadow-2xs"
                                           title="Cetak Ulang Struk Kasir">
                                            <span class="material-symbols-outlined text-[16px] text-primary">print</span>
                                            <span>Cetak Nota</span>
                                        </a>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
