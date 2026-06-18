# LAPORAN HASIL TESTING PRE-RELEASE

**Tanggal:** 2026-06-18 02:33:26
**Environment:** Laragon Development
**Tester:** Automated (run_release_tests.php)

## Ringkasan

| Metrik | Nilai |
|--------|-------|
| Total PASS | 56 |
| Total FAIL | 6 |
| Total WARN | 1 |
| Status Release | **PERLU PERBAIKAN** |

## Hasil per Modul

### 1. Input Analis

| Test | Status | Detail |
|------|--------|--------|
| File analis/dashboard.php ada | PASS |  |
| File analis/input.php ada | PASS |  |
| File analis/save_section.php ada | PASS |  |
| File analis/form_umum.php ada | PASS |  |
| File analis/edit.php ada | PASS |  |

### 2-4. Approval

| Test | Status | Detail |
|------|--------|--------|
| Hierarchy mengandung kepatuhan | PASS |  |
| Threshold <500M stop di kadiv | PASS |  |
| Threshold >=500M ke direktur | PASS |  |
| Workflow simulation cleanup | WARN | SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`bank_kredit_db`.`assessment_kepatuhan`, CONSTRAINT `fk_assessment_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE RESTRICT ON UPDATE CASCADE) |

### 3. Kadiv

| Test | Status | Detail |
|------|--------|--------|
| Kadiv approve 250M → selesai (tanpa direksi) | PASS |  |
| Kadiv approve 600M → direktur_utama | PASS |  |

### 5. Kepatuhan

| Test | Status | Detail |
|------|--------|--------|
| Kabag blocked tanpa assessment kepatuhan | PASS | 🔐 Tidak dapat melanjutkan approval: Kepatuhan belum melakukan assessment untuk pengajuan ini.\n\nSilakan minta Dept. Kepatuhan untuk menyelesaikan assessment terlebih dahulu. |

### 6. Hasil Cetak

| Test | Status | Detail |
|------|--------|--------|
| print.php render | FAIL | Tidak ada pengajuan di database |

### 7. Upload Foto

| Test | Status | Detail |
|------|--------|--------|
| Direktori assets/uploads writable | PASS |  |

### 8. Repayment

| Test | Status | Detail |
|------|--------|--------|
| hitungRepayment(100M) sesuai master umum (75%) | PASS | hasil=75000000 |
| PPPK dasar=gaji_bersih dari master (95%) | PASS | hasil=9500000 |
| Parameter umum dipilih berdasarkan tanggal analisa | PASS | as_of=2020-06-01 |
| Parameter hari ini memiliki as_of_date | PASS | as_of=2026-06-18 |
| normalizeRepaymentAsOfDate() valid | PASS |  |
| hitung_repayment(5M, 2M, 0.5M) = 2.5M | PASS |  |
| klasifikasi_repayment(80%, gaji) = Layak | PASS |  |
| Override tanpa alasan ditolak | PASS |  |
| Override alasan < 10 karakter ditolak | PASS |  |
| getRepaymentOverrideInfo() override aktif | PASS |  |

### 9. Scoring 5C

| Test | Status | Detail |
|------|--------|--------|
| hitung_6c() valid input | PASS | rata=4.67 |
| klasifikasi_6c() = Sangat Baik untuk rata 4.67 | PASS |  |
| tentukan_status_kelayakan(4.67) = LAYAK | PASS |  |
| tentukan_status_kelayakan(3.5) = LAYAK_DENGAN_CATATAN | PASS |  |
| tentukan_status_kelayakan(2.0) = TIDAK_LAYAK | PASS |  |
| Validasi skor di luar 1-5 ditolak | PASS |  |

### 10. Neraca

| Test | Status | Detail |
|------|--------|--------|
| Balance equation (aktiva=pasiva) pada data existing | PASS | OK atau belum ada data |

### 11. Agunan

| Test | Status | Detail |
|------|--------|--------|
| Kolom jaminan_tanah_bangunan.alamat_agunan | PASS |  |
| Kolom jaminan_tanah_bangunan.jenis_surat | PASS |  |
| Kolom jaminan_tanah_bangunan.luas_tanah | PASS |  |
| Kolom jaminan_tanah_bangunan.nilai_taksasi | PASS |  |
| Kolom jaminan_kendaraan.merk | PASS |  |
| Kolom jaminan_kendaraan.tipe | PASS |  |
| Kolom jaminan_kendaraan.tahun_pembuatan | PASS |  |
| Kolom jaminan_kendaraan.no_polisi | PASS |  |
| Kolom jaminan_kendaraan.nilai_taksasi | PASS |  |

### Infrastructure

| Test | Status | Detail |
|------|--------|--------|
| PHP syntax check (14 files) | PASS |  |
| Database connection | PASS |  |
| Tabel users ada | PASS |  |
| Tabel pengajuan_kredit ada | PASS |  |
| Tabel analisa_5c ada | PASS |  |
| Tabel analisa_neraca ada | PASS |  |
| Tabel jaminan_tanah_bangunan ada | PASS |  |
| Tabel jaminan_kendaraan ada | PASS |  |
| Tabel agunan_foto ada | PASS |  |
| Tabel approval_kredit ada | PASS |  |
| Tabel assessment_kepatuhan ada | PASS |  |
| Tabel master_pejabat ada | PASS |  |
| Tabel audit_log ada | PASS |  |
| Tabel master_parameter_repayment ada | PASS |  |
| Tabel repayment_parameter_audit_log ada | PASS |  |
| User test: analis_test | FAIL | tidak ditemukan |
| User test: kabag_test | FAIL | tidak ditemukan |
| User test: kadiv_test | FAIL | tidak ditemukan |
| User test: direktur_test | FAIL | tidak ditemukan |
| User test: kepatuhan_test | FAIL | tidak ditemukan |

## Bug Ditemukan

### BUG #1
- **Modul:** Infrastructure
- **Test:** User test: analis_test
- **Severity:** medium
- **Detail:** tidak ditemukan

### BUG #2
- **Modul:** Infrastructure
- **Test:** User test: kabag_test
- **Severity:** medium
- **Detail:** tidak ditemukan

### BUG #3
- **Modul:** Infrastructure
- **Test:** User test: kadiv_test
- **Severity:** medium
- **Detail:** tidak ditemukan

### BUG #4
- **Modul:** Infrastructure
- **Test:** User test: direktur_test
- **Severity:** medium
- **Detail:** tidak ditemukan

### BUG #5
- **Modul:** Infrastructure
- **Test:** User test: kepatuhan_test
- **Severity:** medium
- **Detail:** tidak ditemukan

### BUG #6
- **Modul:** 6. Hasil Cetak
- **Test:** print.php render
- **Severity:** medium
- **Detail:** Tidak ada pengajuan di database

## Rekomendasi

- Perbaiki bug yang tercatat sebelum release production.
- Jalankan `php insert_test_data.php` jika data test belum lengkap.
- UAT manual: ikuti TESTING_CHECKLIST.md untuk verifikasi UI/foto upload.

---
*Generated by run_release_tests.php*
