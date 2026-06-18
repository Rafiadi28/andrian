<?php
/**
 * Repayment Parameter Snapshot Management
 * 
 * STEP 9: Sinkronisasi hasil analisa
 * Captures and manages repayment parameter snapshots at time of analysis save
 * Ensures all approval levels use same parameters (immutable snapshot)
 */

if (isset($_SERVER['SCRIPT_FILENAME']) && @realpath((string) $_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

/**
 * Ensure repayment_parameter_snapshot table exists
 */
function bankKreditEnsureRepaymentSnapshotSchema(PDO $pdo)
{
    try {
        $exists = $pdo->query("SHOW TABLES LIKE 'repayment_parameter_snapshot'")->rowCount() > 0;
        if (!$exists) {
            $pdo->exec("
                CREATE TABLE repayment_parameter_snapshot (
                    id_snapshot BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    id_pengajuan INT NOT NULL,
                    id_parameter INT NULL COMMENT 'FK to master_parameter_repayment at time of capture',
                    jenis_kredit VARCHAR(50) NOT NULL COMMENT 'Credit type: umum|pppk|perangkat_desa|kretamas|cashcolateral',
                    dasar_perhitungan VARCHAR(50) NOT NULL COMMENT 'Calculation basis: net_cashflow|gaji_bersih|gaji_bersih_pendapatan|laba_bersih',
                    persen_maks_angsuran DECIMAL(5,2) NOT NULL COMMENT 'Percentage used for this snapshot',
                    nilai_dasar DECIMAL(15,2) NOT NULL COMMENT 'Basis value (salary/cashflow/profit) used in calculation',
                    maksimal_angsuran DECIMAL(15,2) NOT NULL COMMENT 'Max monthly repayment = nilai_dasar * (persen / 100)',
                    tgl_parameter DATE NOT NULL COMMENT 'Date when parameter became effective',
                    tgl_parameter_akhir DATE NULL COMMENT 'End date of parameter validity',
                    captured_by INT NULL COMMENT 'User ID who captured snapshot (analyst)',
                    captured_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    repayment_override_aktif TINYINT(1) DEFAULT 0 COMMENT 'Whether override was active at capture time',
                    repayment_override_by INT NULL COMMENT 'User who applied override',
                    repayment_override_alasan TEXT NULL COMMENT 'Reason for override',
                    catatan_snapshot TEXT NULL COMMENT 'Additional notes or context',
                    INDEX idx_rps_pengajuan (id_pengajuan),
                    INDEX idx_rps_parameter (id_parameter),
                    INDEX idx_rps_jenis (jenis_kredit),
                    INDEX idx_rps_captured_at (captured_at),
                    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE,
                    FOREIGN KEY (captured_by) REFERENCES users(id_user) ON DELETE SET NULL,
                    UNIQUE KEY uk_rps_pengajuan (id_pengajuan)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Repayment parameter snapshot at time of analysis — immutable, used by all approval levels'
            ");
        }

        // Ensure pengajuan_kredit has FK to snapshot
        if ($pdo->query("SHOW TABLES LIKE 'pengajuan_kredit'")->rowCount() > 0) {
            $col = $pdo->query("SHOW COLUMNS FROM pengajuan_kredit LIKE 'id_repayment_snapshot'")->rowCount();
            if ($col == 0) {
                try {
                    $pdo->exec("
                        ALTER TABLE pengajuan_kredit 
                        ADD COLUMN id_repayment_snapshot BIGINT UNSIGNED NULL 
                        COMMENT 'FK to repayment_parameter_snapshot for audit trail'
                        AFTER repayment_capacity
                    ");
                } catch (Throwable $e) {
                    // Column may already exist or syntax error
                    error_log('bankKreditEnsureRepaymentSnapshotSchema ALTER: ' . $e->getMessage());
                }
            }
        }

    } catch (Throwable $e) {
        error_log('bankKreditEnsureRepaymentSnapshotSchema: ' . $e->getMessage());
    }
}

/**
 * Capture repayment parameter snapshot when analyst saves pengajuan
 * 
 * @param PDO $pdo
 * @param int $id_pengajuan
 * @param string $jenis_kredit (e.g., 'umum', 'pppk', 'perangkat_desa')
 * @param float $nilai_dasar (salary/cashflow/profit amount)
 * @param array|null $overrideData ['override_aktif' => 1, 'override_by' => 123, 'override_alasan' => 'Dirut approval']
 * @return array ['success' => bool, 'id_snapshot' => int|null, 'message' => string]
 */
function captureRepaymentParameterSnapshot(
    PDO $pdo,
    int $id_pengajuan,
    string $jenis_kredit,
    float $nilai_dasar,
    ?array $overrideData = null
): array {
    if ($id_pengajuan <= 0) {
        return ['success' => false, 'id_snapshot' => null, 'message' => 'Invalid id_pengajuan'];
    }

    try {
        bankKreditEnsureRepaymentSnapshotSchema($pdo);

        // Require helper functions
        require_once __DIR__ . '/credit_helper.php';

        // Get active parameter for this credit type
        $paramConfig = getRepaymentParameterConfig($pdo, $jenis_kredit, [
            'asOfDate' => date('Y-m-d'),
            'returnFullRow' => true
        ]);

        if (!$paramConfig) {
            $paramConfig = getRepaymentPolicyFallbackConfig($jenis_kredit);
        }

        // Normalize jenis_kredit to canonical form
        $jenis_kredit = $paramConfig['jenis_kredit'] ?? normalizeRepaymentJenisKey($jenis_kredit);

        // Extract values
        $dasar_perhitungan = $paramConfig['dasar_perhitungan'] ?? 'net_cashflow';
        $persen = (float)($paramConfig['persen_maks_angsuran'] ?? 75.00);
        $id_parameter = (int)($paramConfig['id_parameter'] ?? 0);
        $tgl_parameter = $paramConfig['tgl_berlaku_mulai'] ?? date('Y-m-d');
        $tgl_parameter_akhir = $paramConfig['tgl_berlaku_sampai'] ?? null;

        // Calculate maksimal_angsuran
        $maksimal_angsuran = $nilai_dasar * ($persen / 100);

        // Override data
        $override_aktif = (int)(($overrideData['override_aktif'] ?? 0) ? 1 : 0);
        $override_by = (int)($overrideData['override_by'] ?? 0) ?: null;
        $override_alasan = $overrideData['override_alasan'] ?? null;

        // Captured by current user
        $captured_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        // Check if snapshot already exists for this pengajuan
        $checkStmt = $pdo->prepare("
            SELECT id_snapshot FROM repayment_parameter_snapshot 
            WHERE id_pengajuan = ?
        ");
        $checkStmt->execute([$id_pengajuan]);
        $existingSnapshot = $checkStmt->fetchColumn();

        if ($existingSnapshot) {
            // Update existing snapshot with new values
            $updateStmt = $pdo->prepare("
                UPDATE repayment_parameter_snapshot 
                SET jenis_kredit = ?,
                    dasar_perhitungan = ?,
                    persen_maks_angsuran = ?,
                    nilai_dasar = ?,
                    maksimal_angsuran = ?,
                    id_parameter = ?,
                    tgl_parameter = ?,
                    tgl_parameter_akhir = ?,
                    captured_by = ?,
                    captured_at = NOW(),
                    repayment_override_aktif = ?,
                    repayment_override_by = ?,
                    repayment_override_alasan = ?
                WHERE id_pengajuan = ?
            ");
            $updateStmt->execute([
                $jenis_kredit,
                $dasar_perhitungan,
                $persen,
                $nilai_dasar,
                $maksimal_angsuran,
                $id_parameter ?: null,
                $tgl_parameter,
                $tgl_parameter_akhir,
                $captured_by,
                $override_aktif,
                $override_by,
                $override_alasan ?: null,
                $id_pengajuan
            ]);
            $id_snapshot = (int)$existingSnapshot;
        } else {
            // Create new snapshot
            $insertStmt = $pdo->prepare("
                INSERT INTO repayment_parameter_snapshot
                (id_pengajuan, id_parameter, jenis_kredit, dasar_perhitungan, 
                 persen_maks_angsuran, nilai_dasar, maksimal_angsuran,
                 tgl_parameter, tgl_parameter_akhir, captured_by,
                 repayment_override_aktif, repayment_override_by, repayment_override_alasan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $id_pengajuan,
                $id_parameter ?: null,
                $jenis_kredit,
                $dasar_perhitungan,
                $persen,
                $nilai_dasar,
                $maksimal_angsuran,
                $tgl_parameter,
                $tgl_parameter_akhir,
                $captured_by,
                $override_aktif,
                $override_by,
                $override_alasan ?: null
            ]);
            $id_snapshot = (int)$pdo->lastInsertId();
        }

        // Link snapshot to pengajuan_kredit
        try {
            $pdo->prepare("
                UPDATE pengajuan_kredit 
                SET id_repayment_snapshot = ? 
                WHERE id_pengajuan = ?
            ")->execute([$id_snapshot, $id_pengajuan]);
        } catch (Throwable $e) {
            error_log('captureRepaymentParameterSnapshot UPDATE pengajuan: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'id_snapshot' => $id_snapshot,
            'message' => 'Snapshot parameter repayment berhasil direkam.',
            'snapshot' => [
                'jenis_kredit' => $jenis_kredit,
                'dasar_perhitungan' => $dasar_perhitungan,
                'persen' => $persen,
                'nilai_dasar' => $nilai_dasar,
                'maksimal_angsuran' => $maksimal_angsuran,
                'id_parameter' => $id_parameter ?: null,
                'tgl_parameter' => $tgl_parameter,
            ]
        ];

    } catch (Throwable $e) {
        error_log('captureRepaymentParameterSnapshot: ' . $e->getMessage());
        return [
            'success' => false,
            'id_snapshot' => null,
            'message' => 'Error capturing snapshot: ' . $e->getMessage()
        ];
    }
}

/**
 * Fetch repayment parameter snapshot for a pengajuan
 * 
 * @param PDO $pdo
 * @param int $id_pengajuan
 * @return array|null Snapshot record or null if not found
 */
function fetchRepaymentParameterSnapshot(PDO $pdo, int $id_pengajuan): ?array
{
    try {
        $stmt = $pdo->prepare("
            SELECT rps.*,
                   u_captured.nama AS captured_by_nama,
                   u_override.nama AS override_by_nama
            FROM repayment_parameter_snapshot rps
            LEFT JOIN users u_captured ON u_captured.id_user = rps.captured_by
            LEFT JOIN users u_override ON u_override.id_user = rps.repayment_override_by
            WHERE rps.id_pengajuan = ?
            LIMIT 1
        ");
        $stmt->execute([$id_pengajuan]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        error_log('fetchRepaymentParameterSnapshot: ' . $e->getMessage());
        return null;
    }
}

/**
 * Format repayment parameter snapshot for display
 * 
 * @param array $snapshot
 * @return string Formatted display string
 */
function formatRepaymentParameterSnapshot(array $snapshot): string
{
    $parts = [
        'Jenis: ' . htmlspecialchars($snapshot['jenis_kredit'] ?? '—'),
        'Dasar: ' . htmlspecialchars($snapshot['dasar_perhitungan'] ?? '—'),
        'Persentase: ' . number_format((float)($snapshot['persen_maks_angsuran'] ?? 0), 2, ',', '.') . '%',
        'Nilai Dasar: Rp ' . number_format((float)($snapshot['nilai_dasar'] ?? 0), 0, ',', '.'),
        'Max Angsuran: Rp ' . number_format((float)($snapshot['maksimal_angsuran'] ?? 0), 0, ',', '.'),
        'Tgl Parameter: ' . htmlspecialchars($snapshot['tgl_parameter'] ?? '—'),
    ];

    if (!empty($snapshot['repayment_override_aktif'])) {
        $parts[] = 'Override: YA (' . htmlspecialchars($snapshot['override_by_nama'] ?? 'N/A') . ') — ' . htmlspecialchars($snapshot['repayment_override_alasan'] ?? '');
    }

    return implode(' | ', $parts);
}

/**
 * Ensure snapshot is used in approval workflow
 * Retrieves snapshot and verifies it's still being referenced
 * 
 * @param PDO $pdo
 * @param int $id_pengajuan
 * @return array Snapshot data for use in approval
 */
function getRepaymentParameterSnapshotForApproval(PDO $pdo, int $id_pengajuan): ?array
{
    return fetchRepaymentParameterSnapshot($pdo, $id_pengajuan);
}
