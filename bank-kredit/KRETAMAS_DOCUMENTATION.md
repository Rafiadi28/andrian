# 📋 DOKUMENTASI KRETAMAS (KREDIT EMAS)

**Versi:** 1.0 (Production Ready)  
**Tanggal:** 30 April 2026  
**Status:** ✅ Selesai & Siap Digunakan

---

## 📑 DAFTAR ISI

1. [Overview](#overview)
2. [Fitur Lengkap](#fitur-lengkap)
3. [Panduan Penggunaan](#panduan-penggunaan)
4. [Perhitungan Otomatis](#perhitungan-otomatis)
5. [Validasi & Error Handling](#validasi--error-handling)
6. [Integrasi Backend](#integrasi-backend)
7. [Troubleshooting](#troubleshooting)

---

## Overview

KRETAMAS (Kredit Emas) adalah aplikasi web untuk input dan proses permohonan kredit dengan jaminan emas di Bank Kredit Wonosobo. Aplikasi dirancang khusus untuk mempermudah analis dalam input data dengan perhitungan otomatis yang akurat dan user interface yang profesional.

### 🎯 Target User
- Analis Kredit (Kredit Emas)
- Staff Operasional
- Officer Kredit

### 🔄 Alur Penggunaan
1. **Buka Form** → Akses kretamas-form.html
2. **Isi Data** → Nilai taksasi emas + dokumen
3. **Perhitungan Otomatis** → Likuidasi & plafond auto-calculate
4. **Upload Files** → Foto agunan + dokumen neraca
5. **Simpan** → Submit data ke backend

---

## Fitur Lengkap

### ✅ 1. Data Kredit Emas
- **Input Nilai Taksasi**: Nilai emas dalam Rupiah
- **Perhitungan Otomatis Real-time**:
  - Nilai Likuidasi = 95% × Nilai Taksasi
  - Plafond Kredit = 100% × Nilai Taksasi
- **Display Format**: Rupiah dengan pemisah ribuan
- **Update Real-time**: Saat nilai taksasi berubah

### ✅ 2. Dokumen Agunan
- **Upload Foto Emas**:
  - Format: JPG, PNG
  - Max size: 2MB
  - Image preview otomatis
  - Validasi format & size
- **Error Handling**: Clear error messages
- **Preview**: Menampilkan nama file + ukuran

### ✅ 3. Neraca & Dokumen
- **Upload Dokumen**:
  - Format: PDF, JPG, PNG
  - Max size: 2MB
  - Validasi format & size
- **File Preview**: Menampilkan nama file + ukuran
- **Fleksibel**: Bisa PDF atau gambar

### ✅ 4. Validasi & Error Handling
- **Real-time Validation**: Saat blur/input
- **Form-level Validation**: Saat submit
- **Validation Rules**:
  - Required fields (tidak boleh kosong)
  - Numeric validation (hanya angka)
  - Positive numbers (tidak boleh negatif)
  - File size (max 2MB)
  - File format (JPG/PNG/PDF)
- **Error Messages**: Clear & informatif
- **Visual Indicators**: Red border + error text

### ✅ 5. UI/UX Professional
- **Design**: Modern dengan tema emas (gold/brown gradient)
- **Responsive**: Mobile, tablet, desktop
- **Layout**: Grid/Flexbox untuk arrangement
- **Typography**: Clear hierarchy & readability
- **Colors**: Gold/brown gradient (tema emas)
- **Spacing**: Konsisten & professional
- **Icons**: Relevant & clear

---

## Panduan Penggunaan

### 📖 LANGKAH DEMI LANGKAH

#### 1. **Buka Form**
```bash
# Local
file:///D:/laragon/www/andrian/bank-kredit/kretamas-form.html

# Web Server
http://localhost/bank-kredit/kretamas-form.html
```

#### 2. **Isi Nilai Taksasi**
```
Nilai Taksasi Emas: 10,000,000

Sistem otomatis menghitung:
→ Nilai Likuidasi: Rp 9,500,000 (95%)
→ Plafond Kredit: Rp 10,000,000 (100%)
```

**Tips:**
- Masukkan nilai taksasi sesuai dokumen penilaian
- Perhitungan otomatis real-time saat input
- Format otomatis menjadi Rp saat ditampilkan
- Nilai harus positif (>0)

#### 3. **Upload Foto Agunan**
```
1. Klik area upload foto
2. Pilih foto emas (JPG/PNG)
3. File harus ≤ 2MB
4. Preview otomatis muncul
↓ Foto ditampilkan
```

**Tips:**
- Foto harus jelas & readable
- Gunakan JPG untuk file lebih kecil
- Pastikan ukuran ≤ 2MB
- Error akan muncul jika format/size salah
- Preview membantu verifikasi file yang dipilih

#### 4. **Upload Dokumen Neraca**
```
1. Klik area upload dokumen
2. Pilih dokumen (PDF/JPG/PNG)
3. File harus ≤ 2MB
4. Preview nama file + ukuran
```

**Tips:**
- Bisa dokumen neraca, laporan keuangan, dll
- Format PDF lebih profesional untuk dokumen
- Pastikan readable & tidak corrupted
- Error akan muncul jika format/size salah

#### 5. **Validasi & Simpan**
```
✓ Periksa semua field (error akan highlight)
✓ Nilai taksasi wajib > 0
✓ Foto agunan harus diupload
✓ Dokumen neraca harus diupload
✓ Klik "Simpan Data"
↓ Sistem validasi lengkap
✓ Success message muncul
↓ Data terkirim ke backend
```

#### 6. **Bersihkan Form**
```
[Klik "Bersihkan Form"]
↓ Confirm dialog
✓ Semua data dihapus
→ Siap untuk input baru
```

---

## Perhitungan Otomatis

### Formula Kredit Emas

```
INPUT: Nilai Taksasi (Rp)

PERHITUNGAN:

1. Nilai Likuidasi = 95% × Nilai Taksasi
   Tujuan: Nilai emas untuk likuidasi/eksekusi
   Contoh: Rp 10.000.000 × 95% = Rp 9.500.000

2. Plafond Kredit = 100% × Nilai Taksasi
   Tujuan: Batas kredit maksimal yang bisa diberikan
   Contoh: Rp 10.000.000 × 100% = Rp 10.000.000

OUTPUT: 
├── Nilai Likuidasi (display + hidden input)
└── Plafond Kredit (display + hidden input)
```

### Trigger Perhitungan

```
Event: Input atau change pada Nilai Taksasi
Timing: Real-time (instant)
Update: Display & hidden inputs
Reset: Jika nilai taksasi dihapus → Rp 0
```

### Contoh Perhitungan

| Nilai Taksasi | Nilai Likuidasi (95%) | Plafond Kredit (100%) |
|---------------|----------------------|----------------------|
| Rp 5.000.000 | Rp 4.750.000 | Rp 5.000.000 |
| Rp 10.000.000 | Rp 9.500.000 | Rp 10.000.000 |
| Rp 50.000.000 | Rp 47.500.000 | Rp 50.000.000 |
| Rp 100.000.000 | Rp 95.000.000 | Rp 100.000.000 |

---

## Validasi & Error Handling

### Field-level Validation

| Field | Rules | Error Message |
|-------|-------|---------------|
| Nilai Taksasi | required, numeric, ≥0 | "Nilai taksasi wajib diisi" / "Hanya angka" / "Tidak boleh negatif" |
| Foto Agunan | required, ≤2MB, JPG/PNG | "Foto wajib diupload" / "Max 2MB" / "Format harus JPG/PNG" |
| Dokumen Neraca | required, ≤2MB, PDF/JPG/PNG | "Dokumen wajib diupload" / "Max 2MB" / "Format harus PDF/JPG/PNG" |

### Form-level Validation

```
Saat submit:
1. Validasi nilai taksasi tidak kosong
2. Validasi nilai taksasi > 0
3. Validasi foto agunan diupload
4. Validasi dokumen neraca diupload
5. Jika valid → submit
6. Jika error → show messages
```

### Error Display

```
[Input dengan error]
                       ↓
  ┌──────────────────────────────┐
  │ Nilai Taksasi *              │
  │ ┌────────────────────────────┤ ← Red border
  │ │ [Input value] ← Red bg     │
  │ └────────────────────────────┤
  │ ❌ Nilai taksasi wajib diisi  │ ← Error msg
  └──────────────────────────────┘
```

---

## Integrasi Backend

### Form Submission Data

```javascript
FormManager.handleFormSubmit() → POST /api/kretamas/save

Payload JSON:
{
  "nilaiTaksasi": 10000000,
  "nilaiLikuidasi": 9500000,
  "plafondKredit": 10000000,
  "fotoAgunan": "foto_emas_001.jpg",
  "dokumenNeraca": "neraca_2024.pdf"
}
```

### Backend Endpoint Requirements

```php
// POST /api/kretamas/save
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
// Dalam kretamas form submission handler

if ($_POST && isset($_POST['nilaiTaksasi'])) {
    // 1. Validate
    if (empty($_POST['nilaiTaksasi']) || $_POST['nilaiTaksasi'] <= 0) {
        die(json_encode(['status' => 'error', 'message' => 'Invalid nilai taksasi']));
    }
    
    // 2. Handle file uploads
    $fotoAgunan = saveUploadedFile($_FILES['fotoAgunan'], 'kretamas/agunan');
    $dokumenNeraca = saveUploadedFile($_FILES['dokumenNeraca'], 'kretamas/neraca');
    
    if (!$fotoAgunan || !$dokumenNeraca) {
        die(json_encode(['status' => 'error', 'message' => 'File upload failed']));
    }
    
    // 3. Save to database
    $data = [
        'nilai_taksasi' => (float)$_POST['nilaiTaksasi'],
        'nilai_likuidasi' => (float)$_POST['nilaiLikuidasi'],
        'plafond_kredit' => (float)$_POST['plafondKredit'],
        'foto_agunan' => $fotoAgunan,
        'dokumen_neraca' => $dokumenNeraca,
        'created_by' => $userId
    ];
    
    $kretamasId = $db->insert('kretamas', $data);
    
    // 4. Return success
    echo json_encode(['status' => 'success', 'id' => $kretamasId]);
}
```

---

## Troubleshooting

### ❌ Masalah & Solusi

#### 1. "Form tidak bisa disubmit"
```
Penyebab:
- Nilai taksasi kosong atau 0
- File foto agunan belum diupload
- File dokumen neraca belum diupload

Solusi:
✓ Isi nilai taksasi dengan angka positif
✓ Upload foto emas (JPG/PNG)
✓ Upload dokumen neraca (PDF/JPG/PNG)
✓ Periksa error message yang muncul
```

#### 2. "Perhitungan tidak muncul"
```
Penyebab:
- JavaScript error di console
- Nilai taksasi tidak valid (text, negative)
- Browser cache issue

Solusi:
✓ Refresh page (Ctrl+F5)
✓ Buka Developer Tools (F12) check error
✓ Pastikan input value adalah angka positif
✓ Try browser lain atau incognito mode
```

#### 3. "File tidak bisa diupload"
```
Penyebab:
- File size > 2MB
- Format file tidak sesuai (wrong extension)
- Browser security restriction

Solusi:
✓ Compress file (gunakan tinypng.com untuk JPG/PNG)
✓ Gunakan format yang tepat (JPG/PNG untuk foto, PDF untuk dokumen)
✓ Check file extension matches content
✓ Try browser lain
✓ Clear browser cache
```

#### 4. "Tidak bisa lihat image preview"
```
Penyebab:
- File corrupted
- Format tidak support (JPEG vs JPG)
- Large file size

Solusi:
✓ Check file dengan image viewer dulu
✓ Gunakan format standar (JPG atau PNG)
✓ Compress image jika terlalu besar
✓ Re-upload file yang berbeda
```

### 🔍 Debug Tips

#### Enable Console Logging
```javascript
// Di browser console (F12)
console.log('Nilai Likuidasi:', document.getElementById('nilaiLikuidasi').value);
console.log('Plafond Kredit:', document.getElementById('plafondKredit').value);
```

#### Check Element State
```javascript
// Di browser console
document.getElementById('nilaiTaksasi').value           // Check nilai
document.getElementById('fotoAgunan').files.length      // Check foto diupload
document.getElementById('dokumenNeraca').files.length   // Check dokumen diupload
```

---

## Tips & Best Practices

### ✅ Untuk Analis Kredit
- ✓ Masukkan nilai taksasi sesuai dokumen penilaian resmi
- ✓ Upload foto emas yang jelas & readable
- ✓ Upload dokumen neraca yang lengkap
- ✓ Review hasil perhitungan sebelum submit
- ✓ Pastikan file tidak corrupted

### ✅ Untuk Developer/Admin
- ✓ Setup backend endpoint sebelum production
- ✓ Validate file upload di server side
- ✓ Implement rate limiting untuk API
- ✓ Log semua form submission untuk audit
- ✓ Monitor upload folder size
- ✓ Backup database regularly

---

## Versioning & Updates

### v1.0 (30 April 2026) - Initial Release
- ✅ All 3 form sections
- ✅ Auto calculation features (Likuidasi 95%, Plafond 100%)
- ✅ File upload with validation
- ✅ Complete validation system
- ✅ Professional UI/UX
- ✅ Responsive design
- ✅ Clean modular code
- ✅ Production-ready

---

**Last Updated:** 30 April 2026  
**Created By:** Development Team  
**Status:** Production Ready ✅
