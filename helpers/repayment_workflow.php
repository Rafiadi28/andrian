<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && @realpath((string) $_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/repayment_rbac.php';
require_once __DIR__ . '/repayment_parameter_audit.php';

/**
 * Tahapan workflow approval parameter repayment (terpisah dari workflow pengajuan kredit).
 *
 * @return array<int, array{key:string,label:string,role:string}>
 */
function getRepaymentWorkflowStages()
{
    return [
        ['key' => 'draft', 'label' => 'Kabag Kredit — Buat Usulan', 'role' => 'kabag_kredit'],
        ['key' => 'menunggu', 'label' => 'Kadiv — Review & Approval Tahap 1', 'role' => 'kadiv'],
        ['key' => 'disetujui_kadiv', 'label' => 'Direksi — Approval Final', 'role' => 'direksi'],
        ['key' => 'disetujui', 'label' => 'Parameter Aktif', 'role' => 'sistem'],
    ];
}

/**
 * Label status tampilan termasuk kondisi dikembalikan ke Kabag.
 */
function getRepaymentWorkflowDisplayStatus(array $row)
{
    $status = (string) ($row['status_approval'] ?? 'draft');

    if ($status === 'draft' && !empty($row['catatan_kadiv'])) {
        return 'Dikembalikan ke Kabag Kredit (revisi)';
    }
    if ($status === 'ditolak') {
        return 'Ditolak Direksi — Kembali ke Kabag Kredit';
    }

    $labels = getRepaymentApprovalStatusLabels();
    return $labels[$status] ?? $status;
}

/**
 * Indeks tahap aktif untuk diagram workflow (0–3).
 */
function getRepaymentWorkflowStepIndex(array $row)
{
    $map = [
        'draft' => 0,
        'ditolak' => 0,
        'menunggu' => 1,
        'disetujui_kadiv' => 2,
        'disetujui' => 3,
    ];

    return $map[$row['status_approval'] ?? 'draft'] ?? 0;
}

function repaymentWorkflowEnsureLogTable(PDO $pdo)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $exists = $pdo->query("SHOW TABLES LIKE 'repayment_parameter_workflow_log'")->rowCount() > 0;
        if (!$exists) {
            $pdo->exec("
                CREATE TABLE repayment_parameter_workflow_log (
                    id_log INT AUTO_INCREMENT PRIMARY KEY,
                    id_parameter INT NOT NULL,
                    aksi VARCHAR(50) NOT NULL,
                    status_dari VARCHAR(32) NULL,
                    status_ke VARCHAR(32) NOT NULL,
                    id_user INT NULL,
                    catatan TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_rpw_param (id_parameter),
                    INDEX idx_rpw_waktu (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (Throwable $e) {
        error_log('repaymentWorkflowEnsureLogTable: ' . $e->getMessage());
    }
}

function repaymentWorkflowLog(PDO $pdo, int $idParameter, string $aksi, ?string $statusDari, string $statusKe, ?int $userId, ?string $catatan = null)
{
    repaymentWorkflowEnsureLogTable($pdo);
    try {
        $stmt = $pdo->prepare("
            INSERT INTO repayment_parameter_workflow_log
                (id_parameter, aksi, status_dari, status_ke, id_user, catatan)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$idParameter, $aksi, $statusDari, $statusKe, $userId, $catatan]);
    } catch (Throwable $e) {
        error_log('repaymentWorkflowLog: ' . $e->getMessage());
    }
}

/**
 * Catat audit lengkap untuk aksi workflow parameter.
 */
function repaymentWorkflowAudit(
    PDO $pdo,
    int $idParameter,
    string $aksi,
    ?array $beforeRow,
    ?array $afterRow,
    ?int $userId,
    ?string $alasan = null,
    ?string $statusApproval = null,
    ?int $approverId = null
) {
    logRepaymentParameterAudit($pdo, [
        'id_parameter' => $idParameter,
        'aksi' => $aksi,
        'id_user' => $userId,
        'nilai_sebelum' => repaymentParameterAuditSnapshot($beforeRow),
        'nilai_sesudah' => repaymentParameterAuditSnapshot($afterRow),
        'alasan_perubahan' => $alasan,
        'status_approval' => $statusApproval ?? ($afterRow['status_approval'] ?? null),
        'id_user_penyetuju' => $approverId,
        'role_penyetuju' => $approverId ? repaymentParameterAuditResolveRoleFromDb($pdo, $approverId) : null,
    ]);
}

/**
 * Kabag mengajukan usulan ke Kadiv.
 */
function repaymentWorkflowSubmit(PDO $pdo, int $id, int $userId)
{
    $row = fetchRepaymentParameterById($pdo, $id);
    if (!$row || !canSubmitRepaymentProposal($row)) {
        return [false, 'Usulan tidak dapat diajukan pada status ini.'];
    }

    $from = $row['status_approval'];
    $stmt = $pdo->prepare("
        UPDATE master_parameter_repayment
        SET status_approval = 'menunggu',
            submitted_by = ?, submitted_at = NOW(),
            approved_kadiv_by = NULL, approved_kadiv_at = NULL,
            approved_by = NULL, approved_at = NULL,
            catatan_kadiv = NULL, catatan_direksi = NULL
        WHERE id_parameter = ?
    ");
    $stmt->execute([$userId, $id]);
    repaymentWorkflowLog($pdo, $id, 'ajukan_usulan', $from, 'menunggu', $userId, null);
    $after = fetchRepaymentParameterById($pdo, $id);
    repaymentWorkflowAudit(
        $pdo,
        $id,
        'ajukan_usulan',
        $row,
        $after,
        $userId,
        $row['keterangan_kebijakan'] ?? null,
        'menunggu'
    );

    return [true, 'Usulan perubahan berhasil diajukan ke Kadiv Kredit/Bisnis.'];
}

/**
 * Kadiv — persetujuan tahap 1.
 */
function repaymentWorkflowApproveKadiv(PDO $pdo, int $id, int $userId, ?string $catatan)
{
    $row = fetchRepaymentParameterById($pdo, $id);
    if (!$row || !canApproveRepaymentKadiv($row)) {
        return [false, 'Persetujuan Kadiv tidak dapat dilakukan pada tahap ini.'];
    }

    $from = $row['status_approval'];
    $stmt = $pdo->prepare("
        UPDATE master_parameter_repayment
        SET status_approval = 'disetujui_kadiv',
            approved_kadiv_by = ?, approved_kadiv_at = NOW(), catatan_kadiv = ?
        WHERE id_parameter = ?
    ");
    $stmt->execute([$userId, $catatan !== '' && $catatan !== null ? $catatan : null, $id]);
    repaymentWorkflowLog($pdo, $id, 'setujui_kadiv', $from, 'disetujui_kadiv', $userId, $catatan);
    $after = fetchRepaymentParameterById($pdo, $id);
    repaymentWorkflowAudit(
        $pdo,
        $id,
        'setujui_kadiv',
        $row,
        $after,
        $userId,
        $catatan,
        'disetujui_kadiv',
        $userId
    );

    return [true, 'Persetujuan tahap pertama berhasil. Menunggu persetujuan akhir Direksi.'];
}

/**
 * Direksi — persetujuan akhir & aktivasi.
 */
function repaymentWorkflowApproveDireksi(PDO $pdo, int $id, int $userId, ?string $catatan)
{
    $row = fetchRepaymentParameterById($pdo, $id);
    if (!$row || !canApproveRepaymentFinal($row)) {
        return [false, 'Persetujuan akhir tidak dapat dilakukan (kasus khusus: usulan belum valid/aktif).'];
    }

    $from = $row['status_approval'];
    $stmt = $pdo->prepare("
        UPDATE master_parameter_repayment
        SET status_approval = 'disetujui', status = 'aktif',
            approved_by = ?, approved_at = NOW(), catatan_direksi = ?
        WHERE id_parameter = ?
    ");
    $stmt->execute([$userId, $catatan !== '' && $catatan !== null ? $catatan : null, $id]);
    repaymentWorkflowLog($pdo, $id, 'aktifkan_direksi', $from, 'disetujui', $userId, $catatan);
    $after = fetchRepaymentParameterById($pdo, $id);
    repaymentWorkflowAudit(
        $pdo,
        $id,
        'aktifkan_direksi',
        $row,
        $after,
        $userId,
        $catatan ?: ($row['keterangan_kebijakan'] ?? null),
        'disetujui',
        $userId
    );

    return [true, 'Parameter disetujui dan diaktifkan untuk perhitungan sistem.'];
}

/**
 * Kadiv menolak — dikembalikan ke Kabag Kredit (status draft).
 */
function repaymentWorkflowRejectKadiv(PDO $pdo, int $id, int $userId, ?string $catatan)
{
    $row = fetchRepaymentParameterById($pdo, $id);
    if (!$row || !canRejectRepaymentKadiv($row)) {
        return [false, 'Penolakan Kadiv tidak dapat dilakukan pada tahap ini.'];
    }

    $from = $row['status_approval'];
    $stmt = $pdo->prepare("
        UPDATE master_parameter_repayment
        SET status_approval = 'draft', status = 'nonaktif',
            submitted_by = NULL, submitted_at = NULL,
            approved_kadiv_by = NULL, approved_kadiv_at = NULL,
            approved_by = NULL, approved_at = NULL,
            catatan_kadiv = ?
        WHERE id_parameter = ?
    ");
    $stmt->execute([$catatan !== '' && $catatan !== null ? $catatan : null, $id]);
    repaymentWorkflowLog($pdo, $id, 'tolak_kadiv', $from, 'draft', $userId, $catatan);
    $after = fetchRepaymentParameterById($pdo, $id);
    repaymentWorkflowAudit(
        $pdo,
        $id,
        'tolak_kadiv',
        $row,
        $after,
        $userId,
        $catatan,
        'draft',
        $userId
    );

    return [true, 'Usulan dikembalikan ke Kabag Kredit untuk revisi.'];
}

/**
 * Direksi menolak — status ditolak, Kabag dapat revisi & ajukan ulang.
 */
function repaymentWorkflowRejectDireksi(PDO $pdo, int $id, int $userId, ?string $catatan)
{
    $row = fetchRepaymentParameterById($pdo, $id);
    if (!$row || !canRejectRepaymentDireksi($row)) {
        return [false, 'Penolakan Direksi tidak dapat dilakukan pada tahap ini.'];
    }

    $from = $row['status_approval'];
    $stmt = $pdo->prepare("
        UPDATE master_parameter_repayment
        SET status_approval = 'ditolak', status = 'nonaktif',
            approved_by = NULL, approved_at = NULL,
            catatan_direksi = ?
        WHERE id_parameter = ?
    ");
    $stmt->execute([$catatan !== '' && $catatan !== null ? $catatan : null, $id]);
    repaymentWorkflowLog($pdo, $id, 'tolak_direksi', $from, 'ditolak', $userId, $catatan);
    $after = fetchRepaymentParameterById($pdo, $id);
    repaymentWorkflowAudit(
        $pdo,
        $id,
        'tolak_direksi',
        $row,
        $after,
        $userId,
        $catatan,
        'ditolak',
        $userId
    );

    return [true, 'Parameter ditolak. Usulan dikembalikan ke Kabag Kredit untuk perbaikan.'];
}

/**
 * @return array<int, array<string, mixed>>
 */
function fetchRepaymentWorkflowLog(PDO $pdo, int $idParameter, int $limit = 20)
{
    repaymentWorkflowEnsureLogTable($pdo);
    try {
        $stmt = $pdo->prepare("
            SELECT l.*, u.nama AS user_nama
            FROM repayment_parameter_workflow_log l
            LEFT JOIN users u ON u.id_user = l.id_user
            WHERE l.id_parameter = ?
            ORDER BY l.created_at DESC, l.id_log DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $idParameter, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('fetchRepaymentWorkflowLog: ' . $e->getMessage());
        return [];
    }
}
