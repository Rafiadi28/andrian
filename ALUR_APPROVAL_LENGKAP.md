# ALUR SISTEM APPROVAL KREDIT - ANALISIS LENGKAP

**Status**: ✅ Sistem berjalan dengan benar  
**Last Updated**: 4 April 2026  

---

## 📊 DIAGRAM ALUR LENGKAP

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                          COMPLETE CREDIT APPROVAL WORKFLOW                               │
└─────────────────────────────────────────────────────────────────────────────────────────┘

                                   [ ANALIS INPUT ]
                                   status: draft
                                   posisi: analis
                                          │
                                          │ (semua form diisi)
                                          ↓
                                    [ SUBMIT FORM ]
                                (save_section.php:1006)
                                          │
                  ┌───────────────────────┼───────────────────────┐
                  │ Update status_pengajuan │                       │
                  │ Update posisi_saat_ini  │                       │
                  │ Clear revision flags    │                       │
                  │ Create approval record  │                       │
                  │ Auto-skip inactive      │                       │
                  └────────────┬────────────┘                       │
                               │                                    │
                               ↓                                    │
        ┌──────────────────────────────────────────┐                │
        │ status: 'diajukan' or 'proses'          │                │
        │ posisi: 'kabag_analis' (next active)    │                │
        │ approval_kredit: level='analis'         │                │
        │                   keputusan='setuju'    │                │
        └──────────────────────────────────────────┘                │
                               │                                    │
                  ┌────────────┴───────────────┐                    │
                  │ Check: Inactive roles?      │                    │
                  │ (e.g., kadiv_bisnis tidak  │                    │
                  │  dalam hierarchy sekarang) │                    │
                  │ → Auto-skip create records │                    │
                  └────────────┬───────────────┘                    │
                               │                                    │
                               ↓                                    │
           ╔════════════════════════════════════════╗                │
           ║  [KABAG ANALIS DASHBOARD MENERIMA]    ║                │
           ║  Query: posisi_saat_ini = 'kabag_analis' │                │
           ║         status IN (proses,diajukan,...) │                │
           ║  Location: /kabag_analis/proses.php    ║                │
           ║  Action: View Detail / Proses          ║                │
           ╚════════════════════════════════════════╝                │
                    │           │           │                       │
        ┌───────────┴────┐  ┌──┴──┬───┐  ┌─┴───────────┐           │
        │                │  │     │   │  │             │           │
        ↓                ↓  ↓     ↓   ↓  ↓             │           │
     [SETUJU]      [REVISI] [TOLAK]    │         NEXT DECISION     │
        │                │      │          │           │           │
        │   ┌────────────┘      │          │           │           │
        │   │                   │          │           │           │
        ↓   ↓                   ↓          │           │           │
    ┌─────────────────────┐  ┌─────────────────┐      │           │
    │ posisi=kabag_kredit │  │ posisi=analis   │      │           │
    │ status=kabag        │  │ status=revisi   │      │           │
    │ → NEXT STAGE →      │  │ → ANALIS EDIT   │      │           │
    └─────────────────────┘  │   & RESUBMIT →  │      │           │
           │                 │ loop kembali ↻  │      │           │
           │                 └─────────────────┘      │           │
           ↓                          │                │           │
   ╔═══════════════════════════╗     │                │           │
   ║ [KABAG KREDIT MENERIMA]   ║     │                │           │
   ║ Query: posisi='kabag_kredit' ║     │                │           │
   ║ Location: kabag_kredit/proses.php║  │                │           │
   ╚═══════════════════════════╝     │                │           │
           │                         │                │           │
           ├─→ [SETUJU] ─────┐      │ [DITOLAK]      │           │
           │                 │      │    │             │           │
           ↓                 │      │    └──→ analis  │           │
   ╔═══════════════════════════╗   │                │           │
   ║ [KADIV KREDIT MENERIMA]    ║   │                │           │
   ║ Query: posisi='kadiv_kredit' ║   │                │           │
   ║ Location: kadiv_kredit/proses.php║  │                │           │
   ╚═══════════════════════════╝   │                │           │
           │                       │                │           │
           ├─→ [SETUJU] ─────┐    │                │           │
           │                 │    │                │           │
           ↓                 │    │                │           │
   ╔═══════════════════════════╗ │                │           │
   ║ [DIREKSI MENERIMA]         ║ │                │           │
   ║ Query: posisi='direksi'    ║ │                │           │
   ║ Location: direksi/proses.php ║  │                │           │
   ╚═══════════════════════════╝ │                │           │
           │                     │                │           │
           └─→ [SETUJU/TOLAK] ──┘                │           │
                  ↓                              │           │
         ┌────────────────────┐                 │           │
         │ status=disetujui   │                 │           │
         │ posisi=selesai     │                 │           │
         │ → APPROVAL SELESAI │                 │           │
         └────────────────────┘                 │           │
                  ✅ APPROVED                    │           │
                                                 ↓           │
                                          ALL REJECTED
                                          to ANALIS
```

---

## 🔄 ALUR APPROVAL LOGIC

### **1️⃣ SETUJU (Approval)**

```php
// Di processApproval() - includes/functions.php:219
if ($k === 'setuju') {
    // Cari role berikutnya
    $nextStep = findNextTarget($role, $pdo);
    $targetRole = $nextStep['role'];  // e.g., 'kabag_kredit'
    
    // Update posisi_saat_ini ke role berikutnya
    UPDATE pengajuan_kredit 
    SET status_pengajuan = 'kabag',  // atau status sesuai target
        posisi_saat_ini = $targetRole,
        last_revision_at = NULL,
        last_revision_by = NULL,
        last_reject_level = NULL
    WHERE id_pengajuan = ?
    
    // Catat ke approval_kredit (audit trail)
    INSERT INTO approval_kredit 
    (id_pengajuan, id_user, level_approval, keputusan, catatan)
    VALUES (?, ?, $role, 'setuju', ?)
}
```

**Hasil**: Aplikasi bergerak ke stage berikutnya

---

### **2️⃣ REVISI (Request Changes)**

```php
if ($k === 'revisi') {
    UPDATE pengajuan_kredit
    SET status_pengajuan = 'revisi',
        posisi_saat_ini = 'analis',  // Kembali ke analis
        last_revision_at = NOW(),
        last_revision_by = $user_id,
        last_reject_level = $role,   // Simpan siapa yg minta revisi
        revision_count = revision_count + 1
    WHERE id_pengajuan = ?
    
    INSERT INTO approval_kredit (...)
    VALUES (?, ?, $role, 'revisi', ?)
}
```

**Alur Kembali ke Analis**:
- Analis melihat di dalam form (status: revisi)
- Analis bisa edit data & resubmit
- Resubmit baca `last_reject_level` → kirim ke role tersebut, bukan ulang dari awal

---

### **3️⃣ TOLAK (Rejection)**

```php
if ($k === 'tolak') {
    UPDATE pengajuan_kredit
    SET status_pengajuan = 'ditolak',
        posisi_saat_ini = 'analis',
        last_reject_level = $role
    WHERE id_pengajuan = ?
    
    INSERT INTO approval_kredit (...)
    VALUES (?, ?, $role, 'tolak', ?)
}
```

**Analis Bisa**:
- View rejection note
- Click "Kirim Ulang" di form (detail.php)
- Re-edit & resubmit → ke role yg tolak

---

## 👥 DASHBOARD SETIAP ROLE

| Role | File | Query | Status |
|------|------|-------|--------|
| **Analis** | analis/riwayat.php | `posisi='analis'` AND status IN (draft,revisi,ditolak) | Lihat riwayat, edit, resubmit |
| **Kabag Analis** | kabag_analis/proses.php | `posisi='kabag_analis'` AND status IN (proses,diajukan,...) | Approve/Revisi/Tolak |
| **Kabag Kredit** | kabag_kredit/proses.php | `posisi='kabag_kredit'` AND status IN (proses,diajukan,...) | Approve/Revisi/Tolak |
| **Kadiv Kredit** | kadiv_kredit/proses.php | `posisi='kadiv_kredit'` AND status IN (proses,diajukan,...) | Approve/Revisi/Tolak |
| **Direksi** | direksi/proses.php | `posisi='direksi'` AND status IN (proses,diajukan,...) | Final Approve/Tolak |

### **Active Pipeline Statuses** (pengajuanStatusesActivePipeline())
```php
return ['proses', 'diajukan', 'kasubag', 'kabag', 'kadiv', 'direksi'];
```

Hanya aplikasi dengan status ini yang tampil di dashboard approval.

---

## ⚙️ SISTEM AUTO-SKIP ROLES

Jika seorang pejabat **inactive** (status_jabatan ≠ 'aktif'), sistem otomatis skip mereka.

**Contoh**: 
- Hierarchy: analis → kabag_analis → kabag_kredit → kadiv_kredit → direksi
- Kadiv_kredit sedang sakit (status_jabatan = 'sakit')
- Analis submit → langsung ke direksi, kadiv_kredit di-skip
- Approval record untuk kadiv_kredit dibuat dengan `is_auto_skip = 1`

**Code** (functions.php:118-127):
```php
function findNextTarget($currentRole, $pdo) {
    $hierarchy = getHierarchy();
    $currentIndex = array_search($currentRole, $hierarchy);
    
    for ($i = $currentIndex + 1; $i < count($hierarchy); $i++) {
        $role = $hierarchy[$i];
        
        // Check if ANY user with this role is active
        $stmt->execute([$role]);
        $activeCount = $stmt->fetchColumn();
        
        if ($activeCount > 0) {
            return ['role' => $role, 'skipped' => $skipped];
        } else {
            $skipped[] = $role;  // Log sebagai skipped
        }
    }
    
    return ['role' => 'selesai', 'skipped' => $skipped];
}
```

---

## 🔍 DETAIL PAGE (detail.php)

**Fungsi**: Central hub untuk lihat aplikasi & lakukan aksi

**Conditional Actions** (based on role & status):
- ✏️ **Edit**: Owner + status IN ['draft','revisi','ditolak']
- 🔄 **Kirim Ulang**: Owner + status IN ['revisi','ditolak']
- ✅ **Lanjutkan**: Current role = posisi_saat_ini + status in active pipeline
- ❌ **Hapus**: Owner atau Admin only
- 📊 **Lihat Approval Timeline**: Everyone (read-only)

**Tabs**:
1. Data Pemohon → visible to all roles
2. Penghasilan → visible to all roles  
3. Struktur Kredit → visible to all roles
4. Agunan → visible to all roles
5. Neraca → visible to all roles (untuk umum)
6. Analisa 6C → visible to all roles
7. Approval Timeline → summary of all decisions

---

## 📈 COMPLETE STATE TRANSITION TABLE

| Scenario | Initial State | Action | New State |
|----------|---------------|--------|-----------|
| Analis submit | draft / analis | Submit → Auto next | diajukan / kabag_analis |
| Kabag Analis approve | diajukan / kabag_analis | Setuju | kabag / kabag_kredit |
| Kabag Kredit approve | kabag / kabag_kredit | Setuju | kadiv / kadiv_kredit |
| Kadiv approve | kadiv / kadiv_kredit | Setuju | direksi / direksi |
| Direksi approve | direksi / direksi | Setuju | disetujui / selesai ✅ |
| Mid-stage revisi | (any) / (role) | Revisi | revisi / analis → edit |
| Mid-stage reject | (any) / (role) | Tolak | ditolak / analis → resubmit |
| Analis resubmit after reject | ditolak / analis | Kirim Ulang | diajukan / last_reject_level |

---

## 🗂️ DATABASE TABLES

### `pengajuan_kredit`
```sql
KEY COLUMNS:
- id_pengajuan (PK)
- status_pengajuan ENUM(...) -- workflow status
- posisi_saat_ini VARCHAR(100) -- current role's turn
- last_reject_level VARCHAR(50) -- where it was rejected (for resume)
- last_revision_at TIMESTAMP -- revision timestamp
- last_revision_by INT -- who revised it
- revision_count INT -- how many revisions
```

### `approval_kredit`
```sql
- id_approval (PK)
- id_pengajuan (FK)
- id_user (FK) -- who made the decision
- level_approval VARCHAR(50) -- which role (analis, kabag_analis, etc)
- keputusan ENUM('setuju','tolak','revisi','pending','eskalasi_otomatis')
- catatan TEXT -- notes
- is_auto_skip TINYINT(1) -- is this auto-skip?
- tanggal_approval TIMESTAMP -- when decision was made
```

**Setiap approval direkam di sini** → complete audit trail

---

## ✅ VERIFICATION CHECKLIST

### Form Submission (save_section.php)
- [x] Analis submit → status changes to 'diajukan'
- [x] posisi_saat_ini updates to next active role
- [x] approval_kredit record created (level='analis', keputusan='setuju')
- [x] Inactive roles get auto-skip records
- [x] last_revision_* fields cleared

### Dashboard Queries
- [x] Kabag Analis sees `posisi='kabag_analis'` items
- [x] Kabag Kredit sees `posisi='kabag_kredit'` items
- [x] Kadiv Kredit sees `posisi='kadiv_kredit'` items
- [x] Direksi sees `posisi='direksi'` items
- [x] All filter by active pipeline statuses

### Approval Decision Flow
- [x] Setuju → next role appears in that role's dashboard
- [x] Revisi → returns to analis with revision flag
- [x] Tolak → returns to analis with rejection flag
- [x] Kirim Ulang → resumes from last_reject_level (not from beginning)

### Auto-Skip
- [x] Inactive pejabat are skipped
- [x] Application jumps to next active role
- [x] Skipped roles logged in approval_kredit

### Error Handling
- [x] Errors logged to bank-kredit/logs/error_*.log
- [x] Transaction rollback on errors
- [x] User sees friendly error messages

---

## 🚨 POTENTIAL ISSUES & SOLUTIONS

### Issue 1: Application stuck at one stage
**Cause**: Role = posisi_saat_ini but user can't find it
**Solution**: Check dashboard query, verify posisi_saat_ini value in database

### Issue 2: Application disappears
**Cause**: status not in active pipeline OR posisi_saat_ini doesn't match
**Solution**: Check application status in database, verify it's in ['proses','diajukan','kasubag','kabag','kadiv','direksi']

### Issue 3: Analis can't resubmit after rejection
**Cause**: status = 'ditolak' but can't edit
**Solution**: Check if there's a typo in status check, verify user is logged in as analis

### Issue 4: Role hierarchy not working
**Cause**: getHierarchy() returns wrong roles
**Solution**: Verify functions.php line 85-89, should match user.role values from database

---

## 📊 MONITORING & DEBUGGING

**View current application state**:
```sql
SELECT id_pengajuan, nama_debitur, status_pengajuan, posisi_saat_ini, 
       last_reject_level, last_revision_by, revision_count
FROM pengajuan_kredit
WHERE id_pengajuan = ?;
```

**View approval timeline**:
```sql
SELECT id_approval, level_approval, keputusan, catatan, 
       tanggal_approval, is_auto_skip
FROM approval_kredit
WHERE id_pengajuan = ?
ORDER BY tanggal_approval ASC;
```

**Check error logs**:
```bash
tail -f bank-kredit/logs/error_2026-04-04.log
```

---

## 📝 SUMMARY

✅ **Sistem approval berjalan dengan baik:**
1. Analis submit form → aplikasi masuk ke kabag_analis
2. Kabag Analis process → ke kabag_kredit
3. Kabag Kredit process → ke kadiv_kredit
4. Kadiv Kredit process → ke direksi
5. Direksi approve → disetujui / tolak → kembali ke analis

✅ **Fitur tambahan bekerja**:
- Auto-skip inactive pejabat
- Revision/rejection dengan catatan
- Resume dari stage terakhir (tidak dari awal)
- Complete audit trail in approval_kredit

✅ **Dashboard masing-masing role menerima aplikasi** sesuai dengan posisi_saat_ini

