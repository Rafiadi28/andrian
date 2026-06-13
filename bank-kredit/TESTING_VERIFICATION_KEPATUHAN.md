# 🧪 TESTING & VERIFICATION QUERIES

## ✅ PRE-TEST: Verify Database Schema

### 1. Check status_pengajuan ENUM includes 'kepatuhan'
```sql
SHOW COLUMNS FROM pengajuan_kredit LIKE 'status_pengajuan';
-- Expected Type: enum('draft','diajukan','kepatuhan','kasubag','kabag','kadiv','direksi','revisi',...)
```

### 2. Check level_approval ENUM includes 'kepatuhan'
```sql
SHOW COLUMNS FROM approval_kredit LIKE 'level_approval';
-- Expected Type: enum('analis','kepatuhan','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama')
```

### 3. Verify pengajuan_kredit table structure
```sql
DESCRIBE pengajuan_kredit;
-- Check: posisi_saat_ini is VARCHAR(100), not ENUM
-- Check: status_pengajuan includes 'kepatuhan'
```

---

## 🧪 TEST SCENARIO 1: Auto-Route Analis → Kepatuhan

### Test Data
```sql
-- Create test user: Kepatuhan staff
INSERT INTO users (nama, username, password, role, status_jabatan) 
VALUES ('Staff Kepatuhan', 'kepatuhan1', MD5('pass123'), 'kepatuhan', 'aktif');

-- Create test pengajuan via Analis
INSERT INTO pengajuan_kredit (
    nama_debitur, nik, pekerjaan, jenis_kredit, jumlah_kredit, 
    jangka_waktu, tujuan_kredit, status_pengajuan, posisi_saat_ini, input_by
) VALUES (
    'Test Debitur', '1234567890123456', 'Karyawan', 'KMK', 100000000,
    12, 'Modal Usaha', 'draft', 'analis', 1
);

-- Get the ID
SELECT @test_id := id_pengajuan FROM pengajuan_kredit WHERE nama_debitur = 'Test Debitur' LIMIT 1;
```

### Simulate Analis Submit
```php
// In analis/save_section.php (simulate case 'submit')
$id_pengajuan = @test_id;
$nextStep = findNextTarget('analis', $pdo, 100000000);
// Should return: ['role' => 'kepatuhan', 'skipped' => []]
```

### Verify Result After Submit
```sql
SELECT id_pengajuan, status_pengajuan, posisi_saat_ini 
FROM pengajuan_kredit 
WHERE id_pengajuan = @test_id;

-- Expected:
-- status_pengajuan: 'kepatuhan'
-- posisi_saat_ini: 'kepatuhan'
```

### Verify Approval Record Created
```sql
SELECT id_approval, level_approval, keputusan, catatan 
FROM approval_kredit 
WHERE id_pengajuan = @test_id AND level_approval = 'analis';

-- Expected: 1 record with keputusan = 'setuju'
```

---

## 🧪 TEST SCENARIO 2: Kepatuhan Queue Display

### Check Pengajuan di Antrian Kepatuhan
```sql
SELECT 
    pk.id_pengajuan,
    pk.nama_debitur,
    pk.jumlah_kredit,
    pk.posisi_saat_ini,
    pk.status_pengajuan,
    COUNT(ak.id_approval) as approval_count
FROM pengajuan_kredit pk
LEFT JOIN approval_kredit ak ON pk.id_pengajuan = ak.id_pengajuan
WHERE pk.posisi_saat_ini = 'kepatuhan'
  AND pk.status_pengajuan IN ('kepatuhan', 'proses')
GROUP BY pk.id_pengajuan
ORDER BY pk.tanggal_pengajuan DESC;

-- Expected: Should show test pengajuan
```

### Check Compliance Status (before assessment)
```sql
SELECT 
    pk.id_pengajuan,
    pk.nama_debitur,
    CASE 
        WHEN ak.id_assessment IS NOT NULL 
          AND ak.checklist_data IS NOT NULL 
          AND ak.kesimpulan IS NOT NULL 
        THEN 'LENGKAP'
        WHEN ak.id_assessment IS NOT NULL 
        THEN 'PARTIAL'
        ELSE 'TIDAK_ADA'
    END as compliance_status
FROM pengajuan_kredit pk
LEFT JOIN assessment_kepatuhan ak ON pk.id_pengajuan = ak.id_pengajuan
WHERE pk.id_pengajuan = @test_id;

-- Expected: compliance_status = 'TIDAK_ADA' (no assessment yet)
```

---

## 🧪 TEST SCENARIO 3: Kepatuhan Submit Assessment & Auto-Route

### Simulate Kepatuhan Assessment Submission
```sql
-- Insert assessment record (simulate form submission)
INSERT INTO assessment_kepatuhan (
    id_pengajuan,
    id_user,
    checklist_data,
    kesesuaian_dokumen,
    kelengkapan_dokumen,
    kualitas_dokumen,
    kesesuaian_nasabah,
    status_kepegawaian,
    dampak_risiko,
    mitigasi_risiko,
    kesimpulan,
    rekomendasi
) VALUES (
    @test_id,
    2,  -- kepatuhan user id
    '["item1","item2","item3"]',
    'sesuai',
    'lengkap',
    'baik',
    'sesuai',
    'aktif',
    'rendah',
    'monitoring',
    'Pengajuan memenuhi standar kepatuhan',
    'Direkomendasikan untuk dilanjutkan'
);

-- Check: assessment_kepatuhan record created
SELECT * FROM assessment_kepatuhan WHERE id_pengajuan = @test_id;
```

### Simulate Auto-Route to Kasubag (via API submit)
```php
// api/save_assessment_kepatuhan.php should:
// 1. INSERT assessment_kepatuhan record
// 2. INSERT approval_kredit with level='kepatuhan', keputusan='setuju'
// 3. AUTO-ROUTE: UPDATE pengajuan_kredit
//    - status_pengajuan = 'diajukan'
//    - posisi_saat_ini = 'kasubag_analis'
```

### Verify Auto-Route Result
```sql
SELECT 
    id_pengajuan,
    status_pengajuan,
    posisi_saat_ini,
    (SELECT COUNT(*) FROM approval_kredit WHERE id_pengajuan = @test_id AND level_approval = 'kepatuhan') as kepatuhan_approval_count
FROM pengajuan_kredit 
WHERE id_pengajuan = @test_id;

-- Expected:
-- status_pengajuan: 'diajukan'
-- posisi_saat_ini: 'kasubag_analis'
-- kepatuhan_approval_count: 1
```

---

## 🧪 TEST SCENARIO 4: Kasubag Queue with Compliance ✓

### Check Kasubag Queue
```sql
SELECT 
    pk.id_pengajuan,
    pk.nama_debitur,
    pk.jumlah_kredit,
    pk.posisi_saat_ini,
    CASE 
        WHEN ak.id_assessment IS NOT NULL 
          AND ak.checklist_data IS NOT NULL 
          AND ak.kesimpulan IS NOT NULL 
        THEN 'LENGKAP ✓'
        ELSE 'TIDAK_ADA ✗'
    END as compliance_badge
FROM pengajuan_kredit pk
LEFT JOIN assessment_kepatuhan ak ON pk.id_pengajuan = ak.id_pengajuan
WHERE pk.posisi_saat_ini = 'kasubag_analis'
  AND pk.status_pengajuan IN ('proses', 'diajukan', 'kasubag');

-- Expected: Should show test pengajuan with compliance_badge = 'LENGKAP ✓'
```

### Verify UI Should Display "Proses" Button Enabled
```php
// In kasubag_analis/proses.php (includes/proses_template.php):
// - Compliance status = 'lengkap'
// - $is_compliance_blocked = false
// - Button "Proses" should be ENABLED
// - Row background should be normal (not light red)
```

---

## 🧪 TEST SCENARIO 5: Nominal Logic - Less than 500 Million

### Create Test Pengajuan < 500 Juta
```sql
-- Rp 100 Juta
INSERT INTO pengajuan_kredit (
    nama_debitur, nik, pekerjaan, jenis_kredit, jumlah_kredit, 
    jangka_waktu, tujuan_kredit, status_pengajuan, posisi_saat_ini, input_by
) VALUES (
    'Test Kecil', '1234567890123457', 'Karyawan', 'KMK', 100000000,
    12, 'Modal', 'draft', 'analis', 1
);

SELECT @test_small := id_pengajuan FROM pengajuan_kredit WHERE nama_debitur = 'Test Kecil' LIMIT 1;
```

### Simulate Full Approval Chain (< 500M)
```sql
-- Simulate: Kepatuhan → Kasubag → Kabag → Kadiv (STOP HERE)
-- At Kadiv level with SETUJU:
-- Expected result: status='disetujui', posisi='selesai' (NO direktur)

-- Query to verify:
SELECT 
    pk.id_pengajuan,
    pk.jumlah_kredit,
    pk.status_pengajuan,
    pk.posisi_saat_ini,
    GROUP_CONCAT(ak.level_approval ORDER BY ak.id_approval) as approval_chain
FROM pengajuan_kredit pk
LEFT JOIN approval_kredit ak ON pk.id_pengajuan = ak.id_pengajuan
WHERE pk.id_pengajuan = @test_small
GROUP BY pk.id_pengajuan;

-- Expected approval_chain: 
-- analis,kepatuhan,kasubag_analis,kabag_kredit,kadiv_bisnis (STOPS HERE)
-- status='disetujui', posisi='selesai'
```

---

## 🧪 TEST SCENARIO 6: Nominal Logic - Greater than/Equal 500 Million

### Create Test Pengajuan >= 500 Juta
```sql
-- Rp 600 Juta
INSERT INTO pengajuan_kredit (
    nama_debitur, nik, pekerjaan, jenis_kredit, jumlah_kredit, 
    jangka_waktu, tujuan_kredit, status_pengajuan, posisi_saat_ini, input_by
) VALUES (
    'Test Besar', '1234567890123458', 'Karyawan', 'KMK', 600000000,
    12, 'Modal', 'draft', 'analis', 1
);

SELECT @test_big := id_pengajuan FROM pengajuan_kredit WHERE nama_debitur = 'Test Besar' LIMIT 1;
```

### Simulate Full Approval Chain (>= 500M)
```sql
-- At Kadiv level with SETUJU:
-- Expected: status='direksi', posisi='direktur_utama' (CONTINUES)

-- Then at Direktur level with SETUJU:
-- Expected: status='disetujui', posisi='selesai'

-- Query to verify:
SELECT 
    pk.id_pengajuan,
    pk.jumlah_kredit,
    pk.status_pengajuan,
    pk.posisi_saat_ini,
    GROUP_CONCAT(ak.level_approval ORDER BY ak.id_approval) as approval_chain
FROM pengajuan_kredit pk
LEFT JOIN approval_kredit ak ON pk.id_pengajuan = ak.id_pengajuan
WHERE pk.id_pengajuan = @test_big
GROUP BY pk.id_pengajuan;

-- Expected approval_chain: 
-- analis,kepatuhan,kasubag_analis,kabag_kredit,kadiv_bisnis,direktur_utama
-- status='disetujui', posisi='selesai'
```

---

## 🧪 TEST SCENARIO 7: Compliance Blocking at Kasubag

### Create Test Pengajuan WITHOUT Assessment
```sql
INSERT INTO pengajuan_kredit (
    nama_debitur, nik, pekerjaan, jenis_kredit, jumlah_kredit, 
    jangka_waktu, tujuan_kredit, status_pengajuan, posisi_saat_ini, input_by
) VALUES (
    'Test No Assessment', '1234567890123459', 'Karyawan', 'KMK', 50000000,
    12, 'Modal', 'diajukan', 'kasubag_analis', 1
);

SELECT @test_no_assess := id_pengajuan FROM pengajuan_kredit WHERE nama_debitur = 'Test No Assessment' LIMIT 1;

-- Verify NO assessment record exists
SELECT COUNT(*) FROM assessment_kepatuhan WHERE id_pengajuan = @test_no_assess;
-- Expected: 0
```

### Verify Compliance Blocking Logic
```php
// In kasubag_analis/proses.php:
// Query compliance status for @test_no_assess
// $compliance_status = checkComplianceAssessmentStatus($pdo, @test_no_assess)
// Expected: $compliance_status['is_complete'] = false

// UI should show:
// - Row background: light red (#fff5f5)
// - Badge: ✗ Waiting Compliance (red)
// - Button: "Proses (Blokir)" - DISABLED
// - Alert on click: "Pengajuan ini TIDAK BISA diproses sampai..."
```

---

## 📊 FINAL VERIFICATION QUERY

### Complete Audit Trail
```sql
SELECT 
    pk.id_pengajuan,
    pk.nama_debitur,
    pk.jumlah_kredit,
    pk.status_pengajuan,
    pk.posisi_saat_ini,
    GROUP_CONCAT(
        CONCAT(
            ak.level_approval, 
            ' (', ak.keputusan, ')', 
            CASE WHEN ak.is_auto_skip = 1 THEN ' [AUTO-SKIP]' ELSE '' END
        ) 
        ORDER BY ak.id_approval 
        SEPARATOR ' → '
    ) as approval_history,
    CASE 
        WHEN ast.id_assessment IS NOT NULL THEN 'Lengkap'
        ELSE 'Belum'
    END as compliance_status
FROM pengajuan_kredit pk
LEFT JOIN approval_kredit ak ON pk.id_pengajuan = ak.id_pengajuan
LEFT JOIN assessment_kepatuhan ast ON pk.id_pengajuan = ast.id_pengajuan
WHERE pk.status_pengajuan != 'draft'
GROUP BY pk.id_pengajuan
ORDER BY pk.tanggal_pengajuan DESC;
```

---

## ✅ SUCCESS CRITERIA

- [ ] Analis submit → Auto-routes to 'kepatuhan'
- [ ] Kepatuhan sees pengajuan in queue
- [ ] Kepatuhan fills assessment & submits
- [ ] Assessment saved & auto-routes to 'kasubag_analis'
- [ ] Kasubag sees "✓ Compliance OK" badge
- [ ] Kasubag can approve (button enabled)
- [ ] Chain continues: Kabag → Kadiv
- [ ] < 500M: Stops at Kadiv (final approval)
- [ ] >= 500M: Continues to Direktur (final approval)
- [ ] Compliance blocking works (no assessment = disabled button)
- [ ] Approval audit trail complete

---

**Last Updated**: 29 May 2026  
**Status**: ✅ TEST READY
