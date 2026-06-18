<?php
require_once __DIR__ . '/../includes/functions.php';
requireSameRole('kepatuhan');

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'tanggal_pengajuan';
$order = $_GET['order'] ?? 'DESC';
$page = intval($_GET['page'] ?? 1);
$per_page = intval($_GET['per_page'] ?? 10);
if ($per_page < 5 || $per_page > 100) $per_page = 10;

// Validate sort column
$valid_sorts = ['tanggal_pengajuan', 'nama_debitur', 'jumlah_kredit', 'status_pengajuan'];
if (!in_array($sort, $valid_sorts)) $sort = 'tanggal_pengajuan';
if (!in_array(strtoupper($order), ['ASC', 'DESC'])) $order = 'DESC';

$toggle_order = ($order === 'ASC') ? 'DESC' : 'ASC';

// Build WHERE clause
$where_parts = [];
$params = [];

if (!empty($search)) {
    $where_parts[] = "(nama_debitur LIKE ? OR pekerjaan LIKE ? OR nik LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$where_clause = !empty($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_kredit $where_clause");
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

$offset = ($page - 1) * $per_page;

// Get paginated data
$select_cols = "id_pengajuan, tanggal_pengajuan, nama_debitur, pekerjaan, jumlah_kredit, status_pengajuan";
$stmt = $pdo->prepare("SELECT $select_cols FROM pengajuan_kredit $where_clause ORDER BY $sort $order LIMIT " . intval($per_page) . " OFFSET " . intval($offset));
$stmt->execute($params);
$riwayat_assesmen = $stmt->fetchAll();

// URL helper
function build_url_riwayat($params_override = []) {
    $base_params = [
        'search' => $_GET['search'] ?? '',
        'sort' => $_GET['sort'] ?? 'tanggal_pengajuan',
        'order' => $_GET['order'] ?? 'DESC',
        'page' => $_GET['page'] ?? 1,
        'per_page' => $_GET['per_page'] ?? 10,
    ];
    $final_params = array_merge($base_params, $params_override);
    return '?' . http_build_query(array_filter($final_params));
}

// Sort link helper
function sort_link_riwayat($column, $label) {
    global $sort, $order, $toggle_order;
    $new_order = ($sort === $column) ? $toggle_order : 'ASC';
    $arrow = ($sort === $column) ? (($order === 'ASC') ? ' ↑' : ' ↓') : '';
    $url = build_url_riwayat(['sort' => $column, 'order' => $new_order, 'page' => 1]);
    return "<a href=\"$url\" class=\"sort-link\">$label$arrow</a>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Assesmen Kepatuhan</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <?php
        $page_title = 'Riwayat Assesmen Kepatuhan';
        $page_subtitle = 'Daftar pengajuan yang telah dinilai kepatuhannya';
        include __DIR__ . '/../includes/page_header.inc.php';
        ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="mb-0">
                <div class="filter-row">
                    <div>
                        <label class="filter-label">Cari Debitur/NIK</label>
                        <input type="text" name="search" placeholder="Nama atau NIK..." value="<?= htmlspecialchars($search) ?>" class="w-full">
                    </div>
                    <div>
                        <label class="filter-label">Per Halaman</label>
                        <select name="per_page" class="w-full">
                            <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10 records</option>
                            <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25 records</option>
                            <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50 records</option>
                            <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100 records</option>
                        </select>
                    </div>
                    <div></div>
                    <div></div>
                    <button type="submit" class="btn btn-primary">Cari</button>
                    <a href="?" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="record-info">
            Menampilkan <strong><?= count($riwayat_assesmen) ?></strong> dari <strong><?= $total_records ?></strong> pengajuan
            <?php if (!empty($search)): ?><span class="search-highlight"> — "<?= htmlspecialchars($search) ?>"</span><?php endif; ?>
        </div>

        <div class="card">
            <?php if(empty($riwayat_assesmen)): ?>
                <div class="empty-state">
                    <?php if (!empty($search)): ?>
                        <p>Tidak ada hasil untuk pencarian tersebut. <a href="?">Lihat semua</a></p>
                    <?php else: ?>
                        <p>Belum ada riwayat assesmen kepatuhan.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-stack">
                        <thead>
                            <tr>
                                <th><?= sort_link_riwayat('tanggal_pengajuan', 'Tgl Pengajuan') ?></th>
                                <th><?= sort_link_riwayat('nama_debitur', 'Nama Debitur') ?></th>
                                <th>Pekerjaan</th>
                                <th class="text-right"><?= sort_link_riwayat('jumlah_kredit', 'Nominal') ?></th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($riwayat_assesmen as $item): ?>
                            <tr>
                                <td data-label="Tgl"><?= date('d/M/Y', strtotime($item['tanggal_pengajuan'])) ?></td>
                                <td data-label="Debitur" class="font-medium"><?= htmlspecialchars($item['nama_debitur']) ?></td>
                                <td data-label="Pekerjaan" class="text-sm text-muted"><?= htmlspecialchars($item['pekerjaan'] ?? '-') ?></td>
                                <td data-label="Nominal" class="text-right font-medium"><?= formatRupiah($item['jumlah_kredit']) ?></td>
                                <td data-label="Status" class="text-center">
                                    <?php
                                        $status = $item['status_pengajuan'];
                                        $class = match(true) {
                                            $status === 'disetujui' => 'badge-approved',
                                            $status === 'ditolak' => 'badge-rejected',
                                            in_array($status, ['revisi', 'kembalikan'], true) => 'badge-revision',
                                            default => 'badge-pending'
                                        };
                                    ?>
                                    <span class="badge <?= $class ?>"><?= ucwords(str_replace('_', ' ', $status)) ?></span>
                                </td>
                                <td data-label="Aksi">
                                    <a href="../detail.php?id=<?= $item['id_pengajuan'] ?>" class="btn btn-secondary btn-sm">Detail</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= build_url_riwayat(['page' => 1]) ?>" title="Halaman pertama">« Awal</a>
                        <a href="<?= build_url_riwayat(['page' => $page - 1]) ?>" title="Halaman sebelumnya">‹ Prev</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    if ($start > 1): ?>
                        <span>...</span>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= build_url_riwayat(['page' => $i]) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end < $total_pages): ?>
                        <span>...</span>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?= build_url_riwayat(['page' => $page + 1]) ?>" title="Halaman berikutnya">Next ›</a>
                        <a href="<?= build_url_riwayat(['page' => $total_pages]) ?>" title="Halaman terakhir">Akhir »</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>