# LAPORAN KOMPARASI JENIS PENGAJUAN: UMUM vs KRETAMAS

**Tanggal**: 17 April 2026  
**Sistem**: Bank Kredit - Sistem Persetujuan Kredit  
**Dokumen**: Analisis Perbandingan Jenis Pengajuan Kredit

---

## 📊 RINGKASAN EKSEKUTIF

Sistem persetujuan kredit mendukung 6 jenis pengajuan kredit yang berbeda:
1. **UMUM** - Kredit Usaha / Umum
2. **PPPK** - Pegawai Pemerintah dengan Perjanjian Kerja
3. **PERANGKAT DESA** - Aparatur Desa
4. **KPR** - Kredit Pemilikan Rumah
5. **KRETAMAS** - Kredit Tani Masyarakat (Community Agricultural Credit)
6. **CASHCOLATERAL** - Kredit dengan Jaminan Deposito/Tabungan

Laporan ini fokus pada perbandingan mendalam antara **UMUM** dan **KRETAMAS**.

---

## 🎯 PERBANDINGAN DETAIL

### 1. IDENTITAS DAN KLASIFIKASI

| Aspek | UMUM | KRETAMAS |
|-------|------|---------|
| **Nama Lengkap** | Kredit Usaha / Umum | Kredit Tani Masyarakat |
| **Kategori** | Kredit Usaha / Umum | Kredit Khusus (Sektor Pertanian) |
| **Kode Sistem** | `umum` | `kretamas` |
| **Target Pasar** | Pengusaha/Wiraswastawan Umum | Petani/Usaha Pertanian Masyarakat |
| **Ikon/Logo** | 💼 | 🌾 |
| **Warna Tema** | Biru (#2563eb) | Oranye (#d97706) |

---

### 2. FORM PENGUMPULAN DATA

#### Form yang Digunakan

| Parameter | UMUM | KRETAMAS |
|-----------|------|---------|
| **File Form** | `form_umum.php` | `form_umum.php` |
| **Form Dinamis** | Ya | Ya |
| **Template Khusus** | Tidak | Tidak |
| **Validasi Khusus** | Standar | Standar (sama dengan UMUM) |

**Catatan**: Kedua jenis pengajuan menggunakan **FORM YANG SAMA** (form_umum.php), tetapi diidentifikasi melalui parameter `jenis_pekerjaan`.

---

### 3. DATA PEMOHON (NASABAH)

#### Data Identitas Dasar (Sama untuk Kedua Jenis)

```sql
Kolom Dasar:
- nama_debitur          : Nama lengkap pemohon
- nik                   : Nomor Identitas Kependudukan (16 digit)
- id_nasabah            : ID Nasabah Bank
- npwp                  : Nomor Pokok Wajib Pajak
- status_perkawinan     : Status (lajang/menikah/dll)
- tempat_lahir          : Tempat lahir
- tanggal_lahir         : Tanggal lahir
- no_hp                 : Nomor HP (10-15 digit)
- jumlah_tanggungan     : Jumlah tanggungan
- nama_ibu_kandung      : Nama ibu kandung (untuk verifikasi)
```

#### Data Alamat (Sama untuk Kedua Jenis)

```sql
- alamat_ktp            : Alamat sesuai KTP
- dukuh                 : Dukuh/Rukun Warga
- desa                  : Nama Desa
- kecamatan             : Kecamatan
- kota_kabupaten        : Kota/Kabupaten
- alamat_domisili       : Alamat Domisili (jika berbeda)
```

#### Data Pekerjaan

| Kolom | UMUM | KRETAMAS | Catatan |
|-------|------|---------|---------|
| `pekerjaan` | Wajib | Wajib | Deskripsi jenis usaha |
| `alamat_pekerjaan` | Wajib | Wajib | Lokasi usaha/pertanian |
| `nama_usaha` | Wajib | Wajib | Nama bisnis/usaha tani |
| `bidang_usaha` | Wajib | Wajib | Sektor (UMUM: berbagai; KRETAMAS: Pertanian) |
| `lama_usaha` | Wajib | Wajib | Lamanya menjalankan usaha |

---

### 4. DATA USAHA DAN KEUANGAN

#### Struktur Kolom Keuangan (IDENTIK)

```sql
Penghasilan:
- omset_per_bulan           : Omset bulanan (pendapatan kotor)
- laba_bersih               : Laba bersih setelah biaya
- biaya_operasional         : Total biaya operasional

Rincian Biaya:
- biaya_bahan_baku          : Biaya bahan baku
- biaya_gaji                : Biaya gaji karyawan
- biaya_listrik             : Biaya listrik/energi
- biaya_air                 : Biaya air
- biaya_sewa                : Biaya sewa lokasi
- biaya_transportasi        : Biaya transportasi
- biaya_lainnya             : Biaya operasional lainnya

Pengeluaran Pribadi:
- biaya_hidup               : Biaya hidup bulanan
- cicilan_lain              : Cicilan/hutang lain

Analisis Kapasitas:
- repayment_capacity        : Kemampuan pembayaran (RPC = Net CF × 75%)
- net_cashflow              : Arus kas bersih (Penghasilan - Pengeluaran)
- angsuran_diajukan         : Angsuran kredit yang diajukan
- status_kelayakan          : Status kelayakan (LAYAK/TIDAK LAYAK)
```

#### Perbedaan Data Usaha

| Metrik | UMUM | KRETAMAS | Catatan |
|--------|------|---------|---------|
| **Jenis Usaha yang Diizinkan** | Semua sektor (pertanian, perdagangan, jasa, manufaktur, dll) | Khusus Pertanian (tanaman pangan, perkebunan, ternak, dll) | KRETAMAS lebih spesifik |
| **Analisis Biaya Operasional** | Sesuai jenis bisnis | Spesifik pertanian (benih, pupuk, pestisida, dll) | KRETAMAS lebih fokus |
| **Basis Perhitungan RPC** | Omset - Biaya Operasional - Biaya Hidup | Sama (Omset - Biaya Operasional - Biaya Hidup) | Metode identik |
| **Verifikasi Usaha** | Umum (surat keterangan, bukti usaha) | Umum + verifikasi lahan/pertanian | Mungkin lebih ketat |

**Rumus Kapasitas Pembayaran (IDENTIK untuk Kedua Jenis):**

```
Net Cashflow = Penghasilan Usaha - Biaya Operasional - Biaya Hidup - Cicilan Lain
Repayment Capacity (RPC) = Net Cashflow × 75%
Status Kelayakan = LAYAK jika RPC ≥ Angsuran Diajukan
```

---

### 5. JAMINAN KREDIT (AGUNAN)

#### Jenis Jaminan yang Didukung (SAMA)

```
Sistem mendukung beberapa jenis jaminan:
1. Tanah & Bangunan (jaminan_tanah_bangunan)
2. Kendaraan (jaminan_kendaraan)
3. Emas/Logam Mulia (jaminan_emas)
```

| Jenis Jaminan | UMUM | KRETAMAS | Tabel Database |
|---------------|------|---------|-----------------|
| Tanah & Bangunan | Ya | Ya | `jaminan_tanah_bangunan` |
| Kendaraan | Ya | Ya | `jaminan_kendaraan` |
| Emas | Ya | Ya | `jaminan_emas` |

#### Field Jaminan Tanah & Bangunan (Detail yang Sama)

```sql
Informasi Umum:
- alamat_agunan         : Alamat properti jaminan
- jenis_surat           : Jenis surat tanah (SHM, GIIK, AJB, dll)
- nomor_surat           : Nomor surat
- atas_nama             : Atas nama pemilik
- masa_covernote        : Masa berlaku cover note

Spesifikasi Tanah:
- luas_tanah            : Luas tanah (m²)
- luas_tanah_sppt       : Luas tanah menurut SPPT
- harga_tanah_sppt      : Harga tanah menurut SPPT
- harga_tanah_pasar     : Harga tanah pasar

Penilaian Tanah:
- nilai_wajar_sppt      : Nilai wajar SPPT
- nilai_taksasi_sppt    : Nilai taksasi SPPT
- nilai_likuidasi_sppt  : Nilai likuidasi SPPT
- nilai_pasar (tanah)   : Nilai pasar tanah

Spesifikasi Bangunan:
- luas_bangunan         : Luas bangunan utama (m²)
- luas_bangunan_2       : Luas bangunan tambahan (m²)
- harga_bangunan_m2     : Harga per m² bangunan

Penilaian Bangunan:
- nilai_pasar           : Nilai pasar bangunan
- nilai_taksasi         : Nilai taksasi bangunan
- nilai_likuidasi       : Nilai likuidasi bangunan
```

---

### 6. PROSES PERSETUJUAN (ALUR APPROVAL)

#### Tahap Approval (IDENTIK)

```
UMUM:
Draft → Diajukan → KaSubag Analis → KaBag Analis → KaBag Kredit → 
KaDiv Kredit → Direksi → Disetujui → Proses → Selesai

KRETAMAS:
Draft → Diajukan → KaSubag Analis → KaBag Analis → KaBag Kredit → 
KaDiv Kredit → Direksi → Disetujui → Proses → Selesai
```

| Parameter | UMUM | KRETAMAS |
|-----------|------|---------|
| **Tahap Approval** | 7 level | 7 level (IDENTIK) |
| **Waktu Approval** | Bergantung proses | Bergantung proses |
| **Revisi Dimungkinkan** | Ya | Ya |
| **Penolakan Dimungkinkan** | Ya | Ya |
| **Resubmit Dimungkinkan** | Ya | Ya |

#### Status Pengajuan (IDENTIK)

```
draft               : Draft awal
diajukan            : Sudah diajukan
kasubag             : Level KaSubag Analis
kabag               : Level KaBag Analis
kadiv               : Level KaBag/KaDiv Kredit
direksi             : Level Direksi
revisi              : Permintaan revisi
ditolak             : Pengajuan ditolak
diajukan_ulang      : Resubmit setelah revisi/penolakan
disetujui           : Disetujui semua level
proses              : Dalam proses pencairan
selesai             : Selesai/Pencairan selesai
```

---

### 7. ANALISIS TINGKAT RISIKO

#### Faktor Risiko Umum (SAMA)

| Faktor | UMUM | KRETAMAS | Kerentanan |
|--------|------|---------|------------|
| Volatilitas Pendapatan | Medium | Tinggi | KRETAMAS lebih tergantung cuaca |
| Stabilitas Usaha | Medium-Tinggi | Medium | KRETAMAS bergantung musim |
| Liquidity Nasabah | Medium | Medium-Rendah | KRETAMAS mungkin lebih rendah |
| Regulasi Sektor | Umum | Pertanian (subsidi, regulasi khusus) | KRETAMAS lebih teregulasi |
| Jaminan Collateral | Tergantung | Tergantung | Sama |

#### Indikator Khusus KRETAMAS

```
Faktor Tambahan KRETAMAS:
1. Musim Tanam         : Penting untuk cash flow planning
2. Cuaca/Iklim         : Risiko gagal panen
3. Harga Komoditas     : Fluktuasi harga hasil panen
4. Jenis Tanaman       : Mempengaruhi ROI dan risiko
5. Luas Lahan          : Kapasitas produksi
6. Irigasi/Lahan Basah : Aksesibilitas air
```

---

### 8. FITUR FORMULIR

#### Tab/Section di Form (SAMA untuk Kedua Jenis)

```
Tab Pemohon:
- Data Identitas Dasar
- Data Alamat
- Data Pasangan (jika menikah)
- Data Kontak

Tab Usaha:
- Informasi Usaha
- Rincian Biaya Operasional
- Pendapatan & Laba
- Pengeluaran Pribadi

Tab Penghasilan: (TIDAK untuk UMUM/KRETAMAS)
- Spesifik untuk PPPK dan Perangkat Desa

Tab Jaminan:
- Jaminan Tanah & Bangunan
- Jaminan Kendaraan
- Jaminan Emas

Tab Analisis:
- Rasio Keuangan
- Penilaian Risiko (5C Analysis)
- Neraca Keuangan

Tab Pengajuan:
- Detail Kredit yang Diajukan
- Tujuan Kredit
- Dokumen Pendukung
```

---

### 9. VALIDASI DATA

#### Aturan Validasi (IDENTIK)

| Field | Validasi | Berlaku UMUM | Berlaku KRETAMAS |
|-------|----------|--------------|------------------|
| NIK | 16 digit angka | ✓ | ✓ |
| No HP | 10-15 digit angka | ✓ | ✓ |
| Nama Debitur | Non-empty, uppercase | ✓ | ✓ |
| Alamat KTP | Non-empty, uppercase | ✓ | ✓ |
| Tanggal Lahir | Format DD/MM/YYYY | ✓ | ✓ |
| Omset | Positif atau 0 | ✓ | ✓ |
| Biaya | Positif atau 0 | ✓ | ✓ |
| Jaminan | Nilai likuidasi > 0 | ✓ | ✓ |

#### Validasi Unik

```
NIK Uniqueness:
- Tidak boleh ada NIK yang sama di pengajuan berbeda
- Berlaku untuk UMUM dan KRETAMAS

Pinjaman Ke:
- Minimal 1 (pinjaman pertama)
- Tidak bisa diubah setelah approval disetujui
```

---

### 10. INTEGRASI SISTEM

#### Database (SAMA)

```sql
Tabel Utama:
- pengajuan_kredit          : Record pengajuan (untuk semua jenis)
- jaminan_tanah_bangunan    : Jaminan properti
- jaminan_kendaraan         : Jaminan kendaraan
- jaminan_emas              : Jaminan emas
- approval_kredit           : History approval
- audit_log                 : Log aktivitas

Kolom Differentiator:
- jenis_pekerjaan           : 'umum' atau 'kretamas' (dan lainnya)
```

#### Workflow Integration

```
Input Flow:
analis/pilih_jenis_pekerjaan.php 
  → analis/input.php?jenis=umum|kretamas 
  → analis/form_umum.php
  → analis/save_section.php (AJAX)

View Flow:
detail.php → Tampil berdasarkan jenis_pekerjaan
print.php  → Layout adaptif berdasarkan jenis

Approval Flow:
Sama untuk semua jenis → approval workflow identik
```

---

## 📈 PERBANDINGAN MATRIKS LENGKAP

| Aspek | UMUM | KRETAMAS | Status Perbedaan |
|-------|------|---------|------------------|
| **Form Input** | form_umum.php | form_umum.php | ✓ SAMA |
| **Database Tabel** | pengajuan_kredit | pengajuan_kredit | ✓ SAMA |
| **Field Data Pemohon** | 30+ field | 30+ field | ✓ SAMA |
| **Field Data Usaha** | 15+ field | 15+ field | ✓ SAMA |
| **Jenis Jaminan** | 3 jenis | 3 jenis | ✓ SAMA |
| **Tahap Approval** | 7 level | 7 level | ✓ SAMA |
| **Status Pengajuan** | 12 status | 12 status | ✓ SAMA |
| **Rumus RPC** | Net CF × 75% | Net CF × 75% | ✓ SAMA |
| **Validasi NIK** | Unique | Unique | ✓ SAMA |
| **Validasi Nomor HP** | 10-15 digit | 10-15 digit | ✓ SAMA |
| **Tab Penghasilan** | Tidak ada | Tidak ada | ✓ SAMA |
| **Dukungan Pasangan** | Ya | Ya | ✓ SAMA |
| **Upload File** | Ya | Ya | ✓ SAMA |
| **Audit Log** | Ya | Ya | ✓ SAMA |
| **Kategori Sektor** | Umum (semua) | Pertanian | ✗ BERBEDA |
| **Target Pasar** | Entrepreneur | Petani | ✗ BERBEDA |
| **Ikon Visual** | 💼 | 🌾 | ✗ BERBEDA |
| **Warna Tema** | Biru | Oranye | ✗ BERBEDA |

---

## 🔍 ANALISIS PERBEDAAN UTAMA

### 1. ASPEK OPERASIONAL

**UMUM:**
- Target pasar luas (semua entrepreneur non-pegawai)
- Biaya operasional sangat variatif tergantung jenis usaha
- Penghasilan stabil sepanjang tahun (umumnya)
- Risiko moderate

**KRETAMAS:**
- Target pasar spesifik (petani/agribisnis)
- Biaya operasional terstruktur (benih, pupuk, pestisida, dll)
- Penghasilan musiman (seasonal)
- Risiko tinggi (cuaca, harga komoditas)

### 2. ASPEK TEKNIS

**Kedua jenis pada dasarnya IDENTIK dalam:**
- Struktur database
- Form pengumpulan data
- Proses approval
- Validasi data
- Rumus perhitungan

**Perbedaan hanya pada:**
- Konteks bisnis dan target pasar
- Deskripsi jenis usaha yang diizinkan
- Guidance dan tips di interface

### 3. ASPEK REGULASI

**KRETAMAS mungkin memiliki:**
- Persyaratan khusus dari OJK (Otoritas Jasa Keuangan)
- Target penyaluran kredit khusus sektor pertanian
- Bunga kompetitif / subsidi pemerintah
- Pelaporan khusus ke bank sentral

---

## 💡 REKOMENDASI

### Untuk Operasional

1. **Batch Processing**: Karena struktur identik, dapat dibuat batch approval untuk UMUM+KRETAMAS berdasarkan risk profile
2. **Reporting Konsolidasi**: Dashboard dapat menampilkan statistik gabungan UMUM vs KRETAMAS
3. **Training Staff**: Staff approval perlu memahami karakteristik khusus KRETAMAS (musiman, risiko iklim)
4. **Due Diligence Lebih Ketat KRETAMAS**: Verifikasi lahan, musim tanam, jenis komoditas

### Untuk Pengembangan Sistem

1. **Field Tambahan KRETAMAS** (rekomendasi):
   - `musim_tanam` : Musim tanam (musim hujan/kemarau)
   - `jenis_komoditas` : Jenis tanaman/ternak
   - `luas_lahan` : Luas lahan usaha tani
   - `potensi_hasil` : Potensi hasil/panen
   - `harga_ekspektasi` : Harga ekspektasi hasil

2. **Dashboard Khusus KRETAMAS**:
   - Monitoring musiman
   - Analisis harga komoditas
   - Pemetaan risiko iklim

3. **Report Khusus**:
   - Laporan perbandingan UMUM vs KRETAMAS
   - Laporan risiko sektor pertanian
   - Laporan compliance sektor pertanian

---

## 📋 CHECKLIST IMPLEMENTASI

### Untuk Analis saat Input KRETAMAS:

- [ ] Pastikan bidang usaha "Pertanian" / "Agribisnis"
- [ ] Verifikasi lahan/lokasi usaha tani
- [ ] Dokumentasi jenis komoditas yang dibudidayakan
- [ ] Analisis musim tanam dan cash flow musiman
- [ ] Perhitungan RPC lebih konservatif (cuaca, pasar)
- [ ] Jaminan sebaiknya berupa tanah/properti
- [ ] Persyaratan dokumen: sertifikat tanah, bukti pertanian

### Untuk Approver:

- [ ] Teliti analisis seasonality cash flow untuk KRETAMAS
- [ ] Verifikasi kelayakan RPC dengan margin risiko lebih tinggi
- [ ] Pertimbangkan exposure ke sektor pertanian
- [ ] Compliance check: apakah kredit sesuai ketentuan OJK
- [ ] Monitoring post-disbursement: tracking musim dan hasil panen

---

## 📚 REFERENSI FILE TERKAIT

```
Sistem File:
- analis/form_umum.php              : Form input untuk UMUM & KRETAMAS
- analis/pilih_jenis_pekerjaan.php  : Halaman pemilihan jenis
- analis/save_section.php           : AJAX handler penyimpanan data
- analis/input.php                  : Routing ke form yang tepat
- database.sql                      : Skema database
- detail.php                         : Detail pengajuan
- print.php                          : Cetak pengajuan
```

---

## 📊 STATISTIK SISTEM

**Jumlah Jenis Pengajuan**: 6 (umum, pppk, perangkat_desa, kpr, kretamas, cashcolateral)

**Jenis yang Menggunakan form_umum.php**: 
- umum ✓
- kretamas ✓
- kpr ✓
- cashcolateral ✓
(Note: pppk & perangkat_desa menggunakan form spesifik)

**Kolom Pengajuan di Database**: 40+ kolom

**Tabel Relasi Pengajuan**: 4 tabel jaminan + 2 tabel approval/audit

---

## 🎯 KESIMPULAN

**UMUM dan KRETAMAS adalah jenis pengajuan kredit yang:**

✓ **Identik secara teknis** - Menggunakan form, database, workflow yang sama  
✗ **Berbeda secara konseptual** - Target market, jenis usaha, profil risiko berbeda  
⚠️ **Perlu perhatian khusus** - KRETAMAS butuh expertise di sektor pertanian  

**Rekomendasi**: Sistem saat ini sudah fleksibel cukup baik. Untuk optimalisasi, tambahkan field khusus pertanian dan dashboard monitoring KRETAMAS yang lebih sophisticated.

---

**Document Status**: ✓ FINAL  
**Last Updated**: 17 April 2026  
**Prepared by**: System Analysis Team
