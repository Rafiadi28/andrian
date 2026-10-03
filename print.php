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

// Analis: only print their own submissions
if (($_SESSION['role'] ?? '') === 'analis'
    && (int)($data['input_by'] ?? 0) !== (int)($_SESSION['user_id'] ?? 0)) {
    http_response_code(403);
    die("<h2>Akses Ditolak</h2><p>Anda hanya dapat mencetak dokumen pengajuan yang Anda input sendiri.</p>");
}

// Fetch 6C analysis data
$stmt6c = $pdo->prepare("SELECT * FROM analisa_5c WHERE id_pengajuan = ?");
$stmt6c->execute([$id]);
$print_6c = $stmt6c->fetch(PDO::FETCH_ASSOC);

// ===== FETCH COMPLIANCE ASSESSMENT DATA =====
$stmt_compliance = $pdo->prepare("SELECT * FROM assessment_kepatuhan WHERE id_pengajuan = ?");
$stmt_compliance->execute([$id]);
$compliance_data = $stmt_compliance->fetch(PDO::FETCH_ASSOC);

// Parse compliance checklist (filter out N/A items)
$compliance_items = [];
if ($compliance_data && !empty($compliance_data['checklist_data'])) {
    $all_checklist = json_decode($compliance_data['checklist_data'], true) ?: [];
    foreach ($all_checklist as $key => $item) {
        // Only include items that are NOT 'na' (N/A)
        if (isset($item['val']) && $item['val'] !== 'na') {
            $compliance_items[$key] = $item;
        }
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
    ORDER BY FIELD(role, 'analis', 'kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama')
");
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

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: var(--bw-text);
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            line-height: 1.45;
        }

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
            page-break-inside: avoid;
        }

        .bank-badge {
            width: 76px;
            height: 76px;
            border: 2px solid var(--bw-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            color: var(--bw-blue);
            background: #eff6ff;
            flex-shrink: 0;
        }

        .letterhead-main {
            flex: 1;
            min-width: 0;
        }

        .bank-name {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.03em;
            color: var(--bw-navy);
            text-transform: uppercase;
        }

        .bank-sub {
            margin-top: 2px;
            color: var(--bw-muted);
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .letterhead-meta {
            min-width: 180px;
            font-size: 11px;
            text-align: right;
            color: var(--bw-muted);
        }

        .letterhead-meta strong {
            display: block;
            margin-top: 4px;
            font-size: 15px;
            color: var(--bw-navy);
        }

        .doc-title-box {
            background: var(--bw-soft);
            border: 1px solid var(--bw-line);
            padding: 14px 18px;
            margin-bottom: 18px;
            page-break-inside: avoid;
        }

        .doc-title-box h1 {
            margin: 0;
            padding: 0;
            font-size: 26px;
            text-align: center;
            font-weight: 700;
            color: var(--bw-navy);
            letter-spacing: 0.06em;
        }

        .page-meta {
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
            page-break-inside: avoid;
        }

        .summary-card {
            border: 1px solid var(--bw-line);
            background: #fff;
            padding: 12px 14px;
            min-height: 110px;
            page-break-inside: avoid;
        }

        .summary-label {
            font-size: 10px;
            color: var(--bw-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .summary-value {
            margin-top: 8px;
            font-size: 15px;
            font-weight: 700;
            color: var(--bw-navy);
            word-break: break-word;
        }

        .summary-value.status-approved {
            color: var(--bw-success);
        }

        .summary-value.status-pending {
            color: var(--bw-warning);
        }

        .section {
            margin-bottom: 18px;
            border: 1px solid var(--bw-line);
            background: #fff;
            page-break-inside: avoid;
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

        .section-body {
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
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
            background: var(--bw-soft);
            color: var(--bw-navy);
            font-weight: 700;
        }

        td {
            color: var(--bw-text);
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .section .section-body table:last-child th:last-child,
        .section .section-body table:last-child td:last-child,
        .section .section-body table:last-child th,
        .section .section-body table:last-child td {
            border-bottom: none;
        }

        .muted {
            color: var(--bw-muted);
        }

        .signature-block {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
            padding: 18px 14px 10px;
            page-break-inside: avoid;
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

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            background: #dcfce7;
            color: var(--bw-success);
        }

        .status-badge.pending {
            background: #fef3c7;
            color: var(--bw-warning);
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
            <div class="bank-badge">BPR</div>
            <div class="letterhead-main">
                <div class="bank-name">PT. BPR Bank Wonosobo (Persero)</div>
                <div class="bank-sub">Unit Analisis Kredit</div>
            </div>
            <div class="letterhead-meta">
                <span>Nomor Pengajuan</span>
                <strong><?= htmlspecialchars((string)($data['id_pengajuan'] ?? '-')) ?></strong>
            </div>
        </header>

        <div class="doc-title-box">
            <h1>FORMULIR PENGAJUAN KREDIT</h1>
            <div class="page-meta">
                <?= htmlspecialchars($data['nama_lengkap'] ?? $data['nama_pemohon'] ?? '-') ?> • <?= htmlspecialchars($data['jenis_kredit'] ?? '-') ?> • <?= date('d-m-Y') ?>
            </div>
        </div>

        <section class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Pemohon</div>
                <div class="summary-value"><?= htmlspecialchars($data['nama_lengkap'] ?? $data['nama_pemohon'] ?? '-') ?></div>
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
                <div class="summary-value <?= strtolower(trim((string)($teks_status ?? ''))) === '✓ disetujui' || strtolower(trim((string)($teks_status ?? ''))) === '✓ disetujui untuk dicairkan' ? 'status-approved' : 'status-pending' ?>"><?= $teks_status ?></div>
            </div>
        </section>

        <section class="section">
            <div class="section-header">Data Pengajuan</div>
            <div class="section-body">
                <table>
                    <tr>
                        <th>ID Pengajuan</th>
                        <td><?= htmlspecialchars((string)($data['id_pengajuan'] ?? '-')) ?></td>
                    </tr>
                    <tr>
                        <th>Nama Pemohon</th>
                        <td><?= htmlspecialchars($data['nama_pemohon'] ?? $data['nama_lengkap'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Jenis Kredit</th>
                        <td><?= htmlspecialchars($data['jenis_kredit'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Tujuan Penggunaan</th>
                        <td><?= htmlspecialchars($data['tujuan_penggunaan'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Jumlah Kredit</th>
                        <td>Rp <?= number_format((float)($data['jumlah_kredit'] ?? 0), 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <th>Jangka Waktu</th>
                        <td><?= htmlspecialchars((string)($data['jangka_waktu'] ?? '-')) ?> bulan</td>
                    </tr>
                    <tr>
                        <th>Suku Bunga</th>
                        <td><?= htmlspecialchars((string)($data['suku_bunga'] ?? '-')) ?>%</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="status-badge <?= (strtolower(trim((string)($teks_status ?? ''))) === '✓ disetujui' || strtolower(trim((string)($teks_status ?? ''))) === '✓ disetujui untuk dicairkan') ? '' : 'pending' ?>"><?= $teks_status ?></span></td>
                    </tr>
                </table>
            </div>
        </section>

        <section class="section">
            <div class="section-header">Data Diri Pemohon</div>
            <div class="section-body">
                <table>
                    <tr>
                        <th>Nama Lengkap</th>
                        <td><?= htmlspecialchars($data['nama_lengkap'] ?? $data['nama_pemohon'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Tempat / Tgl Lahir</th>
                        <td><?= htmlspecialchars(trim((string)($data['tempat_lahir'] ?? '')) . (!empty($data['tempat_lahir']) && !empty($data['tanggal_lahir']) ? ', ' : '') . ($data['tanggal_lahir'] ?? '')) ?></td>
                    </tr>
                    <tr>
                        <th>Jenis Kelamin</th>
                        <td><?= htmlspecialchars($data['jenis_kelamin'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Alamat</th>
                        <td><?= htmlspecialchars($data['alamat'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>No. Telepon</th>
                        <td><?= htmlspecialchars($data['no_telepon'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?= htmlspecialchars($data['email'] ?? '-') ?></td>
                    </tr>
                </table>
            </div>
        </section>

        <section class="section">
            <div class="section-header">Jaminan</div>
            <div class="section-body">
                <?php
                $jaminan_list = [];
                foreach ($jaminan_tanah as $jt) {
                    $jaminan_list[] = '<strong>Tanah/Bangunan:</strong> ' . htmlspecialchars($jt['alamat'] ?? '-') . ' • Nilai: Rp ' . number_format((float)($jt['nilai_taksasi'] ?? $jt['nilai_pasar'] ?? 0), 0, ',', '.');
                }
                foreach ($jaminan_kendaraan as $jk) {
                    $jaminan_list[] = '<strong>Kendaraan:</strong> ' . htmlspecialchars($jk['jenis'] ?? '-') . ' • Nilai: Rp ' . number_format((float)($jk['nilai_taksasi'] ?? $jk['nilai_pasar'] ?? 0), 0, ',', '.');
                }
                foreach ($jaminan_emas as $je) {
                    $jaminan_list[] = '<strong>Emas:</strong> ' . htmlspecialchars((string)($je['berat'] ?? '-')) . ' gr • Nilai: Rp ' . number_format((float)($je['nilai_pasar'] ?? 0), 0, ',', '.');
                }
                ?>
                <table>
                    <tr>
                        <th>Detail Jaminan</th>
                        <td><?= !empty($jaminan_list) ? implode('<br>', $jaminan_list) : '<span class="muted">-</span>' ?></td>
                    </tr>
                    <tr>
                        <th>Total Nilai Jaminan</th>
                        <td>Rp <?= number_format((float)$total_collateral, 0, ',', '.') ?></td>
                    </tr>
                </table>
            </div>
        </section>

        <section class="section">
            <div class="section-header">Analisa 6C</div>
            <div class="section-body">
                <table>
                    <tr><th>Karakter</th><td><?= htmlspecialchars((string)($print_6c['karakter'] ?? '-')) ?></td></tr>
                    <tr><th>Kapasitas</th><td><?= htmlspecialchars((string)($print_6c['kapasitas'] ?? '-')) ?></td></tr>
                    <tr><th>Modal</th><td><?= htmlspecialchars((string)($print_6c['modal'] ?? '-')) ?></td></tr>
                    <tr><th>Agunan</th><td><?= htmlspecialchars((string)($print_6c['agunan'] ?? '-')) ?></td></tr>
                    <tr><th>Syariah</th><td><?= htmlspecialchars((string)($print_6c['syariah'] ?? '-')) ?></td></tr>
                    <tr><th>Risiko</th><td><?= htmlspecialchars((string)($print_6c['risiko'] ?? '-')) ?></td></tr>
                </table>
            </div>
        </section>

        <section class="section">
            <div class="section-header">Assessment Kepatuhan</div>
            <div class="section-body">
                <table>
                    <tr><th>Kepatuhan</th><td><?= htmlspecialchars((string)($compliance_data['kepatuhan'] ?? '-')) ?></td></tr>
                    <tr><th>Catatan</th><td><?= nl2br(htmlspecialchars((string)($compliance_data['catatan'] ?? '-'))) ?></td></tr>
                </table>
            </div>
        </section>

        <section class="section">
            <div class="section-header">Riwayat Persetujuan</div>
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
                                <td><?= htmlspecialchars((string)($a['tanggal'] ?? '-')) ?></td>
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
            <div class="section-header">Tanda Tangan Pejabat</div>
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
                            <img class="signature-img" src="<?= htmlspecialchars($stempel) ?>" alt="Stempel" style="max-width: 90px; margin-top: 4px;">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($ttd_replacement_note)): ?>
                <div class="note"><?= htmlspecialchars($ttd_replacement_note) ?></div>
            <?php endif; ?>
        </section>

        <div class="note">Catatan: Dokumen ini dihasilkan secara otomatis oleh sistem analisa kredit BPR Bank Wonosobo untuk keperluan internal dan pencairan.</div>
    </div>
</body>
</html>


