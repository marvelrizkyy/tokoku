<?php
/**
 * Nota & Struk Transaksi Digital (Print-Ready)
 * Stitch Design System: TokoKu POS Retail Engine
 * Sistem Informasi Penjualan & POS (toko_ku)
 */
require_once __DIR__ . '/config/db.php';

// Ambil ID transaksi dari parameter URL, jika tidak ada ambil transaksi terbaru
$id_transaksi = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$auto_print   = isset($_GET['print']) && $_GET['print'] == '1';

$transaksi = null;
$detail_items = [];
$error_message = null;

if ($pdo) {
    try {
        // Jika ID belum diset atau 0, ambil transaksi paling baru
        if ($id_transaksi <= 0) {
            $id_transaksi = (int)$pdo->query("SELECT MAX(id_transaksi) FROM tbl_transaksi")->fetchColumn();
        }

        if ($id_transaksi > 0) {
            // Ambil Data Header Transaksi & Pelanggan
            $stmtTx = $pdo->prepare("
                SELECT 
                    t.*,
                    p.nama_pelanggan,
                    p.kota
                FROM tbl_transaksi t
                LEFT JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
                WHERE t.id_transaksi = ?
            ");
            $stmtTx->execute([$id_transaksi]);
            $transaksi = $stmtTx->fetch();
            $stmtTx->closeCursor();

            if ($transaksi) {
                // Ambil Detail Item Transaksi
                $stmtItems = $pdo->prepare("
                    SELECT 
                        id_detail,
                        nama_produk,
                        jumlah,
                        harga,
                        (jumlah * harga) AS subtotal
                    FROM tbl_detail_transaksi
                    WHERE id_transaksi = ?
                    ORDER BY id_detail ASC
                ");
                $stmtItems->execute([$id_transaksi]);
                $detail_items = $stmtItems->fetchAll();
                $stmtItems->closeCursor();

                // Jika detail items kosong, buat fallback 1 baris sesuai total_belanja
                if (empty($detail_items)) {
                    $detail_items[] = [
                        'id_detail'   => 0,
                        'nama_produk' => 'Penjualan Produk',
                        'jumlah'      => 1,
                        'harga'       => $transaksi['total_belanja'],
                        'subtotal'    => $transaksi['total_belanja']
                    ];
                }

                // Kalkulasi Subtotal Barang
                $subtotal_barang = 0;
                foreach ($detail_items as $di) {
                    $subtotal_barang += (float)$di['subtotal'];
                }

                // Ambil data pembayaran dan rincian pajak/diskon
                // Prioritas: parameter GET (dari proses checkout kasir) -> kolom tabel transaksi -> fallback estimasi
                $nominal_bayar     = isset($_GET['bayar']) && is_numeric($_GET['bayar']) ? (float)$_GET['bayar'] : (float)($transaksi['nominal_bayar'] ?? 0);
                $kembalian         = isset($_GET['kembalian']) && is_numeric($_GET['kembalian']) ? (float)$_GET['kembalian'] : (float)($transaksi['kembalian'] ?? 0);
                $nominal_pajak     = isset($_GET['pajak']) && is_numeric($_GET['pajak']) ? (float)$_GET['pajak'] : (float)($transaksi['nominal_pajak'] ?? 0);
                $nominal_diskon    = isset($_GET['diskon']) && is_numeric($_GET['diskon']) ? (float)$_GET['diskon'] : (float)($transaksi['nominal_diskon'] ?? 0);
                $diskon_persen     = isset($_GET['diskon_persen']) && is_numeric($_GET['diskon_persen']) ? (float)$_GET['diskon_persen'] : (float)($transaksi['diskon_persen'] ?? 0);
                
                $raw_metode        = !empty($_GET['metode']) ? trim($_GET['metode']) : (!empty($transaksi['metode_pembayaran']) ? trim($transaksi['metode_pembayaran']) : 'Tunai');
                if (strcasecmp($raw_metode, 'QRIS') === 0) {
                    $metode_pembayaran = 'QRIS';
                } elseif (stripos($raw_metode, 'transfer') !== false || stripos($raw_metode, 'debit') !== false) {
                    $metode_pembayaran = 'Transfer / Debit';
                } else {
                    $metode_pembayaran = 'Tunai';
                }

                $total_belanja = (float)$transaksi['total_belanja'];

                // Fallback cerdas jika data transaksi belum memiliki kolom rincian:
                if ($nominal_pajak <= 0 && $nominal_diskon <= 0 && $subtotal_barang > 0) {
                    $selisih = $total_belanja - $subtotal_barang;
                    if ($selisih > 0) {
                        $nominal_pajak = $selisih; // PPN 11%
                    } elseif ($selisih < 0) {
                        $nominal_diskon = abs($selisih);
                    }
                }

                // Jika nominal bayar masih 0 atau belum tercatat, anggap bayar pas (LUNAS)
                if ($nominal_bayar <= 0) {
                    $nominal_bayar = $total_belanja;
                    $kembalian = 0;
                } else if ($nominal_bayar >= $total_belanja && $kembalian <= 0) {
                    $kembalian = $nominal_bayar - $total_belanja;
                }
            } else {
                $error_message = "Faktur transaksi #TX-" . str_pad($id_transaksi, 4, '0', STR_PAD_LEFT) . " tidak ditemukan.";
            }
        } else {
            $error_message = "Belum ada transaksi di database untuk dicetak.";
        }
    } catch (PDOException $e) {
        $error_message = "Gagal memuat data nota: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Transaksi #TX-<?= str_pad($id_transaksi, 4, '0', STR_PAD_LEFT) ?> - GopellMart</title>
    
    <!-- Google Fonts: Plus Jakarta Sans & Inter & Material Symbols Outlined -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#9d4300',
                        'primary-container': '#f97316',
                        tertiary: '#006c49',
                        surface: '#f8f9ff',
                        'on-surface': '#0b1c30'
                    },
                    fontFamily: {
                        headline: ['"Plus Jakarta Sans"', 'sans-serif'],
                        body: ['"Inter"', 'sans-serif'],
                        mono: ['"Space Mono"', 'monospace']
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background-color: #f8f9ff;
            color: #0b1c30;
        }
        .font-headline {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
        }
        .font-mono-num {
            font-family: 'Space Mono', monospace;
        }

        /* PRINT STYLES */
        @media print {
            .no-print, nav, header, aside, .action-bar {
                display: none !important;
            }

            body {
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .print-area {
                width: 100% !important;
                max-width: 80mm !important;
                margin: 0 auto !important;
                padding: 5mm !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                background: #ffffff !important;
            }

            @page {
                size: portrait;
                margin: 3mm;
            }
        }
    </style>
</head>
<body class="min-h-screen py-8 px-4 flex flex-col items-center justify-start">

    <!-- ACTION CONTROL BAR (Hidden Saat Print) -->
    <div class="no-print w-full max-w-sm mb-5 space-y-3">
        <?php if ($auto_print): ?>
            <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-900 rounded-xl text-xs flex items-center space-x-2 shadow-sm">
                <span class="material-symbols-outlined text-emerald-600 text-[18px]">check_circle</span>
                <span class="font-medium">Transaksi berhasil diproses &bull; Membuka dialog cetak...</span>
            </div>
        <?php endif; ?>

        <!-- Top Row Actions -->
        <div class="flex items-center justify-between">
            <a href="kasir.php" class="inline-flex items-center space-x-1.5 px-3 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-semibold rounded-xl shadow-sm transition">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                <span>Kembali ke Kasir</span>
            </a>

            <!-- Tombol Cetak Utama -->
            <button type="button" id="btnPrintNow" onclick="executePrint()" 
                    class="inline-flex items-center space-x-2 px-4 py-2 bg-primary-container hover:bg-primary text-white text-xs font-bold rounded-xl shadow-md transition cursor-pointer active:scale-95">
                <span class="material-symbols-outlined text-[18px]">print</span>
                <span>Cetak Nota</span>
            </button>
        </div>
    </div>

    <!-- ERROR STATE IF TRANSACTION NOT FOUND -->
    <?php if (!empty($error_message) || !$transaksi): ?>
        <div class="w-full max-w-sm p-6 bg-white rounded-2xl border border-slate-200 text-center shadow-sm">
            <div class="w-12 h-12 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-3">
                <span class="material-symbols-outlined text-[24px]">warning</span>
            </div>
            <h2 class="text-sm font-bold text-slate-900 font-headline">Nota Belum Tersedia</h2>
            <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($error_message ?? 'Data transaksi tidak ditemukan.') ?></p>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-center space-x-3">
                <a href="kasir.php" class="px-3 py-1.5 bg-primary-container text-white text-xs font-semibold rounded-lg">Buat Transaksi</a>
                <a href="laporan.php" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-semibold rounded-lg">Buka Laporan</a>
            </div>
        </div>
    <?php else: ?>

        <!-- NOTA STRUK FISIK (Container Cetak) -->
        <div id="receiptCard" class="print-area w-full max-w-sm bg-white rounded-2xl border border-slate-200/90 shadow-md p-6 text-xs text-slate-800 relative">
            
            <!-- Stempel Status LUNAS -->
            <div class="absolute right-6 top-8 pointer-events-none select-none opacity-25 transform rotate-12 border-2 border-emerald-600 rounded px-2 py-0.5 text-[10px] font-black uppercase text-emerald-600 tracking-wider">
                LUNAS
            </div>

            <!-- HEADER TOKO -->
            <div class="text-center pb-3 border-b border-dashed border-slate-300">
                <div class="w-9 h-9 rounded-xl bg-primary-container text-white flex items-center justify-center mx-auto mb-2 text-sm font-bold shadow-sm">
                    <span class="material-symbols-outlined text-[20px]">storefront</span>
                </div>
                <h1 class="text-base font-extrabold tracking-tight text-slate-900 leading-tight font-headline">GopellMart</h1>
                <p class="text-[11px] text-primary font-semibold tracking-wider uppercase mt-0.5">Supermarket &amp; Retail POS</p>
                <p class="text-[10px] text-slate-400 mt-0.5">Jl. Diponegoro No. 45, Ponorogo &bull; Telp: (0352) 123456</p>
            </div>

            <!-- INFO NOTA & PELANGGAN -->
            <div class="py-3 border-b border-dashed border-slate-300 space-y-1.5 text-[11px]">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">No. Faktur:</span>
                    <span class="font-mono-num font-bold text-slate-900">#TX-<?= str_pad($transaksi['id_transaksi'], 4, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Waktu:</span>
                    <span class="font-mono-num text-slate-700"><?= date('d/m/Y', strtotime($transaksi['tanggal'])) ?> <?= date('H:i') ?> WIB</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Pelanggan:</span>
                    <span class="font-semibold text-slate-800"><?= htmlspecialchars($transaksi['nama_pelanggan'] ?? 'Pelanggan Umum') ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Kota:</span>
                    <span class="text-slate-700"><?= htmlspecialchars($transaksi['kota'] ?? '-') ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Kasir:</span>
                    <span class="text-slate-700">Kasir 1 (Wulan)</span>
                </div>
                <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                    <span class="text-slate-500 font-semibold">Metode Bayar:</span>
                    <span class="font-bold text-[11px] <?= ($metode_pembayaran === 'QRIS') ? 'text-primary font-mono' : (($metode_pembayaran === 'Transfer / Debit') ? 'text-blue-700 font-mono' : 'text-slate-900') ?>">
                        <?= htmlspecialchars(strtoupper($metode_pembayaran)) ?>
                    </span>
                </div>
            </div>

            <!-- DAFTAR ITEM BELANJA -->
            <div class="py-3 border-b border-dashed border-slate-300">
                <table class="w-full text-left text-[11px]">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-100 pb-1.5">
                            <th class="pb-1 font-normal">Item</th>
                            <th class="pb-1 text-center font-normal">Qty</th>
                            <th class="pb-1 text-right font-normal">Harga</th>
                            <th class="pb-1 text-right font-normal">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php 
                            $total_kalkulasi = 0;
                            foreach ($detail_items as $item): 
                                $total_kalkulasi += (float)$item['subtotal'];
                        ?>
                            <tr>
                                <td class="py-1.5 font-medium text-slate-800 pr-1">
                                    <?= htmlspecialchars($item['nama_produk']) ?>
                                </td>
                                <td class="py-1.5 text-center font-mono-num text-slate-600">
                                    <?= $item['jumlah'] ?>
                                </td>
                                <td class="py-1.5 text-right font-mono-num text-slate-600">
                                    <?= number_format($item['harga'], 0, ',', '.') ?>
                                </td>
                                <td class="py-1.5 text-right font-mono-num font-bold text-slate-900">
                                    <?= number_format($item['subtotal'], 0, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- RINGKASAN PEMBAYARAN -->
            <div class="py-3 border-b border-dashed border-slate-300 space-y-1.5 text-[11px]">
                <div class="flex items-center justify-between text-slate-600">
                    <span>Subtotal:</span>
                    <span class="font-mono-num font-medium"><?= formatRupiah($subtotal_barang) ?></span>
                </div>
                
                <?php if ($nominal_diskon > 0): ?>
                    <div class="flex items-center justify-between text-rose-600">
                        <span>Diskon <?= $diskon_persen > 0 ? "({$diskon_persen}%)" : '' ?>:</span>
                        <span class="font-mono-num font-medium">- <?= formatRupiah($nominal_diskon) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($nominal_pajak > 0): ?>
                    <div class="flex items-center justify-between text-slate-700">
                        <span>PPN (11%):</span>
                        <span class="font-mono-num font-medium text-primary font-semibold">+ <?= formatRupiah($nominal_pajak) ?></span>
                    </div>
                <?php endif; ?>

                <div class="flex items-baseline justify-between pt-2 border-t border-slate-200">
                    <span class="text-xs font-bold text-slate-900 uppercase font-headline">Total Akhir:</span>
                    <span class="text-base font-black text-primary-container font-mono-num"><?= formatRupiah($total_belanja) ?></span>
                </div>

                <!-- RINCIAN BAYAR SESUAI METODE PEMBAYARAN -->
                <div class="pt-2 border-t border-dashed border-slate-200 space-y-2 bg-slate-50/80 p-3 rounded-xl mt-1">
                    <?php if ($metode_pembayaran === 'QRIS'): ?>
                        <div class="flex items-center justify-between text-slate-800">
                            <span class="font-bold flex items-center gap-1.5 text-primary">
                                <span class="material-symbols-outlined text-[18px]">qr_code_2</span>
                                <span>Bayar (QRIS Digital):</span>
                            </span>
                            <span class="font-mono-num font-bold text-slate-900 text-xs"><?= formatRupiah($total_belanja) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-slate-600">
                            <span>Status Pembayaran:</span>
                            <span class="font-bold text-emerald-700 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                <span>LUNAS</span>
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-[10px] text-slate-400 pt-0.5 border-t border-slate-200/60">
                            <span>Ref / NMID:</span>
                            <span class="font-mono font-medium">QRIS-<?= str_pad($transaksi['id_transaksi'], 4, '0', STR_PAD_LEFT) ?>-<?= date('ymd') ?></span>
                        </div>
                    <?php elseif ($metode_pembayaran === 'Transfer / Debit'): ?>
                        <div class="flex items-center justify-between text-slate-800">
                            <span class="font-bold flex items-center gap-1.5 text-primary">
                                <span class="material-symbols-outlined text-[18px]">credit_card</span>
                                <span>Bayar (Transfer / Debit):</span>
                            </span>
                            <span class="font-mono-num font-bold text-slate-900 text-xs"><?= formatRupiah($total_belanja) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-slate-600">
                            <span>Status Transaksi:</span>
                            <span class="font-bold text-emerald-700 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                <span>LUNAS</span>
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-[10px] text-slate-400 pt-0.5 border-t border-slate-200/60">
                            <span>Clearing / Bank:</span>
                            <span class="font-mono font-medium">Electronic Clearing #TX<?= str_pad($transaksi['id_transaksi'], 4, '0', STR_PAD_LEFT) ?></span>
                        </div>
                    <?php else: ?>
                        <!-- Tunai -->
                        <div class="flex items-center justify-between text-slate-800">
                            <span class="font-bold flex items-center gap-1.5 text-primary">
                                <span class="material-symbols-outlined text-[18px]">payments</span>
                                <span>Bayar (Tunai):</span>
                            </span>
                            <span class="font-mono-num font-bold text-slate-900 text-xs"><?= formatRupiah($nominal_bayar) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-emerald-700 font-bold">
                            <span>Kembalian:</span>
                            <span class="font-mono-num font-black text-xs text-emerald-700"><?= formatRupiah($kembalian) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex items-center justify-between text-slate-600 pt-1 text-[10px]">
                    <span class="font-bold uppercase tracking-wider flex items-center gap-1">
                        <span>METODE:</span>
                        <span class="text-primary font-bold"><?= htmlspecialchars(strtoupper($metode_pembayaran)) ?></span>
                    </span>
                    <span class="inline-flex items-center space-x-1 text-emerald-700 font-bold">
                        <span class="material-symbols-outlined text-[14px]">check_circle</span>
                        <span>LUNAS</span>
                    </span>
                </div>
            </div>

            <!-- FOOTER STRUK -->
            <div class="pt-4 text-center space-y-1 text-slate-400 text-[10px]">
                <p class="font-bold text-slate-700">*** TERIMA KASIH ***</p>
                <p>Barang yang sudah dibeli tidak dapat ditukar</p>
                <p class="font-mono pt-0.5 text-[9px] text-slate-300">Simpan struk ini sebagai bukti pembayaran</p>
                
                <!-- Barcode visual decoration -->
                <div class="pt-2 flex justify-center opacity-60">
                    <div class="space-x-[2px] flex items-end h-6">
                        <span class="w-[2px] h-full bg-slate-800"></span>
                        <span class="w-[1px] h-full bg-slate-800"></span>
                        <span class="w-[3px] h-full bg-slate-800"></span>
                        <span class="w-[1px] h-full bg-slate-800"></span>
                        <span class="w-[2px] h-full bg-slate-800"></span>
                        <span class="w-[4px] h-full bg-slate-800"></span>
                        <span class="w-[1px] h-full bg-slate-800"></span>
                        <span class="w-[3px] h-full bg-slate-800"></span>
                        <span class="w-[2px] h-full bg-slate-800"></span>
                        <span class="w-[1px] h-full bg-slate-800"></span>
                        <span class="w-[3px] h-full bg-slate-800"></span>
                        <span class="w-[2px] h-full bg-slate-800"></span>
                        <span class="w-[4px] h-full bg-slate-800"></span>
                        <span class="w-[2px] h-full bg-slate-800"></span>
                        <span class="w-[1px] h-full bg-slate-800"></span>
                        <span class="w-[3px] h-full bg-slate-800"></span>
                    </div>
                </div>
                <p class="font-mono text-[9px] text-slate-400 mt-1">TX<?= str_pad($transaksi['id_transaksi'], 6, '0', STR_PAD_LEFT) ?></p>
            </div>

        </div>

    <?php endif; ?>

    <!-- JAVASCRIPT PRINT EXECUTION -->
    <script>
        function executePrint() {
            try {
                window.focus();
                window.print();
            } catch (err) {
                console.error("Print error:", err);
                alert("Petunjuk: Anda juga dapat menekan Ctrl + P pada keyboard untuk mencetak nota ini.");
            }
        }

        // Auto trigger print jika parameter ?print=1
        <?php if ($auto_print): ?>
        window.addEventListener('load', () => {
            setTimeout(executePrint, 350);
        });
        <?php endif; ?>
    </script>

</body>
</html>