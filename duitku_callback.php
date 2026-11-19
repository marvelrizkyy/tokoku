<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/duitku.php';

header('Content-Type: text/plain');

$merchantCode = trim($_POST['merchantCode'] ?? '');
$amount = trim($_POST['amount'] ?? '');
$merchantOrderId = trim($_POST['merchantOrderId'] ?? '');
$signature = trim($_POST['signature'] ?? '');
$reference = trim($_POST['reference'] ?? '');
$resultCode = trim($_POST['resultCode'] ?? '');
$paymentCode = trim($_POST['paymentCode'] ?? '');

$merchantExpected = duitkuEnvValue('DUITKU_MERCHANT_CODE');
$merchantKey = duitkuEnvValue('DUITKU_API_KEY');

$validSignature = false;
if ($merchantCode !== '' && $amount !== '' && $merchantOrderId !== '' && $signature !== '' && $merchantKey !== '') {
    $calculated = hash_hmac('sha256', $merchantCode . $amount . $merchantOrderId, $merchantKey);
    $validSignature = hash_equals($calculated, $signature);
}

try {
    $stmtAudit = $pdo->prepare("
        INSERT INTO tbl_payment_callback (
            merchant_order_id, duitku_reference, amount, result_code, signature_valid, payload
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtAudit->execute([
        $merchantOrderId,
        $reference,
        is_numeric($amount) ? (float)$amount : null,
        $resultCode,
        $validSignature ? 1 : 0,
        json_encode([
            'paymentCode' => $paymentCode,
            'publisherOrderId' => trim($_POST['publisherOrderId'] ?? ''),
            'settlementDate' => trim($_POST['settlementDate'] ?? ''),
            'issuerCode' => trim($_POST['issuerCode'] ?? ''),
            'bankRespCode' => trim($_POST['bankRespCode'] ?? ''),
            'bankRespMsg' => trim($_POST['bankRespMsg'] ?? ''),
            'transactionStateStatus' => trim($_POST['transactionStateStatus'] ?? ''),
        ], JSON_UNESCAPED_SLASHES),
    ]);

    if (!$validSignature) {
        http_response_code(400);
        echo 'BAD SIGNATURE';
        exit;
    }
    if ($merchantCode !== $merchantExpected) {
        http_response_code(400);
        echo 'BAD MERCHANT';
        exit;
    }
    if ($merchantOrderId === '' || !is_numeric($amount)) {
        http_response_code(400);
        echo 'BAD PARAMETER';
        exit;
    }

    $isSuccess = $resultCode === '00';
    $isFailed = $resultCode === '01';
    $newStatus = $isSuccess ? 'PAID' : ($isFailed ? 'FAILED' : 'PENDING');
    duitkuApplyTransactionStatus(
        $pdo,
        $merchantOrderId,
        $amount,
        $newStatus,
        $resultCode,
        $reference,
        $paymentCode
    );
    echo 'OK';
} catch (Throwable $e) {
    $failureResponses = [
        'ORDER NOT FOUND' => 404,
        'INVALID AMOUNT' => 400,
        'INVALID REFERENCE' => 400,
        'INSUFFICIENT STOCK' => 409,
    ];
    $message = $e->getMessage();
    if (isset($failureResponses[$message])) {
        http_response_code($failureResponses[$message]);
        echo $message;
    } else {
        http_response_code(500);
        echo 'PROCESSING ERROR';
    }
}
