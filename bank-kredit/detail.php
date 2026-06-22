<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/helpers/credit_helper.php';
require_once __DIR__ . '/helpers/repayment_override.php';


if (!isLoggedIn()) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID Pengajuan tidak ditemukan.");
}

// Get Pengajuan
$stmt = $pdo->prepare("
    SELECT p.*, u.nama AS nama_input, uo.nama AS repayment_override_by_nama
    FROM pengajuan_kredit p
    JOIN users u ON p.input_by = u.id_user
    LEFT JOIN users uo ON p.repayment_override_by = uo.id_user
    WHERE p.id_pengajuan = ?
");
$stmt->execute([$id]);
$data = $stmt->fetch();

$repaymentOverride = $data ? getRepaymentOverrideInfo($data) : null;
$canRepaymentOverride = $data && canOverrideRepaymentForPengajuan($data);

if (!$data) {
    die("Data tidak ditemukan.");
}

if (!canAccessPengajuanDetail($data)) {
    http_response_code(403);
    die('Anda tidak memiliki akses ke pengajuan ini.');
}

// Check edit permission
$can_edit = canEditPengajuan($data);

// Get Neraca
$stmt = $pdo->prepare("SELECT * FROM analisa_neraca WHERE id_pengajuan = ?");
$stmt->execute([$id]);
$neraca = $stmt->fetch();

// Get 6C
$stmt = $pdo->prepare("SELECT * FROM analisa_5c WHERE id_pengajuan = ?");
$stmt->execute([$id]);
$analisa_6c = $stmt->fetch();

// Get Jaminan Detail (Multi-Agunan Support)
$jaminan_tanah = [];
$jaminan_kendaraan = [];
$jaminan_emas = [];

// Fetch ALL tanah_bangunan records for this pengajuan
$stmt = $pdo->prepare("SELECT * FROM jaminan_tanah_bangunan WHERE id_pengajuan = ? ORDER BY id_jaminan ASC");
$stmt->execute([$id]);
$jaminan_tanah = $stmt->fetchAll();

// Fetch ALL kendaraan records for this pengajuan
$stmt = $pdo->prepare("SELECT * FROM jaminan_kendaraan WHERE id_pengajuan = ? ORDER BY id_jaminan ASC");
$stmt->execute([$id]);
$jaminan_kendaraan = $stmt->fetchAll();

// Fetch ALL emas records for this pengajuan
$stmt = $pdo->prepare("SELECT * FROM jaminan_emas WHERE id_pengajuan = ? ORDER BY id_jaminan ASC");
$stmt->execute([$id]);
$jaminan_emas = $stmt->fetchAll();

// Fetch Multiple Agunan Foto
$agunan_foto_all = [];
$stmt = $pdo->prepare("
    SELECT af.*, 
           CASE 
               WHEN af.tipe_jaminan='tanah_bangunan' THEN (SELECT alamat_agunan FROM jaminan_tanah_bangunan WHERE id_jaminan=af.id_jaminan LIMIT 1)
               WHEN af.tipe_jaminan='kendaraan' THEN (SELECT CONCAT(merk,' ',tipe) FROM jaminan_kendaraan WHERE id_jaminan=af.id_jaminan LIMIT 1)
               ELSE ''
           END as agunan_desc
    FROM agunan_foto af
    WHERE af.id_pengajuan=?
    ORDER BY af.created_at DESC
");
$stmt->execute([$id]);
$agunan_foto_all = $stmt->fetchAll();

// Calculate aggregate totals from all jaminan
$total_nilai_pasar = 0;
$total_nilai_taksasi = 0;
$total_nilai_likuidasi = 0;
$total_agunan_count = 0;

foreach ($jaminan_tanah as $jt) {
    $total_nilai_pasar += floatval($jt['nilai_pasar'] ?? 0);
    $total_nilai_taksasi += floatval($jt['nilai_taksasi'] ?? 0);
    $total_nilai_likuidasi += floatval($jt['nilai_likuidasi'] ?? 0);
    $total_agunan_count++;
}
foreach ($jaminan_kendaraan as $jk) {
    $total_nilai_pasar += floatval($jk['nilai_pasar'] ?? 0);
    $total_nilai_taksasi += floatval($jk['nilai_taksasi'] ?? 0);
    $total_nilai_likuidasi += floatval($jk['nilai_likuidasi'] ?? 0);
    $total_agunan_count++;
}
foreach ($jaminan_emas as $je) {
    $total_nilai_pasar += floatval($je['nilai_pasar'] ?? 0);
    $total_nilai_taksasi += floatval($je['nilai_taksasi'] ?? 0);
    $total_nilai_likuidasi += floatval($je['nilai_likuidasi'] ?? 0);
    $total_agunan_count++;
}

// Backward compatibility: single jaminan variable for old code that references it
$jaminan = null;
if (!empty($jaminan_tanah)) {
    $jaminan = $jaminan_tanah[0];
} elseif (!empty($jaminan_kendaraan)) {
    $jaminan = $jaminan_kendaraan[0];
}

// Get Assessment Kepatuhan
$assessment = null;
$stmt = $pdo->prepare("SELECT * FROM assessment_kepatuhan WHERE id_pengajuan = ?");
$stmt->execute([$id]);
$assessment = $stmt->fetch();

// Get Timeline
$stmt = $pdo->prepare("
    SELECT a.*, u.nama as nama_approver, u.role as role_approver 
    FROM approval_kredit a 
    LEFT JOIN users u ON a.id_user = u.id_user 
    WHERE a.id_pengajuan = ? 
    ORDER BY a.tanggal_approval ASC
");
$stmt->execute([$id]);
$timeline = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Detail Pengajuan Kredit</title>
    <link rel="stylesheet" href="assets/style.css">
    <script>window.__CSRF_TOKEN__ = <?= json_encode(generateCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
</head>

<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="container">
        <a href="javascript:history.back()" class="btn btn-secondary" style="margin-bottom: 1rem;">&larr; Kembali</a>

        <?php if (!$can_edit): ?>
        <div class="alert alert-info" style="background: #E0E7FF; border: 1px solid #4F46E5; color: #4338CA; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
            <strong>ℹ️ Mode View Only</strong> - Anda bisa melihat detail pengajuan ini tapi tidak bisa melakukan perubahan. Hanya analis yang menginput dan staff approval yang bisa mengedit.
        </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between;">
                <div>
                    <h2><?= htmlspecialchars($data['nama_debitur']) ?></h2>
                    <p class="text-muted">ID Nasabah: <?= htmlspecialchars($data['id_nasabah'] ?? '-') ?> | NIK: <?= htmlspecialchars($data['nik']) ?> | NPWP: <?= htmlspecialchars($data['npwp'] ?? '-') ?></p>
                    <p class="text-muted">Pekerjaan: <?= htmlspecialchars($data['pekerjaan']) ?><?= (!empty($data['nib'])) ? " | NIB: " . htmlspecialchars($data['nib']) : '' ?></p>
                </div>
                <div style="text-align: right;">
                    <h2 class="text-primary"><?= formatRupiah($data['jumlah_kredit']) ?></h2>
                    <div style="display:flex; gap:0.5rem; align-items:center; justify-content:flex-end;">
                        <span class="badge badge-process"><?= strtoupper($data['status_pengajuan']) ?></span>
                        <?php // Action buttons: Edit (analis owner), Delete (owner or admin), Continue (if user's role matches posisi_saat_ini) ?>
                        <?php
                        $analis_edit_ok = $_SESSION['user_id'] == $data['input_by']
                            && in_array($data['status_pengajuan'] ?? '', ['draft', 'revisi', 'ditolak'], true)
                            && $can_edit;
                        ?>
                        <?php if ($analis_edit_ok): ?>
                            <a href="analis/edit.php?id=<?= (int) $data['id_pengajuan'] ?>" class="btn btn-primary" style="font-size:0.85rem; padding:0.35rem 0.6rem;">✏️ Edit</a>
                        <?php endif; ?>

                        <?php 
                        // Print button: visible to ALL logged-in roles for any status
                        $can_print = isLoggedIn();
                        ?>
                        <?php if ($can_print): ?>
                            <a href="print.php?id=<?= (int) $data['id_pengajuan'] ?>&from=detail" class="btn btn-success" style="font-size:0.85rem; padding:0.35rem 0.6rem;" target="_blank">🖨️ Cetak / PDF</a>
                        <?php endif; ?>

                        <?php if (($can_edit && $_SESSION['role'] === 'Superadmin') || ($_SESSION['user_id'] == $data['input_by'] && $can_edit)): ?>
                            <form method="POST" action="detail_action.php" onsubmit="return confirm('Hapus pengajuan ini secara permanen? Semua data terkait (agunan, neraca, approval) akan ikut terhapus dan TIDAK BISA dikembalikan.');" style="display:inline-block; margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id_pengajuan" value="<?= $data['id_pengajuan'] ?>">
                                <button type="submit" class="btn btn-danger" style="font-size:0.85rem; padding:0.35rem 0.6rem;">🗑️ Hapus</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($_SESSION['user_id'] == $data['input_by'] && in_array($data['status_pengajuan'], ['revisi','ditolak'])): ?>
                            <form method="POST" action="detail_action.php" style="display:inline-block; margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="kirim_ulang">
                                <input type="hidden" name="id_pengajuan" value="<?= $data['id_pengajuan'] ?>">
                                <input type="hidden" name="catatan" id="kirim_catatan" value="">
                                <button type="button" class="btn btn-primary" style="font-size:0.85rem; padding:0.35rem 0.6rem;" onclick="var c=prompt('Catatan singkat untuk kirim ulang (opsional):'); if(c!==null){ document.getElementById('kirim_catatan').value = c; this.form.submit(); }">📤 Kirim Ulang</button>
                            </form>
                        <?php endif; ?>

                        <?php
                        $status_live = $data['status_pengajuan'] ?? '';
                        $boleh_lanjut = $_SESSION['role'] === $data['posisi_saat_ini']
                            && !in_array($status_live, ['selesai', 'ditolak', 'revisi', 'draft', 'disetujui'], true);
                        ?>
                        <?php if ($boleh_lanjut): ?>
                            <?php
                                // Redirect to role's proses page
                                $rolePage = ($_SESSION['role'] === 'analis') ? 'analis/dashboard.php' : ($_SESSION['role'] . '/proses.php');
                            ?>
                            <a href="<?= $rolePage ?>" class="btn btn-success" style="font-size:0.85rem; padding:0.35rem 0.6rem;">▶️ Lanjutkan Proses</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid #E5E7EB;">

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <div>
                    <h3>I. Data Pribadi</h3>
                    <table style="width:100%; font-size:0.9rem;">
                        <tr>
                            <td style="width:120px; color:#64748B;">Alamat KTP</td>
                            <td>: <?= htmlspecialchars($data['alamat_ktp'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td style="color:#64748B;">Domisili</td>
                            <td>: <?= htmlspecialchars($data['alamat_domisili'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td style="color:#64748B;">No HP</td>
                            <td>: <?= htmlspecialchars($data['no_hp'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td style="color:#64748B;">Status</td>
                            <td>: <?= ucfirst($data['status_perkawinan'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td style="color:#64748B;">Pasangan</td>
                            <td>: <?= htmlspecialchars($data['nama_pasangan'] ?? '-') ?></td>
                        </tr>
                    </table>
                </div>
                <div>
                    <h3>II. Data Usaha & Keuangan</h3>
                    <table style="width:100%; font-size:0.9rem;">
                        <tr>
                            <td style="width:120px; color:#64748B;">Nama Usaha</td>
                            <td>: <?= htmlspecialchars($data['nama_usaha'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td style="color:#64748B;">Bidang</td>
                            <td>: <?= htmlspecialchars($data['bidang_usaha'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td style="color:#64748B;">Lama Usaha</td>
                            <td>: <?= htmlspecialchars($data['lama_usaha'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <hr>
                            </td>
                        </tr>
                        <tr>
                            <td style="color:#64748B;">Omset/Bln</td>
                            <td>: <strong><?= formatRupiah($data['omset_per_bulan'] ?? 0) ?></strong></td>
                        </tr>
                        <tr>
                            <td style="color:#64748B;">Pendapatan Lain</td>
                            <td>: <?= formatRupiah($data['pendapatan_lain'] ?? 0) ?></td>
                        </tr>
                        <tr>
                            <td style="color:#64748B;">Biaya Ops</td>
                            <td>: <?= formatRupiah($data['biaya_operasional'] ?? 0) ?></td>
                        </tr>
                        <tr>
                            <td style="color:#64748B;">Laba Bersih</td>
                            <td>: <strong style="color:green;"><?= formatRupiah($data['laba_bersih'] ?? 0) ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td style="color:#64748B;">Repayment Cap.</td>
                            <td>:
                                <span style="color:#D97706; font-weight:bold;"><?= formatRupiah($data['repayment_capacity'] ?? 0) ?></span>
                                <?php if ($repaymentOverride && $repaymentOverride['aktif']): ?>
                                    <span style="background:#fef3c7;color:#92400e;padding:0.15rem 0.5rem;border-radius:4px;font-size:0.75rem;font-weight:700;margin-left:0.35rem;">OVERRIDE DIREKSI</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($repaymentOverride && $repaymentOverride['aktif']): ?>
                        <tr>
                            <td style="color:#64748B;">Nilai Dihitung</td>
                            <td>: <?= formatRupiah($repaymentOverride['nilai_dihitung']) ?></td>
                        </tr>
                        <tr>
                            <td style="color:#64748B;">Alasan Override</td>
                            <td>: <?= nl2br(htmlspecialchars($repaymentOverride['alasan'])) ?></td>
                        </tr>
                        <tr>
                            <td style="color:#64748B;">Override Oleh</td>
                            <td>: <?= htmlspecialchars($repaymentOverride['override_by_nama'] ?? '-') ?>
                                <?php if (!empty($repaymentOverride['override_at'])): ?>
                                    <span style="color:#94a3b8;font-size:0.85rem;">(<?= date('d/m/Y H:i', strtotime($repaymentOverride['override_at'])) ?>)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <?php if ($canRepaymentOverride): ?>
            <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:1.25rem;margin-bottom:2rem;">
                <h3 style="color:#92400e;margin:0 0 0.75rem;font-size:1rem;">Override Repayment Capacity (Direksi)</h3>
                <p style="color:#78350f;font-size:0.9rem;margin:0 0 1rem;">
                    Override hanya berlaku untuk pengajuan ini dan <strong>tidak mengubah master parameter</strong>.
                    Nilai dihitung sistem saat ini:
                    <strong><?= formatRupiah($repaymentOverride['nilai_dihitung'] ?? ($data['repayment_capacity'] ?? 0)) ?></strong>
                </p>
                <div id="rpc-override-msg" style="display:none;padding:0.75rem;border-radius:6px;margin-bottom:0.75rem;font-size:0.9rem;"></div>
                <?php if ($repaymentOverride && $repaymentOverride['aktif']): ?>
                    <p style="margin:0 0 0.75rem;color:#166534;font-weight:600;">Override aktif: <?= formatRupiah($repaymentOverride['nilai_override']) ?></p>
                    <button type="button" class="btn btn-secondary" id="btn-revoke-rpc-override">Cabut Override</button>
                <?php else: ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:0.75rem;">
                        <div>
                            <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.35rem;">Nilai Override (Rp)</label>
                            <input type="text" id="rpc_override_nilai" class="form-control" placeholder="cth: 5000000"
                                value="<?= htmlspecialchars(number_format((float)($data['repayment_capacity'] ?? 0), 0, '', '.'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.35rem;">Alasan Override <span style="color:#dc2626">*</span></label>
                            <textarea id="rpc_override_alasan" rows="3" class="form-control" placeholder="Jelaskan alasan bisnis/risk exception (min. 10 karakter)"></textarea>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" id="btn-apply-rpc-override">Terapkan Override</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- IV. MULTI AGUNAN SECTION -->
            <div
                style="background: #F0FDFA; padding: 1.5rem; border-left: 4px solid #0D9488; margin-bottom: 2rem; border-radius:0 0.5rem 0.5rem 0;">
                <h3 style="color:#0F766E; margin-bottom:1rem;">IV. Analisa Agunan (<?= $total_agunan_count ?> Jaminan)
                </h3>

                <?php if ($total_agunan_count == 0): ?>
                    <p style="color:#94a3b8; font-style:italic;">Belum ada data agunan yang diinput.</p>
                <?php endif; ?>

                <?php
                $agn_no = 0;
                // Display all tanah_bangunan
                foreach ($jaminan_tanah as $jt):
                    $agn_no++;
                    ?>
                    <div
                        style="background:#fff; border-radius:8px; padding:1.25rem; margin-bottom:1rem; border:1px solid #e2e8f0;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <span
                                    style="background:#0D9488; color:#fff; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.75rem;"><?= $agn_no ?></span>
                                <strong>🏠 Tanah & Bangunan</strong>
                            </div>
                            <div style="display:flex; gap:0.5rem;">
                                <span
                                    style="background:#e0f2fe; color:#0369a1; padding:0.2rem 0.6rem; border-radius:4px; font-size:0.8rem; font-weight:600;">
                                    <?= strtoupper(str_replace('_', ' ', $jt['kategori_agunan'] ?? '-')) ?>
                                </span>
                                <span
                                    style="background:<?= (($jt['tipe_valuasi'] ?? 'otomatis') === 'manual') ? '#fef2f2' : '#f0fdf4' ?>; color:<?= (($jt['tipe_valuasi'] ?? 'otomatis') === 'manual') ? '#dc2626' : '#16a34a' ?>; padding:0.2rem 0.6rem; border-radius:4px; font-size:0.8rem; font-weight:600;">
                                    <?= (($jt['tipe_valuasi'] ?? 'otomatis') === 'manual') ? '✏️ Manual Override' : '🔄 Otomatis' ?>
                                </span>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div>
                                <h5 style="border-bottom: 1px solid #ccc; padding-bottom: 5px;">Data Fisik & Legalitas</h5>
                                <table style="width:100%; font-size:0.9rem;">
                                    <tr>
                                        <td style="color:#64748B; width:130px;">Alamat</td>
                                        <td>: <?= htmlspecialchars($jt['alamat_agunan'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color:#64748B;">Jenis Surat</td>
                                        <td>: <?= htmlspecialchars($jt['jenis_surat'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color:#64748B;">No. Surat</td>
                                        <td>: <?= htmlspecialchars($jt['nomor_surat'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color:#64748B;">Atas Nama</td>
                                        <td>: <?= htmlspecialchars($jt['atas_nama'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <hr>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="color:#64748B;">Luas Tanah</td>
                                        <td>: <?= $jt['luas_tanah'] ?? 0 ?> m²</td>
                                    </tr>
                                    <tr>
                                        <td style="color:#64748B;">Luas Bangunan</td>
                                        <td>: <?= $jt['luas_bangunan'] ?? 0 ?> m²</td>
                                    </tr>
                                </table>
                            </div>
                            <div>
                                <h5 style="border-bottom: 1px solid #ccc; padding-bottom: 5px;">Analisa Harga (Per m²)</h5>
                                <table style="width:100%; font-size:0.9rem;">
                                    <tr>
                                        <td style="color:#64748B; width:140px;">Harga Tanah (SPPT)</td>
                                        <td>: <?= formatRupiah($jt['harga_tanah_sppt'] ?? 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color:#64748B;">Harga Tanah (Pasar)</td>
                                        <td>: <strong><?= formatRupiah($jt['harga_tanah_pasar'] ?? 0) ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td style="color:#64748B;">Harga Bangunan</td>
                                        <td>: <?= formatRupiah($jt['harga_bangunan_m2'] ?? 0) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <hr style="margin:0.75rem 0; border-top:1px dashed #ccc;">

                        <div
                            style="display:flex; justify-content:space-between; align-items:center; background: #fdf2f8; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #fbcfe8;">
                            <div>
                                <span style="display:block; font-size:0.75rem; color:#be185d;">Nilai Wajar Agunan</span>
                                <span
                                    style="font-size:1.1rem; font-weight:bold; color:#be185d;"><?= formatRupiah($jt['nilai_pasar'] ?? 0) ?></span>
                            </div>
                            <div style="text-align:center;">
                                <span style="display:block; font-size:0.75rem; color:#be185d;">
                                    Nilai Taksasi <?php if (($jt['tipe_valuasi'] ?? 'otomatis') === 'manual'): ?><span style="color:#dc2626; font-weight:bold;">(MANUAL)</span><?php else: ?>(<?= ($jt['kategori_agunan'] == 'sawah_tegal') ? '70%' : '75%' ?>)<?php endif; ?>
                                </span>
                                <span
                                    style="font-size:1.25rem; font-weight:bold; color:#9d174d;"><?= formatRupiah($jt['nilai_taksasi'] ?? 0) ?></span>
                                <?php if (($jt['tipe_valuasi'] ?? 'otomatis') === 'manual' && !empty($jt['nilai_taksasi_manual'])): ?>
                                <small style="display:block; color:#666; margin-top:0.25rem; font-size:0.75rem;">
                                    Override dari: <?= formatRupiah($jt['nilai_taksasi_manual']) ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <div style="text-align:right;">
                                <span style="display:block; font-size:0.75rem; color:#be185d;">Likuidasi (55%)</span>
                                <span
                                    style="font-size:1rem; font-weight:bold; color:#d97706;"><?= formatRupiah($jt['nilai_likuidasi'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php
                // Display all kendaraan
                foreach ($jaminan_kendaraan as $jk):
                    $agn_no++;
                    ?>
                    <div
                        style="background:#fff; border-radius:8px; padding:1.25rem; margin-bottom:1rem; border:1px solid #e2e8f0;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <span
                                    style="background:#0D9488; color:#fff; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.75rem;"><?= $agn_no ?></span>
                                <strong>🚗 Kendaraan</strong>
                            </div>
                            <span
                                style="background:<?= (($jk['tipe_valuasi'] ?? 'otomatis') === 'manual') ? '#fef2f2' : '#f0fdf4' ?>; color:<?= (($jk['tipe_valuasi'] ?? 'otomatis') === 'manual') ? '#dc2626' : '#16a34a' ?>; padding:0.2rem 0.6rem; border-radius:4px; font-size:0.8rem; font-weight:600;">
                                <?= (($jk['tipe_valuasi'] ?? 'otomatis') === 'manual') ? '✏️ Manual Override' : '🔄 Otomatis' ?>
                            </span>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <table style="width:100%; font-size:0.9rem;">
                                <tr>
                                    <td style="color:#64748B; width:130px;">Kendaraan</td>
                                    <td>: <?= htmlspecialchars($jk['merk'] ?? '') ?>
                                        <?= htmlspecialchars($jk['tipe'] ?? '') ?></td>
                                </tr>
                                <tr>
                                    <td style="color:#64748B;">Tahun</td>
                                    <td>: <?= $jk['tahun_pembuatan'] ?? '-' ?></td>
                                </tr>
                                <tr>
                                    <td style="color:#64748B;">No. Polisi</td>
                                    <td>: <?= htmlspecialchars($jk['no_polisi'] ?? '-') ?></td>
                                </tr>
                            </table>
                            <table style="width:100%; font-size:0.9rem;">
                                <tr>
                                    <td style="color:#64748B; width:130px;">Pemilik BPKB</td>
                                    <td>: <?= htmlspecialchars($jk['nama_pemilik'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td style="color:#64748B;">No Rangka</td>
                                    <td>: <?= htmlspecialchars($jk['no_rangka'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td style="color:#64748B;">No Mesin</td>
                                    <td>: <?= htmlspecialchars($jk['no_mesin'] ?? '-') ?></td>
                                </tr>
                                <?php if (isset($jk['no_stnk']) && !is_null($jk['no_stnk']) && $jk['no_stnk'] !== ''): ?>
                                <tr style="background:#f0f9ff; border-top:1px solid #bfdbfe;">
                                    <td style="color:#0369a1; font-weight:600;">No BPKB</td>
                                    <td>: <?= htmlspecialchars($jk['no_stnk']) ?></td>
                                </tr>
                                <?php if (isset($jk['masa_berlaku_stnk']) && !is_null($jk['masa_berlaku_stnk']) && $jk['masa_berlaku_stnk'] !== ''): ?>
                                <tr style="background:#f0f9ff;">
                                    <td style="color:#0369a1; font-weight:600;">Masa Berlaku BPKB</td>
                                    <td>: <?= date('d F Y', strtotime($jk['masa_berlaku_stnk'])) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php endif; ?>
                        </div>
                        <hr style="margin:0.75rem 0; border-top:1px dashed #ccc;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="display:block; font-size:0.75rem; color:#64748B;">Nilai Pasar</span>
                                <span
                                    style="font-size:1.1rem; font-weight:bold; color:#0F766E;"><?= formatRupiah($jk['nilai_pasar'] ?? 0) ?></span>
                            </div>
                            <div style="text-align:center;">
                                <span style="display:block; font-size:0.75rem; color:#64748B;">
                                    Taksasi <?php if (($jk['tipe_valuasi'] ?? 'otomatis') === 'manual'): ?><span style="color:#dc2626; font-weight:bold;">(MANUAL)</span><?php else: ?>(60%)<?php endif; ?>
                                </span>
                                <span
                                    style="font-size:1.1rem; font-weight:bold; color:#059669;"><?= formatRupiah($jk['nilai_taksasi'] ?? 0) ?></span>
                                <?php if (($jk['tipe_valuasi'] ?? 'otomatis') === 'manual' && !empty($jk['nilai_taksasi_manual'])): ?>
                                <small style="display:block; color:#666; margin-top:0.25rem; font-size:0.75rem;">
                                    Override dari: <?= formatRupiah($jk['nilai_taksasi_manual']) ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <div style="text-align:right;">
                                <span style="display:block; font-size:0.75rem; color:#64748B;">Likuidasi (50%)</span>
                                <span
                                    style="font-size:1.1rem; font-weight:bold; color:#D97706;"><?= formatRupiah($jk['nilai_likuidasi'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php
                // Display all emas
                foreach ($jaminan_emas as $je):
                    $agn_no++;
                    ?>
                    <div
                        style="background:#fff; border-radius:8px; padding:1.25rem; margin-bottom:1rem; border:2px solid #fcd34d;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <span
                                    style="background:#f59e0b; color:#fff; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.75rem;"><?= $agn_no ?></span>
                                <strong>💛 EMAS</strong>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <table style="width:100%; font-size:0.9rem;">
                                <tr>
                                    <td style="color:#64748B; width:130px;">Harga per Gram (Rp)</td>
                                    <td>: <?= formatRupiah($je['harga_per_gram'] ?? 0) ?></td>
                                </tr>
                                <tr>
                                    <td style="color:#64748B;">Berat (gram)</td>
                                    <td>: <?= number_format($je['berat'] ?? 0, 3, ',', '.') ?> g</td>
                                </tr>
                            </table>
                        </div>
                        <hr style="margin:0.75rem 0; border-top:1px dashed #ccc;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="display:block; font-size:0.75rem; color:#92400e;">Nilai Pasar</span>
                                <span
                                    style="font-size:1.1rem; font-weight:bold; color:#b45309;"><?= formatRupiah($je['nilai_pasar'] ?? 0) ?></span>
                            </div>
                            <div style="text-align:right;">
                                <span style="display:block; font-size:0.75rem; color:#92400e;">Likuidasi Bank (90%)</span>
                                <span
                                    style="font-size:1rem; font-weight:bold; color:#d97706;"><?= formatRupiah($je['nilai_likuidasi'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($total_agunan_count > 0): ?>
                    <!-- AGGREGATE TOTALS -->
                    <div
                        style="background:linear-gradient(135deg,#1e293b,#334155); color:#fff; padding:1.25rem; border-radius:10px; margin-top:0.5rem;">
                        <h4
                            style="margin:0 0 0.75rem 0; font-size:0.8rem; text-transform:uppercase; letter-spacing:1px; opacity:0.7;">
                            📊 REKAPITULASI TOTAL SELURUH JAMINAN (<?= $total_agunan_count ?> Agunan)</h4>
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                            <div>
                                <div style="font-size:0.75rem; opacity:0.7;">Total Nilai Pasar</div>
                                <div style="font-size:1.25rem; font-weight:800;"><?= formatRupiah($total_nilai_pasar) ?>
                                </div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:0.75rem; opacity:0.7;">Total Nilai Taksasi</div>
                                <div style="font-size:1.25rem; font-weight:800; color:#fbbf24;">
                                    <?= formatRupiah($total_nilai_taksasi) ?></div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:0.75rem; opacity:0.7;">Total Nilai Likuidasi</div>
                                <div style="font-size:1.25rem; font-weight:800; color:#34d399;">
                                    <?= formatRupiah($total_nilai_likuidasi) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- III. Struktur Kredit & IV. Data Agunan -->
            <!-- Note: IV. Agunan is already displayed above in previous section 99-171? No, 99-170 is IV. Analisa Agunan. -->
            <!-- Wait, in detail.php based on view step 272:
                 Lines 99-170 is "IV. Analisa Agunan".
                 Lines 173-175 is "Tujuan Kredit" and "Jangka Waktu" text.
                 So I will replace 173-175 with the new table.
            -->

            <!-- III. NERACA -->
            <?php if ($neraca): ?>
            <div style="background: #F8FAFC; padding: 1.5rem; border-left: 4px solid #3B82F6; margin-bottom: 2rem; border-radius:0 0.5rem 0.5rem 0; clear: both;">
                <h3 style="color:#1E40AF; margin-bottom:1rem;">III. Neraca (Posisi Keuangan - Sebelum & Sesudah Kredit)</h3>

                <!-- NERACA SEBELUM KREDIT -->
                <div style="background:#f0fdf4; padding:1.5rem; border-radius:8px; border-left:4px solid #16a34a; margin-bottom:2rem;">
                    <h4 style="color:#16a34a; margin-top:0; margin-bottom:1rem;">📋 Neraca Sebelum Kredit</h4>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <div>
                            <h5 style="color:#059669; border-bottom:1px solid #10B981; padding-bottom:0.5rem; margin-bottom:0.5rem;">Total Aktiva</h5>
                            <table style="width:100%; font-size:0.95rem;">
                                <tr><td style="color:#64748B;">Kas & Bank</td><td style="text-align:right; font-weight:600;"><?= formatRupiah(($neraca['aktiva_kas']??0) + ($neraca['aktiva_tabungan']??0)) ?></td></tr>
                                <tr><td style="color:#64748B;">Tanah & Bangunan</td><td style="text-align:right; font-weight:600;"><?= formatRupiah($neraca['aktiva_tanah']??0) ?></td></tr>
                                <tr><td style="color:#64748B;">Kendaraan</td><td style="text-align:right; font-weight:600;"><?= formatRupiah($neraca['aktiva_kendaraan']??0) ?></td></tr>
                                <tr><td style="color:#64748B;">Stok & Lainnya</td><td style="text-align:right; font-weight:600;"><?= formatRupiah(($neraca['aktiva_stok']??0) + ($neraca['aktiva_lainnya']??0)) ?></td></tr>
                                <tr><td colspan="2"><hr style="margin:0.25rem 0;"></td></tr>
                                <tr><td style="color:#065F46; font-weight:bold;">TOTAL AKTIVA</td><td style="text-align:right; font-weight:bold; color:#065F46;"><?= formatRupiah($neraca['total_aktiva']??0) ?></td></tr>
                            </table>
                        </div>
                        <div>
                            <h5 style="color:#DC2626; border-bottom:1px solid #EF4444; padding-bottom:0.5rem; margin-bottom:0.5rem;">Total Pasiva</h5>
                            <table style="width:100%; font-size:0.95rem;">
                                <tr><td style="color:#64748B;">Pinjaman Bank</td><td style="text-align:right; font-weight:600;"><?= formatRupiah($neraca['pasiva_hutang_bank']??0) ?></td></tr>
                                <tr><td style="color:#64748B;">Hutang/Kewajiban Lain</td><td style="text-align:right; font-weight:600;"><?= formatRupiah($neraca['pasiva_hutang_lain']??0) ?></td></tr>
                                <tr><td style="color:#64748B; font-weight:bold;">Modal Sendiri</td><td style="text-align:right; font-weight:bold; color:#4F46E5; font-size:1.05rem;"><?= formatRupiah($neraca['pasiva_modal']??0) ?></td></tr>
                                <tr><td colspan="2"><hr style="margin:0.25rem 0;"></td></tr>
                                <tr><td style="color:#991B1B; font-weight:bold;">TOTAL PASIVA</td><td style="text-align:right; font-weight:bold; color:#991B1B;"><?= formatRupiah($neraca['total_pasiva']??0) ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- NERACA SESUDAH KREDIT -->
                <div style="background:#fef3c7; padding:1.5rem; border-radius:8px; border-left:4px solid #d97706;">
                    <h4 style="color:#d97706; margin-top:0; margin-bottom:1rem;">📝 Neraca Sesudah Kredit (Proyeksi)</h4>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <div>
                            <h5 style="color:#059669; border-bottom:1px solid #10B981; padding-bottom:0.5rem; margin-bottom:0.5rem;">Total Aktiva</h5>
                            <table style="width:100%; font-size:0.95rem;">
                                <tr><td style="color:#64748B;">Kas & Bank</td><td style="text-align:right; font-weight:600;"><?= formatRupiah(($neraca['aktiva_kas_sesudah']??0) + ($neraca['aktiva_tabungan_sesudah']??0)) ?></td></tr>
                                <tr><td style="color:#64748B;">Tanah & Bangunan</td><td style="text-align:right; font-weight:600;"><?= formatRupiah($neraca['aktiva_tanah_sesudah']??0) ?></td></tr>
                                <tr><td style="color:#64748B;">Kendaraan</td><td style="text-align:right; font-weight:600;"><?= formatRupiah($neraca['aktiva_kendaraan_sesudah']??0) ?></td></tr>
                                <tr><td style="color:#64748B;">Stok & Lainnya</td><td style="text-align:right; font-weight:600;"><?= formatRupiah(($neraca['aktiva_stok_sesudah']??0) + ($neraca['aktiva_lainnya_sesudah']??0)) ?></td></tr>
                                <tr><td colspan="2"><hr style="margin:0.25rem 0;"></td></tr>
                                <tr><td style="color:#065F46; font-weight:bold;">TOTAL AKTIVA</td><td style="text-align:right; font-weight:bold; color:#065F46;"><?= formatRupiah($neraca['total_aktiva_sesudah']??0) ?></td></tr>
                            </table>
                        </div>
                        <div>
                            <h5 style="color:#DC2626; border-bottom:1px solid #EF4444; padding-bottom:0.5rem; margin-bottom:0.5rem;">Total Pasiva</h5>
                            <table style="width:100%; font-size:0.95rem;">
                                <tr><td style="color:#64748B;">Pinjaman Bank</td><td style="text-align:right; font-weight:600;"><?= formatRupiah($neraca['pasiva_hutang_bank_sesudah']??0) ?></td></tr>
                                <tr><td style="color:#64748B;">Hutang/Kewajiban Lain</td><td style="text-align:right; font-weight:600;"><?= formatRupiah($neraca['pasiva_hutang_lain_sesudah']??0) ?></td></tr>
                                <tr><td style="color:#64748B; font-weight:bold;">Modal Sendiri</td><td style="text-align:right; font-weight:bold; color:#4F46E5; font-size:1.05rem;"><?= formatRupiah($neraca['pasiva_modal_sesudah']??0) ?></td></tr>
                                <tr><td colspan="2"><hr style="margin:0.25rem 0;"></td></tr>
                                <tr><td style="color:#991B1B; font-weight:bold;">TOTAL PASIVA</td><td style="text-align:right; font-weight:bold; color:#991B1B;"><?= formatRupiah($neraca['total_pasiva_sesudah']??0) ?></td></tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Balance status indicator -->
                    <?php 
                        $diff = abs(($neraca['total_aktiva_sesudah']??0) - ($neraca['total_pasiva_sesudah']??0));
                        $is_balanced = ($diff <= 100);
                    ?>
                    <div style="margin-top:1rem; padding:1rem; border-radius:6px; <?= $is_balanced ? 'background:#dcfce7; color:#166534;' : 'background:#fee2e2; color:#b91c1c;' ?>">
                        <?php if ($is_balanced): ?>
                            ✅ <strong>Neraca Seimbang</strong> (Total Aktiva = Total Pasiva)
                        <?php else: ?>
                            ⚠️ <strong>Neraca Tidak Seimbang</strong> - Selisih: <?= formatRupiah($diff) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- V. STRUKTUR KREDIT -->
            <div style="margin-bottom: 2rem;">
                <h3>V. Struktur Kredit</h3>
                <table style="width:100%; font-size:0.9rem;">
                    <tr>
                        <td style="color:#64748B; width:150px;">Jenis Kredit</td>
                        <td>: <span
                                class="badge badge-process"><?= htmlspecialchars($data['jenis_kredit'] ?? '-') ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:#64748B;">Plafon Pengajuan</td>
                        <td>: <strong><?= formatRupiah($data['jumlah_kredit']) ?></strong></td>
                    </tr>
                    <tr>
                        <td style="color:#64748B;">Jangka Waktu</td>
                        <td>: <?= $data['jangka_waktu'] ?> Bulan</td>
                    </tr>
                    <tr>
                        <td style="color:#64748B; vertical-align:top;">Tujuan</td>
                        <td>: <?= nl2br(htmlspecialchars($data['tujuan_kredit'])) ?></td>
                    </tr>
                </table>
            </div>
            <!-- VI. ANALISA 6C -->
            <?php if ($analisa_6c): ?>
            <?php
            // Compute display scores from stored integer scores (1-5 scale)
            $s6c_char   = intval($analisa_6c['character_score']  ?? 0);
            $s6c_cap    = intval($analisa_6c['capacity_score']   ?? 0);
            $s6c_capit  = intval($analisa_6c['capital_score']    ?? 0);
            $s6c_cond   = intval($analisa_6c['condition_score']  ?? 0);
            $s6c_coll   = intval($analisa_6c['collateral_score'] ?? 0);
            $s6c_const  = intval($analisa_6c['constraint_score'] ?? 0);
            $s6c_total  = floatval($analisa_6c['total_score']    ?? 0);
            $s6c_rekomendasi = $analisa_6c['rekomendasi'] ?? '-';
            $s6c_catatan = $analisa_6c['catatan_5c'] ?? '';

            // Grade helper - Display Scale (5=Best, 1=Worst)
            function gradeLabel6C(int $score): string {
                if ($score == 5) return 'Sangat Baik';
                if ($score == 4) return 'Baik';
                if ($score == 3) return 'Cukup';
                if ($score == 2) return 'Kurang';
                if ($score == 1) return 'Sangat Kurang';
                return '-';
            }
            function scoreColor6C(int $score): string {
                if ($score >= 4) return '#059669';    // Green for 4-5 (Good)
                if ($score == 3) return '#D97706';    // Orange for 3 (Neutral)
                return '#DC2626';                     // Red for 1-2 (Bad)
            }
            ?>
            <div style="background: #FEF3C7; padding: 1.5rem; border-left: 4px solid #F59E0B; margin-bottom: 2rem; border-radius:0 0.5rem 0.5rem 0; clear: both;">
                <h3 style="color:#B45309; margin-bottom:1rem;">VI. Hasil Analisa 6C</h3>

                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom:1rem;">
                    <?php
                    $sixc_items = [
                        ['label'=>'Character',  'score'=>$s6c_char,  'note'=>$analisa_6c['catatan_character']??''],
                        ['label'=>'Capacity',   'score'=>$s6c_cap,   'note'=>$analisa_6c['catatan_capacity']??''],
                        ['label'=>'Capital',    'score'=>$s6c_capit, 'note'=>$analisa_6c['catatan_capital']??''],
                        ['label'=>'Condition',  'score'=>$s6c_cond,  'note'=>$analisa_6c['catatan_condition']??''],
                        ['label'=>'Collateral', 'score'=>$s6c_coll,  'note'=>$analisa_6c['catatan_collateral']??''],
                        ['label'=>'Constraint', 'score'=>$s6c_const, 'note'=>$analisa_6c['catatan_constraint_risk']??''],
                    ];
                    foreach ($sixc_items as $ci):
                        $clr = scoreColor6C($ci['score']);
                    ?>
                    <div style="background:#fff; padding:0.75rem; border-radius:0.5rem; border:1px solid #FDE68A;">
                        <span style="display:block; font-size:0.78rem; color:#92400E; font-weight:700; text-transform:uppercase;"><?= $ci['label'] ?></span>
                        <div style="display:flex; align-items:baseline; gap:0.5rem; margin:0.25rem 0;">
                            <span style="font-size:1.8rem; font-weight:800; color:<?= $clr ?>;"><?= $ci['score'] ?></span>
                            <span style="font-size:0.75rem; color:<?= $clr ?>; font-weight:600;"><?= gradeLabel6C($ci['score']) ?></span>
                        </div>
                        <?php if (!empty($ci['note'])): ?>
                        <p style="font-size:0.78rem; color:#6B7280; margin:0; line-height:1.4;"><?= htmlspecialchars($ci['note']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Total & Rekomendasi -->
                <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
                    <div style="background:#fff8e1; border:1px solid #fcd34d; border-radius:8px; padding:0.75rem 1.25rem; flex:1;">
                        <span style="font-size:0.8rem; color:#92400E; font-weight:600;">TOTAL SKOR 6C</span>
                        <div style="font-size:1.5rem; font-weight:800; color:#B45309;"><?= $s6c_total ?> <span style="font-size:0.85rem; color:#92400E;">/5</span></div>
                    </div>
                    <div style="background:#fff8e1; border:1px solid #fcd34d; border-radius:8px; padding:0.75rem 1.25rem; flex:1;">
                        <span style="font-size:0.8rem; color:#92400E; font-weight:600;">STATUS KELAYAKAN</span>
                        <?php 
                            $status_layak = tentukan_status_kelayakan($s6c_total);
                            $label_layak = get_status_kelayakan_label($s6c_total);
                        ?>
                        <div style="font-size:1.1rem; font-weight:800; color:<?= $status_layak['warna'] ?>;"><?= $label_layak ?></div>
                    </div>
                    <div style="background:#fff8e1; border:1px solid #fcd34d; border-radius:8px; padding:0.75rem 1.25rem; flex:1;">
                        <span style="font-size:0.8rem; color:#92400E; font-weight:600;">REKOMENDASI</span>
                        <div style="font-size:0.95rem; font-weight:700; color:#374151;"><?= $status_layak['rekomendasi'] ?></div>
                    </div>
                    <?php if (!empty($s6c_catatan)): ?>
                    <div style="background:#fff8e1; border:1px solid #fcd34d; border-radius:8px; padding:0.75rem 1.25rem; flex:2;">
                        <span style="font-size:0.8rem; color:#92400E; font-weight:600;">CATATAN ANALISA</span>
                        <p style="margin:0.25rem 0 0 0; font-size:0.9rem; color:#374151; line-height:1.5;"><?= nl2br(htmlspecialchars($s6c_catatan)) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <!-- Data Pendukung & KTP -->
                <div class="card" style="padding: 1rem;">
                    <strong style="display:block; margin-bottom:0.5rem;">Data Pendukung Usaha (KTP/SK/Dll)</strong>
                    <?php if (!empty($data['file_pendukung'])): ?>
                        <?php 
                        $files = explode('|', $data['file_pendukung']);
                        foreach($files as $file): 
                            $f = trim($file);
                            if(empty($f)) continue;
                            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                        ?>
                            <?php if (in_array($ext, ['jpg','jpeg','png','webp'])): ?>
                                <img src="assets/uploads/<?= htmlspecialchars($f) ?>" style="width:100%; height:150px; object-fit:cover; border-radius:0.5rem; margin-top:0.5rem;">
                            <?php endif; ?>
                            <a href="assets/uploads/<?= htmlspecialchars($f) ?>" target="_blank" class="btn btn-secondary" style="font-size:0.8rem; display:block; text-align:center; margin-top:0.5rem; margin-bottom:1rem;">Lihat Dokumen</a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted" style="font-size:0.8rem;">Tidak ada dokumen pendukung.</span>
                    <?php endif; ?>
                </div>

                <!-- Dokumen Pendukung Lain (Opsional) -->
                <?php if (!empty($data['foto_data_pendukung'])): ?>
                <div class="card" style="padding: 1rem;">
                    <strong style="display:block; margin-bottom:0.5rem;">Dokumen Agunan/Lainnya</strong>
                    <?php 
                    $fdocs = explode('|', $data['foto_data_pendukung']);
                    foreach($fdocs as $doc):
                        $d = trim($doc);
                        if(empty($d)) continue;
                        $ext = strtolower(pathinfo($d, PATHINFO_EXTENSION));
                    ?>
                        <?php if (in_array($ext, ['jpg','jpeg','png','webp'])): ?>
                            <img src="assets/uploads/<?= htmlspecialchars($d) ?>" style="width:100%; height:150px; object-fit:cover; border-radius:0.5rem; margin-top:0.5rem;">
                        <?php endif; ?>
                        <a href="assets/uploads/<?= htmlspecialchars($d) ?>" target="_blank" class="btn btn-secondary" style="font-size:0.8rem; display:block; text-align:center; margin-top:0.5rem; margin-bottom:1rem;">Lihat Laporan/Dokumen</a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Jaminan (Scan) -->
                <div class="card" style="padding: 1rem;">
                    <strong style="display:block; margin-bottom:0.5rem;">Dokumen Jaminan (Scan)</strong>
                    <?php if (!empty($data['file_jaminan'])): ?>
                        <?php foreach (explode('|', $data['file_jaminan']) as $fj): if(empty(trim($fj))) continue; ?>
                            <a href="assets/uploads/<?= htmlspecialchars(trim($fj)) ?>" target="_blank" class="btn btn-secondary" style="font-size:0.8rem; display:block; margin-bottom:0.5rem;">Lihat Dokumen</a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-danger" style="font-size:0.8rem;">Tidak ada</span>
                    <?php endif; ?>
                </div>

                <!-- Foto Rumah -->
                <div class="card" style="padding: 1rem;">
                    <strong style="display:block; margin-bottom:0.5rem;">Foto Rumah / Agunan</strong>
                    <?php if (!empty($data['foto_rumah'])): ?>
                        <?php foreach (explode('|', $data['foto_rumah']) as $fr): if(empty(trim($fr))) continue; ?>
                            <img src="assets/uploads/<?= htmlspecialchars(trim($fr)) ?>" style="width:100%; height:150px; object-fit:cover; border-radius:0.5rem; margin-top:0.5rem;">
                            <a href="assets/uploads/<?= htmlspecialchars(trim($fr)) ?>" target="_blank" class="text-primary" style="font-size:0.8rem; display:block; text-align:center; margin-bottom:1rem;">Lihat Full Size</a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-danger" style="font-size:0.8rem;">Tidak ada</span>
                    <?php endif; ?>
                </div>

                <!-- Foto Usaha -->
                <div class="card" style="padding: 1rem;">
                    <strong style="display:block; margin-bottom:0.5rem;">Foto Usaha</strong>
                    <?php if (!empty($data['foto_usaha'])): ?>
                        <?php foreach (explode('|', $data['foto_usaha']) as $fu): if(empty(trim($fu))) continue; ?>
                            <img src="assets/uploads/<?= htmlspecialchars(trim($fu)) ?>" style="width:100%; height:150px; object-fit:cover; border-radius:0.5rem; margin-top:0.5rem;">
                            <a href="assets/uploads/<?= htmlspecialchars(trim($fu)) ?>" target="_blank" class="text-primary" style="font-size:0.8rem; display:block; text-align:center; margin-bottom:1rem;">Lihat Full Size</a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-danger" style="font-size:0.8rem;">Tidak ada</span>
                    <?php endif; ?>
                </div>

                <!-- Multiple Agunan Foto (NEW) -->
                <div class="card" style="padding: 1rem; grid-column: 1 / -1;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <strong style="display:block;">📸 Foto Agunan (Multiple)</strong>
                        <?php if ($can_edit): ?>
                        <small style="color:#0369A1; cursor:pointer;" onclick="document.getElementById('add_agunan_foto_form').style.display = document.getElementById('add_agunan_foto_form').style.display === 'none' ? 'block' : 'none';">
                            + Tambah Foto
                        </small>
                        <?php endif; ?>
                    </div>

                    <!-- Add New Foto Form (Hidden by Default) -->
                    <?php if ($can_edit): ?>
                    <div id="add_agunan_foto_form" style="display:none; background:#f0fdf4; padding:1rem; border-radius:8px; margin-bottom:1rem; border:1px solid #bbf7d0;">
                        <form id="form_tambah_foto" enctype="multipart/form-data">
                            <input type="hidden" name="id_pengajuan" value="<?= htmlspecialchars($id) ?>">
                            <input type="hidden" name="action" value="add_agunan_foto">
                            <div style="margin-bottom:0.75rem;">
                                <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.25rem;">Pilih Foto (JPG/PNG, Max 5MB)</label>
                                <input type="file" id="input_foto_baru" name="foto_baru" accept="image/jpeg,image/png" style="width:100%;">
                                <small style="color:#6b7280; display:block; margin-top:0.25rem;">Format: JPG, JPEG, PNG | Ukuran max 5 MB</small>
                            </div>
                            <div style="margin-bottom:0.75rem;">
                                <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.25rem;">Keterangan (Opsional)</label>
                                <input type="text" name="keterangan_baru" placeholder="Contoh: Foto depan rumah, Foto BPKB, dll" style="width:100%; padding:0.5rem;">
                            </div>
                            <button type="submit" style="background:#059669; color:#fff; border:none; padding:0.5rem 1rem; border-radius:6px; font-weight:600; cursor:pointer; font-size:0.85rem;">
                                ✔ Upload Foto
                            </button>
                            <button type="button" onclick="document.getElementById('add_agunan_foto_form').style.display='none'" style="background:#e2e8f0; color:#374151; border:none; padding:0.5rem 1rem; border-radius:6px; font-weight:600; cursor:pointer; font-size:0.85rem; margin-left:0.5rem;">
                                ✕ Batal
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Foto Grid -->
                    <?php if (!empty($agunan_foto_all)): ?>
                    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:1rem;">
                        <?php foreach ($agunan_foto_all as $foto): ?>
                        <div id="foto_<?= $foto['id'] ?>" style="position:relative; border-radius:8px; overflow:hidden; background:#f1f5f9; aspect-ratio:1;">
                            <img src="assets/uploads/<?= htmlspecialchars($foto['nama_file']) ?>" 
                                 style="width:100%; height:100%; object-fit:cover; cursor:pointer;"
                                 onclick="openLightbox('assets/uploads/<?= htmlspecialchars($foto['nama_file']) ?>', '<?= htmlspecialchars($foto['agunan_desc'] ?? '') ?>')">
                            
                            <!-- Overlay dengan tombol hapus -->
                            <div style="position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0); transition:background 0.2s; display:flex; align-items:center; justify-content:center; gap:0.25rem;" 
                                 class="img-overlay" 
                                 onmouseover="this.style.background='rgba(0,0,0,0.6)'; this.querySelectorAll('button').forEach(b => b.style.display='block')" 
                                 onmouseout="this.style.background='rgba(0,0,0,0)'; this.querySelectorAll('button').forEach(b => b.style.display='none')">
                                <button type="button" onclick="openLightbox('assets/uploads/<?= htmlspecialchars($foto['nama_file']) ?>', '<?= htmlspecialchars($foto['agunan_desc'] ?? '') ?>')" 
                                        style="background:#2563eb; color:#fff; border:none; padding:0.4rem 0.6rem; border-radius:4px; font-weight:bold; cursor:pointer; font-size:0.7rem; display:none;">
                                    👁 View
                                </button>
                                <?php if ($can_edit): ?>
                                <button type="button" onclick="hapusAgunanFoto(<?= $foto['id'] ?>)" 
                                        style="background:#dc2626; color:#fff; border:none; padding:0.4rem 0.6rem; border-radius:4px; font-weight:bold; cursor:pointer; font-size:0.7rem; display:none;">
                                    🗑 Hapus
                                </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Info -->
                            <small style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.7); color:#fff; padding:0.25rem 0.5rem; font-size:0.65rem; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">
                                <?= date('d M', strtotime($foto['created_at'])) ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div style="text-align:center; padding:2rem; color:#94a3b8;">
                        <p style="margin:0;">📁 Belum ada foto agunan yang diupload</p>
                        <?php if ($can_edit): ?>
                        <small style="color:#6b7280;">Klik "Tambah Foto" untuk upload foto agunan</small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ASSESSMENT KEPATUHAN SECTION -->
        <h3 style="margin-top: 2rem;">Hasil Assessment Kepatuhan</h3>
        <div class="card">
            <?php if ($assessment): ?>
                <div style="margin-bottom: 1.5rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div style="background: #EFF6FF; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #0369A1;">
                            <p style="color: #0369A1; font-weight: bold; margin: 0; font-size: 0.85rem;">Tanggal Assessment</p>
                            <p style="margin: 0.5rem 0; font-size: 1rem; font-weight: bold;">
                                <?= date('d F Y', strtotime($assessment['tanggal_assessment'])) ?>
                            </p>
                        </div>
                        <div style="background: #FEF3C7; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #D97706;">
                            <p style="color: #D97706; font-weight: bold; margin: 0; font-size: 0.85rem;">Hasil</p>
                            <p style="margin: 0.5rem 0; font-size: 1rem; font-weight: bold;">
                                <?php 
                                $checklist = json_decode($assessment['checklist_data'], true) ?: [];
                                $comply_count = 0;
                                $not_comply_count = 0;
                                $na_count = 0;
                                
                                foreach ($checklist as $item) {
                                    if ($item['val'] === 'comply') $comply_count++;
                                    elseif ($item['val'] === 'not_comply') $not_comply_count++;
                                    elseif ($item['val'] === 'na') $na_count++;
                                }
                                
                                echo $comply_count . " Comply / " . $not_comply_count . " Not Comply";
                                ?>
                            </p>
                        </div>
                        <div style="background: #E0F2FE; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #0284C7;">
                            <p style="color: #0284C7; font-weight: bold; margin: 0; font-size: 0.85rem;">Marketing</p>
                            <p style="margin: 0.5rem 0; font-size: 1rem; font-weight: bold;">
                                <?= htmlspecialchars($assessment['marketing'] ?: '-') ?>
                            </p>
                        </div>
                    </div>

                    <!-- Checklist Summary -->
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="border-bottom: 2px solid #E5E7EB; padding-bottom: 0.5rem; margin-bottom: 1rem;">
                            Ringkasan Compliance Checklist
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                            <?php 
                            $checklist_items = [
                                'krit_jenis' => 'Kesesuaian jenis debitur',
                                'krit_wni' => 'Kewarganegaraan WNI',
                                'krit_kol' => 'Kolektibilitas debitur',
                                'usaha_pkpb' => 'Usaha bukan PKPB',
                                'dok_form' => 'Formulir permohonan',
                                'dok_ktp' => 'KTP debitur',
                                'leg_nib' => 'NIB/SIUP',
                                'keu_lap' => 'Laporan keuangan',
                                'ag_shm' => 'Sertifikat (SHM/SHGB)',
                                'bmpk' => 'Kesesuaian BMPK',
                                'an_krd' => 'Analisa Kredit',
                                'prod' => 'Produk Kredit'
                            ];
                            
                            foreach ($checklist_items as $key => $label) {
                                if (isset($checklist[$key])) {
                                    $status = $checklist[$key]['val'];
                                    $status_color = $status === 'comply' ? '#10B981' : ($status === 'not_comply' ? '#EF4444' : '#9CA3AF');
                                    $status_text = $status === 'comply' ? 'COMPLY' : ($status === 'not_comply' ? 'NOT COMPLY' : 'N/A');
                                    ?>
                                    <div style="background: #F9FAFB; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid <?= $status_color ?>;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span style="font-weight: 500;"><?= htmlspecialchars($label) ?></span>
                                            <span style="background: <?= $status_color ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: bold;">
                                                <?= $status_text ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($checklist[$key]['ket'])): ?>
                                            <p style="margin: 0.5rem 0 0 0; color: #6B7280; font-size: 0.85rem;">
                                                <em><?= htmlspecialchars($checklist[$key]['ket']) ?></em>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Fasilitas Existing -->
                    <?php 
                    $fasilitas = json_decode($assessment['fasilitas_existing'], true) ?: [];
                    if (!empty($fasilitas)):
                    ?>
                    <div style="margin-bottom: 1.5rem; background: #F0FDF4; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #16A34A;">
                        <h4 style="margin-top: 0;">Fasilitas Kredit Existing</h4>
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <thead>
                                <tr style="background: #DCFCE7;">
                                    <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #BBF7D0;">No Rek</th>
                                    <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #BBF7D0;">Tgl Akad</th>
                                    <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #BBF7D0;">J. Tempo</th>
                                    <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #BBF7D0;">Kol</th>
                                    <th style="padding: 0.5rem; text-align: right; border-bottom: 1px solid #BBF7D0;">Plafond</th>
                                    <th style="padding: 0.5rem; text-align: right; border-bottom: 1px solid #BBF7D0;">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fasilitas as $fas): ?>
                                <tr style="border-bottom: 1px solid #DBEAFE;">
                                    <td style="padding: 0.5rem;"><?= htmlspecialchars($fas['rek']) ?></td>
                                    <td style="padding: 0.5rem;"><?= htmlspecialchars($fas['tgl']) ?></td>
                                    <td style="padding: 0.5rem;"><?= htmlspecialchars($fas['jt']) ?></td>
                                    <td style="padding: 0.5rem;"><?= htmlspecialchars($fas['kol']) ?></td>
                                    <td style="padding: 0.5rem; text-align: right;"><?= formatRupiah($fas['plafond']) ?></td>
                                    <td style="padding: 0.5rem; text-align: right;"><?= formatRupiah($fas['saldo']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <!-- Kesimpulan & Rekomendasi -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <?php if (!empty($assessment['kesimpulan'])): ?>
                        <div style="background: #EDE9FE; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #7C3AED;">
                            <h5 style="margin-top: 0; color: #7C3AED;">Kesimpulan</h5>
                            <p style="margin: 0; color: #333; line-height: 1.6;">
                                <?= nl2br(htmlspecialchars($assessment['kesimpulan'])) ?>
                            </p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($assessment['rekomendasi'])): ?>
                        <div style="background: #FEF3C7; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #D97706;">
                            <h5 style="margin-top: 0; color: #D97706;">Rekomendasi</h5>
                            <p style="margin: 0; color: #333; line-height: 1.6;">
                                <?= nl2br(htmlspecialchars($assessment['rekomendasi'])) ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 1rem; padding: 0.75rem; background: #F3F4F6; border-radius: 0.5rem; font-size: 0.85rem; color: #6B7280;">
                        <p style="margin: 0;">
                            <strong>Created:</strong> <?= date('d F Y H:i', strtotime($assessment['created_at'])) ?>
                            <?php if ($assessment['updated_at'] && $assessment['updated_at'] !== $assessment['created_at']): ?>
                                | <strong>Updated:</strong> <?= date('d F Y H:i', strtotime($assessment['updated_at'])) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div style="padding: 2rem; text-align: center; color: #9CA3AF;">
                    <p style="margin: 0; font-size: 1rem;">
                        ℹ️ Assesmen kepatuhan belum dilakukan
                    </p>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem;">
                        Hubungi departemen kepatuhan untuk melakukan assessment
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <h3 style="margin-top: 2rem;">Riwayat Approval & Timeline</h3>
        <div class="card">
            <div class="timeline">
                <!-- Input Log -->
                <div class="timeline-item">
                    <h4>Pengajuan Dibuat</h4>
                    <p class="text-muted"><?= date('d F Y H:i', strtotime($data['tanggal_pengajuan'])) ?></p>
                    <p>Oleh: <?= htmlspecialchars($data['nama_input']) ?> (Analis)</p>
                </div>

                <?php foreach ($timeline as $t): ?>
                    <div class="timeline-item <?= $t['is_auto_skip'] ? 'skipped' : '' ?>">
                        <?php if ($t['is_auto_skip']): ?>
                            <h4 class="text-danger">Eskalasi Otomatis (Skip Level)</h4>
                            <p class="text-muted">Posisi: <?= strtoupper(str_replace('_', ' ', $t['level_approval'])) ?></p>
                            <p><em><?= htmlspecialchars($t['catatan']) ?></em></p>
                        <?php else: ?>
                            <h4>Keputusan: <?= strtoupper($t['keputusan']) ?></h4>
                            <p class="text-muted"><?= date('d F Y H:i', strtotime($t['tanggal_approval'])) ?></p>
                            <p>Oleh: <strong><?= htmlspecialchars($t['nama_approver']) ?></strong>
                                (<?= strtoupper($t['role_approver']) ?>)</p>
                            <div style="background: #F3F4F6; padding: 0.5rem; border-radius: 0.25rem; margin-top: 0.5rem;">
                                "<?= htmlspecialchars($t['catatan']) ?>"
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php
                $timeline_proses = in_array($data['status_pengajuan'] ?? '', ['proses', 'diajukan', 'kasubag', 'kabag', 'kadiv', 'direksi'], true);
                ?>
                <?php if ($timeline_proses): ?>
                    <div class="timeline-item" style="opacity: 0.5;">
                        <h4>Sedang Diproses...</h4>
                        <p>Posisi Saat Ini:
                            <strong><?= strtoupper(str_replace('_', ' ', $data['posisi_saat_ini'])) ?></strong></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Lightbox Modal -->
    <div id="lightboxModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.9); z-index:9999; align-items:center; justify-content:center; padding:2rem;">
        <div style="position:relative; max-width:90vw; max-height:90vh;">
            <button onclick="closeLightbox()" style="position:absolute; top:-2rem; right:0; background:none; border:none; color:#fff; font-size:2rem; cursor:pointer;">✕</button>
            <img id="lightboxImage" src="" style="max-width:100%; max-height:90vh; object-fit:contain;">
            <p id="lightboxCaption" style="color:#fff; text-align:center; margin-top:1rem; font-size:0.9rem;"></p>
        </div>
    </div>

    <script>
        function openLightbox(src, caption) {
            document.getElementById('lightboxImage').src = src;
            document.getElementById('lightboxCaption').textContent = caption || '';
            document.getElementById('lightboxModal').style.display = 'flex';
        }

        function closeLightbox() {
            document.getElementById('lightboxModal').style.display = 'none';
        }

        // Close lightbox when clicking outside image
        document.getElementById('lightboxModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLightbox();
            }
        });

        // Close lightbox on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });

        function hapusAgunanFoto(id) {
            if (!confirm('Yakin ingin menghapus foto ini?')) {
                return;
            }

            let formData = new FormData();
            formData.append('id_pengajuan', <?= intval($id) ?>);
            formData.append('action', 'delete_agunan_foto');
            formData.append('foto_id', id);

            fetch('save_section.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('foto_' + id).remove();
                        alert('Foto berhasil dihapus');
                        location.reload();
                    } else {
                        alert('Gagal menghapus foto: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                });
        }

        // Handle add new foto form
        document.getElementById('form_tambah_foto')?.addEventListener('submit', function(e) {
            e.preventDefault();

            let formData = new FormData(this);
            let file = document.getElementById('input_foto_baru').files[0];

            if (!file) {
                alert('Pilih file foto terlebih dahulu');
                return;
            }

            // Validate file size
            if (file.size > 5 * 1024 * 1024) {
                alert('Ukuran file terlalu besar (max 5 MB)');
                return;
            }

            // Validate file type
            if (!['image/jpeg', 'image/png'].includes(file.type)) {
                alert('Format file harus JPG atau PNG');
                return;
            }

            fetch('save_section.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Foto berhasil diupload');
                        location.reload();
                    } else {
                        alert('Gagal upload foto: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                });
        });

        function showRpcOverrideMsg(text, ok) {
            var el = document.getElementById('rpc-override-msg');
            if (!el) return;
            el.style.display = 'block';
            el.style.background = ok ? '#dcfce7' : '#fee2e2';
            el.style.color = ok ? '#166534' : '#991b1b';
            el.textContent = text;
        }

        function postRpcOverride(formData) {
            return fetch('<?= BASE_URL ?>/api/repayment_override.php', {
                method: 'POST',
                body: formData
            }).then(function (r) { return r.json(); });
        }

        document.getElementById('btn-apply-rpc-override')?.addEventListener('click', function () {
            var nilai = (document.getElementById('rpc_override_nilai')?.value || '').replace(/\D/g, '');
            var alasan = (document.getElementById('rpc_override_alasan')?.value || '').trim();
            if (!nilai || parseInt(nilai, 10) <= 0) {
                showRpcOverrideMsg('Nilai override tidak valid.', false);
                return;
            }
            if (alasan.length < 10) {
                showRpcOverrideMsg('Alasan override wajib diisi minimal 10 karakter.', false);
                return;
            }
            if (!confirm('Terapkan override repayment untuk pengajuan ini?')) return;

            var fd = new FormData();
            fd.append('csrf_token', window.__CSRF_TOKEN__ || '');
            fd.append('id_pengajuan', '<?= (int) $id ?>');
            fd.append('override_action', 'apply');
            fd.append('nilai_override', nilai);
            fd.append('alasan_override', alasan);

            postRpcOverride(fd).then(function (data) {
                if (data.success) {
                    location.reload();
                } else {
                    showRpcOverrideMsg(data.message || 'Gagal menerapkan override.', false);
                }
            }).catch(function (err) {
                showRpcOverrideMsg('Error: ' + err.message, false);
            });
        });

        document.getElementById('btn-revoke-rpc-override')?.addEventListener('click', function () {
            if (!confirm('Cabut override repayment dan kembalikan ke nilai hasil perhitungan sistem?')) return;
            var fd = new FormData();
            fd.append('csrf_token', window.__CSRF_TOKEN__ || '');
            fd.append('id_pengajuan', '<?= (int) $id ?>');
            fd.append('override_action', 'revoke');

            postRpcOverride(fd).then(function (data) {
                if (data.success) {
                    location.reload();
                } else {
                    showRpcOverrideMsg(data.message || 'Gagal mencabut override.', false);
                }
            }).catch(function (err) {
                showRpcOverrideMsg('Error: ' + err.message, false);
            });
        });
    </script>
</body>

</html>