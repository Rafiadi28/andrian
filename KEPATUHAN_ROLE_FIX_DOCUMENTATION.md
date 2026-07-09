# Dokumentasi: Kepatuhan Role Data Access Fix

**Date:** 2024
**Status:** Implementation Complete
**Task:** Fix role kepatuhan to access complete final analis data

---

## 🎯 Tujuan
Memastikan bahwa role **kepatuhan** (compliance officer) dapat mengakses dan menampilkan data final dari analis, khususnya:
- Analisa 5C / 6C (scoring dan rekomendasi)
- Analisa Agunan (jaminan/collateral details dengan valuasi)
- Repayment Capacity (kapasitas pembayaran)
- Kesimpulan Analis (conclusion dari analisa 5C)

---

## 📋 Implementasi

### 1. Helper Functions (credit_helper.php)

**File:** `helpers/credit_helper.php`

#### Fungsi Baru: `fetch_data_analis_untuk_kepatuhan()`
```php
/**
 * Fetch semua data analis untuk compliance review
 * 
 * @param PDO $pdo Database connection
 * @param int $id_pengajuan ID pengajuan kredit
 * @return array Data dengan keys: pengajuan, analisa_5c, agunan_detail, repayment, status
 */
function fetch_data_analis_untuk_kepatuhan(PDO $pdo, $id_pengajuan)
```

**Data yang di-fetch:**
1. **pengajuan** - Data dasar dari `pengajuan_kredit` table
2. **analisa_5c** - Scoring 5C dari `analisa_5c` table
3. **agunan_detail** - Array nested dengan 5 sub-keys:
   - `tanah[]` - Data dari `jaminan_tanah_bangunan` dengan valuasi (pasar, taksasi, likuidasi)
   - `kendaraan[]` - Data dari `jaminan_kendaraan` dengan valuasi & STNK
   - `emas[]` - Data dari `jaminan_emas` dengan berat & harga per gram
   - `cashcolateral[]` - Data dari `jaminan_cashcolateral`
   - `foto_agunan[]` - File foto dari `agunan_foto` table
4. **repayment** - Calculated object dengan:
   - `omzet_bulanan` - Revenue bulanan dari pengajuan_kredit.omset_per_bulan
   - `pengeluaran` - Expense dari pengajuan_kredit.total_biaya_bulanan
   - `angsuran_lain` - Existing loan payments
   - `repayment_capacity` - 75% dari net cashflow
   - `angsuran_diajukan` - Proposed loan payment
   - `margin_keamanan` - Repayment capacity minus loan payment
   - `status_kelayakan_repayment` - 'LAYAK' or 'TIDAK LAYAK'
5. **status** - Summary object dengan:
   - `skor_5c_total` - Total 5C score (0-5)
   - `rekomendasi_5c` - Recommendation text
   - `status_kelayakan_5c` - Status object (dari tentukan_status_kelayakan)
   - `ada_analisa_5c` - Boolean flag
   - `ada_agunan` - Boolean flag
   - `kesimpulan_5c` - Conclusion text dari analisa_5c.catatan_5c

#### Fungsi Baru: `validate_data_analis_untuk_kepatuhan()`
```php
/**
 * Validate data kepatuhan review
 * Pastikan semua required data ada sebelum compliance assessment dimulai
 * 
 * @param PDO $pdo Database connection
 * @param int $id_pengajuan ID pengajuan kredit
 * @return array Validation result dengan keys: valid, missing, warnings
 */
function validate_data_analis_untuk_kepatuhan(PDO $pdo, $id_pengajuan)
```

**Validasi yang dilakukan:**
- ✅ Pengajuan exists
- ✅ Analisa 5C completed (required)
- ⚠️ Ada agunan recorded (warning jika tidak)
- ⚠️ Repayment capacity calculated (warning jika <= 0)
- ⚠️ Kesimpulan filled (warning jika kosong)

---

### 2. Compliance Assessment Form Update (compliance_assessment.php)

**File:** `analis/compliance_assessment.php`

#### Perubahan:
1. **Data Validation Display** - Sebelum form compliance dimunculkan:
   - Jika data analis tidak lengkap: Tampilkan alert merah dengan daftar data yang hilang
   - User tidak bisa melanjutkan assessment sampai data analis siap

2. **Data Review Section** - Sebelum form compliance (untuk kepatuhan review):
   - Box biru dengan data final analis:
     - **Analisa 5C**: Skor, Status Kelayakan (✅ LAYAK / ⚠️ LAYAK DENGAN CATATAN / ❌ TIDAK LAYAK)
     - **Agunan**: Summary dari semua tipe agunan (tanah, kendaraan, emas, cash, foto)
     - **Repayment Capacity**: Omzet, Pengeluaran, Capacity, Angsuran, Status
   - Semua data readonly agar kepatuhan tahu data mana yang dari analis

#### Flow:
```
1. User buka compliance_assessment.php?id=XXX
2. System fetch data_analis via fetch_data_analis_untuk_kepatuhan()
3. System validate via validate_data_analis_untuk_kepatuhan()
4. Jika ada missing data: Show alert merah, block form
5. Jika data lengkap: Show blue review box dengan data final analis
6. Kepatuhan bisa proceed dengan form assessment (compliance checklist, fasilitas existing, dll)
```

#### Display Format:
```
┌─────────────────────────────────────────┐
│ ⚠️ DATA ANALIS TIDAK LENGKAP             │ (jika ada yang missing)
│ - Analisa 5C belum dikerjakan           │
│ - Agunan tidak ada                      │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ 📋 DATA FINAL ANALIS (Review)           │ (always shown)
│                                         │
│ Analisa 5C:                             │
│ Skor 4.20/5 | ✅ LAYAK | DISETUJUI     │
│                                         │
│ Agunan:                                 │
│ Tanah: 2 | Kendaraan: 1 | Emas: 1     │
│                                         │
│ Repayment Capacity:                     │
│ Rp 25,000,000 | Angsuran: Rp 5,000,000│
│ | Status: ✅ LAYAK                      │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ PENILAIAN KEPATUHAN (COMPLIANCE)        │
│                                         │
│ 1. Data Usulan Kredit (readonly)        │
│ 2. Compliance Checklist (input)         │
│ 3. Fasilitas Kredit Existing (input)    │
│ ... (rest of assessment form)           │
└─────────────────────────────────────────┘
```

---

## 📊 Database Relations

### JOINs yang digunakan di `fetch_data_analis_untuk_kepatuhan()`:

```sql
-- Main query
SELECT * FROM pengajuan_kredit WHERE id_pengajuan = ?

-- Related queries (INNER/LEFT JOIN pattern)
SELECT * FROM analisa_5c WHERE id_pengajuan = ?

SELECT * FROM jaminan_tanah_bangunan WHERE id_pengajuan = ?
SELECT * FROM jaminan_kendaraan WHERE id_pengajuan = ?
SELECT * FROM jaminan_emas WHERE id_pengajuan = ?
SELECT * FROM jaminan_cashcolateral WHERE id_pengajuan = ?

SELECT * FROM agunan_foto WHERE id_pengajuan = ?
```

### Foreign Key Constraints (Ensured in schema_realtime_migrate.php):
- `analisa_5c.id_pengajuan` → `pengajuan_kredit.id_pengajuan`
- `assessment_kepatuhan.id_pengajuan` → `pengajuan_kredit.id_pengajuan`
- `assessment_kepatuhan.id_user` → `users.id_user`
- All `jaminan_*` tables → `pengajuan_kredit.id_pengajuan`
- `agunan_foto.id_pengajuan` → `pengajuan_kredit.id_pengajuan`

---

## ✅ Testing Checklist

- [x] Helper functions added to credit_helper.php
- [x] compliance_assessment.php updated to display data analis
- [x] PHP syntax validation passed
- [ ] Test data fetch with actual pengajuan that has complete analis data
- [ ] Test data fetch with pengajuan missing analis data
- [ ] Test validation logic (missing data, warnings)
- [ ] Test kepatuhan role can view all agunan types
- [ ] Test repayment capacity display format
- [ ] Test 5C scoring display and status colors
- [ ] Test form submission doesn't break with new fields
- [ ] Test role permissions (kepatuhan can access compliance_assessment.php)

---

## 🔄 Data Flow (After Fix)

```
ANALIS WORKFLOW:
1. Analis buka form_umum.php
2. Isi 7 tab form (pemohon, usaha, struktur, agunan, neraca, 6c, scoring)
3. Save semua section → data tersimpan di:
   - pengajuan_kredit (main data + repayment_capacity)
   - jaminan_tanah_bangunan / jaminan_kendaraan / jaminan_emas / jaminan_cashcolateral
   - agunan_foto
   - analisa_5c (6C scores + total_score + rekomendasi + catatan_5c)
4. Status berubah ke "Siap Kepatuhan"

KEPATUHAN WORKFLOW (NEW):
1. Kepatuhan buka compliance_assessment.php?id=XXX
2. System otomatis fetch data analis via fetch_data_analis_untuk_kepatuhan()
3. Kepatuhan melihat blue review box dengan:
   - Skor 5C, Status Kelayakan, Rekomendasi
   - Detail agunan (tanah, kendaraan, emas, cash, foto)
   - Repayment capacity (capacity vs. angsuran diajukan)
4. Kepatuhan proceed dengan compliance assessment:
   - Isi checklist compliance
   - Review fasilitas existing
   - Buat kesimpulan dan rekomendasi kepatuhan
   - Save ke assessment_kepatuhan table
5. Status berubah ke "Selesai Kepatuhan" / "Siap Komite" / dll
```

---

## 🐛 Potential Issues & Mitigation

### Issue 1: Missing Analisa 5C
**Problem:** Kepatuhan buka form tapi analis belum selesai analisa 5C
**Solution:** Validation menampilkan alert merah, block form dari input
**Implementation:** Done via `validate_data_analis_untuk_kepatuhan()`

### Issue 2: Multiple Agunan of Same Type
**Problem:** Tanah bangunan ada 3, data bisa duplicate atau hilang
**Solution:** Query menggunakan `ORDER BY id_jaminan` dan return array semua records
**Testing:** Verify dengan pengajuan yang punya 3+ tanah, 2+ kendaraan, dll

### Issue 3: Null/Empty Repayment Capacity
**Problem:** pengajuan_kredit.repayment_capacity belum dihitung
**Solution:** Helper calculate 75% dari (omset - pengeluaran - angsuran_lain)
**Implementation:** Done in repayment object construction

### Issue 4: Data Mismatch Between Analis & Kepatuhan
**Problem:** Analis ubah data, kepatuhan lihat data lama
**Solution:** Fetch always terbaru (no caching), direct dari DB
**Implementation:** Setiap buka compliance_assessment.php selalu fetch terbaru

---

## 📝 Code Examples

### Using Helper in compliance_assessment.php:
```php
<?php
// At top of output section (after role check, before form render)
$data_analis = fetch_data_analis_untuk_kepatuhan($pdo, $id);
$validasi = validate_data_analis_untuk_kepatuhan($pdo, $id);

// Check if data complete
if (!$validasi['valid']) {
    // Show error alert (done)
    echo "Data analis tidak lengkap";
    // Do not render assessment form
    return;
}

// Render review box (done)
if ($data_analis) {
    echo "Blue box with data analis";
}

// Render compliance assessment form (existing code)
?>
```

### Using fetch_data_analis_untuk_kepatuhan():
```php
$data = fetch_data_analis_untuk_kepatuhan($pdo, $id);

// Access nested structure
$score_5c = $data['status']['skor_5c_total'];
$status_label = $data['status']['status_kelayakan_5c']['label'];
$repayment = $data['repayment']['repayment_capacity'];
$agunan_tanah = count($data['agunan_detail']['tanah']);
$kesimpulan = $data['status']['kesimpulan_5c'];
```

---

## 🔗 Related Files Updated

| File | Changes | Status |
|------|---------|--------|
| `helpers/credit_helper.php` | + 2 new functions | ✅ Done |
| `analis/compliance_assessment.php` | + Data validation display + Data review section | ✅ Done |
| `helpers/functions.php` | No change needed | - |
| `includes/schema_realtime_migrate.php` | No change needed (FKs already present) | - |

---

## 📚 Next Steps

### Immediate (Testing):
1. Test with pengajuan that has complete analis data
2. Test with pengajuan missing analis data
3. Verify all agunan types display correctly
4. Test form submission works with new display

### Short Term (Enhancement):
1. Add link/button from detail.php to compliance_assessment.php
2. Add data change tracking (if analis modify, kepatuhan sees latest)
3. Add audit log for who reviewed what data when

### Long Term (Workflow):
1. Implement full approval workflow (komite approval after kepatuhan)
2. Add notification when data changes
3. Add data export/print with compliance assessment included

---

## 📞 Troubleshooting

**Q: Kepatuhan buka compliance_assessment.php tapi data kosong?**
A: Check `pengajuan_kredit.id_pengajuan` exists dan `analisa_5c` record sudah dibuat

**Q: Agunan tidak muncul di review box?**
A: Verify analis sudah save agunan data di form_umum tab "Agunan"

**Q: "Data analis tidak lengkap" alert terus muncul?**
A: Check analis sudah complete tab 6C (scoring) sampai hasilnya di analisa_5c table

**Q: Repayment capacity showing negative?**
A: Check pengajuan_kredit fields: omset_per_bulan, total_biaya_bulanan, angsuran_bank_lain

---

**Created:** 2024
**Last Updated:** 2024
**Version:** 1.0
