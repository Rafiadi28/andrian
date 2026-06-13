# Checklist Remediasi Keamanan & Stabilitas

**Sistem:** Analisa Kredit BPR — Aplikasi PHP Native + MySQL  
**Dokumen:** Internal bank — pelacakan perbaikan yang telah diimplementasikan  
**Status:** Item di bawah ini ditandai selesai (`[x]`) setelah verifikasi di lingkungan masing-masing.

---

## 1. Keamanan — CSRF & sesi

- [x] CSRF pada form keputusan approval: `kasubag_analis`, `kabag_analis`, `kabag_kredit`, `kadiv_kredit`, `direksi`, `kadiv_bisnis` (verifikasi token + field tersembunyi di modal).
- [x] CSRF pada `detail_action.php` (hapus / kirim ulang) + token di `detail.php`.
- [x] CSRF pada `admin/users.php` (seluruh form POST terkait manajemen user).
- [x] CSRF pada `admin/backup.php` (buat backup & hapus file backup).
- [x] CSRF pada `auth/login.php` (token di sesi + verifikasi aman + field tersembunyi).
- [x] CSRF pada endpoint `analis/save_section.php` + pengiriman token dari JavaScript (`window.__CSRF_TOKEN__` di `form_umum.php` & `partials/pegawai_head_raw.inc.php`).
- [x] CSRF pada `analis/memo_internal.php` dan `analis/input_agunan.php`.
- [x] CSRF pada `api/request_revision_completed.php` (+ verifikasi `isLoggedIn()`).
- [x] `session_regenerate_id(true)` setelah login sukses.
- [x] Timeout sesi tidak aktif (~30 menit) melalui `last_activity` + `enforceSessionSecurity()` di `includes/functions.php`.

---

## 2. Keamanan — anti-bypass approval & error handling

- [x] Validasi di `processApproval()`: `posisi_saat_ini` harus sesuai role pemroses (mencegah approval di luar giliran).
- [x] Pesan error internal `processApproval` tidak menampilkan detail exception ke pengguna; detail dicatat lewat `logError()`.

---

## 3. Kerahasiaan — error & koneksi database

- [x] `config/database.php`: kegagalan koneksi tidak menampilkan detail PDO ke pengguna; pesan generik + `error_log`.
- [x] `detail_action.php`: gagal hapus tidak menampilkan exception; `logError` + pesan generik.
- [x] `admin/users.php`: operasi gagal — log internal, pesan generik ke pengguna.
- [x] `admin/backup.php`: gagal backup — log + pesan generik; tampilan pesan di-escape.
- [x] `analis/memo_internal.php` & `analis/input_agunan.php`: error simpan tidak mengekspos detail teknis ke pengguna.
- [x] `api/request_revision_completed.php`: respons error generik (tidak mengembalikan `getMessage()` exception ke klien).

---

## 4. Keamanan — XSS & output

- [x] `admin/logs.php`: kolom role menggunakan `htmlspecialchars`.
- [x] `admin/users.php`: `$success` / `$error` di-escape.
- [x] `admin/backup.php`: pesan & nama file di-escape; link download memakai `urlencode`.
- [x] `auth/login.php`: `$error` di-escape.
- [x] `analis/memo_internal.php`: `$success` / `$error` di-escape.
- [x] `analis/input_agunan.php`: `$error` di-escape.
- [x] `kadiv_bisnis/proses.php`: `$success` / `$error` di-escape.
- [x] Halaman proses approval (`kadiv_kredit`, `direksi`, `kabag_kredit`, `kabag_analis`, `kasubag_analis`): `$success` / `$error` di-escape.

---

## 5. Otorisasi akses data (tanpa mengubah alur bisnis)

- [x] `detail.php`: fungsi `canAccessPengajuanDetail()` — analis hanya melihat pengajuan milik sendiri; pejabat rantai approval + role terkait dapat melihat; Superadmin seluruh data.
- [x] `print.php`: analis hanya dapat mencetak pengajuan yang diinput sendiri (selaras dengan detail).

---

## 6. Performa & stabilitas

- [x] `config/database.php`: pengecekan migrasi skema (`bankKreditEnsureSchema`) di-throttle (~90 detik via `logs/.schema_ensure_stamp`) agar tidak membebani setiap request.
- [x] `analis/riwayat.php`: kolom terbatas + pagination (30 per halaman); tidak memuat seluruh riwayat sekaligus.
- [x] Inbox approval `kasubag_analis/proses.php` & `kadiv_bisnis/proses.php`: pagination + sort/cari + kolom eksplisit (selaras `kabag_kredit`); `kabag_kredit/proses.php` daftar inbox memakai kolom eksplisit (bukan `SELECT *`).
- [x] `val/.htaccess` + `.htaccess` root: blokir akses web ke folder `val/` dan skrip migrasi tertentu di root (`migration_trigger`, `migrate_*`, `_add_covernote_col`). *Nginx: aturan setara harus dikonfigurasi manual.*
- [x] `admin/users.php`: pagination daftar user (25 per halaman); query kolom eksplisit.
- [x] `admin/backup.php`: backup SQL ditulis streaming ke file (tidak mengakumulasi seluruh DB ke satu string di memori).
- [x] `includes/schema_realtime_migrate.php`: nilai ENUM `revisi` pada `approval_kredit.keputusan` ikut di-ensure (mencegah truncation saat log approval revisi).

---

## 7. Audit trail & backup

- [x] `admin/backup.php`: pencatatan aktivitas (audit) untuk pembuatan backup, penghapusan file, dan download; validasi pola nama file `backup_*.sql`.
- [x] `auth/logout.php`: pencatatan audit logout sebelum session dihancurkan.
- [x] `auth/login.php`: percobaan login gagal dicatat di `audit_log` (`id_user` NULL, username disanitasi).
- [x] `admin/users.php`: audit untuk tambah user, ubah user (ringkas before→after), hapus user.

---

## 8. Upload, file statis & produksi

- [x] Validasi MIME (`finfo`) untuk unggahan di `analis/save_section.php` selaras ekstensi (gambar/PDF); fungsi `bankKreditVerifyUploadMime()` di `includes/functions.php`.
- [x] Produksi: `BK_PRODUCTION` dari env `BK_PRODUCTION=1` atau flag `$bkForceProduction` di `config/database.php`; unggahan ditolak jika `fileinfo` tidak ada; jika finfo ada, MIME selalu diverifikasi ketat.
- [x] `admin/backup.php` memakai host/user/pass/nama DB yang sama dengan `config/database.php` (bukan hardcode terpisah).
- [x] `assets/uploads/.htaccess`: pembatasan akses file skrip di folder unggahan (Apache).
- [x] File uji/patch di root aplikasi yang tidak diperlukan operasional dihapus (lihat commit / daftar file di bawah).

---

## 9. Validasi bisnis & input

- [x] `processApproval()`: `sanitizeApprovalCatatan()` — tag HTML dihilangkan, panjang dibatasi.
- [x] `analis/save_section.php` section `pemohon`: `tanggal_lahir` / pasangan memakai `validateDate()` (YYYY-MM-DD).
- [x] `analis/save_section.php` section `submit`: cek `jumlah_kredit` & `jangka_waktu` > 0 serta nama/NIK terisi sebelum kirim ke approval.

---

## 10. Kualitas kode & bug

- [x] `kadiv_kredit/proses.php`: penghapusan duplikat blok HTML setelah penutup `</html>` (sisa salinan halaman).
- [x] `includes/navbar.php`: duplikat blok menu `kadiv_kredit` dihapus.
- [x] Akses langsung ke `includes/functions.php`, `includes/navbar.php`, `includes/schema_realtime_migrate.php` via URL dibalas HTTP 403.
- [x] `auth/login.php`: pencegahan double-submit sederhana (disable tombol setelah kirim).

---

## Verifikasi pasca-deploy (diisi tim IT / QA)

Gunakan checklist ini setiap kali deploy ke staging/production:

- [ ] Login, logout, dan sesi timeout berjalan sesuai kebijakan.
- [ ] Semua form POST kritikal menolak request tanpa token CSRF valid.
- [ ] Alur approval tidak dapat dilakukan jika `posisi_saat_ini` tidak sesuai role.
- [ ] Pesan error ke pengguna tidak memuat stack trace / path server / kredensial DB.
- [ ] Backup & download backup tercatat di `audit_log` (sampling).
- [ ] Regresi fungsi: input analis (`save_section`), memo internal, input agunan, admin user/backup.
- [ ] Setelah upgrade DB manual: jika perlu paksa migrasi skema sekali, **hapus** `logs/.schema_ensure_stamp` lalu reload halaman (atau tunggu interval throttle).

---

## Referensi file utama yang disentuh

| Area | File / pola |
|------|----------------|
| CSRF & sesi | `includes/functions.php`, `auth/login.php`, `auth/logout.php` |
| Approval | `*/proses.php`, `includes/functions.php` (`processApproval`) |
| Aksi detail | `detail.php`, `detail_action.php` |
| Admin | `admin/users.php`, `admin/backup.php`, `admin/logs.php` |
| Analis | `analis/save_section.php`, `analis/form_umum.php`, `analis/partials/pegawai_head_raw.inc.php`, `analis/memo_internal.php`, `analis/input_agunan.php` |
| API | `api/request_revision_completed.php` |
| Konfigurasi & performa | `config/database.php`, `includes/schema_realtime_migrate.php` |
| Otorisasi detail/cetak | `detail.php`, `print.php`, `includes/functions.php` (`canAccessPengajuanDetail`) |
| Riwayat analis | `analis/riwayat.php` |
| Upload & MIME | `analis/save_section.php`, `includes/functions.php` (`bankKreditVerifyUploadMime`) |
| Folder upload | `assets/uploads/.htaccess` |

**File non-produksi yang dihapus dari web root (sampel):** skrip `test_*.php`, `TEST_*.php`, `tmp_chk.php`, `_check_cols.php`, `fix_roles.sql`, `fix_roles_enum.php`, `patch_input*.php`, `setup_tables.php`, `upgrade_dashboard_features.php`, `ip sharing.html`.

---

*Dokumen ini dapat direvisi ketika ada remediasi tambahan; tambahkan baris baru dengan status `[ ]` hingga diimplementasikan dan diuji.*
