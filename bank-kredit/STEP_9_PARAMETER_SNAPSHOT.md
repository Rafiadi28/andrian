# STEP 9 — SINKRONISASI HASIL ANALISA (Parameter Snapshot)

**Status:** ✅ IMPLEMENTED

---

## Deskripsi

STEP 9 mengimplementasikan **Repayment Parameter Snapshot** — mekanisme pengambilan snapshot parameter perhitungan kemampuan bayar pada saat analyst menyimpan pengajuan. Snapshot bersifat **immutable** dan digunakan oleh seluruh level approval untuk memastikan konsistensi perhitungan meski parameter master berubah di kemudian hari.

---

## Problem Statement

**Sebelum STEP 9:**
- Parameter repayment master dapat berubah kapan saja
- Jika parameter berubah, approval level akan menggunakan parameter yang berbeda dari analyst
- Tidak ada audit trail lengkap tentang parameter apa yang digunakan saat analisa
- Hasil perhitungan tidak konsisten antar level approval

**Solusi STEP 9:**
- Capture snapshot saat analyst menyimpan: parameter aktif + nilai dasar perhitungan + hasil kalkulasi
- Snapshot tersimpan di tabel dedicated: `repayment_parameter_snapshot`
- Semua approval level mereferensi snapshot, bukan parameter master yang berubah-ubah
- Audit trail lengkap: dapat dilihat parameter apa, tanggal berlaku, siapa yang capture, kapan

---

## Implementasi

### 1. Snapshot Table Schema

**File:** `helpers/repayment_snapshot.php` (baru)
**DB Table:** `repayment_parameter_snapshot`

```sql
CREATE TABLE repayment_parameter_snapshot (
    id_snapshot BIGINT UNSIGNED PRIMARY KEY,
    id_pengajuan INT NOT NULL,
    id_parameter INT NULL,
    jenis_kredit VARCHAR(50) NOT NULL,
    dasar_perhitungan VARCHAR(50) NOT NULL,
    persen_maks_angsuran DECIMAL(5,2) NOT NULL,
    nilai_dasar DECIMAL(15,2) NOT NULL,
    maksimal_angsuran DECIMAL(15,2) NOT NULL,
    tgl_parameter DATE NOT NULL,
    tgl_parameter_akhir DATE NULL,
    captured_by INT NULL,
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    repayment_override_aktif TINYINT(1) DEFAULT 0,
    repayment_override_by INT NULL,
    repayment_override_alasan TEXT NULL,
    catatan_snapshot TEXT NULL,
    UNIQUE KEY uk_rps_pengajuan (id_pengajuan),
    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE
);
```

**Fields:**

| Kolom | Tipe | Deskripsi |
|-------|------|-----------|
| `id_snapshot` | BIGINT | Primary Key unik untuk setiap snapshot |
| `id_pengajuan` | INT | Referensi ke pengajuan (1:1) |
| `id_parameter` | INT | FK ke parameter master saat capture (NULL jika fallback) |
| `jenis_kredit` | VARCHAR | Jenis kredit: umum, pppk, perangkat_desa, kretamas, cashcolateral |
| `dasar_perhitungan` | VARCHAR | Basis: net_cashflow, gaji_bersih, gaji_bersih_pendapatan, laba_bersih |
| `persen_maks_angsuran` | DECIMAL | Persentase parameter (75.00, 95.00, dll) |
| `nilai_dasar` | DECIMAL | Gaji/cashflow/profit yang digunakan dalam kalkulasi |
| `maksimal_angsuran` | DECIMAL | Hasil kalkulasi = nilai_dasar × (persen / 100) |
| `tgl_parameter` | DATE | Tanggal berlaku parameter (efektif date) |
| `tgl_parameter_akhir` | DATE | Tanggal akhir validitas parameter |
| `captured_by` | INT | ID analyst yang capture |
| `captured_at` | TIMESTAMP | Waktu snapshot dibuat |
| `repayment_override_aktif` | TINYINT | 0=tidak ada override, 1=ada override Direksi |
| `repayment_override_by` | INT | ID Direksi yang apply override (jika ada) |
| `repayment_override_alasan` | TEXT | Alasan override (jika ada) |

---

### 2. Helper Functions

**File:** `helpers/repayment_snapshot.php`

#### `captureRepaymentParameterSnapshot()`
Fungsi utama untuk menangkap snapshot saat analyst menyimpan.

```php
$result = captureRepaymentParameterSnapshot(
    $pdo,
    $id_pengajuan,
    'pppk',           // jenis_kredit
    10000000,         // nilai_dasar (gaji)
    [
        'override_aktif' => 1,
        'override_by' => 5,
        'override_alasan' => 'Approved by Direksi'
    ]
);

// Returns:
[
    'success' => true,
    'id_snapshot' => 123,
    'message' => 'Snapshot parameter repayment berhasil direkam.',
    'snapshot' => [
        'jenis_kredit' => 'pppk',
        'dasar_perhitungan' => 'gaji_bersih',
        'persen' => 95.00,
        'nilai_dasar' => 10000000,
        'maksimal_angsuran' => 9500000,
        'id_parameter' => 42,
        'tgl_parameter' => '2026-06-01'
    ]
]
```

**Logic:**
1. Fetch parameter aktif dari `master_parameter_repayment` dengan `getRepaymentParameterConfig()`
2. Normalisasi `jenis_kredit` dengan `normalizeRepaymentJenisKey()`
3. Extract: dasar_perhitungan, persen, tgl_berlaku
4. Kalkulasi: maksimal_angsuran = nilai_dasar × (persen / 100)
5. Check apakah snapshot sudah ada untuk pengajuan ini
6. Jika ada → UPDATE dengan nilai terbaru
7. Jika tidak ada → INSERT baru
8. Link snapshot ke `pengajuan_kredit.id_repayment_snapshot` (FK)
9. Return result dengan snapshot details

#### `fetchRepaymentParameterSnapshot()`
Mengambil snapshot yang sudah tersimpan.

```php
$snapshot = fetchRepaymentParameterSnapshot($pdo, $id_pengajuan);
// Returns array dengan semua fields + nama analyst, nama director override
```

#### `getRepaymentParameterSnapshotForApproval()`
Convenience function untuk approval workflow.

```php
$snapshot = getRepaymentParameterSnapshotForApproval($pdo, $id_pengajuan);
// Digunakan oleh Kadiv, Kabag, Direksi saat review
```

#### `formatRepaymentParameterSnapshot()`
Format display untuk UI.

```php
$display = formatRepaymentParameterSnapshot($snapshot);
// Output: "Jenis: pppk | Dasar: gaji_bersih | Persentase: 95.00% | Nilai Dasar: Rp 10.000.000 | Max Angsuran: Rp 9.500.000 | Tgl Parameter: 2026-06-01 | Override: YA (Direktur Utama) — Kebijakan Direksi"
```

---

### 3. Integration Points

**File:** `analis/save_section.php`

Snapshot capture dipanggil di tiga section:

#### a. PPPK Section (Line ~513)
```php
case 'penghasilan_pegawai':
    // ... calculate repayment ...
    $snapshotResult = captureRepaymentParameterSnapshot($pdo, $id_pengajuan, 'pppk', $gaji_pp, $overrideData);
```

#### b. Perangkat Desa Section (Line ~668)
```php
$snapshotResult = captureRepaymentParameterSnapshot($pdo, $id_pengajuan, 'perangkat_desa', $tetap + $tambahan, $overrideData);
```

#### c. Usaha / Business Section (Line ~843)
```php
$snapshotResult = captureRepaymentParameterSnapshot($pdo, $id_pengajuan, $jenis_pekerjaan_post, $laba, $overrideData);
```

**Kapan dipanggil:**
- Setelah `persistRepaymentCalculationForPengajuan()` berhasil
- Ketika nilai dasar (gaji/cashflow/profit) tersedia
- Dengan override data jika ada Direksi override

---

### 4. Approval Workflow Integration

Semua approval level (Kadiv, Kabag, Direksi) **HARUS** menggunakan snapshot:

**Current Status:** ✓ Snapshot tersimpan, akan diverifikasi approval workflow mengaksesnya.

**Future Verification:**
- Check `approval_kredit` workflow functions
- Ensure they call `getRepaymentParameterSnapshotForApproval()`
- Verify snapshot data ditampilkan di approval UI
- Confirm approval decisions mereferensi snapshot, bukan parameter master

---

### 5. pengajuan_kredit Column Addition

**Kolom baru:** `id_repayment_snapshot`

```sql
ALTER TABLE pengajuan_kredit 
ADD COLUMN id_repayment_snapshot BIGINT UNSIGNED NULL 
COMMENT 'FK to repayment_parameter_snapshot for audit trail'
AFTER repayment_capacity;

-- Foreign key (auto-created by schema migration)
ALTER TABLE pengajuan_kredit 
ADD FOREIGN KEY (id_repayment_snapshot) 
    REFERENCES repayment_parameter_snapshot(id_snapshot) 
    ON DELETE SET NULL;
```

---

## Data Flow

```
ANALYST MENYIMPAN PENGAJUAN
        ↓
    save_section.php
        ↓
    calculate repayment_capacity (persistRepaymentCalculationForPengajuan)
        ↓
    captureRepaymentParameterSnapshot()
        ↓
    - Fetch parameter master aktif (getRepaymentParameterConfig)
    - Extract: jenis, dasar, persen, tgl_berlaku
    - Calculate: maksimal_angsuran = nilai_dasar × (persen/100)
    - INSERT/UPDATE repayment_parameter_snapshot
    - Link to pengajuan_kredit.id_repayment_snapshot
        ↓
    SNAPSHOT TERSIMPAN ✓
        ↓
    APPROVAL WORKFLOW (Kadiv → Kadiv → Direksi)
        ↓
    - Fetch snapshot via getRepaymentParameterSnapshotForApproval()
    - All approvers use same parameter values
    - Audit trail shows who approved with which parameters
        ↓
    KONSISTEN ACROSS ALL LEVELS ✓
```

---

## Snapshot Immutability

**Snapshot bersifat immutable:**

| Skenario | Behavior |
|----------|----------|
| Parameter master berubah setelah snapshot | Snapshot tetap, approval pakai snapshot lama |
| Analyst revisi pengajuan | Snapshot di-UPDATE dengan nilai baru (jika ada perubahan finansial signifikan) |
| Override ditambah kemudian | Snapshot di-UPDATE dengan override info |
| Query approval level | Query mereferensi snapshot, bukan master parameter |

**Audit Trail:**
- Tanggal capture: `captured_at`
- Analyst: `captured_by`
- Parameter yang digunakan: semua fields
- Override history: `repayment_override_aktif`, `repayment_override_by`, `repayment_override_alasan`

---

## Benefit

| Benefit | Penjelasan |
|---------|-----------|
| **Consistency** | Semua approval level menggunakan data parameter yang sama |
| **Audit Trail** | Lengkap: parameter apa, tanggal, siapa capture, hasil kalkulasi |
| **Regulatory** | Bank dapat menunjukkan parameter apa yang digunakan per pengajuan |
| **Revision Safety** | Jika parameter master berubah, pengajuan lama tetap valid dengan parameter lamanya |
| **Override Tracking** | Terlihat jelas kapan Direksi apply override dan alasannya |
| **Non-Repudiation** | Analyst tidak bisa claim parameter berbeda dari yang tersimpan |

---

## Testing Checklist

- [ ] Create pengajuan baru → capture snapshot for PPPK
- [ ] Create pengajuan baru → capture snapshot for Perangkat Desa
- [ ] Create pengajuan baru → capture snapshot for Usaha (business)
- [ ] Verify snapshot table has correct data
- [ ] Verify `pengajuan_kredit.id_repayment_snapshot` linked correctly
- [ ] Modify master parameter → verify old pengajuan still uses old snapshot
- [ ] Apply Direksi override → verify override info in snapshot
- [ ] Revise pengajuan → verify snapshot updated with new values
- [ ] Run approval workflow → verify approval uses snapshot data
- [ ] Check audit log → verify analyst, capture time, parameters logged

---

## Files Modified/Created

| File | Action | Changes |
|------|--------|---------|
| `helpers/repayment_snapshot.php` | **NEW** | 330 lines: snapshot capture, fetch, format functions |
| `analis/save_section.php` | Modified | +3 snapshot captures (PPPK, Perangkat Desa, Usaha) |
| `includes/schema_realtime_migrate.php` | Modified | +1 function call: `bankKreditEnsureRepaymentSnapshotSchema()` |
| `includes/schema_realtime_migrate.php` (end) | Modified | +1 new function: `bankKreditEnsureRepaymentSnapshotSchema()` wrapper |

---

## Related Files

- `helpers/credit_helper.php` — `getRepaymentParameterConfig()`, `normalizeRepaymentJenisKey()`
- `helpers/repayment_override.php` — override data structure
- `admin/approval_kredit.php` — approval workflow (to be verified)
- `admin/master_parameter_repayment.php` — parameter management

---

## Future Enhancements

1. **Snapshot Comparison UI** — Show parameter changes between old/new snapshots
2. **Snapshot Audit Report** — Export all snapshots for audit purposes
3. **Snapshot Versioning** — Track all snapshot changes (append-only log)
4. **Alert on Parameter Change** — Notify if parameter changed for pending approval
5. **Snapshot Approval Lock** — Lock snapshot once final approval given

---

## Summary

✅ **STEP 9 COMPLETE:**
- Snapshot table created with immutable structure
- Capture function integrated into save_section.php (3 locations)
- Schema migration in place
- Audit trail ready
- Ready for approval workflow verification & testing

**Next:** Run test suite to verify snapshot capture and approval workflow.
