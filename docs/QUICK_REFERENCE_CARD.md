# 🚀 QUICK REFERENCE CARD - FORM PPPK IMPLEMENTATION

**Print this page and stick it on your monitor while implementing!** 📌

---

## 📋 5-MINUTE QUICK START

```bash
# 1. BACKUP
cp analis/partials/tab_penghasilan_pppk.inc.php \
   analis/partials/tab_penghasilan_pppk.inc.php.backup

# 2. DATABASE
mysql -u root -p bank_kredit < docs/migration_pppk_improved_2026-04-30.sql

# 3. FOLDER
mkdir -p assets/uploads/sk_files && chmod 755 assets/uploads/sk_files

# 4. FORM FILE
cp analis/partials/tab_penghasilan_pppk_improved.inc.php \
   analis/partials/tab_penghasilan_pppk.inc.php

# 5. BACKEND
# Edit save_section.php - add helper functions & PPPK logic
# (See SAVE_SECTION_IMPLEMENTATION_EXAMPLE.php)

# 6. TEST
# Open form-pppk-demo.html in browser
```

---

## 🔑 KEY VARIABLES & NAMING

### Form Field Names (HTML)
```html
<!-- Kontrak -->
pppk_no_sk              → "SK/HRM/2024/001"
pppk_tgl_awal           → "2024-01-15"
pppk_tgl_akhir          → "2026-12-31"
pppk_sisa_kerja_bulan   → 35 (hidden, auto-calc)

<!-- Agunan -->
pppk_agunan_no_sk       → "SK/AGUNAN/2024/001"
pppk_file_sk            → file object

<!-- Angsuran (arrays) -->
pppk_angsuran_nama[]    → ["KREDIT KONSUMTIF", "KMK"]
pppk_angsuran_nominal[] → [500000, 750000]
pppk_total_angsuran     → 1250000
```

### Database Columns
```sql
-- Main table
pppk_tgl_awal
pppk_tgl_akhir
pppk_sisa_kerja_bulan
pppk_agunan_no_sk
pppk_file_sk
pppk_total_angsuran

-- Detail table
pppk_angsuran_detail (
    id, id_pengajuan, nama_produk, nominal_angsuran
)
```

### JavaScript Functions
```javascript
calculateSisaMasaKerja()      // Auto-calc on date change
validateField(id, type)        // Client validation
pppkAddAngsuran()             // Add row
pppkRemoveAngsuran(index)     // Remove row
pppkUpdateTotalAngsuran()     // Update sum
```

---

## 📝 CODE SNIPPETS

### A. Calculate Sisa Kerja
```javascript
const start = new Date(tglAwal + 'T00:00:00');
const end = new Date(tglAkhir + 'T00:00:00');
const diffDays = Math.floor((end - start) / (1000 * 60 * 60 * 24));
const bulan = Math.floor(diffDays / 30);
const tahun = Math.floor(bulan / 12);
const sisaBulan = bulan % 12;
```

### B. Validate File Upload
```javascript
const MAX_SIZE = 2 * 1024 * 1024; // 2MB
const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];
const ext = file.name.split('.').pop().toLowerCase();

if (file.size > MAX_SIZE) throw "File too large";
if (!ALLOWED_EXT.includes(ext)) throw "Invalid format";
```

### C. Calculate Total Angsuran
```javascript
let total = 0;
document.querySelectorAll('.pppk-angsuran-nominal').forEach(input => {
    total += parseFloat(input.value) || 0;
});
document.getElementById('pppk_total_angsuran').value = total;
```

### D. Backend Date Validation (PHP)
```php
function validateDateFormat($value) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        throw new Exception("Invalid format");
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new Exception("Invalid date");
    }
    return $value;
}
```

### E. File Upload Handler (PHP)
```php
$file = $_FILES['pppk_file_sk'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = ['application/pdf', 'image/jpeg', 'image/png'];
if (!in_array($mime, $allowed)) throw new Exception("Invalid MIME");

$upload_dir = __DIR__ . '/../assets/uploads/sk_files/';
if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
    throw new Exception("Upload failed");
}
```

---

## ⚠️ COMMON PITFALLS

| Issue | Fix |
|-------|-----|
| Folder permission denied | `chmod 755 assets/uploads/sk_files` |
| Date calculation wrong | Use UTC dates, no timezone conversion |
| File upload empty | Check max_file_upload in php.ini |
| Total not updating | Verify class selector `.pppk-angsuran-nominal` |
| MIME type rejected | Use `finfo_open()` not just $_FILES['type'] |
| DB error on save | Run migration first! |
| Old form still shows | Clear browser cache (Ctrl+Shift+Del) |
| Angsuran not saved | Check FK constraint on table |

---

## ✅ TESTING CHECKLIST

### Unit Test - Single Fields
```
☐ Date awal: Try past, future, invalid format
☐ Date akhir: Try before awal (should error)
☐ File upload: Try 3MB (reject), 1.5MB (accept)
☐ File format: Try .doc, .xlsx, .pdf (only pdf/jpg/png accept)
☐ Angsuran nominal: Try -100 (reject), 500000 (accept)
```

### Integration Test - Full Flow
```
☐ Fill all fields with valid data
☐ Add 3 angsuran entries
☐ Upload file
☐ Click save
☐ Check response success
☐ Refresh page, data still there
☐ Edit form, values prefilled
```

### Database Test
```sql
-- Check new columns
DESC pengajuan_kredit LIKE 'pppk%';

-- Check detail table
SELECT * FROM pppk_angsuran_detail LIMIT 5;

-- Verify FK
SELECT * FROM pppk_angsuran_detail 
WHERE id_pengajuan NOT IN (SELECT id_pengajuan FROM pengajuan_kredit);
```

### File System Test
```bash
ls -la assets/uploads/sk_files/
# Should have: sk_123_1651234567_a1b2c3d4.pdf etc

du -sh assets/uploads/sk_files/
# Size should be reasonable (~2MB max per file)

find assets/uploads/sk_files -type f -exec file {} \;
# Verify actual file types, not just extension
```

---

## 🔍 DEBUG COMMANDS

### JavaScript Console
```javascript
// Check if PPPK logic loaded
typeof pppkAddAngsuran  // Should be "function"

// Check DOM elements
document.getElementById('pppk_tgl_awal')  // Should exist
document.querySelectorAll('.pppk-angsuran-item').length  // Should > 0

// Check values
document.getElementById('pppk_total_angsuran').value  // Should be number

// Simulate calculation
calculateSisaMasaKerja();
console.log(document.getElementById('pppk_sisa_kerja_display').textContent);
```

### PHP Debugging
```php
// In save_section.php
error_log('DEBUG: pppk_tgl_awal = ' . $_POST['pppk_tgl_awal']);
error_log('DEBUG: pppk_angsuran_nominal = ' . json_encode($_POST['pppk_angsuran_nominal']));

// Check if file received
error_log('DEBUG: File size = ' . $_FILES['pppk_file_sk']['size']);
error_log('DEBUG: File error = ' . $_FILES['pppk_file_sk']['error']);
```

### MySQL Debugging
```sql
-- Check insert
SELECT * FROM pengajuan_kredit WHERE pppk_tgl_awal IS NOT NULL LIMIT 1;

-- Check angsuran
SELECT * FROM pppk_angsuran_detail ORDER BY id DESC LIMIT 10;

-- Count by type
SELECT jenis_pekerjaan, COUNT(*) FROM pengajuan_kredit 
GROUP BY jenis_pekerjaan;
```

---

## 📊 DATA VALIDATION RULES

| Field | Type | Required | Length | Format |
|-------|------|----------|--------|--------|
| pppk_no_sk | text | YES | max 100 | Uppercase |
| pppk_tgl_awal | date | YES | - | YYYY-MM-DD |
| pppk_tgl_akhir | date | YES | - | YYYY-MM-DD, >= start |
| pppk_agunan_no_sk | text | YES | max 100 | Uppercase |
| pppk_file_sk | file | YES | max 2MB | PDF/JPG/PNG |
| angsuran_nama | text | YES* | max 100 | *if nominal > 0 |
| angsuran_nominal | number | YES* | - | >= 0, *min 1 entry |

---

## 🎨 CSS CLASSES REFERENCE

```css
/* Main container */
.pppk-penghasilan-container

/* Form structure */
.pppk-form-grid
.pppk-grid-2
.pppk-form-group
.pppk-label
.pppk-required

/* Inputs */
.pppk-input
.pppk-error
.pppk-display-box
.pppk-helper

/* File upload */
.pppk-file-upload-wrapper
.pppk-file-input
.pppk-file-label
.pppk-file-preview

/* Angsuran */
.pppk-angsuran-container
.pppk-angsuran-item
.pppk-angsuran-header
.pppk-angsuran-item-delete
.pppk-angsuran-content

/* Total */
.pppk-total-box
.pppk-total-row
.pppk-total-label
.pppk-total-value

/* Buttons */
.pppk-btn
.pppk-btn-primary
.pppk-btn-save
.pppk-btn-danger
```

---

## 🚨 ERROR MESSAGES

### Client-Side
```
"Tanggal wajib diisi"
"Tanggal akhir harus >= tanggal awal"
"Format harus YYYY-MM-DD"
"Ukuran file maksimal 2MB"
"Format tidak didukung"
"Nama produk wajib diisi"
"Minimal 1 angsuran diperlukan"
```

### Server-Side
```
"Tanggal awal perjanjian PPPK wajib diisi"
"Format file tidak didukung: application/msword"
"Ukuran file terlalu besar: 2.5MB"
"Nominal angsuran #2 tidak boleh negatif"
"Gagal membuat folder upload"
"Gagal menyimpan file"
```

---

## 📞 WHEN STUCK

1. **Check demo:** Open `form-pppk-demo.html` - does it work there?
2. **Check logs:** `tail -f logs/error_*.log`
3. **Check console:** F12 → Console tab → Any red errors?
4. **Check network:** F12 → Network tab → Request success?
5. **Check database:** `SELECT * FROM pengajuan_kredit WHERE id_pengajuan = 123;`
6. **Check filesystem:** `ls -la assets/uploads/sk_files/`
7. **Check docs:** Read DOKUMENTASI_FORM_PPPK_IMPROVED.md troubleshooting section

---

## 📦 FILE STRUCTURE AFTER IMPLEMENTATION

```
bank-kredit/
├── analis/
│   ├── partials/
│   │   └── tab_penghasilan_pppk.inc.php (UPDATED)
│   └── save_section.php (UPDATED)
│
├── assets/
│   └── uploads/
│       └── sk_files/ (NEW - with permissions 755)
│
└── docs/
    ├── migration_pppk_improved_2026-04-30.sql (EXECUTED)
    ├── DOKUMENTASI_FORM_PPPK_IMPROVED.md
    ├── BACKEND_IMPLEMENTATION_GUIDE.php
    └── SAVE_SECTION_IMPLEMENTATION_EXAMPLE.php
```

---

## ✨ FINAL VERIFICATION

After implementation, verify with:

```bash
# 1. Check files exist
[ -f analis/partials/tab_penghasilan_pppk.inc.php ] && echo "✓ Form file" || echo "✗ Missing"
[ -d assets/uploads/sk_files ] && echo "✓ Upload folder" || echo "✗ Missing"

# 2. Check database
mysql -e "DESC pengajuan_kredit LIKE 'pppk%';" | grep -q "pppk_tgl_awal" && echo "✓ DB columns" || echo "✗ Missing"
mysql -e "DESC pppk_angsuran_detail;" | grep -q "id" && echo "✓ Detail table" || echo "✗ Missing"

# 3. Check permissions
[ -w assets/uploads/sk_files ] && echo "✓ Write permission" || echo "✗ No write access"

# 4. Check form (open in browser)
# - Fill form
# - Submit
# - Check console for errors
# - Verify data in database
```

---

## 🎯 SUCCESS INDICATORS

✅ Form loads without JavaScript errors  
✅ Date calculation works in real-time  
✅ File upload shows preview  
✅ Can add/remove angsuran entries  
✅ Total updates automatically  
✅ Form saves without error  
✅ Data appears in database  
✅ File saved in upload folder  
✅ Can edit form and data pre-fills  

---

**Laminated version size:** 8.5" x 11" (A4)  
**Print:** Color, landscape, fit to page  
**Location:** Stick on monitor or desk  

🎉 **Good luck! You got this!** 🚀
