-- Backup 2026-04-17 04:41:53
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `analisa_5c`;

CREATE TABLE `analisa_5c` (
  `id_5c` int NOT NULL AUTO_INCREMENT,
  `id_pengajuan` int NOT NULL,
  `character_score` int DEFAULT '0',
  `capacity_score` int DEFAULT '0',
  `capital_score` int DEFAULT '0',
  `collateral_score` int DEFAULT '0',
  `condition_score` int DEFAULT '0',
  `constraint_score` int DEFAULT '0',
  `total_score` decimal(5,2) DEFAULT '0.00',
  `catatan_5c` text,
  `rekomendasi` varchar(50) DEFAULT NULL,
  `catatan_character` text,
  `catatan_capacity` text,
  `catatan_capital` text,
  `catatan_collateral` text,
  `catatan_condition` text,
  `catatan_constraint_risk` text,
  PRIMARY KEY (`id_5c`),
  KEY `id_pengajuan` (`id_pengajuan`),
  CONSTRAINT `analisa_5c_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kredit` (`id_pengajuan`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

INSERT INTO `analisa_5c` VALUES('6','4','0','0','0','0','0','0','0.00','','LAYAK','','','','','','');
INSERT INTO `analisa_5c` VALUES('7','3','0','0','0','0','0','0','0.00','PASTIKAN ANGSURAN DIPOTONG SETIAP BULAN','LAYAK','','','','','','');
INSERT INTO `analisa_5c` VALUES('9','7','5','0','5','5','5','5','25.00','MAINTENANCE ANGSURAN','LAYAK','Kategori sangat kuat, risiko sangat rendah.','Kategori sangat kuat, risiko sangat rendah.','Kategori sangat kuat, risiko sangat rendah.','Kategori sangat kuat, risiko sangat rendah.','Kategori sangat kuat, risiko sangat rendah.','Kategori sangat kuat, risiko sangat rendah.');

DROP TABLE IF EXISTS `analisa_neraca`;

CREATE TABLE `analisa_neraca` (
  `id_neraca` int NOT NULL AUTO_INCREMENT,
  `id_pengajuan` int NOT NULL,
  `aktiva_kas` decimal(15,2) DEFAULT '0.00',
  `aktiva_tabungan` decimal(15,2) DEFAULT '0.00',
  `aktiva_tanah` decimal(15,2) DEFAULT '0.00',
  `aktiva_kendaraan` decimal(15,2) DEFAULT '0.00',
  `aktiva_stok` decimal(15,2) DEFAULT '0.00',
  `aktiva_lainnya` decimal(15,2) DEFAULT '0.00',
  `pasiva_hutang_bank` decimal(15,2) DEFAULT '0.00',
  `pasiva_hutang_lain` decimal(15,2) DEFAULT '0.00',
  `pasiva_modal` decimal(15,2) DEFAULT '0.00',
  `total_aktiva` decimal(15,2) DEFAULT '0.00',
  `total_pasiva` decimal(15,2) DEFAULT '0.00',
  PRIMARY KEY (`id_neraca`),
  KEY `id_pengajuan` (`id_pengajuan`),
  CONSTRAINT `analisa_neraca_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kredit` (`id_pengajuan`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

INSERT INTO `analisa_neraca` VALUES('2','4','1000000.00','1000000.00','50000000.00','15000000.00','0.00','0.00','60000000.00','500000.00','6500000.00','67000000.00','67000000.00');
INSERT INTO `analisa_neraca` VALUES('3','7','1000000.00','20000000.00','10000000.00','250000000.00','100000000.00','10000000.00','300000000.00','2000000.00','89000000.00','391000000.00','391000000.00');

DROP TABLE IF EXISTS `angsuran_bank_lain`;

CREATE TABLE `angsuran_bank_lain` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pengajuan` int NOT NULL,
  `nama_bank` varchar(100) NOT NULL,
  `plafond` decimal(15,2) DEFAULT '0.00',
  `tenor` int DEFAULT '0',
  `bunga` decimal(5,2) DEFAULT '0.00',
  `jenis_bunga` varchar(20) DEFAULT 'Flat',
  `baki_debet` decimal(15,2) DEFAULT '0.00',
  `angsuran` decimal(15,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `id_pengajuan` (`id_pengajuan`),
  CONSTRAINT `angsuran_bank_lain_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kredit` (`id_pengajuan`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `approval_kredit`;

CREATE TABLE `approval_kredit` (
  `id_approval` int NOT NULL AUTO_INCREMENT,
  `id_pengajuan` int NOT NULL,
  `id_user` int DEFAULT NULL,
  `level_approval` enum('analis','kabag_analis','kabag_kredit','kadiv_kredit','direksi') NOT NULL,
  `keputusan` enum('setuju','tolak','kembalikan','pending','eskalasi_otomatis','kirim_ulang','revisi_diajukan','revisi') NOT NULL,
  `catatan` text,
  `is_auto_skip` tinyint(1) DEFAULT '0',
  `tanggal_approval` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_approval`),
  KEY `id_pengajuan` (`id_pengajuan`),
  KEY `idx_ak_user_level` (`id_user`,`level_approval`),
  CONSTRAINT `approval_kredit_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kredit` (`id_pengajuan`),
  CONSTRAINT `approval_kredit_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `approval_kredit` VALUES('3','4','2','analis','setuju','Pengajuan lengkap.','0','2026-04-04 09:46:58');
INSERT INTO `approval_kredit` VALUES('4','4',NULL,'kabag_analis','eskalasi_otomatis','Auto Skip.','1','2026-04-04 09:46:58');
INSERT INTO `approval_kredit` VALUES('5','3','2','analis','setuju','Pengajuan lengkap.','0','2026-04-04 10:43:40');
INSERT INTO `approval_kredit` VALUES('8','4','2','analis','tolak','Dibatalkan oleh 2 (analis)','0','2026-04-04 10:44:57');
INSERT INTO `approval_kredit` VALUES('10','4','2','analis','tolak','Dibatalkan oleh 2 (analis)','0','2026-04-04 10:49:30');
INSERT INTO `approval_kredit` VALUES('11','4','2','analis','tolak','Dibatalkan oleh 2 (analis)','0','2026-04-04 10:49:34');
INSERT INTO `approval_kredit` VALUES('19','3','3','kabag_analis','setuju','sudah lengkap','0','2026-04-04 10:58:50');
INSERT INTO `approval_kredit` VALUES('20','3','4','kabag_kredit','setuju','lengkap','0','2026-04-04 10:59:14');
INSERT INTO `approval_kredit` VALUES('21','3','5','kadiv_kredit','setuju','lengkap','0','2026-04-04 10:59:35');
INSERT INTO `approval_kredit` VALUES('22','3','6','direksi','setuju','setuju ','0','2026-04-04 11:18:17');
INSERT INTO `approval_kredit` VALUES('33','7','2','analis','setuju','Pengajuan lengkap.','0','2026-04-06 10:49:27');
INSERT INTO `approval_kredit` VALUES('34','7','3','kabag_analis','setuju','acc','0','2026-04-13 09:10:18');
INSERT INTO `approval_kredit` VALUES('35','7','4','kabag_kredit','setuju','acc','0','2026-04-13 09:12:52');
INSERT INTO `approval_kredit` VALUES('36','7','5','kadiv_kredit','setuju','acc','0','2026-04-13 09:13:41');

DROP TABLE IF EXISTS `assessment_kepatuhan`;

CREATE TABLE `assessment_kepatuhan` (
  `id_assessment` int NOT NULL AUTO_INCREMENT,
  `id_pengajuan` int NOT NULL,
  `id_user` int NOT NULL,
  `tanggal_assessment` date NOT NULL,
  `checklist_data` json DEFAULT NULL,
  `fasilitas_existing` json DEFAULT NULL,
  `catatan_existing` json DEFAULT NULL,
  `kesimpulan` text,
  `rekomendasi` text,
  `marketing` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_assessment`),
  KEY `idx_assessment_pengajuan` (`id_pengajuan`),
  KEY `idx_assessment_user_created` (`id_user`,`created_at`),
  KEY `idx_assessment_created_date` (`created_at`),
  CONSTRAINT `assessment_kepatuhan_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kredit` (`id_pengajuan`) ON DELETE CASCADE,
  CONSTRAINT `fk_assessment_pengajuan` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kredit` (`id_pengajuan`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_assessment_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `assessment_kepatuhan` VALUES('2','7','7','2026-04-17','{\"bmpk\": {\"ket\": \"Pihak tidak terkait\", \"val\": \"comply\"}, \"prod\": {\"ket\": \"Sesuai\", \"val\": \"comply\"}, \"an_ag\": {\"ket\": \"Sesuai limit\", \"val\": \"comply\"}, \"ag_cek\": {\"ket\": \"Belum Terlampir\", \"val\": \"comply\"}, \"ag_shm\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"an_krd\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"dok_kk\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"ag_foto\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"ag_njop\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"ag_sppt\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"dok_ktp\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"keu_lap\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"keu_rek\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"leg_nib\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"ag_kuasa\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"ag_visit\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"dok_form\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"dok_foto\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"krit_kol\": {\"ket\": \"Lancar\", \"val\": \"comply\"}, \"krit_wni\": {\"ket\": \"WNI\", \"val\": \"comply\"}, \"leg_npwp\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"dok_nikah\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}, \"krit_jenis\": {\"ket\": \"Perorangan\", \"val\": \"comply\"}, \"usaha_pkpb\": {\"ket\": \"\", \"val\": \"comply\"}, \"dok_ktp_pas\": {\"ket\": \"Terlampir\", \"val\": \"comply\"}}','[]','{\"dok\": {\"ket\": \"\", \"val\": \"comply\"}, \"ikat\": {\"ket\": \"\", \"val\": \"comply\"}, \"putus\": {\"ket\": \"\", \"val\": \"comply\"}}','','','','2026-04-17 08:28:35','2026-04-17 08:28:35');

DROP TABLE IF EXISTS `audit_log`;

CREATE TABLE `audit_log` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `id_user` int DEFAULT NULL,
  `aktivitas` text NOT NULL,
  `waktu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`),
  KEY `idx_audit_user_waktu` (`id_user`,`waktu`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`)
) ENGINE=InnoDB AUTO_INCREMENT=245 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `audit_log` VALUES('1','1','Login ke sistem','2026-03-04 13:52:14');
INSERT INTO `audit_log` VALUES('2','1','Admin mengubah status user ID 5 menjadi aktif','2026-03-04 13:54:37');
INSERT INTO `audit_log` VALUES('3','2','Login ke sistem','2026-03-04 13:55:16');
INSERT INTO `audit_log` VALUES('4','1','Login ke sistem','2026-03-04 13:56:21');
INSERT INTO `audit_log` VALUES('5','1','Login ke sistem','2026-03-04 14:01:32');
INSERT INTO `audit_log` VALUES('6','1','Login ke sistem','2026-03-04 14:03:24');
INSERT INTO `audit_log` VALUES('7','2','Login ke sistem','2026-03-04 14:03:45');
INSERT INTO `audit_log` VALUES('8','6','Login ke sistem','2026-03-04 20:55:52');
INSERT INTO `audit_log` VALUES('9','2','Login ke sistem','2026-03-04 20:56:14');
INSERT INTO `audit_log` VALUES('10','1','Login ke sistem','2026-03-04 21:20:30');
INSERT INTO `audit_log` VALUES('11','2','Login ke sistem','2026-03-05 09:04:49');
INSERT INTO `audit_log` VALUES('12','2','Login ke sistem','2026-03-05 09:15:31');
INSERT INTO `audit_log` VALUES('13','2','Login ke sistem','2026-03-05 09:15:46');
INSERT INTO `audit_log` VALUES('14','2','Login ke sistem','2026-03-05 20:34:59');
INSERT INTO `audit_log` VALUES('15','2','Login ke sistem','2026-04-01 08:27:41');
INSERT INTO `audit_log` VALUES('16','2','Login ke sistem','2026-04-01 09:22:21');
INSERT INTO `audit_log` VALUES('17','2','Login ke sistem','2026-04-01 10:51:07');
INSERT INTO `audit_log` VALUES('18','3','Login ke sistem','2026-04-01 10:52:08');
INSERT INTO `audit_log` VALUES('19','3','Login ke sistem','2026-04-01 10:52:43');
INSERT INTO `audit_log` VALUES('20','2','Login ke sistem','2026-04-01 10:53:14');
INSERT INTO `audit_log` VALUES('21','3','Login ke sistem','2026-04-01 10:53:27');
INSERT INTO `audit_log` VALUES('22','2','Login ke sistem','2026-04-01 10:53:52');
INSERT INTO `audit_log` VALUES('23','2','Login ke sistem','2026-04-01 10:54:14');
INSERT INTO `audit_log` VALUES('24','1','Login ke sistem','2026-04-01 10:54:45');
INSERT INTO `audit_log` VALUES('25','2','Login ke sistem','2026-04-01 19:07:33');
INSERT INTO `audit_log` VALUES('26','2','Login ke sistem','2026-04-02 09:06:05');
INSERT INTO `audit_log` VALUES('27','2','Login ke sistem','2026-04-02 09:55:20');
INSERT INTO `audit_log` VALUES('28','2','Login ke sistem','2026-04-02 10:22:53');
INSERT INTO `audit_log` VALUES('29','2','Login ke sistem','2026-04-02 10:23:49');
INSERT INTO `audit_log` VALUES('30','2','Login ke sistem','2026-04-02 10:32:26');
INSERT INTO `audit_log` VALUES('31','2','Membuat Data Pemohon Baru (ID Pengajuan: 1)','2026-04-02 11:02:40');
INSERT INTO `audit_log` VALUES('32','2','Memperbarui Data Pemohon (ID Pengajuan: 1)','2026-04-02 11:03:02');
INSERT INTO `audit_log` VALUES('33','2','Login ke sistem','2026-04-02 13:20:46');
INSERT INTO `audit_log` VALUES('34','2','Login ke sistem','2026-04-02 15:01:06');
INSERT INTO `audit_log` VALUES('35','2','Memperbarui Data Pemohon (ID Pengajuan: 1)','2026-04-02 15:02:15');
INSERT INTO `audit_log` VALUES('36','2','Menyimpan 1 Data Agunan (ID Pengajuan: 1)','2026-04-02 15:05:07');
INSERT INTO `audit_log` VALUES('37','3','Login ke sistem','2026-04-02 15:42:18');
INSERT INTO `audit_log` VALUES('38','3','Login ke sistem','2026-04-02 15:43:11');
INSERT INTO `audit_log` VALUES('39','4','Login ke sistem','2026-04-02 15:48:38');
INSERT INTO `audit_log` VALUES('40','3','Login ke sistem','2026-04-02 15:49:11');
INSERT INTO `audit_log` VALUES('41','5','Login ke sistem','2026-04-02 15:49:54');
INSERT INTO `audit_log` VALUES('42','4','Login ke sistem','2026-04-02 15:52:19');
INSERT INTO `audit_log` VALUES('43','3','Login ke sistem','2026-04-02 15:54:49');
INSERT INTO `audit_log` VALUES('44','2','Login ke sistem','2026-04-02 20:03:51');
INSERT INTO `audit_log` VALUES('45','2','Membuat Data Pemohon Baru (ID Pengajuan: 2)','2026-04-02 20:07:02');
INSERT INTO `audit_log` VALUES('46','2','Memperbarui Data Pemohon (ID Pengajuan: 1)','2026-04-02 20:07:32');
INSERT INTO `audit_log` VALUES('47','3','Login ke sistem','2026-04-02 20:10:02');
INSERT INTO `audit_log` VALUES('48','1','Login ke sistem','2026-04-02 20:11:07');
INSERT INTO `audit_log` VALUES('49','1','Login ke sistem','2026-04-02 20:12:51');
INSERT INTO `audit_log` VALUES('50','2','Login ke sistem','2026-04-03 21:39:27');
INSERT INTO `audit_log` VALUES('51','2','Membuat Data Pemohon Baru (ID Pengajuan: 3)','2026-04-03 21:42:28');
INSERT INTO `audit_log` VALUES('52','3','Login ke sistem','2026-04-03 21:47:19');
INSERT INTO `audit_log` VALUES('53','2','Login ke sistem','2026-04-03 21:47:40');
INSERT INTO `audit_log` VALUES('54','2','Membuat Data Pemohon Baru (ID Pengajuan: 4)','2026-04-03 22:06:25');
INSERT INTO `audit_log` VALUES('55','2','Membuat Data Pemohon Baru (ID Pengajuan: 5)','2026-04-03 22:12:07');
INSERT INTO `audit_log` VALUES('56','2','Menyimpan 1 Data Agunan (ID Pengajuan: 4)','2026-04-03 22:37:30');
INSERT INTO `audit_log` VALUES('57','2','Login ke sistem','2026-04-04 09:42:46');
INSERT INTO `audit_log` VALUES('58','2','Memperbarui Data Pemohon (ID Pengajuan: 5)','2026-04-04 09:42:59');
INSERT INTO `audit_log` VALUES('59','2','Menyimpan 1 Data Agunan (ID Pengajuan: 5)','2026-04-04 09:43:36');
INSERT INTO `audit_log` VALUES('60','3','Login ke sistem','2026-04-04 09:45:15');
INSERT INTO `audit_log` VALUES('61','5','Login ke sistem','2026-04-04 09:45:41');
INSERT INTO `audit_log` VALUES('62','2','Login ke sistem','2026-04-04 09:45:52');
INSERT INTO `audit_log` VALUES('63','2','Memperbarui Data Pemohon (ID Pengajuan: 4)','2026-04-04 09:46:29');
INSERT INTO `audit_log` VALUES('64','2','Menyimpan 1 Data Agunan (ID Pengajuan: 4)','2026-04-04 09:46:43');
INSERT INTO `audit_log` VALUES('65','3','Login ke sistem','2026-04-04 09:47:25');
INSERT INTO `audit_log` VALUES('66','3','Login ke sistem','2026-04-04 09:54:40');
INSERT INTO `audit_log` VALUES('67','3','Login ke sistem','2026-04-04 10:11:36');
INSERT INTO `audit_log` VALUES('68','2','Login ke sistem','2026-04-04 10:13:04');
INSERT INTO `audit_log` VALUES('69','2','Login ke sistem','2026-04-04 10:13:49');
INSERT INTO `audit_log` VALUES('70','4','Login ke sistem','2026-04-04 10:14:41');
INSERT INTO `audit_log` VALUES('71','3','Login ke sistem','2026-04-04 10:40:53');
INSERT INTO `audit_log` VALUES('72','3','Login ke sistem','2026-04-04 10:41:38');
INSERT INTO `audit_log` VALUES('73','2','Login ke sistem','2026-04-04 10:43:07');
INSERT INTO `audit_log` VALUES('74','2','Memperbarui Data Pemohon (ID Pengajuan: 3)','2026-04-04 10:43:24');
INSERT INTO `audit_log` VALUES('75','2','Menyimpan 0 Data Agunan (ID Pengajuan: 3)','2026-04-04 10:43:33');
INSERT INTO `audit_log` VALUES('76','3','Login ke sistem','2026-04-04 10:43:50');
INSERT INTO `audit_log` VALUES('77','2','Login ke sistem','2026-04-04 10:44:25');
INSERT INTO `audit_log` VALUES('78','2','Membatalkan pengajuan ID: 5','2026-04-04 10:44:34');
INSERT INTO `audit_log` VALUES('79','2','Membatalkan pengajuan ID: 5','2026-04-04 10:44:37');
INSERT INTO `audit_log` VALUES('80','2','Membatalkan pengajuan ID: 4','2026-04-04 10:44:57');
INSERT INTO `audit_log` VALUES('81','1','Login ke sistem','2026-04-04 10:48:14');
INSERT INTO `audit_log` VALUES('82','2','Login ke sistem','2026-04-04 10:49:05');
INSERT INTO `audit_log` VALUES('83','2','Login ke sistem','2026-04-04 10:49:14');
INSERT INTO `audit_log` VALUES('84','2','Membatalkan pengajuan ID: 5','2026-04-04 10:49:20');
INSERT INTO `audit_log` VALUES('85','2','Membatalkan pengajuan ID: 4','2026-04-04 10:49:30');
INSERT INTO `audit_log` VALUES('86','2','Membatalkan pengajuan ID: 4','2026-04-04 10:49:34');
INSERT INTO `audit_log` VALUES('87','2','Membatalkan pengajuan ID: 5','2026-04-04 10:53:06');
INSERT INTO `audit_log` VALUES('88','2','Membatalkan pengajuan ID: 5','2026-04-04 10:53:16');
INSERT INTO `audit_log` VALUES('89','2','Membatalkan pengajuan ID: 5','2026-04-04 10:55:59');
INSERT INTO `audit_log` VALUES('90','2','Membatalkan pengajuan ID: 5','2026-04-04 10:56:07');
INSERT INTO `audit_log` VALUES('91','1','Login ke sistem','2026-04-04 10:56:22');
INSERT INTO `audit_log` VALUES('92','2','Login ke sistem','2026-04-04 10:57:34');
INSERT INTO `audit_log` VALUES('93','2','Membatalkan pengajuan ID: 5','2026-04-04 10:57:52');
INSERT INTO `audit_log` VALUES('94','2','Mengirim ulang pengajuan (ID: 5) ke kabag_analis','2026-04-04 10:57:57');
INSERT INTO `audit_log` VALUES('95','2','Membatalkan pengajuan ID: 5','2026-04-04 10:58:01');
INSERT INTO `audit_log` VALUES('96','4','Login ke sistem','2026-04-04 10:58:16');
INSERT INTO `audit_log` VALUES('97','4','Login ke sistem','2026-04-04 10:58:31');
INSERT INTO `audit_log` VALUES('98','3','Login ke sistem','2026-04-04 10:58:40');
INSERT INTO `audit_log` VALUES('99','4','Login ke sistem','2026-04-04 10:59:05');
INSERT INTO `audit_log` VALUES('100','5','Login ke sistem','2026-04-04 10:59:22');
INSERT INTO `audit_log` VALUES('101','6','Login ke sistem','2026-04-04 11:06:35');
INSERT INTO `audit_log` VALUES('102','5','Login ke sistem','2026-04-04 11:07:12');
INSERT INTO `audit_log` VALUES('103','6','Login ke sistem','2026-04-04 11:07:36');
INSERT INTO `audit_log` VALUES('104','5','Login ke sistem','2026-04-04 11:08:27');
INSERT INTO `audit_log` VALUES('105','6','Login ke sistem','2026-04-04 11:18:07');
INSERT INTO `audit_log` VALUES('106','2','Login ke sistem','2026-04-04 11:18:36');
INSERT INTO `audit_log` VALUES('107','3','Login ke sistem','2026-04-04 11:51:43');
INSERT INTO `audit_log` VALUES('108','4','Login ke sistem','2026-04-04 11:52:04');
INSERT INTO `audit_log` VALUES('109','1','Login ke sistem','2026-04-04 11:52:17');
INSERT INTO `audit_log` VALUES('110','1','Login ke sistem','2026-04-04 11:58:50');
INSERT INTO `audit_log` VALUES('111','3','Login ke sistem','2026-04-04 12:02:15');
INSERT INTO `audit_log` VALUES('112','1','Login ke sistem','2026-04-04 12:02:23');
INSERT INTO `audit_log` VALUES('113','3','Login ke sistem','2026-04-04 12:03:36');
INSERT INTO `audit_log` VALUES('114','1','Login ke sistem','2026-04-04 12:03:49');
INSERT INTO `audit_log` VALUES('115','2','Login ke sistem','2026-04-04 12:11:37');
INSERT INTO `audit_log` VALUES('116','2','Login ke sistem','2026-04-04 14:23:46');
INSERT INTO `audit_log` VALUES('117','2','Membuat Data Pemohon Baru (ID Pengajuan: 6)','2026-04-04 14:25:50');
INSERT INTO `audit_log` VALUES('118','3','Login ke sistem','2026-04-04 14:28:46');
INSERT INTO `audit_log` VALUES('119','4','Login ke sistem','2026-04-04 14:30:16');
INSERT INTO `audit_log` VALUES('120','5','Login ke sistem','2026-04-04 14:31:01');
INSERT INTO `audit_log` VALUES('121','2','Login ke sistem','2026-04-04 14:31:26');
INSERT INTO `audit_log` VALUES('122','1','Login ke sistem','2026-04-04 14:34:45');
INSERT INTO `audit_log` VALUES('123','3','Login ke sistem','2026-04-04 14:38:06');
INSERT INTO `audit_log` VALUES('124','6','Login ke sistem','2026-04-04 14:38:26');
INSERT INTO `audit_log` VALUES('125','2','Login ke sistem','2026-04-04 14:39:22');
INSERT INTO `audit_log` VALUES('126','2','Membatalkan pengajuan ID: 6','2026-04-04 14:39:34');
INSERT INTO `audit_log` VALUES('127','2','Membatalkan pengajuan ID: 6','2026-04-04 14:39:38');
INSERT INTO `audit_log` VALUES('128','2','Membatalkan pengajuan ID: 6','2026-04-04 14:39:59');
INSERT INTO `audit_log` VALUES('129','2','Membatalkan pengajuan ID: 6','2026-04-04 14:40:10');
INSERT INTO `audit_log` VALUES('130','2','Membatalkan pengajuan ID: 6','2026-04-04 14:40:20');
INSERT INTO `audit_log` VALUES('131','2','Membatalkan pengajuan ID: 6','2026-04-04 14:41:30');
INSERT INTO `audit_log` VALUES('132','2','Login ke sistem','2026-04-04 14:45:35');
INSERT INTO `audit_log` VALUES('133','2','Login ke sistem','2026-04-06 10:01:58');
INSERT INTO `audit_log` VALUES('134','2','Login ke sistem','2026-04-06 10:08:52');
INSERT INTO `audit_log` VALUES('135','2','Login ke sistem','2026-04-06 10:10:05');
INSERT INTO `audit_log` VALUES('136','2','Login ke sistem','2026-04-06 10:27:23');
INSERT INTO `audit_log` VALUES('137','2','Membuat Data Pemohon Baru (ID Pengajuan: 7)','2026-04-06 10:31:44');
INSERT INTO `audit_log` VALUES('138','2','Menyimpan 1 Data Agunan (ID Pengajuan: 7)','2026-04-06 10:41:45');
INSERT INTO `audit_log` VALUES('139','3','Login ke sistem','2026-04-06 10:50:06');
INSERT INTO `audit_log` VALUES('140','2','Login ke sistem','2026-04-06 10:51:44');
INSERT INTO `audit_log` VALUES('141','3','Login ke sistem','2026-04-06 10:53:28');
INSERT INTO `audit_log` VALUES('142','2','Login ke sistem','2026-04-06 10:57:05');
INSERT INTO `audit_log` VALUES('143','2','Login ke sistem','2026-04-06 11:00:42');
INSERT INTO `audit_log` VALUES('144','2','Login ke sistem','2026-04-06 11:00:56');
INSERT INTO `audit_log` VALUES('145','2','Login ke sistem','2026-04-06 11:02:11');
INSERT INTO `audit_log` VALUES('146','1','Login ke sistem','2026-04-06 11:23:49');
INSERT INTO `audit_log` VALUES('147','6','Login ke sistem','2026-04-06 11:25:31');
INSERT INTO `audit_log` VALUES('148','2','Login ke sistem','2026-04-06 11:26:21');
INSERT INTO `audit_log` VALUES('149','3','Login ke sistem','2026-04-06 11:32:23');
INSERT INTO `audit_log` VALUES('150','2','Login ke sistem','2026-04-06 11:34:38');
INSERT INTO `audit_log` VALUES('151','1','Login ke sistem','2026-04-13 08:37:40');
INSERT INTO `audit_log` VALUES('152','2','Login ke sistem','2026-04-13 08:56:56');
INSERT INTO `audit_log` VALUES('153','3','Login ke sistem','2026-04-13 09:10:04');
INSERT INTO `audit_log` VALUES('154','4','Login ke sistem','2026-04-13 09:11:04');
INSERT INTO `audit_log` VALUES('155','5','Login ke sistem','2026-04-13 09:13:32');
INSERT INTO `audit_log` VALUES('156','2','Login ke sistem','2026-04-13 09:13:49');
INSERT INTO `audit_log` VALUES('157','2','Memperbarui Data Pemohon (ID Pengajuan: 2)','2026-04-13 09:14:41');
INSERT INTO `audit_log` VALUES('158','2','Membatalkan pengajuan ID: 5','2026-04-13 10:33:47');
INSERT INTO `audit_log` VALUES('159','2','Membatalkan pengajuan ID: 5','2026-04-13 10:34:00');
INSERT INTO `audit_log` VALUES('160','2','Membatalkan pengajuan ID: 5','2026-04-13 11:00:47');
INSERT INTO `audit_log` VALUES('161','2','Logout dari sistem','2026-04-13 11:14:03');
INSERT INTO `audit_log` VALUES('162',NULL,'Login gagal (username: kabag_analis)','2026-04-13 11:14:13');
INSERT INTO `audit_log` VALUES('163','2','Login ke sistem','2026-04-13 11:14:23');
INSERT INTO `audit_log` VALUES('164','2','Membatalkan pengajuan ID: 6','2026-04-13 11:23:26');
INSERT INTO `audit_log` VALUES('165','2','Menghapus pengajuan ID: 6 (ADRIAN PRATAMA)','2026-04-13 11:25:59');
INSERT INTO `audit_log` VALUES('166','2','Menghapus pengajuan ID: 5 (ADRIAN PRATAMA)','2026-04-13 11:26:04');
INSERT INTO `audit_log` VALUES('167','2','Logout dari sistem','2026-04-13 11:42:00');
INSERT INTO `audit_log` VALUES('168','1','Login ke sistem','2026-04-13 11:42:07');
INSERT INTO `audit_log` VALUES('169','1','Logout dari sistem','2026-04-13 11:43:44');
INSERT INTO `audit_log` VALUES('170','2','Login ke sistem','2026-04-13 11:43:52');
INSERT INTO `audit_log` VALUES('171','2','Logout dari sistem','2026-04-13 11:44:27');
INSERT INTO `audit_log` VALUES('172','3','Login ke sistem','2026-04-13 11:44:35');
INSERT INTO `audit_log` VALUES('173','3','Logout dari sistem','2026-04-13 11:44:53');
INSERT INTO `audit_log` VALUES('174','2','Login ke sistem','2026-04-13 11:45:07');
INSERT INTO `audit_log` VALUES('175','2','Membuat Data Pemohon Baru (ID Pengajuan: 8)','2026-04-13 11:46:09');
INSERT INTO `audit_log` VALUES('176','2','Logout dari sistem','2026-04-13 11:53:58');
INSERT INTO `audit_log` VALUES('177','2','Login ke sistem','2026-04-13 11:54:02');
INSERT INTO `audit_log` VALUES('178',NULL,'Login gagal (username: admin)','2026-04-13 12:34:26');
INSERT INTO `audit_log` VALUES('179','2','Login ke sistem','2026-04-13 12:34:35');
INSERT INTO `audit_log` VALUES('180','2','Logout dari sistem','2026-04-13 12:35:47');
INSERT INTO `audit_log` VALUES('181','2','Login ke sistem','2026-04-14 09:28:15');
INSERT INTO `audit_log` VALUES('182','2','Logout dari sistem','2026-04-14 09:37:06');
INSERT INTO `audit_log` VALUES('183','2','Login ke sistem','2026-04-14 09:37:11');
INSERT INTO `audit_log` VALUES('184','2','Logout dari sistem','2026-04-14 09:37:49');
INSERT INTO `audit_log` VALUES('185','2','Login ke sistem','2026-04-14 09:38:46');
INSERT INTO `audit_log` VALUES('186','2','Membuat Data Pemohon Baru (ID Pengajuan: 9)','2026-04-14 09:42:25');
INSERT INTO `audit_log` VALUES('187','2','Login ke sistem','2026-04-14 09:49:32');
INSERT INTO `audit_log` VALUES('188','2','Menghapus pengajuan ID: 9 (SLALDS)','2026-04-14 09:49:43');
INSERT INTO `audit_log` VALUES('189','2','Logout dari sistem','2026-04-14 09:51:59');
INSERT INTO `audit_log` VALUES('190','2','Login ke sistem','2026-04-14 09:52:05');
INSERT INTO `audit_log` VALUES('191',NULL,'Login gagal (username: analis)','2026-04-14 09:53:56');
INSERT INTO `audit_log` VALUES('192',NULL,'Login gagal (username: analis)','2026-04-14 09:54:11');
INSERT INTO `audit_log` VALUES('193',NULL,'Login gagal (username: analis)','2026-04-14 09:54:27');
INSERT INTO `audit_log` VALUES('194',NULL,'Login gagal (username: analis)','2026-04-14 09:54:42');
INSERT INTO `audit_log` VALUES('195',NULL,'Login gagal (username: analis)','2026-04-14 09:54:57');
INSERT INTO `audit_log` VALUES('196','2','Login ke sistem','2026-04-14 09:55:33');
INSERT INTO `audit_log` VALUES('197','2','Membuat Data Pemohon Baru (ID Pengajuan: 10)','2026-04-14 09:57:26');
INSERT INTO `audit_log` VALUES('198','2','Memperbarui Data Pemohon (ID Pengajuan: 10)','2026-04-14 10:00:43');
INSERT INTO `audit_log` VALUES('199','2','Login ke sistem','2026-04-14 10:40:41');
INSERT INTO `audit_log` VALUES('200','2','Logout dari sistem','2026-04-14 10:55:59');
INSERT INTO `audit_log` VALUES('201','2','Login ke sistem','2026-04-14 10:56:06');
INSERT INTO `audit_log` VALUES('202','2','Logout dari sistem','2026-04-14 10:56:54');
INSERT INTO `audit_log` VALUES('203','2','Login ke sistem','2026-04-14 11:07:05');
INSERT INTO `audit_log` VALUES('204','2','Login ke sistem','2026-04-14 11:11:10');
INSERT INTO `audit_log` VALUES('205','2','Login ke sistem','2026-04-14 12:12:32');
INSERT INTO `audit_log` VALUES('206','2','Login ke sistem','2026-04-14 12:38:01');
INSERT INTO `audit_log` VALUES('207','2','Logout dari sistem','2026-04-14 13:11:32');
INSERT INTO `audit_log` VALUES('208','2','Logout dari sistem','2026-04-14 13:11:32');
INSERT INTO `audit_log` VALUES('209','2','Logout dari sistem','2026-04-14 13:12:59');
INSERT INTO `audit_log` VALUES('210',NULL,'Login gagal (username: kepatuhan)','2026-04-14 13:13:07');
INSERT INTO `audit_log` VALUES('211',NULL,'Login gagal (username: kepatuhan)','2026-04-14 13:13:16');
INSERT INTO `audit_log` VALUES('212','1','Login ke sistem','2026-04-14 13:13:25');
INSERT INTO `audit_log` VALUES('213','1','Mengubah user ID 7: nama \"Pejabat Kepatuhan\"→\"Pejabat Kepatuhan\", username \"kepatuhan\"→\"kepatuhan\", role kepatuhan→kepatuhan, password diubah','2026-04-14 13:16:58');
INSERT INTO `audit_log` VALUES('214','1','Logout dari sistem','2026-04-14 13:17:04');
INSERT INTO `audit_log` VALUES('215','7','Login ke sistem','2026-04-14 13:17:13');
INSERT INTO `audit_log` VALUES('216','7','Login ke sistem','2026-04-14 13:22:18');
INSERT INTO `audit_log` VALUES('217','7','Logout dari sistem','2026-04-14 13:38:20');
INSERT INTO `audit_log` VALUES('218','2','Login ke sistem','2026-04-14 13:38:25');
INSERT INTO `audit_log` VALUES('219','2','Login ke sistem','2026-04-17 07:35:29');
INSERT INTO `audit_log` VALUES('220','2','Logout dari sistem','2026-04-17 07:40:15');
INSERT INTO `audit_log` VALUES('221','7','Login ke sistem','2026-04-17 07:40:32');
INSERT INTO `audit_log` VALUES('222','7','Logout dari sistem','2026-04-17 08:05:52');
INSERT INTO `audit_log` VALUES('223','2','Login ke sistem','2026-04-17 08:05:58');
INSERT INTO `audit_log` VALUES('224','2','Logout dari sistem','2026-04-17 08:28:17');
INSERT INTO `audit_log` VALUES('225','7','Login ke sistem','2026-04-17 08:28:28');
INSERT INTO `audit_log` VALUES('226','7','Logout dari sistem','2026-04-17 08:28:40');
INSERT INTO `audit_log` VALUES('227','2','Login ke sistem','2026-04-17 08:28:48');
INSERT INTO `audit_log` VALUES('228','2','Logout dari sistem','2026-04-17 08:42:27');
INSERT INTO `audit_log` VALUES('229','3','Login ke sistem','2026-04-17 08:42:35');
INSERT INTO `audit_log` VALUES('230','3','Logout dari sistem','2026-04-17 08:42:50');
INSERT INTO `audit_log` VALUES('231','2','Login ke sistem','2026-04-17 08:42:56');
INSERT INTO `audit_log` VALUES('232','2','Logout dari sistem','2026-04-17 09:03:58');
INSERT INTO `audit_log` VALUES('233','2','Login ke sistem','2026-04-17 09:04:07');
INSERT INTO `audit_log` VALUES('234','2','Logout dari sistem','2026-04-17 09:04:13');
INSERT INTO `audit_log` VALUES('235','7','Login ke sistem','2026-04-17 09:04:20');
INSERT INTO `audit_log` VALUES('236','7','Login ke sistem','2026-04-17 10:11:19');
INSERT INTO `audit_log` VALUES('237','7','Logout dari sistem','2026-04-17 10:44:24');
INSERT INTO `audit_log` VALUES('238','2','Login ke sistem','2026-04-17 10:44:29');
INSERT INTO `audit_log` VALUES('239','2','Logout dari sistem','2026-04-17 10:45:00');
INSERT INTO `audit_log` VALUES('240','2','Login ke sistem','2026-04-17 10:46:27');
INSERT INTO `audit_log` VALUES('241','2','Logout dari sistem','2026-04-17 11:09:38');
INSERT INTO `audit_log` VALUES('242','1','Login ke sistem','2026-04-17 11:09:45');
INSERT INTO `audit_log` VALUES('243','1','Login ke sistem','2026-04-17 11:17:06');
INSERT INTO `audit_log` VALUES('244','1','Login ke sistem','2026-04-17 11:22:49');

DROP TABLE IF EXISTS `jaminan_kendaraan`;

CREATE TABLE `jaminan_kendaraan` (
  `id_jaminan` int NOT NULL AUTO_INCREMENT,
  `id_pengajuan` int NOT NULL,
  `merk` varchar(100) DEFAULT NULL,
  `tipe` varchar(100) DEFAULT NULL,
  `tahun_pembuatan` varchar(10) DEFAULT NULL,
  `no_polisi` varchar(20) DEFAULT NULL,
  `no_rangka` varchar(50) DEFAULT NULL,
  `no_mesin` varchar(50) DEFAULT NULL,
  `nama_pemilik` varchar(100) DEFAULT NULL,
  `nilai_pasar` decimal(15,2) DEFAULT '0.00',
  `nilai_taksasi` decimal(15,2) DEFAULT '0.00',
  `nilai_likuidasi` decimal(15,2) DEFAULT '0.00',
  PRIMARY KEY (`id_jaminan`),
  KEY `idx_jm_id_pengajuan` (`id_pengajuan`),
  CONSTRAINT `jaminan_kendaraan_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kredit` (`id_pengajuan`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `jaminan_tanah_bangunan`;

CREATE TABLE `jaminan_tanah_bangunan` (
  `id_jaminan` int NOT NULL AUTO_INCREMENT,
  `id_pengajuan` int NOT NULL,
  `alamat_agunan` text,
  `jenis_surat` varchar(50) DEFAULT 'SHM',
  `masa_covernote` date DEFAULT NULL,
  `nomor_surat` varchar(100) DEFAULT NULL,
  `atas_nama` varchar(100) DEFAULT NULL,
  `kategori_agunan` varchar(50) DEFAULT 'rumah_tinggal',
  `luas_tanah` decimal(10,2) DEFAULT '0.00',
  `luas_tanah_sppt` double DEFAULT '0',
  `harga_tanah_sppt` decimal(15,2) DEFAULT '0.00',
  `nilai_wajar_sppt` decimal(15,2) DEFAULT '0.00',
  `nilai_taksasi_sppt` decimal(15,2) DEFAULT '0.00',
  `nilai_likuidasi_sppt` decimal(15,2) DEFAULT '0.00',
  `harga_tanah_pasar` decimal(15,2) DEFAULT '0.00',
  `luas_bangunan` decimal(10,2) DEFAULT '0.00',
  `luas_bangunan_2` decimal(10,2) DEFAULT '0.00',
  `harga_bangunan_m2` decimal(15,2) DEFAULT '0.00',
  `nilai_pasar` decimal(15,2) DEFAULT '0.00',
  `nilai_taksasi` decimal(15,2) DEFAULT '0.00',
  `nilai_likuidasi` decimal(15,2) DEFAULT '0.00',
  PRIMARY KEY (`id_jaminan`),
  KEY `idx_jm_id_pengajuan` (`id_pengajuan`),
  CONSTRAINT `jaminan_tanah_bangunan_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kredit` (`id_pengajuan`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

INSERT INTO `jaminan_tanah_bangunan` VALUES('1','1','dqwdq','SHM',NULL,'121','fafa','rumah_tinggal','100.00','150','100000000.00','15000000000.00','11250000000.00','7875000000.00','100000000.00','75.00','25.00','1500000000.00','160000000000.00','120000000000.00','84000000000.00');
INSERT INTO `jaminan_tanah_bangunan` VALUES('4','4','HJKHJKHJK','SHM',NULL,'567','HGHJGHJG','rumah_tinggal','100.00','100','50000.00','5000000.00','3750000.00','2625000.00','100000.00','150.00','0.00','2000000.00','310000000.00','232500000.00','162750000.00');
INSERT INTO `jaminan_tanah_bangunan` VALUES('5','7','PERUMAHAN BDI','SHM',NULL,'6767867','GALIH PAMMBAJENG','rumah_tinggal','180.00','180','2500000.00','450000000.00','337500000.00','236250000.00','2500000.00','0.00','0.00','0.00','450000000.00','337500000.00','236250000.00');

DROP TABLE IF EXISTS `pengajuan_kredit`;

CREATE TABLE `pengajuan_kredit` (
  `id_pengajuan` int NOT NULL AUTO_INCREMENT,
  `nama_debitur` varchar(100) NOT NULL,
  `nik` varchar(20) NOT NULL,
  `npwp` varchar(50) DEFAULT NULL,
  `nib` varchar(50) DEFAULT NULL,
  `pekerjaan` varchar(100) NOT NULL,
  `id_nasabah` varchar(50) DEFAULT NULL,
  `jenis_pekerjaan` varchar(32) DEFAULT 'umum' COMMENT 'umum|pns|pppk|perangkat_desa',
  `jumlah_kredit` decimal(15,2) NOT NULL,
  `jangka_waktu` int NOT NULL,
  `tujuan_kredit` text NOT NULL,
  `status_pengajuan` enum('draft','proses','disetujui','ditolak','diajukan','kasubag','kabag','kadiv','direksi','revisi','diajukan_ulang','selesai','revisi_diajukan') DEFAULT 'draft',
  `posisi_saat_ini` varchar(100) DEFAULT 'analis',
  `tanggal_pengajuan` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `input_by` int DEFAULT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `alamat_pekerjaan` text,
  `tempat_lahir_pasangan` varchar(100) DEFAULT NULL,
  `tanggal_lahir_pasangan` date DEFAULT NULL,
  `pekerjaan_pasangan` varchar(100) DEFAULT NULL,
  `alamat_pekerjaan_pasangan` text,
  `dukuh` varchar(100) DEFAULT NULL,
  `desa` varchar(100) DEFAULT NULL,
  `kecamatan` varchar(100) DEFAULT NULL,
  `kota_kabupaten` varchar(100) DEFAULT NULL,
  `jumlah_tanggungan` int DEFAULT '0',
  `nama_ibu_kandung` varchar(100) DEFAULT NULL,
  `nama_instansi` varchar(150) DEFAULT NULL,
  `alamat_instansi` text,
  `telepon_kantor` varchar(20) DEFAULT NULL,
  `departemen_bagian` varchar(100) DEFAULT NULL,
  `jabatan` varchar(100) DEFAULT NULL,
  `pinjaman_ke` int DEFAULT '1',
  `alamat_ktp` text,
  `alamat_domisili` text,
  `no_hp` varchar(20) DEFAULT NULL,
  `status_perkawinan` enum('lajang','menikah','janda','duda') DEFAULT 'lajang',
  `nama_pasangan` varchar(100) DEFAULT NULL,
  `nama_usaha` varchar(100) DEFAULT NULL,
  `bidang_usaha` varchar(100) DEFAULT NULL,
  `lama_usaha` varchar(50) DEFAULT NULL,
  `omset_per_bulan` decimal(15,2) DEFAULT '0.00',
  `biaya_operasional` decimal(15,2) DEFAULT '0.00',
  `laba_bersih` decimal(15,2) DEFAULT '0.00',
  `repayment_capacity` decimal(15,2) DEFAULT '0.00',
  `jenis_kredit` varchar(50) DEFAULT 'KMK',
  `jenis_jaminan` varchar(50) DEFAULT 'tanah_bangunan',
  `file_pendukung` varchar(255) DEFAULT NULL,
  `file_jaminan` varchar(255) DEFAULT NULL,
  `foto_rumah` varchar(255) DEFAULT NULL,
  `foto_usaha` varchar(255) DEFAULT NULL,
  `biaya_bahan_baku` decimal(15,2) DEFAULT '0.00',
  `biaya_gaji` decimal(15,2) DEFAULT '0.00',
  `biaya_listrik` decimal(15,2) DEFAULT '0.00',
  `biaya_air` decimal(15,2) DEFAULT '0.00',
  `biaya_sewa` decimal(15,2) DEFAULT '0.00',
  `biaya_transportasi` decimal(15,2) DEFAULT '0.00',
  `biaya_lainnya` decimal(15,2) DEFAULT '0.00',
  `penyusutan` decimal(15,2) DEFAULT '0.00',
  `cashflow_usaha` decimal(15,2) DEFAULT '0.00',
  `biaya_hidup` decimal(15,2) DEFAULT '0.00',
  `cicilan_lain` decimal(15,2) DEFAULT '0.00',
  `total_pengeluaran_tetap` decimal(15,2) DEFAULT '0.00',
  `net_cashflow` decimal(15,2) DEFAULT '0.00',
  `angsuran_diajukan` decimal(15,2) DEFAULT '0.00',
  `status_kelayakan` varchar(50) DEFAULT '',
  `suku_bunga` decimal(5,2) DEFAULT '0.00',
  `grace_period` int DEFAULT '0',
  `jangka_tempo` int DEFAULT '1',
  `revision_count` int NOT NULL DEFAULT '0',
  `last_revision_at` timestamp NULL DEFAULT NULL,
  `last_revision_by` int DEFAULT NULL,
  `last_reject_level` varchar(50) DEFAULT NULL,
  `revisi_dari_role` varchar(100) DEFAULT NULL,
  `catatan_revisi` text,
  `ditolak_dari_role` varchar(100) DEFAULT NULL,
  `alasan_penolakan` text,
  `last_position_role` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_pengajuan`),
  KEY `idx_pk_posisi_status_tgl` (`posisi_saat_ini`,`status_pengajuan`,`tanggal_pengajuan`),
  KEY `idx_pk_input_tgl` (`input_by`,`tanggal_pengajuan`),
  KEY `idx_pk_status_tgl` (`status_pengajuan`,`tanggal_pengajuan`),
  CONSTRAINT `pengajuan_kredit_ibfk_1` FOREIGN KEY (`input_by`) REFERENCES `users` (`id_user`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `pengajuan_kredit` VALUES('1','GAGA','3307092809930002',NULL,NULL,'GURU',NULL,'umum','0.00','0','','draft','analis','2026-04-02 11:02:39','2','WONOSOBO','1999-06-10','BUNTU',NULL,NULL,NULL,NULL,'BUNTU','BUNTU','KEJAJAJR','WONOSOBO','2','GUGU','SMK NU KEJAJAR','HDAKHKAD','01818181','SMK','GURU','1','BUNTU','BUNTU','01817271012312','lajang','-','','','','0.00','0.00','0.00','0.00','KMK','tanah_bangunan','',NULL,NULL,NULL,'0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','','0.00','0','1','0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `pengajuan_kredit` VALUES('2','AANG','3307031898773333',NULL,NULL,'PEDAGANG',NULL,'umum','0.00','0','','draft','analis','2026-04-02 20:07:02','2','WONOSOBO','1965-01-19','WONOSOBO',NULL,NULL,NULL,NULL,'GJHGFHGHJ','HGHJGHJGJH','HGHGJHGJ','HJGHJGHJG','2','JKHJKHJKH','JHJHKHJK','JHJKHJKHJK','jhjkhkj','JKHJKHJKH','JKHJKHKJ','1','HGHJGHJGHJGF','JHJKGHJGHJ','98097878979798','lajang','-','','','','0.00','0.00','0.00','0.00','KMK','tanah_bangunan','',NULL,NULL,NULL,'0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','','0.00','0','1','0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `pengajuan_kredit` VALUES('3','ADRIAN WAHYU PRATAMA','3307031907920005',NULL,NULL,'GURU',NULL,'pppk','20000000.00','12','INVESTASI PEMBELIAN TANAH','disetujui','selesai','2026-04-03 21:42:28','2','CILACAP','1992-07-19','SD NEGERI 1 SAPURAN','WONOSOBO','1991-08-22','GURU','KEJAJAR','JARAKSARI','JARAKSARI','WONOSOBO','WONOSOBO','1','NURHAYATI','','','','','','1','JARAKSARI','JARAKSARI 005/002 JARAKSARI WONOSOBO','081316048174','menikah','VIDYA JATI NINGRUM','PPPK','11/12/13','31 DES 2025','3150000.00','0.00','3150000.00','2362500.00','KI','tanah_bangunan','file_pendukung_69cfd1d414f51.png',NULL,NULL,NULL,'0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','3150000.00','0.00','0.00','0.00','3150000.00','1816667.00','LAYAK','9.00','0','1','0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `pengajuan_kredit` VALUES('4','ADRIAN WAHYU PRATAMA','3307031907920005',NULL,NULL,'WIRASWASTA',NULL,'umum','50000000.00','24','TAMBAH BARANG','ditolak','selesai','2026-04-03 22:06:25','2','CILACAP','1992-07-19','WONOSOBO','WONOSOBO','1991-08-22','GURU','KEJAJAR','JARAKSARI','JARAKSARI','WONOSOBO','WONOSOBO','1','NURHAYATI','J&T KALIWIRO','KALIWIRO','-','JASA','PEMILIK','1','JARAKSARI 005/002 JARAKSARI WONOSOBO','-','081316048174','menikah','VIDYA JATI NINGRUM','DAGANG LANCAR','PERDAGANGAN','2 TAHUN','5000000000.00','900000000.00','4100000000.00','2927270025.00','KMK','tanah_bangunan','file_pendukung_69cfd77161763.png',NULL,NULL,NULL,'500000000.00','200000000.00','200000000.00','0.00','0.00','0.00','0.00','0.00','4100000000.00','100000000.00','96973300.00','196973300.00','3903026700.00','2708333.00','LAYAK','15.00','0','1','0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `pengajuan_kredit` VALUES('7','GALIH PAMBAJENG','3307098779878776',NULL,NULL,'KARYAWAN BUMD',NULL,'umum','0.00','0','','disetujui','selesai','2026-04-06 10:31:44','2','PURWOREJO','1987-04-20','WONOSOBO','PURWOREJO','1989-07-01','ASN','PURWOREJO','KRASAK','KRASAK','MOJOTENGAH','WONOSOBO','3','PRAPTINING DYAH','PT. BPR BANK WONOSOBO (PERSERODA)','JL. A YANI 160 WONOSOBO','0286321293','PERBANKAN','DIREKTUR UTAMA','1','PERUMAHAN BDI','PERUMAHAN BDI','082326064789','menikah','WACHID','','','','0.00','0.00','0.00','0.00','KMK','tanah_bangunan','file_pendukung_69d32920d8657.jpeg',NULL,NULL,'foto_usaha_69d32b776856a.jpg','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','','0.00','0','1','0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `pengajuan_kredit` VALUES('8','ADRIAN PRATAMA','1928292100101829',NULL,NULL,'PPPK',NULL,'pppk','0.00','0','','draft','analis','2026-04-13 11:46:09','2','WONOSOBO','1994-03-02','KJNK',NULL,NULL,NULL,NULL,'SKS','SKSS','SLS','LSLS','2','SSOPS','','','','','','1','BJS','OSSOS','992222377389','lajang','-','','','','0.00','0.00','0.00','0.00','KMK','tanah_bangunan','',NULL,NULL,NULL,'0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','','0.00','0','1','0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `pengajuan_kredit` VALUES('10','BUDI PPPK','1234567890123456','','','','12345','pppk','0.00','0','','draft','analis','2026-04-14 09:57:26','2','WONOSOBO',NULL,'',NULL,NULL,NULL,NULL,'','','','','0','','','','','','','1','JL. RAYA WONOSOBO','-','081234567890','lajang','-','','','','0.00','0.00','0.00','0.00','KMK','tanah_bangunan','',NULL,NULL,NULL,'0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','0.00','','0.00','0','1','0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id_user` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Superadmin','analis','kabag_analis','kabag_kredit','kadiv_kredit','direksi','kepatuhan') DEFAULT NULL,
  `status_jabatan` enum('aktif','sakit','izin','cuti','berhalangan') DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_users_role_jabatan` (`role`,`status_jabatan`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `users` VALUES('1','Super Admin','admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Superadmin','aktif','2026-03-04 13:44:02');
INSERT INTO `users` VALUES('2','Adrian Analis','analis','$2y$10$77ElhGVtlxN6f37XQsfFYe7zXYZz4cEKIWAZywkTLAHKwLkFcfCqa','analis','aktif','2026-03-04 13:44:02');
INSERT INTO `users` VALUES('3','Eko Agus Muharom','kasubag_analis','$2y$10$Kvev5zDsiHmruffhQD3nuueVvypsiZA57ChpEwHykrknhTj7kPyAS','kabag_analis','aktif','2026-03-04 13:44:02');
INSERT INTO `users` VALUES('4','Cahyo Nugroho','kabag_kredit','$2y$10$d9ahsg25mzPK0S7UeUqvceZFxxRCLLIiTPW4ay8/pHFIZJuoHWeMy','kabag_kredit','aktif','2026-03-04 13:44:02');
INSERT INTO `users` VALUES('5','Aang Kunaefi Usman','kadiv_Bisnis','$2y$10$koeWVOECMLPkIbuLrrJVS.35pAgTsnHpmtS60nh88zKAnwHl5xgqC','kadiv_kredit','aktif','2026-03-04 13:44:02');
INSERT INTO `users` VALUES('6','Galih Pambajeng, S.AK','Direktur Utama','$2y$10$9vRfZt3/G9Z0HjC1oYN0petzbpYGwbmk25cn8m9wP13gn9acpNzlW','direksi','aktif','2026-03-04 13:44:02');
INSERT INTO `users` VALUES('7','Pejabat Kepatuhan','kepatuhan','$2y$10$WDTZ6iliXGRQddcCwUZXWeNcM1X5FCVWZvsMZBRAbZEnxrFHyeW3u','kepatuhan','aktif','2026-04-14 13:03:06');

SET FOREIGN_KEY_CHECKS=1;
