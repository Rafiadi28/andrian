<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && @realpath((string) $_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// normalize legacy role names stored in session
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $_SESSION['role'] = 'Superadmin';
}

/**
 * Session hardening with inactivity timeout.
 */
function enforceSessionSecurity()
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user_id'])) {
        return;
    }

    $timeout = 1800; // 30 menit
    $last = intval($_SESSION['last_activity'] ?? 0);
    if ($last > 0 && (time() - $last) > $timeout) {
        session_unset();
        session_destroy();
        header("Location: " . BASE_URL . "/auth/login.php?expired=1");
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * ============ FORMATTING UTILITIES ============
 * Standardized formatting for common data types
 */

/**
 * Format date in standardized Indonesian format
 */
function formatTanggal($date, $format = 'd M Y')
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    try {
        return date($format, strtotime($date));
    } catch (Exception $e) {
        return '-';
    }
}

/**
 * Format date with time
 */
function formatTanggalJam($datetime, $format = 'd M Y H:i')
{
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    try {
        return date($format, strtotime($datetime));
    } catch (Exception $e) {
        return '-';
    }
}

/**
 * Format weight/gram value consistently
 */
function formatBerat($gram)
{
    if (empty($gram) || $gram == 0) {
        return '-';
    }
    return number_format((float)$gram, 3, ',', '.') . ' g';
}

/**
 * Format percentage value
 */
function formatPersen($value)
{
    if (empty($value) || $value == 0) {
        return '0%';
    }
    return number_format((float)$value, 2, ',', '.') . '%';
}

/**
 * Get user friendly status label
 */
function getStatusLabel($status)
{
    $labels = [
        'draft' => 'Draft',
        'diajukan' => 'Diajukan',
        'proses' => 'Proses Review',
        'kasubag' => 'Review Kasubag',
        'kabag' => 'Review Kabag',
        'kadiv' => 'Review Kadiv',
        'direksi' => 'Review Direksi',
        'revisi' => 'Perlu Revisi',
        'ditolak' => 'Ditolak',
        'disetujui' => 'Disetujui',
        'selesai' => 'Selesai',
    ];
    return $labels[$status] ?? $status;
}

/**
 * Get role display name
 */
function getRoleDisplay($role)
{
    $displays = [
        'Superadmin' => 'Admin Sistem',
        'analis' => 'Analis Kredit',
        'kasubag_analis' => 'Kasubag Analis',
        'kabag_kredit' => 'Kabag Kredit',
        'kadiv_bisnis' => 'Kadiv Bisnis',
        'direktur_utama' => 'Direktur Utama',
        'kepatuhan' => 'Dept. Kepatuhan',
    ];
    return $displays[$role] ?? ucwords(str_replace('_', ' ', $role));
}

/**
 * CSRF helpers.
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Normalisasi catatan approval: tanpa tag HTML, panjang terbatas.
 */
function sanitizeApprovalCatatan($text)
{
    $t = trim((string) $text);
    $t = strip_tags($t);
    if (function_exists('mb_substr')) {
        return mb_substr($t, 0, 2000);
    }
    return substr($t, 0, 2000);
}

/**
 * MIME yang diizinkan per ekstensi (untuk finfo).
 */
function bankKreditMimeTypesForExtension(string $ext): array
{
    $ext = strtolower($ext);
    $map = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
    ];
    return $map[$ext] ?? [];
}

/**
 * Validasi isi file vs ekstensi.
 * - Jika finfo tersedia: MIME wajib diverifikasi (selalu ketat).
 * - Produksi (BK_PRODUCTION): tanpa finfo / finfo gagal → unggahan ditolak.
 * - Development: tanpa finfo → hanya whitelist ekstensi (kompatibilitas lokal).
 *
 * @return string|null null = lolos, string = pesan error
 */
function bankKreditVerifyUploadMime(string $tmpPath, string $originalName): ?string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = bankKreditMimeTypesForExtension($ext);
    if ($allowed === []) {
        return 'Ekstensi file tidak diperbolehkan.';
    }
    if (!is_readable($tmpPath)) {
        return 'File tidak dapat dibaca.';
    }

    $production = defined('BK_PRODUCTION') && BK_PRODUCTION;

    if (!function_exists('finfo_open')) {
        if ($production) {
            return 'Unggahan di lingkungan produksi memerlukan ekstensi PHP fileinfo. Hubungi administrator.';
        }
        return null;
    }

    $f = finfo_open(FILEINFO_MIME_TYPE);
    if (!$f) {
        if ($production) {
            return 'Tidak dapat memulai verifikasi tipe file (fileinfo). Hubungi administrator.';
        }
        return null;
    }

    $mime = finfo_file($f, $tmpPath);
    finfo_close($f);

    if ($mime === false) {
        return 'Tidak dapat memverifikasi tipe file.';
    }

    if ($mime === 'image/jpg') {
        $mime = 'image/jpeg';
    }

    if (!in_array($mime, $allowed, true)) {
        return 'Isi file tidak sesuai format yang diizinkan (validasi MIME).';
    }

    return null;
}

enforceSessionSecurity();

/**
 * ============ INPUT VALIDATION & SANITIZATION ============
 */

/**
 * Sanitize username input
 */
function sanitizeUsername($username)
{
    $username = trim((string)$username);
    $username = preg_replace('/[^a-zA-Z0-9._\-@]/', '', $username);
    return substr($username, 0, 50);
}

/**
 * Sanitize text input (remove tags, limit length)
 */
function sanitizeText($text, $maxLength = 500)
{
    $text = trim((string)$text);
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength);
    }
    return substr($text, 0, $maxLength);
}

/**
 * Sanitize numeric input
 */
function sanitizeNumber($value)
{
    $value = trim((string)$value);
    $value = preg_replace('/[^0-9.\-]/', '', $value);
    return (float)$value;
}

/**
 * Validate email format
 */
function validateEmail($email)
{
    $email = trim((string)$email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * ============ RATE LIMITING ============
 */

/**
 * Check if IP should be rate limited for login attempts
 */
function checkLoginRateLimit()
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $rateFile = $logDir . '/.login_attempts_' . md5($ip);
    $maxAttempts = 5;
    $windowSeconds = 60; // 1 minute
    
    $attempts = [];
    if (file_exists($rateFile)) {
        $contents = @file_get_contents($rateFile);
        if ($contents) {
            $attempts = json_decode($contents, true) ?? [];
            // Clean old attempts
            $attempts = array_filter($attempts, function($t) use ($windowSeconds) {
                return ($t > (time() - $windowSeconds));
            });
        }
    }
    
    if (count($attempts) >= $maxAttempts) {
        return false; // Rate limited
    }
    
    // Record this attempt
    $attempts[] = time();
    @file_put_contents($rateFile, json_encode($attempts));
    return true; // Not rate limited
}

/**
 * Log errors to file for debugging
 */
function logError($message, $context = [])
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if (!empty($context)) {
        $logMessage .= " | Context: " . json_encode($context);
    }
    
    $logMessage .= "\n";
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function requireSameRole($allowed_role)
{
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "/auth/login.php");
        exit;
    }
    // Allow Superadmin to access ANY role page
    if ($_SESSION['role'] === 'Superadmin') {
        return;
    }
    if ($_SESSION['role'] !== $allowed_role) {
        header("Location: " . BASE_URL . "/auth/login.php");
        exit;
    }
}

function requireAnyRole($allowed_roles)
{
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "/auth/login.php");
        exit;
    }
    // Allow Superadmin to access ANY role page
    if ($_SESSION['role'] === 'Superadmin') {
        return;
    }
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: " . BASE_URL . "/auth/login.php");
        exit;
    }
}

function getHierarchy()
{
    // Approval hierarchy chain - must align with posisi_saat_ini column values
    // NOTE: 'selesai' is added by the system when chain ends
    // UPDATED: Kepatuhan now INTEGRATED into approval chain (not parallel)
    return ['analis', 'kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama'];
}

/**
 * Siapa boleh MEMBUKA/VIEW detail pengajuan (semua user login bisa view, read-only)
 */
function canAccessPengajuanDetail(array $pengajuanRow)
{
    if (!isLoggedIn()) {
        return false;
    }
    // Semua user yang login bisa view detail
    return true;
}

/**
 * Siapa boleh MENGEDIT pengajuan (restricted edit access)
 * - Superadmin: semua
 * - analis: hanya pengajuan yang diinput sendiri
 * - Pejabat rantai approval + kepatuhan + support roles: semua (untuk keperluan review)
 */
function canEditPengajuan(array $pengajuanRow)
{
    if (!isLoggedIn()) {
        return false;
    }
    $role = $_SESSION['role'] ?? '';
    if ($role === 'Superadmin') {
        return true;
    }
    if ($role === 'analis') {
        return isset($pengajuanRow['input_by'])
            && (int)$pengajuanRow['input_by'] === (int)($_SESSION['user_id'] ?? 0);
    }
    $chainSansAnalis = array_values(array_filter(getHierarchy(), static function ($r) {
        return $r !== 'analis';
    }));
    $allowed = array_merge($chainSansAnalis, ['kadiv_bisnis', 'kasubag_analis']);
    return in_array($role, $allowed, true);
}

/**
 * Get maximum approval level based on loan amount
 * Ketentuan:
 * - Pengajuan < 500 juta: maksimal approval hanya sampai kadiv_bisnis
 * - Pengajuan >= 500 juta: approval sampai direktur_utama
 */
function getMaxApprovalLevel($jumlah_kredit)
{
    $THRESHOLD_AMOUNT = 500000000; // 500 juta
    
    if ($jumlah_kredit < $THRESHOLD_AMOUNT) {
        return 'kadiv_bisnis'; // Stop at Kadiv Bisnis for amounts < 500M
    }
    return 'direktur_utama'; // Continue to Direktur Utama for amounts >= 500M
}



function findNextTarget($currentRole, $pdo, $jumlah_kredit = null)
{
    $hierarchy = getHierarchy();
    $currentIndex = array_search($currentRole, $hierarchy);
    
    if ($currentIndex === false || $currentIndex >= count($hierarchy) - 1) {
        return ['role' => 'selesai', 'skipped' => []];
    }

    // Check if current role is the maximum approval level allowed for this amount
    if ($jumlah_kredit !== null) {
        $maxLevel = getMaxApprovalLevel($jumlah_kredit);
        $maxIndex = array_search($maxLevel, $hierarchy);
        
        // If we're already at max level, stop here
        if ($currentIndex >= $maxIndex) {
            return ['role' => 'selesai', 'skipped' => []];
        }
    }

    $skipped = [];
    for ($i = $currentIndex + 1; $i < count($hierarchy); $i++) {
        $role = $hierarchy[$i];

        // Check if we've reached max approval level for this amount
        if ($jumlah_kredit !== null) {
            $maxLevel = getMaxApprovalLevel($jumlah_kredit);
            // Skip to next iteration if this role exceeds max level
            if ($role === 'direktur_utama' && $maxLevel === 'kadiv_bisnis') {
                return ['role' => 'selesai', 'skipped' => array_merge($skipped, array_slice($hierarchy, $i))];
            }
        }

        // Check if ANY user in this role is active
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ? AND status_jabatan = 'aktif'");
        $stmt->execute([$role]);
        $activeCount = $stmt->fetchColumn();

        if ($activeCount > 0) {
            return ['role' => $role, 'skipped' => $skipped];
        } else {
            $skipped[] = $role;
        }
    }

    return ['role' => 'selesai', 'skipped' => $skipped];
}

function auditLog($pdo, $userId, $activity)
{
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (id_user, aktivitas) VALUES (?, ?)");
        $stmt->execute([$userId, $activity]);
    } catch (Exception $e) {
        error_log('auditLog failed: ' . $e->getMessage());
    }
}

/**
 * Enhanced audit logging with details
 */
function auditLogDetail($pdo, $userId, $activity, $details = [])
{
    $activityText = $activity;
    if (!empty($details)) {
        $activityText .= ' | ' . json_encode($details, JSON_UNESCAPED_UNICODE);
    }
    return auditLog($pdo, $userId, $activityText);
}

/**
 * Check whether an ENUM column allows a given value.
 * Returns true if the column exists and the enum contains the value.
 */
function enumAllows($pdo, $table, $column, $value)
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$col || empty($col['Type'])) return false;
        if (preg_match("/enum\((.*)\)/i", $col['Type'], $m)) {
            $inside = $m[1];
            $parts = str_getcsv($inside, ',', "'");
            foreach ($parts as $p) {
                if (trim($p, "'\"") === $value) return true;
            }
        }
    } catch (Exception $e) {
        return false;
    }
    return false;
}

function formatRupiah($angka)
{
    return "Rp " . number_format($angka, 2, ',', '.');
}

/**
 * Label status_pengajuan yang selaras dengan posisi approval (tahap workflow).
 * UPDATED: Kepatuhan role maps to 'kepatuhan' status
 */
function statusPengajuanForPipelinePosition($role_posisi)
{
    $map = [
        // 'kepatuhan' removed from pipeline — no longer an approval step
        'kasubag_analis' => 'kasubag',
        'kabag_kredit' => 'kabag',
        'kadiv_bisnis' => 'kadiv',
        'direktur_utama' => 'direksi',
    ];
    return $map[$role_posisi] ?? 'proses';
}

/**
 * Status yang dianggap "sedang dalam alur persetujuan" untuk inbox approver.
 * UPDATED: Include 'kepatuhan' status
 */
function pengajuanStatusesActivePipeline()
{
    // 'kepatuhan' removed from pipeline — no longer blocks approval routing
    return ['proses', 'diajukan', 'kasubag', 'kabag', 'kadiv', 'direksi'];
}

/** Untuk disisipkan aman ke SQL IN (...) — nilai berasal dari kode, bukan input pengguna. */
function pengajuanStatusesActivePipelineSqlIn()
{
    return "'" . implode("','", pengajuanStatusesActivePipeline()) . "'";
}

/**
 * Check if compliance assessment (assessment_kepatuhan) has been completed for an application
 * Returns: ['exists' => bool, 'is_complete' => bool, 'message' => string]
 */
function checkComplianceAssessmentStatus($pdo, $id_pengajuan)
{
    try {
        $stmt = $pdo->prepare("SELECT id_assessment, checklist_data, kesimpulan FROM assessment_kepatuhan WHERE id_pengajuan = ?");
        $stmt->execute([$id_pengajuan]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assessment) {
            return [
                'exists' => false,
                'is_complete' => false,
                'message' => 'Kepatuhan belum melakukan assessment untuk pengajuan ini.'
            ];
        }
        
        // Check if assessment has substantive data (not just empty/incomplete)
        $checklist = json_decode($assessment['checklist_data'], true);
        $has_checklist = !empty($checklist) && is_array($checklist) && count($checklist) > 0;
        $has_conclusion = !empty($assessment['kesimpulan']) && strlen(trim($assessment['kesimpulan'])) > 0;
        
        if (!$has_checklist && !$has_conclusion) {
            return [
                'exists' => true,
                'is_complete' => false,
                'message' => 'Assessment kepatuhan masih kosong atau belum lengkap. Silakan tunggu kepatuhan menyelesaikan assessment.'
            ];
        }
        
        return [
            'exists' => true,
            'is_complete' => true,
            'message' => 'Assessment kepatuhan sudah lengkap.'
        ];
    } catch (Exception $e) {
        return [
            'exists' => false,
            'is_complete' => false,
            'message' => 'Error checking assessment: ' . $e->getMessage()
        ];
    }
}

/**
 * Process an approval decision in a safe transactional way.
 * Actions: 'setuju' | 'revisi' | 'tolak' | 'kirim_ulang'
 * Returns array: ['success'=>bool, 'message'=>string]
 */
function processApproval($pdo, $id_pengajuan, $role, $user_id, $keputusan, $catatan)
{
    $catatan = sanitizeApprovalCatatan($catatan);
    try {
        $pdo->beginTransaction();

        // Lock row
        $stmt = $pdo->prepare("SELECT * FROM pengajuan_kredit WHERE id_pengajuan = ? FOR UPDATE");
        $stmt->execute([$id_pengajuan]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Pengajuan tidak ditemukan.'];
        }

        // Anti-bypass: role pemroses harus sesuai posisi saat ini.
        if ($role !== 'Superadmin' && ($row['posisi_saat_ini'] ?? '') !== $role) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Pengajuan tidak berada pada antrian role Anda.'];
        }

        // ============================================================
        // COMPLIANCE ASSESSMENT VALIDATION — DISABLED
        // Kepatuhan removed from approval chain per workflow update.
        // Existing assessment_kepatuhan data preserved for audit.
        // ============================================================

        // Normalize keputusan
        $k = strtolower(trim($keputusan));

        if ($k === 'setuju') {
            // Pass jumlah_kredit to determine max approval level based on amount
            $nextStep = findNextTarget($role, $pdo, $row['jumlah_kredit']);
            $targetRole = is_array($nextStep) && isset($nextStep['role']) ? $nextStep['role'] : $nextStep;
            $skippedRoles = is_array($nextStep) && isset($nextStep['skipped']) ? $nextStep['skipped'] : [];

            if ($targetRole === 'selesai') {
                $newStatus = 'disetujui';
                $newPos = 'selesai';
            } else {
                $candidate = statusPengajuanForPipelinePosition($targetRole);
                $newStatus = enumAllows($pdo, 'pengajuan_kredit', 'status_pengajuan', $candidate) ? $candidate : 'proses';
                $newPos = $targetRole;
            }

            $u = $pdo->prepare("UPDATE pengajuan_kredit SET status_pengajuan = ?, posisi_saat_ini = ? WHERE id_pengajuan = ?");
            $u->execute([$newStatus, $newPos, $id_pengajuan]);

            $log = $pdo->prepare("INSERT INTO approval_kredit (id_pengajuan, id_user, level_approval, keputusan, catatan) VALUES (?, ?, ?, 'setuju', ?)");
            $log->execute([$id_pengajuan, $user_id, $role, $catatan]);

            // log skipped roles if any
            foreach ($skippedRoles as $sr) {
                $s = $pdo->prepare("INSERT INTO approval_kredit (id_pengajuan, level_approval, keputusan, catatan, is_auto_skip) VALUES (?, ?, 'eskalasi_otomatis', 'Pejabat berhalangan. Diteruskan.', 1)");
                $s->execute([$id_pengajuan, $sr]);
            }

            // ===== CREATE NOTIFICATIONS FOR NEXT ROLE(S) =====
            if ($newPos !== 'selesai') {
                // Get users of next role
                $stmtNotif = $pdo->prepare("SELECT id_user, nama FROM users WHERE role = ? AND status_jabatan = 'aktif'");
                $stmtNotif->execute([$newPos]);
                $nextRoleUsers = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
                
                // Get pengajuan info for notification message
                $stmtPK = $pdo->prepare("SELECT nama_debitur, jumlah_kredit FROM pengajuan_kredit WHERE id_pengajuan = ?");
                $stmtPK->execute([$id_pengajuan]);
                $pkInfo = $stmtPK->fetch(PDO::FETCH_ASSOC);
                
                // Create notification for each user in next role
                if (!empty($nextRoleUsers) && $pkInfo) {
                    $role_display = getRoleDisplay($role);
                    $next_role_display = getRoleDisplay($newPos);
                    
                    foreach ($nextRoleUsers as $user) {
                        createNotification(
                            $user['id_user'],
                            $id_pengajuan,
                            'auto_routed',
                            "Pengajuan Dikirim ke " . $next_role_display,
                            "Pengajuan kredit a.n {$pkInfo['nama_debitur']} (Rp " . number_format($pkInfo['jumlah_kredit'], 0, ',', '.') . ") telah disetujui oleh {$role_display} dan siap untuk proses {$next_role_display}.",
                            $role,
                            $newPos
                        );
                    }
                }
            } else {
                // Notify original analis that application is completed
                $stmtAnalis = $pdo->prepare("
                    SELECT DISTINCT u.id_user 
                    FROM users u
                    LEFT JOIN approval_kredit ak ON u.id_user = ak.id_user AND ak.id_pengajuan = ?
                    WHERE u.role = 'analis' AND ak.level_approval = 'analis' LIMIT 1
                ");
                $stmtAnalis->execute([$id_pengajuan]);
                $analisId = $stmtAnalis->fetchColumn();
                
                if ($analisId) {
                    $stmtPK = $pdo->prepare("SELECT nama_debitur, jumlah_kredit FROM pengajuan_kredit WHERE id_pengajuan = ?");
                    $stmtPK->execute([$id_pengajuan]);
                    $pkInfo = $stmtPK->fetch(PDO::FETCH_ASSOC);
                    
                    if ($pkInfo) {
                        createNotification(
                            $analisId,
                            $id_pengajuan,
                            'completed',
                            "Pengajuan Kredit Disetujui",
                            "Pengajuan kredit a.n {$pkInfo['nama_debitur']} (Rp " . number_format($pkInfo['jumlah_kredit'], 0, ',', '.') . ") telah disetujui seluruhnya dan berhasil diproses.",
                            $role,
                            'analis'
                        );
                    }
                }
            }

            $pdo->commit();
            return ['success' => true, 'message' => 'Pengajuan disetujui dan diteruskan ke ' . strtoupper($newPos)];
        }

        if ($k === 'revisi') {
            // Mark for revision and return to analis. Preserve old data.
            if (enumAllows($pdo, 'pengajuan_kredit', 'status_pengajuan', 'revisi')) {
                $upd = $pdo->prepare("UPDATE pengajuan_kredit SET status_pengajuan = 'revisi', posisi_saat_ini = 'analis', revision_count = COALESCE(revision_count,0) + 1, last_revision_at = NOW(), last_revision_by = ? , last_reject_level = ? WHERE id_pengajuan = ?");
                $upd->execute([$user_id, $role, $id_pengajuan]);
            } else {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Kolom status_pengajuan tidak mendukung nilai revisi'];
            }

            $log = $pdo->prepare("INSERT INTO approval_kredit (id_pengajuan, id_user, level_approval, keputusan, catatan) VALUES (?, ?, ?, 'revisi', ?)");
            $log->execute([$id_pengajuan, $user_id, $role, $catatan]);

            // Store revision note into pengajuan_kredit.catatan_revisi if exists
            try {
                $pdo->prepare("UPDATE pengajuan_kredit SET catatan_revisi = ? WHERE id_pengajuan = ?")->execute([$catatan, $id_pengajuan]);
            } catch (Exception $e) {
                // ignore if column not exists
            }

            // ===== CREATE NOTIFICATION FOR ANALIS (REVISION) =====
            $stmtAnalis = $pdo->prepare("
                SELECT DISTINCT u.id_user 
                FROM users u
                LEFT JOIN approval_kredit ak ON u.id_user = ak.id_user AND ak.id_pengajuan = ?
                WHERE u.role = 'analis' AND ak.level_approval = 'analis' LIMIT 1
            ");
            $stmtAnalis->execute([$id_pengajuan]);
            $analisId = $stmtAnalis->fetchColumn();
            
            if ($analisId) {
                $stmtPK = $pdo->prepare("SELECT nama_debitur FROM pengajuan_kredit WHERE id_pengajuan = ?");
                $stmtPK->execute([$id_pengajuan]);
                $pkInfo = $stmtPK->fetch(PDO::FETCH_ASSOC);
                
                if ($pkInfo) {
                    $role_display = getRoleDisplay($role);
                    createNotification(
                        $analisId,
                        $id_pengajuan,
                        'revised',
                        "Pengajuan Perlu Revisi dari " . $role_display,
                        "Pengajuan kredit a.n {$pkInfo['nama_debitur']} perlu dilakukan revisi oleh {$role_display}. Catatan revisi: " . substr($catatan, 0, 100) . "...",
                        $role,
                        'analis'
                    );
                }
            }

            auditLog($pdo, $user_id, "Mengirim revisi (ID: $id_pengajuan) oleh $role");
            $pdo->commit();
            return ['success' => true, 'message' => 'Pengajuan dikembalikan untuk revisi ke analis.'];
        }

        if ($k === 'tolak') {
            // Mark as rejected and return to analis for edit (but status ditolak)
            $upd = $pdo->prepare("UPDATE pengajuan_kredit SET status_pengajuan = 'ditolak', posisi_saat_ini = 'analis', last_reject_level = ? WHERE id_pengajuan = ?");
            $upd->execute([$role, $id_pengajuan]);

            $log = $pdo->prepare("INSERT INTO approval_kredit (id_pengajuan, id_user, level_approval, keputusan, catatan) VALUES (?, ?, ?, 'tolak', ?)");
            $log->execute([$id_pengajuan, $user_id, $role, $catatan]);

            // Store rejection note if column exists
            try {
                $pdo->prepare("UPDATE pengajuan_kredit SET alasan_penolakan = ? WHERE id_pengajuan = ?")->execute([$catatan, $id_pengajuan]);
            } catch (Exception $e) {
            }

            // ===== CREATE NOTIFICATION FOR ANALIS (REJECTION) =====
            $stmtAnalis = $pdo->prepare("
                SELECT DISTINCT u.id_user 
                FROM users u
                LEFT JOIN approval_kredit ak ON u.id_user = ak.id_user AND ak.id_pengajuan = ?
                WHERE u.role = 'analis' AND ak.level_approval = 'analis' LIMIT 1
            ");
            $stmtAnalis->execute([$id_pengajuan]);
            $analisId = $stmtAnalis->fetchColumn();
            
            if ($analisId) {
                $stmtPK = $pdo->prepare("SELECT nama_debitur FROM pengajuan_kredit WHERE id_pengajuan = ?");
                $stmtPK->execute([$id_pengajuan]);
                $pkInfo = $stmtPK->fetch(PDO::FETCH_ASSOC);
                
                if ($pkInfo) {
                    $role_display = getRoleDisplay($role);
                    createNotification(
                        $analisId,
                        $id_pengajuan,
                        'rejected',
                        "Pengajuan Ditolak oleh " . $role_display,
                        "Pengajuan kredit a.n {$pkInfo['nama_debitur']} telah ditolak oleh {$role_display}. Alasan: " . substr($catatan, 0, 100) . "...",
                        $role,
                        'analis'
                    );
                }
            }

            auditLog($pdo, $user_id, "Menolak pengajuan (ID: $id_pengajuan) oleh $role");
            $pdo->commit();
            return ['success' => true, 'message' => 'Pengajuan ditolak dan dikembalikan ke analis.'];
        }

        if ($k === 'kirim_ulang' || $k === 'kirimulang' || $k === 'kirim-ulang') {
            // Only allow analis to send ulang
            if ($_SESSION['role'] !== 'analis' && $_SESSION['role'] !== 'Superadmin') {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Hanya analis yang dapat mengirim ulang pengajuan.'];
            }

            // Find last_reject_level
            $stmt2 = $pdo->prepare("SELECT last_reject_level FROM pengajuan_kredit WHERE id_pengajuan = ?");
            $stmt2->execute([$id_pengajuan]);
            $last = $stmt2->fetchColumn();
            $resumeTo = $last ?: 'kasubag_analis';

            $resumeStatus = enumAllows($pdo, 'pengajuan_kredit', 'status_pengajuan', 'diajukan') ? 'diajukan' : 'proses';
            $upd = $pdo->prepare("UPDATE pengajuan_kredit SET status_pengajuan = ?, posisi_saat_ini = ?, last_revision_at = NULL, last_revision_by = NULL, last_reject_level = NULL WHERE id_pengajuan = ?");
            $upd->execute([$resumeStatus, $resumeTo, $id_pengajuan]);

            $log = $pdo->prepare("INSERT INTO approval_kredit (id_pengajuan, id_user, level_approval, keputusan, catatan) VALUES (?, ?, ?, 'kirim_ulang', ?)");
            $log->execute([$id_pengajuan, $user_id, 'analis', $catatan]);

            auditLog($pdo, $user_id, "Mengirim ulang pengajuan (ID: $id_pengajuan) ke $resumeTo");
            $pdo->commit();
            return ['success' => true, 'message' => 'Pengajuan dikirim ulang dan dilanjutkan ke ' . strtoupper($resumeTo)];
        }

        $pdo->rollBack();
        return ['success' => false, 'message' => 'Aksi tidak dikenali.'];
    } catch (Exception $e) {
        try { $pdo->rollBack(); } catch (Exception $x) {}
        logError('processApproval error', [
            'id_pengajuan' => $id_pengajuan,
            'role' => $role,
            'user_id' => $user_id,
            'keputusan' => $keputusan,
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
        ]);
        return ['success' => false, 'message' => 'Terjadi kesalahan internal saat memproses pengajuan.'];
    }
}

/**
 * Request revision for completed/approved applications
 * Allows any role to request analis to revise an already-approved application
 * 
 * Usage: After an application is approved (status=disetujui), a higher role can send it back for revision
 * New status: 'revisi_diajukan' → signifies revision is pending analis action
 * Analis can then edit data and resubmit
 */
function requestCompletedApplicationRevision($pdo, $id_pengajuan, $requestor_role, $requestor_id, $revisi_notes)
{
    try {
        $pdo->beginTransaction();

        // Lock row
        $stmt = $pdo->prepare("SELECT id_pengajuan, status_pengajuan, posisi_saat_ini FROM pengajuan_kredit WHERE id_pengajuan = ? FOR UPDATE");
        $stmt->execute([$id_pengajuan]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Pengajuan tidak ditemukan.'];
        }

        // Only allow revision if status is 'disetujui' or 'ditolak' or similar completed statuses
        $completedStatuses = ['disetujui', 'ditolak', 'selesai'];
        if (!in_array($row['status_pengajuan'], $completedStatuses, true)) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Hanya pengajuan yang sudah diselesaikan (disetujui/ditolak) yang bisa diminta revisi.'];
        }

        // Update to revision-pending state
        if (!enumAllows($pdo, 'pengajuan_kredit', 'status_pengajuan', 'revisi_diajukan')) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Status revisi_diajukan tidak didukung di database.'];
        }

        $upd = $pdo->prepare("UPDATE pengajuan_kredit SET 
            status_pengajuan = 'revisi_diajukan', 
            posisi_saat_ini = 'analis', 
            last_revision_at = NOW(), 
            last_revision_by = ?,
            revision_count = COALESCE(revision_count, 0) + 1,
            catatan_revisi = ?
            WHERE id_pengajuan = ?");
        $upd->execute([$requestor_id, $revisi_notes, $id_pengajuan]);

        // Log approval record for audit trail
        $log = $pdo->prepare("INSERT INTO approval_kredit (id_pengajuan, id_user, level_approval, keputusan, catatan) VALUES (?, ?, ?, 'revisi_diajukan', ?)");
        $log->execute([$id_pengajuan, $requestor_id, $requestor_role, $revisi_notes]);

        // Audit log
        auditLog($pdo, $requestor_id, "Meminta revisi pengajuan yang sudah completed (ID: $id_pengajuan) oleh $requestor_role");
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Permintaan revisi berhasil. Pengajuan dikembalikan ke analis.'];
        
    } catch (Exception $e) {
        try { $pdo->rollBack(); } catch (Exception $x) {}
        logError('requestCompletedApplicationRevision error', [
            'id_pengajuan' => $id_pengajuan,
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ]);
        return ['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
    }
}

/**
 * Fetch all role labels from `roles` table (if present).
 * Returns associative array [role_key => label].
 */
function getRoleLabels()
{
    // Tabel 'roles' tidak digunakan di sistem ini — kembalikan hardcoded map
    // agar isValidRole() dan getRoleLabel() tidak melempar query ke tabel yang tidak ada.
    return [
        'Superadmin'   => 'Admin Sistem',
        'analis'       => 'Analis Kredit',
        'kasubag_analis' => 'Kasubag Analis',
        'kabag_kredit' => 'Kabag Kredit',
        'kadiv_bisnis' => 'Kadiv Bisnis',
        'direktur_utama' => 'Direktur Utama',
        'kepatuhan'    => 'Dept. Kepatuhan',
    ];
}

/**
 * Return true if the provided role key is allowable within the system.
 *
 * When the `roles` table exists we treat its contents as authoritative.
 * For backwards compatibility we also fall back to the hard‑coded
 * hierarchy (with Superadmin prepended).  This avoids sending invalid
 * values to the database enum column and triggers the "data truncated" warning.
 */
function isValidRole($role)
{
    // normalize the user input
    $role = trim((string) $role);
    if ($role === '') {
        return false;
    }

    $labels = getRoleLabels();
    if (!empty($labels)) {
        // if the table is present, check against it
        return array_key_exists($role, $labels);
    }

    // fallback to built‑in list
    $allowed = getHierarchy();
    array_unshift($allowed, 'Superadmin', 'kepatuhan');
    return in_array($role, $allowed, true);
}

/**
 * Log activity to audit_log table
 * @param int $id_user User ID performing the action
 * @param string $aktivitas Description of the activity
 * @return bool True if logged successfully, false otherwise
 */
function logActivity($id_user, $aktivitas)
{
    global $pdo;
    
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (id_user, aktivitas) VALUES (?, ?)");
        return $stmt->execute([$id_user, $aktivitas]);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notification for a user
 * @param int $id_user Target user ID
 * @param int $id_pengajuan Application ID
 * @param string $tipe_notifikasi Notification type (submitted, approved, rejected, revised, auto_routed)
 * @param string $judul_notifikasi Notification title
 * @param string $pesan_notifikasi Notification message (optional)
 * @param string $role_source Source role that triggered notification
 * @param string $role_target Target role receiving notification
 * @return bool|int Returns notification ID if successful, false otherwise
 */
function createNotification($id_user, $id_pengajuan, $tipe_notifikasi, $judul_notifikasi, $pesan_notifikasi = '', $role_source = '', $role_target = '')
{
    global $pdo;
    
    if (!$pdo || !$id_user || !$id_pengajuan) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications 
            (id_user, id_pengajuan, tipe_notifikasi, judul_notifikasi, pesan_notifikasi, role_source, role_target)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $id_user,
            $id_pengajuan,
            $tipe_notifikasi,
            $judul_notifikasi,
            $pesan_notifikasi,
            $role_source,
            $role_target
        ]);
        
        return $result ? $pdo->lastInsertId() : false;
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notifications for a user
 * @param int $id_user User ID
 * @param int $limit Number of notifications to retrieve
 * @return array Array of notifications
 */
function getUnreadNotifications($id_user, $limit = 50)
{
    global $pdo;
    
    if (!$pdo || !$id_user) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                n.*,
                pk.nama_debitur,
                pk.jumlah_kredit,
                pk.status_pengajuan
            FROM notifications n
            LEFT JOIN pengajuan_kredit pk ON n.id_pengajuan = pk.id_pengajuan
            WHERE n.id_user = ? AND n.is_read = 0
            ORDER BY n.created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$id_user, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Get count of unread notifications for a user
 * @param int $id_user User ID
 * @return int Count of unread notifications
 */
function getUnreadNotificationCount($id_user)
{
    global $pdo;
    
    if (!$pdo || !$id_user) {
        return 0;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_user = ? AND is_read = 0");
        $stmt->execute([$id_user]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error counting notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark notification as read
 * @param int $id_notification Notification ID
 * @return bool True if successful
 */
function markNotificationAsRead($id_notification)
{
    global $pdo;
    
    if (!$pdo || !$id_notification) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id_notification = ?");
        return $stmt->execute([$id_notification]);
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 * @param int $id_user User ID
 * @return bool True if successful
 */
function markAllNotificationsAsRead($id_user)
{
    global $pdo;
    
    if (!$pdo || !$id_user) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id_user = ? AND is_read = 0");
        return $stmt->execute([$id_user]);
    } catch (Exception $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify next role(s) in approval chain
 * @param int $id_pengajuan Application ID
 * @param string $current_role Current role that approved
 * @param string $action_type Type of action (approved, rejected, revised, auto_routed)
 * @param string $message Optional custom message
 * @return bool True if successful
 */
function notifyNextRole($id_pengajuan, $current_role, $action_type = 'approved', $message = '')
{
    global $pdo;
    
    if (!$pdo || !$id_pengajuan) {
        return false;
    }
    
    try {
        // Get pengajuan data
        $stmt = $pdo->prepare("SELECT id_pengajuan, nama_debitur, jumlah_kredit, posisi_saat_ini FROM pengajuan_kredit WHERE id_pengajuan = ?");
        $stmt->execute([$id_pengajuan]);
        $pengajuan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pengajuan) {
            return false;
        }
        
        $next_role = $pengajuan['posisi_saat_ini'];
        
        // Get users of next role
        $stmt = $pdo->prepare("SELECT id_user, nama FROM users WHERE role = ? AND status_jabatan = 'aktif'");
        $stmt->execute([$next_role]);
        $next_role_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create notifications for all users in next role
        $role_display = getRoleDisplay($current_role);
        $next_role_display = getRoleDisplay($next_role);
        
        if ($action_type === 'approved') {
            $title = "Pengajuan Disetujui " . $role_display;
            $detail = "Pengajuan kredit a.n {$pengajuan['nama_debitur']} (Rp " . number_format($pengajuan['jumlah_kredit'], 0, ',', '.') . ") telah disetujui oleh {$role_display} dan siap untuk proses {$next_role_display}.";
        } elseif ($action_type === 'rejected') {
            $title = "Pengajuan Ditolak " . $role_display;
            $detail = "Pengajuan kredit a.n {$pengajuan['nama_debitur']} telah ditolak oleh {$role_display}.";
        } elseif ($action_type === 'revised') {
            $title = "Pengajuan Perlu Revisi";
            $detail = "Pengajuan kredit a.n {$pengajuan['nama_debitur']} perlu dilakukan revisi oleh {$role_display}.";
        } else {
            $title = "Pengajuan Dikirim ke " . $next_role_display;
            $detail = $message ? $message : "Pengajuan kredit a.n {$pengajuan['nama_debitur']} telah dikirim ke {$next_role_display}.";
        }
        
        foreach ($next_role_users as $user) {
            createNotification(
                $user['id_user'],
                $id_pengajuan,
                $action_type,
                $title,
                $detail,
                $current_role,
                $next_role
            );
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error notifying next role: " . $e->getMessage());
        return false;
    }
}

/**
 * Return a friendly label for a given role key.
 * Alias of getRoleDisplay() — consolidated to avoid duplication.
 */
function getRoleLabel($key)
{
    return getRoleDisplay($key);
}

?>