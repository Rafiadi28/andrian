# 🚀 DEPLOYMENT GUIDE - SISTEM NOTIFIKASI

**Version**: 1.0  
**Date**: 29 Mei 2026  
**Status**: Ready for Production

---

## 📋 PRE-DEPLOYMENT CHECKLIST

### Database Setup
- [ ] Backup database existing (CRITICAL)
  ```bash
  mysqldump -u root -p bank_kredit_db > backup_$(date +%Y%m%d).sql
  ```

- [ ] Verify `notifications` table exists
  ```sql
  SELECT COUNT(*) FROM information_schema.tables 
  WHERE table_name='notifications' AND table_schema='bank_kredit_db';
  ```

- [ ] Verify schema migration runs
  - Check: `includes/schema_realtime_migrate.php` included di bootstrap
  - Verify: No errors di application logs

- [ ] Check indexes created
  ```sql
  SHOW INDEX FROM notifications;
  -- Should show: idx_notif_user_read, idx_notif_tipe_created, idx_notif_pengajuan
  ```

### File Deployment
- [ ] Copy files ke server:
  ```
  ├── includes/notification_bell.php
  ├── includes/functions.php (updated with 6 functions)
  ├── notifications/list.php
  ├── api/mark_notification_read.php
  ├── api/mark_all_notifications_read.php
  └── includes/schema_realtime_migrate.php
  ```

- [ ] Verify includes/navbar.php has bell component
  ```php
  <?php include __DIR__ . '/notification_bell.php'; ?>
  ```

- [ ] Check file permissions (755 untuk scripts)
  ```bash
  chmod 755 api/mark_*.php
  chmod 755 notifications/list.php
  ```

### Environment Verification
- [ ] Database connection working
- [ ] PDO connection active
- [ ] Session management working
- [ ] CSRF token generation enabled
- [ ] Error logging enabled

### Code Review
- [ ] All 6 functions implemented in functions.php
- [ ] All API endpoints have proper error handling
- [ ] UI components have styling
- [ ] JavaScript functions defined
- [ ] No console errors in browser DevTools

---

## 🔧 DEPLOYMENT STEPS

### Step 1: Database Migration (5 minutes)

#### Option A: Automatic Migration
```php
// Already configured in includes/schema_realtime_migrate.php
// Will run automatically on first page access
// Check application logs for confirmation
```

#### Option B: Manual SQL
```sql
-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id_notification INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    id_pengajuan INT NOT NULL,
    tipe_notifikasi VARCHAR(50) NOT NULL,
    judul_notifikasi VARCHAR(255) NOT NULL,
    pesan_notifikasi TEXT,
    role_source VARCHAR(50),
    role_target VARCHAR(50),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE,
    INDEX idx_notif_user_read (id_user, is_read),
    INDEX idx_notif_tipe_created (tipe_notifikasi, created_at),
    INDEX idx_notif_pengajuan (id_pengajuan)
);
```

### Step 2: Backend Deployment (10 minutes)

#### Update functions.php
- [ ] Add 6 notification functions
- [ ] Verify syntax: `php -l includes/functions.php`
- [ ] Test: Call each function directly

```php
// Quick test
$result = createNotification(1, 1, 'submitted', 'Test', 'Test message');
echo $result > 0 ? "✓ Function works" : "✗ Failed";
```

#### Deploy API endpoints
- [ ] Copy `/api/mark_notification_read.php`
- [ ] Copy `/api/mark_all_notifications_read.php`
- [ ] Verify HTTP 200 responses

### Step 3: Frontend Deployment (10 minutes)

#### Add Bell Component
- [ ] Copy `includes/notification_bell.php`
- [ ] Include di `includes/navbar.php`
```php
<?php include __DIR__ . '/notification_bell.php'; ?>
```
- [ ] Test: Visual check bell icon appears

#### Add Notification Center
- [ ] Copy `notifications/list.php`
- [ ] Create directory if not exists: `mkdir notifications/`
- [ ] Test: Navigate to `/notifications/list.php`

### Step 4: Integration Testing (20 minutes)

#### Test Each Component
```
1. [ ] Bell icon visible di navbar
2. [ ] Badge shows unread count
3. [ ] Dropdown toggles
4. [ ] Notifications display correctly
5. [ ] Pagination works di list.php
6. [ ] Click notification redirects
7. [ ] Mark as read updates DB
8. [ ] API responses valid JSON
```

#### Test Workflow
```
1. [ ] Submit pengajuan → notification created
2. [ ] Assessment submit → notification routed
3. [ ] Approval flow → notifications cascade
4. [ ] Rejection → notification to original user
5. [ ] Revision request → notification created
6. [ ] Final approval → completion notification
```

#### Test Security
```
1. [ ] API rejects unauthorized users (401)
2. [ ] API rejects invalid CSRF (403)
3. [ ] Permission check works (can't access other user's notif)
4. [ ] XSS prevention (special chars escaped)
5. [ ] SQL injection prevention (prepared statements)
```

### Step 5: Production Verification (15 minutes)

#### Performance Check
```bash
# Check database query speed
mysql> EXPLAIN SELECT * FROM notifications WHERE id_user = 1 AND is_read = 0;

# Should use idx_notif_user_read index
# Rows should be low
# Type should be 'ref' or 'eq_ref'
```

#### Monitoring
```
1. [ ] Application logs clean (no errors)
2. [ ] Database connections stable
3. [ ] Page load time acceptable
4. [ ] No memory leaks
5. [ ] Error rate normal
```

#### User Acceptance
```
1. [ ] Demo to admin users
2. [ ] Collect feedback
3. [ ] Address any UX issues
4. [ ] Document gotchas
```

---

## 📊 DEPLOYMENT SUMMARY CHART

```
┌─────────────────────────────────────────┐
│   DEPLOYMENT CHECKLIST - ESTIMATE: 60min│
├─────────────────────────────────────────┤
│ 1. Database Migration          5 min │ ✓
│ 2. Backend Deployment          10 min │ ✓
│ 3. Frontend Deployment         10 min │ ✓
│ 4. Integration Testing         20 min │ ✓
│ 5. Production Verification     15 min │ ✓
├─────────────────────────────────────────┤
│ TOTAL ESTIMATED TIME:        60 min │ ✓
└─────────────────────────────────────────┘
```

---

## 🔍 POST-DEPLOYMENT VERIFICATION

### Immediately After Deploy (30 min)

#### Smoke Test
```javascript
// Open browser console (F12)
// Test notification bell
console.log(document.querySelector('.notification-bell'));  // Should exist

// Test API
fetch('/api/mark_all_notifications_read.php', {
    method: 'POST',
    body: 'csrf_token=test'
}).then(r => r.json()).then(console.log);  // Should return JSON
```

#### Check Logs
```bash
# Application logs
tail -f logs/application.log | grep -i notif

# Database logs
tail -f logs/mysql.log | grep notifications

# Server logs
tail -f /var/log/apache2/error.log
```

#### Admin Check
```sql
-- Verify data integrity
SELECT COUNT(*) FROM notifications;
-- Should be >= 0

SELECT COUNT(DISTINCT id_user) FROM notifications;
-- Should show affected users

SELECT 
  tipe_notifikasi, 
  COUNT(*) as count 
FROM notifications 
GROUP BY tipe_notifikasi;
-- Should show distribution
```

### 24-Hour Monitoring

#### Check Metrics
```
- Notification creation rate (per hour)
- Average query time (should be < 100ms)
- Read rate (% marked as read vs created)
- Error rate (should be < 0.1%)
- User engagement (% users with notifications)
```

#### Sample Queries
```sql
-- Unread notifications by user
SELECT id_user, COUNT(*) as unread_count 
FROM notifications 
WHERE is_read = 0 
GROUP BY id_user 
ORDER BY unread_count DESC;

-- Notification creation over time
SELECT 
  DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as hour,
  COUNT(*) as notifications_created
FROM notifications
GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H:00')
ORDER BY hour DESC;

-- Average read time
SELECT 
  AVG(TIMESTAMPDIFF(SECOND, created_at, read_at)) as avg_read_seconds,
  MIN(TIMESTAMPDIFF(SECOND, created_at, read_at)) as min_read_seconds,
  MAX(TIMESTAMPDIFF(SECOND, created_at, read_at)) as max_read_seconds
FROM notifications
WHERE read_at IS NOT NULL;
```

---

## 🚨 ROLLBACK PROCEDURE (if needed)

### Quick Rollback (< 5 min)

```bash
# 1. Backup current state
cp -r includes/ includes.backup/
cp -r api/ api.backup/
cp -r notifications/ notifications.backup/

# 2. Revert files
rm includes/notification_bell.php
git checkout includes/navbar.php
git checkout includes/functions.php
git checkout api/mark_*.php
rm -rf notifications/

# 3. Restart application
systemctl restart apache2  # or nginx

# 4. Clear cache if any
php artisan cache:clear  # if using Laravel
# or delete temp files
```

### Database Rollback (< 2 min)

```sql
-- Option A: Drop table (REMOVE ALL NOTIFICATIONS)
DROP TABLE IF EXISTS notifications;

-- Option B: Keep table but disable notifications in code
-- (Comment out createNotification() calls)
```

### Verify Rollback
```
1. [ ] Bell icon gone from navbar
2. [ ] No notification links in UI
3. [ ] /notifications/list.php returns 404
4. [ ] API endpoints return 404
5. [ ] No errors in logs
6. [ ] Application stable
```

---

## 💡 DEPLOYMENT TIPS

### General
1. **Always backup database before migration**
2. **Test in staging environment first**
3. **Deploy during low-traffic hours**
4. **Have rollback plan ready**
5. **Monitor for 24 hours after deploy**

### Database
- Use indices for performance
- Regularly cleanup old notifications (> 90 days)
- Monitor table size (can grow large)
- Consider archiving old records

### Frontend
- Test in multiple browsers
- Check mobile responsiveness
- Verify CSS loading
- Test JavaScript in console

### Backend
- Check error logs immediately
- Monitor database connections
- Watch for memory leaks
- Track query performance

### Users
- Notify users about new notification system
- Provide quick tutorial
- Collect feedback
- Address UX issues quickly

---

## 📞 SUPPORT & TROUBLESHOOTING

### Common Issues

#### Issue: Bell icon not showing
```
Solution:
1. Check notification_bell.php included in navbar.php
2. Clear browser cache (Ctrl+Shift+Del)
3. Check console errors (F12)
4. Verify CSS loaded
5. Check file permissions
```

#### Issue: Notifications not creating
```
Solution:
1. Check database table exists: SHOW TABLES;
2. Verify createNotification() called with valid params
3. Check error logs for SQL errors
4. Verify user IDs and pengajuan IDs exist
5. Check PDO connection
```

#### Issue: API returning 403 Forbidden
```
Solution:
1. Verify user logged in
2. Check CSRF token in session
3. Verify user owns the notification
4. Check PHP error logs
5. Test API in Postman with valid token
```

#### Issue: Performance slow
```
Solution:
1. Check indices: SHOW INDEX FROM notifications;
2. Run EXPLAIN on slow queries
3. Check database load (too many connections?)
4. Consider pagination limits
5. Archive old notifications
```

---

## ✅ FINAL CHECKLIST BEFORE GOING LIVE

### Code Quality
- [ ] All functions documented with PHPDoc
- [ ] No console errors in browser
- [ ] No database errors in logs
- [ ] Code follows style guide
- [ ] No hardcoded credentials
- [ ] No debug code left in

### Security
- [ ] CSRF tokens verified
- [ ] Authentication enforced
- [ ] Permission checks working
- [ ] XSS prevention active
- [ ] SQL injection prevention active
- [ ] Input validation present

### Performance
- [ ] Queries use indices
- [ ] Page load < 500ms
- [ ] API responses < 200ms
- [ ] No N+1 queries
- [ ] Memory usage normal
- [ ] No memory leaks

### Usability
- [ ] UI is intuitive
- [ ] Mobile friendly
- [ ] Accessibility OK
- [ ] Help text clear
- [ ] Error messages helpful
- [ ] No broken links

### Documentation
- [ ] README updated
- [ ] API docs complete
- [ ] User guide created
- [ ] Developer guide created
- [ ] Troubleshooting guide included
- [ ] Deployment guide included

---

## 🎉 DEPLOYMENT SUCCESS CRITERIA

| Criteria | Target | Actual | Status |
|----------|--------|--------|--------|
| Zero Critical Issues | 0 | TBD | ? |
| Page Load Time | < 500ms | TBD | ? |
| API Response Time | < 200ms | TBD | ? |
| Error Rate | < 0.1% | TBD | ? |
| User Engagement | > 50% | TBD | ? |
| System Uptime | > 99.9% | TBD | ? |

---

## 📞 ESCALATION CONTACTS

```
For Issues Contact:

1. Database Issues → DBA
   - Name: [TBD]
   - Phone: [TBD]
   - Email: [TBD]

2. Server Issues → DevOps
   - Name: [TBD]
   - Phone: [TBD]
   - Email: [TBD]

3. Application Issues → Development Team
   - Name: [TBD]
   - Phone: [TBD]
   - Email: [TBD]

4. User Issues → Support Team
   - Name: [TBD]
   - Phone: [TBD]
   - Email: [TBD]
```

---

**Deployment Guide Version**: 1.0  
**Last Updated**: 29 Mei 2026  
**Status**: Ready for Implementation

✅ **APPROVED FOR PRODUCTION DEPLOYMENT**
