<?php
/**
 * ========================================================================
 * CONTOH IMPLEMENTASI - SAVE SECTION PHP
 * ========================================================================
 * File ini menunjukkan PERSIS bagaimana mengupdate analis/save_section.php
 * untuk handle field-field baru dari form PPPK yang sudah diperbaiki.
 * 
 * INSTRUKSI:
 * 1. Buka file: analis/save_section.php
 * 2. Cari section: if ($section === 'penghasilan_pegawai')
 * 3. Tambahkan code dari bagian-bagian di bawah
 * 4. Sesuaikan dengan kondisi existing code di file Anda
 * ========================================================================
 */

// ========================================================================
// STEP 1: TAMBAHKAN HELPER FUNCTIONS (Letakkan di awal file sebelum logic)
// ========================================================================

/**
 * Validate date format YYYY-MM-DD
 */
function validateDateFormat($value, $fieldName = 'field')
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
        throw new Exception("$fieldName tanggal tidak valid: $value");
    }
    
    return $value;
}

/**
 * Get human-readable upload error message
 */
function getUploadErrorMessage($code)
{
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File melebihi ukuran maksimal php.ini',
        UPLOAD_ERR_FORM_SIZE => 'File melebihi ukuran form',
        UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
        UPLOAD_ERR_NO_FILE => 'File tidak dipilih',
        UPLOAD_ERR_NO_TMP_DIR => 'Temporary directory hilang',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file',
        UPLOAD_ERR_EXTENSION => 'Upload dihentikan extension',
    ];
    return $errors[$code] ?? 'Error upload tidak diketahui';
}

// ========================================================================
// STEP 2: DALAM SECTION 'penghasilan_pegawai' - TAMBAHKAN LOGIC BERIKUT
// ========================================================================

if ($section === 'penghasilan_pegawai') {
    
    // Cek apakah ini form PPPK
    $jenis_pekerjaan = trim((string)($_POST['jenis_pekerjaan'] ?? 'umum'));
    
    // Initialize variables
    $pppk_tgl_awal = null;
    $pppk_tgl_akhir = null;
    $pppk_sisa_kerja_bulan = 0;
    $pppk_file_sk = null;
    $pppk_file_sk_old = null;
    $angsuran_data = [];
    $total_angsuran = 0;
    
    // ===== HANDLE PPPK SPECIFIC FIELDS =====
    if ($jenis_pekerjaan === 'pppk') {
        
        // --- 1. VALIDASI DATE FIELDS ---
        if (empty($_POST['pppk_tgl_awal'])) {
            throw new Exception('Tanggal awal perjanjian PPPK wajib diisi');
        }
        
        if (empty($_POST['pppk_tgl_akhir'])) {
            throw new Exception('Tanggal akhir perjanjian PPPK wajib diisi');
        }
        
        $pppk_tgl_awal = validateDateFormat($_POST['pppk_tgl_awal'], 'Tanggal awal');
        $pppk_tgl_akhir = validateDateFormat($_POST['pppk_tgl_akhir'], 'Tanggal akhir');
        
        // Validasi: end date >= start date
        if ($pppk_tgl_awal && $pppk_tgl_akhir) {
            $start = strtotime($pppk_tgl_awal);
            $end = strtotime($pppk_tgl_akhir);
            if ($end < $start) {
                throw new Exception('Tanggal akhir tidak boleh lebih kecil dari tanggal awal');
            }
        }
        
        // --- 2. HITUNG SISA MASA KERJA ---
        if ($pppk_tgl_awal && $pppk_tgl_akhir) {
            $start = strtotime($pppk_tgl_awal);
            $end = strtotime($pppk_tgl_akhir);
            $diff_days = ceil(($end - $start) / (24 * 60 * 60));
            $pppk_sisa_kerja_bulan = (int)round($diff_days / 30);
            
            if ($pppk_sisa_kerja_bulan < 0) {
                $pppk_sisa_kerja_bulan = 0;
            }
        }
        
        // --- 3. HANDLE FILE UPLOAD SK ---
        
        // Get old filename jika ini edit
        if ($edit_id > 0) {
            $stmt = $pdo->prepare("SELECT pppk_file_sk FROM pengajuan_kredit WHERE id_pengajuan = ? LIMIT 1");
            $stmt->execute([$edit_id]);
            $pppk_file_sk_old = $stmt->fetchColumn() ?: null;
        }
        
        // Process file if uploaded
        if (isset($_FILES['pppk_file_sk'])) {
            $file = $_FILES['pppk_file_sk'];
            
            // Check upload error
            if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error = getUploadErrorMessage($file['error']);
                    throw new Exception('Error upload file: ' . $error);
                }
                
                // --- Validasi MIME Type ---
                $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($mime_type, $allowed_mimes)) {
                    throw new Exception('Format file tidak didukung: ' . $mime_type);
                }
                
                // --- Validasi Extension ---
                $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext)) {
                    throw new Exception('Ekstensi file tidak valid: .' . $ext);
                }
                
                // --- Validasi File Size ---
                $max_size = 2 * 1024 * 1024;  // 2MB
                if ($file['size'] > $max_size) {
                    $size_mb = number_format($file['size'] / 1024 / 1024, 2);
                    throw new Exception('File terlalu besar: ' . $size_mb . 'MB (max 2MB)');
                }
                
                // --- Move file ke storage ---
                $upload_dir = __DIR__ . '/../assets/uploads/sk_files/';
                
                // Create directory jika belum ada
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        throw new Exception('Gagal membuat folder upload');
                    }
                }
                
                // Generate safe filename
                $timestamp = time();
                $random = substr(md5(uniqid()), 0, 8);
                $filename = 'sk_' . $id_pengajuan . '_' . $timestamp . '_' . $random . '.' . $ext;
                $file_path = $upload_dir . $filename;
                
                // Move file
                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    throw new Exception('Gagal menyimpan file. Cek permission folder assets/');
                }
                
                // Delete old file jika ada
                if ($pppk_file_sk_old && file_exists($upload_dir . $pppk_file_sk_old)) {
                    unlink($upload_dir . $pppk_file_sk_old);
                }
                
                $pppk_file_sk = $filename;
            }
        }
        
        // --- 4. PROCESS ANGSURAN DATA ---
        $angsuran_nominal_arr = $_POST['pppk_angsuran_nominal'] ?? [];
        $angsuran_nama_arr = $_POST['pppk_angsuran_nama'] ?? [];
        
        if (is_array($angsuran_nominal_arr) && count($angsuran_nominal_arr) > 0) {
            foreach ($angsuran_nominal_arr as $i => $nominal) {
                $nama = trim((string)($angsuran_nama_arr[$i] ?? ''));
                $nominal = (int)($nominal ?? 0);
                
                // Validasi: jika ada nominal, nama harus ada
                if ($nominal > 0 || $nama) {
                    if (!$nama) {
                        throw new Exception('Nama produk angsuran #' . ($i + 1) . ' wajib diisi');
                    }
                    if (strlen($nama) > 100) {
                        throw new Exception('Nama produk terlalu panjang (max 100 chars)');
                    }
                }
                
                // Nominal harus positif
                if ($nominal < 0) {
                    throw new Exception('Nominal angsuran #' . ($i + 1) . ' tidak boleh negatif');
                }
                
                // Tambah ke array jika valid
                if ($nominal > 0 && $nama) {
                    $angsuran_data[] = [
                        'nama_produk' => strtoupper($nama),
                        'nominal_angsuran' => $nominal
                    ];
                    $total_angsuran += $nominal;
                }
            }
        }
        
        // Minimal 1 angsuran untuk PPPK
        if (count($angsuran_data) === 0) {
            throw new Exception('Minimal harus ada 1 data angsuran Bank Wonosobo');
        }
    }
    
    // ===== SEKARANG ADD FIELDS KE UPDATE QUERY =====
    // (Setelah validasi semua fields)
    
    if ($jenis_pekerjaan === 'pppk' && $pppk_tgl_awal) {
        
        $update_fields['pppk_tgl_awal'] = $pppk_tgl_awal;
        $update_fields['pppk_tgl_akhir'] = $pppk_tgl_akhir;
        $update_fields['pppk_sisa_kerja_bulan'] = $pppk_sisa_kerja_bulan;
        
        // Nomor SK Agunan dari form
        if (!empty($_POST['pppk_agunan_no_sk'])) {
            $no_sk = trim((string)$_POST['pppk_agunan_no_sk']);
            if (strlen($no_sk) > 100) {
                throw new Exception('Nomor SK terlalu panjang');
            }
            $update_fields['pppk_agunan_no_sk'] = strtoupper($no_sk);
        }
        
        // File SK - hanya jika ada file baru
        if ($pppk_file_sk) {
            $update_fields['pppk_file_sk'] = $pppk_file_sk;
        }
        
        // Total Angsuran
        $update_fields['pppk_total_angsuran'] = $total_angsuran;
    }
}

// ========================================================================
// STEP 3: SETELAH MAIN INSERT/UPDATE QUERY BERHASIL
// ========================================================================

// Tambahkan code ini SETELAH main pengajuan_kredit INSERT/UPDATE berhasil:

if ($section === 'penghasilan_pegawai' && $jenis_pekerjaan === 'pppk' && !empty($angsuran_data)) {
    
    try {
        // Delete existing angsuran detail untuk update case
        $stmt = $pdo->prepare("DELETE FROM pppk_angsuran_detail WHERE id_pengajuan = ?");
        $stmt->execute([$id_pengajuan]);
        
        // Insert detail angsuran baru
        $stmt = $pdo->prepare("
            INSERT INTO pppk_angsuran_detail (id_pengajuan, nama_produk, nominal_angsuran)
            VALUES (?, ?, ?)
        ");
        
        foreach ($angsuran_data as $row) {
            $stmt->execute([
                $id_pengajuan,
                $row['nama_produk'],
                $row['nominal_angsuran']
            ]);
        }
        
    } catch (Exception $e) {
        // Log but don't throw - pengajuan sudah tersimpan
        error_log('Warning: Gagal simpan angsuran detail: ' . $e->getMessage());
    }
}

// ========================================================================
// COMPLETE EXAMPLE - Lokasi excat di save_section.php
// ========================================================================

/**
 * Lokasi di file: analis/save_section.php
 * 
 * Struktur file:
 * 1. Header & requires
 * 2. Helper functions (TAMBAH DI SINI)
 * 3. Main logic:
 *    a. CSRF check
 *    b. Section validation
 *    c. TAMBAH PPPK HANDLING DI SINI
 *    d. Build update fields
 *    e. Execute query
 *    f. TAMBAH ANGSURAN SAVE DI SINI
 *    g. Return JSON response
 */

// Kira-kira struktur akan seperti ini:

/*

<?php
require_once __DIR__ . '/../includes/functions.php';
requireSameRole('analis');

header('Content-Type: application/json');

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

// ===== HELPER FUNCTIONS (TAMBAH INI) =====
function validateDateFormat($value, $fieldName = 'field') { ... }
function getUploadErrorMessage($code) { ... }

// ===== MAIN LOGIC =====
$section = $_POST['section'] ?? '';

try {
    
    if ($section === 'penghasilan_pegawai') {
        
        // ===== PPPK HANDLING (TAMBAH INI) =====
        $jenis = trim((string)($_POST['jenis_pekerjaan'] ?? 'umum'));
        
        if ($jenis === 'pppk') {
            // Validasi date fields
            // Handle file upload
            // Process angsuran data
            // Add ke update_fields
        }
    }
    
    // Execute query...
    
    if ($section === 'penghasilan_pegawai' && $jenis === 'pppk') {
        // ===== SAVE ANGSURAN DETAIL (TAMBAH INI) =====
        // Delete & insert to pppk_angsuran_detail
    }
    
    echo json_encode(['success' => true, 'message' => 'Saved']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>

*/

// ========================================================================
// TESTING - Verify implementation
// ========================================================================

/**
 * Setelah implement code di atas, test dengan:
 * 
 * 1. Fill form PPPK di browser
 * 2. Open browser console (F12)
 * 3. Check Network tab
 * 4. Send form
 * 5. Verify request ke save_section.php
 * 6. Check response (should be success: true)
 * 7. Check database:
 *    - SELECT * FROM pengajuan_kredit WHERE id_pengajuan = ... \G
 *    - SELECT * FROM pppk_angsuran_detail WHERE id_pengajuan = ...;
 * 8. Verify file uploaded ke assets/uploads/sk_files/
 */

// ========================================================================
// ERROR SCENARIOS TO HANDLE
// ========================================================================

/**
 * 1. Date validation fails
 *    - End date < Start date
 *    - Invalid format
 *    -> Throw exception dengan pesan jelas
 * 
 * 2. File upload fails
 *    - Size > 2MB
 *    - Invalid format
 *    - Directory not writable
 *    -> Delete file jika partial, throw exception
 * 
 * 3. Angsuran validation fails
 *    - No entries
 *    - Nama empty but nominal > 0
 *    - Nominal negative
 *    -> Throw exception
 * 
 * 4. Database fails
 *    - angsuran_detail table not exist
 *    - Foreign key error
 *    -> Log error, don't block main save
 */

// ========================================================================
// MONITORING & LOGGING
// ========================================================================

/**
 * Setelah deploy, monitor untuk:
 * 
 * 1. Check logs:
 *    - tail -f logs/error_*.log
 *    - grep "pppk" logs/*.log
 * 
 * 2. Database:
 *    - SELECT COUNT(*) FROM pengajuan_kredit WHERE pppk_tgl_awal IS NOT NULL;
 *    - SELECT COUNT(*) FROM pppk_angsuran_detail;
 * 
 * 3. File system:
 *    - ls -la assets/uploads/sk_files/
 *    - du -sh assets/uploads/sk_files/
 * 
 * 4. Browser console:
 *    - Check untuk JavaScript errors
 *    - Check Network tab untuk request/response
 */

// ========================================================================
// ROLLBACK PLAN
// ========================================================================

/**
 * Jika ada issue, rollback dengan:
 * 
 * 1. Revert save_section.php ke backup
 * 2. Keep database migration (safe)
 * 3. Update form file ke yang lama
 * 4. Test existing form masih works
 * 5. Coordinate dengan user untuk re-entry data
 * 
 * Database structure bisa di-rollback nanti jika diperlukan
 */

?>
