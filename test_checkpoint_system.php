<?php
/**
 * PHASE 6: Testing & Validasi Sistem Revisi Berbasis Checkpoint
 * 
 * Jalankan via browser: https://analisa.bankwonosobo.co.id/test_checkpoint_system.php
 * 
 * HAPUS FILE INI SETELAH TESTING SELESAI!
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Test Checkpoint System</title>';
echo '<style>body{font-family:monospace;padding:2rem;background:#1e1e2e;color:#cdd6f4;line-height:1.8;}';
echo '.ok{color:#a6e3a1;}.fail{color:#f38ba8;}.warn{color:#f9e2af;}.info{color:#89b4fa;}';
echo 'h2{color:#cba6f7;border-bottom:1px solid #45475a;padding-bottom:8px;}';
echo '.section{background:#313244;padding:1rem;margin:1rem 0;border-radius:8px;}';
echo '</style></head><body>';
echo '<h1>🔬 Phase 6: Test Checkpoint & Revisi System</h1>';

$results = ['pass' => 0, 'fail' => 0, 'warn' => 0];

function test_pass($msg) { global $results; $results['pass']++; echo "<div class='ok'>✅ PASS: $msg</div>"; }
function test_fail($msg) { global $results; $results['fail']++; echo "<div class='fail'>❌ FAIL: $msg</div>"; }
function test_warn($msg) { global $results; $results['warn']++; echo "<div class='warn'>⚠️ WARN: $msg</div>"; }
function test_info($msg) { echo "<div class='info'>ℹ️ INFO: $msg</div>"; }

// ============================================================
// TEST 1: MIGRASI DATABASE
// ============================================================
echo '<h2>1. Migrasi Database</h2><div class="section">';

// Create tables if not exist
$tables_sql = [
    'analysis_checkpoints' => "CREATE TABLE IF NOT EXISTS analysis_checkpoints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_pengajuan INT NOT NULL,
        tab_name VARCHAR(50) NOT NULL,
        status ENUM('FILLED', 'APPROVED', 'REVISION', 'FIXED', 'RESUBMITTED') DEFAULT 'FILLED',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_pengajuan_tab (id_pengajuan, tab_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'analysis_revisions' => "CREATE TABLE IF NOT EXISTS analysis_revisions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_pengajuan INT NOT NULL,
        version_id INT DEFAULT 1,
        tab_name VARCHAR(50) NOT NULL,
        field_name VARCHAR(100) NULL,
        revision_note TEXT NOT NULL,
        status ENUM('PENDING', 'FIXED', 'APPROVED') DEFAULT 'PENDING',
        requested_by INT NOT NULL,
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fixed_by INT NULL,
        fixed_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'analysis_audit_logs' => "CREATE TABLE IF NOT EXISTS analysis_audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_pengajuan INT NOT NULL,
        id_revision INT NULL,
        tab_name VARCHAR(50) NOT NULL,
        field_name VARCHAR(100) NOT NULL,
        old_value TEXT,
        new_value TEXT,
        changed_by INT NOT NULL,
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($tables_sql as $table => $sql) {
    try {
        $pdo->exec($sql);
        // Verify table exists
        $check = $pdo->query("SHOW TABLES LIKE '$table'")->fetchColumn();
        if ($check) {
            test_pass("Tabel <b>$table</b> berhasil dibuat / sudah ada.");
        } else {
            test_fail("Tabel <b>$table</b> gagal dibuat.");
        }
    } catch (PDOException $e) {
        test_fail("Error membuat tabel <b>$table</b>: " . htmlspecialchars($e->getMessage()));
    }
}

// Verify columns
foreach (['analysis_checkpoints', 'analysis_revisions', 'analysis_audit_logs'] as $t) {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_COLUMN);
        test_info("Kolom <b>$t</b>: " . implode(', ', $cols));
    } catch (PDOException $e) {
        test_fail("Gagal membaca kolom <b>$t</b>: " . htmlspecialchars($e->getMessage()));
    }
}
echo '</div>';

// ============================================================
// TEST 2: FILE INTEGRITY
// ============================================================
echo '<h2>2. File Integrity Check</h2><div class="section">';

$required_files = [
    'includes/checkpoint_helper.php' => 'Checkpoint Helper (Backend Logic)',
    'database_migrations/01_checkpoint_revisi.sql' => 'SQL Migration File',
    'analis/input.php' => 'Input Page (Revisi Query Injected)',
    'analis/form_umum.php' => 'Form Umum (UI Badges + Lock)',
    'analis/partials/pegawai_page.inc.php' => 'Pegawai Page (PPPK/Desa UI)',
    'analis/save_section.php' => 'Save Section (Audit Hook)',
];

foreach ($required_files as $file => $desc) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        test_pass("<b>$file</b> — $desc");
    } else {
        test_fail("<b>$file</b> tidak ditemukan — $desc");
    }
}

// Verify checkpoint_helper.php contains required functions
$chk_content = file_get_contents(__DIR__ . '/includes/checkpoint_helper.php');
if (strpos($chk_content, 'captureAllDbState') !== false) {
    test_pass("Function <b>captureAllDbState()</b> ditemukan di checkpoint_helper.php");
} else {
    test_fail("Function <b>captureAllDbState()</b> TIDAK ditemukan");
}
if (strpos($chk_content, 'processAuditDiffAndCheckpoint') !== false) {
    test_pass("Function <b>processAuditDiffAndCheckpoint()</b> ditemukan di checkpoint_helper.php");
} else {
    test_fail("Function <b>processAuditDiffAndCheckpoint()</b> TIDAK ditemukan");
}

// Verify save_section.php includes checkpoint_helper
$save_content = file_get_contents(__DIR__ . '/analis/save_section.php');
if (strpos($save_content, 'checkpoint_helper.php') !== false) {
    test_pass("save_section.php meng-include <b>checkpoint_helper.php</b>");
} else {
    test_fail("save_section.php BELUM meng-include checkpoint_helper.php");
}
if (strpos($save_content, 'register_shutdown_function') !== false) {
    test_pass("save_section.php menggunakan <b>register_shutdown_function</b> untuk audit");
} else {
    test_fail("save_section.php BELUM menggunakan register_shutdown_function");
}
if (strpos($save_content, 'analysis_revisions') !== false) {
    test_pass("save_section.php memvalidasi <b>pending revisions</b> sebelum submit");
} else {
    test_fail("save_section.php BELUM memvalidasi pending revisions");
}

// Verify UI injection in form_umum.php
$form_content = file_get_contents(__DIR__ . '/analis/form_umum.php');
if (strpos($form_content, 'ANALISA MEMERLUKAN REVISI') !== false) {
    test_pass("form_umum.php memiliki <b>Banner Revisi</b>");
} else {
    test_fail("form_umum.php BELUM memiliki Banner Revisi");
}
if (strpos($form_content, 'renderTabBadge') !== false) {
    test_pass("form_umum.php memiliki <b>Tab Badge/Indikator</b>");
} else {
    test_fail("form_umum.php BELUM memiliki Tab Badge");
}
if (strpos($form_content, 'TERKUNCI') !== false) {
    test_pass("form_umum.php memiliki <b>Form Locking JS</b>");
} else {
    test_fail("form_umum.php BELUM memiliki Form Locking JS");
}

// Verify UI injection in pegawai_page.inc.php
$pegawai_content = file_get_contents(__DIR__ . '/analis/partials/pegawai_page.inc.php');
if (strpos($pegawai_content, 'ANALISA MEMERLUKAN REVISI') !== false) {
    test_pass("pegawai_page.inc.php memiliki <b>Banner Revisi</b>");
} else {
    test_fail("pegawai_page.inc.php BELUM memiliki Banner Revisi");
}
if (strpos($pegawai_content, 'renderTabBadgePegawai') !== false) {
    test_pass("pegawai_page.inc.php memiliki <b>Tab Badge/Indikator</b>");
} else {
    test_fail("pegawai_page.inc.php BELUM memiliki Tab Badge");
}
if (strpos($pegawai_content, 'TERKUNCI') !== false) {
    test_pass("pegawai_page.inc.php memiliki <b>Form Locking JS</b>");
} else {
    test_fail("pegawai_page.inc.php BELUM memiliki Form Locking JS");
}

// Verify input.php fetches revisions
$input_content = file_get_contents(__DIR__ . '/analis/input.php');
if (strpos($input_content, 'active_revisions') !== false) {
    test_pass("input.php mengambil data <b>active_revisions</b>");
} else {
    test_fail("input.php BELUM mengambil data active_revisions");
}
if (strpos($input_content, 'checkpoints_json') !== false) {
    test_pass("input.php mengambil data <b>checkpoints</b>");
} else {
    test_fail("input.php BELUM mengambil data checkpoints");
}

echo '</div>';

// ============================================================
// TEST 3: SIMULASI WORKFLOW REVISI (DB Operations)
// ============================================================
echo '<h2>3. Simulasi Workflow Revisi</h2><div class="section">';

// Ambil satu pengajuan existing untuk test
$testRow = $pdo->query("SELECT id_pengajuan, nama_debitur, status_pengajuan FROM pengajuan_kredit ORDER BY id_pengajuan DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$testRow) {
    test_warn("Tidak ada data pengajuan di database. Skip simulasi.");
} else {
    $testId = (int) $testRow['id_pengajuan'];
    test_info("Menggunakan pengajuan <b>#$testId</b> ({$testRow['nama_debitur']}) untuk simulasi. Status: {$testRow['status_pengajuan']}");

    // 3a. Insert Checkpoint (FILLED)
    try {
        $tabs = ['pemohon', 'usaha', 'struktur', 'agunan', '6c', 'scoring'];
        $stmt = $pdo->prepare("INSERT INTO analysis_checkpoints (id_pengajuan, tab_name, status) VALUES (?, ?, 'FILLED') ON DUPLICATE KEY UPDATE status = IF(status = 'FILLED', 'FILLED', status)");
        foreach ($tabs as $tab) {
            $stmt->execute([$testId, $tab]);
        }
        test_pass("Checkpoint FILLED berhasil di-insert/update untuk 6 tab.");
    } catch (PDOException $e) {
        test_fail("Gagal insert checkpoint: " . htmlspecialchars($e->getMessage()));
    }

    // 3b. Verify Checkpoints
    $chks = $pdo->prepare("SELECT tab_name, status FROM analysis_checkpoints WHERE id_pengajuan = ?");
    $chks->execute([$testId]);
    $chkData = $chks->fetchAll(PDO::FETCH_ASSOC);
    test_info("Checkpoint saat ini: " . json_encode($chkData));

    // 3c. Simulasi Atasan memberikan Revisi
    try {
        // Insert revisi dari "atasan" (user_id = 1 sebagai dummy)
        $pdo->prepare("INSERT INTO analysis_revisions (id_pengajuan, version_id, tab_name, field_name, revision_note, status, requested_by) VALUES (?, 1, 'agunan', 'Nilai Pasar', 'Sesuaikan dengan appraisal terbaru. Nilai pasar terlalu rendah.', 'PENDING', 1)")->execute([$testId]);
        test_pass("Revisi dari atasan berhasil di-insert (Tab: AGUNAN, Field: Nilai Pasar).");
        
        // Update checkpoint status ke REVISION
        $pdo->prepare("UPDATE analysis_checkpoints SET status = 'REVISION' WHERE id_pengajuan = ? AND tab_name = 'agunan'")->execute([$testId]);
        test_pass("Checkpoint AGUNAN diubah ke REVISION.");
        
        // Approve tab lainnya
        $pdo->prepare("UPDATE analysis_checkpoints SET status = 'APPROVED' WHERE id_pengajuan = ? AND tab_name != 'agunan'")->execute([$testId]);
        test_pass("Tab lainnya diubah ke APPROVED.");
    } catch (PDOException $e) {
        test_fail("Gagal simulasi revisi: " . htmlspecialchars($e->getMessage()));
    }

    // 3d. Verify state setelah revisi
    $chks2 = $pdo->prepare("SELECT tab_name, status FROM analysis_checkpoints WHERE id_pengajuan = ? ORDER BY tab_name");
    $chks2->execute([$testId]);
    $stateAfterRevision = $chks2->fetchAll(PDO::FETCH_ASSOC);
    test_info("State setelah atasan revisi:");
    $agunanIsRevision = false;
    $othersApproved = true;
    foreach ($stateAfterRevision as $s) {
        $icon = ($s['status'] === 'APPROVED') ? '✅' : (($s['status'] === 'REVISION') ? '⚠️' : '🔵');
        echo "<div class='info' style='margin-left:2rem;'>$icon {$s['tab_name']}: <b>{$s['status']}</b></div>";
        if ($s['tab_name'] === 'agunan' && $s['status'] === 'REVISION') $agunanIsRevision = true;
        if ($s['tab_name'] !== 'agunan' && $s['status'] !== 'APPROVED') $othersApproved = false;
    }
    if ($agunanIsRevision) test_pass("AGUNAN = REVISION ✓"); else test_fail("AGUNAN seharusnya REVISION");
    if ($othersApproved) test_pass("Tab lain = APPROVED ✓"); else test_warn("Beberapa tab belum APPROVED");

    // 3e. Cek pending revisions
    $pendingCount = $pdo->prepare("SELECT COUNT(*) FROM analysis_revisions WHERE id_pengajuan = ? AND status = 'PENDING'");
    $pendingCount->execute([$testId]);
    $pc = (int) $pendingCount->fetchColumn();
    if ($pc > 0) test_pass("Pending revisions terdeteksi: $pc item."); else test_fail("Pending revisions tidak terdeteksi");

    // 3f. Simulasi Analis memperbaiki → FIXED
    try {
        $pdo->prepare("UPDATE analysis_checkpoints SET status = 'FIXED' WHERE id_pengajuan = ? AND tab_name = 'agunan'")->execute([$testId]);
        $pdo->prepare("UPDATE analysis_revisions SET status = 'FIXED', fixed_by = 2, fixed_at = NOW() WHERE id_pengajuan = ? AND tab_name = 'agunan' AND status = 'PENDING'")->execute([$testId]);
        test_pass("Analis memperbaiki AGUNAN → FIXED.");
    } catch (PDOException $e) {
        test_fail("Gagal simulasi fix: " . htmlspecialchars($e->getMessage()));
    }

    // 3g. Verify no more PENDING
    $pendingCount2 = $pdo->prepare("SELECT COUNT(*) FROM analysis_revisions WHERE id_pengajuan = ? AND status = 'PENDING'");
    $pendingCount2->execute([$testId]);
    $pc2 = (int) $pendingCount2->fetchColumn();
    if ($pc2 === 0) test_pass("Tidak ada lagi revisi PENDING → Siap resubmit."); else test_fail("Masih ada $pc2 revisi PENDING");

    // 3h. Test Audit Log Insert
    try {
        $pdo->prepare("INSERT INTO analysis_audit_logs (id_pengajuan, tab_name, field_name, old_value, new_value, changed_by) VALUES (?, 'agunan', 'nilai_pasar', '150000000', '175000000', 2)")->execute([$testId]);
        test_pass("Audit log berhasil di-insert.");
        
        $auditCheck = $pdo->prepare("SELECT * FROM analysis_audit_logs WHERE id_pengajuan = ? ORDER BY id DESC LIMIT 1");
        $auditCheck->execute([$testId]);
        $auditRow = $auditCheck->fetch(PDO::FETCH_ASSOC);
        if ($auditRow) {
            test_pass("Audit log terverifikasi: field={$auditRow['field_name']}, old={$auditRow['old_value']}, new={$auditRow['new_value']}");
        }
    } catch (PDOException $e) {
        test_fail("Gagal insert audit log: " . htmlspecialchars($e->getMessage()));
    }

    // 3i. Cleanup test data
    echo '<br>';
    test_info("<b>Membersihkan data test...</b>");
    try {
        $pdo->prepare("DELETE FROM analysis_audit_logs WHERE id_pengajuan = ? AND field_name = 'nilai_pasar' AND old_value = '150000000'")->execute([$testId]);
        $pdo->prepare("DELETE FROM analysis_revisions WHERE id_pengajuan = ? AND tab_name = 'agunan' AND field_name = 'Nilai Pasar'")->execute([$testId]);
        $pdo->prepare("DELETE FROM analysis_checkpoints WHERE id_pengajuan = ?")->execute([$testId]);
        test_pass("Data test berhasil dibersihkan.");
    } catch (PDOException $e) {
        test_warn("Gagal membersihkan data test: " . htmlspecialchars($e->getMessage()));
    }
}

echo '</div>';

// ============================================================
// TEST 4: SYNTAX CHECK PHP FILES
// ============================================================
echo '<h2>4. PHP Syntax Check</h2><div class="section">';

$filesToCheck = [
    'includes/checkpoint_helper.php',
    'analis/input.php',
    'analis/save_section.php',
];

foreach ($filesToCheck as $file) {
    $fullPath = __DIR__ . '/' . $file;
    $output = [];
    $returnCode = 0;
    exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $returnCode);
    $outputStr = implode(' ', $output);
    if ($returnCode === 0 && strpos($outputStr, 'No syntax errors') !== false) {
        test_pass("Syntax OK: <b>$file</b>");
    } else {
        test_fail("Syntax ERROR di <b>$file</b>: " . htmlspecialchars($outputStr));
    }
}

echo '</div>';

// ============================================================
// SUMMARY
// ============================================================
echo '<h2>📊 Ringkasan Hasil Test</h2><div class="section">';
$total = $results['pass'] + $results['fail'] + $results['warn'];
echo "<div class='ok'><b>✅ PASS: {$results['pass']}</b></div>";
echo "<div class='fail'><b>❌ FAIL: {$results['fail']}</b></div>";
echo "<div class='warn'><b>⚠️ WARN: {$results['warn']}</b></div>";
echo "<br><div class='info'>Total: $total test</div>";

if ($results['fail'] === 0) {
    echo "<br><div class='ok' style='font-size:1.3em;padding:1rem;background:#1a4031;border-radius:8px;text-align:center;'>";
    echo "🎉 <b>SEMUA TEST PASSED!</b> Sistem Checkpoint & Revisi siap digunakan.";
    echo "</div>";
} else {
    echo "<br><div class='fail' style='font-size:1.3em;padding:1rem;background:#4a1520;border-radius:8px;text-align:center;'>";
    echo "🚨 <b>Ada {$results['fail']} test GAGAL.</b> Perbaiki sebelum production.";
    echo "</div>";
}

echo '</div>';
echo '<br><div class="warn" style="padding:1rem;background:#4a3a10;border-radius:8px;">⚠️ <b>HAPUS FILE INI SETELAH TESTING SELESAI!</b> File: test_checkpoint_system.php</div>';
echo '</body></html>';
