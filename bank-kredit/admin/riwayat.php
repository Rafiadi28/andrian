<?php
require_once __DIR__ . '/../includes/functions.php';
requireSameRole('Superadmin');


// -- PAGINATION & SEARCH LOGIC --
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build Query
$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (pk.nama_debitur LIKE ? OR u.nama LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($start_date) {
    $where .= " AND DATE(ak.tanggal_approval) >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $where .= " AND DATE(ak.tanggal_approval) <= ?";
    $params[] = $end_date;
}

// 1. Get Total Count for Pagination
$stmt_count = $pdo->prepare("
    SELECT COUNT(*) 
    FROM approval_kredit ak
    JOIN pengajuan_kredit pk ON ak.id_pengajuan = pk.id_pengajuan
    LEFT JOIN users u ON ak.id_user = u.id_user
    $where
");
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// 2. Get Data
$sql = "
    SELECT 
        ak.tanggal_approval,
        pk.nama_debitur,
        u.nama AS nama_approver,
        u.role AS role_approver,
        ak.level_approval,
        ak.keputusan,
        ak.catatan,
        ak.is_auto_skip
    FROM approval_kredit ak
    JOIN pengajuan_kredit pk ON ak.id_pengajuan = pk.id_pengajuan
    LEFT JOIN users u ON ak.id_user = u.id_user
    $where
    ORDER BY ak.tanggal_approval DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$approvals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Approval Kredit</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .filter-container {
            background: #fff;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .filter-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #4b5563;
        }
        .filter-input {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.9rem;
        }
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
        .page-link :hover:not(.active) {
            background: #f3f4f6;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
             <h1>Riwayat Proses Approval Kredit</h1>
        </div>

        <!-- Filter Form -->
        <form method="GET" class="filter-container">
            <div class="filter-group">
                <label>Cari (Debitur/Approver)</label>
                <input type="text" name="search" class="filter-input" placeholder="Nama..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="filter-group">
                <label>Dari Tanggal</label>
                <input type="date" name="start_date" class="filter-input" value="<?= htmlspecialchars($start_date) ?>">
            </div>
             <div class="filter-group">
                <label>Sampai Tanggal</label>
                <input type="date" name="end_date" class="filter-input" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="filter-group">
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1rem;">Filter</button>
            </div>
            <div class="filter-group">
                 <a href="riwayat.php" class="btn btn-secondary" style="padding: 0.6rem 1rem;">Reset</a>
            </div>
        </form>

        <div class="card table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Nama Debitur</th>
                        <th>Approver</th>
                        <th>Level</th>
                        <th>Keputusan</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($approvals) > 0): ?>
                        <?php foreach($approvals as $a): ?>
                        <tr>
                            <td><?= date('d/M/Y H:i', strtotime($a['tanggal_approval'])) ?></td>
                            <td><?= htmlspecialchars($a['nama_debitur']) ?></td>
                            <td>
                                <?php if($a['is_auto_skip']): ?>
                                    <em class="text-muted">System (Auto Skip)</em>
                                <?php else: ?>
                                    <?= htmlspecialchars($a['nama_approver'] ?? 'Unknown') ?>
                                    <br>
                                    <small class="text-muted">(<?= $a['role_approver'] ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge"><?= strtoupper(str_replace('_', ' ', $a['level_approval'])) ?></span></td>
                            <td>
                                <?php if($a['keputusan'] == 'setuju'): ?>
                                    <span class="badge badge-success">SETUJU</span>
                                <?php elseif($a['keputusan'] == 'tolak'): ?>
                                    <span class="badge badge-danger">TOLAK</span>
                                <?php elseif($a['keputusan'] == 'revisi'): ?>
                                    <span class="badge badge-warning">REVISI</span>
                                <?php else: ?>
                                    <span class="badge"><?= strtoupper(str_replace('_', ' ', $a['keputusan'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($a['catatan']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">Belum ada data approval.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="page-link">&laquo; Prev</a>
            <?php endif; ?>

            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="page-link <?= ($i == $page) ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="page-link">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <p class="text-muted" style="text-align:center; margin-top:1rem; font-size:0.9rem;">
            Total Data: <?= $total_records ?> | Halaman <?= $page ?> dari <?= $total_pages ?>
        </p>
    </div>
</body>
</html>
