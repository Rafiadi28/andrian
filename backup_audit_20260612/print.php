<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

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

// ===== CALCULATE FINANCIAL METRICS =====
$monthly_income = floatval($data['omset_per_bulan'] ?? 0);
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
$risk_level = 'MEDIUM'; // Default
if ($debt_income_ratio > 50 || $ltv_ratio > 80 || $remaining_capacity < 0) {
    $risk_level = 'HIGH';
} elseif ($debt_income_ratio <= 30 && $ltv_ratio <= 60 && $remaining_capacity > floatval($data['angsuran_diajukan'] ?? 0)) {
    $risk_level = 'LOW';
}

// ===== DETERMINE SIGNATURE APPROVAL LEVELS BASED ON LOAN AMOUNT =====
$loan_threshold = 500000000; // 500 juta threshold
$signature_roles = [
    [
        'role' => 'analis',
        'title' => 'Analis',
        'full_title' => 'Analis Kredit'
    ],
    [
        'role' => 'kasubag_analis',
        'title' => 'Kasubag Analis',
        'full_title' => 'Kepala Subbagian Analis'
    ],
    [
        'role' => 'kabag_kredit',
        'title' => 'Kabag Kredit',
        'full_title' => 'Kepala Bagian Kredit'
    ],
    [
        'role' => 'kadiv_bisnis',
        'title' => 'Kadiv Bisnis',
        'full_title' => 'Kepala Divisi Bisnis'
    ]
];

// Add Direktur Utama only if loan >= 500 juta
if ($loan_amount >= $loan_threshold) {
    $signature_roles[] = [
        'role' => 'direktur_utama',
        'title' => 'Direktur Utama',
        'full_title' => 'Direktur Utama'
    ];
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
            text-align: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 3px double #000;
        }
        
        .bank-logo {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .bank-name-letterhead {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 3px;
        }
        
        .bank-address {
            font-size: 10px;
            color: #555;
            margin: 3px 0;
        }
        
        .doc-reference {
            margin-top: 8px;
            font-size: 10px;
            text-align: center;
        }
        
        /* Professional Table Styling */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .data-table td {
            padding: 5px 5px;
            border: 1px solid #000;
            line-height: 1.25;
        }
        
        .data-table td.label {
            background-color: #f5f5f5;
            font-weight: bold;
            width: 35%;
            vertical-align: top;
        }
        
        .data-table td.value {
            width: 65%;
            vertical-align: top;
        }
        
        /* Summary Table */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            margin-top: 5px;
            font-size: 12px;
        }
        
        .summary-table td {
            padding: 7px 5px;
            border: 2px solid #1e3a8a;
            text-align: center;
            font-weight: bold;
        }
        
        .summary-table .summary-label {
            background-color: #e8f4f8;
            font-size: 11px;
            width: 50%;
        }
        
        .summary-table .summary-value {
            color: #1e3a8a;
            font-size: 13px;
        }
        
        /* Approval Timeline Table */
        .timeline-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            margin-top: 5px;
            font-size: 11px;
        }
        
        .timeline-table th {
            background-color: #1e3a8a;
            color: white;
            padding: 5px 4px;
            border: 1px solid #000;
            text-align: left;
            font-weight: bold;
        }
        
        .timeline-table td {
            padding: 5px 4px;
            border: 1px solid #000;
            line-height: 1.2;
        }
        
        .timeline-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 12px;
        }
        
        .summary-table td {
            padding: 12px 10px;
            border: 2px solid #1e3a8a;
            text-align: center;
            font-weight: bold;
        }
        
        .summary-table .summary-label {
            background-color: #e8f4f8;
            font-size: 11px;
            width: 50%;
        }
        
        .summary-table .summary-value {
            color: #1e3a8a;
            font-size: 14px;
        }
        
        /* Approval Timeline Table */
        .timeline-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        
        .timeline-table th {
            background-color: #1e3a8a;
            color: white;
            padding: 10px 8px;
            border: 1px solid #000;
            text-align: left;
            font-weight: bold;
        }
        
        .timeline-table td {
            padding: 10px 8px;
            border: 1px solid #000;
        }
        
        .timeline-table tr:nth-child(even) {
            background-color: #f9f9f9;
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
            background-color: #f0f9ff;
            border-left: 4px solid #1e3a8a;
            padding: 10px 12px;
            margin: 8px 0;
            border-radius: 2px;
        }
        
        .executive-summary-title {
            font-size: 11px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        
        .executive-summary-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            font-size: 11px;
        }
        
        .summary-item {
            padding: 4px 6px;
            background-color: white;
            border: 0.5px solid #d1d5db;
            border-radius: 2px;
        }
        
        .summary-item-label {
            font-weight: bold;
            color: #374151;
            font-size: 9px;
        }
        
        .summary-item-value {
            color: #1e3a8a;
            font-weight: bold;
            font-size: 12px;
            margin-top: 2px;
        }
        
        /* ===== METRICS / FINANCIAL HEALTH BOX ===== */
        .metrics-box {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 10px 12px;
            margin: 8px 0;
            border-radius: 2px;
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
        
        .risk-low {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .risk-medium {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        
        .risk-high {
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
            }
            
            @page {
                size: var(--paper-width) var(--paper-height);
                margin: var(--paper-margin);
            }
            
            .toolbar {
                display: none !important;
            }
            
            .print-button {
                display: none !important;
            }
            
            .page {
                margin-bottom: 0;
                box-shadow: none;
                page-break-after: always;
                break-after: page;
                border-radius: 0;
            }
            
            .page:last-child {
                page-break-after: avoid;
                break-after: avoid;
            }
            
            .page-content {
                padding: 35px;
            }
            
            .page-number {
                display: block;
            }
            
            .section {
                page-break-inside: avoid;
            }
            
            .approval-timeline {
                page-break-inside: avoid;
            }
            
            .signature-section {
                page-break-inside: avoid;
            }
            
            .signature-grid-container {
                display: grid;
                gap: 8px;
                page-break-inside: avoid;
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
                    <div class="bank-logo">🏦</div>
                    <div class="bank-name-letterhead">PT BPR BANK WONOSOBO (PERSERODA)</div>
                    <div class="bank-address">Jl Ahmad Yani NO.160 Wonosobo</div>
                    <div class="bank-address">(0286) 321293</div>
                    
                    <div class="doc-reference" style="margin-top: 12px;">
                        <strong style="font-size: 11px;">Surat Persetujuan Pengajuan Kredit</strong><br>
                        <span style="font-size: 10px;">Nomor: NK-<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?>/<?= date('Y') ?></span><br>
                        <span style="font-size: 10px;">Tanggal: <?= date('d F Y', time()) ?></span>
                    </div>
                </div>

                <!-- Document Title -->
                <div class="doc-title-formal">RINGKASAN PERSETUJUAN PENGAJUAN KREDIT</div>
                
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
                            <div class="summary-item-label">Tenor</div>
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

                <!-- Section 1: DATA DIRI PEMOHON -->
                <div class="section-header-formal">I. DATA DIRI PEMOHON</div>
                
                <table class="data-table">
                    <tr>
                        <td class="label">Nama Lengkap</td>
                        <td class="value"><strong><?= htmlspecialchars($data['nama_debitur']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="label">Nomor Induk Kependudukan (NIK)</td>
                        <td class="value"><?= htmlspecialchars($data['nik']) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Tempat / Tanggal Lahir</td>
                        <td class="value">
                            <?= htmlspecialchars($data['tempat_lahir']) ?> / 
                            <?= !empty($data['tanggal_lahir']) ? date('d F Y', strtotime($data['tanggal_lahir'])) : '-' ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Status Perkawinan</td>
                        <td class="value"><?= htmlspecialchars($data['status_perkawinan'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Pekerjaan / Profesi</td>
                        <td class="value"><?= htmlspecialchars($data['pekerjaan']) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Alamat Sesuai KTP</td>
                        <td class="value"><?= htmlspecialchars($data['alamat_ktp'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Alamat Domisili</td>
                        <td class="value"><?= htmlspecialchars($data['alamat_domisili'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Nomor Telepon / HP</td>
                        <td class="value"><?= htmlspecialchars($data['no_hp'] ?? '-') ?></td>
                    </tr>
                </table>

                <!-- Section 2: DATA PINJAMAN -->
                <div class="section-header-formal">II. DATA PINJAMAN / KREDIT</div>
                
                <!-- Summary Boxes as Table -->
                <table class="summary-table">
                    <tr>
                        <td class="summary-label">Plafon Kredit (Jumlah)</td>
                        <td class="summary-value"><?= formatRupiah($data['jumlah_kredit']) ?></td>
                    </tr>
                    <tr>
                        <td class="summary-label">Angsuran Bulanan</td>
                        <td class="summary-value"><?= formatRupiah($monthly_installment) ?></td>
                    </tr>
                </table>

                <!-- Detailed Loan Information -->
                <table class="data-table">
                    <tr>
                        <td class="label">Jangka Waktu Kredit</td>
                        <td class="value"><?= (int)$data['jangka_waktu'] ?> Bulan</td>
                    </tr>
                    <tr>
                        <td class="label">Suku Bunga (Interest Rate)</td>
                        <td class="value"><?= (float)$data['suku_bunga'] ?>% per tahun</td>
                    </tr>
                    <tr>
                        <td class="label">Jenis Kredit</td>
                        <td class="value"><?= htmlspecialchars($data['jenis_kredit'] ?? 'KMK - Kredit Modal Kerja') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Tujuan Penggunaan Kredit</td>
                        <td class="value"><?= htmlspecialchars($data['tujuan_kredit'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Masa Tenggang (Grace Period)</td>
                        <td class="value"><?= (int)($data['grace_period'] ?? 0) ?> Bulan</td>
                    </tr>
                    <tr>
                        <td class="label">Status Kelayakan</td>
                        <td class="value">
                            <?php 
                            $status = $data['status_kelayakan'] ?? 'LAYAK';
                            $status_display = strtoupper($status);
                            ?>
                            <strong style="color: <?= ($status === 'LAYAK') ? '#155724' : '#d32f2f' ?>;">
                                ✓ <?= $status_display ?>
                            </strong>
                        </td>
                    </tr>
                </table>

                <?php if ($print_6c): ?>
                <!-- Section 3: ANALISA 6C -->
                <div class="section-header-formal">III. ANALISA 6C (KELAYAKAN KREDIT)</div>
                <table class="data-table" style="font-size:11px;">
                    <tr>
                        <td class="label" style="width:20%;">Character</td>
                        <td class="value" style="width:30%;"><?= intval($print_6c['character_score']??0) ?> / 5
                            <?php $cs=intval($print_6c['character_score']??0); echo $cs>=4?'(Baik)':($cs>=3?'(Cukup)':'(Kurang)'); ?></td>
                        <td class="label" style="width:20%;">Capacity</td>
                        <td class="value" style="width:30%;"><?= intval($print_6c['capacity_score']??0) ?> / 5
                            <?php $cs=intval($print_6c['capacity_score']??0); echo $cs>=4?'(Baik)':($cs>=3?'(Cukup)':'(Kurang)'); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Capital</td>
                        <td class="value"><?= intval($print_6c['capital_score']??0) ?> / 5
                            <?php $cs=intval($print_6c['capital_score']??0); echo $cs>=4?'(Baik)':($cs>=3?'(Cukup)':'(Kurang)'); ?></td>
                        <td class="label">Condition</td>
                        <td class="value"><?= intval($print_6c['condition_score']??0) ?> / 5
                            <?php $cs=intval($print_6c['condition_score']??0); echo $cs>=4?'(Baik)':($cs>=3?'(Cukup)':'(Kurang)'); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Collateral</td>
                        <td class="value"><?= intval($print_6c['collateral_score']??0) ?> / 5
                            <?php $cs=intval($print_6c['collateral_score']??0); echo $cs>=4?'(Baik)':($cs>=3?'(Cukup)':'(Kurang)'); ?></td>
                        <td class="label">Constraint</td>
                        <td class="value"><?= intval($print_6c['constraint_score']??0) ?> / 5
                            <?php $cs=intval($print_6c['constraint_score']??0); echo $cs>=4?'(Baik)':($cs>=3?'(Cukup)':'(Kurang)'); ?></td>
                    </tr>
                    <tr>
                        <td class="label" style="background:#1e3a8a; color:#fff; font-weight:bold;">TOTAL SKOR</td>
                        <td class="value" style="font-weight:bold; font-size:13px;"><?= floatval($print_6c['total_score']??0) ?> / 5.0</td>
                        <td class="label" style="background:#1e3a8a; color:#fff; font-weight:bold;">REKOMENDASI</td>
                        <td class="value" style="font-weight:bold; font-size:13px; color:<?= strtoupper($print_6c['rekomendasi']??'')=='LAYAK'?'#155724':'#dc2626' ?>;">
                            <?= htmlspecialchars(strtoupper($print_6c['rekomendasi'] ?? '-')) ?>
                        </td>
                    </tr>
                    <?php if (!empty($print_6c['catatan_5c'])): ?>
                    <tr>
                        <td class="label">Catatan</td>
                        <td class="value" colspan="3" style="font-style:italic;"><?= htmlspecialchars($print_6c['catatan_5c']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                <?php endif; ?>

                <!-- ===== Section 4: COLLATERAL / JAMINAN (NEW) ===== -->
                <?php if (!empty($jaminan_tanah) || !empty($jaminan_kendaraan) || !empty($jaminan_emas)): ?>
                <div class="collateral-section">
                    <div class="collateral-title">🔐 DETAIL JAMINAN / AGUNAN</div>
                    
                    <?php if (!empty($jaminan_tanah)): ?>
                    <div style="margin-bottom: 6px;">
                        <strong style="font-size: 9px; color: #5b21b6;">TANAH & BANGUNAN</strong>
                        <table class="collateral-table">
                            <thead>
                                <tr>
                                    <th width="30%">Alamat</th>
                                    <th width="20%">Kategori</th>
                                    <th width="25%">Nilai Taksasi</th>
                                    <th width="25%">Nilai Pasar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jaminan_tanah as $jt): 
                                    $val_taksasi = floatval($jt['nilai_taksasi'] ?? $jt['nilai_pasar'] ?? 0);
                                    $val_pasar = floatval($jt['nilai_pasar'] ?? 0);
                                ?>
                                <tr>
                                    <td><?= substr(htmlspecialchars($jt['alamat_agunan'] ?? '-'), 0, 30) ?></td>
                                    <td><?= htmlspecialchars($jt['kategori_agunan'] ?? '-') ?></td>
                                    <td align="right"><?= formatRupiah($val_taksasi) ?></td>
                                    <td align="right"><?= formatRupiah($val_pasar) ?></td>
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
                                    <th width="25%">Merk / Tipe</th>
                                    <th width="15%">Tahun</th>
                                    <th width="20%">No. Polisi</th>
                                    <th width="20%">Nilai Taksasi</th>
                                    <th width="20%">Nilai Pasar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jaminan_kendaraan as $jk):
                                    $val_taksasi = floatval($jk['nilai_taksasi'] ?? $jk['nilai_pasar'] ?? 0);
                                    $val_pasar = floatval($jk['nilai_pasar'] ?? 0);
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($jk['merk'] ?? '') ?> <?= htmlspecialchars($jk['tipe'] ?? '') ?></td>
                                    <td align="center"><?= htmlspecialchars($jk['tahun_pembuatan'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($jk['no_polisi'] ?? '-') ?></td>
                                    <td align="right"><?= formatRupiah($val_taksasi) ?></td>
                                    <td align="right"><?= formatRupiah($val_pasar) ?></td>
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
                                    <th width="25%">Berat (gram)</th>
                                    <th width="25%">Harga/gram</th>
                                    <th width="25%">Nilai Pasar</th>
                                    <th width="25%">Nilai Likuidasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jaminan_emas as $je): ?>
                                <tr>
                                    <td align="right"><?= number_format(floatval($je['berat'] ?? 0), 2) ?></td>
                                    <td align="right"><?= formatRupiah(floatval($je['harga_per_gram'] ?? 0)) ?></td>
                                    <td align="right"><?= formatRupiah(floatval($je['nilai_pasar'] ?? 0)) ?></td>
                                    <td align="right"><?= formatRupiah(floatval($je['nilai_likuidasi'] ?? 0)) ?></td>
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
                </div>
                <?php endif; ?>
            </div>
            <div class="page-number">Halaman 1 dari 2</div>
        </div>

        <!-- PAGE 2: Approval Timeline & Signatures -->
        <div class="page">
            <div class="page-content">
                <!-- Professional Letterhead (Simplified) -->
                <div style="text-align: center; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #000;">
                    <strong style="font-size: 13px;">BANK WONOSOBO</strong><br>
                    <span style="font-size: 9px;">Nomor Surat: NK-<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?>/<?= date('Y') ?></span>
                </div>

                <!-- Timeline Section Title -->
                <div class="section-header-formal">III. TIMELINE PROSES PERSETUJUAN</div>

                <!-- Approval Timeline Table -->
                <table class="timeline-table">
                    <thead>
                        <tr>
                            <th width="15%">No.</th>
                            <th width="25%">Level Approval</th>
                            <th width="30%">Nama Pejabat</th>
                            <th width="20%">Tanggal Persetujuan</th>
                            <th width="10%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($approvals)): ?>
                            <?php 
                            $approval_labels = [
                                'analis' => 'Analis Kredit',
                                'kasubag_analis' => 'Kepala Subbagian Analis',
                                'kabag_kredit' => 'Kepala Bagian Kredit',
                                'kadiv_bisnis' => 'Kepala Divisi Bisnis',
                                'direktur_utama' => 'Direktur Utama'
                            ];
                            $no = 1; 
                            foreach ($approvals as $approval): 
                                $level = $approval['level_approval'];
                                $level_label = $approval_labels[$level] ?? ucwords(str_replace('_', ' ', $level));
                            ?>
                            <tr>
                                <td align="center"><?= $no++ ?></td>
                                <td><?= htmlspecialchars($level_label) ?></td>
                                <td><?= htmlspecialchars($approval['nama_approver'] ?? 'Auto-Skip') ?></td>
                                <td><?= date('d-m-Y H:i', strtotime($approval['tanggal_approval'])) ?></td>
                                <td align="center"><strong style="color: #155724;">✓ SETUJU</strong></td>
                            </tr>
                            <?php if (!empty($approval['catatan'])): ?>
                            <tr>
                                <td colspan="5" style="background-color: #fafafa; font-style: italic; font-size: 10px;">
                                    <strong>Catatan:</strong> <?= htmlspecialchars($approval['catatan']) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" align="center" style="color: #666;">Tidak ada data persetujuan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Signature Section -->
                <div style="margin-top: 25px;">
                    <div style="text-align: center; font-size: 11px; font-weight: bold; margin-bottom: 15px; text-transform: uppercase;">
                        TANDA TANGAN & STEMPEL PEJABAT BANK
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(<?= count($signature_roles) ?>, 1fr); gap: 8px;">
                        <?php foreach ($signature_roles as $index => $sig): ?>
                        <div style="text-align: center; padding: 8px 6px; border: 1px solid #ddd; border-radius: 4px;">
                            <div style="height: 60px; border: 1px solid #000; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; font-size: 8px; color: #999;">
                                Stempel & Tanda Tangan
                            </div>
                            <div style="font-size: 9px; font-weight: bold; margin-bottom: 2px;">
                                <?= htmlspecialchars($sig['title']) ?>
                            </div>
                            <div style="font-size: 8px; color: #666;">
                                <?= htmlspecialchars($sig['full_title']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($loan_amount >= $loan_threshold): ?>
                    <div style="margin-top: 12px; padding: 8px; background-color: #f0f9ff; border-left: 3px solid #0284c7; border-radius: 2px; font-size: 8px; color: #0c4a6e;">
                        <strong>📌 Catatan:</strong> Kredit nominal ≥ Rp 500.000.000 memerlukan persetujuan hingga Direktur Utama
                    </div>
                    <?php else: ?>
                    <div style="margin-top: 12px; padding: 8px; background-color: #fef3c7; border-left: 3px solid #f59e0b; border-radius: 2px; font-size: 8px; color: #92400e;">
                        <strong>📌 Catatan:</strong> Kredit nominal < Rp 500.000.000 memerlukan persetujuan hingga Kadiv Bisnis
                    </div>
                    <?php endif; ?>
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
