# Credit System Approval Workflow - Complete Analysis

## 1. FORM SUBMISSION FLOW (save_section.php - submit case)

### Location
[analis/save_section.php](analis/save_section.php#L1006) - Lines 1006-1060

### Submit Case Execution
When analis clicks submit button, here's the exact sequence:

```
1. Check last_reject_level to determine resume point
   - If exists: Continue from that role
   - If empty: Start from kabag_analis

2. Call findNextTarget() to find next active approver
   - Skips inactive roles
   - Records auto-skipped roles

3. Update pengajuan_kredit:
   - status_pengajuan → 'diajukan' (or 'proses' if ENUM unavailable)
   - posisi_saat_ini → TARGET ROLE (not next in hierarchy, but next ACTIVE)
   - Clear: last_revision_at, last_revision_by, last_reject_level

4. Create approval_kredit record:
   - level_approval = 'analis'
   - keputusan = 'setuju'
   - catatan = 'Pengajuan lengkap.'

5. Create auto-skip records for skipped roles:
   - level_approval = skipped role
   - keputusan = 'eskalasi_otomatis'
   - is_auto_skip = 1
```

### Key Variables Changed
```
status_pengajuan: draft/revisi/ditolak → diajukan/proses
posisi_saat_ini: analis → next active role in hierarchy
last_reject_level: CLEARED (set to NULL)
last_revision_at: CLEARED
last_revision_by: CLEARED
```

### Example
If kabag_analis is inactive:
- posisi goes straight to kabag_kredit
- Auto-skip record created for kabag_analis
- approval_kredit shows: analis (setuju), kabag_analis (eskalasi_otomatis)

---

## 2. ROLE HIERARCHY & DASHBOARD DISPLAY

### Role Hierarchy Chain (From functions.php)
```
getHierarchy() = ['analis', 'kabag_analis', 'kabag_kredit', 'kadiv_kredit', 'direksi']
Final position: 'selesai'
```

### Critical Query Pattern (All roles identical)
```sql
SELECT * FROM pengajuan_kredit 
WHERE posisi_saat_ini = $my_role 
AND status_pengajuan IN ('proses','diajukan','kasubag','kabag','kadiv','direksi')
ORDER BY tanggal_pengajuan ASC
```

### What Each Dashboard Shows

| Role | File | Shows Items Where |
|------|------|-------------------|
| kabag_analis | [kabag_analis/proses.php](kabag_analis/proses.php) | posisi_saat_ini = 'kabag_analis' |
| kabag_kredit | [kabag_kredit/proses.php](kabag_kredit/proses.php) | posisi_saat_ini = 'kabag_kredit' |
| kadiv_kredit | [kadiv_kredit/proses.php](kadiv_kredit/proses.php) | posisi_saat_ini = 'kadiv_kredit' |
| direksi | [direksi/proses.php](direksi/proses.php) | posisi_saat_ini = 'direksi' |

### Dashboard UI
Each dashboard's proses.php shows:
- Table with columns: Tgl Input, Debitur, Nominal, Jenis, Aksi
- Two buttons per row:
  - "Detail" → links to [detail.php](detail.php)
  - "Proses" → opens approval modal

---

## 3. APPROVAL PROCESS MECHANISM

### Core Function
[includes/functions.php](includes/functions.php#L217) - `processApproval($pdo, $id_pengajuan, $role, $user_id, $keputusan, $catatan)`

### Approval Data Storage
Table: `approval_kredit`
```sql
Columns:
- id_pengajuan (FK to pengajuan_kredit)
- id_user (approver, can be NULL for auto-skip)
- level_approval (enum: analis|kabag_analis|kabag_kredit|kadiv_kredit|direksi)
- keputusan (enum: setuju|tolak|kembalikan|pending|eskalasi_otomatis)
- catatan (text reason)
- is_auto_skip (0/1)
- tanggal_approval (timestamp)
```

### Three Approval Decision Paths

#### PATH 1: SETUJUI (keputusan = 'setuju')
**What happens:**
```
1. Call findNextTarget(current_role) to find NEXT approver
2. If target = 'selesai':
   - status_pengajuan → 'disetujui'
   - posisi_saat_ini → 'selesai'
3. Else:
   - status_pengajuan → mapped status (e.g., 'kabag', 'kadiv')
   - posisi_saat_ini → target role name

4. Create approval_kredit:
   - keputusan = 'setuju'
   - id_user = current approver
   - level_approval = current approver's role
   - catatan = their note

5. Auto-skip any inactive roles between current and next:
   - Create records with is_auto_skip=1
```

**Result**: Application MOVES FORWARD through hierarchy

#### PATH 2: REVISI (keputusan = 'revisi')
**What happens:**
```
1. Sets status_pengajuan = 'revisi'
2. Sets posisi_saat_ini = 'analis'
3. Records rejection point: last_reject_level = current role
4. Increments: revision_count += 1
5. Stores rejection metadata:
   - last_revision_at = NOW()
   - last_revision_by = approver_id
6. Creates approval_kredit:
   - keputusan = 'revisi'
   - catatan = Their revision request

7. Stores catatan into catatan_revisi field (if exists)
```

**Result**: Application RETURNS to analis for re-editing

#### PATH 3: TOLAK (keputusan = 'tolak')
**What happens:**
```
1. Sets status_pengajuan = 'ditolak'
2. Sets posisi_saat_ini = 'analis'
3. Records rejection level: last_reject_level = current role
4. Creates approval_kredit:
   - keputusan = 'tolak'
   - catatan = rejection reason

5. Stores reason into alasan_penolakan field (if exists)
```

**Result**: Application MARKED AS REJECTED, but analis can fix and re-submit

### Auto-Approval or Manual?
**ALWAYS MANUAL** - Every step requires a user to actively click "Proses" button and select decision

---

## 4. DETAIL PAGE - How It Works

### Location
[detail.php](detail.php)

### Information Displayed
- Debitur name, NIK, status_pengajuan badge
- Nominal (jumlah_kredit)
- Complete timeline from approval_kredit table
- All application data (pemohon, penghasilan, jaminan, analisa)

### Available Actions (Conditional)

```php
// Edit Button - Shows if:
$analis_edit_ok = $_SESSION['user_id'] == $data['input_by']
  && in_array($data['status_pengajuan'], ['draft', 'revisi', 'ditolak'])

// Delete Button - Shows if:
Superadmin || owner of application

// Kirim Ulang (Re-submit) Button - Shows if:
$_SESSION['user_id'] == $data['input_by'] 
&& in_array($data['status_pengajuan'], ['revisi','ditolak'])

// Lanjutkan (Continue) Button - Shows if:
$_SESSION['role'] === $data['posisi_saat_ini']
&& !in_array($data['status_pengajuan'], ['selesai','ditolak','revisi','draft','disetujui'])
```

### Role/posisi Check
```php
$boleh_lanjut = $_SESSION['role'] === $data['posisi_saat_ini']
  && status NOT in terminal states
```

Only the role whose name matches `posisi_saat_ini` can process the application

---

## 5. COMPLETE STATUS TRANSITION PATH

### Normal Approval Flow (No rejections, all roles active)
```
┌─────────────────────────────────────────────────────────────────┐
│                                                                   │
│  draft ─► diajukan ─► kabag_analis ─► kabag_kredit ─► kadiv_kredit
│   (analis             (kabag_analis    (kabag_kredit   (kadiv_kredit
│   creating)            reviews)         reviews)        reviews)
│
│                 ─► direksi ─► disetujui ─► selesai
│                   (direksi      (final)
│                    reviews)
│
└─────────────────────────────────────────────────────────────────┘
```

### With Revision Cycle
```
draft → diajukan → kabag_analis 
    ▲                    │
    │              (revisi requested)
    │                    ▼
    └───────── revisi
               (back to analis)
               
analis re-edits, then kirim ulang→ diajukan → kabag_analis (continues)
```

### With Rejection Cycle
```
draft → diajukan → kabag_analis 
    ▲                    │
    │              (reject)
    │                    ▼
    └───────── ditolak
               (marked rejected)
               
Can edit and Re-submit (kirim_ulang)
```

### Field Value Changes Over Time

| Stage | status_pengajuan | posisi_saat_ini | last_reject_level |
|-------|-----------------|-----------------|-------------------|
| Create | draft | analis | NULL |
| After analis submit | diajukan | kabag_analis | NULL |
| After kabag_analis approve | kabag | kabag_kredit | NULL |
| After kabag_kredit approve | kadiv | kadiv_kredit | NULL |
| After kadiv_kredit approve | direksi | direksi | NULL |
| After direksi approve | disetujui | selesai | NULL |
| If kabag_analis rejects | revisi | analis | kabag_analis |
| If analis kirim_ulang after revisi | diajukan | kabag_analis | NULL (cleared) |

---

## 6. KEY HELPER FUNCTIONS

### findNextTarget($currentRole, $pdo)
**Purpose**: Determine next ACTIVE role in hierarchy, skip inactive ones
**Returns**: `['role' => 'role_name', 'skipped' => ['inactive_role1', 'inactive_role2']]`
**Location**: [includes/functions.php](includes/functions.php#L124)

```php
Algorithm:
1. Start from current role's position in hierarchy
2. Check each next role for active users (status_jabatan = 'aktif')
3. First active role found = target
4. All skipped roles get auto-approval records
5. If no active roles remain = 'selesai'
```

### pengajuanStatusesActivePipeline()
**Purpose**: Define which statuses are "in progress" (show in dashboards)
**Returns**: `['proses', 'diajukan', 'kasubag', 'kabag', 'kadiv', 'direksi']`
**Location**: [includes/functions.php](includes/functions.php#L192)

### enumAllows($pdo, $table, $column, $value)
**Purpose**: Check if ENUM column accepts a value
**Usage**: Validates whether 'diajukan' status is allowed before using it

---

## 7. RESUME LOGIC (After Revision)

When analis wants to re-submit after revision:
```php
$resumeTo = last_reject_level ?: 'kabag_analis'
```

- If rejected by kadiv_kredit: resume at kadiv_kredit
- If rejected by kabag_analis: resume at kabag_analis
- If no rejection level (legacy): default to kabag_analis

The `last_reject_level` field stores WHERE (which role) the application was last rejected/revised

---

## 8. ACTIVE USER CHECK

Role escalation considers: `status_jabatan = 'aktif'`

Possible values in users table:
- 'aktif' (included in approvals)
- 'sakit' (skipped)
- 'izin' (skipped)
- 'cuti' (skipped)
- 'berhalangan' (skipped)

If ALL users of a role are inactive, that role is auto-skipped

---

## Summary: From Submit to Final

1. **Analis** creates draft → fills form → clicks submit
   - status: draft → diajukan
   - posisi: analis → first active role (usually kabag_analis)

2. **Kabag Analis** sees in dashboard → clicks Proses or Detail → decides
   - If approve: posisi → kabag_kredit, status → kabag
   - If revise: status → revisi, posisi → analis
   - If reject: status → ditolak, posisi → analis

3. **Kabag Kredit** → **Kadiv Kredit** → **Direksi** (same pattern)

4. **Final approval by Direksi**:
   - status: direksi → disetujui
   - posisi: direksi → selesai

5. **Approval history** = complete timeline in approval_kredit table

define('BASE_URL', '/andrian/bank-kredit');