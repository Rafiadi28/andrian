<?php
/**
 * Audit log parameter repayment — read-only, append-only (tidak ada edit/hapus).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/credit_helper.php';
require_once __DIR__ . '/../helpers/repayment_rbac.php';
require_once __DIR__ . '/../helpers/repayment_parameter_audit.php';

requireRepaymentParameterAccess();

$idParameter = isset($_GET['id_parameter']) ? (int) $_GET['id_parameter'] : 0;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$total = countRepaymentParameterAuditLog($pdo, $idParameter > 0 ? $idParameter : null);
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$logs = fetchRepaymentParameterAuditLog(
    $pdo,
    $idParameter > 0 ? $idParameter : null,
    $perPage,
    $offset
);

$approvalLabels = getRepaymentApprovalStatusLabels();
$roleLabels = [
    'kabag_kredit' => 'Kabag Kredit',
    'kadiv_kredit' => 'Kadiv Kredit',
    'kadiv_bisnis' => 'Kadiv Bisnis',
    'direksi' => 'Direksi',
    'direktur_utama' => 'Direktur Utama',
    'analis' => 'Analis',
    'Superadmin' => 'Superadmin',
];

function auditRoleLabel($role, array $labels) {
    $role = (string) $role;
    return $labels[$role] ?? str_replace('_', ' ', ucwords($role, '_'));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Audit Log Parameter Repayment</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
            <div>
                <h1>Audit Log Parameter Repayment</h1>
                <p style="color:#64748b; margin-top:0.5rem; font-size:0.92rem;">
                    Catatan permanen seluruh perubahan master parameter repayment.
                    <?php if ($idParameter > 0): ?>
                        Filter: parameter #<?= $idParameter ?>.
                    <?php endif; ?>
                </p>
                <p style="color:#b45309; margin-top:0.35rem; font-size:0.85rem; font-weight:600;">
                    Audit log tidak dapat diedit atau dihapus.
                </p>
            </div>
            <a href="master_parameter_repayment.php" class="btn btn-secondary">&larr; Kembali ke Master Parameter</a>
        </div>

        <div class="card table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Parameter</th>
                        <th>Aksi</th>
                        <th>User / Role</th>
                        <th>Sebelum</th>
                        <th>Sesudah</th>
                        <th>Alasan</th>
                        <th>Status Approval</th>
                        <th>Penyetuju</th>
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
                                    <?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['waktu']))) ?>
                                </td>
                                <td>#<?= (int) $log['id_parameter'] ?></td>
                                <td>
                                    <span class="badge badge-process" style="font-size:0.78rem;">
                                        <?= htmlspecialchars(repaymentParameterAuditActionLabel($log['aksi'])) ?>
                                    </span>
                                </td>
                                <td style="font-size:0.85rem;">
                                    <?= htmlspecialchars($log['user_nama'] ?? '-') ?><br>
                                    <span style="color:#64748b;"><?= htmlspecialchars(auditRoleLabel($log['role_user'] ?? '', $roleLabels)) ?></span>
                                </td>
                                <td style="font-size:0.8rem; max-width:200px;"><?= htmlspecialchars(formatRepaymentParameterAuditSnapshot($log['nilai_sebelum'])) ?></td>
                                <td style="font-size:0.8rem; max-width:200px;"><?= htmlspecialchars(formatRepaymentParameterAuditSnapshot($log['nilai_sesudah'])) ?></td>
                                <td style="font-size:0.85rem; max-width:220px;"><?= nl2br(htmlspecialchars($log['alasan_perubahan'] ?? '-')) ?></td>
                                <td style="font-size:0.85rem;">
                                    <?= htmlspecialchars($approvalLabels[$log['status_approval'] ?? ''] ?? ($log['status_approval'] ?? '-')) ?>
                                </td>
                                <td style="font-size:0.85rem;">
                                    <?php if (!empty($log['approver_nama'])): ?>
                                        <?= htmlspecialchars($log['approver_nama']) ?><br>
                                        <span style="color:#64748b;"><?= htmlspecialchars(auditRoleLabel($log['role_penyetuju'] ?? '', $roleLabels)) ?></span>
                                    <?php else: ?>
                                        -
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
                    if ($idParameter > 0) {
                        $q['id_parameter'] = $idParameter;
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
