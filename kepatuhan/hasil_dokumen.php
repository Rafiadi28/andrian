<?php
/**
 * Halaman Hasil Dokumen Kepatuhan - Dapat diakses oleh SEMUA role
 */
require_once __DIR__ . '/../includes/functions.php';

// Require any logged-in user (all roles allowed)
if (!isLoggedIn()) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

$current_role = $_SESSION['role'] ?? '';

// Ensure table exists (safe, idempotent)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS dokumen_kepatuhan (
        id_dokumen INT AUTO_INCREMENT PRIMARY KEY,
        id_user INT NOT NULL,
        judul VARCHAR(255) NOT NULL,
        deskripsi TEXT NULL,
        kategori VARCHAR(100) NOT NULL DEFAULT 'umum',
        nama_file VARCHAR(255) NOT NULL,
        nama_asli VARCHAR(255) NOT NULL,
        ukuran_file INT DEFAULT 0,
        tipe_file VARCHAR(50) DEFAULT NULL,
        tanggal_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_dok_user (id_user),
        KEY idx_dok_kategori (kategori),
        KEY idx_dok_tanggal (tanggal_upload),
        FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE RESTRICT
    )");
} catch (Exception $e) {}

// Filter parameters
$search    = trim($_GET['search'] ?? '');
$kategori  = trim($_GET['kategori'] ?? '');
$sort      = $_GET['sort'] ?? 'tanggal_upload';
$order     = strtoupper($_GET['order'] ?? 'DESC');
$page      = max(1, intval($_GET['page'] ?? 1));
$per_page  = 15;

$valid_sorts = ['tanggal_upload', 'judul', 'kategori'];
if (!in_array($sort, $valid_sorts, true)) $sort = 'tanggal_upload';
if (!in_array($order, ['ASC', 'DESC'], true)) $order = 'DESC';
$toggle_order = ($order === 'ASC') ? 'DESC' : 'ASC';

$allowed_kategori = ['umum','regulasi','kebijakan','laporan','pengumuman','panduan','lainnya'];

// Build WHERE
$where_parts = [];
$params = [];
if (!empty($search)) {
    $where_parts[] = "(d.judul LIKE ? OR d.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($kategori) && in_array($kategori, $allowed_kategori, true)) {
    $where_parts[] = "d.kategori = ?";
    $params[] = $kategori;
}
$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen_kepatuhan d $where_sql");
$countStmt->execute($params);
$total_records = (int)$countStmt->fetchColumn();
$total_pages = max(1, ceil($total_records / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// Fetch data
$dataStmt = $pdo->prepare("SELECT d.*, u.nama as nama_uploader
    FROM dokumen_kepatuhan d
    LEFT JOIN users u ON d.id_user = u.id_user
    $where_sql
    ORDER BY d.$sort $order
    LIMIT $per_page OFFSET $offset");
$dataStmt->execute($params);
$documents = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

function build_url_docs($overrides = []) {
    $base = [
        'search'   => $_GET['search']   ?? '',
        'kategori' => $_GET['kategori'] ?? '',
        'sort'     => $_GET['sort']     ?? 'tanggal_upload',
        'order'    => $_GET['order']    ?? 'DESC',
        'page'     => $_GET['page']     ?? 1,
    ];
    return '?' . http_build_query(array_merge($base, $overrides));
}

function sort_link_docs($col, $label) {
    global $sort, $order, $toggle_order;
    $newOrder = ($sort === $col) ? $toggle_order : 'ASC';
    $arrow = ($sort === $col) ? ($order === 'ASC' ? ' ↑' : ' ↓') : '';
    $url = build_url_docs(['sort' => $col, 'order' => $newOrder, 'page' => 1]);
    return "<a href=\"$url\" style=\"color:inherit;text-decoration:none;\">$label$arrow</a>";
}

$ext_icons = [
    'pdf'  => '📄', 'doc' => '📝', 'docx' => '📝',
    'xls'  => '📊', 'xlsx' => '📊',
    'ppt'  => '📋', 'pptx' => '📋',
    'jpg'  => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️',
    'zip'  => '🗜️',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumen Kepatuhan</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .badge-kategori { padding:3px 10px; border-radius:999px; font-size:0.78rem; font-weight:600; text-transform:uppercase; display:inline-block; }
        .badge-umum { background:#e0f2fe; color:#0369a1; }
        .badge-regulasi { background:#fce7f3; color:#be185d; }
        .badge-kebijakan { background:#e0e7ff; color:#3730a3; }
        .badge-laporan { background:#dcfce7; color:#15803d; }
        .badge-pengumuman { background:#fef3c7; color:#92400e; }
        .badge-panduan { background:#f3e8ff; color:#6d28d9; }
        .badge-lainnya { background:#f1f5f9; color:#475569; }
        .filter-chips { display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1.25rem; }
        .chip { padding:5px 14px; border-radius:999px; border:1.5px solid #e2e8f0; background:#fff; font-size:0.85rem; cursor:pointer; text-decoration:none; color:#475569; transition:all .15s; }
        .chip:hover, .chip.active { background:#6366f1; color:#fff; border-color:#6366f1; }
        .doc-grid { display:grid; gap:1rem; }
        .doc-item { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.25rem; display:flex; align-items:flex-start; gap:1rem; transition:box-shadow .2s; }
        .doc-item:hover { box-shadow:0 4px 16px rgba(0,0,0,.08); }
        .doc-icon { font-size:2.2rem; flex-shrink:0; width:48px; text-align:center; }
        .doc-meta { flex:1; min-width:0; }
        .doc-title { font-weight:700; font-size:1rem; color:#1e293b; margin:0 0 0.25rem 0; }
        .doc-desc { font-size:0.85rem; color:#64748b; margin:0 0 0.5rem 0; }
        .doc-info { font-size:0.8rem; color:#94a3b8; display:flex; flex-wrap:wrap; gap:0.75rem; }
        .doc-actions { display:flex; align-items:center; gap:0.5rem; flex-shrink:0; }
        .empty-state { text-align:center; padding:3rem 2rem; color:#94a3b8; }
        .empty-state p { font-size:1rem; margin:0.5rem 0; }
        .kep-notice { background:#eff6ff; border-left:4px solid #6366f1; padding:0.85rem 1.25rem; border-radius:8px; font-size:0.9rem; color:#3730a3; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.75rem; }
        @media (max-width: 600px) {
            .doc-item { flex-direction: column; }
            .doc-actions { flex-direction: row; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <?php
    $page_title = 'Dokumen Kepatuhan';
    $page_subtitle = 'Dokumen & informasi yang dipublikasikan oleh Departemen Kepatuhan';
    include __DIR__ . '/../includes/page_header.inc.php';
    ?>

    <?php if ($current_role === 'kepatuhan' || $current_role === 'Superadmin'): ?>
    <div class="kep-notice">
        <span style="font-size:1.2rem;">🛡️</span>
        <span>Anda login sebagai <strong><?= htmlspecialchars(getRoleDisplay($current_role)) ?></strong>.
        <a href="upload_dokumen.php" style="color:#4f46e5; font-weight:600; margin-left:0.5rem;">➕ Upload Dokumen Baru</a>
        </span>
    </div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="filter-section">
        <form method="GET" style="margin:0;">
            <div class="filter-row">
                <div>
                    <label class="filter-label">Cari Dokumen</label>
                    <input type="text" name="search" placeholder="Judul atau deskripsi..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                <div>
                    <label class="filter-label">Kategori</label>
                    <select name="kategori">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($allowed_kategori as $k): ?>
                        <option value="<?= $k ?>" <?= $kategori === $k ? 'selected' : '' ?>><?= ucfirst($k) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div></div>
                <div></div>
                <button type="submit" class="btn btn-primary">Cari</button>
                <a href="hasil_dokumen.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Category chips -->
    <div class="filter-chips">
        <a href="hasil_dokumen.php" class="chip <?= $kategori === '' ? 'active' : '' ?>">Semua</a>
        <?php foreach ($allowed_kategori as $k): ?>
        <a href="<?= build_url_docs(['kategori' => $k, 'page' => 1]) ?>" 
           class="chip <?= $kategori === $k ? 'active' : '' ?>"><?= ucfirst($k) ?></a>
        <?php endforeach; ?>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;font-size:0.9rem;color:#64748b;">
        <span>Menampilkan <strong><?= count($documents) ?></strong> dari <strong><?= $total_records ?></strong> dokumen</span>
        <span>Urutkan: <?= sort_link_docs('tanggal_upload','Terbaru') ?> | <?= sort_link_docs('judul','Judul') ?></span>
    </div>

    <?php if (empty($documents)): ?>
        <div class="empty-state">
            <div style="font-size:3rem;margin-bottom:1rem;">📂</div>
            <p><strong>Belum ada dokumen kepatuhan</strong></p>
            <p><?= !empty($search) ? 'Tidak ada hasil untuk pencarian tersebut.' : 'Departemen kepatuhan belum mengupload dokumen.' ?></p>
            <?php if (!empty($search)): ?>
                <a href="hasil_dokumen.php" class="btn btn-secondary" style="margin-top:1rem;">Lihat Semua</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="doc-grid">
            <?php foreach ($documents as $doc): ?>
            <?php $icon = $ext_icons[$doc['tipe_file'] ?? ''] ?? '📎'; ?>
            <div class="doc-item">
                <div class="doc-icon"><?= $icon ?></div>
                <div class="doc-meta">
                    <p class="doc-title"><?= htmlspecialchars($doc['judul']) ?></p>
                    <?php if ($doc['deskripsi']): ?>
                        <p class="doc-desc"><?= htmlspecialchars(mb_substr($doc['deskripsi'], 0, 150)) ?><?= mb_strlen($doc['deskripsi']) > 150 ? '...' : '' ?></p>
                    <?php endif; ?>
                    <div class="doc-info">
                        <span><span class="badge-kategori badge-<?= htmlspecialchars($doc['kategori']) ?>"><?= ucfirst(htmlspecialchars($doc['kategori'])) ?></span></span>
                        <span>👤 <?= htmlspecialchars($doc['nama_uploader'] ?? 'Kepatuhan') ?></span>
                        <span>📅 <?= date('d M Y, H:i', strtotime($doc['tanggal_upload'])) ?></span>
                        <span>📦 <?= number_format($doc['ukuran_file'] / 1024, 1) ?> KB</span>
                        <span>🔖 <?= strtoupper(htmlspecialchars($doc['tipe_file'] ?? 'FILE')) ?></span>
                    </div>
                </div>
                <div class="doc-actions">
                    <a href="../assets/uploads/kepatuhan/<?= htmlspecialchars($doc['nama_file']) ?>"
                       target="_blank" class="btn btn-primary btn-sm" download>
                        ⬇️ Unduh
                    </a>
                    <?php if ($current_role === 'kepatuhan' && (int)$doc['id_user'] === (int)$_SESSION['user_id']): ?>
                    <?php $csrf_del = generateCsrfToken(); ?>
                    <a href="upload_dokumen.php?delete=<?= $doc['id_dokumen'] ?>&csrf=<?= urlencode($csrf_del) ?>"
                       onclick="return confirm('Hapus dokumen ini?')"
                       class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;">🗑</a>
                    <?php elseif ($current_role === 'Superadmin'): ?>
                    <?php $csrf_del = generateCsrfToken(); ?>
                    <a href="upload_dokumen.php?delete=<?= $doc['id_dokumen'] ?>&admin=1&csrf=<?= urlencode($csrf_del) ?>"
                       onclick="return confirm('Hapus dokumen ini? (Admin)')"
                       class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;">🗑</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination" style="margin-top:1.5rem;">
            <?php if ($page > 1): ?>
                <a href="<?= build_url_docs(['page' => 1]) ?>">« Awal</a>
                <a href="<?= build_url_docs(['page' => $page - 1]) ?>">‹ Prev</a>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= build_url_docs(['page' => $i]) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?= build_url_docs(['page' => $page + 1]) ?>">Next ›</a>
                <a href="<?= build_url_docs(['page' => $total_pages]) ?>">Akhir »</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
