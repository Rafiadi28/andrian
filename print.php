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
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            font-size: 24px;
            margin-bottom: 20px;
        }
        h2 {
            font-size: 20px;
            margin-bottom: 15px;
        }
        h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        p {
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: bold;
        }
        .font-italic {
            font-style: italic;
        }
        .bg-light {
            background-color: #f9f9f9;
        }
        .border {
            border: 1px solid #ddd;
        }
        .signature {
            margin-top: 40px;
            padding-top: 10px;
            border-top: 1px solid #000;
        }
        .note {
            font-size: 12px;
            color: #777;
            margin-top: 5px;
        }
        @page {
            size: <?= $paper['width'] ?> <?= $paper['height'] ?>;
            margin: <?= $paper['margin'] ?>;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Formulir Pengajuan Kredit</h1>
        
        <h2>Data Pengajuan</h2>
        <table>
            <tr>
                <th>ID Pengajuan</th>
                <td><?= htmlspecialchars($data['id_pengajuan']) ?></td>
            </tr>
            <tr>
                <th>Nama Pemohon</th>
                <td><?= htmlspecialchars($data['nama_pemohon']) ?></td>
            </tr>
            <tr>
                <th>Jenis Kredit</th>
                <td><?= htmlspecialchars($data['jenis_kredit']) ?></td>
            </tr>
            <tr>
                <th>Jumlah Kredit</th>
                <td class="text-right"><?= number_format($data['jumlah_kredit'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <th>Jangka Waktu</th>
                <td><?= htmlspecialchars($data['jangka_waktu']) ?> bulan</td>
            </tr>
            <tr>
                <th>Suku Bunga</th>
                <td><?= htmlspecialchars($data['suku_bunga']) ?>%</td>
            </tr>
            <tr>
                <th>Agunan</th>
                <td>
                    <?php
                    $jaminan_list = [];
                    foreach ($jaminan_tanah as $jt) {
                        $jaminan_list[] = 'Tanah/Bangunan: ' . htmlspecialchars($jt['alamat']) . ' (Nilai: ' . number_format($jt['nilai_taksasi'], 0, ',', '.') . ')';
                    }
                    foreach ($jaminan_kendaraan as $jk) {
                        $jaminan_list[] = 'Kendaraan: ' . htmlspecialchars($jk['jenis']) . ' (Nilai: ' . number_format($jk['nilai_taksasi'], 0, ',', '.') . ')';
                    }
                    foreach ($jaminan_emas as $je) {
                        $jaminan_list[] = 'Emas: ' . htmlspecialchars($je['berat'] . ' gr') . ' (Nilai: ' . number_format($je['nilai_pasar'], 0, ',', '.') . ')';
                    }
                    echo implode('<br>', $jaminan_list);
                    ?>
                </td>
            </tr>
            <tr>
                <th>Tujuan Penggunaan</th>
                <td><?= htmlspecialchars($data['tujuan_penggunaan']) ?></td>
            </tr>
            <tr>
                <th>Status Pengajuan</th>
                <td>
                    <span style="color: <?= $warna_status ?>; font-weight: bold;"><?= $teks_status ?></span>
                </td>
            </tr>
        </table>
        
        <h2>Data Diri Pemohon</h2>
        <table>
            <tr>
                <th>Nama Lengkap</th>
                <td><?= htmlspecialchars($data['nama_lengkap']) ?></td>
            </tr>
            <tr>
                <th>Tempat, Tanggal Lahir</th>
                <td><?= htmlspecialchars($data['tempat_lahir'] . ', ' . $data['tanggal_lahir']) ?></td>
            </tr>
            <tr>
                <th>Jenis Kelamin</th>
                <td><?= htmlspecialchars($data['jenis_kelamin']) ?></td>
            </tr>
            <tr>
                <th>Alamat</th>
                <td><?= htmlspecialchars($data['alamat']) ?></td>
            </tr>
            <tr>
                <th>No. Telepon</th>
                <td><?= htmlspecialchars($data['no_telepon']) ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?= htmlspecialchars($data['email']) ?></td>
            </tr>
        </table>
        
        <h2>Analisa 6C</h2>
        <table>
            <tr>
                <th>Karakter</th>
                <td><?= htmlspecialchars($print_6c['karakter'] ?? '-') ?></td>
            </tr>
            <tr>
                <th>Kapasitas</th>
                <td><?= htmlspecialchars($print_6c['kapasitas'] ?? '-') ?></td>
            </tr>
            <tr>
                <th>Modal</th>
                <td><?= htmlspecialchars($print_6c['modal'] ?? '-') ?></td>
            </tr>
            <tr>
                <th>Agunan</th>
                <td><?= htmlspecialchars($print_6c['agunan'] ?? '-') ?></td>
            </tr>
            <tr>
                <th>Syariah</th>
                <td><?= htmlspecialchars($print_6c['syariah'] ?? '-') ?></td>
            </tr>
            <tr>
                <th>Risiko</th>
                <td><?= htmlspecialchars($print_6c['risiko'] ?? '-') ?></td>
            </tr>
        </table>
        
        <h2>Assessment Kepatuhan</h2>
        <table>
            <tr>
                <th>Kepatuhan</th>
                <td><?= htmlspecialchars($compliance_data['kepatuhan'] ?? '-') ?></td>
            </tr>
            <tr>
                <th>Catatan</th>
                <td><?= nl2br(htmlspecialchars($compliance_data['catatan'] ?? '-')) ?></td>
            </tr>
        </table>
        
        <h2>Riwayat Persetujuan</h2>
        <table>
            <tr>
                <th>Role</th>
                <th>Nama Approver</th>
                <th>Tanggal</th>
                <th>Status</th>
            </tr>
            <?php foreach ($approvals as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['role_approver']) ?></td>
                <td><?= htmlspecialchars($a['nama_approver']) ?></td>
                <td><?= htmlspecialchars($a['tanggal']) ?></td>
                <td><?= htmlspecialchars($a['keputusan']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <div class="signature">
            <h2>Tanda Tangan Pejabat</h2>
            <?php
            $last_signature_role = null;
            foreach ($signature_roles as $sig):
                $role = $sig['role'] ?? '';
                $nama_pejabat = $sig['nama'] ?? '';
                $acting_for = $sig['acting_for'] ?? '';
                $tanda_tangan = $sig['tanda_tangan'] ?? '';
                $stempel = $sig['stempel'] ?? '';
                
                // Skip if no signature found
                if (empty($tanda_tangan) && empty($stempel)) {
                    continue;
                }
                
                // Display role label only for the first signature
                if (is_null($last_signature_role)):
            ?>
            <div style="margin-bottom: 20px;">
                <strong>Mengetahui,</strong><br>
                <?= $roleDisplayTitles[$role] ?? htmlspecialchars($role) ?>
            </div>
            <?php
                endif;
                
                $last_signature_role = $role;
            ?>
            <!-- Garis Bawah dan Nama Pejabat -->
            <div>
                <span style="font-size: 11px; font-weight: bold; color: #000; border-bottom: 1px solid #000; padding-bottom: 2px; display: inline-block; min-width: 80%;">
                    <?= $nama_pejabat ?>
                </span>
                <?php if (!empty($sig['acting_for'])): ?>
                <div style="font-size: 9px; color: #475569; margin-top: 4px; font-style: italic;">
                    <?= htmlspecialchars($sig['acting_for']) ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 5px;">
                <?php if (!empty($tanda_tangan)): ?>
                <img src="<?= htmlspecialchars($tanda_tangan) ?>" alt="Tanda Tangan" style="max-width: 200px; height: auto;">
                <?php endif; ?>
                <?php if (!empty($stempel)): ?>
                <img src="<?= htmlspecialchars($stempel) ?>" alt="Stempel" style="max-width: 100px; height: auto;">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <?php if ($directorReplacementInSignature): ?>
            <div style="font-size: 10px; color: #dc3545; margin-top: 10px;">
                ⚠ Direktur Utama bertindak sebagai pengganti pejabat yang cuti, tetapi tetap ditampilkan di urutan tanda tangan paling akhir.
            </div>
            <?php endif; ?>
        </div>
        
        <div class="note">
            Catatan: Dokumen ini dihasilkan secara otomatis dan tidak memerlukan tanda tangan basah.
        </div>
    </div>
</body>
</html>


