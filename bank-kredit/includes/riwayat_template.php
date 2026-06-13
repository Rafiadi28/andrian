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

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Count total records
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM pengajuan_kredit p 
    JOIN approval_kredit a ON p.id_pengajuan = a.id_pengajuan 
    WHERE a.id_user = ? AND a.level_approval = ?
");
$stmtCount->execute([$_SESSION['user_id'], $my_role]);
$total_records = $stmtCount->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch History
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
    <title>Riwayat Approval - <?= htmlspecialchars($role_name_display) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem; margin-bottom: 1.5rem; }
        .pagination a, .pagination span { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; text-decoration: none; color: #0f172a; }
        .pagination a:hover { background: #f1f5f9; }
        .pagination .active { background: #3b82f6; color: white; pointer-events: none; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>

    <div class="container">
        <h1>Riwayat Approval Saya (<?= htmlspecialchars($role_name_display) ?>)</h1>
        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Tgl Approval</th>
                            <th>Debitur</th>
                            <th>Keputusan Saya</th>
                            <th>Catatan</th>
                            <th>Posisi Surat Terkini</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($history_items)): ?>
                            <tr><td colspan="6" style="text-align:center;">Belum ada riwayat approval.</td></tr>
                        <?php else: ?>
                            <?php foreach($history_items as $item): ?>
                            <tr>
                                <td><?= date('d/M/Y H:i', strtotime($item['tanggal_approval'])) ?></td>
                                <td><?= htmlspecialchars($item['nama_debitur']) ?></td>
                                <td>
                                    <?php 
                                        if($item['my_decision'] == 'setuju') echo '<span style="background: #d4edda; color: #155724; padding: 0.2rem 0.6rem; border-radius: 0.2rem; font-size: 0.85rem; font-weight:600;">DISETUJUI</span>';
                                        elseif($item['my_decision'] == 'tolak') echo '<span style="background: #f8d7da; color: #721c24; padding: 0.2rem 0.6rem; border-radius: 0.2rem; font-size: 0.85rem; font-weight:600;">DITOLAK</span>';
                                        else echo '<span style="background: #fff3cd; color: #856404; padding: 0.2rem 0.6rem; border-radius: 0.2rem; font-size: 0.85rem; font-weight:600;">REVISI</span>';
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($item['my_note']) ?></td>
                                <td><span class="badge badge-process"><?= strtoupper(str_replace('_', ' ', $item['posisi_saat_ini'])) ?></span></td>
                                <td>
                                    <a href="../detail.php?id=<?= $item['id_pengajuan'] ?>" class="btn btn-secondary" style="font-size:0.8rem; padding:0.4rem 0.8rem;">Detail</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1">« Awal</a>
                    <a href="?page=<?= $page - 1 ?>">‹ Prev</a>
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
                    <a href="?page=<?= $page + 1 ?>">Next ›</a>
                    <a href="?page=<?= $total_pages ?>">Akhir »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
