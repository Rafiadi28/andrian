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
            --bw-navy: #0f172a;
            --bw-blue: #1d4ed8;
            --bw-gold: #b99543;
            --bw-soft: #f8fafc;
            --bw-line: #dfe7ef;
            --bw-text: #1f2937;
            --bw-muted: #5b6472;
            --bw-success: #15803d;
            --bw-warning: #b45309;
            --bw-danger: #b91c1c;
        }

        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: var(--bw-text);
            font-family: Arial, Helvetica, sans-serif;
        }
        body { line-height: 1.4; }

        .print-shell {
            max-width: 1000px;
            margin: 0 auto;
            padding: 18px 22px 28px;
        }

        .letterhead {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            border-bottom: 3px solid var(--bw-gold);
            padding-bottom: 14px;
            margin-bottom: 18px;
        }

        .logo-box {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: #eff6ff;
            border: 2px solid var(--bw-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .logo-box img {
            width: 78px;
            height: 78px;
            object-fit: contain;
            display: block;
        }

        .brand-copy {
            flex: 1;
            min-width: 0;
        }

        .bank-name {
            font-size: 23px;
            font-weight: 700;
            color: var(--bw-navy);
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .bank-sub {
            font-size: 11px;
            letter-spacing: 0.09em;
            color: var(--bw-muted);
            text-transform: uppercase;
            margin-top: 3px;
        }

        .doc-code {
            min-width: 190px;
            text-align: right;
            color: var(--bw-muted);
            font-size: 11px;
        }

        .doc-code strong {
            display: block;
            margin-top: 4px;
            font-size: 16px;
            color: var(--bw-navy);
        }

        .doc-title-box {
            background: #f8fafc;
            border: 1px solid var(--bw-line);
            padding: 14px 18px;
            margin-bottom: 16px;
        }

        .doc-title-box h1 {
            margin: 0;
            text-align: center;
            font-size: 26px;
            font-weight: 700;
            color: var(--bw-navy);
            letter-spacing: 0.06em;
        }

        .doc-title-meta {
            margin-top: 8px;
            text-align: center;
            font-size: 11px;
            color: var(--bw-muted);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .summary-card {
            border: 1px solid var(--bw-line);
            background: #fff;
            padding: 12px 14px;
            min-height: 108px;
        }

        .summary-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--bw-muted);
        }

        .summary-value {
            margin-top: 8px;
            font-size: 15px;
            font-weight: 700;
            color: var(--bw-navy);
            word-break: break-word;
        }

        .summary-value.status-approved { color: var(--bw-success); }
        .summary-value.status-pending { color: var(--bw-warning); }

        .section {
            margin-bottom: 18px;
            border: 1px solid var(--bw-line);
            background: #fff;
        }

        .section-header {
            background: #0f172a;
            color: #fff;
            padding: 10px 14px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            padding: 9px 12px;
            border-bottom: 1px solid var(--bw-line);
            vertical-align: top;
            word-break: break-word;
            font-size: 12px;
        }

        th {
            width: 34%;
            text-align: left;
            background: #f8fafc;
            color: var(--bw-navy);
            font-weight: 700;
        }

        td {
            color: var(--bw-text);
        }

        .muted { color: var(--bw-muted); }

        .status-pill {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            background: #dcfce7;
            color: var(--bw-success);
        }

        .status-pill.pending {
            background: #fef3c7;
            color: var(--bw-warning);
        }

        .signature-block {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
            padding: 18px 14px 10px;
        }

        .signature-box {
            min-height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .signature-role {
            font-size: 11px;
            font-weight: 700;
            color: var(--bw-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 10px;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            min-height: 28px;
            margin-bottom: 6px;
        }

        .signature-name {
            font-size: 12px;
            font-weight: 700;
            color: var(--bw-navy);
        }

        .signature-img {
            margin-top: 8px;
            max-width: 140px;
            max-height: 60px;
            display: block;
        }

        .note {
            margin-top: 10px;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px dashed var(--bw-line);
            font-size: 11px;
            color: var(--bw-muted);
        }

        .score-table {
            border-collapse: collapse;
            width: 100%;
            margin: 0;
        }

        .score-table th,
        .score-table td {
            border: 1px solid var(--bw-line);
            padding: 8px 10px;
            text-align: left;
            font-size: 12px;
        }

        .score-table th {
            width: 28%;
            background: #f8fafc;
        }

        .score-table td:nth-child(2) {
            width: 16%;
            text-align: center;
            font-weight: 700;
        }

        @page {
            size: <?= $paper['width'] ?> <?= $paper['height'] ?>;
            margin: <?= $paper['margin'] ?>;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="print-shell">
        <header class="letterhead">
            <div class="logo-box">
                <img src="assets/img/logo_bank.png" alt="Logo Bank Wonosobo">
            </div>
            <div class="brand-copy">
                <div class="bank-name">PT. BPR Bank Wonosobo (Persero)</div>
                <div class="bank-sub">Sistem Analisa Kredit</div>
            </div>
            <div class="doc-code">
                <span>Nomor Pengajuan</span>
                <strong><?= htmlspecialchars((string)($data['id_pengajuan'] ?? '-')) ?></strong>
            </div>
        </header>

        <div class="doc-title-box">
            <h1>FORMULIR ANALISA KREDIT</h1>
            <div class="doc-title-meta">
                <?= htmlspecialchars($data['nama_lengkap'] ?? $data['nama_debitur'] ?? $data['nama_pemohon'] ?? '-') ?> • <?= htmlspecialchars($data['jenis_kredit'] ?? '-') ?> • <?= date('d-m-Y') ?>
            </div>
        </div>

        <section class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Pemohon</div>
                <div class="summary-value"><?= htmlspecialchars($data['nama_lengkap'] ?? $data['nama_debitur'] ?? $data['nama_pemohon'] ?? '-') ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Plafon</div>
                <div class="summary-value">Rp <?= number_format((float)($data['jumlah_kredit'] ?? 0), 0, ',', '.') ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Tenor</div>
                <div class="summary-value"><?= htmlspecialchars((string)($data['jangka_waktu'] ?? '-')) ?> Bulan</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Status</div>
                <div class="summary-value <?= (strtolower(trim((string)($teks_status ?? ''))) === '✓ disetujui' || strtolower(trim((string)($teks_status ?? ''))) === '✓ disetujui untuk dicairkan') ? 'status-approved' : 'status-pending' ?>"><?= $teks_status ?></div>
            </div>
        </section>

        <section class="section">
            <div class="section-header">I. Data Pengajuan</div>
            <div class="section-body">
                <table>
                    <tr><th>ID Pengajuan</th><td><?= htmlspecialchars((string)($data['id_pengajuan'] ?? '-')) ?></td></tr>
                    <tr><th>Nama Debitur</th><td><?= htmlspecialchars($data['nama_lengkap'] ?? $data['nama_debitur'] ?? $data['nama_pemohon'] ?? '-') ?></td></tr>
                    <tr><th>Jenis Kredit</th><td><?= htmlspecialchars($data['jenis_kredit'] ?? '-') ?></td></tr>
                    <tr><th>Jangka Waktu</th><td><?= htmlspecialchars((string)($data['jangka_waktu'] ?? '-')) ?> bulan</td></tr>
                    <tr><th>Plafon</th><td>Rp <?= number_format((float)($data['jumlah_kredit'] ?? 0), 0, ',', '.') ?></td></tr>
                    <tr><th>Suku Bunga</th><td><?= htmlspecialchars((string)($data['suku_bunga'] ?? '-')) ?>%</td></tr>
                    <tr><th>Tujuan Penggunaan</th><td><?= htmlspecialchars($data['tujuan_penggunaan'] ?? $data['tujuan_kredit'] ?? '-') ?></td></tr>
                    <tr><th>Status</th><td><span class="status-pill <?= (strtolower(trim((string)($teks_status ?? ''))) === '✓ disetujui' || strtolower(trim((string)($teks_status ?? ''))) === '✓ disetujui untuk dicairkan') ? '' : 'pending' ?>"><?= $teks_status ?></span></td></tr>
                </table>
            </div>
        </section>

        <section class="section">
            <div class="section-header">II. Data Pemohon</div>
            <div class="section-body">
                <table>
                    <tr><th>Nama Lengkap</th><td><?= htmlspecialchars($data['nama_lengkap'] ?? $data['nama_debitur'] ?? $data['nama_pemohon'] ?? '-') ?></td></tr>
                    <tr><th>NIK</th><td><?= htmlspecialchars($data['nik'] ?? '-') ?></td></tr>
                    <tr><th>NPWP</th><td><?= htmlspecialchars($data['npwp'] ?? '-') ?></td></tr>
                    <tr><th>Alamat KTP</th><td><?= htmlspecialchars($data['alamat_ktp'] ?? '-') ?></td></tr>
                    <tr><th>Alamat Domisili</th><td><?= htmlspecialchars($data['alamat_domisili'] ?? $data['alamat'] ?? '-') ?></td></tr>
                    <tr><th>No HP</th><td><?= htmlspecialchars($data['no_hp'] ?? $data['no_telepon'] ?? '-') ?></td></tr>
                    <tr><th>Status Pernikahan</th><td><?= htmlspecialchars($data['status_perkawinan'] ?? '-') ?></td></tr>
                    <tr><th>Nama Pasangan</th><td><?= htmlspecialchars($data['nama_pasangan'] ?? '-') ?></td></tr>
                </table>
            </div>
        </section>

        <section class="section">
            <div class="section-header">III. Analisa Keuangan & Usaha</div>
            <div class="section-body">
                <table>
                    <tr><th>Pekerjaan / Usaha</th><td><?= htmlspecialchars($data['pekerjaan'] ?? $data['nama_usaha'] ?? '-') ?></td></tr>
                    <tr><th>Bidang Usaha</th><td><?= htmlspecialchars($data['bidang_usaha'] ?? '-') ?></td></tr>
                    <tr><th>Omzet / Bulan</th><td>Rp <?= number_format((float)($data['omset_per_bulan'] ?? 0), 0, ',', '.') ?></td></tr>
                    <tr><th>Pendapatan Lain</th><td>Rp <?= number_format((float)($data['pendapatan_lain'] ?? 0), 0, ',', '.') ?></td></tr>
                    <tr><th>Pengeluaran Tetap</th><td>Rp <?= number_format((float)($data['total_pengeluaran_tetap'] ?? 0), 0, ',', '.') ?></td></tr>
                    <tr><th>Repayment Capacity</th><td>Rp <?= number_format((float)($data['repayment_capacity'] ?? 0), 0, ',', '.') ?></td></tr>
                    <tr><th>Angsuran Diajukan</th><td>Rp <?= number_format((float)($data['angsuran_diajukan'] ?? 0), 0, ',', '.') ?></td></tr>
                </table>
            </div>
        </section>

        <section class="section">
            <div class="section-header">IV. Jaminan</div>
            <div class="section-body">
                <?php
                $jaminan_rows = [];
                foreach ($jaminan_tanah as $jt) {
                    $jaminan_rows[] = '<strong>Tanah/Bangunan:</strong> ' . htmlspecialchars($jt['alamat_agunan'] ?? $jt['alamat'] ?? '-') . ' • Nilai: Rp ' . number_format((float)($jt['nilai_taksasi'] ?? $jt['nilai_pasar'] ?? 0), 0, ',', '.');
                }
                foreach ($jaminan_kendaraan as $jk) {
                    $jaminan_rows[] = '<strong>Kendaraan:</strong> ' . htmlspecialchars(trim((string)($jk['merk'] ?? '') . ' ' . ($jk['tipe'] ?? '')) ?: '-') . ' • Nilai: Rp ' . number_format((float)($jk['nilai_taksasi'] ?? $jk['nilai_pasar'] ?? 0), 0, ',', '.');
                }
                foreach ($jaminan_emas as $je) {
                    $jaminan_rows[] = '<strong>Emas:</strong> ' . htmlspecialchars((string)($je['berat'] ?? '-')) . ' gr • Nilai: Rp ' . number_format((float)($je['nilai_pasar'] ?? 0), 0, ',', '.');
                }
                ?>
                <table>
                    <tr>
                        <th>Detail Jaminan</th>
                        <td><?= !empty($jaminan_rows) ? implode('<br>', $jaminan_rows) : '<span class="muted">-</span>' ?></td>
                    </tr>
                    <tr>
                        <th>Total Nilai Jaminan</th>
                        <td>Rp <?= number_format((float)$total_collateral, 0, ',', '.') ?></td>
                    </tr>
                </table>
            </div>
        </section>

        <section class="section">
            <div class="section-header">V. Analisa 6C</div>
            <div class="section-body">
                <table class="score-table">
                    <tr>
                        <th>Komponen</th>
                        <th>Skor</th>
                        <th>Penilaian</th>
                        <th>Catatan</th>
                    </tr>
                    <?php foreach ($scoreRows as $item): ?>
                        <?php
                        $score = (int)($print_6c[$item['key']] ?? 0);
                        $note = $normalizePrintText($print_6c[$item['note_key']] ?? '', '-');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($item['label']) ?></td>
                            <td style="color: <?= $colorLabel6C($score) ?>;"><?= $score ?: '-' ?></td>
                            <td><?= $score ? htmlspecialchars($gradeLabel6C($score)) : '-' ?></td>
                            <td><?= htmlspecialchars($note) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th>Rata-rata Skor</th>
                        <td colspan="3"><?= number_format((float)$analisa_6c_total, 2, ',', '.') ?> / 5</td>
                    </tr>
                    <tr>
                        <th>Rekomendasi</th>
                        <td colspan="3"><?= htmlspecialchars($rekomendasi_6c) ?></td>
                    </tr>
                </table>
            </div>
        </section>

        <section class="section">
            <div class="section-header">VI. Assessment Kepatuhan</div>
            <div class="section-body">
                <table>
                    <?php
                    $hasil_kepatuhan = $normalizePrintText($compliance_data['hasil_kepatuhan'] ?? 'Belum diisi', 'Belum diisi');
                    $kesimpulan_kepatuhan = $normalizePrintText($compliance_data['kesimpulan'] ?? '-', '-');
                    $rekomendasi_kepatuhan = $normalizePrintText($compliance_data['rekomendasi'] ?? '-', '-');
                    ?>
                    <tr><th>Hasil</th><td><?= htmlspecialchars(strtoupper($hasil_kepatuhan)) ?></td></tr>
                    <tr><th>Checklist</th><td><?= $compliance_summary['comply'] . ' comply / ' . $compliance_summary['not_comply'] . ' not comply / ' . $compliance_summary['na'] . ' N/A' ?></td></tr>
                    <tr><th>Kesimpulan</th><td><?= nl2br(htmlspecialchars($kesimpulan_kepatuhan)) ?></td></tr>
                    <tr><th>Rekomendasi</th><td><?= nl2br(htmlspecialchars($rekomendasi_kepatuhan)) ?></td></tr>
                </table>
            </div>
        </section>

        <section class="section">
            <div class="section-header">VII. Persetujuan</div>
            <div class="section-body">
                <table>
                    <tr>
                        <th>Role</th>
                        <th>Nama</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                    </tr>
                    <?php if (!empty($approvals)): ?>
                        <?php foreach ($approvals as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($a['role_approver'] ?? $a['level_approval'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($a['nama_approver'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($a['tanggal'] ?? $a['tanggal_approval'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($a['keputusan'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="muted">Belum ada riwayat persetujuan.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </section>

        <section class="section">
            <div class="section-header">VIII. Tanda Tangan Pejabat</div>
            <div class="signature-block">
                <?php foreach ($signature_roles as $sig): ?>
                    <?php
                    $role = $sig['role'] ?? '';
                    $nama_pejabat = $sig['nama'] ?? '';
                    $tanda_tangan = $sig['tanda_tangan'] ?? '';
                    $stempel = $sig['stempel'] ?? '';
                    if (empty($tanda_tangan) && empty($stempel) && empty($nama_pejabat)) {
                        continue;
                    }
                    ?>
                    <div class="signature-box">
                        <div class="signature-role"><?= htmlspecialchars($roleDisplayTitles[$role] ?? $role) ?></div>
                        <div class="signature-line"></div>
                        <div class="signature-name"><?= htmlspecialchars($nama_pejabat) ?></div>
                        <?php if (!empty($tanda_tangan)): ?>
                            <img class="signature-img" src="<?= htmlspecialchars($tanda_tangan) ?>" alt="Tanda Tangan">
                        <?php endif; ?>
                        <?php if (!empty($stempel)): ?>
                            <img class="signature-img" src="<?= htmlspecialchars($stempel) ?>" alt="Stempel" style="max-width:90px; margin-top:4px;">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($ttd_replacement_note)): ?>
                <div class="note"><?= htmlspecialchars($ttd_replacement_note) ?></div>
            <?php endif; ?>
        </section>

        <div class="note">Catatan: Dokumen ini dihasilkan secara otomatis oleh sistem analisa kredit Bank Wonosobo untuk kepentingan internal dan proses pencairan.</div>
    </div>
</body>
</html>


