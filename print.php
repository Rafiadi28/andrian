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
    if ($score >= 5) return 'Sangat Baik (Sangat Memenuhi Kriteria)';
    if ($score == 4) return 'Baik (Sangat Memenuhi Kriteria)';
    if ($score == 3) return 'Cukup (Memenuhi Kriteria)';
    if ($score == 2) return 'Kurang (Tidak Memenuhi Kriteria)';
    if ($score == 1) return 'Sangat Kurang (Tidak Memenuhi Kriteria)';
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
$approval_map_no_kepatuhan = array_filter($approval_map, static function ($entry, $role) {
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
    'kasubag_analis' => 'Kepala Subbagian Analis',
    'kepatuhan' => 'Kepatuhan',
    'kabag_kredit' => 'Kepala Bagian Kredit',
    'kadiv_bisnis' => 'Kepala Divisi Bisnis',
    'direktur_utama' => 'Direktur Utama'
];

// ATURAN BISNIS: Direktur Utama HANYA masuk TTD jika plafon >= 500 juta.
// Cuti pejabat TIDAK menambahkan Direktur Utama ke TTD.
// Signature sequence dibangun murni dari approval records yang ada.

$timeline_roles = $approval_chain_roles;
$signature_roles = buildPrintSignatureSequence($approval_map_no_kepatuhan, $pejabat_by_role, $roleDisplayTitles);

$ttd_replacement_note = '';

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
    ['label' => 'Character', 'key' => 'character_score', 'note_key' => 'catatan_character'],
    ['label' => 'Capacity', 'key' => 'capacity_score', 'note_key' => 'catatan_capacity'],
    ['label' => 'Capital', 'key' => 'capital_score', 'note_key' => 'catatan_capital'],
    ['label' => 'Collateral', 'key' => 'collateral_score', 'note_key' => 'catatan_collateral'],
    ['label' => 'Condition', 'key' => 'condition_score', 'note_key' => 'catatan_condition'],
    ['label' => 'Constraint', 'key' => 'constraint_score', 'note_key' => 'catatan_constraint_risk'],
];

$analisa_6c_total = 0;
foreach ($scoreRows as $row) {
    $analisa_6c_total += (int)($print_6c[$row['key']] ?? 0);
}
$analisa_6c_total = count($scoreRows) > 0 ? round($analisa_6c_total / count($scoreRows), 2) : 0;
$rekomendasi_6c = $normalizePrintText($print_6c['rekomendasi'] ?? '-', 'Belum ada rekomendasi');

// ===== PREPARE PRINT DATA =====
$nama_pemohon = htmlspecialchars($data['nama_lengkap'] ?? $data['nama_debitur'] ?? $data['nama_pemohon'] ?? '-');
$cetak_timestamp = date('d F Y') . ' pukul ' . date('H:i:s');
$nomor_dokumen = 'NK.' . str_pad((string)($data['id_pengajuan'] ?? '0'), 5, '0', STR_PAD_LEFT) . '/' . date('Y');
$pengajuan_label = 'Pengajuan_' . strtoupper(str_replace(' ', '_', $data['nama_lengkap'] ?? $data['nama_debitur'] ?? 'UNKNOWN')) . '_' . date('Ymd');

$jenis_pek = $data['jenis_pekerjaan'] ?? 'umum';
$is_pegawai = in_array($jenis_pek, ['pppk', 'perangkat_desa'], true);

// Hitung usia dan sisa masa kerja dari input asli agar sesuai dengan form
$usia_tercetak = $data['usia'] ?? null;
if ($usia_tercetak === null || trim((string)$usia_tercetak) === '' || (string)$usia_tercetak === '-') {
    $tanggal_lahir = $data['tanggal_lahir'] ?? null;
    if (!empty($tanggal_lahir) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_lahir)) {
        try {
            $usia_tercetak = (new DateTime('today'))->diff(new DateTime($tanggal_lahir))->y;
        } catch (Exception $e) {
            $usia_tercetak = '-';
        }
    } else {
        $usia_tercetak = '-';
    }
}

$sisa_masa_kerja_tercetak = '-';
if ($is_pegawai) {
    $tgl_akhir_kontrak = trim((string)($data['departemen_bagian'] ?? $data['pppk_tgl_akhir'] ?? $data['desk_tgl_akhir'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_akhir_kontrak)) {
        try {
            $today = new DateTime('today');
            $akhir = new DateTime($tgl_akhir_kontrak);
            if ($akhir > $today) {
                $diff = $today->diff($akhir);
                $parts = [];
                if ($diff->y > 0) $parts[] = $diff->y . ' Tahun';
                if ($diff->m > 0) $parts[] = $diff->m . ' Bulan';
                if (empty($parts)) $parts[] = '< 1 Bulan';
                $sisa_masa_kerja_tercetak = implode(' ', $parts);
            } else {
                $sisa_masa_kerja_tercetak = 'Sudah Berakhir';
            }
        } catch (Exception $e) {
            $sisa_masa_kerja_tercetak = $data['lama_usaha'] ?? '-';
        }
    } else {
        $sisa_masa_kerja_tercetak = $data['lama_usaha'] ?? '-';
    }
} else {
    $sisa_masa_kerja_tercetak = $data['lama_usaha'] ?? '-';
}

// Agunan description
$agunan_desc = 'Tanpa Agunan / Tidak Tersedia';
$agunan_items = [];
if (!empty($jaminan_tanah)) {
    foreach ($jaminan_tanah as $jt) {
        $agunan_items[] = ($jt['jenis_sertifikat'] ?? 'Tanah/Bangunan') . ' ' . ($jt['no_sertifikat'] ?? '');
    }
}
if (!empty($jaminan_kendaraan)) {
    foreach ($jaminan_kendaraan as $jk) {
        $agunan_items[] = ($jk['jenis_kendaraan'] ?? 'Kendaraan') . ' ' . ($jk['merk'] ?? '') . ' ' . ($jk['tahun'] ?? '');
    }
}
if (!empty($jaminan_emas)) {
    foreach ($jaminan_emas as $je) {
        $agunan_items[] = 'Emas ' . ($je['berat'] ?? '') . ' gram';
    }
}
if (!empty($agunan_items)) {
    $agunan_desc = implode(', ', $agunan_items);
} elseif (!empty($data['jaminan']) && trim((string)$data['jaminan']) !== '') {
    $agunan_desc = $data['jaminan'];
}

// Sistem bunga display
$sistem_bunga_display = ucfirst((string)($data['sistem_bunga'] ?? 'Anuitas'));
if (stripos($sistem_bunga_display, 'anuitas') !== false) {
    $sistem_bunga_display = 'Anuitas / Efektif';
}

// Kelayakan kredit
$kelayakan_kredit = ($analisa_6c_total >= 3) ? 'LAYAK' : 'TIDAK LAYAK';
if ($semua_disetujui) {
    $kelayakan_kredit = 'LAYAK';
}

// Build timeline data including kepatuhan
$timeline_full_roles = ['analis', 'kasubag_analis', 'kepatuhan', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama'];
$timeline_data = [];
foreach ($timeline_full_roles as $tRole) {
    $entry = null;
    if ($tRole === 'kepatuhan') {
        $entry = $approval_latest['kepatuhan'] ?? null;
        $pejabat = $pejabat_by_role['kepatuhan'] ?? null;
        $nama = $pejabat['nama'] ?? ($entry['nama_approver'] ?? 'Petugas Kepatuhan');
    } else {
        $entry = $approval_approved[$tRole] ?? ($approval_latest[$tRole] ?? null);
        $pejabat = $pejabat_by_role[$tRole] ?? null;
        $nama = $pejabat['nama'] ?? ($entry['nama_approver'] ?? '-');
    }

    $isApproved = false;
    if ($tRole === 'kepatuhan') {
        $isApproved = (isset($approval_approved['kepatuhan'])) ||
            ($compliance_data && strtolower((string)($compliance_data['status'] ?? '')) === 'comply');
        if (!$isApproved && $entry && strtolower((string)($entry['keputusan'] ?? '')) === 'setuju') {
            $isApproved = true;
        }
    } else {
        $isApproved = isset($approval_approved[$tRole]);
    }

    $tanggal = '';
    if ($entry && !empty($entry['tanggal_approval'])) {
        $tanggal = date('d-m-Y H:i', strtotime($entry['tanggal_approval']));
    }

    $timeline_data[] = [
        'role' => $tRole,
        'title' => $roleDisplayTitles[$tRole] ?? ucwords(str_replace('_', ' ', $tRole)),
        'nama' => $nama,
        'approved' => $isApproved,
        'tanggal' => $tanggal,
    ];
}

// Signature roles for keputusan page
$ttd_data = [];
foreach ($signature_roles as $sig) {
    $app = $sig['approval_entry'] ?? null;
    $tgl_ttd = '';

    if ($app && !empty($app['tanggal_approval'])) {
        $tgl_ttd = date('d/m/Y', strtotime($app['tanggal_approval']));
    } else {
        $tgl_ttd = date('d/m/Y');
    }

    $title = $sig['display_title'] ?? $sig['jabatan'] ?? '';
    if (empty($title)) {
        $title = str_replace('_', ' ', $sig['role'] ?? '');
    }

    $ttd_data[] = [
        'title' => strtoupper((string)$title),
        'nama' => strtoupper((string)($sig['nama'] ?? '-')),
        'tanggal' => $tgl_ttd,
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Pengajuan Kredit - <?= $nama_pemohon ?></title>
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

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            background: var(--bw-bg);
            color: var(--bw-text);
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }

        .print-page {
            max-width: 780px;
            margin: 20px auto;
            background: #fff;
            padding: 20px 28px 16px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.08);
            position: relative;
        }

        /* ===== TOP META ===== */
        .topmeta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: var(--bw-muted);
            margin-bottom: 6px;
            font-weight: 600;
        }

        /* ===== BANK HEADER — HORIZONTAL ===== */
        .bank-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 6px 0;
        }

        .bank-logo {
            flex-shrink: 0;
            width: 64px;
            height: 64px;
        }

        .bank-logo img,
        .bank-logo svg {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .bank-text {
            flex: 1;
            text-align: center;
        }

        .bank-text .bank-name {
            font-size: 20px;
            font-weight: 900;
            color: var(--bw-blue);
            letter-spacing: 0.02em;
            text-transform: uppercase;
            line-height: 1.15;
        }

        .bank-text .bank-addr {
            font-size: 10px;
            color: var(--bw-muted);
            margin-top: 3px;
            line-height: 1.5;
        }

        .rule {
            border: none;
            border-top: 2.5px solid #1e2a39;
            margin: 8px 0;
        }

        /* ===== DOCUMENT INFO ===== */
        .doc-info {
            display: flex;
            justify-content: space-between;
            font-size: 11.5px;
            margin-bottom: 2px;
            line-height: 1.6;
        }

        .doc-info .left strong {
            font-weight: 700;
        }

        .doc-title {
            text-align: center;
            font-size: 20px;
            font-weight: 800;
            color: var(--bw-blue);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 14px 0 12px;
        }

        /* ===== STATUS BANNER ===== */
        .status-banner {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: 14px;
            border-radius: 3px;
        }

        .status-banner.waiting {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #e0c777;
        }

        .status-banner.approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #a3d9b1;
        }

        /* ===== SECTION BOX ===== */
        .section-box {
            margin-bottom: 14px;
            border: 1px solid var(--bw-line);
            background: #fff;
        }

        .section-head {
            background: linear-gradient(180deg, #0d3d7a, #123c7e);
            color: #fff;
            padding: 8px 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 11.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .section-head.light {
            background: linear-gradient(180deg, #1e5ead, #19529a);
        }

        .section-head.warning {
            background: linear-gradient(180deg, #c0392b, #a93226);
        }

        .section-head.gold {
            background: linear-gradient(180deg, #8B7D3C, #6B5D2C);
        }

        /* ===== SUMMARY PANEL (Ringkasan Eksekutif) ===== */
        .summary-panel {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            padding: 10px;
            background: #f4f8ff;
            border: 1px solid var(--bw-line);
            margin-bottom: 14px;
        }

        .mini-card {
            background: #fff;
            border: 1px solid var(--bw-line);
            padding: 8px 10px;
        }

        .mini-label {
            font-size: 9px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--bw-muted);
            font-weight: 700;
            margin-bottom: 5px;
        }

        .mini-value {
            font-size: 13px;
            font-weight: 700;
            color: var(--bw-text);
            word-break: break-word;
        }

        .color-green { color: var(--bw-success) !important; }
        .color-amber { color: var(--bw-warning) !important; }
        .color-red { color: var(--bw-danger) !important; }
        .color-blue { color: var(--bw-blue) !important; }

        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 3px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
        }

        .badge-danger { background: #fce4ec; color: #c0392b; border: 1px solid #f5c6cb; }
        .badge-success { background: #d4edda; color: #155724; border: 1px solid #a3d9b1; }
        .badge-warning { background: #fff3cd; color: #856404; border: 1px solid #e0c777; }

        /* ===== FINANCIAL BOX ===== */
        .financial-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            border: 1px solid #d9d2a8;
            background: #f8fbe7;
        }

        .kpi {
            padding: 8px 10px;
            border-right: 1px solid #d9d2a8;
        }
        .kpi:last-child { border-right: none; }

        .kpi label {
            display: block;
            font-size: 9px;
            color: var(--bw-muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .kpi strong {
            font-size: 12px;
            color: var(--bw-text);
            font-weight: 700;
        }

        .budget-table {
            width: 100%;
            border-collapse: collapse;
        }

        .budget-table td {
            padding: 7px 10px;
            border-top: 1px solid var(--bw-line);
            font-size: 12px;
        }

        .budget-table td:first-child {
            width: 55%;
            font-weight: 600;
            color: var(--bw-text);
            background: #dfeefe;
            text-align: right;
            padding-right: 20px;
        }

        .budget-table td:last-child {
            text-align: right;
            font-weight: 700;
            color: var(--bw-blue);
            background: #ebf4ff;
        }

        /* ===== DETAIL TABLE ===== */
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        .detail-table th,
        .detail-table td {
            border: 1px solid var(--bw-line);
            padding: 6px 10px;
            font-size: 11.5px;
            vertical-align: top;
            word-break: break-word;
        }

        .detail-table th {
            color: var(--bw-text);
            background: #f6f9ff;
            font-weight: 700;
            text-align: left;
            width: 22%;
        }

        .detail-table td {
            background: #fff;
        }

        /* 2-column table for Rincian */
        .rincian-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        .rincian-table th,
        .rincian-table td {
            border: 1px solid var(--bw-line);
            padding: 7px 10px;
            font-size: 11.5px;
            vertical-align: top;
        }

        .rincian-table th {
            background: #f6f9ff;
            font-weight: 700;
            text-align: left;
            width: 35%;
            color: var(--bw-text);
        }

        .rincian-table td {
            background: #fff;
        }

        .rincian-table .val-highlight {
            color: var(--bw-danger);
            font-weight: 700;
        }

        /* ===== 6C TABLE ===== */
        .score-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        .score-table th,
        .score-table td {
            border: 1px solid var(--bw-line);
            padding: 7px 10px;
            font-size: 11.5px;
            vertical-align: middle;
        }

        .score-table thead th {
            background: #f6f9ff;
            font-weight: 700;
            color: var(--bw-text);
        }

        .score-table thead th:first-child { width: 30%; text-align: left; }
        .score-table thead th:nth-child(2) { width: 15%; text-align: center; }
        .score-table thead th:nth-child(3) { text-align: left; }

        .score-table tbody td:first-child { font-weight: 700; }
        .score-table tbody td:nth-child(2) { text-align: center; font-weight: 700; }
        .score-table tbody td:nth-child(3) { color: var(--bw-success); font-weight: 600; }

        .score-table tfoot th { text-align: right; font-weight: 700; background: #f6f9ff; }
        .score-table tfoot td { font-weight: 700; }
        .score-table tfoot td:first-of-type { text-align: center; color: var(--bw-blue); }

        /* ===== KESIMPULAN TABLE ===== */
        .kesimpulan-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        .kesimpulan-table th,
        .kesimpulan-table td {
            border: 1px solid var(--bw-line);
            padding: 8px 12px;
            font-size: 11.5px;
            vertical-align: top;
        }

        .kesimpulan-table th {
            background: #f6f9ff;
            font-weight: 600;
            text-align: left;
            width: 28%;
            color: var(--bw-text);
        }

        .rekomendasi-box {
            background: #d4edda;
            color: #155724;
            padding: 6px 14px;
            font-weight: 800;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 8px;
            border-left: 4px solid #28a745;
        }

        .catatan-box {
            margin-top: 6px;
            font-style: italic;
            font-size: 11px;
            color: #555;
        }

        .note-analis-box {
            margin-top: 6px;
            padding: 5px 10px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            font-size: 11px;
        }

        .note-analis-box strong {
            display: block;
            font-size: 10px;
            text-decoration: underline;
            margin-bottom: 2px;
        }

        /* ===== APPROVAL TIMELINE GRID ===== */
        .approval-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            padding: 10px;
            background: #f5f9ff;
        }

        .approval-card {
            background: #fff;
            border: 1px solid var(--bw-line);
            padding: 8px 10px;
            display: flex;
            flex-direction: column;
            min-height: 110px;
            border-left: 4px solid #ccc;
        }

        .approval-card.is-approved {
            border-left-color: var(--bw-success);
            background: #f0faf4;
        }

        .approval-card.is-kepatuhan-approved {
            border-left-color: #17a2b8;
            background: #f0f9fb;
        }

        .approval-card.is-pending {
            border-left-color: var(--bw-warning);
            background: #fffcf0;
        }

        .approval-role {
            font-size: 10px;
            font-weight: 800;
            color: var(--bw-blue);
            text-transform: uppercase;
            line-height: 1.3;
            border-bottom: 1px dashed #ddd;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }

        .approval-name {
            font-size: 11px;
            font-weight: 700;
            color: var(--bw-text);
            margin-bottom: auto;
            padding-bottom: 6px;
        }

        .approval-status {
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 4px;
        }

        .approval-status.ok { color: var(--bw-success); }
        .approval-status.pending { color: var(--bw-warning); }

        .approval-date {
            font-size: 9px;
            color: var(--bw-muted);
            margin-top: 3px;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        /* ===== SIGNATURE BLOCK ===== */
        .signature-block {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            margin-top: 30px;
            text-align: center;
        }

        .sig-col {
            padding: 0 10px;
        }

        .sig-title {
            font-size: 11px;
            font-weight: 800;
            color: var(--bw-text);
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .sig-date {
            font-size: 10px;
            color: var(--bw-muted);
            margin-bottom: 50px;
        }

        .sig-name {
            font-size: 11px;
            font-weight: 800;
            color: var(--bw-blue);
            text-decoration: underline;
            text-transform: uppercase;
            padding-top: 4px;
        }

        /* ===== FOOTER ===== */
        .footer-note {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid var(--bw-line);
            font-size: 10px;
            color: var(--bw-muted);
            text-align: center;
            line-height: 1.7;
        }

        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-warning {
            color: #c0392b;
            font-weight: 700;
            margin-top: 2px;
        }

        /* ===== PAGE BREAK ===== */
        .page-break {
            page-break-before: always;
        }

        /* ===== PRINT STYLES ===== */
        @page {
            size: <?= $paper['width'] ?> <?= $paper['height'] ?>;
            margin: <?= $paper['margin'] ?>;
        }

        @media print {
            html, body {
                background: #fff;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            .print-page {
                box-shadow: none;
                max-width: none;
                width: 100%;
                margin: 0;
                padding: 0;
                page-break-inside: auto;
            }

            .page-break {
                page-break-before: always;
                margin-top: 0;
            }

            .no-print {
                display: none !important;
            }

            .section-box {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<!-- ============================================================ -->
<!-- PAGE 1: LEMBAR ANALISA KREDIT                                -->
<!-- ============================================================ -->
<div class="print-page" id="page1">
    <div class="topmeta">
        <div><?= date('d/m/y, H:i') ?></div>
        <div><?= $pengajuan_label ?></div>
    </div>

    <!-- Bank Header -->
    <div class="bank-header">
        <div class="bank-logo">
            <img src="logobawon.png" alt="Logo Bank Wonosobo" style="display:block; width:100%; height:100%; object-fit:contain;">
        </div>
        <div class="bank-text">
            <div class="bank-name">PT BPR BANK WONOSOBO (PERSERODA)</div>
            <div class="bank-addr">
                Kantor Pusat: Jl. Ahmad Yani No. 160 Wonosobo 56311<br>
                Telp: (0286) 321293 &nbsp;|&nbsp; Email: bprbankwonosobo@yahoo.co.id
            </div>
        </div>
    </div>

    <hr class="rule">

    <!-- Document Info -->
    <div class="doc-info">
        <div class="left">
            <strong>Nomor:</strong> <?= $nomor_dokumen ?><br>
            <strong>Lampiran:</strong> -
        </div>
        <div class="right">Wonosobo, <?= date('d F Y') ?></div>
    </div>

    <div class="doc-title">Lembar Analisa Kredit</div>

    <!-- Status Banner -->
    <div class="status-banner <?= $semua_disetujui ? 'approved' : 'waiting' ?>">
        <?= $semua_disetujui ? '⚑ DISETUJUI' : '⏳ MENUNGGU PERSETUJUAN' ?>
    </div>

    <!-- Ringkasan Eksekutif -->
    <div class="section-box" style="margin-bottom:14px;">
        <div class="section-head">📊 Ringkasan Eksekutif</div>
        <div class="summary-panel" style="margin-bottom:0; border:none;">
            <div class="mini-card">
                <div class="mini-label">Pemohon</div>
                <div class="mini-value"><?= $nama_pemohon ?></div>
            </div>
            <div class="mini-card">
                <div class="mini-label">Status Kredit</div>
                <div class="mini-value <?= $semua_disetujui ? 'color-green' : 'color-amber' ?>"><?= $teks_status ?></div>
            </div>
            <div class="mini-card">
                <div class="mini-label">Plafon Disetujui</div>
                <div class="mini-value">Rp <?= number_format((float)($data['jumlah_kredit'] ?? 0), 0, ',', '.') ?></div>
            </div>
            <div class="mini-card">
                <div class="mini-label">Risiko</div>
                <div class="mini-value">
                    <span class="badge <?= $risk_level === 'TINGGI' ? 'badge-danger' : ($risk_level === 'RENDAH' ? 'badge-success' : 'badge-warning') ?>"><?= $risk_level ?></span>
                </div>
            </div>
            <div class="mini-card">
                <div class="mini-label">Jangka Waktu</div>
                <div class="mini-value color-blue"><?= htmlspecialchars((string)($data['jangka_waktu'] ?? '-')) ?> Bulan</div>
            </div>
            <div class="mini-card">
                <div class="mini-label">Suku Bunga</div>
                <div class="mini-value color-blue"><?= htmlspecialchars((string)($data['suku_bunga'] ?? '0')) ?>%/tahun</div>
            </div>
        </div>
    </div>

    <!-- Analisa Kesehatan Keuangan -->
    <div class="section-box">
        <div class="section-head gold">📊 Analisa Kesehatan Keuangan</div>
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
                <td>Gaji Pokok / THP Bulanan</td>
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

    <!-- I. Data Diri Pemohon -->
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
                <td><?= htmlspecialchars((string) $usia_tercetak) ?><?= is_numeric($usia_tercetak) ? ' Tahun' : '' ?></td>
            </tr>
            <tr>
                <th>Nomor KTP</th>
                <td><?= htmlspecialchars($data['nik'] ?? '-') ?></td>
                <th>Sisa Masa Kerja</th>
                <td><?= htmlspecialchars((string) $sisa_masa_kerja_tercetak) ?></td>
            </tr>
            <tr>
                <th>Alamat</th>
                <td colspan="3"><?= htmlspecialchars($data['alamat_domisili'] ?? $data['alamat_ktp'] ?? $data['alamat'] ?? '-') ?></td>
            </tr>
            <tr>
                <th>Jaminan</th>
                <td colspan="3"><?= htmlspecialchars($agunan_desc) ?></td>
            </tr>
            <tr>
                <th>Pekerjaan</th>
                <td><?= htmlspecialchars($data['pekerjaan'] ?? $data['nama_usaha'] ?? '-') ?></td>
                <th>Pinjaman Ke</th>
                <td><?= htmlspecialchars((string)($data['pinjaman_ke'] ?? '1')) ?></td>
            </tr>
        </table>
    </div>

    <!-- II. Informasi Kredit Header (continues on page 2) -->
    <div class="section-box" style="margin-bottom:0;">
        <div class="section-head light">II. Informasi Kredit & Struktur Pembiayaan</div>
    </div>

    <div class="footer-note">
        <div class="footer-row">
            <span>Dokumen ini dicetak secara otomatis oleh Sistem Informasi Pengajuan Kredit – Bank Wonosobo</span>
            <span>Halaman 1 dari 3</span>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- PAGE 2: RINCIAN KREDIT + ANALISA 6C + KESIMPULAN             -->
<!-- ============================================================ -->
<div class="print-page page-break" id="page2">
    <div class="topmeta">
        <div><?= date('d/m/y, H:i') ?></div>
        <div><?= $pengajuan_label ?></div>
    </div>

    <!-- Rincian Pengajuan Kredit (2-column) -->
    <div class="section-box">
        <div class="section-head light" style="font-size: 12px;">
            <span style="text-decoration: underline;">Rincian Pengajuan Kredit</span>
        </div>
        <table class="rincian-table">
            <tr>
                <th>Produk Kredit</th>
                <td><?= htmlspecialchars($data['jenis_kredit'] ?? 'KK') ?></td>
            </tr>
            <tr>
                <th>Tujuan Kredit</th>
                <td><?= htmlspecialchars($data['tujuan_penggunaan'] ?? $data['tujuan_kredit'] ?? '-') ?></td>
            </tr>
            <tr>
                <th>Plafon (Jumlah Kredit)</th>
                <td class="val-highlight">Rp <?= number_format((float)($data['jumlah_kredit'] ?? 0), 0, ',', '.') ?></td>
            </tr>
            <tr>
                <th>Jangka Waktu</th>
                <td><?= htmlspecialchars((string)($data['jangka_waktu'] ?? '-')) ?> Bulan</td>
            </tr>
            <tr>
                <th>Suku Bunga</th>
                <td><?= htmlspecialchars((string)($data['suku_bunga'] ?? '0')) ?>% per tahun</td>
            </tr>
            <tr>
                <th>Sistem Bunga</th>
                <td><?= htmlspecialchars($sistem_bunga_display) ?></td>
            </tr>
            <tr>
                <th>Angsuran per Bulan</th>
                <td class="val-highlight">Rp <?= number_format((float)($monthly_installment ?? 0), 0, ',', '.') ?></td>
            </tr>
            <tr>
                <th>Grace Period</th>
                <td><?= htmlspecialchars((string)($data['grace_period'] ?? '0')) ?> Bulan</td>
            </tr>
            <tr>
                <th>Agunan / Jaminan</th>
                <td><?= htmlspecialchars($agunan_desc) ?></td>
            </tr>
        </table>
    </div>

    <!-- III. Hasil Analisa 6C -->
    <div class="section-box">
        <div class="section-head light">III. Hasil Analisa 6C (Kelayakan Kredit)</div>
        <table class="score-table">
            <thead>
                <tr>
                    <th>Unsur Penilaian (6C)</th>
                    <th>Skor Nilai</th>
                    <th>Interpretasi / Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scoreRows as $item): ?>
                    <?php $score = (int)($print_6c[$item['key']] ?? 0); ?>
                    <tr>
                        <td><?= htmlspecialchars($item['label']) ?></td>
                        <td><?= $score > 0 ? $score . ' / 5' : '-' ?></td>
                        <td style="color: <?= $colorLabel6C($score) ?>"><?= $score > 0 ? htmlspecialchars($gradeLabel6C($score)) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>TOTAL SKOR 6C :</th>
                    <td><?= number_format((float)$analisa_6c_total, 1) ?> / 5.0</td>
                    <td>
                        <span style="color: <?= $analisa_6c_total >= 3 ? 'var(--bw-success)' : 'var(--bw-danger)' ?>; font-weight:700;">
                            <?= $analisa_6c_total >= 3 ? '☑' : '☐' ?> <?= $kelayakan_kredit ?>
                        </span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Kesimpulan Akhir & Rekomendasi -->
    <div class="section-box">
        <div class="section-head warning">🔴 Kesimpulan Akhir & Rekomendasi</div>
        <table class="kesimpulan-table">
            <tr>
                <th>Status Risiko</th>
                <td>
                    <span class="badge <?= $risk_level === 'TINGGI' ? 'badge-danger' : ($risk_level === 'RENDAH' ? 'badge-success' : 'badge-warning') ?>">
                        <?= $risk_level ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Kelayakan Kredit</th>
                <td>
                    <span style="color: <?= $kelayakan_kredit === 'LAYAK' ? 'var(--bw-success)' : 'var(--bw-danger)' ?>; font-weight:700; font-size:13px;">
                        ☑ <?= $kelayakan_kredit ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Status Kelayakan Pembayaran</th>
                <td><strong><?= ($remaining_capacity ?? 0) > 0 ? 'LAYAK' : 'TIDAK LAYAK' ?></strong></td>
            </tr>
            <tr>
                <th>Repayment Capacity</th>
                <td>Rp <?= number_format((float)($data['repayment_capacity'] ?? $remaining_capacity ?? 0), 0, ',', '.') ?> / Angsuran: Rp <?= number_format((float)($monthly_installment ?? 0), 0, ',', '.') ?></td>
            </tr>
            <tr>
                <th>Rekomendasi Analis</th>
                <td>
                    <?php
                    $rekom_upper = strtoupper(trim($rekomendasi_6c));
                    $rekom_style = (strpos($rekom_upper, 'DISETUJUI') !== false || strpos($rekom_upper, 'SETUJU') !== false)
                        ? 'background:#d4edda;color:#155724;border-left:4px solid #28a745;'
                        : 'background:#fff3cd;color:#856404;border-left:4px solid #ffc107;';
                    ?>
                    <div style="<?= $rekom_style ?> padding:6px 14px; font-weight:800; font-size:13px; text-transform:uppercase; margin-bottom:6px;">
                        <?= htmlspecialchars($rekomendasi_6c) ?>
                    </div>
                    <?php if (!empty($catatan_khusus)): ?>
                    <div class="catatan-box">
                        <em>Catatan Khusus:</em><br>
                        <?= htmlspecialchars($catatan_khusus) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($note_analis)): ?>
                    <div class="note-analis-box">
                        <strong>Note Analis (Kesimpulan):</strong>
                        <?= htmlspecialchars($note_analis) ?>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer-note">
        <div class="footer-row">
            <span>Dokumen ini dicetak secara otomatis oleh Sistem Informasi Pengajuan Kredit – Bank Wonosobo</span>
            <span>Halaman 2 dari 3</span>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- PAGE 3: LEMBAR KEPUTUSAN KREDIT                              -->
<!-- ============================================================ -->
<div class="print-page page-break" id="page3">
    <div class="topmeta">
        <div><?= date('d/m/y, H:i') ?></div>
        <div><?= $pengajuan_label ?></div>
    </div>

    <!-- Bank Header (repeated) -->
    <div class="bank-header">
        <div class="bank-logo">
            <img src="logobawon.png" alt="Logo Bank Wonosobo" style="display:block; width:100%; height:100%; object-fit:contain;">
        </div>
        <div class="bank-text">
            <div class="bank-name">PT BPR BANK WONOSOBO (PERSERODA)</div>
            <div class="bank-addr">
                Kantor Pusat: Jl. Ahmad Yani No. 160 Wonosobo 56311<br>
                Telp: (0286) 321293 &nbsp;|&nbsp; Email: bprbankwonosobo@yahoo.co.id
            </div>
        </div>
    </div>

    <hr class="rule">

    <!-- Document Info -->
    <div class="doc-info">
        <div class="left">
            <strong>Nomor:</strong> <?= $nomor_dokumen ?><br>
            <strong>Lampiran:</strong> 1 (Satu) Berkas
        </div>
        <div class="right">Wonosobo, <?= date('d F Y') ?></div>
    </div>

    <div class="doc-title">Lembar Keputusan Kredit</div>

    <!-- V. Timeline Proses Persetujuan -->
    <div class="section-box">
        <div class="section-head">V. Timeline Proses Persetujuan</div>
        <div class="approval-grid">
            <?php foreach ($timeline_data as $td): ?>
                <?php
                $cardClass = 'is-pending';
                if ($td['approved']) {
                    $cardClass = ($td['role'] === 'kepatuhan') ? 'is-kepatuhan-approved' : 'is-approved';
                }
                ?>
                <div class="approval-card <?= $cardClass ?>">
                    <div class="approval-role"><?= htmlspecialchars($td['title']) ?></div>
                    <div class="approval-name"><?= htmlspecialchars($td['nama']) ?></div>
                    <?php if ($td['approved']): ?>
                        <div class="approval-status ok">✓ Disetujui</div>
                        <?php if ($td['tanggal']): ?>
                            <div class="approval-date">📅 <?= $td['tanggal'] ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="approval-status pending">Menunggu Persetujuan</div>
                        <div class="approval-date">📋 Belum diproses</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Signature Block -->
    <div class="signature-block" style="grid-template-columns: repeat(<?= count($ttd_data) ?: 1 ?>, 1fr);">
        <?php foreach ($ttd_data as $ttd): ?>
            <div class="sig-col">
                <div class="sig-title"><?= htmlspecialchars($ttd['title']) ?></div>
                <div class="sig-date">Tgl. <?= $ttd['tanggal'] ?></div>
                <div class="sig-name"><?= htmlspecialchars($ttd['nama']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Footer -->
    <div class="footer-note">
        <div>Dokumen ini dicetak secara otomatis oleh Sistem Informasi Pengajuan Kredit – Bank Wonosobo</div>
        <div class="footer-row">
            <span>Tanggal & Waktu Cetak: <?= $cetak_timestamp ?></span>
            <span>Halaman 3 dari 3</span>
        </div>
        <div class="footer-warning">⚠ Dokumen Resmi - Harap Disimpan dengan Aman</div>
    </div>
</div>

</body>
</html>
