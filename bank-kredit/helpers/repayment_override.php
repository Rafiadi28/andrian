<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && @realpath((string) $_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/credit_helper.php';

/**
 * Hanya Direksi yang boleh override repayment per pengajuan.
 */
function canApplyRepaymentOverride() {
    return in_array((string) ($_SESSION['role'] ?? ''), ['direksi', 'direktur_utama'], true);
}

function requireRepaymentOverrideAccess() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
    if (!canApplyRepaymentOverride()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hanya Direksi yang dapat melakukan override repayment.']);
        exit;
    }
}

/**
 * @param array<string, mixed> $row Baris pengajuan_kredit
 */
function isRepaymentOverrideActive(array $row) {
    return (int) ($row['repayment_override_aktif'] ?? 0) === 1;
}

/**
 * @param array<string, mixed> $row
 * @return array{aktif:bool,nilai_efektif:float,nilai_dihitung:float,nilai_override:float,alasan:string,override_at:?string,override_by_nama:?string}
 */
function getRepaymentOverrideInfo(array $row) {
    $aktif = isRepaymentOverrideActive($row);
    $dihitung = (float) ($row['repayment_capacity_dihitung'] ?? $row['repayment_capacity'] ?? 0);
    $overrideNilai = (float) ($row['repayment_override_nilai'] ?? 0);
    return [
        'aktif' => $aktif,
        'nilai_efektif' => $aktif ? $overrideNilai : (float) ($row['repayment_capacity'] ?? 0),
        'nilai_dihitung' => $dihitung,
        'nilai_override' => $overrideNilai,
        'alasan' => trim((string) ($row['repayment_override_alasan'] ?? '')),
        'override_at' => $row['repayment_override_at'] ?? null,
        'override_by_nama' => $row['repayment_override_by_nama'] ?? null,
    ];
}

/**
 * Direksi boleh override jika pengajuan sudah diajukan (bukan draft murni).
 */
function canOverrideRepaymentForPengajuan(array $row) {
    if (!canApplyRepaymentOverride()) {
        return false;
    }
    $status = (string) ($row['status_pengajuan'] ?? '');
    return $status !== 'draft';
}

function fetchPengajuanForRepaymentOverride(PDO $pdo, $idPengajuan) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nama AS repayment_override_by_nama
        FROM pengajuan_kredit p
        LEFT JOIN users u ON u.id_user = p.repayment_override_by
        WHERE p.id_pengajuan = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $idPengajuan]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Terapkan override repayment untuk satu pengajuan (tidak mengubah master parameter).
 *
 * @return array{success:bool,message:string}
 */
function applyRepaymentOverride(PDO $pdo, $idPengajuan, $userId, $nilaiOverride, $alasan) {
    $id = (int) $idPengajuan;
    $uid = (int) $userId;
    $alasan = trim((string) $alasan);

    if ($id <= 0) {
        return ['success' => false, 'message' => 'ID pengajuan tidak valid.'];
    }
    if ($alasan === '') {
        return ['success' => false, 'message' => 'Alasan override wajib diisi.'];
    }
    if (mb_strlen($alasan) < 10) {
        return ['success' => false, 'message' => 'Alasan override minimal 10 karakter.'];
    }

    try {
        $nilaiOverride = validate_currency($nilaiOverride, 'Repayment Capacity override');
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }

    if ($nilaiOverride <= 0) {
        return ['success' => false, 'message' => 'Nilai override harus lebih dari 0.'];
    }

    $row = fetchPengajuanForRepaymentOverride($pdo, $id);
    if (!$row) {
        return ['success' => false, 'message' => 'Pengajuan tidak ditemukan.'];
    }
    if (!canOverrideRepaymentForPengajuan($row)) {
        return ['success' => false, 'message' => 'Override tidak diizinkan untuk pengajuan ini.'];
    }

    $nilaiDihitung = (float) ($row['repayment_capacity_dihitung'] ?? 0);
    if ($nilaiDihitung <= 0) {
        $nilaiDihitung = (float) ($row['repayment_capacity'] ?? 0);
    }

    $angsuran = (float) ($row['angsuran_diajukan'] ?? 0);
    $statusKelayakan = $row['status_kelayakan'] ?? '';
    if ($angsuran > 0) {
        $statusKelayakan = ($nilaiOverride >= $angsuran) ? 'LAYAK' : 'TIDAK LAYAK';
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE pengajuan_kredit SET
                repayment_capacity_dihitung = ?,
                repayment_capacity = ?,
                repayment_override_aktif = 1,
                repayment_override_nilai = ?,
                repayment_override_alasan = ?,
                repayment_override_by = ?,
                repayment_override_at = NOW(),
                status_kelayakan = ?
            WHERE id_pengajuan = ?
        ");
        $stmt->execute([
            $nilaiDihitung,
            $nilaiOverride,
            $nilaiOverride,
            $alasan,
            $uid,
            $statusKelayakan,
            $id,
        ]);

        $namaDebitur = $row['nama_debitur'] ?? '-';
        log_activity(
            $pdo,
            $uid,
            sprintf(
                'OVERRIDE REPAYMENT pengajuan #%d (%s): Rp %s → Rp %s. Alasan: %s',
                $id,
                $namaDebitur,
                number_format($nilaiDihitung, 0, ',', '.'),
                number_format($nilaiOverride, 0, ',', '.'),
                $alasan
            )
        );

        return ['success' => true, 'message' => 'Override repayment berhasil diterapkan.'];
    } catch (Throwable $e) {
        error_log('applyRepaymentOverride: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal menyimpan override repayment.'];
    }
}

/**
 * Cabut override — kembalikan ke nilai hasil perhitungan sistem.
 *
 * @return array{success:bool,message:string}
 */
function revokeRepaymentOverride(PDO $pdo, $idPengajuan, $userId, $catatan = '') {
    $id = (int) $idPengajuan;
    $uid = (int) $userId;

    $row = fetchPengajuanForRepaymentOverride($pdo, $id);
    if (!$row) {
        return ['success' => false, 'message' => 'Pengajuan tidak ditemukan.'];
    }
    if (!canOverrideRepaymentForPengajuan($row)) {
        return ['success' => false, 'message' => 'Tidak diizinkan mencabut override untuk pengajuan ini.'];
    }
    if (!isRepaymentOverrideActive($row)) {
        return ['success' => false, 'message' => 'Pengajuan ini tidak memiliki override aktif.'];
    }

    $nilaiDihitung = (float) ($row['repayment_capacity_dihitung'] ?? $row['repayment_capacity'] ?? 0);
    $angsuran = (float) ($row['angsuran_diajukan'] ?? 0);
    $statusKelayakan = $row['status_kelayakan'] ?? '';
    if ($angsuran > 0) {
        $statusKelayakan = ($nilaiDihitung >= $angsuran) ? 'LAYAK' : 'TIDAK LAYAK';
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE pengajuan_kredit SET
                repayment_capacity = ?,
                repayment_override_aktif = 0,
                repayment_override_nilai = NULL,
                repayment_override_alasan = NULL,
                repayment_override_by = NULL,
                repayment_override_at = NULL,
                status_kelayakan = ?
            WHERE id_pengajuan = ?
        ");
        $stmt->execute([$nilaiDihitung, $statusKelayakan, $id]);

        $catatan = trim((string) $catatan);
        $logCatatan = $catatan !== '' ? " Catatan: {$catatan}" : '';
        log_activity(
            $pdo,
            $uid,
            sprintf(
                'CABUT OVERRIDE REPAYMENT pengajuan #%d (%s): kembali ke Rp %s.%s',
                $id,
                $row['nama_debitur'] ?? '-',
                number_format($nilaiDihitung, 0, ',', '.'),
                $logCatatan
            )
        );

        return ['success' => true, 'message' => 'Override repayment berhasil dicabut.'];
    } catch (Throwable $e) {
        error_log('revokeRepaymentOverride: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal mencabut override repayment.'];
    }
}

/**
 * Simpan hasil hitung analis — hormati override Direksi jika masih aktif.
 *
 * @return array{rpc:float,id_parameter:?int,skip_capacity_update:bool}
 */
function persistRepaymentCalculationForPengajuan(PDO $pdo, $idPengajuan, $jenisKredit, array $context) {
    $result = hitungRepaymentUntukPengajuan($pdo, $jenisKredit, (int) $idPengajuan, $context);
    $row = fetchPengajuanForRepaymentOverride($pdo, (int) $idPengajuan);
    $overrideActive = $row && isRepaymentOverrideActive($row);

    $basis_amount = resolveRepaymentBasisAmount($result['config']['dasar_perhitungan'] ?? '', $context);

    $snapshot = [
        'jenis_kredit' => $jenisKredit,
        'dasar_perhitungan' => $result['config']['dasar_perhitungan'] ?? 'net_cashflow',
        'persen_maks_angsuran' => $result['config']['persen_maks_angsuran'] ?? 75.0,
        'nilai_basis' => $basis_amount,
        'repayment_capacity_dihitung' => $result['rpc'],
        'id_parameter_repayment' => $result['id_parameter'],
        'tanggal_berlaku' => $result['config']['tgl_efektif'] ?? null,
    ];

    try {
        $stmt_audit = $pdo->prepare("
            INSERT INTO audit_repayment_analisa (
                id_pengajuan, id_analis, nama_analis, tanggal_analisa, 
                jenis_kredit, dasar_perhitungan, persen_digunakan, nilai_basis, 
                maksimal_angsuran, override_aktif, id_override_by, nama_override_by
            ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_audit->execute([
            $idPengajuan,
            $_SESSION['id_user'] ?? null,
            $_SESSION['nama'] ?? null,
            $jenisKredit,
            $snapshot['dasar_perhitungan'],
            $snapshot['persen_maks_angsuran'],
            $snapshot['nilai_basis'],
            $snapshot['repayment_capacity_dihitung'],
            $overrideActive ? 1 : 0,
            $row['repayment_override_by'] ?? null,
            $row['repayment_override_by_nama'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('Gagal menyimpan audit trail repayment: ' . $e->getMessage());
    }

    return [
        'rpc' => $overrideActive ? (float) ($row['repayment_capacity'] ?? 0) : $result['rpc'],
        'rpc_dihitung' => $result['rpc'],
        'id_parameter' => $result['id_parameter'],
        'skip_capacity_update' => $overrideActive,
        'snapshot_json' => json_encode($snapshot)
    ];
}
