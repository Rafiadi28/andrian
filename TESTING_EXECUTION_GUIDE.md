# TESTING EXECUTION GUIDE

**Quick Start Guide untuk menjalankan test sebelum release**

---

## 🚀 STEP 1: SETUP TEST ENVIRONMENT

### 1.1 Start Laragon
```
Click: Laragon.exe
Click: START ALL
Wait for: Apache & MySQL green indicator
```

### 1.2 Verify Database Connection
```bash
# Via command line (in bank-kredit folder)
php -r "require 'includes/db.php'; echo 'DB Connected: OK';"
```

**Expected:** `DB Connected: OK`

### 1.3 Load Test Data
```bash
cd d:\laragon\www\andrian\bank-kredit
php insert_test_data.php
```

**Expected Output:**
```
✅ TEST DATA SETUP COMPLETE
======================================================================
📊 SUMMARY:
✓ User created: Analis Test (analis_test)
✓ User created: Kabag Kredit Test (kabag_test)
✓ User created: Kadiv Bisnis Test (kadiv_test)
✓ User created: Direktur Utama Test (direktur_test)
✓ User created: Kepatuhan Test (kepatuhan_test)
✓ Pengajuan created: #1 - PT TEST COMPANY 1 (Rp 250,000,000)
✓ Pengajuan created: #2 - PT TEST COMPANY 2 (Rp 600,000,000)
...more items...
🔑 TEST USER CREDENTIALS:
Username: analis_test | Password: password123 | Role: Analis
...
```

**If Error:** Check PHP error_log, verify database connection

---

## 🧪 STEP 2: RUN INDIVIDUAL TESTS

### Test 1: LOGIN & DASHBOARD
```
1. Open browser: http://localhost/andrian/bank-kredit/login_page.html
2. Login: analis_test / password123
3. Verify: Dashboard loads without error
```

**PASS if:** ✅ Dashboard visible, menu active

---

### Test 2: INPUT 5C SCORING
```
1. Click pengajuan #1 from list
2. Go to: Detail → Tab "Analis" → Section "5C Scoring"
3. Enter scores:
   - Character: 90
   - Capacity: 85
   - Capital: 80
   - Collateral: 85
   - Condition: 80
4. Click: SIMPAN
5. Verify: Total = 420, Status = "LAYAK"
```

**PASS if:** ✅ All scores saved, status shows LAYAK

**If FAIL:**
- Check browser console for JS errors (F12)
- Check server error log: `php -l analis/detail.php`
- Verify database table exists: `SELECT * FROM analisa_5c;`

---

### Test 3: APPROVAL WORKFLOW
```
1. Logout (current: Analis)
2. Login: kabag_test / password123
3. Find pengajuan #1 in approval queue
4. Click: Review
5. Click: SETUJU
6. Enter catatan: "Layak untuk dilanjutkan"
7. Click: SIMPAN
8. Verify: Status changes to next level (Kadiv)
```

**PASS if:** ✅ Status updated, notification sent

**Debug if FAIL:**
- Check approval_kredit table: `SELECT * FROM approval_kredit;`
- Check audit_log: `SELECT * FROM audit_log WHERE id_user = [user_id];`

---

### Test 4: PRINT OUTPUT
```
1. Open: http://localhost/andrian/bank-kredit/print.php?id=1
2. Verify Page 1:
   - Header with Bank name
   - 5C scores visible
   - Agunan section
   - Hasil Kepatuhan (if compliance done)
3. Verify Page 2:
   - Signature boxes with officer names
   - NO timeline table visible
4. Click: SIMPAN SEBAGAI PDF
5. Verify: PDF opens in new tab
```

**PASS if:** ✅ PDF renders correctly, no broken layout

**If PDF has issues:**
- Check print.php syntax: `php -l print.php`
- Verify master_pejabat has data: `SELECT * FROM master_pejabat;`
- Check /assets/uploads/ directory exists and is writable

---

### Test 5: KEPATUHAN ASSESSMENT
```
1. Logout (current: Kabag)
2. Login: kepatuhan_test / password123
3. Find pengajuan #1 in queue
4. Click: Assessment
5. Fill compliance checklist:
   - Item 1: Comply
   - Item 2: Not Comply (add catatan)
   - Item 3: N/A
6. Select Hasil Kepatuhan:
   - Option: COMPLY
   - Leave catatan empty
7. Click: SIMPAN ASSESSMENT
8. Verify: Success message, data saved
```

**PASS if:** ✅ Assessment saved, No error on empty catatan with COMPLY

**If FAIL on save:**
- Check save_assessment_kepatuhan.php logic
- Verify database columns exist: `SHOW COLUMNS FROM assessment_kepatuhan;`

---

## 🔍 STEP 3: VERIFICATION CHECKS

### Check Database Integrity
```bash
# SSH to database or use phpMyAdmin

# Verify all tables exist
SHOW TABLES;

# Check audit log has entries
SELECT COUNT(*) FROM audit_log;

# Verify approval chain
SELECT * FROM approval_kredit WHERE id_pengajuan = 1 ORDER BY created_at;

# Check master pejabat populated
SELECT * FROM master_pejabat WHERE status = 'aktif';

# Verify photos uploaded
SELECT COUNT(*) FROM agunan_foto WHERE id_pengajuan = 1;
```

---

### Check No PHP Errors
```bash
# Validate all key files
php -l print.php
php -l analis/detail.php
php -l analis/compliance_assessment.php
php -l api/save_assessment_kepatuhan.php
php -l admin/master_pejabat.php

# Check Apache error log
tail -f c:\laragon\logs\apache_error.log

# Check PHP error log
tail -f c:\laragon\logs\php_error.log
```

**PASS if:** ✅ No "Parse error" or "Syntax error"

---

### Performance Check
```
1. Open DevTools (F12) → Network tab
2. Reload print.php
3. Check loading time:
   - Page load: <3 seconds ✅
   - PDF generation: <5 seconds ✅
   - Photos display: <2 seconds ✅
```

---

## 🐛 STEP 4: BUG REPORTING

### If You Find a Bug

**Step 1: Document the Issue**
```
BUG: [Clear title]
Severity: Critical / High / Medium / Low
Module: [Which feature/page]
Steps to Reproduce:
1. [Step 1]
2. [Step 2]
3. [Step 3]

Expected: [What should happen]
Actual: [What actually happened]
Screenshot: [If visual issue]
```

**Step 2: Locate Error Details**
```bash
# Check browser console (F12 → Console)
# Copy any red errors

# Check server logs
tail -f c:\laragon\logs\apache_error.log
tail -f c:\laragon\logs\php_error.log

# Query database
SELECT * FROM audit_log ORDER BY waktu DESC LIMIT 5;
```

**Step 3: Report to Dev**
```
Send to: [Dev Team]
Subject: BUG - [Feature] - [Brief Description]
Include:
- Full bug description
- Steps to reproduce
- Error logs/screenshots
- Database queries run
- What you expected
```

---

## ✅ QUICK TEST CHECKLIST

Run through this fast (15 min):

- [ ] Login with analis_test
- [ ] Open pengajuan #1
- [ ] Verify all tabs load (Analis, Agunan, etc.)
- [ ] Enter 5C scores (should save)
- [ ] Logout, login as kabag_test
- [ ] Approve pengajuan #1 (should update status)
- [ ] Open print.php?id=1
- [ ] Verify PDF structure (2 pages, no timeline visible)
- [ ] Check database: `SELECT COUNT(*) FROM approval_kredit;` (should > 0)
- [ ] No red errors in browser console (F12)

**If all 10 items ✓:** Release is ready!

---

## 📞 TROUBLESHOOTING

### Issue: "Database Connection Failed"
**Fix:**
```bash
1. Start Laragon MySQL
2. Verify connection: php -r "require 'includes/db.php';"
3. Check credentials in includes/db.php match Laragon setup
```

### Issue: "Table doesn't exist"
**Fix:**
```bash
1. Run: php insert_test_data.php (will create tables via migration)
2. OR manually: Open phpMyAdmin → Run schema_realtime_migrate.php code
```

### Issue: "File upload failed"
**Fix:**
```bash
1. Check directory: c:\laragon\www\andrian\bank-kredit\assets\uploads\
2. If missing, create it manually
3. Verify permissions: chmod 777 assets/uploads/
```

### Issue: "Photo not showing in PDF"
**Fix:**
```bash
1. Verify file path in database: SELECT tanda_tangan FROM master_pejabat;
2. Check file exists: dir c:\laragon\www\andrian\bank-kredit\assets\uploads\pejabat\
3. Verify print.php file_exists() check working
```

### Issue: "Print page blank or errors"
**Fix:**
```bash
1. Validate print.php: php -l print.php
2. Check error log: tail -f c:\laragon\logs\php_error.log
3. Verify database has data: SELECT COUNT(*) FROM pengajuan_kredit;
4. Check parameter: print.php?id=1 (must be existing pengajuan ID)
```

---

## 📊 EXPECTED TEST RESULTS

### All Should PASS ✅
- [x] Login all 5 user roles
- [x] Input 5C scoring
- [x] Input neraca with validation
- [x] Input agunan and calculate totals
- [x] Upload photos (multiple)
- [x] Approval workflow (Analis → Kabag → Kadiv → [Direktur if >=500M])
- [x] Kepatuhan assessment with conditional validation
- [x] Print PDF with officer data from master
- [x] Repayment calculations and risk levels
- [x] No timeline visible in print
- [x] No threshold catatan visible in print

### Database Should Show
```
- 5 test users created
- 2 pengajuan kredit
- 2 sets of 5C scores
- 2 sets of neraca data
- Multiple agunan entries
- Approval records for each level
- Audit log entries for all actions
- Master pejabat with 5 officer records
```

---

## 🎯 FINAL RELEASE GATE

**Before Release, Verify:**
- [ ] All 11 test modules passed
- [ ] No critical bugs open
- [ ] Performance acceptable (<3s load time)
- [ ] Database integrity verified
- [ ] Audit logs complete
- [ ] Security review passed
- [ ] Sign-off from QA, Dev, PM, Business
- [ ] User documentation ready
- [ ] Backup created

**Go/No-Go Decision:** [To be filled during testing]

---

**END OF TESTING GUIDE**

For detailed checklist, see: [TESTING_CHECKLIST.md](TESTING_CHECKLIST.md)  
For bug reporting, see: [TEST_RESULT_REPORT.md](TEST_RESULT_REPORT.md)
