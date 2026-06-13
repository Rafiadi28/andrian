<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && @realpath((string) $_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

/**
 * Patch skema idempotent — dijalankan tiap request setelah koneksi database.
 * Tidak bergantung pada functions.php (tanpa session).
 */
function bankKreditEnsureSchema(PDO $pdo)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $check = $pdo->query("SHOW TABLES LIKE 'pengajuan_kredit'");
        if (!$check || $check->rowCount() === 0) {
            return;
        }

        $col = $pdo->query("SHOW COLUMNS FROM pengajuan_kredit LIKE 'jenis_pekerjaan'")->rowCount();
        if ($col == 0) {
            $pdo->exec("ALTER TABLE pengajuan_kredit ADD COLUMN jenis_pekerjaan VARCHAR(32) NULL DEFAULT 'umum' COMMENT 'umum|pppk|perangkat_desa|kpr|kretamas|cashcolateral' AFTER pekerjaan");
        }

        // Add ID Nasabah, NPWP, NIB
        $colIdNasabah = $pdo->query("SHOW COLUMNS FROM pengajuan_kredit LIKE 'id_nasabah'")->rowCount();
        if ($colIdNasabah == 0) {
            $pdo->exec("ALTER TABLE pengajuan_kredit ADD COLUMN id_nasabah VARCHAR(50) NULL AFTER pekerjaan");
        }
        $colNpwp = $pdo->query("SHOW COLUMNS FROM pengajuan_kredit LIKE 'npwp'")->rowCount();
        if ($colNpwp == 0) {
            $pdo->exec("ALTER TABLE pengajuan_kredit ADD COLUMN npwp VARCHAR(50) NULL AFTER nik");
        }
        $colNib = $pdo->query("SHOW COLUMNS FROM pengajuan_kredit LIKE 'nib'")->rowCount();
        if ($colNib == 0) {
            $pdo->exec("ALTER TABLE pengajuan_kredit ADD COLUMN nib VARCHAR(50) NULL AFTER npwp");
        }

        // PPPK: Nomor SK untuk Agunan dan file SK
        $colPppkAgunanSk = $pdo->query("SHOW COLUMNS FROM pengajuan_kredit LIKE 'pppk_agunan_no_sk'")->rowCount();
        if ($colPppkAgunanSk == 0) {
            $pdo->exec("ALTER TABLE pengajuan_kredit ADD COLUMN pppk_agunan_no_sk VARCHAR(150) NULL COMMENT 'Nomor SK PPPK sebagai agunan' AFTER nib");
        }
        $colFileSk = $pdo->query("SHOW COLUMNS FROM pengajuan_kredit LIKE 'file_sk_pppk'")->rowCount();
        if ($colFileSk == 0) {
            $pdo->exec("ALTER TABLE pengajuan_kredit ADD COLUMN file_sk_pppk VARCHAR(255) NULL COMMENT 'File SK PPPK (agunan)' AFTER pppk_agunan_no_sk");
        }

        $colFileNeraca = $pdo->query("SHOW COLUMNS FROM pengajuan_kredit LIKE 'file_pendukung_neraca'")->rowCount();
        if ($colFileNeraca == 0) {
            $pdo->exec("ALTER TABLE pengajuan_kredit ADD COLUMN file_pendukung_neraca VARCHAR(255) NULL COMMENT 'File laporan neraca/rekening koran' AFTER file_sk_pppk");
        }

        // Add pendapatan_lain column (other income) for cash flow analysis
        $colPendapatanLain = $pdo->query("SHOW COLUMNS FROM pengajuan_kredit LIKE 'pendapatan_lain'")->rowCount();
        if ($colPendapatanLain == 0) {
            $pdo->exec("ALTER TABLE pengajuan_kredit ADD COLUMN pendapatan_lain DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Pendapatan lain-lain (tambahan ke omzet)' AFTER omset_per_bulan");
        }

        $desiredEnumStatuses = [
            'draft',
            'diajukan',
            'kepatuhan',
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
            'selesai',
        ];

        $row = $pdo->query("SHOW COLUMNS FROM pengajuan_kredit LIKE 'status_pengajuan'")->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['Type']) && stripos($row['Type'], 'enum(') === 0) {
            preg_match("/enum\((.*)\)/i", $row['Type'], $m);
            $vals = [];
            if (!empty($m[1])) {
                $parts = str_getcsv($m[1], ',', "'");
                foreach ($parts as $p) {
                    $vals[] = trim($p, "'");
                }
            }
            $added = false;
            foreach ($desiredEnumStatuses as $n) {
                if (!in_array($n, $vals, true)) {
                    $vals[] = $n;
                    $added = true;
                }
            }
            if ($added) {
                $newEnum = "ENUM('" . implode("','", $vals) . "')";
                $pdo->exec("ALTER TABLE pengajuan_kredit MODIFY COLUMN status_pengajuan {$newEnum} DEFAULT 'draft'");
            }
        }

        // Fix posisi_saat_ini truncation issues by converting ENUM to VARCHAR(50+)
        // Ensure column can hold all possible role values from hierarchy and future expansion
        $rowPosisi = $pdo->query("SHOW COLUMNS FROM pengajuan_kredit LIKE 'posisi_saat_ini'")->fetch(PDO::FETCH_ASSOC);
        if ($rowPosisi) {
            $posisiType = isset($rowPosisi['Type']) ? trim($rowPosisi['Type']) : '';
            // If ENUM or too small VARCHAR, convert to VARCHAR(100)
            if (empty($posisiType) || stripos($posisiType, 'enum') === 0 || !preg_match('/varchar\((\d+)\)/i', $posisiType, $m) || $m[1] < 100) {
                $pdo->exec("ALTER TABLE pengajuan_kredit MODIFY COLUMN posisi_saat_ini VARCHAR(100) NULL DEFAULT 'analis'");
            }
        }

        // Ensure approval_kredit.keputusan ENUM has all required decision values
        $desiredKeputusanValues = [
            'setuju',
            'tolak',
            'kembalikan',
            'revisi',
            'pending',
            'eskalasi_otomatis',
            'kirim_ulang',
            'revisi_diajukan',
        ];

        $rowKeputusan = $pdo->query("SHOW COLUMNS FROM approval_kredit LIKE 'keputusan'")->fetch(PDO::FETCH_ASSOC);
        if ($rowKeputusan && !empty($rowKeputusan['Type']) && stripos($rowKeputusan['Type'], 'enum(') === 0) {
            preg_match("/enum\((.*)\)/i", $rowKeputusan['Type'], $m);
            $keputusanVals = [];
            if (!empty($m[1])) {
                $parts = str_getcsv($m[1], ',', "'");
                foreach ($parts as $p) {
                    $keputusanVals[] = trim($p, "'");
                }
            }
            $keputusanAdded = false;
            foreach ($desiredKeputusanValues as $kv) {
                if (!in_array($kv, $keputusanVals, true)) {
                    $keputusanVals[] = $kv;
                    $keputusanAdded = true;
                }
            }
            if ($keputusanAdded) {
                $newKeputusanEnum = "ENUM('" . implode("','", $keputusanVals) . "')";
                $pdo->exec("ALTER TABLE approval_kredit MODIFY COLUMN keputusan {$newKeputusanEnum} NOT NULL");
            }
        }

        // Ensure approval_kredit.level_approval ENUM matches the current code hierarchy
        $desiredLevelValues = [
            'analis',
            'kepatuhan',
            'kasubag_analis',
            'kabag_kredit',
            'kadiv_bisnis',
            'direktur_utama',
        ];

        $rowLevel = $pdo->query("SHOW COLUMNS FROM approval_kredit LIKE 'level_approval'")->fetch(PDO::FETCH_ASSOC);
        if ($rowLevel && !empty($rowLevel['Type']) && stripos($rowLevel['Type'], 'enum(') === 0) {
            preg_match("/enum\((.*)\)/i", $rowLevel['Type'], $m);
            $levelVals = [];
            if (!empty($m[1])) {
                $parts = str_getcsv($m[1], ',', "'");
                foreach ($parts as $p) {
                    $levelVals[] = trim($p, "'");
                }
            }
            
            // Check if any desired value is missing
            $levelNeedUpdate = false;
            foreach ($desiredLevelValues as $lv) {
                if (!in_array($lv, $levelVals, true)) {
                    $levelNeedUpdate = true;
                    break;
                }
            }

            if ($levelNeedUpdate) {
                // Step A: Temporarily alter ENUM to support all (old and new) values to prevent truncation during update
                $allEnumValues = array_unique(array_merge($levelVals, $desiredLevelValues, ['kabag_analis', 'kadiv_kredit', 'direksi']));
                $tempEnum = "ENUM('" . implode("','", $allEnumValues) . "')";
                $pdo->exec("ALTER TABLE approval_kredit MODIFY COLUMN level_approval {$tempEnum} NOT NULL");

                // Step B: Migrate data in users table
                $pdo->exec("UPDATE users SET role = 'kasubag_analis' WHERE role = 'kabag_analis'");
                $pdo->exec("UPDATE users SET role = 'kadiv_bisnis' WHERE role = 'kadiv_kredit'");
                $pdo->exec("UPDATE users SET role = 'direktur_utama' WHERE role = 'direksi'");

                // Step C: Migrate data in approval_kredit table
                $pdo->exec("UPDATE approval_kredit SET level_approval = 'kasubag_analis' WHERE level_approval = 'kabag_analis'");
                $pdo->exec("UPDATE approval_kredit SET level_approval = 'kadiv_bisnis' WHERE level_approval = 'kadiv_kredit'");
                $pdo->exec("UPDATE approval_kredit SET level_approval = 'direktur_utama' WHERE level_approval = 'direksi'");

                // Step D: Migrate data in pengajuan_kredit positions (in case any application is currently in progress)
                $pdo->exec("UPDATE pengajuan_kredit SET posisi_saat_ini = 'kasubag_analis' WHERE posisi_saat_ini = 'kabag_analis'");
                $pdo->exec("UPDATE pengajuan_kredit SET posisi_saat_ini = 'kadiv_bisnis' WHERE posisi_saat_ini = 'kadiv_kredit'");
                $pdo->exec("UPDATE pengajuan_kredit SET posisi_saat_ini = 'direktur_utama' WHERE posisi_saat_ini = 'direksi'");

                // Step E: Set to final ENUM with only desired values (clean)
                $newLevelEnum = "ENUM('" . implode("','", $desiredLevelValues) . "')";
                $pdo->exec("ALTER TABLE approval_kredit MODIFY COLUMN level_approval {$newLevelEnum} NOT NULL");
            }
        }


        // Ensure jaminan tables exist
        $tableExistsJaminanTanah = $pdo->query("SHOW TABLES LIKE 'jaminan_tanah_bangunan'")->rowCount() > 0;
        if (!$tableExistsJaminanTanah) {
            $pdo->exec("
                CREATE TABLE jaminan_tanah_bangunan (
                    id_jaminan INT AUTO_INCREMENT PRIMARY KEY,
                    id_pengajuan INT NOT NULL,
                    alamat_agunan TEXT,
                    jenis_surat VARCHAR(50),
                    masa_covernote DATE,
                    nomor_surat VARCHAR(100),
                    atas_nama VARCHAR(100),
                    kategori_agunan VARCHAR(50),
                    luas_tanah DECIMAL(15,2),
                    luas_tanah_sppt DECIMAL(15,2),
                    harga_tanah_sppt DECIMAL(15,2),
                    nilai_wajar_sppt DECIMAL(15,2),
                    nilai_taksasi_sppt DECIMAL(15,2),
                    nilai_likuidasi_sppt DECIMAL(15,2),
                    harga_tanah_pasar DECIMAL(15,2),
                    luas_bangunan DECIMAL(15,2),
                    luas_bangunan_2 DECIMAL(15,2),
                    harga_bangunan_m2 DECIMAL(15,2),
                    nilai_pasar DECIMAL(15,2),
                    nilai_taksasi DECIMAL(15,2),
                    nilai_likuidasi DECIMAL(15,2),
                    foto_rumah VARCHAR(255),
                    file_jaminan VARCHAR(255),
                    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan)
                )
            ");
        }

        $tableExistsJaminanKendaraan = $pdo->query("SHOW TABLES LIKE 'jaminan_kendaraan'")->rowCount() > 0;
        if (!$tableExistsJaminanKendaraan) {
            $pdo->exec("
                CREATE TABLE jaminan_kendaraan (
                    id_jaminan INT AUTO_INCREMENT PRIMARY KEY,
                    id_pengajuan INT NOT NULL,
                    merk VARCHAR(100),
                    tipe VARCHAR(100),
                    tahun_pembuatan INT,
                    no_polisi VARCHAR(50),
                    no_rangka VARCHAR(100),
                    no_mesin VARCHAR(100),
                    nama_pemilik VARCHAR(100),
                    nilai_pasar DECIMAL(15,2),
                    nilai_taksasi DECIMAL(15,2),
                    nilai_likuidasi DECIMAL(15,2),
                    foto_rumah VARCHAR(255),
                    file_jaminan VARCHAR(255),
                    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan)
                )
            ");
        }

        $tableExistsJaminanEmas = $pdo->query("SHOW TABLES LIKE 'jaminan_emas'")->rowCount() > 0;
        if (!$tableExistsJaminanEmas) {
            $pdo->exec("
                CREATE TABLE jaminan_emas (
                    id_jaminan INT AUTO_INCREMENT PRIMARY KEY,
                    id_pengajuan INT NOT NULL,
                    harga_per_gram DECIMAL(15,2),
                    berat DECIMAL(15,6),
                    nilai_pasar DECIMAL(15,2),
                    nilai_taksasi DECIMAL(15,2),
                    nilai_likuidasi DECIMAL(15,2),
                    file_jaminan VARCHAR(255),
                    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan)
                )
            ");
        } else {
            $colTaksasiEmas = $pdo->query("SHOW COLUMNS FROM jaminan_emas LIKE 'nilai_taksasi'")->rowCount();
            if ($colTaksasiEmas == 0) {
                $pdo->exec("ALTER TABLE jaminan_emas ADD COLUMN nilai_taksasi DECIMAL(15,2) DEFAULT '0.00' AFTER nilai_pasar");
            }
        }

        // Add manual valuation columns to jaminan tables (idempotent)
        try {
            $colTipeValuasi = $pdo->query("SHOW COLUMNS FROM jaminan_tanah_bangunan LIKE 'tipe_valuasi'")->rowCount();
            if ($colTipeValuasi == 0) {
                $pdo->exec("ALTER TABLE jaminan_tanah_bangunan ADD COLUMN tipe_valuasi ENUM('otomatis','manual') DEFAULT 'otomatis' COMMENT 'Tipe valuasi: otomatis atau manual override' AFTER nilai_taksasi");
                $pdo->exec("ALTER TABLE jaminan_tanah_bangunan ADD COLUMN nilai_taksasi_manual DECIMAL(15,2) DEFAULT NULL COMMENT 'Nilai taksasi manual jika dipilih tipe_valuasi=manual' AFTER tipe_valuasi");
            }
        } catch (Exception $e) {}

        try {
            $colTipeValuasiKendaraan = $pdo->query("SHOW COLUMNS FROM jaminan_kendaraan LIKE 'tipe_valuasi'")->rowCount();
            if ($colTipeValuasiKendaraan == 0) {
                $pdo->exec("ALTER TABLE jaminan_kendaraan ADD COLUMN tipe_valuasi ENUM('otomatis','manual') DEFAULT 'otomatis' COMMENT 'Tipe valuasi: otomatis atau manual override' AFTER nilai_taksasi");
                $pdo->exec("ALTER TABLE jaminan_kendaraan ADD COLUMN nilai_taksasi_manual DECIMAL(15,2) DEFAULT NULL COMMENT 'Nilai taksasi manual jika dipilih tipe_valuasi=manual' AFTER tipe_valuasi");
            }
        } catch (Exception $e) {}

        // Add STNK columns for kendaraan (BPKB)
        try {
            $colNoStnk = $pdo->query("SHOW COLUMNS FROM jaminan_kendaraan LIKE 'no_stnk'")->rowCount();
            if ($colNoStnk == 0) {
                $pdo->exec("ALTER TABLE jaminan_kendaraan ADD COLUMN no_stnk VARCHAR(50) DEFAULT NULL COMMENT 'Nomor STNK (untuk BPKB)' AFTER nilai_taksasi_manual");
                $pdo->exec("ALTER TABLE jaminan_kendaraan ADD COLUMN masa_berlaku_stnk DATE DEFAULT NULL COMMENT 'Masa berlaku STNK (untuk BPKB)' AFTER no_stnk");
            }
        } catch (Exception $e) {}

        try {
            $colTipeValuasiEmas = $pdo->query("SHOW COLUMNS FROM jaminan_emas LIKE 'tipe_valuasi'")->rowCount();
            if ($colTipeValuasiEmas == 0) {
                $pdo->exec("ALTER TABLE jaminan_emas ADD COLUMN tipe_valuasi ENUM('otomatis','manual') DEFAULT 'otomatis' COMMENT 'Tipe valuasi: otomatis atau manual override' AFTER nilai_taksasi");
                $pdo->exec("ALTER TABLE jaminan_emas ADD COLUMN nilai_taksasi_manual DECIMAL(15,2) DEFAULT NULL COMMENT 'Nilai taksasi manual jika dipilih tipe_valuasi=manual' AFTER tipe_valuasi");
            }
        } catch (Exception $e) {}

        // Add Neraca Sesudah Kredit columns (idempotent)
        try {
            $colAktivaSesudah = $pdo->query("SHOW COLUMNS FROM analisa_neraca LIKE 'aktiva_kas_sesudah'")->rowCount();
            if ($colAktivaSesudah == 0) {
                $pdo->exec("ALTER TABLE analisa_neraca ADD COLUMN aktiva_kas_sesudah DECIMAL(15,2) DEFAULT NULL COMMENT 'Kas setelah kredit (manual)' AFTER total_pasiva");
                $pdo->exec("ALTER TABLE analisa_neraca ADD COLUMN aktiva_tabungan_sesudah DECIMAL(15,2) DEFAULT NULL COMMENT 'Tabungan setelah kredit (manual)' AFTER aktiva_kas_sesudah");
                $pdo->exec("ALTER TABLE analisa_neraca ADD COLUMN aktiva_tanah_sesudah DECIMAL(15,2) DEFAULT NULL COMMENT 'Tanah setelah kredit (manual)' AFTER aktiva_tabungan_sesudah");
                $pdo->exec("ALTER TABLE analisa_neraca ADD COLUMN aktiva_kendaraan_sesudah DECIMAL(15,2) DEFAULT NULL COMMENT 'Kendaraan setelah kredit (manual)' AFTER aktiva_tanah_sesudah");
                $pdo->exec("ALTER TABLE analisa_neraca ADD COLUMN aktiva_stok_sesudah DECIMAL(15,2) DEFAULT NULL COMMENT 'Stok setelah kredit (manual)' AFTER aktiva_kendaraan_sesudah");
                $pdo->exec("ALTER TABLE analisa_neraca ADD COLUMN aktiva_lainnya_sesudah DECIMAL(15,2) DEFAULT NULL COMMENT 'Lainnya setelah kredit (manual)' AFTER aktiva_stok_sesudah");
                $pdo->exec("ALTER TABLE analisa_neraca ADD COLUMN pasiva_hutang_bank_sesudah DECIMAL(15,2) DEFAULT NULL COMMENT 'Pajak/PBB setelah kredit (manual)' AFTER aktiva_lainnya_sesudah");
                $pdo->exec("ALTER TABLE analisa_neraca ADD COLUMN pasiva_hutang_lain_sesudah DECIMAL(15,2) DEFAULT NULL COMMENT 'Pinjaman lain setelah kredit (manual)' AFTER pasiva_hutang_bank_sesudah");
                $pdo->exec("ALTER TABLE analisa_neraca ADD COLUMN pasiva_pinjaman_bawon_sesudah DECIMAL(15,2) DEFAULT NULL COMMENT 'Pinjaman bawon setelah kredit (auto dengan plafon baru)' AFTER pasiva_hutang_lain_sesudah");
                $pdo->exec("ALTER TABLE analisa_neraca ADD COLUMN pasiva_modal_sesudah DECIMAL(15,2) DEFAULT NULL COMMENT 'Modal setelah kredit (otomatis dari balance)' AFTER pasiva_pinjaman_bawon_sesudah");
                $pdo->exec("ALTER TABLE analisa_neraca ADD COLUMN total_aktiva_sesudah DECIMAL(15,2) DEFAULT NULL COMMENT 'Total aktiva setelah kredit' AFTER pasiva_modal_sesudah");
                $pdo->exec("ALTER TABLE analisa_neraca ADD COLUMN total_pasiva_sesudah DECIMAL(15,2) DEFAULT NULL COMMENT 'Total pasiva setelah kredit' AFTER total_aktiva_sesudah");
            }
        } catch (Exception $e) {}
        $tableExistsJaminanCash = $pdo->query("SHOW TABLES LIKE 'jaminan_cashcolateral'")->rowCount() > 0;
        if (!$tableExistsJaminanCash) {
            $pdo->exec("
                CREATE TABLE jaminan_cashcolateral (
                    id_jaminan INT AUTO_INCREMENT PRIMARY KEY,
                    id_pengajuan INT NOT NULL,
                    jenis_agunan ENUM('bilyet_deposito','tabungan') NOT NULL DEFAULT 'bilyet_deposito',
                    nomor_bilyet VARCHAR(100) NULL COMMENT 'Nomor Bilyet Deposito',
                    nomor_rekening VARCHAR(100) NULL COMMENT 'Nomor Rekening Tabungan',
                    atas_nama VARCHAR(100) NULL,
                    nilai_nominal DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Nilai nominal deposito/saldo tabungan',
                    nilai_taksasi DECIMAL(15,2) DEFAULT 0.00 COMMENT '95% dari nilai nominal',
                    jatuh_tempo DATE NULL COMMENT 'Tanggal jatuh tempo (khusus deposito)',
                    keterangan VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_jcc_id_pengajuan (id_pengajuan),
                    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE
                )
            ");
        }

        // Create agunan_foto table for multiple collateral photos (idempotent)
        $tableExistsAgunanFoto = $pdo->query("SHOW TABLES LIKE 'agunan_foto'")->rowCount() > 0;
        if (!$tableExistsAgunanFoto) {
            $pdo->exec("
                CREATE TABLE agunan_foto (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_jaminan INT NOT NULL,
                    id_pengajuan INT NOT NULL,
                    tipe_jaminan VARCHAR(50) NOT NULL COMMENT 'tanah_bangunan|kendaraan|emas|cashcolateral',
                    nama_file VARCHAR(255) NOT NULL COMMENT 'Nama file di storage',
                    ukuran INT DEFAULT 0 COMMENT 'Ukuran file dalam bytes',
                    tipe_file VARCHAR(50) DEFAULT NULL COMMENT 'jpg|jpeg|png',
                    keterangan VARCHAR(255) DEFAULT NULL COMMENT 'Deskripsi foto (opsional)',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_id_jaminan (id_jaminan),
                    KEY idx_id_pengajuan (id_pengajuan),
                    KEY idx_tipe_jaminan (tipe_jaminan),
                    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE
                )
            ");
        }

        // Ensure analisa_neraca table exists
        $tableExistsAnaliseNeraca = $pdo->query("SHOW TABLES LIKE 'analisa_neraca'")->rowCount() > 0;
        if (!$tableExistsAnaliseNeraca) {
            $pdo->exec("
                CREATE TABLE analisa_neraca (
                    id_neraca INT AUTO_INCREMENT PRIMARY KEY,
                    id_pengajuan INT NOT NULL,
                    aktiva_kas DECIMAL(15,2) DEFAULT '0.00',
                    aktiva_tabungan DECIMAL(15,2) DEFAULT '0.00',
                    aktiva_tanah DECIMAL(15,2) DEFAULT '0.00',
                    aktiva_kendaraan DECIMAL(15,2) DEFAULT '0.00',
                    aktiva_stok DECIMAL(15,2) DEFAULT '0.00',
                    aktiva_lainnya DECIMAL(15,2) DEFAULT '0.00',
                    pasiva_hutang_bank DECIMAL(15,2) DEFAULT '0.00',
                    pasiva_hutang_lain DECIMAL(15,2) DEFAULT '0.00',
                    pasiva_modal DECIMAL(15,2) DEFAULT '0.00',
                    total_aktiva DECIMAL(15,2) DEFAULT '0.00',
                    total_pasiva DECIMAL(15,2) DEFAULT '0.00',
                    PRIMARY KEY (id_neraca),
                    KEY idx_id_pengajuan (id_pengajuan),
                    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE
                )
            ");
        }

        // Ensure analisa_5c table exists
        $tableExistsAnalisa5c = $pdo->query("SHOW TABLES LIKE 'analisa_5c'")->rowCount() > 0;
        if (!$tableExistsAnalisa5c) {
            $pdo->exec("
                CREATE TABLE analisa_5c (
                    id_5c INT AUTO_INCREMENT PRIMARY KEY,
                    id_pengajuan INT NOT NULL,
                    character_score INT DEFAULT '0',
                    capacity_score INT DEFAULT '0',
                    capital_score INT DEFAULT '0',
                    collateral_score INT DEFAULT '0',
                    condition_score INT DEFAULT '0',
                    constraint_score INT DEFAULT '0',
                    total_score DECIMAL(5,2) DEFAULT '0.00',
                    catatan_5c TEXT,
                    rekomendasi VARCHAR(50),
                    catatan_character TEXT,
                    catatan_capacity TEXT,
                    catatan_capital TEXT,
                    catatan_collateral TEXT,
                    catatan_condition TEXT,
                    catatan_constraint_risk TEXT,
                    PRIMARY KEY (id_5c),
                    KEY idx_id_pengajuan (id_pengajuan),
                    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE
                )
            ");
        }

        // Ensure assessment_kepatuhan table exists
        $tableExistsAssessmentKepatuhan = $pdo->query("SHOW TABLES LIKE 'assessment_kepatuhan'")->rowCount() > 0;
        if (!$tableExistsAssessmentKepatuhan) {
            $pdo->exec("
                CREATE TABLE assessment_kepatuhan (
                    id_assessment INT AUTO_INCREMENT PRIMARY KEY,
                    id_pengajuan INT NOT NULL,
                    id_user INT NOT NULL,
                    tanggal_assessment DATE NOT NULL,
                    checklist_data JSON,
                    fasilitas_existing JSON,
                    catatan_existing JSON,
                    hasil_kepatuhan VARCHAR(20),
                    catatan_hasil TEXT,
                    kesimpulan TEXT,
                    rekomendasi TEXT,
                    marketing VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_assessment),
                    KEY idx_assessment_pengajuan (id_pengajuan),
                    KEY idx_assessment_user_created (id_user, created_at),
                    KEY idx_assessment_created_date (created_at),
                    CONSTRAINT fk_assessment_pengajuan FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_assessment_user FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE RESTRICT ON UPDATE CASCADE
                )
            ");
        }

        // Kolom hasil_kepatuhan untuk DB yang dibuat sebelum fitur Hasil Kepatuhan
        if ($pdo->query("SHOW TABLES LIKE 'assessment_kepatuhan'")->rowCount() > 0) {
            if ($pdo->query("SHOW COLUMNS FROM assessment_kepatuhan LIKE 'hasil_kepatuhan'")->rowCount() === 0) {
                $pdo->exec("ALTER TABLE assessment_kepatuhan ADD COLUMN hasil_kepatuhan VARCHAR(20) NULL AFTER catatan_existing");
            }
            if ($pdo->query("SHOW COLUMNS FROM assessment_kepatuhan LIKE 'catatan_hasil'")->rowCount() === 0) {
                $pdo->exec("ALTER TABLE assessment_kepatuhan ADD COLUMN catatan_hasil TEXT NULL AFTER hasil_kepatuhan");
            }
        }

        // Ensure audit_log table exists
        $tableExistsAuditLog = $pdo->query("SHOW TABLES LIKE 'audit_log'")->rowCount() > 0;
        if (!$tableExistsAuditLog) {
            $pdo->exec("
                CREATE TABLE audit_log (
                    id_log INT AUTO_INCREMENT PRIMARY KEY,
                    id_user INT,
                    aktivitas TEXT NOT NULL,
                    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_audit_user_waktu (id_user, waktu),
                    FOREIGN KEY (id_user) REFERENCES users(id_user)
                )
            ");
        }

        // Ensure notifications table exists
        $tableExistsNotifications = $pdo->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0;
        if (!$tableExistsNotifications) {
            $pdo->exec("
                CREATE TABLE notifications (
                    id_notification INT AUTO_INCREMENT PRIMARY KEY,
                    id_user INT NOT NULL,
                    id_pengajuan INT NOT NULL,
                    tipe_notifikasi VARCHAR(50) NOT NULL COMMENT 'submitted, approved, rejected, revised, auto_routed',
                    judul_notifikasi VARCHAR(255) NOT NULL,
                    pesan_notifikasi TEXT,
                    role_source VARCHAR(50) COMMENT 'Role yang trigger notifikasi (analis, kepatuhan, kasubag_analis, dll)',
                    role_target VARCHAR(50) COMMENT 'Role yang menerima notifikasi',
                    is_read TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    read_at TIMESTAMP NULL,
                    KEY idx_notif_user_read (id_user, is_read),
                    KEY idx_notif_tipe_created (tipe_notifikasi, created_at),
                    KEY idx_notif_pengajuan (id_pengajuan),
                    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
                    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE
                )
            ");
        }

        // Ensure angsuran_bank_lain table exists
        $tableExistsAngsuranBankLain = $pdo->query("SHOW TABLES LIKE 'angsuran_bank_lain'")->rowCount() > 0;
        if (!$tableExistsAngsuranBankLain) {
            $pdo->exec("
                CREATE TABLE angsuran_bank_lain (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_pengajuan INT NOT NULL,
                    nama_bank VARCHAR(100) NOT NULL,
                    plafond DECIMAL(15,2) DEFAULT 0.00,
                    tenor INT DEFAULT 0,
                    bunga DECIMAL(5,2) DEFAULT 0.00,
                    jenis_bunga VARCHAR(20) DEFAULT 'Flat',
                    baki_debet DECIMAL(15,2) DEFAULT 0.00,
                    angsuran DECIMAL(15,2) DEFAULT 0.00,
                    KEY idx_abl_id_pengajuan (id_pengajuan),
                    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE
                )
            ");
        }

        // Master Pejabat table for signature box data
        $tableExistsMasterPejabat = $pdo->query("SHOW TABLES LIKE 'master_pejabat'")->rowCount() > 0;
        if (!$tableExistsMasterPejabat) {
            $pdo->exec("
                CREATE TABLE master_pejabat (
                    id_pejabat INT AUTO_INCREMENT PRIMARY KEY,
                    role VARCHAR(100) NOT NULL UNIQUE COMMENT 'Role: analis, kasubag_analis, kabag_kredit, kadiv_bisnis, direktur_utama',
                    nama VARCHAR(150) NOT NULL COMMENT 'Nama lengkap pejabat',
                    jabatan VARCHAR(150) NOT NULL COMMENT 'Jabatan resmi',
                    tanda_tangan VARCHAR(255) NULL COMMENT 'Path ke file tanda tangan (JPG/PNG)',
                    stempel VARCHAR(255) NULL COMMENT 'Path ke file stempel/cap (JPG/PNG)',
                    status ENUM('aktif','nonaktif') DEFAULT 'aktif',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_mp_role (role),
                    INDEX idx_mp_status (status)
                )
            ");

            // Seed default master pejabat data
            $default_pejabat = [
                ['role' => 'analis', 'jabatan' => 'Analis Kredit'],
                ['role' => 'kasubag_analis', 'jabatan' => 'Kepala Subbagian Analis'],
                ['role' => 'kabag_kredit', 'jabatan' => 'Kepala Bagian Kredit'],
                ['role' => 'kadiv_bisnis', 'jabatan' => 'Kepala Divisi Bisnis'],
                ['role' => 'direktur_utama', 'jabatan' => 'Direktur Utama']
            ];

            foreach ($default_pejabat as $pj) {
                $checkStmt = $pdo->prepare("SELECT id_pejabat FROM master_pejabat WHERE role = ?");
                $checkStmt->execute([$pj['role']]);
                if ($checkStmt->rowCount() === 0) {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO master_pejabat (role, nama, jabatan, status)
                        VALUES (?, ?, ?, 'aktif')
                    ");
                    $insertStmt->execute([$pj['role'], '[Belum Ditentukan]', $pj['jabatan']]);
                }
            }
        }

        bankKreditEnsureIndexes($pdo);
        bankKreditEnsureForeignKeys($pdo);
    } catch (Throwable $e) {
        error_log('bankKreditEnsureSchema: ' . $e->getMessage());
    }
}

/**
 * Ensure all critical foreign keys exist on existing tables
 * Runs idempotently - only adds constraints that don't exist
 */
function bankKreditEnsureForeignKeys(PDO $pdo)
{
    try {
        $schema = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$schema) {
            return;
        }

        /**
         * Get existing foreign keys for a table
         */
        $getFKs = function (string $table) use ($pdo, $schema) {
            $stmt = $pdo->prepare('
                SELECT CONSTRAINT_NAME, COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
            ');
            $stmt->execute([$schema, $table]);
            $result = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $result[$row['COLUMN_NAME']][] = $row['CONSTRAINT_NAME'];
            }
            return $result;
        };

        /**
         * Drop foreign key safely
         */
        $dropFK = function (string $table, string $fkName) use ($pdo) {
            try {
                $qt = '`' . str_replace('`', '``', $table) . '`';
                $qfk = '`' . str_replace('`', '``', $fkName) . '`';
                $pdo->exec("ALTER TABLE {$qt} DROP FOREIGN KEY {$qfk}");
            } catch (Throwable $e) {
                error_log("Could not drop FK {$fkName}: " . $e->getMessage());
            }
        };

        /**
         * Ensure FK constraint exists
         */
        $ensureFK = function (string $table, string $column, string $refTable, string $refColumn, string $fkName) use ($pdo, $getFKs, $dropFK) {
            $existingFKs = $getFKs($table);
            if (isset($existingFKs[$column])) {
                // FK already exists on this column
                return;
            }

            try {
                $qt = '`' . str_replace('`', '``', $table) . '`';
                $qc = '`' . str_replace('`', '``', $column) . '`';
                $qrt = '`' . str_replace('`', '``', $refTable) . '`';
                $qrc = '`' . str_replace('`', '``', $refColumn) . '`';
                $qfk = '`' . str_replace('`', '``', $fkName) . '`';

                $pdo->exec("ALTER TABLE {$qt} ADD CONSTRAINT {$qfk} FOREIGN KEY ({$qc}) REFERENCES {$qrt}({$qrc}) ON DELETE RESTRICT ON UPDATE CASCADE");
            } catch (Throwable $e) {
                error_log("bankKreditEnsureForeignKeys {$table}.{$column}: " . $e->getMessage());
            }
        };

        // ==========================================
        // ENSURE CRITICAL FOREIGN KEYS
        // ==========================================

        // 1. assessment_kepatuhan.id_user → users.id_user
        if ($pdo->query("SHOW TABLES LIKE 'assessment_kepatuhan'")->rowCount() > 0) {
            $ensureFK('assessment_kepatuhan', 'id_user', 'users', 'id_user', 'fk_assessment_user');
            // 2. assessment_kepatuhan.id_pengajuan → pengajuan_kredit.id_pengajuan (ensure it exists)
            $ensureFK('assessment_kepatuhan', 'id_pengajuan', 'pengajuan_kredit', 'id_pengajuan', 'fk_assessment_pengajuan');
        }

        // 3. pengajuan_kredit.input_by → users.id_user
        if ($pdo->query("SHOW TABLES LIKE 'pengajuan_kredit'")->rowCount() > 0) {
            $ensureFK('pengajuan_kredit', 'input_by', 'users', 'id_user', 'fk_pengajuan_input_by');
        }

        // 4. approval_kredit foreign keys
        if ($pdo->query("SHOW TABLES LIKE 'approval_kredit'")->rowCount() > 0) {
            $ensureFK('approval_kredit', 'id_pengajuan', 'pengajuan_kredit', 'id_pengajuan', 'fk_approval_pengajuan');
            $ensureFK('approval_kredit', 'id_user', 'users', 'id_user', 'fk_approval_user');
        }

        // 5. Collateral jaminan tables
        foreach (['jaminan_tanah_bangunan', 'jaminan_kendaraan', 'jaminan_emas'] as $jaminanTable) {
            if ($pdo->query("SHOW TABLES LIKE '{$jaminanTable}'")->rowCount() > 0) {
                $ensureFK($jaminanTable, 'id_pengajuan', 'pengajuan_kredit', 'id_pengajuan', 'fk_' . $jaminanTable . '_pengajuan');
            }
        }

        // 6. Analysis tables
        if ($pdo->query("SHOW TABLES LIKE 'analisa_neraca'")->rowCount() > 0) {
            $ensureFK('analisa_neraca', 'id_pengajuan', 'pengajuan_kredit', 'id_pengajuan', 'fk_analisa_neraca_pengajuan');
        }

        if ($pdo->query("SHOW TABLES LIKE 'analisa_5c'")->rowCount() > 0) {
            $ensureFK('analisa_5c', 'id_pengajuan', 'pengajuan_kredit', 'id_pengajuan', 'fk_analisa_5c_pengajuan');
        }

        // 7. Audit log
        if ($pdo->query("SHOW TABLES LIKE 'audit_log'")->rowCount() > 0) {
            $ensureFK('audit_log', 'id_user', 'users', 'id_user', 'fk_audit_log_user');
        }

    } catch (Throwable $e) {
        error_log('bankKreditEnsureForeignKeys: ' . $e->getMessage());
    }
}

/**
 * Indeks untuk query rutin: inbox approval, dashboard analis, filter status, lookup user aktif per role.
 * Satu kali konsultasi information_schema per tabel per request (ringan).
 */
function bankKreditEnsureIndexes(PDO $pdo)
{
    $schema = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!$schema) {
        return;
    }

    $tableExists = function (string $table) use ($pdo) {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    };

    $indexNameCache = [];
    $getIndexSet = function (string $table) use ($pdo, $schema, &$indexNameCache) {
        if (!isset($indexNameCache[$table])) {
            $stmt = $pdo->prepare(
                'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
            );
            $stmt->execute([$schema, $table]);
            $indexNameCache[$table] = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        return $indexNameCache[$table];
    };

    $ensure = function (string $table, string $indexName, string $ddl) use ($getIndexSet, $pdo) {
        if (isset($getIndexSet($table)[$indexName])) {
            return;
        }
        try {
            $pdo->exec($ddl);
        } catch (Throwable $e) {
            error_log("bankKreditEnsureIndexes {$table}.{$indexName}: " . $e->getMessage());
        }
    };

    $t = 'pengajuan_kredit';
    if ($tableExists($t)) {
        $ensure(
            $t,
            'idx_pk_posisi_status_tgl',
            "CREATE INDEX idx_pk_posisi_status_tgl ON {$t} (posisi_saat_ini, status_pengajuan, tanggal_pengajuan)"
        );
        $ensure(
            $t,
            'idx_pk_input_tgl',
            "CREATE INDEX idx_pk_input_tgl ON {$t} (input_by, tanggal_pengajuan)"
        );
        $ensure(
            $t,
            'idx_pk_status_tgl',
            "CREATE INDEX idx_pk_status_tgl ON {$t} (status_pengajuan, tanggal_pengajuan)"
        );
    }

    $t = 'approval_kredit';
    if ($tableExists($t)) {
        // id_pengajuan biasanya sudah terindeks dari FK — tambahkan indeks gabungan untuk dashboard
        $ensure(
            $t,
            'idx_ak_user_level',
            "CREATE INDEX idx_ak_user_level ON {$t} (id_user, level_approval)"
        );
    }

    $t = 'users';
    if ($tableExists($t)) {
        $ensure(
            $t,
            'idx_users_role_jabatan',
            "CREATE INDEX idx_users_role_jabatan ON {$t} (role, status_jabatan)"
        );
    }

    foreach (['jaminan_tanah_bangunan', 'jaminan_kendaraan', 'jaminan_emas'] as $t) {
        if ($tableExists($t)) {
            $ensure(
                $t,
                'idx_jm_id_pengajuan',
                "CREATE INDEX idx_jm_id_pengajuan ON {$t} (id_pengajuan)"
            );
        }
    }

    $t = 'audit_log';
    if ($tableExists($t)) {
        $ensure(
            $t,
            'idx_audit_user_waktu',
            "CREATE INDEX idx_audit_user_waktu ON {$t} (id_user, waktu)"
        );
    }
}
