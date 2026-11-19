<?php

function loadEnvFile($path) {
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}

loadEnvFile(dirname(__DIR__) . '/.env');

function duitkuEnvValue($name, $default = '') {
    $value = getenv($name);
    if ($value === false || trim($value) === '') {
        return $default;
    }
    return trim($value);
}

function duitkuIsConfigured() {
    return duitkuEnvValue('DUITKU_MERCHANT_CODE') !== ''
        && duitkuEnvValue('DUITKU_API_KEY') !== '';
}

function duitkuBaseUrl() {
    return strtolower(duitkuEnvValue('DUITKU_ENV', 'sandbox')) === 'production'
        ? 'https://api-prod.duitku.com'
        : 'https://api-sandbox.duitku.com';
}

function duitkuFrontendUrl() {
    return strtolower(duitkuEnvValue('DUITKU_ENV', 'sandbox')) === 'production'
        ? 'https://app-prod.duitku.com'
        : 'https://app-sandbox.duitku.com';
}

function duitkuBasePublicUrl() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($scriptDir === '' ? '' : $scriptDir);
}

function duitkuCallbackUrl() {
    $configured = duitkuEnvValue('DUITKU_CALLBACK_URL');
    return $configured !== '' ? $configured : duitkuBasePublicUrl() . '/duitku_callback.php';
}

function duitkuReturnUrl($merchantOrderId = '') {
    $configured = duitkuEnvValue('DUITKU_RETURN_URL');
    if ($configured !== '') {
        return $configured . (str_contains($configured, '?') ? '&' : '?') . 'order=' . rawurlencode($merchantOrderId);
    }
    return duitkuBasePublicUrl() . '/duitku_return.php' . ($merchantOrderId !== '' ? '?order=' . rawurlencode($merchantOrderId) : '');
}

function duitkuCreateInvoice(array $params) {
    $merchantCode = duitkuEnvValue('DUITKU_MERCHANT_CODE');
    $merchantKey = duitkuEnvValue('DUITKU_API_KEY');

    if ($merchantCode === '' || $merchantKey === '') {
        throw new RuntimeException('Konfigurasi Duitku belum diisi. Salin .env.example menjadi .env dan isi credential sandbox.');
    }

    $timestamp = (string) round(microtime(true) * 1000);
    $signature = hash_hmac('sha256', $merchantCode . $timestamp, $merchantKey);
    $body = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        throw new RuntimeException('Gagal membuat payload pembayaran.');
    }

    $ch = curl_init(duitkuBaseUrl() . '/api/merchant/createInvoice');
    if ($ch === false) {
        throw new RuntimeException('Gagal menginisialisasi koneksi pembayaran.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
            'x-duitku-timestamp: ' . $timestamp,
            'x-duitku-signature: ' . $signature,
            'x-duitku-merchantcode: ' . $merchantCode,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        throw new RuntimeException('Tidak dapat menghubungi Duitku: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded) || $httpCode !== 200) {
        $details = [];
        if (is_array($decoded)) {
            $statusCode = $decoded['statusCode'] ?? $decoded['code'] ?? null;
            $statusMessage = $decoded['statusMessage'] ?? $decoded['message'] ?? null;
            if (is_scalar($statusCode) && (string)$statusCode !== '') {
                $details[] = 'kode ' . (string)$statusCode;
            }
            if (is_scalar($statusMessage) && trim((string)$statusMessage) !== '') {
                $details[] = trim((string)$statusMessage);
            }
        }

        throw new RuntimeException(
            'Duitku menolak request (HTTP ' . $httpCode
            . (empty($details) ? '' : ', ' . implode(': ', $details))
            . '). Periksa konfigurasi dan data checkout.'
        );
    }

    if (($decoded['statusCode'] ?? '') !== '00'
        || empty($decoded['reference'])
        || empty($decoded['paymentUrl'])) {
        $statusCode = is_scalar($decoded['statusCode'] ?? null) ? (string)$decoded['statusCode'] : 'tidak diketahui';
        $statusMessage = is_scalar($decoded['statusMessage'] ?? null)
            ? trim((string)$decoded['statusMessage'])
            : '';
        throw new RuntimeException(
            'Invoice Duitku gagal (kode ' . $statusCode
            . ($statusMessage !== '' ? ': ' . $statusMessage : '')
            . ').'
        );
    }

    return $decoded;
}

function duitkuGetTransactionStatus($merchantOrderId) {
    $merchantCode = duitkuEnvValue('DUITKU_MERCHANT_CODE');
    $merchantKey = duitkuEnvValue('DUITKU_API_KEY');
    if ($merchantCode === '' || $merchantKey === '') {
        throw new RuntimeException('Konfigurasi Duitku belum tersedia.');
    }

    $url = strtolower(duitkuEnvValue('DUITKU_ENV', 'sandbox')) === 'production'
        ? 'https://passport.duitku.com/webapi/api/merchant/transactionStatus'
        : 'https://sandbox.duitku.com/webapi/api/merchant/transactionStatus';
    $payload = json_encode([
        'merchantCode' => $merchantCode,
        'merchantOrderId' => $merchantOrderId,
        'signature' => hash_hmac('sha256', $merchantCode . $merchantOrderId, $merchantKey),
    ], JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('Gagal membuat permintaan pemeriksaan status Duitku.');
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Gagal menginisialisasi pemeriksaan status Duitku.');
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError !== '' || $httpCode !== 200) {
        throw new RuntimeException('Duitku belum dapat memverifikasi status transaksi.');
    }

    $result = json_decode((string)$response, true);
    if (!is_array($result)
        || ($result['merchantOrderId'] ?? '') !== $merchantOrderId
        || !in_array((string)($result['statusCode'] ?? ''), ['00', '01', '02'], true)) {
        throw new RuntimeException('Respons pemeriksaan status Duitku tidak valid.');
    }

    return $result;
}

function duitkuApplyTransactionStatus(PDO $pdo, $merchantOrderId, $amount, $status, $resultCode, $reference = '', $paymentCode = '') {
    if (!in_array($status, ['PAID', 'PENDING', 'FAILED'], true)) {
        throw new RuntimeException('INVALID STATUS');
    }

    $pdo->beginTransaction();
    try {
        $stmtTx = $pdo->prepare("
            SELECT id_transaksi, total_belanja, duitku_reference, stock_processed, payment_status
            FROM tbl_transaksi
            WHERE merchant_order_id = ?
            FOR UPDATE
        ");
        $stmtTx->execute([$merchantOrderId]);
        $transaction = $stmtTx->fetch(PDO::FETCH_ASSOC);
        $stmtTx->closeCursor();
        if (!$transaction) {
            throw new RuntimeException('ORDER NOT FOUND');
        }

        if (!is_numeric($amount) || abs((float)$amount - (float)$transaction['total_belanja']) > 0.01) {
            throw new RuntimeException('INVALID AMOUNT');
        }
        if (!empty($transaction['duitku_reference'])
            && $reference !== ''
            && $reference !== $transaction['duitku_reference']) {
            throw new RuntimeException('INVALID REFERENCE');
        }

        $newStatus = $transaction['payment_status'] === 'PAID' ? 'PAID' : $status;
        if ($newStatus === 'PAID' && (int)$transaction['stock_processed'] === 0) {
            $stmtItems = $pdo->prepare("
                SELECT id_produk, jumlah
                FROM tbl_detail_transaksi
                WHERE id_transaksi = ?
                ORDER BY id_detail ASC
                FOR UPDATE
            ");
            $stmtItems->execute([$transaction['id_transaksi']]);
            $itemRows = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
            $stmtItems->closeCursor();

            $quantities = [];
            foreach ($itemRows as $item) {
                $productId = (int)$item['id_produk'];
                $quantities[$productId] = ($quantities[$productId] ?? 0) + (int)$item['jumlah'];
            }
            foreach ($quantities as $productId => $quantity) {
                $stmtStock = $pdo->prepare("SELECT stok FROM tbl_produk WHERE id_produk = ? FOR UPDATE");
                $stmtStock->execute([$productId]);
                $stock = $stmtStock->fetchColumn();
                $stmtStock->closeCursor();
                if ($stock === false || (int)$stock < $quantity) {
                    throw new RuntimeException('INSUFFICIENT STOCK');
                }
            }

            $stmtUpdateStock = $pdo->prepare("UPDATE tbl_produk SET stok = stok - ? WHERE id_produk = ?");
            foreach ($quantities as $productId => $quantity) {
                $stmtUpdateStock->execute([$quantity, $productId]);
            }
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE tbl_transaksi
            SET payment_status = ?,
                payment_result_code = ?,
                nominal_bayar = CASE WHEN ? = 'PAID' THEN ? ELSE nominal_bayar END,
                paid_at = CASE WHEN ? = 'PAID' THEN NOW() ELSE paid_at END,
                duitku_reference = COALESCE(NULLIF(?, ''), duitku_reference),
                metode_pembayaran = CASE WHEN ? <> '' THEN ? ELSE metode_pembayaran END,
                stock_processed = CASE WHEN ? = 'PAID' THEN 1 ELSE stock_processed END
            WHERE id_transaksi = ?
        ");
        $stmtUpdate->execute([
            $newStatus,
            $resultCode,
            $newStatus,
            (float)$amount,
            $newStatus,
            $reference,
            $paymentCode,
            $paymentCode,
            $newStatus,
            $transaction['id_transaksi'],
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

