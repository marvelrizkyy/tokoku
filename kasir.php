<?php
/**
 * Kasir / Terminal POS - Multi-Item Shopping Cart
 * Stitch Design System: TokoKu POS Retail Engine
 * Sistem Informasi Penjualan & POS (toko_ku)
 * Mendukung pembelian multi-produk (banyak macam barang sekaligus)
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/duitku.php';
requireRole(['Kasir', 'Admin']);

$page_title = 'Point of Sale (Kasir)';
$current_page = 'kasir';

$success_message = null;
$error_trigger_message = null;
$error_general_message = null;

if (isset($_GET['error']) && $_GET['error'] === 'forbidden') {
    $error_general_message = "Akses Ditolak: Anda tidak memiliki izin untuk membuka halaman Administrator. Anda telah diarahkan ke Terminal Kasir.";
}
if (isset($_GET['msg']) && $_GET['msg'] === 'member_added') {
    $success_message = "Member baru berhasil didaftarkan dan dipilih di transaksi.";
}

// Handle Form Submission: Tambah Member Baru Langsung dari Kasir
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_pelanggan_kasir') {
    $nama_baru = sanitize($_POST['nama_pelanggan'] ?? '');
    $rfid_baru = sanitize($_POST['rfid_uid'] ?? '');
    $kota_baru = sanitize($_POST['kota'] ?? 'Ponorogo');

    if (empty($nama_baru)) {
        $error_general_message = "Nama member wajib diisi.";
    } else {
        try {
            if (empty($rfid_baru)) {
                $rfid_baru = 'RFID-' . rand(1000, 9999);
            }
            $stmtNP = $pdo->prepare("INSERT INTO tbl_pelanggan (nama_pelanggan, rfid_uid, kota) VALUES (?, ?, ?)");
            $stmtNP->execute([$nama_baru, $rfid_baru, $kota_baru]);
            $new_id = (int)$pdo->lastInsertId();

            if (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'id_pelanggan' => $new_id,
                    'nama_pelanggan' => $nama_baru,
                    'rfid_uid' => $rfid_baru,
                    'kota' => $kota_baru,
                    'message' => "Member '{$nama_baru}' ({$rfid_baru}) berhasil didaftarkan!"
                ]);
                exit;
            } else {
                header("Location: kasir.php?pelanggan_id={$new_id}&msg=member_added");
                exit;
            }
        } catch (Exception $eNP) {
            if (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $eNP->getMessage()]);
                exit;
            }
            $error_general_message = "Gagal menambah member: " . $eNP->getMessage();
        }
    }
}

// Ambil pelanggan terpilih dari URL jika ada
$selected_pelanggan_id = isset($_GET['pelanggan_id']) ? (int)$_GET['pelanggan_id'] : 0;

// Handle Form Submission POST (Multi-Item Checkout)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_transaksi') {
    $id_pelanggan      = (int)($_POST['id_pelanggan'] ?? 0);
    $diskon            = (float)($_POST['diskon'] ?? 0);
    $tanggal           = !empty($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');
    $hitung_pajak      = isset($_POST['termasuk_pajak']) && $_POST['termasuk_pajak'] === '1';
    $nominal_bayar     = (float)($_POST['nominal_bayar'] ?? 0);
    $raw_metode        = trim($_POST['metode_pembayaran'] ?? 'Tunai');
    $is_duitku         = strcasecmp($raw_metode, 'Duitku POP') === 0;
    $customer_email    = trim($_POST['customer_email'] ?? '');
    $customer_phone    = trim($_POST['customer_phone'] ?? '');
    if ($is_duitku) {
        $metode_pembayaran = 'Duitku POP';
    } elseif (strcasecmp($raw_metode, 'QRIS') === 0) {
        $metode_pembayaran = 'QRIS';
    } elseif (stripos($raw_metode, 'transfer') !== false || stripos($raw_metode, 'debit') !== false) {
        $metode_pembayaran = 'Transfer / Debit';
    } else {
        $metode_pembayaran = 'Tunai';
    }
    
    // Parse items dari JSON
    $items_raw    = $_POST['items_json'] ?? '[]';
    $cart_items   = json_decode($items_raw, true);

    if ($id_pelanggan <= 0) {
        $error_general_message = "Harap pilih Pelanggan terlebih dahulu.";
    } elseif ($is_duitku && !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        $error_general_message = "Masukkan email pelanggan yang valid untuk pembayaran Duitku.";
    } elseif (empty($cart_items) || !is_array($cart_items)) {
        $error_general_message = "Keranjang belanja masih kosong. Harap pilih minimal satu barang.";
    } else {
        try {
            // Transaction Control Language (TCL)
            $pdo->beginTransaction();

            $subtotal_kotor = 0;
            $validated_items = [];

            // 1. Validasi seluruh produk dan kuantitas stok
            foreach ($cart_items as $item) {
                $item_id  = (int)($item['id_produk'] ?? 0);
                $item_qty = (int)($item['jumlah'] ?? 0);

                if ($item_id <= 0 || $item_qty <= 0) {
                    continue;
                }

                $stmtProd = $pdo->prepare("SELECT id_produk, nama_produk, harga, stok FROM tbl_produk WHERE id_produk = ? FOR UPDATE");
                $stmtProd->execute([$item_id]);
                $prod = $stmtProd->fetch();
                $stmtProd->closeCursor();

                if (!$prod) {
                    throw new Exception("Produk dengan ID {$item_id} tidak ditemukan.");
                }

                if ($prod['stok'] < $item_qty) {
                    throw new Exception("Stok untuk '{$prod['nama_produk']}' tidak mencukupi (Tersedia: {$prod['stok']}, diminta: {$item_qty}).");
                }

                $harga = (float)$prod['harga'];
                $item_subtotal = $harga * $item_qty;
                $subtotal_kotor += $item_subtotal;

                $validated_items[] = [
                    'id_produk'   => $item_id,
                    'nama_produk' => $prod['nama_produk'],
                    'jumlah'      => $item_qty,
                    'harga'       => $harga,
                    'subtotal'    => $item_subtotal
                ];
            }

            if (empty($validated_items)) {
                throw new Exception("Tidak ada item yang valid untuk diproses.");
            }

            $merchant_order_id = null;
            if ($is_duitku) {
                $merchant_order_id = 'GK-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
                $stmtCustomer = $pdo->prepare("SELECT id_pelanggan, nama_pelanggan, rfid_uid FROM tbl_pelanggan WHERE id_pelanggan = ?");
                $stmtCustomer->execute([$id_pelanggan]);
                $customer = $stmtCustomer->fetch();
                $stmtCustomer->closeCursor();
                if (!$customer) {
                    throw new Exception("Data pelanggan tidak ditemukan.");
                }

                $stmtInsertTx = $pdo->prepare("
                    INSERT INTO tbl_transaksi (
                        tanggal, id_pelanggan, total_belanja, nominal_bayar, kembalian,
                        metode_pembayaran, merchant_order_id, payment_status, stock_processed
                    ) VALUES (?, ?, 0, 0, 0, 'Duitku POP', ?, 'UNPAID', 0)
                ");
                $stmtInsertTx->execute([$tanggal, $id_pelanggan, $merchant_order_id]);
                $id_transaksi_baru = (int)$pdo->lastInsertId();

                $stmtInsertDetail = $pdo->prepare("
                    INSERT INTO tbl_detail_transaksi (id_transaksi, id_produk, nama_produk, jumlah, harga)
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($validated_items as $item) {
                    $stmtInsertDetail->execute([
                        $id_transaksi_baru,
                        $item['id_produk'],
                        $item['nama_produk'],
                        $item['jumlah'],
                        $item['harga']
                    ]);
                }
            } else {
                // Stored procedure creates the transaction and decreases stock for the first item.
                $first_item = $validated_items[0];
                $stmtSP = $pdo->prepare("CALL TambahTransaksi(?, ?, ?, ?)");
                $stmtSP->execute([$tanggal, $id_pelanggan, $first_item['id_produk'], $first_item['jumlah']]);
                $stmtSP->closeCursor();

                $stmtId = $pdo->prepare("SELECT id_transaksi FROM tbl_transaksi WHERE id_pelanggan = ? ORDER BY id_transaksi DESC LIMIT 1");
                $stmtId->execute([$id_pelanggan]);
                $id_transaksi_baru = (int)$stmtId->fetchColumn();
                $stmtId->closeCursor();

                if ($id_transaksi_baru <= 0) {
                    $id_transaksi_baru = (int)$pdo->query("SELECT MAX(id_transaksi) FROM tbl_transaksi")->fetchColumn();
                }

                $total_count = count($validated_items);
                if ($total_count > 1) {
                    $stmtInsertDetail = $pdo->prepare("
                        INSERT INTO tbl_detail_transaksi (id_transaksi, id_produk, nama_produk, jumlah, harga)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmtUpdateStok = $pdo->prepare("UPDATE tbl_produk SET stok = stok - ? WHERE id_produk = ?");

                    for ($i = 1; $i < $total_count; $i++) {
                        $other_item = $validated_items[$i];
                        $stmtInsertDetail->execute([
                            $id_transaksi_baru,
                            $other_item['id_produk'],
                            $other_item['nama_produk'],
                            $other_item['jumlah'],
                            $other_item['harga']
                        ]);
                        $stmtInsertDetail->closeCursor();
                        $stmtUpdateStok->execute([$other_item['jumlah'], $other_item['id_produk']]);
                        $stmtUpdateStok->closeCursor();
                    }
                }
            }

            // 4. Hitung Diskon & Pajak (Stored Functions: HitungDiskon & HitungPajak)
            $nominal_diskon = 0;
            $nominal_pajak  = 0;

            if ($diskon > 0) {
                try {
                    $stmtFnD = $pdo->prepare("SELECT HitungDiskon(:subtotal, :diskon) AS nilai_diskon");
                    $stmtFnD->execute([':subtotal' => $subtotal_kotor, ':diskon' => $diskon]);
                    $nominal_diskon = (float)$stmtFnD->fetchColumn();
                    $stmtFnD->closeCursor();
                } catch (Exception $eD) {
                    $nominal_diskon = $subtotal_kotor * ($diskon / 100);
                }
            }
            $setelah_diskon = max(0, $subtotal_kotor - $nominal_diskon);

            if ($hitung_pajak) {
                try {
                    $stmtFnP = $pdo->prepare("SELECT HitungPajak(:setelah_diskon) AS nilai_pajak");
                    $stmtFnP->execute([':setelah_diskon' => $setelah_diskon]);
                    $nominal_pajak = (float)$stmtFnP->fetchColumn();
                    $stmtFnP->closeCursor();
                } catch (Exception $eP) {
                    $nominal_pajak = round($setelah_diskon * 0.11);
                }
            }

            $total_akhir = $setelah_diskon + $nominal_pajak;

            if ($is_duitku) {
                $total_akhir = (int)round($total_akhir);
                if ($total_akhir <= 0) {
                    throw new Exception("Total pembayaran Duitku harus lebih dari Rp 0.");
                }
                $nominal_bayar = 0;
                $kembalian = 0;
            } elseif ($metode_pembayaran !== 'Tunai') {
                // QRIS / transfer lokal are recorded as paid, matching the existing POS flow.
                $nominal_bayar = $total_akhir;
                $kembalian = 0;
            } else {
                // Jika tunai dan nominal bayar belum diisi atau kurang, default ke uang pas
                if ($nominal_bayar < $total_akhir) {
                    $nominal_bayar = $total_akhir;
                }
                $kembalian = max(0, $nominal_bayar - $total_akhir);
            }

            $payment_status = $is_duitku ? 'UNPAID' : 'PAID';
            $stock_processed = $is_duitku ? 0 : 1;
            $stmtUpdateTx = $pdo->prepare("
                UPDATE tbl_transaksi
                SET total_belanja = :total,
                    nominal_bayar = :bayar,
                    kembalian = :kembalian,
                    nominal_diskon = :diskon,
                    diskon_persen = :diskon_persen,
                    nominal_pajak = :pajak,
                    metode_pembayaran = :metode,
                    payment_status = :payment_status,
                    merchant_order_id = COALESCE(:merchant_order_id, merchant_order_id),
                    stock_processed = :stock_processed,
                    paid_at = CASE WHEN :payment_status_paid = 'PAID' THEN NOW() ELSE paid_at END
                WHERE id_transaksi = :id
            ");
            $stmtUpdateTx->execute([
                ':total' => $total_akhir,
                ':bayar' => $nominal_bayar,
                ':kembalian' => $kembalian,
                ':diskon' => $nominal_diskon,
                ':diskon_persen' => $diskon,
                ':pajak' => $nominal_pajak,
                ':metode' => $metode_pembayaran,
                ':payment_status' => $payment_status,
                ':merchant_order_id' => $merchant_order_id,
                ':stock_processed' => $stock_processed,
                ':payment_status_paid' => $payment_status,
                ':id' => $id_transaksi_baru,
            ]);
            $stmtUpdateTx->closeCursor();

            // Commit seluruh operasi multi-item
            $pdo->commit();

            if ($is_duitku) {
                $fullName = trim($customer['nama_pelanggan']);
                $nameParts = preg_split('/\s+/', $fullName, 2);
                $customerDetail = [
                    'firstName' => $nameParts[0] ?? 'Pelanggan',
                    'lastName' => $nameParts[1] ?? '',
                    'email' => $customer_email,
                    'merchantCustomerId' => (string)$id_pelanggan,
                ];
                if ($customer_phone !== '') {
                    $customerDetail['phoneNumber'] = $customer_phone;
                }

                try {
                    $invoiceParams = [
                        'paymentAmount' => $total_akhir,
                        'merchantOrderId' => $merchant_order_id,
                        'productDetails' => 'Transaksi Kasir TokoKu ' . $merchant_order_id,
                        'additionalParam' => 'tx=' . $id_transaksi_baru,
                        'merchantUserInfo' => $customer['rfid_uid'] ?? ('member-' . $id_pelanggan),
                        'paymentMethod' => '',
                        'customerVaName' => substr($fullName, 0, 20),
                        'email' => $customer_email,
                        'itemDetails' => [[
                            'name' => 'Transaksi Kasir',
                            'price' => $total_akhir,
                            'quantity' => 1,
                        ]],
                        'customerDetail' => $customerDetail,
                        'callbackUrl' => duitkuCallbackUrl(),
                        'returnUrl' => duitkuReturnUrl($merchant_order_id),
                        'expiryPeriod' => 60,
                    ];
                    if ($customer_phone !== '') {
                        $invoiceParams['phoneNumber'] = $customer_phone;
                    }
                    $invoice = duitkuCreateInvoice($invoiceParams);
                } catch (Exception $invoiceError) {
                    $failureMessage = 'Gagal membuat pembayaran Duitku: ' . $invoiceError->getMessage();
                    $stmtFailInvoice = $pdo->prepare("
                        UPDATE tbl_transaksi
                        SET payment_status = 'FAILED', payment_result_code = 'CREATE_ERR'
                        WHERE id_transaksi = ?
                    ");
                    $stmtFailInvoice->execute([$id_transaksi_baru]);
                    throw new Exception($failureMessage, 0, $invoiceError);
                }

                $stmtSaveInvoice = $pdo->prepare("
                    UPDATE tbl_transaksi
                    SET duitku_reference = ?, duitku_payment_url = ?
                    WHERE id_transaksi = ?
                ");
                $stmtSaveInvoice->execute([
                    $invoice['reference'],
                    $invoice['paymentUrl'],
                    $id_transaksi_baru,
                ]);
                header('Location: ' . $invoice['paymentUrl']);
                exit;
            }

            // Alihkan langsung ke nota cetak siap print dengan parameter lengkap
            $redirect_url = "nota.php?id={$id_transaksi_baru}&print=1"
                . "&metode=" . urlencode($metode_pembayaran)
                . "&bayar=" . urlencode($nominal_bayar)
                . "&kembalian=" . urlencode($kembalian)
                . "&pajak=" . urlencode($nominal_pajak)
                . "&diskon=" . urlencode($nominal_diskon)
                . "&diskon_persen=" . urlencode($diskon);
            header("Location: {$redirect_url}");
            exit;

        } catch (PDOException $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $isTrigger45000 = ($e->getCode() === '45000' || 
                               strpos($e->getMessage(), '45000') !== false || 
                               strpos($e->getMessage(), 'Total belanja tidak boleh negatif') !== false);

            if ($isTrigger45000) {
                $error_trigger_message = [
                    'code'    => 'SQLSTATE 45000',
                    'trigger' => 'before_insert_transaksi',
                    'message' => 'Total belanja tidak boleh negatif (Trigger trg_validate_transaksi_total active)',
                    'detail'  => $e->getMessage()
                ];
            } else {
                $error_general_message = $e->getMessage();
            }
        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_general_message = $e->getMessage();
        }
    }
}

// Data Master
$daftar_pelanggan = [];
$daftar_produk = [];

if ($pdo) {
    try {
        $daftar_pelanggan = $pdo->query("SELECT id_pelanggan, nama_pelanggan, rfid_uid, kota FROM tbl_pelanggan ORDER BY nama_pelanggan ASC")->fetchAll();
        foreach ($daftar_pelanggan as &$cust) {
            if (empty($cust['rfid_uid'])) {
                $cust['rfid_uid'] = 'RFID-' . (1000 + $cust['id_pelanggan']);
            }
        }
        unset($cust);

        $stmtProd = $pdo->query("
            SELECT p.id_produk, p.barcode, p.nama_produk, p.harga, p.stok, k.nama_kategori 
            FROM tbl_produk p 
            LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori 
            ORDER BY p.nama_produk ASC
        ");
        $daftar_produk = $stmtProd->fetchAll();
        foreach ($daftar_produk as &$dp) {
            if (empty($dp['barcode'])) {
                $dp['barcode'] = 'PRD' . str_pad($dp['id_produk'], 5, '0', STR_PAD_LEFT);
            }
        }
        unset($dp);

    } catch (PDOException $e) {
        // Heningkan error setup master
    }
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
                <span class="material-symbols-outlined text-primary-container text-[24px]">point_of_sale</span>
                Point of Sale (Kasir Multi-Item)
            </h1>
            <p class="text-xs text-secondary mt-0.5"><?= formatTanggal(date('Y-m-d')) ?> &bull; Bisa memilih & membeli beberapa macam barang sekaligus</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-inverse-surface text-inverse-on-surface text-xs font-semibold">
                <span class="material-symbols-outlined text-[16px] text-tertiary-fixed-dim">terminal</span>
                <span>POS Terminal #01</span>
            </div>
        </div>
    </header>

    <!-- Page Body -->
    <div class="p-4 sm:p-6 lg:p-8 space-y-6 flex-1">

        <!-- Trigger SQL Error Toast (Stitch sql-error-toast) -->
        <?php if (!empty($error_trigger_message)): ?>
            <div id="sql-error-toast" class="w-full bg-surface-container-lowest rounded-xl shadow-lg border-l-4 border-error p-4 flex items-start gap-3.5 transition-all">
                <div class="w-9 h-9 rounded-lg bg-error-container text-error flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">error</span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="text-xs sm:text-sm font-bold text-error tracking-tight font-headline">Gagal (<?= htmlspecialchars($error_trigger_message['code']) ?>)</h4>
                        <span class="text-[11px] text-secondary">Baru saja</span>
                    </div>
                    <p class="text-xs text-on-surface mt-1 leading-snug">
                        <?= htmlspecialchars($error_trigger_message['message']) ?>
                    </p>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-[11px] text-secondary font-mono">Trigger: <?= htmlspecialchars($error_trigger_message['trigger']) ?></span>
                        <span class="w-1 h-1 rounded-full bg-secondary"></span>
                        <button type="button" onclick="document.getElementById('sql-error-toast').style.display='none'" class="text-[11px] text-error font-semibold hover:underline">Tutup</button>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('sql-error-toast').style.display='none'" class="text-secondary hover:text-on-surface p-1">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_general_message)): ?>
            <div role="alert" class="p-4 rounded-xl bg-error-container text-on-error-container border border-error/20 flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-3">
                    <span class="material-symbols-outlined text-error text-[22px]">warning</span>
                    <p class="text-xs font-semibold"><?= htmlspecialchars($error_general_message) ?></p>
                </div>
                <button onclick="dismissAlert(this)" class="text-on-error-container/70 hover:text-on-error-container p-1">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Main POS Canvas 12-Column Split -->
        <div class="grid grid-cols-12 gap-6 items-start">

            <!-- LEFT COLUMN: Customer Selection & Product Catalog (col-span-12 xl:col-span-7) -->
            <div class="col-span-12 xl:col-span-7 flex flex-col gap-6">

                <!-- 1. Customer Selector Section Card -->
                <div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm border border-outline-variant/30">
                    <div class="flex items-center justify-between mb-3.5">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary-container text-[20px]">person_pin</span>
                            <label class="font-headline text-sm font-bold text-on-surface tracking-tight">Pilih Pelanggan Transaksi</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="riwayat.php" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface text-xs font-semibold transition-colors" title="Lihat Riwayat Transaksi Kasir">
                                <span class="material-symbols-outlined text-[16px] text-primary">receipt_long</span>
                                <span class="hidden sm:inline">Riwayat Nota</span>
                            </a>
                            <button type="button" onclick="openModalTambahMemberKasir()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary-container hover:bg-on-primary-fixed-variant text-white text-xs font-bold transition shadow-2xs active:scale-95">
                                <span class="material-symbols-outlined text-[16px]">person_add</span>
                                <span>+ Member Baru</span>
                            </button>
                        </div>
                    </div>

                    <!-- Dropdown & Tap Kartu RFID Pelanggan -->
                    <div class="p-3.5 rounded-xl bg-surface-container-low border border-outline-variant/20 flex flex-col gap-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary-container text-[18px]">contactless</span>
                            <span class="text-[11px] font-bold text-on-surface whitespace-nowrap">Tap Kartu RFID:</span>
                            <div class="relative flex-1">
                                <input type="text" id="rfidCardInput" placeholder="Tempel kartu RFID di sensor (Enter)..." 
                                       autocomplete="off"
                                       class="w-full h-8 pl-8 pr-14 text-xs font-mono font-bold bg-surface-container-lowest text-on-surface rounded-lg border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container shadow-2xs">
                                <span class="material-symbols-outlined absolute left-2.5 top-1.5 text-secondary text-[16px]">credit_card</span>
                                <span class="absolute right-2 top-1.5 px-1 py-0.2 rounded bg-surface-container text-[8px] font-bold text-secondary font-mono pointer-events-none">
                                    TAP RFID
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center font-bold text-sm text-primary font-headline" id="custAvatar">
                                ?
                            </div>
                            <div class="flex-1">
                                <select id="selectPelanggan" name="id_pelanggan_proxy" onchange="updatePelangganInfo(this)" 
                                        class="w-full h-10 px-3 bg-surface-container-lowest text-on-surface rounded-lg border border-outline-variant/40 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary-container/30">
                                    <option value="">-- Pilih Pelanggan Terdaftar --</option>
                                    <?php foreach ($daftar_pelanggan as $p): ?>
                                        <option value="<?= $p['id_pelanggan'] ?>" 
                                                data-nama="<?= htmlspecialchars($p['nama_pelanggan']) ?>"
                                                data-rfid="<?= htmlspecialchars($p['rfid_uid'] ?? '') ?>"
                                                data-kota="<?= htmlspecialchars($p['kota'] ?? 'Ponorogo') ?>"
                                                <?= ($selected_pelanggan_id === (int)$p['id_pelanggan']) ? 'selected' : '' ?>>
                                            #CUST-<?= str_pad($p['id_pelanggan'], 3, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($p['nama_pelanggan']) ?> [<?= htmlspecialchars($p['rfid_uid'] ?? '') ?>]
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Catalog Browsing & Filter Area -->
                <div class="bg-surface-container-lowest rounded-xl p-5 shadow-sm border border-outline-variant/30 flex flex-col gap-4">
                    
                    <!-- BARCODE & QR SCANNER BAR (Hardware Barcode Gun & Smartphone/Webcam Camera) -->
                    <div class="p-3.5 rounded-xl bg-surface-container-low border border-primary-container/20 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-2xs">
                        <div class="flex items-center gap-2.5 w-full sm:flex-1">
                            <div class="w-9 h-9 rounded-lg bg-primary-container/10 text-primary-container flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[22px]">barcode_scanner</span>
                            </div>
                            <div class="flex-1 relative">
                                <input type="text" id="barcodeScannerInput" placeholder="Scan Barcode / Kode SKU (Tekan Enter)..." 
                                       autocomplete="off"
                                       class="w-full h-9 pl-3 pr-16 bg-surface-container-lowest text-on-surface text-xs font-mono font-bold rounded-lg border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container placeholder:font-normal placeholder:font-body">
                                <span class="absolute right-2 top-2 px-1.5 py-0.5 rounded bg-surface-container text-[9px] font-bold text-secondary font-mono pointer-events-none">
                                    ENTER
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-auto shrink-0">
                            <button type="button" onclick="openCameraScannerModal()" 
                                    class="w-full sm:w-auto px-3.5 py-2 rounded-lg bg-primary-container hover:bg-on-primary-fixed-variant text-white text-xs font-bold shadow-xs transition flex items-center justify-center gap-1.5 active:scale-95">
                                <span class="material-symbols-outlined text-[16px]">photo_camera</span>
                                <span>Scan Kamera</span>
                            </button>
                        </div>
                    </div>

                    <!-- Search and Quick Category Filter -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <span class="material-symbols-outlined absolute left-3.5 top-2.5 text-secondary text-[20px]">search</span>
                            <input type="text" id="catalogSearch" oninput="filterCatalog()" 
                                   class="w-full h-10 pl-10 pr-4 bg-surface text-on-surface rounded-lg border border-outline-variant/40 text-xs placeholder:text-secondary/70 focus:outline-none focus:ring-2 focus:ring-primary-container/30 transition-all" 
                                   placeholder="Cari nama barang atau kode barcode...">
                        </div>
                    </div>

                    <!-- Category Pills -->
                    <div class="flex overflow-x-auto gap-2 pb-1 no-scrollbar text-xs">
                        <button type="button" onclick="filterCategory('ALL', this)" class="category-pill active px-3.5 py-1.5 rounded-lg bg-primary-container text-on-primary font-bold shadow-xs whitespace-nowrap">
                            Semua Produk
                        </button>
                        <?php 
                        $kategoriList = array_unique(array_filter(array_column($daftar_produk, 'nama_kategori')));
                        foreach ($kategoriList as $kat): ?>
                            <button type="button" onclick="filterCategory('<?= htmlspecialchars($kat) ?>', this)" class="category-pill px-3.5 py-1.5 rounded-lg bg-surface-container text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-colors font-medium whitespace-nowrap">
                                <?= htmlspecialchars($kat) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Product Grid Cards (Clicking adds to Multi-Item Cart) -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3.5 max-h-[550px] overflow-y-auto pr-1" id="productGrid">
                        <?php foreach ($daftar_produk as $prod): 
                            $isLowStock = $prod['stok'] < 20;
                            $isOutOfStock = $prod['stok'] <= 0;
                            $pBarcode = !empty($prod['barcode']) ? $prod['barcode'] : ('PRD' . str_pad($prod['id_produk'], 5, '0', STR_PAD_LEFT));
                        ?>
                            <div class="product-card bg-surface-container-low hover:bg-surface-container-lowest rounded-xl border <?= $isLowStock ? 'border-amber-300' : 'border-outline-variant/30' ?> p-3.5 flex flex-col justify-between transition-all duration-200 hover:shadow-md group"
                                 data-name="<?= strtolower(htmlspecialchars($prod['nama_produk'])) ?>"
                                 data-barcode="<?= strtolower(htmlspecialchars($pBarcode)) ?>"
                                 data-category="<?= htmlspecialchars($prod['nama_kategori'] ?? 'Lainnya') ?>">
                                
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-[10px] font-semibold text-secondary uppercase tracking-wider block">
                                        <?= htmlspecialchars($prod['nama_kategori'] ?? 'Umum') ?>
                                    </span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $isOutOfStock ? 'bg-rose-100 text-rose-800' : ($isLowStock ? 'bg-amber-100 text-amber-800' : 'bg-surface-container text-secondary') ?>">
                                        <?= $isOutOfStock ? 'Habis' : 'Stok: ' . $prod['stok'] ?>
                                    </span>
                                </div>

                                <div class="my-2.5">
                                    <h4 class="font-headline font-bold text-xs text-on-surface line-clamp-2 leading-snug group-hover:text-primary transition-colors">
                                        <?= htmlspecialchars($prod['nama_produk']) ?>
                                    </h4>
                                    <div class="flex items-center justify-between gap-1 mt-1.5 flex-wrap">
                                        <p class="font-bold text-sm text-primary-container font-headline">
                                            <?= formatRupiah($prod['harga']) ?>
                                        </p>
                                        <span class="font-mono text-[9px] font-bold text-secondary bg-surface-container px-1.5 py-0.5 rounded border border-outline-variant/30 flex items-center gap-0.5">
                                            <span class="material-symbols-outlined text-[11px]">barcode</span>
                                            <span><?= htmlspecialchars($pBarcode) ?></span>
                                        </span>
                                    </div>
                                </div>

                                <button type="button" 
                                        <?= $isOutOfStock ? 'disabled' : '' ?>
                                        onclick="addToCart(<?= $prod['id_produk'] ?>, '<?= htmlspecialchars(addslashes($prod['nama_produk'])) ?>', <?= (float)$prod['harga'] ?>, <?= (int)$prod['stok'] ?>)" 
                                        class="w-full py-2 px-3 rounded-lg <?= $isOutOfStock ? 'bg-slate-200 text-slate-400 cursor-not-allowed' : 'bg-surface-container hover:bg-primary-container hover:text-white text-on-surface active:scale-95 shadow-xs' ?> text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span>
                                    <span><?= $isOutOfStock ? 'Stok Habis' : '+ Masuk Keranjang' ?></span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN: Multi-Item Shopping Cart & Checkout (col-span-12 xl:col-span-5) -->
            <div class="col-span-12 xl:col-span-5">
                <div class="bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant/30 flex flex-col overflow-hidden sticky top-20">
                    
                    <!-- Cart Header -->
                    <div class="p-4 border-b border-outline-variant/30 flex items-center justify-between bg-surface-container-low">
                        <div class="flex items-center gap-2.5">
                            <span class="material-symbols-outlined text-primary-container text-[24px]">shopping_cart</span>
                            <div>
                                <h3 class="font-headline font-bold text-sm text-on-surface flex items-center gap-1.5">
                                    <span>Keranjang Belanja</span>
                                    <span class="text-[11px] font-bold px-2 py-0.2 rounded-full bg-primary-container text-white" id="cartCountBadge">0</span>
                                </h3>
                                <p class="text-[11px] text-secondary">Faktur Baru &bull; <?= date('d M Y') ?></p>
                            </div>
                        </div>
                        <button type="button" onclick="clearCart()" class="text-xs text-secondary hover:text-error transition flex items-center gap-1" title="Kosongkan Semua">
                            <span class="material-symbols-outlined text-[16px]">delete_sweep</span>
                            <span>Kosongkan</span>
                        </button>
                    </div>

                    <!-- Checkout Form submitting to PHP -->
                    <form method="POST" action="kasir.php" id="posForm" class="flex flex-col" novalidate>
                        <input type="hidden" name="action" value="tambah_transaksi">
                        <input type="hidden" name="id_pelanggan" id="formIdPelanggan" value="<?= $selected_pelanggan_id ?>">
                        <input type="hidden" name="items_json" id="formItemsJson" value="[]">
                        <input type="hidden" name="tanggal" value="<?= date('Y-m-d') ?>">

                        <!-- Cart Items List Container -->
                        <div class="p-4 space-y-3 border-b border-outline-variant/30">
                            
                            <!-- Empty Item Placeholder -->
                            <div id="emptyItemBox" class="py-10 text-center text-secondary border-2 border-dashed border-outline-variant/40 rounded-xl">
                                <span class="material-symbols-outlined text-[36px] text-secondary/40 block mb-1">shopping_basket</span>
                                <p class="text-xs font-bold text-on-surface">Keranjang Belanja Kosong</p>
                                <p class="text-[11px] text-secondary mt-0.5">Pilih produk di katalog sebelah kiri untuk menambahkannya ke keranjang</p>
                            </div>

                            <!-- List of Multiple Items -->
                            <div id="cartItemsContainer" class="space-y-2.5 max-h-[280px] overflow-y-auto pr-1">
                                <!-- Populated dynamically by JavaScript -->
                            </div>

                            <!-- Diskon & Pajak Options -->
                            <div class="space-y-2.5 pt-2 border-t border-outline-variant/20">
                                <div class="flex items-center justify-between gap-3">
                                    <label class="text-xs font-semibold text-secondary flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">percent</span>
                                        <span>Diskon Transaksi (%):</span>
                                    </label>
                                    <input type="number" id="inputDiskon" name="diskon" value="0" min="0" max="100" step="0.5" oninput="calculateTotals()"
                                           class="w-24 h-8 px-2.5 bg-surface text-on-surface rounded-lg border border-outline-variant/40 text-xs font-semibold text-right focus:outline-none focus:ring-1 focus:ring-primary-container">
                                </div>

                                <label for="checkPajak" class="flex items-center justify-between p-3 rounded-xl bg-surface-container-low hover:bg-surface-container border border-outline-variant/30 cursor-pointer select-none transition group">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-lg bg-primary-container/10 text-primary flex items-center justify-center">
                                            <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                                        </div>
                                        <div>
                                            <span class="text-xs font-bold text-on-surface block leading-tight">PPN 11% (SF HitungPajak)</span>
                                            <span class="text-[10px] text-secondary">Pajak Pertambahan Nilai 11%</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span id="badgePajakStatus" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-surface-container-high text-secondary transition">Nonaktif</span>
                                        <input type="checkbox" id="checkPajak" name="termasuk_pajak" value="1" onchange="calculateTotals()" 
                                               class="w-4 h-4 rounded text-primary-container focus:ring-primary-container/30 border-outline-variant cursor-pointer">
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Calculation Summary -->
                        <div class="p-5 bg-surface-container-low space-y-2.5 text-xs">
                            <div class="flex justify-between items-center text-secondary">
                                <span>Subtotal (<span id="txtTotalPcs">0</span> item)</span>
                                <span class="font-mono font-semibold text-on-surface" id="txtSubtotal">Rp 0</span>
                            </div>
                            <div class="flex justify-between items-center text-secondary">
                                <span>Potongan Diskon</span>
                                <span class="font-mono font-semibold text-error" id="txtDiskon">- Rp 0</span>
                            </div>
                            <div class="flex justify-between items-center text-secondary">
                                <span>Pajak (PPN 11%)</span>
                                <span class="font-mono font-semibold text-tertiary" id="txtPajak">+ Rp 0</span>
                            </div>
                            <div class="h-px bg-outline-variant/30 my-1"></div>
                            <div class="flex justify-between items-end">
                                <div>
                                    <span class="font-headline font-bold text-sm text-on-surface block">Total Tagihan</span>
                                    <span class="text-[10px] text-secondary">Total seluruh barang</span>
                                </div>
                                <span class="font-headline text-2xl font-extrabold text-primary-container" id="txtGrandTotal">Rp 0</span>
                            </div>
                        </div>

                        <!-- Cashier Payment & Calculator -->
                        <div class="p-5 space-y-3.5 bg-surface-container-lowest border-t border-outline-variant/30">
                            
                            <!-- Payment Method Selector (Pills) -->
                            <div>
                                <label class="text-xs font-bold text-on-surface block mb-1.5 flex items-center justify-between">
                                    <span>Metode Pembayaran</span>
                                    <span class="text-[10px] text-primary-container font-semibold" id="labelActiveMethod">Tunai (Cash)</span>
                                </label>
                                <input type="hidden" name="metode_pembayaran" id="formMetodePembayaran" value="Tunai">
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button type="button" onclick="selectPaymentMethod('Tunai', this)" id="btnMethodTunai" class="pay-method-pill active py-2 px-2 rounded-xl border border-primary-container bg-primary-container/10 text-primary font-bold text-xs flex flex-col items-center justify-center gap-1 transition">
                                        <span class="material-symbols-outlined text-[20px]">payments</span>
                                        <span>Tunai</span>
                                    </button>
                                    <button type="button" onclick="selectPaymentMethod('Duitku POP', this)" id="btnMethodDuitku" class="pay-method-pill py-2 px-2 rounded-xl border border-outline-variant/40 bg-surface-container text-secondary hover:text-on-surface hover:bg-surface-container-high font-medium text-xs flex flex-col items-center justify-center gap-1 transition">
                                        <span class="material-symbols-outlined text-[20px]">language</span>
                                        <span>Duitku</span>
                                    </button>
                                </div>
                            </div>

                            <!-- PANEL 1: TUNAI (CASH) -->
                            <div id="panelTunai" class="space-y-3">
                                <div>
                                    <label class="text-xs font-bold text-on-surface block mb-1">Nominal Bayar (Tunai)</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2.5 text-xs font-bold text-secondary">Rp</span>
                                        <input type="number" id="inputBayar" name="nominal_bayar" oninput="calculateKembalian()" 
                                               class="w-full h-10 pl-9 pr-3 rounded-lg bg-surface text-on-surface font-headline font-bold text-sm border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container" 
                                               placeholder="0">
                                    </div>
                                </div>

                                <!-- Quick Pay Buttons -->
                                <div class="grid grid-cols-3 gap-2">
                                    <button type="button" onclick="setQuickPay(50000)" class="py-1.5 px-2 bg-surface-container hover:bg-surface-container-high text-on-surface text-xs font-bold rounded-lg border border-outline-variant/30 transition">
                                        50.000
                                    </button>
                                    <button type="button" onclick="setQuickPay(100000)" class="py-1.5 px-2 bg-surface-container hover:bg-surface-container-high text-on-surface text-xs font-bold rounded-lg border border-outline-variant/30 transition">
                                        100.000
                                    </button>
                                    <button type="button" onclick="setQuickPas()" class="py-1.5 px-2 bg-primary-fixed hover:bg-primary-fixed-dim text-on-primary-fixed text-xs font-bold rounded-lg transition">
                                        Uang Pas
                                    </button>
                                </div>

                                <!-- Kembalian Display -->
                                <div class="flex justify-between items-center p-3 rounded-lg bg-surface-container-low border border-outline-variant/20">
                                    <span class="text-xs font-semibold text-secondary">Kembalian:</span>
                                    <span class="font-headline font-bold text-sm text-tertiary" id="txtKembalian">Rp 0</span>
                                </div>
                            </div>

                            <div id="panelDuitku" class="hidden p-3.5 rounded-xl bg-surface-container-low border border-outline-variant/30 space-y-3 text-xs">
                                <div class="flex items-center gap-2 pb-2 border-b border-outline-variant/20">
                                    <span class="material-symbols-outlined text-primary-container text-[20px]">lock</span>
                                    <div>
                                        <p class="font-bold text-on-surface">Pembayaran aman melalui Duitku</p>
                                        <p class="text-[10px] text-secondary">Pelanggan memilih metode pembayaran di halaman Duitku.</p>
                                    </div>
                                </div>
                                <div>
                                    <label for="customerEmail" class="text-[11px] font-semibold text-on-surface block mb-1">Email pelanggan <span class="text-error">*</span></label>
                                    <input type="email" name="customer_email" id="customerEmail" autocomplete="email"
                                           class="w-full h-9 px-3 rounded-lg bg-surface text-on-surface border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container"
                                           placeholder="nama@email.com">
                                </div>
                                <div>
                                    <label for="customerPhone" class="text-[11px] font-semibold text-on-surface block mb-1">Nomor telepon <span class="text-secondary">(opsional)</span></label>
                                    <input type="tel" name="customer_phone" id="customerPhone" autocomplete="tel"
                                           class="w-full h-9 px-3 rounded-lg bg-surface text-on-surface border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary-container"
                                           placeholder="08xxxxxxxxxx">
                                </div>
                            </div>

                            <!-- Submit Checkout Button -->
                            <button type="submit" id="btnSubmitCheckout" disabled 
                                    class="w-full py-3.5 px-4 rounded-xl bg-primary-container hover:bg-on-primary-fixed-variant disabled:opacity-50 disabled:cursor-not-allowed text-on-primary font-headline font-bold text-sm shadow-md transition-all active:scale-[0.98] flex items-center justify-center gap-2 mt-2">
                                <span class="material-symbols-outlined text-[20px]" id="btnSubmitIcon">payments</span>
                                <span id="btnSubmitLabel">Bayar & Cetak Nota Sekarang</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

    </div>

<!-- Modal Scanner Kamera POS (html5-qrcode) -->
<div id="cameraScannerModal" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="bg-surface-container-lowest rounded-2xl max-w-md w-full border border-outline-variant/30 shadow-2xl overflow-hidden transition-all transform animate-in fade-in zoom-in-95 flex flex-col">
        <!-- Header -->
        <div class="px-5 py-3.5 border-b border-outline-variant/30 flex items-center justify-between bg-surface-container-low">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary-container text-[22px]">photo_camera</span>
                <div>
                    <h3 class="font-headline font-bold text-sm text-on-surface">Scan Barcode / QR Code Kamera</h3>
                    <p class="text-[10px] text-secondary">Arahkan kamera ke barcode produk atau QR Code</p>
                </div>
            </div>
            <button type="button" onclick="closeCameraScannerModal()" class="text-secondary hover:text-on-surface p-1 rounded-lg">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- Camera Viewport -->
        <div class="p-4 bg-black flex flex-col items-center justify-center relative min-h-[300px]">
            <div id="cameraReader" class="w-full max-w-[320px] rounded-xl overflow-hidden bg-black"></div>
            
            <!-- Toast Feedback inside camera -->
            <div id="cameraScanFeedback" class="absolute bottom-6 left-1/2 -translate-x-1/2 px-4 py-2 rounded-xl bg-emerald-600/90 text-white font-bold text-xs shadow-lg backdrop-blur-xs hidden flex items-center gap-2 pointer-events-none transition-all z-10">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                <span id="cameraScanFeedbackText">Item berhasil di-scan!</span>
            </div>
        </div>

        <!-- Camera Control Bar -->
        <div class="px-5 py-3 bg-surface-container-low border-t border-outline-variant/30 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 flex-1">
                <span class="material-symbols-outlined text-secondary text-[18px]">videocam</span>
                <select id="cameraDeviceSelect" onchange="changeCameraDevice(this.value)" class="h-8 px-2 bg-surface text-on-surface text-xs rounded-lg border border-outline-variant/40 focus:outline-none flex-1">
                    <option value="">Deteksi kamera...</option>
                </select>
            </div>
            <button type="button" onclick="closeCameraScannerModal()" class="px-4 py-2 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface text-xs font-bold transition">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- Floating Toast Notification for Scanner -->
<div id="scannerToast" class="fixed bottom-6 right-6 z-50 transform translate-y-16 opacity-0 pointer-events-none transition-all duration-300 max-w-sm">
    <div id="scannerToastBox" class="bg-slate-900 text-white rounded-xl shadow-2xl p-3.5 border border-slate-700 flex items-center gap-3">
        <div id="scannerToastIconWrapper" class="w-9 h-9 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px]" id="scannerToastIcon">check_circle</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-headline font-bold text-xs" id="scannerToastTitle">Produk Ditambahkan!</p>
            <p class="text-[11px] text-slate-300 truncate" id="scannerToastDesc">Nama Barang</p>
        </div>
    </div>
</div>

<!-- CDN Script html5-qrcode -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<!-- POS Interactive JavaScript Engine (Multi-Item Cart & Barcode Scanner) -->
<script>
    // Master data produk dari server
    const daftarProdukMaster = <?= json_encode($daftar_produk) ?>;

    // Struktur Cart State: Map of id_produk -> { id_produk, nama_produk, harga, stok, jumlah }
    let cart = {};
    let grandCalculatedTotal = 0;
    let isScanningPaused = false;
    let toastTimeout = null;
    let html5QrCode = null;
    let currentCameraId = null;

    // Web Audio API POS Audio Beep Synthesizer
    function playPosBeep(type = 'success') {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);

            if (type === 'success') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1760, ctx.currentTime);
                gain.gain.setValueAtTime(0.25, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.12);
            } else {
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(220, ctx.currentTime);
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.25);
            }
        } catch (e) {}
    }

    // Floating Toast Notification
    function showScannerToast(title, desc, type = 'success') {
        const toast = document.getElementById('scannerToast');
        const toastBox = document.getElementById('scannerToastBox');
        const iconWrapper = document.getElementById('scannerToastIconWrapper');
        const icon = document.getElementById('scannerToastIcon');
        const titleEl = document.getElementById('scannerToastTitle');
        const descEl = document.getElementById('scannerToastDesc');

        if (!toast) return;

        titleEl.innerText = title;
        descEl.innerText = desc;

        if (type === 'success') {
            iconWrapper.className = 'w-9 h-9 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0';
            icon.innerText = 'check_circle';
            toastBox.className = 'bg-slate-900 text-white rounded-xl shadow-2xl p-3.5 border border-emerald-500/30 flex items-center gap-3';
        } else {
            iconWrapper.className = 'w-9 h-9 rounded-lg bg-rose-500/20 text-rose-400 flex items-center justify-center shrink-0';
            icon.innerText = 'error';
            toastBox.className = 'bg-slate-900 text-white rounded-xl shadow-2xl p-3.5 border border-rose-500/30 flex items-center gap-3';
        }

        toast.classList.remove('translate-y-16', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');

        if (toastTimeout) clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => {
            toast.classList.remove('translate-y-0', 'opacity-100');
            toast.classList.add('translate-y-16', 'opacity-0');
        }, 2500);
    }

    // Handler Barcode Scanner (Hardware Scanner & Kamera)
    function handleBarcodeScan(code) {
        if (!code) return;
        const cleanCode = code.trim().toLowerCase();
        if (!cleanCode) return;

        const item = daftarProdukMaster.find(p => {
            const pBarcode = (p.barcode || ('PRD' + String(p.id_produk).padStart(5, '0'))).toLowerCase();
            return pBarcode === cleanCode || 
                   String(p.id_produk) === cleanCode ||
                   p.nama_produk.toLowerCase() === cleanCode;
        });

        if (item) {
            const stock = parseInt(item.stok);
            if (stock <= 0) {
                playPosBeep('error');
                showScannerToast('Stok Habis!', `Stok untuk "${item.nama_produk}" telah habis.`, 'error');
                return;
            }

            addToCart(item.id_produk, item.nama_produk, parseFloat(item.harga), stock);
            playPosBeep('success');
            showScannerToast('Produk Masuk Keranjang', `${item.nama_produk} (${formatRupiahJs(item.harga)})`, 'success');

            const camFeedback = document.getElementById('cameraScanFeedback');
            const camFeedbackText = document.getElementById('cameraScanFeedbackText');
            if (camFeedback && camFeedbackText) {
                camFeedbackText.innerText = `✓ ${item.nama_produk}`;
                camFeedback.classList.remove('hidden');
                setTimeout(() => {
                    camFeedback.classList.add('hidden');
                }, 1200);
            }
        } else {
            playPosBeep('error');
            showScannerToast('Barcode Tidak Dikenal', `Kode "${code}" tidak ditemukan di katalog GopellMart.`, 'error');
        }
    }

    // Kamera Scanner Logic (html5-qrcode)
    function openCameraScannerModal() {
        const modal = document.getElementById('cameraScannerModal');
        modal.classList.remove('hidden');

        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("cameraReader");
        }

        Html5Qrcode.getCameras().then(devices => {
            const select = document.getElementById('cameraDeviceSelect');
            select.innerHTML = '';
            if (devices && devices.length) {
                devices.forEach((device, idx) => {
                    const opt = document.createElement('option');
                    opt.value = device.id;
                    opt.text = device.label || `Kamera ${idx + 1}`;
                    select.appendChild(opt);
                });
                const rearCamera = devices.find(d => d.label.toLowerCase().includes('back') || d.label.toLowerCase().includes('rear')) || devices[devices.length - 1];
                currentCameraId = rearCamera.id;
                select.value = currentCameraId;
                startCameraStream(currentCameraId);
            } else {
                alert('Tidak ditemukan perangkat kamera yang terhubung.');
            }
        }).catch(err => {
            console.error("Camera access error:", err);
            alert('Gagal mengakses kamera: ' + err);
        });
    }

    function startCameraStream(cameraId) {
        if (!html5QrCode) return;
        
        if (html5QrCode.isScanning) {
            html5QrCode.stop().then(() => {
                initStartCamera(cameraId);
            }).catch(() => {
                initStartCamera(cameraId);
            });
        } else {
            initStartCamera(cameraId);
        }
    }

    function initStartCamera(cameraId) {
        const config = {
            fps: 10,
            qrbox: { width: 240, height: 240 },
            aspectRatio: 1.0
        };

        html5QrCode.start(
            cameraId,
            config,
            (decodedText, decodedResult) => {
                if (isScanningPaused) return;
                isScanningPaused = true;
                handleBarcodeScan(decodedText);
                setTimeout(() => {
                    isScanningPaused = false;
                }, 1500);
            },
            (errorMessage) => {
                // Ignore scanning noise
            }
        ).catch(err => {
            console.error("Start camera failed:", err);
        });
    }

    function changeCameraDevice(cameraId) {
        currentCameraId = cameraId;
        startCameraStream(cameraId);
    }

    function closeCameraScannerModal() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().then(() => {
                document.getElementById('cameraScannerModal').classList.add('hidden');
            }).catch(() => {
                document.getElementById('cameraScannerModal').classList.add('hidden');
            });
        } else {
            document.getElementById('cameraScannerModal').classList.add('hidden');
        }
    }

    function formatRupiahJs(number) {
        return 'Rp ' + Math.round(number).toLocaleString('id-ID');
    }

    function updatePelangganInfo(selectEl) {
        const opt = selectEl.options[selectEl.selectedIndex];
        const custId = selectEl.value;
        const avatarEl = document.getElementById('custAvatar');
        const formIdCust = document.getElementById('formIdPelanggan');

        formIdCust.value = custId;

        if (custId && opt) {
            const nama = opt.getAttribute('data-nama') || 'User';
            avatarEl.innerText = nama.substring(0, 2).toUpperCase();
            avatarEl.className = "w-10 h-10 rounded-lg bg-primary-container text-white flex items-center justify-center font-bold text-sm font-headline";
        } else {
            avatarEl.innerText = '?';
            avatarEl.className = "w-10 h-10 rounded-lg bg-surface-container text-secondary flex items-center justify-center font-bold text-sm font-headline";
        }
        checkFormValidity();
    }

    // Tambahkan item ke keranjang belanja (bisa berbagai macam barang)
    function addToCart(id, name, price, stock) {
        if (stock <= 0) {
            alert('Maaf, stok barang ini sudah habis.');
            return;
        }

        if (cart[id]) {
            // Jika sudah ada, tambah jumlahnya
            if (cart[id].jumlah < stock) {
                cart[id].jumlah += 1;
            } else {
                alert('Jumlah telah mencapai batas stok maksimal yang tersedia (' + stock + ').');
                return;
            }
        } else {
            // Jika belum ada, masukkan sebagai macam barang baru
            cart[id] = {
                id_produk: id,
                nama_produk: name,
                harga: price,
                stok: stock,
                jumlah: 1
            };
        }

        renderCart();
    }

    // Ubah kuantitas per item
    function updateItemQty(id, delta) {
        if (!cart[id]) return;

        const newQty = cart[id].jumlah + delta;
        if (newQty <= 0) {
            removeItemFromCart(id);
            return;
        }

        if (newQty > cart[id].stok) {
            alert('Jumlah melebihi stok yang tersedia (' + cart[id].stok + ').');
            return;
        }

        cart[id].jumlah = newQty;
        renderCart();
    }

    // Hapus satu jenis produk dari keranjang
    function removeItemFromCart(id) {
        delete cart[id];
        renderCart();
    }

    // Kosongkan seluruh keranjang
    function clearCart() {
        cart = {};
        renderCart();
    }

    // Render ulang tampilan keranjang belanja
    function renderCart() {
        const container = document.getElementById('cartItemsContainer');
        const emptyBox = document.getElementById('emptyItemBox');
        const badge = document.getElementById('cartCountBadge');
        const items = Object.values(cart);

        badge.innerText = items.length;

        if (items.length === 0) {
            emptyBox.classList.remove('hidden');
            container.innerHTML = '';
        } else {
            emptyBox.classList.add('hidden');
            
            let html = '';
            items.forEach(item => {
                const subtotal = item.harga * item.jumlah;
                html += `
                    <div class="p-3 rounded-xl bg-surface-container-low border border-outline-variant/30 flex items-center justify-between gap-3 text-xs">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <h4 class="font-bold text-on-surface truncate font-headline">${item.nama_produk}</h4>
                                <button type="button" onclick="removeItemFromCart(${item.id_produk})" class="text-secondary hover:text-error transition p-0.5" title="Hapus">
                                    <span class="material-symbols-outlined text-[16px]">close</span>
                                </button>
                            </div>
                            <div class="flex items-center justify-between mt-1 text-secondary">
                                <span>${formatRupiahJs(item.harga)}</span>
                                <span class="font-mono font-bold text-on-surface">${formatRupiahJs(subtotal)}</span>
                            </div>
                        </div>
                        <div class="flex items-center bg-surface-container-lowest rounded-lg border border-outline-variant/40 h-7 shrink-0">
                            <button type="button" onclick="updateItemQty(${item.id_produk}, -1)" class="w-6 h-full flex items-center justify-center text-secondary hover:text-primary transition font-bold text-xs">&minus;</button>
                            <span class="w-7 text-center font-bold text-xs text-on-surface">${item.jumlah}</span>
                            <button type="button" onclick="updateItemQty(${item.id_produk}, 1)" class="w-6 h-full flex items-center justify-center text-secondary hover:text-primary transition font-bold text-xs">+</button>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        // Simpan data items ke hidden input JSON
        document.getElementById('formItemsJson').value = JSON.stringify(items);

        calculateTotals();
    }

    // Kalkulasi subtotal, diskon, pajak, dan grand total
    function calculateTotals() {
        const items = Object.values(cart);
        let subtotal = 0;
        let totalPcs = 0;

        items.forEach(item => {
            subtotal += item.harga * item.jumlah;
            totalPcs += item.jumlah;
        });

        const diskonPersen = parseFloat(document.getElementById('inputDiskon').value) || 0;
        const checkPajakEl = document.getElementById('checkPajak');
        const includePajak = checkPajakEl ? checkPajakEl.checked : false;

        const badgePajak = document.getElementById('badgePajakStatus');
        if (badgePajak) {
            if (includePajak) {
                badgePajak.innerText = 'Aktif (11%)';
                badgePajak.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary-container text-white shadow-xs transition';
            } else {
                badgePajak.innerText = 'Nonaktif';
                badgePajak.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-surface-container-high text-secondary transition';
            }
        }

        const nominalDiskon = subtotal * (diskonPersen / 100);
        const setelahDiskon = Math.max(0, subtotal - nominalDiskon);
        const nominalPajak = includePajak ? (setelahDiskon * 0.11) : 0;
        const grandTotal = setelahDiskon + nominalPajak;

        grandCalculatedTotal = grandTotal;

        document.getElementById('txtTotalPcs').innerText = totalPcs;
        document.getElementById('txtSubtotal').innerText = formatRupiahJs(subtotal);
        document.getElementById('txtDiskon').innerText = '- ' + formatRupiahJs(nominalDiskon);
        document.getElementById('txtPajak').innerText = '+ ' + formatRupiahJs(nominalPajak);
        document.getElementById('txtGrandTotal').innerText = formatRupiahJs(grandTotal);

        calculateKembalian();
        checkFormValidity();
    }

    let currentPaymentMethod = 'Tunai';

    function selectPaymentMethod(method, btn) {
        currentPaymentMethod = method;
        document.getElementById('formMetodePembayaran').value = method;

        // Update pills styling
        document.querySelectorAll('.pay-method-pill').forEach(p => {
            p.className = 'pay-method-pill py-2 px-2 rounded-xl border border-outline-variant/40 bg-surface-container text-secondary hover:text-on-surface hover:bg-surface-container-high font-medium text-xs flex flex-col items-center justify-center gap-1 transition';
        });
        btn.className = 'pay-method-pill active py-2 px-2 rounded-xl border border-primary-container bg-primary-container/10 text-primary font-bold text-xs flex flex-col items-center justify-center gap-1 transition shadow-xs';

        // Update label
        document.getElementById('labelActiveMethod').innerText = method;

        // Toggle panels
        const panelTunai = document.getElementById('panelTunai');
        const panelDuitku = document.getElementById('panelDuitku');
        const customerEmail = document.getElementById('customerEmail');
        const btnLabel = document.getElementById('btnSubmitLabel');
        const btnIcon = document.getElementById('btnSubmitIcon');

        panelTunai.classList.add('hidden');
        panelDuitku.classList.add('hidden');
        customerEmail.required = method === 'Duitku POP';

        if (method === 'Tunai') {
            panelTunai.classList.remove('hidden');
            btnLabel.innerText = 'Bayar Tunai & Cetak Nota';
            btnIcon.innerText = 'payments';
            calculateKembalian();
        } else {
            panelDuitku.classList.remove('hidden');
            btnLabel.innerText = 'Lanjutkan ke Pembayaran Duitku';
            btnIcon.innerText = 'open_in_new';
            document.getElementById('inputBayar').value = 0;
            document.getElementById('txtKembalian').innerText = 'Dibayar melalui Duitku';
        }

        checkFormValidity();
    }

    function calculateKembalian() {
        if (currentPaymentMethod === 'Duitku POP') {
            document.getElementById('inputBayar').value = 0;
            document.getElementById('txtKembalian').innerText = 'Dibayar melalui Duitku';
            return;
        }
        if (currentPaymentMethod !== 'Tunai') {
            document.getElementById('inputBayar').value = Math.ceil(grandCalculatedTotal);
            const kembalianEl = document.getElementById('txtKembalian');
            kembalianEl.innerText = 'Rp 0 (Pas)';
            kembalianEl.className = "font-headline font-bold text-sm text-tertiary";
            return;
        }

        const bayar = parseFloat(document.getElementById('inputBayar').value) || 0;
        const kembalian = bayar - grandCalculatedTotal;
        const kembalianEl = document.getElementById('txtKembalian');

        if (grandCalculatedTotal <= 0) {
            kembalianEl.innerText = 'Rp 0';
            kembalianEl.className = "font-headline font-bold text-sm text-secondary";
            return;
        }

        if (kembalian >= 0) {
            kembalianEl.innerText = formatRupiahJs(kembalian);
            kembalianEl.className = "font-headline font-bold text-sm text-tertiary";
        } else {
            kembalianEl.innerText = 'Kurang ' + formatRupiahJs(Math.abs(kembalian));
            kembalianEl.className = "font-headline font-bold text-sm text-error";
        }
    }

    function setQuickPay(nominal) {
        document.getElementById('inputBayar').value = nominal;
        calculateKembalian();
    }

    function setQuickPas() {
        document.getElementById('inputBayar').value = Math.ceil(grandCalculatedTotal);
        calculateKembalian();
    }

    function checkFormValidity() {
        const custId = document.getElementById('formIdPelanggan').value;
        const items = Object.values(cart);
        const btn = document.getElementById('btnSubmitCheckout');

        if (custId && items.length > 0 && grandCalculatedTotal > 0) {
            btn.removeAttribute('disabled');
        } else {
            btn.setAttribute('disabled', 'disabled');
        }
    }

    function filterCatalog() {
        const query = (document.getElementById('catalogSearch').value || '').toLowerCase().trim();
        const cards = document.querySelectorAll('.product-card');

        cards.forEach(card => {
            const name = card.getAttribute('data-name') || '';
            const barcode = card.getAttribute('data-barcode') || '';
            if (!query || name.includes(query) || barcode.includes(query)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function filterCategory(cat, btn) {
        document.querySelectorAll('.category-pill').forEach(p => {
            p.className = 'category-pill px-3.5 py-1.5 rounded-lg bg-surface-container text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-colors font-medium whitespace-nowrap';
        });
        btn.className = 'category-pill active px-3.5 py-1.5 rounded-lg bg-primary-container text-on-primary font-bold shadow-xs whitespace-nowrap';

        const cards = document.querySelectorAll('.product-card');
        cards.forEach(card => {
            const cardCat = card.getAttribute('data-category');
            if (cat === 'ALL' || cardCat === cat) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Inisialisasi awal jika ada pelanggan di URL & Barcode Scanner Listeners
    document.addEventListener('DOMContentLoaded', () => {
        const selectPel = document.getElementById('selectPelanggan');
        if (selectPel && selectPel.value) {
            updatePelangganInfo(selectPel);
        }
        renderCart();

        // RFID Card Tap Handler (Otomatis Pilih Pelanggan saat Kartu di-Tap)
        const rfidInput = document.getElementById('rfidCardInput');
        if (rfidInput) {
            rfidInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const cardCode = this.value.trim().toLowerCase();
                    if (!cardCode) return;

                    const selectPel = document.getElementById('selectPelanggan');
                    let found = false;
                    for (let i = 0; i < selectPel.options.length; i++) {
                        const opt = selectPel.options[i];
                        const rfidVal = (opt.getAttribute('data-rfid') || '').toLowerCase();
                        const custId = opt.value;
                        if (rfidVal === cardCode || custId === cardCode || opt.text.toLowerCase().includes(cardCode)) {
                            selectPel.selectedIndex = i;
                            updatePelangganInfo(selectPel);
                            playPosBeep('success');
                            showScannerToast('Kartu Terdeteksi', `Member: ${opt.getAttribute('data-nama')}`, 'success');
                            found = true;
                            break;
                        }
                    }

                    if (!found) {
                        playPosBeep('error');
                        showScannerToast('Kartu Tidak Dikenal', `Nomor kartu "${this.value}" belum terdaftar.`, 'error');
                    }
                    this.value = '';
                }
            });
        }

        // Hardware Barcode Scanner Input Handler (Auto-focus & Enter Key)
        const scannerInput = document.getElementById('barcodeScannerInput');
        if (scannerInput) {
            scannerInput.focus();
            scannerInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const code = this.value;
                    handleBarcodeScan(code);
                    this.value = '';
                }
            });
        }

        // Global hotkey: Tekan F2 untuk langsung fokus ke input barcode scanner
        document.addEventListener('keydown', (e) => {
            if (e.key === 'F2') {
                e.preventDefault();
                if (scannerInput) scannerInput.focus();
            }
        });

        // Interceptor submit agar nominal bayar otomatis terisi uang pas jika dikosongkan atau non-tunai
        const posForm = document.getElementById('posForm');
        if (posForm) {
            posForm.addEventListener('submit', function(e) {
                const bayarInput = document.getElementById('inputBayar');
                const metodeInput = document.getElementById('formMetodePembayaran');
                const currentMetode = metodeInput ? metodeInput.value : 'Tunai';

                if (currentMetode === 'Duitku POP') {
                    const emailInput = document.getElementById('customerEmail');
                    if (!emailInput.checkValidity()) {
                        e.preventDefault();
                        emailInput.reportValidity();
                    }
                    bayarInput.value = 0;
                } else if (currentMetode !== 'Tunai') {
                    // QRIS & transfer: selalu otomatis terisi uang pas.
                    bayarInput.value = Math.ceil(grandCalculatedTotal);
                } else {
                    let bayarVal = parseFloat(bayarInput.value) || 0;
                    if (bayarVal < grandCalculatedTotal) {
                        bayarInput.value = Math.ceil(grandCalculatedTotal);
                    }
                }
            });
        }
    });

    // Helper Modal Tambah Member Baru Langsung dari Kasir
    function openModalTambahMemberKasir() {
        document.getElementById('modalTambahMemberKasir').classList.remove('hidden');
        document.getElementById('kasirNewMemberNama').focus();
    }
    function closeModalTambahMemberKasir() {
        document.getElementById('modalTambahMemberKasir').classList.add('hidden');
    }
    function generateRandomRfidKasir() {
        const rand = Math.floor(1000 + Math.random() * 9000);
        document.getElementById('kasirNewMemberRfid').value = 'RFID-' + rand;
    }
    async function handleTambahMemberKasir(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitMemberKasir');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-[16px]">sync</span> Menyimpan...';

        const form = document.getElementById('formTambahMemberKasir');
        const formData = new FormData(form);

        try {
            const resp = await fetch('kasir.php', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();

            if (data.success) {
                const selectPel = document.getElementById('selectPelanggan');
                const newOpt = document.createElement('option');
                newOpt.value = data.id_pelanggan;
                newOpt.setAttribute('data-nama', data.nama_pelanggan);
                newOpt.setAttribute('data-rfid', data.rfid_uid);
                newOpt.setAttribute('data-kota', data.kota);
                newOpt.textContent = `#CUST-${String(data.id_pelanggan).padStart(3, '0')} - ${data.nama_pelanggan} [${data.rfid_uid}]`;
                selectPel.appendChild(newOpt);

                selectPel.value = data.id_pelanggan;
                updatePelangganInfo(selectPel);

                closeModalTambahMemberKasir();
                form.reset();
                document.getElementById('kasirNewMemberKota').value = 'Ponorogo';
                showScannerToast('Member Terdaftar', data.message, 'success');
                playPosBeep('success');
            } else {
                alert('Gagal: ' + (data.message || 'Terjadi kesalahan sistem'));
            }
        } catch (err) {
            alert('Terjadi kesalahan jaringan: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">save</span> Simpan & Pilih Member';
        }
    }
</script>

<!-- Modal Tambah Member Baru Langsung dari Kasir -->
<div id="modalTambahMemberKasir" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/40 shadow-xl max-w-md w-full overflow-hidden">
        
        <div class="px-5 py-4 border-b border-outline-variant/20 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[22px]">badge</span>
                <h3 class="font-headline font-bold text-base text-on-surface">Pendaftaran Member &amp; Kartu RFID</h3>
            </div>
            <button type="button" onclick="closeModalTambahMemberKasir()" class="text-secondary hover:text-on-surface p-1">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <form id="formTambahMemberKasir" onsubmit="handleTambahMemberKasir(event)" class="p-5 space-y-4">
            <input type="hidden" name="action" value="tambah_pelanggan_kasir">
            <input type="hidden" name="is_ajax" value="1">

            <div>
                <label class="block text-xs font-bold text-on-surface mb-1">Nama Pelanggan / Member <span class="text-error">*</span></label>
                <input type="text" id="kasirNewMemberNama" name="nama_pelanggan" required placeholder="Contoh: Budi Prasetyo"
                       class="w-full h-10 px-3 text-xs rounded-lg border border-outline-variant/40 bg-surface text-on-surface focus:outline-none focus:ring-2 focus:ring-primary-container">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-bold text-on-surface">Nomor Kartu RFID</label>
                    <button type="button" onclick="generateRandomRfidKasir()" class="text-[10px] text-primary hover:underline font-bold">Acak Nomor</button>
                </div>
                <div class="relative">
                    <input type="text" id="kasirNewMemberRfid" name="rfid_uid" placeholder="Tempel kartu RFID atau klik acak..."
                           class="w-full h-10 pl-9 pr-3 text-xs font-mono font-bold rounded-lg border border-outline-variant/40 bg-surface text-on-surface focus:outline-none focus:ring-2 focus:ring-primary-container">
                    <span class="material-symbols-outlined absolute left-2.5 top-2.5 text-secondary text-[18px]">credit_card</span>
                </div>
                <p class="text-[10px] text-secondary mt-1">Bisa langsung tap kartu RFID fisik di sensor reader</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-on-surface mb-1">Kota Domisili</label>
                <input type="text" id="kasirNewMemberKota" name="kota" value="Ponorogo" placeholder="Contoh: Ponorogo"
                       class="w-full h-10 px-3 text-xs rounded-lg border border-outline-variant/40 bg-surface text-on-surface focus:outline-none focus:ring-2 focus:ring-primary-container">
            </div>

            <div class="pt-3 border-t border-outline-variant/20 flex items-center justify-end gap-2">
                <button type="button" onclick="closeModalTambahMemberKasir()" class="px-4 py-2 rounded-lg bg-surface-container text-secondary text-xs font-bold transition">
                    Batal
                </button>
                <button type="submit" id="btnSubmitMemberKasir" class="px-5 py-2 rounded-lg bg-primary-container hover:bg-on-primary-fixed-variant text-white font-headline text-xs font-bold transition shadow-2xs active:scale-95 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">save</span>
                    <span>Simpan &amp; Pilih Member</span>
                </button>
            </div>
        </form>

    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>