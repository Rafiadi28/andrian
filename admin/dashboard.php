<?php
require_once __DIR__ . '/../includes/functions.php';
requireSameRole('Superadmin');

$stmt = $pdo->query("SELECT COUNT(*) FROM pengajuan_kredit");
$total_kredit = $stmt->fetchColumn();

$inSt = pengajuanStatusesActivePipelineSqlIn();
$stmt = $pdo->query("SELECT COUNT(*) FROM pengajuan_kredit WHERE status_pengajuan IN ($inSt)");
$pending_kredit = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$total_users = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT * FROM users WHERE status_jabatan != 'aktif'");
$unavailable_users = $stmt->fetchAll();

$page_title = 'Dashboard Admin';
$page_subtitle = 'Kelola sistem dan pantau aktivitas pengajuan kredit';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <?php include __DIR__ . '/../includes/page_header.inc.php'; ?>

        <div class="stats-grid">
            <div class="stat-card stat-card-primary">
                <div class="stat-card-header">
                    <h4>Total Users</h4>
                    <div class="stat-card-icon">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($total_users, 0, ',', '.') ?></div>
                <p>Pengguna terdaftar</p>
            </div>
            <div class="stat-card stat-card-warning">
                <div class="stat-card-header">
                    <h4>Pengajuan Proses</h4>
                    <div class="stat-card-icon">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($pending_kredit, 0, ',', '.') ?></div>
                <p>Sedang dalam proses</p>
            </div>
            <div class="stat-card stat-card-success">
                <div class="stat-card-header">
                    <h4>Total Pengajuan</h4>
                    <div class="stat-card-icon">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($total_kredit, 0, ',', '.') ?></div>
                <p>Semua pengajuan kredit</p>
            </div>
        </div>

        <div class="card">
            <div class="section-header">
                <h3 class="section-title">
                    <span class="section-title-icon" aria-hidden="true">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </span>
                    Monitoring Pejabat
                </h3>
            </div>
            <?php if (count($unavailable_users) > 0): ?>
                <div class="alert alert-danger">
                    <strong>Peringatan</strong>
                    <?= count($unavailable_users) ?> pejabat tidak aktif. Eskalasi otomatis aktif untuk jabatan ini.
                </div>
                <div class="table-responsive">
                    <table class="table-stack">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Jabatan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unavailable_users as $u): ?>
                                <tr>
                                    <td data-label="Nama"><?= htmlspecialchars($u['nama']) ?></td>
                                    <td data-label="Jabatan"><?= htmlspecialchars($u['role']) ?></td>
                                    <td data-label="Status"><span class="badge badge-danger"><?= strtoupper($u['status_jabatan']) ?></span></td>
                                    <td data-label="Aksi">
                                        <a href="users.php" class="btn btn-secondary btn-sm">Kelola</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    Semua pejabat berstatus <strong>AKTIF</strong>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
