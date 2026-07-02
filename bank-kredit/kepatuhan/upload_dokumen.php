<?php
require_once __DIR__ . '/../includes/functions.php';
requireSameRole('kepatuhan');

// Ensure DB table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS dokumen_kepatuhan (
        id_dokumen INT AUTO_INCREMENT PRIMARY KEY,
        id_user INT NOT NULL,
        judul VARCHAR(255) NOT NULL,
        deskripsi TEXT NULL,
        kategori VARCHAR(100) NOT NULL DEFAULT 'umum',
        nama_file VARCHAR(255) NOT NULL,
        nama_asli VARCHAR(255) NOT NULL,
        ukuran_file INT DEFAULT 0,
        tipe_file VARCHAR(50) DEFAULT NULL,
        tanggal_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_dok_user (id_user),
        KEY idx_dok_kategori (kategori),
        KEY idx_dok_tanggal (tanggal_upload),
        FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE RESTRICT
    )");
} catch (Exception $e) {
    // Ignore if table already exists
}

$csrf_token = generateCsrfToken();
$success_msg = '';
$error_msg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $judul = trim($_POST['judul'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $kategori = trim($_POST['kategori'] ?? 'umum');
        $allowed_kategori = ['umum', 'regulasi', 'kebijakan', 'laporan', 'pengumuman', 'panduan', 'lainnya'];
        if (!in_array($kategori, $allowed_kategori, true)) $kategori = 'umum';

        if (empty($judul)) {
            $error_msg = 'Judul dokumen wajib diisi.';
        } elseif (!isset($_FILES['file_dokumen']) || $_FILES['file_dokumen']['error'] !== UPLOAD_ERR_OK) {
            $err_codes = [
                UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi batas server.',
                UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas form.',
                UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian.',
                UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih.',
                UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan.',
                UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
            ];
            $errCode = $_FILES['file_dokumen']['error'] ?? UPLOAD_ERR_NO_FILE;
            $error_msg = $err_codes[$errCode] ?? 'Gagal upload file (kode: ' . $errCode . ')';
        } else {
            $file = $_FILES['file_dokumen'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip'];
            $max_size = 10 * 1024 * 1024; // 10 MB

            if (!in_array($ext, $allowed_ext, true)) {
                $error_msg = 'Format file tidak diizinkan. Format yang diterima: ' . implode(', ', $allowed_ext);
            } elseif ($file['size'] > $max_size) {
                $error_msg = 'Ukuran file maksimal 10 MB.';
            } else {
                // Image files: validate MIME
                if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                    $mimeErr = bankKreditVerifyUploadMime($file['tmp_name'], $file['name']);
                    if ($mimeErr !== null) {
                        $error_msg = $mimeErr;
                    }
                }

                if (empty($error_msg)) {
                    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'kepatuhan' . DIRECTORY_SEPARATOR;
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $newName = 'kep_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                        $stmt = $pdo->prepare("INSERT INTO dokumen_kepatuhan 
                            (id_user, judul, deskripsi, kategori, nama_file, nama_asli, ukuran_file, tipe_file)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            htmlspecialchars($judul, ENT_QUOTES, 'UTF-8'),
                            htmlspecialchars($deskripsi, ENT_QUOTES, 'UTF-8'),
                            $kategori,
                            $newName,
                            htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'),
                            $file['size'],
                            $ext,
                        ]);
                        log_activity($pdo, $_SESSION['user_id'], "Upload Dokumen Kepatuhan: " . htmlspecialchars($judul));
                        $success_msg = 'Dokumen berhasil diupload dan tersedia untuk semua role.';
                    } else {
                        $error_msg = 'Gagal menyimpan file ke server.';
                    }
                }
            }
        }
    }
}

// Fetch existing documents uploaded by this user
$myDocs = $pdo->prepare("SELECT d.*, u.nama as nama_uploader 
    FROM dokumen_kepatuhan d 
    LEFT JOIN users u ON d.id_user = u.id_user 
    WHERE d.id_user = ? 
    ORDER BY d.tanggal_upload DESC 
    LIMIT 20");
$myDocs->execute([$_SESSION['user_id']]);
$my_documents = $myDocs->fetchAll(PDO::FETCH_ASSOC);

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    if (verifyCsrfToken($_GET['csrf'] ?? '')) {
        $stmtDel = $pdo->prepare("SELECT nama_file FROM dokumen_kepatuhan WHERE id_dokumen = ? AND id_user = ?");
        $stmtDel->execute([$delId, $_SESSION['user_id']]);
        $docRow = $stmtDel->fetch(PDO::FETCH_ASSOC);
        if ($docRow) {
            $filePath = dirname(__DIR__) . '/assets/uploads/kepatuhan/' . $docRow['nama_file'];
            if (file_exists($filePath)) @unlink($filePath);
            $pdo->prepare("DELETE FROM dokumen_kepatuhan WHERE id_dokumen = ? AND id_user = ?")->execute([$delId, $_SESSION['user_id']]);
            log_activity($pdo, $_SESSION['user_id'], "Hapus Dokumen Kepatuhan ID: $delId");
            header('Location: upload_dokumen.php?deleted=1');
            exit;
        }
    }
}
if (isset($_GET['deleted'])) $success_msg = 'Dokumen berhasil dihapus.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Dokumen Kepatuhan</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .upload-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }
        .upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 2.5rem;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
            background: #f8fafc;
        }
        .upload-zone:hover, .upload-zone.drag-over { border-color: #6366f1; background:#eef2ff; }
        .upload-zone svg { width:48px;height:48px;stroke:#94a3b8;margin-bottom:0.75rem; }
        .upload-zone p { margin:0.4rem 0; color:#64748b; font-size:0.9rem; }
        .upload-zone strong { color:#1e293b; font-size:1rem; }
        .file-input-hidden { display:none; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        @media(max-width:640px){ .form-row{grid-template-columns:1fr;} }
        .doc-table { width:100%; border-collapse:collapse; font-size:0.9rem; }
        .doc-table th { background:#f1f5f9; padding:10px 12px; text-align:left; font-weight:600; color:#475569; }
        .doc-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        .doc-table tr:hover td { background:#f8fafc; }
        .badge-kategori { padding:2px 10px; border-radius:999px; font-size:0.78rem; font-weight:600; text-transform:uppercase; }
        .badge-umum { background:#e0f2fe; color:#0369a1; }
        .badge-regulasi { background:#fce7f3; color:#be185d; }
        .badge-kebijakan { background:#e0e7ff; color:#3730a3; }
        .badge-laporan { background:#dcfce7; color:#15803d; }
        .badge-pengumuman { background:#fef3c7; color:#92400e; }
        .badge-panduan { background:#f3e8ff; color:#6d28d9; }
        .badge-lainnya { background:#f1f5f9; color:#475569; }
        .file-size { color:#94a3b8; font-size:0.82rem; }
        .alert { padding:1rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-weight:500; }
        .alert-success { background:#dcfce7; color:#166534; border:1px solid #86efac; }
        .alert-error { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
        .btn-delete { background:none;border:none;cursor:pointer;color:#dc2626;padding:0 6px;font-size:1.1rem; }
        .btn-delete:hover { color:#b91c1c; }
        .info-box { background:#eff6ff;border-left:4px solid #3b82f6;padding:1rem 1.25rem;border-radius:8px;font-size:0.9rem;color:#1e40af;margin-bottom:1.5rem; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="container">
    <?php
    $page_title = 'Upload Dokumen Kepatuhan';
    $page_subtitle = 'Upload dokumen kepatuhan yang dapat dilihat oleh semua role';
    include __DIR__ . '/../includes/page_header.inc.php';
    ?>

    <?php if ($success_msg): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="info-box">
        📢 <strong>Informasi:</strong> Dokumen yang diupload dapat dilihat oleh semua role (Analis, Kasubag, Kabag, Kadiv, Direktur, dan Superadmin).
        Hanya role Kepatuhan yang dapat mengupload dan menghapus dokumen.
    </div>

    <!-- Upload Form -->
    <div class="upload-card">
        <h3 style="margin:0 0 1.5rem 0; color:#1e293b; font-size:1.1rem;">📄 Upload Dokumen Baru</h3>
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="form-row" style="margin-bottom:1rem;">
                <div>
                    <label style="display:block;margin-bottom:0.4rem;font-weight:500;color:#475569;">
                        Judul Dokumen <span style="color:#dc2626">*</span>
                    </label>
                    <input type="text" name="judul" required maxlength="255"
                        placeholder="Contoh: Surat Edaran No. 001/KEP/2026"
                        style="width:100%;padding:0.75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.95rem;">
                </div>
                <div>
                    <label style="display:block;margin-bottom:0.4rem;font-weight:500;color:#475569;">Kategori</label>
                    <select name="kategori" style="width:100%;padding:0.75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.95rem;">
                        <option value="umum">Umum</option>
                        <option value="regulasi">Regulasi</option>
                        <option value="kebijakan">Kebijakan</option>
                        <option value="laporan">Laporan</option>
                        <option value="pengumuman">Pengumuman</option>
                        <option value="panduan">Panduan</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:1rem;">
                <label style="display:block;margin-bottom:0.4rem;font-weight:500;color:#475569;">Deskripsi (opsional)</label>
                <textarea name="deskripsi" rows="2" maxlength="1000"
                    placeholder="Keterangan singkat tentang dokumen ini..."
                    style="width:100%;padding:0.75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.95rem;resize:vertical;"></textarea>
            </div>

            <!-- Drag & drop upload zone -->
            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
                <strong id="dropLabel">Klik atau seret file ke sini</strong>
                <p>PDF, Word, Excel, PPT, Gambar, ZIP (maks. 10 MB)</p>
                <p id="selectedFileName" style="color:#6366f1; font-weight:600; margin-top:0.5rem;"></p>
            </div>
            <input type="file" id="fileInput" name="file_dokumen" class="file-input-hidden"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip" required>

            <button type="submit" class="btn btn-primary" style="margin-top:1.25rem; width:100%; padding:0.85rem; font-size:1rem;">
                ⬆️ Upload Dokumen
            </button>
        </form>
    </div>

    <!-- My Uploaded Documents -->
    <div class="upload-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
            <h3 style="margin:0;color:#1e293b;font-size:1.1rem;">📁 Dokumen yang Saya Upload</h3>
            <a href="hasil_dokumen.php" class="btn btn-secondary btn-sm">Lihat Semua Dokumen →</a>
        </div>

        <?php if (empty($my_documents)): ?>
            <div style="text-align:center;padding:2rem;color:#94a3b8;">
                <p>Belum ada dokumen yang diupload.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
            <table class="doc-table">
                <thead>
                    <tr>
                        <th>Judul Dokumen</th>
                        <th>Kategori</th>
                        <th>Ukuran</th>
                        <th>Tanggal Upload</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_documents as $doc): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($doc['judul']) ?></div>
                            <?php if ($doc['deskripsi']): ?>
                                <div style="font-size:0.82rem;color:#64748b;margin-top:2px;"><?= htmlspecialchars(mb_substr($doc['deskripsi'],0,80)) ?><?= strlen($doc['deskripsi'])>80?'...':'' ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge-kategori badge-<?= htmlspecialchars($doc['kategori']) ?>"><?= htmlspecialchars(ucfirst($doc['kategori'])) ?></span></td>
                        <td class="file-size"><?= number_format($doc['ukuran_file']/1024, 1) ?> KB</td>
                        <td><?= date('d/M/Y H:i', strtotime($doc['tanggal_upload'])) ?></td>
                        <td class="text-center" style="white-space:nowrap;">
                            <a href="../assets/uploads/kepatuhan/<?= htmlspecialchars($doc['nama_file']) ?>" 
                               target="_blank" class="btn btn-secondary btn-sm" style="margin-right:4px;">⬇️ Unduh</a>
                            <a href="upload_dokumen.php?delete=<?= $doc['id_dokumen'] ?>&csrf=<?= urlencode($csrf_token) ?>"
                               onclick="return confirm('Hapus dokumen ini?')"
                               class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;">🗑 Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const zone = document.getElementById('uploadZone');
const input = document.getElementById('fileInput');
const label = document.getElementById('dropLabel');
const fname = document.getElementById('selectedFileName');

input.addEventListener('change', function() {
    if (this.files.length > 0) {
        fname.textContent = '✅ File dipilih: ' + this.files[0].name;
        label.textContent = 'File siap diupload';
    }
});

zone.addEventListener('dragover', function(e) {
    e.preventDefault();
    zone.classList.add('drag-over');
});
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', function(e) {
    e.preventDefault();
    zone.classList.remove('drag-over');
    if (e.dataTransfer.files.length > 0) {
        input.files = e.dataTransfer.files;
        fname.textContent = '✅ File dipilih: ' + e.dataTransfer.files[0].name;
        label.textContent = 'File siap diupload';
    }
});
</script>
</body>
</html>
