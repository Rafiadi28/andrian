<?php
/**
 * Master Pejabat Management API
 * Endpoints: GET (list/detail), POST (create), PUT (update), DELETE
 */

header('Content-Type: application/json');
http_response_code(400);

// Check request method
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

// Get action from query/body
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Include dependencies
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check authorization (admin only)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'Superadmin', 'direksi', 'kadiv_kredit'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

try {
    switch ($action) {
        case 'list':
            // GET /api/master_pejabat.php?action=list
            $stmt = $pdo->prepare("
                SELECT id_pejabat, role, nama, jabatan, tanda_tangan, stempel, status, updated_at
                FROM master_pejabat
                ORDER BY FIELD(role, 'analis', 'kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama'), created_at ASC
            ");
            $stmt->execute();
            $pejabat_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $pejabat_list,
                'count' => count($pejabat_list)
            ]);
            break;

        case 'detail':
            // GET /api/master_pejabat.php?action=detail&id=1
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT id_pejabat, role, nama, jabatan, tanda_tangan, stempel, status, created_at, updated_at
                FROM master_pejabat
                WHERE id_pejabat = ?
            ");
            $stmt->execute([$id]);
            $pejabat = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pejabat) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Data pejabat tidak ditemukan']);
                exit;
            }

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $pejabat]);
            break;

        case 'create':
            // POST /api/master_pejabat.php with action=create
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Gunakan method POST']);
                exit;
            }

            $role = trim($_POST['role'] ?? '');
            $nama = trim($_POST['nama'] ?? '');
            $jabatan = trim($_POST['jabatan'] ?? '');
            $status = $_POST['status'] ?? 'aktif';

            // Validation
            if (empty($role)) {
                echo json_encode(['success' => false, 'message' => 'Role harus diisi']);
                exit;
            }
            if (empty($nama)) {
                echo json_encode(['success' => false, 'message' => 'Nama harus diisi']);
                exit;
            }
            if (empty($jabatan)) {
                echo json_encode(['success' => false, 'message' => 'Jabatan harus diisi']);
                exit;
            }

            // Check if role already exists
            $stmt = $pdo->prepare("SELECT id_pejabat FROM master_pejabat WHERE role = ?");
            $stmt->execute([$role]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Role sudah terdaftar']);
                exit;
            }

            // Handle file uploads
            $tanda_tangan_file = null;
            $stempel_file = null;

            if (isset($_FILES['tanda_tangan']) && $_FILES['tanda_tangan']['error'] === UPLOAD_ERR_OK) {
                $tanda_tangan_file = handleFileUpload($_FILES['tanda_tangan'], 'pejabat');
                if (!$tanda_tangan_file) {
                    echo json_encode(['success' => false, 'message' => 'Gagal upload tanda tangan']);
                    exit;
                }
            }

            if (isset($_FILES['stempel']) && $_FILES['stempel']['error'] === UPLOAD_ERR_OK) {
                $stempel_file = handleFileUpload($_FILES['stempel'], 'pejabat');
                if (!$stempel_file) {
                    echo json_encode(['success' => false, 'message' => 'Gagal upload stempel']);
                    exit;
                }
            }

            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO master_pejabat (role, nama, jabatan, tanda_tangan, stempel, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$role, $nama, $jabatan, $tanda_tangan_file, $stempel_file, $status]);
            $id_pejabat = $pdo->lastInsertId();

            // Log activity
            logActivity($_SESSION['user_id'], "Master pejabat baru dibuat: $nama ($role)");

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Pejabat berhasil ditambahkan',
                'id_pejabat' => $id_pejabat
            ]);
            break;

        case 'update':
            // PUT /api/master_pejabat.php with action=update
            if ($method !== 'POST' && $method !== 'PUT') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Gunakan method POST atau PUT']);
                exit;
            }

            $id = intval($_POST['id_pejabat'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
                exit;
            }

            // Check if exists
            $stmt = $pdo->prepare("SELECT role, tanda_tangan, stempel FROM master_pejabat WHERE id_pejabat = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Pejabat tidak ditemukan']);
                exit;
            }

            $nama = trim($_POST['nama'] ?? '');
            $jabatan = trim($_POST['jabatan'] ?? '');
            $status = $_POST['status'] ?? 'aktif';

            if (empty($nama)) {
                echo json_encode(['success' => false, 'message' => 'Nama harus diisi']);
                exit;
            }
            if (empty($jabatan)) {
                echo json_encode(['success' => false, 'message' => 'Jabatan harus diisi']);
                exit;
            }

            // Handle file updates
            $tanda_tangan_file = $existing['tanda_tangan'];
            $stempel_file = $existing['stempel'];

            if (isset($_FILES['tanda_tangan']) && $_FILES['tanda_tangan']['error'] === UPLOAD_ERR_OK) {
                // Delete old file if exists
                if (!empty($existing['tanda_tangan']) && file_exists('assets/uploads/' . $existing['tanda_tangan'])) {
                    @unlink('assets/uploads/' . $existing['tanda_tangan']);
                }
                $tanda_tangan_file = handleFileUpload($_FILES['tanda_tangan'], 'pejabat');
                if (!$tanda_tangan_file) {
                    echo json_encode(['success' => false, 'message' => 'Gagal upload tanda tangan']);
                    exit;
                }
            }

            if (isset($_FILES['stempel']) && $_FILES['stempel']['error'] === UPLOAD_ERR_OK) {
                // Delete old file if exists
                if (!empty($existing['stempel']) && file_exists('assets/uploads/' . $existing['stempel'])) {
                    @unlink('assets/uploads/' . $existing['stempel']);
                }
                $stempel_file = handleFileUpload($_FILES['stempel'], 'pejabat');
                if (!$stempel_file) {
                    echo json_encode(['success' => false, 'message' => 'Gagal upload stempel']);
                    exit;
                }
            }

            // Update
            $stmt = $pdo->prepare("
                UPDATE master_pejabat
                SET nama = ?, jabatan = ?, tanda_tangan = ?, stempel = ?, status = ?, updated_at = NOW()
                WHERE id_pejabat = ?
            ");
            $stmt->execute([$nama, $jabatan, $tanda_tangan_file, $stempel_file, $status, $id]);

            // Log activity
            logActivity($_SESSION['user_id'], "Master pejabat diperbarui: ID #$id - $nama");

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Pejabat berhasil diperbarui']);
            break;

        case 'delete':
            // DELETE /api/master_pejabat.php with action=delete
            if ($method !== 'POST' && $method !== 'DELETE') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Gunakan method POST atau DELETE']);
                exit;
            }

            $id = intval($_POST['id_pejabat'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
                exit;
            }

            // Check if exists
            $stmt = $pdo->prepare("SELECT nama, role, tanda_tangan, stempel FROM master_pejabat WHERE id_pejabat = ?");
            $stmt->execute([$id]);
            $pejabat = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pejabat) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Pejabat tidak ditemukan']);
                exit;
            }

            // Delete files
            if (!empty($pejabat['tanda_tangan']) && file_exists('assets/uploads/' . $pejabat['tanda_tangan'])) {
                @unlink('assets/uploads/' . $pejabat['tanda_tangan']);
            }
            if (!empty($pejabat['stempel']) && file_exists('assets/uploads/' . $pejabat['stempel'])) {
                @unlink('assets/uploads/' . $pejabat['stempel']);
            }

            // Delete record
            $stmt = $pdo->prepare("DELETE FROM master_pejabat WHERE id_pejabat = ?");
            $stmt->execute([$id]);

            // Log activity
            logActivity($_SESSION['user_id'], "Master pejabat dihapus: {$pejabat['nama']} ({$pejabat['role']})");

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Pejabat berhasil dihapus']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
    }
} catch (Exception $e) {
    error_log('master_pejabat.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server']);
}

/**
 * Handle file upload for signatures/stamps
 */
function handleFileUpload($file, $folder = 'pejabat') {
    $upload_dir = 'assets/uploads/' . $folder . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }

    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return false;
    }

    $filename = uniqid('sig_') . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $upload_path = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return $folder . '/' . $filename;
    }

    return false;
}
?>
