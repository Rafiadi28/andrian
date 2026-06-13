<?php
/**
 * API Endpoint: Save Compliance Assessment
 * 
 * Purpose: Menyimpan hasil compliance assessment ke database
 * Digunakan oleh: analis (create initial) atau kepatuhan (update assessment)
 * 
 * POST parameters:
 * - action: 'create' atau 'update'
 * - id_pengajuan: ID pengajuan kredit
 * - check: array checklist items (key => value)
 * - ket: array keterangan untuk checklist
 * - fasilitas_rek: array no rekening fasilitas
 * - fasilitas_akad: array tanggal akad
 * - fasilitas_jtempo: array jatuh tempo
 * - fasilitas_kol: array kolektibilitas
 * - fasilitas_plafond: array plafond fasilitas
 * - fasilitas_saldo: array saldo fasilitas
 * - note_check: array catatan existing (dok, putus, ikat)
 * - note_ket: array keterangan catatan existing
 * - kesimpulan: text kesimpulan
 * - rekomendasi: text rekomendasi
 * - marketing: nama marketing/officer
 * - csrf_token: CSRF token
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid.']);
    exit;
}

// Check role - hanya analis, kasubag_analis, dan kepatuhan yang bisa save assessment
$allowed_roles = ['analis', 'kasubag_analis', 'kepatuhan'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menyimpan assessment.']);
    exit;
}

try {
    $action = trim($_POST['action'] ?? '');
    $id_pengajuan = (int)($_POST['id_pengajuan'] ?? 0);

    if (!in_array($action, ['create', 'update'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action tidak valid.']);
        exit;
    }

    if ($id_pengajuan <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID pengajuan tidak valid.']);
        exit;
    }

    // Verify pengajuan exists
    $stmt = $pdo->prepare("SELECT id_pengajuan FROM pengajuan_kredit WHERE id_pengajuan = ?");
    $stmt->execute([$id_pengajuan]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pengajuan kredit tidak ditemukan.']);
        exit;
    }

    // ===== COLLECT CHECKLIST DATA =====
    $checklist = [];
    if (isset($_POST['check']) && is_array($_POST['check'])) {
        foreach ($_POST['check'] as $key => $val) {
            $key_clean = trim($key);
            if (!empty($key_clean) && in_array($val, ['comply', 'not_comply', 'na'], true)) {
                $checklist[$key_clean] = [
                    'val' => $val,
                    'ket' => trim($_POST['ket'][$key] ?? '')
                ];
            }
        }
    }

    // ===== COLLECT FASILITAS DATA =====
    $fasilitas = [];
    if (isset($_POST['fasilitas_lembaga']) && is_array($_POST['fasilitas_lembaga'])) {
        foreach ($_POST['fasilitas_lembaga'] as $i => $lembaga) {
            $lembaga = trim($lembaga);
            if (!empty($lembaga)) {
                $fasilitas[] = [
                    'lembaga' => $lembaga,
                    'baki_debet' => str_replace(',', '', trim($_POST['fasilitas_baki'][$i] ?? '')) ?: '0',
                    'kolektibilitas' => trim($_POST['fasilitas_kol'][$i] ?? ''),
                    'keterangan' => trim($_POST['fasilitas_ket'][$i] ?? ''),
                ];
            }
        }
    }

    // ===== COLLECT CATATAN EXISTING =====
    $catatan = [];
    if (isset($_POST['note_check']) && is_array($_POST['note_check'])) {
        foreach ($_POST['note_check'] as $key => $val) {
            $key_clean = trim($key);
            if (!empty($key_clean) && in_array($val, ['comply', 'not_comply', 'na'], true)) {
                $catatan[$key_clean] = [
                    'val' => $val,
                    'ket' => trim($_POST['note_ket'][$key] ?? '')
                ];
            }
        }
    }

    $kesimpulan = trim($_POST['kesimpulan'] ?? '');
    $rekomendasi = trim($_POST['rekomendasi'] ?? '');
    $marketing = trim($_POST['marketing'] ?? '');
    $hasil_kepatuhan = trim($_POST['hasil_kepatuhan'] ?? '');
    $catatan_hasil = trim($_POST['catatan_hasil'] ?? '');
    $tanggal_assessment = date('Y-m-d');

    // ===== VALIDATE HASIL_KEPATUHAN =====
    if (empty($hasil_kepatuhan)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Hasil Kepatuhan (COMPLY atau NOT COMPLY) harus dipilih!'
        ]);
        exit;
    }

    if (!in_array($hasil_kepatuhan, ['COMPLY', 'NOT_COMPLY'], true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Hasil Kepatuhan harus COMPLY atau NOT COMPLY!'
        ]);
        exit;
    }

    // ===== VALIDATE CATATAN_HASIL IF NOT_COMPLY =====
    if ($hasil_kepatuhan === 'NOT_COMPLY' && empty($catatan_hasil)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Catatan Hasil wajib diisi ketika Hasil Kepatuhan adalah NOT COMPLY!'
        ]);
        exit;
    }

    // ===== CHECK IF RECORD EXISTS =====
    $stmt = $pdo->prepare("SELECT id_assessment FROM assessment_kepatuhan WHERE id_pengajuan = ?");
    $stmt->execute([$id_pengajuan]);
    $existing = $stmt->fetchColumn();

    if ($action === 'create' && $existing) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Assessment untuk pengajuan ini sudah ada. Gunakan action update untuk mengubahnya.'
        ]);
        exit;
    }

    if ($action === 'update' && !$existing) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Assessment untuk pengajuan ini tidak ditemukan. Gunakan action create untuk membuat baru.'
        ]);
        exit;
    }

    // ===== SAVE TO DATABASE =====
    if ($action === 'create') {
        $stmt = $pdo->prepare("
            INSERT INTO assessment_kepatuhan 
            (id_pengajuan, id_user, tanggal_assessment, checklist_data, fasilitas_existing, 
             catatan_existing, hasil_kepatuhan, catatan_hasil, kesimpulan, rekomendasi, marketing)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $id_pengajuan,
            $_SESSION['user_id'],
            $tanggal_assessment,
            json_encode($checklist),
            json_encode($fasilitas),
            json_encode($catatan),
            $hasil_kepatuhan,
            $catatan_hasil,
            $kesimpulan,
            $rekomendasi,
            $marketing
        ]);

        $id_assessment = $pdo->lastInsertId();

        // Log activity
        $log_msg = "Assessment kepatuhan dibuat oleh " . ($_SESSION['role'] ?? 'unknown') . " untuk pengajuan #" . $id_pengajuan;
        logActivity($_SESSION['user_id'], $log_msg);

        // ===== CREATE NOTIFICATIONS FOR NEXT ROLE (KASUBAG ANALIS) =====
        $stmtPK = $pdo->prepare("SELECT nama_debitur, jumlah_kredit, posisi_saat_ini FROM pengajuan_kredit WHERE id_pengajuan = ?");
        $stmtPK->execute([$id_pengajuan]);
        $pkInfo = $stmtPK->fetch(PDO::FETCH_ASSOC);
        
        if ($pkInfo && isset($pkInfo['posisi_saat_ini'])) {
            // Get users of next role (should be kasubag_analis after kepatuhan)
            $stmtNextRole = $pdo->prepare("SELECT id_user, nama FROM users WHERE role = 'kasubag_analis' AND status_jabatan = 'aktif'");
            $stmtNextRole->execute();
            $nextRoleUsers = $stmtNextRole->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($nextRoleUsers as $user) {
                createNotification(
                    $user['id_user'],
                    $id_pengajuan,
                    'auto_routed',
                    'Assessment Kepatuhan Selesai - Siap untuk Kasubag Analis',
                    "Pengajuan kredit a.n {$pkInfo['nama_debitur']} (Rp " . number_format($pkInfo['jumlah_kredit'], 0, ',', '.') . ") telah selesai di-assess oleh Dept. Kepatuhan dan siap untuk ditinjau oleh Kasubag Analis.",
                    'kepatuhan',
                    'kasubag_analis'
                );
            }
        }

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Assessment berhasil dibuat.',
            'id_assessment' => $id_assessment
        ]);

    } else { // UPDATE
        $stmt = $pdo->prepare("
            UPDATE assessment_kepatuhan 
            SET checklist_data = ?, fasilitas_existing = ?, catatan_existing = ?,
                hasil_kepatuhan = ?, catatan_hasil = ?, kesimpulan = ?, rekomendasi = ?, 
                marketing = ?, updated_at = NOW()
            WHERE id_pengajuan = ?
        ");

        $stmt->execute([
            json_encode($checklist),
            json_encode($fasilitas),
            json_encode($catatan),
            $hasil_kepatuhan,
            $catatan_hasil,
            $kesimpulan,
            $rekomendasi,
            $marketing,
            $id_pengajuan
        ]);

        // Log activity
        $log_msg = "Assessment kepatuhan diperbarui oleh " . ($_SESSION['role'] ?? 'unknown') . " untuk pengajuan #" . $id_pengajuan;
        logActivity($_SESSION['user_id'], $log_msg);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Assessment berhasil diperbarui.'
        ]);
    }

} catch (Exception $e) {
    error_log("API Error in save_assessment_kepatuhan.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat menyimpan assessment. Silakan coba lagi.'
    ]);
}
?>
