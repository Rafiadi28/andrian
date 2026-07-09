# Panduan Cepat - Master Pejabat

**Ringkas:** Manajemen tanda tangan dan stempel pejabat bank yang otomatis terupdate di hasil cetak.

---

## 🚀 Cara Menggunakan

### 1. Akses Admin Interface
**URL:** `bank-kredit/admin/master_pejabat.php`

Anda akan melihat tabel dengan 5 role default (semua belum ditentukan):
- Analis Kredit
- Kepala Subbagian Analis
- Kepala Bagian Kredit
- Kepala Divisi Bisnis
- Direktur Utama

### 2. Tambah atau Update Pejabat

**Klik "Edit"** di row yang ingin diupdate:

| Field | Isi |
|-------|-----|
| Role | Tetap (tidak bisa diubah) |
| Nama Pejabat | Nama lengkap officer |
| Jabatan Resmi | Gelar/posisi resmi |
| Tanda Tangan | Upload file tanda tangan (JPG/PNG) |
| Stempel | Upload file stempel/cap (JPG/PNG) |
| Status | Aktif atau Nonaktif |

**Contoh:**
- Nama: `Budi Santoso`
- Jabatan: `Analis Kredit`
- Upload file: tanda_tangan.jpg, stempel.png
- Status: Aktif

Klik **"Perbarui Pejabat"**

### 3. Verifikasi di Cetak

Buka pengajuan kredit dan klik **CETAK**:
- Signature box akan menampilkan nama pejabat
- Stempel akan terlihat di PDF
- Semua data otomatis dari master (tidak perlu hardcode)

---

## 📋 Tips

✅ **Upload foto yang jelas** - Stempel harus terlihat jelas di PDF
✅ **Gunakan format JPG atau PNG** - Format lainnya tidak support
✅ **Ukuran file max 5MB** - Jangan upload file terlalu besar
✅ **Status = Nonaktif** - Officer tidak akan muncul di signature box
✅ **Update kapan saja** - Cetak berikutnya langsung terlihat

---

## ❓ FAQ

**Q: Bagaimana jika saya lupa upload stempel?**
A: Tidak masalah, tulisan "Stempel & Tanda Tangan" akan ditampilkan sementara.

**Q: Bisa update stempel di tengah pengajuan?**
A: Ya, update master kapan saja, cetak berikutnya otomatis terpakai.

**Q: Bagaimana jika ada perubahan pejabat?**
A: Edit di master, nonaktifkan yang lama, aktivkan yang baru. Selesai!

**Q: Apakah perlu restart aplikasi?**
A: Tidak perlu. Master otomatis terbaca saat print.

---

## 📞 Support

Ada masalah?
1. Cek file tersimpan (klik ✓ icon untuk preview)
2. Pastikan role sudah benar
3. Reload halaman admin jika data tidak update

Jika tetap error, hub. Tim IT untuk check logs di server.

---

**Dokumentasi Lengkap:** Lihat file `MASTER_PEJABAT_DOCUMENTATION.md`
