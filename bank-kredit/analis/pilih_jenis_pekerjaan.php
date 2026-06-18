<?php
/**
 * Halaman gate: wajib pilih jenis pekerjaan sebelum form analisa.
 * Variabel opsional: $gate_error (string)
 */
if (!isset($gate_error)) {
    $gate_error = '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Jenis Pekerjaan — Input Analisa Kredit</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <div class="container">
        <div class="gate-wrapper">
            <div class="gate-header">
                <h1>Pilih Jenis Debitur</h1>
                <p>Pilih kategori pekerjaan debitur. Form disesuaikan otomatis berdasarkan pilihan ini.</p>
            </div>

            <?php if ($gate_error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($gate_error) ?></div>
            <?php endif; ?>

            <div class="tip-box">
                <strong>Catatan:</strong> Pilih jenis debitur yang sesuai agar perhitungan analisa relevan. Jenis ini tidak dapat diubah setelah data tersimpan.
            </div>

            <div class="gate-category">
                <div class="gate-category-label">Kredit Usaha / Umum</div>
                <div class="jenis-grid">
                    <a href="input.php?jenis=umum" class="jenis-card">
                        <div class="jenis-icon" aria-hidden="true">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                        <div class="jenis-info">
                            <div class="jenis-name">Umum / Wiraswasta</div>
                            <div class="jenis-desc">Pedagang, pengusaha, UMKM, karyawan swasta</div>
                        </div>
                        <span class="arrow" aria-hidden="true">→</span>
                    </a>
                </div>
            </div>

            <div class="gate-category">
                <div class="gate-category-label">Pegawai Pemerintah</div>
                <div class="jenis-grid">
                    <a href="input.php?jenis=pppk" class="jenis-card">
                        <div class="jenis-icon" aria-hidden="true">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                        </div>
                        <div class="jenis-info">
                            <div class="jenis-name">PPPK</div>
                            <div class="jenis-desc">Pegawai Pemerintah dengan Perjanjian Kerja</div>
                        </div>
                        <span class="arrow" aria-hidden="true">→</span>
                    </a>
                    <a href="input.php?jenis=perangkat_desa" class="jenis-card">
                        <div class="jenis-icon" aria-hidden="true">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        </div>
                        <div class="jenis-info">
                            <div class="jenis-name">Perangkat Desa</div>
                            <div class="jenis-desc">Kepala Desa, Sekdes, Kaur, BPD</div>
                        </div>
                        <span class="arrow" aria-hidden="true">→</span>
                    </a>
                </div>
            </div>

            <div class="gate-category">
                <div class="gate-category-label">Kredit Khusus</div>
                <div class="jenis-grid">
                    <a href="input.php?jenis=kpr" class="jenis-card">
                        <div class="jenis-icon" aria-hidden="true">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        </div>
                        <div class="jenis-info">
                            <div class="jenis-name">KPR</div>
                            <div class="jenis-desc">Kredit Pemilikan Rumah</div>
                        </div>
                        <span class="arrow" aria-hidden="true">→</span>
                    </a>
                    <a href="input.php?jenis=kretamas" class="jenis-card">
                        <div class="jenis-icon" aria-hidden="true">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div class="jenis-info">
                            <div class="jenis-name">KRETAMAS</div>
                            <div class="jenis-desc">Kredit Emas</div>
                        </div>
                        <span class="arrow" aria-hidden="true">→</span>
                    </a>
                    <a href="input.php?jenis=cashcolateral" class="jenis-card">
                        <div class="jenis-icon" aria-hidden="true">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <div class="jenis-info">
                            <div class="jenis-name">CASHCOLATERAL</div>
                            <div class="jenis-desc">Kredit dengan jaminan deposito / tabungan</div>
                        </div>
                        <span class="arrow" aria-hidden="true">→</span>
                    </a>
                </div>
            </div>

            <div class="gate-footer">
                <a href="dashboard.php">Kembali ke Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
