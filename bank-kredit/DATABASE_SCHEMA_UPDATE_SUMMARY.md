# 📋 DATABASE SCHEMA UPDATE - KEPATUHAN INTEGRATION

## ✅ COMPLETED UPDATES

### 1. database.sql - Status Pengajuan ENUM
**File**: [bank-kredit/database.sql](bank-kredit/database.sql)  
**Line**: 111

**Before**:
```sql
status_pengajuan ENUM(
    'draft','diajukan','kasubag','kabag','kadiv','direksi',
    'revisi','revisi_diajukan','ditolak','disetujui','proses',
    'diajukan_ulang','selesai'
) DEFAULT 'draft',
```

**After**:
```sql
status_pengajuan ENUM(
    'draft','diajukan','kepatuhan','kasubag','kabag','kadiv','direksi',
    'revisi','revisi_diajukan','ditolak','disetujui','proses',
    'diajukan_ulang','selesai'
) DEFAULT 'draft',
```

**Changes**:
- ✅ Added 'kepatuhan' status after 'diajukan'
- This allows pengajuan.status_pengajuan = 'kepatuhan'

---

### 2. database.sql - Level Approval ENUM  
**File**: [bank-kredit/database.sql](bank-kredit/database.sql)  
**Line**: 145

**Before**:
```sql
level_approval ENUM('analis','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama') NOT NULL,
```

**After**:
```sql
level_approval ENUM('analis','kepatuhan','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama') NOT NULL,
```

**Changes**:
- ✅ Added 'kepatuhan' level after 'analis'
- This allows approval_kredit.level_approval = 'kepatuhan'

---

### 3. schema_realtime_migrate.php - Desired Status Values
**File**: [bank-kredit/includes/schema_realtime_migrate.php](bank-kredit/includes/schema_realtime_migrate.php)  
**Line**: 59-73

**Before**:
```php
$desiredEnumStatuses = [
    'draft',
    'diajukan',
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
```

**After**:
```php
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
```

**Changes**:
- ✅ Added 'kepatuhan' to auto-migration array
- Migration script will auto-add 'kepatuhan' to database on next execution

---

### 4. schema_realtime_migrate.php - Desired Level Values
**File**: [bank-kredit/includes/schema_realtime_migrate.php](bank-kredit/includes/schema_realtime_migrate.php)  
**Line**: 144-151

**Before**:
```php
$desiredLevelValues = [
    'analis',
    'kasubag_analis',
    'kabag_kredit',
    'kadiv_bisnis',
    'direktur_utama',
];
```

**After**:
```php
$desiredLevelValues = [
    'analis',
    'kepatuhan',
    'kasubag_analis',
    'kabag_kredit',
    'kadiv_bisnis',
    'direktur_utama',
];
```

**Changes**:
- ✅ Added 'kepatuhan' to auto-migration array
- Migration script will auto-add 'kepatuhan' to database on next execution

---

## 🔄 HOW THE MIGRATION WORKS

When `schema_realtime_migrate.php` is executed (usually on application startup):

1. **Check Current Status**: Reads current ENUM from database
2. **Compare with Desired**: Compares with $desiredEnumStatuses array
3. **If Missing**: Auto-adds missing values (including 'kepatuhan')
4. **Execute ALTER**: Runs `ALTER TABLE` to update ENUM definition
5. **Safe Migration**: Uses temporary ENUM to prevent data loss

**Migration Code** (lines 144-196):
```php
// Step A: Create temp ENUM with all values (old + new + desired)
$tempEnum = "ENUM('" . implode("','", $allEnumValues) . "')";
$pdo->exec("ALTER TABLE approval_kredit MODIFY COLUMN level_approval {$tempEnum} NOT NULL");

// Step B: Migrate data (rename old role names to new names)
$pdo->exec("UPDATE approval_kredit SET level_approval = 'kepatuhan' WHERE level_approval = '...'");

// Step C: Set final ENUM with only desired values
$newLevelEnum = "ENUM('" . implode("','", $desiredLevelValues) . "')";
$pdo->exec("ALTER TABLE approval_kredit MODIFY COLUMN level_approval {$newLevelEnum} NOT NULL");
```

---

## 🚀 DEPLOYMENT INSTRUCTIONS

### Option 1: Auto-Migration (Recommended)
```bash
# Just access the application - migration runs automatically on startup
# No manual SQL needed
curl http://localhost/andrian/bank-kredit/index.php
# OR
php -S localhost:8000 -t /path/to/bank-kredit
```

### Option 2: Manual MySQL Migration
```sql
-- Login to MySQL
mysql -u root -p

-- Use database
USE bank_kredit_db;

-- Option 2a: Update status_pengajuan if not already updated
ALTER TABLE pengajuan_kredit 
MODIFY COLUMN status_pengajuan ENUM(
    'draft','diajukan','kepatuhan','kasubag','kabag','kadiv','direksi',
    'revisi','revisi_diajukan','ditolak','disetujui','proses',
    'diajukan_ulang','selesai'
) DEFAULT 'draft';

-- Option 2b: Update level_approval if not already updated
ALTER TABLE approval_kredit 
MODIFY COLUMN level_approval ENUM(
    'analis','kepatuhan','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama'
) NOT NULL;

-- Verify updates
SHOW COLUMNS FROM pengajuan_kredit LIKE 'status_pengajuan';
SHOW COLUMNS FROM approval_kredit LIKE 'level_approval';
```

---

## 📊 VERIFICATION QUERIES

### Check Current ENUM Values
```sql
-- Check status_pengajuan ENUM
SHOW COLUMNS FROM pengajuan_kredit LIKE 'status_pengajuan';
-- Type should include 'kepatuhan'

-- Check level_approval ENUM
SHOW COLUMNS FROM approval_kredit LIKE 'level_approval';
-- Type should include 'kepatuhan'
```

### Verify Schema Matches Code
```sql
-- All ENUMs should have these values in order:
-- approval_kredit.level_approval: 
--   'analis','kepatuhan','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama'

-- pengajuan_kredit.status_pengajuan:
--   'draft','diajukan','kepatuhan','kasubag','kabag','kadiv','direksi','revisi',...

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME IN ('pengajuan_kredit', 'approval_kredit')
  AND COLUMN_NAME IN ('status_pengajuan', 'level_approval');
```

---

## 🔗 RELATED CODE CHANGES

These database updates support the following PHP code changes:

### functions.php - Updated
- ✅ Line 383: getHierarchy() now includes 'kepatuhan'
- ✅ Line 560: statusPengajuanForPipelinePosition() maps 'kepatuhan' status
- ✅ Line 574: pengajuanStatusesActivePipeline() includes 'kepatuhan'
- ✅ Line 410: canEditPengajuan() allows 'kepatuhan' role

### navbar.php - Updated
- ✅ Admin menu: Added link to kepatuhan/proses.php
- ✅ Kepatuhan role: Added Antrian Assessment link

### kepatuhan/proses.php - Created
- ✅ New file to display kepatuhan approval queue

### includes/proses_template.php - Already Has Logic
- ✅ Compliance blocking check (lines 87-97)
- ✅ UI badges for compliance status
- ✅ Auto-detection for kepatuhan role

---

## ⚠️ IMPORTANT NOTES

1. **No Data Loss**: Old pengajuan and approval records are safe
   - Existing records will continue to work
   - They just won't have 'kepatuhan' status (stays at current position)

2. **Auto-Routing Active**: After migration, new pengajuan will:
   - Submit from Analis → Auto-route to Kepatuhan
   - After Kepatuhan assessment → Auto-route to Kasubag Analis
   - Continue through: Kabag → Kadiv → (Direktur if >= 500M)

3. **Compliance Blocking Active**: Kasubag onwards cannot approve until:
   - assessment_kepatuhan record exists
   - checklist_data is filled
   - kesimpulan is provided

4. **Backwards Compatible**: System works with and without kepatuhan:
   - Old workflows still function
   - Inactive kepatuhan = auto-skipped
   - No breaking changes to existing code

---

## 📝 DEPLOYMENT CHECKLIST

- [ ] Review database.sql changes (lines 111, 145)
- [ ] Review schema_realtime_migrate.php changes (lines 59-73, 144-151)
- [ ] Backup production database BEFORE deploying
- [ ] Deploy code to production
- [ ] Access application to trigger auto-migration (or run manual SQL)
- [ ] Verify ENUM values in database match expected values
- [ ] Test: Create new pengajuan and verify auto-routes to 'kepatuhan'
- [ ] Test: Kepatuhan submits assessment and verify auto-routes to kasubag
- [ ] Test: Compliance blocking works (button disabled when assessment incomplete)
- [ ] Test: Nominal logic works (< 500M stops at Kadiv, >= 500M goes to Direktur)
- [ ] Monitor application logs for any errors

---

**Last Updated**: 29 May 2026  
**Migration Status**: ✅ READY FOR DEPLOYMENT  
**Testing**: See [TESTING_VERIFICATION_KEPATUHAN.md](TESTING_VERIFICATION_KEPATUHAN.md)
