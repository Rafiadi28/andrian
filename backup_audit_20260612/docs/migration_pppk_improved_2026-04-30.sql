-- ========================================================================
-- DATABASE MIGRATION - FORM PPPK IMPROVEMENT
-- ========================================================================
-- Script untuk menambahkan kolom baru dan tabel untuk mendukung form PPPK yang sudah diperbaiki
-- ========================================================================

-- ========================================================================
-- STEP 1: TAMBAH KOLOM BARU KE TABEL pengajuan_kredit
-- ========================================================================

-- Cek apakah kolom sudah ada sebelum menambahkan
ALTER TABLE pengajuan_kredit ADD COLUMN IF NOT EXISTS pppk_tgl_awal DATE COMMENT 'Tanggal awal perjanjian PPPK' AFTER pppk_no_sk;
ALTER TABLE pengajuan_kredit ADD COLUMN IF NOT EXISTS pppk_tgl_akhir DATE COMMENT 'Tanggal akhir perjanjian PPPK' AFTER pppk_tgl_awal;
ALTER TABLE pengajuan_kredit ADD COLUMN IF NOT EXISTS pppk_sisa_kerja_bulan INT DEFAULT 0 COMMENT 'Sisa masa kerja dalam bulan' AFTER pppk_tgl_akhir;
ALTER TABLE pengajuan_kredit ADD COLUMN IF NOT EXISTS pppk_agunan_no_sk VARCHAR(100) COMMENT 'Nomor SK untuk agunan' AFTER pppk_gaji;
ALTER TABLE pengajuan_kredit ADD COLUMN IF NOT EXISTS pppk_file_sk VARCHAR(255) COMMENT 'Nama file SK yang diupload' AFTER pppk_agunan_no_sk;
ALTER TABLE pengajuan_kredit ADD COLUMN IF NOT EXISTS pppk_total_angsuran DECIMAL(15,2) DEFAULT 0 COMMENT 'Total angsuran di Bank Wonosobo' AFTER pppk_file_sk;

-- ========================================================================
-- STEP 2: BUAT TABEL BARU untuk detail angsuran dinamis
-- ========================================================================

CREATE TABLE IF NOT EXISTS pppk_angsuran_detail (
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'ID auto-increment',
    id_pengajuan INT NOT NULL COMMENT 'Reference ke tabel pengajuan_kredit',
    nama_produk VARCHAR(100) NOT NULL COMMENT 'Nama produk/jenis kredit (cth: KMK, Kredit Konsumtif)',
    nominal_angsuran DECIMAL(15,2) NOT NULL COMMENT 'Nominal angsuran per bulan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu entry dibuat',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu entry terakhir diupdate',
    
    -- Foreign Key
    CONSTRAINT fk_pppk_angsuran_pengajuan 
        FOREIGN KEY (id_pengajuan) 
        REFERENCES pengajuan_kredit(id_pengajuan) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    
    -- Index untuk performa query
    INDEX idx_id_pengajuan (id_pengajuan),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Tabel detail angsuran dinamis untuk PPPK - satu pengajuan bisa punya multiple angsuran';

-- ========================================================================
-- STEP 3: CREATE DIRECTORY untuk upload file (Manual atau via PHP)
-- ========================================================================

-- Note: Jalankan command ini di terminal/file manager:
-- mkdir -p assets/uploads/sk_files
-- chmod 755 assets/uploads/sk_files

-- Atau jalankan via PHP:
/*
<?php
$upload_dir = __DIR__ . '/assets/uploads/sk_files/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
    echo "Directory created: " . $upload_dir;
}
?>
*/

-- ========================================================================
-- STEP 4: VERIFY - Check struktur tabel setelah migration
-- ========================================================================

-- Lihat struktur tabel pengajuan_kredit (baris terakhir seharusnya pppk_total_angsuran):
-- DESC pengajuan_kredit;

-- Lihat struktur tabel baru:
-- DESC pppk_angsuran_detail;

-- ========================================================================
-- STEP 5: OPTIONAL - SAMPLE DATA untuk testing
-- ========================================================================

-- Uncomment jika ingin menambah sample data untuk testing

/*
-- Asumsikan id_pengajuan = 1 (ganti sesuai kebutuhan)

-- Update pengajuan_kredit dengan data PPPK baru
UPDATE pengajuan_kredit 
SET 
    pppk_tgl_awal = '2024-01-15',
    pppk_tgl_akhir = '2026-12-31',
    pppk_sisa_kerja_bulan = 35,
    pppk_agunan_no_sk = 'SK/AGUNAN/2024/001',
    pppk_total_angsuran = 1250000.00
WHERE id_pengajuan = 1 AND jenis_pekerjaan = 'pppk';

-- Insert sample angsuran detail
INSERT INTO pppk_angsuran_detail (id_pengajuan, nama_produk, nominal_angsuran) VALUES
(1, 'KREDIT KONSUMTIF', 500000.00),
(1, 'KMK', 750000.00);

*/

-- ========================================================================
-- STEP 6: BACKUP EXISTING DATA (Safety measure)
-- ========================================================================

-- SEBELUM menjalankan migration, backup table:
-- mysqldump -u root -p bank_kredit pengajuan_kredit > pengajuan_kredit_backup_2026-04-30.sql

-- ========================================================================
-- ROLLBACK SCRIPT (jika perlu reverse migration)
-- ========================================================================

/*
-- Uncomment untuk rollback jika terjadi error

ALTER TABLE pengajuan_kredit DROP COLUMN IF EXISTS pppk_tgl_awal;
ALTER TABLE pengajuan_kredit DROP COLUMN IF EXISTS pppk_tgl_akhir;
ALTER TABLE pengajuan_kredit DROP COLUMN IF EXISTS pppk_sisa_kerja_bulan;
ALTER TABLE pengajuan_kredit DROP COLUMN IF EXISTS pppk_agunan_no_sk;
ALTER TABLE pengajuan_kredit DROP COLUMN IF EXISTS pppk_file_sk;
ALTER TABLE pengajuan_kredit DROP COLUMN IF EXISTS pppk_total_angsuran;

DROP TABLE IF EXISTS pppk_angsuran_detail;

*/

-- ========================================================================
-- END OF MIGRATION
-- ========================================================================
