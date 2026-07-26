# 🔄 IMPLEMENTATION GUIDE: APPROVAL ROUTING DENGAN PENGGANTI KASUBAG

**Date**: 26 Juli 2026  
**Status**: Implementation Documentation  
**Purpose**: Logika approval otomatis beralih ke Direktur Utama jika Kasubag cuti/nonaktif

---

## 📋 RINGKASAN REQUIREMENT

### Kondisi 1: Kasubag AKTIF
```
Alur approval normal:
Analis → Kasubag → Kepatuhan → Kabag → Kadiv → Direksi
```

### Kondisi 2: Kasubag CUTI/NONAKTIF
```
Alur approval dengan pengganti:
Analis → [Direktur Utama GANTI Kasubag] → Kepatuhan → Kabag → Kadiv → (Selesai)
                                                                     ↑
                                                        Langsung selesai tanpa Direksi lagi
```

---

## 🏗️ ARSITEKTUR SISTEM SAAT INI

### File-File Terkait:

1. **`includes/functions.php`**
   - `findNextTarget()` - Cari role berikutnya berdasarkan hierarchy
   - `getHierarchy()` - Define urutan approval chain

2. **`includes/approval_routing.php`** (NEW/EXISTING)
   - `resolve_next_active_role()` - Tentukan role aktif dengan fallback
   - `get_active_users_for_role()` - Ambil user aktif untuk role tertentu

3. **`analis/save_section.php`**
   - Submit pengajuan & tentukan target approval berikutnya
   - Gunakan `resolve_next_active_role()` untuk mendapat role aktif

4. **`*/approval_action.php`** (Various roles)
   - Handle approval submission
   - Submit to next role

5. **`detail.php`**
   - Tampilkan riwayat approval
   - Show current status

---

## 🔧 IMPLEMENTASI LOGIC

### STEP 1: Perbaiki `findNextTarget()` di functions.php

**Current Issue:**
- Hanya skip role kalau tidak ada user aktif
- Tidak ada fallback mapping untuk Kasubag → Direksi

**Target:**
- Skip Kasubag jika tidak aktif
- Fallback ke Direksi (sebagai pengganti)
- Lanjut approval chain normal setelah Direksi approval

**Perubahan yang diperlukan:**
```php
// CURRENT (findNextTarget di functions.php)
function findNextTarget($currentRole, $pdo, $jumlah_kredit = null) {
    $hierarchy = getHierarchy();
    // ...logic check active users...
    // Jika user tidak aktif, skip ke role berikutnya
}

// IMPROVED LOGIC (di approval_routing.php)
function resolve_next_active_role($pdo, string $desiredRole): ?string {
    // 1. Check: apakah desiredRole punya user aktif?
    // 2. JA → return desiredRole
    // 3. TIDAK → cek fallback mapping
    // 4. Fallback untuk kasubag_analis = direktur_utama
    // 5. Return direktur_utama jika aktif
    // 6. Jika tidak, lanjut chain
}
```

---

### STEP 2: Update Fallback Mapping

**File**: `includes/approval_routing.php`

**Current Fallback:**
```php
$fallback = [
    'kasubag_analis' => 'kepatuhan',  // ❌ WRONG
    'kepatuhan' => 'kabag_kredit',
    'kabag_kredit' => 'kadiv_bisnis',
    'kadiv_bisnis' => 'direktur_utama'
];
```

**Target Fallback (NEW):**
```php
$fallback = [
    'kasubag_analis' => 'direktur_utama',  // ✅ CORRECT - Kasubag cuti → Direksi ganti
    'kepatuhan' => 'kabag_kredit',
    'kabag_kredit' => 'kadiv_bisnis',
    'kadiv_bisnis' => 'direktur_utama'
];
```

**Alasan:**
- Kasubag → Direktur Utama: Pengganti resmi dari Direksi
- Kepatuhan → Kabag: Compliance sudah selesai
- Kabag → Kadiv: Next in chain
- Kadiv → Direksi: Final approval

---

### STEP 3: Update Approval Chain Logic

**File**: `includes/functions.php`

**Current getHierarchy():**
```php
function getHierarchy() {
    return ['analis', 'kasubag_analis', 'kepatuhan', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama'];
}
```

**Perubahan Needed:**
- Masih sama (jangan ubah)
- Tapi logic di `findNextTarget()` harus gunakan `resolve_next_active_role()`

---

### STEP 4: Flow Diagram

```
┌─ Analis submit pengajuan
│
├─ findNextTarget('analis') → cari role berikutnya
│  │
│  └─ Hasil: 'kasubag_analis'
│
├─ resolve_next_active_role('kasubag_analis') → tentukan role aktif
│  │
│  ├─ Check: ada user dengan role='kasubag_analis' & status='aktif'?
│  │
│  ├─ YA (Kasubag aktif)
│  │  └─ Return 'kasubag_analis'
│  │     └─ Approval ke Kasubag
│  │
│  └─ TIDAK (Kasubag cuti/nonaktif)
│     ├─ Check fallback: kasubag_analis → direktur_utama
│     ├─ ada user dengan role='direktur_utama' & status='aktif'?
│     │
│     ├─ YA
│     │  └─ Return 'direktur_utama'
│     │     └─ Approval ke Direksi (pengganti Kasubag)
│     │
│     └─ TIDAK
│        └─ Lanjut chain ke role berikutnya (kepatuhan, dll)
│
└─ Update posisi_saat_ini dengan role yang sudah di-resolve
```

---

## 📝 DETAIL IMPLEMENTASI

### 1. **Perbaiki approval_routing.php**

**Current:**
```php
$fallback = [
    'kasubag_analis' => 'kepatuhan',  // ❌ SALAH
    ...
];
```

**Change to:**
```php
$fallback = [
    'kasubag_analis' => 'direktur_utama',  // ✅ BENAR - Pengganti resmi
    ...
];
```

---

### 2. **Ensure save_section.php menggunakan resolve_next_active_role()**

**Current:**
```php
// Line ~2047
$nextStep = findNextTarget('analis', $pdo, $jumlah_kredit);
$targetRole = $nextStep['role'];

// Resolve target role considering cuti/fallback rules
require_once __DIR__ . '/../includes/approval_routing.php';
$resolvedTarget = resolve_next_active_role($pdo, $targetRole) ?? $targetRole;

// Update pengajuan dengan resolved role
$stmt = $pdo->prepare("UPDATE pengajuan_kredit SET posisi_saat_ini=? WHERE id_pengajuan=?");
$stmt->execute([$resolvedTarget, $id_pengajuan]);
```

**Status: ✅ SUDAH BENAR** (code sudah ada!)

---

### 3. **Update approval_action.php untuk role berikutnya**

**File**: `kasubag_analis/approval_action.php` atau equivalent

**Logika yang harus ditambah:**
```php
// Setelah Kasubag/Pengganti approve
$nextStep = findNextTarget('kasubag_analis', $pdo, $pengajuan['jumlah_kredit']);
$targetRole = $nextStep['role'];

// RESOLVE to check if kasubag was substituted
$resolvedTarget = resolve_next_active_role($pdo, $targetRole) ?? $targetRole;

// Update pengajuan
UPDATE pengajuan_kredit SET posisi_saat_ini = ? WHERE id_pengajuan = ?
// + Buat approval record
INSERT INTO approval_kredit (id_pengajuan, id_user, level_approval, keputusan) ...
```

---

### 4. **Riwayat Approval Display**

**File**: `detail.php` atau `*/dashboard.php`

**Query untuk tampilkan approval history:**
```php
SELECT * FROM approval_kredit 
WHERE id_pengajuan = ?
ORDER BY tanggal_approval ASC

// Tampilkan:
// ✓ Analis Kredit (2026-07-20 10:30)
// ✓ Direktur Utama - Pengganti Kasubag (2026-07-20 11:00)  ← Note "Pengganti"
// ✓ Kepatuhan (2026-07-20 11:30)
// ✓ Kabag Kredit (2026-07-20 12:00)
// ✓ Kadiv Bisnis (2026-07-20 13:00)
// ✓ Selesai
```

**Need to add column di approval_kredit:**
- `is_substitute` (0/1) - apakah ini approval pengganti?
- `substitute_for` (varchar) - menggantikan role apa? (e.g., 'kasubag_analis')

---

## 🚀 CHECKLIST IMPLEMENTASI

### Phase 1: Setup & Validation (2-3 jam)

- [ ] Review & confirm `approval_routing.php` fallback mapping sudah benar
- [ ] Verify `findNextTarget()` di functions.php tidak perlu perubahan
- [ ] Verify `resolve_next_active_role()` dipanggil di `save_section.php`
- [ ] Verify `resolve_next_active_role()` dipanggil di approval action endpoints
- [ ] Test: Create pengajuan, submit sebagai analis
- [ ] Test: Verifikasi Kasubag muncul di posisi_saat_ini saat active
- [ ] Test: Set Kasubag status jadi 'cuti'
- [ ] Test: Submit pengajuan lagi, verify Direktur muncul di posisi_saat_ini

### Phase 2: Display & UI (1-2 jam)

- [ ] Update approval history query di detail.php
- [ ] Add "Pengganti" label di riwayat approval jika `is_substitute = 1`
- [ ] Show keterangan "(Pengganti Kasubag)" di setiap approval record
- [ ] Update timeline display
- [ ] Test display dengan berbagai skenario

### Phase 3: Database Schema Enhancement (1 jam)

- [ ] Tambah column `is_substitute` ke approval_kredit
- [ ] Tambah column `substitute_for` ke approval_kredit
- [ ] Create migration script
- [ ] Test migration

### Phase 4: Testing (3-4 jam)

- [ ] Test Skenario 1: Kasubag aktif → approval normal
- [ ] Test Skenario 2: Kasubag cuti → approval ke Direksi
- [ ] Test Skenario 3: Direksi cuti → approval ke Kadiv
- [ ] Test Skenario 4: Kepatuhan cuti → approval ke Kabag
- [ ] Test Skenario 5: Multiple substitutions sekaligus
- [ ] Test audit log capture
- [ ] Test notification muncul ke Direksi (jika pengganti)
- [ ] Test riwayat approval tampil dengan benar

### Phase 5: Documentation & Deployment (1-2 jam)

- [ ] Update SOP approval workflow
- [ ] Create user guide untuk status cuti
- [ ] Document fallback mapping
- [ ] Deploy ke staging
- [ ] Deploy ke production

---

## 📊 DATA SCHEMA CHANGES

### Add columns ke approval_kredit table

```sql
ALTER TABLE approval_kredit ADD COLUMN is_substitute TINYINT(1) DEFAULT 0 COMMENT 'Apakah ini approval pengganti';
ALTER TABLE approval_kredit ADD COLUMN substitute_for VARCHAR(50) NULL COMMENT 'Menggantikan role apa (e.g., kasubag_analis)';
ALTER TABLE approval_kredit ADD COLUMN substitute_reason VARCHAR(255) NULL COMMENT 'Alasan substitusi (e.g., Cuti sampai 2026-08-15)';
```

### Sample data

```
id_pengajuan | id_user | level_approval | is_substitute | substitute_for | keputusan | tanggal_approval
100          | 5       | analis         | 0             | NULL           | setuju    | 2026-07-20 10:30
100          | 10      | direktur_utama | 1             | kasubag_analis | setuju    | 2026-07-20 11:00
100          | 15      | kepatuhan      | 0             | NULL           | setuju    | 2026-07-20 11:30
```

---

## 🔍 TESTING SCENARIOS

### Scenario 1: Normal Flow (Kasubag Aktif)

**Setup:**
- Kasubag status = 'aktif'
- All other roles = 'aktif'

**Test:**
```
1. Analis submit pengajuan
2. Verify posisi_saat_ini = 'kasubag_analis'
3. Notification ke Kasubag group
4. Kasubag approve
5. Verify posisi_saat_ini = 'kepatuhan'
6. Continue normal flow
```

**Expected Result:** ✅ Normal approval ke Kasubag

---

### Scenario 2: Kasubag Substitute (Kasubag Cuti)

**Setup:**
- Kasubag status = 'cuti' atau 'nonaktif'
- Direktur status = 'aktif'

**Test:**
```
1. Analis submit pengajuan
2. resolve_next_active_role('kasubag_analis') called
3. Check: kasubag_analis user exists & aktif? NO
4. Check fallback: kasubag_analis → direktur_utama
5. Check: direktur_utama user exists & aktif? YES
6. Return 'direktur_utama'
7. Verify posisi_saat_ini = 'direktur_utama'
8. Notification ke Direktur group
```

**Expected Result:** ✅ Approval beralih ke Direktur sebagai pengganti Kasubag

---

### Scenario 3: Chain Multiple Substitutes

**Setup:**
- Kasubag status = 'cuti'
- Direktur status = 'cuti'
- Kadiv status = 'aktif'

**Test:**
```
1. resolve_next_active_role('kasubag_analis') called
2. kasubag_analis NOT active → check fallback → direktur_utama
3. direktur_utama NOT active → advance to kepatuhan in chain
4. kepatuhan (assume aktif) → return kepatuhan
5. Verify posisi_saat_ini = 'kepatuhan'
```

**Expected Result:** ✅ Skip both Kasubag & Direktur, lanjut ke Kepatuhan

---

### Scenario 4: Resume Kasubag After Cuti

**Setup:**
- Pengajuan sudah di-approve oleh Direktur (sebagai pengganti)
- Kasubag status berubah dari 'cuti' → 'aktif'
- New pengajuan datang

**Test:**
```
1. Kasubag status update to 'aktif'
2. New pengajuan submit
3. resolve_next_active_role('kasubag_analis')
4. Check: kasubag_analis user exists & aktif? YES
5. Return 'kasubag_analis'
6. Verify posisi_saat_ini = 'kasubag_analis' (kembali normal)
```

**Expected Result:** ✅ Kembali ke Kasubag setelah aktif kembali

---

## 📌 IMPORTANT NOTES

1. **Jangan ada double approval:** Saat Kasubag jadi pengganti, hanya Direktur yang bisa approve untuk tahap Kasubag (tidak ada kedua-duanya)

2. **Fallback harus jelas:** Fallback mapping di `approval_routing.php` adalah satu-satunya source of truth untuk substitusi

3. **Audit trail:** Setiap substitusi harus tercatat:
   - `is_substitute = 1`
   - `substitute_for = 'kasubag_analis'`
   - `substitute_reason = 'Status Kasubag: cuti'`

4. **Consistency:** Logic ini harus konsisten di:
   - Submit pengajuan
   - After approve
   - Tampilan riwayat
   - Dokumen cetak

5. **Status Priority:** Hierarchy for checking:
   - User status: 'aktif' > 'cuti' > 'nonaktif'
   - Role substitution: Gunakan fallback mapping di approval_routing.php

---

## 🔗 RELATED FILES

| File | Purpose | Change |
|------|---------|--------|
| `includes/approval_routing.php` | Fallback mapping | ✅ VERIFY fallback untuk Kasubag |
| `includes/functions.php` | Hierarchy & findNextTarget | ⚠️ Minor - ensure consistency |
| `analis/save_section.php` | Submit pengajuan | ✅ Already uses resolve_next_active_role |
| `*/approval_action.php` | Approval submission | ⚠️ Need to verify uses resolve_next_active_role |
| `detail.php` | Display riwayat | 🔧 Need update untuk tampil "Pengganti" |
| `Database schema` | approval_kredit table | 🔧 Need to add is_substitute, substitute_for |

---

## ✨ SUMMARY

**Current Status:** 60% implemented (already has fallback logic)

**Remaining Work:**
1. Confirm fallback mapping untuk Kasubag → Direktur
2. Verify all approval endpoints use `resolve_next_active_role()`
3. Add UI labels untuk approval pengganti
4. Add database columns untuk track substitution
5. Comprehensive testing

**Total Timeline:** 1-2 minggu dengan QA lengkap

---

**Document Created**: 26 Juli 2026  
**For**: PT. BPR Bank Wonosobo Kredit Approval System  
**Status**: Ready for Implementation
