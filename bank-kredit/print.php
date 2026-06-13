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

// Get approval timeline - show only latest approval per level
// Use subquery to get latest id_approval per level to avoid ONLY_FULL_GROUP_BY error
$stmt = $pdo->prepare("
    SELECT a.*, u.nama as nama_approver, u.role as role_approver 
    FROM approval_kredit a 
    LEFT JOIN users u ON a.id_user = u.id_user 
    WHERE a.id_pengajuan = ? AND a.keputusan = 'setuju'
    AND a.id_approval IN (
        SELECT MAX(id_approval) 
        FROM approval_kredit 
        WHERE id_pengajuan = ? AND keputusan = 'setuju'
        GROUP BY level_approval
    )
    ORDER BY FIELD(a.level_approval, 'analis', 'kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama')
");
$stmt->execute([$id, $id]);
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
$monthly_income = floatval($data['omset_per_bulan'] ?? 0) + floatval($data['pendapatan_lain'] ?? 0);
$monthly_expense = floatval($data['total_pengeluaran_tetap'] ?? 0) + floatval($data['biaya_hidup'] ?? 0);
$monthly_installment = floatval($data['angsuran_diajukan'] ?? 0);
$loan_amount = floatval($data['jumlah_kredit'] ?? 0);

// If angsuran_diajukan is 0, calculate from jumlah_kredit / jangka_waktu
if ($monthly_installment <= 0 && $loan_amount > 0) {
    $jangka_waktu = intval($data['jangka_waktu'] ?? 12);
    if ($jangka_waktu <= 0) {
        $jangka_waktu = 12; // Default to 12 months
    }
    $monthly_installment = $loan_amount / $jangka_waktu;
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

// ===== DETERMINE SIGNATURE APPROVAL LEVELS BASED ON LOAN AMOUNT =====
$loan_threshold = 500000000; // 500 juta threshold

// Base roles for all loan amounts
$required_roles = ['analis', 'kasubag_analis', 'kabag_kredit', 'kadiv_bisnis'];

// Add Direktur Utama if loan >= 500 juta
if ($loan_amount >= $loan_threshold) {
    $required_roles[] = 'direktur_utama';
}

// Fetch master pejabat data for the required roles
$signature_roles = [];
$placeholders = implode(',', array_fill(0, count($required_roles), '?'));
$stmt_pejabat = $pdo->prepare("
    SELECT id_pejabat, role, nama, jabatan, tanda_tangan, stempel, status 
    FROM master_pejabat 
    WHERE role IN ($placeholders) AND status = 'aktif'
    ORDER BY FIELD(role, 'analis', 'kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama')
");
$stmt_pejabat->execute($required_roles);
$pejabat_data = $stmt_pejabat->fetchAll(PDO::FETCH_ASSOC);

// Build signature_roles from master data, with fallback to defaults if not found
$pejabat_by_role = [];
foreach ($pejabat_data as $p) {
    $pejabat_by_role[$p['role']] = $p;
}

foreach ($required_roles as $role) {
    if (isset($pejabat_by_role[$role])) {
        $p = $pejabat_by_role[$role];
        $signature_roles[] = [
            'id_pejabat' => $p['id_pejabat'],
            'role' => $p['role'],
            'nama' => $p['nama'],
            'jabatan' => $p['jabatan'],
            'tanda_tangan' => $p['tanda_tangan'],
            'stempel' => $p['stempel']
        ];
    } else {
        // Fallback to default titles if not in master_pejabat
        $defaults = [
            'analis' => ['title' => 'Analis', 'full_title' => 'Analis Kredit'],
            'kasubag_analis' => ['title' => 'Kasubag Analis', 'full_title' => 'Kepala Subbagian Analis'],
            'kabag_kredit' => ['title' => 'Kabag Kredit', 'full_title' => 'Kepala Bagian Kredit'],
            'kadiv_bisnis' => ['title' => 'Kadiv Bisnis', 'full_title' => 'Kepala Divisi Bisnis'],
            'direktur_utama' => ['title' => 'Direktur Utama', 'full_title' => 'Direktur Utama']
        ];
        $signature_roles[] = [
            'id_pejabat' => null,
            'role' => $role,
            'nama' => '',
            'jabatan' => $defaults[$role]['full_title'] ?? '',
            'tanda_tangan' => null,
            'stempel' => null
        ];
    }
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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Pengajuan Kredit - <?= htmlspecialchars($data['nama_debitur']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --paper-width: <?= $paper['width'] ?>;
            --paper-height: <?= $paper['height'] ?>;
            --paper-margin: <?= $paper['margin'] ?>;
        }
        
        body {
            font-family: 'Times New Roman', 'Calibri', serif;
            background-color: #f5f5f5;
            padding: 20px;
            line-height: 1.3;
            color: #000;
        }
        
        /* Toolbar - Paper Size Selector */
        .toolbar {
            max-width: 900px;
            margin: 0 auto 20px;
            background-color: white;
            padding: 15px 20px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .toolbar label {
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        .toolbar select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
            background-color: white;
            cursor: pointer;
        }
        
        .toolbar select:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }
        
        .toolbar button {
            background-color: #1e3a8a;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }
        
        .toolbar button:first-of-type {
            background-color: #6b7280;
            margin-right: 10px;
        }
        
        .toolbar button:hover {
            opacity: 0.9;
        }
        
        .toolbar button:first-of-type:hover {
            background-color: #4b5563;
        }
        
        .paper-info {
            font-size: 12px;
            color: #6b7280;
            margin-left: auto;
        }
        
        /* Pages Container */
        .pages-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .page {
            background-color: white;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }
        
        .page-content {
            padding: 25px;
        }
        
        .page-number {
            position: absolute;
            bottom: 15px;
            right: 20px;
            font-size: 12px;
            color: #9ca3af;
            font-style: italic;
        }
        
        /* Header */
        .print-header {
            display: none;
        }
        
        /* Sections */
        .section {
            margin-bottom: 25px;
        }
        
        /* Professional Letter Header */
        .letterhead {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 4px double #000;
        }
        
        .bank-logo {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .bank-name-letterhead {
            font-size: 20px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 3px;
        }
        
        .bank-address {
            font-size: 11px;
            color: #555;
            margin: 3px 0;
        }
        
        .doc-reference {
            margin-top: 10px;
            font-size: 11px;
            text-align: center;
        }
        
        /* Professional Table Styling */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 11px;
            margin-top: 5px;
            page-break-inside: avoid;
        }
        
        .data-table td {
            padding: 6px 8px;
            border: 1px solid #666;
            line-height: 1.4;
        }
        
        .data-table td.label {
            background-color: #f9fafb;
            font-weight: bold;
            width: 35%;
            vertical-align: top;
            color: #1f2937;
        }
        
        .data-table td.value {
            width: 65%;
            vertical-align: top;
        }
        
        /* Summary Table */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            margin-top: 5px;
            font-size: 12px;
            page-break-inside: avoid;
        }
        
        .summary-table td {
            padding: 10px 8px;
            border: 1.5px solid #1e3a8a;
            text-align: center;
            font-weight: bold;
        }
        
        .summary-table .summary-label {
            background-color: #f0f9ff;
            font-size: 11px;
            width: 50%;
            color: #1f2937;
        }
        
        .summary-table .summary-value {
            color: #1e3a8a;
            font-size: 14px;
        }
        
        /* Approval Timeline Table */
        .timeline-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            margin-top: 5px;
            font-size: 11px;
            page-break-inside: avoid;
        }
        
        .timeline-table th {
            background-color: #1e3a8a;
            color: white;
            padding: 8px 6px;
            border: 1px solid #000;
            text-align: left;
            font-weight: bold;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .timeline-table td {
            padding: 8px 6px;
            border: 1px solid #666;
            line-height: 1.3;
        }
        
        .timeline-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        /* Document Title */
        .doc-title-formal {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin: 8px 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .doc-status-formal {
            text-align: center;
            font-size: 12px;
            margin-bottom: 8px;
            font-weight: bold;
            color: #155724;
        }
        
        /* ===== EXECUTIVE SUMMARY SECTION ===== */
        .executive-summary {
            background-color: #f8fafc;
            border-left: 4px solid #1e3a8a;
            border: 1px solid #e2e8f0;
            border-left-width: 4px;
            padding: 12px 15px;
            margin: 12px 0;
            border-radius: 4px;
            page-break-inside: avoid;
        }
        
        .executive-summary-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .executive-summary-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            font-size: 11px;
        }
        
        .summary-item {
            padding: 6px 8px;
            background-color: white;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
        }
        
        .summary-item-label {
            font-weight: bold;
            color: #475569;
            font-size: 10px;
            text-transform: uppercase;
        }
        
        .summary-item-value {
            color: #1e3a8a;
            font-weight: bold;
            font-size: 13px;
            margin-top: 4px;
        }
        
        /* ===== METRICS / FINANCIAL HEALTH BOX ===== */
        .metrics-box {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            padding: 12px 15px;
            margin: 12px 0;
            border-radius: 4px;
            page-break-inside: avoid;
        }
        
        .metrics-title {
            font-size: 11px;
            font-weight: bold;
            color: #b45309;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            font-size: 10px;
        }
        
        .metric-item {
            padding: 5px 6px;
            background-color: white;
            border: 0.5px solid #d1d5db;
            border-radius: 2px;
        }
        
        .metric-label {
            font-weight: 600;
            color: #374151;
            font-size: 8px;
        }
        
        .metric-value {
            color: #1e3a8a;
            font-weight: bold;
            font-size: 11px;
            margin-top: 2px;
        }
        
        /* ===== COLLATERAL SECTION ===== */
        .collateral-section {
            margin-top: 8px;
            background-color: #f5f3ff;
            padding: 8px 10px;
            border-left: 4px solid #8b5cf6;
            border-radius: 2px;
        }
        
        .collateral-title {
            font-size: 11px;
            font-weight: bold;
            color: #6d28d9;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .collateral-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 4px;
        }
        
        .collateral-table th {
            background-color: #ede9fe;
            border: 0.5px solid #c4b5fd;
            padding: 3px 4px;
            text-align: left;
            font-weight: bold;
            color: #5b21b6;
        }
        
        .collateral-table td {
            border: 0.5px solid #e9d5ff;
            padding: 3px 4px;
            vertical-align: top;
        }
        
        /* ===== RISK LEVEL INDICATOR ===== */
        .risk-indicator {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }
        
        .risk-rendah {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .risk-sedang {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        
        .risk-tinggi {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        /* ===== COMPLIANCE SECTION ===== */
        .compliance-section {
            background-color: #ecfdf5;
            border-left: 4px solid #10b981;
            padding: 10px 12px;
            margin: 8px 0;
            border-radius: 2px;
        }
        
        .compliance-title {
            font-size: 11px;
            font-weight: bold;
            color: #047857;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        
        .compliance-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 4px;
        }
        
        .compliance-table th {
            background-color: #d1fae5;
            border: 0.5px solid #6ee7b7;
            padding: 4px 5px;
            text-align: left;
            font-weight: bold;
            color: #065f46;
        }
        
        .compliance-table td {
            border: 0.5px solid #a7f3d0;
            padding: 4px 5px;
            vertical-align: top;
        }
        
        .compliance-table tr:nth-child(even) {
            background-color: #f0fdf4;
        }
        
        .compliance-status-comply {
            display: inline-block;
            padding: 2px 6px;
            background-color: #d1fae5;
            color: #065f46;
            border-radius: 2px;
            font-weight: bold;
            font-size: 9px;
        }
        
        .compliance-status-not-comply {
            display: inline-block;
            padding: 2px 6px;
            background-color: #fee2e2;
            color: #991b1b;
            border-radius: 2px;
            font-weight: bold;
            font-size: 9px;
        }
        
        /* Section Headers */
        .section-header-formal {
            background-color: #1e3a8a;
            color: white;
            padding: 6px 8px;
            margin-top: 8px;
            margin-bottom: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        /* Mini Header */
        .mini-header {
            display: none;
        }
        
        .mini-header-title {
            display: none;
        }
        
        .mini-header-subtitle {
            display: none;
        }
        
        .signature-section-title {
            display: none;
        }
        
        .signature-grid {
            display: none;
        }
        
        .signature-box {
            display: none;
        }
        
        .signature-space {
            display: none;
        }
        
        .signature-label {
            display: none;
        }
        
        .signature-role {
            display: none;
        }
        
        .footer {
            display: none;
        }
        
        .print-button {
            display: none;
        }
        
        /* ===== SIGNATURE GRID STYLES ===== */
        .signature-grid-container {
            display: grid;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .signature-box {
            text-align: center;
            padding: 8px 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }
        
        .signature-box-inner {
            height: 60px;
            border: 1px solid #000;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            color: #999;
        }
        
        .signature-box-title {
            font-size: 9px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .signature-box-subtitle {
            font-size: 8px;
            color: #666;
        }
        
        .signature-note {
            margin-top: 12px;
            padding: 8px;
            border-radius: 2px;
            font-size: 8px;
        }
        
        .signature-note-blue {
            background-color: #f0f9ff;
            border-left: 3px solid #0284c7;
            color: #0c4a6e;
        }
        
        .signature-note-amber {
            background-color: #fef3c7;
            border-left: 3px solid #f59e0b;
            color: #92400e;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .page-content {
                padding: 25px;
            }
            
            .data-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .summary-boxes {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .signature-grid-container {
                grid-template-columns: 1fr;
            }
            
            .bank-name {
                font-size: 24px;
            }
            
            .doc-title {
                font-size: 14px;
            }
            
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .toolbar select,
            .toolbar button {
                width: 100%;
            }
            
            .paper-info {
                margin-left: 0;
                text-align: center;
            }
        }
        
        /* Print Media */
        @media print {
            body {
                background-color: white;
                padding: 0;
                margin: 0;
                font-family: 'Times New Roman', Times, serif; /* Font konsisten seluruh dokumen */
                color: #000;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            @page {
                size: A4 portrait; /* Paksa kertas A4 Portrait */
                margin: 20mm 15mm 15mm 15mm; /* Atas 20mm, Kiri/Kanan/Bawah 15mm */
            }
            
            .toolbar, .print-button {
                display: none !important;
            }
            
            .page {
                margin: 0;
                box-shadow: none;
                border-radius: 0;
                page-break-after: always;
            }
            
            .page:last-child {
                page-break-after: avoid;
            }
            
            .page-content {
                padding: 0; /* Margin sepenuhnya dikendalikan oleh @page */
            }
            
            /* Hindari pemotongan tabel dan elemen penting */
            table, tr, td, th, tbody, thead, tfoot {
                page-break-inside: avoid;
            }
            
            .section, .approval-timeline, .signature-section, .executive-summary, .collateral-section {
                page-break-inside: avoid;
            }
            
            .page-number {
                display: block;
                text-align: right;
                font-size: 10px;
                color: #555;
                margin-top: 20px;
                border-top: 1px dashed #ccc;
                padding-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.location.href='<?= htmlspecialchars($back_url) ?>'" title="Kembali ke halaman sebelumnya">← Kembali</button>
        <label for="paper-select">📄 Ukuran Kertas:</label>
        <select id="paper-select" onchange="changePaperSize(this.value)">
            <option value="A4" <?= $paper_size === 'A4' ? 'selected' : '' ?>>A4 (210mm × 297mm)</option>
            <option value="F4" <?= $paper_size === 'F4' ? 'selected' : '' ?>>F4 Foolscap (210mm × 330mm)</option>
        </select>
        <button onclick="window.print()" style="background-color:#1e3a8a;">🖨️ Cetak Dokumen</button>
        <button onclick="savePDF()" style="background-color:#059669;">📥 Simpan PDF</button>
        <span class="paper-info">Status: <strong><?= strtoupper(htmlspecialchars($data['status_pengajuan'])) ?></strong></span>
    </div>
    
    <div class="pages-container">
        <!-- PAGE 1: Data Diri & Pinjaman -->
        <div class="page">
            <div class="page-content">
                <!-- Professional Letterhead -->
                <div class="letterhead">
                    <div style="display: flex; align-items: center; gap: 12px; text-align: left;">
                        <img src="assets/img/logo_bank.png" alt="Logo Bank Wonosobo" style="height: 50px; width: auto; object-fit: contain;">
                        <div>
                            <div class="bank-name-letterhead" style="font-size: 18px; font-weight: bold; color: #1e3a8a; margin-bottom: 2px;">PT BPR BANK WONOSOBO (PERSERODA)</div>
                            <div class="bank-address" style="font-size: 11px; color: #555; margin: 0;">Jl Ahmad Yani NO.160 Wonosobo | Telp: (0286) 321293</div>
                        </div>
                    </div>
                    
                    <div class="doc-reference" style="text-align: right; margin: 0;">
                        <span style="font-size: 12px; font-weight: bold;">Nomor: NK-<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?>/<?= date('Y') ?></span><br>
                        <span style="font-size: 11px;">Tanggal: <?= date('d F Y', time()) ?></span>
                    </div>
                </div>

                <!-- Document Title -->
                <div class="doc-title-formal" style="font-size: 16px; margin: 15px 0 20px 0;">LEMBAR PERSETUJUAN PENGAJUAN KREDIT</div>
                
                <div class="doc-status-formal">✓ DISETUJUI UNTUK DICAIRKAN</div>

                <!-- ===== EXECUTIVE SUMMARY (NEW) ===== -->
                <div class="executive-summary">
                    <div class="executive-summary-title">📊 RINGKASAN EKSEKUTIF</div>
                    <div class="executive-summary-content">
                        <div class="summary-item">
                            <div class="summary-item-label">Pemohon</div>
                            <div class="summary-item-value"><?= htmlspecialchars(substr($data['nama_debitur'], 0, 25)) ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Status Kredit</div>
                            <div class="summary-item-value" style="color: #15803d;">✓ DISETUJUI</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Plafon Disetujui</div>
                            <div class="summary-item-value"><?= formatRupiah($data['jumlah_kredit']) ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Risiko</div>
                            <div class="summary-item-value">
                                <span class="risk-indicator risk-<?= strtolower($risk_level) ?>">
                                    <?= $risk_level ?>
                                </span>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Jangka Waktu</div>
                            <div class="summary-item-value"><?= (int)$data['jangka_waktu'] ?> Bulan</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Suku Bunga</div>
                            <div class="summary-item-value"><?= (float)$data['suku_bunga'] ?>%/tahun</div>
                        </div>
                    </div>
                </div>

                <!-- ===== FINANCIAL HEALTH METRICS (NEW) ===== -->
                <div class="metrics-box">
                    <div class="metrics-title">💰 ANALISA KESEHATAN KEUANGAN</div>
                    <div class="metrics-grid">
                        <div class="metric-item">
                            <div class="metric-label">Penghasilan Bulanan</div>
                            <div class="metric-value"><?= formatRupiah($monthly_income) ?></div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label">Angsuran Bulanan</div>
                            <div class="metric-value"><?= formatRupiah($monthly_installment) ?></div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label">Debt-to-Income Ratio</div>
                            <div class="metric-value" style="color: <?= $debt_income_ratio <= 35 ? '#15803d' : ($debt_income_ratio <= 50 ? '#b45309' : '#991b1b') ?>;">
                                <?= number_format($debt_income_ratio, 1) ?>%
                            </div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label">Sisa Kapasitas</div>
                            <div class="metric-value" style="color: <?= $remaining_capacity > 0 ? '#15803d' : '#991b1b' ?>;">
                                <?= formatRupiah(max(0, $remaining_capacity)) ?>
                            </div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label">Total Jaminan</div>
                            <div class="metric-value"><?= formatRupiah($total_collateral) ?></div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label">LTV Ratio</div>
                            <div class="metric-value" style="color: <?= $ltv_ratio <= 60 ? '#15803d' : ($ltv_ratio <= 80 ? '#b45309' : '#991b1b') ?>;">
                                <?= number_format($ltv_ratio, 1) ?>%
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== INCOME BREAKDOWN (OMZET + PENDAPATAN LAIN) ===== -->
                <table class="summary-table" style="margin-top: 1rem;">
                    <tr>
                        <td class="summary-label">Omzet Usaha Per Bulan</td>
                        <td class="summary-value"><?= formatRupiah($data['omset_per_bulan'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td class="summary-label">Pendapatan Lain-lain Per Bulan</td>
                        <td class="summary-value"><?= formatRupiah($data['pendapatan_lain'] ?? 0) ?></td>
                    </tr>
                    <tr style="background:#e0f2fe; font-weight:bold;">
                        <td class="summary-label">Total Penghasilan Bulanan</td>
                        <td class="summary-value"><?= formatRupiah($monthly_income) ?></td>
                    </tr>
                </table>

                <!-- Section 1: DATA DIRI PEMOHON -->
                <div class="section-header-formal">I. DATA DIRI PEMOHON</div>
                
                <?php
                $usia = '-';
                if (!empty($data['tanggal_lahir'])) {
                    try {
                        $dob = new DateTime($data['tanggal_lahir']);
                        $now = new DateTime();
                        $usia = $now->diff($dob)->y . ' Tahun';
                    } catch (Exception $e) {
                        $usia = '-';
                    }
                }
                ?>
                <table class="data-table" style="table-layout: fixed; width: 100%;">
                    <tr>
                        <td class="label" style="width: 20%;">Nama Debitur</td>
                        <td class="value" style="width: 30%;"><strong><?= htmlspecialchars($data['nama_debitur'] ?? '-') ?></strong></td>
                        <td class="label" style="width: 20%;">Jabatan</td>
                        <td class="value" style="width: 30%;"><?= htmlspecialchars($data['jabatan'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Nomor CIF</td>
                        <td class="value"><?= htmlspecialchars($data['id_nasabah'] ?? $data['cif'] ?? '-') ?></td>
                        <td class="label">Usia</td>
                        <td class="value"><?= $usia ?></td>
                    </tr>
                    <tr>
                        <td class="label">Nomor KTP</td>
                        <td class="value"><?= htmlspecialchars($data['nik'] ?? '-') ?></td>
                        <td class="label">Masa Kerja</td>
                        <td class="value"><?= htmlspecialchars($data['masa_kerja'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Alamat</td>
                        <td class="value"><?= htmlspecialchars($data['alamat_ktp'] ?? '-') ?></td>
                        <td class="label">Nomor SK</td>
                        <td class="value"><?= htmlspecialchars($data['nomor_sk'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Pekerjaan</td>
                        <td class="value"><?= htmlspecialchars($data['pekerjaan'] ?? '-') ?></td>
                        <td class="label">Status Kepegawaian</td>
                        <td class="value"><?= htmlspecialchars($data['status_kepegawaian'] ?? '-') ?></td>
                    </tr>
                </table>

                <!-- Section 2: DATA PINJAMAN -->
                <div class="section-header-formal">II. INFORMASI KREDIT & STRUKTUR PEMBIAYAAN</div>
                
                <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 15px;">
                    <thead>
                        <tr>
                            <th colspan="2" style="background-color: #f0f9ff; padding: 8px; border: 1px solid #9ca3af; text-align: left; color: #1e3a8a; font-weight: bold; font-size: 12px;">Rincian Pengajuan Kredit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label" style="width: 35%; border: 1px solid #9ca3af; padding: 6px 8px; background: #f9fafb;">Produk Kredit</td>
                            <td class="value" style="width: 65%; border: 1px solid #9ca3af; padding: 6px 8px;"><?= htmlspecialchars($data['jenis_kredit'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="label" style="border: 1px solid #9ca3af; padding: 6px 8px; background: #f9fafb;">Tujuan Kredit</td>
                            <td class="value" style="border: 1px solid #9ca3af; padding: 6px 8px;"><?= htmlspecialchars($data['tujuan_kredit'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="label" style="border: 1px solid #9ca3af; padding: 6px 8px; background: #f9fafb;">Plafon (Jumlah Kredit)</td>
                            <td class="value" style="border: 1px solid #9ca3af; padding: 6px 8px; font-weight: bold; color: #1e3a8a; font-size: 13px;"><?= formatRupiah($data['jumlah_kredit']) ?></td>
                        </tr>
                        <tr>
                            <td class="label" style="border: 1px solid #9ca3af; padding: 6px 8px; background: #f9fafb;">Jangka Waktu</td>
                            <td class="value" style="border: 1px solid #9ca3af; padding: 6px 8px;"><?= (int)$data['jangka_waktu'] ?> Bulan</td>
                        </tr>
                        <tr>
                            <td class="label" style="border: 1px solid #9ca3af; padding: 6px 8px; background: #f9fafb;">Suku Bunga</td>
                            <td class="value" style="border: 1px solid #9ca3af; padding: 6px 8px;"><?= (float)$data['suku_bunga'] ?>% per tahun</td>
                        </tr>
                        <tr>
                            <td class="label" style="border: 1px solid #9ca3af; padding: 6px 8px; background: #f9fafb;">Sistem Bunga</td>
                            <td class="value" style="border: 1px solid #9ca3af; padding: 6px 8px;"><?= htmlspecialchars($data['sistem_bunga'] ?? 'Anuitas / Efektif') ?></td>
                        </tr>
                        <tr>
                            <td class="label" style="border: 1px solid #9ca3af; padding: 6px 8px; background: #f9fafb;">Angsuran per Bulan</td>
                            <td class="value" style="border: 1px solid #9ca3af; padding: 6px 8px; font-weight: bold; color: #047857;"><?= formatRupiah($monthly_installment) ?></td>
                        </tr>
                        <tr>
                            <td class="label" style="border: 1px solid #9ca3af; padding: 6px 8px; background: #f9fafb;">Grace Period</td>
                            <td class="value" style="border: 1px solid #9ca3af; padding: 6px 8px;"><?= (int)($data['grace_period'] ?? 0) ?> Bulan</td>
                        </tr>
                        <tr>
                            <td class="label" style="border: 1px solid #9ca3af; padding: 6px 8px; background: #f9fafb;">Agunan / Jaminan</td>
                            <td class="value" style="border: 1px solid #9ca3af; padding: 6px 8px;">
                                <?php
                                $total_agunan = count($jaminan_tanah) + count($jaminan_kendaraan) + count($jaminan_emas);
                                if ($total_agunan > 0) {
                                    echo "<strong>Tersedia</strong> (" . $total_agunan . " Jaminan) &mdash; Nilai Taksasi: " . formatRupiah($total_collateral);
                                } else {
                                    echo "Tanpa Agunan / Tidak Tersedia";
                                }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php if ($neraca_data): ?>
                <!-- Section 2.5: NERACA (Posisi Keuangan) -->
                <div class="section-header-formal">II.A NERACA (Posisi Keuangan - Sebelum & Sesudah Kredit)</div>

                <!-- NERACA SEBELUM KREDIT -->
                <table class="data-table" style="margin-bottom: 1.5rem;">
                    <tr style="background-color: #f0fdf4;">
                        <td colspan="2" style="font-weight: bold; color: #16a34a; padding: 8px; text-align: center;">📋 NERACA SEBELUM KREDIT</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding: 8px; font-weight: bold; color: #059669;">AKTIVA</td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px;">Kas & Bank</td>
                        <td class="value"><?= formatRupiah((floatval($neraca_data['aktiva_kas'] ?? 0) + floatval($neraca_data['aktiva_tabungan'] ?? 0))) ?></td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px;">Tanah & Bangunan</td>
                        <td class="value"><?= formatRupiah($neraca_data['aktiva_tanah'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px;">Kendaraan</td>
                        <td class="value"><?= formatRupiah($neraca_data['aktiva_kendaraan'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px;">Stok & Lainnya</td>
                        <td class="value"><?= formatRupiah((floatval($neraca_data['aktiva_stok'] ?? 0) + floatval($neraca_data['aktiva_lainnya'] ?? 0))) ?></td>
                    </tr>
                    <tr style="border-top: 2px solid #000; font-weight: bold; color: #065F46;">
                        <td class="label" style="padding-left: 20px;">TOTAL AKTIVA</td>
                        <td class="value"><?= formatRupiah($neraca_data['total_aktiva'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding: 8px; font-weight: bold; color: #dc2626;">PASIVA</td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px;">Pinjaman Bank</td>
                        <td class="value"><?= formatRupiah($neraca_data['pasiva_hutang_bank'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px;">Hutang/Kewajiban Lain</td>
                        <td class="value"><?= formatRupiah($neraca_data['pasiva_hutang_lain'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px; font-weight: bold;">Modal Sendiri</td>
                        <td class="value" style="font-weight: bold; color: #4F46E5;"><?= formatRupiah($neraca_data['pasiva_modal'] ?? 0) ?></td>
                    </tr>
                    <tr style="border-top: 2px solid #000; font-weight: bold; color: #991B1B;">
                        <td class="label" style="padding-left: 20px;">TOTAL PASIVA</td>
                        <td class="value"><?= formatRupiah($neraca_data['total_pasiva'] ?? 0) ?></td>
                    </tr>
                </table>

                <!-- NERACA SESUDAH KREDIT (Proyeksi) -->
                <table class="data-table" style="margin-bottom: 1.5rem;">
                    <tr style="background-color: #fef3c7;">
                        <td colspan="2" style="font-weight: bold; color: #d97706; padding: 8px; text-align: center;">📝 NERACA SESUDAH KREDIT (Proyeksi)</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding: 8px; font-weight: bold; color: #059669;">AKTIVA</td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px;">Kas & Bank</td>
                        <td class="value"><?= formatRupiah((floatval($neraca_data['aktiva_kas_sesudah'] ?? 0) + floatval($neraca_data['aktiva_tabungan_sesudah'] ?? 0))) ?></td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px;">Tanah & Bangunan</td>
                        <td class="value"><?= formatRupiah($neraca_data['aktiva_tanah_sesudah'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px;">Kendaraan</td>
                        <td class="value"><?= formatRupiah($neraca_data['aktiva_kendaraan_sesudah'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px;">Stok & Lainnya</td>
                        <td class="value"><?= formatRupiah((floatval($neraca_data['aktiva_stok_sesudah'] ?? 0) + floatval($neraca_data['aktiva_lainnya_sesudah'] ?? 0))) ?></td>
                    </tr>
                    <tr style="border-top: 2px solid #000; font-weight: bold; color: #065F46;">
                        <td class="label" style="padding-left: 20px;">TOTAL AKTIVA</td>
                        <td class="value"><?= formatRupiah($neraca_data['total_aktiva_sesudah'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding: 8px; font-weight: bold; color: #dc2626;">PASIVA</td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px;">Pinjaman Bank</td>
                        <td class="value"><?= formatRupiah($neraca_data['pasiva_hutang_bank_sesudah'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px;">Hutang/Kewajiban Lain</td>
                        <td class="value"><?= formatRupiah($neraca_data['pasiva_hutang_lain_sesudah'] ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td class="label" style="padding-left: 20px; font-weight: bold;">Modal Sendiri</td>
                        <td class="value" style="font-weight: bold; color: #4F46E5;"><?= formatRupiah($neraca_data['pasiva_modal_sesudah'] ?? 0) ?></td>
                    </tr>
                    <tr style="border-top: 2px solid #000; font-weight: bold; color: #991B1B;">
                        <td class="label" style="padding-left: 20px;">TOTAL PASIVA</td>
                        <td class="value"><?= formatRupiah($neraca_data['total_pasiva_sesudah'] ?? 0) ?></td>
                    </tr>
                    <!-- Balance Status -->
                    <tr>
                        <td colspan="2" style="padding: 8px; text-align: center; font-size: 12px;">
                            <?php 
                                $diff = abs((floatval($neraca_data['total_aktiva_sesudah'] ?? 0) - floatval($neraca_data['total_pasiva_sesudah'] ?? 0)));
                                $is_balanced = ($diff <= 100);
                            ?>
                            <?php if ($is_balanced): ?>
                                ✅ <strong>Neraca Seimbang</strong>
                            <?php else: ?>
                                ⚠️ <strong>Neraca Tidak Seimbang</strong> - Selisih: <?= formatRupiah($diff) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php endif; ?>

                <?php if ($print_6c): ?>
                <!-- Section 3: ANALISA 6C -->
                <div class="section-header-formal">III. HASIL ANALISA 6C (KELAYAKAN KREDIT)</div>
                <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 15px;">
                    <thead>
                        <tr style="background-color: #f0f9ff; color: #1e3a8a; border-bottom: 2px solid #1e3a8a;">
                            <th style="padding: 8px; border: 1px solid #9ca3af; text-align: left; width: 35%; font-weight: bold;">Unsur Penilaian (6C)</th>
                            <th style="padding: 8px; border: 1px solid #9ca3af; text-align: center; width: 15%; font-weight: bold;">Skor Nilai</th>
                            <th style="padding: 8px; border: 1px solid #9ca3af; text-align: left; width: 50%; font-weight: bold;">Interpretasi / Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $unsur_6c = [
                            'Character' => intval($print_6c['character_score'] ?? 0),
                            'Capacity' => intval($print_6c['capacity_score'] ?? 0),
                            'Capital' => intval($print_6c['capital_score'] ?? 0),
                            'Collateral' => intval($print_6c['collateral_score'] ?? 0),
                            'Condition' => intval($print_6c['condition_score'] ?? 0),
                            'Constraint' => intval($print_6c['constraint_score'] ?? 0)
                        ];
                        
                        foreach ($unsur_6c as $nama_unsur => $skor): 
                            $keterangan = ($skor >= 4) ? 'Baik (Sangat Memenuhi Kriteria)' : (($skor == 3) ? 'Cukup (Memenuhi dengan Catatan)' : 'Kurang (Beresiko Tinggi)');
                            $warna_keterangan = ($skor >= 4) ? '#059669' : (($skor == 3) ? '#d97706' : '#dc2626');
                        ?>
                        <tr>
                            <td style="padding: 6px 8px; border: 1px solid #9ca3af; font-weight: bold; background: #f9fafb;"><?= $nama_unsur ?></td>
                            <td style="padding: 6px 8px; border: 1px solid #9ca3af; text-align: center; font-weight: bold;"><?= $skor ?> / 5</td>
                            <td style="padding: 6px 8px; border: 1px solid #9ca3af; color: <?= $warna_keterangan ?>; font-weight: bold;"><?= $keterangan ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php 
                            $skor_6c_total = floatval($print_6c['total_score'] ?? 0);
                            $status_6c = tentukan_status_kelayakan($skor_6c_total);
                        ?>
                        <tr style="background-color: #f1f5f9;">
                            <td style="padding: 8px; border: 1px solid #9ca3af; text-align: right; font-weight: bold;">TOTAL SKOR 6C :</td>
                            <td style="padding: 8px; border: 1px solid #9ca3af; text-align: center; font-weight: bold; font-size: 13px; color: #1e3a8a;"><?= $skor_6c_total ?> / 5.0</td>
                            <td style="padding: 8px; border: 1px solid #9ca3af; font-weight: bold; font-size: 12px; color: <?= $status_6c['warna'] ?>;"><?= $status_6c['label'] ?></td>
                        </tr>
                    </tfoot>
                </table>
                
                <!-- Section 3.5: HIGHLIGHT KESIMPULAN -->
                <div style="margin-top: 15px; margin-bottom: 20px; border: 2px solid #1e3a8a; border-radius: 6px; page-break-inside: avoid; overflow: hidden;">
                    <div style="background-color: #1e3a8a; color: #ffffff; padding: 8px 12px; font-weight: bold; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                        🎯 Kesimpulan Akhir & Rekomendasi
                    </div>
                    <div style="background-color: #f8fafc; padding: 12px;">
                        <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
                            <tr>
                                <td style="width: 30%; padding: 8px 6px; font-weight: bold; color: #475569; border-bottom: 1px dashed #cbd5e1;">Status Risiko</td>
                                <td style="width: 70%; padding: 8px 6px; border-bottom: 1px dashed #cbd5e1;">
                                    <span class="risk-indicator risk-<?= strtolower($risk_level) ?>" style="font-weight: bold; font-size: 12px; padding: 4px 8px; border-radius: 4px; border: 1px solid currentColor;"><?= strtoupper($risk_level) ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 6px; font-weight: bold; color: #475569; border-bottom: 1px dashed #cbd5e1;">Kelayakan Kredit</td>
                                <td style="padding: 8px 6px; border-bottom: 1px dashed #cbd5e1; font-weight: bold; font-size: 14px; color: <?= $status_6c['warna'] ?>;">
                                    <?= strtoupper($status_6c['label']) ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 6px; font-weight: bold; color: #475569; vertical-align: top;">Rekomendasi Analis</td>
                                <td style="padding: 8px 6px;">
                                    <div style="font-weight: bold; font-size: 13px; color: #1e3a8a; background-color: #e0e7ff; padding: 10px 12px; border-radius: 4px; border-left: 4px solid #4f46e5;">
                                        <?= nl2br(htmlspecialchars($status_6c['rekomendasi'])) ?>
                                    </div>
                                    <?php if (!empty($print_6c['catatan_5c'])): ?>
                                        <div style="margin-top: 8px; font-style: italic; color: #475569; font-size: 11px;">
                                            <strong>Catatan Khusus:</strong><br>
                                            <?= nl2br(htmlspecialchars($print_6c['catatan_5c'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ===== Section 4: COLLATERAL / JAMINAN ===== -->
                <?php if (!empty($jaminan_tanah) || !empty($jaminan_kendaraan) || !empty($jaminan_emas)): ?>
                <div class="collateral-section">
                    <div class="collateral-title">🔐 DETAIL JAMINAN / AGUNAN</div>
                    
                    <?php if (!empty($jaminan_tanah)): ?>
                    <div style="margin-bottom: 6px;">
                        <strong style="font-size: 9px; color: #5b21b6;">TANAH & BANGUNAN</strong>
                        <table class="collateral-table">
                            <thead>
                                <tr>
                                    <th width="20%">Nama Pemilik</th>
                                    <th width="20%">Alamat</th>
                                    <th width="15%">Nilai Pasar</th>
                                    <th width="15%">Nilai Taksasi</th>
                                    <th width="15%">Nilai Pengikatan</th>
                                    <th width="15%">Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jaminan_tanah as $jt): 
                                    $nama_pemilik = htmlspecialchars(isset($jt['atas_nama']) && $jt['atas_nama'] !== null ? $jt['atas_nama'] : '-');
                                    $alamat = htmlspecialchars(isset($jt['alamat_agunan']) && $jt['alamat_agunan'] !== null ? substr($jt['alamat_agunan'], 0, 30) : '-');
                                    $val_pasar = floatval($jt['nilai_pasar'] ?? 0);
                                    $val_taksasi = floatval($jt['nilai_taksasi'] ?? 0);
                                    $val_pengikatan = floatval($jt['nilai_likuidasi'] ?? 0);
                                    $tipe_val = isset($jt['tipe_valuasi']) && $jt['tipe_valuasi'] !== null ? $jt['tipe_valuasi'] : 'otomatis';
                                    $val_manual = floatval($jt['nilai_taksasi_manual'] ?? 0);
                                ?>
                                <tr>
                                    <td><?= $nama_pemilik ?></td>
                                    <td><?= $alamat ?></td>
                                    <td align="right"><?= $val_pasar > 0 ? formatRupiah($val_pasar) : '-' ?></td>
                                    <td align="right">
                                        <?= $val_taksasi > 0 ? formatRupiah($val_taksasi) : '-' ?>
                                        <?php if ($tipe_val === 'manual' && $val_manual > 0): ?>
                                        <br><span style="font-size:7px; color:#dc2626;">✏️ MANUAL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td align="right"><?= $val_pengikatan > 0 ? formatRupiah($val_pengikatan) : '-' ?></td>
                                    <td align="center" style="font-size:7px;">
                                        <?php if ($tipe_val === 'manual'): ?>
                                        <span style="color:#dc2626; font-weight:bold;">MANUAL</span>
                                        <?php else: ?>
                                        <span style="color:#059669;">AUTO</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($jaminan_kendaraan)): ?>
                    <div style="margin-bottom: 6px;">
                        <strong style="font-size: 9px; color: #5b21b6;">KENDARAAN BERMOTOR</strong>
                        <table class="collateral-table">
                            <thead>
                                <tr>
                                    <th width="16%">Nama Pemilik</th>
                                    <th width="16%">Merk / Tipe</th>
                                    <th width="10%">Tahun</th>
                                    <th width="12%">No. Polisi</th>
                                    <th width="10%">No STNK</th>
                                    <th width="11%">Nilai Pasar</th>
                                    <th width="11%">Nilai Taksasi</th>
                                    <th width="11%">Nilai Pengikatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jaminan_kendaraan as $jk):
                                    $nama_pemilik = htmlspecialchars(isset($jk['nama_pemilik']) && $jk['nama_pemilik'] !== null ? $jk['nama_pemilik'] : '-');
                                    $merk_tipe = htmlspecialchars((isset($jk['merk']) && $jk['merk'] !== null ? $jk['merk'] : '') . ' ' . (isset($jk['tipe']) && $jk['tipe'] !== null ? $jk['tipe'] : ''));
                                    if (trim($merk_tipe) === '') $merk_tipe = '-';
                                    $tahun = isset($jk['tahun_pembuatan']) && $jk['tahun_pembuatan'] !== null ? htmlspecialchars($jk['tahun_pembuatan']) : '-';
                                    $no_polisi = htmlspecialchars(isset($jk['no_polisi']) && $jk['no_polisi'] !== null ? $jk['no_polisi'] : '-');
                                    $no_stnk = htmlspecialchars(isset($jk['no_stnk']) && !is_null($jk['no_stnk']) && $jk['no_stnk'] !== '' ? $jk['no_stnk'] : '-');
                                    $val_pasar = floatval($jk['nilai_pasar'] ?? 0);
                                    $val_taksasi = floatval($jk['nilai_taksasi'] ?? 0);
                                    $val_pengikatan = floatval($jk['nilai_likuidasi'] ?? 0);
                                    $tipe_val = isset($jk['tipe_valuasi']) && $jk['tipe_valuasi'] !== null ? $jk['tipe_valuasi'] : 'otomatis';
                                    $val_manual = floatval($jk['nilai_taksasi_manual'] ?? 0);
                                ?>
                                <tr>
                                    <td><?= $nama_pemilik ?></td>
                                    <td><?= $merk_tipe ?></td>
                                    <td align="center"><?= $tahun ?></td>
                                    <td><?= $no_polisi ?></td>
                                    <td style="font-size:8px; color:<?= $no_stnk === '-' ? '#9ca3af' : '#0369a1' ?>;"><?= $no_stnk ?></td>
                                    <td align="right"><?= $val_pasar > 0 ? formatRupiah($val_pasar) : '-' ?></td>
                                    <td align="right">
                                        <?= $val_taksasi > 0 ? formatRupiah($val_taksasi) : '-' ?>
                                        <?php if ($tipe_val === 'manual' && $val_manual > 0): ?>
                                        <br><span style="font-size:7px; color:#dc2626;">✏️ MANUAL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td align="right"><?= $val_pengikatan > 0 ? formatRupiah($val_pengikatan) : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($jaminan_emas)): ?>
                    <div>
                        <strong style="font-size: 9px; color: #5b21b6;">EMAS</strong>
                        <table class="collateral-table">
                            <thead>
                                <tr>
                                    <th width="20%">Berat (gram)</th>
                                    <th width="20%">Harga/gram</th>
                                    <th width="20%">Nilai Pasar</th>
                                    <th width="20%">Nilai Taksasi</th>
                                    <th width="20%">Nilai Pengikatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jaminan_emas as $je): 
                                    $berat = floatval($je['berat'] ?? 0);
                                    $harga_per_gram = floatval($je['harga_per_gram'] ?? 0);
                                    $val_pasar = floatval($je['nilai_pasar'] ?? 0);
                                    // Emas tabel doesn't have nilai_taksasi, biasanya sama dengan nilai_pasar
                                    $val_taksasi = floatval($je['nilai_pasar'] ?? 0);
                                    $val_pengikatan = floatval($je['nilai_likuidasi'] ?? 0);
                                ?>
                                <tr>
                                    <td align="right"><?= $berat > 0 ? number_format($berat, 2) : '-' ?></td>
                                    <td align="right"><?= $harga_per_gram > 0 ? formatRupiah($harga_per_gram) : '-' ?></td>
                                    <td align="right"><?= $val_pasar > 0 ? formatRupiah($val_pasar) : '-' ?></td>
                                    <td align="right"><?= $val_taksasi > 0 ? formatRupiah($val_taksasi) : '-' ?></td>
                                    <td align="right"><?= $val_pengikatan > 0 ? formatRupiah($val_pengikatan) : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <div style="margin-top: 4px; padding: 4px 6px; background-color: white; border-top: 1px dashed #c4b5fd;">
                        <strong style="font-size: 9px;">Total Nilai Jaminan:</strong>
                        <span style="float: right; font-weight: bold; color: #6d28d9;"><?= formatRupiah($total_collateral) ?></span>
                    </div>
                </div>
                <?php else: ?>
                <!-- Fallback jika tidak ada agunan -->
                <div class="collateral-section">
                    <div class="collateral-title">🔐 DETAIL JAMINAN / AGUNAN</div>
                    <div style="padding: 8px; text-align: center; color: #6b7280; font-size: 9px;">
                        <p style="margin: 0;">-</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ===== Section 4B: AGUNAN FOTO (NEW) ===== -->
                <?php if (!empty($agunan_foto_all)): ?>
                <div style="page-break-inside: avoid; margin-top: 8px; padding: 6px; border: 1px solid #d1d5db; background: #f9fafb;">
                    <strong style="font-size: 9px; color: #1f2937; display: block; margin-bottom: 6px;">📸 Foto-Foto Agunan</strong>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px;">
                        <?php foreach (array_slice($agunan_foto_all, 0, 8) as $foto): // Limit to 8 photos for print 
                            $foto_nama = isset($foto['nama_file']) && !empty($foto['nama_file']) ? $foto['nama_file'] : null;
                            if (!$foto_nama) continue; // Skip jika nama_file kosong
                            
                            $file_path = __DIR__ . '/assets/uploads/' . htmlspecialchars($foto_nama);
                            if (!file_exists($file_path)) continue; // Skip jika file tidak ada
                        ?>
                        <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden; aspect-ratio: 1;">
                            <img src="<?= htmlspecialchars($file_path) ?>" 
                                 style="width: 100%; height: 100%; object-fit: cover; display: block;" 
                                 alt="Foto Agunan" />
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($agunan_foto_all) > 8): ?>
                    <small style="font-size: 8px; color: #6b7280; display: block; margin-top: 4px;">
                        ... dan <?= count($agunan_foto_all) - 8 ?> foto lainnya
                    </small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ===== Section 5: COMPLIANCE ASSESSMENT (NEW) ===== -->
                <?php if (!empty($compliance_items)): ?>
                <div class="compliance-section">
                    <div class="compliance-title">✓ HASIL ASSESMEN KEPATUHAN</div>
                    
                    <table class="compliance-table">
                        <thead>
                            <tr>
                                <th width="50%">Item Checklist</th>
                                <th width="15%">Status</th>
                                <th width="35%">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Map of checklist keys to display labels
                            $checklist_labels = [
                                'krit_jenis' => 'Kesesuaian jenis debitur',
                                'krit_wni' => 'Kewarganegaraan Debitur WNI',
                                'krit_kol' => 'Kolektibilitas calon debitur',
                                'usaha_pkpb' => 'Usaha bukan termasuk yg dihindari',
                                'dok_form' => 'Formulir permohonan kredit',
                                'dok_ktp' => 'KTP calon debitur',
                                'dok_ktp_pas' => 'KTP pasangan debitur',
                                'dok_kk' => 'Kartu Keluarga',
                                'dok_nikah' => 'Akta nikah',
                                'dok_foto' => 'Foto debitur dan pasangan',
                                'leg_nib' => 'NIB/TDP/SIUP/Ijin lainnya',
                                'leg_npwp' => 'NPWP',
                                'keu_lap' => 'Laporan keuangan/pembukuan',
                                'keu_rek' => 'Rekening koran',
                                'ag_shm' => 'Sertifikat (SHM/SHGB)',
                                'ag_sppt' => 'FC SPPT',
                                'ag_kuasa' => 'Surat Kuasa',
                                'ag_njop' => 'Ket Harga Tanah / NJOP',
                                'ag_cek' => 'Bukti Cek SHM',
                                'ag_foto' => 'Foto usaha & tempat tinggal',
                                'ag_visit' => 'Laporan Kunjungan',
                                'bmpk' => 'Kesesuaian BMPK',
                                'an_krd' => 'Kesesuaian Analisa Kredit',
                                'an_ag' => 'Kesesuaian Analisa Agunan',
                                'prod' => 'Kesesuaian Produk Kredit',
                                'dok' => 'Kelengkapan Dokumen',
                                'putus' => 'Catatan Pemutus',
                                'ikat' => 'Pengikatan Kredit',
                            ];
                            
                            foreach ($compliance_items as $key => $item):
                                $label = $checklist_labels[$key] ?? $key;
                                $status = $item['val'] ?? 'na';
                                $ket = $item['ket'] ?? '';
                                
                                // Color code based on status
                                $status_class = ($status === 'comply') ? 'compliance-status-comply' : 'compliance-status-not-comply';
                                $status_text = ($status === 'comply') ? '✓ COMPLY' : '✗ NOT COMPLY';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($label) ?></td>
                                <td align="center"><span class="<?= $status_class ?>"><?= $status_text ?></span></td>
                                <td><?= htmlspecialchars($ket) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($compliance_data && !empty($compliance_data['kesimpulan'])): ?>
                    <div style="margin-top: 6px; padding: 6px; background-color: white; border-left: 2px solid #10b981;">
                        <strong style="font-size: 9px; color: #047857;">KESIMPULAN:</strong><br>
                        <span style="font-size: 9px;"><?= nl2br(htmlspecialchars($compliance_data['kesimpulan'])) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($compliance_data && !empty($compliance_data['rekomendasi'])): ?>
                    <div style="margin-top: 6px; padding: 6px; background-color: white; border-left: 2px solid #10b981;">
                        <strong style="font-size: 9px; color: #047857;">REKOMENDASI:</strong><br>
                        <span style="font-size: 9px;"><?= nl2br(htmlspecialchars($compliance_data['rekomendasi'])) ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Hasil Kepatuhan & Catatan Hasil -->
                    <?php if ($compliance_data && !empty($compliance_data['hasil_kepatuhan'])): ?>
                    <div style="margin-top: 6px; padding: 6px; background-color: <?= $compliance_data['hasil_kepatuhan'] === 'COMPLY' ? '#ecfdf5' : '#fee2e2' ?>; border-left: 2px solid <?= $compliance_data['hasil_kepatuhan'] === 'COMPLY' ? '#10b981' : '#ef4444' ?>;">
                        <strong style="font-size: 9px; color: <?= $compliance_data['hasil_kepatuhan'] === 'COMPLY' ? '#047857' : '#991b1b' ?>;">HASIL KEPATUHAN:</strong> 
                        <span style="font-size: 9px; font-weight: 600; color: <?= $compliance_data['hasil_kepatuhan'] === 'COMPLY' ? '#047857' : '#991b1b' ?>;">
                            <?= $compliance_data['hasil_kepatuhan'] === 'COMPLY' ? '✓ COMPLY' : '✗ NOT COMPLY' ?>
                        </span>
                        <?php if (!empty($compliance_data['catatan_hasil'])): ?>
                        <br><strong style="font-size: 8px; color: #666;">Catatan:</strong>
                        <span style="font-size: 8px;"><?= nl2br(htmlspecialchars($compliance_data['catatan_hasil'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Fasilitas Kredit Existing -->
                    <?php if ($compliance_data && !empty($compliance_data['fasilitas_existing'])): ?>
                    <?php 
                    $fasilitas_existing_raw = json_decode($compliance_data['fasilitas_existing'], true) ?: [];
                    // Support both old and new format
                    $fasilitas_to_display = [];
                    if (!empty($fasilitas_existing_raw)) {
                        if (isset($fasilitas_existing_raw[0]['lembaga'])) {
                            $fasilitas_to_display = $fasilitas_existing_raw; // New format
                        } elseif (isset($fasilitas_existing_raw[0]['rek'])) {
                            // Convert old format to display
                            foreach ($fasilitas_existing_raw as $f) {
                                $fasilitas_to_display[] = [
                                    'lembaga' => $f['rek'] ?? '',
                                    'baki_debet' => $f['saldo'] ?? '0',
                                    'kolektibilitas' => $f['kol'] ?? '',
                                    'keterangan' => ''
                                ];
                            }
                        }
                    }
                    ?>
                    <?php if (!empty($fasilitas_to_display)): ?>
                    <div style="margin-top: 6px;">
                        <strong style="font-size: 9px; color: #000;">Fasilitas Kredit Existing:</strong>
                        <table style="width: 100%; font-size: 8px; border-collapse: collapse; margin-top: 2px;">
                            <thead>
                                <tr style="background-color: #f3f4f6;">
                                    <th style="border: 1px solid #ddd; padding: 3px; text-align: left;">Lembaga</th>
                                    <th style="border: 1px solid #ddd; padding: 3px; text-align: right;">Baki Debet</th>
                                    <th style="border: 1px solid #ddd; padding: 3px; text-align: center;">Kol</th>
                                    <th style="border: 1px solid #ddd; padding: 3px; text-align: left;">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fasilitas_to_display as $fas): ?>
                                <tr>
                                    <td style="border: 1px solid #ddd; padding: 3px;"><?= htmlspecialchars($fas['lembaga'] ?? '') ?></td>
                                    <td style="border: 1px solid #ddd; padding: 3px; text-align: right;"><?= number_format(intval($fas['baki_debet'] ?? 0)) ?></td>
                                    <td style="border: 1px solid #ddd; padding: 3px; text-align: center;"><?= htmlspecialchars($fas['kolektibilitas'] ?? '') ?></td>
                                    <td style="border: 1px solid #ddd; padding: 3px;"><?= htmlspecialchars($fas['keterangan'] ?? '') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="page-number">Halaman 1 dari 2</div>
        </div>

        <!-- PAGE 2: Approval Timeline & Signatures -->
        <div class="page">
            <div class="page-content">
                <!-- Professional Letterhead (Page 2) -->
                <div class="letterhead">
                    <div style="display: flex; align-items: center; gap: 12px; text-align: left;">
                        <img src="assets/img/logo_bank.png" alt="Logo Bank Wonosobo" style="height: 50px; width: auto; object-fit: contain;">
                        <div>
                            <div class="bank-name-letterhead" style="font-size: 18px; font-weight: bold; color: #1e3a8a; margin-bottom: 2px;">PT BPR BANK WONOSOBO (PERSERODA)</div>
                            <div class="bank-address" style="font-size: 11px; color: #555; margin: 0;">Jl Ahmad Yani NO.160 Wonosobo | Telp: (0286) 321293</div>
                        </div>
                    </div>
                    
                    <div class="doc-reference" style="text-align: right; margin: 0;">
                        <span style="font-size: 12px; font-weight: bold;">Nomor: NK-<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?>/<?= date('Y') ?></span><br>
                        <span style="font-size: 11px;">Tanggal: <?= date('d F Y', time()) ?></span>
                    </div>
                </div>

                <!-- Timeline Section Title -->
                <div class="section-header-formal">V. TIMELINE PROSES PERSETUJUAN</div>

                <?php
                $approval_map = [];
                if (!empty($approvals)) {
                    foreach ($approvals as $app) {
                        $approval_map[$app['level_approval']] = $app;
                    }
                }

                $status_overall = strtolower($data['status'] ?? 'pending');
                $found_blocking = false;
                ?>
                <div style="display: flex; gap: 8px; margin-bottom: 20px; overflow-x: hidden;">
                    <?php 
                    foreach ($signature_roles as $role_info): 
                        $lvl = $role_info['role'];
                        $pejabat_nama = !empty($role_info['nama']) ? $role_info['nama'] : 'Pejabat Belum Ditentukan';
                        $pejabat_jabatan = !empty($role_info['jabatan']) ? $role_info['jabatan'] : ucwords(str_replace('_', ' ', $lvl));
                        
                        $box_bg = '#f1f5f9';
                        $box_border = '#cbd5e1';
                        $status_text = 'Menunggu Persetujuan';
                        $status_color = '#64748b';
                        $tanggal_text = '-';
                        
                        if (isset($approval_map[$lvl])) {
                            // Already approved
                            $status_text = '✓ Disetujui';
                            $status_color = '#059669';
                            $box_bg = '#ecfdf5';
                            $box_border = '#6ee7b7';
                            $tanggal_text = date('d-m-Y H:i', strtotime($approval_map[$lvl]['tanggal_approval']));
                            if (!empty($approval_map[$lvl]['nama_approver'])) {
                                $pejabat_nama = $approval_map[$lvl]['nama_approver'];
                            }
                        } else {
                            if (!$found_blocking) {
                                if ($status_overall === 'ditolak') {
                                    $status_text = '✕ Ditolak';
                                    $status_color = '#dc2626';
                                    $box_bg = '#fef2f2';
                                    $box_border = '#fca5a5';
                                } elseif ($status_overall === 'revisi') {
                                    $status_text = '⚠ Perlu Perbaikan';
                                    $status_color = '#d97706';
                                    $box_bg = '#fffbeb';
                                    $box_border = '#fcd34d';
                                } else {
                                    $status_text = 'Menunggu Persetujuan';
                                    $status_color = '#2563eb';
                                    $box_bg = '#eff6ff';
                                    $box_border = '#93c5fd';
                                }
                                $found_blocking = true;
                            } else {
                                $status_text = 'Menunggu Persetujuan';
                                $status_color = '#94a3b8';
                                $box_bg = '#f8fafc';
                                $box_border = '#e2e8f0';
                            }
                        }
                    ?>
                    <div style="flex: 1; border: 1px solid <?= $box_border ?>; border-radius: 6px; background-color: <?= $box_bg ?>; padding: 10px 8px; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="font-size: 9px; font-weight: bold; color: #475569; margin-bottom: 4px; border-bottom: 1px dashed <?= $box_border ?>; padding-bottom: 4px; text-transform: uppercase;"><?= htmlspecialchars($pejabat_jabatan) ?></div>
                            <div style="font-size: 11px; font-weight: bold; color: #1e293b; margin-bottom: 8px;"><?= htmlspecialchars($pejabat_nama) ?></div>
                        </div>
                        <div style="background: rgba(255,255,255,0.6); padding: 4px; border-radius: 4px; margin-top: auto;">
                            <div style="font-size: 10px; font-weight: bold; color: <?= $status_color ?>; margin-bottom: 2px;"><?= $status_text ?></div>
                            <div style="font-size: 9px; color: #64748b;"><?= $tanggal_text !== '-' ? '📅 ' . $tanggal_text : '⏳ Belum diproses' ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Signature Section -->
                <div style="margin-top: 40px; page-break-inside: avoid;">
                    <div style="display: grid; grid-template-columns: repeat(<?= count($signature_roles) ?>, 1fr); gap: 15px;">
                        <?php foreach ($signature_roles as $index => $sig): 
                            $lvl = $sig['role'];
                            // Ambil tanggal approval dari approval_map (dibuat di section timeline)
                            $tgl_approval = isset($approval_map[$lvl]) ? date('d/m/Y', strtotime($approval_map[$lvl]['tanggal_approval'])) : '....../....../..........';
                            
                            // Gunakan nama pejabat asli atau nama approver jika ada
                            $nama_pejabat = !empty($sig['nama']) ? $sig['nama'] : '';
                            if (isset($approval_map[$lvl]) && !empty($approval_map[$lvl]['nama_approver'])) {
                                $nama_pejabat = $approval_map[$lvl]['nama_approver'];
                            }
                            if (empty($nama_pejabat)) {
                                $nama_pejabat = '...........................................';
                            } else {
                                $nama_pejabat = htmlspecialchars($nama_pejabat);
                            }
                        ?>
                        <div style="text-align: center; display: flex; flex-direction: column; justify-content: space-between;">
                            <!-- Header Pejabat -->
                            <div style="margin-bottom: 5px;">
                                <div style="font-size: 11px; font-weight: bold; color: #1e293b; text-transform: uppercase;"><?= htmlspecialchars($sig['jabatan']) ?></div>
                                <div style="font-size: 10px; color: #475569; margin-top: 4px;">Tgl: <?= $tgl_approval ?></div>
                            </div>
                            
                            <!-- Ruang Tanda Tangan -->
                            <div style="height: 100px; display: flex; align-items: center; justify-content: center; margin: 10px 0;">
                                <?php if (!empty($sig['stempel']) && file_exists('assets/uploads/' . $sig['stempel'])): ?>
                                <img src="<?= htmlspecialchars('assets/uploads/' . $sig['stempel']) ?>" 
                                     style="max-height: 85px; width: auto; max-width: 100%; object-fit: contain; mix-blend-mode: multiply;" 
                                     alt="Stempel" />
                                <?php endif; ?>
                            </div>
                            
                            <!-- Garis Bawah dan Nama Pejabat -->
                            <div>
                                <span style="font-size: 11px; font-weight: bold; color: #000; border-bottom: 1px solid #000; padding-bottom: 2px; display: inline-block; min-width: 80%;">
                                    <?= $nama_pejabat ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Footer -->
                <div style="margin-top: 20px; text-align: center; border-top: 1px solid #ccc; padding-top: 8px; font-size: 9px; color: #666;">
                    <p style="margin: 2px 0;">Dokumen ini dicetak secara otomatis oleh Sistem Informasi Pengajuan Kredit - Bank Wonosobo</p>
                    <p style="margin: 2px 0;">Tanggal & Waktu Cetak: <?= date('d F Y \p\u\k\u\l H:i:s', time()) ?></p>
                    <p style="margin: 2px 0; font-style: italic; font-weight: bold;">⚠ Dokumen Resmi - Harap Disimpan dengan Aman</p>
                </div>
            </div>
            <div class="page-number">Halaman 2 dari 2</div>
        </div>
    </div>

    <script>
        function changePaperSize(size) {
            const url = new URL(window.location);
            url.searchParams.set('paper_size', size);
            window.location = url.toString();
        }

        // PDF: trigger browser print dialog which supports 'Save as PDF' natively
        function savePDF() {
            // Set document title so the PDF filename is descriptive
            const origTitle = document.title;
            document.title = 'Pengajuan_<?= preg_replace('/[^A-Za-z0-9_]/', '_', $data['nama_debitur']) ?>_<?= date('Ymd') ?>';
            window.print();
            document.title = origTitle;
        }
    </script>
</body>
</html>
