<?php
require_once __DIR__ . '/../includes/functions.php';
requireSameRole('Superadmin');


// Pagination Logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; // 20 logs per page
$offset = ($page - 1) * $limit;

// Count Total
$stmt_count = $pdo->query("SELECT COUNT(*) FROM audit_log");
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get Data
$stmt = $pdo->prepare("SELECT l.*, u.nama, u.role FROM audit_log l LEFT JOIN users u ON l.id_user = u.id_user ORDER BY l.waktu DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Audit Log System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        .page-link {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background: white;
            color: #374151;
            text-decoration: none;
        }
        .page-link.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }
        .page-link:hover:not(.active) {
            background: #f3f4f6;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <h1>Audit Log Aktivitas Sistem</h1>
        <div class="card table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Aktivitas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($logs) > 0): ?>
                        <?php foreach($logs as $l): ?>
                        <tr>
                            <td><?= date('d/M/Y H:i:s', strtotime($l['waktu'])) ?></td>
                            <td><?= htmlspecialchars($l['nama'] ?? 'Unknown') ?></td>
                            <td><span class="badge badge-process"><?= htmlspecialchars($l['role'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars($l['aktivitas']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                         <tr><td colspan="4" class="text-center">Belum ada log aktivitas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=<?= $page-1 ?>" class="page-link">&laquo; Prev</a>
            <?php endif; ?>

            <?php 
                // Show limited pages to avoid clutter
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                if($start > 1) echo '<span style="padding:0.5rem;">...</span>';

                for($i=$start; $i<=$end; $i++): 
            ?>
                <a href="?page=<?= $i ?>" class="page-link <?= ($i == $page) ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if($end < $total_pages) echo '<span style="padding:0.5rem;">...</span>'; ?>

            <?php if($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?>" class="page-link">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <p class="text-muted" style="text-align:center; margin-top:1rem; font-size:0.9rem;">
            Total Log: <?= $total_records ?> | Halaman <?= $page ?> dari <?= $total_pages ?>
        </p>

    </div>
</body>
</html>
