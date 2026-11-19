<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/duitku.php';

$orderId = trim($_GET['order'] ?? '');
if ($orderId === '') {
    $orderId = trim($_GET['merchantOrderId'] ?? '');
}
if ($orderId === '') {
    $orderId = trim($_GET['merchantOrderID'] ?? '');
}
$refreshCount = min(10, max(0, (int)($_GET['check'] ?? 0)));
$transaction = null;

if ($orderId !== '') {
    $stmt = $pdo->prepare("
        SELECT id_transaksi, merchant_order_id, total_belanja, payment_status,
               duitku_reference, payment_result_code, paid_at
        FROM tbl_transaksi
        WHERE merchant_order_id = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId]);
    $transaction = $stmt->fetch();
}

// Duitku callbacks can arrive late or be unreachable from a local development server.
// Verify the order server-to-server before settling it; never trust redirect parameters.
$verificationMessage = null;
if ($transaction
    && in_array($transaction['payment_status'], ['UNPAID', 'PENDING'], true)
    && $refreshCount % 3 === 0) {
    try {
        $duitkuStatus = duitkuGetTransactionStatus($orderId);
        $statusCode = (string)$duitkuStatus['statusCode'];
        $paymentStatus = $statusCode === '00'
            ? 'PAID'
            : ($statusCode === '02' ? 'FAILED' : 'PENDING');

        duitkuApplyTransactionStatus(
            $pdo,
            $orderId,
            $duitkuStatus['amount'] ?? null,
            $paymentStatus,
            $statusCode,
            (string)($duitkuStatus['reference'] ?? '')
        );

        $stmt->execute([$orderId]);
        $transaction = $stmt->fetch();
    } catch (Throwable $e) {
        $verificationMessage = 'Status pembayaran belum dapat diverifikasi. Sistem akan mencoba kembali otomatis.';
    }
}

$status = $transaction['payment_status'] ?? 'PENDING';
$isCashierOrder = strncmp($orderId, 'GK-', 3) === 0;
$returnPage = $isCashierOrder ? 'kasir.php' : 'pembeli.php';
$returnLabel = $isCashierOrder ? 'Kembali ke Kasir' : 'Kembali ke Toko';
if ($transaction && $status === 'PAID') {
    header('Location: nota.php?id=' . (int)$transaction['id_transaksi']);
    exit;
}

$statusLabel = [
    'PAID' => 'Pembayaran Berhasil',
    'FAILED' => 'Pembayaran Gagal',
    'UNPAID' => 'Menunggu Pembayaran',
    'PENDING' => 'Menunggu Pembayaran',
][$status] ?? $status;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pembayaran - GopellMart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if ($transaction && in_array($status, ['UNPAID', 'PENDING'], true) && $refreshCount < 10): ?>
        <meta http-equiv="refresh" content="5;url=duitku_return.php?order=<?= rawurlencode($orderId) ?>&amp;check=<?= $refreshCount + 1 ?>">
    <?php endif; ?>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-lg p-6 text-center">
        <div class="text-5xl mb-4"><?= $status === 'PAID' ? '✅' : ($status === 'FAILED' ? '❌' : '⏳') ?></div>
        <h1 class="text-xl font-bold text-slate-900"><?= htmlspecialchars($statusLabel) ?></h1>
        <?php if ($transaction): ?>
            <p class="mt-3 text-sm text-slate-600">Order: <strong><?= htmlspecialchars($transaction['merchant_order_id']) ?></strong></p>
            <p class="text-sm text-slate-600">Total: <strong>Rp <?= number_format((float)$transaction['total_belanja'], 0, ',', '.') ?></strong></p>
            <?php if (in_array($status, ['UNPAID', 'PENDING'], true)): ?>
                <p class="mt-4 text-sm text-slate-600"><?= htmlspecialchars($verificationMessage ?? 'Menunggu konfirmasi pembayaran. Halaman akan memperbarui status otomatis.') ?></p>
                <?php if ($refreshCount >= 10): ?>
                    <a href="duitku_return.php?order=<?= rawurlencode($orderId) ?>" class="mt-5 inline-flex px-4 py-2.5 rounded-xl bg-orange-600 text-white text-sm font-bold">Periksa Status Lagi</a>
                <?php endif; ?>
            <?php else: ?>
                <p class="mt-4 text-sm text-slate-600">Pembayaran belum berhasil. Anda dapat kembali ke toko untuk mencoba lagi.</p>
                <a href="<?= htmlspecialchars($returnPage) ?>" class="mt-5 inline-flex px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-bold"><?= htmlspecialchars($returnLabel) ?></a>
            <?php endif; ?>
        <?php else: ?>
            <p class="mt-3 text-sm text-slate-600">Order tidak ditemukan.</p>
            <a href="<?= htmlspecialchars($returnPage) ?>" class="mt-5 inline-flex px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-bold"><?= htmlspecialchars($returnLabel) ?></a>
        <?php endif; ?>
        <p class="mt-4 text-xs text-slate-400">Status pembayaran diverifikasi langsung ke Duitku dan diperbarui melalui notifikasi.</p>
    </div>
</body>
</html>
