<?php
/**
 * Modern Clean Layout Footer (No Footer Bar)
 * Sistem Informasi Penjualan & POS (toko_ku)
 */
?>
    </main>
    <!-- End Main Content Area -->

    <!-- Global JavaScript Scripts -->
    <script>
        // Toggle Mobile Sidebar
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const closeSidebarBtn = document.getElementById('closeSidebarBtn');
        const mainSidebar = document.getElementById('mainSidebar');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');

        function openSidebar() {
            if (mainSidebar && sidebarBackdrop) {
                mainSidebar.classList.remove('-translate-x-full');
                sidebarBackdrop.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }

        function closeSidebar() {
            if (mainSidebar && sidebarBackdrop) {
                mainSidebar.classList.add('-translate-x-full');
                sidebarBackdrop.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', openSidebar);
        if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
        if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', closeSidebar);

        // Auto Dismiss Alert Helper
        function dismissAlert(el) {
            const alertBox = el.closest('[role="alert"]') || el.parentElement;
            if (alertBox) {
                alertBox.style.transition = "opacity 0.2s ease, transform 0.2s ease";
                alertBox.style.opacity = "0";
                alertBox.style.transform = "translateY(-6px)";
                setTimeout(() => alertBox.remove(), 200);
            }
        }
    </script>
</body>
</html>
