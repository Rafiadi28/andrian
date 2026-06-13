# 📋 UAT TESTING PLAN - Bank Kredit Approval System

**Test Date:** [Schedule 1-2 hours]  
**Prepared:** April 22, 2026  
**Status:** READY FOR EXECUTION

---

## 1. TEST SCOPE

### Users to Test
- [ ] **Superadmin** - Full system access, user management
- [ ] **Analis** - Input credit applications, initial assessment
- [ ] **Kasubag Analis** - Review and approve analyses
- [ ] **Kabag Kredit** - Credit department head approvals
- [ ] **Kadiv Kredit** - Division director level approval
- [ ] **Direksi** - Executive final approval

### Systems to Test
- [ ] Authentication & Security
- [ ] Data Input & Validation
- [ ] Approval Workflows
- [ ] Reporting & History
- [ ] Mobile Responsiveness
- [ ] Error Handling

---

## 2. AUTHENTICATION TESTS

### 2.1 Login Flow
**Test Case:** USER-001 - Successful Login
```
Steps:
1. Navigate to /auth/login.php
2. Enter valid username: "analis"
3. Enter valid password: "password"
4. Click "Masuk ke Dashboard"

Expected Result:
✓ Redirects to /analis/dashboard.php
✓ Session created with user_id and role
✓ Audit log entry created: "Login ke sistem"
✓ Last activity timestamp updated
```

**Test Case:** USER-002 - Failed Login Attempt
```
Steps:
1. Navigate to /auth/login.php
2. Enter valid username but wrong password
3. Click "Masuk ke Dashboard"

Expected Result:
✓ Error message: "Username atau Password salah!"
✓ User NOT logged in
✓ Audit log entry: "Login gagal (username: analis)"
✓ Page reloads with error message highlighted
```

**Test Case:** USER-003 - Rate Limiting (5 Failed Attempts)
```
Steps:
1. Try login 5 times with wrong password
2. Attempt 6th login

Expected Result:
✓ After 5 failed attempts within 15 minutes
✓ 6th attempt shows: "Terlalu banyak upaya login gagal. Coba lagi dalam 15 menit."
✓ Account temporarily locked for IP
✓ After 15 minutes, login works again
```

**Test Case:** USER-004 - CSRF Token Validation
```
Steps:
1. Open login page, inspect HTML
2. Attempt to submit form without CSRF token (manually craft POST)

Expected Result:
✓ Error: "Token keamanan tidak valid. Muat ulang halaman lalu coba lagi."
✓ Login denied regardless of valid credentials
```

**Test Case:** USER-005 - Session Timeout (30 minutes)
```
Steps:
1. Login successfully
2. Wait 30 minutes (or manually set session expiration)
3. Try to access protected page

Expected Result:
✓ Session automatically destroyed
✓ Redirected to login.php with ?expired=1
✓ User prompted to login again
```

### 2.2 Logout Flow
**Test Case:** USER-006 - Successful Logout
```
Steps:
1. Login as any user
2. Click logout button
3. Try to access dashboard directly

Expected Result:
✓ Session cleared
✓ Redirected to login.php
✓ Audit log entry: "Logout dari sistem"
✓ Cannot access dashboard without re-login
```

---

## 3. DATA INPUT & VALIDATION TESTS

### 3.1 Form Validation
**Test Case:** INPUT-001 - Required Field Validation
```
Steps:
1. Login as Analis
2. Navigate to "Input Pengajuan Kredit"
3. Try to submit form with empty required fields

Expected Result:
✓ HTML5 validation prevents submission
✓ Error highlights on required fields
✓ User-friendly error messages displayed
```

**Test Case:** INPUT-002 - Number Field Validation
```
Steps:
1. In credit amount field, enter: "abc123"
2. Try to submit

Expected Result:
✓ Field rejects non-numeric input
✓ Only digits accepted
✓ Can enter decimal points for currency
```

**Test Case:** INPUT-003 - Email Validation
```
Steps:
1. Enter invalid email: "invalid.email@"
2. Try to submit

Expected Result:
✓ Email validation triggers
✓ Error message: format must be valid
✓ Accepts only valid email format
```

**Test Case:** INPUT-004 - Date Field Validation
```
Steps:
1. Enter invalid date: "32-13-2026"
2. Try to submit

Expected Result:
✓ Date validation prevents invalid dates
✓ Only valid dates accepted
✓ Format enforced: DD-MM-YYYY or calendar picker
```

### 3.2 XSS Protection
**Test Case:** SECURITY-001 - XSS Prevention in Checklist
```
Steps:
1. Navigate to Compliance Assessment
2. Check if labels/fields contain proper escaping
3. Inspect HTML source for any unescaped content

Expected Result:
✓ All user inputs escaped with htmlspecialchars()
✓ No <script> tags in rendered output
✓ Special characters displayed safely (< > & " ')
```

**Test Case:** SECURITY-002 - XSS in Error Messages
```
Steps:
1. Enter payload: '<script>alert("xss")</script>'
2. Submit form
3. Observe error message

Expected Result:
✓ Script not executed
✓ Payload displayed as text
✓ htmlspecialchars() applied to error output
```

---

## 4. APPROVAL WORKFLOW TESTS

### 4.1 Single-Step Approval (Analis)
**Test Case:** APPROVAL-001 - Analis Review & Assessment
```
Steps:
1. Login as Analis
2. Select pending pengajuan
3. Input compliance assessment data
4. Submit assessment form

Expected Result:
✓ Data saved to assessment_kepatuhan table
✓ Status changes to "Pending Kasubag Analis"
✓ Audit log created with user and timestamp
✓ Form becomes read-only after submission
```

### 4.2 Multi-Step Approval Chain
**Test Case:** APPROVAL-002 - Complete Approval Flow
```
Steps:
1. Create new pengajuan as Analis
2. Submit assessment
3. Login as Kasubag Analis
4. Review and approve
5. Login as Kabag Kredit
6. Review and approve
7. Login as Kadiv Kredit
8. Review and approve
9. Login as Direksi
10. Final approval

Expected Result:
✓ Each level can only access their level
✓ Status updates after each approval
✓ Comments preserved through chain
✓ Final status: "Disetujui" or "Ditolak"
✓ All approvals logged in audit_log
✓ Email notifications sent (if configured)
```

### 4.3 Rejection Flow
**Test Case:** APPROVAL-003 - Rejection with Revision Request
```
Steps:
1. At any approval level, click "Tolak"
2. Enter rejection reason
3. Submit

Expected Result:
✓ Status reverts to "Needs Revision"
✓ Goes back to previous level for revision
✓ Original Analis notified
✓ Revision history preserved
```

---

## 5. ROLE-BASED ACCESS CONTROL TESTS

### 5.1 Permission Testing
**Test Case:** RBAC-001 - Analis Permissions
```
Expected Access:
✓ Can view own pengajuan
✓ Can input new applications
✓ Can edit own assessments (before submission)
✓ Can view approval history

Expected Denial:
✗ Cannot access admin users page
✗ Cannot view other analis's data
✗ Cannot approve (no approval button)
✗ Cannot delete applications
```

**Test Case:** RBAC-002 - Kabag Kredit Permissions
```
Expected Access:
✓ Can view all applications in workflow
✓ Can approve/reject applications
✓ Can view credit reports
✓ Can reassign applications

Expected Denial:
✗ Cannot edit assessment data
✗ Cannot delete applications
✗ Cannot access admin panel
✗ Cannot manage users
```

**Test Case:** RBAC-003 - Superadmin Permissions
```
Expected Access:
✓ Full access to all functions
✓ Can manage users (create/edit/deactivate)
✓ Can view all reports
✓ Can access system logs
✓ Can run backups

Expected Denial:
✗ None - has full system access
```

---

## 6. DATA INTEGRITY TESTS

### 6.1 Foreign Key Constraints
**Test Case:** DATA-001 - Orphaned Records Prevention
```
Steps:
1. Create pengajuan_kredit
2. Attempt to manually delete user via SQL

Expected Result:
✓ Deletion rejected with FK constraint error
✓ User record cannot be deleted if referenced
✓ Data integrity maintained
```

**Test Case:** DATA-002 - Cascade Delete
```
Steps:
1. Create pengajuan with all related records:
   - jaminan_tanah_bangunan
   - analisa_neraca
   - assessment_kepatuhan
2. Delete the pengajuan
3. Check related records

Expected Result:
✓ All related records automatically deleted
✓ No orphaned records remain
✓ Audit trail preserved
```

### 6.2 Data Consistency
**Test Case:** DATA-003 - Transaction Integrity
```
Steps:
1. Create pengajuan with multiple sections
2. Simulate network interruption mid-transaction

Expected Result:
✓ Either all data saved or none
✓ No partial/corrupted records
✓ Database remains consistent
```

---

## 7. MOBILE RESPONSIVENESS TESTS

### 7.1 Responsive Design
**Test Case:** MOBILE-001 - Mobile View (375px)
```
Steps:
1. Open application on iPhone (375px width)
2. Navigate through all pages

Expected Result:
✓ All elements visible without horizontal scroll
✓ Touch targets ≥44px
✓ Font size readable (16px minimum)
✓ Navigation accessible
✓ Forms usable on mobile
```

**Test Case:** MOBILE-002 - Tablet View (768px)
```
Steps:
1. Open application on iPad (768px width)
2. Check layout and navigation

Expected Result:
✓ Two-column layout where appropriate
✓ All content visible
✓ Navigation optimized for tablet
```

**Test Case:** MOBILE-003 - Desktop View (1024px+)
```
Steps:
1. Open application on desktop
2. Verify optimal layout

Expected Result:
✓ Multi-column layout used
✓ Sidebar visible
✓ All features accessible
```

---

## 8. PERFORMANCE & STABILITY TESTS

### 8.1 Response Time
**Test Case:** PERF-001 - Page Load Time
```
Steps:
1. Measure load time for dashboard (should be <2 seconds)
2. Measure load time for list pages (should be <3 seconds)

Expected Result:
✓ Dashboard: <2 seconds
✓ List pages: <3 seconds
✓ Form submission: <1 second
```

### 8.2 Concurrent Users
**Test Case:** PERF-002 - Multiple Simultaneous Users
```
Steps:
1. Have 3 users login simultaneously
2. All perform different actions
3. Monitor for conflicts or slowdown

Expected Result:
✓ All users can work independently
✓ No session conflicts
✓ No performance degradation
```

---

## 9. ERROR HANDLING TESTS

### 9.1 Error Messages
**Test Case:** ERROR-001 - User-Friendly Error Messages
```
Steps:
1. Trigger various errors:
   - Database connection error
   - Missing required field
   - Invalid input
   - Access denied

Expected Result:
✓ All errors show user-friendly messages
✓ Technical details not exposed
✓ Guidance provided on how to fix
✓ Error logged but user message clear
```

### 9.2 Graceful Degradation
**Test Case:** ERROR-002 - Missing Optional Features
```
Steps:
1. If email notification disabled, test approval workflow

Expected Result:
✓ Approval still works without email
✓ No errors or crashes
✓ Graceful handling of missing features
```

---

## 10. REPORTING & HISTORY TESTS

### 10.1 Audit Logs
**Test Case:** REPORT-001 - Audit Log Completeness
```
Steps:
1. Perform various user actions:
   - Login/logout
   - Create pengajuan
   - Submit assessment
   - Approve/reject
2. Check audit_log table

Expected Result:
✓ All actions logged with:
   - User ID
   - Activity description
   - Timestamp
   - Status
✓ Entries cannot be modified
✓ Accessible only to Superadmin
```

### 10.2 Riwayat (History)
**Test Case:** REPORT-002 - Complete History Trail
```
Steps:
1. Navigate to riwayat page for any application
2. View complete approval history

Expected Result:
✓ All approval steps shown with dates
✓ Comments preserved
✓ Rejection reasons visible
✓ Revision history traceable
```

---

## 11. FILE UPLOAD TESTS

### 11.1 MIME Type Validation
**Test Case:** UPLOAD-001 - Valid File Upload
```
Steps:
1. Upload valid image (JPEG/PNG)
2. Verify file saved

Expected Result:
✓ File accepted and saved
✓ Accessible in application
✓ No security warnings
```

**Test Case:** UPLOAD-002 - Invalid File Type Rejection
```
Steps:
1. Attempt to upload .txt file with image upload
2. Attempt to upload .exe file
3. Attempt to upload .php file

Expected Result:
✓ All rejected with error message
✓ File not saved
✓ Error: "Jenis file tidak didukung"
✓ Only allowed types accepted
```

### 11.2 File Size Limits
**Test Case:** UPLOAD-003 - File Size Validation
```
Steps:
1. Upload large file (>10MB)
2. Verify rejection

Expected Result:
✓ File rejected
✓ Error message with size limit
✓ User informed of max file size
```

---

## 12. TEST EXECUTION LOG

| Test ID | Description | Start | End | Result | Notes |
|---------|-------------|-------|-----|--------|-------|
| USER-001 | Successful Login | | | [ ] | |
| USER-002 | Failed Login | | | [ ] | |
| USER-003 | Rate Limiting | | | [ ] | |
| INPUT-001 | Required Fields | | | [ ] | |
| SECURITY-001 | XSS Prevention | | | [ ] | |
| APPROVAL-001 | Single Approval | | | [ ] | |
| APPROVAL-002 | Full Chain | | | [ ] | |
| RBAC-001 | Role Permissions | | | [ ] | |
| MOBILE-001 | Mobile View | | | [ ] | |
| PERF-001 | Load Time | | | [ ] | |
| REPORT-001 | Audit Logs | | | [ ] | |
| UPLOAD-001 | File Upload | | | [ ] | |

---

## 13. UAT SIGN-OFF

**Test Coordinator:** ___________________  
**Date:** ___________________

**Signature (Approved):** ___________________  
**Date:** ___________________

**Critical Issues Found:** ☐ None  
**Recommendations:** ___________________

---

## 14. NEXT STEPS

- [ ] Schedule UAT session (1-2 hours)
- [ ] Assemble test users (one per role)
- [ ] Provide test credentials
- [ ] Walk through test plan
- [ ] Document all issues
- [ ] Prioritize and fix critical issues
- [ ] Re-test critical areas
- [ ] Obtain sign-off
- [ ] Proceed to deployment
