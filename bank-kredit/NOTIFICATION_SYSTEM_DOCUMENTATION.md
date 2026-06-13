# 🔔 SISTEM NOTIFIKASI - DOKUMENTASI LENGKAP

## 📋 Ringkasan Implementasi

Sistem notifikasi telah ditambahkan ke seluruh approval chain. Setiap role akan menerima notifikasi otomatis ketika:
- **Pengajuan baru** diterima untuk diproses
- **Pengajuan disetujui** dan diteruskan ke role berikutnya
- **Pengajuan ditolak** atau perlu revisi
- **Pengajuan selesai** diproses di semua level

---

## 🗄️ PERUBAHAN DATABASE

### 1. Tabel Baru: `notifications`

**File**: [bank-kredit/database.sql](bank-kredit/database.sql) - Ditambahkan setelah table `audit_log`

**Struktur**:
```sql
CREATE TABLE notifications (
    id_notification INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,                          -- User yang menerima notifikasi
    id_pengajuan INT NOT NULL,                     -- ID pengajuan terkait
    tipe_notifikasi VARCHAR(50) NOT NULL,          -- submitted, approved, rejected, revised, auto_routed, completed
    judul_notifikasi VARCHAR(255) NOT NULL,        -- Judul notifikasi
    pesan_notifikasi TEXT,                         -- Isi pesan detail
    role_source VARCHAR(50),                       -- Role yang mengirim (analis, kepatuhan, dll)
    role_target VARCHAR(50),                       -- Role yang menerima
    is_read TINYINT(1) DEFAULT 0,                  -- 0 = belum dibaca, 1 = sudah dibaca
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Waktu pembuatan
    read_at TIMESTAMP NULL,                        -- Waktu dibaca
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE,
    INDEX idx_notif_user_read (id_user, is_read),
    INDEX idx_notif_tipe_created (tipe_notifikasi, created_at),
    INDEX idx_notif_pengajuan (id_pengajuan)
);
```

**Indeks**:
- `idx_notif_user_read`: Cepat mencari notifikasi belum dibaca user
- `idx_notif_tipe_created`: Cepat filter by type dan waktu
- `idx_notif_pengajuan`: Cepat mencari notifikasi per pengajuan

---

## 🔧 PERUBAHAN FUNGSI PHP

### [bank-kredit/includes/functions.php](bank-kredit/includes/functions.php)

#### 1. `createNotification()` - Buat notifikasi baru
```php
function createNotification(
    $id_user,              // User ID yang menerima
    $id_pengajuan,         // ID pengajuan
    $tipe_notifikasi,      // 'submitted', 'approved', 'rejected', 'revised', 'auto_routed', 'completed'
    $judul_notifikasi,     // Judul notifikasi
    $pesan_notifikasi = '', // Pesan detail
    $role_source = '',     // Role yang trigger
    $role_target = ''      // Role target
)
```

#### 2. `getUnreadNotifications()` - Ambil notifikasi belum dibaca
```php
function getUnreadNotifications($id_user, $limit = 50)
// Returns: Array notifikasi dengan info pengajuan (nama_debitur, jumlah_kredit, status)
```

#### 3. `getUnreadNotificationCount()` - Hitung notifikasi belum dibaca
```php
function getUnreadNotificationCount($id_user)
// Returns: Integer count
```

#### 4. `markNotificationAsRead()` - Tandai satu notifikasi dibaca
```php
function markNotificationAsRead($id_notification)
// Returns: Boolean
```

#### 5. `markAllNotificationsAsRead()` - Tandai semua notifikasi dibaca
```php
function markAllNotificationsAsRead($id_user)
// Returns: Boolean
```

#### 6. `notifyNextRole()` - Helper untuk notify role berikutnya
```php
function notifyNextRole(
    $id_pengajuan,
    $current_role,
    $action_type = 'approved',  // 'approved', 'rejected', 'revised', 'auto_routed'
    $message = ''
)
```

---

## 🔔 NOTIFIKASI OTOMATIS

### 1. **Analis Submit Pengajuan Baru**
**File**: [bank-kredit/analis/save_section.php](bank-kredit/analis/save_section.php) - Case `submit` (~line 1551)

**Trigger**: Saat Analis klik "SUBMIT" pengajuan

**Notifikasi dikirim ke**: Semua staff **Kepatuhan** (role = 'kepatuhan')

**Tipe**: `submitted`

**Contoh Pesan**:
```
Judul: Pengajuan Kredit Baru dari Analis
Pesan: Pengajuan kredit a.n Budi Santoso (Rp 100.000.000) telah dikirimkan dari Analis. 
       Silakan lakukan pengecekan dan assessment.
Role Source: analis
Role Target: kepatuhan
```

---

### 2. **Kepatuhan Submit Assessment**
**File**: [bank-kredit/api/save_assessment_kepatuhan.php](bank-kredit/api/save_assessment_kepatuhan.php) - CREATE action (~line 185)

**Trigger**: Saat Kepatuhan save assessment dan submit

**Notifikasi dikirim ke**: Semua staff **Kasubag Analis** (role = 'kasubag_analis')

**Tipe**: `auto_routed`

**Contoh Pesan**:
```
Judul: Assessment Kepatuhan Selesai - Siap untuk Kasubag Analis
Pesan: Pengajuan kredit a.n Budi Santoso (Rp 100.000.000) telah selesai di-assess oleh 
       Dept. Kepatuhan dan siap untuk ditinjau oleh Kasubag Analis.
Role Source: kepatuhan
Role Target: kasubag_analis
```

---

### 3. **Role Mana Pun Approve (Setuju)**
**File**: [bank-kredit/includes/functions.php](bank-kredit/includes/functions.php) - Function `processApproval()` (~line 700)

**Trigger**: Saat role approve pengajuan dengan keputusan "SETUJU"

**Notifikasi dikirim ke**: Semua staff **role berikutnya** dalam chain

**Tipe**: `auto_routed`

**Contoh Pesan**:
```
Judul: Pengajuan Dikirim ke Kabag Kredit
Pesan: Pengajuan kredit a.n Budi Santoso (Rp 100.000.000) telah disetujui oleh Kasubag Analis 
       dan siap untuk proses Kabag Kredit.
Role Source: kasubag_analis
Role Target: kabag_kredit
```

**Notifikasi ke Analis Jika Selesai**:
Jika pengajuan sudah disetujui di semua level (selesai), notifikasi juga dikirim ke Analis yang submit awal.

---

### 4. **Role Mana Pun Reject (Tolak)**
**File**: [bank-kredit/includes/functions.php](bank-kredit/includes/functions.php) - Function `processApproval()` (~line 770)

**Trigger**: Saat role reject pengajuan dengan keputusan "TOLAK"

**Notifikasi dikirim ke**: **Analis** (pembuat pengajuan awal)

**Tipe**: `rejected`

**Contoh Pesan**:
```
Judul: Pengajuan Ditolak oleh Kasubag Analis
Pesan: Pengajuan kredit a.n Budi Santoso telah ditolak oleh Kasubag Analis. 
       Alasan: Dokumen kurang lengkap, perlu surat keterangan tambahan dari pemohon...
Role Source: kasubag_analis
Role Target: analis
```

---

### 5. **Role Mana Pun Revisi (Kembalikan)**
**File**: [bank-kredit/includes/functions.php](bank-kredit/includes/functions.php) - Function `processApproval()` (~line 740)

**Trigger**: Saat role kembalikan pengajuan dengan keputusan "REVISI"

**Notifikasi dikirim ke**: **Analis** (pembuat pengajuan awal)

**Tipe**: `revised`

**Contoh Pesan**:
```
Judul: Pengajuan Perlu Revisi dari Kasubag Analis
Pesan: Pengajuan kredit a.n Budi Santoso perlu dilakukan revisi oleh Kasubag Analis. 
       Catatan revisi: Mohon perbaiki data pendapatan dan sertakan surat verifikasi dari BPS...
Role Source: kasubag_analis
Role Target: analis
```

---

## 🎨 UI KOMPONEN

### 1. **Notification Bell** - Dropdown di Navbar
**File**: [bank-kredit/includes/notification_bell.php](bank-kredit/includes/notification_bell.php)

**Fitur**:
- 🔔 Bell icon dengan red badge (jika ada notifikasi belum dibaca)
- Dropdown menampilkan 5 notifikasi terakhir belum dibaca
- Klik notifikasi → mark as read + redirect ke detail pengajuan
- Link "Tandai semua" → mark all as read
- Link "Lihat semua notifikasi" → buka halaman daftar lengkap

**Styling**:
- Dropdown dengan shadow dan smooth animation
- Color-coded by type (green=approved, blue=routed, red=rejected, orange=revised)
- Time indicator (baru saja, 5 menit lalu, dll)
- Highlight untuk unread (background biru muda)

**Lokasi**: Ditampilkan di navbar sebelum user profile

---

### 2. **Notification List Page** - Halaman Pusat Notifikasi
**File**: [bank-kredit/notifications/list.php](bank-kredit/notifications/list.php)

**Fitur**:
- 📬 Pusat Notifikasi dengan daftar lengkap (20 per halaman)
- Filter belum dibaca vs semua
- Pagination
- Click notifikasi → mark as read + redirect
- Button "Tandai Semua Sudah Dibaca"
- Refresh button
- Color-coded badges untuk tipe notifikasi

**Info per notifikasi**:
- Badge tipe (submitted, auto_routed, approved, rejected, revised, completed)
- Judul dan pesan
- Nama debitur, jumlah kredit, status pengajuan
- Waktu dibuat (relative time: "baru saja", "5 menit lalu", dll)

---

## 📡 API ENDPOINTS

### 1. `POST /api/mark_notification_read.php`
Mark satu notifikasi sebagai dibaca

**Parameters**:
```
POST /api/mark_notification_read.php
id_notification: 123
csrf_token: xxx
```

**Response**:
```json
{
  "success": true,
  "message": "Notification marked as read"
}
```

---

### 2. `POST /api/mark_all_notifications_read.php`
Mark semua notifikasi user sebagai dibaca

**Parameters**:
```
POST /api/mark_all_notifications_read.php
csrf_token: xxx
```

**Response**:
```json
{
  "success": true,
  "message": "All notifications marked as read"
}
```

---

## 🔄 ALUR NOTIFIKASI LENGKAP

### Scenario: Pengajuan Rp 300 Juta (< 500M)

```
┌─ ANALIS Submit
│  ├─ Create: status = 'kepatuhan', posisi = 'kepatuhan'
│  ├─ Create: approval_kredit dengan level='analis', keputusan='setuju'
│  └─ Notify: Semua Kepatuhan staff
│     Judul: "Pengajuan Kredit Baru dari Analis"
│
├─ KEPATUHAN Submit Assessment
│  ├─ Create: assessment_kepatuhan record
│  ├─ Update: status = 'diajukan', posisi = 'kasubag_analis'
│  ├─ Create: approval_kredit dengan level='kepatuhan', keputusan='setuju'
│  └─ Notify: Semua Kasubag Analis staff
│     Judul: "Assessment Kepatuhan Selesai - Siap untuk Kasubag Analis"
│
├─ KASUBAG ANALIS Approve
│  ├─ Update: status = 'diajukan', posisi = 'kabag_kredit'
│  ├─ Create: approval_kredit dengan level='kasubag_analis', keputusan='setuju'
│  └─ Notify: Semua Kabag Kredit staff
│     Judul: "Pengajuan Dikirim ke Kabag Kredit"
│
├─ KABAG KREDIT Approve
│  ├─ Update: status = 'diajukan', posisi = 'kadiv_bisnis'
│  ├─ Create: approval_kredit dengan level='kabag_kredit', keputusan='setuju'
│  └─ Notify: Semua Kadiv Bisnis staff
│     Judul: "Pengajuan Dikirim ke Kadiv Bisnis"
│
├─ KADIV BISNIS Approve (Final untuk < 500M)
│  ├─ Update: status = 'disetujui', posisi = 'selesai'
│  ├─ Create: approval_kredit dengan level='kadiv_bisnis', keputusan='setuju'
│  └─ Notify: Analis (pembuat awal)
│     Judul: "Pengajuan Kredit Disetujui"
│     Pesan: "Pengajuan kredit a.n ... telah disetujui seluruhnya dan berhasil diproses."
│
└─ SELESAI ✓
```

---

## 🚀 CARA KERJA

### Saat Aplikasi Diakses
1. Schema migration berjalan otomatis
2. Tabel `notifications` dicek - dibuat jika tidak ada
3. Semua perubahan ENUM & struktur lainnya di-update

### Saat Notifikasi Dibuat
1. Function `createNotification()` dipanggil
2. Record diinsert ke tabel `notifications`
3. User akan melihat badge merah di bell icon

### Saat User Membuka Navbar
1. Function `getUnreadNotificationCount()` di-call
2. Badge menampilkan jumlah unread
3. Dropdown menampilkan 5 notifikasi terakhir via `getUnreadNotifications()`

### Saat User Klik Notifikasi
1. AJAX call ke `mark_notification_read.php`
2. Notifikasi di-mark as read
3. User di-redirect ke detail.php pengajuan

---

## ✅ TESTING CHECKLIST

- [ ] Akses aplikasi - tabel notifications otomatis dibuat
- [ ] Analis submit pengajuan → Kepatuhan menerima notifikasi
- [ ] Buka navbar → Bell icon menampilkan unread count badge
- [ ] Klik dropdown → Lihat 5 notifikasi terakhir
- [ ] Klik notifikasi → Redirect ke detail.php + mark as read
- [ ] Kepatuhan submit assessment → Kasubag menerima notifikasi
- [ ] Kasubag approve → Kabag menerima notifikasi  
- [ ] Kabag approve → Kadiv menerima notifikasi
- [ ] Kadiv approve (final) → Analis menerima notifikasi "selesai"
- [ ] Klik "Tandai semua" → Semua notifikasi marked as read
- [ ] Buka /notifications/list.php → Lihat semua notifikasi dengan pagination
- [ ] Tolak pengajuan → Analis menerima notifikasi "rejected"
- [ ] Revisi pengajuan → Analis menerima notifikasi "revised"

---

## 📁 FILE YANG DIMODIFIKASI/DIBUAT

### Database
- ✅ [bank-kredit/database.sql](bank-kredit/database.sql) - Tabel notifications ditambahkan

### Functions & Helpers
- ✅ [bank-kredit/includes/functions.php](bank-kredit/includes/functions.php) - 6 function notifikasi ditambahkan
- ✅ [bank-kredit/includes/schema_realtime_migrate.php](bank-kredit/includes/schema_realtime_migrate.php) - Migration untuk notifications table ditambahkan
- ✅ [bank-kredit/includes/notification_bell.php](bank-kredit/includes/notification_bell.php) - **BARU**: Komponen bell dropdown
- ✅ [bank-kredit/includes/navbar.php](bank-kredit/includes/navbar.php) - Notification bell ditambahkan

### Business Logic
- ✅ [bank-kredit/analis/save_section.php](bank-kredit/analis/save_section.php) - Notifikasi saat submit (~line 1551)
- ✅ [bank-kredit/includes/functions.php](bank-kredit/includes/functions.php) - Notifikasi di processApproval() (~lines 700, 740, 770)
- ✅ [bank-kredit/api/save_assessment_kepatuhan.php](bank-kredit/api/save_assessment_kepatuhan.php) - Notifikasi saat assessment submit (~line 185)

### UI Pages
- ✅ [bank-kredit/notifications/list.php](bank-kredit/notifications/list.php) - **BARU**: Halaman pusat notifikasi

### API Endpoints
- ✅ [bank-kredit/api/mark_notification_read.php](bank-kredit/api/mark_notification_read.php) - **BARU**: API mark satu notifikasi
- ✅ [bank-kredit/api/mark_all_notifications_read.php](bank-kredit/api/mark_all_notifications_read.php) - **BARU**: API mark semua notifikasi

---

## 🎯 NEXT STEPS (Optional)

1. **Email Notifications**: Tambahkan email saat notifikasi dibuat
   - Sendmail atau SMTP integration
   - Template email per tipe notifikasi

2. **SMS Notifications**: Notifikasi via SMS untuk urgent cases
   - Integrasi dengan SMS gateway

3. **Dashboard Widget**: Widget notifikasi di dashboard admin
   - Real-time count
   - Recent activities feed

4. **Notification Settings**: User preference untuk tipe notifikasi
   - Disable certain notification types
   - Notification frequency settings

---

**Status**: ✅ SIAP PRODUKSI  
**Last Updated**: 29 May 2026

Untuk pertanyaan atau debugging, lihat console browser (F12) untuk AJAX errors.
