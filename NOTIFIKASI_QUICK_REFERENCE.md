# 🔔 SISTEM NOTIFIKASI - RINGKASAN QUICK REFERENCE

**Status**: ✅ **AKTIF & FUNGSIONAL**

---

## 📊 Dashboard Cepat

### Fitur yang Tersedia
| Fitur | Status | File |
|-------|--------|------|
| Database Table | ✅ Live | `notifications` table |
| Bell Component | ✅ Aktif | [notification_bell.php](includes/notification_bell.php) |
| Notification Center | ✅ Aktif | [notifications/list.php](notifications/list.php) |
| Mark Read API | ✅ Aktif | [api/mark_notification_read.php](api/mark_notification_read.php) |
| Mark All API | ✅ Aktif | [api/mark_all_notifications_read.php](api/mark_all_notifications_read.php) |
| Backend Functions | ✅ 6 Active | [functions.php](includes/functions.php) |

---

## 🎯 Tipe Notifikasi

```
✓ submitted     → Pengajuan baru masuk
→ auto_routed   → Diteruskan otomatis
✓ approved      → Disetujui
✗ rejected      → Ditolak
✏ revised       → Perlu revisi
✓ completed     → Selesai diproses
```

---

## 👥 Workflow: Siapa Dapat Notifikasi?

```
1. Analis Submit
   └─→ [Notif] Kepatuhan: "Pengajuan baru dari Analis X"

2. Kepatuhan Selesai Assessment
   └─→ [Notif] Kasubag Analis: "Assessment siap untuk review"

3. Kasubag Approve
   └─→ [Notif] Kabag: "Siap untuk persetujuan Kabag"

4. Kabag Approve
   └─→ [Notif] Kadiv: "Siap untuk final approval"

5. Kadiv Approve (FINAL)
   └─→ [Notif] Analis: "Pengajuan SELESAI - APPROVED"
```

---

## 🔧 Backend Functions (6 Core)

| # | Function | Fungsi |
|---|----------|--------|
| 1 | `createNotification()` | Buat notifikasi baru |
| 2 | `getUnreadNotifications()` | Ambil notifikasi unread (max 50) |
| 3 | `getUnreadNotificationCount()` | Hitung total notifikasi unread |
| 4 | `markNotificationAsRead()` | Mark 1 notifikasi sebagai dibaca |
| 5 | `markAllNotificationsAsRead()` | Mark semua notifikasi sebagai dibaca |
| 6 | `notifyNextRole()` | Auto-notify role berikutnya |

**Lokasi**: [includes/functions.php](includes/functions.php) (lines 1054+)

---

## 🎨 UI Components

### Bell Icon (Navbar)
```
Location: [includes/notification_bell.php](includes/notification_bell.php)
- Red badge dengan count unread
- Dropdown: 5 notifikasi terakhir
- Color-coded badges (green=approved, blue=routed, red=rejected, orange=revised)
- Klik → mark as read + redirect
```

### Notification Center
```
Location: [notifications/list.php](notifications/list.php)
- Halaman pusat dengan semua notifikasi
- Pagination: 20 per halaman
- Filter: unread / semua
- Button "Tandai semua dibaca"
- Relative time display
```

---

## 📡 API Endpoints

### 1. Mark Single Notification
```
POST /api/mark_notification_read.php
Parameters: id_notification, csrf_token
Response: { success: true/false, message: "..." }
```

### 2. Mark All Notifications
```
POST /api/mark_all_notifications_read.php
Parameters: csrf_token
Response: { success: true/false, message: "..." }
```

---

## 🗄️ Database Schema

```
notifications:
  id_notification      INT PRIMARY KEY
  id_user             INT FOREIGN KEY → users
  id_pengajuan        INT FOREIGN KEY → pengajuan_kredit
  tipe_notifikasi     VARCHAR (submitted, approved, rejected, etc)
  judul_notifikasi    VARCHAR
  pesan_notifikasi    TEXT
  role_source         VARCHAR
  role_target         VARCHAR
  is_read             TINYINT (0=unread, 1=read)
  created_at          TIMESTAMP
  read_at             TIMESTAMP

Indexes:
  - idx_notif_user_read (user + is_read)
  - idx_notif_tipe_created (type + date)
  - idx_notif_pengajuan (pengajuan id)
```

---

## ⚙️ Cara Menggunakan

### Untuk Backend Developer

```php
// 1. Buat notifikasi baru
createNotification(
    $id_user,
    $id_pengajuan,
    'approved',
    'Pengajuan Disetujui',
    'Pengajuan atas nama Budi telah disetujui',
    'kasubag_analis',
    'kabag'
);

// 2. Auto-notify role berikutnya
notifyNextRole($id_pengajuan, 'kasubag_analis', 'approved');

// 3. Get notifikasi di navbar
$count = getUnreadNotificationCount($user_id);
$notifications = getUnreadNotifications($user_id, 5);
```

### Untuk Frontend Developer

```javascript
// Mark as read (AJAX)
fetch('/api/mark_notification_read.php', {
    method: 'POST',
    body: 'id_notification=123&csrf_token=xxx'
})
.then(r => r.json())
.then(d => console.log(d));

// Mark all as read
fetch('/api/mark_all_notifications_read.php', {
    method: 'POST',
    body: 'csrf_token=xxx'
})
.then(r => r.json())
.then(d => location.reload());
```

---

## 🧪 Quick Test Checklist

- [ ] Load aplikasi → bell icon visible di navbar
- [ ] Buka notification dropdown → lihat notifikasi
- [ ] Klik notifikasi → redirect + mark as read
- [ ] Buka /notifications/list.php → notification center
- [ ] Filter unread/all → working
- [ ] Pagination → working
- [ ] "Tandai semua dibaca" → semua mark as read
- [ ] Badge count → update otomatis

---

## 🚨 Troubleshooting

### Problem: Badge tidak muncul
**Solution**: 
- Check: `getUnreadNotificationCount()` returns > 0
- Verify: `is_read` field di database
- Check: notifications table exists

### Problem: Notifikasi tidak dibuat
**Solution**:
- Check: `createNotification()` being called
- Verify: user_id valid
- Check: pengajuan_kredit id valid
- Error logs di application

### Problem: Mark as read tidak berfungsi
**Solution**:
- Check: CSRF token valid
- Verify: user authenticated
- Check: notification ownership (verify api)
- Test: Direct SQL update

### Problem: Dropdown tidak tampil
**Solution**:
- Check: notification_bell.php included di navbar
- Verify: JavaScript functions loaded
- Check: no console errors (F12)
- Verify: CSS loaded properly

---

## 📁 File Structure

```
bank-kredit/
├── includes/
│   ├── notification_bell.php      [BARU] Bell component
│   ├── functions.php              [MODIFIED] +6 functions
│   ├── schema_realtime_migrate.php [MODIFIED] Migration
│   └── navbar.php                 [MODIFIED] Include bell
├── notifications/
│   └── list.php                   [BARU] Notification center
├── api/
│   ├── mark_notification_read.php [BARU] API endpoint 1
│   └── mark_all_notifications_read.php [BARU] API endpoint 2
├── database.sql                   [MODIFIED] +notifications table
└── LAPORAN_HASIL_NOTIFIKASI.md   [BARU] Full report
```

---

## 💡 Tips

1. **Performance**: Notifikasi hanya load 5 di dropdown (lightweight)
2. **Cleanup**: Archive notifikasi lama setiap bulan
3. **Testing**: Gunakan browser DevTools > Network tab untuk debug API calls
4. **Security**: Semua API endpoint punya CSRF + permission check
5. **UX**: Relative time lebih user-friendly (baru saja vs 2026-05-29 12:00:00)

---

## 📞 Next Steps

1. ✅ Testing di production dengan real users
2. ✅ Monitor notification volume per role
3. ✅ Collect user feedback tentang UX
4. ✅ Consider: Notification email digest (future enhancement)
5. ✅ Consider: Notification preferences per user (future enhancement)

---

**Last Updated**: 29 Mei 2026  
**Status**: ✅ PRODUCTION READY
