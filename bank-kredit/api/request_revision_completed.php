<?php
/**
 * Endpoint untuk request revisi pada pengajuan yang sudah completed
 * Digunakan oleh: kabag_analis, kabag_kredit, kadiv_kredit, direksi
 * Untuk: Mengirim kembali pengajuan ke analis untuk revisi
 * 
 * POST parameters:
 * - section: 'request_revision_completed' 
 * - id_pengajuan: ID aplikasi
 * - revisi_notes: Alasan/catatan revisi
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.']);
    exit;
}

// Check role (only approver roles can request revision)
$allowedRoles = ['kabag_analis', 'kasubag_analis', 'kabag_kredit', 'kadiv_kredit', 'kadiv_bisnis', 'direksi', 'direktur_utama'];
if (!in_array($_SESSION['role'] ?? '', $allowedRoles, true)) {
    echo json_encode(['success' => false, 'message' => 'Hanya pejabat persetujuan yang dapat meminta revisi.']);
    exit;
}

$section = $_POST['section'] ?? '';
$id_pengajuan = intval($_POST['id_pengajuan'] ?? 0);
$revisi_notes = trim($_POST['revisi_notes'] ?? '');

if ($section !== 'request_revision_completed') {
    echo json_encode(['success' => false, 'message' => 'Section tidak valid.']);
    exit;
}

if ($id_pengajuan <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID pengajuan tidak valid.']);
    exit;
}

if (empty($revisi_notes)) {
    echo json_encode(['success' => false, 'message' => 'Catatan revisi wajib diisi.']);
    exit;
}

try {
    global $pdo;
    $result = requestCompletedApplicationRevision(
        $pdo,
        $id_pengajuan,
        $_SESSION['role'],
        $_SESSION['user_id'],
        $revisi_notes
    );
    
    echo json_encode($result);
} catch (Exception $e) {
    logError('request_revision_completed endpoint error', [
        'section' => $section,
        'id_pengajuan' => $id_pengajuan,
        'user_role' => $_SESSION['role'] ?? 'unknown',
        'error' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
    
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan internal. Silakan coba lagi.']);
}
