<?php
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (id_user, aktivitas) VALUES (?, 'Logout dari sistem')");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        error_log('logout audit log failed: ' . $e->getMessage());
    }
}
session_destroy();
header("Location: " . BASE_URL . "/auth/login.php");
exit;
?>
