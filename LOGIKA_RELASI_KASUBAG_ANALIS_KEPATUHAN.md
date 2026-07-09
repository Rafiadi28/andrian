# 📋 LOGIKA RELASI KASUBAG ANALIS + KEPATUHAN - APPROVAL CHAIN LENGKAP

## 🎯 RINGKASAN EKSEKUTIF

**Alur Approval Lengkap (BARU - Kepatuhan dalam chain):**
```
┌─────────────────────────────────────────────────────────────────────────┐
│ ANALIS → KEPATUHAN → KASUBAG_ANALIS → KABAG_KREDIT → KADIV_BISNIS     │
└─────────────────────────────────────────────────────────────────────────┘
              ↓
        (Nominal Check)
        
├─ Rp 1 Juta - Rp 500 Juta:
│  └─ STOP di KADIV_BISNIS (No need Direktur)
│
└─ Rp 500 Juta - Rp 1 Milyar:
   └─ Lanjut ke DIREKTUR_UTAMA → SELESAI
```

**KEPATUHAN** adalah bagian INTEGRAL dari approval chain (bukan track paralel).
Sebelum pengajuan masuk ke **KASUBAG_ANALIS**, kepatuhan harus sudah memberikan penilaian.

---

## 📊 APPROVAL HIERARCHY BARU (NEW CHAIN)

### Hierarchy Approval Chain
```php
// Di includes/functions.php - getHierarchy() [BARU]
return [
    'analis',           // Input Operator
    'kepatuhan',        // Compliance Assessment  ← BARU (integrated)
    'kasubag_analis',   // Level 1 Approval
    'kabag_kredit',     // Level 2 Approval
    'kadiv_bisnis',     // Level 3 Approval (or Kadiv Kredit)
    'direktur_utama'    // Level 4 Approval
];
```

### Status Pengajuan & Mapping Role
| Level | Enum Status | Role | Deskripsi |
|-------|-------------|------|-----------|
| **Analis** | `draft` | `analis` | Operator input data (6 section form) |
| **Kepatuhan** | `kepatuhan` | `kepatuhan` | ⭐ Compliance Assessment (INTEGRATED) |
| **Kasubag Analis** | `kasubag` | `kasubag_analis` | Sub-head analysis review |
| **Kabag Kredit** | `kabag` | `kabag_kredit` | Cabinet head credit review |
| **Kadiv Bisnis** | `kadiv` | `kadiv_bisnis` | Division head business review |
| **Direktur Utama** | `direksi` | `direktur_utama` | Chief director final approval |
| **Final** | `disetujui` | N/A | Pengajuan Disetujui/Selesai |

### Perbedaan dengan Alur Lama
| Aspek | Alur Lama | Alur Baru |
|-------|----------|----------|
| **Kepatuhan** | Track PARALEL (non-blocking) | Track INTEGRATED (blocking step) |
| **Urutan** | Analis → Kasubag → Kabag → Kadiv → Direksi | Analis → **Kepatuhan** → Kasubag → Kabag → Kadiv → Direksi |
| **Compliance Check** | Di setiap level (proses_template) | AUTO-CHECK sebelum masuk kasubag |
| **Nominal Logic** | < 500M stop kadiv, ≥ 500M go direksi | SAMA |

---

## 🔄 ALUR APPROVAL LENGKAP (NEW - INTEGRATED KEPATUHAN)

### FASE 1: ANALIS SUBMIT & AUTO-ROUTE KE KEPATUHAN

```
STEP 1.1: Analis Isi Form Lengkap
├─ Section 1: Data Pemohon ✓
├─ Section 2: Penghasilan (sesuai jenis pekerjaan) ✓
├─ Section 3: Agunan/Jaminan ✓
├─ Section 4: Analisa Neraca ✓
├─ Section 5: Analisa 5C ✓
└─ Section 6: Cashflow ✓

STEP 1.2: Klik "SUBMIT / KIRIM KE APPROVAL"
└─ File: analis/save_section.php (case 'submit')

STEP 1.3: System Processing
├─ a. findNextTarget('analis', $pdo, $jumlah_kredit)
│    └─ Result: ['role' => 'kepatuhan', 'skipped' => []]
│       (Selalu ke kepatuhan - mandatory step)
│
├─ b. Update pengajuan_kredit:
│    ├─ status_pengajuan: 'draft' → 'kepatuhan'
│    ├─ posisi_saat_ini: 'analis' → 'kepatuhan'
│    ├─ last_revision_at: NULL
│    ├─ last_revision_by: NULL
│    └─ last_reject_level: NULL
│
├─ c. Create approval_kredit record:
│    ├─ id_pengajuan: (dari form)
│    ├─ id_user: $_SESSION['user_id'] (analis yg submit)
│    ├─ level_approval: 'analis'
│    ├─ keputusan: 'setuju'
│    ├─ catatan: 'Pengajuan lengkap. Siap untuk assessment kepatuhan.'
│    └─ is_auto_skip: 0
│
└─ d. Create placeholder untuk kepatuhan (opsional)
     ├─ approval_kredit dengan level='kepatuhan', keputusan='pending'
     └─ Tunggu kepatuhan memberikan penilaian

RESULT: 
✓ Pengajuan di queue KEPATUHAN
✓ Status: posisi_saat_ini = 'kepatuhan', status_pengajuan = 'kepatuhan'
✓ Kepatuhan bisa akses di: kepatuhan/assesmen.php?action=list
```

---

### FASE 2: KEPATUHAN ASSESSMENT (INTEGRATED STEP)

```
STEP 2.1: Kepatuhan Lihat Daftar Pengajuan
├─ URL: kepatuhan/assesmen.php?action=list
├─ Query: 
│  SELECT id_pengajuan, nama_debitur, jumlah_kredit, ...
│  FROM pengajuan_kredit
│  WHERE posisi_saat_ini = 'kepatuhan'
│    AND status_pengajuan IN ('kepatuhan', 'proses')
└─ Filter: Hanya menampilkan pengajuan yang ditugaskan ke kepatuhan

STEP 2.2: Kepatuhan Buka Detail & Isi Assessment Form
├─ URL: kepatuhan/assesmen.php?action=form&id=X
├─ Form Fields:
│  ├─ Checklist Item 1, 2, 3, ... (JSON checklist_data)
│  ├─ Kesesuaian Dokumen (dropdown)
│  ├─ Kelengkapan Dokumen (dropdown)
│  ├─ Kualitas Dokumen (dropdown)
│  ├─ Kesesuaian Nasabah (dropdown)
│  ├─ Status Kepegawaian (text)
│  ├─ Analisa Risiko Detail (JSON)
│  ├─ Dampak Risiko (dropdown)
│  ├─ Mitigasi Risiko (text)
│  ├─ KESIMPULAN ⭐ [REQUIRED untuk lengkap]
│  ├─ REKOMENDASI ⭐ [REQUIRED untuk lengkap]
│  └─ Catatan Tambahan (text)
│
└─ Auto-populate: Data dari analis (jika ada)

STEP 2.3: Kepatuhan Submit Assessment
├─ POST: api/save_assessment_kepatuhan.php
├─ Save to: assessment_kepatuhan table
│  ├─ id_pengajuan: (dari form)
│  ├─ id_user: $_SESSION['user_id'] (kepatuhan staff)
│  ├─ checklist_data: (JSON)
│  ├─ kesimpulan: (TEXT) ⭐ MANDATORY
│  ├─ rekomendasi: (TEXT) ⭐ MANDATORY
│  ├─ kesesuaian_dokumen: (dropdown value)
│  ├─ kelengkapan_dokumen: (dropdown value)
│  └─ tanggal_assessment: CURRENT_TIMESTAMP
│
└─ Update pengajuan_kredit:
   ├─ status_pengajuan: 'kepatuhan' → 'diajukan' (auto-move)
   └─ posisi_saat_ini: 'kepatuhan' → 'kasubag_analis' (auto-route)

STEP 2.4: Auto-Create Approval Record untuk Kepatuhan
└─ INSERT into approval_kredit:
   ├─ level_approval: 'kepatuhan'
   ├─ keputusan: 'setuju' (auto-accept, bukan blocking decision)
   ├─ catatan: 'Assessment kepatuhan selesai: [kesimpulan]'
   ├─ id_user: (kepatuhan staff id)
   └─ is_auto_skip: 0

RESULT:
✓ Assessment kepatuhan LENGKAP & tersimpan
✓ Pengajuan AUTO-ROUTED ke kasubag_analis
✓ Status: posisi_saat_ini = 'kasubag_analis'
✓ Status: status_pengajuan = 'diajukan'
✓ Compliance check PASSED, ready untuk kasubag_analis review
```

---

### FASE 3: KASUBAG_ANALIS REVIEW (LEVEL 1 APPROVAL)

```
STEP 3.1: Kasubag Analis Lihat Antrian
├─ URL: kasubag_analis/proses.php
├─ Query:
│  SELECT id_pengajuan, nama_debitur, jumlah_kredit, ...
│  FROM pengajuan_kredit
│  WHERE posisi_saat_ini = 'kasubag_analis'
│    AND status_pengajuan IN ('proses', 'diajukan', ...)
│
└─ Filter: Show only pengajuan di queue kasubag_analis

STEP 3.2: Compliance Status Indicator (AUTO-CHECK)
├─ Query assessment_kepatuhan:
│  SELECT id_pengajuan, 
│         CASE 
│             WHEN id_assessment IS NOT NULL 
│              AND checklist_data IS NOT NULL 
│              AND kesimpulan IS NOT NULL 
│             THEN 'lengkap'
│             ELSE 'tidak_ada'
│         END as status
│  FROM assessment_kepatuhan
│  WHERE id_pengajuan IN (...)
│
├─ Display Badge:
│  ├─ ✓ Compliance OK (green) - lengkap
│  └─ ✗ Waiting Compliance (red) - tidak ada
│
└─ Note: Jika compliance TIDAK lengkap → ROW BLOCKED (background highlight)
         Tombol "Proses" disabled, tunjukkan: "⚠ Blokir approval - Waiting Compliance"

STEP 3.3: Kasubag Buat Keputusan
├─ 3 Pilihan:
│
│ OPTION 1: SETUJU (Approve)
│ ├─ Update pengajuan_kredit:
│ │  ├─ status_pengajuan: 'diajukan' → 'kabag'
│ │  ├─ posisi_saat_ini: 'kasubag_analis' → 'kabag_kredit'
│ │  └─ Clear revision flags
│ │
│ ├─ Create approval_kredit:
│ │  ├─ level_approval: 'kasubag_analis'
│ │  ├─ keputusan: 'setuju'
│ │  ├─ catatan: (dari form)
│ │  └─ id_user: (kasubag staff id)
│ │
│ ├─ Auto-skip inactive roles (if any)
│ │  └─ Create auto-approval records untuk inactive roles
│ │
│ └─ Result: Pengajuan masuk ke KABAG_KREDIT queue
│
│ OPTION 2: REVISI (Request Changes)
│ ├─ Update pengajuan_kredit:
│ │  ├─ status_pengajuan: 'diajukan' → 'revisi'
│ │  ├─ posisi_saat_ini: 'kasubag_analis' → 'analis'
│ │  ├─ last_revision_at: CURRENT_TIMESTAMP
│ │  ├─ last_revision_by: (kasubag staff id)
│ │  └─ catatan_revisi: (revision notes)
│ │
│ ├─ Create approval_kredit:
│ │  ├─ level_approval: 'kasubag_analis'
│ │  ├─ keputusan: 'revisi'
│ │  └─ catatan: (revision reason)
│ │
│ └─ Result: Pengajuan KEMBALI ke ANALIS untuk edit
│    Analis akan lihat di: analis/riwayat.php
│    Analis bisa edit & resubmit
│
│ OPTION 3: TOLAK (Reject)
│ ├─ Update pengajuan_kredit:
│ │  ├─ status_pengajuan: 'diajukan' → 'ditolak'
│ │  ├─ posisi_saat_ini: 'kasubag_analis' → 'analis'
│ │  ├─ last_reject_level: 'kasubag_analis'
│ │  ├─ ditolak_dari_role: 'kasubag_analis'
│ │  └─ alasan_penolakan: (rejection reason)
│ │
│ ├─ Create approval_kredit:
│ │  ├─ level_approval: 'kasubag_analis'
│ │  ├─ keputusan: 'tolak'
│ │  └─ catatan: (rejection reason)
│ │
│ └─ Result: Pengajuan MARKED REJECTED
│    Analis bisa edit & kirim_ulang untuk resubmit

RESULT:
✓ Keputusan tersimpan di approval_kredit
✓ Pengajuan maju ke step berikutnya (atau kembali ke analis)
```

---

### FASE 4: KABAG_KREDIT REVIEW (LEVEL 2 APPROVAL)

```
STEP 4.1: Kabag Kredit Lihat Antrian
├─ URL: kabag_kredit/proses.php
├─ Query:
│  SELECT id_pengajuan, nama_debitur, jumlah_kredit, ...
│  FROM pengajuan_kredit
│  WHERE posisi_saat_ini = 'kabag_kredit'
│    AND status_pengajuan IN ('proses', 'diajukan', 'kabag', ...)
│
└─ Filter: Only pengajuan di queue kabag_kredit

STEP 4.2: Compliance Status Indicator (SAME AS KASUBAG)
└─ Display status kepatuhan (should be 'lengkap' sudah)

STEP 4.3: Kabag Kredit Buat Keputusan
├─ Same 3 options: SETUJU / REVISI / TOLAK
└─ Flow: Same as Kasubag Analis
   ├─ SETUJU → posisi_saat_ini = 'kadiv_bisnis'
   ├─ REVISI → posisi_saat_ini = 'analis' (back to start)
   └─ TOLAK → marked rejected, back to analis

RESULT:
✓ Pengajuan maju ke KADIV_BISNIS (or back to analis)
```

---

### FASE 5: KADIV_BISNIS REVIEW (LEVEL 3 APPROVAL)

```
STEP 5.1: Kadiv Bisnis Lihat Antrian
├─ URL: kadiv_bisnis/proses.php
├─ Query:
│  SELECT id_pengajuan, nama_debitur, jumlah_kredit, ...
│  FROM pengajuan_kredit
│  WHERE posisi_saat_ini = 'kadiv_bisnis'
│    AND status_pengajuan IN ('proses', 'diajukan', 'kadiv', ...)
│
└─ Filter: Only pengajuan di queue kadiv_bisnis

STEP 5.2: NOMINAL CHECK - CRITICAL DECISION POINT
├─ Check jumlah_kredit dari pengajuan_kredit
│
├─ NOMINAL LOGIC:
│  │
│  ├─ IF jumlah_kredit < 500,000,000 (< Rp 500 Juta):
│  │  │
│  │  ├─ SETUJU:
│  │  │  ├─ status_pengajuan: 'diajukan' → 'disetujui' (FINAL APPROVAL)
│  │  │  ├─ posisi_saat_ini: 'kadiv_bisnis' → 'selesai'
│  │  │  └─ Pengajuan SELESAI (no need direktur utama)
│  │  │
│  │  └─ REVISI/TOLAK: Same as before (back to analis)
│  │
│  └─ ELSE IF jumlah_kredit >= 500,000,000 (≥ Rp 500 Juta):
│     │
│     ├─ SETUJU:
│     │  ├─ status_pengajuan: 'diajukan' → 'direksi' (NOT final yet)
│     │  ├─ posisi_saat_ini: 'kadiv_bisnis' → 'direktur_utama'
│     │  └─ Pengajuan goes to DIREKTUR_UTAMA for final decision
│     │
│     └─ REVISI/TOLAK: Same as before (back to analis)

STEP 5.3: Kadiv Bisnis Decision Form
├─ Display nominal amount prominently
├─ Show: "Nominal Rp X.XXX.XXX.XXX"
├─ Show: "Tier Level: < 500 Juta | >= 500 Juta"
├─ Form options:
│  ├─ SETUJU
│  ├─ REVISI
│  └─ TOLAK
└─ Catatan text field

RESULT:
✓ Jika < 500 Juta & SETUJU → pengajuan FINAL APPROVED
✓ Jika >= 500 Juta & SETUJU → pengajuan to DIREKTUR_UTAMA
✓ Jika REVISI/TOLAK → back to ANALIS
```

---

### FASE 6: DIREKTUR_UTAMA REVIEW (LEVEL 4 APPROVAL - HANYA UNTUK >= 500 JUTA)

```
STEP 6.1: Direktur Lihat Antrian
├─ URL: direksi/proses.php
├─ Query:
│  SELECT id_pengajuan, nama_debitur, jumlah_kredit, ...
│  FROM pengajuan_kredit
│  WHERE posisi_saat_ini = 'direktur_utama'
│    AND status_pengajuan IN ('proses', 'diajukan', 'direksi', ...)
│
└─ Note: Hanya pengajuan dengan jumlah_kredit >= 500 Juta yang sampai sini

STEP 6.2: Compliance Status Indicator (AUTO-CHECK)
└─ Display kepatuhan status (should be 'lengkap')

STEP 6.3: Direktur Buat FINAL Decision
├─ 3 Options:
│
│ OPTION 1: SETUJU (FINAL APPROVAL)
│ ├─ Update pengajuan_kredit:
│ │  ├─ status_pengajuan: 'diajukan' → 'disetujui' (FINAL STATUS)
│ │  ├─ posisi_saat_ini: 'direktur_utama' → 'selesai'
│ │  └─ Create approval_kredit dengan level='direktur_utama', keputusan='setuju'
│ │
│ └─ Result: Pengajuan FULLY APPROVED ✓
│    ├─ Trigger email notification ke semua stakeholder
│    ├─ Mark ready untuk disbursement
│    └─ Create signing document untuk print
│
│ OPTION 2: REVISI
│ ├─ Send back to ANALIS for edit
│ └─ Same workflow as before
│
│ OPTION 3: TOLAK (FINAL REJECTION)
│ ├─ Mark as ditolak final
│ └─ Analis dapat resubmit jika ada perbaikan

RESULT:
✓ Pengajuan FINAL APPROVED → status = 'disetujui', posisi = 'selesai'
✓ Ready untuk disbursement (pencairan dana)
```

---

### FASE 7: AUTO-SKIP MECHANISM

```
Ketika ada role yang INACTIVE dalam hierarchy, sistem AUTO-SKIP:

SCENARIO: Kasubag Analis inactive saat ada pengajuan baru dari analis
├─ findNextTarget('analis', $pdo, $nominal) dipanggil
├─ System check: Status kasubag_analis di users table = 'sakit'
├─ Result: Skip kasubag_analis, lanjut ke kabag_kredit
├─ Auto-create approval record:
│  ├─ level_approval: 'kasubag_analis'
│  ├─ keputusan: 'eskalasi_otomatis' (atau 'setuju')
│  ├─ is_auto_skip: 1 (mark sebagai auto-skip)
│  ├─ id_user: NULL (system approval, no user)
│  └─ catatan: 'Auto-skipped: Kasubag Analis tidak aktif'
│
└─ Pengajuan langsung masuk ke queue KABAG_KREDIT

AFFECTED ROLES: Any role in hierarchy dapat di-skip jika inactive
```

---

## ⚡ LOGIKA NOMINAL (CRITICAL BUSINESS RULE)

```
THRESHOLD: Rp 500,000,000 (500 Juta)

┌─────────────────────────────────────────────────────────────────┐
│ RENTANG 1: Rp 1,000,000 - Rp 500,000,000                        │
├─────────────────────────────────────────────────────────────────┤
│ Alur: Analis → Kepatuhan → Kasubag → Kabag → Kadiv Bisnis      │
│ Final Approval: KADIV_BISNIS (NO Direktur Utama needed)        │
│ Status Final: 'disetujui', posisi='selesai'                    │
│ Keputusan Kadiv: SETUJU → Langsung FINAL (tidak lanjut)       │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ RENTANG 2: Rp 500,000,000 - Rp 1,000,000,000 (1 Milyar)         │
├─────────────────────────────────────────────────────────────────┤
│ Alur: Analis → Kepatuhan → Kasubag → Kabag → Kadiv → DIREKSI   │
│ Final Approval: DIREKTUR_UTAMA (mandatory step)                │
│ Status di Kadiv: 'direksi' (NOT final yet)                    │
│ Keputusan Kadiv: SETUJU → LANJUT ke Direktur Utama           │
│ Status Final: 'disetujui', posisi='selesai'                    │
└─────────────────────────────────────────────────────────────────┘

IMPLEMENTATION:
├─ Function: getMaxApprovalLevel($jumlah_kredit) di functions.php
│  ├─ IF $jumlah_kredit < 500,000,000:
│  │  └─ return 'kadiv_bisnis'
│  └─ ELSE:
│     └─ return 'direktur_utama'
│
├─ Function: findNextTarget() call getMaxApprovalLevel()
│  └─ Stop routing jika sudah reach maximal approval level
│
└─ Check di setiap level approval
   └─ Jika sudah at maximal level & SETUJU → mark as 'disetujui'
```

---

## 📋 SUMMARY TABLE - ALUR LENGKAP

| Step | Role | Input | Decision | Output Jika SETUJU | Output Jika REVISI | Output Jika TOLAK |
|------|------|-------|----------|-------------------|--------------------|-------------------|
| 1 | **Analis** | Draft form 6 section | SUBMIT | posisi=kepatuhan | N/A | N/A |
| 2 | **Kepatuhan** | Assessment form | ASSESSMENT | posisi=kasubag_analis | N/A | N/A |
| 3 | **Kasubag Analis** | Review detail | APPROVE | posisi=kabag_kredit | posisi=analis (revisi) | posisi=analis (tolak) |
| 4 | **Kabag Kredit** | Review detail | APPROVE | posisi=kadiv_bisnis | posisi=analis (revisi) | posisi=analis (tolak) |
| 5a | **Kadiv Bisnis** (< 500M) | Review detail | APPROVE | **FINAL ✓** (selesai) | posisi=analis | posisi=analis |
| 5b | **Kadiv Bisnis** (≥ 500M) | Review detail | APPROVE | posisi=direktur_utama | posisi=analis | posisi=analis |
| 6 | **Direktur Utama** | Review detail | APPROVE | **FINAL ✓** (selesai) | posisi=analis | posisi=analis |


---

## ✅ FILE-FILE YANG PERLU DIUPDATE (IMPLEMENTATION CHECKLIST)

#### 3. Button State (Line ~228-235)
```html
<!-- COMPLIANCE OK -->
<button onclick="openModal(...)" class="btn btn-primary">
    Proses
</button>

<!-- COMPLIANCE NOT COMPLETE -->
<button class="btn btn-secondary" style="opacity: 0.6;" disabled 
    title="Approval diblokir: menunggu assessment kepatuhan"
    onclick="alert('⚠️ Pengajuan ini TIDAK BISA diproses sampai Dept. Kepatuhan menyelesaikan assessment...')">
    Proses (Blokir)
</button>
```

---

## 🔍 VERIFIKASI RELASI

### Query Pengecekan Status Kepatuhan
```sql
-- Check assessment lengkap atau belum
SELECT 
    p.id_pengajuan,
    p.nama_debitur,
    p.status_pengajuan,


| Task | File | Status | Notes |
|------|------|--------|-------|
| ✅ Add 'kepatuhan' to hierarchy | includes/functions.php - getHierarchy() | DONE | Return: ['analis', 'kepatuhan', 'kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama'] |
| ✅ Update findNextTarget() | includes/functions.php | DONE | Route analis → kepatuhan (mandatory) |
| ✅ Add kepatuhan auto-routing | analis/save_section.php case 'submit' | DONE | After analis submit → posisi_saat_ini = 'kepatuhan' |
| ⏳ Create kepatuhan/proses.php | kepatuhan/ | TODO | List pengajuan di queue kepatuhan |
| ⏳ Create kepatuhan/detail.php | kepatuhan/ | TODO | Detail & assessment form untuk kepatuhan |
| ⏳ Update processApproval() | includes/functions.php | TODO | Handle kepatuhan decision → route to kasubag_analis |
| ✅ Compliance blocking logic | includes/proses_template.php | DONE | Disable tombol jika compliance ≠ 'lengkap' |
| ✅ Nominal routing logic | includes/functions.php - getMaxApprovalLevel() | DONE | < 500M stop kadiv_bisnis, >= 500M go direktur_utama |
| ⏳ Update all proses.php files | kasubag_analis/, kabag_kredit/, kadiv_bisnis/, direksi/ | TODO | Verify nominal logic di setiap level |
| ✅ Database enum updates | database.sql | DONE | approval_kredit level_approval includes 'kepatuhan' |

---

## 📋 QUERY VERIFICATION - CEK RELASI SAAT INI

### 1. Check Pengajuan di Queue Kepatuhan (NEW)
```sql
SELECT 
    id_pengajuan,
    nama_debitur,
    jumlah_kredit,
    posisi_saat_ini,
    status_pengajuan,
    tanggal_pengajuan
FROM pengajuan_kredit
WHERE posisi_saat_ini = 'kepatuhan'
  AND status_pengajuan IN ('kepatuhan', 'proses')
ORDER BY tanggal_pengajuan DESC;
```

### 2. Check Approval Chain Lengkap
```sql
SELECT 
    a.level_approval,
    COUNT(*) as total_approvals,
    SUM(CASE WHEN a.keputusan = 'setuju' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN a.keputusan = 'revisi' THEN 1 ELSE 0 END) as revised,
    SUM(CASE WHEN a.keputusan = 'tolak' THEN 1 ELSE 0 END) as rejected
FROM approval_kredit a
WHERE a.id_pengajuan = 123  -- Replace dengan id pengajuan
GROUP BY a.level_approval
ORDER BY FIELD(a.level_approval, 'analis', 'kepatuhan', 'kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama');

-- Expected result untuk pengajuan lengkap:
-- | analis | 1 | 1 | 0 | 0 |
-- | kepatuhan | 1 | 1 | 0 | 0 |
-- | kasubag_analis | 1 | 1 | 0 | 0 |
-- | kabag_kredit | 1 | 1 | 0 | 0 |
-- | kadiv_bisnis | 1 | 1 | 0 | 0 |  (< 500M) STOP HERE
-- | direktur_utama | 1 | 1 | 0 | 0 | (≥ 500M) CONTINUE
```

### 3. Check Compliance Status untuk Pengajuan di Queue
```sql
SELECT 
    p.id_pengajuan,
    p.nama_debitur,
    p.jumlah_kredit,
    p.posisi_saat_ini,
    CASE 
        WHEN a.id_assessment IS NOT NULL 
          AND a.checklist_data IS NOT NULL 
          AND a.kesimpulan IS NOT NULL 
        THEN 'LENGKAP ✓'
        WHEN a.id_assessment IS NOT NULL 
        THEN 'PARTIAL ⚠'
        ELSE 'WAITING ✗'
    END as compliance_status,
    CASE 
        WHEN a.id_assessment IS NULL THEN 'No assessment yet'
        WHEN a.checklist_data IS NULL THEN 'Missing checklist'
        WHEN a.kesimpulan IS NULL THEN 'Missing kesimpulan'
        ELSE 'All fields complete'
    END as compliance_detail
FROM pengajuan_kredit p
LEFT JOIN assessment_kepatuhan a ON p.id_pengajuan = a.id_pengajuan
WHERE p.posisi_saat_ini IN ('kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama')
  AND p.status_pengajuan IN ('proses', 'diajukan', 'kasubag', 'kabag', 'kadiv', 'direksi')
ORDER BY p.tanggal_pengajuan DESC;
```

### 4. Check Nominal Routing (< 500M vs >= 500M)
```sql
-- Pengajuan < 500 Juta - harus stop di kadiv_bisnis
SELECT 
    p.id_pengajuan,
    p.nama_debitur,
    p.jumlah_kredit,
    p.posisi_saat_ini,
    CASE 
        WHEN p.jumlah_kredit < 500000000 AND p.posisi_saat_ini = 'selesai' THEN '✓ Correct (< 500M, already done)'
        WHEN p.jumlah_kredit < 500000000 AND p.posisi_saat_ini IN ('kadiv_bisnis', 'kasubag_analis') THEN '✓ OK (< 500M, still processing)'
        WHEN p.jumlah_kredit < 500000000 AND p.posisi_saat_ini = 'direktur_utama' THEN '✗ ERROR (< 500M, should not be at direktur)'
        ELSE '?' 
    END as routing_check
FROM pengajuan_kredit p
WHERE p.status_pengajuan != 'draft'
ORDER BY p.jumlah_kredit DESC;

-- Pengajuan >= 500 Juta - harus lanjut ke direktur_utama
SELECT 
    p.id_pengajuan,
    p.nama_debitur,
    p.jumlah_kredit,
    p.posisi_saat_ini,
    CASE 
        WHEN p.jumlah_kredit >= 500000000 AND p.posisi_saat_ini = 'selesai' THEN '✓ Correct (>= 500M, already approved)'
        WHEN p.jumlah_kredit >= 500000000 AND p.posisi_saat_ini = 'direktur_utama' THEN '✓ OK (>= 500M, at direktur)'
        WHEN p.jumlah_kredit >= 500000000 AND p.posisi_saat_ini = 'kadiv_bisnis' THEN '✓ OK (>= 500M, waiting direktur approval)'
        WHEN p.jumlah_kredit >= 500000000 AND p.posisi_saat_ini = 'selesai' THEN '? Check if approved by direktur'
        ELSE '?' 
    END as routing_check
FROM pengajuan_kredit p
WHERE p.status_pengajuan != 'draft'
ORDER BY p.jumlah_kredit DESC;
```

---

## 🎯 KESIMPULAN - ALUR APPROVAL FINAL

### Chain Baru (INTEGRATED):
```
ANALIS → KEPATUHAN → KASUBAG_ANALIS → KABAG_KREDIT → KADIV_BISNIS → (Optional) DIREKTUR_UTAMA → SELESAI
```

### Nominal Logic:
```
IF jumlah_kredit < 500,000,000 (Rp 500 Juta):
    └─ STOP at KADIV_BISNIS (tidak lanjut ke direktur)
    └─ Status FINAL: 'disetujui', posisi = 'selesai'

IF jumlah_kredit >= 500,000,000 (Rp 500 Juta ke atas):
    └─ LANJUT ke DIREKTUR_UTAMA (mandatory step)
    └─ Status FINAL: 'disetujui', posisi = 'selesai' (setelah direktur approve)
```

### Kepatuhan Role:
- **INTEGRATED step**: Mandatory sebelum masuk kasubag_analis
- **Auto-routing**: Setelah kepatuhan selesai assessment → otomatis ke kasubag_analis
- **Blocking**: Approval di level lain BLOCKED jika kepatuhan belum lengkap
- **Audit Trail**: All kepatuhan assessments recorded di approval_kredit table

---

**Status**: ✅ DOCUMENTED & READY FOR IMPLEMENTATION  
**Last Updated**: 29 May 2026  
**Dokumentasi**: LENGKAP - Alur Kepatuhan Terintegrasi + Nominal Logic
