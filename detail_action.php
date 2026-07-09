<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    die('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
}

$action = $_POST['action'] ?? '';
$id_pengajuan = intval($_POST['id_pengajuan'] ?? 0);

if ($action === 'delete' && $id_pengajuan > 0) {
    // Permission: admin or owner
    $stmt = $pdo->prepare("SELECT id_pengajuan, input_by, status_pengajuan, nama_debitur FROM pengajuan_kredit WHERE id_pengajuan = ?");
    $stmt->execute([$id_pengajuan]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        die('Pengajuan tidak ditemukan.');
    }

    if ($_SESSION['role'] !== 'Superadmin' && $_SESSION['user_id'] != $row['input_by']) {
        die('Tidak memiliki izin untuk menghapus pengajuan ini.');
    }

    try {
        $pdo->beginTransaction();

        $nama_debitur = $row['nama_debitur'] ?? 'Unknown';

        // Hapus semua data terkait di tabel relasi terlebih dahulu
        $pdo->prepare("DELETE FROM approval_kredit WHERE id_pengajuan = ?")->execute([$id_pengajuan]);
        $pdo->prepare("DELETE FROM jaminan_tanah_bangunan WHERE id_pengajuan = ?")->execute([$id_pengajuan]);
        $pdo->prepare("DELETE FROM jaminan_kendaraan WHERE id_pengajuan = ?")->execute([$id_pengajuan]);

        // Hapus tabel analisa (jika ada)
        try { $pdo->prepare("DELETE FROM analisa_neraca WHERE id_pengajuan = ?")->execute([$id_pengajuan]); } catch (Exception $e) {}
        try { $pdo->prepare("DELETE FROM analisa_5c WHERE id_pengajuan = ?")->execute([$id_pengajuan]); } catch (Exception $e) {}
        try { $pdo->prepare("DELETE FROM angsuran_bank_lain WHERE id_pengajuan = ?")->execute([$id_pengajuan]); } catch (Exception $e) {}

        // Hapus data pengajuan utama
        $pdo->prepare("DELETE FROM pengajuan_kredit WHERE id_pengajuan = ?")->execute([$id_pengajuan]);

        // Audit log — catat penghapusan
        $pdo->prepare("INSERT INTO audit_log (id_user, aktivitas) VALUES (?, ?)")
            ->execute([$_SESSION['user_id'], "Menghapus pengajuan ID: {$id_pengajuan} ({$nama_debitur})"]);

        $pdo->commit();

        // Redirect ke dashboard yang sesuai, bukan kembali ke detail
        if ($_SESSION['role'] === 'Superadmin') {
            header('Location: ' . BASE_URL . '/admin/dashboard.php?msg=deleted');
        } elseif ($_SESSION['role'] === 'analis') {
            header('Location: ' . BASE_URL . '/analis/riwayat.php?msg=deleted');
        } else {
            header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/dashboard.php?msg=deleted');
        }
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        logError('detail_action delete failed', ['id' => $id_pengajuan, 'err' => $e->getMessage()]);
        die('Gagal menghapus pengajuan. Silakan coba lagi atau hubungi administrator.');
    }
}

if ($action === 'kirim_ulang' && $id_pengajuan > 0) {
    // Only owner (analis) can send ulang
    $stmt = $pdo->prepare("SELECT input_by, status_pengajuan FROM pengajuan_kredit WHERE id_pengajuan = ?");
    $stmt->execute([$id_pengajuan]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) die('Pengajuan tidak ditemukan.');
    if ($_SESSION['user_id'] != $r['input_by'] && $_SESSION['role'] !== 'Superadmin') die('Tidak memiliki izin untuk mengirim ulang.');
    if (!in_array($r['status_pengajuan'], ['revisi','ditolak'])) die('Pengajuan tidak dalam status revisi/ditolak.');

    $catatan = trim($_POST['catatan'] ?? 'Kirim ulang setelah revisi');
    $res = processApproval($pdo, $id_pengajuan, 'analis', $_SESSION['user_id'], 'kirim_ulang', $catatan);
    if ($res['success']) {
        header('Location: detail.php?id=' . $id_pengajuan . '&msg=kirim_ulang_ok');
        exit;
    } else {
        die('Gagal mengirim ulang: ' . htmlspecialchars($res['message']));
    }
}

header('Location: index.php');
exit;
