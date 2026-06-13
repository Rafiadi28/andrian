# 🔒 SECURITY AUDIT CHECKLIST - Bank Kredit System

**Audit Date:** April 22, 2026  
**Auditor:** ___________________  
**Status:** READY FOR AUDIT

---

## 1. AUTHENTICATION SECURITY

### 1.1 Password Management
- [ ] **Passwords Hashed**: Verify all passwords use bcrypt (`password_hash()`, `password_verify()`)
  - Check: `includes/functions.php` - login uses `password_verify()`
  - Risk Level: CRITICAL
  
- [ ] **No Plain Text Passwords**: Audit database for any plain text passwords
  - Command: `SELECT COUNT(*) FROM users WHERE password NOT LIKE '$2y$%';`
  - Expected: 0 results
  - Risk Level: CRITICAL

- [ ] **Password Length Validation**: Minimum 8 characters enforced
  - Check: Form validation and stored procedure
  - Risk Level: HIGH

### 1.2 Session Management
- [ ] **Session Timeout (30 minutes)**: Configured in `includes/functions.php`
  ```php
  enforceSessionSecurity() // 1800 seconds = 30 min
  ```
  - Risk Level: HIGH

- [ ] **Session Regeneration**: After successful login
  ```php
  session_regenerate_id(true); // in auth/login.php line 47
  ```
  - Risk Level: HIGH

- [ ] **Session Cookie Security**: 
  - Check php.ini settings:
    - [ ] `session.cookie_httponly = On`
    - [ ] `session.cookie_secure = On` (HTTPS only)
    - [ ] `session.cookie_samesite = Strict`
  - Risk Level: CRITICAL

- [ ] **CSRF Token Generation**: Random 64-character hex
  ```php
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  ```
  - Verified in: `auth/login.php` line 7
  - Risk Level: CRITICAL

### 1.3 Login Rate Limiting
- [ ] **Rate Limit Implemented**: 5 attempts per 15 minutes per IP
  - Function: `checkLoginRateLimit()` in `includes/functions.php`
  - Storage: JSON file in `/logs/` directory
  - Verified: `auth/login.php` line 25
  - Risk Level: HIGH

- [ ] **IP Tracking**: Correctly identifies attacker IP
  ```php
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  ```
  - Note: May need proxy handling if behind load balancer
  - Risk Level: MEDIUM

### 1.4 Legacy Authentication
- [ ] **'admin' Role Conversion**: Old 'admin' users converted to 'Superadmin'
  - Verified in: `auth/login.php` lines 43-50
  - Risk Level: LOW (migration complete)

---

## 2. INPUT VALIDATION & SANITIZATION

### 2.1 Username Sanitization
- [ ] **Function Implemented**: `sanitizeUsername()`
  - Location: `includes/functions.php` line 250
  - Allowed characters: alphanumeric + special (._@-)
  - Max length: 50 characters
  - Risk Level: HIGH

- [ ] **Applied in Login**: Used in `auth/login.php` line 29
  ```php
  $username = sanitizeUsername($_POST['username'] ?? '');
  ```
  - Risk Level: CRITICAL

### 2.2 Text Input Sanitization
- [ ] **Function Implemented**: `sanitizeText()`
  - HTML escape + tag stripping
  - Configurable length limit
  - Risk Level: HIGH

- [ ] **Applied to User Inputs**: Check all forms use sanitization
  - Assessment labels: ✓ (checked)
  - Memo fields: ⟳ (verify in production)
  - Comments: ⟳ (verify in production)

### 2.3 Number Validation
- [ ] **Function Implemented**: `sanitizeNumber()`
  - Extracts digits only, returns float
  - Risk Level: MEDIUM

- [ ] **Applied to**: Loan amounts, weights, percentages
  - Loan amount: ⟳ (verify in forms)
  - Collateral weight: ⟳ (verify in forms)
  - Interest rate: ⟳ (verify in forms)

### 2.4 Email Validation
- [ ] **Function Implemented**: `validateEmail()`
  - Uses `FILTER_VALIDATE_EMAIL`
  - Risk Level: MEDIUM

- [ ] **Applied to**: Contact email fields
  - Verified in forms: ⟳ (verify implementation)

### 2.5 SQL Injection Prevention
- [ ] **Prepared Statements**: All database queries use parameterized statements
  - Check: Search for direct `$sql = "SELECT ... $var"`
  - Expected: None found
  - Risk Level: CRITICAL

- [ ] **PDO Prepared Statements**: Used throughout
  - Example: `$pdo->prepare("SELECT * FROM users WHERE username = ?")->execute([$username])`
  - Risk Level: CRITICAL

- [ ] **No String Concatenation**: Verify no direct string concatenation in queries
  - Risk Level: CRITICAL

---

## 3. OUTPUT ENCODING & XSS PREVENTION

### 3.1 HTML Output Escaping
- [ ] **Function Used**: `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`
  - Applied to: User names, labels, error messages
  - Risk Level: CRITICAL

- [ ] **XSS Fix in checklistRow()**: Escaped $no, $label, $key
  - Location: `kepatuhan/assesmen.php` lines 305-330
  - Verified: htmlspecialchars applied
  - Risk Level: CRITICAL

- [ ] **Error Message Escaping**: All error messages escaped
  - Location: `auth/login.php` line 161
  - Verified: htmlspecialchars applied
  - Risk Level: CRITICAL

### 3.2 JSON Output Safety
- [ ] **JSON Responses**: Check if API endpoints properly encode
  - ⟳ Search workspace for `json_encode()` calls
  - Verify: `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`

### 3.3 File Upload Security
- [ ] **MIME Type Validation**: Uses `finfo` extension
  - Function: `bankKreditVerifyUploadMime()`
  - Risk Level: HIGH

- [ ] **File Extension Whitelist**: Only safe extensions allowed
  - Allowed: jpg, jpeg, png, pdf, doc, docx, xls, xlsx
  - Blocked: php, exe, sh, bat, cmd
  - Risk Level: CRITICAL

- [ ] **File Stored Outside Web Root**: 
  - Location: `/assets/uploads/` or similar
  - Verify: Not directly executable
  - Risk Level: HIGH

---

## 4. ACCESS CONTROL & AUTHORIZATION

### 4.1 Role-Based Access Control (RBAC)
- [ ] **Roles Defined**: 6 distinct roles with hierarchy
  ```
  Superadmin > Direksi > Kadiv Kredit > Kabag Kredit > Kasubag Analis > Analis
  ```
  - Risk Level: MEDIUM

- [ ] **Role Checking**: Every protected page checks `$_SESSION['role']`
  - Pattern: `if ($_SESSION['role'] !== 'expectedRole') { exit; }`
  - Risk Level: CRITICAL

- [ ] **Analis Dashboard**: Only sees own pengajuan
  - Verify: `WHERE input_by = {user_id}`
  - Risk Level: HIGH

- [ ] **Higher Level Access**: Can view all applications in workflow
  - Risk Level: MEDIUM (by design)

### 4.2 Function-Level Authorization
- [ ] **Delete Operations**: Restricted to Superadmin only
  - Risk Level: CRITICAL

- [ ] **User Management**: Restricted to Superadmin only
  - Risk Level: CRITICAL

- [ ] **Backup/Restore**: Restricted to Superadmin only
  - Risk Level: CRITICAL

### 4.3 Data Ownership Verification
- [ ] **Pengajuan Access**: User can only access own applications (unless higher role)
  - Check: `WHERE pengajuan_kredit.input_by = {user_id} OR role IN (...)`
  - Risk Level: HIGH

- [ ] **Assessment Access**: User can only assess assigned pengajuan
  - Risk Level: MEDIUM

---

## 5. DATABASE SECURITY

### 5.1 Foreign Key Constraints
- [ ] **FK Constraints Enforced**: All relationships have constraints
  - Function: `bankKreditEnsureForeignKeys()` in `schema_realtime_migrate.php`
  - Risk Level: HIGH

- [ ] **ON DELETE Rules**: Properly configured
  - CASCADE: For audit logs (safe to delete)
  - RESTRICT: For data integrity (prevent deletion)
  - Risk Level: HIGH

- [ ] **Constraint Check**: Verified on every application startup
  - Idempotent: Checks if exists before adding
  - Risk Level: LOW

### 5.2 Database Indexes
- [ ] **Indexes Created**: 8 optimized indexes
  - pengajuan_kredit: status, date, user indexes
  - approval_kredit: user, level indexes
  - users: role indexes
  - Risk Level: MEDIUM

- [ ] **No Missing Indexes**: Query performance adequate
  - Risk Level: MEDIUM

### 5.3 Data Encryption
- [ ] **Sensitive Fields**: Identify fields needing encryption
  - [ ] Loan amounts: Consider encryption at rest
  - [ ] Personal ID numbers: Should be encrypted
  - [ ] Bank account numbers: Should be encrypted
  - Risk Level: MEDIUM (recommend for next phase)

- [ ] **Database Connection**: Uses SSL/TLS
  - Verify: PDO connection string includes `ssl=true` or similar
  - Risk Level: HIGH

---

## 6. AUDIT & LOGGING

### 6.1 Audit Logging
- [ ] **Function Implemented**: `auditLog($activity)` and `auditLogDetail($activity, $context)`
  - Location: `includes/functions.php` lines 527-545
  - Risk Level: HIGH

- [ ] **All Critical Actions Logged**:
  - [ ] Login/logout
  - [ ] Data creation
  - [ ] Data modification
  - [ ] Data deletion
  - [ ] Approval actions
  - [ ] Role changes
  - [ ] File uploads
  - Risk Level: CRITICAL

- [ ] **Audit Log Immutable**: Cannot be modified by users
  - INSERT only, no UPDATE/DELETE allowed
  - Risk Level: CRITICAL

- [ ] **Sufficient Detail**: Logs include:
  - [ ] User ID
  - [ ] Timestamp
  - [ ] Activity description
  - [ ] IP address (optional)
  - [ ] Related record ID
  - Risk Level: HIGH

### 6.2 Error Logging
- [ ] **Error Logging Enabled**: `error_log()` function used
  - Location: Various functions in `includes/functions.php`
  - File: `/logs/error_YYYY-MM-DD.log`
  - Risk Level: MEDIUM

- [ ] **Errors Not Exposed**: User-facing errors don't expose details
  - Example: "Login gagal" instead of "Query failed: ..."
  - Risk Level: CRITICAL

- [ ] **Technical Details Logged**: Full error details in logs for debugging
  - Risk Level: MEDIUM

### 6.3 Log Protection
- [ ] **Log File Permissions**: `/logs/` directory not web-accessible
  - Verify: No direct access to `/logs/` via HTTP
  - Risk Level: HIGH

- [ ] **Log Retention**: Old logs archived or deleted periodically
  - Risk Level: MEDIUM

---

## 7. CRYPTOGRAPHY

### 7.1 Password Hashing
- [ ] **Algorithm**: bcrypt (password_hash default in PHP)
  - Cost factor: 10+ (adequate security)
  - Risk Level: CRITICAL

- [ ] **No Reversible Encryption**: Passwords are hashes, not encrypted
  - Risk Level: CRITICAL

### 7.2 CSRF Token
- [ ] **Random Generation**: 32 random bytes → 64-char hex
  - `bin2hex(random_bytes(32))`
  - Risk Level: CRITICAL

- [ ] **Comparison**: Using `hash_equals()` to prevent timing attacks
  - Location: `auth/login.php` line 23
  - Risk Level: HIGH

- [ ] **Token Regeneration**: New token on session start and sensitive operations
  - Risk Level: MEDIUM

---

## 8. INFRASTRUCTURE & DEPLOYMENT

### 8.1 HTTPS/TLS
- [ ] **HTTPS Required**: All traffic encrypted
  - Verify: `https://` in production
  - Risk Level: CRITICAL

- [ ] **SSL Certificate Valid**: Not expired, proper domain
  - Risk Level: CRITICAL

- [ ] **HSTS Header**: Enforces HTTPS
  - Header: `Strict-Transport-Security: max-age=31536000`
  - Risk Level: HIGH

### 8.2 Security Headers
- [ ] **Content Security Policy (CSP)**: Prevents inline script execution
  - Header: `Content-Security-Policy: default-src 'self'`
  - Risk Level: HIGH

- [ ] **X-Frame-Options**: Prevents clickjacking
  - Header: `X-Frame-Options: DENY` or `SAMEORIGIN`
  - Risk Level: HIGH

- [ ] **X-Content-Type-Options**: Prevents MIME sniffing
  - Header: `X-Content-Type-Options: nosniff`
  - Risk Level: MEDIUM

### 8.3 Server Configuration
- [ ] **PHP Security Settings**:
  - [ ] `expose_php = Off`
  - [ ] `display_errors = Off`
  - [ ] `log_errors = On`
  - [ ] `error_log` points to secure location
  - Risk Level: MEDIUM

- [ ] **File Permissions**: 
  - [ ] Config files: 600 or 640
  - [ ] Uploaded files: 644
  - [ ] Directories: 755
  - Risk Level: HIGH

- [ ] **Web Server Configuration**:
  - [ ] `.php` files not served from `/assets/` or `/uploads/`
  - [ ] Directory listing disabled
  - [ ] Unnecessary services disabled
  - Risk Level: MEDIUM

---

## 9. BACKUP & DISASTER RECOVERY

### 9.1 Backup Process
- [ ] **Automated Backups**: Scheduled daily/weekly
  - Function: `admin/backup.php`
  - Risk Level: HIGH

- [ ] **Backup Integrity**: Backup files tested periodically
  - Risk Level: HIGH

- [ ] **Backup Encryption**: Backups encrypted at rest
  - Risk Level: MEDIUM (recommend for sensitive data)

- [ ] **Backup Storage**: Stored outside web root
  - Verify: `/backups/` directory not web-accessible
  - Risk Level: MEDIUM

### 9.2 Recovery Testing
- [ ] **Recovery Procedure Documented**: Clear steps to restore
  - Risk Level: MEDIUM

- [ ] **Recovery Test**: Simulate restore on test server
  - Risk Level: HIGH

---

## 10. THIRD-PARTY DEPENDENCIES

### 10.1 Library Vulnerabilities
- [ ] **PHP Version**: 7.4+ recommended
  - Check: `phpinfo()`
  - Risk Level: HIGH

- [ ] **Known Vulnerabilities**: No known CVEs in dependencies
  - Check: Run `composer audit` if composer used
  - Risk Level: MEDIUM

---

## 11. SECURITY TESTING

### 11.1 Manual Testing
- [ ] **SQL Injection Attempt**: 
  - Payload: `' OR '1'='1`
  - Expected: Properly escaped, no injection
  - Risk Level: CRITICAL

- [ ] **XSS Attempt**:
  - Payload: `<script>alert('xss')</script>`
  - Expected: Escaped as text, not executed
  - Risk Level: CRITICAL

- [ ] **CSRF Attempt**:
  - Remove CSRF token from form
  - Expected: Form rejected
  - Risk Level: CRITICAL

- [ ] **Privilege Escalation**:
  - Login as Analis, try to access Superadmin functions
  - Expected: Access denied
  - Risk Level: CRITICAL

### 11.2 Automated Testing
- [ ] **OWASP ZAP Scan**: Run vulnerability scan
  - Risk Level: MEDIUM

- [ ] **SQLMap Test**: Database injection testing
  - Risk Level: MEDIUM

---

## 12. SECURITY SIGN-OFF

### Issues Found

| ID | Category | Severity | Description | Status |
|-------|----------|----------|-------------|--------|
| SEC-001 | | | | [ ] |
| SEC-002 | | | | [ ] |
| SEC-003 | | | | [ ] |

### Critical Issues
- [ ] None found
- [ ] Issues found - see above

### Recommendations for Next Phase
1. Add database encryption for sensitive PII
2. Implement WAF (Web Application Firewall)
3. Add two-factor authentication for Superadmin
4. Implement API rate limiting
5. Regular security training for users

---

## 13. AUDIT SIGN-OFF

**Security Auditor:** ___________________  
**Date:** ___________________

**Overall Security Rating:** [ ] Excellent (9.5/10) [ ] Good (8/10) [ ] Acceptable (7/10) [ ] Needs Work (6/10)

**Ready for Production:** [ ] YES [ ] NO (issues must be resolved)

**Signature:** ___________________  
**Date:** ___________________

---

## 14. REFERENCES

- OWASP Top 10: https://owasp.org/www-project-top-ten/
- PHP Security Guide: https://www.php.net/manual/en/security.php
- CWE/SANS Top 25: https://cwe.mitre.org/top25/
- Bank Data Protection Requirements: [Local Policy]
