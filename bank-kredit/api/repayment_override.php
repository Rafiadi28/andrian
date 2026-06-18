<?php
/**
 * API override repayment per pengajuan — hanya Direksi.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/repayment_override.php';

header('Content-Type: application/json');

requireRepaymentOverrideAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.']);
    exit;
}

$idPengajuan = (int) ($_POST['id_pengajuan'] ?? 0);
$action = trim((string) ($_POST['override_action'] ?? 'apply'));
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($action === 'revoke') {
    $result = revokeRepaymentOverride(
        $pdo,
        $idPengajuan,
        $userId,
        trim((string) ($_POST['catatan_cabut'] ?? ''))
    );
    echo json_encode($result);
    exit;
}

$result = applyRepaymentOverride(
    $pdo,
    $idPengajuan,
    $userId,
    $_POST['nilai_override'] ?? 0,
    trim((string) ($_POST['alasan_override'] ?? ''))
);
echo json_encode($result);
