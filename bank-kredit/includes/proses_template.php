<?php
/**
 * Shared Template for Approval Process Pages (Kasubag, Kabag, Kadiv, Direksi)
 * Variables expected: $my_role (e.g. 'kadiv_kredit')
 */

require_once __DIR__ . '/functions.php';

if (!isset($my_role)) {
    die('Role not defined for this process.');
}
requireSameRole($my_role);

// Handle Decision (delegated to central processApproval)
if (isset($_POST['submit_decision'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $id_pengajuan = intval($_POST['id_pengajuan'] ?? 0);
        $keputusan = trim((string)($_POST['keputusan'] ?? ''));
        $catatan = trim((string)($_POST['catatan'] ?? ''));

        $res = processApproval($pdo, $id_pengajuan, $my_role, $_SESSION['user_id'], $keputusan, $catatan);
        if ($res['success']) $success = $res['message']; else $error = $res['message'];
    }
}

// Get filters from GET
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'tanggal_pengajuan';
$order = $_GET['order'] ?? 'ASC';
$page = intval($_GET['page'] ?? 1);
$per_page = intval($_GET['per_page'] ?? 10);
if ($per_page < 5 || $per_page > 100) $per_page = 10;

// Validate sort column (prevent SQL injection)
$valid_sorts = ['tanggal_pengajuan', 'nama_debitur', 'jumlah_kredit', 'status_pengajuan'];
if (!in_array($sort, $valid_sorts)) $sort = 'tanggal_pengajuan';
if (!in_array(strtoupper($order), ['ASC', 'DESC'])) $order = 'ASC';

// Toggle order for column header click
$toggle_order = ($order === 'ASC') ? 'DESC' : 'ASC';

$activeStat = pengajuanStatusesActivePipeline();
$phStat = implode(',', array_fill(0, count($activeStat), '?'));

// Build WHERE clause
$where_parts = ["posisi_saat_ini = ?", "status_pengajuan IN ($phStat)"];
$params = array_merge([$my_role], $activeStat);

if (!empty($search)) {
    $where_parts[] = "(nama_debitur LIKE ? OR pekerjaan LIKE ? OR nik LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$where_clause = implode(' AND ', $where_parts);

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_kredit WHERE $where_clause");
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

$offset = ($page - 1) * $per_page;

// Get paginated data (Optimized SELECT *)
$select_cols = "id_pengajuan, tanggal_pengajuan, nama_debitur, pekerjaan, jumlah_kredit, jenis_kredit";
$stmt = $pdo->prepare("SELECT $select_cols FROM pengajuan_kredit WHERE $where_clause ORDER BY $sort $order LIMIT " . intval($per_page) . " OFFSET " . intval($offset));
$stmt->execute($params);
$pending_items = $stmt->fetchAll();

// Load compliance assessment status for each item (for UI indicators)
$compliance_status_cache = [];
if (!empty($pending_items)) {
    $ids_list = implode(',', array_map(function($item) { return intval($item['id_pengajuan']); }, $pending_items));
    $stmt_compliance = $pdo->prepare("SELECT id_pengajuan, 
        CASE 
            WHEN id_assessment IS NOT NULL AND checklist_data IS NOT NULL AND kesimpulan IS NOT NULL 
            THEN 'lengkap'
            WHEN id_assessment IS NOT NULL 
            THEN 'partial'
            ELSE 'tidak_ada'
        END as status
        FROM assessment_kepatuhan 
        WHERE id_pengajuan IN ($ids_list)");
    $stmt_compliance->execute();
    foreach ($stmt_compliance->fetchAll(PDO::FETCH_ASSOC) as $comp) {
        $compliance_status_cache[$comp['id_pengajuan']] = $comp['status'];
    }
}

// Helper function to check if compliance assessment is complete for an item
function getComplianceStatusBadge($id_pengajuan) {
    global $compliance_status_cache;
    $status = $compliance_status_cache[$id_pengajuan] ?? 'tidak_ada';
    
    if ($status === 'lengkap') {
        return '<span class="badge badge-success" title="Assessment kepatuhan sudah lengkap">✓ Compliance OK</span>';
    } elseif ($status === 'partial') {
        return '<span class="badge badge-warning" title="Assessment kepatuhan masih belum lengkap">⚠ Compliance Partial</span>';
    } else {
        return '<span class="badge badge-danger" title="Menunggu assessment kepatuhan">✗ Waiting Compliance</span>';
    }
}

// URL helper function
function build_url_proses($params_override = []) {
    $base_params = [
        'search' => $_GET['search'] ?? '',
        'sort' => $_GET['sort'] ?? 'tanggal_pengajuan',
        'order' => $_GET['order'] ?? 'ASC',
        'page' => $_GET['page'] ?? 1,
        'per_page' => $_GET['per_page'] ?? 10,
    ];
    $final_params = array_merge($base_params, $params_override);
    return '?' . http_build_query(array_filter($final_params));
}

// Sort link helper
function sort_link_proses($column, $label) {
    global $sort, $order, $toggle_order;
    $new_order = ($sort === $column) ? $toggle_order : 'ASC';
    $arrow = ($sort === $column) ? (($order === 'ASC') ? ' ↑' : ' ↓') : '';
    $url = build_url_proses(['sort' => $column, 'order' => $new_order, 'page' => 1]);
    return "<a href=\"$url\" class=\"sort-link\">$label$arrow</a>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Proses Approval - <?= strtoupper(str_replace('_', ' ', $my_role)) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>

    <div class="container">
        <div class="flex-between mb-4">
            <h1>Antrian Proses (<?= strtoupper(str_replace('_',' ', $my_role)) ?>)</h1>
        </div>

        <?php if(isset($success)): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if(isset($error)): ?><div class="alert alert-error">✗ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

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
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <a href="?" class="btn btn-secondary">↺ Reset</a>
                </div>
            </form>
        </div>

        <div class="record-info">
            📊 Menampilkan <strong><?= count($pending_items) ?></strong> dari <strong><?= $total_records ?></strong> pengajuan 
            <?php if (!empty($search)): ?><span class="search-highlight"> | Pencarian: "<?= htmlspecialchars($search) ?>"</span><?php endif; ?>
        </div>

        <div class="card">
            <?php if(empty($pending_items)): ?>
                <div class="empty-state">
                    <?php if (!empty($search)): ?>
                        <p>❌ Tidak ada hasil untuk pencarian "<?= htmlspecialchars($search) ?>". <a href="?">Lihat semua →</a></p>
                    <?php else: ?>
                        <p>✅ Tidak ada pengajuan yang perlu diproses.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th><?= sort_link_proses('tanggal_pengajuan', 'Tgl Input') ?></th>
                                <th><?= sort_link_proses('nama_debitur', 'Debitur') ?></th>
                                <th><?= sort_link_proses('jumlah_kredit', 'Nominal') ?></th>
                                <th>Jenis</th>
                                <th style="min-width: 120px;">Compliance</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pending_items as $item): 
                                $comp_status = $compliance_status_cache[$item['id_pengajuan']] ?? 'tidak_ada';
                                $is_compliance_blocked = ($comp_status !== 'lengkap') && in_array($my_role, ['kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama']);
                            ?>
                            <tr<?= $is_compliance_blocked ? ' style="background-color: #fff5f5;"' : '' ?>>
                                <td><?= date('d/M/Y', strtotime($item['tanggal_pengajuan'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['nama_debitur']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($item['pekerjaan']) ?></small>
                                </td>
                                <td><?= formatRupiah($item['jumlah_kredit']) ?></td>
                                <td><span class="badge badge-process"><?= htmlspecialchars($item['jenis_kredit'] ?? '-') ?></span></td>
                                <td>
                                    <?= getComplianceStatusBadge($item['id_pengajuan']) ?>
                                    <?php if ($is_compliance_blocked): ?>
                                        <br><small style="color:#d32f2f;">⚠ Blokir approval</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="../detail.php?id=<?= $item['id_pengajuan'] ?>" class="btn btn-secondary" style="font-size:0.8rem; padding: 0.5rem 1rem;">Detail</a>
                                        <?php if ($is_compliance_blocked): ?>
                                            <button class="btn btn-secondary" style="font-size:0.8rem; padding: 0.5rem 1rem; opacity: 0.6;" disabled title="Approval diblokir: menunggu assessment kepatuhan" onclick="alert('⚠️ Pengajuan ini TIDAK BISA diproses sampai Dept. Kepatuhan menyelesaikan assessment.\n\nSilakan hubungi Dept. Kepatuhan untuk menyelesaikan compliance assessment terlebih dahulu.');">Proses (Blokir)</button>
                                        <?php else: ?>
                                            <button onclick="openModal('<?= $item['id_pengajuan'] ?>', '<?= htmlspecialchars($item['nama_debitur'], ENT_QUOTES) ?>', '<?= formatRupiah($item['jumlah_kredit']) ?>')" class="btn btn-primary" style="font-size:0.8rem; padding: 0.5rem 1rem;">Proses</button>
                                        <?php endif; ?>
                                    </div>
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
                        <a href="<?= build_url_proses(['page' => 1]) ?>" title="Halaman pertama">« Awal</a>
                        <a href="<?= build_url_proses(['page' => $page - 1]) ?>" title="Halaman sebelumnya">‹ Prev</a>
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
                            <a href="<?= build_url_proses(['page' => $i]) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end < $total_pages): ?>
                        <span>...</span>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?= build_url_proses(['page' => $page + 1]) ?>" title="Halaman berikutnya">Next ›</a>
                        <a href="<?= build_url_proses(['page' => $total_pages]) ?>" title="Halaman terakhir">Akhir »</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Decision -->
    <div id="modal-approve" class="modal-overlay" style="display:none;">
        <div class="modal-content px-4 py-4">
            <h3 class="modal-header">Proses Pengajuan</h3>
            
            <div class="modal-info">
                <p>Debitur</p>
                <strong id="p_nama"></strong>
                
                <p class="mt-4">Nominal Pengajuan</p>
                <strong id="p_nominal"></strong>
            </div>

            <form method="POST">
                <input type="hidden" name="id_pengajuan" id="p_id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label>Catatan Keputusan (Wajib)</label>
                    <textarea name="catatan" rows="3" required placeholder="Berikan alasan persetujuan atau penolakan..." class="w-full"></textarea>
                </div>
                <div class="form-group">
                    <label>Keputusan</label>
                    <select name="keputusan" required class="w-full">
                        <option value="setuju">SETUJUI & TERUSKAN</option>
                        <option value="revisi">KEMBALIKAN / REVISI (minta perbaikan ke analis)</option>
                        <option value="tolak">TOLAK</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="document.getElementById('modal-approve').style.display='none'" class="btn btn-secondary">Batal</button>
                    <button type="submit" name="submit_decision" class="btn btn-primary">Simpan Keputusan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id, nama, nominal) {
            document.getElementById('modal-approve').style.display = 'flex';
            document.getElementById('p_id').value = id;
            document.getElementById('p_nama').innerText = nama;
            document.getElementById('p_nominal').innerText = nominal;
        }
    </script>
</body>
</html>
