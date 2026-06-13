# TESTING QUICK REFERENCE CARD

**Print this or keep open on second monitor during testing**

---

## 🔑 TEST CREDENTIALS

```
Role: Analis Kredit
Username: analis_test
Password: password123

Role: Kabag Kredit
Username: kabag_test
Password: password123

Role: Kadiv Bisnis
Username: kadiv_test
Password: password123

Role: Direktur Utama
Username: direktur_test
Password: password123

Role: Kepatuhan
Username: kepatuhan_test
Password: password123
```

---

## 📍 KEY URLs

```
Base:     http://localhost/andrian/bank-kredit/
Login:    http://localhost/andrian/bank-kredit/login_page.html
Print:    http://localhost/andrian/bank-kredit/print.php?id=[1-2]
Test Admin: http://localhost/andrian/bank-kredit/admin/master_pejabat.php
```

---

## 🆔 TEST PENGAJUAN IDs

After running `insert_test_data.php`:

```
Pengajuan #1:
ID: 1
Nama: PT TEST COMPANY 1
Amount: Rp 250,000,000
Approval: Analis → Kabag → Kadiv (stops here)

Pengajuan #2:
ID: 2
Nama: PT TEST COMPANY 2
Amount: Rp 600,000,000
Approval: Analis → Kabag → Kadiv → Direktur (full chain)
```

---

## ✅ 5C REFERENCE SCORES

**Test Data (Insert_test_data.php uses):**

```
Pengajuan #1 (250M):
- Character:   90 (Good)
- Capacity:    85 (Good)
- Capital:     80 (Good)
- Collateral:  85 (Good)
- Condition:   80 (Good)
- TOTAL:       420 = LAYAK ✓
```

---

## 💰 NERACA REFERENCE VALUES

**Test Data balances:**

```
Assets (Aset):
- Kas:                50M
- Piutang:           100M
- Persediaan:        200M
- Aset Lancar:       350M
- Aset Tetap:        150M
- Aset Lainnya:       25M
- TOTAL ASET:        525M

Liabilities & Equity (Kewajiban & Modal):
- Hutang Lancar:      75M
- Hutang JP:          50M
- Modal:             400M
- TOTAL K&M:         525M ✓ (matches Aset)

Financial Ratios:
- Current Ratio:      350/75 = 4.67x (EXCELLENT)
- Debt-to-Equity:    125/400 = 0.31 (GOOD)
```

---

## 🏦 AGUNAN REFERENCE DATA

**Test Data collateral:**

```
Jaminan Tanah:
- Lokasi: "Jl. Test Property"
- Luas Tanah: 500 m²
- Sertifikat: "Sertifikat Hak Milik"
- Nilai Taksasi: 500M
- Status: AKTIF ✓

Jaminan Kendaraan:
- Merek: Toyota
- Tipe: Avanza
- Tahun: 2020
- Plat: "B 1234 ABC"
- Nilai Taksasi: 150M
- Status: AKTIF ✓

TOTAL AGUNAN: 650M
LTV Ratio: 250M / 650M = 38.5% (GOOD) ✓
```

---

## ⏸️ STATUS PROGRESSION

### Approval Status Flow:

```
INPUT (Analis input) → PENDING_KABAG
  ↓
APPROVED_KABAG → PENDING_KADIV
  ↓
APPROVED_KADIV → [if >=500M: PENDING_DIREKTUR] or [if <500M: APPROVED]
  ↓
[Only for >=500M]
APPROVED_DIREKTUR → DISETUJUI (FINAL)
  ↓
[or if rejected anywhere]
REVISI → back to Analis for edit

Kepatuhan starts at ANY approval level (can run in parallel):
PENDING_KEPATUHAN → ASSESSMENT_SUBMITTED → APPROVED
```

---

## 🎯 EXPECTED OUTPUTS

### After Full Workflow (Pengajuan #1):

```
✅ Database should have:
- 1 row in pengajuan_kredit (id=1)
- 1 row in analisa_5c (total_skor=420, status=LAYAK)
- 1 row in neraca (aset=525M, kewajiban_modal=525M)
- 2 rows in agunan (1 tanah + 1 kendaraan)
- 3 rows in approval_kredit (Analis, Kabag, Kadiv)
- 3 audit_log entries

✅ Print output should show:
- Page 1: Identitas, 5C scores, Neraca, Agunan, Hasil Kepatuhan
- Page 2: Signature section with 4 officer boxes
- NO timeline table visible
- NO threshold catatan visible
- Professional layout, no errors

✅ Master Pejabat should display:
- Budi Santoso (Analis)
- Ahmad Wijaya (Kasubag Analis)
- Siti Nurhaliza (Kabag Kredit)
- Rudi Hermawan (Kadiv Bisnis)
- Bambang Suryanto (Direktur Utama)
All with "aktif" status
```

---

## 🔄 TEST SEQUENCE (FAST TRACK - 30 min)

```
0:00 Start
├─ 0:00-0:05  Login as analis_test → verify dashboard
├─ 0:05-0:10  Input 5C scores (90,85,80,85,80) → save
├─ 0:10-0:15  Logout → Login as kabag_test → Approve pengajuan #1
├─ 0:15-0:20  Logout → Login as kadiv_test → Approve pengajuan #1
├─ 0:20-0:25  Open print.php?id=1 → Verify 2 pages, no timeline
└─ 0:25-0:30  Check database: SELECT COUNT(*) FROM approval_kredit
              (should = 3 records) ✓

Result: If all ✓ in green = PASS, proceed to release
```

---

## ⚠️ COMMON MISTAKES & FIXES

```
❌ Mistake: Trying to approve as Analis
   ✓ Fix: Only Kabag/Kadiv/Direktur can approve

❌ Mistake: Uploading 10MB photo
   ✓ Fix: Max 5MB. Try JPG or PNG, smaller file

❌ Mistake: Print showing [Pejabat belum ditentukan]
   ✓ Fix: Verify master_pejabat populated in database

❌ Mistake: Kepatuhan field validation error
   ✓ Fix: If NOT_COMPLY selected, must fill catatan

❌ Mistake: Neraca balance error
   ✓ Fix: Aset must equal Kewajiban + Modal exactly

❌ Mistake: Cannot find pengajuan after insert_test_data.php
   ✓ Fix: Verify script ran to completion, check browser for errors

❌ Mistake: PDF blank after clicking print
   ✓ Fix: Check ID parameter (print.php?id=1), verify database has data

❌ Mistake: Photo upload says "File too large"
   ✓ Fix: File > 5MB, compress or choose smaller image
```

---

## 📊 PASS CRITERIA (per test)

```
✓ PASS if:
  - Page loads without error
  - Data saves correctly
  - Status updates as expected
  - No PHP warnings/errors
  - No console JS errors (F12)
  - Database shows new record

❌ FAIL if:
  - Page shows error message
  - Data doesn't save
  - Status stuck/incorrect
  - White screen or 500 error
  - Console has red JS errors
  - Database empty after save
  - Behavior violates business logic
```

---

## 🛠️ QUICK DIAGNOSTICS

### Page Won't Load
```
1. Check: http://localhost/andrian/bank-kredit/index.php
   (should show login)
2. Check browser console: F12 → Console (any red errors?)
3. Check PHP error log: tail -f c:\laragon\logs\php_error.log
4. Restart Laragon: Click RESTART ALL
```

### Can't Save Data
```
1. Open DevTools: F12 → Network tab
2. Trigger save
3. Find POST request
4. Check response (should be 200 OK, not 500/404)
5. Check database if INSERT actually happened
6. Review validation errors in API response
```

### Print Blank/Broken
```
1. Check print.php syntax: php -l print.php
2. Verify pengajuan ID exists: SELECT * FROM pengajuan_kredit WHERE id=1;
3. Check file path in browser: print.php?id=1 (correct ID?)
4. Try different pengajuan ID
5. Check error log for broken SQL queries
```

### Photos Not Showing
```
1. Verify upload directory: dir c:\laragon\www\andrian\bank-kredit\assets\uploads\
2. Check database: SELECT * FROM agunan_foto;
3. Verify file permissions: attrib assets/uploads/
4. Try upload again (single file, <5MB)
5. Check browser console for JS errors
```

---

## 📝 NOTES FOR TESTER

```
☐ Pengajuan #1 (250M) tests: 4-level approval
☐ Pengajuan #2 (600M) tests: 5-level approval (includes Direktur)
☐ Always logout before switching user roles
☐ Clear browser cache if old data showing (Ctrl+Shift+Delete)
☐ Take screenshots of failures for bug report
☐ Keep browser console open (F12) to catch JS errors
☐ Document exact steps if bug found (helps dev fix faster)
☐ Check timestamp - approval times should be sequential
☐ Note: Kepatuhan can work in parallel with approvals
☐ Master Pejabat can be managed in admin/master_pejabat.php
```

---

## 🆘 EMERGENCY CONTACTS

**Bug Found:**
1. Document details in [BUG TEMPLATE]
2. Include: Steps to reproduce, Expected vs Actual, Screenshot
3. Send to: [Dev Team]
4. Severity: Critical → Immediate, High → Within 4 hours, Medium → Within 24 hours

**Database Issues:**
1. Check: php -r "require 'includes/db.php'; echo 'OK';"
2. If error → Restart MySQL in Laragon
3. If still error → Check c:\laragon\logs\mysql_error.log

**Server Not Responding:**
1. Check Laragon is running (system tray)
2. Check Apache/MySQL showing green
3. Try: http://localhost/test.php
4. If no response → Restart computer

---

## ✅ FINAL CHECKLIST BEFORE SIGN-OFF

- [ ] All 11 modules tested (TEST 1-11)
- [ ] Pengajuan #1 (250M) full workflow passed
- [ ] Pengajuan #2 (600M) full workflow passed
- [ ] Print PDF looks professional, no timeline visible
- [ ] No critical bugs open
- [ ] Database integrity verified
- [ ] 5+ audit log entries present
- [ ] Master pejabat officers showing in print
- [ ] Repayment calculations accurate
- [ ] No PHP errors in any console/log
- [ ] Performance acceptable (page load <3s)
- [ ] Ready to present results to stakeholders

**Date Completed:** _____________  
**Tester Signature:** _____________

---

**END OF QUICK REFERENCE**

For detailed test execution, see: TESTING_EXECUTION_GUIDE.md  
For full checklist, see: TESTING_CHECKLIST.md  
For bug procedures, see: BUG_FIX_PROCEDURE.md
