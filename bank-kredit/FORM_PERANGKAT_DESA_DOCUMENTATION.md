# 📋 DOKUMENTASI FORM INPUT PERANGKAT DESA

**Versi:** 1.0 (Production Ready)  
**Tanggal:** 30 April 2026  
**Status:** ✅ Selesai & Siap Digunakan

---

## 📑 DAFTAR ISI

1. [Overview](#overview)
2. [Fitur Lengkap](#fitur-lengkap)
3. [Struktur Form](#struktur-form)
4. [Panduan Penggunaan](#panduan-penggunaan)
5. [Fitur Teknis](#fitur-teknis)
6. [Validasi & Error Handling](#validasi--error-handling)
7. [Perhitungan Otomatis](#perhitungan-otomatis)
8. [Integrasi Backend](#integrasi-backend)
9. [Troubleshooting](#troubleshooting)

---

## Overview

Sistem Form Input Perangkat Desa adalah aplikasi web modern untuk input data dan analisis kelayakan kredit perangkat desa di Bank Kredit Wonosobo. Form dirancang untuk kemudahan input analis kredit dengan fitur-fitur canggih seperti perhitungan otomatis, validasi real-time, dan upload dokumen.

### 🎯 Target User
- Analis Kredit Bank Wonosobo
- Staff Operasional
- Tim Verifikasi

### 🔄 Alur Penggunaan
1. **Buka Form** → Akses `form-desa-improved.html`
2. **Isi Data** → Lengkapi semua section form
3. **Validasi** → Sistem otomatis validasi saat input
4. **Simpan** → Submit data ke backend
5. **Proses** → Data masuk ke workflow approval

---

## Fitur Lengkap

### ✅ 1. Data Pekerjaan & SK
- **Input Jabatan**: Teks, 3-100 karakter
- **Nomor SK**: Surat keputusan pengangkatan
- **Tanggal Mulai & Akhir**: Date picker dengan validasi
- **Perhitungan Sisa Masa Kerja**: Otomatis real-time
  - Format: "X tahun Y bulan" atau "Z bulan"
  - Update saat tanggal diubah
  - Validasi: tanggal akhir ≥ tanggal mulai

### ✅ 2. Agunan & SK
- **Nomor SK Agunan**: Input teks dengan format uppercase
- **Upload File SK**: 
  - Format: PDF, JPG, PNG
  - Max ukuran: 2MB
  - Preview otomatis setelah upload
  - Validasi format & ukuran
  - Error handling yang informatif

### ✅ 3. Data Penghasilan
- **Penghasilan Tetap** (Required): Gaji/penghasilan pokok
- **Tambahan Penghasilan** (Optional): Tunjangan, bonus
- **Biaya Hidup** (Optional): Pengeluaran rutin bulanan
- Semua dalam Rupiah per bulan
- Format input: number dengan step Rp 50.000
- Validasi minimal value: 0

### ✅ 4. Angsuran Bank Wonosobo (Dynamic)
- **Repeatable Form**: Tambah/hapus data angsuran
- **Per Item Berisi**:
  - Jenis Kredit/Produk (text, required)
  - Nominal Angsuran (number, Rp/bulan)
- **Total Otomatis**: Dihitung real-time dari semua item
- **Minimal Requirement**: Minimal 1 data angsuran
- **Delete Function**: Hapus item & recalculate total

### ✅ 5. UI/UX Professional
- **Design Modern**: Gradient, shadows, animations
- **Responsive**: Mobile, tablet, desktop
- **Accessibility**: Proper labels, error messages
- **Visual Feedback**: 
  - Focus states untuk semua inputs
  - Error highlighting (red border + background)
  - Success feedback (preview display)
  - Loading states (akan diupdate untuk server calls)

### ✅ 6. Validasi & Error Handling
- **Real-time Validation**: Saat blur/change
- **Form-level Validation**: Saat submit
- **Error Messages**: Clear, informatif, actionable
- **Field-specific Rules**:
  - Required fields
  - Length validation
  - Date format & logic
  - File size & type
  - Number ranges
- **Visual Indicators**: Error styling pada input

### ✅ 7. Code Quality
- **Modular Architecture**: FormManager pattern
- **No Code Duplication**: Utility functions reusable
- **Clean Code**: Clear variable names, proper comments
- **Maintainable**: Easy to extend & modify
- **Error Handling**: Comprehensive try-catch (akan ditambah)
- **Performance**: Optimized selectors, event delegation

---

## Struktur Form

### Form Sections

```
FORM PERANGKAT DESA
├── 1. DATA PEKERJAAN & SK
│   ├── Jabatan
│   ├── Nomor SK
│   ├── Tanggal Mulai
│   ├── Tanggal Akhir
│   └── Sisa Masa Kerja (Display)
│
├── 2. AGUNAN & SK
│   ├── Nomor SK Agunan
│   └── Upload File SK
│
├── 3. DATA PENGHASILAN
│   ├── Penghasilan Tetap (Required)
│   ├── Tambahan Penghasilan
│   └── Biaya Hidup
│
└── 4. ANGSURAN BANK WONOSOBO
    ├── Angsuran Item #1 (Dynamic)
    │   ├── Jenis Kredit
    │   └── Nominal Angsuran
    ├── Angsuran Item #2 (Dynamic)
    ├── Tombol Tambah Angsuran
    └── Total Angsuran (Display/Hidden)
```

### HTML Structure
```html
<form id="desaForm">
  <section class="form-section">
    <header class="form-section-header">...</header>
    <div class="form-grid">
      <div class="form-group">
        <label>...</label>
        <input type="..." id="fieldId" ...>
        <span class="form-error" id="error-fieldId"></span>
      </div>
    </div>
  </section>
</form>
```

---

## Panduan Penggunaan

### 📖 LANGKAH DEMI LANGKAH

#### 1. **Buka Form**
```bash
# Local Development
file:///D:/laragon/www/andrian/bank-kredit/form-desa-improved.html

# Web Server
http://localhost/bank-kredit/form-desa-improved.html
```

#### 2. **Isi Section 1: Data Pekerjaan**
```
Jabatan: KEPALA DESA
Nomor SK: SK/DESA/2024/001
Tanggal Mulai: 01-01-2020
Tanggal Akhir: 31-12-2025
↓ (Otomatis: Sisa Masa Kerja = 5 tahun 11 bulan)
```

**Tips:**
- Gunakan format standar untuk nomor SK
- Pastikan tanggal akhir >= tanggal mulai
- Sisa masa kerja berguna untuk analisis jangka waktu kredit

#### 3. **Isi Section 2: Agunan**
```
Nomor SK Agunan: SK/AGUNAN/2024/001
Upload File SK: [Pilih File] (PDF/JPG/PNG, max 2MB)
↓ (Preview otomatis)
✓ File terpilih: SK_2024.pdf (1.5 MB)
```

**Tips:**
- File bisa PDF (hasil scan), JPG, atau PNG
- Maksimal 2MB untuk upload cepat
- Preview membantu verifikasi file yang dipilih
- Error akan muncul jika format/size tidak sesuai

#### 4. **Isi Section 3: Penghasilan**
```
Penghasilan Tetap: 3,000,000 (REQUIRED)
Tambahan Penghasilan: 500,000 (Optional)
Biaya Hidup: 1,500,000 (Optional)
```

**Tips:**
- Gunakan nilai realistis dari SK
- Format otomatis Rp (ribuan)
- Semua dalam satuan per bulan
- Biaya hidup penting untuk kalkulasi kemampuan bayar

#### 5. **Isi Section 4: Angsuran Bank Wonosobo**
```
[Klik "Tambah Angsuran"]
Angsuran #1:
  Jenis Kredit: KREDIT MODAL KERJA
  Nominal: 500,000

[Klik "Tambah Angsuran"]
Angsuran #2:
  Jenis Kredit: KREDIT KONSUMTIF
  Nominal: 750,000

↓ (Otomatis: Total Angsuran = Rp 1,250,000)
```

**Tips:**
- Minimal 1 angsuran harus ada
- Bisa tambah banyak angsuran sesuai kebutuhan
- Total otomatis update saat input berubah
- Gunakan [Hapus] untuk menghapus item

#### 6. **Validasi & Submit**
```
✓ Periksa semua field (error red highlight)
✓ Klik "Simpan Data"
↓ Sistem validasi
✓ Success message
↓ Data terkirim ke backend
```

**Validasi Error Messages:**
- ❌ "Field wajib diisi" → Lengkapi field
- ❌ "Format file tidak didukung" → Gunakan PDF/JPG/PNG
- ❌ "Ukuran file maksimal 2MB" → Compress file
- ❌ "Tanggal akhir harus >= tanggal mulai" → Perbaiki urutan tanggal
- ❌ "Minimal 1 data angsuran wajib ditambahkan" → Tambah minimal 1 angsuran

#### 7. **Bersihkan Form**
```
[Klik "Bersihkan Form"]
↓ Confirm dialog
✓ Semua data dihapus
→ Siap untuk input baru
```

---

## Fitur Teknis

### 🔧 Perhitungan Otomatis

#### 1. Sisa Masa Kerja
```javascript
// Input: Tanggal Mulai & Akhir
// Output: Tahun bulan atau bulan saja

Rumus:
- Hitung selisih hari
- Konversi ke bulan (÷30)
- Konversi ke tahun (÷12)
- Display format: "X tahun Y bulan" atau "Z bulan"

Contoh:
Mulai: 01-01-2020
Akhir: 31-12-2025
Hasil: 5 tahun 11 bulan (71 bulan)
```

#### 2. Total Angsuran
```javascript
// Input: Semua nominal angsuran
// Output: Total Rp

Rumus:
- Sum(Nominal1 + Nominal2 + ... + NominalN)
- Trigger: saat input berubah atau item ditambah/dihapus

Contoh:
Item 1: Rp 500.000
Item 2: Rp 750.000
Item 3: Rp 1.000.000
Total: Rp 2.250.000
```

### 🎨 CSS Architecture

```css
/* Reset & Base */
* { box-sizing: border-box; }

/* Layout */
.form-container { max-width: 1000px; }
.form-grid { grid-template-columns: repeat(auto-fit, ...); }

/* Components */
.form-group { display: flex; flex-direction: column; }
.form-input { padding, border, radius, transitions; }
.btn { padding, bg-gradient, box-shadow, hover effects; }

/* States */
.form-input:focus { border-color: #667eea; box-shadow: blue glow; }
.form-input.error { border-color: #ef4444; background: light-red; }

/* Responsive */
@media (max-width: 768px) {
  .form-grid-2 { grid-template-columns: 1fr; }
  .button-group { flex-direction: column; }
}
```

### 🔄 JavaScript Architecture

```javascript
FormManager {
  config: { form, maxFileSize, allowedTypes }
  state: { angsuranCounter, formData }
  
  formatRupiah()           // Format number to Rp format
  parseRupiah()            // Parse Rp format to number
  calculateDateDiff()      // Hitung selisih tanggal
  validateField()          // Validasi single field
  showError() / clearError()
  
  calculateSisaMasa()      // Perhitung otomatis sisa masa
  initFileUpload()         // Setup file upload handler
  addAngsuran()            // Tambah item angsuran
  removeAngsuran()         // Hapus item angsuran
  updateTotalAngsuran()    // Recalculate total
  
  initEventListeners()     // Setup all event handlers
  validateForm()           // Validasi seluruh form
  handleFormSubmit()       // Process form submission
  resetForm()              // Clear all fields
  
  init()                   // Initialize semua
}
```

### 📋 Validasi Rules

```javascript
Tipe Validasi:
- required            : Field tidak boleh kosong
- minLength:N         : Minimal N karakter
- maxLength:N         : Maksimal N karakter
- minValue:N          : Nilai minimal N
- dateFormat          : Format YYYY-MM-DD
- dateAfter:fieldId   : Tanggal harus setelah field lain
- fileRequired        : File harus diupload
- fileSize:bytes      : Max file size
- fileType:ext1,ext2  : Tipe file allowed

Contoh:
data-validate="required|minLength:3|maxLength:100"
```

---

## Validasi & Error Handling

### Field-level Validation

| Field | Rules | Error Message |
|-------|-------|---------------|
| Jabatan | required, 3-100 char | "Field wajib diisi" / "Minimal 3 karakter" |
| Nomor SK | required, 3-50 char | "Field wajib diisi" / "Minimal 3 karakter" |
| Tgl Mulai | required, date | "Tanggal wajib diisi" |
| Tgl Akhir | required, date, ≥ tgl mulai | "Tanggal wajib diisi" / "Harus >= tanggal mulai" |
| File SK | required, ≤2MB, pdf/jpg/png | "File wajib diupload" / "Format tidak didukung" |
| Penghasilan | ≥ 0 | "Harus >= 0" |
| Angsuran | required, ≥ 0 per item | "Jenis kredit wajib diisi" / "Nominal >= 0" |

### Form-level Validation
```javascript
saat submit:
1. Validasi semua required fields
2. Validasi format & logic semua fields
3. Validasi minimal 1 angsuran
4. Validasi setiap angsuran item
5. Jika semua valid → submit
6. Jika ada error → show error messages
```

### Error Display
```
[Input dengan error]
                       ↓
  ┌──────────────────────────────┐
  │ Penghasilan Tetap *          │
  │ ┌────────────────────────────┤ ← Red border
  │ │ [Input value] ← Red bg     │
  │ └────────────────────────────┤
  │ ❌ Harus berupa angka positif │ ← Error msg
  └──────────────────────────────┘
```

---

## Perhitungan Otomatis

### Trigger Points

| Event | Calculation | Update |
|-------|-----------|--------|
| `tglMulai` change | - | Validate tglMulai |
| `tglAkhir` change | Calculate sisa masa jabatan | Display & hidden input |
| `angsuranNominal` input | Sum all nominal | Display total |
| Add angsuran item | Update counter | Recount total |
| Delete angsuran | Update counter | Recalculate total |

### Calculation Flow

```
Input: Tgl Mulai & Tgl Akhir
    ↓
Validasi: tglAkhir >= tglMulai?
    ├─ Ya  → Hitung selisih
    └─ Tidak → Show error
    ↓
Hitung: (tglAkhir - tglMulai) / 30 = bulan
        bulan / 12 = tahun & sisa bulan
    ↓
Format: 
├─ Jika tahun > 0: "X tahun Y bulan"
└─ Jika hanya bulan: "Z bulan"
    ↓
Output: 
├─ Display: HTML element
└─ Hidden input: untuk backend
```

---

## Integrasi Backend

### Form Submission Data

```javascript
FormManager.handleFormSubmit() → POST /api/perangkat-desa/save

Payload JSON:
{
  "jabatan": "KEPALA DESA",
  "noSk": "SK/DESA/2024/001",
  "tglMulai": "2020-01-01",
  "tglAkhir": "2025-12-31",
  "sisaMasaBulan": 71,
  "noSkAgunan": "SK/AGUNAN/2024/001",
  "fileSk": "SK_2024_001.pdf",
  "penghasilanTetap": 3000000,
  "tambahanPenghasilan": 500000,
  "biayaHidup": 1500000,
  "totalAngsuran": 2250000,
  "angsuranItems": [
    {
      "index": 1,
      "nama": "KREDIT MODAL KERJA",
      "nominal": 500000
    },
    {
      "index": 2,
      "nama": "KREDIT KONSUMTIF",
      "nominal": 750000
    },
    {
      "index": 3,
      "nama": "KREDIT MULTIGUNA",
      "nominal": 1000000
    }
  ]
}
```

### File Upload Handling

**Option 1: Form Data (Recommended)**
```javascript
const formData = new FormData(document.getElementById('desaForm'));
fetch('/api/perangkat-desa/save', {
  method: 'POST',
  body: formData
});
```

**Option 2: Base64 Encoding**
```javascript
const file = document.getElementById('fileSk').files[0];
const reader = new FileReader();
reader.onload = (e) => {
  const base64 = e.target.result;
  // Include dalam JSON: "fileSk": base64
};
```

### Backend Endpoint Requirements

```php
// POST /api/perangkat-desa/save
POST endpoint harus:
1. Validate CSRF token
2. Check authorization (user role)
3. Validate form data
4. Handle file upload (store dengan unique name)
5. Save data ke database
6. Return success/error response

Response Format:
{
  "status": "success|error",
  "message": "Form berhasil disimpan",
  "data": {
    "id": 123,
    "created_at": "2026-04-30T10:30:00Z"
  }
}
```

### PHP Integration Example

```php
<?php
// Dalam form_desa.php atau endpoint handler

if ($_POST && isset($_POST['jabatan'])) {
    // 1. Validate
    $validator = new FormValidator($_POST);
    if (!$validator->validate()) {
        die(json_encode(['status' => 'error', 'errors' => $validator->errors()]));
    }
    
    // 2. Handle file upload
    if ($_FILES['fileSk']) {
        $filename = saveUploadedFile($_FILES['fileSk'], 'perangkat_desa');
    }
    
    // 3. Save to database
    $data = [
        'jabatan' => $_POST['jabatan'],
        'no_sk' => $_POST['noSk'],
        'tgl_mulai' => $_POST['tglMulai'],
        'tgl_akhir' => $_POST['tglAkhir'],
        'sisa_masa_bulan' => $_POST['sisaMasaBulan'],
        'no_sk_agunan' => $_POST['noSkAgunan'],
        'file_sk' => $filename,
        'penghasilan_tetap' => $_POST['penghasilanTetap'],
        'tambahan_penghasilan' => $_POST['tambahanPenghasilan'],
        'biaya_hidup' => $_POST['biayaHidup'],
        'total_angsuran' => $_POST['totalAngsuran']
    ];
    
    $perangkatDesaId = $db->insert('perangkat_desa', $data);
    
    // 4. Save angsuran items
    foreach ($_POST['angsuranItems'] as $item) {
        $db->insert('angsuran', [
            'perangkat_desa_id' => $perangkatDesaId,
            'jenis_kredit' => $item['nama'],
            'nominal' => $item['nominal']
        ]);
    }
    
    // 5. Return success
    echo json_encode(['status' => 'success', 'id' => $perangkatDesaId]);
}
```

---

## Troubleshooting

### ❌ Masalah & Solusi

#### 1. "Form tidak bisa disubmit"
```
Penyebab:
- Ada field required yang kosong
- File belum diupload
- Error messages muncul (red)

Solusi:
✓ Isi semua field yang ada tanda (*)
✓ Upload file SK
✓ Periksa error message yang muncul
✓ Scroll to error fields (browser akan auto)
```

#### 2. "Tanggal akhir selalu error"
```
Penyebab:
- Tanggal akhir < tanggal mulai
- Format tanggal tidak sesuai
- Tanggal akhir kosong

Solusi:
✓ Pastikan tgl akhir >= tgl mulai
✓ Gunakan date picker (bukan manual input)
✓ Isi tgl mulai terlebih dahulu
```

#### 3. "File tidak bisa diupload"
```
Penyebab:
- Format file tidak PDF/JPG/PNG
- File size > 2MB
- Browser block upload

Solusi:
✓ Convert file ke PDF atau JPG
✓ Compress image jika > 2MB (gunakan: tinypng.com)
✓ Coba browser lain
✓ Clear browser cache & cookies
```

#### 4. "Total angsuran tidak terupdate"
```
Penyebab:
- JavaScript error di console
- Event listener tidak attached
- Browser cache issue

Solusi:
✓ Buka Developer Tools (F12)
✓ Check Console untuk error
✓ Refresh page (Ctrl+F5)
✓ Try incognito mode
✓ Check FormManager.init() dijalankan
```

#### 5. "Form macet/freeze"
```
Penyebab:
- File terlalu besar (>2MB)
- Banyak angsuran items (>100)
- Browser low memory

Solusi:
✓ Compress file terlebih dahulu
✓ Clear form & mulai ulang
✓ Close unused tabs
✓ Restart browser
```

### 🔍 Debug Tips

#### Enable Console Logging
```javascript
// Di browser console (F12)
console.log('Form data:', JSON.stringify(formData, null, 2));
console.log('Validation result:', validateForm());
console.log('Total angsuran:', document.getElementById('totalAngsuran').value);
```

#### Check Element State
```javascript
// Di browser console
document.getElementById('jabatan').value           // Check value
document.getElementById('error-jabatan').textContent // Check error
document.querySelectorAll('.dynamic-item').length  // Check angsuran count
```

#### Network Debugging
```
1. Open DevTools (F12)
2. Go to Network tab
3. Submit form
4. Check request/response
5. Status: 200 OK or error?
6. Response data valid?
```

---

## Tips & Best Practices

### ✅ Untuk Analis Kredit
- ✓ Isi data dengan lengkap & akurat dari dokumen SK asli
- ✓ Verify sisa masa kerja sesuai dengan SK
- ✓ Upload scan SK yang jelas & readable
- ✓ Gunakan penghasilan realistis dari SK/bukti gaji
- ✓ Jangan lupa catat semua angsuran bank lain
- ✓ Review data sebelum submit

### ✅ Untuk Developer/Admin
- ✓ Setup backend endpoint sebelum production
- ✓ Validate file upload di server side
- ✓ Implement rate limiting untuk API
- ✓ Log semua form submission untuk audit
- ✓ Setup email notification saat form diterima
- ✓ Monitor error logs regularly
- ✓ Test dengan berbagai browser & device

### ✅ Untuk Maintenance
- ✓ Regular backup database
- ✓ Monitor upload folder size
- ✓ Clean old files (> 6 bulan)
- ✓ Update validation rules jika ada perubahan
- ✓ Test form monthly
- ✓ Keep browser compatibility updated

---

## Versioning & Updates

### v1.0 (30 April 2026) - Initial Release
- ✅ All 4 form sections
- ✅ Auto calculation features
- ✅ File upload with validation
- ✅ Dynamic repeatable items
- ✅ Complete validation system
- ✅ Professional UI/UX
- ✅ Responsive design
- ✅ Clean modular code

### Future Enhancements (v1.1+)
- [ ] Electron signature support
- [ ] Offline mode (IndexedDB)
- [ ] Multi-language support
- [ ] PDF export function
- [ ] Batch upload multiple forms
- [ ] Advanced scoring calculation
- [ ] Integration dengan approval workflow
- [ ] Mobile app version

---

## Support & Contact

Untuk questions, issues, atau suggestions:
```
Email: support@bank-kredit-wonosobo.local
Internal Wiki: [Link to internal docs]
Help Desk: ext. 1234
```

---

## License & Copyright

© 2026 Bank Kredit Wonosobo  
All rights reserved.

---

**Last Updated:** 30 April 2026  
**Created By:** Development Team  
**Status:** Production Ready ✅
