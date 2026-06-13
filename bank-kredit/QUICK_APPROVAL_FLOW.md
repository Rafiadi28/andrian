# QUICK REFERENCE: APPROVAL WORKFLOW

---

## 🎯 SATU HALAMAN RINGKASAN

```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃          ALUR APPROVAL KREDIT - QUICK REFERENCE              ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

📝 ANALIS INPUT
    ↓ Form submit (save_section.php)
    ↓ All 6 sections filled
    ↓ Click "KIRIM"
    ↓

┌─────────────────────────────────┐
│ Database Changes:               │
│ - status: draft → diajukan      │
│ - posisi: analis → kabag_analis │
│ - clear revision fields         │
│ - create approval record        │
└─────────────────────────────────┘
    ↓ posisi_saat_ini = 'kabag_analis'
    ↓

✅ KABAG_ANALIS RECEIVES
   └─ kabag_analis/proses.php
   └─ Query: posisi='kabag_analis' & status IN (proses, diajukan, ...)
   └─ Click "Detail" → "Proses"
   
   ┌──────────────────────────────────┐
   │ DECISION OPTIONS:                 │
   ├──────────────────────────────────┤
   │ 1. SETUJU (Approve)               │
   │    → posisi = kabag_kredit        │
   │    → Status = 'kabag'             │
   │    → Moves to NEXT ROLE           │
   │                                   │
   │ 2. REVISI (Request Changes)       │
   │    → posisi = analis              │
   │    → status = 'revisi'            │
   │    → Analis edits & resubmit      │
   │                                   │
   │ 3. TOLAK (Reject)                 │
   │    → posisi = analis              │
   │    → status = 'ditolak'           │
   │    → Analis can re-edit & retry   │
   └──────────────────────────────────┘
         ↓ (if SETUJU)
         ↓

✅ KABAG_KREDIT RECEIVES
   └─ kabag_kredit/proses.php
   └─ Same 3 decision options
      (Setuju → kadiv_kredit)
         ↓ (if SETUJU)
         ↓

✅ KADIV_KREDIT RECEIVES
   └─ kadiv_kredit/proses.php
   └─ Same 3 decision options
      (Setuju → direksi)
         ↓ (if SETUJU)
         ↓

✅ DIREKSI RECEIVES
   └─ direksi/proses.php
   └─ Final decision:
      (Setuju → status=disetujui, posisi=selesai)
         ↓
         ↓

🎉 APPLICATION APPROVED ✅
   status: 'disetujui'
   posisi: 'selesai'
```

---

## 🔑 KEY CONCEPTS

### 1. DATABASE COLUMNS CONTROL FLOW

| Column | Value | What It Means |
|--------|-------|---|
| `posisi_saat_ini` | 'analis' | Waiting for Analis to input |
| `posisi_saat_ini` | 'kabag_analis' | In Kabag Analis's queue|
| `posisi_saat_ini` | 'kabag_kredit' | In Kabag Kredit's queue |
| `posisi_saat_ini` | 'kadiv_kredit' | In Kadiv Kredit's queue |
| `posisi_saat_ini` | 'direksi' | In Direksi's queue |
| `posisi_saat_ini` | 'selesai' | DONE (approved or rejected final) |
| `status_pengajuan` | 'draft' | Analis still editing |
| `status_pengajuan` | 'diajukan' | Submitted to next level |
| `status_pengajuan` | 'revisi' | Sent back for revision |
| `status_pengajuan` | 'ditolak' | Rejected - can be resubmitted |
| `status_pengajuan` | 'disetujui' | Final approval - DONE |

### 2. EACH ROLE'S DASHBOARD QUERY

```sql
-- Hidden formula for ALL approval roles:
SELECT * FROM pengajuan_kredit 
WHERE posisi_saat_ini = [CURRENT_ROLE]
AND status_pengajuan IN ('proses','diajukan','kasubag','kabag','kadiv','direksi')
ORDER BY tanggal_pengajuan ASC;

-- Results in this table:
┌──────────────────────────┐
│ Applications for [ROLE]   │
├──────────────────────────┤
│ - Kabag Analis: ~         │
│   SELECT ... WHERE posisi │
│   = 'kabag_analis' ...    │
│                           │
│ - Kabag Kredit: ~         │
│   SELECT ... WHERE posisi │
│   = 'kabag_kredit' ...    │
│                           │
│ - Kadiv Kredit: ~         │
│   SELECT ... WHERE posisi │
│   = 'kadiv_kredit' ...    │
│                           │
│ - Direksi: ~              │
│   SELECT ... WHERE posisi │
│   = 'direksi' ...         │
└──────────────────────────┘
```

### 3. THREE DECISION PATHS

```
DECISION 1: SETUJU (Approve)
├─ posisi_saat_ini = NEXT_ROLE
├─ status = status_for_next_role
├─ Escalates to next stage
└─ Application appears in next role's dashboard

DECISION 2: REVISI (Request Changes)
├─ posisi_saat_ini = 'analis'
├─ status = 'revisi'
├─ last_revision_by = current_user_id
├─ last_reject_level = current_role
└─ Analis sees it, edits, & resubmits → back to CURRENT role

DECISION 3: TOLAK (Reject)
├─ posisi_saat_ini = 'analis'
├─ status = 'ditolak'
├─ last_reject_level = current_role
└─ Analis sees it, edits, & resubmits → back to REJECTING role
```

### 4. AUTO-SKIP LOGIC

```
IF pejabat status_jabatan ≠ 'aktif':
├─ Skip them
├─ Go to next active role
└─ Log as 'eskalasi_otomatis' in approval_kredit

Example:
- Kadiv Kredit sakit (status = 'sakit')
- Kabag Kredit approves
- System checks: kadiv_kredit active? NO
- System checks: direksi active? YES
- Application goes directly to direksi (skip kadiv)
- approval_kredit records kadiv_kredit as auto-skip
```

---

## 📍 WHERE TO MONITOR

### Dashboard Files
```
analis/riwayat.php              ← Analis sees drafts + revisions
kabag_analis/proses.php         ← Kabag Analis approves
kabag_kredit/proses.php         ← Kabag Kredit approves
kadiv_kredit/proses.php         ← Kadiv Kredit approves
direksi/proses.php              ← Direksi final approval
```

### Central Hub
```
detail.php  ← See application data + approval timeline
            ← Where actual approval decisions happen
            ← Shows all tabs & decision history
```

### Code Logic
```
save_section.php:1006    ← Analis submission logic
includes/functions.php:219   ← processApproval() function
includes/functions.php:118   ← findNextTarget() (finds next active role)
```

### Database
```
pengajuan_kredit     ← Current state (status, posisi, revision fields)
approval_kredit      ← Complete decision history (audit trail)
users                ← Role info (username, role, status_jabatan)
```

---

## 🔍 VERIFY WORKFLOW WORKING

### Test 1: Check Application Moved to Next Role
```bash
Query:
SELECT posisi_saat_ini, status_pengajuan 
FROM pengajuan_kredit WHERE id_pengajuan = 123;

Expected AFTER Kabag Analis approves:
posisi_saat_ini: 'kabag_kredit'
status_pengajuan: 'kabag'
```

### Test 2: Check Approval Record Created
```bash
Query:
SELECT level_approval, keputusan FROM approval_kredit 
WHERE id_pengajuan = 123;

Expected AFTER each role approves:
analis → setuju
kabag_analis → setuju
kabag_kredit → setuju
kadiv_kredit → setuju
direksi → setuju
```

### Test 3: Check Revision Works
```bash
Query (after revision request):
SELECT status_pengajuan, posisi_saat_ini, last_revision_by
FROM pengajuan_kredit WHERE id_pengajuan = 123;

Expected:
status: 'revisi'
posisi: 'analis'
last_revision_by: [ID of role that requested]
```

### Test 4: Check Auto-Skip
```bash
Query (when staff inactive):
SELECT is_auto_skip FROM approval_kredit 
WHERE id_pengajuan = 123 AND level_approval = 'kadiv_kredit';

Expected:
is_auto_skip: 1 (means auto-skipped because inactive)
```

---

## ⚡ QUICK TROUBLESHOOTING

| Problem | Check |
|---------|-------|
| App not in dashboard | `posisi_saat_ini` = that role? |
| App disappeared | `status` still in active pipeline? |
| Can't see revisions | `status` = 'revisi' & `posisi` = 'analis'? |
| Resubmit goes to wrong stage | Check `last_reject_level` value |
| Staff can't approve | Check `posisi_saat_ini` = their role? |
| Auto-skip not working | User status = 'aktif'? Or intentional 'sakit'? |

---

## 📱 FIELDS THAT CONTROL EVERYTHING

**These 6 fields in `pengajuan_kredit` table**:

```
1. status_pengajuan     ← Workflow status (draft, diajukan, revisi, ditolak, disetujui)
2. posisi_saat_ini      ← Who's turn now (analis, kabag_analis, kabag_kredit, kadiv_kredit, direksi, selesai)
3. last_reject_level    ← Where was it rejected/revised (for resubmit resume)
4. last_revision_by     ← Who requested revision (user_id)
5. last_revision_at     ← When revision requested (timestamp)
6. revision_count       ← How many times requested revision (counter)
```

**These control everything else.**

---

## 🎓 LEARNING PATH

1. **Understand**: Read ALUR_APPROVAL_LENGKAP.md (this doc explains the 'what')
2. **Test**: Follow TESTING_WORKFLOW.md (hands-on scenarios)
3. **Monitor**: Use SQL queries to verify states
4. **Debug**: Check error logs if issues occur
5. **Master**: Know these 6 fields + 3 decision types

---

## ✅ SUCCESS CRITERIA

✅ Applications successfully flow from analis → kabag_analis → kabag_kredit → kadiv_kredit → direksi
✅ Each role sees only applications in their queue (posisi_saat_ini = their role)
✅ Approval moves to next stage, revision returns to analis
✅ Rejection returns to analis with notes
✅ Resubmit after revision/rejection goes to correct stage (not restart)
✅ Inactive staff auto-skipped
✅ Complete audit trail in approval_kredit

If all 7 ✅, then workflow is **WORKING PERFECTLY**.

