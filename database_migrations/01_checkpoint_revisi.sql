-- ============================================================
-- MIGRATION: SISTEM REVISI BERBASIS CHECKPOINT
-- ============================================================

-- Tabel: analysis_checkpoints
-- Menyimpan status per-tab (Checkpoint)
CREATE TABLE IF NOT EXISTS analysis_checkpoints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pengajuan INT NOT NULL,
    tab_name VARCHAR(50) NOT NULL COMMENT 'pemohon, penghasilan, struktur, agunan, 6c, scoring, neraca',
    status ENUM('FILLED', 'APPROVED', 'REVISION', 'FIXED', 'RESUBMITTED') DEFAULT 'FILLED',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pengajuan_tab (id_pengajuan, tab_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: analysis_revisions
-- Menyimpan instruksi detail revisi dari atasan
CREATE TABLE IF NOT EXISTS analysis_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pengajuan INT NOT NULL,
    version_id INT DEFAULT 1,
    tab_name VARCHAR(50) NOT NULL,
    field_name VARCHAR(100) NULL COMMENT 'Bisa spesifik ke field tertentu',
    revision_note TEXT NOT NULL,
    status ENUM('PENDING', 'FIXED', 'APPROVED') DEFAULT 'PENDING',
    requested_by INT NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fixed_by INT NULL,
    fixed_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: analysis_audit_logs
-- Menyimpan riwayat perubahan sebelum-sesudah
CREATE TABLE IF NOT EXISTS analysis_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pengajuan INT NOT NULL,
    id_revision INT NULL,
    tab_name VARCHAR(50) NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    changed_by INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
