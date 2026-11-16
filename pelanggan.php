<?php
/**
 * Manajemen Pelanggan - Stitch Design System: TokoKu POS Retail Engine
 * Sistem Informasi Penjualan & POS (toko_ku)
 * Menggunakan SP TambahPelanggan, View view_total_belanja & SF KategoriBelanja
 */
require_once __DIR__ . '/config/db.php';

$page_title = 'Manajemen Pelanggan & Loyalitas';
$current_page = 'pelanggan';

$success_message = null;
$error_message = null;
$edit_data = null;

// Handle Form POST Actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. TAMBAH PELANGGAN (CALL TambahPelanggan(nama, kota))
    if ($action === 'tambah_pelanggan') {
        $nama = sanitize($_POST['nama_pelanggan'] ?? '');
        $kota = sanitize($_POST['kota'] ?? '');

        if (empty($nama) || empty($kota)) {
            $error_message = "Nama Pelanggan dan Kota wajib diisi.";
        } else {
            try {
                $stmt = $pdo->prepare("CALL TambahPelanggan(?, ?)");
                $stmt->execute([$nama, $kota]);
                $stmt->closeCursor();

                $success_message = "Pelanggan '{$nama}' berhasil ditambahkan ke database TokoKu.";
            } catch (PDOException $e) {
                $error_message = "Gagal menambah pelanggan: " . $e->getMessage();
            }
        }
    }

    // 2. UPDATE PELANGGAN (memicu trigger after_update_pelanggan)
    elseif ($action === 'update_pelanggan') {
        $id_pelanggan = (int)($_POST['id_pelanggan'] ?? 0);
        $nama_baru    = sanitize($_POST['nama_pelanggan'] ?? '');
        $kota_baru    = sanitize($_POST['kota'] ?? '');

        if ($id_pelanggan <= 0 || empty($nama_baru) || empty($kota_baru)) {
            $error_message = "Data perubahan tidak valid.";
        } else {
            try {
                $stmtUpdate = $pdo->prepare("UPDATE tbl_pelanggan SET nama_pelanggan = ?, kota = ? WHERE id_pelanggan = ?");
                $stmtUpdate->execute([$nama_baru, $kota_baru, $id_pelanggan]);

                $success_message = "Data pelanggan #CUST-{$id_pelanggan} berhasil diperbarui (Audit Log terpicu).";
            } catch (PDOException $e) {
                $error_message = "Gagal memperbarui pelanggan: " . $e->getMessage();
            }
        }
    }

    // 3. HAPUS PELANGGAN (memicu trigger after_delete_pelanggan & Foreign Key error handling)
    elseif ($action === 'delete_pelanggan') {
        $id_pelanggan = (int)($_POST['id_pelanggan'] ?? 0);
        $nama_pelanggan = sanitize($_POST['nama_pelanggan'] ?? 'Pelanggan');

        if ($id_pelanggan <= 0) {
            $error_message = "ID Pelanggan tidak valid.";
        } else {
            try {
                $stmtDelete = $pdo->prepare("DELETE FROM tbl_pelanggan WHERE id_pelanggan = ?");
                $stmtDelete->execute([$id_pelanggan]);

                $success_message = "Pelanggan '{$nama_pelanggan}' berhasil dihapus.";
            } catch (PDOException $e) {
                $isForeignKeyError = ($e->getCode() === '23000' || 
                                      strpos($e->getMessage(), '1451') !== false || 
                                      strpos($e->getMessage(), 'foreign key constraint fails') !== false);

                if ($isForeignKeyError) {
                    $error_message = "Pelanggan '{$nama_pelanggan}' tidak dapat dihapus karena memiliki riwayat transaksi aktif di sistem.";
                } else {
                    $error_message = "Gagal menghapus pelanggan: " . $e->getMessage();
                }
            }
        }
    }
}

// Cek mode edit via query parameter (?edit=ID)
if (isset($_GET['edit']) && (int)$_GET['edit'] > 0 && $pdo) {
    $edit_id = (int)$_GET['edit'];
    try {
        $spCari = $pdo->prepare("CALL CariPelanggan(?)");
        $spCari->execute([$edit_id]);
        $edit_data = $spCari->fetch();
        $spCari->closeCursor();
    } catch (PDOException $e) {
        $error_message = "Gagal memuat data pelanggan: " . $e->getMessage();
    }
}

// Ambil Daftar Pelanggan dari View: view_total_belanja & SF KategoriBelanja
$daftar_pelanggan = [];
$search_keyword = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($pdo) {
    try {
        $sqlList = "SELECT 
                        id_pelanggan,
                        nama_pelanggan,
                        kota,
                        total_transaksi,
                        total_belanja,
                        KategoriBelanja(total_belanja) AS kategori
                    FROM view_total_belanja
                    WHERE 1=1";
        $paramsList = [];

        if (!empty($search_keyword)) {
            $sqlList .= " AND (nama_pelanggan LIKE :kw OR kota LIKE :kw)";
            $paramsList[':kw'] = "%{$search_keyword}%";
        }

        $sqlList .= " ORDER BY id_pelanggan ASC";

        $stmtList = $pdo->prepare($sqlList);
        $stmtList->execute($paramsList);
        $daftar_pelanggan = $stmtList->fetchAll();

    } catch (PDOException $e) {
        // Fallback auto-healing
        try {
            $pdo->exec("
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
            ");
            $pdo->exec("
                DROP FUNCTION IF EXISTS KategoriBelanja;
                CREATE FUNCTION KategoriBelanja(total DECIMAL(12,2))
                RETURNS VARCHAR(30) DETERMINISTIC NO SQL
                BEGIN
                    RETURN CASE
                        WHEN total >= 1000000 THEN 'Pelanggan Gold'
                        WHEN total >= 500000 THEN 'Pelanggan Silver'
                        ELSE 'Pelanggan Reguler'
                    END;
                END;
            ");
            $stmtList = $pdo->prepare($sqlList);
            $stmtList->execute($paramsList);
            $daftar_pelanggan = $stmtList->fetchAll();
        } catch (Exception $ex) {
            $sqlFallback = "
                SELECT 
                    p.id_pelanggan,
                    p.nama_pelanggan,
                    p.kota,
                    COUNT(t.id_transaksi) AS total_transaksi,
                    COALESCE(SUM(t.total_belanja), 0) AS total_belanja,
                    CASE 
                        WHEN COALESCE(SUM(t.total_belanja), 0) >= 1000000 THEN 'Pelanggan Gold'
                        WHEN COALESCE(SUM(t.total_belanja), 0) >= 500000 THEN 'Pelanggan Silver'
                        ELSE 'Pelanggan Reguler'
                    END AS kategori
                FROM tbl_pelanggan p
                LEFT JOIN tbl_transaksi t ON p.id_pelanggan = t.id_pelanggan
                GROUP BY p.id_pelanggan, p.nama_pelanggan, p.kota
                ORDER BY p.id_pelanggan ASC
            ";
            $daftar_pelanggan = $pdo->query($sqlFallback)->fetchAll();
        }
    }
}

// Hitung Statistik Tier
$countGold = 0;
$countSilver = 0;
$countReguler = 0;
foreach ($daftar_pelanggan as $dp) {
    if (strpos($dp['kategori'], 'Gold') !== false) $countGold++;
    elseif (strpos($dp['kategori'], 'Silver') !== false) $countSilver++;
    else $countReguler++;
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
                <span class="material-symbols-outlined text-primary-container text-[24px]">group</span>
                Manajemen Pelanggan & Loyalitas
            </h1>
            <p class="text-xs text-secondary mt-0.5">Kelola data master pembeli & klasifikasi tier loyalitas otomatis</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-surface-container-low border border-outline-variant/30 text-on-surface">
                Total: <?= count($daftar_pelanggan) ?> Pelanggan
            </span>
        </div>
    </header>

    <!-- Page Body -->
    <div class="p-4 sm:p-6 lg:p-8 space-y-6 flex-1">

        <!-- Notification Alerts -->
        <?php if (!empty($success_message)): ?>
            <div role="alert" class="p-4 rounded-xl bg-tertiary-fixed text-on-tertiary-fixed-variant border border-tertiary/20 flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-3">
                    <span class="material-symbols-outlined text-tertiary text-[22px]">check_circle</span>
                    <p class="text-xs font-semibold"><?= htmlspecialchars($success_message) ?></p>
                </div>
                <button onclick="dismissAlert(this)" class="text-on-tertiary-fixed-variant/70 hover:text-on-tertiary-fixed-variant p-1">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        <?php endif; ?>

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

        <!-- Split Grid (Left Form & Right Table) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- LEFT CARD: Form Tambah/Edit Pelanggan (col-span-12 lg:col-span-4) -->
            <div class="lg:col-span-4 bg-surface-container-lowest rounded-xl p-5 shadow-sm border border-outline-variant/30 space-y-5">
                
                <div class="flex items-center justify-between pb-3 bg-surface-container-low -mx-5 -mt-5 p-5 rounded-t-xl border-b border-outline-variant/20">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-primary-container text-on-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]"><?= $edit_data ? 'edit' : 'person_add' ?></span>
                        </div>
                        <div>
                            <h2 class="font-headline text-sm font-bold text-on-surface"><?= $edit_data ? 'Edit Data Pelanggan' : 'Tambah Pelanggan Baru' ?></h2>
                            <p class="text-[11px] text-secondary"><?= $edit_data ? 'Perbarui identitas profil' : 'Input data master pelanggan' ?></p>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded <?= $edit_data ? 'bg-amber-100 text-amber-800' : 'bg-surface-container-highest text-on-surface-variant' ?>">
                        <?= $edit_data ? 'Mode Edit' : 'SP Tambah' ?>
                    </span>
                </div>

                <form method="POST" action="pelanggan.php" class="space-y-4">
                    <input type="hidden" name="action" value="<?= $edit_data ? 'update_pelanggan' : 'tambah_pelanggan' ?>">
                    <?php if ($edit_data): ?>
                        <input type="hidden" name="id_pelanggan" value="<?= (int)$edit_data['id_pelanggan'] ?>">
                    <?php endif; ?>

                    <!-- Input Nama -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-on-surface">
                            Nama Lengkap Pelanggan <span class="text-error">*</span>
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-2.5 text-secondary text-[18px]">badge</span>
                            <input type="text" name="nama_pelanggan" required 
                                   value="<?= $edit_data ? htmlspecialchars($edit_data['nama_pelanggan']) : '' ?>"
                                   placeholder="Contoh: Hendra Kusuma" 
                                   class="w-full h-10 pl-9 pr-3 rounded-lg bg-surface text-on-surface text-xs font-medium border border-outline-variant/40 focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary-container/30 transition shadow-xs">
                        </div>
                    </div>

                    <!-- Dropdown / Input Kota -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-on-surface">
                            Kota Asal Domisili <span class="text-error">*</span>
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-2.5 text-secondary text-[18px]">location_on</span>
                            <input type="text" name="kota" required 
                                   value="<?= $edit_data ? htmlspecialchars($edit_data['kota']) : '' ?>"
                                   placeholder="Contoh: Ponorogo, Surabaya, Madiun" 
                                   class="w-full h-10 pl-9 pr-3 rounded-lg bg-surface text-on-surface text-xs font-medium border border-outline-variant/40 focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary-container/30 transition shadow-xs">
                        </div>
                    </div>

                    <!-- Rule Indicator Callout -->
                    <div class="p-3.5 rounded-lg bg-primary-fixed/40 text-on-primary-fixed border border-primary-fixed flex items-start gap-2.5">
                        <span class="material-symbols-outlined text-primary text-[20px] shrink-0 mt-0.5">info</span>
                        <p class="text-[11px] leading-relaxed text-on-surface">
                            <strong class="font-bold text-primary">Kalkulasi Otomatis:</strong> Kategori loyalitas dihitung otomatis via Stored Function <code class="font-mono bg-white/70 px-1 py-0.2 rounded font-bold">KategoriBelanja()</code>:
                            <br><span class="font-semibold text-primary">&bull; Gold: &ge; Rp 1.000.000</span>
                            <br><span class="font-semibold text-secondary">&bull; Silver: &ge; Rp 500.000</span>
                            <br><span class="font-semibold text-on-surface-variant">&bull; Reguler: &lt; Rp 500.000</span>
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-2 flex flex-col gap-2">
                        <button type="submit" class="w-full py-2.5 rounded-lg bg-primary-container hover:bg-on-primary-fixed-variant text-on-primary font-headline font-bold text-xs shadow-sm transition-all active:scale-[0.99] flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            <span><?= $edit_data ? 'Perbarui Pelanggan' : 'Simpan Pelanggan' ?></span>
                        </button>
                        <?php if ($edit_data): ?>
                            <a href="pelanggan.php" class="w-full py-2 text-center rounded-lg bg-surface-container text-secondary hover:text-on-surface text-xs font-semibold transition-colors">
                                Batal Edit
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- RIGHT CARD: Data Pelanggan & Kategori Loyalitas (col-span-12 lg:col-span-8) -->
            <div class="lg:col-span-8 bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant/30 flex flex-col overflow-hidden">
                
                <!-- Filter, Search & Tab Bar -->
                <div class="p-4 bg-surface-container-low border-b border-outline-variant/30 space-y-3.5">
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                        <!-- Search Input -->
                        <form method="GET" action="pelanggan.php" class="relative flex-1">
                            <span class="material-symbols-outlined absolute left-3 top-2.5 text-secondary text-[20px]">search</span>
                            <input type="text" name="q" value="<?= htmlspecialchars($search_keyword) ?>" 
                                   placeholder="Cari nama atau kota pelanggan..." 
                                   class="w-full h-10 pl-9 pr-4 rounded-lg bg-surface-container-lowest text-on-surface text-xs placeholder:text-secondary/70 border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container/30 shadow-xs">
                        </form>
                    </div>

                    <!-- Loyalty Filter Pills & Active Summary -->
                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                        <div class="flex items-center gap-1.5 text-xs">
                            <span class="px-3 py-1 rounded-full font-bold bg-inverse-surface text-inverse-on-surface shadow-xs">
                                Semua (<?= count($daftar_pelanggan) ?>)
                            </span>
                            <span class="px-2.5 py-1 rounded-full font-bold bg-primary-fixed text-on-primary-fixed">
                                Gold (<?= $countGold ?>)
                            </span>
                            <span class="px-2.5 py-1 rounded-full font-bold bg-secondary-fixed text-on-secondary-fixed">
                                Silver (<?= $countSilver ?>)
                            </span>
                            <span class="px-2.5 py-1 rounded-full font-bold bg-surface-container text-on-surface-variant">
                                Reguler (<?= $countReguler ?>)
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Enterprise Data Table -->
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-surface-container-low/70 text-secondary font-semibold uppercase tracking-wider text-[11px] h-10 border-b border-outline-variant/20">
                                <th class="py-2.5 px-5">#ID</th>
                                <th class="py-2.5 px-4">Nama Pelanggan</th>
                                <th class="py-2.5 px-4">Kota</th>
                                <th class="py-2.5 px-4 text-right">Total Belanja</th>
                                <th class="py-2.5 px-4 text-center">Kategori Loyalitas</th>
                                <th class="py-2.5 px-5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/20">
                            <?php if (empty($daftar_pelanggan)): ?>
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-secondary">
                                        Tidak ada data pelanggan yang cocok dengan pencarian.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daftar_pelanggan as $row): 
                                    $isGold = strpos($row['kategori'], 'Gold') !== false;
                                    $isSilver = strpos($row['kategori'], 'Silver') !== false;
                                    $initials = strtoupper(substr($row['nama_pelanggan'], 0, 2));
                                ?>
                                    <tr class="hover:bg-surface-container-low/60 transition-colors">
                                        <td class="py-3 px-5 font-mono font-bold text-secondary">
                                            #CUST-<?= str_pad($row['id_pelanggan'], 3, '0', STR_PAD_LEFT) ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-full <?= $isGold ? 'bg-primary-fixed text-on-primary-fixed' : ($isSilver ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-surface-container text-secondary') ?> flex items-center justify-center font-bold text-[10px] font-headline">
                                                    <?= $initials ?>
                                                </div>
                                                <span class="font-bold text-on-surface"><?= htmlspecialchars($row['nama_pelanggan']) ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-secondary">
                                            <?= htmlspecialchars($row['kota']) ?>
                                        </td>
                                        <td class="py-3 px-4 text-right font-mono font-bold text-on-surface">
                                            <?= formatRupiah($row['total_belanja']) ?>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $isGold ? 'bg-primary-fixed text-on-primary-fixed' : ($isSilver ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-surface-container text-on-surface-variant') ?>">
                                                <span class="material-symbols-outlined text-[12px] fill-icon">star</span>
                                                <span><?= htmlspecialchars($row['kategori']) ?></span>
                                            </span>
                                        </td>
                                        <td class="py-3 px-5 text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-2">
                                                <a href="kasir.php?pelanggan_id=<?= $row['id_pelanggan'] ?>" title="Kasir" 
                                                   class="p-1 rounded text-primary hover:bg-primary-fixed transition">
                                                    <span class="material-symbols-outlined text-[16px]">point_of_sale</span>
                                                </a>
                                                <a href="pelanggan.php?edit=<?= $row['id_pelanggan'] ?>" title="Edit" 
                                                   class="p-1 rounded text-secondary hover:text-on-surface transition">
                                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                                </a>
                                                <form method="POST" action="pelanggan.php" class="inline" onsubmit="return confirm('Hapus pelanggan <?= htmlspecialchars(addslashes($row['nama_pelanggan'])) ?>?')">
                                                    <input type="hidden" name="action" value="delete_pelanggan">
                                                    <input type="hidden" name="id_pelanggan" value="<?= $row['id_pelanggan'] ?>">
                                                    <input type="hidden" name="nama_pelanggan" value="<?= htmlspecialchars($row['nama_pelanggan']) ?>">
                                                    <button type="submit" title="Hapus" class="p-1 rounded text-secondary hover:text-error transition">
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

                <!-- Table Footer -->
                <div class="px-5 py-3 bg-surface-container-low border-t border-outline-variant/20 flex items-center justify-between text-xs text-secondary">
                    <span>Data terindeks dari View <code class="font-mono bg-white px-1 py-0.5 rounded text-[10px]">view_total_belanja</code></span>
                    <span class="font-bold font-mono text-on-surface"><?= count($daftar_pelanggan) ?> Pelanggan</span>
                </div>
            </div>

        </div>

    </div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>