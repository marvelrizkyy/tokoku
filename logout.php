<?php
/**
 * Logout Handler - TokoKu POS Retail Engine
 */
session_start();
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['username'])) {
    try {
        $logStmt = $pdo->prepare("INSERT INTO tbl_log (tabel_terkait, aktivitas, keterangan) VALUES ('tbl_user', 'LOGOUT', ?)");
        $logStmt->execute(["User '{$_SESSION['username']}' logout dari sistem"]);
        $logStmt->closeCursor();
    } catch (Exception $e) {}
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: login.php?logged_out=1");
exit;
