# APPROVAL WORKFLOW BASED ON LOAN AMOUNT

## Overview
Sistem approval pengajuan kredit sekarang memperhitungkan **jumlah kredit yang diajukan** untuk menentukan level approval final:

- **Pengajuan < 500 juta** → Approval chain berakhir di **Kadiv Kredit** (final approval)
- **Pengajuan ≥ 500 juta** → Approval chain berakhir di **Direksi** (final approval)

---

## Approval Flow Diagram

### Skenario 1: Pengajuan < 500 Juta
```
┌─────────┐     ┌──────────────┐     ┌─────────────┐     ┌──────────────┐     ┌───────────┐
│ ANALIS  │ ──→ │ KABAG ANALIS │ ──→ │ KABAG KREDIT│ ──→ │ KADIV KREDIT │ ──→ │ SELESAI ✓ │
└─────────┘     └──────────────┘     └─────────────┘     └──────────────┘     └───────────┘
                                                                ▲
                                                           [FINAL APPROVAL]
                                                                
                                            🛑 Direksi NOT involved
```

### Skenario 2: Pengajuan ≥ 500 Juta
```
┌─────────┐     ┌──────────────┐     ┌─────────────┐     ┌──────────────┐     ┌──────────┐     ┌───────────┐
│ ANALIS  │ ──→ │ KABAG ANALIS │ ──→ │ KABAG KREDIT│ ──→ │ KADIV KREDIT │ ──→ │ DIREKSI  │ ──→ │ SELESAI ✓ │
└─────────┘     └──────────────┘     └─────────────┘     └──────────────┘     └──────────┘     └───────────┘
                                                                                      ▲
                                                                                 [FINAL APPROVAL]
```

---

## Implementation Details

### 1. New Helper Function: `getMaxApprovalLevel()`

**Location:** [includes/functions.php](includes/functions.php#L88)

```php
/**
 * Get maximum approval level based on loan amount
 * Ketentuan:
 * - Pengajuan < 500 juta: maksimal approval hanya sampai kadiv_kredit
 * - Pengajuan >= 500 juta: approval sampai direksi
 */
function getMaxApprovalLevel($jumlah_kredit)
{
    $THRESHOLD_AMOUNT = 500000000; // 500 juta
    
    if ($jumlah_kredit < $THRESHOLD_AMOUNT) {
        return 'kadiv_kredit'; // Stop at Kadiv Kredit for amounts < 500M
    }
    return 'direksi'; // Continue to Direksi for amounts >= 500M
}
```

**Parameters:**
- `$jumlah_kredit` (float/int): Jumlah kredit yang diajukan dalam Rupiah

**Returns:**
- `'kadiv_kredit'` - jika jumlah < 500 juta
- `'direksi'` - jika jumlah ≥ 500 juta

---

### 2. Modified Function: `findNextTarget()`

**Location:** [includes/functions.php](includes/functions.php#L140)

**Old Signature:**
```php
function findNextTarget($currentRole, $pdo)
```

**New Signature:**
```php
function findNextTarget($currentRole, $pdo, $jumlah_kredit = null)
```

**Changes:**
- Parameter tambahan: `$jumlah_kredit` (optional, backward compatible)
- Saat routing, fungsi sekarang mengecek:
  1. Apakah current role sudah mencapai atau melampaui max level untuk amount ini?
  2. Jika ya → return `'selesai'` (tidak ada role berikutnya)
  3. Jika tidak → lanjut ke role berikutnya (dengan pengecekan active status)

**Logic Flow:**
```php
1. Get max approval level for this amount → getMaxApprovalLevel($jumlah_kredit)
2. If current role >= max level → return 'selesai'
3. Otherwise → find next active role in hierarchy
```

---

### 3. Updated Function: `processApproval()`

**Location:** [includes/functions.php](includes/functions.php#L256)

**Change:**
```php
// BEFORE (line 273):
$nextStep = findNextTarget($role, $pdo);

// AFTER (line 275):
$nextStep = findNextTarget($role, $pdo, $row['jumlah_kredit']);
```

**Benefit:** Saat approver klik "SETUJUI", sistem otomatis menentukan status & role berikutnya berdasarkan amount

---

### 4. Updated: `analis/save_section.php` - Submit Handler

**Location:** [analis/save_section.php](analis/save_section.php#L1014)

**Change:**
```php
// BEFORE (old code):
$stmtLR = $pdo->prepare("SELECT last_reject_level FROM pengajuan_kredit WHERE id_pengajuan = ?");
...
$nextStep = findNextTarget('analis', $pdo);

// AFTER (new code):
$stmtLR = $pdo->prepare("SELECT last_reject_level, jumlah_kredit FROM pengajuan_kredit WHERE id_pengajuan = ?");
$dataRow = $stmtLR->fetch(PDO::FETCH_ASSOC);
$lr = is_string($dataRow['last_reject_level'] ?? null) ? trim($dataRow['last_reject_level']) : '';
$jumlah_kredit = $dataRow['jumlah_kredit'] ?? 0;
...
$nextStep = findNextTarget('analis', $pdo, $jumlah_kredit);
```

**Benefit:** Saat analis submit form, sistem route ke approver yang tepat sesuai amount

---

## Test Cases

### Test 1: Amount 450 Juta (< 500M)
```
Max Level Expected: kadiv_kredit
Routing: analis → kabag_analis → kabag_kredit → kadiv_kredit [FINAL]
Direksi: ❌ NOT INVOLVED
```

### Test 2: Amount 500 Juta (= 500M)
```
Max Level Expected: direksi
Routing: analis → kabag_analis → kabag_kredit → kadiv_kredit → direksi [FINAL]
All levels: ✅ INVOLVED
```

### Test 3: Amount 750 Juta (> 500M)
```
Max Level Expected: direksi
Routing: analis → kabag_analis → kabag_kredit → kadiv_kredit → direksi [FINAL]
All levels: ✅ INVOLVED
```

### Test 4: Amount 50 Juta (< 500M)
```
Max Level Expected: kadiv_kredit
Routing: analis → kabag_analis → kabag_kredit → kadiv_kredit [FINAL]
Direksi: ❌ NOT INVOLVED
```

---

## Running Tests

Execute the test file to verify all conditional routing logic:

```bash
cd d:\laragon\www\andrian\bank-kredit
php TEST_APPROVAL_AMOUNT_LOGIC.php
```

**Expected Output:**
```
╔════════════════════════════════════════════════════════════════╗
║     TESTING: Conditional Approval Based on Loan Amount        ║
╚════════════════════════════════════════════════════════════════╝

✅ ALL TESTS PASSED

📋 APPROVAL LOGIC SUMMARY:
───────────────────────────────────────────────────────────────
✓ Pengajuan < 500 juta   → Final approval: Kadiv Kredit
✓ Pengajuan >= 500 juta  → Final approval: Direksi
───────────────────────────────────────────────────────────────
```

---

## Backward Compatibility

✅ **Fully backward compatible!**

- All calls to `findNextTarget()` without the 3rd parameter still work
- Default behavior: `jumlah_kredit = null` → acts as if no amount limit
- Existing code continues to function normally

**Migration Path:**
1. ✅ Core logic implemented
2. ✅ New parameter added (optional)
3. ✅ Old calls still work (backward compatible)
4. ✅ New calls include amount parameter for proper routing

---

## Database Fields Used

| Table | Field | Type | Purpose |
|-------|-------|------|---------|
| pengajuan_kredit | jumlah_kredit | DECIMAL(15,2) | Loan amount for routing decision |
| pengajuan_kredit | posisi_saat_ini | VARCHAR | Current approval level |
| pengajuan_kredit | status_pengajuan | ENUM | Current status |
| users | status_jabatan | VARCHAR | Check if approver is active |

---

## Edge Cases Handled

### Case 1: Inactive Approvers
- If Kabag Kredit is inactive, system automatically skips to next active role
- Amount logic still applies to determine max level
- Example: If Kadiv Kredit is inactive AND amount < 500M → approval marked as 'selesai'

### Case 2: Resumed Application After Rejection
- When analis resubmits after rejection, `last_reject_level` is respected
- Amount logic applies from current position onward
- Example: If rejected at Kadiv Kredit, resubmit resumes from Kadiv Kredit level

### Case 3: Manual Routing Override
- If `last_reject_level` is set (rejected at specific level), that level is used directly
- Amount logic only applies to new submissions without prior rejection

---

## Approval Decision Possibilities at Each Level

### Kadiv Kredit (< 500M)
- ✅ **SETUJUI** → Status = 'disetujui', posisi = 'selesai' [FINAL]
- 🔄 **REVISI** → Return to analis for corrections
- ❌ **TOLAK** → Return to analis with rejected status

### Direksi (≥ 500M)
- ✅ **SETUJUI** → Status = 'disetujui', posisi = 'selesai' [FINAL]
- 🔄 **REVISI** → Return to analis for corrections
- ❌ **TOLAK** → Return to analis with rejected status

---

## Summary

| Aspect | Detail |
|--------|--------|
| **Threshold** | 500 juta Rupiah |
| **Max Level < 500M** | Kadiv Kredit |
| **Max Level ≥ 500M** | Direksi |
| **Key Functions** | getMaxApprovalLevel(), findNextTarget() |
| **Files Modified** | includes/functions.php, analis/save_section.php |
| **Backward Compatible** | ✅ Yes |
| **Auto-skipping** | ✅ Inactive roles still skipped |

---

## Questions & Support

For issues or questions regarding the amount-based approval logic, check:
- TEST_APPROVAL_AMOUNT_LOGIC.php (test cases)
- includes/functions.php (core logic)
- analis/save_section.php (submission flow)
