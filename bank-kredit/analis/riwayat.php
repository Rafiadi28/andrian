<?php
require_once __DIR__ . '/../includes/functions.php';
requireSameRole('analis');

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

$page_title = 'Riwayat Pengajuan';
$page_subtitle = 'Daftar semua pengajuan yang Anda buat';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pengajuan Saya</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <?php include __DIR__ . '/../includes/page_header.inc.php'; ?>

        <div class="card">
            <div class="table-responsive">
                <table class="table-stack">
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
                        <tr><td colspan="6" class="text-center">Belum ada pengajuan.</td></tr>
                        <?php else: ?>
                            <?php foreach($my_submissions as $s): ?>
                            <tr>
                                <td data-label="Tanggal"><?= date('d/M/Y', strtotime($s['tanggal_pengajuan'])) ?></td>
                                <td data-label="Debitur" class="font-medium"><?= htmlspecialchars($s['nama_debitur']) ?></td>
                                <td data-label="Jumlah"><?= formatRupiah($s['jumlah_kredit']) ?></td>
                                <td data-label="Posisi"><span class="badge badge-process"><?= strtoupper($s['posisi_saat_ini']) ?></span></td>
                                <td data-label="Status">
                                    <?php
                                        if ($s['status_pengajuan'] == 'disetujui') echo '<span class="badge badge-active">DISETUJUI</span>';
                                        elseif ($s['status_pengajuan'] == 'ditolak') echo '<span class="badge badge-sick">DITOLAK</span>';
                                        else echo '<span class="badge badge-process">PROSES</span>';
                                    ?>
                                </td>
                                <td data-label="Aksi">
                                    <a href="../detail.php?id=<?= $s['id_pengajuan'] ?>" class="btn btn-secondary btn-sm">Detail</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalRiwayatPages > 1): ?>
            <div class="pagination">
                <?php if ($pageRiwayat > 1): ?>
                    <a href="?page=<?= (int) ($pageRiwayat - 1) ?>">Prev</a>
                <?php endif; ?>
                <span class="active"><?= (int) $pageRiwayat ?> / <?= (int) $totalRiwayatPages ?></span>
                <?php if ($pageRiwayat < $totalRiwayatPages): ?>
                    <a href="?page=<?= (int) ($pageRiwayat + 1) ?>">Next</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
