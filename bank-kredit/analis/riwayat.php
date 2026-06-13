<?php
require_once __DIR__ . '/../includes/functions.php';
requireSameRole('analis');

// List My Submissions (kolom terbatas + pagination)
$perPage = 30;
$pageRiwayat = max(1, (int) ($_GET['page'] ?? 1));
$uid = (int) $_SESSION['user_id'];
$stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_kredit WHERE input_by = ?");
$stmtCnt->execute([$uid]);
$totalRiwayat = (int) $stmtCnt->fetchColumn();
$totalRiwayatPages = max(1, (int) ceil($totalRiwayat / $perPage));
if ($pageRiwayat > $totalRiwayatPages) {
    $pageRiwayat = $totalRiwayatPages;
}
$offRiwayat = ($pageRiwayat - 1) * $perPage;
$stmt = $pdo->prepare("SELECT id_pengajuan, nama_debitur, jumlah_kredit, posisi_saat_ini, status_pengajuan, tanggal_pengajuan
    FROM pengajuan_kredit WHERE input_by = :uid ORDER BY tanggal_pengajuan DESC LIMIT :lim OFFSET :off");
$stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offRiwayat, PDO::PARAM_INT);
$stmt->execute();
$my_submissions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Pengajuan Saya</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <h1>Riwayat Pengajuan Saya</h1>
        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Debitur</th>
                            <th>Jumlah</th>
                            <th>Posisi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($my_submissions)): ?>
                        <tr><td colspan="6" style="text-align:center;">Belum ada pengajuan.</td></tr>
                        <?php else: ?>
                            <?php foreach($my_submissions as $s): ?>
                            <tr>
                                <td><?= date('d/M/Y', strtotime($s['tanggal_pengajuan'])) ?></td>
                                <td><?= htmlspecialchars($s['nama_debitur']) ?></td>
                                <td><?= formatRupiah($s['jumlah_kredit']) ?></td>
                                <td><span class="badge badge-process"><?= strtoupper($s['posisi_saat_ini']) ?></span></td>
                                <td>
                                    <?php     
                                        if($s['status_pengajuan'] == 'disetujui') echo '<span class="badge badge-active">DISETUJUI</span>';
                                        else if($s['status_pengajuan'] == 'ditolak') echo '<span class="badge badge-sick">DITOLAK</span>';
                                        else echo '<span class="badge badge-process">PROSES</span>';
                                    ?>
                                </td>
                                <td>
                                    <a href="../detail.php?id=<?= $s['id_pengajuan'] ?>" class="btn btn-secondary" style="padding:0.25rem 0.5rem; font-size:0.8rem;">Detail</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($totalRiwayatPages > 1): ?>
                    <p style="margin-top: 1rem; text-align: center; color: #64748b; font-size: 0.95rem;">
                        Halaman <?= (int) $pageRiwayat ?> dari <?= (int) $totalRiwayatPages ?>
                        (<?= (int) $totalRiwayat ?> pengajuan)
                        <?php if ($pageRiwayat > 1): ?>
                            <a href="?page=<?= (int) ($pageRiwayat - 1) ?>" class="btn btn-secondary" style="margin-left: 0.5rem; padding: 0.35rem 0.75rem; font-size: 0.85rem;">Sebelumnya</a>
                        <?php endif; ?>
                        <?php if ($pageRiwayat < $totalRiwayatPages): ?>
                            <a href="?page=<?= (int) ($pageRiwayat + 1) ?>" class="btn btn-secondary" style="margin-left: 0.5rem; padding: 0.35rem 0.75rem; font-size: 0.85rem;">Berikutnya</a>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
