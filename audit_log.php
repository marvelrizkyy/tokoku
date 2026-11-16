<?php
/**
 * Audit Log Trigger - Stitch Design System: TokoKu POS Retail Engine
 * Sistem Informasi Penjualan & POS (toko_ku)
 * Menampilkan rekaman mutasi otomatis dari Database Triggers:
 * 1. after_update_pelanggan -> log_perubahan_pelanggan
 * 2. after_insert_pelanggan & after_delete_pelanggan -> tbl_log & log_pelanggan
 */
require_once __DIR__ . '/config/db.php';

$page_title = 'Audit Log Trigger';
$current_page = 'audit_log';

$list_tbl_log = [];
$list_log_perubahan = [];
$list_log_pelanggan = [];
$error_message = null;

if ($pdo) {
    try {
        $stmtLog = $pdo->query("SELECT id_log, tabel_terkait, aktivitas, keterangan, waktu FROM tbl_log ORDER BY id_log DESC");
        $list_tbl_log = $stmtLog->fetchAll();
    } catch (PDOException $e) {
        $list_tbl_log = [];
    }

    try {
        $stmtPerubahan = $pdo->query("SELECT id_log, id_pelanggan, nama_lama, nama_baru, waktu FROM log_perubahan_pelanggan ORDER BY id_log DESC");
        $list_log_perubahan = $stmtPerubahan->fetchAll();
    } catch (PDOException $e) {
        $list_log_perubahan = [];
    }

    try {
        $stmtPelanggan = $pdo->query("SELECT l.id_log, l.aktivitas, l.id_pelanggan, p.nama_pelanggan, l.waktu 
                                      FROM log_pelanggan l 
                                      LEFT JOIN tbl_pelanggan p ON l.id_pelanggan = p.id_pelanggan 
                                      ORDER BY l.id_log DESC");
        $list_log_pelanggan = $stmtPelanggan->fetchAll();
    } catch (PDOException $e) {
        $list_log_pelanggan = [];
    }
} else {
    $error_message = "Koneksi database tidak tersedia.";
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
                <span class="material-symbols-outlined text-primary-container text-[24px]">security</span>
                Audit Log & Database Triggers
            </h1>
            <p class="text-xs text-secondary mt-0.5">Pemantauan mutasi data otomatis oleh Trigger MySQL (INSERT &bull; UPDATE &bull; DELETE)</p>
        </div>
        <div class="flex items-center gap-2.5">
            <button onclick="window.location.reload()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface text-xs font-semibold transition">
                <span class="material-symbols-outlined text-[16px]">rotate_right</span>
                <span>Segarkan</span>
            </button>
            <a href="pelanggan.php" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-primary-container hover:bg-on-primary-fixed-variant text-on-primary text-xs font-bold shadow-sm transition active:scale-95">
                <span class="material-symbols-outlined text-[16px]">edit_note</span>
                <span>Uji Trigger di Pelanggan</span>
            </a>
        </div>
    </header>

    <!-- Page Body -->
    <div class="p-4 sm:p-6 lg:p-8 space-y-6 flex-1">

        <!-- Error Notification -->
        <?php if (!empty($error_message)): ?>
            <div role="alert" class="p-4 rounded-xl bg-error-container text-on-error-container border border-error/20 flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-3">
                    <span class="material-symbols-outlined text-error text-[22px]">warning</span>
                    <p class="text-xs font-semibold"><?= htmlspecialchars($error_message) ?></p>
                </div>
                <button onclick="dismissAlert(this)" class="text-on-error-container/70 hover:text-on-error-container p-1">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Bottom 2-Column Grid (Database Trigger Monitoring matching Stitch) -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">

            <!-- LEFT CARD: Log Perubahan Pelanggan (Trigger AFTER UPDATE) -->
            <div class="w-full bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant/30 flex flex-col justify-between overflow-hidden">
                <div>
                    <div class="p-5 bg-surface-container-low border-b border-outline-variant/20">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-secondary-container flex items-center justify-center text-on-secondary-fixed">
                                    <span class="material-symbols-outlined text-[20px]">manage_history</span>
                                </div>
                                <div>
                                    <h3 class="font-headline font-bold text-sm text-on-surface">
                                        Log Perubahan Pelanggan (Trigger UPDATE)
                                    </h3>
                                    <p class="text-[11px] text-secondary">
                                        Menangkap histori nama lama vs nama baru via Trigger <code class="font-mono bg-white px-1 py-0.2 rounded">log_perubahan_pelanggan</code>
                                    </p>
                                </div>
                            </div>
                            <span class="text-[10px] bg-tertiary-fixed text-on-tertiary-fixed px-2 py-0.5 rounded font-mono font-bold">
                                after_update
                            </span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-surface-container-low/70 text-secondary font-semibold uppercase tracking-wider text-[11px] h-10 border-b border-outline-variant/20">
                                    <th class="py-2.5 px-4 font-semibold">ID Pelanggan</th>
                                    <th class="py-2.5 px-3 font-semibold">Nama Lama</th>
                                    <th class="py-2.5 px-1 font-semibold text-center w-8"></th>
                                    <th class="py-2.5 px-3 font-semibold">Nama Baru</th>
                                    <th class="py-2.5 px-4 font-semibold text-right">Waktu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/20 font-body-sm text-body-sm">
                                <?php if (empty($list_log_perubahan)): ?>
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-secondary text-xs">
                                            Belum ada histori perubahan data pelanggan yang tertangkap trigger.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($list_log_perubahan as $log): ?>
                                        <tr class="hover:bg-surface-container-low/30 transition-colors">
                                            <td class="py-3 px-4 font-mono font-bold text-on-surface">
                                                #CUST-<?= str_pad($log['id_pelanggan'], 3, '0', STR_PAD_LEFT) ?>
                                            </td>
                                            <td class="py-3 px-3">
                                                <span class="inline-block bg-error-container text-on-error-container line-through px-2 py-0.5 rounded text-[11px] font-semibold">
                                                    <?= htmlspecialchars($log['nama_lama']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-1 text-center text-secondary">
                                                <span class="material-symbols-outlined text-[16px]">trending_flat</span>
                                            </td>
                                            <td class="py-3 px-3">
                                                <span class="inline-block bg-tertiary-fixed text-on-tertiary-fixed font-bold px-2 py-0.5 rounded text-[11px]">
                                                    <?= htmlspecialchars($log['nama_baru']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-right text-secondary font-mono text-[11px]">
                                                <?= formatTanggalWaktu($log['waktu']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Drawer -->
                <div class="p-3.5 bg-surface-container-low border-t border-outline-variant/20 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-1.5 font-mono text-[11px] text-secondary">
                        <span class="material-symbols-outlined text-[16px]">code</span>
                        <span>TRIGGER after_update_pelanggan</span>
                    </div>
                    <span class="text-[11px] text-tertiary font-bold flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span> 100% Captured
                    </span>
                </div>
            </div>

            <!-- RIGHT CARD: Real-time System Audit Stream (Trigger INSERT & DELETE) -->
            <div class="w-full bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant/30 flex flex-col justify-between overflow-hidden">
                <div>
                    <div class="p-5 bg-surface-container-low border-b border-outline-variant/20">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed">
                                    <span class="material-symbols-outlined text-[20px]">security_update_good</span>
                                </div>
                                <div>
                                    <h3 class="font-headline font-bold text-sm text-on-surface">
                                        Audit Log Aktivitas (Trigger INSERT & DELETE)
                                    </h3>
                                    <p class="text-[11px] text-secondary">
                                        Feed audit sistem real-time untuk pemantauan integritas data dan keamanan transaksi.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-tertiary-container animate-ping"></span>
                                <span class="text-[10px] text-tertiary font-extrabold uppercase tracking-wider">Live Feed</span>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Stream Feed List -->
                    <div class="divide-y divide-outline-variant/20 px-5 py-2 max-h-[480px] overflow-y-auto">
                        <?php if (empty($list_tbl_log)): ?>
                            <div class="py-8 text-center text-secondary text-xs">
                                Belum ada log aktivitas yang tercatat.
                            </div>
                        <?php else: ?>
                            <?php foreach ($list_tbl_log as $tl): 
                                $act = strtoupper($tl['aktivitas']);
                                $actClass = 'bg-surface-container text-on-surface-variant';
                                if (strpos($act, 'INSERT') !== false || strpos($act, 'TAMBAH') !== false) {
                                    $actClass = 'bg-tertiary-fixed text-on-tertiary-fixed';
                                } elseif (strpos($act, 'DELETE') !== false || strpos($act, 'HAPUS') !== false) {
                                    $actClass = 'bg-error-container text-on-error-container';
                                } elseif (strpos($act, 'UPDATE') !== false || strpos($act, 'UBAH') !== false) {
                                    $actClass = 'bg-secondary-fixed text-on-secondary-fixed';
                                }
                            ?>
                                <div class="py-3 flex items-start justify-between gap-3 hover:bg-surface-container-low/40 rounded-lg px-2 -mx-2 transition-colors">
                                    <div class="flex items-start gap-2.5">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded tracking-wide font-mono <?= $actClass ?>">
                                            <?= htmlspecialchars($tl['aktivitas']) ?>
                                        </span>
                                        <div>
                                            <p class="text-xs text-on-surface leading-tight font-medium">
                                                <?= htmlspecialchars($tl['keterangan'] ?? 'Mutasi data terekam oleh trigger') ?>
                                            </p>
                                            <div class="flex items-center gap-2 mt-1 text-[11px] text-secondary">
                                                <span class="font-mono">tabel: <?= htmlspecialchars($tl['tabel_terkait']) ?></span>
                                                <span>&bull;</span>
                                                <span class="font-mono">#LOG-<?= str_pad($tl['id_log'], 4, '0', STR_PAD_LEFT) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="text-[10px] text-secondary font-mono whitespace-nowrap self-start">
                                        <?= formatTanggalWaktu($tl['waktu']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer Drawer -->
                <div class="p-3.5 bg-surface-container-low border-t border-outline-variant/20 flex items-center justify-between text-xs text-secondary">
                    <span>Tabel terindeks: <code class="font-mono bg-white px-1 py-0.2 rounded font-bold">tbl_log</code></span>
                    <span class="font-bold text-on-surface font-mono"><?= count($list_tbl_log) ?> Entitas Log</span>
                </div>
            </div>

        </div>

    </div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>