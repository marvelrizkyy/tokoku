<?php
/**
 * Modern Clean Sidebar
 * Stitch Design System: TokoKu POS Retail Engine
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
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-primary-container flex items-center justify-center text-white shadow-sm shadow-primary-container/20">
                    <span class="material-symbols-outlined text-[20px]">storefront</span>
                </div>
                <div class="flex flex-col leading-none">
                    <span class="font-headline font-bold text-base text-on-surface tracking-tight">TokoKu</span>
                    <span class="text-[10px] text-primary tracking-wider uppercase font-semibold mt-0.5">POS Retail</span>
                </div>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="text-[10px] bg-primary-fixed text-on-primary-fixed font-bold px-1.5 py-0.5 rounded">PRO</span>
                <!-- Mobile Close Button -->
                <button id="closeSidebarBtn" class="md:hidden text-secondary hover:text-on-surface p-1 rounded-lg hover:bg-surface-container transition">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        </div>

        <!-- Store Branch Status Pill -->
        <div class="px-3.5 py-2.5 mx-3 my-3 bg-surface-container-low rounded-lg border border-outline-variant/20 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-tertiary-container animate-pulse"></span>
                <span class="text-xs text-on-surface font-semibold truncate">Cabang Ponorogo</span>
            </div>
            <span class="text-[10px] text-tertiary font-bold uppercase">Online</span>
        </div>

        <!-- Navigation Links -->
        <nav class="flex flex-col gap-1 px-3 mt-1">
            <span class="px-2 text-[10px] font-bold uppercase tracking-wider text-secondary/70 mb-1 block">Menu Utama</span>

            <!-- 1. Dashboard -->
            <a href="index.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'index') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                <div class="flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'index') ? 'text-white' : 'text-secondary' ?>">grid_view</span>
                    <span>Dashboard</span>
                </div>
            </a>

            <!-- 2. Kasir (POS) -->
            <a href="kasir.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'kasir') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                <div class="flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'kasir') ? 'text-white' : 'text-secondary' ?>">point_of_sale</span>
                    <span>Kasir / POS</span>
                </div>
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?= ($current_page === 'kasir') ? 'bg-white/20 text-white' : 'bg-inverse-surface text-inverse-on-surface' ?>">Quick</span>
            </a>

            <!-- 3. Produk -->
            <a href="produk.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'produk') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                <div class="flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'produk') ? 'text-white' : 'text-secondary' ?>">inventory_2</span>
                    <span>Kelola Produk</span>
                </div>
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?= ($current_page === 'produk') ? 'bg-white/20 text-white' : 'bg-surface-container text-secondary' ?>">Katalog</span>
            </a>

            <!-- 4. Pelanggan -->
            <a href="pelanggan.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'pelanggan') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                <div class="flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'pelanggan') ? 'text-white' : 'text-secondary' ?>">group</span>
                    <span>Pelanggan</span>
                </div>
            </a>

            <!-- 4. Laporan -->
            <a href="laporan.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'laporan') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                <div class="flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'laporan') ? 'text-white' : 'text-secondary' ?>">analytics</span>
                    <span>Laporan Multi-JOIN</span>
                </div>
            </a>

            <!-- 5. Audit Log -->
            <a href="audit_log.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-semibold transition-all <?= ($current_page === 'audit_log') ? 'bg-primary-container text-on-primary font-bold shadow-sm' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                <div class="flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-[19px] <?= ($current_page === 'audit_log') ? 'text-white' : 'text-secondary' ?>">security</span>
                    <span>Audit Log</span>
                </div>
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?= ($current_page === 'audit_log') ? 'bg-white/20 text-white' : 'bg-surface-container text-on-surface-variant' ?>">Trigger</span>
            </a>
        </nav>
    </div>

    <!-- Bottom Status Card (Minimalist) -->
    <div class="p-3 mx-3 mb-4 rounded-lg bg-surface-container-low border border-outline-variant/30 flex flex-col gap-2">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full <?= empty($db_error) ? 'bg-tertiary-container' : 'bg-error' ?>"></span>
                <span class="text-[11px] text-on-surface-variant font-medium"><?= empty($db_error) ? 'Database Terhubung' : 'Terputus' ?></span>
            </div>
            <span class="material-symbols-outlined text-secondary text-[16px] hover:text-on-surface cursor-pointer" title="Refreshed">sync</span>
        </div>
        <div class="flex items-center justify-between pt-1 border-t border-outline-variant/20">
            <span class="text-[10px] text-secondary">Sistem POS</span>
            <span class="text-[10px] font-mono text-on-surface font-bold">v2.4.0</span>
        </div>
    </div>
</aside>
