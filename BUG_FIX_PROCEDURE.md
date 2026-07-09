# BUG FIX PROCEDURE & GUIDELINES

**Guidelines untuk menemukan dan memperbaiki bug tanpa mengubah business logic**

---

## 📋 BUG DISCOVERY PROCESS

### Step 1: Capture Bug Details
```
BUG REPORT FORMAT:

ID: BUG-[Date]-[Number]
Severity: ⚠️ CRITICAL / 🔴 HIGH / 🟡 MEDIUM / 🟢 LOW
Status: NEW → ASSIGNED → IN_PROGRESS → FIXED → VERIFIED → CLOSED

Component: [Module/File affected]
Feature: [What feature has bug]
Environment: Development / Staging / Production

DESCRIPTION:
[What went wrong - clear and concise]

STEPS TO REPRODUCE:
1. [First action]
2. [Second action]
3. [Third action]
... (as detailed as possible)

EXPECTED RESULT:
[What should happen according to spec]

ACTUAL RESULT:
[What actually happened]

EVIDENCE:
- Screenshot: [Yes/No]
- Error Log: [Yes/No]
- Database Issue: [Yes/No]
- Code Problem: [Yes/No]

BUSINESS IMPACT:
- Does it violate approved business logic? [Yes/No]
- Does it block workflow? [Yes/No]
- Does it affect data integrity? [Yes/No]
```

---

## 🔍 BUG CATEGORIZATION

### CRITICAL - MUST FIX IMMEDIATELY
Examples:
- Data loss or corruption
- Security vulnerability
- System crash
- Approval workflow broken
- Database constraint violated

**Fix Timeline:** Same day

### HIGH - FIX BEFORE RELEASE
Examples:
- Calculation errors (5C, repayment, ratios)
- Missing required field
- Cannot save data
- Authorization bypass
- File upload failure

**Fix Timeline:** Within 24 hours

### MEDIUM - FIX SOON
Examples:
- Display issue (formatting, alignment)
- Non-critical field missing
- Validation message unclear
- Performance lag (<3s still OK)
- Minor UI issue

**Fix Timeline:** Before next test cycle

### LOW - FIX LATER
Examples:
- Typo or spelling error
- UI preference (color, spacing)
- Non-blocking workflow issue
- Enhancement request
- Nice-to-have feature

**Fix Timeline:** After release (backlog)

---

## 🛠️ BUG FIX WORKFLOW

### Step 1: Assess Business Logic Impact

**CRITICAL QUESTION:**
> Does this bug violate the approved business logic?

**Business Logic = Approved Workflow:**
- Multi-level approval (Analis → Kabag → Kadiv → [Direktur])
- 5C scoring thresholds (>=400 LAYAK, 350-399 CATATAN, <350 TIDAK)
- 500M threshold (requires Direktur approval)
- Kepatuhan conditional validation (NOT_COMPLY requires catatan)
- LTV and Debt-to-Income ratios
- Role-based access control
- Audit logging
- Data persistence

**If YES - Violates Logic:** DO NOT FIX without approval!
- Escalate to business analyst
- Document impact
- Get sign-off before proceeding

**If NO - Pure Bug:** Can fix immediately
- Proceed to fix

---

### Step 2: Reproduce the Bug

```bash
# In development environment
1. Follow exact steps from bug report
2. Verify bug occurs 100% of time
3. Test with different data sets
4. Check if affects other features too
```

**Important:** Do NOT fix until you can reproduce!

---

### Step 3: Root Cause Analysis

### Common Bug Types & Analysis:

#### A. PHP Logic Error
```php
// EXAMPLE BUG: Wrong variable name
// WRONG:
$status = $total >= 400 ? 'LAYAK' : 'TIDAK_LAYAK'; // Missing: 350-399 case

// ROOT CAUSE: Missing condition for 350-399 range
// FIX: Add middle condition
$status = $total >= 400 ? 'LAYAK' : 
          ($total >= 350 ? 'LAYAK_DENGAN_CATATAN' : 'TIDAK_LAYAK');

// VERIFY: Business logic not changed, just bug fixed
```

#### B. Database Issue
```sql
-- EXAMPLE BUG: Data not saving
-- ROOT CAUSE: Missing column in INSERT statement
-- Wrong:
INSERT INTO analisa_5c (skor_character, skor_capacity) 
VALUES (90, 85);

-- Fixed:
INSERT INTO analisa_5c (id_pengajuan, skor_character, skor_capacity, total_skor, status_kelayakan)
VALUES (1, 90, 85, 415, 'LAYAK');

-- VERIFY: All required fields included, data integrity maintained
```

#### C. Validation Error
```php
// EXAMPLE BUG: Cannot submit with valid data
// WRONG:
if (empty($hasil_kepatuhan) || empty($catatan_hasil)) {
    $error = "Both fields required";
}

// ROOT CAUSE: Catatan should be conditional (only required for NOT_COMPLY)
// FIXED:
if (empty($hasil_kepatuhan)) {
    $error = "Hasil Kepatuhan required";
} elseif ($hasil_kepatuhan === 'NOT_COMPLY' && empty($catatan_hasil)) {
    $error = "Catatan required for NOT_COMPLY";
}

// VERIFY: Business logic preserved (optional for COMPLY, required for NOT_COMPLY)
```

---

### Step 4: Locate Bug in Code

```bash
# Search for the issue
grep -r "problematic_code" bank-kredit/

# Narrow down by file type
grep -r "issue" bank-kredit/*.php

# Check specific module
php -l bank-kredit/analis/detail.php  # Syntax check
```

---

### Step 5: Fix the Bug

### FIX CHECKLIST:
- [ ] Understand the original intent
- [ ] Make minimal change (don't refactor)
- [ ] Don't change business logic
- [ ] Add code comment explaining fix
- [ ] Test fix locally
- [ ] Verify no side effects

### Example Fix Template:
```php
// BEFORE (buggy):
$approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo $approvals;  // Missing format - shows array

// AFTER (fixed):
$approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($approvals as $approval) {
    echo $approval['nama_approver'] . ' - ' . $approval['tanggal_approval'];
}

// FIX COMMENT:
// BUG FIX: Display approval data properly instead of raw array
// Business logic unchanged - still shows same approvals, just formatted
```

---

### Step 6: Test the Fix

```bash
# Syntax check
php -l file_with_fix.php

# Test specific function
php -r "require 'file_with_fix.php'; function_with_fix();"

# Manual test
1. Open browser to affected page
2. Perform steps from bug report
3. Verify bug is gone
4. Verify related features still work
```

---

### Step 7: Verify Business Logic Intact

**Before Commit Checklist:**

```
APPROVAL WORKFLOW:
- [ ] Multi-level approval still works
- [ ] Rejection route still works
- [ ] Status transitions still correct

5C SCORING:
- [ ] Scores still 0-100
- [ ] Thresholds still: 400=LAYAK, 350-399=CATATAN, <350=TIDAK
- [ ] Total calculation still correct

KEPATUHAN:
- [ ] COMPLY path: optional catatan
- [ ] NOT_COMPLY path: required catatan
- [ ] Validation still enforced

FINANCIAL:
- [ ] 500M threshold still triggers Direktur
- [ ] <500M stops at Kadiv
- [ ] Calculations unchanged

DATA INTEGRITY:
- [ ] No orphaned records created
- [ ] Audit log still records changes
- [ ] Backup still works

SECURITY:
- [ ] Role-based access still enforced
- [ ] No unauthorized access allowed
- [ ] Passwords still hashed
```

---

## 📝 BUG FIX DOCUMENTATION

### For Each Bug Fixed, Document:

```
FIX RECORD:

Bug ID: BUG-2026-06-001
Title: 5C Status Logic Missing Middle Case
Severity: HIGH
Date Fixed: 2026-06-12
Fixed By: [Dev Name]

ANALYSIS:
Root Cause: Missing LAYAK_DENGAN_CATATAN condition for 350-399 range
Impact: Users with score 350-399 cannot get correct status

CHANGE MADE:
File: helpers/credit_helper.php, function tentukan_status_kelayakan()
Change: Added condition for 350-399 range
Lines: 45-48

CODE CHANGE:
- OLD: $status = $total >= 400 ? 'LAYAK' : 'TIDAK_LAYAK';
+ NEW: $status = $total >= 400 ? 'LAYAK' : 
+       ($total >= 350 ? 'LAYAK_DENGAN_CATATAN' : 'TIDAK_LAYAK');

BUSINESS LOGIC VERIFICATION:
✓ Thresholds unchanged (400, 350, <350)
✓ Status options unchanged
✓ Calculation formula unchanged
✓ Approval workflow unaffected

TESTING:
✓ Tested with score 415 → LAYAK
✓ Tested with score 375 → LAYAK_DENGAN_CATATAN (now works!)
✓ Tested with score 340 → TIDAK_LAYAK

VERIFIED BY: [QA Name]
DATE: 2026-06-12
```

---

## 🚫 PROHIBITED CHANGES

### DO NOT DO THESE:

❌ **Change approval workflow levels**
```
X Don't do: Remove Kabag from approval chain
X Don't do: Add new approval role
✓ OK: Fix bug in existing workflow
```

❌ **Change 5C thresholds**
```
X Don't do: Change 400 threshold to 380
X Don't do: Add new status level
✓ OK: Fix calculation formula
```

❌ **Skip audit logging**
```
X Don't do: Remove audit_log insert
X Don't do: Bypass logActivity() function
✓ OK: Fix timestamp format
```

❌ **Bypass security checks**
```
X Don't do: Remove authorization check
X Don't do: Allow unauthorized role access
✓ OK: Fix role name matching
```

❌ **Alter database schema without migration**
```
X Don't do: Direct ALTER TABLE in production
X Don't do: Delete data to fix bug
✓ OK: Add fix to schema_realtime_migrate.php
```

---

## ✅ ALLOWED CHANGES

### OK TO FIX:

✓ **Display issues**
```
- Fix formatting in print output
- Correct alignment in forms
- Hide/show elements appropriately
```

✓ **Calculation errors**
```
- Fix wrong formula (but keep business logic)
- Fix rounding issues
- Fix missing values
```

✓ **Validation problems**
```
- Fix overly strict validation
- Fix missing validation
- Fix validation message
```

✓ **Data persistence issues**
```
- Fix unsaved data
- Fix query errors
- Fix missing database entries
```

✓ **Performance issues**
```
- Add database indexes
- Optimize queries
- Cache expensive operations
```

✓ **User experience improvements** (non-logic)
```
- Better error messages
- Clearer UI labels
- Keyboard shortcuts (if not breaking workflow)
```

---

## 📋 CRITICAL BUG EXAMPLES & FIXES

### BUG EXAMPLE 1: Cannot Submit Assessment
```
SYMPTOM: Kepatuhan user gets "Field required" error even with data filled

ROOT CAUSE: 
Form collects data but validation checks wrong variable name

FIX:
File: analis/compliance_assessment.php (line 850)
- Change: const catatanRequired = document.getElementById('catatan_hasil_required');
+ To: const catatanRequired = document.getElementById('catatan_required');

VERIFY: Correct element ID matches HTML, business logic unchanged
```

### BUG EXAMPLE 2: 5C Score Not Saving
```
SYMPTOM: User enters 5C scores, clicks save, gets blank response

ROOT CAUSE:
API missing error handling for duplicate submission

FIX:
File: api/save_5c_scoring.php (line 120)
Add: Check if record already exists before insert
- INSERT INTO analisa_5c ... (will fail if exists)
+ Check if EXISTS first, UPDATE if yes, INSERT if no

VERIFY: Data still saves, audit trail complete
```

### BUG EXAMPLE 3: Print PDF Shows Wrong Officer Name
```
SYMPTOM: Print shows "[Pejabat belum ditentukan]" instead of name

ROOT CAUSE:
Master pejabat query returns NULL due to missing WHERE condition

FIX:
File: print.php (line 175)
Query: SELECT * FROM master_pejabat
Add: WHERE status = 'aktif' AND role IN (...)
+ Properly join to get active officers only

VERIFY: Only active officers shown, business logic of "aktif" status preserved
```

---

## 🔄 BUG FIX REVIEW CHECKLIST

**Before marking bug as FIXED:**

- [ ] Bug is reproducible with steps provided
- [ ] Root cause identified and documented
- [ ] Fix is minimal (one logical change)
- [ ] Business logic verified unchanged
- [ ] Code syntax validated (php -l)
- [ ] Fix tested locally with multiple data sets
- [ ] No side effects on other features
- [ ] Audit logging intact
- [ ] Database integrity verified
- [ ] Performance impact checked
- [ ] Related bugs checked (may affect them too)
- [ ] Fix documented with before/after code
- [ ] QA sign-off obtained
- [ ] No security holes introduced

**If all ✓:** Mark as VERIFIED & CLOSED

---

## 📞 ESCALATION PROCEDURE

### When to Escalate:

**To Business Analyst:**
- Bug requires business rule clarification
- Fix might affect business logic
- Bug involves approval/compliance rules

**To Security Team:**
- Bug involves authentication/authorization
- Data access control affected
- Potential security vulnerability

**To Infrastructure Team:**
- Server/database performance issue
- Storage space issue
- Network/connectivity problem

**Escalation Template:**
```
ESCALATION REQUEST

To: [Department]
Bug ID: BUG-2026-[Number]
Urgency: Critical / High / Medium
Issue: [Brief description]
Required Decision: [What decision needed]
Timeline: [By when needed]
```

---

## 📊 BUG TRACKING METRICS

**Track per test cycle:**
- Total bugs found: [X]
- Critical: [X] - Fixed: [X] - Closed: [X]
- High: [X] - Fixed: [X] - Closed: [X]
- Medium: [X] - Fixed: [X] - Closed: [X]
- Low: [X] - Fixed: [X] - Closed: [X]

**Success Criteria:**
- All CRITICAL bugs fixed & verified
- 90%+ of HIGH bugs fixed & verified
- MEDIUM & LOW tracked for backlog

---

**END OF BUG FIX PROCEDURE**

For test execution, see: [TESTING_EXECUTION_GUIDE.md](TESTING_EXECUTION_GUIDE.md)  
For test checklist, see: [TESTING_CHECKLIST.md](TESTING_CHECKLIST.md)
