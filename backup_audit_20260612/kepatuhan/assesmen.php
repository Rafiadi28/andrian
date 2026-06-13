<?php
$my_role = 'kepatuhan';
require_once __DIR__ . '/../includes/functions.php';
requireSameRole('kepatuhan');

$action = $_GET['action'] ?? 'list';

// Note: Saving now handled via API endpoint (api/save_assessment_kepatuhan.php) via AJAX
// This maintains consistency with analis assessment form
?>
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Assesmen Kepatuhan</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;700&display=swap" rel="stylesheet">
    <style>
        .memo-container {
            background: #fff;
            padding: 3rem;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 1000px;
            margin: 0 auto;
            color: #333;
            line-height: 1.4;
        }
        .memo-header {
            margin-bottom: 2rem;
            border-bottom: 3px double #000;
            padding-bottom: 1rem;
        }
        .memo-title {
            text-align: center;
            font-family: 'Times New Roman', serif;
            font-weight: bold;
            font-size: 1.5rem;
            text-decoration: underline;
            margin-bottom: 0.5rem;
        }
        .memo-meta table {
            width: 100%;
            border: none;
        }
        .memo-meta td {
            padding: 4px 0;
            vertical-align: middle;
        }
        .memo-meta td:first-child {
            width: 100px;
            font-weight: bold;
        }
        .memo-meta input {
            width: 100%;
            padding: 4px;
            border: 1px solid #ccc;
        }
        .memo-body h3 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            margin-top: 2rem;
            font-weight: bold;
            background: #f1f5f9;
            padding: 0.5rem;
            border-left: 4px solid #1e40af;
        }
        .form-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .form-table td {
            vertical-align: top;
        }
        .form-table td:first-child {
            width: 250px;
            font-weight: 500;
            padding-top: 6px;
        }
        .form-table input,
        .form-table textarea,
        .form-table select {
            width: 100%;
            padding: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
        }
        .checklist-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin-top: 1rem;
        }
        .checklist-table th,
        .checklist-table td {
            border: 1px solid #94a3b8;
            padding: 6px;
            vertical-align: middle;
        }
        .checklist-table th {
            text-align: center;
            background: #e2e8f0;
        }
        .checklist-table input[type="text"] {
            width: 100%;
            padding: 4px;
            border: 1px solid #cbd5e1;
        }
        .btn-save {
            background: #1e40af;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 2rem;
            transition: background 0.2s;
        }
        .btn-save:hover {
            background: #1e3a8a;
        }

        @media print {
            .sidebar, .navbar, .btn-save, .sidebar-toggle, .sidebar-overlay, .btn-print, .nav-links, .user-profile, .header-action, .mb-3 {
                display: none !important;
            }
            .container {
                padding: 0; margin: 0; width: 100%; max-width: 100%;
            }
            .memo-container {
                box-shadow: none; padding: 0; border: none; margin: 0; width: 100%;
            }
            body {
                background: #fff;
                -webkit-print-color-adjust: exact;
            }
            input, textarea, select {
                border: none !important;
                background: transparent !important;
                padding: 0 !important;
                resize: none;
                width: auto;
            }
            textarea {
                height: auto; overflow: hidden;
            }
            .checklist-table th {
                background-color: #f0f0f0 !important; color: #000 !important;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="main-content">
    <div class="container">
        <?php if ($action === 'list'): ?>
            <div class="header-action" style="margin-bottom: 2rem;">
                <h1>Daftar Pengajuan untuk Assesmen Kepatuhan</h1>
            </div>
            
            <div class="card p-4">
                <table class="table table-striped" style="width: 100%; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #ddd;">
                            <th style="padding: 10px;">ID</th>
                            <th style="padding: 10px;">Nama Debitur</th>
                            <th style="padding: 10px;">Pekerjaan</th>
                            <th style="padding: 10px;">Plafond</th>
                            <th style="padding: 10px;">Status</th>
                            <th style="padding: 10px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("SELECT id_pengajuan, nama_debitur, jenis_pekerjaan, jumlah_kredit, status_pengajuan FROM pengajuan_kredit WHERE status_pengajuan != 'draft' ORDER BY id_pengajuan DESC");
                        while($row = $stmt->fetch()):
                        ?>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 10px;"><?= $row['id_pengajuan'] ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($row['nama_debitur']) ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($row['jenis_pekerjaan']) ?></td>
                            <td style="padding: 10px;"><?= formatRupiah($row['jumlah_kredit']) ?></td>
                            <td style="padding: 10px;"><span class="badge status-<?= $row['status_pengajuan'] ?>"><?= strtoupper($row['status_pengajuan']) ?></span></td>
                            <td style="padding: 10px;">
                                <a href="?action=form&id=<?= $row['id_pengajuan'] ?>" class="btn btn-sm btn-primary" style="padding: 6px 12px; border-radius: 4px; text-decoration: none;">Buka Assesmen</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($action === 'form' && isset($_GET['id'])): 
            $id = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT p.*, u.nama as nama_analis FROM pengajuan_kredit p LEFT JOIN users u ON p.input_by = u.id_user WHERE p.id_pengajuan = ?");
            $stmt->execute([$id]);
            $p = $stmt->fetch();

            if(!$p) {
                echo "<p>Pengajuan tidak ditemukan.</p>";
                exit;
            }

            $has_assessment = false;
            $checklist = [];
            $fasilitas = [];
            $cat_existing = [];
            $kesimpulan = '';
            $rekomendasi = '';
            $marketing = '';
            $tanggal = date('Y-m-d');
            
            $sa = $pdo->prepare("SELECT * FROM assessment_kepatuhan WHERE id_pengajuan = ?");
            $sa->execute([$id]);
            if($a = $sa->fetch()) {
                $has_assessment = true;
                $checklist = json_decode($a['checklist_data'], true) ?: [];
                $fasilitas = json_decode($a['fasilitas_existing'], true) ?: [];
                $cat_existing = json_decode($a['catatan_existing'], true) ?: [];
                $kesimpulan = $a['kesimpulan'] ?? '';
                $rekomendasi = $a['rekomendasi'] ?? '';
                $marketing = $a['marketing'] ?? '';
                $tanggal = $a['tanggal_assessment'];
            }

            function cVal($checklist, $key, $choice, $default = false) {
                if (isset($checklist[$key]['val'])) {
                    return $checklist[$key]['val'] === $choice ? 'checked' : '';
                }
                return $default ? 'checked' : '';
            }
            function cKet($checklist, $key, $defaultStr = '') {
                return htmlspecialchars($checklist[$key]['ket'] ?? $defaultStr);
            }

            function checklistRow($checklist, $no, $label, $key, $default_ket = '')
            {
                $na = cVal($checklist, $key, 'na');
                $nc = cVal($checklist, $key, 'not_comply');
                $cm = cVal($checklist, $key, 'comply', true); // default comply
                $ket = cKet($checklist, $key, $default_ket);
                
                // Escape output untuk prevent XSS
                $no_safe = htmlspecialchars($no, ENT_QUOTES, 'UTF-8');
                $label_safe = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
                $key_safe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');

                echo "<tr>
                    <td>" . $no_safe . "</td>
                    <td>" . $label_safe . "</td>
                    <td style='text-align:center;'><input type='radio' name='check[" . $key_safe . "]' value='na' " . $na . "></td>
                    <td style='text-align:center;'><input type='radio' name='check[" . $key_safe . "]' value='not_comply' " . $nc . "></td>
                    <td style='text-align:center;'><input type='radio' name='check[" . $key_safe . "]' value='comply' " . $cm . "></td>
                    <td><input type='text' name='ket[" . $key_safe . "]' value='" . $ket . "'></td>
                </tr>";
            }
        ?>

        <a href="?action=list" class="btn btn-secondary mb-3" style="display:inline-block; padding: 8px 16px; margin-bottom: 20px; text-decoration: none; border-radius: 4px;">&larr; Kembali ke Daftar</a>

        <form method="POST" action="javascript:void(0);" class="memo-container" id="assessmentForm" onsubmit="submitAssessment(event)">
            <input type="hidden" name="action" value="<?= $has_assessment ? 'update' : 'create' ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id_pengajuan" value="<?= $id ?>">
            
            <div class="memo-header">
                <div class="memo-title">MEMO INTERNAL</div>
                <div class="memo-meta">
                    <table>
                        <tr>
                            <td>Nomor</td>
                            <td><input type="text" name="nomor_memo" value="137/60557/GRG/IX/2025"></td>
                        </tr>
                        <tr>
                            <td>Kepada</td>
                            <td><input type="text" name="kepada_memo" value="Komite Kredit"></td>
                        </tr>
                        <tr>
                            <td>Dari</td>
                            <td><input type="text" name="dari_memo" value="PE Kepatuhan, Manrisk, APU PPT & PPPSPM"></td>
                        </tr>
                        <tr>
                            <td>Tanggal</td>
                            <td><input type="date" name="tanggal_memo" value="<?= htmlspecialchars($tanggal) ?>"></td>
                        </tr>
                        <tr>
                            <td>Perihal</td>
                            <td><input type="text" name="perihal_memo" value="Compliance Checklist"></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="memo-body">
                <h3>1. Data Usulan Kredit</h3>
                <table class="form-table">
                    <tr>
                        <td>Nama Calon Debitur</td>
                        <td><input type="text" value="<?= htmlspecialchars($p['nama_debitur']) ?>" readonly></td>
                    </tr>
                    <tr>
                        <td>No KTP</td>
                        <td><input type="text" value="<?= htmlspecialchars($p['nik']) ?>" readonly></td>
                    </tr>
                    <tr>
                        <td>Nama Pasangan</td>
                        <td><input type="text" value="<?= htmlspecialchars($p['nama_pasangan'] ?? '-') ?>" readonly></td>
                    </tr>
                    <tr>
                        <td>Jenis Debitur</td>
                        <td><input type="text" value="Perorangan" readonly></td>
                    </tr>
                    <tr>
                        <td>Jenis Kredit</td>
                        <td><input type="text" value="<?= htmlspecialchars($p['jenis_kredit'] ?? 'Pinjaman Umum (Kredit Modal Usaha)') ?>" readonly></td>
                    </tr>
                    <tr>
                        <td>Alamat</td>
                        <td><textarea rows="2" readonly><?= htmlspecialchars($p['alamat_domisili'] ?? '-') ?></textarea></td>
                    </tr>
                    <tr>
                        <td>Pekerjaan/Usaha</td>
                        <td><input type="text" value="<?= htmlspecialchars($p['pekerjaan'] ?? '-') ?>" readonly></td>
                    </tr>
                    <tr>
                        <td>Plafon (Rp)</td>
                        <td><input type="text" value="<?= formatRupiah($p['jumlah_kredit']) ?>" readonly></td>
                    </tr>
                    <tr>
                        <td>Tujuan</td>
                        <td><textarea rows="2" readonly><?= htmlspecialchars($p['tujuan_kredit'] ?? '-') ?></textarea></td>
                    </tr>
                    <tr>
                        <td>Jangka Waktu (Bulan)</td>
                        <td><input type="text" value="<?= htmlspecialchars($p['jangka_waktu'] ?? '-') ?>" readonly></td>
                    </tr>
                    <tr>
                        <td>Suku Bunga</td>
                        <td><input type="text" value="<?= htmlspecialchars($p['suku_bunga'] ?? '14') ?>% Anuitas" readonly></td>
                    </tr>
                    <tr>
                        <td>Marketing</td>
                        <td><input type="text" name="marketing" value="<?= htmlspecialchars($marketing) ?>"></td>
                    </tr>
                    <tr>
                        <td>Analis</td>
                        <td><input type="text" value="<?= htmlspecialchars($p['nama_analis'] ?? '-') ?>" readonly></td>
                    </tr>
                </table>

                <h3>2. Compliance Checklist</h3>
                <table class="checklist-table">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width:30px;">No</th>
                            <th rowspan="2">Keterangan</th>
                            <th colspan="3">Checklist</th>
                            <th rowspan="2">Keterangan / Catatan</th>
                        </tr>
                        <tr>
                            <th style="font-size:0.8em">NA</th>
                            <th style="font-size:0.8em">Not Comply</th>
                            <th style="font-size:0.8em">Comply</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" style="background:#f1f5f9; font-weight:bold;">1. Kesesuaian Kriteria Debitur</td>
                        </tr>
                        <?php checklistRow($checklist, '', 'a. Kesesuaian jenis debitur', 'krit_jenis', 'Perorangan'); ?>
                        <?php checklistRow($checklist, '', 'b. Kewarganegaraan Debitur WNI', 'krit_wni', 'WNI'); ?>
                        <?php checklistRow($checklist, '', 'c. Kolektibilitas calon debitur', 'krit_kol', 'Lancar'); ?>

                        <tr>
                            <td colspan="6" style="background:#f1f5f9; font-weight:bold;">2. Kesesuaian Usaha Calon Debitur</td>
                        </tr>
                        <?php checklistRow($checklist, '', 'a. Usaha bukan termasuk yang dihindari', 'usaha_pkpb'); ?>

                        <tr>
                            <td colspan="6" style="background:#f1f5f9; font-weight:bold;">3. Prosedur Pengajuan Kredit</td>
                        </tr>
                        <tr><td colspan="6" style="font-style:italic;">a. Kelengkapan Dokumen Permohonan</td></tr>
                        <?php checklistRow($checklist, '', 'Formulir permohonan kredit', 'dok_form', 'Terlampir'); ?>
                        <?php checklistRow($checklist, '', 'KTP calon debitur', 'dok_ktp', 'Terlampir'); ?>
                        <?php checklistRow($checklist, '', 'KTP pasangan debitur', 'dok_ktp_pas', 'Terlampir'); ?>
                        <?php checklistRow($checklist, '', 'Kartu Keluarga', 'dok_kk', 'Terlampir'); ?>
                        <?php checklistRow($checklist, '', 'Akta nikah', 'dok_nikah', 'Terlampir'); ?>
                        <?php checklistRow($checklist, '', 'Foto debitur dan pasangan', 'dok_foto', 'Terlampir'); ?>

                        <tr><td colspan="6" style="font-style:italic;">b. Legalitas Usaha</td></tr>
                        <?php checklistRow($checklist, '', 'NIB/TDP/SIUP/Ijin lainnya', 'leg_nib', 'Terlampir'); ?>
                        <?php checklistRow($checklist, '', 'NPWP', 'leg_npwp', 'Terlampir'); ?>

                        <tr><td colspan="6" style="font-style:italic;">c. Dokumen Keuangan</td></tr>
                        <?php checklistRow($checklist, '', 'Laporan keuangan/pembukuan', 'keu_lap', 'Terlampir'); ?>
                        <?php checklistRow($checklist, '', 'Rekening koran', 'keu_rek', 'Terlampir'); ?>

                        <tr><td colspan="6" style="font-style:italic;">d. Dokumen Agunan</td></tr>
                        <?php checklistRow($checklist, '', 'Sertifikat (SHM/SHGB)', 'ag_shm', 'Terlampir'); ?>
                        <?php checklistRow($checklist, '', 'FC SPPT', 'ag_sppt', 'Terlampir'); ?>
                        <?php checklistRow($checklist, '', 'Surat Kuasa', 'ag_kuasa', 'Terlampir'); ?>
                        <?php checklistRow($checklist, '', 'Ket Harga Tanah / NJOP', 'ag_njop', 'Terlampir'); ?>
                        <?php checklistRow($checklist, '', 'Bukti Cek SHM', 'ag_cek', 'Belum Terlampir'); ?>
                        <?php checklistRow($checklist, '', 'Foto usaha & tempat tinggal', 'ag_foto', 'Terlampir'); ?>
                        <?php checklistRow($checklist, '', 'Laporan Kunjungan', 'ag_visit', 'Terlampir'); ?>

                        <tr><td colspan="6" style="background:#f1f5f9; font-weight:bold;">4-7. Analisa & BMPK</td></tr>
                        <?php checklistRow($checklist, '4', 'Kesesuaian BMPK', 'bmpk', 'Pihak tidak terkait'); ?>
                        <?php checklistRow($checklist, '5', 'Kesesuaian Analisa Kredit', 'an_krd', 'Terlampir'); ?>
                        <?php checklistRow($checklist, '6', 'Kesesuaian Analisa Agunan', 'an_ag', 'Sesuai limit'); ?>
                        <?php checklistRow($checklist, '7', 'Kesesuaian Produk Kredit', 'prod', 'Sesuai'); ?>
                    </tbody>
                </table>

                <h3>3. Fasilitas Kredit Existing</h3>
                <table class="checklist-table" id="fasTable">
                    <tr>
                        <th>No Rekening</th>
                        <th>Tgl Akad</th>
                        <th>Jt Tempo</th>
                        <th>Kol</th>
                        <th>Plafond</th>
                        <th>Saldo</th>
                        <th class="btn-print">Aksi</th>
                    </tr>
                    <?php if(empty($fasilitas)): ?>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <tr>
                                <td><input type="text" name="fasilitas_rek[]"></td>
                                <td><input type="date" name="fasilitas_akad[]"></td>
                                <td><input type="date" name="fasilitas_jtempo[]"></td>
                                <td><input type="text" name="fasilitas_kol[]"></td>
                                <td><input type="number" name="fasilitas_plafond[]"></td>
                                <td><input type="number" name="fasilitas_saldo[]"></td>
                                <td class="btn-print text-center"><button type="button" onclick="this.closest('tr').remove()" style="color:red; background:none; border:none; cursor:pointer;">&times;</button></td>
                            </tr>
                        <?php endfor; ?>
                    <?php else: ?>
                        <?php foreach($fasilitas as $f): ?>
                            <tr>
                                <td><input type="text" name="fasilitas_rek[]" value="<?= htmlspecialchars($f['rek']) ?>"></td>
                                <td><input type="date" name="fasilitas_akad[]" value="<?= htmlspecialchars($f['tgl']) ?>"></td>
                                <td><input type="date" name="fasilitas_jtempo[]" value="<?= htmlspecialchars($f['jt']) ?>"></td>
                                <td><input type="text" name="fasilitas_kol[]" value="<?= htmlspecialchars($f['kol']) ?>"></td>
                                <td><input type="number" name="fasilitas_plafond[]" value="<?= htmlspecialchars($f['plafond']) ?>"></td>
                                <td><input type="number" name="fasilitas_saldo[]" value="<?= htmlspecialchars($f['saldo']) ?>"></td>
                                <td class="btn-print text-center"><button type="button" onclick="this.closest('tr').remove()" style="color:red; background:none; border:none; cursor:pointer;">&times;</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
                <button type="button" class="btn-print" onclick="addFas()" style="margin-top:8px; padding:4px 8px; font-size:12px; cursor:pointer;">+ Tambah Baris</button>

                <h4 style="margin-top:1rem;">Catatan Compliance (Existing)</h4>
                <table class="checklist-table">
                    <thead>
                        <tr>
                            <th>Keterangan</th>
                            <th>NA</th>
                            <th>Not Comply</th>
                            <th>Comply</th>
                            <th>Ket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $opts = ['dok' => 'Kelengkapan Dokumen', 'putus' => 'Catatan Pemutus', 'ikat' => 'Pengikatan Kredit'];
                        foreach ($opts as $k => $l) {
                            $na = cVal($cat_existing, $k, 'na');
                            $nc = cVal($cat_existing, $k, 'not_comply');
                            $cm = cVal($cat_existing, $k, 'comply', true);
                            $ket = cKet($cat_existing, $k, '');
                            
                            echo "<tr>
                                <td>$l</td>
                                <td style='text-align:center;'><input type='radio' name='note_check[$k]' value='na' $na></td>
                                <td style='text-align:center;'><input type='radio' name='note_check[$k]' value='not_comply' $nc></td>
                                <td style='text-align:center;'><input type='radio' name='note_check[$k]' value='comply' $cm></td>
                                <td><input type='text' name='note_ket[$k]' value='$ket'></td>
                            </tr>";
                        }
                        ?>
                    </tbody>
                </table>

                <h3>4. Kesimpulan</h3>
                <textarea name="kesimpulan" rows="5"><?= htmlspecialchars($kesimpulan) ?></textarea>

                <h3>5. Rekomendasi</h3>
                <textarea name="rekomendasi" rows="5"><?= htmlspecialchars($rekomendasi) ?></textarea>

                <div style="display:flex; gap:1rem; margin-top:2rem;">
                    <button type="submit" class="btn-save">SIMPAN ASSESSMENT</button>
                    <button type="button" class="btn-save btn-print" style="background:#4b5563;" onclick="window.print()">CETAK ASSESSMENT</button>
                </div>
            </div>
        </form>

        <script>
            async function submitAssessment(e) {
                e.preventDefault();
                const form = document.getElementById('assessmentForm');
                const formData = new FormData(form);
                
                // Add loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Menyimpan...';

                try {
                    const response = await fetch('../api/save_assessment_kepatuhan.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('Assessment berhasil disimpan!');
                        window.location.href = '?action=form&id=' + formData.get('id_pengajuan');
                    } else {
                        alert('Error: ' + result.message);
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                } catch (error) {
                    alert('Terjadi kesalahan: ' + error.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }

            function addFas() {
                var tbody = document.querySelector('#fasTable tbody');
                var tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="text" name="fasilitas_rek[]"></td>
                    <td><input type="date" name="fasilitas_akad[]"></td>
                    <td><input type="date" name="fasilitas_jtempo[]"></td>
                    <td><input type="text" name="fasilitas_kol[]"></td>
                    <td><input type="number" name="fasilitas_plafond[]"></td>
                    <td><input type="number" name="fasilitas_saldo[]"></td>
                    <td class="btn-print text-center"><button type="button" onclick="this.closest('tr').remove()" style="color:red; background:none; border:none; cursor:pointer;">&times;</button></td>
                `;
                tbody.appendChild(tr);
            }
        </script>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
