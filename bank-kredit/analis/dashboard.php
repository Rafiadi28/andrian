<?php
require_once __DIR__ . '/../includes/functions.php';
requireSameRole('analis');

$inSt = pengajuanStatusesActivePipelineSqlIn();
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status_pengajuan IN ($inSt) THEN 1 ELSE 0 END) as proses,
    SUM(CASE WHEN status_pengajuan = 'disetujui' THEN 1 ELSE 0 END) as setuju,
    SUM(CASE WHEN status_pengajuan = 'ditolak' THEN 1 ELSE 0 END) as tolak
    FROM pengajuan_kredit WHERE input_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

$page_title = 'Dashboard Analis';
$page_subtitle = 'Ringkasan pengajuan kredit Anda';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Analis</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <?php include __DIR__ . '/../includes/page_header.inc.php'; ?>

        <div class="stats-grid">
            <div class="stat-card stat-card-primary">
                <div class="stat-card-header">
                    <h4>Total Pengajuan</h4>
                    <div class="stat-card-icon">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['total'] ?: 0, 0, ',', '.') ?></div>
                <p>Total pengajuan yang Anda buat</p>
            </div>
            <div class="stat-card stat-card-warning">
                <div class="stat-card-header">
                    <h4>Sedang Proses</h4>
                    <div class="stat-card-icon">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['proses'] ?: 0, 0, ',', '.') ?></div>
                <p>Dalam proses review</p>
            </div>
            <div class="stat-card stat-card-success">
                <div class="stat-card-header">
                    <h4>Disetujui</h4>
                    <div class="stat-card-icon">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['setuju'] ?: 0, 0, ',', '.') ?></div>
                <p>Pengajuan disetujui</p>
            </div>
            <div class="stat-card stat-card-danger">
                <div class="stat-card-header">
                    <h4>Ditolak</h4>
                    <div class="stat-card-icon">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['tolak'] ?: 0, 0, ',', '.') ?></div>
                <p>Pengajuan ditolak</p>
            </div>
        </div>

        <div class="card">
            <div class="section-header">
                <h3 class="section-title">
                    <span class="section-title-icon" aria-hidden="true">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </span>
                    Aksi Cepat
                </h3>
            </div>
            <div class="grid-2">
                <a href="input.php" class="action-card">
                    <div class="action-card-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                        </svg>
                    </div>
                    <div class="action-card-content">
                        <h3>Buat Pengajuan Baru</h3>
                        <p>Analisa calon debitur dan buat pengajuan kredit</p>
                    </div>
                    <div class="action-card-arrow">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>
                <a href="riwayat.php" class="action-card">
                    <div class="action-card-icon action-card-icon--info">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="action-card-content">
                        <h3>Riwayat Pengajuan</h3>
                        <p>Lihat status semua pengajuan Anda</p>
                    </div>
                    <div class="action-card-arrow">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>
                <a href="compliance_assessment.php" class="action-card">
                    <div class="action-card-icon action-card-icon--warning">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="action-card-content">
                        <h3>Penilaian Kepatuhan</h3>
                        <p>Buat assessment kepatuhan pengajuan</p>
                    </div>
                    <div class="action-card-arrow">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
