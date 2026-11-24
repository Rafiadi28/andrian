<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/AnalisisKreditImporter.php';
requireSameRole('analis');

// 1. ACTION: DOWNLOAD TEMPLATE RESMI
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    $templatePath = __DIR__ . '/../assets/template/Template_Import_Analisis_Kredit_Bank_Wonosobo.xlsx';
    if (!file_exists($templatePath)) {
        http_response_code(404);
        die("File template tidak ditemukan di server.");
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Template_Import_Analisis_Kredit_Bank_Wonosobo.xlsx"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($templatePath));
    readfile($templatePath);
    exit;
}

$page_title = 'Import Excel Analisis Kredit';
$page_subtitle = 'Unggah berkas template Excel untuk otomatisasi pengisian form analisis kredit';

$flash_success = '';
$flash_error = '';
$preview_data = null;
$validation_result = null;

// 2. ACTION: CONFIRM & SAVE KE DATABASE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_save') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Token keamanan (CSRF) tidak valid atau sesi telah kedaluwarsa. Silakan ulangi upload.';
    } else {
        $sessionToken = $_POST['preview_token'] ?? '';
        if (empty($_SESSION['import_preview_data']) || empty($_SESSION['import_preview_token']) || $_SESSION['import_preview_token'] !== $sessionToken) {
            $flash_error = 'Data preview tidak ditemukan atau telah disimpan sebelumnya. Silakan unggah file kembali.';
        } else {
            $parsedData = $_SESSION['import_preview_data'];
            try {
                $importer = new AnalisisKreditImporter(__DIR__ . '/../assets/template/Template_Import_Analisis_Kredit_Bank_Wonosobo.xlsx');
                // Simpan ke database
                $newId = $importer->saveToDatabase($pdo, $parsedData, (int) $_SESSION['user_id']);

                // Bersihkan sesi preview
                unset($_SESSION['import_preview_data']);
                unset($_SESSION['import_preview_token']);

                // Redirect dengan sukses
                $_SESSION['flash_import_success'] = [
                    'id_pengajuan' => $newId,
                    'nama_debitur' => $parsedData['nasabah']['nama_debitur'] ?? '',
                    'plafon' => $parsedData['pinjaman']['plafon_pengajuan'] ?? 0,
                    'agunan_count' => count($parsedData['agunan'] ?? [])
                ];
                header("Location: import_excel.php?status=success");
                exit;
            } catch (Exception $e) {
                $flash_error = 'Gagal menyimpan ke database: ' . $e->getMessage();
            }
        }
    }
}

// 3. ACTION: UPLOAD & PROCESS PREVIEW
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_excel') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } elseif (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['excel_file']['error'] ?? -1;
        $flash_error = 'Gagal mengunggah file Excel (Kode error: ' . $errCode . '). Pastikan file tidak rusak.';
    } else {
        $file = $_FILES['excel_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'xlsx') {
            $flash_error = 'Format file tidak didukung! Format file wajib berekstensi .xlsx (Microsoft Excel OpenXML).';
        } elseif ($file['size'] > 15 * 1024 * 1024) {
            $flash_error = 'Ukuran file terlalu besar! Maksimal ukuran file yang diizinkan adalah 15 MB.';
        } else {
            // Pindahkan ke temporary path
            $tempDir = __DIR__ . '/../backups/temp';
            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0755, true);
            }
            $tempFilePath = $tempDir . '/import_' . $_SESSION['user_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.xlsx';

            if (!move_uploaded_file($file['tmp_name'], $tempFilePath)) {
                $flash_error = 'Gagal memindahkan file upload ke direktori sementara server.';
            } else {
                try {
                    $importer = new AnalisisKreditImporter($tempFilePath);
                    $parsed = $importer->parse();
                    $valResult = $importer->validate($parsed, $pdo);

                    $preview_data = $parsed;
                    $validation_result = $valResult;

                    // Simpan ke sesi jika valid atau hanya warning
                    if ($valResult['is_valid']) {
                        $token = bin2hex(random_bytes(16));
                        $_SESSION['import_preview_data'] = $parsed;
                        $_SESSION['import_preview_token'] = $token;
                    }

                    // Hapus file temp setelah selesai dibaca
                    @unlink($tempFilePath);
                } catch (Exception $e) {
                    @unlink($tempFilePath);
                    $flash_error = 'Kesalahan membaca file Excel: ' . $e->getMessage();
                }
            }
        }
    }
}

// Cek Flash Message sukses setelah redirect
if (isset($_GET['status']) && $_GET['status'] === 'success' && !empty($_SESSION['flash_import_success'])) {
    $info = $_SESSION['flash_import_success'];
    $flash_success = "Data analisis kredit calon debitur <strong>" . htmlspecialchars($info['nama_debitur']) . "</strong> berhasil diimport dan disimpan ke database (ID Pengajuan: <strong>#" . (int) $info['id_pengajuan'] . "</strong>).";
}

// Ambil riwayat 8 import / draft pengajuan terakhir oleh analis ini
$stmtHist = $pdo->prepare("SELECT id_pengajuan, nama_debitur, nik, jumlah_kredit, status_pengajuan, tanggal_analisa, created_at FROM pengajuan_kredit WHERE input_by = ? ORDER BY id_pengajuan DESC LIMIT 8");
$stmtHist->execute([$_SESSION['user_id']]);
$recentImports = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Bank Wonosobo</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .import-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .banner-card {
            background: linear-gradient(135deg, #1b365d 0%, #2b5288 100%);
            color: #ffffff;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(27, 54, 93, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .banner-info h2 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            font-weight: 700;
        }
        .banner-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
            line-height: 1.5;
            max-width: 650px;
        }
        .btn-download-template {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            background: #ffffff;
            color: #1b365d;
            padding: 0.85rem 1.4rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        .btn-download-template:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .upload-dropzone {
            background: #ffffff;
            border: 2px dashed #94a3b8;
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .upload-dropzone:hover, .upload-dropzone.dragover {
            border-color: #1b365d;
            background: #f8fafc;
        }
        .upload-dropzone input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .upload-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 1rem;
            color: #1b365d;
        }
        .validation-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.85rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
        }
        .badge-valid { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef08a; color: #854d0e; }
        .badge-error { background: #fee2e2; color: #991b1b; }

        .preview-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .preview-header {
            padding: 1.25rem 1.75rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .preview-nav {
            display: flex;
            gap: 0.5rem;
            padding: 0.75rem 1.75rem;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            overflow-x: auto;
        }
        .preview-nav-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: transparent;
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .preview-nav-btn.active {
            background: #1b365d;
            color: #ffffff;
        }
        .preview-tab-pane {
            display: none;
            padding: 1.75rem;
        }
        .preview-tab-pane.active {
            display: block;
        }
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
        }
        .preview-field {
            background: #f8fafc;
            padding: 0.85rem 1rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .preview-field label {
            display: block;
            font-size: 0.78rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 0.35rem;
        }
        .preview-field .value {
            font-size: 0.95rem;
            color: #1e293b;
            font-weight: 600;
            word-break: break-word;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .data-table th, .data-table td {
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        .data-table th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 600;
        }
        .data-table tr:hover {
            background: #f8fafc;
        }
        .alert-box {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        .alert-success { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
        .alert-error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
        .alert-warning { background: #fef08a; border: 1px solid #fde047; color: #854d0e; }
        .btn-confirm-save {
            background: #16a34a;
            color: #ffffff;
            border: none;
            padding: 0.85rem 1.75rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }
        .btn-confirm-save:hover {
            background: #15803d;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);
        }
        .btn-cancel {
            background: #64748b;
            color: #ffffff;
            border: none;
            padding: 0.85rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <?php include __DIR__ . '/../includes/page_header.inc.php'; ?>

        <div class="import-container">
            <?php if (!empty($flash_success)): ?>
                <div class="alert-box alert-success">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div>
                        <div style="font-size: 1.05rem;"><?= $flash_success ?></div>
                        <?php if (!empty($_SESSION['flash_import_success']['id_pengajuan'])): ?>
                            <div style="margin-top: 1rem; display: flex; gap: 0.75rem;">
                                <a href="input.php?id=<?= (int)$_SESSION['flash_import_success']['id_pengajuan'] ?>" class="btn btn-primary" style="font-weight: bold;">
                                    Buka Form Analisis Kredit &rarr;
                                </a>
                                <a href="import_excel.php" class="btn btn-secondary">
                                    Upload File Lainnya
                                </a>
                            </div>
                            <?php unset($_SESSION['flash_import_success']); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($flash_error)): ?>
                <div class="alert-box alert-error">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div><strong>Terjadi Kesalahan:</strong> <?= htmlspecialchars($flash_error) ?></div>
                </div>
            <?php endif; ?>

            <!-- BANNER DOWNLOAD TEMPLATE -->
            <div class="banner-card">
                <div class="banner-info">
                    <h2>Template Excel Analisis Kredit Otomatis</h2>
                    <p>Unduh template resmi 10 sheet yang telah disesuaikan dengan formulir analisis kredit Bank Wonosobo. Isi data calon debitur di Excel lalu unggah kembali untuk otomatis mengisi semua tab formulir aplikasi.</p>
                </div>
                <div>
                    <a href="?action=download_template" class="btn-download-template">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Download Template Excel (.xlsx)
                    </a>
                </div>
            </div>

            <!-- AREA UPLOAD (HANYA DITAMPILKAN JIKA BELUM ADA PREVIEW DATA VALID) -->
            <?php if (!$preview_data): ?>
                <div class="card" style="margin-bottom: 2rem;">
                    <h3 style="margin-top: 0; font-size: 1.15rem; color: #1e293b; margin-bottom: 1rem;">Upload Berkas Excel Analisis Kredit</h3>
                    
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="action" value="upload_excel">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                        <div class="upload-dropzone" id="dropzone" onclick="document.getElementById('excelFileInput').click();">
                            <input type="file" id="excelFileInput" name="excel_file" accept=".xlsx" onchange="handleFileSelect(this)">
                            <div class="upload-icon">
                                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            </div>
                            <h4 style="margin: 0 0 0.5rem 0; font-size: 1.1rem; color: #1e293b;" id="fileNameDisplay">Klik atau Seret Berkas Excel (.xlsx) ke Sini</h4>
                            <p style="margin: 0; color: #64748b; font-size: 0.88rem;">Mendukung berkas Microsoft Excel (.xlsx) dengan ukuran maksimal 15 MB.</p>
                        </div>

                        <div style="margin-top: 1.25rem; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" id="btnSubmitUpload" style="padding: 0.75rem 1.5rem; font-weight: 600;">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 0.35rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                Proses & Tampilkan Preview
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- AREA PREVIEW & KONFIRMASI SIMPAN -->
            <?php if ($preview_data && $validation_result): ?>
                <div class="preview-card">
                    <div class="preview-header">
                        <div>
                            <h3 style="margin: 0 0 0.35rem 0; font-size: 1.25rem; color: #1b365d;">
                                Verifikasi Data Import: <?= htmlspecialchars($preview_data['nasabah']['nama_debitur'] ?? 'Tanpa Nama') ?>
                            </h3>
                            <span style="font-size: 0.85rem; color: #64748b;">
                                NIK: <?= htmlspecialchars($preview_data['nasabah']['nik'] ?? '-') ?> &bull; 
                                Plafon: Rp <?= number_format(SimpleXlsxReader::parseCurrencyValue($preview_data['pinjaman']['plafon_pengajuan'] ?? 0), 0, ',', '.') ?> &bull; 
                                Agunan: <?= count($preview_data['agunan'] ?? []) ?> Objek
                            </span>
                        </div>
                        <div>
                            <?php if ($validation_result['is_valid']): ?>
                                <span class="validation-badge badge-valid">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                    DATA VALID & SIAP DISIMPAN
                                </span>
                            <?php else: ?>
                                <span class="validation-badge badge-error">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    <?= $validation_result['error_count'] ?> KESALAHAN DITEMUKAN
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TAB NAVIGATION PREVIEW -->
                    <div class="preview-nav">
                        <button type="button" class="preview-nav-btn active" onclick="switchPreviewTab('p-nasabah', this)">1. Debitur & Pinjaman</button>
                        <button type="button" class="preview-nav-btn" onclick="switchPreviewTab('p-usaha', this)">2. Pekerjaan & Usaha</button>
                        <button type="button" class="preview-nav-btn" onclick="switchPreviewTab('p-keuangan', this)">3. Neraca & Cashflow</button>
                        <button type="button" class="preview-nav-btn" onclick="switchPreviewTab('p-agunan', this)">4. Data Agunan (<?= count($preview_data['agunan'] ?? []) ?>)</button>
                        <button type="button" class="preview-nav-btn" onclick="switchPreviewTab('p-6c', this)">5. Analisis 6C & CDD</button>
                        <button type="button" class="preview-nav-btn" onclick="switchPreviewTab('p-validasi', this)">
                            Status Validasi 
                            <?php if (!$validation_result['is_valid']): ?>
                                <span style="background: #ef4444; color: #fff; padding: 1px 6px; border-radius: 10px; font-size: 0.75rem;"><?= $validation_result['error_count'] ?></span>
                            <?php elseif ($validation_result['warning_count'] > 0): ?>
                                <span style="background: #f59e0b; color: #fff; padding: 1px 6px; border-radius: 10px; font-size: 0.75rem;"><?= $validation_result['warning_count'] ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- TAB 1: DEBITUR & PINJAMAN -->
                    <div class="preview-tab-pane active" id="p-nasabah">
                        <h4 style="margin-top: 0; color: #1e293b; margin-bottom: 1rem;">Data Calon Debitur & Pengajuan Pinjaman</h4>
                        <div class="preview-grid">
                            <div class="preview-field">
                                <label>Nama Lengkap Debitur</label>
                                <div class="value"><?= htmlspecialchars($preview_data['nasabah']['nama_debitur'] ?? '-') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Nomor NIK / KTP</label>
                                <div class="value"><?= htmlspecialchars($preview_data['nasabah']['nik'] ?? '-') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Tempat, Tanggal Lahir</label>
                                <div class="value"><?= htmlspecialchars($preview_data['nasabah']['tempat_lahir'] ?? '-') ?>, <?= htmlspecialchars($preview_data['nasabah']['tanggal_lahir'] ?? '-') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Nama Ibu Kandung</label>
                                <div class="value"><?= htmlspecialchars($preview_data['nasabah']['nama_ibu_kandung'] ?? '-') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Status Perkawinan</label>
                                <div class="value"><?= strtoupper(htmlspecialchars($preview_data['nasabah']['status_perkawinan'] ?? 'lajang')) ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Nomor Handphone / WA</label>
                                <div class="value"><?= htmlspecialchars($preview_data['nasabah']['no_hp'] ?? '-') ?></div>
                            </div>
                            <div class="preview-field" style="grid-column: 1 / -1;">
                                <label>Alamat Lengkap KTP</label>
                                <div class="value"><?= htmlspecialchars($preview_data['nasabah']['alamat_ktp'] ?? '-') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Plafon Pinjaman</label>
                                <div class="value" style="color: #1b365d; font-size: 1.1rem;">
                                    Rp <?= number_format(SimpleXlsxReader::parseCurrencyValue($preview_data['pinjaman']['plafon_pengajuan'] ?? 0), 0, ',', '.') ?>
                                </div>
                            </div>
                            <div class="preview-field">
                                <label>Jangka Waktu & Sistem Angsuran</label>
                                <div class="value"><?= (int)($preview_data['pinjaman']['jangka_waktu_bulan'] ?? 12) ?> Bulan &bull; <?= htmlspecialchars($preview_data['pinjaman']['sistem_angsuran'] ?? 'ANUITAS') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Suku Bunga</label>
                                <div class="value"><?= htmlspecialchars($preview_data['pinjaman']['suku_bunga_tahunan'] ?? 15) ?>% per tahun</div>
                            </div>
                            <div class="preview-field" style="grid-column: 1 / -1;">
                                <label>Tujuan Pinjaman</label>
                                <div class="value"><?= htmlspecialchars($preview_data['pinjaman']['tujuan_pinjaman'] ?? '-') ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: PEKERJAAN & USAHA -->
                    <div class="preview-tab-pane" id="p-usaha">
                        <h4 style="margin-top: 0; color: #1e293b; margin-bottom: 1rem;">Data Pekerjaan & Analisis Usaha</h4>
                        <div class="preview-grid">
                            <div class="preview-field">
                                <label>Pekerjaan Debitur</label>
                                <div class="value"><?= htmlspecialchars($preview_data['pekerjaan']['jenis_pekerjaan'] ?? '-') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Nama Usaha / Perusahaan</label>
                                <div class="value"><?= htmlspecialchars($preview_data['usaha']['nama_usaha'] ?? $preview_data['pekerjaan']['nama_instansi_usaha'] ?? '-') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Bidang Usaha</label>
                                <div class="value"><?= htmlspecialchars($preview_data['usaha']['bidang_usaha'] ?? '-') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Lama Usaha</label>
                                <div class="value"><?= htmlspecialchars($preview_data['usaha']['lama_usaha'] ?? '-') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Omzet Penjualan per Bulan</label>
                                <div class="value">Rp <?= number_format(SimpleXlsxReader::parseCurrencyValue($preview_data['usaha']['omzet_per_bulan'] ?? 0), 0, ',', '.') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Pendapatan Bersih Usaha</label>
                                <div class="value" style="color: #16a34a;">Rp <?= number_format(SimpleXlsxReader::parseCurrencyValue($preview_data['usaha']['pendapatan_bersih_usaha'] ?? 0), 0, ',', '.') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Nama Suami / Istri</label>
                                <div class="value"><?= htmlspecialchars($preview_data['pekerjaan']['nama_pasangan'] ?? '-') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Pekerjaan Pasangan</label>
                                <div class="value"><?= htmlspecialchars($preview_data['pekerjaan']['pekerjaan_pasangan'] ?? '-') ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: NERACA & KEUANGAN -->
                    <div class="preview-tab-pane" id="p-keuangan">
                        <h4 style="margin-top: 0; color: #1e293b; margin-bottom: 1rem;">Data Neraca Keuangan & Repayment Capacity</h4>
                        <div class="preview-grid">
                            <div class="preview-field">
                                <label>Aktiva: Kas & Tabungan</label>
                                <div class="value">Rp <?= number_format(SimpleXlsxReader::parseCurrencyValue($preview_data['keuangan']['aktiva_kas'] ?? 0) + SimpleXlsxReader::parseCurrencyValue($preview_data['keuangan']['aktiva_tabungan'] ?? 0), 0, ',', '.') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Aktiva: Tanah & Bangunan</label>
                                <div class="value">Rp <?= number_format(SimpleXlsxReader::parseCurrencyValue($preview_data['keuangan']['aktiva_tanah'] ?? 0), 0, ',', '.') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Aktiva: Kendaraan & Stok</label>
                                <div class="value">Rp <?= number_format(SimpleXlsxReader::parseCurrencyValue($preview_data['keuangan']['aktiva_kendaraan'] ?? 0) + SimpleXlsxReader::parseCurrencyValue($preview_data['keuangan']['aktiva_stok'] ?? 0), 0, ',', '.') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Total Aset / Aktiva</label>
                                <div class="value" style="color: #1b365d;">Rp <?= number_format(SimpleXlsxReader::parseCurrencyValue($preview_data['keuangan']['total_aktiva'] ?? 0), 0, ',', '.') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Pengeluaran Rumah Tangga</label>
                                <div class="value">Rp <?= number_format(SimpleXlsxReader::parseCurrencyValue($preview_data['keuangan']['pengeluaran_rumah_tangga'] ?? 0), 0, ',', '.') ?></div>
                            </div>
                            <div class="preview-field">
                                <label>Total Pengeluaran Rutin</label>
                                <div class="value">Rp <?= number_format(SimpleXlsxReader::parseCurrencyValue($preview_data['keuangan']['total_pengeluaran'] ?? 0), 0, ',', '.') ?></div>
                            </div>
                            <div class="preview-field" style="grid-column: 1 / -1; background: #f0fdf4; border-color: #86efac;">
                                <label style="color: #166534;">Repayment Capacity (Kemampuan Mengangsur per Bulan)</label>
                                <div class="value" style="color: #15803d; font-size: 1.25rem;">
                                    Rp <?= number_format(SimpleXlsxReader::parseCurrencyValue($preview_data['keuangan']['repayment_capacity'] ?? 0), 0, ',', '.') ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: DATA AGUNAN -->
                    <div class="preview-tab-pane" id="p-agunan">
                        <h4 style="margin-top: 0; color: #1e293b; margin-bottom: 1rem;">Daftar Objek Agunan (<?= count($preview_data['agunan'] ?? []) ?> Objek)</h4>
                        <?php if (empty($preview_data['agunan'])): ?>
                            <p style="color: #64748b; font-style: italic;">Tidak ada data agunan yang terisi di dalam file Excel.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Jenis Agunan</th>
                                            <th>No. Dokumen</th>
                                            <th>Atas Nama</th>
                                            <th>Lokasi / Alamat</th>
                                            <th>Nilai Pasar</th>
                                            <th>Taksasi (%)</th>
                                            <th>Nilai Taksasi Bank</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $totTaksasi = 0;
                                        foreach ($preview_data['agunan'] as $idx => $ag): 
                                            $taksasi = SimpleXlsxReader::parseCurrencyValue($ag['nilai_taksasi'] ?? 0);
                                            $totTaksasi += $taksasi;
                                        ?>
                                            <tr>
                                                <td><?= $idx + 1 ?></td>
                                                <td><strong><?= strtoupper(htmlspecialchars($ag['jenis_agunan'] ?? '-')) ?></strong></td>
                                                <td><?= htmlspecialchars($ag['nomor_dokumen'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($ag['nama_pemilik'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($ag['alamat_agunan'] ?? '-') ?></td>
                                                <td>Rp <?= number_format(SimpleXlsxReader::parseCurrencyValue($ag['nilai_pasar'] ?? 0), 0, ',', '.') ?></td>
                                                <td><?= htmlspecialchars($ag['persen_taksasi'] ?? 70) ?>%</td>
                                                <td style="font-weight: bold; color: #1b365d;">Rp <?= number_format($taksasi, 0, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr style="background: #f8fafc; font-weight: bold;">
                                            <td colspan="7" style="text-align: right;">TOTAL TAKSASI AGUNAN:</td>
                                            <td style="color: #16a34a; font-size: 1rem;">Rp <?= number_format($totTaksasi, 0, ',', '.') ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TAB 5: ANALISIS 6C & CDD -->
                    <div class="preview-tab-pane" id="p-6c">
                        <h4 style="margin-top: 0; color: #1e293b; margin-bottom: 1rem;">Penilaian Analisis 6C & Data CDD</h4>
                        <div class="preview-grid" style="margin-bottom: 1.5rem;">
                            <div class="preview-field">
                                <label>Character</label>
                                <div class="value">Skor: <?= htmlspecialchars($preview_data['analisis_6c']['score_character'] ?? 4) ?> / 5</div>
                            </div>
                            <div class="preview-field">
                                <label>Capacity</label>
                                <div class="value">Skor: <?= htmlspecialchars($preview_data['analisis_6c']['score_capacity'] ?? 5) ?> / 5</div>
                            </div>
                            <div class="preview-field">
                                <label>Capital</label>
                                <div class="value">Skor: <?= htmlspecialchars($preview_data['analisis_6c']['score_capital'] ?? 5) ?> / 5</div>
                            </div>
                            <div class="preview-field">
                                <label>Collateral</label>
                                <div class="value">Skor: <?= htmlspecialchars($preview_data['analisis_6c']['score_collateral'] ?? 5) ?> / 5</div>
                            </div>
                            <div class="preview-field">
                                <label>Condition</label>
                                <div class="value">Skor: <?= htmlspecialchars($preview_data['analisis_6c']['score_condition'] ?? 4) ?> / 5</div>
                            </div>
                            <div class="preview-field">
                                <label>Rekomendasi 6C</label>
                                <div class="value" style="color: #16a34a;"><?= htmlspecialchars($preview_data['analisis_6c']['rekomendasi_6c'] ?? 'LAYAK') ?></div>
                            </div>
                        </div>

                        <div class="preview-field" style="margin-bottom: 1rem;">
                            <label>Catatan Kesimpulan 6C</label>
                            <div class="value" style="font-weight: normal;"><?= nl2br(htmlspecialchars($preview_data['analisis_6c']['kesimpulan_6c'] ?? $preview_data['analisis_6c']['catatan_character'] ?? '-')) ?></div>
                        </div>

                        <div class="preview-field">
                            <label>Data Ahli Waris (CDD)</label>
                            <div class="value"><?= htmlspecialchars($preview_data['cdd']['nama_ahli_waris'] ?? '-') ?> (Hubungan: <?= htmlspecialchars($preview_data['cdd']['hubungan_ahli_waris'] ?? 'Keluarga') ?>)</div>
                        </div>
                    </div>

                    <!-- TAB 6: STATUS VALIDASI & CATATAN -->
                    <div class="preview-tab-pane" id="p-validasi">
                        <h4 style="margin-top: 0; color: #1e293b; margin-bottom: 1rem;">Hasil Pengecekan Sistem</h4>

                        <?php if (!empty($validation_result['errors'])): ?>
                            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                                <h5 style="margin: 0 0 0.5rem 0; color: #991b1b; font-size: 0.95rem;">Ditemukan Kesalahan Wajib (Penyimpanan Ditolak Sementara):</h5>
                                <ul style="margin: 0; padding-left: 1.25rem; color: #991b1b; font-size: 0.9rem;">
                                    <?php foreach ($validation_result['errors'] as $err): ?>
                                        <li><strong>[<?= htmlspecialchars($err['sheet']) ?> - <?= htmlspecialchars($err['kolom']) ?>]</strong>: <?= htmlspecialchars($err['pesan']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($validation_result['warnings'])): ?>
                            <div style="background: #fffbeb; border: 1px solid #fef08a; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                                <h5 style="margin: 0 0 0.5rem 0; color: #854d0e; font-size: 0.95rem;">Catatan & Peringatan:</h5>
                                <ul style="margin: 0; padding-left: 1.25rem; color: #854d0e; font-size: 0.9rem;">
                                    <?php foreach ($validation_result['warnings'] as $warn): ?>
                                        <li><strong>[<?= htmlspecialchars($warn['sheet']) ?> - <?= htmlspecialchars($warn['kolom']) ?>]</strong>: <?= htmlspecialchars($warn['pesan']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($validation_result['errors']) && empty($validation_result['warnings'])): ?>
                            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1rem; color: #166534;">
                                <strong>Semua data valid!</strong> Seluruh kolom wajib dan format data telah memenuhi ketentuan.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- FOOTER ACTIONS -->
                    <div style="padding: 1.25rem 1.75rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <a href="import_excel.php" class="btn-cancel">
                            &larr; Batalkan & Unggah Ulang
                        </a>

                        <?php if ($validation_result['is_valid']): ?>
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Apakah Anda yakin ingin menyimpan seluruh data analisis kredit ini ke database?');">
                                <input type="hidden" name="action" value="confirm_save">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                <input type="hidden" name="preview_token" value="<?= htmlspecialchars($_SESSION['import_preview_token'] ?? '') ?>">

                                <button type="submit" class="btn-confirm-save">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                    Konfirmasi & Simpan ke Database
                                </button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary" disabled style="cursor: not-allowed; opacity: 0.6;">
                                Perbaiki Kesalahan Sebelum Menyimpan
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- RIWAYAT PENGAJUAN / DRAFT ANALIS -->
            <div class="card" style="margin-top: 2rem;">
                <h3 style="margin-top: 0; font-size: 1.15rem; color: #1e293b; margin-bottom: 1rem;">Riwayat Pengajuan Kredit Terakhir Anda</h3>
                
                <?php if (empty($recentImports)): ?>
                    <p style="color: #64748b; font-style: italic; margin: 0;">Belum ada pengajuan kredit yang Anda buat.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama Debitur</th>
                                    <th>NIK</th>
                                    <th>Plafon Pengajuan</th>
                                    <th>Status Saat Ini</th>
                                    <th>Tgl Analisa</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentImports as $row): ?>
                                    <tr>
                                        <td><strong>#<?= $row['id_pengajuan'] ?></strong></td>
                                        <td><strong><?= htmlspecialchars($row['nama_debitur']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['nik']) ?></td>
                                        <td>Rp <?= number_format($row['jumlah_kredit'], 0, ',', '.') ?></td>
                                        <td>
                                            <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; background: #e2e8f0; color: #334155;">
                                                <?= strtoupper(htmlspecialchars($row['status_pengajuan'])) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($row['tanggal_analisa'] ?? '-') ?></td>
                                        <td>
                                            <a href="input.php?id=<?= $row['id_pengajuan'] ?>" class="btn btn-primary" style="padding: 4px 10px; font-size: 0.85rem; text-decoration: none;">
                                                Buka Form Analisis &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function handleFileSelect(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                document.getElementById('fileNameDisplay').innerHTML = 'Berkas terpilih: <strong>' + file.name + '</strong> (' + (file.size / 1024).toFixed(1) + ' KB)';
                document.getElementById('dropzone').style.borderColor = '#16a34a';
                document.getElementById('dropzone').style.backgroundColor = '#f0fdf4';
            }
        }

        const dropzone = document.getElementById('dropzone');
        if (dropzone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('dragover');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('dragover');
                }, false);
            });

            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length) {
                    document.getElementById('excelFileInput').files = files;
                    handleFileSelect(document.getElementById('excelFileInput'));
                }
            }, false);
        }

        function switchPreviewTab(tabId, btn) {
            document.querySelectorAll('.preview-tab-pane').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.preview-nav-btn').forEach(el => el.classList.remove('active'));

            const target = document.getElementById(tabId);
            if (target) {
                target.classList.add('active');
            }
            if (btn) {
                btn.classList.add('active');
            }
        }
    </script>
</body>
</html>
