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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #f1f5f9;
        }

        .gate-wrapper {
            max-width: 720px;
            margin: 2.5rem auto;
            padding: 0 1rem;
        }

        .gate-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .gate-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 0.5rem 0;
        }

        .gate-header p {
            color: #64748b;
            font-size: 0.95rem;
            margin: 0;
            line-height: 1.5;
        }

        .gate-category {
            margin-bottom: 1.75rem;
        }

        .gate-category-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .gate-category-label .cat-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .jenis-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        @media (max-width: 600px) {
            .jenis-grid {
                grid-template-columns: 1fr;
            }
        }

        .jenis-card {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.15rem 1.25rem;
            border-radius: 12px;
            text-decoration: none;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .jenis-card:hover {
            border-color: #2563eb;
            background: #f0f7ff;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.12);
            transform: translateY(-2px);
        }

        .jenis-card:active {
            transform: translateY(0);
        }

        .jenis-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .jenis-info {
            flex: 1;
            min-width: 0;
        }

        .jenis-info .jenis-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.95rem;
            margin-bottom: 0.2rem;
        }

        .jenis-info .jenis-desc {
            font-size: 0.8rem;
            color: #94a3b8;
            line-height: 1.35;
        }

        .jenis-card .arrow {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #cbd5e1;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .jenis-card:hover .arrow {
            color: #2563eb;
            right: 0.75rem;
        }

        /* Category color accents */
        .cat-umum .cat-dot { background: #2563eb; }
        .cat-pegawai .cat-dot { background: #059669; }
        .cat-khusus .cat-dot { background: #d97706; }

        .jenis-card.card-umum .jenis-icon { background: #eff6ff; color: #2563eb; }
        .jenis-card.card-pegawai .jenis-icon { background: #ecfdf5; color: #059669; }
        .jenis-card.card-khusus .jenis-icon { background: #fffbeb; color: #d97706; }

        .jenis-card.card-umum:hover { border-color: #2563eb; background: #eff6ff; }
        .jenis-card.card-pegawai:hover { border-color: #059669; background: #ecfdf5; }
        .jenis-card.card-khusus:hover { border-color: #d97706; background: #fffbeb; }

        .err {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.85rem 1.15rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid #fca5a5;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .gate-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .gate-footer a {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.6rem 1.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.15s;
        }

        .gate-footer a:hover {
            color: #1e293b;
            border-color: #cbd5e1;
            background: #fff;
        }

        .tip-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.5;
        }

        .tip-box .tip-icon {
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: 1px;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <div class="container">
        <div class="gate-wrapper">
            <div class="gate-header">
                <h1>Pilih Jenis Debitur</h1>
                <p>Pilih kategori pekerjaan debitur untuk memulai input analisa kredit. Form disesuaikan secara otomatis berdasarkan pilihan ini.</p>
            </div>

            <?php if ($gate_error !== ''): ?>
                <div class="err">⚠ <?= htmlspecialchars($gate_error) ?></div>
            <?php endif; ?>

            <div class="tip-box">
                <span class="tip-icon">💡</span>
                <div><strong>Tips:</strong> Pilih jenis debitur yang sesuai agar form dan perhitungan analisa (cashflow, repayment capacity) relevan. Jenis ini tidak dapat diubah setelah data tersimpan.</div>
            </div>

            <!-- KATEGORI: UMUM / WIRASWASTA -->
            <div class="gate-category">
                <div class="gate-category-label cat-umum">
                    <span class="cat-dot"></span>
                    Kredit Usaha / Umum
                </div>
                <div class="jenis-grid">
                    <a href="input.php?jenis=umum" class="jenis-card card-umum">
                        <div class="jenis-icon">🏪</div>
                        <div class="jenis-info">
                            <div class="jenis-name">Umum / Wiraswasta</div>
                            <div class="jenis-desc">Pedagang, pengusaha, pelaku UMKM, karyawan swasta</div>
                        </div>
                        <span class="arrow">→</span>
                    </a>
                </div>
            </div>

            <!-- KATEGORI: PEGAWAI / ASN -->
            <div class="gate-category">
                <div class="gate-category-label cat-pegawai">
                    <span class="cat-dot"></span>
                    Pegawai Pemerintah
                </div>
                <div class="jenis-grid">
                    <a href="input.php?jenis=pppk" class="jenis-card card-pegawai">
                        <div class="jenis-icon">🏛️</div>
                        <div class="jenis-info">
                            <div class="jenis-name">PPPK</div>
                            <div class="jenis-desc">Pegawai Pemerintah dengan Perjanjian Kerja</div>
                        </div>
                        <span class="arrow">→</span>
                    </a>
                    <a href="input.php?jenis=perangkat_desa" class="jenis-card card-pegawai">
                        <div class="jenis-icon">🏘️</div>
                        <div class="jenis-info">
                            <div class="jenis-name">Perangkat Desa</div>
                            <div class="jenis-desc">Kepala Desa, Sekdes, Kaur, BPD</div>
                        </div>
                        <span class="arrow">→</span>
                    </a>
                </div>
            </div>

            <!-- KATEGORI: KREDIT KHUSUS -->
            <div class="gate-category">
                <div class="gate-category-label cat-khusus">
                    <span class="cat-dot"></span>
                    Kredit Khusus
                </div>
                <div class="jenis-grid">
                    <a href="input.php?jenis=kpr" class="jenis-card card-khusus">
                        <div class="jenis-icon">🏠</div>
                        <div class="jenis-info">
                            <div class="jenis-name">KPR</div>
                            <div class="jenis-desc">Kredit Pemilikan Rumah</div>
                        </div>
                        <span class="arrow">→</span>
                    </a>
                    <a href="input.php?jenis=kretamas" class="jenis-card card-khusus">
                        <div class="jenis-icon">🥇</div>
                        <div class="jenis-info">
                            <div class="jenis-name">KRETAMAS</div>
                            <div class="jenis-desc">Kredit Emas</div>
                        </div>
                        <span class="arrow">→</span>
                    </a>
                    <a href="input.php?jenis=cashcolateral" class="jenis-card card-khusus">
                        <div class="jenis-icon">💰</div>
                        <div class="jenis-info">
                            <div class="jenis-name">CASHCOLATERAL</div>
                            <div class="jenis-desc">Kredit dengan jaminan deposito / tabungan</div>
                        </div>
                        <span class="arrow">→</span>
                    </a>
                </div>
            </div>

            <div class="gate-footer">
                <a href="dashboard.php">← Kembali ke Dashboard</a>
            </div>
        </div>
    </div>
</body>

</html>
