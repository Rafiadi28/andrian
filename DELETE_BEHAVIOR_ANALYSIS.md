# Delete Behavior Analysis

## Summary: Soft-Delete Implementation

**Delete Type**: SOFT DELETE (not hard delete)  
**Method**: Update status and position to indicate completion  
**Implemented in**: [detail_action.php](detail_action.php#L12-L41)

---

## 1. Current Delete Logic (detail_action.php)

```php
// Lines 12-41: Delete action handler
if ($action === 'delete' && $id_pengajuan > 0) {
    // Creates approval record for audit
    $note = 'Dibatalkan oleh ' . $_SESSION['user_id'] . ' (' . $_SESSION['role'] . ')';
    $stmt = $pdo->prepare("INSERT INTO approval_kredit (id_pengajuan, id_user, level_approval, keputusan, catatan, is_auto_skip) 
                          VALUES (?, ?, ?, 'tolak', ?, 0)");
    
    // SOFT-DELETE: Mark as rejected, set position to complete
    $stmt = $pdo->prepare("UPDATE pengajuan_kredit 
                          SET status_pengajuan = 'ditolak', posisi_saat_ini = 'selesai' 
                          WHERE id_pengajuan = ?");
    
    // Audit trail
    $pdo->prepare("INSERT INTO audit_log (id_user, aktivitas) VALUES (?, ?)")
        ->execute([$_SESSION['user_id'], "Membatalkan pengajuan ID: {$id_pengajuan}"]);
}
```

**Key Points:**
- ✅ Uses **UPDATE** not **DELETE FROM**
- ✅ Sets `status_pengajuan = 'ditolak'`
- ✅ Sets `posisi_saat_ini = 'selesai'` (removes from active workflow)
- ✅ Creates audit trail in both `approval_kredit` and `audit_log`
- ✅ Data is **preserved** for historical record

---

## 2. Visibility After Soft-Delete

### Active Pipeline Statuses (functions.php:209-211)
```php
function pengajuanStatusesActivePipeline()
{
    return ['proses', 'diajukan', 'kasubag', 'kabag', 'kadiv', 'direksi'];
}
```

Applications are filtered by these statuses in approval queues. Since `'ditolak'` and `'selesai'` are NOT in this list:
- ✅ Soft-deleted apps are **HIDDEN** from approver inboxes
- ✅ They appear ONLY in historical records

### Visibility Matrix

| Role | Query | Visibility | Filter |
|------|-------|------------|--------|
| **Analis** | `riwayat.php` - All submissions | ✅ See ditolak | NO WHERE clause on status |
| **Kabag/Kadiv/Direksi** | `proses.php` - Approval queue | ❌ Hide ditolak | `posisi_saat_ini = ? AND status_pengajuan IN (active)` |
| **Admin** | `dashboard.php` | ✅ Count ditolak separately | Counts pending separately |

---

## 3. SQL Queries to Check Current State

### Check for Soft-Deleted (ditolak) Applications

```sql
-- Count all applications marked as 'ditolak'
SELECT COUNT(*) as total_ditolak 
FROM pengajuan_kredit 
WHERE status_pengajuan = 'ditolak';

-- List all soft-deleted applications with details
SELECT 
    id_pengajuan,
    nama_debitur,
    status_pengajuan,
    posisi_saat_ini,
    tanggal_pengajuan,
    input_by
FROM pengajuan_kredit 
WHERE status_pengajuan = 'ditolak'
ORDER BY tanggal_pengajuan DESC;

-- See who deleted what
SELECT 
    pk.id_pengajuan,
    pk.nama_debitur,
    u.nama as deleted_by,
    ak.tanggal_approval as deletion_time,
    ak.catatan
FROM pengajuan_kredit pk
JOIN approval_kredit ak ON pk.id_pengajuan = ak.id_pengajuan
JOIN users u ON ak.id_user = u.id_user
WHERE pk.status_pengajuan = 'ditolak' 
  AND ak.keputusan = 'tolak'
  AND ak.catatan LIKE 'Dibatalkan oleh%'
ORDER BY ak.tanggal_approval DESC;
```

### Check Applications Hidden from Approvers

```sql
-- Applications in 'selesai' position (hidden from all approver queues)
SELECT 
    id_pengajuan,
    nama_debitur,
    status_pengajuan,
    posisi_saat_ini,
    tanggal_pengajuan
FROM pengajuan_kredit 
WHERE posisi_saat_ini = 'selesai'
ORDER BY tanggal_pengajuan DESC;

-- What approvers see (active pipeline only)
SELECT 
    id_pengajuan,
    nama_debitur,
    status_pengajuan,
    posisi_saat_ini,
    tanggal_pengajuan
FROM pengajuan_kredit 
WHERE status_pengajuan IN ('proses', 'diajukan', 'kasubag', 'kabag', 'kadiv', 'direksi')
ORDER BY posisi_saat_ini, tanggal_pengajuan ASC;
```

### Check Creator's View (Analis riwayat)

```sql
-- What analis sees in their history (NO filtering on status!)
SELECT 
    id_pengajuan,
    nama_debitur,
    status_pengajuan,
    posisi_saat_ini,
    tanggal_pengajuan,
    jumlah_kredit
FROM pengajuan_kredit 
WHERE input_by = ?  -- analis user_id
ORDER BY tanggal_pengajuan DESC;

-- Count by status for single analis
SELECT 
    status_pengajuan,
    COUNT(*) as count
FROM pengajuan_kredit 
WHERE input_by = ?
GROUP BY status_pengajuan;
```

### Check for Recently Modified Applications

```sql
-- Recently changed applications (last 7 days)
SELECT 
    id_pengajuan,
    nama_debitur,
    status_pengajuan,
    posisi_saat_ini,
    tanggal_pengajuan
FROM pengajuan_kredit 
WHERE tanggal_pengajuan >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY tanggal_pengajuan DESC;

-- Track deletion timeline
SELECT 
    DATE(ak.tanggal_approval) as deletion_date,
    COUNT(DISTINCT ak.id_pengajuan) as apps_deleted
FROM approval_kredit ak
WHERE ak.keputusan = 'tolak'
  AND ak.catatan LIKE 'Dibatalkan oleh%'
GROUP BY DATE(ak.tanggal_approval)
ORDER BY deletion_date DESC;
```

### Check Filtering Logic in Code

```sql
-- Validate pengajuan_kredit structure for filtering
SHOW COLUMNS FROM pengajuan_kredit WHERE Field IN ('status_pengajuan', 'posisi_saat_ini');

-- Check all status values in use
SELECT DISTINCT status_pengajuan FROM pengajuan_kredit ORDER BY status_pengajuan;

-- Check all position values in use
SELECT DISTINCT posisi_saat_ini FROM pengajuan_kredit ORDER BY posisi_saat_ini;
```

---

## 4. Delete Behavior Flow Chart

```
User clicks "Delete" in detail.php
         ↓
detail_action.php receives action='delete'
         ↓
Verify permission (owner or Superadmin)
         ↓
BEGIN TRANSACTION
  ├─ INSERT approval_kredit row with keputusan='tolak'
  ├─ UPDATE pengajuan_kredit: status_pengajuan='ditolak', posisi_saat_ini='selesai'
  └─ INSERT audit_log entry
         ↓
COMMIT
         ↓
Redirect to detail.php?msg=deleted
         ↓
Application is now:
  ✅ PRESERVED in database (history maintained)
  ✅ HIDDEN from approver queues (status filtering)
  ✅ VISIBLE to creator in riwayat.php (no status filter)
  ✅ VISIBLE to admin dashboards (for monitoring)
```

---

## 5. Key Implementation Details

### Permission Check
- Only **Superadmin** OR the **application creator** (input_by) can delete
- Source: [detail_action.php](detail_action.php#L18-L22)

### Audit Trail
- Deletion recorded in `approval_kredit` table with auto-generated reason
- Also logged to `audit_log` for admin monitoring
- Source: [detail_action.php](detail_action.php#L31-L34)

### Dashboard Filtering
- [analis/riwayat.php](analis/riwayat.php#L5): No WHERE clause on status - shows all
- [kabag_kredit/proses.php](kabag_kredit/proses.php#L15-16): Filters on active statuses only
- [admin/dashboard.php](admin/dashboard.php#L9): Uses `pengajuanStatusesActivePipelineSqlIn()` for pending count

---

## 6. Status Values Legend

| Status | Meaning | In Pipeline? | Created When |
|--------|---------|:---:|---|
| `draft` | Initial - not submitted | ✅ | New application created |
| `diajukan` | Submitted to analis | ✅ | Analis submits |
| `kasubag` | At kasubag level | ✅ | Routing logic |
| `kabag` | At kabag level | ✅ | Routing logic |
| `kadiv` | At kadiv level | ✅ | Routing logic |
| `direksi` | At direksi level | ✅ | Routing logic |
| `proses` | In active processing | ✅ | During approval flow |
| `revisi` | Sent back for revision | ⚠️ | When revision needed |
| `ditolak` | Soft-deleted/Rejected | ❌ | User deletes OR rejected 
| `disetujui` | Approved - complete | ❌ | Final approval |
| `selesai` | Position marker (complete) | Companion to status | Final state |

---

## Recommendations

### Current State ✅
- Soft-delete implementation is sound
- Data preservation for audit/compliance
- Proper filtering keeps deleted apps out of workflow
- Analis still sees their work for reference

### Potential Improvements
1. Consider adding a `deleted_at` timestamp column for better tracking
2. Add soft-delete status badge to detail view (show "DIBATALKAN" message)
3. Could restrict analis visibility of ditolak apps if needed (update riwayat.php WHERE clause)
4. Add bulk delete capability with confirmation dialog
