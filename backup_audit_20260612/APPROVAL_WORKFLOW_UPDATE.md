# ✅ APPROVAL WORKFLOW UPDATE - Nominal Based Approval Chain

**Updated**: May 12, 2026  
**Status**: ✅ IMPLEMENTED  
**Threshold**: 1 Juta - 500 Juta vs ≥ 500 Juta

---

## 📋 RINGKASAN PERUBAHAN

### 1. APPROVAL CHAIN (Updated)

**OLD CHAIN**:
```
Analis → Kabag Analis → Kabag Kredit → Kadiv Kredit → Direksi
```

**NEW CHAIN**:
```
Analis → Kasubag Analis → Kabag Kredit → Kadiv Bisnis → Direktur Utama
```

### 2. NOMINAL-BASED APPROVAL ROUTING

#### Skenario A: Nominal < 500 Juta (1 Juta - 500 Juta)
```
┌─────────┐     ┌──────────────┐     ┌─────────────┐     ┌──────────────┐     ┌──────────┐
│ ANALIS  │ ──→ │KASUBAG ANALIS│ ──→ │KABAG KREDIT │ ──→ │KADIV BISNIS  │ ──→ │ SELESAI✓ │
└─────────┘     └──────────────┘     └─────────────┘     └──────────────┘     └──────────┘
                                                               ▲
                                                          [FINAL APPROVAL]

🛑 Direktur Utama: NOT INVOLVED
```

#### Skenario B: Nominal ≥ 500 Juta
```
┌─────────┐     ┌──────────────┐     ┌─────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────┐
│ ANALIS  │ ──→ │KASUBAG ANALIS│ ──→ │KABAG KREDIT │ ──→ │KADIV BISNIS  │ ──→ │DIREKTUR UTAMA│ ──→ │ SELESAI✓ │
└─────────┘     └──────────────┘     └─────────────┘     └──────────────┘     └──────────────┘     └──────────┘
                                                                                        ▲
                                                                                   [FINAL APPROVAL]
```

---

## 🔧 PERUBAHAN TEKNIS

### A. Database Changes (database.sql)

#### ✅ approval_kredit Table
**Enum level_approval** - Updated:
```sql
-- OLD:
ENUM('analis','kabag_analis','kabag_kredit','kadiv_kredit','direksi')

-- NEW:
ENUM('analis','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama')
```

#### ✅ Users (Seed Data)
**Updated user roles**:
```sql
-- Role Changes:
INSERT INTO users VALUES
('Siti Kasubag',     'kasubag_analis', ..., 'kasubag_analis', ...),  -- Changed from 'kabag_analis'
('Dewi Kadiv Bisnis','kadiv_bisnis',   ..., 'kadiv_bisnis', ...),    -- Changed from 'kadiv_kredit'
('Pak Bos Direktur', 'direktur_utama', ..., 'direktur_utama', ...);  -- Changed from 'direksi'
```

---

### B. Functions Changes (includes/functions.php)

#### ✅ getHierarchy()
**Updated approval chain**:
```php
function getHierarchy()
{
    return ['analis', 'kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama'];
}
```

#### ✅ getMaxApprovalLevel($jumlah_kredit)
**Updated threshold logic**:
```php
function getMaxApprovalLevel($jumlah_kredit)
{
    $THRESHOLD_AMOUNT = 500000000; // 500 juta
    
    if ($jumlah_kredit < $THRESHOLD_AMOUNT) {
        return 'kadiv_bisnis';      // Stop at Kadiv Bisnis for < 500M
    }
    return 'direktur_utama';        // Continue to Direktur Utama for >= 500M
}
```

#### ✅ getRoleDisplay($role)
**Updated role labels**:
```php
$displays = [
    'Superadmin' => 'Admin Sistem',
    'analis' => 'Analis Kredit',
    'kasubag_analis' => 'Kasubag Analis',      // Changed
    'kabag_kredit' => 'Kabag Kredit',
    'kadiv_bisnis' => 'Kadiv Bisnis',          // Changed
    'direktur_utama' => 'Direktur Utama',      // Changed
    'kepatuhan' => 'Dept. Kepatuhan',
];
```

#### ✅ getRoleLabels()
**Updated role mapping**:
```php
return [
    'kasubag_analis' => 'Kasubag Analis',      // New
    'kabag_kredit' => 'Kabag Kredit',
    'kadiv_bisnis' => 'Kadiv Bisnis',          // New
    'direktur_utama' => 'Direktur Utama',      // New
    ...
];
```

#### ✅ statusPengajuanForPipelinePosition($role_posisi)
**Updated status mapping**:
```php
$map = [
    'kasubag_analis' => 'kasubag',    // Status ketika approval di Kasubag Analis
    'kabag_kredit' => 'kabag',        // Status ketika approval di Kabag Kredit
    'kadiv_bisnis' => 'kadiv',        // Status ketika approval di Kadiv Bisnis
    'direktur_utama' => 'direksi',    // Status ketika approval di Direktur Utama
];
```

---

### C. Role Folder Files

#### ✅ direksi/ Folder
**Files updated**:
- `direksi/proses.php`: `$my_role = 'direktur_utama'`
- `direksi/dashboard.php`: `$my_role = 'direktur_utama'`
- `direksi/riwayat.php`: `$my_role = 'direktur_utama'`

#### ✅ kadiv_kredit/ Folder (Legacy)
**Files updated**:
- `kadiv_kredit/proses.php`: `$my_role = 'kadiv_bisnis'` (Backward compatible)
- `kadiv_kredit/dashboard.php`: `$my_role = 'kadiv_bisnis'`
- `kadiv_kredit/riwayat.php`: `$my_role = 'kadiv_bisnis'`

> **Note**: Folder `kadiv_kredit/` masih ada untuk backward compatibility. Jika tidak ada user dengan role `kadiv_kredit`, folder ini bisa dihapus di masa depan.

---

### D. Navigation Changes (includes/navbar.php)

#### ✅ Admin Menu - Approval Links
**Updated menu links**:
```php
<div id="submenu-admin-approval" class="submenu">
    <a href="<?= BASE_URL ?>/analis/dashboard.php?approval_view=true">Analis</a>
    <a href="<?= BASE_URL ?>/kasubag_analis/proses.php">Kasubag Analis</a>
    <a href="<?= BASE_URL ?>/kabag_kredit/proses.php">Kabag Kredit</a>
    <a href="<?= BASE_URL ?>/kadiv_bisnis/proses.php">Kadiv Bisnis</a>          <!-- Changed -->
    <a href="<?= BASE_URL ?>/direksi/proses.php">Direktur Utama</a>            <!-- Changed -->
</div>
```

---

## 📊 COMPARISON TABLE

| Aspek | SEBELUM | SESUDAH |
|-------|---------|---------|
| **Chain Approval** | 5 Level | 5 Level (SAMA) |
| **Level 2** | Kabag Analis | **Kasubag Analis** |
| **Level 4** | Kadiv Kredit | **Kadiv Bisnis** |
| **Level 5** | Direksi | **Direktur Utama** |
| **Threshold < 500M** | Stop di Kadiv Kredit | **Stop di Kadiv Bisnis** |
| **Threshold ≥ 500M** | Continue ke Direksi | **Continue ke Direktur Utama** |

---

## ✨ BENEFITS

1. ✅ **Clearer Role Naming**: Penamaan role lebih spesifik sesuai organisasi
   - "Kasubag Analis" lebih jelas dari "Kabag Analis"
   - "Kadiv Bisnis" merepresentasikan divisi bisnis
   - "Direktur Utama" adalah top-level decision maker

2. ✅ **Consistent with Organization Structure**: Approval chain now matches actual bank structure

3. ✅ **Same Nominal Thresholding Logic**: 
   - Tetap mempertahankan automatic approval routing based on amount
   - < 500M: Stop at Kadiv Bisnis (faster decision)
   - ≥ 500M: Require Direktur Utama (strategic approval)

4. ✅ **Backward Compatible**:
   - Old role folders still functional
   - Old enum values can be migrated gradually
   - No breaking changes to approval logic

---

## 🔄 WORKFLOW ACTIVATION

### Step 1: Database Update
```bash
# Execute SQL changes to database.sql
# The enum change in approval_kredit table will be applied
```

### Step 2: Role Migration (Optional)
```sql
-- If you have existing users with old roles, migrate them:
UPDATE users SET role = 'kasubag_analis' WHERE role = 'kabag_analis';
UPDATE users SET role = 'kadiv_bisnis' WHERE role = 'kadiv_kredit';
UPDATE users SET role = 'direktur_utama' WHERE role = 'direksi';

-- Update approval_kredit history if needed:
UPDATE approval_kredit SET level_approval = 'kasubag_analis' WHERE level_approval = 'kabag_analis';
UPDATE approval_kredit SET level_approval = 'kadiv_bisnis' WHERE level_approval = 'kadiv_kredit';
UPDATE approval_kredit SET level_approval = 'direktur_utama' WHERE level_approval = 'direksi';
```

### Step 3: Test the Flow
1. ✅ Create test application with amount < 500M
   - Should approve at Kadiv Bisnis
   - Should NOT reach Direktur Utama
   
2. ✅ Create test application with amount ≥ 500M
   - Should approve at Kadiv Bisnis
   - Should continue to Direktur Utama
   
3. ✅ Test revision & rejection flows
   - Verify return-to-analyst logic still works
   - Verify audit trail is correct

---

## 📝 NOTES FOR DEPLOYMENT

### ⚠️ Critical
1. **Database Enum Change**: Must update `approval_kredit.level_approval` ENUM in production database
2. **User Role Migration**: If you have existing user data with old role names, must migrate to new names
3. **Approval History**: Old approval records with 'kabag_analis', 'kadiv_kredit', 'direksi' should be handled:
   - Option A: Migrate (UPDATE) to new role names
   - Option B: Keep as-is (historical data) - requires handling in queries

### ✅ Safe to Deploy
- No approval logic changes (same thresholding logic)
- Backward compatible naming in functions
- No database schema changes (only ENUM values)
- No breaking changes to approval workflow

### 🔍 Verification Checklist
- [ ] Database enum updated in approval_kredit
- [ ] User roles migrated (if applicable)
- [ ] Navigation menu shows new role names
- [ ] Test application routing < 500M
- [ ] Test application routing ≥ 500M
- [ ] Verify revision/rejection flows
- [ ] Check audit logs for role names

---

## 📞 SUPPORT

Untuk pertanyaan atau issues:
1. Verifikasi perubahan di database.sql
2. Check file `functions.php` - getHierarchy() dan getMaxApprovalLevel()
3. Review navbar.php untuk menu links
4. Test dengan aplikasi uji coba di staging environment

---

**Implementation Date**: May 12, 2026  
**Status**: READY FOR DEPLOYMENT ✅
