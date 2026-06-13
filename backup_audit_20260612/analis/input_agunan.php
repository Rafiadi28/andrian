<?php
require_once __DIR__ . '/../includes/functions.php';
requireSameRole('analis');

$id_pengajuan = $_GET['id'] ?? null;

if (!$id_pengajuan) {
    die("ID Pengajuan tidak valid.");
}

// 1. Get Pengajuan Data (to know jenis_jaminan)
$stmt = $pdo->prepare("SELECT * FROM pengajuan_kredit WHERE id_pengajuan = ?");
$stmt->execute([$id_pengajuan]);
$pengajuan = $stmt->fetch();

if (!$pengajuan) {
    die("Data pengajuan tidak ditemukan.");
}

if ($pengajuan['input_by'] != $_SESSION['user_id']) {
    die("Anda tidak memiliki akses ke data ini.");
}

$jenis_jaminan = $pengajuan['jenis_jaminan'];
$success = "";
$error = "";

// 2. Handle Form Submission
if (isset($_POST['submit_agunan'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        try {
        $pdo->beginTransaction();

        if ($jenis_jaminan == 'tanah_bangunan') {
            // Delete existing if any (to allow re-input/update)
            $pdo->prepare("DELETE FROM jaminan_tanah_bangunan WHERE id_pengajuan = ?")->execute([$id_pengajuan]);

            try { $pdo->exec("ALTER TABLE jaminan_tanah_bangunan ADD COLUMN IF NOT EXISTS masa_covernote DATE NULL AFTER jenis_surat"); } catch (Exception $e) {}

            $stmt = $pdo->prepare("INSERT INTO jaminan_tanah_bangunan 
            (id_pengajuan, alamat_agunan, jenis_surat, masa_covernote, nomor_surat, atas_nama, kategori_agunan, 
             luas_tanah, harga_tanah_sppt, nilai_wajar_sppt, nilai_taksasi_sppt, nilai_likuidasi_sppt,
             harga_tanah_pasar, luas_bangunan, luas_bangunan_2, harga_bangunan_m2, 
             nilai_pasar, nilai_taksasi, nilai_likuidasi) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Calculations
            $luas_tanah = $_POST['luas_tanah'] ?? 0;
            $harga_tanah_sppt = $_POST['harga_tanah_sppt'] ?? 0;
            $nilai_wajar_sppt = $luas_tanah * $harga_tanah_sppt;

            // Taksasi/Likuidasi for SPPT is usually just informational or uses same % as market
            // But let's follow the standard pattern:
            $kategori = $_POST['kategori_agunan'] ?? '';
            $persen_taksasi = 1.0;
            if ($kategori == 'rumah_tinggal' || $kategori == 'ruko') {
                $persen_taksasi = 0.75;
            } else if ($kategori == 'sawah_tegal') {
                $persen_taksasi = 0.70;
            }

            $nilai_taksasi_sppt = $nilai_wajar_sppt * $persen_taksasi;
            $nilai_likuidasi_sppt = $nilai_taksasi_sppt * 0.70; // rough default

            // MARKET Value Calculations
            $harga_tanah_pasar = $_POST['harga_tanah_pasar'] ?? 0;
            $luas_bangunan = $_POST['luas_bangunan'] ?? 0;
            $harga_bangunan = $_POST['harga_bangunan_m2'] ?? 0;

            $val_tanah = $luas_tanah * $harga_tanah_pasar;
            $val_bangunan = $luas_bangunan * $harga_bangunan;
            $nilai_pasar_total = $val_tanah + $val_bangunan;

            $nilai_taksasi_total = ($nilai_pasar_total * $persen_taksasi) ?: ($val_tanah + $val_bangunan);
            $nilai_likuidasi_total = $nilai_taksasi_total * 0.70; // 70% of Taksasi is usually Likuidasi limit

            $masa_covernote = null;
            if (($_POST['jenis_surat'] ?? '') === 'Covernote') {
                $masa_covernote = $_POST['masa_covernote'] ?? null;
            }

            $stmt->execute([
                $id_pengajuan,
                $_POST['alamat'],
                $_POST['jenis_surat'],
                $masa_covernote,
                $_POST['nomor_surat'],
                $_POST['atas_nama'],
                $kategori,
                $luas_tanah,
                $harga_tanah_sppt,
                $nilai_wajar_sppt,
                $nilai_taksasi_sppt,
                $nilai_likuidasi_sppt,
                $harga_tanah_pasar,
                $luas_bangunan,
                0,
                $harga_bangunan,
                $nilai_pasar_total,
                $nilai_taksasi_total,
                $nilai_likuidasi_total
            ]);

        } else if ($jenis_jaminan == 'kendaraan') {
            $pdo->prepare("DELETE FROM jaminan_kendaraan WHERE id_pengajuan = ?")->execute([$id_pengajuan]);

            $nilai_pasar = $_POST['nilai_pasar'] ?? 0;
            $tahun = $_POST['tahun'] ?? 0;
            
            $currentYear = date("Y");
            $umur = ($tahun > 0) ? ($currentYear - $tahun) : 0;
            
            $persen = 0.65;
            if ($tahun > 0 && $nilai_pasar > 0) {
                if ($umur <= 5) $persen = 0.85;
                else if ($umur <= 10) $persen = 0.75;
            } else {
                $persen = 0;
            }

            $nilai_taksasi = $nilai_pasar * $persen;
            $nilai_likuidasi = $nilai_taksasi * 0.70; // Optional based on previous default

            $stmt = $pdo->prepare("INSERT INTO jaminan_kendaraan (id_pengajuan, merk, tipe, tahun_pembuatan, no_polisi, no_rangka, no_mesin, nama_pemilik, nilai_pasar, nilai_taksasi, nilai_likuidasi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $id_pengajuan,
                $_POST['merk'],
                $_POST['tipe'],
                $_POST['tahun'],
                $_POST['nopol'],
                $_POST['norangka'],
                $_POST['nomesin'],
                $_POST['bpkb_nama'],
                $nilai_pasar,
                $nilai_taksasi,
                $nilai_likuidasi
            ]);
        }

        $pdo->commit();
        // Redirect to detail or list
        header("Location: riwayat.php?msg=success_input");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        logError('input_agunan submit', ['err' => $e->getMessage()]);
        $error = 'Gagal menyimpan agunan. Silakan coba lagi atau hubungi administrator.';
    }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Input Agunan Lengkap</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .form-content {
            font-family: 'Outfit', sans-serif;
        }

        .container {
            padding-top: 1rem;
            max-width: 1000px;
        }

        .main-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2.5rem;
            margin-top: 2rem;
        }

        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 2rem;
        }
    </style>
    <script>
        function formatRupiah(num) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
        }

        function calcKendaraan() {
            let tahunElem = document.querySelector('[name=tahun]');
            let hargaElem = document.querySelector('[name=nilai_pasar]');
            
            if (!tahunElem || !hargaElem) return;
            
            let tahun = parseInt(tahunElem.value) || 0;
            let hargaPasar = parseFloat(hargaElem.value) || 0;
            
            let currentYear = new Date().getFullYear();
            let umur = (tahun > 0) ? (currentYear - tahun) : 0;
            
            let persen = 0;
            if (tahun > 0 && hargaPasar > 0) {
                if (umur <= 5) persen = 0.85;
                else if (umur <= 10) persen = 0.75;
                else persen = 0.65;
            }
            
            let nilaiTaksasi = hargaPasar * persen;
            let elPembiayaan = document.getElementById('display_nilai_pembiayaan');
            if(elPembiayaan) elPembiayaan.textContent = formatRupiah(nilaiTaksasi);
        }

        function toggleLegalitas() {
            let js = document.querySelector('[name=jenis_surat]');
            if(!js) return;
            let val = js.value;
            
            let shmTanah = document.getElementById('wrap_luas_tanah');
            if (shmTanah) {
                if (val === 'SHM') {
                    shmTanah.style.display = 'block';
                } else {
                    shmTanah.style.display = 'none';
                    let lt = document.querySelector('[name=luas_tanah]');
                    if (lt) lt.value = '';
                }
            }

            let cvnWrap = document.getElementById('wrap_covernote');
            if (cvnWrap) {
                if (val === 'Covernote') {
                    cvnWrap.style.display = 'block';
                } else {
                    cvnWrap.style.display = 'none';
                    let mc = document.querySelector('[name=masa_covernote]');
                    if(mc) mc.value = '';
                }
            }
            
            // Only call calcTanah if on tanah_bangunan form (check if element exists)
            if (document.querySelector('[name=luas_tanah]')) {
                calcTanah();
            }
        }

        function calcTanah() {
            let luasT = parseFloat(document.querySelector('[name=luas_tanah]')?.value) || 0;
            let hargaT = parseFloat(document.querySelector('[name=harga_tanah_pasar]')?.value) || 0;
            let luasB = parseFloat(document.querySelector('[name=luas_bangunan]')?.value) || 0;
            let hargaB = parseFloat(document.querySelector('[name=harga_bangunan_m2]')?.value) || 0;

            let total = (luasT * hargaT) + (luasB * hargaB);

            let katElem = document.querySelector('[name=kategori_agunan]');
            let kat = katElem ? katElem.value : 'rumah_tinggal';
            let persen = 1.0;
            if (kat === 'rumah_tinggal' || kat === 'ruko') {
                persen = 0.75;
            } else if (kat === 'sawah_tegal') {
                persen = 0.70;
            }

            let taksasi = total * persen;
            let likuidasi = taksasi * 0.70;

            let elPasar = document.getElementById('display_nilai_pasar');
            if(elPasar) elPasar.textContent = formatRupiah(total);
            let elTaksasi = document.getElementById('display_nilai_taksasi');
            if(elTaksasi) elTaksasi.textContent = formatRupiah(taksasi);
            let elLikuidasi = document.getElementById('display_nilai_likuidasi');
            if(elLikuidasi) elLikuidasi.textContent = formatRupiah(likuidasi);
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Initialize form based on type (tanah_bangunan or kendaraan)
            if (document.querySelector('[name=jenis_surat]')) {
                // tanah_bangunan form
                toggleLegalitas();
                let js = document.querySelector('[name=jenis_surat]');
                if(js) js.addEventListener('change', toggleLegalitas);
            }
            
            if (document.querySelector('[name=tahun]')) {
                // kendaraan form
                let th = document.querySelector('[name=tahun]');
                if(th) th.addEventListener('input', calcKendaraan);
            }
        });
    </script>
</head>

<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container form-content">
        <h2>Lengkapi Data Agunan</h2>
        <p class="text-muted">Langkah 2 dari 2: Detail Jaminan (<?= ucwords(str_replace('_', ' ', $jenis_jaminan)) ?>)
        </p>

        <div class="main-card">
            <?php if ($error): ?>
                <div style="color:#991b1b; font-weight:bold; margin-bottom:1.5rem; padding:1rem; background:#fee2e2; border-radius:8px; border-left:4px solid #dc2626;">
                    ❌ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="color:#166534; font-weight:bold; margin-bottom:1.5rem; padding:1rem; background:#dcfce7; border-radius:8px; border-left:4px solid #16a34a;">
                    ✅ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($jenis_jaminan == 'tanah_bangunan'): ?>
                    <h3 style="margin-bottom:1.5rem; border-bottom:1px solid #eee; padding-bottom:0.5rem;">Legalitas & Fisik
                    </h3>

                    <div class="grid-2">
                        <div class="form-group"><label>Kategori Agunan</label>
                            <select name="kategori_agunan" onchange="calcTanah()">
                                <option value="rumah_tinggal">Tanah dan Bangunan</option>
                                <option value="ruko">Ruko / Toko</option>
                                <option value="sawah_tegal">Sawah / Tegal</option>
                                <option value="tanah_kosong">Tanah Kosong</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Jenis Surat</label>
                            <select name="jenis_surat" id="jenis_surat">
                                <option value="SHM">SHM (Sertifikat Hak Milik)</option>
                                <option value="SHGB">SHGB</option>
                                <option value="AJB">AJB</option>
                                <option value="Letter C">Letter C / Petok D</option>
                                <option value="Covernote">Covernote</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group"><label>Nomor Surat</label><input type="text" name="nomor_surat" required>
                        </div>
                        <div class="form-group"><label>Atas Nama Sertifikat</label><input type="text" name="atas_nama"
                                required></div>
                    </div>

                    <div class="form-group" id="wrap_covernote" style="display:none;"><label>Masa Berlaku Covernote (Tanggal)</label><input type="date" name="masa_covernote"></div>

                    <div class="form-group"><label>Alamat Lengkap Agunan</label><textarea name="alamat" required></textarea>
                    </div>

                    <h3 style="margin:2rem 0 1rem; border-bottom:1px solid #eee; padding-bottom:0.5rem;">Penilaian
                        (Appraisal)</h3>

                    <div class="grid-2">
                        <div class="form-group" id="wrap_luas_tanah"><label>Luas Tanah (m2)</label><input type="number" name="luas_tanah"
                                oninput="calcTanah()"></div>
                        <div class="form-group"><label>Harga Tanah Pasar /m2 (Rp)</label><input type="number"
                                name="harga_tanah_pasar" oninput="calcTanah()" required></div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group"><label>Luas Bangunan (m2)</label><input type="number" name="luas_bangunan"
                                oninput="calcTanah()" value="0"></div>
                        <div class="form-group"><label>Harga Bangunan /m2 (Rp)</label><input type="number"
                                name="harga_bangunan_m2" oninput="calcTanah()" value="0"></div>
                    </div>

                    <div class="form-group"><label>Harga Tanah (Versi SPPT) - Optional</label><input type="number"
                            name="harga_tanah_sppt" value="0"></div>

                    <div style="background:#f0abfc; padding:1.5rem; border-radius:8px; margin-top:1.5rem; color:#86198f;">
                        <h4 style="margin:0 0 1rem 0;">Estimasi Nilai Agunan</h4>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                            <span>Nilai Pasar (Market Value):</span>
                            <strong id="display_nilai_pasar" style="font-size:1.1rem;">Rp 0</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                            <span>Nilai Pembiayaan / Taksasi (Safety):</span>
                            <strong id="display_nilai_taksasi" style="font-size:1.1rem;">Rp 0</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span>Nilai Likuidasi (Quick Sale):</span>
                            <strong id="display_nilai_likuidasi" style="font-size:1.1rem;">Rp 0</strong>
                        </div>
                    </div>

                <?php elseif ($jenis_jaminan == 'kendaraan'): ?>
                    <div class="grid-2">
                        <div class="form-group"><label>Merk Kendaraan</label><input type="text" name="merk" required
                                placeholder="Contoh: Honda, Toyota"></div>
                        <div class="form-group"><label>Tipe / Model</label><input type="text" name="tipe" required
                                placeholder="Contoh: Vario 125, Avanza G"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>Tahun Pembuatan</label><input type="number" name="tahun" required>
                        </div>
                        <div class="form-group"><label>Nomor Polisi</label><input type="text" name="nopol" required></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>Nomor Rangka</label><input type="text" name="norangka" required>
                        </div>
                        <div class="form-group"><label>Nomor Mesin</label><input type="text" name="nomesin" required></div>
                    </div>
                    <div class="form-group"><label>Nama Pemilik di BPKB</label><input type="text" name="bpkb_nama" required>
                    </div>

                    <hr style="margin:2rem 0;">
                    <div class="form-group"><label>Nilai Pasar (Rp)</label><input type="number" name="nilai_pasar" oninput="calcKendaraan()" required>
                    </div>
                    <div style="background:#f0abfc; padding:1.5rem; border-radius:8px; margin-top:1.5rem; color:#86198f;">
                        <h4 style="margin:0 0 1rem 0;">Estimasi Pembiayaan Kendaraan</h4>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                            <span>Nilai Pembiayaan / Taksasi:</span>
                            <strong id="display_nilai_pembiayaan" style="font-size:1.1rem;">Rp 0</strong>
                        </div>
                    </div>

                <?php endif; ?>

                <button type="submit" name="submit_agunan" class="btn-submit">SIMPAN & SELESAI</button>
            </form>
        </div>
    </div>
</body>

</html>