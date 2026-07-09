<?php
/**
 * Shared Template for Approval History (Riwayat) - Kasubag, Kabag, Kadiv, Direksi
 * Expected variable: $my_role
 */
require_once __DIR__ . '/functions.php';

if (!isset($my_role)) {
    die('Role not defined for this riwayat.');
}
requireSameRole($my_role);

$role_name_display = ucwords(str_replace('_', ' ', $my_role));
$page_title = 'Riwayat Approval';
$page_subtitle = 'Keputusan yang telah Anda berikan sebagai ' . $role_name_display;

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$stmtCount = $pdo->prepare("
    SELECT COUNT(*)
    FROM pengajuan_kredit p
    JOIN approval_kredit a ON p.id_pengajuan = a.id_pengajuan
    WHERE a.id_user = ? AND a.level_approval = ?
");
$stmtCount->execute([$_SESSION['user_id'], $my_role]);
$total_records = $stmtCount->fetchColumn();
$total_pages = ceil($total_records / $limit);

$stmt = $pdo->prepare("
    SELECT p.id_pengajuan, p.nama_debitur, p.posisi_saat_ini,
           a.keputusan as my_decision, a.tanggal_approval, a.catatan as my_note
    FROM pengajuan_kredit p
    JOIN approval_kredit a ON p.id_pengajuan = a.id_pengajuan
    WHERE a.id_user = ? AND a.level_approval = ?
    ORDER BY a.tanggal_approval DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute([$_SESSION['user_id'], $my_role]);
$history_items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Approval - <?= htmlspecialchars($role_name_display) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>

    <div class="container">
        <?php include __DIR__ . '/page_header.inc.php'; ?>

        <div class="card">
            <div class="table-responsive">
                <table class="table-stack">
                    <thead>
                        <tr>
                            <th>Tgl Approval</th>
                            <th>Debitur</th>
                            <th>Keputusan</th>
                            <th>Catatan</th>
                            <th>Posisi Terkini</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($history_items)): ?>
                            <tr><td colspan="6" class="text-center">Belum ada riwayat approval.</td></tr>
                        <?php else: ?>
                            <?php foreach($history_items as $item):
                                $badge_class = match($item['my_decision']) {
                                    'setuju' => 'badge-approved',
                                    'tolak' => 'badge-rejected',
                                    default => 'badge-revision'
                                };
                            ?>
                            <tr>
                                <td data-label="Tgl"><?= date('d/M/Y H:i', strtotime($item['tanggal_approval'])) ?></td>
                                <td data-label="Debitur" class="font-medium"><?= htmlspecialchars($item['nama_debitur']) ?></td>
                                <td data-label="Keputusan">
                                    <span class="badge <?= $badge_class ?>"><?= strtoupper($item['my_decision']) ?></span>
                                </td>
                                <td data-label="Catatan"><?= htmlspecialchars($item['my_note']) ?></td>
                                <td data-label="Posisi"><span class="badge badge-process"><?= strtoupper(str_replace('_', ' ', $item['posisi_saat_ini'])) ?></span></td>
                                <td data-label="Aksi">
                                    <a href="../detail.php?id=<?= $item['id_pengajuan'] ?>" class="btn btn-secondary btn-sm">Detail</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1">Awal</a>
                    <a href="?page=<?= $page - 1 ?>">Prev</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>">Next</a>
                    <a href="?page=<?= $total_pages ?>">Akhir</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
