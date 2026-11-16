<?php
/**
 * Modern Clean Layout Header
 * Stitch Design System: TokoKu POS Retail Engine
 */
if (!isset($page_title)) {
    $page_title = 'Dashboard';
}
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-[#f8f9ff]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> &mdash; TokoKu POS</title>
    
    <!-- Google Fonts: Plus Jakarta Sans & Inter & Material Symbols Outlined -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Tailwind CSS CDN with TokoKu Stitch Config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#9d4300",
                        "primary-container": "#f97316",
                        "on-primary": "#ffffff",
                        "on-primary-container": "#582200",
                        "primary-fixed": "#ffdbca",
                        "primary-fixed-dim": "#ffb690",
                        "on-primary-fixed": "#341100",
                        "on-primary-fixed-variant": "#783200",
                        "secondary": "#565e74",
                        "secondary-container": "#dae2fd",
                        "on-secondary": "#ffffff",
                        "on-secondary-container": "#5c647a",
                        "secondary-fixed": "#dae2fd",
                        "secondary-fixed-dim": "#bec6e0",
                        "on-secondary-fixed": "#131b2e",
                        "on-secondary-fixed-variant": "#3f465c",
                        "tertiary": "#006c49",
                        "tertiary-container": "#00b07a",
                        "on-tertiary": "#ffffff",
                        "on-tertiary-container": "#003b26",
                        "tertiary-fixed": "#6ffbbe",
                        "tertiary-fixed-dim": "#4edea3",
                        "on-tertiary-fixed": "#002113",
                        "on-tertiary-fixed-variant": "#005236",
                        "surface": "#f8f9ff",
                        "surface-dim": "#cbdbf5",
                        "surface-bright": "#f8f9ff",
                        "surface-variant": "#d3e4fe",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-low": "#eff4ff",
                        "surface-container": "#e5eeff",
                        "surface-container-high": "#dce9ff",
                        "surface-container-highest": "#d3e4fe",
                        "on-surface": "#0b1c30",
                        "on-surface-variant": "#584237",
                        "inverse-surface": "#213145",
                        "inverse-on-surface": "#eaf1ff",
                        "error": "#ba1a1a",
                        "error-container": "#ffdad6",
                        "on-error": "#ffffff",
                        "on-error-container": "#93000a",
                        "outline": "#8c7164",
                        "outline-variant": "#e0c0b1",
                        "background": "#f8f9ff",
                        "on-background": "#0b1c30"
                    },
                    fontFamily: {
                        sans: ['"Inter"', 'system-ui', 'sans-serif'],
                        headline: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                        body: ['"Inter"', 'system-ui', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <style>
        body { 
            font-family: 'Inter', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        h1, h2, h3, h4, h5, h6, .font-headline {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .material-symbols-outlined.fill-icon {
            font-variation-settings: 'FILL' 1;
        }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="h-full flex flex-col md:flex-row text-on-surface antialiased bg-surface selection:bg-primary-container selection:text-white">

    <!-- Mobile Top Navigation Bar -->
    <header class="md:hidden bg-surface-container-lowest border-b border-outline-variant/40 px-4 py-3 flex items-center justify-between sticky top-0 z-40">
        <div class="flex items-center space-x-2.5">
            <div class="w-8 h-8 rounded-lg bg-primary-container flex items-center justify-center text-white shadow-sm">
                <span class="material-symbols-outlined text-[20px]">storefront</span>
            </div>
            <div>
                <span class="font-headline font-bold text-sm tracking-tight text-on-surface block leading-tight">TokoKu</span>
                <span class="text-[10px] text-primary font-semibold uppercase tracking-wider">POS Retail</span>
            </div>
        </div>
        <button id="mobileMenuBtn" aria-label="Menu" class="p-2 rounded-lg text-secondary hover:text-on-surface hover:bg-surface-container transition">
            <span class="material-symbols-outlined text-[22px]">menu</span>
        </button>
    </header>

    <!-- Backdrop for Mobile Navigation -->
    <div id="sidebarBackdrop" class="fixed inset-0 bg-slate-900/30 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>
