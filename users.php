<?php
/**
 * Manajemen Akun Kasir & Petugas POS
 * Khusus Administrator TokoKu POS Retail Engine
 */
require_once __DIR__ . '/config/db.php';
requireRole(['Admin']);

$current_page = 'users';
$page_title = 'Manajemen Kasir & Petugas';

$success_message = null;
$error_message = null;

// Handle Aksi Form
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. TAMBAH USER / KASIR BARU
    if ($action === 'tambah_user') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $role = trim($_POST['role'] ?? 'Kasir');

        if (empty($username) || empty($password) || empty($nama_lengkap)) {
            $error_message = "Semua field (Username, Password, Nama Lengkap) wajib diisi.";
        } else {
            try {
                // Cek username duplikat
                $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM tbl_user WHERE username = ?");
                $stmtCek->execute([$username]);
                if ($stmtCek->fetchColumn() > 0) {
                    $error_message = "Username '{$username}' sudah digunakan. Gunakan username lain.";
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmtIns = $pdo->prepare("
                        INSERT INTO tbl_user (username, password, nama_lengkap, role, status)
                        VALUES (?, ?, ?, ?, 'Aktif')
                    ");
                    $stmtIns->execute([$username, $hash, $nama_lengkap, $role]);
                    $stmtIns->closeCursor();

                    // Log audit
                    try {
                        $stmtL = $pdo->prepare("INSERT INTO tbl_log (tabel_terkait, aktivitas, keterangan) VALUES ('tbl_user', 'INSERT', ?)");
                        $stmtL->execute(["Admin menambahkan user baru '{$username}' ({$role})"]);
                    } catch (Exception $eL) {}

                    $success_message = "Petugas baru '{$nama_lengkap}' ({$role}) berhasil ditambahkan!";
                }
            } catch (Exception $e) {
                $error_message = "Gagal menambah petugas: " . $e->getMessage();
            }
        }
    }

    // 2. TOGGLE STATUS AKTIF / NONAKTIF
    elseif ($action === 'toggle_status') {
        $id_target = (int)($_POST['id_user'] ?? 0);
        $status_baru = $_POST['status_baru'] ?? 'Aktif';

        if ($id_target === (int)$_SESSION['user_id']) {
            $error_message = "Anda tidak dapat menonaktifkan akun yang sedang Anda gunakan saat ini.";
        } else {
            try {
                $stmtUp = $pdo->prepare("UPDATE tbl_user SET status = ? WHERE id_user = ?");
                $stmtUp->execute([$status_baru, $id_target]);
                $stmtUp->closeCursor();
                $success_message = "Status akun berhasil diubah menjadi '{$status_baru}'.";
            } catch (Exception $e) {
                $error_message = "Gagal mengubah status: " . $e->getMessage();
            }
        }
    }

    // 3. RESET PASSWORD KASIR
    elseif ($action === 'reset_password') {
        $id_target = (int)($_POST['id_user'] ?? 0);
        $password_baru = $_POST['password_baru'] ?? '';

        if (empty($password_baru) || strlen($password_baru) < 4) {
            $error_message = "Password baru minimal 4 karakter.";
        } else {
            try {
                $hash = password_hash($password_baru, PASSWORD_BCRYPT);
                $stmtRP = $pdo->prepare("UPDATE tbl_user SET password = ? WHERE id_user = ?");
                $stmtRP->execute([$hash, $id_target]);
                $stmtRP->closeCursor();
                $success_message = "Password petugas berhasil direset!";
            } catch (Exception $e) {
                $error_message = "Gagal mereset password: " . $e->getMessage();
            }
        }
    }

    // 4. HAPUS USER
    elseif ($action === 'hapus_user') {
        $id_target = (int)($_POST['id_user'] ?? 0);
        if ($id_target === (int)$_SESSION['user_id']) {
            $error_message = "Anda tidak dapat menghapus akun Anda sendiri.";
        } else {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM tbl_user WHERE id_user = ?");
                $stmtDel->execute([$id_target]);
                $stmtDel->closeCursor();
                $success_message = "Petugas berhasil dihapus dari sistem.";
            } catch (Exception $e) {
                $error_message = "Gagal menghapus petugas: " . $e->getMessage();
            }
        }
    }
}

// Ambil Seluruh Data Petugas
$users_list = [];
$total_kasir = 0;
$total_admin = 0;

try {
    $stmtU = $pdo->query("SELECT * FROM tbl_user ORDER BY role ASC, id_user ASC");
    $users_list = $stmtU->fetchAll();
    $stmtU->closeCursor();

    foreach ($users_list as $u) {
        if ($u['role'] === 'Kasir') $total_kasir++;
        if ($u['role'] === 'Admin') $total_admin++;
    }
} catch (Exception $e) {
    $error_message = "Gagal mengambil data user: " . $e->getMessage();
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
                <span class="material-symbols-outlined text-primary text-[24px]">manage_accounts</span>
                Manajemen Kasir &amp; Hak Akses Petugas
            </h1>
            <p class="text-xs text-secondary mt-0.5">Kelola akun kasir, kontrol hak akses POS, dan reset password staf toko.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="openTambahUserModal()" 
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-primary-container hover:bg-on-primary-fixed-variant text-white text-xs font-bold shadow-2xs transition active:scale-95">
                <span class="material-symbols-outlined text-[18px]">person_add</span>
                <span>+ Tambah Petugas Kasir</span>
            </button>
        </div>
    </header>

    <!-- Page Body -->
    <div class="p-6 sm:p-8 space-y-6 flex-1 bg-surface">

        <!-- Notifikasi Sukses / Error -->
        <?php if ($success_message): ?>
            <div role="alert" class="p-4 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-emerald-600 text-[20px]">check_circle</span>
                    <span class="text-xs font-semibold"><?= htmlspecialchars($success_message) ?></span>
                </div>
                <button onclick="dismissAlert(this)" class="text-emerald-600 hover:text-emerald-800 p-1">
                    <span class="material-symbols-outlined text-[16px]">close</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div role="alert" class="p-4 rounded-xl bg-error-container text-on-error-container border border-error/20 flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-error text-[20px]">error</span>
                    <span class="text-xs font-semibold"><?= htmlspecialchars($error_message) ?></span>
                </div>
                <button onclick="dismissAlert(this)" class="text-on-error-container/70 hover:text-on-error-container p-1">
                    <span class="material-symbols-outlined text-[16px]">close</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- 3 KPI Cards Ringkasan Petugas -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            
            <div class="p-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 flex items-center gap-4 shadow-2xs">
                <div class="w-12 h-12 rounded-xl bg-primary-container/10 text-primary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[26px]">badge</span>
                </div>
                <div>
                    <span class="text-xs text-secondary font-medium">Total Akun Terdaftar</span>
                    <h3 class="font-headline font-extrabold text-xl text-on-surface mt-0.5"><?= count($users_list) ?> Pengguna</h3>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 flex items-center gap-4 shadow-2xs">
                <div class="w-12 h-12 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[26px]">point_of_sale</span>
                </div>
                <div>
                    <span class="text-xs text-secondary font-medium">Petugas Kasir</span>
                    <h3 class="font-headline font-extrabold text-xl text-teal-800 mt-0.5"><?= $total_kasir ?> Kasir</h3>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 flex items-center gap-4 shadow-2xs">
                <div class="w-12 h-12 rounded-xl bg-orange-50 text-orange-700 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[26px]">shield_person</span>
                </div>
                <div>
                    <span class="text-xs text-secondary font-medium">Administrator</span>
                    <h3 class="font-headline font-extrabold text-xl text-orange-800 mt-0.5"><?= $total_admin ?> Admin</h3>
                </div>
            </div>

        </div>

        <!-- Tabel Petugas / User -->
        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 shadow-2xs overflow-hidden">
            <div class="px-5 py-4 border-b border-outline-variant/20 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[20px]">group</span>
                    <h3 class="font-headline font-bold text-sm text-on-surface">Daftar Akun Kasir &amp; Administrator</h3>
                </div>
                <span class="text-xs text-secondary font-medium">Total: <?= count($users_list) ?> Akun</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-surface-container-low text-secondary border-b border-outline-variant/30">
                            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[10px]">Petugas</th>
                            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[10px]">Username</th>
                            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[10px]">Peran (Role)</th>
                            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[10px]">Status</th>
                            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[10px]">Terakhir Login</th>
                            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[10px] text-center">Aksi Admin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20 text-on-surface">
                        <?php foreach ($users_list as $u): ?>
                            <tr class="hover:bg-surface-container-low/50 transition">
                                
                                <!-- Nama & Avatar -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg <?= $u['role'] === 'Admin' ? 'bg-primary-container text-white' : 'bg-teal-700 text-white' ?> flex items-center justify-center font-bold text-xs font-headline shrink-0">
                                            <?= strtoupper(substr($u['nama_lengkap'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <span class="font-bold text-on-surface block"><?= htmlspecialchars($u['nama_lengkap']) ?></span>
                                            <?php if ((int)$u['id_user'] === (int)$_SESSION['user_id']): ?>
                                                <span class="text-[9px] font-bold text-primary bg-primary-fixed/30 px-1 py-0.2 rounded">Akun Anda</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Username -->
                                <td class="py-3 px-4 font-mono font-bold text-secondary">
                                    @<?= htmlspecialchars($u['username']) ?>
                                </td>

                                <!-- Role -->
                                <td class="py-3 px-4">
                                    <?php if ($u['role'] === 'Admin'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-100 text-orange-800 border border-orange-200">
                                            <span class="material-symbols-outlined text-[13px]">shield_person</span>
                                            <span>Administrator</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-100 text-teal-800 border border-teal-200">
                                            <span class="material-symbols-outlined text-[13px]">point_of_sale</span>
                                            <span>Kasir POS</span>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status -->
                                <td class="py-3 px-4">
                                    <?php if ($u['status'] === 'Aktif'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                            <span>Aktif</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span>
                                            <span>Nonaktif</span>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Terakhir Login -->
                                <td class="py-3 px-4 text-secondary text-[11px]">
                                    <?php if ($u['terakhir_login']): ?>
                                        <span class="font-medium text-on-surface"><?= date('d M Y', strtotime($u['terakhir_login'])) ?></span>
                                        <span class="font-mono text-[10px] block"><?= date('H:i', strtotime($u['terakhir_login'])) ?> WIB</span>
                                    <?php else: ?>
                                        <span class="text-secondary/60 italic">Belum pernah login</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Aksi -->
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        
                                        <!-- Tombol Reset Password -->
                                        <button type="button" onclick="openResetPasswordModal(<?= $u['id_user'] ?>, '<?= htmlspecialchars($u['nama_lengkap']) ?>')"
                                                class="p-1.5 rounded-lg text-secondary hover:text-primary hover:bg-surface-container transition" title="Ganti / Reset Password">
                                            <span class="material-symbols-outlined text-[17px]">lock_reset</span>
                                        </button>

                                        <?php if ((int)$u['id_user'] !== (int)$_SESSION['user_id']): ?>
                                            <!-- Tombol Toggle Status -->
                                            <form method="POST" action="users.php" class="inline" onsubmit="return confirm('Ubah status akun ini?')">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id_user" value="<?= $u['id_user'] ?>">
                                                <input type="hidden" name="status_baru" value="<?= $u['status'] === 'Aktif' ? 'Nonaktif' : 'Aktif' ?>">
                                                <button type="submit" class="p-1.5 rounded-lg text-secondary hover:text-amber-600 hover:bg-amber-50 transition" 
                                                        title="<?= $u['status'] === 'Aktif' ? 'Nonaktifkan Akun' : 'Aktifkan Akun' ?>">
                                                    <span class="material-symbols-outlined text-[17px]"><?= $u['status'] === 'Aktif' ? 'toggle_on' : 'toggle_off' ?></span>
                                                </button>
                                            </form>

                                            <!-- Tombol Hapus -->
                                            <form method="POST" action="users.php" class="inline" onsubmit="return confirm('Hapus akun <?= htmlspecialchars($u['nama_lengkap']) ?> secara permanen?')">
                                                <input type="hidden" name="action" value="hapus_user">
                                                <input type="hidden" name="id_user" value="<?= $u['id_user'] ?>">
                                                <button type="submit" class="p-1.5 rounded-lg text-secondary hover:text-rose-600 hover:bg-rose-50 transition" title="Hapus Akun">
                                                    <span class="material-symbols-outlined text-[17px]">delete</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                    </div>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<!-- Modal Tambah Petugas / Kasir Baru -->
<div id="tambahUserModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/40 shadow-xl max-w-md w-full overflow-hidden">
        
        <div class="px-6 py-4 border-b border-outline-variant/20 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[22px]">person_add</span>
                <h3 class="font-headline font-bold text-base text-on-surface">Tambah Petugas Kasir Baru</h3>
            </div>
            <button type="button" onclick="closeTambahUserModal()" class="text-secondary hover:text-on-surface p-1">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <form method="POST" action="users.php" class="p-6 space-y-4">
            <input type="hidden" name="action" value="tambah_user">

            <div>
                <label class="block text-xs font-bold text-on-surface mb-1">Nama Lengkap Petugas:</label>
                <input type="text" name="nama_lengkap" required placeholder="Contoh: Siti Rahmawati"
                       class="w-full h-10 px-3 text-xs rounded-lg border border-outline-variant/40 bg-surface-container-lowest text-on-surface focus:outline-none focus:ring-2 focus:ring-primary-container">
            </div>

            <div>
                <label class="block text-xs font-bold text-on-surface mb-1">Username Login:</label>
                <input type="text" name="username" required placeholder="Contoh: kasir2"
                       class="w-full h-10 px-3 text-xs font-mono rounded-lg border border-outline-variant/40 bg-surface-container-lowest text-on-surface focus:outline-none focus:ring-2 focus:ring-primary-container">
            </div>

            <div>
                <label class="block text-xs font-bold text-on-surface mb-1">Password:</label>
                <input type="password" name="password" required placeholder="Masukkan password login..."
                       class="w-full h-10 px-3 text-xs rounded-lg border border-outline-variant/40 bg-surface-container-lowest text-on-surface focus:outline-none focus:ring-2 focus:ring-primary-container">
            </div>

            <div>
                <label class="block text-xs font-bold text-on-surface mb-1">Peran (Role):</label>
                <select name="role" class="w-full h-10 px-3 text-xs font-bold rounded-lg border border-outline-variant/40 bg-surface-container-lowest text-on-surface focus:outline-none focus:ring-2 focus:ring-primary-container">
                    <option value="Kasir" selected>Kasir (Hanya Akses POS, Riwayat &amp; Tambah Member)</option>
                    <option value="Admin">Administrator (Akses Penuh: CRUD Produk, Laporan, Manajemen User)</option>
                </select>
            </div>

            <div class="pt-3 border-t border-outline-variant/20 flex items-center justify-end gap-2">
                <button type="button" onclick="closeTambahUserModal()" 
                        class="px-4 py-2 rounded-lg bg-surface-container hover:bg-surface-container-high text-secondary text-xs font-bold transition">
                    Batal
                </button>
                <button type="submit" 
                        class="px-5 py-2 rounded-lg bg-primary-container hover:bg-on-primary-fixed-variant text-white font-headline text-xs font-bold transition active:scale-95 shadow-2xs">
                    Simpan Petugas
                </button>
            </div>
        </form>

    </div>
</div>

<!-- Modal Reset Password -->
<div id="resetPasswordModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/40 shadow-xl max-w-sm w-full overflow-hidden">
        
        <div class="px-5 py-4 border-b border-outline-variant/20 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-600 text-[20px]">lock_reset</span>
                <h3 class="font-headline font-bold text-sm text-on-surface">Reset Password Petugas</h3>
            </div>
            <button type="button" onclick="closeResetPasswordModal()" class="text-secondary hover:text-on-surface p-1">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <form method="POST" action="users.php" class="p-5 space-y-4">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id_user" id="resetUserId" value="">

            <div>
                <p class="text-xs text-secondary mb-3">
                    Mereset password untuk: <strong id="resetUserName" class="text-on-surface"></strong>
                </p>
                <label class="block text-xs font-bold text-on-surface mb-1">Password Baru:</label>
                <input type="password" name="password_baru" required placeholder="Minimal 4 karakter..."
                       class="w-full h-10 px-3 text-xs rounded-lg border border-outline-variant/40 bg-surface-container-lowest text-on-surface focus:outline-none focus:ring-2 focus:ring-primary-container">
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button type="button" onclick="closeResetPasswordModal()" 
                        class="px-3 py-1.5 rounded-lg bg-surface-container text-secondary text-xs font-bold transition">
                    Batal
                </button>
                <button type="submit" 
                        class="px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-headline text-xs font-bold transition">
                    Simpan Password
                </button>
            </div>
        </form>

    </div>
</div>

<script>
    function openTambahUserModal() {
        document.getElementById('tambahUserModal').classList.remove('hidden');
    }
    function closeTambahUserModal() {
        document.getElementById('tambahUserModal').classList.add('hidden');
    }
    function openResetPasswordModal(id, nama) {
        document.getElementById('resetUserId').value = id;
        document.getElementById('resetUserName').innerText = nama;
        document.getElementById('resetPasswordModal').classList.remove('hidden');
    }
    function closeResetPasswordModal() {
        document.getElementById('resetPasswordModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
