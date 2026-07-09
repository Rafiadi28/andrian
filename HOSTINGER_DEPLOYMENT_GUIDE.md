# 🚀 Panduan Deployment Hostinger — Aplikasi Bank Kredit
**Versi:** 1.0 | **Tanggal:** April 2026 | **Database:** MySQL (`bank_kredit_db`)

---

## 📌 Informasi Proyek

| Item | Detail |
|---|---|
| Nama Aplikasi | Sistem Analisa Kredit Bank |
| Framework | PHP Native (PDO + MySQL) |
| Database | MySQL — `bank_kredit_db` (11 tabel) |
| Server Lokal | Laragon (`localhost`) |
| Target Hosting | **Hostinger** (Shared Hosting) |

---

## 🛒 BAGIAN 1 — Beli Paket Hostinger

### Langkah 1.1 — Buka Website Hostinger

1. Buka browser → pergi ke **https://www.hostinger.co.id**
2. Klik tombol **"Mulai Sekarang"** atau **"Hosting Web"**

### Langkah 1.2 — Pilih Paket

Rekomendasi paket untuk aplikasi ini:

| Paket | Harga/Bulan | Disk | DB | Cocok |
|---|---|---|---|---|
| **Premium Web Hosting** | ~Rp 15.900 | 100 GB | Unlimited | ✅ Direkomendasikan |
| Business Web Hosting | ~Rp 25.900 | 200 GB | Unlimited | ✅ Jika traffic tinggi |

> **Pilih minimal paket Premium** — paket Single hanya 1 database.

### Langkah 1.3 — Daftar & Bayar

1. Klik **"Tambah ke Keranjang"**
2. Pilih durasi: **12 bulan** (lebih hemat)
3. Masukkan domain:
   - Jika punya domain sendiri → masukkan domain
   - Belum punya → pilih **"Gunakan Subdomain Hostinger"** (gratis: `namamu.hostingersite.com`)
4. Daftar akun → bayar
5. Tunggu email konfirmasi aktivasi

---

## 💾 BAGIAN 2 — Export Database dari Laragon

### Langkah 2.1 — Buka phpMyAdmin Lokal

1. Buka browser → akses **`http://localhost/phpmyadmin`**
2. Login (default Laragon: username `root`, password kosong)

### Langkah 2.2 — Export Database

1. Klik database **`bank_kredit_db`** di sidebar kiri
2. Klik tab **Export** di menu atas
3. Pilih method: **"Custom - display all possible options"**
4. **Pengaturan Export:**
   - ✅ **Format:** SQL
   - ✅ Centang semua tabel (11 tabel)
   - ✅ **Structure:** Add DROP TABLE / VIEW
   - ✅ **Data:** Complete inserts
   - ✅ **Output:** Save output to a file
   - ✅ **Compression:** None (atau gzip jika file besar)
5. Klik **"Go"** → file `bank_kredit_db.sql` terunduh

> 📁 Simpan file SQL ini di lokasi yang mudah ditemukan, misalnya Desktop.

### Langkah 2.3 — Export Via Command Line (Alternatif)

Buka **Terminal Laragon** dan jalankan:

```bash
# Export database ke file SQL
mysqldump -u root bank_kredit_db > D:\backup\bank_kredit_db.sql

# Verifikasi file terbuat
dir D:\backup\bank_kredit_db.sql
```

---

## 📁 BAGIAN 3 — Persiapkan File Aplikasi

### Langkah 3.1 — Edit `config/database.php` Sebelum Upload

Buka file `bank-kredit/config/database.php` dan ubah sesuai data Hostinger:

**SEBELUM (konfigurasi lokal):**
```php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'bank_kredit_db';

define('BASE_URL', '/andrian/bank-kredit');
$bkForceProduction = false;
```

**SESUDAH (konfigurasi Hostinger — isi sesuai data Anda):**
```php
$host = 'localhost';
$user = 'u123456789_bank';      // ← dari Hostinger: MySQL Users
$pass = 'PasswordKuat@2026';    // ← password yang Anda buat
$db   = 'u123456789_bankkredit'; // ← dari Hostinger: nama database

// Jika domain root (contoh: bankku.com) → kosongkan BASE_URL
define('BASE_URL', '');

// Jika di subfolder (contoh: bankku.com/kredit) → isi subfolder
// define('BASE_URL', '/kredit');

$bkForceProduction = true;      // ← AKTIFKAN untuk production
```

### Langkah 3.2 — Siapkan `.htaccess` Production

Ganti isi file `bank-kredit/.htaccess` dengan konfigurasi berikut:

```apache
Options -Indexes
ServerSignature Off

# ============================================================
# KEAMANAN: Blokir akses ke file sensitif
# ============================================================
<FilesMatch "\.(sql|log|env|md|txt|ini|sh)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Blokir skrip migrasi & utilitas
<IfModule mod_authz_core.c>
  <FilesMatch "^(migration_trigger|migrate_agunan_fix|migrate_bank_lain|_add_covernote_col)\.php$">
    Require all denied
  </FilesMatch>
</IfModule>

# Blokir akses ke folder sensitif
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^config/ - [F,L]
    RewriteRule ^logs/ - [F,L]
    RewriteRule ^backups/ - [F,L]
    RewriteRule ^docs/ - [F,L]
</IfModule>

# ============================================================
# PHP Settings untuk Production
# (Dibungkus IfModule agar tidak Error 500 di Hostinger/LiteSpeed)
# ============================================================
<IfModule mod_php.c>
    php_flag display_errors Off
    php_flag log_errors On
    php_value error_log logs/php_error.log
    php_value upload_max_filesize 10M
    php_value post_max_size 12M
    php_value max_execution_time 60
    php_value session.cookie_httponly 1
    php_value session.use_strict_mode 1
</IfModule>
<IfModule mod_php7.c>
    php_flag display_errors Off
    php_flag log_errors On
    php_value error_log logs/php_error.log
    php_value upload_max_filesize 10M
    php_value post_max_size 12M
    php_value max_execution_time 60
    php_value session.cookie_httponly 1
    php_value session.use_strict_mode 1
</IfModule>

# ============================================================
# HTTPS Redirect (aktifkan setelah SSL terpasang di Hostinger)
# ============================================================
# <IfModule mod_rewrite.c>
#     RewriteEngine On
#     RewriteCond %{HTTPS} off
#     RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
# </IfModule>
```

### Langkah 3.3 — Zip Semua File Aplikasi

1. Buka Windows Explorer
2. Masuk ke `D:\laragon\www\andrian\`
3. **Klik kanan** folder `bank-kredit` → **Send to** → **Compressed (zipped) folder**
4. Hasilnya: `bank-kredit.zip`

---

## 🌐 BAGIAN 4 — Setup di Hostinger hPanel

### Langkah 4.1 — Login ke hPanel Hostinger

1. Buka **https://hpanel.hostinger.com**
2. Login dengan akun Hostinger Anda
3. Klik **"Kelola"** pada hosting yang sudah dibeli

### Langkah 4.2 — Buat Database MySQL

1. Di hPanel → cari menu **"Database"** → klik **"MySQL Databases"**
2. Klik **"Create New Database"**
3. Isi form:
   - **Database Name:** `bankkredit`
   - **Username:** `bank` (akan jadi `u123456789_bank`)
   - **Password:** buat password kuat (min. 8 karakter, huruf+angka+simbol)
4. Klik **"Create"**

> 📝 **Catat informasi ini:**
> - DB Host: `localhost`
> - DB Name: `u123456789_bankkredit` (perhatikan prefix angka!)
> - DB Username: `u123456789_bank`
> - DB Password: password yang Anda buat

### Langkah 4.3 — Import Database via phpMyAdmin Hostinger

1. Di hPanel → **"Database"** → **"phpMyAdmin"**
2. Klik **"Enter phpMyAdmin"**
3. Di panel kiri klik database **`u123456789_bankkredit`**
4. Klik tab **"Import"**
5. Klik **"Choose File"** → pilih file `bank_kredit_db.sql`
6. Klik **"Go"**
7. Tunggu hingga muncul: ✅ **"Import has been successfully finished"**

---

## 📤 BAGIAN 5 — Upload File ke Hostinger

### Langkah 5.1 — Via File Manager Hostinger

1. Di hPanel → **"File"** → **"File Manager"**
2. Masuk ke folder **`public_html`**
3. Klik **"Upload"** di toolbar atas
4. Pilih file `bank-kredit.zip`
5. Tunggu upload selesai
6. Klik kanan file ZIP → **"Extract"**
7. Ekstrak ke `public_html/`

> Setelah ekstrak, struktur folder akan menjadi:
> ```
> public_html/
> └── bank-kredit/
>     ├── config/
>     ├── auth/
>     ├── analis/
>     ├── index.php
>     └── ...
> ```
> Akses via: `https://namadomain.com/bank-kredit`

**Atau jika ingin di root domain** (tanpa subfolder):
- Pindahkan **semua isi** folder `bank-kredit/` langsung ke `public_html/`
- Akses via: `https://namadomain.com`

### Langkah 5.2 — Via FTP FileZilla (Alternatif)

1. Download FileZilla → **https://filezilla-project.org**
2. Di hPanel → **"Advanced"** → **"FTP Accounts"** → catat data FTP
3. Buka FileZilla → isi:
   - **Host:** `ftp.namadomain.com`
   - **Username:** username FTP dari hPanel
   - **Password:** password FTP
   - **Port:** `21`
4. Klik **"Quickconnect"**
5. Drag folder `bank-kredit` dari kiri (lokal) ke `public_html` di kanan (server)

---

## ✏️ BAGIAN 6 — Edit Config di Server

### Langkah 6.1 — Edit `database.php` via File Manager

1. Di File Manager Hostinger → masuk ke `public_html/bank-kredit/config/`
2. Klik kanan `database.php` → **"Edit"**
3. Ubah kredensial sesuai database Hostinger (dari Langkah 4.2)
4. Klik **"Save & Close"**

### Langkah 6.2 — Buat Folder `logs` dan `uploads`

1. Di File Manager → masuk ke `public_html/bank-kredit/`
2. Buat folder `logs` jika belum ada → set permission **755**
3. Pastikan folder `assets/uploads/` ada → set permission **755**

---

## 🔒 BAGIAN 7 — Keamanan & Ganti Password User

### Langkah 7.1 — Reset Password Semua User Default

Di phpMyAdmin Hostinger, jalankan SQL berikut:

> ⚠️ **7 user default** saat ini menggunakan password `password` — SANGAT BERBAHAYA!

**Cara 1: Ganti via SQL (langsung di phpMyAdmin)**
```sql
-- Contoh: ganti password admin menjadi "Admin@Bank2026"
-- Buat hash bcrypt baru di: https://bcrypt-generator.com

UPDATE users SET password = '$2y$10$HASH_BARU_ANDA_DISINI'
WHERE username = 'admin';

-- Ulangi untuk setiap user:
-- analis, kasubag_analis, kabag_kredit, kadiv_kredit, direksi, kepatuhan
```

**Cara 2: Ganti via Aplikasi (lebih mudah)**
1. Login sebagai `admin` (password: `password`)
2. Masuk ke menu **Admin → Manajemen User**
3. Edit setiap user → ganti password → simpan
4. Logout → login ulang dengan password baru

### Langkah 7.2 — Aktifkan SSL (HTTPS) di Hostinger

1. Di hPanel → **"Advanced"** → **"SSL"**
2. Klik **"Install SSL"** pada domain Anda
3. Pilih **"Let's Encrypt"** (gratis)
4. Klik **"Install"** → tunggu beberapa menit
5. Setelah aktif, buka `config/database.php` → hapus komentar pada bagian HTTPS redirect di `.htaccess`

---

## ✅ BAGIAN 8 — Verifikasi Final

### Checklist Testing

| # | Test | Cara Cek | Status |
|---|---|---|---|
| 1 | Halaman login muncul | Buka URL aplikasi | ⬜ |
| 2 | Login berhasil | Login sebagai admin | ⬜ |
| 3 | Dashboard tampil | Cek menu sidebar | ⬜ |
| 4 | Input pengajuan baru | Buat pengajuan kredit | ⬜ |
| 5 | Data tersimpan di DB | Cek phpMyAdmin | ⬜ |
| 6 | Alur approval | Proses approval analis → direksi | ⬜ |
| 7 | Upload file | Upload dokumen/foto | ⬜ |
| 8 | Logout berfungsi | Klik logout | ⬜ |
| 9 | HTTPS aktif | Cek ikon gembok di browser | ⬜ |
| 10 | Folder logs aman | Akses `domain.com/bank-kredit/logs/` → harus 403 | ⬜ |

---

## 🛠️ Troubleshooting Umum

### Error: "Database connection failed"
- **Penyebab:** Kredensial DB salah di `config/database.php`
- **Solusi:** Cek ulang username, password, dan nama database di hPanel

### Error: "500 Internal Server Error"
- **Penyebab:** Error PHP tersembunyi, biasanya `.htaccess` bermasalah
- **Solusi:** Sementara hapus baris `php_flag display_errors Off` untuk lihat error aslinya

### Error: "403 Forbidden"
- **Penyebab:** Permission folder salah
- **Solusi:** Set permission `public_html/bank-kredit/` dan semua subfolder ke **755**, file ke **644**

### Upload File Gagal
- **Penyebab:** Folder `uploads/` tidak ada atau permission salah
- **Solusi:** Buat folder `assets/uploads/` → set permission **755**

### Halaman Kosong (Blank White)
- **Penyebab:** Fatal PHP error
- **Solusi:** Cek `logs/php_error.log` di server, atau sementara set `display_errors = On`

---

## 📊 Ringkasan Konfigurasi Production

```
┌─────────────────────────────────────────────┐
│         KONFIGURASI HOSTINGER FINAL          │
├─────────────────┬───────────────────────────┤
│ Host            │ localhost                  │
│ DB Name         │ u123456789_bankkredit      │
│ DB User         │ u123456789_bank            │
│ DB Password     │ [password kuat Anda]       │
│ BASE_URL        │ '' atau '/bank-kredit'     │
│ BK_PRODUCTION   │ true                       │
│ SSL/HTTPS       │ Let's Encrypt (gratis)     │
│ PHP Version     │ 8.1+ (pilih di hPanel)     │
├─────────────────┴───────────────────────────┤
│ URL Akses: https://namadomain.com/bank-kredit│
└─────────────────────────────────────────────┘
```

---

## 📞 Referensi & Bantuan

| Sumber | Link |
|---|---|
| hPanel Hostinger | https://hpanel.hostinger.com |
| Dokumentasi Hostinger | https://support.hostinger.com |
| Live Chat Support | Di dalam hPanel → ikon chat |
| bcrypt Generator | https://bcrypt-generator.com |
| FileZilla FTP | https://filezilla-project.org |
| phpMyAdmin | Tersedia di hPanel → Database |

---

*Laporan ini dibuat berdasarkan analisis proyek `bank-kredit` — Hostinger Deployment Guide v1.0*  
*Dibuat: April 2026*
