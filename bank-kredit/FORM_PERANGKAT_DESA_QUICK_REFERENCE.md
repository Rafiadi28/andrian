# 🚀 QUICK REFERENCE GUIDE - FORM PERANGKAT DESA

**Panduan Cepat Penggunaan & Implementasi**

---

## 📋 QUICK START (5 MENIT)

### Untuk Pengguna Form
```
1. Buka: form-desa-improved.html
2. Isi: Section 1-4 (sesuai instruksi)
3. Validasi: Error akan highlight otomatis
4. Simpan: Klik tombol "Simpan Data"
5. Done! ✓
```

### Untuk Developer
```
1. Copy: form-desa-improved.html ke server
2. Setup: Backend endpoint /api/perangkat-desa/save
3. Test: Open form, submit, check database
4. Deploy: Go live!
```

---

## 🎯 KEY FEATURES AT A GLANCE

| Fitur | Cara Kerja | Hasil |
|-------|-----------|-------|
| **Sisa Masa Kerja** | Input tgl mulai & akhir → Auto calculate | Misal: "5 tahun 11 bulan" |
| **File Upload** | Drag/drop atau pilih PDF/JPG/PNG ≤2MB | Preview muncul, validasi auto |
| **Angsuran Dinamis** | Click "+ Tambah" → Input nama & nominal | Total auto sum |
| **Validasi** | Saat blur/change & saat submit | Error highlight + message |
| **Responsive** | Auto adjust ke screen size | Mobile-friendly |

---

## 🔑 CRITICAL VALIDATIONS

```javascript
Jangan lewatkan:
- Tanggal AKHIR harus ≥ tanggal MULAI (atau error!)
- Minimal 1 ANGSURAN harus ada
- File SK harus DIUPLOAD (required)
- Penghasilan TETAP harus > 0 (required)
- Semua field dengan * WAJIB diisi
```

---

## 📊 FIELD REFERENCE TABLE

### Section 1: Data Pekerjaan
```
┌─────────────────────┬──────────┬──────────────┬─────────────────────┐
│ Field               │ Type     │ Required     │ Rules               │
├─────────────────────┼──────────┼──────────────┼─────────────────────┤
│ Jabatan             │ Text     │ YES (*)      │ Min 3, Max 100 char │
│ Nomor SK            │ Text     │ YES (*)      │ Min 3, Max 50 char  │
│ Tanggal Mulai       │ Date     │ YES (*)      │ YYYY-MM-DD          │
│ Tanggal Akhir       │ Date     │ YES (*)      │ YYYY-MM-DD, ≥ mulai │
│ Sisa Masa Kerja     │ Display  │ NO           │ Auto calculated     │
└─────────────────────┴──────────┴──────────────┴─────────────────────┘
```

### Section 2: Agunan
```
┌─────────────────────┬──────────┬──────────────┬──────────────────────┐
│ Field               │ Type     │ Required     │ Rules                │
├─────────────────────┼──────────┼──────────────┼──────────────────────┤
│ Nomor SK Agunan     │ Text     │ YES (*)      │ Min 3, Max 50 char   │
│ File SK             │ File     │ YES (*)      │ PDF/JPG/PNG, ≤2MB    │
└─────────────────────┴──────────┴──────────────┴──────────────────────┘
```

### Section 3: Penghasilan
```
┌──────────────────────┬──────────┬──────────────┬──────────────┐
│ Field                │ Type     │ Required     │ Rules        │
├──────────────────────┼──────────┼──────────────┼──────────────┤
│ Penghasilan Tetap    │ Number   │ YES (*)      │ Min 0        │
│ Tambahan Penghasilan │ Number   │ NO           │ Min 0        │
│ Biaya Hidup          │ Number   │ NO           │ Min 0        │
└──────────────────────┴──────────┴──────────────┴──────────────┘
```

### Section 4: Angsuran (Dynamic)
```
┌────────────────────┬──────────┬──────────────┬─────────────────┐
│ Field              │ Type     │ Required     │ Rules           │
├────────────────────┼──────────┼──────────────┼─────────────────┤
│ Jenis Kredit       │ Text     │ YES (*)      │ Min 1 item      │
│ Nominal Angsuran   │ Number   │ YES (*)      │ Min 0           │
│ [Add/Remove]       │ Button   │ -            │ Dynamic         │
│ Total (Display)    │ Display  │ NO           │ Auto sum        │
└────────────────────┴──────────┴──────────────┴─────────────────┘
```

---

## 🎨 CSS CLASSES REFERENCE

**Untuk custom styling atau troubleshooting:**

```css
/* Container */
.form-container           /* Main container */
.form-header              /* Header area */
.form-content             /* Form content wrapper */

/* Layout */
.form-grid                /* Grid container */
.form-grid-1              /* 1 column layout */
.form-grid-2              /* 2 column responsive */

/* Components */
.form-section             /* Section wrapper */
.form-section-header      /* Section title area */
.form-group               /* Input group */
.form-label               /* Label */
.form-input               /* Input field */
.form-error               /* Error message */
.form-helper              /* Helper text */

/* Special */
.form-display-box         /* Display/result box */
.summary-box              /* Total/summary box */
.dynamic-item             /* Repeatable item */
.dynamic-container        /* Container for repeatable */

/* States */
.form-input:focus         /* Input focus state */
.form-input.error         /* Input error state */
.form-error.show          /* Error message visible */

/* Buttons */
.btn                      /* Base button */
.btn-primary              /* Primary CTA */
.btn-secondary            /* Secondary action */
.btn-success              /* Success action */
.btn-danger               /* Danger action */
```

---

## 🔧 JAVASCRIPT API REFERENCE

**Public Methods (untuk extend/customize):**

```javascript
// Initialize form
FormManager.init()

// Add angsuran item
FormManager.addAngsuran()

// Remove angsuran item
FormManager.removeAngsuran(index)

// Recalculate total
FormManager.updateTotalAngsuran()

// Validate single field
FormManager.validateField(fieldId, value, rules)

// Examples:
FormManager.addAngsuran()           // Tambah 1 angsuran
FormManager.removeAngsuran(0)       // Hapus angsuran #0
FormManager.updateTotalAngsuran()   // Recalc total
```

---

## ⚙️ CONFIG & CONSTANTS

**Dapat dimodifikasi dalam `<script>` section:**

```javascript
// Ukuran max file (bytes)
config.maxFileSize = 2 * 1024 * 1024  // 2MB

// Tipe file yang diizinkan
config.allowedFileTypes = [
  'application/pdf',
  'image/jpeg',
  'image/png'
]

// Ekstensi file yang diizinkan
config.allowedExtensions = [
  'pdf', 'jpg', 'jpeg', 'png'
]

// Untuk ubah:
// 1. Edit nilai di script section
// 2. Test upload
// 3. Deploy
```

---

## 📡 API DATA FORMAT

**Data yang dikirim saat submit:**

```json
{
  "jabatan": "STRING",
  "noSk": "STRING",
  "tglMulai": "YYYY-MM-DD",
  "tglAkhir": "YYYY-MM-DD",
  "sisaMasaBulan": NUMBER,
  "noSkAgunan": "STRING",
  "fileSk": "FILENAME",
  "penghasilanTetap": NUMBER,
  "tambahanPenghasilan": NUMBER,
  "biayaHidup": NUMBER,
  "totalAngsuran": NUMBER,
  "angsuranItems": [
    {
      "index": NUMBER,
      "nama": "STRING",
      "nominal": NUMBER
    }
  ]
}
```

---

## 🐛 DEBUGGING CHECKLIST

**Jika ada masalah, cek:**

```
✓ Browser console (F12 → Console)
  - Ada error merah? → Baca error message
  - Jika ada, screenshot & report

✓ Form submission
  - Console: FormManager.validateForm() return true?
  - Network tab: Request terkirim? Response status 200?

✓ Calculations
  - Sisa masa kerja tidak update? → Check tgl mulai & akhir diisi
  - Total angsuran 0? → Check ada item & nominal terisi

✓ File upload
  - File tidak muncul di preview? → Check format & size
  - Error "Format not supported"? → Check ekstensi file

✓ Styling issues
  - Element tidak terlihat? → Check CSS classes applied
  - Button tidak bisa diklik? → Check z-index & pointer-events
```

---

## 🚨 COMMON ERROR MESSAGES & FIXES

| Error | Cause | Fix |
|-------|-------|-----|
| "Field wajib diisi" | Field empty | Fill the field |
| "Tanggal akhir harus >= tanggal mulai" | Wrong date order | Fix date range |
| "Format file tidak didukung" | Wrong file type | Use PDF/JPG/PNG |
| "Ukuran file maksimal 2MB" | File too large | Compress file |
| "Minimal 1 data angsuran" | No angsuran added | Click "Tambah Angsuran" |
| "Harus berupa angka positif" | Invalid number | Use positive numbers |

---

## 📱 RESPONSIVE BREAKPOINTS

```css
Desktop:  > 1200px  (Full layout)
Tablet:   768-1199px (2 col → 1 col)
Mobile:   < 768px   (1 col, stacked)

Form auto adjusts di:
- Tablet (768px): Form-grid-2 → single column
- Mobile (480px): Buttons full width, compact layout
```

---

## 🔄 FORM SUBMISSION FLOW

```
User Input
    ↓
Form Submit (click button)
    ↓
validateForm() → Cek semua field
    ├─ Ada error? → Show error messages, stop
    └─ Valid? → Continue
    ↓
handleFormSubmit() → Kumpulkan data
    ↓
POST /api/perangkat-desa/save
    ├─ Success (200) → Show success message
    └─ Error → Show error modal
    ↓
Redirect atau reload (sesuai backend)
```

---

## 📝 SAMPLE DATA FOR TESTING

```javascript
// Copy-paste ke console untuk quick test:
document.getElementById('jabatan').value = 'KEPALA DESA';
document.getElementById('noSk').value = 'SK/DESA/2024/001';
document.getElementById('tglMulai').value = '2020-01-15';
document.getElementById('tglAkhir').value = '2025-12-31';
document.getElementById('noSkAgunan').value = 'SK/AGUNAN/2024/001';
document.getElementById('penghasilanTetap').value = '3000000';
document.getElementById('tambahanPenghasilan').value = '500000';
document.getElementById('biayaHidup').value = '1500000';

FormManager.addAngsuran();
document.querySelector('.angsuran-nama').value = 'KREDIT MODAL KERJA';
document.querySelector('.angsuran-nominal').value = '500000';

FormManager.updateTotalAngsuran();
// Form now filled with sample data
```

---

## 🎓 LEARNING RESOURCES

### Untuk Analis (User)
- [ ] Baca: "Panduan Penggunaan" di dokumentasi full
- [ ] Coba: Fill form 3x dengan berbeda data
- [ ] Validasi: Test error cases (empty field, wrong date, etc)
- [ ] Tanya: Sesuatu yang tidak jelas? → Help desk

### Untuk Developer
- [ ] Review: Kode struktur & architecture
- [ ] Setup: Backend endpoint sesuai spec
- [ ] Test: Submit form, check database
- [ ] Deploy: To staging first, then production
- [ ] Monitor: Error logs, form submission success rate

---

## 🎯 IMPLEMENTATION CHECKLIST

### Phase 1: Setup (Developer)
- [ ] Copy file ke server
- [ ] Setup backend endpoint
- [ ] Create database tables
- [ ] Test file upload path
- [ ] Setup error logging

### Phase 2: Testing (QA)
- [ ] Test all fields validation
- [ ] Test file upload (different formats)
- [ ] Test calculations (sisa masa, total angsuran)
- [ ] Test dynamic add/remove items
- [ ] Test responsive (mobile, tablet, desktop)
- [ ] Test browser compatibility
- [ ] Test form submission flow

### Phase 3: Training (Admin)
- [ ] Train users on form usage
- [ ] Document: Jabatan Perangkat Desa valid
- [ ] Document: Contoh file upload
- [ ] Document: Common issues & solutions
- [ ] Setup help desk process

### Phase 4: Launch (Admin)
- [ ] Deploy to production
- [ ] Monitor first day usage
- [ ] Collect user feedback
- [ ] Fix bugs quickly
- [ ] Document lessons learned

---

## 📞 SUPPORT MATRIX

| Issue | Contact | Response Time |
|-------|---------|----------------|
| User can't login | IT Help Desk | 1 hour |
| Form not loading | Developer | 30 min |
| File upload fails | Developer | 30 min |
| Backend error | Backend Dev | 15 min |
| Data not saving | DBA | 1 hour |

---

## 🔐 SECURITY CHECKLIST

- [ ] CSRF token protection
- [ ] Input validation (client + server)
- [ ] File upload validation (server-side)
- [ ] SQL injection prevention
- [ ] XSS protection
- [ ] Auth & authorization checks
- [ ] Audit logging
- [ ] Data encryption at rest

---

**Last Updated:** 30 April 2026  
**Version:** 1.0  
**Status:** Ready for Production ✅
