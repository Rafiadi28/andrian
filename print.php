<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/helpers/credit_helper.php';

if (!isLoggedIn()) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

// All authenticated users can access print — no role restriction
// (Access already gated by isLoggedIn() check above)

$id = $_GET['id'] ?? null;
$paper_size = $_GET['paper_size'] ?? 'A4'; // A4 or F4
$from = $_GET['from'] ?? 'detail'; // Track source page (detail, dashboard, riwayat)

if (!$id) {
    die("ID Pengajuan tidak ditemukan.");
}

// Validate paper size
if (!in_array($paper_size, ['A4', 'F4'])) {
    $paper_size = 'A4';
}

// Get Pengajuan
$stmt = $pdo->prepare("SELECT * FROM pengajuan_kredit WHERE id_pengajuan = ?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Data tidak ditemukan.");
}

$normalizePrintText = static function ($value, $fallback = '-') {
    if ($value === null || $value === '') {
        return $fallback;
    }
    $text = trim((string) $value);
    return $text === '' ? $fallback : $text;
};

$resolvePrintData = static function (array $row, array $keys, $fallback = '-') use ($normalizePrintText) {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && trim((string) $row[$key]) !== '') {
            return $normalizePrintText($row[$key], $fallback);
        }
    }
    return $fallback;
};

$gradeLabel6C = static function ($score) {
    $score = (int) $score;
    if ($score >= 5) return 'Sangat Baik';
    if ($score == 4) return 'Baik';
    if ($score == 3) return 'Cukup';
    if ($score == 2) return 'Kurang';
    if ($score == 1) return 'Sangat Kurang';
    return '-';
};

$colorLabel6C = static function ($score) {
    $score = (int) $score;
    if ($score >= 4) return '#15803d';
    if ($score == 3) return '#b45309';
    return '#b91c1c';
};

// Analis: only print their own submissions
if (($_SESSION['role'] ?? '') === 'analis'
    && (int)($data['input_by'] ?? 0) !== (int)($_SESSION['user_id'] ?? 0)) {
    http_response_code(403);
    die("<h2>Akses Ditolak</h2><p>Anda hanya dapat mencetak dokumen pengajuan yang Anda input sendiri.</p>");
}

// Fetch 6C analysis data
$stmt6c = $pdo->prepare("SELECT * FROM analisa_5c WHERE id_pengajuan = ?");
$stmt6c->execute([$id]);
$print_6c = $stmt6c->fetch(PDO::FETCH_ASSOC) ?: [];

// ===== FETCH COMPLIANCE ASSESSMENT DATA =====
$stmt_compliance = $pdo->prepare("SELECT * FROM assessment_kepatuhan WHERE id_pengajuan = ?");
$stmt_compliance->execute([$id]);
$compliance_data = $stmt_compliance->fetch(PDO::FETCH_ASSOC);

// Parse compliance checklist (filter out N/A items)
$compliance_items = [];
if ($compliance_data && !empty($compliance_data['checklist_data'])) {
    $all_checklist = json_decode($compliance_data['checklist_data'], true) ?: [];
    foreach ($all_checklist as $key => $item) {
        if (isset($item['val']) && $item['val'] !== 'na') {
            $compliance_items[$key] = $item;
        }
    }
}

$compliance_summary = [
    'comply' => 0,
    'not_comply' => 0,
    'na' => 0,
];
if ($compliance_data && !empty($compliance_data['checklist_data'])) {
    $all_checklist = json_decode($compliance_data['checklist_data'], true) ?: [];
    foreach ($all_checklist as $item) {
        $val = strtolower((string)($item['val'] ?? ''));
        if ($val === 'comply') $compliance_summary['comply']++;
        elseif ($val === 'not_comply') $compliance_summary['not_comply']++;
        elseif ($val === 'na') $compliance_summary['na']++;
    }
}

// Get approval history for the pengajuan and keep all roles in the approval chain
$stmt = $pdo->prepare("
    SELECT a.*, u.nama as nama_approver, u.role as role_approver 
    FROM approval_kredit a 
    LEFT JOIN users u ON a.id_user = u.id_user 
    WHERE a.id_pengajuan = ?
    ORDER BY a.id_approval ASC
");
$stmt->execute([$id]);
$approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== FETCH AGUNAN DATA =====
$stmt_jaminan_tanah = $pdo->prepare("SELECT * FROM jaminan_tanah_bangunan WHERE id_pengajuan = ?");
$stmt_jaminan_tanah->execute([$id]);
$jaminan_tanah = $stmt_jaminan_tanah->fetchAll(PDO::FETCH_ASSOC);

$stmt_jaminan_kendaraan = $pdo->prepare("SELECT * FROM jaminan_kendaraan WHERE id_pengajuan = ?");
$stmt_jaminan_kendaraan->execute([$id]);
$jaminan_kendaraan = $stmt_jaminan_kendaraan->fetchAll(PDO::FETCH_ASSOC);

$stmt_jaminan_emas = $pdo->prepare("SELECT * FROM jaminan_emas WHERE id_pengajuan = ?");
$stmt_jaminan_emas->execute([$id]);
$jaminan_emas = $stmt_jaminan_emas->fetchAll(PDO::FETCH_ASSOC);

// Fetch Multiple Agunan Foto
$agunan_foto_all = [];
$stmt = $pdo->prepare("SELECT * FROM agunan_foto WHERE id_pengajuan = ? ORDER BY created_at DESC");
$stmt->execute([$id]);
$agunan_foto_all = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== FETCH NERACA DATA =====
$stmt_neraca = $pdo->prepare("SELECT * FROM analisa_neraca WHERE id_pengajuan = ?");
$stmt_neraca->execute([$id]);
$neraca_data = $stmt_neraca->fetch(PDO::FETCH_ASSOC);

// ===== CALCULATE FINANCIAL METRICS =====
$jenis_pek = $data['jenis_pekerjaan'] ?? 'umum';
if (($jenis_pek) === 'perangkat_desa') {
    $monthly_income = floatval($data['omset_per_bulan'] ?? 0);
} else {
    $monthly_income = floatval($data['omset_per_bulan'] ?? 0) + floatval($data['pendapatan_lain'] ?? 0);
}
// NOTE: total_pengeluaran_tetap sudah termasuk biaya_hidup + cicilan_lain, jangan duplikasi
$monthly_expense = floatval($data['total_pengeluaran_tetap'] ?? 0);
$monthly_installment = floatval($data['angsuran_diajukan'] ?? 0);
$loan_amount = floatval($data['jumlah_kredit'] ?? 0);

// If angsuran_diajukan is 0, calculate from jumlah_kredit / jangka_waktu
if ($monthly_installment <= 0 && $loan_amount > 0) {
    $jangka_waktu = intval($data['jangka_waktu'] ?? 12);
    if ($jangka_waktu <= 0) {
        $jangka_waktu = 12; // Default to 12 months
    }
    
    $suku_bunga = floatval($data['suku_bunga'] ?? 0);
    $sistem_bunga = strtolower($data['sistem_bunga'] ?? 'anuitas');
    
    if (strpos($sistem_bunga, 'flat') !== false) {
        // Flat calculation
        $monthly_installment = ($loan_amount + ($loan_amount * ($suku_bunga / 100) * ($jangka_waktu / 12))) / $jangka_waktu;
    } else {
        // Anuitas / Efektif calculation (PMT Formula)
        if ($suku_bunga > 0) {
            $r = ($suku_bunga / 100) / 12; // Monthly interest rate
            $monthly_installment = $loan_amount * ($r * pow(1 + $r, $jangka_waktu)) / (pow(1 + $r, $jangka_waktu) - 1);
        } else {
            $monthly_installment = $loan_amount / $jangka_waktu;
        }
    }
}

// Calculate ratios
$debt_income_ratio = $monthly_income > 0 ? ($monthly_installment / $monthly_income) * 100 : 0;
$remaining_capacity = $monthly_income > 0 ? $monthly_income - $monthly_expense - $monthly_installment : 0;

// Total collateral value
$total_collateral = 0;
foreach ($jaminan_tanah as $jt) {
    $total_collateral += floatval($jt['nilai_taksasi'] ?? $jt['nilai_pasar'] ?? 0);
}
foreach ($jaminan_kendaraan as $jk) {
    $total_collateral += floatval($jk['nilai_taksasi'] ?? $jk['nilai_pasar'] ?? 0);
}
foreach ($jaminan_emas as $je) {
    $total_collateral += floatval($je['nilai_pasar'] ?? 0);
}

// LTV Ratio (Loan to Value)
$ltv_ratio = $loan_amount > 0 && $total_collateral > 0 ? ($loan_amount / $total_collateral) * 100 : 0;

// Risk Level
$risk_level = 'SEDANG'; // Default
if ($debt_income_ratio > 50 || $ltv_ratio > 80 || $remaining_capacity < 0) {
    $risk_level = 'TINGGI';
} elseif ($debt_income_ratio <= 30 && $ltv_ratio <= 60 && $remaining_capacity > floatval($data['angsuran_diajukan'] ?? 0)) {
    $risk_level = 'RENDAH';
}

// Build approval map from query results
$approval_map = [];
$legacy_role_aliases = [
    'kabag' => 'kabag_kredit',
    'kasubag' => 'kasubag_analis',
    'kabag_analis' => 'kabag_kredit',
    'kadiv' => 'kadiv_bisnis',
    'kadiv_kredit' => 'kadiv_bisnis',
    'direksi' => 'direktur_utama'
];

$normalizeApprovalRoleForPrint = static function ($role) use ($legacy_role_aliases) {
    $role = strtolower(trim((string) $role));
    return $legacy_role_aliases[$role] ?? $role;
};

$approval_latest = [];
$approval_approved = [];

foreach ((array)$approvals as $a) {
    $normalized_role = $normalizeApprovalRoleForPrint($a['level_approval'] ?? '');
    if ($normalized_role === '') {
        continue;
    }

    if (!isset($approval_latest[$normalized_role]) || (int)($a['id_approval'] ?? 0) > (int)($approval_latest[$normalized_role]['id_approval'] ?? 0)) {
        $approval_latest[$normalized_role] = $a;
    }

    if (($a['keputusan'] ?? '') === 'setuju') {
        if (!isset($approval_approved[$normalized_role]) || (int)($a['id_approval'] ?? 0) > (int)($approval_approved[$normalized_role]['id_approval'] ?? 0)) {
            $approval_approved[$normalized_role] = $a;
        }
    }
}

// Use approved-only map for status/timeline
$approval_map = $approval_approved;
$approval_map = array_filter($approval_map, static function ($entry, $role) {
    return strtolower((string) $role) !== 'kepatuhan';
}, ARRAY_FILTER_USE_BOTH);

$approval_chain_roles = getApprovalChainRoles($pdo, $loan_amount);
// Kepatuhan dinilai terpisah melalui assessment_kepatuhan.
// Untuk cetakan status final, jangan hitung kepatuhan sebagai bagian dari approval_kredit chain.
$approval_chain_roles = array_values(array_filter($approval_chain_roles, static function ($role) {
    return $role !== 'kepatuhan';
}));

// Determine if all required approvals along the active chain are completed
$semua_disetujui = true;
foreach ($approval_chain_roles as $role) {
    if (!isset($approval_approved[$role])) {
        $semua_disetujui = false;
        break;
    }
}

if (strtolower(trim((string)($data['status_pengajuan'] ?? ''))) === 'disetujui'
    || strtolower(trim((string)($data['posisi_saat_ini'] ?? ''))) === 'selesai') {
    $semua_disetujui = true;
}

if ($semua_disetujui) {
    $teks_status = '✓ DISETUJUI';
    $dok_status_formal = '✓ DISETUJUI UNTUK DICAIRKAN';
    $warna_status = '#15803d'; // green
    $warna_status_formal = '#155724'; // dark green
    $bg_status_formal = '';
} else {
    $teks_status = '⏳ MENUNGGU';
    $dok_status_formal = '⏳ MENUNGGU PERSETUJUAN';
    $warna_status = '#d97706'; // amber
    $warna_status_formal = '#856404'; // dark amber
    $bg_status_formal = 'background-color: #fff3cd; padding: 4px 8px; border-radius: 4px; display: inline-block;';
}

// Fetch master pejabat data for all roles
$stmt_pejabat = $pdo->prepare("
    SELECT id_pejabat, role, nama, jabatan, tanda_tangan, stempel, status 
    FROM master_pejabat 
    ORDER BY FIELD(role, 'analis', 'kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama')");
$stmt_pejabat->execute();
$pejabat_data = $stmt_pejabat->fetchAll(PDO::FETCH_ASSOC);

$pejabat_by_role = [];
foreach ($pejabat_data as $p) {
    $pejabat_by_role[$p['role']] = $p;
}

$defaults = [
    'analis' => ['title' => 'Analis', 'full_title' => 'Analis Kredit'],
    'kasubag_analis' => ['title' => 'Kasubag Analis', 'full_title' => 'Kasubag Analis'],
    'kabag_kredit' => ['title' => 'Kabag Kredit', 'full_title' => 'Kepala Bagian Kredit'],
    'kadiv_bisnis' => ['title' => 'Kadiv Bisnis', 'full_title' => 'Kepala Divisi Bisnis'],
    'direktur_utama' => ['title' => 'Direktur Utama', 'full_title' => 'Direktur Utama']
];

$roleDisplayTitles = [
    'analis' => 'Analis Kredit',
    'kasubag_analis' => 'Kasubag Analis',
    'kabag_kredit' => 'Kepala Bagian Kredit',
    'kadiv_bisnis' => 'Kepala Divisi Bisnis',
    'direktur_utama' => 'Direktur Utama'
];

// Jika ada auto-skip karena pejabat cuti/non-aktif, tampilkan Direktur Utama sebagai pengganti
// di cetakan tanda tangan agar TTD tetap muncul pada posisi terakhir.
$directorReplacementDetected = false;
foreach ((array) $approvals as $approvalEntry) {
    $levelApproval = strtolower(trim((string)($approvalEntry['level_approval'] ?? $approvalEntry['role_approver'] ?? '')));
    $isAutoSkip = (int)($approvalEntry['is_auto_skip'] ?? 0) === 1;
    if ($isAutoSkip && in_array($levelApproval, ['kasubag_analis', 'kabag_kredit', 'kadiv_bisnis'], true)) {
        $directorReplacementDetected = true;
        break;
    }
}

if ($directorReplacementDetected && !isset($approval_map['direktur_utama'])) {
    $directorInfo = $pejabat_by_role['direktur_utama'] ?? ['nama' => 'Direktur Utama', 'jabatan' => 'Direktur Utama'];
    $approval_map['direktur_utama'] = [
        'id_approval' => 0,
        'id_user' => $directorInfo['id_pejabat'] ?? null,
        'level_approval' => 'direktur_utama',
        'role_approver' => 'direktur_utama',
        'nama_approver' => $directorInfo['nama'] ?? 'Direktur Utama',
        'keputusan' => 'setuju',
        'tanggal_approval' => date('Y-m-d H:i:s'),
        'catatan' => 'Pengganti pejabat cuti',
    ];
}

$timeline_roles = $approval_chain_roles;
$signature_roles = buildPrintSignatureSequence($approval_map, $pejabat_by_role, $roleDisplayTitles);

$directorReplacementInSignature = false;
foreach ($signature_roles as $sig) {
    if (($sig['role'] ?? '') === 'direktur_utama' && !empty($sig['acting_for'])) {
        $directorReplacementInSignature = true;
        break;
    }
}

if ($directorReplacementInSignature) {
    $ttd_replacement_note = '⚠ Direktur Utama bertindak sebagai pengganti pejabat yang cuti, tetapi tetap ditampilkan di urutan tanda tangan paling akhir.';
} else {
    $ttd_replacement_note = '';
}

// Paper styles
$paper_styles = [
    'A4' => [
        'name' => 'A4 (210mm × 297mm)',
        'width' => '210mm',
        'height' => '297mm',
        'margin' => '1.5cm'
    ],
    'F4' => [
        'name' => 'F4 Foolscap (210mm × 330mm)',
        'width' => '210mm',
        'height' => '330mm',
        'margin' => '1.5cm'
    ]
];

$paper = $paper_styles[$paper_size];

// Determine back URL based on page source and user role
$back_url = 'detail.php?id=' . $id; // default

if ($from === 'dashboard' || $from === 'riwayat') {
    // Determine dashboard URL based on user role
    $role_dashboards = [
        'analis' => 'analis/dashboard.php',
        'kabag_analis' => 'kabag_analis/dashboard.php',
        'kabag_kredit' => 'kabag_kredit/dashboard.php',
        'kadiv_kredit' => 'kadiv_kredit/dashboard.php',
        'direksi' => 'direksi/dashboard.php',
        'admin' => 'admin/dashboard.php'
    ];
    
    $user_role = $_SESSION['role'] ?? 'analis';
    $back_url = isset($role_dashboards[$user_role]) ? $role_dashboards[$user_role] : 'detail.php?id=' . $id;
}

$scoreRows = [
    ['label' => 'Karakter', 'key' => 'character_score', 'note_key' => 'catatan_character'],
    ['label' => 'Kapasitas', 'key' => 'capacity_score', 'note_key' => 'catatan_capacity'],
    ['label' => 'Modal', 'key' => 'capital_score', 'note_key' => 'catatan_capital'],
    ['label' => 'Agunan', 'key' => 'collateral_score', 'note_key' => 'catatan_collateral'],
    ['label' => 'Condition', 'key' => 'condition_score', 'note_key' => 'catatan_condition'],
    ['label' => 'Risiko', 'key' => 'constraint_score', 'note_key' => 'catatan_constraint_risk'],
];

$analisa_6c_total = 0;
foreach ($scoreRows as $row) {
    $analisa_6c_total += (int)($print_6c[$row['key']] ?? 0);
}
$analisa_6c_total = count($scoreRows) > 0 ? round($analisa_6c_total / count($scoreRows), 2) : 0;
$rekomendasi_6c = $normalizePrintText($print_6c['rekomendasi'] ?? '-', 'Belum ada rekomendasi');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Pengajuan Kredit</title>
    <style>
        :root {
            --bw-blue: #0f3f84;
            --bw-blue-soft: #eaf1fb;
            --bw-blue-2: #1a4d8f;
            --bw-gold: #d8b566;
            --bw-gold-soft: #fff3d6;
            --bw-text: #1f2937;
            --bw-muted: #64748b;
            --bw-line: #d7e2ef;
            --bw-success: #1e8e4a;
            --bw-warning: #d97706;
            --bw-danger: #c0392b;
            --bw-bg: #f3f4f6;
        }

        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: var(--bw-bg);
            color: var(--bw-text);
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            line-height: 1.35;
        }

        .print-shell {
            max-width: 980px;
            margin: 0 auto;
            background: #fff;
            min-height: 100vh;
            padding: 18px 24px 10px;
            box-shadow: 0 0 0 1px rgba(15, 63, 132, 0.05);
        }

        .topmeta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--bw-muted);
            padding: 0 4px;
            margin-bottom: 8px;
        }

        .bank-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-top: 4px;
            padding: 10px 0 6px;
            flex-direction: column;
        }

        .bank-badge {
            width: 410px;
            max-width: 100%;
            height: 240px;
            background: #0a0a0a;
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 14px;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.05);
        }

        .bank-badge svg {
            width: 78%;
            height: 78%;
            display: block;
        }

        .brand-block {
            text-align: center;
            margin-top: 8px;
        }

        .brand-subtitle {
            font-size: 16px;
            font-weight: 700;
            color: var(--bw-blue);
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .brand-name {
            font-size: 30px;
            font-weight: 800;
            color: var(--bw-blue);
            letter-spacing: 0.02em;
            text-transform: uppercase;
            line-height: 1.1;
            margin-top: 2px;
        }

        .brand-name .small {
            font-size: 0.8em;
        }

        .brand-name .strong {
            display: block;
            font-size: 1.05em;
        }

        .brand-sub {
            font-size: 16px;
            font-weight: 700;
            color: var(--bw-blue);
            text-transform: uppercase;
            margin-top: 2px;
        }

        .bank-logo {
            display: none;
        }

        .bank-heading {
            text-align: center;
            margin-top: 6px;
        }

        .rule {
            border-top: 2px solid #1e2a39;
            margin: 16px 0 12px;
        }

        .doc-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            font-size: 13px;
            color: var(--bw-text);
            margin-bottom: 8px;
            padding: 0 4px;
        }

        .doc-row strong {
            font-weight: 700;
        }

        .doc-title {
            text-align: center;
            font-size: 30px;
            font-weight: 800;
            margin: 16px 0 14px;
            letter-spacing: 0.03em;
            color: var(--bw-text);
            text-transform: uppercase;
        }

        .status-banner {
            background: linear-gradient(180deg, #fdf5d8, #f9e7b3);
            color: #9a6200;
            border: 1px solid #e0c777;
            padding: 7px 12px;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-banner::before {
            content: "⚑";
            font-size: 14px;
        }

        .summary-panel {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            border: 1px solid var(--bw-line);
            background: #f4f8ff;
            padding: 12px;
            margin-bottom: 18px;
        }

        .mini-card {
            background: #fff;
            border: 1px solid var(--bw-line);
            padding: 10px 12px;
            min-height: 68px;
        }

        .mini-label {
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--bw-muted);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .mini-value {
            font-size: 15px;
            font-weight: 700;
            color: var(--bw-text);
            word-break: break-word;
        }

        .mini-value.status-ok {
            color: var(--bw-success);
        }

        .mini-value.status-warn {
            color: var(--bw-warning);
        }

        .mini-value.status-danger {
            color: var(--bw-danger);
        }

        .section-box {
            margin-bottom: 18px;
            border: 1px solid var(--bw-line);
            background: #fff;
        }

        .section-head {
            background: linear-gradient(180deg, #0d3d7a 0%, #123c7e 100%);
            color: #fff;
            padding: 10px 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 13px;
        }

        .section-head.light {
            background: linear-gradient(180deg, #1e5ead, #19529a);
        }

        .financial-box {
            background: #fff;
            border: 1px solid var(--bw-line);
            padding: 0;
        }

        .financial-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0;
            background: #f8fbe7;
            border: 1px solid #e7d9a6;
        }

        .kpi {
            background: #f8fbe9;
            padding: 12px 10px;
            border-right: 1px solid #e7d9a6;
            min-height: 78px;
        }

        .kpi:last-child { border-right: none; }

        .kpi label {
            display: block;
            font-size: 10px;
            color: var(--bw-muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .kpi strong {
            font-size: 14px;
            color: var(--bw-text);
        }

        .budget-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
            border: 1px solid var(--bw-line);
        }

        .budget-table td {
            padding: 10px 12px;
            border-top: 1px solid var(--bw-line);
            font-size: 13px;
            background: #eaf3ff;
        }

        .budget-table td:first-child {
            width: 60%;
            font-weight: 600;
            color: var(--bw-text);
            background: #dfeefe;
        }

        .budget-table td:last-child {
            text-align: right;
            font-weight: 700;
            color: var(--bw-blue);
            background: #ebf4ff;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #fff;
        }

        .detail-table th,
        .detail-table td {
            border: 1px solid var(--bw-line);
            padding: 8px 10px;
            font-size: 12px;
            vertical-align: top;
            word-break: break-word;
        }

        .detail-table th {
            width: 24%;
            color: var(--bw-text);
            background: #f6f9ff;
            font-weight: 700;
        }

        .detail-table td {
            background: #fff;
        }

        .line-box {
            padding: 10px 0 0;
        }

        .approval-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            padding: 12px;
            background: #f5f9ff;
            border: 1px solid var(--bw-line);
            margin-top: 0;
        }

        .approval-card {
            min-height: 124px;
            background: #f4f9ff;
            border: 1px solid var(--bw-line);
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .approval-role {
            font-size: 11px;
            font-weight: 800;
            color: var(--bw-blue);
            text-transform: uppercase;
            line-height: 1.4;
        }

        .approval-name {
            font-size: 12px;
            font-weight: 700;
            color: var(--bw-text);
            margin-top: 8px;
        }

        .approval-status {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--bw-success);
            font-weight: 700;
        }

        .approval-status::before {
            content: "✓";
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 3px;
            background: #dff7ea;
            text-align: center;
            line-height: 16px;
        }

        .approval-status.pending {
            color: var(--bw-warning);
        }

        .approval-status.pending::before {
            content: "";
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--bw-warning);
        }

        .footer-note {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid var(--bw-line);
            font-size: 11px;
            color: var(--bw-muted);
        }

        @page {
            size: <?= $paper['width'] ?> <?= $paper['height'] ?>;
            margin: <?= $paper['margin'] ?>;
        }

        @media print {
            body {
                background: #fff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-shell {
                box-shadow: none;
                max-width: none;
                width: 100%;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="print-shell">
        <div class="topmeta">
            <div>03/10/26, 12:31</div>
            <div>Pengajuan <strong><?= htmlspecialchars((string)($data['id_pengajuan'] ?? '-')) ?></strong></div>
        </div>

        <div class="bank-header">
            <div class="bank-badge" aria-label="Logo Bank Wonosobo">
                <svg viewBox="0 0 600 420" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="logoTitle logoDesc">
                    <title id="logoTitle">Logo PT BPR Bank Wonosobo</title>
                    <desc id="logoDesc">Simbol emblem berbentuk gold W pada background hitam</desc>
                    <g fill="none" stroke="#D7B36A" stroke-width="22" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M120 280 L190 120 L260 260 L330 120 L400 280"/>
                        <path d="M160 280 L250 160 L330 280" opacity="0.9"/>
                        <path d="M220 280 L310 160 L400 280" opacity="0.9"/>
                        <path d="M120 280 L90 200 L150 160 L190 120"/>
                        <path d="M480 120 L520 160 L550 200 L520 280 L480 280"/>
                    </g>
                </svg>
            </div>

            <div class="brand-block">
                <div class="brand-subtitle">PT BPR</div>
                <div class="brand-name"><span class="strong">BANK WONOSOBO</span></div>
                <div class="brand-sub">(PERSERODA)</div>
            </div>
        </div>

        <div class="rule"></div>

        <div class="doc-row">
            <div><strong>Nomor:</strong> NK-00034/2026</div>
            <div>Wonosobo, <?= date('d F Y') ?></div>
        </div>

        <div class="doc-title">Lembar Analisa Kredit</div>

        <div class="status-banner">Menunggu Persetujuan</div>

        <div class="summary-panel">
            <div class="mini-card">
                <div class="mini-label">Pemohon</div>
                <div class="mini-value"><?= htmlspecialchars($data['nama_lengkap'] ?? $data['nama_debitur'] ?? $data['nama_pemohon'] ?? '-') ?></div>
            </div>
            <div class="mini-card">
                <div class="mini-label">Status Kredit</div>
                <div class="mini-value status-warn">Menunggu</div>
            </div>
            <div class="mini-card">
                <div class="mini-label">Plafon Disetujui</div>
                <div class="mini-value">Rp <?= number_format((float)($data['jumlah_kredit'] ?? 0), 0, ',', '.') ?></div>
            </div>
            <div class="mini-card">
                <div class="mini-label">Risiko</div>
                <div class="mini-value status-danger"><?= htmlspecialchars(strtoupper((string)($risk_level ?? 'SEDANG'))) ?></div>
            </div>
            <div class="mini-card">
                <div class="mini-label">Jangka Waktu</div>
                <div class="mini-value"><?= htmlspecialchars((string)($data['jangka_waktu'] ?? '-')) ?> Bulan</div>
            </div>
            <div class="mini-card">
                <div class="mini-label">Suku Bunga</div>
                <div class="mini-value"><?= htmlspecialchars((string)($data['suku_bunga'] ?? '0')) ?>%/tahun</div>
            </div>
        </div>

        <div class="section-box">
            <div class="section-head">Analisa Kesehatan Keuangan</div>
            <div class="financial-box">
                <div class="financial-grid">
                    <div class="kpi">
                        <label>Penghasilan Bulanan</label>
                        <strong>Rp <?= number_format((float)($monthly_income ?? 0), 0, ',', '.') ?></strong>
                    </div>
                    <div class="kpi">
                        <label>Angsuran Bulanan</label>
                        <strong>Rp <?= number_format((float)($monthly_installment ?? 0), 0, ',', '.') ?></strong>
                    </div>
                    <div class="kpi">
                        <label>Sisa Kapasitas</label>
                        <strong>Rp <?= number_format((float)($remaining_capacity ?? 0), 0, ',', '.') ?></strong>
                    </div>
                </div>
                <table class="budget-table">
                    <tr>
                        <td>Gaji Pokok / THB Bulanan</td>
                        <td>Rp <?= number_format((float)($data['omset_per_bulan'] ?? 0), 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td>Tunjangan & Pendapatan Lain</td>
                        <td>Rp <?= number_format((float)($data['pendapatan_lain'] ?? 0), 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td>Total Penghasilan Bulanan</td>
                        <td>Rp <?= number_format((float)($monthly_income ?? 0), 0, ',', '.') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="section-box">
            <div class="section-head light">I. Data Diri Pemohon</div>
            <table class="detail-table">
                <tr>
                    <th>Nama Debitur</th>
                    <td><?= htmlspecialchars($data['nama_lengkap'] ?? $data['nama_debitur'] ?? '-') ?></td>
                    <th>Jabatan</th>
                    <td><?= htmlspecialchars($data['pekerjaan'] ?? $data['jenis_pekerjaan'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Nomor CIF</th>
                    <td><?= htmlspecialchars($data['nik'] ?? '-') ?></td>
                    <th>Usia</th>
                    <td><?= htmlspecialchars((string)($data['usia'] ?? '-')) ?> Tahun</td>
                </tr>
                <tr>
                    <th>Nomor KTP</th>
                    <td><?= htmlspecialchars($data['nik'] ?? '-') ?></td>
                    <th>Sisa Masa Kerja</th>
                    <td><?= htmlspecialchars((string)($data['masa_kerja'] ?? '-')) ?></td>
                </tr>
                <tr>
                    <th>Alamat</th>
                    <td colspan="3"><?= htmlspecialchars($data['alamat_domisili'] ?? $data['alamat_ktp'] ?? $data['alamat'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Jaminan</th>
                    <td><?= !empty($jaminan_tanah) || !empty($jaminan_kendaraan) || !empty($jaminan_emas) ? 'Tersedia' : 'Tidak tersedia' ?></td>
                    <th>Pekerjaan</th>
                    <td><?= htmlspecialchars($data['pekerjaan'] ?? $data['nama_usaha'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Pinjaman Ke</th>
                    <td><?= htmlspecialchars((string)($data['pinjaman_ke'] ?? '1')) ?></td>
                    <th>Tujuan Kredit</th>
                    <td><?= htmlspecialchars($data['tujuan_penggunaan'] ?? $data['tujuan_kredit'] ?? '-') ?></td>
                </tr>
            </table>
        </div>

        <div class="section-box">
            <div class="section-head light">II. Informasi Kredit & Struktur Pembayaran</div>
            <table class="detail-table">
                <tr>
                    <th>Produk Kredit</th>
                    <td><?= htmlspecialchars($data['jenis_kredit'] ?? '-') ?></td>
                    <th>KK</th>
                    <td><?= htmlspecialchars($data['jenis_pekerjaan'] ?? 'UMUM') ?></td>
                </tr>
                <tr>
                    <th>Tujuan Kredit</th>
                    <td><?= htmlspecialchars($data['tujuan_penggunaan'] ?? $data['tujuan_kredit'] ?? '-') ?></td>
                    <th>Plafon</th>
                    <td>Rp <?= number_format((float)($data['jumlah_kredit'] ?? 0), 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <th>Jangka Waktu</th>
                    <td><?= htmlspecialchars((string)($data['jangka_waktu'] ?? '-')) ?> Bulan</td>
                    <th>Suku Bunga</th>
                    <td><?= htmlspecialchars((string)($data['suku_bunga'] ?? '0')) ?>% per tahun</td>
                </tr>
                <tr>
                    <th>Angsuran Per Bulan</th>
                    <td>Rp <?= number_format((float)($monthly_installment ?? 0), 0, ',', '.') ?></td>
                    <th>Grace Period</th>
                    <td><?= htmlspecialchars((string)($data['grace_period'] ?? '0')) ?> Bulan</td>
                </tr>
                <tr>
                    <th>Agunan / Jaminan</th>
                    <td colspan="3"><?= !empty($jaminan_rows) ? implode(', ', $jaminan_rows) : '-' ?></td>
                </tr>
            </table>
        </div>

        <div class="section-box">
            <div class="section-head light">III. Hasil Analisa 6C (Kelayakan Kredit)</div>
            <table class="detail-table">
                <tr>
                    <th>Unsur Penilaian (6C)</th>
                    <th>Skor Nilai</th>
                    <th>Interpretasi / Keterangan</th>
                </tr>
                <?php foreach ($scoreRows as $item): ?>
                    <?php $score = (int)($print_6c[$item['key']] ?? 0); ?>
                    <tr>
                        <td><?= htmlspecialchars($item['label']) ?></td>
                        <td><?= $score ?: '-' ?></td>
                        <td><?= htmlspecialchars($gradeLabel6C($score)) ?: '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th>Total SKOR 6C</th>
                    <td colspan="2"><?= number_format((float)$analisa_6c_total, 2, ',', '.') ?> / 5.00</td>
                </tr>
            </table>
        </div>

        <div class="section-box">
            <div class="section-head light">Kesimpulan Akhir & Rekomendasi</div>
            <table class="detail-table">
                <tr>
                    <th>Status Risiko</th>
                    <td><?= htmlspecialchars(strtoupper((string)($risk_level ?? 'SEDANG'))) ?></td>
                </tr>
                <tr>
                    <th>Kelayakan Kredit</th>
                    <td><?= $semua_disetujui ? 'LAYAK' : 'MENUNGGU' ?></td>
                </tr>
                <tr>
                    <th>Status Kelayakan Pembayaran</th>
                    <td><?= ($remaining_capacity ?? 0) > 0 ? 'LAYAK' : 'TIDAK LAYAK' ?></td>
                </tr>
                <tr>
                    <th>Repayment Capacity</th>
                    <td>Rp <?= number_format((float)($data['repayment_capacity'] ?? 0), 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <th>Rekomendasi Analisis</th>
                    <td><?= htmlspecialchars($rekomendasi_6c) ?></td>
                </tr>
                <tr>
                    <th>Catatan Khusus</th>
                    <td><?= htmlspecialchars($normalizePrintText($compliance_data['catatan'] ?? 'Tidak ada catatan khusus', 'Tidak ada catatan khusus')) ?></td>
                </tr>
            </table>
        </div>

        <div class="section-box">
            <div class="section-head">V. Timeline Proses Persetujuan</div>
            <div class="approval-grid">
                <?php foreach ($signature_roles as $sig): ?>
                    <?php
                    $role = $sig['role'] ?? '';
                    $nama = $sig['nama'] ?? '';
                    $status = !empty($sig['keputusan']) && strtolower((string)$sig['keputusan']) === 'setuju' ? 'Disetujui' : 'Menunggu Persetujuan';
                    $statusClass = !empty($sig['keputusan']) && strtolower((string)$sig['keputusan']) === 'setuju' ? '' : 'pending';
                    ?>
                    <div class="approval-card">
                        <div class="approval-role"><?= htmlspecialchars($roleDisplayTitles[$role] ?? $role) ?></div>
                        <div class="approval-name"><?= htmlspecialchars($nama ?: '-') ?></div>
                        <div class="approval-status <?= $statusClass ?>"><?= $status ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="footer-note">
            <div>Dokumen ini dicetak otomatis oleh sistem analisa kredit BPR Bank Wonosobo.</div>
            <div>Halaman 1 dari 2</div>
        </div>
    </div>
</body>
</html>


