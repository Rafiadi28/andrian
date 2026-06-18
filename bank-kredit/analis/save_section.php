<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/credit_helper.php';
require_once __DIR__ . '/../helpers/repayment_override.php';
require_once __DIR__ . '/../helpers/repayment_snapshot.php';
requireSameRole('analis');

// Type assertion for static analysis — $pdo is guaranteed initialized by functions.php
/** @var PDO $pdo */
// @phpstan-ignore-next-line
if (!isset($pdo) || !($pdo instanceof PDO)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

header('Content-Type: application/json');

// Function stubs for static analysis (actual implementations in repayment_snapshot.php)
if (!function_exists('captureRepaymentParameterSnapshot')) {
    /**
     * Stub for static analysis - actual implementation in helpers/repayment_snapshot.php
     * @param PDO $pdo
     * @param int $id_pengajuan
     * @param string $jenis_kredit
     * @param float $nilai_dasar
     * @param array|null $overrideData
     * @return array
     */
    function captureRepaymentParameterSnapshot(
        PDO $pdo,
        int $id_pengajuan,
        string $jenis_kredit,
        float $nilai_dasar,
        ?array $overrideData = null
    ): array {
        return [];
    }
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.']);
    exit;
}

// ============================================================
// INPUT VALIDATION FUNCTIONS
// ============================================================

/**
 * Validate and sanitize text input: trim, limit length, prevent injection
 */
function validateText(mixed $value, int $maxLength = 100, string $fieldName = 'field', bool $allowUpper = true): string
{
    $value = trim((string)$value);
    
    if (strlen($value) > $maxLength) {
        throw new Exception("$fieldName melebihi batas $maxLength karakter (panjang: " . strlen($value) . ")");
    }
    
    // Basic HTML/injection prevention
    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    
    if ($allowUpper) {
        $value = strtoupper($value);
    }
    
    return $value;
}

/**
 * Validate and sanitize numeric strings
 */
function validateNumber(mixed $value, string $fieldName = 'field'): string
{
    $value = trim((string)$value);
    
    if (!preg_match('/^[0-9]{1,}$/', $value)) {
        throw new Exception("$fieldName harus angka saja");
    }
    
    return $value;
}

/**
 * Validate decimal numbers
 */
function validateDecimal(mixed $value, string $fieldName = 'field'): float
{
    $value = floatval($value);
    
    if ($value < 0) {
        throw new Exception("$fieldName tidak boleh negatif");
    }
    
    return $value;
}

/**
 * Validate date format
 */
function validateDate(mixed $value, string $fieldName = 'field'): ?string
{
    $value = trim((string)$value);
    
    if (empty($value)) {
        return null;
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        throw new Exception("$fieldName format harus YYYY-MM-DD");
    }
    
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new Exception("$fieldName tanggal tidak valid");
    }
    
    return $value;
}

function ensureUniqueNik(PDO $pdo, string $nik, int $excludeId = 0): void
{
    $sql = "SELECT id_pengajuan FROM pengajuan_kredit WHERE nik = ?";
    $params = [$nik];
    if ($excludeId > 0) {
        $sql .= " AND id_pengajuan <> ?";
        $params[] = $excludeId;
    }
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn()) {
        throw new Exception('NIK sudah terdaftar pada pengajuan lain.');
    }
}

$section = $_POST['section'] ?? '';
$id_pengajuan = intval($_POST['id_pengajuan'] ?? 0);

/**
 * Helper: Get SQL condition for editable states (analis can edit these)
 * Includes: draft, revisi (from kabag), ditolak (rejected), diajukan_ulang (resubmit)
 */
function getAnalisEditableCondition() {
    return "status_pengajuan IN ('draft','revisi','ditolak','diajukan_ulang','revisi_diajukan')";
}
const ANALIS_DRAFT_LIKE = "status_pengajuan IN ('draft','revisi','ditolak','diajukan_ulang','revisi_diajukan')";

$jenis_pekerjaan_post = trim((string) ($_POST['jenis_pekerjaan'] ?? 'umum'));
$allowed_jenis_pekerjaan = ['umum', 'pppk', 'perangkat_desa', 'kpr', 'kretamas', 'cashcolateral'];
if (!in_array($jenis_pekerjaan_post, $allowed_jenis_pekerjaan, true)) {
    $jenis_pekerjaan_post = 'umum';
}

$pegawai_jenis_list = ['pppk', 'perangkat_desa'];
if (in_array($jenis_pekerjaan_post, $pegawai_jenis_list, true)) {
    foreach ([
        'nama_usaha',
        'bidang_usaha',
        'lama_usaha',
        'omset_per_bulan',
        'biaya_bahan_baku',
        'biaya_gaji',
        'biaya_listrik',
        'biaya_air',
        'biaya_sewa',
        'biaya_transportasi',
        'biaya_lainnya',
        'biaya_hidup',
        'cicilan_lain',
        'usaha',
    ] as $_spam_usaha_key) {
        unset($_POST[$_spam_usaha_key]);
    }
}

try {
    switch ($section) {

        // ============================================================
        // SECTION 1: DATA PEMOHON — creates new record (draft)
        // ============================================================
        case 'pemohon':
            // Validation & Normalization
            $nik = trim($_POST['nik'] ?? '');
            if (!preg_match('/^[0-9]{16}$/', $nik)) {
                echo json_encode(['success' => false, 'message' => 'NIK harus 16 digit angka.']);
                exit;
            }
            $hp = trim($_POST['no_hp'] ?? '');
            if (!preg_match('/^[0-9]{10,15}$/', $hp)) {
                echo json_encode(['success' => false, 'message' => 'No HP harus 10-15 digit angka.']);
                exit;
            }
            $nama = strtoupper(preg_replace('/\s+/', ' ', trim($_POST['nama_debitur'] ?? '')));
            if (empty($nama)) {
                echo json_encode(['success' => false, 'message' => 'Nama Debitur wajib diisi.']);
                exit;
            }
            $id_nasabah = strtoupper(trim($_POST['id_nasabah'] ?? ''));
            $npwp = strtoupper(trim($_POST['npwp'] ?? ''));
            $nib = strtoupper(trim($_POST['nib'] ?? ''));
            $alamat_ktp = strtoupper(preg_replace('/\s+/', ' ', trim($_POST['alamat_ktp'] ?? '')));
            if (empty($alamat_ktp)) {
                echo json_encode(['success' => false, 'message' => 'Alamat sesuai KTP wajib diisi.']);
                exit;
            }

            $tempat_lahir = strtoupper(trim($_POST['tempat_lahir'] ?? ''));
            try {
                $tanggal_lahir = validateDate($_POST['tanggal_lahir'] ?? '', 'Tanggal lahir');
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            $pekerjaan = strtoupper(trim($_POST['pekerjaan'] ?? ''));
            $alamat_pekerjaan = strtoupper(trim($_POST['alamat_pekerjaan'] ?? ''));

            $status_kawin = $_POST['status_perkawinan'] ?? 'lajang';
            $pasangan = ($status_kawin == 'menikah') ? strtoupper(preg_replace('/\s+/', ' ', trim($_POST['nama_pasangan'] ?? ''))) : '-';
            $tempat_lahir_pasangan = ($status_kawin == 'menikah') ? strtoupper(trim($_POST['tempat_lahir_pasangan'] ?? '')) : null;
            $tanggal_lahir_pasangan = null;
            if ($status_kawin == 'menikah' && !empty($_POST['tanggal_lahir_pasangan'])) {
                try {
                    $tanggal_lahir_pasangan = validateDate($_POST['tanggal_lahir_pasangan'], 'Tanggal lahir pasangan');
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            }
            $pekerjaan_pasangan = ($status_kawin == 'menikah') ? strtoupper(trim($_POST['pekerjaan_pasangan'] ?? '')) : null;
            $alamat_pekerjaan_pasangan = ($status_kawin == 'menikah') ? strtoupper(trim($_POST['alamat_pekerjaan_pasangan'] ?? '')) : null;

            $dukuh = strtoupper(trim($_POST['dukuh'] ?? ''));
            $desa = strtoupper(trim($_POST['desa'] ?? ''));
            $kecamatan = strtoupper(trim($_POST['kecamatan'] ?? ''));
            $kota_kabupaten = strtoupper(trim($_POST['kota_kabupaten'] ?? ''));
            $alamat_dom = strtoupper(preg_replace('/\s+/', ' ', trim($_POST['alamat_domisili'] ?? '')));
            if (empty($alamat_dom))
                $alamat_dom = '-';
            $jumlah_tanggungan = intval($_POST['jumlah_tanggungan'] ?? 0);
            $nama_ibu_kandung = strtoupper(trim($_POST['nama_ibu_kandung'] ?? ''));

            $nama_instansi = strtoupper(trim($_POST['nama_instansi'] ?? ''));
            $alamat_instansi = strtoupper(trim($_POST['alamat_instansi'] ?? ''));
            $telepon_kantor = trim($_POST['telepon_kantor'] ?? '');
            $departemen_bagian = strtoupper(trim($_POST['departemen_bagian'] ?? ''));
            $jabatan = strtoupper(trim($_POST['jabatan'] ?? ''));

            $pinjaman_ke = intval($_POST['pinjaman_ke'] ?? 1);
            if ($pinjaman_ke < 1) {
                $pinjaman_ke = 1;
            }

            // Handle file upload
            $file_pendukung = '';
            if (isset($_FILES['file_pendukung']) && $_FILES['file_pendukung']['error'] == UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../assets/uploads/';
                $ext = strtolower(pathinfo($_FILES['file_pendukung']['name'], PATHINFO_EXTENSION));
                $allowedPendukung = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
                if (!in_array($ext, $allowedPendukung, true)) {
                    echo json_encode(['success' => false, 'message' => 'Format file pendukung harus JPG, PNG, WEBP, atau PDF.']);
                    exit;
                }
                $mimeErr = bankKreditVerifyUploadMime($_FILES['file_pendukung']['tmp_name'], $_FILES['file_pendukung']['name']);
                if ($mimeErr !== null) {
                    echo json_encode(['success' => false, 'message' => $mimeErr]);
                    exit;
                }
                $newFileName = uniqid('file_pendukung_') . '.' . $ext;
                if (!move_uploaded_file($_FILES['file_pendukung']['tmp_name'], $uploadDir . $newFileName)) {
                    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file pendukung.']);
                    exit;
                }
                $file_pendukung = $newFileName;
            }

            try {
                $pdo->beginTransaction();

                if ($id_pengajuan > 0) {
                    // Cek apakah sudah terikat akad / status disetujui, jika ya skip pinjaman_ke
                    $stmt_check = $pdo->prepare("SELECT status_pengajuan FROM pengajuan_kredit WHERE id_pengajuan = ?");
                    $stmt_check->execute([$id_pengajuan]);
                    $curr_status = $stmt_check->fetchColumn();

                    $sql = "UPDATE pengajuan_kredit SET 
                            nama_debitur=?, id_nasabah=?, nik=?, npwp=?, tempat_lahir=?, tanggal_lahir=?, pekerjaan=?, alamat_pekerjaan=?,
                            status_perkawinan=?, nama_pasangan=?, tempat_lahir_pasangan=?, tanggal_lahir_pasangan=?, pekerjaan_pasangan=?, alamat_pekerjaan_pasangan=?,
                            alamat_ktp=?, dukuh=?, desa=?, kecamatan=?, kota_kabupaten=?, alamat_domisili=?, no_hp=?, jumlah_tanggungan=?, nama_ibu_kandung=?,
                            nib=?, nama_instansi=?, alamat_instansi=?, telepon_kantor=?, departemen_bagian=?, jabatan=?";

                    $params = [
                        $nama,
                        $id_nasabah,
                        $nik,
                        $npwp,
                        $tempat_lahir,
                        $tanggal_lahir,
                        $pekerjaan,
                        $alamat_pekerjaan,
                        $status_kawin,
                        $pasangan,
                        $tempat_lahir_pasangan,
                        $tanggal_lahir_pasangan,
                        $pekerjaan_pasangan,
                        $alamat_pekerjaan_pasangan,
                        $alamat_ktp,
                        $dukuh,
                        $desa,
                        $kecamatan,
                        $kota_kabupaten,
                        $alamat_dom,
                        $hp,
                        $jumlah_tanggungan,
                        $nama_ibu_kandung,
                        $nib,
                        $nama_instansi,
                        $alamat_instansi,
                        $telepon_kantor,
                        $departemen_bagian,
                        $jabatan
                    ];

                    if (in_array($curr_status, ['draft', 'revisi', 'ditolak'], true)) {
                        $sql .= ", pinjaman_ke=?";
                        $params[] = $pinjaman_ke;
                    }

                    $sql .= ", jenis_pekerjaan=?";
                    $params[] = $jenis_pekerjaan_post;

                    if ($file_pendukung) {
                        $sql .= ", file_pendukung=?";
                        $params[] = $file_pendukung;
                    }

                    $sql .= " WHERE id_pengajuan=?";
                    $params[] = $id_pengajuan;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    $pdo->prepare("UPDATE pengajuan_kredit SET tanggal_analisa = COALESCE(tanggal_analisa, ?) WHERE id_pengajuan = ?")
                        ->execute([date('Y-m-d'), $id_pengajuan]);

                    // 📋 Audit log
                    log_activity($pdo, $_SESSION['user_id'] ?? 0, "Memperbarui Data Pemohon (ID Pengajuan: $id_pengajuan)");

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Data pemohon berhasil diperbarui', 'id_pengajuan' => $id_pengajuan]);
                }
                else {
                    // INSERT new draft
                    $sql = "INSERT INTO pengajuan_kredit 
                        (nama_debitur, id_nasabah, nik, npwp, tempat_lahir, tanggal_lahir, pekerjaan, alamat_pekerjaan,
                         status_perkawinan, nama_pasangan, tempat_lahir_pasangan, tanggal_lahir_pasangan, pekerjaan_pasangan, alamat_pekerjaan_pasangan,
                         alamat_ktp, dukuh, desa, kecamatan, kota_kabupaten, alamat_domisili, no_hp, jumlah_tanggungan, nama_ibu_kandung,
                         nib, nama_instansi, alamat_instansi, telepon_kantor, departemen_bagian, jabatan, pinjaman_ke, jenis_pekerjaan,
                         tanggal_analisa,
                         file_pendukung, nama_usaha, bidang_usaha, lama_usaha, omset_per_bulan, biaya_operasional, laba_bersih, repayment_capacity,
                         jumlah_kredit, jangka_waktu, tujuan_kredit, jenis_kredit, jenis_jaminan, status_pengajuan, input_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, ?, ?, ?, ?,
                                ?,
                                ?, '', '', '', 0, 0, 0, 0,
                                0, 0, '', 'KMK', 'tanah_bangunan', 'draft', ?)";
                    $params = [
                        $nama,
                        $id_nasabah,
                        $nik,
                        $npwp,
                        $tempat_lahir,
                        $tanggal_lahir,
                        $pekerjaan,
                        $alamat_pekerjaan,
                        $status_kawin,
                        $pasangan,
                        $tempat_lahir_pasangan,
                        $tanggal_lahir_pasangan,
                        $pekerjaan_pasangan,
                        $alamat_pekerjaan_pasangan,
                        $alamat_ktp,
                        $dukuh,
                        $desa,
                        $kecamatan,
                        $kota_kabupaten,
                        $alamat_dom,
                        $hp,
                        $jumlah_tanggungan,
                        $nama_ibu_kandung,
                        $nib,
                        $nama_instansi,
                        $alamat_instansi,
                        $telepon_kantor,
                        $departemen_bagian,
                        $jabatan,
                        $pinjaman_ke,
                        $jenis_pekerjaan_post,
                        date('Y-m-d'),
                        $file_pendukung,
                        $_SESSION['user_id']
                    ];
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $id_pengajuan = $pdo->lastInsertId();

                    // 📋 Audit log
                    log_activity($pdo, $_SESSION['user_id'] ?? 0, "Membuat Data Pemohon Baru (ID Pengajuan: $id_pengajuan)");

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Data pemohon berhasil disimpan', 'id_pengajuan' => $id_pengajuan]);
                }
            }
            catch (Exception $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // Log the error for debugging
                logError('save_section pemohon error', [
                    'message' => $ex->getMessage(),
                    'code' => $ex->getCode(),
                    'line' => $ex->getLine(),
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'post_keys' => array_keys($_POST)
                ]);
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . $ex->getMessage()]);
            }
            break;

        // ============================================================
        // SECTION: PENGHASILAN PEGAWAI (PPPK / PERANGKAT DESA)
        // Memetakan ke kolom pengajuan_kredit (tanpa tab usaha di form).
        // ============================================================
        case 'penghasilan_pegawai':
            if ($id_pengajuan <= 0) {
                echo json_encode(['success' => false, 'message' => 'Simpan Data Pemohon terlebih dahulu!']);
                exit;
            }
            if (!in_array($jenis_pekerjaan_post, $pegawai_jenis_list, true)) {
                echo json_encode(['success' => false, 'message' => 'Tab penghasilan ini hanya untuk PPPK atau Perangkat Desa.']);
                exit;
            }

            $angsuran_diajukan = floatval($_POST['angsuran_diajukan'] ?? 0);
            if ($angsuran_diajukan < 0) {
                $angsuran_diajukan = 0;
            }
            $status_kelayakan = '';
            $rpc = 0.0;
            $net_cashflow = 0.0;

            if ($jenis_pekerjaan_post === 'pppk') {
                $sk = strtoupper(preg_replace('/\s+/', ' ', trim($_POST['pppk_no_sk'] ?? '')));
                if ($sk === '') {
                    echo json_encode(['success' => false, 'message' => 'Nomor SK PPPK wajib diisi.']);
                    exit;
                }

                // ⚠️ VALIDASI: Cek No SK tidak duplikasi
                if (!is_unique_no_sk($pdo, $sk, $id_pengajuan)) {
                    echo json_encode(['success' => false, 'message' => '❌ No SK sudah digunakan pada pengajuan lain. Silakan gunakan No SK yang berbeda.']);
                    exit;
                }

                // --- Tanggal perjanjian (form baru) ---
                $tgl_awal  = trim($_POST['pppk_tgl_awal'] ?? '');
                $tgl_akhir = trim($_POST['pppk_tgl_akhir'] ?? '');

                // Fallback ke field lama jika form baru kosong (backward compat)
                if ($tgl_awal === '') {
                    $tgl_awal = strtoupper(trim($_POST['pppk_masa_kontrak'] ?? '-'));
                }
                if ($tgl_akhir === '') {
                    $tgl_akhir = strtoupper(trim($_POST['pppk_sisa_kontrak'] ?? '-'));
                }

                // Hitung sisa masa kerja server-side jika format tanggal valid
                $sisa_bulan_server = intval($_POST['pppk_sisa_kerja_bulan'] ?? 0);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_akhir)) {
                    $today = new DateTime('today');
                    $akhir = DateTime::createFromFormat('Y-m-d', $tgl_akhir);
                    if ($akhir && $akhir > $today) {
                        $diff = $today->diff($akhir);
                        $sisa_bulan_server = ($diff->y * 12) + $diff->m;
                    }
                }

                $gaji_pp    = floatval($_POST['pppk_gaji'] ?? 0);
                $biaya_hidup = floatval($_POST['pppk_biaya_hidup'] ?? 0);

                // --- Angsuran Bank Wonosobo (array dinamis, form baru) ---
                $angsuran_nominal_arr = $_POST['pppk_angsuran_nominal'] ?? [];
                if (is_array($angsuran_nominal_arr) && count($angsuran_nominal_arr) > 0) {
                    $cic = 0;
                    foreach ($angsuran_nominal_arr as $v) {
                        $cic += floatval($v);
                    }
                } else {
                    // Fallback: pppk_total_angsuran dari hidden field, atau field lama
                    $cic = floatval($_POST['pppk_total_angsuran'] ?? $_POST['pppk_angsuran_lain'] ?? 0);
                }

                // Repayment Capacity dari master parameter (dasar + persen per jenis kredit)
                $biaya_operasional = 0.0;
                $laba = $gaji_pp;
                $total_pengeluaran = $biaya_hidup + $cic;
                $net_cashflow = $laba - $total_pengeluaran;
                
                // === START LOGIKA REVISI FINANSIAL ===
                $is_revisi_unlock = false;
                $rpc_lama = 0.0;
                if ($id_pengajuan > 0) {
                    $stmtO = $pdo->prepare("SELECT status_pengajuan, laba_bersih, biaya_hidup, cicilan_lain, repayment_capacity_dihitung, repayment_capacity FROM pengajuan_kredit WHERE id_pengajuan = ?");
                    $stmtO->execute([$id_pengajuan]);
                    $old = $stmtO->fetch(PDO::FETCH_ASSOC);
                    
                    if ($old && $old['status_pengajuan'] === 'revisi') {
                        if ((float)$old['laba_bersih'] !== (float)$laba || (float)$old['biaya_hidup'] !== (float)$biaya_hidup || (float)$old['cicilan_lain'] !== (float)$cic) {
                            $rpc_lama = (float)($old['repayment_capacity_dihitung'] ?? $old['repayment_capacity'] ?? 0);
                            $pdo->prepare("UPDATE pengajuan_kredit SET id_parameter_repayment = NULL, tanggal_analisa = ? WHERE id_pengajuan = ?")
                                ->execute([date('Y-m-d'), $id_pengajuan]);
                            $is_revisi_unlock = true;
                        }
                    }
                }
                // === END LOGIKA REVISI FINANSIAL ===
                
                $repaymentResult = persistRepaymentCalculationForPengajuan($pdo, $id_pengajuan, 'pppk', [
                    'net_cashflow' => $net_cashflow,
                    'gaji_bersih' => $gaji_pp,
                    'pendapatan' => $gaji_pp,
                    'laba_bersih' => $laba,
                ]);
                $rpc = $repaymentResult['rpc'];
                $rpc_dihitung = $repaymentResult['rpc_dihitung'];
                $id_param_repayment = $repaymentResult['id_parameter'];
                $snapshot_json = $repaymentResult['snapshot_json'];
                
                // STEP 9: Capture repayment parameter snapshot
                $snapshotResult = captureRepaymentParameterSnapshot($pdo, $id_pengajuan, 'pppk', $gaji_pp, [
                    'override_aktif' => (int)($overrideData['override_aktif'] ?? 0),
                    'override_by' => (int)($overrideData['override_by'] ?? 0),
                    'override_alasan' => $overrideData['override_alasan'] ?? null,
                ]);
                if ($snapshotResult['success']) {
                    // Snapshot captured successfully
                    logError('repayment_snapshot_pppk', ['id_snapshot' => $snapshotResult['id_snapshot'], 'rpc' => $rpc]);
                }
                
                // Klasifikasi repayment quality
                $klasifikasi_rpc = klasifikasi_repayment($rpc, $gaji_pp);
                
                // Tentukan status kelayakan
                if ($angsuran_diajukan > 0) {
                    $status_kelayakan = ($rpc >= $angsuran_diajukan) ? 'LAYAK' : 'TIDAK LAYAK';
                } else {
                    $status_kelayakan = '';
                }

                // --- Nomor SK Agunan (opsional) ---
                $agunan_no_sk = strtoupper(trim($_POST['pppk_agunan_no_sk'] ?? ''));

                // --- Upload File SK (opsional) ---
                $file_sk_pppk_saved = null; // null = tidak ada file baru, keep existing
                if (isset($_FILES['pppk_file_sk']) && $_FILES['pppk_file_sk']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../assets/uploads/';
                    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

                    $skFile = $_FILES['pppk_file_sk'];
                    if ($skFile['size'] > 2 * 1024 * 1024) {
                        echo json_encode(['success' => false, 'message' => 'File SK terlalu besar. Maksimal 2MB.']);
                        exit;
                    }
                    $ext = strtolower(pathinfo($skFile['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                        echo json_encode(['success' => false, 'message' => 'Format file SK tidak didukung. Gunakan PDF, JPG, atau PNG.']);
                        exit;
                    }
                    $newName = 'sk_pppk_' . $id_pengajuan . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($skFile['tmp_name'], $uploadDir . $newName)) {
                        $file_sk_pppk_saved = $newName;
                    }
                }

                // Build UPDATE: jika ada file baru, update file_sk_pppk juga
                $fileSkSql = $file_sk_pppk_saved !== null ? ', file_sk_pppk=?' : '';
                $stmt = $pdo->prepare("UPDATE pengajuan_kredit SET 
                    nama_usaha=?, bidang_usaha=?, lama_usaha=?, departemen_bagian=?,
                    omset_per_bulan=?, biaya_bahan_baku=0, biaya_gaji=0, biaya_listrik=0, biaya_air=0, biaya_sewa=0, biaya_transportasi=0, biaya_lainnya=0,
                    biaya_operasional=?, laba_bersih=?, penyusutan=0, cashflow_usaha=?,
                    biaya_hidup=?, cicilan_lain=?, total_pengeluaran_tetap=?,
                    net_cashflow=?, repayment_capacity=?, repayment_capacity_dihitung=?, id_parameter_repayment=?, repayment_parameter_snapshot=?, angsuran_diajukan=?, status_kelayakan=?,
                    pppk_agunan_no_sk=?" . $fileSkSql . "
                    WHERE id_pengajuan=? AND " . getAnalisEditableCondition());

                $execParams = [
                    'PPPK', $sk, $tgl_awal, $tgl_akhir,
                    $gaji_pp,
                    $biaya_operasional, $laba, $laba,
                    $biaya_hidup, $cic, $total_pengeluaran,
                    $net_cashflow, $rpc, $rpc_dihitung, $id_param_repayment, $snapshot_json, $angsuran_diajukan, $status_kelayakan,
                    $agunan_no_sk,
                ];
                if ($file_sk_pppk_saved !== null) {
                    $execParams[] = $file_sk_pppk_saved;
                }
                $execParams[] = $id_pengajuan;
                $stmt->execute($execParams);
            }
            else { // perangkat_desa
                $jabatan = strtoupper(preg_replace('/\s+/', ' ', trim($_POST['desk_jabatan'] ?? '')));
                $sk_d = strtoupper(preg_replace('/\s+/', ' ', trim($_POST['desk_no_sk'] ?? '')));
                if ($jabatan === '' || $sk_d === '') {
                    echo json_encode(['success' => false, 'message' => 'Jabatan dan Nomor SK wajib diisi.']);
                    exit;
                }
                
                // Validasi berdasarkan jabatan
                $tgl_mulai = trim($_POST['desk_tgl_mulai'] ?? '');
                $tgl_akhir = '';
                
                if ($jabatan === 'KEPALA DESA') {
                    // Kepala Desa: wajib isi tgl_mulai dan tgl_akhir
                    $tgl_akhir = trim($_POST['desk_tgl_akhir'] ?? '');
                    if ($tgl_mulai === '' || $tgl_akhir === '') {
                        echo json_encode(['success' => false, 'message' => 'Tanggal mulai dan tanggal akhir wajib diisi untuk Kepala Desa.']);
                        exit;
                    }
                } else if (in_array($jabatan, ['SEKRETARIS DESA', 'KEPALA DUSUN', 'KAUR'], true)) {
                    // Non-Kepala Desa: wajib isi tgl_mulai dan tgl_lahir
                    $tgl_lahir = trim($_POST['desk_tgl_lahir'] ?? '');
                    if ($tgl_mulai === '' || $tgl_lahir === '') {
                        echo json_encode(['success' => false, 'message' => 'Tanggal mulai dan tanggal lahir wajib diisi untuk ' . htmlspecialchars($jabatan) . '.']);
                        exit;
                    }
                    $tgl_akhir = $tgl_lahir;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Jabatan tidak dikenali. Pilih: Kepala Desa, Sekretaris Desa, Kepala Dusun, atau Kaur.']);
                    exit;
                }
                
                $tetap = floatval($_POST['desk_penghasilan_tetap'] ?? 0);
                $tambahan = floatval($_POST['desk_tambahan_penghasilan'] ?? 0);
                $omset_total = $tetap + $tambahan;
                
                $biaya_hidup = floatval($_POST['desk_biaya_hidup'] ?? 0);
                
                // --- Angsuran Bank Wonosobo (array dinamis) ---
                $angsuran_nominal_arr = $_POST['desk_angsuran_nominal'] ?? [];
                if (is_array($angsuran_nominal_arr) && count($angsuran_nominal_arr) > 0) {
                    $cic = 0;
                    foreach ($angsuran_nominal_arr as $v) {
                        $cic += floatval($v);
                    }
                } else {
                    $cic = floatval($_POST['desk_total_angsuran'] ?? $_POST['desk_angsuran_lain'] ?? 0);
                }
                
                // ⚠️ BANKING STANDARD: Repayment Capacity untuk Perangkat Desa
                $laba = $omset_total;
                $total_pengeluaran = $biaya_hidup + $cic;
                $net_cashflow = $laba - $total_pengeluaran;
                
                // === START LOGIKA REVISI FINANSIAL ===
                $is_revisi_unlock = false;
                $rpc_lama = 0.0;
                if ($id_pengajuan > 0) {
                    $stmtO = $pdo->prepare("SELECT status_pengajuan, laba_bersih, biaya_hidup, cicilan_lain, repayment_capacity_dihitung, repayment_capacity FROM pengajuan_kredit WHERE id_pengajuan = ?");
                    $stmtO->execute([$id_pengajuan]);
                    $old = $stmtO->fetch(PDO::FETCH_ASSOC);
                    
                    if ($old && $old['status_pengajuan'] === 'revisi') {
                        if ((float)$old['laba_bersih'] !== (float)$laba || (float)$old['biaya_hidup'] !== (float)$biaya_hidup || (float)$old['cicilan_lain'] !== (float)$cic) {
                            $rpc_lama = (float)($old['repayment_capacity_dihitung'] ?? $old['repayment_capacity'] ?? 0);
                            $pdo->prepare("UPDATE pengajuan_kredit SET id_parameter_repayment = NULL, tanggal_analisa = ? WHERE id_pengajuan = ?")
                                ->execute([date('Y-m-d'), $id_pengajuan]);
                            $is_revisi_unlock = true;
                        }
                    }
                }
                // === END LOGIKA REVISI FINANSIAL ===
                
                $repaymentResult = persistRepaymentCalculationForPengajuan($pdo, $id_pengajuan, 'perangkat_desa', [
                    'net_cashflow' => $net_cashflow,
                    'gaji_bersih' => $tetap + $tambahan,
                    'pendapatan' => $tetap + $tambahan,
                    'laba_bersih' => $laba,
                ]);
                $rpc = $repaymentResult['rpc'];
                $rpc_dihitung = $repaymentResult['rpc_dihitung'];
                $id_param_repayment = $repaymentResult['id_parameter'];
                $snapshot_json = $repaymentResult['snapshot_json'];
                
                // STEP 9: Capture repayment parameter snapshot for Perangkat Desa
                $snapshotResult = captureRepaymentParameterSnapshot($pdo, $id_pengajuan, 'perangkat_desa', $tetap + $tambahan, [
                    'override_aktif' => (int)($overrideData['override_aktif'] ?? 0),
                    'override_by' => (int)($overrideData['override_by'] ?? 0),
                    'override_alasan' => $overrideData['override_alasan'] ?? null,
                ]);
                if ($snapshotResult['success']) {
                    // Snapshot captured successfully
                    logError('repayment_snapshot_perangkat_desa', ['id_snapshot' => $snapshotResult['id_snapshot'], 'rpc' => $rpc]);
                }
                
                if ($angsuran_diajukan > 0) {
                    $status_kelayakan = ($rpc >= $angsuran_diajukan) ? 'LAYAK' : 'TIDAK LAYAK';
                } else {
                    $status_kelayakan = '';
                }

                // --- Upload File SK (opsional, hanya saat ada file baru) ---
                $file_sk_saved = null;
                if (isset($_FILES['desk_file_sk']) && $_FILES['desk_file_sk']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../assets/uploads/';
                    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

                    $skFile = $_FILES['desk_file_sk'];
                    if ($skFile['size'] > 2 * 1024 * 1024) {
                        echo json_encode(['success' => false, 'message' => 'File SK terlalu besar. Maksimal 2MB.']);
                        exit;
                    }
                    $ext = strtolower(pathinfo($skFile['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                        echo json_encode(['success' => false, 'message' => 'Format file SK tidak didukung. Gunakan PDF, JPG, atau PNG.']);
                        exit;
                    }
                    $newName = 'sk_desa_' . $id_pengajuan . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($skFile['tmp_name'], $uploadDir . $newName)) {
                        $file_sk_saved = $newName;
                    }
                }

                // Build UPDATE
                $fileSkSql = $file_sk_saved !== null ? ', file_sk_pppk=?' : '';
                $stmt = $pdo->prepare("UPDATE pengajuan_kredit SET 
                    jabatan=?,
                    nama_usaha=?, bidang_usaha=?, lama_usaha=?, departemen_bagian=?,
                    omset_per_bulan=?, biaya_bahan_baku=0, biaya_gaji=0, biaya_listrik=0, biaya_air=0, biaya_sewa=0, biaya_transportasi=0, biaya_lainnya=0,
                    biaya_operasional=0, laba_bersih=?, penyusutan=0, cashflow_usaha=?,
                    biaya_hidup=?, cicilan_lain=?, total_pengeluaran_tetap=?,
                    net_cashflow=?, repayment_capacity=?, repayment_capacity_dihitung=?, id_parameter_repayment=?, repayment_parameter_snapshot=?, angsuran_diajukan=?, status_kelayakan=?,
                    pppk_agunan_no_sk=?" . $fileSkSql . "
                    WHERE id_pengajuan=? AND " . getAnalisEditableCondition());
                
                $execParams = [
                    $jabatan,               // jabatan
                    '-',                    // nama_usaha (tidak digunakan untuk perangkat_desa)
                    $sk_d,                  // bidang_usaha (untuk menyimpan nomor SK)
                    $tgl_mulai, $tgl_akhir, // lama_usaha, departemen_bagian (tanggal mulai dan akhir/lahir)
                    $omset_total,           // omset_per_bulan
                    $laba,                  // laba_bersih
                    $laba,                  // cashflow_usaha (sama dengan laba karena biaya semua 0)
                    $biaya_hidup,           // biaya_hidup
                    $cic,                   // cicilan_lain
                    $total_pengeluaran,     // total_pengeluaran_tetap
                    $net_cashflow,          // net_cashflow
                    $rpc,                   // repayment_capacity
                    $rpc_dihitung,          // repayment_capacity_dihitung
                    $id_param_repayment,    // id_parameter_repayment
                    $snapshot_json,         // repayment_parameter_snapshot
                    $angsuran_diajukan,     // angsuran_diajukan
                    $status_kelayakan,      // status_kelayakan
                    '-'                     // pppk_agunan_no_sk (tidak digunakan untuk perangkat_desa)
                ];
                if ($file_sk_saved !== null) {
                    $execParams[] = $file_sk_saved;
                }
                $execParams[] = $id_pengajuan;
                
                $stmt->execute($execParams);
            }

            // 📋 Log activity untuk audit trail
            $jenis_pegawai = ($jenis_pekerjaan_post === 'pppk') ? 'PPPK' : 'PERANGKAT_DESA';
            
            if (isset($is_revisi_unlock) && $is_revisi_unlock) {
                log_activity($pdo, $_SESSION['user_id'] ?? 0, 
                    "REVISI FINANSIAL $jenis_pegawai: Terdeteksi perubahan gaji/cashflow. Parameter direset ke yang terbaru. Repayment lama: Rp " . number_format($rpc_lama,0) . " -> Baru: Rp " . number_format($rpc,0)
                );
                $msg = 'Data berhasil disimpan. NOTIFIKASI: Revisi data finansial mengubah hasil perhitungan Repayment Capacity menjadi Rp ' . number_format($rpc,0,',','.');
            } else {
                log_activity($pdo, $_SESSION['user_id'] ?? 0, 
                    "Menyimpan Data Penghasilan $jenis_pegawai (ID Pengajuan: $id_pengajuan | Repayment Capacity: " . 
                    number_format($rpc, 0, ',', '.') . " | Status: " . ($status_kelayakan ?: 'Pending') . ")");
                $msg = 'Data penghasilan berhasil disimpan!';
            }

            echo json_encode(['success' => true, 'message' => $msg, 'id_pengajuan' => $id_pengajuan]);
            break;

        // ============================================================
        // SECTION 2: DATA USAHA & ANALISA KEMAMPUAN BAYAR
        // ============================================================
        case 'usaha':
            if ($id_pengajuan <= 0) {
                echo json_encode(['success' => false, 'message' => 'Simpan Data Pemohon terlebih dahulu!']);
                exit;
            }
            if (in_array($jenis_pekerjaan_post, $pegawai_jenis_list, true)) {
                echo json_encode(['success' => false, 'message' => 'Jenis pekerjaan ini tidak memakai data usaha. Isi tab Penghasilan.']);
                exit;
            }

            // A. Data Usaha
            $usaha = strtoupper(trim($_POST['nama_usaha'] ?? '-'));
            $bidang = strtoupper(trim($_POST['bidang_usaha'] ?? '-'));
            $lama = strtoupper(trim($_POST['lama_usaha'] ?? '-'));

            // B. Omzet & Pendapatan Lain
            $omset = floatval($_POST['omset_per_bulan'] ?? 0);
            $pendapatan_lain = floatval($_POST['pendapatan_lain'] ?? 0);
            if ($omset < 0) $omset = 0;
            if ($pendapatan_lain < 0) $pendapatan_lain = 0;

            // C. Rincian Biaya Usaha
            $b_bahan_baku = floatval($_POST['biaya_bahan_baku'] ?? 0);
            $b_gaji = floatval($_POST['biaya_gaji'] ?? 0);
            $b_listrik = floatval($_POST['biaya_listrik'] ?? 0);
            $b_air = floatval($_POST['biaya_air'] ?? 0);
            $b_sewa = floatval($_POST['biaya_sewa'] ?? 0);
            $b_transportasi = floatval($_POST['biaya_transportasi'] ?? 0);
            $b_lainnya = floatval($_POST['biaya_lainnya'] ?? 0);
            $total_biaya = $b_bahan_baku + $b_gaji + $b_listrik + $b_air + $b_sewa + $b_transportasi + $b_lainnya;

            // D. Laba Usaha = (Omzet + Pendapatan Lain) - Biaya Operasional
            $laba = ($omset + $pendapatan_lain) - $total_biaya;

            // E. Pengeluaran Tetap Debitur
            $biaya_hidup = floatval($_POST['biaya_hidup'] ?? 0);
            
            $cicilan_lain = floatval($_POST['cicilan_lain'] ?? 0);
            
            $total_pengeluaran = $biaya_hidup + $cicilan_lain;

            // F. Net Cashflow (Laba - Pengeluaran)
            $net_cashflow = $laba - $total_pengeluaran;

            // === START LOGIKA REVISI FINANSIAL ===
            $is_revisi_unlock = false;
            $rpc_lama = 0.0;
            if ($id_pengajuan > 0) {
                $stmtO = $pdo->prepare("SELECT status_pengajuan, omset_per_bulan, pendapatan_lain, biaya_operasional, biaya_hidup, cicilan_lain, repayment_capacity_dihitung, repayment_capacity FROM pengajuan_kredit WHERE id_pengajuan = ?");
                $stmtO->execute([$id_pengajuan]);
                $old = $stmtO->fetch(PDO::FETCH_ASSOC);
                
                if ($old && $old['status_pengajuan'] === 'revisi') {
                    if ((float)$old['omset_per_bulan'] !== (float)$omset || (float)$old['pendapatan_lain'] !== (float)$pendapatan_lain || (float)$old['biaya_operasional'] !== (float)$total_biaya || (float)$old['biaya_hidup'] !== (float)$biaya_hidup || (float)$old['cicilan_lain'] !== (float)$cicilan_lain) {
                        $rpc_lama = (float)($old['repayment_capacity_dihitung'] ?? $old['repayment_capacity'] ?? 0);
                        $pdo->prepare("UPDATE pengajuan_kredit SET id_parameter_repayment = NULL, tanggal_analisa = ? WHERE id_pengajuan = ?")
                            ->execute([date('Y-m-d'), $id_pengajuan]);
                        $is_revisi_unlock = true;
                    }
                }
            }
            // === END LOGIKA REVISI FINANSIAL ===

            // G. Repayment Capacity dari master parameter
            $repaymentResult = persistRepaymentCalculationForPengajuan($pdo, $id_pengajuan, $jenis_pekerjaan_post, [
                'net_cashflow' => $net_cashflow,
                'gaji_bersih' => 0,
                'pendapatan' => $omset + $pendapatan_lain,
                'laba_bersih' => $laba,
            ]);
            $rpc = $repaymentResult['rpc'];
            $rpc_dihitung = $repaymentResult['rpc_dihitung'];
            $id_param_repayment = $repaymentResult['id_parameter'];
            $snapshot_json = $repaymentResult['snapshot_json'];
            
            // STEP 9: Capture repayment parameter snapshot for business type (umum, kretamas, etc.)
            $snapshotResult = captureRepaymentParameterSnapshot($pdo, $id_pengajuan, $jenis_pekerjaan_post, $laba, [
                'override_aktif' => (int)($overrideData['override_aktif'] ?? 0),
                'override_by' => (int)($overrideData['override_by'] ?? 0),
                'override_alasan' => $overrideData['override_alasan'] ?? null,
            ]);
            if ($snapshotResult['success']) {
                // Snapshot captured successfully
                logError('repayment_snapshot_usaha', ['id_snapshot' => $snapshotResult['id_snapshot'], 'jenis' => $jenis_pekerjaan_post, 'rpc' => $rpc]);
            }

            // H. Uji Kelayakan
            $angsuran_diajukan = floatval($_POST['angsuran_diajukan'] ?? 0);
            $status_kelayakan = '';
            if ($angsuran_diajukan > 0) {
                $status_kelayakan = ($rpc >= $angsuran_diajukan) ? 'LAYAK' : 'TIDAK LAYAK';
            }

            // I. Upload Foto (Max 2 MB)
            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            try { $pdo->exec("ALTER TABLE pengajuan_kredit ADD COLUMN IF NOT EXISTS foto_data_pendukung VARCHAR(255) NULL AFTER foto_usaha"); } catch(Exception $e) {}

            $file_updates = "";
            $file_params = [];

            $uploaded_usaha = [];
            if (isset($_FILES['foto_usaha'])) {
                $is_array = is_array($_FILES['foto_usaha']['name']);
                $count = $is_array ? count($_FILES['foto_usaha']['name']) : 1;
                
                for ($i = 0; $i < $count; $i++) {
                    $err = $is_array ? $_FILES['foto_usaha']['error'][$i] : $_FILES['foto_usaha']['error'];
                    if ($err == UPLOAD_ERR_OK) {
                        $size = $is_array ? $_FILES['foto_usaha']['size'][$i] : $_FILES['foto_usaha']['size'];
                        $name = $is_array ? $_FILES['foto_usaha']['name'][$i] : $_FILES['foto_usaha']['name'];
                        $tmp  = $is_array ? $_FILES['foto_usaha']['tmp_name'][$i] : $_FILES['foto_usaha']['tmp_name'];
                        
                        if ($size <= 2 * 1024 * 1024) {
                            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                            if (in_array($ext, $allowed)) {
                                $mimeErr = bankKreditVerifyUploadMime($tmp, $name);
                                if ($mimeErr !== null) {
                                    echo json_encode(['success' => false, 'message' => $mimeErr]);
                                    exit;
                                }
                                $newFileName = uniqid('foto_usaha_') . '.' . $ext;
                                if (move_uploaded_file($tmp, $uploadDir . $newFileName)) {
                                    $uploaded_usaha[] = $newFileName;
                                }
                            } else {
                                echo json_encode(['success' => false, 'message' => 'Format Foto Usaha tidak valid (JPG, PNG, WEBP).']);
                                exit;
                            }
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Ukuran salah satu Foto Usaha melebihi 2 MB!']);
                            exit;
                        }
                    }
                }
            }

            if (!empty($uploaded_usaha)) {
                $stmt = $pdo->prepare("SELECT foto_usaha FROM pengajuan_kredit WHERE id_pengajuan = ? AND " . getAnalisEditableCondition());
                $stmt->execute([$id_pengajuan]);
                $existing = $stmt->fetchColumn();
                $all_files = trim((string)$existing) ? explode('|', $existing) : [];
                $all_files = array_merge($all_files, $uploaded_usaha);
                $final_string = implode('|', $all_files);

                $file_updates .= ", foto_usaha=?";
                $file_params[] = $final_string;
            }

            $uploaded_pendukung = [];
            if (isset($_FILES['foto_data_pendukung'])) {
                $is_array = is_array($_FILES['foto_data_pendukung']['name']);
                $count = $is_array ? count($_FILES['foto_data_pendukung']['name']) : 1;
                
                for ($i = 0; $i < $count; $i++) {
                    $err = $is_array ? $_FILES['foto_data_pendukung']['error'][$i] : $_FILES['foto_data_pendukung']['error'];
                    if ($err == UPLOAD_ERR_OK) {
                        $size = $is_array ? $_FILES['foto_data_pendukung']['size'][$i] : $_FILES['foto_data_pendukung']['size'];
                        $name = $is_array ? $_FILES['foto_data_pendukung']['name'][$i] : $_FILES['foto_data_pendukung']['name'];
                        $tmp  = $is_array ? $_FILES['foto_data_pendukung']['tmp_name'][$i] : $_FILES['foto_data_pendukung']['tmp_name'];
                        
                        if ($size <= 2 * 1024 * 1024) {
                            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
                            if (in_array($ext, $allowed)) {
                                $mimeErr = bankKreditVerifyUploadMime($tmp, $name);
                                if ($mimeErr !== null) {
                                    echo json_encode(['success' => false, 'message' => $mimeErr]);
                                    exit;
                                }
                                $newFileName = uniqid('data_pendukung_') . '.' . $ext;
                                if (move_uploaded_file($tmp, $uploadDir . $newFileName)) {
                                    $uploaded_pendukung[] = $newFileName;
                                }
                            } else {
                                echo json_encode(['success' => false, 'message' => 'Format Foto Data Pendukung tidak valid.']);
                                exit;
                            }
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Ukuran salah satu Foto Data Pendukung melebihi 2 MB!']);
                            exit;
                        }
                    }
                }
            }

            if (!empty($uploaded_pendukung)) {
                $stmt = $pdo->prepare("SELECT foto_data_pendukung FROM pengajuan_kredit WHERE id_pengajuan = ? AND " . getAnalisEditableCondition());
                $stmt->execute([$id_pengajuan]);
                $existing = $stmt->fetchColumn();
                $all_files = trim((string)$existing) ? explode('|', $existing) : [];
                $all_files = array_merge($all_files, $uploaded_pendukung);
                $final_string = implode('|', $all_files);

                $file_updates .= ", foto_data_pendukung=?";
                $file_params[] = $final_string;
            }

            $sql = "UPDATE pengajuan_kredit SET 
                nama_usaha=?, bidang_usaha=?, lama_usaha=?,
                omset_per_bulan=?, pendapatan_lain=?,
                biaya_bahan_baku=?, biaya_gaji=?, biaya_listrik=?, biaya_air=?, biaya_sewa=?, biaya_transportasi=?, biaya_lainnya=?,
                biaya_operasional=?,
                laba_bersih=?,
                penyusutan=?, cashflow_usaha=?,
                biaya_hidup=?, cicilan_lain=?, total_pengeluaran_tetap=?,
                net_cashflow=?,
                repayment_capacity=?, repayment_capacity_dihitung=?, id_parameter_repayment=?, repayment_parameter_snapshot=?,
                angsuran_diajukan=?, status_kelayakan=? {$file_updates}
                WHERE id_pengajuan=? AND " . ANALIS_DRAFT_LIKE;

            $params = [
                $usaha, $bidang, $lama, $omset, $pendapatan_lain,
                $b_bahan_baku, $b_gaji, $b_listrik, $b_air, $b_sewa, $b_transportasi, $b_lainnya,
                $total_biaya, $laba, 0, $laba,
                $biaya_hidup, $cicilan_lain, $total_pengeluaran,
                $net_cashflow, $rpc, $rpc_dihitung, $id_param_repayment, $snapshot_json, $angsuran_diajukan, $status_kelayakan
            ];
            $params = array_merge($params, $file_params, [$id_pengajuan]);

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            if (isset($is_revisi_unlock) && $is_revisi_unlock) {
                log_activity($pdo, $_SESSION['user_id'] ?? 0, 
                    "REVISI FINANSIAL UMUM: Terdeteksi perubahan cashflow. Parameter direset ke yang terbaru. Repayment lama: Rp " . number_format($rpc_lama,0) . " -> Baru: Rp " . number_format($rpc,0)
                );
                $msg = 'Data berhasil disimpan. NOTIFIKASI: Revisi data finansial mengubah hasil perhitungan Repayment Capacity menjadi Rp ' . number_format($rpc,0,',','.');
            } else {
                $msg = 'Data Usaha & Analisa Kemampuan Bayar berhasil disimpan!';
            }
            
            echo json_encode(['success' => true, 'message' => $msg]);
            break;

        // ============================================================
        // SECTION 3: STRUKTUR KREDIT
        // ============================================================
        case 'struktur':
            if ($id_pengajuan <= 0) {
                echo json_encode(['success' => false, 'message' => 'Simpan Data Pemohon terlebih dahulu!']);
                exit;
            }
            $jumlah = floatval($_POST['jumlah_kredit'] ?? 0);
            $suku_bunga = floatval($_POST['suku_bunga'] ?? 0);
            $waktu = intval($_POST['jangka_waktu'] ?? 0);
            $jangka_tempo = intval($_POST['jangka_tempo'] ?? 1);
            $grace_period = intval($_POST['grace_period'] ?? 0);
            $tujuan = strtoupper(trim($_POST['tujuan_kredit'] ?? ''));
            $jenis_kredit = $_POST['jenis_kredit'] ?? 'KMK';
            if ($jumlah < 0 || $suku_bunga < 0 || $waktu < 0 || $grace_period < 0) {
                echo json_encode(['success' => false, 'message' => 'Nilai struktur kredit tidak boleh negatif.']);
                exit;
            }

            // Validate grace period < tenor
            if ($grace_period >= $waktu && $waktu > 0) {
                $grace_period = $waktu - 1;
            }

            // === START LOGIKA REVISI FINANSIAL (STRUKTUR) ===
            $is_revisi_unlock = false;
            $rpc_lama = 0.0;
            $rpc_baru = 0.0;
            if ($id_pengajuan > 0) {
                $stmtO = $pdo->prepare("SELECT status_pengajuan, jumlah_kredit, jangka_waktu, jenis_kredit, jenis_pekerjaan, net_cashflow, laba_bersih, repayment_capacity_dihitung, repayment_capacity FROM pengajuan_kredit WHERE id_pengajuan = ?");
                $stmtO->execute([$id_pengajuan]);
                $old = $stmtO->fetch(PDO::FETCH_ASSOC);
                
                if ($old && $old['status_pengajuan'] === 'revisi') {
                    if ((float)$old['jumlah_kredit'] !== $jumlah || (int)$old['jangka_waktu'] !== $waktu || $old['jenis_kredit'] !== $jenis_kredit) {
                        $rpc_lama = (float)($old['repayment_capacity_dihitung'] ?? $old['repayment_capacity'] ?? 0);
                        $pdo->prepare("UPDATE pengajuan_kredit SET id_parameter_repayment = NULL, tanggal_analisa = ? WHERE id_pengajuan = ?")
                            ->execute([date('Y-m-d'), $id_pengajuan]);
                        
                        $repaymentResult = persistRepaymentCalculationForPengajuan($pdo, $id_pengajuan, $old['jenis_pekerjaan'] ?? 'umum', [
                            'net_cashflow' => (float)($old['net_cashflow'] ?? 0),
                            'gaji_bersih' => (float)($old['laba_bersih'] ?? 0),
                            'pendapatan' => (float)($old['laba_bersih'] ?? 0),
                            'laba_bersih' => (float)($old['laba_bersih'] ?? 0),
                        ]);
                        
                        $rpc_baru = $repaymentResult['rpc'];
                        // Lakukan pre-update untuk parameter baru sebelum update struktur dilakukan
                        $pdo->prepare("UPDATE pengajuan_kredit SET repayment_capacity=?, repayment_capacity_dihitung=?, id_parameter_repayment=?, repayment_parameter_snapshot=? WHERE id_pengajuan=?")
                            ->execute([$repaymentResult['rpc'], $repaymentResult['rpc_dihitung'], $repaymentResult['id_parameter'], $repaymentResult['snapshot_json'], $id_pengajuan]);
                        
                        $is_revisi_unlock = true;
                    }
                }
            }
            // === END LOGIKA REVISI FINANSIAL (STRUKTUR) ===

            $stmt = $pdo->prepare("UPDATE pengajuan_kredit SET jumlah_kredit=?, suku_bunga=?, jangka_waktu=?, jangka_tempo=?, grace_period=?, tujuan_kredit=?, jenis_kredit=? WHERE id_pengajuan=? AND " . getAnalisEditableCondition());
            $stmt->execute([$jumlah, $suku_bunga, $waktu, $jangka_tempo, $grace_period, $tujuan, $jenis_kredit, $id_pengajuan]);

            // Untuk Cash Collateral, status kelayakan dihitung dari Taksasi vs Jumlah Kredit
            if (($jenis_pekerjaan_post ?? '') === 'cashcolateral') {
                $stmtT = $pdo->prepare("SELECT SUM(nilai_taksasi) FROM jaminan_cashcolateral WHERE id_pengajuan=?");
                $stmtT->execute([$id_pengajuan]);
                $taksasi = floatval($stmtT->fetchColumn());
                
                $status_kelayakan = ($taksasi >= $jumlah) ? 'LAYAK' : 'TIDAK LAYAK';
                $pdo->prepare("UPDATE pengajuan_kredit SET status_kelayakan=? WHERE id_pengajuan=?")
                    ->execute([$status_kelayakan, $id_pengajuan]);
            }

            if (isset($is_revisi_unlock) && $is_revisi_unlock) {
                log_activity($pdo, $_SESSION['user_id'] ?? 0, 
                    "REVISI FINANSIAL STRUKTUR: Terdeteksi perubahan plafon/tenor/jenis_kredit. Parameter direset ke yang terbaru. Repayment lama: Rp " . number_format($rpc_lama,0) . " -> Baru: Rp " . number_format($rpc_baru,0)
                );
                $msg = 'Struktur Kredit Data berhasil disimpan. NOTIFIKASI: Revisi mengubah hasil perhitungan Repayment Capacity menjadi Rp ' . number_format($rpc_baru,0,',','.');
            } else {
                $msg = 'Struktur Kredit berhasil disimpan!';
            }

            echo json_encode(['success' => true, 'message' => $msg]);
            break;

        // ============================================================
        // SECTION 4: DATA AGUNAN (MULTI-AGUNAN SUPPORT)
        // ============================================================
        case 'agunan':
            if ($id_pengajuan <= 0) {
                echo json_encode(['success' => false, 'message' => 'Simpan Data Pemohon terlebih dahulu!']);
                exit;
            }

            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // --- Handle foto_usaha (single file, not per-agunan) ---
            $foto_usaha_col = '';
            if (isset($_FILES['foto_usaha']) && $_FILES['foto_usaha']['error'] == UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['foto_usaha']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'webp'];
                if (in_array($ext, $allowed)) {
                    $mimeErr = bankKreditVerifyUploadMime($_FILES['foto_usaha']['tmp_name'], $_FILES['foto_usaha']['name']);
                    if ($mimeErr !== null) {
                        echo json_encode(['success' => false, 'message' => $mimeErr]);
                        exit;
                    }
                    $newFileName = uniqid('foto_usaha_') . '.' . $ext;
                    move_uploaded_file($_FILES['foto_usaha']['tmp_name'], $uploadDir . $newFileName);
                    $foto_usaha_col = $newFileName;
                }
            }

            // Update foto_usaha on pengajuan_kredit if uploaded
            if ($foto_usaha_col) {
                $pdo->prepare("UPDATE pengajuan_kredit SET foto_usaha=? WHERE id_pengajuan=? AND " . ANALIS_DRAFT_LIKE)
                    ->execute([$foto_usaha_col, $id_pengajuan]);
            }

            // --- Begin transaction for multi-agunan ---
            try {
                $pdo->beginTransaction();

                // Delete old jaminan data (will be re-inserted from form)
                $pdo->prepare("DELETE FROM jaminan_tanah_bangunan WHERE id_pengajuan=?")->execute([$id_pengajuan]);
                $pdo->prepare("DELETE FROM jaminan_kendaraan WHERE id_pengajuan=?")->execute([$id_pengajuan]);
                $pdo->prepare("DELETE FROM jaminan_emas WHERE id_pengajuan=?")->execute([$id_pengajuan]);

                // Get array of jenis_jaminan per agunan
                $jenis_jaminan_arr = $_POST['jenis_jaminan'] ?? [];
                if (!is_array($jenis_jaminan_arr)) {
                    // backward compatibility: if single value, wrap in array
                    $jenis_jaminan_arr = [$jenis_jaminan_arr];
                }

                $count_saved = 0;
                $first_jenis = 'tanah_bangunan'; // default for pengajuan_kredit.jenis_jaminan

                foreach ($jenis_jaminan_arr as $i => $jenis) {
                    $jenis = trim($jenis);
                    if (empty($jenis))
                        continue;

                    if ($count_saved === 0) {
                        $first_jenis = $jenis; // track first jenis for pengajuan_kredit column
                    }

                    if ($jenis === 'tanah_bangunan') {
                        // --- TANAH & BANGUNAN ---
                        // Safely get array values with index check
                        $luas_tanah = floatval($_POST['luas_tanah'][$i] ?? 0);
                        $luas_tanah_sppt = floatval($_POST['luas_tanah_sppt'][$i] ?? 0);
                        $harga_tanah_sppt = floatval($_POST['harga_tanah_sppt'][$i] ?? 0);
                        $harga_tanah_pasar = floatval($_POST['harga_tanah_pasar'][$i] ?? 0);
                        $luas_bangunan_1 = floatval($_POST['luas_bangunan'][$i] ?? 0);
                        $luas_bangunan_2 = floatval($_POST['luas_bangunan_2'][$i] ?? 0);
                        $harga_bangunan = floatval($_POST['harga_bangunan_m2'][$i] ?? 0);
                        $alamat = $_POST['alamat'][$i] ?? '';
                        $jenis_surat = $_POST['jenis_surat'][$i] ?? 'SHM';
                        $nomor_surat = $_POST['nomor_surat'][$i] ?? '';
                        $atas_nama = $_POST['atas_nama'][$i] ?? '';
                        $kategori = $_POST['kategori_agunan'][$i] ?? 'rumah_tinggal';
                        $masa_covernote = null;
                        if ($jenis_surat === 'Covernote' && !empty($_POST['masa_covernote_multi'][$i])) {
                            $masa_covernote = $_POST['masa_covernote_multi'][$i];
                        }

                        // Skip jika data kunci kosong (defensive)
                        if ($luas_tanah <= 0 && $luas_tanah_sppt <= 0 && $harga_tanah_pasar <= 0 && empty($alamat) && empty($nomor_surat)) {
                            continue;
                        }

                        // Calculations — synced with frontend logic
                        $taksasi_pct = 0.50; // default for AJB / Letter C / Covernote
                        if ($kategori === 'sawah_tegal') {
                            $taksasi_pct = 0.70;
                        } else {
                            if ($jenis_surat === 'SHM' || $jenis_surat === 'SHGB') {
                                $taksasi_pct = 0.75;
                            } else {
                                $taksasi_pct = 0.50;
                            }
                        }

                        $nilai_wajar_sppt = $luas_tanah_sppt * $harga_tanah_sppt;
                        $nilai_taksasi_sppt = $nilai_wajar_sppt * $taksasi_pct;
                        $nilai_likuidasi_sppt = $nilai_taksasi_sppt * 0.70;

                        // For pasar: use luas_tanah (SHM), fallback to luas_tanah_sppt for non-SHM
                        $luas_for_pasar = $luas_tanah > 0 ? $luas_tanah : $luas_tanah_sppt;
                        $val_tanah = $luas_for_pasar * $harga_tanah_pasar;
                        $val_bangunan = ($luas_bangunan_1 + $luas_bangunan_2) * $harga_bangunan;
                        $nilai_pasar_total = $val_tanah + $val_bangunan;
                        $nilai_taksasi_total = $nilai_pasar_total * $taksasi_pct;
                        $nilai_likuidasi_total = $nilai_taksasi_total * 0.70;

                        // Handle manual taksasi override
                        $tipe_valuasi_tanah = $_POST['tipe_valuasi_tanah'][$i] ?? 'otomatis';
                        $nilai_taksasi_manual_tanah = null;
                        if ($tipe_valuasi_tanah === 'manual') {
                            $nilai_taksasi_manual_tanah = floatval($_POST['nilai_taksasi_manual_tanah'][$i] ?? 0);
                            if ($nilai_taksasi_manual_tanah > 0) {
                                $nilai_taksasi_total = $nilai_taksasi_manual_tanah;
                                $nilai_likuidasi_total = $nilai_taksasi_manual_tanah * 0.70;
                            }
                        }

                        $stmt = $pdo->prepare("INSERT INTO jaminan_tanah_bangunan 
                            (id_pengajuan, alamat_agunan, jenis_surat, masa_covernote, nomor_surat, atas_nama, kategori_agunan, 
                             luas_tanah, luas_tanah_sppt, harga_tanah_sppt, nilai_wajar_sppt, nilai_taksasi_sppt, nilai_likuidasi_sppt,
                             harga_tanah_pasar, luas_bangunan, luas_bangunan_2, harga_bangunan_m2, 
                             nilai_pasar, nilai_taksasi, nilai_likuidasi, tipe_valuasi, nilai_taksasi_manual) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                // Get the last inserted jaminan ID for current agunan
                        $id_jaminan = $pdo->lastInsertId();
                        
                        $stmt->execute([
                            $id_pengajuan,
                            $alamat,
                            $jenis_surat,
                            $masa_covernote,
                            $nomor_surat,
                            $atas_nama,
                            $kategori,
                            $luas_tanah,
                            $luas_tanah_sppt,
                            $harga_tanah_sppt,
                            $nilai_wajar_sppt,
                            $nilai_taksasi_sppt,
                            $nilai_likuidasi_sppt,
                            $harga_tanah_pasar,
                            $luas_bangunan_1,
                            $luas_bangunan_2,
                            $harga_bangunan,
                            $nilai_pasar_total,
                            $nilai_taksasi_total,
                            $nilai_likuidasi_total,
                            $tipe_valuasi_tanah,
                            $nilai_taksasi_manual_tanah
                        ]);
                        $count_saved++;

                    }
                    else if ($jenis === 'kendaraan') {
                        // --- KENDARAAN ---
                        $merk = $_POST['merk'][$i] ?? '';
                        $tipe_kend = $_POST['tipe'][$i] ?? '';
                        $tahun = $_POST['tahun'][$i] ?? '';
                        $nopol = $_POST['nopol'][$i] ?? '';
                        $norangka = $_POST['norangka'][$i] ?? '';
                        $nomesin = $_POST['nomesin'][$i] ?? '';
                        $bpkb_nama = $_POST['bpkb_nama'][$i] ?? '';
                        $nilai_pasar = floatval($_POST['nilai_pasar'][$i] ?? 0);
                        // Skip jika data kunci kosong (defensive)
                        if (empty($merk) && empty($nopol) && $nilai_pasar <= 0) {
                            continue;
                        }

                        $umur = 0;
                        if (!empty($tahun)) {
                            $umur = date('Y') - intval($tahun);
                        }
                        
                        $pKend = 0;
                        if (!empty($tahun) && $nilai_pasar > 0) {
                            if ($umur <= 5) $pKend = 0.85;
                            else if ($umur <= 10) $pKend = 0.75;
                            else $pKend = 0.65;
                        }

                        $nilai_taksasi = $nilai_pasar * $pKend;
                        $nilai_likuidasi = $nilai_taksasi * 0.70;

                        // Handle manual taksasi override
                        $tipe_valuasi_kendaraan = $_POST['tipe_valuasi_kendaraan'][$i] ?? 'otomatis';
                        $nilai_taksasi_manual_kendaraan = null;
                        if ($tipe_valuasi_kendaraan === 'manual') {
                            $nilai_taksasi_manual_kendaraan = floatval($_POST['nilai_taksasi_manual_kendaraan'][$i] ?? 0);
                            if ($nilai_taksasi_manual_kendaraan > 0) {
                                $nilai_taksasi = $nilai_taksasi_manual_kendaraan;
                                $nilai_likuidasi = $nilai_taksasi * 0.70;
                            }
                        }

                        // STNK fields (optional)
                        $no_stnk = trim($_POST['no_stnk'][$i] ?? '');
                        $masa_berlaku_stnk = trim($_POST['masa_berlaku_stnk'][$i] ?? '');
                        
                        // Validate date format if provided
                        if ($masa_berlaku_stnk) {
                            $dateCheck = strtotime($masa_berlaku_stnk);
                            if (!$dateCheck) {
                                // Skip invalid date, don't crash
                                $masa_berlaku_stnk = '';
                            }
                        }

                        $stmt = $pdo->prepare("INSERT INTO jaminan_kendaraan 
                            (id_pengajuan, merk, tipe, tahun_pembuatan, no_polisi, no_rangka, no_mesin, nama_pemilik, 
                             nilai_pasar, nilai_taksasi, nilai_likuidasi, tipe_valuasi, nilai_taksasi_manual, no_stnk, masa_berlaku_stnk) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $id_pengajuan,
                            $merk,
                            $tipe_kend,
                            $tahun,
                            $nopol,
                            $norangka,
                            $nomesin,
                            $bpkb_nama,
                            $nilai_pasar,
                            $nilai_taksasi,
                            $nilai_likuidasi,
                            $tipe_valuasi_kendaraan,
                            $nilai_taksasi_manual_kendaraan,
                            $no_stnk ?: null,
                            $masa_berlaku_stnk ?: null
                        ]);
                        $count_saved++;
                    }
                    else if ($jenis === 'emas') {
                        // --- EMAS ---
                        $harga_per_gram = floatval($_POST['emas_harga_per_gram'][$i] ?? 0);
                        $berat = floatval($_POST['emas_berat'][$i] ?? 0);
                        
                        // Skip jika data kunci kosong (defensive)
                        if ($harga_per_gram <= 0 && $berat <= 0) {
                            continue;
                        }

                        // Calculations
                        $nilai_pasar_emas = $harga_per_gram * $berat;
                        $nilai_taksasi_emas = $nilai_pasar_emas * 0.95;
                        $nilai_likuidasi_emas = $nilai_taksasi_emas;

                        $stmt = $pdo->prepare("INSERT INTO jaminan_emas 
                            (id_pengajuan, harga_per_gram, berat, nilai_pasar, nilai_taksasi, nilai_likuidasi) 
                            VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $id_pengajuan,
                            $harga_per_gram,
                            $berat,
                            $nilai_pasar_emas,
                            $nilai_taksasi_emas,
                            $nilai_likuidasi_emas
                        ]);
                        $count_saved++;
                    }
                // Unknown jenis → skip silently (defensive)
                }

                // Handle per-agunan file uploads safely
                $fileFields = ['file_jaminan', 'foto_rumah', 'file_pendukung'];
                foreach ($fileFields as $field) {
                    if (isset($_FILES[$field]) && is_array($_FILES[$field]['name'])) {
                        // Multi-file array upload — but these are informational only, stored on pengajuan_kredit
                        // Take the first valid file for the pengajuan_kredit record
                        for ($fi = 0; $fi < count($_FILES[$field]['name']); $fi++) {
                            if (isset($_FILES[$field]['error'][$fi]) && $_FILES[$field]['error'][$fi] == UPLOAD_ERR_OK) {
                                $ext = strtolower(pathinfo($_FILES[$field]['name'][$fi], PATHINFO_EXTENSION));
                                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'webp'];
                                if (in_array($ext, $allowed)) {
                                    $mimeErr = bankKreditVerifyUploadMime($_FILES[$field]['tmp_name'][$fi], $_FILES[$field]['name'][$fi]);
                                    if ($mimeErr !== null) {
                                        echo json_encode(['success' => false, 'message' => $mimeErr]);
                                        exit;
                                    }
                                    $newFileName = uniqid($field . '_') . '.' . $ext;
                                    move_uploaded_file($_FILES[$field]['tmp_name'][$fi], $uploadDir . $newFileName);
                                    // Store first valid file in pengajuan_kredit
                                    $colName = ($field === 'foto_rumah') ? 'foto_rumah' : 'file_jaminan';
                                    $pdo->prepare("UPDATE pengajuan_kredit SET {$colName}=? WHERE id_pengajuan=? AND " . ANALIS_DRAFT_LIKE)
                                        ->execute([$newFileName, $id_pengajuan]);
                                    break; // only first valid file
                                }
                            }
                        }
                    }
                    elseif (isset($_FILES[$field]) && $_FILES[$field]['error'] == UPLOAD_ERR_OK) {
                        // Single file upload (backward compatibility)
                        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'webp'];
                        if (in_array($ext, $allowed)) {
                            $mimeErr = bankKreditVerifyUploadMime($_FILES[$field]['tmp_name'], $_FILES[$field]['name']);
                            if ($mimeErr !== null) {
                                echo json_encode(['success' => false, 'message' => $mimeErr]);
                                exit;
                            }
                            $newFileName = uniqid($field . '_') . '.' . $ext;
                            move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir . $newFileName);
                            $colName = ($field === 'foto_rumah') ? 'foto_rumah' : 'file_jaminan';
                            $pdo->prepare("UPDATE pengajuan_kredit SET {$colName}=? WHERE id_pengajuan=? AND " . ANALIS_DRAFT_LIKE)
                                ->execute([$newFileName, $id_pengajuan]);
                        }
                    }
                }

                // ===== Handle Multiple Agunan Foto Upload (New Feature) =====
                if (isset($_FILES['agunan_foto']) && is_array($_FILES['agunan_foto']['name'])) {
                    $uploadDir = __DIR__ . '/../assets/uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // Get latest jaminan ID for associating photos
                    // (We'll associate all photos with the last inserted jaminan)
                    $lastJaminanQuery = $pdo->prepare("
                        SELECT id_jaminan FROM jaminan_tanah_bangunan 
                        WHERE id_pengajuan = ? 
                        ORDER BY id_jaminan DESC LIMIT 1
                    ");
                    $lastJaminanQuery->execute([$id_pengajuan]);
                    $lastJaminan = $lastJaminanQuery->fetch(PDO::FETCH_ASSOC);
                    $id_jaminan_for_foto = $lastJaminan['id_jaminan'] ?? 0;

                    // If no tanah, try kendaraan
                    if ($id_jaminan_for_foto <= 0) {
                        $lastJaminanQuery = $pdo->prepare("
                            SELECT id_jaminan FROM jaminan_kendaraan 
                            WHERE id_pengajuan = ? 
                            ORDER BY id_jaminan DESC LIMIT 1
                        ");
                        $lastJaminanQuery->execute([$id_pengajuan]);
                        $lastJaminan = $lastJaminanQuery->fetch(PDO::FETCH_ASSOC);
                        $id_jaminan_for_foto = $lastJaminan['id_jaminan'] ?? 0;
                        $tipe_jaminan_for_foto = 'kendaraan';
                    } else {
                        $tipe_jaminan_for_foto = 'tanah_bangunan';
                    }

                    // Process each uploaded file
                    for ($fi = 0; $fi < count($_FILES['agunan_foto']['name']); $fi++) {
                        if ($_FILES['agunan_foto']['error'][$fi] != UPLOAD_ERR_OK) {
                            continue; // Skip files with errors
                        }

                        $fileName = $_FILES['agunan_foto']['name'][$fi];
                        $tmpFile = $_FILES['agunan_foto']['tmp_name'][$fi];
                        $fileSize = $_FILES['agunan_foto']['size'][$fi];

                        // Validate file
                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $allowed = ['jpg', 'jpeg', 'png'];
                        if (!in_array($ext, $allowed)) {
                            continue; // Skip unsupported format
                        }

                        // Validate file size (5 MB max)
                        if ($fileSize > 5 * 1024 * 1024) {
                            continue; // Skip oversized file
                        }

                        // Validate MIME type
                        $mimeErr = bankKreditVerifyUploadMime($tmpFile, $fileName);
                        if ($mimeErr !== null) {
                            continue; // Skip invalid MIME
                        }

                        // Save file
                        $newFileName = uniqid('agunan_foto_') . '.' . $ext;
                        if (move_uploaded_file($tmpFile, $uploadDir . $newFileName)) {
                            // Insert into agunan_foto table
                            $insertFotoStmt = $pdo->prepare("
                                INSERT INTO agunan_foto (id_jaminan, id_pengajuan, tipe_jaminan, nama_file, ukuran, tipe_file, keterangan, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $insertFotoStmt->execute([
                                $id_jaminan_for_foto,
                                $id_pengajuan,
                                $tipe_jaminan_for_foto,
                                $newFileName,
                                $fileSize,
                                $ext,
                                null // keterangan dapat ditambahkan nanti jika diperlukan
                            ]);
                        }
                    }
                }

                // Update jenis_jaminan on pengajuan_kredit (use first agunan's type for backward compat)
                $pdo->prepare("UPDATE pengajuan_kredit SET jenis_jaminan=? WHERE id_pengajuan=? AND " . ANALIS_DRAFT_LIKE)
                    ->execute([$first_jenis, $id_pengajuan]);

                // 📋 Audit log dengan banking-standard format
                log_activity($pdo, $_SESSION['user_id'] ?? 0, 
                    "Menyimpan " . $count_saved . " Data Agunan (ID Pengajuan: " . $id_pengajuan . " | Jenis: " . $first_jenis . ")");

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => "Data Agunan berhasil disimpan! ({$count_saved} agunan tersimpan)"]);
            }
            catch (Exception $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan agunan: ' . $ex->getMessage()]);
            }
            break;

        // ============================================================
        // SECTION 5: NERACA
        // ============================================================
        case 'neraca':
            if ($id_pengajuan <= 0) {
                echo json_encode(['success' => false, 'message' => 'Simpan Data Pemohon terlebih dahulu!']);
                exit;
            }

            // ===== NERACA SEBELUM KREDIT =====
            $n_kas = floatval($_POST['neraca_kas'] ?? 0);
            $n_bank = floatval($_POST['neraca_bank'] ?? 0);

            // Sum multiple Tanah values if provided (safe)
            $n_tanah = 0;
            if (isset($_POST['tanah_nilai']) && is_array($_POST['tanah_nilai'])) {
                foreach ($_POST['tanah_nilai'] as $v) {
                    $n_tanah += floatval($v);
                }
            }
            else {
                $n_tanah = floatval($_POST['neraca_tanah'] ?? 0);
            }

            // Sum multiple Kendaraan values if provided (safe)
            $n_kend = 0;
            if (isset($_POST['kendaraan_nilai']) && is_array($_POST['kendaraan_nilai'])) {
                foreach ($_POST['kendaraan_nilai'] as $v) {
                    $n_kend += floatval($v);
                }
            }
            else {
                $n_kend = floatval($_POST['neraca_kendaraan'] ?? 0);
            }

            $n_stok = floatval($_POST['neraca_stok'] ?? 0);
            $n_lain = floatval($_POST['neraca_lain'] ?? 0);
            $pinj_bri = floatval($_POST['neraca_pinjaman_bri'] ?? 0);
            $pinj_bawon = floatval($_POST['neraca_pinjaman_bawon'] ?? 0);
            $n_hutang_bank = $pinj_bri + $pinj_bawon;
            
            $n_hutang_lain = floatval($_POST['neraca_hutang_lain'] ?? 0);
            $n_modal = floatval($_POST['neraca_modal'] ?? 0);
            $total_aktiva = $n_kas + $n_bank + $n_tanah + $n_kend + $n_stok + $n_lain;
            $total_pasiva = $n_hutang_bank + $n_hutang_lain + $n_modal;

            // ===== NERACA SESUDAH KREDIT (Manual Input) =====
            $n_kas_sesudah = floatval($_POST['neraca_kas_sesudah'] ?? 0);
            $n_bank_sesudah = floatval($_POST['neraca_bank_sesudah'] ?? 0);
            $n_tanah_sesudah = floatval($_POST['neraca_tanah_sesudah'] ?? 0);
            $n_kend_sesudah = floatval($_POST['neraca_kendaraan_sesudah'] ?? 0);
            $n_stok_sesudah = floatval($_POST['neraca_stok_sesudah'] ?? 0);
            $n_lainnya_sesudah = floatval($_POST['neraca_lainnya_sesudah'] ?? 0);
            
            $n_hutang_lain_sesudah = floatval($_POST['neraca_hutang_lain_sesudah'] ?? 0);
            $pinj_bri_sesudah = floatval($_POST['neraca_pinjaman_bri_sesudah'] ?? 0);
            $pinj_bawon_sesudah = floatval($_POST['neraca_pinjaman_bawon_sesudah'] ?? 0);
            
            $total_aktiva_sesudah = $n_kas_sesudah + $n_bank_sesudah + $n_tanah_sesudah + $n_kend_sesudah + $n_stok_sesudah + $n_lainnya_sesudah;
            $n_hutang_bank_sesudah = $pinj_bri_sesudah + $pinj_bawon_sesudah;
            $n_modal_sesudah = $total_aktiva_sesudah - ($n_hutang_lain_sesudah + $n_hutang_bank_sesudah);
            $total_pasiva_sesudah = $n_hutang_lain_sesudah + $n_hutang_bank_sesudah + $n_modal_sesudah;

            // ===== BALANCE VALIDATION =====
            $balance_diff = abs($total_aktiva_sesudah - $total_pasiva_sesudah);
            $is_balanced = ($balance_diff <= 100); // Tolerance for rounding errors
            
            if (!$is_balanced) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Neraca Sesudah Kredit tidak seimbang! Total Aktiva ≠ Total Pasiva. Selisih: ' . 
                        number_format($balance_diff, 0, ',', '.'),
                    'balance_warning' => true
                ]);
                exit;
            }

            // Delete old then insert new
            $pdo->prepare("DELETE FROM analisa_neraca WHERE id_pengajuan=?")->execute([$id_pengajuan]);
            
            $stmt = $pdo->prepare("
                INSERT INTO analisa_neraca 
                (id_pengajuan, 
                 aktiva_kas, aktiva_tabungan, aktiva_tanah, aktiva_kendaraan, aktiva_stok, aktiva_lainnya, 
                 pasiva_hutang_bank, pasiva_hutang_lain, pasiva_modal, total_aktiva, total_pasiva,
                 aktiva_kas_sesudah, aktiva_tabungan_sesudah, aktiva_tanah_sesudah, aktiva_kendaraan_sesudah, aktiva_stok_sesudah, aktiva_lainnya_sesudah,
                 pasiva_hutang_bank_sesudah, pasiva_hutang_lain_sesudah, pasiva_modal_sesudah, total_aktiva_sesudah, total_pasiva_sesudah)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $id_pengajuan,
                // Sebelum kredit
                $n_kas, $n_bank, $n_tanah, $n_kend, $n_stok, $n_lain,
                $n_hutang_bank, $n_hutang_lain, $n_modal, $total_aktiva, $total_pasiva,
                // Sesudah kredit
                $n_kas_sesudah, $n_bank_sesudah, $n_tanah_sesudah, $n_kend_sesudah, $n_stok_sesudah, $n_lainnya_sesudah,
                $n_hutang_bank_sesudah, $n_hutang_lain_sesudah, $n_modal_sesudah, $total_aktiva_sesudah, $total_pasiva_sesudah
            ]);

            // --- Upload File Pendukung Neraca (opsional) ---
            if (isset($_FILES['file_pendukung_neraca']) && $_FILES['file_pendukung_neraca']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../assets/uploads/';
                if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

                $neracaFile = $_FILES['file_pendukung_neraca'];
                if ($neracaFile['size'] <= 2 * 1024 * 1024) {
                    $ext = strtolower(pathinfo($neracaFile['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp'])) {
                        $newName = 'neraca_' . $id_pengajuan . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($neracaFile['tmp_name'], $uploadDir . $newName)) {
                            $pdo->prepare("UPDATE pengajuan_kredit SET file_pendukung_neraca=? WHERE id_pengajuan=?")
                                ->execute([$newName, $id_pengajuan]);
                        }
                    }
                }
            }

            // 📋 Log activity untuk audit trail
            log_activity($pdo, $_SESSION['user_id'] ?? 0, 
                "Menyimpan Data Neraca (ID Pengajuan: $id_pengajuan | " . 
                "Sebelum: Aktiva=" . number_format($total_aktiva, 0, ',', '.') . 
                " | Sesudah: Aktiva=" . number_format($total_aktiva_sesudah, 0, ',', '.') . ")");

            echo json_encode(['success' => true, 'message' => 'Neraca (Sebelum & Sesudah) berhasil disimpan!']);
            break;

        // ============================================================
        // SECTION 6: ANALISA 6C (BANKING STANDARD - SCORE 1-5)
        // ============================================================
        // SCORING RULES (WAJIB):
        // 1 = Sangat Baik (best)
        // 2 = Baik
        // 3 = Cukup
        // 4 = Kurang
        // 5 = Sangat Kurang (worst)
        // ============================================================
        case '6c':
            if ($id_pengajuan <= 0) {
                echo json_encode(['success' => false, 'message' => 'Simpan Data Pemohon terlebih dahulu!']);
                exit;
            }

            try {
                // TAHAP 1: Validasi dan Ekstrak Scoring Data
                $scoring_data = [
                    'character' => $_POST['score_character'] ?? null,
                    'capacity' => $_POST['score_capacity'] ?? null,
                    'capital' => $_POST['score_capital'] ?? null,
                    'collateral' => $_POST['score_collateral'] ?? null,
                    'condition' => $_POST['score_condition'] ?? null,
                    'constraint' => $_POST['score_constraint'] ?? null
                ];

                // TAHAP 2: Gunakan Helper Function untuk Hitung 6C
                $hasil_6c = hitung_6c($scoring_data);
                
                // Jika ada error validasi
                if (isset($hasil_6c['error'])) {
                    echo json_encode(['success' => false, 'message' => '❌ ' . $hasil_6c['error']]);
                    exit;
                }

                // TAHAP 3: Extract hasil perhitungan
                $detail = $hasil_6c['detail'];
                $total_score = $hasil_6c['total'];
                $rata_rata_score = $hasil_6c['rata'];
                $klasifikasi_6c = $hasil_6c['klasifikasi'];

                // Catatan & Rekomendasi
                $catatan = trim($_POST['catatan_5c'] ?? '-');
                if (empty($catatan)) $catatan = '-';
                
                $catatan_character = trim($_POST['catatan_character'] ?? '');
                $catatan_capacity = trim($_POST['catatan_capacity'] ?? '');
                $catatan_capital = trim($_POST['catatan_capital'] ?? '');
                $catatan_collateral = trim($_POST['catatan_collateral'] ?? '');
                $catatan_condition = trim($_POST['catatan_condition'] ?? '');
                $catatan_constraint = trim($_POST['catatan_constraint'] ?? '');
                
                // Rekomendasi otomatis berdasarkan helper function
                $rekomendasi = $_POST['rekomendasi_6c'] ?? '';
                if (empty($rekomendasi)) {
                    // Gunakan helper function untuk konsistensi di seluruh sistem
                    $rekomendasi = get_rekomendasi_kelayakan($rata_rata_score);
                }

                // TAHAP 4: Simpan ke Database (PDO Prepared Statement)
                $pdo->prepare("DELETE FROM analisa_5c WHERE id_pengajuan=?")->execute([$id_pengajuan]);
                
                $stmt = $pdo->prepare("INSERT INTO analisa_5c
                    (id_pengajuan, character_score, capacity_score, capital_score, condition_score, 
                     collateral_score, constraint_score, total_score, catatan_5c,
                     catatan_character, catatan_capacity, catatan_capital, catatan_collateral, 
                     catatan_condition, catatan_constraint_risk, rekomendasi)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $id_pengajuan,
                    $detail['character']['skor'],
                    $detail['capacity']['skor'],
                    $detail['capital']['skor'],
                    $detail['condition']['skor'],
                    $detail['collateral']['skor'],
                    $detail['constraint']['skor'],
                    $rata_rata_score,  // Simpan rata-rata (bukan total)
                    htmlspecialchars($catatan, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($catatan_character, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($catatan_capacity, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($catatan_capital, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($catatan_collateral, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($catatan_condition, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($catatan_constraint, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($rekomendasi, ENT_QUOTES, 'UTF-8')
                ]);

                // TAHAP 5: Log Activity
                log_activity($pdo, $_SESSION['user_id'] ?? 0, 
                    "Menyimpan Analisa 6C (ID Pengajuan: $id_pengajuan | Rata-rata: $rata_rata_score | Klasifikasi: $klasifikasi_6c | Rekomendasi: $rekomendasi)");

                // TAHAP 6: Response dengan Detail
                echo json_encode([
                    'success' => true, 
                    'message' => '✅ Analisa 6C berhasil disimpan!',
                    'data' => [
                        'rata_rata' => $rata_rata_score,
                        'klasifikasi' => $klasifikasi_6c,
                        'rekomendasi' => $rekomendasi,
                        'detail' => $detail
                    ]
                ]);
            }
            catch (Exception $e) {
                logError('save_section 6c error', [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                echo json_encode(['success' => false, 'message' => '❌ Gagal menyimpan: ' . $e->getMessage()]);
            }
            break;

        // ============================================================
        // SECTION: AGUNAN CASH COLLATERAL
        // ============================================================
        case 'cc_agunan':
            if ($id_pengajuan <= 0) {
                echo json_encode(['success' => false, 'message' => 'Simpan Data Pemohon terlebih dahulu!']);
                exit;
            }

            $pdo->beginTransaction();
            try {
                // Clear old items
                $stmtDel = $pdo->prepare("DELETE FROM jaminan_cashcolateral WHERE id_pengajuan = ?");
                $stmtDel->execute([$id_pengajuan]);

                $agunanList = json_decode($_POST['cc_agunan_json'] ?? '[]', true);
                if (is_array($agunanList) && count($agunanList) > 0) {
                    $stmtIns = $pdo->prepare("INSERT INTO jaminan_cashcolateral 
                        (id_pengajuan, jenis_agunan, nomor_bilyet, nomor_rekening, atas_nama, nilai_nominal, nilai_taksasi, jatuh_tempo, keterangan) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    foreach ($agunanList as $item) {
                        $jenis = $item['jenis'] ?? 'bilyet_deposito';
                        $nomor_bilyet = $item['nomor_bilyet'] ?? null;
                        if ($nomor_bilyet === '') $nomor_bilyet = null;
                        
                        $nomor_rekening = $item['nomor_rekening'] ?? null;
                        if ($nomor_rekening === '') $nomor_rekening = null;
                        
                        $atas_nama = $item['atas_nama'] ?? null;
                        if ($atas_nama === '') $atas_nama = null;
                        
                        $nominal = floatval($item['nilai_nominal'] ?? 0);
                        $taksasi = floatval($item['nilai_taksasi'] ?? 0);
                        
                        $jatuh_tempo = $item['jatuh_tempo'] ?? null;
                        if ($jatuh_tempo === '') $jatuh_tempo = null;
                        
                        $keterangan = $item['keterangan'] ?? null;
                        if ($keterangan === '') $keterangan = null;

                        $stmtIns->execute([
                            $id_pengajuan, $jenis, $nomor_bilyet, $nomor_rekening, $atas_nama, 
                            $nominal, $taksasi, $jatuh_tempo, $keterangan
                        ]);
                    }
                }

                $pdo->commit();

                // Calculate status kelayakan based on total taksasi vs jumlah_kredit
                $stmtKredit = $pdo->prepare("SELECT jumlah_kredit FROM pengajuan_kredit WHERE id_pengajuan=?");
                $stmtKredit->execute([$id_pengajuan]);
                $jumlah_kredit = floatval($stmtKredit->fetchColumn());

                $stmtT = $pdo->prepare("SELECT SUM(nilai_taksasi) FROM jaminan_cashcolateral WHERE id_pengajuan=?");
                $stmtT->execute([$id_pengajuan]);
                $taksasi = floatval($stmtT->fetchColumn());

                $status_kelayakan = ($taksasi >= $jumlah_kredit) ? 'LAYAK' : 'TIDAK LAYAK';
                $pdo->prepare("UPDATE pengajuan_kredit SET status_kelayakan=? WHERE id_pengajuan=?")
                    ->execute([$status_kelayakan, $id_pengajuan]);
                
                log_activity($pdo, $_SESSION['user_id'] ?? 0, "Menyimpan Data Agunan Cash Collateral (ID Pengajuan: $id_pengajuan)");
                echo json_encode(['success' => true, 'message' => '✅ Data Agunan Cash Collateral berhasil disimpan!']);
            } catch (Exception $e) {
                $pdo->rollBack();
                logError('save_section cc_agunan error', ['message' => $e->getMessage()]);
                echo json_encode(['success' => false, 'message' => '❌ Gagal menyimpan agunan: ' . $e->getMessage()]);
            }
            break;

        // ============================================================
        // SECTION 7: SUBMIT FINAL (draft → proses)
        // ============================================================
        case 'submit':
            if ($id_pengajuan <= 0) {
                echo json_encode(['success' => false, 'message' => 'Simpan Data Pemohon terlebih dahulu!']);
                exit;
            }

            $stmtPreSubmit = $pdo->prepare("SELECT jumlah_kredit, jangka_waktu, nama_debitur, nik, angsuran_diajukan, repayment_capacity, repayment_override_aktif, repayment_override_alasan FROM pengajuan_kredit WHERE id_pengajuan = ?");
            $stmtPreSubmit->execute([$id_pengajuan]);
            $preSubmit = $stmtPreSubmit->fetch(PDO::FETCH_ASSOC);
            if (!$preSubmit) {
                echo json_encode(['success' => false, 'message' => 'Pengajuan tidak ditemukan.']);
                exit;
            }
            if (floatval($preSubmit['jumlah_kredit'] ?? 0) <= 0) {
                echo json_encode(['success' => false, 'message' => 'Lengkapi struktur kredit: jumlah kredit harus lebih dari 0 sebelum submit.']);
                exit;
            }
            if ((int) ($preSubmit['jangka_waktu'] ?? 0) <= 0) {
                echo json_encode(['success' => false, 'message' => 'Lengkapi struktur kredit: jangka waktu harus lebih dari 0 bulan sebelum submit.']);
                exit;
            }
            if (trim((string) ($preSubmit['nama_debitur'] ?? '')) === '' || trim((string) ($preSubmit['nik'] ?? '')) === '') {
                echo json_encode(['success' => false, 'message' => 'Data pemohon (nama & NIK) harus lengkap sebelum submit.']);
                exit;
            }
            
            // VALIDASI REPAYMENT CAPACITY
            $angsuran_diajukan = (float)($preSubmit['angsuran_diajukan'] ?? 0);
            $repayment_capacity = (float)($preSubmit['repayment_capacity'] ?? 0);
            if ($angsuran_diajukan > $repayment_capacity) {
                $overrideInfo = ((int)($preSubmit['repayment_override_aktif'] ?? 0) === 1) ? 
                    " [Status Override Direksi AKTIF: " . htmlspecialchars($preSubmit['repayment_override_alasan'] ?? '') . "]" : "";
                
                echo json_encode([
                    'success' => false, 
                    'message' => 'Gagal Meneruskan: Angsuran kredit (Rp ' . number_format($angsuran_diajukan,0,',','.') . ') melebihi Repayment Capacity maksimal (Rp ' . number_format($repayment_capacity,0,',','.') . ').' . $overrideInfo
                ]);
                exit;
            }

            $pdo->beginTransaction();

            $stmtLR = $pdo->prepare("SELECT last_reject_level, jumlah_kredit FROM pengajuan_kredit WHERE id_pengajuan = ?");
            $stmtLR->execute([$id_pengajuan]);
            $dataRow = $stmtLR->fetch(PDO::FETCH_ASSOC);
            $lr = is_string($dataRow['last_reject_level'] ?? null) ? trim($dataRow['last_reject_level']) : '';
            $jumlah_kredit = $dataRow['jumlah_kredit'] ?? 0;

            if ($lr !== '') {
                $targetRole = $lr;
                $skippedRoles = [];
            } else {
                // Pass jumlah_kredit to determine max approval level based on amount
                $nextStep = findNextTarget('analis', $pdo, $jumlah_kredit);
                $targetRole = $nextStep['role'];
                $skippedRoles = $nextStep['skipped'];
            }

            $newSubmitStatus = enumAllows($pdo, 'pengajuan_kredit', 'status_pengajuan', 'diajukan') ? 'diajukan' : 'proses';
            
            // Validate & sanitize targetRole to prevent truncation
            $targetRole = trim($targetRole);
            if (strlen($targetRole) > 100) {
                logError('save_section submit: targetRole too long', ['targetRole' => $targetRole, 'length' => strlen($targetRole)]);
                throw new Exception("Role name exceeds maximum length (100 chars)");
            }
            
            $stmt = $pdo->prepare("UPDATE pengajuan_kredit SET status_pengajuan=?, posisi_saat_ini=?, last_revision_at=NULL, last_revision_by=NULL, last_reject_level=NULL WHERE id_pengajuan=? AND " . ANALIS_DRAFT_LIKE);
            $stmt->execute([$newSubmitStatus, $targetRole, $id_pengajuan]);

            if ($stmt->rowCount() == 0) {
                $pdo->rollBack();
                // Check if application exists but in wrong state
                $stmtCheck = $pdo->prepare("SELECT status_pengajuan FROM pengajuan_kredit WHERE id_pengajuan=?");
                $stmtCheck->execute([$id_pengajuan]);
                $existingStatus = $stmtCheck->fetchColumn();
                
                if ($existingStatus) {
                    logError('save_section submit: invalid status for edit', [
                        'id_pengajuan' => $id_pengajuan,
                        'current_status' => $existingStatus,
                        'editable_statuses' => "'draft','revisi','ditolak','diajukan_ulang','revisi_diajukan'"
                    ]);
                    echo json_encode(['success' => false, 'message' => "Pengajuan dengan status '{$existingStatus}' tidak bisa disubmit. Status harus: draft, revisi, ditolak, atau diajukan_ulang."]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Pengajuan tidak ditemukan.']);
                }
                exit;
            }

            // Approval record
            $stmt = $pdo->prepare("INSERT INTO approval_kredit (id_pengajuan, id_user, level_approval, keputusan, catatan) VALUES (?, ?, 'analis', 'setuju', 'Pengajuan lengkap.')");
            $stmt->execute([$id_pengajuan, $_SESSION['user_id']]);

            foreach ($skippedRoles as $skippedRole) {
                $stmt = $pdo->prepare("INSERT INTO approval_kredit (id_pengajuan, level_approval, keputusan, catatan, is_auto_skip) VALUES (?, ?, 'eskalasi_otomatis', 'Auto Skip.', 1)");
                $stmt->execute([$id_pengajuan, $skippedRole]);
            }

            // ===== CREATE NOTIFICATIONS FOR NEXT ROLE =====
            // Get users of target role
            $stmtNotif = $pdo->prepare("SELECT id_user, nama FROM users WHERE role = ? AND status_jabatan = 'aktif'");
            $stmtNotif->execute([$targetRole]);
            $targetRoleUsers = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
            
            // Create notification for each user in target role
            if (!empty($targetRoleUsers)) {
                foreach ($targetRoleUsers as $user) {
                    createNotification(
                        $user['id_user'],
                        $id_pengajuan,
                        'submitted',
                        'Pengajuan Kredit Baru dari Analis',
                        "Pengajuan kredit a.n {$preSubmit['nama_debitur']} (Rp " . number_format($preSubmit['jumlah_kredit'], 0, ',', '.') . ") telah dikirimkan dari Analis. Silakan lakukan pengecekan dan assessment.",
                        'analis',
                        $targetRole
                    );
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Pengajuan berhasil disubmit!']);
            break;

        // ============================================================
        // SECTION 8A: ADD AGUNAN FOTO (Multiple Photo Upload)
        // ============================================================
        case 'add_agunan_foto':
            if ($id_pengajuan <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID Pengajuan tidak valid.']);
                exit;
            }

            // Check edit permission
            $stmtCheck = $pdo->prepare("SELECT status FROM pengajuan_kredit WHERE id_pengajuan = ?");
            $stmtCheck->execute([$id_pengajuan]);
            $pengajuan = $stmtCheck->fetch();
            
            if (!$pengajuan || !in_array($pengajuan['status'], ['draft', 'revisi', 'ditolak', 'diajukan_ulang'])) {
                echo json_encode(['success' => false, 'message' => 'Pengajuan dalam status yang tidak bisa diedit.']);
                exit;
            }

            if (empty($_FILES['foto_baru'])) {
                echo json_encode(['success' => false, 'message' => 'File foto belum dipilih.']);
                exit;
            }

            $file = $_FILES['foto_baru'];
            
            // Validate file
            if ($file['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Error upload: ' . $file['error']]);
                exit;
            }

            // Validate extension
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                echo json_encode(['success' => false, 'message' => 'Format file harus JPG atau PNG.']);
                exit;
            }

            // Validate size (5MB max)
            if ($file['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'Ukuran file melebihi 5 MB.']);
                exit;
            }

            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime, ['image/jpeg', 'image/png'])) {
                echo json_encode(['success' => false, 'message' => 'Tipe file tidak valid. Harus JPG atau PNG.']);
                exit;
            }

            // Check max photos count (5 for backward compat, but should be 10 for new uploads)
            $stmtCount = $pdo->prepare("SELECT COUNT(*) as total FROM agunan_foto WHERE id_pengajuan = ?");
            $stmtCount->execute([$id_pengajuan]);
            $photoCount = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($photoCount >= 10) {
                echo json_encode(['success' => false, 'message' => 'Sudah mencapai batas maksimum 10 foto agunan.']);
                exit;
            }

            // Move file to uploads directory
            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFilename = uniqid('agunan_foto_') . '.' . $ext;
            $filePath = $uploadDir . $newFilename;

            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file.']);
                exit;
            }

            // Get the last jaminan record to link with (or use null if no jaminan exists yet)
            $stmtJaminan = $pdo->prepare("
                SELECT id_jaminan FROM jaminan_tanah_bangunan WHERE id_pengajuan = ?
                UNION ALL
                SELECT id_jaminan FROM jaminan_kendaraan WHERE id_pengajuan = ?
                LIMIT 1
            ");
            $stmtJaminan->execute([$id_pengajuan, $id_pengajuan]);
            $jaminanRecord = $stmtJaminan->fetch();
            $id_jaminan = $jaminanRecord ? $jaminanRecord['id_jaminan'] : null;

            // Insert into agunan_foto
            $keterangan = trim($_POST['keterangan_baru'] ?? '');
            $stmtInsert = $pdo->prepare("
                INSERT INTO agunan_foto (id_jaminan, id_pengajuan, tipe_jaminan, nama_file, ukuran, tipe_file, keterangan, created_at, updated_at)
                VALUES (?, ?, NULL, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmtInsert->execute([$id_jaminan, $id_pengajuan, $newFilename, $file['size'], $mime, $keterangan]);

            echo json_encode(['success' => true, 'message' => 'Foto agunan berhasil diupload.', 'filename' => $newFilename]);
            break;

        // ============================================================
        // SECTION 8B: DELETE AGUNAN FOTO
        // ============================================================
        case 'delete_agunan_foto':
            if ($id_pengajuan <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID Pengajuan tidak valid.']);
                exit;
            }

            // Check edit permission
            $stmtCheck = $pdo->prepare("SELECT status FROM pengajuan_kredit WHERE id_pengajuan = ?");
            $stmtCheck->execute([$id_pengajuan]);
            $pengajuan = $stmtCheck->fetch();
            
            if (!$pengajuan || !in_array($pengajuan['status'], ['draft', 'revisi', 'ditolak', 'diajukan_ulang'])) {
                echo json_encode(['success' => false, 'message' => 'Pengajuan dalam status yang tidak bisa diedit.']);
                exit;
            }

            $foto_id = intval($_POST['foto_id'] ?? 0);
            if ($foto_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID Foto tidak valid.']);
                exit;
            }

            // Fetch the foto record to verify ownership and get filename
            $stmtFoto = $pdo->prepare("SELECT nama_file FROM agunan_foto WHERE id = ? AND id_pengajuan = ?");
            $stmtFoto->execute([$foto_id, $id_pengajuan]);
            $fotoRecord = $stmtFoto->fetch();

            if (!$fotoRecord) {
                echo json_encode(['success' => false, 'message' => 'Foto tidak ditemukan.']);
                exit;
            }

            // Delete file from disk
            $filePath = __DIR__ . '/../assets/uploads/' . $fotoRecord['nama_file'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }

            // Delete record from database
            $stmtDelete = $pdo->prepare("DELETE FROM agunan_foto WHERE id = ? AND id_pengajuan = ?");
            $stmtDelete->execute([$foto_id, $id_pengajuan]);

            echo json_encode(['success' => true, 'message' => 'Foto agunan berhasil dihapus.']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Section tidak valid.']);
    }
}
catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    
    // Log error for debugging
    logError('save_section catch error', [
        'section' => $_POST['section'] ?? 'unknown',
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'user_id' => $_SESSION['user_id'] ?? null
    ]);
    
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
