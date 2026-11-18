<?php
/**
 * Halaman Login Sistem POS - Stitch Design System: TokoKu POS Retail Engine
 * Autentikasi Pengguna (Admin & Kasir)
 */
session_start();
require_once __DIR__ . '/config/db.php';

// Jika sudah login, redirect langsung ke halaman kerja sesuai role
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Kasir') {
        header('Location: kasir.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error_message = null;
$success_message = null;

if (isset($_GET['unauthorized']) || (isset($_GET['error']) && $_GET['error'] === 'unauthorized')) {
    $error_message = "Silakan login terlebih dahulu untuk mengakses sistem.";
} elseif (isset($_GET['error']) && $_GET['error'] === 'forbidden') {
    $error_message = "Akses ditolak: Anda tidak memiliki izin untuk mengakses halaman tersebut.";
}

if (isset($_GET['logged_out'])) {
    $success_message = "Anda telah berhasil keluar (logout) dari sistem POS.";
}

// Handle Form Login
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = "Username dan Password wajib diisi.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM tbl_user WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            $stmt->closeCursor();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'Aktif') {
                    $error_message = "Akun Anda berstatus nonaktif. Silakan hubungi Administrator.";
                } else {
                    // Update terakhir login
                    try {
                        $upStmt = $pdo->prepare("UPDATE tbl_user SET terakhir_login = NOW() WHERE id_user = ?");
                        $upStmt->execute([$user['id_user']]);
                    } catch (Exception $eU) {}

                    // Simpan data login ke Session
                    $_SESSION['user_id']      = (int)$user['id_user'];
                    $_SESSION['username']     = $user['username'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['role']         = $user['role'];

                    // Catat ke tbl_log
                    try {
                        $logStmt = $pdo->prepare("INSERT INTO tbl_log (tabel_terkait, aktivitas, keterangan) VALUES ('tbl_user', 'LOGIN', ?)");
                        $logStmt->execute(["User '{$user['username']}' ({$user['role']}) berhasil login"]);
                        $logStmt->closeCursor();
                    } catch (Exception $eL) {}

                    // Redirect sesuai peran
                    if ($user['role'] === 'Kasir') {
                        header('Location: kasir.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit;
                }
            } else {
                $error_message = "Username atau Password yang Anda masukkan salah.";
            }
        } catch (Exception $e) {
            $error_message = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-surface">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Petugas POS &mdash; GopellMart TokoKu</title>
    <!-- Tailwind CSS with Stitch POS Theme -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#9d4300',
                        'primary-container': '#f97316',
                        'on-primary': '#ffffff',
                        'on-primary-fixed-variant': '#783200',
                        surface: '#f8f9ff',
                        'surface-container-low': '#eff4ff',
                        'surface-container': '#e5eeff',
                        'surface-container-high': '#dce9ff',
                        'surface-container-lowest': '#ffffff',
                        'on-surface': '#0b1c30',
                        secondary: '#565e74',
                        error: '#ba1a1a',
                        'error-container': '#ffdad6',
                        'on-error-container': '#93000a',
                    },
                    fontFamily: {
                        headline: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'],
                        body: ['"Inter"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body class="h-full flex items-center justify-center p-4 bg-gradient-to-br from-[#f8f9ff] via-[#eff4ff] to-[#ffeed9] text-on-surface font-body antialiased selection:bg-primary-container selection:text-white">

    <div class="w-full max-w-md">

        <!-- Login Card -->
        <div class="bg-surface-container-lowest rounded-3xl p-6 sm:p-8 shadow-xl border border-outline-variant/30 space-y-6">

            <!-- Brand Header -->
            <div class="text-center space-y-2">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-tr from-primary to-primary-container text-white flex items-center justify-center shadow-md shadow-primary-container/30">
                    <span class="material-symbols-outlined text-[32px]">point_of_sale</span>
                </div>
                <div>
                    <h1 class="font-headline font-bold text-xl sm:text-2xl text-on-surface tracking-tight">GopellMart POS</h1>
                    <p class="text-xs text-secondary mt-0.5">Sistem Informasi Penjualan &amp; Kasir Ritel</p>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success_message): ?>
                <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs flex items-center gap-2.5 shadow-2xs">
                    <span class="material-symbols-outlined text-emerald-600 text-[20px] shrink-0">check_circle</span>
                    <span class="font-medium"><?= htmlspecialchars($success_message) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="p-3.5 rounded-xl bg-error-container/60 border border-error/20 text-on-error-container text-xs flex items-center gap-2.5 shadow-2xs">
                    <span class="material-symbols-outlined text-error text-[20px] shrink-0">error</span>
                    <span class="font-medium"><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>

            <!-- Form Login -->
            <form method="POST" action="login.php" class="space-y-4">

                <!-- Input Username -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-on-surface">Username</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-3 text-secondary text-[20px]">person</span>
                        <input type="text" name="username" id="inputUsername" required autofocus
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="Masukkan username petugas..."
                               class="w-full h-11 pl-11 pr-4 bg-surface text-on-surface text-xs font-medium rounded-xl border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container shadow-2xs transition">
                    </div>
                </div>

                <!-- Input Password -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-on-surface">Password</label>
                    </div>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-3 text-secondary text-[20px]">lock</span>
                        <input type="password" name="password" id="inputPassword" required
                               placeholder="Masukkan kata sandi..."
                               class="w-full h-11 pl-11 pr-11 bg-surface text-on-surface text-xs font-medium rounded-xl border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container shadow-2xs transition">
                        <!-- Toggle Password Visibility -->
                        <button type="button" onclick="togglePasswordVisibility()" class="absolute right-3 top-2.5 text-secondary hover:text-on-surface p-1 rounded-lg">
                            <span class="material-symbols-outlined text-[20px]" id="eyeIcon">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" 
                        class="w-full py-3.5 px-4 rounded-xl bg-primary-container hover:bg-on-primary-fixed-variant text-white font-headline font-bold text-sm shadow-md shadow-primary-container/20 transition-all active:scale-[0.98] flex items-center justify-center gap-2 mt-2">
                    <span class="material-symbols-outlined text-[20px]">login</span>
                    <span>Masuk ke Sistem POS</span>
                </button>
            </form>

            <!-- Quick Demo Login Buttons (Untuk Kemudahan Uji Coba) -->
            <div class="pt-4 border-t border-outline-variant/30 space-y-2">
                <span class="text-[10px] font-bold text-secondary uppercase tracking-wider block text-center">Akun Uji Coba Cepat (Demo):</span>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="fillDemo('admin', 'admin123')" class="p-2 rounded-xl bg-surface-container hover:bg-surface-container-high border border-outline-variant/30 text-left transition text-xs group">
                        <div class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-primary text-[16px]">shield_person</span>
                            <strong class="font-bold text-on-surface block">Admin</strong>
                        </div>
                        <span class="text-[10px] text-secondary font-mono">admin / admin123</span>
                    </button>
                    <button type="button" onclick="fillDemo('kasir', 'kasir123')" class="p-2 rounded-xl bg-surface-container hover:bg-surface-container-high border border-outline-variant/30 text-left transition text-xs group">
                        <div class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-tertiary text-[16px]">point_of_sale</span>
                            <strong class="font-bold text-on-surface block">Kasir</strong>
                        </div>
                        <span class="text-[10px] text-secondary font-mono">kasir / kasir123</span>
                    </button>
                </div>
            </div>

            <!-- Footer Info -->
            <div class="text-center pt-2 space-y-1">
                <p class="text-[11px] text-secondary">
                    Butuh belanja online? Buka <a href="pembeli.php" class="text-primary-container hover:underline font-bold">Toko Online Pembeli</a>
                </p>
                <p class="text-[11px] text-secondary">
                    <a href="welcome.php" class="text-secondary hover:text-on-surface font-medium hover:underline inline-flex items-center gap-1">
                        <span class="material-symbols-outlined text-[13px]">arrow_back</span>
                        <span>Kembali ke Halaman Utama (Welcome)</span>
                    </a>
                </p>
            </div>

        </div>

        <p class="text-center text-xs text-secondary/70 mt-6">
            &copy; <?= date('Y') ?> GopellMart Retail Engine &bull; All Rights Reserved.
        </p>

    </div>

    <!-- Toggle Password & Fill Demo Script -->
    <script>
        function togglePasswordVisibility() {
            const passInput = document.getElementById('inputPassword');
            const eyeIcon = document.getElementById('eyeIcon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                eyeIcon.innerText = 'visibility_off';
            } else {
                passInput.type = 'password';
                eyeIcon.innerText = 'visibility';
            }
        }

        function fillDemo(username, password) {
            document.getElementById('inputUsername').value = username;
            document.getElementById('inputPassword').value = password;
        }
    </script>
</body>
</html>
