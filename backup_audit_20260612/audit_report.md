# 🔍 LAPORAN AUDIT MENYELURUH — Sistem Analisa Kredit BPR Bank Wonosobo

**Tanggal Audit:** 13 April 2026  
**Sistem:** Aplikasi Analisa Kredit (PHP Native + MySQL)  
**Versi Audit:** v1.0  

---

## RINGKASAN EKSEKUTIF

| Kategori | Temuan | Risiko Tertinggi |
|---|---|---|
| 1. Struktur Aplikasi | 5 temuan | 🟡 Sedang |
| 2. Performa | 4 temuan | 🟡 Sedang |
| 3. Keamanan Sistem | 6 temuan | 🔴 **Tinggi/Kritis** |
| 4. Validasi Data | 3 temuan | 🟡 Sedang |
| 5. Logika Bisnis Kredit | 3 temuan | 🟡 Sedang |
| 6. Role & Hak Akses | 3 temuan | 🟡 Sedang |
| 7. Perhitungan Kredit | 2 temuan | 🟢 Rendah |
| 8. Error Handling | 3 temuan | 🟡 Sedang |
| 9. Logging & Audit Trail | 3 temuan | 🟡 Sedang |
| 10. Testing & Stabilitas | 2 temuan | 🟡 Sedang |
| **Total** | **34 temuan** | |

---

## 1. STRUKTUR APLIKASI

### 1.1 Struktur Folder
**Status:** ⚠️ Cukup — Perlu Perbaikan

| Aspek | Evaluasi |
|---|---|
| Pemisahan per role | ✅ Sudah ada folder per role (`analis/`, `kasubag_analis/`, `kabag_kredit/`, dst) |
| Config terpisah | ✅ `config/database.php` sudah terpisah |
| Includes terpisah | ✅ `includes/functions.php`, `includes/navbar.php` |
| MVC Pattern | ❌ Tidak menggunakan MVC — logic & view campur |

**Temuan:** Banyak file "sisa development" yang tertinggal di root folder dan berpotensi menjadi celah keamanan:

| File | Risiko | Dampak |
|---|---|---|
| `test_diagnosis.php` | 🔴 Tinggi | Bisa expose informasi database & sistem |
| `test_routing.php` | 🔴 Tinggi | Bisa expose routing & role internal |
| `test_routing_complete.php` | 🔴 Tinggi | Testing file yang bisa diakses publik |
| `TEST_APPROVAL_AMOUNT_LOGIC.php` | 🔴 Tinggi | Test file yang mengandung logic internal |
| `tmp_chk.php`, `_check_cols.php` | 🟡 Sedang | Tools debug yang seharusnya tidak ada di production |
| `fix_roles.sql`, `fix_roles_enum.php` | 🟡 Sedang | Script migrasi publik |
| `patch_input.php`, `patch_input2.php` | 🟡 Sedang | Patch files yang tersisa |
| `migrate_*.php`, `setup_tables.php` | 🟡 Sedang | Script migrasi publik |

**Rekomendasi:**
```
# Hapus atau pindahkan file-file berikut ke folder yang tidak bisa diakses web:
test_diagnosis.php
test_routing.php
test_routing_complete.php
TEST_APPROVAL_AMOUNT_LOGIC.php
tmp_chk.php
_check_cols.php
_add_covernote_col.php
fix_roles.sql
fix_roles_enum.php
patch_input.php
patch_input2.php
migrate_agunan_fix.php
migrate_bank_lain.php
migration_trigger.php
setup_tables.php
upgrade_dashboard_features.php
temp_patch.txt
ip sharing.html
```

### 1.2 Pemisahan Logic dan Tampilan
**Status:** ⚠️ Parsial  
**Risiko:** 🟡 Sedang  

- ✅ `save_section.php` sudah terpisah sebagai API JSON handler
- ✅ `functions.php` menampung business logic terpusat
- ❌ Halaman proses approval (seperti `kasubag_analis/proses.php` baris 7-14) menggabungkan POST handling langsung di file view:
```php
// kasubag_analis/proses.php baris 7-14 — logic langsung di file view
if (isset($_POST['submit_decision'])) {
    $id_pengajuan = $_POST['id_pengajuan'];  // tanpa validasi/sanitasi
    $keputusan = $_POST['keputusan'];         // tanpa validasi
    $catatan = $_POST['catatan'];             // tanpa sanitasi
    $res = processApproval($pdo, $id_pengajuan, ...);
}
```

**Rekomendasi:** Ekstrak POST handling ke file terpisah (mis. `kasubag_analis/action.php`) dan validasi input sebelum diproses.

### 1.3 Duplikasi Kode
**Status:** ⚠️ Ada Duplikasi Signifikan  
**Risiko:** 🟡 Sedang  

**Temuan duplikasi utama:**

1. **Navbar (`navbar.php` baris 241-288):** Terdapat duplikat blok `kadiv_kredit` (muncul 2 kali dengan kondisi `elseif ($current_role == 'kadiv_kredit')` yang identik). Ini dead code karena kondisi kedua tidak pernah tercapai.

2. **Proses approval pages:** File `kasubag_analis/proses.php`, `kabag_kredit/proses.php`, `kadiv_kredit/proses.php`, dan `direksi/proses.php` memiliki struktur sangat mirip. Sebaiknya gunakan satu template dengan parameter role.

3. **Dashboard pages:** Mirip untuk `kabag_kredit/dashboard.php`, `kadiv_kredit/dashboard.php`, `direksi/dashboard.php`.

---

## 2. PERFORMA APLIKASI

### 2.1 Query `SELECT *` yang Tidak Efisien
**Status:** ❌ Ditemukan 27+ lokasi  
**Risiko:** 🟡 Sedang  

Beberapa contoh query `SELECT *` yang bisa dioptimasi:

| File | Baris | Query | Rekomendasi |
|---|---|---|---|
| `auth/login.php` | 21 | `SELECT * FROM users WHERE username = ?` | Pilih kolom yang dibutuhkan saja |
| `analis/riwayat.php` | 6 | `SELECT * FROM pengajuan_kredit WHERE input_by = ?` | Tanpa LIMIT/pagination |
| `admin/users.php` | 102 | `SELECT * FROM users ORDER BY role ASC` | Tanpa pagination |
| `kasubag_analis/proses.php` | 19 | `SELECT * FROM pengajuan_kredit WHERE posisi_saat_ini = ?` | Tanpa LIMIT |
| `admin/backup.php` | 39 | `SELECT * FROM $table` | Backup seluruh tabel ke string — memory issue pada data besar |

**Dampak:** Untuk tabel `pengajuan_kredit` yang memiliki banyak kolom (termasuk TEXT), mengambil semua kolom memperlambat query dan menghabiskan memori.

**Rekomendasi:**
```php
// SEBELUM (analis/riwayat.php):
$stmt = $pdo->prepare("SELECT * FROM pengajuan_kredit WHERE input_by = ? ORDER BY tanggal_pengajuan DESC");

// SETELAH:
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$stmt = $pdo->prepare("SELECT id_pengajuan, nama_debitur, jumlah_kredit, jenis_kredit, 
    status_pengajuan, posisi_saat_ini, tanggal_pengajuan 
    FROM pengajuan_kredit WHERE input_by = ? 
    ORDER BY tanggal_pengajuan DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $_SESSION['user_id']);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
```

### 2.2 Indexing Database
**Status:** ✅ Baik  
**Risiko:** 🟢 Rendah

Aplikasi sudah memiliki mekanisme auto-indexing di `schema_realtime_migrate.php` yang baik:
- `idx_pk_posisi_status_tgl` — untuk query inbox approval
- `idx_pk_input_tgl` — untuk query dashboard analis
- `idx_pk_status_tgl` — untuk filter status
- `idx_users_role_jabatan` — untuk lookup user aktif
- `idx_jm_id_pengajuan` — untuk lookup jaminan
- `idx_audit_user_waktu` — untuk audit log

### 2.3 Schema Migration Setiap Request
**Status:** ⚠️ Potensi Bottleneck  
**Risiko:** 🟡 Sedang  

**Temuan:** `schema_realtime_migrate.php` dijalankan pada **setiap request** (`config/database.php` baris 15-16):
```php
require_once __DIR__ . '/../includes/schema_realtime_migrate.php';
bankKreditEnsureSchema($pdo);
```

Fungsi ini melakukan:
- `SHOW TABLES LIKE 'pengajuan_kredit'`
- `SHOW COLUMNS FROM pengajuan_kredit LIKE 'jenis_pekerjaan'`
- `SHOW COLUMNS FROM pengajuan_kredit LIKE 'status_pengajuan'`
- `SHOW COLUMNS FROM pengajuan_kredit LIKE 'posisi_saat_ini'`
- `SHOW COLUMNS FROM approval_kredit LIKE 'keputusan'`
- **6+ query ke `information_schema`** untuk pengecekan indeks

Minimal **10 query** tambahan per request hanya untuk migrasi.

**Rekomendasi:**
```php
// config/database.php — gunakan flag file untuk skip jika sudah migrasi
$migrationFlagFile = __DIR__ . '/../logs/.migration_done_v2';
if (!file_exists($migrationFlagFile)) {
    require_once __DIR__ . '/../includes/schema_realtime_migrate.php';
    bankKreditEnsureSchema($pdo);
    @file_put_contents($migrationFlagFile, date('Y-m-d H:i:s'));
}
```

### 2.4 Backup Database — Memory Overflow Risk
**Status:** ❌ Bermasalah  
**Risiko:** 🟡 Sedang  

**Temuan:** `admin/backup.php` baris 31-65 membaca **seluruh database ke string PHP** (`$return`), lalu menulis sekaligus. Untuk database dengan ribuan pengajuan + data agunan, ini bisa menyebabkan `memory exhaustion`.

**Rekomendasi:** Tulis langsung ke file per-baris, bukan akumulasi ke variabel:
```php
$handle = fopen($backupPath, 'w+');
foreach ($tables as $table) {
    $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM);
    fwrite($handle, "DROP TABLE IF EXISTS $table;\n\n" . $row2[1] . ";\n\n");
    
    $result = $conn->query("SELECT * FROM `$table`");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $values = array_map(function($v) use ($conn) {
            return $v === null ? 'NULL' : $conn->quote($v);
        }, $row);
        fwrite($handle, "INSERT INTO `$table` VALUES(" . implode(',', $values) . ");\n");
    }
    fwrite($handle, "\n\n");
}
fclose($handle);
```

---

## 3. KEAMANAN SISTEM

### 3.1 Prepared Statement (SQL Injection)
**Status:** ✅ Baik  
**Risiko:** 🟢 Rendah

Hampir semua query sudah menggunakan prepared statements (`$pdo->prepare()`). 

> [!WARNING]
> **Pengecualian kritis ditemukan** di `admin/backup.php` baris 39:
> ```php
> $result = $conn->query("SELECT * FROM $table");
> ```
> Variabel `$table` berasal dari `SHOW TABLES` (bukan user input), jadi risikonya rendah. Namun tetap harus dipertimbangkan untuk konsistensi.

Juga di `includes/functions.php` baris 201:
```php
$stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
```
Ini juga berasal dari kode internal (bukan user input), tapi pattern-nya tidak aman.

### 3.2 CSRF Protection
**Status:** ❌ Tidak Ada  
**Risiko:** 🔴 **TINGGI**

**Temuan:** Tidak ditemukan implementasi CSRF token di seluruh aplikasi. Semua form POST rentan CSRF attack:

- Form approval (`kasubag_analis/proses.php`, `kabag_kredit/proses.php`, dll)
- Form delete pengajuan (`detail_action.php`)
- Form tambah/edit/hapus user (`admin/users.php`)
- Form backup database (`admin/backup.php`)

**Dampak:** Attacker bisa memalsukan request approval/penolakan/penghapusan atas nama user yang sedang login.

**Rekomendasi — Implementasi CSRF Token:**
```php
// 1. Tambahkan di includes/functions.php:
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) 
        && hash_equals($_SESSION['csrf_token'], $token);
}

// 2. Tambahkan di setiap form:
<input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

// 3. Validasi di setiap POST handler:
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    die('Token keamanan tidak valid.');
}
```

### 3.3 Input Sanitization (XSS)
**Status:** ✅ Sebagian Besar Baik, ❌ Beberapa Celah  
**Risiko:** 🟡 Sedang

**Positif:** Aplikasi cukup konsisten menggunakan `htmlspecialchars()` di output view (~30+ file).

**Temuan celah:**

1. **`admin/logs.php` baris 75** — role ditampilkan tanpa escape:
```php
<span class="badge badge-process"><?= $l['role'] ?? '-' ?></span>
// SEHARUSNYA:
<span class="badge badge-process"><?= htmlspecialchars($l['role'] ?? '-') ?></span>
```

2. **`admin/backup.php` baris 170** — pesan bisa berisi data tak aman:
```php
<?= $message ?>
// SEHARUSNYA:
<?= htmlspecialchars($message) ?>
```

3. **`admin/backup.php` baris 193** — nama file tanpa escape:
```php
<span style="font-family: monospace; color: #334155;"><?= $file ?></span>
// SEHARUSNYA:
<?= htmlspecialchars($file) ?>
```

4. **`admin/users.php` baris 135-138** — variabel `$success` dan `$error` ditampilkan tanpa escape:
```php
<div class="alert alert-success"><?= $success ?></div>
<div class="alert alert-error"><?= $error ?></div>
```

### 3.4 Password Hashing
**Status:** ✅ Baik  
**Risiko:** 🟢 Rendah

- ✅ `password_hash()` digunakan saat buat/edit user (`admin/users.php` baris 35, 56)
- ✅ `password_verify()` digunakan saat login (`auth/login.php` baris 25)
- ✅ Default PASSWORD_DEFAULT (bcrypt)

### 3.5 Session Management
**Status:** ⚠️ Perlu Peningkatan  
**Risiko:** 🟡 Sedang

**Temuan:**
1. **Tidak ada session regeneration setelah login** — Session fixation vulnerability:
```php
// auth/login.php — SEHARUSNYA ditambahkan sebelum set session:
session_regenerate_id(true); // <-- tambahkan ini
$_SESSION['user_id'] = $user['id_user'];
```

2. **Tidak ada session timeout** — Session berlangsung selamanya sampai browser ditutup.

3. **Tidak ada pengecekan IP/User-Agent** untuk session hijacking prevention.

**Rekomendasi Session Security:**
```php
// Tambahkan di includes/functions.php, panggil setelah session_start():
function secureSession() {
    // Session timeout 30 menit
    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header("Location: " . BASE_URL . "/auth/login.php?expired=1");
        exit;
    }
    $_SESSION['last_activity'] = time();
    
    // Cookie security
    if (session_status() === PHP_SESSION_ACTIVE) {
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => isset($_SERVER['HTTPS']),
        ]);
    }
}
```

### 3.6 File Upload Security
**Status:** ⚠️ Perlu Peningkatan  
**Risiko:** 🟡 Sedang

**Positif:**
- ✅ Validasi ekstensi file (`jpg`, `png`, `webp`, `pdf`)
- ✅ Batas ukuran 2MB
- ✅ Rename file dengan `uniqid()`

**Temuan:**
1. **Tidak ada validasi MIME type** — file bisa di-rename menjadi `.jpg` padahal isinya PHP:
```php
// SEBELUM:
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

// SETELAH — tambahkan MIME check:
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($tmp);
$allowedMimes = [
    'image/jpeg', 'image/png', 'image/webp', 'image/gif',
    'application/pdf'
];
if (!in_array($mimeType, $allowedMimes, true)) {
    echo json_encode(['success' => false, 'message' => 'Tipe file tidak diizinkan.']);
    exit;
}
```

2. **Folder uploads di dalam web-accessible directory** — File upload bisa diakses langsung via URL. Pertimbangkan menambahkan `.htaccess`:
```apache
# assets/uploads/.htaccess
<FilesMatch "\.(php|phtml|php5|php7|pht)$">
    Deny from all
</FilesMatch>
```

### 3.7 Direct Access File
**Status:** ⚠️ Partial  
**Risiko:** 🟡 Sedang

- File-file include (`functions.php`, `navbar.php`, `schema_realtime_migrate.php`) bisa diakses langsung walaupun efeknya minimal.

**Rekomendasi:**
```php
// Tambahkan di awal setiap file include:
defined('BASE_URL') || die('Direct access not allowed.');
```

---

## 4. VALIDASI DATA

### 4.1 Validasi Input Form
**Status:** ✅ Baik di save_section.php  
**Risiko:** 🟢-🟡

**Positif:**
- ✅ NIK divalidasi 16 digit: `preg_match('/^[0-9]{16}$/', $nik)`
- ✅ No HP divalidasi 10-15 digit
- ✅ Nama wajib diisi
- ✅ Alamat KTP wajib diisi
- ✅ Format tanggal divalidasi (`YYYY-MM-DD`)
- ✅ `floatval()` digunakan untuk konversi numerik
- ✅ `intval()` digunakan untuk integer

**Temuan:**
1. **Validasi tanggal_lahir tidak ketat** (`save_section.php` baris 153):
```php
$tanggal_lahir = $_POST['tanggal_lahir'] ?: null;
// Tidak memanggil validateDate(), beda dengan date field lainnya
```

2. **Input catatan pada approval pages tidak disanitasi** (`kasubag_analis/proses.php` baris 10):
```php
$catatan = $_POST['catatan']; // langsung masuk ke processApproval tanpa sanitasi
```

### 4.2 Mismatch Tipe Data
**Status:** ✅ Sudah Baik  
**Risiko:** 🟢 Rendah

- `posisi_saat_ini` sudah di-migrate dari ENUM ke `VARCHAR(100)` — mencegah "data truncated"
- `status_pengajuan` ENUM sudah di-extend otomatis via `schema_realtime_migrate.php`
- `approval_kredit.keputusan` ENUM juga sudah auto-extend
- `jenis_pekerjaan` sudah `VARCHAR(32)` — aman

### 4.3 Field Wajib NULL Check
**Status:** ⚠️ Sebagian  
**Risiko:** 🟡 Sedang

**Temuan:** Beberapa field penting tidak dicek NULL sebelum disimpan:
- `tempat_lahir`, `pekerjaan`, `alamat_pekerjaan` bisa kosong tanpa warning
- `tujuan_kredit` bisa kosong saat INSERT baru (diisi default string kosong)
- `jumlah_kredit` diinit dengan `0` — bisa disubmit tanpa pengisian

**Rekomendasi:** Tambahkan validasi wajib isi pada section `submit`:
```php
case 'submit':
    // Validasi kelengkapan data sebelum submit
    $stmt = $pdo->prepare("SELECT jumlah_kredit, jangka_waktu, tujuan_kredit 
        FROM pengajuan_kredit WHERE id_pengajuan = ?");
    $stmt->execute([$id_pengajuan]);
    $check = $stmt->fetch();
    
    if ($check['jumlah_kredit'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Jumlah kredit wajib diisi sebelum submit.']);
        exit;
    }
    if ($check['jangka_waktu'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Jangka waktu wajib diisi sebelum submit.']);
        exit;
    }
```

---

## 5. LOGIKA BISNIS KREDIT

### 5.1 Alur Approval
**Status:** ✅ Baik  
**Risiko:** 🟢 Rendah

Alur approval sudah diimplementasikan dengan benar:

```
analis → kabag_analis → kabag_kredit → kadiv_kredit → direksi
```

Hierarchy didefinisikan di `functions.php` baris 74:
```php
return ['analis', 'kabag_analis', 'kabag_kredit', 'kadiv_kredit', 'direksi'];
```

> [!NOTE]
> **Catatan Penting:** Hierarchy Anda menggunakan `kabag_analis` lalu `kabag_kredit`, namun di pertanyaan awal Anda menyebut `analis → kabag → kadiv → direksi`. Pastikan hierarchy saat ini (`analis → kabag_analis → kabag_kredit → kadiv_kredit → direksi`) sesuai dengan struktur organisasi BPR yang sebenarnya.

Fitur juga mendukung:
- ✅ **Eskalasi otomatis** — jika approver berhalangan, otomatis lompat ke level berikutnya
- ✅ **Amount-based routing** — kredit < 500 juta berhenti di `kadiv_kredit`, ≥ 500 juta lanjut ke `direksi`

### 5.2 Status Management
**Status:** ✅ Baik  
**Risiko:** 🟢 Rendah

Status yang didukung:
| Status | Deskripsi |
|---|---|
| `draft` | Baru dibuat, belum disubmit |
| `diajukan` | Sudah disubmit oleh analis |
| `proses` | Dalam proses approval |
| `kasubag`, `kabag`, `kadiv`, `direksi` | Posisi saat ini di pipeline |
| `revisi` | Dikembalikan untuk perbaikan |
| `revisi_diajukan` | Revisi diminta setelah disetujui |
| `ditolak` | Ditolak |
| `disetujui` | Disetujui (akhir) |
| `selesai` | Proses selesai |

### 5.3 Revisi Tanpa Input Ulang
**Status:** ✅ Baik  
**Risiko:** 🟢 Rendah

Saat revisi:
- Data dikembalikan ke analis (`posisi_saat_ini = 'analis'`)
- **Data TIDAK dihapus** — hanya status yang diubah
- Analis bisa edit data yang sudah ada dan kirim ulang
- `revision_count` diincrement untuk tracking
- `last_revision_at`, `last_revision_by` dicatat
- `catatan_revisi` disimpan
- Kirim ulang (`kirim_ulang`) mengembalikan ke `last_reject_level` — **TIDAK dari awal**

### 5.4 Anti-Bypass Approval
**Status:** ⚠️ Perlu Pengecekan Tambahan  
**Risiko:** 🟡 Sedang

**Positif:**
- ✅ `processApproval()` melakukan `FOR UPDATE` lock pada row
- ✅ Status dicek sebelum diproses

**Temuan:**
Fungsi `processApproval()` menerima parameter `$role` dari caller tanpa memverifikasi bahwa `$role` benar-benar sesuai dengan `posisi_saat_ini` dari pengajuan:

```php
// functions.php baris 256 — processApproval menerima $role tapi tidak cek:
function processApproval($pdo, $id_pengajuan, $role, $user_id, $keputusan, $catatan) {
    // ...
    $row = $stmt->fetch(); // mendapat posisi_saat_ini
    // ❌ TIDAK ADA: if ($row['posisi_saat_ini'] !== $role) return error;
```

**Dampak:** Secara teori, jika caller mengirim `$role` yang salah, approval bisa diproses oleh role yang bukan gilirannya.

**Rekomendasi:**
```php
// Tambahkan validasi di awal processApproval():
if ($row['posisi_saat_ini'] !== $role && $role !== 'analis') {
    $pdo->rollBack();
    return ['success' => false, 'message' => 
        "Pengajuan tidak berada di posisi $role. Posisi saat ini: " . 
        $row['posisi_saat_ini']];
}
```

---

## 6. ROLE & HAK AKSES

### 6.1 Role Checking
**Status:** ✅ Baik  
**Risiko:** 🟢 Rendah

Setiap halaman menggunakan `requireSameRole()` atau `requireAnyRole()`:
- `analis/dashboard.php`: `requireSameRole('analis')`
- `admin/users.php`: `requireSameRole('Superadmin')`
- `kasubag_analis/proses.php`: `requireSameRole('kasubag_analis')`
- dll.

Superadmin memiliki akses ke semua halaman (bypass check).

### 6.2 Koneksi POST Handler dengan Session Role
**Status:** ⚠️ Sebagian  
**Risiko:** 🟡 Sedang

**Temuan:** Di halaman proses approval, role dicek di awal halaman (`requireSameRole`), tapi pada POST handler, `$my_role` di-hardcode:

```php
// kasubag_analis/proses.php baris 3,8:
$my_role = 'kasubag_analis';
requireSameRole($my_role);  // ✅ Benar

// Tapi di POST handler:
$res = processApproval($pdo, $id_pengajuan, $my_role, ...);
// $my_role di-hardcode, bukan dari session — ini AMAN karena requireSameRole sudah memfilter
```

Ini sebenarnya aman karena `requireSameRole()` sudah memastikan hanya user dengan role tersebut yang bisa akses, **KECUALI** Superadmin. Ketika Superadmin mengakses halaman ini, `$my_role` tetap `kasubag_analis`, yang bisa salah konteks.

**Rekomendasi:** Untuk POST handler, tambahkan pengecekan role aktual:
```php
if (isset($_POST['submit_decision'])) {
    // Jika Superadmin, gunakan role halaman ini
    $acting_role = ($_SESSION['role'] === 'Superadmin') ? $my_role : $_SESSION['role'];
    // ...
}
```

### 6.3 Direct URL Access
**Status:** ⚠️ Partial Issue  
**Risiko:** 🟡 Sedang

**Temuan:** `detail.php` baris 5-8 hanya mengecek `isLoggedIn()`, TANPA mengecek apakah user berhak melihat data tersebut:

```php
if (!isLoggedIn()) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}
// ❌ Tidak ada pengecekan: apakah user ini boleh lihat pengajuan ini?
```

**Dampak:** Semua user yang login bisa melihat detail pengajuan manapun hanya dengan URL `detail.php?id=1`.

**Rekomendasi:**
```php
// Tambahkan setelah fetch data:
$myRole = $_SESSION['role'];
if ($myRole !== 'Superadmin' 
    && $myRole !== 'analis' 
    && !in_array($myRole, getHierarchy())) {
    die("Anda tidak memiliki akses.");
}
// Untuk analis, hanya boleh lihat pengajuannya sendiri:
if ($myRole === 'analis' && $data['input_by'] != $_SESSION['user_id']) {
    die("Anda tidak memiliki akses ke pengajuan ini.");
}
```

---

## 7. PERHITUNGAN KREDIT

### 7.1 Konsistensi Perhitungan
**Status:** ✅ Baik  
**Risiko:** 🟢 Rendah

| Komponen | Rumus | Status |
|---|---|---|
| Biaya Operasional | Σ (bahan baku + gaji + listrik + air + sewa + transport + lainnya) | ✅ Konsisten |
| Laba Usaha | Omset - Total Biaya | ✅ Benar |
| Total Pengeluaran | Biaya Hidup + Cicilan Lain | ✅ Benar |
| Net Cashflow | Laba - Total Pengeluaran | ✅ Benar |
| Repayment Capacity | Net Cashflow × 75% | ✅ Benar |
| Kelayakan | RPC ≥ Angsuran → LAYAK | ✅ Benar |
| Taksasi Tanah (SHM/SHGB) | 75% dari nilai pasar | ✅ Benar |
| Taksasi Tanah (AJB/Letter C/Covernote) | 50% dari nilai pasar | ✅ Benar |
| Taksasi Sawah/Tegal | 70% dari nilai pasar | ✅ Benar |
| Likuidasi | 70% dari taksasi | ✅ Benar |
| Taksasi Kendaraan ≤5th | 85% | ✅ |
| Taksasi Kendaraan 6-10th | 75% | ✅ |
| Taksasi Kendaraan >10th | 65% | ✅ |

### 7.2 Duplikasi Logika Perhitungan
**Status:** ⚠️ Parsial  
**Risiko:** 🟡 Sedang

Perhitungan taksasi property terdapat di:
1. `save_section.php` (backend PHP, baris 744-766)
2. `form_umum.php` (frontend JavaScript — real-time calculation)

Kedua perhitungan **harus selalu sinkron**. Saat ini sudah sinkron, tapi jika salah satu diubah tanpa mengubah yang lain, hasil akan inkonsisten.

**Rekomendasi:** Dokumentasikan pendekatan **"backend is authority"** — hasil perhitungan JavaScript hanya untuk preview, hasil akhir **selalu dihitung ulang di backend** (`save_section.php`). Ini sudah benar, tetap pertahankan pattern ini.

---

## 8. ERROR HANDLING & DEBUG

### 8.1 Try-Catch Implementation
**Status:** ✅ Baik  
**Risiko:** 🟢 Rendah

- ✅ `processApproval()` menggunakan try-catch + transaction rollback
- ✅ `save_section.php` memiliki global try-catch wrapper
- ✅ `config/database.php` catch PDOException

### 8.2 Error Exposure
**Status:** ⚠️ Beberapa Celah  
**Risiko:** 🟡 Sedang

**Temuan:**

1. **Database error expose di production** (`config/database.php` baris 18):
```php
die("Koneksi Database Gagal: " . $e->getMessage() ...);
// getMessage() bisa berisi path server, username DB, dll
```

2. **Error pesan di processApproval** (`functions.php` baris 377):
```php
return ['success' => false, 'message' => 'Terjadi kesalahan saat memproses: ' . $e->getMessage()];
// $e->getMessage() bisa expose internal info
```

**Rekomendasi:**
```php
// config/database.php — production mode:
} catch (PDOException $e) {
    logError('Database connection failed', ['error' => $e->getMessage()]);
    die("Sistem sedang mengalami gangguan. Silakan hubungi administrator.");
}

// functions.php — processApproval:
} catch (Exception $e) {
    logError('processApproval error', ['error' => $e->getMessage(), ...]);
    return ['success' => false, 'message' => 'Terjadi kesalahan internal. Silakan coba lagi.'];
}
```

### 8.3 Logging
**Status:** ✅ Sudah Ada  
**Risiko:** 🟢 Rendah

Fungsi `logError()` di `functions.php` baris 14-31 sudah:
- ✅ Menulis ke file `logs/error_YYYY-MM-DD.log`
- ✅ Menyertakan timestamp, message, dan context
- ✅ Auto-create directory jika belum ada

---

## 9. LOGGING & AUDIT TRAIL

### 9.1 Cakupan Logging
**Status:** ✅ Baik  
**Risiko:** 🟢 Rendah

Aktivitas yang sudah tercatat di `audit_log`:
| Aktivitas | Status |
|---|---|
| Login | ✅ `auth/login.php` baris 41 |
| Buat pengajuan baru | ✅ `save_section.php` baris 314 |
| Update data pemohon | ✅ `save_section.php` baris 256 |
| Simpan data agunan | ✅ `save_section.php` baris 890 |
| Approve / Revisi / Tolak | ✅ `functions.php` baris 324, 343 |
| Kirim ulang | ✅ `functions.php` baris 368 |
| Delete/Cancel pengajuan | ✅ `detail_action.php` baris 39 |
| Update status user | ✅ `admin/users.php` baris 14 |

**Temuan — Aktivitas yang BELUM tercatat:**
| Aktivitas yang Hilang | Risiko |
|---|---|
| Logout | 🟡 Sedang |
| Perubahan password | 🟡 Sedang |
| Gagal login (brute-force detection) | 🟡 Sedang |
| Edit user data (nama, username, role) | 🟡 Sedang |
| Hapus user | 🟡 Sedang |
| Backup database | 🟡 Sedang |
| Download backup | 🔴 Tinggi |

### 9.2 Detail Perubahan
**Status:** ⚠️ Perlu Peningkatan  
**Risiko:** 🟡 Sedang

**Temuan:** Audit log hanya mencatat "apa yang dilakukan" tanpa menyimpan **data sebelum dan sesudah perubahan** (before/after snapshot):

```
"Memperbarui Data Pemohon (ID Pengajuan: 1)"
// Tidak tahu field apa yang diubah, dari nilai apa ke nilai apa
```

**Rekomendasi untuk audit perbankan:**
```php
function auditLogDetailed($pdo, $userId, $activity, $oldData = null, $newData = null) {
    $changes = '';
    if ($oldData && $newData) {
        $diff = [];
        foreach ($newData as $key => $val) {
            if (isset($oldData[$key]) && $oldData[$key] != $val) {
                $diff[] = "$key: '{$oldData[$key]}' → '$val'";
            }
        }
        $changes = implode('; ', $diff);
    }
    $stmt = $pdo->prepare("INSERT INTO audit_log (id_user, aktivitas, detail_perubahan, ip_address) 
        VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $activity, $changes, $_SERVER['REMOTE_ADDR'] ?? '']);
}
```

### 9.3 Riwayat Approval
**Status:** ✅ Baik  
**Risiko:** 🟢 Rendah

- ✅ Semua keputusan (setuju, tolak, revisi, eskalasi otomatis, kirim_ulang) tercatat di `approval_kredit`
- ✅ Tercatat: siapa, kapan, level, keputusan, catatan
- ✅ Data historis **tidak pernah dihapus** — hanya INSERT, TIDAK ada DELETE pada approval_kredit
- ✅ Timeline approval ditampilkan di `detail.php`

---

## 10. TESTING & STABILITAS

### 10.1 Skenario Edge Case
**Status:** ⚠️ Perlu Perhatian  
**Risiko:** 🟡 Sedang

**Temuan skenario yang belum ditangani optimal:**

| Skenario | Status | Detail |
|---|---|---|
| Input salah (NIK kurang digit) | ✅ Ditolak | Regex validation |
| Data kosong pada form | ✅ Partial | Beberapa field boleh kosong |
| User tidak valid (session expired) | ✅ Redirect login | `isLoggedIn()` check |
| Double submit form | ❌ Tidak ada protection | Bisa duplikasi data |
| Concurrent approval | ✅ `FOR UPDATE` lock | Mencegah race condition |
| SQL query error | ✅ Try-catch + rollback | |
| File upload melebihi PHP limit | ⚠️ Partial | PHP `upload_max_filesize` tidak dicek |

**Rekomendasi Double Submit Protection:**
```php
// Tambahkan di save_section.php setelah berhasil simpan:
$_SESSION['last_submit_token'] = $id_pengajuan . '_' . time();

// Di awal form submission:
$submitToken = $_POST['submit_token'] ?? '';
if ($submitToken === ($_SESSION['last_submit_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Form sudah pernah disubmit.']);
    exit;
}
```

### 10.2 Stabilitas pada Data Besar
**Status:** ⚠️ Potensi Masalah  
**Risiko:** 🟡 Sedang

**Temuan:**
1. `analis/riwayat.php` baris 6 — mengambil SEMUA pengajuan tanpa pagination:
```php
$stmt = $pdo->prepare("SELECT * FROM pengajuan_kredit WHERE input_by = ? ORDER BY tanggal_pengajuan DESC");
```

2. `admin/users.php` baris 102 — mengambil semua users tanpa pagination:
```php
$users = $pdo->query("SELECT * FROM users ORDER BY role ASC")->fetchAll();
```

3. `admin/backup.php` — membaca seluruh database ke memori (dibahas di 2.4)

---

## PRIORITAS PERBAIKAN

### 🔴 KRITIKAL (Segera)

| # | Temuan | File | Aksi |
|---|---|---|---|
| 1 | Tidak ada CSRF protection | Semua form | Implementasi CSRF token |
| 2 | File test/debug accessible | Root folder | Hapus/pindahkan 15+ file |
| 3 | Session fixation vulnerability | `auth/login.php` | Tambah `session_regenerate_id(true)` |
| 4 | Logout tanpa audit | `auth/logout.php` | Tambah audit log |

### 🟡 PENTING (1-2 Minggu)

| # | Temuan | File | Aksi |
|---|---|---|---|
| 5 | Tidak ada session timeout | `functions.php` | Implementasi timeout 30 menit |
| 6 | Bypass approval check kurang ketat | `functions.php` → `processApproval()` | Validasi `posisi_saat_ini == $role` |
| 7 | Detail.php accessible semua user | `detail.php` | Tambah role/ownership check |
| 8 | File upload tanpa MIME check | `save_section.php` | Tambah validasi MIME type |
| 9 | Schema migration setiap request | `config/database.php` | Implementasi flag file |
| 10 | Error message expose internal info | `config/database.php`, `functions.php` | Generic error messages |
| 11 | XSS di beberapa tempat | `admin/logs.php`, `admin/backup.php`, `admin/users.php` | Tambah `htmlspecialchars()` |
| 12 | Kurang audit log activities | `auth/logout.php`, `admin/users.php` | Tambah logging |
| 13 | Riwayat tanpa pagination | `analis/riwayat.php` | Implementasi pagination |

### 🟢 RENDAH (3-4 Minggu)

| # | Temuan | File | Aksi |
|---|---|---|---|
| 14 | Duplikasi kode proses pages | `*/proses.php` | Refactor ke template tunggal |
| 15 | Duplikat navbar kadiv_kredit | `includes/navbar.php` | Hapus blok duplikat baris 265-288 |
| 16 | Backup memory overflow risk | `admin/backup.php` | Stream write ke file |
| 17 | SELECT * queries | ~27 lokasi | Optimasi per-file |
| 18 | Double submit protection | `save_section.php` | Tambah token-based protection |
| 19 | Audit log detail (before/after) | `functions.php` | Implementasi diff logging |

---

## CATATAN AKHIR

> [!IMPORTANT]
> Secara keseluruhan, aplikasi ini sudah memiliki **fondasi yang cukup baik**:
> - ✅ Menggunakan PDO prepared statements secara konsisten
> - ✅ Password sudah di-hash dengan bcrypt
> - ✅ Alur approval terstruktur dengan hierarki yang jelas
> - ✅ Audit log sudah diimplementasikan untuk aktivitas kunci
> - ✅ Transaction management dengan rollback sudah ada
> - ✅ Error logging ke file sudah aktif
> - ✅ Database indexing sudah otomatis
>
> **Area terkritis yang perlu diperbaiki segera** adalah: CSRF protection, penghapusan file test/debug, dan session security (fixation + timeout). Perbaikan ini bisa dilakukan tanpa mengubah struktur database dan minimal risiko bug baru.
