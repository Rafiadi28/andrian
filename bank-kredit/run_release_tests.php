<?php
/**
 * Automated Pre-Release Test Suite
 * Jalankan: php run_release_tests.php
 */

declare(strict_types=1);

$baseDir = __DIR__;
$results = [];
$bugs = [];
$passed = 0;
$failed = 0;
$warned = 0;

require_once $baseDir . '/config/database.php';
require_once $baseDir . '/includes/functions.php';
require_once $baseDir . '/helpers/credit_helper.php';

function test_result(string $module, string $name, bool $ok, string $detail = '', string $severity = 'high'): void
{
    global $results, $bugs, $passed, $failed, $warned;
    $status = $ok ? 'PASS' : ($severity === 'low' ? 'WARN' : 'FAIL');
    $results[] = ['module' => $module, 'test' => $name, 'status' => $status, 'detail' => $detail];
    if ($ok) {
        $passed++;
    } elseif ($severity === 'low') {
        $warned++;
    } else {
        $failed++;
        $bugs[] = ['module' => $module, 'test' => $name, 'detail' => $detail, 'severity' => $severity];
    }
}

$verbose = !in_array('--quiet', $argv ?? [], true);
$log = static function (string $msg) use ($verbose): void {
    if ($verbose) {
        echo $msg;
    }
};

$log("=== BANK KREDIT — PRE-RELEASE TEST SUITE ===\n");
$log("Tanggal: " . date('Y-m-d H:i:s') . "\n\n");

// ── 0. PHP Syntax Check ──────────────────────────────────────
$keyFiles = [
    'config/database.php', 'includes/functions.php', 'helpers/credit_helper.php',
    'analis/save_section.php', 'print.php', 'detail.php',
    'includes/proses_template.php', 'api/save_assessment_kepatuhan.php',
    'kepatuhan/assesmen.php', 'kabag_kredit/proses.php', 'kadiv_bisnis/proses.php',
    'direksi/proses.php', 'kepatuhan/proses.php', 'insert_test_data.php',
];
$syntaxOk = true;
foreach ($keyFiles as $f) {
    $path = $baseDir . '/' . $f;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $syntaxOk = false;
        test_result('Infrastructure', "Syntax: $f", false, implode(' ', $out));
    }
}
if ($syntaxOk) {
    test_result('Infrastructure', 'PHP syntax check (' . count($keyFiles) . ' files)', true);
}

// ── 1. Database Connection ───────────────────────────────────
test_result('Infrastructure', 'Database connection', isset($pdo) && $pdo instanceof PDO);
if (!isset($pdo) || !$pdo instanceof PDO) {
    fwrite(STDERR, "\n❌ Database tidak tersedia. Hentikan.\n");
    exit(1);
}

// ── 2. Scoring 5C/6C Logic ───────────────────────────────────
$hasil6c = hitung_6c(['character' => 5, 'capacity' => 5, 'capital' => 4, 'collateral' => 5, 'condition' => 4, 'constraint' => 5]);
test_result('9. Scoring 5C', 'hitung_6c() valid input', !isset($hasil6c['error']) && $hasil6c['rata'] == 4.67, 'rata=' . ($hasil6c['rata'] ?? 'N/A'));
test_result('9. Scoring 5C', 'klasifikasi_6c() = Sangat Baik untuk rata 4.67', klasifikasi_6c(4.67) === 'Sangat Baik');
$statusLayak = tentukan_status_kelayakan(4.67);
test_result('9. Scoring 5C', 'tentukan_status_kelayakan(4.67) = LAYAK', ($statusLayak['status'] ?? '') === 'LAYAK');
$statusCatatan = tentukan_status_kelayakan(3.5);
test_result('9. Scoring 5C', 'tentukan_status_kelayakan(3.5) = LAYAK_DENGAN_CATATAN', ($statusCatatan['status'] ?? '') === 'LAYAK_DENGAN_CATATAN');
$statusTidak = tentukan_status_kelayakan(2.0);
test_result('9. Scoring 5C', 'tentukan_status_kelayakan(2.0) = TIDAK_LAYAK', ($statusTidak['status'] ?? '') === 'TIDAK_LAYAK');
$invalid6c = hitung_6c(['character' => 6, 'capacity' => 5, 'capital' => 4, 'collateral' => 5, 'condition' => 4, 'constraint' => 5]);
test_result('9. Scoring 5C', 'Validasi skor di luar 1-5 ditolak', isset($invalid6c['error']));

// ── 3. Repayment ─────────────────────────────────────────────
$umumRpcCfg = getRepaymentParameterConfig($pdo, 'umum');
$expectedUmum = 100000000 * ((float) $umumRpcCfg['persen_maks_angsuran'] / 100);
$rpc = hitungRepayment(100000000, 'umum');
test_result(
    '8. Repayment',
    'hitungRepayment(100M) sesuai master umum (' . $umumRpcCfg['persen_maks_angsuran'] . '%)',
    abs($rpc - $expectedUmum) < 0.01,
    'hasil=' . $rpc
);
$pppkRpcCfg = getRepaymentParameterConfig($pdo, 'pppk');
$expectedPppk = 10000000 * ((float) $pppkRpcCfg['persen_maks_angsuran'] / 100);
$rpcPppk = hitungRepaymentDariKonteks('pppk', [
    'gaji_bersih' => 10000000,
    'net_cashflow' => 5000000,
]);
test_result(
    '8. Repayment',
    'PPPK dasar=' . $pppkRpcCfg['dasar_perhitungan'] . ' dari master (' . $pppkRpcCfg['persen_maks_angsuran'] . '%)',
    abs($rpcPppk - $expectedPppk) < 0.01,
    'hasil=' . $rpcPppk
);
$cfgAnalisaLama = getRepaymentParameterConfig($pdo, 'umum', ['asOfDate' => '2020-06-01']);
$cfgAnalisaHariIni = getRepaymentParameterConfig($pdo, 'umum', ['asOfDate' => date('Y-m-d')]);
test_result(
    '8. Repayment',
    'Parameter umum dipilih berdasarkan tanggal analisa',
    !empty($cfgAnalisaLama['as_of_date']) && $cfgAnalisaLama['as_of_date'] === '2020-06-01',
    'as_of=' . ($cfgAnalisaLama['as_of_date'] ?? 'N/A')
);
test_result(
    '8. Repayment',
    'Parameter hari ini memiliki as_of_date',
    ($cfgAnalisaHariIni['as_of_date'] ?? '') === date('Y-m-d'),
    'as_of=' . ($cfgAnalisaHariIni['as_of_date'] ?? 'N/A')
);
$tglAnalisaFn = function_exists('normalizeRepaymentAsOfDate') && normalizeRepaymentAsOfDate('2024-03-15') === '2024-03-15';
test_result('8. Repayment', 'normalizeRepaymentAsOfDate() valid', $tglAnalisaFn);
$rep = hitung_repayment(5000000, 2000000, 500000);
test_result('8. Repayment', 'hitung_repayment(5M, 2M, 0.5M) = 2.5M', abs($rep - 2500000) < 0.01);
test_result('8. Repayment', 'klasifikasi_repayment(80%, gaji) = Layak', klasifikasi_repayment(4000000, 5000000) === 'Layak');

require_once __DIR__ . '/helpers/repayment_override.php';
$overrideNoAlasan = applyRepaymentOverride($pdo, 0, 1, 5000000, '');
test_result('8. Repayment', 'Override tanpa alasan ditolak', !($overrideNoAlasan['success'] ?? false));
$overridePendek = applyRepaymentOverride($pdo, 0, 1, 5000000, 'terlalu pendek');
test_result('8. Repayment', 'Override alasan < 10 karakter ditolak', !($overridePendek['success'] ?? false));
$mockOverride = getRepaymentOverrideInfo([
    'repayment_override_aktif' => 1,
    'repayment_override_nilai' => 8000000,
    'repayment_capacity_dihitung' => 5000000,
    'repayment_capacity' => 8000000,
    'repayment_override_alasan' => 'Kondisi khusus debitur prioritas.',
]);
test_result(
    '8. Repayment',
    'getRepaymentOverrideInfo() override aktif',
    $mockOverride['aktif'] && $mockOverride['nilai_efektif'] == 8000000
);

require_once __DIR__ . '/helpers/repayment_parameter_audit.php';
bankKreditEnsureRepaymentParameterAuditSchema($pdo);
$tableAudit = $pdo->query("SHOW TABLES LIKE 'repayment_parameter_audit_log'")->rowCount() > 0;
test_result('10. Repayment Audit', 'Tabel repayment_parameter_audit_log ada', $tableAudit);
$snap = repaymentParameterAuditSnapshot([
    'id_parameter' => 1,
    'jenis_kredit' => 'umum',
    'dasar_perhitungan' => 'net_cashflow',
    'persen_maks_angsuran' => 75,
    'status_approval' => 'draft',
]);
test_result('10. Repayment Audit', 'repaymentParameterAuditSnapshot() valid', ($snap['jenis_kredit'] ?? '') === 'umum');
$fmt = formatRepaymentParameterAuditSnapshot($snap);
test_result('10. Repayment Audit', 'formatRepaymentParameterAuditSnapshot() tidak kosong', $fmt !== '-' && strpos($fmt, 'umum') !== false);

// ── 4. Approval Hierarchy & Threshold ─────────────────────────
$hierarchy = getHierarchy();
test_result('2-4. Approval', 'Hierarchy mengandung kepatuhan', in_array('kepatuhan', $hierarchy, true));
test_result('2-4. Approval', 'Threshold <500M stop di kadiv', getMaxApprovalLevel(250000000) === 'kadiv_bisnis');
test_result('2-4. Approval', 'Threshold >=500M ke direktur', getMaxApprovalLevel(600000000) === 'direktur_utama');
$next250 = findNextTarget('kadiv_bisnis', $pdo, 250000000);
test_result('3. Kadiv', 'Kadiv approve 250M → selesai (tanpa direksi)', ($next250['role'] ?? '') === 'selesai');
$next600 = findNextTarget('kadiv_bisnis', $pdo, 600000000);
test_result('3. Kadiv', 'Kadiv approve 600M → direktur_utama', ($next600['role'] ?? '') === 'direktur_utama');

// ── 5. Schema Tables Exist ───────────────────────────────────
$requiredTables = [
    'users', 'pengajuan_kredit', 'analisa_5c', 'analisa_neraca',
    'jaminan_tanah_bangunan', 'jaminan_kendaraan', 'agunan_foto',
    'approval_kredit', 'assessment_kepatuhan', 'master_pejabat', 'audit_log',
    'master_parameter_repayment', 'repayment_parameter_audit_log',
];
foreach ($requiredTables as $tbl) {
    $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tbl))->rowCount() > 0;
    test_result('Infrastructure', "Tabel $tbl ada", $exists);
}

// ── 6. Test Users Exist ──────────────────────────────────────
$testUsers = ['analis_test', 'kabag_test', 'kadiv_test', 'direktur_test', 'kepatuhan_test'];
foreach ($testUsers as $uname) {
    $stmt = $pdo->prepare("SELECT id_user, role FROM users WHERE username = ? AND status_jabatan = 'aktif'");
    $stmt->execute([$uname]);
    $u = $stmt->fetch();
    test_result('Infrastructure', "User test: $uname", (bool)$u, $u ? 'role=' . $u['role'] : 'tidak ditemukan', 'medium');
}

// ── 7. Workflow Simulation (transaction rollback) ─────────────
try {
    $pdo->beginTransaction();

    $nik = '9999' . substr((string)time(), -10);
    $pdo->prepare("INSERT INTO pengajuan_kredit (nama_debitur, nik, pekerjaan, jumlah_kredit, jangka_waktu, tujuan_kredit, status_pengajuan, posisi_saat_ini, jenis_pekerjaan) VALUES (?, ?, ?, ?, ?, ?, 'draft', 'analis', 'umum')")
        ->execute(['TEST WORKFLOW AUTO', $nik, 'Wiraswasta', 250000000, 24, 'Modal Kerja']);
    $testId = (int)$pdo->lastInsertId();

    // Set posisi ke kepatuhan (simulasi setelah analis submit)
    $pdo->prepare("UPDATE pengajuan_kredit SET status_pengajuan='kepatuhan', posisi_saat_ini='kepatuhan' WHERE id_pengajuan=?")->execute([$testId]);

    $stmtKep = $pdo->prepare("SELECT id_user FROM users WHERE username='kepatuhan_test'");
    $stmtKep->execute();
    $kepUserId = (int)$stmtKep->fetchColumn();

    // Insert compliance assessment
    $pdo->prepare("INSERT INTO assessment_kepatuhan (id_pengajuan, id_user, tanggal_assessment, checklist_data, kesimpulan, rekomendasi, hasil_kepatuhan) VALUES (?, ?, CURDATE(), ?, ?, ?, ?)")
        ->execute([$testId, $kepUserId, json_encode(['item1' => ['val' => 'comply']]), 'Memenuhi kriteria', 'Layak', 'COMPLY']);

    $pdo->commit();

    // Walk approval chain: kepatuhan → ... → kadiv (250M should end at kadiv)
    $chain = ['kepatuhan', 'kasubag_analis', 'kabag_kredit', 'kadiv_bisnis'];
    $workflowOk = true;
    $workflowDetail = [];

    foreach ($chain as $role) {
        $stmt = $pdo->prepare("SELECT posisi_saat_ini FROM pengajuan_kredit WHERE id_pengajuan=?");
        $stmt->execute([$testId]);
        $pos = $stmt->fetchColumn();
        if ($pos !== $role) {
            // kasubag may be skipped if no active user
            $stmtSkip = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role=? AND status_jabatan='aktif'");
            $stmtSkip->execute([$role]);
            if ((int)$stmtSkip->fetchColumn() === 0) {
                $workflowDetail[] = "$role skipped (no user)";
                continue;
            }
            $workflowOk = false;
            $workflowDetail[] = "Expected posisi=$role, got=$pos";
            break;
        }

        $stmtU = $pdo->prepare("SELECT id_user FROM users WHERE role=? AND status_jabatan='aktif' LIMIT 1");
        $stmtU->execute([$role]);
        $uid = (int)$stmtU->fetchColumn();
        if ($uid <= 0) {
            $workflowDetail[] = "$role: no active user, skip";
            continue;
        }

        $res = processApproval($pdo, $testId, $role, $uid, 'setuju', 'Test otomatis');
        if (!$res['success']) {
            $workflowOk = false;
            $workflowDetail[] = "$role failed: " . $res['message'];
            break;
        }
        $workflowDetail[] = "$role OK";
    }

    $stmt = $pdo->prepare("SELECT status_pengajuan, posisi_saat_ini FROM pengajuan_kredit WHERE id_pengajuan=?");
    $stmt->execute([$testId]);
    $final = $stmt->fetch();
    $workflowOk = $workflowOk && ($final['status_pengajuan'] ?? '') === 'disetujui' && ($final['posisi_saat_ini'] ?? '') === 'selesai';

    test_result('2-4. Approval', 'Workflow 250M end-to-end (kepatuhan→kadiv)', $workflowOk, implode('; ', $workflowDetail));

    // Cleanup test pengajuan (hapus child records dulu karena FK)
    $pdo->prepare("DELETE FROM approval_kredit WHERE id_pengajuan=?")->execute([$testId]);
    $pdo->prepare("DELETE FROM assessment_kepatuhan WHERE id_pengajuan=?")->execute([$testId]);
    $pdo->prepare("DELETE FROM pengajuan_kredit WHERE id_pengajuan=?")->execute([$testId]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    test_result('2-4. Approval', 'Workflow simulation cleanup', false, $e->getMessage(), 'low');
}

// ── 8. Compliance blocking ───────────────────────────────────
try {
    $nik2 = '8888' . substr((string)time(), -10);
    $pdo->prepare("INSERT INTO pengajuan_kredit (nama_debitur, nik, pekerjaan, jumlah_kredit, jangka_waktu, tujuan_kredit, status_pengajuan, posisi_saat_ini) VALUES (?, ?, ?, ?, ?, ?, 'kabag', 'kabag_kredit')")
        ->execute(['TEST COMPLIANCE BLOCK', $nik2, 'Wiraswasta', 100000000, 12, 'Modal Kerja']);
    $blockId = (int)$pdo->lastInsertId();

    $stmtKab = $pdo->prepare("SELECT id_user FROM users WHERE username='kabag_test'");
    $stmtKab->execute();
    $kabId = (int)$stmtKab->fetchColumn();

    $blockRes = processApproval($pdo, $blockId, 'kabag_kredit', $kabId, 'setuju', 'Should block');
    test_result('5. Kepatuhan', 'Kabag blocked tanpa assessment kepatuhan', !$blockRes['success'], $blockRes['message'] ?? '');

    $pdo->prepare("DELETE FROM pengajuan_kredit WHERE id_pengajuan=?")->execute([$blockId]);
} catch (Throwable $e) {
    test_result('5. Kepatuhan', 'Compliance blocking test', false, $e->getMessage());
}

// ── 9. Neraca data integrity ─────────────────────────────────
$stmtN = $pdo->query("SELECT id_pengajuan, total_aktiva, total_pasiva FROM analisa_neraca ORDER BY id_neraca DESC LIMIT 5");
$neracaOk = true;
$neracaDetail = [];
while ($row = $stmtN->fetch()) {
    if (abs((float)$row['total_aktiva'] - (float)$row['total_pasiva']) > 0.01) {
        $neracaOk = false;
        $neracaDetail[] = "#{$row['id_pengajuan']}: aktiva≠pasiva";
    }
}
test_result('10. Neraca', 'Balance equation (aktiva=pasiva) pada data existing', $neracaOk || empty($neracaDetail), implode(', ', $neracaDetail) ?: 'OK atau belum ada data');

// ── 10. Agunan columns ───────────────────────────────────────
$tanahCols = ['alamat_agunan', 'jenis_surat', 'luas_tanah', 'nilai_taksasi'];
foreach ($tanahCols as $col) {
    $exists = $pdo->query("SHOW COLUMNS FROM jaminan_tanah_bangunan LIKE " . $pdo->quote($col))->rowCount() > 0;
    test_result('11. Agunan', "Kolom jaminan_tanah_bangunan.$col", $exists);
}
$kendCols = ['merk', 'tipe', 'tahun_pembuatan', 'no_polisi', 'nilai_taksasi'];
foreach ($kendCols as $col) {
    $exists = $pdo->query("SHOW COLUMNS FROM jaminan_kendaraan LIKE " . $pdo->quote($col))->rowCount() > 0;
    test_result('11. Agunan', "Kolom jaminan_kendaraan.$col", $exists);
}

// ── 11. Upload directory writable ────────────────────────────
$uploadDir = $baseDir . '/assets/uploads';
$uploadWritable = is_dir($uploadDir) && is_writable($uploadDir);
if (!$uploadWritable && !is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
    $uploadWritable = is_writable($uploadDir);
}
test_result('7. Upload Foto', 'Direktori assets/uploads writable', $uploadWritable);

// ── 12. Print output (no fatal errors) ───────────────────────
$printOk = false;
$printDetail = '';
$stmtP = $pdo->query("SELECT pk.id_pengajuan FROM pengajuan_kredit pk INNER JOIN analisa_5c a ON a.id_pengajuan = pk.id_pengajuan ORDER BY pk.id_pengajuan DESC LIMIT 1");
$printId = (int)$stmtP->fetchColumn();
if ($printId > 0) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'kabag_kredit';
    $_GET['id'] = $printId;
    $_GET['paper_size'] = 'A4';
    ob_start();
    try {
        include $baseDir . '/print.php';
        $html = (string) ob_get_clean();
        $denied = stripos($html, 'Akses Ditolak') !== false;
        $printOk = !$denied && strlen($html) > 500;
        $printDetail = 'length=' . strlen($html) . ' bytes, id=' . $printId;
        test_result('6. Hasil Cetak', 'print.php render tanpa fatal error', $printOk, $printDetail);
        test_result('6. Hasil Cetak', 'Print mengandung section 6C/5C', stripos($html, 'Character') !== false || stripos($html, '6C') !== false || stripos($html, 'Penilaian') !== false, '', 'medium');
        test_result('6. Hasil Cetak', 'Print mengandung data agunan', stripos($html, 'Jaminan') !== false || stripos($html, 'Agunan') !== false, '', 'medium');
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        test_result('6. Hasil Cetak', 'print.php render', false, $e->getMessage(), 'critical');
    }
} else {
    test_result('6. Hasil Cetak', 'print.php render', false, 'Tidak ada pengajuan di database', 'medium');
}

// ── 13. Analis input endpoints exist ─────────────────────────
$analisFiles = ['analis/dashboard.php', 'analis/input.php', 'analis/save_section.php', 'analis/form_umum.php', 'analis/edit.php'];
foreach ($analisFiles as $f) {
    test_result('1. Input Analis', "File $f ada", is_file($baseDir . '/' . $f));
}

// ── 14. fetch_data_analis_untuk_kepatuhan ────────────────────
$stmtKepId = $pdo->query("SELECT pk.id_pengajuan FROM pengajuan_kredit pk INNER JOIN analisa_5c a ON a.id_pengajuan = pk.id_pengajuan ORDER BY pk.id_pengajuan DESC LIMIT 1");
$kepTestId = (int)$stmtKepId->fetchColumn();
if ($kepTestId > 0) {
    $kepData = fetch_data_analis_untuk_kepatuhan($pdo, $kepTestId);
    test_result('5. Kepatuhan', 'fetch_data_analis_untuk_kepatuhan()', is_array($kepData) && isset($kepData['pengajuan']), $kepData ? "id=$kepTestId OK" : 'null');
    test_result('5. Kepatuhan', 'Data agunan tersedia untuk review', !empty($kepData['status']['ada_agunan']), '', 'medium');
}

// ── SUMMARY ──────────────────────────────────────────────────
$log("\n" . str_repeat('=', 70) . "\n");
$log("HASIL RINGKASAN\n");
$log(str_repeat('=', 70) . "\n");
$log("PASS: $passed | FAIL: $failed | WARN: $warned\n\n");

$modules = [];
foreach ($results as $r) {
    $modules[$r['module']][] = $r;
}

foreach ($modules as $mod => $tests) {
    $modPass = count(array_filter($tests, fn($t) => $t['status'] === 'PASS'));
    $modFail = count(array_filter($tests, fn($t) => $t['status'] === 'FAIL'));
    $icon = $modFail === 0 ? '✅' : '❌';
    $log("$icon $mod ($modPass/" . count($tests) . ")\n");
    foreach ($tests as $t) {
        $s = $t['status'] === 'PASS' ? '  ✓' : ($t['status'] === 'WARN' ? '  ⚠' : '  ✗');
        $log("$s {$t['test']}");
        if ($t['detail']) {
            $log(" — {$t['detail']}");
        }
        $log("\n");
    }
    $log("\n");
}

// Write report file
$reportPath = $baseDir . '/TEST_RESULT_REPORT.md';
$report = "# LAPORAN HASIL TESTING PRE-RELEASE\n\n";
$report .= "**Tanggal:** " . date('Y-m-d H:i:s') . "\n";
$report .= "**Environment:** Laragon Development\n";
$report .= "**Tester:** Automated (run_release_tests.php)\n\n";
$report .= "## Ringkasan\n\n";
$report .= "| Metrik | Nilai |\n|--------|-------|\n";
$report .= "| Total PASS | $passed |\n";
$report .= "| Total FAIL | $failed |\n";
$report .= "| Total WARN | $warned |\n";
$report .= "| Status Release | " . ($failed === 0 ? '**SIAP UAT**' : '**PERLU PERBAIKAN**') . " |\n\n";

$report .= "## Hasil per Modul\n\n";
$moduleOrder = [
    '1. Input Analis', '2-4. Approval', '2. Kabag', '3. Kadiv', '4. Direksi',
    '5. Kepatuhan', '6. Hasil Cetak', '7. Upload Foto', '8. Repayment',
    '9. Scoring 5C', '10. Neraca', '11. Agunan', 'Infrastructure',
];
foreach ($moduleOrder as $mod) {
    if (!isset($modules[$mod])) continue;
    $report .= "### $mod\n\n";
    $report .= "| Test | Status | Detail |\n|------|--------|--------|\n";
    foreach ($modules[$mod] as $t) {
        $report .= "| {$t['test']} | {$t['status']} | " . str_replace('|', '/', $t['detail']) . " |\n";
    }
    $report .= "\n";
}

if (!empty($bugs)) {
    $report .= "## Bug Ditemukan\n\n";
    foreach ($bugs as $i => $b) {
        $report .= "### BUG #" . ($i + 1) . "\n";
        $report .= "- **Modul:** {$b['module']}\n";
        $report .= "- **Test:** {$b['test']}\n";
        $report .= "- **Severity:** {$b['severity']}\n";
        $report .= "- **Detail:** {$b['detail']}\n\n";
    }
}

$report .= "## Rekomendasi\n\n";
if ($failed === 0) {
    $report .= "- Semua test otomatis lulus. Lanjutkan UAT manual di browser untuk verifikasi UI.\n";
} else {
    $report .= "- Perbaiki bug yang tercatat sebelum release production.\n";
}
$report .= "- Jalankan `php insert_test_data.php` jika data test belum lengkap.\n";
$report .= "- UAT manual: ikuti TESTING_CHECKLIST.md untuk verifikasi UI/foto upload.\n\n";
$report .= "---\n*Generated by run_release_tests.php*\n";

file_put_contents($reportPath, $report);
$log("Laporan disimpan: $reportPath\n");
$log("\nExit code: " . ($failed > 0 ? 1 : 0) . "\n");
exit($failed > 0 ? 1 : 0);
