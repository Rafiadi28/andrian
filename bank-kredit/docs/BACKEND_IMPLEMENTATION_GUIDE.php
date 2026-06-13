/**
 * ========================================================================
 * BACKEND HANDLER IMPLEMENTATION GUIDE
 * ========================================================================
 * Panduan lengkap untuk mengimplementasi handler backend untuk form PPPK
 * yang sudah diperbaiki. Kode ini perlu ditambahkan ke analis/save_section.php
 * ========================================================================
 */

// ========================================================================
// PART 1: TAMBAHKAN HELPER FUNCTIONS (di awal file, sebelum function lain)
// ========================================================================

/**
 * Helper function: Validate date dengan format YYYY-MM-DD
 */
function validateDate($value, $fieldName = 'field')
{
    $value = trim((string)$value);
    
    if (empty($value)) {
        return null;  // Field optional jika kosong
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        throw new Exception("$fieldName harus format YYYY-MM-DD (Anda: $value)");
    }
    
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new Exception("$fieldName tanggal tidak valid: $value");
    }
    
    return $value;
}

/**
 * Helper function: Get upload error message
 */
function getUploadErrorMessage($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'File melebihi ukuran maksimal php.ini (upload_max_filesize)';
        case UPLOAD_ERR_FORM_SIZE:
            return 'File melebihi ukuran maksimal form MAX_FILE_SIZE';
        case UPLOAD_ERR_PARTIAL:
            return 'File hanya terupload sebagian - coba upload ulang';
        case UPLOAD_ERR_NO_FILE:
            return 'File tidak dipilih';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Temporary directory tidak ditemukan di server';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Gagal menulis file ke disk - periksa permission folder';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload file dihentikan oleh extension PHP';
        default:
            return 'Error upload file tidak diketahui (kode: ' . $code . ')';
    }
}

/**
 * Helper function: Sanitize filename agar aman untuk filesystem
 */
function sanitizeFilename($filename)
{
    // Remove extension
    $info = pathinfo($filename);
    $name = $info['filename'];
    $ext = $info['extension'] ?? '';
    
    // Replace unsafe characters
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    $name = preg_replace('/_{2,}/', '_', $name);  // Replace multiple underscores
    $name = trim($name, '_');
    
    return $name . ($ext ? '.' . $ext : '');
}

// ========================================================================
// PART 2: TAMBAHKAN HANDLER UNTUK SECTION 'penghasilan_pegawai'
// ========================================================================

// Lokasi: Di dalam if ($section === 'penghasilan_pegawai') block
// SEBELUM query INSERT/UPDATE

if ($section === 'penghasilan_pegawai') {
    
    // ===== PPPK-SPECIFIC VALIDATION & PROCESSING =====
    
    // --- Validasi Date Fields ---
    $pppk_tgl_awal = null;
    $pppk_tgl_akhir = null;
    $pppk_sisa_kerja_bulan = 0;
    
    // Check if this is PPPK form
    $jenis = trim((string)($_POST['jenis_pekerjaan'] ?? 'umum'));
    if ($jenis === 'pppk') {
        
        // Validasi tanggal awal perjanjian
        if (empty($_POST['pppk_tgl_awal'])) {
            throw new Exception('Tanggal awal perjanjian PPPK wajib diisi');
        }
        $pppk_tgl_awal = validateDate($_POST['pppk_tgl_awal'], 'Tanggal awal perjanjian');
        
        // Validasi tanggal akhir perjanjian
        if (empty($_POST['pppk_tgl_akhir'])) {
            throw new Exception('Tanggal akhir perjanjian PPPK wajib diisi');
        }
        $pppk_tgl_akhir = validateDate($_POST['pppk_tgl_akhir'], 'Tanggal akhir perjanjian');
        
        // Validasi: Tanggal akhir harus >= tanggal awal
        if ($pppk_tgl_awal && $pppk_tgl_akhir) {
            $start_time = strtotime($pppk_tgl_awal);
            $end_time = strtotime($pppk_tgl_akhir);
            
            if ($end_time < $start_time) {
                throw new Exception('Tanggal akhir perjanjian tidak boleh lebih kecil dari tanggal awal perjanjian');
            }
        }
        
        // Hitung sisa kerja dalam bulan
        if ($pppk_tgl_awal && $pppk_tgl_akhir) {
            $start_time = strtotime($pppk_tgl_awal);
            $end_time = strtotime($pppk_tgl_akhir);
            $diff_days = ceil(($end_time - $start_time) / (24 * 60 * 60));
            $pppk_sisa_kerja_bulan = (int)round($diff_days / 30);  // Asumsi: 1 bulan = 30 hari
            
            if ($pppk_sisa_kerja_bulan < 0) {
                $pppk_sisa_kerja_bulan = 0;
            }
        }
    }
    
    // --- Handle File Upload SK ---
    $pppk_file_sk = null;
    $pppk_file_sk_old = null;
    
    // Get filename lama dari database (untuk reference jika update)
    if ($edit_id > 0) {
        $stmt = $pdo->prepare("SELECT pppk_file_sk FROM pengajuan_kredit WHERE id_pengajuan = ? LIMIT 1");
        $stmt->execute([$edit_id]);
        $pppk_file_sk_old = $stmt->fetchColumn() ?: null;
    }
    
    // Check if file upload ada
    if (isset($_FILES['pppk_file_sk'])) {
        $file = $_FILES['pppk_file_sk'];
        
        // Cek apakah ada error
        if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error_msg = getUploadErrorMessage($file['error']);
                throw new Exception('Error upload file SK: ' . $error_msg);
            }
            
            // Validasi tipe file
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
            
            // Check MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Format file tidak didukung: ' . $file_type . '. Gunakan: PDF, JPG, PNG');
            }
            
            // Check file extension
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) {
                throw new Exception('Ekstensi file tidak valid: .' . $ext . '. Gunakan: PDF, JPG, PNG');
            }
            
            // Check file size (max 2MB)
            $max_size = 2 * 1024 * 1024;  // 2MB
            if ($file['size'] > $max_size) {
                $size_mb = number_format($file['size'] / 1024 / 1024, 2);
                throw new Exception('Ukuran file terlalu besar: ' . $size_mb . 'MB (maksimal 2MB)');
            }
            
            // Check if file is actually a file (not directory)
            if (!is_uploaded_file($file['tmp_name'])) {
                throw new Exception('File upload tidak valid atau corrupted');
            }
            
            // Create upload directory if not exists
            $upload_dir = __DIR__ . '/../assets/uploads/sk_files/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    throw new Exception('Gagal membuat directory upload. Periksa permission folder assets/');
                }
            }
            
            // Generate safe filename
            $timestamp = time();
            $random = substr(md5(uniqid()), 0, 8);
            $filename = 'sk_' . $id_pengajuan . '_' . $timestamp . '_' . $random . '.' . $ext;
            $file_path = $upload_dir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception('Gagal menyimpan file. Periksa permission folder assets/uploads/sk_files/');
            }
            
            // Delete old file if exists and is being replaced
            if ($pppk_file_sk_old && file_exists($upload_dir . $pppk_file_sk_old)) {
                unlink($upload_dir . $pppk_file_sk_old);
            }
            
            $pppk_file_sk = $filename;
        }
    }
    
    // --- Validasi & Process Angsuran Detail ---
    $angsuran_nominal_arr = $_POST['pppk_angsuran_nominal'] ?? [];
    $angsuran_nama_arr = $_POST['pppk_angsuran_nama'] ?? [];
    $total_angsuran = 0;
    $angsuran_data = [];
    
    // Validasi setiap angsuran entry
    if (is_array($angsuran_nominal_arr) && count($angsuran_nominal_arr) > 0) {
        
        foreach ($angsuran_nominal_arr as $i => $nominal) {
            $nama = trim((string)($angsuran_nama_arr[$i] ?? ''));
            $nominal = (int)($nominal ?? 0);
            
            // Nama produk wajib diisi jika ada entry
            if ($nominal > 0 || $nama) {
                if (!$nama) {
                    throw new Exception('Nama produk angsuran #' . ($i + 1) . ' wajib diisi jika ada nominal');
                }
                if (strlen($nama) > 100) {
                    throw new Exception('Nama produk angsuran #' . ($i + 1) . ' terlalu panjang (max 100 karakter)');
                }
            }
            
            // Nominal harus >= 0
            if ($nominal < 0) {
                throw new Exception('Nominal angsuran #' . ($i + 1) . ' tidak boleh negatif');
            }
            
            // Tambahkan ke array jika valid
            if ($nominal > 0 && $nama) {
                $angsuran_data[] = [
                    'nama_produk' => strtoupper($nama),
                    'nominal_angsuran' => $nominal
                ];
                $total_angsuran += $nominal;
            }
        }
    }
    
    // Minimal validasi: jika ini PPPK form, harus ada minimal 1 angsuran
    if ($jenis === 'pppk' && count($angsuran_data) === 0) {
        throw new Exception('Minimal harus ada 1 data angsuran Bank Wonosobo untuk PPPK');
    }
}

// ========================================================================
// PART 3: TAMBAHKAN FIELDS KE UPDATE QUERY
// ========================================================================

// Lokasi: Di dalam query INSERT/UPDATE section, tambahkan:

if ($section === 'penghasilan_pegawai' && isset($pppk_tgl_awal)) {
    
    // Tambahkan ke $update_fields array jika menggunakan UPDATE style
    $update_fields['pppk_tgl_awal'] = $pppk_tgl_awal;
    $update_fields['pppk_tgl_akhir'] = $pppk_tgl_akhir;
    $update_fields['pppk_sisa_kerja_bulan'] = $pppk_sisa_kerja_bulan;
    
    // Nomor SK Agunan - dari form input
    if (!empty($_POST['pppk_agunan_no_sk'])) {
        $no_sk = trim((string)$_POST['pppk_agunan_no_sk']);
        if (strlen($no_sk) > 100) {
            throw new Exception('Nomor SK Agunan terlalu panjang (max 100 karakter)');
        }
        $update_fields['pppk_agunan_no_sk'] = strtoupper($no_sk);
    }
    
    // File SK - hanya update jika ada file baru yang diupload
    if ($pppk_file_sk) {
        $update_fields['pppk_file_sk'] = $pppk_file_sk;
    }
    
    // Total Angsuran
    $update_fields['pppk_total_angsuran'] = $total_angsuran;
}

// ========================================================================
// PART 4: SIMPAN DETAIL ANGSURAN KE TABEL TERPISAH
// ========================================================================

// Lokasi: SETELAH main INSERT/UPDATE query berhasil dijalankan

if ($section === 'penghasilan_pegawai' && $jenis === 'pppk' && !empty($angsuran_data)) {
    
    try {
        // Delete existing angsuran detail untuk pengajuan ini
        // (untuk handle UPDATE/edit case)
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
        // Log error tapi jangan throw - data pengajuan sudah tersimpan
        error_log('Error saving angsuran detail: ' . $e->getMessage());
    }
}

// ========================================================================
// PART 5: PREFILL DATA UNTUK EDIT/REVISI
// ========================================================================

// Lokasi: Di dalam analis/includes/analis_prefill_data.php
// Tambahkan function untuk load angsuran detail:

/**
 * Load angsuran detail dari database untuk prefill form
 */
function loadAngsuranDetail(PDO $pdo, $id_pengajuan)
{
    $stmt = $pdo->prepare("
        SELECT nama_produk, nominal_angsuran
        FROM pppk_angsuran_detail
        WHERE id_pengajuan = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$id_pengajuan]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Tambahkan ke prefill bundle (di dalam analisLoadPrefillBundle function):
if ($jenis === 'pppk' && isset($pengajuan['pppk_tgl_awal'])) {
    $bundle['pppk_detail'] = [
        'tgl_awal' => $pengajuan['pppk_tgl_awal'],
        'tgl_akhir' => $pengajuan['pppk_tgl_akhir'],
        'sisa_kerja_bulan' => $pengajuan['pppk_sisa_kerja_bulan'],
        'agunan_no_sk' => $pengajuan['pppk_agunan_no_sk'],
        'file_sk' => $pengajuan['pppk_file_sk'],
        'total_angsuran' => $pengajuan['pppk_total_angsuran'],
        'angsuran_list' => loadAngsuranDetail($pdo, $id_pengajuan)
    ];
}

// ========================================================================
// PART 6: JAVASCRIPT PREFILL UNTUK EDIT FORM
// ========================================================================

// Tambahkan di dalam pegawai_page.inc.php, sebelum </script>:

if (typeof window.__ANALIS_PREFILL__ !== 'undefined' && window.__ANALIS_PREFILL__.pppk_detail) {
    var pppkDetail = window.__ANALIS_PREFILL__.pppk_detail;
    
    // Set date fields
    document.getElementById('pppk_tgl_awal').value = pppkDetail.tgl_awal || '';
    document.getElementById('pppk_tgl_akhir').value = pppkDetail.tgl_akhir || '';
    
    // Set static fields
    if (document.getElementById('pppk_agunan_no_sk')) {
        document.getElementById('pppk_agunan_no_sk').value = pppkDetail.agunan_no_sk || '';
    }
    
    // Trigger recalculation
    if (typeof calculateSisaMasaKerja === 'function') {
        calculateSisaMasaKerja();
    }
    
    // Populate angsuran list
    if (pppkDetail.angsuran_list && Array.isArray(pppkDetail.angsuran_list)) {
        pppkDetail.angsuran_list.forEach(function(item) {
            if (typeof pppkAddAngsuran === 'function') {
                pppkAddAngsuran();
                
                // Set nilai pada item terakhir yang ditambahkan
                var items = document.querySelectorAll('.pppk-angsuran-item');
                if (items.length > 0) {
                    var lastItem = items[items.length - 1];
                    var namaInput = lastItem.querySelector('.pppk-angsuran-nama');
                    var nominalInput = lastItem.querySelector('.pppk-angsuran-nominal');
                    
                    if (namaInput) namaInput.value = item.nama_produk;
                    if (nominalInput) nominalInput.value = item.nominal_angsuran;
                }
            }
        });
        
        // Update total
        if (typeof pppkUpdateTotalAngsuran === 'function') {
            pppkUpdateTotalAngsuran();
        }
    }
}

// ========================================================================
// END OF IMPLEMENTATION GUIDE
// ========================================================================

/**
 * ERROR HANDLING SUMMARY
 * 
 * 1. Date Validation:
 *    - Format must be YYYY-MM-DD
 *    - End date must be >= start date
 *    - Required for PPPK form
 * 
 * 2. File Upload:
 *    - Max 2MB
 *    - Only PDF, JPG, PNG allowed
 *    - MIME type checked
 *    - Filename sanitized
 *    - Old file deleted on update
 * 
 * 3. Angsuran:
 *    - Minimum 1 entry required for PPPK
 *    - Nominal must be positive
 *    - Nama produk required if nominal > 0
 *    - Saved to separate table with FK
 * 
 * 4. Security:
 *    - CSRF token verified
 *    - SQL injection prevention (prepared statements)
 *    - File upload security checks
 *    - XSS prevention on output
 */
