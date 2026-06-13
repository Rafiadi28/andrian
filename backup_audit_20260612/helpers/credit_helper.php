<?php
/**
 * ============================================================
 * CREDIT ANALYSIS HELPER FUNCTIONS
 * ============================================================
 * Banking-grade helper functions untuk validasi, scoring, dan
 * perhitungan kapasitas repayment. Semua operasi menggunakan
 * PDO prepared statements dan server-side validation.
 * 
 * Version: 1.0 (Banking Standard)
 * Last Updated: May 2, 2026
 * ============================================================
 */

// Prevent direct access
if (isset($_SERVER['SCRIPT_FILENAME']) && @realpath((string) $_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

/**
 * Validate kriteria score (1-5 scale)
 * 1 = Sangat Baik (best)
 * 5 = Sangat Kurang (worst)
 * 
 * @param mixed $kriteria The score value to validate
 * @return true|string Returns true if valid, error message if invalid
 */
function validate_kriteria($kriteria) {
    if ($kriteria === null || $kriteria === '') {
        return "Kriteria wajib diisi";
    }

    if (!is_numeric($kriteria)) {
        return "Kriteria harus berupa angka";
    }

    $nilai = (int)$kriteria;
    if ($nilai < 1 || $nilai > 5) {
        return "Kriteria harus 1 sampai 5 (1=Sangat Baik, 5=Sangat Kurang)";
    }

    return true;
}

/**
 * Get grade and description for a score (1-5)
 * 
 * @param int $skor The score (1-5)
 * @return array|null Array with 'grade' and 'keterangan', or null if invalid
 */
function get_grade($skor) {
    $gradeMap = [
        1 => ['grade' => 'A', 'keterangan' => 'Sangat Baik'],
        2 => ['grade' => 'B', 'keterangan' => 'Baik'],
        3 => ['grade' => 'C', 'keterangan' => 'Cukup'],
        4 => ['grade' => 'D', 'keterangan' => 'Kurang'],
        5 => ['grade' => 'E', 'keterangan' => 'Sangat Kurang']
    ];

    $skor = (int)$skor;
    return $gradeMap[$skor] ?? null;
}

/**
 * Calculate 6C analysis (Character, Capacity, Capital, Collateral, Condition, Constraint)
 * 
 * Returns detailed scoring with:
 * - Individual scores (1-5) per criteria
 * - Grades (A-E) for each
 * - Average score
 * - Overall classification
 * 
 * @param array $data Array with keys: character, capacity, capital, collateral, condition, constraint
 * @return array Result with 'detail', 'total', 'rata', or 'error' key
 */
function hitung_6c($data) {
    $total = 0;
    $detail = [];
    $requiredKeys = ['character', 'capacity', 'capital', 'collateral', 'condition', 'constraint'];

    // Validate all keys exist and are valid
    foreach ($requiredKeys as $key) {
        $val = $data[$key] ?? null;
        
        $valid = validate_kriteria($val);
        if ($valid !== true) {
            return ['error' => "Komponen $key: $valid"];
        }

        $skor = (int)$val;
        $grade = get_grade($skor);

        $detail[$key] = [
            'skor' => $skor,
            'grade' => $grade['grade'],
            'keterangan' => $grade['keterangan']
        ];

        $total += $skor;
    }

    $rata = $total / count($requiredKeys);

    return [
        'detail' => $detail,
        'total' => $total,
        'rata' => round($rata, 2),
        'klasifikasi' => klasifikasi_6c($rata)
    ];
}

/**
 * Classify overall 6C score (average)
 * 
 * RULES:
 * - <= 1.5 = Sangat Baik (average skor mendekati 1)
 * - <= 2.5 = Baik (average skor mendekati 2)
 * - <= 3.5 = Cukup (average skor mendekati 3)
 * - <= 4.5 = Kurang (average skor mendekati 4)
 * - >  4.5 = Sangat Kurang (average skor mendekati 5)
 * 
 * @param float $rata Average score
 * @return string Classification
 */
function klasifikasi_6c($rata) {
    $rata = (float)$rata;
    
    if ($rata <= 1.5) {
        return "Sangat Baik";
    } elseif ($rata <= 2.5) {
        return "Baik";
    } elseif ($rata <= 3.5) {
        return "Cukup";
    } elseif ($rata <= 4.5) {
        return "Kurang";
    } else {
        return "Sangat Kurang";
    }
}

/**
 * Calculate repayment capacity
 * 
 * FORMULA: Repayment = Gaji - (Pengeluaran + Angsuran Lain)
 * 
 * This represents the remaining income after all fixed expenses
 * and other obligations are deducted.
 * 
 * @param float $gaji Monthly income
 * @param float $pengeluaran Monthly expenses
 * @param float $angsuran Other monthly installments (optional, default 0)
 * @return float|null Repayment capacity, or null if invalid
 */
function hitung_repayment($gaji, $pengeluaran, $angsuran = 0) {
    $gaji = (float)($gaji ?? 0);
    $pengeluaran = (float)($pengeluaran ?? 0);
    $angsuran = (float)($angsuran ?? 0);

    // Safety check: repayment shouldn't be negative (would indicate overspending)
    $repayment = $gaji - ($pengeluaran + $angsuran);
    
    return $repayment;
}

/**
 * Classify repayment capacity quality
 * 
 * RULES:
 * - >= 95% of income = Sangat Layak (excellent capacity)
 * - >= 95% of income = Layak (good capacity)
 * - >= 50% of income = Cukup (moderate capacity)
 * - <  50% of income = Tidak Layak (poor capacity)
 * 
 * @param float $nilai Repayment value (in percentage or amount)
 * @param float|null $gaji Optional: income for percentage-based calculation
 * @return string Classification
 */
function klasifikasi_repayment($nilai, $gaji = null) {
    $nilai = (float)$nilai;
    
    // If income is provided, calculate percentage
    if ($gaji !== null) {
        $gaji = (float)$gaji;
        if ($gaji <= 0) {
            return "Tidak Layak";
        }
        $persen = ($nilai / $gaji) * 100;
    } else {
        // Treat nilai as percentage already
        $persen = $nilai;
    }

    if ($persen >= 95) {
        return "Sangat Layak";
    } elseif ($persen >= 75) {
        return "Layak";
    } elseif ($persen >= 50) {
        return "Cukup";
    } else {
        return "Tidak Layak";
    }
}

/**
 * Check if No SK (application number) is unique
 * Prevents duplicate No SK entries in the database
 * 
 * USAGE for PPPK:
 * $unique = is_unique_no_sk($pdo, $no_sk);
 * if (!$unique) {
 *     throw new Exception("No SK sudah digunakan");
 * }
 * 
 * @param PDO $pdo Database connection
 * @param string $no_sk The No SK to check
 * @param int $excludeId Optional: exclude specific id_pengajuan (for updates)
 * @return bool True if unique, false if duplicate
 */
function is_unique_no_sk(PDO $pdo, $no_sk, $excludeId = 0) {
    try {
        $no_sk = trim((string)$no_sk);
        
        if (empty($no_sk)) {
            return false;
        }

        // Check in pengajuan_kredit table
        $sql = "SELECT COUNT(*) FROM pengajuan_kredit WHERE UPPER(bidang_usaha) = UPPER(?)";
        $params = [$no_sk];
        
        if ($excludeId > 0) {
            $sql .= " AND id_pengajuan <> ?";
            $params[] = (int)$excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $count = (int)$stmt->fetchColumn();
        return $count == 0;
    } catch (Exception $e) {
        // If query fails, assume not unique (fail-safe)
        return false;
    }
}

/**
 * Log activity to audit_log table
 * Banking-standard audit trail for all operations
 * 
 * USAGE:
 * log_activity($pdo, $user_id, "Menyimpan analisa 6C untuk pengajuan #$id");
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID performing the action
 * @param string $aktivitas Description of the activity
 * @return bool True if logged successfully
 */
function log_activity(PDO $pdo, $user_id, $aktivitas) {
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (id_user, aktivitas, waktu) VALUES (?, ?, NOW())");
        $stmt->execute([(int)$user_id, (string)$aktivitas]);
        return true;
    } catch (Exception $e) {
        // Log to error file instead if database fails
        error_log("Audit log failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate and format currency input
 * Removes currency symbols and ensures numeric format
 * 
 * @param string|float $value The value to validate
 * @param string $fieldName Field name for error messages
 * @return float|null Validated value, or null if invalid
 */
function validate_currency($value, $fieldName = 'field') {
    if ($value === null || $value === '') {
        return 0;
    }

    // Remove common currency symbols and spaces
    $value = preg_replace('/[Rp\s.,-]/', '', (string)$value);
    
    if (!is_numeric($value)) {
        throw new Exception("$fieldName harus berupa angka");
    }

    $numeric = (float)$value;
    if ($numeric < 0) {
        throw new Exception("$fieldName tidak boleh negatif");
    }

    return $numeric;
}

/**
 * Safely escape HTML output (XSS protection)
 * 
 * @param string $text Text to escape
 * @param int $flags HTML entity flags
 * @return string Escaped text safe for HTML display
 */
function safe_output($text, $flags = ENT_QUOTES) {
    return htmlspecialchars((string)$text, $flags, 'UTF-8');
}

/**
 * Validate approval workflow status
 * Ensures status is within valid workflow states
 * 
 * @param string $status The status to validate
 * @return bool True if valid status
 */
function is_valid_approval_status($status) {
    $validStatuses = [
        'draft',
        'diajukan',
        'kasubag',
        'kabag',
        'kadiv',
        'direksi',
        'revisi',
        'revisi_diajukan',
        'ditolak',
        'disetujui',
        'proses',
        'diajukan_ulang',
        'selesai'
    ];

    return in_array((string)$status, $validStatuses, true);
}

/**
 * Get workflow status description
 * 
 * @param string $status The status code
 * @return string Status description in Indonesian
 */
function get_approval_status_label($status) {
    $labels = [
        'draft' => 'Draft (Belum Diajukan)',
        'diajukan' => 'Diajukan ke Atasan',
        'kasubag' => 'Menunggu Kasubag',
        'kabag' => 'Menunggu Kabag',
        'kadiv' => 'Menunggu Kadiv',
        'direksi' => 'Menunggu Direksi',
        'revisi' => 'Revisi dari Atasan',
        'revisi_diajukan' => 'Revisi Diajukan Kembali',
        'ditolak' => 'Ditolak',
        'disetujui' => 'Disetujui',
        'proses' => 'Dalam Proses',
        'diajukan_ulang' => 'Diajukan Ulang',
        'selesai' => 'Selesai'
    ];

    return $labels[(string)$status] ?? (string)$status;
}

/**
 * Calculate months remaining in contract
 * Useful for PPPK contract tenure validation
 * 
 * @param string $startDate Start date (YYYY-MM-DD format)
 * @param string $endDate End date (YYYY-MM-DD format)
 * @return int Number of months remaining (0 if expired)
 */
function hitung_bulan_sisa($startDate, $endDate) {
    try {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $today = new DateTime('today');

        // If already expired, return 0
        if ($today > $end) {
            return 0;
        }

        // Calculate months between today and end date
        $interval = $today->diff($end);
        $months = ($interval->y * 12) + $interval->m;
        
        // Add 1 to include current month
        return max(0, $months + 1);
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Validate date format and logic
 * Ensures dates are in valid format and logically sound
 * 
 * @param string $date Date to validate (YYYY-MM-DD format)
 * @param string $fieldName Field name for error messages
 * @return string|false The validated date, or false if invalid
 */
function validate_date_format($date, $fieldName = 'Tanggal') {
    $date = trim((string)$date);
    
    if (empty($date)) {
        return false;
    }

    // Check format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception("$fieldName harus format YYYY-MM-DD");
    }

    // Validate with DateTime
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        throw new Exception("$fieldName tidak valid");
    }

    return $date;
}
