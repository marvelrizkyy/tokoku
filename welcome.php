<?php
/**
 * Halaman Welcome / Landing Page Ekosistem GopellMart
 * Stitch Design System: Warm Vibrant Orange POS & Smart Online Retail
 */
require_once __DIR__ . '/config/db.php';

// Ambil Statistik Live Database untuk Showcase
$total_produk = 0;
$total_member = 0;
$total_transaksi = 0;
$total_omset = 0;

try {
    $total_produk = (int)$pdo->query("SELECT COUNT(*) FROM tbl_produk")->fetchColumn();
    $total_member = (int)$pdo->query("SELECT COUNT(*) FROM tbl_pelanggan")->fetchColumn();
    $total_transaksi = (int)$pdo->query("SELECT COUNT(*) FROM tbl_transaksi")->fetchColumn();
    $total_omset = (float)$pdo->query("SELECT COALESCE(SUM(total_belanja), 0) FROM tbl_transaksi")->fetchColumn();
} catch (Exception $e) {
    // Graceful fallback jika query statistik gagal
}

// Ambil beberapa sampel produk unggulan
$produk_preview = [];
try {
    $stmtP = $pdo->query("SELECT nama_produk, kategori, harga, stok FROM tbl_produk WHERE stok > 0 ORDER BY id_produk ASC LIMIT 4");
    $produk_preview = $stmtP->fetchAll();
    $stmtP->closeCursor();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50 scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Datang &mdash; GopellMart POS &amp; Online Shop</title>
    <!-- Tailwind CSS with Stitch Theme -->
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
                        surface: '#f8fafc',
                        'surface-container-low': '#f1f5f9',
                        'surface-container': '#e2e8f0',
                        'surface-container-high': '#cbd5e1',
                        'surface-container-lowest': '#ffffff',
                        'on-surface': '#0f172a',
                        secondary: '#64748b',
                        tertiary: '#0f766e',
                        'tertiary-container': '#14b8a6',
                    },
                    fontFamily: {
                        headline: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'],
                        body: ['"Inter"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts & Material Symbols -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .material-symbols-outlined.fill-icon {
            font-variation-settings: 'FILL' 1;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 font-body flex flex-col selection:bg-orange-500 selection:text-white antialiased">

    <!-- Top Navigation Bar -->
    <header class="bg-white/90 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            
            <!-- Logo & Brand Title -->
            <a href="welcome.php" class="flex items-center gap-3 group">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-orange-600 to-amber-500 flex items-center justify-center text-white shadow-sm shadow-orange-500/25 group-hover:scale-105 transition-transform">
                    <span class="material-symbols-outlined text-[24px]">storefront</span>
                </div>
                <div class="leading-none">
                    <div class="flex items-center gap-1.5">
                        <span class="font-headline font-extrabold text-base sm:text-lg text-slate-900 tracking-tight">Gopell<span class="text-orange-600">Mart</span></span>
                    </div>
                    <span class="text-[10px] text-slate-400 font-semibold tracking-wider uppercase">POS &amp; Smart Retail System</span>
                </div>
            </a>

            <!-- Navigation Links -->
            <nav class="hidden md:flex items-center gap-6 text-xs font-semibold text-slate-600">
                <a href="#portal" class="hover:text-orange-600 transition">Pilihan Portal</a>
                <a href="#fitur" class="hover:text-orange-600 transition">Keunggulan Sistem</a>
                <a href="#rfid-checker" class="hover:text-orange-600 transition">Cek Kartu RFID</a>
                <a href="#katalog" class="hover:text-orange-600 transition">Katalog Belanja</a>
            </nav>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2 sm:gap-3">
                <a href="pembeli.php" class="inline-flex items-center gap-1.5 px-3 sm:px-4 py-2 rounded-xl bg-orange-50 hover:bg-orange-100 text-orange-700 font-headline text-xs font-bold border border-orange-200 transition active:scale-95">
                    <span class="material-symbols-outlined text-[18px]">shopping_bag</span>
                    <span class="hidden sm:inline">Portal Pembeli</span>
                    <span class="sm:hidden">Belanja</span>
                </a>

                <?php if (!empty($_SESSION['username'])): ?>
                    <a href="<?= ($_SESSION['role'] ?? '') === 'Kasir' ? 'kasir.php' : 'index.php' ?>" class="inline-flex items-center gap-1.5 px-3 sm:px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-headline text-xs font-bold transition shadow-sm active:scale-95">
                        <span class="material-symbols-outlined text-[18px] text-teal-400">dashboard</span>
                        <span>Workspace (<?= htmlspecialchars($_SESSION['username']) ?>)</span>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="inline-flex items-center gap-1.5 px-3 sm:px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-headline text-xs font-bold transition shadow-sm active:scale-95">
                        <span class="material-symbols-outlined text-[18px]">login</span>
                        <span>Login Petugas</span>
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </header>

    <!-- Hero Section -->
    <section class="relative overflow-hidden pt-12 pb-16 lg:pt-20 lg:pb-24 bg-gradient-to-b from-white via-orange-50/20 to-slate-50 border-b border-slate-200/60">
        <!-- Background Radial Glow -->
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-96 bg-gradient-to-br from-orange-400/10 via-amber-300/10 to-transparent blur-3xl pointer-events-none -z-10"></div>

        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            
            <!-- Badge Sambutan -->
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-orange-100/80 border border-orange-200 text-orange-800 text-xs font-bold mb-6 shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-orange-600 animate-ping"></span>
                <span class="material-symbols-outlined text-[16px] text-orange-600">verified</span>
                <span>Ekosistem Ritel Pintar &bull; Generasi Terbaru</span>
            </div>

            <!-- Big Main Headline -->
            <h1 class="font-headline font-extrabold text-3xl sm:text-5xl lg:text-6xl text-slate-900 tracking-tight leading-[1.15] max-w-4xl mx-auto">
                Selamat Datang di <br class="hidden sm:inline">
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-600 via-amber-600 to-orange-500">GopellMart</span>
            </h1>

            <!-- Subtitle -->
            <p class="mt-5 text-sm sm:text-base lg:text-lg text-slate-600 max-w-2xl mx-auto leading-relaxed">
                Platform terpadu untuk pelanggan yang ingin berbelanja daring <strong>100% non-tunai</strong> dengan login kartu RFID member, serta sistem kasir POS berkecepatan tinggi bagi staf ritel.
            </p>

            <!-- Trust Badges -->
            <div class="mt-10 flex flex-wrap items-center justify-center gap-6 text-xs font-semibold text-slate-500">
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-emerald-600 text-[18px]">check_circle</span>
                    <span>100% Cashless (QRIS &amp; Transfer)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-orange-600 text-[18px]">contactless</span>
                    <span>Member Card RFID Terintegrasi</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-blue-600 text-[18px]">lock</span>
                    <span>Database ACID Guaranteed</span>
                </div>
            </div>

        </div>
    </section>

    <!-- Dual Portal Gateway Section -->
    <section id="portal" class="py-16 sm:py-20 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        
        <div class="text-center max-w-2xl mx-auto mb-12">
            <h2 class="font-headline font-bold text-2xl sm:text-3xl text-slate-900 tracking-tight">Pilih Akses Sesuai Kebutuhan Anda</h2>
            <p class="text-xs sm:text-sm text-slate-500 mt-2">Sistem menyediakan dua portal terpisah dengan pengalaman antarmuka yang optimal untuk masing-masing pengguna.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch">
            
            <!-- PORTAL 1: TOKO ONLINE PEMBELI -->
            <div class="relative bg-white rounded-2xl border border-orange-200/90 p-6 sm:p-8 flex flex-col justify-between shadow-sm hover:shadow-xl hover:border-orange-300 transition-all group overflow-hidden">
                <!-- Decorative Corner Glow -->
                <div class="absolute -top-12 -right-12 w-40 h-40 bg-orange-400/10 rounded-full blur-2xl group-hover:bg-orange-500/20 transition-all pointer-events-none"></div>

                <div>
                    <!-- Header Portal -->
                    <div class="flex items-center justify-between gap-3 mb-6">
                        <div class="w-14 h-14 rounded-2xl bg-orange-500 text-white flex items-center justify-center shadow-md shadow-orange-500/20 group-hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined text-[30px]">shopping_bag</span>
                        </div>
                        <span class="text-[11px] font-bold px-3 py-1 rounded-full bg-orange-100 text-orange-800 border border-orange-200">
                            UNTUK PEMBELI
                        </span>
                    </div>

                    <!-- Title & Desc -->
                    <h3 class="font-headline font-bold text-xl sm:text-2xl text-slate-900 tracking-tight">
                        GopellMart Online Shop
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-600 mt-2 leading-relaxed">
                        Belanja kebutuhan harian praktis dari rumah atau smartphone Anda tanpa perlu datang atau mengantri di toko fisik.
                    </p>

                    <!-- Feature Bullet Points -->
                    <div class="mt-6 space-y-3 pt-6 border-t border-slate-100">
                        <div class="flex items-start gap-3">
                            <div class="w-6 h-6 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-[16px]">touch_app</span>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">Pilih Barang Langsung (Tanpa Scan)</h4>
                                <p class="text-[11px] text-slate-500">Cukup klik dan pilih produk yang diinginkan dari katalog interaktif.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-[16px]">qr_code_scanner</span>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">100% Pembayaran Non-Tunai</h4>
                                <p class="text-[11px] text-slate-500">Mendukung QRIS instan dan transfer bank. Tidak melayani uang tunai.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-[16px]">contactless</span>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">Login Instan dengan Nomor Kartu RFID</h4>
                                <p class="text-[11px] text-slate-500">Masukkan kode kartu RFID member Anda untuk langsung melihat riwayat dan invoice digital.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CTA Button -->
                <div class="mt-8 pt-6 border-t border-slate-100">
                    <a href="pembeli.php" class="w-full inline-flex items-center justify-between px-5 py-3.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-headline text-sm font-bold shadow-md shadow-orange-500/15 transition active:scale-95">
                        <span class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">storefront</span>
                            <span>Buka Toko Online Pembeli</span>
                        </span>
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </a>
                </div>

            </div>

            <!-- PORTAL 2: PETUGAS KASIR & BACKOFFICE POS -->
            <div class="relative bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 flex flex-col justify-between shadow-sm hover:shadow-xl hover:border-slate-300 transition-all group overflow-hidden">
                <!-- Decorative Corner Glow -->
                <div class="absolute -top-12 -right-12 w-40 h-40 bg-slate-400/10 rounded-full blur-2xl group-hover:bg-slate-500/20 transition-all pointer-events-none"></div>

                <div>
                    <!-- Header Portal -->
                    <div class="flex items-center justify-between gap-3 mb-6">
                        <div class="w-14 h-14 rounded-2xl bg-slate-900 text-white flex items-center justify-center shadow-md shadow-slate-900/20 group-hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined text-[30px] text-teal-400">point_of_sale</span>
                        </div>
                        <span class="text-[11px] font-bold px-3 py-1 rounded-full bg-slate-100 text-slate-800 border border-slate-200">
                            UNTUK KASIR &amp; ADMIN
                        </span>
                    </div>

                    <!-- Title & Desc -->
                    <h3 class="font-headline font-bold text-xl sm:text-2xl text-slate-900 tracking-tight">
                        GopellMart Kasir &amp; POS Engine
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-600 mt-2 leading-relaxed">
                        Aplikasi kasir meja cepat dengan pemindai barcode fisik, tap kartu RFID pelanggan, serta manajemen stok dan laporan analitik.
                    </p>

                    <!-- Feature Bullet Points -->
                    <div class="mt-6 space-y-3 pt-6 border-t border-slate-100">
                        <div class="flex items-start gap-3">
                            <div class="w-6 h-6 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-[16px]">barcode_scanner</span>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">Hardware Scanner &amp; Tap RFID Member</h4>
                                <p class="text-[11px] text-slate-500">Scan barcode item instan dan tap kartu fisik pelanggan untuk diskon otomatis.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="w-6 h-6 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-[16px]">query_stats</span>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">Laporan Penjualan Multi-JOIN &amp; Stok</h4>
                                <p class="text-[11px] text-slate-500">Dashboard lengkap dengan 4 ringkasan analitik, cetak struk termal, dan audit log.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="w-6 h-6 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-[16px]">shield_person</span>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">Multi-Role Hak Akses (Admin &amp; Kasir)</h4>
                                <p class="text-[11px] text-slate-500">Sistem terproteksi dengan password hashing BCRYPT dan pembatasan peran tugas.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CTA Button -->
                <div class="mt-8 pt-6 border-t border-slate-100">
                    <a href="login.php" class="w-full inline-flex items-center justify-between px-5 py-3.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-headline text-sm font-bold shadow-md shadow-slate-900/15 transition active:scale-95">
                        <span class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px] text-teal-400">login</span>
                            <span>Login Petugas / Kasir</span>
                        </span>
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </a>
                </div>

            </div>

        </div>

    </section>

    <!-- Live Statistics Strip -->
    <section class="bg-white border-y border-slate-200/80 py-10">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center divide-y md:divide-y-0 md:divide-x divide-slate-100">
                
                <!-- Stat 1 -->
                <div class="pt-4 md:pt-0">
                    <span class="material-symbols-outlined text-orange-500 text-[26px]">inventory_2</span>
                    <div class="font-headline font-extrabold text-2xl sm:text-3xl text-slate-900 mt-1">
                        <?= number_format($total_produk, 0, ',', '.') ?>
                    </div>
                    <span class="text-xs text-slate-500 font-medium">Produk Siap Belanja</span>
                </div>

                <!-- Stat 2 -->
                <div class="pt-4 md:pt-0">
                    <span class="material-symbols-outlined text-blue-500 text-[26px]">badge</span>
                    <div class="font-headline font-extrabold text-2xl sm:text-3xl text-slate-900 mt-1">
                        <?= number_format($total_member, 0, ',', '.') ?>
                    </div>
                    <span class="text-xs text-slate-500 font-medium">Member Kartu RFID</span>
                </div>

                <!-- Stat 3 -->
                <div class="pt-4 md:pt-0">
                    <span class="material-symbols-outlined text-emerald-500 text-[26px]">receipt_long</span>
                    <div class="font-headline font-extrabold text-2xl sm:text-3xl text-slate-900 mt-1">
                        <?= number_format($total_transaksi, 0, ',', '.') ?>
                    </div>
                    <span class="text-xs text-slate-500 font-medium">Transaksi Berhasil</span>
                </div>

                <!-- Stat 4 -->
                <div class="pt-4 md:pt-0">
                    <span class="material-symbols-outlined text-teal-600 text-[26px]">database</span>
                    <div class="font-headline font-extrabold text-base sm:text-xl text-slate-900 mt-2 flex items-center justify-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>Online</span>
                    </div>
                    <span class="text-xs text-slate-500 font-medium">MySQL PDO Connected</span>
                </div>

            </div>
        </div>
    </section>

    <!-- Interactive RFID Member Quick Login Section -->
    <section id="rfid-checker" class="py-16 max-w-4xl mx-auto px-4 sm:px-6 w-full">
        <div class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl p-6 sm:p-10 text-white shadow-xl relative overflow-hidden">
            <!-- Background Radial -->
            <div class="absolute -bottom-10 -right-10 w-64 h-64 bg-orange-500/20 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col md:flex-row items-center gap-8 justify-between">
                
                <!-- Left Text -->
                <div class="max-w-md text-center md:text-left">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 text-orange-300 text-[11px] font-bold mb-3">
                        <span class="material-symbols-outlined text-[16px]">contactless</span>
                        <span>CEK &amp; LOGIN KARTU MEMBER</span>
                    </div>
                    <h3 class="font-headline font-bold text-2xl text-white tracking-tight">
                        Punya Kartu RFID Member?
                    </h3>
                    <p class="text-xs text-slate-300 mt-2 leading-relaxed">
                        Masukkan nomor kartu RFID Anda untuk langsung masuk ke akun online di <strong>GopellMart</strong> dan cek riwayat transaksi belanja Anda.
                    </p>
                </div>

                <!-- Right Form -->
                <div class="w-full md:w-auto shrink-0 bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/15 max-w-sm">
                    <form method="POST" action="pembeli.php" class="space-y-3.5">
                        <input type="hidden" name="action" value="login_rfid">
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-200 mb-1.5">Nomor Kartu RFID Anda:</label>
                            <div class="relative">
                                <input type="text" id="welcomeRfidInput" name="rfid_code" required
                                       placeholder="Contoh: RFID-1001"
                                       class="w-full h-11 pl-10 pr-4 text-xs font-mono font-bold bg-white text-slate-900 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-orange-500 shadow-inner">
                                <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-[20px]">credit_card</span>
                            </div>
                        </div>

                        <button type="submit" class="w-full h-11 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-headline text-xs font-bold transition flex items-center justify-center gap-2 shadow-md active:scale-95">
                            <span class="material-symbols-outlined text-[18px]">login</span>
                            <span>Buka Akun Pembeli</span>
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </section>

    <!-- Key Ecosystem Features Section -->
    <section id="fitur" class="py-16 bg-slate-100/60 border-t border-slate-200/80">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-2xl mx-auto mb-12">
                <span class="text-xs text-orange-600 font-bold uppercase tracking-wider">Teknologi Terintegrasi</span>
                <h2 class="font-headline font-bold text-2xl sm:text-3xl text-slate-900 tracking-tight mt-1">Mengapa Memilih Ekosistem GopellMart?</h2>
                <p class="text-xs sm:text-sm text-slate-500 mt-2">Didesain dengan arsitektur SQL Lanjutan dan antarmuka modern yang nyaman bagi staf maupun pembeli.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Feature 1 -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-2xs hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-[26px]">contactless</span>
                    </div>
                    <h3 class="font-headline font-bold text-base text-slate-900">Kartu RFID Multi-Fungsi</h3>
                    <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                        Satu kartu fisik digunakan offline di meja kasir untuk identifikasi member dan online di smartphone sebagai autentikasi login tanpa ribet mengingat kata sandi.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-2xs hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-[26px]">payments</span>
                    </div>
                    <h3 class="font-headline font-bold text-base text-slate-900">Pasti Non-Tunai di Portal Pembeli</h3>
                    <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                        Toko online melarang pembayaran tunai untuk memastikan transaksi tervalidasi digital via QRIS dinamis atau transfer bank yang langsung tercatat.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-2xs hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-[26px]">security</span>
                    </div>
                    <h3 class="font-headline font-bold text-base text-slate-900">Integritas Database SQL Lanjutan</h3>
                    <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                        Didukung Database Triggers (penolakan angka belanja negatif otomatis), Stored Procedures ACID transaction, dan View multi-tabel yang andal.
                    </p>
                </div>

            </div>

        </div>
    </section>

    <!-- Product Sample Preview Section -->
    <?php if (!empty($produk_preview)): ?>
    <section id="katalog" class="py-16 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
            <div>
                <span class="text-xs text-orange-600 font-bold uppercase tracking-wider">Katalog Unggulan</span>
                <h2 class="font-headline font-bold text-2xl text-slate-900 tracking-tight mt-1">Produk Siap Dipesan di Toko Online</h2>
            </div>
            <a href="pembeli.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-orange-600 hover:text-orange-700 transition">
                <span>Lihat Semua Produk (<?= $total_produk ?> item)</span>
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
            <?php foreach ($produk_preview as $p): ?>
                <div class="bg-white rounded-2xl border border-slate-200/90 p-4 flex flex-col justify-between hover:border-orange-300 hover:shadow-md transition">
                    <div>
                        <div class="h-28 rounded-xl bg-orange-50/70 border border-orange-100 flex items-center justify-center text-orange-400 mb-3">
                            <span class="material-symbols-outlined text-[40px]">inventory_2</span>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                            <?= htmlspecialchars($p['kategori'] ?? 'Umum') ?>
                        </span>
                        <h4 class="font-headline font-bold text-xs sm:text-sm text-slate-900 mt-2 line-clamp-1">
                            <?= htmlspecialchars($p['nama_produk']) ?>
                        </h4>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                        <span class="font-headline font-bold text-xs sm:text-sm text-orange-600">
                            <?= formatRupiah((float)$p['harga']) ?>
                        </span>
                        <a href="pembeli.php" class="p-1.5 rounded-lg bg-orange-50 text-orange-600 hover:bg-orange-600 hover:text-white transition" title="Beli di Toko Online">
                            <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="mt-auto bg-white border-t border-slate-200 py-10">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-orange-600 text-white flex items-center justify-center font-bold">
                        <span class="material-symbols-outlined text-[18px]">storefront</span>
                    </div>
                    <div>
                        <span class="font-headline font-bold text-sm text-slate-900 block leading-tight">GopellMart</span>
                        <span class="text-[10px] text-slate-400">Ekosistem POS Ritel &amp; Toko Online Non-Tunai</span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-6 text-xs text-slate-500 font-semibold">
                    <a href="welcome.php" class="hover:text-orange-600 transition">Halaman Welcome</a>
                    <a href="pembeli.php" class="hover:text-orange-600 transition">Toko Online Pembeli</a>
                    <a href="login.php" class="hover:text-orange-600 transition">Login Petugas</a>
                    <a href="kasir.php" class="hover:text-orange-600 transition">Kasir POS</a>
                    <a href="index.php" class="hover:text-orange-600 transition">Dashboard Admin</a>
                </div>

                <div class="text-[11px] text-slate-400">
                    &copy; <?= date('Y') ?> GopellMart POS Engine &bull; All Rights Reserved.
                </div>

            </div>
        </div>
    </footer>

</body>
</html>
