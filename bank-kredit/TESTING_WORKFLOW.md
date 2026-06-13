# PANDUAN TESTING APPROVAL WORKFLOW

---

## 🧪 SCENARIO 1: NORMAL APPROVAL FLOW (All Step Approve)

### Setup
```
User: analis
Role: analis
Status: Aktif (status_jabatan = 'aktif')
```

### Step 1: Prepare Test Data
**Create test applicant database (optional - use existing or create new)**

Or use existing applicant by ID.

### Step 2: Login as Analis
```
URL: localhost/andrian/bank-kredit/analis/input.php
User: analis
Password: password
```

### Step 3: Fill & Submit Form
1. Create new or edit existing draft
2. Fill all form sections:
   - Data Pemohon
   - Data Usaha / Penghasilan
   - Struktur Kredit
   - Agunan
   - Neraca (if umum jenis)
   - Analisa 6C
3. Click "Kirim" at final section
   - Expected: Success message "Pengajuan berhasil disubmit!"

### Step 4: Verify State Changed

**Open any SQL client and run**:
```sql
SELECT id_pengajuan, nama_debitur, status_pengajuan, posisi_saat_ini, 
       last_reject_level, revision_count
FROM pengajuan_kredit
WHERE id_pengajuan = [ID_FROM_STEP_3]
LIMIT 1;
```

**Expected Result**:
```
id_pengajuan: [YOUR_ID]
status_pengajuan: 'diajukan' or 'proses'
posisi_saat_ini: 'kabag_analis'  ← Should be next role!
last_reject_level: NULL
revision_count: 0
```

### Step 5: Login as Kabag Analis
```
URL: localhost/andrian/bank-kredit/kabag_analis/proses.php
User: kabag_analis
Password: password
```

**Expected**: Your test application appears in the table!

### Step 6: Click "Detail" then "Proses"
1. Review applicant data
2. Click "Proses" button
3. Modal appears:
   - Fill optional catatan (notes)
   - Select Decision: "Setuju" (Approve)
   - Click "Konfirm"
   - Expected: Success "Pengajuan disetujui dan diteruskan ke KABAG_KREDIT"

### Step 7: Verify Next Stage Received
```sql
SELECT posisi_saat_ini FROM pengajuan_kredit WHERE id_pengajuan = [ID];
```

**Expected**: `posisi_saat_ini = 'kabag_kredit'`

### Step 8: Login as Kabag Kredit
```
URL: localhost/andrian/bank-kredit/kabag_kredit/proses.php
User: kabag_kredit
Password: password
```

**Expected**: Your application appears!

### Step 9: Repeat for Each Role
- Kabag Kredit → approve → kadiv_kredit
- Kadiv Kredit → approve → direksi
- Direksi → approve → FINAL

### Step 10: Verify Final Status
```sql
SELECT status_pengajuan, posisi_saat_ini
FROM pengajuan_kredit WHERE id_pengajuan = [ID];
```

**Expected Final State**:
```
status_pengajuan: 'disetujui'
posisi_saat_ini: 'selesai'
```

✅ **COMPLETE FLOW SUCCESSFUL**

---

## 🔄 SCENARIO 2: REVISION REQUEST

### Setup
Same as Scenario 1, but at one stage request revision instead of approval.

### Step 1-5: Same as Scenario 1 (through Kabag Analis Dashboard)

### Step 6: Request Revision Instead of Approval
1. Login as Kabag Analis
2. Open application detail
3. Click "Proses" button
4. Select Decision: "Revisi" ← (NOT Setuju)
5. Fill catatan with revision notes, e.g., "Lampiran SK tidak lengkap"
6. Click "Konfirm"
7. Expected: Message "Pengajuan dikembalikan untuk revisi ke analis."

### Step 7: Verify Returned to Analis
```sql
SELECT status_pengajuan, posisi_saat_ini, last_revision_by, 
       last_revision_at, catatan_revisi
FROM pengajuan_kredit WHERE id_pengajuan = [ID];
```

**Expected**:
```
status_pengajuan: 'revisi'
posisi_saat_ini: 'analis'
last_revision_by: [ID_OF_KABAG_ANALIS]
last_revision_at: 2026-04-04 14:30:00
catatan_revisi: 'Lampiran SK tidak lengkap'
```

### Step 8: Login as Analis & See Revision
1. Login as analis
2. Go to analis/input.php
3. Search & open application
4. Status shows: "revisi" with yellow banner
5. Shows catatan_revisi from kabag_analis

### Step 9: Edit Application
1. Modify relevant fields
2. Re-submit (Kirim Ulang button)

### Step 10: Verify Resume Logic
```sql
SELECT posisi_saat_ini, last_reject_level
FROM pengajuan_kredit WHERE id_pengajuan = [ID];
```

**Expected After Resubmit**:
```
posisi_saat_ini: 'kabag_analis'  ← Goes back to WHO REJECTED
last_reject_level: 'kabag_analis'
```

✅ **RESUMES FROM kabag_analis, NOT FROM START**

---

## ❌ SCENARIO 3: REJECTION

### Setup
Same as Scenario 1

### Step 1-5: Lead to Kabag Analis Dashboard

### Step 6: Reject Application
1. Click "Proses"
2. Select Decision: "Tolak" ← (NOT Setuju)
3. Fill rejection reason, e.g., "Nominal request terlalu besar untuk kategori usaha"
4. Click "Konfirm"
5. Expected: Message "Pengajuan ditolak dan dikembalikan ke analis."

### Step 7: Verify Rejection Logged
```sql
SELECT status_pengajuan, posisi_saat_ini, last_reject_level, 
       alasan_penolakan, revision_count
FROM pengajuan_kredit WHERE id_pengajuan = [ID];
```

**Expected**:
```
status_pengajuan: 'ditolak'
posisi_saat_ini: 'analis'
last_reject_level: 'kabag_analis'
alasan_penolakan: 'Nominal request terlalu besar untuk kategori usaha'
```

### Step 8: View Approval Timeline
```sql
SELECT level_approval, keputusan, catatan, tanggal_approval
FROM approval_kredit
WHERE id_pengajuan = [ID]
ORDER BY tanggal_approval ASC;
```

**Expected Records**:
```
1. level: 'analis',       keputusan: 'setuju',  catatan: 'Pengajuan lengkap.'
2. level: 'kabag_analis', keputusan: 'tolak',   catatan: 'Nominal request...'
```

### Step 9: Analis Resubmit After Rejection
1. Login as analis
2. Edit application (fix the issues)
3. Click "Kirim Ulang"

### Step 10: Verify Resumes to Rejected Stage
```sql
SELECT posisi_saat_ini FROM pengajuan_kredit WHERE id_pengajuan = [ID];
```

**Expected**: `posisi_saat_ini = 'kabag_analis'` (goes back to rejecting role)

✅ **REJECTION FLOW COMPLETE**

---

## ⏭️ SCENARIO 4: AUTO-SKIP INACTIVE STAFF

### Setup: Make Kadiv Kredit Inactive
```sql
UPDATE users 
SET status_jabatan = 'sakit' 
WHERE username = 'kadiv_kredit';
```

### Step 1-5: Normal flow through kabag_kredit

### Step 6: Kabag Kredit Approves
- Application should skip kadiv_kredit
- Directly go to direksi

### Step 7: Verify Skip
```sql
SELECT level_approval, keputusan, is_auto_skip
FROM approval_kredit
WHERE id_pengajuan = [ID]
ORDER BY tanggal_approval ASC;
```

**Expected**:
```
1. analis → setuju
2. kabag_analis → setuju
3. kabag_kredit → setuju
...
4. kadiv_kredit → 'eskalasi_otomatis' (is_auto_skip=1)  ← AUTO-SKIPPED!
5. direksi → ready for approval (posisi_saat_ini='direksi')
```

### Step 8: Direksi Receives It
1. Login as direksi
2. Application appears in dashboard
3. No kadiv_kredit in approval timeline (only auto-skip entry)

### Step 9: Restore Kadiv Status
```sql
UPDATE users 
SET status_jabatan = 'aktif' 
WHERE username = 'kadiv_kredit';
```

✅ **AUTO-SKIP WORKS**

---

## 📊 SQL QUERIES FOR MONITORING

### Query 1: Current Applications in Pipeline
```sql
SELECT 
    pk.id_pengajuan,
    pk.nama_debitur,
    pk.status_pengajuan,
    pk.posisi_saat_ini,
    u.username,
    pk.tanggal_pengajuan
FROM pengajuan_kredit pk
LEFT JOIN users u ON u.username = pk.posisi_saat_ini
WHERE pk.status_pengajuan IN ('proses','diajukan','kasubag','kabag','kadiv','direksi')
ORDER BY pk.tanggal_pengajuan DESC;
```

### Query 2: Specific Application Status
```sql
SELECT 
    pk.id_pengajuan,
    pk.nama_debitur,
    pk.status_pengajuan,
    pk.posisi_saat_ini,
    pk.last_reject_level,
    pk.revision_count,
    pk.last_revision_at,
    COUNT(ak.id_approval) as total_decisions
FROM pengajuan_kredit pk
LEFT JOIN approval_kredit ak ON ak.id_pengajuan = pk.id_pengajuan
WHERE pk.id_pengajuan = 5  -- Replace with actual ID
GROUP BY pk.id_pengajuan;
```

### Query 3: Approval Timeline for Specific Application
```sql
SELECT 
    ak.id_approval,
    u.nama as decided_by,
    ak.level_approval,
    ak.keputusan,
    ak.catatan,
    ak.tanggal_approval,
    ak.is_auto_skip
FROM approval_kredit ak
LEFT JOIN users u ON u.id_user = ak.id_user
WHERE ak.id_pengajuan = 5  -- Replace with actual ID
ORDER BY ak.tanggal_approval ASC;
```

### Query 4: Pending Items for Each Role
```sql
-- Pending for Kabag Analis
SELECT COUNT(*) 
FROM pengajuan_kredit 
WHERE posisi_saat_ini = 'kabag_analis' 
AND status_pengajuan IN ('proses','diajukan','kasubag','kabag','kadiv','direksi');

-- Pending for Kabag Kredit
SELECT COUNT(*) 
FROM pengajuan_kredit 
WHERE posisi_saat_ini = 'kabag_kredit' 
AND status_pengajuan IN ('proses','diajukan','kasubag','kabag','kadiv','direksi');

-- And so on for each role...
```

### Query 5: Find Stuck Applications
```sql
SELECT 
    pk.id_pengajuan,
    pk.nama_debitur,
    pk.status_pengajuan,
    pk.posisi_saat_ini,
    DATEDIFF(NOW(), pk.tanggal_pengajuan) as days_in_pipeline
FROM pengajuan_kredit pk
WHERE pk.status_pengajuan IN ('proses','diajukan','kasubag','kabag','kadiv','direksi')
AND DATEDIFF(NOW(), pk.tanggal_pengajuan) > 7  -- More than 7 days
ORDER BY pk.tanggal_pengajuan ASC;
```

---

## ✅ CHECKLIST

Test each scenario and mark when complete:

- [ ] Scenario 1: Normal approval flow (all approve)
- [ ] Scenario 2: Revision request flow
- [ ] Scenario 3: Rejection flow
- [ ] Scenario 4: Auto-skip inactive staff
- [ ] Verify dashboard shows correct applications for each role
- [ ] Verify posisi_saat_ini changes correctly
- [ ] Verify approval_kredit records complete timeline
- [ ] Verify error logs capture any issues
- [ ] Verify resubmit after revision goes to correct stage
- [ ] Verify auto-skip creates appropriate records

---

## 🐛 TROUBLESHOOTING

### Application Missing from Dashboard
1. Check `posisi_saat_ini` matches the role
   ```sql
   SELECT posisi_saat_ini FROM pengajuan_kredit WHERE id_pengajuan = X;
   ```

2. Check status is in active pipeline
   ```sql
   SELECT status_pengajuan FROM pengajuan_kredit WHERE id_pengajuan = X;
   -- Should be in: proses, diajukan, kasubag, kabag, kadiv, direksi
   ```

3. Check user is logged in with correct role
   - Session role should match the dashboard role

### Application Stuck at One Stage
1. Check if current role has active user
   ```sql
   SELECT COUNT(*) FROM users 
   WHERE role = 'kabag_analis' AND status_jabatan = 'aktif';
   ```

2. Verify no error in logs
   ```bash
   tail -f bank-kredit/logs/error_*.log
   ```

### Application Disappeared
1. Check if status changed to terminal state
   ```sql
   SELECT status_pengajuan, posisi_saat_ini 
   FROM pengajuan_kredit WHERE id_pengajuan = X;
   -- Should NOT be 'selesai' or 'disetujui' if still in progress
   ```

### Revision/Rejection Not Working
1. Verify approval_kredit.level_approval matches a real role
2. Check revision count incremented
   ```sql
   SELECT revision_count, last_revision_by 
   FROM pengajuan_kredit WHERE id_pengajuan = X;
   ```

---

## 📞 CONTACT SUPPORT

If workflow issues persist:
1. Run monitoring queries above
2. Check error logs: `bank-kredit/logs/error_*.log`
3. Export database states from queries
4. Contact development team with:
   - Application ID
   - Current status & posisi
   - What action was taken
   - Error log excerpt

