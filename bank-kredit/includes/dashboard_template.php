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
$page_title = 'Dashboard ' . $role_name_display;
$page_subtitle = 'Ringkasan pengajuan yang perlu dan telah Anda proses';

// Stats
$activeStat = pengajuanStatusesActivePipeline();
$phStat = implode(',', array_fill(0, count($activeStat), '?'));
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_kredit WHERE posisi_saat_ini = ? AND status_pengajuan IN ($phStat)");
$stmt->execute(array_merge([$my_role], $activeStat));
$count_pending = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM approval_kredit WHERE id_user = ? AND level_approval = ?");
$stmt->execute([$_SESSION['user_id'], $my_role]);
$count_processed = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT id_pengajuan, nama_debitur, jumlah_kredit, tanggal_pengajuan, pekerjaan FROM pengajuan_kredit WHERE posisi_saat_ini = ? AND status_pengajuan IN ($phStat) ORDER BY tanggal_pengajuan DESC LIMIT 5");
$stmt->execute(array_merge([$my_role], $activeStat));
$recent_pending = $stmt->fetchAll();

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
        <?php include __DIR__ . '/page_header.inc.php'; ?>

        <div class="stats-grid">
            <div class="stat-card stat-card-warning">
                <div class="stat-card-header">
                    <h4>Perlu Diproses</h4>
                    <div class="stat-card-icon">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($count_pending, 0, ',', '.') ?></div>
                <p>Menunggu persetujuan Anda</p>
                <div class="mt-4">
                    <a href="proses.php" class="btn btn-primary btn-block">Proses Sekarang</a>
                </div>
            </div>

            <div class="stat-card stat-card-success">
                <div class="stat-card-header">
                    <h4>Telah Diproses</h4>
                    <div class="stat-card-icon">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($count_processed, 0, ',', '.') ?></div>
                <p>Total yang sudah Anda review</p>
                <div class="mt-4">
                    <a href="riwayat.php" class="btn btn-secondary btn-block">Lihat Riwayat</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="section-header">
                <h3 class="section-title">
                    <span class="section-title-icon" aria-hidden="true">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    </span>
                    Antrian Proses
                </h3>
                <a href="proses.php" class="btn btn-primary btn-sm">Lihat Semua</a>
            </div>

            <?php if (empty($recent_pending)): ?>
                <div class="empty-state">
                    <p>Tidak ada pengajuan yang perlu diproses</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-stack">
                        <thead>
                            <tr>
                                <th>Tgl Pengajuan</th>
                                <th>Nama Debitur</th>
                                <th>Pekerjaan</th>
                                <th class="text-right">Nominal</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_pending as $item): ?>
                            <tr>
                                <td data-label="Tgl"><?= date('d/M/Y', strtotime($item['tanggal_pengajuan'])) ?></td>
                                <td data-label="Debitur" class="font-medium"><?= htmlspecialchars($item['nama_debitur']) ?></td>
                                <td data-label="Pekerjaan" class="text-sm text-muted"><?= htmlspecialchars($item['pekerjaan']) ?></td>
                                <td data-label="Nominal" class="text-right font-medium"><?= formatRupiah($item['jumlah_kredit']) ?></td>
                                <td data-label="Aksi">
                                    <a href="../detail.php?id=<?= $item['id_pengajuan'] ?>" class="btn btn-secondary btn-sm">Detail</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="section-header">
                <h3 class="section-title">
                    <span class="section-title-icon" aria-hidden="true">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                    Riwayat Approval
                </h3>
                <a href="riwayat.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
            </div>

            <?php if (empty($recent_approvals)): ?>
                <div class="empty-state">
                    <p>Belum ada riwayat approval</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-stack">
                        <thead>
                            <tr>
                                <th>Tgl Approval</th>
                                <th>Nama Debitur</th>
                                <th class="text-right">Nominal</th>
                                <th class="text-center">Keputusan</th>
                                <th class="text-center">Aksi</th>
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
                                <td data-label="Tgl"><?= date('d/M/Y H:i', strtotime($item['tanggal_approval'])) ?></td>
                                <td data-label="Debitur" class="font-medium"><?= htmlspecialchars($item['nama_debitur']) ?></td>
                                <td data-label="Nominal" class="text-right font-medium"><?= formatRupiah($item['jumlah_kredit']) ?></td>
                                <td data-label="Keputusan" class="text-center">
                                    <span class="badge <?= $badge_class ?>">
                                        <?= ucwords(str_replace('_', ' ', $item['keputusan'])) ?>
                                    </span>
                                </td>
                                <td data-label="Aksi">
                                    <div class="table-actions">
                                        <a href="../detail.php?id=<?= $item['id_pengajuan'] ?>" class="btn btn-secondary btn-sm">Detail</a>
                                        <?php if ($item['status_pengajuan'] === 'disetujui'): ?>
                                            <a href="../print.php?id=<?= $item['id_pengajuan'] ?>&from=dashboard" class="btn btn-primary btn-sm" target="_blank" title="Cetak Dokumen">Cetak</a>
                                        <?php endif; ?>
                                    </div>
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
