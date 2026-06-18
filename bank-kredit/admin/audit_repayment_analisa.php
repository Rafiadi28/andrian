<?php
/**
 * Audit log repayment analisa — menampilkan log perhitungan repayment pada setiap analisa pengajuan kredit.
 */
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$idPengajuan = isset($_GET['id_pengajuan']) ? (int) $_GET['id_pengajuan'] : 0;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Base query for total
$whereClauses = [];
$params = [];

if ($idPengajuan > 0) {
    $whereClauses[] = "id_pengajuan = ?";
    $params[] = $idPengajuan;
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Get Total
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM audit_repayment_analisa {$whereSql}");
$stmtTotal->execute($params);
$total = $stmtTotal->fetchColumn();

$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Get Data
$sqlData = "SELECT * FROM audit_repayment_analisa {$whereSql} ORDER BY id_audit DESC LIMIT ? OFFSET ?";
$stmtData = $pdo->prepare($sqlData);
$execParams = $params;
$execParams[] = $perPage;
$execParams[] = $offset;

// PDO bindParam for LIMIT and OFFSET because execute handles everything as strings by default which fails standard mode sometimes, but since Laravel/PHP 8 fixes it, we can just use bindValue.
foreach ($params as $key => $val) {
    $stmtData->bindValue($key + 1, $val);
}
$stmtData->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmtData->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmtData->execute();

$logs = $stmtData->fetchAll(PDO::FETCH_ASSOC);

function formatRupiahOutput($num) {
    return 'Rp ' . number_format((float)$num, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Audit Trail Repayment Analisa</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1>Audit Trail Repayment Analisa</h1>
                <p style="color:#64748b; margin-top:0.5rem; font-size:0.92rem;">
                    Catatan hasil perhitungan dan dasar parameter ketika petugas (Analis) melakukan proses simpan.
                    <?php if ($idPengajuan > 0): ?>
                        <br>Filter: Pengajuan #<?= $idPengajuan ?>. <a href="audit_repayment_analisa.php" style="color:var(--primary); text-decoration:underline;">Tampilkan Semua</a>
                    <?php endif; ?>
                </p>
                <p style="color:#b45309; margin-top:0.35rem; font-size:0.85rem; font-weight:600;">
                    Audit trail hanya dapat dilihat, untuk keperluan kepatuhan (compliance).
                </p>
            </div>
            <?php if ($idPengajuan > 0): ?>
            <a href="../analis/riwayat.php" class="btn btn-secondary">&larr; Kembali ke Riwayat</a>
            <?php else: ?>
            <a href="../admin/dashboard.php" class="btn btn-secondary">&larr; Dashboard</a>
            <?php endif; ?>
        </div>

        <div class="card table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Waktu Analisa</th>
                        <th>User (Analis)</th>
                        <th>ID Pengajuan</th>
                        <th>Jenis Kredit</th>
                        <th>Dasar Perhitungan</th>
                        <th>Nilai Basis (Cashflow / Gaji)</th>
                        <th>Maks (Persentase)</th>
                        <th>Kapasitas Angsuran (RPC)</th>
                        <th>Status Override</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center; color:#94a3b8; padding:2rem;">Belum ada entri audit.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="font-size:0.85rem; white-space:nowrap;">
                                    <?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['tanggal_analisa']))) ?>
                                </td>
                                <td style="font-size:0.85rem;">
                                    <?= htmlspecialchars($log['nama_analis'] ?? '-') ?><br>
                                    <span style="color:#64748b; font-size:0.75rem;">ID: <?= htmlspecialchars($log['id_analis'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <a href="?id_pengajuan=<?= (int) $log['id_pengajuan'] ?>" style="color:var(--primary); font-weight:bold;">
                                        #<?= (int) $log['id_pengajuan'] ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge badge-process" style="font-size:0.78rem;">
                                        <?= htmlspecialchars(strtoupper($log['jenis_kredit'] ?? '-')) ?>
                                    </span>
                                </td>
                                <td style="font-size:0.85rem;"><?= htmlspecialchars(strtoupper(str_replace('_', ' ', $log['dasar_perhitungan'] ?? '-'))) ?></td>
                                <td style="font-size:0.85rem; font-weight:600; text-align:right;">
                                    <?= formatRupiahOutput($log['nilai_basis']) ?>
                                </td>
                                <td style="font-size:0.85rem; text-align:center;">
                                    <?= (float) $log['persen_digunakan'] ?>%
                                </td>
                                <td style="font-size:0.9rem; font-weight:bold; text-align:right; color:#166534;">
                                    <?= formatRupiahOutput($log['maksimal_angsuran']) ?>
                                </td>
                                <td style="font-size:0.85rem;">
                                    <?php if ($log['override_aktif']): ?>
                                        <span style="background:#fef3c7; color:#d97706; padding:0.15rem 0.4rem; border-radius:4px; font-size:0.75rem; font-weight:bold;">AKTIF</span><br>
                                        <div style="margin-top:0.3rem;">Oleh: <?= htmlspecialchars($log['nama_override_by'] ?? '-') ?></div>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">Tidak</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="display:flex; gap:0.5rem; margin-top:1rem; flex-wrap:wrap;">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php
                    $q = ['page' => $p];
                    if ($idPengajuan > 0) {
                        $q['id_pengajuan'] = $idPengajuan;
                    }
                    ?>
                    <a href="?<?= http_build_query($q) ?>"
                        class="btn btn-secondary"
                        style="padding:0.35rem 0.65rem; font-size:0.85rem; <?= $p === $page ? 'background:#2563eb;color:#fff;' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
