<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && @realpath((string) $_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

/**
 * Audit log parameter repayment — append-only, tidak boleh diubah/dihapus via aplikasi.
 */

function repaymentParameterAuditEnsureTable(PDO $pdo)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (function_exists('bankKreditEnsureRepaymentParameterAuditSchema')) {
        bankKreditEnsureRepaymentParameterAuditSchema($pdo);
    }
}

/**
 * Snapshot nilai parameter untuk perbandingan sebelum/sesudah.
 *
 * @param array<string, mixed>|null $row
 * @return array<string, mixed>|null
 */
function repaymentParameterAuditSnapshot($row)
{
    if (!$row || !is_array($row)) {
        return null;
    }

    return [
        'id_parameter' => isset($row['id_parameter']) ? (int) $row['id_parameter'] : null,
        'jenis_kredit' => $row['jenis_kredit'] ?? null,
        'dasar_perhitungan' => $row['dasar_perhitungan'] ?? null,
        'persen_maks_angsuran' => isset($row['persen_maks_angsuran']) ? (float) $row['persen_maks_angsuran'] : null,
        'status' => $row['status'] ?? null,
        'tgl_berlaku_mulai' => $row['tgl_berlaku_mulai'] ?? null,
        'tgl_berlaku_sampai' => $row['tgl_berlaku_sampai'] ?? null,
        'keterangan_kebijakan' => $row['keterangan_kebijakan'] ?? null,
        'status_approval' => $row['status_approval'] ?? null,
    ];
}

/**
 * @return array<string, mixed>
 */
function repaymentParameterAuditSnapshotFromForm(
    $jenis,
    $dasar,
    $persen,
    $status,
    $tglMulai,
    $tglSampai,
    $keterangan,
    $statusApproval = 'draft',
    $idParameter = null
) {
    $snap = [
        'jenis_kredit' => $jenis,
        'dasar_perhitungan' => $dasar,
        'persen_maks_angsuran' => (float) $persen,
        'status' => $status,
        'tgl_berlaku_mulai' => $tglMulai,
        'tgl_berlaku_sampai' => $tglSampai,
        'keterangan_kebijakan' => $keterangan !== '' ? $keterangan : null,
        'status_approval' => $statusApproval,
    ];
    if ($idParameter !== null) {
        $snap['id_parameter'] = (int) $idParameter;
    }
    return $snap;
}

function repaymentParameterAuditActionLabel($aksi)
{
    $labels = [
        'buat_draft' => 'Buat Draft',
        'ubah_draft' => 'Ubah Draft',
        'ajukan_usulan' => 'Ajukan Usulan',
        'setujui_kadiv' => 'Disetujui Kadiv',
        'aktifkan_direksi' => 'Diaktifkan Direksi',
        'tolak_kadiv' => 'Dikembalikan Kadiv',
        'tolak_direksi' => 'Ditolak Direksi',
        'hapus_draft' => 'Hapus Draft',
        'simpan_draft' => 'Simpan Draft',
    ];
    return $labels[$aksi] ?? (string) $aksi;
}

function repaymentParameterAuditResolveRole($userId, $fallbackRole = null)
{
    if ($fallbackRole !== null && $fallbackRole !== '') {
        return (string) $fallbackRole;
    }
    if ((int) $userId > 0 && (int) ($userId) === (int) ($_SESSION['user_id'] ?? 0)) {
        return (string) ($_SESSION['role'] ?? '');
    }
    return '';
}

function repaymentParameterAuditResolveRoleFromDb(PDO $pdo, $userId)
{
    $uid = (int) $userId;
    if ($uid <= 0) {
        return '';
    }
    try {
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id_user = ? LIMIT 1');
        $stmt->execute([$uid]);
        return (string) ($stmt->fetchColumn() ?: '');
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Catat audit log parameter repayment (INSERT-only).
 *
 * @param array{
 *   id_parameter:int,
 *   aksi:string,
 *   id_user?:int|null,
 *   role_user?:string|null,
 *   nilai_sebelum?:array|null,
 *   nilai_sesudah?:array|null,
 *   alasan_perubahan?:string|null,
 *   status_approval?:string|null,
 *   id_user_penyetuju?:int|null,
 *   role_penyetuju?:string|null
 * } $entry
 */
function logRepaymentParameterAudit(PDO $pdo, array $entry)
{
    repaymentParameterAuditEnsureTable($pdo);

    $idParameter = (int) ($entry['id_parameter'] ?? 0);
    if ($idParameter <= 0) {
        return false;
    }

    $userId = isset($entry['id_user']) ? (int) $entry['id_user'] : null;
    $roleUser = trim((string) ($entry['role_user'] ?? ''));
    if ($roleUser === '' && $userId > 0) {
        $roleUser = repaymentParameterAuditResolveRoleFromDb($pdo, $userId);
    }
    if ($roleUser === '' && $userId > 0) {
        $roleUser = repaymentParameterAuditResolveRole($userId);
    }

    $approverId = isset($entry['id_user_penyetuju']) ? (int) $entry['id_user_penyetuju'] : null;
    $approverRole = trim((string) ($entry['role_penyetuju'] ?? ''));
    if ($approverRole === '' && $approverId > 0) {
        $approverRole = repaymentParameterAuditResolveRoleFromDb($pdo, $approverId);
    }

    $sebelum = $entry['nilai_sebelum'] ?? null;
    $sesudah = $entry['nilai_sesudah'] ?? null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO repayment_parameter_audit_log
                (id_parameter, aksi, id_user, role_user,
                 nilai_sebelum, nilai_sesudah, alasan_perubahan, status_approval,
                 id_user_penyetuju, role_penyetuju)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $idParameter,
            (string) ($entry['aksi'] ?? 'perubahan'),
            $userId > 0 ? $userId : null,
            $roleUser !== '' ? $roleUser : null,
            $sebelum !== null ? json_encode($sebelum, JSON_UNESCAPED_UNICODE) : null,
            $sesudah !== null ? json_encode($sesudah, JSON_UNESCAPED_UNICODE) : null,
            ($entry['alasan_perubahan'] ?? null) !== '' ? (string) $entry['alasan_perubahan'] : null,
            ($entry['status_approval'] ?? null) !== '' ? (string) $entry['status_approval'] : null,
            $approverId > 0 ? $approverId : null,
            $approverRole !== '' ? $approverRole : null,
        ]);
        return true;
    } catch (Throwable $e) {
        error_log('logRepaymentParameterAudit: ' . $e->getMessage());
        return false;
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function fetchRepaymentParameterAuditLog(PDO $pdo, $idParameter = null, $limit = 50, $offset = 0)
{
    repaymentParameterAuditEnsureTable($pdo);
    $limit = max(1, min(200, (int) $limit));
    $offset = max(0, (int) $offset);

    try {
        if ($idParameter !== null && (int) $idParameter > 0) {
            $stmt = $pdo->prepare("
                SELECT a.*,
                       u.nama AS user_nama,
                       ua.nama AS approver_nama
                FROM repayment_parameter_audit_log a
                LEFT JOIN users u ON u.id_user = a.id_user
                LEFT JOIN users ua ON ua.id_user = a.id_user_penyetuju
                WHERE a.id_parameter = ?
                ORDER BY a.waktu DESC, a.id_audit DESC
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute([(int) $idParameter]);
        } else {
            $stmt = $pdo->prepare("
                SELECT a.*,
                       u.nama AS user_nama,
                       ua.nama AS approver_nama
                FROM repayment_parameter_audit_log a
                LEFT JOIN users u ON u.id_user = a.id_user
                LEFT JOIN users ua ON ua.id_user = a.id_user_penyetuju
                ORDER BY a.waktu DESC, a.id_audit DESC
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('fetchRepaymentParameterAuditLog: ' . $e->getMessage());
        return [];
    }
}

function countRepaymentParameterAuditLog(PDO $pdo, $idParameter = null)
{
    repaymentParameterAuditEnsureTable($pdo);
    try {
        if ($idParameter !== null && (int) $idParameter > 0) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM repayment_parameter_audit_log WHERE id_parameter = ?');
            $stmt->execute([(int) $idParameter]);
        } else {
            $stmt = $pdo->query('SELECT COUNT(*) FROM repayment_parameter_audit_log');
        }
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Format snapshot untuk tampilan ringkas.
 */
function formatRepaymentParameterAuditSnapshot($jsonOrArray)
{
    if ($jsonOrArray === null || $jsonOrArray === '') {
        return '-';
    }
    $data = is_array($jsonOrArray) ? $jsonOrArray : json_decode((string) $jsonOrArray, true);
    if (!is_array($data)) {
        return '-';
    }
    $parts = [];
    if (!empty($data['jenis_kredit'])) {
        $parts[] = 'Jenis: ' . $data['jenis_kredit'];
    }
    if (!empty($data['dasar_perhitungan'])) {
        $parts[] = 'Dasar: ' . $data['dasar_perhitungan'];
    }
    if (isset($data['persen_maks_angsuran'])) {
        $parts[] = '%: ' . rtrim(rtrim(number_format((float) $data['persen_maks_angsuran'], 2, '.', ''), '0'), '.');
    }
    if (!empty($data['tgl_berlaku_mulai'])) {
        $parts[] = 'Efektif: ' . $data['tgl_berlaku_mulai'];
    }
    if (!empty($data['status_approval'])) {
        $parts[] = 'Approval: ' . $data['status_approval'];
    }
    return $parts !== [] ? implode(' | ', $parts) : '-';
}
