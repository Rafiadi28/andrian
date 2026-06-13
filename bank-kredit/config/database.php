<?php
// ============================================================
// KONFIGURASI DATABASE
// ============================================================
// LOKAL (Laragon)  : host=localhost, user=root, pass='', db=bank_kredit_db
// HOSTINGER        : host=localhost, user=u123456_xxx, pass=xxx, db=u123456_xxx
//
// CARA DEPLOY KE HOSTINGER:
//   1. hPanel → Database → MySQL Databases → buat DB + User baru
//   2. Salin nama DB, User, Password ke variabel di bawah
//   3. Ganti BASE_URL:
//      - Root domain  : define('BASE_URL', '');
//      - Subfolder    : define('BASE_URL', '/bank-kredit');
//   4. Set $bkForceProduction = true
// ============================================================

$host = 'localhost';
$user = 'root';          // ← HOSTINGER: ganti dengan DB User dari hPanel
$pass = '';              // ← HOSTINGER: ganti dengan DB Password Anda
$db   = 'bank_kredit_db'; // ← HOSTINGER: ganti dengan DB Name dari hPanel

define('BASE_URL', '/andrian/bank-kredit'); // ← HOSTINGER: '' (root) atau '/subfolder'

/**
 * Produksi: unggahan ditolak jika PHP tidak punya ext fileinfo; MIME wajib dicek saat finfo tersedia.
 * Aktifkan: set env BK_PRODUCTION=1 (true/yes/on), atau set $bkForceProduction = true di bawah.
 */
$bkForceProduction = false;

if (!defined('BK_PRODUCTION')) {
    $bkEnv = strtolower(trim((string) getenv('BK_PRODUCTION')));
    $fromEnv = in_array($bkEnv, ['1', 'true', 'yes', 'on'], true);
    define('BK_PRODUCTION', $bkForceProduction || $fromEnv);
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Migrasi skema idempotent — di-throttle agar tidak membebani setiap request (tetap aman: skema akan dicek lagi setelah interval)
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $schemaStamp = $logDir . '/.schema_ensure_stamp';
    $schemaIntervalSec = 90;
    if (!is_file($schemaStamp) || (time() - filemtime($schemaStamp)) >= $schemaIntervalSec) {
        require_once __DIR__ . '/../includes/schema_realtime_migrate.php';
        bankKreditEnsureSchema($pdo);
        @touch($schemaStamp);
    }
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die("Sistem sedang mengalami gangguan koneksi database. Silakan hubungi administrator.");
}
?>
