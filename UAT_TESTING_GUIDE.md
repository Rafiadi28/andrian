# UAT TESTING GUIDE - Approval Routing & Prefill Fix

**Purpose**: Validate prefill bug fix and approval routing with cuti fallback  
**Duration**: ~30-45 minutes  
**Prerequisites**: Local database, test user accounts, sample pengajuan data

---

## PART 1: SETUP

### 1.1 Verify Database Connection
```bash
cd d:\laragon\www\andrian
php -r "try { $p = new PDO('mysql:host=localhost;dbname=bank_kredit_db;charset=utf8mb4', 'root', 'rian123'); echo 'OK'; } catch (Exception $e) { echo 'ERROR: ' . $e->getMessage(); }"
```

Expected: `OK`

### 1.2 Create Test Data
```sql
-- Insert test users with different roles if not exists
INSERT IGNORE INTO users (id_user, nama, role, status_jabatan, email, password)
VALUES 
  (100, 'Test Analis', 'analis', 'aktif', 'analis@test', MD5('pass')),
  (101, 'Test KSA', 'kasubag_analis', 'aktif', 'ksa@test', MD5('pass')),
  (102, 'Test KBK', 'kabag_kredit', 'aktif', 'kbk@test', MD5('pass')),
  (103, 'Test KDB', 'kadiv_bisnis', 'aktif', 'kdb@test', MD5('pass')),
  (104, 'Test DU', 'direktur_utama', 'aktif', 'du@test', MD5('pass'));

-- Verify
SELECT id_user, nama, role, status_jabatan FROM users WHERE role IN ('analis','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama') ORDER BY role;
```

### 1.3 Backup User Status
```sql
-- Save original status
CREATE TEMPORARY TABLE user_status_backup AS 
  SELECT id_user, role, status_jabatan FROM users WHERE role IN ('analis','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama');

-- View backup
SELECT * FROM user_status_backup;
```

---

## PART 2: PREFILL TEST

**Duration**: ~5 minutes  
**Test**: Verify perangkat desa revisi prefill

### Step 1: Create Sample Pengajuan (Perangkat Desa)
```sql
-- Insert sample pengajuan
INSERT INTO pengajuan_kredit (
  nama_debitur, jenis_kredit, jumlah_kredit, posisi_saat_ini, status_pengajuan
) VALUES (
  'Test Debitur Desa', 
  'perangkat desa',  -- ← Important: use 'perangkat desa' variant
  5000000, 
  'analis', 
  'submisi_analis'
);

-- Get ID
SELECT LAST_INSERT_ID() as id_pengajuan;
-- Expected: 1 or next available ID
```

### Step 2: Prefill Initial Data via UI
- Open app at `http://localhost/andrian/bank-kredit`
- Login as analis (id=100, pass=pass)
- Create pengajuan with jenis_kredit = 'perangkat desa'
- Fill in all desa fields:
  - Jabatan: Kepala Desa
  - Tgl Mulai: 2020-01-01
  - Penghasilan Tetap: 2000000
  - Penghasilan Lain: 1000000

### Step 3: Mark as Revisi
- Click "Buat Revisi" or "Request Revisi"
- Verify status changes to 'revisi'

### Step 4: Edit Revisi
- **CRITICAL TEST**: Click "Edit Revisi" for this perangkat desa pengajuan
- **Expected**: All desa fields should prefill with previous values
  - ✓ Jabatan: Kepala Desa
  - ✓ Tgl Mulai: 2020-01-01
  - ✓ Penghasilan Tetap: 2000000
  - ✓ Penghasilan Lain: 1000000
  - ✓ Total Penghasilan: 3000000 (auto-calc)

### Step 5: Verify Variants
Test with different case/format:
- Update jenis_kredit to 'PERANGKAT DESA' → edit → verify prefill
- Update jenis_kredit to 'perangkat_desa' → edit → verify prefill
- Update jenis_kredit to 'Perangkat Desa' → edit → verify prefill

**Expected**: All variants should prefill correctly

---

## PART 3: ROUTING TEST - ALL ACTIVE

**Duration**: ~10 minutes  
**Test**: Verify routing works when all roles are active

### Setup
```sql
-- Restore all to aktif
UPDATE users SET status_jabatan='aktif' WHERE role IN ('analis','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama');

-- Verify
SELECT role, COUNT(*) FROM users WHERE status_jabatan='aktif' AND role IN ('analis','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama') GROUP BY role;
```

Expected: All 5 roles have aktif users

### Test Flow
1. Create new pengajuan → submit to analis
2. Complete analis assessment
3. Submit kepatuhan assessment:
   - Select hasil_kepatuhan = 'COMPLY'
   - Fill checklist
   - **Click Save**

### Verification
```sql
-- Check posisi_saat_ini routed to kasubag_analis
SELECT id_pengajuan, posisi_saat_ini, status_pengajuan FROM pengajuan_kredit 
WHERE id_pengajuan = [YOUR_TEST_ID];

-- Expected: posisi_saat_ini = 'kasubag_analis'

-- Check NO auto-skip record
SELECT * FROM approval_kredit 
WHERE id_pengajuan = [YOUR_TEST_ID] AND is_auto_skip = 1;

-- Expected: 0 rows (no auto-skip when all active)
```

**Expected Status**: ✓ posisi_saat_ini = kasubag_analis, no auto-skip

---

## PART 4: ROUTING TEST - SINGLE ROLE CUTI

**Duration**: ~10 minutes  
**Test**: Verify fallback when kasubag_analis unavailable

### Setup
```sql
-- Make kasubag_analis unavailable (cuti)
UPDATE users SET status_jabatan='cuti' WHERE role='kasubag_analis';

-- Verify
SELECT role, status_jabatan FROM users WHERE role IN ('analis','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama') ORDER BY role;

-- Expected: kasubag_analis → 'cuti', others → 'aktif'
```

### Test Flow
1. Create new pengajuan
2. Complete kepatuhan assessment
3. Submit kepatuhan

### Verification
```sql
-- Check posisi_saat_ini routed to ANALIS (fallback)
SELECT id_pengajuan, posisi_saat_ini FROM pengajuan_kredit WHERE id_pengajuan = [YOUR_TEST_ID];

-- Expected: posisi_saat_ini = 'analis' (fallback from kasubag_analis)

-- Check AUTO-SKIP record was created
SELECT id_pengajuan, level_approval, keputusan, catatan, is_auto_skip FROM approval_kredit 
WHERE id_pengajuan = [YOUR_TEST_ID] AND is_auto_skip = 1;

-- Expected: 1 row with:
--   level_approval = 'kasubag_analis'
--   keputusan = 'eskalasi_otomatis'
--   catatan contains 'Auto-skip: routed to analis'
--   is_auto_skip = 1
```

**Expected Status**: ✓ Routed to analis, auto-skip recorded

---

## PART 5: ROUTING TEST - MULTIPLE ROLES CUTI

**Duration**: ~10 minutes  
**Test**: Verify chain escalation when multiple roles unavailable

### Setup
```sql
-- Make kasubag_analis, analis, and kabag_kredit unavailable
UPDATE users SET status_jabatan='cuti' WHERE role IN ('kasubag_analis', 'analis', 'kabag_kredit');

-- Verify
SELECT role, status_jabatan FROM users WHERE role IN ('analis','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama') ORDER BY role;

-- Expected: kasubag_analis, analis, kabag_kredit = 'cuti', others = 'aktif'
```

### Test Flow
1. Create new pengajuan
2. Complete kepatuhan assessment
3. Submit kepatuhan

### Verification
```sql
-- Check posisi_saat_ini routed to KADIV_BISNIS (chain escalation)
SELECT id_pengajuan, posisi_saat_ini FROM pengajuan_kredit WHERE id_pengajuan = [YOUR_TEST_ID];

-- Expected: posisi_saat_ini = 'kadiv_bisnis'

-- Check AUTO-SKIP records
SELECT level_approval, catatan, is_auto_skip FROM approval_kredit 
WHERE id_pengajuan = [YOUR_TEST_ID] AND is_auto_skip = 1
ORDER BY id_approval;

-- Expected: 2 rows (kasubag_analis and kabag_kredit both skipped)
--   Row 1: level_approval = 'kasubag_analis'
--   Row 2: level_approval = 'kabag_kredit' (from fallback kadiv_bisnis)
```

**Expected Status**: ✓ Escalated to kadiv_bisnis, 2 auto-skip records

---

## PART 6: ROUTING TEST - EXTREME SCENARIO

**Duration**: ~5 minutes  
**Test**: Verify behavior when only direktur_utama active

### Setup
```sql
-- Make ALL except direktur_utama unavailable
UPDATE users SET status_jabatan='cuti' WHERE role != 'direktur_utama' AND role IN ('analis','kasubag_analis','kabag_kredit','kadiv_bisnis');

-- Verify
SELECT role, status_jabatan FROM users WHERE role IN ('analis','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama') ORDER BY role;

-- Expected: only direktur_utama = 'aktif'
```

### Test Flow
1. Create new pengajuan
2. Complete kepatuhan
3. Submit kepatuhan

### Verification
```sql
-- Check posisi_saat_ini routed to DIREKTUR_UTAMA
SELECT id_pengajuan, posisi_saat_ini FROM pengajuan_kredit WHERE id_pengajuan = [YOUR_TEST_ID];

-- Expected: posisi_saat_ini = 'direktur_utama'

-- Check AUTO-SKIP records (should have multiple levels)
SELECT level_approval, catatan FROM approval_kredit 
WHERE id_pengajuan = [YOUR_TEST_ID] AND is_auto_skip = 1
ORDER BY id_approval;

-- Expected: Multiple auto-skip records for the chain
```

**Expected Status**: ✓ Escalated to direktur_utama, multiple auto-skip records

---

## PART 7: CLEANUP

### Restore Original Status
```sql
-- Restore all to aktif
UPDATE users SET status_jabatan='aktif' WHERE status_jabatan='cuti';

-- Verify
SELECT role, status_jabatan FROM users WHERE role IN ('analis','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama') ORDER BY role;

-- Expected: all = 'aktif'
```

---

## PART 8: SUMMARY REPORT

| Test Case | Expected | Actual | Status |
|-----------|----------|--------|--------|
| Prefill - perangkat desa | Fields populated | ? | [ ] |
| Prefill - PERANGKAT DESA | Fields populated | ? | [ ] |
| Prefill - perangkat_desa | Fields populated | ? | [ ] |
| Routing - All active | pos_saat_ini = kasubag_analis, no auto-skip | ? | [ ] |
| Routing - kasubag_analis cuti | pos_saat_ini = analis, 1 auto-skip | ? | [ ] |
| Routing - kasubag_analis + kabag_kredit cuti | pos_saat_ini = kadiv_bisnis, 2 auto-skips | ? | [ ] |
| Routing - Only direktur_utama active | pos_saat_ini = direktur_utama, multiple auto-skips | ? | [ ] |

---

## TROUBLESHOOTING

### Issue: Database Connection Failed
```
Error: "Sistem sedang mengalami gangguan koneksi database"
```
- Check MySQL is running: `tasklist | findstr mysqld`
- Check database exists: `mysql -uroot -prian123 -e "SHOW DATABASES LIKE 'bank_kredit_db'"`
- Check credentials in `config/database.php`

### Issue: Prefill Not Working
- Inspect browser console: `F12 → Console`
- Check `window.__ANALIS_PREFILL__` is defined
- Verify `jenis_kredit` contains "perangkat desa" (case-insensitive)

### Issue: Auto-Skip Not Recorded
- Check `includes/approval_routing.php` is loaded
- Check `approval_kredit` table exists: `DESC approval_kredit`
- Check `is_auto_skip` column exists

---

## TEST EVIDENCE

Save these files for documentation:
1. `test_unit_routing.php` output (should show 6/6 pass)
2. SQL queries results from each verification step
3. Screenshots of prefill working
4. Browser console logs if any errors

---

**Created**: 2026-07-25  
**Status**: Ready for UAT  
**Duration**: ~1 hour total  
**Responsible**: QA Team / Business Owner
