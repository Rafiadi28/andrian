-- ============================================================
-- Database: bank_kredit_db
-- Schema Version: 2.0 (Updated: April 22, 2026)
-- Total Tables: 11
-- Charset: utf8mb4_unicode_ci
-- ============================================================

-- ============================================================
-- TABEL 1: users
-- Menyimpan akun pengguna dan peran dalam sistem
-- ============================================================
CREATE TABLE users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    -- VARCHAR agar dapat menambah role baru tanpa ALTER TABLE
    role VARCHAR(100) NOT NULL,
    status_jabatan ENUM('aktif', 'sakit', 'izin', 'cuti', 'berhalangan') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role_jabatan (role, status_jabatan)
);

-- ============================================================
-- TABEL 2: pengajuan_kredit
-- Tabel utama data pengajuan kredit debitur
-- ============================================================
CREATE TABLE pengajuan_kredit (
    id_pengajuan INT AUTO_INCREMENT PRIMARY KEY,

    -- === DATA IDENTITAS ===
    nama_debitur VARCHAR(100) NOT NULL,
    nik VARCHAR(20) NOT NULL,
    npwp VARCHAR(50) NULL,
    nib VARCHAR(50) NULL,
    tempat_lahir VARCHAR(100) NULL,
    tanggal_lahir DATE NULL,
    nama_ibu_kandung VARCHAR(100) NULL,
    status_perkawinan ENUM('lajang','menikah','janda','duda') DEFAULT 'lajang',
    nama_pasangan VARCHAR(100) NULL,
    tempat_lahir_pasangan VARCHAR(100) NULL,
    tanggal_lahir_pasangan DATE NULL,
    pekerjaan_pasangan VARCHAR(100) NULL,
    alamat_pekerjaan_pasangan TEXT NULL,
    jumlah_tanggungan INT DEFAULT 0,
    no_hp VARCHAR(20) NULL,
    alamat_ktp TEXT NULL,
    alamat_domisili TEXT NULL,
    dukuh VARCHAR(100) NULL,
    desa VARCHAR(100) NULL,
    kecamatan VARCHAR(100) NULL,
    kota_kabupaten VARCHAR(100) NULL,

    -- === DATA PEKERJAAN ===
    pekerjaan VARCHAR(100) NOT NULL,
    jenis_pekerjaan VARCHAR(32) NULL DEFAULT 'umum' COMMENT 'umum|pns|pppk|perangkat_desa|kpr|kretamas|cashcolateral',
    id_nasabah VARCHAR(50) NULL,
    alamat_pekerjaan TEXT NULL,
    nama_instansi VARCHAR(150) NULL,
    alamat_instansi TEXT NULL,
    telepon_kantor VARCHAR(20) NULL,
    departemen_bagian VARCHAR(100) NULL,
    jabatan VARCHAR(100) NULL,

    -- === DATA USAHA ===
    nama_usaha VARCHAR(100) NULL,
    bidang_usaha VARCHAR(100) NULL,
    lama_usaha VARCHAR(50) NULL,

    -- === DATA KREDIT ===
    jenis_kredit VARCHAR(50) DEFAULT 'KMK',
    jenis_jaminan VARCHAR(50) DEFAULT 'tanah_bangunan',
    jumlah_kredit DECIMAL(15,2) NOT NULL,
    jangka_waktu INT NOT NULL COMMENT 'dalam bulan',
    jangka_tempo INT DEFAULT 1,
    suku_bunga DECIMAL(5,2) DEFAULT 0.00,
    grace_period INT DEFAULT 0,
    tujuan_kredit TEXT NOT NULL,
    pinjaman_ke INT DEFAULT 1,

    -- === DATA CASHFLOW PEMASUKAN ===
    omset_per_bulan DECIMAL(15,2) DEFAULT 0.00,
    biaya_operasional DECIMAL(15,2) DEFAULT 0.00,
    laba_bersih DECIMAL(15,2) DEFAULT 0.00,
    repayment_capacity DECIMAL(15,2) DEFAULT 0.00,

    -- === DATA CASHFLOW PENGELUARAN ===
    biaya_bahan_baku DECIMAL(15,2) DEFAULT 0.00,
    biaya_gaji DECIMAL(15,2) DEFAULT 0.00,
    biaya_listrik DECIMAL(15,2) DEFAULT 0.00,
    biaya_air DECIMAL(15,2) DEFAULT 0.00,
    biaya_sewa DECIMAL(15,2) DEFAULT 0.00,
    biaya_transportasi DECIMAL(15,2) DEFAULT 0.00,
    biaya_lainnya DECIMAL(15,2) DEFAULT 0.00,
    penyusutan DECIMAL(15,2) DEFAULT 0.00,
    cashflow_usaha DECIMAL(15,2) DEFAULT 0.00,
    biaya_hidup DECIMAL(15,2) DEFAULT 0.00,
    cicilan_lain DECIMAL(15,2) DEFAULT 0.00,
    total_pengeluaran_tetap DECIMAL(15,2) DEFAULT 0.00,
    net_cashflow DECIMAL(15,2) DEFAULT 0.00,
    angsuran_diajukan DECIMAL(15,2) DEFAULT 0.00,
    status_kelayakan VARCHAR(50) DEFAULT '',

    -- === FILE UPLOAD ===
    file_pendukung VARCHAR(255) NULL,
    file_jaminan VARCHAR(255) NULL,
    foto_rumah VARCHAR(255) NULL,
    foto_usaha VARCHAR(255) NULL,

    -- === STATUS DAN ALUR APPROVAL ===
    status_pengajuan ENUM(
        'draft','diajukan','kepatuhan','kasubag','kabag','kadiv','direksi',
        'revisi','revisi_diajukan','ditolak','disetujui','proses',
        'diajukan_ulang','selesai'
    ) DEFAULT 'draft',
    posisi_saat_ini VARCHAR(100) NULL DEFAULT 'analis',
    tanggal_pengajuan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    input_by INT,

    -- === TRACKING REVISI DAN PENOLAKAN ===
    revision_count INT NOT NULL DEFAULT 0,
    last_revision_at TIMESTAMP NULL,
    last_revision_by INT NULL,
    last_reject_level VARCHAR(50) NULL,
    revisi_dari_role VARCHAR(100) NULL,
    catatan_revisi TEXT NULL,
    ditolak_dari_role VARCHAR(100) NULL,
    alasan_penolakan TEXT NULL,
    last_position_role VARCHAR(100) NULL,

    FOREIGN KEY (input_by) REFERENCES users(id_user),
    INDEX idx_pk_posisi_status_tgl (posisi_saat_ini, status_pengajuan, tanggal_pengajuan),
    INDEX idx_pk_input_tgl (input_by, tanggal_pengajuan),
    INDEX idx_pk_status_tgl (status_pengajuan, tanggal_pengajuan)
);

-- ============================================================
-- TABEL 3: approval_kredit
-- Riwayat keputusan approval setiap level
-- ============================================================
CREATE TABLE approval_kredit (
    id_approval INT AUTO_INCREMENT PRIMARY KEY,
    id_pengajuan INT NOT NULL,
    id_user INT NULL COMMENT 'NULL jika auto-skip (sistem)',
    level_approval ENUM('analis','kepatuhan','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama') NOT NULL,
    keputusan ENUM(
        'setuju','tolak','kembalikan','revisi','pending',
        'eskalasi_otomatis','kirim_ulang','revisi_diajukan'
    ) NOT NULL,
    catatan TEXT,
    is_auto_skip TINYINT(1) DEFAULT 0,
    tanggal_approval TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan),
    FOREIGN KEY (id_user) REFERENCES users(id_user),
    INDEX idx_ak_user_level (id_user, level_approval)
);

-- ============================================================
-- TABEL 4: audit_log
-- Log aktivitas semua pengguna sistem
-- ============================================================
CREATE TABLE audit_log (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT,
    aktivitas TEXT NOT NULL,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id_user),
    INDEX idx_audit_user_waktu (id_user, waktu)
);

-- ============================================================
-- TABEL 4b: notifications
-- Sistem notifikasi untuk setiap role pada approval chain
-- ============================================================
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
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE,
    INDEX idx_notif_user_read (id_user, is_read),
    INDEX idx_notif_tipe_created (tipe_notifikasi, created_at),
    INDEX idx_notif_pengajuan (id_pengajuan)
);

-- ============================================================
-- TABEL 5: jaminan_tanah_bangunan
-- Data agunan berupa tanah dan/atau bangunan
-- ============================================================
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
    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE,
    INDEX idx_jm_id_pengajuan (id_pengajuan)
);

-- ============================================================
-- TABEL 6: jaminan_kendaraan
-- Data agunan berupa kendaraan bermotor
-- ============================================================
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
    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE,
    INDEX idx_jm_id_pengajuan (id_pengajuan)
);

-- ============================================================
-- TABEL 7: jaminan_emas
-- Data agunan berupa emas
-- ============================================================
CREATE TABLE jaminan_emas (
    id_jaminan INT AUTO_INCREMENT PRIMARY KEY,
    id_pengajuan INT NOT NULL,
    harga_per_gram DECIMAL(15,2),
    berat DECIMAL(15,6),
    nilai_pasar DECIMAL(15,2),
    nilai_likuidasi DECIMAL(15,2),
    file_jaminan VARCHAR(255),
    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE,
    INDEX idx_jm_id_pengajuan (id_pengajuan)
);

-- ============================================================
-- TABEL 8: analisa_neraca
-- Analisa neraca (balance sheet) debitur
-- ============================================================
CREATE TABLE analisa_neraca (
    id_neraca INT AUTO_INCREMENT PRIMARY KEY,
    id_pengajuan INT NOT NULL,
    aktiva_kas DECIMAL(15,2) DEFAULT 0.00,
    aktiva_tabungan DECIMAL(15,2) DEFAULT 0.00,
    aktiva_tanah DECIMAL(15,2) DEFAULT 0.00,
    aktiva_kendaraan DECIMAL(15,2) DEFAULT 0.00,
    aktiva_stok DECIMAL(15,2) DEFAULT 0.00,
    aktiva_lainnya DECIMAL(15,2) DEFAULT 0.00,
    pasiva_hutang_bank DECIMAL(15,2) DEFAULT 0.00,
    pasiva_hutang_lain DECIMAL(15,2) DEFAULT 0.00,
    pasiva_modal DECIMAL(15,2) DEFAULT 0.00,
    total_aktiva DECIMAL(15,2) DEFAULT 0.00,
    total_pasiva DECIMAL(15,2) DEFAULT 0.00,
    KEY idx_id_pengajuan (id_pengajuan),
    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE
);

-- ============================================================
-- TABEL 9: analisa_5c
-- Analisa 5C+1 (Character, Capacity, Capital, Collateral, Condition, Constraint)
-- ============================================================
CREATE TABLE analisa_5c (
    id_5c INT AUTO_INCREMENT PRIMARY KEY,
    id_pengajuan INT NOT NULL,
    character_score INT DEFAULT 0,
    capacity_score INT DEFAULT 0,
    capital_score INT DEFAULT 0,
    collateral_score INT DEFAULT 0,
    condition_score INT DEFAULT 0,
    constraint_score INT DEFAULT 0,
    total_score DECIMAL(5,2) DEFAULT 0.00,
    catatan_5c TEXT,
    rekomendasi VARCHAR(50),
    catatan_character TEXT,
    catatan_capacity TEXT,
    catatan_capital TEXT,
    catatan_collateral TEXT,
    catatan_condition TEXT,
    catatan_constraint_risk TEXT,
    KEY idx_id_pengajuan (id_pengajuan),
    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE
);

-- ============================================================
-- TABEL 10: assessment_kepatuhan
-- Penilaian kepatuhan / compliance assessment oleh analis
-- ============================================================
CREATE TABLE assessment_kepatuhan (
    id_assessment INT AUTO_INCREMENT PRIMARY KEY,
    id_pengajuan INT NOT NULL,
    id_user INT NOT NULL,
    tanggal_assessment DATE NOT NULL,
    checklist_data JSON,
    fasilitas_existing JSON,
    catatan_existing JSON,
    kesimpulan TEXT,
    rekomendasi TEXT,
    marketing VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_assessment_pengajuan (id_pengajuan),
    KEY idx_assessment_user_created (id_user, created_at),
    KEY idx_assessment_created_date (created_at),
    CONSTRAINT fk_assessment_pengajuan FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_assessment_user FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ============================================================
-- TABEL 11: angsuran_bank_lain
-- Data kredit/pinjaman debitur di bank lain (pengeluaran tetap)
-- ============================================================
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
);

-- ============================================================
-- SEED DATA: Default Users
-- Password: "password"  (bcrypt hash)
-- WAJIB GANTI sebelum production!
-- ============================================================
INSERT INTO users (nama, username, password, role, status_jabatan) VALUES
('Super Admin',        'admin',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Superadmin',   'aktif'),
('Budi Analis',        'analis',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'analis',       'aktif'),
('Siti Kasubag',       'kasubag_analis','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kasubag_analis', 'aktif'),
('Rudi Kabag Kredit',  'kabag_kredit',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kabag_kredit', 'aktif'),
('Dewi Kadiv Bisnis',  'kadiv_bisnis',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kadiv_bisnis', 'aktif'),
('Pak Bos Direktur',   'direktur_utama','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'direktur_utama', 'aktif'),
('Petugas Kepatuhan',  'kepatuhan',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepatuhan',    'aktif');
