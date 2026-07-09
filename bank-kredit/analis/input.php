<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/analis_prefill_data.php';
requireSameRole('analis');

$allowed_jenis = ['umum', 'pppk', 'perangkat_desa', 'kpr', 'kretamas', 'cashcolateral'];
$jenis_param = isset($_GET['jenis']) ? trim((string) $_GET['jenis']) : null;
$edit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$jenis_pekerjaan = 'umum';
$form_banner_title = '';
$catatan_revisi_display = '';
$edit_id_pengajuan = 0;
$prefill_bundle = null;
$prefill_json = 'null';

if ($edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM pengajuan_kredit WHERE id_pengajuan = ? AND input_by = ? LIMIT 1");
    $stmt->execute([$edit_id, $_SESSION['user_id']]);
    $rowEdit = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$rowEdit) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title><link rel="stylesheet" href="../assets/style.css"></head><body style="padding:2rem;font-family:sans-serif">';
        echo '<p><strong>Pengajuan tidak ditemukan</strong> atau Anda tidak berhak mengaksesnya.</p>';
        echo '<p><a href="dashboard.php">Kembali ke dashboard</a></p></body></html>';
        exit;
    }
    $st = (string) ($rowEdit['status_pengajuan'] ?? '');
    if (!in_array($st, ['draft', 'revisi', 'ditolak', 'diajukan_ulang', 'revisi_diajukan'], true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Tidak dapat mengedit</title><link rel="stylesheet" href="../assets/style.css"></head><body style="padding:2rem;font-family:sans-serif">';
        echo '<p>Pengajuan ini sedang dalam proses approval atau telah selesai. Pengeditan hanya untuk status <strong>draft</strong>, <strong>revisi</strong>, atau <strong>ditolak</strong>.</p>';
        echo '<p>Status saat ini: <strong>' . htmlspecialchars($st) . '</strong></p>';
        echo '<p><a href="../detail.php?id=' . (int) $edit_id . '">Lihat detail</a> · <a href="dashboard.php">Dashboard</a></p></body></html>';
        exit;
    }
    $edit_id_pengajuan = $edit_id;
    $stored_jenis = isset($rowEdit['jenis_pekerjaan']) ? normalizeJenisPekerjaan((string) $rowEdit['jenis_pekerjaan']) : '';
    if ($stored_jenis !== '' && in_array($stored_jenis, $allowed_jenis, true)) {
        $jenis_pekerjaan = $stored_jenis;
    } else {
        $jenis_pekerjaan = 'umum';
    }
    if ($jenis_param !== null && $jenis_param !== '') {
        $normalized_param = normalizeJenisPekerjaan($jenis_param);
        if (in_array($normalized_param, $allowed_jenis, true)) {
            $jenis_pekerjaan = $normalized_param;
        }
    }
    $catatan_revisi_display = trim((string) ($rowEdit['catatan_revisi'] ?? ''));
    if ($catatan_revisi_display === '' && !empty($rowEdit['alasan_penolakan'])) {
        $catatan_revisi_display = trim((string) $rowEdit['alasan_penolakan']);
    }
    $prefill_bundle = analisLoadPrefillBundle($pdo, $edit_id);
    $prefill_json = json_encode($prefill_bundle, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($prefill_json === false) {
        $prefill_json = 'null';
    }
                            } else {
        $normalized_param = $jenis_param !== null ? normalizeJenisPekerjaan($jenis_param) : '';
        if ($normalized_param === '' || !in_array($normalized_param, $allowed_jenis, true)) {
            include __DIR__ . '/pilih_jenis_pekerjaan.php';
            exit;
        }
        $jenis_pekerjaan = $normalized_param;
    }

$nav_q = [];
if ($edit_id_pengajuan > 0) {
    $nav_q['id'] = $edit_id_pengajuan;
}
if ($jenis_pekerjaan !== 'umum') {
    $nav_q['jenis'] = $jenis_pekerjaan;
}
$ANALIS_INPUT_NAV_QUERY = $nav_q !== [] ? ('?' . http_build_query($nav_q)) : '';

if ($jenis_pekerjaan === 'pppk') {
    include __DIR__ . '/form_pppk.php';
} elseif ($jenis_pekerjaan === 'perangkat_desa') {
    include __DIR__ . '/form_desa.php';
} elseif ($jenis_pekerjaan === 'cashcolateral') {
    include __DIR__ . '/form_cashcolateral.php';
} elseif ($jenis_pekerjaan === 'kretamas') {
    $form_banner_title = 'Form analisa: Kredit Emas (KRETAMAS)';
    include __DIR__ . '/form_umum.php';
} else {
    include __DIR__ . '/form_umum.php';
}
