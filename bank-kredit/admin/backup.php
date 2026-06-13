<?php
require_once __DIR__ . '/../includes/functions.php';

// Only Admin can access
requireSameRole('Superadmin');

$message = '';
$backupFile = '';

$csrf_ok = ($_SERVER['REQUEST_METHOD'] !== 'POST') || verifyCsrfToken($_POST['csrf_token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$csrf_ok) {
    $message = 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
}

if ($csrf_ok && isset($_POST['backup'])) {
    try {
        // Koneksi backup = kredensial yang sama dengan config/database.php (sudah termuat lewat functions.php)
        if (!isset($host, $user, $pass, $db)) {
            throw new Exception('Konfigurasi database tidak lengkap.');
        }

        // Define backup file name
        $backupName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupDir = __DIR__ . '/../backups/';
        $backupPath = $backupDir . $backupName;

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        // Custom PHP Backup Logic (No mysqldump dependency)
        $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $handle = fopen($backupPath, 'wb');
        if ($handle === false) {
            throw new Exception('Tidak dapat membuat file backup.');
        }
        fwrite($handle, "-- Backup " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $table) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $table)) {
                continue;
            }
            $qt = '`' . str_replace('`', '``', $table) . '`';
            $result = $conn->query("SELECT * FROM {$qt}");
            $num_fields = $result->columnCount();

            fwrite($handle, "DROP TABLE IF EXISTS {$qt};\n");
            $row2 = $conn->query("SHOW CREATE TABLE {$qt}")->fetch(PDO::FETCH_NUM);
            fwrite($handle, "\n" . $row2[1] . ";\n\n");

            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                fwrite($handle, "INSERT INTO {$qt} VALUES(");
                for ($j = 0; $j < $num_fields; $j++) {
                    if ($j > 0) {
                        fwrite($handle, ',');
                    }
                    if (!array_key_exists($j, $row) || $row[$j] === null) {
                        fwrite($handle, 'NULL');
                    } else {
                        fwrite($handle, $conn->quote($row[$j]));
                    }
                }
                fwrite($handle, ");\n");
            }
            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        $message = "Backup Berhasil! File tersimpan di: " . $backupName;
        $backupFile = $backupName;
        auditLog($pdo, $_SESSION['user_id'], 'Membuat backup database: ' . $backupName);

    } catch (Exception $e) {
        logError('admin backup failed', ['err' => $e->getMessage()]);
        $message = 'Backup gagal. Silakan coba lagi atau hubungi administrator.';
    }

}

// Handle Delete Backup
if ($csrf_ok && isset($_POST['delete_backup'])) {
    $fileToDelete = basename((string)($_POST['filename'] ?? ''));
    if ($fileToDelete === '' || !preg_match('/^backup_[a-zA-Z0-9._-]+\.sql$/', $fileToDelete)) {
        $message = 'Nama file tidak valid.';
    } else {
        $filePath = __DIR__ . '/../backups/' . $fileToDelete;
        if (file_exists($filePath)) {
            unlink($filePath);
            $message = "File backup berhasil dihapus: " . $fileToDelete;
            auditLog($pdo, $_SESSION['user_id'], 'Menghapus file backup: ' . $fileToDelete);
        } else {
            $message = "File tidak ditemukan.";
        }
    }
}

// Listing Backup Files
$backupDir = __DIR__ . '/../backups/';
$backups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $backups[] = $file;
        }
    }
}

// Download Handler
if (isset($_GET['download'])) {
    $file = basename((string) $_GET['download']);
    if ($file === '' || !preg_match('/^backup_[a-zA-Z0-9._-]+\.sql$/', $file)) {
        $message = 'Permintaan download tidak valid.';
    } else {
        $filePath = $backupDir . $file;
        if (file_exists($filePath)) {
            auditLog($pdo, $_SESSION['user_id'], 'Download backup: ' . $file);
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
        $message = 'File tidak ditemukan.';
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Database - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;700&display=swap"
        rel="stylesheet">
    <style>
        .backup-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .file-list {
            margin-top: 2rem;
        }

        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .file-item:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div class="header-section">
            <h2 class="page-title">Backup Database</h2>
            <p>Kelola cadangan data sistem</p>
        </div>

        <?php if ($message): ?>
            <?php
            $msgOk = (strpos($message, 'Backup Berhasil') === 0 || strpos($message, 'File backup berhasil') === 0);
            ?>
            <div class="<?= $msgOk ? 'alert alert-success' : 'alert alert-error' ?>"
                style="padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="backup-card">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" name="backup" class="btn btn-primary"
                    style="width: 100%; display: flex; justify-content: center; gap: 0.5rem; align-items: center;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                    Buat Backup Database Baru
                </button>
            </form>

            <div class="file-list">
                <h3 style="margin-bottom: 1rem; font-size: 1.1rem; color: #64748b;">Riwayat Backup</h3>
                <?php if (empty($backups)): ?>
                    <p style="color: #94a3b8; text-align: center;">Belum ada file backup.</p>
                <?php else: ?>
                    <?php foreach ($backups as $file): ?>
                        <div class="file-item">
                            <span style="font-family: monospace; color: #334155;"><?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?></span>
                            <div style="display:flex; gap:0.5rem;">
                                <a href="?download=<?= urlencode($file) ?>" class="btn btn-secondary"
                                    style="padding: 0.5rem 1rem; font-size: 0.9rem;">Download</a>
                                <form method="POST" onsubmit="return confirm('Hapus file backup ini?');" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="filename" value="<?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" name="delete_backup" class="btn btn-secondary"
                                        style="padding: 0.5rem 1rem; font-size: 0.9rem; background:#fee2e2; color:#ef4444; border-color:#fca5a5;">Hapus</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>