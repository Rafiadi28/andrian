# Angsuran Bank Wonosobo - Implementasi Opsional Global

## 📋 Ringkasan Perubahan

Sistem telah diperbarui untuk membuat **Angsuran Bank Wonosobo** menjadi **OPSIONAL** (tidak wajib) secara global di seluruh form.

## 🔧 Komponen yang Dimodifikasi

### 1. **Global Configuration File** 
**File**: `config/form_settings.php`

Menambahkan konfigurasi terpusat untuk mengelola requirement angsuran:
- `angsuran_required` - Boolean untuk menentukan apakah angsuran wajib (default: `false`)
- `angsuran_min_count` - Minimal berapa item angsuran jika wajib
- Helper functions untuk mendapatkan pesan dan konfigurasi

**Manfaat**:
- Satu tempat untuk mengubah requirement
- Menghindari duplikasi kode
- Memudahkan maintenance dan testing

### 2. **Form Perangkat Desa** 
**File**: `analis/partials/tab_penghasilan_desa_improved.inc.php`

**Perubahan**:
- Menambahkan require ke `config/form_settings.php`
- UI menampilkan badge "OPSIONAL" atau "WAJIB" di section header
- Helper text berubah berdasarkan konfigurasi global
- Validasi JavaScript (`validateDesaForm()`) hanya meminta angsuran jika `angsuran_required = true`

**Lokasi perubahan**:
- Line ~6: Include config file
- Line ~230: Section header dengan badge
- Line ~236: Helper text dinamis
- Line ~1047: Validasi kondisional

### 3. **Form PPPK**
**File**: `analis/partials/tab_penghasilan_pppk_improved.inc.php`

**Perubahan**:
- Menambahkan require ke `config/form_settings.php`
- UI menampilkan badge "OPSIONAL" atau "WAJIB" di section header
- Helper text berubah berdasarkan konfigurasi global
- Validasi JavaScript (`validatePPPKForm()`) hanya meminta angsuran jika `angsuran_required = true`

**Lokasi perubahan**:
- Line ~6: Include config file
- Line ~163: Section header dengan badge
- Line ~169: Helper text dinamis
- Line ~962: Validasi kondisional

## 🌐 Hubungan Antar Komponen

```
┌─────────────────────────────────────┐
│   config/form_settings.php          │
│   (Global Configuration)            │
│   - angsuran_required (false)       │
│   - Helper functions                │
└──────────────┬──────────────────────┘
               │
       ┌───────┴──────────┐
       │                  │
┌──────▼─────────────────┐ ┌──────▼─────────────────┐
│  form_desa.php         │ │  form_pppk.php         │
│  - Include config      │ │  - Include config      │
│  - UI dengan badge     │ │  - UI dengan badge     │
│  - Validasi kondisional│ │  - Validasi kondisional│
└────────────────────────┘ └────────────────────────┘
       │                         │
       └────────┬────────────────┘
                │
        ┌───────▼────────┐
        │  Backend PHP   │
        │ save_section   │
        │ (Handle 0 val) │
        └────────────────┘
```

## 🔄 Alur Kerja

### Frontend (JavaScript)
1. User membuka form (Perangkat Desa atau PPPK)
2. Config global dimuat via PHP variable
3. UI menampilkan status "OPSIONAL" atau "WAJIB"
4. Saat save:
   - Jika `angsuran_required = false`: Allow submit tanpa angsuran
   - Jika `angsuran_required = true`: Wajib ada minimal 1 angsuran

### Backend (PHP)
1. Terima POST dari form
2. Angsuran array diproses:
   - Jika ada items: Sum total
   - Jika kosong: Default ke 0
3. Simpan ke database
4. Hitung repayment capacity dengan angsuran (bisa 0)

## ⚙️ Mengubah Requirement

Untuk **mengubah apakah angsuran wajib atau opsional**:

**Buka file**: `config/form_settings.php`

**Ubah line**:
```php
// Untuk membuat OPSIONAL (default):
'angsuran_required' => false,

// Untuk membuat WAJIB:
'angsuran_required' => true,
```

Perubahan akan **otomatis berlaku** di semua form (Desa, PPPK, dsb).

## ✅ Testing Checklist

- [x] Angsuran dapat dikosongkan (tidak wajib)
- [x] Form dapat disave tanpa angsuran
- [x] Repayment capacity tetap terhitung (dengan angsuran = 0)
- [x] UI menampilkan status dengan benar
- [x] Tidak ada duplikasi kode validasi
- [x] Backend handle empty angsuran gracefully
- [x] Config dapat diubah global

## 🛡️ Bug Prevention

1. **Tidak ada duplikasi** - Semua validasi mengacu ke global config
2. **Backward compatible** - Existing data tetap valid
3. **Safe empty handling** - Backend convert empty ke 0
4. **Graceful fallback** - Jika config tidak ada, default false

## 📝 Notes

- Angsuran bersifat **OPSIONAL** secara default untuk kedua jenis form
- Jika user menambahkan angsuran, akan diperhitungkan dalam repayment capacity
- Status "OPSIONAL" ditampilkan dengan badge hijau di UI
- Sistem ini hindari source code yang menyebabkan bug dengan consolidation
