# QUICK START - SINGKRONISASI ASSESSMENT KEPATUHAN

**Last Updated:** May 23, 2026

---

## 🎯 UNTUK ANALIS

### Langkah 1: Akses Menu Penilaian Kepatuhan
1. Login sebagai **Analis**
2. Di Dashboard, cari tombol/card **"Penilaian Kepatuhan"**
3. Klik → akan membuka halaman daftar pengajuan

### Langkah 2: Lihat Daftar Pengajuan
- Anda akan melihat list pengajuan yang sudah Anda buat
- Kolom "Status Assessment" menunjukkan:
  - 🟢 **Ada** = Assessment sudah ada
  - 🟡 **Belum** = Belum ada assessment
- Klik **"Buat/Edit Assessment"** untuk membuka form

### Langkah 3: Isi Form Assessment
1. **Data Usulan Kredit** (auto-populated, read-only):
   - Nama debitur, KTP, Jenis kredit, Plafon
   - Anda bisa edit "Marketing" field

2. **Compliance Checklist** - Untuk setiap item, pilih:
   - **NA** = Tidak berlaku
   - **Not Comply** = Tidak sesuai
   - **Comply** = Sesuai (default)
   - Tambah keterangan di kolom paling kanan

3. **Fasilitas Kredit Existing** (optional):
   - Tambah data fasilitas kredit existing
   - No Rekening, Tgl Akad, Jatuh Tempo, Kol, Plafon, Saldo
   - Klik "+ Tambah Baris" untuk tambah lebih banyak

4. **Catatan Compliance**:
   - Pilih untuk 3 kategori: Kelengkapan Dokumen, Catatan Pemutus, Pengikatan Kredit
   - Tambah keterangan jika diperlukan

5. **Kesimpulan & Rekomendasi**:
   - Tulis kesimpulan dari assessment
   - Tulis rekomendasi untuk departemen kepatuhan

### Langkah 4: Simpan
- Klik **"SIMPAN ASSESSMENT"**
- Tunggu sampai muncul notifikasi "Assessment berhasil disimpan"
- Anda akan di-redirect kembali ke form (untuk verifikasi data)

---

## 🎯 UNTUK DEPARTEMEN KEPATUHAN

### Langkah 1: Akses Menu Assesmen
1. Login sebagai **Kepatuhan**
2. Klik **"Assesmen"** di sidebar/menu
3. Halaman akan menampilkan "Daftar Pengajuan untuk Assesmen Kepatuhan"

### Langkah 2: Lihat Daftar Pengajuan
- Anda akan lihat list pengajuan dengan status "Sedang Proses" atau lebih tinggi
- Setiap pengajuan menampilkan:
  - ID, Nama Debitur, Jenis Pekerjaan, Plafon, Status
  - Tombol **"Buka Assesmen"**

### Langkah 3: Buka Form Assessment
- Klik **"Buka Assesmen"**
- Form akan muncul dengan data yang sudah di-siapkan analis:
  - ✅ Checklist sudah ter-isi (dari analis)
  - ✅ Fasilitas existing sudah ter-isi (dari analis)
  - ✅ Catatan dari analis sudah ter-isi
  - ✅ Kesimpulan/Rekomendasi dari analis sudah ter-isi

### Langkah 4: Review & Edit (jika perlu)
- **Checklist**: Review pilihan analis, ubah jika diperlukan
- **Fasilitas**: Review data, tambah/edit jika perlu
- **Catatan**: Review dan tambah catatan kepatuhan jika ada
- **Kesimpulan/Rekomendasi**: 
  - Bisa pakai dari analis atau tulis baru
  - Ini adalah kesimpulan FINAL dari departemen kepatuhan

### Langkah 5: Simpan & Selesai
- Klik **"SIMPAN ASSESSMENT"**
- Data Anda tersimpan di database
- Status assessment = **FINAL**
- Data ready untuk proses approval berikutnya

### Bonus: Cetak Assessment
- Klik **"CETAK ASSESSMENT"** untuk print memo internal
- Print akan include semua data yang sudah di-isi

---

## 📊 CONTOH DATA FLOW

```
Analis membuat pengajuan
        ↓
Analis isi form kredita (PPPK/Desa/Umum)
        ↓
Analis akses "Penilaian Kepatuhan"
        ↓
Analis buat Assessment Kepatuhan
    ├─ Isi Checklist Compliance
    ├─ Input Fasilitas Existing
    ├─ Input Catatan Existing
    ├─ Tulis Kesimpulan
    └─ Simpan → Tersimpan di DATABASE
        ↓
Kepatuhan akses "Assesmen Kepatuhan"
        ↓
Kepatuhan klik "Buka Assesmen"
        ↓
Form AUTO-POPULATE dengan data dari Analis
    ├─ Checklist sudah ter-isi ✓
    ├─ Fasilitas sudah ter-isi ✓
    ├─ Catatan sudah ter-isi ✓
    └─ Kesimpulan sudah ter-isi ✓
        ↓
Kepatuhan review & bisa edit jika perlu
        ↓
Kepatuhan klik "SIMPAN ASSESSMENT"
        ↓
Data FINAL tersimpan → Ready untuk Approval
```

---

## ✨ KEUNTUNGAN SISTEM INI

✅ **Mengurangi duplikasi data** - Data input sekali, dipakai berkali-kali  
✅ **Lebih cepat** - Kepatuhan tidak perlu re-input semua data dari analis  
✅ **Lebih akurat** - Data dari analis langsung ter-populate di form kepatuhan  
✅ **Audit trail** - Semua perubahan tercatat dengan timestamp  
✅ **Flexible** - Analis dan Kepatuhan bisa edit kapan saja  

---

## 🆘 TROUBLESHOOTING

### Q: Form tidak tersimpan?
**A:** 
- Pastikan CSRF token valid (mungkin page expire)
- Reload page dan coba lagi
- Check browser console untuk error message

### Q: Data tidak ter-populate di form kepatuhan?
**A:**
- Pastikan analis sudah simpan assessment terlebih dahulu
- Refresh page untuk reload data
- Check ID pengajuan sudah benar

### Q: Bisa nge-edit data yang sudah disimpan?
**A:**
- ✅ Ya! Analis bisa edit assessment yang sudah dibuat
- ✅ Kepatuhan juga bisa edit assessment
- Data lama akan di-replace dengan data baru saat disimpan

### Q: Apakah data assessment terlihat di print/report?
**A:**
- ✅ Ya, pada halaman detail pengajuan
- ✅ Ya, pada memo cetak assesmen
- Data assessment ditampilkan dengan filtering (hanya comply/not comply, NA tidak ditampilkan)

---

## 📞 SUPPORT

Jika ada pertanyaan atau masalah teknis, hubungi:
- **Admin/Developer** untuk troubleshooting teknis
- **Manajemen** untuk kebijakan assessment

---

## 📋 CHECKLIST BEFORE GO-LIVE

- [ ] Test analis bisa akses "Penilaian Kepatuhan"
- [ ] Test analis bisa membuat assessment baru
- [ ] Test analis bisa edit assessment existing
- [ ] Test kepatuhan bisa akses "Assesmen Kepatuhan"
- [ ] Test kepatuhan bisa melihat data dari analis
- [ ] Test kepatuhan bisa edit assessment
- [ ] Test form submit (SIMPAN button)
- [ ] Test print functionality
- [ ] Test database (check `assessment_kepatuhan` table punya data)
- [ ] Test CSRF token validation
- [ ] Test role-based access control

---

Semoga bermanfaat! 🎉
