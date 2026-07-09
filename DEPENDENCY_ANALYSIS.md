# 📊 ANALISIS DEPENDENCIES MODUL APLIKASI ANALISA KREDIT

**Tanggal**: 12 Juni 2026  
**Tujuan**: Identifikasi modul yang terdampak oleh perubahan  
**Status**: ✓ SELESAI  

---

## 📋 DAFTAR ISI
1. [Critical Core Dependencies](#critical-core-dependencies)
2. [Feature Module Dependencies](#feature-module-dependencies)
3. [Database Table Dependencies](#database-table-dependencies)
4. [File Modification Impact Analysis](#file-modification-impact-analysis)
5. [Change Risk Matrix](#change-risk-matrix)

---

## CRITICAL CORE DEPENDENCIES

### 1. includes/functions.php
**Status**: 🔴 **CRITICAL** - Digunakan oleh SEMUA modul

**Definisi Fungsi Penting:**
```
- isLoggedIn() 
  Used by: ALL entry points, ALL role modules
  Risk: Session check pada SEMUA halaman

- requireSameRole($role)
  Used by: ALL role-based modules
  Risk: Access control untuk SETIAP halaman

- getHierarchy()
  Used by: Approval chain logic, findNextTarget()
  Risk: Approval workflow sequence

- findNextTarget($role, $pdo, $amount)
  Used by: Approval processing (proses.php for all roles)
  Risk: Auto-routing to next level

- canAccessPengajuanDetail($pengajuanRow)
  Used by: detail.php, print.php
  Risk: Read access untuk detail pengajuan

- canEditPengajuan($pengajuanRow)
  Used by: detail.php, edit forms
  Risk: Edit permission untuk form

- checkComplianceAssessmentStatus($pdo, $id_pengajuan)
  Used by: Approval workflow (blocking logic)
  Risk: Blocks approval if assessment not complete

- getMaxApprovalLevel($jumlah_kredit)
  Used by: findNextTarget() untuk amount-based routing
  Risk: 500M threshold untuk direktur escalation

- auditLog($pdo, $userId, $activity)
  Used by: ALL modules untuk activity tracking
  Risk: Compliance tracking

- formatRupiah($angka)
  Used by: detail.php, print.php, all form displays
  Risk: Display formatting
```

**Impact of modification:**
- ANY change → affects ALL modules
- Breaking change → entire system fails
- **RECOMMENDATION**: Very conservative changes, extensive testing

---

### 2. config/database.php
**Status**: 🔴 **CRITICAL** - Basis koneksi untuk SEMUA

**Bagian Penting:**
```
- PDO Connection: $pdo object
  Used by: SEMUA file yang execute query

- BASE_URL constant
  Used by: Header redirect, navigation links
  Risk: Broken links if changed

- BK_PRODUCTION constant
  Used by: File upload validation, error handling
  Risk: Security in production mode

- Schema migration: bankKreditEnsureSchema($pdo)
  Used by: Auto-create/update tabel pada startup
  Risk: Database consistency
```

**Impact of modification:**
- Connection change → database inaccessible
- BASE_URL change → all redirects broken
- Schema migration error → application crash
- **RECOMMENDATION**: Test thoroughly in test environment first

---

### 3. helpers/credit_helper.php
**Status**: 🟡 **HIGH** - Digunakan oleh form inputs & analysis

**Key Functions:**
```
- hitung_6c($data)
  Used by: analis/form_umum.php, analis/form_pppk.php
  Risk: 6C scoring calculation

- calculate_repayment_capacity()
  Used by: analis/form_umum.php (cash flow calculations)
  Risk: Repayment analysis

- validate_kriteria()
  Used by: Input validation
  Risk: Score validation (1-5 range)

- get_grade(), klasifikasi_6c()
  Used by: Score display, analysis output
  Risk: Grade classification logic
```

**Impact of modification:**
- Change formula → affect ALL 6C scoring
- Change repayment logic → cash flow analysis changes
- **USED BY**: analis/form_umum.php, detail.php, print.php
- **RECOMMENDATION**: Update test cases when modifying calculations

---

### 4. includes/analis_prefill_data.php
**Status**: 🟡 **MEDIUM** - Digunakan saat form loading

**Fungsi:**
```
- analisLoadPrefillBundle($pdo, $edit_id)
  Used by: analis/input.php (edit mode)
  Risk: Pre-populate form data untuk edit
```

**Impact of modification:**
- Used only in edit mode (analis/input.php)
- Low-risk if changes only affect data retrieval
- **RECOMMENDATION**: Test edit functionality after changes

---

## FEATURE MODULE DEPENDENCIES

### Feature #1: Analisa Data Usaha
```
Entry Points:
├─ analis/input.php
│  ├─ includes/functions.php (requireSameRole)
│  ├─ includes/analis_prefill_data.php
│  └─ branches to:
│     ├─ form_umum.php (general)
│     ├─ form_pppk.php (PPPK-specific)
│     ├─ form_desa.php (village)
│     └─ form_cashcolateral.php (cash)
│
└─ analis/save_section.php
   └─ includes/functions.php

Database Tables:
└─ pengajuan_kredit
   └─ fields: nama_usaha, bidang_usaha, lama_usaha, omset_per_bulan

Data Flow:
User Input → Form Validation → Database Insert/Update → Display

Affected Display:
├─ detail.php (display business data - read-only)
├─ analis/dashboard.php (list pengajuan)
└─ print.php (print business data)

Modification Impact:
- Change form fields → update analis/form_*.php AND detail.php
- Change database fields → update INSERT/UPDATE queries
- Change validation → update form_umum.php, analis/save_section.php
- Risk Level: MEDIUM (affects form input flow)
```

---

### Feature #2: Repayment Capacity
```
Entry Points:
└─ analis/form_umum.php (input omset, biaya, laba, dll)

Calculation:
├─ helpers/credit_helper.php (calculate_repayment_capacity function)
├─ Formula: Income - (Operating Expenses + Living Expenses + Other Installments)
└─ JavaScript calculation in form_umum.php (preview)

Database Tables:
└─ pengajuan_kredit
   └─ fields: omset_per_bulan, biaya_operasional, biaya_hidup, cicilan_lain,
                repayment_capacity (RESULT), net_cashflow, total_pengeluaran_tetap

Storage Location:
├─ pengajuan_kredit.repayment_capacity (final calculated value)
├─ pengajuan_kredit.net_cashflow
└─ pengajuan_kredit.total_pengeluaran_tetap

Used For:
├─ Approval decision basis
├─ detail.php display (line 259: "Repayment Cap.")
├─ print.php (financial metrics section)
└─ Cash flow analysis

Modification Impact:
- Change formula → update helpers/credit_helper.php AND analis/form_umum.php JS
- Change validation → need to update both server & client side
- Change display format → update detail.php, print.php
- Risk Level: HIGH (affects approval decision logic)
- Recommend: Extensive testing, verification against banking standards
```

---

### Feature #3: Agunan (Collateral)
```
Entry Points:
└─ analis/input_agunan.php (form input)

Database Tables:
├─ jaminan_tanah_bangunan (land/building)
│  └─ Multi-item support (can have multiple records per pengajuan)
├─ jaminan_kendaraan (vehicle)
│  └─ Multi-item support
└─ jaminan_emas (gold)
   └─ Multi-item support

Data Structure:
Each collateral type has:
├─ nilai_pasar (market value)
├─ nilai_taksasi (appraisal value, 70-75%)
├─ nilai_likuidasi (liquidation value, 70% of taksasi)
└─ Various property-specific fields

Calculation Logic (in input_agunan.php):
Tanah & Bangunan:
- Nilai Pasar = (Luas × Harga/unit) + (Building m² × Harga/m²)
- Nilai Taksasi = Nilai Pasar × persen_taksasi (70-75% per kategori)
- Nilai Likuidasi = Nilai Taksasi × 70%

Kendaraan: Direct nilai pasar, taksasi, likuidasi input

Display Locations:
├─ detail.php (Section IV, lines 268-517)
│  └─ Shows all jaminan per type
│  └─ Shows total summary (rekapitulasi)
├─ print.php (collateral section)
└─ analis/dashboard.php (list view)

Modification Impact:
- Change calculation logic → update input_agunan.php formulas
- Add new collateral type → create new jaminan_* table
- Change validation → update input_agunan.php, JavaScript
- Change display → update detail.php, print.php
- Risk Level: MEDIUM-HIGH (multi-item complexity)
- Impact Scope: Cascades to detail, print, approval workflow
```

---

### Feature #4: Neraca (Balance Sheet)
```
Entry Points:
└─ analis/form_umum.php (balance sheet input)

Database Table:
└─ analisa_neraca
   ├─ Aktiva (Assets): kas, tabungan, tanah, kendaraan, stok, lainnya
   └─ Pasiva (Liabilities): hutang_bank, hutang_lain, modal

Data Type:
├─ Financial analysis
├─ One record per pengajuan
└─ JSON not used (standard numeric columns)

Stored Queries:
INSERT INTO analisa_neraca (id_pengajuan, aktiva_kas, ..., total_pasiva) VALUES (...)
UPDATE analisa_neraca SET aktiva_kas = ?, ... WHERE id_pengajuan = ?

Display Locations:
├─ detail.php (Section III, lines 525-570)
├─ print.php (balance sheet section)
└─ analis/dashboard.php

Modification Impact:
- Add new asset type → update analisa_neraca schema AND form_umum.php
- Change calculation (total) → minimal impact
- Change validation rules → update form_umum.php
- Risk Level: LOW-MEDIUM (straightforward numeric data)
- Impact Scope: Form, display, print
```

---

### Feature #5: Scoring 5C (6C Analysis)
```
Entry Points:
└─ analis/form_umum.php (6C input section)

Calculation Engine:
└─ helpers/credit_helper.php
   ├─ hitung_6c($data) - Main calculation
   ├─ get_grade($skor) - Convert score to grade
   └─ klasifikasi_6c($rata) - Classify average

Database Table:
└─ analisa_5c
   ├─ Scores (1-5): character, capacity, capital, collateral, condition, constraint
   ├─ Notes: catatan_character, catatan_capacity, catatan_capital, etc.
   ├─ Output: total_score, rekomendasi
   └─ One record per pengajuan

Scoring Framework:
Scale 1-5:
1 = Sangat Baik (A grade)
2 = Baik (B grade)
3 = Cukup (C grade)
4 = Kurang (D grade)
5 = Sangat Kurang (E grade)

Average Classification:
<= 1.5 = Sangat Baik
<= 2.5 = Baik
<= 3.5 = Cukup
<= 4.5 = Kurang
> 4.5 = Sangat Kurang

Display Locations:
├─ detail.php (6C analysis section)
├─ print.php (full score display)
├─ analis/dashboard.php (summary)
└─ approval interfaces (approval decision basis)

Used For Approval Decision:
├─ Manual review of 6C grades
├─ Recommendation field (based on average)
└─ Risk assessment

Modification Impact:
- Change scoring scale → affects ENTIRE approval framework
- Change formula/classification → affects all pengajuan assessments
- Change notes fields → low impact
- Risk Level: CRITICAL (affects approval decision)
- Impact Scope: Widespread (form, calculation, display, approval)
- Recommendation: DO NOT CHANGE without business approval
```

---

### Feature #6: Kepatuhan (Compliance Assessment)
```
Entry Points:
├─ analis/compliance_assessment.php (initial assessment)
└─ kepatuhan/assesmen.php (compliance review)

Database Table:
└─ assessment_kepatuhan
   ├─ id_user (assessing officer)
   ├─ tanggal_assessment (date)
   ├─ checklist_data (JSON) - Compliance checklist items
   ├─ fasilitas_existing (JSON) - Existing facilities
   ├─ catatan_existing (JSON) - Notes
   ├─ kesimpulan (conclusion)
   ├─ rekomendasi (recommendation)
   ├─ marketing (sales note)
   └─ Timestamps

Workflow Integration:
└─ Part of approval chain (STAGE 1: KEPATUHAN)
   ├─ Blocks if checkComplianceAssessmentStatus() returns is_complete=false
   ├─ Must have checklist_data AND kesimpulan filled
   └─ Auto-routes to kasubag_analis when complete

Blocking Logic:
Function: checkComplianceAssessmentStatus($pdo, $id_pengajuan)
Returns:
├─ exists: true/false (record exists)
└─ is_complete: true/false (has required data)

Location of block: findNextTarget() approval logic

Display Locations:
├─ detail.php (show assessment summary)
├─ print.php (full compliance data)
├─ kepatuhan/proses.php (approval interface)
└─ analis/compliance_assessment.php (form)

Modification Impact:
- Change checklist items → update both assessment forms
- Change blocking logic → update functions.php (CRITICAL)
- Change display → update detail.php, print.php, forms
- Risk Level: CRITICAL (blocks entire approval workflow)
- Impact Scope: Approval chain, form, display
- Recommendation: Test blocking logic thoroughly
```

---

### Feature #7: Memo Internal
```
Storage:
└─ assessment_kepatuhan table
   ├─ kesimpulan (conclusion)
   ├─ rekomendasi (recommendation)
   ├─ catatan_existing (notes)
   └─ marketing (marketing note)

Display Format:
└─ analis/compliance_assessment.php
   ├─ CSS classes: .memo-container, .memo-header, .memo-body
   ├─ Formal format: Times New Roman, double-line borders
   └─ Sections: header, meta, body, signature

Print Output:
└─ print.php (shows compliance section with memo format)

Modification Impact:
- Change memo format → update CSS in compliance_assessment.php
- Change fields → update analis/compliance_assessment.php, kepatuhan/assesmen.php
- Change content display → minimal impact (display logic only)
- Risk Level: LOW (display only, no logic impact)
- Impact Scope: Form styling, print format
```

---

### Feature #8: Hasil Cetak (Print/Export)
```
Main Controller:
└─ print.php

Data Fetching:
print.php fetches:
├─ pengajuan_kredit (main data)
├─ analisa_neraca (balance sheet)
├─ analisa_5c (6C scores)
├─ assessment_kepatuhan (compliance)
├─ jaminan_tanah_bangunan (collateral)
├─ jaminan_kendaraan (collateral)
├─ jaminan_emas (collateral)
└─ approval_kredit (approval timeline)

Parameters:
├─ id (pengajuan ID) - REQUIRED
├─ paper_size (A4|F4) - optional, default A4
└─ from (detail|dashboard|riwayat) - optional, for tracking

Output Format:
├─ HTML (viewed in browser)
├─ CSS for print layout
└─ Can use print-to-PDF function in browser

Access Control:
├─ All authenticated users
├─ Analysts: only own submissions
├─ Approvers/Superadmin: all submissions

Modification Impact:
- Change data display → update print.php queries & layout
- Change calculation → affects print output (e.g., 6C scores)
- Change paper format → update CSS
- Risk Level: MEDIUM (display only, pulls from other modules)
- Impact Scope: Depends on what data source changed
- Dependencies: All 8 features contribute data to print
```

---

## DATABASE TABLE DEPENDENCIES

### Dependency Hierarchy
```
┌─────────────────────────────────────────────────────────┐
│        pengajuan_kredit (CORE)                          │
│  Stores all loan application data & workflow status     │
└──────────────────────────────────┬──────────────────────┘
       ↓ Foreign Key Relationships (CASCADE on DELETE)
       │
       ├─→ jaminan_tanah_bangunan
       │   └─ Land/building collateral (multi-item)
       │
       ├─→ jaminan_kendaraan
       │   └─ Vehicle collateral (multi-item)
       │
       ├─→ jaminan_emas
       │   └─ Gold collateral (multi-item)
       │
       ├─→ analisa_neraca
       │   └─ Balance sheet analysis
       │
       ├─→ analisa_5c
       │   └─ 6C scoring analysis
       │
       ├─→ assessment_kepatuhan
       │   └─ Compliance assessment (BLOCKS approval if not complete)
       │
       ├─→ angsuran_bank_lain
       │   └─ Other bank installments (for repayment calc)
       │
       ├─→ approval_kredit
       │   └─ Approval history & decisions
       │
       └─→ notifications
           └─ Workflow notifications
```

### Table Modification Impact

#### pengajuan_kredit (CRITICAL)
**If DELETED**: ALL related data is CASCADE deleted
```
Impact: 
- jaminan_tanah_bangunan records deleted
- jaminan_kendaraan records deleted
- jaminan_emas records deleted
- analisa_neraca deleted
- analisa_5c deleted
- assessment_kepatuhan deleted
- angsuran_bank_lain deleted
- notifications deleted
- approval_kredit deleted (FK constraint)
```

**If schema MODIFIED**:
```
Add new column:
├─ Update all form inputs (analis/form_*.php)
├─ Update INSERT/UPDATE queries
├─ Update display (detail.php)
├─ Update print (print.php)
└─ Update imports (config/database.php schema migration)

Remove column:
├─ Check all usages (grep for column name)
├─ Update all queries
├─ Update forms
└─ Data loss risk!

Change column type:
├─ Risk of data corruption
├─ Need migration script
└─ Test thoroughly
```

#### assessment_kepatuhan (BLOCKING TABLE)
**If schema changed or data missing**:
```
Impact:
├─ checkComplianceAssessmentStatus() may fail
├─ Approval workflow BLOCKED
├─ Application cannot progress past kepatuhan stage
└─ CRITICAL: approval chain breaks
```

#### jaminan_* tables (CASCADE on DELETE)
**If schema changed**:
```
Impact:
├─ detail.php display affected (section IV)
├─ input_agunan.php needs update
├─ Calculation logic affected (nilai_pasar, nilai_taksasi, dll)
├─ Print output affected
└─ Collateral values for approval decision change
```

#### analisa_5c (SCORING TABLE)
**If schema changed**:
```
Impact:
├─ Scoring logic affected (hitung_6c)
├─ All displays affected (detail.php, print.php)
├─ Approval decision basis changed
├─ Historical data may become invalid
└─ CRITICAL: scoring framework changes
```

---

## FILE MODIFICATION IMPACT ANALYSIS

### High-Risk Files (Change affects multiple modules)
```
🔴 CRITICAL:
├─ includes/functions.php
│  └─ Used by: ALL 40+ entry points
│  └─ Changes affect: Auth, approval, access control, formatting
│  └─ Testing needed: Full regression test
│
├─ config/database.php
│  └─ Used by: ALL database operations
│  └─ Changes affect: Connection, migration, schema
│  └─ Testing needed: Database connectivity check
│
└─ helpers/credit_helper.php
   └─ Used by: Form inputs, calculations, scoring
   └─ Changes affect: 6C, repayment, all analysis
   └─ Testing needed: Calculation verification

🟠 HIGH:
├─ analis/form_umum.php
│  └─ Used by: Business data, repayment, 6C input
│  └─ Changes affect: Data collection, calculation
│  └─ Testing needed: Form validation, calculation
│
├─ detail.php
│  └─ Used by: ALL role dashboards (read-only view)
│  └─ Changes affect: Display, permission checks
│  └─ Testing needed: Display verification, access control
│
└─ print.php
   └─ Used by: Export functionality
   └─ Changes affect: Print output, data export
   └─ Testing needed: Print layout, data completeness
```

### Medium-Risk Files (Change affects specific features)
```
🟡 MEDIUM:
├─ analis/input_agunan.php
│  └─ Used by: Collateral input only
│  └─ Changes affect: Agunan feature only
│  └─ Testing needed: Collateral calculation, multi-item
│
├─ analis/compliance_assessment.php
│  └─ Used by: Compliance input
│  └─ Changes affect: Assessment, blocking logic
│  └─ Testing needed: Checklist, blocking logic
│
└─ kepatuhan/assesmen.php
   └─ Used by: Compliance review
   └─ Changes affect: Assessment, approval progression
   └─ Testing needed: Workflow progression
```

### Low-Risk Files (Limited scope)
```
🟢 LOW:
├─ analis/form_pppk.php
│  └─ Used by: PPPK loan type only
│  └─ Changes affect: PPPK feature only
│
├─ analis/form_desa.php
│  └─ Used by: Village official type only
│  └─ Changes affect: Village feature only
│
└─ All role-specific dashboards (kabag_*, kadiv_*, etc.)
   └─ Used by: Role dashboards only
   └─ Changes affect: Display only
```

---

## CHANGE RISK MATRIX

### Risk Assessment by Change Type

| Change Type | Risk Level | Affected Modules | Testing Needed | Notes |
|------------|-----------|-----------------|----------------|-------|
| **Approval workflow** | 🔴 CRITICAL | approval_kredit, findNextTarget(), all role modules | Full regression | Breaks entire system if wrong |
| **Compliance blocking logic** | 🔴 CRITICAL | assessment_kepatuhan, checkComplianceAssessmentStatus() | Workflow test | Blocks approval if missing |
| **6C scoring formula** | 🔴 CRITICAL | hitung_6c(), analisa_5c, detail.php, print.php | Scoring test | Affects approval decisions |
| **Repayment capacity formula** | 🔴 CRITICAL | calculate_repayment_capacity(), cash flow analysis | Financial test | Affects lending decision |
| **Database schema (core)** | 🔴 CRITICAL | ALL modules | Migration test | Risk of data loss |
| **Collateral calculation** | 🟠 HIGH | input_agunan.php, detail.php, print.php, approval | Calculation test | Affects collateral valuation |
| **Balance sheet display** | 🟠 HIGH | analisa_neraca, form_umum.php, detail.php | Display test | Affects financial analysis |
| **Form validation** | 🟠 HIGH | All form_*.php, save_section.php | Input test | Affects data quality |
| **Access control** | 🟠 HIGH | functions.php, all entry points | Access test | Security critical |
| **Print layout** | 🟡 MEDIUM | print.php, CSS | Print test | Display only |
| **Display formatting** | 🟡 MEDIUM | detail.php, dashboard templates | Display test | UI only |
| **Memo format** | 🟢 LOW | compliance_assessment.php CSS | Style test | Display only |
| **Landing page** | 🟢 LOW | index.php | Navigation test | Limited scope |

---

## MODUL INTERDEPENDENCIES SUMMARY

### Features by Risk Level if Modified

#### 🔴 DO NOT MODIFY WITHOUT EXTENSIVE TESTING:
```
1. Approval Workflow Logic
   └─ functions.php: getHierarchy(), findNextTarget(), getMaxApprovalLevel()
   └─ Impact: Entire 6-level approval chain breaks

2. Compliance Assessment Blocking
   └─ functions.php: checkComplianceAssessmentStatus()
   └─ Impact: Approval cannot progress past kepatuhan stage

3. 6C Scoring Calculation
   └─ helpers/credit_helper.php: hitung_6c(), klasifikasi_6c()
   └─ Impact: Approval decision basis changes

4. Repayment Capacity Formula
   └─ helpers/credit_helper.php: calculate_repayment_capacity()
   └─ Impact: Cash flow analysis accuracy
```

#### 🟠 REQUIRES CAREFUL TESTING:
```
1. Collateral Multi-Item Logic
   └─ analis/input_agunan.php, detail.php, print.php
   └─ Impact: Collateral valuation for approval

2. Balance Sheet Analysis
   └─ analisa_neraca table, form_umum.php
   └─ Impact: Financial analysis accuracy

3. Permission/Access Control
   └─ functions.php: canAccessPengajuanDetail(), canEditPengajuan()
   └─ Impact: Security, data access
```

#### 🟡 LOWER RISK BUT MONITOR:
```
1. Form Validation
   └─ All form_*.php files
   └─ Impact: Data quality, calculation accuracy

2. Display & Formatting
   └─ detail.php, print.php, dashboard templates
   └─ Impact: UI/UX, readability

3. Specific Loan Types (PPPK, Desa, etc.)
   └─ form_pppk.php, form_desa.php, respective partials
   └─ Impact: Limited to specific loan type
```

---

## TESTING CHECKLIST FOR MODIFICATIONS

### Before deploying ANY changes:

- [ ] **Unit Tests**: Test individual functions (calculation, validation)
- [ ] **Integration Tests**: Test data flow across modules
- [ ] **Approval Workflow**: Test all 6 approval stages
- [ ] **Compliance Blocking**: Verify assessment blocks approval if incomplete
- [ ] **Collateral Multi-Item**: Test multiple collateral items per application
- [ ] **Print Output**: Verify all data displays correctly
- [ ] **Access Control**: Test role-based access for each role
- [ ] **Data Integrity**: Verify FK constraints, cascading deletes
- [ ] **Audit Logging**: Verify all actions logged
- [ ] **Calculation Accuracy**: 
  - [ ] Repayment capacity formula
  - [ ] Collateral valuations
  - [ ] 6C scoring & classification
  - [ ] Cash flow analysis
- [ ] **Backward Compatibility**: Existing data works with new code
- [ ] **Browser Compatibility**: Works in all supported browsers
- [ ] **Database Backup**: Verify backup exists before production deployment

---

**Audit Dependencies Completed**: 12 Juni 2026  
**Status**: ✓ READY FOR MODIFICATION PLANNING
