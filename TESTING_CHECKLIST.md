# COMPREHENSIVE TESTING CHECKLIST

**Project:** Bank Wonosobo Kredit System  
**Test Date:** 2026-06-12  
**Tester:** [Name]  
**Test Environment:** Development (Laragon)  

---

## 📋 PRE-TEST SETUP

### Step 1: Load Test Data
```bash
# Option A: Via PHP CLI (recommended)
php insert_test_data.php

# Option B: Via Browser
Open: http://localhost/andrian/bank-kredit/insert_test_data.php
```

**Expected Output:**
- ✅ 2 test users created for debitur
- ✅ 5 test officers created (analis, kabag, kadiv, direktur, kepatuhan)
- ✅ 2 pengajuan kredit created (1x 250M, 1x 600M)
- ✅ 5C scoring, neraca, agunan data populated
- ✅ Test credentials displayed

### Step 2: Verify Test Data
```sql
SELECT * FROM users WHERE username LIKE '%_test';
SELECT * FROM pengajuan_kredit WHERE tgl_dibuat = CURDATE();
SELECT * FROM master_pejabat WHERE status = 'aktif';
```

---

## 🧪 TEST CASES

### ✅ TEST 1: INPUT ANALIS
**Role:** Analis  
**URL:** http://localhost/andrian/bank-kredit/analis/dashboard.php

#### 1.1 Login Analis
- [ ] Login dengan: `analis_test` / `password123`
- [ ] Dashboard loads successfully
- [ ] Menu navigasi visible
- [ ] Pengajuan list visible

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 1.2 Input 5C Scoring
**Go to:** Pilih pengajuan #1 (250M) → Detail → Tab Analis

- [ ] Input semua field 5C (Character, Capacity, Capital, Collateral, Condition)
- [ ] Skor untuk setiap kriteria: 85-95 (LAYAK)
- [ ] Total skor auto-calculated: 415-475
- [ ] Status auto-set: "LAYAK"
- [ ] Form validation works (required fields)
- [ ] Save button works
- [ ] Data saved to database

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 1.3 Input Neraca
**Go to:** Detail pengajuan → Tab Analis → Neraca section

- [ ] Input: Kas, Piutang, Persediaan, Aset Lancar, Aset Tetap
- [ ] Input: Hutang Lancar, Hutang Jangka Panjang, Modal
- [ ] Total Aset = Aset Lancar + Aset Tetap + Aset Lainnya
- [ ] Total Kewajiban & Modal = Hutang + Modal
- [ ] Validation: Aset = Kewajiban & Modal
- [ ] Save successful
- [ ] Data persists after refresh

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 1.4 Input Agunan
**Go to:** Detail pengajuan → Tab Jaminan

- [ ] Add Jaminan Tanah: Lokasi, Luas, Sertifikat, Nilai Taksasi
- [ ] Add Jaminan Kendaraan: Merek, Tipe, Tahun, Plat, Nilai
- [ ] Upload foto agunan (JPG/PNG, <5MB)
- [ ] Preview foto works
- [ ] Delete jaminan works
- [ ] Total agunan calculated correctly

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 1.5 Upload Foto
**Go to:** Detail pengajuan → Upload Foto section

- [ ] Select multiple photo files (3-5 files)
- [ ] Progress bar shows upload status
- [ ] Photos saved to assets/uploads/
- [ ] Thumbnails displayed in gallery
- [ ] Delete photo works
- [ ] Photos persist after refresh

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 1.6 Repayment Capacity Calculation
**Go to:** Detail pengajuan → Repayment section

- [ ] Pendapatan Bulanan: Omset + Pendapatan Lain
- [ ] Pengeluaran Tetap: Total + Biaya Hidup
- [ ] Angsuran Diajukan: Auto-calculated or manual input
- [ ] Remaining Capacity: Income - Expense - Installment
- [ ] Debt-to-Income Ratio: (Installment / Income) × 100%
- [ ] Risk Level: GREEN (Low) / YELLOW (Medium) / RED (High)
- [ ] Threshold validation: Installment < 50% Income
- [ ] Data saved correctly

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 1.7 Submit for Kabag
**Go to:** Detail pengajuan → Button "Submit to Kabag"

- [ ] All required fields filled (5C, Neraca, Agunan)
- [ ] Validation passes
- [ ] Status changes to "Pending Kabag Kredit"
- [ ] Notification created for Kabag
- [ ] Audit log entry created
- [ ] Analis cannot edit anymore (locked)

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

---

### ✅ TEST 2: PERSETUJUAN KABAG
**Role:** Kabag Kredit  
**URL:** http://localhost/andrian/bank-kredit/kabag_kredit/dashboard.php

#### 2.1 Login Kabag
- [ ] Login dengan: `kabag_test` / `password123`
- [ ] Dashboard loads
- [ ] Pending pengajuan #1 visible in queue

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 2.2 Review Analis Data
**Go to:** Pengajuan #1 → Review Tab

- [ ] 5C scores visible (Character: 90, Capacity: 85, etc.)
- [ ] Neraca data displayed
- [ ] Agunan summary shown
- [ ] Repayment capacity visible
- [ ] Risk level indicator shown

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 2.3 Approve with Catatan
**Go to:** Detail pengajuan → Approval section

- [ ] Click "SETUJU" button
- [ ] Enter catatan: "Layak untuk dilanjutkan ke Kadiv"
- [ ] Approval saved with timestamp
- [ ] Status updates to "Pending Kadiv Bisnis"
- [ ] Notification sent to Kadiv
- [ ] Audit log recorded

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 2.4 Reject Path (Test on pengajuan #2)
**Go to:** Pengajuan #2

- [ ] Click "TOLAK" button
- [ ] Enter reason: "Data kurang lengkap, mohon revisi"
- [ ] Status changes to "Revisi"
- [ ] Notification sent back to Analis
- [ ] Analis can edit again
- [ ] Resubmit works

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

---

### ✅ TEST 3: PERSETUJUAN KADIV
**Role:** Kadiv Bisnis  
**URL:** http://localhost/andrian/bank-kredit/kadiv_kredit/dashboard.php

#### 3.1 Login Kadiv
- [ ] Login dengan: `kadiv_test` / `password123`
- [ ] Dashboard loads
- [ ] Pengajuan #1 (from Kabag) visible

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 3.2 Review Complete File
**Go to:** Pengajuan #1

- [ ] All previous levels' catatan visible
- [ ] Approval history shows Analis → Kabag → Kadiv flow
- [ ] Risk assessment: LOW (all ratios good)
- [ ] Recommendation ready

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 3.3 Approve & Route to Direksi (250M case)
- [ ] Click "SETUJU"
- [ ] Enter catatan: "Approved, plafon Rp 250 juta feasible"
- [ ] Status updates to "Pending Direktur" (since >= 250M)
- [ ] Notification to Direktur
- [ ] Audit log entry

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 3.4 Test 500M+ Threshold (with pengajuan #2 if available)
- [ ] For 600M pengajuan: Kadiv approves → Direktur involved
- [ ] Check approval logic: All 5 levels required for >= 500M
- [ ] Check approval logic: Only 4 levels for < 500M

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

---

### ✅ TEST 4: PERSETUJUAN DIREKSI
**Role:** Direktur Utama  
**URL:** http://localhost/andrian/bank-kredit/direksi/dashboard.php

#### 4.1 Login Direktur
- [ ] Login dengan: `direktur_test` / `password123`
- [ ] Dashboard loads
- [ ] Pengajuan #1 (high value) visible in queue

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 4.2 Final Approval
**Go to:** Pengajuan #1

- [ ] Review all approval stages
- [ ] Click "SETUJU" (Final approval)
- [ ] Enter catatan: "Final approval granted"
- [ ] Status changes to "DISETUJUI"
- [ ] Timestamp recorded
- [ ] Audit log entry

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 4.3 Conditional Approval Test
- [ ] Click "SETUJU DENGAN SYARAT"
- [ ] Enter conditions/terms
- [ ] Condition stored in database
- [ ] Visible in approval history

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

---

### ✅ TEST 5: KEPATUHAN
**Role:** Kepatuhan  
**URL:** http://localhost/andrian/bank-kredit/kepatuhan/dashboard.php

#### 5.1 Login Kepatuhan
- [ ] Login dengan: `kepatuhan_test` / `password123`
- [ ] Dashboard loads

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 5.2 Access Analis Data (Compliance Assessment)
**Go to:** Pengajuan #1 → Compliance Tab

- [ ] Can view 5C scores: Character 90, Capacity 85, etc.
- [ ] Agunan summary displayed: Tanah Rp 500M, Kendaraan Rp 150M
- [ ] Repayment capacity shown
- [ ] Data read-only (cannot edit analis input)

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 5.3 Fill Compliance Checklist
**Go to:** Assessment form

- [ ] Checklist items visible (minimum 5 items)
- [ ] Select for each item: Comply / Not Comply / N/A
- [ ] Commentary field for each non-comply
- [ ] Add fasilitas kredit existing (4 columns: Lembaga, Baki Debet, Kol, Ket)
- [ ] Dynamic add/remove rows works

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 5.4 Hasil Kepatuhan (NEW)
**Go to:** Assessment form → Hasil Kepatuhan section

- [ ] Two radio buttons: COMPLY / NOT_COMPLY visible
- [ ] Select COMPLY
  - [ ] Catatan textarea appears (optional)
  - [ ] Red asterisk disappears
  - [ ] Can submit with empty catatan
- [ ] Select NOT_COMPLY
  - [ ] Red asterisk shows
  - [ ] Textarea border turns red
  - [ ] Must fill catatan before submit
  - [ ] Submit without catatan → Alert: "Catatan wajib diisi"

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 5.5 Submit Assessment
- [ ] Fill all required fields
- [ ] Kesimpulan: "PT TEST COMPANY 1 memenuhi kriteria kepatuhan"
- [ ] Rekomendasi: "Layak untuk persetujuan"
- [ ] Click "SIMPAN ASSESSMENT"
- [ ] Success message
- [ ] Data saved to database
- [ ] Status updates to reflect completion

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

---

### ✅ TEST 6: HASIL CETAK (PRINT OUTPUT)
**URL:** http://localhost/andrian/bank-kredit/print.php?id=1

#### 6.1 PDF Rendering
- [ ] Page loads without errors
- [ ] Professional layout
- [ ] No PHP errors/warnings visible

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 6.2 Page 1: Identitas & 5C
- [ ] Header: Bank Wonosobo, Nomor Surat
- [ ] Identitas: Nama, NIK, Alamat lengkap
- [ ] 5C Scoring: Character 90, Capacity 85, etc.
- [ ] Status Kelayakan: "LAYAK"
- [ ] Neraca: Aset, Hutang, Modal displayed

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 6.3 Page 1: Agunan & Kepatuhan
- [ ] Agunan Tanah: Lokasi, Luas, Sertifikat, Nilai
- [ ] Agunan Kendaraan: Merek, Tipe, Tahun, Plat
- [ ] Fasilitas Existing: 4 columns correctly displayed
- [ ] Hasil Assesmen Kepatuhan:
  - [ ] ✓ COMPLY (green background)
  - [ ] OR ✗ NOT_COMPLY (red background)
  - [ ] Catatan Hasil if exists

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 6.4 Page 2: Signature Section
- [ ] Title: "TANDA TANGAN & STEMPEL PEJABAT BANK"
- [ ] Officer cards for: Analis, Kasubag, Kabag, Kadiv
- [ ] Each card shows: [Stempel Area] + Name + Position
- [ ] Master pejabat data populated:
  - [ ] Analis: "Budi Santoso"
  - [ ] Kabag: "Siti Nurhaliza"
  - [ ] Kadiv: "Rudi Hermawan"
  - [ ] Direktur: "Bambang Suryanto" (for 600M)

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 6.5 No Timeline Visible
- [ ] Section "III. TIMELINE PROSES PERSETUJUAN" exists
- [ ] Timeline approval table is HIDDEN (commented out)
- [ ] Catatan threshold (500M rule) is HIDDEN
- [ ] Professional appearance maintained

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 6.6 Print to PDF
- [ ] Click "SIMPAN SEBAGAI PDF" button
- [ ] PDF opens in new tab
- [ ] PDF renders correctly (both pages)
- [ ] Officer data visible in signature boxes
- [ ] No corruption or missing content

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

---

### ✅ TEST 7: UPLOAD FOTO (AGUNAN PHOTO)
**URL:** http://localhost/andrian/bank-kredit/analis/detail.php?id=1

#### 7.1 Single Photo Upload
- [ ] Navigate to Upload Foto section
- [ ] Select 1 JPG file (<5MB)
- [ ] Click "Upload"
- [ ] Progress shows
- [ ] Success message
- [ ] Thumbnail appears in gallery

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 7.2 Multiple Photo Upload
- [ ] Upload 5 photos in batch
- [ ] All progress smoothly
- [ ] All thumbnails visible
- [ ] File count correct (5)

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 7.3 Photo Restrictions
- [ ] Try upload PNG file (should work)
- [ ] Try upload GIF file (should work)
- [ ] Try upload PDF file
  - [ ] Should be rejected
  - [ ] Error message: "File type not supported"
- [ ] Try upload >5MB file
  - [ ] Should be rejected
  - [ ] Error message: "File too large"

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 7.4 Photo Display in Print
**Go to:** print.php?id=1

- [ ] Photos visible in print output
- [ ] Up to 8 photos shown in grid
- [ ] Photos crop properly (no distortion)
- [ ] All photos download to PDF

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 7.5 Delete Photo
- [ ] Click delete (×) on photo thumbnail
- [ ] Confirmation dialog appears
- [ ] Photo removed from display
- [ ] Database updated
- [ ] File deleted from server

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

---

### ✅ TEST 8: REPAYMENT CAPACITY
**URL:** http://localhost/andrian/bank-kredit/analis/detail.php?id=1 → Repayment Tab

#### 8.1 Auto-Calculation
- [ ] Pendapatan Bulanan: 150M (from Omset)
- [ ] Pengeluaran: 60M (50M tetap + 10M hidup)
- [ ] Angsuran: 250M / 24 bulan = ~10.4M/bulan
- [ ] Remaining: 150M - 60M - 10.4M = 79.6M ✓

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 8.2 Ratios & Risk
- [ ] Debt-to-Income: (10.4M / 150M) × 100% = 6.9% (GOOD)
- [ ] Risk Level: GREEN (Low risk)
- [ ] LTV Ratio if calculated: (250M / 650M) = 38.5% (GOOD)

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 8.3 Validation
- [ ] Input negative income: Should reject or show warning
- [ ] Input expense > income: Risk level changes to RED
- [ ] Installment > 50% income: RED warning

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 8.4 Edge Cases
- [ ] Zero income: Cannot calculate, error message
- [ ] Very high expense (>100% income): RED risk
- [ ] Manual angsuran input: Recalculates ratios correctly

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

---

### ✅ TEST 9: SCORING 5C
**URL:** http://localhost/andrian/bank-kredit/analis/detail.php?id=1 → 5C Tab

#### 9.1 Input Scoring
- [ ] Character (0-100): 90
- [ ] Capacity (0-100): 85
- [ ] Capital (0-100): 80
- [ ] Collateral (0-100): 85
- [ ] Condition (0-100): 80

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 9.2 Auto-Calculation & Status
- [ ] Total Score: 90+85+80+85+80 = 420
- [ ] Status auto-set: "LAYAK" (total >= 400)
- [ ] Display: "Total Skor: 420 - Status: LAYAK"

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 9.3 Status Logic
Test with different scores:
- [ ] Total 415 (>400): LAYAK
- [ ] Total 375 (350-399): LAYAK_DENGAN_CATATAN
- [ ] Total 340 (<350): TIDAK_LAYAK

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 9.4 Display in Print
**Go to:** print.php?id=1

- [ ] Section: "Penilaian 5C"
- [ ] Character: 90
- [ ] Capacity: 85
- [ ] Capital: 80
- [ ] Collateral: 85
- [ ] Condition: 80
- [ ] Total: 420
- [ ] Status: LAYAK (green indicator)

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

---

### ✅ TEST 10: NERACA (BALANCE SHEET)
**URL:** http://localhost/andrian/bank-kredit/analis/detail.php?id=1 → Neraca Tab

#### 10.1 Input Neraca Components
- [ ] Kas: 50M
- [ ] Piutang: 100M
- [ ] Persediaan: 200M
- [ ] Aset Lancar: 350M (auto-sum)
- [ ] Aset Tetap: 150M
- [ ] Aset Lainnya: 25M
- [ ] Total Aset: 525M (auto-calculated)

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 10.2 Liability & Equity
- [ ] Hutang Lancar: 75M
- [ ] Hutang Jangka Panjang: 50M
- [ ] Modal: 400M
- [ ] Total Kewajiban & Modal: 525M (auto-calculated)

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 10.3 Validation: Balance Equation
- [ ] Total Aset = Total Kewajiban & Modal
- [ ] 525M = 525M ✓
- [ ] Save allowed
- [ ] If unequal: Error message & prevent save

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 10.4 Display in Print
**Go to:** print.php?id=1

- [ ] Section: "Laporan Neraca"
- [ ] All fields visible with values
- [ ] Professional table format
- [ ] Proper number formatting (Rp notation)

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 10.5 Financial Ratios
- [ ] Current Ratio: 350 / 75 = 4.67x (EXCELLENT)
- [ ] Debt-to-Equity: 125 / 400 = 0.31 (GOOD)
- [ ] Asset Turnover if displayed: Calculated correctly

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

---

### ✅ TEST 11: AGUNAN (COLLATERAL)
**URL:** http://localhost/andrian/bank-kredit/analis/detail.php?id=1 → Agunan Tab

#### 11.1 Jaminan Tanah Input
- [ ] Lokasi: "Jl. Test Property"
- [ ] Luas Tanah: 500 m²
- [ ] Luas Bangunan: 300 m²
- [ ] Status Sertifikat: "Sertifikat Hak Milik"
- [ ] Nilai Taksasi: 500M
- [ ] Nilai Pasar: 600M
- [ ] Catatan: "Property berupa bangunan komersial"
- [ ] Save successful

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 11.2 Jaminan Kendaraan Input
- [ ] Jenis: "Mobil"
- [ ] Merek: "Toyota"
- [ ] Tipe: "Avanza"
- [ ] Tahun: 2020
- [ ] Plat Nomor: "B 1234 ABC"
- [ ] Nilai Taksasi: 150M
- [ ] Nilai Pasar: 180M
- [ ] Save successful

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 11.3 Total Collateral Calculation
- [ ] Total Agunan = 500M (tanah) + 150M (kendaraan) = 650M
- [ ] Display: "Total Agunan: Rp 650.000.000"
- [ ] LTV: 250M / 650M = 38.5% (GOOD)

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 11.4 Add/Remove Collateral
- [ ] Add second jaminan tanah: Works
- [ ] Add second kendaraan: Works
- [ ] Delete jaminan: Works with confirmation
- [ ] Total recalculates after delete

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### 11.5 Display in Print
**Go to:** print.php?id=1

- [ ] Section: "Detail Jaminan"
- [ ] Tanah: Lokasi, Luas, Sertifikat, Nilai displayed
- [ ] Kendaraan: Merek, Tipe, Tahun, Plat, Nilai displayed
- [ ] Total Agunan: 650M
- [ ] LTV Ratio if shown: 38.5%

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

---

## 📊 ADDITIONAL TEST SCENARIOS

### Cross-Functional Tests

#### Multi-Step Workflow (Pengajuan #1)
- [ ] Analis input complete (5C, Neraca, Agunan, Photos)
- [ ] Submit to Kabag
- [ ] Kabag reviews & approves
- [ ] Kadiv reviews & approves
- [ ] Direktur final approval (for 600M case)
- [ ] Print works with all data
- [ ] Audit log shows all steps

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### Rejection & Revision Workflow
- [ ] Kabag rejects pengajuan #2
- [ ] Status: "Revisi"
- [ ] Notification to Analis
- [ ] Analis can edit again
- [ ] Analis resubmit
- [ ] Back in approval queue

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### Concurrent User Access
- [ ] Analis viewing & editing pengajuan #1
- [ ] Simultaneously: Kabag viewing pengajuan #2
- [ ] No data conflicts or race conditions
- [ ] Both can save independently

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

#### Database Integrity
- [ ] After full workflow: Run `CHECK TABLE` on key tables
- [ ] No orphaned records
- [ ] Foreign keys intact
- [ ] Audit log complete

**Expected:** ✓ PASSED / ❌ FAILED / ⚠️ ISSUE

---

## 🐛 BUG TRACKING

### Template for Found Issues

```
BUG #[Number]
Status: [New / In Progress / Fixed / Closed]
Severity: [Critical / High / Medium / Low]
Component: [Module/Feature]
Description: [What went wrong]
Steps to Reproduce: [Clear steps]
Expected Result: [What should happen]
Actual Result: [What actually happened]
Screenshot/Log: [If applicable]
Fixed By: [If resolved]
Notes: [Additional info]
```

### Example Issues to Watch For:

1. **Database validation errors** - Check logs for constraint violations
2. **File upload failures** - Check directory permissions
3. **Calculation errors** - Verify math in repayment/ratios
4. **Display issues** - Check PDF rendering, missing data
5. **Permission/authorization** - Users accessing unauthorized data
6. **Data consistency** - Mismatches between forms and print output

---

## ✅ TEST COMPLETION CHECKLIST

- [ ] All 11 test cases executed
- [ ] All sub-tests documented (PASSED/FAILED/ISSUE)
- [ ] Screenshots taken for any failures
- [ ] Bug log completed with severity ratings
- [ ] Critical bugs fixed
- [ ] Non-critical issues documented for backlog
- [ ] Full workflow test passed end-to-end
- [ ] Print output verified and looks professional
- [ ] Database integrity verified
- [ ] Audit logs reviewed and complete
- [ ] Performance acceptable (page load < 3s)
- [ ] No unhandled PHP errors/warnings
- [ ] Security: Authorization working correctly
- [ ] Final sign-off obtained

---

## 📋 TEST SUMMARY TEMPLATE

**Date Tested:** [Date]  
**Tester Name:** [Name]  
**Environment:** Development (Laragon)  
**Test Data Version:** v1.0  

**Results:**
- Tests Passed: [X]/11
- Tests Failed: [Y]/11
- Issues Found: [Z]
- Critical Issues: [Count]
- Recommendations: [Text]

**Sign Off:**
- [ ] Ready for UAT
- [ ] Ready for Production
- [ ] Needs Fixes (see bug list)

---

**End of Testing Checklist**
