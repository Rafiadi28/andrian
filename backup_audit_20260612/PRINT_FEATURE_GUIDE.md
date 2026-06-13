# FITUR CETAK DOKUMEN PENGAJUAN KREDIT

## Overview
Fitur cetak memungkinkan **analis**, **kabag_analis**, dan **admin** untuk mencetak ringkasan hasil persetujuan pengajuan kredit setelah semua approval selesai.

---

## Kapan Dokumen Dapat Dicetak?

Dokumen hanya dapat dicetak ketika:
1. ✅ **Status Pengajuan = 'disetujui'** (semua approval selesai)
2. ✅ **Pengguna adalah salah satu dari:** analis, kabag_analis, atau Superadmin
3. ✅ **Pengajuan sudah through full approval chain** (kadiv_kredit untuk < 500M, atau direksi untuk ≥ 500M)

---

## Cara Mengakses Cetak

### **Metode 1: Dari Detail Pengajuan**

1. Buka detail pengajuan: `detail.php?id=<pengajuan_id>`
2. Jika status adalah **disetujui**, tombol **🖨️ Cetak Dokumen** akan muncul
3. Klik tombol untuk membuka halaman cetak di tab baru
4. Gunakan **Ctrl+P** atau klik tombol **🖨️ Cetak Dokumen** di halaman print untuk cetak

```
[📋 Data Diri] [💰 Data Pinjaman] [✓ Timeline Persetujuan]
         ↓
    [CETAK BUTTON] ← Hanya muncul jika status='disetujui'
```

### **Metode 2: Dari Riwayat Approval Dashboard**

1. Buka dashboard approver: 
   - `kadiv_kredit/dashboard.php`
   - `kabag_analis/dashboard.php`
   - `kabag_kredit/dashboard.php`
   - `direksi/dashboard.php`

2. Scroll ke bagian **"✓ Riwayat Approval"**
3. Lihat tabel approval history
4. Untuk pengajuan yang sudah **fully approved** (status = 'disetujui'), kolom **Aksi** akan menampilkan tombol **🖨️ Cetak**
5. Klik tombol untuk cetak

```
[Detail] [🖨️ Cetak]  ← Cetak button hanya muncul untuk approved items
```

---

## Isi Dokumen Cetak

Dokumen cetak menampilkan:

### **1. Header Bank**
- Logo & nama bank
- Judul dokumen: "RINGKASAN PERSETUJUAN PENGAJUAN KREDIT"
- Status persetujuan: ✓ DISETUJUI

### **2. Data Diri Pemohon (📋)**
```
├─ Nama Pemohon
├─ NIK
├─ Tempat/Tanggal Lahir
├─ Status Perkawinan
├─ Pekerjaan
├─ Alamat KTP
├─ Alamat Domisili
└─ No. HP
```

### **3. Data Pinjaman (💰)**
```
┌─────────────────────┐ ┌──────────────────┐
│ Plafon Kredit       │ │ Angsuran Bulanan │
│ Rp X.XXX.XXX        │ │ Rp X.XXX.XXX     │
└─────────────────────┘ └──────────────────┘

├─ Jangka Waktu: XX Bulan
├─ Suku Bunga: X.XX% per tahun
├─ Jenis Kredit: KMK
├─ Tujuan Kredit: [dijelaskan]
├─ Masa Tenggang: XX Bulan
└─ Status Kelayakan: [LAYAK / TIDAK LAYAK]
```

### **4. Timeline Persetujuan (✓)**
Menampilkan semua persetujuan dengan:
- Tanggal persetujuan
- Nama approver
- Level approval (analis, kabag_analis, kabag_kredit, kadiv_kredit, direksi)
- Catatan dari approver (jika ada)

---

## Fitur Print

### **Print-to-PDF (Browser Native)**
```
1. Klik tombol 🖨️ Cetak Dokumen
2. Print dialog muncul
3. Pilih "Save as PDF" atau printer fisik
4. Klik "Simpan" atau "Print"
```

### **Styling untuk Cetak**
- ✅ Halaman print-friendly (tanpa sidebar, clean layout)
- ✅ Header bank dengan branding
- ✅ Tata letak landscape-ready
- ✅ Warna dan border optimal untuk cetak
- ✅ Footer dengan timestamp & pengajuan ID

### **Print CSS Features**
```css
/* Responsive untuk cetak */
@media print {
    - Tombol cetak disembunyikan
    - Background clean (putih)
    - Box shadow dihilangkan
    - Page break diatur
}
```

---

## Otorisasi Akses

| Role | Bisa Cetak? | Untuk Status |
|------|-----------|---|
| **analis** | ✅ Ya | disetujui |
| **kabag_analis** | ✅ Ya | disetujui |
| **kabag_kredit** | ❌ Tidak |  |
| **kadiv_kredit** | ❌ Tidak |  |
| **direksi** | ❌ Tidak |  |
| **Superadmin** | ✅ Ya | disetujui |

> **Catatan:** Hanya analis, kabag_analis, dan admin yang bisa mencetak atas permintaan bisnis.

---

## File-File Terkait

| File | Fungsi | Lokasi |
|------|--------|--------|
| **print.php** | Main print page | `/print.php` |
| **detail.php** | Detail view + print button | `/detail.php` |
| **dashboard.php** | Dashboard dengan print di riwayat | `/{role}/dashboard.php` |
| **functions.php** | Authorization checks | `/includes/functions.php` |

---

## Contoh URL

```
Halaman Cetak:
  http://localhost/andrian/bank-kredit/print.php?id=123

Detail Pengajuan (dengan print button):
  http://localhost/andrian/bank-kredit/detail.php?id=123

Dashboard (dengan print button di riwayat):
  http://localhost/andrian/bank-kredit/kadiv_kredit/dashboard.php
  http://localhost/andrian/bank-kredit/kabag_analis/dashboard.php
  http://localhost/andrian/bank-kredit/kabag_kredit/dashboard.php
  http://localhost/andrian/bank-kredit/direksi/dashboard.php
```

---

## Error Handling

### **Dokumen Tidak Bisa Dicetak Jika:**

❌ **Status bukan 'disetujui'**
```
Error: "Dokumen Belum Selesai Diproses"
Message: "Pengajuan baru dapat dicetak setelah semua approvals selesai."
```

❌ **User Role Tidak Diizinkan**
```
Error: "Akses Ditolak"
Message: "Anda tidak memiliki izin untuk mencetak dokumen ini."
```

❌ **Pengajuan Tidak Ditemukan**
```
Error: "Data tidak ditemukan." atau "ID Pengajuan tidak ditemukan."
```

---

## Workflow Visualisasi

```
PERSETUJUAN PENGAJUAN
│
├─→ analis input form
│   ├─→ kabag_analis review
│   ├─→ kabag_kredit review
│   ├─→ kadiv_kredit review
│   ├─→ (jika ≥500M) direksi review
│   │
│   └─→ DISETUJUI ✓ [status='disetujui']
│       │
│       ├─→ [Print Button Muncul] 🖨️
│       └─→ User bisa klik "Cetak Dokumen"
│           ↓
│           [Halaman Print di Tab Baru]
│           ├─→ Data Diri ✓
│           ├─→ Data Pinjaman ✓
│           └─→ Timeline Persetujuan ✓
│               ↓
│               [Ctrl+P untuk Print]
│               ↓
│               [PDF / Printer Fisik]
```

---

## Testing Checklist

- [ ] Akses print.php tanpa login → redirect ke login
- [ ] Akses print.php non-approved status → error message
- [ ] Role kabag_kredit akses print → "Akses Ditolak"
- [ ] Role analis akses print untuk approved → berhasil
- [ ] Tombol cetak di detail.php → hanya muncul untuk disetujui
- [ ] Tombol cetak di dashboard → hanya muncul untuk approved items
- [ ] Print browser dialog → sudah siap untuk PDF/printer
- [ ] Page break & layout → optimal untuk cetak A4

---

## Support

Untuk masalah atau pertanyaan terkait fitur cetak:
1. Periksa status pengajuan → harus 'disetujui'
2. Periksa role pengguna → harus analis, kabag_analis, atau admin
3. Lihat browser console untuk error details
4. Cek file `print.php` untuk authorization & query logic
