# Perbaikan Form Data Pekerjaan Perangkat Desa

## 📋 Ringkasan Masalah

Form **Data Pekerjaan & Keuangan (Perangkat Desa)** tidak bisa disimpan data karena ada beberapa bug pada:
1. Parameter mapping di backend (file parameter tidak sesuai SQL columns)
2. Form validation mencoba validasi field yang tidak ada
3. File upload requirement terlalu ketat untuk edit

## 🔧 Bug #1: Parameter Mapping Error di `save_section.php`

**File**: `analis/save_section.php` (lines 630-667)

### Masalah
Saat UPDATE database, parameter tidak sesuai dengan column di SQL:

```php
// SALAH - Sebelum:
$execParams = [
    $jabatan,           
    'PERANGKAT_DESA',   // ← Seharusnya '-' untuk nama_usaha
    $sk_d,              // ✓ Benar untuk bidang_usaha
    $tgl_mulai, $tgl_akhir,
    $omset_total,
    $laba,
    $tambahan,          // ← SALAH! Ini untuk cashflow_usaha, seharusnya $laba
    $biaya_hidup,
    ...
];
```

### Solusi
```php
// BENAR - Sesudah:
$execParams = [
    $jabatan,           // jabatan
    '-',                // nama_usaha (tidak digunakan)
    $sk_d,              // bidang_usaha (SK number)
    $tgl_mulai, $tgl_akhir, // lama_usaha, departemen_bagian
    $omset_total,       // omset_per_bulan
    $laba,              // laba_bersih
    $laba,              // cashflow_usaha ✓ FIXED
    $biaya_hidup,
    $cic,
    $total_pengeluaran,
    $net_cashflow,
    $rpc,
    $angsuran_diajukan,
    $status_kelayakan,
    '-'                 // pppk_agunan_no_sk (tidak digunakan)
];
```

## 🔧 Bug #2: Validasi Field Yang Tidak Ada

**File**: `analis/partials/tab_penghasilan_desa_improved.inc.php` (lines 945, 1053)

### Masalah
Form mencoba validate field `desk_agunan_no_sk` yang tidak ada di HTML:

```javascript
// SALAH:
['desk_jabatan', 'desk_no_sk', 'desk_agunan_no_sk'].forEach(id => {
    // Try to find element... tidak ada!
});
```

### Solusi
```javascript
// BENAR:
['desk_jabatan', 'desk_no_sk'].forEach(id => {
    // Element ada di form
});
```

## 🔧 Bug #3: File Required Terlalu Ketat

**File**: `analis/partials/tab_penghasilan_desa_improved.inc.php` (line 195)

### Masalah
File SK marked `required` pada input field, tapi saat **edit**, user tidak perlu re-upload:

```html
<!-- SALAH: -->
<input type="file" id="desk_file_sk" required>
```

### Solusi
```html
<!-- BENAR: -->
<input type="file" id="desk_file_sk">  <!-- required removed -->
```

Dan di JavaScript, tambah conditional validation:

```javascript
// Validasi file SK - hanya wajib jika belum ada file dan form baru
const fileInput = document.getElementById('desk_file_sk');
const idPengajuan = document.getElementById('id_pengajuan')?.value || '0';
if (parseInt(idPengajuan) === 0 && (!fileInput?.files || fileInput.files.length === 0)) {
    showDesaError('desk_file_sk', 'File SK wajib diisi untuk pengajuan baru');
    isValid = false;
}
```

## 🔧 Bug #4: Prefill Data Tidak Sesuai

**File**: `analis/partials/pegawai_page.inc.php` (lines 143-156)

### Masalah
Saat edit, prefill logic mencoba set field yang tidak ada:

```javascript
// SALAH:
if (pg.pppk_agunan_no_sk) setId('desk_agunan_no_sk', pg.pppk_agunan_no_sk);
// Field ini tidak ada di form Perangkat Desa!
```

Dan tidak handle perbedaan field tanggal berdasarkan jabatan:

```javascript
// SALAH - Selalu set desk_tgl_akhir:
if (pg.departemen_bagian) setId('desk_tgl_akhir', pg.departemen_bagian);
```

### Solusi
```javascript
// BENAR - Conditional set berdasarkan jabatan:
var jabatan = pg.jabatan || '';
if (jabatan === 'KEPALA DESA') {
    if (pg.departemen_bagian) setId('desk_tgl_akhir', pg.departemen_bagian);
} else if (['SEKRETARIS DESA', 'KEPALA DUSUN', 'KAUR'].indexOf(jabatan) !== -1) {
    if (pg.departemen_bagian) setId('desk_tgl_lahir', pg.departemen_bagian);
}
```

## 📊 Files Modified

| File | Perubahan | Status |
|------|-----------|--------|
| `analis/save_section.php` | Fix parameter mapping untuk Perangkat Desa | ✅ |
| `analis/partials/tab_penghasilan_desa_improved.inc.php` | Remove invalid field ref, conditional file validation | ✅ |
| `analis/partials/pegawai_page.inc.php` | Fix prefill logic, conditional date field setting | ✅ |

## ✅ Testing Checklist

- [x] Parameter mapping sesuai dengan SQL columns
- [x] File upload optional untuk edit
- [x] Form validation hanya untuk field yang ada
- [x] Prefill data bekerja dengan benar
- [x] Kondisional tanggal berdasarkan jabatan
- [x] Tidak ada duplikasi kode

## 🎯 Result

Form **Data Pekerjaan & Keuangan (Perangkat Desa)** sekarang bisa disimpan dengan benar tanpa error.
