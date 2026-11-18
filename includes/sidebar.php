<?php
/**
 * Modern Clean Sidebar
 * Stitch Design System: GopellMart POS Retail Engine
 */
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
}
?>
<aside id="mainSidebar" class="fixed inset-y-0 left-0 z-50 w-[250px] bg-surface-container-lowest border-r border-outline-variant/40 text-on-surface flex flex-col justify-between transition-transform duration-200 transform -translate-x-full md:translate-x-0 md:static md:inset-auto md:h-screen md:sticky md:top-0 shadow-[0_1px_8px_rgba(0,0,0,0.02)]">
    
    <!-- Top Branding & Navigation -->
    <div class="flex-1 overflow-y-auto">
        <!-- Brand Header -->
        <div class="px-5 pt-5 pb-4 border-b border-outline-variant/30 flex items-center justify-between">
            <a href="welcome.php" class="flex items-center gap-2.5 group" title="Buka Halaman Utama / Welcome">
                <div class="w-9 h-9 rounded-xl bg-primary-container flex items-center justify-center text-white shadow-sm shadow-primary-container/20 group-hover:scale-105 transition-transform">
                    <span class="material-symbols-outlined text-[20px]">storefront</span>
                </div>
                <div class="flex flex-col leading-none">
                    <span class="font-headline font-bold text-base text-on-surface tracking-tight group-hover:text-primary transition">GopellMart</span>
                    <span class="text-[10px] text-primary tracking-wider uppercase font-semibold mt-0.5">POS Retail</span>
                </div>
            </a>
            <div class="flex items-center gap-1.5">
                <span class="text-[10px] bg-primary-fixed text-on-primary-fixed font-bold px-1.5 py-0.5 rounded">PRO</span>
                <!-- Mobile Close Button -->
                <button id="closeSidebarBtn" class="md:hidden text-secondary hover:text-on-surface p-1 rounded-lg hover:bg-surface-container transition">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex flex-col gap-1 px-3 mt-3">
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Kasir'): ?>
                <!-- MENU KHUSUS KASIR -->
                <div class="px-2 py-1 mb-1 flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-teal-600 block">Workspace Kasir</span>
                    <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-teal-50 text-teal-700 border border-teal-200">KASIR</span>
                </div>

                <!-- 1. Kasir / Melayani Penjual & Pembeli -->
                <a href="kasir.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'kasir') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'kasir') ? 'text-white' : 'text-secondary' ?>">point_of_sale</span>
                        <span>Terminal Kasir POS</span>
                    </div>
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?= ($current_page === 'kasir') ? 'bg-white/20 text-white' : 'bg-primary-fixed/30 text-primary' ?>">Layani</span>
                </a>

                <!-- 2. Riwayat Pembelian -->
                <a href="riwayat.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'riwayat') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'riwayat') ? 'text-white' : 'text-secondary' ?>">receipt_long</span>
                        <span>Riwayat Pembelian</span>
                    </div>
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?= ($current_page === 'riwayat') ? 'bg-white/20 text-white' : 'bg-surface-container text-secondary' ?>">Struk</span>
                </a>

                <!-- 3. Tambah Pelanggan / Member RFID -->
                <a href="pelanggan.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'pelanggan') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'pelanggan') ? 'text-white' : 'text-secondary' ?>">badge</span>
                        <span>Tambah Member / RFID</span>
                    </div>
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?= ($current_page === 'pelanggan') ? 'bg-white/20 text-white' : 'bg-surface-container text-secondary' ?>">Member</span>
                </a>

            <?php else: ?>
                <!-- MENU KHUSUS ADMINISTRATOR (Default jika Admin atau belum login) -->
                <div class="px-2 py-1 mb-1 flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-orange-700 block">Workspace Admin</span>
                    <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-orange-50 text-orange-700 border border-orange-200">ADMIN</span>
                </div>

                <!-- 1. Dashboard Eksekutif -->
                <a href="index.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'index') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'index') ? 'text-white' : 'text-secondary' ?>">grid_view</span>
                        <span>Dashboard</span>
                    </div>
                </a>

                <!-- 2. CRUD Produk & Memantau Stok -->
                <a href="produk.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'produk') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'produk') ? 'text-white' : 'text-secondary' ?>">inventory_2</span>
                        <span>Kelola & Pantau Stok</span>
                    </div>
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?= ($current_page === 'produk') ? 'bg-white/20 text-white' : 'bg-surface-container text-secondary' ?>">CRUD</span>
                </a>

                <!-- 3. Laporan Penjualan -->
                <a href="laporan.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'laporan') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'laporan') ? 'text-white' : 'text-secondary' ?>">analytics</span>
                        <span>Laporan Penjualan</span>
                    </div>
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?= ($current_page === 'laporan') ? 'bg-white/20 text-white' : 'bg-surface-container text-secondary' ?>">Multi-JOIN</span>
                </a>

                <!-- 4. Data Pelanggan & Member -->
                <a href="pelanggan.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'pelanggan') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'pelanggan') ? 'text-white' : 'text-secondary' ?>">group</span>
                        <span>Pelanggan & Member</span>
                    </div>
                </a>

                <!-- 5. Fitur Admin Khusus: Manajemen Akun Kasir & User -->
                <a href="users.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'users') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'users') ? 'text-white' : 'text-secondary' ?>">manage_accounts</span>
                        <span>Manajemen Kasir</span>
                    </div>
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?= ($current_page === 'users') ? 'bg-white/20 text-white' : 'bg-orange-100 text-orange-800' ?>">Petugas</span>
                </a>

                <!-- 6. Audit Log Database Triggers -->
                <a href="audit_log.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'audit_log') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'audit_log') ? 'text-white' : 'text-secondary' ?>">security</span>
                        <span>Audit Log Security</span>
                    </div>
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?= ($current_page === 'audit_log') ? 'bg-white/20 text-white' : 'bg-surface-container text-on-surface-variant' ?>">Trigger</span>
                </a>

                <div class="pt-2 mt-2 border-t border-outline-variant/20">
                    <span class="px-2 text-[10px] font-bold uppercase tracking-wider text-secondary/70 mb-1 block">Akses Kasir</span>
                    <a href="kasir.php" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-semibold text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition">
                        <div class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-[18px] text-teal-600">point_of_sale</span>
                            <span>Buka Terminal Kasir</span>
                        </div>
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-surface-container text-secondary">POS</span>
                    </a>
                </div>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Active User Profile & Logout Button -->
    <div class="p-3">
        <?php if (!empty($_SESSION['username'])): ?>
            <div class="p-2.5 rounded-xl bg-surface-container-low border border-outline-variant/30 flex items-center justify-between gap-2 shadow-2xs">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="w-8 h-8 rounded-lg <?= ($_SESSION['role'] ?? '') === 'Admin' ? 'bg-primary-container text-white' : 'bg-tertiary text-white' ?> flex items-center justify-center font-bold text-xs shrink-0 font-headline">
                        <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                    </div>
                    <div class="min-w-0">
                        <span class="font-bold text-xs text-on-surface block truncate"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username']) ?></span>
                        <span class="text-[10px] font-semibold text-secondary block"><?= htmlspecialchars($_SESSION['role'] ?? 'Petugas') ?></span>
                    </div>
                </div>
                <a href="logout.php" class="p-1.5 rounded-lg text-secondary hover:text-error hover:bg-rose-50 transition" title="Logout dari POS">
                    <span class="material-symbols-outlined text-[18px]">logout</span>
                </a>
            </div>
        <?php else: ?>
            <a href="login.php" class="w-full py-2 px-3 rounded-xl bg-surface-container-low hover:bg-surface-container border border-outline-variant/30 flex items-center justify-between text-xs font-bold text-on-surface transition shadow-2xs">
                <span class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-primary">login</span>
                    <span>Login Petugas</span>
                </span>
                <span class="material-symbols-outlined text-[16px] text-secondary">arrow_forward</span>
            </a>
        <?php endif; ?>
    </div>
</aside>
