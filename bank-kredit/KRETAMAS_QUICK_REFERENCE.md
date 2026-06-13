# ⚡ QUICK REFERENCE - KRETAMAS

**Panduan Cepat Penggunaan & Implementasi**

---

## 📋 QUICK START (5 MENIT)

### Untuk User (Analis Kredit)
```
1. Buka: kretamas-form.html
2. Isi: Nilai Taksasi → Auto calculate ✓
3. Upload: Foto agunan (JPG/PNG)
4. Upload: Dokumen neraca (PDF/JPG/PNG)
5. Klik: "Simpan Data"
6. Done! ✅
```

### Untuk Developer
```
1. Deploy: kretamas-form.html ke server
2. Setup: Endpoint POST /api/kretamas/save
3. Test: Isi form, submit, check database
4. Deploy: Go live!
```

---

## 🎯 KEY FEATURES AT A GLANCE

| Fitur | Input | Output | Update |
|-------|-------|--------|--------|
| **Nilai Likuidasi** | Nilai Taksasi | 95% × Taksasi | Real-time |
| **Plafond Kredit** | Nilai Taksasi | 100% × Taksasi | Real-time |
| **Foto Agunan** | JPG/PNG, ≤2MB | Preview gambar | On upload |
| **Dokumen Neraca** | PDF/JPG/PNG, ≤2MB | Nama file + size | On upload |
| **Validasi** | All inputs | Error messages | Real-time |

---

## 🔑 PERHITUNGAN OTOMATIS

```javascript
Input: Nilai Taksasi = Rp 10.000.000

Perhitungan:
Nilai Likuidasi   = 95% × 10.000.000 = Rp 9.500.000
Plafond Kredit    = 100% × 10.000.000 = Rp 10.000.000

Output:
├── Display: Rp 9.500.000 (likuidasi)
├── Display: Rp 10.000.000 (plafond)
└── Hidden inputs: Untuk backend
```

---

## 📊 FIELD REFERENCE TABLE

### Section 1: Data Kredit Emas
```
┌─────────────────────┬──────────┬──────────────┬────────────────┐
│ Field               │ Type     │ Required     │ Rules          │
├─────────────────────┼──────────┼──────────────┼────────────────┤
│ Nilai Taksasi       │ Number   │ YES (*)      │ > 0, numeric   │
│ Nilai Likuidasi     │ Display  │ NO           │ Auto 95%       │
│ Plafond Kredit      │ Display  │ NO           │ Auto 100%      │
└─────────────────────┴──────────┴──────────────┴────────────────┘
```

### Section 2: Dokumen Agunan
```
┌─────────────────────┬──────────┬──────────────┬─────────────────┐
│ Field               │ Type     │ Required     │ Rules           │
├─────────────────────┼──────────┼──────────────┼─────────────────┤
│ Foto Agunan         │ File     │ YES (*)      │ JPG/PNG, ≤2MB   │
└─────────────────────┴──────────┴──────────────┴─────────────────┘
```

### Section 3: Neraca & Dokumen
```
┌─────────────────────┬──────────┬──────────────┬──────────────────┐
│ Field               │ Type     │ Required     │ Rules            │
├─────────────────────┼──────────┼──────────────┼──────────────────┤
│ Dokumen Neraca      │ File     │ YES (*)      │ PDF/JPG/PNG, ≤2MB│
└─────────────────────┴──────────┴──────────────┴──────────────────┘
```

---

## 🎨 CSS CLASSES REFERENCE

```css
/* Layout */
.app-container           /* Main container */
.app-header              /* Header */
.app-content             /* Form area */
.section                 /* Section wrapper */

/* Form */
.form-grid               /* Grid container */
.form-group              /* Input group */
.form-label              /* Label */
.form-input              /* Input field */
.form-error              /* Error message */

/* Results */
.result-box              /* Display box untuk hasil */
.result-value            /* Hasil value (Rp format) */

/* File Upload */
.file-upload-wrapper     /* File upload area */
.file-preview            /* File info preview */
.image-preview-container /* Image preview area */

/* Buttons */
.btn                     /* Base button */
.btn-primary             /* Primary action */
.btn-secondary           /* Secondary action */
.btn-success             /* Success action */
```

---

## 🔧 JAVASCRIPT API REFERENCE

```javascript
// KretamasApp API:

KretamasApp.init()               // Initialize aplikasi

// Perhitungan:
calculateKreditResults()         // Trigger perhitungan

// Validasi:
validateField(fieldId)           // Validate single field
validateForm()                   // Validate entire form

// File handling:
initFotoAgunanUpload()           // Setup foto upload
initDokumenNeracaUpload()        // Setup dokumen upload

// Form handling:
handleFormSubmit()               // Process submission
resetForm()                      // Clear all fields
```

---

## ⚙️ CONFIG & CONSTANTS

```javascript
// Di dalam KretamasApp:

config.maxFileSize = 2 * 1024 * 1024  // 2MB

config.allowedImageFormats = [
  'image/jpeg',
  'image/png'
]

config.allowedImageExtensions = [
  'jpg', 'jpeg', 'png'
]

config.allowedDocumentFormats = [
  'application/pdf',
  'image/jpeg',
  'image/png'
]

config.allowedDocumentExtensions = [
  'pdf', 'jpg', 'jpeg', 'png'
]
```

---

## 📡 API DATA FORMAT

**Data yang dikirim saat submit:**

```json
{
  "nilaiTaksasi": 10000000,
  "nilaiLikuidasi": 9500000,
  "plafondKredit": 10000000,
  "fotoAgunan": "FOTO_EMAS_001.jpg",
  "dokumenNeraca": "NERACA_2024.pdf"
}
```

---

## 🐛 DEBUGGING CHECKLIST

```
✓ Perhitungan tidak update?
  - Check: Nilai taksasi diisi dengan angka positif
  - Check: Input event listener berfungsi
  - Check: Console.log hasil perhitungan

✓ File tidak bisa diupload?
  - Check: File size ≤ 2MB
  - Check: File format sesuai (JPG/PNG/PDF)
  - Check: Browser console untuk error

✓ Form tidak bisa disubmit?
  - Check: Semua required fields terisi
  - Check: Tidak ada error messages (red)
  - Check: Both files sudah diupload

✓ Styling issue?
  - Check: CSS classes applied
  - Check: No browser cache (Ctrl+F5)
  - Check: Responsive viewport
```

---

## 🚨 COMMON ERROR MESSAGES & FIXES

| Error | Cause | Fix |
|-------|-------|-----|
| "Nilai taksasi wajib diisi" | Field empty | Fill dengan angka |
| "Hanya angka yang diizinkan" | Non-numeric input | Use numbers only |
| "Nilai tidak boleh negatif" | Negative number | Use positive value |
| "Foto agunan wajib diupload" | File not selected | Click upload & select file |
| "Ukuran file maksimal 2MB" | File too large | Compress image |
| "Format file harus JPG atau PNG" | Wrong format | Use JPG or PNG |
| "Dokumen neraca wajib diupload" | File not selected | Upload dokumen |
| "Format file harus PDF, JPG, atau PNG" | Wrong format | Use PDF, JPG, or PNG |

---

## 📱 RESPONSIVE BREAKPOINTS

```css
Desktop:  > 1200px  (Full 2-col layout)
Tablet:   768-1199px (2 col → 1 col)
Mobile:   < 768px   (1 col, stacked)
Small:    < 480px   (Compact layout)

Form auto adjusts dengan media queries
```

---

## 🎓 LEARNING PATH

### Untuk Analis (User)
```
1. Baca README cepat (5 min)
2. Buka form di browser
3. Fill dengan demo data
4. Praktek 2-3x
5. Ready untuk real data!
```

### Untuk Developer
```
1. Review kode HTML/CSS (30 min)
2. Study JavaScript (30 min)
3. Setup backend endpoint (2-3 jam)
4. Test end-to-end (1 jam)
5. Deploy & monitor
```

---

## 📝 SAMPLE DATA FOR TESTING

```javascript
// Quick fill test data:

document.getElementById('nilaiTaksasi').value = '10000000';
document.getElementById('nilaiTaksasi').dispatchEvent(new Event('input'));

// Expected results:
// Nilai Likuidasi: Rp 9.500.000
// Plafond Kredit: Rp 10.000.000
```

---

## 🔄 FORM SUBMISSION FLOW

```
User Input nilai taksasi
    ↓
Auto calculate (real-time)
↓ Nilai Likuidasi & Plafond display
    ↓
Upload file foto agunan
    ↓
Upload file dokumen neraca
    ↓
Click "Simpan Data"
    ↓
validateForm() → Check semua field
├─ Ada error? → Show messages, stop
└─ Valid? → Continue
    ↓
handleFormSubmit() → Kumpulkan data
    ↓
POST /api/kretamas/save
├─ Success (200) → Show success msg
└─ Error → Show error modal
    ↓
Redirect atau reload
```

---

## 🎯 IMPLEMENTATION CHECKLIST

### Phase 1: Setup (Developer)
- [ ] Copy file ke server
- [ ] Setup backend endpoint
- [ ] Create database table (kretamas)
- [ ] Test file upload path
- [ ] Setup error logging

### Phase 2: Testing (QA)
- [ ] Test perhitungan (95% & 100%)
- [ ] Test file upload (JPG/PNG/PDF)
- [ ] Test file size validation
- [ ] Test error messages
- [ ] Test responsive layout
- [ ] Test browser compatibility
- [ ] Test form submission flow

### Phase 3: Training (Admin)
- [ ] Train users on form usage
- [ ] Document: Sample data
- [ ] Document: Common issues
- [ ] Setup help desk process

### Phase 4: Launch (Admin)
- [ ] Deploy to production
- [ ] Monitor first day
- [ ] Collect feedback
- [ ] Fix bugs quickly

---

**Last Updated:** 30 April 2026  
**Version:** 1.0  
**Status:** Ready ✅
