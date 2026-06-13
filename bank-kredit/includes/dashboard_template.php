<?php
/**
 * Shared Template for Approval Dashboards (Kasubag, Kabag, Kadiv, Direksi)
 * Expected variable: $my_role
 */
require_once __DIR__ . '/functions.php';

if (!isset($my_role)) {
    die('Role not defined for this dashboard.');
}
requireSameRole($my_role);

$role_name_display = ucwords(str_replace('_', ' ', $my_role));

// Stats
// 1. Pending (To Do)
$activeStat = pengajuanStatusesActivePipeline();
$phStat = implode(',', array_fill(0, count($activeStat), '?'));
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_kredit WHERE posisi_saat_ini = ? AND status_pengajuan IN ($phStat)");
$stmt->execute(array_merge([$my_role], $activeStat));
$count_pending = $stmt->fetchColumn();

// 2. Processed by me (Approved/Rejected)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM approval_kredit WHERE id_user = ? AND level_approval = ?");
$stmt->execute([$_SESSION['user_id'], $my_role]);
$count_processed = $stmt->fetchColumn();

// 3. Get recent pending items (for dashboard preview)
$stmt = $pdo->prepare("SELECT id_pengajuan, nama_debitur, jumlah_kredit, tanggal_pengajuan, pekerjaan FROM pengajuan_kredit WHERE posisi_saat_ini = ? AND status_pengajuan IN ($phStat) ORDER BY tanggal_pengajuan DESC LIMIT 5");
$stmt->execute(array_merge([$my_role], $activeStat));
$recent_pending = $stmt->fetchAll();

// 4. Get recent approvals history
$stmt = $pdo->prepare("SELECT pk.id_pengajuan, pk.nama_debitur, pk.jumlah_kredit, pk.status_pengajuan, ak.keputusan, ak.tanggal_approval FROM approval_kredit ak JOIN pengajuan_kredit pk ON ak.id_pengajuan = pk.id_pengajuan WHERE ak.id_user = ? AND ak.level_approval = ? ORDER BY ak.tanggal_approval DESC LIMIT 10");
$stmt->execute([$_SESSION['user_id'], $my_role]);
$recent_approvals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard <?= htmlspecialchars($role_name_display) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>

    <div class="container">
        <div class="dashboard-header">
            <h1>Dashboard <?= htmlspecialchars($role_name_display) ?></h1>
            <p class="text-muted">Selamat datang di Dashboard <?= htmlspecialchars($role_name_display) ?></p>
        </div>

        <div class="stats-grid">
            <!-- PENDING CARD -->
            <div class="stat-card stat-card-warning">
                <div class="stat-card-header">
                    <h4>Perlu Diproses</h4>
                    <div class="stat-card-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($count_pending, 0, ',', '.') ?></div>
                <p>Pengajuan menunggu persetujuan Anda</p>
                <div class="mt-4">
                    <a href="proses.php" class="btn btn-primary w-full">Lihat & Proses &rarr;</a>
                </div>
            </div>

            <!-- PROCESSED CARD -->
            <div class="stat-card stat-card-success">
                <div class="stat-card-header">
                    <h4>Telah Diproses</h4>
                    <div class="stat-card-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($count_processed, 0, ',', '.') ?></div>
                <p>Total pengajuan yang sudah Anda review</p>
                <div class="mt-4">
                    <a href="riwayat.php" class="btn btn-secondary w-full">Lihat Riwayat &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Proses Pengajuan Section -->
        <div class="card">
            <div class="section-header">
                <h3>📋 Proses Pengajuan</h3>
                <a href="proses.php" class="btn btn-primary">Lihat Semua →</a>
            </div>
            
            <?php if (empty($recent_pending)): ?>
                <div class="empty-state">
                    <p>Tidak ada pengajuan yang perlu diproses</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Tgl Pengajuan</th>
                                <th>Nama Debitur</th>
                                <th>Pekerjaan</th>
                                <th style="text-align: right;">Nominal</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_pending as $item): ?>
                            <tr>
                                <td><?= date('d/M/Y', strtotime($item['tanggal_pengajuan'])) ?></td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($item['nama_debitur']) ?></td>
                                <td class="text-sm text-muted"><?= htmlspecialchars($item['pekerjaan']) ?></td>
                                <td style="text-align: right; font-weight: 500;"><?= formatRupiah($item['jumlah_kredit']) ?></td>
                                <td style="text-align: center;">
                                    <a href="../detail.php?id=<?= $item['id_pengajuan'] ?>" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">Detail</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Riwayat Approval Section -->
        <div class="card mb-6">
            <div class="section-header">
                <h3>✓ Riwayat Approval</h3>
                <a href="riwayat.php" class="btn btn-secondary">Lihat Semua →</a>
            </div>
            
            <?php if (empty($recent_approvals)): ?>
                <div class="empty-state">
                    <p>Belum ada riwayat approval</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Tgl Approval</th>
                                <th>Nama Debitur</th>
                                <th style="text-align: right;">Nominal</th>
                                <th style="text-align: center;">Keputusan</th>
                                <th style="text-align: center;">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_approvals as $item): 
                                $badge_class = match($item['keputusan']) {
                                    'setuju' => 'badge-approved',
                                    'tolak' => 'badge-rejected',
                                    'revisi', 'kembalikan' => 'badge-revision',
                                    default => 'badge-pending'
                                };
                            ?>
                            <tr>
                                <td><?= date('d/M/Y H:i', strtotime($item['tanggal_approval'])) ?></td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($item['nama_debitur']) ?></td>
                                <td style="text-align: right; font-weight: 500;"><?= formatRupiah($item['jumlah_kredit']) ?></td>
                                <td style="text-align: center;">
                                    <span class="badge <?= $badge_class ?>">
                                        <?= ucwords(str_replace('_', ' ', $item['keputusan'])) ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <a href="../detail.php?id=<?= $item['id_pengajuan'] ?>" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; margin-right: 0.3rem;">Detail</a>
                                    <?php if ($item['status_pengajuan'] === 'disetujui'): ?>
                                        <a href="../print.php?id=<?= $item['id_pengajuan'] ?>&from=dashboard" class="btn btn-success" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;" target="_blank" title="Cetak Dokumen">🖨️ Cetak</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
